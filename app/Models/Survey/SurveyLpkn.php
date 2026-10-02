<?php

namespace App\Models\Survey;

class SurveyLpkn extends SurveyModel
{
    protected $table = 'survey_lpkn';

    protected $casts = [
        'record_date' => 'date',
        'area_ha'     => 'decimal:2',
    ];
}
