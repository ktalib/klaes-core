{{--
    Shared styles for the Instrument Registration Workflow screens.

    The layout loads Tailwind 2.2 from a CDN, which has no slash opacities
    (bg-blue-50/40) or arbitrary values (text-[11px]); anything the screens need
    beyond Tailwind 2 lives here, prefixed iw-.
--}}
<style>
    .iw-page { padding: 24px; max-width: 1280px; }
    .iw-crumbs { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #6b7280; margin-bottom: 14px; }
    .iw-crumbs a { color: #2563eb; }
    .iw-crumbs a:hover { text-decoration: underline; }

    .iw-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .iw-card-head { padding: 18px 22px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
    .iw-card-title { font-size: 16px; font-weight: 700; color: #111827; display: flex; align-items: center; gap: 10px; }
    .iw-card-sub { font-size: 13px; color: #6b7280; margin-top: 2px; }
    .iw-card-body { padding: 20px 22px; }
    .iw-card-foot { padding: 14px 22px; border-top: 1px solid #f3f4f6; background: #f9fafb; border-radius: 0 0 16px 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    .iw-icon-tile { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; flex: none; }
    .iw-icon-tile.blue { background: #eff6ff; color: #2563eb; }
    .iw-icon-tile.green { background: #f0fdf4; color: #16a34a; }
    .iw-icon-tile.orange { background: #fff7ed; color: #ea580c; }
    .iw-icon-tile.gray { background: #f3f4f6; color: #4b5563; }

    .iw-grid { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .iw-grid .full { grid-column: 1 / -1; }
    @media (max-width: 768px) { .iw-grid { grid-template-columns: 1fr; } }

    .iw-field { display: flex; flex-direction: column; gap: 6px; }
    .iw-label { font-size: 13px; font-weight: 600; color: #374151; }
    .iw-label .req { color: #dc2626; margin-left: 2px; }
    .iw-help { font-size: 12px; color: #6b7280; }
    .iw-error { font-size: 12px; color: #dc2626; }
    .iw-input, .iw-select, .iw-textarea {
        width: 100%; font-size: 14px; color: #111827; background: #fff;
        border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px;
        transition: border-color .15s, box-shadow .15s;
    }
    .iw-input:focus, .iw-select:focus, .iw-textarea:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, .15); }
    .iw-input[readonly] { background: #f9fafb; color: #4b5563; }
    .iw-input.has-error, .iw-select.has-error { border-color: #f87171; box-shadow: 0 0 0 3px rgba(248, 113, 113, .12); }
    .iw-input-group { position: relative; }
    .iw-input-group .iw-input { padding-left: 38px; }
    .iw-input-group > i, .iw-input-group > svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: #9ca3af; pointer-events: none; }
    .iw-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

    .iw-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; font-weight: 600; padding: 10px 16px; border-radius: 10px; border: 1px solid transparent; cursor: pointer; transition: background .15s, box-shadow .15s, color .15s; white-space: nowrap; }
    .iw-btn:disabled { opacity: .55; cursor: not-allowed; }
    .iw-btn-primary { background: #2563eb; color: #fff; }
    .iw-btn-primary:hover:not(:disabled) { background: #1d4ed8; }
    .iw-btn-success { background: #16a34a; color: #fff; }
    .iw-btn-success:hover:not(:disabled) { background: #15803d; }
    .iw-btn-light { background: #fff; color: #374151; border-color: #d1d5db; }
    .iw-btn-light:hover:not(:disabled) { background: #f9fafb; }
    .iw-btn-sm { font-size: 13px; padding: 7px 12px; }

    .iw-divider-or { display: flex; align-items: center; gap: 10px; color: #9ca3af; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; }
    .iw-divider-or::before, .iw-divider-or::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }

    .iw-segment { display: inline-flex; background: #f3f4f6; padding: 4px; border-radius: 12px; gap: 4px; }
    .iw-segment button { font-size: 13px; font-weight: 600; padding: 7px 14px; border-radius: 9px; color: #4b5563; border: 0; background: transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
    .iw-segment button.active { background: #fff; color: #111827; box-shadow: 0 1px 3px rgba(0,0,0,.08); }

    .iw-option { display: flex; gap: 12px; align-items: flex-start; border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 12px 14px; cursor: pointer; background: #fff; transition: border-color .15s, background .15s, box-shadow .15s; text-align: left; width: 100%; }
    .iw-option:hover { border-color: #93c5fd; background: #f8fbff; }
    .iw-option.selected { border-color: #2563eb; background: #eff6ff; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); }
    .iw-avatar { width: 40px; height: 40px; border-radius: 12px; background: #dbeafe; color: #1d4ed8; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; flex: none; }
    .iw-avatar.corporate { background: #ede9fe; color: #6d28d9; }
    .iw-radio { width: 18px; height: 18px; border-radius: 99px; border: 2px solid #d1d5db; flex: none; margin-top: 2px; display: flex; align-items: center; justify-content: center; }
    .iw-option.selected .iw-radio { border-color: #2563eb; }
    .iw-option.selected .iw-radio::after { content: ''; width: 8px; height: 8px; border-radius: 99px; background: #2563eb; }

    .iw-tag { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #f3f4f6; color: #4b5563; }
    .iw-tag.blue { background: #eff6ff; color: #1d4ed8; }
    .iw-tag.green { background: #f0fdf4; color: #15803d; }
    .iw-tag.orange { background: #fff7ed; color: #c2410c; }
    .iw-tag.purple { background: #f5f3ff; color: #6d28d9; }

    .iw-empty { border: 1.5px dashed #e5e7eb; border-radius: 12px; padding: 26px; text-align: center; color: #6b7280; font-size: 13px; }
    .iw-alert { border-radius: 12px; padding: 12px 14px; font-size: 13px; display: flex; gap: 10px; align-items: flex-start; }
    .iw-alert.info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
    .iw-alert.warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .iw-alert.danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .iw-soft-blue { background: #f5f9ff; }
    .iw-soft-green { background: #f6fdf8; }
    .iw-soft-orange { background: #fffaf5; }

    /* Stepper */
    .iw-stepper { display: flex; align-items: center; gap: 0; padding: 18px 22px; overflow-x: auto; }
    .iw-step { display: flex; align-items: center; gap: 10px; flex: none; background: none; border: 0; padding: 0; cursor: default; text-align: left; }
    .iw-step.clickable { cursor: pointer; }
    .iw-step-dot { width: 34px; height: 34px; border-radius: 99px; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; border: 2px solid #d1d5db; color: #6b7280; background: #fff; transition: all .2s; }
    .iw-step-name { font-size: 13px; font-weight: 600; color: #6b7280; line-height: 1.2; }
    .iw-step-desc { font-size: 11px; color: #9ca3af; }
    .iw-step.current .iw-step-dot { border-color: #2563eb; background: #2563eb; color: #fff; box-shadow: 0 0 0 4px rgba(37, 99, 235, .15); }
    .iw-step.current .iw-step-name { color: #111827; }
    .iw-step.done .iw-step-dot { border-color: #16a34a; background: #16a34a; color: #fff; }
    .iw-step.done .iw-step-name { color: #15803d; }
    .iw-step-line { flex: 1; min-width: 28px; height: 2px; background: #e5e7eb; margin: 0 14px; border-radius: 2px; }
    .iw-step-line.done { background: #16a34a; }

    /* Summary rail */
    .iw-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    @media (max-width: 1100px) { .iw-layout { grid-template-columns: 1fr; } }
    .iw-sticky { position: sticky; top: 16px; }
    .iw-summary-row { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px dashed #eef0f3; }
    .iw-summary-row:last-child { border-bottom: 0; }
    .iw-summary-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; }
    .iw-summary-value { font-size: 14px; color: #111827; font-weight: 500; word-break: break-word; }
    .iw-summary-muted { font-size: 13px; color: #9ca3af; font-style: italic; }

    .iw-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .iw-table td { padding: 10px 0; border-bottom: 1px solid #f3f4f6; }
    .iw-table td.amount { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .iw-table tr.total td { border-bottom: 0; font-weight: 700; font-size: 15px; padding-top: 14px; }

    /* Tailwind 3-style utilities the workflow views use that the Tailwind 2.2
       CDN build does not ship (orange / amber / emerald palettes, per-side border
       colours, slash opacities, arbitrary font size). */
    .bg-orange-50 { background-color: #fff7ed; }
    .bg-orange-100 { background-color: #ffedd5; }
    .bg-orange-500 { background-color: #f97316; }
    .hover\:bg-orange-600:hover { background-color: #ea580c; }
    .text-orange-600 { color: #ea580c; }
    .text-orange-700 { color: #c2410c; }
    .text-orange-800 { color: #9a3412; }
    .border-orange-200 { border-color: #fed7aa; }
    .border-orange-300 { border-color: #fdba74; }
    .border-orange-500 { border-color: #f97316; }
    .bg-amber-100 { background-color: #fef3c7; }
    .border-amber-300 { border-color: #fcd34d; }
    .text-amber-700 { color: #b45309; }
    .text-amber-800 { color: #92400e; }
    .bg-emerald-100 { background-color: #d1fae5; }
    .border-emerald-300 { border-color: #6ee7b7; }
    .text-emerald-700 { color: #047857; }
    .text-emerald-800 { color: #065f46; }
    .border-l-blue-600 { border-left-color: #2563eb !important; }
    .border-l-green-600 { border-left-color: #16a34a !important; }
    .border-l-orange-500 { border-left-color: #f97316 !important; }
    .bg-blue-50\/40 { background-color: rgba(239, 246, 255, .6); }
    .bg-green-50\/40 { background-color: rgba(240, 253, 244, .6); }
    .text-\[11px\] { font-size: 11px; }
    .min-w-max { min-width: max-content; }

    .iw-spin { animation: iw-spin 1s linear infinite; }
    @keyframes iw-spin { to { transform: rotate(360deg); } }
    [x-cloak] { display: none !important; }
</style>

