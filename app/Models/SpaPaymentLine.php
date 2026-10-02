<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * How one payment was split across the items of the bill it settles.
 *
 * The name and amount are COPIED from the bill line rather than joined to it,
 * for the same reason SpaBillLine copies from the tariff: a receipt printed
 * today must still reconcile after somebody edits a tariff amount or a bill
 * line is removed. The reference is kept for reporting; the copy is the record.
 */
class SpaPaymentLine extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_payment_lines';

    protected $fillable = ['spa_payment_id', 'spa_bill_line_id', 'name', 'amount_paid'];

    protected $casts = ['amount_paid' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(SpaPayment::class, 'spa_payment_id');
    }

    public function billLine()
    {
        return $this->belongsTo(SpaBillLine::class, 'spa_bill_line_id');
    }
}
