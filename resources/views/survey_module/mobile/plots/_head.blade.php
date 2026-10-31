{{-- Plot Confirmation app pages: shared head (2026-10-30). Pass $title. --}}
<meta charset="UTF-8">
<link rel="icon" type="image/png" href="{{ asset('assets/logo/SCF.png') }}">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#1e1b2e">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }} — Plot Confirmation</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('survey_module.mobile._styles')
<style>
    body { padding-bottom: calc(84px + env(safe-area-inset-bottom)); }
    .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .item { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #efedf3; font-size: 14px; text-decoration: none; color: inherit; }
    .item:last-child { border-bottom: 0; }
    .sub { font-size: 12.5px; color: var(--muted); margin-top: 2px; }
    .kv { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; font-size: 14px; margin: 8px 0 0; }
    .kv dt { color: var(--muted); }
    .kv dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }
    .st { display: inline-block; font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 2px 9px; white-space: nowrap; }
    .st.ok { background: #ecfdf5; color: #047857; }
    .st.warn { background: #fffbeb; color: #92400e; }
    .st.bad { background: #fef2f2; color: #b91c1c; }
    .err-box { color: #b91c1c; font-size: 13.5px; margin-top: 8px; }
    .seg { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .seg label { display: flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--line); border-radius: 12px; padding: 11px 8px; font-weight: 600; font-size: 14px; background: #fff; margin: 0; }
    .seg input { accent-color: var(--primary); }
    .seg label.on { border-color: var(--primary); background: var(--primary-50); color: var(--primary-dark); }
    .thumbs { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; margin-top: 8px; }
    .thumbs img { width: 100%; height: 86px; object-fit: cover; border-radius: 10px; display: block; }
    .stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
    .stats .card { padding: 12px 14px; }
    .stats b { font-size: 24px; display: block; }
    .stats span { font-size: 12.5px; color: var(--muted); }
    select.input { appearance: auto; }
</style>
