<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyBeneficiary extends SurveyModel
{
    protected $table = 'survey_beneficiaries';

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }
}
