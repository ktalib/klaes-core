<?php

namespace App\Http\Middleware;

use App\Services\SecurityQuestionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Security questions are mandatory: until the signed-in user has set their answers, the
 * only things they may reach are the dashboard, which carries the set-up card
 * (profile.partials.security-questions-card, which cannot be closed), the endpoint it
 * saves to, and logout. They are what the sign-in code screen falls back on when a code
 * does not arrive (SecurityQuestionService).
 *
 * The THIRD gate: it defers to the passport photo and the phone number, so a user meets
 * one blocking card at a time. It holds only sessions that signed in after it went live
 * (AuthenticatedSessionController::completeLogin sets CHECK_KEY), so nobody is pulled
 * out of half-finished work; everyone meets it at their next sign-in.
 * SecurityQuestionService::mustSetUp() holds the whole judgment.
 */
class RequireSecurityQuestions
{
    /** Set at sign-in: this session must have answers before it goes anywhere. */
    public const CHECK_KEY = 'security_questions.check';

    /** The user id whose answers this session has seen in place, so the table is read once. */
    public const DONE_KEY = 'security_questions.done_user';

    private const ALLOWED_ROUTE_NAMES = [
        'home',
        'dashboard',
        'security-questions.update',
        'logout',
        'login',
        'markWelcomePopupShown',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (!app(SecurityQuestionService::class)->mustSetUp($request, Auth::guard('web')->user())) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $message = __('Set your security questions to continue using the system.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'security_questions_required' => true,
            ], 403);
        }

        // The dashboard opens the card by itself while the questions are owed.
        return redirect()->route('home')->with('error', $message);
    }

    private function isAllowed(Request $request): bool
    {
        // Public portals run on their own guards.
        if ($request->is('laas', 'laas/*', 'phs', 'phs/*', 'online-legal-search', 'online-legal-search/*', 'kssgof', 'kssgof/*', 'mlpp-sg', 'mlpp-sg/*')) {
            return true;
        }

        $routeName = optional($request->route())->getName();
        if ($routeName && in_array($routeName, self::ALLOWED_ROUTE_NAMES, true)) {
            return true;
        }

        return $request->is('logout', 'login', 'home', 'dashboard');
    }
}
