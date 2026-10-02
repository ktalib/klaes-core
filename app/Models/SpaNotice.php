<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaNotice extends Model
{
    /** A second serve notice falls due this many days after the first serve. */
    public const SECOND_SERVE_AFTER_DAYS = 14;

    /**
     * The statutory notice wording, held here so the manual (controller) and
     * automatic (spa:trigger-second-service) paths can never send different
     * texts. Wording is the Ministry's — do not paraphrase it.
     */
    public const FIRST_SERVE_SMS = 'YOUR PROPERTY HAVE HEREBY BEEN FOUND TO CONTRAVENE THE APPROVED LANDUSE IN LINE WITH THE MASTER PLAN. TAKE THIS AS YOUR FIRST SERVE AND YOU WILL RECEIVE A SECOND SERVE IN 2 WEEKS IF THERE IS NO RESPONSE FROM YOU';

    public const SECOND_SERVE_SMS = 'THERE WAS NO RESPONSE FROM YOU AFTER THE FIRST SERVE, HENCE YOU WILL PAY THE CONTRAVENTION CHARGES PLUS PENLATY';

    /** The SMS body for a notice type ('first' | 'second'). */
    public static function smsBody(string $noticeType): string
    {
        return $noticeType === 'second' ? self::SECOND_SERVE_SMS : self::FIRST_SERVE_SMS;
    }

    protected $connection = 'sqlsrv';
    protected $table      = 'spa_notices';

    protected $fillable = [
        'spa_application_id', 'file_number', 'notice_type',
        'recipient_name', 'phone', 'served_by',
        'served_date', 'scheduled_date',
        'sms_sent', 'sms_sent_at', 'status', 'created_by',
    ];

    protected $casts = [
        'sms_sent'       => 'boolean',
        'sms_sent_at'    => 'datetime',
        'served_date'    => 'date',
        'scheduled_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function servedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'served_by');
    }
}
