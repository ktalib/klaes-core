<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Government Computation File (Field Jacket) commissioned by the Survey Department,
 * numbered LPKN-<serial>, no year (CR 30102026 item 13; user 2026-11-02). Private ones are
 * Cadastral's (cadastral_field_jackets, LPPL-<year>-<serial>).
 */
class SurveyComputationFile extends SurveyModel
{
    protected $table = 'survey_computation_files';

    protected $casts = ['commissioned_at' => 'datetime'];

    public const PREFIX = 'LPKN';

    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'commissioned_by');
    }
}
