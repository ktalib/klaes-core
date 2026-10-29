<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A compensation complaint, laid out as the printed Compensation Complaint
 * Form (amended 2026-10-29; docs/KLAES Survey Module – Workflow_V02.md 2.4).
 *
 * One of three categories, each with its own block. Compensation is either
 * Monetary (A, an amount in naira) or Land to Land (B, a number of plots).
 * Status follows the form's official-use box: Pending -> Under Review, then a
 * finding (Overpaid / Underpaid / Adequately Compensated) or Resolved. A
 * Resolved complaint is frozen.
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
        'original_amount'       => 'decimal:2',
        'expected_amount'       => 'decimal:2',
        'original_plots'        => 'integer',
        'expected_plots'        => 'integer',
        'supporting_documents'  => 'array',
    ];

    /* Status: the form's Pending / Under Review / Resolved plus the handwritten findings. */
    public const PENDING      = 'Pending';
    public const UNDER_REVIEW = 'Under Review';
    public const OVERPAID     = 'Overpaid';
    public const UNDERPAID    = 'Underpaid';
    public const ADEQUATE     = 'Adequately Compensated';
    public const RESOLVED     = 'Resolved';

    public const STATUSES = [self::PENDING, self::UNDER_REVIEW, self::OVERPAID, self::UNDERPAID, self::ADEQUATE, self::RESOLVED];

    /** Where each status may move next. A finding can go back to review or close. */
    public const NEXT = [
        self::PENDING      => [self::UNDER_REVIEW],
        self::UNDER_REVIEW => [self::OVERPAID, self::UNDERPAID, self::ADEQUATE, self::RESOLVED],
        self::OVERPAID     => [self::UNDER_REVIEW, self::RESOLVED],
        self::UNDERPAID    => [self::UNDER_REVIEW, self::RESOLVED],
        self::ADEQUATE     => [self::UNDER_REVIEW, self::RESOLVED],
        self::RESOLVED     => [],
    ];

    /* Section 3, select one. Keys are the tabs; labels are stored. */
    public const CAT_INADEQUATE        = 'inadequate';
    public const CAT_MISIDENTIFICATION = 'misidentification';
    public const CAT_BOUNDARY          = 'boundary';

    public const CATEGORIES = [
        self::CAT_INADEQUATE        => 'Inadequate Compensation',
        self::CAT_MISIDENTIFICATION => 'Misidentification of Farmland/Property',
        self::CAT_BOUNDARY          => 'Boundary Dispute',
    ];

    /** Category 3 types, as rewritten on the form (were Property / LGA / Interstate boundary). */
    public const BOUNDARY_TYPES = ['Overlap', 'Absent', 'Mis-identification'];

    /** Section 2: A Monetary, B Land to Land. Same values as SurveyProject::scheme_type. */
    public const COMP_TYPES = [
        SurveyProject::SCHEME_MONETARY => 'A — Monetary',
        SurveyProject::SCHEME_LAND     => 'B — Land to Land',
    ];

    /** Section 1 "Project": a Layout, or a public facility (road, school, ...). "Other" is typed. */
    public const PROJECT_TYPES = ['Layout', 'Road', 'School'];

    /** Section 4 checklist. */
    public const DOCUMENTS = [
        'compensation_card'  => 'Copy of Original Compensation Card',
        'valid_id'           => 'Valid Identification (NIN / Voter\'s Card / Passport)',
        'proof_of_ownership' => 'Proof of Ownership / Allocation Letter',
        'photographs'        => 'Photographs of the Property/Farm',
        'other'              => 'Any Other Relevant Documents',
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

    /** Tab key for the stored category label, or null for a label not on the form. */
    public function getCategoryKeyAttribute(): ?string
    {
        $k = array_search($this->category, self::CATEGORIES, true);

        return $k === false ? null : $k;
    }

    public function isLandToLand(): bool
    {
        return $this->compensation_type === SurveyProject::SCHEME_LAND;
    }

    public function hasDocument(string $key): bool
    {
        return in_array($key, (array) $this->supporting_documents, true);
    }
}
