<?php

namespace App\Support;

/**
 * Folds the free-text `file_indexings.lga` down to the canonical `lgas` table.
 *
 * WHY THIS EXISTS
 * The column was never constrained to the reference table, so manual entry
 * produced 196 distinct strings against 45 canonical rows. Measured 2026-08-16
 * on the live database: 123,211 of 128,559 LGA-bearing files (96%) match a
 * dropdown value case-insensitively, and 5,409 do not.
 *
 * That 4% matters because of the SPAS offline app. `file_index_cache` is seeded
 * per surveyor by LGA, and the lookup filtered with an exact `where('lga', ?)`.
 * A surveyor scoped to Nasarawa got the canonical rows and silently missed the
 * 3,388 filed under "NASSARAWA" — the file simply would not exist offline, with
 * no error explaining why. Resolving aliases at query time fixes that without
 * needing a data migration first.
 *
 * DELIBERATELY CONSERVATIVE
 * Only unambiguous typos and well-known abbreviations of *Kano* LGAs are mapped.
 * Anything else returns null rather than guessing, because the raw column also
 * contains:
 *   - LGAs of other states (Hadejia, Dutse, Ringim, Gumel, Kazaure — Jigawa;
 *     "Egbado South" — Ogun),
 *   - ward/quarter names that are not LGAs at all (Waje, Sharada, Naibawa,
 *     Giginyu, Dakata, Yakasai, Gobirawa),
 *   - and outright junk ("29-12-1984", "Select LGA", "DA").
 * Mapping any of those would file a record under the wrong LGA, which is worse
 * than leaving it unresolved.
 *
 * "Kano City" is also left unmapped on purpose: it is the old walled city, which
 * spans Dala, Gwale and Kano Municipal, and one row even reads "KANO CITY DALA".
 * There is no single correct answer, so it stays a decision for a human.
 *
 * @see docs/plans/SPAS_MOBILE_OFFLINE_CAPACITOR_SYNC_PLAN.md §11.1
 */
class LgaNormalizer
{
    /**
     * Raw value (lower-cased, whitespace-collapsed) => canonical `lgas`.name.
     *
     * Every target here must exist in the lgas table. Counts are the live row
     * counts as at 2026-08-16, kept so the value of each entry is visible.
     */
    private const ALIASES = [
        // Nasarawa — one 's' is canonical
        'nassarawa'         => 'Nasarawa',   // 3,388
        'nassarwa'          => 'Nasarawa',   // 1

        // Kano Municipal — by far the most misspelt
        'municipal'         => 'Kano Municipal',   // 334
        'munincipal'        => 'Kano Municipal',   // 253
        'kano munincipal'   => 'Kano Municipal',   // 7
        'kano municpal'     => 'Kano Municipal',   // 7
        'munisipal'         => 'Kano Municipal',   // 3
        'kano municipla'    => 'Kano Municipal',   // 2
        'kano municial'     => 'Kano Municipal',   // 2
        'kano munucipal'    => 'Kano Municipal',   // 1
        'kan0 municipal'    => 'Kano Municipal',   // 1 (zero for O)

        // Dawakin Kudu
        'd/kudu'            => 'Dawakin Kudu',     // 154
        'dawakin kuda'      => 'Dawakin Kudu',     // 3
        'dawajin kudu'      => 'Dawakin Kudu',     // 1
        'dawaki kudu'       => 'Dawakin Kudu',     // 1

        // Ungogo
        'ungoggo'           => 'Ungogo',           // 110
        'ungogo`'           => 'Ungogo',           // 2 (stray backtick)
        'unoggo'            => 'Ungogo',           // 1

        // Dambatta — 'n' for 'm' is the common slip
        'danbatta'          => 'Dambatta',         // 64
        'danbatt'           => 'Dambatta',         // 2
        'dantatta'          => 'Dambatta',         // 1

        // Dawakin Tofa
        'd/tofa'            => 'Dawakin Tofa',     // 56
        'dawaki tofa'       => 'Dawakin Tofa',     // 3
        'dakawakin tofa'    => 'Dawakin Tofa',     // 1

        // Kumbotso
        'kumbtso'           => 'Kumbotso',         // 22
        'kunbotso'          => 'Kumbotso',         // 2

        // Tudun Wada
        't/wada'            => 'Tudun Wada',       // 16

        // Garun Mallam
        'garin mallam'      => 'Garun Mallam',     // 3
        'garun malam'       => 'Garun Mallam',     // 3
        'garin malam'       => 'Garun Mallam',     // 3

        // Tarauni
        'tarauna'           => 'Tarauni',          // 3
        'taraunu'           => 'Tarauni',          // 2

        // Minjibir
        'mingibir'          => 'Minjibir',         // 2
        'minjir'            => 'Minjibir',         // 1

        // Assorted single-letter slips
        'geazawa'           => 'Gezawa',           // 2
        'faffe'             => 'Fagge',            // 2
        'sdala'             => 'Dala',             // 1
    ];

    /** Lower-case, trim, and collapse runs of whitespace ("KANO  MUNICIPAL"). */
    public static function key(?string $value): string
    {
        return preg_replace('/\s+/', ' ', trim(mb_strtolower((string) $value)));
    }

    /**
     * Resolve a raw LGA string to its canonical name.
     *
     * @param  string[]  $canonical  the `lgas` table names
     * @return string|null null when the value cannot be resolved with confidence
     */
    public static function normalize(?string $raw, array $canonical): ?string
    {
        $key = self::key($raw);

        if ($key === '') {
            return null;
        }

        // Exact match, ignoring case and spacing.
        foreach ($canonical as $name) {
            if (self::key($name) === $key) {
                return $name;
            }
        }

        $alias = self::ALIASES[$key] ?? null;

        // Never return an alias target that is not actually in the table —
        // otherwise a reference-data change turns into a silent bad write.
        if ($alias !== null && in_array($alias, $canonical, true)) {
            return $alias;
        }

        return null;
    }

    /**
     * Every raw spelling that means this canonical LGA, including itself.
     *
     * Used to widen a cache query from `where lga = ?` to `whereIn lga (...)`,
     * so the offline file index picks up the misspelt rows too.
     *
     * @return string[]
     */
    public static function variantsFor(string $canonical): array
    {
        $variants = [$canonical];

        foreach (self::ALIASES as $raw => $target) {
            if (self::key($target) === self::key($canonical)) {
                $variants[] = $raw;
            }
        }

        return array_values(array_unique($variants));
    }

    /** The full alias map, for the backfill command and for tests. */
    public static function aliases(): array
    {
        return self::ALIASES;
    }
}
