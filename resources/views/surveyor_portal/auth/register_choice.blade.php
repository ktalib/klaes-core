@extends('surveyor_portal.layouts.guest')

@section('title', 'Register — MLPP-SG Portal')
@section('width', '520px')

@section('guest-banner')
    <img src="{{ asset($portalSettings['logo_registration_banner']) }}"
         alt="{{ $portalSettings['brand_name'] }} — {{ $portalSettings['office_name'] }} Portal"
         width="1600" height="666" class="relative"
         style="width:100%;max-width:620px;height:auto;mix-blend-mode:multiply;">
@endsection

@section('guest-mobile-banner')
    <img src="{{ asset($portalSettings['logo_registration_banner']) }}"
         alt="{{ $portalSettings['brand_name'] }} — {{ $portalSettings['office_name'] }} Portal"
         width="1600" height="666" class="mx-auto mb-5 lg:hidden"
         style="width:100%;max-width:460px;height:auto;mix-blend-mode:multiply;">
@endsection

@section('card')
    <h1 class="mb-1">Is your surveying company registered?</h1>
    <p class="ks-help mb-5">If it is registered and approved, continue to choose your company. Otherwise, register the company first.</p>
    <div class="grid sm:grid-cols-2 gap-3">
        <a class="ks-choice primary" href="{{ route('surveyor-portal.register', ['company' => 'yes']) }}">
            <span class="ks-choice-icon"><i data-lucide="user-check" class="w-5 h-5"></i></span>
            <span class="ks-choice-title">Yes, register</span>
            <i data-lucide="arrow-right" class="ks-choice-arrow w-4 h-4"></i>
        </a>
        <a class="ks-choice" href="{{ route('surveyor-portal.register', ['company' => 'no']) }}">
            <span class="ks-choice-icon"><i data-lucide="building-2" class="w-5 h-5"></i></span>
            <span class="ks-choice-title">No, register a company</span>
            <i data-lucide="arrow-right" class="ks-choice-arrow w-4 h-4"></i>
        </a>
    </div>
    <p class="ks-help ks-auth-alt"><a href="{{ route('surveyor-portal.login') }}" data-signin-dialog-trigger style="color:var(--ks-blue);font-weight:600;">Already registered? Sign in</a></p>
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
