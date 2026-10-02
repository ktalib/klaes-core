<?php

namespace App\Console\Commands;

use App\Models\FileTracker;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Backfills file_tracker.timeline_days for rows logged before the column existed.
 *
 * Timeline (Days) used to be a UI-only field: it computed the Expected Return Date and
 * was then discarded. The stored deadline is therefore the only surviving record of the
 * agreed window, so the days are derived back from it — deadline − log-out date. Rows
 * with no deadline had no timeline agreed and are left NULL rather than invented.
 *
 * Also normalises deadlines stored at 00:00:00 to 23:59:59. A date-only deadline means
 * "by the end of that day"; left at midnight it reads as overdue from the first second
 * of the due date, losing the file a full day.
 *
 * Idempotent — re-running changes nothing once applied. Use --dry-run to preview.
 */
class BackfillTrackerTimelineDays extends Command
{
    protected $signature = 'trackers:backfill-timeline-days
        {--dry-run : Show what would change without writing}
        {--force : Skip the confirmation prompt (for non-interactive deploys)}';

    protected $description = 'Populate file_tracker.timeline_days from existing deadlines';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Always state the target before writing — this command is run by hand on
        // production, where "which database am I pointed at?" is the question that
        // matters most.
        $connection = FileTracker::query()->getConnection();
        $this->line('  Environment : ' . app()->environment());
        $this->line('  Connection  : ' . $connection->getName());
        $this->line('  Database    : ' . $connection->getDatabaseName());
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        } elseif (! $this->option('force') && ! $this->confirm('Write timeline_days and normalise deadlines on the database above?', false)) {
            $this->info('Aborted — nothing was written.');
            return self::SUCCESS;
        }

        $rows = FileTracker::whereNotNull('deadline')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — no trackers carry a deadline.');
            return self::SUCCESS;
        }

        $table = [];
        $written = 0;

        foreach ($rows as $tracker) {
            $start = $tracker->timeline_start;
            if (! $start) {
                $this->warn("Skipped {$tracker->tracking_id} — no created_at/date_created to count from.");
                continue;
            }

            $deadline = Carbon::parse($tracker->deadline);

            // Whole calendar days between log-out and the due date, so a deadline stored
            // at midnight yields the same figure as one stored at end of day. Once
            // timeline_days is set it is authoritative — the agreed window, not whatever
            // the deadline has since drifted to.
            $days = $tracker->timeline_days
                ?? $start->copy()->startOfDay()->diffInDays($deadline->copy()->startOfDay());

            // Rebuild the deadline from the window so the two can never disagree.
            // setTime(23,59,59), NOT endOfDay(): the latter's .999999 microseconds round
            // UP to the next midnight in SQL Server's `datetime`, granting an extra day.
            $normalisedDeadline = $start->copy()->addDays($days)->setTime(23, 59, 59);
            $deadlineChanged = ! $deadline->equalTo($normalisedDeadline);

            if (! $deadlineChanged && $tracker->timeline_days !== null) {
                continue;   // already correct — keep the command idempotent
            }

            $table[] = [
                $tracker->tracking_id,
                $start->format('Y-m-d'),
                $deadline->format('Y-m-d H:i:s'),
                $days,
                $normalisedDeadline->format('Y-m-d H:i:s'),
            ];

            if (! $dryRun) {
                $tracker->timeline_days = $days;
                $tracker->deadline = $normalisedDeadline;
                $tracker->save();
                $written++;
            }
        }

        if (empty($table)) {
            $this->info('Nothing to backfill — every tracker with a deadline is already consistent.');
            return self::SUCCESS;
        }

        $this->table(
            ['Tracking ID', 'Logged out', 'Deadline (was)', 'timeline_days', 'Deadline (now)'],
            $table
        );

        $this->info($dryRun
            ? \count($table) . ' tracker(s) would be updated.'
            : "{$written} tracker(s) updated.");

        return self::SUCCESS;
    }
}
