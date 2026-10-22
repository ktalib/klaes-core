<?php

namespace App\Models;

use App\Services\Billing\BillRef;
use Illuminate\Database\Eloquent\Model;

/**
 * One legacy bill: a historical bill captured under File Indexing. Kept apart
 * from the system bills in bill_master. Bill Ref ID LGB-{year}-{000001};
 * the receipt captured at indexing stays on the row.
 * Types: config('billing.legacy_types').
 */
class BillLegacy extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'bill_legacy';

    protected $fillable = [
        'bill_ref_id', 'bill_type_code', 'file_number', 'file_indexing_id',
        'payer_name', 'payer_phone', 'payer_email', 'payer_address',
        'plot_no', 'district', 'lga', 'location',
        'description', 'amount', 'amount_paid', 'from_year', 'to_year', 'bill_date', 'status',
        'transaction_id', 'receipt_no', 'receipt_date', 'source_table', 'source_id',
        'notes', 'created_by', 'updated_by',
    ];

    protected $attributes = [
        'status' => BillMaster::STATUS_UNPAID,
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'from_year' => 'integer',
        'to_year' => 'integer',
        'bill_date' => 'date',
        'receipt_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $bill) {
            $bill->bill_ref_id = trim((string) $bill->bill_ref_id) ?: BillRef::next(BillRef::LEGACY);
        });
    }

    public function typeName(): string
    {
        return config('billing.legacy_types.' . $this->bill_type_code, (string) $this->bill_type_code);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class, 'bill_ref_id', 'bill_ref_id')->orderBy('sort_order')->orderBy('id');
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class, 'bill_ref_id', 'bill_ref_id')->orderBy('id');
    }
}
