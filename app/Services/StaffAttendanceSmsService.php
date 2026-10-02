<?php

namespace App\Services;

use App\Models\SmsSetting;
use App\Models\StaffSmsLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * One sign-in SMS and one sign-out SMS per member of staff per working day.
 *
 * Sent through Bulk-SMS.ng (sender ID KANOMLPP). BetaSMS is deliberately not
 * used: it serves its API on plain HTTP :80 only, and production cannot make
 * outbound :80 connections — see App\Services\BulkSmsNgService.
 *
 * TWO RULES DECIDE WHETHER A MESSAGE GOES OUT
 *
 *  1. Once a day, each. Enforced by a unique index on staff_sms_logs
 *     (user_id, sms_type, sent_on) rather than by a read-then-write check,
 *     which two simultaneous sign-ins could interleave.
 *
 *  2. The sign-out SMS waits for the end of THAT USER'S shift. Full-day staff
 *     (1,296 of them) finish at 17:00 — the "5pm" case — but morning staff
 *     finish at 13:00 and overnight staff at 04:00, and a rule fixed at 17:00
 *     would never reach them. A sign-out before shift end sends nothing and
 *     spends nothing, so signing out for lunch does not consume the day.
 *
 * THE CLOCK
 * Everything here is evaluated in config('staff_sms.timezone'), Africa/Lagos.
 * config('app.timezone') is UTC on this deployment, so now() reads 16:00 while
 * the office clock says 17:00; testing a shift end against it would hold every
 * sign-out message back by an hour and file late sign-outs under the wrong day.
 */
class StaffAttendanceSmsService
{
    public function __construct(private BulkSmsNgService $gateway)
    {
    }

    /**
     * Record and send the day's sign-in message.
     *
     * @return bool True when a message was accepted by the gateway.
     */
    public function sendLoginSms(User $user, ?Carbon $at = null): bool
    {
        if (!$this->featureEnabled('login')) {
            return false;
        }

        return $this->deliver($user, StaffSmsLog::TYPE_LOGIN, $this->localise($at));
    }

    /**
     * Record and send the day's sign-out message, if the user has reached the
     * end of their shift.
     */
    public function sendLogoutSms(User $user, ?Carbon $at = null): bool
    {
        if (!$this->featureEnabled('logout')) {
            return false;
        }

        $at = $this->localise($at);

        if (!$this->shiftHasEnded($user, $at)) {
            return false;
        }

        return $this->deliver($user, StaffSmsLog::TYPE_LOGOUT, $at);
    }

    /**
     * Has this user reached the end of their own shift at the given moment?
     *
     * An overnight shift (21:00-04:00) needs both ends of the test: 05:00 is
     * after its 04:00 finish, but 22:00 is not — that is the next night's shift
     * already running. Every other shift finishes on the same calendar day, so
     * the finish time alone answers it.
     */
    public function shiftHasEnded(User $user, ?Carbon $at = null): bool
    {
        $at = $this->localise($at);
        $shift = $this->shiftFor($user);

        $now = $at->format('H:i');
        $end = $shift['end'];

        if (!empty($shift['overnight'])) {
            return $now >= $end && $now < $shift['start'];
        }

        return $now >= $end;
    }

    /**
     * The shift row this user's shift_code names, or a synthetic one built from
     * the configured fallback finish time when it names nothing.
     *
     * @return array{start:string, end:string, overnight:bool, label:string}
     */
    public function shiftFor(User $user): array
    {
        $shifts = config('attendance.shifts', []);
        $code = trim((string) ($user->shift_code ?? ''));

        if ($code !== '' && isset($shifts[$code]['end'])) {
            return [
                'start' => (string) ($shifts[$code]['start'] ?? '00:00'),
                'end' => (string) $shifts[$code]['end'],
                'overnight' => (bool) ($shifts[$code]['overnight'] ?? false),
                'label' => (string) ($shifts[$code]['label'] ?? $code),
            ];
        }

        $fallback = (string) config('staff_sms.logout.default_shift_end', '17:00');

        return [
            'start' => '00:00',
            'end' => $fallback,
            'overnight' => false,
            'label' => 'default (' . $fallback . ')',
        ];
    }

    /**
     * Already sent successfully today?
     */
    public function alreadySentToday(User $user, string $type, ?Carbon $at = null): bool
    {
        return StaffSmsLog::where('user_id', $user->id)
            ->where('sms_type', $type)
            ->whereDate('sent_on', $this->localise($at)->toDateString())
            ->where('status', StaffSmsLog::STATUS_SENT)
            ->exists();
    }

    /**
     * Claim the day's slot, send, and record the outcome.
     */
    private function deliver(User $user, string $type, Carbon $at): bool
    {
        $phone = BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? ''));

        if ($phone === null) {
            // Roughly three quarters of staff have no number on file. That is a
            // data gap, not an error, and must not fill the log on every login.
            return false;
        }

        $claim = $this->claim($user, $type, $at, $phone);

        if ($claim === null) {
            return false;
        }

        $messages = $this->messagesFor($user, $type, $at);

        // Sign-in and sign-out go to a member of staff, so they carry the staff
        // sender ID (KLAES) rather than the departmental one the public sees.
        $sender = SmsSetting::senderFor($type === StaffSmsLog::TYPE_LOGIN
            ? SmsSetting::KEY_ATTENDANCE_LOGIN
            : SmsSetting::KEY_ATTENDANCE_LOGOUT);

        $accepted = $this->gateway->sendFirstAccepted($phone, $messages, $sender);

        if ($accepted !== null) {
            $claim->forceFill([
                'status' => StaffSmsLog::STATUS_SENT,
                'message' => $accepted,
                'gateway_code' => $this->gateway->lastStatusCode(),
                'failure_reason' => null,
            ])->save();

            return true;
        }

        $claim->forceFill([
            'status' => StaffSmsLog::STATUS_FAILED,
            'message' => $messages[0] ?? null,
            'gateway_code' => $this->gateway->lastStatusCode(),
            'failure_reason' => $this->gateway->lastFailureReason(),
        ])->save();

        Log::warning('StaffAttendanceSmsService: attendance SMS not delivered', [
            'user_id' => $user->id,
            'type' => $type,
            'reason' => $this->gateway->lastFailureReason(),
            'code' => $this->gateway->lastStatusCode(),
        ]);

        return false;
    }

    /**
     * Take today's slot for this user and message type.
     *
     * Returns null when the day is already spent — either a message went out
     * successfully, or another process is holding the slot right now. A row
     * left at 'failed' by an earlier attempt IS handed back, so a gateway
     * outage does not cost the user their message for the whole day.
     */
    private function claim(User $user, string $type, Carbon $at, string $phone): ?StaffSmsLog
    {
        $today = $at->toDateString();

        try {
            return StaffSmsLog::create([
                'user_id' => $user->id,
                'sms_type' => $type,
                'sent_on' => $today,
                'status' => StaffSmsLog::STATUS_PENDING,
                'phone' => $phone,
                'attempts' => 1,
                'event_at' => $at->toDateTimeString(),
            ]);
        } catch (QueryException $e) {
            // Unique index hit: the slot already exists. Whether it is ours to
            // retry depends on how the earlier attempt ended.
            $existing = StaffSmsLog::where('user_id', $user->id)
                ->where('sms_type', $type)
                ->whereDate('sent_on', $today)
                ->first();

            if ($existing === null) {
                // Not the unique index, then — a real database problem.
                Log::error('StaffAttendanceSmsService: could not claim an SMS slot', [
                    'user_id' => $user->id,
                    'type' => $type,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if ($existing->status !== StaffSmsLog::STATUS_FAILED) {
                return null;
            }

            $existing->forceFill([
                'status' => StaffSmsLog::STATUS_PENDING,
                'phone' => $phone,
                'attempts' => (int) $existing->attempts + 1,
                'event_at' => $at->toDateTimeString(),
            ])->save();

            return $existing;
        }
    }

    /**
     * Best wording first, then a plainer one.
     *
     * Both fit a single 160-character page: these go out twice a day to every
     * member of staff with a number on file, so a second page would double the
     * bill for nothing. The fallback exists because a gateway can reject a
     * wording outright — BetaSMS does it on words like "approved" and "code" —
     * and BulkSmsNgService::sendFirstAccepted retries the next one on the only
     * status that could plausibly be about the text.
     *
     * @return array<int,string>
     */
    private function messagesFor(User $user, string $type, Carbon $at): array
    {
        $name = trim((string) ($user->first_name ?: $user->name));
        $time = $at->format('h:i A');
        $date = $at->format('d/m/Y');

        if ($type === StaffSmsLog::TYPE_LOGIN) {
            return [
                "Hello {$name}, your KLAES sign-in was recorded at {$time} on {$date}. If this was not you, please contact ICT.",
                "{$name}: KLAES sign-in recorded at {$time} on {$date}.",
            ];
        }

        return [
            "Hello {$name}, your KLAES sign-out was recorded at {$time} on {$date}. Thank you for today.",
            "{$name}: KLAES sign-out recorded at {$time} on {$date}.",
        ];
    }

    /**
     * Is this kind of attendance SMS switched on?
     *
     * Asks the SMS Control Centre first (sms_settings), and only falls back to
     * config/staff_sms.php when that table has no row -- a fresh server, or one
     * where nobody has opened the page yet.
     *
     * The switches used to live only in .env, which is gitignored and does not
     * travel with a code upload: turning attendance SMS on meant editing a file
     * on the server and restarting it, and the next deployment silently lost the
     * setting. This method is the whole seam -- no call site changed.
     */
    private function featureEnabled(string $type): bool
    {
        $key = $type === StaffSmsLog::TYPE_LOGIN
            ? SmsSetting::KEY_ATTENDANCE_LOGIN
            : SmsSetting::KEY_ATTENDANCE_LOGOUT;

        try {
            if (SmsSetting::hasRowFor($key)) {
                return SmsSetting::isEnabled($key);
            }
        } catch (\Throwable $e) {
            // sms_settings not deployed yet. Fall through to the config file,
            // which is exactly the behaviour this method had before.
        }

        return (bool) config('staff_sms.enabled', true)
            && (bool) config('staff_sms.' . $type . '.enabled', true);
    }

    /**
     * Move a moment onto the office clock, defaulting to now.
     */
    private function localise(?Carbon $at): Carbon
    {
        $timezone = config('staff_sms.timezone', 'Africa/Lagos');

        return $at
            ? $at->copy()->setTimezone($timezone)
            : Carbon::now($timezone);
    }
}
