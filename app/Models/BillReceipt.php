<?php

namespace App\Models;

use App\Services\Billing\BillRef;
use Illuminate\Database\Eloquent\Model;

/**
 * An official Ministry receipt, issued under Receipting → Generate Receipt and
 * printed in three copies (Original, Duplicate, Triplicate).
 * Number RCT-{year}-{000001}, issued by BillRef.
 */
class BillReceipt extends Model
{
    public const COPIES = ['Original', 'Duplicate', 'Triplicate'];

    protected $connection = 'sqlsrv';
    protected $table = 'bill_receipts';

    protected $fillable = [
        'receipt_no', 'bill_ref_id', 'bill_payment_id', 'amount', 'status', 'issued_by', 'issued_at',
        'copies_printed', 'reprint_count', 'last_printed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'last_printed_at' => 'datetime',
        'copies_printed' => 'integer',
        'reprint_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $receipt) {
            $receipt->receipt_no = trim((string) $receipt->receipt_no) ?: BillRef::next(BillRef::RECEIPT);
        });
    }

    public function payment()
    {
        return $this->belongsTo(BillPayment::class, 'bill_payment_id');
    }
}
