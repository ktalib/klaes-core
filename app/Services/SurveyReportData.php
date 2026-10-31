<?php

namespace App\Services;

use App\Http\Controllers\Survey\GknApplicationController;
use App\Models\Survey\SurveyBoundaryDispute;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCoFile;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyComplaint;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveyOldSurvey;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyRegisterFile;
use App\Models\Survey\SurveySurveyorReport;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Survey Reports (user, 2026-10-25: "graphs, charts and tables"). Every figure is
 * queried live; the date range limits what was recorded in it, while "open" counts
 * are as of now.
 */
class SurveyReportData
{
    public function build(Carbon $from, Carbon $to): array
    {
        return [
            'kpis'     => $this->kpis($from, $to),
            'issuance' => $this->monthlyIssuance($from, $to),
            'register' => $this->monthlyRegister($from, $to),
            'plots'    => $this->plotsByProject(),
            'cash'     => $this->cashByProject(),
            'its'      => $this->itsByStatus(),
        ];
    }

    private function kpis(Carbon $from, Carbon $to): array
    {
        $in = fn ($q, $col = 'created_at') => $q->whereBetween($col, [$from, $to]);
        $db = DB::connection('sqlsrv');

        $apps = $db->table('file_indexings')->whereNotNull('file_number')
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where(function ($w) {
                foreach (collect(GknApplicationController::TYPES)->pluck('like')->flatten() as $like) $w->orWhere('file_number', 'like', $like);
            })->count();

        return [
            'compensation' => [
                'co_files'      => SurveyCoFile::count(),
                'projects'      => SurveyProject::count(),
                'cases'         => $in(SurveyCompCase::query())->count(),
                'cash'          => (float) $in(SurveyCaseTree::query())->sum('line_total'),
                'complaints_open' => SurveyComplaint::where('status', '!=', SurveyComplaint::RESOLVED)->count(),
            ],
            'gkn' => [
                'applications' => $apps,
                'its_issued'   => $in(SurveyIts::query()->whereNotNull('its_number'), 'issued_at')->count(),
                'reports'      => $in(SurveySurveyorReport::where('status', SurveySurveyorReport::SUBMITTED), 'submitted_at')->count(),
                'coordinates'  => $db->table('survey_its_coordinates')->whereNull('deleted_at')->count(),
            ],
            'examination' => [
                'received'       => $in(SurveyRegisterFile::query(), 'received_at')->count(),
                'open'           => SurveyRegisterFile::whereIn('status', [SurveyRegisterFile::RECEIVED, SurveyRegisterFile::IN_PROGRESS])->count(),
                'beacons'        => (int) $db->table('survey_beacon_register')->whereNull('deleted_at')->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])->sum('total_issued'),
                'control_points' => (int) $db->table('survey_control_point_register')->whereNull('deleted_at')->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])->sum('total_issued'),
                'disputes_open'  => SurveyBoundaryDispute::where('status', '!=', SurveyBoundaryDispute::RESOLVED)->count(),
                'old_pending'    => SurveyOldSurvey::where('verification_status', SurveyOldSurvey::PENDING)->count(),
            ],
        ];
    }

    /** Months in the range, capped at the last 24, as 'Y-m' => 'M Y'. */
    private function months(Carbon $from, Carbon $to): array
    {
        $start = $from->copy()->startOfMonth();
        // Months that have not happened yet are left off, not plotted as zero.
        $end = $to->copy()->min(now())->startOfMonth();
        if ($end->lt($start)) $end = $start->copy();
        if ($start->diffInMonths($end) > 23) $start = $end->copy()->subMonths(23);

        $out = [];
        foreach (CarbonPeriod::create($start, '1 month', $end) as $m) $out[$m->format('Y-m')] = $m->format('M Y');

        return $out;
    }

    /** Beacon and control point numbers issued per month (issue date). */
    private function monthlyIssuance(Carbon $from, Carbon $to): array
    {
        $months = $this->months($from, $to);
        $series = [];
        foreach (['beacons' => 'survey_beacon_register', 'control_points' => 'survey_control_point_register'] as $k => $table) {
            $rows = DB::connection('sqlsrv')->table($table)->whereNull('deleted_at')
                ->whereBetween('issue_date', [array_key_first($months) . '-01', $to->toDateString()])
                ->selectRaw("FORMAT(issue_date, 'yyyy-MM') AS ym, SUM(total_issued) AS n")
                ->groupByRaw("FORMAT(issue_date, 'yyyy-MM')")->pluck('n', 'ym');
            $series[$k] = array_map(fn ($ym) => (int) ($rows[$ym] ?? 0), array_keys($months));
        }

        return ['labels' => array_values($months), 'series' => $series];
    }

    /** Register File lines received per month, by purpose. */
    private function monthlyRegister(Carbon $from, Carbon $to): array
    {
        $months = $this->months($from, $to);
        $rows = DB::connection('sqlsrv')->table('survey_register_files')->whereNull('deleted_at')
            ->whereBetween('received_at', [array_key_first($months) . '-01', $to])
            ->selectRaw("FORMAT(received_at, 'yyyy-MM') AS ym, purpose, COUNT(*) AS n")
            ->groupByRaw("FORMAT(received_at, 'yyyy-MM'), purpose")->get();

        $series = ['verification' => [], 'examination' => []];
        foreach (array_keys($series) as $p) {
            $byMonth = $rows->where('purpose', $p)->pluck('n', 'ym');
            $series[$p] = array_map(fn ($ym) => (int) ($byMonth[$ym] ?? 0), array_keys($months));
        }

        return ['labels' => array_values($months), 'series' => $series];
    }

    /** Land to Land projects: owner and Government plots (per-beneficiary 50:50, as the calculator). */
    private function plotsByProject(): array
    {
        $schedule = app(SurveyCompensationSchedule::class);
        $out = [];
        foreach (SurveyProject::where('scheme_type', SurveyProject::SCHEME_LAND)->orderBy('project_code')->get() as $p) {
            $t = $schedule->forProject($p)['totals'];
            if ($t['plots'] > 0) $out[] = ['label' => $p->project_code, 'name' => $p->name, 'owner' => $t['owner'], 'govt' => $t['govt']];
        }

        return $out;
    }

    /** Monetary Compensation projects: the cash valued on their cases. */
    private function cashByProject(): array
    {
        return SurveyCaseTree::query()
            ->join('survey_comp_cases as c', 'c.id', '=', 'survey_case_trees.survey_comp_case_id')
            ->join('survey_projects as p', 'p.id', '=', 'c.survey_project_id')
            ->whereNull('c.deleted_at')->whereNull('p.deleted_at')->where('c.status', '!=', 'Rejected')
            ->groupBy('p.project_code', 'p.name')
            ->orderByRaw('SUM(survey_case_trees.line_total) DESC')
            ->selectRaw('p.project_code AS label, p.name AS name, SUM(survey_case_trees.line_total) AS amount')
            ->get()->map(fn ($r) => ['label' => $r->label, 'name' => $r->name, 'amount' => (float) $r->amount])->all();
    }

    /** Survey's own I to S by status, in workflow order; Cadastral's issued I to S alongside. */
    private function itsByStatus(): array
    {
        $counts = SurveyIts::selectRaw('status, COUNT(*) n')->groupBy('status')->pluck('n', 'status');

        return [
            'labels'    => SurveyIts::STATUSES,
            'values'    => array_map(fn ($s) => (int) ($counts[$s] ?? 0), SurveyIts::STATUSES),
            'cadastral' => DB::connection('sqlsrv')->table('cadastral_survey_jobs')->whereNull('deleted_at')->whereNotNull('its_number')->count(),
        ];
    }
}
