{{-- Shared look for the recertification acknowledgement (letter + collection slip), after the Ministry paper template. --}}
<style>
    @page { size: A4; margin: 14mm 16mm 14mm 16mm; }
    body { font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; color: #111; font-size: 14px; line-height: 1.35; max-width: 190mm; margin: 0 auto; padding: 10px 6px; background: #fff; }
    .ack-page { position: relative; }
    .ack-break { page-break-after: always; break-after: page; }

    /* Letter head: three centred lines with the KANGIS seal on the right */
    .ack-head { display: flex; align-items: center; justify-content: center; gap: 12px; }
    .ack-head-text { flex: 1; text-align: center; color: #a5281b; font-weight: bold; line-height: 1.2; }
    .ack-head-text div { font-size: 16px; }
    .ack-seal { height: 72px; width: 72px; object-fit: contain; }
    .ack-seal-only { display: flex; justify-content: flex-end; }

    .ack-meta { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 8px; }
    .ack-qr img { width: 92px; height: 92px; display: block; }
    .ack-ref { font-weight: bold; font-style: italic; font-size: 14px; text-align: right; }
    .ack-ref small { display: block; font-style: normal; font-weight: normal; font-size: 11px; color: #444; }
    .ack-date { font-style: italic; margin-top: 4px; }

    .ack-salutation { margin: 18px 0 8px; font-style: italic; }
    .ack-title { color: #1f3864; font-weight: bold; text-decoration: underline; text-align: center; font-size: 15px; margin: 6px 40px 8px; line-height: 1.25; }
    .ack-body p { margin: 0 0 8px; text-align: justify; }

    /* Title documents: label / status columns, ruled header, plain boxes */
    .ack-docs { margin: 4px 0 8px 40px; width: 82%; border-collapse: collapse; }
    .ack-docs th { font-weight: bold; text-decoration: underline; text-align: left; border-bottom: 1px solid #111; padding: 0 0 2px; }
    .ack-docs th.ack-status, .ack-docs td.ack-status { text-align: center; width: 150px; }
    .ack-docs td { font-weight: bold; padding: 1px 0; }
    .ack-docs td.ack-other { font-weight: normal; }
    .ack-box { display: inline-block; width: 11px; height: 11px; border: 1px solid #111; line-height: 10px; font-size: 11px; text-align: center; vertical-align: middle; }

    .ack-sign { margin-top: 12px; }
    .ack-sign .ack-sign-space { height: 70px; }
    .ack-sign p { margin: 0; }

    /* Collection slip */
    .ack-slip-title { text-align: center; font-weight: bold; font-size: 15px; margin-top: 4px; }
    .ack-slip-title .u { text-decoration: underline; display: block; margin-bottom: 6px; }
    .ack-contact { margin-top: 22px; }
    .ack-contact p { margin: 0; }
    .ack-collect { margin-top: 26px; }
    .ack-line { display: flex; align-items: flex-end; margin-top: 26px; width: 62%; }
    .ack-line span.fill { flex: 1; border-bottom: 1px dotted #111; margin-left: 4px; height: 1em; }
    a { color: #1f4e9c; }

    /* KLAES mark: bottom-left corner. On paper it is fixed, so it repeats on every printed page. */
    .klaes-footer { display: flex; justify-content: flex-start; margin-top: 24px; }
    .klaes-footer img, .klaes-corner img { height: 30px; width: auto; }
    .klaes-corner { display: none; }
    .print-bar { text-align: center; margin: 12px 0; }

    @media print {
        body { padding: 0; }
        .no-print, .klaes-footer { display: none !important; }
        .klaes-corner { display: block; position: fixed; left: 0; bottom: 0; }
        a { color: #1f4e9c !important; }
    }
</style>
