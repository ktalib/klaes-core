<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireSecurityQuestions;
use App\Services\LoginOtpService;
use App\Services\SecurityQuestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Security questions (config/security_questions.php, SecurityQuestionService):
 *  - edit / update: the dashboard card where the signed-in user sets their answers
 *    (forced once by RequireSecurityQuestions, cannot be closed) or changes them later
 *    from the profile menu, which needs their password;
 *  - challenge / verify: on the sign-in code screen, once the sign-in has waited long
 *    enough, the pending account answers them instead of typing the code. Like the code
 *    screen, these work on the PENDING sign-in in this browser's session only.
 */
class SecurityQuestionsController extends Controller
{
    public function __construct(private SecurityQuestionService $questions, private LoginOtpService $otp)
    {
    }

    /** Profile menu "Security Questions": the dashboard opens the card in change mode. */
    public function edit(Request $request)
    {
        abort_unless($this->questions->enabled(), 404);

        return redirect()->route('home')->with('open_security_questions_card', true);
    }

    /** Saved from the card (profile.partials.security-questions-card) as JSON. */
    public function update(Request $request)
    {
        abort_unless($this->questions->enabled(), 404);
        $user = Auth::user();
        $isUpdate = $this->questions->hasAnswers($user);

        $entries = [];
        for ($i = 0; $i < $this->questions->requiredCount(); $i++) {
            $entries[] = [
                'question' => (string) $request->input("questions.$i", ''),
                'answer' => (string) $request->input("answers.$i", ''),
            ];
        }

        $errors = $this->questions->validate($entries);
        // Changing answers someone already set needs the password: an unlocked screen is not enough.
        if ($isUpdate && !Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            $errors['current_password'] = __('Your password is not correct.');
        }
        if ($errors) {
            return response()->json([
                'success' => false,
                'message' => __('Please correct the marked answers.'),
                'errors' => $errors,
            ], 422);
        }

        $this->questions->save($user, $entries);
        $request->session()->put(RequireSecurityQuestions::DONE_KEY, $user->id);

        return response()->json([
            'success' => true,
            'message' => $isUpdate
                ? __('Your security questions have been changed.')
                : __('Your security questions are set. If a sign-in code does not arrive, you can answer them instead.'),
        ]);
    }

    public function challenge(Request $request)
    {
        [$user, $refusal] = $this->pendingWithFallback($request);
        if ($refusal) {
            return $refusal;
        }

        $questions = $this->questions->questions();

        return view('auth.login-security-questions', [
            'questions' => collect($this->questions->questionKeysFor($user))->mapWithKeys(fn ($key) => [$key => $questions[$key]]),
            'lockedFor' => $this->questions->lockedFor($user),
        ]);
    }

    public function verify(Request $request)
    {
        [$user, $refusal] = $this->pendingWithFallback($request);
        if ($refusal) {
            return $refusal;
        }

        $result = $this->questions->verify($user, (array) $request->input('answers', []), $request->ip());
        if (!$result['verified']) {
            return redirect()->route('login.questions')->with('error', $result['message']);
        }

        $remember = (bool) ($this->otp->pending($request)['remember'] ?? false);
        $this->otp->clear($request);
        // The code that never arrived must not open a second sign-in later.
        Cache::forget(LoginOtpService::CODE_CACHE . $user->id);

        return app(AuthenticatedSessionController::class)->completeLogin($request, $user, $remember);
    }

    /**
     * The pending account, or the redirect that refuses it: no sign-in waiting, no answers
     * set, the switch off, or too soon in the sign-in for the fallback.
     *
     * @return array{0: ?\App\Models\User, 1: ?\Illuminate\Http\RedirectResponse}
     */
    private function pendingWithFallback(Request $request): array
    {
        $user = $this->otp->pendingUser($request);
        if (!$user) {
            return [null, redirect()->route('login')->with('error', __('Your sign-in has expired. Enter your username and password again.'))];
        }

        if (!$this->questions->enabled() || !$this->questions->hasAnswers($user)) {
            return [null, redirect()->route('login.otp')->with('error', __('This account has no security questions set. Use the sign-in code.'))];
        }

        if ($this->questions->fallbackOpensIn((int) ($this->otp->pending($request)['started_at'] ?? 0)) > 0) {
            return [null, redirect()->route('login.otp')->with('error', __('Wait for the sign-in code a little longer, or ask for it by email.'))];
        }

        return [$user, null];
    }
}
