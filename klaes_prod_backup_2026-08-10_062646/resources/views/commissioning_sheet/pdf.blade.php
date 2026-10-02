<!DOCTYPE html>
<html lang="en"  for the ST application  url/approvals/planning_recomm?url=view
 >
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Commissioning Sheet</title>
    @php
        $fileNumber = $data['file_number'] ?? '';
        $fileName = $data['file_name'] ?? '';
        $allottee = $data['name_or_allottee'] ?? '';
        $reason = $data['plot_number'] ?? '';
        $tpNumber = $data['tp_number'] ?? '';
        $location = $data['location'] ?? '';
        $timeCommissioned = $data['commissioning_time'] ?? ($data['time_created'] ?? '');
        $dateCommissioned = $data['commissioning_date'] ?? ($data['date_created'] ?? '');
        $commissionedBy = $data['created_by'] ?? '';
        $trackingId = $data['tracking_id'] ?? $fileNumber;

        // SIT files show the reason directly after the Location.
        $sitReason = $data['sit_reason'] ?? '';
        $isSit = stripos((string) $fileNumber, 'SIT-') === 0;
        $originalOpHolder = $data['original_op_holder'] ?? '';

        $status = request('status', 'Original');
        $isCtc = request('isCTC') == '1';
        $isOss = request('source') === 'oss';
        $watermarkText = $isCtc ? 'CERTIFIED TRUE COPY' : (($status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL');
        $qrPayload = $trackingId ?: $fileNumber;

        try {
            if (!empty($timeCommissioned)) {
                $timeCommissioned = \Carbon\Carbon::parse($timeCommissioned)->format('h:i A');
            }
        } catch (\Throwable $e) {
            // Keep original value when parsing fails.
        }

        try {
            if (!empty($dateCommissioned)) {
                $dateCommissioned = \Carbon\Carbon::parse($dateCommissioned)->format('Y-m-d');
            }
        } catch (\Throwable $e) {
            // Keep original value when parsing fails.
        }
    @endphp
    <style>
        @page {
            size: A4;
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
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 15mm 18mm 10mm;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .sheet-body {
            flex: 1;
        }

        .sheet-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-top: 8mm;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8mm;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .header-table .logo-cell {
            width: 18mm;
            min-width: 18mm;
            max-width: 18mm;
        }

        .header-table .logo-cell img {
            width: 18mm;
            height: 18mm;
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
            font-size: 4.5mm;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: 0.2px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .title-sub {
            margin: 1.5mm 0 0;
            font-size: 3mm;
            font-weight: 800;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .title-doc {
            margin: 3mm 0 0;
            font-size: 3.5mm;
            font-weight: 800;
            text-transform: uppercase;
        }

        .qr-wrap {
            text-align: center;
            margin: 6mm 0;
        }

        .qr-wrap img {
            width: 24mm;
            height: 24mm;
            image-rendering: pixelated;
        }

        .qr-subtitle {
            margin-top: 3.5mm;
            font-size: 3.2mm;
            font-weight: 700;
            letter-spacing: 1.2mm;
            color: #94a3b8;
            text-transform: uppercase;
        }

        .fields {
            margin-top: 2mm;
        }

        .row {
            display: grid;
            grid-template-columns: 52mm 1fr;
            align-items: end;
            margin-bottom: 8mm;
        }

        .label {
            font-size: 4.2mm;
            font-weight: 700;
            line-height: 1;
            padding-right: 5mm;
            white-space: nowrap;
            text-align: left;
        }

        .value-line {
            border-bottom: 0.3mm solid #444444;
            min-height: 7mm;
            display: flex;
            align-items: center;
            font-size: 4.6mm;
            line-height: 1;
            padding: 0 2mm 1mm;
        }

        /* Long values (e.g. Reason / sit_reason) wrap onto multiple lines instead of overflowing. */
        .value-line.wrap {
            display: block;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            line-height: 1.3;
            padding: 1.5mm 2mm;
        }

        .watermark {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%) rotate(-49deg);
            font-size: 18mm;
            font-weight: 700;
            color: rgba(230, 30, 30, 0.15);
            text-transform: uppercase;
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
        }

        .content {
            position: relative;
            z-index: 2;
        }

        .signatures {
            margin-top: 15mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 20mm;
            align-items: end;
        }

        .sig {
            text-align: center;
        }

        .sig-label {
            font-size: 4mm;
            margin-top: 3mm;
        }

        .sig-line {
            border-bottom: 0.3mm solid #444444;
            height: 0;
            width: 100%;
        }

        .mark {
            width: 16mm;
            height: 16mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 1.2mm;
            flex-shrink: 0;
        }

        .mark span {
            border-radius: 2.5mm;
            display: block;
        }

        .m-red { background: #ea1b1b; }
        .m-black { background: #0a0a0a; }
        .m-green { background: #0f6d3c; }
        .m-yellow { background: #f6c106; }

        .footer-logo-right {
            width: 45mm;
            height: auto;
            max-height: 18mm;
            object-fit: contain;
            flex-shrink: 0;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="watermark">{{ $watermarkText }}</div>

        <div class="sheet-body">
        <div class="content">
            <table class="header-table">
                <tr>
                    <td class="logo-cell">
                        <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Left Logo" onerror="this.style.display='none'">
                    </td>
                    <td class="title-cell">
                        <h1 class="title-main">MINISTRY OF LAND &amp; PHYSICAL PLANNING</h1>
                        <h2 class="title-sub">{{ $isOss ? 'DEPARTMENT OF LAND' : 'DEPARTMENT OF COMPLAINT INVESTIGATION AND VERIFICATION' }}</h2>
                        <h3 class="title-doc">FILE COMMISSIONING SHEET</h3>
                    </td>
                    <td class="logo-cell logo-cell-right">
                        <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Right Logo" onerror="this.style.display='none'">
                    </td>
                </tr>
            </table>

            <div class="qr-wrap">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($qrPayload) }}" alt="QR Code">
                    @if($isOss)
                    <div class="qr-subtitle" style="color:#ea1b1b">LANDS ONE STOP SHOP</div>
                    @endif
            <div class="fields">
                <div class="row">
                    <div class="label">File No/(File Type):</div>
                    <div class="value-line">
                        {{ $fileNumber }}
                        @if(!empty($data['related_file_title']))
                            ({{ $data['related_file_title'] }})
                        @endif
                    </div>
                </div>
                @if(!empty($data['related_file_number']))
                <div class="row">
                    <div class="label">Related File No:</div>
                    <div class="value-line">{{ $data['related_file_number'] }}</div>
                </div>
                @endif
                @if(!empty($allottee))
                <div class="row">
                    <div class="label">File Title:</div>
                    <div class="value-line">{{ $allottee }}</div>
                </div>
                @endif
                @if(!empty($originalOpHolder))
                <div class="row">
                    <div class="label">Original OP Holder:</div>
                    <div class="value-line">{{ $originalOpHolder }}</div>
                </div>
                @endif
                <div class="row">
                    <div class="label">{{ $isOss ? 'Plot No:' : 'Reason:' }}</div>
                    <div class="value-line{{ $isOss ? '' : ' wrap' }}">{{ $reason }}</div>
                </div>
                <div class="row">
                    <div class="label">TP No:</div>
                    <div class="value-line">{{ $tpNumber }}</div>
                </div>
                <div class="row">
                    <div class="label">Location:</div>
                    <div class="value-line">{{ $location }}</div>
                </div>
                @if($isSit && !empty($sitReason))
                <div class="row">
                    <div class="label">Reason:</div>
                    <div class="value-line wrap">{{ $sitReason }}</div>
                </div>
                @endif
                <div class="row">
                    <div class="label">Time Commissioned:</div>
                    <div class="value-line">{{ $timeCommissioned }}</div>
                </div>
                <div class="row">
                    <div class="label">Date Commissioned:</div>
                    <div class="value-line">{{ $dateCommissioned }}</div>
                </div>
                <div class="row">
                    <div class="label">Commissioned by:</div>
                    <div class="value-line">{{ $commissionedBy }}</div>
                </div>
            </div>
<br><br>
            <div class="signatures">
                <div class="sig">
                    <div class="sig-line"></div>
                    <div class="sig-label">Created by Signature</div>
                </div>
                <div class="sig">
                    <div class="sig-line"></div>
                    <div class="sig-label">Approved by Signature</div>
                </div>
            </div>
        </div>
        </div>{{-- end .sheet-body --}}
<br><br>
        <div class="sheet-footer">
            <img src="http://app.klaes.ng/storage/upload/logo/Klase.png" alt="Left Footer Logo" class="footer-logo-left" style="height: 16mm; width: auto; max-height: 16mm; object-fit: contain;" onerror="this.style.display='none'">
            <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Right Footer Logo" class="footer-logo-right" onerror="this.style.display='none'">
        </div>
    </div>
</body>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() { window.print(); }, 500);
    });
</script>
</html>
            