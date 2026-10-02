{{--
    Chart tokens and chrome for the Cadastral dashboards.

    Colour is assigned by the JOB it does, not by taste:

      categorical  identity  — only where the series ARE the subject (the three
                               report streams, the four fee lines). Fixed slot
                               order, so a filter that changes the counts never
                               repaints the survivors.
      sequential   magnitude — one hue, light to dark.
      emphasis     one point — the bar that matters in the accent, the rest gray.
      status       state     — reserved; never reused as "series 4", and always
                               shipped with a label, never colour alone.

    The three categorical slots were validated against this surface (#ffffff),
    all pairs: worst CVD ΔE 9.2, worst normal-vision ΔE 24.0. Aqua sits at
    2.82:1 contrast, below 3:1, so every chart that uses it carries direct
    labels AND a table view — that is the relief rule, not an optional extra.

    Light only, deliberately: the KLAES shell has no dark theme, and inventing
    one here would be a theme no other page honours. The roles are custom
    properties, so adding it later is a one-block change.
--}}
<style>
    .cad-viz {
        --viz-surface:   #ffffff;
        --viz-plane:     #f8f9fa;
        --viz-ink:       #212529;
        --viz-ink-2:     #495057;
        --viz-muted:     #6c757d;
        --viz-grid:      #e9ecef;
        --viz-axis:      #dee2e6;
        --viz-dim:       #ced4da;   /* the de-emphasis gray */

        /* Categorical — fixed order, never cycled. */
        --viz-1: #2a78d6;   /* blue    */
        --viz-2: #eb6834;   /* orange  */
        --viz-3: #1baf7a;   /* aqua    */
        --viz-4: #eda100;   /* yellow  */

        /* Sequential (single hue) and the accent for emphasis. */
        --viz-seq: #2a78d6;

        /* Status — reserved. */
        --viz-good:     #0ca30c;
        --viz-warning:  #fab219;
        --viz-serious:  #ec835a;
        --viz-critical: #d03b3b;
    }

    /* ---------- card ---------- */
    .cad-card {
        background: var(--viz-surface);
        border: 1px solid var(--viz-grid);
        border-radius: 10px;
        padding: 18px 20px 16px;
    }
    .cad-card + .cad-card { margin-top: 0; }

    .cad-card-head {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 4px;
    }
    .cad-card-head h3 {
        margin: 0;
        font-size: 13px;
        font-weight: 700;
        letter-spacing: .01em;
        color: var(--viz-ink);
    }
    .cad-card-sub {
        margin: 0 0 14px;
        font-size: 11.5px;
        line-height: 1.45;
        color: var(--viz-muted);
    }

    /* ---------- hero ---------- */
    .cad-hero {
        display: grid;
        grid-template-columns: minmax(230px, 300px) 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    @media (max-width: 1100px) { .cad-hero { grid-template-columns: 1fr; } }

    .cad-hero-figure {
        background: var(--viz-surface);
        border: 1px solid var(--viz-grid);
        border-left: 3px solid var(--viz-seq);
        border-radius: 10px;
        padding: 18px 20px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .cad-hero-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--viz-muted);
    }
    .cad-hero-value {
        font-size: 52px;
        line-height: 1.05;
        font-weight: 700;
        color: var(--viz-ink);
        margin: 6px 0 2px;
    }
    .cad-hero-value.is-critical { color: var(--viz-critical); }
    .cad-hero-hint { font-size: 12px; color: var(--viz-muted); }

    .cad-delta {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 600;
        margin-top: 8px;
        color: var(--viz-ink-2);
    }
    .cad-delta i { font-size: 10px; }

    /* ---------- stat tiles ---------- */
    .cad-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
    }
    .cad-tile {
        background: var(--viz-surface);
        border: 1px solid var(--viz-grid);
        border-radius: 10px;
        padding: 14px 16px 12px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 0;
    }
    .cad-tile-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--viz-muted);
    }
    .cad-tile-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--viz-ink);
        line-height: 1.15;
        overflow-wrap: anywhere;
    }
    .cad-tile-value.is-money { font-size: 21px; }
    .cad-tile-value.is-good     { color: var(--viz-good); }
    .cad-tile-value.is-critical { color: var(--viz-critical); }
    .cad-tile-value.is-warning  { color: #a8730a; }   /* warning at text contrast */
    .cad-tile-spark { margin-top: 6px; height: 26px; }

    /* ---------- grids ---------- */
    .cad-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 14px; }
    .cad-grid-3 { display: grid; grid-template-columns: 2fr 1fr; gap: 14px; margin-top: 14px; }
    @media (max-width: 1100px) {
        .cad-grid-2, .cad-grid-3 { grid-template-columns: 1fr; }
    }

    /* ---------- chart chrome ---------- */
    .cad-plot { width: 100%; display: block; overflow: visible; }
    .cad-plot .grid  { stroke: var(--viz-grid); stroke-width: 1; }
    .cad-plot .axis  { stroke: var(--viz-axis); stroke-width: 1; }
    .cad-plot .tick  { fill: var(--viz-muted); font-size: 10px; }
    .cad-plot .vlabel { fill: var(--viz-ink-2); font-size: 11px; font-weight: 600; font-variant-numeric: tabular-nums; }
    .cad-plot .clabel { fill: var(--viz-ink-2); font-size: 11.5px; }

    /* Hit targets are bigger than the marks they serve. */
    .cad-plot .hit { fill: transparent; cursor: default; }
    .cad-plot .hit:hover + .mark,
    .cad-plot .mark:hover { filter: brightness(.92); }

    .cad-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 10px;
        font-size: 11.5px;
        color: var(--viz-ink-2);
    }
    .cad-legend span { display: inline-flex; align-items: center; gap: 6px; }
    .cad-legend i {
        width: 9px; height: 9px; border-radius: 2px; display: inline-block;
    }

    /* ---------- empty state ---------- */
    .cad-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 130px;
        color: var(--viz-muted);
        font-size: 12.5px;
        text-align: center;
        border: 1px dashed var(--viz-axis);
        border-radius: 8px;
        padding: 16px;
    }
    .cad-empty i { font-size: 20px; opacity: .45; }

    /* ---------- table view (the accessibility relief) ---------- */
    .cad-tableview { margin-top: 10px; }
    .cad-tableview summary {
        cursor: pointer;
        font-size: 11.5px;
        color: var(--viz-muted);
        list-style: none;
    }
    .cad-tableview summary::-webkit-details-marker { display: none; }
    .cad-tableview summary::before { content: '▸ '; }
    .cad-tableview[open] summary::before { content: '▾ '; }
    .cad-tableview table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .cad-tableview th, .cad-tableview td {
        padding: 4px 6px;
        font-size: 11.5px;
        border-bottom: 1px solid var(--viz-grid);
        text-align: left;
    }
    .cad-tableview th { color: var(--viz-muted); font-weight: 600; }
    .cad-tableview td.num { text-align: right; font-variant-numeric: tabular-nums; }

    /* ---------- queue lists ---------- */
    .cad-queue { display: flex; flex-direction: column; }
    .cad-queue-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid var(--viz-grid);
        font-size: 12.5px;
    }
    .cad-queue-row:last-child { border-bottom: 0; }
    .cad-queue-row .who { flex: 1; min-width: 0; }
    .cad-queue-row .who strong { display: block; color: var(--viz-ink); }
    .cad-queue-row .who span { color: var(--viz-muted); font-size: 11.5px; }
    .cad-queue-row .when { color: var(--viz-muted); font-size: 11.5px; white-space: nowrap; }

    /* ---------- unit nav ---------- */
    .cad-units {
        display: flex;
        gap: 4px;
        flex-wrap: wrap;
        border-bottom: 1px solid var(--viz-grid);
        margin-bottom: 18px;
    }
    .cad-unit {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 600;
        color: var(--viz-muted);
        text-decoration: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        white-space: nowrap;
    }
    .cad-unit:hover { color: var(--viz-ink); }
    .cad-unit.is-active { color: #be123c; border-bottom-color: #be123c; }
    .cad-unit .n {
        font-size: 11px;
        font-weight: 700;
        background: var(--viz-plane);
        border-radius: 999px;
        padding: 1px 7px;
        color: var(--viz-ink-2);
        font-variant-numeric: tabular-nums;
    }
    .cad-unit.is-active .n { background: #ffe4e6; color: #be123c; }

    .cad-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
</style>
