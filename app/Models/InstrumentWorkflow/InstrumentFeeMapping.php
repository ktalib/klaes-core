<?php

namespace App\Models\InstrumentWorkflow;

use App\Models\RevenueItem;
use Illuminate\Database\Eloquent\Model;

/**
 * The revenue items an instrument type is billed with (Configurable Entries →
 * Revenue Items). Application fee on the application-fee bill; Registration
 * Fee and Approval Fee on the registration fee bill. A slot left empty, or a mapped item rated
 * ₦0, falls back to the instrument_fee_items tariff.
 */
class InstrumentFeeMapping extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'instrument_fee_mappings';

    public const SLOTS = [
        'application' => ['column' => 'application_revenue_item_id', 'label' => 'Application Fee', 'bill' => 'Application fee bill'],
        'registration' => ['column' => 'registration_revenue_item_id', 'label' => 'Registration Fee', 'bill' => 'registration fee bill'],
        'approval' => ['column' => 'approval_revenue_item_id', 'label' => 'Approval Fee', 'bill' => 'registration fee bill'],
    ];

    protected $fillable = ['instrument_type', 'application_revenue_item_id', 'registration_revenue_item_id', 'approval_revenue_item_id', 'updated_by_name'];

    public function applicationItem()
    {
        return $this->belongsTo(RevenueItem::class, 'application_revenue_item_id');
    }

    public function registrationItem()
    {
        return $this->belongsTo(RevenueItem::class, 'registration_revenue_item_id');
    }

    public function approvalItem()
    {
        return $this->belongsTo(RevenueItem::class, 'approval_revenue_item_id');
    }

    public static function forType(?string $instrumentType): ?self
    {
        $type = trim((string) $instrumentType);

        return $type === '' ? null : self::query()->where('instrument_type', $type)->first();
    }
}

