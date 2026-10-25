<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/**
 * One file number on a Control Points register line, in the order picked.
 * Copied from the Beacon Register model (2026-10-25).
 */
class SurveyControlPointRegisterFile extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'survey_control_point_register_files';
    protected $guarded = ['id'];

    public function register()
    {
        return $this->belongsTo(SurveyControlPointRegister::class, 'survey_control_point_register_id');
    }
}
