<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\SurveyItsAnchors;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Beacon Nos (change list p.2, 2026-10-29): GKN > Surveyors Report and the
 * Surveyor's Report (Private) both list the survey's beacon numbers, checked
 * against the Beacon Register. The numbers come from the survey's List of
 * Coordinates, so there is nothing to enter twice.
 */
class SurveyBeaconNosController extends Controller
{
    public function __construct(private SurveyItsAnchors $anchors, private BeaconNumberCheck $check)
    {
    }

    /** GKN overview: every issued I to S with its beacons and how many are in the register. */
    public function index(Request $r)
    {
        $counts = Coord::query()->whereIn('its_source', ['survey', 'cadastral'])
            ->selectRaw('its_source, survey_its_id, cadastral_survey_job_id, count(*) as n')
            ->groupBy('its_source', 'survey_its_id', 'cadastral_survey_job_id')->get()
            ->mapWithKeys(fn ($c) => [($c->its_source === 'survey' ? 'survey-' . $c->survey_its_id : 'cadastral-' . $c->cadastral_survey_job_id) => (int) $c->n]);

        $rows = collect($this->anchors->all())->map(fn ($a) => $a + ['beacons' => $counts[$a['key']] ?? 0]);
        if ($term = mb_strtolower(trim((string) $r->query('q', '')))) {
            $rows = $rows->filter(fn ($a) => str_contains(mb_strtolower($a['label']), $term));
        }
        if ($r->query('has') === 'with')    $rows = $rows->filter(fn ($a) => $a['beacons'] > 0);
        if ($r->query('has') === 'without') $rows = $rows->filter(fn ($a) => $a['beacons'] === 0);

        $page = max(1, (int) $r->query('page', 1));

        return view('survey_module.gkn.beacons.index', [
            'rows'   => new LengthAwarePaginator($rows->values()->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $r->url(), 'query' => $r->query()]),
            'totals' => ['its' => $rows->count(), 'beacons' => array_sum($counts->all())],
        ]);
    }

    public function show(string $its)
    {
        $anchor = $this->anchors->resolve($its);
        abort_unless($anchor, 404);
        $rows = $this->check->forAnchor($anchor);

        return view('survey_module.gkn.beacons.show', [
            'anchor'  => $anchor,
            'rows'    => $rows,
            'summary' => BeaconNumberCheck::summary($rows),
        ]);
    }
}
