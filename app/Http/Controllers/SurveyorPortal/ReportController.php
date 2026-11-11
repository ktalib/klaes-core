<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Survey\LookupController;
use App\Models\Survey\SurveyChangeReport as CR;
use App\Models\Survey\SurveySurveyorReport as SR;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\PortalSurveyorReports;
use App\Services\Survey\ReportRecords;
use App\Support\AddressBuilder;
use App\Support\SurveyGeometry as G;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Surveyor's Report on the KSSGOF portal (user, 2026-11-09): its own sidebar page under Requested Beacons,
 * a table like Survey's (Beacon Nos, List of Coordinates, Computation & Field Observations), the standard
 * report form and its print. See PortalSurveyorReports for how it reaches the system.
 */
class ReportController extends Controller
{
    public function __construct(private PortalSurveyorReports $reports)
    {
    }

    private function me()
    {
        return Auth::guard('surveyor')->user();
    }

    public function index()
    {
        $records = app(ReportRecords::class);
        // Beacon Nos counts the surveyor's own issued beacons; control points on the list are not beacons.
        $issued = $this->reports->issuedBeacons($this->me())->map(fn ($b) => $this->reports->key($b->beacon_no))->all();
        $rows = $this->reports->reports($this->me())->map(fn (CR $r) => [
            'report' => $r,
            'rec' => $records->summary($this->reports->anchor($r)),
            'beacons' => $this->reports->coordinates($r)->filter(fn ($c) => in_array($this->reports->key($c->beacon_id), $issued, true))->count(),
        ]);

        return view('surveyor_portal.reports.index', ['rows' => $rows, 'balance' => $this->reports->balance($this->me()), 'unused' => $this->reports->unusedBeacons($this->me())]);
    }

    public function create(Request $r)
    {
        $me = $this->me();
        $its = $this->reports->itsChoices($me);
        $key = $r->query('its');
        $prefill = $key && isset($its[$key]) ? $this->reports->itsPrefill($key, $its[$key]) : [];
        $report = new CR($prefill + ['report_date' => now()->toDateString(), 'surveyor_name' => $me->name]);
        if ($prefill) $report->file_title = $prefill['grantee_name'] ?? null;

        return view('surveyor_portal.reports.form', [
            'report' => $report,
            'its' => $its,
            'itsKey' => $prefill ? $key : null,
            'beacons' => $this->reports->issuedBeacons($me)->reject(fn ($b) => (bool) $b->used)->values(),
            'picked' => (array) $r->old('beacons', array_filter((array) $r->query('beacons'))),
        ] + $this->lists());
    }

    public function store(Request $r)
    {
        $me = $this->me();
        // Every report starts from an I to S issued to the surveyor (Survey Update 5d, 2026-11-10). Beacons picked
        // under Beacons to update come along as rows to fill on its List of Coordinates.
        $itsKey = trim((string) $r->input('its_key'));
        $beacons = array_values(array_filter((array) $r->input('beacons')));
        $fields = $this->validated($r);
        $its = $this->reports->itsChoices($me);
        if ($itsKey === '' || ! isset($its[$itsKey])) throw ValidationException::withMessages(['its_key' => 'Choose an I to S issued to you.']);
        // The I to S's file is the report's file.
        $pre = $this->reports->itsPrefill($itsKey, $its[$itsKey]);
        $fields['file_number'] = $pre['file_number'] ?? $fields['file_number'];
        $fields['its_number'] = $pre['its_number'] ?? null;
        $report = $this->reports->create($me, $fields, $beacons, $itsKey);

        return redirect()->route('surveyor-portal.reports.show', $report->id)
            ->with('status', "{$report->report_ref} saved. Enter the List of Coordinates and Field Observations, then submit it.");
    }

    public function show(int $report)
    {
        $me = $this->me();
        $r = $this->reports->mine($me, $report);
        $used = $this->reports->coordinates($r)->map(fn ($c) => $this->reports->key($c->beacon_id))->all();
        $issued = $this->reports->issuedBeacons($me);

        return view('surveyor_portal.reports.show', [
            'issuedKeys' => $issued->map(fn ($b) => $this->reports->key($b->beacon_no))->all(),
            'report' => $r,
            'editable' => $this->reports->editable($r),
            'coords' => $this->reports->coordinates($r),
            'observations' => $this->reports->observations($r),
            'pending' => $this->reports->pendingBeacons($r),
            'rec' => app(ReportRecords::class)->summary($this->reports->anchor($r)),
            'beacons' => $issued->reject(fn ($b) => (bool) $b->used || in_array($this->reports->key($b->beacon_no), $used, true))->values(),
        ] + $this->lists());
    }

    public function update(Request $r, int $report)
    {
        $cr = $this->reports->mine($this->me(), $report);
        $fields = $this->validated($r, $cr);
        if ($cr->its_key) unset($fields['file_number']);
        $this->reports->update($cr, $fields);

        return back()->with('status', "{$cr->report_ref} saved.");
    }

    public function storeCoordinates(Request $r, int $report)
    {
        $cr = $this->reports->mine($this->me(), $report);
        $data = $r->validate([
            'lines' => 'required|array|min:1|max:200',
            'lines.*.beacon' => 'required|string|max:50',
            'lines.*.beacon_other' => 'nullable|string|max:50',
            'lines.*.northing' => 'required|numeric|min:-99999999|max:99999999',
            'lines.*.easting' => 'required|numeric|min:-99999999|max:99999999',
            'lines.*.elevation' => 'nullable|numeric|min:-9999|max:99999',
            'lines.*.remarks' => 'nullable|string|max:500',
        ], [
            'lines.required' => 'Add at least one beacon.',
            'lines.*.beacon.required' => 'Choose the beacon on each line.',
            'lines.*.northing.required' => 'Give the northing (Y) on each line.',
            'lines.*.easting.required' => 'Give the easting (X) on each line.',
        ]);
        $lines = [];
        foreach (array_values($data['lines']) as $n => $l) {
            $other = $l['beacon'] === 'other';
            $id = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $other ? (string) ($l['beacon_other'] ?? '') : $l['beacon'])));
            if ($id === '') throw ValidationException::withMessages(["lines.$n.beacon_other" => 'Specify the point.']);
            $lines[$n] = ['beacon_id' => $id, 'is_beacon' => ! $other, 'northing' => (float) $l['northing'], 'easting' => (float) $l['easting'],
                          'elevation' => isset($l['elevation']) && $l['elevation'] !== '' ? (float) $l['elevation'] : null, 'remarks' => $l['remarks'] ?? null];
        }
        $n = $this->reports->addCoordinates($this->me(), $cr, $lines);

        return back()->with('status', $n === 1 ? '1 coordinate saved.' : "{$n} coordinates saved.");
    }

    public function destroyCoordinate(int $report, int $coord)
    {
        $cr = $this->reports->mine($this->me(), $report);
        $id = $this->reports->removeCoordinate($this->me(), $cr, $coord);

        return back()->with('status', "{$id} removed from the List of Coordinates.");
    }

    public function storeObservations(Request $r, int $report)
    {
        $cr = $this->reports->mine($this->me(), $report);
        $data = $r->validate([
            'lines' => 'required|array|min:1|max:200',
            'lines.*.from_station' => 'required|string|max:50',
            'lines.*.to_station' => 'required|string|max:50|different:lines.*.from_station',
            'lines.*.bearing_d' => 'required|integer|min:0|max:359',
            'lines.*.bearing_m' => 'nullable|integer|min:0|max:59',
            'lines.*.bearing_s' => 'nullable|numeric|min:0|lt:60',
            'lines.*.observed_distance' => 'required|numeric|gt:0|max:1000000',
            'lines.*.remarks' => 'nullable|string|max:500',
            'observed_on' => 'nullable|date|before_or_equal:today',
            'observer' => 'nullable|string|max:255',
        ], [
            'lines.required' => 'Add at least one reading.',
            'lines.*.to_station.different' => 'A reading goes from one station to another.',
            'lines.*.bearing_d.required' => 'Give the observed bearing (degrees at least).',
        ]);
        $rows = [];
        foreach (array_values($data['lines']) as $n => $l) {
            $bearing = G::fromDms($l['bearing_d'], $l['bearing_m'] ?? 0, $l['bearing_s'] ?? 0);
            if ($bearing === null) throw ValidationException::withMessages(["lines.$n.bearing_d" => 'The bearing is out of range.']);
            $rows[] = [
                'from_station' => mb_strtoupper(trim($l['from_station'])), 'to_station' => mb_strtoupper(trim($l['to_station'])),
                'observed_bearing' => $bearing, 'observed_distance' => (float) $l['observed_distance'],
                'observed_on' => $data['observed_on'] ?? null, 'observer' => $data['observer'] ?? null, 'remarks' => $l['remarks'] ?? null,
            ];
        }
        $n = $this->reports->addObservations($cr, $rows);

        return back()->with('status', $n === 1 ? '1 reading saved.' : "{$n} readings saved.");
    }

    public function destroyObservation(int $report, int $obs)
    {
        $cr = $this->reports->mine($this->me(), $report);
        $this->reports->removeObservation($cr, $obs);

        return back()->with('status', 'Reading removed.');
    }

    public function submit(int $report)
    {
        $cr = $this->reports->mine($this->me(), $report);
        $this->reports->submit($cr);

        return back()->with('status', "{$cr->report_ref} submitted to the Office of the Surveyor General.");
    }

    public function print(int $report)
    {
        $cr = $this->reports->mine($this->me(), $report);

        return view('surveyor_portal.reports.print', [
            'report' => $cr,
            'coords' => $this->reports->coordinates($cr),
            'observations' => $this->reports->observations($cr),
            'rec' => app(ReportRecords::class)->summary($this->reports->anchor($cr)),
        ]);
    }

    private function lists(): array
    {
        return [
            'districts' => LookupController::options('districts'),
            'lgas' => CompensationListReader::LGAS,
            'transport' => SR::TRANSPORT,
            'fieldRecords' => SR::FIELD_RECORDS,
            'tpNumbers' => $this->tpNumbers(),
        ];
    }

    /**
     * Layout plan No. = a TP No. from Public Layout Plans (user, 2026-11-09: "drop down with search, the same as
     * tp no"), as the Beacon Register's Plan No. A value saved before stays offered on its own report.
     */
    private function tpNumbers(?string $saved = null): \Illuminate\Support\Collection
    {
        $numbers = \App\Models\Survey\SurveyTpNumber::listed()->whereNotNull('tp_no')->where('tp_no', '<>', '')
            ->serialOrder()->get(['tp_no', 'layout_name'])
            ->map(fn ($row) => ['no' => trim((string) $row->tp_no), 'name' => trim((string) $row->layout_name)])
            ->filter(fn ($row) => $row['no'] !== '')
            ->unique(fn ($row) => mb_strtoupper(str_replace(' ', '', $row['no'])))
            ->values();
        $saved = trim((string) $saved);
        if ($saved !== '' && ! $numbers->contains(fn ($row) => $row['no'] === $saved)) {
            $numbers->prepend(['no' => $saved, 'name' => 'Previously saved']);
        }

        return $numbers;
    }

    /** The staff Surveyor's Report (Private) fields, checked the same way. */
    private function validated(Request $r, ?CR $existing = null): array
    {
        $yesNo = ['nullable', Rule::in(['1', '0'])];
        $data = $r->validate([
            'file_number' => 'nullable|string|max:100',
            'grantee_name' => 'nullable|string|max:500',
            'plot_no' => 'nullable|string|max:100',
            'district' => ['required', 'string', 'max:255', Rule::in(array_merge(LookupController::options('districts'), ['Other']))],
            'district_other' => 'nullable|string|max:255|required_if:district,Other',
            'lga' => ['required', Rule::in(CompensationListReader::LGAS)],
            'grid_origin' => 'nullable|string|max:255',
            'control_points' => 'nullable|string|max:500',
            'boundaries_pointed_by' => 'nullable|string|max:255',
            'boundaries_pointed_on' => 'nullable|date',
            'commenced_on' => 'nullable|date',
            'completed_on' => 'nullable|date|after_or_equal:commenced_on',
            'distance_km' => 'nullable|numeric|min:0|max:100000',
            'computed_on' => 'nullable|string|max:255',
            'delay_reason' => 'nullable|string|max:2000',
            'on_layout_plan' => $yesNo,
            'layout_plan_no' => ['nullable', 'required_if:on_layout_plan,1', 'string', 'max:100',
                Rule::in($this->tpNumbers($existing?->layout_plan_no)->pluck('no')->all())],
            'corners_demarcated' => $yesNo,
            'hard_by' => 'nullable|string|max:500',
            'encroachment' => $yesNo,
            'encroachment_details' => 'nullable|required_if:encroachment,1|string|max:2000',
            'developed' => $yesNo,
            'developed_with' => 'nullable|required_if:developed,1|string|max:500',
            'beacons_not_emplaced' => 'nullable|string|max:500',
            'beacons_reason' => 'nullable|required_with:beacons_not_emplaced|string|max:1000',
            'previous_kn' => 'nullable|string|max:100',
            'previous_kn_coincides' => $yesNo,
            'transport_by' => ['nullable', Rule::in(array_keys(SR::TRANSPORT))],
            'remarks' => 'nullable|string|max:4000',
            'field_jacket_checklist' => 'nullable|array',
            'field_jacket_checklist.*' => [Rule::in(array_keys(SR::FIELD_RECORDS))],
            'surveyor_name' => 'nullable|string|max:255',
            'report_date' => 'nullable|date',
        ], [
            'district_other.required_if' => 'Specify the district.',
            'completed_on.after_or_equal' => 'The survey cannot be completed before it commenced.',
            'layout_plan_no.required_if' => 'Choose the approved layout plan number.',
            'layout_plan_no.in' => 'Choose the layout plan number from the list.',
            'encroachment_details.required_if' => 'Describe the encroachment noticed.',
            'developed_with.required_if' => 'Say what the plot is developed with.',
            'beacons_reason.required_with' => 'Say why those beacons were not emplaced.',
        ]);

        $data['file_title'] = $data['grantee_name'] ?? null;
        unset($data['grantee_name']);
        $district = $data['district'] === 'Other'
            ? trim(preg_replace('/\s+/u', ' ', (string) $data['district_other']))
            : $data['district'];
        $data['district'] = mb_substr($district, 0, 255);
        $data['location'] = AddressBuilder::propertyLocation(['district' => $district, 'lga' => $data['lga'], 'state' => 'Kano']);
        unset($data['district_other']);
        foreach (['on_layout_plan', 'corners_demarcated', 'developed', 'previous_kn_coincides'] as $field) {
            $data[$field] = isset($data[$field]) && $data[$field] !== null ? (bool) $data[$field] : null;
        }
        $data['encroachment'] = (bool) ($data['encroachment'] ?? false);
        if (! $data['encroachment']) $data['encroachment_details'] = null;
        if ($data['on_layout_plan'] === false) $data['layout_plan_no'] = null;
        if ($data['developed'] === false) $data['developed_with'] = null;
        $data['field_jacket_checklist'] = array_values(array_intersect(array_keys(SR::FIELD_RECORDS), $data['field_jacket_checklist'] ?? []));

        return $data;
    }
}
