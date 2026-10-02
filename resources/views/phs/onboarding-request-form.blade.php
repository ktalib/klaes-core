@extends('phs.layouts.app')

@section('title', 'PHS Portal - Access Request')

 @section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: #f0f7f2; min-height: 100vh; }
    .dark body { background: #0f1117; }

    /* Dark mode overrides */
    .dark .navbar { background: rgba(17,24,39,.92); border-bottom-color: rgba(26,107,60,.15); }
    .dark .navbar-brand span { color: #e5e7eb; }
    .dark .mobile-menu { background: rgba(17,24,39,.97); border-bottom-color: rgba(26,107,60,.15); }
    .dark .btn-ghost { border-color: #374151; color: #d1d5db; }
    .dark .btn-ghost:hover { background: #1f2937; }
    .dark .form-card { background: #1f2937; border-color: rgba(26,107,60,.15); box-shadow: 0 4px 24px rgba(0,0,0,.3); }
    .dark .section-title { color: #f3f4f6; }
    .dark .section-title .icon { background: #14532d; }
    .dark .section-divider { background: #374151; }
    .dark .field-label { color: #9ca3af; }
    .dark .field-input, .dark .field-select, .dark .field-textarea { background: #111827; border-color: #374151; color: #f3f4f6; }
    .dark .field-input:focus, .dark .field-select:focus, .dark .field-textarea:focus { background: #1f2937; border-color: #1a6b3c; }
    .dark .field-input::placeholder, .dark .field-textarea::placeholder { color: #4b5563; }
    .dark .error-box { background: #1f0505; border-color: #7f1d1d; }
    .dark .btn-cancel { background: #1f2937; border-color: #374151; color: #d1d5db; }
    .dark .btn-cancel:hover { background: #374151; }
    .dark .step-badge { background: #14532d; color: #86efac; }

    .blob-1 { position: fixed; top: -120px; left: -120px; width: 480px; height: 480px; border-radius: 50%; background: #1a6b3c; opacity: .14; pointer-events: none; z-index: 0; }
    .blob-2 { position: fixed; bottom: -160px; right: -100px; width: 520px; height: 520px; border-radius: 50%; background: #06d6a0; opacity: .11; pointer-events: none; z-index: 0; }
    .blob-3 { position: fixed; top: 40%; right: 6%; width: 240px; height: 240px; border-radius: 50%; background: #1a6b3c; opacity: .06; pointer-events: none; z-index: 0; }

    .navbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; justify-content: space-between; padding: 10px 32px; background: rgba(255,255,255,.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(26,107,60,.10); box-shadow: 0 1px 0 rgba(0,0,0,.04); min-height: 96px; }
    .navbar-brand { display: flex; align-items: center; gap: 10px; }
    .navbar-brand img { height: 80px; width: auto; }
    .navbar-brand span { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600; color: #3d3a4e; letter-spacing: .04em; }
    .navbar-actions { display: flex; align-items: center; gap: 10px; }
    .btn-ghost { padding: 8px 18px; border: 1.5px solid #d0cfe8; border-radius: 8px; background: transparent; font-size: 13px; font-weight: 500; color: #3d3a4e; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-ghost:hover { background: #f0f7f2; }
    .btn-primary { padding: 8px 18px; border: none; border-radius: 8px; background: #1a6b3c; font-size: 13px; font-weight: 500; color: #fff; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-primary:hover { background: #155a32; }
    .mobile-menu-btn { display: none; border: none; background: transparent; padding: 8px; cursor: pointer; border-radius: 8px; }
    .mobile-menu { display: none; flex-direction: column; gap: 10px; padding: 16px 32px; background: rgba(255,255,255,.96); border-bottom: 1px solid rgba(26,107,60,.10); }
    .mobile-menu a { text-align: center; }
    @media (max-width: 768px) { .navbar-actions { display: none; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } .mobile-menu.open { display: flex; } }

    .page-wrapper { position: relative; z-index: 1; max-width: 1060px; margin: 0 auto; padding: 40px 20px 80px; }

    .step-badge { display: inline-flex; align-items: center; gap: 6px; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #1a6b3c; background: #e8f5ee; border-radius: 20px; padding: 5px 14px; margin-bottom: 28px; }
    .step-badge::before { content: ''; display: inline-block; width: 6px; height: 6px; background: #1a6b3c; border-radius: 50%; }

    .main-grid { display: grid; grid-template-columns: 1fr 1.55fr; gap: 24px; align-items: start; }
    @media (max-width: 820px) { .main-grid { grid-template-columns: 1fr; } }

    /* Sidebar */
    .sidebar-card { background: #1a6b3c; border-radius: 20px; padding: 32px 28px; color: #fff; position: sticky; top: 84px; }
    .sb-logo { height: 30px; width: auto; margin-bottom: 24px; filter: brightness(0) invert(1); opacity: .85; }
    .sb-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.55); margin-bottom: 10px; }
    .sb-title { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 700; line-height: 1.25; color: #fff; margin-bottom: 14px; }
    .sb-desc { font-size: 13px; line-height: 1.7; color: rgba(255,255,255,.72); margin-bottom: 24px; }
    .sb-video { margin-bottom: 24px; }
    .sb-video-el { width: 100%; border-radius: 12px; border: 1px solid rgba(255,255,255,.14); background: #000; display: block; }
    .sb-video a { color: #06d6a0; }

    /* Package cards in sidebar */
    .pkg-list { display: flex; flex-direction: column; gap: 10px; }
    .pkg-item { background: var(--card-bg, rgba(255,255,255,.10)); border: 1px solid rgba(255,255,255,.14); border-left: 4px solid var(--accent, rgba(255,255,255,.3)); border-radius: 12px; padding: 13px 15px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: filter .15s, box-shadow .15s; }
    .pkg-item:hover { filter: brightness(1.12); }
    .pkg-item.active { border-color: var(--accent, #fff); box-shadow: 0 0 0 2px var(--accent, #fff); }
    .pk-name { font-size: 13px; font-weight: 600; color: #fff; }
    .pk-tok { font-size: 10px; color: rgba(255,255,255,.55); margin-top: 2px; }
    .pk-badge { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; padding: 2px 8px; border-radius: 20px; background: rgba(0,0,0,.12); border: 1px solid var(--accent, rgba(255,255,255,.18)); font-size: 10px; font-weight: 600; color: #fff; }
    .pk-badge svg { stroke: var(--accent, rgba(255,255,255,.9)); }
    .pk-right { display: flex; align-items: center; gap: 8px; }
    .pk-price { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 700; color: #fff; }
    .pk-dot { width: 8px; height: 8px; border-radius: 50%; border: 2px solid rgba(255,255,255,.35); transition: all .15s; flex-shrink: 0; }
    .pkg-item.active .pk-dot { background: var(--accent, #fff); border-color: var(--accent, #fff); }

    /* Form card */
    .form-card { background: #fff; border-radius: 20px; border: 1px solid rgba(26,107,60,.10); box-shadow: 0 4px 24px rgba(26,107,60,.07); padding: 32px 32px; }
    .section-title { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 700; color: #1e1b3a; letter-spacing: .04em; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .section-title .icon { width: 28px; height: 28px; border-radius: 8px; background: #e8f5ee; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .section-title .icon svg { width: 14px; height: 14px; stroke: #1a6b3c; fill: none; stroke-width: 2; }
    .section-divider { height: 1px; background: #d1fae5; margin: 24px 0; }
    .wizard-step[hidden] { display: none; }

    /* Fields */
    .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
    @media (max-width: 540px) { .field-row { grid-template-columns: 1fr; } }
    .field { display: flex; flex-direction: column; }
    .field-label { font-size: 12px; font-weight: 600; color: #4a4760; margin-bottom: 7px; letter-spacing: .03em; }
    .field-label .req { color: #1a6b3c; margin-left: 2px; }
    .field-input, .field-select, .field-textarea { width: 100%; padding: 11px 16px; background: #fafafa; border: 1.5px solid #e2e0f0; border-radius: 10px; font-family: inherit; font-size: 13px; color: #1e1b3a; outline: none; transition: border-color .2s, box-shadow .2s; appearance: none; }
    .field-input:focus, .field-select:focus, .field-textarea:focus { border-color: #1a6b3c; box-shadow: 0 0 0 3px rgba(26,107,60,.12); background: #fff; }
    .field-input::placeholder, .field-textarea::placeholder { color: #bcbbcc; }
    .select-wrap { position: relative; }
    .select-wrap::after { content: ''; position: absolute; right: 14px; top: 50%; transform: translateY(-50%); width: 0; height: 0; border-left: 4px solid transparent; border-right: 4px solid transparent; border-top: 5px solid #8b88aa; pointer-events: none; }
    .field-select { padding-right: 36px; }
    .field-textarea { resize: vertical; min-height: 90px; line-height: 1.6; }

    /* Error */
    .error-box { background: #fff5f5; border: 1px solid #ffc5c5; border-radius: 12px; padding: 14px 18px; margin-bottom: 24px; }
    .error-box p { font-size: 13px; font-weight: 600; color: #c0392b; margin-bottom: 8px; }
    .error-box ul { padding-left: 18px; }
    .error-box li { font-size: 13px; color: #c0392b; line-height: 1.7; }

    /* Actions */
    .action-row { display: flex; align-items: center; justify-content: space-between; margin-top: 30px; gap: 12px; flex-wrap: wrap; }
    .btn-cancel { display: inline-flex; align-items: center; gap: 7px; padding: 11px 22px; border-radius: 10px; border: 1.5px solid #d6d4ee; background: #fff; font-size: 13px; font-weight: 600; color: #4a4760; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-cancel:hover { background: #f0f7f2; }
    .btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; border-radius: 10px; border: none; background: #1a6b3c; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; transition: background .15s, transform .1s; box-shadow: 0 4px 18px rgba(26,107,60,.35); }
    .btn-submit:hover { background: #155a32; }
    .btn-submit:active { transform: scale(.98); }
</style>
 


<div class="blob-1"></div>
<div class="blob-2"></div>
<div class="blob-3"></div>

<nav class="navbar">
    <div class="navbar-brand">
        <img src="{{ asset('assets/logo/phs-light-logo.jpeg') }}" alt="PHS" class="dark:hidden">
        <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS" class="hidden dark:block">
       
    </div>
    <div class="navbar-actions">
        <button onclick="phsToggleTheme()" title="Toggle dark mode" class="btn-ghost" style="padding:8px 12px;display:inline-flex;align-items:center;gap:6px;">
            <svg class="dark:hidden" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg class="hidden dark:block" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
        <a href="{{ route('phs.landing') }}" class="btn-primary">Home</a>
    </div>
    <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Menu">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
</nav>
<div id="mobile-menu" class="mobile-menu">
    <button onclick="phsToggleTheme()" class="btn-ghost" style="display:flex;align-items:center;justify-content:center;gap:6px;">
        <svg class="dark:hidden" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="hidden dark:block" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        Toggle Theme
    </button>
    <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
    <a href="{{ route('phs.landing') }}" class="btn-primary" style="text-align:center">Home</a>
</div>

<div class="page-wrapper">
    <div class="step-badge" id="step-badge">Step 1 of 3 — Organization Details</div>

    <div class="main-grid">

        {{-- Sidebar --}}
        <div class="sidebar-card">
            <img src="{{ asset('assets/logo/phs-dark-logo.jpeg') }}" alt="PHS" class="sb-logo">
            <div class="sb-eyebrow">Kano State Ministry of Land &amp; Physical Planning</div>
            <div class="sb-title">Property History Search (PHS) Portal</div>
            <div class="sb-desc">Request institutional access to search official property records. You will select a subscription package after your documents are reviewed and approved.</div>

            {{-- On-boarding user guide video --}}
            <div class="sb-video">
                <div class="sb-eyebrow" style="margin-bottom:8px;">Watch how it works</div>
                <video controls preload="metadata" playsinline class="sb-video-el">
                    <source src="{{ asset('assets/videos/video.mp4') }}" type="video/mp4">
                    Your browser does not support embedded video. You can
                    <a href="{{ asset('assets/videos/video.mp4') }}">download the guide video</a> instead.
                </video>
            </div>

              {{-- <div class="pkg-list">
                <div class="pkg-item" style="--accent: #1a6b3c; --card-bg: rgba(26,107,60,.08); cursor:default;">
                    <div>
                        <div class="pk-name" style="font-size:13px;color:#1a6b3c;">What happens next?</div>
                        <div class="pk-tok" style="font-size:11px;color:#6b7280;line-height:1.6;">1. Submit documents<br>2. Legal reviews &amp; approves<br>3. Admin approves → payment link<br>4. Select plan &amp; pay<br>5. Sign &amp; upload SLA<br>6. Account activated</div>
                    </div>
                </div>
            </div>   --}}
        </div>

        {{-- Form card --}}
        <div class="form-card">

            @if ($errors->any())
            <div class="error-box">
                <p>Please fix the following:</p>
                <ul>
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('phs.request.confirm') }}" id="requestForm" enctype="multipart/form-data">
                @csrf

                <div class="wizard-step" data-step="1">
                {{-- Organization --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M3 21h18M3 10h18M12 3 2 10h20L12 3z"/></svg></span>
                    Organization Information
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Organization Name <span class="req">*</span></label>
                        <input type="text" name="organization_name" value="{{ old('organization_name') }}" required placeholder="e.g. Acme Law Firm" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Organization Type <span class="req">*</span></label>
                        <div class="select-wrap">
                            <select name="organization_type" required class="field-select">
                                <option value="">Select type</option>
                                <option value="bank"      {{ old('organization_type') === 'bank'      ? 'selected' : '' }}>Bank</option>
                                <option value="law_firm"  {{ old('organization_type') === 'law_firm'  ? 'selected' : '' }}>Law Firm</option>
                                <option value="corporate" {{ old('organization_type') === 'corporate' ? 'selected' : '' }}>Corporate</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Phone</label>
                        <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+234 800 000 0000" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Address</label>
                        <input type="text" name="address" value="{{ old('address') }}" placeholder="Street, City" class="field-input">
                    </div>
                </div>

                <div class="section-divider"></div>

                {{-- Contact --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                    Contact Information
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">First Name <span class="req">*</span></label>
                        <input type="text" name="contact_first_name" value="{{ old('contact_first_name') }}" required placeholder="First name" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Last Name <span class="req">*</span></label>
                        <input type="text" name="contact_last_name" value="{{ old('contact_last_name') }}" required placeholder="Last name" class="field-input">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="contact_email" value="{{ old('contact_email') }}" required placeholder="you@organization.com" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">Confirm Email Address <span class="req">*</span></label>
                        <input type="email" name="contact_email_confirmation" value="{{ old('contact_email_confirmation') }}" required placeholder="Retype your email" class="field-input">
                    </div>
                </div>
                @php
                    $jobTitleOld   = old('job_title');
                    $departmentOld = old('department');
                    // Treat a previously-entered value that isn't in the list as a custom "Other" value.
                    $jobTitleIsOther   = $jobTitleOld !== null && $jobTitleOld !== '' && !in_array($jobTitleOld, $jobTitles, true);
                    $departmentIsOther = $departmentOld !== null && $departmentOld !== '' && !in_array($departmentOld, $departments, true);
                @endphp
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">Job Title</label>
                        <select name="job_title" class="field-select phs-lookup-select" data-other-target="job_title_other">
                            <option value="">Select job title</option>
                            @foreach($jobTitles as $jt)
                                <option value="{{ $jt }}" {{ $jobTitleOld === $jt ? 'selected' : '' }}>{{ $jt }}</option>
                            @endforeach
                            <option value="__other__" {{ $jobTitleIsOther ? 'selected' : '' }}>Other (specify)</option>
                        </select>
                        <input type="text" name="job_title_other" value="{{ $jobTitleIsOther ? $jobTitleOld : '' }}"
                               placeholder="Enter job title" class="field-input phs-other-input"
                               style="margin-top:8px;{{ $jobTitleIsOther ? '' : 'display:none;' }}">
                    </div>
                    <div class="field">
                        <label class="field-label">Department</label>
                        <select name="department" class="field-select phs-lookup-select" data-other-target="department_other">
                            <option value="">Select department</option>
                            @foreach($departments as $dep)
                                <option value="{{ $dep }}" {{ $departmentOld === $dep ? 'selected' : '' }}>{{ $dep }}</option>
                            @endforeach
                            <option value="__other__" {{ $departmentIsOther ? 'selected' : '' }}>Other (specify)</option>
                        </select>
                        <input type="text" name="department_other" value="{{ $departmentIsOther ? $departmentOld : '' }}"
                               placeholder="Enter department" class="field-input phs-other-input"
                               style="margin-top:8px;{{ $departmentIsOther ? '' : 'display:none;' }}">
                    </div>
                </div>
               

                </div>{{-- /wizard step 1 --}}

                <div class="wizard-step" data-step="2" hidden>
                {{-- CAC Documentation --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                    CAC Documentation
                </div>
                <div class="field-row">
                    <div class="field">
                        <label class="field-label">CAC Registration Number <span class="req">*</span></label>
                        <input type="text" name="cac_registration_number" value="{{ old('cac_registration_number') }}" required placeholder="e.g. RC123456" class="field-input">
                    </div>
                    <div class="field">
                        <label class="field-label">CAC Certificate (PDF) <span class="req">*</span></label>
                        <input type="file" name="cac_document" accept="application/pdf,.pdf" required class="field-input" style="padding:9px 12px;">
                        <span style="font-size:11px;color:#8b88aa;margin-top:5px;">PDF only · max 5MB</span>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">ID Card <span class="req">*</span></label>
                    <input type="file" name="additional_documents[]" accept=".pdf,.jpg,.jpeg,.png" required class="field-input" style="padding:9px 12px;">
                    <span style="font-size:11px;color:#8b88aa;margin-top:5px;">Upload a valid government-issued ID card — PDF/JPG/PNG, max 5MB.</span>
                    @error('additional_documents.0')<span style="font-size:11px;color:#ef4444;margin-top:4px;">{{ $message }}</span>@enderror
                </div>

                <div class="section-divider"></div>

                {{-- Request Letter --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
                    Request Letter
                </div>
                <div class="field">
                    <label class="field-label">Organization Request Letter <span class="req">*</span></label>
                    <input type="file" name="request_letter" accept=".pdf,.jpg,.jpeg,.png" required class="field-input" style="padding:9px 12px;">
                    <span style="font-size:11px;color:#8b88aa;margin-top:5px;">Official request letter on your organization's letterhead — PDF/JPG/PNG, max 5MB.</span>
                    @error('request_letter')<span style="font-size:11px;color:#ef4444;margin-top:4px;">{{ $message }}</span>@enderror
                </div>

                <div class="section-divider"></div>

                {{-- Notes --}}
                <div class="section-title">
                    <span class="icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></span>
                    Additional Notes
                </div>
                <div class="field">
                    <textarea name="additional_notes" rows="3" placeholder="Any special requirements or questions?" class="field-textarea">{{ old('additional_notes') }}</textarea>
                </div>
                </div>{{-- /wizard step 2 --}}

                <div class="action-row">
                    <div style="display:flex; gap:10px; align-items:center;">
                        <a href="{{ route('phs.landing') }}" class="btn-cancel">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            Cancel
                        </a>
                        <button type="button" class="btn-cancel" id="btn-back" style="display:none;">
                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                            Back
                        </button>
                    </div>
                    <button type="button" class="btn-submit" id="btn-next">
                        Next
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                    <button type="submit" class="btn-submit" id="btn-finish" style="display:none;">
                     Next
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('mobile-menu-btn').addEventListener('click', function() {
        document.getElementById('mobile-menu').classList.toggle('open');
    });

    const pkgItems = document.querySelectorAll('.pkg-item');
    /* ---------- Multi-step wizard (steps 1 & 2 of 3) ---------- */
    const step1 = document.querySelector('.wizard-step[data-step="1"]');
    const step2 = document.querySelector('.wizard-step[data-step="2"]');
    const stepBadge = document.getElementById('step-badge');
    const btnNext = document.getElementById('btn-next');
    const btnBack = document.getElementById('btn-back');
    const btnFinish = document.getElementById('btn-finish');

    function showStep(n) {
        step1.hidden = n !== 1;
        step2.hidden = n !== 2;
        btnNext.style.display = n === 1 ? '' : 'none';
        btnFinish.style.display = n === 2 ? '' : 'none';
        btnBack.style.display = n === 2 ? '' : 'none';
        stepBadge.textContent = n === 1
            ? 'Step 1 of 3 — Organization Details'
            : 'Step 2 of 3 — Documents';
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    btnNext.addEventListener('click', function () {
        // Validate step-1 required fields with native messages.
        for (const el of step1.querySelectorAll('[required]')) {
            if (!el.checkValidity()) { el.reportValidity(); return; }
        }
        const email = document.querySelector('[name="contact_email"]').value.trim();
        const confirmEmail = document.querySelector('[name="contact_email_confirmation"]').value.trim();
        if (email !== confirmEmail) { alert('Email addresses do not match.'); return; }
        showStep(2);
    });

    btnBack.addEventListener('click', () => showStep(1));

    // If the server returned validation errors, surface them where the user can
    // act — start on step 1 (the error summary is shown above the form).
    showStep(1);
</script>
@endsection

@section('extra_styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Make Select2 visually match the form's .field-select inputs. */
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single {
        height: 44px; padding: 5px 6px; background: #fafafa;
        border: 1.5px solid #e2e0f0; border-radius: 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px; color: #1e1b3a; font-size: 13px; padding-left: 10px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #1a6b3c; box-shadow: 0 0 0 3px rgba(26,107,60,.12); background: #fff;
    }
    .select2-dropdown { border-color: #e2e0f0; font-size: 13px; }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background: #1a6b3c; }

    /* Dark mode */
    .dark .select2-container--default .select2-selection--single { background: #111827; border-color: #374151; }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered { color: #f3f4f6; }
    .dark .select2-dropdown { background: #111827; border-color: #374151; color: #f3f4f6; }
    .dark .select2-results__option { color: #f3f4f6; }
    .dark .select2-search--dropdown .select2-search__field { background: #1f2937; border-color: #374151; color: #f3f4f6; }
</style>
@endsection

@section('extra_scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(function () {
        const $selects = $('.phs-lookup-select');

        if ($.fn.select2) {
            $selects.select2({ width: '100%', minimumResultsForSearch: 6 });
        }

        // Reveal/hide the companion "Other (specify)" text input.
        $selects.each(function () {
            const $sel = $(this);
            const $other = $('input[name="' + $sel.data('other-target') + '"]');

            function sync(clearOnHide) {
                const isOther = $sel.val() === '__other__';
                $other.css('display', isOther ? '' : 'none');
                if (isOther) {
                    $other.attr('required', 'required');
                } else {
                    $other.removeAttr('required');
                    if (clearOnHide) { $other.val(''); }
                }
            }

            $sel.on('change', function () { sync(true); });
            sync(false); // honour server-repopulated state without clearing it
        });
    });

    // ---- Prevent the same file being used for more than one upload ----
    // A user shouldn't attach the identical document to CAC Certificate, ID Card
    // and Organization Request Letter. We compare each newly picked file against
    // the others by name + size; on a clash we reject it with a SweetAlert.
    $(function () {
        const uploads = [
            { name: 'cac_document',            label: 'CAC Certificate' },
            { name: 'additional_documents[]',  label: 'ID Card' },
            { name: 'request_letter',          label: 'Organization Request Letter' }
        ];

        // Build a signature unique enough to spot the same file: name + size.
        const sig = (file) => file ? (file.name + '::' + file.size) : null;

        function inputFor(cfg) {
            return document.querySelector('input[type="file"][name="' + cfg.name + '"]');
        }

        function checkDuplicate(changedCfg) {
            const changedInput = inputFor(changedCfg);
            const changedFile = changedInput && changedInput.files && changedInput.files[0];
            if (!changedFile) return;

            const changedSig = sig(changedFile);

            for (const other of uploads) {
                if (other.name === changedCfg.name) continue;
                const otherInput = inputFor(other);
                const otherFile = otherInput && otherInput.files && otherInput.files[0];
                if (otherFile && sig(otherFile) === changedSig) {
                    // Clear the just-picked duplicate and warn.
                    changedInput.value = '';
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: "Can't use the same file",
                            html: 'You already selected <strong>' + $('<div>').text(changedFile.name).html()
                                + '</strong> for <strong>' + other.label + '</strong>.<br>'
                                + 'Please choose a different file for <strong>' + changedCfg.label + '</strong>.',
                            confirmButtonColor: '#1a6b3c'
                        });
                    } else {
                        alert("Can't use the same file. \"" + changedFile.name + "\" is already used for " + other.label + '.');
                    }
                    return;
                }
            }
        }

        uploads.forEach(function (cfg) {
            const input = inputFor(cfg);
            if (input) {
                input.addEventListener('change', function () { checkDuplicate(cfg); });
            }
        });
    });
</script>
@endsection