@extends('surveyor_portal.layouts.guest')

{{-- /login (user, 2026-11-11): company or surveyor first, as the Sign in pop-up asks; this page is what shows
     without JavaScript and where sign-outs and expired sessions land. Styled as register_choice. --}}
@section('title', 'Sign in — MLPP-SG Portal')
@section('width', '520px')

@section('card')
    <h1 class="mb-1">Sign in as</h1>
    <p class="ks-help mb-5">Companies and surveyors have their own sign-in.</p>
    <div class="grid sm:grid-cols-2 gap-3">
        <a class="ks-choice primary" href="{{ route('surveyor-portal.login.as', 'surveyor') }}">
            <span class="ks-choice-icon"><i data-lucide="user-round" class="w-5 h-5"></i></span>
            <span class="ks-choice-title">Surveyor</span>
            <i data-lucide="arrow-right" class="ks-choice-arrow w-4 h-4"></i>
        </a>
        <a class="ks-choice" href="{{ route('surveyor-portal.login.as', 'company') }}">
            <span class="ks-choice-icon"><i data-lucide="building-2" class="w-5 h-5"></i></span>
            <span class="ks-choice-title">Company</span>
            <i data-lucide="arrow-right" class="ks-choice-arrow w-4 h-4"></i>
        </a>
    </div>
    <p class="ks-help ks-auth-alt">New here? <a href="{{ route('surveyor-portal.register') }}" style="color:var(--ks-blue);font-weight:600;" data-register-dialog-trigger>Register</a></p>
@endsection

@push('styles')
<style>
    .ks-choice { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; position: relative; padding: 18px; border: 1px solid var(--ks-line); border-radius: 12px; background: #fff; color: var(--ks-ink); text-decoration: none; transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
    .ks-choice:hover { border-color: var(--ks-blue); box-shadow: 0 10px 22px -14px rgba(11,58,126,.55); transform: translateY(-1px); }
    .ks-choice:focus-visible { outline: 2px solid var(--ks-gold); outline-offset: 2px; }
    .ks-choice.primary { border-color: var(--ks-blue); background: #F3F6FB; }
    .ks-choice-icon { display: grid; place-items: center; width: 40px; height: 40px; border-radius: 11px; background: #E8EDF5; color: var(--ks-blue); }
    .ks-choice.primary .ks-choice-icon { background: var(--ks-blue); color: #fff; }
    .ks-choice-title { font-size: 15px; font-weight: 750; }
    .ks-choice-arrow { position: absolute; top: 18px; right: 18px; color: #7A838C; }
    @media (prefers-reduced-motion: reduce) { .ks-choice { transition: none; } .ks-choice:hover { transform: none; } }
</style>
@endpush
