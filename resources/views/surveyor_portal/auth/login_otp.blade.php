@extends('surveyor_portal.layouts.guest')

@section('title', 'Sign-in code — MLPP-SG Portal')

{{-- Steps 2-3 of signing in: the password has been checked, the surveyor is not signed in yet.
     See SurveyorLoginOtpService / SurveyorPortal\LoginOtpController. --}}
@push('styles')
<style>
    .ks-otp-boxes { display:flex; justify-content:space-between; gap:8px; }
    .ks-otp-box { width:100%; max-width:52px; height:56px; padding:0; border:2px solid #D5DAE0; border-radius:10px; background:#F7F8FA; color:var(--ks-ink);
        font:700 24px/1 ui-monospace, SFMono-Regular, Menlo, monospace; text-align:center; caret-color:transparent; transition:border-color .15s, box-shadow .15s, background .15s; }
    .ks-otp-box:focus { border-color:var(--ks-blue); outline:0; background:#fff; box-shadow:0 0 0 3px rgba(201,154,30,.35); }
    .ks-otp-box.filled { border-color:#8E97A1; background:#fff; }
    .ks-otp-error .ks-otp-box { border-color:#F87171; background:#FEF2F2; }
    @keyframes ks-otp-shake { 10%,90% { transform:translateX(-1px); } 20%,80% { transform:translateX(2px); } 30%,50%,70% { transform:translateX(-4px); } 40%,60% { transform:translateX(4px); } }
    .ks-otp-shake { animation:ks-otp-shake .45s both; }
    .ks-otp-status { display:flex; align-items:center; gap:4px; margin-top:10px; color:var(--ks-soft); font-size:12px; }
    .ks-otp-help { margin-top:20px; padding:12px 14px; border-radius:10px; background:#F5F6F7; font-size:14px; }
    .ks-otp-help form { display:inline; }
    .ks-otp-link { padding:0; border:0; background:none; color:var(--ks-blue); font-weight:700; text-decoration:underline; cursor:pointer; }
    .ks-otp-link:disabled { color:#9CA3AF; text-decoration:none; cursor:not-allowed; }
    .ks-otp-fallback { margin-top:14px; padding:12px 14px; border:1px solid #F3D98B; border-radius:10px; background:#FDF7E3; color:#5C4708; font-size:14px; }
    .ks-otp-fallback a { color:#5C4708; font-weight:700; text-decoration:underline; }
    #ksVerifyBtn:disabled { opacity:.45; cursor:not-allowed; }
</style>
@endpush

@section('card')
    <h1 class="mb-1">Enter your sign-in code</h1>
    <p class="ks-help mb-5">
        @if ($channel === 'email' && $maskedEmail)
            We emailed a {{ $codeLength }}-digit code to <strong style="color:var(--ks-ink);white-space:nowrap;">{{ $maskedEmail }}</strong>.
        @elseif ($maskedPhone)
            We texted a {{ $codeLength }}-digit code to <strong style="color:var(--ks-ink);white-space:nowrap;">{{ $maskedPhone }}</strong>.
        @else
            Enter the {{ $codeLength }}-digit code we sent you.
        @endif
    </p>

    @if (session('error') || $errors->has('code'))
        <div class="ks-flash bad" role="alert">{{ session('error') ?: $errors->first('code') }}</div>
    @endif

    <form method="POST" action="{{ route('surveyor-portal.login.otp.verify') }}" id="ksOtpForm">
        @csrf
        <input type="hidden" name="code" id="ksOtpCode">
        <label class="sr-only" for="ksOtp0">Sign-in code</label>
        <div id="ksOtpBoxes" class="ks-otp-boxes {{ session('error') ? 'ks-otp-error ks-otp-shake' : '' }}">
            @for ($i = 0; $i < $codeLength; $i++)
                <input id="ksOtp{{ $i }}" class="ks-otp-box" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                    {{ $i === 0 ? 'autocomplete=one-time-code autofocus' : 'autocomplete=off' }} aria-label="Digit {{ $i + 1 }}">
            @endfor
        </div>
        <p class="ks-otp-status" aria-live="polite"><span id="ksOtpExpiry" data-seconds="{{ $expiresIn }}"></span></p>
        <button class="ks-btn ks-btn-primary w-full mt-4" id="ksVerifyBtn" disabled><i data-lucide="shield-check" class="w-4 h-4"></i> <span id="ksVerifyLabel">Verify and sign in</span></button>
    </form>

    <div class="ks-otp-help">
        <p style="font-weight:600;">Didn't get a code?</p>
        <div style="margin-top:4px;">
            <form method="POST" action="{{ route('surveyor-portal.login.otp.resend') }}">
                @csrf
                <input type="hidden" name="channel" value="{{ $channel === 'email' ? 'email' : 'sms' }}">
                <button class="ks-otp-link" id="ksResendBtn" data-wait="{{ $retryAfter }}"><span id="ksResendLabel">{{ $channel === 'email' ? 'Resend email' : 'Resend SMS' }}</span></button>
            </form>
            @if ($channel === 'email' ? $maskedPhone : $maskedEmail)
                <span style="margin:0 8px;color:#C4C9CF;">|</span>
                <form method="POST" action="{{ route('surveyor-portal.login.otp.resend') }}">
                    @csrf
                    <input type="hidden" name="channel" value="{{ $channel === 'email' ? 'sms' : 'email' }}">
                    <button class="ks-otp-link">{{ $channel === 'email' ? 'Text it to my phone' : 'Email it to me instead' }}</button>
                </form>
            @endif
        </div>
        @if ($quietHours && $channel !== 'email' && $maskedEmail)
            <p class="ks-help" style="margin-top:6px;font-size:12px;color:#7A5B00;">Networks hold SMS between 7:45pm and 8:00am. Email is faster right now.</p>
        @endif
    </div>

    {{-- The security-question fallback, shown once the sign-in has waited long enough (SurveyorSecurityQuestions). --}}
    @if (!is_null($questionsOpenIn))
        <div id="ksQuestionsFallback" class="ks-otp-fallback" data-seconds="{{ $questionsOpenIn }}" @if ($questionsOpenIn > 0) hidden @endif>
            Experiencing network delays?
            <a href="{{ route('surveyor-portal.login.questions') }}">Click here to verify your identity using your security questions.</a>
        </div>
    @endif

    <form method="POST" action="{{ route('surveyor-portal.login.otp.cancel') }}" class="ks-auth-alt">
        @csrf
        <button class="ks-otp-link" style="color:var(--ks-soft);font-weight:600;text-decoration:none;">&larr; Use a different account</button>
    </form>
    <p class="ks-help" style="margin-top:10px;font-size:12px;text-align:center;">Never share this code. Nobody from the Ministry will ever ask you for it.</p>
@endsection

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('ksOtpForm'), hidden = document.getElementById('ksOtpCode'),
            wrap = document.getElementById('ksOtpBoxes'), button = document.getElementById('ksVerifyBtn'),
            boxes = Array.prototype.slice.call(wrap.querySelectorAll('.ks-otp-box')), submitting = false;

        function refresh() {
            boxes.forEach(function (b) { b.classList.toggle('filled', b.value !== ''); });
            hidden.value = boxes.map(function (b) { return b.value; }).join('');
            button.disabled = hidden.value.length !== boxes.length || submitting;
        }
        function submit() {
            if (submitting || hidden.value.length !== boxes.length) return;
            submitting = true; button.disabled = true;
            document.getElementById('ksVerifyLabel').textContent = 'Verifying…';
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
                wrap.classList.remove('ks-otp-error');
                var digits = box.value.replace(/\D+/g, '');
                box.value = '';
                if (digits) { fill(i, digits); } else { refresh(); }
            });
            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && box.value === '' && i > 0) { boxes[i - 1].value = ''; boxes[i - 1].focus(); refresh(); e.preventDefault(); }
                else if (e.key === 'ArrowLeft' && i > 0) { boxes[i - 1].focus(); e.preventDefault(); }
                else if (e.key === 'ArrowRight' && i < boxes.length - 1) { boxes[i + 1].focus(); e.preventDefault(); }
                else if (e.key === 'Enter') { submit(); e.preventDefault(); }
            });
            box.addEventListener('paste', function (e) {
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D+/g, '');
                if (text) { e.preventDefault(); fill(i, text); }
            });
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); submit(); });

        var expiry = document.getElementById('ksOtpExpiry'), left = parseInt(expiry.getAttribute('data-seconds'), 10) || 0;
        (function tick() {
            if (left <= 0) { expiry.textContent = 'This code has expired. Ask for a new one below.'; expiry.style.color = 'var(--ks-danger)'; return; }
            var m = Math.floor(left / 60), s = left % 60;
            expiry.textContent = 'Expires in ' + m + ':' + (s < 10 ? '0' : '') + s;
            left -= 1; setTimeout(tick, 1000);
        })();

        // Resend stays disabled for the cooldown the server reported.
        var resend = document.getElementById('ksResendBtn'), label = document.getElementById('ksResendLabel'),
            text = label.textContent, wait = parseInt(resend.getAttribute('data-wait'), 10) || 0;
        (function resendTick() {
            if (wait <= 0) { resend.disabled = false; label.textContent = text; return; }
            resend.disabled = true; label.textContent = text + ' in ' + wait + 's';
            wait -= 1; setTimeout(resendTick, 1000);
        })();

        var fallback = document.getElementById('ksQuestionsFallback');
        var fallbackWait = fallback ? (parseInt(fallback.getAttribute('data-seconds'), 10) || 0) : 0;
        if (fallback && fallbackWait > 0) setTimeout(function () { fallback.hidden = false; }, fallbackWait * 1000);
    })();
</script>
@endpush
