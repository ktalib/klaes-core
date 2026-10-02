<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SLTR Recommendation - {{ $recommendation->sltr_number ?? 'N/A' }}</title>
    @php
        // The proof sheet — set by SltrRecommendationController::printWhiteCopy().
        $isWhiteCopy = !empty($isWhiteCopy);

        // Never minted for a proof: the serial is the document's official number and
        // this template mints it as it renders, so merely opening a proof would
        // otherwise spend a real one.
        $securityCode = null;
        $sc = null;
        if (!$isWhiteCopy) {
            $securityCode = app(\App\Services\SecurityCodeService::class)->getOrGenerateForDocument(
                (string) ($recommendation->sltr_number ?? ($recommendation->id ?? '')),
                (int) $recommendation->id,
                'SLTR Recomm'
            );
            $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($securityCode->code);
        }
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
            /* Top inset clears the letterhead rule at 30mm. Side insets are equal
               so the column sits centred on the sheet; 22mm each side preserves
               the previous 166mm column width exactly, so nothing re-wraps. */
            padding: 42mm 22mm 10px 22mm;
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

        /* ── White Copy: the proof sheet ────────────────────────────────────
           Black on white, on ordinary paper. The crest, the serial and the
           signature lines come off in the markup; the mark below says what the
           sheet is, centred across the head of the page. Geometry untouched, so
           the proof breaks where the official document breaks. */
        /* Black on white, the whole sheet. Named selectors were not enough — the
           letterhead brings its own green and red, and the body carries a handful
           of accent colours of its own — so the proof paints EVERYTHING black and
           then puts back only what has to stay drawn: the rules and borders.

           A grayscale filter would have been shorter and wrong: it turns the reds
           into a mid-grey that prints faint, and this sheet has to be read. */
        .white-copy,
        .white-copy * { color: #000 !important; }

        /* The letterhead's coloured rules become plain black ones rather than
           vanishing — they are the frame of the page, not decoration. */
        .white-copy .lh-rule-top,
        .white-copy .lh-rule-v { border-color: #000 !important; background: #000 !important; }

        /* The crest is the State's mark. Off the proof, with its space kept so the
           titles beside it stay where they are on the official sheet. */
        .white-copy .lh-crest { visibility: hidden; }

        .white-copy-mark-block {
            position: absolute; top: 8px; left: 0; right: 0;
            text-align: center; z-index: 5;
        }
        .white-copy-mark {
            color: #000; font-family: Arial, Helvetica, sans-serif;
            font-size: 24pt; font-weight: 900; line-height: 1.1;
            letter-spacing: 0.24em; text-transform: uppercase;
        }
        .white-copy-note {
            margin-top: 2px; font-family: Arial, Helvetica, sans-serif;
            font-size: 6.5pt; font-weight: bold; letter-spacing: 0.14em;
            color: #333; text-transform: uppercase;
        }
        .white-copy-sig-gap {
            display: block; text-align: center; font-size: 7pt;
            font-style: italic; color: #6b7280;
        }

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
            /* 3mm is about as close to the sheet edge as a laser printer will
               render; the rest of the clearance from the letterhead title comes
               from the tightened padding and type sizes below. */
            top: 3mm;
            right: 22mm; /* flush with the centred content column */
            border: 1px solid var(--primary-green);
            padding: 2px 8px;
            min-width: 110px;
            font-size: 7.5pt;
            background: white;
            z-index: 20;
            text-align: center;
        }
        .serial-box .serial-label {
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid var(--primary-green);
            padding-bottom: 2px;
            margin-bottom: 2px;
            font-size: 7.5pt;
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

<div class="a4-page{{ $isWhiteCopy ? ' white-copy' : '' }}">
    @include('partials.ministry_letterhead', ['lhShowSpine' => false])

    {{-- The serial is the document's official number, so a proof carries none. In
         its place, WHITE COPY across the head of the sheet — the first thing read
         off it, and the reason nothing on it can be mistaken for an issued copy. --}}
    @if($isWhiteCopy)
    <div class="white-copy-mark-block">
        <div class="white-copy-mark">White Copy</div>
        <div class="white-copy-note">Proof for vetting — not an official document</div>
    </div>
    @else
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
    @endif
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
            {{-- The unit was hard-coded here as " per sq meter" and printed on every
                 record whatever had been agreed. It now comes off the record, and the
                 backfill stamped the existing ones with what this line used to say. --}}
            <li>a) Rent: <span class="red-bold">{{ $recommendation->ground_rent !== null && $recommendation->ground_rent !== '' ? '₦'.$recommendation->ground_rent_label : 'N/A' }}</span></li>
            <li>b) Term: <span class="red-bold">{{ $recommendation->term ? $recommendation->term.' Years' : 'N/A' }}</span></li>
            @if($recommendation->revision_period)
            <li>c) Revision Period: <span class="red-bold">{{ $recommendation->revision_period }} Years</span></li>
            @endif
            <li>{{ $recommendation->revision_period ? 'd' : 'c' }}) Land Use: <span class="red-bold">{{ $recommendation->land_use ?? 'N/A' }}</span></li>
            @if($recommendation->processing_fee)
            <li>{{ $recommendation->revision_period ? 'e' : 'd' }}) Processing Fee: <span class="red-bold">₦{{ number_format($recommendation->processing_fee, 2) }}</span></li>
            @endif
        </ul>

        <p style="text-align: justify; font-size: 11pt; margin: 8px 0;">
            You may wish to approve this application on the terms set above and subject to Survey Report and recommendation of the Urban Board/Physical Planning Department at page <span class="red-bold">{{ $pageSurvey }} and {{ $pagePlanning }} refer</span>
        </p>

        {{-- Signature lines come off the proof: a blank rule over an office name is
             what can be signed and passed off as the document itself. The room they
             took is left behind so the page still breaks where it breaks on the
             official print. --}}
        @if($isWhiteCopy)
        <div class="sig-row white-copy-sig-gap" style="margin-top: 55px;">
            Signature block omitted — white copy for proofreading only
        </div>
        @else
        <div class="sig-row" style="margin-top: 55px;">
            <div class="sig-col"><div class="line">Rank</div></div>
            <div class="sig-col"><div class="line">Director SLTR</div></div>
        </div>
        @endif

        <div class="approval-footer">
            <div class="section-center" style="margin-top: 10px;">Approval for the Conversion of Customary<br>Title to SLTR Statutory Right of Occupancy</div>
            <p style="margin: 4px 0 65px;">
                I recommend/do not recommend the application for a Grant
                @if(filled($recommendation->plot_number))
                    over Plot No.: <span class="red-bold">{{ $recommendation->plot_number }}</span>
                @else
                    over a Piece of Land
                @endif
                <br>
                Location <span class="red-bold">{{ normalizeLocationText($recommendation->location) ?: 'N/A' }}</span>
            </p>

            @if($isWhiteCopy)
            <div class="sig-row white-copy-sig-gap">
                Signature block omitted — white copy for proofreading only
            </div>
            @else
            <div class="sig-row" style="margin-top: 0;">
                <div class="sig-col"><div class="line">Permanent Secretary</div></div>
                <div class="sig-col"><div class="line">Date</div></div>
            </div>
            @endif

            <div class="status-stamp" style="margin: 12px 0 65px;">
                The Grant of Occupancy is hereby APPROVED/NOT APPROVED
            </div>

            @if($isWhiteCopy)
            <div class="sig-row white-copy-sig-gap">
                Signature block omitted — white copy for proofreading only
            </div>
            @else
            <div class="sig-row" style="margin-top: 0; margin-bottom: 10px;">
                <div class="sig-col"><div class="line">Honourable Commissioner</div></div>
                <div class="sig-col"><div class="line">Date</div></div>
            </div>
            @endif
        </div>

    </div>
</div>

{{-- Collection acknowledgement, one sheet per copy (File Copy, then Original).
     `.a4-page` forces height 297mm with overflow:hidden and is a flex row, none
     of which suits the ack sheet, so each gets its own sheet wrapper. @page
     margin is 0 here, so the wrapper supplies the print margins the sheet's own
     CSS assumes. --}}
{{-- Not on a proof: the acknowledgement sheet is the half the applicant signs and
     returns on collection, and there is nothing on it to proofread that is not
     already on the letter. A white copy is one sheet. --}}
@unless($isWhiteCopy)
<div class="ack-sheet-wrap">
    @include('sltr_recommendations.templates._ack_sheet')
</div>
@endunless

{{-- Wait for the letterhead crest, the footer logos and the remote QR image
     before opening the dialog, otherwise they print blank. --}}
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 400);
    });
</script>
</body>
</html>
