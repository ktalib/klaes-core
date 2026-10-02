<?php

namespace App\Models\Survey;

class SurveyExamination extends SurveyModel
{
    protected $table = 'survey_examinations';

    protected $casts = ['reviewed_at' => 'datetime'];
}
