<?php

namespace App\Services\Survey;

use App\Mail\AccountVerificationCode;
use App\Models\Survey\SurveyorPortalAccount;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The second step of signing in to the MLPP-SG portal (user, 2026-11-10): a one-time code
 * to the surveyor's registered mobile, or to their email when they ask for it.
 *
 *   1. username + password are checked WITHOUT signing in;
 *   2. a code goes to the contact on the account;
 *   3. the code (or, after 10 minutes, the security questions) is typed back and only
 *      then is the `surveyor` guard signed in.
 *
 * A copy of App\Services\Laas\LaasLoginOtpService on its own config keys
 * (phone_verification.surveyor_login_otp), session keys and cache keys: staff, LAAS and
 * portal sign-ins share one session cookie, and their account ids overlap. Nothing is
 * written to surveyor_portal_accounts; the reset_code columns belong to Forgot password.
 */
class SurveyorLoginOtpService
{
    /** The pending sign-in, in the guest's session. */
    public const SESSION_KEY = 'surveyor_login_otp.pending';

    /** Set once this session's sign-in has satisfied the policy. */
    public const PASSED_KEY = 'surveyor_login_otp.passed_account';

    /** Per-account code: hash, sent_at, channel, attempts. Shared by every session of the account. */
    public const CODE_CACHE = 'surveyor_login_otp.code.';

    /** Per-account timestamps of SMS sent in the last hour, for the hourly cap. */
    public const SMS_LOG_CACHE = 'surveyor_login_otp.sms.';

    public const CHANNEL_SMS = PhoneOtpService::CHANNEL_SMS;
    public const CHANNEL_EMAIL = PhoneOtpService::CHANNEL_EMAIL;

    public function __construct(
        private PhoneOtpService $phoneOtp,
        private BulkSmsNgService $gateway,
    ) {
    }

    private function config(string $key, $default = null)
    {
        return config('phone_verification.surveyor_login_otp.' . $key, $default);
    }

    /* ── policy ───────────────────────────────────────────────────────────── */

    public function enabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    /**
     * Yes, as long as there is somewhere to send a code. An account with neither a usable
     * mobile nor a real email signs in on its password rather than being locked out for
     * good; begin() is never reached for it, and AuthController logs it.
     */
    public function requiredFor(SurveyorPortalAccount $account): bool
    {
        return $this->enabled() && ($this->phoneFor($account) !== null || $this->emailFor($account) !== null);
    }

    public function phoneFor(SurveyorPortalAccount $account): ?string
    {
        return BulkSmsNgService::normalizeNumber((string) ($account->phone ?? ''));
    }

    public function emailFor(SurveyorPortalAccount $account): ?string
    {
        $email = $this->phoneOtp->normaliseEmail((string) ($account->email ?? ''));

        return ($email === null || $this->phoneOtp->isPlaceholderEmail($email)) ? null : $email;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) $this->config('ttl_minutes', 10));
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
     * Hold a sign-in for this account and make sure a code is on its way.
     *
     * @return array{sent:bool, message:string}
     */
    public function begin(Request $request, SurveyorPortalAccount $account, bool $remember): array
    {
        // A fresh session id: whoever held the old one does not inherit the half-finished sign-in.
        $request->session()->regenerate();
        $request->session()->forget(self::PASSED_KEY);
        $request->session()->put(self::SESSION_KEY, [
            'account_id' => $account->id,
            'remember' => $remember,
            'started_at' => now()->timestamp,
        ]);

        $live = $this->liveCode($account);
        $reuseFor = max(0, (int) $this->config('reuse_minutes', 5)) * 60;

        if ($live && now()->timestamp - $live['sent_at'] < $reuseFor) {
            $where = $live['channel'] === self::CHANNEL_EMAIL
                ? $this->phoneOtp->maskEmail($this->emailFor($account))
                : $this->phoneOtp->mask($this->phoneFor($account));

            return ['sent' => true, 'message' => "We sent a code to {$where} a moment ago. Enter that code, or ask for a new one below."];
        }

        return $this->send($request, $this->firstChannel($account));
    }

    /** SMS normally; email when there is no usable mobile, when configured, or in the quiet hours. */
    public function firstChannel(SurveyorPortalAccount $account): string
    {
        if ($this->phoneFor($account) === null) {
            return self::CHANNEL_EMAIL;
        }

        if ($this->emailFor($account) !== null
            && (strtolower((string) $this->config('channel', 'sms')) === self::CHANNEL_EMAIL
                || ($this->config('quiet_hours_email_first', true) && $this->inQuietHours()))) {
            return self::CHANNEL_EMAIL;
        }

        return self::CHANNEL_SMS;
    }

    /** The networks hold SMS 19:45-08:00 Kano time; app.timezone is UTC, so read the office clock. */
    public function inQuietHours(): bool
    {
        $hhmm = (int) now(config('phone_verification.timezone', 'Africa/Lagos'))->format('Hi');

        return $hhmm >= 1945 || $hhmm < 800;
    }

    /** @return array{hash:?string, sent_at:int, channel:string, attempts:int}|null */
    public function liveCode(SurveyorPortalAccount $account): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $account->id);

        if (!is_array($code) || empty($code['hash']) || now()->timestamp - (int) $code['sent_at'] > $this->ttlMinutes() * 60) {
            return null;
        }

        return $code;
    }

    /** When the account was last sent a code and by which route, even if that code is spent. */
    public function lastSend(SurveyorPortalAccount $account): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $account->id);

        return is_array($code) && !empty($code['sent_at']) ? $code : null;
    }

    private function storeCode(SurveyorPortalAccount $account, ?string $hash, string $channel, int $attempts = 0, ?int $sentAt = null): void
    {
        Cache::put(self::CODE_CACHE . $account->id, [
            'hash' => $hash,
            'sent_at' => $sentAt ?? now()->timestamp,
            'channel' => $channel,
            'attempts' => $attempts,
        ], now()->addMinutes($this->ttlMinutes())->addSeconds($this->cooldownSeconds()));
    }

    private function recentSms(SurveyorPortalAccount $account): array
    {
        return array_values(array_filter((array) Cache::get(self::SMS_LOG_CACHE . $account->id, []), fn ($t) => $t > now()->timestamp - 3600));
    }

    private function recordSms(SurveyorPortalAccount $account): void
    {
        $stamps = $this->recentSms($account);
        $stamps[] = now()->timestamp;
        Cache::put(self::SMS_LOG_CACHE . $account->id, $stamps, now()->addHour());
    }

    /** The account behind the pending sign-in, or null when there is none, it ran out, or the account is no longer active. */
    public function pendingAccount(Request $request): ?SurveyorPortalAccount
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (!is_array($pending) || empty($pending['account_id'])) {
            return null;
        }

        $limit = max(1, (int) $this->config('challenge_minutes', 30)) * 60;
        if (now()->timestamp - (int) ($pending['started_at'] ?? 0) > $limit) {
            $this->clear($request);
            return null;
        }

        $account = SurveyorPortalAccount::find($pending['account_id']);
        if (!$account || !$account->isActive()) {
            $this->clear($request);
            return null;
        }

        return $account;
    }

    public function pending(Request $request): array
    {
        return (array) $request->session()->get(self::SESSION_KEY, []);
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /** Seconds before another code may go by $channel. Switching route is always allowed at once. */
    public function cooldownRemaining(Request $request, ?string $channel = null): int
    {
        $account = $this->pendingAccount($request);
        $last = $account ? $this->lastSend($account) : null;

        if (!$last || ($channel !== null && $channel !== $last['channel'])) {
            return 0;
        }

        return max(0, $this->cooldownSeconds() - (now()->timestamp - (int) $last['sent_at']));
    }

    /* ── sending ──────────────────────────────────────────────────────────── */

    /** @return array{sent:bool, message:string} */
    public function send(Request $request, string $channel): array
    {
        $account = $this->pendingAccount($request);
        if (!$account) {
            return ['sent' => false, 'message' => 'Your sign-in has expired. Enter your username and password again.'];
        }

        $channel = $channel === self::CHANNEL_EMAIL ? self::CHANNEL_EMAIL : self::CHANNEL_SMS;

        $wait = $this->cooldownRemaining($request, $channel);
        if ($wait > 0) {
            return ['sent' => false, 'message' => "Wait {$wait} seconds before asking for another code."];
        }

        if ($channel === self::CHANNEL_SMS && count($this->recentSms($account)) >= max(1, (int) $this->config('max_sms_per_hour', 6))) {
            return ['sent' => false, 'message' => $this->emailFor($account)
                ? 'Too many codes have been texted to this account in the last hour. Use "Email it to me instead", or try again later.'
                : "Too many codes have been texted to this account in the last hour. Please try again later, or contact the Surveyor General's office."];
        }

        $code = $this->phoneOtp->generateCode();

        // Stored before the send and kept if it fails: the gateway may have accepted it anyway,
        // and the cooldown must apply either way.
        $this->storeCode($account, $this->hash($code, $account), $channel);
        if ($channel === self::CHANNEL_SMS) {
            $this->recordSms($account);
        }

        return $channel === self::CHANNEL_EMAIL
            ? $this->sendByEmail($request, $account, $code)
            : $this->sendBySms($account, $code);
    }

    private function sendBySms(SurveyorPortalAccount $account, string $code): array
    {
        $phone = $this->phoneFor($account);
        if ($phone === null) {
            return ['sent' => false, 'message' => "The mobile number on your account cannot receive a code. Use your email address instead, or contact the Surveyor General's office."];
        }

        $accepted = $this->gateway->sendFirstAccepted($phone, $this->messagesFor($code), $this->sender());
        $status = $this->gateway->lastStatusCode();
        $reason = $this->gateway->lastFailureReason();

        if ($accepted === null) {
            Log::warning('SurveyorLoginOtpService: sign-in code not delivered by SMS', [
                'account_id' => $account->id, 'gateway_code' => $status, 'reason' => $reason,
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainFailure($status, $reason)];
        }

        return ['sent' => true, 'message' => 'A sign-in code has been sent to ' . $this->phoneOtp->mask($phone)
            . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    private function sendByEmail(Request $request, SurveyorPortalAccount $account, string $code): array
    {
        $email = $this->emailFor($account);
        if ($email === null) {
            return ['sent' => false, 'message' => "There is no usable email address on your account. Use your mobile number, or contact the Surveyor General's office."];
        }

        try {
            // ->send(), never ->queue(): see AccountVerificationCode.
            Mail::to($email)->send(new AccountVerificationCode(
                code: $code,
                recipientName: (string) ($account->name ?: 'there'),
                expiresInMinutes: $this->ttlMinutes(),
                requestedFrom: $request->ip(),
                subjectLine: (string) $this->config('email_subject', 'Your MLPP-SG Portal sign-in code'),
            ));
        } catch (\Throwable $e) {
            Log::warning('SurveyorLoginOtpService: sign-in code not delivered by email', [
                'account_id' => $account->id, 'message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainEmailFailure($e)];
        }

        return ['sent' => true, 'message' => 'A sign-in code has been sent to ' . $this->phoneOtp->maskEmail($email)
            . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    /** The staff sign-in code's sender unless overridden: an unregistered sender ID is billed and never delivered. */
    private function sender(): ?string
    {
        if (!empty($configured = $this->config('sender'))) {
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
            strtr((string) $this->config('message'), $tokens),
            strtr((string) $this->config('message_fallback'), $tokens),
        ]));
    }

    /* ── verifying ────────────────────────────────────────────────────────── */

    /** @return array{verified:bool, message:string, account:SurveyorPortalAccount|null, remember:bool} */
    public function verify(Request $request, string $code): array
    {
        $fail = fn (string $message) => ['verified' => false, 'message' => $message, 'account' => null, 'remember' => false];

        $account = $this->pendingAccount($request);
        if (!$account) {
            return $fail('Your sign-in has expired. Enter your username and password again.');
        }

        $pending = $this->pending($request);
        $last = $this->lastSend($account);

        if (!$last || empty($last['hash'])) {
            return $fail('No code is waiting. Ask for a new one.');
        }

        if (now()->timestamp - (int) $last['sent_at'] > $this->ttlMinutes() * 60) {
            return $fail('That code has expired. Ask for a new one.');
        }

        $given = preg_replace('/\D+/', '', $code) ?? '';

        if (!hash_equals((string) $last['hash'], $this->hash($given, $account))) {
            // Counted per account, so another tab buys no guesses.
            $attempts = (int) ($last['attempts'] ?? 0) + 1;
            $left = $this->maxAttempts() - $attempts;

            if ($left <= 0) {
                // Burn the code, not the account. The send time stays, so the resend cooldown still applies.
                $this->storeCode($account, null, $last['channel'], $attempts, (int) $last['sent_at']);

                return $fail('Too many wrong codes. Ask for a new one.');
            }

            $this->storeCode($account, $last['hash'], $last['channel'], $attempts, (int) $last['sent_at']);

            return $fail($left === 1
                ? 'That code is not correct. One more attempt before you must ask for a new code.'
                : "That code is not correct. {$left} attempts left.");
        }

        return $this->passed($request, $account);
    }

    /**
     * The pending sign-in is proven (code typed, or the security questions answered): end it and
     * spend the code, so a later attempt must get a new one.
     *
     * @return array{verified:bool, message:string, account:SurveyorPortalAccount, remember:bool}
     */
    public function passed(Request $request, SurveyorPortalAccount $account): array
    {
        $remember = (bool) ($this->pending($request)['remember'] ?? false);
        $this->clear($request);
        Cache::forget(self::CODE_CACHE . $account->id);

        return ['verified' => true, 'message' => '', 'account' => $account, 'remember' => $remember];
    }

    /** Keyed to the app key and the account, so a hash lifted from the cache is useless anywhere else. */
    private function hash(string $code, SurveyorPortalAccount $account): string
    {
        return hash_hmac('sha256', 'mlppsg|' . $account->id . '|' . $code, (string) config('app.key'));
    }

    /* ── the session's standing ───────────────────────────────────────────── */

    public function markPassed(Request $request, SurveyorPortalAccount $account): void
    {
        $request->session()->put(self::PASSED_KEY, $account->id);
    }

    public function hasPassed(Request $request, SurveyorPortalAccount $account): bool
    {
        return (string) $request->session()->get(self::PASSED_KEY) === (string) $account->id;
    }
}
