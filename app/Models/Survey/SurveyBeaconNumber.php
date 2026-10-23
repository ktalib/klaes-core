<?php

namespace App\Models\Survey;

/**
 * One beacon number expanded from a Beacon Register line. The surveyor, date,
 * location and payment are the line's, read through register().
 */
class SurveyBeaconNumber extends SurveyModel
{
    protected $table = 'survey_beacon_numbers';

    public function register()
    {
        return $this->belongsTo(SurveyBeaconRegister::class, 'survey_beacon_register_id');
    }
}
