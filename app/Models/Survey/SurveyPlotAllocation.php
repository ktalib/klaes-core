<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyPlotAllocation extends SurveyModel
{
    use Concerns\HasImportOrigin;

    protected $table = 'survey_plot_allocations';

    protected $casts = [
        'acreage'   => 'decimal:4',
        'govt_50'   => 'decimal:4',
        'farmer_50' => 'decimal:4',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }
}
