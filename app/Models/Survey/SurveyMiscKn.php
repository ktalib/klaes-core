<?php

namespace App\Models\Survey;

class SurveyMiscKn extends SurveyModel
{
    protected $table = 'survey_misc_kn';

    protected $casts = ['record_date' => 'date'];
}
