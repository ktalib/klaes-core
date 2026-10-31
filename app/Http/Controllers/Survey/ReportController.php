<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyExamination;
use App\Models\Survey\SurveyLpkn;
use App\Models\Survey\SurveyOpRecord;
use App\Models\Survey\SurveyPlotAllocation;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filterable reports. Every figure is queried; nothing is hard-coded.
 *
 * Monetary Compensation and Land to Land totals come from disjoint sets of cases, because a
 * project's scheme is exclusive — so they are never double counted.
 */
class ReportController extends Controller
{
    /**
     * Cross-module summary: KPIs, charts and their tables (SurveyReportData). Every
     * figure is queried; nothing is hard-coded.
     */
    public function index(Request $r)
    {
        [$from, $to] = $this->range($r);
        $data = app(\App\Services\SurveyReportData::class)->build($from, $to);

        return view('survey_module.reports', $data + compact('from', 'to'));
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
        $db = DB::connection('sqlsrv');
        $type = array_key_exists($r->query('type'), \App\Http\Controllers\Survey\GknApplicationController::TYPES) ? $r->query('type') : null;

        // Application files (GKN Applications): every indexed or commissioned file, all time.
        $files = function (?string $t) use ($db) {
            $likes = $t ? \App\Http\Controllers\Survey\GknApplicationController::TYPES[$t]['like']
                        : collect(\App\Http\Controllers\Survey\GknApplicationController::TYPES)->pluck('like')->flatten()->all();

            return $db->table('file_indexings')->whereNotNull('file_number')
                ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
                ->where(function ($w) use ($likes) { foreach ($likes as $l) $w->orWhere('file_number', 'like', $l); });
        };
        $byType = [];
        foreach (\App\Http\Controllers\Survey\GknApplicationController::TYPES as $k => $t) {
            $byType[$k] = ['label' => $t['label'], 'files' => $files($k)->count(),
                           'commissioned' => $files($k)->where('source', \App\Http\Controllers\Survey\GknApplicationController::COMMISSIONED_SOURCE)->count()];
        }
        $byLandUse = $files($type)->selectRaw("COALESCE(NULLIF(LTRIM(RTRIM(land_use_type)), ''), 'Not recorded') AS land_use, COUNT(*) AS total")
            ->groupByRaw("COALESCE(NULLIF(LTRIM(RTRIM(land_use_type)), ''), 'Not recorded')")->orderByDesc('total')->get();

        // Survey work on those files in the period: Survey's own instructions and what came of them.
        $its = \App\Models\Survey\SurveyIts::query()->whereNotNull('its_number')->whereBetween('issued_at', [$from, $to])
            ->when($type, fn ($q) => $q->where('file_type', $type));
        $itsIds = (clone $its)->pluck('id');
        $reports = \App\Models\Survey\SurveySurveyorReport::whereIn('survey_its_id', $itsIds)->get(['survey_its_id', 'report_ref', 'status'])->keyBy('survey_its_id');
        $beacons = $db->table('survey_its_coordinates')->whereNull('deleted_at')->whereIn('survey_its_id', $itsIds->all() ?: [0])
            ->selectRaw('survey_its_id, COUNT(*) n')->groupBy('survey_its_id')->pluck('n', 'survey_its_id');

        $totals = [
            'files'     => $files($type)->count(),
            'its'       => $itsIds->count(),
            'reports'   => $reports->where('status', \App\Models\Survey\SurveySurveyorReport::SUBMITTED)->count(),
            'completed' => (clone $its)->where('status', \App\Models\Survey\SurveyIts::COMPLETED)->count(),
            'beacons'   => (int) $beacons->sum(),
            'readings'  => $db->table('survey_its_observations')->whereNull('deleted_at')->whereIn('survey_its_id', $itsIds->all() ?: [0])->count(),
        ];

        return view('survey_module.gkn.reports', [
            'rows'      => $its->orderByDesc('issued_at')->paginate(20)->withQueryString(),
            'reports'   => $reports,
            'beacons'   => $beacons,
            'totals'    => $totals,
            'byType'    => $byType,
            'byLandUse' => $byLandUse,
            'type'      => $type,
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    private function range(Request $r): array
    {
        $from = $r->query('from') ? \Carbon\Carbon::parse($r->query('from'))->startOfDay()
                                  : now()->startOfYear();
        $to   = $r->query('to') ? \Carbon\Carbon::parse($r->query('to'))->endOfDay()
                                : now()->endOfYear();

        return [$from, $to];
    }
}
