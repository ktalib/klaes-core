<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Account verification by email, SMS, or one code delivered to both contacts.
 * Combined delivery proves account access through either contact; it does not
 * independently verify the mailbox, so it does not set email_verified_at.
 */
class PhoneOtpService
{
    /** The two routes a code can travel. NULL on a row means SMS — see below. */
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_BOTH = 'both';

    /** Cache key holding the wrong-code count for one user. */
    private const ATTEMPTS_KEY = 'phone_otp.attempts.';

    /** Cache key holding the route the last code went by; see recordedChannel(). */
    private const CHANNEL_KEY = 'phone_otp.channel.';

    /** How long the answer to "are the columns deployed?" is remembered. */
    private const SCHEMA_CACHE_KEY = 'phone_otp.columns_ready';
    private const CHANNEL_SCHEMA_CACHE_KEY = 'phone_otp.channel_column_ready';
    private const SCHEMA_CACHE_TTL = 300;

    /** Per-request memo of the schema checks; see columnsReady(). */
    private static ?bool $columnsReadyMemo = null;
    private static ?bool $channelColumnReadyMemo = null;

    public function __construct(private BulkSmsNgService $gateway)
    {
    }

    /**
     * Is the gate switched on AND able to record a result AND able to send at all?
     */
    public function gateEnabled(): bool
    {
        return (bool) config('phone_verification.enabled', true)
            && $this->enabledChannels() !== []
            && $this->columnsReady();
    }

    /**
     * Should this user be held at the verification card?
     *
     * The passport photo comes first: User::getNeedsPhoneVerificationAttribute()
     * defers to it so a user never faces two blocking cards at once.
     */
    public function needsVerification(User $user): bool
    {
        if (!$this->gateEnabled()) {
            return false;
        }

        return !$this->isVerified($user);
    }

    public function isVerified(User $user): bool
    {
        return (bool) ($user->getAttribute('is_otp_verified') ?? false);
    }

    /* ── channels ─────────────────────────────────────────────────────────── */

    /**
     * The routes that are open, in the order the card should offer them.
     *
     * @return array<int,string>
     */
    public function enabledChannels(): array
    {
        $open = [];

        foreach ([self::CHANNEL_EMAIL, self::CHANNEL_SMS] as $channel) {
            if ((bool) config('phone_verification.channels.' . $channel, true)) {
                $open[] = $channel;
            }
        }

        return $open;
    }

    public function channelEnabled(string $channel): bool
    {
        return in_array($this->normaliseChannel($channel), $this->enabledChannels(), true);
    }

    /**
     * The route the card opens on.
     *
     * Configured (email), unless that route has been closed off — in which case
     * the card must open on one that is actually available rather than on a step
     * whose button refuses everything.
     */
    public function defaultChannel(): string
    {
        $configured = $this->normaliseChannel((string) config('phone_verification.default_channel', self::CHANNEL_EMAIL));
        $open = $this->enabledChannels();

        if ($open === []) {
            return $configured;
        }

        return in_array($configured, $open, true) ? $configured : $open[0];
    }

    /**
     * The route the code on this account actually went by, or null if unknown.
     *
     * users.otp_channel is the durable record. The cache is a SECOND copy of the
     * same answer, and it is not redundant: the column arrived a day after the
     * rest of the gate and columnsReady()-style checks are cached for five
     * minutes, so there is a real window — a fresh deployment, or the five
     * minutes after one — in which a code goes out with nowhere to write its
     * route. Without this fallback the whole thing degrades to "assume SMS",
     * which silently skips the resend cooldown on email, tells a user who
     * verified by email that their phone number was confirmed, and lets a
     * harmless phone-number edit void an email-proved account.
     *
     * The cache entry outlives the code it describes by the cooldown, so it is
     * still there for the resend and the verify that follow it.
     */
    public function recordedChannel(User $user): ?string
    {
        if ($this->channelColumnReady()) {
            $stored = $this->normaliseChannel((string) ($user->getAttribute('otp_channel') ?? ''));

            if ($stored !== '') {
                return $stored;
            }
        }

        try {
            $remembered = $this->normaliseChannel((string) (Cache::get(self::CHANNEL_KEY . $user->id) ?? ''));
        } catch (\Throwable $e) {
            return null;
        }

        return $remembered === '' ? null : $remembered;
    }

    /**
     * The route this account is part-way through.
     *
     * A code already sent has a cooldown running against it and a masked address
     * to show; the card has to come back to the same step after a page reload,
     * or the user is invited to start again on the other route while the first
     * one is still counting down.
     */
    public function currentChannel(User $user): string
    {
        $recorded = $this->recordedChannel($user);

        if ($recorded === self::CHANNEL_BOTH) {
            return $recorded;
        }

        if ($recorded !== null && in_array($recorded, $this->enabledChannels(), true)) {
            return $recorded;
        }

        // A code is in flight but nothing recorded where it went: an account left
        // mid-verification by the SMS-only version of this gate, which is the only
        // way that state can now arise. A route we no longer offer lands here too.
        if ($recorded === null && $user->getAttribute('otp_sent_at') && $this->channelEnabled(self::CHANNEL_SMS)) {
            return self::CHANNEL_SMS;
        }

        return $this->defaultChannel();
    }

    /* ── schema ───────────────────────────────────────────────────────────── */

    /**
     * Are users.otp / otp_sent_at / is_otp_verified actually on this database?
     *
     * Cached: this is asked on effectively every request through the middleware,
     * and hasColumn() is a catalogue query. Five minutes is short enough that a
     * deployment does not need a cache flush to take effect.
     */
    public function columnsReady(): bool
    {
        // Asked several times in one page render — the middleware, the header banner
        // and the sidebar each consult the gate — so the answer is memoised for the
        // request as well as cached across them. A table's shape cannot change
        // mid-request, so this is only ever saving reads.
        if (self::$columnsReadyMemo !== null) {
            return self::$columnsReadyMemo;
        }

        $cached = Cache::get(self::SCHEMA_CACHE_KEY);

        if ($cached !== null) {
            return self::$columnsReadyMemo = (bool) $cached;
        }

        try {
            $schema = Schema::connection('sqlsrv');
            $ready = $schema->hasColumn('users', 'is_otp_verified')
                && $schema->hasColumn('users', 'otp')
                && $schema->hasColumn('users', 'otp_sent_at');
        } catch (\Throwable $e) {
            // The database could not be asked. Hold nobody on the strength of a
            // question that was never answered.
            Log::warning('PhoneOtpService: could not inspect the users table', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        Cache::put(self::SCHEMA_CACHE_KEY, $ready, self::SCHEMA_CACHE_TTL);

        return self::$columnsReadyMemo = $ready;
    }

    /**
     * Is users.otp_channel on this database?
     *
     * Asked separately from the other three ON PURPOSE. It arrived a day later,
     * and a server that has run the first SQL script but not the second must
     * still be able to send codes — it simply cannot remember which route it
     * used, which costs a resend the masked address and nothing else. Making the
     * gate depend on it would turn a missed deployment step into a lock-out.
     */
    public function channelColumnReady(): bool
    {
        if (self::$channelColumnReadyMemo !== null) {
            return self::$channelColumnReadyMemo;
        }

        $cached = Cache::get(self::CHANNEL_SCHEMA_CACHE_KEY);

        if ($cached !== null) {
            return self::$channelColumnReadyMemo = (bool) $cached;
        }

        try {
            $ready = Schema::connection('sqlsrv')->hasColumn('users', 'otp_channel');
        } catch (\Throwable $e) {
            Log::warning('PhoneOtpService: could not inspect users.otp_channel', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        Cache::put(self::CHANNEL_SCHEMA_CACHE_KEY, $ready, self::SCHEMA_CACHE_TTL);

        return self::$channelColumnReadyMemo = $ready;
    }

    /* ── sending ──────────────────────────────────────────────────────────── */

    /**
     * Send a fresh code, storing it and the address it went to on the account.
     *
     * @param string|null $target  The address the user confirmed or corrected on
     *                             the card — an email address or a mobile number,
     *                             depending on $channel. Null means "use what is
     *                             on file".
     * @param string|null $channel 'email' or 'sms'. Null means the route this
     *                             account is already on, or the default.
     *
     * @return array{sent:bool, message:string, channel:string, target:string|null,
     *               masked:string|null, retry_after:int, expires_in:int, reason:string|null}
     */
    /** Send one account OTP to both contacts in the same submission. */
    public function sendToBoth(User $user, string $email, string $phone): array
    {
        $channel = self::CHANNEL_BOTH;
        $email = $this->normaliseEmail($email);
        $rawPhone = $phone;
        $phone = BulkSmsNgService::normalizeNumber($phone);
        if (!$this->columnsReady()) {
            return $this->result(false, __('Account verification is not available yet. Contact ICT.'), $channel);
        }
        if (!$email || $this->isPlaceholderEmail($email)) {
            return $this->result(false, __('Enter a valid email address that you use.'), $channel);
        }
        if (!$phone) {
            return $this->result(false, __('Enter a usable Nigerian mobile number, such as 08012345678.'), $channel);
        }
        if (!$this->isLocalMobileFormat($rawPhone)) {
            return $this->result(false, __('Enter an 11-digit mobile number starting with 0, such as 08012345678.'), $channel);
        }
        if ($this->emailBelongsToAnotherAccount($user, $email)) {
            return $this->result(false, __('That email address is already on another KLAES account.'), $channel);
        }
        if (!$this->channelEnabled(self::CHANNEL_EMAIL) || !$this->channelEnabled(self::CHANNEL_SMS)) {
            return $this->result(false, __('Email and SMS verification must both be enabled. Contact ICT.'), $channel);
        }
        $wait = $this->cooldownRemaining($user);
        if ($wait > 0) {
            return $this->cooldownResult($channel, $email, $wait);
        }
        $code = $this->generateCode();
        $this->stashCode($user, $channel, ['email' => $email, 'phone_number' => $phone], $code);
        // Each delivery is attempted even if the other fails. Both use the saved code.
        $mail = $this->sendByEmail($user, $email, $code);
        try {
            $sms = $this->sendBySms($user, $phone, $code);
        } catch (\Throwable $e) {
            Log::warning('PhoneOtpService: SMS delivery failed', ['user_id' => $user->id]);
            $this->logDispatch($user, $phone, false, null, 'SMS delivery failed');
            $sms = ['sent' => false];
        }
        $sent = $mail['sent'] || $sms['sent'];
        $message = $mail['sent'] && $sms['sent']
            ? __('The same code has been sent to your email and phone. Enter the code from either message.')
            : ($mail['sent']
                ? __('The code was emailed, but SMS delivery failed. Use the code in your email.')
                : ($sms['sent']
                    ? __('The code was sent by SMS, but email delivery failed. Use the code on your phone.')
                    : __('Neither email nor SMS could be sent. Try again after the countdown or contact ICT.')));
        $result = $this->result($sent, $message, $channel, null, $this->cooldownSeconds());
        $result['masked'] = $this->maskEmail($email) . ' / ' . $this->mask($phone);
        return $result;
    }

    public function sendCode(User $user, ?string $target = null, ?string $channel = null): array
    {
        $channel = $channel === null || $this->normaliseChannel($channel) === ''
            ? $this->currentChannel($user)
            : $this->normaliseChannel($channel);

        if (!$this->columnsReady()) {
            return $this->result(false, __('Account verification is not available on this server yet. Please contact ICT.'), $channel);
        }

        if (!$this->channelEnabled($channel)) {
            return $this->result(
                false,
                $channel === self::CHANNEL_EMAIL
                    ? __('Codes are not being sent by email at the moment. Use the mobile number instead.')
                    : __('Codes are not being sent by SMS at the moment. Use your email address instead.'),
                $channel
            );
        }

        return $channel === self::CHANNEL_EMAIL
            ? $this->sendByEmail($user, $target)
            : $this->sendBySms($user, $target);
    }

    /**
     * The default route: a code to the member of staff's email address.
     *
     * @return array{sent:bool, message:string, channel:string, target:string|null,
     *               masked:string|null, retry_after:int, expires_in:int, reason:string|null}
     */
    private function sendByEmail(User $user, ?string $email, ?string $sharedCode = null): array
    {
        $channel = self::CHANNEL_EMAIL;
        $raw = trim((string) ($email ?? $user->email ?? ''));

        if ($raw === '') {
            return $this->result(false, __('Enter the email address that should receive the code.'), $channel);
        }

        $normalised = $this->normaliseEmail($raw);

        if ($normalised === null) {
            return $this->result(false, __('That does not look like an email address. Enter it as name@example.com.'), $channel);
        }

        // A seeded placeholder. SMTP would accept the message and nothing would
        // ever arrive, so the user would sit at a card waiting for a code that
        // does not exist — the one failure with no way out of it.
        if ($this->isPlaceholderEmail($normalised)) {
            return $this->result(
                false,
                __('That address (:email) is a placeholder, not a real mailbox — no code can reach it. Type the email address you actually use, or switch to your mobile number.', [
                    'email' => $normalised,
                ]),
                $channel
            );
        }

        // Email is also a sign-in identifier here (AuthPageController matches on
        // email OR username), so two accounts claiming one address makes signing
        // in by email ambiguous — and it would send this person's code to the
        // colleague who owns the mailbox.
        if ($this->emailBelongsToAnotherAccount($user, $normalised)) {
            return $this->result(
                false,
                __('That email address is already on another KLAES account. Use your own address, or contact ICT if you believe this is a mistake.'),
                $channel
            );
        }

        $wait = $this->cooldownRemaining($user);
        $sameTarget = $this->normaliseEmail((string) ($user->email ?? '')) === $normalised
            && $this->sameRoute($user, $channel);

        if ($sharedCode === null && $sameTarget && $wait > 0) {
            return $this->cooldownResult($channel, $normalised, $wait);
        }

        $code = $sharedCode ?? $this->generateCode();

        // Written BEFORE the send, and kept even if it fails: ICT reads the code
        // back to staff whose mailbox is unreachable, and that support route is
        // the only one those people have.
        if ($sharedCode === null) {
            $this->stashCode($user, $channel, ['email' => $normalised], $code);
        }

        try {
            /*
             | ->send(), not ->queue(). QUEUE_CONNECTION is `database` here and
             | nothing guarantees a worker is running; a queued sign-in code sits
             | in the jobs table while the user watches a card that says one is on
             | its way. The mailable deliberately does not implement ShouldQueue,
             | and this call must stay synchronous for the same reason.
             */
            Mail::to($normalised)->send(new \App\Mail\AccountVerificationCode(
                code: $code,
                recipientName: (string) ($user->first_name ?? $user->name ?? 'there'),
                expiresInMinutes: $this->ttlMinutes(),
                requestedFrom: request()?->ip(),
            ));
        } catch (\Throwable $e) {
            Log::warning('PhoneOtpService: verification email not delivered', [
                'user_id' => $user->id,
                'email' => $normalised,
                'message' => $e->getMessage(),
            ]);

            return $this->result(
                false,
                $this->explainEmailFailure($e),
                $channel,
                $normalised,
                $this->cooldownSeconds(),
                $e->getMessage()
            );
        }

        return $this->result(
            true,
            __('A verification code has been sent to :email. It expires in :minutes minutes.', [
                'email' => $this->maskEmail($normalised),
                'minutes' => $this->ttlMinutes(),
            ]),
            $channel,
            $normalised,
            $this->cooldownSeconds()
        );
    }

    /**
     * The alternative route: a code by SMS to the mobile number on the account.
     *
     * @return array{sent:bool, message:string, channel:string, target:string|null,
     *               masked:string|null, retry_after:int, expires_in:int, reason:string|null}
     */
    private function sendBySms(User $user, ?string $phone, ?string $sharedCode = null): array
    {
        $channel = self::CHANNEL_SMS;
        $raw = trim((string) ($phone ?? $user->phone_number ?? ''));

        if ($raw === '') {
            return $this->result(false, __('Enter the mobile number that should receive the code.'), $channel);
        }

        $normalised = BulkSmsNgService::normalizeNumber($raw);

        if ($normalised === null) {
            return $this->result(false, __('That is not a usable Nigerian mobile number. Enter it as 08012345678.'), $channel);
        }

        // A code already sent to THIS number, recently enough that a second one
        // would only cost credit. A different number is always allowed straight
        // away: the user has just told us the first one was wrong.
        $sameTarget = BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? '')) === $normalised
            && $this->sameRoute($user, $channel);
        $wait = $this->cooldownRemaining($user);

        if ($sharedCode === null && $sameTarget && $wait > 0) {
            return $this->cooldownResult($channel, $normalised, $wait);
        }

        $code = $sharedCode ?? $this->generateCode();

        // Written BEFORE the gateway call, and kept even if the send fails: ICT
        // reads the code back to staff whose handset is out of coverage or whose
        // network is holding promotional traffic until 08:30, and that support
        // route is the only one those people have.
        if ($sharedCode === null) {
            $this->stashCode($user, $channel, ['phone_number' => $normalised], $code);
        }

        // A sign-in code goes to a member of staff, so it carries the staff
        // sender ID (KLAES). Null if the catalogue cannot be read, which leaves
        // the server default in place rather than failing the send.
        $sender = null;

        try {
            $sender = \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP);
        } catch (\Throwable $e) {
            // Fall through on the server default.
        }

        $accepted = $this->gateway->sendFirstAccepted($normalised, $this->messagesFor($code), $sender);

        if ($accepted !== null) {
            $this->logDispatch($user, $normalised, true, $this->gateway->lastStatusCode(), null);

            return $this->result(
                true,
                __('A verification code has been sent to :phone. It expires in :minutes minutes.', [
                    'phone' => $this->mask($normalised),
                    'minutes' => $this->ttlMinutes(),
                ]),
                $channel,
                $normalised,
                $this->cooldownSeconds()
            );
        }

        $reason = $this->gateway->lastFailureReason();
        $codeOut = $this->gateway->lastStatusCode();

        Log::warning('PhoneOtpService: verification code not delivered', [
            'user_id' => $user->id,
            'phone' => $normalised,
            'gateway_code' => $codeOut,
            'reason' => $reason,
        ]);

        $this->logDispatch($user, $normalised, false, $codeOut, $reason);

        return $this->result(
            false,
            $this->explainFailure($codeOut, $reason),
            $channel,
            $normalised,
            $this->cooldownSeconds(),
            $reason
        );
    }

    /**
     * Write the code, the moment, the route and the address onto the account.
     *
     * One save, because these belong together: a code without the address it was
     * sent to cannot be checked against anything, and a code without the moment
     * never expires.
     *
     * @param array<string,string> $address The column that carries the target for
     *                                      this route — email, or phone_number.
     */
    private function stashCode(User $user, string $channel, array $address, string $code): void
    {
        $attributes = $address + [
            'otp' => $code,
            'otp_sent_at' => now(),
            // Changing the address voids any earlier proof. Belt and braces: the
            // model event does this too, and this path must not depend on it.
            'is_otp_verified' => false,
        ];

        // Only if the second SQL script has been run; see channelColumnReady().
        if ($this->channelColumnReady()) {
            $attributes['otp_channel'] = $channel;
        }

        $user->forceFill($attributes)->save();

        $this->clearAttempts($user);
        $this->rememberChannel($user, $channel);
    }

    /**
     * The cache half of recordedChannel() — see the note there for why a column
     * and a cache entry both hold this.
     *
     * Kept for the code's lifetime plus the cooldown, so the resend and the
     * verify that follow it can both still read it. Failing to write it costs
     * nothing on a database that has the column.
     */
    private function rememberChannel(User $user, string $channel): void
    {
        try {
            Cache::put(
                self::CHANNEL_KEY . $user->id,
                $channel,
                now()->addSeconds($this->ttlMinutes() * 60 + $this->cooldownSeconds())
            );
        } catch (\Throwable $e) {
            // No cache. On a database that carries users.otp_channel this changes
            // nothing at all; on one that does not, the gate falls back to
            // assuming SMS, which is where it started.
        }
    }

    /* ── verifying ────────────────────────────────────────────────────────── */

    /**
     * Check a code the user typed back.
     *
     * @return array{verified:bool, message:string, attempts_left:int|null}
     */
    public function verifyCode(User $user, string $code): array
    {
        if (!$this->columnsReady()) {
            return ['verified' => false, 'message' => __('Account verification is not available on this server yet. Please contact ICT.'), 'attempts_left' => null];
        }

        $expected = trim((string) ($user->getAttribute('otp') ?? ''));
        $given = preg_replace('/\D+/', '', $code) ?? '';

        if ($expected === '') {
            return ['verified' => false, 'message' => __('No code has been sent yet. Ask for one first.'), 'attempts_left' => null];
        }

        if ($this->hasExpired($user)) {
            return ['verified' => false, 'message' => __('That code has expired. Ask for a new one.'), 'attempts_left' => null];
        }

        if (!hash_equals($expected, $given)) {
            $left = $this->countWrongAttempt($user);

            if ($left <= 0) {
                // Burn the code rather than the account: the user asks for
                // another one and carries on. Nothing here locks a person out —
                // a lockout would need an administrator to undo, and the address
                // being wrong is the very thing they are trying to fix.
                $user->forceFill(['otp' => null])->save();
                $this->clearAttempts($user);

                return ['verified' => false, 'message' => __('Too many wrong codes. Ask for a new one.'), 'attempts_left' => 0];
            }

            return [
                'verified' => false,
                'message' => trans_choice(
                    '{1}That code is not correct. One more attempt before you must request a new code.|[2,*]That code is not correct. :count attempts left.',
                    $left,
                    ['count' => $left]
                ),
                'attempts_left' => $left,
            ];
        }

        $verifiedBy = $this->currentChannel($user);
        $this->markVerified($user, $verifiedBy);

        return [
            'verified' => true,
            'message' => $verifiedBy === self::CHANNEL_BOTH
                ? __('Your account has been verified. You can now use the system.')
                : ($verifiedBy === self::CHANNEL_EMAIL
                ? __('Your email address has been verified. You can now use the system.')
                : __('Your phone number has been verified. You can now use the system.')),
            'attempts_left' => null,
        ];
    }

    /**
     * Record the account as verified and drop the code.
     *
     * Also used by `phone:verify-status --verify`, which is how somebody who can
     * receive neither an email nor an SMS is let through.
     */
    public function markVerified(User $user, ?string $channel = null): void
    {
        $attributes = [
            'is_otp_verified' => true,
            'otp' => null,
        ];

        /*
         | A code typed back off an email is proof of that mailbox, which is
         | exactly what email_verified_at records — and this is the only place in
         | the application that ever earns it. Set only for the email route: an
         | SMS proves the handset and says nothing about the address.
         */
        if ($channel === self::CHANNEL_EMAIL && !$user->getAttribute('email_verified_at')) {
            $attributes['email_verified_at'] = now();
        }

        $user->forceFill($attributes)->save();

        $this->clearAttempts($user);
    }

    /**
     * Put an account back behind the gate.
     */
    public function reset(User $user): void
    {
        $attributes = [
            'is_otp_verified' => false,
            'otp' => null,
            'otp_sent_at' => null,
        ];

        if ($this->channelColumnReady()) {
            $attributes['otp_channel'] = null;
        }

        $user->forceFill($attributes)->save();

        $this->clearAttempts($user);

        try {
            Cache::forget(self::CHANNEL_KEY . $user->id);
        } catch (\Throwable $e) {
            // A stale entry costs one wrong guess at which route a code went by,
            // and only until the next send overwrites it.
        }
    }

    /* ── timing ───────────────────────────────────────────────────────────── */

    /**
     * Seconds until this user may ask for another code on the same address.
     */
    public function cooldownRemaining(User $user): int
    {
        $sentAt = $user->getAttribute('otp_sent_at');

        if (!$sentAt) {
            return 0;
        }

        $sentAt = $sentAt instanceof Carbon ? $sentAt : Carbon::parse($sentAt);
        $elapsed = $sentAt->diffInSeconds(now(), false);

        // A negative elapsed time means the row carries a moment in the future —
        // a clock change, or a value written by another process. Treat it as
        // "just sent" rather than letting it hand out an unlimited cooldown.
        if ($elapsed < 0) {
            return $this->cooldownSeconds();
        }

        return (int) max(0, $this->cooldownSeconds() - $elapsed);
    }

    /**
     * Has the code on file passed its expiry?
     */
    public function hasExpired(User $user): bool
    {
        $sentAt = $user->getAttribute('otp_sent_at');

        if (!$sentAt) {
            return true;
        }

        $sentAt = $sentAt instanceof Carbon ? $sentAt : Carbon::parse($sentAt);

        return $sentAt->addMinutes($this->ttlMinutes())->isPast();
    }

    /* ── addresses ────────────────────────────────────────────────────────── */

    /**
     * "0803****678" — enough for the user to recognise the number without
     * printing it in full on a screen somebody else may be looking at.
     */
    public function mask(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // Show it the way staff read it back: local 0-leading form.
        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            $digits = '0' . substr($digits, 3);
        }

        if (strlen($digits) <= 5) {
            return $digits;
        }

        return substr($digits, 0, 4) . str_repeat('*', max(0, strlen($digits) - 7)) . substr($digits, -3);
    }

    /**
     * "mus***@gmail.com" — the same courtesy for an address.
     *
     * The DOMAIN is shown in full on purpose. It is the half that tells somebody
     * they have the wrong account ("that is my old ministry address"), and it is
     * not the half that identifies a person.
     */
    public function maskEmail(?string $email): ?string
    {
        $value = trim((string) $email);

        if ($value === '' || !str_contains($value, '@')) {
            return $value === '' ? null : $value;
        }

        [$local, $domain] = explode('@', $value, 2);

        if ($local === '') {
            return $value;
        }

        $keep = mb_strlen($local) <= 2 ? 1 : 3;
        $shown = mb_substr($local, 0, min($keep, mb_strlen($local)));

        return $shown . str_repeat('*', max(1, mb_strlen($local) - mb_strlen($shown))) . '@' . $domain;
    }

    /**
     * The number as the card should show it for editing: local 0-leading form.
     */
    public function localFormat(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '0' . substr($digits, 3);
        }

        return $digits;
    }

    /**
     * The address the card should prefill, or '' when there is nothing worth
     * offering.
     *
     * A placeholder is deliberately treated as nothing. Prefilling
     * `abdul@example.org` invites the user to press Send on an address that
     * cannot receive anything — and a full box reads as a value somebody has
     * checked, which this one never was.
     */
    public function emailForCard(User $user): string
    {
        $normalised = $this->normaliseEmail((string) ($user->email ?? ''));

        if ($normalised === null || $this->isPlaceholderEmail($normalised)) {
            return '';
        }

        return $normalised;
    }

    /**
     * Lower-cased and trimmed, or null when it is not an address at all.
     */
    /**
     * Is this the shape a member of staff actually reads off their handset?
     *
     * Eleven digits beginning with a zero, once the spaces and dashes people
     * write numbers with are taken out. Deliberately stricter than
     * BulkSmsNgService::normalizeNumber(), which also takes a bare ten digits and
     * the 234-prefixed form: that normaliser serves every outbound message in the
     * system and must keep swallowing the shapes already sitting in the tables,
     * whereas this card is where a number is TYPED, and the one place a typo can
     * still be sent back to the person who made it.
     */
    public function isLocalMobileFormat(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return (bool) preg_match('/^0\d{10}$/', $digits);
    }

    public function normaliseEmail(?string $email): ?string
    {
        $value = mb_strtolower(trim((string) $email));

        if ($value === '' || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $value;
    }

    /**
     * Is this one of the seeded addresses that is not a mailbox?
     *
     * A configured entry beginning with a dot matches the domain and every
     * subdomain of it (".local" catches "klaes.local" and "pc12.klaes.local");
     * anything else must match the domain exactly.
     */
    public function isPlaceholderEmail(?string $email): bool
    {
        $value = $this->normaliseEmail($email);

        if ($value === null) {
            return false;
        }

        $domain = mb_strtolower(substr(strrchr($value, '@') ?: '', 1));

        if ($domain === '') {
            return false;
        }

        foreach ((array) config('phone_verification.placeholder_email_domains', []) as $pattern) {
            $pattern = mb_strtolower(trim((string) $pattern));

            if ($pattern === '') {
                continue;
            }

            if (str_starts_with($pattern, '.')) {
                if (str_ends_with($domain, $pattern) || $domain === ltrim($pattern, '.')) {
                    return true;
                }

                continue;
            }

            if ($domain === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this address already sit on somebody else's account?
     *
     * Fails OPEN: if the question cannot be asked, the send goes ahead. A
     * database hiccup must not be the thing that holds a member of staff at a
     * card, and the worst case is a duplicate address — of which the table
     * already holds fourteen from before the rule existed.
     */
    public function emailBelongsToAnotherAccount(User $user, string $email): bool
    {
        try {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->where('id', '!=', $user->id)
                ->exists();
        } catch (\Throwable $e) {
            Log::warning('PhoneOtpService: could not check the address against other accounts', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /* ── settings ─────────────────────────────────────────────────────────── */

    public function ttlMinutes(): int
    {
        return max(1, (int) config('phone_verification.ttl_minutes', 15));
    }

    public function cooldownSeconds(): int
    {
        return max(0, (int) config('phone_verification.resend_cooldown_seconds', 60));
    }

    public function maxAttempts(): int
    {
        return max(1, (int) config('phone_verification.max_attempts', 5));
    }

    /* ── internals ────────────────────────────────────────────────────────── */

    /**
     * Is the code already in flight on the route being asked for?
     *
     * Only half of the "same target" test — the address has to match too. It is
     * separate because of what happens when the route is UNKNOWN: an unrecorded
     * channel counts as a match, so the cooldown still applies. The alternative
     * is worse in both directions. Treating unknown as a different route would
     * let anybody skip the wait by pressing the button again, and on the SMS side
     * that spends the wallet a page at a time; and the cooldown is only ever a
     * minute, so the cost of being wrong the other way is that somebody waits
     * sixty seconds before switching routes.
     */
    private function sameRoute(User $user, string $channel): bool
    {
        $recorded = $this->recordedChannel($user);

        // 'both' covers every single-channel resend too: an account verified on
        // both routes must still wait out the cooldown when asking for SMS (or
        // email) again, not skip it because 'both' != 'sms'. Without this an
        // account recorded as 'both' had NO resend cooldown at all — see the
        // 2026-09-24 incident where user 1 (otp_channel=both) was sent 51 real
        // SMS in ten minutes, one per request, because every single-channel
        // resend looked like "a different route" and sailed straight past the
        // wait check below.
        return $recorded === null || $recorded === $channel || $recorded === self::CHANNEL_BOTH;
    }

    private function normaliseChannel(string $channel): string
    {
        $value = mb_strtolower(trim($channel));

        return in_array($value, [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_BOTH], true) ? $value : '';
    }

    /**
     * Best wording first, then a plainer one.
     *
     * Both fit one 160-character page. The fallback exists because these
     * gateways run keyword filters that refuse a wording outright while
     * answering HTTP 200 — BetaSMS does it on "code" and "approved" — and
     * BulkSmsNgService::sendFirstAccepted retries on the one status that could
     * plausibly be about the text (602).
     *
     * @return array<int,string>
     */
    private function messagesFor(string $code): array
    {
        $tokens = ['Code' => $code, 'Minutes' => (string) $this->ttlMinutes()];

        /*
         | The wording is editable in the SMS Control Centre, like every other
         | message -- what this one does NOT get there is an off switch; see
         | SmsSetting::KEY_PHONE_OTP. An override saved on the page wins; with
         | none saved, the catalogue default applies; and if the catalogue cannot
         | be read at all we fall back to config/phone_verification.php, which is
         | where this wording lived before.
         */
        try {
            $dispatcher = app(\App\Services\Sms\KlaesSmsDispatcher::class);
            [$rich, $plain] = $dispatcher->wordings(\App\Models\SmsSetting::KEY_PHONE_OTP, $tokens);

            if ($rich !== null) {
                return array_values(array_filter([$rich, $plain]));
            }
        } catch (\Throwable $e) {
            Log::warning('PhoneOtpService: falling back to the built-in wording', [
                'message' => $e->getMessage(),
            ]);
        }

        // {code} is the legacy placeholder in config/phone_verification.php.
        $primary = (string) config('phone_verification.message');
        $fallback = (string) config('phone_verification.message_fallback');

        return [
            str_replace('{code}', $code, $primary),
            str_replace('{code}', $code, $fallback),
        ];
    }

    /**
     * Record a verification code in sms_dispatch_logs, so it shows up on the
     * SMS Control Centre next to every other message.
     *
     * SMS ONLY. That page is a log of what went through the SMS gateway; an
     * email in it would misreport the wallet and the delivery statuses, neither
     * of which apply.
     *
     * THE CODE ITSELF IS NEVER WRITTEN THERE. That log is readable by anyone who
     * can open the control page; storing a live sign-in code in it would hand
     * them somebody else's account. Only the outcome is kept.
     *
     * No dedupe key: a resend is a deliberate second send, not a duplicate.
     */
    /** Record an OTP delivery outcome for either verification or sign-in. */
    public function logDispatch(User $user, string $phone, bool $sent, ?string $gatewayCode, ?string $reason): void
    {
        try {
            \App\Models\SmsDispatchLog::create([
                'message_key' => \App\Models\SmsSetting::KEY_PHONE_OTP,
                'phone' => $phone,
                'message' => '[verification code withheld]',
                'status' => $sent
                    ? \App\Models\SmsDispatchLog::STATUS_SENT
                    : \App\Models\SmsDispatchLog::STATUS_FAILED,
                'gateway_code' => $gatewayCode,
                'failure_reason' => $reason,
                'attempts' => 1,
                'subject_type' => 'users',
                'subject_id' => (string) $user->id,
                'dedupe_key' => null,
                'event_at' => now(config('klaes_sms.timezone', 'Africa/Lagos'))->toDateTimeString(),
                'created_by' => $user->name ?? null,
            ]);
        } catch (\Throwable $e) {
            // The log table may not be deployed. Never let bookkeeping stop
            // somebody receiving the code they need to sign in.
            Log::debug('PhoneOtpService: could not write the dispatch log', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Turn a gateway status into something the person at the card can act on.
     *
     * "Message not sent" tells a member of staff nothing they can do. Every one
     * of these has a different next step, and 609 in particular is not a fault
     * at all — the message was accepted and is simply being held by the network.
     */
    /** Return a safe, actionable SMS-delivery error for an OTP screen. */
    public function explainFailure(?string $status, ?string $reason): string
    {
        return match ($status) {
            '609' => __('Nigerian networks do not deliver these messages between 7:45pm and 8:00am, so your code will not arrive until about 8:30am. Use your email address instead, or contact ICT if you need access before then.'),
            '604' => __('Codes cannot be sent by SMS at the moment because the SMS account has run out of credit. Use your email address instead, or contact ICT.'),
            '601' => __('The SMS service is not configured on this server. Use your email address instead, or contact ICT.'),
            default => $reason
                ? __('Your code could not be sent by SMS: :reason. Try your email address instead, or contact ICT.', ['reason' => $reason])
                : __('Your code could not be sent by SMS. Check the number and try again, or use your email address instead.'),
        };
    }

    /**
     * The same courtesy for a mail failure.
     *
     * The common ones are not the user's doing and none of them is worth showing
     * verbatim — an SMTP exception carries the server's hostname and sometimes
     * its credentials path. The user is told what to do; the detail goes to the
     * log, where ICT can read it.
     */
    /** Return a safe, actionable email-delivery error for an OTP screen. */
    public function explainEmailFailure(\Throwable $e): string
    {
        $text = mb_strtolower($e->getMessage());

        if (str_contains($text, 'authenticat') || str_contains($text, 'credential')) {
            return __('Email is not set up correctly on this server, so no code could be sent. Use your mobile number instead, or contact ICT.');
        }

        if (str_contains($text, 'could not be resolved') || str_contains($text, 'connection') || str_contains($text, 'timed out')) {
            return __('The mail server could not be reached, so your code was not sent. Try again in a moment, use your mobile number, or contact ICT.');
        }

        if (str_contains($text, 'recipient') || str_contains($text, 'mailbox') || str_contains($text, '550')) {
            return __('That mailbox would not accept the message. Check the address, or use your mobile number instead.');
        }

        return __('Your code could not be emailed. Try again, use your mobile number instead, or contact ICT.');
    }

    /** Generate a cryptographically secure numeric code for an OTP challenge. */
    public function generateCode(): string
    {
        $length = max(4, min(10, (int) config('phone_verification.code_length', 6)));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Record a wrong code and say how many tries are left.
     */
    private function countWrongAttempt(User $user): int
    {
        $key = self::ATTEMPTS_KEY . $user->id;

        try {
            $used = (int) Cache::get($key, 0) + 1;
            Cache::put($key, $used, now()->addMinutes($this->ttlMinutes()));
        } catch (\Throwable $e) {
            // No cache, no counter. The route throttle still applies, and a
            // wrong code is still a wrong code.
            return $this->maxAttempts();
        }

        return max(0, $this->maxAttempts() - $used);
    }

    private function clearAttempts(User $user): void
    {
        try {
            Cache::forget(self::ATTEMPTS_KEY . $user->id);
        } catch (\Throwable $e) {
            // Nothing to do: a stale counter costs the user one extra resend.
        }
    }

    /**
     * "Your code was just sent" — the one refusal that is not a fault, phrased
     * the same way on both routes.
     *
     * @return array{sent:bool, message:string, channel:string, target:string|null,
     *               masked:string|null, retry_after:int, expires_in:int, reason:string|null}
     */
    private function cooldownResult(string $channel, string $target, int $wait): array
    {
        return $this->result(
            false,
            trans_choice(
                '{1}Your code was just sent. Wait a second before asking for another.|[2,*]Your code was just sent. Wait :seconds seconds before asking for another.',
                $wait,
                ['seconds' => $wait]
            ),
            $channel,
            $target,
            $wait
        );
    }

    /**
     * @return array{sent:bool, message:string, channel:string, target:string|null,
     *               masked:string|null, retry_after:int, expires_in:int, reason:string|null}
     */
    private function result(
        bool $sent,
        string $message,
        string $channel,
        ?string $target = null,
        int $retryAfter = 0,
        ?string $reason = null
    ): array {
        return [
            'sent' => $sent,
            'message' => $message,
            'channel' => $channel,
            'target' => $target,
            'masked' => $channel === self::CHANNEL_EMAIL ? $this->maskEmail($target) : $this->mask($target),
            'retry_after' => $retryAfter,
            'expires_in' => $sent ? $this->ttlMinutes() * 60 : 0,
            'reason' => $reason,
        ];
    }
}
