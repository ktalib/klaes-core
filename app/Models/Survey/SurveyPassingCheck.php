<?php

namespace App\Models\Survey;

/**
 * A Passing & Checking record for a final survey plan (CR 30102026 item 21, user 2026-10-31):
 * the lines checked, each distance on the plan against the distance measured, and the result.
 */
class SurveyPassingCheck extends SurveyModel
{
    protected $table = 'survey_passing_checks';

    protected $casts = [
        'lines'       => 'array',
        'checked_at'  => 'date',
        'tolerance_m' => 'decimal:3',
    ];

    public const RESULTS = ['Passed', 'Failed', 'Returned for Correction'];

    public function checker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'checked_by');
    }
}
