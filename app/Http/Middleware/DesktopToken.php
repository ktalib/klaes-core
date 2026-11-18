<?php

namespace App\Http\Middleware;

use App\Services\Desktop\DesktopAccess;
use Closure;
use Illuminate\Http\Request;

/**
 * After auth:sanctum on /api/desktop/v1: the token must be a desktop token, under 30 days old, its account active and
 * still allowed to use the app (DesktopAccess). Anything else is a 401 "sign in again" (or 403 for a lost role).
 */
class DesktopToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if (! $user || ! $token || ! $token->can(DesktopAccess::ABILITY)) {
            return response()->json(['message' => 'Sign in to KLAES from the desktop app.'], 401);
        }
        if ($token->created_at && $token->created_at->lt(now()->subDays(DesktopAccess::TOKEN_DAYS))) {
            $token->delete();

            return response()->json(['message' => 'Your sign-in on this PC has ended after ' . DesktopAccess::TOKEN_DAYS . ' days. Sign in again.'], 401);
        }
        if ((string) $user->is_active === '0') {
            $token->delete();

            return response()->json(['message' => 'Your account is inactive. Contact your administrator.'], 401);
        }
        if (! DesktopAccess::allowed($user)) {
            return response()->json(['message' => 'Your account does not have Survey Plan Management, which the desktop app needs.'], 403);
        }

        return $next($request);
    }
}
