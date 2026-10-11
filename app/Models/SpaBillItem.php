<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A line of the SPAS contravention tariff.
 *
 * This is settings, not a transaction: it says what a contravention costs, and
 * every bill raised automatically is composed of the active rows here.
 *
 * Amounts are edited on System Admin -> Configurable Entries -> SPAS Bill
 * Items (App\Services\SpaBillItemTariff). Changing an AMOUNT affects bills
 * raised FROM NOW ON — existing bills keep their own copy of the amount in
 * spa_bill_lines, so an old bill still adds up after a tariff change. Changing
 * a NAME is carried onto existing bills and payments as well.
 */
class SpaBillItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'spa_bill_items';

    protected $fillable = [
        'name', 'description', 'amount', 'is_active', 'sort_order',
        'is_removed', 'removed_at', 'removed_by', 'is_on_demand',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'is_active'    => 'boolean',
        'is_removed'   => 'boolean',
        'is_on_demand' => 'boolean',
        'removed_at'   => 'datetime',
    ];

    private static ?bool $supportsRemoval = null;
    private static ?bool $supportsOnDemand = null;

    /**
     * Whether "add when needed" can be recorded (migration 2026_10_09_150000).
     * Before it runs every item is charged automatically, as it always was.
     */
    public static function supportsOnDemand(): bool
    {
        return self::$supportsOnDemand ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')
            ->hasColumn('spa_bill_items', 'is_on_demand');
    }

    /**
     * Items an officer adds to a particular bill when needed (a Penalty Fee),
     * at the tariff amount. Never composed into a bill automatically.
     */
    public static function onDemand()
    {
        if (! self::supportsOnDemand()) {
            return collect();
        }

        return static::notRemoved()
            ->where('is_active', 1)
            ->where('is_on_demand', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether "removed" can be recorded (migration 2026_10_08_120000). Before it
     * runs, removing an item only switches it off, as it always did.
     */
    public static function supportsRemoval(): bool
    {
        return self::$supportsRemoval ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')
            ->hasColumn('spa_bill_items', 'is_removed');
    }

    /** Items still on the tariff: everything except those removed from it. */
    public function scopeNotRemoved($query)
    {
        return self::supportsRemoval() ? $query->where('is_removed', 0) : $query;
    }

    /** Ids of items removed from the tariff (empty before the migration). */
    public static function removedIds(): array
    {
        return self::supportsRemoval()
            ? static::where('is_removed', 1)->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
    }

    /**
     * The items a new bill should be composed of.
     *
     * Zero-amount items are excluded: the table seeds at 0 so nothing is
     * charged before an officer has set a real tariff, and a zero line on a
     * bill is noise rather than information.
     */
    public static function billable()
    {
        return static::notRemoved()
            ->where('is_active', 1)
            ->where('amount', '>', 0)
            // Items added only when needed (a Penalty Fee) are left for the officer.
            ->when(self::supportsOnDemand(), fn ($q) => $q->where('is_on_demand', 0))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
