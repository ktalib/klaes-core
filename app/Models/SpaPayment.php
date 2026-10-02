<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaPayment extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_payments';

    protected $fillable = [
        'spa_bill_id', 'spa_application_id',
        'amount_paid', 'receipt_number', 'payment_date',
        'payment_method', 'recorded_by',
    ];

    protected $casts = [
        'amount_paid'  => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function bill()
    {
        return $this->belongsTo(SpaBill::class, 'spa_bill_id');
    }

    /**
     * How this payment was split across the bill's items.
     *
     * Empty for payments recorded before item-by-item entry existed — those
     * were a lump figure against the bill, and the receipt says so rather than
     * inventing a split.
     */
    public function lines()
    {
        return $this->hasMany(SpaPaymentLine::class, 'spa_payment_id');
    }

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }
}
