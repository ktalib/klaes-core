{{-- Shared look of the Survey Mobile screens (login excepted): tokens, app bar, cards, form controls, buttons. --}}
<style>
        :root {
            --primary: #db2777;
            --primary-dark: #be185d;
            --primary-50: #fdf2f8;
            --primary-100: #fce7f3;
            --ink: #1e1b2e;
            --text: #1f2937;
            --muted: #6b7280;
            --line: #e5e7eb;
            --bg: #f6f4f9;
            --card: #ffffff;
            --ok: #059669;
            --warn: #d97706;
            --err: #dc2626;
            --radius: 18px;
            --bar-h: 76px;
            --nav-h: 68px;
            --page-w: 1100px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html { scroll-padding-top: 140px; }
        body {
            font-family: 'Outfit', system-ui, sans-serif; background: var(--bg); color: var(--text);
            font-size: 16px; line-height: 1.45; min-height: 100vh; min-height: 100dvh;
            padding-bottom: calc(var(--bar-h) + env(safe-area-inset-bottom) + 12px);
        }
        button, input, select, textarea { font-family: inherit; font-size: 16px; color: inherit; }
        a { color: var(--primary-dark); }

        /* ---------- App bar ---------- */
        .appbar {
            position: sticky; top: 0; z-index: 40; color: #fff;
            background: linear-gradient(135deg, var(--ink) 0%, #4a1d3f 60%, var(--primary-dark) 100%);
            padding: calc(env(safe-area-inset-top) + 12px) 16px 0;
            box-shadow: 0 6px 20px -8px rgba(30, 27, 46, .5);
        }
        .appbar-row { display: flex; align-items: center; gap: 12px; max-width: var(--page-w); margin: 0 auto; }
        .appbar-logo { width: 38px; height: 38px; border-radius: 12px; background: rgba(255,255,255,.12); display: grid; place-items: center; flex: none; }
        .appbar-logo img { width: 28px; height: 28px; object-fit: contain; }
        .appbar-title { flex: 1; min-width: 0; }
        .appbar-title h1 { font-size: 17px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .appbar-title p { font-size: 12px; opacity: .75; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .icon-btn {
            width: 40px; height: 40px; border-radius: 12px; border: 0; background: rgba(255,255,255,.12); color: #fff;
            display: grid; place-items: center; cursor: pointer; flex: none; position: relative;
        }
        .icon-btn:active { transform: scale(.96); }
        .icon-btn .count {
            position: absolute; top: -4px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px;
            background: #fff; color: var(--primary-dark); font-size: 11px; font-weight: 700; display: grid; place-items: center;
        }

        /* Progress + step chips */
        .progress { max-width: var(--page-w); margin: 12px auto 0; }
        .progress-meta { display: flex; justify-content: space-between; font-size: 13px; opacity: .9; margin-bottom: 6px; }
        .progress-track { height: 4px; background: rgba(255,255,255,.18); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #fff; border-radius: 4px; transition: width .35s ease; }
        .chips { display: flex; gap: 6px; overflow-x: auto; padding: 10px 0 12px; scrollbar-width: none; }
        .chips::-webkit-scrollbar { display: none; }
        .chip {
            flex: none; border: 0; border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer;
            background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); display: flex; align-items: center; gap: 6px;
        }
        .chip .n { width: 18px; height: 18px; border-radius: 50%; background: rgba(255,255,255,.2); display: grid; place-items: center; font-size: 11px; }
        .chip.done .n { background: #34d399; color: var(--ink); }
        .chip.active { background: #fff; color: var(--primary-dark); }
        .chip.active .n { background: var(--primary); color: #fff; }
        .chip.bad .n { background: #fca5a5; color: #7f1d1d; }

        /* ---------- Layout ---------- */
        .wrap { max-width: var(--page-w); margin: 0 auto; padding: 16px; }
        @media (min-width: 1024px) { .wrap { padding: 24px; } }

        .card { background: var(--card); border-radius: var(--radius); box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 24px -12px rgba(30,27,46,.12); border: 1px solid #efedf3; }
        .card-pad { padding: 20px 16px; }
        @media (min-width: 640px) { .card-pad { padding: 28px; } }

        .step { display: none; animation: stepIn .25s ease; }
        .step.active { display: block; }
        @keyframes stepIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .step, .progress-fill { animation: none; transition: none; } }
        .step-head { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 18px; }
        .step-icon { width: 44px; height: 44px; border-radius: 14px; background: var(--primary-50); color: var(--primary); display: grid; place-items: center; font-size: 18px; flex: none; }
        .step-head h2 { font-size: 20px; font-weight: 700; color: var(--ink); line-height: 1.2; }
        .step-head p { font-size: 14px; color: var(--muted); margin-top: 2px; }

        .grid { display: grid; gap: 14px; }
        @media (min-width: 640px) { .grid.two { grid-template-columns: 1fr 1fr; } .span-2 { grid-column: 1 / -1; } }

        .field label, .flabel { display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .req { color: var(--err); }
        .input, select.input, textarea.input {
            width: 100%; min-height: 50px; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 14px;
            background: #fff; outline: none; transition: border-color .2s, box-shadow .2s; appearance: none; -webkit-appearance: none;
        }
        select.input { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M6 8 0 0h12z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 36px; }
        textarea.input { min-height: 96px; resize: vertical; }
        .input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(219,39,119,.12); }
        .input[readonly] { background: #f9fafb; color: var(--muted); }
        .input:disabled { background: #f3f4f6; color: #9ca3af; }
        .field.invalid .input, .field.invalid .picker-box { border-color: var(--err); box-shadow: 0 0 0 3px rgba(220,38,38,.1); }
        .field .err { display: none; color: var(--err); font-size: 13px; margin-top: 5px; }
        .field.invalid .err { display: block; }
        .error-link { border: 0; background: transparent; color: inherit; text-align: left; text-decoration: underline; cursor: pointer; padding: 2px 0; }
        .hint { font-size: 13px; color: var(--muted); margin-top: 5px; }

        .note { display: flex; gap: 10px; padding: 12px 14px; border-radius: 14px; font-size: 14px; background: #f3f4f6; color: #374151; }
        .note i { margin-top: 3px; }
        .note.pink { background: var(--primary-50); color: #831843; }
        .note.green { background: #ecfdf5; color: #065f46; }
        .note.amber { background: #fffbeb; color: #92400e; }

        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 13px; font-weight: 700; }
        .badge.monetary { background: #ecfdf5; color: #047857; }
        .badge.land { background: #eef2ff; color: #4338ca; }

        .section-title { font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin: 22px 0 10px; display: flex; align-items: center; gap: 8px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--line); }

        /* ---------- Picker (searchable multi-select) ---------- */
        .picker { position: relative; }
        .picker-box {
            display: flex; flex-wrap: wrap; gap: 6px; align-items: center; min-height: 50px; padding: 7px 10px;
            border: 1.5px solid var(--line); border-radius: 14px; background: #fff; cursor: text;
        }
        .picker.open .picker-box { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(219,39,119,.12); }
        .tag { display: inline-flex; align-items: center; gap: 6px; background: var(--primary-100); color: #831843; border-radius: 10px; padding: 5px 6px 5px 10px; font-size: 14px; font-weight: 600; max-width: 100%; }
        .tag span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tag button { border: 0; background: rgba(131,24,67,.12); color: inherit; width: 22px; height: 22px; border-radius: 7px; cursor: pointer; flex: none; }
        .picker-search { flex: 1; min-width: 120px; border: 0; outline: none; padding: 6px 4px; background: transparent; }
        .picker-list {
            display: none; position: absolute; left: 0; right: 0; top: calc(100% + 6px); z-index: 30; max-height: 260px; overflow-y: auto;
            background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 18px 40px -12px rgba(30,27,46,.25);
        }
        .picker.open .picker-list { display: block; }
        .picker-list button { display: flex; width: 100%; align-items: center; justify-content: space-between; gap: 8px; text-align: left; border: 0; background: none; padding: 13px 14px; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
        .picker-list button:hover, .picker-list button:focus { background: var(--primary-50); outline: none; }
        .picker-list button.on { color: var(--primary-dark); font-weight: 700; }
        .picker-list .empty { padding: 14px; color: var(--muted); font-size: 14px; }

        /* ---------- Repeating rows ---------- */
        .rows { display: grid; gap: 12px; margin-top: 14px; }
        @media (min-width: 768px) { .rows.two { grid-template-columns: 1fr 1fr; } }
        .row-card { border: 1.5px solid var(--line); border-radius: 16px; padding: 14px; background: #fcfbfd; animation: stepIn .2s ease; }
        .row-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .row-head strong { font-size: 14px; color: var(--ink); display: flex; align-items: center; gap: 8px; }
        .row-head .num { width: 26px; height: 26px; border-radius: 8px; background: var(--primary); color: #fff; display: grid; place-items: center; font-size: 13px; }
        .row-grid { display: grid; gap: 10px; grid-template-columns: 1fr 1fr; }
        .row-grid .full { grid-column: 1 / -1; }
        .row-grid label { font-size: 12px; font-weight: 600; color: var(--muted); display: block; margin-bottom: 4px; }
        .row-grid .input { min-height: 46px; padding: 10px 12px; border-radius: 12px; }
        .line-total { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--line); font-size: 14px; color: var(--muted); }
        .line-total b { color: var(--ink); font-size: 16px; }
        .del { border: 0; background: #fef2f2; color: var(--err); width: 36px; height: 36px; border-radius: 10px; cursor: pointer; }
        .empty-state { text-align: center; padding: 26px 16px; border: 2px dashed var(--line); border-radius: 16px; color: var(--muted); font-size: 14px; margin-top: 14px; }
        .empty-state i { font-size: 26px; color: #d1d5db; display: block; margin-bottom: 8px; }

        /* ---------- Buttons ---------- */
        .btn {
            min-height: 50px; padding: 0 18px; border-radius: 14px; border: 0; cursor: pointer; font-weight: 700;
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform .1s, background .2s;
            text-decoration: none; white-space: nowrap;
        }
        .btn:active { transform: scale(.98); }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 8px 18px -8px rgba(219,39,119,.7); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-success { background: var(--ok); color: #fff; box-shadow: 0 8px 18px -8px rgba(5,150,105,.7); }
        .btn-ghost { background: #f3f4f6; color: #374151; }
        .btn-outline { background: #fff; color: var(--primary-dark); border: 1.5px dashed #f9a8d4; width: 100%; }
        .btn-sm { min-height: 40px; padding: 0 12px; font-size: 14px; border-radius: 12px; }
        .btn[disabled] { opacity: .6; pointer-events: none; }

        /* Sticky action bar */
        .actionbar {
            position: fixed; left: 0; right: 0; bottom: 0; z-index: 50; background: rgba(255,255,255,.96);
            backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-top: 1px solid var(--line);
            padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
        }
        .actionbar-inner { max-width: var(--page-w); margin: 0 auto; display: flex; gap: 10px; }
        .has-mobile-footer { padding-bottom: calc(var(--nav-h) + env(safe-area-inset-bottom) + 20px); }
        .has-register-actions { padding-bottom: calc(var(--nav-h) + var(--bar-h) + env(safe-area-inset-bottom) + 20px); }
        .has-register-actions .actionbar { bottom: calc(var(--nav-h) + env(safe-area-inset-bottom)); padding-bottom: 12px; }
        .has-register-actions .toast { bottom: calc(var(--nav-h) + var(--bar-h) + env(safe-area-inset-bottom) + 20px); }
        .mobile-footer { position: fixed; bottom: 0; left: 0; right: 0; z-index: 50; background: var(--ink); padding-bottom: env(safe-area-inset-bottom); border-top: 1px solid rgba(255,255,255,.12); }
        .mobile-footer-inner { display: flex; height: var(--nav-h); max-width: var(--page-w); margin: 0 auto; }
        .mobile-tab { flex: 1; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 4px; border: 0; background: transparent; color: #a8a5b5; text-decoration: none; font-size: 12px; font-weight: 600; cursor: pointer; }
        .mobile-tab i { font-size: 23px; }
        .mobile-tab.active { color: #34d399; }
        .mobile-tab:focus-visible { outline: 2px solid #34d399; outline-offset: -4px; }
        .has-mobile-footer .fab { bottom: calc(var(--nav-h) + env(safe-area-inset-bottom) + 16px); }
        .actionbar .btn { flex: 1; }
        .actionbar .btn-ghost { flex: 0 0 auto; }
        @media (min-width: 768px) { .actionbar .btn { flex: 0 0 auto; min-width: 150px; } .actionbar-inner .spacer { flex: 1; } }

        /* ---------- Summary / compensation ---------- */
        .stat { border-radius: 18px; padding: 20px; text-align: center; background: linear-gradient(135deg, var(--primary-50), #fff); border: 1.5px solid var(--primary-100); }
        .stat .lbl { font-size: 13px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .stat .big { font-size: clamp(28px, 8vw, 38px); font-weight: 800; color: var(--primary-dark); margin: 6px 0; word-break: break-word; }
        .split { display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center; margin-top: 14px; }
        .split div { background: #fff; border-radius: 14px; padding: 14px 8px; border: 1px solid var(--line); }
        .split b { display: block; font-size: 26px; color: var(--ink); }
        .split small { color: var(--muted); font-size: 13px; }
        .breakdown { margin-top: 14px; text-align: left; font-size: 14px; }
        .breakdown div { display: flex; justify-content: space-between; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }

        .review { display: grid; gap: 0; }
        @media (min-width: 640px) { .review { grid-template-columns: 1fr 1fr; column-gap: 24px; } }
        .review div { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid #f3f4f6; font-size: 15px; }
        .review div span:first-child { color: var(--muted); flex: none; }
        .review div span:last-child { font-weight: 600; text-align: right; word-break: break-word; }
        .review .wide { grid-column: 1 / -1; }
        .review .edit { border: 0; background: none; color: var(--primary); font-weight: 700; cursor: pointer; font-size: 13px; }

        /* ---------- Flash ---------- */
        .flash { border-radius: var(--radius); padding: 16px; display: flex; gap: 12px; align-items: flex-start; }
        .flash i { font-size: 20px; margin-top: 1px; }
        .flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .flash.bad { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .flash ul { margin: 6px 0 0 18px; font-size: 14px; }
        .flash .ref { display: inline-block; margin-top: 6px; font-weight: 800; font-size: 18px; letter-spacing: .02em; }



        /* ---------- Status pills ---------- */
        .pill { font-size: 12px; font-weight: 700; border-radius: 999px; padding: 3px 9px; white-space: nowrap; }
        .st-draft { background: #f3f4f6; color: #4b5563; } .st-review { background: #fef3c7; color: #92400e; }
        .st-active { background: #dbeafe; color: #1e40af; } .st-done { background: #d1fae5; color: #065f46; } .st-rejected { background: #fee2e2; color: #991b1b; }

        .menu { position: absolute; right: 16px; top: calc(env(safe-area-inset-top) + 58px); background: #fff; color: var(--text); border-radius: 14px; box-shadow: 0 18px 40px -12px rgba(30,27,46,.35); min-width: 220px; padding: 8px; display: none; z-index: 70; }
        .menu.open { display: block; }
        .menu .who { padding: 8px 10px 10px; border-bottom: 1px solid var(--line); margin-bottom: 6px; }
        .menu .who b { display: block; }
        .menu .who small { color: var(--muted); }
        .menu a, .menu button { display: flex; align-items: center; gap: 10px; width: 100%; padding: 10px; border-radius: 10px; border: 0; background: none; text-decoration: none; color: inherit; cursor: pointer; font-size: 15px; }
        .menu a:hover, .menu button:hover { background: #f3f4f6; }

        .toast { position: fixed; left: 50%; bottom: calc(var(--bar-h) + 20px + env(safe-area-inset-bottom)); transform: translate(-50%, 20px); background: var(--ink); color: #fff; padding: 12px 18px; border-radius: 12px; font-size: 14px; opacity: 0; pointer-events: none; transition: all .25s; z-index: 80; max-width: calc(100% - 32px); }
        .toast.show { opacity: 1; transform: translate(-50%, 0); }
        .short { display: none; }
        @media (max-width: 420px) {
            .long { display: none; } .short { display: inline; }
            .actionbar .btn { padding: 0 14px; }
            .gps-lbl { display: none; }
        }
        .gps-row { display: flex; gap: 8px; }
        .gps-row .input { flex: 1; min-width: 0; }
</style>
