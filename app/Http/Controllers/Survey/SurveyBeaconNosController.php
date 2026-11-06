<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Services\Survey\BeaconNumberCheck;
use App\Services\SurveyItsAnchors;
use Illuminate\Http\Request;

/**
 * Beacon Nos (change list p.2, 2026-10-29): GKN > Surveyors Report and the
 * Surveyor's Report (Private) both list the survey's beacon numbers, checked
 * against the Beacon Register. The numbers come from the survey's List of
 * Coordinates, so there is nothing to enter twice.
 */
class SurveyBeaconNosController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    public function __construct(private SurveyItsAnchors $anchors, private BeaconNumberCheck $check)
    {
    }

    /** The list is a section of the Surveyors Report table now (recording, 2026-11-06). */
    public function index(Request $r)
    {
        return redirect()->route('survey-module.gkn.reports.index');
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

    /** The list with its filters, shared with Export Records. */
    private function overviewRows(Request $r): array
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

        return [$rows, $counts];
    }

    public function export(Request $r)
    {
        return $this->exportRecords($r, fn () => $this->overviewRows($r)[0], [
            'source' => 'Source', 'its_number' => 'I to S No.', 'file_number' => 'File No.', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA',
            'surveyor' => 'Surveyor', 'beacons' => 'Beacons',
        ], fn ($a) => [
            'source' => $a['source'] === 'survey' ? 'Survey' : 'Cadastral', 'its_number' => $a['prefill']['its_number'], 'file_number' => $a['prefill']['file_number'],
            'plot_no' => $a['prefill']['plot_no'], 'district' => $a['prefill']['district'], 'lga' => $a['prefill']['lga'], 'surveyor' => $a['prefill']['surveyor_name'],
            'beacons' => $a['beacons'],
        ], null);
    }
}
