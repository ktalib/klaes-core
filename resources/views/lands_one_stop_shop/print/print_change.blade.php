{{-- Print template based on docs/templates/landsoss/one-stop3.html --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OP Change of Ownership Form</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            margin: 0;
        }

        .print-bar {
            margin-bottom: 20px;
            text-align: center;
        }
        .print-bar button {
            padding: 10px 25px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            margin: 0 5px;
        }
        .print-bar button:hover { background-color: #1b5e20; }
        .print-bar button.secondary { background-color: #64748b; }
        .print-bar button.secondary:hover { background-color: #475569; }

        .form-container {
            background-color: white;
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            /* Column layout so the footer logos can be pushed to the page bottom. */
            display: flex;
            flex-direction: column;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .qr-placeholder {
            width: 85px;
            height: 85px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            overflow: hidden;
        }
        .qr-placeholder img {
            width: 85px;
            height: 85px;
            display: block;
            object-fit: contain;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            /* Push the logos to the bottom of the page (fills remaining space). */
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #ddd;
        }
        .form-footer .footer-logo {
            max-height: 60px;
            max-width: 160px;
            object-fit: contain;
        }

        .center-logo {
            text-align: center;
            flex-grow: 1;
        }
        .center-logo img { width: 75px; }

        .ministry-title {
            text-align: center;
            margin-top: 5px;
        }
        .ministry-title h1 {
            color: #2e7d32;
            font-size: 22px;
            margin: 0;
            text-transform: uppercase;
        }
        .ministry-title p {
            font-size: 10px;
            margin: 2px 0;
            color: #333;
        }

        .ref-box {
            border: 1px solid #999;
            margin-top: 15px;
            padding: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        .badge-heading {
            text-align: center;
            margin: 20px 0;
        }
        .badge-text {
            padding: 0;
            display: inline-block;
            text-align: center;
        }
        .badge-top {
            color: #2e7d32;
            font-weight: bold;
            font-size: 20px;
            padding: 5px 20px;
            display: block;
        }
        .badge-bottom {
            background-color: #f28d8d;
            color: white;
            font-weight: bold;
            font-size: 16px;
            padding: 5px 20px;
            display: block;
            text-transform: uppercase;
        }

        .field-row {
            margin-bottom: 12px;
            font-size: 15px;
            line-height: 1.4;
        }

        .line-input {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-height: 1.2em;
            min-width: 50px;
        }

        .sub-fields {
            margin-left: 25px;
            margin-top: 5px;
        }

        /* Item 1 – OP details: OP Number + Location share one row; a long location wraps to a 2nd line */
        .op1 { line-height: 1.7; }
        .op1 .ul {
            border-bottom: 1px solid #000;
            padding: 0 4px;
        }
        .op1 .ul-op {
            display: inline-block;
            width: 110px;              /* short OP Number underline */
        }
        .op1 .nowrap { white-space: nowrap; }
        .op1-sub { margin-left: 25px; margin-top: 6px; }
        .op1-sub .nowrap { margin-right: 22px; }

        .checkbox-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            width: 300px;
        }

        .check-box {
            width: 25px;
            height: 25px;
            border: 1px solid #000;
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }

        .declaration-box {
            border: 1px solid #333;
            padding: 15px;
            margin-top: 30px;
            font-size: 14px;
            line-height: 1.4;
            background-color: #f9f9f9;
        }

        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .sig-block {
            width: 45%;
            text-align: center;
            font-size: 14px;
        }
        .sig-line {
            height: 55px;
            border-bottom: 1px solid #000;
            margin-bottom: 6px;
        }

        @media print {
            .print-bar { display: none !important; }
            body { background-color: white; padding: 0; }
            /* Force background colours/tints (e.g. the pink badge) to actually print */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .form-container {
                box-shadow: none;
                border: none;
                width: 100%;
                /* Don't force a full-page height — that pushes the footer onto a 2nd sheet */
                min-height: 0;
                padding: 6mm 16mm;
            }
            /* Tighten every vertical gap so the whole form + footer fits one A4 sheet */
            .header-top { margin-bottom: 4px; }
            .ref-box { margin-top: 8px; padding: 5px; }
            .badge-heading { margin: 10px 0; }
            .field-row { margin-bottom: 6px; }
            .checkbox-row { margin-bottom: 4px; }
            .declaration-box { margin-top: 12px; padding: 10px; }
            .signature-section { margin-top: 16px; }
            .sig-line { height: 38px; }
            .form-footer { margin-top: 10px; padding-top: 8px; }
            .form-footer .footer-logo { max-height: 48px; }
            .form-container, .declaration-box { page-break-inside: avoid; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="print-bar">
        <button onclick="window.print()">&#128424; Print Ownership Form</button>
        <button class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="form-container">
        @php
            $qrPayload = trim((string) ($data['op_number'] ?? '')) ?: trim((string) ($data['file_no'] ?? ''));
            $qrPayload = $qrPayload !== '' ? ('OP-CHANGE-OF-OWNERSHIP: ' . $qrPayload) : 'OP-CHANGE-OF-OWNERSHIP';
        @endphp
        <div class="header-top">
            <div class="qr-placeholder">
                <img src="{{ qr_data_uri($qrPayload, 85) }}"
                     alt="QR Code" onerror="this.parentNode.innerHTML='QR CODE';">
            </div>
            <div class="center-logo">
                <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="Coat of Arms" onerror="this.style.display='none'">
            </div>
            <div style="width: 85px;"></div>
        </div>

        <div class="ministry-title">
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <p>No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, Kano P.M.B. 3083, Kano-Nigeria</p>
        </div>

        <div class="ref-box">
            <span>In case of reply please quote</span>
            <span>Our ref: <div class="line-input" style="width: 200px;">{{ $data['file_no'] ?? '' }}</div></span>
        </div>

        <div class="badge-heading">
            <div class="badge-text">
                <span class="badge-top">OCCUPANCY PERMIT</span>
                <span class="badge-bottom">CHANGE OF OWNERSHIP FORM</span>
            </div>
        </div>

        <div class="field-row op1">
            <span class="nowrap"><b>1.</b> OP Number <span class="ul ul-op">{{ $data['op_number'] ?? '' }}</span></span>
            &nbsp;&nbsp;
            <span class="nowrap">Location</span> <span class="ul">{{ $data['location'] ?? '' }}</span>
            <div class="op1-sub">
                <span class="nowrap">Plot No <span class="ul" style="display:inline-block; width:110px;">{{ $data['plot_no'] ?? '' }}</span></span>
                <span class="nowrap">Plan No <span class="ul" style="display:inline-block; width:140px;">{{ $data['plan_no'] ?? '' }}</span></span>
                <span class="nowrap">Date of Issuance <span class="ul" style="display:inline-block; width:120px;">{{ $data['date_of_issuance'] ?? '' }}</span></span>
            </div>
        </div>

        <div class="field-row">
            <b>2. Original Allottee</b>
            <div class="sub-fields">
                a. Name <div class="line-input" style="width: 530px;">{{ $data['original_name'] ?? '' }}</div><br>
                b. Address: <div class="line-input" style="width: 515px;">{{ $data['original_address'] ?? '' }}</div><br>
                c. Phone No. <div class="line-input" style="width: 505px;">{{ $data['original_phone'] ?? '' }}</div>
            </div>
        </div>

        <div class="field-row">
            <b>3. Current Owner</b>
            <div class="sub-fields">
                a. Name <div class="line-input" style="width: 530px;">{{ $data['current_name'] ?? '' }}</div><br>
                b. Address: <div class="line-input" style="width: 515px;">{{ $data['current_address'] ?? '' }}</div><br>
                c. Phone No. <div class="line-input" style="width: 505px;">{{ $data['current_phone'] ?? '' }}</div>
            </div>
        </div>

        <div class="field-row">
            <b>4. How ownership was obtained</b>
            <div class="sub-fields">
                @php
                    $method = trim((string) ($data['ownership_method'] ?? ''));
                    // Values must mirror the modal radio options (change-of-ownership-modal.blade.php).
                    $methods = ['a' => 'Direct', 'b' => 'Resettlement', 'c' => 'Gift', 'd' => 'Purchase', 'e' => 'Inheritance'];
                @endphp
                @foreach($methods as $letter => $label)
                    <div class="checkbox-row">{{ $letter }}. {{ $label }} <div class="check-box">{{ strcasecmp($method, $label) === 0 ? '✓' : '' }}</div></div>
                @endforeach
            </div>
        </div>

        <div class="declaration-box">
            I, the undersigned declares that all the information given above is true and correct and I also understand that if any part of the information is false, it may / shall jeopardize the validity of the application or document or any registration resulting there-from.
        </div>

        <div class="signature-section">
            <div class="sig-block">
                <div class="sig-line"></div>
                Signature of Applicant
            </div>
            <div class="sig-block">
                <div class="sig-line"></div>
                Date
            </div>
        </div>
 <br><br> <br><br> <br><br>
        {{-- Footer logos --}}
        <div class="form-footer">
            <img class="footer-logo" src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Logo" onerror="this.style.display='none'">
            <img class="footer-logo" src="http://app.klaes.ng/assets/logo/las.jpg" alt="LAS" onerror="this.style.display='none'">
        </div>
    </div>
</body>
</html>
