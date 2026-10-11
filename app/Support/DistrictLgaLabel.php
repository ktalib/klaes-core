<?php

namespace App\Support;

/**
 * "DISTRICT, LGA" for listing tables, upper-cased so mixed-case rows read alike.
 *
 * Location is district + LGA and never the plot number. Older rows often hold only
 * a free-text location ("11, MAKAFIN DALA, Dala", "Plot PIECE OF LAND, TIGA VILL,
 * Bebeji, Kano"), so a missing part is read from it: house/plot numbers, "piece of
 * land" and the state are dropped, the LGA is the last part left and the district
 * the one before it. Display only; nothing is written back.
 */
class DistrictLgaLabel
{
    public static function for($district, $lga, $location = null): string
    {
        $district = self::norm($district);
        $lga = self::norm($lga);

        if (($district === '' || $lga === '') && trim((string) $location) !== '') {
            $parts = collect(explode(',', (string) $location))
                ->map(fn ($p) => self::norm($p))
                ->reject(fn ($p) => $p === ''
                    || preg_match('/^(PLOT\b|NO\.?\s|\d)|PIECE OF LAND/', $p)
                    || in_array($p, ['KANO', 'KANO STATE'], true))
                ->values();

            if ($lga === '') {
                $lga = (string) $parts->pop();
            } else {
                $parts = $parts->reject(fn ($p) => $p === $lga)->values();
            }

            if ($district === '') {
                $district = (string) $parts->last();
            }
        }

        return collect([$district, $lga])->filter()->unique()->implode(', ');
    }

    private static function norm($value): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $value)));
    }
}
