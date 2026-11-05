<?php

namespace App\Services;

use App\Mail\AccountVerificationCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The second step of signing in: a one-time code to the account's confirmed mobile number.
 *
 *   1. username + password are checked WITHOUT signing in (what the user knows);
 *   2. a code goes to the handset the account has proved it owns (what the user has);
 *   3. the code is typed back and only then is the session signed in.
 *
 * Between 1 and 3 the browser is a guest holding a pending sign-in in its session: the
 * user id, the code as an HMAC (never the code itself), when it was sent, and how many
 * wrong tries it has taken. Nothing is written to the users row, so this step can never
 * disturb the account-setup code or its is_otp_verified flag (PhoneOtpService).
 *
 * Which accounts: only those whose mobile number is confirmed — see config
 * phone_verification.login_otp for why. The SMS gateway, its wording fallback, the
 * dispatch log and the failure explanations are PhoneOtpService's, reused as they are.
 */
class LoginOtpService
{
    public const SESSION_KEY = 'login_otp.pending';

    /** Set once this session's sign-in has satisfied the policy (code typed, or none needed). */
    public const PASSED_KEY = 'login_otp.passed_user';

    /** Per-account code: hash, sent_at, channel, attempts. Shared by every session of the account. */
    public const CODE_CACHE = 'login_otp.code.';

    /** Per-account timestamps of SMS sent in the last hour, for the hourly cap. */
    public const SMS_LOG_CACHE = 'login_otp.sms.';

    public const CHANNEL_SMS = PhoneOtpService::CHANNEL_SMS;
    public const CHANNEL_EMAIL = PhoneOtpService::CHANNEL_EMAIL;

    /** A code an administrator generated on the Users screen and read to the officer. */
    public const CHANNEL_ADMIN = 'admin';

    private const OTP_EXEMPT_USERNAMES = ['USMANSCB'];

    /**
     * Accounts that are exempt from the staff sign-in code policy when their
     * username is unavailable or has changed.  Keep addresses normalized so
     * this remains an exact, case-insensitive account match.
     */
    private const OTP_EXEMPT_EMAILS = [
        'katsoltech@gmail.com',
        'dict@gmail.com',
    ];

    /** The ICT Director's account is also exempt from the mandatory photo gate. */
    private const PROFILE_PHOTO_EXEMPT_EMAILS = ['dict@gmail.com'];

    public function __construct(
        private PhoneOtpService $phoneOtp,
        private BulkSmsNgService $gateway,
    ) {
    }

    /* ── policy ───────────────────────────────────────────────────────────── */

    public function enabled(): bool
    {
        return (bool) config('phone_verification.login_otp.enabled', true)
            && $this->phoneOtp->columnsReady();
    }

    /**
     * Must this account type a code to sign in?
     *
     * Yes when its mobile number has been confirmed by a code — the verification card
     * sent by SMS, or by SMS and email together — and that number is still one the
     * gateway can reach. Changing phone_number clears is_otp_verified (User model), so a
     * number nobody has proved never receives a sign-in code.
     */
    public function requiredFor(User $user): bool
    {
        if (self::isOtpExempt($user)) {
            return false;
        }

        if (!$this->enabled()) {
            return false;
        }

        if (!(bool) ($user->getAttribute('is_otp_verified') ?? false)) {
            return false;
        }

        $channel = $this->phoneOtp->recordedChannel($user);
        if (!in_array($channel, [PhoneOtpService::CHANNEL_SMS, PhoneOtpService::CHANNEL_BOTH], true)) {
            return false;
        }

        return $this->phoneFor($user) !== null;
    }

    public static function isOtpExempt(User $user): bool
    {
        return in_array((string) $user->username, self::OTP_EXEMPT_USERNAMES, true)
            || in_array(strtolower(trim((string) $user->email)), self::OTP_EXEMPT_EMAILS, true);
    }

    public static function isProfilePhotoExempt(User $user): bool
    {
        return in_array(
            strtolower(trim((string) $user->email)),
            self::PROFILE_PHOTO_EXEMPT_EMAILS,
            true,
        );
    }

    public function phoneFor(User $user): ?string
    {
        return BulkSmsNgService::normalizeNumber((string) ($user->phone_number ?? ''));
    }

    /** The backup address, or null when the account holds only a placeholder. */
    public function emailFor(User $user): ?string
    {
        $email = $this->phoneOtp->normaliseEmail((string) ($user->email ?? ''));

        return ($email === null || $this->phoneOtp->isPlaceholderEmail($email)) ? null : $email;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('phone_verification.login_otp.ttl_minutes', 10));
    }

    public function cooldownSeconds(): int
    {
        return $this->phoneOtp->cooldownSeconds();
    }

    /* ── the pending sign-in ──────────────────────────────────────────────── */

    /**
     * Hold a sign-in for this user and make sure a code is on its way.
     *
     * The code lives per ACCOUNT (cache), not per session. A second sign-in attempt,
     * another tab, or RequireLoginOtp bouncing a mobile login within a few minutes of
     * the last code reuses that code instead of texting again. Each of those used to
     * send a fresh SMS, and overnight they piled up at the gateway and arrived together
     * at 08:30 (2026-09-15).
     *
     * @return array{sent:bool, message:string}
     */
    public function begin(Request $request, User $user, bool $remember): array
    {
        // A fresh session id for the pending state: whoever held the old one before the
        // password was typed does not inherit the half-finished sign-in.
        $request->session()->regenerate();
        $request->session()->forget(self::PASSED_KEY);
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'started_at' => now()->timestamp,
        ]);

        $live = $this->liveCode($user);
        $reuseFor = max(0, (int) config('phone_verification.login_otp.reuse_minutes', 5)) * 60;

        // A code ICT generated for this account stays the code for its whole life. Texting
        // a fresh one here would silently void the code the officer is holding on paper.
        if ($live && $live['channel'] === self::CHANNEL_ADMIN) {
            return ['sent' => true, 'message' => __('ICT has issued a sign-in code for your account. Enter the code they gave you.')];
        }

        if ($live && now()->timestamp - $live['sent_at'] < $reuseFor) {
            $where = $live['channel'] === self::CHANNEL_EMAIL
                ? $this->phoneOtp->maskEmail($this->emailFor($user))
                : $this->phoneOtp->mask($this->phoneFor($user));

            return ['sent' => true, 'message' => __('A code was sent to :where a moment ago. Enter that code, or ask for a new one below.', ['where' => $where])];
        }

        return $this->send($request, $this->firstChannel($user));
    }

    /**
     * SMS normally. Between 19:45 and 08:00 the networks hold this traffic until about
     * 08:30, so the first code goes by email when the account has a real address.
     */
    public function firstChannel(User $user): string
    {
        // Email-only mode (LOGIN_OTP_CHANNEL=email). An account whose address is
        // blank or a placeholder still goes by SMS — otherwise it could not be
        // sent a code at all.
        if (strtolower((string) config('phone_verification.login_otp.channel', 'sms')) === self::CHANNEL_EMAIL
            && $this->emailFor($user) !== null) {
            return self::CHANNEL_EMAIL;
        }

        if (config('phone_verification.login_otp.quiet_hours_email_first', true)
            && $this->inQuietHours()
            && $this->emailFor($user) !== null) {
            return self::CHANNEL_EMAIL;
        }

        return self::CHANNEL_SMS;
    }

    public function inQuietHours(): bool
    {
        $hhmm = (int) now(config('phone_verification.timezone', 'Africa/Lagos'))->format('Hi');

        return $hhmm >= 1945 || $hhmm < 800;
    }

    /**
     * The account's current code, or null when none is live.
     *
     * @return array{hash:?string, sent_at:int, channel:string, attempts:int}|null
     */
    public function liveCode(User $user): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $user->id);

        if (!is_array($code) || empty($code['hash']) || now()->timestamp - (int) $code['sent_at'] > $this->ttlMinutes() * 60) {
            return null;
        }

        return $code;
    }

    /** When the account was last sent a code and by which route, even if that code is spent. */
    public function lastSend(User $user): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $user->id);

        return is_array($code) && !empty($code['sent_at']) ? $code : null;
    }

    private function storeCode(User $user, ?string $hash, string $channel, int $attempts = 0, ?int $sentAt = null): void
    {
        Cache::put(self::CODE_CACHE . $user->id, [
            'hash' => $hash,
            'sent_at' => $sentAt ?? now()->timestamp,
            'channel' => $channel,
            'attempts' => $attempts,
        ], now()->addMinutes($this->ttlMinutes())->addSeconds($this->cooldownSeconds()));
    }

    /** SMS sent to this account in the rolling hour; the cap protects the handset and the wallet. */
    private function smsSentThisHour(User $user): int
    {
        $stamps = array_filter((array) Cache::get(self::SMS_LOG_CACHE . $user->id, []), fn ($t) => $t > now()->timestamp - 3600);

        return count($stamps);
    }

    private function recordSms(User $user): void
    {
        $stamps = array_filter((array) Cache::get(self::SMS_LOG_CACHE . $user->id, []), fn ($t) => $t > now()->timestamp - 3600);
        $stamps[] = now()->timestamp;
        Cache::put(self::SMS_LOG_CACHE . $user->id, array_values($stamps), now()->addHour());
    }

    /**
     * The user behind the pending sign-in, or null when there is none or it has run out.
     */
    public function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (!is_array($pending) || empty($pending['user_id'])) {
            return null;
        }

        $limit = max(1, (int) config('phone_verification.login_otp.challenge_minutes', 30)) * 60;
        if (now()->timestamp - (int) ($pending['started_at'] ?? 0) > $limit) {
            $this->clear($request);
            return null;
        }

        $user = User::find($pending['user_id']);
        if (!$user || (string) $user->is_active === '0') {
            $this->clear($request);
            return null;
        }

        return $user;
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
     * Seconds before another code may be asked for by $channel (null: by the route last used).
     *
     * Switching route is always allowed at once: an SMS the network is holding until
     * morning is exactly when the user is told to use email instead.
     */
    public function cooldownRemaining(Request $request, ?string $channel = null): int
    {
        $user = $this->pendingUser($request);
        $last = $user ? $this->lastSend($user) : null;

        if (!$last || ($channel !== null && $channel !== $last['channel'])) {
            return 0;
        }

        return max(0, $this->cooldownSeconds() - (now()->timestamp - (int) $last['sent_at']));
    }

    /* ── sending ──────────────────────────────────────────────────────────── */

    /**
     * Send a new code for the pending sign-in, by SMS or to the backup email.
     *
     * @return array{sent:bool, message:string}
     */
    public function send(Request $request, string $channel): array
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            return ['sent' => false, 'message' => __('Your sign-in has expired. Enter your username and password again.')];
        }

        $wait = $this->cooldownRemaining($request, $channel);
        if ($wait > 0) {
            return ['sent' => false, 'message' => trans_choice(
                '{1}Wait a second before asking for another code.|[2,*]Wait :count seconds before asking for another code.',
                $wait,
                ['count' => $wait]
            )];
        }

        if ($channel === self::CHANNEL_SMS) {
            $cap = max(1, (int) config('phone_verification.login_otp.max_sms_per_hour', 6));

            if ($this->smsSentThisHour($user) >= $cap) {
                return ['sent' => false, 'message' => $this->emailFor($user)
                    ? __('Too many codes have been texted to this account in the last hour. Use "Email it to me instead", or try again later.')
                    : __('Too many codes have been texted to this account in the last hour. Try again later, or contact ICT.')];
            }
        }

        $code = $this->phoneOtp->generateCode();

        // Stored before the send and kept if it fails: the gateway may have accepted the
        // message whatever it answered, and the cooldown must apply either way.
        $this->storeCode($user, $this->hash($code, $user), $channel);

        if ($channel === self::CHANNEL_SMS) {
            $this->recordSms($user);
        }

        return $channel === self::CHANNEL_EMAIL
            ? $this->sendByEmail($request, $user, $code)
            : $this->sendBySms($user, $code);
    }

    private function sendBySms(User $user, string $code): array
    {
        $phone = $this->phoneFor($user);
        if ($phone === null) {
            return ['sent' => false, 'message' => __('The mobile number on your account cannot receive a code. Use your email address instead, or contact ICT.')];
        }

        $sender = null;
        try {
            $sender = \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP);
        } catch (\Throwable $e) {
            // Server default sender.
        }

        $accepted = $this->gateway->sendFirstAccepted($phone, $this->messagesFor($code), $sender);
        $status = $this->gateway->lastStatusCode();
        $reason = $this->gateway->lastFailureReason();

        $this->phoneOtp->logDispatch($user, $phone, $accepted !== null, $status, $accepted !== null ? null : $reason);

        if ($accepted === null) {
            Log::warning('LoginOtpService: sign-in code not delivered by SMS', [
                'user_id' => $user->id,
                'gateway_code' => $status,
                'reason' => $reason,
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainFailure($status, $reason)];
        }

        return ['sent' => true, 'message' => __('A sign-in code has been sent to :phone. It expires in :minutes minutes.', [
            'phone' => $this->phoneOtp->mask($phone),
            'minutes' => $this->ttlMinutes(),
        ])];
    }

    private function sendByEmail(Request $request, User $user, string $code): array
    {
        $email = $this->emailFor($user);
        if ($email === null) {
            return ['sent' => false, 'message' => __('There is no usable email address on your account. Use your mobile number, or contact ICT.')];
        }

        try {
            // ->send(), never ->queue(): see AccountVerificationCode.
            Mail::to($email)->send(new AccountVerificationCode(
                code: $code,
                recipientName: (string) ($user->first_name ?? $user->name ?? 'there'),
                expiresInMinutes: $this->ttlMinutes(),
                requestedFrom: $request->ip(),
            ));
        } catch (\Throwable $e) {
            Log::warning('LoginOtpService: sign-in code not delivered by email', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainEmailFailure($e)];
        }

        return ['sent' => true, 'message' => __('A sign-in code has been sent to :email. It expires in :minutes minutes.', [
            'email' => $this->phoneOtp->maskEmail($email),
            'minutes' => $this->ttlMinutes(),
        ])];
    }

    /**
     * "Generate on the fly": a sign-in code made on the Users screen and handed to the
     * officer by the administrator instead of by SMS or email — for when the network is
     * holding SMS overnight, the handset is out of coverage, or the mailbox is unreachable.
     *
     * It is stored exactly like a texted code (per account, as an HMAC, same expiry, same
     * wrong-try limit, single use), so the ordinary sign-in code screen accepts it. It
     * replaces any code already sent. It only completes a sign-in that has passed the
     * password, so it is no use to anybody who does not also know the password.
     *
     * Nothing is sent and the code is never logged; the caller shows it once.
     *
     * @return array{issued:bool, message:string, code:?string, expires_in:int}
     */
    public function issueByAdmin(User $user, User $admin): array
    {
        $refuse = fn (string $message) => ['issued' => false, 'message' => $message, 'code' => null, 'expires_in' => 0];

        if ((int) $user->id === (int) $admin->id) {
            return $refuse(__('You cannot generate a sign-in code for your own account.'));
        }

        if ((string) $user->is_active === '0') {
            return $refuse(__(':name is suspended. Reactivate the account first.', ['name' => $user->name]));
        }

        if ($user->isSuperAdmin() && !$admin->isSuperAdmin()) {
            return $refuse(__('Only a Super Admin may generate a sign-in code for a Super Admin account.'));
        }

        if (!$this->requiredFor($user)) {
            return $refuse($this->whyNoCode($user));
        }

        $code = $this->phoneOtp->generateCode();
        $this->storeCode($user, $this->hash($code, $user), self::CHANNEL_ADMIN);

        Log::warning('LoginOtpService: sign-in code generated by an administrator', [
            'admin_id' => $admin->id,
            'admin' => $admin->username,
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => request()?->ip(),
        ]);

        return [
            'issued' => true,
            'message' => __('Sign-in code for :name. It works once and expires in :minutes minutes.', [
                'name' => $user->name,
                'minutes' => $this->ttlMinutes(),
            ]),
            'code' => $code,
            'expires_in' => $this->ttlMinutes() * 60,
        ];
    }

    /** Why requiredFor() said no, in words an administrator can act on. */
    private function whyNoCode(User $user): string
    {
        if (self::isOtpExempt($user) || !$this->enabled()) {
            return __(':name signs in with a password only; no sign-in code is asked for.', ['name' => $user->name]);
        }

        if (!(bool) ($user->getAttribute('is_otp_verified') ?? false)) {
            return __(':name has not confirmed their contact details yet, so no sign-in code is asked for. They sign in with their password and are shown the account confirmation card.', ['name' => $user->name]);
        }

        if ($this->phoneFor($user) === null) {
            return __(':name has no usable mobile number, so no sign-in code is asked for.', ['name' => $user->name]);
        }

        if ($this->phoneOtp->recordedChannel($user) === PhoneOtpService::CHANNEL_EMAIL) {
            return __(':name confirmed their account by email only, so no sign-in code is asked for.', ['name' => $user->name]);
        }

        return __(':name signs in with a password only; their account confirmation did not record a mobile number, so no sign-in code is asked for.', ['name' => $user->name]);
    }

    /** @return array<int,string> */
    private function messagesFor(string $code): array
    {
        $tokens = ['{code}' => $code, '{minutes}' => (string) $this->ttlMinutes()];

        return array_values(array_filter([
            strtr((string) config('phone_verification.login_otp.message'), $tokens),
            strtr((string) config('phone_verification.login_otp.message_fallback'), $tokens),
        ]));
    }

    /* ── verifying ────────────────────────────────────────────────────────── */

    /**
     * Check a typed code against the pending sign-in.
     *
     * @return array{verified:bool, message:string, user:User|null, remember:bool}
     */
    public function verify(Request $request, string $code): array
    {
        $fail = fn (string $message) => ['verified' => false, 'message' => $message, 'user' => null, 'remember' => false];

        $user = $this->pendingUser($request);
        if (!$user) {
            return $fail(__('Your sign-in has expired. Enter your username and password again.'));
        }

        $pending = $this->pending($request);
        $last = $this->lastSend($user);

        if (!$last || empty($last['hash'])) {
            return $fail(__('No code is waiting. Ask for a new one.'));
        }

        if (now()->timestamp - (int) $last['sent_at'] > $this->ttlMinutes() * 60) {
            return $fail(__('That code has expired. Ask for a new one.'));
        }

        $given = preg_replace('/\D+/', '', $code) ?? '';

        if (!hash_equals((string) $last['hash'], $this->hash($given, $user))) {
            // Counted per account, so opening another tab does not buy more guesses.
            $attempts = (int) ($last['attempts'] ?? 0) + 1;
            $left = $this->phoneOtp->maxAttempts() - $attempts;

            if ($left <= 0) {
                // Burn the code, not the account: the user asks for another one. The send
                // time stays, so the resend cooldown still applies.
                $this->storeCode($user, null, $last['channel'], $attempts, (int) $last['sent_at']);

                return $fail(__('Too many wrong codes. Ask for a new one.'));
            }

            $this->storeCode($user, $last['hash'], $last['channel'], $attempts, (int) $last['sent_at']);

            return $fail(trans_choice(
                '{1}That code is not correct. One more attempt before you must ask for a new code.|[2,*]That code is not correct. :count attempts left.',
                $left,
                ['count' => $left]
            ));
        }

        $this->clear($request);
        // Single use: the code opens one sign-in, and a later attempt must get a new one.
        Cache::forget(self::CODE_CACHE . $user->id);

        return ['verified' => true, 'message' => '', 'user' => $user, 'remember' => (bool) ($pending['remember'] ?? false)];
    }

    /**
     * Keyed to the app key and the account, so a hash lifted from one session store is
     * useless against another account or another deployment.
     */
    private function hash(string $code, User $user): string
    {
        return hash_hmac('sha256', $user->id . '|' . $code, (string) config('app.key'));
    }

    /* ── the session's standing ───────────────────────────────────────────── */

    public function markPassed(Request $request, User $user): void
    {
        $request->session()->put(self::PASSED_KEY, $user->id);
    }

    public function hasPassed(Request $request, User $user): bool
    {
        return (string) $request->session()->get(self::PASSED_KEY) === (string) $user->id;
    }
}
