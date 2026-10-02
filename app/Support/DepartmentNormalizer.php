<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Folds the free-text `department` captured on file requests down to the
 * canonical set held in the `departments` table.
 *
 * The column was never constrained to that table, so years of manual entry
 * produced ~64 distinct strings for ~15 real departments: job titles used as
 * departments ("Deputy Director GIS"), synonyms ("Land" / "Lands" /
 * "Land Administration"), and legacy seed values ("Management", "Records").
 * That inflated every department count on the commissioner dashboard.
 *
 * Anything that does not resolve to a domain department lands in ALL, the
 * table's cross-cutting row — see the CORPORATE list for what that covers.
 */
class DepartmentNormalizer
{
    /** Exact `departments`.name values. Output is always one of these, or null. */
    public const CANONICAL = [
        'ALL',
        'CSU',
        'Land',
        'Survey',
        'GIS',
        'KANGIS',
        'Account/Finance',
        'Deeds',
        'Physical Planning',
        'Cadastral',
        'Sectional Titling',
        'SLTR',
        'ICT',
        'GIS/Survey',
        'DCIV',
    ];

    /**
     * Units with no domain department of their own. Kept as an explicit list
     * rather than falling through to the keyword scan so that folding them into
     * ALL is a visible decision, not an accident.
     */
    private const CORPORATE = [
        'admin and general services department',
        'admin officer',
        'dags office',
        'prs',
        'legal',
        'legal department',
        "lawyer's office",
        'lawyers office',
        'secretary director legal',
        "permanent secretary's office",
        'permanent secretarys office',
        'ps',
        'commissioner',
        'honorable commissioners office',
        "honorable commissioner's office",
        'secretary commissioner 2',
        'special duty',
        'management',
        'records',
        'technical',
        'custom entry',
        'unknown department',
        'reg',
        'registry',
        'production',
    ];

    /**
     * Values the keyword scan would get wrong, or would not match at all.
     * Keys are lowercased and whitespace-collapsed.
     */
    private const ALIASES = [
        // Land
        'lands' => 'Land',
        'land administration' => 'Land',
        'director, lands office' => 'Land',
        'directors lands office' => 'Land',
        // Survey — no "survey" substring, or a unit rather than a title
        'geometry' => 'Survey',
        'surveyors' => 'Survey',
        // KANGIS — Director General heads KANGIS
        'dg' => 'KANGIS',
        'dg, kangis' => 'KANGIS',
        // Sectional Titling
        'st' => 'Sectional Titling',
        // Customer-facing front desk
        'customer service' => 'CSU',
        'customer service unit' => 'CSU',
        'one stop shop' => 'CSU',
        'oss' => 'CSU',
        // Finance
        'accountant' => 'Account/Finance',
        'accounts' => 'Account/Finance',
        'finance' => 'Account/Finance',
        'account' => 'Account/Finance',
    ];

    /**
     * Substring rules for anything not matched exactly, so titles minted later
     * ("Assistant Director Deeds II") still land in the right department.
     * Order matters: the more specific department wins.
     */
    private const KEYWORDS = [
        'dciv' => 'DCIV',
        'kangis' => 'KANGIS',
        'sltr' => 'SLTR',
        'sectional titling' => 'Sectional Titling',
        'physical planning' => 'Physical Planning',
        'cadastral' => 'Cadastral',
        'deed' => 'Deeds',
        'gis/survey' => 'GIS/Survey',
        'gis' => 'GIS',
        'survey' => 'Survey',
        'ict' => 'ICT',
        'customer' => 'CSU',
        'land' => 'Land',
    ];

    /**
     * Blank in, blank out: an unset department still renders as "Unassigned"
     * on the dashboard and is not worth inventing a value for.
     */
    public static function normalize(?string $value): ?string
    {
        $key = Str::lower(preg_replace('/\s+/', ' ', trim((string) $value)));

        if ($key === '' || $key === 'unassigned') {
            return null;
        }

        foreach (self::CANONICAL as $canonical) {
            if ($key === Str::lower($canonical)) {
                return $canonical;
            }
        }

        if (isset(self::ALIASES[$key])) {
            return self::ALIASES[$key];
        }

        if (in_array($key, self::CORPORATE, true)) {
            return 'ALL';
        }

        foreach (self::KEYWORDS as $needle => $canonical) {
            if (str_contains($key, $needle)) {
                return $canonical;
            }
        }

        return 'ALL';
    }
}
