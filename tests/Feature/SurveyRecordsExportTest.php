<?php

namespace Tests\Feature;

use App\Http\Controllers\Survey\Concerns\ExportsRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Export Records on the Survey registers. Reads only. */
class SurveyRecordsExportTest extends TestCase
{
    private function exporter(): object
    {
        return new class {
            use ExportsRecords;

            public function run(Request $r, callable $list, array $columns, callable $row, ?string $date = 'created_at')
            {
                return json_decode($this->exportRecords($r, $list, $columns, $row, $date)->getContent(), true);
            }
        };
    }

    public function test_every_export_route_sits_under_auth(): void
    {
        $routes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'survey-module.') && str_ends_with($name, '.export'));

        // 31 from Export Records, plus the older CSV exports (Coordinate Register, Resettlement Plots) and any others.
        $this->assertGreaterThanOrEqual(31, $routes->count());
        foreach ($routes as $name => $route) {
            $this->assertContains('auth', $route->gatherMiddleware(), "$name is open to guests");
            $this->assertSame(['GET', 'HEAD'], $route->methods(), "$name must only read");
        }
    }

    public function test_rows_are_numbered_in_order_with_sn_first(): void
    {
        $out = $this->exporter()->run(Request::create('/x'), fn () => collect([['n' => 'B'], ['n' => 'A']]), ['name' => 'Name'], fn ($x) => ['name' => $x['n']], null);

        $this->assertSame(['sn', 'name'], array_column($out['columns'], 'key'));
        $this->assertSame([['sn' => 1, 'name' => 'B'], ['sn' => 2, 'name' => 'A']], $out['data']);
        $this->assertSame(2, $out['count']);
    }

    public function test_the_modal_search_reaches_the_page_filter_as_q(): void
    {
        $seen = null;
        $r = Request::create('/x', 'GET', ['search' => 'kano', 'q' => 'stale']);
        $this->exporter()->run($r, function () use ($r, &$seen) {
            $seen = $r->query('q');

            return collect();
        }, [], fn ($x) => [], null);

        $this->assertSame('kano', $seen);
    }

    public function test_dates_filter_a_list_and_a_bad_date_is_ignored(): void
    {
        $rows = collect([['d' => '2026-01-10'], ['d' => '2026-03-10'], ['d' => null]]);
        $map = fn ($x) => ['d' => $x['d']];

        $out = $this->exporter()->run(Request::create('/x', 'GET', ['start_date' => '2026-02-01']), fn () => $rows, ['d' => 'D'], $map, 'd');
        $this->assertSame(['2026-03-10'], array_column($out['data'], 'd'));

        $out = $this->exporter()->run(Request::create('/x', 'GET', ['start_date' => "1' OR 1=1"]), fn () => $rows, ['d' => 'D'], $map, 'd');
        $this->assertSame(3, $out['count']);
    }
}
