<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change of Purpose Sheet – {{ $cert->cert_number }}</title>
    <style>
        /* A4 Page Setup */
        @page {
            size: A4;
            margin: 0;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Container matching A4 dimensions at standard 96 DPI */
        .a4-page {
            width: 210mm;
            height: 297mm;
            margin: 20px auto;
            background-color: #ffffff;
            box-sizing: border-box;
            padding: 30mm 20mm 20mm 20mm; /* Standard document margins */
            position: relative;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
        }

        /* Header Section styling */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 50px;
        }

        .logo-box {
            width: 90px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-box img {
            max-width: 90px;
            max-height: 90px;
            object-fit: contain;
        }

        .gov-title-container {
            text-align: center;
            flex-grow: 1;
            padding: 0 10px;
        }

        .gov-title-container h1 {
            font-size: 22px;
            font-weight: bold;
            margin: 0 0 6px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .gov-title-container h2 {
            font-size: 15px;
            font-weight: normal;
            margin: 0 0 3px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* The gap down to the sheet title belongs to whichever line is last,
           so it stays correct whether or not the department line is present.
           :last-of-type rather than :has(), which print renderers do not all
           support. */
        .gov-title-container h2:last-of-type {
            margin-bottom: 25px;
        }

        /* The department sits under the Ministry. Smaller, so it reads as
           subordinate to it rather than competing with it. */
        .gov-title-container h2.dept-line {
            font-size: 12.5px;
            color: #333;
        }

        .gov-title-container h3 {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0;
            letter-spacing: 0.5px;
        }

        .cert-no {
            text-align: center;
            font-size: 12px;
            color: #444;
            margin-bottom: 30px;
        }

        .cert-no strong {
            color: #1a1a1a;
        }

        /* Main Declaration Paragraph */
        .declaration-text {
            font-size: 15px;
            line-height: 1.8;
            margin-bottom: 40px;
            text-align: justify;
        }

        /* Metadata Details Section */
        .details-section {
            margin-bottom: 60px;
            flex-grow: 1;
        }

        .detail-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 24px;
        }

        .detail-label {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            width: 250px;
            flex-shrink: 0;
            line-height: 1.2;
        }

        .detail-value-line {
            flex-grow: 1;
            border-bottom: 1px dotted #444;
            min-height: 20px;
            margin-left: 10px;
            font-size: 14px;
            font-weight: bold;
            padding-bottom: 2px;
        }

        /* Signatures Layout */
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 60px;
            padding: 0 10px;
        }

        .sig-block {
            width: 260px;
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #000;
            margin-bottom: 8px;
        }

        .sig-title {
            font-size: 13px;
            font-weight: bold;
        }

        /* Footer Section */
        .footer-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 11px;
            font-style: italic;
            color: #666;
        }

        .system-tag {
            font-family: "Courier New", Courier, monospace;
        }

        .footer-logo img {
            max-width: 90px;
            max-height: 45px;
            object-fit: contain;
        }

        /* On-screen toolbar (hidden when printing) */
        .toolbar {
            text-align: center;
            padding: 12px;
        }

        .toolbar button {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            color: #fff;
        }

        .toolbar .btn-print { background: rgb(186,191,12); }
        .toolbar .btn-close { background: #6b7280; }

        /* Optimization for actual browser printing */
        @media print {
            body {
                background-color: #fff;
            }
            .a4-page {
                margin: 0;
                box-shadow: none;
                width: 210mm;
                height: 297mm;
            }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button class="btn-print" onclick="window.print()">Print Sheet</button>
    <button class="btn-close" onclick="window.close()">Close</button>
</div>

<div class="a4-page">

    <div class="header-section">
        <div class="logo-box">
            <img src="http://app.klaes.ng/assets/logo/ministry2.png" alt="Ministry Logo">
        </div>

        <div class="gov-title-container">
            <h1>Kano State Government</h1>
            <h2>Ministry of Land &amp; Physical Planning</h2>
            {{-- The issuing department. A Change of Purpose Sheet is a document
                 the holder keeps, so it has to say which department issued it,
                 not only which Ministry. --}}
            <h2 class="dept-line">Department of Special Assignment</h2>
            <h3>Change of Purpose Sheet</h3>
        </div>

        <div class="logo-box">
            <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Coat of Arms">
        </div>
    </div>

    <p class="cert-no">Sheet No: <strong>{{ $cert->cert_number }}</strong></p>

    <p class="declaration-text">
        The Application for change of purpose has been approved for the conversion of Land use from
        <strong>{{ $cert->from_use ?? '—' }}</strong> to <strong>{{ $cert->to_use ?? '—' }}</strong> with the details below;
    </p>

    <div class="details-section">
        <div class="detail-row">
            <span class="detail-label">New File No:</span>
            <div class="detail-value-line">{{ $cert->new_file_number ?: ($cert->file_number ?? '') }}</div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Old File No:</span>
            <div class="detail-value-line">{{ $cert->file_number ?? '' }}</div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Approved Land Use<br>(Current):</span>
            <div class="detail-value-line">{{ $cert->to_use ?? '' }}</div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Approved Land Use (Old):</span>
            <div class="detail-value-line">{{ $cert->from_use ?? '' }}</div>
        </div>

        <div class="detail-row">
            <span class="detail-label">File Title:</span>
            <div class="detail-value-line">{{ $cert->holder_name ?? '' }}</div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Date of Issue:</span>
            <div class="detail-value-line">{{ $cert->issue_date?->format('d F Y') ?? '' }}</div>
        </div>
    </div>

    <div class="signature-section">
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-title">Permanent Secretary</div>
        </div>

        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-title">Honorable Commissioner</div>
        </div>
    </div>

    <div class="footer-section">
        <div class="system-tag">Generated by KLAIS &middot; {{ now()->format('d/m/Y H:i') }}</div>
        <div class="footer-logo">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Logo">
        </div>
    </div>

</div>

</body>
</html>
