<?php

namespace App\Services\Prs;

use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * OSS applications (sections 15 and 16) from oss_applications.
 *
 * Two warnings, both measured 2026-08-02 and both material:
 *
 *  1. VOLUME. 350 rows, against 2,315 in the 2025 source report. This section is
 *     thin, and the module must not imply otherwise.
 *  2. land_use IS NULL IN ALL 350 ROWS. The category cut therefore comes from
 *     application_type, which — despite its name and the enum documented in
 *     CommissionNewSTController — actually contains land-use values
 *     ('residential' 334, 'commercial' 16), not the application stream.
 *
 * sex is the exception that makes this section worth building: 334 of 350 rows
 * carry it, 95.4% — by far the best gender coverage in the database, and captured
 * on a form rather than inferred. It answers D11 too: 16 rows are null, so the
 * source spreadsheet's perfect gender reconciliation was never achievable from
 * this data and unknowns were being absorbed somewhere.
 *
 * Do not build this on the OSS applications *page* — that page is known to crash
 * Chrome (1,818 districts x ~23 duplicated selects). Aggregate server-side.
 */
class OssStats
{
    public function __construct(private LandUseNormalizer $landUse)
    {
    }

    private function conn()
    {
        return DB::connection('sqlsrv');
    }

    private array $memo = [];

    public function monthly(int $year): array
    {
        return $this->memo[$year] ??= $this->computeMonthly($year);
    }

    private function computeMonthly(int $year): array
    {
        $sql = "
            SELECT MONTH(created_at) AS month, application_type, sex, COUNT(*) AS n
            FROM oss_applications
            WHERE ISNULL(is_deleted, 0) = 0
              AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at), application_type, sex
        ";

        $months  = array_fill(0, 12, 0);
        $landuse = [];
        $gender  = [];
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

            $lu = $this->landUse->normalize($r->application_type);
            $landuse[$lu] ??= array_fill(0, 12, 0);
            $landuse[$lu][$i] += $n;

            $g = match (strtoupper(trim((string) $r->sex))) {
                'MALE', 'M'   => 'Male',
                'FEMALE', 'F' => 'Female',
                default       => 'Not Recorded',
            };

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
            ],
        ];
    }

    public function availableYears(): array
    {
        $years = [];

        foreach ($this->conn()->select("
            SELECT YEAR(created_at) AS y, COUNT(*) AS n
            FROM oss_applications
            WHERE ISNULL(is_deleted,0)=0 AND created_at IS NOT NULL
            GROUP BY YEAR(created_at)
        ") as $r) {
            if ($r->y >= 1970 && $r->y <= (int) date('Y') + 1) {
                $years[(int) $r->y] = (int) $r->n;
            }
        }

        return $years;
    }
}
