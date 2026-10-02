<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#f4f5fb">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>KLAES File Tracker</title>
  {{-- Set theme before paint to avoid flash --}}
  <script>
    (function(){
      try {
        var t = localStorage.getItem('klaes-theme') ||
                ((window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', t);
      } catch(e){}
    })();
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <style>
    /* ─── Design tokens ─────────────────────────────────────────── */
    :root {
      --bg: #f4f5fb;
      --bg-grad-1: #eef0fb;

        --bg-grad-2: #f7f4fb;
      --surface: #ffffff;
      --surface-2: #f7f8fc;
      --surface-3: #eef0f6;
      --text: #1a1d2b;
      --muted: #6b7185;
      --faint: #99a0b3;
      --border: #ebedf4;
      --border-strong: #e0e3ec;
      --primary: #6366f1;
      --primary-2: #8b5cf6;
      --primary-soft: rgba(99,102,241,0.10);
      --grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      --grad-warm: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
      --shadow-sm: 0 1px 3px rgba(20,23,40,0.05);
      --shadow: 0 6px 22px rgba(20,23,40,0.07);
      --shadow-lg: 0 12px 34px rgba(20,23,40,0.12);
      --tabbar: rgba(255,255,255,0.92);
      --c-sky:#0ea5e9; --c-amber:#f59e0b; --c-emerald:#10b981; --c-rose:#ef4444; --c-indigo:#6366f1;
    }
    [data-theme="dark"] {
      --bg: #0c0e15;
      --bg-grad-1: #11131d;
      --bg-grad-2: #15121f;
      --surface: #171a24;
      --surface-2: #1d212e;
      --surface-3: #242938;
      --text: #e7e9f3;
      --muted: #9aa0b6;
      --faint: #6c7286;
      --border: #262b3a;
      --border-strong: #2f3445;
      --primary: #818cf8;
      --primary-2: #a78bfa;
      --primary-soft: rgba(129,140,248,0.15);
      --grad: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
      --shadow-sm: 0 1px 3px rgba(0,0,0,0.4);
      --shadow: 0 6px 22px rgba(0,0,0,0.45);
      --shadow-lg: 0 12px 34px rgba(0,0,0,0.55);
      --tabbar: rgba(23,26,36,0.88);
    }

    * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
    body {
      font-family:'Inter',sans-serif;
      background:
        radial-gradient(1100px 480px at 100% -10%, var(--bg-grad-2), transparent 60%),
        radial-gradient(900px 460px at -10% 0%, var(--bg-grad-1), transparent 55%),
        var(--bg);
      color:var(--text); height:100vh; overflow:hidden; position:fixed; width:100%;
      transition:background-color .3s ease, color .3s ease;
    }
    #app { display:flex; flex-direction:column; height:100vh; width:100%; position:relative; overflow:hidden; }
    .screen { flex:1; overflow-y:auto; padding:18px 16px 110px; display:none; position:relative; animation:fade .35s ease; }
    .screen.active { display:block; }
    @keyframes fade { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
    .screen::-webkit-scrollbar { width:0; }

    .card {
      background:var(--surface); border:1px solid var(--border);
      border-radius:22px; box-shadow:var(--shadow); transition:background-color .3s, border-color .3s;
    }

    /* ─── Top bar ───────────────────────────────────────────────── */
    .topbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
    .topbar h1 { font-size:25px; font-weight:800; letter-spacing:-.5px; }
    .greeting-text { font-size:13px; color:var(--muted); margin-top:3px; }
    .topbar-actions { display:flex; align-items:center; gap:10px; }
    .icon-circle {
      background:var(--surface); border:1px solid var(--border); border-radius:50%;
      width:44px; height:44px; display:flex; align-items:center; justify-content:center;
      cursor:pointer; transition:.2s; box-shadow:var(--shadow-sm); position:relative; color:var(--text);
    }
    .icon-circle:active { transform:scale(.92); }
    .icon-circle i { font-size:18px; color:var(--primary); }
    .notification-badge {
      position:absolute; top:-3px; right:-3px; background:var(--c-rose); color:#fff;
      font-size:10px; font-weight:700; padding:2px 6px; border-radius:30px; min-width:18px;
      text-align:center; display:none; border:2px solid var(--surface);
    }
    .notification-wrapper { position:relative; }
    .notification-panel {
      position:absolute; top:54px; right:0; width:300px; background:var(--surface);
      border-radius:18px; box-shadow:var(--shadow-lg); z-index:200; display:none; border:1px solid var(--border);
      overflow:hidden;
    }
    .notification-panel.show { display:block; }
    .notification-header { padding:14px 16px; border-bottom:1px solid var(--border); font-weight:700; font-size:14px; display:flex; justify-content:space-between; align-items:center; background:var(--surface-2); }
    .mark-read { font-size:11px; color:var(--primary); cursor:pointer; background:none; border:none; font-weight:600; }
    .notification-list { max-height:350px; overflow-y:auto; }
    .notification-item { padding:12px 16px; border-bottom:1px solid var(--border); cursor:pointer; transition:.15s; }
    .notification-item:hover { background:var(--surface-2); }
    .notification-item.unread { background:var(--primary-soft); }
    .notification-title { font-size:12px; font-weight:600; }
    .notification-desc { font-size:11px; color:var(--muted); margin-top:3px; }
    .notification-time { font-size:9px; color:var(--faint); margin-top:4px; }

    /* ─── Stats ─────────────────────────────────────────────────── */
    .stats-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:18px; }
    .stat-card { padding:16px; background:var(--surface); border:1px solid var(--border); border-radius:20px; box-shadow:var(--shadow-sm); transition:transform .2s; }
    .stat-card:active { transform:scale(.98); }
    .stat-icon { width:42px; height:42px; border-radius:14px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; font-size:17px; }
    .stat-value { font-size:26px; font-weight:800; letter-spacing:-.5px; }
    .stat-label { font-size:11px; color:var(--muted); margin-top:2px; font-weight:500; }

    /* ─── Quick actions ─────────────────────────────────────────── */
    .section-title { font-size:13px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; margin:4px 2px 12px; }
    .quick-actions-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; margin-bottom:20px; }
    .quick-action-btn {
      background:var(--surface); border:1px solid var(--border); border-radius:20px;
      padding:16px 8px; text-align:center; cursor:pointer; box-shadow:var(--shadow-sm); transition:.2s;
    }
    .quick-action-btn:active { transform:scale(.95); }
    .quick-action-btn .qa-ic {
      width:42px; height:42px; border-radius:14px; margin:0 auto 8px; display:flex; align-items:center; justify-content:center;
      background:var(--grad); color:#fff; font-size:17px; box-shadow:0 6px 14px var(--primary-soft);
    }
    .quick-action-btn.warm .qa-ic { background:var(--grad-warm); box-shadow:0 6px 14px rgba(245,158,11,.25); }
    .quick-action-btn.red .qa-ic  { background:linear-gradient(135deg,#ef4444,#b91c1c); box-shadow:0 6px 14px rgba(239,68,68,.25); }
    .quick-action-btn span { font-size:11px; font-weight:600; color:var(--text); line-height:1.25; display:block; }

    /* ─── Chart / panels ────────────────────────────────────────── */
    .panel { padding:18px; margin-bottom:18px; }
    .panel-title { font-size:14px; font-weight:700; margin-bottom:14px; display:flex; align-items:center; gap:9px; }
    .panel-title i { color:var(--primary); }
    canvas { max-height:170px; width:100% !important; }

    .priority-item { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
    .priority-item:last-child { margin-bottom:0; }
    .priority-label { width:54px; font-size:12px; font-weight:600; }
    .priority-bar { flex:1; height:9px; background:var(--surface-3); border-radius:10px; overflow:hidden; }
    .priority-bar-fill { height:100%; border-radius:10px; transition:width .6s ease; }
    .priority-value { width:34px; font-size:12px; font-weight:700; text-align:right; }
    .fill-high { background:linear-gradient(90deg,#f87171,#ef4444); }
    .fill-medium { background:linear-gradient(90deg,#fbbf24,#f59e0b); }
    .fill-low { background:linear-gradient(90deg,#34d399,#10b981); }

    .activity-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
    .activity-item:last-child { border-bottom:none; }
    .activity-icon { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
    .activity-content { flex:1; min-width:0; }
    .activity-title { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .activity-time { font-size:11px; color:var(--text); margin-top:2px; }

    /* ─── Page header (sub screens) ─────────────────────────────── */
    .page-header h1 { font-size:23px; font-weight:800; letter-spacing:-.5px; }
    .page-header p { font-size:13px; color:var(--muted); margin-top:3px; }

    /* ─── Forms ─────────────────────────────────────────────────── */
    .form-section { background:var(--surface); border:1px solid var(--border); border-radius:22px; padding:18px; margin-bottom:16px; box-shadow:var(--shadow-sm); }
    .section-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .section-header .section-icon { font-size:17px; background:var(--primary-soft); padding:10px; border-radius:13px; color:var(--primary); width:40px; height:40px; display:flex; align-items:center; justify-content:center; }
    .section-header h2 { font-size:15px; font-weight:700; }
    .section-header p { font-size:11px; color:var(--faint); margin-top:1px; }
    .field-wrap { margin-bottom:14px; }
    .field-wrap:last-child { margin-bottom:0; }
    .field-wrap label { font-size:12px; font-weight:600; margin-bottom:6px; display:block; color:var(--muted); }
    .field-wrap input, .field-wrap select, .field-wrap textarea {
      background:var(--surface-2); border:1px solid var(--border-strong); border-radius:14px;
      padding:12px 14px; font-size:14px; width:100%; outline:none; font-family:'Inter',sans-serif; color:var(--text);
      transition:.18s;
    }
    .field-wrap input::placeholder, .field-wrap textarea::placeholder { color:var(--faint); }
    .field-wrap input:focus, .field-wrap select:focus, .field-wrap textarea:focus {
      border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-soft); background:var(--surface);
    }
    .field-wrap input[readonly] { background:var(--surface-3); color:var(--muted); cursor:not-allowed; }
    select { -webkit-appearance:none; appearance:none;
      background-image:url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="%2399a0b3" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>');
      background-repeat:no-repeat; background-position:right 14px center; padding-right:36px; }
    .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .btn {
      background:var(--grad); border:none; padding:15px; border-radius:16px; font-weight:700; font-size:15px;
      color:#fff; width:100%; cursor:pointer; box-shadow:0 8px 20px var(--primary-soft); transition:.2s;
      display:inline-flex; align-items:center; justify-content:center; gap:8px;
    }
    .btn:active { transform:scale(.98); }
    .btn:disabled { opacity:.6; cursor:not-allowed; }
    .priority-selector { display:flex; gap:8px; }
    .prio-chip { flex:1; text-align:center; background:var(--surface-2); border:1px solid var(--border-strong); padding:10px; border-radius:13px; font-size:13px; font-weight:600; cursor:pointer; user-select:none; transition:.18s; color:var(--muted); }
    .prio-chip.active { background:var(--grad); color:#fff; border-color:transparent; box-shadow:0 6px 14px var(--primary-soft); }

    .success-toast, .error-toast {
      position:fixed; top:18px; left:50%; transform:translateX(-50%); color:#fff; padding:12px 22px;
      border-radius:30px; font-size:13px; font-weight:600; z-index:999; display:none; box-shadow:var(--shadow-lg);
      max-width:90%;
    }
    .success-toast { background:#059669; }
    .error-toast { background:#dc2626; }

    /* ─── Scanner ───────────────────────────────────────────────── */
    .scanner-container { position:relative; width:100%; border-radius:18px; overflow:hidden; background:#000; margin-bottom:14px; min-height:330px; }
    #qr-reader { width:100%; min-height:330px; border:none !important; }
    .camera-controls { display:flex; gap:8px; margin-top:6px; }
    .camera-controls button { flex:1; background:var(--surface-2); border:1px solid var(--border-strong); color:var(--text); padding:11px; border-radius:13px; font-weight:600; font-size:12px; cursor:pointer; }
    .flash-on { background:var(--grad) !important; color:#fff !important; border-color:transparent !important; }
    .scanner-status { padding:13px; margin-top:12px; text-align:center; border-radius:14px; background:var(--surface-2); border:1px solid var(--border); font-size:13px; color:var(--muted); }

    /* ─── Profile ───────────────────────────────────────────────── */
    .avatar-large { background:var(--grad); width:76px; height:76px; border-radius:26px; margin:0 auto 14px; display:flex; align-items:center; justify-content:center; box-shadow:0 10px 24px var(--primary-soft); }
    .profile-name { font-size:20px; font-weight:700; }
    .profile-username { font-size:13px; color:var(--muted); margin-top:2px; }
    .role-pill { background:var(--primary-soft); color:var(--primary); display:inline-block; padding:4px 14px; border-radius:30px; margin-top:10px; font-size:11px; font-weight:700; }
    .menu-item { display:flex; align-items:center; gap:12px; padding:13px 0; cursor:pointer; font-size:14px; font-weight:500; }
    .menu-item i:first-child { color:var(--primary); width:20px; }
    .divider { height:1px; background:var(--border); margin:4px 0; }
    .ghost-btn { background:var(--surface-2); border:1px solid var(--border-strong); color:var(--text); }

    .result-card { background:var(--surface); border:1px solid var(--border); border-radius:18px; box-shadow:var(--shadow-sm); overflow:hidden; margin-top:8px; }

    /* ─── Select2 (themed to match) ──────────────────────────────── */
    #fileSearchSelect + .select2-container { flex:1; }
    .select2-container--default .select2-selection--single { height:46px; background:var(--surface-2); border:1px solid var(--border-strong); border-radius:14px; display:flex; align-items:center; padding:0 14px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color:var(--text); font-size:14px; line-height:normal; padding:0; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color:var(--faint); }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height:44px; }
    .select2-dropdown { background:var(--surface); border:1px solid var(--border-strong); border-radius:14px; color:var(--text); }
    .select2-search--dropdown .select2-search__field { background:var(--surface-2); border:1px solid var(--border-strong); border-radius:8px; color:var(--text); }
    .select2-results__option { color:var(--text); }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background:var(--primary); }

    /* ─── Tab bar ───────────────────────────────────────────────── */
    .tab-bar {
      position:fixed; bottom:14px; left:14px; right:14px; background:var(--tabbar);
      backdrop-filter:blur(22px); -webkit-backdrop-filter:blur(22px); border-radius:26px; display:flex;
      justify-content:space-around; align-items:center; padding:8px 8px; border:1px solid var(--border);
      box-shadow:var(--shadow-lg); z-index:100;
    }
    .tab-item { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:3px; background:transparent; border:none; color:var(--faint); font-size:10px; font-weight:600; padding:7px 4px; border-radius:16px; cursor:pointer; transition:.2s; flex:1; }
    .tab-item i { font-size:18px; }
    .tab-item.active { color:var(--primary); }
    .tab-item.active i { transform:translateY(-1px); }
    /* Longer labels (e.g. "Requests- SCB View") wrap instead of stretching the bar. */
    .tab-item span { text-align:center; line-height:1.15; word-break:break-word; }

    @media (min-width:768px) {
      .screen { padding:24px 24px 110px; max-width:560px; margin:0 auto; }
      .tab-bar { max-width:540px; margin:0 auto; }
    }
  </style>
</head>
<body >

  {{-- @if(optional(auth()->user())->username !== 'admin1') style="display:none" @endif --}}


@include('mobile.partials.splash')
@include('mobile.partials.preloader')

{{-- Toast messages --}}
<div id="successToast" class="success-toast"></div>
<div id="errorToast"   class="error-toast"></div>

{{-- File Digital Library: inline cover card + fullscreen gallery (shared with DFR) --}}
<style>
  .dig-empty { background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px; font-size: 11px; color: var(--muted); display: flex; align-items: center; gap: 7px; }
  .dig-cover { display: flex; align-items: center; gap: 12px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 14px; padding: 12px; cursor: pointer; transition: border-color .15s, transform .05s; }
  .dig-cover:active { transform: scale(.99); }
  .dig-cover-stack { position: relative; width: 56px; height: 72px; flex-shrink: 0; }
  .dig-cover-stack::before, .dig-cover-stack::after { content: ''; position: absolute; inset: 0; border-radius: 9px; background: var(--surface); border: 1px solid var(--border-strong); }
  .dig-cover-stack::before { transform: translate(6px, 6px); opacity: .55; }
  .dig-cover-stack::after  { transform: translate(3px, 3px); opacity: .8; }
  .dig-cover-page { position: absolute; inset: 0; border-radius: 9px; overflow: hidden; background: var(--surface); border: 1px solid var(--border-strong); display: flex; align-items: center; justify-content: center; z-index: 1; }
  .dig-cover-page img { width: 100%; height: 100%; object-fit: cover; }
  .dig-cover-page i { font-size: 24px; color: var(--primary); }
  .dig-cover-meta { flex: 1; min-width: 0; }
  .dig-cover-title { font-size: 13px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 6px; }
  .dig-cover-title i { color: var(--primary); }
  .dig-cover-sub { font-size: 11px; color: var(--muted); margin-top: 3px; }
  .dig-cover-cta { font-size: 10.5px; font-weight: 700; color: var(--primary); margin-top: 7px; display: inline-flex; align-items: center; gap: 5px; }
  .dig-cover-chev { color: var(--muted); font-size: 16px; flex-shrink: 0; }

  .dig-viewer { position: fixed; inset: 0; z-index: 10001; background: #0b0b0f; display: none; flex-direction: column; }
  .dig-viewer.open { display: flex; }
  .dig-viewer-bar { display: flex; align-items: center; gap: 10px; padding: 14px; color: #fff; flex-shrink: 0; background: linear-gradient(to bottom, rgba(0,0,0,.6), transparent); }
  .dig-viewer-bar .dig-vtitle { flex: 1; min-width: 0; }
  .dig-viewer-bar .dig-vname { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .dig-viewer-bar .dig-vcount { font-size: 11px; opacity: .7; margin-top: 1px; }
  .dig-viewer-bar button { background: rgba(255,255,255,0.14); border: none; color: #fff; width: 38px; height: 38px; border-radius: 50%; font-size: 15px; cursor: pointer; flex-shrink: 0; }
  .dig-stage { flex: 1; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; touch-action: pan-y; }
  .dig-stage img { max-width: 100%; max-height: 100%; object-fit: contain; -webkit-user-drag: none; transition: transform .2s ease; }
  .dig-stage iframe { width: 100%; height: 100%; border: none; background: #fff; }
  .dig-doc { width: 100%; height: 100%; display: flex; flex-direction: column; }
  .dig-doc iframe { flex: 1; }
  .dig-doc a { flex-shrink: 0; text-align: center; padding: 9px; font-size: 12px; font-weight: 700; color: #fff; background: rgba(0,0,0,.6); text-decoration: none; }
  /* PDF rendered to canvas via PDF.js — scrollable page stack overlaying the stage. */
  .dig-pdf { position: absolute; inset: 0; z-index: 1; background: #0b0b0f; display: flex; flex-direction: column; }
  .dig-pdf-pages { flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 10px; }
  .dig-pdf-pages canvas { max-width: 100%; height: auto; background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.4); }
  .dig-pdf-fallback { flex-shrink: 0; text-align: center; padding: 9px; font-size: 12px; font-weight: 700; color: #fff; background: rgba(0,0,0,.6); text-decoration: none; }
  .dig-pdf-spin { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,.8); font-size: 26px; }
  .dig-stage .dig-spin { position: absolute; color: rgba(255,255,255,.8); font-size: 26px; }
  .dig-nav { position: absolute; top: 0; bottom: 0; width: 26%; display: flex; align-items: center; z-index: 2; border: none; background: transparent; color: rgba(255,255,255,.85); font-size: 20px; cursor: pointer; }
  .dig-nav:disabled { opacity: 0; pointer-events: none; }
  .dig-nav .dig-nav-ic { width: 40px; height: 40px; border-radius: 50%; background: rgba(0,0,0,.35); display: flex; align-items: center; justify-content: center; }
  .dig-nav.prev { left: 0; justify-content: flex-start; padding-left: 10px; }
  .dig-nav.next { right: 0; justify-content: flex-end; padding-right: 10px; }
  .dig-strip { flex-shrink: 0; display: flex; gap: 6px; padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); overflow-x: auto; background: rgba(0,0,0,.55); -webkit-overflow-scrolling: touch; }
  .dig-strip::-webkit-scrollbar { height: 0; }
  .dig-strip-item { position: relative; flex: 0 0 auto; width: 46px; height: 60px; border-radius: 6px; overflow: hidden; border: 2px solid transparent; background: #1b1f29; cursor: pointer; display: flex; align-items: center; justify-content: center; }
  .dig-strip-item.active { border-color: #818cf8; }
  .dig-strip-item img { width: 100%; height: 100%; object-fit: cover; }
  .dig-strip-item i { color: rgba(255,255,255,.5); font-size: 14px; }
  .dig-strip-item .dig-strip-n { position: absolute; bottom: 0; left: 0; right: 0; font-size: 8px; font-weight: 700; text-align: center; background: rgba(0,0,0,.6); color: #fff; }
</style>
<div class="dig-viewer" id="digViewer">
  <div class="dig-viewer-bar">
    <div class="dig-vtitle">
      <div class="dig-vname" id="digVName">—</div>
      <div class="dig-vcount" id="digVCount">—</div>
    </div>
    <button id="digRotateLeft" onclick="digRotate(-1)" title="Rotate left" style="display:none;"><i class="fas fa-rotate-left"></i></button>
    <button id="digRotateRight" onclick="digRotate(1)" title="Rotate right" style="display:none;"><i class="fas fa-rotate-right"></i></button>
    <button onclick="closeDigViewer()" title="Close"><i class="fas fa-times"></i></button>
  </div>
  <div class="dig-stage" id="digStage">
    <button class="dig-nav prev" id="digNavPrev" onclick="digPrev()" title="Previous"><span class="dig-nav-ic"><i class="fas fa-chevron-left"></i></span></button>
    <div class="dig-spin" id="digStageInner"></div>
    <button class="dig-nav next" id="digNavNext" onclick="digNext()" title="Next"><span class="dig-nav-ic"><i class="fas fa-chevron-right"></i></span></button>
  </div>
  <div class="dig-strip" id="digStrip"></div>
</div>

<div id="app">
  <!-- ═══ DASHBOARD SCREEN ═══ -->
  <div id="dashboard-screen" class="screen">
    <div class="topbar">
      <div>
        <h1>Dashboard</h1>
        <div class="greeting-text" id="greetingMsg"></div>
      </div>
      <div class="topbar-actions">
        <button class="icon-circle" id="themeToggle" onclick="toggleTheme()" title="Toggle theme">
          <i id="themeToggleIcon" class="fas fa-moon"></i>
        </button>
        <div class="notification-wrapper">
          <button class="icon-circle" id="notificationBell">
            <i class="fas fa-bell"></i>
            <span class="notification-badge" id="notificationBadge"></span>
          </button>
          <div class="notification-panel" id="notificationPanel">
            <div class="notification-header">
              <span>Notifications</span>
              <button class="mark-read" id="markAllRead">Mark all read</button>
            </div>
            <div class="notification-list" id="notificationList"></div>
          </div>
        </div>
      </div>
    </div>

    @if($isScbMonitor)
    <!-- Requests Today — mirrors the web Quick Search tile. Lives here rather than
         on the File Requests screen so that screen stays a compact working queue. -->
    <div id="fsTodayCard" style="position:relative;overflow:hidden;border:1px solid #a5b4fc;background:#6366f1;border-radius:16px;padding:16px;margin-bottom:16px;box-shadow:0 4px 10px rgba(79,70,229,.25);cursor:pointer;" onclick="setActiveTab('requests')">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
        <div>
          <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:#e0e7ff;">Requests Today</div>
          <div id="fsToday" style="margin-top:4px;font-size:30px;font-weight:800;line-height:1;color:#fff;">—</div>
        </div>
        <span style="display:flex;height:36px;width:36px;flex-shrink:0;align-items:center;justify-content:center;border-radius:10px;background:#4338ca;color:#fff;">
          <i class="fas fa-inbox"></i>
        </span>
      </div>
      <div style="margin-top:12px;border-top:1px solid #818cf8;padding-top:8px;font-size:11px;font-weight:500;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
          <span style="color:#e0e7ff;"><span style="display:inline-block;height:6px;width:6px;border-radius:50%;background:#fff;margin-right:6px;"></span>Found</span>
          <span id="fsTodayFound" style="font-weight:700;color:#fff;">—</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
          <span style="color:#e0e7ff;"><span style="display:inline-block;height:6px;width:6px;border-radius:50%;background:#fff;margin-right:6px;"></span>Not Found</span>
          <span id="fsTodayNotFound" style="font-weight:700;color:#fff;">—</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <span style="color:#e0e7ff;"><span style="display:inline-block;height:6px;width:6px;border-radius:50%;background:#fff;margin-right:6px;"></span>Awaiting</span>
          <span id="fsTodayAwaiting" style="font-weight:700;color:#fff;">—</span>
        </div>
      </div>
    </div>
    @endif

    <div class="section-title">Quick Actions</div>
    <div class="quick-actions-grid">
      <div class="quick-action-btn" onclick="setActiveTab('scanner')">
        <div class="qa-ic"><i class="fas fa-qrcode"></i></div><span>Scan QR</span>
      </div>
      {{-- SCB Monitors don't raise searches — they work the request queue — so the
           Quick Search shortcut is replaced by one into their File Requests View. --}}
      @if($isScbMonitor && !auth()->user()->isSuperAdmin())
      <div class="quick-action-btn" onclick="setActiveTab('requests')">
        <div class="qa-ic"><i class="fas fa-inbox"></i></div><span>SCB View</span>
      </div>
      @else
      <div class="quick-action-btn" onclick="setActiveTab('files')">
        <div class="qa-ic"><i class="fas fa-magnifying-glass"></i></div><span>Quick Search</span>
      </div>
      @endif
      {{-- DFR quick action commented out for now
      <div class="quick-action-btn red" onclick="window.location='{{ route('mobile.digital-request') }}'">
        <div class="qa-ic"><i class="fas fa-paper-plane"></i></div><span>Digital File Request</span>
      </div>
      --}}
    </div>

    <div class="card panel">
      <div class="panel-title"><i class="fas fa-chart-line"></i> Weekly Activity</div>
      <canvas id="weeklyChart"></canvas>
    </div>

    <div class="card panel">
      <div class="panel-title"><i class="fas fa-chart-pie"></i> Priority Distribution</div>
      <div id="priorityDistribution"></div>
    </div>

    <div class="card panel">
      <div class="panel-title"><i class="fas fa-clock-rotate-left"></i> {{ $isScbMonitor ? 'Recent File Search Activity' : 'Recent Activity' }}</div>
      <div id="recentActivityList" class="activity-list"></div>
    </div>
  </div>

  <!-- ═══ LOG A FILE SCREEN ═══ -->
  @php
    $deptOptions = $offices->pluck('department')->filter()->unique()->sort()->values();
  @endphp
  <div id="create-screen" class="screen">
    <div class="page-header" style="margin-bottom:18px;"><h1>Log a File</h1><p>Create a new file tracker</p></div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-file-circle-plus"></i></span>
        <div><h2>File Details</h2><p>Enter the file information</p></div>
      </div>
      <div class="field-wrap">
        <label>File Number</label>
        <input type="text" id="createFileNumber" placeholder="e.g. RES-2015-4859" autocomplete="off">
      </div>
      <div class="field-wrap">
        <label>File Title *</label>
        <input type="text" id="createFileName" placeholder="e.g. Alhaji Ibrahim Dantata" autocomplete="off">
      </div>
      <div class="grid-2">
        <div class="field-wrap">
          <label>Tracking ID</label>
          <input type="text" id="trackingIdField" readonly>
        </div>
        <div class="field-wrap">
          <label>Log ID</label>
          <input type="text" id="logIdField" readonly>
        </div>
      </div>
      <div class="field-wrap">
        <label>Priority</label>
        <div class="priority-selector">
          <span class="prio-chip" data-prio="LOW">Low</span>
          <span class="prio-chip active" data-prio="MEDIUM">Medium</span>
          <span class="prio-chip" data-prio="HIGH">High</span>
        </div>
      </div>
      <div class="field-wrap">
        <label>Status</label>
        <select id="fileStatus">
          <option value="Log-in" selected>Log-in</option>
          <option value="Log-out">Log-out</option>
          <option value="Canceled">Canceled</option>
        </select>
      </div>
      <div class="field-wrap">
        <label>Notes / Remarks</label>
        <textarea id="createNotes" rows="2" placeholder="Reason the file is in this office..."></textarea>
      </div>
    </div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-building"></i></span>
        <div><h2>Office Details</h2><p>Select department and offices</p></div>
      </div>
      <div class="field-wrap">
        <label>Department *</label>
        <select id="department">
          <option value="">Select department</option>
          @foreach($deptOptions as $dept)
            <option value="{{ $dept }}">{{ $dept }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-wrap">
        <label>Origin Office *</label>
        <select id="originOffice">
          <option value="">Select origin office</option>
          @foreach($offices as $office)
            <option value="{{ $office->office_code }}" data-name="{{ $office->office_name }}">{{ $office->office_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-wrap">
        <label>Receiving Office *</label>
        <select id="receivingOffice">
          <option value="">Select receiving office</option>
          @foreach($offices as $office)
            <option value="{{ $office->office_code }}" data-name="{{ $office->office_name }}">{{ $office->office_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="field-wrap">
        <label>Receiving Officer *</label>
        <select id="receivingOfficer">
          <option value="">Select receiving officer</option>
          @foreach($officers as $officer)
            @php $fullName = trim($officer->first_name.' '.$officer->last_name) ?: $officer->username; @endphp
            <option value="{{ $officer->id }}" data-name="{{ $fullName }}">{{ $fullName }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-clock"></i></span>
        <div><h2>Movement Log</h2><p>Log in / log out date &amp; time</p></div>
      </div>
      <div class="grid-2">
        <div class="field-wrap"><label>Log In Date</label><input type="date" id="logInDate"></div>
        <div class="field-wrap"><label>Log In Time</label><input type="time" id="logInTime"></div>
      </div>
      <div class="grid-2">
        <div class="field-wrap"><label>Log Out Date</label><input type="date" id="logOutDate"></div>
        <div class="field-wrap"><label>Log Out Time</label><input type="time" id="logOutTime"></div>
      </div>
    </div>

    <button id="submitCreateBtn" class="btn" onclick="createNewFile()">
      <i class="fas fa-file-circle-plus"></i> Log a File
    </button>
  </div>

  <!-- ═══ FILES SCREEN ═══ -->
  <div id="files-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>File Search</h1><p>Look up a file's movement history</p></div>

    <div class="form-section">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-search"></i></span>
        <div><h2>Search File</h2><p>Enter a file number or tracking ID</p></div>
      </div>
      <div class="field-wrap">
        <label>File No / Tracking ID</label>
        <div style="display:flex;gap:8px;align-items:center;">
          <select id="fileSearchSelect" style="flex:1;min-width:0;"></select>
          <input id="fileSearchManual" type="text" placeholder="Type file number…" autocomplete="off" style="display:none;flex:1;min-width:0;height:46px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:14px;padding:0 14px;font-size:14px;color:var(--text);text-transform:uppercase;">
          <button id="fileSearchBtn" type="button" class="btn" style="width:auto;height:46px;padding:0 18px;flex-shrink:0;border-radius:14px;font-size:16px;">
            <i class="fas fa-search"></i>
          </button>
        </div>
        <label for="fsBlindToggle" style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:12px;color:var(--muted);cursor:pointer;font-weight:500;">
          <input id="fsBlindToggle" type="checkbox" onchange="toggleBlindSearch()" style="width:16px;height:16px;flex-shrink:0;accent-color:var(--primary);cursor:pointer;">
          Blind request — file not yet indexed (type the file number manually)
        </label>
      </div>
    </div>

    <div id="fileSearchResult" style="margin-top:4px;"></div>
  </div>

  <!-- ═══ SCANNER SCREEN ═══ -->
  <div id="scanner-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>QR Scanner</h1><p>Scan a file QR to log movement</p></div>
    <div class="form-section">
      <div class="scanner-container"><div id="qr-reader"></div></div>
      <div class="camera-controls">
        <button id="startCameraBtn"><i class="fas fa-play"></i> Start</button>
        <button id="stopCameraBtn"><i class="fas fa-stop"></i> Stop</button>
        <button id="flashToggleBtn"><i class="fas fa-bolt"></i> Flash</button>
      </div>
      <div id="scannerStatus" class="scanner-status">
        <i class="fas fa-camera"></i> <span>Click Start to begin scanning</span>
      </div>
    </div>

    {{-- Manual entry fallback --}}
    <div class="form-section" style="margin-top:4px;">
      <div class="section-header">
        <span class="section-icon"><i class="fas fa-keyboard"></i></span>
        <div><h2>Manual Entry</h2><p>Type a Tracking ID if camera is unavailable</p></div>
      </div>
      <div class="field-wrap">
        <label>Tracking ID / File Number</label>
        <div style="display:flex;gap:8px;">
          <input type="text" id="manualScanInput" placeholder="e.g. TRK-XXXXXX" style="flex:1;">
          <button id="manualScanBtn" class="btn" style="width:auto;padding:0 16px;flex-shrink:0;white-space:nowrap;">
            <i class="fas fa-search"></i> Look up
          </button>
        </div>
      </div>
      <div id="manualScanResult"></div>
    </div>
  </div>

  @if($isOfs)
  <!-- ═══ OFS — MY FILE REQUESTS SCREEN ═══ -->
  <div id="myreq-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-end;">
      <div><h1>My File Requests</h1><p>What you sent, SCB status & where the file is</p></div>
      <button class="icon-circle" id="myReqRefreshBtn" title="Refresh" onclick="loadMyRequests()"><i class="fas fa-rotate-right"></i></button>
    </div>
    <div id="myReqContainer"></div>
  </div>
  @endif

  @if($isScbMonitor)
  <!-- ═══ SCB MONITOR — FILE REQUESTS SCREEN ═══ -->
  <div id="requests-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-end;">
      <div><h1 id="frScreenTitle">{{ (($user->fr_permissions ?? '') === 'SCB') ? 'File Request-SCB View' : 'File Request' }}</h1><p>Physical searches & FSR History</p></div>
      <button class="icon-circle" id="frRefreshBtn" title="Refresh"><i class="fas fa-rotate-right"></i></button>
    </div>

    {{-- The "Requests Today" tile lives on the Home dashboard, not here — this
         screen is a working queue and the tile ate most of the first screenful. --}}


    <div class="fr-seg">
      <button type="button" class="fr-seg-btn active" data-frview="open" onclick="setFrView('open')">Open <span class="fr-count" id="frCountOpen">0</span></button>
      <button type="button" class="fr-seg-btn" data-frview="log" onclick="setFrView('log')">FSR History <span class="fr-count" id="frCountLog">0</span></button>
    </div>
    <div class="fr-search">
      <i class="fas fa-search"></i>
      <input type="text" id="frSearch" placeholder="Search file no, title, requester…" oninput="onFrSearch()" autocomplete="off">
      <button type="button" id="frSearchClear" onclick="clearFrSearch()" title="Clear"><i class="fas fa-times"></i></button>
    </div>
    <div id="frListContainer"></div>
    <div id="frLogContainer" style="display:none;"></div>
  </div>
  <style>
    .fr-seg { display:flex; gap:6px; background:var(--primary-soft); padding:4px; border-radius:12px; margin-bottom:12px; }
    .fr-seg-btn { flex:1; border:none; background:transparent; padding:9px; border-radius:9px; font-size:12px; font-weight:700; color:var(--muted); cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:6px; }
    .fr-seg-btn.active { background:var(--primary); color:#fff; }
    /* Compact SCB list rows: the whole row is the <summary>, so drop the marker. */
    .fr-row summary::-webkit-details-marker { display:none; }
    .fr-row summary::marker { content:''; }
    .fr-row-chev { transition:transform .15s ease; }
    .fr-row details[open] .fr-row-chev { transform:rotate(180deg); }
    .fr-count { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; padding:0 5px; border-radius:30px; font-size:10px; font-weight:800; background:rgba(0,0,0,0.12); color:inherit; }
    .fr-seg-btn.active .fr-count { background:rgba(255,255,255,0.28); color:#fff; }
    .fr-search { position:relative; margin-bottom:14px; }
    .fr-search > .fa-search { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--faint); font-size:12px; }
    .fr-search input { width:100%; padding:11px 34px 11px 32px; border:1px solid var(--border,#2a2f3a); border-radius:12px; background:var(--card,#1b1f29); color:inherit; font-size:13px; }
    .fr-search input:focus { outline:none; border-color:var(--primary); }
    #frSearchClear { position:absolute; right:8px; top:50%; transform:translateY(-50%); border:none; background:transparent; color:var(--faint); cursor:pointer; padding:4px 6px; display:none; }
    #frSearchClear.show { display:inline-flex; }
  </style>
  @endif

  <!-- ═══ PROFILE SCREEN ═══ -->
  <div id="profile-screen" class="screen">
    <div class="page-header" style="margin-bottom:16px;"><h1>Profile</h1></div>
    <div class="form-section" style="text-align:center;">
      <div class="avatar-large"><i class="fas fa-user fa-2x" style="color:#fff;"></i></div>
      <div class="profile-name">{{ trim(auth()->user()->first_name.' '.auth()->user()->last_name) ?: auth()->user()->username }}</div>
      <div class="profile-username">{{ auth()->user()->username }}</div>
      <div class="role-pill">{{ strtoupper(auth()->user()->type ?? 'USER') }}</div>
    </div>

    <div class="form-section">
      <div class="menu-item" onclick="toggleTheme()">
        <i class="fas fa-circle-half-stroke"></i><span>Toggle Light / Dark</span>
        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--faint);"></i>
      </div>
      <div class="divider"></div>
      <div class="menu-item" onclick="setActiveTab('dashboard');document.getElementById('notificationBell').click();">
        <i class="fas fa-bell"></i><span>Notifications</span>
        <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--faint);"></i>
      </div>
    </div>

    <form method="POST" action="{{ route('mobile.logout') }}">
      @csrf
      <button type="submit" class="btn ghost-btn">
        <i class="fas fa-right-from-bracket"></i> Logout
      </button>
    </form>
    <div style="text-align:center;margin-top:16px;font-size:10px;color:var(--faint);">KLAES File Tracker v1.0</div>
  </div>

  <!-- Tab Bar -->
  <div class="tab-bar">
    <button class="tab-item" data-tab="dashboard"><i class="fas fa-house"></i><span>Home</span></button>

    {{-- SCB Monitors work the request queue rather than raising searches, so Quick
         Search is hidden for them (super admins keep it). --}}
    @if(!$isScbMonitor || auth()->user()->isSuperAdmin())
    <button class="tab-item" data-tab="files"><i class="fas fa-magnifying-glass"></i><span>Search</span></button>
    @endif
  @if($isOfs)
    <button class="tab-item" data-tab="myreq"><i class="fas fa-list-check"></i><span>My FRs</span></button>
    @endif
  @if($isScbMonitor)
    {{-- SCB Monitors get the compact list view under "SCB-V". Super admins keep
         the full FSR card view AND get a matching "SCB-V" tab so they can see the
         screen exactly as an SCB Monitor sees it. --}}
    @if(auth()->user()->isSuperAdmin())
    <button class="tab-item" data-tab="requests"><i class="fas fa-clipboard-list"></i><span>FSR</span></button>
    <button class="tab-item" data-tab="requests-scb"><i class="fas fa-eye"></i><span>SCB View</span></button>
    @else
    <button class="tab-item" data-tab="requests"><i class="fas fa-inbox"></i><span>SCB View</span></button>
    @endif
    @endif
    <button class="tab-item" data-tab="scanner"><i class="fas fa-qrcode"></i><span>Scan</span></button>
    {{-- DFR tab commented out for now
    <button class="tab-item" data-tab="dfr" onclick="window.location='{{ route('mobile.digital-request') }}'"><i class="fas fa-paper-plane"></i><span>DFR</span></button>
    --}}
    <button class="tab-item" data-tab="profile"><i class="fas fa-user"></i><span>Profile</span></button>
  </div>
</div>

<script>
// ─── Server-injected data ─────────────────────────────────────────────────
const CSRF_TOKEN  = '{{ csrf_token() }}';
const API_BASE    = '{{ url("/api/file-trackers") }}';
const MOB_BASE    = '{{ url("/api/mobile") }}';
const CURRENT_USER = {
  id:       {{ auth()->id() }},
  name:     '{{ addslashes(trim(auth()->user()->first_name." ".auth()->user()->last_name) ?: auth()->user()->username) }}',
  username: '{{ auth()->user()->username }}',
};
const IS_SCB_MONITOR = {{ $isScbMonitor ? 'true' : 'false' }};
// OFS (Office Priority Search) ranked officer — may raise prioritised File/Blind
// Requests to the SCB Monitor straight from File Search.
const IS_OFS = {{ ($isOfs ?? false) ? 'true' : 'false' }};
const IS_SUPER_ADMIN = {{ auth()->user()->isSuperAdmin() ? 'true' : 'false' }};
// Render the File Requests inbox as the compact SCB list rather than full cards.
// Always on for SCB Monitors; for super admins only on the "FSR (SCB)" tab.
let frCompact = !IS_SUPER_ADMIN;
// Origin registries (+ short codes) for the File Search request dropdown.
const REGISTRIES = @json($registries ?? []);
// Registry theme colours — mirror Create File Tracker (?url=kangis / sltr / st /
// cadastral). Drives the Registry (Origin) code badge + Send button colour.
const REGISTRY_THEME = {
  'KANGIS Registry':        '#ea580c', // orange  (KANGIS)
  'SLTR Registry':          '#65a30d', // lime    (SLTR)
  'ST Registry':            '#2563eb', // blue    (ST)
  'Registry 1 - Cadastral': '#8B4513', // brown   (Cadastral)
  'Registry 2 - Cadastral': '#8B4513',
  'DCIV Registry':          '#065f46', // green   (DCIV)
};
// Requester cascade (mirrors Quick Search): Department → Office → Officer.
@php
    $reqOffices = ($offices ?? collect())->map(fn ($o) => [
        'code'       => $o->office_code,
        'name'       => $o->office_name,
        'department' => $o->department,
    ])->values();
    $reqOfficers = ($officers ?? collect())->map(fn ($o) => [
        'name'          => trim(($o->first_name ?? '') . ' ' . ($o->last_name ?? '')) ?: $o->username,
        'department_id' => $o->department_id,
    ])->filter(fn ($o) => $o['name'] !== '')->values();
    $reqDeptNameToId = ($departmentIds ?? collect())->mapWithKeys(fn ($d) => [$d->name => $d->id]);
@endphp
const REQ_DEPARTMENTS = @json($departments ?? []);
const REQ_OFFICES     = @json($reqOffices);
const REQ_OFFICERS    = @json($reqOfficers);
const REQ_DEPT_NAME_TO_ID = @json($reqDeptNameToId);
// Request Purpose lookup (id, name, turnaround_days) — mirrors Quick Search.
const REQUEST_PURPOSES = @json($requestPurposes ?? []);
const FS_DEPT_OTHER   = '__DEPT_OTHER__';
const FS_OFFICE_OTHER = '__OFFICE_OTHER__';

// Quick Search / File Location outcome styling
const LOC_STATUS_META = {
  IN_TRANSIT:                 { label:'In Transit',                 color:'#f59e0b', icon:'fa-truck-fast' },
  IN_ARCHIVE:                 { label:'In Archive',                 color:'#10b981', icon:'fa-box-archive' },
  IN_ARCHIVE_FOUND:           { label:'In Archive — Found',         color:'#10b981', icon:'fa-circle-check' },
  IN_ARCHIVE_NOT_FOUND:       { label:'In Archive — Not Found',     color:'#ef4444', icon:'fa-triangle-exclamation' },
  IN_POOL_OFFICE:             { label:'In Pool Office',             color:'#0ea5e9', icon:'fa-folder-open' },
  IN_POOL_OFFICE_FOUND:       { label:'In Pool Office — Found',     color:'#10b981', icon:'fa-circle-check' },
  IN_POOL_OFFICE_NOT_FOUND:   { label:'In Pool Office — Not Found', color:'#ef4444', icon:'fa-triangle-exclamation' },
  PENDING_FILE:               { label:'Pending (Not Indexed)',      color:'#6b7185', icon:'fa-circle-question' },
  BLIND_REQUEST_SENT:         { label:'Blind Request Sent',         color:'#dc2626', icon:'fa-paper-plane' },
  FILE_NOT_FOUND:             { label:'File Not Found',             color:'#ef4444', icon:'fa-triangle-exclamation' },
  MISSING_FILE:               { label:'Missing File',               color:'#ef4444', icon:'fa-triangle-exclamation' },
  REFER_TO_ORIGINAL_REGISTRY: { label:'Refer to Original Registry', color:'#6b7185', icon:'fa-share-from-square' },
};

// ─── Theme ────────────────────────────────────────────────────────────────
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  try { localStorage.setItem('klaes-theme', theme); } catch(e) {}
  const ic = document.getElementById('themeToggleIcon');
  if (ic) ic.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
  const mc = document.querySelector('meta[name="theme-color"]');
  if (mc) mc.setAttribute('content', theme === 'dark' ? '#0c0e15' : '#f4f5fb');
}
function toggleTheme() {
  const cur = document.documentElement.getAttribute('data-theme') || 'light';
  applyTheme(cur === 'dark' ? 'light' : 'dark');
  if (document.getElementById('dashboard-screen').classList.contains('active')) renderDashboard();
}

// ─── API helper (session-auth + CSRF) ────────────────────────────────────
async function api(url, opts = {}) {
  const res = await fetch(url, {
    ...opts,
    headers: {
      'Accept':          'application/json',
      'Content-Type':    'application/json',
      'X-CSRF-TOKEN':    CSRF_TOKEN,
      'X-Requested-With':'XMLHttpRequest',
      ...(opts.headers || {}),
    },
    credentials: 'same-origin',
  });
  if (res.status === 401 || res.status === 403) {
    console.warn('API 401/403 on:', url);
    return { success: false, _authError: true };
  }
  return res.json();
}

// ─── Toast ────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const el = document.getElementById(type === 'success' ? 'successToast' : 'errorToast');
  el.textContent = msg; el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 3500);
}

// ─── State ────────────────────────────────────────────────────────────────
let filesDB = [], notifications = [], weeklyChart = null;
let html5QrCode = null, isScannerRunning = false, flashEnabled = false;

// ─── Notifications ────────────────────────────────────────────────────────
function updateNotificationBadge() {
  const unread = notifications.filter(n => !n.is_read).length;
  const badge  = document.getElementById('notificationBadge');
  badge.textContent = unread;
  badge.style.display = unread > 0 ? 'block' : 'none';
}
function renderNotifications() {
  const container = document.getElementById('notificationList');
  if (!notifications.length) { container.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--faint);">No notifications</div>'; return; }
  container.innerHTML = notifications.map(n => `
    <div class="notification-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
      <div class="notification-title">${esc(n.title)}</div>
      <div class="notification-desc">${esc(n.body || '')}</div>
      <div class="notification-time">${relTime(n.created_at)}</div>
    </div>`).join('');
  container.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', async (e) => {
      e.stopPropagation();
      await api(`${MOB_BASE}/notifications/${item.dataset.id}/read`, { method: 'POST' });
      const n = notifications.find(n => String(n.id) === item.dataset.id);
      if (n) n.is_read = true;
      renderNotifications(); updateNotificationBadge();
    });
  });
}
async function loadNotifications() {
  try {
    const res = await api(`${MOB_BASE}/notifications`);
    if (res.success) {
      let list = res.data || [];
      // File Request notifications are for SCB Monitors only on the app.
      if (!IS_SCB_MONITOR) list = list.filter(n => n.type !== 'file_search_request');
      notifications = list;
      renderNotifications(); updateNotificationBadge();
    }
  } catch(e) {}
}

// ─── Utilities ────────────────────────────────────────────────────────────
function esc(s) { return String(s||'').replace(/[&<>]/g,m=>m==='&'?'&amp;':m==='<'?'&lt;':'&gt;'); }
// Darken a #rrggbb colour by pct (0..1) — used to build matching button gradients.
function shade(hex, pct) {
  const n = parseInt(String(hex||'').replace('#',''), 16);
  if (isNaN(n)) return hex;
  let r=(n>>16)&255, g=(n>>8)&255, b=n&255;
  r=Math.round(r*(1-pct)); g=Math.round(g*(1-pct)); b=Math.round(b*(1-pct));
  return '#'+((1<<24)+(r<<16)+(g<<8)+b).toString(16).slice(1);
}
function getPrioCls(p) { p=(p||'').toLowerCase(); return p==='high'?'priority-high':p==='medium'?'priority-medium':'priority-low'; }
function relTime(d) {
  if (!d) return '';
  const diff = Math.floor((new Date()-new Date(d))/60000);
  if (diff<1) return 'Just now'; if (diff<60) return `${diff}m ago`;
  if (diff<1440) return `${Math.floor(diff/60)}h ago`; return `${Math.floor(diff/1440)}d ago`;
}
function genTrackingId() { return 'TRK-'+Math.random().toString(36).substring(2,8).toUpperCase(); }
function genLogId() { const d=new Date(); return `LOG-${d.toISOString().replace(/[-:T]/g,'').split('.')[0]}-${Math.floor(Math.random()*999)}`; }
function fillDateTime() {
  const now = new Date();
  const t = now.toTimeString().substring(0,5);
  const dt= now.toISOString().split('T')[0];
  document.getElementById('logInTime').value  = t;
  document.getElementById('logInDate').value  = dt;
  document.getElementById('logOutTime').value = t;
  document.getElementById('logOutDate').value = dt;
}

// ─── Dashboard ────────────────────────────────────────────────────────────
function chartColors() {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  return {
    grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.05)',
    tick: dark ? '#9aa0b6' : '#6b7185',
  };
}
async function renderDashboard() {
  const hr = new Date().getHours();
  const greet = hr<12?'Good Morning':hr<18?'Good Afternoon':'Good Evening';
  document.getElementById('greetingMsg').textContent = `${greet}, ${CURRENT_USER.name.split(' ')[0]}!`;
  loadFsToday();   // "Requests Today" tile now sits on this screen (SCB Monitors only)
  try {
    const res = await api(`${API_BASE}/dashboard/stats`);
    if (!res || res._authError || (!res.success && !res.data)) return;
    const s = res.data;
    const pb = s.priority_breakdown||{}, h=pb.high||0, m=pb.medium||0, l=pb.low||0, tot=h+m+l||1;
    document.getElementById('priorityDistribution').innerHTML = `
        <div class="priority-item"><span class="priority-label">High</span><div class="priority-bar"><div class="priority-bar-fill fill-high" style="width:${(h/tot)*100}%"></div></div><span class="priority-value">${h}</span></div>
        <div class="priority-item"><span class="priority-label">Medium</span><div class="priority-bar"><div class="priority-bar-fill fill-medium" style="width:${(m/tot)*100}%"></div></div><span class="priority-value">${m}</span></div>
        <div class="priority-item"><span class="priority-label">Low</span><div class="priority-bar"><div class="priority-bar-fill fill-low" style="width:${(l/tot)*100}%"></div></div><span class="priority-value">${l}</span></div>`;
    // Recent Activity: SCB Monitors see their 5 most recent File Search Requests;
    // everyone else gets an empty placeholder (file-tracker activity is not shown here).
    renderRecentActivity();
    const wl = s.weekly_labels || ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
    const wdTotal = s.weekly_activity || [0,0,0,0,0,0,0];
    const wdFound = s.weekly_found || [0,0,0,0,0,0,0];
    const wdNotFound = s.weekly_not_found || [0,0,0,0,0,0,0];
    const wdAwaiting = s.weekly_awaiting || [0,0,0,0,0,0,0];
    const cc = chartColors();
    if (weeklyChart) weeklyChart.destroy();
    weeklyChart = new Chart(document.getElementById('weeklyChart').getContext('2d'), { type:'line', data:{ labels:wl, datasets:[{ label:'Total', data:wdTotal, borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,0.12)', borderWidth:2.5, fill:true, tension:0.4, pointBackgroundColor:'#8b5cf6', pointRadius:4 }]}, options:{ responsive:true, maintainAspectRatio:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, grid:{ color:cc.grid }, ticks:{ color:cc.tick, font:{ size:9 }}}, x:{ grid:{ display:false }, ticks:{ color:cc.tick, font:{ size:9 }}}}}});
  } catch(e) { console.error(e); }
}

// Recent Activity panel — only SCB Monitors have File Search Requests to show.
const FSR_ACTIVITY_META = {
  PENDING:   { color:'#f59e0b', icon:'fa-clock' },
  SEARCHING: { color:'#0ea5e9', icon:'fa-magnifying-glass' },
  FOUND:     { color:'#10b981', icon:'fa-circle-check' },
  NOT_FOUND: { color:'#ef4444', icon:'fa-triangle-exclamation' },
  CLOSED:    { color:'#6b7185', icon:'fa-circle-xmark' },
};
async function renderRecentActivity() {
  const el = document.getElementById('recentActivityList');
  if (!el) return;
  if (!IS_SCB_MONITOR) {
    el.innerHTML = '<p style="text-align:center;color:var(--faint);font-size:12px;padding:20px;">No recent activity</p>';
    return;
  }
  try {
    // Merge the Open inbox and the FSR History, then take the 5 most recent
    // (highest id = newest) so the panel reflects the latest requests regardless
    // of whether they are still open or already handled.
    const [openRes, logRes] = await Promise.all([
      api(`${MOB_BASE}/file-requests`),
      api(`${MOB_BASE}/file-requests/log`),
    ]);
    const all = []
      .concat((openRes && openRes.success) ? (openRes.data || []) : [])
      .concat((logRes && logRes.success) ? (logRes.data || []) : []);
    const seen = new Set();
    const recent = all
      .filter(fr => !seen.has(fr.id) && seen.add(fr.id))
      .sort((a, b) => (b.id || 0) - (a.id || 0))
      .slice(0, 5);
    el.innerHTML = recent.length
      ? recent.map(fr => {
          const m = FSR_ACTIVITY_META[fr.status] || FSR_ACTIVITY_META.PENDING;
          const title = fr.file_number || fr.file_title || fr.request_no || '—';
          // "Requested by" is the selected Requester Officer, falling back to
          // office/department; "Created by" is the user account that raised it.
          const requester = fr.receiving_officer || fr.requester || fr.requester_office || fr.requester_department || '';
          const details = [
            ['fa-user',     requester,      'Requested by'],
            ['fa-building', fr.requester_office, 'Office'],
            ['fa-user-pen', fr.created_by,  'Created by'],
          ].filter(([, val]) => val && String(val).trim() && String(val).trim() !== '—')
           .map(([icon, val, label]) =>
             `<div style="font-size:12px;color:var(--text);"><i class="fas ${icon}" style="width:12px;margin-right:5px;color:var(--primary);"></i><span style="color:var(--text);">${label}:</span> ${esc(val)}</div>`
           ).join('');
          return `<div class="activity-item"><div class="activity-icon" style="background:${m.color}22;color:${m.color};"><i class="fas ${m.icon}"></i></div><div class="activity-content"><div class="activity-title">${esc(title)}</div><div class="activity-time">${esc(fr.request_no||'')}${fr.created_at ? ' · '+esc(fr.created_at) : ''}</div>${details ? `<div style="margin-top:5px;display:flex;flex-direction:column;gap:3px;">${details}</div>` : ''}</div></div>`;
        }).join('')
      : '<p style="text-align:center;color:var(--faint);font-size:12px;padding:20px;">No recent file search requests</p>';
  } catch(e) {
    el.innerHTML = '<p style="text-align:center;color:var(--faint);font-size:12px;padding:20px;">No recent file search requests</p>';
  }
}

// ─── Files ────────────────────────────────────────────────────────────────
async function loadFiles() {
  const search  = (document.getElementById('searchInput')?.value||'').trim();
  const status  = document.getElementById('statusFilter')?.value||'all';
  const prio    = document.querySelector('.priority-filter-btn.active')?.dataset.priority||'all';
  const params  = new URLSearchParams({ per_page: 50 });
  if (search) params.set('search', search);
  if (prio !== 'all') params.set('priority', prio);
  if (status === 'my-files')  params.set('handler_id',        CURRENT_USER.id);
  if (status === 'awaiting')  params.set('assignment_status', 'PENDING');
  if (status === 'completed') params.set('status',            'COMPLETED');
  try {
    const res = await api(`${API_BASE}?${params}`);
    filesDB = (res.data?.data || res.data || []).map(normalizeTracker);
  } catch(e) { console.error(e); }
  renderFileList();
}
function normalizeTracker(t) {
  const ml = Array.isArray(t.movement_log) ? t.movement_log : (t.movement_log ? JSON.parse(t.movement_log) : []);
  return {
    id: t.id, fileNumber: t.file_number||'—', fileName: t.file_title||'—',
    trackingId: t.tracking_id||'—', status: (t.status||'').toLowerCase(),
    currentLocation: t.current_office_name||t.receiving_office_name||'—',
    priority: (t.priority||'low').toLowerCase(),
    handler: t.receiving_officer_name||'—',
    handlerId: String(t.receiving_officer_id||''),
    createdAt: t.created_at,
    movements: ml.map(m=>({
      logId: m.log_id||m.logId||'—', office: m.office_name||m.office||'—',
      receivingOfficer: m.receiving_officer_name||m.receivingOfficer||'—',
      logInTime: m.log_in_time||m.logInTime||'', logInDate: m.log_in_date||m.logInDate||'',
      logOutTime: m.log_out_time||m.logOutTime||'', logOutDate: m.log_out_date||m.logOutDate||'',
      status: m.status||'active',
    }))
  };
}
function renderFileList() {
  // Guard: the file-list UI is not part of this screen set; skip if absent.
  if (!document.getElementById('fileListContainer')) return;
  const search = (document.getElementById('searchInput')?.value||'').toLowerCase();
  const prio   = document.querySelector('.priority-filter-btn.active')?.dataset.priority||'all';
  let filtered = filesDB;
  if (search) filtered = filtered.filter(f=>(f.fileName||'').toLowerCase().includes(search)||(f.fileNumber||'').toLowerCase().includes(search));
  if (prio !== 'all') filtered = filtered.filter(f=>f.priority===prio.toLowerCase());
  const myId = String(CURRENT_USER.id);
  document.getElementById('myFilesCount').textContent  = filesDB.filter(f=>f.handlerId===myId).length+' Mine';
  document.getElementById('awaitingCount').textContent = filesDB.filter(f=>f.status==='pending_acceptance'||f.status==='pending').length+' Awaiting';
  document.getElementById('othersCount').textContent   = filesDB.filter(f=>f.handlerId!==myId&&f.status!=='completed').length+' Others';
  document.getElementById('completedCount').textContent= filesDB.filter(f=>f.status==='completed').length+' Done';
  const container = document.getElementById('fileListContainer');
  if (!filtered.length) { container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-folder-open fa-2x"></i><p style="margin-top:12px;">No files found</p></div>'; return; }
  container.innerHTML = filtered.map(file => `
    <div class="tracker-card">
      <h3>${esc(file.fileName)}</h3>
      <p>${esc(file.fileNumber)} · ${esc(file.currentLocation)}</p>
    </div>`).join('');
}

// ─── Create file ──────────────────────────────────────────────────────────
async function createNewFile() {
  const fileNumber   = document.getElementById('createFileNumber').value.trim();
  const fileName     = document.getElementById('createFileName').value.trim();
  const status       = document.getElementById('fileStatus').value;
  const priority     = document.querySelector('.prio-chip.active')?.dataset.prio||'MEDIUM';
  const notes        = document.getElementById('createNotes').value.trim();
  const department   = document.getElementById('department').value;
  const originSel    = document.getElementById('originOffice');
  const recvOfficeSel= document.getElementById('receivingOffice');
  const recvOfficerSel=document.getElementById('receivingOfficer');
  const logInTime    = document.getElementById('logInTime').value;
  const logInDate    = document.getElementById('logInDate').value;
  const logOutTime   = document.getElementById('logOutTime').value;
  const logOutDate   = document.getElementById('logOutDate').value;

  if (!fileName)                       { toast('File Title is required','error'); return; }
  if (!department)                     { toast('Please select a department','error'); return; }
  if (!originSel.value)                { toast('Please select origin office','error'); return; }
  if (!recvOfficeSel.value)            { toast('Please select receiving office','error'); return; }
  if (!recvOfficerSel.value)           { toast('Please select receiving officer','error'); return; }

  const originCode = originSel.value;
  const originName = originSel.options[originSel.selectedIndex]?.dataset.name || originSel.options[originSel.selectedIndex]?.text || originCode;
  const recvCode   = recvOfficeSel.value;
  const recvName   = recvOfficeSel.options[recvOfficeSel.selectedIndex]?.dataset.name || recvOfficeSel.options[recvOfficeSel.selectedIndex]?.text || recvCode;
  const recvOfficerId   = recvOfficerSel.value;
  const recvOfficerName = recvOfficerSel.options[recvOfficerSel.selectedIndex]?.dataset.name || '';

  const btn = document.getElementById('submitCreateBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

  try {
    const payload = {
      file_number: fileNumber || null,
      file_title: fileName,
      priority: priority.toUpperCase(),
      status: status === 'Canceled' ? 'Canceled' : (status === 'Log-in' ? null : null),
      department,
      notes,
      origin_office_code: originCode,
      origin_office_name: originName,
      receiving_office_code: recvCode,
      receiving_office_name: recvName,
      receiving_officer_id: recvOfficerId,
      receiving_officer_name: recvOfficerName,
      movement_log: [{
        office_code: originCode,
        office_name: originName,
        log_in_time:  logInTime,
        log_in_date:  logInDate,
        log_out_time: logOutTime,
        log_out_date: logOutDate,
        notes: notes,
      }],
    };
    const res = await api(API_BASE, { method: 'POST', body: JSON.stringify(payload) });
    if (res.success || res.data?.id) {
      toast(`✅ File created! Tracking: ${res.data?.tracking_id||'—'}`);
      // Reset form
      ['createFileNumber','createFileName','createNotes'].forEach(id => { document.getElementById(id).value=''; });
      document.getElementById('trackingIdField').value = genTrackingId();
      document.getElementById('logIdField').value      = genLogId();
      fillDateTime();
      document.getElementById('department').value = '';
      document.getElementById('originOffice').value = '';
      document.getElementById('receivingOffice').value = '';
      document.getElementById('receivingOfficer').value = '';
      setActiveTab('files');
    } else {
      const errs = res.errors ? Object.values(res.errors).flat().join(', ') : (res.message||'Unknown error');
      toast(errs, 'error');
    }
  } catch(e) { toast('Error: '+e.message, 'error'); }

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-file-circle-plus"></i> Log a File';
}

// ─── Scanner ──────────────────────────────────────────────────────────────
async function startScanner() {
  // Camera requires HTTPS in production
  if (location.protocol !== 'https:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
    updateScanStatus('Camera requires HTTPS. Contact your administrator to enable SSL.', 'error');
    return;
  }
  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    updateScanStatus('Camera not supported in this browser or requires HTTPS.', 'error');
    return;
  }
  try {
    const devices = await Html5Qrcode.getCameras();
    if (!devices.length) { updateScanStatus('No camera found on this device.', 'error'); return; }
    html5QrCode = new Html5Qrcode('qr-reader');
    await html5QrCode.start(devices[0].id, { fps:10, qrbox:250 }, onScanSuccess, ()=>{});
    isScannerRunning = true;
    updateScanStatus('Camera ready — scanning', 'success');
    document.getElementById('startCameraBtn').innerHTML = '<i class="fas fa-sync"></i> Restart';
  } catch(e) {
    const msg = e instanceof Error ? e.message : (typeof e === 'string' ? e : (e?.name || JSON.stringify(e)));
    if (msg && (msg.includes('NotAllowed') || msg.includes('Permission'))) {
      updateScanStatus('Camera permission denied. Please allow camera access in your browser.', 'error');
    } else if (msg && msg.includes('NotFound')) {
      updateScanStatus('No camera found on this device.', 'error');
    } else {
      updateScanStatus('Camera error: ' + (msg || 'Could not access camera. HTTPS may be required.'), 'error');
    }
  }
}
async function stopScanner() {
  if (html5QrCode && isScannerRunning) {
    await html5QrCode.stop(); isScannerRunning = false;
    updateScanStatus('Scanner stopped','info');
    document.getElementById('startCameraBtn').innerHTML = '<i class="fas fa-play"></i> Start';
  }
}
async function toggleFlash() {
  if (!html5QrCode || !isScannerRunning) { updateScanStatus('Start camera first','info'); return; }
  flashEnabled = !flashEnabled;
  try { await html5QrCode.applyVideoConstraints({ advanced:[{ torch:flashEnabled }] }); } catch(e) {}
  document.getElementById('flashToggleBtn').classList.toggle('flash-on', flashEnabled);
  document.getElementById('flashToggleBtn').innerHTML = flashEnabled ? '<i class="fas fa-bolt"></i> Flash ON' : '<i class="fas fa-bolt"></i> Flash';
}
async function onScanSuccess(text) {
  const trimmed = text.trim();
  updateScanStatus(`Scanned: ${trimmed.substring(0,35)}`,'success');
  if (isScannerRunning && html5QrCode) html5QrCode.pause(true);
  try {
    const res = await api(`${MOB_BASE}/tracker/scan-and-log`, {
      method:'POST',
      body: JSON.stringify({ qr_code:trimmed, office_code:'MOB', office_name:'Mobile Scan', notes:'Scanned via mobile app' })
    });
    if (res.success) {
      const t = res.tracker;
      toast(`✅ ${t.file_title} — ${t.current_office||'logged'}`);
      await loadFiles();
    } else {
      toast(res.message||'File not found','error');
    }
  } catch(e) { toast('Scan error: '+e.message,'error'); }
  setTimeout(()=>{ if(isScannerRunning&&html5QrCode) html5QrCode.resume(); }, 2500);
}
function updateScanStatus(msg, type) {
  const el = document.getElementById('scannerStatus');
  const icon = type==='success'?'<i class="fas fa-check-circle" style="color:#10b981;"></i>':type==='error'?'<i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i>':'<i class="fas fa-camera"></i>';
  el.innerHTML = `${icon} <span>${msg}</span>`;
}

async function manualLookup() {
  const input = document.getElementById('manualScanInput');
  const resultEl = document.getElementById('manualScanResult');
  const val = input.value.trim();
  if (!val) { resultEl.innerHTML = '<p style="color:#ef4444;font-size:12px;margin-top:6px;">Please enter a Tracking ID or File Number.</p>'; return; }

  const btn = document.getElementById('manualScanBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

  try {
    const res = await api(`${API_BASE}/track/${encodeURIComponent(val)}`);
    if (res && res.success && res.data) {
      const t = res.data;
      resultEl.innerHTML = `
        <div class="result-card" style="padding:14px;margin-top:10px;">
          <div style="font-weight:700;font-size:14px;">${esc(t.file_title||'—')}</div>
          <div style="font-size:12px;color:var(--muted);margin-top:6px;">
            <span style="margin-right:12px;"><i class="fas fa-hashtag" style="color:var(--primary);"></i> ${esc(t.file_number||'—')}</span>
            <span><i class="fas fa-map-marker-alt" style="color:var(--primary);"></i> ${esc(t.current_office_name||t.receiving_office_name||'—')}</span>
          </div>
          <div style="font-size:11px;color:var(--faint);margin-top:6px;">
            Tracking: ${esc(t.tracking_id||'—')} &nbsp;|&nbsp; Priority: ${esc(t.priority||'—')} &nbsp;|&nbsp; Status: ${esc(t.status||'—')}
          </div>
        </div>`;
      input.value = '';
      await loadFiles();
    } else {
      resultEl.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;"><i class="fas fa-times-circle"></i> No file found for "<strong>${esc(val)}</strong>".</p>`;
    }
  } catch(e) {
    resultEl.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;">Error: ${esc(e.message)}</p>`;
  }

  btn.disabled = false; btn.innerHTML = '<i class="fas fa-search"></i> Look up';
}

// ─── Tab router ───────────────────────────────────────────────────────────
function setActiveTab(tabId) {
  if (tabId !== 'scanner') stopScanner();
  // "requests-scb" is the same File Requests screen rendered in SCB (compact)
  // mode — a preview tab for super admins, who otherwise see the full cards.
  frCompact = IS_SUPER_ADMIN ? (tabId === 'requests-scb') : true;
  const screenId = tabId === 'requests-scb' ? 'requests' : tabId;
  document.querySelectorAll('.screen').forEach(s=>s.classList.remove('active'));
  document.getElementById(`${screenId}-screen`).classList.add('active');
  document.querySelectorAll('.tab-item').forEach(t=>t.classList.remove('active'));
  document.querySelector(`.tab-item[data-tab="${tabId}"]`)?.classList.add('active');
  if (tabId==='dashboard') renderDashboard();
  if (tabId==='files')     $('#fileSearchSelect').select2('open');
  if (tabId==='scanner')   updateScanStatus('Click Start to begin scanning','info');
  if (tabId==='create')    initCreateForm();
  if (tabId==='requests' || tabId==='requests-scb') {
    // Compact/SCB view (all SCB Monitors, plus super admins on the "SCB View" tab)
    // reads as "File Request-SCB View"; the full FSR card view reads "File Request".
    const frTitle = document.getElementById('frScreenTitle');
    if (frTitle) frTitle.textContent = frCompact ? 'File Request-SCB View' : 'File Request';
    setFrView(frView); frView === 'open' ? loadFsrLog() : loadFileRequests();
  }
  if (tabId==='myreq')     loadMyRequests();
}

// ─── Log a File: init defaults when screen opens ───────────────────────────
function initCreateForm() {
  fillDateTime();
  const tid = document.getElementById('trackingIdField');
  const lid = document.getElementById('logIdField');
  if (tid && !tid.value) tid.value = genTrackingId();
  if (lid && !lid.value) lid.value = genLogId();
}

// ─── File Search (5-outcome locator) ───────────────────────────────────────
let lastFileSearch = null;   // most recent resolved file (for the OFS send shortcut)

// Blind request: a not-yet-indexed file can't be picked from the Select2 list
// (it only serves indexed files), so swap the picker for a free-text input.
function toggleBlindSearch() {
  const on        = document.getElementById('fsBlindToggle')?.checked;
  const manual    = document.getElementById('fileSearchManual');
  const container = $('#fileSearchSelect').next('.select2-container');
  const btn       = document.getElementById('fileSearchBtn');
  if (on) {
    container.hide();
    if (manual) { manual.style.display = ''; manual.focus(); }
    // Blind mode → the search button becomes a red "send" (the request goes to SCB).
    if (btn) { btn.innerHTML = '<i class="fas fa-paper-plane"></i>'; btn.style.background = '#ef4444'; btn.style.borderColor = '#ef4444'; }
  } else {
    container.show();
    if (manual) { manual.style.display = 'none'; manual.value = ''; }
    if (btn) { btn.innerHTML = '<i class="fas fa-search"></i>'; btn.style.background = ''; btn.style.borderColor = ''; }
    clearTimeout(blindAutoLoadTimer);
  }
}

// Blind mode: auto-load the result once the user finishes typing the file number,
// so they don't have to click the button — same outcome as pressing search.
let blindAutoLoadTimer = null;
function onBlindManualInput() {
  if (!document.getElementById('fsBlindToggle')?.checked) return;
  const manual = document.getElementById('fileSearchManual');
  clearTimeout(blindAutoLoadTimer);
  if (!manual || !manual.value.trim()) return;
  blindAutoLoadTimer = setTimeout(() => searchFile(), 700);
}

async function searchFile() {
  const sel    = document.getElementById('fileSearchSelect');
  const manual = document.getElementById('fileSearchManual');
  const blind  = document.getElementById('fsBlindToggle')?.checked;
  const result = document.getElementById('fileSearchResult');
  const val    = blind ? (manual?.value || '').trim().toUpperCase() : (sel.value || '').trim();
  if (!val) { result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:4px;">Please ${blind ? 'type' : 'select'} a file number.</p>`; return; }

  const btn = document.getElementById('fileSearchBtn');
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  result.innerHTML = '';

  try {
    const res = await api(`${MOB_BASE}/files/search?q=${encodeURIComponent(val)}`);
    if (res && res.success && res.data) {
      const d = res.data;
      const meta = LOC_STATUS_META[d.status] || { label:d.status, color:'#6b7185', icon:'fa-file' };
      // Detail rows mirror the web Quick Search (/create-file-tracker/quick-search):
      // an in-transit file is physically held by a Receiving Officer in their
      // department — show the holding Department as the location and label the
      // holder explicitly. Other statuses keep the expected archive/pool location.
      let detailRows;
      if (d.status === 'IN_TRANSIT') {
        let dept = String(d.receiving_department || '').trim();
        if (dept && !/department$/i.test(dept)) dept = dept + ' Department';
        detailRows = [
          ['Registry', d.registry],
          // Always show the home shelf label; files without one read "—".
          ['Shelf/Rack', d.rack_shelf || '—'],
          ['Receiving Officer (holder)', d.receiving_officer_name],
          ['Department', dept || d.current_location],
          // In-transit timeline — only populated for IN_TRANSIT files.
          ['Date Requested', d.date_requested],
          ['Date Collected', d.date_collected],
          // Duration follows the request/collection dates it is derived from.
          ['Duration with holder', d.duration_with_holder],
        ];
      } else {
        detailRows = [
          ['Registry', d.registry],
          ['Shelf/Rack', d.rack_shelf || '—'],
          ['Current Location (Expected)', d.current_location],
          ['Receiving Officer', d.receiving_officer_name],
        ];
      }
      const rowsHtml = detailRows.filter(r=>r[1]).map(r=>`<div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;border-bottom:1px solid var(--border);"><span style="font-size:11px;color:var(--muted);">${esc(r[0])}</span><span style="font-size:13px;font-weight:600;text-align:right;">${esc(r[1])}</span></div>`).join('');

      // OFS (ranked officer): raise a prioritised File/Blind Request to the SCB
      // Monitor straight from the locator. Hidden for SCB Monitors (they receive,
      // not raise). Only when the file's status allows a request (can_send_fr).
      lastFileSearch = d;

      // Any located outcome (Archive, Pool Office, Refer-to-Registry, Pending/blind,
      // incl. their Found / Not-Found variants) warrants a physical check — some files
      // were missing from the start of the digitization exercise. The only case it does
      // NOT apply to is a file actively in transit (already being tracked/redirected).
      const physicalNote = (d.status && !/^IN_TRANSIT/.test(d.status) && d.status !== 'MISSING_FILE')
        ? `<p style="margin-top:8px;font-size:13px;font-style:italic;color:#ef4444;line-height:1.6;">The SCBs would have to do a Physical Check to ascertain the availability of the File as some files were missing even from the beginning of the Digitization Exercise</p>`
        : '';

      // A file under DCIV investigation (own registry = DCIV Registry, or flagged
      // dciv_status=1) is re-directed to the DCIV Director rather than sent to the
      // SCB. Derived from the server-computed next_action label (see
      // FileLocationResolver::actionMetaFor()) instead of re-deriving the rule here.
      const isDciv = /DCIV Director/.test(d.next_action || '');

      // OFS send block is split into two parts so the File Digital Library can sit
      // between the Registry (Origin) selector and the Send button.
      let ofsRegistry = '', ofsButton = '';
      if (IS_OFS && d.can_send_fr && !isDciv) {
        const isMissing = d.is_missing_file;
        const label = isMissing
          ? (d.is_blind ? 'Send Blind Request to the Original Registry' : 'Send File Search Request to the Original Registry')
          : (d.next_action || (d.is_blind ? 'Send Blind Request to SCB Monitor' : 'Send File Search Request to SCB Monitor'));
        // Button colour matches the status label (e.g. In Pool Office = sky, In Archive = green).
        const grad  = `linear-gradient(135deg, ${meta.color}, ${shade(meta.color, 0.18)})`;
        // Origin Registry selector (mirrors Create File Tracker). The chosen registry's
        // short code is carried via the option's data attribute and shown live below.
        const regOpts = REGISTRIES.map(r =>
          `<option value="${esc(r.name)}" data-code="${esc(r.registry_code || '')}"${d.origin_registry && r.name === d.origin_registry ? ' selected' : ''}>${esc(r.name)}</option>`
        ).join('');
        // Requester cascade options (Department → Office → Officer; mirrors Quick Search).
        const fieldSelCss = 'width:100%;height:42px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:12px;padding:0 12px;font-size:13px;color:var(--text);';
        const fieldInpCss = 'width:100%;height:42px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:12px;padding:0 12px;font-size:13px;color:var(--text);margin-top:8px;';
        const fieldLblCss = 'display:block;font-size:11px;font-weight:700;color:var(--muted);margin-bottom:5px;';
        const deptOpts = REQ_DEPARTMENTS.map(dn => `<option value="${esc(dn)}">${esc(dn)}</option>`).join('');
        ofsRegistry = `
          <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
            <div>
              <label style="${fieldLblCss}"><i class="fas fa-building-columns" style="margin-right:5px;color:var(--primary);"></i>Registry (Origin) <span style="color:#ef4444;">*</span></label>
              <div style="display:flex;gap:8px;align-items:center;">
                <select id="fsRegistry" onchange="onFsRegistryChange()" style="flex:1;height:42px;background:var(--surface-2);border:1px solid var(--border-strong);border-radius:12px;padding:0 12px;font-size:13px;color:var(--text);">
                  <option value="">Select Registry (Origin)</option>
                  ${regOpts}
                </select>
                <span id="fsRegistryCode" style="flex-shrink:0;min-width:54px;text-align:center;font-size:12px;font-weight:800;color:var(--primary);background:var(--primary-soft);border:1px solid var(--primary);border-radius:10px;padding:9px 8px;">—</span>
              </div>
            </div>
            <div>
              <label style="${fieldLblCss}"><i class="fas fa-building" style="margin-right:5px;color:var(--primary);"></i>Requester Office (Departments) <span style="color:#ef4444;">*</span></label>
              <select id="fsDept" onchange="onFsDeptChange()" style="${fieldSelCss}">
                <option value="">Select Department</option>
                ${deptOpts}
                <option value="${FS_DEPT_OTHER}">Other…</option>
              </select>
              <input id="fsDeptOther" type="text" placeholder="Specify department *" style="${fieldInpCss}display:none;">
            </div>
          </div>
          <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
            <div>
              <label style="${fieldLblCss}"><i class="fas fa-briefcase" style="margin-right:5px;color:var(--primary);"></i>Requester Office <span style="color:#ef4444;">*</span></label>
              <select id="fsOffice" onchange="onFsOfficeChange()" disabled style="${fieldSelCss}">
                <option value="">Select Office</option>
              </select>
              <input id="fsOfficeOther" type="text" placeholder="Specify office *" style="${fieldInpCss}display:none;">
            </div>
            <div>
              <label style="${fieldLblCss}"><i class="fas fa-user-tie" style="margin-right:5px;color:var(--primary);"></i>Requester Officer <span style="color:#ef4444;">*</span></label>
              <input id="fsOfficer" type="text" readonly value="${esc(CURRENT_USER.name)}" title="The logged-in officer raising this request" style="${fieldSelCss}opacity:.85;cursor:not-allowed;">
            </div>
          </div>
          <div style="margin-top:12px;display:flex;flex-direction:column;gap:12px;">
            <div>
              <label style="${fieldLblCss}"><i class="fas fa-clipboard-list" style="margin-right:5px;color:var(--primary);"></i>Request Purpose <span style="color:#ef4444;">*</span></label>
              <select id="fsPurpose" onchange="onFsPurposeChange()" style="${fieldSelCss}">
                <option value="">Select the reason this file is being requested</option>
                ${REQUEST_PURPOSES.map(p => `<option value="${p.id}" data-turnaround-days="${p.turnaround_days}">${esc(p.name)}</option>`).join('')}
                <option value="in_transit">In-Transit</option>
                <option value="other">Other</option>
              </select>
              <input id="fsPurposeOther" type="text" placeholder="Specify the reason this file is being requested *" style="${fieldInpCss}display:none;">
            </div>
            <div id="fsTimelineDaysWrap">
              <label style="${fieldLblCss}"><i class="fas fa-hourglass-half" style="margin-right:5px;color:var(--primary);"></i>Timeline (Days)</label>
              <input id="fsTimelineDays" type="number" min="0" max="365" placeholder="e.g. 5" oninput="onFsTimelineDaysInput()" style="${fieldSelCss}">
            </div>
          </div>`;
        // Send button is disabled until an origin Registry is chosen.
        ofsButton = `<button id="fsSendBtn" class="btn" disabled style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:${grad};opacity:.5;cursor:not-allowed;" onclick="sendFrFromSearch(this)"><i class="fas fa-paper-plane"></i> ${label}</button>`;
      }

      // In-transit files: re-direct the request straight to the office currently holding
      // the file (its last receiving officer) instead of routing it to the SCB Monitor.
      let redirectSend = '';
      if (IS_OFS && d.can_redirect) {
        const office = d.receiving_officer_name || d.current_location || 'Current Office';
        const grad   = 'linear-gradient(135deg,#f59e0b,#d97706)';
        // Button names the receiving officer when available, otherwise the current
        // file location. The line below still shows the officer + department if set.
        // Sub-line note below the button ("Receiving Officer: … currently holding
        // the file.") — hidden for now (mirrors the web Quick Search, which also
        // hides its equivalent redirectSubline).
        // const officer = d.receiving_officer_name || '';
        // let dept = d.receiving_department || '';
        // if (dept && !/department$/i.test(dept.trim())) dept = dept.trim() + ' Department';
        // let subline = '';
        // if (officer) {
        //   const deptPart = (dept && esc(dept) !== esc(officer)) ? ` (${esc(dept)})` : '';
        //   subline = `<p style="margin-top:6px;font-size:12px;color:var(--muted);text-align:center;">Receiving Officer: <strong>${esc(officer)}</strong>${deptPart} &middot; currently holding the file.</p>`;
        // } else if (office) {
        //   subline = `<p style="margin-top:6px;font-size:12px;color:var(--muted);text-align:center;">This request will be sent to <strong>${esc(office)}</strong>, which currently holds the file.</p>`;
        // }
        const subline = '';
        redirectSend = `<button class="btn" style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:${grad};" onclick="redirectFromSearch(this)"><i class="fas fa-user-tag"></i> Re-direct Request to ${esc(office)}</button>` + subline;
      }

      // A file under DCIV investigation is not blind-searched by the SCB — it is
      // re-directed to the DCIV Director to resolve.
      let dcivRedirectSend = '';
      if (IS_OFS && d.can_send_fr && isDciv) {
        const grad = 'linear-gradient(to right, #0b3d2e, #065f46, #0b3d2e)';
        dcivRedirectSend = `<button class="btn" style="width:100%;margin-top:12px;padding:12px;font-size:13px;box-shadow:none;background:${grad};" onclick="redirectDcivFromSearch(this)"><i class="fas fa-shield-halved"></i> Re-direct To DCIV Director (Land Department)</button>`;
      }

      const inTransitLookupId = String(d.file_number || d.file_title || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
      const _tlOriginRegistry = String(d.origin_registry || d.registry || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
      const _tlRackShelf = String(d.rack_shelf || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
      // Movement timeline now opens directly from the section header, matching
      // the existing collapsible behavior used for holders and bill balance.
      // Shown for both IN_TRANSIT and IN_ARCHIVE files.
      const movementDetails = /^IN_TRANSIT|^IN_ARCHIVE/.test(d.status) ? `
        <details id="movementTimelineDetails" style="margin-bottom:12px;border:1px solid var(--border);border-radius:10px;" ontoggle="toggleMovementTimeline('${inTransitLookupId}', this, '${_tlOriginRegistry}', '${_tlRackShelf}')">
          <summary style="cursor:pointer;list-style:none;padding:9px 12px;font-size:12px;font-weight:700;color:var(--text);">
            <i class="fas fa-stream" style="margin-right:6px;color:var(--primary);"></i>View Movement timeline
          </summary>
          <div style="padding:8px 12px;">
            <div id="fileMovementTimeline"></div>
          </div>
        </details>` : '';

      // Mobile File Search is a read-only locator. But if this file has an OPEN
      // File Request waiting on this SCB Monitor, offer a Found/Not-Found shortcut.
      let frShortcut = '';
      if (IS_SCB_MONITOR) {
        const norm = s => String(s || '').trim().toUpperCase();
        const fr = (await getOpenFileRequests()).find(r => norm(r.file_number) === norm(d.file_number));
        if (fr) {
          frShortcut = `
            <div style="margin-top:12px;background:var(--primary-soft);border-radius:12px;padding:10px 12px;" data-fr="${fr.id}">
              <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--primary);">Open File Request · ${esc(fr.request_no)}</div>
              <div style="display:flex;gap:8px;margin-top:10px;align-items:stretch;">
                <button class="btn" style="flex:1;width:auto;padding:11px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);" onclick="respondFrFromSearch(${fr.id}, 'found', this, ${fr.is_blind ? 'true' : 'false'})"><i class="fas fa-check"></i> Found</button>
                <button class="btn ghost-btn" style="flex:1;width:auto;padding:11px;font-size:13px;box-shadow:none;" onclick="respondFrFromSearch(${fr.id}, 'not_found', this)"><i class="fas fa-xmark"></i> Not&nbsp;Found</button>
              </div>
            </div>`;
        }
      }

      // Holders + latest Deeds Bill Balance — collapsed by default, sits right under
      // the file-number header. Always shown; the bill section falls back to "—".
      let holderBill = '';
      {
        // Every row is always rendered; a missing value falls back to "—" throughout.
        const DASH = '<span style="color:var(--muted);">—</span>';
        const has  = v => !(v === null || v === undefined || v === '');
        const dRow = (l, v) => `<div style="display:flex;justify-content:space-between;gap:12px;padding:6px 0;border-bottom:1px solid var(--border);"><span style="font-size:11px;color:var(--muted);">${esc(l)}</span><span style="font-size:12px;font-weight:600;text-align:right;">${has(v) ? esc(v) : DASH}</span></div>`;
        const dMoney = (l, v) => dRow(l, has(v) ? '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : null);
        const bb   = d.bill_balance || {};
        const fees = bb.fees || {};
        // A bold money row used for the Total / Balance Due summary lines.
        const dTotal = (l, v) => `<div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;"><span style="font-size:12px;font-weight:800;">${esc(l)}</span><span style="font-size:13px;font-weight:800;text-align:right;">${has(v) ? '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : DASH}</span></div>`;
        // Fee labels mirror the "Generate Bill Balance" wizard (Fees & Charges step).
        const FEE_KEYS = ['Registration Fees', 'Survey Fees', 'Preparation Fees', 'Compensation Fees', 'Development Charges'];
        // Indexing bills (Bill Balance + Ground Rent amounts captured during File Indexing).
        const ib = d.indexing_bills || {};
        const indexingBillRows = `${dMoney('Bill Balance', ib.bill_balance)}${dMoney('Ground Rent', ib.grant_rent)}`;
        const billHtml = `
          <details style="margin-top:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;">
            <summary style="cursor:pointer;list-style:none;padding:8px 10px;font-size:11px;font-weight:800;color:var(--text);"><i class="fas fa-file-invoice-dollar" style="margin-right:6px;color:var(--primary);"></i>Bill Balance Details</summary>
            <div style="padding:0 10px 8px;">
              ${dTotal('Balance', bb.balance_due)}
              ${indexingBillRows}
            </div>
          </details>`;
        // Ownership history — a vertical timeline of holders derived from the
        // cross-table property timeline. Falls back to the two flat indexing
        // rows when the file has no transaction chain.
        const holderHistoryHtml = (() => {
          let hist = Array.isArray(d.holder_history) ? d.holder_history : [];
          // Hide Mortgage and Surrender And Release nodes from the holders list.
          hist = hist.filter((h) => {
            const t = String(h.transaction_type || '').toLowerCase();
            return !t.includes('mortgage') && !t.includes('surrender');
          });
          if (!hist.length) {
            const origVal = has(d.original_holder) ? d.original_holder : d.current_holder;
            const currVal = has(d.current_holder) ? d.current_holder : d.original_holder;
            return dRow('Original Holder', origVal) + dRow('Current Holder', currVal);
          }
          // Shorten a transaction type to its instrument label (R of O, C of O,
          // Assignment, Mortgage, …) for the compact ownership list.
          const abbrevType = (t) => {
            if (!t) return '';
            const s = String(t).toLowerCase();
            if (s.includes('right of occupancy')) return 'RofO';
            if (s.includes('certificate of occupancy')) return 'CofO';
            return String(t).replace(/^deed of\s+/i, '').trim();
          };

          // Render a single holder node: recipient name + role (Original/Current
          // Holder) and the instrument in brackets. `last` controls the rail.
          const buildNode = (h, isFirst, isLast) => {
            const dot = isFirst ? '#059669' : (isLast ? 'var(--primary)' : 'var(--surface-3)');
            const dotIcon = (isFirst || isLast) ? '#fff' : 'var(--muted)';
            const line = !isLast ? `<span style="position:absolute;left:6px;top:17px;bottom:-2px;width:2px;background:var(--border);"></span>` : '';
            const name = h.to || h.holder;
            const roleLabel = isFirst ? 'Original Holder' : (isLast ? 'Current Holder' : 'Holder');
            const roleColor = isFirst ? '#059669' : (isLast ? 'var(--primary)' : 'var(--muted)');
            const type = abbrevType(h.transaction_type);
            return `
              <div style="position:relative;padding-left:24px;padding-bottom:${isLast ? '0' : '7px'};">
                ${line}
                <span style="position:absolute;left:0;top:1px;width:15px;height:15px;border-radius:50%;background:${dot};border:2px solid var(--border);display:flex;align-items:center;justify-content:center;">
                  <i class="fas fa-user" style="font-size:7px;color:${dotIcon};"></i>
                </span>
                <div style="font-size:13px;font-weight:700;color:var(--text);line-height:1.3;">${esc(name)}</div>
                <div style="font-size:11px;font-weight:600;color:${roleColor};margin-top:2px;">${roleLabel}${type ? ` <span style="color:var(--muted);font-weight:500;">(${esc(type)})</span>` : ''}</div>
              </div>`;
          };

          // A single transaction (e.g. only a CofO grant with no later transfer)
          // means the same person is both the original AND the current holder.
          // Render that holder twice — as Original and as Current — mirroring the
          // flat two-row fallback above, so a Current Holder always shows.
          const nodes = hist.length === 1
            ? buildNode(hist[0], true, false) + buildNode(hist[0], false, true)
            : hist.map((h, i) => buildNode(h, i === 0, i === hist.length - 1)).join('');
          return `
            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin:2px 0 10px;">Ownership</div>
            <div style="padding:0 2px 2px;">${nodes}</div>`;
        })();
        holderBill = `
          <details style="margin-bottom:12px;border:1px solid var(--border);border-radius:10px;">
            <summary style="cursor:pointer;list-style:none;padding:9px 12px;font-size:12px;font-weight:700;color:var(--text);"><i class="fas fa-user-tag" style="margin-right:6px;color:var(--primary);"></i>Holders &amp; Bill Balance Details</summary>
            <div style="padding:2px 12px 10px;">
              ${holderHistoryHtml}
              ${billHtml}
            </div>
          </details>`;
      }

      // When the file's origin registry is known, the status pill becomes
      // "In {Registry}" tinted with that registry's colour (KANGIS / SLTR / ST /
      // Cadastral); otherwise it shows the resolved location status.
      // Exception: an IN_TRANSIT (logged-out) file is NOT in its registry — it is
      // out at another office — so it always shows the "In Transit" status pill,
      // never "In {Registry}" (which would wrongly imply it sits in the registry).
      const badge = (d.origin_registry && !/^IN_TRANSIT/.test(d.status || '') && d.status !== 'MISSING_FILE')
        ? { label: 'In ' + d.origin_registry, color: REGISTRY_THEME[d.origin_registry] || meta.color, icon: 'fa-folder-open' }
        : meta;

      result.innerHTML = `
        <div class="result-card">
          <div style="background:var(--surface-2);padding:14px 16px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div>
              <div style="font-size:15px;font-weight:800;">${esc(d.file_number)}${d.linked_file_number ? ` <span style="font-size:12px;font-weight:700;color:var(--muted);">(${esc(d.linked_file_number)})</span>` : ''}</div>
              <div style="font-size:12px;font-weight:600;color:var(--text);opacity:.82;margin-top:3px;">${esc(d.file_title||'—')}</div>
            </div>
            <span style="white-space:nowrap;font-size:11px;font-weight:700;color:${badge.color};background:${badge.color}1a;border:1px solid ${badge.color}55;padding:5px 10px;border-radius:30px;"><i class="fas ${badge.icon}" style="margin-right:4px;"></i>${esc(badge.label)}</span>
          </div>
          <div style="padding:12px 16px;">
            ${Number(d.dciv_status) === 1 ? `
            <div style="margin-bottom:12px;background:#fff1f2;border:1px solid #fecdd3;border-radius:12px;padding:10px 12px;">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:800;color:#be123c;"><i class="fas fa-triangle-exclamation" style="margin-right:5px;"></i>Under Investigation</span>
                ${d.dciv_fileno ? `<span style="font-size:11px;font-weight:700;color:#be123c;background:#ffe4e6;border:1px solid #fecdd3;padding:3px 9px;border-radius:30px;white-space:nowrap;">${esc(d.dciv_fileno)}</span>` : ''}
              </div>
              ${d.dciv_reason ? `<div style="font-size:11px;color:#9f1239;margin-top:6px;line-height:1.5;">${esc(d.dciv_reason)}</div>` : ''}
            </div>` : ''}
            ${(() => {
              const rf = d.dciv_related_files || [];
              if (!rf.length) return '';
              const item = f => `
                <div style="font-size:11px;color:#065f46;">
                  <span style="font-weight:700;background:#d1fae5;border:1px solid #a7f3d0;padding:2px 8px;border-radius:30px;white-space:nowrap;">${esc(f.related_file_number)}</span>
                  ${f.related_file_title ? ` — ${esc(f.related_file_title)}` : ''}
                </div>`;
              const list = `<div style="margin-top:6px;display:flex;flex-direction:column;gap:4px;">${rf.map(item).join('')}</div>`;
              return rf.length > 1
                ? `<details style="margin-bottom:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;">
                    <summary style="cursor:pointer;list-style:none;font-size:13px;font-weight:800;color:#047857;"><i class="fas fa-link" style="margin-right:5px;"></i>Linked Related Files (${rf.length})</summary>
                    ${list}
                  </details>`
                : `<div style="margin-bottom:12px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;padding:10px 12px;">
                    <span style="font-size:13px;font-weight:800;color:#047857;"><i class="fas fa-link" style="margin-right:5px;"></i>Linked Related Files (${rf.length})</span>
                    ${list}
                  </div>`;
            })()}
            ${(d.status === 'MISSING_FILE' && d.is_indexed) ? `
            <div style="margin-bottom:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;padding:10px 12px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <i class="fas fa-circle-info" style="color:#2563eb;"></i>
                <span style="font-size:13px;font-weight:700;color:#1e40af;">This file was indexed and returned to the Original Registry before archiving.</span>
              </div>
              ${(d.registry || d.rack_shelf) ? `
              <div style="margin-top:6px;padding-left:22px;display:flex;flex-wrap:wrap;gap:12px;font-size:11px;color:#1e40af;">
                ${d.registry ? `<span><strong>Registry:</strong> ${esc(d.registry)}</span>` : ''}
                ${d.rack_shelf ? `<span><strong>Shelf/Rack:</strong> ${esc(d.rack_shelf)}</span>` : ''}
              </div>` : ''}
            </div>` : ''}
            ${d.duplicate_flag ? `
            <div style="margin-bottom:12px;background:${d.duplicate_flag.color}14;border:1px solid ${d.duplicate_flag.color}55;border-radius:12px;padding:10px 12px;">
              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:800;color:${d.duplicate_flag.color};"><i class="fas fa-copy" style="margin-right:5px;"></i>${esc(d.duplicate_flag.label)}</span>
              </div>
              ${(d.duplicate_flag.entries && d.duplicate_flag.entries.length > 1) ? `
              <div style="margin-top:6px;padding-left:22px;display:flex;flex-direction:column;gap:2px;">
                ${d.duplicate_flag.entries.map(e => `
                <div style="font-size:11px;"><span style="font-weight:700;color:${d.duplicate_flag.color};">${esc(e.file_number)}</span> <span style="color:var(--muted);">${esc(e.file_title || '—')}</span></div>`).join('')}
              </div>` : (d.duplicate_flag.file_title ? `
              <div style="margin-top:4px;padding-left:22px;font-size:11px;color:var(--muted);">${esc(d.duplicate_flag.file_title)}</div>` : '')}
            </div>` : ''}
            ${holderBill}
            ${movementDetails}
            ${rowsHtml}
            ${ofsRegistry}
            <div id="fsDigital" style="margin-top:12px;"></div>
            ${ofsButton}
            ${redirectSend}
            ${dcivRedirectSend}
            ${physicalNote}
            ${frShortcut}
            
          </div>
        </div>`;
      // When the file's origin registry was auto-detected and the Send form is shown,
      // sync the pre-selected Registry (Origin): set its code, theme its colour, enable
      // Send and load the digital copy from that registry. Otherwise load the default lib.
      if (d.origin_registry && document.getElementById('fsRegistry')) {
        onFsRegistryChange();
      } else {
        loadFsDigital(d.file_number);
      }
    } else {
      result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;"><i class="fas fa-times-circle"></i> Could not resolve "<strong>${esc(val)}</strong>".</p>`;
    }
  } catch(e) {
    result.innerHTML = `<p style="color:#ef4444;font-size:12px;margin-top:6px;">Error: ${esc(e.message)}</p>`;
  }

  btn.disabled = false; btn.innerHTML = blind ? '<i class="fas fa-paper-plane"></i>' : '<i class="fas fa-search"></i>';
}

async function toggleMovementTimeline(fileNumber, detailsEl, originRegistry, rackShelf) {
  const container = document.getElementById('fileMovementTimeline');
  if (!container) return;
  if (!detailsEl) return;

  if (!detailsEl.open) {
    return;
  }

  if (container.dataset.loaded === '1') return;

  container.innerHTML = `<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:var(--muted);">Loading movement history for <strong>${esc(fileNumber)}</strong>…</div>`;

  // Default "In Archive" home row — mirrors the permanent registry/archive location
  // shown at the top of the movement log in the desktop Create File Tracker view.
  // Always rendered (with fallback label), matching js.blade.php behaviour.
  const inArchiveHomeRow = `
    <div style="position:relative;padding:0 0 14px 26px;margin-left:2px;">
      <span style="position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--border);"></span>
      <span style="position:absolute;left:2px;top:6px;width:14px;height:14px;border-radius:50%;background:#10b981;border:3px solid var(--surface);box-shadow:0 0 0 1px rgba(16,185,129,.3);"></span>
      <div style="padding:0 4px 0 0;">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
          <div style="min-width:0;flex:1 1 65%;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">Archive / Registry</div>
            <div style="font-size:13px;color:var(--text);font-weight:700;line-height:1.35;">
              <i class="fas fa-box-archive" style="margin-right:5px;color:#10b981;"></i>${esc(originRegistry || 'Registry / Archive')}${rackShelf ? ` — Shelf/Rack ${esc(rackShelf)}` : ''}
            </div>
          </div>
          <div style="min-width:0;flex:1 1 30%;text-align:right;">
            <span style="display:inline-block;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;background:#d1fae5;color:#166534;border:1px solid #a7f3d0;">In Archive</span>
          </div>
        </div>
      </div>
    </div>`;

  try {
    const res = await api(`${API_BASE}/track/${encodeURIComponent(fileNumber)}`);
    // "not found" just means the file has never been tracked — that is an empty
    // history, not a failure.
    const neverTracked = res && res.success === false && !res._authError && /not found/i.test(String(res.message || ''));
    if (neverTracked) {
      container.innerHTML = `${inArchiveHomeRow}<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:var(--muted);">No movement history available for this file.</div>`;
    } else if (!res || !res.success || !res.data) {
      container.innerHTML = `${inArchiveHomeRow}<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:#ef4444;">Could not load movement history.</div>`;
    } else {
      const currentLogs = Array.isArray(res.data.movement_history) ? res.data.movement_history : [];
      const priorLogs = Array.isArray(res.data.prior_movements) ? res.data.prior_movements : [];
      const allLogs = [...priorLogs, ...currentLogs].sort(compareMovementEntries);
      if (!allLogs.length) {
        container.innerHTML = `${inArchiveHomeRow}<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:var(--muted);">No movement history available for this file.</div>`;
      } else {
        const approvalPurposes = ['recommendation', 'approval'];
        const movementLogs = allLogs.filter(entry => !approvalPurposes.includes(String(entry.purpose || '').toLowerCase()));
        const approvalLogs = allLogs.filter(entry => approvalPurposes.includes(String(entry.purpose || '').toLowerCase()));

        // Request Purpose / Timeline / Expected Return Date belong to the current
        // tracker (one per tracking cycle), not the individual movement entry —
        // applied to every row here, mirroring the desktop File Log Table.
        const trackerMeta = {
          requestPurposeName: res.data.request_purpose_name || '',
          timelineStatus: res.data.timeline_status || null,
          daysUntilDeadline: (res.data.days_until_deadline === null || res.data.days_until_deadline === undefined) ? null : Number(res.data.days_until_deadline),
          expectedReturnDate: res.data.deadline || null,
        };

        container.innerHTML = `
          <div style="margin-top:12px;font-size:13px;font-weight:700;color:var(--text);">Movement timeline</div>
          ${priorLogs.length ? `<div style="margin-top:8px;padding:10px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:12px;color:var(--muted);">Tracking cycles for this file.</div>` : ''}
          <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
            ${inArchiveHomeRow}
            ${movementLogs.length ? movementLogs.map((entry) => renderMovementRow(entry, trackerMeta)).join('') : `<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:var(--muted);">No physical movement entries found. Approval steps may still be present below.</div>`}
          </div>
          ${approvalLogs.length ? `
            <div style="margin-top:16px;font-size:13px;font-weight:700;color:var(--text);">Workflow approvals</div>
            <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
              ${approvalLogs.map((entry) => renderApprovalRow(entry)).join('')}
            </div>
          ` : ''}`;
        container.dataset.loaded = '1';
      }
    }
  } catch (e) {
    container.innerHTML = `<div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-2);font-size:13px;color:#ef4444;">Error loading movement history: ${esc(e.message)}</div>`;
  }
}

function compareMovementEntries(a, b) {
  return movementEntryTimestamp(a) - movementEntryTimestamp(b);
}

function movementEntryTimestamp(entry) {
  const parseDateTime = (date, time) => {
    const d = (date || '').toString().trim();
    if (!d) return null;
    const t = (time || '').toString().trim() || '00:00';
    const parsed = Date.parse(`${d} ${t}`);
    return Number.isNaN(parsed) ? null : parsed;
  };

  const inTs = parseDateTime(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
  if (inTs !== null) return inTs;

  const outTs = parseDateTime(entry.log_out_date || entry.logOutDate, entry.log_out_time || entry.logOutTime);
  if (outTs !== null) return outTs;

  const createdAt = entry.created_at || entry.createdAt || '';
  const createdTs = Date.parse(createdAt);
  return Number.isNaN(createdTs) ? Number.POSITIVE_INFINITY : createdTs;
}

function toAmPm(timeStr) {
  if (!timeStr) return '';
  const parts = timeStr.toString().trim().split(':');
  if (parts.length < 2) return timeStr;
  let h = parseInt(parts[0], 10);
  const m = parts[1].padStart(2, '0');
  if (isNaN(h)) return timeStr;
  const period = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${m} ${period}`;
}

function formatMovementDate(date, time) {
  const d = (date || '').toString().trim();
  if (!d) return '—';
  if (!time) return esc(d);
  // Prefer AM/PM formatting for the time portion to match web view
  const pretty = toAmPm(time);
  return `${esc(d)} ${esc(pretty)}`;
}

function resolveMovementStatus(entry) {
  const rawOverride = (entry.status_label || entry.statusLabel || entry.new_status || entry.newStatus || '').toString().trim();
  const rawStatus = (entry.status || '').toString().trim().toLowerCase();
  const normalize = (value) => value.toLowerCase().replace(/_/g, ' ');

  if (rawOverride) {
    const normalized = normalize(rawOverride);
    switch (normalized) {
      case 'log-in':
      case 'log in':
        return { label: 'Log-in', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
      case 'log-out':
      case 'log out':
        return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
      case 'pending acceptance':
      case 'in-transit':
      case 'in transit':
        return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
      case 'rejected':
        return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
      case 'cancelled':
      case 'canceled':
        return { label: 'Cancelled', style: 'background:#f3f4f6;color:#4b5563;border:1px solid #d1d5db;' };
      default:
        return { label: rawOverride, style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
    }
  }

  switch (rawStatus) {
    case 'pending_acceptance':
      return { label: 'In-Transit', style: 'background:#fef9c3;color:#78350f;border:1px solid #fde68a;' };
    case 'active':
      return { label: 'Log-out', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
    case 'completed':
      return { label: 'Log-in', style: 'background:#d1fae5;color:#166534;border:1px solid #a7f3d0;' };
    case 'rejected':
      return { label: 'Rejected', style: 'background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;' };
    default:
      return { label: String(entry.status || 'Completed').replace(/_/g, ' '), style: 'background:#e0e7ff;color:#3730a3;border:1px solid #c7d2fe;' };
  }
}

// Green/Amber/Red timeline label + colour for a tracker — mirrors
// FileTracker::getTimelineStatusAttribute() / the desktop day-count badge.
function formatTimelineMeta(meta) {
  const byStatus = {
    green:   { color: '#166534', bg: '#d1fae5', border: '#a7f3d0' },
    amber:   { color: '#78350f', bg: '#fef9c3', border: '#fde68a' },
    red:     { color: '#b91c1c', bg: '#fee2e2', border: '#fecaca' },
    // The clock has not started: the file is still being searched for / not yet
    // logged out, so there is nothing to count down from.
    pending: { color: '#475569', bg: '#e2e8f0', border: '#cbd5e1' },
  };
  if (!meta || !meta.timelineStatus || !byStatus[meta.timelineStatus]) return null;

  if (meta.timelineStatus === 'pending') {
    return { label: 'Pending', icon: 'fa-clock', ...byStatus.pending };
  }

  const days = meta.daysUntilDeadline;
  let label;
  if (days === null || days === undefined) {
    label = { green: 'On Track', amber: 'Due Soon', red: 'Overdue' }[meta.timelineStatus];
  } else if (days > 0) {
    label = `${days} day${days === 1 ? '' : 's'} left`;
  } else if (days === 0) {
    label = 'Due today';
  } else {
    const abs = Math.abs(days);
    label = `${abs} day${abs === 1 ? '' : 's'} overdue`;
  }
  return { label, ...byStatus[meta.timelineStatus] };
}

function formatExpectedReturnDate(value) {
  if (!value) return '—';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return esc(String(value).slice(0, 10));
  const dd = String(d.getDate()).padStart(2, '0');
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  return `${dd}/${mm}/${d.getFullYear()}`;
}

function renderMovementRow(entry, trackerMeta) {
  const office = esc(entry.office_name || entry.office || entry.receiving_office_name || 'Unknown');
  const officer = esc(entry.receiving_officer_name || entry.receivingOfficerName || entry.accepted_by_name || '-');
  const status = resolveMovementStatus(entry);
  const statusLabel = status.label;
  const hasLogIn = statusLabel === 'Log-in' || statusLabel === 'Completed';
  const inDate = hasLogIn
    ? formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime)
    : '-';
  const outDateRaw = entry.log_out_date || entry.logOutDate;
  const outTimeRaw = entry.log_out_time || entry.logOutTime;
  const outDate = outDateRaw
    ? formatMovementDate(outDateRaw, outTimeRaw)
    : ['active', 'pending_acceptance', 'in-transit', 'in transit'].includes((entry.status || '').toString().trim().toLowerCase())
      ? 'In transit'
      : '-';
  const notes = entry.notes ? `<div style="margin-top:8px;font-size:12px;color:var(--muted);">${esc(entry.notes)}</div>` : '';

  // Request Purpose / Timeline / Expected Return Date (tracker-level, same for
  // every row) and Delay Reason (per-entry) — Request Purpose always precedes
  // Timeline, per the desktop File Log Table column order.
  const timelineMeta = formatTimelineMeta(trackerMeta);
  const requestPurposeName = trackerMeta && trackerMeta.requestPurposeName ? esc(trackerMeta.requestPurposeName) : '—';
  const expectedReturnDate = formatExpectedReturnDate(trackerMeta && trackerMeta.expectedReturnDate);
  const delayReason = entry.delay_reason ? esc(entry.delay_reason) : '—';
  const requestMetaBlock = `
        <div style="margin-top:10px;display:grid;gap:9px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Request Purpose</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${requestPurposeName}</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Timeline</div>
            <div style="font-size:13px;">${timelineMeta
              ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:${timelineMeta.bg};color:${timelineMeta.color};border:1px solid ${timelineMeta.border};">${timelineMeta.icon ? `<i class="fas ${timelineMeta.icon}"></i>` : ''}${esc(timelineMeta.label)}</span>`
              : `<span style="color:var(--muted);">—</span>`}</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Expected Return Date</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${expectedReturnDate}</div>
          </div>
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Delay Reason</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${delayReason}</div>
          </div>
        </div>`;

  return `
    <div style="position:relative;padding:0 0 14px 26px;margin-left:2px;">
      <span style="position:absolute;left:8px;top:0;bottom:0;width:2px;background:var(--border);"></span>
      <span style="position:absolute;left:2px;top:6px;width:14px;height:14px;border-radius:50%;background:var(--primary);border:3px solid var(--surface);box-shadow:0 0 0 1px var(--primary-soft);"></span>
      <div style="padding:0 4px 0 0;">
        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
          <div style="min-width:0;flex:1 1 65%;">
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px;">Office</div>
            <div style="font-size:13px;color:var(--text);font-weight:700;line-height:1.35;">${office}</div>
          </div>
          <div style="min-width:0;flex:1 1 30%;text-align:right;">
            <span style="display:inline-block;padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;${status.style}">${esc(status.label)}</span>
          </div>
        </div>
        <div style="margin-top:10px;display:grid;gap:9px;">
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Receiving Officer</div>
            <div style="font-size:13px;color:var(--text);font-weight:600;">${officer}</div>
          </div>
          <div style="display:grid;gap:7px;">
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Log In</div>
              <div style="font-size:13px;color:var(--text);font-weight:600;">${inDate}</div>
            </div>
            <div>
              <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:3px;">Log Out</div>
              <div style="font-size:13px;color:var(--text);font-weight:600;">${outDate}</div>
            </div>
          </div>
        </div>
        ${requestMetaBlock}
        ${notes}
      </div>
    </div>`;
}

function renderApprovalRow(entry) {
  const office = esc(entry.office_name || entry.office || entry.receiving_office_name || 'Unknown');
  const officer = esc(entry.receiving_officer_name || entry.accepted_by_name || '—');
  const eventDate = formatMovementDate(entry.log_in_date || entry.logInDate, entry.log_in_time || entry.logInTime);
  const status = resolveMovementStatus(entry);
  const purposeLabel = String(entry.purpose || '').toLowerCase() === 'recommendation' ? 'Recommendation' : 'Approval';
  const notes = entry.notes ? `<div style="margin-top:8px;font-size:12px;color:var(--muted);">${esc(entry.notes)}</div>` : '';

  return `
    <div style="padding:12px;border:1px solid var(--border);border-radius:14px;background:var(--surface-3);">
      <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;">
        <div style="min-width:0;flex:1 1 65%;">
          <div style="font-size:12px;font-weight:700;color:var(--text);">${purposeLabel}</div>
          <div style="margin-top:6px;font-size:12px;color:var(--muted);">${eventDate}</div>
        </div>
        <div style="min-width:0;flex:1 1 30%;text-align:right;">
          <span style="padding:6px 10px;border-radius:999px;font-size:11px;font-weight:700;${status.style}">${esc(status.label)}</span>
        </div>
      </div>
      <div style="margin-top:10px;display:grid;gap:10px;font-size:12px;color:var(--text);">
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Office</div>
          <div>${office}</div>
        </div>
        <div>
          <div style="font-size:11px;font-weight:700;color:var(--muted);margin-bottom:4px;">Officer</div>
          <div>${officer}</div>
        </div>
      </div>
      ${notes}
    </div>`;
}

// ─── File Digital Library (cover card + gallery; shared source with DFR) ────
const DIG_DIGITAL_URL  = '{{ route('digital-request.digital-files') }}';
const DIG_REGISTRY_URL = '{{ route('digital-request.registry-files') }}';
const DIG_IMG = ['jpg','jpeg','png','tif','tiff','gif','webp'];
let digFiles = [], digIndex = 0, digStripBuilt = false, digRotation = 0;

// Load the digital copy for a file. When a Registry (Origin) is selected, the
// registry folder source (SLTR / Cadastral / KANGIS / Physical Planning) is
// tried first; otherwise (and as a fallback) the FileIndexing-based library
// (Land registries) is used.
async function loadFsDigital(fileNo, registry = null) {
  const box = document.getElementById('fsDigital');
  if (!box) return;
  box.innerHTML = `<div class="dig-empty"><i class="fas fa-spinner fa-spin"></i> Checking the digital library…</div>`;
  try {
    let files = [];
    if (registry) {
      const r = await api(DIG_REGISTRY_URL, { method: 'POST', body: JSON.stringify({ file_no: fileNo, registry }) });
      if (r && r.available && Array.isArray(r.files)) files = r.files;
    }
    if (!files.length) {
      const d = await api(DIG_DIGITAL_URL, { method: 'POST', body: JSON.stringify({ file_no: fileNo }) });
      files = (d && d.available && Array.isArray(d.files)) ? d.files : [];
    }
    if (files.length) files = await pruneMissing(files);
    digFiles = files;
    renderFsDigital();
  } catch (e) {
    digFiles = [];
    box.innerHTML = `<div class="dig-empty"><i class="fas fa-exclamation-circle"></i> Could not load the digital file.</div>`;
  }
}

// Drop pages whose soft copy is missing — only on a definitive 404 (fail open).
async function pruneMissing(files) {
  const present = new Array(files.length).fill(true);
  let i = 0;
  const worker = async () => {
    while (i < files.length) {
      const idx = i++;
      try { const r = await fetch(files[idx].url, { method: 'HEAD' }); if (r.status === 404) present[idx] = false; }
      catch (e) {}
    }
  };
  await Promise.all(Array.from({ length: Math.min(8, files.length) }, worker));
  return files.filter((_, idx) => present[idx]);
}

const isImgFile = f => DIG_IMG.includes((f.ext || '').toLowerCase());

function renderFsDigital() {
  digStripBuilt = false;
  const box = document.getElementById('fsDigital');
  if (!box) return;
  if (!digFiles.length) {
    box.innerHTML = `<div class="dig-empty"><i class="fas fa-folder-open"></i> No digital copy found in the library for this file.</div>`;
    return;
  }
  const cover = digFiles[0];
  const n = digFiles.length;
  const coverInner = isImgFile(cover)
    ? `<img src="${esc(cover.url)}" alt="cover" loading="lazy" onerror="this.parentNode.innerHTML='<i class=\\'fas fa-images\\'></i>'">`
    : `<i class="fas fa-file-${cover.ext === 'pdf' ? 'pdf' : 'alt'}"></i>`;
  box.innerHTML =
    `<div class="dig-cover" onclick="openDigViewer(0)">
       <div class="dig-cover-stack"><div class="dig-cover-page">${coverInner}</div></div>
       <div class="dig-cover-meta">
         <div class="dig-cover-title"><i class="fas fa-images"></i> File Digital Library</div>
         <div class="dig-cover-sub">${n} page${n > 1 ? 's' : ''} in the digital library</div>
         <div class="dig-cover-cta"><i class="fas fa-expand"></i> Tap to open gallery</div>
       </div>
       <i class="fas fa-chevron-right dig-cover-chev"></i>
     </div>`;
}

function openDigViewer(i) {
  if (!digFiles.length) return;
  digIndex = Math.max(0, Math.min(i, digFiles.length - 1));
  document.getElementById('digViewer').classList.add('open');
  document.body.style.overflow = 'hidden';
  buildDigStrip();
  renderDigViewer();
}
function closeDigViewer() {
  pdfRenderToken++; // cancel any in-flight PDF render
  removeDigPdf();
  document.getElementById('digViewer').classList.remove('open');
  document.getElementById('digStageInner').innerHTML = '';
  document.body.style.overflow = '';
}
function digNext() { if (digIndex < digFiles.length - 1) { digIndex++; renderDigViewer(); } }
function digPrev() { if (digIndex > 0) { digIndex--; renderDigViewer(); } }

function renderDigViewer() {
  const f = digFiles[digIndex];
  if (!f) return;
  pdfRenderToken++;   // cancel any in-flight PDF render
  removeDigPdf();     // drop a PDF overlay left from the previous page
  digRotation = 0;    // each page starts upright
  const isImg = isImgFile(f);
  // Rotate tools apply to images only.
  document.getElementById('digRotateLeft').style.display  = isImg ? '' : 'none';
  document.getElementById('digRotateRight').style.display = isImg ? '' : 'none';
  const stage = document.getElementById('digStageInner');
  if (isImg) {
    stage.innerHTML = `<i class="fas fa-spinner fa-spin dig-spin"></i>`;
    const img = new Image();
    img.alt = f.name;
    img.onload = () => { stage.innerHTML = ''; stage.appendChild(img); applyImgRotation(); };
    img.onerror = () => { stage.innerHTML = `<div style="color:#fff;font-size:13px;">Could not load this page.</div>`; };
    img.src = f.url;
  } else if ((f.ext || '').toLowerCase() === 'pdf') {
    // Mobile/embedded browsers won't render a PDF in an <iframe>, so paint the
    // pages to <canvas> with PDF.js instead. Each page is rendered in order into
    // a scrollable stack overlaying the stage.
    renderPdfToStage(f);
  } else {
    // Other doc types embed in a frame; a fallback link covers browsers that
    // won't render the format inline.
    stage.innerHTML = `<div class="dig-doc">
      <iframe src="${esc(f.url)}" title="${esc(f.name)}"></iframe>
      <a href="${esc(f.url)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt" style="margin-right:5px;"></i>Open file in new tab</a>
    </div>`;
  }
  document.getElementById('digVName').textContent = f.name;
  document.getElementById('digVCount').textContent = `Page ${digIndex + 1} of ${digFiles.length}`;
  document.getElementById('digNavPrev').disabled = digIndex === 0;
  document.getElementById('digNavNext').disabled = digIndex === digFiles.length - 1;
  [digIndex - 1, digIndex + 1].forEach(j => { const nf = digFiles[j]; if (nf && isImgFile(nf)) { const im = new Image(); im.src = nf.url; } });
  const strip = document.getElementById('digStrip');
  strip.querySelectorAll('.dig-strip-item').forEach((el, j) => {
    el.classList.toggle('active', j === digIndex);
    if (j === digIndex) el.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
  });
}

function buildDigStrip() {
  if (digStripBuilt) return;
  const strip = document.getElementById('digStrip');
  strip.innerHTML = digFiles.map((f, i) => {
    const thumb = isImgFile(f)
      ? `<img src="${esc(f.url)}" alt="" loading="lazy" onerror="this.outerHTML='<i class=\\'fas fa-file-image\\'></i>'">`
      : `<i class="fas fa-file-${f.ext === 'pdf' ? 'pdf' : 'alt'}"></i>`;
    return `<div class="dig-strip-item" onclick="digGoto(${i})">${thumb}<span class="dig-strip-n">${i + 1}</span></div>`;
  }).join('');
  digStripBuilt = true;
}
function digGoto(i) { digIndex = Math.max(0, Math.min(i, digFiles.length - 1)); renderDigViewer(); }

// Rotate the current image in 90° steps. When sideways (90°/270°) the natural
// width/height caps are swapped so the rotated image still fits the stage.
function digRotate(dir) {
  digRotation = (((digRotation + (dir < 0 ? -90 : 90)) % 360) + 360) % 360;
  applyImgRotation();
}
function applyImgRotation() {
  const img = document.querySelector('#digStageInner img');
  if (!img) return;
  img.style.transformOrigin = 'center center';
  img.style.transform = `rotate(${digRotation}deg)`;
  if (digRotation % 180 === 0) {
    img.style.maxWidth = '';
    img.style.maxHeight = '';
  } else {
    const stage = document.getElementById('digStage');
    img.style.maxWidth = stage.clientHeight + 'px';
    img.style.maxHeight = stage.clientWidth + 'px';
  }
}

// ── PDF rendering (PDF.js) ──────────────────────────────────────────────────
// Embedded <iframe> PDFs render blank in mobile/in-app browsers, so paint the
// pages to <canvas>. A bumping token cancels a render when the user navigates
// to another page before this PDF finishes.
if (window.pdfjsLib) {
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
}
let pdfRenderToken = 0;
function removeDigPdf() {
  document.querySelectorAll('#digStage .dig-pdf').forEach(el => el.remove());
}
function pdfFallbackWrap(f) {
  const wrap = document.createElement('div');
  wrap.className = 'dig-pdf';
  wrap.innerHTML = `<div class="dig-doc" style="flex:1;">`
    + `<iframe src="${esc(f.url)}" title="${esc(f.name)}"></iframe>`
    + `<a href="${esc(f.url)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt" style="margin-right:5px;"></i>Open PDF in new tab</a></div>`;
  return wrap;
}
// Paint a PDF's pages to <canvas> as an overlay filling the stage. Returning to
// another page (or closing) bumps pdfRenderToken, which cancels this render.
async function renderPdfToStage(f) {
  const token = ++pdfRenderToken;
  const stage = document.getElementById('digStage');
  removeDigPdf();
  document.getElementById('digStageInner').innerHTML = '';
  const overlay = document.createElement('div');
  overlay.className = 'dig-pdf';
  overlay.innerHTML = `<div class="dig-pdf-spin"><i class="fas fa-spinner fa-spin"></i></div>`;
  stage.appendChild(overlay);
  if (!window.pdfjsLib) { overlay.replaceWith(pdfFallbackWrap(f)); return; }
  try {
    const pdf = await pdfjsLib.getDocument({ url: f.url }).promise;
    if (token !== pdfRenderToken) { overlay.remove(); return; }
    const pages = document.createElement('div');
    pages.className = 'dig-pdf-pages';
    const link = document.createElement('a');
    link.className = 'dig-pdf-fallback';
    link.href = f.url; link.target = '_blank'; link.rel = 'noopener';
    link.innerHTML = `<i class="fas fa-external-link-alt" style="margin-right:5px;"></i>Open PDF in new tab`;
    overlay.innerHTML = '';
    overlay.appendChild(pages); overlay.appendChild(link);
    const scale = Math.min(2, (window.devicePixelRatio || 1) * 1.4);
    for (let p = 1; p <= pdf.numPages; p++) {
      if (token !== pdfRenderToken) return;
      const page = await pdf.getPage(p);
      const viewport = page.getViewport({ scale });
      const canvas = document.createElement('canvas');
      canvas.width = viewport.width; canvas.height = viewport.height;
      pages.appendChild(canvas);
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    }
  } catch (e) {
    if (token !== pdfRenderToken) { overlay.remove(); return; }
    overlay.replaceWith(pdfFallbackWrap(f));
  }
}

document.addEventListener('keydown', (e) => {
  if (!document.getElementById('digViewer').classList.contains('open')) return;
  if (e.key === 'Escape') closeDigViewer();
  else if (e.key === 'ArrowRight') digNext();
  else if (e.key === 'ArrowLeft') digPrev();
  else if (e.key === 'r' || e.key === 'R') digRotate(e.shiftKey ? -1 : 1);
});
(function () {
  const stage = document.getElementById('digStage');
  if (!stage) return;
  let x0 = null, y0 = null;
  stage.addEventListener('touchstart', e => { const t = e.changedTouches[0]; x0 = t.clientX; y0 = t.clientY; }, { passive: true });
  stage.addEventListener('touchend', e => {
    if (x0 === null) return;
    const t = e.changedTouches[0];
    const dx = t.clientX - x0, dy = t.clientY - y0;
    if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) { dx < 0 ? digNext() : digPrev(); }
    x0 = y0 = null;
  }, { passive: true });
})();

// OFS: raise a (Blind) File Search Request from the locator. The backend tags it
// as OFS (is_ofs + priority from the user's rank) using the authenticated user.
// Reflect the selected registry's short code next to the dropdown.
function onFsRegistryChange() {
  const sel  = document.getElementById('fsRegistry');
  const code = document.getElementById('fsRegistryCode');
  if (!sel || !code) return;
  const opt = sel.options[sel.selectedIndex];
  code.textContent = (opt && opt.getAttribute('data-code')) ? opt.getAttribute('data-code') : '—';
  // Gate the Send button: only enabled once an origin Registry is selected.
  const btn = document.getElementById('fsSendBtn');
  if (btn) {
    const ok = !!(sel.value || '').trim();
    btn.disabled = !ok;
    btn.style.opacity = ok ? '1' : '.5';
    btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
  }

  // Theme the registry code badge + Send button to the chosen registry's colour
  // (KANGIS / SLTR / ST / Cadastral — same palette as Create File Tracker).
  applyFsRegistryTheme(REGISTRY_THEME[(sel.value || '').trim()] || null);

  // Reload the digital copy from the selected registry's folder (falls back to
  // the FileIndexing library when that registry has no folder for this file).
  if (lastFileSearch && lastFileSearch.file_number) {
    loadFsDigital(lastFileSearch.file_number, (sel.value || '').trim() || null);
  }
}

// Colour the Registry (Origin) code badge, the select border and the Send button
// to a registry's theme colour. Passing null restores the default theme.
function applyFsRegistryTheme(color) {
  const code = document.getElementById('fsRegistryCode');
  const sel  = document.getElementById('fsRegistry');
  const btn  = document.getElementById('fsSendBtn');
  if (code) {
    code.style.color       = color || 'var(--primary)';
    code.style.borderColor = color || 'var(--primary)';
    code.style.background   = color ? color + '1a' : 'var(--primary-soft)';
  }
  if (sel)  sel.style.borderColor = color || 'var(--border-strong)';
  if (btn && color) { btn.style.background = color; btn.style.backgroundImage = 'none'; }
}

// Requester cascade: Department → Office → Officer (mirrors Quick Search).
function onFsDeptChange() {
  const deptSel    = document.getElementById('fsDept');
  const deptOther  = document.getElementById('fsDeptOther');
  const officeSel  = document.getElementById('fsOffice');
  const officeOther= document.getElementById('fsOfficeOther');
  if (!deptSel || !officeSel) return;
  const dept = deptSel.value;
  // "Other" department → reveal a free-text specify input.
  if (deptOther) {
    const isOther = dept === FS_DEPT_OTHER;
    deptOther.style.display = isOther ? 'block' : 'none';
    if (!isOther) deptOther.value = '';
  }
  officeSel.innerHTML = '<option value="">Select Office</option>' +
    REQ_OFFICES.filter(o => o.department === dept)
      .map(o => `<option value="${esc(o.code)}" data-name="${esc(o.name)}">${esc(o.name)}</option>`).join('') +
    `<option value="${FS_OFFICE_OTHER}">Other…</option>`;
  officeSel.disabled = false;
  if (officeOther) { officeOther.style.display = 'none'; officeOther.value = ''; }
}

function onFsOfficeChange() {
  const officeSel  = document.getElementById('fsOffice');
  const officeOther= document.getElementById('fsOfficeOther');
  if (!officeSel) return;
  // "Other" office → reveal a free-text specify input.
  if (officeOther) {
    const isOther = officeSel.value === FS_OFFICE_OTHER;
    officeOther.style.display = isOther ? 'block' : 'none';
    if (!isOther) officeOther.value = '';
  }
}

// Request Purpose → Timeline (Days): pre-fill the days from the selected purpose's
// default turnaround. The Expected Return Date is NOT captured here — the return
// clock starts when the file is logged out on Create File Tracker, not when the
// request is raised, so that date is only set on the Log a File page.
function onFsPurposeChange() {
  const purposeSel = document.getElementById('fsPurpose');
  const purposeOther = document.getElementById('fsPurposeOther');
  const timelineDaysInput = document.getElementById('fsTimelineDays');
  if (!purposeSel) return;
  const isOther = purposeSel.value === 'other';
  if (purposeOther) {
    purposeOther.style.display = isOther ? 'block' : 'none';
    if (!isOther) purposeOther.value = '';
  }

  // "In-Transit" is a purely internal movement (no loan/return expected) —
  // hide Timeline (Days) and clear any stale value instead of just skipping
  // validation.
  const isInTransit = purposeSel.value === 'in_transit';
  const timelineDaysWrap = document.getElementById('fsTimelineDaysWrap');
  if (timelineDaysWrap) timelineDaysWrap.style.display = isInTransit ? 'none' : '';
  if (isInTransit) {
    if (timelineDaysInput) timelineDaysInput.value = '';
    return;
  }

  // No turnaround pre-fill — Timeline (Days) is entered by hand.
}
function onFsTimelineDaysInput() { /* Timeline (Days) is captured as-is; no date derived here. */ }

async function sendFrFromSearch(btn) {
  const d = lastFileSearch;
  if (!d) return;
  // Origin Registry is required before a request can be raised.
  const regSel = document.getElementById('fsRegistry');
  const registry = regSel ? (regSel.value || '').trim() : '';
  const registryCode = regSel ? (regSel.options[regSel.selectedIndex]?.getAttribute('data-code') || '') : '';
  if (regSel && !registry) {
    toast('Please select the origin Registry first.', 'error');
    regSel.focus();
    return;
  }

  // Requester cascade (Department → Office → Officer).
  const deptSel    = document.getElementById('fsDept');
  const officeSel  = document.getElementById('fsOffice');
  const officerSel = document.getElementById('fsOfficer');
  const deptOther  = document.getElementById('fsDeptOther');
  const officeOther= document.getElementById('fsOfficeOther');

  const deptIsOther = deptSel && deptSel.value === FS_DEPT_OTHER;
  const requesterDept = deptIsOther
    ? (deptOther ? deptOther.value.trim() : '')
    : (deptSel ? deptSel.value.trim() : '');

  const officeOpt     = officeSel ? officeSel.options[officeSel.selectedIndex] : null;
  const officeIsOther = officeOpt && officeOpt.value === FS_OFFICE_OTHER;
  const requesterOffice = officeIsOther
    ? (officeOther ? officeOther.value.trim() : '')
    : ((officeOpt && officeOpt.value) ? (officeOpt.getAttribute('data-name') || officeOpt.textContent.trim()) : '');
  const requesterOfficeCode = officeIsOther ? '' : ((officeOpt && officeOpt.value) ? officeOpt.value : '');

  // Requester Officer is the logged-in user raising the request.
  const receivingOfficer = ((officerSel && officerSel.value.trim()) || CURRENT_USER.name || '').trim();

  const purposeSel = document.getElementById('fsPurpose');
  const purposeOther = document.getElementById('fsPurposeOther');
  const rawPurposeValue = purposeSel ? purposeSel.value : '';
  const purposeIsOther = rawPurposeValue === 'other';
  const purposeIsInTransit = rawPurposeValue === 'in_transit';
  const requestPurposeId = (purposeIsOther || purposeIsInTransit) ? '' : rawPurposeValue;
  const requestPurposeOther = purposeIsOther ? (purposeOther ? purposeOther.value.trim() : '') : (purposeIsInTransit ? 'In-Transit' : '');

  if (deptSel && !requesterDept)   { toast('Please select the requester department.', 'error'); (deptIsOther ? deptOther : deptSel).focus(); return; }
  if (officeIsOther && !requesterOffice) { toast('Please specify the requester office.', 'error'); officeOther.focus(); return; }
  if (officeSel && !requesterOffice) { toast('Please select the requester office.', 'error'); officeSel.focus(); return; }
  if (!receivingOfficer) { toast('Could not determine the requester officer (logged-in user).', 'error'); return; }
  if (purposeSel && !rawPurposeValue) { toast('Please select the Request Purpose.', 'error'); purposeSel.focus(); return; }
  if (purposeIsOther && !requestPurposeOther) { toast('Please specify the Request Purpose.', 'error'); purposeOther.focus(); return; }
  // "In-Transit" is a purely internal movement (no loan/return expected), so
  // Timeline (Days) is not required or validated.
  const timelineDaysInput = document.getElementById('fsTimelineDays');
  if (!purposeIsInTransit && timelineDaysInput && timelineDaysInput.value.trim() !== '') {
    const timelineDaysNum = Number(timelineDaysInput.value);
    if (!Number.isInteger(timelineDaysNum) || timelineDaysNum < 0 || timelineDaysNum > 365) {
      toast('Timeline (Days) must be a whole number between 0 and 365.', 'error');
      timelineDaysInput.focus();
      return;
    }
  }
  const original = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  try {
    const res = await api(`{{ route('create-file-tracker.file-request') }}`, {
      method: 'POST',
      body: JSON.stringify({
        file_number:     d.file_number,
        file_title:      d.file_title || null,
        resolved_status: d.status || null,
        current_location: d.current_location || null,
        registry:        registry || null,
        registry_code:   registryCode || null,
        requester_department:  requesterDept || null,
        requester_office:      requesterOffice || null,
        requester_office_code: requesterOfficeCode || null,
        receiving_officer:     receivingOfficer || null,
        request_purpose_id:    requestPurposeId || null,
        request_purpose_other: requestPurposeOther || null,
      }),
    });
    if (res.success) {
      const isMissing = d.is_missing_file;
      const msg = isMissing
        ? `Request ${res.data?.request_no || ''} saved — file is in the missing list`
        : `${d.is_blind ? 'Blind ' : ''}Request ${res.data?.request_no || ''} sent to SCB`;
      toast(msg);
      btn.outerHTML = `<div style="margin-top:12px;text-align:center;font-size:12px;font-weight:700;color:#10b981;"><i class="fas fa-check-circle"></i> Request ${esc(res.data?.request_no || '')} ${isMissing ? 'saved (Refer to Original Registry)' : 'sent to SCB Monitor'}</div>`;
      if (IS_OFS && document.getElementById('myReqContainer')) loadMyRequests();
    } else if (res.duplicate) {
      toast(res.message || 'This file already has an open request to SCB', 'error');
      btn.disabled = false; btn.innerHTML = original;
    } else {
      toast(res.message || 'Could not send request', 'error');
      btn.disabled = false; btn.innerHTML = original;
    }
  } catch(e) {
    toast('Error: ' + e.message, 'error');
    btn.disabled = false; btn.innerHTML = original;
  }
}

// OFS: re-direct an in-transit file's request straight to the office currently
// holding it. Mirrors the Digital File Request "Send Request to {office}" redirect —
// creates a redirected request routed to the last receiving officer's account.
async function redirectFromSearch(btn) {
  const d = lastFileSearch;
  if (!d) return;
  const original = btn.innerHTML;
  const office = d.current_location || 'Current Office';
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  try {
    const res = await api(`{{ route('digital-request.store') }}`, {
      method: 'POST',
      body: JSON.stringify({
        file_no:               d.file_number,
        file_title:            d.file_title || null,
        request_type:          'Physical',
        is_redirected:         true,
        receiving_officer:     d.receiving_officer_name || null,
        current_file_location: d.current_location || null,
        current_file_holder:   d.receiving_officer_name || null,
      }),
    });
    if (res.success) {
      toast(`Request ${res.request_no || ''} re-directed to ${office}`);
      btn.outerHTML = `<div style="margin-top:12px;text-align:center;font-size:12px;font-weight:700;color:#10b981;"><i class="fas fa-check-circle"></i> Request ${esc(res.request_no || '')} re-directed to ${esc(office)}</div>`;
      if (IS_OFS && document.getElementById('myReqContainer')) loadMyRequests();
    } else if (res.duplicate) {
      toast(res.message || 'This file already has an open request', 'error');
      btn.disabled = false; btn.innerHTML = original;
    } else {
      toast(res.message || 'Could not re-direct request', 'error');
      btn.disabled = false; btn.innerHTML = original;
    }
  } catch(e) {
    toast('Error: ' + e.message, 'error');
    btn.disabled = false; btn.innerHTML = original;
  }
}

// OFS: re-direct a file under DCIV investigation straight to the DCIV Director
// instead of raising a File Search Request to the SCB. Mirrors redirectFromSearch().
async function redirectDcivFromSearch(btn) {
  const d = lastFileSearch;
  if (!d) return;
  const original = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Re-directing…';
  try {
    const res = await api(`{{ route('create-file-tracker.quick-search.redirect-dciv-director') }}`, {
      method: 'POST',
      body: JSON.stringify({ file_number: d.file_number, file_title: d.file_title || null }),
    });
    if (res.success) {
      toast(res.message || `Request ${res.request_no || ''} re-directed to DCIV Director`);
      btn.outerHTML = `<div style="margin-top:12px;text-align:center;font-size:12px;font-weight:700;color:#10b981;"><i class="fas fa-check-circle"></i> Request ${esc(res.request_no || '')} re-directed to DCIV Director</div>`;
      if (IS_OFS && document.getElementById('myReqContainer')) loadMyRequests();
    } else {
      toast(res.message || 'Could not re-direct to DCIV Director', 'error');
      btn.disabled = false; btn.innerHTML = original;
    }
  } catch(e) {
    toast('Error: ' + e.message, 'error');
    btn.disabled = false; btn.innerHTML = original;
  }
}

// ─── OFS: My File Requests (track what I sent + outcome) ───────────────────
async function loadMyRequests() {
  const box = document.getElementById('myReqContainer');
  if (!box) return;
  box.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  try {
    const res = await api(`${MOB_BASE}/my-file-requests`);
    const list = (res && res.success) ? (res.data || []) : [];
    if (!list.length) {
      box.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-inbox fa-2x"></i><p style="margin-top:12px;">You haven\'t sent any file requests yet.</p></div>';
      return;
    }
    box.innerHTML = list.map(renderMyRequestCard).join('');
  } catch(e) {
    box.innerHTML = `<p style="color:#ef4444;font-size:12px;">Error loading your requests: ${esc(e.message)}</p>`;
  }
}

function renderMyRequestCard(fr) {
  const m = FSR_STATUS_META[fr.status] || FSR_STATUS_META.PENDING;
  // Logged-out outcome: green "to your office" callout if it's out to this user,
  // otherwise a neutral note showing where the file currently is.
  let loggedOut = '';
  if (fr.logged_out) {
    if (fr.logged_out_to_me) {
      loggedOut = `<div style="margin-top:8px;display:flex;align-items:center;gap:7px;background:rgba(16,185,129,0.12);border:1px solid #10b98155;border-radius:10px;padding:8px 10px;font-size:11.5px;font-weight:700;color:#059669;"><i class="fas fa-building-circle-check"></i> Logged out to your office${fr.logged_out_office ? ' · '+esc(fr.logged_out_office) : ''}</div>`;
    } else {
      loggedOut = `<div style="margin-top:8px;display:flex;align-items:center;gap:7px;background:rgba(245,158,11,0.12);border:1px solid #f59e0b55;border-radius:10px;padding:8px 10px;font-size:11.5px;font-weight:700;color:#b45309;"><i class="fas fa-arrow-right-from-bracket"></i> Logged out${fr.logged_out_office ? ' to '+esc(fr.logged_out_office) : ''}${fr.handler ? ' · '+esc(fr.handler) : ''}</div>`;
    }
  }
  return `
    <div class="result-card" style="margin-bottom:12px;${fr.is_ofs ? 'border-left:4px solid #f59e0b;' : ''}">
      <div style="padding:14px 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
          <div style="font-size:15px;font-weight:800;">${esc(fr.file_number)}</div>
          <span style="white-space:nowrap;font-size:10px;font-weight:800;padding:3px 9px;border-radius:30px;background:${m.bg};color:${m.fg};">${esc(m.label)}</span>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-top:4px;">${esc(fr.file_title||'—')}</div>
        <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:5px;align-items:center;">
          <span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:${fr.is_dfr ? '#e5e7eb' : (fr.is_blind ? '#fee2e2' : '#e0f2fe')};color:${fr.is_dfr ? '#374151' : (fr.is_blind ? '#b91c1c' : '#0369a1')};"><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}"></i> ${esc(fr.request_type || 'Open Request')}</span>
          <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-soft);padding:2px 8px;border-radius:30px;">${esc(fr.request_no)}</span>
        </div>
        ${fr.feedback_note ? `<div style="margin-top:8px;font-size:11.5px;color:var(--muted);background:var(--surface-2);border-radius:8px;padding:7px 9px;"><i class="fas fa-comment-dots" style="margin-right:5px;color:var(--primary);"></i>${esc(fr.feedback_note)}</div>` : ''}
        ${loggedOut}
        ${fr.current_location && !fr.logged_out ? `<div style="font-size:11px;color:var(--faint);margin-top:6px;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--primary);"></i>${esc(fr.current_location)}</div>` : ''}
        ${fr.created_at ? `<div style="font-size:11px;color:var(--faint);margin-top:6px;"><i class="fas fa-clock" style="margin-right:4px;color:var(--primary);"></i>Sent ${esc(fr.created_at)}${fr.responded_at ? ' · Responded '+esc(fr.responded_at) : ''}</div>` : ''}
      </div>
    </div>`;
}

// Raise an FR straight from a Pool-Office search result (web → SCB pipeline mirror).
async function createFrFromSearch(fileNumber, btn) {
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  try {
    const res = await api(`{{ route('create-file-tracker.file-request') }}`, {
      method:'POST',
      body: JSON.stringify({ file_number: fileNumber }),
    });
    if (res.success) { toast(`File Request ${res.data?.request_no||''} created`); if (IS_SCB_MONITOR) loadFileRequests(); }
    else toast(res.message||'Could not create request','error');
  } catch(e) { toast('Error: '+e.message,'error'); }
  btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Raise File Request';
}

// ─── SCB Monitor: FSR view toggle (Open inbox / full log) ──────────────────
const FSR_STATUS_META = {
  PENDING:   { label:'Pending',   bg:'#fef3c7', fg:'#92400e' },
  SEARCHING: { label:'Searching', bg:'#dbeafe', fg:'#1e40af' },
  FOUND:     { label:'Found',     bg:'#dcfce7', fg:'#166534' },
  NOT_FOUND: { label:'Not Found', bg:'#fee2e2', fg:'#991b1b' },
  CLOSED:    { label:'Closed',    bg:'#e5e7eb', fg:'#374151' },
};

let frView = 'open';
function setFrView(view) {
  frView = view;
  document.querySelectorAll('.fr-seg-btn').forEach(b => b.classList.toggle('active', b.dataset.frview === view));
  const open = document.getElementById('frListContainer');
  const log  = document.getElementById('frLogContainer');
  if (open) open.style.display = view === 'open' ? 'block' : 'none';
  if (log)  log.style.display  = view === 'log'  ? 'block' : 'none';
  // loadFileRequests() refreshes the Requests Today tile itself; the History view
  // fetches a different endpoint, so top the tile up separately there.
  if (view === 'open') loadFileRequests();
  else { loadFsrLog(); loadFsToday(); }
}

// Filter a File Request list by the search box term (file no, title, request no, requester).
function frMatches(fr) {
  const q = (document.getElementById('frSearch')?.value || '').trim().toLowerCase();
  if (!q) return true;
  return [fr.file_number, fr.file_title, fr.request_no, fr.requester, fr.receiving_officer, fr.current_location]
    .some(v => (v || '').toString().toLowerCase().includes(q));
}

// Tab badges show the TRUE total from the API (counted before the display limit),
// falling back to the loaded list length when a total isn't supplied.
let frOpenTotal = null, frLogTotal = null;
function updateFrCounts() {
  const openEl = document.getElementById('frCountOpen');
  const logEl  = document.getElementById('frCountLog');
  if (openEl) openEl.textContent = frOpenTotal != null ? frOpenTotal : (frOpenList || []).length;
  if (logEl)  logEl.textContent  = frLogTotal  != null ? frLogTotal  : (frLogList  || []).length;
}

// Re-render whichever tab is active (used by the search box). For FSR History we
// also refetch from the server (debounced) so matches beyond the 200 most-recent
// rows are found — client-side filtering alone would miss older requests.
let frLogSearchTimer = null;
function onFrSearch() {
  const clearBtn = document.getElementById('frSearchClear');
  if (clearBtn) clearBtn.classList.toggle('show', !!(document.getElementById('frSearch')?.value || '').trim());
  if (frView === 'log') {
    renderFsrLog();
    clearTimeout(frLogSearchTimer);
    frLogSearchTimer = setTimeout(loadFsrLog, 350);
  } else {
    renderFileRequests();
  }
}
function clearFrSearch() {
  const input = document.getElementById('frSearch');
  if (input) input.value = '';
  onFrSearch();
}

// Full FSR History — every request routed to this SCB Monitor, by whom, where, status.
let frLogList = [];
async function loadFsrLog() {
  const container = document.getElementById('frLogContainer');
  if (!container) return;
  container.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  try {
    const q = (document.getElementById('frSearch')?.value || '').trim();
    const res = await api(`${MOB_BASE}/file-requests/log${q ? ('?q=' + encodeURIComponent(q)) : ''}`);
    frLogList = (res && res.success) ? (res.data || []) : [];
    frLogTotal = (res && res.success && res.total != null) ? res.total : frLogList.length;
    updateFrCounts();
    renderFsrLog();
  } catch(e) {
    container.innerHTML = `<p style="color:#ef4444;font-size:12px;">Error loading FSR History: ${esc(e.message)}</p>`;
  }
}

function renderFsrLog() {
  const container = document.getElementById('frLogContainer');
  if (!container) return;
  const list = (frLogList || []).filter(frMatches);
  if (!list.length) {
    container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-clipboard-list fa-2x"></i><p style="margin-top:12px;">No file requests yet</p></div>';
    return;
  }
  container.innerHTML = list.map(fr => {
      const meta = FSR_STATUS_META[fr.status] || FSR_STATUS_META.PENDING;
      // For a Not Found outcome, spell out the kind (Missing / Pending) on the badge.
      const nft = (fr.status === 'NOT_FOUND' && fr.not_found_type)
        ? String(fr.not_found_type).toUpperCase() : '';
      const badgeLabel = nft ? `${meta.label} · ${nft === 'MISSING' ? 'Missing' : 'Pending'}` : meta.label;
      return `
      <div class="result-card" style="margin-bottom:12px;">
        <div style="padding:14px 16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div style="font-size:15px;font-weight:800;">${esc(fr.file_number)}</div>
            <span style="font-size:9px;font-weight:800;padding:3px 9px;border-radius:30px;background:${meta.bg};color:${meta.fg};">${badgeLabel}</span>
          </div>
          <div style="font-size:12px;color:var(--text);margin-top:4px;">${esc(fr.file_title||'—')}</div>
          <div style="font-size:12px;color:var(--text);margin-top:8px;display:flex;flex-direction:column;gap:5px;">
            <span><i class="fas fa-hashtag" style="width:14px;color:var(--primary);"></i> ${esc(fr.request_no)}</span>
            <span><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}" style="width:14px;color:var(--primary);"></i> ${esc(fr.request_type || 'Open Request')}</span>
            <span><i class="fas fa-user" style="width:14px;color:var(--primary);"></i> By ${esc(fr.requester||'—')}</span>
            ${fr.current_location ? `<span><i class="fas fa-map-marker-alt" style="width:14px;color:var(--primary);"></i> ${esc(fr.current_location)}</span>` : ''}
            <span><i class="fas fa-clock" style="width:14px;color:var(--primary);"></i> Sent ${esc(fr.created_at||'—')}</span>
            ${fr.responded_at ? `<span><i class="fas fa-reply" style="width:14px;color:var(--primary);"></i> ${meta.label} ${esc(fr.responded_at)}${fr.responder ? ' · '+esc(fr.responder) : ''}</span>` : ''}
            ${fr.feedback_note ? `<span style="font-style:italic;">"${esc(fr.feedback_note)}"</span>` : ''}
          </div>
          ${(IS_SUPER_ADMIN && fr.can_revert) ? `
          <div data-fr="${fr.id}" style="margin-top:12px;">
            <button class="btn" style="width:100%;padding:11px;font-size:13px;box-shadow:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;" onclick="revertFr(${fr.id}, this)"><i class="fas fa-rotate-left"></i> Revert response</button>
          </div>` : ''}
        </div>
      </div>`;
    }).join('');
}

// Revert (undo) an accidental Found / Not-Found response, putting the request
// back in the Open queue. Only offered while the Front Desk hasn't acted yet.
async function revertFr(id, btn) {
  if (!confirm('Revert this response and send the request back to the open queue?')) return;
  const original = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reverting…';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}/revert`, { method: 'POST' });
    if (res && res.success) {
      toast('Response reverted — back in the open queue');
      // Reload both lists and drop the user back on the Open tab.
      loadFileRequests();
      setFrView('open');
    } else {
      toast(res.message || 'Could not revert this response', 'error');
      btn.disabled = false; btn.innerHTML = original;
    }
  } catch(e) {
    toast('Error: ' + e.message, 'error');
    btn.disabled = false; btn.innerHTML = original;
  }
}

// ─── SCB Monitor: "Requests Today" card on File Search ─────────────────────
function renderFsToday(t) {
  if (!t) return;
  const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = (v ?? 0); };
  set('fsToday', t.total);
  set('fsTodayFound', t.found);
  set('fsTodayNotFound', t.not_found);
  set('fsTodayAwaiting', t.awaiting);
}

async function loadFsToday() {
  if (!IS_SCB_MONITOR || !document.getElementById('fsTodayCard')) return;
  try {
    const res = await api(`${MOB_BASE}/file-requests`);
    if (res && res.success) { openFileRequests = res.data || []; renderFsToday(res.today); }
  } catch (e) { /* leave the tile on its placeholder dashes */ }
}

// ─── SCB Monitor: File Requests inbox ──────────────────────────────────────
// Cached open-FR list so File Search can offer a Found/Not-Found shortcut.
let openFileRequests = null;
async function getOpenFileRequests() {
  if (openFileRequests !== null) return openFileRequests;
  try {
    const res = await api(`${MOB_BASE}/file-requests`);
    openFileRequests = (res && res.success) ? (res.data || []) : [];
    if (res && res.success) renderFsToday(res.today);
  } catch (e) { openFileRequests = []; }
  return openFileRequests;
}

let frOpenList = [];
async function loadFileRequests() {
  const container = document.getElementById('frListContainer');
  if (!container) return;
  container.innerHTML = '<div class="card" style="padding:30px;text-align:center;color:var(--faint);"><i class="fas fa-spinner fa-spin fa-lg"></i></div>';
  try {
    // Load the History alongside the Open inbox so we can flag any request that
    // wrongly shows up in BOTH tabs at once (green dot — see renderFileRequests).
    const [res, logRes] = await Promise.all([
      api(`${MOB_BASE}/file-requests`),
      api(`${MOB_BASE}/file-requests/log`),
    ]);
    frOpenList = (res && res.success) ? (res.data || []) : [];
    frLogList  = (logRes && logRes.success) ? (logRes.data || []) : [];
    frOpenTotal = (res && res.success && res.total != null) ? res.total : frOpenList.length;
    frLogTotal  = (logRes && logRes.success && logRes.total != null) ? logRes.total : frLogList.length;
    openFileRequests = frOpenList;  // keep the search shortcut in sync
    renderFsToday(res && res.today);  // keep File Search's Requests Today tile in sync
    updateFrCounts();
    renderFileRequests();
  } catch(e) {
    container.innerHTML = `<p style="color:#ef4444;font-size:12px;">Error loading requests: ${esc(e.message)}</p>`;
  }
}

// Collapsible "Request Details" block — Request Purpose, Timeline, Expected
// Return Date, Delay Reason — tucked away by default so the inbox card stays
// scannable; reuses the same timeline/date helpers as the File Search timeline.
function frDetailsSection(fr) {
  return `
    <details style="margin-top:10px;">
      <summary style="cursor:pointer;font-size:11.5px;font-weight:700;color:var(--primary);">Request Details</summary>
      ${frDetailsBody(fr)}
    </details>`;
}

// The Request Details panel itself — shared by the admin card (inside its own
// <details>) and the compact SCB row (already inside the row's <details>).
function frDetailsBody(fr) {
  const purpose = fr.request_purpose_name ? esc(fr.request_purpose_name) : '—';
  const timelineMeta = formatTimelineMeta({ timelineStatus: fr.timeline_status, daysUntilDeadline: fr.days_until_deadline });
  const timelineHtml = timelineMeta
    ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:${timelineMeta.bg};color:${timelineMeta.color};border:1px solid ${timelineMeta.border};">${timelineMeta.icon ? `<i class="fas ${timelineMeta.icon}"></i>` : ''}${esc(timelineMeta.label)}</span>`
    : '<span style="color:var(--muted);">—</span>';
  const delayReason = fr.delay_reason ? esc(fr.delay_reason) : '—';
  // The Expected Return Date is deliberately NOT the stored request date — a request has
  // no return window until the file is handed over. The row stays visible (so the field
  // is not mistaken for missing) but reads "Pending" until the file is logged out, at
  // which point the FileTracker supplies the real date.
  const pendingChip = `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:700;background:#e2e8f0;color:#475569;border:1px solid #cbd5e1;"><i class="fas fa-clock"></i>Pending</span>`;
  return `
      <div style="margin-top:8px;padding:10px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px;display:flex;flex-direction:column;gap:7px;">
        <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Request Purpose:</span> <span style="font-size:12px;color:var(--text);">${purpose}</span></div>
        <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Timeline:</span> ${timelineHtml}</div>
        <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Expected Return Date:</span> ${pendingChip}</div>
        <div><span style="font-size:10.5px;font-weight:700;color:var(--muted);">Delay Reason:</span> <span style="font-size:12px;color:var(--text);">${delayReason}</span></div>
      </div>`;
}

// Compact "who is asking" block — the requester details selected in Quick Search.
function frRequesterDetails(fr) {
  const rows = [
    ['fa-user',           fr.receiving_officer,    'Requested by'],
    ['fa-building',       fr.requester_office,     'Office'],
    ['fa-sitemap',        fr.requester_department, 'Department'],
  ].filter(([, val]) => val && String(val).trim() && String(val).trim() !== '—');
  if (!rows.length) return '';
  return `<div style="margin-top:8px;display:flex;flex-direction:column;gap:3px;">` + rows.map(([icon, val, label]) =>
    `<div style="font-size:12px;color:var(--text);"><i class="fas ${icon}" style="width:13px;margin-right:5px;color:var(--primary);"></i><span style="color:var(--text);">${label}:</span> ${esc(val)}</div>`
  ).join('') + `</div>`;
}

// Slim list row used by SCB Monitors: collapsed it is just the file number, who
// asked and when. Tapping it opens the details AND the Found / Not Found / delete
// actions, so the queue stays one thin line per request.
function frListRow(fr, histIds) {
  const mix  = histIds.has(fr.id)
    ? `<span title="Also in FSR History" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#10b981;margin-right:6px;vertical-align:middle;"></span>` : '';
  // Second line is the file title — the requester and timestamp move into the
  // expanded body, since the title is what identifies the file at a glance.
  const title = (fr.file_title && String(fr.file_title).trim()) || '';
  const loc   = fr.current_location && String(fr.current_location).trim();
  const meta  = [fr.receiving_officer, fr.created_at].filter(v => v && String(v).trim() && String(v).trim() !== '—');
  return `
    <div class="result-card fr-row" style="margin-bottom:5px;${fr.is_ofs ? 'border-left:3px solid #f59e0b;' : ''}" data-fr="${fr.id}">
      <details style="padding:7px 10px;">
        <summary style="cursor:pointer;list-style:none;display:flex;align-items:center;gap:8px;">
          <div style="flex:1;min-width:0;">
            <div style="font-size:12.5px;font-weight:800;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${mix}${esc(fr.file_number)}</div>
            ${title ? `<div style="font-size:10px;color:var(--muted);margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(title)}</div>` : ''}
          </div>
          <i class="fas fa-chevron-down fr-row-chev" style="flex:0 0 auto;font-size:10px;color:var(--faint);"></i>
        </summary>
        <div style="margin-top:6px;padding-top:6px;border-top:1px solid var(--border);font-size:11px;color:var(--text);display:flex;flex-direction:column;gap:3px;">
          ${meta.length ? `<div><span style="color:var(--muted);">Requested by:</span> ${meta.map(esc).join(' · ')}</div>` : ''}
          <div><span style="color:var(--muted);">Type:</span> ${esc(fr.request_type || 'Open Request')}${fr.is_ofs ? ' · OFS' : ''}</div>
          ${loc ? `<div><span style="color:var(--muted);">Location:</span> ${esc(loc)}</div>` : ''}
          ${fr.requester_office ? `<div><span style="color:var(--muted);">Office:</span> ${esc(fr.requester_office)}</div>` : ''}
        </div>
        ${frDetailsBody(fr)}
        <div style="display:flex;gap:6px;margin-top:8px;">
          <button class="btn" style="flex:1;width:auto;min-width:0;padding:9px;font-size:12px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);" onclick="respondFr(${fr.id}, 'found', this, ${fr.is_blind ? 'true' : 'false'})"><i class="fas fa-check"></i> Found</button>
          <button class="btn ghost-btn" style="flex:1;width:auto;min-width:0;padding:9px;font-size:12px;box-shadow:none;" onclick="respondFr(${fr.id}, 'not_found', this)"><i class="fas fa-xmark"></i> Not&nbsp;Found</button>
          ${IS_SUPER_ADMIN ? `<button class="btn" style="flex:0 0 auto;width:auto;padding:9px 12px;font-size:12px;box-shadow:none;background:#fee2e2;color:#991b1b;" onclick="deleteFr(${fr.id}, this)" title="Delete request"><i class="fas fa-trash"></i></button>` : ''}
        </div>
      </details>
    </div>`;
}

function renderFileRequests() {
  const container = document.getElementById('frListContainer');
  if (!container) return;
  const list = (frOpenList || []).filter(frMatches);
  if (!list.length) {
    container.innerHTML = '<div class="card" style="padding:40px;text-align:center;color:var(--faint);"><i class="fas fa-clipboard-check fa-2x"></i><p style="margin-top:12px;">No open file requests</p></div>';
    return;
  }
  // IDs present in FSR History — a request here AND in Open is a mix-up (flagged with a green dot).
  const histIds = new Set((frLogList || []).map(r => r.id));
  // SCB Monitors work through a queue, so they get a stripped-down list view with
  // the Found / Not Found / delete actions. Super admins keep the full card here,
  // but read-only — they act on requests from the SCB tab instead.
  if (frCompact) { container.innerHTML = list.map(fr => frListRow(fr, histIds)).join(''); return; }
  container.innerHTML = list.map(fr => `
      <div class="result-card" style="margin-bottom:12px;${fr.is_ofs ? 'border-left:4px solid #f59e0b;background:rgba(245,158,11,0.06);' : ''}" data-fr="${fr.id}">
        <div style="padding:14px 16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <div style="font-size:15px;font-weight:800;">${histIds.has(fr.id) ? `<span title="Also in FSR History — this request is in both tabs at once" style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#10b981;margin-right:7px;vertical-align:middle;box-shadow:0 0 0 3px rgba(16,185,129,0.25);"></span>` : ''}${esc(fr.file_number)}</div>
            <span style="font-size:10px;font-weight:700;color:var(--primary);background:var(--primary-soft);padding:3px 9px;border-radius:30px;">${esc(fr.request_no)}</span>
          </div>
          <div style="font-size:12px;color:var(--text);margin-top:4px;">${esc(fr.file_title||'—')}</div>
          <div style="margin-top:6px;"><span style="display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:${fr.is_dfr ? '#e5e7eb' : (fr.is_blind ? '#fee2e2' : '#e0f2fe')};color:${fr.is_dfr ? '#374151' : (fr.is_blind ? '#b91c1c' : '#0369a1')};"><i class="fas ${fr.is_dfr ? 'fa-file-lines' : (fr.is_blind ? 'fa-eye-slash' : 'fa-folder-open')}"></i> ${esc(fr.request_type || 'Open Request')}</span>${fr.is_ofs ? `<span style="margin-left:5px;display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:800;padding:2px 8px;border-radius:30px;background:#fef3c7;color:#92400e;border:1px solid #fcd34d;"><i class="fas fa-crown"></i> OFS${fr.ofs_rank ? ' · '+esc(fr.ofs_rank) : ''}</span>` : ''}</div>
          ${fr.current_location ? (
            String(fr.current_location).trim().toLowerCase() === 'not indexed'
              ? `<div style="margin-top:8px;"><span style="display:inline-flex;align-items:center;gap:5px;font-size:10px;font-weight:800;padding:3px 9px;border-radius:30px;background:#fee2e2;color:#b91c1c;"><i class="fas fa-circle-exclamation"></i> Not indexed</span></div>`
              : `<div style="font-size:12px;color:var(--text);margin-top:6px;"><i class="fas fa-map-marker-alt" style="margin-right:4px;color:var(--primary);"></i>${esc(fr.current_location)}</div>`
          ) : ''}
          ${frRequesterDetails(fr)}
          ${fr.created_at ? `<div style="font-size:12px;color:var(--text);margin-top:6px;"><i class="fas fa-clock" style="margin-right:4px;color:var(--primary);"></i>Sent ${esc(fr.created_at)}</div>` : ''}
          ${frDetailsSection(fr)}
        </div>
      </div>`).join('');
}

// ─── "Not Found" type chooser ──────────────────────────────────────────────
// When the SCB Monitor marks a request Not Found, they must say which kind:
// MISSING (file genuinely can't be located) or PENDING (search not concluded).
// Returns a Promise resolving to 'missing' | 'pending', or null if cancelled.
function pickNotFoundType() {
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:10050;background:rgba(0,0,0,0.55);display:flex;align-items:flex-end;justify-content:center;';
    overlay.innerHTML = `
      <div style="background:var(--card,#1b1b22);width:100%;max-width:480px;border-radius:18px 18px 0 0;padding:20px 18px calc(18px + env(safe-area-inset-bottom));box-shadow:0 -8px 30px rgba(0,0,0,0.4);">
        <div style="width:42px;height:4px;border-radius:4px;background:var(--faint,#555);opacity:.5;margin:0 auto 16px;"></div>
        <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px;"><i class="fas fa-triangle-exclamation" style="color:#ef4444;margin-right:6px;"></i> Not Found — which type?</div>
        <div style="font-size:12px;color:var(--faint);margin-bottom:16px;">Tell the requester whether the file is missing or the search is still pending.</div>
        <button class="btn" data-pick="missing" style="width:100%;padding:14px;font-size:14px;box-shadow:none;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;margin-bottom:10px;text-align:left;"><i class="fas fa-ban" style="margin-right:8px;"></i> Missing<span style="display:block;font-size:11px;font-weight:500;opacity:.9;margin-top:2px;">File not given to us</span></button>
        <button class="btn" data-pick="pending" style="width:100%;padding:14px;font-size:14px;box-shadow:none;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;margin-bottom:14px;text-align:left;"><i class="fas fa-hourglass-half" style="margin-right:8px;"></i> Pending<span style="display:block;font-size:11px;font-weight:500;opacity:.9;margin-top:2px;"></span></button>
        <button class="btn ghost-btn" data-pick="" style="width:100%;padding:12px;font-size:13px;box-shadow:none;">Cancel</button>
      </div>`;
    function close(val) { overlay.remove(); resolve(val || null); }
    overlay.addEventListener('click', e => {
      if (e.target === overlay) return close(null);
      const b = e.target.closest('[data-pick]');
      if (b) close(b.getAttribute('data-pick'));
    });
    document.body.appendChild(overlay);
  });
}

// Blind-request Found picker: either the file is already indexed (can be logged),
// or physically found and queued for indexing first.
function pickBlindFoundType() {
  return new Promise(resolve => {
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:10050;background:rgba(0,0,0,0.55);display:flex;align-items:flex-end;justify-content:center;';
    overlay.innerHTML = `
      <div style="background:var(--card,#1b1b22);width:100%;max-width:480px;border-radius:18px 18px 0 0;padding:20px 18px calc(18px + env(safe-area-inset-bottom));box-shadow:0 -8px 30px rgba(0,0,0,0.4);">
        <div style="width:42px;height:4px;border-radius:4px;background:var(--faint,#555);opacity:.5;margin:0 auto 16px;"></div>
        <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:4px;"><i class="fas fa-circle-check" style="color:#10b981;margin-right:6px;"></i> Found — select type</div>
        <div style="font-size:12px;color:var(--faint);margin-bottom:16px;">Choose whether the file is already indexed or should wait in the indexing queue.</div>
        <button class="btn" data-pick="indexed" style="width:100%;padding:14px;font-size:14px;box-shadow:none;background:linear-gradient(135deg,#10b981,#059669);color:#fff;margin-bottom:10px;text-align:left;"><i class="fas fa-check-double" style="margin-right:8px;"></i> Found (Indexed)</button>
        <button class="btn" data-pick="indexing" style="width:100%;padding:14px;font-size:14px;box-shadow:none;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;margin-bottom:14px;text-align:left;"><i class="fas fa-layer-group" style="margin-right:8px;"></i> Found for Indexing</button>
        <button class="btn ghost-btn" data-pick="" style="width:100%;padding:12px;font-size:13px;box-shadow:none;">Cancel</button>
      </div>`;
    function close(val) { overlay.remove(); resolve(val || null); }
    overlay.addEventListener('click', e => {
      if (e.target === overlay) return close(null);
      const b = e.target.closest('[data-pick]');
      if (b) close(b.getAttribute('data-pick'));
    });
    document.body.appendChild(overlay);
  });
}

async function respondFr(id, result, btn, isBlindRequest = false) {
  let note = '';
  let foundType = null;
  if (result === 'found' && isBlindRequest) {
    foundType = await pickBlindFoundType();
    if (!foundType) return;
  }
  // A Not Found response requires the monitor to choose missing / pending first.
  let notFoundType = null;
  if (result === 'not_found') {
    notFoundType = await pickNotFoundType();
    if (!notFoundType) return;  // cancelled — leave the request open
  }
  const card = document.querySelector(`[data-fr="${id}"]`);
  if (card) card.querySelectorAll('button').forEach(b=>b.disabled=true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}/respond`, {
      method:'POST',
      body: JSON.stringify({ result, note, found_type: foundType, not_found_type: notFoundType }),
    });
    if (res && res.success) {
      const sc = res.data?.second_check;
      if (result === 'found') {
        toast(foundType === 'indexing' ? 'Marked as Found for Indexing ✅' : 'Marked as Found (Indexed) ✅');
      }
      else if (notFoundType) toast(`Marked as Not Found — ${notFoundType === 'missing' ? 'Missing' : 'Pending'}`, 'error');
      else if (sc === 'print_missing_slip') toast('Not found — print Missing File slip', 'error');
      else if (sc === 'do_second_physical_search') toast('Not found — file is scanned; do a 2nd physical search', 'error');
      else toast('Feedback recorded');
      // Responded → the request leaves Open; refresh the Open count and move the
      // user to the FSR History tab (which reloads its list + count).
      loadFileRequests();
      setFrView('log');
    } else {
      toast(res.message || 'Could not record feedback', 'error');
      if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
    }
  } catch(e) {
    toast('Error: '+e.message, 'error');
    if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
  }
}

// Respond to an open File Request straight from the File Search result.
async function respondFrFromSearch(id, result, btn, isBlindRequest = false) {
  let foundType = null;
  if (result === 'found' && isBlindRequest) {
    foundType = await pickBlindFoundType();
    if (!foundType) return;
  }
  // A Not Found response requires the monitor to choose missing / pending first.
  let notFoundType = null;
  if (result === 'not_found') {
    notFoundType = await pickNotFoundType();
    if (!notFoundType) return;  // cancelled — leave the request open
  }
  const wrap = btn.closest('[data-fr]');
  if (wrap) wrap.querySelectorAll('button').forEach(b=>b.disabled=true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}/respond`, {
      method:'POST',
      body: JSON.stringify({ result, note:'', found_type: foundType, not_found_type: notFoundType }),
    });
    if (res && res.success) {
      openFileRequests = null;  // invalidate cache; this FR is no longer open
      const found = result === 'found';
      const nftLabel = notFoundType ? ` — ${notFoundType === 'missing' ? 'Missing' : 'Pending'}` : '';
      const foundLabel = foundType === 'indexing' ? 'Found for Indexing' : 'Found (Indexed)';
      toast(found ? `Marked as ${foundLabel} ✅` : 'Marked as Not Found' + nftLabel);
      if (wrap) {
        wrap.innerHTML = `<div style="font-size:12px;font-weight:700;color:${found ? '#10b981' : '#ef4444'};">
          <i class="fas fa-${found ? 'check-circle' : 'times-circle'}"></i> Response recorded: ${found ? foundLabel : 'Not Found' + nftLabel}</div>`;
      }
    } else {
      toast(res.message || 'Could not record feedback', 'error');
      if (wrap) wrap.querySelectorAll('button').forEach(b=>b.disabled=false);
    }
  } catch(e) {
    toast('Error: '+e.message, 'error');
    if (wrap) wrap.querySelectorAll('button').forEach(b=>b.disabled=false);
  }
}

// Delete a File Search Request from the open inbox.
async function deleteFr(id, btn) {
  if (!confirm('Delete this file request? This cannot be undone.')) return;
  const card = document.querySelector(`[data-fr="${id}"]`);
  if (card) card.querySelectorAll('button').forEach(b=>b.disabled=true);
  if (btn) btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  try {
    const res = await api(`${MOB_BASE}/file-requests/${id}`, { method:'DELETE' });
    if (res && res.success) {
      openFileRequests = null;  // invalidate search shortcut cache
      toast('File request deleted');
      if (card) card.remove();
    } else {
      toast(res.message || 'Could not delete request', 'error');
      if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
      if (btn) btn.innerHTML = '<i class="fas fa-trash"></i>';
    }
  } catch(e) {
    toast('Error: '+e.message, 'error');
    if (card) card.querySelectorAll('button').forEach(b=>b.disabled=false);
    if (btn) btn.innerHTML = '<i class="fas fa-trash"></i>';
  }
}

// ─── Event listeners ──────────────────────────────────────────────────────
document.getElementById('fileSearchBtn')?.addEventListener('click', searchFile);
// File-number Select2 search — selecting a file auto-runs the movement lookup.
$('#fileSearchSelect').select2({
  placeholder: 'Search file number…',
  allowClear: true,
  minimumInputLength: 2,
  width: '100%',
  ajax: {
    url: '{{ url("/digital-request/file-numbers") }}',
    dataType: 'json',
    delay: 250,
    data: params => ({ q: params.term }),
    processResults: data => ({ results: data.results || [] }),
    cache: true,
  },
});
$('#fileSearchSelect').on('select2:select', searchFile);
// Blind request: typed file number — Enter runs the lookup; pausing after typing
// auto-loads the result so the user doesn't have to click the button.
document.getElementById('fileSearchManual')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); clearTimeout(blindAutoLoadTimer); searchFile(); } });
document.getElementById('fileSearchManual')?.addEventListener('input', onBlindManualInput);

document.getElementById('startCameraBtn')?.addEventListener('click', startScanner);
document.getElementById('stopCameraBtn')?.addEventListener('click', stopScanner);
document.getElementById('flashToggleBtn')?.addEventListener('click', toggleFlash);
document.getElementById('manualScanBtn')?.addEventListener('click', manualLookup);
document.getElementById('manualScanInput')?.addEventListener('keydown', e => { if (e.key === 'Enter') manualLookup(); });
document.getElementById('notificationBell')?.addEventListener('click', function(e) {
  e.stopPropagation(); document.getElementById('notificationPanel').classList.toggle('show');
});
document.addEventListener('click', ()=>document.getElementById('notificationPanel')?.classList.remove('show'));
document.getElementById('markAllRead')?.addEventListener('click', async function(e) {
  e.stopPropagation();
  await api(`${MOB_BASE}/notifications/mark-all-read`, { method:'POST' });
  notifications.forEach(n=>n.is_read=true); renderNotifications(); updateNotificationBadge();
});
document.querySelectorAll('.tab-item[data-tab]').forEach(tab=>{
  const t = tab.getAttribute('data-tab');
  if (t && t !== 'dfr') tab.addEventListener('click', ()=>setActiveTab(t));
});
document.getElementById('frRefreshBtn')?.addEventListener('click', () => (frView === 'log' ? loadFsrLog() : loadFileRequests()));
// Priority chip selector (Log a File)
document.querySelectorAll('#create-screen .prio-chip').forEach(chip=>{
  chip.addEventListener('click', ()=>{
    document.querySelectorAll('#create-screen .prio-chip').forEach(c=>c.classList.remove('active'));
    chip.classList.add('active');
  });
});

// ─── Boot ─────────────────────────────────────────────────────────────────
applyTheme(document.documentElement.getAttribute('data-theme') || 'light');
// Honour a deep-link hash (e.g. #files) so the bottom-nav on other pages can open
// a specific screen here. Falls back to the dashboard.
const VALID_TABS = ['dashboard','files','myreq','requests','scanner','profile'];
const bootTab = (location.hash || '').replace('#','');
setActiveTab(VALID_TABS.includes(bootTab) ? bootTab : 'dashboard');
loadNotifications();
</script>
</body>
</html>




