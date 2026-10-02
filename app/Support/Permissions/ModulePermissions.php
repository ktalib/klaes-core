<?php

namespace App\Support\Permissions;

use App\Models\ModulePermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * The single gate. Answers "may this user do <action> in <module>".
 *
 * Two rules here are load-bearing and should not be "tidied" without reading this note.
 *
 * 1. VIEW COMES FROM users.assign_role, NOT FROM module_permissions.
 *
 *    Sidebar visibility has always been a string match against assign_role. Three name
 *    spaces exist and they do not line up: the menu checks 190 distinct names, user_roles
 *    holds 167, and users actually hold 188. Eight names are granted AND checked by the
 *    sidebar AND absent from user_roles — 38 users between them, including 18 on
 *    "Deeds - Property Index Cards Assistant (Legacy Records)".
 *
 *    Had view been moved onto the new table, those 38 users would have lost menu items the
 *    moment a backfill built from the registry missed them. Reading view off assign_role
 *    means sidebar access cannot regress even if the backfill is wrong, incomplete, or
 *    never run at all. That is the property worth keeping.
 *
 * 2. AN ABSENT ROW IS NOT A DENIAL WHILE THE TABLE IS EMPTY.
 *
 *    This is live production with no backups. Deploying the gate before the backfill runs
 *    would strip create/edit/print from 596 users at once. So an action is allowed when the
 *    user can view the module and no grant row exists for it yet — the table opting in,
 *    rather than the app failing closed on empty data. `delete` is the exception: it is
 *    never implied, only ever granted explicitly. Once the backfill has run every viewer
 *    has a row, and this fallback stops being reachable for them.
 *
 *    Flip config('module_permissions.strict_actions') to true after the backfill reconciles
 *    to make a missing row a denial.
 */
final class ModulePermissions
{
    /** Cache of user id => [normalized module => [action => bool]]. */
    private static array $grants = [];

    /** Cache of user id => [normalized module => true] from assign_role. */
    private static array $viewable = [];

    private static ?bool $tableExists = null;

    /**
     * May the user perform $action on $module?
     */
    public static function allows(?User $user, string $module, string $action = 'view'): bool
    {
        if (!$user) {
            return false;
        }

        // The whole-system grant. Reuses the model's own predicate so the two spellings
        // of "Supper Admin" stay in one place.
        if ($user->isSuperAdmin()) {
            return true;
        }

        $key = ModuleName::normalize($module);

        if ($key === '' || !in_array($action, ModulePermission::ACTIONS, true)) {
            return false;
        }

        // Rule 1: view is answered by assign_role alone.
        $canView = isset(self::viewable($user)[$key]);

        if ($action === 'view') {
            return $canView;
        }

        // An action on a module the user cannot even see is never allowed, whatever
        // rows may be lying around in the table.
        if (!$canView) {
            return false;
        }

        $grants = self::grants($user);

        if (array_key_exists($key, $grants)) {
            return $grants[$key][$action] ?? false;
        }

        /*
         | Rule 2: no row yet. Allow everything except delete until the backfill lands.
         |
         | approve is allowed here on purpose. It is a new permission, but not a new ability:
         | until it existed the 29 approve/reject routes were gated as `edit`, which this
         | fallback already permits. Denying it would take approval away from officers who
         | have it rather than leaving them where they were.
         |
         | delete stays withheld, because nothing ever authorized it.
        */
        if (config('module_permissions.strict_actions', false)) {
            return false;
        }

        return $action !== 'delete';
    }

    /**
     * Every action verdict for one module — for rendering a row of the user modal's grid
     * without six separate lookups.
     *
     * @return array<string, bool>
     */
    public static function all(?User $user, string $module): array
    {
        $out = [];

        foreach (ModulePermission::ACTIONS as $action) {
            $out[$action] = self::allows($user, $module, $action);
        }

        return $out;
    }

    /**
     * The modules a user may see, as normalized names. Straight off assign_role.
     *
     * @return array<int, string>
     */
    public static function viewableModules(?User $user): array
    {
        return $user ? array_keys(self::viewable($user)) : [];
    }

    /**
     * Stored grant rows for a user, keyed by normalized module name. One query, memoized.
     *
     * @return array<string, array<string, bool>>
     */
    public static function grants(User $user): array
    {
        if (isset(self::$grants[$user->id])) {
            return self::$grants[$user->id];
        }

        if (!self::tableExists()) {
            return self::$grants[$user->id] = [];
        }

        $rows = ModulePermission::query()->where('user_id', $user->id)->get();

        $grants = [];

        foreach ($rows as $row) {
            $actions = [];

            foreach (ModulePermission::ACTIONS as $action) {
                $actions[$action] = (bool) $row->{'can_' . $action};
            }

            $grants[ModuleName::normalize($row->module_name)] = $actions;
        }

        return self::$grants[$user->id] = $grants;
    }

    /**
     * Drop the memo for a user — call after writing their grants so the same request
     * sees the new values.
     */
    public static function forget(?int $userId = null): void
    {
        if ($userId === null) {
            self::$grants = [];
            self::$viewable = [];

            return;
        }

        unset(self::$grants[$userId], self::$viewable[$userId]);
    }

    /** @return array<string, true> */
    private static function viewable(User $user): array
    {
        if (isset(self::$viewable[$user->id])) {
            return self::$viewable[$user->id];
        }

        $names = [];

        foreach (ModuleName::listFrom($user->assign_role) as $name) {
            $names[$name] = true;
        }

        return self::$viewable[$user->id] = $names;
    }

    /**
     * The gate is deployed before the migration is run on some environments; a missing
     * table must not 500 every page. Checked once per request.
     */
    private static function tableExists(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }

        try {
            return self::$tableExists = Schema::connection('sqlsrv')->hasTable('module_permissions');
        } catch (\Throwable $e) {
            return self::$tableExists = false;
        }
    }
}
