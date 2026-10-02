<?php

namespace App\Services\Phs;

use App\Mail\AccountVerificationCode;
use App\Models\Phs\PhsMember;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The second step of signing in to the PHS Portal: a one-time code to the
 * member's email address.
 *
 *   1. username + password are checked WITHOUT signing in (what they know);
 *   2. a code goes to the address on the member row (what they have);
 *   3. the code is typed back and only then is the session signed in.
 *
 * Between 1 and 3 the browser is a guest holding a pending sign-in in its
 * session: the member id, whether they ticked "remember me", and when it
 * started. The code itself is NOT in the session — it lives in the cache as an
 * HMAC, keyed to the member.
 *
 * EMAIL LEADS, with SMS as the second route once the member has a PROVED mobile
 * number (PhsPhoneSetupService). Email first because these are office users with
 * the mailbox already open, and because the only SMS route this gateway account
 * has is the promotional one, which the telcos hold overnight and never deliver
 * to a DND-blocked handset. A member who has not confirmed a number gets email
 * regardless — there is nothing else to use, and an UNPROVED number in the
 * column is exactly what must never be texted a sign-in code.
 *
 * NOTHING IS WRITTEN TO phs_members, so this needs no migration and can be
 * switched off with one env key.
 *
 * A PHS account is reached by INSTITUTION username and resolves to that
 * institution's super_admin member (PhsAuthController::login). The pending
 * sign-in therefore holds the member id, not the username — a username is a
 * way in, and the thing being proved is the person behind one account.
 */
class PhsLoginOtpService
{
    /** The pending sign-in, in the guest's session. */
    public const SESSION_KEY = 'phs_login_otp.pending';

    /** Set once this session's sign-in has satisfied the policy. */
    public const PASSED_KEY = 'phs_login_otp.passed_member';

    /** Per-member code: hash, sent_at, channel, attempts. Shared by every session of the account. */
    public const CODE_CACHE = 'phs_login_otp.code.';

    /** Per-member timestamps of SMS sent in the last hour, for the hourly cap. */
    public const SMS_LOG_CACHE = 'phs_login_otp.sms.';

    public const CHANNEL_EMAIL = PhoneOtpService::CHANNEL_EMAIL;
    public const CHANNEL_SMS = PhoneOtpService::CHANNEL_SMS;

    public function __construct(
        private PhoneOtpService $phoneOtp,
        private BulkSmsNgService $gateway,
    ) {
    }

    /* ── policy ───────────────────────────────────────────────────────────── */

    public function enabled(): bool
    {
        return (bool) config('phone_verification.phs_login_otp.enabled', true);
    }

    /**
     * Must this member type a code to sign in?
     *
     * Only when there is somewhere to send one. A member with neither a usable
     * address nor a proved mobile is let through on their password alone,
     * because the alternative is an institution locked out of a service it pays
     * for. In practice the phone card (RequirePhsPhone) means that case is a
     * placeholder email and no confirmed number.
     */
    public function requiredFor(PhsMember $member): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return $this->emailFor($member) !== null || $this->phoneFor($member) !== null;
    }

    /**
     * The mobile the gateway can reach, or null.
     *
     * ONLY a number the member has confirmed by code. An unproved number is one
     * somebody typed — possibly an administrator adding a colleague, possibly a
     * typo — and texting a sign-in code to it would be sending the key to the
     * account to whoever happens to hold that handset.
     */
    public function phoneFor(PhsMember $member): ?string
    {
        if (!$member->hasVerifiedPhone()) {
            return null;
        }

        return BulkSmsNgService::normalizeNumber((string) $member->phone);
    }

    /** The address, or null when the row holds only a placeholder. */
    public function emailFor(PhsMember $member): ?string
    {
        $email = $this->phoneOtp->normaliseEmail((string) ($member->email ?? ''));

        return ($email === null || $this->phoneOtp->isPlaceholderEmail($email)) ? null : $email;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('phone_verification.phs_login_otp.ttl_minutes', 10));
    }

    public function cooldownSeconds(): int
    {
        return $this->phoneOtp->cooldownSeconds();
    }

    public function maxAttempts(): int
    {
        return $this->phoneOtp->maxAttempts();
    }

    /* ── the pending sign-in ──────────────────────────────────────────────── */

    /**
     * Hold a sign-in for this member and make sure a code is on its way.
     *
     * @return array{sent:bool, message:string}
     */
    public function begin(Request $request, PhsMember $member, bool $remember): array
    {
        // A fresh session id for the pending state: whoever held the old one
        // before the password was typed does not inherit the half-finished
        // sign-in.
        $request->session()->regenerate();
        $request->session()->forget(self::PASSED_KEY);
        $request->session()->put(self::SESSION_KEY, [
            'member_id' => $member->id,
            'remember' => $remember,
            'started_at' => now()->timestamp,
        ]);

        $live = $this->liveCode($member);
        $reuseFor = max(0, (int) config('phone_verification.phs_login_otp.reuse_minutes', 5)) * 60;

        if ($live && now()->timestamp - $live['sent_at'] < $reuseFor) {
            $where = ($live['channel'] ?? self::CHANNEL_EMAIL) === self::CHANNEL_SMS
                ? $this->phoneOtp->mask($this->phoneFor($member))
                : $this->phoneOtp->maskEmail($this->emailFor($member));

            return [
                'sent' => true,
                'message' => "A code was sent to {$where} a moment ago. Enter that code, or ask for a new one below.",
            ];
        }

        return $this->send($request, $this->firstChannel($member));
    }

    /**
     * The route the first code takes: email unless configured otherwise, and
     * always email when there is no proved mobile to text.
     */
    public function firstChannel(PhsMember $member): string
    {
        if ($this->phoneFor($member) === null) {
            return self::CHANNEL_EMAIL;
        }

        if ($this->emailFor($member) === null) {
            return self::CHANNEL_SMS;
        }

        return strtolower((string) config('phone_verification.phs_login_otp.channel', 'email')) === self::CHANNEL_SMS
            ? self::CHANNEL_SMS
            : self::CHANNEL_EMAIL;
    }

    /**
     * config('app.timezone') is UTC on this deployment while Kano is WAT, so the
     * office clock has to be read explicitly. Used only to warn on the code
     * screen that a text sent now will not arrive until the morning.
     */
    public function inQuietHours(): bool
    {
        $hhmm = (int) now(config('phone_verification.timezone', 'Africa/Lagos'))->format('Hi');

        return $hhmm >= 1945 || $hhmm < 800;
    }

    /**
     * The member's current code, or null when none is live.
     *
     * @return array{hash:?string, sent_at:int, attempts:int}|null
     */
    public function liveCode(PhsMember $member): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $member->id);

        if (!is_array($code) || empty($code['hash']) || now()->timestamp - (int) $code['sent_at'] > $this->ttlMinutes() * 60) {
            return null;
        }

        return $code;
    }

    /** When the member was last sent a code, even if that code is spent. */
    public function lastSend(PhsMember $member): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $member->id);

        return is_array($code) && !empty($code['sent_at']) ? $code : null;
    }

    private function storeCode(PhsMember $member, ?string $hash, string $channel = self::CHANNEL_EMAIL, int $attempts = 0, ?int $sentAt = null): void
    {
        Cache::put(self::CODE_CACHE . $member->id, [
            'hash' => $hash,
            'sent_at' => $sentAt ?? now()->timestamp,
            'channel' => $channel,
            'attempts' => $attempts,
        ], now()->addMinutes($this->ttlMinutes())->addSeconds($this->cooldownSeconds()));
    }

    private function smsSentThisHour(PhsMember $member): int
    {
        $stamps = array_filter((array) Cache::get(self::SMS_LOG_CACHE . $member->id, []), fn ($t) => $t > now()->timestamp - 3600);

        return count($stamps);
    }

    private function recordSms(PhsMember $member): void
    {
        $stamps = array_filter((array) Cache::get(self::SMS_LOG_CACHE . $member->id, []), fn ($t) => $t > now()->timestamp - 3600);
        $stamps[] = now()->timestamp;
        Cache::put(self::SMS_LOG_CACHE . $member->id, array_values($stamps), now()->addHour());
    }

    /**
     * The member behind the pending sign-in, or null when there is none or it
     * has run out.
     *
     * A suspended member, or one whose INSTITUTION has been suspended, loses the
     * pending sign-in here — the same two checks PhsAuthController::login makes
     * before the password is accepted, repeated so a suspension that lands
     * mid-flow takes effect rather than being waved through by a code.
     */
    public function pendingMember(Request $request): ?PhsMember
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (!is_array($pending) || empty($pending['member_id'])) {
            return null;
        }

        $limit = max(1, (int) config('phone_verification.phs_login_otp.challenge_minutes', 30)) * 60;
        if (now()->timestamp - (int) ($pending['started_at'] ?? 0) > $limit) {
            $this->clear($request);

            return null;
        }

        $member = PhsMember::find($pending['member_id']);

        if (!$member || !$member->isActive() || !$member->institution || !$member->institution->isActive()) {
            $this->clear($request);

            return null;
        }

        return $member;
    }

    public function pending(Request $request): array
    {
        return (array) $request->session()->get(self::SESSION_KEY, []);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Seconds before another code may be asked for by $channel (null: by the
     * route last used).
     *
     * Switching route is allowed at once: an SMS the network is holding until
     * morning is exactly when the member is told to use email instead.
     */
    public function cooldownRemaining(Request $request, ?string $channel = null): int
    {
        $member = $this->pendingMember($request);
        $last = $member ? $this->lastSend($member) : null;

        if (!$last || ($channel !== null && $channel !== ($last['channel'] ?? self::CHANNEL_EMAIL))) {
            return 0;
        }

        return max(0, $this->cooldownSeconds() - (now()->timestamp - (int) $last['sent_at']));
    }

    /* ── sending ──────────────────────────────────────────────────────────── */

    /**
     * Send a new code for the pending sign-in.
     *
     * @return array{sent:bool, message:string}
     */
    public function send(Request $request, ?string $channel = null): array
    {
        $member = $this->pendingMember($request);
        if (!$member) {
            return ['sent' => false, 'message' => 'Your sign-in has expired. Enter your username and password again.'];
        }

        $channel = $channel === self::CHANNEL_SMS ? self::CHANNEL_SMS : self::CHANNEL_EMAIL;

        // Asking for SMS on an account with no proved number falls back rather
        // than refusing: the member cannot tell from the screen why a route they
        // were offered would not work, and email always can.
        if ($channel === self::CHANNEL_SMS && $this->phoneFor($member) === null) {
            $channel = self::CHANNEL_EMAIL;
        }

        $wait = $this->cooldownRemaining($request, $channel);
        if ($wait > 0) {
            return ['sent' => false, 'message' => "Wait {$wait} seconds before asking for another code."];
        }

        if ($channel === self::CHANNEL_SMS) {
            $cap = max(1, (int) config('phone_verification.phs_login_otp.max_sms_per_hour', 6));

            if ($this->smsSentThisHour($member) >= $cap) {
                return ['sent' => false, 'message' => 'Too many codes have been texted to this account in the last hour. Use the email option, or try again later.'];
            }
        }

        $code = $this->phoneOtp->generateCode();

        // Stored before the send and kept if it fails: the mailer or the gateway
        // may have handed the message off whatever it then answered, and the
        // cooldown must apply either way.
        $this->storeCode($member, $this->hash($code, $member), $channel);

        if ($channel === self::CHANNEL_SMS) {
            $this->recordSms($member);

            return $this->sendBySms($member, $code);
        }

        return $this->sendByEmail($request, $member, $code);
    }

    private function sendByEmail(Request $request, PhsMember $member, string $code): array
    {
        $email = $this->emailFor($member);
        if ($email === null) {
            return ['sent' => false, 'message' => 'There is no usable email address on your account. Please contact KLAES support.'];
        }

        try {
            // ->send(), never ->queue(): see AccountVerificationCode.
            Mail::to($email)->send(new AccountVerificationCode(
                code: $code,
                recipientName: (string) ($member->name ?: 'there'),
                expiresInMinutes: $this->ttlMinutes(),
                requestedFrom: $request->ip(),
                subjectLine: (string) config('phone_verification.phs_login_otp.email_subject', 'Your PHS Portal sign-in code'),
            ));
        } catch (\Throwable $e) {
            Log::warning('PhsLoginOtpService: sign-in code not delivered by email', [
                'member_id' => $member->id,
                'institution_id' => $member->phs_institution_id,
                'message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainEmailFailure($e)];
        }

        return ['sent' => true, 'message' => 'A sign-in code has been sent to ' . $this->phoneOtp->maskEmail($email)
            . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    private function sendBySms(PhsMember $member, string $code): array
    {
        $phone = $this->phoneFor($member);

        $accepted = $this->gateway->sendFirstAccepted($phone, $this->messagesFor($code), $this->sender());
        $status = $this->gateway->lastStatusCode();
        $reason = $this->gateway->lastFailureReason();

        if ($accepted === null) {
            Log::warning('PhsLoginOtpService: sign-in code not delivered by SMS', [
                'member_id' => $member->id,
                'gateway_code' => $status,
                'reason' => $reason,
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainFailure($status, $reason)];
        }

        return ['sent' => true, 'message' => 'A sign-in code has been sent to ' . $this->phoneOtp->mask($phone)
            . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    /**
     * The name the text arrives under. Follows the staff sign-in code rather
     * than the server default; see LaasLoginOtpService::sender() for the night
     * that cost.
     */
    private function sender(): ?string
    {
        $configured = config('phone_verification.phs_login_otp.sender');
        if (!empty($configured)) {
            return (string) $configured;
        }

        try {
            return \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @return array<int,string> */
    private function messagesFor(string $code): array
    {
        $tokens = ['{code}' => $code, '{minutes}' => (string) $this->ttlMinutes()];

        return array_values(array_filter([
            strtr((string) config('phone_verification.phs_login_otp.message'), $tokens),
            strtr((string) config('phone_verification.phs_login_otp.message_fallback'), $tokens),
        ]));
    }

    /* ── verifying ────────────────────────────────────────────────────────── */

    /**
     * Check a typed code against the pending sign-in.
     *
     * @return array{verified:bool, message:string, member:PhsMember|null, remember:bool}
     */
    public function verify(Request $request, string $code): array
    {
        $fail = fn (string $message) => ['verified' => false, 'message' => $message, 'member' => null, 'remember' => false];

        $member = $this->pendingMember($request);
        if (!$member) {
            return $fail('Your sign-in has expired. Enter your username and password again.');
        }

        $pending = $this->pending($request);
        $last = $this->lastSend($member);

        if (!$last || empty($last['hash'])) {
            return $fail('No code is waiting. Ask for a new one.');
        }

        if (now()->timestamp - (int) $last['sent_at'] > $this->ttlMinutes() * 60) {
            return $fail('That code has expired. Ask for a new one.');
        }

        $given = preg_replace('/\D+/', '', $code) ?? '';

        if (!hash_equals((string) $last['hash'], $this->hash($given, $member))) {
            // Counted per member, so opening another tab does not buy more guesses.
            $attempts = (int) ($last['attempts'] ?? 0) + 1;
            $left = $this->maxAttempts() - $attempts;

            if ($left <= 0) {
                // Burn the code, not the account: the member asks for another
                // one. The send time stays, so the resend cooldown still applies.
                $this->storeCode($member, null, $last['channel'] ?? self::CHANNEL_EMAIL, $attempts, (int) $last['sent_at']);

                return $fail('Too many wrong codes. Ask for a new one.');
            }

            $this->storeCode($member, $last['hash'], $last['channel'] ?? self::CHANNEL_EMAIL, $attempts, (int) $last['sent_at']);

            return $fail($left === 1
                ? 'That code is not correct. One more attempt before you must ask for a new code.'
                : "That code is not correct. {$left} attempts left.");
        }

        $this->clear($request);
        // Single use: the code opens one sign-in, and a later attempt must get a new one.
        Cache::forget(self::CODE_CACHE . $member->id);

        return [
            'verified' => true,
            'message' => '',
            'member' => $member,
            'remember' => (bool) ($pending['remember'] ?? false),
        ];
    }

    /**
     * Keyed to the app key and the member, so a hash lifted from one session
     * store is useless against another account or another deployment.
     */
    private function hash(string $code, PhsMember $member): string
    {
        return hash_hmac('sha256', 'phs|' . $member->id . '|' . $code, (string) config('app.key'));
    }

    /* ── the session's standing ───────────────────────────────────────────── */

    public function markPassed(Request $request, PhsMember $member): void
    {
        $request->session()->put(self::PASSED_KEY, $member->id);
    }

    public function hasPassed(Request $request, PhsMember $member): bool
    {
        return (string) $request->session()->get(self::PASSED_KEY) === (string) $member->id;
    }
}
