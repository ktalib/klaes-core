<?php

namespace App\Models\Payroll;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAttendance extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'payroll_attendance';

    protected $fillable = [
        'period_id',
        'user_id',
        'department_id',
        'login_days',
        'hours_worked',
        'overtime_hours',
        'session_breakdown',
        'source_reference',
    ];

    protected $casts = [
        'login_days' => 'float',
        'hours_worked' => 'float',
        'overtime_hours' => 'float',
        'session_breakdown' => 'array',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
