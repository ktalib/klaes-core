<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyExamination;
use App\Models\Survey\SurveyGknRecord;
use App\Models\Survey\SurveyLpkn;
use App\Models\Survey\SurveyOpRecord;
use App\Models\Survey\SurveyPlotAllocation;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filterable reports. Every figure is queried; nothing is hard-coded.
 *
 * Monetary and Land-for-Land totals come from disjoint sets of cases, because a
 * project's scheme is exclusive — so they are never double counted.
 */
class ReportController extends Controller
{
    /** Cross-module summary. */
    public function index(Request $r)
    {
        [$from, $to] = $this->range($r);

        $within = fn ($q) => $q->whereBetween('created_at', [$from, $to]);

        $summary = [
            'compensation' => [
                'cases'         => SurveyCompCase::where($within)->count(),
                'beneficiaries' => SurveyBeneficiary::where($within)->count(),
                'cash'          => (float) SurveyCaseTree::where($within)->sum('line_total'),
            ],
            'gkn' => [
                'records'    => SurveyGknRecord::where($within)->count(),
                'in_transit' => SurveyGknRecord::where('status', 'Pending')->count(),
            ],
            'examination' => [
                'queued'   => SurveyExamination::whereIn('status', ['Queued', 'In Review'])->count(),
                'passed'   => SurveyExamination::where('status', 'Passed')->where($within)->count(),
                'returned' => SurveyExamination::where('status', 'Returned')->where($within)->count(),
            ],
            'plots' => [
                'rows'  => SurveyPlotAllocation::where($within)->count(),
                'lpkn'  => SurveyLpkn::where($within)->count(),
            ],
            'op' => [
                'issued' => SurveyOpRecord::where('status', 'Issued')->where($within)->count(),
                'queue'  => SurveyOpRecord::where('status', '!=', 'Issued')->count(),
            ],
        ];

        return view('survey_module.reports', compact('summary', 'from', 'to'));
    }

    /** Compensation report, filterable by scheme, status and date. */
    public function compensation(Request $r)
    {
        [$from, $to] = $this->range($r);
        $scheme = $r->query('scheme');
        $status = $r->query('status');

        $cases = SurveyCompCase::query()
            ->with('project')
            ->whereBetween('created_at', [$from, $to])
            ->when($scheme, fn ($q) => $q->where('scheme_type', $scheme))
            ->when($status, fn ($q) => $q->where('status', $status));

        $ids = (clone $cases)->pluck('id');

        // Cash belongs only to monetary cases, plots only to land cases.
        $monetaryIds = SurveyCompCase::whereIn('id', $ids)
            ->where('scheme_type', SurveyProject::SCHEME_MONETARY)->pluck('id');
        $landIds = SurveyCompCase::whereIn('id', $ids)
            ->where('scheme_type', SurveyProject::SCHEME_LAND)->pluck('id');

        $plotRows = SurveyPlotAllocation::whereIn('survey_comp_case_id', $landIds)->count();
        $declared = (int) SurveyCompCase::whereIn('id', $landIds)
            ->whereDoesntHave('plots')->sum('num_plots');
        $totalPlots = $plotRows + $declared;

        $totals = [
            'cases'         => $ids->count(),
            'monetary_cash' => (float) SurveyCaseTree::whereIn('survey_comp_case_id', $monetaryIds)->sum('line_total'),
            'plots'         => $totalPlots,
            'plots_farmer'  => (int) floor($totalPlots / 2),
            'plots_govt'    => (int) ceil($totalPlots / 2),
            'beneficiaries' => SurveyBeneficiary::whereIn('survey_comp_case_id', $ids)->count(),
        ];

        $byScheme = SurveyCompCase::query()
            ->select('scheme_type', DB::raw('COUNT(*) as total'))
            ->whereIn('id', $ids)
            ->groupBy('scheme_type')
            ->pluck('total', 'scheme_type');

        return view('survey_module.compensation.reports', [
            'rows'     => $cases->orderByDesc('id')->paginate(20)->withQueryString(),
            'totals'   => $totals,
            'byScheme' => $byScheme,
            'from'     => $from,
            'to'       => $to,
        ]);
    }

    /** GKN report, filterable by status, land use and date. */
    public function gkn(Request $r)
    {
        [$from, $to] = $this->range($r);

        $q = SurveyGknRecord::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($r->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($r->query('land_use'), fn ($w, $v) => $w->where('land_use', $v));

        $ids = (clone $q)->pluck('id');

        $totals = [
            'records' => $ids->count(),
            'area'    => (float) SurveyGknRecord::whereIn('id', $ids)->sum('area_ha'),
            'mapped'  => SurveyGknRecord::whereIn('id', $ids)
                            ->whereNotNull('coordinates')->where('coordinates', '!=', '')->count(),
        ];

        $byLandUse = SurveyGknRecord::query()
            ->select('land_use', DB::raw('COUNT(*) as total'), DB::raw('SUM(area_ha) as area'))
            ->whereIn('id', $ids)
            ->whereNotNull('land_use')
            ->groupBy('land_use')
            ->orderByDesc('total')
            ->get();

        return view('survey_module.gkn.reports', [
            'rows'      => $q->orderByDesc('id')->paginate(20)->withQueryString(),
            'totals'    => $totals,
            'byLandUse' => $byLandUse,
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    /**
     * Report window. Defaults to the current calendar year, which is what the
     * prototype's filters showed.
     */
    private function range(Request $r): array
    {
        $from = $r->query('from') ? \Carbon\Carbon::parse($r->query('from'))->startOfDay()
                                  : now()->startOfYear();
        $to   = $r->query('to') ? \Carbon\Carbon::parse($r->query('to'))->endOfDay()
                                : now()->endOfYear();

        return [$from, $to];
    }
}
