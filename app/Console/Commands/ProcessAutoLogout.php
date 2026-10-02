<?php

namespace App\Console\Commands;

use App\Services\ActivityLogService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessAutoLogout extends Command
{
    protected $signature = 'activity:auto-logout
        {--dry-run : Simulate without persisting updates}
        {--limit=0 : Maximum number of sessions to evaluate}
        {--now= : Override the current timestamp (Y-m-d H:i)}';

    protected $description = 'Enforce auto logout for online sessions that exceeded configured work hours.';

    public function handle(): int
    {
        $nowOption = $this->option('now');
        $timestamp = null;

        if (!empty($nowOption)) {
            try {
                $timestamp = Carbon::parse($nowOption, config('app.timezone'));
            } catch (\Throwable $exception) {
                $this->error('Invalid --now value. Please use a valid datetime string.');
                return self::FAILURE;
            }
        }

        $limitOption = (int) $this->option('limit');
        $limit = $limitOption > 0 ? $limitOption : null;

        $dryRun = (bool) $this->option('dry-run');

        $result = ActivityLogService::autoLogoutExpiredSessions($timestamp, $dryRun, $limit);

        $this->line('Auto logout evaluation summary:');
        $this->table(
            ['Processed', 'Auto Logged Out', 'Sessions Removed', 'Skipped', 'Dry Run', 'Timestamp'],
            [[
                $result['processed'] ?? 0,
                $result['auto_logged_out'] ?? 0,
                $result['sessions_removed'] ?? 0,
                count($result['skipped'] ?? []),
                $result['dry_run'] ? 'Yes' : 'No',
                $result['timestamp'] ?? Carbon::now()->toDateTimeString(),
            ]]
        );

        $skipped = $result['skipped'] ?? [];
        if (!empty($skipped)) {
            $this->warn(sprintf('Skipped %d session(s). See verbose output below.', count($skipped)));
            foreach ($skipped as $entry) {
                $this->line(sprintf('- Session %s: %s', $entry['session_id'] ?? 'unknown', $entry['reason'] ?? 'unknown'));
            }
        }

        if (!($result['processed'] ?? 0)) {
            $this->comment('No active sessions required auto logout.');
        }

        return self::SUCCESS;
    }
}
