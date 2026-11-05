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
