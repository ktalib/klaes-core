<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step of a report's stage chain. Seeded from config at report creation so
 * a later config change cannot rewrite a report already in flight.
 */
class CadastralReportStep extends CadastralModel
{
    protected $table = 'cadastral_report_steps';

    protected $casts = [
        // sqlsrv hands integer columns back as strings; without these casts a
        // strict comparison of a foreign key against a model id is always false.
        'cadastral_report_id' => 'integer',
        'step_no'      => 'integer',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUSES = ['waiting', 'active', 'done', 'returned', 'skipped'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(CadastralReport::class, 'cadastral_report_id');
    }

    public function isDone(): bool
    {
        return $this->status === 'done' || $this->status === 'skipped';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'done'     => 'completed',
            'active'   => 'active',
            'returned' => 'rejected',
            'skipped'  => 'review',
            default    => 'pending',
        };
    }
}
