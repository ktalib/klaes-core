<?php

namespace App\Services\Prs;

use Illuminate\Support\Facades\DB;

/**
 * Official searches (section 09) from file_search_requests.
 *
 * Answers D4. The source report shows ~181 searches for the year, which is far too
 * low for system lookups; file_search_requests holds 890 rows for 2026, which is
 * the right order of magnitude for formal, logged search requests. That is what
 * this section counts.
 *
 * The table has NO gender and NO land-use column, so unlike every other section
 * this one renders a single dimension. That is honest rather than a gap to fill:
 * the source table's own "gender" columns counted 125 against 181 land-use rows
 * and never reconciled (docs/prs-2025/09-search.md).
 */
class SearchStats
{
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
            SELECT MONTH(created_at) AS month,
                   COALESCE(NULLIF(LTRIM(RTRIM(status)), ''), 'Unspecified') AS status,
                   COUNT(*) AS n
            FROM file_search_requests
            WHERE YEAR(created_at) = ?
            GROUP BY MONTH(created_at), status
        ";

        $months  = array_fill(0, 12, 0);
        $byState = [];
        $total   = 0;

        foreach ($this->conn()->select($sql, [$year]) as $r) {
            $i = ((int) $r->month) - 1;

            if ($i < 0 || $i > 11) {
                continue;
            }

            $n = (int) $r->n;
            $total += $n;
            $months[$i] += $n;

            $label = ucfirst(strtolower(str_replace(['_', '-'], ' ', (string) $r->status)));
            $byState[$label] ??= array_fill(0, 12, 0);
            $byState[$label][$i] += $n;
        }

        arsort($byState);

        return ['months' => $months, 'status' => $byState, 'total' => $total];
    }

    public function availableYears(): array
    {
        $years = [];

        foreach ($this->conn()->select("
            SELECT YEAR(created_at) AS y, COUNT(*) AS n
            FROM file_search_requests
            WHERE created_at IS NOT NULL
            GROUP BY YEAR(created_at)
        ") as $r) {
            if ($r->y >= 1970 && $r->y <= (int) date('Y') + 1) {
                $years[(int) $r->y] = (int) $r->n;
            }
        }

        return $years;
    }
}
