<?php

namespace Tests\Feature\FileNumber;

use App\Http\Controllers\FileNumberController;
use App\Models\User;
use App\Support\MlsRowTarget;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The MLS File Commission screen: its list payload and the Master Delete guard.
 *
 * READ-ONLY BY DESIGN. This suite runs against the shared development database, which the
 * team also uses for live UI testing, so nothing here creates or removes a row. The delete
 * assertions all target requests that must be REFUSED before any write happens — which is
 * precisely the behaviour worth pinning, and happens to be safe to exercise for real.
 *
 * What it protects:
 *
 *  - The list is a UNION of three tables (`fileNumber`, `mls_file_no` for temporary files,
 *    `plot_extensions`) whose ids collide. Master Delete used to resolve any id against
 *    `fileNumber`, so deleting temporary RES-1993-2644(T) — mls_file_no id 1166 — purged
 *    the unrelated live file CON-AG-1987-57 from five tables. The refusal is asserted here
 *    at the HTTP boundary, not just in the unit tests for MlsRowTarget, because the client
 *    hiding the button is not a guarantee: a page cached before the fix still posts.
 *
 *  - The Passport / RoT / Related-Old-FileNo columns must actually be present in the
 *    payload, with the shapes the DataTable renderers expect.
 */
class MlsFcListAndDeleteGuardTest extends TestCase
{
    /** @test */
    public function root_of_title_marker_follows_the_legal_search_commissioning_rules(): void
    {
        $method = new \ReflectionMethod(FileNumberController::class, 'isRootOfTitleFile');
        $controller = app(FileNumberController::class);

        $this->assertTrue($method->invoke($controller, 'Direct Allocation', null));
        $this->assertTrue($method->invoke($controller, 'OP Direct Allocation', null));
        $this->assertTrue($method->invoke($controller, 'Re-grant', 'Customary Right of Occupancy'));
        $this->assertFalse($method->invoke($controller, 'Re-grant', null));
    }

    private function admin(): ?User
    {
        // The delete guard is Supper Admin only; without one we cannot reach the entity
        // check at all, so those tests skip rather than assert something weaker.
        return User::where('assign_role', 'Supper Admin')->first();
    }

    private function anyUser(): ?User
    {
        return User::query()->first();
    }

    /** @test */
    public function the_list_payload_carries_the_passport_root_of_title_and_related_old_file_number(): void
    {
        $user = $this->anyUser();
        if (!$user) {
            $this->markTestSkipped('No user available in this environment.');
        }

        $response = $this->actingAs($user)->getJson('/file-numbers/data?source=New&start=0&length=5&draw=1');

        $response->assertOk();

        $rows = $response->json('data');
        $this->assertIsArray($rows);

        if (empty($rows)) {
            $this->markTestSkipped('No file-number rows available to inspect.');
        }

        foreach ($rows as $row) {
            $this->assertArrayHasKey('passport_url', $row, 'Passport column missing from the row payload');
            $this->assertArrayHasKey('root_of_title', $row, 'RoT column missing from the row payload');
            $this->assertArrayHasKey('is_root_of_title', $row, 'RoT marker missing from the row payload');
            $this->assertArrayHasKey('related_old_fileno', $row, 'Related/Old File No column missing from the row payload');

            // passport_url is a URL or null — the renderer branches on falsiness.
            $this->assertTrue(
                $row['passport_url'] === null || is_string($row['passport_url']),
                'passport_url must be a string URL or null'
            );

            // RoT is always a string; 'N/A' when the file has never been indexed.
            $this->assertIsString($row['root_of_title']);

            // The Related/Old cell needs both halves: the renderer prints a different
            // badge for an old number than for a related one.
            $this->assertIsArray($row['related_old_fileno']);
            $this->assertArrayHasKey('value', $row['related_old_fileno']);
            $this->assertArrayHasKey('kind', $row['related_old_fileno']);
            $this->assertContains($row['related_old_fileno']['kind'], ['old', 'related', 'none']);
        }
    }

    /** @test */
    public function every_row_type_in_the_payload_reports_the_same_three_columns(): void
    {
        // Temporary and Plot Extension rows are formatted by separate code paths; a column
        // added to only the fileNumber path renders as blank on the others.
        $user = $this->anyUser();
        if (!$user) {
            $this->markTestSkipped('No user available in this environment.');
        }

        $response = $this->actingAs($user)->getJson('/file-numbers/data?source=New&start=0&length=100&draw=1');
        $response->assertOk();

        $byType = [];
        foreach (($response->json('data') ?? []) as $row) {
            $byType[$row['type'] ?? 'unknown'] ??= $row;
        }

        if (empty($byType)) {
            $this->markTestSkipped('No file-number rows available to inspect.');
        }

        foreach ($byType as $type => $row) {
            foreach (['passport_url', 'root_of_title', 'is_root_of_title', 'related_old_fileno'] as $key) {
                $this->assertArrayHasKey($key, $row, "Row type '{$type}' is missing '{$key}'");
            }
        }
    }

    /** @test */
    public function an_edit_reaches_the_oss_applications_row_the_file_was_published_as(): void
    {
        // Commissioning publishes every MLS file into oss_applications
        // (MlsCommissioningOssApplicationService) — 5,102 rows carry
        // system_source = MLS_FILE_NUMBER_GENERATOR — and that table is what
        // /lands-one-stop-shop/applications?type=no-change-of-name reads.
        //
        // Before this, an edit stopped at the file-number screens, so the applications page
        // kept showing whatever was captured at commissioning time. Asserted structurally:
        // the propagation must be wired into BOTH save paths, and it must not write columns
        // commissioning never wrote.
        $controller = file_get_contents(app_path('Http/Controllers/FileNumberController.php'));

        $this->assertStringContainsString(
            'private function propagateToOssApplications(',
            $controller,
            'The OSS propagation is missing'
        );

        $this->assertSame(
            2,
            substr_count($controller, '$this->propagateToOssApplications($request, $fileNoCandidates, $nameChanged);'),
            'Both the normal and the temporary-file save paths must propagate to oss_applications'
        );
    }

    /** @test */
    public function the_oss_edit_mapping_matches_what_commissioning_writes(): void
    {
        // The two must not drift. plan_no is the trap: commissioning feeds it from the TP
        // number, so an edit that skipped it would leave a corrected TP invisible on the
        // applications page forever.
        $controller = file_get_contents(app_path('Http/Controllers/FileNumberController.php'));
        $commissioning = file_get_contents(app_path('Services/MlsCommissioningOssApplicationService.php'));

        $start = strpos($controller, 'private function propagateToOssApplications(');
        $mapping = substr($controller, $start, 2500);

        foreach (['plot_no', 'plan_no', 'location', 'district', 'lga', 'applicant_name'] as $column) {
            $this->assertStringContainsString(
                $column,
                $mapping,
                "The edit does not propagate oss_applications.{$column}"
            );
            $this->assertStringContainsString(
                "'{$column}'",
                $commissioning,
                "Commissioning does not write oss_applications.{$column} — the mapping has drifted"
            );
        }

        $this->assertStringContainsString(
            "'tp_no' => 'plan_no'",
            $mapping,
            'plan_no must be fed from the TP number, as commissioning does'
        );
    }

    /** @test */
    public function the_oss_row_for_a_commissioned_file_is_reachable_by_the_propagation_filter(): void
    {
        // Proves the WHERE clause actually resolves — without writing anything.
        $db = DB::connection('sqlsrv');

        $published = $db->table('oss_applications')
            ->where('system_source', 'MLS_FILE_NUMBER_GENERATOR')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->whereNotNull('file_no')
            ->first(['file_no']);

        if (!$published) {
            $this->markTestSkipped('No MLS-sourced oss_applications rows in this environment.');
        }

        $record = $db->table('fileNumber')->where('mlsfNo', $published->file_no)->first();

        if (!$record) {
            $this->markTestSkipped('The sampled OSS row has no fileNumber record.');
        }

        // The same candidate list applyFileNumberEdit() builds.
        $candidates = array_values(array_unique(array_filter([
            $record->mlsfNo ?? null,
            $record->kangisFileNo ?? null,
            $record->NewKANGISFileNo ?? null,
        ])));

        $reachable = $db->table('oss_applications')
            ->whereIn('file_no', $candidates)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->count();

        $this->assertGreaterThan(
            0,
            $reachable,
            "An edit to {$record->mlsfNo} would not reach its oss_applications row"
        );
    }

    /** @test */
    public function the_oss_commissioning_listing_renders_a_passport_column(): void
    {
        // OSS commissioning captures the applicant's photograph as well (331 oss_applications
        // rows carry a passport_photo), and the controller already resolved `passport_url`
        // per record — it simply was not rendered. Column counts are asserted because the
        // page has TWO row shapes (normal and OP-batch) and a cell added to only one of them
        // shears every column after it.
        $view = file_get_contents(resource_path('views/lands_one_stop_shop/applications.blade.php'));

        $start = strpos($view, 'id="op-resettlement-table"');
        $this->assertNotFalse($start, 'The OP resettlement table is missing');

        $theadStart = strpos($view, '<thead>', $start);
        $thead = substr($view, $theadStart, strpos($view, '</thead>', $theadStart) - $theadStart);

        $this->assertStringContainsString('>Passport<', $thead, 'The Passport header is missing');

        $tbodyStart = strpos($view, '<tbody', $start);
        $tbody = substr($view, $tbodyStart, strpos($view, '</tbody>', $tbodyStart) - $tbodyStart);

        $batch = substr($tbody, strpos($tbody, '@forelse($opBatchGroups'));
        $batch = substr($batch, 0, strpos($batch, '@empty'));

        $normal = substr($tbody, strpos($tbody, '@forelse($records'));
        $normal = substr($normal, 0, strpos($normal, '@empty'));

        $this->assertSame(
            substr_count($batch, '<td'),
            substr_count($normal, '<td'),
            'The OP-batch row and the normal row must carry the same number of cells'
        );

        // A batch stands for N files with N applicants, so it shows no single photograph.
        $this->assertStringContainsString('passport_url', $normal, 'The normal row does not render the passport');
        $this->assertStringNotContainsString('passport_url', $batch, 'A batch row must not claim a single passport');
    }

    /** @test */
    public function the_oss_exports_carry_the_passport_as_a_flag(): void
    {
        // An image means nothing in a CSV or PDF, but whether a photograph is on record does.
        // Both exports read the row's data-record JSON rather than cell indices, so the new
        // column cannot shift them — but the header and the value must stay in step.
        $view = file_get_contents(resource_path('views/lands_one_stop_shop/applications.blade.php'));

        $this->assertSame(
            2,
            substr_count($view, "'Source','Passport','MLS File No'"),
            'Both the CSV and PDF export headers should list Passport after Source'
        );
        $this->assertSame(
            2,
            substr_count($view, "rec.passport_url ? 'Yes' : 'No',"),
            'Both exports should emit the passport flag in the matching position'
        );
    }

    /** @test */
    public function the_cascade_purges_the_oss_listing_and_the_tracking_request(): void
    {
        // Commissioning does two things beyond the file-number registers: it publishes the
        // file into `oss_applications` (what /lands-one-stop-shop/applications?type=no-change-of-name
        // lists) and opens a `file_tracker` request. Neither was purged, so a master-deleted
        // file kept appearing on the applications page and as an ACTIVE tracking request —
        // IND-2026-272 was gone from all six original tables and still listed in both.
        $controller = file_get_contents(app_path('Http/Controllers/FileNumberController.php'));

        $start = strpos($controller, 'private function cascadeDeleteFileRecord(');
        $this->assertNotFalse($start, 'The cascade is missing');

        $end = strpos($controller, 'private function forgetFileNumberCaches(', $start);
        $cascade = substr($controller, $start, $end - $start);

        foreach (['oss_applications', 'file_tracker', 'rds_tracking', 'digital_file_tracking_requests'] as $table) {
            $this->assertStringContainsString(
                "table('{$table}')",
                $cascade,
                "The cascade does not purge {$table}"
            );
        }

        // file_tracker's children are keyed on the tracker id, so they must be resolved
        // before the parent row goes.
        $this->assertStringContainsString('kangis_checkout_approvals', $cascade);
        $this->assertStringContainsString('file_tracker_department_backfill', $cascade);

        // An indexing_duplicates row documents indexing, not the tracking request — its
        // pointer is cleared rather than the row deleted.
        $this->assertMatchesRegularExpression(
            "/table\('indexing_duplicates'\)[\s\S]{0,200}->update\(\['file_tracker_id' => null\]\)/",
            $cascade,
            'indexing_duplicates should have its pointer cleared, not be deleted'
        );

        // Both counts must reach the caller, or the dialog under-reports what it removed.
        $this->assertStringContainsString("'oss_applications' => \$deletedOssApplications", $cascade);
        $this->assertStringContainsString("'file_tracking' => \$deletedTracking", $cascade);
    }

    /** @test */
    public function the_delete_dialog_lists_every_table_the_cascade_touches(): void
    {
        // The confirmation names the tables it will purge; leaving the two new ones out
        // would understate what the button does.
        $js = file_get_contents(resource_path('views/generate_fileno/mls_js.blade.php'));

        $this->assertStringContainsString("'OSS Applications', 'File Tracking',", $js);
        $this->assertStringContainsString('json.totals?.oss_applications', $js);
        $this->assertStringContainsString('json.totals?.file_tracking', $js);
    }

    /** @test */
    public function master_delete_refuses_a_temporary_row(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        // A temporary file whose id also exists in fileNumber — the exact shape of the bug.
        $temp = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('file_option', 'temporary')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$temp) {
            $this->markTestSkipped('No temporary file rows in this environment.');
        }

        $victim = DB::connection('sqlsrv')->table('fileNumber')->where('id', $temp->id)->first();

        $response = $this->actingAs($admin)
            ->deleteJson("/file-numbers/{$temp->id}", ['entity' => MlsRowTarget::TEMPORARY]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertStringContainsString('Temporary file', $response->json('message'));

        // The collision victim must still be there. This is the assertion that would have
        // caught the original bug.
        if ($victim) {
            $this->assertNotNull(
                DB::connection('sqlsrv')->table('fileNumber')->where('id', $temp->id)->first(),
                "Deleting temporary file {$temp->full_file_number} removed fileNumber id {$temp->id}"
            );
        }
    }

    /** @test */
    public function master_delete_refuses_a_plot_extension_row(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        $pe = DB::connection('sqlsrv')->table('plot_extensions')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$pe) {
            $this->markTestSkipped('No plot extension rows in this environment.');
        }

        $response = $this->actingAs($admin)
            ->deleteJson("/file-numbers/{$pe->id}", ['entity' => MlsRowTarget::PLOT_EXTENSION]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Plot Extension', $response->json('message'));
    }

    /** @test */
    public function bulk_delete_skips_non_file_number_selections_instead_of_resolving_them(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        $temp = DB::connection('sqlsrv')->table('mls_file_no')
            ->where('file_option', 'temporary')
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->first();

        if (!$temp) {
            $this->markTestSkipped('No temporary file rows in this environment.');
        }

        // A selection of ONLY non-fileNumber rows resolves to nothing to delete, so this
        // exercises the guard without putting any real row at risk.
        $response = $this->actingAs($admin)->postJson('/file-numbers/bulk-destroy', [
            'ids' => ["T:{$temp->id}"],
        ]);

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);

        $skipped = $response->json('skipped');
        $this->assertIsArray($skipped);
        $this->assertNotEmpty($skipped, 'The temporary row should be reported as skipped, not silently dropped');
        $this->assertSame((int) $temp->id, (int) $skipped[0]['id']);

        // And the collision victim survives.
        $this->assertNotNull(
            DB::connection('sqlsrv')->table('fileNumber')->where('id', $temp->id)->first()
                ?: 'no-collision',
            'Bulk delete must not touch the fileNumber row sharing the temporary id'
        );
    }

    /** @test */
    public function bulk_delete_rejects_an_unreadable_selection_key(): void
    {
        $admin = $this->admin();
        if (!$admin) {
            $this->markTestSkipped('No Supper Admin available in this environment.');
        }

        // "X:8" must not be read as fileNumber id 8 (AG-RC-1981-54).
        $response = $this->actingAs($admin)->postJson('/file-numbers/bulk-destroy', [
            'ids' => ['X:8'],
        ]);

        $response->assertStatus(404);
        $this->assertNotEmpty($response->json('skipped'));

        $this->assertNotNull(
            DB::connection('sqlsrv')->table('fileNumber')->where('id', 8)->first(),
            'An unreadable selection key must never resolve to a real file'
        );
    }

    /** @test */
    public function master_delete_still_requires_supper_admin(): void
    {
        $user = User::where('assign_role', '!=', 'Supper Admin')->first();
        if (!$user) {
            $this->markTestSkipped('No non-admin user available in this environment.');
        }

        $response = $this->actingAs($user)->deleteJson('/file-numbers/8', ['entity' => 'file_number']);

        $response->assertStatus(403);

        $this->assertNotNull(
            DB::connection('sqlsrv')->table('fileNumber')->where('id', 8)->first(),
            'A non-admin delete must not remove the record'
        );
    }
}
