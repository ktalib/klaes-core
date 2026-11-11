<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyorPortalAccount;
use App\Services\SecurityQuestionService;
use Illuminate\Http\Request;

/**
 * Security questions for the MLPP-SG portal (user, 2026-11-10): the staff rules
 * (config/security_questions.php: the list, 2 answers, blocklist, fallback at 10 minutes,
 * 3 wrong = locked 30 minutes) pointed at surveyor_portal_security_answers. Portal and
 * staff ids overlap, so the table and the lock keys are the portal's own.
 */
class SurveyorSecurityQuestions extends SecurityQuestionService
{
    /** Set at sign-in (AuthController::completeLogin): only sessions from then on are held. */
    public const CHECK_KEY = 'surveyor_security_questions.check';

    /** The account whose answers this session has already seen, so the table is read once. */
    public const DONE_KEY = 'surveyor_security_questions.done';

    protected function table(): string
    {
        return 'surveyor_portal_security_answers';
    }

    protected function ownerColumn(): string
    {
        return 'surveyor_portal_account_id';
    }

    protected function cacheScope(): string
    {
        return 'surveyor_security_questions';
    }

    /**
     * Must this signed-in portal session set answers before going anywhere? Shared by
     * RequireSurveyorSecurityQuestions (the URLs) and the portal card (what is shown).
     */
    public function owesAnswers(Request $request, ?SurveyorPortalAccount $account): bool
    {
        if (!$account || !$request->hasSession() || !$request->session()->get(self::CHECK_KEY)) {
            return false;
        }

        if ((string) $request->session()->get(self::DONE_KEY) === (string) $account->getKey()) {
            return false;
        }

        if (!$this->enabled() || $this->hasAnswers($account)) {
            $request->session()->put(self::DONE_KEY, $account->getKey());
            return false;
        }

        return true;
    }
}
