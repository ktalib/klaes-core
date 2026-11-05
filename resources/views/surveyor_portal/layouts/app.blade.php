<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'KSSGOF Portal')</title>
    <meta name="theme-color" content="#0B3A7E">
    <link rel="icon" href="{{ asset('assets/logo/kssgof-seal.jpg') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@0.429.0/dist/umd/lucide.min.js" crossorigin="anonymous"></script>
    <style>
        :root { --ks-blue: #0B3A7E; --ks-blue-dark: #082B5E; --ks-green: #15803D; --ks-gold: #C99A1E; --ks-ink: #14171A; --ks-soft: #4B5259; --ks-line: #DBDEE2; --ks-page: #F5F6F7; --ks-danger: #9F1239; }
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
    </style>
    @stack('styles')
</head>
<body>
    @yield('body')
    <script>if (window.lucide) lucide.createIcons();</script>
    @stack('scripts')
</body>
</html>
