<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SLTR RofO - {{ $recommendation->sltr_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background-color: #525659;
            font-family: "Times New Roman", Times, serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 0;
            gap: 30px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Plain white page — printed onto the pre-printed MLPP Right of
           Occupancy security paper, so no background scan is rendered. */
        .page-container {
            background-color: white;
            width: 210mm;
            height: 297mm;
            box-shadow: 0 0 30px rgba(0,0,0,0.3);
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Foot of the letter, against the pre-printed security paper ──────
           The sheet this is printed on carries a barcode across its foot, at
           277.5-290mm down a 297mm page. The frame used to end at 283.8mm, so
           it printed straight across the top 6.3mm of that barcode and risked
           making it unscannable. Everything in this group exists to keep the
           letter clear of it, and it applies to page 1 only -- :has() picks the
           letter page by the frame it carries, leaving the acknowledgement,
           which has .simple-margin instead, untouched.

           To preview the letter over a scan of the sheet again, put this back:

               background-image: url("<url of the sheet scan>");
               background-size: 100% 100%;
               background-repeat: no-repeat;
               background-position: center center;

           on the rule below. Use background-size 100% 100%, never a fixed
           210mm 297mm -- in print .page-container becomes height:100vh, and a
           background pinned to 297mm overflows it and gets clipped exactly
           where the barcode sits. */
        .page-container:has(> .ornate-border) > .ornate-border {
            /* flex:1 will not shrink an item below its content, and the content
               was 11.8mm taller than the room it had -- so the frame overflowed
               the spacer and no margin could lift it. This is what lets it move. */
            min-height: 0;

            /* .ornate-border was never a flex container, so .inner-content's
               flex:1 did nothing and it sized to its own content, leaving an
               empty strip at the foot INSIDE the frame. With this the content
               fills the frame and .signature-block's margin-top:auto finally
               puts the signature lines at the bottom, where they belong. */
            display: flex;
            flex-direction: column;

            /* Negative on purpose: it reaches down into the fixed 25mm spacer
               below, closing the gap to the barcode without touching the
               markup. Frame foot lands at 271.1mm, 6.4mm clear of the barcode.
               Raise this number to open that gap, lower it to close it. */
            margin-bottom: -3mm;
        }

        /* The 11.8mm has to come out of the content for any of the above to fit.
           Scaled rather than re-typeset so nothing reflows unpredictably. */
        .page-container:has(> .ornate-border) .inner-content {
            zoom: 0.945;
            padding-bottom: 2px;
        }

        /* Lifted off the frame foot so there is room to sign and date in pen.
           One number: raise it for more room, lower it for less. Nothing else
           moves when it changes. */
        .page-container:has(> .ornate-border) .signature-block {
            padding-bottom: 0;
            margin-bottom: 5mm;
        }

        .ornate-border {
            flex: 1;
            border: 45px solid transparent;
            border-image-source: url("http://app.klaes.ng/storage/template_frames/sltr.jpeg");
            border-image-slice: 160;
            border-image-repeat: round;
            border-image-width: 45px;
            margin: 8mm 8mm 0 8mm;
        }

        .inner-content {
            padding: 15px 25px;
            flex: 1;
            display: flex;
            flex-direction: column;
            font-size: 14.5px;
            line-height: 1.35;
            position: relative;
        }

        .simple-margin {
            margin: 20mm;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .underline { border-bottom: 1px solid #000; display: inline-block; }
        /* .sub-item-line is a flex container, and a flex container drops the
           whitespace between its children - so the space after a value has to be a
           margin, not a space in the markup. It used to come from min-width padding
           the figure out, which held only while the text was short; a value long
           enough to fill the box ran straight into the words after it. */
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
        .bold { font-weight: bold; }

        .original-placeholder {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.35em;
            text-align: right;
            z-index: 2;
            text-transform: uppercase;
        }

        .logo-container { text-align: center; margin-bottom: 4px; margin-top: 10px; }
        .logo-container img { width: 100px; height: auto; }

        .green-header-wrapper { text-align: center; margin-bottom: 8px; }
        .green-header-outer { display: inline-block; border: 2px solid #000; border-radius: 8px; padding: 3px; background: #fff; }
        .green-header {
            background-color: #1a5c3a !important;
            padding: 8px 16px;
            border-radius: 5px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .green-header h1 { font-weight: bold; color: #fff !important; text-transform: uppercase; font-size: 15px; margin: 0; letter-spacing: 0.5px; }

        /* The applicant's address is the one field here with no length limit, and
           this grid is what sets where the rest of the letter starts. Left to
           itself the box grew with the address and carried the whole body down
           the page with it, so a long address pushed the conditions onto a second
           sheet. A fixed height holds the letter still; the address is scaled to
           fit it rather than being allowed to force the box open -- see
           $sltrAddressFont in the markup below. */
        .meta-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 15px; margin: 8px 0; align-items: stretch; min-height: 132px; }
        .meta-grid > div { display: flex; flex-direction: column; }
        .meta-grid > div > .bordered-section { flex: 1; height: 100%; box-sizing: border-box; overflow: hidden; }
        /* The ceiling the scaling aims at: four lines at the smallest step, three
           at the middle ones, one at full size. */
        .to-address { display: block; max-height: 56px; overflow: hidden; word-break: break-word; }
        .bordered-section { border: 2px solid #000; padding: 15px 12px; background-color: #fff; }
        .row { display: flex; margin-bottom: 3px; font-weight: bold; align-items: baseline; }

        .sltr-section { text-align: center; margin-top: 5px; }
        .sltr-title { color: #1a5c3a !important; font-size: 15px; font-weight: bold; text-decoration: underline; margin: 5px 0; }

        .body-text { font-size: 11.5pt; text-align: justify; line-height: 1.4; margin-bottom: 8px; }
        .conditions-list-fixed p { margin: 4px 0; text-align: justify; line-height: 1.4; }
        .condition-item { margin-bottom: 8px; font-size: 10.5pt; text-align: justify; }
        .sub-item { margin-left: 20px; margin-top: 2px; }
        .sub-item-line { display: flex; align-items: baseline; margin-bottom: 2px; }
        .sub-item-label { min-width: 20px; margin-right: 5px; }

        .signature-block {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            padding: 0 20px 10px 20px;
            text-align: center;
            font-weight: bold;
            align-items: flex-end;
        }
        .signature-block > div { display: flex; flex-direction: column; align-items: center; }

        .footer-barcode-area {
            height: 25mm;
            padding: 0 22mm 8mm 22mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            z-index: 5;
            position: relative;
            flex-shrink: 0;
        }
        .qr-code-group { display: flex; flex-direction: column; align-items: center; }
        .qr-img { width: 40px; height: 40px; }

        /* Page 2 */
        .applicant-address-block { display: flex; border: 1px solid #000; margin-bottom: 15px; min-height: 100px; }
        .left-commissioner { padding: 10px; width: 50%; border-right: 1px solid #000; display: flex; flex-direction: column; justify-content: flex-end; }
        .right-address { padding: 10px; width: 50%; }
        .address-line-box { min-height: 20px; border-bottom: 1px dotted #000; display: block; line-height: 1.4; padding-bottom: 3px; margin-top: 8px; font-size: 12px; word-break: break-word; }

        .acceptance-box { border: 1.2px solid #000; padding: 15px; margin-top: 10px; }
        .acceptance-title { text-align: center; text-decoration: underline; font-weight: bold; font-size: 13pt; margin-bottom: 10px; }

        .fee-table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
        .fee-table th, .fee-table td { border: 1px solid #000; padding: 4px 8px; }
        .fee-table .sub-item { padding-left: 25px; margin-left: 0; }

        .surveyor-options { margin-top: 15px; }
        .option { display: flex; align-items: flex-start; margin-bottom: 6px; font-size: 10.5pt; }
        .check-box { width: 18px; height: 18px; border: 1.5px solid #000; margin-right: 12px; flex-shrink: 0; }

        .note-box { border: 1px solid #000; padding: 10px; margin-top: 20px; font-weight: bold; font-size: 11px; }

        /* ── White Copy: the proof sheet ────────────────────────────────────
           A draft on ordinary white paper, in black and white, read against the
           record before a sheet of security stock is spent. The arms, the QR, the
           serial, the copy designation and the signature lines come off in the
           markup; what is left is marked WHITE COPY, centred across the head of the
           page and large enough to be the first thing read off it.

           The geometry is deliberately untouched — the frame keeps its width, the
           header keeps its height, the signature blocks leave their space — so the
           proof breaks across pages exactly where the official letter will. A proof
           that reflows is a proof of a different document. */
        .white-copy .ornate-border { border-image-source: none !important; }
        .white-copy .green-header,
        .white-copy .green-header-outer { background: #fff !important; border: 1px solid #000; }
        .white-copy .green-header h1,
        .white-copy .green-header p { color: #000 !important; }
        .white-copy .sltr-title,
        .white-copy .original-placeholder { color: #000 !important; }

        .white-copy-mark-block {
            position: absolute; top: 8px; left: 0; right: 0;
            text-align: center; z-index: 2;
        }
        .white-copy-mark {
            color: #000; font-family: Arial, Helvetica, sans-serif;
            font-size: 30px; font-weight: 900; line-height: 1.1;
            letter-spacing: 0.28em; text-transform: uppercase;
        }
        .white-copy-note {
            margin-top: 3px; font-family: Arial, Helvetica, sans-serif;
            font-size: 9px; font-weight: 700; letter-spacing: 0.14em;
            color: #333; text-transform: uppercase;
        }
        /* The room the signature blocks occupied. */
        .white-copy-signature-gap {
            text-align: center; font-size: 9px; font-style: italic;
            font-weight: 600; color: #6b7280; padding-top: 62px;
        }

        /* 160px (was 228, originally 60): page two ends well short of the sheet,
           which left the applicant's signature line floating in the middle with a
           hand's depth of blank paper under it. 228 sat it too low; 30% less lifts
           it back up. Nudged rather than pushed to the foot with margin-top:auto,
           because .page-container is a fixed 297mm box with overflow:hidden —
           anything past the bottom is clipped, not printed. */
        .signature-row { display: flex; justify-content: space-between; margin-top: 160px; }
        .signature-item { border-top: 1px solid #000; width: 45%; text-align: center; padding-top: 5px; font-weight: bold; font-size: 11px; }
        .signature-item-date { border-top: 1px solid #000; width: 30%; text-align: center; padding-top: 5px; font-weight: bold; font-size: 11px; }

        .security-line-container { position: relative; width: 280px; height: 40px; margin: 0; overflow: hidden; }
        .security-line-container::after {
            content: "Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning Kano State Ministry of Land and Physical Planning ";
            position: absolute; bottom: 0; left: 0; width: 100%;
            font-size: 3.5px; font-weight: 900; letter-spacing: -0.65px; word-spacing: -3.2px;
            color: #000; white-space: nowrap; overflow: hidden; text-transform: uppercase;
            pointer-events: none; z-index: 2;
            font-family: "Arial Narrow", "Helvetica Condensed", "Courier New", monospace;
            text-align: center; line-height: 1; text-shadow: 0 0.5px 0 #666;
        }

        .date-line { width: 150px; border-top: 2px solid #000; height: 1px; margin-bottom: 8px; }

        .print-btn-container { position: fixed; top: 20px; right: 20px; z-index: 1000; }
        .print-btn { padding: 12px 24px; background: #1a5c3a; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .print-btn:hover { background-color: #143d23; }

        @media print {
            @page { size: A4; margin: 0 !important; }
            body { background: none !important; padding: 0 !important; margin: 0 !important; display: block; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .print-btn-container { display: none !important; }
            #scheme-toolbar { display: none !important; }
            .page-container { box-shadow: none !important; margin: 0 !important; height: 100vh !important; page-break-after: always !important; }
            .page-container:last-child { page-break-after: auto !important; }
        }
    </style>
</head>
<body>

<div class="print-btn-container">
    <button class="print-btn" onclick="window.print()">Print Document</button>
</div>

@php
    // The proof sheet. Set by SltrRofoController::printWhiteCopy(); no query string
    // switches it on, so an official print URL cannot become a proof and a proof URL
    // cannot become an official print.
    $isWhiteCopy = !empty($isWhiteCopy);

    $requestedStatus = request('status', 'Original');
    $isCTCBatch = request('isCTC') == 1;
    // 'Office' is the Duplicate and Triplicate alone — run 2 of a split print,
    // once the plain paper has replaced the security stock in the tray.
    $printVersions = ($requestedStatus === 'Batch')
        ? ['Original', 'Duplicate', 'Triplicate']
        : (($requestedStatus === 'Office') ? ['Duplicate', 'Triplicate'] : [$requestedStatus]);
    $versionColors = [
        'Original'   => '#ff0000',
        'Duplicate'  => '#0000ff',
        'Triplicate' => '#008000',
        'CTC'        => '#ff0000',
    ];
    $qrData = urlencode($recommendation->sltr_number ?? 'SLTR-' . $recommendation->id);
    $rofoDate = $recommendation->rofo_date_generated
        ? $recommendation->rofo_date_generated->format('jS F, Y')
        : now()->format('jS F, Y');

    // DATE OF ISSUE, and nothing else. It used to print rofo_date_generated, falling
    // back to today — so a letter that had never been issued came out carrying a
    // date nobody chose, and one generated months ago printed the generation date as
    // its issue date. Blank when there is none: an empty line on the letter is the
    // truth, an invented date is not, and the White Copy will not print without one.
    $issueDate = $recommendation->date_issued
        ? $recommendation->date_issued->format('jS F, Y')
        : '';
    $appDate = $recommendation->application_date
        ? $recommendation->application_date->format('jS F, Y')
        : '';
    // Security code: generated once per ROFO from its stable identifier only.
    // Same across Original/Duplicate/Triplicate and consistent on reprint.
    $securityCode = str_pad(substr(abs(crc32($recommendation->sltr_number ?? (string) $recommendation->id)), 0, 6), 6, '0', STR_PAD_LEFT);

    // A White Copy is one copy — front and back — whatever the URL asks for. It is
    // not an Original, a Duplicate or a Triplicate. Set last so nothing above can
    // widen it back out.
    if ($isWhiteCopy) {
        $printVersions = ['White Copy'];
    }
@endphp

@foreach($printVersions as $index => $version)
{{-- ========== PAGE 1 ========== --}}
<div class="page-container{{ $isWhiteCopy ? ' white-copy' : '' }}" style="{{ $index > 0 ? 'page-break-before: always;' : '' }}">
    <div class="ornate-border">
        <div class="inner-content">
            {{-- Where the official letter names the copy — ORIGINAL, DUPLICATE,
                 TRIPLICATE, over the security serial — the proof says WHITE COPY
                 across the head of the page instead, and carries no serial: a proof
                 has none to carry. Absolutely positioned so it takes no space of its
                 own and the letter below starts where it starts on the official
                 print. --}}
            @if($isWhiteCopy)
                <div class="white-copy-mark-block">
                    <div class="white-copy-mark">White Copy</div>
                    <div class="white-copy-note">Proof for vetting — not an official document</div>
                </div>
            @else
            <!-- Version + Security Code -->
            <div class="original-placeholder" style="color: {{ $versionColors[$version] ?? '#ff0000' }};">
                {{ $version }}
                <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                    <div style="display: inline-flex; align-items: center; gap: 4px; letter-spacing: normal;">
                        <span style="line-height: 1; color: #334155; display: inline-flex; flex-direction: column; align-items: center; font-weight: 900; font-family: Arial, sans-serif;">
                            <span style="border-bottom: 1.5px solid #334155; padding-bottom: 1px; font-size: 8px;">V</span>
                            <span style="padding-top: 1px; font-size: 8px;">{{ now()->format('y') }}</span>
                        </span>
                        <span style="font-size: 13px; font-weight: 900; letter-spacing: 0.1em; color: #334155; font-family: 'Courier New', monospace;">{{ $securityCode }}</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- The arms are the State's and the QR resolves to a verifiable record,
                 so a proof carries neither. The block keeps its height with them taken
                 out, so the letter below begins on the same line of the page. --}}
            <div class="logo-container" style="position: relative; min-height: 110px;">
                @unless($isWhiteCopy)
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" alt="Coat of Arms" style="margin-bottom: 5px; height: 100px;" />
                <img src="{{ qr_data_uri($qrData, 200) }}" alt="QR" style="position: absolute; left: 40px; top: 50%; transform: translateY(-50%); width: 55px; height: 55px; padding: 2px;" crossorigin="anonymous">
                @endunless
            </div>

            <!-- Green Header Banner -->
            <div class="green-header-wrapper">
                <div class="green-header-outer">
                    <div class="green-header">
                        <h1>KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                        <p style="font-size: 15px; color: #fff; margin: 3px 0 0 0; letter-spacing: 0.3px; text-align: center;">No. 2 Dr Bala Mohammed Road, Kano State, Nigeria</p>
                    </div>
                </div>
            </div>

            @php
                // Sized by length against the width this column actually has (about
                // 215px once the border, the padding and the To: indent are taken
                // off). The steps are tuned so the address lands inside .to-address
                // rather than being clipped by it -- widen them together if the
                // column is ever given more room.
                $sltrAddress     = mb_strtoupper(trim((string) $recommendation->applicant_address), 'UTF-8');
                $sltrAddressLen  = mb_strlen($sltrAddress);
                $sltrAddressFont = $sltrAddressLen > 110 ? 9.5
                    : ($sltrAddressLen > 85 ? 10.5
                    : ($sltrAddressLen > 55 ? 12
                    : ($sltrAddressLen > 30 ? 13.5 : 16)));
            @endphp

            <!-- Meta Grid -->
            <div class="meta-grid">
                <div>
                    <div class="bordered-section" style="padding: 20px 15px;">
                        <div class="row" style="margin-bottom: 25px; align-items: flex-end;">
                            <span style="font-weight: bold; font-size: 16px; margin-right: 8px; white-space: nowrap;">To:</span>
                            <span class="inline-data" style="flex: 1; text-align: left; border-bottom: 1.5px dotted #000; font-size: 16px; padding-bottom: 2px; line-height: 1;">{{ $recommendation->applicant_name }}</span>
                        </div>
                        <div class="row" style="margin-bottom: 10px; align-items: flex-end;">
                            <span style="width: 32px; display: inline-block;"></span>
                            {{-- Upper-cased on the letter, not in the record: the address is
                                 captured however the officer typed it, and a formal letter
                                 addresses in caps. mb_ so a non-ASCII character is not
                                 mangled on the way. --}}
                            <span class="inline-data to-address" style="flex: 1; text-align: left; border-bottom: 1.5px dotted #000; font-size: {{ $sltrAddressFont }}px; padding-bottom: 2px; min-height: 20px; line-height: 1.15;">{{ $sltrAddress }}</span>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="bordered-section" style="padding: 12px 15px;">
                        <div class="row" style="margin-bottom: 8px; align-items: flex-end;">
                            <span style="white-space: nowrap; margin-right: 8px;">SLTR NO:</span>
                            <span class="inline-data" style="flex: 1;">{{ $recommendation->sltr_number }}</span>
                        </div>
                        @php
                            // PLOT NO / PLAN NO. Either may be absent: a plot is
                            // numbered where one was allotted, a TP number where the
                            // parcel sits on a town-planning layout, and some records
                            // carry both. Whatever exists is shown, joined by a slash;
                            // nothing prints when neither does, rather than a bare "/".
                            //
                            // tp_no is read defensively because sltr_recommendations
                            // does not carry the column yet — this stays blank until
                            // one is added and captured, and needs no further change
                            // here when it is.
                            $rofoPlotNo = trim((string) ($recommendation->plot_number ?? ''));
                            $rofoTpNo   = trim((string) ($recommendation->tp_no ?? ''));

                            $rofoPlotPlan = implode(' / ', array_filter([
                                $rofoPlotNo !== '' ? mb_strtoupper($rofoPlotNo, 'UTF-8') : '',
                                $rofoTpNo !== '' ? mb_strtoupper($rofoTpNo, 'UTF-8') : '',
                            ], fn ($part) => $part !== ''));

                            // Blank means "piece of land" — that is what the capture form
                            // tells the officer when it says to leave the field empty, and
                            // it is what the body of this letter already says a line below.
                            // Printing it here too states the parcel's description instead
                            // of leaving a rule an officer could read as an omission.
                            if ($rofoPlotPlan === '') {
                                $rofoPlotPlan = 'PIECE OF LAND';
                            }
                        @endphp
                        <div class="row" style="margin-bottom: 8px; align-items: flex-end;">
                            <span style="white-space: nowrap; margin-right: 8px;">PLOT NO/PLAN NO:</span>
                            <span class="inline-data" style="flex: 1;">{{ $rofoPlotPlan }}</span>
                        </div>
                        {{-- LOCATION sits alongside PLOT NO/PLAN NO rather than being
                             replaced by it: the two answer different questions, and the
                             corrected letter returned by SLTR carries both. --}}
                        <div class="row" style="margin-bottom: 8px; align-items: flex-end;">
                            <span style="white-space: nowrap; margin-right: 8px;">LOCATION:</span>
                            <span class="inline-data" style="flex: 1;">{{ mb_strtoupper(trim((string) $recommendation->location), 'UTF-8') }}</span>
                        </div>
                        <div class="row" style="margin-bottom: 0; align-items: flex-end;">
                            <span style="white-space: nowrap; margin-right: 8px;">DATE OF ISSUE:</span>
                            <span class="inline-data" style="flex: 1;">{{ $issueDate }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SLTR Title -->
            <div class="sltr-section">
                <h2 class="sltr-title">TERMS OF OFFER OF GRANT / CONVEYANCE OF APPROVAL</h2>
            </div>

            <!-- Body Text -->
            <p class="body-text">
                With reference to your application dated
                <span class="inline-data" style="min-width: 120px">{{ $appDate }}</span>,
                I am directed to inform you that the Governor of Kano State has approved the grant of
                Right of Occupancy to you over
                @if(!empty($recommendation->plot_number))
                    {{-- Upper-cased on the way out, not in the record: the source
                         values arrive in mixed case ("KABUGA, Gwale, Kano") and a
                         grant instrument reads as one voice. --}}
                    plot No. <span class="inline-data" style="min-width: 70px">{{ mb_strtoupper(trim((string) $recommendation->plot_number), 'UTF-8') }}</span>
                @else
                    {{-- Set like a filled value, not like prose: "PIECE OF LAND" is
                         the parcel's description standing in for a plot number, so it
                         carries the same caps and dotted rule as the number it
                         replaces rather than reading as part of the sentence. --}}
                    <span class="inline-data" style="min-width: 70px">PIECE OF LAND</span>
                @endif
                situated at
                <span class="inline-data" style="min-width: 130px">{{ mb_strtoupper(trim((string) $recommendation->location), 'UTF-8') }}</span>
                in <span class="inline-data" style="min-width: 130px">{{ mb_strtoupper(trim((string) $recommendation->lga), 'UTF-8') }}</span>
                on the following conditions.
            </p>

            <!-- Conditions -->
            <div class="conditions-list-fixed">
                <div class="condition-item">
                    <strong>1. Payment of:</strong>
                    <div class="sub-item">
                        <div class="sub-item-line">
                            <span class="sub-item-label">(a)</span> Ground rent ₦
                            <span class="inline-data" style="min-width: 200px">{{ $recommendation->ground_rent_label }}</span>
                            P.H.P.A. (Revisable every 5 years)
                        </div>
                        <div class="sub-item-line">
                            <span class="sub-item-label">(b)</span> Processing Fee of ₦
                            <span class="inline-data" style="min-width: 200px">{{ number_format($recommendation->processing_fee, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="condition-item">
                    <div class="sub-item" style="margin-left: 0;">
                        <div class="sub-item-line">
                            <strong style="margin-right: 8px;">2.</strong>
                            <span class="sub-item-label">(a)</span> Term:
                            <span class="inline-data" style="min-width: 30px">{{ $recommendation->term }}</span>
                            Years.
                        </div>
                        <div class="sub-item-line" style="padding-left: 18px;">
                            <span class="sub-item-label">(b)</span> Purpose:
                            <span class="inline-data" style="min-width: 300px">{{ strtoupper($recommendation->purpose_of_clause) }}</span>
                        </div>
                    </div>
                </div>

                <p class="condition-item"><strong>3.</strong> Not to alienate the Right of Occupancy in part or whole without the written consent of the Governor.</p>
                <p class="condition-item"><strong>4.</strong> To be responsible for the development and maintenance of the drainage, landscaping and general beautification of the frontage of the subject property.</p>
                <p class="condition-item"><strong>5.</strong> Not to erect or permit to be erected on the subject land any building or development except in accordance with plans and specifications approved by the State Planning Authority in the case of urban areas or this ministry in the case of rural areas.</p>
                <p class="condition-item"><strong>6.</strong> Compliance with the Statutory Bills and Charges under Systematic Land Titling and Registration (SLTR) Law.</p>
                <p class="condition-item"><strong>7.</strong> The duplicate &amp; triplicate copies of the letter of Grant must be returned within three months from the above date, duly completed with the required fees to enable production of C OF O, otherwise the offer lapses.</p>
            </div>

            <!-- Signature -->
            {{-- Off the proof entirely. A signature line is what makes a sheet look
                 executed, and a proof carrying one can be signed and passed off as the
                 letter itself — the one misuse this stage exists to prevent. The space
                 is left behind so the page still breaks where the official letter
                 breaks. --}}
            @if($isWhiteCopy)
            <div class="white-copy-signature-gap">
                Signature block omitted — white copy for proofreading only
            </div>
            @else
            <div class="signature-block">
                <div style="width: 45%;">
                    <div class="security-line-container"></div>
                    <div style="margin-top: 2px;"><span class="bold">Honourable Commissioner</span></div>
                </div>
                <div style="width: 30%;">
                    <div class="date-line"></div>
                    <div style="margin-top: 0;"><span class="bold">DATE</span></div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <!-- Footer area removed to reflect moved QR -->
    <div style="height: 25mm; padding: 0 22mm 8mm 22mm; flex-shrink: 0;"></div>
</div>

{{-- ========== PAGE 2 ========== --}}
<div class="page-container{{ $isWhiteCopy ? ' white-copy' : '' }}">
    <div class="simple-margin">
        <!-- Address block -->
        <div class="applicant-address-block">
            <div class="left-commissioner">
                <p style="font-size: 14px;"><strong>The Honourable Commissioner</strong></p>
                <p style="font-size: 13px;">Ministry of Land and Physical Planning</p>
                <p style="font-size: 13px;">Kano State.</p>
            </div>
            <div class="right-address">
                <p style="font-size: 11px; margin-bottom: 5px;"><strong>Applicant's Address:</strong></p>
                <div class="address-line-box">{{ mb_strtoupper((string) $recommendation->applicant_address, 'UTF-8') }}</div>
                <div style="margin-top: 10px; font-size: 11px;">
                    <strong>Date:</strong>
                    <span class="inline-data" style="width: 150px;">{{ $rofoDate }}</span>
                </div>
            </div>
        </div>

        <!-- Acceptance box -->
        <div class="acceptance-box">
            <h2 class="acceptance-title">ACCEPTANCE LETTER</h2>
            <p class="body-text" style="font-size: 10.5pt">
                With reference to the Letter of Grant, I hereby accept the terms and conditions of the grant
                of the Right of Occupancy as conveyed to me by your overleaf quoted letter.
            </p>
            <p class="body-text" style="font-size: 10.5pt">
                I will submit my building plans to you for approval before I commence any improvement on
                the Site, and on completion of the improvement, I will get your completion Certificate
                before occupation on the building. I forward herewith.
            </p>

            <table class="fee-table">
                <thead>
                    <tr><th width="60%">Land Use</th><th>Survey/ Processing Fees</th></tr>
                </thead>
                <tbody>
                    <tr><td>a. Residential</td><td></td></tr>
                    <tr><td class="sub-item">i. Systematic</td><td>₦ 10,000.00</td></tr>
                    <tr><td class="sub-item">ii. On demand</td><td>₦ 10,000.00</td></tr>
                    <tr><td>b. Commercial</td><td></td></tr>
                    <tr><td class="sub-item">i. Systematic</td><td>₦ 20,000.00</td></tr>
                    <tr><td class="sub-item">ii. On Demand Within Metro</td><td>₦ 20,000.00</td></tr>
                    <tr><td class="sub-item">iii. On Demand Outside Metro</td><td>₦ 20,000.00</td></tr>
                    <tr><td>c. Warehouse</td><td>₦ 20,000.00</td></tr>
                    <tr><td>d. Above one (1) Hectare</td><td>₦ 20,000.00</td></tr>
                    <tr><td>e. Farmland</td><td>₦ 10,000.00</td></tr>
                    <tr>
                        <td style="text-align: right"><span class="bold">TOTAL</span></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="surveyor-options">
                <div class="option">
                    <div class="check-box" style="{{ $recommendation->rofo_director_survey === 'YES' ? 'background:#000;' : '' }}"></div>
                    <span>I require the Director Survey to carry out the land survey for me</span>
                </div>
                <div class="option">
                    <div class="check-box" style="{{ $recommendation->rofo_licensed_surveyor === 'YES' ? 'background:#000;' : '' }}"></div>
                    <span>I require a licensed Surveyor to carry out the land survey for me</span>
                </div>
            </div>
        </div> 
        
 

        <!-- Note -->
        <div class="note-box">
            NOTE: APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER SIGNING.<br /><br />
            THIS R OF O IS SUBJECT TO VERIFICATION BEFORE ANY STATUTORY PAYMENTS TO REVENUE DEPARTMENT.
        </div>

        {{-- The applicant's own line goes with the Commissioner's: a proof carrying
             any signature line is a sheet that can be signed, and a signed white copy
             read at a counter looks like an accepted offer. --}}
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
    </div>
</div>
@endforeach

<script>
    setTimeout(() => { window.print(); }, 1000);
</script>

<script>
    {{-- A White Copy is never logged: log-print writes a print_logs row and moves
         this RofO onto the Printed side, and a proof has done neither. The handler
         is simply not attached, so printing one leaves the record exactly as it was
         and another can be run after every correction. --}}
    @unless($isWhiteCopy)
    // Log print after load
    window.addEventListener('afterprint', function() {
        fetch('{{ route('sltr-rofos.log-print', $recommendation->id) }}?status={{ request('status', 'Original') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
    });
    @endunless
</script>

<div id="scheme-toolbar" style="display: none;"></div>
<style id="scheme-override"></style>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        {{-- Skipped on a proof: these rules carry !important and would put the
             decorative frame and the green banner back on a sheet the stylesheet has
             just taken them off. --}}
        @unless($isWhiteCopy)
        var frameUrl = 'http://app.klaes.ng/storage/template_frames/sltr.jpeg';
        var css = '.ornate-border { border-image-source: url("' + frameUrl + '") !important; }\n'
                + '.green-header { background-color: #4ebf97 !important; }\n'
                + '.sltr-title { color: #4ebf97 !important; }\n'
                + '@media print { .ornate-border { border-image-source: url("' + frameUrl + '") !important; } }';
        document.getElementById('scheme-override').textContent = css;
        @endunless
    });
</script>
</body>
</html>
