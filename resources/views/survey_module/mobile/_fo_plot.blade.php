{{-- Field Observations > Plot (user, 2026-11-15: "the mobile looks like a web too much"). A phone screen of its own,
     not the web Survey Plan Check card: map on top, then Bearing & distance (traverse) or Walk & mark (GPS).
     Logic in public/js/fo-field.js; same projections and maths as the web card, so the numbers agree.
     Saved through survey-module.mobile.observations.traverse.store. --}}
<style>
    .fo-plot { padding-bottom: 76px; --fo-ink: #1e1b2e; --fo-acc: #0ea5e9; --fo-ok: #059669; --fo-warn: #d97706; --fo-bad: #dc2626; }
    .fo-map-wrap { position: relative; margin: 0 -16px 14px; height: 46vh; min-height: 280px; background: #dfe7ee; overflow: hidden; }
    .fo-map-wrap.full { position: fixed; inset: 0; z-index: 80; margin: 0; height: 100dvh; }
    #foMap { position: absolute; inset: 0; }
    .fo-fab-col { position: absolute; right: 12px; top: 12px; display: flex; flex-direction: column; gap: 8px; z-index: 3; }
    .fo-fab { width: 44px; height: 44px; border-radius: 14px; border: 0; background: rgba(255,255,255,.95); color: var(--fo-ink);
              box-shadow: 0 4px 14px rgba(0,0,0,.18); display: grid; place-items: center; font-size: 17px; }
    .fo-fab.on { background: var(--fo-ink); color: #fff; }
    .fo-sys-chip { position: absolute; left: 12px; top: 12px; z-index: 3; border: 0; border-radius: 999px; padding: 8px 14px;
                   background: rgba(30,27,46,.88); color: #fff; font: 600 13px 'Outfit', sans-serif; box-shadow: 0 4px 14px rgba(0,0,0,.2); }
    .fo-sys-chip i { margin-right: 6px; opacity: .8; }
    .fo-map-stats { position: absolute; left: 12px; top: 60px; right: 70px; z-index: 3; display: flex; gap: 6px; flex-wrap: wrap; pointer-events: none; }
    .fo-map-stats span { background: rgba(255,255,255,.94); border-radius: 10px; padding: 5px 9px; font-size: 12.5px; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,.15); }

    .fo-seg { display: grid; grid-template-columns: 1fr 1fr; background: #ebe7f1; border-radius: 16px; padding: 4px; gap: 4px; margin-bottom: 14px; }
    .fo-seg button { border: 0; border-radius: 12px; padding: 11px 8px; background: transparent; font: 700 14px 'Outfit', sans-serif; color: #5b5470; }
    .fo-seg button.on { background: #fff; color: var(--fo-ink); box-shadow: 0 2px 8px rgba(30,27,46,.12); }
    .fo-seg i { margin-right: 6px; }

    .fo-tile { display: flex; align-items: center; gap: 12px; width: 100%; text-align: left; border: 0; background: #fff; border-radius: 18px;
               padding: 14px; box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 6px 18px -10px rgba(30,27,46,.25); margin-bottom: 12px; }
    .fo-tag { flex: none; min-width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; font: 800 13px 'Outfit', sans-serif; color: #fff; background: #c2410c; padding: 0 6px; }
    .fo-tag.to { background: #0f3b5f; }
    .fo-tile b { display: block; font-size: 16px; }
    .fo-tile small { display: block; color: var(--muted); font-size: 12.5px; font-family: ui-monospace, monospace; }
    .fo-tile .chev { margin-left: auto; color: var(--muted); }

    .fo-line { background: #fff; border-radius: 18px; padding: 14px; margin-bottom: 10px; box-shadow: 0 1px 2px rgba(0,0,0,.05), 0 6px 18px -12px rgba(30,27,46,.3); border-left: 5px solid #e5e7eb; }
    .fo-line.ok { border-left-color: var(--fo-ok); }
    .fo-line.bad { border-left-color: var(--fo-bad); }
    .fo-line-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-weight: 700; }
    .fo-line-head .arrow { color: var(--muted); }
    .fo-line-head .del { margin-left: auto; border: 0; background: #f3f4f6; width: 34px; height: 34px; border-radius: 10px; color: #6b7280; }
    .fo-brg { display: grid; grid-template-columns: 52px 1fr 1fr 1fr 52px; gap: 6px; align-items: stretch; }
    .fo-tog { border: 0; border-radius: 12px; background: var(--fo-ink); color: #fff; font: 800 18px 'Outfit', sans-serif; min-height: 52px; }
    .fo-tog:disabled { background: #e5e7eb; color: #9ca3af; }
    .fo-num { width: 100%; min-height: 52px; border: 1.5px solid #e5e7eb; border-radius: 12px; text-align: center; font: 700 19px ui-monospace, monospace; background: #fafafa; padding: 0 4px; }
    .fo-num:focus { outline: none; border-color: var(--fo-acc); background: #fff; }
    .fo-unit { font-size: 11px; color: var(--muted); text-align: center; margin-top: 2px; }
    .fo-qd { margin: 8px 0 10px; font-size: 13px; font-weight: 600; color: #075985; background: #e0f2fe; border-radius: 10px; padding: 6px 10px; }
    .fo-qd.bad { color: #991b1b; background: #fee2e2; }
    .fo-qd.empty { color: var(--muted); background: #f3f4f6; }
    .fo-row2 { display: grid; grid-template-columns: 1fr 1.2fr; gap: 8px; }
    .fo-txt { width: 100%; min-height: 52px; border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 0 12px; font: 700 16px 'Outfit', sans-serif; text-transform: uppercase; background: #fafafa; }
    .fo-txt:focus { outline: none; border-color: var(--fo-acc); background: #fff; }
    .fo-out { margin-top: 8px; font: 600 12.5px ui-monospace, monospace; color: #374151; }
    .fo-out.reg { color: #7c3aed; }
    .fo-add { width: 100%; border: 2px dashed #cbd5e1; background: transparent; border-radius: 16px; padding: 14px; font: 700 15px 'Outfit', sans-serif; color: #475569; margin-bottom: 12px; }
    .fo-switch { display: flex; align-items: center; justify-content: space-between; gap: 10px; background: #fff; border-radius: 14px; padding: 12px 14px; margin-bottom: 10px; font-weight: 600; font-size: 14.5px; }
    .fo-switch input { width: 46px; height: 26px; appearance: none; background: #d1d5db; border-radius: 999px; position: relative; transition: background .2s; flex: none; }
    .fo-switch input::after { content: ''; position: absolute; width: 20px; height: 20px; top: 3px; left: 3px; background: #fff; border-radius: 50%; transition: left .2s; }
    .fo-switch input:checked { background: var(--fo-ok); }
    .fo-switch input:checked::after { left: 23px; }
    .fo-switch small { display: block; font-weight: 400; color: var(--muted); font-size: 12px; }

    .fo-verdict { border-radius: 16px; padding: 12px 14px; margin-bottom: 12px; font-size: 13.5px; }
    .fo-verdict b { display: block; font-size: 15.5px; }
    .fo-verdict.ok { background: #ecfdf5; color: #065f46; }
    .fo-verdict.warn { background: #fffbeb; color: #92400e; }
    .fo-verdict.bad { background: #fef2f2; color: #991b1b; }
    .fo-verdict.idle { background: #f3f4f6; color: #4b5563; }

    /* Walk & mark */
    .fo-gps { display: flex; align-items: center; gap: 12px; background: var(--fo-ink); color: #fff; border-radius: 18px; padding: 14px; margin-bottom: 12px; }
    .fo-gps .dot { width: 14px; height: 14px; border-radius: 50%; background: #9ca3af; flex: none; box-shadow: 0 0 0 4px rgba(255,255,255,.12); }
    .fo-gps .dot.good { background: #22c55e; } .fo-gps .dot.fair { background: #f59e0b; } .fo-gps .dot.poor { background: #ef4444; }
    .fo-gps .dot.live { animation: foPulse 1.4s infinite; }
    @keyframes foPulse { 50% { box-shadow: 0 0 0 9px rgba(255,255,255,0); } }
    .fo-gps b { display: block; font-size: 15px; } .fo-gps small { opacity: .75; font-size: 12.5px; }
    .fo-gps button { margin-left: auto; border: 0; border-radius: 12px; padding: 10px 14px; font: 700 14px 'Outfit', sans-serif; background: #22c55e; color: #052e16; }
    .fo-gps button.stop { background: #f87171; color: #450a0a; }
    .fo-mark-wrap { display: flex; flex-direction: column; align-items: center; margin: 6px 0 16px; }
    .fo-mark { width: 132px; height: 132px; border-radius: 50%; border: 0; color: #fff; font: 800 18px 'Outfit', sans-serif;
               background: radial-gradient(circle at 30% 30%, #f472b6, var(--primary) 55%, var(--primary-dark)); box-shadow: 0 14px 30px -10px rgba(219,39,119,.7), inset 0 -4px 0 rgba(0,0,0,.15); }
    .fo-mark i { display: block; font-size: 30px; margin-bottom: 6px; }
    .fo-mark:disabled { background: #d1d5db; box-shadow: none; }
    .fo-mark.busy { animation: foPulse 1s infinite; }
    .fo-mark-wrap small { margin-top: 8px; color: var(--muted); font-size: 12.5px; text-align: center; }
    .fo-step { display: flex; align-items: center; gap: 8px; }
    .fo-step input { width: 70px; min-height: 40px; border: 1.5px solid #e5e7eb; border-radius: 10px; text-align: center; font: 700 16px ui-monospace, monospace; }
    .fo-pts { background: #fff; border-radius: 18px; padding: 4px 14px; margin-bottom: 12px; }
    .fo-pt { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f0f4; }
    .fo-pt:last-child { border-bottom: 0; }
    .fo-pt input { flex: 1; min-width: 0; border: 0; border-bottom: 1.5px dashed #d1d5db; font: 700 15px 'Outfit', sans-serif; text-transform: uppercase; padding: 4px 0; background: transparent; }
    .fo-pt small { display: block; color: var(--muted); font: 600 11.5px ui-monospace, monospace; }
    .fo-pt .acc { font-size: 11.5px; font-weight: 700; border-radius: 999px; padding: 2px 8px; flex: none; }
    .acc.good { background: #dcfce7; color: #166534; } .acc.fair { background: #fef3c7; color: #92400e; } .acc.poor { background: #fee2e2; color: #991b1b; }
    .fo-pt button { border: 0; background: transparent; color: #9ca3af; font-size: 16px; padding: 6px; }
    .fo-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
    .fo-btn { border: 0; border-radius: 14px; padding: 14px 10px; font: 700 14.5px 'Outfit', sans-serif; background: #fff; color: var(--fo-ink); box-shadow: 0 1px 2px rgba(0,0,0,.06), 0 6px 16px -10px rgba(30,27,46,.3); }
    .fo-btn.primary { background: var(--fo-ink); color: #fff; }
    .fo-btn:disabled { opacity: .45; }

    /* Save bar + sheets */
    .fo-savebar { position: fixed; left: 0; right: 0; bottom: calc(var(--nav-h, 68px) + env(safe-area-inset-bottom)); z-index: 45; padding: 10px 16px;
                  background: linear-gradient(180deg, rgba(246,244,249,0), var(--bg) 35%); }
    .fo-savebar button { width: 100%; max-width: 560px; margin: 0 auto; display: block; border: 0; border-radius: 16px; padding: 15px; font: 800 16px 'Outfit', sans-serif;
                         color: #fff; background: linear-gradient(135deg, #059669, #047857); box-shadow: 0 12px 24px -10px rgba(5,150,105,.7); }
    .fo-savebar button:disabled { background: #9ca3af; box-shadow: none; }
    .fo-sheet-bg { position: fixed; inset: 0; background: rgba(15,12,25,.5); z-index: 90; opacity: 0; pointer-events: none; transition: opacity .2s; }
    .fo-sheet-bg.open { opacity: 1; pointer-events: auto; }
    .fo-sheet { position: fixed; left: 0; right: 0; bottom: 0; z-index: 91; background: #fff; border-radius: 24px 24px 0 0; max-height: 86dvh; overflow: auto;
                padding: 8px 16px calc(18px + env(safe-area-inset-bottom)); transform: translateY(105%); transition: transform .25s ease; }
    .fo-sheet.open { transform: none; }
    .fo-x { position: absolute; right: 14px; top: 12px; width: 36px; height: 36px; border-radius: 12px; border: 0; background: #f3f4f6; color: #4b5563; font-size: 16px; }
    .fo-sheet .grab { width: 44px; height: 5px; border-radius: 3px; background: #d1d5db; margin: 4px auto 12px; }
    .fo-sheet h3 { font-size: 18px; margin-bottom: 4px; }
    .fo-sheet .sub { color: var(--muted); font-size: 13px; margin-bottom: 12px; }
    .fo-opt { display: flex; align-items: center; gap: 12px; width: 100%; border: 1.5px solid #ece9f2; background: #fff; border-radius: 14px; padding: 12px; margin-bottom: 8px; text-align: left; }
    .fo-opt.on { border-color: var(--fo-ink); background: #f5f3fa; }
    .fo-opt b { display: block; font-size: 15px; } .fo-opt small { color: var(--muted); font-size: 12.5px; }
    .fo-opt i { width: 22px; text-align: center; color: var(--primary-dark); }
    .fo-sheet-tabs { display: flex; gap: 6px; overflow-x: auto; margin-bottom: 12px; scrollbar-width: none; }
    .fo-sheet-tabs button { flex: none; border: 1.5px solid #ece9f2; background: #fff; border-radius: 999px; padding: 8px 13px; font: 600 13.5px 'Outfit', sans-serif; }
    .fo-sheet-tabs button.on { background: var(--fo-ink); color: #fff; border-color: var(--fo-ink); }
    .fo-sum { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
    .fo-sum div { background: #f6f4f9; border-radius: 14px; padding: 10px 12px; } .fo-sum b { display: block; font-size: 18px; } .fo-sum span { font-size: 12px; color: var(--muted); }
    .fo-note { font-size: 12.5px; color: var(--muted); margin: 6px 2px 12px; }
    .fo-plot [hidden] { display: none !important; }
</style>

<div class="fo-plot" id="foPlot">
    <div class="fo-map-wrap" id="foMapWrap">
        <div id="foMap"></div>
        <button type="button" class="fo-sys-chip" id="foSysChip"><i class="fas fa-globe"></i><span>Minna / UTM 32N</span></button>
        <div class="fo-fab-col">
            <button type="button" class="fo-fab" id="foFull" aria-label="Full screen map"><i class="fas fa-expand"></i></button>
            <button type="button" class="fo-fab on" id="foBase" aria-label="Satellite or street map"><i class="fas fa-satellite"></i></button>
            <button type="button" class="fo-fab" id="foFit" aria-label="Zoom to the plot"><i class="fas fa-vector-square"></i></button>
            <button type="button" class="fo-fab" id="foMe" aria-label="My position"><i class="fas fa-location-crosshairs"></i></button>
        </div>
        <div class="fo-map-stats" id="foMapStats"></div>
    </div>

    <div class="fo-seg" role="tablist">
        <button type="button" data-mode="trav" class="on"><i class="fas fa-compass"></i>Bearing &amp; distance</button>
        <button type="button" data-mode="walk"><i class="fas fa-person-walking"></i>Walk &amp; mark</button>
    </div>

    {{-- ---------------- Bearing & distance ---------------- --}}
    <section id="foTrav">
        <button type="button" class="fo-tile" id="foStart">
            <span class="fo-tag">X1</span>
            <span><b id="foStartName">Choose the start beacon</b><small id="foStartXY">From this job, the Beacon Register, GPS or typed</small></span>
            <i class="fas fa-chevron-right chev"></i>
        </button>
        <div id="foLines"></div>
        <button type="button" class="fo-add" id="foAddLine"><i class="fas fa-plus"></i> Add line</button>
        <label class="fo-switch"><span>Last line closes on X1<small>The traverse returns to the start beacon</small></span><input type="checkbox" id="foClose" checked></label>
        <div class="fo-verdict idle" id="foVerdict"><b>Enter the lines</b>Bearing and distance per line, as read on the ground.</div>
    </section>

    {{-- ---------------- Walk & mark (GPS) ---------------- --}}
    <section id="foWalk" hidden>
        <div class="fo-gps">
            <span class="dot" id="foGpsDot"></span>
            <span><b id="foGpsTitle">GPS is off</b><small id="foGpsSub">Start it, then walk to each corner and tap Mark.</small></span>
            <button type="button" id="foGpsBtn"><i class="fas fa-satellite-dish"></i> Start</button>
        </div>
        <div class="fo-mark-wrap">
            <button type="button" class="fo-mark" id="foMark" disabled><i class="fas fa-location-dot"></i><span id="foMarkLbl">Mark P1</span></button>
            <small id="foMarkHint">Stand on the beacon and hold still.</small>
        </div>
        <label class="fo-switch"><span>Average for 5 seconds<small>Steadier points: the fixes are averaged before marking</small></span><input type="checkbox" id="foAvg" checked></label>
        <label class="fo-switch"><span>Auto-mark as I walk<small class="fo-step">every <input type="number" id="foStepN" value="10" min="1" max="500" inputmode="numeric"> <select id="foStepU" style="border:0;font:600 13px Outfit"><option value="m">metres</option><option value="steps">steps</option></select></small></span><input type="checkbox" id="foAuto"></label>
        <div class="fo-sum">
            <div><b id="foWPts">0</b><span>points</span></div>
            <div><b id="foWPer">0 m</b><span>perimeter</span></div>
            <div><b id="foWArea">—</b><span>area (closed)</span></div>
            <div><b id="foWAcc">—</b><span>average accuracy</span></div>
        </div>
        <div class="fo-pts" id="foPts"><div class="fo-note" style="margin:12px 0;">No points yet.</div></div>
        <div class="fo-actions">
            <button type="button" class="fo-btn" id="foWalkUndo"><i class="fas fa-rotate-left"></i> Undo last</button>
            <button type="button" class="fo-btn" id="foWalkToTrav"><i class="fas fa-compass"></i> Use as traverse</button>
        </div>
        <div class="fo-note">GPS on a phone is good to about 3–10 m: use the points to find and sketch the plot. For the official X / Y, turn them into a traverse and enter the measured bearings and distances.</div>
    </section>
</div>

<div class="fo-savebar" id="foSaveBar"><button type="button" id="foSave" disabled><i class="fas fa-floppy-disk"></i> <span id="foSaveLbl">Save to this job</span></button></div>

<div class="fo-sheet-bg" id="foSheetBg"></div>
<div class="fo-sheet" id="foSheet" role="dialog" aria-modal="true"><div class="grab"></div><div id="foSheetBody"></div></div>
