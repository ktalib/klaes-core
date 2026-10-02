<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered surveyor or survey firm.
 *
 * Distinct from cadastral_officers, which is Ministry staff. This is the
 * profession: the people an Instruction to Surveyor can be addressed to.
 *
 * The address is the addr_* builder group, rendered "Street, Plot, District,
 * LGA, State".
 */
class CadastralSurveyor extends CadastralModel
{
    protected $table = 'cadastral_surveyors';

    protected $casts = [
        'licence_expires_on' => 'date',
        'is_active'          => 'boolean',
    ];

    public const LICENCE_STATUSES = ['Active', 'Expired', 'Suspended', 'Struck Off'];

    public function jobs(): HasMany
    {
        return $this->hasMany(CadastralSurveyJob::class, 'cadastral_surveyor_id');
    }

    /** Only an active licence may receive a new Instruction to Surveyor. */
    public function canReceiveInstruction(): bool
    {
        if (! $this->is_active || $this->licence_status !== 'Active') {
            return false;
        }

        return $this->licence_expires_on === null || ! $this->licence_expires_on->isPast();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->firm_name
            ? "{$this->full_name} ({$this->firm_name})"
            : (string) $this->full_name;
    }
}
