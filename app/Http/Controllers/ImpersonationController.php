<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Services\LoginOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * "Login as user": a Super Admin borrows another account's session to see the system
 * exactly as that officer sees it -- their modules, their dashboard, their file lists --
 * without knowing their password.
 *
 * NO SIGN-IN CODE IS SENT. The second factor proves who is at the keyboard, and that was
 * already proved when the Super Admin signed in; texting a code to the officer's handset
 * would only ask the officer to authorise a session they are not sitting at. The same
 * reasoning covers the other account gates -- 2FA, the passport photo, the mobile-number
 * card: the four middlewares that enforce them step aside while a session is borrowed, so
 * the admin is never held on another person's setup card (and so can never complete it
 * for them by accident). See RequireLoginOtp, Verify2FA, RequireProfilePhoto,
 * RequirePhoneVerification.
 *
 * What that buys has to be paid for at the door, so entry is Super Admin only -- not
 * 'User Account' edit, which a good many desk supervisors hold. Every take and leave is
 * written to the log with both ids.
 *
 * The session swap itself is lab404/laravel-impersonate: it keeps the impersonator's id
 * in the session (`impersonated_by`) and hands it back on leave, so the admin returns to
 * their own account rather than being signed out.
 */
class ImpersonationController extends Controller
{
    public function __construct(
        private ImpersonateManager $manager,
        private LoginOtpService $otp,
    ) {
    }

    /** Borrow $user's session. */
    public function take(Request $request, User $user)
    {
        $admin = $request->user();

        abort_unless($admin instanceof User && $admin->isSuperAdmin(), 403, __('Only a Super Admin may sign in as another user.'));

        if ($this->manager->isImpersonating()) {
            return redirect()->route('users.index')->with('error', __('You are already signed in as another user. Return to your own account first.'));
        }

        if ((int) $user->id === (int) $admin->id) {
            return redirect()->route('users.index')->with('error', __('You are already signed in as yourself.'));
        }

        // A suspended account cannot sign in at the login form; borrowing it must not be
        // the way around that.
        if ((int) ($user->is_active ?? 1) === 0) {
            return redirect()->route('users.index')->with('error', __(':name is suspended. Reactivate the account before signing in as them.', ['name' => $user->name]));
        }

        if (!$this->manager->take($admin, $user, 'web')) {
            return redirect()->route('users.index')->with('error', __('Could not sign in as :name. Try again.', ['name' => $user->name]));
        }

        // The borrowed session has met the sign-in policy by the admin's own sign-in. Said
        // in the session as well as in the middlewares, because after leave() the flag
        // must be right for whoever is holding the session then.
        $this->otp->markPassed($request, $user);

        Log::warning('Impersonation started', [
            'impersonator_id' => $admin->id,
            'impersonator' => $admin->username,
            'user_id' => $user->id,
            'username' => $user->username,
            'ip' => $request->ip(),
        ]);

        return redirect()->to(RouteServiceProvider::HOME)->with('success', __('You are now signed in as :name. Use "Return to my account" to come back.', ['name' => $user->name]));
    }

    /** Hand the session back to the impersonator. */
    public function leave(Request $request)
    {
        abort_unless($this->manager->isImpersonating(), 403, __('You are not signed in as another user.'));

        $impersonated = $request->user();
        $impersonatorId = $this->manager->getImpersonatorId();

        if (!$this->manager->leave()) {
            // Neither account is reliably in the session now; the safe end is the login form.
            Log::error('Impersonation could not be left; session signed out', [
                'impersonator_id' => $impersonatorId,
                'user_id' => $impersonated?->id,
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('Your session could not be handed back. Sign in again.'));
        }

        $admin = $request->user();

        // Without this the returning admin is challenged for a code by RequireLoginOtp:
        // the session's "passed" marker still names the account just left.
        if ($admin instanceof User) {
            $this->otp->markPassed($request, $admin);
        }

        Log::warning('Impersonation ended', [
            'impersonator_id' => $impersonatorId,
            'user_id' => $impersonated?->id,
            'username' => $impersonated?->username,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('users.index')->with('success', __('You are back on your own account.'));
    }
}
