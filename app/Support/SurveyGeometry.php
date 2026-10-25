<?php

namespace App\Support;

use App\Http\Controllers\Survey\LpknController;

/**
 * Plane-survey arithmetic for the Survey module's coordinate lists and field
 * observations. Grid bearings are clockwise from north, in decimal degrees;
 * distances in metres. LpknController::computeLeg stays the one place a
 * bearing is worked out from two coordinates.
 */
class SurveyGeometry
{
    /** Decimal degrees to D° M' S" (seconds to one decimal), e.g. 123°45'30.0". */
    public static function dms(?float $deg): ?string
    {
        if ($deg === null) return null;
        $deg = fmod(fmod($deg, 360) + 360, 360);
        $d = (int) floor($deg);
        $mFloat = ($deg - $d) * 60;
        $m = (int) floor($mFloat);
        $s = round(($mFloat - $m) * 60, 1);
        if ($s >= 60) { $s = 0; $m++; }
        if ($m >= 60) { $m = 0; $d = ($d + 1) % 360; }

        return sprintf("%d°%02d'%04.1f\"", $d, $m, $s);
    }

    /**
     * Degrees, minutes and seconds to decimal degrees. Null when any part is out of
     * range, so a mistyped reading is refused rather than silently wrapped.
     */
    public static function fromDms($d, $m, $s): ?float
    {
        if (! is_numeric($d) || ($m !== null && $m !== '' && ! is_numeric($m)) || ($s !== null && $s !== '' && ! is_numeric($s))) return null;
        $d = (float) $d; $m = (float) ($m ?: 0); $s = (float) ($s ?: 0);
        if ($d < 0 || $d >= 360 || $m < 0 || $m >= 60 || $s < 0 || $s >= 60) return null;

        return $d + $m / 60 + $s / 3600;
    }

    /** Smallest signed difference a - b, in seconds of arc (-648000 .. 648000). */
    public static function bearingDiffSeconds(float $a, float $b): float
    {
        $diff = fmod($a - $b + 540, 360) - 180;

        return $diff * 3600;
    }

    /**
     * Polygon area (m²) and perimeter (m) of a closed beacon list in order, by the
     * shoelace formula. Fewer than three beacons enclose nothing.
     *
     * @param array<int, array{0: float, 1: float}> $points [northing, easting]
     */
    public static function polygon(array $points): array
    {
        $n = count($points);
        if ($n < 3) return ['area' => null, 'perimeter' => null];

        $twice = 0.0; $perimeter = 0.0;
        for ($i = 0; $i < $n; $i++) {
            [$n1, $e1] = $points[$i];
            [$n2, $e2] = $points[($i + 1) % $n];
            $twice += $e1 * $n2 - $e2 * $n1;
            $perimeter += hypot($n2 - $n1, $e2 - $e1);
        }

        return ['area' => abs($twice) / 2, 'perimeter' => $perimeter];
    }

    /**
     * Linear misclosure of a run of observed legs (bearing, distance): the sum of
     * the latitudes and departures. A closed traverse should sum to zero; the
     * precision is total distance / misclosure (e.g. 1 : 12 500).
     *
     * @param array<int, array{0: float, 1: float}> $legs [bearing deg, distance m]
     */
    public static function misclosure(array $legs): array
    {
        $dn = 0.0; $de = 0.0; $length = 0.0;
        foreach ($legs as [$bearing, $distance]) {
            $r = deg2rad($bearing);
            $dn += $distance * cos($r);
            $de += $distance * sin($r);
            $length += $distance;
        }
        $mis = hypot($dn, $de);

        return [
            'dn'        => $dn,
            'de'        => $de,
            'linear'    => $mis,
            'length'    => $length,
            'precision' => $mis > 0.0005 ? $length / $mis : null,   // null: closes within half a millimetre
        ];
    }

    /** Bearing and distance between two [northing, easting] points. */
    public static function leg(array $from, array $to): array
    {
        return LpknController::computeLeg($from[0], $from[1], $to[0], $to[1]);
    }
}
