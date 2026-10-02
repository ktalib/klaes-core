<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyExamination;
use App\Models\Survey\SurveyFileMovement;
use App\Models\Survey\SurveyGknRecord;
use App\Models\Survey\SurveyLpkn;
use App\Models\Survey\SurveyOpRecord;
use App\Models\Survey\SurveyPlotAllocation;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Live dashboards. Every figure here is a query — the prototype's literals
 * (1,247 cases, N45.2M, 2,340 plots) are all derived now.
 */
class DashboardController extends Controller
{
    /** Survey Department overview. */
    public function index(Request $r)
    {
        $kpis = [
            'cases'        => SurveyCompCase::count(),
            'pending'      => SurveyCompCase::whereIn('status', ['Pending', 'Review'])->count(),
            'ops_issued'   => SurveyOpRecord::where('status', 'Issued')->count(),
            'gkn'          => SurveyGknRecord::count(),
            'completed'    => SurveyCompCase::where('status', 'Completed')->count(),
            'awaiting_gis' => SurveyOpRecord::whereHas('steps', fn ($q) =>
                                  $q->where('step_no', 2)->where('status', 'active'))->count(),
        ];

        // The most recently touched permit drives the workflow strip.
        $op = SurveyOpRecord::with(['steps', 'case'])
            ->where('status', '!=', 'Issued')
            ->orderByDesc('updated_at')
            ->first()
            ?? SurveyOpRecord::with(['steps', 'case'])->orderByDesc('id')->first();

        $sections = [
            'compensation' => SurveyCompCase::whereIn('status', ['Pending', 'Review'])->count(),
            'gkn'          => SurveyGknRecord::count(),
            'lpkn'         => SurveyLpkn::where('status', '!=', 'Approved')->count(),
            'examination'  => SurveyExamination::whereIn('status', ['Queued', 'In Review'])->count(),
        ];

        return view('survey_module.dashboard', [
            'kpis'       => $kpis,
            'op'         => $op,
            'sections'   => $sections,
            'activities' => $this->recentActivity(),
        ]);
    }

    /** Compensation overview: scheme totals and the monthly trend. */
    public function compensation(Request $r)
    {
        $kpis = [
            'total'     => SurveyCompCase::count(),
            'pending'   => SurveyCompCase::whereIn('status', ['Pending', 'Review'])->count(),
            'approved'  => SurveyCompCase::whereIn('status', ['Active', 'Completed'])->count(),
            'ops'       => SurveyOpRecord::count(),
        ];

        // Monetary cases pay cash for trees; land cases carve plots. Never both,
        // so the two totals are counted from disjoint sets of cases.
        $monetaryCash = (float) SurveyCaseTree::whereHas('case', fn ($q) =>
                            $q->where('scheme_type', SurveyProject::SCHEME_MONETARY))
                            ->sum('line_total');

        $landCaseIds = SurveyCompCase::where('scheme_type', SurveyProject::SCHEME_LAND)->pluck('id');
        $plotRows    = SurveyPlotAllocation::whereIn('survey_comp_case_id', $landCaseIds)->count();
        $declared    = (int) SurveyCompCase::whereIn('id', $landCaseIds)
                            ->whereDoesntHave('plots')->sum('num_plots');

        $summary = [
            'monetary_cash' => $monetaryCash,
            'land_plots'    => $plotRows + $declared,
            'beneficiaries' => SurveyBeneficiary::count(),
        ];

        return view('survey_module.compensation.dashboard', [
            'kpis'    => $kpis,
            'summary' => $summary,
            'chart'   => $this->monthlyCases(),
        ]);
    }

    /** Government lands overview. */
    public function gkn(Request $r)
    {
        $kpis = [
            'total'      => SurveyGknRecord::count(),
            'pending'    => SurveyGknRecord::where('status', 'Pending')->count(),
            'review'     => SurveyGknRecord::where('status', 'Review')->count(),
            'in_transit' => SurveyFileMovement::where('status', 'In Transit')->count(),
        ];

        $byLandUse = SurveyGknRecord::query()
            ->select('land_use', DB::raw('COUNT(*) as total'))
            ->whereNotNull('land_use')
            ->groupBy('land_use')
            ->orderByDesc('total')
            ->get();

        $recent = SurveyGknRecord::orderByDesc('id')->limit(6)->get();

        return view('survey_module.gkn.dashboard', compact('kpis', 'byLandUse', 'recent'));
    }

    /** Spatial tools. Counts of what actually carries coordinates. */
    public function gis(Request $r)
    {
        $hasCoords = fn ($q) => $q->whereNotNull('coordinates')->where('coordinates', '!=', '');

        $gisStats = [
            'gkn_total'   => SurveyGknRecord::count(),
            'gkn_mapped'  => SurveyGknRecord::where($hasCoords)->count(),
            'case_total'  => SurveyCompCase::count(),
            'case_mapped' => SurveyCompCase::where($hasCoords)->count(),
            'lpkn_total'  => SurveyLpkn::count(),
            'plot_total'  => SurveyPlotAllocation::count(),
        ];

        $gisQuery   = trim((string) $r->query('q', ''));
        $gisResults = collect();

        if ($gisQuery !== '') {
            $gisResults = $this->searchSpatial($gisQuery);
        }

        return view('survey_module.tools.gis', compact('gisStats', 'gisResults', 'gisQuery'));
    }

    /* ---------------------------------------------------------------- */

    /** GKN parcels and cases matching a free-text term. */
    private function searchSpatial(string $term)
    {
        $like = '%' . $term . '%';

        $gkn = SurveyGknRecord::where(fn ($q) => $q
                ->where('gkn_number', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('prop_district', 'like', $like)
                ->orWhere('coordinates', 'like', $like))
            ->limit(20)->get()
            ->map(fn ($g) => [
                'type'        => 'GKN',
                'ref'         => $g->gkn_number,
                'label'       => $g->title,
                'location'    => $g->property_location,
                'coordinates' => $g->coordinates,
                'url'         => route('survey-module.gkn.edit', $g),
            ]);

        $cases = SurveyCompCase::where(fn ($q) => $q
                ->where('case_ref', 'like', $like)
                ->orWhere('prop_district', 'like', $like)
                ->orWhere('coordinates', 'like', $like))
            ->limit(20)->get()
            ->map(fn ($c) => [
                'type'        => 'Case',
                'ref'         => $c->case_ref,
                'label'       => $c->project->name ?? '—',
                'location'    => $c->property_location,
                'coordinates' => $c->coordinates,
                'url'         => route('survey-module.compensation.cases.show', $c),
            ]);

        return $gkn->concat($cases);
    }

    /** Cases per month for the last six months, for the bar chart. */
    private function monthlyCases(): array
    {
        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $end   = (clone $start)->endOfMonth();
            $out[] = [
                'label' => $start->format('M'),
                'count' => SurveyCompCase::whereBetween('created_at', [$start, $end])->count(),
            ];
        }
        return $out;
    }

    /**
     * Newest records across the module, merged into one feed.
     * Cheap enough at this scale: six small ordered reads, no joins.
     */
    private function recentActivity(): array
    {
        $feed = [];

        foreach (SurveyCompCase::with('project')->orderByDesc('id')->limit(4)->get() as $c) {
            $feed[] = ['icon' => 'fa-plus', 'colour' => null, 'when' => $c->created_at,
                       'text' => "Case <strong>{$c->case_ref}</strong> registered"
                                 . ($c->project ? " under {$c->project->name}" : '')];
        }
        foreach (SurveyGknRecord::orderByDesc('id')->limit(3)->get() as $g) {
            $feed[] = ['icon' => 'fa-building', 'colour' => null, 'when' => $g->created_at,
                       'text' => "GKN <strong>{$g->gkn_number}</strong> registered"];
        }
        foreach (SurveyOpRecord::where('status', 'Issued')->orderByDesc('issued_at')->limit(3)->get() as $o) {
            $feed[] = ['icon' => 'fa-file-signature', 'colour' => 'var(--secondary)', 'when' => $o->issued_at,
                       'text' => "Occupancy Permit <strong>{$o->op_number}</strong> issued"];
        }
        foreach (SurveyExamination::where('status', 'Returned')->orderByDesc('reviewed_at')->limit(3)->get() as $e) {
            $feed[] = ['icon' => 'fa-exclamation-triangle', 'colour' => 'var(--danger)', 'when' => $e->reviewed_at,
                       'text' => "Examination <strong>{$e->exam_ref}</strong> returned"];
        }

        usort($feed, fn ($a, $b) => ($b['when']?->timestamp ?? 0) <=> ($a['when']?->timestamp ?? 0));

        return array_slice($feed, 0, 8);
    }
}
