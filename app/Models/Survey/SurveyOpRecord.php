<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyOpRecord extends SurveyModel
{
    protected $table = 'survey_op_records';

    protected $casts = [
        'cash_amount'      => 'decimal:2',
        'issued_at'        => 'datetime',
        'verified_at'      => 'datetime',
        'sent_to_deeds_at' => 'datetime',
        'verified_by'      => 'integer',
        'sent_to_deeds_by' => 'integer',
    ];

    /** OP Verification (Compensation > Records), Survey-side. NULL reads as Pending. */
    public const V_PENDING  = 'Pending';
    public const V_VERIFIED = 'Verified';
    public const V_RETURNED = 'Returned';
    public const V_SENT     = 'Sent to Deeds';

    public const V_STATUSES = [self::V_PENDING, self::V_VERIFIED, self::V_RETURNED, self::V_SENT];
    public const V_COLOURS  = [self::V_PENDING => 'yellow', self::V_VERIFIED => 'blue', self::V_RETURNED => 'red', self::V_SENT => 'green'];

    public function verificationStatus(): string
    {
        return $this->verification_status ?: self::V_PENDING;
    }

    /** The OP pipeline from the concept note. */
    public const STEPS = [
        1 => ['Survey Submission',   'Survey Department'],
        2 => ['GIS Verification',    'GIS Unit (KANGIS)'],
        3 => ['Commissioner Review', 'Hon. Commissioner Office'],
        4 => ['Deeds Registration',  'Deeds Registry'],
        5 => ['Land / OSS Issuance', 'Land Admin / OSS'],
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(SurveyCompCase::class, 'survey_comp_case_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(SurveyBeneficiary::class, 'survey_beneficiary_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(SurveyOpWorkflowStep::class, 'survey_op_record_id')->orderBy('step_no');
    }

    /** Create the five pipeline rows, with step 1 already active. */
    public function seedWorkflow(): void
    {
        foreach (self::STEPS as $no => [$name, $actor]) {
            $this->steps()->create([
                'step_no'   => $no,
                'step_name' => $name,
                'actor'     => $actor,
                'status'    => $no === 1 ? 'active' : 'waiting',
            ]);
        }
    }
}
