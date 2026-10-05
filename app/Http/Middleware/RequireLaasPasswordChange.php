<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Holds an applicant on the password screen until the temporary password from
 * the commissioning SMS has been replaced.
 *
 * Accounts opened by File Commissioning are sent their password by text, so it
 * has passed through the SMS gateway and sits in the phone's inbox. The flag is
 * set when the account is made (LaasCommissioningAccountService) and cleared by
 * LaasProfileController::updatePassword(). Self-registered accounts never carry
 * it and pass straight through.
 *
 * Runs after `auth:laas` and `laas.otp`, so the sign-in code is still asked for
 * first.
 */
class RequireLaasPasswordChange
{
    /** Routes a held applicant may still reach. */
    private const ALLOWED = [
        'laas.profile.show',
        'laas.profile.password',
        'laas.logout',
        'alaes_portal.logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        $applicant = Auth::guard('laas')->user();

        if (!$applicant || !$applicant->must_change_password
            || in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => false,
                'message'  => 'Choose a new password to continue.',
                'redirect' => route('laas.profile.show'),
            ], 403);
        }

        return redirect()->route('laas.profile.show')
            ->with('error', 'Welcome. Please choose a new password before you continue — the one sent by SMS was temporary.');
    }
}
