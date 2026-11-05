<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** One person. survey_beneficiaries holds one row per farm per section; this is who they all belong to. */
class SurveyFarmer extends SurveyModel
{
    protected $table = 'survey_farmers';

    public function farms(): HasMany
    {
        return $this->hasMany(SurveyBeneficiary::class, 'survey_farmer_id');
    }
}
