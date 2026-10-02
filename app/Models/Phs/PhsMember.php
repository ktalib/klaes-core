<?php

namespace App\Models\Phs;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class PhsMember extends Authenticatable
{
    use Notifiable;

    protected $connection = 'sqlsrv';
    protected $table = 'phs_members';

    protected $fillable = [
        'phs_institution_id',
        'name',
        'email',
        'phone',
        'password',
        'job_title',
        'department',
        'user_type',
        'access_role',
        'tokens_used',
        'allocated_tokens',
        'status',
        'last_login_at',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'allocated_tokens' => 'integer',
        'tokens_used' => 'integer',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Has this member proved the mobile number on their row?
     *
     * Both halves matter. A number with no phone_verified_at is one somebody
     * typed -- possibly an administrator adding a colleague, possibly a typo --
     * and nothing has shown it reaches the person signing in. Changing the
     * number clears the date, so it has to be proved again.
     */
    public function hasVerifiedPhone(): bool
    {
        return !empty($this->phone) && $this->phone_verified_at !== null;
    }

    /**
     * Normalise a Nigerian number to the 0XXXXXXXXXX form used for storage.
     *
     * BulkSmsNgService normalises again to 234... at send time, so either form
     * would deliver -- storing one form consistently is what lets the column be
     * compared at all. Lives on the model so the sign-in gate and the
     * organization console cannot drift apart on what counts as the same
     * number. Same rules as LaasApplicant::normalizePhone().
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

    /** 0803**** 567 -- enough to recognise, not enough to disclose. */
    public static function maskPhone(?string $phone): string
    {
        $phone = (string) $phone;

        if (strlen($phone) < 8) {
            return $phone;
        }

        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 7) . substr($phone, -3);
    }

    /** Returns the access_role as an array (supports comma-separated storage). */
    public function accessRoles(): array
    {
        return array_filter(array_map('trim', explode(',', $this->access_role ?? '')));
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->accessRoles(), true);
    }

    /** Regular users with search_only or super admins can run searches. */
    public function canSearch(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $roles = $this->accessRoles();
        return !empty(array_intersect($roles, ['search_only', 'report_viewer', 'analytics_viewer']));
    }

    public function canViewReports(): bool
    {
        return $this->isSuperAdmin() || !empty(array_intersect($this->accessRoles(), ['report_viewer', 'analytics_viewer']));
    }
}
