<?php

namespace App\Services\Laas;

use App\Mail\AccountVerificationCode;
use App\Models\Laas\LaasApplicant;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * The second step of signing in to the LAAS Portal: a one-time code to the
 * applicant's mobile number, or to their email when they ask for it.
 *
 *   1. phone/email + password are checked WITHOUT signing in (what they know);
 *   2. a code goes to the contact on the account (what they have);
 *   3. the code is typed back and only then is the session signed in.
 *
 * Between 1 and 3 the browser is a guest holding a pending sign-in in its
 * session: the applicant id, which portal they came in through, whether they
 * ticked "keep me signed in", and when it started. The code itself is NOT in
 * the session — it lives in the cache as an HMAC, keyed to the account.
 *
 * NOTHING IS WRITTEN TO laas_applicants. That is deliberate and it is the whole
 * reason this does not reuse the verification_code columns already on the row:
 * those belong to the phone-CHANGE flow (LaasProfileController), and an
 * applicant who starts a phone change, then opens a second tab and signs in,
 * must not have the change quietly cancelled by the sign-in overwriting its
 * code. Two different questions, two different stores. It also means this whole
 * feature needs no migration and can be switched off with one env key.
 *
 * Staff have their own copy of this shape in App\Services\LoginOtpService. The
 * two are deliberately NOT merged: that one is typed to App\Models\User, reads
 * users.is_otp_verified and writes the staff dispatch log, none of which exists
 * for an applicant. What IS shared is every part that does not care who the
 * account belongs to — the gateway, the mailable, and PhoneOtpService's pure
 * helpers for masking, code generation and turning a gateway status into a
 * sentence a human can act on.
 */
class LaasLoginOtpService
{
    /** The pending sign-in, in the guest's session. */
    public const SESSION_KEY = 'laas_login_otp.pending';

    /** Set once this session's sign-in has satisfied the policy. */
    public const PASSED_KEY = 'laas_login_otp.passed_applicant';

    /** Per-account code: hash, sent_at, channel, attempts. Shared by every session of the account. */
    public const CODE_CACHE = 'laas_login_otp.code.';

    /** Per-account timestamps of SMS sent in the last hour, for the hourly cap. */
    public const SMS_LOG_CACHE = 'laas_login_otp.sms.';

    public const CHANNEL_SMS = PhoneOtpService::CHANNEL_SMS;
    public const CHANNEL_EMAIL = PhoneOtpService::CHANNEL_EMAIL;

    /** Which front door the sign-in started at; decides where it lands. */
    public const PORTAL_LAAS = 'laas';
    public const PORTAL_ALAES = 'alaes';

    public function __construct(
        private PhoneOtpService $phoneOtp,
        private BulkSmsNgService $gateway,
    ) {
    }

    /* ── policy ───────────────────────────────────────────────────────────── */

    public function enabled(): bool
    {
        return (bool) config('phone_verification.laas_login_otp.enabled', true);
    }

    /**
     * Must this applicant type a code to sign in?
     *
     * Yes, as long as there is somewhere to send one. An applicant whose mobile
     * the gateway cannot parse AND whose email is blank or a seeded placeholder
     * is let through on their password alone, because the alternative is an
     * account that can never be signed into again — and unlike a member of
     * staff, an applicant cannot walk to ICT and have it sorted out. That case
     * is logged rather than passed over in silence; see begin().
     */
    public function requiredFor(LaasApplicant $applicant): bool
    {
        if (!$this->enabled()) {
            return false;
        }

        return $this->phoneFor($applicant) !== null || $this->emailFor($applicant) !== null;
    }

    /** The mobile in the form the gateway wants, or null when it cannot be reached. */
    public function phoneFor(LaasApplicant $applicant): ?string
    {
        return BulkSmsNgService::normalizeNumber((string) ($applicant->phone ?? ''));
    }

    /** The address, or null when the account holds only a placeholder. */
    public function emailFor(LaasApplicant $applicant): ?string
    {
        $email = $this->phoneOtp->normaliseEmail((string) ($applicant->email ?? ''));

        return ($email === null || $this->phoneOtp->isPlaceholderEmail($email)) ? null : $email;
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('phone_verification.laas_login_otp.ttl_minutes', 10));
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
     * Hold a sign-in for this applicant and make sure a code is on its way.
     *
     * @param  string  $portal  self::PORTAL_LAAS or self::PORTAL_ALAES — the door they came in
     * @return array{sent:bool, message:string}
     */
    public function begin(Request $request, LaasApplicant $applicant, bool $remember, string $portal = self::PORTAL_LAAS): array
    {
        // A fresh session id for the pending state: whoever held the old one
        // before the password was typed does not inherit the half-finished
        // sign-in.
        $request->session()->regenerate();
        $request->session()->forget(self::PASSED_KEY);
        $request->session()->put(self::SESSION_KEY, [
            'applicant_id' => $applicant->id,
            'remember' => $remember,
            'portal' => $portal === self::PORTAL_ALAES ? self::PORTAL_ALAES : self::PORTAL_LAAS,
            'started_at' => now()->timestamp,
        ]);

        $live = $this->liveCode($applicant);
        $reuseFor = max(0, (int) config('phone_verification.laas_login_otp.reuse_minutes', 5)) * 60;

        if ($live && now()->timestamp - $live['sent_at'] < $reuseFor) {
            $where = $live['channel'] === self::CHANNEL_EMAIL
                ? $this->phoneOtp->maskEmail($this->emailFor($applicant))
                : $this->phoneOtp->mask($this->phoneFor($applicant));

            return [
                'sent' => true,
                'message' => "We sent a code to {$where} a moment ago. Enter that code, or ask for a new one below.",
            ];
        }

        return $this->send($request, $this->firstChannel($applicant));
    }

    /**
     * SMS normally — the applicant's mobile is their username and carries every
     * other message about their application. Between 19:45 and 08:00 the
     * networks hold that traffic until about 08:30, so inside those hours the
     * first code goes by email when the account has a real address.
     */
    public function firstChannel(LaasApplicant $applicant): string
    {
        // No reachable mobile leaves exactly one route.
        if ($this->phoneFor($applicant) === null) {
            return self::CHANNEL_EMAIL;
        }

        if (strtolower((string) config('phone_verification.laas_login_otp.channel', 'sms')) === self::CHANNEL_EMAIL
            && $this->emailFor($applicant) !== null) {
            return self::CHANNEL_EMAIL;
        }

        if (config('phone_verification.laas_login_otp.quiet_hours_email_first', true)
            && $this->inQuietHours()
            && $this->emailFor($applicant) !== null) {
            return self::CHANNEL_EMAIL;
        }

        return self::CHANNEL_SMS;
    }

    /**
     * config('app.timezone') is UTC on this deployment while Kano is WAT, so
     * the office clock has to be read explicitly.
     */
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
    public function liveCode(LaasApplicant $applicant): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $applicant->id);

        if (!is_array($code) || empty($code['hash']) || now()->timestamp - (int) $code['sent_at'] > $this->ttlMinutes() * 60) {
            return null;
        }

        return $code;
    }

    /** When the account was last sent a code and by which route, even if that code is spent. */
    public function lastSend(LaasApplicant $applicant): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $applicant->id);

        return is_array($code) && !empty($code['sent_at']) ? $code : null;
    }

    private function storeCode(LaasApplicant $applicant, ?string $hash, string $channel, int $attempts = 0, ?int $sentAt = null): void
    {
        Cache::put(self::CODE_CACHE . $applicant->id, [
            'hash' => $hash,
            'sent_at' => $sentAt ?? now()->timestamp,
            'channel' => $channel,
            'attempts' => $attempts,
        ], now()->addMinutes($this->ttlMinutes())->addSeconds($this->cooldownSeconds()));
    }

    /** SMS sent to this account in the rolling hour; the cap protects the handset and the wallet. */
    private function smsSentThisHour(LaasApplicant $applicant): int
    {
        $stamps = array_filter((array) Cache::get(self::SMS_LOG_CACHE . $applicant->id, []), fn ($t) => $t > now()->timestamp - 3600);

        return count($stamps);
    }

    private function recordSms(LaasApplicant $applicant): void
    {
        $stamps = array_filter((array) Cache::get(self::SMS_LOG_CACHE . $applicant->id, []), fn ($t) => $t > now()->timestamp - 3600);
        $stamps[] = now()->timestamp;
        Cache::put(self::SMS_LOG_CACHE . $applicant->id, array_values($stamps), now()->addHour());
    }

    /**
     * The applicant behind the pending sign-in, or null when there is none or
     * it has run out. A suspended account loses its pending sign-in here rather
     * than at the code screen, so a suspension takes effect mid-flow.
     */
    public function pendingApplicant(Request $request): ?LaasApplicant
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if (!is_array($pending) || empty($pending['applicant_id'])) {
            return null;
        }

        $limit = max(1, (int) config('phone_verification.laas_login_otp.challenge_minutes', 45)) * 60;
        if (now()->timestamp - (int) ($pending['started_at'] ?? 0) > $limit) {
            $this->clear($request);

            return null;
        }

        $applicant = LaasApplicant::find($pending['applicant_id']);
        if (!$applicant || !$applicant->isActive()) {
            $this->clear($request);

            return null;
        }

        return $applicant;
    }

    public function pending(Request $request): array
    {
        return (array) $request->session()->get(self::SESSION_KEY, []);
    }

    /** Which portal this sign-in started at, for the screens and the landing route. */
    public function portal(Request $request): string
    {
        $portal = (string) ($this->pending($request)['portal'] ?? self::PORTAL_LAAS);

        return $portal === self::PORTAL_ALAES ? self::PORTAL_ALAES : self::PORTAL_LAAS;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Seconds before another code may be asked for by $channel (null: by the
     * route last used).
     *
     * Switching route is always allowed at once: an SMS the network is holding
     * until morning is exactly when the applicant is told to use email instead.
     */
    public function cooldownRemaining(Request $request, ?string $channel = null): int
    {
        $applicant = $this->pendingApplicant($request);
        $last = $applicant ? $this->lastSend($applicant) : null;

        if (!$last || ($channel !== null && $channel !== $last['channel'])) {
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
    public function send(Request $request, string $channel): array
    {
        $applicant = $this->pendingApplicant($request);
        if (!$applicant) {
            return ['sent' => false, 'message' => 'Your sign-in has expired. Enter your phone number and password again.'];
        }

        $channel = $channel === self::CHANNEL_EMAIL ? self::CHANNEL_EMAIL : self::CHANNEL_SMS;

        $wait = $this->cooldownRemaining($request, $channel);
        if ($wait > 0) {
            return ['sent' => false, 'message' => "Wait {$wait} seconds before asking for another code."];
        }

        if ($channel === self::CHANNEL_SMS) {
            $cap = max(1, (int) config('phone_verification.laas_login_otp.max_sms_per_hour', 6));

            if ($this->smsSentThisHour($applicant) >= $cap) {
                return ['sent' => false, 'message' => $this->emailFor($applicant)
                    ? 'Too many codes have been texted to this account in the last hour. Use "Email it to me instead", or try again later.'
                    : 'Too many codes have been texted to this account in the last hour. Please try again later, or contact the Lands office.'];
            }
        }

        $code = $this->phoneOtp->generateCode();

        // Stored before the send and kept if it fails: the gateway may have
        // accepted the message whatever it answered, and the cooldown must
        // apply either way.
        $this->storeCode($applicant, $this->hash($code, $applicant), $channel);

        if ($channel === self::CHANNEL_SMS) {
            $this->recordSms($applicant);
        }

        return $channel === self::CHANNEL_EMAIL
            ? $this->sendByEmail($request, $applicant, $code)
            : $this->sendBySms($applicant, $code);
    }

    private function sendBySms(LaasApplicant $applicant, string $code): array
    {
        $phone = $this->phoneFor($applicant);
        if ($phone === null) {
            return ['sent' => false, 'message' => 'The mobile number on your account cannot receive a code. Use your email address instead, or contact the Lands office.'];
        }

        $accepted = $this->gateway->sendFirstAccepted($phone, $this->messagesFor($code), $this->sender());
        $status = $this->gateway->lastStatusCode();
        $reason = $this->gateway->lastFailureReason();

        if ($accepted === null) {
            Log::warning('LaasLoginOtpService: sign-in code not delivered by SMS', [
                'applicant_id' => $applicant->id,
                'gateway_code' => $status,
                'reason' => $reason,
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainFailure($status, $reason)];
        }

        return ['sent' => true, 'message' => 'A sign-in code has been sent to ' . $this->phoneOtp->mask($phone)
            . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    private function sendByEmail(Request $request, LaasApplicant $applicant, string $code): array
    {
        $email = $this->emailFor($applicant);
        if ($email === null) {
            return ['sent' => false, 'message' => 'There is no usable email address on your account. Use your mobile number, or contact the Lands office.'];
        }

        try {
            // ->send(), never ->queue(): see AccountVerificationCode.
            Mail::to($email)->send(new AccountVerificationCode(
                code: $code,
                recipientName: (string) ($applicant->name ?: 'there'),
                expiresInMinutes: $this->ttlMinutes(),
                requestedFrom: $request->ip(),
                subjectLine: (string) config('phone_verification.laas_login_otp.email_subject', 'Your LAAS Portal sign-in code'),
            ));
        } catch (\Throwable $e) {
            Log::warning('LaasLoginOtpService: sign-in code not delivered by email', [
                'applicant_id' => $applicant->id,
                'message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainEmailFailure($e)];
        }

        return ['sent' => true, 'message' => 'A sign-in code has been sent to ' . $this->phoneOtp->maskEmail($email)
            . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    /**
     * The name the code arrives under.
     *
     * This matters more than it looks. The gateway accepts an UNREGISTERED
     * sender ID, bills for it, and never delivers — HTTP 200, empty body,
     * indistinguishable from a message that arrived (see BulkSmsNgService's own
     * note on the argument). The first LAAS sign-in codes went out under the
     * config default KANOMLPP and reached nobody, while every staff sign-in code
     * that same morning went out as KLAES and arrived. So this follows the staff
     * OTP rather than the server default.
     *
     * LAAS_LOGIN_OTP_SENDER overrides it; null hands the gateway its own
     * default, which is the behaviour of every caller that predates the
     * argument.
     */
    private function sender(): ?string
    {
        $configured = config('phone_verification.laas_login_otp.sender');
        if (!empty($configured)) {
            return (string) $configured;
        }

        try {
            return \App\Models\SmsSetting::senderFor(\App\Models\SmsSetting::KEY_PHONE_OTP);
        } catch (\Throwable $e) {
            // Settings table unreadable; the server default is better than nothing.
            return null;
        }
    }

    /** @return array<int,string> */
    private function messagesFor(string $code): array
    {
        $tokens = ['{code}' => $code, '{minutes}' => (string) $this->ttlMinutes()];

        return array_values(array_filter([
            strtr((string) config('phone_verification.laas_login_otp.message'), $tokens),
            strtr((string) config('phone_verification.laas_login_otp.message_fallback'), $tokens),
        ]));
    }

    /* ── verifying ────────────────────────────────────────────────────────── */

    /**
     * Check a typed code against the pending sign-in.
     *
     * @return array{verified:bool, message:string, applicant:LaasApplicant|null, remember:bool}
     */
    public function verify(Request $request, string $code): array
    {
        $fail = fn (string $message) => ['verified' => false, 'message' => $message, 'applicant' => null, 'remember' => false];

        $applicant = $this->pendingApplicant($request);
        if (!$applicant) {
            return $fail('Your sign-in has expired. Enter your phone number and password again.');
        }

        $pending = $this->pending($request);
        $last = $this->lastSend($applicant);

        if (!$last || empty($last['hash'])) {
            return $fail('No code is waiting. Ask for a new one.');
        }

        if (now()->timestamp - (int) $last['sent_at'] > $this->ttlMinutes() * 60) {
            return $fail('That code has expired. Ask for a new one.');
        }

        $given = preg_replace('/\D+/', '', $code) ?? '';

        if (!hash_equals((string) $last['hash'], $this->hash($given, $applicant))) {
            // Counted per account, so opening another tab does not buy more guesses.
            $attempts = (int) ($last['attempts'] ?? 0) + 1;
            $left = $this->maxAttempts() - $attempts;

            if ($left <= 0) {
                // Burn the code, not the account: the applicant asks for
                // another one. The send time stays, so the resend cooldown
                // still applies.
                $this->storeCode($applicant, null, $last['channel'], $attempts, (int) $last['sent_at']);

                return $fail('Too many wrong codes. Ask for a new one.');
            }

            $this->storeCode($applicant, $last['hash'], $last['channel'], $attempts, (int) $last['sent_at']);

            return $fail($left === 1
                ? 'That code is not correct. One more attempt before you must ask for a new code.'
                : "That code is not correct. {$left} attempts left.");
        }

        $this->clear($request);
        // Single use: the code opens one sign-in, and a later attempt must get a new one.
        Cache::forget(self::CODE_CACHE . $applicant->id);

        return [
            'verified' => true,
            'message' => '',
            'applicant' => $applicant,
            'remember' => (bool) ($pending['remember'] ?? false),
        ];
    }

    /**
     * Keyed to the app key and the account, so a hash lifted from one session
     * store is useless against another account or another deployment.
     */
    private function hash(string $code, LaasApplicant $applicant): string
    {
        return hash_hmac('sha256', 'laas|' . $applicant->id . '|' . $code, (string) config('app.key'));
    }

    /* ── the session's standing ───────────────────────────────────────────── */

    public function markPassed(Request $request, LaasApplicant $applicant): void
    {
        $request->session()->put(self::PASSED_KEY, $applicant->id);
    }

    public function hasPassed(Request $request, LaasApplicant $applicant): bool
    {
        return (string) $request->session()->get(self::PASSED_KEY) === (string) $applicant->id;
    }
}
