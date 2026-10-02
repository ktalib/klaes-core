@extends('laas.layouts.app')

@section('title', 'Sign-in code — LAAS Portal')

{{--
  Steps 2-3 of signing in: the password has been checked, the session is NOT
  signed in yet. See LaasLoginOtpService / LaasLoginOtpController.

  Same split layout as laas.auth.login so the applicant does not feel handed to
  a different system halfway through, with the assurance panel replaced by the
  three-step progress — the one thing worth saying at this point is "you are
  nearly in, and here is why we asked".
--}}

@push('styles')
<style>
    .otp-box {
        caret-color: transparent;
        transition: border-color .15s, box-shadow .15s, background-color .15s;
    }
    .otp-box:focus {
        outline: none;
        border-color: var(--brand);
        box-shadow: 0 0 0 3px rgba(20, 32, 26, .12);
    }
    .otp-box.filled { border-color: var(--brand); }
    .otp-error .otp-box { border-color: var(--danger); }
    @keyframes otp-shake {
        10%, 90% { transform: translateX(-1px); }
        20%, 80% { transform: translateX(2px); }
        30%, 50%, 70% { transform: translateX(-4px); }
        40%, 60% { transform: translateX(4px); }
    }
    .otp-shake { animation: otp-shake .45s both; }
    @keyframes otp-spin { to { transform: rotate(360deg); } }
    .otp-spin { animation: otp-spin .8s linear infinite; }
    /* No disabled: variants are relied on; the states are spelled out here. */
    #verifyButton:disabled { opacity: .45; cursor: not-allowed; }
    #resendButton:disabled { opacity: .5; text-decoration: none; cursor: not-allowed; }
</style>
@endpush

@section('body')
@php
    // Where the logo goes back to — the door this sign-in started at.
    $landingRoute = $portal === 'alaes' ? 'alaes_portal.login' : 'laas.landing';
    $sentByEmail = $channel === 'email' && $maskedEmail;
@endphp

<div class="grid min-h-screen lg:grid-cols-[1fr_1.05fr]">

    {{-- ---------- Code form ---------- --}}
    <main id="laas-main" class="flex flex-col justify-center px-4 py-10 sm:px-8 lg:px-14">
        <div class="mx-auto w-full max-w-md">

            <div class="mb-10 flex items-center justify-between">
                <a href="{{ route($landingRoute) }}" class="inline-flex items-center gap-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white ring-1"
                          style="--tw-ring-color: var(--border);">
                        <img src="{{ asset('assets/logo/ministry2.png') }}" alt="" aria-hidden="true"
                             class="h-11 w-11 rounded-full object-contain">
                    </span>
                    <img src="{{ asset('assets/logo/laas-light-logo.jpeg') }}" alt="LAAS Portal"
                         class="h-10 w-auto object-contain dark:hidden">
                    <img src="{{ asset('assets/logo/laas-dark-logo.jpeg') }}" alt="LAAS Portal"
                         class="hidden h-10 w-auto object-contain dark:block">
                </a>
                <span class="laas-eyebrow" style="color: var(--ink-soft);">Step 2 of 2</span>
            </div>

            <h1 class="text-3xl font-extrabold tracking-tight" style="color: var(--ink);">
                Enter your sign-in code
            </h1>
            <p class="mt-2 text-sm leading-relaxed" style="color: var(--ink-soft);">
                @if ($sentByEmail)
                    We emailed a {{ $codeLength }}-digit code to
                    <span class="whitespace-nowrap font-bold" style="color: var(--ink);">{{ $maskedEmail }}</span>.
                @else
                    We texted a {{ $codeLength }}-digit code to
                    <span class="whitespace-nowrap font-bold" style="color: var(--ink);">{{ $maskedPhone }}</span>.
                @endif
            </p>

            @if (session('error') || $errors->has('code'))
                <div role="alert" class="mt-6 flex items-start gap-2.5 rounded-xl border p-4"
                     style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                    <i data-lucide="alert-circle" class="mt-0.5 h-4 w-4 flex-shrink-0"
                       style="color: var(--danger);" aria-hidden="true"></i>
                    <p class="text-sm font-medium" style="color: var(--danger);">
                        {{ session('error') ?: $errors->first('code') }}
                    </p>
                </div>
            @endif

            <form action="{{ route('laas.login.otp.verify') }}" method="POST" id="otpForm" class="mt-7">
                @csrf
                <input type="hidden" name="code" id="code">

                <label class="sr-only" for="otp-0">Sign-in code</label>
                <div id="otpBoxes" class="flex justify-between gap-2 {{ session('error') ? 'otp-error otp-shake' : '' }}">
                    @for ($i = 0; $i < $codeLength; $i++)
                        <input id="otp-{{ $i }}" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                               {{ $i === 0 ? 'autocomplete=one-time-code autofocus' : 'autocomplete=off' }}
                               aria-label="Digit {{ $i + 1 }}"
                               class="otp-box h-14 w-full rounded-xl border-2 text-center text-2xl font-bold"
                               style="border-color: var(--border); background: var(--surface-card); color: var(--ink);">
                    @endfor
                </div>

                {{-- Sent / countdown / expired. Replaces a banner repeating the line above. --}}
                <p id="otpStatus" class="mt-3 flex items-center gap-1.5 text-xs" aria-live="polite"
                   style="color: var(--ink-soft);">
                    @if (session('status'))
                        <i data-lucide="check-circle-2" class="h-3.5 w-3.5 flex-shrink-0"
                           style="color: var(--brand);" aria-hidden="true"></i>
                        <span class="font-semibold" style="color: var(--brand);">Code sent.</span>
                    @endif
                    <span id="otpExpiry" data-seconds="{{ $expiresIn }}"></span>
                </p>

                <button type="submit" id="verifyButton" disabled class="laas-btn mt-6 w-full py-3.5 text-base">
                    <i data-lucide="loader-2" id="verifySpinner" class="hidden otp-spin h-5 w-5" aria-hidden="true"></i>
                    <span id="verifyLabel">Verify and sign in</span>
                </button>
            </form>

            {{-- Didn't get it --}}
            <div class="mt-7 rounded-xl border p-4"
                 style="border-color: var(--border); background: rgba(127,127,127,.06);">
                <p class="text-sm font-bold" style="color: var(--ink);">Didn&rsquo;t get a code?</p>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
                    <form action="{{ route('laas.login.otp.resend') }}" method="POST">
                        @csrf
                        <input type="hidden" name="channel" value="{{ $sentByEmail ? 'email' : 'sms' }}">
                        <button type="submit" id="resendButton" data-wait="{{ $retryAfter }}"
                                class="font-bold underline underline-offset-2" style="color: var(--brand);">
                            <span id="resendLabel">{{ $sentByEmail ? 'Resend email' : 'Resend SMS' }}</span>
                        </button>
                    </form>

                    {{-- The other route, offered only when the account can actually be reached on it. --}}
                    @if ($sentByEmail ? (bool) $maskedPhone : (bool) $maskedEmail)
                        <span aria-hidden="true" style="color: var(--border);">|</span>
                        <form action="{{ route('laas.login.otp.resend') }}" method="POST">
                            @csrf
                            <input type="hidden" name="channel" value="{{ $sentByEmail ? 'sms' : 'email' }}">
                            <button type="submit" class="font-bold underline underline-offset-2" style="color: var(--brand);">
                                {{ $sentByEmail ? 'Text it to my phone' : 'Email it to me instead' }}
                            </button>
                        </form>
                    @endif
                </div>

                @if ($quietHours && !$sentByEmail && $maskedEmail)
                    <p class="mt-3 flex items-start gap-1.5 text-xs" style="color: var(--ink-soft);">
                        <i data-lucide="moon" class="mt-px h-3.5 w-3.5 flex-shrink-0" aria-hidden="true"></i>
                        <span>The networks hold text messages between 7:45pm and 8:00am. Email is faster right now.</span>
                    </p>
                @endif
            </div>

            <div class="mt-8 flex items-center justify-between border-t pt-6" style="border-color: var(--border);">
                <form action="{{ route('laas.login.otp.cancel') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold"
                            style="color: var(--ink-soft);">
                        <i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>
                        Use a different account
                    </button>
                </form>
                <button type="button" onclick="laasToggleTheme()" class="rounded-lg p-2" style="color: var(--ink-soft);">
                    <i data-lucide="sun" class="h-4 w-4 dark:hidden" aria-hidden="true"></i>
                    <i data-lucide="moon" class="hidden h-4 w-4 dark:block" aria-hidden="true"></i>
                    <span class="sr-only" data-theme-label>Dark mode</span>
                </button>
            </div>
        </div>
    </main>

    {{-- ---------- Assurance panel ---------- --}}
    <aside class="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-center"
           style="background: var(--brand-deep);">
        <div class="laas-grid-texture absolute inset-0" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -left-32 top-1/3 h-[460px] w-[460px] rounded-full opacity-35 blur-3xl"
             style="background: radial-gradient(circle, rgba(255,255,255,.06) 0%, transparent 70%);" aria-hidden="true"></div>

        <div class="relative px-14 py-16">
            <p class="mb-5 inline-flex items-center gap-2.5 rounded-full border py-2 pl-2 pr-4"
               style="border-color: rgba(255,255,255,.22); background: rgba(255,255,255,.06);">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white">
                    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="" aria-hidden="true"
                         class="h-5 w-5 rounded-full object-contain">
                </span>
                <span class="laas-eyebrow" style="color: var(--on-deep-soft);">Two-step sign-in</span>
            </p>

            <h2 class="max-w-md text-4xl font-black leading-tight text-white">
                Your password proves who you are. The code proves it is you holding the phone.
            </h2>

            <ol class="mt-10 space-y-5">
                @foreach([
                    ['done', 'Password checked'],
                    ['now',  $sentByEmail ? 'Code sent to your email' : 'Code sent to your phone'],
                    ['next', 'Into your application'],
                ] as [$state, $label])
                    <li class="flex items-center gap-4">
                        @if ($state === 'done')
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full"
                                  style="background: rgba(255,255,255,.92);">
                                <i data-lucide="check" class="h-4 w-4" style="color: var(--brand-deep);" aria-hidden="true"></i>
                            </span>
                            <span class="text-sm" style="color: var(--on-deep-soft);">{{ $label }}</span>
                        @elseif ($state === 'now')
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full text-sm font-bold text-white ring-4"
                                  style="background: rgba(255,255,255,.18); --tw-ring-color: rgba(255,255,255,.12);">2</span>
                            <span class="text-sm font-bold text-white">{{ $label }}</span>
                        @else
                            <span class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full border-2 text-sm font-bold"
                                  style="border-color: rgba(255,255,255,.28); color: rgba(255,255,255,.55);">3</span>
                            <span class="text-sm" style="color: rgba(255,255,255,.55);">{{ $label }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>

            <div class="mt-12 flex items-start gap-3 rounded-xl border p-4"
                 style="border-color: rgba(255,255,255,.18); background: rgba(255,255,255,.06);">
                <i data-lucide="shield-alert" class="h-5 w-5 flex-shrink-0" style="color: var(--on-deep-soft);" aria-hidden="true"></i>
                <p class="text-sm leading-6" style="color: var(--on-deep-soft);">
                    <span class="font-bold text-white">Never share this code.</span>
                    Nobody from the Ministry, and no agent, will ever ask you for it.
                </p>
            </div>

            <div class="mt-10 flex items-center gap-3 border-t pt-7" style="border-color: rgba(255,255,255,.16);">
                <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="" aria-hidden="true" class="h-8 w-auto">
                <p class="text-xs font-semibold" style="color: var(--on-deep-soft);">
                    Kano State Ministry of Land &amp; Physical Planning<br>Federal Republic of Nigeria
                </p>
            </div>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
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
            document.getElementById('verifyLabel').textContent = 'Verifying…';
            form.submit();
        }

        // Spread a run of digits across the boxes from `start` (typing, paste, SMS autofill).
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
                expiry.style.color = 'var(--danger)';
                expiry.style.fontWeight = '600';
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
@endpush
