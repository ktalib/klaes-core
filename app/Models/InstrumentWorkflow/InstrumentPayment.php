<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * A payment against a bill, following the KANGIS payment process:
 *   captured  - details from the deposit slip (Bank or KANGIS teller, Transaction ID)
 *   validated - KANGIS Accounts has matched it (manual); receipt generated
 *   rejected  - Accounts could not match it; the bill is open again
 * then the customer counter-signs the receipt and the signed receipt is scanned
 * into DMS (receipt_scan_document_id) and issued. Central Pay (registration fee bill) payments
 * are recorded already validated.
 */
class InstrumentPayment extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_payments';

    public const CHANNEL_BANK = 'bank';
    public const CHANNEL_KANGIS = 'kangis_teller';
    public const CHANNEL_PAYSTACK = 'paystack';
    public const CHANNEL_CENTRAL_PAY = 'central_pay';

    /** Online payment started, waiting for Paystack's confirmation. */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REJECTED = 'rejected';
    /** Online payment abandoned, declined or cancelled; the bill is open again. */
    public const STATUS_FAILED = 'failed';

    /** Statuses that hold the bill: at most one per invoice (filtered unique index). */
    public const LIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_CAPTURED, self::STATUS_VALIDATED];

    public const CHANNEL_LABELS = [
        self::CHANNEL_BANK => 'Bank Branch (Bank Teller)',
        self::CHANNEL_KANGIS => 'KANGIS Payment Collection Point (KANGIS Teller)',
        self::CHANNEL_PAYSTACK => 'Online payment (Paystack)',
        self::CHANNEL_CENTRAL_PAY => 'Kano billing',
    ];

    /** The four copies of the deposit slip, as the process diagram distributes them. */
    public const DEPOSIT_SLIP_COPIES = ['Customer', 'IGR', 'Lands', 'Finance'];

    protected $fillable = [
        'invoice_id',
        'channel',
        'bank_name',
        'deposit_slip_no',
        'transaction_id',
        'depositor_name',
        'paid_on',
        'amount',
        'status',
        'captured_by',
        'captured_at',
        'validated_by',
        'validated_by_name',
        'validated_at',
        'validation_note',
        'receipt_number',
        'receipt_generated_at',
        'receipt_generated_by',
        'counter_signed_at',
        'receipt_scan_document_id',
        'receipt_issued_at',
        'raw_payload',
        'driver',
        'is_demo',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
        'captured_at' => 'datetime',
        'validated_at' => 'datetime',
        'receipt_generated_at' => 'datetime',
        'counter_signed_at' => 'datetime',
        'receipt_issued_at' => 'datetime',
        'is_demo' => 'boolean',
    ];

    /** A payment changes its bill's row in KLAES REV-M → Billing → Automated Billing. */
    protected static function booted(): void
    {
        static::saved(fn (self $payment) => app(\App\Services\Billing\InstrumentBillMirror::class)->syncById((int) $payment->invoice_id));
    }

    public function channelLabel(): string
    {
        return self::CHANNEL_LABELS[$this->channel] ?? $this->channel;
    }

    /** Stored gateway data (Paystack authorization URL, verification response). */
    public function payload(): array
    {
        return json_decode((string) $this->raw_payload, true) ?: [];
    }

    public function invoice()
    {
        return $this->belongsTo(InstrumentInvoice::class, 'invoice_id');
    }

    public function receiptScan()
    {
        return $this->belongsTo(InstrumentApplicationDocument::class, 'receipt_scan_document_id');
    }
}

