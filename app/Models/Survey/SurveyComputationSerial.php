<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Model;

/**
 * One counter per prefix and year for Computation File numbers (CR 30102026 items 13-14):
 * LPKN (Survey, Government) and LPPL (Cadastral, Private). Settable under Configurable
 * Entities > Serial Initialization; App\Services\Survey\ComputationNumbers reads it.
 */
class SurveyComputationSerial extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'survey_computation_serials';

    protected $fillable = ['prefix', 'year', 'last_serial', 'is_initialized', 'initialized_at', 'initialized_by', 'is_locked'];

    protected $casts = [
        'is_initialized' => 'boolean',
        'is_locked'      => 'boolean',
        'year'           => 'integer',
        'last_serial'    => 'integer',
        'initialized_at' => 'datetime',
    ];
}
