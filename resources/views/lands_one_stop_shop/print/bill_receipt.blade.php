<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OSS Bill Receipt - {{ $bill->ref_id ?? $bill->ID }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            @page { size: A4; margin: 1cm; }
            .no-print { display: none !important; }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.5;
            color: #333;
            background: #fff;
        }
        .receipt {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            position: relative;
        }

        /* ── Watermark ── */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            font-weight: bold;
            opacity: 0.06;
            color: #166534;
            z-index: -1;
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 15px;
        }

        /* ── Header ── */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            padding-bottom: 10px;
            border-bottom: 3px solid #166534;
        }
        .header .logo {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
        }
        .header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .header .title {
            text-align: center;
            flex: 1;
            padding: 0 15px;
        }
        .header .title h1 {
            font-size: 14px;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .header .title h2 {
            font-size: 13px;
            font-weight: bold;
            color: #166534;
            margin-bottom: 2px;
        }
        .header .title h3 {
            font-size: 16px;
            font-weight: bold;
            color: #dc2626;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 4px;
        }

        /* ── Meta row (date + ref) ── */
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin: 12px 0 15px;
            font-size: 12px;
        }
        .meta-row .ref-id {
            font-weight: bold;
            color: #166534;
        }

        /* ── Details table ── */
        .details-grid {
            margin-bottom: 20px;
            padding: 2px 0 15px;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            table-layout: fixed;
        }
        .details-table td {
            padding: 5px 8px;
            vertical-align: top;
        }
        .details-table td.label {
            font-weight: bold;
            color: #555;
            white-space: nowrap;
            text-align: left;
        }
        .details-table td.value {
            color: #111;
            font-weight: bold;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* ── Fee table ── */
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 12px;
        }
        .fee-table th {
            background-color: #166534;
            color: #fff;
            padding: 10px 12px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .fee-table th.amount-col {
            text-align: right;
        }
        .fee-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .fee-table td.amount {
            text-align: right;
            font-family: 'Courier New', monospace;
        }
        .fee-table tr.total-row {
            background-color: #f0fdf4;
            font-weight: bold;
        }
        .fee-table tr.total-row td {
            border-top: 2px solid #166534;
            border-bottom: 2px solid #166534;
            font-size: 13px;
        }

        /* ── Amount in words ── */
        .amount-words {
            font-size: 12px;
            font-style: italic;
            margin-bottom: 15px;
            padding: 8px 12px;
            background: #f9fafb;
            border-left: 3px solid #166534;
        }

        /* ── Status badge ── */
        .status-badge {
            display: inline-block;
            padding: 3px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-paid {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }
        .status-unpaid {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        /* ── Note ── */
        .note-section {
            font-size: 11px;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        /* ── Signatures ── */
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        .signature-box {
            width: 200px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-bottom: 4px;
        }
        .signature-label {
            font-size: 10px;
            color: #555;
        }

        /* ── Footer ── */
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 2px solid #166534;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer .footer-logo {
            width: 50px;
            height: 50px;
        }
        .footer .footer-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .footer .footer-text {
            text-align: center;
            flex: 1;
            font-size: 9px;
            color: #888;
            padding: 0 10px;
        }

        /* ── Print button ── */
        .print-bar {
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .print-bar button {
            padding: 8px 24px;
            background: #166534;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            margin: 0 6px;
        }
        .print-bar button:hover {
            background: #14532d;
        }
        .print-bar button.secondary {
            background: #64748b;
        }
        .print-bar button.secondary:hover {
            background: #475569;
        }
    </style>
</head>
<body>
    <!-- Print controls -->
    <div class="print-bar no-print">
        <button onclick="window.print()">
            <span>&#128424;</span> Print Receipt
        </button>
        <button class="secondary" onclick="window.close()">Close</button>
    </div>

    <div class="receipt">
        <!-- Watermark -->
        {{-- <div class="watermark">{{ ($bill->Payment_Status ?? '') === 'paid' ? 'PAID' : 'UNPAID' }}</div> --}}

        <!-- ── Header: Kano State Logo (left) | Title | Ministry Logo (right) ── -->
        <div class="header">
            <div class="logo">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo" onerror="this.style.display='none'">
            </div>
            <div class="title">
                <h1>Kano State Ministry of Land and Physical Planning</h1>
                <h2>No. 2 Dr Bala Muhammad Road, Kano</h2>
                <h3>Land One Stop Shop</h3>
            </div>
            <div class="logo">
                <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo" onerror="this.style.display='none'">
            </div>
        </div>

        <!-- ── Meta row ── -->
        <div class="meta-row">
            <div>
                <strong>Date:</strong> {{ now()->format('F j, Y') }}
            </div>
            <div>
                <strong>Bill Reference ID:</strong>
                <span class="ref-id">{{ $bill->ref_id ?? ('OSS-' . $bill->ID) }}</span>
            </div>
        </div>

        <!-- ── Status ── -->
        {{-- <div style="margin-bottom: 15px;">
            <span class="status-badge {{ ($bill->Payment_Status ?? '') === '' ? 'status-paid' : 'status-unpaid' }}">
               -
            </span>
        </div> --}}

        <!-- ── Application Details ── -->
        @php
            $applicantName = $fileNumberRow->FileName
                ?? $application->applicant_name
                ?? $application->party_2_name
                ?? $capture->party_2_name
                ?? $capture->party_1_name
                ?? '—';

            $fileNo = $fileNumberRow->mlsfNo
                ?? $application->file_no
                ?? $application->mls_file_no
                ?? $application->temp_fileno
                ?? $capture->mlsFNo
                ?? $capture->fileno
                ?? $capture->temp_fileno
                ?? '—';

            $location = $fileNumberRow->location
                ?? $fileNumberRow->source_property_description
                ?? $application->location
                ?? $application->property_description
                ?? $application->address
                ?? $capture->property_description
                ?? $capture->property_location
                ?? '—';

            $landUse = $fileNumberRow->mls_land_use
                ?? $fileNumberRow->source_land_use
                ?? $application->land_use
                ?? $capture->land_use
                ?? '—';

            $landUse = strtoupper(trim((string) $landUse));
            $landUse = match ($landUse) {
                'RES' => 'RESIDENTIAL',
                'COM' => 'COMMERCIAL',
                'IND' => 'INDUSTRIAL',
                'AGR' => 'AGRICULTURAL',
                default => ($landUse !== '' ? $landUse : '—'),
            };
        @endphp
        <div class="details-grid">
            <table class="details-table">
                <colgroup>
                    <col style="width: 13%;">
                    <col style="width: 19%;">
                    <col style="width: 3%;">
                    <col style="width: 16%;">
                    <col style="width: 49%;">
                </colgroup>
                <tr>
                    <td class="label">Applicant Name:</td>
                    <td class="value">{{ $applicantName }}</td>
                    <td class="spacer"></td>
                    <td class="label">Location:</td>
                    <td class="value">{{ $location }}</td>
                </tr>
                <tr>
                    <td class="label">File No:</td>
                    <td class="value">{{ $fileNo }}</td>
                    <td class="spacer"></td>
                    <td class="label">Land Use:</td>
                    <td class="value" style="text-transform: capitalize;">{{ $landUse }}</td>
                </tr>
                <tr>
                    <td class="label"></td>
                    <td class="value"></td>
                    <td class="spacer"></td>
                    <td class="label">Date Generated:</td>
                    <td class="value">{{ optional($bill->created_at)->format('M d, Y') ?? '—' }}</td>
                </tr>
            </table>
        </div>

        <!-- ── Fee Breakdown Table ── -->
        @php
            $changeOfName   = $bill->Change_of_Name ?? 0;
            $processingFee  = $bill->Processing_Fee ?? 0;
            $surveyFee      = $bill->survey_fee ?? 0;
            $applicationForm = $bill->Application_Form ?? 0;
            $total          = $changeOfName + $processingFee + $surveyFee + $applicationForm;
        @endphp
        <table class="fee-table">
            <thead>
                <tr>
                    <th style="width: 10%;">#</th>
                    <th>Description</th>
                    <th class="amount-col" style="width: 30%;">Amount (&#8358;)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td>Change of Name</td>
                    <td class="amount">&#8358;{{ number_format($changeOfName, 2) }}</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>Processing Fee</td>
                    <td class="amount">&#8358;{{ number_format($processingFee, 2) }}</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>Survey Deposit</td>
                    <td class="amount">&#8358;{{ number_format($surveyFee, 2) }}</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>Application Form</td>
                    <td class="amount">&#8358;{{ number_format($applicationForm, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td></td>
                    <td>Total</td>
                    <td class="amount">&#8358;{{ number_format($total, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- ── Amount in words ── -->
        @php
            // NumberFormatter removed due to missing class.
            $amountWords = '—';
        @endphp
        {{-- <div class="amount-words">
            <strong></strong> {{ $amountWords }} Naira Only (&#8358;{{ number_format($total, 2) }})
        </div> --}}

        <!-- ── Note ── -->
        <div class="note-section">
            {{-- <p>
                You are hereby advised to settle this bill promptly in order to accelerate the processing of your application.
                Payments can be made at the Lands One Stop Shop and all designated banks. Ensure that you obtain a duly
                acknowledged Revenue Receipt issued at the Ministry Office after concluding payment of the billed amount.
            </p> --}}
        </div>

        <!-- ── Signatures ── -->
        <div class="signatures">
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Date &amp; Signature of Revenue Officer</div>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div class="signature-label">Date &amp; Signature of Applicant</div>
            </div>
        </div>
<br><br><br><br><br><br><br><br>
        <!-- ── Footer with branding logos ── -->
        <div class="footer">
            <div class="footer-logo">
                <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Left Footer Logo" onerror="this.style.display='none'">
            </div>
            <div class="footer-text">
                Kano State Ministry of Land and Physical Planning &bull; Lands One Stop Shop<br>
                No. 2 Dr Bala Muhammad Road, Kano &bull; {{ now()->format('Y') }}
            </div>
            <div class="footer-logo">
                <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="Right Footer Logo" onerror="this.style.display='none'">
            </div>
        </div>
    </div>
</body>
</html>
