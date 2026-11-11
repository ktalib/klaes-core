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

    /** Boundary type (change list p.2): private Property Boundary, or Public Boundary at LGA or Interstate level. */
    public const TYPE_PROPERTY          = 'Property Boundary';
    public const TYPE_PUBLIC_LGA        = 'Public Boundary – LGA Boundary';
    public const TYPE_PUBLIC_INTERSTATE = 'Public Boundary – Interstate Boundary';
    /**
     * Any other public boundary (sidebar update 2026-10-29), typed in boundary_type_other.
     * Withdrawn 2026-10-30, offered again since 2026-11-10 (user: "c. Others" under Public
     * Boundary on the Survey Update sidebar image).
     */
    public const TYPE_PUBLIC_OTHER      = 'Public Boundary – Others';

    public const TYPES = [self::TYPE_PROPERTY, self::TYPE_PUBLIC_LGA, self::TYPE_PUBLIC_INTERSTATE, self::TYPE_PUBLIC_OTHER];

    public const PUBLIC_TYPES = [self::TYPE_PUBLIC_LGA, self::TYPE_PUBLIC_INTERSTATE, self::TYPE_PUBLIC_OTHER];

    /** "Public Boundary – Others (Ward boundary)" when the other boundary was typed. */
    public function getBoundaryLabelAttribute(): ?string
    {
        if (! $this->boundary_type) return null;

        return $this->boundary_type === self::TYPE_PUBLIC_OTHER && $this->boundary_type_other
            ? $this->boundary_type . ' (' . $this->boundary_type_other . ')'
            : $this->boundary_type;
    }

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
