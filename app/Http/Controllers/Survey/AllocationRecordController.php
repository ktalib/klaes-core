<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyProjectSurveyor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Allocation Records (Compensation > Records): the portions each surveyor was
 * allocated on each project, and how many have been surveyed on a case. Read-only;
 * allocations are set on the project form.
 */
class AllocationRecordController extends Controller
{
    public function index(Request $r)
    {
        if ($r->query('tab') === 'direct') {
            return $this->directAllocation($r);
        }

        $q = SurveyProjectSurveyor::query()
            ->with(['project.coFile'])
            ->withCount(['cases as surveyed' => fn ($c) => $c->whereNotNull('portion_no')])
            ->whereHas('project');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('surveyor_name', 'like', "%$term%")
                  ->orWhereHas('project', fn ($p) => $p->where('name', 'like', "%$term%")
                                                       ->orWhere('project_code', 'like', "%$term%"));
            });
        }
        if ($p = $r->query('project')) $q->where('survey_project_id', $p);
        if ($co = $r->query('co_file')) $q->whereHas('project', fn ($p) => $p->where('survey_co_file_id', $co));

        $rows = $q->get()
            ->each(fn ($a) => $a->surveyed = (int) $a->surveyed)   // sqlsrv returns counts as strings
            ->sortBy(fn ($a) => [$a->project->project_code, mb_strtolower($a->surveyor_name)])->values();

        // Progress is derived per row, so the filter runs after counting.
        if ($prog = $r->query('progress')) {
            $rows = $rows->filter(fn ($a) => match ($prog) {
                'not-started' => $a->surveyed === 0,
                'in-progress' => $a->surveyed > 0 && $a->surveyed < $a->portions,
                'complete'    => $a->surveyed >= $a->portions,
                default       => true,
            })->values();
        }

        $stats = [
            'allocations' => $rows->count(),
            'portions'    => $rows->sum('portions'),
            'surveyed'    => $rows->sum('surveyed'),
            'size'        => $rows->sum(fn ($a) => $a->total_size_ha),
        ];

        $page = max(1, (int) $r->query('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, 15)->values(), $rows->count(), 15, $page,
            ['path' => $r->url(), 'query' => $r->query()]
        );

        return view('survey_module.compensation.allocation_records', [
            'allocations' => $paged,
            'stats'       => $stats,
            'projects'    => SurveyProject::orderByDesc('id')->get(['id', 'project_code', 'name']),
            'coFiles'     => SurveyCoFile::orderByDesc('id')->get(['id', 'co_file_no', 'title']),
        ]);
    }

    /**
     * Direct Allocation files (user, 2026-10-27): every file commissioned as a Direct
     * Allocation (mls_file_no.source), read live from its file_indexings row, joined on
     * the file-number string. Read-only; nothing is copied.
     */
    private function directAllocation(Request $r)
    {
        $db = DB::connection('sqlsrv');
        $live = fn ($w, $t) => $w->whereNull("$t.is_deleted")->orWhere("$t.is_deleted", 0);
        $base = fn () => $db->table('file_indexings as fi')
            ->join('mls_file_no as m', 'm.full_file_number', '=', 'fi.file_number')
            ->where('m.source', 'Direct Allocation')
            ->where(fn ($w) => $live($w, 'm'))
            ->where(fn ($w) => $live($w, 'fi'));

        $q = $base();
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                foreach (['fi.file_number', 'fi.file_title', 'fi.plot_number', 'fi.district', 'm.district', 'fi.location', 'm.tp_no'] as $col) {
                    $w->orWhere($col, 'like', "%$term%");
                }
            });
        }
        if ($lga = $r->query('lga')) $q->where('fi.lga', $lga);
        if ($use = $r->query('land_use')) $q->where('fi.land_use_type', $use);
        if ($year = $r->query('year')) $q->where('m.year', (int) $year);

        $files = $q->select([
                'fi.id', 'fi.file_number', 'fi.file_title', 'fi.plot_number', 'fi.district', 'fi.lga', 'fi.location',
                'fi.land_use_type', 'fi.plot_size', 'fi.is_decommissioned', 'fi.successor_file_no',
                'm.district as mls_district', 'm.tp_no', 'm.commissioning_date', 'm.year',
            ])
            ->orderByDesc('m.commissioning_date')->orderByDesc('fi.id')
            ->paginate(20)->withQueryString();

        $stats = [
            'files'          => $base()->count(),
            'this_year'      => $base()->where('m.year', now()->year)->count(),
            'decommissioned' => $base()->where('fi.is_decommissioned', 1)->count(),
            'not_indexed'    => $db->table('mls_file_no as m')->where('m.source', 'Direct Allocation')->where(fn ($w) => $live($w, 'm'))
                ->whereNotExists(fn ($x) => $x->from('file_indexings as fi')->whereColumn('fi.file_number', 'm.full_file_number'))->count(),
        ];

        return view('survey_module.compensation.allocation_records', [
            'tab'      => 'direct',
            'files'    => $files,
            'stats'    => $stats,
            'lgas'     => $base()->whereNotNull('fi.lga')->where('fi.lga', '<>', '')->distinct()->orderBy('fi.lga')->pluck('fi.lga'),
            'landUses' => $base()->whereNotNull('fi.land_use_type')->where('fi.land_use_type', '<>', '')->distinct()->orderBy('fi.land_use_type')->pluck('fi.land_use_type'),
            'years'    => $base()->whereNotNull('m.year')->distinct()->orderByDesc('m.year')->pluck('m.year'),
        ]);
    }
}
