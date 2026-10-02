<?php

namespace App\Models\ManualAttendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualAttendanceEntry extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'manual_attendance_entries';

    protected $fillable = [
        'batch_id',
        'user_id',
        'attendance_date',
        'is_present',
        'check_in_time',
        'check_out_time',
        'shift_code',
        'login_hours',
        'overtime_hours',
        'status',
        'notes',
        'captured_by',
        'captured_at',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'is_present' => 'boolean',
        'login_hours' => 'float',
        'overtime_hours' => 'float',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'captured_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ManualAttendanceBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'captured_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(ManualAttendanceAuditLog::class, 'entry_id');
    }
}
