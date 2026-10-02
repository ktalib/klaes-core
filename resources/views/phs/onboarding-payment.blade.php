@extends('phs.layouts.app')

@section('title', 'PHS Portal - Review & Submit')

@section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: #f0f2ff; min-height: 100vh; }

    .blob-1 { position: fixed; top: -120px; left: -120px; width: 480px; height: 480px; border-radius: 50%; background: #6c63ff; opacity: .14; pointer-events: none; z-index: 0; }
    .blob-2 { position: fixed; bottom: -160px; right: -100px; width: 520px; height: 520px; border-radius: 50%; background: #06d6a0; opacity: .11; pointer-events: none; z-index: 0; }

    .navbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; justify-content: space-between; padding: 14px 32px; background: rgba(255,255,255,.88); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(108,99,255,.10); box-shadow: 0 1px 0 rgba(0,0,0,.04); }
    .navbar-brand { display: flex; align-items: center; gap: 10px; }
    .navbar-brand img { height: 36px; width: auto; }
    .navbar-brand span { font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600; color: #3d3a4e; letter-spacing: .04em; }
    .navbar-actions { display: flex; align-items: center; gap: 10px; }
    .btn-ghost { padding: 8px 18px; border: 1.5px solid #d0cfe8; border-radius: 8px; background: transparent; font-size: 13px; font-weight: 500; color: #3d3a4e; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-ghost:hover { background: #f4f3ff; }
    .btn-primary-nav { padding: 8px 18px; border: none; border-radius: 8px; background: #6c63ff; font-size: 13px; font-weight: 500; color: #fff; text-decoration: none; cursor: pointer; }
    .mobile-menu-btn { display: none; border: none; background: transparent; padding: 8px; cursor: pointer; border-radius: 8px; }
    .mobile-menu { display: none; flex-direction: column; gap: 10px; padding: 16px 32px; background: rgba(255,255,255,.96); border-bottom: 1px solid rgba(108,99,255,.10); }
    @media (max-width: 768px) { .navbar-actions { display: none; } .mobile-menu-btn { display: flex; align-items: center; justify-content: center; } .mobile-menu.open { display: flex; } }

    .page-wrapper { position: relative; z-index: 1; max-width: 1060px; margin: 0 auto; padding: 40px 20px 80px; }

    .step-badge { display: inline-flex; align-items: center; gap: 6px; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #6c63ff; background: #ededff; border-radius: 20px; padding: 5px 14px; margin-bottom: 28px; }
    .step-badge::before { content: ''; display: inline-block; width: 6px; height: 6px; background: #6c63ff; border-radius: 50%; }

    .main-grid { display: grid; grid-template-columns: 1fr 1.55fr; gap: 24px; align-items: start; }
    @media (max-width: 820px) { .main-grid { grid-template-columns: 1fr; } }

    .sidebar-card { background: #6c63ff; border-radius: 20px; padding: 32px 28px; color: #fff; position: sticky; top: 84px; }
    .sb-logo { height: 30px; width: auto; margin-bottom: 24px; filter: brightness(0) invert(1); opacity: .85; }
    .sb-eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,.55); margin-bottom: 10px; }
    .sb-title { font-family: 'Sora', sans-serif; font-size: 22px; font-weight: 700; line-height: 1.25; color: #fff; margin-bottom: 14px; }
    .sb-desc { font-size: 13px; line-height: 1.7; color: rgba(255,255,255,.72); }
    .sum-list { margin-top: 22px; display: flex; flex-direction: column; gap: 11px; }
    .sum-row { display: flex; justify-content: space-between; align-items: baseline; }
    .s-lbl { font-size: 11px; color: rgba(255,255,255,.55); }
    .s-val { font-size: 12px; font-weight: 500; color: rgba(255,255,255,.88); text-align: right; max-width: 60%; word-break: break-word; }

    .form-card { background: #fff; border-radius: 20px; border: 1px solid rgba(108,99,255,.10); box-shadow: 0 4px 24px rgba(108,99,255,.07); padding: 32px; }
    .form-title { font-family: 'Sora', sans-serif; font-size: 16px; font-weight: 700; color: #1e1b3a; margin-bottom: 4px; }
    .form-sub { font-size: 13px; color: #6b6880; margin-bottom: 24px; }
    .section-divider { height: 1px; background: #eeecfa; margin: 22px 0; }

    .review-row { display: flex; justify-content: space-between; align-items: baseline; padding: 9px 0; border-bottom: 1px solid #f0eeff; font-size: 13px; }
    .review-row:last-child { border-bottom: none; }
    .rv-lbl { color: #8b88aa; font-weight: 500; }
    .rv-val { color: #1e1b3a; font-weight: 600; text-align: right; max-width: 65%; word-break: break-word; }

    .doc-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px; }
    .doc-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #4a4760; }
    .doc-item svg { width: 15px; height: 15px; stroke: #6c63ff; flex-shrink: 0; }

    .info-box { background: #f7f6ff; border: 1.5px solid #e0deff; border-radius: 14px; padding: 18px 20px; margin-bottom: 20px; }
    .info-box h4 { font-size: 13px; font-weight: 700; color: #1e1b3a; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
    .info-box ol { padding-left: 18px; }
    .info-box ol li { font-size: 12px; color: #4a4760; line-height: 1.8; }

    .action-row { display: flex; align-items: center; justify-content: space-between; margin-top: 28px; gap: 12px; flex-wrap: wrap; }
    .btn-back { display: inline-flex; align-items: center; gap: 7px; padding: 11px 22px; border-radius: 10px; border: 1.5px solid #d6d4ee; background: #fff; font-size: 13px; font-weight: 600; color: #4a4760; text-decoration: none; cursor: pointer; transition: background .15s; }
    .btn-back:hover { background: #f4f3ff; }
    .btn-submit { display: inline-flex; align-items: center; gap: 8px; padding: 12px 30px; border-radius: 10px; border: none; background: #6c63ff; font-size: 14px; font-weight: 600; color: #fff; cursor: pointer; transition: background .15s, transform .1s; box-shadow: 0 4px 18px rgba(108,99,255,.35); }
    .btn-submit:hover { background: #5a52e0; }
    .btn-submit:active { transform: scale(.98); }

    /* Dark Mode Overrides */
    .dark body { background: #0f1117; }
    .dark .navbar { background: rgba(17,24,39,.92); border-bottom-color: rgba(108,99,255,.15); }
    .dark .navbar-brand span { color: #e5e7eb; }
    .dark .btn-ghost { border-color: #374151; color: #d1d5db; }
    .dark .btn-ghost:hover { background: #1f2937; }
    .dark .form-card { background: #1f2937; border-color: rgba(108,99,255,.15); box-shadow: 0 4px 24px rgba(0,0,0,.3); }
    .dark .form-title { color: #f3f4f6; }
    .dark .form-sub, .dark .rv-lbl { color: #9ca3af; }
    .dark .review-row { border-bottom-color: #374151; }
    .dark .rv-val { color: #f3f4f6; }
    .dark .doc-item { color: #d1d5db; }
    .dark .info-box { background: #111827; border-color: #374151; }
    .dark .info-box h4 { color: #f3f4f6; }
    .dark .info-box ol li { color: #d1d5db; }
    .dark .btn-back { background: #1f2937; border-color: #374151; color: #d1d5db; }
    .dark .btn-back:hover { background: #374151; }
    .dark .step-badge { background: #1e1b4b; color: #a5b4fc; }
    .dark .mobile-menu { background: rgba(17,24,39,.97); border-bottom-color: rgba(108,99,255,.15); }
</style>

<div class="blob-1"></div>
<div class="blob-2"></div>

<nav class="navbar">
    <div class="navbar-brand">
        <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES">
        <span>PHS Portal</span>
    </div>
    <div class="navbar-actions">
        <button onclick="phsToggleTheme()" title="Toggle dark mode" class="btn-ghost" style="padding:8px 12px;display:inline-flex;align-items:center;gap:6px;">
            <svg class="dark:hidden" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <svg class="hidden dark:block" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
        <a href="{{ route('phs.request.form') }}" class="btn-primary-nav">Request Access</a>
    </div>
    <button id="mobile-menu-btn" class="mobile-menu-btn" aria-label="Menu">
        <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
    </button>
</nav>
<div id="mobile-menu" class="mobile-menu">
    <button onclick="phsToggleTheme()" class="btn-ghost" style="display:flex;align-items:center;justify-content:center;gap:6px;width:100%;">
        <svg class="dark:hidden" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="hidden dark:block" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        Toggle Theme
    </button>
    <a href="{{ route('phs.login') }}" class="btn-ghost">Sign In</a>
    <a href="{{ route('phs.request.form') }}" class="btn-primary-nav" style="text-align:center">Request Access</a>
</div>

<div class="page-wrapper">
    <div class="step-badge">Step 3 of 3 — Review &amp; Submit</div>

    <div class="main-grid">

        {{-- Sidebar --}}
        <div class="sidebar-card">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES" class="sb-logo">
            <div class="sb-eyebrow">Application Summary</div>
            <div class="sb-title">Review Your Application</div>
            <div class="sb-desc">Confirm your details are correct before submitting. You will be notified by email at each stage of the review process.</div>

            <div class="sum-list">
                <div class="sum-row">
                    <span class="s-lbl">Organization</span>
                    <span class="s-val">{{ $validated['organization_name'] }}</span>
                </div>
                <div class="sum-row">
                    <span class="s-lbl">Contact</span>
                    <span class="s-val">{{ $validated['contact_name'] }}</span>
                </div>
                <div class="sum-row">
                    <span class="s-lbl">Email</span>
                    <span class="s-val" style="font-size:11px">{{ $validated['contact_email'] }}</span>
                </div>
                @if(!empty($validated['phone']))
                <div class="sum-row">
                    <span class="s-lbl">Phone</span>
                    <span class="s-val">{{ $validated['phone'] }}</span>
                </div>
                @endif
                <div class="sum-row">
                    <span class="s-lbl">Type</span>
                    <span class="s-val">{{ ucwords(str_replace('_', ' ', $validated['organization_type'])) }}</span>
                </div>
            </div>
        </div>

        {{-- Review card --}}
        <div class="form-card">
            <div class="form-title">Application Details</div>
            <div class="form-sub">Please review everything below before submitting your request.</div>

            <div class="review-row">
                <span class="rv-lbl">CAC Registration No.</span>
                <span class="rv-val">{{ $validated['cac_registration_number'] }}</span>
            </div>
            @if(!empty($validated['department']))
            <div class="review-row">
                <span class="rv-lbl">Department</span>
                <span class="rv-val">{{ $validated['department'] }}</span>
            </div>
            @endif
            @if(!empty($validated['job_title']))
            <div class="review-row">
                <span class="rv-lbl">Job Title</span>
                <span class="rv-val">{{ $validated['job_title'] }}</span>
            </div>
            @endif
            @if(!empty($validated['address']))
            <div class="review-row">
                <span class="rv-lbl">Address</span>
                <span class="rv-val">{{ $validated['address'] }}</span>
            </div>
            @endif

            <div class="section-divider"></div>

            <p style="font-size:12px;font-weight:700;color:#1e1b3a;margin-bottom:10px;">Uploaded Documents</p>
            <div class="doc-list">
                <div class="doc-item">
                    <svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    CAC Certificate / Registration Document
                </div>
                <div class="doc-item">
                    <svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Request Letter
                </div>
                @foreach ((array) ($validated['additional_documents'] ?? []) as $i => $doc)
                <div class="doc-item">
                    <svg fill="none" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    Additional Document {{ $i + 1 }}
                </div>
                @endforeach
            </div>

            <div class="section-divider"></div>

            <div class="info-box">
                <h4>
                    <svg width="15" height="15" fill="none" stroke="#6c63ff" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    What happens after submission?
                </h4>
                <ol>
                    <li>Legal Department reviews your submitted documents</li>
                    <li>Admin approves the request &amp; sends you a secure link</li>
                    <li>You select a subscription package, then download, sign, and upload the Service Level Agreement (SLA) — no payment yet</li>
                    <li>Legal Department approves the signed SLA</li>
                    <li>Admin sends a payment &amp; onboarding link — you pay online via Paystack</li>
                    <li>You complete registration and your account is activated</li>
                </ol>
            </div>

            <form method="POST" action="{{ route('phs.request.submit') }}" id="submitForm">
                @csrf
                <div class="action-row">
                    <a href="{{ route('phs.request.form') }}" class="btn-back">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                        Back
                    </a>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span>Submit Application</span>
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
    document.getElementById('submitForm').addEventListener('submit', function() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="animation:spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Submitting…';
    });
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
@endsection
