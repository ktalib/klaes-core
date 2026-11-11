<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KLAES-CORE - Security questions</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/logo/klas_core_logo.png') }}">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <style>
    /* Tailwind 2.2 CDN ships no disabled: variants. */
    #verifyButton:disabled { opacity: .4; cursor: not-allowed; }
  </style>
</head>
<body class="bg-gray-100">
  {{-- The sign-in code fallback: the password has been checked, the session is not signed in yet.
       See SecurityQuestionsController::challenge / verify. Same look as auth.login-otp. --}}
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-50 via-gray-100 to-gray-200 px-4 py-8">
    <div class="w-full max-w-lg bg-white rounded-xl shadow-lg px-6 py-8 sm:px-8">

      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center">
          <img src="{{ asset('assets/logo/klas_core_logo.png') }}" alt="KLAES-CORE Logo" class="w-16 h-10 rounded-md object-contain">
          <span class="ml-2 text-sm font-bold tracking-wide text-gray-900">KLAES-CORE</span>
        </div>
        <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Step 2 of 2</span>
      </div>

      <h1 class="text-2xl font-bold text-gray-900">Verify with your security questions</h1>
      <p class="mt-2 text-sm leading-relaxed text-gray-600">
        Answer every question exactly as you set it. Capital letters and extra spaces do not matter.
      </p>

      @if (session('error'))
        <div class="mt-5 flex items-start rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700" role="alert">
          <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
          <span>{{ session('error') }}</span>
        </div>
      @endif

      <form action="{{ route('login.questions.verify') }}" method="post" class="mt-6 space-y-5" id="questionsForm">
        @csrf
        @foreach ($questions as $key => $question)
          <div>
            <label for="answer-{{ $key }}" class="block text-sm font-semibold text-gray-800">{{ $loop->iteration }}. {{ $question }}</label>
            <input id="answer-{{ $key }}" name="answers[{{ $key }}]" type="text" required autocomplete="off" spellcheck="false"
                   @if ($loop->first) autofocus @endif @disabled($lockedFor > 0)
                   class="mt-1 w-full rounded-lg border-2 border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-gray-700 focus:bg-white focus:outline-none">
          </div>
        @endforeach

        <button type="submit" id="verifyButton" @disabled($lockedFor > 0)
          class="w-full inline-flex items-center justify-center py-2.5 px-4 rounded-lg text-sm font-semibold text-white bg-gray-800 hover:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-700 transition">
          Verify and sign in
        </button>
      </form>

      <div class="mt-6 flex flex-wrap items-center justify-between gap-2 text-sm">
        <a href="{{ route('login.otp') }}" class="inline-flex items-center text-gray-500 hover:text-gray-800">
          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
          Back to the sign-in code
        </a>
        <form action="{{ route('login.otp.cancel') }}" method="post">
          @csrf
          <button type="submit" class="text-gray-500 hover:text-gray-800">Use a different account</button>
        </form>
      </div>

      <div class="mt-6 flex items-start rounded-lg border border-gray-200 bg-gray-50 p-3 text-xs text-gray-600">
        <svg class="w-4 h-4 mr-2 text-gray-700 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>After {{ config('security_questions.max_attempts', 3) }} wrong tries the questions lock for {{ config('security_questions.lock_minutes', 30) }} minutes. Forgotten your answers? Ask ICT to sign you in with a code.</span>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('questionsForm').addEventListener('submit', function () {
      var button = document.getElementById('verifyButton');
      button.disabled = true;
      button.textContent = 'Verifying…';
    });
  </script>
</body>
</html>
