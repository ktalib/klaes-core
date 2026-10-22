<?php

namespace App\Services\Billing;

use App\Models\BillMaster;
use App\Models\BillPayment;
use App\Models\BillReceipt;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;

/**
 * Receipting → Generate Receipt (docs/plans/KLAES_REVM_Bill_Master_Plan.md, 3.3).
 *
 * One official Ministry receipt per successful payment, numbered
 * RCT-{year}-{000001} by BillRef, printed as Original, Duplicate and
 * Triplicate. Printing again is a reprint and is counted.
 */
class BillReceiptService
{
    public function __construct(private BillMasterService $bills)
    {
    }

    public function issue(BillPayment $payment, string $actor): BillReceipt
    {
        if ($payment->status !== BillPayment::STATUS_SUCCESSFUL) {
            throw WorkflowGuardException::because('Only a successful payment can be receipted.');
        }
        if ($payment->receipt_no || BillReceipt::query()->where('bill_payment_id', $payment->id)->where('status', 'issued')->exists()) {
            throw WorkflowGuardException::because("This payment already has receipt {$payment->receipt_no}.");
        }

        $bill = BillMaster::query()->where('bill_ref_id', $payment->bill_ref_id)->first();
        if (!$bill) {
            throw WorkflowGuardException::because("Bill {$payment->bill_ref_id} was not found.");
        }

        return DB::connection('sqlsrv')->transaction(function () use ($payment, $bill, $actor) {
            $receipt = BillRef::retrying(fn () => BillReceipt::create([
                'bill_ref_id' => $payment->bill_ref_id,
                'bill_payment_id' => $payment->id,
                'amount' => $payment->amount,
                'status' => 'issued',
                'issued_by' => $actor,
                'issued_at' => now(),
            ]));

            $payment->update(['receipt_no' => $receipt->receipt_no, 'receipt_date' => now()->toDateString()]);
            $this->bills->refreshTotals($bill, $actor);

            return $receipt;
        });
    }

    /** Record a print: the first prints all three copies; any later one is a reprint. */
    public function markPrinted(BillReceipt $receipt): bool
    {
        $reprint = (int) $receipt->copies_printed > 0;

        $receipt->update([
            'copies_printed' => count(BillReceipt::COPIES),
            'reprint_count' => (int) $receipt->reprint_count + ($reprint ? 1 : 0),
            'last_printed_at' => now(),
        ]);

        return $reprint;
    }
}
