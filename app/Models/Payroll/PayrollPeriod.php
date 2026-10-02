<?php

namespace App\Models\Payroll;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'payroll_periods';

    protected $fillable = [
        'period_code',
        'start_date',
        'end_date',
        'status',
        'locked_at',
        'created_by',
        'locked_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'locked_at' => 'datetime',
    ];

    public function attendance(): HasMany
    {
        return $this->hasMany(PayrollAttendance::class, 'period_id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(PayrollSalary::class, 'period_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PayrollAuditLog::class, 'period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function scopeForMonth(Builder $query, string $periodCode): Builder
    {
        return $query->where('period_code', $periodCode);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'locked');
    }

    public function markLocked(int $userId): bool
    {
        return $this->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $userId,
        ]);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function periodStart(): Carbon
    {
        return $this->start_date instanceof Carbon
            ? $this->start_date->copy()
            : Carbon::parse($this->start_date);
    }

    public function periodEnd(): Carbon
    {
        return $this->end_date instanceof Carbon
            ? $this->end_date->copy()
            : Carbon::parse($this->end_date);
    }

    public static function codeFromDate(Carbon $date): string
    {
        return $date->format('Y-m');
    }
}
