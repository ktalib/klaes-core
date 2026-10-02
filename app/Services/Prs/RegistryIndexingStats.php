<?php

namespace App\Services\Prs;

use App\Services\Prs\Support\LandUseNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * KANGIS and SLTR — the two indexing registries. Added 2026-08-17.
 *
 * Both sit in `file_indexings` under their own `registry` value, and both are
 * INDEXING ONLY. There is no commissioning side to pair them with: the dashboard
 * hardcodes KANGIS and SLTR commissioned to zero (Api\DashboardController) because
 * neither programme has started issuing file numbers through KLAES. So each gets
 * one section, not the two-measure treatment ST needed — there is no second
 * measure to be confused with, and inventing one would repeat exactly the
 * OP-files-vs-OP-registrations trap the report already navigates.
 *
 * DATE BASIS IS `created_at`, LABELLED "Date indexed". `date_created` is NULL on
 * every KANGIS and SLTR row, so there is no registry-act date to prefer the way
 * the deed sections prefer deeds_date. What the section counts is the act of
 * indexing the file, and it says so on its face.
 *
 * DIMENSIONS, measured 2026-08-17:
 *
 *   KANGIS  3,950 rows   land use 98.9%   gender 46.1%   LGA 100%
 *   SLTR    6,037 rows   land use 99.8%   gender  3.3%   LGA 100%
 *
 * Hence the two registries carry different secondary panels, decided in
 * PrsReportAggregator rather than here: KANGIS charts gender like every other
 * section, SLTR charts LGA. A gender panel on SLTR would be one grey bar — 5,837
 * of 6,037 rows are Not Recorded — and would say less than nothing. The gender
 * TABLE is still emitted for both, so the coverage gap is visible rather than
 * quietly dropped.
 *
 * LGA is the right cut for SLTR on its merits, not just as a fallback: systematic
 * titling runs LGA by LGA, so the LGA split is the shape of the programme the way
 * blocks-to-units is the shape of ST.
 */
class RegistryIndexingStats
{
    /**
     * The registry values each stream answers to.
     *
     * KANGIS matches two spellings because both exist and the dashboard counts
     * both; matching only 'KANGIS' would silently undercount.
     */
    public const KANGIS = ['KANGIS', 'KANGIS Registry'];
    public const SLTR   = ['SLTR'];

    /**
     * LGAs charted individually before the tail is folded into Other.
     *
     * Seven, not eight: the palette holds eight hues and Other LGAs takes the
     * eighth (grey). An eighth named LGA would force the ninth series to reuse a
     * colour, and colour follows the entity on this page — a repeated colour reads
     * as the same entity twice.
     */
    public const LGA_LIMIT = 7;

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
     * Monthly indexing counts for one registry, cut by land use, gender and LGA.
     *
     * @param string[] $registries
     */
    public function monthly(array $registries, int $year): array
    {
        return $this->memo[implode(',', $registries) . "|$year"]
            ??= $this->computeMonthly($registries, $year);
    }

    private function computeMonthly(array $registries, int $year): array
    {
        $in = implode(',', array_fill(0, count($registries), '?'));

        $sql = "
            SELECT MONTH(created_at) AS month, land_use_type, gender, lga, COUNT(*) AS n
            FROM file_indexings
            WHERE ISNULL(is_deleted, 0) = 0
              AND registry IN ($in)
              AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at), land_use_type, gender, lga
        ";

        $months  = array_fill(0, 12, 0);
        $landuse = [];
        $gender  = [];
        $lga     = [];
        $total   = 0;
        $missing = 0;

        foreach ($this->conn()->select($sql, [...$registries, $year]) as $r) {
            $i = ((int) $r->month) - 1;

            if ($i < 0 || $i > 11) {
                continue;
            }

            $n = (int) $r->n;
            $total += $n;
            $months[$i] += $n;

            $lu = $this->landUse->normalize($r->land_use_type);
            $landuse[$lu] ??= array_fill(0, 12, 0);
            $landuse[$lu][$i] += $n;

            // file_indexings.gender already holds the canonical vocabulary the
            // report uses — Male, Female, Corporate, Joint — because the
            // gender:backfill wrote it. Only the null case needs a name.
            $g = trim((string) $r->gender) ?: 'Not Recorded';
            $gender[$g] ??= array_fill(0, 12, 0);
            $gender[$g][$i] += $n;

            if ($g === 'Not Recorded') {
                $missing += $n;
            }

            $area = trim((string) $r->lga) ?: 'Not Recorded';
            $lga[$area] ??= array_fill(0, 12, 0);
            $lga[$area][$i] += $n;
        }

        return [
            'months'   => $months,
            'landuse'  => $landuse,
            'gender'   => $gender,
            'lga'      => $this->topLgas($lga),
            'lga_count' => count($lga),
            'total'    => $total,
            'coverage' => [
                'gender_resolved' => $total - $missing,
                'gender_missing'  => $missing,
            ],
        ];
    }

    /**
     * The busiest LGAs, with the tail folded into one Other series.
     *
     * Kano has 44 LGAs. Charting all of them stacked would produce a legend longer
     * than the chart and force the palette to repeat colours, which the section
     * rules forbid — colour follows the entity, so a colour used twice is a lie.
     */
    private function topLgas(array $lga): array
    {
        uasort($lga, fn ($a, $b) => array_sum($b) <=> array_sum($a));

        $top  = array_slice($lga, 0, self::LGA_LIMIT, true);
        $tail = array_slice($lga, self::LGA_LIMIT, null, true);

        if ($tail !== []) {
            $other = array_fill(0, 12, 0);

            foreach ($tail as $months) {
                foreach ($months as $i => $n) {
                    $other[$i] += $n;
                }
            }

            $top['Other LGAs'] = $other;
        }

        return $top;
    }

    /**
     * Years carrying indexing activity, across both registries at once.
     *
     * Both are 2026-only today, so this contributes a single year to the selector —
     * which is the point of keeping the selector data-driven rather than hardcoded.
     */
    public function availableYears(): array
    {
        $registries = array_merge(self::KANGIS, self::SLTR);
        $in         = implode(',', array_fill(0, count($registries), '?'));

        $years = [];

        foreach ($this->conn()->select("
            SELECT YEAR(created_at) AS y, COUNT(*) AS n
            FROM file_indexings
            WHERE ISNULL(is_deleted,0)=0 AND created_at IS NOT NULL
              AND registry IN ($in)
            GROUP BY YEAR(created_at)
        ", $registries) as $r) {
            if ($r->y >= 1970 && $r->y <= (int) date('Y') + 1) {
                $years[(int) $r->y] = (int) $r->n;
            }
        }

        return $years;
    }
}
