<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Models\BillMasterType;
use App\Models\BillPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * KLAES REV-M → Dashboard: totals across Bill Master, payments, receipting and
 * Bill Legacy. Read-only; every figure comes from bill_master / bill_payments /
 * bill_receipts / bill_legacy (cancelled bills left out).
 */
class DashboardController extends Controller
{
    /** Anyone who can open a REV-M section can open the dashboard. */
    private const ROLES = ['Billing', 'Generate Receipt', 'Bill Balance', 'Land Use Charge (LUC)', 'Transaction Token Control'];

    public function index()
    {
        $user = Auth::user();
        abort_unless($user && ($user->isSuperAdmin() || collect(self::ROLES)->contains(fn ($r) => $user->canDo($r, 'view'))), 403);

        $db = DB::connection('sqlsrv');
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        // Per bill type (children such as Primary / PuA / SuA kept separate).
        $byType = collect($db->select("
            SELECT bill_type_code,
                   COUNT(*) n,
                   SUM(CASE WHEN status = 'unpaid' AND generated_at IS NULL THEN 1 ELSE 0 END) pending,
                   SUM(CASE WHEN status = 'unpaid' AND generated_at IS NOT NULL THEN 1 ELSE 0 END) unpaid,
                   SUM(CASE WHEN status = 'part_paid' THEN 1 ELSE 0 END) part_paid,
                   SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) paid,
                   COALESCE(SUM(amount), 0) billed,
                   COALESCE(SUM(amount_paid), 0) collected
            FROM bill_master
            WHERE status <> 'cancelled'
            GROUP BY bill_type_code"))->keyBy('bill_type_code');

        $types = BillMasterType::query()->orderBy('sort_order')->get();
        $rows = $types->map(function ($t) use ($byType) {
            $r = $byType->get($t->code);

            return (object) [
                'code' => $t->code,
                'name' => $t->name,
                'parent' => $t->parent_code,
                'url' => $this->typeUrl($t),
                'n' => (int) ($r->n ?? 0),
                'pending' => (int) ($r->pending ?? 0),
                'unpaid' => (int) ($r->unpaid ?? 0),
                'part_paid' => (int) ($r->part_paid ?? 0),
                'paid' => (int) ($r->paid ?? 0),
                'billed' => (float) ($r->billed ?? 0),
                'collected' => (float) ($r->collected ?? 0),
            ];
        })->filter(fn ($r) => $r->n > 0 || !$r->parent)->values();

        $totals = (object) [
            'n' => $rows->sum('n'),
            'pending' => $rows->sum('pending'),
            'unpaid' => $rows->sum('unpaid') + $rows->sum('part_paid'),
            'paid' => $rows->sum('paid'),
            'billed' => $rows->sum('billed'),
            'collected' => $rows->sum('collected'),
        ];
        $totals->outstanding = max(0, $totals->billed - $totals->collected);

        $awaiting = $db->table('bill_payments')
            ->where('status', BillPayment::STATUS_SUCCESSFUL)
            ->where(fn ($q) => $q->whereNull('receipt_no')->orWhere('receipt_no', ''))
            ->selectRaw('COUNT(*) n, COALESCE(SUM(amount), 0) amount')->first();

        $receipts = $db->table('bill_receipts')->where('status', 'issued')
            ->selectRaw('SUM(CASE WHEN CAST(issued_at AS date) = ? THEN 1 ELSE 0 END) today,
                         SUM(CASE WHEN issued_at >= ? THEN 1 ELSE 0 END) month,
                         COUNT(*) total', [$today, $monthStart])->first();

        $collectedToday = (float) $db->table('bill_payments')->where('status', BillPayment::STATUS_SUCCESSFUL)
            ->whereRaw('CAST(paid_at AS date) = ?', [$today])->sum('amount');
        $collectedMonth = (float) $db->table('bill_payments')->where('status', BillPayment::STATUS_SUCCESSFUL)
            ->where('paid_at', '>=', $monthStart)->sum('amount');

        // Collections for the last six months (successful payments by the month paid).
        $sixStart = now()->startOfMonth()->subMonths(5);
        $monthly = collect($db->select("
            SELECT YEAR(paid_at) y, MONTH(paid_at) m, COALESCE(SUM(amount), 0) amount, COUNT(*) n
            FROM bill_payments
            WHERE status = ? AND paid_at >= ?
            GROUP BY YEAR(paid_at), MONTH(paid_at)", [BillPayment::STATUS_SUCCESSFUL, $sixStart]))
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->y, $r->m));
        $months = collect(range(0, 5))->map(function ($i) use ($sixStart, $monthly) {
            $d = $sixStart->copy()->addMonths($i);
            $r = $monthly->get($d->format('Y-m'));

            return (object) ['label' => $d->format('M Y'), 'amount' => (float) ($r->amount ?? 0), 'n' => (int) ($r->n ?? 0)];
        });

        $byMethod = collect($db->select("
            SELECT method, COUNT(*) n, COALESCE(SUM(amount), 0) amount
            FROM bill_payments
            WHERE status = ? AND paid_at >= ?
            GROUP BY method
            ORDER BY SUM(amount) DESC", [BillPayment::STATUS_SUCCESSFUL, $monthStart]))
            ->map(fn ($r) => (object) ['label' => self::methodLabel($r->method), 'n' => (int) $r->n, 'amount' => (float) $r->amount]);

        $recent = $db->table('bill_payments as p')
            ->join('bill_master as b', 'b.bill_ref_id', '=', 'p.bill_ref_id')
            ->leftJoin('bill_master_types as t', 't.code', '=', 'b.bill_type_code')
            ->where('p.status', BillPayment::STATUS_SUCCESSFUL)
            ->orderByDesc('p.id') // latest recorded; a few captured receipts carry future paid dates
            ->limit(10)
            ->get(['p.id', 'p.bill_ref_id', 'p.method', 'p.transaction_id', 'p.amount', 'p.paid_at', 'p.receipt_no',
                'b.file_number', 'b.payer_name', 't.name as type_name']);

        $legacy = $db->table('bill_legacy')->where('status', '<>', 'cancelled')
            ->selectRaw("COUNT(*) n,
                         SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) paid,
                         SUM(CASE WHEN status <> 'paid' THEN 1 ELSE 0 END) unpaid,
                         COALESCE(SUM(amount), 0) billed")->first();

        return view('revenue.dashboard', compact(
            'rows', 'totals', 'awaiting', 'receipts', 'collectedToday', 'collectedMonth',
            'months', 'byMethod', 'recent', 'legacy'
        ));
    }

    /** Short payment-method names for the dashboard (config labels are long). */
    public static function methodLabel(?string $method): string
    {
        return [
            'paystack' => 'Paystack',
            'pos' => 'POS',
            'bank_transfer' => 'Bank Transfer',
            'captured' => 'Captured receipt',
            'module_receipt' => 'Module receipt',
            'invoice' => 'Bank transfer (invoice)',
            'online' => 'Online portal',
        ][(string) $method] ?? BillPayment::labelFor($method);
    }

    private function typeUrl(BillMasterType $type): ?string
    {
        if ($type->code === 'land_use_charge') {
            return null; // on hold
        }

        return $type->parent_code
            ? route('revenue.bills.index', $type->parent_code) . '?sub=' . $type->code
            : route('revenue.bills.index', $type->code);
    }
}
