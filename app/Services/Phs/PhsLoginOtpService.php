<?php

namespace App\Services\Phs;

use App\Mail\AccountVerificationCode;
use App\Models\Phs\PhsMember;
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
 * Sign-in codes are delivered only by email. No mobile number is required.
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

    public const CHANNEL_EMAIL = PhoneOtpService::CHANNEL_EMAIL;

    public function __construct(private PhoneOtpService $phoneOtp)
    {
    }

    /* ── policy ───────────────────────────────────────────────────────────── */

    public function enabled(): bool
    {
        return (bool) config('phone_verification.phs_login_otp.enabled', true);
    }

    /**
     * Must this member type a code to sign in?
     *
     * Only when the member has a usable email address and email OTP is enabled.
     */
    public function requiredFor(PhsMember $member): bool
    {
        return $this->enabled() && $this->emailFor($member) !== null;
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
            $where = $this->phoneOtp->maskEmail($this->emailFor($member));

            return [
                'sent' => true,
                'message' => "A code was sent to {$where} a moment ago. Enter that code, or ask for a new one below.",
            ];
        }

        return $this->send($request);
    }

    /**
     * The member's current code, or null when none is live.
     *
     * @return array{hash:?string, sent_at:int, attempts:int}|null
     */
    public function liveCode(PhsMember $member): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $member->id);

        if (!is_array($code) || ($code['channel'] ?? self::CHANNEL_EMAIL) !== self::CHANNEL_EMAIL || empty($code['hash']) || now()->timestamp - (int) $code['sent_at'] > $this->ttlMinutes() * 60) {
            return null;
        }

        return $code;
    }

    /** When the member was last sent a code, even if that code is spent. */
    public function lastSend(PhsMember $member): ?array
    {
        $code = Cache::get(self::CODE_CACHE . $member->id);

        return is_array($code) && ($code['channel'] ?? self::CHANNEL_EMAIL) === self::CHANNEL_EMAIL
            && !empty($code['sent_at']) ? $code : null;
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

    /** Seconds before another email code may be requested. */
    public function cooldownRemaining(Request $request): int
    {
        $member = $this->pendingMember($request);
        $last = $member ? $this->lastSend($member) : null;

        if (!$last) {
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
    public function send(Request $request): array
    {
        $member = $this->pendingMember($request);
        if (!$member) {
            return ['sent' => false, 'message' => 'Your sign-in has expired. Enter your username and password again.'];
        }

        $wait = $this->cooldownRemaining($request);
        if ($wait > 0) {
            return ['sent' => false, 'message' => "Wait {$wait} seconds before asking for another code."];
        }

        $code = $this->phoneOtp->generateCode();

        // Keep the code and cooldown even if the mailer reports a failure.
        $this->storeCode($member, $this->hash($code, $member));

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
