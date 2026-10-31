<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Models\Survey\SurveyItsObservation as Obs;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\Survey\SurveyDocuments;
use App\Services\SurveyItsAnchors;
use App\Support\SurveyGeometry as G;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Computation & Field Observations app ("Mobile App 2"; its own APK since 2026-10-30,
 * App\Support\SurveyMobileApps 'cfo'). Surveyors pick a survey job, an Instruction to
 * Survey (Survey or Cadastral) or an unissued Surveyor's Report (Private), and work it
 * on the phone, online or offline:
 *
 *   Readings      field observations (bearing, distance) → survey_its_observations, source mobile
 *   Coordinates   the List of Coordinates, with the phone's GPS fix as a field check
 *   Beacon Nos    each beacon against the Beacon Register (BeaconNumberCheck)
 *   Computation   closure of the readings, area and perimeter of the beacon list, and
 *                 each reading against the coordinates (as the desktop page)
 *   Sketch Plan   photographed or chosen and filed to EDMS (online only; SurveyDocuments)
 *
 * The desktop GKN / Examination pages read the same rows.
 */
class MobileObservationController extends Controller
{
    public function __construct(private SurveyItsAnchors $anchors)
    {
    }

    /** The open jobs, government and private, to pick one. */
    public function index()
    {
        $jobs = collect($this->anchors->all())->reject(fn ($a) => $a['locked'])
            ->merge(collect($this->anchors->privateOpen())->reject(fn ($a) => $a['locked']))
            ->values();

        return view('survey_module.mobile.observations', [
            'jobs'   => $jobs,
            'counts' => $this->counts(Obs::query()),
            'beacons' => $this->counts(Coord::query()),
        ]);
    }

    /** One job: readings, coordinates, beacon check, computation and Sketch Plan on one page (one page kept offline). */
    public function show(string $its)
    {
        $anchor = $this->anchorOr404($its);
        $readings = Obs::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get();
        $coords = Coord::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get()->values();

        // Each reading against the coordinates, where both stations are on the list (as the desktop).
        $byBeacon = $coords->keyBy(fn ($c) => mb_strtoupper(trim($c->beacon_id)));
        $checks = [];
        foreach ($readings as $o) {
            $from = $byBeacon->get(mb_strtoupper(trim($o->from_station)));
            $to = $byBeacon->get(mb_strtoupper(trim($o->to_station)));
            if ($from && $to) {
                $calc = G::leg([$from->northing, $from->easting], [$to->northing, $to->easting]);
                $checks[$o->id] = $calc + [
                    'bearing_diff'  => $calc['bearing'] !== null ? G::bearingDiffSeconds($o->observed_bearing, $calc['bearing']) : null,
                    'distance_diff' => $calc['distance'] !== null ? $o->observed_distance - $calc['distance'] : null,
                ];
            }
        }

        $beacons = app(BeaconNumberCheck::class)->forAnchor($anchor);
        [$type, $id] = SurveyDocuments::owner($anchor);

        return view('survey_module.mobile.observations_job', [
            'anchor'     => $anchor,
            'readings'   => $readings,
            'coords'     => $coords,
            'stations'   => $byBeacon->keys()->all(),
            'checks'     => $checks,
            'closed'     => $readings->count() >= 3
                && mb_strtoupper(trim($readings->first()->from_station)) === mb_strtoupper(trim($readings->last()->to_station)),
            'misclosure' => $readings->isNotEmpty() ? G::misclosure($readings->map(fn ($o) => [$o->observed_bearing, $o->observed_distance])->all()) : null,
            'shape'      => G::polygon($coords->map(fn ($c) => [(float) $c->northing, (float) $c->easting])->all()),
            'beacons'    => $beacons,
            'beaconSum'  => BeaconNumberCheck::summary($beacons),
            'sketches'   => app(SurveyDocuments::class)->for($type, $id, SurveyDocuments::KIND_SKETCH_PLAN),
        ]);
    }

    /** One reading. JSON for the offline sync; safe to resend (client_uuid). */
    public function store(Request $r, string $its)
    {
        $anchor = $this->writable($its);

        $data = $r->validate([
            'client_uuid'       => 'nullable|string|max:40',
            'from_station'      => 'required|string|max:50',
            'to_station'        => 'required|string|max:50|different:from_station',
            'bearing_d'         => 'required|integer|min:0|max:359',
            'bearing_m'         => 'nullable|integer|min:0|max:59',
            'bearing_s'         => 'nullable|numeric|min:0|lt:60',
            'observed_distance' => 'required|numeric|gt:0|max:1000000',
            'observed_on'       => 'nullable|date',
            'remarks'           => 'nullable|string|max:500',
        ], ['to_station.different' => 'A reading goes from one station to another.']);

        $uuid = $data['client_uuid'] ?? null;
        if ($uuid && ($done = Obs::where('client_uuid', $uuid)->first())) {
            return $this->answer($r, $its, $done, true);
        }

        $bearing = G::fromDms($data['bearing_d'], $data['bearing_m'] ?? 0, $data['bearing_s'] ?? 0);
        if ($bearing === null) throw ValidationException::withMessages(['bearing_d' => 'The bearing is out of range.']);

        try {
            $obs = Obs::create($anchor['keys'] + [
                'client_uuid'       => $uuid,
                'its_number'        => $anchor['prefill']['its_number'],
                'file_number'       => $anchor['prefill']['file_number'],
                'sort_order'        => (int) Obs::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->max('sort_order') + 1,
                'from_station'      => mb_strtoupper(trim($data['from_station'])),
                'to_station'        => mb_strtoupper(trim($data['to_station'])),
                'observed_bearing'  => $bearing,
                'observed_distance' => (float) $data['observed_distance'],
                'observed_on'       => $data['observed_on'] ?? now()->toDateString(),
                'observer'          => $r->user()?->name,
                'source'            => 'mobile',
                'remarks'           => $data['remarks'] ?? null,
            ]);
        } catch (QueryException $e) {
            if ($uuid && ($done = Obs::where('client_uuid', $uuid)->first())) return $this->answer($r, $its, $done, true);
            throw $e;
        }

        return $this->answer($r, $its, $obs, false);
    }

    /**
     * One beacon on the List of Coordinates, with the phone's GPS fix kept beside the
     * official northing / easting (a field check, never converted into them). Same rules
     * as the desktop list: beacon IDs unique per list whatever the case or spacing.
     */
    public function storeCoordinate(Request $r, string $its)
    {
        $anchor = $this->writable($its);

        $data = $r->validate([
            'client_uuid'    => 'nullable|string|max:40',
            'beacon_id'      => 'required|string|max:50',
            'northing'       => 'required|numeric|min:-99999999|max:99999999',
            'easting'        => 'required|numeric|min:-99999999|max:99999999',
            'elevation'      => 'nullable|numeric|min:-9999|max:99999',
            'remarks'        => 'nullable|string|max:500',
            'gps_latitude'   => 'nullable|numeric|between:-90,90',
            'gps_longitude'  => 'nullable|numeric|between:-180,180|required_with:gps_latitude',
            'gps_accuracy_m' => 'nullable|numeric|min:0|max:100000',
        ], [
            'beacon_id.required' => 'Give the beacon ID.',
            'northing.required'  => 'Give the northing.',
            'easting.required'   => 'Give the easting.',
        ]);

        $uuid = $data['client_uuid'] ?? null;
        if ($uuid && ($done = Coord::where('client_uuid', $uuid)->first())) {
            return $this->answerCoord($r, $its, $done, true);
        }

        $beacon = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $data['beacon_id'])));
        $list = Coord::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor));
        if ((clone $list)->get(['beacon_id'])->contains(fn ($c) => mb_strtoupper(trim($c->beacon_id)) === $beacon)) {
            throw ValidationException::withMessages(['beacon_id' => "Beacon {$beacon} is already on this list."]);
        }

        try {
            $coord = Coord::create($anchor['keys'] + [
                'client_uuid'    => $uuid,
                'its_number'     => $anchor['prefill']['its_number'],
                'file_number'    => $anchor['prefill']['file_number'],
                'sort_order'     => (int) (clone $list)->max('sort_order') + 1,
                'beacon_id'      => $beacon,
                'northing'       => $data['northing'],
                'easting'        => $data['easting'],
                'elevation'      => $data['elevation'] ?? null,
                'remarks'        => trim((string) ($data['remarks'] ?? '')) ?: null,
                'gps_latitude'   => $data['gps_latitude'] ?? null,
                'gps_longitude'  => $data['gps_longitude'] ?? null,
                'gps_accuracy_m' => $data['gps_accuracy_m'] ?? null,
                'source'         => 'mobile',
            ]);
        } catch (QueryException $e) {
            if ($uuid && ($done = Coord::where('client_uuid', $uuid)->first())) return $this->answerCoord($r, $its, $done, true);
            throw $e;
        }

        return $this->answerCoord($r, $its, $coord, false);
    }

    /* ------------------------------------------------------------------ */

    /** Rows per job, keyed like the anchors ("survey-12", "private-3"). */
    private function counts($query)
    {
        $aid = 'COALESCE(survey_its_id, cadastral_survey_job_id, survey_change_report_id)';

        return $query->selectRaw("{$aid} aid, its_source, COUNT(*) n")
            ->groupBy('its_source', DB::raw($aid))->get()
            ->mapWithKeys(fn ($x) => [$x->its_source . '-' . $x->aid => (int) $x->n]);
    }

    private function anchorOr404(string $its): array
    {
        $anchor = $this->anchors->resolve($its);
        abort_unless($anchor, 404);

        return $anchor;
    }

    private function writable(string $its): array
    {
        $anchor = $this->anchorOr404($its);
        if ($anchor['locked']) {
            throw ValidationException::withMessages(['its' => "{$anchor['prefill']['its_number']} is closed; nothing more can be added."]);
        }

        return $anchor;
    }

    private function answer(Request $r, string $its, Obs $obs, bool $duplicate)
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $obs->id, 'duplicate' => $duplicate]);
        }

        return redirect()->route('survey-module.mobile.observations.show', $its)
            ->with('success', "Reading {$obs->from_station} → {$obs->to_station} saved.");
    }

    private function answerCoord(Request $r, string $its, Coord $coord, bool $duplicate)
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $coord->id, 'duplicate' => $duplicate]);
        }

        return redirect()->to(route('survey-module.mobile.observations.show', $its) . '#coords')
            ->with('success', "Beacon {$coord->beacon_id} saved.");
    }
}
