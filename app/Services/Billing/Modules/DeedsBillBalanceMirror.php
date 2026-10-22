<?php

namespace App\Services\Billing\Modules;

use App\Models\BillBalance;
use App\Models\BillMaster;
use App\Services\Billing\BillMasterService;

/**
 * Deeds Bill Balance → Bill Master (phase E, docs/plans/KLAES_REVM_Bill_Master_Plan.md).
 *
 * The lines follow the printed bill (resources/views/bill_balance/print.blade.php):
 * rent per annum × years, the five fees, less the amount deposited (Penalty_Fees),
 * so the Bill Master total is the balance due the print shows.
 */
class DeedsBillBalanceMirror
{
    public const SOURCE_TABLE = 'deeds_bill_balances_metadata';
    public const TYPE = 'bill_balance';

    public function __construct(private BillMasterService $bills)
    {
    }

    /** @param array<string, mixed> $fees the billing row's fee fields (Site_Plan_Fee, survey_fee, …) */
    public function sync(BillBalance $balance, array $fees, string $actor, bool $create = false): ?BillMaster
    {
        // Only bills raised under Bill Master are kept in step here; older DBBL-
        // records come in through the approved consolidation (phase F).
        if (!$create && !BillMaster::query()->where('source_table', self::SOURCE_TABLE)->where('source_id', $balance->id)->exists()) {
            return null;
        }

        return $this->bills->mirror(self::TYPE, self::SOURCE_TABLE, $balance->id ?: null, $this->attrs($balance), $this->lines($balance, $fees), $actor);
    }

    public function attrs(BillBalance $balance): array
    {
        $district = $balance->loc_district ?: $balance->district;

        return [
            'file_number' => $balance->file_number ?: null,
            'payer_name' => $balance->applicant_name ?: null,
            'payer_address' => $balance->applicant_address ?: null,
            'plot_no' => $balance->loc_plot_number ?: null,
            'district' => $district ?: null,
            'lga' => $balance->loc_lga ?: null,
            'location' => BillMasterService::location($district, $balance->loc_lga, $balance->loc_state ?: 'Kano State'),
            'description' => 'Bill Balance',
            'from_year' => $balance->rent_from_year ? (int) $balance->rent_from_year : null,
            'to_year' => $balance->rent_to_year ? (int) $balance->rent_to_year : null,
            'due_date' => optional($balance->date_of_expiry)->toDateString(),
        ];
    }

    /** @return list<array{name: string, amount: float, code: string}> */
    public function lines(BillBalance $balance, array $fees): array
    {
        $rent = (float) ($balance->amount ?? 0);
        $from = $balance->rent_from_year;
        $to = $balance->rent_to_year;
        $years = ($from && $to) ? abs((int) $to - (int) $from) : 0;

        return [
            ['code' => 'RENT', 'name' => 'Rent ₦' . number_format($rent, 2) . " × {$years} " . ($years === 1 ? 'year' : 'years') . ($from && $to ? " ({$from}–{$to})" : ''), 'amount' => $rent * $years],
            ['code' => 'REGISTRATION', 'name' => 'Registration fee', 'amount' => (float) ($fees['Site_Plan_Fee'] ?? 0)],
            ['code' => 'SURVEY', 'name' => 'Survey fee', 'amount' => (float) ($fees['survey_fee'] ?? 0)],
            ['code' => 'PREPARATION', 'name' => 'Preparation fee', 'amount' => (float) ($fees['Processing_Fee'] ?? 0)],
            ['code' => 'COMPENSATION', 'name' => 'Compensation fee', 'amount' => (float) ($fees['Betterment_Charges'] ?? 0)],
            ['code' => 'DEVELOPMENT', 'name' => 'Development charge', 'amount' => (float) ($fees['Land_Use_Charge'] ?? 0)],
            ['code' => 'DEPOSIT', 'name' => 'Less: amount deposited', 'amount' => -1 * (float) ($fees['Penalty_Fees'] ?? 0)],
        ];
    }
}
