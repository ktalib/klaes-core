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

        /* The security paper the letter is printed on. It replaces the inline SVG
           wave that stood in for it, and it is a full-bleed layer under
           .content-wrapper (z-index 1), so nothing on the page has to move for it.

           --security-bg-opacity is the one dial: 1 is the artwork as supplied, and
           lowering it fades the paper back if it reads too strong against the text
           on the real printer. body already carries print-color-adjust: exact, so
           what is on screen is what comes out. */
        .page-container {
            --security-bg-opacity: 1;
        }

        /* The security paper the letter prints on — OFF: the letter prints onto
           pre-printed security stock, so the artwork is not laid down again. To turn
           it back on, drop the `display: none` below.
           --security-bg-opacity above is the other dial: 1 is the artwork as supplied,
           lower fades it back if it reads too heavy on the real printer. */
        .security-bg {
            display: none;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: var(--security-bg-opacity);
            pointer-events: none;
            background-image: url("{{ asset('assets/letterhead/rofo-security-paper.jpg') }}");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: 0;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .content-wrapper {
            flex: 1;
            margin: 6mm 8mm 0 8mm;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        /* The frame was 34px (38px in print), then 28/32, now 24/28. It reads the
           same at arm's length, and every pixel it gives back on each axis goes
           straight into the boxes below — width the applicant's address can use
           instead of wrapping onto another line. */
        .ornate-border {
            border: 24px solid transparent;
            border-image-source: url("{{ asset('assets/images/pages/1779539656370(1).png') }}");
            border-image-slice: 160;
            border-image-repeat: round;
            border-image-width: 24px;

            /* Top margin fixed, bottom margin auto: every letter starts 14mm down
               the sheet and whatever room it does not use collects underneath,
               in the gap above the barcode.

               The top is deliberately NOT auto. An auto top margin only gets what
               is left over, and these letters run close to page height — the
               conditions list, the payment block and the signature line are all
               fixed — so on a full one it resolved to nearly 0 and the frame rode
               up to the edge of the paper. 8mm is a margin, not a leftover: it
               holds whether the address box is two lines or five. It was tried at
               14mm first and that read as a band of white above the frame — the
               security paper's own printed border is only a few mm in, so the eye
               measures this gap against that edge, not against the page.

               flex-grow is 0 so the box stays content-sized rather than stretching
               to fill the page, which is what leaves the slack free to fall to the
               bottom margin. The footer band below reserves 22mm — the barcode is
               printed on the security paper about 14mm off the foot — and does not
               shrink, so a long letter cannot push down into it.

               min-height only catches a freak-short letter, so the frame does not
               shrink into a squat box floating mid-page. */
            box-sizing: border-box;
            flex: 0 0 auto;
            min-height: 181mm;
            margin: 8mm 3mm auto 3mm;
        }

        @media print {
            .ornate-border {
                border-width: 28px !important;
                border-image-width: 28px !important;
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

        /* The "To:" box holds free text — a name and an address of whatever length
           the applicant has — while the right-hand box holds fixed labels against
           fixed-width rules. So width given to the left is width that stops the
           address wrapping, which is what was driving the whole letter down the
           page and into the barcode band.

           1.2fr / 1.3fr is the measured limit, not a guess. It was 1.1fr / 1.4fr
           until the dotted rules opposite were trimmed; that lowered the right
           box's min-content width and let this move another 25px. At 1.3fr the
           right box hits its floor again and "R of O No:", "PLOT/PLAN No:" and
           "DATE OF ISSUE:" each break across two lines. Re-measure before moving
           it further — the limit follows those min-widths. */
        .ref-grid {
            display: grid;
            grid-template-columns: 1.2fr 1.3fr;
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

        /* The right-hand details box. Each line used to be its own flex row with its
           own min-width on the rule, so every rule started wherever its label
           happened to end and stopped wherever its min-width happened to fall —
           four labels of four different lengths, four rules at four different
           positions. One grid for the whole box instead: the label column is sized
           to the widest label, so every rule starts on the same x, and 1fr carries
           them all to the same right edge. row-gap replaces the per-row margins
           that used to space them. */
        .ref-details {
            display: grid;
            grid-template-columns: max-content minmax(120px, 1fr);
            column-gap: 6px;
            row-gap: 10px;
            align-items: baseline;
        }
        .ref-details .ref-label {
            font-weight: bold;
            white-space: nowrap;
        }
        /* The grid column owns the width here — the inline defaults would otherwise
           re-introduce the ragged starts and ends this box exists to avoid. */
        .ref-details .inline-data {
            min-width: 0;
            margin-left: 0;
            margin-right: 0;
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

        /* FOOTER — the visible barcode and serial are printed ON the security paper,
           about 14mm off the foot of the sheet; the .barcode-group below is the live
           one and is kept at opacity 0.0001. So this band is not content, it is the
           clearance the frame above must not intrude into. 22mm covers the printed
           barcode with a little air; it was 55mm, which reserved far more room than
           the artwork actually uses. flex-shrink is off so the clearance holds even
           when the letter above runs long. */
        .footer-barcode-area {
            height: 22mm;
            flex-shrink: 0;
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

        /* The conditions take the page's slack, rather than the letter sitting tight
           at the top with one lump of empty paper above the Commissioner's line.
           .signature-block's margin-top:auto used to collect all of it; this block
           grows into the frame first and spreads the surplus evenly between the
           conditions, which is where a letter of grant can carry it without looking
           gappy anywhere in particular.

           On a full letter there is no surplus to spread and space-between has
           nothing to do, so the page is exactly what it was. */
        .conditions-list-fixed {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .conditions-list-fixed p {
            margin: 4px 0;
            text-align: justify;
            line-height: 1.4;
        }

        /* Flex items do not collapse margins the way blocks do, so without this the
           4px bottom and 4px top of two adjacent conditions would start stacking to
           8px where block flow gave 4px — about 25px of height conjured out of
           nothing, on a page that is already tight. Bottom margins alone now set the
           spacing, exactly as the collapsed values did.

           Written as two child selectors rather than `> *` so it matches the
           specificity of the `.conditions-list-fixed p` rule above and can override
           it from here; it must also stay after it. */
        .conditions-list-fixed > p,
        .conditions-list-fixed > div {
            margin-top: 0;
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

        /* .sub-item-line is a flex container, and a flex container drops the
           whitespace between its children - so the space either side of a value
           has to be a margin, not a space in the markup. margin-left has always
           been here; the gap AFTER a value was coming from min-width padding the
           figure out, which held only while the text was short. A value long
           enough to fill the box ("8.00 Per Square Meters") ran straight into the
           words after it. */
        .inline-data {
            display: inline-block;
            border-bottom: 1px dotted #000;
            min-width: 45px;
            margin-left: 5px;
            margin-right: 5px;
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

        /* ── Duplicate / Triplicate: office copies, but no longer monochrome ──
           These copies used to be forced fully black & white by a
           grayscale(100%) filter on .content-wrapper. That filter had to go: the
           coat of arms and the red ministry badge must now carry colour on EVERY
           copy, and a CSS filter applies to the whole subtree with no way for a
           descendant to opt out — grayscale cannot be undone on a child.

           So the copies are desaturated element by element instead. What that
           leaves in colour on the office copies, as a consequence of dropping the
           wrapper filter, is the ornate border frame (a border-image, which no
           img rule can reach) and the coat of arms — the latter deliberately.

           The body text of an office copy still reads black: the coloured text
           rules below stay overridden, because grayscale alone would have left
           red as a mid-grey anyway. */

        /* The security paper is a sibling of .content-wrapper and is not part of
           the letter's identity, so office copies still print on plain paper. */
        .copy-bw .security-bg {
            filter: grayscale(100%);
            -webkit-filter: grayscale(100%);
        }
        .copy-bw .supersede-notice {
            color: #000 !important;
        }
        .copy-bw .reissuance-watermark {
            color: rgba(0, 0, 0, 0.13) !important;
        }
        .copy-bw .title-center {
            color: #000 !important;
        }
        /* No .version-label or [data-rofo-badge] override any more: the copy
           label prints in the header red and the badge stays red on all copies. */

        /* ── White Copy: the proof sheet ──────────────────────────────────────
           A draft run off on ordinary white paper, in black and white, for an
           officer to read against the record before a sheet of security stock is
           spent. It is NOT an issued copy, and the rules below exist to keep it
           from being mistaken for one: no coat of arms, no QR, no serial, no copy
           designation, no signature block (all removed in the markup), and the
           word WHITE COPY where the designation would be.

           What is deliberately NOT changed is the geometry. The frame keeps its
           border-image width, the header keeps its height with the arms taken out
           of it, and the signature block leaves its space behind — so the proof
           breaks across pages exactly where the official letter will. A proof that
           reflows is a proof of a different document.

           Colour is taken out element by element rather than with one grayscale
           filter on the wrapper, for the same reason the office copies stopped
           using one: a filter applies to the whole subtree and a child cannot opt
           back out of it. The frame is the one exception — it is a border-image,
           which nothing but a filter can reach. */
        .white-copy .version-label,
        .white-copy .title-center,
        .white-copy .supersede-notice {
            color: #000 !important;
        }
        /* No decorative frame on a proof. It is the printed border of the security
           stock, and a sheet of plain paper carrying it reads as the real thing —
           the one impression this copy must never give.

           Only the border-image is dropped: .ornate-border's border is already
           24px of transparent, so the box keeps exactly the width it has on the
           official letter and the text below breaks in the same places. */
        .white-copy .ornate-border {
            border-image-source: none !important;
        }
        .white-copy [data-rofo-badge] {
            background: #fff !important;
            border: 1.5px solid #000;
        }
        .white-copy [data-rofo-badge] p {
            color: #000 !important;
        }
        .white-copy .reissuance-watermark {
            color: rgba(0, 0, 0, 0.13) !important;
        }
        /* The mark itself: centred across the head of the page, big enough to be
           the first thing read off the sheet, and plain black so it survives a
           black & white printer at full weight.

           Absolute so it occupies no space in the flow — the letter beneath it
           begins exactly where it begins on the official print, which is the whole
           reason to proofread from this sheet at all. */
        .white-copy-mark-block {
            position: absolute;
            top: 8px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 2;
        }
        .white-copy-mark {
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 30px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: 0.28em;
            text-transform: uppercase;
        }
        .white-copy-note {
            margin-top: 3px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #333;
            text-transform: uppercase;
        }
        /* The space the Commissioner's signature block occupied. Kept empty so the
           letter above it sits exactly where it will on the official print. */
        .white-copy-signature-gap {
            padding: 0 40px 14px 40px;
            text-align: center;
            font-size: 9px;
            font-style: italic;
            font-weight: 600;
            color: #6b7280;
            margin-top: auto;
        }
        .white-copy-signature-gap .gap-space {
            height: 62px;
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
        // The proof sheet. Set by LandRofoController::printWhiteCopy(); no query
        // string switches it on, so an official print URL cannot become a proof and
        // a proof URL cannot become an official print.
        $isWhiteCopy    = !empty($isWhiteCopy);

        $isReissuance   = request()->boolean('supersede');
        $reissueSource  = strtolower(trim((string) request('reissue_source', '')));
        // Every re-issued letter, KLAES or pre-KLAES, prints the full set: Original,
        // Duplicate and Triplicate (decided 2026-10-02). A KLAES re-issuance used to
        // be the Original alone.

        // The three passes the Print Manager offers, as one status each:
        //   Batch  — the whole set, one run.
        //   Office — the Duplicate and Triplicate alone (run 2 of a split print,
        //            once the plain paper is in the tray).
        //   <copy> — that copy on its own.
        $requestedStatus = request('status', 'Original');
        $printVersions = ($requestedStatus === 'Batch')
            ? ['Original', 'Duplicate', 'Triplicate']
            : (($requestedStatus === 'Office') ? ['Duplicate', 'Triplicate'] : [$requestedStatus]);

        // A batch printed "by copy" needs all the Originals first, then all the
        // Duplicates, then all the Triplicates — so the caller renders each record
        // once per copy and orders the passes itself. $printVersionsOnly is how it
        // asks for a single copy out of the set.
        if (!empty($printVersionsOnly)) {
            $printVersions = array_values(array_intersect($printVersions, (array) $printVersionsOnly));
        }

        // A White Copy is one copy — front and back — whatever the URL asks for. It
        // is not an Original, a Duplicate or a Triplicate, and running it off three
        // times would only mean three proofs of the same thing. Set last so nothing
        // above can widen it back out.
        if ($isWhiteCopy) {
            $printVersions = ['White Copy'];
        }

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
            // The date keyed in as "Date the Original RofO Was Issued" (form or Print
            // Manager) wins for KLAES and pre-KLAES alike.
            $originalIssuedAt = $recommendation->reissuance_original_date
                ?? $recommendation->rofo_generated_at
                ?? $recommendation->created_at;

            $supersedeOn = optional($originalIssuedAt)->format('jS F, Y') ?? '';
        }

        // Each copy is written in its own colour, which is how the office tells
        // them apart at a glance: red Original, blue Duplicate, green Triplicate.
        // These used to be overridden to black on the office copies; they are not
        // any more, so all three now print in the colours set here.
        $versionColors = [
            'Original' => '#ff0000',
            'Duplicate' => '#0000ff',
            'Triplicate' => '#008000',
        ];
    @endphp

    @foreach($printVersions as $index => $version)
    @php
        // Only the ORIGINAL copy prints in colour — on every print, not just a
        // re-issuance. The Duplicate and Triplicate are office copies and go out
        // black & white. (A CTC is its own document and keeps its colour.)
        // The White Copy is black & white by definition — it prints on ordinary
        // paper on whatever printer is nearest.
        $isBwCopy = $isWhiteCopy || in_array($version, ['Duplicate', 'Triplicate'], true);
        $pageClass = ($isBwCopy ? ' copy-bw' : '') . ($isWhiteCopy ? ' white-copy' : '');
    @endphp
    <!-- PAGE 1 – Signature line uses exact CSS technique, no double lines -->
        {{-- <div class="page-container" id="page1-{{ $index }}" style="{{ $index > 0 ? 'page-break-before: always;' : '' }} background-image: url('/assets/images/pages/backgrand.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat;">     --}}

                 <div class="page-container{{ $pageClass }}" id="page1-{{ $index }}">


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
                {{-- Where the official letter names the copy in its top corner —
                     ORIGINAL, DUPLICATE, TRIPLICATE, over the security serial — the
                     proof says WHITE COPY across the head of the page instead. Not
                     in the corner: a corner is where a note goes, and this is not a
                     note about the sheet, it is what the sheet is. Absolutely
                     positioned so it takes no space of its own and the letter below
                     starts where it starts on the official print. --}}
                @if($isWhiteCopy)
                    <div class="white-copy-mark-block">
                        <div class="white-copy-mark">White Copy</div>
                        <div class="white-copy-note">Proof for vetting — not an official document</div>
                    </div>
                @endif

                <div style="position: absolute; top: 10px; right: 10px; text-align: right; font-weight: bold; font-size: 16px; letter-spacing: 0.35em; z-index: 2;">
                    @if(!$isWhiteCopy && $version !== 'CTC')<span class="version-label" style="color: {{ $versionColors[$version] ?? '#ff0000' }}; text-transform: uppercase;">{{ $version }}</span>@endif

                    @if(!$isWhiteCopy && isset($securityCode))
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
                {{-- The block keeps its height on a White Copy with the arms and the
                     QR taken out of it, so the letter below starts on the same line
                     of the page as it will on the official print. Both are marks of
                     an issued document — the arms are the State's, the QR resolves
                     to a verifiable record — and a proof carries neither. --}}
                <div style="position: relative; margin-bottom: 4px; margin-top: 10px; min-height: 110px;">
                    @unless($isWhiteCopy)
                    <div style="text-align: center;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" alt="Nigeria Seal" style="width: 100px; height: auto; display: inline-block;" />
                    </div>
                    {{-- Prefer the signed KLAES-Q1 token. The legacy payload was
                         $recommendation->tracking_id, which for most rows is a bare
                         sequential number (e.g. 179239) — enumerable by counting and
                         trivially forged, since there is nothing in it to check.

                         Falls back to the old payload when QR signing is not configured
                         (see `php artisan qr:doctor`), so a misconfigured server prints a
                         legacy QR rather than no QR at all. --}}
                    @php
                        $qrData = document_qr_token('ROFO', $recommendation->id ?? null, [
                            'source_table' => 'land_recommendations',
                            'file_number'  => $recommendation->file_number ?? null,
                            'tracking_id'  => $recommendation->tracking_id ?? null,
                        ]) ?: trim((string) ($recommendation->tracking_id ?: $recommendation->file_number));
                    @endphp
                    @if($qrData !== '')
                        <img src="{{ qr_data_uri($qrData, 150) }}" alt="QR" style="position: absolute; left: 40px; top: 50%; transform: translateY(-50%); width: 55px; height: 55px;" />
                    @endif
                    @endunless
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

                    // Every application type derives from an existing file, and those cite
                    // the parent file number in place of the plan number on the
                    // "as per plan No." line. A plain Direct / Conversion record has no old
                    // file number, so it keeps the layout plan number.
                    $oldFileNumber = trim((string) ($recommendation->old_file_number ?? ''));
                    $layoutPlanNo  = trim((string) ($recommendation->layout_plan_no ?? ''));
                    $planNoRef     = $oldFileNumber !== '' ? $oldFileNumber : $layoutPlanNo;

                    // "Use Subdivision Template": a child of a subdivision whose mother file
                    // was never indexed or linked, so it could not be captured through a
                    // subdivision batch and there is no mother record anywhere to read. Its
                    // mother file number is the record's own old_file_number — the same
                    // column a child saved through a real batch keeps it in — except that
                    // this one was typed by hand rather than picked from the register.
                    //
                    // The mother is cited only in the body's "as per plan No." line —
                    // $planNoRef above already prefers old_file_number. The PLOT/PLAN No.
                    // box keeps the plot's own plot and layout plan numbers, the same as
                    // every other record and the batch template.

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
                    {{-- Label / rule pairs, laid out by .ref-details as ONE grid so all
                         four rules start and end on the same x. Each line used to be
                         its own flex row carrying its own min-width, so a rule began
                         wherever its label happened to end and stopped wherever its
                         min-width happened to fall — four labels of four lengths, four
                         rules at four positions. Those min-widths also set this box's
                         minimum width, which capped how much room the "To:" box
                         opposite could be given; the grid drops both problems at once. --}}
                    <div>
                        <div class="bordered-section ref-details">
                            <span class="ref-label">R of O No:</span>
                            <span class="inline-data">{{ $recommendation->file_number }}</span>

                            <span class="ref-label">PLOT/PLAN No:</span>
                            <span class="inline-data">{{ $plotPlanNo }}</span>

                            <span class="ref-label">LOCATION:</span>
                            <span class="inline-data">{{ $printLocation }}</span>

                            <span class="ref-label">DATE OF ISSUE:</span>
                            <span class="inline-data">{{ optional($recommendation->date_issued)->format('Y-m-d') }}</span>
                        </div>
                    </div>
                </div>

                <div class="title-center">
                    TERMS OF OFFER OF GRANT/CONVEYANCE OF APPROVAL
                </div> 

                <!-- CONDITIONS SECTION -->
                <div class="conditions-list-fixed">
                    <p class="condition-item">
                        {{-- The applicant's own application date, and nothing else.
                             This used to print created_at — the day the record was
                             captured in KLAES — so a recommendation with no
                             application date on it still told the applicant their
                             application was dated the day somebody typed it in.
                             Blank when there is none: an empty line on the letter is
                             the truth, a made-up date is not. --}}
                        With reference to your application dated
                        <span class="inline-data" style="min-width: 80px">{{ optional($recommendation->application_date)->format('jS F') }}</span>
                        <span class="inline-data" style="min-width: 30px">{{ optional($recommendation->application_date)->format('Y') }}</span>, I am directed to inform you that the Governor of Kano State has
                        approved the grant of a Right of Occupancy to you over
                        @if(!empty($recommendation->plot_number))
                            plot No <span class="inline-data" style="min-width: 40px">{{ mb_strtoupper(trim((string) $recommendation->plot_number), 'UTF-8') }}</span>
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
                                <span class="inline-data" style="min-width: 80px">{{ $recommendation->ground_rent_label }}</span>
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
                        <strong>8.</strong> The duplicate &amp; triplicate copies of the letter of Grant must be returned duly accepted with the required fees to enable production of C OF O, otherwise the offer lapses.
                    </p>
                </div>

                <!-- SIGNATURE BLOCK -->
                {{-- Off the White Copy entirely. A signature line is what makes a
                     sheet look executed, and a proof that carries one can be signed
                     and passed off as the letter itself — the one misuse this whole
                     stage exists to prevent. The space it occupied is left behind so
                     the page still breaks where the official letter breaks. --}}
                @if($isWhiteCopy)
                <div class="white-copy-signature-gap" spellcheck="false">
                    <div class="gap-space"></div>
                    Signature block omitted — this is a white copy for proofreading only
                </div>
                @else
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
                @endif

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
    <div class="page-container{{ $pageClass }}" id="page2-{{ $index }}" style="page-break-before: always;">
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

                {{-- The applicant's own signature line — off the proof with the
                     Commissioner's. It was kept at first on the reasoning that a line
                     for the applicant is not official execution, which misses the
                     point: a proof carrying any signature line is a sheet that can be
                     signed, and a signed white copy read at a counter looks like an
                     accepted offer. Nothing on this page is proofread by leaving it
                     there — the rules above it are what an officer checks.

                     The space it took is left behind so the page below the note box
                     falls where it does on the official print. --}}
                @if($isWhiteCopy)
                <div class="white-copy-signature-gap" style="margin-top: 60px;">
                    Signature block omitted — white copy for proofreading only
                </div>
                @else
                <div class="signature-row">
                    <div class="signature-item">APPLICANT'S SIGNATURE</div>
                    <div class="signature-item-date">DATE</div>
                </div>
                @endif
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
        {{-- A White Copy is never logged. log-print writes a print_logs row,
             increments rofo_print_count — which is what the Printed tab and the
             "has this been printed" predicate read — and advances the LAAS "RofO
             signed" stage on the applicant's portal. A proof has done none of
             those things, so the handler is simply not attached: printing one
             leaves the record exactly as it was, which is what makes it safe to
             correct the record and print another. --}}
        @unless($isWhiteCopy)
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
        @endunless

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
            {{-- Skipped on a White Copy: these rules carry !important and would
                 put the frame and the red banner back on a proof, which prints
                 with neither. --}}
            @unless($isWhiteCopy)
            var frameUrl = '{{ asset('assets/images/pages/1779539656370(1).png') }}';
            var css = '.ornate-border { border-image-source: url("' + frameUrl + '") !important; }\n';
            document.getElementById('scheme-override').textContent = css;

            // The red ministry banner is carried by every copy — Original,
            // Duplicate and Triplicate alike. Not the White Copy: it prints black
            // on white, and an inline style set here would beat the stylesheet.
            document.querySelectorAll('[data-rofo-badge]').forEach(function(badge) {
                badge.style.background = '#c90202';
            });
            @endunless
        });


    </script>
</body>
</html>