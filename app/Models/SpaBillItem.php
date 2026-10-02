<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A line of the SPAS contravention tariff.
 *
 * This is settings, not a transaction: it says what a contravention costs, and
 * every bill raised automatically is composed of the active rows here.
 *
 * Amounts are edited in "Bill Items Setting" on the bills page. Changing one
 * affects bills raised FROM NOW ON — existing bills keep their own copy of the
 * name and amount in spa_bill_lines, so an old bill still adds up after a
 * tariff change.
 */
class SpaBillItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_bill_items';

    protected $fillable = [
        'name', 'description', 'amount', 'is_active', 'sort_order',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * The items a new bill should be composed of.
     *
     * Zero-amount items are excluded: the table seeds at 0 so nothing is
     * charged before an officer has set a real tariff, and a zero line on a
     * bill is noise rather than information.
     */
    public static function billable()
    {
        return static::where('is_active', 1)
            ->where('amount', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
