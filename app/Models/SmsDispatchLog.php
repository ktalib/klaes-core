<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One transactional SMS: what was sent, to whom, and what the gateway said.
 *
 * This table is BOTH the audit trail and the double-send guard -- see the
 * migration for why the unique index on dedupe_key is what actually enforces
 * "once per event" rather than a read-then-write check.
 *
 * STATUS MEANINGS, which are not interchangeable when reading this back:
 *   pending  a row was claimed and the gateway call is in flight (or the
 *            process died mid-call and nobody knows).
 *   sent     the gateway accepted it. NOT a delivery receipt -- this account is
 *            on the promotional route, which does not reach DND-blocked
 *            handsets, and code 609 means "accepted, held until 08:30".
 *   failed   the gateway refused it, or the credentials/balance are wrong.
 *            Retryable: the claim logic hands a failed row back.
 *   skipped  we chose not to send. No number on file, the number is shared
 *            across many files and nobody confirmed it, or the message is
 *            switched off. A skipped row is a record of a decision, not a
 *            fault, and is the thing to look at when an applicant says they
 *            were never told.
 */
class SmsDispatchLog extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'sms_dispatch_logs';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /*
     | Why a message was not sent. Stored in failure_reason on a skipped row so
     | the control page and the doctor command can explain it in plain language
     | instead of showing a blank.
     */
    public const SKIP_DISABLED = 'This message is switched off in the SMS Control Centre.';
    public const SKIP_NO_PHONE = 'No phone number was available for this recipient.';
    public const SKIP_BAD_PHONE = 'The phone number on file is not a usable Nigerian mobile number.';
    public const SKIP_SHARED_PHONE = 'The number on file is shared across many files and was not confirmed as this applicant.';
    public const SKIP_NO_TEMPLATE = 'This message has no wording configured.';

    protected $fillable = [
        'message_key',
        'phone',
        'message',
        'status',
        'gateway_code',
        'failure_reason',
        'attempts',
        'file_number',
        'subject_type',
        'subject_id',
        'dedupe_key',
        'event_at',
        'created_by',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'event_at' => 'datetime',
    ];

    public function scopeForKey($query, string $key)
    {
        return $query->where('message_key', $key);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }
}
