<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surveyors' Report (Changes): the printed list of changes required when a submitted
 * plan does not match the records, raised from a Register File examination line.
 * Draft until issued; an issued report is locked.
 */
class SurveyChangeReport extends SurveyModel
{
    protected $table = 'survey_change_reports';

    protected $casts = [
        'report_date'             => 'date',
        'issued_at'               => 'datetime',
        'survey_register_file_id' => 'integer',
        'issued_by'               => 'integer',
    ];

    public const DRAFT = 'Draft';
    public const ISSUED = 'Issued';

    public function registerFile(): BelongsTo
    {
        return $this->belongsTo(SurveyRegisterFile::class, 'survey_register_file_id');
    }

    public function isIssued(): bool
    {
        return $this->status === self::ISSUED;
    }
}
