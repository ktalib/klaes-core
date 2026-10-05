{{--
    Cadastral accents.

    The Survey prototype CSS is reused wholesale; this only re-tints it, so the
    module reads as Cadastral (rose, matching its sidebar entry) rather than as a
    second Survey module. Everything is scoped under .cadastral-proto, which sits
    beside .survey-proto on the layout wrapper — nothing here can reach the app
    chrome or the Survey module's own pages.
--}}
<style>
    .cadastral-proto {
        --primary: #be123c;          /* rose-700  */
        --primary-dark: #9f1239;     /* rose-800  */
        --primary-light: #fff1f2;    /* rose-50   */
        --accent: #e11d48;           /* rose-600  */
    }

    .cadastral-proto .kpi-card {
        border-left: 3px solid var(--accent);
    }

    .cadastral-proto .btn-primary {
        background: var(--primary);
        border-color: var(--primary);
    }

    .cadastral-proto .btn-primary:hover {
        background: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    /* The "4.1 · Cadastral Registry" unit strips were removed: the page header
       already names the page. Hidden here too, in case one comes back. */
    .cadastral-proto .unit-tag {
        display: none;
    }

    /* Honest-limit callout: used where a screen does less than the concept note
       promises (no parcel geometry, unconfirmed SURCON format, upload not
       generation). Deliberately visible rather than a footnote. */
    .cadastral-proto .caveat {
        display: flex;
        gap: 10px;
        padding: 10px 14px;
        margin-bottom: 16px;
        border-left: 4px solid #d97706;
        background: #fffbeb;
        border-radius: 4px;
        font-size: 13px;
        color: #78350f;
    }

    .cadastral-proto .caveat i { margin-top: 2px; }

    /* The beacon/pillar coordinate grids. */
    .cadastral-proto .coord-table { width: 100%; border-collapse: collapse; }
    .cadastral-proto .coord-table th,
    .cadastral-proto .coord-table td { padding: 6px 8px; border-bottom: 1px solid var(--gray-200); }
    .cadastral-proto .coord-table th { font-size: 11px; text-transform: uppercase; color: var(--gray-500); text-align: left; }
    .cadastral-proto .coord-table input,
    .cadastral-proto .coord-table select {
        width: 100%;
        padding: 6px 8px;
        border: 1px solid var(--gray-300);
        border-radius: 4px;
        font-family: var(--font);
        font-size: 13px;
    }

    /* The stage chain on a report. */
    .cadastral-proto .stage-list { list-style: none; margin: 0; padding: 0; }
    .cadastral-proto .stage-list li {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px dashed var(--gray-200);
    }
    .cadastral-proto .stage-dot {
        flex: 0 0 22px;
        height: 22px;
        width: 22px;
        border-radius: 50%;
        display: grid;
        place-items: center;
        font-size: 11px;
        font-weight: 700;
        background: var(--gray-200);
        color: var(--gray-600);
    }
    .cadastral-proto .stage-dot.done    { background: #d1fae5; color: #065f46; }
    .cadastral-proto .stage-dot.active  { background: var(--primary-light); color: var(--primary-dark); }
    .cadastral-proto .stage-dot.returned{ background: #fee2e2; color: #991b1b; }

    .cadastral-proto .money { font-variant-numeric: tabular-nums; text-align: right; }
</style>
