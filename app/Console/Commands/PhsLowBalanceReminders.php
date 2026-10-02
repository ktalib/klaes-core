<?php

namespace App\Console\Commands;

use App\Mail\PhsLowBalanceReminder;
use App\Models\Phs\PhsInstitution;
use App\Models\Phs\PhsMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class PhsLowBalanceReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'phs:low-balance-reminders {--threshold=100 : Token balance below which to warn}';

    /**
     * @var string
     */
    protected $description = 'Email PHS organizations whose token balance has fallen below the threshold (once per drop).';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        // Reset the flag for organizations that have recovered, so a future
        // drop will notify again.
        PhsInstitution::where('token_balance', '>=', $threshold)
            ->whereNotNull('low_balance_notified_at')
            ->update(['low_balance_notified_at' => null]);

        // Notify active organizations newly below the threshold.
        $institutions = PhsInstitution::where('status', 'active')
            ->where('token_balance', '<', $threshold)
            ->whereNull('low_balance_notified_at')
            ->get();

        $notified = 0;

        foreach ($institutions as $institution) {
            $recipients = PhsMember::where('phs_institution_id', $institution->id)
                ->where('user_type', 'super_admin')
                ->where('status', 'active')
                ->pluck('email')
                ->filter()
                ->values()
                ->all();

            if (empty($recipients)) {
                $this->warn("No admin email for {$institution->name} — skipped.");
                continue;
            }

            try {
                Mail::to($recipients)->send(new PhsLowBalanceReminder($institution, $threshold));
                $institution->forceFill(['low_balance_notified_at' => now()])->save();
                $notified++;
                $this->info("Reminded {$institution->name} ({$institution->token_balance} tokens).");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Failed to email {$institution->name}: {$e->getMessage()}");
            }
        }

        $this->info("Low-balance reminders sent: {$notified}.");

        return self::SUCCESS;
    }
}
