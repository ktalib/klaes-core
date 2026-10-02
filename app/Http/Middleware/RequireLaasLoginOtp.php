<?php

namespace App\Http\Middleware;

use App\Services\Laas\LaasLoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The backstop for the LAAS Portal sign-in code.
 *
 * LaasAuthController holds an account at the code screen before it is ever
 * signed in. But the `laas` guard is also entered by a REMEMBER-ME cookie, which
 * asks for nothing: tick "Keep me signed in", come back next week, and the
 * second factor is simply skipped. Without this the code would only be a gate on
 * the days an applicant happened to type their password.
 *
 * So: a signed-in applicant in a session whose sign-in has not met the policy is
 * signed out again and sent to the code screen, keeping the page they wanted.
 * Accounts that need no code, and sessions that already passed, go straight
 * through.
 *
 * This is the applicant twin of RequireLoginOtp. It runs as ROUTE middleware
 * after `auth:laas` rather than in the `web` group, because the group runs on
 * every staff page too and this has nothing to say about those.
 */
class RequireLaasLoginOtp
{
    public function __construct(private LaasLoginOtpService $otp)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $applicant = Auth::guard('laas')->user();

        if (!$applicant
            || $this->otp->hasPassed($request, $applicant)
            || !$this->otp->requiredFor($applicant)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        $portal = $request->is('alaes-portal*')
            ? LaasLoginOtpService::PORTAL_ALAES
            : LaasLoginOtpService::PORTAL_LAAS;

        Auth::guard('laas')->logout();
        $result = $this->otp->begin($request, $applicant, false, $portal);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter the sign-in code sent to you to continue.',
                'login_otp_required' => true,
                'redirect' => route('laas.login.otp'),
            ], 401);
        }

        return redirect()->route('laas.login.otp')
            ->with($result['sent'] ? 'status' : 'error', $result['message']);
    }
}
