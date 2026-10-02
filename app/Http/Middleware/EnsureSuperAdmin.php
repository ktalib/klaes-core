<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Restricts a route to Super Admins on the default (web) guard.
 *
 * NOT the same thing as EnsurePhsSuperAdmin, which authorises a PHS institution
 * member on the separate `phs` guard. That one cannot be reused here: it reads
 * Auth::guard('phs'), which is null for an ordinary KLAES session, so every
 * KLAES user — including a real Super Admin — would be refused.
 *
 * Super Admin is decided by User::isSuperAdmin(), which is the single place that
 * knows all three ways this system records one: users.type, the assigned role,
 * and the "supper admin" spelling that exists alongside "super admin". Do not
 * re-test those strings here; they have drifted before.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if (!$user || !method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This module is restricted to system administrators.',
                ], 403);
            }

            abort(403, 'This module is restricted to system administrators.');
        }

        return $next($request);
    }
}
