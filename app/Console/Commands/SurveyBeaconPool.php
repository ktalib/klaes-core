<?php

namespace App\Console\Commands;

use App\Services\Survey\BeaconPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fill the public beacon pool (CR 03112026 item 14). A dry run unless --apply. Scheduled with --apply:
 * it does nothing until the free numbers run low, then loads the next prefix, so issuing never waits.
 */
class SurveyBeaconPool extends Command
{
    protected $signature = 'survey:beacon-pool {--apply : Load the numbers; without it nothing is written}';

    protected $description = 'Fill the public beacon pool with the next prefix when it runs low';

    public function handle(): int
    {
        if (! BeaconPool::installed()) {
            $this->error('The survey_beacon_pool table has not been created yet.');

            return self::FAILURE;
        }

        foreach (BeaconPool::prefixes() as $p) {
            $this->line("  {$p->prefix}: {$p->available} free of {$p->total}");
        }

        $prefix = BeaconPool::prefixToFill();
        if (! $prefix) {
            $this->info('The pool has enough free numbers; nothing to fill.');

            return self::SUCCESS;
        }

        $have = (int) DB::connection('sqlsrv')->table('survey_beacon_pool')->where('prefix', $prefix)->max('seq');
        $inRegister = count(BeaconPool::registerLines($prefix, 1, BeaconPool::MAX_SEQ));
        $this->info("Next to fill: {$prefix}, " . BeaconPool::number($prefix, $have + 1) . ' to ' . BeaconPool::number($prefix, BeaconPool::MAX_SEQ)
            . " ({$inRegister} already in the register, kept as Issued).");

        if (! $this->option('apply')) {
            $this->warn('Dry run: nothing written. Use --apply to load.');

            return self::SUCCESS;
        }

        $done = BeaconPool::fill($prefix);
        $this->info("Loaded {$done['added']} numbers under {$done['prefix']}, {$done['already_issued']} of them already issued.");

        return self::SUCCESS;
    }
}
