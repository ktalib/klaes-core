<?php

namespace App\Http\Middleware;

use App\Support\Permissions\ModuleRoutes;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Route-level enforcement of module permissions.
 *
 * This closes the hole the app shipped with: there were zero uses of `can:` middleware across
 * roughly 1,040 mutating routes, so authorization was the sidebar declining to draw a link.
 * Anyone who knew a URL could create, edit, delete or print in any module.
 *
 * Modelled on RequireProfilePhoto, the existing global web gate: scoped to the `web` guard,
 * an exempt list so an account can still reach auth and profile screens, a JSON branch for the
 * AJAX modals, and a redirect-with-error for ordinary page loads.
 *
 * ROLLOUT POSTURE. An unmapped route is allowed through and recorded, not denied. That is
 * deliberate and it is the only responsible default here: the map covers route-name prefixes,
 * there are 1,982 named routes, and this runs against a live land registry with no backups. A
 * mistake in fail-closed mode is a department that cannot work; in fail-open mode it is a line
 * in a log. `php artisan permissions:unmapped` turns those lines into the missing map entries,
 * and config('module_permissions.strict_routes') flips the posture once it goes quiet.
 *
 * Mapped routes are enforced from the first request — the point is to start closing the hole
 * where the mapping is known, not to wait for perfection.
 */
class EnforceModulePermission
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('module_permissions.enabled', true)) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        // Unauthenticated traffic is the auth middleware's business, not this gate's.
        if (!$user) {
            return $next($request);
        }

        // A Supper Admin holds the whole system; skip the lookup entirely.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $decision = ModuleRoutes::decide($request);

        if ($decision === null) {
            return $next($request);
        }

        [$module, $action] = $decision;

        if ($user->canDo($module, $action)) {
            return $next($request);
        }

        // Record it either way: a refusal that is only logged is still the signal that the
        // map needs correcting, and the log is the only reason we know about the two that
        // reached production.
        $this->recordDenial($request, $user, $module, $action);

        /*
         | Enforce only the actions config says are ready. See 'enforced_actions' -- the map
         | assigns a module by route-name prefix, which misfiles every endpoint that one
         | module borrows from another, and those misfilings refuse people doing their job.
         | Until the denial log is quiet for an action, it is observed rather than applied.
        */
        if (!in_array($action, (array) config('module_permissions.enforced_actions', ['delete']), true)) {
            return $next($request);
        }

        return $this->deny($request, $module, $action);
    }

    /**
     * Record every refusal.
     *
     * Added after one reached production unnoticed: propertycard.search is a POST, the verb
     * fallback read it as "create", and officers searching a property card were told they
     * could not create in Deeds - Property Records. Nothing logged it, so the first anyone
     * knew was a user reporting the message.
     *
     * A refusal is either a permission working or a mapping bug, and the two are
     * indistinguishable from the outside — so both are written down and
     * `permissions:denials` reads them back.
     */
    private function recordDenial(Request $request, $user, string $module, string $action): void
    {
        Log::warning('[module-permissions] denied', [
            'user' => $user->username ?? $user->id,
            'user_id' => $user->id,
            'module' => $module,
            'action' => $action,
            'route' => optional($request->route())->getName() ?: '(unnamed)',
            'path' => $request->path(),
            'method' => $request->method(),
        ]);
    }

    private function deny(Request $request, string $module, string $action)
    {
        $message = __('You do not have permission to :action in :module.', [
            'action' => $action,
            'module' => $module,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'permission_denied' => true,
                'module' => $module,
                'action' => $action,
            ], 403);
        }

        // Back to where they were, rather than a bare 403 page — the control they clicked
        // should not have been there, and the flash message says why it did nothing.
        return redirect()->back()->with('error', $message);
    }
}
