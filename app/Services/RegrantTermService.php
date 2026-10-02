<?php

namespace App\Services;

use Carbon\CarbonImmutable;
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
     * Earliest grant year treated as a real transaction date. Anything older is a data
     * error rather than a title, and the due list must not act on it.
     *
     * Two distinct problems sat below this floor and put 731 bad rows on the due list:
     *  - Epoch sentinels. `pra` holds 91 rows stamped in year 1900 ("Jan  1 1900 12:00AM"
     *    x77, plus a handful of Feb 1900), which showed as ~86 years overdue.
     *  - Instrument dates carried over from a predecessor title, dated long before the file
     *    itself — e.g. COM-RC-1982-232 dated 1940-11-01 — so the term was being clocked from
     *    a colonial deed. The file's real grant is its 1980s (re)certification, not that date.
     *
     * A file with both an old and a recent instrument is not lost: the CTEs pick the newest
     * instrument *within* this window, so it is simply clocked from the valid one instead.
     */
    private const MIN_GRANT_YEAR = 1981;

    /**
     * File-number prefixes that mark a KANGIS file. KANGIS numbers (MLKN 2958, KNML 1, KN…)
     * are excluded from the due list: they are not MLS grants, so the statutory term cannot
     * be clocked from their instrument dates. Matched on the number rather than only on
     * `general_registry` because 38 KANGIS-numbered rows are filed under Lands Registry.
     */
    private const KANGIS_PREFIXES = ['MLKN%', 'KNML%', 'KN%'];

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

    /**
     * SQL reading the term actually granted on an instrument, from `period` + `period_unit`.
     *
     * This is the figure the statute was applied with, and it is what the term must be clocked
     * from — a standard term inferred from the land use is only ever a guess about it. On
     * CofO_staging it is populated for 21,158 rows (55.7%), covering 20,945 files in the
     * register; on `pra` it is present on 81 of 167,841 rows, so RofO-only files will almost
     * always fall through to the standard term.
     *
     * Only whole years count. `period_unit` holds 'Months' or 'Days' on 11 rows and `period`
     * is '0' on 7 more; neither is a tenure a title can be granted for, so they are rejected
     * rather than converted, and those files fall back like any other file with no term. The
     * 10,102 rows with a blank unit are treated as years, which is what every populated unit
     * on this column says.
     *
     * Terms below {@see self::MIN_CREDIBLE_TERM} are also rejected — see that constant.
     */
    public static function actualTermExpression(string $periodColumn, string $periodUnitColumn): string
    {
        $unit  = "UPPER(LTRIM(RTRIM(ISNULL({$periodUnitColumn}, ''))))";
        $value = "TRY_CAST(LTRIM(RTRIM({$periodColumn})) AS INT)";
        $floor = self::MIN_CREDIBLE_TERM;

        return "CASE WHEN {$unit} IN ('', 'YEARS') AND {$value} BETWEEN {$floor} AND 999 THEN {$value} END";
    }

    /**
     * Shortest term read from an instrument that is treated as real.
     *
     * A term is a far more dangerous field to get wrong in one direction than the other: too
     * long merely delays a re-grant, whereas too short declares a live title expired. Single
     * digits are where that risk concentrates, and the register carries 16 of them out of
     * 21,158 recorded terms — including four residential files recorded as `9`, next to 17,670
     * recorded as `99`. A dropped digit explains those far better than a nine-year grant does.
     *
     * Because a short term is exactly what puts a file on the due list, those 16 rows were
     * landing on it out of all proportion to their number. They are therefore not trusted, and
     * the file falls back to the standard term as though no term had been recorded at all.
     *
     * The floor is 10 rather than something higher because ten years is a real approved term —
     * the boundary is drawn at the point where the value stops being a plausible grant and
     * starts looking like a typing error, and no recorded term between 10 and 39 is affected.
     */
    private const MIN_CREDIBLE_TERM = 10;

    /**
     * SQL reading the term an officer recorded on the file itself (`file_indexings.term`).
     *
     * The column is free text holding a rendered term ("99 Years"), written through Legal
     * Search's Edit File Information modal, where it is documented as overriding the term
     * derived from land use. Leading digits are taken, which is the same thing Legal Search's
     * own reader does in JavaScript, so the two modules cannot read one stored string two
     * different ways.
     *
     * It outranks the instrument because it is the deliberate human correction: someone had
     * the title in hand and said what the term is. Only 25 rows carry one today, so it decides
     * few files — but those are exactly the files somebody has already looked at.
     */
    public static function recordedTermExpression(string $column): string
    {
        $trimmed = "LTRIM(RTRIM(ISNULL({$column}, '')))";
        // PATINDEX finds the first non-digit; the sentinel 'X' guarantees a match so an
        // all-digit value ("99") still yields its own length rather than 0.
        $digits  = "LEFT({$trimmed}, PATINDEX('%[^0-9]%', {$trimmed} + 'X') - 1)";
        $value   = "TRY_CAST(NULLIF({$digits}, '') AS INT)";

        return "CASE WHEN {$value} BETWEEN 1 AND 999 THEN {$value} END";
    }

    /** How long the computed due-list is reused before being rebuilt. */
    private const CACHE_TTL_MINUTES = 15;

    /**
     * Every file whose term has expired, computed once and cached.
     *
     * The underlying union scans all 133,915 `pra` rows and 32,101 `CofO_staging` rows,
     * applying a PATINDEX and a CASE per row — nothing an index can help with, so it costs
     * seconds. The *result* is only ~500 rows, so it is far cheaper to build the whole
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
            // v2: the row shape gained term_source / expiry_date / residual_years. A cache
            // entry written by the previous version would be served to a view that now reads
            // those fields, so the key changes rather than the old entries being trusted.
            "regrant:due:v2:{$asOfYear}",
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($asOfYear) {
                $today = CarbonImmutable::now()->startOfDay();

                return collect($this->runDueQuery($asOfYear))
                    ->map(function ($row) use ($today) {
                        // file_indexings stores many holder names wrapped in literal double
                        // quotes ("KANO CLUB"), which would otherwise render as-is.
                        $row->holder = $this->unquote($row->holder);
                        $row->file_title = $this->unquote($row->file_title);
                        $row->grant_date_display = self::formatDate($row->grant_date_raw);
                        $this->applyExpiry($row, $today);

                        return $row;
                    })
                    // The SQL filters on whole years, so it admits files whose expiry falls
                    // later in the current year and has not actually arrived yet. Nothing
                    // genuinely expired can be missing — a real expiry has an expiry_year at
                    // or below this one — so re-testing the exact date here is what makes the
                    // list correct rather than merely approximate.
                    ->filter(fn ($row) => $row->is_expired)
                    ->values();
            }
        );
    }

    /**
     * Work out when a file's term actually ran out, and write the result onto the row.
     *
     * Expiry is the commencement date plus the granted term — the calculation the statute
     * describes — rather than an arithmetic on years, so a title granted in December does not
     * read as expired in the January eleven months before it is.
     *
     * The date has to be parsed in PHP because the column is nvarchar holding at least four
     * formats; the SQL side deliberately extracts only the year (see {@see self::yearExpression()})
     * because a set-wide multi-format parse is precisely what that approach exists to avoid.
     * SQL therefore narrows the field and this settles it.
     *
     * A commencement that will not parse still yields an answer, at year precision, and the
     * row says so in `date_precision`. Dropping those files would hide real expiries behind a
     * formatting problem; the same instinct as {@see self::formatDate()} returning an
     * unparseable value unchanged rather than inventing a plausible date for it.
     */
    private function applyExpiry(object $row, CarbonImmutable $today): void
    {
        $term  = (int) $row->term_years;
        $parts = self::parseDateParts($row->grant_date_raw);

        if ($parts !== null) {
            [$day, $month, $year] = $parts;

            $commencement = CarbonImmutable::create($year, $month, $day)->startOfDay();
            $expiry       = $commencement->addYears($term);

            $row->commencement_date    = $commencement->toDateString();
            $row->expiry_date          = $expiry->toDateString();
            $row->expiry_date_display  = self::formatDate($expiry->toDateString());
            $row->date_precision       = 'date';
            $row->is_expired           = $expiry->lessThanOrEqualTo($today);
            $row->years_overdue        = $row->is_expired ? (int) $expiry->diffInYears($today) : 0;
        } else {
            $expiryYear = (int) $row->grant_year + $term;
            $nowYear    = (int) $today->format('Y');

            $row->commencement_date   = null;
            $row->expiry_date         = null;
            $row->expiry_date_display = (string) $expiryYear;
            $row->date_precision      = 'year';
            $row->is_expired          = $expiryYear <= $nowYear;
            $row->years_overdue       = max($nowYear - $expiryYear, 0);
        }

        // Negative once the term has elapsed, matching how the Legal Search report states a
        // residual term — an expired title reads as "-34 Years" there rather than as zero.
        $row->residual_years = -$row->years_overdue;
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

        $parts = self::parseDateParts($value);

        if ($parts === null) {
            return $value;
        }

        [$day, $month, $year] = $parts;

        // Built from a lookup rather than date()/mktime() so that pre-1970 grant dates —
        // and there are titles here from 1914 — are not at the mercy of timestamp range.
        return self::MONTH_NAMES[$month] . ' ' . $day . ', ' . $year;
    }

    /**
     * Break a stored date string into [day, month, year], or null when it is not a real
     * calendar date in any of the shapes the columns actually hold.
     *
     * Split out of {@see self::formatDate()} because the expiry calculation needs the parts,
     * not a rendered string: an expiry is the commencement date plus the term, so the day and
     * month have to survive. Both callers therefore agree by construction about what counts
     * as a usable date, and a value this rejects is one formatDate() also leaves alone.
     *
     * @return array{0:int,1:int,2:int}|null
     */
    public static function parseDateParts(?string $value): ?array
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
            return null;
        }

        [$day, $month, $year] = $parts;

        return checkdate($month, $day, $year) ? [$day, $month, $year] : null;
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

        // The Term filter carries two kinds of choice: a specific length (99, 40) and a
        // provenance ("actual", "standard"). They answer different questions — "which tenure"
        // versus "how sure are we" — and both are worth filtering on, so the one control
        // accepts either rather than adding a second dropdown beside it.
        if ($term === 'actual') {
            $rows = $rows->whereIn('term_source', ['file', 'instrument']);
        } elseif ($term === 'standard') {
            $rows = $rows->where('term_source', 'standard');
        } elseif ($term !== '') {
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
        Cache::forget("regrant:due:v2:{$asOfYear}");
        Cache::forget('regrant:unassessable:v2');
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

        // Commencement: the certificate's own date when it has one, else the transaction date.
        // cofo_date is the more faithful statement of when the term began, but it is populated
        // on 75.5% of rows against transaction_date's 93.3%, so neither alone is enough — the
        // union of the two loses nothing and prefers the better answer where it exists.
        $cofoCommencement = "COALESCE(NULLIF(LTRIM(RTRIM(cofo_date)), ''), transaction_date)";
        $cofoYear         = self::yearExpression($cofoCommencement);
        $rofoYear         = self::yearExpression('transaction_date');

        $cofoActualTerm = self::actualTermExpression('period', 'period_unit');
        $rofoActualTerm = self::actualTermExpression('period', 'period_unit');

        // The term, in the priority the grant itself dictates:
        //   1. what an officer recorded against the file, having seen the title;
        //   2. what the instrument says was granted;
        //   3. only then the standard term for the land use.
        // Reversing 2 and 3 is the bug this replaces: 473 files numbered COM- were granted 99
        // years and were being clocked at 40, and 45 numbered RES- were granted 40 and clocked
        // at 99 — roughly a thousand files assessed against a term nobody ever granted them.
        $recordedTerm = self::recordedTermExpression('fi.term');
        // ct.any_term reaches past the MIN_GRANT_YEAR window that c and r sit behind. The
        // floor is there to reject a *date* — an instrument date carried over from a
        // predecessor deed, which would clock the term from a colonial grant — but the length
        // of the term is a fact about the title, not about which date we clock it from.
        // Discarding both together assessed five commercial recertification files at the
        // standard 40 years while their certificate plainly recorded 99. The date still
        // respects the window; only the term is allowed out of it.
        $instrumentTerm = 'COALESCE(c.actual_term, r.actual_term, ct.any_term)';
        $standardTerm   = self::termExpression('fi.file_number', 'fi.land_use_type');
        $term           = "COALESCE({$recordedTerm}, {$instrumentTerm}, {$standardTerm})";

        $termSource = "CASE
            WHEN {$recordedTerm} IS NOT NULL   THEN 'file'
            WHEN {$instrumentTerm} IS NOT NULL THEN 'instrument'
            WHEN {$standardTerm} IS NOT NULL   THEN 'standard'
        END";

        $grantYear = 'COALESCE(c.grant_year, r.grant_year)';
        $notKangis = implode(' AND ', array_map(
            fn ($p) => "UPPER(LTRIM(file_number)) NOT LIKE '{$p}'",
            self::KANGIS_PREFIXES
        ));

        $sql = "
        WITH cofo AS (
            SELECT mlsFNo, grant_date, grant_year, actual_term,
                   ROW_NUMBER() OVER (PARTITION BY mlsFNo ORDER BY grant_year DESC) AS rn
            FROM (
                SELECT mlsFNo,
                       {$cofoCommencement} AS grant_date,
                       {$cofoYear} AS grant_year,
                       {$cofoActualTerm} AS actual_term
                FROM CofO_staging
                WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
            ) s
            WHERE grant_year BETWEEN ? AND ?
        ),
        rofo AS (
            SELECT mlsFNo, grant_date, grant_year, actual_term,
                   ROW_NUMBER() OVER (PARTITION BY mlsFNo ORDER BY grant_year DESC) AS rn
            FROM (
                SELECT mlsFNo, transaction_date AS grant_date, {$rofoYear} AS grant_year,
                       {$rofoActualTerm} AS actual_term
                FROM pra
                WHERE transaction_type IN ({$rofoTypes})
                  AND mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
            ) s
            WHERE grant_year BETWEEN ? AND ?
        ),
        cofo_term AS (
            -- The term recorded on a file's certificate, irrespective of the date window the
            -- cofo CTE applies. MAX because a file can hold up to three CofO rows: where they
            -- disagree, take the longest, since understating a term is what wrongly declares a
            -- live title expired.
            SELECT mlsFNo, MAX({$cofoActualTerm}) AS any_term
            FROM CofO_staging
            WHERE mlsFNo IS NOT NULL AND LTRIM(RTRIM(mlsFNo)) <> ''
            GROUP BY mlsFNo
        ),
        files AS (
            -- file_indexings holds 61 duplicate file numbers; keep the newest row per file.
            SELECT id, file_number, file_title, current_holder, original_holder,
                   plot_number, district, lga, location, land_use_type, term,
                   ROW_NUMBER() OVER (PARTITION BY file_number ORDER BY id DESC) AS rn
            FROM file_indexings
            WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
              -- KANGIS files are not MLS grants and never belong on the due list.
              AND (general_registry IS NULL OR general_registry <> 'KANGIS Registry')
              AND {$notKangis}
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
            {$termSource}                           AS term_source,
            -- Which instrument the commencement date came from, worded as the Legal Search
            -- report badges it so the same file reads the same way in both places.
            CASE WHEN c.grant_year IS NOT NULL THEN 'CofO' ELSE 'RofO' END AS date_source,
            fi.land_use_type                        AS land_use,
            fi.plot_number                          AS plot_no,
            fi.district                             AS district,
            fi.lga                                  AS lga,
            fi.location                             AS location,
            ({$grantYear} + {$term})                AS expiry_year,
            (? - ({$grantYear} + {$term}))          AS years_overdue
        FROM files fi
        LEFT JOIN cofo c      ON c.mlsFNo = fi.file_number AND c.rn = 1
        LEFT JOIN rofo r      ON r.mlsFNo = fi.file_number AND r.rn = 1
        LEFT JOIN cofo_term ct ON ct.mlsFNo = fi.file_number
        WHERE fi.rn = 1
          AND (c.grant_year IS NOT NULL OR r.grant_year IS NOT NULL)
          AND {$term} IS NOT NULL
          AND ({$grantYear} + {$term}) <= ?
          -- A file already carrying a Re-grant is no longer 'to be re-granted'. Either
          -- direction counts: 'Re-granted From' (this file replaced an older one) and
          -- 'Re-granted To' (this file has been replaced) both close the question.
          AND NOT EXISTS (
              SELECT 1 FROM title_status_applications t
              WHERE t.file_no = fi.file_number
                AND t.title_type IN ('Re-grant', 'Re-granted From', 'Re-granted To')
                AND (t.is_deleted IS NULL OR t.is_deleted = 0)
          )
        ORDER BY years_overdue DESC, file_no";

        return DB::connection('sqlsrv')->select($sql, [
            self::MIN_GRANT_YEAR, $asOfYear,   // cofo year window
            self::MIN_GRANT_YEAR, $asOfYear,   // rofo year window
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
        return Cache::remember('regrant:unassessable:v2', now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->computeUnassessableCounts());
    }

    private function computeUnassessableCounts(): array
    {
        $rofoTypes = "'" . implode("','", self::ROFO_TYPES) . "'";

        $cofoCommencement = "COALESCE(NULLIF(LTRIM(RTRIM(cofo_date)), ''), transaction_date)";
        $cofoYear         = self::yearExpression($cofoCommencement);
        $rofoYear         = self::yearExpression('transaction_date');

        // Mirrors the chain in runDueQuery(); the two must agree or the diagnostics would
        // describe a different assessment from the one the list actually performed.
        $recordedTerm   = self::recordedTermExpression('fi.term');
        $standardTerm   = self::termExpression('fi.file_number', 'fi.land_use_type');
        $cofoActualTerm = self::actualTermExpression('period', 'period_unit');
        $rofoActualTerm = self::actualTermExpression('period', 'period_unit');

        $sql = "
        WITH files AS (
            SELECT id, file_number, land_use_type, term,
                   ROW_NUMBER() OVER (PARTITION BY file_number ORDER BY id DESC) AS rn
            FROM file_indexings
            WHERE file_number IS NOT NULL AND LTRIM(RTRIM(file_number)) <> ''
        ),
        matched AS (
            SELECT fi.file_number, fi.land_use_type,
                   (SELECT MAX({$cofoYear}) FROM CofO_staging c WHERE c.mlsFNo = fi.file_number) AS cofo_year,
                   (SELECT MAX({$rofoYear}) FROM pra p WHERE p.mlsFNo = fi.file_number
                        AND p.transaction_type IN ({$rofoTypes})) AS rofo_year,
                   {$recordedTerm} AS recorded_term,
                   COALESCE(
                       (SELECT MAX({$cofoActualTerm}) FROM CofO_staging c WHERE c.mlsFNo = fi.file_number),
                       (SELECT MAX({$rofoActualTerm}) FROM pra p WHERE p.mlsFNo = fi.file_number
                            AND p.transaction_type IN ({$rofoTypes}))
                   ) AS instrument_term,
                   {$standardTerm} AS standard_term
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
                      AND COALESCE(recorded_term, instrument_term, standard_term) IS NULL
                     THEN 1 ELSE 0 END) AS no_term,
            SUM(CASE WHEN COALESCE(recorded_term, instrument_term) IS NOT NULL THEN 1 ELSE 0 END) AS actual_term
        FROM matched";

        $row = DB::connection('sqlsrv')->selectOne($sql);

        return [
            'with_instrument' => (int) ($row->with_instrument ?? 0),
            'no_date'         => (int) ($row->no_date ?? 0),
            'no_term'         => (int) ($row->no_term ?? 0),
            // How many assessments rest on a recorded term rather than an assumed one.
            'actual_term'     => (int) ($row->actual_term ?? 0),
        ];
    }

    /**
     * Human label for a term, used in the UI legend.
     *
     * A term is no longer necessarily 99 or 40 — it is whatever was granted, and the register
     * holds 483 files on some other period entirely. The land-use wording is therefore only
     * attached when the term was inferred from the land use; saying "99 yrs (Residential)"
     * about a term read off the certificate would assert a reason that was never used.
     */
    public static function termLabel(?int $term, ?string $source = null): string
    {
        if ($term === null) {
            return 'Term undetermined';
        }

        if ($source === 'standard') {
            return match ($term) {
                self::TERM_RESIDENTIAL_AGRIC => '99 yrs (Residential / Agricultural)',
                self::TERM_COMMERCIAL_INDUST => '40 yrs (Commercial / Industrial)',
                default => "{$term} yrs (standard term)",
            };
        }

        return "{$term} yrs";
    }

    /** Where a row's term came from, for the provenance badge. */
    public static function termSourceLabel(?string $source): string
    {
        return match ($source) {
            'file'       => 'Actual (file)',
            'instrument' => 'Actual (CofO)',
            'standard'   => 'Standard',
            default      => 'Undetermined',
        };
    }
}
