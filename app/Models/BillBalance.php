<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillBalance extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';

    protected $table = 'deeds_bill_balances_metadata';

    protected $fillable = [
        'reference',
        'file_number',
        'applicant_name',
        'applicant_address',
        'location_station',
        'district',
        'area',
        'unit_cost',
        'amount',
        'prepared_at',
        'date_of_expiry',
        'rent_from_year',
        'rent_to_year',
        'app_house_number',
        'app_plot_number',
        'app_street_name',
        'app_district',
        'app_lga',
        'app_state',
        'loc_house_number',
        'loc_plot_number',
        'loc_street_name',
        'loc_district',
        'loc_lga',
        'loc_state',
        'billing_id',
    ];

    protected $casts = [
        'area' => 'float',
        'unit_cost' => 'float',
        'amount' => 'float',
        'prepared_at' => 'datetime',
        'date_of_expiry' => 'date',
        'rent_from_year' => 'integer',
        'rent_to_year' => 'integer',
        'billing_id' => 'integer',
    ];

    /**
     * Latest Deeds Bill Balance for a file number, joined to its billing record
     * for payment status + the fee breakdown. Returns a flat array (or null).
     * Shared by the web Quick Search and mobile file-search result cards.
     */
    public static function summaryForFile(?string $fileNumber): ?array
    {
        if (!$fileNumber) {
            return null;
        }

        $t = (new self)->getTable();
        $rec = self::query()
            ->leftJoin('billing', 'billing.id', '=', "$t.billing_id")
            ->whereRaw("UPPER(LTRIM(RTRIM($t.file_number))) = ?", [strtoupper(trim($fileNumber))])
            ->orderByDesc("$t.id")
            ->select(
                "$t.reference", "$t.area", "$t.unit_cost", "$t.amount", "$t.date_of_expiry",
                "$t.rent_from_year", "$t.rent_to_year",
                'billing.Payment_Status', 'billing.bill_balance_reciept', 'billing.bill_balance_date',
                'billing.Site_Plan_Fee', 'billing.survey_fee', 'billing.Processing_Fee',
                'billing.Betterment_Charges', 'billing.Land_Use_Charge', 'billing.Penalty_Fees'
            )
            ->first();

        if (!$rec) {
            return null;
        }

        // Fee labels mirror the "Generate Bill Balance" wizard (Fees & Charges step).
        $fees = [
            'Registration Fees'   => (float) $rec->Site_Plan_Fee,
            'Survey Fees'         => (float) $rec->survey_fee,
            'Preparation Fees'    => (float) $rec->Processing_Fee,
            'Compensation Fees'   => (float) $rec->Betterment_Charges,
            'Development Charges'  => (float) $rec->Land_Use_Charge,
        ];

        $rentPerAnnum = $rec->amount !== null ? (float) $rec->amount : null;
        // Cumulative Annual = Rent Per Annum × |From − To| (matches the wizard).
        $years = ($rec->rent_from_year && $rec->rent_to_year)
            ? abs((int) $rec->rent_to_year - (int) $rec->rent_from_year)
            : null;
        $cumulativeAnnual = ($rentPerAnnum !== null && $years !== null) ? $rentPerAnnum * $years : null;

        $feesTotal       = array_sum($fees);
        $totalAmount     = ($cumulativeAnnual ?? 0) + $feesTotal;
        $amountDeposited = (float) $rec->Penalty_Fees;   // "Amount Deposited" in the wizard

        return [
            'reference'         => $rec->reference,
            'area'              => $rec->area !== null ? (float) $rec->area : null,
            'unit_cost'         => $rec->unit_cost !== null ? (float) $rec->unit_cost : null,
            'amount'            => $rentPerAnnum,   // rent per annum
            'rent_from_year'    => $rec->rent_from_year,
            'rent_to_year'      => $rec->rent_to_year,
            'cumulative_annual' => $cumulativeAnnual,
            'expiry'            => optional($rec->date_of_expiry)->format('Y-m-d'),
            'receipt'           => $rec->bill_balance_reciept,
            'receipt_date'      => optional($rec->bill_balance_date)->format('Y-m-d'),
            'payment_status'    => $rec->Payment_Status ?: 'Pending',
            'fees'              => $fees,
            'fees_total'        => $feesTotal,
            'amount_deposited'  => $amountDeposited,
            'total_amount'      => $totalAmount,
            'balance_due'       => $totalAmount - $amountDeposited,
        ];
    }
}
