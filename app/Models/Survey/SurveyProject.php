<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A compensation project. The scheme type is set here and is exclusive:
 * cases inherit it and cannot mix monetary with plot-for-plot.
 */
class SurveyProject extends SurveyModel
{
    protected $table = 'survey_projects';

    protected $casts = [
        'start_date'          => 'date',
        'acquisition_size_ha' => 'decimal:4',
        'survey_co_file_id'   => 'integer',
    ];

    public const SCHEME_MONETARY = 'monetary';
    public const SCHEME_LAND     = 'land';

    public function cases(): HasMany
    {
        return $this->hasMany(SurveyCompCase::class, 'survey_project_id');
    }

    public function coFile(): BelongsTo
    {
        return $this->belongsTo(SurveyCoFile::class, 'survey_co_file_id');
    }

    /** Surveyors on the project, each with portions and a size per portion. */
    public function surveyors(): HasMany
    {
        return $this->hasMany(SurveyProjectSurveyor::class, 'survey_project_id');
    }

    public function isMonetary(): bool
    {
        return $this->scheme_type === self::SCHEME_MONETARY;
    }

    public function getSchemeLabelAttribute(): string
    {
        return $this->isMonetary() ? 'Cash for Trees' : 'Plot for Plot (50:50)';
    }
}
