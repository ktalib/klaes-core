<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A saved Resettlement Calculator result, against the land (survey_comp_cases) it was for. */
class SurveyResettlementCalculation extends SurveyModel
{
    protected $table = 'survey_resettlement_calculations';

    protected $casts = [
        'survey_comp_case_id'         => 'integer',
        'survey_resettlement_rate_id' => 'integer',
        'plot_size_ha'                => 'float',
        'area_ha'                     => 'float',
        'deduction_percent'           => 'float',
        'net_area_ha'                 => 'float',
        'exact_plots'                 => 'float',
        'plots'                       => 'integer',
        'split_applied'               => 'boolean',
        'farmer_plots'                => 'integer',
        'government_plots'            => 'integer',
    ];

    public function land(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }
}
