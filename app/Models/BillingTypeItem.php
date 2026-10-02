<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One variable of a bill formula.
 *
 *   revenue_item  the amount is the revenue item's base rate (its Revenue Code prints on the bill)
 *   amount        a fixed amount entered here
 *   workflow      the module works the amount out (e.g. Stamp Duty on the consideration)
 */
class BillingTypeItem extends Model
{
    public const SOURCE_REVENUE_ITEM = 'revenue_item';
    public const SOURCE_AMOUNT = 'amount';
    public const SOURCE_WORKFLOW = 'workflow';

    public const SOURCE_LABELS = [
        'revenue_item' => 'Revenue item rate',
        'amount' => 'Fixed amount',
        'workflow' => 'Worked out by the module',
    ];

    protected $connection = 'sqlsrv';
    protected $table = 'billing_type_items';

    protected $fillable = ['billing_type_id', 'variable', 'label', 'source', 'revenue_item_id', 'amount', 'sequence', 'is_active', 'effective_from', 'effective_to', 'updated_by_name'];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'sequence' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function billingType()
    {
        return $this->belongsTo(BillingType::class);
    }

    public function revenueItem()
    {
        return $this->belongsTo(RevenueItem::class);
    }

    public function isEffective(?\DateTimeInterface $date = null): bool
    {
        $day = \Illuminate\Support\Carbon::instance($date ?? now())->startOfDay();

        return $this->is_active
            && (!$this->effective_from || $this->effective_from->lte($day))
            && (!$this->effective_to || $this->effective_to->gte($day));
    }
}

