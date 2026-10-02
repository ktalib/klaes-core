@php
    // Read before <head> because the stationery scan is applied as a CSS
    // background inside the <style> block below. A full URL is used as given;
    // anything else is resolved against the app's own public directory.
    $letterheadImage = (string) config('consent_letter.letterhead_image', '');
    $letterheadUrl = $letterheadImage === ''
        ? ''
        : (preg_match('#^(https?:)?//#i', $letterheadImage) ? $letterheadImage : asset($letterheadImage));
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Consent to Assign Property - {{ $application->file_number }}</title>
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
            font-size: 14.5pt;
            line-height: 1.45;
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
            margin-top: 0.85rem;
        }

        .clause.first {
            margin-top: 0;
        }

        /* Signature rule above the name, as ruled on the approved letter. The
           gap above it is deliberately generous: it is the space the
           Commissioner actually signs into, above the rule. */
        .signature-block {
            margin-top: 5.5rem;
        }

        .signature-rule {
            width: 78mm;
            border-top: 1px solid #000;
            margin-bottom: 0.25rem;
        }

        .signature-block div {
            text-transform: uppercase;
            line-height: 1.32;
        }

        .signature-block .signatory {
            font-weight: bold;
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
            <div><span data-fit-line>RE: APPLICATION FOR CONSENT TO ASSIGN THE PROPERTY</span></div>
            <div><span data-fit-line>COVERED BY CERTIFICATE OF OCCUPANCY: <span
                        class="json-data">{{ $application->file_number }}</span></span></div>
        </div>

        <!-- Clause 1 — the statutory authority. Unnumbered, by house style. -->
        <div class="clause first">
            By virtue of the powers conferred upon the Governor of Kano State by the provisions of sections 9, 21, and
            22 of the Land Use Act, Laws of the Federation of Nigeria, which powers were delegated to me by section 45
            of the Act and further to your application dated
            <span class="json-data">{!! $longDate($application->application_dated) ?: '_____________' !!}</span>
            on the above subject matter.
        </div>

        <!-- Clause 2 — the approval itself. -->
        <div class="clause">
            2. I hereby convey my Approval for Consent to Assign the property with Certificate of Occupancy No.
            <span class="json-data">{{ $application->file_number }}</span> to
            <span class="json-data">{{ strtoupper((string) $application->party_name) }}</span> of
            <span
                class="json-data">{{ rtrim(trim((string) $application->party_address), '.') }}</span>@if ($considerationFigure !== '') for consideration of
                <span
                    class="json-data">{{ $application->consideration_words }} ({{ $currency }}{{ $considerationFigure }})</span>@endif.
        </div>

        <!-- Clause 3 — what the applicant must now produce. -->
        <div class="clause">
            {{-- "Deeds Department" is the approved letter's own wording, so it is
                 written out here rather than drawn from consent_letter.department_name,
                 which reads "Department of Deeds" and is shared with the bill letter. --}}
            3. You are by this letter required to present evidence of payment and Duly Stamped Deed of Assignment
            Document to Deeds Department for registration.
        </div>

        <!-- Clause 4 — the default penalty. The deadline and the daily rate come
             from config/consent_letter.php, never from this template. -->
        <div class="clause">
            4. Please note that if Deed of Assignment instrument are not submitted within the stipulated
            <span class="json-data">{{ $deadlineWords }} ({{ $deadlineDays }})</span> calendar days, a penalty sum of
            <span class="json-data">{{ $currency }}{{ $penaltyFigure }} ({{ $penaltyWords }})</span> shall be charged
            for each day of such default in payment until compliance is attained.
        </div>

        <!-- Signature. The approved letter carries no "Yours faithfully,". -->
        <div class="signature-block">
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
