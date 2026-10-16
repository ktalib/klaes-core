<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\User;

/**
 * Who may work a Cadastral desk (user, 2026-10-15).
 *
 * The department role (Cad - Records) covers every desk; otherwise a user needs the desk's
 * own role (config cadastral_dept.desk_roles). Super Admin passes through canDo().
 * Viewing a case stays open to everyone in the department; only desk pages and writes
 * are checked, by the cadastral.desk middleware.
 */
class DeskAccess
{
    public function __construct(private WorkflowEngine $engine)
    {
    }

    public static function departmentRole(): string
    {
        return (string) config('cadastral_dept.department_role', 'Cad - Records');
    }

    /** @return array<string, string> desk key => role name */
    public static function deskRoles(): array
    {
        return (array) config('cadastral_dept.desk_roles', []);
    }

    public function canWorkDesk(?User $user, ?string $desk): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->canDo(self::departmentRole(), 'view')) {
            return true;
        }
        $role = $desk ? (self::deskRoles()[$desk] ?? null) : null;

        return $role !== null && $user->canDo($role, 'view');
    }

    /** True when the user holds the department role or any desk role. */
    public function inDepartment(?User $user): bool
    {
        return self::can($user, 'view');
    }

    /**
     * A department-wide action (print, edit, ...) through the department role or any desk /
     * Verification role. Which file a user may act on is the middleware's desk check.
     * With $desk, only the department role or that desk's role counts.
     * Blade: @cadCan('print') ... @endcadCan, @cadCan('create', 'ad_pnd') ... @endcadCan.
     */
    public static function can(?User $user, string $action = 'view', ?string $desk = null): bool
    {
        if (!$user) {
            return false;
        }
        $roles = $desk !== null
            ? array_filter([self::departmentRole(), self::deskRoles()[$desk] ?? null])
            : array_merge([self::departmentRole()], array_values(self::deskRoles()),
                array_filter([(string) config('cadastral_dept.verification_role', '')]));
        foreach ($roles as $role) {
            if ($user->canDo($role, $action)) {
                return true;
            }
        }

        return false;
    }

    /** The internal desk that acts on the case now (an external step's recorder). */
    public function actingDesk(CadastralCase $case): ?string
    {
        if (!$case->current_step) {
            return $case->current_desk;
        }
        try {
            return $this->engine->definition()->actingDesk($case->workflow, $case->current_step);
        } catch (\Throwable $e) {
            return $case->current_desk;
        }
    }

    public function canActOn(?User $user, CadastralCase $case): bool
    {
        return $this->canWorkDesk($user, $this->actingDesk($case));
    }
}
