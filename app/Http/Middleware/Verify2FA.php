<?php

namespace App\Http\Middleware;

use App\Services\LoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class Verify2FA
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('phs*')) {
            return $next($request);
        }

        // The STAFF guard, never Auth::user(). `auth:laas` / `auth:phs` are sorted ahead
        // of this middleware by $middlewarePriority (Authenticate implements
        // AuthenticatesRequests), and they call shouldUse(), so on a portal route the
        // default guard is the portal's and Auth::user() is a LaasApplicant, not a User.
        // Every other gate in the `web` group already reads guard('web') for this reason.
        $user = Auth::guard('web')->user();

        // Not authenticated => no need to check
        if (!$user) {
            return $next($request);
        }

        if (LoginOtpService::isOtpExempt($user)) {
            return $next($request);
        }

        // Borrowed session: the second factor belongs to the account's owner, who is not
        // the one holding this session. See ImpersonationController.
        if (app(\Lab404\Impersonate\Services\ImpersonateManager::class)->isImpersonating()) {
            return $next($request);
        }

        // 2FA not enabled => no need to check
        if (is_null($user->twofa_secret)) {
            return $next($request);
        }

        // 2FA is already checked
        if (session("2fa_checked", false)) {
            return $next($request);
        }

        // at this point user must provide a valid OTP
        // but we must avoid an infinite loop
        if (request()->is('login/otp')) {
            return $next($request);
        }

        return redirect()->route('otp.show');




    }
}
