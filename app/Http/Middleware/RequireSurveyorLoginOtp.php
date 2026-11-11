<?php

namespace App\Http\Middleware;

use App\Services\Survey\SurveyorLoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The backstop for the MLPP-SG sign-in code (user, 2026-11-10), the twin of RequireLaasLoginOtp.
 *
 * AuthController holds an account at the code screen before signing it in, but the
 * `surveyor` guard is also entered by a remember-me cookie, and sessions signed in before
 * the code went live never typed one. Such a session is signed out and sent to the code
 * screen, keeping the page it wanted. Route middleware after auth:surveyor.
 */
class RequireSurveyorLoginOtp
{
    public function __construct(private SurveyorLoginOtpService $otp)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $account = Auth::guard('surveyor')->user();

        if (!$account || $this->otp->hasPassed($request, $account) || !$this->otp->requiredFor($account)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        Auth::guard('surveyor')->logout();
        $result = $this->otp->begin($request, $account, false);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter the sign-in code sent to you to continue.',
                'login_otp_required' => true,
                'redirect' => route('surveyor-portal.login.otp'),
            ], 401);
        }

        return redirect()->route('surveyor-portal.login.otp')->with($result['sent'] ? 'status' : 'error', $result['message']);
    }
}
