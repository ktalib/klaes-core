<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $portalPageTitle = trim($__env->yieldContent('title', ''));
        $portalPageTitle = str_replace(
            ["KNSSGOF Portal", "Kano State Surveyor General's Office"],
            [$portalSettings['portal_name'], $portalSettings['office_name']],
            $portalPageTitle
        );
    @endphp
    <title>{{ $portalPageTitle !== '' ? $portalPageTitle : $portalSettings['portal_name'] }}</title>
    <meta name="theme-color" content="{{ $portalSettings['primary_color'] }}">
    <link rel="icon" href="{{ asset($portalSettings['logo_favicon']) }}">
    <link rel="apple-touch-icon" href="{{ asset($portalSettings['logo_favicon']) }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.429.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
    <style>
        :root { --ks-blue: {{ $portalSettings['primary_color'] }}; --ks-blue-dark: {{ $portalSettings['primary_dark_color'] }}; --ks-green: {{ $portalSettings['green_color'] }}; --ks-gold: {{ $portalSettings['gold_color'] }}; --ks-ink: #14171A; --ks-soft: #4B5259; --ks-line: #DBDEE2; --ks-page: #F5F6F7; --ks-danger: #9F1239; }
        body { background: var(--ks-page); color: var(--ks-ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .ks-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; border: 0; cursor: pointer; text-decoration: none; }
        .ks-btn-primary { background: var(--ks-blue); color: #fff; }
        .ks-btn-primary:hover { background: var(--ks-blue-dark); }
        .ks-btn-green { background: var(--ks-green); color: #fff; }
        .ks-btn-light { background: #fff; color: var(--ks-ink); border: 1px solid var(--ks-line); }
        .ks-card { background: #fff; border: 1px solid var(--ks-line); border-radius: 12px; }
        .ks-input { width: 100%; padding: 10px 12px; border: 1px solid #B8BEC5; border-radius: 8px; font-size: 14px; background: #fff; color: var(--ks-ink); }
        .ks-input:focus { outline: 2px solid var(--ks-gold); outline-offset: 1px; }
        .ks-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; color: var(--ks-ink); }
        .ks-req { color: var(--ks-danger); }
        .ks-error { color: var(--ks-danger); font-size: 13px; margin-top: 4px; }
        .ks-help { color: var(--ks-soft); font-size: 13px; }
        .ks-table { width: 100%; font-size: 14px; border-collapse: collapse; }
        .ks-table th { text-align: left; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--ks-soft); border-bottom: 1px solid var(--ks-line); white-space: nowrap; }
        .ks-table td { padding: 10px 12px; border-bottom: 1px solid #ECEEF0; vertical-align: top; }
        .ks-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #ECEEF0; color: var(--ks-ink); }
        .ks-badge.submitted { background: #FEF3C7; color: #78350F; }
        .ks-badge.issued { background: #DBEAFE; color: #1E3A8A; }
        .ks-badge.accepted { background: #DCFCE7; color: #14532D; }
        .ks-badge.rejected { background: #FFE4E6; color: #881337; }
        .ks-flash { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 16px; }
        .ks-flash.ok { background: #DCFCE7; color: #14532D; }
        .ks-flash.bad { background: #FFE4E6; color: #881337; }
        .ks-nav a { display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 8px; font-size: 14px; font-weight: 600; color: var(--ks-soft); text-decoration: none; }
        .ks-nav a.on, .ks-nav a:hover { background: #E8EDF5; color: var(--ks-blue); }
        .ks-topbar { background: rgba(255,255,255,.94); backdrop-filter: blur(6px); border-bottom: 1px solid var(--ks-line); position: sticky; top: 0; z-index: 20; }
        .ks-guest-banner { position: relative; background: #fff; border-right: 1px solid var(--ks-line); overflow: hidden; }
        .ks-guest-banner::before { content: ""; position: absolute; inset: 0; pointer-events: none;
            background-image: linear-gradient(rgba(11,58,126,.06) 1px, transparent 1px), linear-gradient(90deg, rgba(11,58,126,.06) 1px, transparent 1px);
            background-size: 40px 40px; -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%); mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%); }
        .ks-guest-banner::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 6px; background: linear-gradient(90deg, var(--ks-blue) 0 33.3%, var(--ks-green) 33.3% 66.6%, var(--ks-gold) 66.6% 100%); }
    </style>
    @stack('styles')
</head>
<body>
    @yield('body')
    @if($portalSettings['support_email'] || $portalSettings['support_phone'])
        <footer class="ks-help" style="padding:18px 16px;text-align:center;border-top:1px solid var(--ks-line);background:#fff;">
            <span>Portal support</span>
            @if($portalSettings['support_email'])
                <a href="mailto:{{ $portalSettings['support_email'] }}" style="color:var(--ks-blue);margin-left:8px;">{{ $portalSettings['support_email'] }}</a>
            @endif
            @if($portalSettings['support_phone'])
                <a href="tel:{{ $portalSettings['support_phone'] }}" style="color:var(--ks-blue);margin-left:8px;">{{ $portalSettings['support_phone'] }}</a>
            @endif
        </footer>
    @endif
    <script>if (window.lucide) lucide.createIcons();</script>
    @stack('scripts')
</body>
</html>
