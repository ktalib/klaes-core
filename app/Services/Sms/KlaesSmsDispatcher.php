<?php

namespace App\Services\Sms;

use App\Models\SmsDispatchLog;
use App\Models\SmsSetting;
use App\Services\BulkSmsNgService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * The one way a transactional SMS leaves this system.
 *
 * Every hook -- commissioning, RofO, file requests, deeds, caveats -- calls
 * queue() and nothing else. Whether the message is switched on, what it says,
 * whether the number is usable, whether it has already been sent for this event
 * and what the gateway made of it are all decided here, so a call site is one
 * line and cannot get any of it subtly wrong.
 *
 * FOUR RULES THIS CLASS ENFORCES
 *
 *  1. IT NEVER THROWS. A failed SMS must not roll back a commissioning or a deed
 *     registration. Everything below is inside a try/catch; the log row and a
 *     Log::error line are the record.
 *
 *  2. IT NEVER SENDS TWICE FOR ONE EVENT. The caller supplies a dedupe key
 *     naming the event ('land_fc:RES-2026-3026'), and the row is claimed on a
 *     unique index BEFORE the gateway is called. A read-then-write check would
 *     not do: a double-clicked Generate button produces two requests that
 *     interleave happily and the applicant is told twice.
 *
 *  3. IT NEVER TEXTS A SHARED NUMBER UNCONFIRMED. See ApplicantPhoneResolver --
 *     one number in file_indexings sits on 1,211 files.
 *
 *  4. IT RESOLVES ITS OWN GATEWAY, PER SEND. BulkSmsNgService keeps the last
 *     status code and failure reason as instance state, so a container-shared
 *     instance would report the PREVIOUS message's outcome against this one and
 *     the log would quietly lie. SpecialAssignmentController carries the same
 *     note for the same reason.
 *
 * NOT SENT INLINE. queue() defers to App\Jobs\SendKlaesSms via
 * dispatchAfterResponse(), so the officer's save returns at once and the gateway
 * call (15s connect, 40s total) happens after the response is flushed.
 */
class KlaesSmsDispatcher
{
    public function __construct(private ApplicantPhoneResolver $phones)
    {
    }

    /**
     * Send this message once the response has reached the user.
     *
     * @param  string       $key     An App\Models\SmsSetting::KEY_* value.
     * @param  string|null  $phone   Raw number; null is a recorded 'skipped'.
     * @param  array        $tokens  ['FileNo' => '...', ...] for the template.
     * @param  array        $context dedupe_key, file_number, subject_type,
     *                               subject_id, event_at, created_by,
     *                               phone_confirmed.
     */
    public function queue(string $key, ?string $phone, array $tokens = [], array $context = []): void
    {
        try {
            // Cheapest possible check first: a switched-off message must not
            // cost a job dispatch on every commissioning in the building.
            if (!$this->enabled($key)) {
                return;
            }

            \App\Jobs\SendKlaesSms::queueFor($key, $phone, $tokens, $this->serialiseContext($context));
        } catch (\Throwable $e) {
            Log::error('KlaesSmsDispatcher: could not queue an SMS', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Decide, claim, send, and record -- synchronously.
     *
     * Called by the job, by the control page's test send, and by the caveat
     * expiry command (which has no request to defer past).
     */
    public function sendNow(string $key, ?string $phone, array $tokens = [], array $context = []): ?SmsDispatchLog
    {
        try {
            if (!$this->enabled($key)) {
                return $this->record($key, null, null, SmsDispatchLog::STATUS_SKIPPED,
                    SmsDispatchLog::SKIP_DISABLED, $context);
            }

            // A batch message covers several files, so it uses the plural
            // wording and [FileNo] carries a range rather than one number.
            [$body, $plain] = $this->wordings($key, $tokens, (bool) ($context['plural'] ?? false));

            if ($body === null) {
                return $this->record($key, null, null, SmsDispatchLog::STATUS_SKIPPED,
                    SmsDispatchLog::SKIP_NO_TEMPLATE, $context);
            }

            /*
             | Judge the number before spending anything on it. A blank field and
             | a landline are different problems, and an applicant asking why they
             | were never told deserves to see which one it was.
             */
            $verdict = $this->phones->describe($phone);

            if ($verdict['phone'] === null) {
                return $this->record($key, null, $body, SmsDispatchLog::STATUS_SKIPPED,
                    $verdict['raw'] === null ? SmsDispatchLog::SKIP_NO_PHONE : SmsDispatchLog::SKIP_BAD_PHONE,
                    $context);
            }

            /*
             | A number on many files is an agent or a clerk until somebody says
             | otherwise. The officer clears this by confirming the number on the
             | form, which sets phone_confirmed.
             */
            if ($verdict['shared'] && empty($context['phone_confirmed'])) {
                return $this->record($key, $verdict['phone'], $body, SmsDispatchLog::STATUS_SKIPPED,
                    SmsDispatchLog::SKIP_SHARED_PHONE . ' It appears on ' . $verdict['shared_count'] . ' files.',
                    $context);
            }

            $claim = $this->claim($key, $verdict['phone'], $body, $context);

            if ($claim === null) {
                // Already sent for this event, or a send is in flight. Either
                // way this one must not go out.
                return null;
            }

            return $this->deliver($claim, $key, $verdict['phone'], $body, $plain);
        } catch (\Throwable $e) {
            Log::error('KlaesSmsDispatcher: send failed', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Is this message switched on, master switch included? */
    public function enabled(string $key): bool
    {
        return SmsSetting::isEnabled($key);
    }

    /**
     * The two wordings to try, best first.
     *
     * The second is a plainer rewrite, used only when the gateway refuses the
     * first on code 602 -- these gateways run keyword filters that have rejected
     * words like "approved" and "notice" while still answering HTTP 200.
     *
     * @return array{0:?string, 1:?string}
     */
    public function wordings(string $key, array $tokens, bool $plural = false): array
    {
        return [
            $this->substitute(SmsSetting::templateFor($key, $plural), $tokens),
            $this->substitute(SmsSetting::fallbackFor($key, $plural), $tokens),
        ];
    }

    /** The wording that would actually be sent, or null if there is none. */
    public function render(string $key, array $tokens, bool $plural = false): ?string
    {
        return $this->wordings($key, $tokens, $plural)[0];
    }

    /**
     * Render a message with its sample values, for the control page's preview.
     *
     * @return array{body:?string, characters:int, pages:int}
     */
    public function preview(string $key, ?string $template = null, bool $plural = false): array
    {
        $definition = SmsSetting::definition($key) ?? [];
        $tokens = (array) ($definition['sample'] ?? []);

        if ($plural && isset($definition['sample_plural'])) {
            // The batch preview shows a RANGE in [FileNo], because that is what
            // a batch message actually carries.
            $tokens = array_merge($tokens, (array) $definition['sample_plural']);
        }

        $body = $template !== null
            ? $this->substitute($template, $tokens)
            : $this->render($key, $tokens, $plural);

        return [
            'body' => $body,
            'characters' => $body === null ? 0 : mb_strlen($body),
            'pages' => self::pagesFor($body),
        ];
    }

    /**
     * How many SMS pages a body bills as.
     *
     * 160 characters a page. Most of these wordings are fixed by the Ministry and
     * run to two -- that is deliberate, and truncating them to save a page would
     * change what the message says.
     */
    public static function pagesFor(?string $body): int
    {
        if ($body === null || $body === '') {
            return 0;
        }

        return (int) max(1, ceil(mb_strlen($body) / 160));
    }

    /**
     * Substitute [Token] placeholders.
     *
     * A token the caller did not supply is emptied rather than left as a literal
     * "[Original Holder]" in somebody's inbox -- a message with a gap in it reads
     * as a system fault; one with a bracket in it reads as a broken system. The
     * double spaces that leaves are then collapsed.
     */
    public function substitute(?string $template, array $tokens): ?string
    {
        if ($template === null || trim($template) === '') {
            return null;
        }

        $body = $template;

        foreach ($tokens as $name => $value) {
            $body = str_replace('[' . $name . ']', trim((string) $value), $body);
        }

        // Anything the caller did not supply.
        $body = preg_replace('/\[[^\]\[]{1,40}\]/', '', $body) ?? $body;

        $body = preg_replace('/[ \t]{2,}/', ' ', $body) ?? $body;
        $body = preg_replace('/\s+([.,])/', '$1', $body) ?? $body;

        return trim($body);
    }

    /**
     * Take this event's one slot.
     *
     * Returns null when the event is already spoken for -- a message went out, or
     * another process is holding the slot right now. A row left at 'failed' IS
     * handed back, so a gateway outage does not permanently consume the event's
     * message; a 'pending' row is not, because a send may still be in flight and
     * re-sending on top of it is how the wallet gets charged twice.
     *
     * A null dedupe key means "no event identity" -- test sends from the control
     * page -- and always gets a fresh row.
     */
    private function claim(string $key, string $phone, string $body, array $context): ?SmsDispatchLog
    {
        $dedupe = $context['dedupe_key'] ?? null;

        try {
            return $this->newRow($key, $phone, null, SmsDispatchLog::STATUS_PENDING, null, $context, 1);
        } catch (QueryException $e) {
            if ($dedupe === null) {
                // Not the unique index, then -- a real database problem.
                Log::error('KlaesSmsDispatcher: could not claim an SMS slot', [
                    'key' => $key,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            $existing = SmsDispatchLog::where('dedupe_key', $dedupe)->first();

            if ($existing === null) {
                Log::error('KlaesSmsDispatcher: could not claim an SMS slot', [
                    'key' => $key,
                    'dedupe_key' => $dedupe,
                    'message' => $e->getMessage(),
                ]);

                return null;
            }

            if ($existing->status !== SmsDispatchLog::STATUS_FAILED) {
                return null;
            }

            $existing->forceFill([
                'status' => SmsDispatchLog::STATUS_PENDING,
                'phone' => $phone,
                'attempts' => (int) $existing->attempts + 1,
            ])->save();

            return $existing;
        }
    }

    /**
     * Hand the message to the gateway and write down what it said.
     */
    private function deliver(SmsDispatchLog $claim, string $key, string $phone, string $body, ?string $plain): SmsDispatchLog
    {
        // A fresh instance per send: lastStatusCode() and lastFailureReason()
        // are instance state, and a shared one reports the previous send.
        $gateway = new BulkSmsNgService();

        /*
         | Staff messages go out as KLAES, departmental ones as KANOMLPP -- the
         | split is by who receives it, not by which module raised it. Null means
         | "whatever this server is configured for".
         */
        $sender = SmsSetting::senderFor($key);

        $accepted = $gateway->sendFirstAccepted($phone, array_values(array_filter([
            $body,
            // Only offer the plain wording when it differs from what we sent --
            // an identical retry cannot get past a content filter.
            $plain !== null && $plain !== $body ? $plain : null,
        ])), $sender);

        if ($accepted !== null) {
            $claim->forceFill([
                'status' => SmsDispatchLog::STATUS_SENT,
                // The wording that WON, which may be the fallback rather than
                // the one we tried first.
                'message' => $accepted,
                'gateway_code' => $gateway->lastStatusCode(),
                'failure_reason' => null,
            ])->save();

            return $claim;
        }

        $claim->forceFill([
            'status' => SmsDispatchLog::STATUS_FAILED,
            'message' => $body,
            'gateway_code' => $gateway->lastStatusCode(),
            'failure_reason' => $gateway->lastFailureReason(),
        ])->save();

        Log::warning('KlaesSmsDispatcher: SMS not delivered', [
            'key' => $key,
            'code' => $gateway->lastStatusCode(),
            'reason' => $gateway->lastFailureReason(),
        ]);

        return $claim;
    }

    /**
     * Write a decision we made without calling the gateway.
     *
     * A skipped row is a record of a choice, not a fault, and it is the thing to
     * look at when an applicant says nobody told them.
     */
    private function record(string $key, ?string $phone, ?string $body, string $status, ?string $reason, array $context): ?SmsDispatchLog
    {
        try {
            return $this->newRow($key, $phone, $body, $status, $reason, $context, 0);
        } catch (QueryException $e) {
            // A duplicate dedupe key here just means the event was already
            // decided about. Nothing to record and nothing wrong.
            return null;
        }
    }

    private function newRow(string $key, ?string $phone, ?string $body, string $status, ?string $reason, array $context, int $attempts): SmsDispatchLog
    {
        return SmsDispatchLog::create([
            'message_key' => $key,
            'phone' => $phone,
            'message' => $body,
            'status' => $status,
            'failure_reason' => $reason,
            'attempts' => $attempts,
            'file_number' => $context['file_number'] ?? null,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => isset($context['subject_id']) ? (string) $context['subject_id'] : null,
            'dedupe_key' => $context['dedupe_key'] ?? null,
            'event_at' => $this->eventAt($context),
            'created_by' => $context['created_by'] ?? (Auth::check() ? (Auth::user()->name ?? null) : null),
        ]);
    }

    /**
     * The business moment this message reports, on the office clock.
     *
     * config('app.timezone') is UTC while Kano is WAT, so a moment stored raw
     * reads an hour early -- the same trap the attendance SMS hit.
     */
    private function eventAt(array $context): string
    {
        $timezone = config('klaes_sms.timezone', 'Africa/Lagos');
        $raw = $context['event_at'] ?? null;

        $moment = $raw ? Carbon::parse($raw) : Carbon::now();

        return $moment->setTimezone($timezone)->toDateTimeString();
    }

    /**
     * Flatten the context for the job.
     *
     * event_at crosses the job boundary as ISO-8601 WITH its offset, never as a
     * bare "Y-m-d H:i:s" -- a bare string is re-parsed in config('app.timezone'),
     * UTC here, so a 17:12 Lagos moment came back as 17:12 UTC and was reported
     * to the applicant as 18:12.
     */
    private function serialiseContext(array $context): array
    {
        if (isset($context['event_at']) && $context['event_at'] instanceof \DateTimeInterface) {
            $context['event_at'] = Carbon::instance($context['event_at'])->toIso8601String();
        }

        if (!isset($context['created_by']) && Auth::check()) {
            // Captured now, while the request still knows who is signed in --
            // the job runs after the response, with Auth still available, but
            // the expiry command has no user at all.
            $context['created_by'] = Auth::user()->name ?? null;
        }

        return $context;
    }
}
