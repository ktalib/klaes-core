<?php

namespace App\Support;

use App\Models\User;

/**
 * Server side of config('fileindexing.non_indexable_years').
 *
 * The File Number selector refuses these years in the browser; this enforces the
 * same rule on save so a direct POST or a stale script can't get round it. A Super
 * Admin may index such a file anyway by ticking the override box on the form,
 * which posts `override_year_block=1`.
 *
 * extractYear() must stay in step with extractFileNumberYear() in
 * public/js/global-fileno-modal.js.
 */
class FileIndexingYearPolicy
{
    public const OVERRIDE_FIELD = 'override_year_block';

    /** @return int[] */
    public static function blockedYears(): array
    {
        return array_map('intval', (array) config('fileindexing.non_indexable_years', []));
    }

    /**
     * The year a file number falls in, for the registry formats that carry one
     * (MLS, SIT, DCIV/LPCC, ST): a hyphen-delimited 4-digit segment that is neither
     * the first nor the last. KANGIS, New KANGIS, SLTR, Old MLS and GKN put a serial
     * there instead and never match.
     */
    public static function extractYear(?string $fileNumber): ?int
    {
        $base = strtoupper((string) $fileNumber);
        $base = preg_replace('/\s*\(\s*T\s*\)\s*$/', '', $base);
        $base = preg_replace('/\s+AND\s+EXTENSION\s*$/', '', $base);
        $parts = explode('-', trim($base));

        for ($i = 1; $i <= count($parts) - 2; $i++) {
            if (!preg_match('/^\d{4}$/', $parts[$i])) {
                continue;
            }
            $year = (int) $parts[$i];
            if ($year >= 1900 && $year <= 2100) {
                return $year;
            }
        }

        return null;
    }

    public static function blockedYearFor(?string $fileNumber): ?int
    {
        $year = self::extractYear($fileNumber);

        return ($year !== null && in_array($year, self::blockedYears(), true)) ? $year : null;
    }

    public static function canOverride(?User $user): bool
    {
        return $user !== null && $user->isSuperAdmin() && !empty(self::blockedYears());
    }

    /**
     * Refusal message for the first blocked number, or null when every number is
     * indexable (or a Super Admin ticked the override).
     *
     * @param  array<int, string|null>  $fileNumbers
     */
    public static function refusal(array $fileNumbers, bool $overrideRequested, ?User $user): ?string
    {
        if ($overrideRequested && self::canOverride($user)) {
            return null;
        }

        foreach ($fileNumbers as $fileNumber) {
            $year = self::blockedYearFor($fileNumber);
            if ($year !== null) {
                return "{$fileNumber} is a {$year} file. {$year} files cannot be indexed from the file indexing interface.";
            }
        }

        return null;
    }
}
