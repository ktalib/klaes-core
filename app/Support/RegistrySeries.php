<?php

namespace App\Support;

use App\Models\FileIndexing;

/**
 * Does a file number belong in the registry picked for its scans?
 *
 * Scan Upload's guard (user, 2026-11-16: "fix the scan upload for the ST"). Same rules and
 * wording as BlindScanningController::registryMismatch(), which guards the Blind Scan page;
 * this one takes the canonical registry names Scan Upload offers.
 *   block  a dedicated-series number (ST-, SLTR-, DCIV-, SIT-, Survey, KANGIS) picked for another
 *          series registry: its scans go to its own registry.
 *   warn   a Lands number picked for a dedicated-series registry: some are kept there, so the
 *          user confirms.
 * Cadastral, Secret, Deeds and Physical Planning hold other registries' numbers on purpose and
 * are never checked; numbers with no known series pass.
 */
class RegistrySeries
{
    public const SERIES_REGISTRIES = [
        'Lands Registry' => 'LANDS',
        'SIT Registry' => 'SIT',
        'ST Registry' => 'ST',
        'SLTR Registry' => 'SLTR',
        'DCIV Registry' => 'DCIV',
        'Survey Registry' => 'GKN',
        'KANGIS Registry' => 'KANGIS',
    ];

    /** null when the number may go into $registry, else [level, expected_name, chosen_name, message]. */
    public static function mismatch(?string $fileNumber, ?string $registry): ?array
    {
        $fileNumber = trim((string) $fileNumber);
        $expectedName = FileIndexing::detectRegistryFromFileNumber($fileNumber);
        $chosenName = self::canonical($registry);
        if ($expectedName === null || $chosenName === null || !isset(self::SERIES_REGISTRIES[$expectedName])) {
            return null;
        }

        // SIT files are kept under Land (user, 2026-11-15): Lands and the SIT Registry both accept them.
        $isSit = self::SERIES_REGISTRIES[$expectedName] === 'SIT';
        if ($isSit) {
            if (in_array($chosenName, ['Lands Registry', 'SIT Registry'], true)) {
                return null;
            }
            $expectedName = 'Lands Registry';
        }
        if ($expectedName === $chosenName) {
            return null;
        }

        $level = $expectedName === 'Lands Registry' && !$isSit ? 'warn' : 'block';

        return [
            'level' => $level,
            'expected_name' => $expectedName,
            'chosen_name' => $chosenName,
            'message' => $level === 'block'
                ? "{$fileNumber} is a {$expectedName} file number, so its scans cannot go into the {$chosenName}. Upload it to the {$expectedName}."
                : "{$fileNumber} is a {$expectedName} file number but the {$chosenName} was picked. Confirm the physical file is kept in the {$chosenName}, or upload it to the {$expectedName}.",
        ];
    }

    /** The series registry's canonical name, or null for a registry that is never checked. */
    private static function canonical(?string $registry): ?string
    {
        $r = preg_replace('/\s+/', ' ', trim((string) $registry));
        foreach (array_keys(self::SERIES_REGISTRIES) as $name) {
            if (strcasecmp($r, $name) === 0) {
                return $name;
            }
        }

        return preg_match('/^lands?\s+registry$/i', $r) ? 'Lands Registry' : null;
    }
}
