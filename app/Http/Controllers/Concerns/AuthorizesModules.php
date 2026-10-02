<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;

/**
 * Module authorization for controllers.
 *
 * The middleware covers anything reachable by a mapped route name. This is for the rest: the
 * AJAX endpoints the modals post to, controllers that fan several actions through one route,
 * and the places where which module applies depends on the record being touched.
 *
 * It replaces a pattern copy-pasted through 44 controllers — a local private method comparing
 * lowercased assignedRoleNames() against a hard-coded list, each copy with its own idea of how
 * to spell "Supper Admin". See SmsControlController::authoriseControl() for the archetype.
 */
trait AuthorizesModules
{
    /**
     * Abort 403 unless the signed-in user may perform $action on $module.
     */
    protected function authorizeModule(string $module, string $action = 'view'): void
    {
        abort_unless(
            $this->userCanDo($module, $action),
            403,
            __('You do not have permission to :action in :module.', ['action' => $action, 'module' => $module])
        );
    }

    /**
     * Abort 403 unless the user may perform at least one of $actions on $module. For a screen
     * that is worth opening if the user can do any of several things on it.
     *
     * @param  array<int, string>  $actions
     */
    protected function authorizeAnyModuleAction(string $module, array $actions): void
    {
        foreach ($actions as $action) {
            if ($this->userCanDo($module, $action)) {
                return;
            }
        }

        abort(403, __('You do not have permission to open :module.', ['module' => $module]));
    }

    /**
     * Non-aborting check, for deciding what to include in a response.
     */
    protected function userCanDo(string $module, string $action = 'view'): bool
    {
        $user = Auth::user();

        return $user !== null && $user->canDo($module, $action);
    }
}
