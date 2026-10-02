<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsurePhsSuperAdmin
{
    /**
     * Allow only PHS members with the super_admin user_type through.
     * Used to guard the institution Organization/User Management console.
     */
    public function handle(Request $request, Closure $next)
    {
        $member = Auth::guard('phs')->user();

        if (! $member || ! $member->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only organization administrators can perform this action.',
                ], 403);
            }
            abort(403, 'Only organization administrators can access this page.');
        }

        return $next($request);
    }
}
