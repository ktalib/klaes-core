@extends('surveyor_portal.layouts.guest')

@section('title', 'Register a Company — KSSGOF Portal')
@section('width', '600px')

@section('guest-banner')
    <div style="width:100%;max-width:540px;position:relative;">
        <div class="ks-company-banner-brand">
            <img src="{{ asset('assets/logo/kssgof-wordmark.png') }}" alt="KSSGOF, Kano State Surveyor General's Office Portal" width="900" height="300">
        </div>
        <div class="ks-company-banner-eyebrow"><i data-lucide="building-2" class="w-4 h-4"></i> COMPANY REGISTRATION</div>
        <h2 class="ks-company-banner-title">Your company’s next step starts here.</h2>
        <p class="ks-company-banner-copy">Submit your registered company for review by the Office of the Surveyor General. Once approved, surveyors can select it when they register.</p>
        <div class="ks-company-banner-steps">
            <div><span class="ks-company-step-icon"><i data-lucide="file-check-2" class="w-5 h-5"></i></span><span><strong>Submit your details</strong><small>Use the company’s registered name and RC number.</small></span></div>
            <div><span class="ks-company-step-icon green"><i data-lucide="shield-check" class="w-5 h-5"></i></span><span><strong>Await SG approval</strong><small>Your application is reviewed before listing.</small></span></div>
            <div><span class="ks-company-step-icon gold"><i data-lucide="users-round" class="w-5 h-5"></i></span><span><strong>Register your surveyors</strong><small>Approved companies become selectable in registration.</small></span></div>
        </div>
    </div>
@endsection

@section('guest-mobile-banner')
    <div class="ks-company-mobile-banner">
        <img src="{{ asset('assets/logo/kssgof-logo.png') }}" alt="" width="44" height="44">
        <div>
            <div class="ks-company-mobile-eyebrow">KSSGOF · COMPANY REGISTRATION</div>
            <div class="ks-company-mobile-title">Register your surveying company</div>
            <div class="ks-company-mobile-copy">Submit the company details for SG approval.</div>
        </div>
    </div>
@endsection

@section('card')
    <h1 class="text-lg font-bold mb-1">Register a surveying company</h1>
    <p class="ks-help mb-4">The Office of the Surveyor General must approve the company before surveyors can register under it.</p>
    <form method="POST" action="{{ route('surveyor-portal.company.submit') }}" class="space-y-4" id="ksCompanyRegisterForm">
        @csrf
        <div>
            <label class="ks-label" for="company_name">Registered Company Name <span class="ks-req">*</span></label>
            <input class="ks-input" id="company_name" name="name" value="{{ old('name') }}" maxlength="255" required autocomplete="organization">
            @error('name')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="company_rc_no">RC No. <span class="ks-req">*</span></label>
            <input class="ks-input" id="company_rc_no" name="company_rc_no" value="{{ old('company_rc_no') }}" maxlength="50" required placeholder="RC 123456">
            @error('company_rc_no')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="company_phone">Company Phone <span class="ks-req">*</span></label>
                <input class="ks-input" id="company_phone" name="phone" value="{{ old('phone') }}" maxlength="30" required autocomplete="tel">
                @error('phone')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="company_email">Company Email <span class="ks-req">*</span></label>
                <input class="ks-input" id="company_email" type="email" name="email" value="{{ old('email') }}" maxlength="150" required autocomplete="email">
                @error('email')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        @include('surveyor_portal.partials._address_builder')
        <button class="ks-btn ks-btn-primary w-full">Submit Company for Approval</button>
    </form>
    <p class="ks-help text-center mt-4">Already approved? <a href="{{ route('surveyor-portal.register', ['type' => 'Private', 'company' => 'yes']) }}" style="color:var(--ks-blue);font-weight:600;">Register as a surveyor</a></p>
@endsection

@push('styles')
<style>
    .ks-company-banner-brand { width:100%; padding:16px 20px; border:1px solid #E5E9EF; border-radius:16px; background:#fff; box-shadow:0 12px 32px rgba(11,58,126,.08); }
    .ks-company-banner-brand img { display:block; width:100%; max-width:470px; height:auto; margin:0 auto; mix-blend-mode:multiply; }
    .ks-company-banner-eyebrow { display:flex; align-items:center; gap:8px; margin-top:34px; color:var(--ks-blue); font-size:11px; font-weight:800; letter-spacing:.14em; }
    .ks-company-banner-title { max-width:470px; margin-top:13px; color:#102A4C; font-size:clamp(28px,3vw,40px); font-weight:800; letter-spacing:-.035em; line-height:1.12; }
    .ks-company-banner-copy { max-width:460px; margin-top:12px; color:var(--ks-soft); font-size:15px; line-height:1.7; }
    .ks-company-banner-steps { display:grid; gap:12px; margin-top:26px; }
    .ks-company-banner-steps > div { display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #E6EAF0; border-radius:12px; background:rgba(255,255,255,.86); }
    .ks-company-step-icon { display:grid; flex:0 0 40px; width:40px; height:40px; place-items:center; border-radius:11px; background:#EAF1FB; color:var(--ks-blue); }
    .ks-company-step-icon.green { background:#EAF6EE; color:var(--ks-green); }
    .ks-company-step-icon.gold { background:#FBF4E2; color:#9A7414; }
    .ks-company-banner-steps strong, .ks-company-banner-steps small { display:block; }
    .ks-company-banner-steps strong { color:var(--ks-ink); font-size:13px; }
    .ks-company-banner-steps small { margin-top:2px; color:var(--ks-soft); font-size:12px; line-height:1.45; }
    .ks-company-mobile-banner { display:flex; align-items:center; gap:13px; margin:0 0 18px; padding:15px 16px; border-radius:14px; background:linear-gradient(120deg,#082B5E,#0B3A7E 70%,#1453A0); color:#fff; box-shadow:0 12px 26px rgba(8,43,94,.18); }
    .ks-company-mobile-banner img { flex:0 0 44px; width:44px; height:44px; border:2px solid rgba(201,154,30,.8); border-radius:50%; }
    .ks-company-mobile-eyebrow { color:#E7C86F; font-size:9px; font-weight:800; letter-spacing:.12em; }
    .ks-company-mobile-title { margin-top:2px; font-size:15px; font-weight:800; line-height:1.25; }
    .ks-company-mobile-copy { margin-top:3px; color:rgba(255,255,255,.82); font-size:11px; }
    .ks-address { border: 1px solid var(--ks-line); border-radius: 10px; padding: 14px; background: #FAFBFC; }
    .ks-address .ab-legend { font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ks-blue); margin-bottom: 10px; }
</style>
@endpush

@push('scripts')
    @include('survey_module.partials._address_builder_js')
@endpush
