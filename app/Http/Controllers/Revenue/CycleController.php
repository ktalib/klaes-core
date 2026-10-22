<?php

namespace App\Http\Controllers\Revenue;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\BillMaster;
use App\Models\BillMasterType;
use App\Services\Billing\BillMasterService;
use App\Services\Billing\BillNotifier;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * KLAES REV-M → Payment Cycles: recurring bills and when their next payment falls due.
 * The cycle is set per bill type in Bill Settings; bills:cycle (daily 07:30) sends the
 * due SMS and raises the next cycle's bill. The same two steps can be run by hand here.
 */
class CycleController extends Controller
{
    use RunsWorkflowActions;

    public function index(Request $request, BillMasterService $bills)
    {
        $this->authorizeAction('view');

        $filters = [
            'when' => (string) $request->query('when', 'current'),
            'type' => (string) $request->query('type', ''),
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = BillMaster::query()->with('type')->whereNotNull('payment_cycle')->where('status', '<>', BillMaster::STATUS_CANCELLED);
        match ($filters['when']) {
            'overdue' => $query->whereNotNull('next_due_date')->whereDate('next_due_date', '<=', now()->toDateString()),
            'due30' => $query->whereNotNull('next_due_date')->whereDate('next_due_date', '<=', now()->addDays(30)->toDateString()),
            'all' => null,
            default => $query->whereNotNull('next_due_date'), // the current bill of each cycle
        };
        if ($filters['type'] !== '') {
            $query->where('bill_type_code', $filters['type']);
        }
        if ($filters['q'] !== '') {
            $like = '%' . mb_strtolower($filters['q']) . '%';
            $query->where(function ($w) use ($like) {
                foreach (['bill_ref_id', 'previous_bill_ref', 'file_number', 'payer_name', 'payer_phone'] as $col) {
                    $w->orWhereRaw("LOWER({$col}) LIKE ?", [$like]);
                }
            });
        }

        return view('revenue.cycles.index', [
            'rows' => $query->orderByRaw('CASE WHEN next_due_date IS NULL THEN 1 ELSE 0 END')->orderBy('next_due_date')->orderByDesc('id')
                ->paginate(25)->withQueryString(),
            'filters' => $filters,
            'recurringTypes' => BillMasterType::query()->where('is_recurring', true)->orderBy('sort_order')->get(),
            'types' => BillMasterType::query()->orderBy('sort_order')->get(),
            'canEdit' => $this->can('create'),
            'indexUrl' => route('revenue.cycles.index'),
        ]);
    }

    /** Raise the next cycle's bill now (it is normally raised on the due date). */
    public function raise(string $ref, BillMasterService $bills)
    {
        $this->authorizeAction('create');
        $bill = BillMaster::query()->with('type')->where('bill_ref_id', $ref)->firstOrFail();

        return $this->attempt(function () use ($bill, $bills) {
            $next = $bills->raiseNextCycle($bill, $this->actor());

            return "Next cycle raised: {$next->bill_ref_id} for {$next->period_start->format('d/m/Y')} – {$next->period_end->format('d/m/Y')}, ₦" . number_format((float) $next->amount, 2) . '.';
        }, 'Next cycle raised.');
    }

    /** Send the "next payment due" SMS now. */
    public function remind(string $ref, BillNotifier $notifier)
    {
        $this->authorizeAction('edit');
        $bill = BillMaster::query()->with('type')->where('bill_ref_id', $ref)->firstOrFail();

        return $this->attempt(function () use ($bill, $notifier) {
            if (!$bill->next_due_date) {
                throw WorkflowGuardException::because("{$bill->bill_ref_id} has no next payment due.");
            }
            if (!$bill->payer_phone) {
                throw WorkflowGuardException::because("{$bill->bill_ref_id} has no payer phone. Add it from the bill's Notifications tab.");
            }
            if (!\App\Models\SmsSetting::isEnabled(\App\Models\SmsSetting::KEY_BILL_DUE)) {
                throw WorkflowGuardException::because('The "Next payment due" SMS is switched off. Switch it on in the SMS Control Centre (Revenue (REV-M)).');
            }
            $notifier->due($bill, false, 'bill_due:' . $bill->bill_ref_id . ':manual:' . now()->format('YmdHis'));

            return "Reminder queued to {$bill->payer_phone}.";
        }, 'Reminder queued.');
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
