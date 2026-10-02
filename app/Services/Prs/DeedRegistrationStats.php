<?php

namespace App\Services\Prs;

use App\Services\Prs\Support\BankNameNormalizer;
use App\Services\Prs\Support\InstrumentTypeNormalizer;
use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Every Deeds Department section, from `deed_registrations` — the register the
 * Deeds Registration module writes (InstrumentRegistrationController).
 *
 * SOURCE (client decision, 2026-08-05). This class previously unioned `pra`,
 * `file_history_staging` and `deed_registrations`. The first two are back-capture
 * staging tables: 133,939 rows of historical instruments keyed in over the years,
 * not registrations the department performed. A progress report that counts them
 * is reporting a data-entry backlog as departmental output. The union is gone;
 * `deed_registrations` is the only source, and every row on this page is now a
 * registration that actually passed through the registry.
 *
 * DATE BASIS (client decision, 2026-08-05). `deeds_date` — the date the deed
 * entered the register. Not `created_at`, which had been the basis since
 * 2026-08-03 and dated a registration by when its row was written rather than by
 * when it was registered. `deeds_date` is a real `date` column here, so none of
 * the TRY_CONVERT machinery the nvarchar staging tables needed applies.
 *
 * GENDER (D2). `deed_registrations` has no gender column, so gender is still
 * reached by joining the file number to `file_indexings.gender`. Coverage on this
 * source is poor and the page says so: 8,939 of the 9,502 rows are Occupancy
 * Permits carrying TEMP-##### file numbers, which do not exist in file_indexings.
 * Those rows report Not Recorded rather than being dropped.
 *
 * NO DEDUPLICATION. The old union deduplicated on (file number, date) because the
 * same registration could appear in two staging tables. With one source that key
 * is actively wrong: `registration_number` is distinct on all 9,502 rows, while
 * (fileno, deeds_date, instrument_type) collapses to 9,437 — the 65 rows it would
 * discard are separate register entries, mostly ST Fragmentation units registered
 * against one parent file on one day.
 */
class DeedRegistrationStats
{
    /**
     * The date basis, in one place. Every query in this class dates a registration
     * by when it was entered in the register.
     */
    private const DATE = 'd.deeds_date';

    public function __construct(
        private InstrumentTypeNormalizer $instruments,
        private LandUseNormalizer $landUse,
        private BankNameNormalizer $banks,
    ) {
    }

    private function conn()
    {
        return DB::connection('sqlsrv');
    }

    /** Per-request memo. sections() and highlights() ask for the same cuts. */
    private array $memo = [];

    /**
     * Monthly counts for one instrument group, cut by land use and by gender.
     *
     * @return array{months: array, landuse: array, gender: array, total: int, coverage: array}
     */
    public function monthly(string $group, int $year): array
    {
        return $this->memo["$group|$year"] ??= $this->computeMonthly($group, $year);
    }

    private function computeMonthly(string $group, int $year): array
    {
        $rows = $this->conn()->select($this->monthlySql($group), [$year]);

        $landuse  = [];
        $gender   = [];
        $months   = array_fill(0, 12, 0);
        $total    = 0;
        $noDate   = 0;
        $noGender = 0;

        foreach ($rows as $r) {
            $n = (int) $r->n;
            $i = $r->month === null ? -1 : ((int) $r->month) - 1;

            // Rows with no deeds_date at all. Reported on the section rather than
            // dropped in silence — an undated registration is a data fault worth
            // seeing, not a row that never happened.
            if ($i < 0 || $i > 11) {
                $noDate += $n;
                continue;
            }

            $total += $n;
            $months[$i] += $n;

            $lu = $this->landUse->normalize($r->land_use);
            $landuse[$lu] ??= array_fill(0, 12, 0);
            $landuse[$lu][$i] += $n;

            $g = $r->gender ?: 'Not Recorded';
            $gender[$g] ??= array_fill(0, 12, 0);
            $gender[$g][$i] += $n;

            if ($g === 'Not Recorded') {
                $noGender += $n;
            }
        }

        return [
            'months'   => $months,
            'landuse'  => $landuse,
            'gender'   => $gender,
            'total'    => $total,
            'coverage' => [
                'gender_resolved' => $total - $noGender,
                'gender_missing'  => $noGender,
                'undated'         => $noDate,
            ],
        ];
    }

    /**
     * Mortgagee ranking for section 04. Counts facilities, not value — the source
     * report ranks by number of registered mortgages and reconciles to the mortgage
     * total, which is the one cross-table check in the Deeds report that holds.
     *
     * `deed_registrations` has no Mortgagee column, only grantor and grantee. In a
     * mortgage the mortgagor grants to the mortgagee, so grantee is the lender —
     * but the register contains rows entered the other way round (JAIZ BANK PLC ->
     * JAMAL BALA). Whichever side normalises to an institution is taken as the
     * mortgagee, falling back to grantee when neither does, which is the private-
     * mortgage case reported separately below.
     */
    public function bankRanking(int $year, int $limit = 12): array
    {
        $sql = "
            SELECT d.grantor, d.grantee, COUNT(*) AS n
            FROM deed_registrations d
            WHERE ISNULL(d.is_deleted, 0) = 0
              AND " . $this->instruments->sqlPredicate(InstrumentTypeNormalizer::MORTGAGE, 'd.instrument_type') . "
              AND YEAR(" . self::DATE . ") = ?
            GROUP BY d.grantor, d.grantee
        ";

        $institutions = [];
        $private      = 0;
        $privatePayees = [];

        foreach ($this->conn()->select($sql, [$year]) as $r) {
            $grantor = $this->banks->normalize($r->grantor);
            $grantee = $this->banks->normalize($r->grantee);

            $name = match (true) {
                $this->banks->isInstitution($grantee) => $grantee,
                $this->banks->isInstitution($grantor) => $grantor,
                default                               => $grantee,
            };

            if ($name === null) {
                continue;
            }

            $n = (int) $r->n;

            if ($this->banks->isInstitution($name)) {
                $institutions[$name] = ($institutions[$name] ?? 0) + $n;
                continue;
            }

            // Private mortgagee — an individual holding the security. Counted,
            // but never ranked among the banks. See BankNameNormalizer.
            $private += $n;
            $privatePayees[$name] = true;
        }

        arsort($institutions);

        return [
            'institutions'   => array_slice($institutions, 0, $limit, true),
            'private'        => $private,
            'private_payees' => count($privatePayees),
        ];
    }

    /** Years that actually carry deed activity, so the year selector is data-driven. */
    public function availableYears(): array
    {
        $sql = "
            SELECT YEAR(" . self::DATE . ") AS y, COUNT(*) AS n
            FROM deed_registrations d
            WHERE ISNULL(d.is_deleted, 0) = 0
              AND " . self::DATE . " IS NOT NULL
            GROUP BY YEAR(" . self::DATE . ")
        ";

        $years = [];

        foreach ($this->conn()->select($sql) as $r) {
            if ($r->y >= 1970 && $r->y <= (int) date('Y') + 1) {
                $years[(int) $r->y] = (int) $r->n;
            }
        }

        return $years;
    }

    // ── SQL construction ────────────────────────────────────────────────────

    /**
     * One group, grouped to (month, land use, gender, count).
     *
     * `deed_registrations` has no land_use column, so the land use is recovered
     * from the file-number prefix (RES-2026-640, CON-COM-2006-74). Legacy and
     * temporary numbers carry none and land in Uncategorised rather than being
     * guessed at — which is most of the Occupancy Permit section, whose rows are
     * numbered TEMP-#####.
     */
    private function monthlySql(string $group): string
    {
        $where = $this->instruments->sqlPredicate($group, 'd.instrument_type');
        $fn    = "COALESCE(NULLIF(d.fileno, ''), NULLIF(d.parent_fileno, ''), '')";
        $lu    = $this->landUse->sqlEffectiveLandUse('NULL', $fn);

        return "
            SELECT MONTH(x.d) AS month,
                   x.land_use,
                   fi.gender,
                   COUNT(*) AS n
            FROM (
                SELECT " . self::DATE . " AS d,
                       $lu AS land_use,
                       LTRIM(RTRIM($fn)) AS fileno
                FROM deed_registrations d
                WHERE ISNULL(d.is_deleted, 0) = 0
                  AND $where
                  AND (YEAR(" . self::DATE . ") = ? OR " . self::DATE . " IS NULL)
            ) x
            -- fi.file_number is NOT wrapped in LTRIM/RTRIM on purpose. Applying a
            -- function to the indexed column makes the predicate non-sargable and
            -- forces a full scan — the same mistake that timed out the file log
            -- table. x.fileno is already trimmed in the derived table.
            LEFT JOIN file_indexings fi
                   ON fi.file_number = x.fileno
                  AND x.fileno <> ''
            GROUP BY MONTH(x.d), x.land_use, fi.gender
        ";
    }
}
