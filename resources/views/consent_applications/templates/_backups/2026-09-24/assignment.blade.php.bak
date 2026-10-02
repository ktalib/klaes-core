<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Consent to Assign Property -{{ $application->file_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
           now pulled up to 60mm, which sits slightly over that border. Measured off the scan (4798x6735px = 210x297mm). */
        :root {
            /* box spans the "Our Ref:" label (83mm) to the end of its dotted
               rule (133.3mm), so centring lands the number mid-way under it */
            --ref-left: 83mm;
            --ref-top: 60mm;
            --ref-size: 11.5pt;
            --ref-width: 50.3mm;
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
            /* background-image: url('{{ asset('assets/letterhead/bg-clean.png') }}'); */
            /* Fixed A4 rectangle, NOT 100% 100%: if the letter runs long the
               sheet grows past 297mm, and a stretched background would drag
               the "Our Ref:" rule away from the field pinned on top of it. */
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

        /* Root size scales every rem-based Tailwind size and gap with it.
           Raised to 112% now that the tighter leading has freed up the room. */
        html {
            font-size: 112%;
        }

        body {
            font-family: 'Times New Roman';
            font-size: 14.5pt;
            line-height: 1.42;
            color: #000;
            background-color: white !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

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
        }

        .print-btn:hover {
            background: #004d26;
            transform: translateY(-2px);
        }

        /* Watermark Styles */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(200, 200, 200, 0.25);
            font-weight: bold;
            text-transform: uppercase;
            z-index: -1;
            pointer-events: none;
            white-space: nowrap;
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

        /* Reserve for the printed letterhead. Its box bottom border sits 62mm
           down the sheet; 1.2cm padding + 180px + 22px lands the first line at
           roughly 65mm, leaving a clean gap under it. */
        .letterhead-space {
            min-height: 180px;
            width: 100%;
            margin-bottom: 22px;
            position: relative;
        }

        /* The letterhead artwork now comes from the .page-sheet background,
           so the old on-screen placeholder guide is no longer shown. */

        /* Recipient block: Calibri Italic, with the name in Calibri Bold Italic.
           Carlito is the metric-compatible fallback if Calibri is absent. */
        .recipient {
            font-family: Calibri, Carlito, 'Segoe UI', sans-serif;
            font-style: italic;
            font-weight: normal;
        }

        .recipient .name {
            font-weight: bold;
        }

        /* Subject heading: exactly two underlined lines, never wrapping */
        .subject {
            font-family: Arial, Helvetica, sans-serif;
            text-align: center;
            text-transform: uppercase;
            font-weight: bold;
            font-size: 17px;
            line-height: 1.4;
            margin-top: 1.1rem;
        }

        .subject [data-fit-line] {
            display: inline-block;
            /* restated so nothing inherited can dilute the heading weight */
            font-weight: 700;
            /* Slight outline thickening on top of Arial Bold. Vector, so it
               stays sharp in print. Kept low — Arial Bold is already heavy. */
            -webkit-text-stroke: 0.25px currentColor;
            paint-order: stroke fill;
            white-space: nowrap;
            text-decoration: underline;
            text-underline-offset: 2px;
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

        /* Flow-positioned, not absolute: margin-top:auto still parks it at the
           foot of a short letter, but on a long one it follows the text instead
           of being stranded past the page break and sliced in half. */
        .footer-logos {
            margin-top: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .footer-logo-img {
            height: 45px;
            width: auto;
            object-fit: contain;
        }

        .logo-left {
            height: 45px;
        }
    </style>
</head>

<body class="print:bg-white">

    <!-- Watermark Logic -->
    @php
        $watermarkText = $application->print_count == 0 ? 'FIRST PRINT' : 'SECOND PRINT';
    @endphp
    <!-- <div class="watermark">{{ $watermarkText }}</div> -->

    <!-- Print Button -->
    <button class="print-btn no-print" id="print-action">
        <i class="fas fa-print"></i> Print Document
    </button>

    <div class="page-sheet">
        <!-- Reference Number, typed onto the letterhead's "Our Ref:" rule -->
        <div class="our-ref" data-fit-ref>{{ $application->file_number }}</div>

        <!-- Letterhead Space -->
        <div class="letterhead-space"></div>
 
        <!-- Date -->
        <div class="text-right mt-6 text-sm">
            <strong>{{ $application->created_at->format('jS F, Y') }}</strong>
        </div>

        <!-- Recipient Address -->
        <div class="mt-2 leading-tight recipient">
            <span class="name">{{ strtoupper($application->applicant_name) }}</span><br>
            {{-- Break the "…, Kano State." tail onto its own line; the stored
                 value may or may not carry a trailing full stop. --}}
            <span>{!! preg_replace('/,\s*([^,]+\s+state)\s*\.?\s*$/i', ',<br>$1.', e(ucfirst(trim($application->applicant_address)))) !!}</span>
        </div>

        <div class="mt-4">Sir,</div>

        <!-- Subject -->
        <div class="subject">
            <div><span data-fit-line>RE: APPLICATION FOR CONSENT TO ASSIGN THE PROPERTY COVERED BY</span></div>
            <div><span data-fit-line>CERTIFICATE OF OCCUPANCY: <span
                        class="json-data">{{ $application->file_number }}</span></span></div>
        </div>

        <!-- Body Text -->
        <div class="mt-0 leading-tight text-md text-justify">
            By virtue of the powers conferred upon the Governor of Kano state by the provisions of Section 9, 21 and 22
            of the Land Use Act, Laws of the Federation of Nigeria, Vol.118, and further to your application dated <span
                class="json-data">{{ optional($application->application_dated)->format('jS F, Y') ?: '_____________' }}</span>
            on the above subject matter. I hereby convey my Approval for Consent to Assign the property with Certificate
            of Occupancy No.<span class="json-data">{{ $application->file_number }}</span> to <span
                class="json-data">{{ strtoupper($application->party_name) }}</span> of <span
                class="json-data">{{ rtrim($application->party_address, '.') }}.</span> for a Consideration of <span
                class="json-data">{{ $application->consideration_words }} (₦{{ $application->consideration }})</span>.
            This is subject to the submission of a Deed
            of Assignment and payment of Stamp Duty.
        </div>

        <!-- Closing -->
        <div class="mt-5">Yours faithfully,</div>
<br>
        <!-- Signature Area -->
        <div class="mt-6">
            <div class="font-bold uppercase text-base json-data">
                ALH ABDULJABBAR M. UMAR
            </div>
            <div class="font-bold uppercase text-sm">HON. COMMISSIONER,</div>
            <div class="uppercase text-sm">MINISTRY OF LAND AND PHYSICAL PLANNING,</div>
            <div class="uppercase text-sm">KANO STATE</div>

            <div class="mt-3 pt-2 text-xs text-gray-600">
                <!-- <div>OFFICIAL STAMP:</div>
                <div class="w-32 h-12 border border-gray-400 mt-1 flex items-center justify-center text-gray-500">
                    [Official Stamp Here]
                </div> -->
            </div>
        </div>

        <div class="footer-logos">
            {{-- <img src="{{ asset('assets/logo/Left_Logo.png') }}" class="footer-logo-img logo-left" alt="Left Logo"> --}}
            <img src="{{ asset('assets/logo/klaes1.png') }}" class="footer-logo-img" alt="KLAES Logo" style="margin-left:auto;">
        </div>
    </div>

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

        document.getElementById('print-action').addEventListener('click', async function () {
            try {
                // 1. Log the print via AJAX
                const response = await fetch('{{ route("consent-applications.log-print", $application->id) }}', {
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

                    // 3. Briefly delay reload to ensure print dialog doesn't get interrupted 
                    // (optional, but helps with watermark update)
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
    </script>
</body>

</html>