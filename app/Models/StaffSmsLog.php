<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One attendance SMS claim: see the migration for why this table is the
 * throttle as well as the audit trail.
 */
class StaffSmsLog extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'staff_sms_logs';

    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGOUT = 'logout';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'sms_type',
        'sent_on',
        'status',
        'phone',
        'message',
        'gateway_code',
        'failure_reason',
        'attempts',
        'event_at',
    ];

    protected $casts = [
        'sent_on' => 'date',
        'event_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
