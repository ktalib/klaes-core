@php
    $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
        (string) ($applicationNumber ?? ($report->file_number ?? ($report->id ?? ''))),
        (int) $report->id,
        'PPC Recomm'
    );
    $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);

    $_fileNo = $applicationNumber ?? ($report->file_number ?? null);
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
    <title>Physical Planning Department - Recommendation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            margin: 0;
        }

        .print-btn {
            margin-bottom: 20px;
            padding: 10px 25px;
            background-color: #000;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }

        .form-container {
            background-color: #fff;
            width: 210mm;
            min-height: 297mm;
            padding: 10mm 12mm 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .serial-box {
            border: 1px solid #000;
            padding: 4px 8px;
            min-width: 120px;
            margin-top: 4px;
            font-size: 11px;
        }

        .serial-box .serial-label {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
            margin-bottom: 4px;
            font-size: 11px;
        }

        .serial-code-wrap {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            letter-spacing: normal;
        }

        .serial-fraction {
            line-height: 1;
            color: #1e293b;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            font-weight: 900;
        }

        .serial-fraction .frac-top {
            border-bottom: 1.5px solid #1e293b;
            padding-bottom: 1px;
            font-size: 8px;
        }

        .serial-fraction .frac-bot {
            padding-top: 1px;
            font-size: 8px;
        }

        .serial-digits {
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.1em;
            color: #1e293b;
            font-family: 'Courier New', monospace;
        }

        .logo {
            width: 74px;
            height: 74px;
            object-fit: contain;
        }

        .header-text {
            flex: 1;
            text-align: center;
            margin: 0 10px;
        }

        .header h1 {
            font-size: 20px;
            margin: 5px 0;
            text-transform: uppercase;
        }

        .header h2 {
            font-size: 18px;
            margin: 5px 0;
            text-transform: uppercase;
        }

        .header h3 {
            font-size: 14px;
            margin: 15px 0;
            text-transform: uppercase;
            font-weight: bold;
        }

        .date-line {
            text-align: right;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 11px;
        }

        .dotted-line {
            border-bottom: 2px dotted #000;
            display: inline-block;
            min-width: 150px;
            padding-bottom: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }

        td {
            border: 1px solid #000;
            padding: 8px 10px;
            font-size: 12px;
            vertical-align: top;
        }

        .label-col {
            width: 30%;
            font-weight: normal;
        }

        .data-col {
            width: 70%;
            font-weight: bold;
            min-height: 25px;
        }

        .large-cell {
            height: 65px;
        }

        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-top: 35px;
            text-align: center;
        }

        .footer-logo {
            position: absolute;
            bottom: 8mm;
            width: 100px;
            height: 100px;
            object-fit: contain;
        }

        .footer-logo-left {
            left: 12mm;
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

        .sig-block {
            width: 30%;
        }

        .sig-line {
            border-top: 2px dotted #000;
            margin-bottom: 10px;
            min-height: 20px;
        }

        .sig-title {
            font-weight: bold;
            font-size: 14px;
        }

        @media print {
            .print-btn { display: none; }
            body { background-color: #fff; padding: 0; }
            .form-container {
                box-shadow: none;
                border: none;
                width: 210mm;
                min-height: 297mm;
                height: 297mm;
                padding: 8mm 10mm 18mm;
                overflow: hidden;
            }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Recommendation Form</button>

    <div class="form-container">
        <div class="header">
            <img class="logo" src="{{ asset('http://app.klaes.ng/assets/images/logos/ministry-logo-left.jpg') }}" alt="Ministry Logo Left">
            <div class="header-text">
                <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h2>KANO</h2>
                <h3>PHYSICAL PLANNING DEPARTMENT (FINAL RECOMMENDATION)</h3>
            </div>
            <div style="display:flex; flex-direction:row; align-items:flex-start; gap:8px;">
                <div class="qr-code">
                    @if($_encryptionKey)
                    <img src="{{ qr_data_uri($_encryptionKey, 150) }}" alt="QR Code">
                    @endif
                </div>
                <img class="logo" src="{{ asset('http://app.klaes.ng/assets/images/logos/ministry-logo-right.jpeg') }}" alt="Ministry Logo Right">
            </div>
        </div>

       

        <div class="date-line">
            APPLICATION DATE:
            <span class="dotted-line">{{ $applicationDate ? $applicationDate->format('d/m/Y') : '' }}</span>
        </div>

        <table>
            <tr>
                <td class="label-col">FROM</td>
                <td class="data-col">DIRECTOR PHYSICAL PLANNING</td>
            </tr>
            <tr>
                <td class="label-col">TO</td>
                <td class="data-col">PERMANENT SECRETARY</td>
            </tr>
            <tr>
                <td class="label-col">APPLICANT NAME</td>
                <td class="data-col">{{ strtoupper($applicantName ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">APPLICATION NUMBER</td>
                <td class="data-col">{{ strtoupper((string) ($applicationNumber ?: 'N/A')) }}</td>
            </tr>
            <tr>
                <td class="label-col">LOCATION</td>
                <td class="data-col">{{ strtoupper($location ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">PURPOSE</td>
                <td class="data-col">{{ strtoupper($purpose ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">SITE MEASUREMENTS</td>
                <td class="data-col large-cell">{{ strtoupper($siteMeasurements ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">RECOMMENDED SITE MEASUREMENTS</td>
                <td class="data-col large-cell">{{ strtoupper($recommendedSiteMeasurements ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">EXISTING ROAD RESERVATION</td>
                <td class="data-col">{{ strtoupper($existingRoadReservation ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">SURROUNDING LAND USE</td>
                <td class="data-col">{{ strtoupper($surroundingLandUse ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">RECOMMENDED ROAD RESERVATION</td>
                <td class="data-col">{{ strtoupper($recommendedRoadReservation ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">RECOMMENDED LAND USE</td>
                <td class="data-col">{{ strtoupper($recommendedLandUse ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-col">CHANGE OF PURPOSE</td>
                <td class="data-col" style="font-weight: normal; font-size: 11px;">Any change of purpose must be with the consent of the physical planning department</td>
            </tr>
            <tr>
                <td class="label-col">BUILDING PERMISSION</td>
                <td class="data-col" style="font-weight: normal; font-size: 11px;">Building plan must be forwarded to the ministry for registration/approval.</td>
            </tr>
            <tr>
                <td class="label-col">ALTERATION</td>
                <td class="data-col" style="font-weight: normal; font-size: 11px;">Any Alteration on the recommended site plan renders this recommendation invalid.</td>
            </tr>
        </table>
        <br>
        <br>
        <div class="signature-row">
            <div class="sig-block">
                <div class="sig-line">{{ strtoupper($inspectionOfficer ?: 'N/A') }}</div>
                <div class="sig-title">Inspection Officer</div>
            </div>

            <div class="sig-block" style="margin-top: 40px;">
                <div class="sig-line"></div>
                <div class="sig-title">Director</div>
            </div>

            <div class="sig-block">
                <div class="sig-line"></div>
                <div class="sig-title">Deputy Director</div>
            </div>
        </div>

        <img class="footer-logo footer-logo-right" src="{{ asset('http://app.klaes.ng/assets/logo/las.jpg') }}" alt="Footer right Logo">
        <img class="footer-logo footer-logo-left" src="{{ asset('http://app.klaes.ng/storage/upload/logo/logo.png') }}" alt="Footer Left Logo">
    </div>

</body>
</html>
