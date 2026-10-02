<?php

namespace App\Support\Permissions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resolves a request to the (module, action) pair the gate should check.
 *
 * Separated from the middleware so the same answer can be obtained without dispatching a
 * request — `permissions:audit` walks the whole route table through decideFor() to show what
 * the map covers before any of it is enforced.
 */
final class ModuleRoutes
{
    /** Route names already logged as unmapped this process, so one page does not spam. */
    private static array $loggedUnmapped = [];

    /** Memo for approvableModules(); null until first built. */
    private static ?array $approvable = null;

    /**
     * What must be authorized for this request, or null to let it through.
     *
     * @return array{0: string, 1: string}|null  [module, action]
     */
    public static function decide(Request $request): ?array
    {
        $route = $request->route();

        if (!$route) {
            return null;
        }

        $name = (string) $route->getName();
        $method = strtoupper($request->method());

        if (self::isExempt($request, $name)) {
            return null;
        }

        $module = self::moduleFor($name);

        if ($module === null) {
            self::recordUnmapped($name, $request->path(), $method);

            // Fail-open unless strict mode is on; then only mutating verbs are denied,
            // because a stray unmapped GET is a reporting screen, not a write.
            if (config('module_permissions.strict_routes', false) && !self::isReadOnly($method)) {
                return ['*unmapped*', self::actionFor($name, $method)];
            }

            return null;
        }

        return [$module, self::actionFor($name, $method)];
    }

    /**
     * Same decision for a route name/verb pair with no live request — used by the audit
     * command.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function decideFor(string $name, string $method = 'GET'): ?array
    {
        if (self::isExemptName($name)) {
            return null;
        }

        $module = self::moduleFor($name);

        return $module === null ? null : [$module, self::actionFor($name, strtoupper($method))];
    }

    /**
     * Is this route name exempt from the gate entirely?
     *
     * Exposed because "exempt" and "unmapped" both make decide() return null, and the
     * reporting commands must not confuse the two: logout and password.* are deliberately
     * ungated, and listing them as gaps in the map would send someone hunting for a module
     * name that should never exist.
     */
    public static function isExemptName(string $name): bool
    {
        return self::matchesAny($name, config('module_permissions.exempt_routes', []));
    }

    /** The module a route name belongs to, or null when the map does not cover it. */
    public static function moduleFor(string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        foreach ((array) config('module_permissions.modules', []) as $pattern => $module) {
            if (Str::is($pattern, $name)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * Which action a route performs.
     *
     * Name first, verb second. The name is the better signal here because destructive work is
     * routinely posted rather than DELETEd in this codebase — 805 POST routes against 131
     * DELETE ones — so a verb-first reading would file most deletes as "create".
     */
    public static function actionFor(string $name, string $method = 'GET'): string
    {
        $overrides = (array) config('module_permissions.overrides', []);

        if (isset($overrides[$name])) {
            return $overrides[$name];
        }

        $lower = strtolower($name);

        foreach ((array) config('module_permissions.action_patterns', []) as $action => $patterns) {
            foreach ((array) $patterns as $pattern) {
                if (Str::is($pattern, $lower)) {
                    return $action;
                }
            }
        }

        return match (strtoupper($method)) {
            'DELETE' => 'delete',
            'PUT', 'PATCH' => 'edit',
            'POST' => 'create',
            default => 'view',
        };
    }

    /**
     * Module names that have an approve/reject route, as normalized keys.
     *
     * Most modules have nothing to approve, so offering the column on all 168 of them would be
     * 168 controls that mean nothing. Derived from the route table rather than a hand-kept
     * list: a module is approvable when something in it can actually be approved.
     *
     * Module names carrying "approval"/"approve" are included too — screens like
     * "ST - Approvals" and "Survey - Approvals" exist to approve, whether or not their routes
     * are mapped in config yet.
     *
     * Memoized per process; the route table does not change within a request.
     *
     * @return array<string, true>
     */
    public static function approvableModules(): array
    {
        if (self::$approvable !== null) {
            return self::$approvable;
        }

        $found = [];

        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            $name = (string) $route->getName();

            if ($name === '' || self::actionFor($name, 'POST') !== 'approve') {
                continue;
            }

            if ($module = self::moduleFor($name)) {
                $found[ModuleName::normalize($module)] = true;
            }
        }

        return self::$approvable = $found;
    }

    /**
     * Is this module one where approving is meaningful?
     */
    public static function isApprovable(string $module): bool
    {
        $key = ModuleName::normalize($module);

        if ($key === '') {
            return false;
        }

        // A module that exists to approve says so in its own name.
        if (str_contains($key, 'approv')) {
            return true;
        }

        return isset(self::approvableModules()[$key]);
    }

    public static function isReadOnly(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private static function isExempt(Request $request, string $name): bool
    {
        $paths = (array) config('module_permissions.exempt_paths', []);

        if ($paths && $request->is(...$paths)) {
            return true;
        }

        return self::isExemptName($name);
    }

    private static function matchesAny(string $name, $patterns): bool
    {
        if ($name === '') {
            return false;
        }

        foreach ((array) $patterns as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Note a route the map does not cover.
     *
     * Only mutating routes are worth reporting — an unmapped GET is usually a dashboard or a
     * lookup, and logging every one of those would bury the writes that actually need a
     * decision. Written at info level to a dedicated channel-free line that
     * `permissions:unmapped` greps back out.
     */
    private static function recordUnmapped(string $name, string $path, string $method): void
    {
        if (self::isReadOnly($method)) {
            return;
        }

        $key = $method . ' ' . ($name !== '' ? $name : $path);

        if (isset(self::$loggedUnmapped[$key])) {
            return;
        }

        self::$loggedUnmapped[$key] = true;

        Log::info('[module-permissions] unmapped route', [
            'route' => $name !== '' ? $name : '(unnamed)',
            'path' => $path,
            'method' => $method,
        ]);
    }
}
