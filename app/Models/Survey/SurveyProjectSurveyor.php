<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A surveyor assigned to a project with a number of portions to survey, each of
 * the same size. Each portion is surveyed on its own case form.
 */
class SurveyProjectSurveyor extends SurveyModel
{
    protected $table = 'survey_project_surveyors';

    protected $casts = [
        'survey_project_id' => 'integer',
        'surveyor_id'     => 'integer',
        'portions'        => 'integer',
        'portion_size_ha' => 'decimal:4',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SurveyProject::class, 'survey_project_id');
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(SurveyorListEntry::class, 'surveyor_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(SurveyCompCase::class, 'survey_project_surveyor_id');
    }

    /** Portion numbers already taken by a case, optionally ignoring one case. */
    public function usedPortions(?int $exceptCaseId = null): array
    {
        return $this->cases()
            ->whereNotNull('portion_no')
            ->when($exceptCaseId, fn ($q) => $q->where('id', '!=', $exceptCaseId))
            ->pluck('portion_no')->map(fn ($n) => (int) $n)->all();
    }

    public function getTotalSizeHaAttribute(): float
    {
        return round($this->portions * (float) $this->portion_size_ha, 4);
    }
}
