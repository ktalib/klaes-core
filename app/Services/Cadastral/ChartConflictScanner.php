<?php

namespace App\Services\Cadastral;

use App\Models\Cadastral\CadastralChart;
use Illuminate\Support\Collection;

/**
 * Finding two charts that describe the same ground.
 *
 * TWO KINDS OF CONFLICT, AND THE DIFFERENCE MATTERS.
 *
 *  1. IDENTITY — two live charts sharing plot + block + layout, or sharing an
 *     approved plan number. This works on day one against data officers already
 *     key, and it is what catches a double allocation in practice.
 *
 *  2. GEOMETRIC — two beacon rings that actually overlap. Correct, but its
 *     coverage equals however many coordinate lists have been keyed, which is
 *     zero at go-live. It is offered, not relied on.
 *
 * The concept note asks for "automated boundary conflict detection". This is the
 * honest version of it: KLAES has no parcel geometry, no PostGIS and no polygon
 * storage, so nothing can compare boundaries that were never captured.
 */
class ChartConflictScanner
{
    public function __construct(private AreaCalculator $area = new AreaCalculator()) {}

    /**
     * Conflicts for one chart.
     *
     * @return array<int, array{type: string, chart: CadastralChart, reason: string}>
     */
    public function scan(CadastralChart $chart): array
    {
        return array_merge(
            $this->identityConflicts($chart),
            $this->geometricConflicts($chart),
        );
    }

    /**
     * Same plot/block/layout, or same approved plan number, on another live
     * chart for a different file.
     */
    public function identityConflicts(CadastralChart $chart): array
    {
        $out = [];

        $identity = $chart->identityKey();

        if ($identity !== null && trim((string) $chart->plot_no) !== '') {
            $rivals = CadastralChart::query()
                ->current()
                ->where('id', '!=', $chart->id)
                ->where('file_number', '!=', $chart->file_number)
                ->where('plot_no', $chart->plot_no)
                ->when($chart->block_no, fn ($q) => $q->where('block_no', $chart->block_no))
                ->when($chart->layout_name, fn ($q) => $q->where('layout_name', $chart->layout_name))
                ->limit(25)
                ->get();

            foreach ($rivals as $rival) {
                $out[] = [
                    'type'   => 'identity',
                    'chart'  => $rival,
                    'reason' => 'Same plot/block/layout charted under ' . $rival->file_number . '.',
                ];
            }
        }

        if (trim((string) $chart->approved_plan_no) !== '') {
            $rivals = CadastralChart::query()
                ->current()
                ->where('id', '!=', $chart->id)
                ->where('file_number', '!=', $chart->file_number)
                ->where('approved_plan_no', $chart->approved_plan_no)
                ->limit(25)
                ->get();

            foreach ($rivals as $rival) {
                $out[] = [
                    'type'   => 'identity',
                    'chart'  => $rival,
                    'reason' => 'Approved plan ' . $chart->approved_plan_no . ' is also charted under ' . $rival->file_number . '.',
                ];
            }
        }

        return $out;
    }

    /**
     * Overlapping beacon rings.
     *
     * Bounded to charts in the same layout, because comparing every ring against
     * every other is quadratic and the layout is the only cheap locality this
     * database offers.
     */
    public function geometricConflicts(CadastralChart $chart): array
    {
        $mine = $this->ringOf($chart);

        if (count($mine) < 3 || trim((string) $chart->layout_name) === '') {
            return [];
        }

        $neighbours = CadastralChart::query()
            ->current()
            ->with('coordinates')
            ->where('id', '!=', $chart->id)
            ->where('file_number', '!=', $chart->file_number)
            ->where('layout_name', $chart->layout_name)
            ->limit(200)
            ->get();

        $out = [];

        foreach ($neighbours as $rival) {
            $theirs = $this->ringOf($rival);

            if (count($theirs) < 3) {
                continue;
            }

            if (! $this->boxesTouch($mine, $theirs)) {
                continue;   // cheap reject before the point-in-polygon work
            }

            if ($this->ringsOverlap($mine, $theirs)) {
                $out[] = [
                    'type'   => 'geometric',
                    'chart'  => $rival,
                    'reason' => 'Beacon ring overlaps the parcel charted under ' . $rival->file_number . '.',
                ];
            }
        }

        return $out;
    }

    /**
     * Every live chart with at least one identity conflict, for the conflicts
     * screen. Grouped in SQL rather than in PHP: 170k files means the naive
     * version would load the table.
     *
     * @return Collection<int, object>
     */
    public function identityClusters(int $limit = 100): Collection
    {
        return CadastralChart::query()
            ->current()
            ->selectRaw('plot_no, block_no, layout_name, COUNT(*) as chart_count, COUNT(DISTINCT file_number) as file_count')
            ->whereNotNull('plot_no')
            ->where('plot_no', '!=', '')
            ->groupBy('plot_no', 'block_no', 'layout_name')
            ->havingRaw('COUNT(DISTINCT file_number) > 1')
            ->orderByRaw('COUNT(DISTINCT file_number) DESC')
            ->limit($limit)
            ->get();
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function ringOf(CadastralChart $chart): array
    {
        $ring = [];

        foreach ($chart->coordinates as $c) {
            if ($c->easting === null || $c->northing === null) {
                continue;
            }

            $ring[] = [(float) $c->easting, (float) $c->northing];
        }

        return $ring;
    }

    /** Bounding boxes intersect — a cheap pre-filter, not a conclusion. */
    private function boxesTouch(array $a, array $b): bool
    {
        [$aMinX, $aMaxX, $aMinY, $aMaxY] = $this->box($a);
        [$bMinX, $bMaxX, $bMinY, $bMaxY] = $this->box($b);

        return $aMinX <= $bMaxX && $bMinX <= $aMaxX
            && $aMinY <= $bMaxY && $bMinY <= $aMaxY;
    }

    private function box(array $ring): array
    {
        $xs = array_column($ring, 0);
        $ys = array_column($ring, 1);

        return [min($xs), max($xs), min($ys), max($ys)];
    }

    /**
     * Overlap test: any vertex of one ring inside the other, or any pair of
     * edges crossing.
     *
     * Not a full polygon-clipping library. It catches the case that matters —
     * two parcels sharing ground — and does not attempt to measure how much.
     */
    private function ringsOverlap(array $a, array $b): bool
    {
        foreach ($a as $p) {
            if ($this->pointInRing($p, $b)) return true;
        }

        foreach ($b as $p) {
            if ($this->pointInRing($p, $a)) return true;
        }

        $na = count($a);
        $nb = count($b);

        for ($i = 0; $i < $na; $i++) {
            $a1 = $a[$i];
            $a2 = $a[($i + 1) % $na];

            for ($j = 0; $j < $nb; $j++) {
                $b1 = $b[$j];
                $b2 = $b[($j + 1) % $nb];

                if ($this->segmentsCross($a1, $a2, $b1, $b2)) return true;
            }
        }

        return false;
    }

    /** Ray casting. */
    private function pointInRing(array $p, array $ring): bool
    {
        $inside = false;
        $n      = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            $straddles = ($yi > $p[1]) !== ($yj > $p[1]);

            if ($straddles && $p[0] < (($xj - $xi) * ($p[1] - $yi) / (($yj - $yi) ?: 1e-12)) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    private function segmentsCross(array $p1, array $p2, array $q1, array $q2): bool
    {
        $d1 = $this->cross($q1, $q2, $p1);
        $d2 = $this->cross($q1, $q2, $p2);
        $d3 = $this->cross($p1, $p2, $q1);
        $d4 = $this->cross($p1, $p2, $q2);

        return (($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0))
            && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0));
    }

    private function cross(array $a, array $b, array $c): float
    {
        return (($b[0] - $a[0]) * ($c[1] - $a[1])) - (($b[1] - $a[1]) * ($c[0] - $a[0]));
    }
}
