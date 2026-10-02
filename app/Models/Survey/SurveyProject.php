<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A compensation project. The scheme type is set here and is exclusive:
 * cases inherit it and cannot mix monetary with land-for-land.
 */
class SurveyProject extends SurveyModel
{
    protected $table = 'survey_projects';

    protected $casts = ['start_date' => 'date'];

    public const SCHEME_MONETARY = 'monetary';
    public const SCHEME_LAND     = 'land';

    public function cases(): HasMany
    {
        return $this->hasMany(SurveyCompCase::class, 'survey_project_id');
    }

    public function isMonetary(): bool
    {
        return $this->scheme_type === self::SCHEME_MONETARY;
    }

    public function getSchemeLabelAttribute(): string
    {
        return $this->isMonetary() ? 'Monetary (Cash for Trees)' : 'Land-for-Land (50:50)';
    }
}
