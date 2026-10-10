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
 *
 * The NAME is the exception: renaming an item in the tariff renames its lines
 * on existing bills too (SpaBillItemTariff::renameOnExistingBills), so every
 * table and receipt shows the current name. The amount never follows.
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

    public function item()
    {
        return $this->belongsTo(SpaBillItem::class, 'spa_bill_item_id');
    }

    /**
     * The name to SHOW: the tariff item's current name, so a rename reads the
     * same everywhere, even on a bill raised before it. Falls back to the name
     * the line was raised with when it has no item. The amount is never looked up.
     */
    public function getDisplayNameAttribute(): string
    {
        $current = $this->spa_bill_item_id ? optional($this->item)->name : null;

        return (string) ($current ?: $this->name);
    }
}
