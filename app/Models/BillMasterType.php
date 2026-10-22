<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Bill Master category (KLAES REV-M → Billing). Initial Bill's Primary /
 * PuA / SuA point at it through parent_code.
 */
class BillMasterType extends Model
{
    public const GROUP_AUTOMATED = 'automated';
    public const GROUP_LEGACY = 'legacy';

    protected $connection = 'sqlsrv';
    protected $table = 'bill_master_types';

    protected $fillable = ['code', 'name', 'bill_group', 'parent_code', 'sort_order', 'is_active', 'settings_tab', 'accepts_payment', 'payment_methods',
        'is_recurring', 'payment_cycle', 'remind_days_before', 'revenue_code'];

    protected $casts = [
        'is_active' => 'boolean',
        'accepts_payment' => 'boolean',
        'sort_order' => 'integer',
        'is_recurring' => 'boolean',
        'remind_days_before' => 'integer',
    ];

    /** Payment methods this type offers (keys of config('billing.payment_methods')). */
    public function paymentMethods(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->payment_methods))));
    }

    public function bills()
    {
        return $this->hasMany(BillMaster::class, 'bill_type_code', 'code');
    }

    /**
     * The bill type's Revenue Code (one per bill type, set in Bill Settings; user,
     * 2026-10-21). Primary / PuA / SuA use Initial Bill's.
     */
    public function revenueCode(): ?string
    {
        if ($this->revenue_code) {
            return $this->revenue_code;
        }

        return $this->parent_code
            ? self::query()->where('code', $this->parent_code)->value('revenue_code')
            : null;
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_code', 'code')->orderBy('sort_order');
    }
}
