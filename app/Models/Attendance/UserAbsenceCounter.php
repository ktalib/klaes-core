<?php

namespace App\Models\Attendance;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAbsenceCounter extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'user_absence_counters';

    protected $fillable = [
        'user_id',
        'consecutive_absences',
        'monthly_absences',
        'month_key',
        'last_absent_date',
    ];

    protected $casts = [
        'consecutive_absences' => 'integer',
        'monthly_absences' => 'integer',
        'last_absent_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
