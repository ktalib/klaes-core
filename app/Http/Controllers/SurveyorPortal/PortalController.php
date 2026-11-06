<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeaconRequest as BeaconRequest;
use App\Models\Survey\SurveyorPortalNotification as Note;
use App\Services\Survey\BeaconRequests;
use App\Services\Survey\CompensationListReader;
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
        $base = fn () => BeaconRequest::where('surveyor_portal_account_id', $this->me()->id);

        return view('surveyor_portal.dashboard', [
            'stats' => [
                'submitted' => $base()->where('status', BeaconRequest::SUBMITTED)->count(),
                'ready'     => $base()->where('status', BeaconRequest::ISSUED)->count(),
                'accepted'  => $base()->where('status', BeaconRequest::ACCEPTED)->count(),
            ],
            'latest' => $base()->orderByDesc('id')->limit(5)->get(),
            'latestMaps' => \App\Models\Sdi\SdiMapRequest::with('mapType:id,name')->where('surveyor_portal_account_id', $this->me()->id)->orderByDesc('id')->limit(5)->get(),
        ]);
    }

    public function create()
    {
        return view('surveyor_portal.requests.form', ['lgas' => CompensationListReader::LGAS]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'layout_type' => ['required', Rule::in(['Government', 'Private'])],
            'job_name'    => 'required|string|max:255',
            'quantity'    => 'required|integer|min:1|max:' . BeaconRequests::MAX_QUANTITY,
            'plot_no'     => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:255',
            'lga'         => ['nullable', Rule::in(CompensationListReader::LGAS)],
            'latitude'    => 'nullable|numeric|between:-90,90|required_with:longitude',
            'longitude'   => 'nullable|numeric|between:-180,180|required_with:latitude',
            'remarks'     => 'nullable|string|max:2000',
        ], [
            'job_name.required' => 'Enter the name of the survey this is for.',
            'quantity.required' => 'Enter the number of beacons.',
        ]);

        foreach (['job_name', 'plot_no', 'district', 'remarks'] as $k) {
            $data[$k] = trim(preg_replace('/[ \t]+/', ' ', (string) ($data[$k] ?? ''))) ?: null;
        }

        $request = $this->requests->fromPortal($this->me(), $data);

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
        $notes = Note::where('surveyor_portal_account_id', $this->me()->id)->orderByDesc('id')->paginate(20);
        Note::where('surveyor_portal_account_id', $this->me()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('surveyor_portal.notifications', ['notes' => $notes]);
    }
}
