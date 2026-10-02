<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Kano State - Right of Occupancy Official - {{ $recommendation->file_number }}</title>
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
            line-height: 3;
            font-size: 20pt;
            padding: 20px 0;
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            position: relative;
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
            margin: 6mm 8mm 0 8mm;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        /* EXACT ORIGINAL DIMENSIONS - only margin adjusted */
        .ornate-border {
            border: 34px solid transparent;
            border-image-source: url("{{ asset('assets/images/pages/1779539656370(1).png') }}");
            border-image-slice: 160;
            border-image-repeat: round;
            border-image-width: 34px;
            margin: 1mm 3mm 60mm 3mm;
        }

        @media print {
            .ornate-border {
                border-width: 38px !important;
                border-image-width: 38px !important;
            }
        }

        .simple-margin {
            margin: 20mm;
        }

        .inner-content {
            padding: 18px 42px 0 42px;
            flex: 1;
            box-sizing: border-box;
            font-size: 13.5px;
            line-height: 1.32;
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
            border-radius: 50%;
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
            margin: 8px 0;
        }

        .row {
            display: flex;
            margin-bottom: 3px;
            font-weight: bold;
            align-items: baseline;
        }
        .title-center {
            text-align: center;
            color: #c90202 !important;
            font-size: 15px;
            font-weight: bold;
            text-decoration: underline;
            margin: 5px 0;
        }

        /* SIGNATURE BLOCK - Commissioner line uses the exact CSS technique provided, no double lines */
        .signature-block {
            display: flex;
            justify-content: space-between;
            padding: 0 40px 14px 40px;
            text-align: center;
            font-weight: bold;
            align-items: flex-end;
        }

        /* Security line container - acts as the line */
        .security-line-container {
            position: relative;
            width: 280px;
            height: 4px;
            margin-bottom: 6px;
            overflow: hidden;
            background: transparent;
        }

        /* Exact CSS from user's request applied to pseudo-element */
        .security-line-container::after {
            content: "Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning ";
            position: absolute;
            top: -1px;
            left: 0;
            width: 100%;
            font-size: 3.5px;
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
            /* Add a subtle line effect through text if needed, but keep it clean */
            text-shadow: 0 0.5px 0 #666;
        }

        /* Date side - single clean line */
        .date-line {
            width: 150px;
            border-top: 2px solid #000;
            padding-top: 0;
            margin-top: 0;
            height: 1px;            /* just the border */
            margin-bottom: 8px;      /* space between line and "DATE" */
        }

        .signature-block > div {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* FOOTER - exactly as your original */
        .footer-barcode-area {
            height: 55mm;
            padding: 0 22mm 4mm 22mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            z-index: 5;
            position: relative;
        }

        .barcode-group {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            visibility: hidden;
        }
        .barcode-img {
            height: 32px;
        }

        .qr-code-group {
            margin-top: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            visibility: visible;
        }
        
        .qr-img {
            margin-top: 0;
            width: 33px;
            height: 33px;
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

        .inline-data {
            display: inline-block;
            border-bottom: 1px dotted #000;
            min-width: 45px;
            margin-left: 5px;
            font-weight: normal;
            color: #000;
            padding-bottom: 1px;
        }

        /* Print Button Styles */
        .print-btn-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .print-btn {
            padding: 12px 24px;
            background-color: #006b3f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition:
                background-color 0.3s,
                transform 0.2s;
        }
        .print-btn:hover {
            background-color: #004d2c;
            transform: translateY(-2px);
        }
        .print-btn:active {
            transform: translateY(0);
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
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .page-container {
                box-shadow: none !important;
                margin: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
            }
            .page-container ~ .page-container {
                page-break-before: always !important;
            }
            .print-btn-container {
                display: none !important;
            }
            #scheme-toolbar {
                display: none !important;
            }
            .barcode-group {
                visibility: hidden !important;
            }
            .no-print {
                display: none !important;
            }
        }

        /* Page2 specific styles */
        .applicant-address-block {
            display: flex;
            border: 1px solid #000;
            margin-bottom: 15px;
            min-height: 100px;
        }
        .left-commissioner {
            padding: 10px;
            width: 50%;
            border-right: 1px solid #000;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .right-address {
            padding: 10px;
            width: 50%;
            font-size: 13px;
        }
        .address-line-box {
            min-height: 22px;
            border-bottom: 1px dotted #000;
            display: block;
            padding-bottom: 3px;
            margin-top: 6px;
            word-break: break-word;
            line-height: 1.3;
        }
        .address-line-box:first-of-type {
            margin-top: 0;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }
        .fee-table th,
        .fee-table td {
            border: 1px solid #000;
            padding: 4px;
        }
        .note-box {
            border: 1px solid #000;
            padding: 10px;
            margin-top: 20px;
            font-weight: bold;
            font-size: 11px;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
        }
        .signature-item {
            border-top: 1px solid #000;
            width: 35%;
            text-align: center;
            padding-top: 5px;
        }
        .signature-item-date {
            border-top: 1px solid #000;
            width: 30%;
            text-align: center;
            padding-top: 5px;
        }

        /* ── Re-issuance only ──────────────────────────────────────────────
           Superseding notice: sits above the coat of arms and stops short of the
           right edge so it cannot run under the absolutely-positioned version /
           security-code block. */
        .supersede-notice {
            /* Matches docs/templates/land/rofo_supersede.html: full-width so it centres
               on the page, and lifted above the ORIGINAL / security-code block (which
               starts at top: 10px inside .inner-content) so the two never collide. */
            position: absolute;
            top: -10px;
            left: 0;
            right: 0;
            margin: 0;
            font-size: 14.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            line-height: 20px;
            /* Red so the supersession is unmissable on the issued letter. Matches the
               document's existing red (.title-center, the ministry banner) rather than
               the brighter #ff0000 of the ORIGINAL marker directly beneath it.
               print-color-adjust keeps it red on paper — without it browsers drop
               non-essential colour in print. */
            color: #c90202;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            text-align: center;
            white-space: nowrap;   /* must stay on one line */
            z-index: 2;
        }

        /* RE-ISSUANCE watermark, on both pages. It sits ABOVE the letter (z-index 5):
           the content wrapper and its ornate border paint over anything lower, which
           left the watermark showing only in the page margin. The low alpha keeps the
           text underneath perfectly readable. */
        .reissuance-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 72px;
            font-weight: 900;
            letter-spacing: 8px;
            color: rgba(190, 24, 24, 0.15);
            white-space: nowrap;
            pointer-events: none;
            z-index: 5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    </style>
</head>
<body spellcheck="false">
    <div class="print-btn-container no-print">
        <button class="print-btn" onclick="window.print()">Print Document</button>
    </div>

    @php
        // Re-issuance (?supersede=1): same letter, plus the superseding notice and
        // the RE-ISSUANCE watermark.
        //   klaes  — the original set was already issued from KLAES, so the
        //            re-issuance is the ORIGINAL copy only.
        //   legacy — pre-KLAES original, so the full Original/Duplicate/Triplicate
        //            set is issued as normal.
        $isReissuance   = request()->boolean('supersede');
        $reissueSource  = strtolower(trim((string) request('reissue_source', '')));
        $originalOnly   = $isReissuance && $reissueSource !== 'legacy';

        $requestedStatus = request('status', 'Original');
        $printVersions = $originalOnly
            ? ['Original']
            : (($requestedStatus === 'Batch') ? ['Original', 'Duplicate', 'Triplicate'] : [$requestedStatus]);

        // Date the PREVIOUS letter was issued. Passed in via ?superseded_date=..., else:
        //   legacy — a new record was created for the re-issuance, so its own
        //            rofo_generated_at is today; the original date is the one keyed in
        //            on the recommendation form (reissuance_original_date).
        //   klaes  — re-issuing only flags the existing record, so rofo_generated_at
        //            still holds the date the original letter was generated.
        // created_at is never used: it is when the record was captured, which for a
        // legacy re-issuance is today and would print "supersedes ... issued today".
        // Legacy rows captured before that field existed have nothing better, so they
        // fall through to rofo_generated_at/created_at — the notice always prints, but
        // such a row shows the capture date until the original date is filled in.
        $supersedeOn = trim((string) ($supersededDate ?? ''));
        if ($isReissuance && $supersedeOn === '') {
            $originalIssuedAt = ($reissueSource === 'legacy' ? $recommendation->reissuance_original_date : null)
                ?? $recommendation->rofo_generated_at
                ?? $recommendation->created_at;

            $supersedeOn = optional($originalIssuedAt)->format('jS F, Y') ?? '';
        }

        $versionColors = [
            'Original' => '#ff0000',
            'Duplicate' => '#0000ff',
            'Triplicate' => '#008000',
        ];
    @endphp

    @foreach($printVersions as $index => $version)
    <!-- PAGE 1 – Signature line uses exact CSS technique, no double lines -->
        {{-- <div class="page-container" id="page1-{{ $index }}" style="{{ $index > 0 ? 'page-break-before: always;' : '' }} background-image: url('/assets/images/pages/backgrand.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat;">     --}}

                 <div class="page-container" id="page1-{{ $index }}">


        <div class="security-bg"></div>

        @if($isReissuance)
            <div class="reissuance-watermark">RE-ISSUANCE</div>
        @endif

        <!-- @if($recommendation->land_rofo_serial_no)
            <div style="position: absolute; top: 15mm; right: 25mm; font-family: 'Arial', sans-serif; font-weight: 900; font-size: 16pt; color: #c90202; z-index: 50; letter-spacing: 2px;">
                No: {{ $recommendation->land_rofo_serial_no }}
            </div>
        @endif -->


        <div class="content-wrapper ornate-border">
            <div class="inner-content" style="position: relative;">
                <!-- Version & Security Code (absolutely positioned top-right) -->
                <div style="position: absolute; top: 10px; right: 10px; text-align: right; font-weight: bold; font-size: 16px; letter-spacing: 0.35em; z-index: 2;">
                    @if($version !== 'CTC')<span style="color: {{ $versionColors[$version] ?? '#ff0000' }}; text-transform: uppercase;">{{ $version }}</span>@endif
                    
                    @if(isset($securityCode))
                        @php
                            $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
                        @endphp
                        <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                            <div style="display: inline-flex; align-items: center; gap: 4px; letter-spacing: normal;">
                                <span style="line-height: 1; color: #334155; display: inline-flex; flex-direction: column; align-items: center; font-weight: 900; font-family: Arial, sans-serif;">
                                    <span style="border-bottom: 1.5px solid #334155; padding-bottom: 1px; font-size: 8px;">
                                        {{ $sc['alphabet'] }}
                                    </span>
                                    <span style="padding-top: 1px; font-size: 8px;">
                                        {{ $sc['digits_start'] }}
                                    </span>
                                </span>
                                <span style="font-size: 13px; font-weight: 900; letter-spacing: 0.1em; color: #334155; font-family: 'Courier New', monospace;">
                                    {{ $sc['digits_end'] }}
                                </span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Superseding notice — replaces the handwritten line on the reissued letter.
                     Printed only with a real date; without one the sentence would end bare. --}}
                @if($isReissuance && $supersedeOn !== '')
                    <div class="supersede-notice">
                        This letter of grant supersedes the previous one issued on {{ $supersedeOn }}
                    </div>
                @endif

                <!-- Header: Coat of Arms centered, QR on the left -->
                <div style="position: relative; margin-bottom: 4px; margin-top: 10px; min-height: 110px;">
                    <div style="text-align: center;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" alt="Nigeria Seal" style="width: 100px; height: auto; display: inline-block;" />
                    </div>
                    {{-- Not every recommendation has a tracking_id (a legacy re-issuance is
                         captured straight onto the RofO table), and an empty ?data= makes the
                         QR service return "malformed request" instead of an image — so fall
                         back to the file number, as the batch template does. --}}
                    @php $qrData = trim((string) ($recommendation->tracking_id ?: $recommendation->file_number)); @endphp
                    @if($qrData !== '')
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($qrData) }}" alt="QR" style="position: absolute; left: 40px; top: 50%; transform: translateY(-50%); width: 55px; height: 55px;" />
                    @endif
                </div>
                <!-- Centered Blue Banner -->
                <div style="text-align: center; margin-bottom: 8px;">
                    <div style="display: inline-block; border: 2px solid #000; border-radius: 8px; padding: 3px; background: #fff;">
                        <div data-rofo-badge style="background: #fff; padding: 8px 16px; border-radius: 5px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                            <p style="font-weight: bold; color: #fff; text-transform: uppercase; font-size: 15px; margin: 0; letter-spacing: 0.5px;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                            <p style="font-size: 13px; font-weight: bold; color: #fff; margin: 3px 0 0 0; letter-spacing: 0.3px;">No. 2 Dr Bala Mohammed Road, Kano State, Nigeria</p>
                        </div>
                    </div>
                </div>

                @php
                    // LOCATION is shown separately from PLOT/PLAN No, so strip a leading
                    // plot-number token (legacy records auto-generated it as a prefix) and
                    // normalize inconsistent casing (e.g. "340B HOTORO Nasarawa Kano State").
                    $printLocation = trim((string) ($recommendation->location ?? ''));
                    $plotNo = trim((string) ($recommendation->plot_number ?? ''));
                    if ($plotNo !== '' && $printLocation !== '') {
                        $printLocation = preg_replace('/^' . preg_quote($plotNo, '/') . '\s*[-\/]?\s*/i', '', $printLocation);
                    }
                    $printLocation = trim(preg_replace('/\s+/', ' ', $printLocation));
                    if ($printLocation !== '') {
                        $printLocation = mb_strtoupper($printLocation, 'UTF-8');
                    }

                    // Applications derived from an existing file (Plot Subdivision / Plot
                    // Merger / Change of Purpose) cite the parent file number in place of
                    // the plan number on the "as per plan No." line.
                    $oldFileNumber = trim((string) ($recommendation->old_file_number ?? ''));
                    $layoutPlanNo  = trim((string) ($recommendation->layout_plan_no ?? ''));
                    $planNoRef     = $oldFileNumber !== '' ? $oldFileNumber : $layoutPlanNo;

                    // PLOT/PLAN No. always prints whatever is on the record: both parts when
                    // present, otherwise whichever one exists (blank when neither does).
                    $plotPlanNo = implode(' / ', array_filter([$plotNo, $layoutPlanNo], fn ($v) => $v !== ''));
                @endphp
                <!-- REF-GRID SECTION -->
                <div class="ref-grid">
                    <div>
                        <div class="bordered-section" style="padding: 20px 15px;">
                            <div class="row" style="margin-bottom: 25px; align-items: flex-end;">
                                <span style="font-weight: bold; font-size: 16px; margin-right: 8px; white-space: nowrap;">To:</span>
                                <span class="inline-data" style="flex: 1; text-align: left; border-bottom: 1.5px dotted #000; font-size: 16px; padding-bottom: 2px; line-height: 1;">{{ $recommendation->applicant_name }}</span>
                            </div>
                            <div class="row" style="margin-bottom: 10px; align-items: flex-end;">
                                <span style="width: 32px; display: inline-block;"></span>
                                <span class="inline-data" style="flex: 1; text-align: left; border-bottom: 1.5px dotted #000; font-size: 16px; padding-bottom: 2px; min-height: 20px; line-height: 1;">{{ $recommendation->applicant_address }}</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="bordered-section">
                            <div class="row">
                                R of O No:<span class="inline-data" style="min-width: 235px">{{ $recommendation->file_number }}</span>
                            </div>
                            <div class="row">
                                PLOT/PLAN No:<span class="inline-data" style="min-width: 190px; margin-top: 10px">{{ $plotPlanNo }}</span>
                            </div>
                            <div class="row">
                                LOCATION:<span class="inline-data" style="min-width: 220px; margin-top: 10px">{{ $printLocation }}</span>
                            </div>
                            <div class="row">
                                DATE OF ISSUE:<span class="inline-data" style="min-width: 190px; margin-top: 10px">{{ $recommendation->rofo_generated_at ? $recommendation->rofo_generated_at->format('Y-m-d') : now()->format('Y-m-d') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="title-center">
                    TERMS OF OFFER OF GRANT/CONVEYANCE OF APPROVAL
                </div> 

                <!-- CONDITIONS SECTION -->
                <div class="conditions-list-fixed">
                    <p class="condition-item">
                        With reference to your application dated
                        <span class="inline-data" style="min-width: 80px">{{ $recommendation->created_at->format('jS F') }}</span>
                        <span class="inline-data" style="min-width: 30px">{{ $recommendation->created_at->format('Y') }}</span>, I am directed to inform you that the Governor of Kano State has
                        approved the grant of a Right of Occupancy to you over
                        @if(!empty($recommendation->plot_number))
                            plot No <span class="inline-data" style="min-width: 40px">{{ $recommendation->plot_number }}</span>
                        @else
                            piece of land
                        @endif
                        situated at
                        <span class="inline-data" style="min-width: 150px">{{ $printLocation }}</span>
                       
                  


                     as per plan No.  <span class="inline-data" style="min-width: 50px">{{ $planNoRef }}</span> on following the conditions:   </p>
                    <div class="condition-item">
                        <strong>1. Payment of:</strong>
                        <div class="sub-item">
                            <div class="sub-item-line">
                                <span class="sub-item-label">(a)</span> Ground Rent N
                                <span class="inline-data" style="min-width: 80px">{{ number_format($recommendation->ground_rent, 2) }}</span>
                                P.H.P.A. (Revisable after every 5 years)
                            </div>
                            <div class="sub-item-line">
                                @php $devIsText = filled($recommendation->development_charge) && !is_numeric($recommendation->development_charge); @endphp
                                <span class="sub-item-label">(b)</span> Development Charges @unless($devIsText) N @endunless
                                <span class="inline-data" style="min-width: 80px">{{ is_numeric($recommendation->development_charge) ? number_format($recommendation->development_charge, 2) : ($recommendation->development_charge ?: '0.00') }}</span>
                                (Payable once)
                            </div>
                            <div class="sub-item-line">
                                <span class="sub-item-label">(c)</span> Survey/Processing fees
                                N
                                <span class="inline-data" style="min-width: 80px">{{ number_format($recommendation->survey_fees, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="condition-item">
                        <div class="sub-item" style="margin-left: 0;">
                            <div class="sub-item-line">
                                <strong style="margin-right: 8px;">2.</strong>
                                <span class="sub-item-label">(a)</span> Term:
                                <span class="inline-data" style="min-width: 40px">{{ $recommendation->term }}</span>
                                years.
                            </div>
                            <div class="sub-item-line" style="padding-left: 18px;">
                                <span class="sub-item-label">(b)</span> Purpose:
                                <span class="inline-data" style="min-width: 120px">
                                    {{ $recommendation->land_use ?? $recommendation->rofo_land_use_category }}
                                    @if($recommendation->purpose_of_clause)
                                        ({{ $recommendation->purpose_of_clause }})
                                    @endif
                                </span>
                            </div>
                    <div class="sub-item-line" style="padding-left: 18px; display:flex; flex-wrap:wrap; align-items:baseline; gap:0 4px;">
                        <span class="sub-item-label">(c)</span>
                        <span>Improvement Value: N</span>
                        <span class="inline-data" style="min-width:80px; white-space:nowrap;">{{ number_format($recommendation->development_value, 2) }}</span>
                        <span style="white-space:nowrap;">within&nbsp;<span class="inline-data" style="min-width:8px; display:inline-block;">{{ $recommendation->development_period }}</span>&nbsp;years</span>
                    </div>
                        </div>
                    </div>
                    <p class="condition-item">
                        <strong>3.</strong> Not to alienate the Right of Occupation in
                        part or whole without written consent of the Governor.
                    </p>
                    <p class="condition-item">
                        <strong>4.</strong>To be responsible for development/maintenance of
                        drainage, landscaping and frontage beautification.
                    </p>
                    <p class="condition-item">
                        <strong>5.</strong> Not to erect or permit to be erected on the subject land any building or development except in accordance with plans and specifications approved by the State Planning Authority in the case of urban areas or this ministry in the case of rural areas.
                    </p>
                    <p class="condition-item">
                        <strong>6.</strong> To complete development of the land within
                        <span class="inline-data" style="min-width: 30px">{{ $recommendation->development_period }}</span>
                          <span>years.</span>
                    </p>
                    <p class="condition-item">
                        <strong>7.</strong> For Petrol Stations, 33 1/2 percent of annual
                        rental is payable to the Government.
                    </p>
                    <p class="condition-item">
                        <strong>8.</strong> The duplicate &amp; triplicate copies of the letter of Grant must be returned immediately duly accepted with the required fees to enable production of C OF O, otherwise the offer lapses.
                    </p>
                </div>

                <!-- SIGNATURE BLOCK -->
                <div class="signature-block" style="margin-top: auto;" spellcheck="false">
                    <div>
                        <div style="height: 45px;"></div>
                        <div class="security-line-container"></div>
                        <div spellcheck="false">HONOURABLE COMMISSIONER</div>
                    </div>
                    <div>
                        <div style="height: 45px;"></div>
                        <div class="security-line-container" style="width:160px;"></div>
                        <div style="margin-top:5px;" spellcheck="false">DATE</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- FOOTER - Exactly as your original -->
        <div class="footer-barcode-area">
            <div class="barcode-group" style="visibility: visible; opacity: 0.0001">
                <div style="font-size: 7px; transform: rotate(-90deg)">©NSPM</div>
                <div style="display: flex; flex-direction: column; align-items: center">
                    <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text={{ $recommendation->file_number }}&scale=1" class="barcode-img" style="margin-bottom: -10px" />
                    <span style="font-size: 8px" id="barcode-text">{{ $recommendation->file_number }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- PAGE 2 – Acceptance Letter -->
    <div class="page-container" id="page2-{{ $index }}" style="page-break-before: always;">
        <div class="security-bg"></div>

        @if($isReissuance)
            <div class="reissuance-watermark">RE-ISSUANCE</div>
        @endif

        <div class="content-wrapper simple-margin">
            <div class="inner-content">
                <div class="applicant-address-block">
                    <div class="left-commissioner">
                        <div style="text-align: center">
                            The Honourable Commissioner<br />
                            Ministry of Land and Physical Planning
                        </div>
                    </div>
                    <div class="right-address">
                        <div class="address-line-box" style="font-weight: bold;">
                            {{ $recommendation->applicant_address }}
                        </div>
                        <div class="address-line-box">&nbsp;</div>
                        <div class="address-line-box">&nbsp;</div>
                        <div style="margin-top: 10px;">
                            Date: <span style="border-bottom: 1px dotted #000; display: inline-block; min-width: 160px; padding-bottom: 2px;">{{ $recommendation->application_date ? $recommendation->application_date->format('Y-m-d') : '' }}</span>
                        </div>
                        <div style="text-align: center; margin-top: 8px; font-size: 12px;">Applicant's Address</div>
                    </div>
                </div>

                <h3 style="text-align: center; text-decoration: underline; margin: 10px 0;">
                    ACCEPTANCE LETTER
                </h3>
                <p>
                    With reference to the Offer of Grant, I hereby accept the terms and
                    conditions of the grant of the Right of Occupancy as conveyed to me
                    by your overleaf quoted letter.
                </p>
                <p>
                    I will submit my building plan to you for approval before I commence
                    any improvement on the Site, and on completion of the improvement, I
                    will get your completion Certificate before occupation of the
                    building. I forwarded herewith:
                </p>
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
                <table class="fee-table">
                    <tr>
                        <th>Land Use</th>
                        <th>Survey Fees (N)</th>
                        <th>Dev. Charge (N)</th>
                    </tr>
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
                            <td style="{{ isset($cat['is_sub']) && $cat['is_sub'] ? 'padding-left: 20px;' : 'font-weight: bold;' }}">
                                {{ $cat['label'] }}
                            </td>
                            <td style="text-align: right;">
                                @if($isMatch)
                                    <strong>{{ number_format($surveyVal, 2) }}</strong>
                                @else
                                    {{ number_format($cat['fee'], 2) }}{{ $cat['extra'] ?? '' }}
                                @endif
                            </td>
                            <td style="text-align: right;">
                                {{ $isMatch ? number_format($devVal, 2) : '' }}
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td><strong>TOTAL</strong></td>
                        <td></td>
                        <td></td>
                    </tr>
                </table>

                <p style="margin-top: 10px;">
                    <span class="checkbox" style="display: inline-block; width: 14px; height: 14px; border: 1.5px solid #000; vertical-align: middle; margin-right: 6px;">@if($recommendation->rofo_director_survey === 'YES')&#10003;@endif</span>
                    I require the Director Survey to carry out the land survey for me
                </p>
                <p>
                    <span class="checkbox" style="display: inline-block; width: 14px; height: 14px; border: 1.5px solid #000; vertical-align: middle; margin-right: 6px;">@if($recommendation->rofo_licensed_surveyor === 'YES')&#10003;@endif</span>
                    I require a licensed Surveyor to carry out the land survey for me
                </p>

                <div class="note-box">
                    NOTE: APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER
                    SIGNING.<br /><br />
                    THIS R OF O IS SUBJECT TO VERIFICATION BEFORE ANY STATUTORY PAYMENTS TO REVENUE DEPARTMENT.
                </div>

                <div class="signature-row">
                    <div class="signature-item">APPLICANT'S SIGNATURE</div>
                    <div class="signature-item-date">DATE</div>
                </div>
                <br>
                 <!-- Footer Logos -->
                <div style="display: flex; align-items: center; justify-content: flex-end; margin-top: 16px; padding-top: 6px; border-top: 1px solid #ccc;">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" style="height: 35px; width: auto; object-fit: contain;">
                </div>
            </div>
        </div>
        <div class="footer-barcode-area"></div>
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
                    try {
                        window.opener.location.reload();
                    } catch(e) {}
                }
            });
        });

        // Trigger print 1s after load
        setTimeout(() => {
            window.print();
        }, 1000);
    </script>

    <!-- Color Scheme Switcher — hidden, locked -->
    <div id="scheme-toolbar"></div>
    <style id="scheme-override"></style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var frameUrl = '{{ asset('assets/images/pages/1779539656370(1).png') }}';
            var css = '.ornate-border { border-image-source: url("' + frameUrl + '") !important; }\n';
            document.getElementById('scheme-override').textContent = css;
            document.querySelectorAll('[data-rofo-badge]').forEach(function(badge) {
                badge.style.background = '#c90202';
            });
        });


    </script>
</body>
</html>