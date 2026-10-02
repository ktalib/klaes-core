<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A configurable bill: its formula over the variables in billing_type_items
 * (System Admin → Configurable Entries → Bill Formulas).
 *
 * The formula uses the item variables with +, - and brackets, e.g.
 * "REG_FEE + APPROVAL_FEE + STAMP_DUTY + DICING + TAX". An empty formula adds
 * every active item.
 */
class BillingType extends Model
{
    public const LEGAL_SEARCH_TOKEN = 'legal_search_token';
    public const INSTRUMENT_APPLICATION_FEE = 'instrument_application_fee';
    public const INSTRUMENT_REGISTRATION_FEE = 'instrument_registration_fee';

    protected $connection = 'sqlsrv';
    protected $table = 'billing_types';

    protected $fillable = ['code', 'name', 'module', 'formula', 'description', 'sort_order', 'is_active', 'effective_from', 'effective_to', 'updated_by_name'];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'sort_order' => 'integer',
    ];

    public static function findCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }

    public function items()
    {
        return $this->hasMany(BillingTypeItem::class)->orderBy('sequence')->orderBy('id');
    }

    /** Active and within its effective dates on $date (today by default). */
    public function isEffective(?\DateTimeInterface $date = null): bool
    {
        $day = \Illuminate\Support\Carbon::instance($date ?? now())->startOfDay();

        return $this->is_active
            && (!$this->effective_from || $this->effective_from->lte($day))
            && (!$this->effective_to || $this->effective_to->gte($day));
    }
}

