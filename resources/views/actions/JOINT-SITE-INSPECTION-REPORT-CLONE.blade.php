@php
    $inspectionDate = !empty($report->inspection_date) ? \Carbon\Carbon::parse($report->inspection_date)->format('d F Y') : 'N/A';
    $appliedLandUse = $report->applied_land_use ?? ($application->land_use ?? 'N/A');
    $applicantName = $report->applicant_name ?? ($application->applicant_name ?? 'N/A');
    $location = $report->location ?? ($application->property_location ?? 'N/A');
    $plotNumber = $report->plot_number ?? ($application->property_plot_no ?? 'N/A');
    $availableOnGround = (bool) ($report->available_on_ground ?? false);
    $availableText = $availableOnGround ? 'available on the ground' : 'not available on the ground';

    $boundaries = [];
    if (!empty($report->boundary_description)) {
        $boundaryDescriptionRaw = (string) $report->boundary_description;
        $decodedBoundary = json_decode($boundaryDescriptionRaw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBoundary)) {
            $boundaries['north'] = $decodedBoundary['north'] ?? null;
            $boundaries['east'] = $decodedBoundary['east'] ?? null;
            $boundaries['south'] = $decodedBoundary['south'] ?? null;
            $boundaries['west'] = $decodedBoundary['west'] ?? null;
        } else {
            $directionPatterns = [
                'north' => '/\b(?:on the\s*)?north\s*:\s*(.*?)(?=(?:\bon the\s*)?(?:east|south|west)\s*:|$)/is',
                'east' => '/\b(?:on the\s*)?east\s*:\s*(.*?)(?=(?:\bon the\s*)?(?:north|south|west)\s*:|$)/is',
                'south' => '/\b(?:on the\s*)?south\s*:\s*(.*?)(?=(?:\bon the\s*)?(?:north|east|west)\s*:|$)/is',
                'west' => '/\b(?:on the\s*)?west\s*:\s*(.*?)(?=(?:\bon the\s*)?(?:north|east|south)\s*:|$)/is',
            ];

            foreach ($directionPatterns as $direction => $pattern) {
                if (preg_match($pattern, $boundaryDescriptionRaw, $matches)) {
                    $value = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B;.");
                    if ($value !== '') {
                        $boundaries[$direction] = $value;
                    }
                }
            }
        }
    }

    $north = trim((string) ($boundaries['north'] ?? 'N/A'));
    $east = trim((string) ($boundaries['east'] ?? 'N/A'));
    $south = trim((string) ($boundaries['south'] ?? 'N/A'));
    $west = trim((string) ($boundaries['west'] ?? 'N/A'));

    $measurementSummary = trim((string) ($report->existing_site_measurement_summary ?? ''));
    if ($measurementSummary === '') {
        $measurementSummary = 'The following existing site measurements were observed during the joint site inspection:';
    }

    $existingDimensions = [];
    foreach (['existing_site_length', 'existing_site_width'] as $field) {
        $val = $report->{$field} ?? null;
        if ($val !== null && trim((string) $val) !== '') {
            $existingDimensions[] = trim((string) $val);
        }
    }
    $existingDimensionsText = !empty($existingDimensions) ? implode(' x ', $existingDimensions) : null;
    $existingArea = $report->existing_site_area ?? null;
    $existingAreaText = ($existingArea !== null && trim((string) $existingArea) !== '') ? trim((string) $existingArea) : null;
    $existingAreaDisplay = null;
    $existingAreaHectareDisplay = null;
    if ($existingAreaText !== null) {
        if (is_numeric($existingAreaText)) {
            $existingAreaNumeric = (float) $existingAreaText;
            $existingAreaDisplay = rtrim(rtrim(number_format($existingAreaNumeric, 2, '.', ''), '0'), '.');
            $existingAreaHectareDisplay = rtrim(rtrim(number_format($existingAreaNumeric / 10000, 4, '.', ''), '0'), '.');
        } else {
            $existingAreaDisplay = $existingAreaText;
        }
    }

    $existingMeasurementNarrative = trim($measurementSummary);
    if ($existingDimensionsText) {
        $existingMeasurementNarrative .= ' ' . $existingDimensionsText;
    }
    if ($existingAreaDisplay !== null) {
        $existingMeasurementNarrative .= ' (' . $existingAreaDisplay . 'm²';
        if ($existingAreaHectareDisplay !== null) {
            $existingMeasurementNarrative .= '/' . $existingAreaHectareDisplay . 'ha';
        }
        $existingMeasurementNarrative .= ')';
    }
    $prevailingLandUse = $report->prevailing_land_use ?? 'N/A';
    $complianceStatus = strtoupper((string) ($report->compliance_status ?? 'N/A'));
    $inspectionOfficer = 'TPL. ZAKARI SULEIMAN SALISU';

    $generatedBy = trim(collect([
        optional(auth()->user())->first_name,
        optional(auth()->user())->last_name,
    ])->filter()->implode(' '));

    if ($generatedBy === '') {
        $generatedBy = optional(auth()->user())->name ?? 'System';
    }

    $generatedAt = now()->format('F j, Y \a\t g:i A');
    $isPrintMode = (bool) ($printMode ?? false);

    $_fileNo = $report->file_number ?? ($application->fileno ?? null);
    $_encryptionKey = '';
    if ($_fileNo) {
        $_groupRow = \DB::connection('sqlsrv')->table('grouping')->where('awaiting_fileno', $_fileNo)->select('encryption_key')->first();
        $_encryptionKey = $_groupRow->encryption_key ?? '';
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joint Site Inspection Report</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background-color: #525659;
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .paper {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm 15mm;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
            color: #000;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
        }

        .logo-wrap {
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px dashed #ccc;
        }

        .logo-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .header-center {
            text-align: center;
            flex: 1;
            padding-top: 10px;
        }

        .header-center h1 {
            font-size: 13.5pt;
            margin: 0;
            text-decoration: underline;
            text-transform: uppercase;
            font-weight: 700;
            line-height: 1.3;
        }

        .header-center h2 {
            font-size: 11pt;
            margin: 8px 0 0 0;
            text-decoration: underline;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
        }

        .recipient-block {
            margin-top: 10px;
            margin-bottom: 25px;
        }

        .recipient-block .director {
            font-weight: 700;
            font-size: 11pt;
            display: block;
            margin-bottom: 12px;
        }

        .content {
            font-size: 10.5pt;
            line-height: 1.6;
            text-align: justify;
        }

        .section { margin-bottom: 18px; }
        .bold { font-weight: 700; }

        .data-table {
            margin: 18px 0;
            padding-left: 6px;
        }

        .data-row { margin-bottom: 6px; }

        .signature {
            margin-top: 60px;
            font-weight: 700;
            font-size: 11pt;
            text-transform: uppercase;
        }

        .footer-stamp {
            position: absolute;
            width: calc(100% - 30mm);
            text-align: center;
            font-size: 8pt;
            color: #444;
            border-top: 1px solid #eee;
            padding-top: 12px;
            bottom: 15mm;
            left: 15mm;
        }

        .footer-logo {
            position: absolute;
            bottom: 17mm;
            width: 100px;
            height: auto;
            object-fit: contain;
            opacity: 0.95;
        }

        .footer-logo-left {
            left: 15mm;
        }

        .footer-logo-right {
            right: 15mm;
            width: 115px;
        }

        .qr-code {
            text-align: center;
            margin-top: 4px;
        }

        .qr-code img {
            width: 45px;
            height: 45px;
        }

        .qr-code .qr-label {
            font-size: 7px;
            color: #666;
            margin-top: 1px;
        }

        .print-btn {
            margin-bottom: 16px;
            padding: 10px 18px;
            background: #111827;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 700;
        }

        @media print {
            body { background: none; padding: 0; }
            .paper { box-shadow: none; margin: 0; }
            .print-btn { display: none !important; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
<div>
    @if(!$isPrintMode)
        <button class="print-btn" onclick="window.print()">Print Report</button>
    @endif

    <div class="paper">
        <header>
            <div class="logo-wrap">
                <img src="{{ asset('http://app.klaes.ng/assets/images/logos/ministry-logo-left.jpg') }}" alt="Left Logo">
            </div>

            <div class="header-center">
                <h1>PHYSICAL PLANNING</h1>
                <h1>DEPARTMENT</h1>
                <h2>JOINT SITE INSPECTION REPORT</h2>
            </div>

            <div style="display:flex; flex-direction:row; align-items:flex-start; gap:8px;">
                <div class="qr-code">
                    @if($_encryptionKey)
                    <img src="{{ qr_data_uri($_encryptionKey, 150) }}" alt="QR Code">
                    @endif
                </div>
                <div class="logo-wrap">
                    <img src="{{ asset('http://app.klaes.ng/assets/images/logos/ministry-logo-right.jpeg') }}" alt="Right Logo">
                </div>
            </div>
        </header>

        <div class="recipient-block">
            <span class="director">THE DIRECTOR</span>
        </div>

        <div class="content">
            <div class="section">
                Below Is A Joint Site Inspection Report Conducted On <span class="bold">{{ $inspectionDate }}</span>
                Of An Application Made For <span class="bold">{{ $appliedLandUse }}</span>
                In The Name Of <span class="bold">{{ $applicantName }}</span>
                Located At <span class="bold">{{ $location }}</span>
                With Plot No <span class="bold">{{ $plotNumber }}</span>.
            </div>

            <div class="section">
                The site has been inspected and found to be <span class="bold">{{ $availableText }}</span>
                and bounded as follows:
                On the North: <span class="bold">{{ $north }}</span>;
                On the East: <span class="bold">{{ $east }}</span>;
                On the South: <span class="bold">{{ $south }}</span>;
                On the West: <span class="bold">{{ $west }}</span>.
            </div>

            <div class="section">
                <span class="bold">Existing Site Area Measurements:</span>
                <span>{{ $existingMeasurementNarrative }}</span>
            </div>

            <div class="data-table">
                <div class="data-row"><span class="bold">Prevailing Land use:</span> {{ $prevailingLandUse }}</div>
                <div class="data-row"><span class="bold">Applied land use:</span> {{ $appliedLandUse }}</div>
                <div class="data-row"><span class="bold">Compliance:</span> {{ $complianceStatus }}</div>
            </div>

            <div class="section">
                Based on the analysis conducted to assess compliance with statutory requirements and planning standards,
                the proposed site is <span class="bold">{{ $complianceStatus }}</span>.
            </div>

            <div class="signature">
                {{ $inspectionOfficer }}
            </div>
        </div>

        <div class="footer-stamp">
            Generated on {{ $generatedAt }} by {{ $generatedBy }}
        </div>

           <img class="footer-logo footer-logo-right  " src="{{ asset('http://app.klaes.ng/assets/logo/las.jpg') }}" alt="Footer right Logo">

           
         <img class="footer-logo  footer-logo-left" src="{{ asset('http://app.klaes.ng/storage/upload/logo/logo.png') }}" alt="Footer Left Logo">
     
       
    </div>
</div>
    @if($isPrintMode)
    <script>
        window.addEventListener('load', function () {
            if (window.top === window.self) {
                window.print();
            }
        });
    </script>
    @endif
</body>
</html>
