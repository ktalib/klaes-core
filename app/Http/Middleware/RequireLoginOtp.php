<?php

namespace App\Http\Middleware;

use App\Services\LoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * The backstop for the sign-in code.
 *
 * The main login form holds an account at the code screen before it is ever signed in
 * (AuthenticatedSessionController). But the same `web` session is also reached by the
 * mobile login screens (MobileController, ValuationMobileAuthController, the special
 * assignment mobile login) and by a remember-me cookie, none of which asks for a code.
 *
 * So: a signed-in account that needs a code, in a session whose sign-in has not met the
 * policy, is signed out again and sent to the code screen, keeping the page it wanted.
 * Accounts that need no code, and sessions that already passed, go straight through.
 */
class RequireLoginOtp
{
    public function __construct(private LoginOtpService $otp)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('web')->user();

        // A borrowed session (ImpersonationController) is never challenged. The code proves
        // who is at the keyboard, and the Super Admin holding this session proved that at
        // their own sign-in; a code texted to the borrowed account's handset would only ask
        // its owner to authorise a session they are not sitting at.
        if ($user && app(ImpersonateManager::class)->isImpersonating()) {
            return $next($request);
        }

        if (!$user || $this->otp->hasPassed($request, $user) || !$this->otp->requiredFor($user)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        Auth::guard('web')->logout();
        $result = $this->otp->begin($request, $user, false);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => __('Enter the sign-in code sent to your phone to continue.'),
                'login_otp_required' => true,
                'redirect' => route('login.otp'),
            ], 401);
        }

        return redirect()->route('login.otp')
            ->with($result['sent'] ? 'success' : 'error', $result['message']);
    }
}
