<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * MLPP-SG portal pages for one kind of login (user, 2026-11-10): `surveyor.kind:surveyor` for a surveyor's own
 * pages (requests, reports, plans, maps), `surveyor.kind:company` for a company's. Route middleware after
 * auth:surveyor. The other kind is sent to its own dashboard.
 */
class EnsureSurveyorPortalKind
{
    public function handle(Request $request, Closure $next, string $kind)
    {
        $account = Auth::guard('surveyor')->user();
        $isCompany = $account && $account->isCompany();

        if (! $account || ($kind === 'company') === $isCompany) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'This page is not part of your portal.'], 403);
        }

        return redirect()->route('surveyor-portal.dashboard')->with('status', 'That page is not part of your portal.');
    }
}
