<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=no">
  <meta name="theme-color" content="#450a0a">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>My File Requests</title>
  {{-- Set theme before paint to avoid flash (shared 'klaes-theme' key, same as the dashboard) --}}
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <style>
    /* ── Design tokens (light defaults; dark overrides below) ──────── */
    :root {
      --dfr-bg: #fff7f7;
      --dfr-surface: #ffffff;
      --dfr-surface-2: #fafafa;
      --dfr-surface-3: #f9fafb;
      --dfr-text: #1f2937;
      --dfr-body: #4b5563;
      --dfr-muted: #6b7280;
      --dfr-faint: #9ca3af;
      --dfr-border: #f3f4f6;
      --dfr-border-strong: #e5e7eb;
      --dfr-accent: #450a0a;   /* brand, used as text/icon accent */
      --dfr-brand: #450a0a;    /* solid brand fill (header, primary buttons) */
      --dfr-brand-grad: linear-gradient(135deg, #450a0a 0%, #6b1010 50%, #450a0a 100%);
      --dfr-on-brand: #ffffff; /* text/icons sitting on a brand fill */
      --dfr-active: #450a0a;   /* selected tab / file-type toggle */
    }
    [data-theme="dark"] {
      --dfr-bg: #0c0e15;
      --dfr-surface: #171a24;
      --dfr-surface-2: #1d212e;
      --dfr-surface-3: #242938;
      --dfr-text: #e7e9f3;
      --dfr-body: #e7e9f3;
      --dfr-muted: #c3c8d8;
      --dfr-faint: #9aa0b6;
      --dfr-border: #262b3a;
      --dfr-border-strong: #2f3445;
      /* Dark mode: keep header + primary buttons dark (no bright gradient), with an
         indigo accent reserved for the selected tab/toggle so the choice stays clear. */
      --dfr-accent: #a5b4fc;
      --dfr-brand: #242938;
      --dfr-brand-grad: linear-gradient(135deg, #242938 0%, #2f3445 100%);
      --dfr-on-brand: #e7e9f3;
      --dfr-active: #6366f1;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: var(--dfr-bg); color: var(--dfr-body); height: 100vh; overflow: hidden; position: fixed; width: 100%; transition: background-color .3s ease, color .3s ease; }
    #app { display: flex; flex-direction: column; height: 100vh; width: 100%; overflow: hidden; }

    /* ── Header ─────────────────────────────────────── */
    .dfr-header { background: var(--dfr-brand-grad); padding: 16px 16px 20px; flex-shrink: 0; }
    .dfr-header-top { display: flex; align-items: center; justify-content: space-between; }
    .back-btn { background: rgba(255,255,255,0.15); border: none; color: white; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
    .back-btn i { font-size: 16px; }
    .header-title { flex: 1; text-align: center; }
    .header-title h1 { font-size: 17px; font-weight: 700; color: white; }
    .header-title p  { font-size: 11px; color: rgba(255,255,255,0.7); margin-top: 2px; }
    .header-avatar { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; color: white; font-size: 15px; font-weight: 700; flex-shrink: 0; }

    /* ── Toolbar ─────────────────────────────────────── */
    .toolbar { background: var(--dfr-surface); border-bottom: 1px solid var(--dfr-border); padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .toolbar-title { font-size: 14px; font-weight: 700; color: var(--dfr-accent); display: flex; align-items: center; gap: 6px; }
    .refresh-btn { background: none; border: 1px solid var(--dfr-accent); border-radius: 30px; padding: 5px 14px; font-size: 11px; font-weight: 600; color: var(--dfr-accent); cursor: pointer; display: flex; align-items: center; gap: 5px; }
    .refresh-btn:active { background: var(--dfr-surface-2); }

    /* ── Scroll area ─────────────────────────────────── */
    .main-scroll { flex: 1; overflow-y: auto; padding: 14px 14px calc(96px + env(safe-area-inset-bottom)); -webkit-overflow-scrolling: touch; }
    .main-scroll::-webkit-scrollbar { width: 3px; }
    .main-scroll::-webkit-scrollbar-thumb { background: var(--dfr-accent); border-radius: 10px; }

    /* ── Bottom navigation (mirrors the dashboard tab bar) ─────────── */
    .dfr-nav { position: fixed; bottom: 14px; left: 14px; right: 14px; max-width: 540px; margin: 0 auto; z-index: 9000;
      background: var(--dfr-surface); border: 1px solid var(--dfr-border); border-radius: 26px; display: flex;
      padding: 8px 6px calc(8px + env(safe-area-inset-bottom)); box-shadow: 0 10px 34px rgba(0,0,0,0.18); }
    .dfr-nav a { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px;
      color: var(--dfr-faint); font-size: 10px; font-weight: 600; text-decoration: none; padding: 7px 4px; border-radius: 16px; }
    .dfr-nav a i { font-size: 18px; }
    .dfr-nav a.active { color: var(--dfr-accent); }

    /* ── Request cards ─────────────────────────────────── */
    .req-card { background: var(--dfr-surface); border-radius: 16px; border: 1px solid var(--dfr-border); box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 12px; overflow: hidden; }
    .req-card-top { padding: 11px 14px; background: var(--dfr-surface-2); border-bottom: 1px solid var(--dfr-border); display: flex; justify-content: space-between; align-items: center; }
    .req-no { font-size: 11px; font-weight: 700; color: var(--dfr-accent); }
    .req-type-badge { font-size: 9px; font-weight: 700; padding: 2px 8px; border-radius: 30px; }
    .req-type-physical { background: #fef3c7; color: #92400e; }
    .req-type-digital  { background: #dbeafe; color: #1e40af; }
    .req-body { padding: 12px 14px; }
    .req-file { font-size: 14px; font-weight: 700; color: var(--dfr-text); }
    .req-title { font-size: 11px; color: var(--dfr-muted); margin-top: 2px; }
    .req-remarks { font-size: 11px; color: var(--dfr-faint); font-style: italic; margin-top: 4px; }
    .req-loc { display: flex; align-items: flex-start; gap: 6px; font-size: 10.5px; color: var(--dfr-muted); margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--dfr-border-strong); }
    .req-loc i { color: var(--dfr-accent); margin-top: 1px; }
    .req-loc-label { font-weight: 700; color: var(--dfr-accent); }
    .req-loc.is-archive i { color: #b45309; }
    .req-loc.is-archive .req-loc-label { color: #b45309; }
    .req-loc.is-transit i { color: #1e40af; }
    .req-loc.is-transit .req-loc-label { color: #1e40af; }
    .req-status-row { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
    .status-pill { font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 30px; }
    .status-pending  { background: #fef3c7; color: #92400e; }
    .status-approved { background: #dcfce7; color: #166534; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-transit  { background: #dbeafe; color: #1e40af; }
    .req-date { font-size: 10px; color: var(--dfr-faint); }

    /* ── Empty / loading ─────────────────────────────── */
    .empty-state { text-align: center; padding: 50px 20px; }
    .empty-state i  { font-size: 48px; color: var(--dfr-accent); margin-bottom: 12px; display: block; }
    .empty-state h3 { font-size: 15px; font-weight: 700; color: var(--dfr-text); margin-bottom: 6px; }
    .empty-state p  { font-size: 12px; color: var(--dfr-faint); }

    /* ── Spinner ─────────────────────────────────────── */
    .spinner { width: 28px; height: 28px; border: 3px solid var(--dfr-border-strong); border-top-color: var(--dfr-accent); border-radius: 50%; animation: spin 0.7s linear infinite; margin: 0 auto 12px; }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Toast ─────────────────────────────────────────── */
    #toast { position: fixed; bottom: 24px; left: 16px; right: 16px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; pointer-events: none; }
    .toast-item { padding: 12px 16px; border-radius: 14px; font-size: 13px; font-weight: 600; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px; animation: slideUp 0.3s ease; }
    .toast-error { background: #991b1b; }
    .toast-info  { background: #1e40af; }
    .toast-success { background: #166534; }
    @keyframes slideUp { from { transform: translateY(16px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

    /* ── New Request form ──────────────────────────────── */
    .nr-card { background: var(--dfr-surface); border-radius: 16px; border: 1px solid var(--dfr-border); box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 14px; overflow: hidden; }
    .nr-head { padding: 12px 14px; background: var(--dfr-brand); color: var(--dfr-on-brand); display: flex; align-items: center; justify-content: space-between; }
    .nr-head h3 { font-size: 13px; font-weight: 700; }
    .nr-body { padding: 14px; }
    .nr-type { display: flex; gap: 8px; margin-bottom: 14px; }
    .nr-type-btn { flex: 1; text-align: center; padding: 10px; border-radius: 12px; border: 1px solid var(--dfr-border-strong); background: var(--dfr-surface-2); font-size: 12px; font-weight: 700; color: var(--dfr-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; }
    .nr-type-btn.active { background: var(--dfr-active); color: #fff; border-color: var(--dfr-active); }
    .fld { margin-bottom: 12px; }
    .fld label { display: block; font-size: 11px; font-weight: 700; color: var(--dfr-text); text-transform: uppercase; margin-bottom: 5px; }
    .fld input, .fld select, .fld textarea { width: 100%; background: var(--dfr-surface); border: 1px solid var(--dfr-border-strong); border-radius: 12px; padding: 11px 12px; font-size: 14px; font-family: 'Inter', sans-serif; color: var(--dfr-text); outline: none; }
    .fld input:focus, .fld select:focus, .fld textarea:focus { border-color: var(--dfr-brand); box-shadow: 0 0 0 3px var(--dfr-border-strong); }
    .fld input[readonly] { background: var(--dfr-surface-3); color: var(--dfr-muted); }
    .fld-row { display: flex; gap: 8px; }
    .avail-box { font-size: 11px; border-radius: 12px; padding: 10px 12px; margin-bottom: 12px; }
    .avail-ok  { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .avail-no  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
    .nr-btn { width: 100%; background: var(--dfr-brand-grad); color: var(--dfr-on-brand); border: 1px solid var(--dfr-border-strong); border-radius: 14px; padding: 14px; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
    .nr-btn:disabled { opacity: .6; }
    .nr-mini-btn { background: var(--dfr-brand); color: var(--dfr-on-brand); border: none; border-radius: 12px; padding: 0 16px; font-size: 12px; font-weight: 700; cursor: pointer; flex-shrink: 0; }

    /* ── Digital file (Doc-Ware) inline cover card ─────── */
    .dig-empty { background: var(--dfr-surface-2); border: 1px solid var(--dfr-border); border-radius: 12px; padding: 12px; margin-bottom: 12px; font-size: 11px; color: var(--dfr-muted); display: flex; align-items: center; gap: 7px; }
    .dig-cover { display: flex; align-items: center; gap: 12px; background: var(--dfr-surface-2); border: 1px solid var(--dfr-border); border-radius: 14px; padding: 12px; margin-bottom: 12px; cursor: pointer; transition: border-color .15s, transform .05s; }
    .dig-cover:active { transform: scale(.99); }
    .dig-cover:hover { border-color: var(--dfr-active); }
    /* Stacked-pages thumbnail look */
    .dig-cover-stack { position: relative; width: 62px; height: 80px; flex-shrink: 0; }
    .dig-cover-stack::before, .dig-cover-stack::after { content: ''; position: absolute; inset: 0; border-radius: 9px; background: var(--dfr-surface); border: 1px solid var(--dfr-border-strong); }
    .dig-cover-stack::before { transform: translate(6px, 6px); opacity: .55; }
    .dig-cover-stack::after  { transform: translate(3px, 3px); opacity: .8; }
    .dig-cover-page { position: absolute; inset: 0; border-radius: 9px; overflow: hidden; background: var(--dfr-surface); border: 1px solid var(--dfr-border-strong); display: flex; align-items: center; justify-content: center; z-index: 1; }
    .dig-cover-page img { width: 100%; height: 100%; object-fit: cover; }
    .dig-cover-page i { font-size: 26px; color: var(--dfr-accent); }
    .dig-cover-meta { flex: 1; min-width: 0; }
    .dig-cover-title { font-size: 13px; font-weight: 800; color: var(--dfr-text); display: flex; align-items: center; gap: 6px; }
    .dig-cover-title i { color: var(--dfr-accent); }
    .dig-cover-sub { font-size: 11px; color: var(--dfr-muted); margin-top: 3px; }
    .dig-cover-cta { font-size: 10.5px; font-weight: 700; color: var(--dfr-active); margin-top: 7px; display: inline-flex; align-items: center; gap: 5px; }
    .dig-cover-chev { color: var(--dfr-faint); font-size: 16px; flex-shrink: 0; }

    /* ── Fullscreen gallery viewer ─────────────────────── */
    .dig-viewer { position: fixed; inset: 0; z-index: 10001; background: #0b0b0f; display: none; flex-direction: column; }
    .dig-viewer.open { display: flex; }
    .dig-viewer-bar { display: flex; align-items: center; gap: 10px; padding: 14px; color: #fff; flex-shrink: 0; background: linear-gradient(to bottom, rgba(0,0,0,.6), transparent); }
    .dig-viewer-bar .dig-vtitle { flex: 1; min-width: 0; }
    .dig-viewer-bar .dig-vname { font-size: 13px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .dig-viewer-bar .dig-vcount { font-size: 11px; opacity: .7; margin-top: 1px; }
    .dig-viewer-bar button { background: rgba(255,255,255,0.14); border: none; color: #fff; width: 38px; height: 38px; border-radius: 50%; font-size: 15px; cursor: pointer; flex-shrink: 0; }
    .dig-viewer-bar button:active { background: rgba(255,255,255,0.28); }
    .dig-stage { flex: 1; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; touch-action: pan-y; }
    .dig-stage img { max-width: 100%; max-height: 100%; object-fit: contain; user-select: none; -webkit-user-drag: none; }
    .dig-stage iframe { width: 100%; height: 100%; border: none; background: #fff; }
    .dig-stage .dig-spin { position: absolute; color: rgba(255,255,255,.8); font-size: 26px; }
    /* Big tap zones for prev / next on the sides */
    .dig-nav { position: absolute; top: 0; bottom: 0; width: 26%; display: flex; align-items: center; z-index: 2; border: none; background: transparent; color: rgba(255,255,255,.85); font-size: 20px; cursor: pointer; }
    .dig-nav:disabled { opacity: 0; pointer-events: none; }
    .dig-nav .dig-nav-ic { width: 40px; height: 40px; border-radius: 50%; background: rgba(0,0,0,.35); display: flex; align-items: center; justify-content: center; }
    .dig-nav.prev { left: 0; justify-content: flex-start; padding-left: 10px; }
    .dig-nav.next { right: 0; justify-content: flex-end; padding-right: 10px; }
    /* Lazy thumbnail filmstrip */
    .dig-strip { flex-shrink: 0; display: flex; gap: 6px; padding: 8px 10px calc(8px + env(safe-area-inset-bottom)); overflow-x: auto; background: rgba(0,0,0,.55); -webkit-overflow-scrolling: touch; }
    .dig-strip::-webkit-scrollbar { height: 0; }
    .dig-strip-item { position: relative; flex: 0 0 auto; width: 46px; height: 60px; border-radius: 6px; overflow: hidden; border: 2px solid transparent; background: #1b1f29; cursor: pointer; display: flex; align-items: center; justify-content: center; }
    .dig-strip-item.active { border-color: #818cf8; }
    .dig-strip-item img { width: 100%; height: 100%; object-fit: cover; }
    .dig-strip-item i { color: rgba(255,255,255,.5); font-size: 14px; }
    .dig-strip-item .dig-strip-n { position: absolute; bottom: 0; left: 0; right: 0; font-size: 8px; font-weight: 700; text-align: center; background: rgba(0,0,0,.6); color: #fff; }

    /* ── Tabs ──────────────────────────────────────────── */
    .dfr-tabs { display: flex; gap: 6px; background: var(--dfr-surface); padding: 8px 14px; border-bottom: 1px solid var(--dfr-border); flex-shrink: 0; }
    .dfr-tab { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; border: none; background: var(--dfr-surface-2); color: var(--dfr-muted); font-size: 12.5px; font-weight: 700; border-radius: 12px; cursor: pointer; font-family: 'Inter', sans-serif; }
    .dfr-tab.active { background: var(--dfr-active); color: #fff; }

    /* ── Select2 to match .fld inputs ──────────────────── */
    .select2-container { width: 100% !important; }
    .select2-container--default .select2-selection--single { height: 46px; background: var(--dfr-surface); border: 1px solid var(--dfr-border-strong); border-radius: 12px; display: flex; align-items: center; padding: 0 12px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { color: var(--dfr-text); font-size: 14px; line-height: normal; padding: 0; }
    .select2-container--default .select2-selection--single .select2-selection__placeholder { color: var(--dfr-faint); }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 44px; }
    .select2-dropdown { background: var(--dfr-surface); border: 1px solid var(--dfr-border-strong); border-radius: 12px; }
    .select2-results__option { color: var(--dfr-text); }
    .select2-search--dropdown .select2-search__field { background: var(--dfr-surface-2); color: var(--dfr-text); }
    .select2-container--default .select2-results__option--highlighted[aria-selected] { background: var(--dfr-active); color: #fff; }
    .select2-search--dropdown .select2-search__field { border-radius: 8px; }
  </style>
</head>
<body>
@include('mobile.partials.preloader')
<div id="app">

  {{-- Header --}}
  <div class="dfr-header">
    <div class="dfr-header-top">
      <button class="back-btn" onclick="window.location='{{ route('mobile.dashboard') }}'">
        <i class="fas fa-arrow-left"></i>
      </button>
      <div class="header-title">
        <h1>Digital File Request</h1>
        <p>e-Registry · Quick Search</p>
      </div>
      <div class="header-avatar">{{ strtoupper(substr($user->first_name ?? $user->name ?? 'U', 0, 1)) }}</div>
    </div>
  </div>

  {{-- Tabs --}}
  <div class="dfr-tabs">
    <button type="button" class="dfr-tab active" data-tab="search" onclick="setTab('search')"><i class="fas fa-magnifying-glass"></i> Quick Search</button>
    <button type="button" class="dfr-tab" data-tab="list" onclick="setTab('list')"><i class="fas fa-list-alt"></i> My Requests</button>
  </div>

  {{-- Panels --}}
  <div class="main-scroll">

    {{-- Quick Search panel (default) --}}
    <div id="tab-search" class="dfr-panel">
      <div class="nr-card">
        <div class="nr-body">
          <div class="nr-type">
            <div class="nr-type-btn active" data-type="Physical" onclick="setReqType('Physical')"><i class="fas fa-box"></i> Physical File</div>
            <div class="nr-type-btn" data-type="Digital" onclick="setReqType('Digital')"><i class="fas fa-desktop"></i> Digital Access</div>
          </div>

          <div class="fld">
            <label>File Number *</label>
            <select id="nrFileNo" style="width:100%"></select>
            <p id="nrCheckHint" style="font-size:10px;color:var(--dfr-faint);margin-top:5px;">Search and select a file number — its location is checked automatically.</p>
          </div>

          <div id="nrAvail"></div>

          {{-- Inline digital file (Doc-Ware) preview for the searched file --}}
          <div id="nrDigital"></div>

          <div class="fld">
            <label>File Title</label>
            <input type="text" id="nrFileTitle" placeholder="Auto-filled after location check" autocomplete="off" readonly>
          </div>

          <div class="fld" id="nrDestWrap">
            <label>Send to Office @unless($isSuperAdmin ?? false)*@endunless</label>
            <select id="nrDestOffice" onchange="updateSubmitState()">
              <option value="">{{ ($isSuperAdmin ?? false) ? 'Select destination office (optional)…' : 'Select destination office…' }}</option>
            </select>
          </div>

          <div class="fld">
            <label>Remarks</label>
            <textarea id="nrRemarks" rows="2" placeholder="Reason for the request (optional)"></textarea>
          </div>

          <button class="nr-btn" id="nrSubmit" onclick="submitRequest()" disabled><i class="fas fa-search"></i> Run File Location Check First</button>
        </div>
      </div>
    </div>

    {{-- My Requests panel --}}
    <div id="tab-list" class="dfr-panel" style="display:none;">
      <div class="toolbar" style="border-radius:14px;border:1px solid #f3f4f6;margin-bottom:12px;padding:10px 14px;">
        <span class="toolbar-title"><i class="fas fa-list-alt"></i> My Requests</span>
        <button class="refresh-btn" onclick="loadMyRequests()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
      <div id="requests-list">
        <div class="empty-state">
          <div class="spinner"></div>
          <p style="font-size:12px; color:var(--dfr-faint);">Loading your requests…</p>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- Bottom navigation — links back to the dashboard screens; DFR is the active tab --}}
<nav class="dfr-nav">
  <a href="{{ route('mobile.dashboard') }}"><i class="fas fa-house"></i><span>Home</span></a>
  <a href="{{ route('mobile.dashboard') }}#files"><i class="fas fa-magnifying-glass"></i><span>Search</span></a>
  @if(!empty($isOfs))
  <a href="{{ route('mobile.dashboard') }}#myreq"><i class="fas fa-list-check"></i><span>My FRs</span></a>
  @endif
  @if(!empty($isScbMonitor))
  <a href="{{ route('mobile.dashboard') }}#requests"><i class="fas fa-clipboard-list"></i><span>FSR</span></a>
  @endif
  <a href="{{ route('mobile.dashboard') }}#scanner"><i class="fas fa-qrcode"></i><span>Scan</span></a>
  <a href="{{ route('mobile.digital-request') }}" class="active"><i class="fas fa-paper-plane"></i><span>DFR</span></a>
  <a href="{{ route('mobile.dashboard') }}#profile"><i class="fas fa-user"></i><span>Profile</span></a>
</nav>

<div id="toast"></div>

{{-- Fullscreen digital-file gallery viewer --}}
<div class="dig-viewer" id="digViewer">
  <div class="dig-viewer-bar">
    <div class="dig-vtitle">
      <div class="dig-vname" id="digVName">—</div>
      <div class="dig-vcount" id="digVCount">—</div>
    </div>
    <button onclick="closeDigViewer()" title="Close"><i class="fas fa-times"></i></button>
  </div>
  <div class="dig-stage" id="digStage">
    <button class="dig-nav prev" id="digNavPrev" onclick="digPrev()" title="Previous"><span class="dig-nav-ic"><i class="fas fa-chevron-left"></i></span></button>
    <div class="dig-spin" id="digStageInner"></div>
    <button class="dig-nav next" id="digNavNext" onclick="digNext()" title="Next"><span class="dig-nav-ic"><i class="fas fa-chevron-right"></i></span></button>
  </div>
  <div class="dig-strip" id="digStrip"></div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const BASE = window.location.origin;
const IS_SUPER_ADMIN = @json($isSuperAdmin ?? false);

async function loadMyRequests() {
  const list = document.getElementById('requests-list');
  list.innerHTML = '<div class="empty-state"><div class="spinner"></div><p style="font-size:12px;color:var(--dfr-faint);">Loading…</p></div>';

  try {
    const res  = await fetch(`${BASE}/digital-request/list?mine=1&with_location=1`, {
      headers: { 'X-CSRF-TOKEN': CSRF }
    });
    const data = await res.json();

    if (!res.ok) {
      throw new Error(data.error || `Server error ${res.status}`);
    }

    const rows = data.data || data.rows || (Array.isArray(data) ? data : []);

    if (!rows.length) {
      list.innerHTML = `<div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No requests yet</h3>
        <p>Requests you submit will appear here</p>
      </div>`;
      return;
    }

    const statusMap = {
      'Pending':    'status-pending',
      'Approved':   'status-approved',
      'Rejected':   'status-rejected',
      'In Transit': 'status-transit',
    };

    list.innerHTML = rows.map(r => {
      const typeClass  = r.request_type === 'Digital' ? 'req-type-digital' : 'req-type-physical';
      const statusClass = statusMap[r.request_status] || 'status-pending';
      const date = (r.requested_at || r.created_at || '').substring(0, 10);
      return `<div class="req-card">
        <div class="req-card-top" style="justify-content:flex-end;">
          <span class="req-type-badge ${typeClass}">${r.request_type || 'Physical'}</span>
        </div>
        <div class="req-body">
          <div class="req-file">${r.file_no || '—'}</div>
          ${r.file_title ? `<div class="req-title">${r.file_title}</div>` : ''}
          ${r.remarks    ? `<div class="req-remarks">"${r.remarks.substring(0,70)}${r.remarks.length>70?'…':''}"</div>` : ''}
          <div class="req-status-row">
            <span class="status-pill ${statusClass}">${r.request_status || 'Pending'}</span>
            <span class="req-date">${date}</span>
          </div>
          ${renderLocationLine(r.location)}
        </div>
      </div>`;
    }).join('');

  } catch (e) {
    list.innerHTML = `<div class="empty-state">
      <i class="fas fa-exclamation-circle"></i>
      <h3>Could not load requests</h3>
      <p style="color:#dc2626;font-size:11px;margin-top:6px;">${e.message || 'Unknown error'}</p>
    </div>`;
    showToast('Failed to load requests', 'error');
  }
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

// Default location line under each request: Registry / Archive (+ rack/shelf).
function renderLocationLine(loc) {
  if (!loc) {
    return `<div class="req-loc"><i class="fas fa-map-marker-alt"></i><span>Location: not yet determined</span></div>`;
  }
  const isArchive = loc.status === 'IN_ARCHIVE';
  const isTransit = loc.status === 'IN_TRANSIT';
  const cls = isArchive ? 'is-archive' : (isTransit ? 'is-transit' : '');

  const parts = [];
  if (loc.registry) {
    parts.push(esc(loc.registry));
    // Files with a registry (Archive / In Transit) always show a rack/shelf slot —
    // placeholder "—" when none is on record.
    parts.push('Rack/Shelf ' + (loc.rack_shelf ? esc(loc.rack_shelf) : '—'));
  } else if (loc.rack_shelf) {
    parts.push('Rack/Shelf ' + esc(loc.rack_shelf));
  }
  // Fall back to the resolver's composed location when we have no registry/shelf detail.
  const detail = parts.length ? parts.join(' · ')
               : (loc.current_location ? esc(loc.current_location) : '');

  return `<div class="req-loc ${cls}">
    <i class="fas fa-map-marker-alt"></i>
    <span><span class="req-loc-label">${esc(loc.label || 'Registry')}</span>${detail ? ' · ' + detail : ''}</span>
  </div>`;
}

function showToast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast-item toast-${type}`;
  const icon = type === 'error' ? 'times-circle' : (type === 'success' ? 'check-circle' : 'info-circle');
  el.innerHTML = `<i class="fas fa-${icon}"></i> ${msg}`;
  document.getElementById('toast').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

// ─── New Request form ───────────────────────────────────────────────────────
let nrReqType = 'Physical';
let nrChecked = false;   // quick search completed for the current file number
let nrRouting = null;    // { mode:'frontdesk'|'redirect', office, officer }
let nrOfficesLoaded = false;

async function loadOffices() {
  try {
    const res = await fetch(`${BASE}/digital-request/offices-by-department`, { headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
    const offices = await res.json();
    const sel = document.getElementById('nrDestOffice');
    const placeholder = IS_SUPER_ADMIN ? 'Select destination office (optional)…' : 'Select destination office…';
    if (Array.isArray(offices) && offices.length) {
      sel.innerHTML = `<option value="">${placeholder}</option>` +
        offices.map(o => `<option value="${o.id}" data-name="${(o.office_name||'').replace(/"/g,'&quot;')}">${o.office_name}</option>`).join('');
      nrOfficesLoaded = true;
      // Auto-select the user's own office (server flags it) so the destination
      // defaults based on who is logged in. The user can still change it.
      const def = offices.find(o => o.is_default);
      if (def) { sel.value = String(def.id); updateSubmitState(); }
    } else {
      sel.innerHTML = '<option value="">No offices for your department</option>';
    }
  } catch (e) { /* non-fatal */ }
}

let dfrListLoaded = false;
function setTab(tab) {
  document.querySelectorAll('.dfr-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  document.getElementById('tab-search').style.display = tab === 'search' ? 'block' : 'none';
  document.getElementById('tab-list').style.display   = tab === 'list'   ? 'block' : 'none';
  document.querySelector('.main-scroll').scrollTop = 0;
  if (tab === 'search') {
    document.getElementById('nrFileNo').focus();
  } else if (tab === 'list') {
    if (!dfrListLoaded) { loadMyRequests(); dfrListLoaded = true; }
  }
}

function setReqType(type) {
  nrReqType = type;
  document.querySelectorAll('.nr-type-btn').forEach(b => b.classList.toggle('active', b.dataset.type === type));
  // The mandatory location check + Front-Desk routing only applies to physical files.
  const isPhysical = type === 'Physical';
  document.getElementById('nrCheckHint').style.display = isPhysical ? '' : 'none';
  // Office picker only applies to physical requests; hidden when the file is being
  // auto-redirected to its current holder (in transit).
  syncDestVisibility();
  if (!isPhysical) document.getElementById('nrAvail').innerHTML = '';
  updateSubmitState();
}

// Show the office dropdown for physical requests unless the file is in transit
// (then it auto-redirects to the last receiving officer).
function syncDestVisibility() {
  const show = nrReqType === 'Physical' && !(nrRouting && nrRouting.mode === 'redirect');
  document.getElementById('nrDestWrap').style.display = show ? 'block' : 'none';
}

// Reset the location check whenever the file number changes — keeps it mandatory & in sync.
function resetCheck() {
  nrChecked = false;
  nrRouting = null;
  document.getElementById('nrAvail').innerHTML = '';
  document.getElementById('nrDigital').innerHTML = '';
  digFiles = [];
  syncDestVisibility();
  updateSubmitState();
}

function updateSubmitState() {
  const btn = document.getElementById('nrSubmit');
  if (nrReqType === 'Digital') {
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-desktop"></i> Request Digital Access';
    return;
  }
  if (!nrChecked || !nrRouting) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-search"></i> Select a File Number';
    return;
  }
  if (nrRouting.mode === 'redirect') {
    // In transit → auto-redirect to the last receiving officer (no office picker).
    btn.disabled = false;
    btn.innerHTML = `<i class="fas fa-user-tag"></i> Send Request to ${nrRouting.officer || 'Last Receiving Officer'}`;
    return;
  }
  // Available → requester picks a destination office (Super Admins may skip it).
  const office = document.getElementById('nrDestOffice').value;
  if (!office && !IS_SUPER_ADMIN) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-building"></i> Select Destination Office';
    return;
  }
  btn.disabled = false;
  btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Request to KLAES Front-Desk &amp; SCB';
}

async function checkAvailability() {
  const fileNo = document.getElementById('nrFileNo').value.trim();
  const box = document.getElementById('nrAvail');
  if (!fileNo) { showToast('Enter a file number first', 'error'); return; }
  box.innerHTML = '<div class="avail-box avail-no">Checking file location…</div>';
  // The digital copy (Doc-Ware) exists independently of the physical location,
  // so fetch it in parallel with the location check.
  loadDigitalFiles(fileNo);
  try {
    const res = await fetch(`${BASE}/digital-request/check-availability`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify({ file_no: fileNo }),
    });
    const d = await res.json();

    if (d.found && d.file_title) document.getElementById('nrFileTitle').value = d.file_title;

    if (d.found && !d.available) {
      // File is in transit → redirect the request to the last receiving officer holding it.
      nrRouting = { mode: 'redirect', office: d.current_office || 'Unknown Office', officer: d.receiving_officer || '' };
      box.innerHTML = `<div class="avail-box avail-no"><strong><i class="fas fa-exchange-alt"></i> File In Transit</strong><br>`
        + `Currently with: <strong>${d.receiving_officer || 'Unknown Officer'}</strong>${d.current_office ? ' (' + d.current_office + ')' : ''}.<br>`
        + `This request will be redirected to the last receiving officer.</div>`;
    } else if (d.found) {
      // File located (at the registry, in its Pool Office, or archive per the range
      // engine) → requester selects the destination office.
      nrRouting = { mode: 'frontdesk' };
      const label = d.location_label || 'Available at Registry';
      const loc = d.current_office ? `<br>Location: <strong>${esc(d.current_office)}</strong>` : '';
      box.innerHTML = `<div class="avail-box avail-ok"><strong><i class="fas fa-check-circle"></i> ${esc(label)}</strong>${loc}<br>`
        + `Choose the destination office below to send the request.</div>`;
    } else {
      // No tracker record → still selectable; Front-Desk will locate the file.
      nrRouting = { mode: 'frontdesk' };
      box.innerHTML = `<div class="avail-box avail-no"><i class="fas fa-info-circle"></i> ${d.message || 'No tracker record found.'}<br>`
        + `Choose the destination office below — the Front-Desk will locate the file.</div>`;
    }
    nrChecked = true;
    syncDestVisibility();
    updateSubmitState();
  } catch (e) {
    box.innerHTML = `<div class="avail-box avail-no">Could not check file location — please retry.</div>`;
    resetCheck();
  }
}

async function submitRequest() {
  const fileNo = document.getElementById('nrFileNo').value.trim();
  if (!fileNo) { showToast('File number is required', 'error'); return; }

  let payload;
  if (nrReqType === 'Digital') {
    payload = {
      file_no: fileNo,
      file_title: document.getElementById('nrFileTitle').value.trim() || null,
      request_type: 'Digital',
      destination_office_name: 'Digital Access',
      remarks: document.getElementById('nrRemarks').value.trim() || null,
    };
  } else {
    if (!nrChecked || !nrRouting) { showToast('Run the file location check first', 'error'); return; }
    const base = {
      file_no: fileNo,
      file_title: document.getElementById('nrFileTitle').value.trim() || null,
      request_type: 'Physical',
      remarks: document.getElementById('nrRemarks').value.trim() || null,
    };
    if (nrRouting.mode === 'redirect') {
      payload = { ...base,
        destination_office_name: nrRouting.office,
        receiving_officer:       nrRouting.officer || null,
        current_file_location:   nrRouting.office,
        current_file_holder:     nrRouting.officer || null,
        is_redirected:           true };
    } else {
      const destSel  = document.getElementById('nrDestOffice');
      const destId   = destSel.value;
      const destName = destSel.options[destSel.selectedIndex]?.dataset.name || '';
      if (!destId && !IS_SUPER_ADMIN) { showToast('Select a destination office', 'error'); return; }
      payload = destId
        ? { ...base, destination_office_id: destId, destination_office_name: destName, is_redirected: false }
        : { ...base, destination_office_name: 'KLAES Front-Desk', is_redirected: false };  // Super Admin bypass
    }
  }

  const btn = document.getElementById('nrSubmit');
  const prevLabel = btn.innerHTML;
  btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';
  try {
    const res = await fetch(`${BASE}/digital-request/store`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });
    const d = await res.json();
    if (res.ok && d.success) {
      showToast(d.message || `Request ${d.request_no || ''} sent`, 'success');
      // reset
      $('#nrFileNo').val(null).trigger('change');
      document.getElementById('nrFileTitle').value = '';
      document.getElementById('nrRemarks').value = '';
      document.getElementById('nrDestOffice').value = '';
      resetCheck();
      // Jump to the list so the user sees their new request.
      loadMyRequests();
      dfrListLoaded = true;
      setTab('list');
    } else {
      const errs = d.errors ? Object.values(d.errors).flat().join(', ') : (d.message || 'Could not send request');
      showToast(errs, 'error');
      btn.disabled = false; btn.innerHTML = prevLabel;
    }
  } catch (e) {
    showToast('Network error — please try again', 'error');
    btn.disabled = false; btn.innerHTML = prevLabel;
  }
}

// File-number Select2 search — selecting a file auto-runs the location check.
$('#nrFileNo').select2({
  placeholder: 'Search file number…',
  allowClear: true,
  minimumInputLength: 2,
  width: '100%',
  ajax: {
    url: `${BASE}/digital-request/file-numbers`,
    dataType: 'json',
    delay: 250,
    data: params => ({ q: params.term }),
    processResults: data => ({ results: data.results || [] }),
    cache: true,
  },
});
// ── Digital file (Doc-Ware) inline preview ──────────────────────────────
let digFiles = [];   // [{name, ext, url}]
let digIndex = 0;
const DIG_IMG = ['jpg','jpeg','png','tif','tiff','gif','webp'];

async function loadDigitalFiles(fileNo) {
  const box = document.getElementById('nrDigital');
  box.innerHTML = `<div class="dig-empty"><i class="fas fa-spinner fa-spin"></i> Checking the digital library…</div>`;
  try {
    const res = await fetch(`${BASE}/digital-request/digital-files`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify({ file_no: fileNo }),
    });
    const d = await res.json();
    let files = (d && d.available && Array.isArray(d.files)) ? d.files : [];
    // The DB may list pages whose soft copy is missing — quietly drop those whose
    // file 404s (only definitive 404s; any other status/error keeps the page).
    if (files.length) files = await pruneMissing(files);
    digFiles = files;
    renderDigital();
  } catch (e) {
    digFiles = [];
    box.innerHTML = `<div class="dig-empty"><i class="fas fa-exclamation-circle"></i> Could not load the digital file.</div>`;
  }
}

// HEAD-probe each file; exclude only when the server clearly says 404 (missing
// soft copy). Network errors / other statuses fail open so we never wipe a file
// just because HEAD is blocked or slow.
async function pruneMissing(files) {
  const present = new Array(files.length).fill(true);
  let i = 0;
  const worker = async () => {
    while (i < files.length) {
      const idx = i++;
      try {
        const r = await fetch(files[idx].url, { method: 'HEAD' });
        if (r.status === 404) present[idx] = false;
      } catch (e) { /* keep on network error */ }
    }
  };
  await Promise.all(Array.from({ length: Math.min(8, files.length) }, worker));
  return files.filter((_, idx) => present[idx]);
}

const isImgFile = f => DIG_IMG.includes((f.ext || '').toLowerCase());

// Inline: a single cover card (no heavy grid — a file can be hundreds of pages).
function renderDigital() {
  digStripBuilt = false; // new file set → filmstrip needs rebuilding
  const box = document.getElementById('nrDigital');
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
         <div class="dig-cover-title"><i class="fas fa-images"></i> Digital File</div>
         <div class="dig-cover-sub">${n} page${n > 1 ? 's' : ''} in the digital library</div>
         <div class="dig-cover-cta"><i class="fas fa-expand"></i> Tap to open gallery</div>
       </div>
       <i class="fas fa-chevron-right dig-cover-chev"></i>
     </div>`;
}

// ── Gallery viewer ──────────────────────────────────────────────────────
let digStripBuilt = false;

function openDigViewer(i) {
  if (!digFiles.length) return;
  digIndex = Math.max(0, Math.min(i, digFiles.length - 1));
  document.getElementById('digViewer').classList.add('open');
  document.body.style.overflow = 'hidden';
  buildDigStrip();
  renderDigViewer();
}
function closeDigViewer() {
  document.getElementById('digViewer').classList.remove('open');
  document.getElementById('digStageInner').innerHTML = '';
  document.body.style.overflow = '';
}
function digNext() { if (digIndex < digFiles.length - 1) { digIndex++; renderDigViewer(); } }
function digPrev() { if (digIndex > 0) { digIndex--; renderDigViewer(); } }

function renderDigViewer() {
  const f = digFiles[digIndex];
  if (!f) return;
  const stage = document.getElementById('digStageInner');
  if (isImgFile(f)) {
    stage.innerHTML = `<i class="fas fa-spinner fa-spin dig-spin"></i>`;
    const img = new Image();
    img.alt = f.name;
    img.onload = () => { stage.innerHTML = ''; stage.appendChild(img); };
    img.onerror = () => { stage.innerHTML = `<div style="color:#fff;font-size:13px;">Could not load this page.</div>`; };
    img.src = f.url;
  } else {
    stage.innerHTML = `<iframe src="${esc(f.url)}" title="${esc(f.name)}"></iframe>`;
  }
  document.getElementById('digVName').textContent = f.name;
  document.getElementById('digVCount').textContent = `Page ${digIndex + 1} of ${digFiles.length}`;
  document.getElementById('digNavPrev').disabled = digIndex === 0;
  document.getElementById('digNavNext').disabled = digIndex === digFiles.length - 1;
  // Preload neighbours for snappy paging.
  [digIndex - 1, digIndex + 1].forEach(j => {
    const nf = digFiles[j];
    if (nf && isImgFile(nf)) { const im = new Image(); im.src = nf.url; }
  });
  // Highlight + scroll the active filmstrip thumbnail.
  const strip = document.getElementById('digStrip');
  strip.querySelectorAll('.dig-strip-item').forEach((el, j) => {
    el.classList.toggle('active', j === digIndex);
    if (j === digIndex) el.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });
  });
}

// Lazy filmstrip: thumbnails only load as they scroll into view (img loading=lazy).
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

// Keyboard navigation
document.addEventListener('keydown', (e) => {
  if (!document.getElementById('digViewer').classList.contains('open')) return;
  if (e.key === 'Escape') closeDigViewer();
  else if (e.key === 'ArrowRight') digNext();
  else if (e.key === 'ArrowLeft') digPrev();
});

// Touch swipe (left/right) on the stage
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

$('#nrFileNo').on('select2:select', function (e) {
  const title = e.params && e.params.data ? e.params.data.title : null;
  resetCheck();
  if (title) document.getElementById('nrFileTitle').value = title;
  checkAvailability();
});
$('#nrFileNo').on('select2:clear', function () {
  document.getElementById('nrFileTitle').value = '';
  resetCheck();
});

// Quick Search is the default tab; My Requests loads lazily on first open.
loadOffices();
setTab('search');
</script>
</body>
</html>
