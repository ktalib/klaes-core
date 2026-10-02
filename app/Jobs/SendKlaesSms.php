<?php

namespace App\Jobs;

use App\Services\Sms\KlaesSmsDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Sends one transactional SMS away from the request the officer is waiting on.
 *
 * DISPATCHED WITH ->afterResponse(), NOT ONTO A QUEUE.
 *
 * The gateway call is an outbound HTTPS request with a 15s connect and 40s total
 * timeout. Running it inside the request would make commissioning a file feel
 * broken whenever the gateway is slow -- and commissioning already writes to a
 * dozen tables before it gets here.
 *
 * A queue worker is not a safe assumption on this deployment: config/queue.php
 * defaults to `sync`, QUEUE_CONNECTION lives only in .env, and .env is gitignored
 * so it does NOT travel with a code upload. On a freshly deployed server a queued
 * job would therefore either run inline anyway -- exactly the delay this avoids --
 * or sit forever in a table nothing drains. afterResponse needs no worker: the
 * message goes out in the same process once the response has been flushed.
 *
 * This is the same shape, and the same reasoning, as SendStaffAttendanceSms.
 */
class SendKlaesSms
{
    use Dispatchable;
    use Queueable;

    public function __construct(
        private string $key,
        private ?string $phone,
        private array $tokens,
        private array $context
    ) {
    }

    public function handle(KlaesSmsDispatcher $dispatcher): void
    {
        try {
            $dispatcher->sendNow($this->key, $this->phone, $this->tokens, $this->context);
        } catch (\Throwable $e) {
            // A transactional SMS must never take down the thing it reports on.
            // The row in sms_dispatch_logs and this line are the record.
            Log::error('SendKlaesSms: failed', [
                'key' => $this->key,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Queue this message to go out after the response reaches the user.
     *
     * Context has already been flattened by KlaesSmsDispatcher::queue() -- in
     * particular event_at is an ISO-8601 string WITH its offset, because a bare
     * "Y-m-d H:i:s" is re-parsed in config('app.timezone'), UTC here, and would
     * report every Lagos moment an hour early.
     */
    public static function queueFor(string $key, ?string $phone, array $tokens, array $context): void
    {
        static::dispatchAfterResponse($key, $phone, $tokens, $context);
    }
}
