<?php

namespace App\Http\Middleware;

use App\Services\Phs\PhsLoginOtpService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The backstop for the PHS Portal sign-in code.
 *
 * PhsAuthController holds an account at the code screen before it is ever signed
 * in. But the `phs` guard is also entered by a REMEMBER-ME cookie, which asks
 * for nothing: tick "Remember me", come back next week, and the second factor is
 * simply skipped. Without this the code would only be a gate on the days a
 * member happened to type their password.
 *
 * So: a signed-in member in a session whose sign-in has not met the policy is
 * signed out again and sent to the code screen, keeping the page they wanted.
 * Members that need no code, and sessions that already passed, go straight
 * through.
 *
 * The applicant twin of this is RequireLaasLoginOtp, and the staff one is
 * RequireLoginOtp. It runs as ROUTE middleware after `auth:phs` rather than in
 * the `web` group, because the group runs on every staff page too and this has
 * nothing to say about those.
 */
class RequirePhsLoginOtp
{
    public function __construct(private PhsLoginOtpService $otp)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $member = Auth::guard('phs')->user();

        if (!$member
            || $this->otp->hasPassed($request, $member)
            || !$this->otp->requiredFor($member)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && !$request->expectsJson()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        Auth::guard('phs')->logout();
        $result = $this->otp->begin($request, $member, false);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Enter the sign-in code sent to your email to continue.',
                'login_otp_required' => true,
                'redirect' => route('phs.login.otp'),
            ], 401);
        }

        return redirect()->route('phs.login.otp')
            ->with($result['sent'] ? 'status' : 'error', $result['message']);
    }
}
