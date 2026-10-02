<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A survey pillar/beacon counted off the plan.
 *
 * Government and private pillars are priced the same (concept note 4.4b) -- the
 * ownership split is for the bill's breakdown, not for a different rate.
 */
class CadastralPillar extends CadastralModel
{
    protected $table = 'cadastral_pillars';

    protected $casts = [
        'cadastral_plan_description_id' => 'integer',
        'northing'   => 'float',
        'easting'    => 'float',
        'latitude'   => 'float',
        'longitude'  => 'float',
        'unit_price' => 'float',
        'sort_order' => 'integer',
    ];

    public const OWNERSHIP_GOVERNMENT = 'government';
    public const OWNERSHIP_PRIVATE    = 'private';

    public const CONDITIONS = ['Intact', 'Damaged', 'Missing', 'Replaced'];

    public function planDescription(): BelongsTo
    {
        return $this->belongsTo(CadastralPlanDescription::class, 'cadastral_plan_description_id');
    }

    public function isGovernment(): bool
    {
        return $this->ownership === self::OWNERSHIP_GOVERNMENT;
    }
}
