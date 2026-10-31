<?php

namespace App\Models\Survey;

/** A geo-tagged photograph of a ground check; on the private local disk (no land file to file it under). */
class SurveyPlotCheckPhoto extends SurveyModel
{
    protected $table = 'survey_plot_check_photos';

    protected $casts = ['survey_plot_check_id' => 'integer', 'size' => 'integer', 'taken_at' => 'datetime'];

    public const DISK = 'local';
}
