<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDailySnapshot extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'attendance_daily_snapshots';

    protected $fillable = [
        'user_id',
        'attendance_date',
        'shift_code',
        'status',
        'login_time',
        'logout_time',
        'late_login',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
        'late_login' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
