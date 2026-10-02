<?php

namespace App\Models\Payroll;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSalary extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'payroll_salaries';

    protected $fillable = [
        'period_id',
        'user_id',
        'base_salary',
        'bonuses',
        'extra_hours_value',
        'deductions',
        'net_salary',
        'computed_at',
        'calculation_status',
        'note',
    ];

    protected $casts = [
        'base_salary' => 'float',
        'bonuses' => 'float',
        'extra_hours_value' => 'float',
        'deductions' => 'float',
        'net_salary' => 'float',
        'computed_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForStatus($query, string $status)
    {
        return $query->where('calculation_status', $status);
    }
}
