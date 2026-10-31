<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Services\Survey\LayoutRecords;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * GKN → Public Survey → Layout Records (user, 2026-10-30): the layouts, and for one
 * layout (?layout=<key>) its records plot by plot, from the allocation lists and the
 * Deeds OPs, with the showings to the allottee. See App\Services\Survey\LayoutRecords.
 */
class LayoutRecordsController extends Controller
{
    public function index(Request $r, LayoutRecords $records)
    {
        if ($r->boolean('refresh')) LayoutRecords::forget();

        if ($key = $r->query('layout')) {
            return $this->layout($r, $records, (string) $key);
        }

        $all = $records->layouts();
        $layouts = $all;
        if (($term = trim((string) $r->query('q', ''))) !== '') {
            $t = mb_strtolower($term);
            $layouts = $layouts->filter(fn ($l) => str_contains(mb_strtolower($l['key'] . ' ' . implode(' ', array_keys($l['plans'])) . ' ' . $l['name']), $t));
        }
        if ($r->query('has') === 'lists') $layouts = $layouts->where('lines', '>', 0);
        if ($r->query('has') === 'shown') $layouts = $layouts->where('showings', '>', 0);

        return view('survey_module.records.layout_records', [
            'layouts' => $this->page($r, $layouts->values(), 25),
            'totals'  => ['layouts' => $all->count(), 'lines' => $all->sum('lines'), 'ops' => $all->sum('ops'), 'showings' => $all->sum('showings')],
        ]);
    }

    private function layout(Request $r, LayoutRecords $records, string $key)
    {
        [$layout, $rows] = $records->records($key);
        abort_unless($layout, 404);

        $all = $rows;
        if (($term = trim((string) $r->query('q', ''))) !== '') {
            $t = mb_strtolower($term);
            $rows = $rows->filter(fn ($x) => str_contains(mb_strtolower(implode(' ', [$x['plot'], $x['allottee'], implode(' ', array_column($x['ops'], 'serial'))])), $t));
        }
        match ($r->query('show')) {
            'op'      => $rows = $rows->filter(fn ($x) => $x['ops']),
            'no-op'   => $rows = $rows->filter(fn ($x) => ! $x['ops']),
            'shown'   => $rows = $rows->filter(fn ($x) => $x['showings']),
            'unshown' => $rows = $rows->filter(fn ($x) => $x['ops'] && ! $x['showings']),
            default   => null,
        };

        return view('survey_module.records.layout_records_show', [
            'layout' => $layout,
            'rows'   => $this->page($r, $rows->values(), 50),
            'totals' => ['plots' => $all->count(), 'with_op' => $all->filter(fn ($x) => $x['ops'])->count(),
                         'listed' => $all->filter(fn ($x) => $x['lines'])->count(), 'shown' => $all->filter(fn ($x) => $x['showings'])->count()],
        ]);
    }

    private function page(Request $r, $items, int $per): LengthAwarePaginator
    {
        $page = max(1, (int) $r->query('page', 1));

        return new LengthAwarePaginator($items->forPage($page, $per)->values(), $items->count(), $per, $page, ['path' => $r->url(), 'query' => $r->query()]);
    }
}
