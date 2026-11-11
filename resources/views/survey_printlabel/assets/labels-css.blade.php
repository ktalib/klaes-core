<style>
    :root {
        --sheet-margin-x: 0.85mm;
        --sheet-margin-y: 1.4mm;
        --label-qr-size: 40px;
    }
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .label-format-option.selected,
    .orientation-option.selected {
        border-color: #2563eb;
        background-color: rgba(37, 99, 235, 0.08);
    }

    .preview-shell {
        border: 1px dashed #cbd5f5;
        border-radius: 0.75rem;
        padding: 1.5rem;
        background: linear-gradient(135deg, rgba(191, 219, 254, 0.15), rgba(219, 234, 254, 0.25));
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .preview-heading {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e3a8a;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        text-align: center;
    }

    .preview-canvas {
        background-color: #ffffff;
        border-radius: 0.75rem;
        box-shadow: 0 20px 45px rgba(30, 58, 138, 0.12);
        padding: 1.5rem;
        min-height: 200px;
    }

    .label-preview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
    }

    .label-preview-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        padding: 1rem;
        border-radius: 0.75rem;
        border: 1px solid rgba(148, 163, 184, 0.6);
        background: linear-gradient(180deg, rgba(248, 250, 252, 0.9), #ffffff);
        transition: transform 0.2s ease;
    min-height: 100px;
    }

    .label-preview-card:hover {
        transform: translateY(-2px);
    }

    .label-preview-qr {
        width: 96px;
        height: 96px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border-radius: 0.6rem;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.12);
        padding: 0.45rem;
    }

    .label-preview-qr canvas {
        width: 100%;
        height: 100%;
        display: block;
    }

    .label-preview-meta {
        text-align: center;
        font-size: 0.85rem;
        color: #1f2937;
    }

    .label-preview-file {
        font-weight: 600;
        letter-spacing: 0.05em;
        color: #0f172a;
    }

    .label-preview-file--secondary {
        font-weight: 500;
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        color: #1f2937;
        margin-top: 0.1rem;
    }

    .label-preview-location {
        margin-top: 0.15rem;
        font-size: 0.8rem;
        color: #475569;
    }

    .preview-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem;
        color: #475569;
        gap: 0.75rem;
    }

    .preview-empty-icon {
        width: 48px;
        height: 48px;
        border-radius: 9999px;
        background: rgba(191, 219, 254, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #1d4ed8;
    }

    .print-summary-card {
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1.5rem;
    }

    .print-summary-title {
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #0f172a;
        margin-bottom: 0.75rem;
    }

    .print-summary-body {
        font-size: 0.9rem;
        color: #475569;
        line-height: 1.6;
    }

    .preview-note {
        font-style: italic;
    }

    #printSection {
        display: none;
    }

    #printSection.active {
        display: block;
    }

    #printSection .print-layout {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        background: #ffffff;
        padding: 1rem;
    }

    #printSection .print-page {
        page-break-after: always;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 0;
    }

    #printSection .print-page:last-child {
        page-break-after: auto;
    }

    #printSection .label-page {
        display: grid;
        grid-template-columns: repeat(3, calc((210mm - (var(--sheet-margin-x) * 2)) / 3));
        grid-template-rows: repeat(10, calc((297mm - (var(--sheet-margin-y) * 2)) / 10));
        row-gap: 0;
        column-gap: 0;
        width: calc(210mm - (var(--sheet-margin-x) * 2));
        height: calc(297mm - (var(--sheet-margin-y) * 2));
        margin: var(--sheet-margin-y) auto;
    }

    #printSection .label-item {
        border: none;
        border-radius: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 1.8mm 1.6mm 2mm;
        gap: 0.6mm;
        background: #ffffff;
        box-sizing: border-box;
    }

    #printSection .label-item canvas {
        width: var(--label-qr-size);
        height: var(--label-qr-size);
        margin-top: 0.4mm;
    }

    #printSection .file-number {
        font-weight: 600;
        font-size: 9pt;
        margin-top: 0;
        letter-spacing: 0.04em;
        line-height: 1.08;
    }

    #printSection .shelf-label {
        font-size: 8pt;
        font-weight: 600;
        color: #1f2933;
        margin-top: 0;
        line-height: 1.05;
        word-break: break-word;
    }

    @media print {
        body * {
            visibility: hidden;
        }

        #printSection,
        #printSection * {
            visibility: visible !important;
        }

        #printSection {
            position: absolute;
            inset: 0;
            display: block !important;
            padding: 0;
            margin: 0;
            background: #ffffff;
        }

        #printSection .print-layout {
            padding: 0;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }
    }
</style>
