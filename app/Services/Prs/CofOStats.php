<?php

namespace App\Services\Prs;

use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Certificates of Occupancy (section 05) from CofO_staging.
 *
 * Kept out of DeedRegistrationStats because CofO_staging is its own table with its
 * own date column and is not part of the three-table deed union.
 *
 * Date basis: cofo_date -> deeds_date -> transaction_date, all nvarchar, all via
 * TRY_CONVERT. 5,319 of the 24,448 non-null cofo_date values do not parse
 * (docs/prs-2025/20-live-data-implementation.md §6) — undated() reports how many
 * were excluded so the shortfall is visible on the page rather than silent.
 */
class CofOStats
{
    public function __construct(private LandUseNormalizer $landUse)
    {
    }

    private function conn()
    {
        return DB::connection('sqlsrv');
    }

    /** Capture date, per the client decision of 2026-08-03. See DeedRegistrationStats. */
    private function dateExpr(string $a = 'c'): string
    {
        return "$a.created_at";
    }

    private array $memo = [];

    public function monthly(int $year): array
    {
        return $this->memo[$year] ??= $this->computeMonthly($year);
    }

    private function computeMonthly(int $year): array
    {
        $d = $this->dateExpr();

        // fi.file_number is left bare so the join stays sargable — wrapping the
        // indexed column in LTRIM/RTRIM forces a full scan. See DeedRegistrationStats.
        $fn = "COALESCE(NULLIF(c.mlsFNo,''), NULLIF(c.kangisFileNo,''), NULLIF(c.NewKANGISFileno,''), NULLIF(c.fileno,''), '')";
        $lu = $this->landUse->sqlEffectiveLandUse('c.land_use', $fn);

        $sql = "
            SELECT MONTH($d) AS month, $lu AS land_use, fi.gender, COUNT(*) AS n
            FROM CofO_staging c
            LEFT JOIN file_indexings fi
                   ON fi.file_number = LTRIM(RTRIM($fn))
            WHERE ISNULL(c.is_deleted, 0) = 0
              AND YEAR($d) = ?
            GROUP BY MONTH($d), $lu, fi.gender
        ";

        $landuse = [];
        $gender  = [];
        $months  = array_fill(0, 12, 0);
        $total   = 0;
        $missing = 0;

        foreach ($this->conn()->select($sql, [$year]) as $r) {
            $i = ((int) $r->month) - 1;

            if ($i < 0 || $i > 11) {
                continue;
            }

            $n = (int) $r->n;
            $total += $n;
            $months[$i] += $n;

            $lu = $this->landUse->normalize($r->land_use);
            $landuse[$lu] ??= array_fill(0, 12, 0);
            $landuse[$lu][$i] += $n;

            $g = $r->gender ?: 'Not Recorded';
            $gender[$g] ??= array_fill(0, 12, 0);
            $gender[$g][$i] += $n;

            if ($g === 'Not Recorded') {
                $missing += $n;
            }
        }

        return [
            'months'   => $months,
            'landuse'  => $landuse,
            'gender'   => $gender,
            'total'    => $total,
            'coverage' => [
                'gender_resolved' => $total - $missing,
                'gender_missing'  => $missing,
                'undated'         => $this->undated(),
            ],
        ];
    }

    /** Rows with no capture date at all — excluded from every year. */
    public function undated(): int
    {
        return (int) $this->conn()->selectOne("
            SELECT COUNT(*) AS n
            FROM CofO_staging c
            WHERE ISNULL(c.is_deleted, 0) = 0
              AND c.created_at IS NULL
        ")->n;
    }

    public function availableYears(): array
    {
        $d     = $this->dateExpr();
        $years = [];

        foreach ($this->conn()->select("
            SELECT YEAR($d) AS y, COUNT(*) AS n
            FROM CofO_staging c
            WHERE ISNULL(c.is_deleted,0)=0 AND $d IS NOT NULL
            GROUP BY YEAR($d)
        ") as $r) {
            if ($r->y >= 1970 && $r->y <= (int) date('Y') + 1) {
                $years[(int) $r->y] = (int) $r->n;
            }
        }

        return $years;
    }
}
