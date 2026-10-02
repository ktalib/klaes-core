<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Occupancy Permit Acknowledgment - Kano State</title>
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

        .print-btn {
            margin-bottom: 20px;
            padding: 10px 25px;
            background-color: #2e7d32;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .print-btn:hover {
            background-color: #256d28;
        }

        .form-container {
            background-color: white;
            width: 210mm;
            min-height: 297mm;
            padding: 15mm 20mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        /* Header Section */
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .qr-placeholder {
            width: 70px;
            height: 70px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #666;
        }

        .qr-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .center-logo {
            text-align: center;
            flex-grow: 1;
        }

        .center-logo img {
            width: 80px;
        }

        .form-id {
            font-size: 14px;
            font-weight: bold;
            color: #555;
        }

        .ministry-title {
            text-align: center;
            margin-top: 10px;
        }

        .ministry-title h1 {
            color: #2e7d32;
            font-size: 24px;
            margin: 0;
            text-transform: uppercase;
        }

        .ministry-title p {
            font-size: 11px;
            margin: 2px 0;
            color: #333;
        }

        /* Badge Heading */
        .badge-heading {
            text-align: center;
            margin: 20px 0;
        }

        .badge-text {
            background-color: #e57373;
            color: white;
            padding: 5px 20px;
            font-weight: bold;
            border: 1px solid #c62828;
            display: inline-block;
            text-transform: uppercase;
        }

        .one-stop-shop {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #555;
            margin-bottom: 25px;
        }

        /* Body Content */
        .date-time-block {
            text-align: right;
            margin-bottom: 30px;
        }

        .date-time-row {
            white-space: nowrap;
            margin-bottom: 4px;
        }

        .date-time-label {
            display: inline-block;
            width: 48px;
            text-align: left;
        }

        .line-input {
            display: inline-block;
            border-bottom: 1px solid #000;
            padding: 0 4px;
            font-weight: bold;
            width: 160px;
            text-align: left;
        }

        .content-text {
            font-size: 16px;
            line-height: 2.2;
            margin-bottom: 40px;
        }

        .full-width-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 70%;
        }

        /* Signatures */
        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 50px;
        }

        .sig-box {
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
        }

        .sig-label {
            font-size: 14px;
        }

        /* Disclaimer Box */
        .disclaimer-box {
            border: 1px solid #333;
            border-radius: 10px;
            padding: 15px;
            margin-top: 60px;
        }

        .disclaimer-title {
            text-align: center;
            font-weight: bold;
            font-size: 18px;
            color: #d32f2f;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .disclaimer-text {
            font-size: 14px;
            line-height: 1.4;
        }

        @media print {
            .print-btn { display: none; }
            body { background-color: white; padding: 0; }
            .form-container { box-shadow: none; border: none; width: 100%; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Acknowledgment Form</button>

    <div class="form-container">
        <div class="header-top">
            <div class="qr-placeholder">
                <img src="{{ qr_data_uri('OP Ack | ' . ($record->applicant_name ?? '') . ' | Plot: ' . ($record->plot_no ?? '') . ' | Plan: ' . ($record->plan_no ?? '') . ' | ' . ($record->date_captured ?? ''), 100) }}" alt="QR">
            </div>
            <div class="center-logo">
                <img src="{{ asset('images/coat_of_arms.png') }}" alt="Coat of Arms"
                     onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg'">
            </div>
            <div class="form-id">FORM LN-03C</div>
        </div>

        <div class="ministry-title">
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <p>No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, Kano P.M.B. 3083, Kano-Nigeria</p>
        </div>

        <div class="badge-heading">
            <div class="badge-text">OCCUPANCY PERMIT (OP) ACKNOWLEDGMENT FORM</div>
        </div>

        <div class="one-stop-shop">ONE STOP SHOP</div>

        <div class="date-time-block">
            <div class="date-time-row"><span class="date-time-label">Date:</span><span class="line-input">{{ $record->date_captured ?? '' }}</span></div>
            <div class="date-time-row"><span class="date-time-label">Time:</span><span class="line-input">{{ $record->time_captured ?? '' }}</span></div>
        </div>

        <div class="content-text">
            This is to acknowledge the receipt of the Original OP for <span class="line-input">
                {{ $record->applicant_name ?? '' }}</span><br>
            in respect of Plot No. <span class="line-input">{{ $record->plot_no ?? '' }}</span> on Plan No. <span class="line-input">{{ $record->plan_no ?? '' }}</span><br>
            located at <span class="line-input">{{ $record->location ?? '' }}</span>
            <p>This acknowledgement is to confirm the receipt of your original OP. The documents will be subjected to verification to ascertain its authenticity.</p>
        </div>

        <div class="signature-grid">
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Name of the Applicant</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Signature</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Chairman One Stop Shop</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"></div>
                <div class="sig-label">Signature</div>
            </div>
        </div>

        <div class="disclaimer-box">
            <div class="disclaimer-title">DISCLAIMER</div>
            <div class="disclaimer-text">
                This acknowledgement does not in anyway validates the authenticity of the documents described above. All documents are subject to further verification for Authenticity. <br><br>
                This acknowledgement <b>must</b> be presented at the time of collection of the Letter of Grant.
            </div>
        </div>
    </div>

</body>
</html>
