<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VFC Mobile · Field Entry</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --bg: #0b0e14;
            --surface: #151921;
            --surface2: #1e2530;
            --border: rgba(255, 255, 255, 0.08);
            --border-active: #3b82f6;
            --accent: #3b82f6;
            --accent-glow: rgba(59, 130, 246, 0.3);
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #475569;
            --radius: 16px;
            --radius-sm: 10px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            min-height: 100vh;
            overflow-x: hidden;
            overscroll-behavior-y: contain;
        }

        input[type="text"], 
        textarea, 
        input[type="search"] {
            text-transform: uppercase !important;
        }

        /* ── TOP BAR ── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(11, 14, 20, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            padding-top: max(14px, env(safe-area-inset-top));
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #1e40af, #3b82f6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .brand-text h1 {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .brand-text p {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .sync-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 8px var(--success);
        }

        /* ── PROGRESS STRIP ── */
        .nav-strip {
            position: sticky;
            top: 65px; /* adjust based on topbar height */
            z-index: 99;
            background: rgba(21, 25, 33, 0.95);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            display: flex;
            gap: 8px;
            overflow-x: auto;
            scrollbar-width: none;
            border-bottom: 1px solid var(--border);
        }

        .nav-strip::-webkit-scrollbar { display: none; }

        .nav-item {
            padding: 6px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            white-space: nowrap;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-item.active {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--accent);
            color: var(--accent);
        }

        .nav-item.done {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }

        /* ── CONTENT ── */
        .main-content {
            padding: 20px;
            padding-bottom: 120px;
            max-width: 500px;
            margin: 0 auto;
        }

        /* ── SUMMARY CARD ── */
        .summary-card {
            margin-top: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 12px;
        }
        .summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 12px;
        }
        .summary-label {
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .id-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            background: rgba(59, 130, 246, 0.15);
            color: var(--accent);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .summary-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .summary-icon-box {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
        }
        .summary-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 700;
        }
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }
        .stat-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 10px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .stat-badge.success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        .stat-badge span {
            color: var(--text);
            font-weight: 800;
        }
        .stat-badge.success span {
            color: var(--success);
        }
        .mt-3 { margin-top: 12px; }
        .hidden { display: none !important; }

        .section-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            margin-bottom: 16px;
            overflow: hidden;
            animation: slideUp 0.4s ease-out both;
        }

        .bank-logo-img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            display: block !important;
        }

        .selected-logo-wrap {
            width: 28px !important;
            height: 28px !important;
            min-width: 28px !important;
            border-radius: 6px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3px;
            overflow: hidden;
            flex-shrink: 0;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .section-header {
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            transition: background 0.2s;
        }

        .section-header:active {
            background: rgba(255, 255, 255, 0.05);
        }

        .section-card.collapsed .section-body {
            display: none;
        }

        .section-card.collapsed {
            margin-bottom: 8px;
        }

        .section-card.collapsed .section-header {
            border-bottom: none;
        }

        .section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .section-header h2 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        .section-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ── FORM ELEMENTS ── */
        .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .field label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .field label .req { color: var(--danger); }

        .inp-wrap {
            position: relative;
        }

        .inp {
            width: 100%;
            background: var(--surface2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            color: var(--text);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            outline: none;
        }

        .inp:focus {
            border-color: var(--accent);
            background: var(--bg);
            box-shadow: 0 0 0 4px var(--accent-glow);
        }

        .inp-readonly {
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-muted);
            cursor: not-allowed;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
        }

        select.inp {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        /* ── SPECIAL WIDGETS ── */
        .amount-card {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(16, 185, 129, 0.05));
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--radius-sm);
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amount-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--accent);
            text-transform: uppercase;
        }

        .amount-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 22px;
            font-weight: 600;
            color: var(--success);
        }

        .items-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .items-section-header {
            grid-column: 1 / -1;
            padding: 12px 4px 6px 4px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 4px;
            margin-top: 10px;
        }

        .items-section-header h3 {
            font-size: 10px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .items-section-header p {
            font-size: 9px;
            color: var(--text-dim);
            font-style: italic;
            margin-top: 2px;
            font-weight: 500;
        }

        .check-item {
            background: var(--surface2);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .check-item.active {
            border-color: var(--accent);
            background: rgba(59, 130, 246, 0.1);
        }

        .check-box {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .check-item.active .check-box {
            background: var(--accent);
            border-color: var(--accent);
        }

        .check-box svg {
            color: white;
            width: 12px;
            height: 12px;
            display: none;
        }

        .check-item.active .check-box svg {
            display: block;
        }

        .check-label {
            font-size: 12px;
            font-weight: 600;
        }

        .calc-box {
            grid-column: 1 / -1;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-top: none;
            border-bottom-left-radius: var(--radius-sm);
            border-bottom-right-radius: var(--radius-sm);
            padding: 12px;
            margin-top: -12px;
            margin-bottom: 8px;
            display: none;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .calc-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .calc-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .calc-field label {
            font-size: 9px;
            font-weight: 800;
            color: var(--text-dim);
            text-transform: uppercase;
        }

        .calc-inp {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px;
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
        }

        .calc-result {
            grid-column: 1 / -1;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .calc-total-label {
            font-size: 10px;
            font-weight: 800;
            color: var(--accent);
        }

        .calc-total-val {
            font-size: 14px;
            font-weight: 700;
            color: var(--success);
        }

        /* ── FOOTER BAR ── */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(11, 14, 20, 0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid var(--border);
            padding: 16px 20px;
            padding-bottom: max(16px, env(safe-area-inset-bottom));
            display: flex;
            gap: 12px;
            max-width: 500px;
            margin: 0 auto;
            z-index: 100;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        body.keyboard-visible .bottom-nav {
            display: none !important;
        }

        .btn {
            flex: 1;
            height: 52px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-ghost {
            background: var(--surface2);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }

        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 4px 20px var(--accent-glow);
        }

        .btn:active { transform: scale(0.96); }

        /* ── UTILS ── */
        .hidden { display: none; }
        
        .toast {
            position: fixed;
            bottom: 100px;
            left: 50%;
            transform: translateX(-50%) translateY(20px);
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 12px 20px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            white-space: nowrap;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }

        /* ── MAP STYLES ── */
        #map {
            height: 220px;
            width: 100%;
            border-radius: var(--radius-sm);
            margin-top: 12px;
            border: 1.5px solid var(--border);
            z-index: 1;
        }

        .map-controls {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .btn-geo {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            color: var(--accent);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }

        .coord-badge {
            flex: 1;
            background: var(--surface2);
            border: 1px solid var(--border);
            padding: 8px 12px;
            border-radius: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 10px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
        }

    </style>
</head>
<body>


    <!-- TOP BAR -->
    <header class="topbar">
        <div class="topbar-brand">
            <div class="brand-icon">
                <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="VFC" style="width: 24px; height: 24px; object-fit: contain;" 
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i data-lucide="shield-check" class="h-5 w-5" style="display: none;"></i>
            </div>
            <div class="brand-text">
                <h1>VFC App</h1>
                
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 15px;">
            <div class="sync-status" title="Online"></div>
            <form action="{{ route('valuation-compensations.mobile.logout') }}" method="POST" id="logout-form" style="display: none;">
                @csrf
            </form>
            <a href="javascript:void(0)" onclick="if(confirm('Are you sure you want to logout?')) document.getElementById('logout-form').submit();" style="text-decoration: none; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #f87171; padding: 8px 12px; border-radius: 10px; font-size: 11px; font-weight: 700; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                <i data-lucide="log-out" style="width: 14px;"></i>
                EXIT
            </a>
        </div>
    </header>

    <!-- NAV STRIP -->
    <nav class="nav-strip" id="navStrip">
        <div class="nav-item active" data-target="sec-project">
            <i data-lucide="briefcase" style="width: 12px;"></i>
            <span>PROJECT</span>
        </div>
        <div class="nav-item" data-target="sec-owner">
            <i data-lucide="user" style="width: 12px;"></i>
            <span>OWNER</span>
        </div>
        <div class="nav-item" data-target="sec-building">
            <i data-lucide="home" style="width: 12px;"></i>
            <span>BUILDING</span>
        </div>
        <div class="nav-item" data-target="sec-payment">
            <i data-lucide="credit-card" style="width: 12px;"></i>
            <span>PAYMENT</span>
        </div>
        <div class="nav-item" data-target="sec-location">
            <i data-lucide="map-pin" style="width: 12px;"></i>
            <span>LOCATION</span>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <form id="vfcForm">
            <!-- 1. PROJECT & ASSIGNMENT -->
            <section class="section-card" id="sec-project">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--accent);">
                        <i data-lucide="layers" style="width: 16px;"></i>
                    </div>
                    <h2>Project Context</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Target Project <span class="req">*</span></label>
                        <select name="project_id" id="projectSelect" class="inp" >
                            <option value="">Loading Projects...</option>
                        </select>

                        <div id="mobile-project-info" class="hidden summary-card">
                            <div class="summary-header">
                                <p class="summary-label">Project Summary</p>
                                <span class="id-badge">ID: <span id="m_proj_id">-</span></span>
                            </div>

                            <div class="summary-body">
                                <div class="summary-item">
                                    <div class="summary-icon-box" style="border-color: rgba(245, 158, 11, 0.2);">
                                        <i data-lucide="hash" style="width: 12px; color: var(--warning);"></i>
                                    </div>
                                    <span id="m_proj_code" class="summary-value" style="color: var(--warning);">-</span>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-icon-box" style="border-color: rgba(59, 130, 246, 0.2);">
                                        <i data-lucide="file-text" style="width: 12px; color: var(--accent);"></i>
                                    </div>
                                    <span id="m_proj_fileno" class="summary-value" style="color: var(--accent);">-</span>
                                </div>

                                <div class="stats-grid">
                                    <div class="stat-badge">
                                        <i data-lucide="users"></i>
                                        WORKERS: <span id="m_proj_workers">0</span>
                                    </div>
                                    <div class="stat-badge success">
                                        <i data-lucide="check-circle"></i>
                                        FORM FILLED: <span id="m_proj_filled">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Sub-Project <span class="req">*</span></label>
                        <select name="sub_project_id" id="subProjectSelect" class="inp"  disabled>
                            <option value="">Select Project First</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Assigned Worker <span class="req">*</span></label>
                        <select name="worker_id" id="workerSelect" class="inp"  disabled>
                            <option value="">Select Project First</option>
                        </select>
                    </div>
                    <div id="workerBadge" class="hidden">
                        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 10px; padding: 12px; display: flex; align-items: center; gap: 10px;">
                            <i data-lucide="id-card" style="width: 14px; color: var(--warning);"></i>
                            <span id="workerCodeDisplay" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 700; color: var(--warning);">—</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 2. OWNER & FILE -->
            <section class="section-card collapsed" id="sec-owner">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i data-lucide="file-text" style="width: 16px;"></i>
                    </div>
                    <h2>Ownership & Dates</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Project Code <span class="text-[10px] italic">(From Project)</span></label>
                        <input type="text" id="mobile_project_code" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Project FileNo <span class="text-[10px] italic">(From Project)</span></label>
                        <input type="text" id="mobile_project_fileno" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Our Reference <span class="req">*</span></label>
                        <input type="text" name="our_ref" id="mobile_our_ref" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Your Reference</label>
                        <input type="text" name="your_ref" id="mobile_your_ref" class="inp inp-readonly" placeholder="Select Project First" readonly>
                    </div>
                    <div class="field">
                        <label>Owner Full Name <span class="req">*</span></label>
                        <input type="text" name="owner_name" id="owner_name" class="inp" placeholder="Musa Yakubu" >
                    </div>
                    <div class="field">
                        <label>Valuation Date <span class="req">*</span></label>
                        <input type="date" name="valuation_date" id="valuation_date" class="inp" value="{{ date('Y-m-d') }}" >
                    </div>
                </div>
            </section>

            <!-- 3. BUILDING & COST -->
            <section class="section-card collapsed" id="sec-building">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i data-lucide="building" style="width: 16px;"></i>
                    </div>
                    <h2>Building Assessment</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Building Count <span class="req">*</span></label>
                        <input type="number" name="building_count" id="buildingCount" class="inp" value="1" min="1" >
                    </div>
                    <div class="field">
                        <label>Building Assessment & Details <span class="req">*</span></label>
                        <div id="building_types_mobile_container" style="display: flex; flex-direction: column; gap: 12px;">
                            <div class="building-type-mobile-row" style="background: rgba(255,255,255,0.02); padding: 12px; border-radius: 12px; border: 1px solid var(--border);">
                                <div style="font-size: 9px; font-weight: 800; color: var(--text-dim); text-transform: uppercase; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 8px;">
                                    <i data-lucide="building" style="width: 10px;"></i>
                                    Building 1
                                </div>
                                <div style="margin-bottom: 12px;">
                                    <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Building Type</label>
                                    <select class="inp building-type-mobile-select">
                                        <option value="">Select Type</option>
                                    </select>
                                    <input type="text" class="inp building-type-mobile-other hidden mt-2" placeholder="Specify type...">
                                </div>
                                <div>
                                    <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Stage of Completion</label>
                                    <select class="inp building-stage-mobile-select">
                                        <option value="">Select Stage</option>
                                    </select>
                                    <input type="text" class="inp building-stage-mobile-other hidden mt-2" placeholder="Specify stage...">
                                </div>
                                <div style="margin-top: 12px;">
                                    <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Number of Floors</label>
                                    <input type="number" min="1" step="1" class="inp building-mobile-floors" placeholder="e.g. 2">
                                </div>
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.05); display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                    <div>
                                        <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Length (L) (m)</label>
                                        <input type="number" step="0.01" class="inp building-mobile-length" placeholder="0.00" style="padding: 8px; font-size: 12px;">
                                    </div>
                                    <div>
                                        <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Breadth (B) (m)</label>
                                        <input type="number" step="0.01" class="inp building-mobile-breadth" placeholder="0.00" style="padding: 8px; font-size: 12px;">
                                    </div>
                                    <div>
                                        <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Area Covered (m²)</label>
                                        <input type="number" step="0.01" class="inp building-mobile-area" placeholder="0.00" style="padding: 8px; font-size: 12px; background: rgba(59,130,246,0.05); color: var(--accent); border-color: rgba(59,130,246,0.2);">
                                    </div>
                                    <div>
                                        <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Rate of Cost (₦)</label>
                                        <input type="number" step="0.01" class="inp building-mobile-rate" placeholder="0.00" style="padding: 8px; font-size: 12px;">
                                    </div>
                                    <div style="grid-column: 1 / -1;">
                                        <label style="font-size: 9px; font-weight: 700; color: var(--text-dim); margin-bottom: 4px; display: block; text-transform: uppercase;">Amount (₦)</label>
                                        <div class="inp-wrap" style="position: relative;">
                                            <div style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--success); font-size: 11px; font-weight: 700;">₦</div>
                                            <input type="number" step="0.01" class="inp building-mobile-comp" placeholder="0.00" readonly style="padding: 8px 8px 8px 24px; font-size: 12px; color: var(--success); font-weight: 700;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="building_type" id="building_type_final_mobile">
                        <input type="hidden" name="completion_stage" id="completion_stage_final_mobile">
                        <input type="hidden" name="number_of_floors" id="number_of_floors_final_mobile">
                    </div>
                    
                    <div style="display: none;">
                        <div class="field">
                            <label>Total Length (L) (m)</label>
                            <input type="number" name="length" id="length" class="inp inp-readonly" placeholder="0.00" step="0.01" readonly>
                        </div>
                        <div class="field">
                            <label>Total Breadth (B) (m)</label>
                            <input type="number" name="breadth" id="breadth" class="inp inp-readonly" placeholder="0.00" step="0.01" readonly>
                        </div>
                        <div class="field">
                            <label>Total Area Covered (m²) <span class="req">*</span></label>
                            <input type="number" name="area_covered" id="areaCovered" class="inp inp-readonly" placeholder="0.00" step="0.01" readonly>
                        </div>

                        <div class="field">
                            <label>Average Rate of Cost (₦) <span class="req">*</span></label>
                            <input type="number" name="rate_of_cost" id="rateOfCost" class="inp inp-readonly" placeholder="0.00" step="0.01" readonly>
                        </div>
                        
                        <div class="field">
                            <label>Total Amount of Compensation <span class="req">*</span></label>
                            <div class="inp-wrap" style="position: relative;">
                                <div style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--success); font-weight: 700;">₦</div>
                                <input type="number" name="compensation_amount" id="compensation_amount" class="inp inp-readonly" placeholder="0.00" step="0.01" style="padding-left: 32px; color: var(--success); font-family: 'JetBrains Mono', monospace; font-size: 18px; font-weight: 700;" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="field">
                        <label>Compensated Items</label>
                        <div class="items-grid" id="valuationItemsGrid">
                            <div style="padding:10px; font-size:11px; color:var(--text-dim); font-style:italic;">Loading items...</div>
                        </div>
                        <input type="text" id="compItemsOtherText" class="inp hidden mt-3" placeholder="Specify other items...">
                        <input type="hidden" name="compensated_items" id="compItemsVal">
                    </div>
                </div>
            </section>

            <!-- 4. ACCOUNT DETAILS -->
            <section class="section-card collapsed" id="sec-payment">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i data-lucide="banknote" style="width: 16px;"></i>
                    </div>
                    <h2>Account & Payment</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Bank Name</label>
                        <div class="inp-wrap" style="position: relative; display: flex; align-items: center;">
                            <div id="selectedBankLogo" class="hidden" style="position: absolute; left: 12px; z-index: 10; pointer-events: none;">
                                <div class="selected-logo-wrap shadow-lg">
                                    <img src="" alt="Bank" class="bank-logo-img">
                                </div>
                            </div>
                            <input type="text" id="bankSearch" class="inp" placeholder="Search Nigerian Banks..." autocomplete="off">
                            <input type="hidden" name="bank_name" id="bankNameVal">
                            <div id="bankResults" class="hidden absolute z-50 left-0 right-0 top-full mt-1 bg-surface2 border border-border rounded-xl max-h-60 overflow-y-auto shadow-2xl">
                                <!-- JS items -->
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Account Name <span class="req">*</span></label>
                        <input type="text" name="account_name" class="inp" placeholder="Full Name as on Bank Account" >
                    </div>
                    <div class="field">
                        <label>Account Number <span class="req">*</span></label>
                        <input type="tel" name="account_number" class="inp" placeholder="10 Digits" maxlength="10" >
                    </div>
                    <div class="field">
                        <label>Phone Number <span class="req">*</span></label>
                        <input type="tel" name="phone_number" class="inp" placeholder="080..." >
                    </div>
                    <div class="field">
                        <label>National Identity Number (NIN)</label>
                        <input type="text" name="nin" id="mobile_nin" class="inp" placeholder="11 Digits">
                    </div>
                    <div class="field">
                        <label>Remarks</label>
                        <textarea name="remarks" id="mobile_remarks" class="inp" rows="2" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
            </section>

            <!-- 5. LOCATION -->
            <section class="section-card collapsed" id="sec-location">
                <div class="section-header">
                    <div class="section-icon" style="background: rgba(139, 92, 246, 0.1); color: #a78bfa;">
                        <i data-lucide="map" style="width: 16px;"></i>
                    </div>
                    <h2>Property Location</h2>
                </div>
                <div class="section-body">
                    <div class="field">
                        <label>Plot No <span class="req">*</span></label>
                        <input type="text" name="plot_no" id="plot_no" class="inp loc-trigger" placeholder="e.g. 101" >
                    </div>
                    <div class="field">
                        <label>Street Name</label>
                        <select name="street_name" id="streetSelect" class="inp loc-trigger">
                            <option value="">Select Street</option>
                        </select>
                        <input type="text" id="streetSelectOther" class="inp loc-trigger hidden mt-2" placeholder="Specify street name">
                    </div>
                    <div class="field">
                        <label>District</label>
                        <select name="district" id="districtSelect" class="inp loc-trigger">
                            <option value="">Loading districts...</option>
                        </select>
                        <input type="text" id="districtSelectOther" class="inp loc-trigger hidden mt-2" placeholder="Specify district">
                    </div>
                    <div class="field">
                        <label>LGA <span class="req">*</span></label>
                        <select name="lga" id="lgaSelect" class="inp loc-trigger" required>
                            <option value="">Loading LGAs...</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Full Address <span class="req">*</span></label>
                        <textarea name="location" id="fullLocation" class="inp" rows="3"  placeholder="Generating from selections..."></textarea>
                    </div>

                    <div class="field">
                        <label>Geographic Coordinates</label>
                        <div id="map"></div>
                        <div class="map-controls">
                            <button type="button" class="btn-geo" onclick="getCurrentLocation()">
                                <i data-lucide="crosshair" style="width: 14px;"></i>
                                PIN CURRENT
                            </button>
                            <div class="coord-badge">
                                <span id="coordDisplay">12.0022, 8.5920</span>
                            </div>
                        </div>
                        <input type="hidden" name="latitude" id="lat" value="12.0022">
                        <input type="hidden" name="longitude" id="lng" value="8.5920">
                    </div>
                </div>
            </section>
        </form>
    </main>

    <!-- BOTTOM NAV -->
    <footer class="bottom-nav">
        <button type="button" class="btn btn-ghost" onclick="resetForm()">
            <i data-lucide="rotate-ccw" style="width: 18px;"></i>
            <span>Clear</span>
        </button>
        <button type="button" id="saveBtn" class="btn btn-primary" onclick="submitForm()">
            <i data-lucide="save" style="width: 18px;"></i>
            <span>Save Record</span>
        </button>
    </footer>

    <!-- TOAST -->
    <div id="toast" class="toast">
        <i data-lucide="info" style="width: 16px;"></i>
        <span id="toastMsg">Message here</span>
    </div>

    <script>
        let lookupData = null;
        let selectedItems = [];
        let allBanks = [];
        let isInitializing = true;

        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } catch (e) {
                console.error('Lucide error:', e);
            }
            
            initMap();
            loadLookupData();
        });

        // Fetch Lookup Data
        async function loadLookupData() {
            try {
                const res = await fetch("{{ route('valuation-compensations.mobile.lookup') }}");
                const data = await res.json();
                lookupData = data;

                // Populate Projects
                const pSel = document.getElementById('projectSelect');
                pSel.innerHTML = '<option value="">Select Project</option>';
                if (data.projects) {
                    data.projects.forEach(p => {
                        const displayCode = p.fileno || p.code;
                        pSel.innerHTML += `<option value="${p.id}">${p.name} (${displayCode})</option>`;
                    });
                }



                // Populate Streets
                const stSel = document.getElementById('streetSelect');
                stSel.innerHTML = '<option value="">Select Street</option>';
                if (data.streets) {
                    data.streets.forEach(s => {
                        stSel.innerHTML += `<option value="${s.name}">${s.name}</option>`;
                    });
                }
                stSel.innerHTML += `<option value="Other">Other</option>`;

                // Populate LGAs
                const lgaSel = document.getElementById('lgaSelect');
                lgaSel.innerHTML = '<option value="">Select LGA</option>';
                if (data.lgas) {
                    data.lgas.forEach(l => {
                        lgaSel.innerHTML += `<option value="${l.LGAName}">${l.LGAName}</option>`;
                    });
                }

                // Populate Districts
                const dSel = document.getElementById('districtSelect');
                dSel.innerHTML = '<option value="">Select District</option>';
                if (data.districts) {
                    data.districts.forEach(d => {
                        dSel.innerHTML += `<option value="${d.name}">${d.name}</option>`;
                    });
                }
                dSel.innerHTML += `<option value="Other">Other</option>`;

                // Banks
                allBanks = data.banks || [];

                // Populate Valuation Items and Dropdowns
                const itemGrid = document.getElementById('valuationItemsGrid');
                const buildingSel = document.querySelector('.building-type-mobile-select');
                const buildingStageSel = document.querySelector('.building-stage-mobile-select');
                const compSel = document.getElementById('completionStage');
                itemGrid.innerHTML = '';
                if (buildingSel) buildingSel.innerHTML = '<option value="">Select Type</option>';
                if (buildingStageSel) buildingStageSel.innerHTML = '<option value="">Select Stage</option>';
                if (compSel) compSel.innerHTML = '<option value="">Select</option>';

                if (data.valuationItems) {
                    const linearItems = ['Cornstalk Fence', 'Sandcare Wall', 'Wire mesh', 'Mud Wall'];
                    const volumeItems = ['Pavement', 'Mass concrete pavement', 'Interlock Court yard', 'DPC', 'Fish pond'];
                    
                    const sections = {
                        linear: { title: 'Linear Measurements (Fencing/Walls)', sub: 'L + B × Rate', icon: 'maximize-2', items: [] },
                        volume: { title: 'Volume Measurements (Pavement/DPC)', sub: 'L × B × Rate', icon: 'box', items: [] },
                        other: { title: 'Other Structures', sub: 'Standard & Area Based', icon: 'archive', items: [] }
                    };

                    // Populate Building Types first
                    if (data.buildingTypes) {
                        data.buildingTypes.forEach(type => {
                            if (buildingSel) buildingSel.innerHTML += `<option value="${type.name}">${type.name}</option>`;
                        });
                        data.valuationItems.filter(i => i.type === 'CompletionStage').forEach(stage => {
                            if (buildingStageSel) buildingStageSel.innerHTML += `<option value="${stage.name}">${stage.name}</option>`;
                        });
                    }

                    data.valuationItems.forEach(item => {
                        if (item.type === 'CompletionStage') {
                            if (compSel) compSel.innerHTML += `<option value="${item.name}">${item.name}</option>`;
                        } else if (item.type === 'Other') {
                            if (linearItems.includes(item.name)) sections.linear.items.push(item);
                            else if (volumeItems.includes(item.name)) sections.volume.items.push(item);
                            else sections.other.items.push(item);
                        }
                    });

                    Object.values(sections).forEach(sec => {
                        if (sec.items.length > 0) {
                            itemGrid.innerHTML += `
                                <div class="items-section-header">
                                    <h3><i data-lucide="${sec.icon}" style="width:10px;"></i> ${sec.title}</h3>
                                    <p>${sec.sub}</p>
                                </div>
                            `;
                            sec.items.forEach(item => {
                                let type = 'standard';
                                if (linearItems.includes(item.name)) type = 'linear';
                                else if (volumeItems.includes(item.name)) type = 'volume';

                                itemGrid.innerHTML += `
                                    <div class="check-item ${item.name === 'Others' || item.name === 'Other' ? 'special-other' : ''}" data-val="${item.name}" data-type="${type}">
                                        <div class="check-box"><i data-lucide="check" style="width:10px;height:10px;"></i></div>
                                        <span class="check-label">${item.name}</span>
                                    </div>
                                    <div class="calc-box" id="calc-${item.name.replace(/\s+/g, '-')}">
                                        <div class="calc-grid">
                                            ${type === 'linear' ? `
                                                <div class="calc-field" style="grid-column: 1/-1;">
                                                    <label>Dimensions (Sum: L + B + ... N)</label>
                                                    <input type="text" class="calc-inp d-val" placeholder="e.g. 15.5 + 10">
                                                </div>
                                                <div class="calc-field" style="grid-column: 1/-1;">
                                                    <div class="flex justify-between items-center px-3 py-2 bg-slate-50 rounded-lg border border-slate-100">
                                                        <span class="text-[10px] font-bold text-slate-500 uppercase">Total Length (L+B)</span>
                                                        <span class="l-total-display text-[12px] font-black text-blue-600">0.00m</span>
                                                    </div>
                                                    <input type="hidden" class="l-val" value="0">
                                                </div>
                                            ` : ''}
                                            ${type === 'volume' ? `
                                                <div class="calc-field">
                                                    <label>Length (L)</label>
                                                    <input type="number" class="calc-inp l-val" placeholder="0.00" step="0.01">
                                                </div>
                                                <div class="calc-field">
                                                    <label>Breadth (B)</label>
                                                    <input type="number" class="calc-inp w-val" placeholder="0.00" step="0.01">
                                                </div>
                                                <div class="calc-field" style="grid-column: 1/-1;">
                                                    <div class="flex justify-between items-center px-3 py-2 bg-slate-50 rounded-lg border border-slate-100">
                                                        <span class="text-[10px] font-bold text-slate-500 uppercase">Area Covered (L×B)</span>
                                                        <span class="v-total-display text-[12px] font-black text-indigo-600">0.00m²</span>
                                                    </div>
                                                </div>
                                            ` : ''}
                                            ${type === 'standard' ? `
                                                <div class="calc-field" style="grid-column: 1/-1; display: none;">
                                                    <label>Quantity / Area</label>
                                                    <input type="number" class="calc-inp q-val" value="1" step="0.01">
                                                </div>
                                            ` : ''}
                                            <div class="calc-field" style="grid-column: 1/-1;">
                                                <label>${type === 'standard' ? 'Amount (₦)' : (type === 'linear' ? 'Rate (₦/m)' : 'Rate (₦/m³)')}</label>
                                                <input type="number" class="calc-inp r-val" placeholder="0.00" step="0.01">
                                            </div>
                                            <div class="calc-result">
                                                <span class="calc-total-label">SUB AMOUNT</span>
                                                <span class="calc-total-val">₦0.00</span>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                    });
                    
                    // Re-initialize click listeners for new items
                    initCheckItems();
                    initCalcListeners();
                    
                    isInitializing = false; // System is now ready for calculations
                    if (window.lucide) window.lucide.createIcons();
                }

            } catch (err) {
                console.error('Initialization Error:', err);
                isInitializing = false;
                showToast('Failed to load system data. Check connection.', 'danger');
            }
        }


        // Bank Search Logic
        const bankSearch = document.getElementById('bankSearch');
        const bankResults = document.getElementById('bankResults');
        const selectedBankLogo = document.getElementById('selectedBankLogo');
        const bankNameVal = document.getElementById('bankNameVal');

        bankSearch.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            if (!q) {
                bankResults.classList.add('hidden');
                return;
            }

            const matches = allBanks.filter(b => b.name.toLowerCase().includes(q));
            if (!matches.length) {
                bankResults.innerHTML = '<div style="padding:16px;text-align:center;font-size:12px;color:var(--text-dim);">No banks found</div>';
            } else {
                bankResults.innerHTML = matches.map(b => {
                    const fallback = `https://ui-avatars.com/api/?name=${encodeURIComponent(b.name)}&background=random`;
                    const logoUrl = b.logo || fallback;
                    return `
                        <div class="bank-item" onclick="selectBank('${b.name}', '${logoUrl}')" style="padding:12px 16px;display:flex;align-items:center;gap:12px;cursor:pointer;border-bottom:1px solid var(--border);">
                            <div class="selected-logo-wrap" style="width:32px;height:32px;background:var(--surface2);border-radius:8px;overflow:hidden;display:flex;align-items:center;justify-center:center;">
                                <img src="${logoUrl}" alt="" style="width:100%;height:100%;object-fit:contain;" onerror="this.src='${fallback}'">
                            </div>
                            <span style="font-size:13px;font-weight:600;color:var(--text);">${b.name}</span>
                        </div>
                    `;
                }).join('');
            }
            bankResults.classList.remove('hidden');
        });

        window.selectBank = function(title, logo) {
            bankSearch.value = title;
            bankNameVal.value = title;
            bankResults.classList.add('hidden');
            
            selectedBankLogo.querySelector('img').src = logo || `https://ui-avatars.com/api/?name=${title}&background=random`;
            selectedBankLogo.classList.remove('hidden');
            bankSearch.style.paddingLeft = '48px';
            bankSearch.style.fontWeight = '700';
            bankSearch.style.color = '#fff';
        };

        // Close bank dropdown on click outside
        document.addEventListener('click', e => {
            if (!e.target.closest('.inp-wrap')) bankResults.classList.add('hidden');
        });

        // Building Type/Stage Other logic handled by initBuildingAssessmentListenersMobile below
        // Completion Stage Other toggle (Main form, if still used)
        const cStageSel = document.getElementById('completionStage');
        if (cStageSel) {
            cStageSel.addEventListener('change', function() {
                const otherInp = document.getElementById('completionStageOther');
                if (this.value === 'Other' || this.value === 'Others') {
                    otherInp.classList.remove('hidden');
                    otherInp.focus();
                } else {
                    otherInp.classList.add('hidden');
                    otherInp.value = '';
                }
            });
        }

        // Initialize Check Items functionality
        function initCheckItems() {
            document.querySelectorAll('.check-item').forEach(item => {
                item.onclick = function() {
                    this.classList.toggle('active');
                    const val = this.dataset.val;
                    const calcBox = document.getElementById(`calc-${val.replace(/\s+/g, '-')}`);
                    
                    if (this.classList.contains('active')) {
                        if (!selectedItems.includes(val)) selectedItems.push(val);
                        if (val === 'Others' || val === 'Other') {
                            document.getElementById('compItemsOtherText').classList.remove('hidden');
                        }
                        if (calcBox) calcBox.style.display = 'block';
                    } else {
                        selectedItems = selectedItems.filter(i => i !== val);
                        if (val === 'Others' || val === 'Other') {
                            document.getElementById('compItemsOtherText').classList.add('hidden');
                        }
                        if (calcBox) {
                            calcBox.style.display = 'none';
                            // Reset values in calc box
                            calcBox.querySelectorAll('input').forEach(inp => inp.value = '');
                            calcBox.querySelector('.calc-total-val').textContent = '₦0.00';
                            box.dataset.total = 0;
                        }
                    }
                    
                    updateItemsValue();
                    calculateAllCompensation();
                };
            });
        }

        function initCalcListeners() {
            // Add input listeners for all calc inputs
            document.querySelectorAll('.calc-inp').forEach(inp => {
                // Use a named function to avoid duplicate listeners if called multiple times
                inp.oninput = function() {
                    const box = this.closest('.calc-box');
                    if (!box) return;

                    const type = box.previousElementSibling.dataset.type;
                    const r = parseFloat(box.querySelector('.r-val')?.value) || 0;

                    let subTotal = 0;
                    if (type === 'linear') {
                        const dStr = box.querySelector('.d-val')?.value || '';
                        const totalL = dStr.split('+')
                            .map(s => parseFloat(s.trim()))
                            .filter(n => !isNaN(n))
                            .reduce((sum, n) => sum + n, 0);
                        
                        box.querySelector('.l-val').value = totalL;
                        box.querySelector('.l-total-display').textContent = totalL.toFixed(2) + 'm';
                        subTotal = totalL * r;
                    } else if (type === 'volume') {
                        const l = parseFloat(box.querySelector('.l-val')?.value) || 0;
                        const w = parseFloat(box.querySelector('.w-val')?.value) || 0;
                        const area = l * w;
                        box.querySelector('.v-total-display').textContent = area.toFixed(2) + 'm²';
                        subTotal = area * r;
                    } else {
                        const q = parseFloat(box.querySelector('.q-val')?.value) || 1;
                        subTotal = q * r;
                    }

                    box.querySelector('.calc-total-val').textContent = '₦' + subTotal.toLocaleString(undefined, {minimumFractionDigits: 2});
                    box.dataset.total = subTotal;

                    updateItemsValue();
                    calculateAllCompensation();
                };
            });
        }

        function updateItemsValue() {
            const finalItems = [];
            document.querySelectorAll('.check-item.active').forEach(item => {
                const name = item.dataset.val;
                const box = document.getElementById(`calc-${name.replace(/\s+/g, '-')}`);
                if (box && box.dataset.total && parseFloat(box.dataset.total) > 0) {
                    finalItems.push(`${name} (₦${parseFloat(box.dataset.total).toLocaleString()})`);
                } else {
                    finalItems.push(name);
                }
            });
            document.getElementById('compItemsVal').value = finalItems.join(', ');
        }

        function calculateBuildingRowMobile(row) {
            const length = parseFloat(row.querySelector('.building-mobile-length').value) || 0;
            const breadth = parseFloat(row.querySelector('.building-mobile-breadth').value) || 0;
            const floors = parseFloat(row.querySelector('.building-mobile-floors')?.value) || 1;

            // If L and B are provided, Area Covered = footprint (L×B) × floors (total built-up area)
            if (length > 0 && breadth > 0) {
                const areaVal = length * breadth * floors;
                row.querySelector('.building-mobile-area').value = areaVal.toFixed(2);
            }

            const area = parseFloat(row.querySelector('.building-mobile-area').value) || 0;
            const rate = parseFloat(row.querySelector('.building-mobile-rate').value) || 0;
            // Floors are already baked into Area Covered, so Amount = Area × Rate.
            const total = area * rate;
            row.querySelector('.building-mobile-comp').value = total.toFixed(2);
        }

        function calculateAllCompensation() {
            if (isInitializing) return; // Prevent loops during startup

            let totalLength = 0;
            let totalBreadth = 0;
            let totalArea = 0;
            let totalBuildingComp = 0;
            let rateSum = 0;
            let rateCount = 0;
            let weightedRateSum = 0;

            document.querySelectorAll('.building-type-mobile-row').forEach(row => {
                const length = parseFloat(row.querySelector('.building-mobile-length')?.value) || 0;
                const breadth = parseFloat(row.querySelector('.building-mobile-breadth')?.value) || 0;
                const area = parseFloat(row.querySelector('.building-mobile-area')?.value) || 0;
                const rate = parseFloat(row.querySelector('.building-mobile-rate')?.value) || 0;
                const comp = parseFloat(row.querySelector('.building-mobile-comp')?.value) || 0;

                totalLength += length;
                totalBreadth += breadth;
                totalArea += area;
                totalBuildingComp += comp;

                if (rate > 0) {
                    rateSum += rate;
                    rateCount++;
                    weightedRateSum += area * rate;
                }
            });

            // Compute average rate
            let averageRate = 0;
            if (totalArea > 0 && weightedRateSum > 0) {
                averageRate = weightedRateSum / totalArea;
            } else if (rateCount > 0) {
                averageRate = rateSum / rateCount;
            }

            // Write to global readonly fields
            const gLen = document.getElementById('length');
            const gBrd = document.getElementById('breadth');
            const gArea = document.getElementById('areaCovered');
            const gRate = document.getElementById('rateOfCost');

            if (gLen) gLen.value = totalLength > 0 ? totalLength.toFixed(2) : '';
            if (gBrd) gBrd.value = totalBreadth > 0 ? totalBreadth.toFixed(2) : '';
            if (gArea) gArea.value = totalArea > 0 ? totalArea.toFixed(2) : '';
            if (gRate) gRate.value = averageRate > 0 ? averageRate.toFixed(2) : '';

            // Grand total includes sub-items
            let grandTotal = totalBuildingComp;
            document.querySelectorAll('.calc-box').forEach(box => {
                if (box.style.display === 'block') {
                    grandTotal += parseFloat(box.dataset.total) || 0;
                }
            });

            const mainComp = document.getElementById('compensation_amount');
            if (mainComp) {
                mainComp.value = grandTotal > 0 ? grandTotal.toFixed(2) : '';
            }
        }

        function isOtherValue(value) {
            return ['other', 'others'].includes((value || '').toString().trim().toLowerCase());
        }

        function getLocationSelectValue(selectId) {
            const select = document.getElementById(selectId);
            const otherInput = document.getElementById(selectId + 'Other');
            if (!select) return '';
            if (isOtherValue(select.value)) {
                return (otherInput?.value || '').trim();
            }
            return (select.value || '').trim();
        }

        function toggleLocationOther(selectId) {
            const select = document.getElementById(selectId);
            const otherInput = document.getElementById(selectId + 'Other');
            if (!select || !otherInput) return;

            const shouldShow = isOtherValue(select.value);
            otherInput.classList.toggle('hidden', !shouldShow);
            if (shouldShow) {
                otherInput.focus();
            } else {
                otherInput.value = '';
            }
            buildAddress();
        }

        function setSelectOrOther(selectId, value) {
            const select = document.getElementById(selectId);
            const otherInput = document.getElementById(selectId + 'Other');
            const normalizedValue = (value || '').toString().trim();
            if (!select || !normalizedValue) return;

            const hasOption = Array.from(select.options).some(option => option.value === normalizedValue);
            if (hasOption) {
                select.value = normalizedValue;
                if (otherInput) otherInput.value = '';
            } else {
                select.value = 'Other';
                if (otherInput) otherInput.value = normalizedValue;
            }
            toggleLocationOther(selectId);
        }

        ['streetSelect', 'districtSelect'].forEach(id => {
            const select = document.getElementById(id);
            const otherInput = document.getElementById(id + 'Other');
            if (select) {
                select.addEventListener('change', () => toggleLocationOther(id));
            }
            if (otherInput) {
                otherInput.addEventListener('input', buildAddress);
            }
        });

        // Project Change -> Load Workers
        document.getElementById('projectSelect').addEventListener('change', async function() {
            const pId = this.value;
            const wSel = document.getElementById('workerSelect');
            const spSel = document.getElementById('subProjectSelect');
            const wBadge = document.getElementById('workerBadge');
            const ourRef = document.getElementById('mobile_our_ref');
            const yourRef = document.getElementById('mobile_your_ref');
            
            if (!pId) {
                wSel.disabled = true;
                spSel.disabled = true;
                wSel.innerHTML = '<option value="">Select Project First</option>';
                spSel.innerHTML = '<option value="">Select Project First</option>';
                wBadge.classList.add('hidden');
                document.getElementById('mobile-project-info').classList.add('hidden');
                ourRef.value = '';
                yourRef.value = '';
                document.getElementById('mobile_project_code').value = '';
                document.getElementById('mobile_project_fileno').value = '';
                return;
            }

            // Backfill references and update summary
            if (lookupData && lookupData.projects) {
                const proj = lookupData.projects.find(p => p.id == pId);
                if (proj) {
                    ourRef.value = proj.our_reference || '';
                    yourRef.value = proj.your_reference || '';
                    
                    document.getElementById('mobile_project_code').value = proj.code;
                    document.getElementById('mobile_project_fileno').value = proj.fileno;
                    
                    // Update Summary UI
                    const safeSetText = (id, text) => {
                        const el = document.getElementById(id);
                        if (el) el.textContent = text;
                    };

                    safeSetText('m_proj_id', proj.id);
                    safeSetText('m_proj_fileno', proj.fileno);
                    safeSetText('m_proj_code', proj.code);
                    safeSetText('m_proj_workers', proj.workers_count || 0);
                    safeSetText('m_proj_filled', proj.valuations_count || 0);
                    
                    const infoPanel = document.getElementById('mobile-project-info');
                    if (infoPanel) infoPanel.classList.remove('hidden');
                    if (window.lucide) window.lucide.createIcons();

                    // Populate Sub-projects
                    spSel.disabled = false;
                    spSel.innerHTML = '<option value="">Select Sub-Project</option>';
                    if (proj.sub_projects && proj.sub_projects.length > 0) {
                        proj.sub_projects.forEach(sp => {
                            spSel.innerHTML += `<option value="${sp.id}">${sp.name}</option>`;
                        });
                    } else {
                        spSel.innerHTML = '<option value="">No Sub-Projects Found</option>';
                        spSel.disabled = true;
                    }

                    // Backfill location scope
                    if (proj.district) {
                        setSelectOrOther('districtSelect', proj.district);
                    }
                    if (proj.lga) {
                        document.getElementById('lgaSelect').value = proj.lga;
                    }
                    buildAddress();
                }
            }

            wSel.disabled = false;
            wSel.innerHTML = '<option value="">Loading Workers...</option>';
            
            try {
                const res = await fetch(`{{ url('valuation-compensations/mobile/workers') }}/${pId}`);
                const data = await res.json();
                wSel.innerHTML = '<option value="">Select Worker</option>';
                data.workers.forEach(w => {
                    wSel.innerHTML += `<option value="${w.id}" data-code="${w.worker_code}">${w.name}</option>`;
                });
            } catch (err) {
                showToast('Failed to load workers', 'danger');
            }
        });

        document.getElementById('workerSelect').addEventListener('change', function() {
            const code = this.options[this.selectedIndex].dataset.code;
            const badge = document.getElementById('workerBadge');
            if (code) {
                document.getElementById('workerCodeDisplay').textContent = code;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        });

        // Other Specify Logic for Dropdowns
        ['structureType', 'completionStage'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('change', function() {
                    const otherField = document.getElementById(id + 'Other');
                    if (otherField) {
                        if (this.value.toLowerCase().includes('other')) {
                            otherField.classList.remove('hidden');
                        } else {
                            otherField.classList.add('hidden');
                        }
                    }
                });
            }
        });

        // Calculation
        ['buildingCount', 'areaCovered', 'rateOfCost'].forEach(id => {
            document.getElementById(id).addEventListener('input', calculateAllCompensation);
        });

        // Address Builder
        function buildAddress() {
            // The plot number is not part of a full location — it has its own
            // field and was only being repeated at the front of the string.
            const street = getLocationSelectValue('streetSelect');
            const district = getLocationSelectValue('districtSelect');
            const lga = document.getElementById('lgaSelect').value;

            let parts = [];
            if (street) parts.push(street);
            if (district) parts.push(district + ' District');
            if (lga) parts.push(lga + ' LGA');
            parts.push('Kano State');
            
            document.getElementById('fullLocation').value = parts.join(', ');
        }

        document.querySelectorAll('.loc-trigger').forEach(el => {
            el.addEventListener('change', buildAddress);
            el.addEventListener('input', buildAddress);
        });

        // Toggle Section Collapse
        document.querySelectorAll('.section-header').forEach(header => {
            header.addEventListener('click', () => {
                const card = header.closest('.section-card');
                card.classList.toggle('collapsed');
            });
            // Add Chevron Icon
            const icon = document.createElement('i');
            icon.setAttribute('data-lucide', 'chevron-down');
            icon.style.width = '16px';
            icon.style.color = 'var(--text-dim)';
            header.appendChild(icon);
        });

        // Navigation Functionality
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                const targetId = item.dataset.target;
                const targetSection = document.getElementById(targetId);
                
                // Expand the section if collapsed
                targetSection.classList.remove('collapsed');
                
                // Scroll to it
                const offset = 120; // topbar + navstrip height
                const bodyRect = document.body.getBoundingClientRect().top;
                const elementRect = targetSection.getBoundingClientRect().top;
                const elementPosition = elementRect - bodyRect;
                const offsetPosition = elementPosition - offset;

                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            });
        });

        // Navigation Highlight on Scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    document.querySelectorAll('.nav-item').forEach(item => {
                        item.classList.toggle('active', item.dataset.target === id);
                    });
                }
            });
        }, { threshold: 0.2 });

        document.querySelectorAll('section').forEach(section => observer.observe(section));

        // Submit Form
        async function submitForm() {
            const form = document.getElementById('vfcForm');
            const formData = new FormData(form);
            formData.set('street_name', getLocationSelectValue('streetSelect'));
            formData.set('district', getLocationSelectValue('districtSelect'));
            buildAddress();
            formData.set('location', document.getElementById('fullLocation')?.value || '');

            // Custom Validation
            const requiredFields = [
                { id: 'projectSelect', name: 'Project', section: 'sec-project' },
                { id: 'workerSelect', name: 'Assigned Worker', section: 'sec-project' },
                { id: 'owner_name', name: 'Owner Full Name', section: 'sec-owner' },
                { id: 'building_type_final_mobile', name: 'Building Type', section: 'sec-building' },
                { id: 'buildingCount', name: 'Building Count', section: 'sec-building' },
                { id: 'compensation_amount', name: 'Amount of Compensation', section: 'sec-building' },
                { id: 'account_name', name: 'Account Name', section: 'sec-payment' },
                { id: 'account_number', name: 'Account Number', section: 'sec-payment' },
                { id: 'phone_number', name: 'Phone Number', section: 'sec-payment' },
                { id: 'plot_no', name: 'Plot No', section: 'sec-location' },
                { id: 'lgaSelect', name: 'LGA', section: 'sec-location' },
                { id: 'fullLocation', name: 'Full Address', section: 'sec-location' }
            ];

            for (const field of requiredFields) {
                const el = document.getElementById(field.id);
                if (!el || !el.value || el.value.trim() === '') {
                    showToast(`⚠️ ${field.name} is required`, 'warning');
                    
                    // Expand and scroll to the section
                    const section = document.getElementById(field.section);
                    if (section) {
                        section.classList.remove('collapsed');
                        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => el.focus(), 500);
                    }
                    return;
                }
            }

            for (const field of [
                { selectId: 'streetSelect', inputId: 'streetSelectOther', name: 'Street Name', section: 'sec-location' },
                { selectId: 'districtSelect', inputId: 'districtSelectOther', name: 'District', section: 'sec-location' }
            ]) {
                const select = document.getElementById(field.selectId);
                const input = document.getElementById(field.inputId);
                if (select && input && isOtherValue(select.value) && input.value.trim() === '') {
                    showToast(`Specify ${field.name}`, 'warning');
                    const section = document.getElementById(field.section);
                    if (section) {
                        section.classList.remove('collapsed');
                        section.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => input.focus(), 500);
                    }
                    return;
                }
            }

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner" style="width:20px;height:20px;border-width:2px;"></div> Saving...';

            // Combine dropdowns and checkboxes for compensated_items
            let finalItems = [...selectedItems];
            document.querySelectorAll('.struct-dropdown').forEach(sel => {
                const val = sel.value;
                const label = sel.previousElementSibling.textContent.trim();
                if (val) {
                    finalItems.unshift(`[${label}: ${val}]`);
                }
            });
            formData.set('compensated_items', finalItems.join(', '));

            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch("{{ route('valuation-compensations.mobile.save') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });

                const result = await res.json();
                if (result.success) {
                    showToast('✅ Record Saved: ' + result.file_number, 'success');
                    resetForm();
                } else {
                    showToast('❌ ' + result.message, 'danger');
                }
            } catch (err) {
                showToast('❌ Server Error', 'danger');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="save" style="width: 18px;"></i> <span>Save Record</span>';
                lucide.createIcons();
            }
        }

        function resetForm() {
            if (!confirm('Clear all entries?')) return;
            document.getElementById('vfcForm').reset();
            selectedItems = [];
            document.querySelectorAll('.check-item').forEach(i => i.classList.remove('active'));
            
            // Sync building rows back to 1
            document.getElementById('buildingCount').value = 1;
            syncBuildingTypesMobile();

            // Clear compensation amount and aggregate fields
            ['length', 'breadth', 'areaCovered', 'rateOfCost', 'compensation_amount'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            // Clear bank logo
            const bankLogo = document.getElementById('selectedBankLogo');
            if (bankLogo) {
                bankLogo.classList.add('hidden');
                document.getElementById('bankSearch').style.paddingLeft = '14px';
            }

            document.getElementById('workerBadge').classList.add('hidden');
            
            // Hide other specify fields
            ['structureTypeOther', 'completionStageOther', 'buildingTypeOther', 'compItemsOtherText', 'streetSelectOther', 'districtSelectOther'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.classList.add('hidden');
                    if ('value' in el) el.value = '';
                }
            });

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function showToast(msg, type) {
            const t = document.getElementById('toast');
            const tm = document.getElementById('toastMsg');
            tm.textContent = msg;
            t.style.borderColor = type === 'success' ? 'var(--success)' : 'var(--danger)';
            t.classList.add('show');
            setTimeout(() => t.classList.remove('show'), 3000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            loadLookupData();
            initMap();

            // Keyboard Visibility Detection (Focus-based for maximum compatibility)
            const formInputs = document.querySelectorAll('input, textarea, select');
            formInputs.forEach(input => {
                input.addEventListener('focus', () => {
                    document.body.classList.add('keyboard-visible');
                });
                input.addEventListener('blur', () => {
                    // Small delay to check if focus moved to another input
                    setTimeout(() => {
                        if (!document.activeElement || !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                            document.body.classList.remove('keyboard-visible');
                        }
                    }, 100);
                });
            });

            // Fallback for visualViewport resizing
            if (window.visualViewport) {
                const initialHeight = window.visualViewport.height;
                window.visualViewport.addEventListener('resize', () => {
                    if (window.visualViewport.height < initialHeight * 0.85) {
                        document.body.classList.add('keyboard-visible');
                    }
                });
            }

            // Auto-capitalize all text inputs
            document.querySelectorAll('input[type="text"], textarea, input[type="search"]').forEach(input => {
                input.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            });
        });

        /* ── MAP LOGIC ── */
        let map, marker;
        function initMap() {
            // Default: Kano, Nigeria
            const startLat = 12.0022;
            const startLng = 8.5920;

            map = L.map('map', {
                zoomControl: false
            }).setView([startLat, startLng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OSM'
            }).addTo(map);

            marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

            marker.on('dragend', function(e) {
                const pos = marker.getLatLng();
                updateCoords(pos.lat, pos.lng);
            });

            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateCoords(e.latlng.lat, e.latlng.lng);
            });

            // Adjust map size when section is expanded
            document.querySelector('#sec-location .section-header').addEventListener('click', () => {
                setTimeout(() => map.invalidateSize(), 100);
            });
        }

        function updateCoords(lat, lng) {
            const fixedLat = lat.toFixed(6);
            const fixedLng = lng.toFixed(6);
            document.getElementById('lat').value = fixedLat;
            document.getElementById('lng').value = fixedLng;
            document.getElementById('coordDisplay').textContent = `${fixedLat}, ${fixedLng}`;
        }

        function getCurrentLocation() {
            if (!navigator.geolocation) {
                showToast('❌ GPS not supported', 'danger');
                return;
            }

            const btn = document.querySelector('.btn-geo');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> LOCATING...';

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    map.setView([lat, lng], 17);
                    marker.setLatLng([lat, lng]);
                    updateCoords(lat, lng);
                    btn.innerHTML = '<i data-lucide="crosshair" style="width: 14px;"></i> PIN CURRENT';
                    lucide.createIcons();
                    showToast('✅ Location pinned', 'success');
                },
                (err) => {
                    showToast('❌ GPS access denied', 'danger');
                    btn.innerHTML = '<i data-lucide="crosshair" style="width: 14px;"></i> PIN CURRENT';
                    lucide.createIcons();
                },
                { enableHighAccuracy: true }
            );
        }
        // Building Count & Dynamic Types (Mobile)
        document.getElementById('buildingCount').addEventListener('input', function() {
            syncBuildingTypesMobile();
            calculateAllCompensation();
        });

        function syncBuildingTypesMobile() {
            const count = parseInt(document.getElementById('buildingCount').value) || 1;
            const container = document.getElementById('building_types_mobile_container');
            if (!container) return;
            const rows = container.querySelectorAll('.building-type-mobile-row');
            const currentCount = rows.length;

            if (count > currentCount) {
                const template = rows[0].cloneNode(true);
                template.querySelectorAll('select').forEach(sel => sel.value = '');
                template.querySelectorAll('input').forEach(inp => {
                    if (inp.type !== 'hidden') {
                        inp.value = '';
                        if (inp.classList.contains('building-type-mobile-other') || inp.classList.contains('building-stage-mobile-other')) {
                            inp.classList.add('hidden');
                        } else {
                            inp.classList.remove('hidden');
                        }
                    }
                });
                for (let i = 0; i < count - currentCount; i++) {
                    container.appendChild(template.cloneNode(true));
                }
            } else if (count < currentCount) {
                for (let i = 0; i < currentCount - count; i++) {
                    if (container.lastElementChild && container.children.length > 1) {
                        container.lastElementChild.remove();
                    }
                }
            }

            // Update Building Numbers
            container.querySelectorAll('.building-type-mobile-row').forEach((row, idx) => {
                const label = row.querySelector('div');
                if (label) {
                    label.innerHTML = `<i data-lucide="building" style="width: 10px;"></i> Building ${idx + 1}`;
                }
            });
            
            if (window.lucide) window.lucide.createIcons();
            initBuildingAssessmentListenersMobile();
            updateFinalBuildingAssessmentMobile();
        }

        function initBuildingAssessmentListenersMobile() {
            document.querySelectorAll('.building-type-mobile-select').forEach(sel => {
                sel.onchange = function() {
                    const row = this.closest('.building-type-mobile-row');
                    const otherInp = row.querySelector('.building-type-mobile-other');
                    if (this.value === 'Other' || this.value === 'Others') {
                        if (otherInp) {
                            otherInp.classList.remove('hidden');
                            otherInp.focus();
                        }
                    } else {
                        if (otherInp) {
                            otherInp.classList.add('hidden');
                            otherInp.value = '';
                        }
                    }
                    updateFinalBuildingAssessmentMobile();
                };
            });
            document.querySelectorAll('.building-stage-mobile-select').forEach(sel => {
                sel.onchange = function() {
                    const row = this.closest('.building-type-mobile-row');
                    const otherInp = row.querySelector('.building-stage-mobile-other');
                    if (this.value === 'Other' || this.value === 'Others') {
                        if (otherInp) {
                            otherInp.classList.remove('hidden');
                            otherInp.focus();
                        }
                    } else {
                        if (otherInp) {
                            otherInp.classList.add('hidden');
                            otherInp.value = '';
                        }
                    }
                    updateFinalBuildingAssessmentMobile();
                };
            });
            document.querySelectorAll('.building-type-mobile-other, .building-stage-mobile-other').forEach(inp => {
                inp.oninput = updateFinalBuildingAssessmentMobile;
            });

            // Bind listeners for measurement and cost inputs
            document.querySelectorAll('.building-mobile-length, .building-mobile-breadth, .building-mobile-area, .building-mobile-rate, .building-mobile-floors').forEach(inp => {
                inp.oninput = function() {
                    const row = this.closest('.building-type-mobile-row');
                    calculateBuildingRowMobile(row);
                    calculateAllCompensation();
                    updateFinalBuildingAssessmentMobile();
                };
            });
        }

        function updateFinalBuildingAssessmentMobile() {
            const types = [];
            const stages = [];
            const floors = [];
            document.querySelectorAll('.building-type-mobile-row').forEach(row => {
                const typeSel = row.querySelector('.building-type-mobile-select');
                const typeOther = row.querySelector('.building-type-mobile-other');
                if (typeSel) {
                    let val = typeSel.value;
                    if (val === 'Other' || val === 'Others') val = typeOther?.value || val;
                    if (val) types.push(val);
                }

                const stageSel = row.querySelector('.building-stage-mobile-select');
                const stageOther = row.querySelector('.building-stage-mobile-other');
                if (stageSel) {
                    let val = stageSel.value;
                    if (val === 'Other' || val === 'Others') val = stageOther?.value || val;
                    if (val) stages.push(val);
                }

                const floorsInp = row.querySelector('.building-mobile-floors');
                if (floorsInp && floorsInp.value) floors.push(floorsInp.value);
            });
            const typeFinal = document.getElementById('building_type_final_mobile');
            const stageFinal = document.getElementById('completion_stage_final_mobile');
            const floorsFinal = document.getElementById('number_of_floors_final_mobile');
            if (typeFinal) typeFinal.value = types.join(', ');
            if (stageFinal) stageFinal.value = stages.join(', ');
            if (floorsFinal) floorsFinal.value = floors.join(', ');
        }

        // Initialize mobile listeners on start
        initBuildingAssessmentListenersMobile();
    </script>
</body>
</html>
