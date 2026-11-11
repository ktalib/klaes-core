<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Survey\LookupController;
use App\Models\Survey\SurveyBeaconRequest as BeaconRequest;
use App\Models\Survey\SurveyBeaconNumber;
use App\Models\Survey\SurveyorPortalNotification as Note;
use App\Services\Survey\BeaconRequests;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\PortalSurveyorReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** A signed-in surveyor's pages: flat, as the recording asks. Every request shown is the surveyor's own. */
class PortalController extends Controller
{
    public function __construct(private BeaconRequests $requests)
    {
    }

    private function me()
    {
        return Auth::guard('surveyor')->user();
    }

    private function mine(BeaconRequest $request): BeaconRequest
    {
        abort_unless((int) $request->surveyor_portal_account_id === (int) $this->me()->id, 404);

        return $request;
    }

    public function dashboard()
    {
        // A company's login has its own dashboard (user, 2026-11-10).
        if ($this->me()->isCompany()) {
            return app(CompanyController::class)->dashboard();
        }

        $base = fn () => BeaconRequest::where('surveyor_portal_account_id', $this->me()->id);

        return view('surveyor_portal.dashboard', [
            'stats' => [
                'submitted' => $base()->where('status', BeaconRequest::SUBMITTED)->count(),
                'ready'     => $base()->where('status', BeaconRequest::ISSUED)->count(),
                'accepted'  => $base()->where('status', BeaconRequest::ACCEPTED)->count(),
            ],
            'latest' => $base()->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    /**
     * Beacon Request (user, 2026-11-09: sidebar items, not tabs). Like the PHS token balance, a new request
     * waits until every beacon already issued is used, i.e. is on a Surveyor's Report.
     */
    public function create()
    {
        return view('surveyor_portal.requests.form', [
            'lgas' => CompensationListReader::LGAS,
            'balance' => app(PortalSurveyorReports::class)->balance($this->me()),
            'unused' => app(PortalSurveyorReports::class)->unusedBeacons($this->me()),
            'jobs' => $this->requests->jobChoices($this->me()),
        ]);
    }

    /** Beacon Register: the beacons this surveyor has used, with their coordinates and report. */
    public function beaconRegister(Request $r)
    {
        $search = mb_strtoupper(trim((string) $r->query('q', '')));
        $rows = app(PortalSurveyorReports::class)->usedBeacons($this->me())
            ->filter(fn ($b) => $search === '' || str_contains(mb_strtoupper(implode(' ', [
                $b->beacon_no, $b->register?->request?->request_no, $b->portalReport?->report_ref, $b->location_details,
                $b->portalReport?->plot_no, $b->portalReport?->district, $b->portalReport?->lga,
            ])), $search))
            ->values();
        $page = max(1, (int) $r->query('page', 1));
        $paged = new \Illuminate\Pagination\LengthAwarePaginator($rows->forPage($page, 25)->values(), $rows->count(), 25, $page,
            ['path' => $r->url(), 'query' => $r->query()]);

        return view('surveyor_portal.beacon_register', [
            'rows' => $paged,
            'search' => $r->query('q', ''),
            'balance' => app(PortalSurveyorReports::class)->balance($this->me()),
        ]);
    }

    public function store(Request $r)
    {
        $balance = app(PortalSurveyorReports::class)->balance($this->me());
        if ($balance['left'] > 0) {
            return redirect()->route('surveyor-portal.requests.create')
                ->withErrors(['request' => "You still have {$balance['left']} of your {$balance['issued']} issued beacons to use. Use them all, and enter them on a Surveyor's Report, before requesting more."])
                ->withInput($r->except('payment_evidence'));
        }

        $data = $r->validate([
            'request_type' => 'required|in:single,bulk',
            'job_no'      => 'required|string|max:40',
            'job_name'    => 'nullable|string|max:255',
            'quantity'    => 'required|integer|min:1|max:' . BeaconRequests::MAX_QUANTITY,
            'plot_no'     => 'nullable|string|max:100',
            'district'    => ['required_if:request_type,single', 'nullable', 'string', 'max:255', Rule::in(array_merge(LookupController::options('districts'), ['Other']))],
            'district_other' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('district') === 'Other')],
            'lga'         => ['required_if:request_type,single', 'nullable', Rule::in(CompensationListReader::LGAS)],
            'latitude'    => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'   => 'nullable|numeric|between:-180,180|required_with:latitude',
            'remarks'     => 'nullable|string|max:2000',
            'amount_paid' => 'required|numeric|gt:0|decimal:0,2|max:9999999999999.99',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_evidence' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'quantity.required' => 'Enter the number of beacons.',
            'job_no.required' => 'Choose the Job No for this survey.',
            'request_type.required' => 'Choose whether this is a Single or Bulk request.',
            'amount_paid.required' => 'Enter the amount paid to SURCON.',
            'amount_paid.decimal' => 'Enter an amount with no more than two decimal places.',
            'payment_date.required' => 'Enter the date the payment was made.',
            'payment_date.before_or_equal' => 'The payment date cannot be in the future.',
            'district.in' => 'Choose a district from the list, or select Other.',
            'district_other.required' => 'Specify the district.',
            'district.required_if' => 'Choose the district for a Single request.',
            'lga.required_if' => 'Choose the LGA for a Single request.',
        ]);
        $data['layout_type'] = $this->me()->surveyor_type;
        // The Job No must be one given to this surveyor (Survey Update 5b, 2026-11-10).
        if (! array_key_exists($data['job_no'], $this->requests->jobChoices($this->me()))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['job_no' => 'Choose a Job No given to you.']);
        }

        if (($data['district'] ?? null) === 'Other') {
            $data['district'] = trim(preg_replace('/[ \t]+/', ' ', (string) $data['district_other']));
        }
        unset($data['district_other']);
        // A Bulk request has no map (user, 2026-11-09), so no pin is stored for one.
        if ($data['request_type'] === 'bulk') {
            $data['latitude'] = $data['longitude'] = null;
        }
        foreach (['job_name', 'plot_no', 'district', 'remarks'] as $k) {
            $data[$k] = trim(preg_replace('/[ \t]+/', ' ', (string) ($data[$k] ?? ''))) ?: null;
        }

        $paymentEvidence = $data['payment_evidence'];
        unset($data['payment_evidence']);
        $request = $this->requests->fromPortal($this->me(), $data, $paymentEvidence);

        return redirect()->route('surveyor-portal.requests.show', $request)->with('status', "Request {$request->request_no} has gone to the Surveyor General.");
    }

    public function index()
    {
        return view('surveyor_portal.requests.index', [
            'requests' => BeaconRequest::where('surveyor_portal_account_id', $this->me()->id)->orderByDesc('id')->paginate(20),
        ]);
    }

    public function show(BeaconRequest $request)
    {
        return view('surveyor_portal.requests.show', ['request' => $this->mine($request)->load('lines.numbers')]);
    }

    public function accept(BeaconRequest $request)
    {
        $this->requests->accept($this->mine($request));

        return redirect()->route('surveyor-portal.requests.show', $request)->with('status', 'Beacons accepted. You can print your slip.');
    }

    public function slip(BeaconRequest $request)
    {
        $this->mine($request);
        abort_unless(in_array($request->status, [BeaconRequest::ISSUED, BeaconRequest::ACCEPTED], true), 404);

        return view('surveyor_portal.requests.slip', ['request' => $request->load('lines.numbers')]);
    }

    public function notifications()
    {
        $account = $this->me();
        $notes = Note::visibleTo($account)->with('account:id,name')->orderByDesc('id')->paginate(20);
        Note::markReadFor($account);

        return view('surveyor_portal.notifications', ['notes' => $notes]);
    }
}
