{{--
    Styles for the CofO Work Card screens, on top of instrument_workflow/partials/styles (iw-*).
    Tailwind 2.2 CDN: no slash opacity, no arbitrary values, no orange/amber/emerald,
    no per-side border colours — anything beyond that lives here, prefixed cw-.
--}}
<style>
    .cw-page { max-width: 1440px; }

    /* Table + side panel (CofO screen). The panel sits beside the table only when there is
       room for both; otherwise it drops below and its cards sit side by side, so the table
       always keeps the full width. 1440px is the viewport width, so zooming in crosses it. */
    .cw-layout { grid-template-columns: minmax(0, 1fr) 300px; }
    @media (max-width: 1440px) {
        .cw-layout { grid-template-columns: minmax(0, 1fr); }
        .cw-layout > aside { position: static; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .cw-layout > aside > * { margin-top: 0 !important; }
    }

    /* Summary tiles */
    .cw-tiles { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); }
    .cw-tile { display: flex; align-items: center; gap: 12px; padding: 16px 18px; }
    .cw-tile-value { font-size: 24px; font-weight: 800; color: #111827; line-height: 1; font-variant-numeric: tabular-nums; }
    .cw-tile-label { font-size: 13px; color: #6b7280; margin-top: 4px; }
    .cw-icon-tile { width: 40px; height: 40px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; flex: none; }
    .cw-icon-tile.blue { background: #eff6ff; color: #2563eb; }
    .cw-icon-tile.green { background: #f0fdf4; color: #16a34a; }
    .cw-icon-tile.purple { background: #f5f3ff; color: #7c3aed; }
    .cw-icon-tile.gray { background: #f3f4f6; color: #4b5563; }
    .cw-icon-tile.red { background: #fef2f2; color: #dc2626; }
    .cw-icon-tile.yellow { background: #fefce8; color: #a16207; }

    /* Workflow stepper (queue) — the pipeline itself, clickable per stage, not a tab bar */
    .cw-flow-wrap { padding: 16px 18px 6px; border-bottom: 1px solid #f3f4f6; }
    .cw-flow-bookends { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .cw-flow-bookend { display: inline-flex; align-items: center; gap: 7px; padding: 5px 10px; border-radius: 8px; font-size: 12px; font-weight: 700; color: #4b5563; background: #f9fafb; border: 1px solid #eef0f3; }
    .cw-flow-bookend:hover { background: #f3f4f6; color: #111827; }
    .cw-flow-bookend.active { background: #111827; border-color: #111827; color: #fff; }
    .cw-flow-bookend .cw-count { font-size: 10px; font-weight: 700; min-width: 20px; text-align: center; padding: 1px 5px; border-radius: 99px; background: #e5e7eb; color: #374151; }
    .cw-flow-bookend.active .cw-count { background: rgba(255,255,255,.2); color: #fff; }
    /* The column count comes from the pipeline itself, via --cw-steps set inline by
       cofo_workflow/partials/pipeline. It used to be hard-coded at 13 (the ALAES stage
       count); with KLAES's 6 stages that left the rail occupying 6/13 of the card and
       looking truncated. Deriving it means the rail always spans the full width, whatever
       the stage list becomes. The fallback matches today's 6. */
    .cw-flow { display: grid; grid-template-columns: repeat(var(--cw-steps, 6), minmax(0, 1fr)); gap: 0; padding: 4px 4px 14px; width: 100%; }
    @media (max-width: 1100px) { .cw-flow { grid-template-columns: repeat(3, minmax(0, 1fr)); row-gap: 18px; } }
    @media (max-width: 700px) { .cw-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .cw-flow-step { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 0 4px; }
    .cw-flow-step::before { content: ''; position: absolute; top: 17px; left: -50%; width: 100%; height: 3px; background: #e5e7eb; z-index: 0; }
    .cw-flow-step:first-child::before { display: none; }
    .cw-flow-dot { position: relative; z-index: 1; width: 36px; height: 36px; border-radius: 99px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; border: 2px solid #d1d5db; background: #fff; color: #6b7280; text-decoration: none; cursor: default; }
    .cw-flow-name { margin-top: 8px; font-size: 12px; font-weight: 600; color: #6b7280; line-height: 1.25; text-decoration: none; }
    .cw-flow-count { position: absolute; top: -6px; right: -6px; font-size: 10px; font-weight: 700; min-width: 17px; height: 17px; line-height: 17px; text-align: center; padding: 0 3px; border-radius: 99px; background: #e5e7eb; color: #374151; font-variant-numeric: tabular-nums; }
    .cw-flow-step.is-active .cw-flow-dot { background: #2563eb; border-color: #2563eb; color: #fff; box-shadow: 0 0 0 5px rgba(37, 99, 235, .18); }
    .cw-flow-step.is-active .cw-flow-name { color: #111827; }
    .cw-flow-step.is-active .cw-flow-count { background: #1d4ed8; color: #fff; }
    .cw-flow-step.has-cards .cw-flow-dot { border-color: #86efac; }
    .cw-flow-step.is-off .cw-flow-dot { border-style: dashed; background: #f9fafb; }
    .cw-flow-step.is-off .cw-flow-name { color: #d1d5db; }
    .cw-flow-step.is-off .cw-flow-count { display: none; }
    .cw-flow-step.is-zero .cw-flow-count { background: #f3f4f6; color: #9ca3af; }

    /* Filter bar */
    .cw-filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; padding: 14px 18px; border-bottom: 1px solid #f3f4f6; background: #fcfcfd; }
    .cw-filters .iw-input-group { flex: 1 1 260px; }
    .cw-filters .iw-input, .cw-filters .iw-select { padding-top: 8px; padding-bottom: 8px; font-size: 13px; }
    .cw-filters .iw-select { width: auto; min-width: 150px; }
    .cw-toggle { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #374151; padding: 7px 12px; border: 1px solid #d1d5db; border-radius: 10px; background: #fff; cursor: pointer; user-select: none; }
    .cw-toggle input { accent-color: #dc2626; }
    .cw-toggle.on { border-color: #fecaca; background: #fef2f2; color: #991b1b; }

    /* Queue table */
    .cw-table { width: 100%; font-size: 13px; border-collapse: collapse; }
    .cw-table thead th { background: #f9fafb; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; padding: 10px 12px; font-weight: 600; white-space: nowrap; }
    .cw-table tbody td { padding: 11px 12px; border-top: 1px solid #f3f4f6; vertical-align: middle; }
    .cw-table tbody tr.cw-row { cursor: pointer; transition: background .12s; }
    .cw-table tbody tr.cw-row:hover { background: #f8fbff; }
    .cw-file { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight: 700; color: #111827; }
    .cw-sub { font-size: 12px; color: #6b7280; }

    /* Pills */
    .cw-pill { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 99px; background: #f3f4f6; color: #4b5563; white-space: nowrap; line-height: 18px; }
    .cw-pill.blue { background: #dbeafe; color: #1e40af; }
    .cw-pill.green { background: #dcfce7; color: #166534; }
    .cw-pill.red { background: #fee2e2; color: #991b1b; }
    .cw-pill.yellow { background: #fef9c3; color: #854d0e; }
    .cw-pill.purple { background: #ede9fe; color: #5b21b6; }
    .cw-pill.teal { background: #ccfbf1; color: #115e59; }
    .cw-pill.ghost { background: #fff; border: 1px solid #e5e7eb; }
    .cw-legend { display: flex; gap: 10px; align-items: flex-start; }
    .cw-legend > .cw-pill { flex: none; align-self: flex-start; margin-top: 1px; }
    .cw-table td.cw-nowrap { white-space: nowrap; }
    .cw-dot { width: 7px; height: 7px; border-radius: 99px; background: currentColor; display: inline-block; flex: none; }

    /* Rail */
    .cw-rail-row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px dashed #eef0f3; font-size: 13px; }
    .cw-rail-row:last-child { border-bottom: 0; }
    .cw-rail-row dt { color: #6b7280; }
    .cw-rail-row dd { color: #111827; font-weight: 600; text-align: right; word-break: break-word; }
    .cw-kicker { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9ca3af; }

    /* Card hero */
    .cw-hero { display: flex; gap: 18px; align-items: center; padding: 20px 22px; flex-wrap: wrap; }
    .cw-hero-mark { width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #1d4ed8, #2563eb); color: #fff; display: flex; align-items: center; justify-content: center; flex: none; box-shadow: 0 6px 16px rgba(37, 99, 235, .25); }
    .cw-hero-file { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -.01em; }
    .cw-hero-holder { font-size: 15px; color: #374151; font-weight: 500; }

    /* Stage tracker */
    .cw-track-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 22px 0; flex-wrap: wrap; }
    .cw-progress { width: 280px; max-width: 100%; }
    .cw-progress-bar { height: 8px; background: #e5e7eb; border-radius: 99px; overflow: hidden; }
    .cw-progress-bar > div { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #16a34a, #2563eb); }
    .cw-track { display: grid; grid-template-columns: repeat(13, minmax(0, 1fr)); gap: 0; padding: 18px 14px 18px; }
    @media (max-width: 1280px) { .cw-track { grid-template-columns: repeat(7, minmax(0, 1fr)); row-gap: 18px; } }
    @media (max-width: 700px) { .cw-track { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .cw-step { position: relative; display: flex; flex-direction: column; align-items: center; text-align: center; padding: 0 4px; cursor: default; }
    .cw-step::before { content: ''; position: absolute; top: 17px; left: -50%; width: 100%; height: 3px; background: #e5e7eb; z-index: 0; }
    .cw-step:first-child::before { display: none; }
    .cw-step.s-completed::before, .cw-step.s-not_applicable::before, .cw-step.s-waived::before, .cw-step.s-skipped::before, .cw-step.s-current::before { background: #86efac; }
    .cw-step-dot { position: relative; z-index: 1; width: 36px; height: 36px; border-radius: 99px; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 700; border: 2px solid #d1d5db; background: #fff; color: #6b7280; }
    .cw-step-name { margin-top: 8px; font-size: 12px; font-weight: 600; color: #6b7280; line-height: 1.25; }
    .cw-step-state { margin-top: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; }
    .cw-step.s-completed .cw-step-dot { background: #16a34a; border-color: #16a34a; color: #fff; }
    .cw-step.s-completed .cw-step-name { color: #166534; }
    .cw-step.s-completed .cw-step-state { color: #16a34a; }
    .cw-step.s-current .cw-step-dot { background: #2563eb; border-color: #2563eb; color: #fff; box-shadow: 0 0 0 5px rgba(37, 99, 235, .18); }
    .cw-step.s-current .cw-step-name { color: #111827; }
    .cw-step.s-current .cw-step-state { color: #2563eb; }
    .cw-step.s-not_applicable .cw-step-dot { border-style: dashed; background: #f9fafb; color: #9ca3af; }
    .cw-step.s-skipped .cw-step-dot { background: #f3f4f6; color: #9ca3af; }
    .cw-step.s-skipped .cw-step-name { text-decoration: line-through; color: #9ca3af; }
    .cw-step.s-waived .cw-step-dot { background: #fef9c3; border-color: #facc15; color: #a16207; }
    .cw-step.s-waived .cw-step-state { color: #a16207; }
    .cw-step.is-optional .cw-step-name::after { content: ' ·opt'; font-weight: 500; color: #9ca3af; font-size: 10px; }
    .cw-tip { display: none; position: absolute; top: 44px; left: 50%; transform: translateX(-50%); z-index: 30; width: 240px; background: #111827; color: #f9fafb; font-size: 12px; text-align: left; line-height: 1.45; padding: 10px 12px; border-radius: 10px; box-shadow: 0 12px 28px rgba(17, 24, 39, .28); pointer-events: none; }
    .cw-tip::before { content: ''; position: absolute; top: -5px; left: 50%; margin-left: -5px; border: 5px solid transparent; border-top: 0; border-bottom-color: #111827; }
    .cw-tip b { color: #fff; }
    .cw-tip .cw-tip-ev { display: block; margin-top: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 11px; color: #93c5fd; word-break: break-all; }
    .cw-step:hover .cw-tip { display: block; }
    .cw-step:first-child .cw-tip { left: 0; transform: none; }
    .cw-step:last-child .cw-tip { left: auto; right: 0; transform: none; }

    /* Next action */
    .cw-next { display: flex; gap: 16px; align-items: flex-start; }
    .cw-next-mark { width: 48px; height: 48px; border-radius: 14px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex: none; }
    .cw-next-title { font-size: 18px; font-weight: 800; color: #111827; }
    .cw-action-box { margin-top: 14px; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; background: #fcfcfd; }

    /* Missing */
    .cw-missing-list { display: flex; flex-wrap: wrap; gap: 8px; }
    .cw-missing-link { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #991b1b; background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 6px 10px; }
    .cw-missing-link:hover { background: #fee2e2; }
    .cw-missing-link.later { color: #4b5563; background: #f9fafb; border-color: #e5e7eb; }
    .cw-missing-link.later:hover { background: #f3f4f6; }

    /* Fields */
    .cw-group-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 22px; border-bottom: 1px solid #f3f4f6; }
    .cw-field { display: grid; grid-template-columns: 220px minmax(0, 1fr) auto; gap: 14px; align-items: flex-start; padding: 13px 22px; border-top: 1px solid #f5f6f8; scroll-margin-top: 90px; }
    .cw-field:first-child { border-top: 0; }
    @media (max-width: 900px) { .cw-field { grid-template-columns: 1fr; } }
    .cw-field.is-missing { background: #fffafa; box-shadow: inset 3px 0 0 #ef4444; }
    .cw-field:target { background: #fefce8; box-shadow: inset 3px 0 0 #eab308; }
    .cw-field-label { font-size: 13px; font-weight: 600; color: #374151; }
    .cw-field-meta { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
    .cw-chip { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 6px; background: #f3f4f6; color: #6b7280; text-transform: uppercase; letter-spacing: .03em; }
    .cw-chip.req { background: #fee2e2; color: #b91c1c; }
    .cw-photo { width: 96px; height: 116px; object-fit: cover; border-radius: 10px; border: 1px solid #e5e7eb; margin-bottom: 6px; background: #f9fafb; }
    .cw-field-value { font-size: 14px; color: #111827; font-weight: 500; word-break: break-word; }
    .cw-field-empty { font-size: 13px; color: #9ca3af; font-style: italic; }
    .cw-field-empty.bad { color: #dc2626; font-style: normal; font-weight: 600; }
    .cw-badge { display: inline-flex; align-items: center; gap: 5px; margin-top: 6px; font-size: 11px; font-weight: 600; padding: 2px 8px 2px 6px; border-radius: 7px; border: 1px solid; max-width: 100%; }
    .cw-badge span.d { font-weight: 500; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cw-badge.blue { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
    .cw-badge.purple { background: #f5f3ff; color: #5b21b6; border-color: #ddd6fe; }
    .cw-badge.green { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
    .cw-badge.yellow { background: #fefce8; color: #854d0e; border-color: #fde68a; }
    .cw-badge.teal { background: #f0fdfa; color: #115e59; border-color: #99f6e4; }
    .cw-badge.gray { background: #f9fafb; color: #4b5563; border-color: #e5e7eb; }
    .cw-field-actions { display: flex; gap: 6px; align-items: center; justify-content: flex-end; }
    .cw-edit { display: grid; gap: 8px; }
    .cw-edit .iw-input, .cw-edit .iw-textarea { font-size: 13px; padding: 8px 10px; }

    /* History */
    .cw-timeline { position: relative; padding: 6px 22px 18px; }
    .cw-event { position: relative; display: flex; gap: 12px; padding: 10px 0; }
    .cw-event::before { content: ''; position: absolute; left: 15px; top: 40px; bottom: -10px; width: 2px; background: #f3f4f6; }
    .cw-event:last-child::before { display: none; }
    .cw-event-icon { width: 32px; height: 32px; border-radius: 99px; display: flex; align-items: center; justify-content: center; flex: none; background: #f3f4f6; color: #4b5563; }
    .cw-event-icon.green { background: #dcfce7; color: #15803d; }
    .cw-event-icon.blue { background: #dbeafe; color: #1d4ed8; }
    .cw-event-icon.purple { background: #ede9fe; color: #6d28d9; }
    .cw-event-icon.yellow { background: #fef9c3; color: #a16207; }
    .cw-event-icon.red { background: #fee2e2; color: #b91c1c; }
    .cw-diff { margin-top: 5px; font-size: 12px; color: #4b5563; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .cw-diff del { color: #9ca3af; }
    .cw-diff ins { text-decoration: none; color: #166534; font-weight: 600; }

    .cw-doc { display: flex; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px dashed #eef0f3; }
    .cw-doc:last-child { border-bottom: 0; }
    .cw-link { color: #2563eb; font-weight: 600; }
    .cw-link:hover { text-decoration: underline; }
</style>
