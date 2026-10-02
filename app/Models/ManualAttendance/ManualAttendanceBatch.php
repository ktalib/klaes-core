<?php

namespace App\Models\ManualAttendance;

use App\Models\ManualAttendance\ManualAttendanceEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualAttendanceBatch extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'manual_attendance_batches';

    protected $fillable = [
        'batch_code',
        'period_start',
        'period_end',
        'status',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(ManualAttendanceEntry::class, 'batch_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereDate('period_start', $startDate)->whereDate('period_end', $endDate);
    }
}
