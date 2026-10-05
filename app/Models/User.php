<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\Attendance\UserAbsenceCounter;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Payroll\RateService;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use Notifiable;
    use Impersonate;
    use HasFactory;

    // Specify SQL Server connection
    protected $connection = 'sqlsrv';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'type',
        'phone_number',
        'profile',
        'passport_photo_path',
        'is_pc_access',
        'lang',
        'subscription',
        'subscription_expire_date',
        'parent_id',
        'is_active',
        'assign_role', // stores user_role ids as comma-separated
        'department_id', // correct field for department
        'work_station',
        'user_level',    // new field for user level
        'user_type',     // new field for user type
        'username', // new field for username
        'work_days_per_week',
        'man_hours_per_day',
        'shift_code',
        'auto_deactivate',
        'staff_type_category',
        'workstation_payment_structure_id',
        'base_salary_override',
        'base_salary_source',
        'rank',
        'signature',
        'user_actions',
        'dfr_permissions',
        'fr_permissions',
        'is_on_leave',
        'leave_start_date',
        'leave_end_date',
        'leave_reason',
        'deputy_user_id',
        'out_of_office_from',
        'out_of_office_to',
        /*
         | otp, otp_sent_at, otp_channel and is_otp_verified are DELIBERATELY NOT
         | LISTED.
         |
         | is_otp_verified is the flag that decides whether the verification gate
         | holds an account, so a form that mass-assigns whatever it was posted would
         | be a way for a user to verify themselves without ever receiving a code.
         | otp_channel decides WHICH address a change voids the proof of, so it is
         | just as much a security value. Every write goes through PhoneOtpService
         | (forceFill) or `phone:verify-status`, both of which are explicit about it.
         | See getNeedsPhoneVerificationAttribute().
         */
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmail);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];


    protected $casts = [
        'email_verified_at' => 'datetime',
        'base_salary_override' => 'float',
        'workstation_payment_structure_id' => 'integer',
        'is_pc_access' => 'boolean',
        'auto_deactivate' => 'boolean',
        'is_on_leave' => 'boolean',
        'leave_start_date' => 'date',
        'leave_end_date' => 'date',
        'deputy_user_id' => 'integer',
        'out_of_office_from' => 'date',
        'out_of_office_to' => 'date',
        'photo_face_checked_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'is_otp_verified' => 'boolean',
    ];

    protected $appends = ['name'];
    protected array $permissionCache = [];

    protected static function booted(): void
    {
        static::created(function (User $user) {
            static::ensurePayrollRate($user);
        });

        /*
         | An address that changes is an address nobody has proved.
         |
         | is_otp_verified says "a code sent to THIS address was typed back", so it
         | cannot survive the address being edited — otherwise the gate is satisfied
         | once and the column then drifts back to whatever anybody types into the
         | profile form, which is the state this feature exists to end.
         |
         | ONLY THE ADDRESS THAT DID THE PROVING COUNTS. The code now travels by
         | email or by SMS, and users.otp_channel records which. An account verified
         | by email loses its proof when the EMAIL changes and not when somebody
         | corrects the phone number, and the other way round. Voiding on both would
         | be far more destructive than it sounds: a thousand accounts still carry a
         | placeholder email address that ICT will be editing in bulk, and every one
         | of those edits would throw a verified user back behind the card.
         |
         | A missing channel means SMS — that is what every account verified before
         | the email route existed used.
         |
         | Two things are deliberately left alone: a caller that is setting the flag
         | itself (PhoneOtpService, which changes the address and the flag together)
         | and a code that is being written in the same save. Clearing those would
         | destroy the code on its way to the gateway.
         */
        static::saving(function (User $user) {
            if (!$user->exists) {
                return;
            }

            $otp = app(\App\Services\PhoneOtpService::class);

            if (!$otp->columnsReady()) {
                return;
            }

            // recordedChannel() rather than the column alone: it also consults the
            // short-lived cache copy, which is the only record there is in the window
            // where users.otp_channel is not deployed yet. Unknown means SMS, which is
            // what every account proved before the email route existed.
            $provedBy = $otp->recordedChannel($user) === \App\Services\PhoneOtpService::CHANNEL_EMAIL
                ? \App\Services\PhoneOtpService::CHANNEL_EMAIL
                : \App\Services\PhoneOtpService::CHANNEL_SMS;

            $addressChanged = $otp->recordedChannel($user) === \App\Services\PhoneOtpService::CHANNEL_BOTH
                ? ($user->isDirty('email') || $user->isDirty('phone_number'))
                : ($provedBy === \App\Services\PhoneOtpService::CHANNEL_EMAIL
                ? $user->isDirty('email')
                : $user->isDirty('phone_number'));

            if (!$addressChanged) {
                return;
            }

            if (!$user->isDirty('is_otp_verified')) {
                $user->setAttribute('is_otp_verified', false);
            }

            if (!$user->isDirty('otp')) {
                $user->setAttribute('otp', null);
            }
        });

        static::updated(function (User $user) {
            if (
                ($user->wasChanged('work_station') && $user->work_station)
                || $user->wasChanged('workstation_payment_structure_id')
            ) {
                static::ensurePayrollRate($user);
            }

            if ($user->wasChanged('is_active') && (int) $user->is_active === 1) {
                UserAbsenceCounter::query()
                    ->where('user_id', $user->id)
                    ->update([
                        'consecutive_absences' => 0,
                        'monthly_absences' => 0,
                        'last_absent_date' => null,
                        'month_key' => Carbon::now()->format('Y-m'),
                    ]);
            }
        });
    }

    protected static function ensurePayrollRate(User $user): void
    {
        if (!$user->work_station) {
            return;
        }

        app(RateService::class)->syncBaselineRate($user, auth()->id());
    }

    public function paymentStructure()
    {
        return $this->belongsTo(\App\Models\Payroll\WorkstationPaymentStructure::class, 'workstation_payment_structure_id');
    }

    /**
     * The colleague designated to receive this user's file/task redirects
     * while they are on leave/holiday.
     */
    public function deputy()
    {
        return $this->belongsTo(User::class, 'deputy_user_id');
    }

    /**
     * Active users eligible to be selected as a deputy (redirect target)
     * while someone is on leave/holiday. Excludes the given user id.
     */
    public static function deputyOptions(?int $excludeUserId = null): array
    {
        return static::where('is_active', 1)
            ->when($excludeUserId, fn ($query) => $query->where('id', '!=', $excludeUserId))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($deputy) => [$deputy->id => trim($deputy->first_name . ' ' . $deputy->last_name)])
            ->toArray();
    }

    /**
     * Who may use "Login as user" (ImpersonationController).
     *
     * isSuperAdmin(), not `type == 'super admin'`: the account type is only one of the
     * three ways this system records a Super Admin -- the other two are the "supper admin"
     * spelling and the assigned role -- and an admin recorded the other ways was silently
     * refused.
     */
    public function canImpersonate()
    {
        return $this->isSuperAdmin();
    }

    /**
     * Whose session may be borrowed. A suspended account is refused here as well as in the
     * controller: it cannot sign in at the login form, so it must not be reachable this way
     * either.
     */
    public function canBeImpersonated()
    {
        return (int) ($this->is_active ?? 1) === 1;
    }

    /**
     * Whether this user is a Super Admin — by account type or assigned role
     * (handles the "supper admin" spelling used across the app).
     */
    public function isSuperAdmin(): bool
    {
        $type      = strtolower((string) $this->type);
        $roleNames = array_map('strtolower', $this->assignedRoleNames());

        return in_array($type, ['super admin', 'supper admin'], true)
            || in_array('super admin', $roleNames, true)
            || in_array('supper admin', $roleNames, true);
    }

    public function totalUser()
    {
        return User::where('parent_id', $this->id)->count();
    }

    public function getNameAttribute()
    {
        return ucfirst($this->first_name) . ' ' . ucfirst($this->last_name);
    }


    public function totalContact()
    {
        return Contact::where('parent_id', '=', parentId())->count();
    }

    public function roleWiseUserCount($role)
    {
        return User::where('type', $role)->where('parent_id', parentId())->count();
    }
    
    public static function getDevice($user)
    {
        $mobileType = '/(?:phone|windows\s+phone|ipod|blackberry|(?:android|bb\d+|meego|silk|googlebot) .+? mobile|palm|windows\s+ce|opera mini|avantgo|mobilesafari|docomo)/i';
        $tabletType = '/(?:ipad|playbook|(?:android|bb\d+|meego|silk)(?! .+? mobile))/i';
        if (preg_match_all($mobileType, $user)) {
            return 'mobile';
        } else {
            if (preg_match_all($tabletType, $user)) {
                return 'tablet';
            } else {
                return 'desktop';
            }
        }
    }

    public function totalDocument()
    {
        return Document::where('parent_id', '=', parentId())->count();
    }

    // Modified to handle null subscription
    public function subscriptions()
    {
        return $this->hasOne('App\Models\Subscription', 'id', 'subscription');
    }

    // Modified to handle null subscription
    public function SubscriptionLeftDay()
    {
        // No longer needed for this application
        return '<span class="text-success">' . __('Active') . '</span>';
    }

    /**
     * Get the department that the user belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user roles assigned to this user
     */
    public function userRoles()
    {
        if(empty($this->assign_role)) {
            return collect([]);
        }
        
        $roleIds = explode(',', $this->assign_role);
        return UserRole::whereIn('id', $roleIds)->get();
    }

    /**
     * Resolve normalized role names assigned to the user (handles IDs, JSON, CSV, etc.)
     */
    public function assignedRoleNames(): array
    {
        if (isset($this->permissionCache['assigned_role_names'])) {
            return $this->permissionCache['assigned_role_names'];
        }

        $raw = trim((string) $this->assign_role);
        if ($raw === '') {
            return $this->permissionCache['assigned_role_names'] = [];
        }

        $names = [];

        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $names = array_merge($names, $this->normalizeRoleNames($decoded));
        }

        $sanitized = str_replace([';', '|'], ',', $raw);
        $tokens = collect(explode(',', $sanitized))
            ->map(fn ($token) => trim($token))
            ->filter();

        $stringTokens = $tokens->filter(fn ($token) => !is_numeric($token));
        $numericTokens = $tokens->filter(fn ($token) => is_numeric($token))->map(fn ($id) => (int) $id);

        if ($stringTokens->isNotEmpty()) {
            $names = array_merge($names, $this->normalizeRoleNames($stringTokens->all()));
        }

        if ($numericTokens->isNotEmpty()) {
            $roleNames = UserRole::whereIn('id', $numericTokens->all())->pluck('name')->all();
            $names = array_merge($names, $this->normalizeRoleNames($roleNames));
        }

        return $this->permissionCache['assigned_role_names'] = array_values(array_unique($names));
    }

    protected function normalizeRoleNames(array $values): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => mb_strtolower(trim((string) $value)),
            $values
        )));
    }

    /**
     * Get session locks for this user
     */
    public function sessionLocks()
    {
        return $this->hasMany(UserSessionLock::class);
    }

    // =====================================================================
    // Phase 2: Activity Log Relationships
    // =====================================================================

    /**
     * Get all activity logs for this user
     */
    public function activityLogs()
    {
        return $this->hasMany(UserActivityLog::class, 'user_id', 'id');
    }

    /**
     * Get activity log settings for this user
     */
    public function activityLogSettings()
    {
        return $this->hasOne(UserActivityLogSetting::class, 'user_id', 'id');
    }

    /**
     * Get current session for this user
     */
    public function getCurrentSession()
    {
        return UserActivityLog::getCurrentSessionForUser($this->id);
    }

    /**
     * Get online users count
     */
    public static function getOnlineUsers()
    {
        return UserActivityLog::getOnlineUsers();
    }

    /**
     * Check if user is currently online
     */
    public function isOnline(): bool
    {
        $session = $this->getCurrentSession();
        return $session && $session->isOnline();
    }

    /**
     * Check if user is idle
     */
    public function isIdle(): bool
    {
        $session = $this->getCurrentSession();
        return $session && $session->isIdle();
    }

    /**
     * Get user's session statistics
     */
    public function getSessionStats($days = 30)
    {
        return UserActivityLog::getDurationStatsForUser($this->id, 
            Carbon::now()->subDays($days), 
            Carbon::now()
        );
    }

    /**
     * Check if user has permission.
     *
     * @param  string|array  $abilities
     * @param  array|mixed  $arguments
     */
    public function can($abilities, $arguments = [])
    {
        $roleNames = $this->assignedRoleNames();
        $type = strtolower((string)$this->type);
        if ($type === 'super admin' || $type === 'supper admin' || in_array('super admin', $roleNames) || in_array('supper admin', $roleNames)) {
            return true;
        }

        if (is_array($abilities)) {
            foreach ($abilities as $ability) {
                if ($this->can($ability, $arguments)) {
                    return true;
                }
            }

            return false;
        }

        if ($this->hasDatabasePermission((string) $abilities)) {
            return true;
        }

        $adminPermissions = [
            'manage user',
            'create user',
            'edit user',
            'delete user',
            'show user',
            'manage logged history',
            'delete logged history',
        ];

        if (in_array($abilities, $adminPermissions, true) && in_array($this->type, ['owner', 'admin'], true)) {
            return true;
        }

        return false;
    }

    /**
     * May this user perform $action on $module?
     *
     * $module is a user_roles name ("Survey - Records"); $action is one of
     * view | create | edit | delete | print | export.
     *
     * This is the app's real authorization entry point. It is deliberately SEPARATE from
     * can() above: can() is Laravel's Gate contract, 44 files call it, and it resolves
     * against Spatie tables that are empty here — so rewiring it would change behaviour
     * everywhere at once. canDo() adds the action layer without touching any of that.
     *
     * See App\Support\Permissions\ModulePermissions for the two rules that keep this safe
     * to deploy on a live registry: view is answered by assign_role, and a missing grant
     * row is not a denial until the backfill has run.
     */
    public function canDo(string $module, string $action = 'view'): bool
    {
        return \App\Support\Permissions\ModulePermissions::allows($this, $module, $action);
    }

    /**
     * Every action verdict for one module at once, for rendering a permission grid row.
     *
     * @return array<string, bool>
     */
    public function moduleActions(string $module): array
    {
        return \App\Support\Permissions\ModulePermissions::all($this, $module);
    }

    /**
     * Check a Digital File Request sub-permission.
     * Valid keys: view_requests | make_request | approve_request
     */
    public function hasDfrPermission(string $key): bool
    {
        $type = strtolower((string) $this->type);
        if ($type === 'super admin' || $type === 'supper admin') {
            return true;
        }
        if (empty($this->dfr_permissions)) {
            return false;
        }
        $perms = array_map('trim', explode(',', $this->dfr_permissions));
        return in_array($key, $perms, true);
    }

    /**
     * SCB Monitor (mobile file searcher) — receives File Search Requests.
     */
    public function isScbMonitor(): bool
    {
        return ($this->fr_permissions ?? '') === 'SCB';
    }

    /**
     * Office Priority Search (OFS) requester — a ranked officer whose users.rank
     * matches the hierarchy in config/file_request_priority.php. OFS requests are
     * prioritised, colour-coded on the SCB Feedback table, and floated to the top
     * of the SCB Monitor's mobile list.
     *
     * Super Admins (users.type / assign_role = "Super Admin" / "Supper Admin")
     * always qualify, whatever their rank reads — their users.rank is often a
     * local title (e.g. "HOS") that isn't part of the OFS hierarchy, which would
     * otherwise hide the Send-File-Search-Request form from them entirely.
     */
    public function isOfs(): bool
    {
        return $this->isSuperAdmin()
            || \App\Models\FileSearchRequest::priorityFor($this->rank) > 0;
    }

    /**
     * The user's rank when it qualifies them as an OFS requester, else null.
     */
    public function ofsRank(): ?string
    {
        return $this->isOfs() ? ($this->rank ?: null) : null;
    }

    /**
     * Lightweight permission lookup compatible with Spatie tables.
     */
    protected function hasDatabasePermission(string $ability): bool
    {
        if ($ability === '') {
            return false;
        }

        if (array_key_exists($ability, $this->permissionCache)) {
            return $this->permissionCache[$ability];
        }

        $tables = config('permission.table_names');
        if (!$tables) {
            return $this->permissionCache[$ability] = false;
        }

        $permissionsTable = $tables['permissions'] ?? 'permissions';
        $modelHasPermissionsTable = $tables['model_has_permissions'] ?? 'model_has_permissions';
        $modelHasRolesTable = $tables['model_has_roles'] ?? 'model_has_roles';
        $roleHasPermissionsTable = $tables['role_has_permissions'] ?? 'role_has_permissions';

        $permissionId = DB::table($permissionsTable)
            ->where('name', $ability)
            ->value('id');

        if (!$permissionId) {
            return $this->permissionCache[$ability] = false;
        }

        $modelType = static::class;

        $hasDirectPermission = DB::table($modelHasPermissionsTable)
            ->where('model_type', $modelType)
            ->where('model_id', $this->id)
            ->where('permission_id', $permissionId)
            ->exists();

        if ($hasDirectPermission) {
            return $this->permissionCache[$ability] = true;
        }

        $roleIds = DB::table($modelHasRolesTable)
            ->where('model_type', $modelType)
            ->where('model_id', $this->id)
            ->pluck('role_id');

        if ($roleIds->isNotEmpty()) {
            $hasRolePermission = DB::table($roleHasPermissionsTable)
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $roleIds)
                ->exists();

            if ($hasRolePermission) {
                return $this->permissionCache[$ability] = true;
            }
        }

        return $this->permissionCache[$ability] = false;
    }

    public static $systemModules = [
        'user',
        'document',
        'reminder',
        'comment',
        'version',
        'mail',
        'category',
        'tag',
        'contact',
        'note',
        'logged history',
        'pricing transation',
        'account settings',
        'password settings',
        'general settings',
        'company settings',
    ];

    /**
     * The `profile` column holds three different shapes depending on which screen wrote it:
     * a public-disk path ("profiles/x.jpg" from the profile page or "upload/profile/x.jpg"
     * from user create/edit), a bare filename that lives under upload/profile, or the legacy
     * "avatar.png" placeholder that has no file behind it. Resolve all of them in one place.
     *
     * Deliberately does not stat the disk: the user list renders hundreds of avatars and a
     * per-row existence check cost ~0.8ms each. A row pointing at a deleted file therefore
     * yields a broken image rather than the placeholder.
     */
    public function getProfileUrlAttribute(): ?string
    {
        return \App\Support\UserPhoto::url($this->profile, $this->passport_photo_path);
    }

    /**
     * True when the account has a passport photo on file — used to prompt users to upload one.
     */
    public function getHasProfilePhotoAttribute(): bool
    {
        return $this->profile_url !== null;
    }

    /**
     * The face-check verdict that applies to the picture currently on file, or null when
     * this picture has not been judged yet.
     *
     * Pinned to the file it was computed on: a row whose photo was replaced by a path
     * that bypassed ProfilePhotoService keeps a stale verdict in the columns, and it must
     * not be allowed to speak for the new picture. photo_face_path is only compared when
     * it was actually recorded, so verdicts written before that column existed still count.
     */
    public function getPhotoFaceVerdictAttribute(): ?string
    {
        $status = strtolower(trim((string) ($this->photo_face_status ?? '')));

        if ($status === '') {
            return null;
        }

        $judged = trim((string) ($this->photo_face_path ?? ''));
        $current = (string) (\App\Services\ProfilePhotoService::currentPhotoPath($this) ?? '');

        if ($judged !== '' && $current !== '' && $judged !== $current) {
            return null;
        }

        return $status;
    }

    /**
     * True when the picture on file was judged not to be a photograph of a face — a
     * cartoon avatar, a logo, a landscape.
     *
     * This is what makes an account with a stock avatar as locked as an account with no
     * picture at all: the two are the same failure — nobody can be identified from the
     * picture — and until 2026-09-05 only the second one was held.
     */
    public function getPhotoFaceRejectedAttribute(): bool
    {
        return $this->has_profile_photo
            && $this->photo_face_verdict === \App\Services\ProfilePhotoService::FACE_FAIL;
    }

    /**
     * True when there is a picture on file that no face check has judged yet, so the
     * browser should check it once and report the verdict.
     */
    public function getNeedsPhotoFaceCheckAttribute(): bool
    {
        return $this->has_profile_photo && $this->photo_face_verdict === null;
    }

    /**
     * True when the mandatory passport-photo card should be shown and the system locked.
     *
     * This is the FIRST gate a user meets: it comes before the first-login password
     * change, so nothing else may prompt until a photo is on file.
     *
     * Two ways to fail it: no picture at all, or a picture that is not a photograph of
     * the account holder's face. An unchecked picture passes — the gate closes only on a
     * verdict actually reaching the server, never on the absence of one.
     */
    public function getNeedsProfilePhotoAttribute(): bool
    {
        if (\App\Services\LoginOtpService::isProfilePhotoExempt($this)) {
            return false;
        }

        return !$this->has_profile_photo || $this->photo_face_rejected;
    }

    /**
     * True when the phone-number confirmation card should be shown and the system locked.
     *
     * The SECOND gate, and deliberately behind the photo: a user meets one blocking card
     * at a time, and the photo is the one that was already there. Signing in is not
     * affected by either — both hold the account after it is in, which is what lets a
     * member of staff whose number is wrong get far enough to correct it.
     *
     * The whole judgment (feature switch, and whether the columns are even deployed)
     * lives in PhoneOtpService so that a server without the migration holds nobody.
     */
    public function getNeedsPhoneVerificationAttribute(): bool
    {
        if (\App\Services\LoginOtpService::isOtpExempt($this)) {
            return false;
        }

        if ($this->needs_profile_photo) {
            return false;
        }

        return app(\App\Services\PhoneOtpService::class)->needsVerification($this);
    }

    /**
     * The first-login password change, which the dashboard prompts for.
     *
     * Held back until the passport photo and the phone number are both settled — its
     * prompt sends the user to the profile page, which both gates block while their
     * requirement is outstanding.
     */
    public function getNeedsPasswordChangeAttribute(): bool
    {
        return is_null($this->is_password_change)
            && !$this->needs_profile_photo
            && !$this->needs_phone_verification;
    }
}
