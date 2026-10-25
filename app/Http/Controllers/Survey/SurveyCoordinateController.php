<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Models\Survey\SurveyItsObservation as Obs;
use App\Services\SurveyItsAnchors;
use App\Support\SurveyGeometry as G;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GKN > List of Coordinates and Computation Field Observations (user, 2026-10-25),
 * both attached to an Instruction to Survey (SurveyItsAnchors).
 *
 * Coordinates: the official beacon list, in order, with the computed legs, area
 * and perimeter. Observations: field readings entered from the field book (Mobile
 * App 2 will sync into the same table), checked against the coordinate list and
 * closed as a traverse.
 *
 * A list is read-only once its I to S is completed or cancelled (Cadastral: accepted).
 */
class SurveyCoordinateController extends Controller
{
    public function __construct(private SurveyItsAnchors $anchors)
    {
    }

    /* ----------------------------- coordinates ----------------------------- */

    public function index(Request $r)
    {
        return view('survey_module.gkn.coordinates.index', $this->overview($r, 'coordinates'));
    }

    public function show(string $its)
    {
        $anchor = $this->anchorOr404($its);
        $coords = $this->coordsFor($anchor);
        $points = $coords->map(fn ($c) => [$c->northing, $c->easting])->all();

        $legs = [];
        foreach ($coords as $i => $c) {
            $next = $coords[($i + 1) % max(1, $coords->count())] ?? null;
            $legs[$c->id] = $next && $coords->count() > 1
                ? G::leg([$c->northing, $c->easting], [$next->northing, $next->easting]) + ['to' => $next->beacon_id]
                : null;
        }

        return view('survey_module.gkn.coordinates.show', [
            'anchor' => $anchor,
            'coords' => $coords,
            'legs'   => $legs,
            'shape'  => G::polygon($points),
        ]);
    }

    public function storeCoordinate(Request $r, string $its)
    {
        $anchor = $this->writableAnchor($its);
        $data = $this->validatedCoordinate($r, $anchor);

        Coord::create($data + $anchor['keys'] + [
            'its_number'  => $anchor['prefill']['its_number'],
            'file_number' => $anchor['prefill']['file_number'],
            'sort_order'  => (int) $this->coordsFor($anchor)->max('sort_order') + 1,
        ]);

        return back()->with('success', "Beacon {$data['beacon_id']} added.");
    }

    public function updateCoordinate(Request $r, string $its, Coord $coord)
    {
        $anchor = $this->writableAnchor($its);
        $this->belongs($coord, $anchor);
        $coord->update($this->validatedCoordinate($r, $anchor, $coord));

        return back()->with('success', "Beacon {$coord->beacon_id} updated.");
    }

    public function destroyCoordinate(string $its, Coord $coord)
    {
        $anchor = $this->writableAnchor($its);
        $this->belongs($coord, $anchor);
        $coord->delete();

        return back()->with('success', "Beacon {$coord->beacon_id} removed.");
    }

    /** Move a beacon one place up or down; the list order is the boundary order. */
    public function moveCoordinate(Request $r, string $its, Coord $coord)
    {
        $anchor = $this->writableAnchor($its);
        $this->belongs($coord, $anchor);

        $list = $this->coordsFor($anchor)->values();
        $i = $list->search(fn ($c) => $c->id === $coord->id);
        $j = $r->input('dir') === 'up' ? $i - 1 : $i + 1;
        if ($i === false || $j < 0 || $j >= $list->count()) return back();

        DB::connection('sqlsrv')->transaction(function () use ($list, $i, $j) {
            $ids = $list->pluck('id')->all();
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $n => $id) Coord::whereKey($id)->update(['sort_order' => $n + 1]);
        });

        return back();
    }

    /* ---------------------------- observations ---------------------------- */

    public function observations(Request $r)
    {
        return view('survey_module.gkn.observations.index', $this->overview($r, 'observations'));
    }

    public function showObservations(string $its)
    {
        $anchor = $this->anchorOr404($its);
        $obs = Obs::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get();

        // Each reading checked against the official coordinates, where both stations are on the list.
        $byBeacon = $this->coordsFor($anchor)->keyBy(fn ($c) => mb_strtoupper(trim($c->beacon_id)));
        $checks = [];
        foreach ($obs as $o) {
            $from = $byBeacon->get(mb_strtoupper(trim($o->from_station)));
            $to = $byBeacon->get(mb_strtoupper(trim($o->to_station)));
            if ($from && $to) {
                $calc = G::leg([$from->northing, $from->easting], [$to->northing, $to->easting]);
                $checks[$o->id] = $calc + [
                    'bearing_diff'  => $calc['bearing'] !== null ? G::bearingDiffSeconds($o->observed_bearing, $calc['bearing']) : null,
                    'distance_diff' => $calc['distance'] !== null ? $o->observed_distance - $calc['distance'] : null,
                ];
            } else {
                $checks[$o->id] = null;
            }
        }

        // Closed when the run of readings returns to its first station.
        $closed = $obs->count() >= 3
            && mb_strtoupper(trim($obs->first()->from_station)) === mb_strtoupper(trim($obs->last()->to_station));

        return view('survey_module.gkn.observations.show', [
            'anchor'     => $anchor,
            'obs'        => $obs,
            'checks'     => $checks,
            'closed'     => $closed,
            'misclosure' => $obs->isNotEmpty() ? G::misclosure($obs->map(fn ($o) => [$o->observed_bearing, $o->observed_distance])->all()) : null,
            'stations'   => $byBeacon->keys()->all(),
        ]);
    }

    public function storeObservation(Request $r, string $its)
    {
        $anchor = $this->writableAnchor($its);
        $data = $this->validatedObservation($r);
        $last = Obs::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->max('sort_order');

        Obs::create($data + $anchor['keys'] + [
            'its_number'  => $anchor['prefill']['its_number'],
            'file_number' => $anchor['prefill']['file_number'],
            'sort_order'  => (int) $last + 1,
            'source'      => 'desktop',
        ]);

        return back()->with('success', "Reading {$data['from_station']} → {$data['to_station']} added.");
    }

    public function destroyObservation(string $its, Obs $obs)
    {
        $anchor = $this->writableAnchor($its);
        $this->belongs($obs, $anchor);
        $obs->delete();

        return back()->with('success', "Reading {$obs->from_station} → {$obs->to_station} removed.");
    }

    /* ------------------------------------------------------------------ */

    /** Every issued I to S with how many beacons and readings it has. */
    private function overview(Request $r, string $page): array
    {
        $coordCounts = Coord::query()->selectRaw("its_source, COALESCE(survey_its_id, cadastral_survey_job_id) AS aid, COUNT(*) AS n")
            ->groupBy('its_source', DB::raw('COALESCE(survey_its_id, cadastral_survey_job_id)'))->get()
            ->mapWithKeys(fn ($x) => [$x->its_source . '-' . $x->aid => (int) $x->n]);
        $obsCounts = Obs::query()->selectRaw("its_source, COALESCE(survey_its_id, cadastral_survey_job_id) AS aid, COUNT(*) AS n")
            ->groupBy('its_source', DB::raw('COALESCE(survey_its_id, cadastral_survey_job_id)'))->get()
            ->mapWithKeys(fn ($x) => [$x->its_source . '-' . $x->aid => (int) $x->n]);

        $rows = collect($this->anchors->all())->map(fn ($a) => $a + [
            'coords' => $coordCounts[$a['key']] ?? 0,
            'obs'    => $obsCounts[$a['key']] ?? 0,
        ]);

        if ($term = mb_strtolower(trim((string) $r->query('q', '')))) {
            $rows = $rows->filter(fn ($a) => str_contains(mb_strtolower($a['label'] . ' ' . ($a['prefill']['grantee_name'] ?? '')), $term));
        }
        if ($r->query('has') === 'with') $rows = $rows->filter(fn ($a) => $a[$page === 'coordinates' ? 'coords' : 'obs'] > 0);
        if ($r->query('has') === 'without') $rows = $rows->filter(fn ($a) => $a[$page === 'coordinates' ? 'coords' : 'obs'] === 0);

        $page_ = max(1, (int) $r->query('page', 1));

        return [
            'rows'  => new \Illuminate\Pagination\LengthAwarePaginator($rows->values()->forPage($page_, 20)->values(), $rows->count(), 20, $page_, ['path' => $r->url(), 'query' => $r->query()]),
            'totals' => ['its' => count($this->anchors->all()), 'coords' => Coord::count(), 'obs' => Obs::count()],
        ];
    }

    private function coordsFor(array $anchor)
    {
        return Coord::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get()->values();
    }

    private function anchorOr404(string $key): array
    {
        $a = $this->anchors->resolve($key);
        abort_unless($a, 404);

        return $a;
    }

    private function writableAnchor(string $key): array
    {
        $a = $this->anchorOr404($key);
        if ($a['locked']) {
            throw ValidationException::withMessages(['its' => "{$a['prefill']['its_number']} is closed; its survey records can no longer be changed."]);
        }

        return $a;
    }

    private function belongs($row, array $anchor): void
    {
        $ok = isset($anchor['keys']['survey_its_id'])
            ? (int) $row->survey_its_id === $anchor['keys']['survey_its_id']
            : (int) $row->cadastral_survey_job_id === $anchor['keys']['cadastral_survey_job_id'];
        abort_unless($ok, 404);
    }

    private function validatedCoordinate(Request $r, array $anchor, ?Coord $existing = null): array
    {
        $data = $r->validate([
            'beacon_id' => 'required|string|max:50',
            'northing'  => 'required|numeric|min:-99999999|max:99999999',
            'easting'   => 'required|numeric|min:-99999999|max:99999999',
            'elevation' => 'nullable|numeric|min:-9999|max:99999',
            'remarks'   => 'nullable|string|max:500',
        ], [
            'beacon_id.required' => 'Give the beacon ID.',
            'northing.required'  => 'Give the northing.',
            'easting.required'   => 'Give the easting.',
        ]);
        $data['beacon_id'] = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $data['beacon_id'])));

        // Beacon IDs are unique within one list, whatever the case or spacing typed.
        $dupe = $this->coordsFor($anchor)
            ->first(fn ($c) => mb_strtoupper(trim($c->beacon_id)) === $data['beacon_id'] && $c->id !== $existing?->id);
        if ($dupe) {
            throw ValidationException::withMessages(['beacon_id' => "Beacon {$data['beacon_id']} is already on this list."]);
        }

        return $data;
    }

    private function validatedObservation(Request $r): array
    {
        $data = $r->validate([
            'from_station' => 'required|string|max:50',
            'to_station'   => 'required|string|max:50|different:from_station',
            'bearing_d'    => 'required|integer|min:0|max:359',
            'bearing_m'    => 'nullable|integer|min:0|max:59',
            'bearing_s'    => 'nullable|numeric|min:0|lt:60',
            'observed_distance' => 'required|numeric|gt:0|max:1000000',
            'observed_on'  => 'nullable|date|before_or_equal:today',
            'observer'     => 'nullable|string|max:255',
            'remarks'      => 'nullable|string|max:500',
        ], [
            'to_station.different' => 'A reading goes from one station to another.',
            'bearing_d.required'   => 'Give the observed bearing (degrees at least).',
        ]);

        $bearing = G::fromDms($data['bearing_d'], $data['bearing_m'] ?? 0, $data['bearing_s'] ?? 0);
        if ($bearing === null) {
            throw ValidationException::withMessages(['bearing_d' => 'The bearing is out of range.']);
        }

        return [
            'from_station'      => mb_strtoupper(trim($data['from_station'])),
            'to_station'        => mb_strtoupper(trim($data['to_station'])),
            'observed_bearing'  => $bearing,
            'observed_distance' => (float) $data['observed_distance'],
            'observed_on'       => $data['observed_on'] ?? null,
            'observer'          => $data['observer'] ?? null,
            'remarks'           => $data['remarks'] ?? null,
        ];
    }
}
