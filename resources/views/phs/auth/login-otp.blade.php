@extends('phs.layouts.app')

@section('title', 'Sign-in code - PHS Portal')

{{--
  Steps 2-3 of signing in: the password has been checked, the session is NOT
  signed in yet. See PhsLoginOtpService / PhsLoginOtpController.

  Same split as phs.auth.login — flyer behind the form on the left, panel on the
  right — so the member does not feel handed to a different system halfway
  through. The disclaimer is NOT repeated here: they read it one screen ago, and
  the only thing worth saying at this point is why they were asked for a code.
--}}

@section('extra_styles')
<style>
    .otp-box { caret-color: transparent; transition: border-color .15s, box-shadow .15s; }
    .otp-box:focus { outline: none; border-color: #1a6b3c; box-shadow: 0 0 0 3px rgba(26, 107, 60, .18); }
    .otp-box.filled { border-color: #1a6b3c; }
    .otp-error .otp-box { border-color: #ef4444; }
    @keyframes otp-shake {
        10%, 90% { transform: translateX(-1px); }
        20%, 80% { transform: translateX(2px); }
        30%, 50%, 70% { transform: translateX(-4px); }
        40%, 60% { transform: translateX(4px); }
    }
    .otp-shake { animation: otp-shake .45s both; }
    @keyframes otp-spin { to { transform: rotate(360deg); } }
    .otp-spin { animation: otp-spin .8s linear infinite; }
    #verifyButton:disabled { opacity: .45; cursor: not-allowed; }
    #resendButton:disabled { opacity: .5; text-decoration: none; cursor: not-allowed; }
</style>
@endsection

@section('content')

<div class="h-screen flex overflow-hidden">

    <!-- LEFT - the code form (flyer sits in the background, as on the login screen) -->
    <div class="w-full md:w-1/2 relative flex flex-col items-center justify-center px-6 py-10 overflow-y-auto">

        <img src="{{ asset('assets/logo/PHS-P Flyer.png') }}" alt="" aria-hidden="true"
             class="absolute inset-0 w-full h-full object-cover object-top">
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50/90 via-white/90 to-green-50/90 dark:from-gray-900/90 dark:via-gray-900/90 dark:to-gray-800/90 backdrop-blur-[1px]"></div>

        <div class="relative z-10 w-full max-w-md">

            <div class="mb-8 text-center">
                <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS Portal"
                     class="mx-auto mb-4 h-20 w-auto object-contain dark:hidden">
                <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS Portal"
                     class="mx-auto mb-4 h-20 w-auto object-contain hidden dark:block">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Enter your sign-in code</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    We emailed a {{ $codeLength }}-digit code to
                    <span class="font-semibold text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $maskedEmail }}</span>.
                </p>
                @if ($organization)
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">{{ $organization }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-lg sm:p-8">

                <div class="mb-5 flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Step 2 of 2</span>
                    @if (session('status'))
                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-green-700 dark:text-green-400">
                            <i data-lucide="check-circle-2" class="h-3.5 w-3.5"></i> Code sent
                        </span>
                    @endif
                </div>

                @if (session('error') || $errors->has('code'))
                    <div role="alert" class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 dark:border-red-700 bg-red-50 dark:bg-red-900/30 p-4">
                        <i data-lucide="alert-circle" class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-600 dark:text-red-400"></i>
                        <p class="text-sm text-red-700 dark:text-red-400">{{ session('error') ?: $errors->first('code') }}</p>
                    </div>
                @endif

                <form action="{{ route('phs.login.otp.verify') }}" method="POST" id="otpForm">
                    @csrf
                    <input type="hidden" name="code" id="code">

                    <label class="sr-only" for="otp-0">Sign-in code</label>
                    <div id="otpBoxes" class="flex justify-between gap-2 {{ session('error') ? 'otp-error otp-shake' : '' }}">
                        @for ($i = 0; $i < $codeLength; $i++)
                            <input id="otp-{{ $i }}" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                                   {{ $i === 0 ? 'autocomplete=one-time-code autofocus' : 'autocomplete=off' }}
                                   aria-label="Digit {{ $i + 1 }}"
                                   class="otp-box h-14 w-full rounded-lg border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-center text-2xl font-bold text-gray-900 dark:text-gray-100">
                        @endfor
                    </div>

                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400" aria-live="polite">
                        <span id="otpExpiry" data-seconds="{{ $expiresIn }}"></span>
                    </p>

                    <button type="submit" id="verifyButton" disabled
                            class="mt-6 w-full rounded-lg py-2.5 font-semibold text-white transition sm:py-3"
                            style="background-color:#1a6b3c;"
                            onmouseover="if(!this.disabled)this.style.backgroundColor='#155a32'"
                            onmouseout="this.style.backgroundColor='#1a6b3c'">
                        <i data-lucide="loader-2" id="verifySpinner" class="mr-2 hidden otp-spin h-4 w-4 inline"></i>
                        <span id="verifyLabel">Verify and sign in</span>
                    </button>
                </form>

                <div class="mt-6 rounded-lg bg-gray-50 dark:bg-gray-700/40 px-4 py-3">
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Didn&rsquo;t get the email?
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        It can take a minute, and it sometimes lands in spam or junk.
                    </p>

                    <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                        <form action="{{ route('phs.login.otp.resend') }}" method="POST">
                            @csrf
                            <button type="submit" id="resendButton" data-wait="{{ $retryAfter }}"
                                    class="font-semibold text-blue-600 dark:text-blue-400 underline hover:text-blue-700 dark:hover:text-blue-300">
                                <span id="resendLabel">Send another email</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-center gap-4">
                <form action="{{ route('phs.login.otp.cancel') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Use a different account
                    </button>
                </form>
                <button onclick="phsToggleTheme()" title="Toggle dark mode"
                        class="rounded-md p-2 text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden"></i>
                    <i data-lucide="moon" class="h-4 w-4 hidden dark:block"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- RIGHT - why this step exists (hidden on mobile, same as the login screen) -->
    <div class="hidden md:flex md:w-1/2 h-screen flex-col justify-center bg-[#f0f7f2] dark:bg-gray-800 border-l border-green-200 dark:border-gray-700 px-12">
        <div class="mx-auto w-full max-w-sm">

            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Two-step sign-in</h2>
            <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                Your password proves who you are. The code proves it is you at the keyboard.
                A PHS account can search the land registry and spend your organization&rsquo;s
                tokens, so a leaked password alone is not enough to get in.
            </p>

            <ol class="mt-8 space-y-5">
                <li class="flex items-center">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-white" style="background-color:#1a6b3c;">
                        <i data-lucide="check" class="h-4 w-4"></i>
                    </span>
                    <span class="ml-3 text-sm text-gray-600 dark:text-gray-400">Password checked</span>
                </li>
                <li class="flex items-center">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-gray-800 dark:bg-gray-200 text-sm font-semibold text-white dark:text-gray-900 ring-4 ring-gray-300 dark:ring-gray-600">2</span>
                    <span class="ml-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Code sent to your email
                    </span>
                </li>
                <li class="flex items-center">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full border-2 border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-400 dark:text-gray-500">3</span>
                    <span class="ml-3 text-sm text-gray-500 dark:text-gray-400">Into the PHS Portal</span>
                </li>
            </ol>

            <div class="mt-10 flex items-start gap-3 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
                <i data-lucide="shield-alert" class="h-5 w-5 flex-shrink-0 text-gray-700 dark:text-gray-300"></i>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Never share this code.</span>
                    Nobody from the Ministry or KLAES support will ever ask you for it.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('extra_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('otpForm');
        var hidden = document.getElementById('code');
        var wrap = document.getElementById('otpBoxes');
        var boxes = Array.prototype.slice.call(wrap.querySelectorAll('.otp-box'));
        var button = document.getElementById('verifyButton');
        var submitting = false;

        function value() { return boxes.map(function (b) { return b.value; }).join(''); }

        function refresh() {
            boxes.forEach(function (b) { b.classList.toggle('filled', b.value !== ''); });
            hidden.value = value();
            button.disabled = hidden.value.length !== boxes.length || submitting;
        }

        function submit() {
            if (submitting || hidden.value.length !== boxes.length) return;
            submitting = true;
            button.disabled = true;
            document.getElementById('verifySpinner').classList.remove('hidden');
            document.getElementById('verifyLabel').textContent = 'Verifying...';
            form.submit();
        }

        // Spread a run of digits across the boxes from `start` (typing or paste).
        function fill(start, digits) {
            var i = start;
            digits.split('').forEach(function (d) { if (i < boxes.length) { boxes[i].value = d; i++; } });
            refresh();
            boxes[Math.min(i, boxes.length - 1)].focus();
            if (hidden.value.length === boxes.length) submit();
        }

        boxes.forEach(function (box, i) {
            box.addEventListener('focus', function () { box.select(); });

            box.addEventListener('input', function () {
                wrap.classList.remove('otp-error');
                var digits = box.value.replace(/\D+/g, '');
                box.value = '';
                if (digits) { fill(i, digits); } else { refresh(); }
            });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && box.value === '' && i > 0) {
                    boxes[i - 1].value = '';
                    boxes[i - 1].focus();
                    refresh();
                    e.preventDefault();
                } else if (e.key === 'ArrowLeft' && i > 0) {
                    boxes[i - 1].focus(); e.preventDefault();
                } else if (e.key === 'ArrowRight' && i < boxes.length - 1) {
                    boxes[i + 1].focus(); e.preventDefault();
                } else if (e.key === 'Enter') {
                    submit(); e.preventDefault();
                }
            });

            box.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D+/g, '');
                if (text) { e.preventDefault(); fill(i, text); }
            });
        });

        form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

        // Code expiry countdown.
        var expiry = document.getElementById('otpExpiry');
        var left = parseInt(expiry.getAttribute('data-seconds'), 10) || 0;
        (function expiryTick() {
            if (left <= 0) {
                expiry.textContent = 'This code has expired. Ask for a new one below.';
                expiry.className = 'font-semibold text-red-600 dark:text-red-400';
                return;
            }
            var m = Math.floor(left / 60), s = left % 60;
            expiry.textContent = 'Expires in ' + m + ':' + (s < 10 ? '0' : '') + s;
            left -= 1;
            setTimeout(expiryTick, 1000);
        })();

        // Resend stays disabled for the cooldown the server reported.
        var resend = document.getElementById('resendButton');
        var label = document.getElementById('resendLabel');
        var text = label.textContent;
        var wait = parseInt(resend.getAttribute('data-wait'), 10) || 0;
        (function resendTick() {
            if (wait <= 0) { resend.disabled = false; label.textContent = text; return; }
            resend.disabled = true;
            label.textContent = text + ' in ' + wait + 's';
            wait -= 1;
            setTimeout(resendTick, 1000);
        })();

        if (window.lucide) window.lucide.createIcons();
    });
</script>
@endsection
