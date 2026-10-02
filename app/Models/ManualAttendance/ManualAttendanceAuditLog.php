<?php

namespace App\Models\ManualAttendance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualAttendanceAuditLog extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'manual_attendance_audit_logs';

    protected $fillable = [
        'entry_id',
        'action',
        'performed_by',
        'performed_at',
        'payload',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
        'payload' => 'array',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(ManualAttendanceEntry::class, 'entry_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'performed_by');
    }
}
