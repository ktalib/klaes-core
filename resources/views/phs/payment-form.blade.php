@extends('phs.layouts.app')

@section('title', 'PHS Portal — Select Subscription Package')

@section('content')
<div id="preloader" style="position:fixed;inset:0;background:var(--phs-preloader-bg,#fff);display:flex;align-items:center;justify-content:center;z-index:9999;">
    <img src="http://app.klaes.ng/storage/upload/logo/klas_logo.gif" alt="Loading..." style="width:200px;height:auto;">
</div>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DM Sans', sans-serif; background: #f0f2ff; min-height: 100vh; }
    .page-wrapper { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
    .card { background: #fff; border-radius: 20px; border: 1px solid rgba(108,99,255,.1); box-shadow: 0 4px 24px rgba(108,99,255,.07); padding: 36px 40px; }
    .ministry-header { display: flex; align-items: center; gap: 14px; margin-bottom: 14px; }
    .ministry-header img { height: 52px; width: auto; flex-shrink: 0; }
    .ministry-title { flex: 1; text-align: center; }
    .ministry-title h2 { font-family: 'Sora', sans-serif; font-size: 14px; font-weight: 700; color: #166534; margin: 0; line-height: 1.3; text-transform: uppercase; }
    .ministry-title h3 { font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 700; color: #374151; margin: 3px 0 0; text-transform: uppercase; }
    .ministry-title p { font-size: 11px; color: #6b7280; margin: 4px 0 0; }
    .divider-green { height: 3px; background: #166534; border-radius: 2px; margin-bottom: 28px; }
    h1 { font-family: 'Sora', sans-serif; font-size: 20px; color: #1e1b3a; margin-bottom: 4px; }
    .subtitle { color: #6b7280; font-size: 13px; margin-bottom: 24px; }
    .meta-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 18px; margin-bottom: 28px; font-size: 13px; line-height: 1.8; }
    .meta-box .lbl { color: #6b7280; display: inline-block; width: 120px; }
    .meta-box .val { font-weight: 600; color: #1e1b3a; }
    .packages { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 28px; }
    .pkg-card { border: 2px solid #e5e7eb; border-radius: 14px; padding: 20px 16px; cursor: pointer; transition: border-color .15s, box-shadow .15s; background: #fff; text-align: center; position: relative; }
    .pkg-card:hover { border-color: #6c63ff; box-shadow: 0 2px 12px rgba(108,99,255,.12); }
    .pkg-card.selected { border-color: #6c63ff; background: #f5f3ff; box-shadow: 0 2px 12px rgba(108,99,255,.15); }
    .pkg-card input[type=radio] { position: absolute; opacity: 0; width: 0; height: 0; }
    .pkg-name { font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700; color: #1e1b3a; margin-bottom: 6px; }
    .pkg-tokens { font-size: 13px; color: #6c63ff; font-weight: 600; margin-bottom: 4px; }
    .pkg-members { font-size: 11px; color: #9ca3af; margin-bottom: 10px; }
    .pkg-price { font-family: 'Sora', sans-serif; font-size: 20px; font-weight: 700; color: #166534; }
    .pkg-price span { font-size: 12px; font-weight: 400; color: #6b7280; }
    .check-badge { position: absolute; top: 10px; right: 10px; width: 20px; height: 20px; border-radius: 50%; background: #6c63ff; display: none; align-items: center; justify-content: center; }
    .check-badge svg { width: 12px; height: 12px; stroke: #fff; fill: none; stroke-width: 2.5; }
    .pkg-card.selected .check-badge { display: flex; }
    .btn-pay { display: flex; align-items: center; justify-content: center; gap: 10px; background: #166534; color: #fff; padding: 14px 28px; border-radius: 10px; border: none; font-family: 'Sora', sans-serif; font-size: 15px; font-weight: 700; cursor: pointer; width: 100%; transition: background .15s; }
    .btn-pay:hover { background: #14532d; }
    .btn-pay:disabled { background: #9ca3af; cursor: not-allowed; }
    .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; font-weight: 500; }
    .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .note { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 14px; }

    /* Dark Mode Overrides */
    .dark body { background: #0f1117; }
    .dark .card { background: #1f2937; border-color: rgba(108,99,255,.15); box-shadow: 0 4px 24px rgba(0,0,0,.3); }
    .dark h1, .dark .pkg-name { color: #f3f4f6; }
    .dark .subtitle, .dark .meta-box .lbl, .dark .pkg-members, .dark .pkg-price span, .dark .note { color: #9ca3af; }
    .dark .meta-box { background: #111827; border-color: #374151; }
    .dark .meta-box .val { color: #f3f4f6; }
    .dark .pkg-card { border-color: #374151; background: #111827; }
    .dark .pkg-card:hover { border-color: #6c63ff; }
    .dark .pkg-card.selected { border-color: #6c63ff; background: #1e1b4b; }
    .dark .ministry-title h2 { color: #4ade80; }
    .dark .ministry-title h3 { color: #e5e7eb; }
    .dark .btn-theme-toggle { border-color: #374151; color: #d1d5db; }
</style>

<div class="page-wrapper">
    <div class="card">
        <div class="ministry-header">
            <img src="http://app.klaes.ng/assets/logo/ministry2.jpeg" alt="">
            <div class="ministry-title">
                <h2>Ministry of Land &amp; Physical Planning</h2>
                <h3>to Department of Deeds</h3>
                <p class="dark:text-gray-400">PHS Portal &mdash; Subscription Payment</p>
            </div>
            <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="">
        </div>
        <div class="divider-green"></div>

        <h1>Select Your Subscription Package</h1>
        <p class="subtitle">Choose the package that best fits your organization's needs. You will be redirected to Paystack to complete your payment securely.</p>

        @if ($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <div class="meta-box">
            <div><span class="lbl">Organization:</span><span class="val">{{ $onboardingRequest->organization_name }}</span></div>
            <div><span class="lbl">Contact:</span><span class="val">{{ $onboardingRequest->contact_email }}</span></div>
        </div>

        <form method="POST" action="{{ route('phs.payment.initiate', [$onboardingRequest->id, $token]) }}" id="payment-form">
            @csrf

            @php $selectedPkg = strtolower((string) $onboardingRequest->initial_token_package); @endphp
            <div class="packages">
                @foreach ($packages as $key => $pkg)
                    @php $isSel = $selectedPkg === strtolower($key); @endphp
                    <label class="pkg-card {{ $isSel ? 'selected' : '' }}" data-key="{{ $key }}" for="pkg_{{ $key }}">
                        <input type="radio" name="package" id="pkg_{{ $key }}" value="{{ $pkg['name'] }}"
                               {{ $isSel ? 'checked' : '' }}
                               onchange="selectPackage('{{ $key }}')">
                        <div class="check-badge"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div>
                        <div class="pkg-name">{{ $pkg['name'] }}</div>
                        <div class="pkg-tokens">{{ number_format($pkg['tokens']) }} tokens</div>
                        <div class="pkg-members">Up to {{ $pkg['team_members'] }} users</div>
                        <div class="pkg-price">₦{{ number_format($pkg['price']) }}<span>/year</span></div>
                    </label>
                @endforeach
            </div>

            <button type="submit" class="btn-pay" id="pay-btn" {{ $selectedPkg ? '' : 'disabled' }}>
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                Proceed to Payment
            </button>
        </form>

        <p class="note">Payments are processed securely via Paystack. You will be redirected to their platform.</p>

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
    function selectPackage(key) {
        document.querySelectorAll('.pkg-card').forEach(function (c) {
            c.classList.toggle('selected', c.dataset.key === key);
        });
        document.getElementById('pay-btn').disabled = false;
    }
</script>
@endsection
