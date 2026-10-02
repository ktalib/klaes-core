<!DOCTYPE html>
<html lang="en">
  {{-- ikkdt --}}
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Duplicate File Call-up Sheet{{ $fileNumber ? ' — ' . $fileNumber : '' }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #1f2937;
            background: #e2e8f0;
        }
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.15);
            padding: 18mm 8mm;
            display: flex;
            flex-direction: column;
        }
        /* ---- Header ---- */
        .doc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 1px solid #1f2937;
            padding-bottom: 12px;
        }
        .doc-header .logo { height: 80px; width: auto; object-fit: contain; flex-shrink: 0; }
        .doc-header .titles { text-align: center; flex: 1; }
        .doc-header .titles h1 {
            font-size: 15px; margin: 0 0 4px; text-transform: uppercase;
            letter-spacing: .5px; line-height: 1.25; white-space: nowrap;
        }
        .doc-header .titles h2 {
            font-size: 13px; margin: 0; font-weight: 600; color: #374151;
            text-transform: uppercase; letter-spacing: .5px;
        }
        /* ---- Document title row ---- */
        .doc-title { text-align: center; margin: 22px 0 18px; }
        .doc-title h3 {
            display: inline-block; font-size: 15px; letter-spacing: 1px;
            text-transform: uppercase; margin: 0; padding: 6px 0;
            border-bottom: 1px solid #1f2937;
        }
        .doc-title .meta { font-size: 12px; color: #64748b; margin-top: 10px; }
        .doc-title .meta strong { color: #1f2937; }
        /* ---- Body ---- */
        .content { flex: 1 1 auto; }
        /* wraps so a file number carrying many duplicates still lists every one */
        .columns { display: flex; flex-wrap: wrap; gap: 12px; align-items: stretch; }
        .col { flex: 1 1 0; min-width: 0; border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; }
        .col h4 {
            margin: 0; padding: 10px 14px; font-size: 13px; letter-spacing: .5px;
            text-transform: uppercase; background: #1f2937; color: #fff;
        }
        .field { padding: 10px 14px; border-top: 1px solid #e2e8f0; }
        .field:first-of-type { border-top: none; }
        /* fixed row heights so the same field lines up across both columns */
        .field-title    { min-height: 40px; }
        .field-location { min-height: 40px; }
        .field .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 2px; }
        .field .value { font-size: 11px; font-weight: 600; word-break: break-word; }
        .missing h4 { background: #94a3b8; }
        .missing .value { color: #94a3b8; font-weight: 500; }
        /* ---- 3+ files: half-width cards, two per row ---- */
        body.wrap2 .col { flex: 0 1 calc(50% - 6px); min-width: calc(50% - 6px); }
        /* 5+ files means 3 rows or more — drop the decorative spacing to make room */
        body.many .vgap { display: none; }
        /* ---- Signature ---- */
        .signature {
            margin-top: 40px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 80px;
        }
        .signature .sig-block { flex: 0 0 220px; }   /* shorter signature line */
        .signature .sig-block-date { flex: 0 0 220px; }   /* shorter line for the date */
        .signature .sig-line {
            border-bottom: 1px solid #1f2937;
            height: 24px;
            text-align: center;        /* centre the date on the line */
            font-size: 13px; color: #1f2937;
            line-height: 22px;
        }
        .signature .sig-caption {
            text-align: center;        /* label centred below the line */
            font-size: 12px; font-weight: 600; color: #1f2937; margin-top: 6px;
        }
        /* ---- Footer ---- */
        .doc-footer {
            margin-top: 24px;
            border-top: 3px double #1f2937;
            padding-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .doc-footer .logo { height: 26px; width: auto; object-fit: contain; flex-shrink: 0; }
        .doc-footer .logo-main { height: 40px; }
        .doc-footer .footer-note { text-align: center; flex: 1; font-size: 10px; color: #64748b; }
        /* ---- Actions ---- */
        .actions { text-align: center; margin: 18px auto 28px; }
        .btn {
            display: inline-block; cursor: pointer; border: none; border-radius: 6px;
            background: #be123c; color: #fff; font-size: 14px; font-weight: 600;
            padding: 10px 22px;
        }
        .btn:hover { background: #9f1239; }
        @media print {
            @page { size: A4; margin: 0; }
            html, body { width: 210mm; background: #fff; }
            body:not(.many) { height: 297mm; }
            .sheet {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 12mm 6mm;   /* keep narrow side padding so cards stay wide when printing */
                box-shadow: none;
            }
            /* up to 4 files still fill exactly one page, never spilling onto a 2nd */
            body:not(.many) .sheet {
                height: 297mm;
                min-height: 0;
                max-height: 297mm;
                overflow: hidden;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            /* beyond 4, run onto a further page rather than clip a file off the
               list — but never split a single card across the break */
            body.many .col { page-break-inside: avoid; }
            .content { flex: 1 1 auto; min-height: 0; }
            .signature { margin-top: 24px; }
            .doc-footer { margin-top: 16px; page-break-inside: avoid; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="{{ count($files) > 2 ? 'wrap2' : '' }}{{ count($files) > 4 ? ' many' : '' }}">
    <div class="sheet">
        <div class="doc-header">
            <img class="logo" src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Ministry logo">
            <div class="titles">
                <h1>Kano State Ministry of Land and Physical Planning</h1>
                <h3>DEPARTMENT OF LAND</h3>

            </div>
            <img class="logo" src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Ministry logo">
        </div>

        <div class="doc-title">
            <h3>Duplicate File Call-up Sheet</h3>
            <div class="meta">
                File No: <strong>{{ $fileNumber }}</strong>
                &nbsp;&middot;&nbsp;
                <strong>{{ $recordCount }}</strong> file{{ $recordCount === 1 ? '' : 's' }} recorded under this number
            </div>
        </div>
        <div class="vgap"><br><br><br><br><br><br></div>
        <div class="content">
            <div class="columns">
                @foreach ($files as $index => $f)
                    <div class="col {{ $f['found'] ? '' : 'missing' }}">
                        <h4>File {{ $index + 1 }}</h4>
                        <div class="field">

                            <div class="value">File No: {{ $f['file_no'] }}</div>
                        </div>
                        <div class="field field-title">

                            <div class="value">File Title: {{ $f['file_title'] }}</div>
                        </div>
                        <div class="field">

                            <div class="value">Plot No: {{ $f['plot_number'] }}</div>
                        </div>
                        <div class="field field-location">

                            <div class="value">Location: {{ $f['location'] }}</div>
                        </div>
                        <div class="field">

                            <div class="value">{{ $f['date_label'] }}: {{ $f['date_indexed'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="vgap"><br><br><br><br><br><br><br></div>
            <div class="signature">
                <div class="sig-block">
                    <div class="sig-line"></div>
                    <div class="sig-caption">For KLAES:</div>
                </div>
                <div class="sig-block sig-block-date">
                    <div class="sig-line"></div>
                    <div class="sig-caption">Date:</div>
                </div>
            </div>
        </div>

        <div class="doc-footer">
            <img class="logo logo-main" src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Logo">
            <div class="footer-note">
                  &nbsp;&middot;&nbsp; Generated At: <strong>{{ $generatedAt }} {{ \Carbon\Carbon::parse($generatedAt)->format('A') }}
                   <br> Kano State LAnd ADmin Enterprise System

                  </strong>
            </div>
            <img class="logo" src="http://app.klaes.ng/assets/logo/las.jpg" alt="LAS logo">
        </div>
    </div>

    <div class="actions no-print">
        <button type="button" class="btn" onclick="window.print()">Print / Save PDF</button>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
