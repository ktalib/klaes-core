<?php

namespace App\Support;

/**
 * Reads the land use out of a registry file number.
 *
 * A file number is not always "<land use>-<year>-<serial>". Some carry a prefix
 * first, and taking the first segment blindly reports the prefix as the land use:
 *
 *   CON-AG-1995-15    land use AG   (CON is a prefix)
 *   ST-RES-2025-0001  land use RES
 *   RES-1994-762      land use RES
 *
 * That mistake is easy to make in several places at once, so the rule lives here.
 */
class FileNumberLandUse
{
    /** Segments that sit BEFORE the land use rather than being one. */
    public const PREFIXES = ['CON', 'ST', 'SLTR', 'KN'];

    /**
     * KANGIS registry prefixes. A KANGIS number carries no land use at all —
     * "MLKN 3235" is an identity, not a purpose — and it does not split on "-",
     * so the generic parser would hand the whole number back as the land-use code
     * and a new file number would be built from it.
     */
    public const KANGIS_PREFIXES = ['KNML', 'MLKN', 'KNGP', 'MLKNGP'];

    /** True for a KANGIS number in any of its written forms: "MLKN 3235", "MLKN3235". */
    public static function isKangisNumber(?string $fileNo): bool
    {
        $first = strtoupper(trim(explode(',', (string) $fileNo)[0]));

        if ($first === '') {
            return false;
        }

        // Longest prefix first, so MLKNGP is not swallowed by MLKN.
        $prefixes = self::KANGIS_PREFIXES;
        usort($prefixes, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($prefixes as $prefix) {
            if (!str_starts_with($first, $prefix)) {
                continue;
            }

            // What follows the prefix must be the serial and nothing else, or
            // "KNMLX-RES-2020-1" would be read as a KANGIS number.
            $rest = trim(substr($first, strlen($prefix)));

            if ($rest !== '' && ctype_digit($rest)) {
                return true;
            }
        }

        return false;
    }

    /** Codes as they appear in file numbers, mapped to what officers call them. */
    public const LABELS = [
        'RES'   => 'Residential',
        'COM'   => 'Commercial',
        'IND'   => 'Industrial',
        'AGR'   => 'Agricultural',
        'AG'    => 'Agricultural',
        'AGRIC' => 'Agricultural',
        'MIX'   => 'Mixed Use',
        'MIXED' => 'Mixed Use',
    ];

    /**
     * The land-use code, or '' when the value carries none — a duplex holding number
     * (DPX-2026-0007-H01) is not a file number and has no land use.
     */
    public static function codeFor(?string $fileNo): string
    {
        $first = strtoupper(trim(explode(',', (string) $fileNo)[0]));

        // A duplex holding number is not a file number, and a KANGIS number is an
        // identity rather than a purpose — neither carries a land use. Both must
        // report '' rather than handing back their own text as a code.
        if ($first === '' || str_starts_with($first, 'DPX-') || self::isKangisNumber($first)) {
            return '';
        }

        $parts = array_values(array_filter(explode('-', $first), fn ($p) => $p !== ''));

        if (!$parts) {
            return '';
        }

        if (in_array($parts[0], self::PREFIXES, true) && isset($parts[1]) && !is_numeric($parts[1])) {
            return $parts[1];
        }

        return is_numeric($parts[0]) ? '' : $parts[0];
    }

    /** "Agricultural (AG)" — the bare code means little to most readers. */
    public static function labelFor(?string $fileNo): string
    {
        $code = self::codeFor($fileNo);

        if ($code === '') {
            return '';
        }

        return isset(self::LABELS[$code]) ? self::LABELS[$code] . " ({$code})" : $code;
    }

    /**
     * The prefix a NEW file number for this parcel must keep.
     *
     * A Change of Purpose on CON-AG-1995-15 produces CON-COM-…, not COM-… — the
     * container prefix belongs to the parcel, not to the purpose.
     */
    public static function prefixFor(?string $fileNo): string
    {
        $first  = strtoupper(trim(explode(',', (string) $fileNo)[0]));
        $parts  = array_values(array_filter(explode('-', $first), fn ($p) => $p !== ''));

        return ($parts && in_array($parts[0], self::PREFIXES, true)) ? $parts[0] : '';
    }
}
