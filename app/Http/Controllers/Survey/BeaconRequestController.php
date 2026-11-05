<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
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
        $tab = $r->query('tab') === 'registrations' ? 'registrations' : 'requests';
        $term = trim((string) $r->query('q', ''));
        $status = (string) $r->query('status', '');

        $requests = SurveyBeaconRequest::query()->with('lines:id,survey_beacon_request_id')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('request_no', 'like', "%$term%")->orWhere('company_name', 'like', "%$term%")
                ->orWhere('surveyor_name', 'like', "%$term%")->orWhere('job_name', 'like', "%$term%")->orWhere('issued_numbers', 'like', "%$term%")))
            ->when($tab === 'requests' && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'Submitted' THEN 0 ELSE 1 END")->orderByDesc('id')
            ->paginate(20, ['*'], 'page')->withQueryString();

        $accounts = SurveyorPortalAccount::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('company_name', 'like', "%$term%")
                ->orWhere('registration_id', 'like', "%$term%")->orWhere('surveyor_id_no', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")))
            ->when($tab === 'registrations' && $status !== '', fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderByDesc('id')
            ->paginate(20, ['*'], 'page')->withQueryString();

        return view('survey_module.beacon_requests.index', [
            'tab' => $tab, 'requests' => $requests, 'accounts' => $accounts, 'status' => $status,
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

    public function approveAccount(SurveyorPortalAccount $account)
    {
        $this->authorizeSg();
        $done = $this->accounts->approve($account);

        return redirect()->route('survey-module.beacon-requests.index', ['tab' => 'registrations'])
            ->with('success', "{$done->name} approved. Surveyor ID {$done->surveyor_id_no}.");
    }

    public function rejectAccount(Request $r, SurveyorPortalAccount $account)
    {
        $this->authorizeSg();
        $data = $r->validate(['review_note' => 'required|string|max:1000'], ['review_note.required' => 'Give the reason.']);
        $done = $this->accounts->reject($account, trim($data['review_note']));

        return redirect()->route('survey-module.beacon-requests.index', ['tab' => 'registrations'])->with('success', "{$done->name}'s registration was not approved.");
    }
}
