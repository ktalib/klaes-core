<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A passport photo is mandatory: until the signed-in user has one, the only things
 * they may reach are the landing page that carries the upload card, the upload
 * endpoint itself, and logout. Greying out the sidebar is not enough on its own —
 * without this the requirement is bypassed by typing a URL.
 *
 * "Has one" means a photograph of the person's own face, not merely a file: an account
 * carrying a stock cartoon avatar is held exactly like an account carrying nothing,
 * because neither identifies anybody on a file or a request. The judgment is made in
 * the browser and stored on the user row (users.photo_face_status) — see
 * User::getNeedsProfilePhotoAttribute(). A picture nobody has checked yet is let
 * through: the gate closes on a verdict arriving, never on the absence of one, so a
 * detector that will not load cannot lock the building.
 *
 * Only the `web` guard is gated; the public portals (laas, phs, ols) run on their
 * own guards and are untouched.
 */
class RequireProfilePhoto
{
    /**
     * Routes that must stay reachable while the account is locked, otherwise the
     * user could neither satisfy the requirement nor sign out.
     */
    private const ALLOWED_ROUTE_NAMES = [
        'home',
        'dashboard',
        'profile.picture.store',
        // The browser reports its face-check verdict here. It must stay open even to a
        // locked account: a user held on a rejected picture still needs to be able to
        // report the verdict on the replacement they upload.
        'profile.picture.face-check',
        'logout',
        'login',
        'markWelcomePopupShown',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        // A Super Admin looking through someone else's account is not the person who owes
        // the photograph, and must not be able to supply one on their behalf. The card is
        // for the account's owner, at their own sign-in. See ImpersonationController.
        if ($user && app(\Lab404\Impersonate\Services\ImpersonateManager::class)->isImpersonating()) {
            return $next($request);
        }

        if (!$user || !$user->needs_profile_photo) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $message = $user->photo_face_rejected
            ? __('The picture on your account is not a photograph of a face. Upload your own passport photo to continue using the system.')
            : __('Upload your profile picture to continue using the system.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'profile_photo_required' => true,
            ], 403);
        }

        // Land on the page that carries the upload card, with it opened for them.
        return redirect()
            ->route('home')
            ->with('open_profile_photo_card', true)
            ->with('error', $message);
    }

    private function isAllowed(Request $request): bool
    {
        // Public portals run on their own guards; a staff member browsing one is
        // not "using the system" in the sense this gate is about.
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
