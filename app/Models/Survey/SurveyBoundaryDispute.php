<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A boundary dispute found or reported during verification. Open -> Under
 * Investigation -> Resolved or Escalated; Resolved is locked.
 */
class SurveyBoundaryDispute extends SurveyModel
{
    protected $table = 'survey_boundary_disputes';

    protected $casts = [
        'received_date'           => 'date',
        'resolved_at'             => 'datetime',
        'survey_register_file_id' => 'integer',
        'resolved_by'             => 'integer',
    ];

    public const OPEN = 'Open';
    public const INVESTIGATING = 'Under Investigation';
    public const RESOLVED = 'Resolved';
    public const ESCALATED = 'Escalated';

    public const STATUSES = [self::OPEN, self::INVESTIGATING, self::RESOLVED, self::ESCALATED];

    /** Where each status may move next (as Compensation complaints). */
    public const NEXT = [
        self::OPEN          => [self::INVESTIGATING],
        self::INVESTIGATING => [self::RESOLVED, self::ESCALATED],
        self::ESCALATED     => [self::INVESTIGATING, self::RESOLVED],
        self::RESOLVED      => [],
    ];

    public const NATURES = [
        'Encroachment',
        'Overlapping surveys',
        'Beacon removed or shifted',
        'Boundary line disagreement',
        'Access / right of way',
        'Double allocation',
    ];

    public function registerFile(): BelongsTo
    {
        return $this->belongsTo(SurveyRegisterFile::class, 'survey_register_file_id');
    }

    public function isFrozen(): bool
    {
        return $this->status === self::RESOLVED;
    }
}
