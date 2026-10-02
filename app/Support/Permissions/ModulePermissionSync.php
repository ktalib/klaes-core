<?php

namespace App\Support\Permissions;

use App\Models\ModulePermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes the permission grid back from the Create/Edit User modals.
 *
 * Kept out of UserController because both store() and update() need it and because the two
 * safety rules below are easy to lose in a 1,000-line controller:
 *
 *   - A module the user cannot see cannot carry actions. The grid enforces this in the
 *     browser; doing it again here means a hand-crafted POST cannot store grants that the gate
 *     would never honour but that a later reader might mistake for real access.
 *
 *   - Delete is only ever written by someone allowed to hand it out. Without that check, any
 *     account that can edit users can grant itself deletion of every record in the ministry —
 *     the grid disables the column, and a disabled checkbox is a suggestion, not a control.
 */
final class ModulePermissionSync
{
    /**
     * Replace a user's grants with what the modal posted.
     *
     * @param  array<string, array<int, string>>  $posted  module name => [actions]
     * @param  array<int, string>  $viewableModules  module names the user was granted (assign_role)
     */
    public static function sync(User $user, array $posted, array $viewableModules, bool $canGrantDelete): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('module_permissions')) {
            return;
        }

        $viewable = [];
        foreach ($viewableModules as $name) {
            $key = ModuleName::normalize($name);
            if ($key !== '' && !ModuleName::isSuperAdminGrant($key)) {
                $viewable[$key] = $name;
            }
        }

        $rows = [];
        $now = now();

        foreach ($posted as $moduleName => $actions) {
            $key = ModuleName::normalize($moduleName);

            // Actions on a module the user was not granted are dropped, not stored.
            if ($key === '' || !isset($viewable[$key])) {
                continue;
            }

            $actions = array_map('strval', (array) $actions);

            $row = [
                'user_id' => $user->id,
                'module_name' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $approvable = ModuleRoutes::isApprovable($moduleName);

            foreach (ModulePermission::ACTIONS as $action) {
                $granted = in_array($action, $actions, true);

                if ($action === 'delete' && !$canGrantDelete) {
                    $granted = false;
                }

                // Approve is only meaningful where the module has an approval step. Storing it
                // elsewhere would leave a grant that reads as real authority and can never
                // apply -- the grid hides the column, and a hand-crafted POST does not get to
                // write what the grid refuses to offer.
                if ($action === 'approve' && !$approvable) {
                    $granted = false;
                }

                $row['can_' . $action] = $granted ? 1 : 0;
            }

            // The module is viewable, so view is true whatever the checkbox said.
            $row['can_view'] = 1;

            $rows[$key] = $row;
        }

        /*
         | A viewable module with nothing posted still needs a row, or the gate falls back to
         | "no row yet, allow everything but delete" and the administrator's decision to
         | withhold create/edit silently does nothing.
         */
        foreach ($viewable as $key => $original) {
            if (isset($rows[$key])) {
                continue;
            }

            // Built from ACTIONS rather than a literal column list: the hardcoded version
            // silently omitted can_approve the moment that column was added, and the next
            // action added would have gone the same way.
            $row = [
                'user_id' => $user->id,
                'module_name' => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach (ModulePermission::ACTIONS as $action) {
                $row['can_' . $action] = $action === 'view' ? 1 : 0;
            }

            $rows[$key] = $row;
        }

        $conn = DB::connection('sqlsrv');

        $conn->transaction(function () use ($conn, $user, $rows) {
            // Replace wholesale: the modal posts the user's complete intended state, so a
            // module missing from it was revoked. Diffing would leave orphans behind whenever
            // a module is renamed or dropped from the registry.
            $conn->table('module_permissions')->where('user_id', $user->id)->delete();

            foreach (array_chunk(array_values($rows), 200) as $chunk) {
                $conn->table('module_permissions')->insert($chunk);
            }
        });

        ModulePermissions::forget($user->id);
    }

    /**
     * A user's stored grants shaped for the grid: [module name => [action => bool]].
     *
     * @return array<string, array<string, bool>>
     */
    public static function gridFor(User $user): array
    {
        if (!Schema::connection('sqlsrv')->hasTable('module_permissions')) {
            return [];
        }

        return ModulePermissions::grants($user);
    }

    /**
     * May the signed-in account hand out the delete permission?
     *
     * Restricted to Super Admins on purpose. Delete is the one action that destroys registry
     * records, and this system has no backups to undo a mistake with — so the ability to grant
     * it is not something an ordinary user administrator should hold.
     */
    public static function canGrantDelete(?User $editor): bool
    {
        return $editor !== null && $editor->isSuperAdmin();
    }
}
