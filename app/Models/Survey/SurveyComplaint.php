<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A grievance from a compensation exercise or OP allocation. Status moves
 * Open -> Under Investigation -> Resolved or Escalated; a Resolved complaint is
 * frozen.
 */
class SurveyComplaint extends SurveyModel
{
    protected $table = 'survey_complaints';

    protected $casts = [
        'received_date'         => 'date',
        'resolved_at'           => 'datetime',
        'survey_project_id'     => 'integer',
        'survey_comp_case_id'   => 'integer',
        'survey_beneficiary_id' => 'integer',
        'resolved_by'           => 'integer',
    ];

    public const OPEN          = 'Open';
    public const INVESTIGATING = 'Under Investigation';
    public const RESOLVED      = 'Resolved';
    public const ESCALATED     = 'Escalated';

    public const STATUSES = [self::OPEN, self::INVESTIGATING, self::RESOLVED, self::ESCALATED];

    /** Where each status may move next. */
    public const NEXT = [
        self::OPEN          => [self::INVESTIGATING],
        self::INVESTIGATING => [self::RESOLVED, self::ESCALATED],
        self::ESCALATED     => [self::INVESTIGATING, self::RESOLVED],
        self::RESOLVED      => [],
    ];

    public const CATEGORIES = [
        'Valuation / amount',
        'Plot allocation',
        'Ownership dispute',
        'Boundary / measurement',
        'Omitted from beneficiary list',
        'Payment delay',
        'Bank / account details',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SurveyProject::class, 'survey_project_id');
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(SurveyBeneficiary::class, 'survey_beneficiary_id');
    }

    public function isFrozen(): bool
    {
        return $this->status === self::RESOLVED;
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::NEXT[$this->status] ?? [], true);
    }
}
