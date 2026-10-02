<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Occupancy Rent - Kano State (with watermark)</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        /* Tightened overall body padding to prevent overflow */
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
            height: 297mm; /* Fixed height for A4 */
            padding: 10mm 15mm; /* Reduced padding to fit content */
            box-sizing: border-box;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: 1px solid #ccc;
            overflow: hidden; /* Prevent content from spilling */
            /* --- Watermark --- */
            position: relative; /* Needed for absolute positioning of pseudo-element */
            isolation: isolate; /* Creates a new stacking context, helps control z-index if needed, but we use z-index on pseudo */
        }

        /* Watermark as a background image using pseudo-element */
        .form-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('{{ asset('images/ministry-logo-left.jpg') }}');
            background-repeat: no-repeat;
            background-position: center;
            background-size: 350px auto; /* Adjust size as desired; auto keeps aspect ratio */
            opacity: 0.1; /* Faint effect so text remains readable */
            pointer-events: none; /* Allows clicking through to form elements */
            z-index: 1; /* Places it above the background color but below content? We'll adjust content z-index if needed */
        }

        /* Ensure all content sits above the watermark */
        .header-section, .qr-container, .main-title, .content-body, .sig-section, .fees-section, div {
            position: relative; /* Helps with stacking, but not strictly necessary if we set z-index on content wrapper.
                                    Instead, we can set a higher z-index on a wrapper or on all content elements.
                                    Simplest: set a very high z-index on the main container's children that need to be above.
                                    But we already set pointer-events: none on watermark, so it's mostly visual.
                                    To be safe, let's wrap all content in a div with relative positioning and z-index. */
            z-index: 2; 
        }

        /* But the above z-index rule applies to many elements, we can be more specific:
           Wrap everything except the pseudo-element in a content wrapper with higher z-index.
           Let's restructure slightly: move all content inside a new div with class "content-overlay" 
           that sits above the watermark. This is cleaner. */
        .content-overlay {
            position: relative;
            z-index: 5;
            height: 100%;
            width: 100%;
            /* No extra background, just ensure it stacks above */
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

        .main-title {
            text-align: center;
            font-family: 'Georgia', serif; 
            font-size: 26px; /* Slightly smaller to save space */
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
        .no-underline {
            border-bottom: none !important;
            padding-bottom: 0;
            height: auto;
        }
        /* Less section layout */
        .less-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: nowrap;
        }
        .less-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1 1 auto;
            white-space: nowrap;
            overflow: hidden;
        }
        .less-right {
            width: 140px;
            text-align: right;
            flex: 0 0 140px;
        }
        .field-inline {
            display: inline-block;
            vertical-align: bottom;
            min-width: 140px;
        }

        .content-body {
            font-size: 14px;
            line-height: 1.6; /* Reduced line height */
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
        
        /* Adjusted signature section to stay on page */
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
            /* Ensure watermark prints */
            .form-container::before { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }
    </style>
</head>
<body>

    <button class="print-btn" onclick="window.print()">Print Document</button>

    <div class="form-container">
        <!-- Content overlay to ensure text stays above watermark -->
        <div class="content-overlay">
            <div class="header-section">
                <div class="logo-address-wrapper">
                    <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Kano State Ministry Logo" class="coat-of-arms">
                    <div class="address-text">
                        Ref No. {{ $billBalance->file_number?? '' }}<br>
                        Ministry of Land and Physical Planning<br>
                        P.M.B. 3083, Kano
                    </div>
                </div>
            </div>

            <div class="qr-container">
                <canvas id="qr-code" width="80" height="80" style="width: 80px; height: 80px;"></canvas>
            </div>

            <div style="margin-top: -30px;">
                To: <span class="field-line" style="width: 250px;">{{ ucwords(strtolower($billBalance->applicant_name ?? '')) }}</span><br>
                <span class="field-line" style="width: 275px;">{{ ucwords(strtolower($billBalance->applicant_address ?? '')) }}</span><br>
                <span class="field-line" style="width: 275px;"></span><br>
                <p style="margin: 5px 0;">Sir/Gentleman/Madam</p>
            </div>

            <div class="main-title">Certificate Of Occupancy Rent</div>

            <div class="content-body">
                <p style="margin: 5px 0;">I have the honour to inform you that the following certificate of Occupancy has been issued in your name:</p>
                Number: <span class="field-line" style="width: 600px;">{{ $billBalance->file_number ?? '' }}</span><br>
                Date of Issue: <span class="field-line" style="width: 560px;">{{ optional($billBalance->prepared_at)->format('d F, Y') ?? '' }}</span><br>
                Date of Expiry: <span class="field-line" style="width: 550px;"></span><br>
                Rent Per Annum: <span class="field-line" style="width: 550px;">{{ $billBalance->amount ? '₦' . number_format($billBalance->amount, 2) : '' }}</span><br>
                Location Station: <span class="field-line" style="width: 540px;">{{ ucwords(strtolower($billBalance->location_station ?? '')) }}</span><br>
                District: <span class="field-line" style="width: 580px;">{{ ucwords(strtolower($billBalance->district ?? '')) }}</span>
                
                <p style="margin: 10px 0 5px 0;">The rent and fees payable are made as follows:</p>
                
                @php
                    $rentPerAnnum = (float) ($billBalance->amount ?? 0);
                    $registrationFee = (float) ($billing->Site_Plan_Fee ?? 0);
                    $surveyFee = (float) ($billing->survey_fee ?? 0);
                    $preparationFee = (float) ($billing->Processing_Fee ?? 0);
                    $compensationFee = (float) ($billing->Betterment_Charges ?? 0);
                    $developmentCharge = (float) ($billing->Land_Use_Charge ?? 0);
                    $totalFees = $rentPerAnnum + $registrationFee + $surveyFee + $preparationFee + $compensationFee + $developmentCharge;
                    $amountDeposited = (float) ($billing->Penalty_Fees ?? 0);
                    $balanceDue = $totalFees - $amountDeposited;
                @endphp
                <div class="fees-section">
                    <div class="fee-row">Rent From: <span class="field-line" style="width: 610px;">{{ optional($billBalance->prepared_at)->format('d F, Y') ?? '' }}</span></div>
                    <div class="fee-row"><div style="width: 140px;"><span class="field-line" style="width: 100%;">₦{{ number_format($rentPerAnnum, 2) }}</span></div><div style="width: 80px; text-align: center;">Per Annum</div><div class="fee-dots"></div><div style="width: 120px; text-align: right;"><span class="field-line" style="width: 100%;">₦{{ number_format($rentPerAnnum, 2) }}</span></div></div>
                    
                    <div class="fee-row"><div class="fee-label">Registration fees</div><div class="field-line" style="width: 200px;"></div><span class="field-line" style="width: 600px;">₦{{ number_format($registrationFee, 2) }}</span></div>
                    <div class="fee-row"><div class="fee-label">Survey Fees</div><div class="field-line" style="width: 200px;"></div><span class="field-line" style="width: 600px;">₦{{ number_format($surveyFee, 2) }}</span></div>
                    <div class="fee-row"><div class="fee-label">Preparation Fees</div><div class="field-line" style="width: 200px;"></div><span class="field-line" style="width: 600px;">₦{{ number_format($preparationFee, 2) }}</span></div>
                    <div class="fee-row"><div class="fee-label">Compensation Fees</div><div class="field-line" style="width: 200px;"></div><span class="field-line" style="width: 600px;">₦{{ number_format($compensationFee, 2) }}</span></div>
                    <div class="fee-row"><div class="fee-label">Development Charges</div><div class="field-line" style="width: 200px;"></div><span class="field-line" style="width: 600px;">₦{{ number_format($developmentCharge, 2) }}</span></div>
                    
                    <div class="fee-row" style="font-weight: bold; border-top: 1px solid #000; padding-top: 3px; margin-top: 5px;">
                        <div class="fee-label">TOTAL</div><div class="fee-dots" style="border:none;"></div><span class="field-line" style="width: 110px;">₦{{ number_format($totalFees, 2) }}</span>
                    </div>
                </div>

                <p style="margin: 5px 0;">Less</p>
                <p style="margin: 2px 0;">Amount Deposited on Kano CRC No <span style="border-bottom: 1px dotted #000; padding: 0 4px;">{{ $billing->bill_balance_reciept ?? '' }}</span> <span style="float: right;">₦{{ number_format($amountDeposited, 2) }}</span></p>
                <p style="margin: 2px 0;">Of <span style="border-bottom: 1px dotted #000; padding: 0 4px;">{{ isset($billing->bill_balance_date) && $billing->bill_balance_date ? \Carbon\Carbon::parse($billing->bill_balance_date)->format('d F, Y') : '' }}</span></p>
                <p style="margin: 2px 0;">Balance due to Government/Applicant <span style="float: right;">₦{{ number_format($balanceDue, 2) }}</span></p>

                <p style="margin: 5px 0;">2. Will you please remit this sum direct to this office not later than a month from the date of this letter</p>
                <p style="margin: 5px 0;">3. I would take this opportunity of reminding you that further annual rent are payable.</p>
            </div>

            <div style="font-style: italic; font-size: 12px; text-align: center; margin-top: 15px;">
                (A) In advance on the 1st January &nbsp; (B) without demand &nbsp; (C) Direct to the Account Department of this ministry
            </div>

            <div class="sig-section">
                <div style="text-align: center; width: 180px;">
                    <div class="field-line" style="width: 100%;"></div><br>
                    <b>DEEDS REGISTER</b>
                </div>
                <div style="text-align: center; width: 220px;">
                    <b>FOR</b><br>
                    <div class="field-line" style="width: 100%;"></div><br>
                    <b style="white-space: nowrap;">HONOURABLE COMMISSIONER</b>
                </div>
            </div>
        </div> <!-- end content-overlay -->
    </div> <!-- end form-container -->

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