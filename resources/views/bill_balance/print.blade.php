<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kano C of O Rent – Complete with To: data</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        /* ——— your original styles ——— */
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            margin: 0;
        }

        .print-btn {
            margin-bottom: 10px;
            padding: 8px 20px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .form-container {
            background-color: white;
            width: 210mm;
            height: 297mm;
            padding: 10mm 15mm;
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: 1px solid #ccc;
            overflow: hidden;
            position: relative;
            isolation: isolate;
        }

        .form-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('{{ asset('http://app.klaes.ng/assets/images/logos/ministry-logo-left.jpg') }}');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 350px auto;
            opacity: 0.1;
            pointer-events: none;
            z-index: 1;
        }

        .content-overlay {
            position: relative;
            z-index: 5;
            height: 100%;
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        .header-section {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 5px;
        }

        .logo-address-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .coat-of-arms {
            width: 65px;
            height: auto;
        }

        .address-text {
            font-size: 13px;
            line-height: 1.2;
            font-weight: bold;
        }

        .qr-container {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }

        .qr-placeholder {
            width: 80px;
            height: 80px;
            border: 1px solid #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
        }

        .main-title {
            text-align: center;
            font-family: 'Georgia', serif;
            font-size: 26px;
            color: #8B0000;
            margin: 10px 0;
        }

        .field-line {
            display: inline-block;
            border-bottom: 1px dotted #000;
            min-width: 40px;
            height: 1.1em;
            vertical-align: bottom;
        }

        .content-body {
            font-size: 14px;
            line-height: 1.6;
        }

        .fees-section {
            margin: 10px 0;
            width: 100%;
        }

        .fee-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 4px;
        }

        .fee-label { width: 210px; }
        .fee-dots { flex-grow: 1; border-bottom: 1px dotted #000; margin: 0 8px; }

        .sig-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        @media print {
            .print-btn { display: none; }
            body { background-color: white; padding: 0; }
            .form-container { box-shadow: none; border: none; width: 210mm; height: 297mm; padding: 10mm 15mm; }
            @page { size: A4; margin: 0; }
            .form-container::before {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* ——— layout adjustments ——— */
        /* Data row with label left and value pushed to far right */
        .data-row {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            margin: 6px 0;
            width: 100%;
        }
        .data-label {
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .data-row .field-line {
            flex: 1;
            max-width: none;
            margin-left: 20px;
            text-align: right;
            min-width: 300px;
        }
        .data-row .field-line .inline-value {
            display: inline-block;
            padding-right: 5px;
            background: transparent;
        }

        /* total centering - shifted right */
        .total-row-center {
            display: flex;
            justify-content: center;
            align-items: baseline;
            margin: 10px 0 5px;
            border-top: 1px solid #000;
            padding-top: 8px;
            padding-left: 150px; /* This shifts the entire TOTAL row to the right */
        }
        .total-row-center .total-label {
            font-weight: bold;
            margin-right: 12px;
        }
        .total-row-center .total-value {
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        
        /* Naira symbol alignment - updated to ₦ */
        .naira-fixed {
            display: inline-block;
            width: 25px;
            text-align: right;
            margin-right: 2px;
            font-weight: normal;
            font-family: 'Times New Roman', Times, serif;
            vertical-align: baseline;
        }
        
        .inline-value {
            display: inline;
            background: transparent;
        }

        /* Ensure naira symbol aligns with bottom of dotted line */
        .fee-row {
            align-items: baseline;
        }
        
        .fee-row .naira-fixed {
            align-self: baseline;
            line-height: 1.6;
        }
        
        /* Rent From/To row styling */
        .rent-period-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 4px;
        }
        .rent-period-label {
            width: 210px;
            white-space: nowrap;
        }
        .rent-period-field {
            margin-left: 0;
        }
        .rent-period-text {
            margin: 0 10px;
            white-space: nowrap;
        }

        /* Less block styling for proper alignment */
        .less-block {
            margin: 5px 0;
        }
        .less-line {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            margin: 3px 0;
        }
        .less-line .field-line {
            margin: 0 5px;
        }
        .less-line .naira-fixed {
            width: auto;
            margin-left: 0;
            margin-right: 2px;
        }

        /* Signature section fixed alignment */
        .sig-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .sig-left {
            text-align: center;
            width: 180px;
        }
        .sig-right {
            text-align: center;
            width: 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .for-commissioner-row {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 5px;
            margin-bottom: 2px;
            height: 1.1em;
        }
        .for-commissioner-row b {
            line-height: 1.1;
            display: inline-block;
        }
        .for-commissioner-row .field-line {
            vertical-align: baseline;
            margin-top: 0;
        }
        .honourable-commissioner {
            line-height: 1.2;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Document</button>

    <div class="form-container">
        <div class="content-overlay">
            <div class="header-section">
                <div class="logo-address-wrapper">
                    <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="kano state m" class="coat-of-arms">
                    <div class="address-text">
                        Ref No. {{ $billBalance->file_number ?? '' }}<br>
                        Ministry of Land and Physical Planning<br>
                        P.M.B. 3083, Kano
                    </div>
                </div>
            </div>

            <div class="qr-container">
                <canvas id="qr-code" width="80" height="80" style="width: 80px; height: 80px;"></canvas>
            </div>

            <div style="margin-top: -30px;">
                To: <span class="field-line" style="width: 250px;" id="toNameContainer"><span class="inline-value" id="toName">{{ ucwords(strtolower($billBalance->applicant_name ?? '')) }}</span></span><br>
                <span class="field-line" style="width: 275px;" id="toAddress1Container"><span class="inline-value" id="toAddress1">{{ ucwords(strtolower($billBalance->applicant_address ?? '')) }}</span></span><br>
                <span class="field-line" style="width: 275px;" id="toAddress2Container"><span class="inline-value" id="toAddress2"></span></span><br>
                <p style="margin: 5px 0;">Sir/Gentleman/Madam</p>
            </div>

            <div class="main-title">Certificate Of Occupancy Rent</div>

            <div class="content-body">
                <p style="margin: 5px 0;">I have the honour to inform you that the following certificate of Occupancy has been issued in your name:</p>

                <div class="data-row">
                    <span class="data-label">Number:</span>
                    <span class="field-line" id="numContainer"><span class="inline-value" id="displayNumber">{{ $billBalance->file_number ?? '' }}</span></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Date of Issue:</span>
                    <span class="field-line" id="issueContainer"><span class="inline-value" id="displayIssue">{{ optional($billBalance->prepared_at)->format('d F, Y') ?? '' }}</span></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Date of Expiry:</span>
                    <span class="field-line" id="expiryContainer"><span class="inline-value" id="displayExpiry">{{ optional($billBalance->date_of_expiry)->format('d F, Y') ?? '' }}</span></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Rent Per Annum:</span>
                    <span class="field-line" id="rentContainer"><span class="inline-value" id="displayRentPerAnnum">{{ $billBalance->amount ? '₦' . number_format($billBalance->amount, 2) : '' }}</span></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Location Station:</span>
                    <span class="field-line" id="locContainer"><span class="inline-value" id="displayLocation">{{ ucwords(strtolower($billBalance->location_station ?? '')) }}</span></span>
                </div>
                <div class="data-row">
                    <span class="data-label">District:</span>
                    <span class="field-line" id="districtContainer"><span class="inline-value" id="displayDistrict">{{ ucwords(strtolower($billBalance->district ?? '')) }}</span></span>
                </div>

                <p style="margin: 10px 0 5px 0;">The rent and fees payable are made as follows:</p>

                @php
                    $rentPerAnnum = (float) ($billBalance->amount ?? 0);
                    $registrationFee = (float) ($billing->Site_Plan_Fee ?? 0);
                    $surveyFee = (float) ($billing->survey_fee ?? 0);
                    $preparationFee = (float) ($billing->Processing_Fee ?? 0);
                    $compensationFee = (float) ($billing->Betterment_Charges ?? 0);
                    $developmentCharge = (float) ($billing->Land_Use_Charge ?? 0);
                    $fromYear = $billBalance->rent_from_year;
                    $toYear = $billBalance->rent_to_year;
                    $termYears = ($fromYear && $toYear) ? abs((int) $toYear - (int) $fromYear) : 0;
                    $cumulativeAnnual = $rentPerAnnum * $termYears;
                    $totalFees = $cumulativeAnnual + $registrationFee + $surveyFee + $preparationFee + $compensationFee + $developmentCharge;
                    $amountDeposited = (float) ($billing->Penalty_Fees ?? 0);
                    $balanceDue = $totalFees - $amountDeposited;
                @endphp

                <div class="fees-section">
                    <div class="rent-period-row">
                        <span class="rent-period-label">Rent From:</span>
                        <span class="field-line" style="width: 600px;" id="rentFromContainer"><span class="inline-value" id="rentFrom">{{ $fromYear ?: '' }}</span></span>
                        <span class="rent-period-text">To</span>
                        <span class="field-line" style="width: 800px;" id="rentToContainer"><span class="inline-value" id="rentTo">{{ $toYear ?: '' }}</span></span>
                    </div>
                    
                    
                    <div class="fee-row"><span class="naira-fixed">₦</span> <span class="field-line" style="width: 246px;" id="rentNContainer"><span class="inline-value" id="rentN">{{ number_format($rentPerAnnum, 2) }}</span></span> <span style="margin-left: 20px;">Per Annum  </span> <span class="naira-fixed">₦</span>   <span class="field-line" style="width: 180px;" id="cumulativeAnnualContainer"><span class="inline-value" id="cumulativeAnnualN">{{ number_format($cumulativeAnnual, 2) }}</span></span></div>

                    <!-- Registration  -->
                    <div class="fee-row">
                        <div class="fee-label">Registration fees</div>
                        <span class="field-line" style="width: 200px;" id="regDescContainer"><span class="inline-value" id="regFeesDesc"></span></span>
                        <span class="naira-fixed">₦</span>
                        <span class="field-line" style="width: 360px;" id="regNContainer"><span class="inline-value" id="regFeesN">{{ number_format($registrationFee, 2) }}</span></span>
                    </div>

                    <div class="fee-row">
                        <div class="fee-label">Survey Fees</div>
                        <span class="field-line" style="width: 200px;" id="surveyDescContainer"><span class="inline-value" id="surveyDesc"></span></span>
                        <span class="naira-fixed">₦</span>
                        <span class="field-line" style="width: 360px;" id="surveyNContainer"><span class="inline-value" id="surveyN">{{ number_format($surveyFee, 2) }}</span></span>
                    </div>

                    <div class="fee-row">
                        <div class="fee-label">Preparation Fees</div>
                        <span class="field-line" style="width: 200px;" id="prepDescContainer"><span class="inline-value" id="prepDesc"></span></span>
                        <span class="naira-fixed">₦</span>
                        <span class="field-line" style="width: 360px;" id="prepNContainer"><span class="inline-value" id="prepN">{{ number_format($preparationFee, 2) }}</span></span>
                    </div>

                    <div class="fee-row">
                        <div class="fee-label">Compensation Fees</div>
                        <span class="field-line" style="width: 200px;" id="compDescContainer"><span class="inline-value" id="compDesc"></span></span>
                        <span class="naira-fixed">₦</span>
                        <span class="field-line" style="width: 360px;" id="compNContainer"><span class="inline-value" id="compN">{{ number_format($compensationFee, 2) }}</span></span>
                    </div>

                    <div class="fee-row">
                        <div class="fee-label">Development Charges</div>
                        <span class="field-line" style="width: 200px;" id="devDescContainer"><span class="inline-value" id="devDesc"></span></span>
                        <span class="naira-fixed">₦</span>
                        <span class="field-line" style="width: 360px;" id="devNContainer"><span class="inline-value" id="devN">{{ number_format($developmentCharge, 2) }}</span></span>
                    </div>

                    <!-- TOTAL row:  -->
                    <div class="total-row-center">
                        <span class="total-label">TOTAL</span>
                        <div class="total-value">
                            <span class="naira-fixed" style="width: auto; margin-right: 2px;">₦</span>
                            <span class="field-line" style="width: 150px;" id="totalContainer"><span class="inline-value" id="totalN">{{ number_format($totalFees, 2) }}</span></span>
                        </div>
                    </div>
                </div>

                <!-- Less block  -->
                <div class="less-block">
                    <p style="margin: 5px 0;">Less</p>
                    <div style="display: flex; align-items: baseline; margin: 3px 0;">
                        Amount Deposited on Kano CRC No 
                        <span class="field-line" style="width: 457px;" id="crcNoContainer"><span class="inline-value" id="crcNo">{{ $billing->bill_balance_reciept ?? '' }}</span></span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin: 3px 0;">
                        Of 
                        <span class="field-line" style="width: 250px;" id="crcDateContainer"><span class="inline-value" id="crcDate">{{ isset($billing->bill_balance_date) && $billing->bill_balance_date ? \Carbon\Carbon::parse($billing->bill_balance_date)->format('d F, Y') : '' }}</span></span>
                        <span class="naira-fixed" style="width: auto; margin-left: 5px; margin-right: 2px;">₦</span>
                        <span class="field-line" style="width: 378px;" id="depositNContainer"><span class="inline-value" id="depositN">{{ number_format($amountDeposited, 2) }}</span></span>
                    </div>
                    <div style="display: flex; align-items: baseline; margin: 3px 0;">
                        Balance due to Government/Applicant 
                        <span class="naira-fixed" style="width: auto; margin-left: 5px; margin-right: 2px;">₦</span>
                        <span class="field-line" style="width: 430px;" id="balanceContainer"><span class="inline-value" id="balanceN">{{ number_format($balanceDue, 2) }}</span></span>
                    </div>
                </div>

                <p style="margin: 5px 0;">2. Will you please remit this sum direct to this office not later than a month from the date of this letter</p>
                <p style="margin: 5px 0;">3. I would take this opportunity of reminding you that further annual rent are payable.</p>
            </div>

            <!-- italic note -->
            <div style="font-style: italic; font-size: 12px; text-align: center; margin-top: 15px;">
                (A) In advance on the 1st January &nbsp; (B) without demand &nbsp; (C) Direct to the Account Department of this ministry
            </div>

            <!-- signature section -->
            <div class="sig-section">
                <div class="sig-left">
                    <div class="field-line" style="width: 100%;"></div><br>
                    <b>DEEDS REGISTER</b>
                </div>
                <div class="sig-right">
                    <div class="for-commissioner-row">
                        <b>FOR</b>
                        <div class="field-line" style="width: 220px;"></div>
                    </div>
                    <div class="honourable-commissioner">
                        <b>HONOURABLE COMMISSIONER</b>
                    </div>
                </div>
            </div>

            <!-- footer logos -->
            <div class="footer-logos">
                <img src="{{ asset('storage/upload/logo/logo.png') }}" alt="KLAES Logo" class="footer-logo">
                <img src="{{ asset('assets/logo/las.jpg') }}" alt="LAS Logo" class="footer-logo">
            </div>
        </div> <!-- end content-overlay -->
    </div> <!-- end form-container -->

    <style>
        .naira-fixed {
            display: inline-block;
            width: 25px;
            text-align: right;
            margin-right: 2px;
            font-weight: normal;
            font-family: 'Times New Roman', Times, serif;
            vertical-align: baseline;
        }
        .total-row-center .total-value .field-line {
            width: 160px;
        }
        .field-line span {
            background: transparent;
        }
        .data-row .field-line {
            text-align: right;
        }
        
        .fee-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 4px;
        }
        /* Rent period row styling */
        .rent-period-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 8px;
        }
        .rent-period-label {
            width: 210px;
            white-space: nowrap;
            font-weight: 600;
        }
        .rent-period-text {
            margin: 0 15px;
            white-space: nowrap;
            font-weight: bold;
        }
        /* Less block alignment */
        .less-block div {
            display: flex;
            align-items: baseline;
            margin: 3px 0;
        }
        .less-block .field-line {
            margin: 0 3px;
        }
        
        .sig-section {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .sig-left {
            text-align: center;
            width: 180px;
        }
        .sig-right {
            text-align: center;
            width: 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .for-commissioner-row {
            display: flex;
            align-items: baseline;
            justify-content: center;
            gap: 5px;
            margin-bottom: 2px;
            height: 1.1em;
        }
        .for-commissioner-row b {
            line-height: 1.1;
            display: inline-block;
            font-size: 14px;
        }
        .for-commissioner-row .field-line {
            vertical-align: baseline;
            margin-top: 0;
            height: 1.1em;
        }
        .honourable-commissioner {
            line-height: 1.2;
            margin-top: 0px;
        }
        .honourable-commissioner b {
            font-size: 14px;
        }
        .footer-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 10px;
        }
        .footer-logo {
            height: 35px;
            width: auto;
            object-fit: contain;
        }
    </style>
    <script>
        @php
            $tracking_id = \App\Models\FileIndexing::where('file_number', $billBalance->file_number ?? '')
                ->first()
                ?->tracking_id ?? 'N/A';
        @endphp

        document.addEventListener('DOMContentLoaded', function() {
            var canvas = document.getElementById('qr-code');
            if (canvas && typeof QRious !== 'undefined') {
                new QRious({
                    element: canvas,
                    value: @json($tracking_id),
                    size: 80,
                    level: 'H'
                });
            }
        });
    </script>
</body>
</html>