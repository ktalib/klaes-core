<?php

namespace App\Models\Survey;

class SurveyGknRecord extends SurveyModel
{
    protected $table = 'survey_gkn_records';

    protected $casts = [
        'record_date' => 'date',
        'area_ha'     => 'decimal:2',
    ];
}
