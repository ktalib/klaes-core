<?php

namespace App\Jobs;

use App\Models\StaffSmsLog;
use App\Models\User;
use App\Services\StaffAttendanceSmsService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Sends one attendance SMS away from the request the user is waiting on.
 *
 * DISPATCHED WITH ->afterResponse(), NOT ONTO A QUEUE.
 *
 * The gateway call is an outbound HTTPS request with a 15s connect and 40s
 * total timeout. Running it inside the sign-in request would make signing in
 * feel broken whenever the gateway is slow, and signing out worse still.
 *
 * A queue worker is not a safe assumption here: config/queue.php defaults to
 * `sync`, QUEUE_CONNECTION lives only in .env, and .env is gitignored so it
 * does NOT travel with a code upload. On a freshly deployed server a queued job
 * would therefore run inline anyway — exactly the delay this avoids — or sit
 * forever in a table nothing drains. afterResponse needs no worker: the message
 * goes out in the same process once the response has been flushed to the user.
 */
class SendStaffAttendanceSms
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        private int $userId,
        private string $type,
        private ?string $eventAt = null
    ) {
    }

    public function handle(StaffAttendanceSmsService $service): void
    {
        try {
            $user = User::find($this->userId, [
                'id', 'first_name', 'last_name', 'phone_number', 'shift_code', 'is_active',
            ]);

            if (!$user || (int) ($user->is_active ?? 1) !== 1) {
                return;
            }

            $at = $this->eventAt ? Carbon::parse($this->eventAt) : null;

            if ($this->type === StaffSmsLog::TYPE_LOGIN) {
                $service->sendLoginSms($user, $at);

                return;
            }

            $service->sendLogoutSms($user, $at);
        } catch (\Throwable $e) {
            // An attendance SMS must never take a sign-in or sign-out down with
            // it. The row in staff_sms_logs and this line are the record.
            Log::error('SendStaffAttendanceSms: failed', [
                'user_id' => $this->userId,
                'type' => $this->type,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue this message to go out after the response reaches the user.
     *
     * The moment is carried as ISO-8601 WITH its offset, never as a plain
     * "Y-m-d H:i:s". A bare string is re-parsed in config('app.timezone') —
     * UTC here — so a 17:12 Lagos sign-out came back as 17:12 UTC and was
     * reported to the member of staff as 18:12.
     */
    public static function queueFor(int $userId, string $type, ?Carbon $at = null): void
    {
        static::dispatchAfterResponse($userId, $type, $at?->toIso8601String());
    }
}
