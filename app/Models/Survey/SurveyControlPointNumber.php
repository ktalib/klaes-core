<?php

namespace App\Models\Survey;

/**
 * One control point number expanded from a Control Points register line.
 * Copied from the Beacon Register model (2026-10-25).
 */
class SurveyControlPointNumber extends SurveyModel
{
    protected $table = 'survey_control_point_numbers';

    public function register()
    {
        return $this->belongsTo(SurveyControlPointRegister::class, 'survey_control_point_register_id');
    }
}
