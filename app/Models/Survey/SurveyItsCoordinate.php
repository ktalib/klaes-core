<?php

namespace App\Models\Survey;

/** One beacon on an Instruction to Survey's official list of coordinates. */
class SurveyItsCoordinate extends SurveyModel
{
    protected $table = 'survey_its_coordinates';

    protected $casts = [
        'survey_its_id'           => 'integer',
        'cadastral_survey_job_id' => 'integer',
        'sort_order'              => 'integer',
        'northing'                => 'float',
        'easting'                 => 'float',
        'elevation'               => 'float',
    ];
}
