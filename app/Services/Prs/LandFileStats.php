<?php

namespace App\Services\Prs;

use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * The Land Department sections, and the Deeds occupancy-permit section, from
 * mls_file_no.
 *
 * THIS CONTRADICTS THE ORIGINAL PLAN, DELIBERATELY. 11-implementation-plan.md made
 * it the "key structural point" that sections 12-16 come from oss_applications
 * pivoted five ways. Measured against the database that does not work:
 * oss_applications holds 350 rows, land_use is null in every one of them, and
 * application_type holds 'residential'/'commercial' rather than the expected
 * Direct Allocation/Conversion stream.
 *
 * mls_file_no carries the stream in `source` and the land use in `land_use`:
 *
 *   Conversion            3,127   section 12
 *   Direct Allocation     1,882   section 13
 *   OP Resettlement         895   section 06
 *   OP Direct Allocation     87   section 06
 *
 * That also settles D5 and D8: "direct allocation" (Lands) and "resettlement
 * allocation" (Deeds) are different source values on one table and must never be
 * conflated, which is exactly the confusion the 6,798 vs 6,047 pair caused.
 *
 * Gender lives on this table directly — no join needed, unlike the deed sections.
 */
class LandFileStats
{
    public const CONVERSION        = ['Conversion'];
    public const DIRECT_ALLOCATION = ['Direct Allocation'];
    public const OCCUPANCY_PERMIT  = ['OP Resettlement', 'OP Direct Allocation'];

    public function __construct(private LandUseNormalizer $landUse)
    {
    }

    private function conn()
    {
        return DB::connection('sqlsrv');
    }

    /** Per-request memo. sections() and highlights() ask for the same cuts. */
    private array $memo = [];

    /**
     * Monthly counts for one or more `source` streams, cut by land use and gender.
     *
     * @param string[] $sources
     */
    public function monthly(array $sources, int $year): array
    {
        return $this->memo[implode(",", $sources)."|$year"] ??= $this->computeMonthly($sources, $year);
    }

    private function computeMonthly(array $sources, int $year): array
    {
        $in  = implode(',', array_fill(0, count($sources), '?'));
        $sql = "
            SELECT MONTH(m.created_at) AS month, m.land_use, m.gender,
                   m.source, m.customer_type, COUNT(*) AS n
            FROM mls_file_no m
            WHERE ISNULL(m.is_deleted, 0) = 0
              AND m.source IN ($in)
              AND YEAR(m.created_at) = ?
            GROUP BY MONTH(m.created_at), m.land_use, m.gender, m.source, m.customer_type
        ";

        $landuse = [];
        $gender  = [];
        $streams = [];
        $months  = array_fill(0, 12, 0);
        $total   = 0;
        $missing = 0;

        foreach ($this->conn()->select($sql, [...$sources, $year]) as $r) {
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

            $streams[$r->source] ??= array_fill(0, 12, 0);
            $streams[$r->source][$i] += $n;

            if ($g === 'Not Recorded') {
                $missing += $n;
            }
        }

        return [
            'months'   => $months,
            'landuse'  => $landuse,
            'gender'   => $gender,
            'streams'  => $streams,
            'total'    => $total,
            'coverage' => [
                'gender_resolved' => $total - $missing,
                'gender_missing'  => $missing,
            ],
        ];
    }

    /**
     * Stream x gender cross-tab for section 14.
     *
     * The source version of this table is unusable — transposed rows and a land-use
     * total pasted into a gender cell (docs/prs-2025/14-land-gender-allocation.md).
     * Cutting one query two ways instead of hand-building a matrix is what makes
     * that class of error structurally impossible: the rows sum to the same total
     * as section 12 and 13 by construction.
     *
     * @return array{rows: array<string, array<string,int>>, genders: string[]}
     */
    public function streamByGender(int $year): array
    {
        $sql = "
            SELECT m.source, m.gender, COUNT(*) AS n
            FROM mls_file_no m
            WHERE ISNULL(m.is_deleted, 0) = 0
              AND YEAR(m.created_at) = ?
              AND m.source IS NOT NULL
            GROUP BY m.source, m.gender
        ";

        $rows    = [];
        $genders = [];

        foreach ($this->conn()->select($sql, [$year]) as $r) {
            $g = $r->gender ?: 'Not Recorded';

            $rows[$r->source] ??= [];
            $rows[$r->source][$g] = ($rows[$r->source][$g] ?? 0) + (int) $r->n;
            $genders[$g] = true;
        }

        uasort($rows, fn ($a, $b) => array_sum($b) <=> array_sum($a));

        return ['rows' => $rows, 'genders' => array_keys($genders)];
    }

    public function availableYears(): array
    {
        $years = [];

        foreach ($this->conn()->select("
            SELECT YEAR(created_at) AS y, COUNT(*) AS n
            FROM mls_file_no
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
