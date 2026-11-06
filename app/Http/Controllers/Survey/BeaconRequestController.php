<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Sdi\SdiMapRequest;
use App\Models\Survey\SurveyBeaconRequest;
use App\Models\Survey\SurveyorPortalAccount;
use App\Services\Survey\BeaconPool;
use App\Services\Survey\BeaconRequests;
use App\Services\Survey\SurveyorAccounts;
use App\Services\Survey\SurveyorPortalNotifier;
use App\Services\Survey\SurveySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The Surveyor General's desk (CR 03112026 items 18-21): surveyor registrations waiting for approval,
 * and beacon requests from the portal and from Cadastral I to S. Any holder of Survey - SG may act
 * (user, 2026-11-05). EnforceModulePermission only refuses delete, so the check is here.
 */
class BeaconRequestController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    public function __construct(private BeaconRequests $requests, private SurveyorAccounts $accounts)
    {
    }

    private function authorizeSg(): void
    {
        abort_unless((bool) optional(Auth::user())->canDo(SurveyorPortalNotifier::SG_ROLE, 'view'), 403, 'This page is for the Surveyor General.');
    }

    public function index(Request $r)
    {
        $this->authorizeSg();
        $tab = in_array($r->query('tab'), ['registrations', 'maps'], true) ? $r->query('tab') : 'requests';
        $term = trim((string) $r->query('q', ''));
        $status = (string) $r->query('status', '');

        $requests = $this->requestsQuery($r)->paginate(20, ['*'], 'page')->withQueryString();

        $accounts = $this->accountsQuery($r)->paginate(20, ['*'], 'page')->withQueryString();

        return view('survey_module.beacon_requests.index', [
            'tab' => $tab, 'requests' => $requests, 'accounts' => $accounts, 'status' => $status,
            'maps' => $this->mapsQuery($r)->paginate(20, ['*'], 'page')->withQueryString(),
            'mapCounts' => SdiMapRequest::whereNotNull('surveyor_portal_account_id')->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'companies' => SurveySettings::companies(),
            'pool' => BeaconPool::status(),
            'stats' => [
                'waiting'  => SurveyBeaconRequest::where('status', SurveyBeaconRequest::SUBMITTED)->count(),
                'issued'   => SurveyBeaconRequest::where('status', SurveyBeaconRequest::ISSUED)->count(),
                'accepted' => SurveyBeaconRequest::where('status', SurveyBeaconRequest::ACCEPTED)->count(),
                'pending'  => SurveyorPortalAccount::where('status', SurveyorPortalAccount::PENDING)->count(),
            ],
        ]);
    }

    public function issue(Request $r, SurveyBeaconRequest $request)
    {
        $this->authorizeSg();
        $data = $r->validate([
            'issue_date'     => 'required|date|before_or_equal:today',
            'letters'        => 'nullable|string|max:3',
            'start'          => 'nullable|integer|min:1|max:9999',
            'company_choice' => 'nullable|string|max:30',
            'company_other'  => 'nullable|string|max:255',
        ], ['issue_date.required' => 'Enter the issue date.']);

        $done = $this->requests->issue($request, $data);

        return redirect()->route('survey-module.beacon-requests.index')->with('success', "{$done->request_no} issued: {$done->issued_numbers}.");
    }

    public function reject(Request $r, SurveyBeaconRequest $request)
    {
        $this->authorizeSg();
        $data = $r->validate(['review_note' => 'required|string|max:1000'], ['review_note.required' => 'Give the reason.']);
        $done = $this->requests->reject($request, trim($data['review_note']));

        return redirect()->route('survey-module.beacon-requests.index')->with('success', "{$done->request_no} was not issued.");
    }

    public function slip(SurveyBeaconRequest $request)
    {
        $this->authorizeSg();
        abort_unless(in_array($request->status, [SurveyBeaconRequest::ISSUED, SurveyBeaconRequest::ACCEPTED], true), 404);

        return view('surveyor_portal.requests.slip', ['request' => $request->load('lines.numbers')]);
    }

    /** The passport photograph a surveyor gave at registration, for the SG's review. Private disk only. */
    public function passport(SurveyorPortalAccount $account)
    {
        $this->authorizeSg();
        $path = $account->passport_path;
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    public function approveAccount(SurveyorPortalAccount $account)
    {
        $this->authorizeSg();
        $done = $this->accounts->approve($account);

        return redirect()->route('survey-module.beacon-requests.index', ['tab' => 'registrations'])
            ->with('success', "{$done->holder_name} approved. Surveyor ID {$done->surveyor_id_no}.");
    }

    public function rejectAccount(Request $r, SurveyorPortalAccount $account)
    {
        $this->authorizeSg();
        $data = $r->validate(['review_note' => 'required|string|max:1000'], ['review_note.required' => 'Give the reason.']);
        $done = $this->accounts->reject($account, trim($data['review_note']));

        return redirect()->route('survey-module.beacon-requests.index', ['tab' => 'registrations'])->with('success', "{$done->holder_name}'s registration was not approved.");
    }

    /** The Requests tab's filters and order, shared with Export Records. */
    private function requestsQuery(Request $r)
    {
        $tab = $r->query('tab') === 'registrations' ? 'registrations' : 'requests';
        $term = trim((string) $r->query('q', ''));
        $status = (string) $r->query('status', '');

        return SurveyBeaconRequest::query()->with('lines:id,survey_beacon_request_id')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('request_no', 'like', "%$term%")->orWhere('company_name', 'like', "%$term%")
                ->orWhere('surveyor_name', 'like', "%$term%")->orWhere('job_name', 'like', "%$term%")->orWhere('issued_numbers', 'like', "%$term%")))
            ->when($tab === 'requests' && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'Submitted' THEN 0 ELSE 1 END")->orderByDesc('id');
    }

    /** The Registrations tab's filters and order, shared with Export Records. */
    private function accountsQuery(Request $r)
    {
        $tab = $r->query('tab') === 'registrations' ? 'registrations' : 'requests';
        $term = trim((string) $r->query('q', ''));
        $status = (string) $r->query('status', '');

        return SurveyorPortalAccount::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('company_name', 'like', "%$term%")
                ->orWhere('registration_id', 'like', "%$term%")->orWhere('surveyor_id_no', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->when($tab === 'registrations' && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('id');
    }

    /** Map Requests: what surveyors asked for on the portal. The Production Unit works them; this list follows them. */
    private function mapsQuery(Request $r)
    {
        $term = trim((string) $r->query('q', ''));
        $status = $r->query('tab') === 'maps' ? (string) $r->query('status', '') : '';

        return SdiMapRequest::query()->with(['mapType:id,name', 'portalAccount:id,surveyor_type,company_name,name,surveyor_id_no'])
            ->whereNotNull('surveyor_portal_account_id')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term) {
                foreach (['request_ref', 'map_name', 'map_reference', 'requester_other', 'contact_name', 'contact_phone', 'production_officer'] as $col) {
                    $w->orWhere($col, 'like', "%$term%");
                }
            }))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status IN ('pending', 'in_progress') THEN 0 ELSE 1 END")->orderByDesc('id');
    }

    public function export(Request $r)
    {
        $this->authorizeSg();

        if ($r->query('tab') === 'maps') {
            return $this->exportRecords($r, fn () => $this->mapsQuery($r), [
                'request_no' => 'Request No.', 'date' => 'Date', 'type' => 'Type', 'company' => 'Company', 'surveyor' => "Surveyor's Name", 'phone' => 'Phone', 'map' => 'Map', 'map_type' => 'Map Type',
                'reference' => 'Map Reference', 'formats' => 'Formats', 'officer' => 'Officer', 'status' => 'Status',
            ], fn ($m) => [
                'request_no' => $m->request_ref, 'date' => $this->exportDate($m->received_date, 'd/m/Y'), 'type' => $m->portalAccount?->type_label, 'company' => $m->portalAccount?->company_name,
                'surveyor' => $m->contact_name, 'phone' => $m->contact_phone, 'map' => $m->map_name, 'map_type' => $m->mapType?->name, 'reference' => $m->map_reference,
                'formats' => $m->formatsLabel(), 'officer' => $m->production_officer, 'status' => $m->statusLabel(),
            ], 'received_date');
        }

        if ($r->query('tab') === 'registrations') {
            return $this->exportRecords($r, fn () => $this->accountsQuery($r), [
                'registration_id' => 'Registration ID', 'surveyor_id' => 'Surveyor ID', 'date' => 'Date', 'type' => 'Type', 'company' => 'Company', 'rc_no' => 'RC No.', 'name' => "Surveyor's Name",
                'surcon' => 'SURCON No.', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'status' => 'Status',
            ], fn ($a) => [
                'registration_id' => $a->registration_id, 'surveyor_id' => $a->surveyor_id_no, 'date' => $this->exportDate($a->created_at, 'd/m/Y'), 'type' => $a->type_label,
                'company' => $a->company_name, 'rc_no' => $a->company_rc_no, 'name' => $a->name, 'surcon' => $a->surcon_number, 'phone' => $a->phone, 'email' => $a->email, 'address' => $a->person_address,
                'status' => $a->status,
            ]);
        }

        return $this->exportRecords($r, fn () => $this->requestsQuery($r), [
            'request_no' => 'Request No.', 'date' => 'Date', 'from' => 'From', 'type' => 'Type', 'company' => 'Company', 'surveyor' => "Surveyor's Name", 'job' => 'Job', 'beacons' => 'Beacons',
            'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location', 'latitude' => 'Latitude', 'longitude' => 'Longitude', 'status' => 'Status', 'issued' => 'Issued Numbers',
        ], fn ($q) => [
            'request_no' => $q->request_no, 'date' => $this->exportDate($q->created_at, 'd/m/Y'), 'from' => $q->source_label, 'type' => $q->type_label, 'company' => $q->company_name,
            'surveyor' => $q->surveyor_name, 'job' => $q->job_name, 'beacons' => $q->quantity, 'plot_no' => $q->plot_no, 'district' => $q->district,
            'lga' => $q->lga ? mb_strtoupper($q->lga) : null, 'location' => $q->location, 'latitude' => $q->latitude, 'longitude' => $q->longitude, 'status' => $q->status, 'issued' => $q->issued_numbers,
        ]);
    }
}
