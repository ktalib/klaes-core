<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Movement History &ndash; {{ $fileNumber }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111111;
            background: #ffffff;
        }

        .sheet {
            width: 297mm;
            min-height: 210mm;
            margin: 0 auto;
            padding: 10mm 15mm 8mm;
            display: flex;
            flex-direction: column;
        }

        .sheet-body {
            flex: 1;
        }

        /* ---------- Screen toolbar (hidden when printing) ---------- */
        .print-toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 10px 15mm;
            background: #f4f4f4;
            border-bottom: 1px solid #dddddd;
        }

        .print-toolbar button {
            font: inherit;
            font-size: 13px;
            padding: 6px 16px;
            border: 1px solid #333333;
            border-radius: 4px;
            background: #ffffff;
            cursor: pointer;
        }

        .print-toolbar button.primary {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #ffffff;
        }

        /* ---------- Header ---------- */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .header-table .logo-cell {
            width: 22mm;
            min-width: 22mm;
            max-width: 22mm;
        }

        .header-table .logo-cell img {
            width: 22mm;
            height: 22mm;
            object-fit: contain;
            display: block;
        }

        .header-table .logo-cell-right {
            text-align: right;
        }

        .header-table .logo-cell-right img {
            margin-left: auto;
        }

        .header-table .title-cell {
            text-align: center;
            padding: 0 4mm;
        }

        .title-main {
            margin: 0;
            font-size: 4.6mm;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .title-doc {
            margin: 2.5mm 0 0;
            font-size: 4mm;
            font-weight: 800;
            text-transform: uppercase;
            text-decoration: underline;
        }

        /* ---------- File info fields ---------- */
        .file-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 16mm;
            row-gap: 5mm;
            margin: 4mm 0 7mm;
        }

        .info-line,
        .info-label {
            white-space: nowrap;
        }

        .info-line {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .info-row {
            display: flex;
            align-items: flex-end;
        }

        .info-label {
            font-size: 3.8mm;
            font-weight: 700;
            white-space: nowrap;
            padding-right: 3mm;
            line-height: 1;
        }

        .info-line {
            flex: 1;
            border-bottom: 0.3mm solid #444444;
            min-height: 5.5mm;
            font-size: 3.8mm;
            padding: 0 1.5mm 0.5mm;
        }

        /* ---------- Movement table ---------- */
        .movement-table {
            width: 100%;
            border-collapse: collapse;
        }

        .movement-table th,
        .movement-table td {
            border: 0.35mm solid #333333;
            font-size: 3.6mm;
            padding: 2mm 2mm;
        }

        .movement-table th {
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            background: #f1f1f1;
        }

        .movement-table td {
            height: 10mm;
            vertical-align: middle;
        }

        .movement-table .col-sn { width: 10mm; text-align: center; }
        .movement-table .col-history { width: auto; }
        .movement-table .col-date { width: 26mm; text-align: center; }
        .movement-table .col-duration { width: 24mm; text-align: center; }
        .movement-table .col-purpose { width: 32mm; }
        .movement-table .col-timeline { width: 24mm; text-align: center; }
        .movement-table .col-return-date { width: 26mm; text-align: center; }
        .movement-table .col-remarks { width: 40mm; }
        .movement-table .col-delay { width: 30mm; }

        .col-note {
            display: block;
            font-weight: 400;
            font-size: 2.8mm;
            text-transform: none;
            margin-top: 0.8mm;
        }

        /* ---------- Footer ---------- */
        .sheet-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-top: 8mm;
        }

        .footer-logo {
            height: 16mm;
            width: auto;
            max-width: 45mm;
            object-fit: contain;
            flex-shrink: 0;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .print-toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button type="button" onclick="window.close()">Close</button>
        <button type="button" class="primary" onclick="window.print()">Print</button>
    </div>

    <div class="sheet">
        <div class="sheet-body">
            <table class="header-table">
                <tr>
                    <td class="logo-cell">
                        <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Left Logo" onerror="this.style.display='none'">
                    </td>
                    <td class="title-cell">
                        <h1 class="title-main">MINISTRY OF LAND &amp; PHYSICAL PLANNING</h1>
                        <h2 class="title-doc">FILE MOVEMENT HISTORY</h2>
                    </td>
                    <td class="logo-cell logo-cell-right">
                        <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Right Logo" onerror="this.style.display='none'">
                    </td>
                </tr>
            </table>

            {{-- Two column stacks, per the approved sketch:
                 left — File No, File Title; right — Tracking ID, Registry, Shelf/Rack --}}
            <div class="file-info">
                <div class="info-row">
                    <span class="info-label">File No:</span>
                    <span class="info-line">{{ $fileNumber }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tracking ID:</span>
                    <span class="info-line">{{ $trackingId }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">File Title:</span>
                    <span class="info-line">{{ $fileTitle }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Registry:</span>
                    <span class="info-line">{{ $registry }}</span>
                </div>
                <div></div>
                <div class="info-row">
                    <span class="info-label">Shelf/Rack:</span>
                    <span class="info-line">{{ $shelf }}</span>
                </div>
            </div>

            <table class="movement-table">
                <thead>
                    <tr>
                        <th class="col-sn">S/N</th>
                        <th class="col-history">File Movement History
                             
                        </th>
                        <th class="col-date">Date</th>
                        <th class="col-duration">Duration</th>
                        <th class="col-purpose">Request Purpose</th>
                        <th class="col-timeline">Timeline</th>
                        <th class="col-return-date">Expected Return Date</th>
                        <th class="col-remarks">Remarks</th>
                        <th class="col-delay">Delay Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $index => $row)
                    <tr>
                        <td class="col-sn">{{ $index + 1 }}.</td>
                        <td class="col-history">
                            @if(!empty($row['officer']))
                                {{ $row['officer'] }} ({{ $row['office'] }})
                            @else
                                {{ $row['office'] }}
                            @endif
                        </td>
                        <td class="col-date">{{ $row['date'] }}</td>
                        <td class="col-duration">{{ $row['duration'] }}</td>
                        <td class="col-purpose">{{ $row['request_purpose'] ?? '—' }}</td>
                        <td class="col-timeline">{{ $row['timeline'] ?? '—' }}</td>
                        <td class="col-return-date">{{ $row['expected_return_date'] ?? '—' }}</td>
                        <td class="col-remarks">{{ $row['remarks'] }}</td>
                        <td class="col-delay">{{ $row['delay_reason'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="sheet-footer">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Left Footer Logo" class="footer-logo" onerror="this.style.display='none'">
            <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Right Footer Logo" class="footer-logo" onerror="this.style.display='none'">
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
</body>
</html>
