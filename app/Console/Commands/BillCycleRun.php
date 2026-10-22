<?php

namespace App\Console\Commands;

use App\Models\BillMaster;
use App\Services\Billing\BillMasterService;
use App\Services\Billing\BillNotifier;
use Illuminate\Console\Command;

/**
 * KLAES REV-M payment cycles (daily at 07:30, app/Console/Kernel.php).
 *
 * For every bill of a recurring type that still carries a next due date:
 *   - within the type's "remind days" of the due date → SMS "next payment due" (once)
 *   - on or after the due date → raise the next cycle's bill (SMS "bill generated")
 *
 * Nothing happens until a bill type is made recurring in Bill Settings, and no SMS goes
 * out until its message is switched on in the SMS Control Centre.
 */
class BillCycleRun extends Command
{
    protected $signature = 'bills:cycle {--dry-run : List what would happen; send and raise nothing}';

    protected $description = 'REV-M recurring bills: send next-payment-due SMS and raise the next cycle\'s bill';

    public function handle(BillMasterService $bills, BillNotifier $notifier): int
    {
        $today = now()->startOfDay();
        $dry = (bool) $this->option('dry-run');
        $reminded = $raised = $failed = 0;

        $due = BillMaster::query()->with('type')
            ->whereNotNull('next_due_date')->whereNotNull('payment_cycle')
            ->where('status', '<>', BillMaster::STATUS_CANCELLED)
            ->orderBy('next_due_date')->get();

        foreach ($due as $bill) {
            $setting = $bills->cycleType($bill->type);
            if (!$setting || !$setting->is_recurring) {
                continue;
            }
            $dueDate = $bill->next_due_date->copy()->startOfDay();

            if ($dueDate->lte($today)) {
                $this->line("raise next cycle  {$bill->bill_ref_id}  due {$dueDate->format('d/m/Y')}");
                if ($dry) {
                    continue;
                }
                try {
                    $next = $bills->raiseNextCycle($bill, 'bills:cycle');
                    $this->info("  → {$next->bill_ref_id} for {$next->period_start->format('d/m/Y')} – {$next->period_end->format('d/m/Y')}");
                    $raised++;
                } catch (\Throwable $e) {
                    $this->error('  ' . $e->getMessage());
                    $failed++;
                }
                continue;
            }

            $remindFrom = $dueDate->copy()->subDays(max(0, (int) $setting->remind_days_before));
            if ($today->gte($remindFrom)) {
                $this->line("due reminder      {$bill->bill_ref_id}  due {$dueDate->format('d/m/Y')}");
                if (!$dry) {
                    $notifier->due($bill, true);
                    $reminded++;
                }
            }
        }

        $this->info(($dry ? '[dry run] ' : '') . "Reminders: {$reminded}; next cycles raised: {$raised}; failed: {$failed}.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
