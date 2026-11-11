<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Services\Survey\SurveyorLoginOtpService;
use App\Services\Survey\SurveyorSecurityQuestions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * MLPP-SG security questions (user, 2026-11-10), the twin of Auth\SecurityQuestionsController:
 *  - edit / update: the dashboard card where the surveyor sets their answers (forced once by
 *    RequireSurveyorSecurityQuestions, cannot be closed) or changes them later, with the password;
 *  - challenge / verify: once a sign-in has waited for its code long enough, the pending
 *    account answers them instead. These work on the PENDING sign-in in this session only.
 */
class SecurityQuestionsController extends Controller
{
    public function __construct(private SurveyorSecurityQuestions $questions, private SurveyorLoginOtpService $otp)
    {
    }

    /** Sidebar "Security Questions": the dashboard opens the card in change mode. */
    public function edit()
    {
        abort_unless($this->questions->enabled(), 404);

        return redirect()->route('surveyor-portal.dashboard')->with('open_security_questions_card', true);
    }

    /** Saved from the card as JSON. */
    public function update(Request $request)
    {
        abort_unless($this->questions->enabled(), 404);
        $account = Auth::guard('surveyor')->user();
        $isUpdate = $this->questions->hasAnswers($account);

        $entries = [];
        for ($i = 0; $i < $this->questions->requiredCount(); $i++) {
            $entries[] = [
                'question' => (string) $request->input("questions.$i", ''),
                'answer' => (string) $request->input("answers.$i", ''),
            ];
        }

        $errors = $this->questions->validate($entries);
        // Changing answers already set needs the password: an unlocked screen is not enough.
        if ($isUpdate && !Hash::check((string) $request->input('current_password'), (string) $account->password)) {
            $errors['current_password'] = 'Your password is not correct.';
        }
        if ($errors) {
            return response()->json(['success' => false, 'message' => 'Please correct the marked answers.', 'errors' => $errors], 422);
        }

        $this->questions->save($account, $entries);
        $request->session()->put(SurveyorSecurityQuestions::DONE_KEY, $account->getKey());

        return response()->json([
            'success' => true,
            'message' => $isUpdate
                ? 'Your security questions have been changed.'
                : 'Your security questions are set. If a sign-in code does not arrive, you can answer them instead.',
        ]);
    }

    public function challenge(Request $request)
    {
        [$account, $refusal] = $this->pendingWithFallback($request);
        if ($refusal) {
            return $refusal;
        }

        $questions = $this->questions->questions();

        return view('surveyor_portal.auth.login_questions', [
            'questions' => collect($this->questions->questionKeysFor($account))->mapWithKeys(fn ($key) => [$key => $questions[$key]]),
            'lockedFor' => $this->questions->lockedFor($account),
        ]);
    }

    public function verify(Request $request)
    {
        [$account, $refusal] = $this->pendingWithFallback($request);
        if ($refusal) {
            return $refusal;
        }

        $result = $this->questions->verify($account, (array) $request->input('answers', []), $request->ip());
        if (!$result['verified']) {
            return redirect()->route('surveyor-portal.login.questions')->with('error', $result['message']);
        }

        // Ends the pending sign-in and spends the code that never arrived.
        $passed = $this->otp->passed($request, $account);

        return app(AuthController::class)->completeLogin($request, $account, $passed['remember']);
    }

    /**
     * The pending account, or the redirect that refuses it: no sign-in waiting, no answers set,
     * the switch off, or too soon in the sign-in for the fallback.
     *
     * @return array{0: ?\App\Models\Survey\SurveyorPortalAccount, 1: ?\Illuminate\Http\RedirectResponse}
     */
    private function pendingWithFallback(Request $request): array
    {
        $account = $this->otp->pendingAccount($request);
        if (!$account) {
            return [null, redirect()->route('surveyor-portal.login')
                ->withErrors(['username' => 'Your sign-in has expired. Enter your username and password again.'])];
        }

        if (!$this->questions->enabled() || !$this->questions->hasAnswers($account)) {
            return [null, redirect()->route('surveyor-portal.login.otp')->with('error', 'This account has no security questions set. Use the sign-in code.')];
        }

        if ($this->questions->fallbackOpensIn((int) ($this->otp->pending($request)['started_at'] ?? 0)) > 0) {
            return [null, redirect()->route('surveyor-portal.login.otp')->with('error', 'Wait for the sign-in code a little longer, or ask for it by email.')];
        }

        return [$account, null];
    }
}
