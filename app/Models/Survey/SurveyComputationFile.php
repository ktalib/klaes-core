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

    /**
     * The next Job No, SUV-JOB-<year>-<serial> (user 2026-11-10; was GKN-JOB-), given on Commission. One
     * series with Cadastral survey jobs, so it comes from their generator. Read inside the commissioning
     * transaction; the filtered unique index refuses a twin.
     */
    public static function nextJobNo(): string
    {
        return app(\App\Services\Cadastral\SurveyJobNumberGenerator::class)->nextJobNumber();
    }

    public function commissioner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'commissioned_by');
    }
}
