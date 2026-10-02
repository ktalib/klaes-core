<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLTR - Right of Occupancy</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #525659;
            font-family: "Times New Roman", Times, serif;
            font-size: 12px;
            line-height: 1.25;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 0;
            gap: 30px;
        }

        .page {
            background: white;
            width: 210mm;
            height: 297mm;
            position: relative;
            display: flex;
            flex-direction: column;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        /* --- ORNATE BORDER FRAME --- */
        .sltr-frame {
            flex: 1;
            margin: 6mm 8mm 0 8mm;
            padding: 0 12mm 3mm 12mm;
            position: relative;
            display: flex;
            flex-direction: column;
            border: 30px solid transparent;
            border-image-source: url('http://app.klaes.ng/storage/template_frames/sltr.jpeg');
            border-image-slice: 160;
            border-image-repeat: round;
        }

        /* --- WATERMARK --- */
        .original-placeholder {
            position: absolute;
            top: 10mm;
            right: 5mm;
            left: auto;
            transform: none;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.35em;
            text-align: right;
            z-index: 2;
        }

        /* --- FOOTER --- */
        .sltr-footer {
            flex-shrink: 0;
            height: 12mm;
            margin: 0 8mm;
            padding: 1mm 15mm 3mm 15mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        /* --- PAGE 2 --- */
        .page2-content {
            flex: 1;
            margin: 10mm 20mm;
            display: flex;
            flex-direction: column;
        }

        .acceptance-box {
            border: 1.5px solid #000;
            padding: 15px;
            margin-top: 10px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .fee-table th,
        .fee-table td {
            border: 1.2px solid #000;
            padding: 5px 8px;
            font-size: 10px;
        }
        .fee-table .sub-item {
            padding-left: 25px;
        }

        .check-box {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 1.5px solid #000;
            vertical-align: middle;
            margin-right: 6px;
        }

        .underline-field {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 80px;
        }

        /* --- SECURITY MICROTEXT --- */
        .security-line-container {
            position: relative;
            width: 280px;
            height: 40px;
            margin-bottom: 1px;
            overflow: hidden;
            background: transparent;
        }
        .security-line-container span {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            font-size: 3px;
            font-weight: 900;
            letter-spacing: -0.65px;
            word-spacing: -3.2px;
            color: #000;
            white-space: nowrap;
            overflow: hidden;
            text-transform: uppercase;
            pointer-events: none;
            z-index: 2;
            font-family: 'Arial Narrow', 'Helvetica Condensed', 'Courier New', monospace;
            text-align: center;
            line-height: 1;
            text-shadow: 0 0.5px 0 #666;
        }

        /* --- NOTE BOX --- */
        .note-box {
            display: flex;
            border: 2px solid #000;
            border-radius: 4px;
            margin-top: 15px;
        }
        .note-box .note-text {
            width: 70%;
            padding: 10px;
            font-weight: bold;
            font-size: 11px;
        }
        .note-box .note-sig {
            width: 30%;
            padding: 10px;
            border-left: 2px solid #000;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        /* --- PRINT --- */
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #1b4d2e;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            z-index: 1000;
        }

        @media print {
            body {
                background: none;
                padding: 0;
                margin: 0;
                display: block;
            }
            .print-btn { display: none; }

            .page {
                box-shadow: none;
                margin: 0;
                width: 100%;
                height: 100vh;
                page-break-after: always;
            }
            .page:last-child {
                page-break-after: auto;
            }

            .sltr-frame {
                margin: 6mm 8mm 0 8mm !important;
                padding: 0 12mm 3mm 12mm !important;
                border: 30px solid transparent !important;
                border-image-source: url('http://app.klaes.ng/storage/template_frames/sltr.jpeg') !important;
                border-image-slice: 160 !important;
                border-image-repeat: round !important;
            }

            .sltr-footer {
                height: 12mm !important;
                margin: 0 8mm !important;
                padding: 1mm 15mm 3mm 15mm !important;
            }

            .original-placeholder {
                top: 10mm !important;
                right: 5mm !important;
                font-size: 16px !important;
                letter-spacing: 0.35em !important;
            }

            @page {
                size: A4;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <button class="print-btn" onclick="window.print()">Print Document</button>

    @php
        // Dummy data for template preview
        $applicantName = $applicantName ?? 'MUSA YAKUBU';
        $applicantAddress = $applicantAddress ?? 'No. 15, Zaria Road, Kano';
        $sltrNo = $sltrNo ?? 'SLTR-RES-2026-00145';
        $location = $location ?? 'NASSARAWA GRA';
        $landUse = $landUse ?? 'RESIDENTIAL';
        $dateOfIssue = $dateOfIssue ?? '2026-03-26';
        $applicationDate = $applicationDate ?? '18th February 26';
        $plotNo = $plotNo ?? '234';
        $situatedAt = $situatedAt ?? 'NASSARAWA GRA, KANO';
        $lga = $lga ?? 'KANO MUNICIPAL';
        $groundRent = $groundRent ?? '5,000.00';
        $processingFee = $processingFee ?? '10,000.00';
        $term = $term ?? '99';
        $purpose = $purpose ?? 'RESIDENTIAL';

        $watermark = $watermark ?? 'ORIGINAL';
        $watermarkColors = [
            'ORIGINAL' => '#ff0000',
            'DUPLICATE' => '#0000ff',
            'TRIPLICATE' => '#008000',
        ];
        $watermarkColor = $watermarkColors[$watermark] ?? '#ff0000';

        $securityAlpha = 'W';
        $securityDigitsTop = '17';
        $securityDigitsEnd = '384595';

        $qrUrl = qr_data_uri($sltrNo, 200);
    @endphp

    <!-- ==================== PAGE 1 ==================== -->
    <div class="page">
        <div class="sltr-frame">

            <!-- Watermark + Security Code -->
            <div class="original-placeholder" style="color: {{ $watermarkColor }};">
                {{ $watermark }}
                <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                    <div style="display: inline-flex; align-items: center; gap: 4px; letter-spacing: normal;">
                        <span style="line-height: 1; color: #334155; display: inline-flex; flex-direction: column; align-items: center; font-weight: 900; font-family: Arial, sans-serif;">
                            <span style="border-bottom: 1.5px solid #334155; padding-bottom: 1px; font-size: 8px;">{{ $securityAlpha }}</span>
                            <span style="padding-top: 1px; font-size: 8px;">{{ $securityDigitsTop }}</span>
                        </span>
                        <span style="font-size: 13px; font-weight: 900; letter-spacing: 0.1em; color: #334155; font-family: 'Courier New', monospace;">{{ $securityDigitsEnd }}</span>
                    </div>
                </div>
            </div>

            <!-- Coat of Arms -->
            <div style="text-align: center; margin-bottom: 4px; margin-top: 10px;">
                <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="Nigerian Coat of Arms" style="width: 100px; height: auto;">
            </div>

            <!-- Header Banner -->
            <div style="text-align: center; margin-bottom: 8px;">
                <div style="display: inline-block; border: 2px solid #000; border-radius: 8px; padding: 3px; background: #fff;">
                    <div style="background: #1b4d2e; padding: 8px 16px; border-radius: 5px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                        <p style="font-weight: bold; color: #fff; text-transform: uppercase; font-size: 15px; margin: 0; letter-spacing: 0.5px;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                        <p style="font-size: 10px; color: #fff; margin: 3px 0 0 0; letter-spacing: 0.3px;">No. 2 Dr Bala Mohammed Road, Kano State, Nigeria</p>
                    </div>
                </div>
            </div>

            <!-- Meta Grid: Address + SLTR Details -->
            <div style="display: grid; grid-template-columns: 1.2fr 1fr; border: 2px solid #000; margin-bottom: 8px;">
                <!-- Left: To + Address -->
                <div style="padding: 8px; border-right: 2px solid #000; font-weight: bold;">
                    <div style="margin-bottom: 4px;">
                        <label>TO: <span style="text-transform: uppercase;">{{ $applicantName }}</span></label>
                        <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>
                    </div>
                    <div style="margin-bottom: 4px;">
                        <span style="text-transform: uppercase;">{{ $applicantAddress }}</span>
                        <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>
                    </div>
                    <div>
                        <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>
                    </div>
                </div>
                <!-- Right: SLTR Details -->
                <div style="padding: 8px; font-weight: bold;">
                    <div style="margin-bottom: 4px;">
                        <label>SLTR NO: <span>{{ $sltrNo }}</span></label>
                    </div>
                    <div style="margin-bottom: 4px;">
                        <label>LOCATION: <span>{{ $location }}</span></label>
                    </div>
                    <div style="margin-bottom: 4px;">
                        <label>LAND USE: <span>{{ $landUse }}</span></label>
                    </div>
                    <div>
                        <label>DATE OF ISSUE: <span>{{ $dateOfIssue }}</span></label>
                    </div>
                </div>
            </div>

            <!-- SLTR Title -->
            <div style="text-align: center; margin-bottom: 6px;">
                <h2 style="color: #8b2d2d; font-size: 20px; font-weight: bold; margin: 0; -webkit-print-color-adjust: exact; print-color-adjust: exact;">SLTR</h2>
                <h3 style="color: #8b2d2d; font-size: 14px; font-weight: bold; text-decoration: underline; -webkit-print-color-adjust: exact; print-color-adjust: exact;">Terms of Offer of Grant/Conveyance of Approval</h3>
            </div>

            <!-- Body Text -->
            <p style="font-size: 11px; text-align: justify; line-height: 1.4; margin-bottom: 6px; font-weight: bold;">
                With reference to your application dated <span style="text-decoration: underline;">{{ $applicationDate }}</span>,
                I am directed to inform you that the Governor of Kano State has approved the grant of Right of Occupancy to you over piece of land/plot No.
                <span style="text-decoration: underline;">{{ $plotNo }}</span> situated at
                <span style="text-decoration: underline;">{{ $situatedAt }}</span> in
                <span style="text-decoration: underline;">{{ $lga }}</span> on the following conditions.
            </p>

            <!-- Conditions -->
            <ol style="padding-left: 25px; font-size: 11px; line-height: 1.2; font-weight: bold; flex-grow: 1;">
                <li style="margin-bottom: 6px;">Payment of:
                    <ul style="list-style: none; padding-left: 15px; margin-top: 3px;">
                        <li>(a) Ground rent ₦ <span style="text-decoration: underline;">{{ $groundRent }}</span> P.h.p.a. (Revisable every 5 years)</li>
                        <li>(b) Processing Fee of ₦ <span style="text-decoration: underline;">{{ $processingFee }}</span></li>
                    </ul>
                </li>
                <li style="margin-bottom: 6px;">
                    (a) Term <span style="text-decoration: underline;">{{ $term }}</span> Years.<br>
                    (b) Purpose <span style="text-decoration: underline;">{{ $purpose }}</span>
                </li>
                <li style="margin-bottom: 6px;">Not to alienate the Right of Occupancy in part or whole without the written consent of the Governor.</li>
                <li style="margin-bottom: 6px;">To be responsible for the development and maintenance of the drainage, landscaping and general beautification of the frontage of the subject property.</li>
                <li style="margin-bottom: 6px;">Not to erect or permit to be erected on the subject land any building or development except in accordance with plans and specifications approved by the State Planning Authority in the case of urban areas or this ministry in the case of rural areas.</li>
                <li style="margin-bottom: 6px;">Compliance with the Statutory Bills and Charges under Systematic Land Titling and Registration (SLTR) Law.</li>
                <li style="margin-bottom: 6px;">This letter of Grant must be returned within three months from the above date, duly completed with the required fees; otherwise, the offer lapses.</li>
            </ol>

            <!-- Signature Block -->
            <div style="margin-top: auto; display: flex; justify-content: space-between; padding: 0 30px 10px 30px; text-align: center; font-weight: bold; align-items: flex-end;">
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <div class="security-line-container">
                        <span>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</span>
                    </div>
                    <div style="font-size: 12px;">HONOURABLE COMMISSIONER</div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <div class="security-line-container">
                        <span>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</span>
                    </div>
                    <div style="margin-top: 5px; font-size: 12px;">DATE</div>
                </div>
            </div>

        </div>

        <!-- Footer: Barcode + QR -->
        <div class="sltr-footer">
            <div style="display: flex; align-items: flex-end; gap: 10px; visibility: visible; opacity: 0.0001;">
                <div style="font-size: 7px; transform: rotate(-90deg);">©NSPM</div>
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text={{ urlencode($sltrNo) }}&scale=1" style="height: 32px; margin-bottom: -10px;" />
                    <span style="font-size: 8px;">{{ $sltrNo }}</span>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; align-items: center;">
                <img src="{{ $qrUrl }}" alt="QR" style="width: 50px; height: 50px; background: #fff; padding: 2px;" />
            </div>
        </div>
    </div>

    <!-- ==================== PAGE 2 ==================== -->
    <div class="page">
        <div class="page2-content">
            <!-- Applicant Address (right-aligned) -->
            <div style="text-align: right; margin-bottom: 25px; font-size: 12px;">
                <div style="border-bottom: 1px solid #000; width: 250px; display: inline-block; margin-bottom: 8px;"></div><br>
                <div style="border-bottom: 1px solid #000; width: 250px; display: inline-block; margin-bottom: 8px;"></div><br>
                <div style="border-bottom: 1px solid #000; width: 250px; display: inline-block; margin-bottom: 8px;"></div><br>
                <p>Date <span style="border-bottom: 1px solid #000; display: inline-block; width: 150px;"></span></p>
            </div>

            <!-- Addressee -->
            <div style="margin-bottom: 20px; font-size: 12px;">
                <p>The Honourable Commissioner</p>
                <p>Ministry of Land and Physical Planning</p>
                <p>Kano State.</p>
            </div>

            <!-- Acceptance Box -->
            <div class="acceptance-box">
                <h2 style="text-align: center; text-decoration: underline; font-weight: bold; font-size: 14px; margin-bottom: 10px;">ACCEPTANCE LETTER</h2>

                <p style="font-size: 11px; text-align: justify; line-height: 1.4; margin-bottom: 8px;">
                    With reference to the Letter of Grant, I hereby accept the terms and conditions of the grant of the Right of Occupancy as conveyed to me by your overleaf quoted letter.
                </p>
                <p style="font-size: 11px; text-align: justify; line-height: 1.4; margin-bottom: 10px;">
                    I will submit my building plans to you for approval before I commence any improvement on the Site, and on completion of the improvement, I will get your completion Certificate before occupation on the building. I forward herewith.
                </p>

                <!-- Fee Table -->
                <table class="fee-table">
                    <thead>
                        <tr>
                            <th width="60%" style="text-align: left;">Land Use</th>
                            <th style="text-align: left;">Processing Fees</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>a. Residential</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="sub-item">i. Systematic</td>
                            <td>- ₦ 5,000.00</td>
                        </tr>
                        <tr>
                            <td class="sub-item">ii. On demand</td>
                            <td>- ₦ 10,000.00</td>
                        </tr>
                        <tr>
                            <td>b. Commercial</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td class="sub-item">i. Systematic</td>
                            <td>- ₦ 10,000.00</td>
                        </tr>
                        <tr>
                            <td class="sub-item">ii. On Demand Within Metro</td>
                            <td>- ₦ 50,000.00</td>
                        </tr>
                        <tr>
                            <td class="sub-item">iii. On Demand Outside Metro</td>
                            <td>- ₦ 40,000.00</td>
                        </tr>
                        <tr>
                            <td>c. Warehouse</td>
                            <td>- ₦ 100,000.00</td>
                        </tr>
                        <tr>
                            <td>d. Above one (1) Hectare</td>
                            <td>- ₦ 100,000.00</td>
                        </tr>
                        <tr>
                            <td>e. Farmland</td>
                            <td>- ₦ 50,000.00</td>
                        </tr>
                        <tr>
                            <td style="text-align: right;">One year Ground Rent</td>
                            <td>₦ <span style="border-bottom: 1px solid #000; display: inline-block; width: 100px;"></span></td>
                        </tr>
                        <tr>
                            <td style="text-align: right; font-weight: bold;">TOTAL</td>
                            <td>₦ <span style="border-bottom: 1px solid #000; display: inline-block; width: 100px;"></span></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Surveyor Options -->
                <div style="margin-top: 15px;">
                    <p style="margin-bottom: 6px; font-size: 11px;">
                        <span class="check-box"></span>
                        I require the Director Survey to carry out the land survey for me
                    </p>
                    <p style="margin-bottom: 6px; font-size: 11px;">
                        <span class="check-box"></span>
                        I require a licensed Surveyor to carry out the land survey for me
                    </p>
                </div>
            </div>

            <!-- Note Box -->
            <div class="note-box">
                <div class="note-text">
                    <p>*NOTE:</p>
                    <p>APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER SIGNING HIS/HER ACCEPTANCE OF THE GRANT.</p>
                    <p style="margin-top: 6px;">THIS R OF O IS SUBJECT TO VERIFICATION BEFORE ANY STATUTORY PAYMENTS TO REVENUE DEPARTMENT.</p>
                </div>
                <div class="note-sig">
                    <div>
                        <div style="border-bottom: 2px solid #000; margin-bottom: 4px;"></div>
                        <span style="font-weight: bold; font-size: 11px; display: block; text-align: center;">APPLICANT'S SIGNATURE</span>
                    </div>
                    <div style="margin-top: 10px; display: flex; align-items: center;">
                        <span style="font-weight: bold; font-size: 11px; margin-right: 4px;">DATE:</span>
                        <div style="border-bottom: 2px solid #000; flex-grow: 1;"></div>
                    </div>
                </div>
            </div>

            <!-- Footer Tag -->
            <p style="text-align: center; font-size: 8px; font-style: italic; color: #444; margin-top: 15px;">
                Generated by: Systematic Land Titling Registration Department (SLTR)
            </p>
        </div>
    </div>
</body>
</html>
