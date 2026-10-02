<?php

namespace App\Models\Survey;

class SurveyFileMovement extends SurveyModel
{
    protected $table = 'survey_file_movements';

    protected $casts = [
        'sent_at'     => 'datetime',
        'received_at' => 'datetime',
    ];
}
