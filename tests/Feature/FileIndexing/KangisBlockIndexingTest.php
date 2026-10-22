<?php

namespace Tests\Feature\FileIndexing;

use App\Http\Controllers\FileIndexingController;
use App\Models\User;
use App\Services\FileIndexing\KangisBlockIndexing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * KANGIS Block Indexing (user, 2026-10-17): on a KANGIS Registry record the land
 * (MLS) file is the Related File No and may be related to one KANGIS file only,
 * unless admin1 ticks KANGIS Block Indexing, which marks the land file
 * "multiple KANGIS files" (file_indexings.allow_multiple_kangis) and lets its
 * kangis_link_count pass 1.
 *
 * Reads real KANGIS rows through the read-only sqlsrv connection; writes nothing.
 */
class KangisBlockIndexingTest extends TestCase
{
    private function kangisRow(): object
    {
        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->where('general_registry', KangisBlockIndexing::REGISTRY)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(fn ($q) => $q->whereNull('is_decommissioned')->orWhere('is_decommissioned', 0))
            ->where('related_fileno', 'like', '[[]"%"]')
            ->orderByDesc('id')
            ->first(['id', 'file_number', 'related_fileno', 'general_registry']);

        if (!$row) {
            $this->markTestSkipped('No KANGIS Registry record with a land file to test against.');
        }

        return $row;
    }

    private function user(string $username): User
    {
        $user = new User();
        $user->username = $username;

        return $user;
    }

    private function check(array $input, ?User $user, ?object $existing = null)
    {
        $request = Request::create('/fileindexing/store', 'POST', $input);
        $request->setUserResolver(fn () => $user);

        $method = new \ReflectionMethod(FileIndexingController::class, 'kangisBlockCheck');
        $method->setAccessible(true);

        return $method->invoke(app(FileIndexingController::class), $request, $existing);
    }

    public function test_parses_land_files_and_keys(): void
    {
        $this->assertSame(['RES-2015-1254'], KangisBlockIndexing::landFilesFrom('["RES-2015-1254"]'));
        $this->assertSame(['RES-2015-1254'], KangisBlockIndexing::landFilesFrom('RES-2015-1254'));
        $this->assertSame(['A 1', 'B'], KangisBlockIndexing::landFilesFrom(['A 1', 'a1', '', 'B']));
        $this->assertSame([], KangisBlockIndexing::landFilesFrom(null));
        $this->assertSame('CON-RES-1', KangisBlockIndexing::key(' con-res -1 '));
    }

    public function test_only_configured_usernames_may_use_it(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);

        $this->assertTrue(KangisBlockIndexing::canUse($this->user('admin1')));
        $this->assertTrue(KangisBlockIndexing::canUse($this->user('ADMIN1')));
        $this->assertFalse(KangisBlockIndexing::canUse($this->user('admin')));
        $this->assertFalse(KangisBlockIndexing::canUse(null));

        // '*' = every signed-in user (client decision 2026-10-20).
        config(['fileindexing.kangis_block_usernames' => ['*']]);
        $this->assertTrue(KangisBlockIndexing::canUse($this->user('someone')));
        $this->assertFalse(KangisBlockIndexing::canUse(null));
    }

    public function test_finds_the_kangis_file_holding_a_land_file(): void
    {
        $row = $this->kangisRow();
        $land = KangisBlockIndexing::landFilesFrom($row->related_fileno)[0];
        $service = app(KangisBlockIndexing::class);

        $this->assertContains($row->file_number, $service->kangisFilesFor($land));
        $this->assertNotContains($row->file_number, $service->kangisFilesFor($land, (int) $row->id));

        $status = $service->status($land);
        $this->assertSame($land, $status['land_file']);
        $this->assertContains($row->file_number, $status['kangis_files']);
        $this->assertIsBool($status['allow_multiple']);
    }

    public function test_save_refuses_a_land_file_already_on_a_kangis_file_and_names_it(): void
    {
        $row = $this->kangisRow();
        $land = KangisBlockIndexing::landFilesFrom($row->related_fileno)[0];
        if (app(KangisBlockIndexing::class)->allowsMultiple($land)) {
            $this->markTestSkipped("{$land} is already marked multiple.");
        }

        $result = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => [$land],
        ], $this->user('someone'));

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(422, $result->getStatusCode());
        $this->assertSame('kangis_land_file_taken', $result->getData(true)['error_type']);
        $this->assertStringContainsString($row->file_number, $result->getData(true)['message']);
    }

    public function test_admin1_box_allows_it_and_marks_the_land_file(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);
        $row = $this->kangisRow();
        $land = KangisBlockIndexing::landFilesFrom($row->related_fileno)[0];

        $result = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => [$land],
            'kangis_block_index' => 1,
        ], $this->user('admin1'));

        $this->assertSame(['mark' => [$land], 'recount' => [$land], 'plots' => [], 'names' => [], 'links' => [$land], 'unlinks' => []], $result);
    }

    public function test_box_is_refused_for_other_users(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);

        $result = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => ['RES-1'],
            'kangis_block_index' => 1,
        ], $this->user('someone'));

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(403, $result->getStatusCode());
    }

    public function test_other_registries_are_not_checked(): void
    {
        $row = $this->kangisRow();

        $this->assertSame(['mark' => [], 'recount' => []], $this->check([
            'general_registry' => 'Lands Registry',
            'related_fileno' => KangisBlockIndexing::landFilesFrom($row->related_fileno),
        ], $this->user('someone')));
    }

    public function test_editing_without_adding_a_land_file_is_not_refused_and_recounts_old_and_new(): void
    {
        $row = $this->kangisRow();
        $land = KangisBlockIndexing::landFilesFrom($row->related_fileno);

        $this->assertSame(['mark' => [], 'recount' => array_merge($land, $land), 'plots' => [], 'names' => [], 'links' => array_values(array_filter($land, fn ($l) => app(KangisBlockIndexing::class)->allowsMultiple($l))), 'unlinks' => []], $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => $land,
        ], $this->user('someone'), $row));

        // Moving the record off KANGIS Registry still recounts the land file it held.
        $this->assertSame(['mark' => [], 'recount' => $land], $this->check([
            'general_registry' => 'Lands Registry',
        ], $this->user('someone'), $row));
    }

    public function test_plot_number_list_reads_and_writes_with_and(): void
    {
        $this->assertSame(['1', '2', '4'], KangisBlockIndexing::plotList('1, 2 and 4'));
        $this->assertSame(['1', '2', '4'], KangisBlockIndexing::plotList('1,2,and 4'));
        $this->assertSame(['1', '2', '3', '4'], KangisBlockIndexing::plotList('1, 2, 3 & 4'));
        $this->assertSame(['GP 967/6'], KangisBlockIndexing::plotList('GP 967/6'));
        $this->assertSame([], KangisBlockIndexing::plotList(null));

        $this->assertSame('', KangisBlockIndexing::formatPlots([]));
        $this->assertSame('GP 432', KangisBlockIndexing::formatPlots(['GP 432']));
        $this->assertSame('1 and 2', KangisBlockIndexing::formatPlots(['1', '2']));
        $this->assertSame('1, 2, 3 and 4', KangisBlockIndexing::formatPlots(['1', '2', '3', '4']));
    }

    public function test_block_save_plans_the_plot_for_the_land_file(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);
        $row = $this->kangisRow();
        $land = KangisBlockIndexing::landFilesFrom($row->related_fileno)[0];

        // New KANGIS file with the box ticked: its plot is added.
        $new = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => [$land],
            'plot_number' => [' GP 433 '],
            'kangis_block_index' => 1,
        ], $this->user('admin1'));
        $this->assertSame([[$land, 'GP 433', null, null]], $new['plots']);

        // The Plot No. beside the Related File Number row wins over Property Details.
        $fromRow = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => [$land],
            'plot_number' => 'GP 433',
            'related_plots' => [['file_number' => ' ' . strtolower($land) . ' ', 'plot_number' => 'GP 500']],
            'kangis_block_index' => 1,
        ], $this->user('admin1'));
        $this->assertSame([[$land, 'GP 500', null, null]], $fromRow['plots']);

        // Edit that changed the plot: the old one is swapped out.
        $edit = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => [$land],
            'plot_number' => 'GP 435',
            'kangis_block_index' => 1,
        ], $this->user('admin1'), (object) ['id' => $row->id, 'general_registry' => 'KANGIS Registry', 'related_fileno' => json_encode([$land]), 'plot_number' => 'GP 433']);
        $this->assertSame([[$land, 'GP 435', 'GP 433', (int) $row->id]], $edit['plots']);

        // Not a block land file and box not ticked: no plot list change.
        if (!app(KangisBlockIndexing::class)->allowsMultiple($land)) {
            $plain = $this->check([
                'general_registry' => 'KANGIS Registry',
                'related_fileno' => [$land],
                'plot_number' => 'GP 433',
            ], $this->user('admin1'), $row);
            $this->assertSame([], $plain['plots']);
        }
    }

    public function test_row_plots_form_the_kangis_plot_number_and_go_to_each_land_file(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);
        $request = Request::create('/fileindexing/store', 'POST', [
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => ['ZZ-TEST-LAND-1', 'ZZ-TEST-LAND-2', 'ZZ-TEST-LAND-3'],
            'plot_number' => ['old value'],
            'related_plots' => [
                ['file_number' => 'ZZ-TEST-LAND-1', 'plot_number' => '1'],
                ['file_number' => 'ZZ-TEST-LAND-2', 'plot_number' => '2'],
                ['file_number' => 'ZZ-TEST-LAND-3', 'plot_number' => '3'],
            ],
            'kangis_block_index' => 1,
        ]);
        $request->setUserResolver(fn () => $this->user('admin1'));
        $method = new \ReflectionMethod(FileIndexingController::class, 'kangisBlockCheck');
        $method->setAccessible(true);

        $result = $method->invoke(app(FileIndexingController::class), $request, null);

        $this->assertSame(['1, 2 and 3'], $request->input('plot_number'));
        $this->assertSame([
            ['ZZ-TEST-LAND-1', '1', null, null],
            ['ZZ-TEST-LAND-2', '2', null, null],
            ['ZZ-TEST-LAND-3', '3', null, null],
        ], $result['plots']);
    }

    public function test_row_names_go_to_their_block_land_files_only(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);
        $input = [
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => ['ZZ-TEST-LAND-1', 'ZZ-TEST-LAND-2'],
            'related_plots' => [
                ['file_number' => 'ZZ-TEST-LAND-1', 'plot_number' => '941/2', 'name' => ' KATO IDRIS '],
                ['file_number' => 'ZZ-TEST-LAND-2', 'plot_number' => '', 'name' => 'SHEHU GARMENT AND TEXTILES'],
            ],
        ];

        // Box ticked: both land files are block land files and get the names.
        $ticked = $this->check($input + ['kangis_block_index' => 1], $this->user('admin1'));
        $this->assertSame([['ZZ-TEST-LAND-1', 'KATO IDRIS'], ['ZZ-TEST-LAND-2', 'SHEHU GARMENT AND TEXTILES']], $ticked['names']);
        $this->assertSame([['ZZ-TEST-LAND-1', '941/2', null, null]], $ticked['plots']);

        // Not ticked and not marked multiple before: no names (the conflict check passes,
        // these test land files are on no KANGIS record).
        $plain = $this->check($input, $this->user('admin1'));
        $this->assertSame([], $plain['names']);
    }

    public function test_edit_swaps_each_land_file_old_plot_by_position(): void
    {
        config(['fileindexing.kangis_block_usernames' => ['admin1']]);
        $existing = (object) [
            'id' => 1, 'general_registry' => 'KANGIS Registry',
            'related_fileno' => '["ZZ-TEST-LAND-1","ZZ-TEST-LAND-2"]', 'plot_number' => '1 and 2',
        ];

        $result = $this->check([
            'general_registry' => 'KANGIS Registry',
            'related_fileno' => ['ZZ-TEST-LAND-1', 'ZZ-TEST-LAND-2'],
            'related_plots' => [
                ['file_number' => 'ZZ-TEST-LAND-1', 'plot_number' => '1'],
                ['file_number' => 'ZZ-TEST-LAND-2', 'plot_number' => '5'],
            ],
            'kangis_block_index' => 1,
        ], $this->user('admin1'), $existing);

        $this->assertSame([
            ['ZZ-TEST-LAND-1', '1', '1', 1],
            ['ZZ-TEST-LAND-2', '5', '2', 1],
        ], $result['plots']);
    }
}
