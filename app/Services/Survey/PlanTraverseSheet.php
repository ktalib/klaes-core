<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyPlanUpload as Plan;

/**
 * The traverse with the reference beacon details, as the checker receives a Digital Plotting (Kunle, 2026-11-17:
 * "either the format or the traverse with the reference Beacon details"). Made from what the plotting saved:
 * its traverse lines (bearing and distance), else the bearing and distance between its beacons in order.
 */
class PlanTraverseSheet
{
    public function csv(Plan $plan): string
    {
        $plan->loadMissing('referenceBeacons');
        $beacons = $plan->referenceBeacons->values();
        $pd = (array) $plan->plot_data;
        $ref = $beacons->first();

        $rows = [
            ['Survey Plan', $plan->upload_ref],
            ['Coordinate system', $plan->coord_system_label ?? $plan->coord_system],
            // Property order: Plot No., District, LGA, Location.
            ['Plot No.', $plan->plot_no], ['District', $plan->district], ['LGA', $plan->lga], ['Location', $plan->location],
            ['Drawn by', $plan->drawn_by],
            [],
            ['Reference beacon', 'Beacon No.', 'X (Easting)', 'Y (Northing)'],
            ['', $ref?->beacon_no, $ref?->coord_x, $ref?->coord_y],
            [],
            ['Line', 'From Beacon', 'From X', 'From Y', 'Bearing', 'Bearing (whole circle)', 'Distance (m)', 'To Beacon', 'To X', 'To Y'],
        ];

        foreach ($this->lines($plan) as $l) {
            $rows[] = [$l['line'], $l['from'], $l['from_x'], $l['from_y'], $l['bearing'], $l['bearing_wcb'], $l['distance'], $l['to'], $l['to_x'], $l['to_y']];
        }

        $rows[] = [];
        $rows[] = ['Closing error (m)', isset($pd['closing_error_m']) ? number_format((float) $pd['closing_error_m'], 3, '.', '') : ''];
        $rows[] = ['Area (m²)', isset($pd['area_m2']) ? number_format((float) $pd['area_m2'], 2, '.', '') : ''];
        $rows[] = [];
        $rows[] = ['Beacons in order', 'Beacon No.', 'X (Easting)', 'Y (Northing)'];
        foreach ($beacons as $i => $b) $rows[] = [$i + 1, $b->beacon_no, $b->coord_x, $b->coord_y];

        $out = "\xEF\xBB\xBF";   // Excel reads it as UTF-8 (the ° and ² signs)
        foreach ($rows as $r) {
            $out .= implode(',', array_map(fn ($v) => preg_match('/[",\r\n]/', (string) $v) ? '"' . str_replace('"', '""', (string) $v) . '"' : (string) $v, $r)) . "\r\n";
        }

        return $out;
    }

    /**
     * The traverse line by line (shared by the CSV, the plan page's Plotted Beacons tab and the checker's card): the
     * plotting's own lines (bearing and distance), else the line between each beacon and the next, closing on the first.
     *
     * @return array<int, array{line:int, from:string, from_x:string, from_y:string, bearing:string, bearing_wcb:string, distance:string, to:string, to_x:string, to_y:string}>
     */
    public function lines(Plan $plan): array
    {
        $plan->loadMissing('referenceBeacons');
        $beacons = $plan->referenceBeacons->values();
        $xy = [];
        foreach ($beacons as $b) $xy[mb_strtoupper((string) $b->beacon_no)] = [(float) $b->coord_x, (float) $b->coord_y];

        $lines = $plan->isDigital() ? array_values((array) (((array) $plan->plot_data)['lines'] ?? [])) : [];
        if (! $lines && $beacons->count() >= 2) {
            $list = $beacons->all();
            $n = count($list);
            for ($i = 0; $i < $n; $i++) {
                $a = $list[$i];
                $b = $list[($i + 1) % $n];
                if ($i === $n - 1 && $n < 3) break;
                $dx = (float) $b->coord_x - (float) $a->coord_x;
                $dy = (float) $b->coord_y - (float) $a->coord_y;
                $lines[] = ['from' => $a->beacon_no, 'to' => $b->beacon_no,
                    'azimuth' => fmod(rad2deg(atan2($dx, $dy)) + 360, 360), 'distance' => round(hypot($dx, $dy), 3)];
            }
        }

        $out = [];
        foreach ($lines as $i => $l) {
            $f = $xy[mb_strtoupper((string) ($l['from'] ?? ''))] ?? null;
            $t = $xy[mb_strtoupper((string) ($l['to'] ?? ''))] ?? null;
            $az = (float) ($l['azimuth'] ?? 0);
            $out[] = [
                'line' => $i + 1,
                'from' => (string) ($l['from'] ?? ''), 'from_x' => $f ? $this->n($f[0]) : '', 'from_y' => $f ? $this->n($f[1]) : '',
                'bearing' => $this->quadrant($az), 'bearing_wcb' => $this->dms($az),
                'distance' => number_format((float) ($l['distance'] ?? 0), 3, '.', ''),
                'to' => (string) ($l['to'] ?? ''), 'to_x' => $t ? $this->n($t[0]) : '', 'to_y' => $t ? $this->n($t[1]) : '',
            ];
        }

        return $out;
    }

    /**
     * A report's own field readings (survey_its_observations) in the shape of lines(), without coordinates: the
     * Readings pop-up on the Surveyors Report lists (user, 2026-11-19). observed_bearing is a whole-circle bearing.
     */
    public function readingLines(iterable $observations): array
    {
        $out = [];
        foreach ($observations as $i => $o) {
            $az = (float) $o->observed_bearing;
            $out[] = [
                'line' => count($out) + 1,
                'from' => (string) $o->from_station, 'to' => (string) $o->to_station,
                'bearing' => $this->quadrant($az), 'bearing_wcb' => $this->dms($az),
                'distance' => number_format((float) $o->observed_distance, 3, '.', ''),
            ];
        }

        return $out;
    }

    private function n(float $v): string
    {
        return number_format($v, 3, '.', '');
    }

    /** Whole-circle bearing as D° M′ S″. */
    private function dms(float $az): string
    {
        $s = (int) round($az * 3600) % (360 * 3600);

        return sprintf('%d° %02d′ %02d″', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60);
    }

    /** Quadrant bearing, e.g. N 84° 17′ 01″ W, as the plan writes it. */
    private function quadrant(float $az): string
    {
        $az = fmod($az + 360, 360);
        [$ns, $ew, $a] = match (true) {
            $az <= 90  => ['N', 'E', $az],
            $az <= 180 => ['S', 'E', 180 - $az],
            $az <= 270 => ['S', 'W', $az - 180],
            default    => ['N', 'W', 360 - $az],
        };

        return $ns . ' ' . $this->dms($a) . ' ' . $ew;
    }
}
