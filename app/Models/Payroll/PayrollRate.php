<?php

namespace App\Models\Payroll;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRate extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'payroll_rates';

    protected $fillable = [
        'user_id',
        'daily_rate',
        'shift_hours',
        'effective_date',
        'expires_at',
        'created_by',
        'updated_by',
        'is_active',
        'allow_overtime',
        'overtime_hours_cap',
        'overtime_notes',
    ];

    protected $casts = [
        'daily_rate' => 'float',
        'shift_hours' => 'integer',
        'effective_date' => 'date',
        'expires_at' => 'date',
        'is_active' => 'boolean',
        'allow_overtime' => 'boolean',
        'overtime_hours_cap' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeEffectiveOn(Builder $query, Carbon $date): Builder
    {
        return $query->where('effective_date', '<=', $date->toDateString())
            ->where(function (Builder $inner) use ($date) {
                $inner->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', $date->toDateString());
            });
    }
}
