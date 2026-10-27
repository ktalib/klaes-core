<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Models\User;

/**
 * Who may work a Cadastral desk (user, 2026-10-15).
 *
 * A desk needs its own role (config cadastral_dept.desk_roles). Since 2026-10-26 the department
 * role (Cad - Records) no longer opens desks (user: "desk roles only"); it keeps the department's
 * shared pages (dashboard, file pages, prints). Super Admin and Central Admin cover every desk.
 * Viewing a case stays open to everyone in the department; only desk pages and writes
 * are checked, by the cadastral.desk middleware. Central Admin (config central_admin_role)
 * covers every desk too.
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

    /**
     * Every role that opens a desk: its own (desk_roles) and any extra (desk_extra_roles, e.g.
     * Cad - Records opens Cadastral Report since 2026-10-26).
     *
     * @return string[]
     */
    public static function rolesFor(?string $desk): array
    {
        if ($desk === null) {
            return [];
        }

        return array_values(array_filter(array_merge(
            [self::deskRoles()[$desk] ?? null],
            (array) (config('cadastral_dept.desk_extra_roles', [])[$desk] ?? [])
        )));
    }

    public static function centralAdminRole(): string
    {
        return (string) config('cadastral_dept.central_admin_role', 'Cad - Central Admin');
    }

    /**
     * Central Admin (2026-10-26): works every desk and stands in for every unit head, so a file
     * waiting on an absent officer can still move. Super Admin passes through canDo().
     */
    public static function isCentralAdmin(?User $user): bool
    {
        return $user !== null && $user->canDo(self::centralAdminRole(), 'view');
    }

    /**
     * The Central Admin role given to the user by name. Standing in for a unit head needs this:
     * Super Admin alone keeps the 2026-10-16 rule (head only while the post is vacant).
     */
    public static function holdsCentralAdminRole(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        $role = mb_strtolower(self::centralAdminRole());

        return in_array($role, array_map('mb_strtolower', $user->assignedRoleNames()), true);
    }

    public function canWorkDesk(?User $user, ?string $desk): bool
    {
        if (!$user) {
            return false;
        }
        if (self::isCentralAdmin($user)) {
            return true;
        }

        return $this->officerCanWorkDesk($user, $desk);
    }

    /** The desk check without Central Admin: the user's own desk role (Super Admin passes). */
    public function officerCanWorkDesk(?User $user, ?string $desk): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }
        foreach (self::rolesFor($desk) as $role) {
            if ($user->canDo($role, 'view')) {
                return true;
            }
        }

        return false;
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
            ? array_merge([self::centralAdminRole()], self::rolesFor($desk))   // desk roles only (2026-10-26)
            : array_merge([self::departmentRole(), self::centralAdminRole()], array_values(self::deskRoles()),
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
