<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Holds an MLPP-SG login on the password screen until the temporary password sent by SMS and email has been
 * replaced (company logins, user 2026-11-10; the RequireLaasPasswordChange pattern). Runs after surveyor.otp and
 * surveyor.questions, so the sign-in code and security questions still come first.
 */
class RequireSurveyorPasswordChange
{
    /** Routes a held login may still reach. */
    private const ALLOWED = [
        'surveyor-portal.password.edit',
        'surveyor-portal.password.change',
        'surveyor-portal.logout',
        'surveyor-portal.security-questions.edit',
        'surveyor-portal.security-questions.update',
    ];

    public function handle(Request $request, Closure $next)
    {
        $account = Auth::guard('surveyor')->user();

        if (! $account || ! $account->must_change_password || in_array($request->route()?->getName(), self::ALLOWED, true)) {
            return $next($request);
        }
        // The security questions come first: they hold the login on the dashboard, and sending it from there to the
        // password page looped between the two (2026-11-10). Once they are set, this takes over.
        if (app(\App\Services\Survey\SurveyorSecurityQuestions::class)->owesAnswers($request, $account)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Choose a new password to continue.', 'redirect' => route('surveyor-portal.password.edit')], 403);
        }

        return redirect()->route('surveyor-portal.password.edit')
            ->with('status', 'Welcome. Please choose a new password before you continue — the one that was sent to you was temporary.');
    }
}
