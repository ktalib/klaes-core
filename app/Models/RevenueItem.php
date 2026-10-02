<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One Revenue Sub-Head: a named rate with
 * its state Revenue Code. Edited in System Admin → Configurable Entries.
 *
 * Several names repeat with different codes and rates (e.g. Annual Ground Rent);
 * they are told apart by code only. Bills copy the rate onto their own lines, so
 * changing a rate never alters an issued bill.
 */
class RevenueItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'revenue_items';

    protected $fillable = ['rate_id', 'name', 'revenue_code', 'base_rate', 'is_active', 'updated_by_name'];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'is_active' => 'boolean',
        'rate_id' => 'integer',
    ];

    /** "Rate of Application Fee for Deed of Gift" → "Application Fee for Deed of Gift". */
    public function shortName(): string
    {
        return preg_replace('/^Rate of\s+/i', '', trim((string) $this->name));
    }

    /** The head a sub-head belongs to: "Application Fee", "Registration Fee", "Penalty Fee", "Annual Ground Rent"… */
    public function category(): string
    {
        return self::categoryOf((string) $this->name);
    }

    public static function categoryOf(string $name): string
    {
        $short = preg_replace('/^Rate of\s+/i', '', trim($name));

        return trim(preg_split('/\s+for\s+/i', $short, 2)[0]);
    }

    /** "₦3,750.00 · 4000408 · Application Fee for Deed of Assignment" for pickers. */
    public function optionLabel(): string
    {
        return $this->revenue_code . ' · ' . $this->shortName() . ' · ₦' . number_format((float) $this->base_rate, 2);
    }
}

