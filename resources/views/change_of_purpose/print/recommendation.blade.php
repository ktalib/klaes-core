<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change of Purpose Recommendation - {{ $record->file_no ?? '' }}</title>
    <style>
        :root {
            --primary-green: #1b7a3d;
            --accent-red: #a00000;
        }

        * { box-sizing: border-box; }
        body {
            background-color: #525659;
            margin: 0;
            padding: 0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 11.5pt;
        }

        .a4-page {
            background-color: #fff;
            position: relative; /* anchors the CSS letterhead */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            color-adjust: exact;
            width: 210mm;
            min-height: 297mm;
            margin: 10px auto;
            display: flex;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
        }

        .main-container {
            flex: 1;
            /* Clears the letterhead drawn by partials/ministry_letterhead:
               top rule at 38mm, vertical rule at 30mm. */
            padding: 42mm 12mm 10mm 34mm;
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
        }

        .addressee {
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-size: 12pt;
        }

        .para {
            text-align: justify;
            line-height: 1.35;
            margin-bottom: 7px;
        }

        .bold-caps { font-weight: bold; text-transform: uppercase; }
        .strong { font-weight: bold; }

        /* Measurements block — bold, as on the ministry copy. */
        /* Label and value sit on one baseline; the value takes the rest of the
           row instead of being pushed onto its own line by a min-width. */
        .measure {
            font-weight: bold;
            line-height: 1.35;
            margin-bottom: 5px;
            display: flex;
            align-items: baseline;
            gap: 6px;
        }
        .measure-label { white-space: nowrap; }
        .measure .fill { flex: 1; min-width: 0; }

        .rec-point {
            font-weight: bold;
            text-align: justify;
            line-height: 1.35;
            margin: 6px 0 0 0;
        }

        /* Fill-in blanks: values the memo needs that the application record does
           not carry (folio page numbers, plot measurements, term dates). They are
           editable on screen so the officer can complete the sheet before
           printing, and print as a plain ruled blank when left empty. */
        .fill {
            display: inline-block;
            min-width: 60px;
            border-bottom: 1px solid #000;
            line-height: 1.1;
            padding: 0 3px;
            font-weight: bold;
            text-align: center;
        }
        .fill:focus { outline: none; background: #fffbe6; }
        .fill-wide { min-width: 100%; text-align: left; }
        .fill-page { min-width: 42px; }
        /* Measurement values print as plain text — no ruled line under them. */
        .fill-noline { border-bottom: none; }

        /* Signature blocks */
        .sig-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 24px;
            margin: 22px 0 10px;
        }
        .sig-row { display: flex; align-items: flex-end; margin-bottom: 10px; }
        .sig-label { white-space: nowrap; margin-right: 6px; }
        .sig-line { flex: 1; border-bottom: 1px solid #000; height: 15px; }
        .sig-title { font-weight: bold; text-align: center; }

        .section-body { margin-bottom: 6px; }

        /* Date blank on the left, approver's signature blank on the right, with
           both rules struck at the same height. */
        .approve-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 10px;
        }
        .approve-date { flex: 1; margin-bottom: 0; }
        .approver-line {
            width: 76mm;
            flex: none;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        /* The longest title ("The Honourable Commissioner") must read on one
           line, so it never wraps and the rule widens to hold it. */
        .approver-line .sig-title {
            padding-top: 3px;
            white-space: nowrap;
            font-size: 10.5pt;
        }

        .doc-footer-logos {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-top: auto;
            padding-top: 8px;
        }
        .doc-footer-logos img { height: 38px; width: auto; object-fit: contain; }

        .toolbar {
            text-align: center;
            padding: 10px;
            font-family: Arial, Helvetica, sans-serif;
        }
        .toolbar button {
            padding: 8px 22px;
            font-size: 11pt;
            cursor: pointer;
            background: #1a5276;
            color: #fff;
            border: none;
            border-radius: 4px;
        }
        .toolbar span { color: #ddd; font-size: 9pt; display: block; margin-top: 6px; }

        @media print {
            body { background: none; }
            /* The memo is a one-sheet document: pin the box to exactly one A4
               and tighten the spacing so the footer logos stay on it instead of
               tipping a second, near-empty page. */
            .a4-page {
                margin: 0;
                box-shadow: none;
                width: 100%;
                height: 297mm;
                min-height: 0;
                overflow: hidden;
                page-break-after: avoid;
            }
            .main-container { padding: 40mm 12mm 6mm 34mm; }
            .para { margin-bottom: 5px; }
            /* The spare room at the foot of the sheet goes to the signature
               blocks — these are hand-signed, so the rules need writing space. */
            .sig-grid { margin: 16px 0 8px; }
            .sig-row { margin-bottom: 16px; }
            .sig-line { height: 26px; }
            .approve-row { margin-top: 12px; }
            /* Pinned to the sheet, not to the end of the flow — the page is
               clipped at 297mm, so a footer that rides on the content can be
               pushed off the paper as the signature blocks grow. */
            .doc-footer-logos {
                position: absolute;
                right: 12mm;
                bottom: 8mm;
                margin-top: 0;
                padding-top: 0;
            }
            .doc-footer-logos img { height: 30px; }
            .no-print { display: none !important; }
            .fill:empty { min-width: 90px; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

@php
    $currentUse = $currentUseLabel ?? ($record->land_use ?: null);
    $newUse     = $newPurposeLabel ?? ($record->purpose ?: null);
    $location   = normalizeLocationText($record->location) ?: null;
    $lga        = $record->lga ?: null;

    // The memo names the district only — many location strings arrive with the
    // plot number glued on the front ("251A, DAURAWA TARAUNI"), so drop a
    // leading plot token before printing.
    if ($location) {
        $stripped = preg_replace(
            '/^(?:PLOT\s*(?:NO\.?)?\s*)?\d+[A-Z]?\s*(?:,|\s)\s*/i',
            '',
            $location
        );
        $location = trim($stripped, ', ') ?: $location;
    }

    // Memo details captured on the Generate Recommendation card. Anything left
    // blank there still prints as an editable ruled space.
    $commencement = $record->rec_commencement_date
        ? $record->rec_commencement_date->format('jS F Y')
        : null;
@endphp

<div class="a4-page">
    {{-- No CSS letterhead here: this memo goes out on the ministry's
         pre-printed stationery, which already carries the crest, the headings,
         the red spine and the rules. The main container keeps its 42mm top /
         34mm left padding so the typed text still clears them on paper. --}}

    <div class="main-container">
        <div class="addressee">The Permanent Secretary</div>

        <div class="para">
            At page <span class="fill fill-page" contenteditable="true">{{ $record->rec_page_application }}</span> is an application for Change of purpose from
            <span class="strong">{{ $currentUse ?? '................' }}</span> to
            <span class="strong">{{ $newUse ?? '................' }}</span> use in respect of a property covered by tittle number
            NO. <span class="bold-caps">{{ $record->file_no ?: '................' }}</span>
            situated at
            <span class="bold-caps">{{ $location ?? '................' }}</span>
            @if($lga) <span class="bold-caps">in {{ $lga }} Local Government Area</span>@endif.
            submitted by <span class="bold-caps">{{ $record->applicant_name ?: '................' }}</span>.
        </div>

        <div class="para">
            The application was referred to Physical planning department for planning views, consequently; the department
            recommended the application for Change of purpose from
            <span class="strong">{{ $currentUse ?? '................' }}</span> to
            <span class="strong">{{ $newUse ?? '................' }}</span> use. Via its recommendation letter at page
            <span class="fill fill-page" contenteditable="true">{{ $record->rec_page_planning }}</span>. this in view of the fact that, the site is
            obtainable accessible adequate in size requirement and conforms with the surrounding land use.
        </div>

        <div class="para">
            However, this recommendation is based on the recommended site plan at page
            <span class="fill fill-page" contenteditable="true">{{ $record->rec_page_site_plan }}</span> and back cover with the following measurements
        </div>

        <div class="measure">
            <span class="measure-label">Part A =</span>
            <span class="fill fill-noline" style="text-align:left;" contenteditable="true">{{ $record->rec_measurement_a }}</span>
        </div>
        {{-- Part B only appears on files split into two parts; hide the ruled line
             when the memo details card left it empty. --}}
        @if($record->rec_measurement_b || !$record->rec_measurement_a)
        <div class="measure">
            <span class="measure-label">Part B =</span>
            <span class="fill fill-noline" style="text-align:left;" contenteditable="true">{{ $record->rec_measurement_b }}</span>
        </div>
        @endif

        <div class="para">
            Meanwhile, the tittle was granted for <span class="strong">{{ $currentUse ?? '................' }}</span> purpose to
            <span class="strong">{{ $newUse ?? '................' }}</span> for a term of
            <span class="fill fill-page" contenteditable="true">{{ $record->rec_term_years }}</span> years commencing from
            <span class="fill" contenteditable="true">{{ $commencement }}</span> AND now has the residual term of
            <span class="fill fill-page" contenteditable="true">{{ $record->rec_residual_years }}</span> years to expire.
        </div>

        <div class="para">
            In view of the above, you may kindly wish to recommend the following for <span class="strong">approval</span>
            of the <span class="strong">Honourable commissioner</span>
        </div>

        <div class="rec-point">
            I)&nbsp; consider and above the application for change of purpose from
            {{ $currentUse ?? '................' }} to {{ $newUse ?? '................' }} use at
            {{ $location ?? '................' }}@if($lga) in {{ $lga }} local government Area @endif covered by
            certificate of occupancy NO.{{ $record->file_no ?: '................' }}
            in favour of {{ $record->applicant_name ?: '................' }} please
        </div>

        {{-- DIRECTOR DEEDS --}}
        <div class="sig-grid">
            <div>
                <div class="sig-row"><span class="sig-label">Name:-</span><span class="sig-line"></span></div>
                <div class="sig-row"><span class="sig-label">Rank:-</span><span class="sig-line"></span></div>
                <div class="sig-row"><span class="sig-label">Sign:-</span><span class="sig-line"></span></div>
                <div class="sig-row"><span class="sig-label">Date:-</span><span class="sig-line"></span></div>
            </div>
            <div>
                <div class="sig-row"><span class="sig-label">countersign:</span><span class="sig-line"></span></div>
                <div class="sig-title">Director Deeds</div>
                <div class="sig-row" style="margin-top:14px;"><span class="sig-label">Date:</span><span class="sig-line"></span></div>
            </div>
        </div>

        {{-- Addressed to the Honourable Commissioner, signed by the Permanent Secretary --}}
        <div class="addressee">The Honourable Commissioner</div>
        <div class="section-body">The application is hereby recommended for your kind approval, please.</div>
        <div class="approve-row">
            <div class="sig-row approve-date">
                <span class="sig-label">Date:</span><span class="sig-line"></span>
                <span style="margin-left:6px;">{{ date('Y') }}.</span>
            </div>
            <div class="approver-line">
                <div class="sig-line"></div>
                <div class="sig-title">Permanent Secretary</div>
            </div>
        </div>

        {{-- Addressed to the Permanent Secretary, signed by the Honourable Commissioner --}}
        <div class="addressee" style="margin-top:20px;">Permanent Secretary</div>
        <div class="section-body">The application is hereby APPROVED/NOT APPROVED.</div>
        <div class="approve-row">
            <div class="sig-row approve-date">
                <span class="sig-label">Date:</span><span class="sig-line"></span>
                <span style="margin-left:6px;">{{ date('Y') }}.</span>
            </div>
            <div class="approver-line">
                <div class="sig-line"></div>
                <div class="sig-title">The Honourable Commissioner</div>
            </div>
        </div>

        <div class="doc-footer-logos">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo">
        </div>
    </div>
</div>

<div class="toolbar no-print">
    <button onclick="window.print()">Print Document</button>
    <span>Click the ruled blanks (page numbers, measurements, term) to fill them in before printing.</span>
</div>

</body>
</html>
