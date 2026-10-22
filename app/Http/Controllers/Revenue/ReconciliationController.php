<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\BillMasterType;
use App\Models\BillPayment;
use App\Services\Billing\BillReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * KLAES REV-M → Reconciliation: successful payments checked against the bank,
 * Paystack or the POS settlement report (BillReconciliationService), plus bills
 * whose totals have drifted from their payments.
 *
 * Module "Billing": view opens the page, edit reconciles / disputes / re-verifies.
 */
class ReconciliationController extends Controller
{
    use RunsWorkflowActions;

    public function index(Request $request, BillReconciliationService $recon)
    {
        $this->authorizeAction('view');

        $filters = [
            'recon' => (string) $request->query('recon', 'unreconciled'),
            'method' => (string) $request->query('method', ''),
            'from' => (string) $request->query('from', ''),
            'to' => (string) $request->query('to', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $base = DB::connection('sqlsrv')->table('bill_payments as p')
            ->join('bill_master as b', 'b.bill_ref_id', '=', 'p.bill_ref_id')
            ->leftJoin('bill_master_types as t', 't.code', '=', 'b.bill_type_code');

        // Summary across every successful payment (and Paystack left pending), before the filters.
        $summary = (clone $base)->selectRaw("
            SUM(CASE WHEN p.status = 'successful' AND ISNULL(p.reconciliation_status, 'unreconciled') = 'unreconciled' THEN 1 ELSE 0 END) un_n,
            SUM(CASE WHEN p.status = 'successful' AND ISNULL(p.reconciliation_status, 'unreconciled') = 'unreconciled' THEN p.amount ELSE 0 END) un_amt,
            SUM(CASE WHEN p.status = 'successful' AND p.reconciliation_status = 'reconciled' THEN 1 ELSE 0 END) ok_n,
            SUM(CASE WHEN p.status = 'successful' AND p.reconciliation_status = 'reconciled' THEN p.amount ELSE 0 END) ok_amt,
            SUM(CASE WHEN p.status = 'successful' AND p.reconciliation_status = 'disputed' THEN 1 ELSE 0 END) dis_n,
            SUM(CASE WHEN p.status = 'successful' AND p.reconciliation_status = 'disputed' THEN p.amount ELSE 0 END) dis_amt,
            SUM(CASE WHEN p.status = 'pending' AND p.method = 'paystack' THEN 1 ELSE 0 END) pend_n,
            SUM(CASE WHEN p.status = 'pending' AND p.method = 'paystack' THEN p.amount ELSE 0 END) pend_amt")->first();

        $query = clone $base;
        if ($filters['recon'] === 'pending') {
            $query->where('p.status', BillPayment::STATUS_PENDING)->where('p.method', BillPayment::METHOD_PAYSTACK);
        } else {
            $query->where('p.status', BillPayment::STATUS_SUCCESSFUL);
            if ($filters['recon'] === 'unreconciled') {
                $query->where(fn ($q) => $q->whereNull('p.reconciliation_status')->orWhere('p.reconciliation_status', BillPayment::RECON_UNRECONCILED));
            } elseif (in_array($filters['recon'], [BillPayment::RECON_RECONCILED, BillPayment::RECON_DISPUTED], true)) {
                $query->where('p.reconciliation_status', $filters['recon']);
            }
        }
        if ($filters['method'] !== '') {
            $query->where('p.method', $filters['method']);
        }
        if ($filters['from'] !== '') {
            $query->whereRaw('CAST(COALESCE(p.paid_at, p.created_at) AS date) >= ?', [$filters['from']]);
        }
        if ($filters['to'] !== '') {
            $query->whereRaw('CAST(COALESCE(p.paid_at, p.created_at) AS date) <= ?', [$filters['to']]);
        }
        if ($filters['q'] !== '') {
            $like = '%' . mb_strtolower($filters['q']) . '%';
            $query->where(function ($w) use ($like) {
                foreach (['p.transaction_id', 'p.settlement_ref', 'p.receipt_no', 'b.bill_ref_id', 'b.file_number', 'b.payer_name'] as $col) {
                    $w->orWhereRaw("LOWER({$col}) LIKE ?", [$like]);
                }
            });
        }

        $totals = (clone $query)->selectRaw('COUNT(*) n, COALESCE(SUM(p.amount), 0) amount')->first();
        $rows = $query->select([
            'p.id', 'p.method', 'p.transaction_id', 'p.amount', 'p.paid_at', 'p.created_at', 'p.status', 'p.receipt_no',
            'p.reconciliation_status', 'p.reconciled_at', 'p.reconciled_by', 'p.reconciliation_note', 'p.settlement_ref',
            'b.bill_ref_id', 'b.file_number', 'b.payer_name', 't.name as type_name',
        ])->orderByRaw('COALESCE(p.paid_at, p.created_at) DESC')->orderByDesc('p.id')->paginate(50)->withQueryString();

        $methods = DB::connection('sqlsrv')->table('bill_payments')->distinct()->orderBy('method')->pluck('method')
            ->mapWithKeys(fn ($m) => [$m => DashboardController::methodLabel($m)]);

        return view('revenue.reconciliation.index', [
            'rows' => $rows,
            'totals' => $totals,
            'summary' => $summary,
            'filters' => $filters,
            'methods' => $methods,
            'drift' => $recon->drift(),
            'canEdit' => $this->can('edit'),
            'paystackReady' => (bool) config('services.paystack.secret'),
            'indexUrl' => route('revenue.reconciliation.index'),
        ]);
    }

    /** Reconcile / dispute / reopen one or many payments (the sticky footer bar or a row's menu). */
    public function mark(Request $request, BillReconciliationService $recon)
    {
        $this->authorizeAction('edit');
        $data = $request->validate([
            'action' => 'required|in:reconcile,dispute,reopen',
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'settlement_ref' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        return $this->attempt(function () use ($data, $recon) {
            $n = match ($data['action']) {
                'reconcile' => $recon->reconcile($data['ids'], $data['settlement_ref'] ?? null, $data['note'] ?? null, $this->actor()),
                'dispute' => $recon->dispute($data['ids'], (string) ($data['note'] ?? ''), $this->actor()),
                'reopen' => $recon->reopen($data['ids'], $this->actor()),
            };
            $word = ['reconcile' => 'reconciled', 'dispute' => 'marked disputed', 'reopen' => 'set back to unreconciled'][$data['action']];

            return "{$n} payment" . ($n === 1 ? '' : 's') . " {$word}.";
        }, 'Saved.');
    }

    public function verify(int $payment, BillReconciliationService $recon)
    {
        $this->authorizeAction('edit');
        $row = BillPayment::query()->findOrFail($payment);

        return $this->attempt(fn () => $recon->verifyPaystack($row, $this->actor()), 'Checked with Paystack.');
    }

    public function fixTotals(string $ref, BillReconciliationService $recon)
    {
        $this->authorizeAction('edit');

        return $this->attempt(function () use ($ref, $recon) {
            $bill = $recon->fixTotals($ref, $this->actor());

            return "{$bill->bill_ref_id}: amount paid now ₦" . number_format((float) $bill->amount_paid, 2) . " ({$bill->status}).";
        }, 'Totals recomputed.');
    }

    private function can(string $action): bool
    {
        return (bool) auth()->user()?->canDo('Billing', $action);
    }

    private function authorizeAction(string $action): void
    {
        abort_unless($this->can($action), 403);
    }

    private function actor(): string
    {
        return (string) (Auth::user()->name ?? Auth::id());
    }
}
