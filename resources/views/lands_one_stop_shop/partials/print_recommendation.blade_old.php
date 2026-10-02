<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recommendation Form</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f0f0f0;
            display: flex;
            justify-content: center;
            padding: 20px;
        }

        .form-container {
            width: 210mm;
            background: white;
            padding: 10mm 15mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border: 1px solid #ccc;
        }

        .header-logo {
            text-align: center;
            margin-bottom: 10px;
        }

        .header-logo img {
            width: 70px;
            height: auto;
        }

        .qr-block {
            position: absolute;
            top: 10mm;
            left: 15mm;
            text-align: center;
        }

        .qr-block canvas, .qr-block img {
            width: 45px;
            height: 45px;
            display: block;
        }

        .qr-block .qr-label {
            font-size: 8px;
            color: #555;
            margin-top: 2px;
            word-break: break-all;
            max-width: 60px;
        }

        .form-container {
            position: relative;
        }

        .main-heading {
            text-align: center;
            border: 2px solid #050505;
            padding: 4px;
            margin-bottom: 8px;
        }

        .main-heading h1 {
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #333;
        }
    



        .sub-heading {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            color: #555;
        }

        .conditions-section {
            font-size: 14px;
            line-height: 1.15;
        }

        .field-row {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            margin-bottom: 5px;
        }

        .field-label {
            font-weight: normal;
            white-space: nowrap;
        }

        .line-input {
            border-bottom: none;
            min-width: 100px;
            display: inline-block;
            padding: 0 5px;
            font-weight: normal;
        }

        .recommendation-block {
            margin-top: 25px;
        }

        .recommendation-title {
            font-weight: normal;
            text-transform: uppercase;
            font-size: 13px;
            margin-bottom: 4px;
            margin-top: 8px;
        }

        .sig-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
        }

        .sig-box {
            width: 45%;
        }

        .sig-line {
            border-top: 1px solid #000;
            padding-top: 5px;
            font-weight: normal;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .sig-space {
            height: 18px;
        }

        .approval-box {
            border: 1px solid #999;
            padding: 10px 15px;
            margin-top: 8px;
            display: flex;
            flex-direction: column;
            min-height: 110px;
        }

        .approval-top {
            text-align: center;
        }

        .approval-bottom {
            margin-top: auto;
            text-align: right;
            padding-top: 12px;
        }

        .approval-text {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .commissioner-sig {
            text-align: right;
            margin-top: 15px;
        }

        .footer-tag {
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #888;
            margin-top: 8px;
        }

        .footer-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
        }

        .footer-logos img {
            height: 28px;
            width: auto;
            object-fit: contain;
        }


          .oss-label{
            margin-top: 3.5mm;
            font-size: 3.2mm;
            font-weight: 700;
            letter-spacing: 1.2mm;
            color: #94a3b8;
            text-transform: uppercase;
        }

        @media print {
            body { background-color: white; padding: 0; }
            .form-container { box-shadow: none; border: none; width: 100%; padding: 8mm 12mm; }
            @page { size: A4; margin: 8mm; }
            .rofo-serial-block { top: 10mm !important; right: 12mm !important; font-size: 14px !important; }
        }
    </style>
</head>
<body>

    <div class="form-container">
        @if(!empty($record->rofo_serial_no))
        <div class="rofo-serial-block" style="position: absolute; top: 10mm; right: 15mm; font-size: 16px; font-weight: 700; letter-spacing: 0.35em; text-align: right; color: #b91c1c;">
            
            <div style="display: flex; justify-content: flex-end; margin-top: 4px; letter-spacing: normal;">
                <span style="font-size: 13px; font-weight: 900; letter-spacing: 0.1em; color: #334155; font-family: 'Courier New', monospace;">Serial No: {{ $record->rofo_serial_no }}</span>
            </div>
        </div>
        @endif
        @if(!empty($record->tracking_id))
        <div class="qr-block" id="qr-block">
            <div id="qr-code"></div>
            <div class="qr-label"></div>
        </div>
        @endif
        <div class="header-logo">
            <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" alt="Coat of Arms">
        </div>

        <div class="main-heading">
            <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            {{-- Kano State Ministry of Land and Physical Planning --}}
            <h2  style=" font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #333;" >RECOMMENDATION FOR GRANT OF STATUTORY
                <br>
                 RIGHT OF OCCUPANCY</h2> 
                 <p class="oss-label" style="color:#ea1b1b">LAND ONE STOP SHOP</p>

        </div>

        <div class="sub-heading">CONDITIONS FOR APPLICATION</div>

        <div class="conditions-section">
            <div class="field-row">
                <span class="field-label">1. Name Of Applicant:</span>
                <span class="line-input" style="flex-grow: 1;">{{ $record->applicant_name }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">2. (a) File Ref No:</span>
                <span class="line-input" style="width: auto; padding-right: 6px;">{{ $record->file_ref }}</span>
                <span class="field-label" style="margin-left: 8px;">(b) Purpose Of Clause:</span>
                <span class="line-input" style="flex-grow: 1;">{{ $record->purpose }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">(c) Location:</span>
                <span class="line-input" style="flex-grow: 1; min-width: 0; white-space: normal; word-break: break-word;">{{ $record->location }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">(d) Plot No:</span>
                <span class="line-input" style="width: auto; padding-right: 6px;">{{ $record->plot_no }}</span>
                <span class="field-label" style="margin-left: 8px;">(e) Layout Plan No.:</span>
                <span class="line-input" style="flex-grow: 1;">{{ $record->plan_no }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">3. Term:</span>
                <span class="line-input" style="width: 100px;">{{ $record->term }} years </span> 
            </div>

            <div class="field-row">
                <span class="field-label">4. Value For Proposed Development:</span>
                <span class="line-input" style="width: 250px;">{{ $record->dev_value }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">5. Time for the Completion of proposed development:</span>
                <span class="line-input" style="width: 150px;">{{ $record->completion_time }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">6. Annual Ground Rent(phpa):</span>
                <span class="line-input" style="width: 150px;">{{ $record->ground_rent }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">7. Development Charge (If any):</span>
                <span class="line-input" style="width: 250px;">{{ $record->dev_charge }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">8. Survey And Processing Charges:</span>
                <span class="line-input" style="width: 250px;">{{ $record->survey_charges }}</span>
            </div>

            <div class="field-row">
                <span class="field-label">9. The Director of Land recommends/does not recommend the application for the following reasons:</span>
            </div>
           <br>   
              <div class="line-input" style="width: 100%; height: 30px; margin-bottom: 6px;">{{ $record->director_reasons }}</div>
            <hr style="border: none; border-top: 1px solid #000; margin: 4px 0 4px 0;">
           <br>   

            <div class="sig-row">
                <div class="sig-box" style="text-align: center;">
                    <div class="sig-space"></div>
                    <div class="sig-line">Sign: {{ $record->director_sign }}Director of Land</div>
                </div>
                <div class="sig-box" style="text-align: center;">
                    <div class="sig-space"></div>
                    <div class="sig-line">Date: {{ $record->director_date }}</div>
                </div>
            </div>

            <div class="recommendation-title">10. RECOMMENDATION BY THE PERMANENT SECRETARY</div>
            <p style="margin: 0; font-size: 14px;">I recommend/do not recommend the Application for a Grant of Right of Occupancy over Plot No: <span class="" style="width: 80px;">{{ $record->ps_plot }}</span></p>
            
            <div class="sig-row">
                <div class="sig-box">
                    <div class="sig-space"></div>
                    <div class="sig-line" style="text-align: center;">Sign: Permanent Secretary</div>
                </div>
                <div class="sig-box">
                    <div class="sig-space"></div>
                    <div class="sig-line" style="text-align: center;">Date: {{ $record->ps_date }}</div>
                </div>
            </div>

            <div class="recommendation-title">11. APPROVAL BY THE HONOURABLE COMMISSIONER</div>
            <div class="approval-box">
                <div class="approval-top">
                    <div class="approval-text">The Grant of Right of Occupancy is hereby</div>
                    <div style="font-weight: bold;">
                        @if($record->approval_status === 'approved')
                            APPROVED
                        @elseif($record->approval_status === 'not_approved')
                            NOT APPROVED
                        @else
                            APPROVED / NOT APPROVED
                        @endif
                    </div>
                </div>
                <div class="approval-bottom">
                    <div style="margin-left: 70%; text-align: center;">
                        <div class="sig-space"></div>
                        <div class="sig-line">The Honourable Commissioner: {{ $record->commissioner_name }}</div>
                        <br>
                        <div class="sig-line" style="margin-top: 6px;">Date: {{ $record->commissioner_date }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-tag">Kano State Ministry of Land and Physical Planning</div>
 <br> <br> <br>
        <div class="footer-logos">
             <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo">
            <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="LAS Logo">
           
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        window.onload = function() {
            var trackingId = @json($record->tracking_id ?? '');
            if (trackingId) {
                new QRCode(document.getElementById('qr-code'), {
                    text: trackingId,
                    width: 45,
                    height: 45,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
            setTimeout(function() { window.print(); }, 600);
        };
    </script>

</body>
</html>
