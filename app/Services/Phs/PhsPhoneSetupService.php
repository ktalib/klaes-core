<?php

namespace App\Services\Phs;

use App\Models\Phs\PhsMember;
use App\Services\BulkSmsNgService;
use App\Services\PhoneOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Collecting and PROVING a PHS member's mobile number.
 *
 * phs_members.phone was added on 2026-10-01 and every row started empty. A
 * member with no proved number is held at a card on their next sign-in
 * (RequirePhsPhone): type the mobile, receive a code on it, type the code back.
 * Only then is phone_verified_at set and the portal opened.
 *
 * PROVED, not merely typed, and that distinction is the whole point. An unproved
 * number looks like a usable second factor and is not — phs_institutions.phone
 * is the cautionary example, mostly seeded data like "178-257-9000" which
 * normalises into a well-formed 234… number the gateway accepts, bills for and
 * never delivers. Nothing is written to the member row until a code has come
 * back from the handset.
 *
 * The staged number and its code live in the CACHE, never on the row. A member
 * halfway through this card has changed nothing about their account, so an
 * abandoned attempt leaves no half-set state behind, and the whole feature adds
 * no columns beyond the two the migration created.
 */
class PhsPhoneSetupService
{
    /** Per-member staging: phone, hash, sent_at, attempts. */
    public const CACHE = 'phs_phone_setup.';

    /** Per-member timestamps of SMS sent in the last hour, for the hourly cap. */
    public const SMS_LOG_CACHE = 'phs_phone_setup.sms.';

    /**
     * The only prefixes a Nigerian mobile actually starts with.
     *
     * normalizePhone() only checks the SHAPE, so "178-257-9000" comes back as
     * "01782579000" — eleven digits, leading zero, entirely well formed and not
     * a phone. Since the number collected here becomes a second factor, a
     * plausible-looking fake is the one outcome worth refusing outright.
     */
    private const MOBILE_PREFIXES = ['070', '071', '080', '081', '090', '091'];

    public function __construct(
        private PhoneOtpService $phoneOtp,
        private BulkSmsNgService $gateway,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('phone_verification.phs_phone_setup.enabled', true);
    }

    /** Must this member be held at the card? */
    public function needsSetup(PhsMember $member): bool
    {
        return $this->enabled() && !$member->hasVerifiedPhone();
    }

    public function ttlMinutes(): int
    {
        return max(1, (int) config('phone_verification.phs_phone_setup.ttl_minutes', 10));
    }

    public function cooldownSeconds(): int
    {
        return $this->phoneOtp->cooldownSeconds();
    }

    public function maxAttempts(): int
    {
        return $this->phoneOtp->maxAttempts();
    }

    /** Is this a number the gateway could actually reach? */
    public function normalise(string $phone): ?string
    {
        $normalised = PhsMember::normalizePhone($phone);

        if ($normalised === null) {
            return null;
        }

        foreach (self::MOBILE_PREFIXES as $prefix) {
            if (str_starts_with($normalised, $prefix)) {
                return $normalised;
            }
        }

        return null;
    }

    /* ── the staged number ────────────────────────────────────────────────── */

    /**
     * The number waiting to be confirmed, or null when none is.
     *
     * @return array{phone:string, hash:?string, sent_at:int, attempts:int}|null
     */
    public function staged(PhsMember $member): ?array
    {
        $staged = Cache::get(self::CACHE . $member->id);

        if (!is_array($staged) || empty($staged['phone'])) {
            return null;
        }

        return $staged;
    }

    /** The staged number, masked, for the card's "we texted 0803**** 567" line. */
    public function maskedStaged(PhsMember $member): ?string
    {
        $staged = $this->staged($member);

        return $staged ? PhsMember::maskPhone($staged['phone']) : null;
    }

    /** Seconds the staged code has left, 0 when there is none or it has lapsed. */
    public function expiresIn(PhsMember $member): int
    {
        $staged = $this->staged($member);

        if (!$staged || empty($staged['hash'])) {
            return 0;
        }

        return max(0, $this->ttlMinutes() * 60 - (now()->timestamp - (int) $staged['sent_at']));
    }

    public function cooldownRemaining(PhsMember $member): int
    {
        $staged = $this->staged($member);

        if (!$staged) {
            return 0;
        }

        return max(0, $this->cooldownSeconds() - (now()->timestamp - (int) $staged['sent_at']));
    }

    /** Throw the staged number away so the card asks for a number again. */
    public function discard(PhsMember $member): void
    {
        Cache::forget(self::CACHE . $member->id);
    }

    private function stage(PhsMember $member, string $phone, ?string $hash, int $attempts = 0, ?int $sentAt = null): void
    {
        Cache::put(self::CACHE . $member->id, [
            'phone' => $phone,
            'hash' => $hash,
            'sent_at' => $sentAt ?? now()->timestamp,
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

    /* ── sending ──────────────────────────────────────────────────────────── */

    /**
     * Stage a number and text a code to it.
     *
     * $rawPhone null resends to the number already staged, which is what the
     * card's "Send another code" does.
     *
     * @return array{sent:bool, message:string}
     */
    public function send(PhsMember $member, ?string $rawPhone = null): array
    {
        if ($rawPhone === null) {
            $staged = $this->staged($member);
            if (!$staged) {
                return ['sent' => false, 'message' => 'Enter the mobile number you want to use.'];
            }

            $phone = $staged['phone'];

            $wait = $this->cooldownRemaining($member);
            if ($wait > 0) {
                return ['sent' => false, 'message' => "Wait {$wait} seconds before asking for another code."];
            }
        } else {
            $phone = $this->normalise($rawPhone);

            if ($phone === null) {
                return ['sent' => false, 'message' => 'Enter a valid Nigerian mobile number, for example 08031234567.'];
            }
        }

        $cap = max(1, (int) config('phone_verification.phs_phone_setup.max_sms_per_hour', 6));
        if ($this->smsSentThisHour($member) >= $cap) {
            return ['sent' => false, 'message' => 'Too many codes have been texted to this account in the last hour. Please try again later, or contact KLAES support.'];
        }

        $code = $this->phoneOtp->generateCode();

        // Staged before the send and kept if it fails: the gateway may have
        // accepted the message whatever it answered, and the cooldown must apply
        // either way.
        $this->stage($member, $phone, $this->hash($code, $member, $phone));
        $this->recordSms($member);

        $accepted = $this->gateway->sendFirstAccepted($phone, $this->messagesFor($code), $this->sender());
        $status = $this->gateway->lastStatusCode();
        $reason = $this->gateway->lastFailureReason();

        if ($accepted === null) {
            Log::warning('PhsPhoneSetupService: confirmation code not delivered', [
                'member_id' => $member->id,
                'gateway_code' => $status,
                'reason' => $reason,
            ]);

            return ['sent' => false, 'message' => $this->phoneOtp->explainFailure($status, $reason)];
        }

        return ['sent' => true, 'message' => 'We sent a ' . strlen($code) . '-digit code to '
            . PhsMember::maskPhone($phone) . '. It expires in ' . $this->ttlMinutes() . ' minutes.'];
    }

    /**
     * The name the code arrives under.
     *
     * Follows the staff sign-in code rather than the server default: the gateway
     * accepts an unregistered sender ID, bills for it and never delivers, which
     * is exactly how the first LAAS codes were lost. See
     * LaasLoginOtpService::sender().
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
            strtr((string) config('phone_verification.phs_phone_setup.message'), $tokens),
            strtr((string) config('phone_verification.phs_phone_setup.message_fallback'), $tokens),
        ]));
    }

    /* ── confirming ───────────────────────────────────────────────────────── */

    /**
     * Check a typed code and, if it matches, write the number to the member.
     *
     * This is the only place phone and phone_verified_at are set together, so a
     * stored number always carries the date the handset answered for it.
     *
     * @return array{verified:bool, message:string}
     */
    public function confirm(PhsMember $member, string $code): array
    {
        $staged = $this->staged($member);

        if (!$staged || empty($staged['hash'])) {
            return ['verified' => false, 'message' => 'No code is waiting. Ask for a new one.'];
        }

        if (now()->timestamp - (int) $staged['sent_at'] > $this->ttlMinutes() * 60) {
            return ['verified' => false, 'message' => 'That code has expired. Ask for a new one.'];
        }

        $given = preg_replace('/\D+/', '', $code) ?? '';

        if (!hash_equals((string) $staged['hash'], $this->hash($given, $member, $staged['phone']))) {
            $attempts = (int) ($staged['attempts'] ?? 0) + 1;
            $left = $this->maxAttempts() - $attempts;

            if ($left <= 0) {
                // Burn the code, keep the number staged: they ask for another
                // code rather than re-typing the mobile. The send time stays, so
                // the resend cooldown still applies.
                $this->stage($member, $staged['phone'], null, $attempts, (int) $staged['sent_at']);

                return ['verified' => false, 'message' => 'Too many wrong codes. Ask for a new one.'];
            }

            $this->stage($member, $staged['phone'], $staged['hash'], $attempts, (int) $staged['sent_at']);

            return ['verified' => false, 'message' => $left === 1
                ? 'That code is not correct. One more attempt before you must ask for a new code.'
                : "That code is not correct. {$left} attempts left."];
        }

        $member->forceFill([
            'phone' => $staged['phone'],
            'phone_verified_at' => now(),
        ])->save();

        $this->discard($member);

        Log::info('PhsPhoneSetupService: member mobile number confirmed', [
            'member_id' => $member->id,
            'institution_id' => $member->phs_institution_id,
            'phone' => PhsMember::maskPhone($staged['phone']),
        ]);

        return ['verified' => true, 'message' => 'Your mobile number has been confirmed.'];
    }

    /**
     * Keyed to the app key, the member AND the staged number, so a code issued
     * for one number cannot be replayed against a different one.
     */
    private function hash(string $code, PhsMember $member, string $phone): string
    {
        return hash_hmac('sha256', 'phs-setup|' . $member->id . '|' . $phone . '|' . $code, (string) config('app.key'));
    }
}
