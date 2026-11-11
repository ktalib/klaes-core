<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorPortalAccount as Account;
use App\Models\Survey\SurveyorPortalNotification as Note;
use App\Services\Survey\CompanyPortal;
use App\Services\Survey\PortalSurveyorReports;
use App\Services\Survey\ReportRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A company's pages on the MLPP-SG portal (user, 2026-11-10): Dashboard, Our Surveyors, Our Beacons (Issued
 * Beacons, Used Beacons, Surveyors Report) and Notifications. Read only; behind surveyor.kind:company.
 */
class CompanyController extends Controller
{
    public function __construct(private CompanyPortal $portal)
    {
    }

    private function me(): Account
    {
        return Auth::guard('surveyor')->user();
    }

    public function dashboard()
    {
        $me = $this->me();

        return view('surveyor_portal.company.dashboard', [
            'stats' => $this->portal->stats($me),
            'latest' => $this->portal->beacons($me)->orderByDesc('survey_beacon_register_id')->orderByDesc('beacon_seq')->limit(5)->get(),
            'notes' => Note::visibleTo($me)->with('account:id,name')->orderByDesc('id')->limit(3)->get(),
        ]);
    }

    public function surveyors(Request $r)
    {
        $term = trim((string) $r->query('q', ''));
        $rows = $this->portal->surveyors($this->me())
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('surveyor_id_no', 'like', "%{$term}%")
                ->orWhere('surcon_number', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('surveyor_portal.company.surveyors', ['rows' => $rows, 'search' => $term]);
    }

    /** Our Beacons > Issued Beacons (every number issued to the company) or Used Beacons (with coordinates). */
    public function beacons(Request $r, string $which)
    {
        abort_unless(in_array($which, ['issued', 'used'], true), 404);
        $me = $this->me();
        $term = mb_strtoupper(preg_replace('/\s+/', '', trim((string) $r->query('q', ''))));

        $rows = $this->portal->beacons($me)
            ->when($which === 'used', fn ($q) => $q->where('used', 1))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw("UPPER(REPLACE(beacon_no, ' ', '')) LIKE ?", ["%{$term}%"])
                ->orWhereHas('register', fn ($g) => $g->where('surveyor_name', 'like', '%' . trim((string) $r->query('q')) . '%'))))
            ->orderByDesc('survey_beacon_register_id')->orderBy('beacon_seq')->orderBy('id')
            ->paginate(25)->withQueryString();

        return view('surveyor_portal.company.beacons', [
            'which' => $which,
            'rows' => $rows,
            'onReport' => $which === 'used' ? $this->portal->reportsFor($me, $rows->getCollection()) : collect(),
            'search' => (string) $r->query('q', ''),
            'stats' => $this->portal->stats($me),
        ]);
    }

    /** Our Beacons > Surveyors Report: the company's surveyors' reports. */
    public function reports(Request $r)
    {
        $term = trim((string) $r->query('q', ''));
        $rows = $this->portal->reports($this->me())
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('report_ref', 'like', "%{$term}%")->orWhere('its_number', 'like', "%{$term}%")
                ->orWhere('surveyor_name', 'like', "%{$term}%")->orWhere('plot_no', 'like', "%{$term}%")->orWhere('district', 'like', "%{$term}%")
                ->orWhere('lga', 'like', "%{$term}%")->orWhere('location', 'like', "%{$term}%")))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('surveyor_portal.company.reports', ['rows' => $rows, 'search' => $term]);
    }

    /** The report as printed, read only. A draft prints without a Submitted date. */
    public function printReport(int $report)
    {
        $cr = $this->portal->report($this->me(), $report);
        $reports = app(PortalSurveyorReports::class);

        return view('surveyor_portal.reports.print', [
            'report' => $cr,
            'coords' => $reports->coordinates($cr),
            'observations' => $reports->observations($cr),
            'rec' => app(ReportRecords::class)->summary($reports->anchor($cr)),
            'backUrl' => route('surveyor-portal.company.reports'),
        ]);
    }
}
