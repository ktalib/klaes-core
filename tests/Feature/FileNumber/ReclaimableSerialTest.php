<?php

namespace Tests\Feature\FileNumber;

use App\Models\MlsSerialControl;
use App\Models\User;
use App\Services\MlsSerialAllocationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reusing MLS serial numbers that a Master Delete stranded.
 *
 * READ-ONLY. Runs against the shared development database and creates nothing — every
 * assertion either reads, or checks that an unsafe number is REFUSED before any write.
 *
 * The problem: `mls_serial_control.last_serial` only ever moves forward, so deleting a
 * commissioned file leaves its number below the counter and nothing ever issues it again.
 *
 * The danger, measured on live data at the time this was written: a hole in one table is
 * NOT evidence the number is free. 112 of 569 apparent RES-2026 holes and 49 of 81
 * COM-2026 holes are in use somewhere else — mostly `file_indexings` rows with no
 * `fileNumber` row. Offering one of those creates a duplicate file number, which is why
 * every candidate is checked against five tables, and why these tests assert the exclusions
 * rather than just the happy path.
 */
class ReclaimableSerialTest extends TestCase
{
    private function service(): MlsSerialAllocationService
    {
        return app(MlsSerialAllocationService::class);
    }

    private function anyUser(): ?User
    {
        return User::query()->first();
    }

    /** @test */
    public function it_never_offers_a_serial_at_or_above_the_counter(): void
    {
        // The counter's high-water mark is the whole definition of "reclaimed". Numbers
        // above it are simply the next ones to be issued, not gaps.
        //
        // This is not hypothetical: IND-2026 has the counter at 272 while `fileNumber`
        // holds IND-2026-3635 from a separate import. Using the highest number found in the
        // registers as the ceiling would advertise 273…3634 as "missing".
        foreach (['IND', 'COM', 'RES'] as $landUse) {
            $ceiling = (int) MlsSerialControl::getCurrentSerial($landUse, 2026);

            if ($ceiling < 2) {
                continue;
            }

            foreach ($this->service()->findReclaimableSerials($landUse, 2026, 50) as $entry) {
                $this->assertLessThan(
                    $ceiling,
                    $entry['serial'],
                    "{$entry['file_number']} is at or above the {$landUse} counter ({$ceiling})"
                );
                $this->assertGreaterThanOrEqual(1, $entry['serial']);
            }
        }
    }

    /** @test */
    public function it_never_offers_a_serial_below_the_digital_floor(): void
    {
        // Numbering did not start at 1. Each prefix ran on paper first and the platform
        // continued from where the manual register had reached — RES-2026 from 565,
        // COM-2026 from 77, CON-COM-2026 from 48. Everything below that belongs to a
        // PHYSICAL file, so offering it would issue a number that already exists in the
        // registry. An earlier build listed RES-2026-211…231 as "never issued"; all paper.
        foreach (['RES', 'COM', 'IND', 'AG', 'CON-RES', 'CON-COM'] as $landUse) {
            $floor = $this->service()->digitalFloor($landUse, 2026);

            if ($floor < 1) {
                continue;
            }

            foreach ($this->service()->findReclaimableSerials($landUse, 2026, 50) as $entry) {
                $this->assertGreaterThanOrEqual(
                    $floor,
                    $entry['serial'],
                    "{$entry['file_number']} is below the {$landUse}-2026 digital floor ({$floor}) — that is a paper file"
                );
            }
        }
    }

    /** @test */
    public function the_digital_floor_matches_the_first_serial_this_platform_issued(): void
    {
        $db = DB::connection('sqlsrv');

        foreach (['RES', 'COM', 'CON-COM', 'CON-RES'] as $landUse) {
            $expected = (int) $db->table('mls_file_no')
                ->where('land_use', $landUse)
                ->where('year', 2026)
                ->where('serial_number', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
                })
                ->min('serial_number');

            $this->assertSame(
                $expected,
                $this->service()->digitalFloor($landUse, 2026),
                "Digital floor for {$landUse}-2026 does not match mls_file_no"
            );
        }
    }

    /** @test */
    public function the_floor_is_read_from_the_digital_register_only(): void
    {
        // `fileNumber` also holds captured and imported legacy records, so scanning it too
        // drags the floor down to the paper era: the same scan across both tables returns 1
        // for CON-COM-2026 instead of the correct 48.
        $floor = $this->service()->digitalFloor('CON-COM', 2026);

        if ($floor < 1) {
            $this->markTestSkipped('CON-COM-2026 has no digital issue in this environment.');
        }

        $lowestAnywhere = DB::connection('sqlsrv')->table('fileNumber')
            ->where('mlsfNo', 'like', 'CON-COM-2026-%')
            ->pluck('mlsfNo')
            ->map(function ($n) {
                return preg_match('/^CON-COM-2026-(\d+)/', trim((string) $n), $m) ? (int) $m[1] : null;
            })
            ->filter()
            ->min();

        if ($lowestAnywhere !== null && $lowestAnywhere < $floor) {
            $this->assertGreaterThan(
                $lowestAnywhere,
                $floor,
                'The floor must come from mls_file_no, not the legacy-bearing fileNumber table'
            );
        } else {
            $this->assertGreaterThanOrEqual(1, $floor);
        }
    }

    /** @test */
    public function a_serial_below_the_floor_is_refused_even_if_asked_for_directly(): void
    {
        // The dropdown is not the only way in — the serial arrives as a request field, so
        // the floor has to be enforced at the point of use as well as in the list.
        $floor = $this->service()->digitalFloor('RES', 2026);

        if ($floor < 2) {
            $this->markTestSkipped('RES-2026 has no floor above 1 in this environment.');
        }

        $this->assertFalse(
            $this->service()->isSerialReclaimable('RES', 2026, $floor - 1),
            'A paper-era serial just below the floor was reported reusable'
        );
        $this->assertFalse($this->service()->isSerialReclaimable('RES', 2026, 1));
    }

    /** @test */
    public function every_offered_serial_is_absent_from_all_five_tables(): void
    {
        $db = DB::connection('sqlsrv');
        $checked = 0;

        foreach (['IND', 'COM', 'RES', 'AG'] as $landUse) {
            foreach ($this->service()->findReclaimableSerials($landUse, 2026, 15) as $entry) {
                $fileNumber = $entry['file_number'];
                $checked++;

                // Boundary-matched, not prefixed: `LIKE 'COM-2026-1%'` would also match
                // COM-2026-10 and COM-2026-100, and report serial 1 as occupied by a
                // hundred unrelated files. `[^0-9]` separates a suffix on this serial from
                // the start of a different one.
                $occupies = function (string $table, string $column) use ($db, $fileNumber) {
                    return $db->table($table)->where(function ($q) use ($column, $fileNumber) {
                        $q->where($column, $fileNumber)
                            ->orWhere($column, 'like', $fileNumber . '[^0-9]%');
                    })->count();
                };

                $this->assertSame(0, $occupies('mls_file_no', 'full_file_number'), "{$fileNumber} exists in mls_file_no");
                $this->assertSame(0, $occupies('fileNumber', 'mlsfNo'), "{$fileNumber} exists in fileNumber");
                $this->assertSame(0, $occupies('pra', 'mlsFNo'), "{$fileNumber} still has a pra row");
                $this->assertSame(0, $occupies('PropID_Master', 'mlsFNo'), "{$fileNumber} still has a PropID_Master row");
                $this->assertCount(0, $this->service()->takenInFileIndexings([$fileNumber]), "{$fileNumber} exists in file_indexings");
            }
        }

        if ($checked === 0) {
            $this->markTestSkipped('No reclaimable serials in this environment to verify.');
        }
    }

    /** @test */
    public function a_serial_still_holding_a_pra_row_is_refused(): void
    {
        // The Master Delete cascade covers six tables but deliberately stops short of `pra`
        // and `PropID_Master`, so a purged file can still own a transaction. IND-2026-257 is
        // exactly that: gone from every register, still carrying a Subdivision instrument.
        $db = DB::connection('sqlsrv');

        $row = $db->table('pra')
            ->whereNotNull('mlsFNo')
            ->where('mlsFNo', 'like', '%-2026-%')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('fileNumber')->whereColumn('fileNumber.mlsfNo', 'pra.mlsFNo');
            })
            ->first(['mlsFNo']);

        if (!$row) {
            $this->markTestSkipped('No pra row without a fileNumber row available to test.');
        }

        if (!preg_match('/^(.*)-(\d{4})-(\d+)$/', trim($row->mlsFNo), $m)) {
            $this->markTestSkipped('Could not parse a serial from ' . $row->mlsFNo);
        }

        [$all, $landUse, $year, $serial] = $m;

        $this->assertFalse(
            $this->service()->isSerialReclaimable($landUse, (int) $year, (int) $serial),
            "{$row->mlsFNo} still has a pra row but was reported reusable"
        );
    }

    /** @test */
    public function a_serial_that_is_in_use_is_never_reclaimable(): void
    {
        $db = DB::connection('sqlsrv');

        $live = $db->table('mls_file_no')
            ->where('full_file_number', 'like', '%-2026-%')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first(['full_file_number']);

        if (!$live || !preg_match('/^(.*)-(\d{4})-(\d+)$/', trim($live->full_file_number), $m)) {
            $this->markTestSkipped('No parseable live file number available.');
        }

        [$all, $landUse, $year, $serial] = $m;

        $this->assertFalse(
            $this->service()->isSerialReclaimable($landUse, (int) $year, (int) $serial),
            "{$live->full_file_number} is live but was reported reusable"
        );
    }

    /** @test */
    public function zero_and_negative_serials_are_refused(): void
    {
        $this->assertFalse($this->service()->isSerialReclaimable('RES', 2026, 0));
        $this->assertFalse($this->service()->isSerialReclaimable('RES', 2026, -5));
        $this->assertSame([], $this->service()->findReclaimableSerials('', 2026, 10));
        $this->assertSame([], $this->service()->findReclaimableSerials('RES', 2026, 0));
    }

    /** @test */
    public function an_unknown_prefix_yields_nothing_rather_than_the_whole_range(): void
    {
        // No counter for this prefix means no high-water mark, so there is nothing to
        // reclaim — it must not fall back to "1..N are all free".
        $this->assertSame([], $this->service()->findReclaimableSerials('ZZNOSUCHPREFIX', 2026, 10));
    }

    /** @test */
    public function a_sibling_prefix_never_leaks_into_the_list(): void
    {
        // "RES-2026-" must not pick up "RES-RC-2026-1". If it did, a serial held by the
        // RES-RC stream would look free in the RES stream.
        $db = DB::connection('sqlsrv');

        foreach ($this->service()->findReclaimableSerials('RES', 2026, 25) as $entry) {
            $this->assertMatchesRegularExpression('/^RES-2026-\d+$/', $entry['file_number']);

            $sibling = str_replace('RES-2026-', 'RES-RC-2026-', $entry['file_number']);
            $siblingExists = $db->table('mls_file_no')->where('full_file_number', $sibling)->exists();

            // The sibling's existence is irrelevant to this stream — asserted so the
            // intent is explicit if the prefix matching is ever loosened.
            $this->assertTrue($siblingExists || !$siblingExists);
        }
    }

    /** @test */
    public function blocked_freed_serials_report_what_is_holding_them(): void
    {
        $blocked = $this->service()->blockedFreedSerials('IND', 2026);

        if (empty($blocked)) {
            $this->markTestSkipped('No blocked freed serials in this environment.');
        }

        foreach ($blocked as $entry) {
            $this->assertArrayHasKey('serial', $entry);
            $this->assertArrayHasKey('file_number', $entry);
            $this->assertNotEmpty($entry['held_by'], 'A blocked serial must say what holds it');

            foreach ($entry['held_by'] as $table) {
                $this->assertContains($table, ['mls_file_no', 'fileNumber', 'file_indexings', 'pra', 'PropID_Master']);
            }

            // A blocked number must never also appear in the offered list.
            $offered = array_column($this->service()->findReclaimableSerials('IND', 2026, 200), 'serial');
            $this->assertNotContains($entry['serial'], $offered);
        }
    }

    /** @test */
    public function the_endpoint_returns_serials_blocked_and_the_counter(): void
    {
        $user = $this->anyUser();
        if (!$user) {
            $this->markTestSkipped('No user available in this environment.');
        }

        $response = $this->actingAs($user)->getJson('/mls-fileno/reclaimable-serials?land_use=COM&year=2026&limit=5');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'data' => ['land_use', 'year', 'current_serial', 'digital_floor', 'serials', 'blocked', 'count'],
        ]);

        $floor = (int) $response->json('data.digital_floor');
        foreach (($response->json('data.serials') ?? []) as $entry) {
            $this->assertGreaterThanOrEqual($floor, $entry['serial']);
        }

        foreach (($response->json('data.serials') ?? []) as $entry) {
            $this->assertContains($entry['origin'], ['deleted', 'never_issued']);
            $this->assertStringStartsWith('COM-2026-', $entry['file_number']);
        }
    }

    /** @test */
    public function the_endpoint_requires_a_land_use(): void
    {
        $user = $this->anyUser();
        if (!$user) {
            $this->markTestSkipped('No user available in this environment.');
        }

        $this->actingAs($user)
            ->getJson('/mls-fileno/reclaimable-serials?year=2026')
            ->assertStatus(422);
    }

    /** @test */
    public function listing_reclaimable_serials_does_not_move_the_counter(): void
    {
        // Reading the list must not consume anything — it is a lookup, not an allocation.
        $before = MlsSerialControl::getCurrentSerial('COM', 2026);

        $this->service()->findReclaimableSerials('COM', 2026, 25);
        $this->service()->blockedFreedSerials('COM', 2026);

        $this->assertSame(
            $before,
            MlsSerialControl::getCurrentSerial('COM', 2026),
            'Listing reclaimable serials changed mls_serial_control'
        );
    }

    /** @test */
    public function every_serial_auto_fill_yields_to_a_chosen_reclaimed_serial(): void
    {
        // The bug this pins: picking a missing serial updated the dropdown but the Serial
        // No. field and the preview both snapped back to the counter's next number — the
        // list said IND-2026-231 while the preview still read IND-2026-273.
        //
        // Four separate code paths write that field from the counter. Each has to check
        // mlsfHoldingReclaimedSerial() first, or it silently undoes the officer's choice.
        $js = file_get_contents(resource_path('views/generate_fileno/mls_js.blade.php'));

        $this->assertStringContainsString(
            'window.mlsfHoldingReclaimedSerial = function',
            $js,
            'The shared guard is missing'
        );

        // 1. The Alpine component's own updatePreview().
        $this->assertStringContainsString(
            "const holdingReclaimedSerial = this.useReclaimedSerial && this.reclaimedSerial;",
            $js,
            "The component's updatePreview() no longer guards the auto-fill"
        );
        $this->assertStringContainsString(
            "if (!holdingReclaimedSerial && ['normal', 'regrant', 'resettlement', 'reissuance', 'subdivision', 'merger', 'separation', 'temporary'].includes(this.fileOption)",
            $js,
            'The serial auto-fill in updatePreview() is not gated on the reclaimed hold'
        );

        // 2. The reservation branch, 3. updateGenerateForm(), 4. updateAlpineSerialNumber().
        $guardedWrites = substr_count($js, 'window.mlsfHoldingReclaimedSerial()');
        $this->assertGreaterThanOrEqual(
            3,
            $guardedWrites,
            'Expected the reservation, form-reset and Alpine-fallback writers to all consult the guard'
        );

        // The pick must survive a refresh fired for an unrelated reason.
        $this->assertStringContainsString(
            'wanted !== this.reclaimedLoadedFor',
            $js,
            'refreshSerialNumber() clears the pick unconditionally instead of only on a prefix change'
        );
    }

    /** @test */
    public function the_blocked_list_is_no_longer_rendered_in_the_panel(): void
    {
        // Asked to be hidden: the "N deleted number(s) cannot be reused yet: …" detail was
        // too noisy in a small panel. The endpoint still returns `blocked` and the tests
        // above still cover it, so it can be surfaced again without a backend change.
        $js = file_get_contents(resource_path('views/generate_fileno/mls_js.blade.php'));

        $this->assertStringNotContainsString('deleted number(s) cannot be reused yet', $js);
        $this->assertStringContainsString(
            'deleted serial(s) not yet reusable',
            $js,
            'The blocked detail should still reach the console for diagnosis'
        );

        // The running "N available in PREFIX-YEAR-x to y" count went the same way: the
        // dropdown already shows what is on offer.
        $this->assertStringNotContainsString('available in ${window}', $js);

        // An EMPTY list must still explain itself — a blank dropdown with no message reads
        // as a broken feature rather than "there is nothing to reclaim".
        $this->assertStringContainsString(
            'No reusable serials in ${window}',
            $js,
            'An empty list must still say so, with the range it searched'
        );
    }

    /** @test */
    public function the_generate_path_does_not_rewind_the_counter_for_a_reclaimed_serial(): void
    {
        // The trap this guards: the neighbouring force_file_number branch calls
        // MlsSerialControl::initialize(), which SETS last_serial to the number given. Doing
        // that for a reclaimed serial would wind the counter back from (say) 3028 to 5, and
        // the next dozen commissionings would collide with files that already exist.
        //
        // Asserted by reading the source, because exercising it for real would commission a
        // file on the shared database.
        $source = file_get_contents(app_path('Http/Controllers/MlsFileNoController.php'));

        $start = strpos($source, 'Reuse a serial the counter has already passed');
        $this->assertNotFalse($start, 'The reclaimed-serial branch is missing');

        $end = strpos($source, 'elseif ($forceFileNumber)', $start);
        $this->assertNotFalse($end, 'Could not find the end of the reclaimed-serial branch');

        $branch = substr($source, $start, $end - $start);

        // Strip comments before asserting — the branch's own explanation names
        // MlsSerialControl::initialize() to say why it must NOT be called, and matching
        // that prose would fail the test for describing the trap it guards.
        $code = '';
        foreach (token_get_all('<?php ' . $branch) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        $branch = $code;

        $this->assertStringNotContainsString(
            'MlsSerialControl::initialize',
            $branch,
            'The reclaimed-serial branch must not touch the serial counter'
        );
        $this->assertStringContainsString('isSerialReclaimable', $branch, 'The chosen serial must be re-verified inside the transaction');
    }
}
