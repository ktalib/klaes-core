<?php

namespace App\Services\Prs\Support;

/**
 * One canonical land-use vocabulary. Answers D3.
 *
 * Six vocabularies are in use across the source reports, and the database adds
 * more. Measured on 2026-08-02:
 *
 *   pra.land_use          RESIDENTIAL 105,508 · COMMERCIAL 14,067 · Industrial 2,571 ·
 *                         AGRICULTURAL 1,409 · RES 404 · COMMERCIAL AND RESIDENTIAL 290 ·
 *                         RESIDENTIAL/COMMERCIAL 243 · Agriculture 172 · IND 134 · COM 23
 *   mls_file_no.land_use  CON-RES 2,540 · RES 2,459 · CON-COM 435 · IND 243 · COM 211 ·
 *                         CON-AG 102 · CON-IND 56 · SIT 9 · RES-RC 6 · IND-RC 3 · AG 3
 *
 * Three separate problems: case variance, abbreviation variance, and values that
 * name two categories at once. The last is why Mixed exists — assigning
 * "RESIDENTIAL/COMMERCIAL" to either side would overstate one and understate the
 * other, and there are 668 such rows.
 *
 * The CON- prefix on mls_file_no encodes conversion, which is already carried by
 * mls_file_no.source. It is stripped here; the stream is not a land use.
 */
class LandUseNormalizer
{
    public const RESIDENTIAL   = 'Residential';
    public const COMMERCIAL    = 'Commercial';
    public const INDUSTRIAL    = 'Industrial';
    public const AGRICULTURE   = 'Agriculture';
    public const INSTITUTIONAL = 'Institutional';
    public const MIXED         = 'Mixed';
    public const UNCATEGORISED = 'Uncategorised';

    /** Display order, fixed so charts keep a stable series order and colour. */
    public const CANON = [
        self::RESIDENTIAL,
        self::COMMERCIAL,
        self::AGRICULTURE,
        self::INDUSTRIAL,
        self::INSTITUTIONAL,
        self::MIXED,
        self::UNCATEGORISED,
    ];

    /** Exact matches after upper-casing and stripping the CON-/-RC affixes. */
    private const EXACT = [
        'RES'            => self::RESIDENTIAL,
        'RESIDENTIAL'    => self::RESIDENTIAL,
        'RESIDENCIAL'    => self::RESIDENTIAL,
        'HIGH DENSITY'   => self::RESIDENTIAL,
        'LOW DENSITY'    => self::RESIDENTIAL,
        'COM'            => self::COMMERCIAL,
        'COMM'           => self::COMMERCIAL,
        'COMMERCIAL'     => self::COMMERCIAL,
        'SMALL SCALE'    => self::COMMERCIAL,
        'IND'            => self::INDUSTRIAL,
        'INDUSTRY'       => self::INDUSTRIAL,
        'INDUSTRIAL'     => self::INDUSTRIAL,
        'AG'             => self::AGRICULTURE,
        'AGRIC'          => self::AGRICULTURE,
        'AGRICULTURE'    => self::AGRICULTURE,
        'AGRICULTURAL'   => self::AGRICULTURE,
        'FARMING'        => self::AGRICULTURE,
        'INSTITUTIONAL'  => self::INSTITUTIONAL,
        'INSTITUTION'    => self::INSTITUTIONAL,
        'FACILITIES'     => self::INSTITUTIONAL,
        'FACILITY'       => self::INSTITUTIONAL,
        'RELIGIOUS'      => self::INSTITUTIONAL,
        'EDUCATIONAL'    => self::INSTITUTIONAL,
        'SIT'            => self::INSTITUTIONAL,
        'MIXED'          => self::MIXED,
        'MIXED USE'      => self::MIXED,
    ];

    /**
     * The land-use prefix carried by every KLAES-generated file number, longest
     * pattern first so CON-RES-RC is not eaten by CON-RES or RES.
     *
     * Source: .agent/skills/klaes/SKILL.md §5. The prefix encodes three things at
     * once — land use, whether the file is a conversion (CON-) and whether it is a
     * recertification (-RC). Only the land use is taken here; the stream lives on
     * mls_file_no.source and the -RC flag is not a land use.
     */
    private const FILE_NUMBER_PREFIXES = [
        'CON-RES-RC' => self::RESIDENTIAL,
        'CON-COM-RC' => self::COMMERCIAL,
        'CON-IND-RC' => self::INDUSTRIAL,
        'CON-AG-RC'  => self::AGRICULTURE,
        'CON-RES'    => self::RESIDENTIAL,
        'CON-COM'    => self::COMMERCIAL,
        'CON-IND'    => self::INDUSTRIAL,
        'CON-AG'     => self::AGRICULTURE,
        'RES-RC'     => self::RESIDENTIAL,
        'COM-RC'     => self::COMMERCIAL,
        'IND-RC'     => self::INDUSTRIAL,
        'AG-RC'      => self::AGRICULTURE,
        'RES'        => self::RESIDENTIAL,
        'COM'        => self::COMMERCIAL,
        'IND'        => self::INDUSTRIAL,
        'AG'         => self::AGRICULTURE,
    ];

    /**
     * Recover the land use from the file number when the column is empty.
     *
     * Only works for KLAES-generated numbers (RES-2026-1862, CON-RES-2026-1308).
     * Legacy and temporary numbers — TEMP-10823, KN 7593 — carry no land use and
     * correctly return null rather than a guess.
     */
    public function deriveFromFileNumber(?string $fileNumber): ?string
    {
        $v = strtoupper(trim((string) $fileNumber));

        if ($v === '') {
            return null;
        }

        foreach (self::FILE_NUMBER_PREFIXES as $prefix => $canonical) {
            if (str_starts_with($v, $prefix . '-')) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * The same mapping as a T-SQL expression, so the fallback can be applied
     * server-side and the query still groups on a handful of categories rather
     * than on tens of thousands of distinct file numbers.
     *
     * @param string $landUseExpr  the land_use column (may be NULL/blank)
     * @param string $fileNoExpr   the file number to fall back to
     */
    public function sqlEffectiveLandUse(string $landUseExpr, string $fileNoExpr): string
    {
        $cases = '';

        foreach (self::FILE_NUMBER_PREFIXES as $prefix => $canonical) {
            $cases .= " WHEN UPPER($fileNoExpr) LIKE '$prefix-%' THEN '$canonical'";
        }

        return "COALESCE(NULLIF(LTRIM(RTRIM($landUseExpr)), ''), CASE$cases ELSE NULL END)";
    }

    public function normalize(?string $raw): string
    {
        $v = strtoupper(trim((string) $raw));

        if ($v === '' || $v === 'NULL' || $v === 'N/A') {
            return self::UNCATEGORISED;
        }

        // "CON-RES" is a conversion of a residential plot; the stream lives on
        // mls_file_no.source. "-RC" is an undocumented mls suffix, stripped the same way.
        $v = preg_replace('/^CON[\s\-_]+/', '', $v);
        $v = preg_replace('/[\s\-_]+RC$/', '', $v);
        $v = trim(preg_replace('/\s+/', ' ', str_replace(['_'], ' ', (string) $v)));

        if (isset(self::EXACT[$v])) {
            return self::EXACT[$v];
        }

        // Two categories named at once — "COMMERCIAL AND RESIDENTIAL",
        // "RESIDENTIAL/COMMERCIAL". Never silently assigned to one side.
        $hits = 0;
        foreach ([self::RESIDENTIAL => 'RESIDEN', self::COMMERCIAL => 'COMMERC',
                  self::INDUSTRIAL => 'INDUSTR', self::AGRICULTURE => 'AGRIC'] as $stem) {
            if (str_contains($v, $stem)) {
                $hits++;
            }
        }

        if ($hits > 1) {
            return self::MIXED;
        }

        return match (true) {
            str_contains($v, 'RESIDEN')                             => self::RESIDENTIAL,
            str_contains($v, 'COMMERC') || str_contains($v, 'SHOP') => self::COMMERCIAL,
            str_contains($v, 'INDUSTR') || str_contains($v, 'WAREHOUSE') => self::INDUSTRIAL,
            str_contains($v, 'AGRIC') || str_contains($v, 'FARM')   => self::AGRICULTURE,
            str_contains($v, 'SCHOOL') || str_contains($v, 'MOSQUE')
                || str_contains($v, 'CHURCH') || str_contains($v, 'HOSPITAL')
                || str_contains($v, 'INSTITUT')                     => self::INSTITUTIONAL,
            default                                                 => self::UNCATEGORISED,
        };
    }
}
