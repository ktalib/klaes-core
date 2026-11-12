<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorPortalAccount as Account;
use App\Models\Survey\SurveyPlanFile as PlanFile;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Models\Survey\SurveyorPortalNotification as Note;
use App\Services\Survey\CompanyPortal;
use App\Services\Survey\PortalSurveyorReports;
use App\Services\Survey\ReportRecords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            // The company's beacon prefix, shown on the dashboard (user, 2026-11-12).
            'prefix' => trim((string) \Illuminate\Support\Facades\DB::connection('sqlsrv')->table('survey_companies')->where('id', $me->company_id)->value('beacon_letters')),
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

    /**
     * Uploaded Survey Plans > Original (every plan the company's surveyors uploaded) or Signed (those with a
     * signed copy). User, 2026-11-12. Read only.
     */
    public function plans(Request $r, string $which)
    {
        abort_unless(Plan::installed(), 404);
        $term = trim((string) $r->query('q', ''));
        $rows = Plan::with('files')->whereIn('surveyor_portal_account_id', $this->portal->surveyors($this->me())->select('id'))
            ->when($which === 'signed', fn ($q) => $q->where('stage', Plan::STAGE_SIGNED))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('upload_ref', 'like', "%{$term}%")->orWhere('drawn_by', 'like', "%{$term}%")
                ->orWhere('plot_no', 'like', "%{$term}%")->orWhere('district', 'like', "%{$term}%")->orWhere('lga', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")->orWhereHas('portalAccount', fn ($a) => $a->where('name', 'like', "%{$term}%"))))
            ->with('portalAccount:id,name,surveyor_id_no')
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('surveyor_portal.company.survey_plans', ['which' => $which, 'rows' => $rows, 'search' => $term]);
    }

    /** A company surveyor's plan file: the original upload or the signed copy. */
    public function planFile(int $plan, string $which)
    {
        $p = Plan::with('files')->whereIn('surveyor_portal_account_id', $this->portal->surveyors($this->me())->select('id'))->findOrFail($plan);
        $path = $p->file_path;
        $name = $p->file_name;
        if ($which === 'signed') {
            $f = $p->latestFile(PlanFile::KIND_SIGNED);
            abort_unless($f, 404);
            [$path, $name] = [$f->file_path, $f->file_name];
        }
        abort_unless($path && Storage::disk(Plan::DISK)->exists($path), 404);

        return Storage::disk(Plan::DISK)->response($path, $name ?: basename($path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
