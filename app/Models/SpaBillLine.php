<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What a bill was actually composed of, as at the moment it was raised.
 *
 * The name and amount are COPIED from the tariff rather than joined to it. A
 * bill raised under last year's tariff has to stay explainable after somebody
 * edits an amount, otherwise its total stops matching anything and the figure
 * becomes unauditable.
 */
class SpaBillLine extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_bill_lines';

    protected $fillable = ['spa_bill_id', 'spa_bill_item_id', 'name', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function bill()
    {
        return $this->belongsTo(SpaBill::class, 'spa_bill_id');
    }
}
