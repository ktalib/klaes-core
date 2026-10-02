<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\UserActivityLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The in-app half of the attendance notices — the bell entry that goes with the
 * sign-in / sign-out SMS.
 *
 * WHY THIS IS NOT SIMPLY A SECOND CHANNEL ON StaffAttendanceSmsService
 *
 * That class carries three rules that exist only because a text message costs
 * money: one message per person per day, the sign-out held back until the
 * member of staff reaches the end of their own shift, and nothing at all for
 * the ~1,280 of 1,562 staff with no number on file. A notification is a row in
 * a local table — it costs nothing and it reaches everybody — so this class has
 * no once-a-day cap and no phone-number requirement, and it keeps working when
 * the SMS feature is switched off (STAFF_SMS_ENABLED defaults to false, so on a
 * fresh deployment it usually is).
 *
 * THE SIGN-OUT SIDE IS NO LONGER GATE-FREE
 *
 * Sign-in still fires on every sign-in. Sign-out does not: it now announces
 * only a sign-out the user performed themselves, and only once they have
 * reached the end of their own shift. See logout() for why — the automatic
 * "your session was closed" notice was landing on people who were still
 * working, and cost is not the only reason to stay quiet. The shift test is
 * borrowed from StaffAttendanceSmsService rather than re-derived, so the two
 * channels cannot drift apart on what counts as the end of a working day.
 *
 * WRITTEN INSIDE THE REQUEST, NOT AFTER IT
 *
 * The SMS goes out through SendStaffAttendanceSms::afterResponse() because it
 * is an outbound HTTPS call with a 40s timeout and must never sit in front of a
 * sign-in. This is a single INSERT on a connection the request already holds,
 * so it runs inline — and it has to: the post-login flash (notification-flash.js)
 * asks for unread notifications as soon as the dashboard paints, and a row
 * written after the response has already lost that race.
 *
 * THE CLOCK
 * Times are rendered in config('attendance.notifications.timezone'),
 * Africa/Lagos. config('app.timezone') is UTC on this deployment, so now()
 * reads 16:00 while the office clock says 17:00 — a body built from the app
 * clock would tell every member of staff they signed out an hour before they
 * did.
 */
class StaffAttendanceNotificationService
{
    public const TYPE_LOGIN = 'attendance.login';
    public const TYPE_LOGOUT = 'attendance.logout';
    public const MODULE = 'attendance';

    /** A sign-out the user asked for by clicking Log Out. */
    public const MODE_MANUAL = 'manual';

    /** A session ActivityLogService closed on its own once the shift ran out. */
    public const MODE_AUTOMATIC = 'automatic';

    /** A session an administrator ended from the user management screens. */
    public const MODE_FORCED = 'forced';

    public function __construct(private UserNotificationService $notifications)
    {
    }

    /**
     * Record the sign-in notification.
     *
     * @param UserActivityLog|null $session The row ActivityLogService::recordLogin
     *        just created. It already holds the resolved IP, browser and
     *        platform, which is what makes this notice worth reading: "signed
     *        in from Chrome on Windows" is how a user spots a sign-in that was
     *        not theirs.
     */
    public function login(User $user, ?UserActivityLog $session = null, ?Carbon $at = null): ?Notification
    {
        if (!$this->enabled('login')) {
            return null;
        }

        $at = $this->localise($at);
        $where = $this->describeOrigin($session);

        $body = "You signed in at {$this->timeOf($at)} on {$this->dateOf($at)}"
            . ($where !== '' ? " from {$where}" : '')
            . '. If this was not you, please contact ICT.';

        return $this->record($user, self::TYPE_LOGIN, 'Sign-in recorded', $body, [
            'event' => 'login',
            'mode' => self::MODE_MANUAL,
            'event_at' => $at->toIso8601String(),
            'ip' => $session->ip_address ?? null,
            'browser' => $session->browser ?? null,
            'platform' => $session->platform ?? null,
        ]);
    }

    /**
     * Record the sign-out notification.
     *
     * TWO GATES, BOTH ADDED ON REQUEST
     *
     *  1. Only a sign-out the user performed themselves is announced. A session
     *     the shift clock closed, or one an administrator ended, now says
     *     nothing at all. The automatic notice was the complaint that brought
     *     this rule in: it reaches people who are still at their desks working,
     *     where "your session was closed because your shift had ended" is noise
     *     at best and alarming at worst.
     *
     *  2. Even a real click on Log Out is only announced once the user has
     *     reached the end of their OWN shift — 17:00 for the 1,296 full-day
     *     staff, which is the "around 5pm" this was asked for, but 13:00 for
     *     morning staff and 04:00 for overnight staff, who would never be
     *     reached by a rule hard-coded to 17:00. Signing out for lunch or to
     *     step out mid-morning leaves no notice.
     *
     * This is deliberately the same test the sign-out SMS already applied, so
     * the bell entry and the text message now agree on what a sign-out is
     * instead of the bell firing on occasions the SMS stayed quiet for.
     *
     * @param string      $mode   One of the MODE_* constants. Anything other
     *                            than MODE_MANUAL returns null without writing.
     * @param string|null $reason Free text from the caller. Retained for the
     *                            manual path's payload and for callers that
     *                            still pass a description.
     */
    public function logout(
        User $user,
        ?Carbon $at = null,
        string $mode = self::MODE_MANUAL,
        ?string $reason = null
    ): ?Notification {
        if (!$this->enabled('logout')) {
            return null;
        }

        if ($mode !== self::MODE_MANUAL) {
            return null;
        }

        $at = $this->localise($at);

        if (!$this->shiftHasEnded($user, $at)) {
            return null;
        }

        $when = "{$this->timeOf($at)} on {$this->dateOf($at)}";

        // Only the manual wording survives the gate above. The automatic and
        // forced wordings are gone with the notices themselves; the MODE_*
        // constants stay because callers still pass them and the payload still
        // records which one it was.
        $title = 'Sign-out recorded';
        $body = "You signed out at {$when}. Thank you for today.";

        return $this->record($user, self::TYPE_LOGOUT, $title, $body, [
            'event' => 'logout',
            'mode' => $mode,
            'event_at' => $at->toIso8601String(),
            'reason' => $reason,
        ]);
    }

    /**
     * Write the row, swallowing anything that goes wrong.
     *
     * A notification must never take a sign-in or a sign-out down with it — the
     * user still has to get in, and on the logout side the session still has to
     * close. The log line is the record when the insert fails.
     */
    private function record(User $user, string $type, string $title, string $body, array $data): ?Notification
    {
        if ($this->alreadyNotified($user, $type)) {
            return null;
        }

        try {
            return $this->notifications->create(
                (int) $user->id,
                $type,
                $title,
                $body,
                array_filter($data, fn ($value) => $value !== null && $value !== ''),
                ['module' => self::MODULE, 'name' => $title]
            );
        } catch (\Throwable $e) {
            Log::error('StaffAttendanceNotificationService: could not record an attendance notification', [
                'user_id' => $user->id,
                'type' => $type,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Has the same notice just gone out?
     *
     * Not a once-a-day rule — a short window, and it exists for one specific
     * shape of duplicate: ActivityLogService closes sessions ROW BY ROW, so a
     * user carrying three stale session rows is finalised three times in the
     * same loop and would collect three identical "session closed" cards.
     * Genuine sign-ins minutes apart are still each announced.
     */
    private function alreadyNotified(User $user, string $type): bool
    {
        $window = (int) config('attendance.notifications.duplicate_window_seconds', 60);

        if ($window <= 0) {
            return false;
        }

        try {
            // created_at is written on the app clock (UTC), so the cutoff is
            // built on it too — this comparison must not be localised.
            return Notification::query()
                ->forUser((int) $user->id)
                ->where('type', $type)
                ->where('created_at', '>=', now()->subSeconds($window))
                ->exists();
        } catch (\Throwable $e) {
            // A failed lookup must not cost the user the notification itself.
            return false;
        }
    }

    /**
     * "Chrome on Windows (105.112.4.9)", from whatever the session row has.
     */
    private function describeOrigin(?UserActivityLog $session): string
    {
        if (!$session) {
            return '';
        }

        $browser = trim((string) ($session->browser ?? ''));
        $platform = trim((string) ($session->platform ?? ''));
        $ip = trim((string) ($session->ip_address ?? ''));

        $agent = trim($browser . ($browser !== '' && $platform !== '' ? ' on ' : '') . $platform);

        if ($agent !== '' && $ip !== '') {
            return "{$agent} ({$ip})";
        }

        return $agent !== '' ? $agent : $ip;
    }

    /**
     * Has this user reached the end of their own shift?
     *
     * Delegates to StaffAttendanceSmsService, which owns the shift table lookup
     * and the awkward overnight case (21:00-04:00 ends at 04:00, so 05:00 is
     * after it but 22:00 is the next night's shift already running). Resolving
     * that class constructs its gateway but makes no network call, and the test
     * itself does not consult the SMS feature switches — so this stays correct
     * with attendance SMS turned off entirely, which is the default.
     *
     * A failure here must not cost the user a notice they had earned, so an
     * unreadable shift falls back to announcing the sign-out.
     */
    private function shiftHasEnded(User $user, Carbon $at): bool
    {
        try {
            return app(StaffAttendanceSmsService::class)->shiftHasEnded($user, $at);
        } catch (\Throwable $e) {
            Log::warning('StaffAttendanceNotificationService: could not evaluate the shift end', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return true;
        }
    }

    private function enabled(string $event): bool
    {
        return (bool) config('attendance.notifications.enabled', true)
            && (bool) config('attendance.notifications.' . $event, true);
    }

    private function timeOf(Carbon $at): string
    {
        return $at->format('h:i A');
    }

    private function dateOf(Carbon $at): string
    {
        return $at->format('d/m/Y');
    }

    /**
     * Move a moment onto the office clock, defaulting to now.
     */
    private function localise(?Carbon $at): Carbon
    {
        $timezone = config('attendance.notifications.timezone', 'Africa/Lagos');

        return $at
            ? $at->copy()->setTimezone($timezone)
            : Carbon::now($timezone);
    }
}
