<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLTR Recommendation - {{ $recommendation->sltr_number ?? 'N/A' }}</title>
    @php
        $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
            (string) ($recommendation->sltr_number ?? ($recommendation->id ?? '')),
            (int) $recommendation->id,
            'SLTR Recomm'
        );
        $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
    @endphp
    <style>
        :root {
            --primary-green: #2e7d32;
            --accent-red: #a00000;
        }

        /* Basic Reset and Layout */
        * { box-sizing: border-box; }
        body {
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt; /* Reduced to leave space at bottom */
        }

        .a4-page {
            background-color: white;
            position: relative; /* anchors the CSS letterhead and the serial box */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
            width: 210mm;
            height: 297mm; /* Forced height to ensure single page */
            margin: 10px auto;
            display: flex;
            overflow: hidden; /* Prevents text from spilling out */
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        .main-container {
            flex: 1;
            /* Keeps content clear of the letterhead drawn by
               partials/ministry_letterhead: top rule at 38mm, vertical rule at 30mm. */
            padding: 42mm 10mm 10px 34mm;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        .title-block {
            text-align: center;
            font-weight: bold;
            font-size: 12pt;
            text-transform: uppercase;
            margin: 6px 0;
            line-height: 1.1;
        }

        .address {
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 12px;
        }

        /* Grid for Page 1 / Page 9 sections */
        .content-grid {
            display: grid;
            grid-template-columns: 70px 1fr;
            gap: 5px;
            margin-bottom: 10px;
            line-height: 1.25;
        }

        .label-bold { font-weight: bold; }
        .red-num { color: var(--accent-red); }
        .red-bold { color: var(--accent-red); font-weight: bold; }
        .caps-bold { font-weight: bold; text-transform: uppercase;color: var(--accent-red); }

        .section-center {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0 6px 0;
            font-size: 10.5pt;
        }

        .highlight-box {
            color: var(--accent-red);
            font-weight: bold;
            text-align: justify;
            margin-bottom: 10px;
        }

        /* Terms List */
        .terms {
            list-style: none;
            padding-left: 15px;
            margin: 10px 0;
        }
        .terms li { margin-bottom: 3px; }

        /* Signature Blocks */
        .sig-row {
            display: flex;
            justify-content: space-between;
            margin-top: 28px;
            text-align: center;
        }
        .sig-col { width: 40%; }
        .line { border-top: 1px solid #000; padding-top: 3px; font-weight: bold; font-size: 9.5pt; }

        /* Bottom Section */
        .approval-footer {
            margin-top: 12px;
            border-top: 2px solid var(--primary-green);
            padding-top: 0;
            padding-bottom: 6px;
        }

        .status-stamp {
            text-align: center;
            color: var(--accent-red);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12pt;
            margin: 18px 0;
        }
 
        /* Footer Logos */
        .doc-footer-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 6px;
            margin-top: 8px;
        }
        .doc-footer-logos img {
            height: 35px;
            width: auto;
            object-fit: contain;
        }

        /* The acknowledgement partial emits one .ack-page per copy (File Copy,
           then Original), each already carrying page-break-before. The styling
           therefore goes on the individual sheets, not the wrapper, or the two
           copies would share a single card. Mirrors .a4-page on screen but stays
           in normal flow and grows with its content. */
        .ack-sheet-wrap {
            width: 210mm;
            margin: 0 auto;
        }
        .ack-sheet-wrap .ack-page {
            background-color: white;
            margin: 10px auto;
            padding: 15mm 14mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        /* The partial anchors its footer logo to bottom/right:0. An absolutely
           positioned child is placed against the PADDING box, so those offsets
           put the logo hard against the sheet edge — and with @page margin:0
           there is no printer margin left, so it prints sliced. Inset it by the
           sheet padding instead. */
        .ack-sheet-wrap .ack-page .footer {
            right: 14mm;
            bottom: 8mm; /* sits low on the sheet but clear of the unprintable edge */
        }

        /* Printing logic to force one page */
        @media print {
            body { background: none; }
            .a4-page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: 100%;
            }
            .ack-sheet-wrap {
                width: 100%;
                margin: 0;
            }
            .ack-sheet-wrap .ack-page {
                margin: 0;
                box-shadow: none;
                padding: 14mm 12mm;
            }
            .ack-sheet-wrap .ack-page .footer {
                right: 12mm;
                bottom: 8mm;
            }
            @page { size: A4; margin: 0; }
        }

        .serial-box {
            position: absolute;
            /* 4mm is about as close to the sheet edge as a laser printer will
               render; the rest of the clearance from the letterhead title comes
               from the tightened padding below. */
            top: 4mm;
            right: 12mm;
            border: 1px solid var(--primary-green);
            padding: 3px 8px;
            min-width: 110px;
            font-size: 8pt;
            background: white;
            z-index: 20;
            text-align: center;
        }
        .serial-box .serial-label {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid var(--primary-green);
            padding-bottom: 2px;
            margin-bottom: 3px;
            font-size: 8pt;
            color: var(--primary-green);
        }
        .serial-code-wrap {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            justify-content: center;
        }
        .serial-fraction {
            line-height: 1;
            color: #1e293b;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            font-weight: 900;
        }
        .serial-fraction .frac-top {
            border-bottom: 1.5px solid #1e293b;
            padding-bottom: 1px;
            font-size: 8px;
        }
        .serial-fraction .frac-bot {
            padding-top: 1px;
            font-size: 8px;
        }
        .serial-digits {
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.1em;
            color: #1e293b;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>

<div class="a4-page">
    @include('partials.ministry_letterhead', ['lhSpine' => 'Systematic Land Titling and Registration'])

    <div class="serial-box">
        <div class="serial-label">Serial No:</div>
        <div class="serial-code-wrap">
            <span class="serial-fraction">
              <span class="frac-top">{{ $sc['alphabet'] }}</span>
              <span class="frac-bot">{{ $sc['digits_start'] }}</span>
            </span>
            <span class="serial-digits">{{ $sc['digits_end'] }}</span>
        </div>
    </div>
    <div class="main-container">
        <div class="title-block">
            Recommendation for the Conversion of<br>
            Customary Title to SLTR Statutory  Right of Occupancy
        </div>

        <div class="address">
            Honourable Commissioner<br>
            Ministry of Land and Physical Planning<br>
            Kano State.
        </div>

        @php
            $pageApp      = $recommendation->page_application ?? '-';
            $pageSurvey   = $recommendation->page_survey      ?? '-';
            $pagePlanning = $recommendation->page_planning     ?? '-';
        @endphp

        <div class="content-grid">
            <div class="label-bold">Page <span class="red-num">{{ $pageApp }}</span></div>
            <div>
                Application by: <span class="caps-bold">{{ strtoupper($recommendation->applicant_name ?? 'N/A') }}</span><br>
                for the right of Occupancy <span class="label-bold">over a piece of Land</span> at <span class="red-bold">{{ normalizeLocationText($recommendation->location) ?: 'N/A' }}</span> in <span class="red-bold">{{ $recommendation->lga ?? 'N/A' }}</span> Local Government Area.<br>
                for the purpose of <span class="red-bold">{{ $recommendation->purpose_of_clause ?? 'N/A' }}{{ $recommendation->sltr_number ? ' ('.$recommendation->sltr_number.')' : '' }}.</span>
            </div>
        </div>

        <div class="content-grid">
            <div class="label-bold">Page <span class="red-num">{{ $pageSurvey }}</span></div>
            <div>
                Survey Report The <span class="label-bold">Correct description of the plot is a piece of Land at {{ normalizeLocationText($recommendation->location) ?: 'N/A' }}</span>{{ $recommendation->plot_number ? ', Plot No. '.$recommendation->plot_number : '' }}, in <span class="label-bold">{{ $recommendation->lga ?? 'N/A' }} Local Government Area.</span>
            </div>
        </div>

        <div class="section-center">Planning Recommendation (If Any)</div>

        <div class="highlight-box">
            This is a case of SLTR process from customary title to statutory right of occupancy, As per physical planning recommendation at page {{ $pagePlanning }} herein.
        </div>

        <p style="margin: 5px 0;">The grant of Right of occupancy is recommended on the terms set out as follows:</p>
        <ul class="terms">
            <li>a) Rent: <span class="red-bold">{{ $recommendation->ground_rent ? number_format($recommendation->ground_rent, 2).' per sq meter' : 'N/A' }}</span></li>
            <li>b) Term: <span class="red-bold">{{ $recommendation->term ? $recommendation->term.' Years' : 'N/A' }}</span></li>
            <li>c) Land Use: <span class="red-bold">{{ $recommendation->land_use ?? 'N/A' }}</span></li>
            @if($recommendation->processing_fee)
            <li>d) Processing Fee: <span class="red-bold">₦{{ number_format($recommendation->processing_fee, 2) }}</span></li>
            @endif
        </ul>

        <p style="text-align: justify; font-size: 11pt; margin: 8px 0;">
            You may wish to approve this application on the terms set above and subject to Survey Report and recommendation of the Urban Board/Physical Planning Department at page <span class="red-bold">{{ $pageSurvey }} and {{ $pagePlanning }} refer</span>
        </p>

        <div class="sig-row" style="margin-top: 55px;">
            <div class="sig-col"><div class="line">Rank</div></div>
            <div class="sig-col"><div class="line">Director SLTR</div></div>
        </div>

        <div class="approval-footer">
            <div class="section-center" style="margin-top: 10px;">Approval for the Conversion of Customary<br>Title to SLTR Statutory Right of Occupancy</div>
            <p style="margin: 4px 0 65px;">I recommend/do not recommend the application for a Grant over Plot No.: <span class="red-bold">{{ $recommendation->plot_number ?? '___________' }}</span> Plan No. Location <span class="red-bold">{{ normalizeLocationText($recommendation->location) ?: 'N/A' }}</span></p>

            <div class="sig-row" style="margin-top: 0;">
                <div class="sig-col"><div class="line">Permanent Secretary</div></div>
                <div class="sig-col"><div class="line">Date</div></div>
            </div>

            <div class="status-stamp" style="margin: 12px 0 65px;">
                The Grant of Occupancy is hereby APPROVED/NOT APPROVED
            </div>

            <div class="sig-row" style="margin-top: 0; margin-bottom: 10px;">
                <div class="sig-col"><div class="line">Honourable Commissioner</div></div>
                <div class="sig-col"><div class="line">Date</div></div>
            </div>
        </div>

        <div class="doc-footer-logos" style="padding-top: 2px; padding-bottom: 10px; margin-top: 4px;">
             <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" >
            <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="LAS Logo" style="height: 30px;">
           
        </div>
    </div>
</div>

{{-- Collection acknowledgement, one sheet per copy (File Copy, then Original).
     `.a4-page` forces height 297mm with overflow:hidden and is a flex row, none
     of which suits the ack sheet, so each gets its own sheet wrapper. @page
     margin is 0 here, so the wrapper supplies the print margins the sheet's own
     CSS assumes. --}}
<div class="ack-sheet-wrap">
    @include('sltr_recommendations.templates._ack_sheet')
</div>

{{-- Wait for the letterhead crest, the footer logos and the remote QR image
     before opening the dialog, otherwise they print blank. --}}
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
</body>
</html>