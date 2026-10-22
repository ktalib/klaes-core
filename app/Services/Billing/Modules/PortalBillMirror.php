<?php

namespace App\Services\Billing\Modules;

use App\Models\BillMaster;
use App\Models\BillPayment;
use App\Services\Billing\BillMasterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Public-portal payments → Bill Master, as already paid (phase E,
 * docs/plans/KLAES_REVM_Bill_Master_Plan.md; decided 2026-10-19).
 *
 *   Legal Search online payment   legal_search_online_payments   type legal_search
 *   PHS token purchase / top-up   phs_token_transactions         type phs_token_topup
 *   PHS onboarding invoice        phs_onboarding_requests        type phs_onboarding
 *
 * The portals keep taking payment (Paystack, or bank transfer validated by PHS
 * admin). When a payment is confirmed the bill is copied into REV-M once, with
 * the payment recorded against its BILL- ref, so it is listed there and the
 * official RCT- receipt can be generated. Never throws into the portal's save.
 * $force (bills:consolidate) copies a payment that was already paid before Bill Master.
 */
class PortalBillMirror
{
    public function __construct(private BillMasterService $bills)
    {
    }

    /** LegalSearchOnlinePayment saved. Amounts are in kobo (Paystack's unit). */
    public function legalSearch($payment, bool $force = false): void
    {
        if ($payment->status !== 'paid' || !($force || $payment->wasRecentlyCreated || $payment->wasChanged('status'))) {
            return;
        }
        $amount = round(((float) $payment->amount) / 100, 2);
        $file = $payment->file_number ? $this->bills->fileDetails($payment->file_number) : [];
        $count = (int) ($payment->file_count ?? 0) ?: 1;

        $this->paid('legal_search', 'legal_search_online_payments', (int) $payment->id, [
            'file_number' => $payment->file_number ?: null,
            'file_indexing_id' => $file['file_indexing_id'] ?? null,
            'payer_name' => $payment->email ?: null,
            'payer_email' => $payment->email ?: null,
            'plot_no' => $file['plot_no'] ?? null,
            'district' => $file['district'] ?? null,
            'lga' => $file['lga'] ?? null,
            'location' => $file['location'] ?? null,
            'description' => 'Online legal search' . ($count > 1 ? " ({$count} files)" : ''),
        ], [['code' => 'LEGAL_SEARCH', 'name' => 'Online legal search' . ($count > 1 ? " × {$count} files" : ''), 'amount' => $amount]],
            'paystack', (string) $payment->reference, $amount, $payment->paid_at ?? now());
    }

    /** PhsTokenTransaction saved: a completed purchase / top-up. */
    public function phsTokens($txn, bool $force = false): void
    {
        if (!in_array($txn->type, ['purchase', 'topup'], true) || $txn->status !== 'completed'
            || !($force || $txn->wasRecentlyCreated || $txn->wasChanged('status')) || (float) $txn->amount <= 0) {
            return;
        }
        $institution = method_exists($txn, 'institution') ? $txn->institution : null;
        $method = ($txn->payment_method ?? '') === 'paystack' ? 'paystack' : (($txn->payment_method ?? '') ?: 'bank_transfer');
        $reference = $txn->payment_gateway_reference ?: ($txn->bank_reference ?: ($txn->reference_no ?: 'PHS-TXN-' . $txn->id));
        $tokens = (int) ($txn->tokens ?? 0);

        $this->paid('phs_token_topup', 'phs_token_transactions', (int) $txn->id, [
            'payer_name' => $institution->name ?? ($institution->organization_name ?? null),
            'payer_email' => $institution->email ?? null,
            'description' => 'PHS tokens' . ($txn->package_name ? " · {$txn->package_name}" : '') . ($tokens ? " · {$tokens} tokens" : ''),
        ], [['code' => 'PHS_TOKENS', 'name' => 'PHS token ' . ($txn->type === 'topup' ? 'top-up' : 'purchase') . ($tokens ? " ({$tokens} tokens)" : ''), 'amount' => (float) $txn->amount]],
            $method, (string) $reference, (float) $txn->amount, $txn->updated_at ?? now());
    }

    /** PhsOnboardingRequest saved: the onboarding invoice is paid. */
    public function phsOnboarding($request, bool $force = false): void
    {
        if (($request->payment_status ?? null) !== 'completed' || !($force || $request->wasRecentlyCreated || $request->wasChanged('payment_status'))) {
            return;
        }
        $amount = (float) ($request->verified_amount ?: ($request->payment_amount ?: $request->expected_amount));
        if ($amount <= 0) {
            return;
        }
        $reference = $request->paystack_reference ?: ($request->payment_reference ?: ($request->invoice_number ?: 'PHS-ONB-' . $request->id));

        $this->paid('phs_onboarding', 'phs_onboarding_requests', (int) $request->id, [
            'payer_name' => $request->organization_name ?: $request->contact_name,
            'payer_phone' => $request->phone ?: null,
            'payer_email' => $request->contact_email ?: null,
            'payer_address' => $request->address ?: null,
            'description' => 'PHS onboarding' . ($request->invoice_number ? " · invoice {$request->invoice_number}" : '')
                . ($request->initial_token_package ? " · {$request->initial_token_package}" : ''),
        ], [['code' => 'PHS_ONBOARDING', 'name' => 'PHS onboarding / subscription', 'amount' => $amount]],
            $request->paystack_reference ? 'paystack' : 'bank_transfer', (string) $reference, $amount, $request->payment_verified_at ?? ($request->payment_received_at ?? now()));
    }

    private function paid(string $type, string $table, int $sourceId, array $attrs, array $lines, string $method, string $txnId, float $amount, $paidAt): void
    {
        try {
            if (BillMaster::query()->where('source_table', $table)->where('source_id', $sourceId)->exists()) {
                return; // copied once; a later save of the portal row changes nothing here
            }
            $actor = (string) (auth()->user()->name ?? auth()->id() ?? 'portal');

            DB::connection('sqlsrv')->transaction(function () use ($type, $table, $sourceId, $attrs, $lines, $method, $txnId, $amount, $paidAt, $actor) {
                $bill = $this->bills->mirror($type, $table, $sourceId, $attrs, $lines, $actor);
                BillPayment::create([
                    'bill_ref_id' => $bill->bill_ref_id,
                    'method' => $method,
                    'transaction_id' => $txnId,
                    'amount' => round($amount, 2),
                    'paid_at' => $paidAt,
                    'status' => BillPayment::STATUS_SUCCESSFUL,
                    'recorded_by' => $actor,
                    'notes' => 'Paid in the public portal.',
                ]);
                $this->bills->refreshTotals($bill, $actor);
            });
        } catch (\Throwable $e) {
            Log::warning('Portal payment not mirrored to Bill Master', ['table' => $table, 'id' => $sourceId, 'error' => $e->getMessage()]);
        }
    }
}
