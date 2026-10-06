{{--
    Consent letter, September 2026 approved wording, with the fee schedule.

    Shared by consent_assignment_2026 (Individual to Individual, Individual to
    Company, Company to Company, Gift) and consent_mortgage_2026 (Mortgage).
    The wording is the same across them; what changes is the verb ("Assign" /
    "Mortgage"), the rates and the stamp duty payee, all of which arrive here:

      $application  the consent row
      $fees         ConsentApplicationController::letterFees() — rates, amounts, payee
      $verb         'Assign' or 'Mortgage'
      $closing      'further action' or 'further consideration'

    Layout (letterhead offsets, QR corner, ack sheet) is carried over from
    assignment_2026, which still prints consents captured before transaction
    types existed.
--}}
@php
    // Read before <head> because the stationery scan is applied as a CSS
    // background inside the <style> block below. A full URL is used as given;
    // anything else is resolved against the app's own public directory.
    // $letterheadOverride is passed only by the preview page (?letterhead=1);
    // a real letter always takes the configured value.
    $letterheadImage = (string) ($letterheadOverride ?? config('consent_letter.letterhead_image', ''));
    $letterheadUrl = $letterheadImage === ''
        ? ''
        : (preg_match('#^(https?:)?//#i', $letterheadImage) ? $letterheadImage : asset($letterheadImage));
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Consent to {{ $verb }} Property - {{ $application->file_number }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        @page {
            size: A4 portrait;
            /* No page margin: the letterhead background must print edge-to-edge.
               Content insets come from .page-sheet padding instead. */
            margin: 0;
        }

        /* File number sits BELOW the letterhead's ref box, left-aligned with the
           "Our Ref:" label (83mm). Printing it inside the box on the dotted rule
           did not come out cleanly on paper. The box's bottom border is at 62mm,
           now pulled up to 60mm, which sits slightly over that border. Measured
           off the scan (4798x6735px = 210x297mm). Carried over unchanged from the
           previous letter — the stationery has not moved. */
        :root {
            /* Measured off the letterhead artwork, not estimated:
                 "Our Ref:" label   82.4 - 88.0mm across, 57.5 - 61.3mm down
                 writable rule      89 - 140mm ("Date:" begins at 141.4mm)
                 box bottom border  61.7mm
               The field therefore starts after the label and centres in the
               rule's writable half. The 5mm line is topped at 56mm — trimmed by
               eye from the measured 57.5mm so the number's baseline sits level
               with the label's rather than a shade under it. */
            --ref-left: 89mm;
            --ref-top: 56mm;
            --ref-size: 11.5pt;
            --ref-width: 50mm;
        }

        /* A4 sheet with the pre-printed letterhead artwork as background */
        .page-sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 1.2cm 2.4cm 1.5cm 2.4cm;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            background-color: #fff;
            @if ($letterheadUrl)
                background-image: url('{{ $letterheadUrl }}');
            @endif
            /* Fixed A4 rectangle, NOT 100% 100%: if the letter runs long the
               sheet grows past 297mm, and a stretched background would drag the
               "Our Ref:" rule away from the field pinned on top of it. */
            background-size: 210mm 297mm;
            background-repeat: no-repeat;
            background-position: left top;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        @media screen {
            body {
                background: #e5e7eb !important;
                padding: 20px 0;
            }

            .page-sheet {
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            }
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            /* 13.5pt rather than the 14.5pt of assignment_2026: the fee schedule
               adds four lines, and print is clipped to a single A4 sheet. */
            font-size: 13.5pt;
            line-height: 1.4;
            color: #000;
            margin: 0;
            background-color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        /* Every value read from the application record, so a proofreader can see
           at a glance which words on the page came from the file. */
        .json-data {
            font-weight: bold;
        }

        .print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #006633;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-family: Arial, Helvetica, sans-serif;
        }

        .print-btn:hover {
            background: #004d26;
            transform: translateY(-2px);
        }

        /* Preview-only banner. Never printed, and never rendered for a real
           consent — see the $demo guard below. */
        .demo-banner {
            /* Absolute, not fixed: a fixed banner stays pinned to the top of the
               viewport and covers the head of the letter — including the QR —
               as soon as the page is scrolled. */
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
            background: #b45309;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            text-align: center;
            padding: 8px 16px;
            letter-spacing: 0.02em;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
                margin: 0;
                padding: 0;
            }

            /* Exactly one page: no stray millimetres spilling a lone footer
               onto sheet two. */
            .page-sheet {
                height: 297mm;
                min-height: 0;
                overflow: hidden;
            }
        }

        /* Reserve for the printed letterhead. Its ref box runs from roughly 45mm
           to 66mm down the sheet; 1.2cm padding + 180px + 22px lands the
           recipient just under the box at about 68mm. Anything shorter puts the
           recipient's name inside the box. */
        .letterhead-space {
            min-height: 180px;
            width: 100%;
            margin-bottom: 22px;
            position: relative;
        }

        /* File number typed onto the pre-printed "Our Ref:" rule */
        .our-ref {
            position: absolute;
            top: var(--ref-top);
            left: var(--ref-left);
            width: var(--ref-width);
            height: 5mm;
            line-height: 5mm;
            font-size: var(--ref-size);
            font-weight: bold;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
        }

        /* Recipient on the left, letter date on the right, their first lines
           level with each other — the date no longer sits in a row of its own
           above the address. */
        .address-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }

        .recipient {
            line-height: 1.3;
        }

        .recipient .name {
            font-weight: bold;
            text-transform: uppercase;
        }

        .letter-date {
            white-space: nowrap;
            padding-top: 1.6rem;
        }

        /* Ordinal suffix rides high: "24th September, 2026". */
        sup {
            font-size: 0.62em;
            vertical-align: super;
            line-height: 0;
        }

        /* Subject heading: exactly two underlined lines, never wrapping. Set in
           the body serif to match the letter, not in Arial. */
        .subject {
            text-align: center;
            text-transform: uppercase;
            font-weight: bold;
            line-height: 1.35;
            margin: 1.1rem 0 1rem;
        }

        .subject [data-fit-line] {
            display: inline-block;
            font-weight: 700;
            white-space: nowrap;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        /* Clauses 2 onward are numbered; the opening clause is not, which is the
           house style on the approved letter. */
        .clause {
            text-align: justify;
            text-justify: inter-word;
            margin-top: 0.6rem;
        }

        /* A line set in under its clause (clause 4's stamp duty sentence). */
        /* Numbered ("i") with a hanging indent, so a wrapped second line
           lines up under the text rather than under the numeral. */
        .clause-sub {
            display: flex;
            gap: 0.6rem;
            margin: 0.2rem 0 0 1.2rem;
        }

        /* The stamp duty still owed (table item iv and clause 4 item i) prints
           in red so the outstanding payment stands out. Prints in colour: the
           sheet sets print-color-adjust: exact. */
        .is-outstanding,
        .is-outstanding td,
        .is-outstanding strong,
        .is-outstanding .json-data {
            color: #c00000;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .clause-sub-no {
            flex: 0 0 auto;
        }

        .clause.first {
            margin-top: 0;
        }

        /* Signature rule above the name, as ruled on the approved letter. The
           gap above it is deliberately generous: it is the space the
           Commissioner actually signs into, above the rule. */
        .signature-block {
            margin-top: 5rem;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        /* Clause 3's fee schedule: label, dash, naira figure, laid out as the
           approved letter tabs them. Figures right-aligned so the kobo line up. */
        /* Clause 3, items i-iv: consideration, the two fees paid to the
           Ministry (with their total), and the stamp duty payable to KIRS /
           FIRS. Rows are indented under the clause and referred to by number. */
        .fee-sections {
            border-collapse: collapse;
            margin: 0.3rem 0 0 0;
            width: 100%;
        }

        .fee-sections td {
            padding: 0 0.5rem 0 0;
            vertical-align: baseline;
        }

        .fee-sections .fee-section td {
            font-weight: bold;
            padding-top: 0.2rem;
        }

        /* No column wraps: each item is one line. The table runs a touch
           smaller than the body so the longest note ("Valuation of the
           Property, not to be paid") fits beside its figure. text-align is set
           explicitly because the clause around the table is justified, which
           stretched a wrapped label ("Registration      fees"). */
        .fee-sections {
            font-size: 0.93em;
        }

        .fee-sections td {
            text-align: left;
        }

        .fee-sections .fee-no {
            width: 8mm;
            text-align: right;
            padding-right: 2.5mm;
            white-space: nowrap;
        }

        .fee-sections .fee-label {
            white-space: nowrap;
            padding-right: 3mm;
        }

        .fee-sections .fee-amount {
            text-align: right;
            font-weight: bold;
            white-space: nowrap;
            padding-right: 3mm;
        }

        .fee-sections .fee-note {
            white-space: nowrap;
        }

        /* Items ii and iii and their Total (the fees already paid to the Ministry) are shaded
           so they read apart from the consideration above and the stamp duty
           below. Prints too: the sheet sets print-color-adjust: exact. */
        .fee-sections .fee-paid td {
            background-color: #e5e7eb;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .fee-sections .fee-total .fee-label {
            text-align: right;
        }

        .fee-sections .fee-total .fee-amount span {
            border-bottom: 3px double #000;
        }

        .fee-table {
            border-collapse: collapse;
            margin: 0.2rem 0 0.35rem 2.2rem;
        }

        .fee-table td {
            padding: 0 0.6rem 0 0;
            vertical-align: baseline;
        }

        .fee-table .fee-amount {
            text-align: right;
            font-weight: bold;
            min-width: 36mm;
        }

        .fee-table .fee-total td {
            padding-top: 0.1rem;
        }

        .fee-table .fee-total .fee-amount span {
            border-bottom: 3px double #000;
        }

        .signature-rule {
            width: 78mm;
            border-top: 1px solid #000;
            margin-bottom: 0.25rem;
        }

        /* Centred signature: the name, office, ministry and state centred on
           the page, with the signing rule centred above them. */
        .signature-block.is-centred {
            text-align: center;
        }

        .signature-block.is-centred .signature-rule {
            margin-left: auto;
            margin-right: auto;
        }

        /* Set smaller than the body, as on the original consent letter
           (assignment.blade.php), so the four lines never crowd the foot. */
        .signature-block div {
            text-transform: uppercase;
            font-size: 11.5pt;
            line-height: 1.3;
        }

        .signature-block .signatory {
            font-weight: bold;
            font-size: 12.5pt;
        }

        /* QR in the top-right corner, beside the crest. Absolute, so a long
           letter cannot push it onto a second page.
           Offsets are measured from the sheet's PADDING box, not its paper
           edge, so right:0 lines the code up with the text column's right edge
           An absolute offset resolves against the containing block's PADDING
           box, which runs to the border — so these are distances from the edge
           of the paper, and the sheet's own padding does not inset them. Hence
           the explicit 2.4cm, which lines the code's right edge up with the
           text column instead of hanging it off the edge of the sheet.
           Vertically this column is blank from 4mm to 30mm, where the ministry
           heading begins. The code starts at 8mm — past the roughly 5mm most
           printers cannot reach — and 20mm of code then leaves 2mm of air
           under it. That is the ceiling for this corner: anything larger
           either prints into the heading or off the edge of the paper. A
           caption underneath does not fit, which is why there is none. */
        .qr-block {
            position: absolute;
            top: 8mm;
            right: 2.4cm;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Drawn at 200px and scaled down here so it stays sharp on paper. */
        .qr-block canvas {
            width: 20mm;
            height: 20mm;
            display: block;
        }

        .qr-block .qr-caption {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6.5pt;
            letter-spacing: 0.02em;
            color: #000;
        }

    </style>
</head>

<body>

    @php
        $demo = $demo ?? false;
        $L = fn($key, $default = '') => config('consent_letter.' . $key, $default) ?: $default;

        // "24th September, 2026" with the ordinal suffix raised, as typed on the
        // approved letter.
        $longDate = function ($date) {
            if (!$date) {
                return null;
            }
            $date = \Carbon\Carbon::parse($date);
            return $date->format('j') . '<sup>' . $date->format('S') . '</sup> ' . $date->format('F, Y');
        };

        $currency = $L('currency_symbol', '₦');

        // consideration is a free-text column, so it is read the way the bill
        // calculator reads it — digits only — and then formatted. Anything that
        // is not a number is printed exactly as captured rather than mangled
        // into one, and an empty one drops the clause fragment entirely instead
        // of printing "for consideration of (₦)."
        $considerationRaw = trim((string) $application->consideration);
        $considerationDigits = preg_replace('/[^0-9.]/', '', $considerationRaw);
        $considerationFigure = ($considerationDigits !== '' && is_numeric($considerationDigits))
            ? number_format((float) $considerationDigits, 2)
            : $considerationRaw;

        // QR payload: the file's own tracking id — the identifier the file
        // tracker scans on — looked up the way the bill letter looks it up,
        // unless the caller already resolved one. A configured verify_url turns
        // it into a link to a verification page; without one the code carries
        // the bare id. Falls back to the file number so it is never empty.
        $trackingId = $trackingId
            ?? \App\Models\FileIndexing::where('file_number', $application->file_number)->value('tracking_id');

        $qrValue = $trackingId ?: $application->file_number;

        if ($L('verify_url')) {
            $qrValue = rtrim($L('verify_url'), '/') . '/' . rawurlencode($qrValue);
        }

        // A figure that could not be worked out prints as a blank to fill in,
        // never as ₦0.00 — see letterFees().
        $money = fn($amount) => $amount === null
            ? '_____________'
            : $currency . number_format((float) $amount, 2);
        $rate = fn($value) => \App\Services\ConsentBillCalculator::formatRate($value);

        // "Deed of Assignment" / "Deed of Gift" / "Deed of Mortgage" — the
        // instrument follows the consent type (config consent_letter.deed_names).
        $deedName = (array) config('consent_letter.deed_names', []);
        $deedName = $deedName[trim((string) $application->consent_type)] ?? 'Deed of Assignment';

        $deadlineDays = (int) $L('deadline_days', 84);
        $deadlineWords = ucfirst((string) $L('deadline_days_words', 'eighty-four'));
        $penaltyFigure = number_format((float) $L('penalty_per_day', 100), 2);
        $penaltyWords = (string) $L('penalty_per_day_words', 'One Hundred Naira');
    @endphp

    @if ($demo)
        <div class="demo-banner no-print">
            PREVIEW — sample data, not a real consent. Nothing is saved and no print is logged.
        </div>
    @endif

    <!-- Print Button -->
    <button class="print-btn no-print" id="print-action">
        <i class="fas fa-print"></i> {{ $demo ? 'Preview Print' : 'Print Document' }}
    </button>

    <div class="page-sheet">
        <!-- Reference Number, typed onto the letterhead's "Our Ref:" rule -->
        <div class="our-ref" data-fit-ref>{{ $application->file_number }}</div>

        <!-- Letterhead Space -->
        <div class="letterhead-space"></div>

        <!-- Recipient (left) and letter date (right), first lines level -->
        <div class="address-row">
            <div class="recipient">
                <span class="name">{{ $application->applicant_name }}</span><br>
                {{-- Break the "…, Kano State." tail onto its own line; the stored
                     value may or may not carry a trailing full stop. --}}
                <span>{!! preg_replace('/,\s*([^,]+\s+state)\s*\.?\s*$/i', ',<br>$1.', e(ucfirst(trim((string) $application->applicant_address)))) !!}</span>
            </div>
            <div class="letter-date json-data">{!! $longDate($application->created_at) !!}</div>
        </div>

        <div style="margin-top: 1.1rem;">Sir,</div>

        <!-- Subject -->
        <div class="subject">
            <div><span data-fit-line>RE: APPLICATION FOR CONSENT TO {{ strtoupper($verb) }} THE PROPERTY</span></div>
            <div><span data-fit-line>COVERED BY CERTIFICATE OF OCCUPANCY: <span
                        class="json-data">{{ $application->file_number }}</span></span></div>
        </div>

        <!-- Clause 1 — the statutory authority. Unnumbered, by house style. -->
        <div class="clause first">
            By virtue of the powers conferred upon the Governor of Kano State by the provisions of sections 9, 21, and
            22 of the Land Use Act, Laws of the Federation of Nigeria, which powers were delegated to me by section 45
            of the Act{{ $demo ? ',' : '' }} and further to your application dated
            <span class="json-data">{!! $longDate($application->application_dated) ?: '_____________' !!}</span>
            on the above subject matter.
        </div>

        <!-- Clause 2 — the approval itself. -->
        {{-- The preview drops the "for consideration of …" tail: the consideration
             is moving into clause 3's fee list. Real letters keep it until the
             new wording is signed off; to drop it there too, remove "! $demo". --}}
        <div class="clause">
            2. I hereby convey my Approval for consent to {{ $verb }} the property with Certificate of Occupancy No.
            <span class="json-data">{{ $application->file_number }}</span> to
            <span class="json-data">{{ strtoupper((string) $application->party_name) }}</span> of
            <span
                class="json-data">{{ rtrim(trim((string) $application->party_address), '.') }}</span>@if ($considerationFigure !== '' && ! $demo) for consideration of
                <span
                    class="json-data">{{ $application->consideration_words }} ({{ $currency }}{{ $considerationFigure }})</span>@endif.
        </div>

        <!-- Clause 3 — the fees the Ministry has received, then the stamp duty
             the applicant must pay elsewhere. Stamp duty is not part of the
             total: it goes to KIRS or FIRS according to the transaction type. -->
        {{-- The revised layout shows on the preview page only, for sign-off: an
             unnumbered sentence and the fee table under clause 2, followed by
             3 (penalty) and 4 (outstanding payment). Real letters
             keep the earlier clause 3 / 4 until approved; to switch, drop the
             $demo conditions here and in clauses 3 and 4 below. --}}
        @if ($demo)
            <div class="clause">
                3. The Ministry hereby acknowledges the prior payments listed as items no ii &amp; iii below, and
                also requests the applicant to pay Stamp Duty (as detailed under item iv below);
                <table class="fee-sections">
                    <tr class="fee-paid">
                        <td class="fee-no">i.</td>
                        <td class="fee-label">Consideration (Valuation of the Property)</td>
                        <td class="fee-amount">{{ $considerationFigure !== '' ? $currency . $considerationFigure : '_____________' }}</td>
                        <td class="fee-note">(Not to be paid)</td>
                    </tr>
                    <tr class="fee-paid">
                        <td class="fee-no">ii.</td>
                        <td class="fee-label">Registration fees ({{ $rate($fees['registration_rate']) }})</td>
                        <td class="fee-amount">{{ $money($fees['registration_fee']) }}</td>
                        <td class="fee-note">(Already paid to MOL&amp;PP)</td>
                    </tr>
                    <tr class="fee-paid">
                        <td class="fee-no">iii.</td>
                        <td class="fee-label">Processing fees</td>
                        <td class="fee-amount">{{ $money($fees['processing_fee']) }}</td>
                        <td class="fee-note">(Already paid to MOL&amp;PP)</td>
                    </tr>
                    <tr class="fee-total fee-paid">
                        <td class="fee-no"></td>
                        <td class="fee-label">Total</td>
                        <td class="fee-amount"><span>{{ $money($fees['total']) }}</span></td>
                        <td class="fee-note"></td>
                    </tr>
                    <tr class="is-outstanding">
                        <td class="fee-no">iv.</td>
                        <td class="fee-label">Stamp Duty ({{ $rate($fees['stamp_duty_rate']) }})</td>
                        <td class="fee-amount">{{ $money($fees['stamp_duty_amount']) }}</td>
                        <td class="fee-note">(Outstanding, to be paid to {{ $fees['payee'] }})</td>
                    </tr>
                </table>
            </div>
        @else
            <div class="clause">
                3. The Ministry has acknowledged the following payment
                <table class="fee-table">
                    <tr>
                        <td>i.</td>
                        <td>Registration fees ({{ $rate($fees['registration_rate']) }}):</td>
                        <td>-</td>
                        <td class="fee-amount">{{ $money($fees['registration_fee']) }}</td>
                    </tr>
                    <tr>
                        <td>ii.</td>
                        <td>Processing fees</td>
                        <td>-</td>
                        <td class="fee-amount">{{ $money($fees['processing_fee']) }}</td>
                    </tr>
                    <tr class="fee-total">
                        <td></td>
                        <td style="padding-left: 1.2rem;">Total</td>
                        <td>-</td>
                        <td class="fee-amount"><span>{{ $money($fees['total']) }}</span></td>
                    </tr>
                </table>
                In view of the above you are requested to pay the sum of
                <span class="json-data">{{ $money($fees['stamp_duty_amount']) }}</span> being
                <span class="json-data">{{ $rate($fees['stamp_duty_rate']) }}</span> for Stamp duty
                <strong>MUST</strong> be paid to the <strong>{{ $fees['payee_name'] }}</strong> before {{ $closing }}.
            </div>
        @endif

        @if ($demo)
            <!-- Clause 4 (preview) — the stamp duty (item iv) is still owed. The
                 amount, rate and authority follow the transfer type. -->
            <div class="clause">
                {{-- The authority (KIRS / FIRS) follows the transfer type. --}}
                4. Please be advised that the payment detailed below must be remitted to the
                <strong>{{ $fees['payee_name'] }}</strong> before the Ministry can proceed with further processing;
                <div class="clause-sub is-outstanding">
                    <span class="clause-sub-no">i.</span>
                    <span>The sum of <span class="json-data">{{ \App\Services\ConsentBillCalculator::amountInWords($fees['stamp_duty_amount'] ?? 0) }}
                    ({{ $money($fees['stamp_duty_amount']) }})</span> being
                    <span class="json-data">{{ $rate($fees['stamp_duty_rate']) }}</span> for Stamp duty.</span>
                </div>
            </div>

            <!-- Clause 5 (preview) — the default penalty. The deadline and the
                 daily rate come from config/consent_letter.php. -->
            <div class="clause">
                5. Furthermore, kindly also note that the {{ $deedName }}/Instrument Documents must be submitted within the
                stipulated <span class="json-data">{{ $deadlineWords }} ({{ $deadlineDays }})</span> calendar days,
                failure of which penalty of the sum of
                <span class="json-data">{{ $currency }}{{ $penaltyFigure }} ({{ $penaltyWords }} only)</span> shall be
                charged for each day of default until the last day of compliance.
            </div>
        @else
            <!-- Clause 4 — the default penalty. The deadline and the daily rate
                 come from config/consent_letter.php, never from this template. -->
            <div class="clause">
                4. Please note that if Deed of Assignment/Instrument Documents are not submitted within the stipulated
                <span class="json-data">{{ $deadlineWords }} ({{ $deadlineDays }})</span> calendar days, a penalty sum of
                <span class="json-data">{{ $currency }}{{ $penaltyFigure }} ({{ $penaltyWords }})</span> shall be charged
                for each day of such default in payment until compliance is attained.
            </div>
        @endif

        <!-- Signature. The approved letter carries no "Yours faithfully,". -->
        {{-- Centred on the preview page only, for sign-off before it goes onto
             real letters. To apply it everywhere, drop the $demo condition. --}}
        <div class="signature-block {{ $demo ? 'is-centred' : '' }}">
            <div class="signature-rule"></div>
            {{-- Name only when one is configured: an unsigned letter shows the
                 rule and the office alone, never a placeholder name. --}}
            @if ($L('signatory_name'))
                <div class="signatory json-data">{{ $L('signatory_name') }}</div>
            @endif
            <div>{{ $L('signatory_designation', 'Honorable Commissioner') }}</div>
            <div>{{ $L('ministry_name', 'Ministry of Land and Physical Planning') }}</div>
            <div>{{ $L('state_name', 'Kano State') }}</div>
        </div>

        {{-- No caption at this placement: the corner is 18mm deep and the code
             alone fills it. The caption returns if the block moves to the foot
             of the sheet, where there is room for it. --}}
        <div class="qr-block">
            <canvas class="js-qr" width="200" height="200"></canvas>
        </div>

    </div>

    {{-- Page 2: the acknowledgement the applicant signs when the original
         consent letter is handed over. Included outside .page-sheet — that box
         is clipped to a single A4 page in print, so anything inside it would be
         cut off rather than flowing onto a second sheet. --}}
    @include('consent_applications.templates._ack_sheet')

    <script>
        // Shrink the Our Ref: text until it fits between the colon and the
        // "Date:" field on the pre-printed rule.
        document.querySelectorAll('[data-fit-ref]').forEach(function (el) {
            var size = 11.5;
            while (el.scrollWidth > el.clientWidth && size > 6) {
                size -= 0.25;
                el.style.fontSize = size + 'pt';
            }
        });

        // One A4 page, always. Print clips the sheet to 297mm, so a long name,
        // address or consideration used to push the signature off the bottom.
        // The body text steps down from 13.5pt (no further than 11pt) until the
        // signature sits above the bottom margin. Runs before print as well,
        // in case the window was resized after load.
        function fitLetterToPage() {
            var sheet = document.querySelector('.page-sheet');
            var sig = sheet && sheet.querySelector('.signature-block');
            if (!sig) return;
            var mm = sheet.getBoundingClientRect().width / 210;
            var limit = function () {
                return sheet.getBoundingClientRect().top + 297 * mm
                    - parseFloat(getComputedStyle(sheet).paddingBottom);
            };
            var size = 13.5;
            sheet.style.fontSize = size + 'pt';
            while (sig.getBoundingClientRect().bottom > limit() && size > 11) {
                size -= 0.25;
                sheet.style.fontSize = size + 'pt';
            }
        }
        fitLetterToPage();
        window.addEventListener('beforeprint', fitLetterToPage);

        // Keep the subject heading to exactly two lines, whatever the file number.
        document.querySelectorAll('[data-fit-line]').forEach(function (el) {
            var size = 17, max = el.parentElement.clientWidth;
            while (el.getBoundingClientRect().width > max && size > 9) {
                size -= 0.5;
                el.style.fontSize = size + 'px';
            }
        });

        // Drawn at 200px and scaled down by CSS so it stays sharp at 15mm on
        // paper. If the library did not load the canvas is hidden rather than
        // left as an empty white square on an official letter.
        //
        // Level M, not H: the corner only allows a 15mm code, so the priority
        // is FEWER, BIGGER modules rather than more redundancy. A short id at
        // level M fits a 21x21 grid — about 0.7mm per module at this size —
        // where level H would push it to 25x25 and 0.6mm. M still recovers
        // from 15% damage, which covers a normal photocopy.
        //
        // padding is pinned low for the same reason: QRious otherwise reserves
        // a tenth of the canvas as a white border, shrinking the code itself.
        // The letter is white around it, so the quiet zone survives.
        document.querySelectorAll('canvas.js-qr').forEach(function (canvas) {
            if (typeof QRious === 'undefined') {
                canvas.closest('.qr-block').style.display = 'none';
                return;
            }
            new QRious({
                element: canvas,
                value: @json($qrValue),
                size: 200,
                padding: 6,
                level: 'M'
            });
        });

        @if ($demo)
            // Preview: print the sheet, but never touch the print log — a preview
            // must not mark a consent as printed.
            document.getElementById('print-action').addEventListener('click', function () {
                window.print();
            });
        @else
            document.getElementById('print-action').addEventListener('click', async function () {
                try {
                    // 1. Log the print via AJAX
                    const response = await fetch('{{ route('consent-applications.log-print', $application->id) }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        }
                    });

                    const result = await response.json();

                    if (result.success) {
                        // 2. Trigger the print dialog
                        window.print();

                        // 3. Briefly delay reload so the print dialog is not interrupted
                        setTimeout(() => {
                            window.location.reload();
                        }, 500);
                    } else {
                        alert('Error logging print: ' + result.message);
                    }
                } catch (error) {
                    console.error('Print logging error:', error);
                    alert('An error occurred while preparing for print.');
                }
            });
        @endif
    </script>
</body>

</html>
