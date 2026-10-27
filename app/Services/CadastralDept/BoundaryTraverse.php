<?php

namespace App\Services\CadastralDept;

/**
 * The boundary lines of a Plan Description (CAD 20): from each beacon, a distance in metres on a
 * bearing (degrees, minutes, seconds, clockwise from National North) to the next beacon, the last
 * line back to the start. Pure maths, no database.
 *
 * The plot does not have to close (change request 26-10-2026: "dimensions without closing in
 * case the shape is irregular"): the area is worked out from the corners the lines reach, and
 * the gap between the last corner and the start is reported as the misclosure, never refused.
 */
class BoundaryTraverse
{
    /** Decimal degrees from degrees, minutes, seconds. */
    public static function degrees(int|float $deg, int|float $min = 0, int|float $sec = 0): float
    {
        return (float) $deg + (float) $min / 60 + (float) $sec / 3600;
    }

    /** "71° 57'" or "71° 57' 30"" as the forms print it. */
    public static function format(?int $deg, ?int $min, int|float|null $sec): string
    {
        if ($deg === null) {
            return '';
        }
        $out = $deg . '° ' . str_pad((string) (int) $min, 2, '0', STR_PAD_LEFT) . "'";
        if ($sec !== null && (float) $sec > 0) {
            $s = (float) $sec;
            $out .= ' ' . (floor($s) == $s ? str_pad((string) (int) $s, 2, '0', STR_PAD_LEFT) : rtrim(rtrim(number_format($s, 2), '0'), '.')) . '"';
        }

        return $out;
    }

    /**
     * Corners reached by walking the lines from (0, 0), as [easting, northing] offsets in metres.
     *
     * @param  array<int, array{distance_m: ?float, bearing_deg: ?int, bearing_min: ?int, bearing_sec: int|float|null}>  $lines
     * @return array<int, array{0: float, 1: float}>|null  null when a line has no distance or bearing
     */
    public static function corners(array $lines): ?array
    {
        $e = 0.0;
        $n = 0.0;
        $points = [[0.0, 0.0]];
        foreach (array_values($lines) as $line) {
            if (($line['distance_m'] ?? null) === null || ($line['bearing_deg'] ?? null) === null) {
                return null;
            }
            $theta = deg2rad(self::degrees($line['bearing_deg'], $line['bearing_min'] ?? 0, $line['bearing_sec'] ?? 0));
            $e += (float) $line['distance_m'] * sin($theta);
            $n += (float) $line['distance_m'] * cos($theta);
            $points[] = [$e, $n];
        }

        return $points;
    }

    /**
     * Area (m²) enclosed by the lines, and how far the last corner lands from the start.
     *
     * @return array{area_sqm: ?float, misclosure_m: ?float, lines: int}
     */
    public static function measure(array $lines): array
    {
        $points = count($lines) >= 3 ? self::corners($lines) : null;
        if ($points === null) {
            return ['area_sqm' => null, 'misclosure_m' => null, 'lines' => count($lines)];
        }

        [$endE, $endN] = end($points);
        $misclosure = sqrt($endE ** 2 + $endN ** 2);

        // Every corner the walk reaches, the last one too: on a closed plot it sits on the start and
        // adds nothing; on an open one the shoelace closes the figure with a straight line back.
        $ring = $points;
        $sum = 0.0;
        $count = count($ring);
        for ($i = 0; $i < $count; $i++) {
            [$x1, $y1] = $ring[$i];
            [$x2, $y2] = $ring[($i + 1) % $count];
            $sum += $x1 * $y2 - $x2 * $y1;
        }

        return ['area_sqm' => round(abs($sum) / 2, 2), 'misclosure_m' => round($misclosure, 3), 'lines' => count($lines)];
    }
}
