<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Works out which files are due for a Re-grant because their statutory term has run out.
 *
 * A right of occupancy is granted for a fixed term that depends on the land use:
 *   Residential / Agricultural  -> 99 years
 *   Commercial  / Industrial    -> 40 years
 *
 * The clock starts at the instrument's transaction date:
 *   CofO  -> CofO_staging.transaction_date
 *   RofO  -> pra.transaction_date  (transaction_type is one of {@see self::ROFO_TYPES})
 *
 * `CofO_staging.cofo_date` also exists but is deliberately not used: transaction_date is
 * populated on 30,540 of 32,101 rows (95.1%) against cofo_date's 24,448 (76.2%).
 *
 * Both columns are nvarchar holding at least three different date formats, so the year is
 * extracted in SQL with a PATINDEX scan for the first 4-digit year rather than a cast —
 * see {@see self::yearExpression()}. Everything is evaluated in SQL so the page can
 * paginate; pulling all 84k candidate rows into PHP to filter them is far too slow.
 */
class RegrantTermService
{
    /** Term in years, by normalised land use. */
    public const TERM_RESIDENTIAL_AGRIC = 99;
    public const TERM_COMMERCIAL_INDUST = 40;

    /** pra.transaction_type values that represent a Right of Occupancy. */
    public const ROFO_TYPES = [
        'Right of Occupancy',
        'Right of Occupancy (OSS)',
        'Right of Occupancy (ST)',
        'R-OFO',
    ];

    /**
     * Grant years below this are data errors, not titles. 1901 rather than 1900 because
     * `pra` holds 91 epoch sentinels stamped in year 1900 ("Jan  1 1900 12:00AM" x77,
     * plus a handful of Feb 1900) which would otherwise show as ~86 years overdue.
     */
    private const MIN_YEAR = 1901;

    /**
     * SQL that pulls the grant year out of a mixed-format nvarchar date column.
     *
     * The column holds ISO ("2016-03-16"), day-first ("20-6-1994", "8/2/1995") and SQL
     * Server's default datetime string ("May 11 2025 12:00AM"). Rather than guess the
     * format, find the first 4-character run that looks like a year and read it — which
     * lands on the right substring in all three layouts.
     *
     * It also discards the .NET DateTime.MinValue sentinel "0001-01-01" for free: its
     * leading "0001" cannot match [12][0-9][0-9][0-9], and no other 4-digit run exists.
     */
    public static function yearExpression(string $column): string
    {
        return "TRY_CAST(SUBSTRING({$column}, NULLIF(PATINDEX('%[12][0-9][0-9][0-9]%', {$column}), 0), 4) AS INT)";
    }

    /**
     * SQL mapping a file to its term in years, or NULL when the term cannot be determined.
     *
     * The land use is taken from the MLS file number's prefix first — RES/AG/COM/IND is a
     * structural part of the numbering scheme and is the authoritative classification.
     * The free-text `land_use` column is only a fallback, used for legacy numbers that
     * carry no prefix (KNML/MLKN/KN...).
     *
     * Prefix wins because the two disagree on the term for 2.7% of RofO rows (1,275 of
     * 46,954) — e.g. 996 files numbered RES-… are tagged "COMMERCIAL" in the column, which
     * would wrongly shorten their term from 99 to 40 years and flag live titles as expired.
     * The column is also riddled with typos ("RESIDENCIAL", "REAIDENTIAL", "AGARICULTURAL").
     *
     * Mixed uses ("RESIDENTIAL/COMMERCIAL", "COMMERCIAL AND RESIDENTIAL") carry two
     * different terms and are deliberately left NULL — guessing one would either hide an
     * expired title or raise a false alarm. {@see self::unassessableCounts()} surfaces them.
     */
    public static function termExpression(string $fileNoColumn, string $landUseColumn): string
    {
        $fno   = "UPPER(LTRIM(RTRIM({$fileNoColumn})))";
        $upper = "UPPER(LTRIM(RTRIM({$landUseColumn})))";
        $long  = self::TERM_RESIDENTIAL_AGRIC;
        $short = self::TERM_COMMERCIAL_INDUST;

        // "CON-" (conversion) and "LKN-" (legacy Kano) wrap the land-use code, so match
        // the code either at the start or straight after one of those prefixes.
        $prefixMatch = function (string $code) use ($fno) {
            return "{$fno} LIKE '{$code}-%' OR {$fno} LIKE 'CON-{$code}-%' OR {$fno} LIKE 'LKN-{$code}-%'";
        };

        return "CASE
            WHEN " . $prefixMatch('RES') . " THEN {$long}
            WHEN " . $prefixMatch('AG')  . " THEN {$long}
            WHEN " . $prefixMatch('COM') . " THEN {$short}
            WHEN " . $prefixMatch('IND') . " THEN {$short}
            WHEN {$landUseColumn} IS NULL OR LTRIM(RTRIM({$landUseColumn})) = '' THEN NULL
            WHEN {$upper} LIKE '%/%' OR {$upper} LIKE '% AND %' THEN NULL
            WHEN {$upper} LIKE 'RES%' OR {$upper} LIKE 'REA%' OR {$upper} LIKE 'AG%' THEN {$long}
            WHEN {$upper} LIKE 'COM%' OR {$upper} LIKE 'IND%' THEN {$short}
            ELSE NULL
        END";
    }

    /** How long the computed due-list is reused before being rebuilt. */
    private const CACHE_TTL_MINUTES = 15;

    /**
     * Every file whose term has expired, computed once and cached.
     *
     * The underlying union scans all 133,915 `pra` rows and 32,101 `CofO_staging` rows,
     * applying a PATINDEX and a CASE per row — nothing an index can help with, so it costs
     * seconds. The *result* is only ~1,200 rows, so it is far cheaper to build the whole
     * set once and then filter, count and paginate it in memory than to re-run the union
     * for each of those operations (which cost ~17s of page load between them).
     *
     * Grants are historical records that change rarely, so a short TTL is ample. Call
     * {@see self::flushCache()} after writing a Re-grant to drop the stale entry.
     */
    public function allDue(?int $asOfYear = null): Collection
    {
        $asOfYear ??= (int) now()->format('Y');

        return Cache::remember(
            "regrant:due:{$asOfYear}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($asOfYear) {
                return collect($this->runDueQuery($asOfYear))
                    ->each(function ($row) {
                        // file_indexings stores many holder names wrapped in literal double
                        // quotes ("KANO CLUB"), which would otherwise render as-is.
                        $row->holder = $this->unquote($row->holder);
                        $row->file_title = $this->unquote($row->file_title);
                        $row->grant_date_display = self::formatDate($row->grant_date_raw);
                    });
            }
        );
    }

    /**
     * Render a stored transaction date as "July 15, 2026".
     *
     * The column is nvarchar and holds at least four shapes, mixed within the same table:
     *   "2016-03-16"            ISO
     *   "1919-01-04 00:00:00"   ISO with a time part
     *   "20-6-1994", "8/2/1995" day-first, variable width
     *   "May 11 2025 12:00AM"   SQL Server's default datetime string
     *
     * Day-first is assumed for the slash/dash forms — values like "20-6-1994" have a day
     * that cannot be a month, and it is the local convention. Anything that does not parse
     * to a real calendar date is returned unchanged rather than guessed at, so a bad value
     * stays visible instead of silently becoming a plausible-looking wrong date.
     */
    public static function formatDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $parts = null;

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $m)) {
            $parts = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } elseif (preg_match('#^(\d{1,2})[-/](\d{1,2})[-/](\d{4})#', $value, $m)) {
            $parts = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^([A-Za-z]{3,})\s+(\d{1,2})\s+(\d{4})/', $value, $m)) {
            $month = date_parse($m[1])['month'] ?? false;
            if ($month) {
                $parts = [(int) $m[2], (int) $month, (int) $m[3]];
            }
        }

        if ($parts === null) {
            return $value;
        }

        [$day, $month, $year] = $parts;

        if (!checkdate($month, $day, $year)) {
            return $value;
        }

        // Built from a lookup rather than date()/mktime() so that pre-1970 grant dates —
        // and there are titles here from 1914 — are not at the mercy of timestamp range.
        return self::MONTH_NAMES[$month] . ' ' . $day . ', ' . $year;
    }

    /** 1-indexed month names for {@see self::formatDate()}. */
    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    /** Strip a matched pair of wrapping double quotes from a stored value. */
    private function unquote(?string $value): ?string
    {
        $value = trim((string) $value);

        if (mb_strlen($value) > 1 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = trim(mb_substr($value, 1, mb_strlen($value) - 2));
        }

        return $value === '' ? null : $value;
    }

    /**
     * Files due for re-grant, filtered and paged.
     *
     * @param array $filters source (cofo|rofo), term (40|99), search
     */
    public function due(array $filters = [], int $perPage = 25, ?int $asOfYear = null): LengthAwarePaginator
    {
        $rows = $this->filter($this->allDue($asOfYear), $filters);

        $page = max(1, (int) (Paginator::resolveCurrentPage() ?: 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    /** Apply the UI filters to an already-built due collection. */
    private function filter(Collection $rows, array $filters): Collection
    {
        $source = trim((string) ($filters['source'] ?? ''));
        $term   = trim((string) ($filters['term'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        if ($source !== '') {
            $rows = $rows->where('source', $source);
        }

        if ($term !== '') {
            $rows = $rows->where('term_years', (int) $term);
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = $rows->filter(function ($row) use ($needle) {
                foreach (['file_no', 'holder', 'land_use'] as $field) {
                    if (str_contains(mb_strtolower((string) ($row->$field ?? '')), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $rows->values();
    }

    /** Headline counts, derived from the cached set rather than re-querying. */
    public function dueCounts(?int $asOfYear = null): array
    {
        $rows = $this->allDue($asOfYear);

        return [
            'total' => $rows->count(),
            'cofo'  => $rows->where('source', 'cofo')->count(),
            'rofo'  => $rows->where('source', 'rofo')->count(),
        ];
    }

    /** Drop the cached due-list and unassessable counts. */
    public function flushCache(?int $asOfYear = null): void
    {
        $asOfYear ??= (int) now()->format('Y');
        Cache::forget("regrant:due:{$asOfYear}");
        Cache::forget('regrant:unassessable');
    }

    /**
     * The expired-term list, driven by `file_indexings`.
     *
     * The file register is the spine and the two instrument tables are joined to it on
     * file number, rather than the reverse. Reading the instrument tables directly gave
     * one row per *instrument*, which is wrong on two counts:
     *
     *  - Duplicates. A file can hold up to 3 CofO_staging rows and 7 RofO `pra` rows, so
     *    118 files were listed two or three times (1,202 rows for 1,081 files).
     *  - Poor detail. The instrument tables barely carry the address: CofO_staging has a
     *    district on 3,032 of 32,101 rows (9%), against 119,927 of 133,778 (90%) in
     *    file_indexings. Plot, LGA, holder and title are all better populated there too.
     *
     * So every display field now comes from file_indexings, and the instruments supply
     * only the grant date. A CofO supersedes a RofO, so when a file has both (15,802 do)
     * the CofO date wins; the most recent instrument of that kind sets the current term.
     */
    private function runDueQuery(int $asOfYear): array
    {
        $rofoTypes = "'" . implode("','", self::ROFO_TYPES) . "'";
        $cofoYear  = self::yearExpression('transaction_date');
        $rofoYear  = self::yearExpression('transaction_date');
        // The term is decided by the file register's own number and land use.
        $term      = self::termExpression('fi.file_number', 'fi.land_use_type');
        $grantYear = 'COALESCE(c.grant_year, r.grant_year)';

        $sql = "
        WITH cofo AS (
            SELECT mlsFNo, grant_date, grant_year,
                   ROW_NUMBER() OVER (PARTITION BY mlsFNo ORDER BY grant_year DESC) AS rn
            FROM (
                SELECT mlsFNo, transaction_date AS grant_date, {$cofoYear} AS grant_year
                FROM CofO_staging
                WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
            ) s
            WHERE grant_year BETWEEN ? AND ?
        ),
        rofo AS (
            SELECT mlsFNo, grant_date, grant_year,
                   ROW_NUMBER() OVER (PARTITION BY mlsFNo ORDER BY grant_year DESC) AS rn
            FROM (
                SELECT mlsFNo, transaction_date AS grant_date, {$rofoYear} AS grant_year
                FROM pra
                WHERE transaction_type IN ({$rofoTypes})
                  AND mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
            ) s
            WHERE grant_year BETWEEN ? AND ?
        ),
        files AS (
            -- file_indexings holds 61 duplicate file numbers; keep the newest row per file.
            SELECT id, file_number, file_title, current_holder, original_holder,
                   plot_number, district, lga, location, land_use_type,
                   ROW_NUMBER() OVER (PARTITION BY file_number ORDER BY id DESC) AS rn
            FROM file_indexings
            WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
        )
        SELECT
            CASE WHEN c.grant_year IS NOT NULL THEN 'cofo' ELSE 'rofo' END AS source,
            fi.id                                   AS file_indexing_id,
            fi.file_number                          AS file_no,
            fi.file_title                           AS file_title,
            COALESCE(NULLIF(LTRIM(RTRIM(fi.current_holder)), ''),
                     NULLIF(LTRIM(RTRIM(fi.original_holder)), ''),
                     fi.file_title)                 AS holder,
            COALESCE(c.grant_date, r.grant_date)    AS grant_date_raw,
            {$grantYear}                            AS grant_year,
            {$term}                                 AS term_years,
            fi.land_use_type                        AS land_use,
            fi.plot_number                          AS plot_no,
            fi.district                             AS district,
            fi.lga                                  AS lga,
            fi.location                             AS location,
            ({$grantYear} + {$term})                AS expiry_year,
            (? - ({$grantYear} + {$term}))          AS years_overdue
        FROM files fi
        LEFT JOIN cofo c ON c.mlsFNo = fi.file_number AND c.rn = 1
        LEFT JOIN rofo r ON r.mlsFNo = fi.file_number AND r.rn = 1
        WHERE fi.rn = 1
          AND (c.grant_year IS NOT NULL OR r.grant_year IS NOT NULL)
          AND {$term} IS NOT NULL
          AND ({$grantYear} + {$term}) <= ?
          -- A file already carrying a Re-grant is no longer 'to be re-granted'.
          AND NOT EXISTS (
              SELECT 1 FROM title_status_applications t
              WHERE t.file_no = fi.file_number
                AND t.title_type = 'Re-grant'
                AND (t.is_deleted IS NULL OR t.is_deleted = 0)
          )
        ORDER BY years_overdue DESC, file_no";

        return DB::connection('sqlsrv')->select($sql, [
            self::MIN_YEAR, $asOfYear,   // cofo year window
            self::MIN_YEAR, $asOfYear,   // rofo year window
            $asOfYear,                   // years_overdue
            $asOfYear,                   // expiry cut-off
        ]);
    }

    /**
     * Files that hold a CofO or RofO but still cannot be assessed, so the gap stays visible
     * rather than being silently dropped. Counted per file (not per instrument), matching
     * the file-driven model used by {@see self::runDueQuery()}.
     *
     * @return array{with_instrument:int,no_date:int,no_term:int}
     */
    public function unassessableCounts(): array
    {
        return Cache::remember('regrant:unassessable', now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->computeUnassessableCounts());
    }

    private function computeUnassessableCounts(): array
    {
        $rofoTypes = "'" . implode("','", self::ROFO_TYPES) . "'";
        $cofoYear  = self::yearExpression('transaction_date');
        $rofoYear  = self::yearExpression('transaction_date');
        $term      = self::termExpression('fi.file_number', 'fi.land_use_type');

        $sql = "
        WITH files AS (
            SELECT id, file_number, land_use_type,
                   ROW_NUMBER() OVER (PARTITION BY file_number ORDER BY id DESC) AS rn
            FROM file_indexings
            WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
        ),
        matched AS (
            SELECT fi.file_number, fi.land_use_type,
                   (SELECT MAX({$cofoYear}) FROM CofO_staging c WHERE c.mlsFNo = fi.file_number) AS cofo_year,
                   (SELECT MAX({$rofoYear}) FROM pra p WHERE p.mlsFNo = fi.file_number
                        AND p.transaction_type IN ({$rofoTypes})) AS rofo_year,
                   {$term} AS term_years
            FROM files fi
            WHERE fi.rn = 1
              AND (EXISTS (SELECT 1 FROM CofO_staging c WHERE c.mlsFNo = fi.file_number)
                OR EXISTS (SELECT 1 FROM pra p WHERE p.mlsFNo = fi.file_number
                        AND p.transaction_type IN ({$rofoTypes})))
        )
        SELECT
            COUNT(*) AS with_instrument,
            SUM(CASE WHEN COALESCE(cofo_year, rofo_year) IS NULL THEN 1 ELSE 0 END) AS no_date,
            SUM(CASE WHEN COALESCE(cofo_year, rofo_year) IS NOT NULL
                      AND term_years IS NULL THEN 1 ELSE 0 END) AS no_term
        FROM matched";

        $row = DB::connection('sqlsrv')->selectOne($sql);

        return [
            'with_instrument' => (int) ($row->with_instrument ?? 0),
            'no_date'         => (int) ($row->no_date ?? 0),
            'no_term'         => (int) ($row->no_term ?? 0),
        ];
    }

    /** Human label for a term, used in the UI legend. */
    public static function termLabel(?int $term): string
    {
        return match ($term) {
            self::TERM_RESIDENTIAL_AGRIC => '99 yrs (Residential / Agricultural)',
            self::TERM_COMMERCIAL_INDUST => '40 yrs (Commercial / Industrial)',
            default => 'Term undetermined',
        };
    }
}
