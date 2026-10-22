<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One charge on a bill (system BILL- or legacy LGB-). The bill's amount is the
 * sum of its items.
 */
class BillItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'bill_items';

    protected $fillable = ['bill_ref_id', 'item_code', 'name', 'quantity', 'rate', 'amount', 'sort_order'];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];
}
