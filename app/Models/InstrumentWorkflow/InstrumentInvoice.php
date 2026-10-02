<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * A bill on an application: the application fee (paid at Bank / KANGIS teller)
 * or the unified registration fee bill from Kano billing.
 */
class InstrumentInvoice extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_invoices';

    public const PURPOSE_APPLICATION_FEE = 'application_fee';
    public const PURPOSE_REGISTRATION = 'registration';

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'application_id',
        'purpose',
        'reference',
        'driver',
        'consideration_amount',
        'total',
        'status',
        'is_demo',
        'created_by',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'consideration_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'is_demo' => 'boolean',
        'voided_at' => 'datetime',
    ];

    /** Every bill is also listed in KLAES REV-M → Billing → Automated Billing (dbo.billing). */
    protected static function booted(): void
    {
        static::saved(fn (self $invoice) => app(\App\Services\Billing\InstrumentBillMirror::class)->sync($invoice));
    }

    public function purposeLabel(): string
    {
        return $this->purpose === self::PURPOSE_REGISTRATION ? 'Registration Fee Bill' : 'Application Fee Bill';
    }

    public function application()
    {
        return $this->belongsTo(InstrumentApplication::class, 'application_id');
    }

    public function lines()
    {
        return $this->hasMany(InstrumentInvoiceLine::class, 'invoice_id')->orderBy('sort_order');
    }

    public function payments()
    {
        return $this->hasMany(InstrumentPayment::class, 'invoice_id')->orderByDesc('id');
    }

    /** The pending, captured or validated payment holding this bill, if any. */
    public function livePayment(): ?InstrumentPayment
    {
        return $this->payments()->whereIn('status', InstrumentPayment::LIVE_STATUSES)->first();
    }
}

