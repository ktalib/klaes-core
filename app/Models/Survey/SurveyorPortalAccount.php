<?php

namespace App\Models\Survey;

use App\Models\Laas\LaasApplicant;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A surveyor's account on the Surveyor General's Office portal (guard: surveyor), separate from staff
 * logins. An account waits at `pending` until the SG approves it; only `active` can sign in.
 */
class SurveyorPortalAccount extends Authenticatable
{
    use \App\Models\Concerns\HasAddressBuilder;   // person_address, from the addr_* columns

    protected $connection = 'sqlsrv';
    protected $table = 'surveyor_portal_accounts';

    public const PENDING = 'pending';
    public const ACTIVE = 'active';
    public const REJECTED = 'rejected';
    public const SUSPENDED = 'suspended';

    /** A reset code is good for ten minutes and five guesses. */
    public const RESET_TTL_MINUTES = 10;
    public const RESET_MAX_ATTEMPTS = 5;

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'reset_code'];

    protected $casts = ['reviewed_at' => 'datetime', 'last_login_at' => 'datetime', 'reset_expires_at' => 'datetime'];

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function getTypeLabelAttribute(): string
    {
        return SurveyBeaconRegister::LAYOUT_TYPES[$this->surveyor_type] ?? (string) $this->surveyor_type;
    }

    /** Who holds the account: a Private registration is the company's, with its surveyor under it (user, 2026-11-06). */
    public function getHolderNameAttribute(): string
    {
        return $this->surveyor_type === 'Private' && $this->company_name ? $this->company_name : (string) $this->name;
    }

    /**
     * The same company, on either of two accounts: the listed company, its name, or its RC number.
     * Case, spaces and punctuation don't count, and "RC 123456" is "123456".
     *
     * @param array{company_id:?int, company_name:?string, company_rc_no:?string} $a
     * @param array{company_id:?int, company_name:?string, company_rc_no:?string} $b
     */
    public static function sameCompanyAs(array $a, array $b): ?string
    {
        $key = fn (?string $v) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $v));
        $rc = fn (?string $v) => preg_replace('/^RC/', '', $key($v));

        if (! empty($a['company_id']) && (int) $a['company_id'] === (int) ($b['company_id'] ?? 0)) return 'company';
        if ($key($a['company_name'] ?? '') !== '' && $key($a['company_name'] ?? '') === $key($b['company_name'] ?? '')) return 'company';
        if ($rc($a['company_rc_no'] ?? '') !== '' && $rc($a['company_rc_no'] ?? '') === $rc($b['company_rc_no'] ?? '')) return 'rc';

        return null;
    }

    public function notes()
    {
        return $this->hasMany(SurveyorPortalNotification::class, 'surveyor_portal_account_id');
    }

    /** The same storage form LAAS uses, so sign-in and registration agree on what one number is. */
    public static function normalizePhone(?string $phone): ?string
    {
        return LaasApplicant::normalizePhone((string) $phone);
    }

    /** Next id with this stem, e.g. KSSGOF-REG-2026-00001. The unique index catches two at once. */
    public static function nextId(string $column, string $stem, int $pad): string
    {
        $prefix = $stem . '-' . now()->year . '-';
        $last = static::where($column, 'like', $prefix . '%')->max($column);

        return $prefix . str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), $pad, '0', STR_PAD_LEFT);
    }
}
