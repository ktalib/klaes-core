<?php

namespace App\Services\Billing;

use App\Models\BillMaster;
use App\Models\BillPayment;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * KLAES REV-M → Reconciliation (user, 2026-10-21).
 *
 * Every successful payment is checked against where the money actually landed:
 *   Paystack   re-verified with Paystack (amount must match to the kobo); a verified
 *              payment is reconciled automatically, also when the payer returns
 *   POS        ticked off against the POS settlement report: the officer enters the
 *              settlement / batch reference and marks it reconciled, or disputed
 *   captured / module receipts / invoices   ticked off against the receipt book
 *
 * A Paystack payment left Pending (the payer never came back) is re-verified here and
 * settles the bill if Paystack says it was paid.
 *
 * Bill totals: amount_paid on bill_master must equal its successful payments; any bill
 * that has drifted is listed and can be recomputed.
 */
class BillReconciliationService
{
    public function __construct(private BillMasterService $bills)
    {
    }

    /** Mark successful payments reconciled (bulk or one), with the settlement reference. */
    public function reconcile(array $paymentIds, ?string $settlementRef, ?string $note, string $actor): int
    {
        return $this->mark($paymentIds, BillPayment::RECON_RECONCILED, $settlementRef, $note, $actor);
    }

    public function dispute(array $paymentIds, string $note, string $actor): int
    {
        if (trim($note) === '') {
            throw WorkflowGuardException::because('Say why the payment is disputed.');
        }

        return $this->mark($paymentIds, BillPayment::RECON_DISPUTED, null, $note, $actor);
    }

    /** Back to unreconciled (a mistake, or a dispute settled the other way). */
    public function reopen(array $paymentIds, string $actor): int
    {
        return $this->mark($paymentIds, BillPayment::RECON_UNRECONCILED, null, null, $actor, true);
    }

    private function mark(array $ids, string $status, ?string $settlementRef, ?string $note, string $actor, bool $clear = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            throw WorkflowGuardException::because('Select at least one payment.');
        }

        $rows = BillPayment::query()->whereIn('id', $ids)->get();
        $notSuccessful = $rows->where('status', '!=', BillPayment::STATUS_SUCCESSFUL);
        if ($notSuccessful->isNotEmpty()) {
            throw WorkflowGuardException::because('Only successful payments are reconciled. ' . $notSuccessful->pluck('transaction_id')->filter()->implode(', ') . ' is not successful.');
        }
        if ($rows->count() !== count($ids)) {
            throw WorkflowGuardException::because('Some of the selected payments no longer exist. Reload the page.');
        }

        // Short chunks: klas has no snapshot isolation, so a long write blocks live reads.
        $n = 0;
        foreach (array_chunk($ids, 200) as $chunk) {
            $n += DB::connection('sqlsrv')->table('bill_payments')->whereIn('id', $chunk)->update([
                'reconciliation_status' => $status,
                'reconciled_at' => $clear ? null : now(),
                'reconciled_by' => $clear ? null : $actor,
                'reconciliation_note' => $clear ? null : (trim((string) $note) ?: null),
                'settlement_ref' => $clear ? null : (trim((string) $settlementRef) ?: DB::raw('settlement_ref')),
                'updated_at' => now(),
            ]);
        }

        return $n;
    }

    /**
     * Ask Paystack about a payment. A Pending one that Paystack says was paid settles
     * the bill (as if the payer had come back); a successful one is reconciled when the
     * amount matches, disputed when it does not.
     *
     * @return string what happened, for the officer
     */
    public function verifyPaystack(BillPayment $payment, string $actor): string
    {
        if ($payment->method !== BillPayment::METHOD_PAYSTACK) {
            throw WorkflowGuardException::because('Only Paystack payments can be checked with Paystack.');
        }
        if (!config('services.paystack.secret')) {
            throw WorkflowGuardException::because('Paystack is not configured on this server.');
        }

        if ($payment->status === BillPayment::STATUS_PENDING) {
            try {
                $this->bills->confirmPaystack((string) $payment->transaction_id, $actor);
            } catch (WorkflowGuardException $e) {
                return "Paystack has not confirmed {$payment->transaction_id}; it is marked failed.";
            }

            return "Paystack confirmed {$payment->transaction_id}: the payment is now successful and reconciled.";
        }

        $verify = Http::withToken(config('services.paystack.secret'))
            ->get(rtrim(config('services.paystack.base_url', 'https://api.paystack.co'), '/') . '/transaction/verify/' . urlencode((string) $payment->transaction_id));
        if (!$verify->successful() || $verify->json('data.status') === null) {
            throw WorkflowGuardException::because('Paystack did not return transaction ' . $payment->transaction_id . '. Please try again.');
        }

        $paid = $verify->json('data.status') === 'success';
        $match = $paid && (int) $verify->json('data.amount') === (int) round((float) $payment->amount * 100);
        $payment->update([
            'reconciliation_status' => $match ? BillPayment::RECON_RECONCILED : BillPayment::RECON_DISPUTED,
            'reconciled_at' => now(),
            'reconciled_by' => $actor . ' (Paystack verify)',
            'settlement_ref' => $verify->json('data.id') ? (string) $verify->json('data.id') : $payment->settlement_ref,
            'reconciliation_note' => $match ? null : Str::limit('Paystack says: status ' . $verify->json('data.status')
                . ', amount ₦' . number_format(((int) $verify->json('data.amount')) / 100, 2), 500),
        ]);

        return $match
            ? "Paystack confirms {$payment->transaction_id} for ₦" . number_format((float) $payment->amount, 2) . ': reconciled.'
            : "Paystack does not match {$payment->transaction_id}: marked disputed. {$payment->reconciliation_note}";
    }

    /** Bills whose amount_paid differs from the sum of their successful payments. */
    public function drift(int $limit = 50)
    {
        return collect(DB::connection('sqlsrv')->select("
            SELECT TOP {$limit} b.bill_ref_id, b.file_number, b.payer_name, b.bill_type_code, b.amount, b.amount_paid, b.status,
                   COALESCE(p.paid, 0) AS payments
            FROM bill_master b
            LEFT JOIN (SELECT bill_ref_id, SUM(amount) paid FROM bill_payments WHERE status = 'successful' GROUP BY bill_ref_id) p
                   ON p.bill_ref_id = b.bill_ref_id
            WHERE b.status <> 'cancelled' AND ABS(COALESCE(b.amount_paid, 0) - COALESCE(p.paid, 0)) > 0.004
            ORDER BY b.id DESC"));
    }

    /** Recompute one bill's amount paid and status from its payments. */
    public function fixTotals(string $billRef, string $actor): BillMaster
    {
        $bill = BillMaster::query()->where('bill_ref_id', $billRef)->firstOrFail();

        return $this->bills->refreshTotals($bill, $actor);
    }
}
