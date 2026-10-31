<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Valuation catalogue for monetary schemes.
 *
 * `category` holds the tree's TYPE (Cash Crop / Fruit / Timber). `maturity` is the
 * assessment CATEGORY, Mature or Early (recording 286): each catalogue row is one
 * tree in one category with its own rate, so "Mango – Mature" and "Mango – Early"
 * are two rows (user, 2026-10-30). default_unit_price is that row's rate; the
 * name is unique per category. early_unit_price (the 2026-10-29 layout) is unused.
 */
class SurveyTreeType extends SurveyModel
{
    protected $table = 'survey_tree_types';

    protected $casts = ['default_unit_price' => 'decimal:2'];

    public const MATURE = 'Mature';
    public const EARLY  = 'Early';
    public const MATURITIES = [self::MATURE, self::EARLY];

    /** This row's rate, or null when it is not set yet. */
    public function rate(): ?float
    {
        return $this->default_unit_price === null ? null : (float) $this->default_unit_price;
    }

    /** "Mango (Mature)". */
    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . ($this->maturity ?: self::MATURE) . ')';
    }

    /** The catalogue row for a tree name in a category (blank category = Mature). */
    public static function findFor(string $name, ?string $maturity)
    {
        return static::where('name', trim($name))->where('maturity', $maturity ?: self::MATURE)->first();
    }

    public function caseTrees(): HasMany
    {
        return $this->hasMany(SurveyCaseTree::class, 'survey_tree_type_id');
    }
}
