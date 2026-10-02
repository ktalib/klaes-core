<?php

namespace App\Services;

use App\Models\MlsFileNo;
use App\Models\MlsSerialControl;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocation of MLS file-number serials (mls_serial_control) with gap skipping.
 *
 * Lifted out of MlsFileNoController so the ST commissioning flow can draw a
 * CON-* serial from the very same stream a land conversion uses -- an ST
 * conversion and a land conversion must never be handed the same number.
 */
class MlsSerialAllocationService
{
    /**
     * Take the next serial for a land-use prefix, skipping serials whose file
     * number is already in use anywhere.
     *
     * @return array{serial:int,file_number:string,skipped:array<int,array{serial:int,file_number:string}>}
     */
    public function allocateNextFreeSerial(string $landUse, int $year, string $suffix = '', int $maxTries = 200): array
    {
        $skipped = [];
        $tries = 0;
        $serial = null;
        $candidate = null;

        do {
            $serial = MlsSerialControl::getNextSerial($landUse, $year);
            $candidate = MlsFileNo::generateFileNumber($landUse, $year, $serial) . $suffix;

            $taken = MlsFileNo::where('full_file_number', $candidate)->exists()
                || DB::connection('sqlsrv')->table('fileNumber')->where('mlsfNo', $candidate)->exists()
                || count($this->takenInFileIndexings([$candidate])) > 0;

            if ($taken) {
                $skipped[] = ['serial' => $serial, 'file_number' => $candidate];
            }
            $tries++;
        } while ($taken && $tries < $maxTries);

        if ($taken) {
            throw new RuntimeException("Could not find a free file number for {$landUse}-{$year} after {$maxTries} attempts. Too many consecutive serials are already in use.");
        }

        return ['serial' => $serial, 'file_number' => $candidate, 'skipped' => $skipped];
    }

    /**
     * Serials below the counter that no longer belong to any file, and so can be issued again.
     *
     * WHY THIS IS NOT "list the holes in the sequence". `mls_serial_control` only ever moves
     * forward, so a Master Delete leaves its serial stranded: the number is gone from the
     * registers but the counter has already passed it. Those are the numbers worth
     * recovering. The trap is that a hole in one table is NOT evidence the number is free —
     * measured on live data, 112 of 569 apparent RES-2026 holes and 49 of 81 COM-2026 holes
     * are in use somewhere else, mostly `file_indexings` rows with no `fileNumber` row.
     * Handing one of those out creates a duplicate file number.
     *
     * So a candidate has to be absent EVERYWHERE before it is offered: the two registers,
     * the indexing table (via the CRLF-tolerant check below), plus `pra` and `PropID_Master`,
     * which the Master Delete cascade deliberately does not purge — a deleted file can still
     * own a transaction and a prop_id, and reissuing its number would bolt a new file onto
     * that history.
     *
     * Suffixed forms occupy the serial too: `RES-2026-5(T)` and `RES-2026-5 AND EXTENSION`
     * both mean 5 is taken.
     *
     * @return array<int, array{serial:int, file_number:string, origin:string}>
     *         origin: 'deleted' when a Master Delete freed it, otherwise 'never_issued'
     */
    public function findReclaimableSerials(string $landUse, int $year, int $limit = 100): array
    {
        $landUse = trim($landUse);
        if ($landUse === '' || $limit < 1) {
            return [];
        }

        $stem = $landUse . '-' . $year . '-';

        // One indexable range scan per source rather than a lookup per candidate. A prefix
        // can have hundreds of holes, and the per-candidate form cost seconds because the
        // CRLF-tolerant comparison in takenInFileIndexings() is non-sargable — it forces a
        // full scan of 133k indexing rows every time it is called.
        //
        // `LIKE 'RES-2026-%'` stays sargable AND still catches the dirty legacy rows: a
        // value stored as "RES-2026-213\r\n" matches the wildcard, and the trailing junk is
        // stripped before the serial is parsed below. It also cannot stray into a sibling
        // prefix — "RES-2026-" never matches "RES-RC-2026-1".
        // The counter alone sets the ceiling. It is tempting to use the highest serial found
        // in the registers instead, but those two disagree badly: IND-2026 has the counter at
        // 272 while `fileNumber` holds IND-2026-3635 from a separate import. Taking the
        // register maximum would declare 273…3634 "missing" and offer three thousand numbers
        // the counter has never reached. A reclaimable serial is one the counter has ALREADY
        // passed and nothing holds; everything above it gets issued normally in due course.
        $ceiling = (int) MlsSerialControl::getCurrentSerial($landUse, $year);
        $floor = $this->digitalFloor($landUse, $year);

        // No digital issue for this prefix/year means there is no stranded number to
        // recover — not that the whole range is free.
        if ($floor < 1 || $ceiling <= $floor) {
            return [];
        }

        $occupied = [];
        $pattern = '/^' . preg_quote($stem, '/') . '(\d+)/';

        $absorb = function ($numbers) use (&$occupied, $pattern) {
            foreach ($numbers as $number) {
                $clean = trim(str_replace(["\r", "\n"], '', (string) $number));

                // No terminating anchor: a (T) or "AND EXTENSION" variant still occupies
                // the serial, so RES-2026-5(T) means 5 is taken.
                if (preg_match($pattern, $clean, $m)) {
                    $occupied[(int) $m[1]] = true;
                }
            }
        };

        $db = DB::connection('sqlsrv');

        $absorb($db->table('mls_file_no')->where('full_file_number', 'like', $stem . '%')->pluck('full_file_number'));
        $absorb($db->table('fileNumber')->where('mlsfNo', 'like', $stem . '%')->pluck('mlsfNo'));
        $absorb($db->table('file_indexings')->where('file_number', 'like', $stem . '%')->pluck('file_number'));
        $absorb($db->table('pra')->where('mlsFNo', 'like', $stem . '%')->pluck('mlsFNo'));
        $absorb($db->table('PropID_Master')->where('mlsFNo', 'like', $stem . '%')->pluck('mlsFNo'));

        $freed = $this->serialsFreedByMasterDelete($landUse, $year);

        // Numbers a Master Delete released come first. They are what this feature is for,
        // and they are the safest of the two kinds — this system issued them, then purged
        // them across every table it owns.
        $reclaimable = [];
        $freedSerials = array_keys($freed);
        sort($freedSerials);

        foreach ($freedSerials as $serial) {
            if ($serial < $floor || $serial >= $ceiling || isset($occupied[$serial])) {
                continue;
            }

            $reclaimable[$serial] = [
                'serial' => $serial,
                'file_number' => MlsFileNo::generateFileNumber($landUse, $year, $serial),
                'origin' => 'deleted',
            ];

            if (count($reclaimable) >= $limit) {
                return array_values($reclaimable);
            }
        }

        // Then serials the counter skipped that were never issued at all. Free in every
        // table this system owns, but no record says they were ever used — a paper file
        // may still carry the number, which is why the clerk picks rather than the machine.
        for ($serial = $floor; $serial < $ceiling && count($reclaimable) < $limit; $serial++) {
            if (isset($occupied[$serial]) || isset($reclaimable[$serial])) {
                continue;
            }

            $reclaimable[$serial] = [
                'serial' => $serial,
                'file_number' => MlsFileNo::generateFileNumber($landUse, $year, $serial),
                'origin' => 'never_issued',
            ];
        }

        return array_values($reclaimable);
    }

    /**
     * The lowest serial this prefix/year was ever issued on the digital platform.
     *
     * Numbering did not start at 1. Each prefix ran on paper first and the system was
     * switched on part-way through, continuing from wherever the manual register had
     * reached: RES-2026 begins at 565 (first commissioned 2026-02-03), COM-2026 at 77,
     * CON-COM-2026 at 48. CON-RES starts at 33, CON-AG at 18. IND-2026 genuinely does
     * start at 1.
     *
     * Everything below that floor is a PAPER file. Those numbers are not free — they are
     * simply invisible to this system — and offering them would issue a file number that
     * already exists on a physical file in the registry. Before this floor existed the
     * dropdown listed RES-2026-211 … 231 as "never issued"; all of them are paper.
     *
     * Read from `mls_file_no`, which is the digital register: a serial only appears there
     * because this platform generated it. `fileNumber` is deliberately NOT consulted — it
     * also holds captured and imported legacy records, which is why the same scan across
     * both tables returns 1 for CON-COM instead of 48.
     *
     * serial_number = 0 is excluded: a handful of rows carry it as a placeholder (2 on RES,
     * 1 each on COM and IND) and it would drag the floor to zero.
     *
     * @return int the floor, or 0 when this prefix/year has no digital issue at all
     */
    public function digitalFloor(string $landUse, int $year): int
    {
        $landUse = trim($landUse);
        if ($landUse === '') {
            return 0;
        }

        $min = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('land_use', $landUse)
            ->where('year', $year)
            ->where('serial_number', '>', 0)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->min('serial_number');

        return (int) ($min ?? 0);
    }

    /**
     * Serials a Master Delete freed that are still NOT safe to reissue, and what is holding them.
     *
     * Without this the feature looks broken in exactly the case it was built for: delete five
     * files, open the dropdown, find nothing. The reason is that the Master Delete cascade
     * covers six tables but deliberately stops short of `pra` and `PropID_Master` — so a
     * purged file can still own a transaction and a prop_id. IND-2026-257 is a live example:
     * gone from every register, still carrying a Subdivision instrument on prop_id 147224.
     *
     * Reissuing it would attach a brand-new file to that history, so it stays out of the
     * dropdown — but the clerk is told it exists and why, rather than being left guessing.
     *
     * @return array<int, array{serial:int, file_number:string, held_by:array<int,string>}>
     */
    public function blockedFreedSerials(string $landUse, int $year, int $limit = 25): array
    {
        $landUse = trim($landUse);
        if ($landUse === '') {
            return [];
        }

        $freed = $this->serialsFreedByMasterDelete($landUse, $year);
        if (empty($freed)) {
            return [];
        }

        $db = DB::connection('sqlsrv');
        $serials = array_keys($freed);
        sort($serials);

        $blocked = [];
        foreach ($serials as $serial) {
            if (count($blocked) >= $limit) {
                break;
            }

            $fileNumber = MlsFileNo::generateFileNumber($landUse, $year, $serial);
            $heldBy = [];

            if ($this->occupies($db->table('mls_file_no'), 'full_file_number', $fileNumber)->exists()) {
                $heldBy[] = 'mls_file_no';
            }
            if ($this->occupies($db->table('fileNumber'), 'mlsfNo', $fileNumber)->exists()) {
                $heldBy[] = 'fileNumber';
            }
            if (count($this->takenInFileIndexings([$fileNumber])) > 0) {
                $heldBy[] = 'file_indexings';
            }
            if ($this->occupies($db->table('pra'), 'mlsFNo', $fileNumber)->exists()) {
                $heldBy[] = 'pra';
            }
            if ($this->occupies($db->table('PropID_Master'), 'mlsFNo', $fileNumber)->exists()) {
                $heldBy[] = 'PropID_Master';
            }

            if (!empty($heldBy)) {
                $blocked[] = [
                    'serial' => $serial,
                    'file_number' => $fileNumber,
                    'held_by' => $heldBy,
                ];
            }
        }

        return $blocked;
    }

    /**
     * Is this exact serial still free? Re-checked inside the commissioning transaction,
     * because the dropdown a clerk is looking at was built seconds or minutes ago and
     * someone else may have taken the number in between.
     */
    public function isSerialReclaimable(string $landUse, int $year, int $serial): bool
    {
        if ($serial < 1) {
            return false;
        }

        // Below the digital floor is the paper era — those numbers exist on physical files
        // this system has never seen. Enforced here as well as in the list, because the
        // request carries the serial and need not have come from the dropdown.
        $floor = $this->digitalFloor($landUse, $year);
        if ($floor < 1 || $serial < $floor) {
            return false;
        }

        $db = DB::connection('sqlsrv');
        $fileNumber = MlsFileNo::generateFileNumber($landUse, $year, $serial);

        $inRegisters = $this->occupies($db->table('mls_file_no'), 'full_file_number', $fileNumber)->exists()
            || $this->occupies($db->table('fileNumber'), 'mlsfNo', $fileNumber)->exists();

        if ($inRegisters) {
            return false;
        }

        return count($this->takenInFileIndexings([$fileNumber])) === 0
            && !$this->occupies($db->table('pra'), 'mlsFNo', $fileNumber)->exists()
            && !$this->occupies($db->table('PropID_Master'), 'mlsFNo', $fileNumber)->exists();
    }

    /**
     * Constrain a query to rows whose file number IS this serial — the bare number, or a
     * suffixed form of it.
     *
     * A plain `LIKE 'COM-2026-1%'` is wrong here and dangerously plausible: it also matches
     * COM-2026-10, COM-2026-100 and COM-2026-123, so serial 1 is reported occupied by a
     * hundred unrelated files and can never be reclaimed. Equality alone is wrong in the
     * other direction, since COM-2026-1(T) and "COM-2026-1 AND EXTENSION" do occupy the
     * serial (and legacy indexing rows carry a trailing CRLF).
     *
     * `[^0-9]` is the boundary that separates the two: a digit after the number means a
     * DIFFERENT serial, anything else means a suffix on this one.
     */
    private function occupies($query, string $column, string $fileNumber)
    {
        return $query->where(function ($q) use ($column, $fileNumber) {
            $q->where($column, $fileNumber)
                ->orWhere($column, 'like', $fileNumber . '[^0-9]%');
        });
    }

    /**
     * Serials this prefix/year lost to a Master Delete, read back out of the audit trail.
     *
     * Used only to label a candidate, never to decide one is safe — the audit says a number
     * was released, not that nothing has referenced it since.
     *
     * @return array<int, true> keyed by serial
     */
    private function serialsFreedByMasterDelete(string $landUse, int $year): array
    {
        $stem = $landUse . '-' . $year . '-';

        try {
            $rows = DB::connection('sqlsrv')->table('audit_logs')
                ->where('resource_type', 'mls_file_record')
                ->where('action', 'DELETED')
                ->where('old_values', 'like', '%' . $stem . '%')
                ->pluck('old_values');
        } catch (\Throwable $e) {
            // The label is a nicety; never fail the lookup over it.
            return [];
        }

        $freed = [];
        foreach ($rows as $raw) {
            $decoded = json_decode((string) $raw, true);
            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $number = trim((string) ($decoded['mlsfNo'] ?? ''));
            if ($number !== '' && preg_match('/^' . preg_quote($stem, '/') . '(\d+)/', $number, $m)) {
                $freed[(int) $m[1]] = true;
            }
        }

        return $freed;
    }

    /**
     * Which of these candidate file numbers already exist in file_indexings?
     *
     * Legacy imported rows carry a trailing CRLF in file_number (e.g. "IND-2026-213\r\n"),
     * which an exact `where` never matches -- the serial then looks free, gets handed out,
     * and no skip is reported even though the number is in use. Comparing on the
     * whitespace-stripped value catches both the clean and the dirty rows.
     *
     * Returns a set keyed by the *candidate* string (not the stored value) so callers can
     * look up by the number they asked about.
     */
    public function takenInFileIndexings(array $candidates): array
    {
        $candidates = array_values(array_unique(array_filter($candidates, 'strlen')));
        if (empty($candidates)) {
            return [];
        }

        $normalized = "LTRIM(RTRIM(REPLACE(REPLACE(file_number, CHAR(13), ''), CHAR(10), '')))";
        $placeholders = implode(',', array_fill(0, count($candidates), '?'));

        $found = DB::connection('sqlsrv')->table('file_indexings')
            ->whereRaw("{$normalized} IN ({$placeholders})", $candidates)
            ->selectRaw("{$normalized} AS normalized_file_number")
            ->pluck('normalized_file_number')
            ->all();

        return array_flip($found);
    }
}
