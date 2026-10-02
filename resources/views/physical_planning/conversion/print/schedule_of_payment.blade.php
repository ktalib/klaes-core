@php
    $_fileNo = $fileNumber ?? null;
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
    <title>Schedule of Payment - Kano State</title>
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
            padding: 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
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
            font-size: 22px;
            margin: 5px 0;
            text-transform: uppercase;
            font-weight: bold;
        }

        .header h2 {
            font-size: 20px;
            margin: 5px 0;
            text-transform: uppercase;
            font-weight: bold;
        }

        .header h3 {
            font-size: 18px;
            margin: 15px 0;
            text-transform: uppercase;
            font-style: italic;
            text-decoration: underline;
        }

        .recipient-line {
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .instruction {
            font-style: italic;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        td {
            border: 2px solid #000;
            padding: 12px;
            font-size: 15px;
            height: 35px;
            text-transform: uppercase;
        }

        .label-cell {
            width: 40%;
            font-weight: bold;
        }

        .sig-section {
            margin-top: 50px;
            line-height: 2.5;
        }

        .sig-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 15px;
        }

        .sig-label {
            font-weight: bold;
            width: 100px;
            font-size: 16px;
        }

        .sig-line {
            flex-grow: 1;
            border-bottom: 2px solid #000;
            margin-left: 10px;
        }

        .footer-logo {
            position: absolute;
            bottom: 8mm;
            width: 44px;
            height: auto;
            object-fit: contain;
        }

        .footer-logo-left {
            left: 20mm;
        }

        .footer-logo-right {
            right: 20mm;
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

        @media print {
            .print-btn { display: none; }
            body { background-color: #fff; padding: 0; }
            .form-container { box-shadow: none; border: none; width: 100%; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print Schedule of Payment</button>

    <div class="form-container">
        <div class="header">
            <img class="logo" src="{{ asset('http://app.klaes.ng/assets/images/logos/ministry-logo-left.jpg') }}" alt="Ministry Logo Left">
            <div class="header-text">
                <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h2>KANO STATE</h2>
                <h3>Schedule of Payment</h3>
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

        <div class="recipient-line">ACCOUNTANT:</div>
        <div class="instruction">Please receive payment as per details below</div>

        <table>
            <tr>
                <td class="label-cell">NAME</td>
                <td>{{ strtoupper($name ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-cell">FILE NUMBER</td>
                <td>{{ strtoupper($fileNumber ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-cell">PURPOSE OF PAYMENT</td>
                <td>{{ strtoupper($purposeOfPayment ?: 'N/A') }}</td>
            </tr>
            <tr>
                <td class="label-cell">AMOUNT</td>
                <td>{{ $amount !== null ? 'N' . number_format((float) $amount, 2) : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label-cell">TICK WHERE APPROPRIATE</td>
                <td>BANK / ACCOUNT NUMBER</td>
            </tr>
            <tr>
                <td class="label-cell">LAND RELATED CHARGES</td>
                <td>JAIZ BANK</td>
            </tr>
            <tr>
                <td class="label-cell">OTHER CHARGES</td>
                <td>TAJ BANK</td>
            </tr>
        </table>

        <div class="sig-section">
            <div class="sig-row">
                <span class="sig-label">NAME:</span>
                <div class="sig-line"></div>
            </div>
            <div class="sig-row">
                <span class="sig-label">RANK:</span>
                <div class="sig-line"></div>
            </div>
            <div class="sig-row">
                <span class="sig-label">SIGNATURE:</span>
                <div class="sig-line"></div>
            </div>
            <div class="sig-row">
                <span class="sig-label">DATE:</span>
                <div class="sig-line">{{ $generatedDate ? $generatedDate->format('d/m/Y') : '' }}</div>
            </div>
        </div>


        <img class="footer-logo footer-logo-left" src="{{ asset('http://app.klaes.ng/storage/upload/logo/logo.png') }}" alt="Footer Right Logo">
        <img class="footer-logo footer-logo-right" src="{{ asset('http://app.klaes.ng/assets/logo/las.jpg') }}" alt="Footer Left Logo">
    </div>
</body>
</html>
