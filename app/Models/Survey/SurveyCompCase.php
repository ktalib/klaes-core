<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyCompCase extends SurveyModel
{
    protected $table = 'survey_comp_cases';

    protected $casts = [
        'case_date' => 'date',
        'area_ha'   => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SurveyProject::class, 'survey_project_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(SurveyBeneficiary::class, 'survey_comp_case_id');
    }

    public function trees(): HasMany
    {
        return $this->hasMany(SurveyCaseTree::class, 'survey_comp_case_id');
    }

    public function plots(): HasMany
    {
        return $this->hasMany(SurveyPlotAllocation::class, 'survey_comp_case_id');
    }

    public function opRecords(): HasMany
    {
        return $this->hasMany(SurveyOpRecord::class, 'survey_comp_case_id');
    }

    public function isMonetary(): bool
    {
        return $this->scheme_type === SurveyProject::SCHEME_MONETARY;
    }

    /** Total cash value of economic trees on this case. */
    public function getTreeTotalAttribute(): float
    {
        return (float) $this->trees()->sum('line_total');
    }

    /**
     * 50:50 split. Plot rows win; otherwise fall back to the declared count.
     * Odd plot goes to Government (ceil), farmer takes the floor.
     */
    public function plotSplit(): array
    {
        $total = $this->plots()->count() ?: (int) ($this->num_plots ?? 0);

        return [
            'total'  => $total,
            'govt'   => (int) ceil($total / 2),
            'farmer' => (int) floor($total / 2),
        ];
    }
}
