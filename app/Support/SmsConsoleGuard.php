<?php

namespace App\Support;

/**
 * No real SMS from scripts, tinker or tests — only from the web app and the
 * console commands whose job is to send.
 *
 * WHY: on 2026-10-12 a rollback test script called the SPAS notice SMS path
 * and a real message went out. A rolled-back transaction does not unsend an
 * SMS, and Http::fake() does not stop BulkSmsNgService, which uses curl. A
 * check inside the gateways closes that hole for good, whatever a script
 * remembers to switch off.
 *
 * A web request (an officer on the site) is never affected. In the console
 * only the commands below may send; anything else — `php some-script.php`,
 * `php artisan tinker`, phpunit, an unlisted command — is refused, logged,
 * and reported to the caller as an ordinary failed send.
 */
final class SmsConsoleGuard
{
    /** Console commands that are meant to send real SMS. */
    public const ALLOWED_COMMANDS = [
        // The scheduler, and the scheduled sends it starts.
        'schedule:run',
        'schedule:work',
        'spa:trigger-second-service',      // daily 08:00 SPAS 2nd Serve
        'spa:serve-pending-first-serves',  // SPAS 1st Serves owed
        'caveats:expire',                  // caveat auto-lift notices (scheduled when enabled)
        // A queue worker running SMS jobs, if one is ever started.
        'queue:work',
        'queue:listen',
        // Diagnostics that send a test to a number the operator types in.
        'klaes:sms-doctor',
        'spa:sms-doctor',
        'staff:sms-doctor',
        'laas:sms-probe',
        'phone:verify-status',
    ];

    /** Why this process may not send, or null when it may. */
    public static function refusal(): ?string
    {
        if (! app()->runningInConsole()) {
            return null;                       // the web app: always allowed
        }

        $command = self::command();
        if ($command !== null && in_array($command, self::ALLOWED_COMMANDS, true)) {
            return null;
        }

        return 'SMS is not sent from scripts, tinker or tests'
            . ($command ? " (`{$command}` is not a sending command)" : '')
            . ' — only the web app and the scheduled SMS commands may send. See App\Support\SmsConsoleGuard.';
    }

    /** The artisan command this process is running, or null for a plain PHP script. */
    private static function command(): ?string
    {
        $argv = $_SERVER['argv'] ?? [];
        $script = strtolower(basename((string) ($argv[0] ?? '')));

        if ($script !== 'artisan') {
            return null;                       // php script.php, phpunit, …
        }

        $name = (string) ($argv[1] ?? '');

        return $name !== '' && $name[0] !== '-' ? $name : null;
    }
}
