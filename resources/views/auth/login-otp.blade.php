<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KLAES - Sign-in code</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    .otp-box { caret-color: transparent; transition: border-color .15s, box-shadow .15s, background-color .15s; }
    .otp-box:focus { outline: none; border-color: #374151; box-shadow: 0 0 0 3px rgba(55, 65, 81, .18); background: #fff; }
    .otp-box.filled { border-color: #6b7280; background: #fff; }
    .otp-error .otp-box { border-color: #f87171; background: #fef2f2; }
    @keyframes otp-shake { 10%, 90% { transform: translateX(-1px); } 20%, 80% { transform: translateX(2px); } 30%, 50%, 70% { transform: translateX(-4px); } 40%, 60% { transform: translateX(4px); } }
    .otp-shake { animation: otp-shake .45s both; }
    @keyframes otp-spin { to { transform: rotate(360deg); } }
    .otp-spin { animation: otp-spin .8s linear infinite; }
    /* Tailwind 2.2 CDN ships no disabled: variants. */
    #verifyButton:disabled { opacity: .4; cursor: not-allowed; }
    #resendButton:disabled { color: #9ca3af; text-decoration: none; cursor: not-allowed; }
  </style>
</head>
<body class="bg-gray-100">
  {{-- Steps 2–3 of signing in: the password has been checked, the session is not signed in
       yet. See LoginOtpService / LoginOtpController. Same split layout as auth.login. --}}
  <div class="min-h-screen flex bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200">

    {{-- Left: the code form --}}
    <div class="w-full md:w-1/2 flex items-center justify-center px-4 py-8">
      <div class="w-full max-w-md bg-white rounded-xl shadow-lg px-6 py-8 sm:px-8">

        <div class="flex items-center justify-between mb-6">
          <div class="flex items-center">
            <img src="{{ asset('storage/upload/logo/Klase.png') }}" alt="KLAES Logo" class="w-9 h-9 rounded-md object-cover">
            <span class="ml-2 text-sm font-bold tracking-wide text-gray-900">KLAES</span>
          </div>
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Step 2 of 2</span>
        </div>

        <h1 class="text-2xl font-bold text-gray-900">Enter your sign-in code</h1>
        <p class="mt-2 text-sm leading-relaxed text-gray-600">
          @if ($channel === 'email' && $maskedEmail)
            We emailed a {{ $codeLength }}-digit code to
            <span class="font-semibold text-gray-900 whitespace-nowrap">{{ $maskedEmail }}</span>.
          @else
            We texted a {{ $codeLength }}-digit code to
            <span class="font-semibold text-gray-900 whitespace-nowrap">{{ $maskedPhone }}</span>.
          @endif
        </p>

        @if (session('error') || $errors->has('code'))
          <div class="mt-5 flex items-start rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" role="alert">
            <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            <span>{{ session('error') ?: $errors->first('code') }}</span>
          </div>
        @endif

        <form action="{{ route('login.otp.verify') }}" method="post" id="otpForm" class="mt-6">
          @csrf
          <input type="hidden" name="code" id="code">

          <label class="sr-only" for="otp-0">Sign-in code</label>
          <div id="otpBoxes" class="flex justify-between {{ session('error') ? 'otp-error otp-shake' : '' }}">
            @for ($i = 0; $i < $codeLength; $i++)
              <input id="otp-{{ $i }}" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1"
                {{ $i === 0 ? 'autocomplete=one-time-code autofocus' : 'autocomplete=off' }}
                aria-label="Digit {{ $i + 1 }}"
                class="otp-box w-10 h-12 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-semibold font-mono text-gray-900 border-2 border-gray-200 rounded-lg bg-gray-50">
            @endfor
          </div>

          {{-- Status: sent / countdown / expired. Replaces a banner repeating the line above. --}}
          <p id="otpStatus" class="mt-3 flex items-center text-xs text-gray-500" aria-live="polite">
            @if (session('success'))
              <svg class="w-3.5 h-3.5 mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
              <span class="text-green-700 font-medium mr-1">Code sent.</span>
            @endif
            <span id="otpExpiry" data-seconds="{{ $expiresIn }}"></span>
          </p>

          <button type="submit" id="verifyButton" disabled
            class="mt-5 w-full inline-flex items-center justify-center py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-700 transition">
            <svg id="verifySpinner" class="hidden otp-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 00-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <span id="verifyLabel">Verify and sign in</span>
          </button>
        </form>

        {{-- Didn't get it --}}
        <div class="mt-6 rounded-lg bg-gray-50 px-4 py-3">
          <p class="text-sm text-gray-700 font-medium">Didn't get a code?</p>
          <div class="mt-1 flex flex-wrap items-center text-sm">
            <form action="{{ route('login.otp.resend') }}" method="post">
              @csrf
              <input type="hidden" name="channel" value="{{ $channel === 'email' ? 'email' : 'sms' }}">
              <button type="submit" id="resendButton" data-wait="{{ $retryAfter }}"
                class="font-semibold text-gray-900 underline hover:text-gray-600">
                <span id="resendLabel">{{ $channel === 'email' ? 'Resend email' : 'Resend SMS' }}</span>
              </button>
            </form>

            @if ($channel === 'email' || $maskedEmail)
              <span class="mx-2 text-gray-300">|</span>
              <form action="{{ route('login.otp.resend') }}" method="post">
                @csrf
                @if ($channel === 'email')
                  <input type="hidden" name="channel" value="sms">
                  <button type="submit" class="font-semibold text-gray-900 underline hover:text-gray-600">Text it to my phone</button>
                @else
                  <input type="hidden" name="channel" value="email">
                  <button type="submit" class="font-semibold text-gray-900 underline hover:text-gray-600">Email it to me instead</button>
                @endif
              </form>
            @endif
          </div>

          @if ($quietHours && $channel !== 'email' && $maskedEmail)
            <p class="mt-2 flex items-start text-xs text-yellow-800">
              <svg class="w-3.5 h-3.5 mr-1 mt-px flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
              Networks hold SMS between 7:45pm and 8:00am. Email is faster right now.
            </p>
          @endif
        </div>

        <form action="{{ route('login.otp.cancel') }}" method="post" class="mt-5 text-center">
          @csrf
          <button type="submit" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-800">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            Use a different account
          </button>
        </form>
      </div>
    </div>

    {{-- Right: what this step is for (desktop only, like the login page's panel) --}}
    <div class="hidden md:flex md:w-1/2 items-center justify-center bg-gray-100 px-12 py-8">
      <div class="w-full max-w-sm">
        <img src="{{ asset('storage/upload/logo/Klase.png') }}" alt="LAnd ADmin Enterprise System" class="h-16 w-auto rounded-md shadow-sm mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Two-step sign-in</h2>
        <p class="mt-2 text-gray-600 leading-relaxed">
          Your password proves who you are. The code proves you have your phone.
          Together they keep land records safe, even if a password is leaked.
        </p>

        <ol class="mt-8 space-y-5">
          <li class="flex items-center">
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white flex-shrink-0">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
            </span>
            <span class="ml-3 text-sm text-gray-600">Password checked</span>
          </li>
          <li class="flex items-center">
            <span class="flex items-center justify-center w-8 h-8 rounded-full bg-gray-800 text-white text-sm font-semibold flex-shrink-0 ring-4 ring-gray-300">2</span>
            <span class="ml-3 text-sm font-semibold text-gray-900">Code sent to your phone</span>
          </li>
          <li class="flex items-center">
            <span class="flex items-center justify-center w-8 h-8 rounded-full border-2 border-gray-300 text-gray-400 text-sm font-semibold flex-shrink-0">3</span>
            <span class="ml-3 text-sm text-gray-500">Into KLAES</span>
          </li>
        </ol>

        <div class="mt-10 flex items-start rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-600">
          <svg class="w-5 h-5 mr-3 text-gray-700 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span><span class="font-semibold text-gray-900">Never share this code.</span> Nobody from the Ministry or ICT will ever ask you for it.</span>
        </div>
      </div>
    </div>
  </div>

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
        (boxes[Math.min(i, boxes.length - 1)]).focus();
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
      function expiryTick() {
        if (left <= 0) {
          expiry.textContent = 'This code has expired. Ask for a new one below.';
          expiry.className = 'text-red-600 font-medium';
          return;
        }
        var m = Math.floor(left / 60), s = left % 60;
        expiry.textContent = 'Expires in ' + m + ':' + (s < 10 ? '0' : '') + s;
        left -= 1;
        setTimeout(expiryTick, 1000);
      }
      expiryTick();

      // Resend stays disabled for the cooldown the server reported.
      var resend = document.getElementById('resendButton');
      var label = document.getElementById('resendLabel');
      var text = label.textContent;
      var wait = parseInt(resend.getAttribute('data-wait'), 10) || 0;
      function resendTick() {
        if (wait <= 0) { resend.disabled = false; label.textContent = text; return; }
        resend.disabled = true;
        label.textContent = text + ' in ' + wait + 's';
        wait -= 1;
        setTimeout(resendTick, 1000);
      }
      resendTick();
    });
  </script>
</body>
</html>
