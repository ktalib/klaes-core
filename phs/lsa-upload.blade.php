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
</style>

<div class="page-wrapper">
    <div class="card">
        <div class="ministry-header">
            <img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="">
            <div class="ministry-title">
                <h2>Ministry of Land &amp; Physical Planning</h2>
                 
                <p>PHS Portal &mdash; Service Level Agreement</p>
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

        <h1>Service Level Agreement</h1>
        <p class="subtitle">Download the SLA, sign it, then upload the signed copy to proceed with your onboarding.</p>

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        {{-- Org details --}}
        <div class="meta-box">
            <div><span class="lbl">Organization:</span><span class="val">{{ $onboardingRequest->organization_name }}</span></div>
            <div><span class="lbl">Ref:</span><span class="val">PHS-SLA-{{ str_pad($onboardingRequest->id, 6, '0', STR_PAD_LEFT) }}</span></div>
            <div><span class="lbl">Package:</span><span class="val">{{ $onboardingRequest->initial_token_package }}</span></div>
        </div>

        {{-- Download button --}}
        <a href="{{ route('phs.lsa.download', [$onboardingRequest->id, $token]) }}" class="btn-download">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download SLA (PDF)
        </a>

        {{-- Upload section --}}
        <div class="upload-section">
            @if (session('success') || $onboardingRequest->lsa_signed_document_path)
                {{-- Success state — closes page --}}
                <div style="margin-top:24px;background:#f0fdf4;border:2px solid #86efac;border-radius:14px;padding:36px 24px;text-align:center;">
                    <div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;background:#166534;border-radius:50%;margin-bottom:16px;">
                        <svg width="28" height="28" fill="none" stroke="#fff" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h2 style="font-family:'Sora',sans-serif;font-size:20px;color:#14532d;margin-bottom:8px;">Signed SLA Received!</h2>
                    <p style="font-size:13px;color:#166534;margin-bottom:4px;">Thank you. Your signed SLA was uploaded on {{ $onboardingRequest->lsa_signed_at?->format('M j, Y \a\t g:i A') ?? now()->format('M j, Y') }}.</p>
                    <p style="font-size:12px;color:#4b7c5a;margin-top:10px;">The Legal Department will review your SLA. You will receive an email with next steps once the review is complete.</p>
                    <p style="font-size:12px;color:#6b7280;margin-top:20px;">This page will close in <strong id="close-countdown">5</strong> seconds&hellip;</p>
                    <button onclick="window.close()" style="margin-top:16px;padding:10px 28px;background:#166534;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                        Close Tab
                    </button>
                </div>
                <script>
                    (function () {
                        var n = 5;
                        var el = document.getElementById('close-countdown');
                        var t = setInterval(function () {
                            n--;
                            if (el) el.textContent = n;
                            if (n <= 0) { clearInterval(t); window.close(); }
                        }, 1000);
                    })();
                </script>
            @else
                <h2>Upload Signed SLA</h2>
                <p>Print the downloaded SLA, sign and stamp it, scan it, then upload the PDF below.</p>
                <form method="POST" action="{{ route('phs.lsa.upload', [$onboardingRequest->id, $token]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="file-drop" onclick="document.getElementById('signed_lsa_input').click()">
                        <label>
                            <div class="icon">📄</div>
                            <div style="font-size:13px;color:#4a4760;font-weight:600;">Click to select signed SLA</div>
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
    </div>
</div>
@endsection
