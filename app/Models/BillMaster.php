<?php

namespace App\Models;

use App\Services\Billing\BillRef;
use Illuminate\Database\Eloquent\Model;

/**
 * One system bill in the Bill Master register: any bill a KLAES module
 * generates. source_table + source_id point back at the module row.
 *
 * Every bill has a Bill Ref ID, BILL-{year}-{000001}, issued here (BillRef).
 * A module's own old ref is kept in legacy_ref when its bills are renumbered.
 * transaction_id / receipt_no are shortcuts to the latest payment;
 * bill_payments is the full record.
 */
class BillMaster extends Model
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PART_PAID = 'part_paid';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    /** raised_via: an on-the-fly bill (Bill Master page, Revenue Codes), or the next cycle of a recurring bill. */
    public const RAISED_ON_THE_FLY = 'on_the_fly';
    public const RAISED_CYCLE = 'cycle';

    protected $connection = 'sqlsrv';
    protected $table = 'bill_master';

    protected $fillable = [
        'bill_ref_id', 'legacy_ref', 'bill_type_code', 'file_number', 'file_indexing_id',
        'payer_name', 'payer_phone', 'payer_email', 'payer_address',
        'plot_no', 'district', 'lga', 'location',
        'description', 'amount', 'amount_paid', 'from_year', 'to_year', 'bill_date', 'due_date', 'status',
        'transaction_id', 'receipt_no', 'receipt_date', 'source_table', 'source_id',
        'notes', 'created_by', 'updated_by', 'generated_at', 'generated_by',
        'payment_cycle', 'period_start', 'period_end', 'next_due_date', 'previous_bill_ref', 'raised_via',
    ];

    protected $attributes = [
        'status' => self::STATUS_UNPAID,
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'from_year' => 'integer',
        'to_year' => 'integer',
        'bill_date' => 'date',
        'due_date' => 'date',
        'receipt_date' => 'date',
        'generated_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'next_due_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $bill) {
            $bill->bill_ref_id = trim((string) $bill->bill_ref_id) ?: BillRef::next(BillRef::SYSTEM);
            // Generated in REV-M itself (no module row behind it): generated now.
            if (!$bill->generated_at && !$bill->source_table) {
                $bill->generated_at = now();
                $bill->generated_by = $bill->created_by;
            }
        });

        // A bill that is being paid has been generated (ST initial / portal bills arrive paid).
        static::saving(function (self $bill) {
            if (!$bill->generated_at && in_array($bill->status, [self::STATUS_PART_PAID, self::STATUS_PAID], true)) {
                $bill->generated_at = now();
                $bill->generated_by = $bill->updated_by ?: $bill->created_by;
            }
        });
    }

    /** What the screens show: an unpaid bill that has not been generated yet is Pending. */
    public function displayStatus(): string
    {
        return $this->status === self::STATUS_UNPAID && !$this->isGenerated() ? 'pending' : (string) $this->status;
    }

    /** Generated in REV-M: only then does its invoice open and can it be paid. */
    public function isGenerated(): bool
    {
        return $this->generated_at !== null;
    }

    public function type()
    {
        return $this->belongsTo(BillMasterType::class, 'bill_type_code', 'code');
    }

    public function items()
    {
        return $this->hasMany(BillItem::class, 'bill_ref_id', 'bill_ref_id')->orderBy('sort_order')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class, 'bill_ref_id', 'bill_ref_id')->orderBy('id');
    }

    public function receipts()
    {
        return $this->hasMany(BillReceipt::class, 'bill_ref_id', 'bill_ref_id')->orderBy('id');
    }
}
