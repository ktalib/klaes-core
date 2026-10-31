<?php

namespace App\Models\Survey;

/**
 * A photograph taken at an OP showing. On the private local disk, like the
 * compensation field photos (SurveyCasePhoto): an OP has no land file, so no
 * EDMS folder to file it in. Served through an authenticated route.
 */
class SurveyOpShowingPhoto extends SurveyModel
{
    protected $table = 'survey_op_showing_photos';

    protected $casts = ['survey_op_showing_id' => 'integer', 'size' => 'integer', 'taken_at' => 'datetime'];

    public const DISK = 'local';
}
