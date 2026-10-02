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
        $district = $data['district'] ?? '';
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

        // Which department issued the file. ST files are commissioned by Sectional
        // Titling, not DCIV — recognised from the caller's ?source=st or, failing
        // that, from the number itself (ST-… and its conversion form ST-CON-…).
        $isSt = request('source') === 'st' || stripos((string) $fileNumber, 'ST-') === 0;
        $department = $isSt
            ? 'DEPARTMENT OF SECTIONAL TITLING'
            : ($isOss ? 'DEPARTMENT OF LAND' : 'DEPARTMENT OF COMPLAINT INVESTIGATION AND VERIFICATION');

        // The file type printed beside the number comes from mls_file_no.source, which
        // says "Conversion" for both a land and a Sectional Titling conversion.
        $fileType = trim((string) ($data['related_file_title'] ?? ''));
        if ($isSt && strcasecmp($fileType, 'Conversion') === 0) {
            $fileType = 'ST Conversion';
        }
        // Whether the shared row prints a Plot No or a Reason: the departments that hold a
        // plot number (Land / Sectional Titling) print the number, DCIV prints the reason.
        $isPlotNoRow = $isOss || $isSt;

        $watermarkText = $isCtc ? 'CERTIFIED TRUE COPY' : (($status === 'Certified True Copy') ? 'CERTIFIED TRUE COPY' : 'ORIGINAL');
        $qrPayload = $trackingId ?: $fileNumber;

        // Passport photograph filed at commissioning (already a data URI or URL, resolved
        // by CommissioningSheetController); absent on files commissioned without one.
        $passportImage = $data['passport_image'] ?? '';

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
            /* Two logos again — the KLAES mark on the left, the LAnd ADmin one on the right. */
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

        /* QR keeps the centre of the sheet; the passport sits in the right margin beside it. */
        .qr-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .qr-table td {
            padding: 0;
            vertical-align: top;
        }

        .qr-table .qr-side {
            width: 18mm;
        }

        .qr-table .passport-cell {
            text-align: right;
        }

        .passport-box {
            width: 30mm;
            height: 38mm;
            border: 0.3mm solid #444444;
            display: inline-block;
            overflow: hidden;
        }

        .passport-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .passport-caption {
            font-size: 3mm;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3mm;
            margin-top: 1.5mm;
            text-align: center;
            width: 30mm;
            display: inline-block;
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

        /* The Related/Old FileNo label is the longest on the sheet; it is set a little
           smaller so it still fits the 52mm label column without wrapping. */
        .label--wide {
            font-size: 3.6mm;
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

        /* Matched in height to the right-hand mark; the width follows the image. */
        .footer-logo-left {
            height: 11.5mm;
            width: auto;
            object-fit: contain;
        }

        /* Down from 45x18mm — the mark stood too tall against the footer line. */
        .footer-logo-right {
            width: 28.8mm;
            height: auto;
            max-height: 11.5mm;
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
                        <img src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Ministry Logo" onerror="this.style.display='none'">
                    </td>
                    <td class="title-cell">
                        <h1 class="title-main">MINISTRY OF LAND &amp; PHYSICAL PLANNING</h1>
                        <h2 class="title-sub">{{ $department }}</h2>
                        <h3 class="title-doc">{{ $isSt ? 'ST FILE COMMISSIONING SHEET' : 'FILE COMMISSIONING SHEET' }}</h3>
                    </td>
                    <td class="logo-cell logo-cell-right">
                        <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Right Logo" onerror="this.style.display='none'">
                    </td>
                </tr>
            </table>

            <table class="qr-table">
                <tr>
                    <td class="qr-side"></td>
                    <td>
                        <div class="qr-wrap">
                            <img src="{{ qr_data_uri($qrPayload, 150) }}" alt="QR Code">
                            @if($isOss)
                            <div class="qr-subtitle" style="color:#ea1b1b">LANDS ONE STOP SHOP</div>
                            @endif
                        </div>
                    </td>
                    {{-- The applicant's passport photograph, uploaded when the file number was
                         generated. Files commissioned without one simply print nothing here. --}}
                    <td class="qr-side passport-cell">
                        @if(!empty($passportImage))
                        <div class="passport-box">
                            <img src="{{ $passportImage }}" alt="Passport Photograph" onerror="this.parentElement.style.display='none'">
                        </div>
                        @endif
                    </td>
                </tr>
            </table>
            <div class="fields">
                {{-- On an ST sheet the ST number leads and the CON mother file follows;
                     everywhere else the file's own number comes first. --}}
                @if($isSt && !empty($data['related_file_number']))
                <div class="row">
                    <div class="label">ST FileNo:</div>
                    {{-- The file type describes the ST application, so it rides with the
                         ST number; the CON line below is the plain land file number. --}}
                    <div class="value-line">
                        {{ $data['related_file_number'] }}
                        @if(!empty($fileType))
                            ({{ $fileType }})
                        @endif
                    </div>
                </div>
                @endif
                {{-- An ST sheet names its two numbers on their own lines: the file's
                     own above, the primary it sits under below. Everywhere else the
                     one line carries the file type in brackets, as it always has. --}}
                <div class="row">
                    <div class="label">{{ $isSt ? 'New ST FileNo:' : 'File No/(File Type):' }}</div>
                    <div class="value-line">
                        {{ $fileNumber }}
                        @if(!empty($fileType) && !$isSt)
                            ({{ $fileType }})
                        @endif
                    </div>
                </div>
                @if($isSt && !empty($data['st_primary_file_number']))
                <div class="row">
                    <div class="label">MLPP FileNo:</div>
                    <div class="value-line">{{ $data['st_primary_file_number'] }}</div>
                </div>
                @endif
                {{-- The line is part of the sheet's shape, not an optional extra: a file
                     with neither an old number (only a Re-Issuance has one) nor a related
                     one still prints the label over a blank rule, the same as TP No and
                     Plot No do. A file carrying both keeps a line for each. --}}
                @php
                    $oldFileNumberLine = trim((string) ($data['old_file_number'] ?? ''));
                    $relatedFileNumberLine = $isSt ? '' : trim((string) ($data['related_file_number'] ?? ''));
                    $relatedOrOldLines = array_values(array_filter([$oldFileNumberLine, $relatedFileNumberLine], fn ($v) => $v !== ''));
                    if (empty($relatedOrOldLines)) {
                        $relatedOrOldLines = [''];
                    }
                @endphp
                @foreach($relatedOrOldLines as $relatedOrOldLine)
                <div class="row">
                    <div class="label label--wide">Related FileNo/Old FileNo:</div>
                    <div class="value-line">{{ $relatedOrOldLine }}</div>
                </div>
                @endforeach
                @if(!empty($allottee))
                <div class="row">
                    <div class="label">File Title:</div>
                    {{-- Names print in full caps on this sheet. --}}
                    <div class="value-line">{{ mb_strtoupper($allottee) }}</div>
                </div>
                @endif
                @if(!empty($originalOpHolder))
                <div class="row">
                    <div class="label">Original OP Holder:</div>
                    <div class="value-line">{{ $originalOpHolder }}</div>
                </div>
                @endif
                {{-- The property block reads TP No > Plot No > District > Location. --}}
                <div class="row">
                    <div class="label">TP No:</div>
                    <div class="value-line">{{ $tpNumber }}</div>
                </div>
                {{-- One row, two readings. An OSS or ST file puts its plot number here;
                     a DCIV file has no plot number but does carry the reason it was
                     commissioned, which is why the label follows the department. The row
                     used to be dropped entirely for ST, which left that sheet with no
                     Plot No line at all. --}}
                <div class="row">
                    <div class="label">{{ $isPlotNoRow ? 'Plot No:' : 'Reason:' }}</div>
                    <div class="value-line{{ $isPlotNoRow ? '' : ' wrap' }}">{{ $reason }}</div>
                </div>
                <div class="row">
                    <div class="label">District:</div>
                    <div class="value-line">{{ $district }}</div>
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
            {{-- Two marks: KLAES on the left, LAnd ADmin on the right. Either drops out
                 quietly if its file cannot be reached. --}}
            <img src="{{ asset('assets/logo/logo.png') }}" alt="KLAES Logo" class="footer-logo-left"
                 onerror="if(!this.dataset.fallback){this.dataset.fallback='1';this.src='http://app.klaes.ng/storage/upload/logo/logo.png';}else{this.style.display='none';}">
            <img src="http://app.klaes.ng/assets/logo/Left_Logo.png" alt="Footer Logo" class="footer-logo-right" onerror="this.style.display='none'">
        </div>
    </div>
</body>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() { window.print(); }, 500);
    });
</script>
</html>
            