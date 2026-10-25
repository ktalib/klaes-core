<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyProjectSurveyor;
use Illuminate\Http\Request;

/**
 * Allocation Records (Compensation > Records): the portions each surveyor was
 * allocated on each project, and how many have been surveyed on a case. Read-only;
 * allocations are set on the project form.
 */
class AllocationRecordController extends Controller
{
    public function index(Request $r)
    {
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
}
