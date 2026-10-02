<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyPlotAllocation extends SurveyModel
{
    protected $table = 'survey_plot_allocations';

    protected $casts = [
        'acreage'   => 'decimal:2',
        'govt_50'   => 'decimal:2',
        'farmer_50' => 'decimal:2',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }
}
