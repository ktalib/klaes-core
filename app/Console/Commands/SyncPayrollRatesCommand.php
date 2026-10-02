<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Payroll\RateService;
use Illuminate\Console\Command;

class SyncPayrollRatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payroll:sync-rates {--dry-run : Report missing rates without creating them}';

    /**
     * The console command description.
     */
    protected $description = 'Ensure payroll rates exist for users assigned to a work station.';

    public function handle(RateService $rateService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $users = User::query()
            ->whereNotNull('work_station')
            ->where('work_station', '!=', '')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No users with work stations found.');
            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $existing = $rateService->findActiveRate($user->id);
            if ($existing) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('[Dry run] Would create rate for %s (%d)', $user->name, $user->id));
            } else {
                $rateService->syncBaselineRate($user);
                $this->line(sprintf('Created baseline rate for %s (%d)', $user->name, $user->id));
            }

            $created++;
        }

        $this->info(sprintf('Processed %d user(s): %d created, %d already had a rate.', $users->count(), $created, $skipped));

        return self::SUCCESS;
    }
}
