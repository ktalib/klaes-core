<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralChart;
use Illuminate\Support\Collection;

/**
 * Area from a surveyor's traverse.
 *
 * WHAT THIS IS NOT. The concept note asks for "automatic area calculation from
 * digital charts". Nothing here reads an area out of a scanned chart image or a
 * CAD file — KLAES has no geometry type, no PostGIS, no DXF/shapefile ingest,
 * and mapping is Leaflet points only. What is honestly computable is the area
 * enclosed by the beacon coordinates the surveyor supplies, which is what the
 * chart's coordinate list holds.
 *
 * The maths is the shoelace (surveyor's) formula over eastings and northings,
 * which is what a traverse sheet is computed by hand.
 */
class AreaCalculator
{
    /** The units an officer may type an area in. Everything is stored in m². */
    public const UNITS = [
        'sqm'   => 'Square metres (m²)',
        'ha'    => 'Hectares',
        'acres' => 'Acres',
    ];

    /**
     * Square metres in one of each unit, from config so the browser's live
     * conversion and the server's stored figure use the same constants.
     *
     * @return array{sqm: float, ha: float, acres: float}
     */
    public function factors(): array
    {
        return [
            'sqm'   => 1.0,
            'ha'    => (float) config('cadastral_module.area.sqm_per_hectare', 10000.0),
            'acres' => (float) config('cadastral_module.area.sqm_per_acre', 4046.8564224),
        ];
    }

    /**
     * A typed area in square metres. An unknown unit reads as m², which is what
     * the form defaults to; the controller validates the unit before this runs.
     */
    public function toSqm(?float $value, string $unit = 'sqm'): ?float
    {
        if ($value === null) {
            return null;
        }

        return round($value * ($this->factors()[$unit] ?? 1.0), 3);
    }

    /**
     * Enclosed area in square metres, or null when there is no usable ring.
     *
     * @param iterable<array{easting: float|null, northing: float|null}|object> $points
     */
    public function areaSqm(iterable $points): ?float
    {
        $ring = $this->ring($points);

        if (count($ring) < 3) {
            return null;   // two points enclose nothing
        }

        $sum = 0.0;
        $n   = count($ring);

        for ($i = 0; $i < $n; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $n];   // wraps, closing the ring

            $sum += ($a[0] * $b[1]) - ($b[0] * $a[1]);
        }

        return round(abs($sum) / 2.0, 3);
    }

    /** The same, read off a chart's stored coordinates. */
    public function areaSqmForChart(CadastralChart $chart): ?float
    {
        return $this->areaSqm($chart->coordinates()->get());
    }

    /**
     * Perimeter in metres, for the sanity check on the description. Null when
     * the ring is unusable.
     */
    public function perimeterM(iterable $points): ?float
    {
        $ring = $this->ring($points);

        if (count($ring) < 3) {
            return null;
        }

        $total = 0.0;
        $n     = count($ring);

        for ($i = 0; $i < $n; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $n];

            $total += sqrt((($b[0] - $a[0]) ** 2) + (($b[1] - $a[1]) ** 2));
        }

        return round($total, 3);
    }

    /**
     * Every representation of one area, from the single stored square-metre
     * figure. Nothing here is persisted — four stored copies of one number drift.
     *
     * @return array{sqm: float|null, hectares: float|null, acres: float|null, plots: float|null}
     */
    public function convert(?float $sqm, ?float $plotSizeSqm = null): array
    {
        if ($sqm === null) {
            return ['sqm' => null, 'hectares' => null, 'acres' => null, 'plots' => null];
        }

        $cfg  = config('cadastral_module.area');
        $plot = $plotSizeSqm ?: (float) $cfg['plot_size_sqm'];

        return [
            'sqm'      => round($sqm, (int) $cfg['precision']),
            'hectares' => round($sqm / (float) $cfg['sqm_per_hectare'], 4),
            'acres'    => round($sqm / (float) $cfg['sqm_per_acre'], 4),
            'plots'    => $plot > 0 ? round($sqm / $plot, 2) : null,
        ];
    }

    /**
     * Misclosure of the traverse in metres: how far the last leg's bearing and
     * distance land from the first point. Only computable when every leg carries
     * both, which in practice is rare, so null is the normal answer.
     */
    public function misclosureM(iterable $points): ?float
    {
        $ring = $this->ring($points);

        if (count($ring) < 3) {
            return null;
        }

        $first = $ring[0];
        $last  = $ring[count($ring) - 1];

        return round(sqrt((($last[0] - $first[0]) ** 2) + (($last[1] - $first[1]) ** 2)), 3);
    }

    /**
     * Usable [easting, northing] pairs, in the order given.
     *
     * A point missing either ordinate is dropped rather than treated as zero —
     * a zero would drag the polygon to the origin and produce a plausible-looking
     * but wildly wrong area.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function ring(iterable $points): array
    {
        $ring = [];

        foreach ($points as $p) {
            $easting  = is_array($p) ? ($p['easting'] ?? null)  : ($p->easting ?? null);
            $northing = is_array($p) ? ($p['northing'] ?? null) : ($p->northing ?? null);

            if ($easting === null || $northing === null || $easting === '' || $northing === '') {
                continue;
            }

            $ring[] = [(float) $easting, (float) $northing];
        }

        // A ring keyed with an explicit closing point repeats the first one; the
        // wrap-around above already closes it, so the duplicate would add a
        // zero-area sliver and a spurious perimeter leg.
        $n = count($ring);
        if ($n > 1 && $ring[0] === $ring[$n - 1]) {
            array_pop($ring);
        }

        return $ring;
    }

    /** How many of a chart's points can actually be plotted. */
    public function plottableCount(Collection $coordinates): int
    {
        return $coordinates->filter(
            fn ($c) => $c->northing !== null && $c->easting !== null
        )->count();
    }
}
