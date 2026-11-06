<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyItsCoordinate as Coord;
use App\Models\Survey\SurveyItsObservation as Obs;
use App\Services\SurveyItsAnchors;
use App\Support\SurveyGeometry as G;

/**
 * The three sections of the Surveyors Report table (recording, 2026-11-06), GKN and Examination: what one
 * survey holds under Beacon Nos, List of Coordinates and Computation Field Observations, read from the rows
 * those pages and the Computation & Field Observations app write. Read only.
 */
class ReportRecords
{
    public function __construct(private BeaconNumberCheck $beacons, private SurveyDocuments $documents)
    {
    }

    /** @param array $anchor from SurveyItsAnchors */
    public function summary(array $anchor): array
    {
        $beacons = BeaconNumberCheck::summary($this->beacons->forAnchor($anchor));
        $coords = Coord::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get();
        $obs = Obs::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))->orderBy('sort_order')->orderBy('id')->get();
        $shape = G::polygon($coords->map(fn ($c) => [(float) $c->northing, (float) $c->easting])->all());
        [$type, $id] = SurveyDocuments::owner($anchor);

        return [
            'beacons'     => $beacons['total'],
            'in_register' => $beacons[BeaconNumberCheck::REGISTERED],
            'flagged'     => $beacons[BeaconNumberCheck::OTHER_SURVEYOR] + $beacons[BeaconNumberCheck::UNREGISTERED],
            'points'      => $coords->count(),
            'area'        => $shape['area'],
            'perimeter'   => $shape['perimeter'],
            'readings'    => $obs->count(),
            'closed'      => $obs->count() >= 3
                && mb_strtoupper(trim($obs->first()->from_station)) === mb_strtoupper(trim($obs->last()->to_station)),
            'misclosure'  => $obs->isNotEmpty() ? G::misclosure($obs->map(fn ($o) => [(float) $o->observed_bearing, (float) $o->observed_distance])->all())['linear'] : null,
            'sketch'      => $this->documents->for($type, $id, SurveyDocuments::KIND_SKETCH_PLAN)->isNotEmpty(),
        ];
    }
}
