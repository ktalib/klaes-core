<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyItsCoordinate;
use App\Models\Survey\SurveyItsObservation as Obs;
use App\Services\SurveyItsAnchors;
use App\Support\SurveyGeometry as G;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SCF Mobile > Field Observations ("Mobile App 2", user 2026-10-25): government surveyors
 * record their field readings against an Instruction to Survey, online or offline. The
 * readings land in survey_its_observations with source = mobile, where Computation Field
 * Observations on the desktop checks and closes them.
 */
class MobileObservationController extends Controller
{
    public function __construct(private SurveyItsAnchors $anchors)
    {
    }

    /** The issued, still-open instructions, to pick one. */
    public function index()
    {
        $jobs = collect($this->anchors->all())->reject(fn ($a) => $a['locked'])->values();
        $counts = Obs::query()->selectRaw("COALESCE(survey_its_id, cadastral_survey_job_id) aid, its_source, COUNT(*) n")
            ->groupBy('its_source', \Illuminate\Support\Facades\DB::raw('COALESCE(survey_its_id, cadastral_survey_job_id)'))->get()
            ->mapWithKeys(fn ($x) => [$x->its_source . '-' . $x->aid => (int) $x->n]);

        return view('survey_module.mobile.observations', ['jobs' => $jobs, 'counts' => $counts]);
    }

    /** One job: its readings, and the form to add one. */
    public function show(string $its)
    {
        $anchor = $this->anchors->resolve($its);
        abort_unless($anchor, 404);

        return view('survey_module.mobile.observations_job', [
            'anchor'   => $anchor,
            'readings' => Obs::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get(),
            'stations' => SurveyItsCoordinate::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->pluck('beacon_id')->all(),
        ]);
    }

    /** One reading. JSON for the offline sync; safe to resend (client_uuid). */
    public function store(Request $r, string $its)
    {
        $anchor = $this->anchors->resolve($its);
        abort_unless($anchor, 404);
        if ($anchor['locked']) {
            throw ValidationException::withMessages(['its' => "{$anchor['prefill']['its_number']} is closed; no more readings can be added."]);
        }

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

    private function answer(Request $r, string $its, Obs $obs, bool $duplicate)
    {
        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'id' => $obs->id, 'duplicate' => $duplicate]);
        }

        return redirect()->route('survey-module.mobile.observations.show', $its)
            ->with('success', "Reading {$obs->from_station} → {$obs->to_station} saved.");
    }
}
