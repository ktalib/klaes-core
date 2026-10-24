<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/**
 * One file number on a Beacon Register line (a line may carry several),
 * in the order they were picked.
 */
class SurveyBeaconRegisterFile extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'survey_beacon_register_files';
    protected $guarded = ['id'];

    public function register()
    {
        return $this->belongsTo(SurveyBeaconRegister::class, 'survey_beacon_register_id');
    }
}
