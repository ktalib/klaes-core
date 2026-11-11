<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Security questions: the fallback when a sign-in code does not arrive (config/security_questions.php).
 *
 * Every account answers `required` different questions; the answers are normalised
 * (lower case, trimmed, single-spaced) and hashed like a password, so they cannot be
 * read back and there are no hints. On the code screen, once a sign-in has waited
 * `fallback_after_minutes`, the account may answer them all instead of typing the code.
 * Wrong tries are counted per account, not per browser, so a new tab buys no guesses;
 * after `max_attempts` the questions lock for `lock_minutes` (the code still works).
 *
 * Staff accounts by default. Survey\SurveyorSecurityQuestions points the same rules at the
 * MLPP-SG portal accounts through table(), ownerColumn() and cacheScope().
 */
class SecurityQuestionService
{
    /** Table name => migrated yet, read once per request. */
    private static array $tableReady = [];

    /** Where the answers live, and the column naming their owner. */
    protected function table(): string
    {
        return 'user_security_answers';
    }

    protected function ownerColumn(): string
    {
        return 'user_id';
    }

    /** Prefix of the wrong-try and lock cache keys, so two kinds of account with the same id never share them. */
    protected function cacheScope(): string
    {
        return 'security_questions';
    }

    private function attemptsKey(Model $owner): string
    {
        return $this->cacheScope() . '.attempts.' . $owner->getKey();
    }

    private function lockKey(Model $owner): string
    {
        return $this->cacheScope() . '.lock.' . $owner->getKey();
    }

    /** Off by switch, or the table is not migrated yet: then nobody is held or offered anything. */
    public function enabled(): bool
    {
        return (bool) config('security_questions.enabled', true) && $this->tableReady();
    }

    private function tableReady(): bool
    {
        return self::$tableReady[$this->table()] ??= Schema::connection('sqlsrv')->hasTable($this->table());
    }

    public function requiredCount(): int
    {
        return max(1, (int) config('security_questions.required', 3));
    }

    /** Category => [key => question], for the dropdowns. */
    public function grouped(): array
    {
        return (array) config('security_questions.questions', []);
    }

    /** key => question. */
    public function questions(): array
    {
        return array_merge(...array_values($this->grouped()) ?: [[]]);
    }

    public static function normalise(?string $answer): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $answer)));
    }

    /**
     * Problems with a set of answers, keyed "answers.<i>" / "questions.<i>" for the form.
     *
     * @param  array<int, array{question:?string, answer:?string}>  $entries
     */
    public function validate(array $entries): array
    {
        $errors = [];
        $questions = $this->questions();
        $blocked = array_map([self::class, 'normalise'], (array) config('security_questions.blocked_answers', []));
        $minLength = max(1, (int) config('security_questions.min_answer_length', 2));
        $seenQuestions = [];
        $seenAnswers = [];

        for ($i = 0; $i < $this->requiredCount(); $i++) {
            $key = (string) ($entries[$i]['question'] ?? '');
            $answer = self::normalise($entries[$i]['answer'] ?? '');

            if (!isset($questions[$key])) {
                $errors["questions.$i"] = __('Choose a question.');
            } elseif (isset($seenQuestions[$key])) {
                $errors["questions.$i"] = __('Choose a different question for each answer.');
            }
            $seenQuestions[$key] = true;

            if ($answer === '') {
                $errors["answers.$i"] = __('Type your answer.');
            } elseif (mb_strlen($answer) < $minLength) {
                $errors["answers.$i"] = __('The answer is too short.');
            } elseif (in_array($answer, $blocked, true) || preg_match('/^(.)\1*$/u', $answer)) {
                $errors["answers.$i"] = __('That answer is too easy to guess. Choose something only you would know.');
            } elseif (isset($seenAnswers[$answer])) {
                $errors["answers.$i"] = __('Give a different answer to each question.');
            }
            $seenAnswers[$answer] = true;
        }

        return $errors;
    }

    /** The account's chosen question keys, in the order it set them (only keys still in the list). */
    public function questionKeysFor(Model $user): array
    {
        if (!$this->tableReady()) {
            return [];
        }

        return DB::connection('sqlsrv')->table($this->table())
            ->where($this->ownerColumn(), $user->getKey())
            ->orderBy('id')
            ->pluck('question_key')
            ->filter(fn ($key) => isset($this->questions()[$key]))
            ->values()
            ->all();
    }

    public function hasAnswers(Model $user): bool
    {
        return count($this->questionKeysFor($user)) >= $this->requiredCount();
    }

    /**
     * Must this signed-in session set answers before going anywhere? Shared by
     * RequireSecurityQuestions (the URLs) and the dashboard card (what is shown).
     *
     * Only sessions that signed in after go-live (CHECK_KEY), never an admin looking
     * through the account, never the OTP-exempt shared accounts, and only once the
     * passport photo and phone number are settled: one blocking step at a time.
     */
    public function mustSetUp(\Illuminate\Http\Request $request, ?User $user): bool
    {
        if (!$user || !$request->hasSession() || !$request->session()->get(\App\Http\Middleware\RequireSecurityQuestions::CHECK_KEY)) {
            return false;
        }

        if (app(\Lab404\Impersonate\Services\ImpersonateManager::class)->isImpersonating()
            || LoginOtpService::isOtpExempt($user)
            || $user->needs_profile_photo
            || $user->needs_phone_verification) {
            return false;
        }

        $done = \App\Http\Middleware\RequireSecurityQuestions::DONE_KEY;
        if ((string) $request->session()->get($done) === (string) $user->id) {
            return false;
        }

        if (!$this->enabled() || $this->hasAnswers($user)) {
            // Read the table once per session, not on every page.
            $request->session()->put($done, $user->id);
            return false;
        }

        return true;
    }

    /**
     * Replace the account's answers. Call validate() first.
     *
     * @param  array<int, array{question:string, answer:string}>  $entries
     */
    public function save(Model $user, array $entries): void
    {
        $now = now();
        $rows = [];
        foreach (array_slice($entries, 0, $this->requiredCount()) as $entry) {
            $rows[] = [
                $this->ownerColumn() => $user->getKey(),
                'question_key' => $entry['question'],
                'answer_hash' => Hash::make(self::normalise($entry['answer'])),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::connection('sqlsrv')->transaction(function () use ($user, $rows) {
            DB::connection('sqlsrv')->table($this->table())->where($this->ownerColumn(), $user->getKey())->delete();
            DB::connection('sqlsrv')->table($this->table())->insert($rows);
        });

        $this->clearAttempts($user);
    }

    /* ── the sign-in fallback ─────────────────────────────────────────────── */

    /** Seconds until the code screen offers the questions, counted from the start of the sign-in. */
    public function fallbackOpensIn(int $signInStartedAt): int
    {
        $after = max(0, (int) config('security_questions.fallback_after_minutes', 10)) * 60;

        return max(0, $signInStartedAt + $after - now()->timestamp);
    }

    public function lockedFor(Model $user): int
    {
        $until = (int) Cache::get($this->lockKey($user), 0);

        return max(0, $until - now()->timestamp);
    }

    /**
     * Check answers typed on the fallback screen. Every question must be right.
     *
     * @param  array<string, ?string>  $answers  question key => typed answer
     * @return array{verified:bool, message:string}
     */
    public function verify(Model $user, array $answers, ?string $ip = null): array
    {
        if (($locked = $this->lockedFor($user)) > 0) {
            return ['verified' => false, 'message' => $this->lockedMessage($locked)];
        }

        $stored = DB::connection('sqlsrv')->table($this->table())
            ->where($this->ownerColumn(), $user->getKey())
            ->pluck('answer_hash', 'question_key');

        $keys = $this->questionKeysFor($user);
        $correct = count($keys) >= $this->requiredCount();
        // Every answer is checked, right or wrong, so the time taken does not say which one failed.
        foreach ($keys as $key) {
            $correct = Hash::check(self::normalise($answers[$key] ?? ''), (string) ($stored[$key] ?? '')) && $correct;
        }

        if ($correct) {
            $this->clearAttempts($user);
            Log::info('Sign-in by security questions', [$this->ownerColumn() => $user->getKey(), 'ip' => $ip]);

            return ['verified' => true, 'message' => ''];
        }

        $max = max(1, (int) config('security_questions.max_attempts', 3));
        $lockSeconds = max(1, (int) config('security_questions.lock_minutes', 30)) * 60;
        $attempts = (int) Cache::get($this->attemptsKey($user), 0) + 1;

        if ($attempts >= $max) {
            Cache::forget($this->attemptsKey($user));
            Cache::put($this->lockKey($user), now()->timestamp + $lockSeconds, $lockSeconds);
            Log::warning('Security questions locked after wrong answers', [$this->ownerColumn() => $user->getKey(), 'ip' => $ip]);

            return ['verified' => false, 'message' => $this->lockedMessage($lockSeconds)];
        }

        Cache::put($this->attemptsKey($user), $attempts, $lockSeconds);
        Log::notice('Wrong security question answers', [$this->ownerColumn() => $user->getKey(), 'ip' => $ip, 'attempt' => $attempts]);

        return ['verified' => false, 'message' => trans_choice(
            '{1}One or more answers are not correct. One more try before the questions are locked.|[2,*]One or more answers are not correct. :count tries left.',
            $max - $attempts,
            ['count' => $max - $attempts]
        )];
    }

    private function clearAttempts(Model $user): void
    {
        Cache::forget($this->attemptsKey($user));
        Cache::forget($this->lockKey($user));
    }

    private function lockedMessage(int $seconds): string
    {
        $minutes = (int) ceil($seconds / 60);

        return trans_choice(
            '{1}Too many wrong answers. The security questions are locked for 1 more minute. You can still use the sign-in code.|[2,*]Too many wrong answers. The security questions are locked for :count minutes. You can still use the sign-in code.',
            $minutes,
            ['count' => $minutes]
        );
    }
}
