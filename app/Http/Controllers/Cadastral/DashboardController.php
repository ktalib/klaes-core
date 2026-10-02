<?php

namespace App\Http\Controllers\Cadastral;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralChart;
use App\Models\Cadastral\CadastralFileReceipt;
use App\Models\Cadastral\CadastralPlanDescription;
use App\Models\Cadastral\CadastralReport;
use App\Models\CadastralOfficer;
use App\Services\Cadastral\DashboardMetrics;
use App\Services\Cadastral\SurveyJobNumberGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The module dashboard and Analytics.
 *
 * The rebuild brief folds the four per-unit dashboards into one dashboard
 * (six tiles, the latest open report, recent activity) plus a management
 * Analytics page. The unit dashboards stay reachable by URL but are no longer
 * in the sidebar.
 *
 * Every figure comes from DashboardMetrics so the same definition of "open"
 * holds on the dashboard, Analytics and the sidebar badges.
 */
class DashboardController extends Controller
{
    public function __construct(
        private DashboardMetrics $metrics,
        private SurveyJobNumberGenerator $numbers,
    ) {}

    /** The module landing page. */
    public function index()
    {
        return view('cadastral_module.dashboard', [
            'm' => $this->metrics->overview(),
            // Surfaced on the landing page because numbers issued in a
            // placeholder format would all need reissuing.
            'jobFormatUnconfirmed' => $this->numbers->formatIsPlaceholder(),
        ]);
    }

    /** Management figures for a date range; defaults to the year to date. */
    public function analytics(Request $r)
    {
        $from = $this->dateOr($r->query('from'), now()->startOfYear());
        $to   = $this->dateOr($r->query('to'), now());

        // A reversed range is a slip, not a request for nothing.
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return view('cadastral_module.analytics.index', [
            'a'    => $this->metrics->analytics($from, $to),
            'from' => $from,
            'to'   => $to,
        ]);
    }

    /** A Y-m-d query value, or the fallback when it is missing or malformed. */
    private function dateOr($value, Carbon $fallback): Carbon
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return Carbon::createFromFormat('!Y-m-d', $value);
            } catch (\Throwable) {
                // fall through
            }
        }

        return $fallback->copy()->startOfDay();
    }

    /** 4.3 Information — charting, index cards, survey jobs. */
    public function information()
    {
        return view('cadastral_module.information.dashboard', [
            'm'         => $this->metrics->information(),
            'navCounts' => $this->navCounts(),
        ]);
    }

    /** 4.4 Plan and Description — area, pillars, fees. */
    public function planDescription()
    {
        return view('cadastral_module.pnd.dashboard', [
            'm'         => $this->metrics->planDescription(),
            'navCounts' => $this->navCounts(),
        ]);
    }

    /**
     * The queue count beside each tab: the one number that says which unit is
     * busiest, without having to open it.
     */
    private function navCounts(): array
    {
        return [
            'registry' => CadastralFileReceipt::where('status', 'Received')->count(),

            'information' => CadastralChart::current()
                ->where('charting_required', true)
                ->where('status', 'Draft')
                ->count(),

            'reports' => CadastralReport::whereNotIn('status', ['Dispatched', 'Rejected'])->count(),

            'pnd' => CadastralPlanDescription::whereDoesntHave(
                'bills', fn ($q) => $q->where('status', 'Issued')
            )->count(),
        ];
    }

    /** Shared with ReportController::dashboard so the tab strip agrees everywhere. */
    public function navCountsFor(): array
    {
        return $this->navCounts();
    }

    /** The posts the signed-in user holds, for the report desk's routing. */
    public static function postsForCurrentUser(): array
    {
        return CadastralOfficer::postsFor(auth()->id());
    }
}
