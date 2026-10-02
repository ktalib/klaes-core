<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * The tariff. `purpose` says which bill a line belongs on: the application fee
 * paid at submission, or the registration fee bill. Invoices copy the computed amount onto their
 * own lines, so changing a rate never alters an issued bill.
 */
class InstrumentFeeItem extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_fee_items';

    public const CALC_FLAT = 'flat';
    public const CALC_PERCENT = 'percent_of_consideration';

    protected $fillable = [
        'purpose',
        'code',
        'label',
        'calc_type',
        'amount',
        'rate',
        'min_amount',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:4',
        'min_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActiveFor($query, string $purpose)
    {
        return $query->where('purpose', $purpose)->where('is_active', true)->orderBy('sort_order');
    }

    public function amountFor(float $consideration): float
    {
        if ($this->calc_type === self::CALC_PERCENT) {
            return round(max($consideration * (float) $this->rate / 100, (float) $this->min_amount), 2);
        }

        return round((float) $this->amount, 2);
    }
}

