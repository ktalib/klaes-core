<?php

namespace App\Models\Survey;

/**
 * A plot size for the Resettlement Calculator: one land use / purpose and density
 * (Residential HD / MD / LD from the SG, 29 Oct 2026). Retired, never deleted.
 */
class SurveyResettlementRate extends SurveyModel
{
    protected $table = 'survey_resettlement_rates';

    protected $casts = ['plot_size_ha' => 'float', 'sort_order' => 'integer', 'is_active' => 'boolean'];

    public function getLabelAttribute(): string
    {
        return $this->land_use . ($this->purpose ? ' · ' . $this->purpose : '') . ($this->density ? ' – ' . $this->density : '') . ($this->code ? " ({$this->code})" : '');
    }
}
