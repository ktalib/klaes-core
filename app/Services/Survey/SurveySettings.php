<?php

namespace App\Services\Survey;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey settings kept under Configurable Entities > Survey (CR 30102026, user 2026-10-31):
 * sharing ratios (item 11), Classmark ranges (item 8), land rates (item 5) and beacon
 * prefixes (Issue Beacon No). Also owns the two rules built on them, so every screen
 * splits and rounds plots the same way.
 */
class SurveySettings
{
    public const GENERAL_FARMER_PCT = 50;

    public const LAND_UNITS = ['hectare' => 'per hectare', 'sqm' => 'per m²'];

    private static ?bool $installed = null;

    public static function installed(): bool
    {
        return self::$installed ??= Schema::connection('sqlsrv')->hasTable('survey_sharing_ratios');
    }

    private static function db()
    {
        return DB::connection('sqlsrv');
    }

    /** Sharing ratios, farmer share first: [id, farmer_pct, govt_pct, label, is_default, is_active]. */
    public static function sharingRatios(bool $activeOnly = true): Collection
    {
        if (! self::installed()) return collect([(object) ['id' => null, 'farmer_pct' => 50, 'govt_pct' => 50, 'is_default' => 1, 'is_active' => 1, 'label' => '50:50']]);

        return self::db()->table('survey_sharing_ratios')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderByDesc('is_default')->orderByDesc('farmer_pct')->get()
            ->each(fn ($r) => $r->label = self::ratioLabel($r->farmer_pct));
    }

    /** The general ratio's farmer share: the default row, else 50. */
    public static function defaultFarmerPct(): int
    {
        if (! self::installed()) return self::GENERAL_FARMER_PCT;

        return (int) (self::db()->table('survey_sharing_ratios')->where('is_default', 1)->where('is_active', 1)->value('farmer_pct') ?? self::GENERAL_FARMER_PCT);
    }

    /** "60:40", farmer first, Government second. */
    public static function ratioLabel(?int $farmerPct): string
    {
        $f = $farmerPct ?? self::GENERAL_FARMER_PCT;

        return $f . ':' . (100 - $f);
    }

    /**
     * Plots split between farmer and Government by the farmer's share. Government takes
     * whatever the farmer's whole-plot share leaves, so on 50:50 the odd plot goes to
     * Government, as it always has.
     *
     * @return array{total:int, govt:int, farmer:int, farmer_pct:int}
     */
    public static function splitPlots(int $total, ?int $farmerPct = null): array
    {
        $total = max(0, $total);
        $pct = $farmerPct ?? self::GENERAL_FARMER_PCT;
        $farmer = intdiv($total * $pct, 100);

        return ['total' => $total, 'govt' => $total - $farmer, 'farmer' => $farmer, 'farmer_pct' => $pct];
    }

    /** Classmark ranges, lowest first: [id, from_fraction, to_fraction, plots, is_active]. */
    public static function classmarks(bool $activeOnly = true): Collection
    {
        if (! self::installed()) return collect();

        return self::db()->table('survey_classmarks')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderBy('from_fraction')->get();
    }

    /** ¼, ½, ¾ or a decimal: how a Classmark result reads. */
    public static function plotsLabel(float $plots): string
    {
        $whole = (int) floor($plots + 1e-9);
        $hundredths = (int) round(($plots - $whole) * 100);
        if ($hundredths === 0) return (string) $whole;

        $sign = [25 => '¼', 50 => '½', 75 => '¾'][$hundredths] ?? null;
        if ($sign === null) return number_format($plots, 2);

        return ($whole ? $whole : '') . $sign;
    }

    /**
     * Apply the Classmark to the exact number of plots (e.g. 1.49): the whole plots stay,
     * and the decimal part, read to two places, becomes the plots of the range it falls in.
     *
     * @return array{exact:float, whole:int, fraction:float, extra:float, plots:float, label:string, range:?object}
     */
    public static function applyClassmark(float $exact): array
    {
        $exact = max(0.0, $exact);
        $whole = (int) floor($exact + 1e-9);
        $fraction = round($exact - $whole, 2);
        // An exact whole number has no part plot to read (Survey's ranges, user 2026-11-03, start at 0).
        $range = $fraction <= 0 ? null : self::classmarks()->first(fn ($r) => $fraction >= (float) $r->from_fraction - 1e-9 && $fraction <= (float) $r->to_fraction + 1e-9);
        $extra = $range ? (float) $range->plots : 0.0;
        $plots = $whole + $extra;

        return ['exact' => $exact, 'whole' => $whole, 'fraction' => $fraction, 'extra' => $extra,
            'plots' => $plots, 'label' => self::plotsLabel($plots), 'range' => $range];
    }

    /** Land rates for the Monetary Compensation calculator's land lines. */
    public static function landRates(bool $activeOnly = true): Collection
    {
        if (! self::installed()) return collect();

        return self::db()->table('survey_land_rates')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderByDesc('is_default')->orderBy('name')->get();
    }

    /** Beacon prefixes for Issue Beacon No. */
    public static function beaconPrefixes(bool $activeOnly = true): Collection
    {
        if (! self::installed()) return collect();

        return self::db()->table('survey_beacon_prefixes')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderBy('prefix')->get();
    }

    /** What the Configurable Entities > Survey tab shows. */
    public static function tabData(): array
    {
        return [
            'svRatios'    => self::sharingRatios(false),
            'svClassmarks'=> self::classmarks(false),
            'svLandRates' => self::landRates(false),
            'svPrefixes'  => self::beaconPrefixes(false),
            'svInstalled' => self::installed(),
        ];
    }
}
