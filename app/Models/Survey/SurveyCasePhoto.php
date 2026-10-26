<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A geo-tagged photo of a compensation case's land or assets, taken in the SCF field
 * app. Kept with the case in a Survey-only store (user, 2026-10-25), not in EDMS:
 * the file lives under storage/app/survey/case-photos/{case}/.
 */
class SurveyCasePhoto extends SurveyModel
{
    protected $table = 'survey_case_photos';

    protected $casts = [
        'survey_comp_case_id' => 'integer',
        'latitude'            => 'float',
        'longitude'           => 'float',
        'accuracy_m'          => 'float',
        'taken_at'            => 'datetime',
        'size'                => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** A map link for the place the photo was taken. */
    public function mapUrl(): ?string
    {
        return $this->hasLocation()
            ? 'https://www.openstreetmap.org/?mlat=' . $this->latitude . '&mlon=' . $this->longitude . '#map=18/' . $this->latitude . '/' . $this->longitude
            : null;
    }
}
