<?php

namespace App\Models\Laas;

use Illuminate\Database\Eloquent\Model;

/**
 * One stage change: what the applicant reads on their timeline, and the SMS
 * attempt that accompanied it. Written by LaasNotificationService.
 */
class LaasApplicationEvent extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'laas_application_events';

    public const SMS_SENT    = 'sent';
    public const SMS_FAILED  = 'failed';
    public const SMS_SKIPPED = 'skipped';

    protected $fillable = [
        'laas_application_id',
        'stage',
        'title',
        'body',
        'actor_type',
        'actor_id',
        'actor_name',
        'visible_to_applicant',
        'sms_to',
        'sms_body',
        'sms_sent_at',
        'sms_status',
    ];

    protected $casts = [
        'visible_to_applicant' => 'boolean',
        'sms_sent_at'          => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(LaasApplication::class, 'laas_application_id');
    }
}
