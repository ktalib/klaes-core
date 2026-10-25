<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A historical survey checked for authenticity against the records. */
class SurveyOldSurvey extends SurveyModel
{
    protected $table = 'survey_old_surveys';

    protected $casts = [
        'survey_date'             => 'date',
        'verified_at'             => 'datetime',
        'survey_register_file_id' => 'integer',
        'verified_by'             => 'integer',
    ];

    public const PENDING = 'Pending';
    public const AUTHENTIC = 'Authentic';
    public const NOT_AUTHENTIC = 'Not Authentic';
    public const INCONCLUSIVE = 'Inconclusive';

    public const STATUSES = [self::PENDING, self::AUTHENTIC, self::NOT_AUTHENTIC, self::INCONCLUSIVE];

    public const STATUS_COLOURS = [self::PENDING => 'yellow', self::AUTHENTIC => 'green', self::NOT_AUTHENTIC => 'red', self::INCONCLUSIVE => 'gray'];

    public const RECORD_SOURCES = ['Survey archive', 'Cadastral records', 'Land registry', 'Doc-WARE scan', 'Surveyor\'s copy'];

    public function registerFile(): BelongsTo
    {
        return $this->belongsTo(SurveyRegisterFile::class, 'survey_register_file_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status !== self::PENDING;
    }
}
