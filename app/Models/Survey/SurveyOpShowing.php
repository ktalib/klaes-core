<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One showing of an OP's site to the allottee (Compensation Status → Verification
 * (Records), docs/KLAES Survey Module – Workflow_V02.md 5.1; recording 286): the
 * surveyor, date, time and photographs. Numbered in order per OP; from the 2nd
 * showing a reason is required (Change of Ownership / Verification). The history
 * is shown latest first, older showings folded away.
 */
class SurveyOpShowing extends SurveyModel
{
    public const RESETTLEMENT = 'Resettlement';
    public const DIRECT = 'Direct Allocation';

    protected $table = 'survey_op_showings';

    /** Whether the Evidence of Payment columns exist yet (migration 2026_11_10_140000; Survey Update 5e). */
    public static function hasPaymentEvidence(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('survey_op_showings', 'payment_evidence_path');
    }

    protected $casts = [
        'survey_op_record_id' => 'integer',
        'surveyors_list_id'   => 'integer',
        'showing_no'          => 'integer',
        'shown_on'            => 'date',
        'op_source_id'        => 'integer',
        'allottee_present'    => 'boolean',
        'requester_is_allottee' => 'boolean',
        'latitude'            => 'float',
        'longitude'           => 'float',
        'accuracy_m'          => 'float',
    ];

    /**
     * Since 2026-10-30 a showing can be of a Deeds OP (op_source capture / pra, with
     * the OP's serial, allottee and plot copied on), recorded from the Plot
     * Confirmation app; survey_op_record_id is then empty. See OpLookup.
     */
    public function getOpKeyAttribute(): ?string
    {
        return $this->op_source && $this->op_source_id ? $this->op_source . '-' . $this->op_source_id : null;
    }

    public const REASONS = ['Change of Ownership', 'Verification'];

    /** Plot Showing Register (Old): copied from the old register book, no OP (user, 2026-11-03). */
    public const SOURCE_OLD = 'old';

    public function op(): BelongsTo
    {
        return $this->belongsTo(SurveyOpRecord::class, 'survey_op_record_id');
    }

    /** Ground checks made in the field in response to this showing (user, 2026-11-03). */
    public function groundChecks(): HasMany
    {
        return $this->hasMany(SurveyPlotCheck::class, 'survey_op_showing_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SurveyOpShowingPhoto::class, 'survey_op_showing_id')->orderBy('id');
    }

    /** "1st Showing", "2nd Showing", "11th Showing", ... */
    public function getLabelAttribute(): string
    {
        return self::ordinal($this->showing_no) . ' Showing';
    }

    /** The showing's type from its OP's: Resettlement, or Direct Allocation for anything else (blank included). */
    public static function typeOf(?string $opType): string
    {
        return stripos((string) $opType, 'Resettlement') !== false ? self::RESETTLEMENT : self::DIRECT;
    }

    public static function ordinal(int $n): string
    {
        $suffix = in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n . $suffix;
    }
}
