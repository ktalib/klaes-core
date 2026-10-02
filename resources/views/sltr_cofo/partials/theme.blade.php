{{--
    SLTR theme for the CofO Workflow screens.

    The cw- styles are SLTR's own copy (sltr_cofo/partials/styles) and already carry the
    lime accent. What remains here is the generic iw- kit (instrument_workflow/partials/
    styles), which belongs to neither ST nor SLTR, re-tinted under .sltr-theme only, plus
    the few SLTR-only classes. Include it AFTER both stylesheets.
--}}
<style>
    .sltr-theme {
        --sltr-accent: #65a30d;
        --sltr-accent-dark: #4d7c0f;
        --sltr-accent-deep: #3f6212;
        --sltr-tint: #f7fee7;
        --sltr-tint-strong: #ecfccb;
        --sltr-border: #d9f99d;
    }

    /* A thin lime rule across the top tells SLTR from ST at a glance. */
    .sltr-theme .sltr-band { height: 4px; border-radius: 99px; background: linear-gradient(90deg, var(--sltr-accent), #84cc16); }

    .sltr-theme .iw-crumbs a { color: var(--sltr-accent-dark); }
    .sltr-theme .iw-icon-tile.blue { background: var(--sltr-tint); color: var(--sltr-accent); }
    .sltr-theme .iw-btn-primary { background: var(--sltr-accent); color: #fff; }
    .sltr-theme .iw-btn-primary:hover:not(:disabled) { background: var(--sltr-accent-dark); }
    .sltr-theme .iw-input:focus,
    .sltr-theme .iw-select:focus,
    .sltr-theme .iw-textarea:focus { border-color: #84cc16; box-shadow: 0 0 0 3px rgba(101, 163, 13, .15); }
    .sltr-theme .iw-alert.info { background: var(--sltr-tint); color: var(--sltr-accent-deep); border-color: var(--sltr-border); }

    .sltr-theme .cw-table thead th { background: var(--sltr-tint); }

    /* Table + side panel. The panel sits beside the table only when there is room for both;
       otherwise it drops below and its cards sit side by side, so the table always keeps
       the full width. 1440px is the viewport width, so zooming in crosses it. */
    .sltr-theme .sltr-layout { grid-template-columns: minmax(0, 1fr) 300px; }
    @media (max-width: 1440px) {
        .sltr-theme .sltr-layout { grid-template-columns: minmax(0, 1fr); }
        .sltr-theme .sltr-layout > aside { position: static; display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
        .sltr-theme .sltr-layout > aside > * { margin-top: 0 !important; }
    }

    /* The two screen tabs (Front Page / CofO). */
    .sltr-theme .sltr-tabs { display: inline-flex; gap: 4px; padding: 4px; border-radius: 10px; background: #f3f4f6; }
    .sltr-theme .sltr-tab { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #4b5563; }
    .sltr-theme .sltr-tab:hover { color: #111827; }
    .sltr-theme .sltr-tab.active { background: #fff; color: var(--sltr-accent-deep); box-shadow: 0 1px 2px rgba(0, 0, 0, .06); }

    .sltr-theme .sltr-upload { display: inline-flex; gap: 4px; align-items: center; }
    .sltr-theme .sltr-upload input[type=file] { max-width: 150px; font-size: 11px; }
</style>
