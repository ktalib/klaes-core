<?php

namespace App\Services\Cadastral;

use App\Support\FileNumberLandUse;

/**
 * Reading a registry file number the way the Cadastral Department needs it.
 *
 * The concept note writes file numbers as "RES/--/--". That is notation: the
 * real ones in file_indexings use hyphens (RES-1981-1), and conversions carry a
 * prefix segment first (CON-RES-2025-14). Parsing lives here so the rule is
 * stated once.
 *
 * The land-use rule itself is NOT reimplemented — App\Support\FileNumberLandUse
 * already knows that CON and ST are prefixes rather than land uses, and this
 * wraps it.
 */
class FileNumberFormat
{
    /** "RES-1981-1" => ['RES', '1981', '1'] with empty segments dropped. */
    public static function segments(?string $fileNumber): array
    {
        $raw = strtoupper(trim((string) $fileNumber));

        if ($raw === '') {
            return [];
        }

        // Tolerate the slash form officers still write by hand.
        $raw = str_replace(['/', '\\'], '-', $raw);
        $raw = preg_replace('/\s+/', ' ', $raw);

        return array_values(array_filter(
            array_map('trim', explode('-', $raw)),
            fn ($s) => $s !== ''
        ));
    }

    /**
     * Direct or conversion.
     *
     * Conversion files are not charted (concept note 4.3a) — they go straight to
     * index-card commissioning — so this decides a real branch in the workflow,
     * not just a label.
     */
    public static function classify(?string $fileNumber): ?string
    {
        $segments = self::segments($fileNumber);

        if ($segments === []) {
            return null;
        }

        $conversion = app(CadastralSettings::class)->filePrefixes()['conversion'];

        return in_array($segments[0], $conversion, true) ? 'conversion' : 'direct';
    }

    public static function isConversion(?string $fileNumber): bool
    {
        return self::classify($fileNumber) === 'conversion';
    }

    /** The land-use code, skipping any leading prefix segment. */
    public static function landUseCode(?string $fileNumber): ?string
    {
        $segments = self::segments($fileNumber);

        if ($segments === []) {
            return null;
        }

        if (FileNumberLandUse::isKangisNumber($fileNumber)) {
            return null;   // "MLKN 3235" is an identity, not a purpose
        }

        $prefixes = FileNumberLandUse::PREFIXES;

        foreach ($segments as $segment) {
            if (in_array($segment, $prefixes, true)) {
                continue;
            }

            return ctype_digit($segment) ? null : $segment;
        }

        return null;
    }

    public static function landUseLabel(?string $fileNumber): ?string
    {
        $code = self::landUseCode($fileNumber);

        return $code ? (FileNumberLandUse::LABELS[$code] ?? $code) : null;
    }

    /**
     * True when the number is shaped like a registry file number at all.
     *
     * Deliberately permissive: this database holds 170,315 file numbers keyed in
     * over four decades, and a validator strict enough to be satisfying would
     * reject real records. It catches empty and obviously malformed input, and
     * leaves judgement to the officer.
     */
    public static function isPlausible(?string $fileNumber): bool
    {
        $segments = self::segments($fileNumber);

        if (count($segments) < 2) {
            return false;
        }

        // Something in there has to be a serial.
        foreach ($segments as $segment) {
            if (preg_match('/\d/', $segment)) {
                return true;
            }
        }

        return false;
    }

    /** Canonical hyphenated spelling, for comparing two hand-keyed numbers. */
    public static function normalise(?string $fileNumber): ?string
    {
        $segments = self::segments($fileNumber);

        return $segments === [] ? null : implode('-', $segments);
    }

    /** Human explanation of what the number says, for the reception screen. */
    public static function describe(?string $fileNumber): array
    {
        return [
            'normalised' => self::normalise($fileNumber),
            'class'      => self::classify($fileNumber),
            'land_use'   => self::landUseLabel($fileNumber),
            'plausible'  => self::isPlausible($fileNumber),
        ];
    }
}
