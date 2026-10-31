<?php

namespace App\Services\Survey;

/**
 * Resettlement Calculator (docs/KLAES Survey Module – Workflow_V02.md 2.3; SG formulas, 29 Oct 2026):
 *
 *   plots = net area (ha) ÷ plot size (ha) for the land use / purpose and density
 *   net area = area × (1 − roads & open space %)
 *
 * Agreed defaults (user, 29 Oct 2026), the SG may change them:
 *   - fractions are rounded DOWN (a part-plot cannot be allocated); the exact figure is shown;
 *   - the area is used as the SG wrote it (0% deduction); planners may enter a deduction;
 *   - on Land-to-Land projects the current 50:50 split applies: odd plot to Government,
 *     as SurveyCompCase::plotSplit().
 */
class ResettlementCalculator
{
    public const DEFAULT_ROUNDING  = 'down';
    public const DEFAULT_DEDUCTION = 0.0;

    /** The land uses offered, in full (user, 2026-10-30): only these four. */
    public const LAND_USES = ['Residential', 'Commercial', 'Agricultural', 'Industrial'];

    /**
     * Purposes per land use (Residential => [RESIDENTIAL, HIGH DENSITY, ...]) from the KLAES
     * purposes lookup, as on the file-number forms (user, 2026-10-30).
     */
    public static function purposes(): array
    {
        $ids = \App\Models\LandUse::query()->pluck('id', 'landuse')->mapWithKeys(fn ($id, $n) => [mb_strtoupper(trim($n)) => $id]);
        $byLandUse = \App\Models\Purpose::query()->orderBy('id')->get(['name', 'landuseid'])->groupBy('landuseid');
        $out = [];
        foreach (self::LAND_USES as $lu) {
            $out[$lu] = ($byLandUse[$ids[mb_strtoupper($lu)] ?? 0] ?? collect())->pluck('name')->map(fn ($n) => trim($n))->unique()->values()->all();
        }

        return $out;
    }

    /** Is $purpose one of $landUse's purposes? Returns the listed spelling, or null. */
    public static function purposeOf(string $landUse, ?string $purpose): ?string
    {
        $want = mb_strtoupper(trim((string) $purpose));

        return collect(self::purposes()[$landUse] ?? [])->first(fn ($p) => mb_strtoupper($p) === $want);
    }

    public const ROUNDINGS = ['down' => 'Round down', 'nearest' => 'Nearest whole plot', 'up' => 'Round up'];

    /**
     * @return array{net_area:float, exact:float, plots:int, split:bool, farmer:?int, government:?int}
     */
    public function calculate(float $areaHa, float $plotSizeHa, float $deductionPercent = self::DEFAULT_DEDUCTION,
                              string $rounding = self::DEFAULT_ROUNDING, bool $split = false): array
    {
        if ($plotSizeHa <= 0) {
            throw new \InvalidArgumentException('Plot size must be greater than zero.');
        }

        $deductionPercent = max(0.0, min(100.0, $deductionPercent));
        $net = round($areaHa * (1 - $deductionPercent / 100), 6);
        $exact = round($net / $plotSizeHa, 6);

        // A hair of tolerance so 15.0000001 from floating point is 15, not 14 / 16.
        $plots = match ($rounding) {
            'up'      => (int) ceil($exact - 1e-6),
            'nearest' => (int) round($exact),
            default   => (int) floor($exact + 1e-6),
        };

        return [
            'net_area'   => $net,
            'exact'      => $exact,
            'plots'      => max(0, $plots),
            'split'      => $split,
            // SurveyCompCase::plotSplit(): Government takes the ceiling, the farmer the floor.
            'farmer'     => $split ? intdiv(max(0, $plots), 2) : null,
            'government' => $split ? (int) ceil(max(0, $plots) / 2) : null,
        ];
    }
}
