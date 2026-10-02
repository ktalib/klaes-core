<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>SPAS Mobile · Special Assignment</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
    <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    {{-- google.maps geocoder shim (Leaflet + Nominatim under the hood). Leaflet
         is already loaded above, so skip its copy — a second one detaches
         Leaflet.draw from the first. --}}
    @include('partials.maps_scripts', ['skipLeaflet' => true])
    <style>
        :root {
            --bg:           #0b0e14;
            --surface:      #151921;
            --surface2:     #1e2530;
            --border:       rgba(255,255,255,0.08);
            --accent:       #babf0c;
            --accent-dim:   rgba(186,191,12,0.15);
            --accent-glow:  rgba(186,191,12,0.28);
            --success:      #10b981;
            --warning:      #f59e0b;
            --danger:       #ef4444;
            --text:         #f1f5f9;
            --text-muted:   #94a3b8;
            --text-dim:     #475569;
            --radius:       16px;
            --radius-sm:    10px;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; font-family: 'Plus Jakarta Sans', sans-serif; }
        html, body { height: 100%; overflow: hidden; }
        body { background: var(--bg); color: var(--text); font-size: 14px; overscroll-behavior-y: contain; }
        input[type="text"], input[type="search"], textarea { text-transform: uppercase; }
        ::-webkit-scrollbar { display: none; }

        /* ── TOP BAR ── */
        .topbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            background: rgba(11,14,20,0.97); backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 18px; padding-top: max(12px, env(safe-area-inset-top));
            height: 60px;
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; }
        .brand-icon {
            width: 34px; height: 34px; border-radius: 10px;
            background: linear-gradient(135deg, #7a7d08, #babf0c);
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 12px var(--accent-glow);
            overflow: hidden;
        }
        .brand-text h1 { font-size: 13px; font-weight: 700; letter-spacing: -.01em; }
        .brand-text p  { font-size: 10px; color: var(--text-muted); font-weight: 500; }

        /* ── NAV STRIP ── */
        .nav-strip {
            position: fixed; top: 60px; left: 0; right: 0; z-index: 199;
            background: rgba(21,25,33,0.97); backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            display: flex; padding: 10px 18px; gap: 8px;
        }
        .nav-pill {
            flex: 1; padding: 7px 10px; border-radius: 20px; cursor: pointer;
            background: var(--surface2); border: 1px solid var(--border);
            font-size: 11px; font-weight: 700; color: var(--text-muted);
            text-align: center; letter-spacing: .04em; transition: all .2s;
            display: flex; align-items: center; justify-content: center; gap: 5px;
            white-space: nowrap;
        }
        .nav-pill.active { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .nav-pill svg { width: 11px; height: 11px; }

        /* ── PAGES ── */
        .page { display: none; position: fixed; top: 112px; left: 0; right: 0; bottom: 0; overflow-y: auto; scrollbar-width: none; }
        .page.active { display: block; }
        #page-map { overflow: hidden; }

        /* ── STAT STRIP ── */
        .stat-strip { display: flex; gap: 8px; padding: 14px 18px 0; overflow-x: auto; scrollbar-width: none; }
        .stat-chip {
            flex-shrink: 0; padding: 6px 14px; border-radius: 20px;
            background: var(--surface2); border: 1px solid var(--border);
            font-size: 11px; font-weight: 700; color: var(--text-muted);
            display: flex; align-items: center; gap: 6px; white-space: nowrap;
        }
        .stat-chip span { color: var(--text); font-weight: 800; }
        .stat-chip.green  { background: rgba(16,185,129,.1); border-color: rgba(16,185,129,.25); color: var(--success); }
        .stat-chip.green span { color: var(--success); }
        .stat-chip.amber  { background: rgba(245,158,11,.1); border-color: rgba(245,158,11,.25); color: var(--warning); }
        .stat-chip.amber span { color: var(--warning); }
        .stat-chip.accent { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .stat-chip.accent span { color: var(--accent); }

        /* ── SEARCH ── */
        .search-wrap { padding: 12px 18px 6px; position: relative; }
        .search-wrap svg { position: absolute; left: 30px; top: 50%; transform: translateY(-50%); width: 15px; color: var(--text-dim); pointer-events: none; }
        .search-inp {
            width: 100%; background: var(--surface2); border: 1.5px solid var(--border);
            border-radius: 10px; padding: 10px 14px 10px 38px;
            color: var(--text); font-size: 13px; outline: none; transition: border-color .2s;
        }
        .search-inp:focus { border-color: var(--accent); }

        /* ── CARDS LIST ── */
        .card-list { padding: 8px 18px 24px; display: flex; flex-direction: column; gap: 10px; padding-bottom: max(24px, env(safe-area-inset-bottom)); }

        .rec-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
            animation: slideUp .35s ease-out both;
        }
        .rec-card-head {
            padding: 12px 14px 10px; display: flex; align-items: center;
            justify-content: space-between; border-bottom: 1px solid var(--border);
        }
        .rec-fileno { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700; color: var(--accent); }
        .rec-status { font-size: 9px; font-weight: 800; letter-spacing: .04em; padding: 3px 9px; border-radius: 99px; }
        .rec-status.open        { background: rgba(59,130,246,.15); color: #60a5fa; }
        .rec-status.in_progress { background: rgba(245,158,11,.15); color: var(--warning); }
        .rec-status.approved    { background: rgba(16,185,129,.15); color: var(--success); }
        .rec-status.closed      { background: rgba(71,85,105,.3); color: var(--text-dim); }
        .rec-status.not_added   { background: rgba(71,85,105,.2); color: #64748b; }
        .rec-card-body { padding: 10px 14px 12px; display: flex; flex-direction: column; gap: 5px; }
        .rec-row { display: flex; align-items: baseline; gap: 8px; }
        .rec-lbl  { font-size: 10px; font-weight: 700; color: #cbd5e1; text-transform: uppercase; letter-spacing: .04em; min-width: 60px; }
        .rec-val  { font-size: 12px; font-weight: 600; color: #e2e8f0; }

        .insp-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: var(--radius); overflow: hidden;
            animation: slideUp .35s ease-out both;
        }
        .insp-card-head { padding: 12px 14px 10px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); }
        .insp-fileno { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700; color: #60a5fa; }
        .insp-date { font-size: 10px; color: #94a3b8; font-weight: 600; }
        .insp-card-body { padding: 10px 14px 12px; }
        .insp-findings { font-size: 12px; color: #cbd5e1; line-height: 1.5; }
        .insp-inspector { margin-top: 6px; font-size: 10px; color: #94a3b8; font-weight: 700; text-transform: uppercase; }

        /* ── ACTION CARD ── */
        .action-card {
            background: var(--accent-dim);
            border: 2px dashed var(--accent) !important;
            transition: all .2s;
            user-select: none;
            -webkit-user-select: none;
        }
        .action-card:active {
            transform: scale(0.98);
            background: var(--accent-glow);
        }

        /* ── EMPTY STATE ── */
        .empty-state { padding: 50px 20px; text-align: center; }
        .empty-state svg { width: 40px; height: 40px; color: var(--text-dim); margin: 0 auto 12px; display: block; }
        .empty-state p { font-size: 13px; color: var(--text-dim); font-weight: 500; }

        /* ── SKELETON ── */
        .skeleton { background: var(--surface); border-radius: var(--radius); height: 88px; animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }

        /* ── MAP PAGE ── */
        #field-map { position: absolute; top: 0; left: 0; right: 0; bottom: 0; }
        .map-filter-bar {
            position: absolute; top: 8px; left: 50%; transform: translateX(-50%); z-index: 500;
            display: flex; gap: 6px; background: rgba(11,14,20,.9); border: 1px solid var(--border);
            border-radius: 20px; padding: 6px 10px; backdrop-filter: blur(12px);
        }
        .map-filter-chip {
            padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 700;
            background: var(--surface2); border: 1px solid var(--border); color: var(--text-muted);
            cursor: pointer; white-space: nowrap; transition: all .2s;
        }
        .map-filter-chip.active { background: var(--accent-dim); border-color: var(--accent); color: var(--accent); }
        .map-pt-count {
            position: absolute; bottom: 80px; right: 14px; z-index: 500;
            background: rgba(11,14,20,.85); border: 1px solid var(--border);
            border-radius: 10px; padding: 6px 12px; font-size: 11px; color: var(--text-muted); font-weight: 700;
        }

        /* ── OVERLAY ── */
        .overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 300; backdrop-filter: blur(2px); }
        .overlay.show { display: block; }

        /* ── BOTTOM SHEET ── */
        .bottom-sheet {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 400;
            background: var(--surface); border-top: 1px solid var(--border);
            border-radius: 20px 20px 0 0;
            transform: translateY(100%); transition: transform .35s cubic-bezier(.32,0,.67,0);
            max-height: 88vh; display: flex; flex-direction: column;
        }
        .bottom-sheet.open { transform: translateY(0); transition: transform .35s cubic-bezier(.17,.67,.83,.67); }
        .sheet-handle { width: 36px; height: 4px; background: var(--text-dim); border-radius: 99px; margin: 12px auto 0; flex-shrink: 0; }
        .sheet-header {
            padding: 14px 18px 12px; display: flex; align-items: center;
            justify-content: space-between; border-bottom: 1px solid var(--border); flex-shrink: 0;
        }
        .sheet-title { font-size: 14px; font-weight: 700; }
        .sheet-close { width: 30px; height: 30px; border-radius: 50%; background: var(--surface2); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .sheet-close svg { width: 14px; height: 14px; color: var(--text-muted); }
        .sheet-body { flex: 1; overflow-y: auto; padding: 16px 18px; display: flex; flex-direction: column; gap: 14px; padding-bottom: max(24px, env(safe-area-inset-bottom)); }

        /* ── FORM ELEMENTS ── */
        .field { display: flex; flex-direction: column; gap: 5px; }
        .field label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #e2e8f0; }
        .field label .req { color: var(--danger); }
        .inp {
            width: 100%; background: var(--surface2); border: 1.5px solid var(--border);
            border-radius: var(--radius-sm); padding: 11px 13px; color: var(--text);
            font-size: 13px; font-weight: 500; outline: none; transition: all .2s;
        }
        .inp:focus { border-color: var(--accent); background: var(--bg); box-shadow: 0 0 0 3px var(--accent-glow); }
        .inp:disabled { opacity: .55; cursor: not-allowed; font-family: 'JetBrains Mono', monospace; font-size: 12px; }
        .inp-ro { background: rgba(255,255,255,.03); color: var(--text-muted); cursor: default; font-family: 'JetBrains Mono', monospace; font-size: 12px; }
        select.inp { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; }
        select.inp option { background: var(--surface2); }
        textarea.inp { resize: none; min-height: 80px; line-height: 1.5; }
        .inp-row { display: flex; gap: 8px; }
        .inp-row .inp { flex: 1; }

        .lookup-msg { font-size: 11px; font-weight: 600; margin-top: 3px; }
        .lookup-msg.ok  { color: var(--success); }
        .lookup-msg.err { color: var(--warning); }
        .fileno-item {
            padding: 10px 14px; font-size: 12px; cursor: pointer;
            border-bottom: 1px solid var(--border);
            display: flex; flex-direction: column; gap: 2px;
            transition: background .15s;
        }
        .fileno-item:last-child { border-bottom: none; }
        .fileno-item:active { background: var(--accent-dim); }
        .fileno-item .fi-num { font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 700; color: var(--accent); }
        .fileno-item .fi-title { font-size: 11px; color: var(--text-muted); }

        .info-badge {
            display: flex; align-items: flex-start; gap: 8px;
            padding: 10px 12px; background: rgba(59,130,246,.07);
            border: 1px solid rgba(59,130,246,.2); border-radius: var(--radius-sm);
            font-size: 11px; color: #93c5fd; font-weight: 500; line-height: 1.5;
        }
        .info-badge svg { width: 13px; flex-shrink: 0; margin-top: 1px; }

        .btn-geo {
            flex-shrink: 0; background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.25);
            color: #60a5fa; padding: 0 12px; border-radius: var(--radius-sm);
            font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 5px; cursor: pointer;
        }
        .btn-geo svg { width: 13px; }

        .coord-badge {
            margin-top: 5px; padding: 7px 11px; background: var(--surface2); border: 1px solid var(--border);
            border-radius: 8px; font-family: 'JetBrains Mono', monospace; font-size: 10px; color: var(--text-muted); text-align: center;
        }

        #mini-map, #add-mini-map { height: 160px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); margin-top: 5px; }

        .save-btn {
            width: 100%; height: 50px; border-radius: var(--radius-sm);
            background: var(--accent); color: #0b0e14; font-size: 14px; font-weight: 800;
            border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 20px var(--accent-glow); transition: all .2s; margin-top: 4px;
        }
        .save-btn:active { transform: scale(.97); }
        .save-btn:disabled { opacity: .5; cursor: not-allowed; }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            display: none !important;
        }
        .btn-nav { flex: 1; height: 46px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 13px; font-weight: 700; border: none; cursor: pointer; transition: all .2s; }
        .btn-nav:active { transform: scale(.97); }
        .btn-ghost   { background: var(--surface2); color: var(--text-muted); border: 1px solid var(--border); }
        .btn-primary { background: var(--accent); color: #0b0e14; box-shadow: 0 3px 14px var(--accent-glow); }
        .btn-nav svg { width: 15px; height: 15px; }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%) translateY(12px);
            background: var(--surface2); border: 1px solid var(--border);
            padding: 10px 20px; border-radius: 99px; font-size: 12px; font-weight: 700;
            color: var(--text); z-index: 600; display: flex; align-items: center; gap: 8px;
            opacity: 0; pointer-events: none; transition: all .3s cubic-bezier(.175,.885,.32,1.275);
            box-shadow: 0 8px 24px rgba(0,0,0,.5); white-space: nowrap; max-width: 90vw;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
        .toast.success { border-color: rgba(16,185,129,.4); }
        .toast.error   { border-color: rgba(239,68,68,.4); }
        .toast svg { width: 14px; height: 14px; flex-shrink: 0; }

        /* ── ANIMATIONS ── */
        @keyframes slideUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeIn  { from { opacity:0; } to { opacity:1; } }
    </style>
</head>
<body>

<!-- TOP BAR -->
<header class="topbar">
    <div class="topbar-brand">
        <div class="brand-icon">
            <img src="{{ asset('storage/upload/logo/logo.png') }}" style="width:22px;height:22px;object-fit:contain;"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='block';">
            <i data-lucide="layers" style="width:16px;color:#0b0e14;display:none;"></i>
        </div>
        <div class="brand-text">
            <h1>SPAS Mobile</h1>
            <p>Special Assignment</p>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;">
       
        <form method="POST" action="{{ route('special-assignment.mobile.logout') }}" style="margin:0;">
            @csrf
            <button type="submit" style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:#ef4444;padding:7px 12px;border-radius:9px;font-size:11px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:6px;">
                <i data-lucide="log-out" style="width:13px;"></i> Logout
            </button>
        </form>
    </div>
</header>

<!-- NAV STRIP -->
<nav class="nav-strip">
    <div class="nav-pill active" data-page="records">
        <i data-lucide="file-text"></i> RECORDS
    </div>
    <div class="nav-pill" data-page="verify">
        <i data-lucide="search"></i>FIELD RECORDS
    </div>
    <div class="nav-pill" data-page="map">
        <i data-lucide="map"></i> FIELD MAP
    </div>
</nav>

<!-- PAGE: RECORDS -->
<div id="page-records" class="page active">
    <div class="stat-strip" id="records-stats">
        <div class="stat-chip accent"><span id="stat-total">—</span> Total</div>
        <div class="stat-chip green"><span id="stat-open">—</span> Open</div>
        <div class="stat-chip amber"><span id="stat-inprog">—</span> In Progress</div>
    </div>
    <div class="search-wrap">
        <i data-lucide="search"></i>
        <input type="search" id="search-records" class="search-inp" placeholder="Search file no or owner…">
    </div>
    <div class="card-list" id="records-list">
        <div class="skeleton"></div>
        <div class="skeleton" style="height:72px;animation-delay:.1s"></div>
        <div class="skeleton" style="height:80px;animation-delay:.2s"></div>
    </div>
</div>

<!-- PAGE: VERIFY (Field Inspections) -->
<div id="page-verify" class="page">
    <div class="search-wrap">
        <i data-lucide="search"></i>
        <input type="search" id="search-verify" class="search-inp" placeholder="Search file no…">
    </div>
    <div class="card-list" id="verify-list">
        <div class="skeleton"></div>
        <div class="skeleton" style="animation-delay:.1s"></div>
        <div class="skeleton" style="height:72px;animation-delay:.2s"></div>
    </div>
</div>

<!-- PAGE: FIELD MAP -->
<div id="page-map" class="page">
    <div id="field-map"></div>
    <div class="map-filter-bar" id="map-filter-bar">
        <div class="map-filter-chip active" data-lu="all">ALL</div>
        <div class="map-filter-chip" data-lu="RESIDENTIAL">RES</div>
        <div class="map-filter-chip" data-lu="COMMERCIAL">COM</div>
        <div class="map-filter-chip" data-lu="AGRICULTURAL">AGR</div>
        <div class="map-filter-chip" data-lu="INDUSTRIAL">IND</div>
    </div>
    <div class="map-pt-count"><span id="map-count">{{ count($mapPoints) }}</span> points</div>
</div>

<!-- OVERLAY -->
<div id="overlay" class="overlay"></div>

<!-- BOTTOM SHEET: ADD RECORD -->
<div id="sheet-add-record" class="bottom-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <span class="sheet-title">Add Land Record</span>
        <button class="sheet-close" id="close-add-record"><i data-lucide="x"></i></button>
    </div>
    <div class="sheet-body">
        <form id="form-add-record" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="file_number"      id="h-file_number">
            <input type="hidden" name="file_indexing_id" id="h-file_indexing_id">
            <input type="hidden" name="tracking_id"      id="h-tracking_id">
            <input type="hidden" name="is_indexed"       id="h-is_indexed" value="0">
            <input type="hidden" name="location"         id="h-location">
            {{-- Statutory path only — disabled for customary, where the #m-lga /
                 #m-district selects carry these same names. --}}
            <input type="hidden" name="lga"              id="h-lga">
            <input type="hidden" name="district"         id="h-district">
            <input type="hidden" name="land_title_type"  id="m-land-title-type" value="statutory">

            <div class="field">
                <label>Land Title Type <span class="req">*</span></label>
                <div id="m-ltt-toggle" style="display:flex;gap:8px;">
                    <button type="button" class="ltt-btn active" data-type="statutory"
                        style="flex:1;padding:10px;border-radius:var(--radius-sm);border:1.5px solid var(--accent);background:var(--accent-dim);color:var(--accent);font-size:11px;font-weight:800;letter-spacing:.04em;cursor:pointer;">STATUTORY</button>
                    <button type="button" class="ltt-btn" data-type="customary"
                        style="flex:1;padding:10px;border-radius:var(--radius-sm);border:1.5px solid var(--border);background:var(--surface2);color:var(--text-muted);font-size:11px;font-weight:800;letter-spacing:.04em;cursor:pointer;">CUSTOMARY</button>
                </div>
            </div>

            <div class="field" style="margin-bottom:2px;position:relative;">
                <label>File Number <span class="req">*</span></label>
                <div style="position:relative;">
                    <input type="search" id="m-file-no-input" class="inp" placeholder="Type to search file number…"
                        style="text-transform:uppercase;padding-right:38px;" autocomplete="off">
                    <span id="fileno-spinner" style="display:none;position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--accent);font-size:11px;">…</span>
                </div>
                <!-- Dropdown list -->
                <div id="fileno-dropdown" style="display:none;position:absolute;left:0;right:0;z-index:999;
                    background:var(--surface2);border:1.5px solid var(--accent);border-radius:var(--radius-sm);
                    max-height:220px;overflow-y:auto;margin-top:4px;box-shadow:0 8px 24px rgba(0,0,0,.5);">
                </div>
                <p id="lookup-msg" class="lookup-msg" style="display:none;"></p>
            </div>

            <div class="field">
                <label>Owner / File Title <span class="req">*</span></label>
                <input type="text" name="owner_name" id="m-owner" class="inp inp-ro" readonly placeholder="Auto-filled after lookup">
            </div>

            <div id="m-location-badge" class="info-badge" style="display:none;">
                <i data-lucide="map-pin"></i>
                <span id="m-location-text"></span>
            </div>

            {{-- Customary Title only: no indexed file to inherit an address from,
                 so LGA + District are picked here and drive the map pin. Mirrors
                 the desktop Add Land Record card. --}}
            <div id="m-customary-geo" style="display:none;">
                <div class="field">
                    <label>LGA <span class="req">*</span></label>
                    <select name="lga" id="m-lga" class="inp" disabled>
                        <option value="">Select LGA…</option>
                        @foreach($lgaOptions as $lga)
                            <option value="{{ $lga }}">{{ $lga }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>District</label>
                    {{-- Fetched on demand: 1,818 districts is far too many to inline --}}
                    <select name="district" id="m-district" class="inp" disabled>
                        <option value="">Loading districts…</option>
                    </select>
                </div>
            </div>

            {{-- Auto-filled from the file for a statutory title; picked from a
                 dropdown for a customary one. Only the active control is enabled,
                 so only one posts land_use_type. --}}
            <div class="field">
                <label>General Landuse (Observed around) - Special</label>
                <input type="text" name="land_use_type" id="m-land-use" class="inp inp-ro" readonly placeholder="Auto-filled">
                <select name="land_use_type" id="m-land-use-select" class="inp" style="display:none;" disabled>
                    <option value="">Select…</option>
                    @foreach($customaryLandUses as $lut)
                        <option value="{{ $lut }}">{{ $lut }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Approved Land Use <span class="req">*</span></label>
                <select name="proposed_use" id="m-approved-use" required class="inp">
                    <option value="">Select…</option>
                    @foreach($landUseTypes as $lut)
                        <option value="{{ $lut }}">{{ $lut }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Prevailing Land Use <span class="req">*</span></label>
                <select name="existing_use" id="m-existing-use" required class="inp">
                    <option value="">Select…</option>
                    @foreach($landUseTypes as $lut)
                        <option value="{{ $lut }}">{{ $lut }}</option>
                    @endforeach
                </select>
                <div id="m-contravening-badge" style="display:none;margin-top:8px;">
                    <span style="display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:99px;background:rgba(239,68,68,.15);border:1.5px solid rgba(239,68,68,.5);color:#ef4444;font-size:12px;font-weight:900;letter-spacing:.06em;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        CONTRAVENTION
                    </span>
                </div>
            </div>

            <div class="field">
                <label>Phone</label>
                <input type="text" name="phone" id="m-phone" class="inp" placeholder="08012345678" style="text-transform:none;">
            </div>

            <div class="field">
                <label>Property Photos</label>
                <label for="m-photos" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;padding:16px 12px;border:2px dashed var(--border);border-radius:var(--radius-sm);background:var(--surface2);cursor:pointer;transition:border-color .2s;">
                    <i data-lucide="image-plus" style="width:22px;height:22px;color:var(--text-dim);"></i>
                    <span style="font-size:11px;color:var(--text-muted);">Tap to upload photos</span>
                    <input type="file" id="m-photos" name="photos[]" multiple accept="image/*" capture="environment" style="display:none;">
                </label>
                <div id="m-photo-preview" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>
            </div>

            <div style="border-top:1px solid var(--border);margin:4px 0 2px;padding-top:14px;">
                <label style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--accent);">Field Inspection</label>
                <p style="font-size:10px;color:var(--text-dim);margin-top:2px;text-transform:none;">On-site now? Log the inspection below along with the record. Otherwise leave blank and log it later.</p>
            </div>

            <div class="field">
                <label>Inspection Date</label>
                <input type="date" id="m-add-inspection-date" class="inp">
            </div>

            <div class="field">
                <label>Coordinates</label>
                <div class="inp-row">
                    <input type="text" id="m-add-coords-display" class="inp" placeholder="12.000000,8.519000" style="text-transform:none;">
                    <button type="button" id="btn-add-gps" class="btn-geo" style="height:46px;">
                        <i data-lucide="navigation" style="width:14px;"></i> GPS
                    </button>
                </div>
                <input type="hidden" id="m-add-coords-hidden">
                <input type="hidden" id="m-add-geometry">
                <div id="add-mini-map"></div>
                <div style="font-size:10px;color:var(--text-dim);margin-top:4px;">Tap the map to drop a pin, or use the polygon tool to trace the plot boundary.</div>
            </div>

            <div class="field">
                <label>Findings</label>
                <textarea id="m-add-findings" class="inp" rows="3" placeholder="DESCRIBE FINDINGS ON GROUND…" style="text-transform:none;"></textarea>
            </div>

            <button type="submit" class="save-btn" id="btn-save-record">
                <i data-lucide="save" style="width:16px;"></i> SAVE RECORD
            </button>
        </form>
    </div>
</div>

<!-- BOTTOM SHEET: LOG INSPECTION -->
<div id="sheet-log-inspect" class="bottom-sheet">
    <div class="sheet-handle"></div>
    <div class="sheet-header">
        <span class="sheet-title">Log Field Inspection</span>
        <button class="sheet-close" id="close-log-inspect"><i data-lucide="x"></i></button>
    </div>
    <div class="sheet-body">
        <form id="form-log-inspect" enctype="multipart/form-data">
            @csrf

            <div class="field" style="position:relative;">
                <label>Linked Application <span class="req">*</span></label>
                <input type="search" id="m-app-search" class="inp" placeholder="Type file no or owner to search…" style="text-transform:none;" autocomplete="off">
                <input type="hidden" name="spa_application_id" id="m-app-id">
                <input type="hidden" name="file_number"        id="m-insp-fileno">
                <!-- Dropdown list -->
                <div id="app-dropdown" style="display:none;position:absolute;left:0;right:0;z-index:999;
                    background:var(--surface2);border:1.5px solid var(--accent);border-radius:var(--radius-sm);
                    max-height:220px;overflow-y:auto;margin-top:4px;box-shadow:0 8px 24px rgba(0,0,0,.5);">
                </div>
            </div>

            <div class="field">
                <label>Inspection Date <span class="req">*</span></label>
                <input type="date" name="inspection_date" required class="inp" value="{{ now()->toDateString() }}">
            </div>

            <div class="field">
                <label>Coordinates</label>
                <div class="inp-row">
                    <input type="text" id="m-coords-display" class="inp" placeholder="12.000000,8.519000" style="text-transform:none;">
                    <button type="button" id="btn-gps" class="btn-geo" style="height:46px;">
                        <i data-lucide="navigation" style="width:14px;"></i> GPS
                    </button>
                </div>
                <input type="hidden" name="coordinates" id="m-coords-hidden">
                <input type="hidden" name="parcel_geometry" id="m-geometry">
                <div id="mini-map"></div>
                <div style="font-size:10px;color:var(--text-dim);margin-top:4px;">Tap the map to drop a pin, or use the polygon tool to trace the plot boundary.</div>
            </div>

            <div class="field">
                <label>Findings <span class="req">*</span></label>
                <textarea name="findings" required class="inp" rows="3" placeholder="DESCRIBE FINDINGS ON GROUND…" style="text-transform:none;"></textarea>
            </div>

            <div class="field">
                <label>Photos</label>
                <input type="file" name="photos[]" multiple accept="image/*" capture="environment"
                    style="background:var(--surface2);border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:10px 12px;color:var(--text-muted);font-size:12px;width:100%;">
            </div>

            <button type="submit" class="save-btn" id="btn-save-inspect">
                <i data-lucide="check-circle" style="width:16px;"></i> SAVE INSPECTION
            </button>
        </form>
    </div>
</div>

<!-- BOTTOM NAV -->
<div class="bottom-nav" id="bottom-nav">
    <button class="btn-nav btn-ghost" id="btn-secondary">
        <i data-lucide="refresh-cw" id="secondary-icon" style="width:15px;"></i>
        <span id="secondary-label">Refresh</span>
    </button>
    <button class="btn-nav btn-primary" id="btn-primary">
        <i data-lucide="plus" id="primary-icon" style="width:15px;"></i>
        <span id="primary-label">Add Record</span>
    </button>
</div>

<!-- TOAST -->
<div id="toast" class="toast">
    <i data-lucide="check-circle" id="toast-icon" style="width:14px;"></i>
    <span id="toast-msg"></span>
</div>

<script>
const CSRF  = '{{ csrf_token() }}';
const MPTS  = @json($mapPoints);
const URLS  = {
    records:     '{{ route("special-assignment.land-records") }}',
    fieldData:   '{{ route("special-assignment.field-data") }}',
    checkFile:   '{{ route("special-assignment.check-file") }}',
    searchFiles: '{{ route("special-assignment.search-files") }}',
    storeRec:    '{{ route("special-assignment.land-records.store") }}',
    storeField:  '{{ route("special-assignment.field-data.store") }}',
    nextCustomaryFileNo: '{{ route("special-assignment.next-customary-fileno") }}',
};

// ── state ────────────────────────────────────────────────────────────────────
let currentTab  = 'records';
let allRecords  = [];
let allInspects = [];
let appMap      = {};
let leafMap     = null, leafMarkers = [];
let mapInited   = false;

// Extracts a {lat, lng} pair from free-typed text ("12.000000,8.519000", GPS
// output, etc.) instead of requiring an exact "a,b" split — a strict split
// silently let malformed text through to the server as raw text before.
function parseLatLngInput(str) {
    if (!str) return null;
    const nums = (String(str).match(/-?\d+(?:\.\d+)?/g) || []).map(Number);
    if (nums.length < 2 || nums.some(Number.isNaN)) return null;
    const [lat, lng] = nums;
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
    return { lat, lng };
}

// Reusable pin + boundary-trace map — one instance for "Log Field Inspection",
// one for the Field Inspection section of "Add Land Record", so a surveyor can
// capture the inspection in the same pass as adding the land record.
function createFieldPinMap({ mapId, coordsDisplayId, coordsHiddenId, geometryId }) {
    let map = null, marker = null, drawnItems = null, drawing = false;

    function setCoords(lat, lng) {
        const laN = parseFloat(lat), lnN = parseFloat(lng);
        document.getElementById(coordsDisplayId).value = `${laN},${lnN}`;
        document.getElementById(coordsHiddenId).value  = JSON.stringify({ lat: laN, lng: lnN });
        if (map) {
            const ll = [laN, lnN];
            if (marker) marker.setLatLng(ll); else marker = L.marker(ll).addTo(map);
            map.setView(ll, 15);
        }
    }

    function syncGeometryField() {
        const layer = drawnItems && drawnItems.getLayers()[0];
        document.getElementById(geometryId).value = layer ? JSON.stringify(layer.toGeoJSON().geometry) : '';
    }

    function reset() {
        if (marker && map) { map.removeLayer(marker); marker = null; }
        if (drawnItems) drawnItems.clearLayers();
        document.getElementById(coordsDisplayId).value = '';
        document.getElementById(coordsHiddenId).value  = '';
        document.getElementById(geometryId).value      = '';
    }

    // Drop the pin from a written address. `location` may be one string or an
    // ordered list, most specific first (district + LGA, then LGA) — the
    // geocoder rarely resolves a Kano street address, so without the broader
    // fallback the pin simply never appears. `opts.force` re-pins over an
    // existing coordinate, for when the surveyor changes the address itself.
    function geocode(location, opts = {}) {
        const hidden = document.getElementById(coordsHiddenId);
        if (opts.force && hidden) hidden.value = '';

        const candidates = (Array.isArray(location) ? location : [location])
            .map(v => (v == null ? '' : String(v)).trim())
            .filter(v => v && v !== '—')
            .filter((v, i, arr) => arr.indexOf(v) === i);

        if (!candidates.length) return;
        if (hidden && hidden.value.trim()) return;   // never overwrite a pin already set
        if (typeof google === 'undefined' || !google.maps) return;

        const geocoder = new google.maps.Geocoder();
        (function tryNext(i) {
            if (i >= candidates.length) return;
            const raw     = candidates[i];
            const address = /kano/i.test(raw) ? raw + ', Nigeria' : raw + ', Kano, Nigeria';
            geocoder.geocode({ address, region: 'NG' }, (results, status) => {
                if (hidden && hidden.value.trim()) return;   // pinned by hand meanwhile
                if (status === 'OK' && results[0]) {
                    const pos = results[0].geometry.location;
                    init();
                    setCoords(pos.lat(), pos.lng());
                    toast(i === 0 ? 'Pin placed from the address — drag to adjust.'
                                  : 'Exact address not found; pin placed on the area — drag to correct.', 'info');
                } else {
                    tryNext(i + 1);
                }
            });
        })(0);
    }

    function init() {
        if (map) { map.invalidateSize(); return; }
        map = L.map(mapId, { zoomControl: false }).setView([12.0, 8.52], 11);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution:'© Esri', maxZoom:19 }).addTo(map);
        // Tap anywhere to drop / move the pin — suppressed while the polygon draw tool is active.
        map.on('click', e => { if (!drawing) setCoords(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6)); });

        // Polygon boundary tracing (Leaflet.draw) — one plot boundary at a time.
        drawnItems = new L.FeatureGroup().addTo(map);
        map.addControl(new L.Control.Draw({
            position: 'topright',
            edit: { featureGroup: drawnItems, remove: true },
            draw: {
                polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#dc2626', weight: 3 } },
                marker: false, circlemarker: false, circle: false, polyline: false, rectangle: false,
            },
        }));
        map.on(L.Draw.Event.DRAWSTART, () => { drawing = true; });
        map.on(L.Draw.Event.DRAWSTOP,  () => { drawing = false; });
        map.on(L.Draw.Event.CREATED, e => { drawnItems.clearLayers(); drawnItems.addLayer(e.layer); syncGeometryField(); });
        map.on(L.Draw.Event.EDITED,  syncGeometryField);
        map.on(L.Draw.Event.DELETED, syncGeometryField);
    }

    return { init, reset, setCoords, geocode };
}

const inspectMiniMapCtrl = createFieldPinMap({ mapId: 'mini-map',     coordsDisplayId: 'm-coords-display',     coordsHiddenId: 'm-coords-hidden',     geometryId: 'm-geometry' });
const addMiniMapCtrl     = createFieldPinMap({ mapId: 'add-mini-map', coordsDisplayId: 'm-add-coords-display', coordsHiddenId: 'm-add-coords-hidden', geometryId: 'm-add-geometry' });

const LU_COLORS = { RESIDENTIAL:'#0f766e', COMMERCIAL:'#0e7490', AGRICULTURAL:'#b45309', INDUSTRIAL:'#7e22ce' };
const LU_DEF    = '#4b5563';
function luColor(lu) {
    if (!lu) return LU_DEF;
    const k = lu.toUpperCase();
    for (const [name, col] of Object.entries(LU_COLORS)) { if (k.includes(name)) return col; }
    return LU_DEF;
}

// ── init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
    initTabs();
    loadRecords();
    loadInspections();
    loadAppsForSelect();
    initForms();
    initKeyboardDetect();
    // mini maps on sheet open
    document.getElementById('sheet-log-inspect').addEventListener('transitionend', () => {
        if (document.getElementById('sheet-log-inspect').classList.contains('open')) inspectMiniMapCtrl.init();
    });
    document.getElementById('sheet-add-record').addEventListener('transitionend', () => {
        if (document.getElementById('sheet-add-record').classList.contains('open')) addMiniMapCtrl.init();
    });
});

// ── tabs ─────────────────────────────────────────────────────────────────────
function initTabs() {
    document.querySelectorAll('.nav-pill').forEach(pill => {
        pill.addEventListener('click', () => switchTab(pill.dataset.page));
    });
}

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.nav-pill').forEach(p => p.classList.toggle('active', p.dataset.page === tab));
    document.querySelectorAll('.page').forEach(p => p.classList.toggle('active', p.id === 'page-' + tab));

    const labels = { records:{ sec:'Refresh', pri:'Add Record', sIcon:'refresh-cw', pIcon:'plus' },
                     verify: { sec:'Refresh', pri:'Log Inspection', sIcon:'refresh-cw', pIcon:'plus' },
                     map:    { sec:'Reset View', pri:'Filter', sIcon:'rotate-cw', pIcon:'filter' } };
    const l = labels[tab];
    document.getElementById('secondary-label').textContent = l.sec;
    document.getElementById('primary-label').textContent   = l.pri;
    document.getElementById('secondary-icon').setAttribute('data-lucide', l.sIcon);
    document.getElementById('primary-icon').setAttribute('data-lucide', l.pIcon);
    lucide.createIcons();

    if (tab === 'map' && !mapInited) { mapInited = true; setTimeout(initLeafMap, 80); }
    else if (tab === 'map' && leafMap) { setTimeout(() => leafMap.invalidateSize(), 80); }
}

// bottom nav actions
document.getElementById('btn-secondary').addEventListener('click', () => {
    if (currentTab === 'records') loadRecords();
    else if (currentTab === 'verify') loadInspections();
    else if (currentTab === 'map' && leafMap) leafMap.setView([12.0, 8.52], 11);
});
document.getElementById('btn-primary').addEventListener('click', () => {
    if (currentTab === 'records') { resetLandTitleToggle(); openSheet('sheet-add-record'); }
    else if (currentTab === 'verify') {
        // Reset search and reload apps when opening
        resetAppField();
        inspectMiniMapCtrl.reset();
        openSheet('sheet-log-inspect');
    }
    else if (currentTab === 'map') toggleMapFilterBar();
});

// ── sheets ───────────────────────────────────────────────────────────────────
function openSheet(id) {
    document.getElementById('overlay').classList.add('show');
    document.getElementById(id).classList.add('open');
}
function closeSheet(id) {
    document.getElementById('overlay').classList.remove('show');
    document.getElementById(id).classList.remove('open');
}
document.getElementById('overlay').addEventListener('click', () => {
    closeSheet('sheet-add-record');
    closeSheet('sheet-log-inspect');
});
document.getElementById('close-add-record').addEventListener('click', () => closeSheet('sheet-add-record'));
document.getElementById('close-log-inspect').addEventListener('click', () => closeSheet('sheet-log-inspect'));

// ── load records ─────────────────────────────────────────────────────────────
async function loadRecords() {
    showSkeletons('records-list', 3);
    try {
        const r = await fetch(URLS.records + '?ajax=1&length=100&start=0', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        allRecords = d.data || [];
        renderRecords(allRecords);
        // stats
        const total  = allRecords.length;
        const open   = allRecords.filter(x => x.status_raw === 'open').length;
        const inprog = allRecords.filter(x => x.status_raw === 'in_progress').length;
        document.getElementById('stat-total').textContent  = d.recordsTotal ?? total;
        document.getElementById('stat-open').textContent   = open;
        document.getElementById('stat-inprog').textContent = inprog;
    } catch(e) { showError('records-list', 'Failed to load records.'); }
}

function renderRecords(data) {
    const list = document.getElementById('records-list');
    if (!data.length) {
        list.innerHTML = `
        <div class="empty-state"><i data-lucide="inbox"></i><p>No land records yet.</p></div>
        <div class="rec-card action-card">
            <div style="padding:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;">
                <i data-lucide="plus" style="width:28px;height:28px;color:var(--accent);"></i>
                <span style="font-weight:700;color:var(--accent);font-size:14px;">Add Land Record</span>
            </div>
        </div>`;
        lucide.createIcons();
        list.querySelector('.action-card').addEventListener('click', () => { resetLandTitleToggle(); openSheet('sheet-add-record'); });
        return;
    }
    const statusLabel = { open:'Open', in_progress:'In Progress', approved:'Approved', certificate_issued:'Cert. Issued', closed:'Closed', not_added:'Not Added' };
    let html = data.map((r, i) => {
        const s = r.status_raw || 'not_added';
        const addBtn = s === 'not_added' ? `<button class="add-record-btn" data-fileno="${r.file_number}" style="background:none;border:none;padding:4px 8px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--accent);transition:all .2s;"><i data-lucide="plus-circle" style="width:18px;height:18px;"></i></button>` : '';
        return `
        <div class="rec-card" style="animation-delay:${i * .04}s">
            <div class="rec-card-head">
                <span class="rec-fileno">${r.file_number || '—'}</span>
                <div style="display:flex;align-items:center;gap:8px;">
                    ${addBtn}
                    <span class="rec-status ${s}">${statusLabel[s] || s.toUpperCase()}</span>
                </div>
            </div>
            <div class="rec-card-body">
                <div class="rec-row"><span class="rec-lbl">Owner</span><span class="rec-val">${r.owner_name||'—'}</span></div>
                <div class="rec-row"><span class="rec-lbl">Location</span><span class="rec-val">${r.location||'—'}</span></div>
                <div class="rec-row"><span class="rec-lbl">Land Use</span><span class="rec-val">${r.land_use_type||'—'}</span></div>
            </div>
        </div>`;
    }).join('');
    html += `
    <div class="rec-card action-card">
        <div style="padding:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;">
            <i data-lucide="plus" style="width:28px;height:28px;color:var(--accent);"></i>
            <span style="font-weight:700;color:var(--accent);font-size:14px;">Add Land Record</span>
        </div>
    </div>`;
    list.innerHTML = html;
    lucide.createIcons();

    // Action card click
    list.querySelector('.action-card').addEventListener('click', () => { resetLandTitleToggle(); openSheet('sheet-add-record'); });

    // Add buttons on Not Added cards
    list.querySelectorAll('.add-record-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const fileNo = this.dataset.fileno;
            const card = this.closest('.rec-card');

            resetLandTitleToggle();

            // Extract data from card
            const owner = card.querySelector('.rec-card-body .rec-row:first-child .rec-val')?.textContent?.trim() || '';
            const location = card.querySelector('.rec-card-body .rec-row:nth-child(2) .rec-val')?.textContent?.trim() || '';
            const landUse = card.querySelector('.rec-card-body .rec-row:nth-child(3) .rec-val')?.textContent?.trim() || '';

            // Pre-fill the form
            document.getElementById('m-file-no-input').value = fileNo;
            document.getElementById('m-owner').value = owner && owner !== '—' ? owner : '';
            document.getElementById('m-land-use').value = landUse && landUse !== '—' ? landUse : '';
            document.getElementById('h-file_number').value = fileNo;
            document.getElementById('h-is_indexed').value = '1';

            // Show location badge if available
            if (location && location !== '—') {
                document.getElementById('m-location-text').textContent = location;
                document.getElementById('m-location-badge').style.display = 'flex';
            }

            // Clear lookup message
            document.getElementById('lookup-msg').style.display = 'none';

            openSheet('sheet-add-record');
        });
    });
}

document.getElementById('search-records').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    renderRecords(allRecords.filter(r => (r.file_number+r.owner_name+r.location).toLowerCase().includes(q)));
});

// ── load inspections ──────────────────────────────────────────────────────────
async function loadInspections() {
    showSkeletons('verify-list', 3);
    try {
        const r = await fetch(URLS.fieldData + '?ajax=1&length=100&start=0', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        allInspects = d.data || [];
        renderInspections(allInspects);
    } catch(e) { showError('verify-list', 'Failed to load inspections.'); }
}

function luColor(val) {
    const c = {RESIDENTIAL:'#0e7490',COMMERCIAL:'#ea580c',INDUSTRIAL:'#7c3aed',AGRICULTURAL:'#92400e'};
    return c[(val||'').toUpperCase().split(' ')[0]] || '#4b5563';
}

function renderInspections(data) {
    const list = document.getElementById('verify-list');
    if (!data.length) {
        list.innerHTML = `
        <div class="empty-state"><i data-lucide="search-x"></i><p>No records found.</p></div>
        <div class="rec-card action-card">
            <div style="padding:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;">
                <i data-lucide="plus" style="width:28px;height:28px;color:var(--accent);"></i>
                <span style="font-weight:700;color:var(--accent);font-size:14px;">Log Field Inspection</span>
            </div>
        </div>`;
        lucide.createIcons();
        list.querySelector('.action-card').addEventListener('click', () => {
            resetAppField();
            inspectMiniMapCtrl.reset();
            openSheet('sheet-log-inspect');
        });
        return;
    }
    let html = data.map((f, i) => {
        const inspected = f.inspection_status === 'inspected';
        const contravening = f.contravening;
        const statusBadge = inspected
            ? `<span style="padding:2px 8px;border-radius:99px;background:rgba(16,185,129,.15);color:#10b981;font-size:10px;font-weight:700;">✓ Inspected</span>`
            : `<span style="padding:2px 8px;border-radius:99px;background:rgba(245,158,11,.15);color:#f59e0b;font-size:10px;font-weight:700;">Pending</span>`;
        const contravBadge = contravening
            ? `<span style="padding:2px 8px;border-radius:99px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#ef4444;font-size:10px;font-weight:800;letter-spacing:.04em;">⚠ CONTRAVENTION</span>`
            : '';
        const appliedColor = luColor(f.land_use_type);
        const prevailingColor = luColor(f.existing_use);
        return `
        <div class="insp-card" style="animation-delay:${i * .04}s">
            <div class="insp-card-head">
                <span class="insp-fileno">${f.file_number||'—'}</span>
                <div style="display:flex;align-items:center;gap:6px;">${statusBadge}</div>
            </div>
            <div class="insp-card-body">
                <div class="rec-row"><span class="rec-lbl">Owner</span><span class="rec-val">${f.owner_name||'—'}</span></div>
                <div class="rec-row"><span class="rec-lbl">Location</span><span class="rec-val">${f.location||'—'}</span></div>
                <div class="rec-row">
                    <span class="rec-lbl">Applied</span>
                    <span style="padding:1px 7px;border-radius:99px;background:${appliedColor};color:#fff;font-size:10px;">${f.land_use_type||'—'}</span>
                </div>
                <div class="rec-row">
                    <span class="rec-lbl">Prevailing</span>
                    <span style="padding:1px 7px;border-radius:99px;background:${prevailingColor};color:#fff;font-size:10px;">${f.existing_use||'—'}</span>
                </div>
                ${contravBadge ? `<div style="margin-top:6px;">${contravBadge}</div>` : ''}
                ${inspected ? `<div style="margin-top:6px;font-size:11px;color:var(--text-muted);">📅 ${f.inspection_date||'—'} &nbsp;|&nbsp; ${f.findings && f.findings!=='—' ? f.findings.slice(0,60)+'…' : 'No findings recorded'}</div>` : ''}
            </div>
        </div>`;
    }).join('');
    html += `
    <div class="rec-card action-card">
        <div style="padding:24px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;">
            <i data-lucide="plus" style="width:28px;height:28px;color:var(--accent);"></i>
            <span style="font-weight:700;color:var(--accent);font-size:14px;">Log Field Inspection</span>
        </div>
    </div>`;
    list.innerHTML = html;
    lucide.createIcons();
    list.querySelector('.action-card').addEventListener('click', () => {
        resetAppField();
        inspectMiniMapCtrl.reset();
        openSheet('sheet-log-inspect');
    });
}

document.getElementById('search-verify').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    renderInspections(allInspects.filter(f =>
        (f.file_number + ' ' + f.owner_name + ' ' + f.location).toLowerCase().includes(q)
    ));
});

// ── load apps for inspect select (limited + searchable) ──────────────────────
let allAppsCache = [];

async function loadAppsForSelect() {
    try {
        const r = await fetch(URLS.records + '?ajax=1&length=500&start=0&exclude_inspected=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const d = await r.json();
        appMap = {};
        allAppsCache = d.data || [];
        allAppsCache.forEach(a => { appMap[a.id] = a; });
    } catch(e) {}
}

// ── Linked Application — searchable combobox (matches the File Number field's pattern) ──
function renderAppDropdown(query) {
    const dropdown = document.getElementById('app-dropdown');
    const q = (query || '').toLowerCase().trim();
    if (!allAppsCache.length) {
        dropdown.innerHTML = `<div class="fileno-item"><span class="fi-title">No uninspected applications</span></div>`;
        dropdown.style.display = 'block';
        return;
    }
    const filtered = q
        ? allAppsCache.filter(a => (a.file_number + ' ' + a.owner_name).toLowerCase().includes(q))
        : allAppsCache;
    const shown = filtered.slice(0, 20);
    if (!shown.length) {
        dropdown.innerHTML = `<div class="fileno-item"><span class="fi-title">No results for "${query}"</span></div>`;
    } else {
        dropdown.innerHTML = shown.map(a => `
            <div class="fileno-item" data-id="${a.id}">
                <span class="fi-num">${a.file_number}</span>
                <span class="fi-title">${a.owner_name && a.owner_name !== '—' ? a.owner_name : ''}</span>
            </div>`).join('')
            + (filtered.length > 20 ? `<div class="fileno-item"><span class="fi-title">… ${filtered.length - 20} more — refine your search</span></div>` : '');
        dropdown.querySelectorAll('.fileno-item[data-id]').forEach(item => {
            item.addEventListener('click', function() { selectApp(this.dataset.id); });
        });
    }
    dropdown.style.display = 'block';
}

function selectApp(id) {
    const app = appMap[id];
    document.getElementById('m-app-id').value      = id;
    document.getElementById('m-app-search').value  = app ? [app.file_number, app.owner_name].filter(v => v && v !== '—').join(' – ') : '';
    document.getElementById('m-insp-fileno').value = app && app.file_number !== '—' ? app.file_number : '';
    document.getElementById('app-dropdown').style.display = 'none';
}

function resetAppField() {
    document.getElementById('m-app-search').value  = '';
    document.getElementById('m-app-id').value       = '';
    document.getElementById('m-insp-fileno').value  = '';
    const dropdown = document.getElementById('app-dropdown');
    dropdown.style.display = 'none';
    dropdown.innerHTML = '';
}

document.getElementById('m-app-search').addEventListener('input', function() {
    document.getElementById('m-app-id').value      = '';
    document.getElementById('m-insp-fileno').value = '';
    renderAppDropdown(this.value);
});
document.getElementById('m-app-search').addEventListener('focus', function() {
    renderAppDropdown(this.value);
});
document.addEventListener('click', e => {
    const dropdown = document.getElementById('app-dropdown');
    const inp      = document.getElementById('m-app-search');
    if (!inp.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
});

// ── file number lookup ────────────────────────────────────────────────────────
// ── File number searchable dropdown ───────────────────────────────────────────
(function() {
    const inp      = document.getElementById('m-file-no-input');
    const dropdown = document.getElementById('fileno-dropdown');
    const spinner  = document.getElementById('fileno-spinner');
    const msg      = document.getElementById('lookup-msg');
    let debounceTimer;

    function fillRecord(fileNo) {
        msg.style.display = 'block'; msg.className = 'lookup-msg'; msg.textContent = 'Loading details…';
        fetch(`${URLS.checkFile}?file_number=${encodeURIComponent(fileNo)}`)
            .then(r => r.json())
            .then(d => {
                if (d.found) {
                    document.getElementById('h-file_number').value      = d.file_number || fileNo;
                    document.getElementById('h-file_indexing_id').value = d.file_indexing_id || '';
                    document.getElementById('h-tracking_id').value      = d.tracking_id || '';
                    document.getElementById('h-is_indexed').value       = '1';
                    document.getElementById('h-location').value         = d.location || '';
                    document.getElementById('h-lga').value              = d.lga || '';
                    document.getElementById('h-district').value         = d.district || '';
                    document.getElementById('m-owner').value            = (d.file_title || d.owner_name || '').trim();
                    document.getElementById('m-phone').value            = d.phone || '';
                    document.getElementById('m-land-use').value         = d.land_use_type || '';
                    const loc = [d.location, d.district, d.lga].filter(Boolean).join(', ');
                    const badge = document.getElementById('m-location-badge');
                    if (loc) { document.getElementById('m-location-text').textContent = loc; badge.style.display = 'flex'; }
                    else badge.style.display = 'none';
                    // Auto-pin the inspection map from the file's address, broadening
                    // to district/LGA when the street address can't be resolved.
                    addMiniMapCtrl.geocode([
                        loc,
                        [d.district, d.lga].filter(Boolean).join(', '),
                        d.lga || '',
                    ]);
                    msg.className = 'lookup-msg ok'; msg.textContent = '✓ File found — details pre-filled.';
                } else {
                    document.getElementById('h-file_number').value = fileNo;
                    document.getElementById('h-is_indexed').value  = '0';
                    document.getElementById('m-location-badge').style.display = 'none';
                    msg.className = 'lookup-msg err'; msg.textContent = 'File not in index — fill details manually.';
                }
            })
            .catch(() => { msg.className = 'lookup-msg err'; msg.textContent = 'Lookup failed.'; });
    }

    inp.addEventListener('input', function() {
        const q = this.value.trim();
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        clearTimeout(debounceTimer);
        if (q.length < 2) return;
        spinner.style.display = 'block';
        debounceTimer = setTimeout(async () => {
            try {
                const r = await fetch(`${URLS.searchFiles}?q=${encodeURIComponent(q)}`);
                const results = await r.json();
                spinner.style.display = 'none';
                if (!results.length) {
                    dropdown.innerHTML = `<div class="fileno-item"><span class="fi-title">No results for "${q}"</span></div>`;
                } else {
                    dropdown.innerHTML = results.map(f => `
                        <div class="fileno-item" data-fileno="${f.file_number}">
                            <span class="fi-num">${f.file_number}</span>
                            <span class="fi-title">${f.file_title || f.land_use_type || ''}</span>
                        </div>`).join('');
                    dropdown.querySelectorAll('.fileno-item[data-fileno]').forEach(item => {
                        item.addEventListener('click', function() {
                            const fn = this.dataset.fileno;
                            inp.value = fn;
                            dropdown.style.display = 'none';
                            fillRecord(fn);
                        });
                    });
                }
                dropdown.style.display = 'block';
            } catch(e) { spinner.style.display = 'none'; }
        }, 350);
    });

    // Close dropdown on outside tap
    document.addEventListener('click', e => {
        if (!inp.contains(e.target) && !dropdown.contains(e.target)) dropdown.style.display = 'none';
    });
})();

// ── Customary address picker (LGA + District) ─────────────────────────────────
// Districts are fetched once, on the first switch to Customary, rather than
// rendered inline: there are 1,818 of them, which is a lot of DOM to ship to a
// phone on a field connection.
let mobileDistrictsPromise = null;
function loadMobileDistricts() {
    if (mobileDistrictsPromise) return mobileDistrictsPromise;
    const sel = document.getElementById('m-district');
    mobileDistrictsPromise = fetch('{{ route("api.reference.districts") }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(d => {
            const list = (d && d.data) || [];
            sel.innerHTML = '<option value="">Select District…</option>'
                + list.map(x => `<option value="${x.name}">${x.name}</option>`).join('');
        })
        .catch(() => {
            sel.innerHTML = '<option value="">Districts unavailable — pin on the map</option>';
            mobileDistrictsPromise = null; // allow a retry on the next switch
        });
    return mobileDistrictsPromise;
}

// LGA / District drive both the stored location and the map pin.
function applyMobileCustomaryLocation() {
    const lga      = document.getElementById('m-lga').value.trim();
    const district = document.getElementById('m-district').value.trim();
    const loc      = [district, lga].filter(Boolean).join(', ');

    document.getElementById('h-location').value = loc;
    const badge = document.getElementById('m-location-badge');
    if (loc) {
        document.getElementById('m-location-text').textContent = loc + ', Kano';
        badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }

    if (!lga && !district) return;
    // force: the address just changed, so any earlier pin is stale.
    addMiniMapCtrl.geocode([loc, lga].filter(Boolean), { force: true });
}

['m-lga', 'm-district'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', applyMobileCustomaryLocation);
});

// ── Land Title Type toggle (Statutory / Customary) ─────────────────────────────
// Statutory: file number is picked from an existing indexed file (default).
// Customary: no existing file — a temporary "SPAS-YYYY-####" number is
// generated server-side and the owner/applicant name is typed manually.
function setMobileLandTitleType(type) {
    const fileInput  = document.getElementById('m-file-no-input');
    const ownerInput = document.getElementById('m-owner');
    const msg        = document.getElementById('lookup-msg');
    const dropdown   = document.getElementById('fileno-dropdown');

    document.getElementById('h-file_number').value      = '';
    document.getElementById('h-file_indexing_id').value = '';
    document.getElementById('h-tracking_id').value      = '';
    document.getElementById('h-location').value         = '';
    document.getElementById('h-lga').value               = '';
    document.getElementById('h-district').value          = '';
    document.getElementById('m-land-use').value          = '';
    document.getElementById('m-location-badge').style.display = 'none';
    msg.style.display = 'none';
    dropdown.style.display = 'none';
    fileInput.value  = '';
    ownerInput.value = '';

    // Only the active control carries each name (land_use_type / lga / district);
    // its counterpart is disabled so the form never posts two values.
    const customary  = type === 'customary';
    const luInput    = document.getElementById('m-land-use');
    const luSelect   = document.getElementById('m-land-use-select');
    const lgaSelect  = document.getElementById('m-lga');
    const distSelect = document.getElementById('m-district');

    luSelect.value = '';
    luInput.style.display  = customary ? 'none' : '';
    luInput.disabled       = customary;
    luSelect.style.display = customary ? '' : 'none';
    luSelect.disabled      = !customary;

    lgaSelect.value  = '';
    distSelect.value = '';
    lgaSelect.disabled  = !customary;
    distSelect.disabled = !customary;
    document.getElementById('h-lga').disabled      = customary;
    document.getElementById('h-district').disabled = customary;
    document.getElementById('m-customary-geo').style.display = customary ? '' : 'none';
    if (customary) loadMobileDistricts();

    if (type === 'customary') {
        fileInput.disabled = true;
        fileInput.placeholder = 'Generating…';
        ownerInput.readOnly = false;
        ownerInput.classList.remove('inp-ro');
        ownerInput.placeholder = "Enter applicant's name";
        document.getElementById('h-is_indexed').value = '0';

        fetch(URLS.nextCustomaryFileNo, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                fileInput.value = d.file_number;
                document.getElementById('h-file_number').value = d.file_number;
            })
            .catch(() => { fileInput.placeholder = 'Could not generate — try again'; });
    } else {
        fileInput.disabled = false;
        fileInput.placeholder = 'Type to search file number…';
        ownerInput.readOnly = true;
        ownerInput.classList.add('inp-ro');
        ownerInput.placeholder = 'Auto-filled after lookup';
    }
}

function resetLandTitleToggle() {
    document.querySelectorAll('.ltt-btn').forEach(b => {
        const isStatutory = b.dataset.type === 'statutory';
        b.classList.toggle('active', isStatutory);
        b.style.background  = isStatutory ? 'var(--accent-dim)' : 'var(--surface2)';
        b.style.borderColor = isStatutory ? 'var(--accent)'     : 'var(--border)';
        b.style.color       = isStatutory ? 'var(--accent)'     : 'var(--text-muted)';
    });
    document.getElementById('m-land-title-type').value = 'statutory';
    setMobileLandTitleType('statutory');
    addMiniMapCtrl.reset();
}

document.querySelectorAll('.ltt-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.ltt-btn').forEach(b => {
            b.classList.remove('active');
            b.style.background  = 'var(--surface2)';
            b.style.borderColor = 'var(--border)';
            b.style.color       = 'var(--text-muted)';
        });
        this.classList.add('active');
        this.style.background  = 'var(--accent-dim)';
        this.style.borderColor = 'var(--accent)';
        this.style.color       = 'var(--accent)';
        document.getElementById('m-land-title-type').value = this.dataset.type;
        setMobileLandTitleType(this.dataset.type);
    });
});

// ── GPS ───────────────────────────────────────────────────────────────────────
function wireGpsButton(btnId, ctrl) {
    document.getElementById(btnId).addEventListener('click', function() {
        if (!navigator.geolocation) { toast('GPS not supported on this device.', 'error'); return; }
        this.textContent = '…'; this.disabled = true;
        navigator.geolocation.getCurrentPosition(pos => {
            ctrl.setCoords(pos.coords.latitude.toFixed(6), pos.coords.longitude.toFixed(6));
            this.innerHTML = '<i data-lucide="navigation" style="width:14px;"></i> GPS'; this.disabled = false;
            lucide.createIcons();
        }, () => {
            toast('Could not get location.', 'error');
            this.innerHTML = '<i data-lucide="navigation" style="width:14px;"></i> GPS'; this.disabled = false;
            lucide.createIcons();
        }, { timeout: 10000 });
    });
}
wireGpsButton('btn-gps', inspectMiniMapCtrl);
wireGpsButton('btn-add-gps', addMiniMapCtrl);

// ── Leaflet field map ─────────────────────────────────────────────────────────
function initLeafMap() {
    leafMap = L.map('field-map', { zoomControl: false }).setView([12.0, 8.52], 11);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution:'© Esri', maxZoom:19 }).addTo(leafMap);

    MPTS.forEach(p => {
        if (!p.coords?.lat || !p.coords?.lng) return;
        const col = luColor(p.land_use);
        const icon = L.divIcon({ className:'', html:`<div style="width:12px;height:12px;border-radius:50%;background:${col};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5);"></div>`, iconSize:[12,12], iconAnchor:[6,6], popupAnchor:[0,-8] });
        const m = L.marker([p.coords.lat, p.coords.lng], { icon });
        m._lu = (p.land_use||'').toUpperCase();
        const photoHtml = p.photo
            ? `<img src="${p.photo}" style="width:100%;height:100px;object-fit:cover;border-radius:6px;margin-bottom:7px;display:block;">`
            : '';
        const cbBadge = p.contravening
            ? `<span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:99px;background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;font-size:10px;font-weight:700;">⚠ Contravention</span>`
            : `<span style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px;border-radius:99px;background:#dcfce7;border:1px solid #86efac;color:#16a34a;font-size:10px;font-weight:700;">✓ Compliant</span>`;
        function mLuChip(val) {
            const cols={RESIDENTIAL:'#0e7490',COMMERCIAL:'#ea580c',INDUSTRIAL:'#7c3aed',AGRICULTURAL:'#92400e'};
            const bg = cols[(val||'').toUpperCase().split(' ')[0]] || '#4b5563';
            return val ? `<span style="display:inline-block;padding:1px 7px;border-radius:99px;background:${bg};color:#fff;font-size:10px;">${val}</span>` : '<span style="color:#9ca3af;font-size:10px;">—</span>';
        }
        m.bindPopup(`<div style="min-width:200px;font-size:12px;font-family:'Plus Jakarta Sans',sans-serif;">
            ${photoHtml}
            <div style="margin-bottom:6px;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">File Number</div>
                <div style="font-weight:700;font-size:13px;color:#1f2937;">${p.file_number||'—'}</div>
            </div>
            <div style="margin-bottom:6px;">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">File Title / Owner</div>
                <div style="color:#374151;font-size:11px;">${p.owner||'—'}</div>
            </div>
            <div style="margin-bottom:8px;padding:5px 7px;background:#f9fafb;border-radius:5px;border-left:3px solid rgb(186,191,12);">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">Location</div>
                <div style="color:#374151;font-size:11px;">${p.location||'—'}</div>
            </div>
            <div style="border-top:1px solid #f3f4f6;padding-top:6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;"><span style="font-size:9px;color:#9ca3af;font-weight:600;text-transform:uppercase;">Applied Land Use</span>${mLuChip(p.land_use)}</div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;"><span style="font-size:9px;color:#9ca3af;font-weight:600;text-transform:uppercase;">Approved Land Use</span>${mLuChip(p.approved_use||'')}</div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;"><span style="font-size:9px;color:#9ca3af;font-weight:600;text-transform:uppercase;">Prevailing Land Use</span>${mLuChip(p.prevailing||'')}</div>
                <div>${cbBadge}</div>
            </div>
        </div>`);
        m.addTo(leafMap);
        leafMarkers.push(m);
    });

    document.getElementById('map-count').textContent = leafMarkers.length;
}

// map filter chips
document.getElementById('map-filter-bar').addEventListener('click', function(e) {
    const chip = e.target.closest('.map-filter-chip');
    if (!chip || !leafMap) return;
    document.querySelectorAll('.map-filter-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    const lu = chip.dataset.lu.toUpperCase();
    leafMarkers.forEach(m => {
        if (lu === 'ALL' || m._lu.includes(lu)) { m.addTo(leafMap); }
        else leafMap.removeLayer(m);
    });
    const visible = lu === 'ALL' ? leafMarkers.length : leafMarkers.filter(m => m._lu.includes(lu)).length;
    document.getElementById('map-count').textContent = visible;
});

function toggleMapFilterBar() {
    const bar = document.getElementById('map-filter-bar');
    bar.style.display = bar.style.display === 'none' ? 'flex' : 'none';
}

// ── forms ─────────────────────────────────────────────────────────────────────
function initForms() {
    // If the surveyor logged an inspection alongside the record, save it too —
    // as a second call, since it needs the spa_application_id the first returns.
    async function saveMobileInspectionFor(app) {
        const inspDate     = document.getElementById('m-add-inspection-date').value;
        const inspFindings = document.getElementById('m-add-findings').value.trim();
        const inspCoords   = document.getElementById('m-add-coords-display').value.trim();
        const inspGeometry = document.getElementById('m-add-geometry').value;

        if (!inspDate && !inspFindings && !inspCoords) return; // nothing entered — skip silently
        if (!inspDate || !inspFindings) {
            toast('Inspection not logged — date and findings are both required; add it later from Field Records.', 'error');
            return;
        }

        const fd = new FormData();
        fd.append('spa_application_id', app.id);
        fd.append('file_number', app.file_number);
        fd.append('inspection_date', inspDate);
        fd.append('findings', inspFindings);
        const coordsJson = document.getElementById('m-add-coords-hidden').value || (inspCoords ? JSON.stringify(parseLatLngInput(inspCoords)) : '');
        if (coordsJson && coordsJson !== 'null') fd.append('coordinates', coordsJson);
        if (inspGeometry) fd.append('parcel_geometry', inspGeometry);

        try {
            const res  = await fetch(URLS.storeField, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'}, body: fd });
            const data = await res.json();
            if (data.success) {
                toast('Inspection logged too.', 'success');
                loadInspections();
                loadAppsForSelect();
                if (data.mapPoint && leafMap && data.mapPoint.coords?.lat && data.mapPoint.coords?.lng) {
                    const p = data.mapPoint;
                    const col = luColor(p.land_use);
                    const icon = L.divIcon({ className:'', html:`<div style="width:12px;height:12px;border-radius:50%;background:${col};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5);"></div>`, iconSize:[12,12], iconAnchor:[6,6] });
                    const m = L.marker([p.coords.lat, p.coords.lng], { icon }); m._lu = (p.land_use||'').toUpperCase(); m.addTo(leafMap); leafMarkers.push(m);
                    document.getElementById('map-count').textContent = leafMarkers.length;
                }
            } else {
                toast('Inspection not logged: ' + (data.message || 'save failed') + ' — add it later from Field Records.', 'error');
            }
        } catch (err) {
            toast('Inspection not logged due to a network error — add it later from Field Records.', 'error');
        }
    }

    // Save record
    document.getElementById('form-add-record').addEventListener('submit', async function(e) {
        e.preventDefault();
        const isCustomary = document.getElementById('m-land-title-type').value === 'customary';
        if (!document.getElementById('h-file_number').value.trim()) {
            toast(isCustomary ? 'Temporary file number still generating — try again.' : 'Select a file number first.', 'error');
            return;
        }
        if (isCustomary && !document.getElementById('m-lga').value.trim()) {
            toast('Select the LGA — a customary title has no file to take its location from.', 'error');
            return;
        }
        const btn = document.getElementById('btn-save-record');
        const originalBtnHtml = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i data-lucide="loader" style="width:15px;animation:spin 1s linear infinite;"></i> Saving…'; lucide.createIcons();
        try {
            const res  = await fetch(URLS.storeRec, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Accept':'application/json'}, body: new FormData(this) });
            const data = await res.json();
            if (res.status === 422) {
                // Show the actual field error rather than a bare "Save failed"
                const first = Object.values(data.errors || {})[0];
                toast((first && first[0]) || data.message || 'Please check the form.', 'error');
                btn.disabled = false; btn.innerHTML = originalBtnHtml; lucide.createIcons();
                return;
            }
            if (data.success) {
                closeSheet('sheet-add-record');
                toast('Record saved.', 'success');
                loadRecords();
                // Read the inspection fields (if any) before this.reset() clears them.
                await saveMobileInspectionFor({ id: data.id, file_number: data.file_number });
                this.reset();
                document.getElementById('m-location-badge').style.display = 'none';
                document.getElementById('lookup-msg').style.display = 'none';
                document.getElementById('m-contravening-badge').style.display = 'none';
            } else { toast(data.message || 'Save failed.', 'error'); }
        } catch(err) { toast('Unexpected error.', 'error'); }
        btn.disabled = false; btn.innerHTML = '<i data-lucide="save" style="width:16px;"></i> SAVE RECORD'; lucide.createIcons();
    });

    // Contravention check (Approved vs Prevailing)
    function checkMobileContravention() {
        const approved   = (document.getElementById('m-approved-use').value || '').trim().toUpperCase();
        const prevailing = (document.getElementById('m-existing-use').value  || '').trim().toUpperCase();
        const badge      = document.getElementById('m-contravening-badge');
        badge.style.display = (approved && prevailing && approved !== prevailing) ? 'block' : 'none';
    }
    document.getElementById('m-approved-use').addEventListener('change', checkMobileContravention);
    document.getElementById('m-existing-use').addEventListener('change', checkMobileContravention);

    // Property photo preview
    document.getElementById('m-photos').addEventListener('change', function () {
        const preview = document.getElementById('m-photo-preview');
        preview.innerHTML = '';
        Array.from(this.files).forEach(file => {
            const url = URL.createObjectURL(file);
            preview.innerHTML += `<img src="${url}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">`;
        });
        lucide.createIcons();
    });

    // Clear photo preview on form reset
    const origReset = document.getElementById('form-add-record').reset.bind(document.getElementById('form-add-record'));
    document.getElementById('form-add-record').reset = function() { origReset(); document.getElementById('m-photo-preview').innerHTML = ''; document.getElementById('m-contravening-badge').style.display = 'none'; resetLandTitleToggle(); };

    // Save inspection
    document.getElementById('form-log-inspect').addEventListener('submit', async function(e) {
        e.preventDefault();
        if (!document.getElementById('m-app-id').value) {
            toast('Search and select a linked application first.', 'error');
            return;
        }
        const btn = document.getElementById('btn-save-inspect');
        btn.disabled = true; btn.innerHTML = '<i data-lucide="loader" style="width:15px;animation:spin 1s linear infinite;"></i> Saving…'; lucide.createIcons();
        const fd = new FormData(this);
        // encode coordinates from display input if not set via GPS/map
        const coordStr = document.getElementById('m-coords-display').value.trim();
        if (coordStr && !fd.get('coordinates')) {
            const parsed = parseLatLngInput(coordStr);
            if (parsed) fd.set('coordinates', JSON.stringify(parsed));
        }
        try {
            const res  = await fetch(URLS.storeField, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'}, body: fd });
            const data = await res.json();
            if (data.success) {
                closeSheet('sheet-log-inspect'); this.reset();
                resetAppField();
                inspectMiniMapCtrl.reset();
                toast('Inspection logged.', 'success');
                loadInspections();
                loadAppsForSelect();
                // Add live marker to field map if coordinates provided
                if (data.mapPoint && leafMap) {
                    const p = data.mapPoint;
                    if (p.coords?.lat && p.coords?.lng) {
                        const col = luColor(p.land_use);
                        const icon = L.divIcon({ className:'', html:`<div style="width:12px;height:12px;border-radius:50%;background:${col};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5);"></div>`, iconSize:[12,12], iconAnchor:[6,6] });
                        const m = L.marker([p.coords.lat, p.coords.lng], { icon }); m._lu = (p.land_use||'').toUpperCase(); m.addTo(leafMap); leafMarkers.push(m);
                        document.getElementById('map-count').textContent = leafMarkers.length;
                    }
                }
            } else { toast(data.message || 'Save failed.', 'error'); }
        } catch(err) { toast('Unexpected error.', 'error'); }
        btn.disabled = false; btn.innerHTML = '<i data-lucide="check-circle" style="width:16px;"></i> SAVE INSPECTION'; lucide.createIcons();
    });
}

// ── helpers ───────────────────────────────────────────────────────────────────
function showSkeletons(id, n) {
    document.getElementById(id).innerHTML = Array.from({length:n}, (_,i) => `<div class="skeleton" style="animation-delay:${i*.1}s;height:${70+i*8}px;"></div>`).join('');
}
function showError(id, msg) {
    document.getElementById(id).innerHTML = `<div class="empty-state"><i data-lucide="wifi-off"></i><p>${msg}</p></div>`;
    lucide.createIcons();
}

let toastTimer;
function toast(msg, type = 'success') {
    const el = document.getElementById('toast');
    const icon = type === 'success' ? 'check-circle' : 'alert-circle';
    document.getElementById('toast-icon').setAttribute('data-lucide', icon);
    document.getElementById('toast-msg').textContent = msg;
    el.className = 'toast show ' + type;
    lucide.createIcons();
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.classList.remove('show'); }, 3000);
}

function initKeyboardDetect() {
    const body = document.body;
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            body.classList.toggle('keyboard-visible', window.visualViewport.height < window.innerHeight * 0.75);
        });
    } else {
        document.addEventListener('focusin',  e => { if (['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName)) body.classList.add('keyboard-visible'); });
        document.addEventListener('focusout', () => setTimeout(() => body.classList.remove('keyboard-visible'), 200));
    }
}
</script>

<style>
@keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
</style>
</body>
</html>
