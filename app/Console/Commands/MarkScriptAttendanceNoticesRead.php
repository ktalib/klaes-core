<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Services\StaffAttendanceNotificationService;
use Illuminate\Console\Command;

/**
 * Clears the "Sign-in recorded" cards nobody earned.
 *
 * Until the guard in LogUserLogin landed, every script Auth::login() — tinker,
 * artisan, a queue worker — raised a sign-in notice. Those requests carry the
 * user agent Laravel's SetRequestForConsole bootstrapper builds, so the session
 * they were given resolves to browser "Unknown", and that is what identifies
 * them here: a real browser always names itself.
 *
 * They are marked READ, never deleted. The rows are an audit trail of what the
 * old behaviour produced, and the badge and the post-login pop-up both key off
 * is_read, so reading them is all it takes to clear the screen.
 *
 * Sign-out notices are left alone: their data carries no browser field, so
 * there is nothing to tell a script sign-out from a real one, and guessing
 * would silently read cards a member of staff has not seen.
 */
class MarkScriptAttendanceNoticesRead extends Command
{
    protected $signature = 'attendance:mute-script-notices
        {--dry-run : Report what would be marked read without writing}
        {--user= : Restrict to a single user id}';

    protected $description = 'Mark script-generated "Sign-in recorded" notices as read (browser "Unknown").';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $userId = $this->option('user') !== null ? (int) $this->option('user') : null;

        $query = Notification::query()
            ->where('type', StaffAttendanceNotificationService::TYPE_LOGIN)
            ->where('is_read', false);

        if ($userId) {
            $query->forUser($userId);
        }

        // Decided in PHP, not SQL. `data` is a JSON string column and the set is
        // small (tens of rows), so a JSON_VALUE predicate would buy nothing and
        // would behave differently on the two connections this app spans.
        $ids = $query->get(['id', 'user_id', 'data'])
            ->filter(fn (Notification $notice) => ($notice->data['browser'] ?? null) === 'Unknown')
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            $this->info('No script-generated sign-in notices are unread.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line(sprintf('Would mark %d sign-in notice(s) read.', count($ids)));

            return self::SUCCESS;
        }

        $marked = 0;

        // By explicit id, in chunks — never a blanket predicate. This database is
        // shared with live testing and the command must touch only the rows it
        // just identified.
        foreach (array_chunk($ids, 200) as $chunk) {
            $marked += Notification::whereIn('id', $chunk)->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }

        $this->info(sprintf('Marked %d script-generated sign-in notice(s) read.', $marked));

        return self::SUCCESS;
    }
}
