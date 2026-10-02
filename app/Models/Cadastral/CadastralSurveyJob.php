<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered survey job and the Instruction to Surveyor issued against it.
 *
 * The its_* column names match survey_lpkn's and the its_signature_* names match
 * survey_report_requests', so all three read alike and the Ministry can decide
 * later whether the layout-level and file-level instructions are one thing.
 *
 * The job's location is the prop_* address-builder group; there is no separate
 * free-text location field to drift away from it.
 */
class CadastralSurveyJob extends CadastralModel
{
    protected $table = 'cadastral_survey_jobs';

    protected $casts = [
        'cadastral_index_card_id'   => 'integer',
        'cadastral_report_id'       => 'integer',
        'cadastral_surveyor_id'     => 'integer',
        'its_issued_at'             => 'date',
        'its_signature_verified_at' => 'datetime',
        'issued_at'                 => 'datetime',
        'submitted_at'              => 'datetime',
        'accepted_at'               => 'datetime',
    ];

    public const STATUSES = ['Draft', 'Issued', 'In Field', 'Submitted', 'Accepted', 'Rejected', 'Cancelled'];

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(CadastralSurveyor::class, 'cadastral_surveyor_id');
    }

    public function indexCard(): BelongsTo
    {
        return $this->belongsTo(CadastralIndexCard::class, 'cadastral_index_card_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CadastralReport::class, 'cadastral_report_id');
    }

    public function hasInstruction(): bool
    {
        return ! empty($this->its_number);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Accepted'              => 'active',
            'Submitted'             => 'completed',
            'Rejected', 'Cancelled' => 'rejected',
            'Issued', 'In Field'    => 'review',
            default                 => 'pending',
        };
    }
}
