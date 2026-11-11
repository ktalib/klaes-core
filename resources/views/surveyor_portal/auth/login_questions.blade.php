@extends('surveyor_portal.layouts.guest')

@section('title', 'Security questions — MLPP-SG Portal')

{{-- The sign-in code fallback: the password has been checked, the surveyor is not signed in yet.
     See SurveyorPortal\SecurityQuestionsController::challenge / verify. --}}
@section('card')
    <h1 class="mb-1">Verify with your security questions</h1>
    <p class="ks-help mb-5">Answer every question exactly as you set it. Capital letters and extra spaces do not matter.</p>

    @if (session('error'))
        <div class="ks-flash bad" role="alert">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('surveyor-portal.login.questions.verify') }}" class="space-y-4" id="ksQuestionsForm">
        @csrf
        @foreach ($questions as $key => $question)
            <div>
                <label class="ks-label" for="answer-{{ $key }}">{{ $loop->iteration }}. {{ $question }}</label>
                <input class="ks-input" id="answer-{{ $key }}" name="answers[{{ $key }}]" type="text" required autocomplete="off" spellcheck="false"
                    @if ($loop->first) autofocus @endif @disabled($lockedFor > 0)>
            </div>
        @endforeach
        <button class="ks-btn ks-btn-primary w-full" id="ksQuestionsBtn" @disabled($lockedFor > 0)><i data-lucide="shield-check" class="w-4 h-4"></i> <span>Verify and sign in</span></button>
    </form>

    <div class="ks-auth-alt" style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px;font-size:14px;">
        <a href="{{ route('surveyor-portal.login.otp') }}" style="color:var(--ks-blue);font-weight:600;">&larr; Back to the sign-in code</a>
        <form method="POST" action="{{ route('surveyor-portal.login.otp.cancel') }}">
            @csrf
            <button style="border:0;background:none;color:var(--ks-soft);font-weight:600;cursor:pointer;">Use a different account</button>
        </form>
    </div>
    <p class="ks-help" style="margin-top:12px;font-size:12px;text-align:center;">
        After {{ config('security_questions.max_attempts', 3) }} wrong tries the questions lock for {{ config('security_questions.lock_minutes', 30) }} minutes; the sign-in code still works.
    </p>
@endsection

@push('scripts')
<script>
    document.getElementById('ksQuestionsForm').addEventListener('submit', function () {
        var b = document.getElementById('ksQuestionsBtn');
        b.disabled = true;
        b.lastElementChild.textContent = 'Verifying…';
    });
</script>
@endpush
