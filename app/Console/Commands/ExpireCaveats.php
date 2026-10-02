<?php

namespace App\Console\Commands;

use App\Services\CaveatLiftService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Lifts caveats that have run their six months.
 *
 * WHY THIS DID NOT EXIST BEFORE
 * Nothing in this system has ever moved a caveat off 'active'. The 'expired'
 * status was only set by hand, at creation time, when an officer back-captured a
 * historical caveat that was already dead. Every caveat placed through the app
 * has therefore stayed in force indefinitely, whatever release_date said.
 *
 * WHAT "DUE" MEANS -- see CaveatLiftService::due(). Briefly: a caveat with a
 * release_date is due once that date passes; one without is due six months after
 * start_date, which is the rule the Ministry asked for and the only thing that
 * will ever clear the backlog.
 *
 * THIS COMMAND CHANGES LIVE STATE AND IS NOT EASILY UNDONE -- it clears the
 * is_caveated flags that block dealings on a property. Run --dry-run first, read
 * the list, and only then schedule it. app/Console/Kernel.php ships with the
 * schedule entry commented out for exactly this reason.
 */
class ExpireCaveats extends Command
{
    protected $signature = 'caveats:expire
                            {--dry-run : List what would be lifted and change nothing}
                            {--limit= : Stop after this many caveats}';

    protected $description = 'Lift caveats that have reached their six-month expiry, and tell the caveator';

    public function handle(CaveatLiftService $lifts): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $due = $lifts->due($limit);

        if ($due->isEmpty()) {
            $this->info('No caveats are due for expiry.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line($dryRun
            ? '<comment>DRY RUN — nothing below has been changed.</comment>'
            : '<info>Lifting ' . $due->count() . ' caveat(s).</info>');
        $this->line('');

        $rows = [];

        foreach ($due as $caveat) {
            $reason = $caveat->release_date
                ? 'release date passed'
                : 'six months from start date';

            $rows[] = [
                $caveat->id,
                $caveat->caveat_number,
                $caveat->petitioner,
                (string) ($caveat->start_date ?? '—'),
                (string) ($caveat->release_date ?? '—'),
                $reason,
                // Says up front whether the caveator will actually hear about it.
                trim((string) $caveat->petitioner_phone) !== '' ? 'yes' : 'no number',
            ];
        }

        $this->table(
            ['ID', 'Caveat No', 'Petitioner', 'Start', 'Release', 'Why', 'Phone'],
            $rows
        );

        if ($dryRun) {
            $this->line('');
            $this->line('Re-run without --dry-run to lift these.');

            return self::SUCCESS;
        }

        $lifted = 0;
        $failed = 0;

        foreach ($due as $caveat) {
            try {
                $lifts->lift(
                    $caveat,
                    CaveatLiftService::MODE_AUTO,
                    fn ($c) => $lifts->clearFlagsByCaveatId($c),
                    'System (6-month expiry)'
                );

                $lifted++;
            } catch (\Throwable $e) {
                // One bad row must not strand the rest of the sweep.
                $failed++;
                $this->error('Caveat ' . $caveat->id . ': ' . $e->getMessage());
                Log::error('ExpireCaveats: could not lift caveat', [
                    'caveat_id' => $caveat->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->line('');
        $this->info('Lifted: ' . $lifted . ($failed ? ' | Failed: ' . $failed : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
