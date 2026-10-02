<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kano State - ROFO - {{ $recommendation->file_number }}</title>
    <style>
        :root {
            --gov-green: #006b3f;
        }

        /* Base layout */
        body {
            background-color: #d3d3d3;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            margin: 0;
            font-family: "Times New Roman", Times, serif;
        }

        .ctc-watermark-bg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 50pt;
            color: rgba(255, 0, 0, 0.05);
            font-weight: bold;
            z-index: 0;
            white-space: nowrap;
            pointer-events: none;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .page-container {
            background-color: #fff;
            width: 210mm;
            height: 297mm;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            margin-bottom: 20px;
            box-sizing: border-box;
        }

        .security-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.04;
            pointer-events: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="20" viewBox="0 0 100 20"><path d="M0 10 Q 25 0 50 10 T 100 10" fill="none" stroke="black" stroke-width="0.5"/></svg>');
            background-size: 60px 12px;
            z-index: 0;
        }

        .content-wrapper {
            flex: 1;
            margin: 8mm;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        /* ORNATE BORDER - ONLY FOR PAGE 1 */
        .ornate-border {
            border: 45px solid transparent;
            border-image-source: url("https://media.istockphoto.com/id/1003557140/vector/ornate-certificate-border.jpg?s=612x612&w=0&k=20&c=h2TB8OQko38_5Hi15OIs-Di6y9zz37FjAJxDZcCB9fs=");
            border-image-slice: 160;
            border-image-repeat: round;
            border-image-width: 45px;
            margin: 8mm 8mm 2mm 8mm;
        }

        /* SIMPLE MARGIN - FOR PAGE 2 */
        .simple-margin {
            margin: 20mm;
        }

        .inner-content {
            padding: 15px 30px;
            flex: 1;
            box-sizing: border-box;
            font-size: 12.5px;
            line-height: 1.35;
            display: flex;
            flex-direction: column;
        }

        .header-main {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .logo {
            width: 75px;
            height: 75px;
            margin-right: 15px;
        }
        .header-center {
            text-align: center;
            flex: 1;
        }
        .state-name {
            font-size: 25px;
            font-weight: bold;
            margin: 0;
        }
        .ministry-title {
            color: var(--gov-green);
            font-size: 15px;
            margin: 2px 0;
        }

        .ref-grid {
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 15px;
            margin: 10px 0;
        }
        .underline {
            border-bottom: 1px dotted #000;
            display: inline-block;
            min-width: 45px;
            flex-grow: 1;
            margin-left: 5px;
            padding: 0 5px;
        }
        .row {
            display: flex;
            margin-bottom: 3px;
            font-weight: bold;
            align-items: center;
        }
        .title-center {
            text-align: center;
            color: #c90202 !important;
            font-size: 15px;
            font-weight: bold;
            text-decoration: underline;
            margin: 15px 0;
        }

        .signature-block {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            padding: 0 40px;
            text-align: center;
            font-weight: bold;
        }

        .footer-barcode-area {
            height: 30mm;
            padding: 0 20mm 10mm 20mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            z-index: 2;
        }

        .qr-code-group {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .qr-img {
            width: 65px;
            height: 65px;
        }

        .bordered-section {
            border: 2px solid #000;
            padding: 10px 12px;
            margin-top: 5px;
            background-color: #fff;
            height: 100%;
            box-sizing: border-box;
        }

        .conditions-list-fixed p {
            margin: 4px 0;
            text-align: justify;
            line-height: 1.4;
        }
        .condition-item {
            margin-bottom: 8px;
        }
        .sub-item {
            margin-left: 20px;
            margin-top: 2px;
        }
        .sub-item-line {
            display: flex;
            align-items: baseline;
            margin-bottom: 2px;
        }
        .sub-item-label {
            min-width: 20px;
            margin-right: 5px;
        }

        @media print {
            @page {
                size: A4;
                margin: 0 !important;
            }
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .page-container {
                box-shadow: none !important;
                margin: 0 !important;
                border: none !important;
                height: 100vh !important;
                page-break-after: always !important;
            }
            .page-container:last-of-type {
                page-break-after: auto !important;
            }
            .no-print {
                display: none !important;
            }
        }

        .print-btn-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #000;
            color: #fff;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
        }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" class="print-btn-float no-print">
        Click to Print ROFO
    </button>

    @php
        $requestedStatus = request('status', 'Original');
        $isCTCBatch = request('isCTC') == 1;
        $printVersions = ($requestedStatus === 'Batch') ? ['Original', 'Duplicate', 'Triplicate'] : [$requestedStatus];
        
        $versionColors = [
            'Original' => '#ff0000',
            'Duplicate' => '#0000ff',
            'Triplicate' => '#008000',
            'CTC' => '#ff0000'
        ];
    @endphp

    @foreach($printVersions as $index => $version)
    <div class="page-container" id="page1-{{ $index }}" style="{{ $index > 0 ? 'page-break-before: always;' : '' }}">
        <div class="security-bg"></div>
        
        @if($isCTCBatch)
            <div class="ctc-watermark-bg">CERTIFIED TRUE COPY</div>
        @endif

        <div class="content-wrapper ornate-border">
            <div class="inner-content">
                <div style="min-height: 20px"></div>
                <div class="header-main">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" class="logo" />
                    <div class="header-center">
                        <p class="state-name">KANO STATE GOVERNMENT</p>
                        <p class="ministry-title">MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                        <p style="margin: 0; font-size: 15px; font-weight: bold">
                            No. 2 Dr. Bala Mohd Road, P.M.B. 3083, Kano State
                        </p>
                    </div>
                    <div style="text-align: right; font-weight: bold; font-size: 12px">
                        <span style="color: {{ $versionColors[$version] ?? '#ff0000' }}; text-transform: uppercase;">
                            {{ $version }}
                        </span><br />
                        LAND 14
                    </div>
                </div>

                <div class="ref-grid">
                    <div>
                        <div class="bordered-section">
                            <div class="row">To:<span class="underline">{{ $recommendation->applicant_name }}</span></div>
                            <br />
                            <div class="row"><span class="underline" style="margin-left: 20px"></span></div>
                            <br />
                            <div class="row"><span class="underline" style="margin-left: 20px"></span></div>
                        </div>
                    </div>
                    <div>
                        <div class="bordered-section">
                            <div class="row">R of O No:<span class="underline" style="min-width: 100px">{{ $recommendation->file_number }}</span></div>
                            <div class="row">PLOT/PLAN No:<span class="underline" style="min-width: 100px">{{ $recommendation->plot_number }} / {{ $recommendation->layout_plan_no }}</span></div>
                            <div class="row">LOCATION:<span class="underline" style="min-width: 120px">{{ $recommendation->location }}</span></div>
                            <div class="row">DATE OF ISSUE:<span class="underline" style="min-width: 100px">{{ $recommendation->rofo_generated_at ? $recommendation->rofo_generated_at->format('Y-m-d') : now()->format('Y-m-d') }}</span></div>
                        </div>
                    </div>
                </div>

                <div class="title-center">TERMS OF OFFER OF GRANT/CONVEYANCE OF APPROVAL</div>

                <div class="conditions-list-fixed">
                    <p class="condition-item">
                        With reference to your application dated
                        <span class="underline" style="min-width: 80px">{{ $recommendation->created_at->format('jS F') }}</span> 20
                        <span class="underline" style="min-width: 30px">{{ $recommendation->created_at->format('y') }}</span>, I am
                        directed to inform you that the Governor of Kano State has
                        approved the grant of a Right of Occupancy to you over piece of
                        land/plot No <span class="underline" style="min-width: 100px">{{ $recommendation->plot_number }}</span> situated
                        at <span class="underline" style="min-width: 150px">{{ $recommendation->location }}</span> on the
                        following conditions:
                    </p>

                    <div class="condition-item">
                        <strong>1. Payment of:</strong>
                        <div class="sub-item">
                            <div class="sub-item-line">
                                <span class="sub-item-label">(a)</span> Ground Rent N <span class="underline" style="min-width: 80px">{{ number_format($recommendation->ground_rent, 2) }}</span> P.H.P.A.
                            </div>
                            <div class="sub-item-line">
                                <span class="sub-item-label">(b)</span> Development Charges N <span class="underline" style="min-width: 80px">{{ is_numeric($recommendation->development_charge) ? number_format($recommendation->development_charge, 2) : ($recommendation->development_charge ?: '0.00') }}</span>
                            </div>
                            <div class="sub-item-line">
                                <span class="sub-item-label">(c)</span> Survey/Processing fees N <span class="underline" style="min-width: 80px">{{ number_format($recommendation->preparation_fees, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="condition-item">
                        <strong>2.</strong>
                        <div class="sub-item">
                            <div class="sub-item-line">
                                <span class="sub-item-label">(a)</span> Term: <span class="underline" style="min-width: 40px">{{ $recommendation->term }}</span> years.
                            </div>
                            <div class="sub-item-line">
                                <span class="sub-item-label">(b)</span> Purpose: <span class="underline" style="min-width: 120px">{{ $recommendation->purpose_of_clause }}</span>
                            </div>
                            <div class="sub-item-line">
                                <span class="sub-item-label">(c)</span> Improvement Value: N <span class="underline" style="min-width: 80px">{{ number_format($recommendation->development_value, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="condition-item"><strong>3.</strong> Not to alienate the Right of Occupation in part or whole without written consent of the Governor.</p>
                    <p class="condition-item"><strong>4.</strong> Responsible for development/maintenance of drainage, landscaping and frontage beautification.</p>
                    <p class="condition-item"><strong>5.</strong> No building/development except in accordance with plans approved by the Planning Authority.</p>
                    <p class="condition-item">
                        <strong>6.</strong> To complete development of the land within <span class="underline" style="min-width: 30px">{{ $recommendation->development_period }}</span> years.
                    </p>
                    <p class="condition-item"><strong>7.</strong> For Petrol Stations, 33 1/2 percent of annual rental is payable to the Government.</p>
                    <p class="condition-item"><strong>8.</strong> This offer lapses if not returned with fees within three months from the above date.</p>
                </div>

                <div class="signature-block">
                    <div><br />_______________________<br />HONOURABLE COMMISSIONER</div>
                    <div><br />_______________________<br />DATE</div>
                </div>
            </div>
        </div>

        <div class="footer-barcode-area">
            <div></div>
            <div class="qr-code-group">
                <img src="{{ qr_data_uri($recommendation->tracking_id ?: $recommendation->file_number, 150) }}" class="qr-img" alt="QR Code" />
                <span style="font-size: 8px; font-weight: bold; margin-top: 2px">VERIFY DOCUMENT</span>
            </div>
        </div>
    </div>

    <!-- Page 2 (Acceptance Letter) -->
    <div class="page-container" id="page2-{{ $index }}" style="page-break-before: always;">
        <div class="security-bg"></div>

        @if($isCTCBatch)
            <div class="ctc-watermark-bg">CERTIFIED TRUE COPY</div>
        @endif

        <div class="content-wrapper simple-margin">
            <div class="inner-content">
                <div style="display: flex; border: 1px solid #000; margin-bottom: 15px; min-height: 180px;">
                    <div style="padding: 10px; width: 50%; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: flex-end;">
                        <div style="text-align: center">
                            The Honourable Commissioner<br />
                            Ministry of Land and Physical Planning
                        </div>
                    </div>
                    <div style="padding: 10px; width: 50%">
                        <div style="height: 40px; border-bottom: 1px dotted #000"></div>
                        <div style="height: 40px; border-bottom: 1px dotted #000"></div>
                        <div style="height: 40px; border-bottom: 1px dotted #000"></div>
                        <br />
                        Date: ___________________________________<br />
                        <br />
                        <center>Applicant's Address</center>
                    </div>
                </div>

                <h3 style="text-align: center; text-decoration: underline; margin: 10px 0;">ACCEPTANCE LETTER</h3>
                <p>With reference to the Offer of Grant, I hereby accept the terms and conditions of the grant of the Right of Occupancy as conveyed to me by your overleaf quoted letter.</p>
                <p>I will submit my building plan to you for approval before I commence any improvement on the Site, and on completion of the improvement, I will get your completion Certificate before occupation of the building. I forwarded herewith:</p>
                
                @php
                    $categories = [
                        ['label' => 'Agriculture', 'fee' => 10000],
                        ['label' => 'Residential', 'fee' => 20000, 'extra' => '+'],
                        ['label' => 'i. Very High Density', 'fee' => 20000, 'extra' => '+', 'is_sub' => true],
                        ['label' => 'ii. High Density', 'fee' => 20000, 'extra' => '+', 'is_sub' => true],
                        ['label' => 'iii. Medium Density', 'fee' => 20000, 'extra' => '+', 'is_sub' => true],
                        ['label' => 'iv. Low Density', 'fee' => 25000, 'extra' => '+', 'is_sub' => true],
                        ['label' => 'Commercial', 'fee' => 10000],
                        ['label' => 'Industrial', 'fee' => 10000],
                    ];
                    
                    // Use rofo_land_use_category if set, otherwise fall back to land_use
                    $selectedCategory = $recommendation->rofo_land_use_category ?? $recommendation->land_use;
                    
                    $totalSurvey = 0;
                    $totalDev = 0;
                @endphp

                <table style="width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px;">
                    <thead>
                        <tr>
                            <th style="border: 1px solid #000; padding: 4px; text-align: left; width: 40%;">Land Use</th>
                            <th style="border: 1px solid #000; padding: 4px; text-align: center; width: 30%;">Survey Fees (N)</th>
                            <th style="border: 1px solid #000; padding: 4px; text-align: center; width: 30%;">Dev. Charge (N)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                            @php
                                // Normalize the category label for comparison
                                $normalizedCatLabel = $cat['label'];
                                if (isset($cat['is_sub']) && $cat['is_sub']) {
                                    $normalizedCatLabel = str_replace(['i. ', 'ii. ', 'iii. ', 'iv. '], 'Residential - ', $cat['label']);
                                }
                                
                                // Multiple matching strategies (CASE-INSENSITIVE)
                                $isMatch = false;
                                
                                // Strategy 1: Exact match (case-insensitive)
                                if (strcasecmp($selectedCategory, $cat['label']) === 0 || strcasecmp($selectedCategory, $normalizedCatLabel) === 0) {
                                    $isMatch = true;
                                }
                                
                                // Strategy 2: Partial match (case-insensitive)
                                if (!$isMatch && $selectedCategory) {
                                    if (stripos($selectedCategory, $cat['label']) !== false || stripos($normalizedCatLabel, $selectedCategory) !== false) {
                                        $isMatch = true;
                                    }
                                }
                                
                                // Get the actual values if matched
                                $surveyVal = $isMatch ? ($recommendation->rofo_survey_fees ?? 0) : null;
                                $devVal = $isMatch ? ($recommendation->rofo_dev_charge ?? 0) : null;
                                
                                // Accumulate totals
                                if ($isMatch) {
                                    $totalSurvey += (float)$surveyVal;
                                    $totalDev += (float)$devVal;
                                }
                            @endphp
                            <tr>
                                <td style="border: 1px solid #000; padding: 4px; {{ $cat['is_sub'] ?? false ? 'padding-left: 20px;' : 'font-weight: bold;' }}">
                                    {{ $cat['label'] }}
                                </td>
                                <td style="border: 1px solid #000; padding: 4px; text-align: right;">
                                    @if($isMatch)
                                        <strong>{{ number_format($surveyVal, 2) }}</strong>
                                    @else
                                        {{ number_format($cat['fee'], 2) }}{{ $cat['extra'] ?? '' }}
                                    @endif
                                </td>
                                <td style="border: 1px solid #000; padding: 4px; text-align: right;">
                                    {{ $isMatch ? number_format($devVal, 2) : '' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr style="font-weight: bold;">
                            <td style="border: 1px solid #000; padding: 4px;">TOTAL</td>
                            <td style="border: 1px solid #000; padding: 4px; border-bottom: 2px double #000;">
                                N <span style="display: inline-block; min-width: 100px; text-align: right;">{{ number_format($totalSurvey + $totalDev, 2) }}</span>
                            </td>
                            <td style="border: 1px solid #000; padding: 4px;"></td>
                        </tr>
                    </tbody>
                </table>

                <p>I require the Director Survey to carry out the land survey for me 
                    <strong>({{ $recommendation->rofo_director_survey ?? 'NO' }})</strong>.
                </p>
                <p>I require a licensed surveyor to carry out the land survey for me 
                    <strong>({{ $recommendation->rofo_licensed_surveyor ?? 'NO' }})</strong>.
                </p>

                <div style="border: 1px solid #000; padding: 10px; margin-top: 20px; font-weight: bold; font-size: 11px;">
                    NOTE: APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER SIGNING.
                </div>

                <div style="display: flex; justify-content: space-between; margin-top: 60px;">
                    <div style="border-top: 1px solid #000; width: 45%; text-align: center; padding-top: 5px;">APPLICANT'S SIGNATURE</div>
                    <div style="border-top: 1px solid #000; width: 30%; text-align: center; padding-top: 5px;">DATE</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    <script>
        window.addEventListener('afterprint', function() {
            fetch('{{ route('land-rofos.log-print', $recommendation->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.opener.location.reload();
                }
            });
        });

        // Trigger print 1s after load
        setTimeout(() => {
            window.print();
        }, 1000);
    </script>
</body>
</html>
