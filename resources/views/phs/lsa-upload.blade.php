@extends('phs.layouts.app')

@section('title', 'PHS Portal — Service Level Agreement')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: #f0f2ff; min-height: 100vh; }
    .page-wrapper { max-width: 680px; margin: 0 auto; padding: 48px 20px 80px; }
    .card { background: #fff; border-radius: 20px; border: 1px solid rgba(108,99,255,.1); box-shadow: 0 4px 24px rgba(108,99,255,.07); padding: 36px 40px; }
    .ministry-header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
    .ministry-header img { height: 52px; width: auto; flex-shrink: 0; }
    .ministry-title { flex: 1; text-align: center; }
    .ministry-title h2 { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 700; color: #166534; margin: 0; line-height: 1.3; text-transform: uppercase; }
    .ministry-title h3 { font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 700; color: #374151; margin: 3px 0 0; text-transform: uppercase; letter-spacing: .03em; }
    .ministry-title p { font-size: 11px; color: #6b7280; margin: 4px 0 0; }
    .divider-green { height: 3px; background: #166534; border-radius: 2px; margin-bottom: 24px; }
    h1 { font-family: 'Sora', sans-serif; font-size: 20px; color: #1e1b3a; margin-bottom: 4px; }
    .subtitle { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
    .meta-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px; margin-bottom: 22px; font-size: 13px; line-height: 1.8; }
    .meta-box .lbl { color: #6b7280; display: inline-block; width: 120px; }
    .meta-box .val { font-weight: 600; color: #1e1b3a; }
    .btn-download { display: inline-flex; align-items: center; gap: 8px; background: #166534; color: #fff; padding: 11px 22px; border-radius: 10px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background .15s; }
    .btn-download:hover { background: #14532d; }
    .upload-section { margin-top: 28px; }
    .upload-section h2 { font-family: 'Sora', sans-serif; font-size: 15px; color: #1e1b3a; margin-bottom: 6px; }
    .upload-section p { color: #6b7280; font-size: 13px; margin-bottom: 16px; }
    .file-drop { border: 2px dashed #c7d2fe; border-radius: 12px; padding: 24px; text-align: center; background: #f5f3ff; cursor: pointer; transition: border-color .2s; }
    .file-drop:hover { border-color: #6c63ff; }
    .file-drop input[type=file] { display: none; }
    .file-drop label { cursor: pointer; display: block; }
    .file-drop .icon { font-size: 28px; margin-bottom: 8px; }
    .file-drop .hint { font-size: 12px; color: #8b88aa; margin-top: 6px; }
    .file-drop-text { color: #4a4760; }
    .file-name { margin-top: 10px; font-size: 12px; color: #166534; font-weight: 600; min-height: 18px; }
    .btn-upload { display: inline-flex; align-items: center; gap: 8px; background: #6c63ff; color: #fff; padding: 12px 28px; border-radius: 10px; border: none; font-size: 14px; font-weight: 600; cursor: pointer; margin-top: 16px; transition: background .15s; width: 100%; justify-content: center; }
    .btn-upload:hover { background: #5a52e0; }
    .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 500; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .signed-badge { display: inline-flex; align-items: center; gap: 6px; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: 20px; padding: 6px 16px; font-size: 13px; font-weight: 700; margin-top: 16px; }
    .steps { display: flex; gap: 0; margin-bottom: 28px; }
    .step { flex: 1; text-align: center; position: relative; }
    .step::after { content:''; position:absolute; top:14px; left:50%; width:100%; height:2px; background:#e5e7eb; z-index:0; }
    .step:last-child::after { display:none; }
    .step-dot { width:28px; height:28px; border-radius:50%; background:#e5e7eb; display:inline-flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#6b7280; position:relative; z-index:1; }
    .step.done .step-dot { background:#166534; color:#fff; }
    .step.active .step-dot { background:#fff; border: 2px solid #f59e0b; color:#d97706; }
    .step-label { font-size:10px; color:#6b7280; margin-top:5px; }
    .step.done .step-label { color:#166534; font-weight:700; }
    .step.active .step-label { color:#d97706; font-weight:700; }
    .section-title { font-family: 'Sora', sans-serif; font-size: 15px; color: #1e1b3a; margin: 26px 0 4px; }
    .section-sub { color: #6b7280; font-size: 12.5px; margin-bottom: 14px; }
    .packages { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 8px; }
    .pkg-card { border: 2px solid #e5e7eb; border-radius: 14px; padding: 16px 12px; cursor: pointer; transition: border-color .15s, box-shadow .15s; background: #fff; text-align: center; position: relative; }
    .pkg-card:hover { border-color: #6c63ff; box-shadow: 0 2px 12px rgba(108,99,255,.12); }
    .pkg-card.selected { border-color: #6c63ff; background: #f5f3ff; box-shadow: 0 2px 12px rgba(108,99,255,.15); }
    .pkg-card input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
    .pkg-name { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 700; color: #1e1b3a; margin-bottom: 5px; }
    .pkg-tokens { font-size: 12px; color: #6c63ff; font-weight: 600; margin-bottom: 3px; }
    .pkg-members { font-size: 10.5px; color: #9ca3af; margin-bottom: 8px; }
    .pkg-price { font-family: 'Sora', sans-serif; font-size: 17px; font-weight: 700; color: #166534; }
    .pkg-price span { font-size: 11px; font-weight: 400; color: #6b7280; }
    .check-badge { position: absolute; top: 8px; right: 8px; width: 18px; height: 18px; border-radius: 50%; background: #6c63ff; display: none; align-items: center; justify-content: center; }
    .check-badge svg { width: 11px; height: 11px; stroke: #fff; fill: none; stroke-width: 2.5; }
    .pkg-card.selected .check-badge { display: flex; }
    .btn-download.disabled { background: #9ca3af; pointer-events: none; opacity: .7; }
    .success-box { background: #f0fdf4; border: 2px solid #86efac; }
    .success-title { color: #14532d; }
    .success-subtitle { color: #166534; }
    .success-desc { color: #4b7c5a; }

    /* Dark Mode Overrides */
    .dark body { background: #0f1117; }
    .dark .card { background: #1f2937; border-color: rgba(108,99,255,.15); box-shadow: 0 4px 24px rgba(0,0,0,.3); }
    .dark h1, .dark .section-title, .dark .upload-section h2, .dark .pkg-name { color: #f3f4f6; }
    .dark .subtitle, .dark .meta-box .lbl, .dark .upload-section p, .dark .pkg-members, .dark .pkg-price span, .dark .hint { color: #9ca3af; }
    .dark .meta-box { background: #111827; border-color: #374151; }
    .dark .meta-box .val { color: #f3f4f6; }
    .dark .file-drop { border-color: #374151; background: #111827; }
    .dark .file-drop:hover { border-color: #6c63ff; }
    .dark .pkg-card { border-color: #374151; background: #111827; }
    .dark .pkg-card:hover { border-color: #6c63ff; }
    .dark .pkg-card.selected { border-color: #6c63ff; background: #1e1b4b; }
    .dark .ministry-title h2 { color: #4ade80; }
    .dark .ministry-title h3 { color: #e5e7eb; }
    .dark .step-dot { background: #374151; color: #9ca3af; }
    .dark .step::after { background: #374151; }
    .dark .step-label { color: #9ca3af; }
    .dark .step.done .step-dot { background: #166534; color: #fff; }
    .dark .step.done .step-label { color: #4ade80; }
    .dark .step.active .step-dot { background: #1f2937; border-color: #f59e0b; color: #f59e0b; }
    .dark .step.active .step-label { color: #f59e0b; }
    .dark .file-drop-text { color: #d1d5db; }
    .dark .success-box { background: rgba(6, 78, 59, 0.2); border-color: #059669; }
    .dark .success-title { color: #34d399; }
    .dark .success-subtitle { color: #6ee7b7; }
    .dark .success-desc { color: #a7f3d0; }
</style>

<div class="page-wrapper">
    <div class="card">
        <div class="ministry-header">
            <img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="">
            <div class="ministry-title">
                <h2>Ministry of Land &amp; Physical Planning</h2>
                 
                <p class="dark:text-gray-400">PHS Portal &mdash; Service Level Agreement</p>
            </div>
            <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="">
        </div>
        <div class="divider-green"></div>

        @php
            $lsaDone = (bool) $onboardingRequest->lsa_signed_document_path;
        @endphp

        {{-- Steps indicator --}}
        <div class="steps">
            <div class="step done">
                <div class="step-dot">✓</div>
                <div class="step-label">Request<br>Submitted</div>
            </div>
            <div class="step {{ $lsaDone ? 'done' : 'active' }}">
                <div class="step-dot">{{ $lsaDone ? '✓' : '2' }}</div>
                <div class="step-label">Sign &amp;<br>Upload SLA</div>
            </div>
        </div>

        <h1>Select Your Package &amp; Sign/Upload the SLA</h1>
        <p class="subtitle">Choose your subscription package, then download the SLA, sign it, and upload the signed copy. No payment is required at this stage.</p>

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        {{-- Org details --}}
        <div class="meta-box">
            <div><span class="lbl">Organization:</span><span class="val">{{ $onboardingRequest->organization_name }}</span></div>
            <div><span class="lbl">Ref:</span><span class="val">PHS-SLA-{{ str_pad($onboardingRequest->id, 6, '0', STR_PAD_LEFT) }}</span></div>
        </div>

        @if (session('success') || $onboardingRequest->lsa_signed_document_path)
            {{-- Already signed: skip package/download, show success state below --}}
        @else
            @php $selectedPkg = strtolower((string) $onboardingRequest->initial_token_package); @endphp

            {{-- 1. Package selection --}}
            <h2 class="section-title">1. Select Your Subscription Package</h2>
            <p class="section-sub">Pick the package that fits your organization. You'll pay for it at the final step, after your SLA is approved.</p>
            <div class="packages">
                @foreach ($packages as $key => $pkg)
                    <label class="pkg-card {{ $selectedPkg === strtolower($key) ? 'selected' : '' }}" data-name="{{ $pkg['name'] }}">
                        <input type="radio" name="package_pick" value="{{ $pkg['name'] }}"
                               {{ $selectedPkg === strtolower($key) ? 'checked' : '' }}
                               onchange="selectPkg('{{ $pkg['name'] }}')">
                        <div class="check-badge"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div class="pkg-name">{{ $pkg['name'] }}</div>
                        <div class="pkg-tokens">{{ number_format($pkg['tokens']) }} tokens</div>
                        <div class="pkg-members">Up to {{ $pkg['team_members'] }} users</div>
                        <div class="pkg-price">₦{{ number_format($pkg['price']) }}<span>/year</span></div>
                    </label>
                @endforeach
            </div>

            {{-- 2. Download SLA (enabled once a package is chosen) --}}
            <h2 class="section-title">2. Download &amp; Sign the SLA</h2>
          
            <a id="dl-btn"
               href="{{ route('phs.lsa.download', [$onboardingRequest->id, $token]) }}"
               class="btn-download {{ $selectedPkg ? '' : 'disabled' }}">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Download SLA (PDF)
            </a>
        @endif

        {{-- 3. Upload section --}}
        <div class="upload-section">
            @if (session('success') || $onboardingRequest->lsa_signed_document_path)
                {{-- Success state --}}
                <div style="margin-top:24px;border-radius:14px;padding:36px 24px;text-align:center;" class="success-box">
                    <div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;background:#166534;border-radius:50%;margin-bottom:16px;">
                        <svg width="28" height="28" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h2 style="font-family:'Sora',sans-serif;font-size:20px;margin-bottom:8px;" class="success-title">Signed SLA Received!</h2>
                    <p style="font-size:13px;margin-bottom:4px;" class="success-subtitle">Thank you. Your signed SLA was uploaded on {{ $onboardingRequest->lsa_signed_at?->format('M j, Y \a\t g:i A') ?? now()->format('M j, Y') }}.</p>
                    <p style="font-size:12px;margin-top:10px;" class="success-desc">The Legal Department will review your SLA. You will receive an email with next steps once the review is complete.</p>
                    <p style="font-size:12px;margin-top:20px;" class="dark:text-gray-400">You can now safely close this tab.</p>
                    <button onclick="phsCloseTab()" style="margin-top:16px;padding:10px 28px;background:#166534;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                        Close Tab
                    </button>
                </div>
                <script>
                    // window.close() only works on tabs opened by script; for a tab the
                    // user navigated to directly the browser blocks it.
                    function phsTryClose() {
                        window.open('', '_self');
                        window.close();
                    }

                    // Auto-attempt to close the tab on load. If the browser refuses
                    // (direct navigation), the success message + button remain as a fallback.
                    window.addEventListener('load', function () {
                        setTimeout(phsTryClose, 1200);
                    });

                    // Explicit button click: try to close, and if still open, tell the user.
                    function phsCloseTab() {
                        phsTryClose();
                        setTimeout(function () {
                            alert('Please close this tab manually — your browser does not allow this page to close itself.');
                        }, 300);
                    }
                </script>
            @else
                <h2 class="section-title" style="margin-top:0;">3. Upload Signed SLA</h2>
                <p class="section-sub">Print the downloaded SLA, sign and stamp it, scan it, then upload the PDF below.</p>
                <form method="POST" action="{{ route('phs.lsa.upload', [$onboardingRequest->id, $token]) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="package" id="pkg-hidden" value="{{ $onboardingRequest->initial_token_package }}">
                    <div class="file-drop">
                        <label style="display:block;">
                            <div class="icon">📄</div>
                            <div style="font-size:13px;font-weight:600;" class="file-drop-text">Click to select signed SLA</div>
                            <div class="hint">PDF only · max 10 MB</div>
                            <input type="file" id="signed_lsa_input" name="signed_lsa" accept="application/pdf,.pdf"
                                   onchange="document.getElementById('file-name-display').textContent = this.files[0]?.name ?? ''">
                        </label>
                    </div>
                    <div id="file-name-display" class="file-name"></div>
                    <button type="submit" class="btn-upload">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Signed SLA
                    </button>
                </form>
            @endif
        </div>

        <!-- Theme toggle -->
        <div style="margin-top:20px;display:flex;justify-content:center;">
            <button onclick="phsToggleTheme()" title="Toggle dark mode"
                style="padding:8px 16px;background:transparent;border:1.5px solid #d6d4ee;border-radius:10px;font-size:13px;font-weight:600;color:#4a4760;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"
                class="btn-theme-toggle">
                <i data-lucide="sun" class="h-4.5 w-4.5 dark:hidden"></i>
                <i data-lucide="moon" class="h-4.5 w-4.5 hidden dark:block"></i>
                Toggle Theme
            </button>
        </div>
    </div>
</div>
    </div>
</div>

<script>
    var dlBase = @json(route('phs.lsa.download', [$onboardingRequest->id, $token]));
    function selectPkg(name) {
        document.querySelectorAll('.pkg-card').forEach(function (c) {
            c.classList.toggle('selected', c.dataset.name === name);
        });
        var dl = document.getElementById('dl-btn');
        if (dl) {
            dl.classList.remove('disabled');
            dl.href = dlBase + '?package=' + encodeURIComponent(name);
        }
        var hidden = document.getElementById('pkg-hidden');
        if (hidden) hidden.value = name;
    }
</script>
@endsection
