<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\BillMasterType;
use App\Models\BillPayment;
use App\Models\BillReceipt;
use App\Services\Billing\BillReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * KLAES REV-M → Receipting (docs/plans/KLAES_REVM_Bill_Master_Plan.md, 3.3).
 *
 *   Awaiting Receipt  successful payments with no official receipt yet → Generate receipt
 *   e-Receipt         issued receipts, viewed on screen
 *   Print Receipt     issued receipts, printed as Original / Duplicate / Triplicate
 *
 * Module "Generate Receipt": view opens the pages, create issues, print prints.
 */
class ReceiptController extends Controller
{
    use RunsWorkflowActions;

    private const ROLE = 'Generate Receipt';

    public function awaiting(Request $request)
    {
        $this->authorizeAction('view');

        $query = $this->paymentQuery()
            ->where('p.status', BillPayment::STATUS_SUCCESSFUL)
            ->where(fn ($q) => $q->whereNull('p.receipt_no')->orWhere('p.receipt_no', ''));
        $filters = $this->filters($request, $query, ['p.transaction_id']);

        $totals = (clone $query)->selectRaw('COUNT(*) n, COALESCE(SUM(p.amount), 0) amount')->first();

        return view('revenue.receipts.awaiting', [
            'rows' => $query->select($this->paymentColumns())->orderBy('p.paid_at')->orderBy('p.id')->paginate(25)->withQueryString(),
            'totals' => $totals,
            'filters' => $filters,
            'types' => $this->types(),
            'canIssue' => $this->can('create'),
            'canPrint' => $this->can('print'),
            'indexUrl' => route('revenue.receipts.awaiting'),
        ]);
    }

    public function issue(int $payment, BillReceiptService $receipts)
    {
        $this->authorizeAction('create');
        $row = BillPayment::query()->findOrFail($payment);

        return $this->attempt(function () use ($row, $receipts) {
            $receipt = $receipts->issue($row, (string) (Auth::user()->name ?? Auth::id()));
            session()->flash('rev_print_receipt', $receipt->receipt_no);

            return "Receipt {$receipt->receipt_no} generated for {$row->bill_ref_id}.";
        }, 'Receipt generated.');
    }

    public function index(Request $request, string $mode)
    {
        abort_unless(in_array($mode, ['e-receipt', 'print'], true), 404);
        $this->authorizeAction('view');

        $query = DB::connection('sqlsrv')->table('bill_receipts as r')
            ->join('bill_master as b', 'b.bill_ref_id', '=', 'r.bill_ref_id')
            ->leftJoin('bill_payments as p', 'p.id', '=', 'r.bill_payment_id')
            ->leftJoin('bill_master_types as t', 't.code', '=', 'b.bill_type_code');
        $filters = $this->filters($request, $query, ['r.receipt_no', 'p.transaction_id']);

        $totals = (clone $query)->where('r.status', 'issued')->selectRaw('COUNT(*) n, COALESCE(SUM(r.amount), 0) amount')->first();

        $rows = $query->select([
            'r.receipt_no', 'r.amount', 'r.status', 'r.issued_at', 'r.issued_by', 'r.copies_printed', 'r.reprint_count',
            'b.bill_ref_id', 'b.file_number', 'b.payer_name', 'b.plot_no', 'b.district', 'b.lga', 'b.location',
            'p.method', 'p.transaction_id', 't.name as type_name',
        ])->orderByDesc('r.id')->paginate(25)->withQueryString();

        return view('revenue.receipts.index', [
            'mode' => $mode,
            'rows' => $rows,
            'totals' => $totals,
            'awaiting' => $this->paymentQuery()->where('p.status', BillPayment::STATUS_SUCCESSFUL)
                ->where(fn ($q) => $q->whereNull('p.receipt_no')->orWhere('p.receipt_no', ''))->count(),
            'filters' => $filters,
            'types' => $this->types(),
            'canPrint' => $this->can('print'),
            'pageTitle' => $mode === 'print' ? 'Print Receipt' : 'e-Receipt',
            'pageDescription' => $mode === 'print' ? 'Print official receipts: Original, Duplicate and Triplicate' : 'Official receipts, viewed on screen',
            'indexUrl' => route('revenue.receipts.index', $mode),
        ]);
    }

    /** e-Receipt: the official receipt on screen (one copy). */
    public function show(string $no)
    {
        $this->authorizeAction('view');

        return view('revenue.receipts.official', $this->receiptData($no) + ['copies' => ['e-Receipt'], 'screen' => true, 'reprint' => false]);
    }

    /** The official receipt in three copies; a second print is marked REPRINT. */
    public function print(string $no, BillReceiptService $receipts)
    {
        $this->authorizeAction('print');
        $data = $this->receiptData($no);
        abort_if($data['receipt']->status !== 'issued', 410, 'This receipt is cancelled.');

        $reprint = $receipts->markPrinted($data['receipt']);

        return view('revenue.receipts.official', $data + ['copies' => BillReceipt::COPIES, 'screen' => false, 'reprint' => $reprint]);
    }

    private function receiptData(string $no): array
    {
        $receipt = BillReceipt::query()->with('payment')->where('receipt_no', $no)->firstOrFail();
        $bill = \App\Models\BillMaster::query()->with(['type', 'items'])->where('bill_ref_id', $receipt->bill_ref_id)->firstOrFail();

        // The balance as it stood right after THIS payment, so an early receipt of a
        // part-paid bill still shows what was left to pay.
        $paidToDate = BillPayment::query()->where('bill_ref_id', $bill->bill_ref_id)
            ->where('status', BillPayment::STATUS_SUCCESSFUL)
            ->when($receipt->bill_payment_id, fn ($q) => $q->where('id', '<=', $receipt->bill_payment_id))
            ->sum('amount');

        return [
            'receipt' => $receipt,
            'bill' => $bill,
            'payment' => $receipt->payment,
            'words' => \App\Services\ConsentBillCalculator::amountInWords((float) $receipt->amount),
            'outstanding' => max(0, round((float) $bill->amount - (float) $paidToDate, 2)),
            // The official sample prints the revenue officer's email under the signature line.
            'officerEmail' => $receipt->issued_by
                ? \App\Models\User::query() // issued_by holds User::name (first + last name)
                    ->whereRaw("LOWER(LTRIM(RTRIM(ISNULL(first_name, ''))) + ' ' + LTRIM(RTRIM(ISNULL(last_name, '')))) = ?", [mb_strtolower(trim($receipt->issued_by))])
                    ->value('email')
                : null,
        ];
    }

    private function paymentQuery()
    {
        return DB::connection('sqlsrv')->table('bill_payments as p')
            ->join('bill_master as b', 'b.bill_ref_id', '=', 'p.bill_ref_id')
            ->leftJoin('bill_master_types as t', 't.code', '=', 'b.bill_type_code');
    }

    private function paymentColumns(): array
    {
        return [
            'p.id', 'p.method', 'p.transaction_id', 'p.amount', 'p.paid_at',
            'b.bill_ref_id', 'b.file_number', 'b.payer_name', 'b.plot_no', 'b.district', 'b.lga', 'b.location', 'b.amount as bill_amount',
            't.name as type_name',
        ];
    }

    /** Case-insensitive search and the bill type filter, applied to a query joined on bill_master as b. */
    private function filters(Request $request, $query, array $extra): array
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
        ];

        if ($filters['q'] !== '') {
            $like = '%' . mb_strtolower($filters['q']) . '%';
            $query->where(function ($w) use ($like, $extra) {
                foreach (array_merge($extra, ['b.bill_ref_id', 'b.file_number', 'b.payer_name', 'b.plot_no', 'b.district', 'b.lga', 'b.location']) as $col) {
                    $w->orWhereRaw("LOWER({$col}) LIKE ?", [$like]);
                }
            });
        }
        if ($filters['type'] !== '') {
            $query->where('b.bill_type_code', $filters['type']);
        }

        return $filters;
    }

    private function types()
    {
        return BillMasterType::query()->orderBy('sort_order')->get();
    }

    private function can(string $action): bool
    {
        return (bool) auth()->user()?->canDo(self::ROLE, $action);
    }

    private function authorizeAction(string $action): void
    {
        abort_unless($this->can($action), 403);
    }
}
