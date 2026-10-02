<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

/**
 * A cadastral bill: the official "Right of Occupancy - Cadastral Fees and Area"
 * sheet (rebuild plan D1, §3b).
 *
 * Every line keeps its unit rate, quantity and amount ({line}_rate, _qty,
 * _amount), copied from CadastralSettings when the bill is issued. A bill
 * already saved keeps its own figures, so a reprint matches the copy the
 * applicant holds after an admin edits the rates.
 *
 * Those columns arrive with 2026_10_02_120000_reshape_cadastral_bills_for_fee_sheet
 * (run by hand, --path only). feeSheetInstalled() says whether it has run; until
 * then nothing can be issued. The old formula columns (area_rate_per_sqm,
 * land_use_multiplier, zone_multiplier, service_charge*, description_fee*) stay
 * on the table, unused. area_fee, pillar_fee, the pillar counts and grand_total
 * are still written, with the same meaning, because DashboardMetrics reads them.
 */
class CadastralBill extends CadastralModel
{
    protected $table = 'cadastral_bills';

    /** The sheet's lines, in sheet order, with the label the sheet prints. */
    public const LINES = [
        'investigation' => 'Investigation and Search',
        'beacon'        => 'Beacons',
        'area'          => 'Area fee',
        'delay'         => 'Delay',
        'transport'     => 'Transport',
        'field_work'    => 'Additional field work',
        'office_work'   => 'Office Work',
        'plan_print'    => 'Plan Prints',
    ];

    /** Columns the fee-sheet migration adds, beyond the {line}_rate/_qty/_amount triplets. */
    public const FEE_SHEET_EXTRA_COLUMNS = [
        'area_sqm', 'area_ha', 'schedule_column', 'schedule_row_ha', 'between_rows_rule', 'above_max_rule',
        'transport_band_label', 'transport_km', 'unconfirmed_rules',
        're_cad_ref', 'rofo_no', 'bill_date',
        'superseded_by_bill_id', 'cancelled_at', 'cancelled_by', 'cancel_reason',
    ];

    protected $casts = [
        'cadastral_plan_description_id' => 'integer',
        'pillar_count_government'       => 'integer',
        'pillar_count_private'          => 'integer',
        'billing_id'             => 'integer',
        // Old formula columns: unused since Phase 7, kept on the table.
        'area_rate_per_sqm'      => 'float',
        'land_use_multiplier'    => 'float',
        'zone_multiplier'        => 'float',
        'service_charge_percent' => 'float',
        'description_fee'        => 'float',
        'service_charge'         => 'float',
        'description_fee_amount' => 'float',
        // Still written: area line, beacon line, total.
        'pillar_unit_price'      => 'float',
        'area_fee'               => 'float',
        'pillar_fee'             => 'float',
        'grand_total'            => 'float',

        'investigation_rate' => 'float', 'investigation_qty' => 'integer', 'investigation_amount' => 'float',
        'beacon_rate'        => 'float', 'beacon_qty'        => 'integer', 'beacon_amount'        => 'float',
        'area_rate'          => 'float', 'area_qty'          => 'integer', 'area_amount'          => 'float',
        'delay_rate'         => 'float', 'delay_qty'         => 'integer', 'delay_amount'         => 'float',
        'transport_rate'     => 'float', 'transport_qty'     => 'integer', 'transport_amount'     => 'float',
        'field_work_rate'    => 'float', 'field_work_qty'    => 'integer', 'field_work_amount'    => 'float',
        'office_work_rate'   => 'float', 'office_work_qty'   => 'integer', 'office_work_amount'   => 'float',
        'plan_print_rate'    => 'float', 'plan_print_qty'    => 'integer', 'plan_print_amount'    => 'float',

        'area_sqm'              => 'float',
        'area_ha'               => 'float',
        'schedule_row_ha'       => 'float',
        'transport_km'          => 'float',
        'superseded_by_bill_id' => 'integer',
        'cancelled_by'          => 'integer',

        'issued_at'              => 'datetime',
        'cancelled_at'           => 'datetime',
        'bill_date'              => 'date',
        'receipt_date'           => 'date',
    ];

    public const STATUSES = ['Draft', 'Issued', 'Paid', 'Cancelled'];

    private static ?bool $installed = null;

    /** Every column the fee-sheet migration adds. */
    public static function feeSheetColumns(): array
    {
        $cols = [];
        foreach (array_keys(self::LINES) as $line) {
            array_push($cols, "{$line}_rate", "{$line}_qty", "{$line}_amount");
        }

        return array_merge($cols, self::FEE_SHEET_EXTRA_COLUMNS);
    }

    /**
     * True once 2026_10_02_120000 has run (every column it adds is present).
     * Cached for the request; forgetInstalled() clears it.
     */
    public static function feeSheetInstalled(): bool
    {
        if (self::$installed !== null) {
            return self::$installed;
        }

        $have = array_map('strtolower', Schema::connection('sqlsrv')->getColumnListing('cadastral_bills'));

        return self::$installed = array_diff(array_map('strtolower', self::feeSheetColumns()), $have) === [];
    }

    public static function forgetInstalled(): void
    {
        self::$installed = null;
    }

    public function planDescription(): BelongsTo
    {
        return $this->belongsTo(CadastralPlanDescription::class, 'cadastral_plan_description_id');
    }

    public function getTotalPillarsAttribute(): int
    {
        return (int) $this->pillar_count_government + (int) $this->pillar_count_private;
    }

    /** Everything on the bill that is neither the area fee nor the beacons. */
    public function getOtherAmountAttribute(): float
    {
        return round((float) $this->grand_total - (float) $this->area_fee - (float) $this->pillar_fee, 2);
    }

    /** True when the bill was issued under the fee sheet (it carries line snapshots). */
    public function getIsFeeSheetAttribute(): bool
    {
        return $this->investigation_amount !== null;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Paid'      => 'active',
            'Issued'    => 'completed',
            'Cancelled' => 'rejected',
            default     => 'pending',
        };
    }
}
