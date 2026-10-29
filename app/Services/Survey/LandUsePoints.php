<?php

namespace App\Services\Survey;

use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Land use from the file indexing records, for the SDI Land Use Map request
 * (user, 2026-10-29: "take advantage of the file indexing record, that has land
 * use and coordinates").
 *
 * file_indexings.latitude/longitude are text and mark the locality, not the
 * plot: 130,442 indexed files sit on only 4,284 distinct points (21,085 on one
 * Kano city point), measured 2026-10-29. So files are grouped per point and
 * shown as a bubble sized by count, with the land-use mix, never as parcels.
 *
 * Land use goes through PRS's LandUseNormalizer, with two local pre-maps it
 * lacks (GOVERNMENT; RESDENTIAL / REAIDENTIAL / AGARICULTURAL typos), kept here
 * so PRS reports do not change.
 */
class LandUsePoints
{
    public const CACHE_KEY = 'survey.sdi.land-use-points.v1';

    public const GOVERNMENT = 'Government';

    /** Display order; the first three carry a colour on the map, the rest fold to grey "Other". */
    public const ORDER = [
        LandUseNormalizer::RESIDENTIAL, LandUseNormalizer::COMMERCIAL, LandUseNormalizer::AGRICULTURE,
        LandUseNormalizer::INDUSTRIAL, self::GOVERNMENT, LandUseNormalizer::INSTITUTIONAL,
        LandUseNormalizer::MIXED, LandUseNormalizer::UNCATEGORISED,
    ];

    /** Kano State with a margin; points outside are mis-keyed and left off. */
    private const BOX = ['lat' => [10.0, 13.5], 'lng' => [7.0, 10.5]];

    private const PRE_MAP = [
        'GOVERNMENT'    => self::GOVERNMENT,
        'GOVT'          => self::GOVERNMENT,
        'RESDENTIAL'    => LandUseNormalizer::RESIDENTIAL,
        'REAIDENTIAL'   => LandUseNormalizer::RESIDENTIAL,
        'AGARICULTURAL' => LandUseNormalizer::AGRICULTURE,
    ];

    public function __construct(private LandUseNormalizer $normalizer)
    {
    }

    /**
     * [lat, lng, total, {use: n}, "District, LGA"] per point, plus totals.
     * Cached an hour: the full grouping scans file_indexings (~1.5 s).
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => $this->build());
    }

    public function landUse(?string $raw): string
    {
        $v = strtoupper(trim((string) $raw));

        return self::PRE_MAP[$v] ?? $this->normalizer->normalize($raw);
    }

    private function build(): array
    {
        $rows = DB::connection('sqlsrv')->select("
            SELECT latitude, longitude, land_use_type, COUNT(*) AS n, MAX(district) AS district, MAX(lga) AS lga
            FROM file_indexings
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND latitude <> '' AND longitude <> ''
              AND (is_deleted IS NULL OR is_deleted = 0)
            GROUP BY latitude, longitude, land_use_type");

        $points = [];
        $totals = array_fill_keys(self::ORDER, 0);
        $skipped = 0;

        foreach ($rows as $r) {
            $lat = is_numeric($r->latitude) ? (float) $r->latitude : null;
            $lng = is_numeric($r->longitude) ? (float) $r->longitude : null;
            if ($lat === null || $lng === null
                || $lat < self::BOX['lat'][0] || $lat > self::BOX['lat'][1]
                || $lng < self::BOX['lng'][0] || $lng > self::BOX['lng'][1]) {
                $skipped += (int) $r->n;
                continue;
            }

            $key = round($lat, 6) . ',' . round($lng, 6);
            $use = $this->landUse($r->land_use_type);
            $n = (int) $r->n;

            if (! isset($points[$key])) {
                $place = collect([trim((string) $r->district), trim((string) $r->lga)])->filter()->implode(', ');
                $points[$key] = [round($lat, 6), round($lng, 6), 0, [], $place];
            }
            $points[$key][2] += $n;
            $points[$key][3][$use] = ($points[$key][3][$use] ?? 0) + $n;
            $totals[$use] += $n;
        }

        return [
            'order'     => self::ORDER,
            'points'    => array_values($points),
            'totals'    => $totals,
            'files'     => array_sum($totals),
            'skipped'   => $skipped,
            'built_at'  => now()->toIso8601String(),
        ];
    }
}
