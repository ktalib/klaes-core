<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyBeneficiary extends SurveyModel
{
    use Concerns\HasImportOrigin;

    protected $table = 'survey_beneficiaries';

    protected $casts = ['survey_farmer_id' => 'integer', 'farm_no' => 'integer'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }
}
