<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyOpRecord extends SurveyModel
{
    protected $table = 'survey_op_records';

    protected $casts = [
        'cash_amount' => 'decimal:2',
        'issued_at'   => 'datetime',
    ];

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
