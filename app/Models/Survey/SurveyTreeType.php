<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Valuation catalogue for monetary schemes: the Kano schedule (user, 2026-11-01).
 *
 * `category` holds the TYPE: Cash Crop (priced per m²) or Economic Tree (priced per stand).
 * `maturity` is the assessment CATEGORY: Mature, Immature or Seedling. Each catalogue row is one
 * crop / tree in one Category with its own rate, so "Mango – Mature" and "Mango – Seedling" are
 * two rows. default_unit_price is the rate per unit (m² or stand), price_per_ha the schedule's
 * per-hectare price, and cost_per_ha the cost of production per hectare, which values the land
 * under the crop / tree. The name is unique per Type and Category. early_unit_price is unused.
 */
class SurveyTreeType extends SurveyModel
{
    protected $table = 'survey_tree_types';

    protected $casts = ['default_unit_price' => 'decimal:2', 'price_per_ha' => 'decimal:2', 'cost_per_ha' => 'decimal:2'];

    public const MATURE   = 'Mature';
    public const IMMATURE = 'Immature';
    public const SEEDLING = 'Seedling';
    public const MATURITIES = [self::MATURE, self::IMMATURE, self::SEEDLING];

    public const CASH_CROP     = 'Cash Crop';
    public const ECONOMIC_TREE = 'Economic Tree';
    public const TYPES = [self::CASH_CROP, self::ECONOMIC_TREE];

    public function isCashCrop(): bool
    {
        return $this->category === self::CASH_CROP;
    }

    /** "m²" for a cash crop, "stand" for an economic tree. */
    public function unitLabel(): string
    {
        return $this->isCashCrop() ? 'm²' : 'stand';
    }

    /** This row's rate per unit (m² / stand), or null when it is not set yet. */
    public function rate(): ?float
    {
        return $this->default_unit_price === null ? null : (float) $this->default_unit_price;
    }

    /**
     * The rate for a quantity in $unit: a cash crop in hectares uses the schedule's per-hectare
     * price, in m² the per-m² price (user, 2026-11-01: priced by the unit typed); a tree, per stand.
     */
    public function rateFor(string $unit): ?float
    {
        if ($this->isCashCrop() && $unit === 'hectare') {
            return $this->price_per_ha === null ? null : (float) $this->price_per_ha;
        }

        return $this->rate();
    }

    public function costPerHa(): ?float
    {
        return $this->cost_per_ha === null ? null : (float) $this->cost_per_ha;
    }

    /** "Mango (Mature)". */
    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . ($this->maturity ?: self::MATURE) . ')';
    }

    /**
     * The catalogue row for a name in a Category (blank = Mature). A name in both lists
     * (White acacia, Ziziphus) resolves to the economic tree unless the Type is given.
     */
    public static function findFor(string $name, ?string $maturity, ?string $type = null)
    {
        return static::where('name', trim($name))->where('maturity', $maturity ?: self::MATURE)
            ->when($type, fn ($q) => $q->where('category', $type))
            ->orderByRaw("CASE WHEN category = ? THEN 0 ELSE 1 END", [self::ECONOMIC_TREE])
            ->first();
    }

    public function caseTrees(): HasMany
    {
        return $this->hasMany(SurveyCaseTree::class, 'survey_tree_type_id');
    }
}
