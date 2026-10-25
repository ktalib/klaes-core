<?php

namespace App\Models\Survey;

/**
 * One field reading on an Instruction to Survey: station to station, observed
 * bearing (decimal degrees) and distance (metres). Entered here from the field
 * book, or synced from Mobile App 2 (source = mobile) once that app exists.
 */
class SurveyItsObservation extends SurveyModel
{
    protected $table = 'survey_its_observations';

    protected $casts = [
        'survey_its_id'           => 'integer',
        'cadastral_survey_job_id' => 'integer',
        'sort_order'              => 'integer',
        'observed_bearing'        => 'float',
        'observed_distance'       => 'float',
        'observed_on'             => 'date',
    ];
}
