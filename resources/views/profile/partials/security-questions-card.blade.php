@php
    /*
     | The security questions card, on the dashboard (included by admin.header).
     |   $sqcForced - the account owes its answers (SecurityQuestionService::mustSetUp):
     |                the card opens itself on every page and cannot be closed.
     |   otherwise  - opened from the profile menu to change them: it asks for the
     |                password first and has a Cancel button.
     | Two panels, sized to fit the screen without scrolling: the left holds the steps
     | and the rules, the right only the current step.
     */
    $sqcService = app(\App\Services\SecurityQuestionService::class);
    $sqcUser = auth()->user();
    $sqcRequired = $sqcService->requiredCount();
    $sqcChange = !$sqcForced && $sqcService->hasAnswers($sqcUser);
    $sqcChosen = $sqcChange ? $sqcService->questionKeysFor($sqcUser) : [];
    // The browser's copy of the easy-guess list; the server checks again on save.
    $sqcBlocked = json_encode(array_values(array_map(
        [\App\Services\SecurityQuestionService::class, 'normalise'],
        (array) config('security_questions.blocked_answers', [])
    )));

    // The step list: [Password,] Question 1..n, Confirm, each with a short line under it.
    $sqcSteps = array_merge(
        $sqcChange ? [[__('Password'), __('Confirm it is you')]] : [],
        array_map(fn ($n) => [__('Question :n', ['n' => $n]), __('Choose and answer')], range(1, $sqcRequired)),
        [[__('Confirm'), __('Check and save')]]
    );

    // The KLAES-CORE mark, as on the sign-in screens (user, 2026-11-10).
    $sqcPrimaryLogo = asset('assets/logo/klas_core_logo.png');
    $sqcSecondaryLogo = asset('assets/logo/Left_Logo.png');
    if (\Illuminate\Support\Facades\Storage::disk('public')->exists('uploads/logo.jpeg')) {
        $sqcSecondaryLogo = asset('storage/uploads/logo.jpeg');
    }
@endphp

<link rel="stylesheet" href="{{ asset('css/security-questions-card.css') }}?v={{ filemtime(public_path('css/security-questions-card.css')) }}">

<div id="securityQuestionsCard" class="sqc-overlay"
    data-save-url="{{ route('security-questions.update') }}"
    data-forced="{{ $sqcForced ? '1' : '0' }}"
    data-change="{{ $sqcChange ? '1' : '0' }}"
    data-required="{{ $sqcRequired }}"
    data-min-length="{{ (int) config('security_questions.min_answer_length', 2) }}"
    data-blocked="{{ $sqcBlocked }}"
    role="dialog" aria-modal="true" aria-labelledby="sqcTitle" aria-hidden="true">

    <div class="sqc-card">

        {{-- Left: who, where they are, and the rules. --}}
        <aside class="sqc-side">
            <div class="sqc-logos">
                <img src="{{ $sqcPrimaryLogo }}" alt="KLAES">
                <span class="sqc-logo-rule" aria-hidden="true"></span>
                <img src="{{ $sqcSecondaryLogo }}" alt="Land Admin Enterprise System">
            </div>

            <p class="sqc-eyebrow">{{ $sqcChange ? __('Account security') : __('Account setup') }}</p>
            <h2 class="sqc-side-title" id="sqcTitle">{{ $sqcChange ? __('Change Your Security Questions') : __('Set Your Security Questions') }}</h2>
            <p class="sqc-side-lede">
                {{ __('Hello') }} <strong>{{ $sqcUser->first_name ?? $sqcUser->name }}</strong>,
                {{ __('if a sign-in code does not reach you within :m minutes, you can answer these instead.', ['m' => config('security_questions.fallback_after_minutes', 10)]) }}
            </p>

            <ol class="sqc-steps" id="sqcSteps" aria-label="{{ __('Progress') }}">
                @foreach ($sqcSteps as [$label, $sub])
                    <li class="sqc-step" data-step-index="{{ $loop->index }}">
                        <span class="sqc-step-dot">{{ $loop->iteration }}</span>
                        <span class="sqc-step-text">
                            <span class="sqc-step-label">{{ $label }}</span>
                            <span class="sqc-step-sub">{{ $sub }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>

            <ul class="sqc-rules">
                <li>{{ __('Answers only you know, that will not change.') }}</li>
                <li>{{ __('Capitals and extra spaces are ignored.') }}</li>
                <li>{{ __('No easy guesses: 1234, password, none, Nigeria, Kano.') }}</li>
                <li>{{ __('No hints. Nobody, including ICT, can see them.') }}</li>
            </ul>

            <p class="sqc-footnote" id="sqcFootnote">
                @if ($sqcForced)
                    {{ __('The system stays locked until this is done.') }}
                    <button type="submit" form="sqcLogoutForm" class="sqc-link">{{ __('Sign out') }}</button>
                @else
                    <button type="button" class="sqc-link" id="sqcCancelBtn">{{ __('Cancel') }}</button>
                @endif
            </p>
        </aside>

        {{-- Right: the current step only. --}}
        <section class="sqc-main">

            {{-- Change mode: the password first, so an unlocked screen cannot rewrite them. --}}
            @if ($sqcChange)
                <div class="sqc-pane" data-pane="password">
                    <p class="sqc-kicker">{{ __('Step :i of :n', ['i' => 1, 'n' => count($sqcSteps)]) }}</p>
                    <h3 class="sqc-title">{{ __('Confirm it is you') }}</h3>
                    <p class="sqc-lede">{{ __('Enter your password. You will then choose and answer :n questions again.', ['n' => $sqcRequired]) }}</p>
                    <div class="sqc-field">
                        <label class="sqc-label" for="sqcPassword">{{ __('Your password') }}</label>
                        <input type="password" id="sqcPassword" class="sqc-input" autocomplete="current-password">
                    </div>
                    <p class="sqc-error sqc-hidden" role="alert"></p>
                </div>
            @endif

            @for ($i = 0; $i < $sqcRequired; $i++)
                <div class="sqc-pane" data-pane="question" data-question-index="{{ $i }}">
                    <p class="sqc-kicker">{{ __('Step :i of :n', ['i' => $i + 1 + ($sqcChange ? 1 : 0), 'n' => count($sqcSteps)]) }}</p>
                    <h3 class="sqc-title">{{ __('Security question :i', ['i' => $i + 1]) }}</h3>
                    <p class="sqc-lede">
                        {{ $i === 0 ? __('Pick a question you can always answer the same way.') : __('Pick a different question from the ones already chosen.') }}
                    </p>

                    <div class="sqc-field">
                        <label class="sqc-label" for="sqcQuestion{{ $i }}">{{ __('Question') }}</label>
                        <select id="sqcQuestion{{ $i }}" class="sqc-select" data-question-select>
                            <option value="">{{ __('Choose a question…') }}</option>
                            @foreach ($sqcService->grouped() as $category => $items)
                                <optgroup label="{{ $category }}">
                                    @foreach ($items as $key => $text)
                                        <option value="{{ $key }}" @selected(($sqcChosen[$i] ?? '') === $key)>{{ $text }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="sqc-field">
                        <label class="sqc-label" for="sqcAnswer{{ $i }}">{{ __('Your answer') }}</label>
                        <div class="sqc-answer-wrap">
                            <input type="password" id="sqcAnswer{{ $i }}" class="sqc-input" data-answer-input
                                maxlength="150" autocomplete="off" spellcheck="false" placeholder="{{ __('Type your answer') }}">
                            <button type="button" class="sqc-reveal" data-reveal="sqcAnswer{{ $i }}">{{ __('Show') }}</button>
                        </div>
                    </div>
                    <p class="sqc-error sqc-hidden" role="alert"></p>
                </div>
            @endfor

            {{-- Confirm: the choices once more before they are saved. --}}
            <div class="sqc-pane" data-pane="confirm">
                <p class="sqc-kicker">{{ __('Step :i of :n', ['i' => count($sqcSteps), 'n' => count($sqcSteps)]) }}</p>
                <h3 class="sqc-title">{{ __('Confirm your answers') }}</h3>
                <p class="sqc-lede">{{ __('You will type them exactly like this if a sign-in code does not arrive.') }}
                    <button type="button" class="sqc-link" id="sqcToggleReview">{{ __('Show answers') }}</button>
                </p>
                <div class="sqc-review" id="sqcReview"></div>
                <p class="sqc-error sqc-hidden" role="alert"></p>
            </div>

            {{-- Done. --}}
            <div class="sqc-pane sqc-pane-done" data-pane="done">
                <span class="sqc-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                </span>
                <h3 class="sqc-title">{{ __('All set') }}</h3>
                <p class="sqc-lede" id="sqcDoneMessage"></p>
            </div>

            <div class="sqc-nav" id="sqcNav">
                <button type="button" class="sqc-back" id="sqcBackBtn">{{ __('Back') }}</button>
                <button type="button" class="sqc-submit" id="sqcNextBtn">{{ __('Next') }}</button>
            </div>
        </section>
    </div>
</div>
<form id="sqcLogoutForm" action="{{ route('logout') }}" method="post" class="sqc-hidden">@csrf</form>

<script src="{{ asset('js/security-questions-card.js') }}?v={{ filemtime(public_path('js/security-questions-card.js')) }}" defer></script>
