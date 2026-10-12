<?php

namespace App\Models\Laas;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A public LAAS Portal account. Authenticated by the `laas` guard, which is
 * entirely separate from staff `auth` — see config/auth.php.
 */
class LaasApplicant extends Authenticatable
{
    use Notifiable;

    protected $connection = 'sqlsrv';
    protected $table = 'laas_applicants';

    /** A phone-change code is good for ten minutes and five guesses. */
    public const OTP_TTL_MINUTES = 10;
    public const OTP_MAX_ATTEMPTS = 5;

    /** Registered on the portal themselves. */
    public const ORIGIN_PORTAL = 'portal';

    /** Opened by File Commissioning (Land or OSS) on the applicant's behalf. */
    public const ORIGIN_COMMISSIONING = 'commissioning';

    /** Real contact details on file. NULL reads as ok (rows from before the column). */
    public const CONTACT_OK = 'ok';

    /** Carries a system-generated email and/or phone until a real one is entered. */
    public const CONTACT_PENDING = 'pending_update';

    /**
     * System phone: "SYS-" + 8 digits. Eight digits is deliberate: both
     * normalizePhone() and BulkSmsNgService::normalizeNumber() accept any 10 or
     * 11 digits starting with 0, so a digits-only placeholder would be texted.
     */
    public const SYSTEM_PHONE_PREFIX = 'SYS-';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'must_change_password',
        'nin',
        'address',
        'status',
        'account_origin',
        'phone_verified_at',
        'last_login_at',
        'pending_phone',
        'verification_code',
        'verification_code_expires_at',
        'verification_attempts',
        'contact_status',
        'contact_flagged_at',
        'contact_flag_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'verification_code',
    ];

    protected $casts = [
        'phone_verified_at'            => 'datetime',
        'last_login_at'                => 'datetime',
        'verification_code_expires_at' => 'datetime',
        'verification_attempts'        => 'integer',
        'must_change_password'         => 'boolean',
        'contact_flagged_at'           => 'datetime',
    ];

    /** The undialable phone a contact-pending account carries: SYS-00003141. */
    public static function systemPhoneFor(int $applicantId): string
    {
        return self::SYSTEM_PHONE_PREFIX . str_pad((string) $applicantId, 8, '0', STR_PAD_LEFT);
    }

    public static function isSystemPhone(?string $phone): bool
    {
        return str_starts_with(strtoupper(trim((string) $phone)), self::SYSTEM_PHONE_PREFIX);
    }

    /**
     * The flag columns once a real phone is on file: the phone reasons drop
     * away; a missing email alone does not keep the account pending.
     */
    public static function contactSettledAttributes(?string $reason): array
    {
        $left = array_values(array_diff(
            array_filter(explode(',', (string) $reason)),
            ['no_phone', 'shared_phone']
        ));

        return [
            'contact_status'      => self::CONTACT_OK,
            'contact_flag_reason' => $left ? implode(',', $left) : null,
        ];
    }

    public function hasPendingContact(): bool
    {
        return $this->contact_status === self::CONTACT_PENDING;
    }

    /** A phone the system may text: real, not the SYS- placeholder. */
    public function textablePhone(): ?string
    {
        return self::isSystemPhone($this->phone) ? null : (self::normalizePhone((string) $this->phone) ?: null);
    }

    public function applications()
    {
        return $this->hasMany(LaasApplication::class, 'laas_applicant_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** True while a phone change is staged and its code is still good. */
    public function hasPendingPhoneChange(): bool
    {
        return !empty($this->pending_phone)
            && $this->verification_code_expires_at !== null
            && $this->verification_code_expires_at->isFuture();
    }

    /** Clear a staged phone change, whether it was used, abandoned or expired. */
    public function clearPhoneChange(): void
    {
        $this->forceFill([
            'pending_phone'                => null,
            'verification_code'            => null,
            'verification_code_expires_at' => null,
            'verification_attempts'        => 0,
        ])->save();
    }

    /**
     * Normalise a Nigerian number to the 0XXXXXXXXXX form used for storage.
     *
     * BetaSmsService normalises again to 234… at send time, so either form
     * would deliver — storing one form consistently is what makes the
     * uniqueness checks on this column mean anything. Lives on the model so
     * registration, sign-in and the profile screen cannot drift apart.
     */
    public static function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            return '0' . substr($digits, 3);
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return $digits;
        }

        // Leading zero already stripped somewhere upstream.
        if (strlen($digits) === 10) {
            return '0' . $digits;
        }

        return null;
    }

    /** 0803**** 567 — enough to recognise, not enough to disclose. */
    public static function maskPhone(?string $phone): string
    {
        $phone = (string) $phone;

        if (strlen($phone) < 8) {
            return $phone;
        }

        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 7) . substr($phone, -3);
    }
}
