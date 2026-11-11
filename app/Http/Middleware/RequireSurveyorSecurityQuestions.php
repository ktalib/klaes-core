<?php

namespace App\Http\Middleware;

use App\Services\Survey\SurveyorSecurityQuestions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * MLPP-SG security questions are mandatory (user, 2026-11-10): until the signed-in surveyor
 * has set them, only the dashboard (which carries the card that cannot be closed), the
 * endpoint it saves to, and sign out are reachable. Holds only sessions that signed in
 * after it went live (AuthController::completeLogin sets the check flag).
 */
class RequireSurveyorSecurityQuestions
{
    private const ALLOWED_ROUTE_NAMES = [
        'surveyor-portal.dashboard',
        'surveyor-portal.security-questions.update',
        'surveyor-portal.logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!app(SurveyorSecurityQuestions::class)->owesAnswers($request, Auth::guard('surveyor')->user())
            || in_array(optional($request->route())->getName(), self::ALLOWED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        $message = 'Set your security questions to continue using the portal.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message, 'security_questions_required' => true], 403);
        }

        // The dashboard opens the card by itself while the questions are owed.
        return redirect()->route('surveyor-portal.dashboard');
    }
}
