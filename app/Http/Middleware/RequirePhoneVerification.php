<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A confirmed mobile number is mandatory: until the signed-in user has proved theirs
 * by typing back a code sent to it, the only things they may reach are the landing
 * page that carries the confirmation card, the two endpoints that card talks to, and
 * logout. Greying out the sidebar is not enough on its own — without this the
 * requirement is bypassed by typing a URL.
 *
 * SIGNING IN IS NOT BLOCKED, on purpose. Roughly three quarters of accounts carry no
 * usable number at all, and the fix for that is the user typing a real one into the
 * card — which they can only do from inside. A gate at the login form would lock out
 * precisely the people it is meant to reach.
 *
 * Runs immediately after RequireProfilePhoto and defers to it (see
 * User::getNeedsPhoneVerificationAttribute), so a user never faces two blocking cards
 * at once. Only the `web` guard is gated; the public portals (laas, phs, ols) run on
 * their own guards and are untouched.
 */
class RequirePhoneVerification
{
    /**
     * Routes that must stay reachable while the account is held, otherwise the user
     * could neither satisfy the requirement nor sign out.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'home',
        'dashboard',
        'logout',
        'login',
        'markWelcomePopupShown',
        // The card itself: ask for a code, and hand one back.
        'phone.verification.send',
        'phone.verification.verify',
        // The photo gate sits in front of this one; its endpoints have to stay open
        // so a user cannot be held here while still owing a picture.
        'profile.picture.store',
        'profile.picture.face-check',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        // Borrowed session: confirming a number means typing back a code sent to the
        // account owner's handset, which the admin holding this session does not have.
        // See ImpersonationController.
        if ($user && app(\Lab404\Impersonate\Services\ImpersonateManager::class)->isImpersonating()) {
            return $next($request);
        }

        if (!$user || !$user->needs_phone_verification) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $message = __('Confirm your mobile number to continue using the system.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'phone_verification_required' => true,
            ], 403);
        }

        // Land on the page that carries the confirmation card. Unlike the photo card
        // there is no "open it" flash to pass along: this one has no dismiss button
        // and opens itself whenever it renders, because there is nothing else the
        // account can do until it is satisfied.
        return redirect()
            ->route('home')
            ->with('error', $message);
    }

    private function isAllowed(Request $request): bool
    {
        // Public portals run on their own guards; a staff member browsing one is not
        // "using the system" in the sense this gate is about.
        if ($request->is('laas', 'laas/*', 'phs', 'phs/*', 'online-legal-search', 'online-legal-search/*')) {
            return true;
        }

        $routeName = optional($request->route())->getName();

        if ($routeName && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        // Logout is a POST on some layouts and a GET link on others.
        return $request->is('logout', 'login', 'home', 'dashboard');
    }
}
