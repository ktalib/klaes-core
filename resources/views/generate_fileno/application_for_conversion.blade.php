<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- A directly commissioned SuA has no mlsfNo of its own; it is filed under
         st_file_no, which is what the sheet is referenced by. --}}
    <title>Application for Conversion @if(isset($batchNo)) - Batch {{ $batchNo }} @else -
    {{ $records->first()->mlsfNo ?: ($records->first()->st_file_no ?? '') }} @endif
    </title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        /* Standalone Prototype Styles */
        @page {
            size: A4;
            margin: 0;
        }

        :root {
            --ministry-green: #2d5a27;
            --ministry-light-green: #e1f0e1;
            --ministry-red: #d00000;
            --text-black: #000000;
            --border-black: #000000;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #525659;
            margin: 0;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .page {
            background: white;
            width: 210mm;
            height: 297mm;
            /* Fixed height for A4 */
            padding: 12mm 20mm;
            /* Reduced top padding */
            box-sizing: border-box;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.5);
            position: relative;
            color: var(--text-black);
            overflow: hidden;
            /* Prevent spillover */
            margin-bottom: 20px;
            page-break-after: always;
            /* Column flow so the footer logo can be pinned to the bottom of the
               content box without leaving the flow (see .footer-logo). */
            display: flex;
            flex-direction: column;
        }

        /* Flex items default to shrinking; keep every block at its natural height. */
        .page > * {
            flex-shrink: 0;
        }

        .page:last-child {
            margin-bottom: 0;
            page-break-after: avoid;
        }

        /* Sidebars */
        .sidebar {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 25px;
            /* Adjust width as needed from screenshot */
            background-color: var(--ministry-green);
            z-index: 10;
        }

        .sidebar-left {
            left: 0;
        }

        .sidebar-right {
            right: 0;
        }

        /* 1. Top Header */
        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            align-items: flex-start;
            margin-bottom: 5px;
        }

        .qr-container {
            width: 85px;
            height: 85px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 2px;
            border: 1px solid #000;
        }

        .qr-container img {
            width: 100%;
            height: 100%;
        }

        .logo-center {
            text-align: center;
            flex-grow: 1;
        }

        .logo-center img {
            width: 95px;
            /* Compacting slightly */
            height: auto;
        }

        .code-meta {
            border: 2px solid var(--border-black);
            border-radius: 4px;
            font-family: Arial, sans-serif;
            min-width: 180px;
        }

        .code-row {
            display: flex;
            border-bottom: 1px solid var(--border-black);
        }

        .code-row:last-child {
            border-bottom: none;
        }

        .code-label {
            padding: 4px 10px;
            font-weight: bold;
            font-size: 14px;
            border-right: 1px solid var(--border-black);
            min-width: 80px;
            text-transform: uppercase;
            background: #fff;
        }

        .code-value {
            padding: 4px 10px;
            font-weight: bold;
            font-size: 14px;
        }

        /* 2. Ministry Branding */
        .ministry-header {
            text-align: center;
            text-align: center;
            margin-bottom: 5px;
        }

        .title-banner {
            background-color: var(--ministry-light-green);
            border: 1px solid #acc6ac;
            padding: 4px 15px;
            display: block;
            border-radius: 4px;
            margin-bottom: 3px;
        }

        .title-banner h1 {
            color: var(--text-black);
            margin: 0;
            font-size: 22px;
            /* Compacting font */
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.5px;
            font-family: 'Arial Black', Gadget, sans-serif;
            white-space: nowrap;
        }

        .ministry-address {
            color: var(--ministry-red);
            font-weight: bold;
            font-size: 13.5px;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
            margin-top: 2px;
        }

        /* 3. Ref & Date Row */
        .meta-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 17px;
        }

        .underline-box {
            border-bottom: 1px solid var(--border-black);
            display: inline-block;
            padding: 0 5px;
            font-weight: bold;
        }

        /* 4. Recipient */
        .recipient-block {
            margin-bottom: 10px;
            font-size: 18px;
            font-weight: bold;
            line-height: 1.3;
        }

        /* 5. Main Red Heading Banner */
        .main-subject-banner {
            border: 3px solid var(--ministry-red);
            padding: 6px;
            text-align: center;
            margin-bottom: 10px;
        }

        .main-subject-banner h2 {
            color: var(--ministry-red);
            margin: 0;
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            line-height: 1.2;
            font-family: Arial, sans-serif;
        }

        /* Each half is placed by its own <br>; never let a long line re-wrap and add
           a third row to the box. */
        .main-subject-banner h2.two-line {
            white-space: nowrap;
        }

        /* 6. Letter Content Body */
        .letter-content {
            font-size: 17.5px;
            font-size: 17.5px;
            line-height: 1.9;
            /* Tightened so the page + footer logo fit inside A4 */
            margin-bottom: 10px;
            text-align: left;
        }

        .data-field {
            border-bottom: 1px solid var(--border-black);
            font-weight: bold;
            padding: 0 2px;
            display: inline-block;
            vertical-align: baseline;
            line-height: 1.2;
            margin: 0 2px;
        }

        /* Acquisition Options Checklist */
        .checklist {
            list-style: none;
            padding-left: 20px;
            margin: 8px 0;
        }

        .checklist li {
            margin-bottom: 3px;
            font-size: 17.5px;
            display: flex;
            align-items: center;
        }

        .check-box {
            width: 18px;
            height: 18px;
            border: 1px solid #000;
            display: inline-block;
            margin-right: 10px;
            text-align: center;
            line-height: 16px;
            font-weight: bold;
            font-family: Arial, sans-serif;
        }

        /* 7. Footer Red Box (Signatures) */
        .signature-container {
            position: relative;
            margin-top: 8px;
            /* Clears the .integrated-title, which hangs 15px below the box. */
            padding-bottom: 14px;
        }

        /* Pinned to the bottom of the page's content box by the auto margin, but
           still in flow — so it can never overlap the signature box above it, and
           the page's fixed 297mm height can never clip it. padding-top is the
           minimum clearance kept when a long letter pushes the box down. */
        .footer-logo {
            margin-top: auto;
            align-self: flex-end;
            padding-top: 5mm;
            text-align: right;
            line-height: 0;
        }

        .footer-logo img {
            max-height: 40px;
            max-width: 60mm;
            width: auto;
            height: auto;
            display: block;
        }

        .signature-box {
            border: 2px solid var(--ministry-red);
            padding: 15px 25px;
            position: relative;
            background: #fff;
        }

        .sig-row-name {
            display: flex;
            align-items: baseline;
            margin-bottom: 18px;
            font-size: 18px;
            font-weight: bold;
        }

        .name-underline {
            flex-grow: 1;
            border-bottom: 2px solid #000;
            margin-left: 10px;
            height: 18px;
        }

        .sig-cols {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            margin-bottom: 12px;
        }

        .sig-field {
            width: 42%;
            text-align: center;
        }

        .field-line {
            border-top: 2px solid #000;
            margin-bottom: 5px;
        }

        .field-label {
            font-weight: bold;
            font-size: 16px;
            font-family: Arial, sans-serif;
        }

        /* Integrated Bottom Title */
        .integrated-title {
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff;
            padding: 0 15px;
            color: #000;
            font-weight: 900;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-family: Arial, sans-serif;
            white-space: nowrap;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 500px;
            opacity: 0.1;
            z-index: 0;
            pointer-events: none;
        }

        .gp-tag {
            position: absolute;
            bottom: 0px;
            right: 0px;
            font-weight: bold;
            font-size: 11px;
            font-family: Arial, sans-serif;
        }

        .watermark-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 60px;
            font-weight: bold;
            color: rgba(255, 0, 0, 0.2);
            /* Red with opacity */
            white-space: nowrap;
            z-index: 0;
            pointer-events: none;
            text-transform: uppercase;
            font-family: Arial, sans-serif;
        }

        /* The File Copy prints black and white; only the Original is in colour.
           Applied on screen too, so what is previewed is what comes out. */
        .page.file-copy {
            filter: grayscale(100%);
            -webkit-filter: grayscale(100%);
        }

        /* Print Override */
        @media print {
            body {
                background: none;
                padding: 0;
                display: block;
            }

            .page {
                box-shadow: none;
                margin: 0;
                border: none;
                page-break-after: always;
            }

            .page:last-child {
                page-break-after: avoid;
            }

            /* Force watermarks/sidebars to render on every printed page */
            .watermark,
            .watermark-text,
            .sidebar,
            .title-banner {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            /* Keep the grayscale on the File Copy: exact colour rendering above would
               otherwise print its banners and sidebars in full colour. */
            .page.file-copy {
                filter: grayscale(100%);
                -webkit-filter: grayscale(100%);
            }
        }
    </style>
</head>

<body>
    @php
        // Every record prints twice: an ORIGINAL and a FILE COPY, each watermarked.
        $copies = [
            ['label' => 'Original', 'slug' => 'original'],
            ['label' => 'File Copy', 'slug' => 'file-copy'],
        ];
    @endphp
    @foreach($records as $record)
        @php
            // A Sectional Titling conversion carries the ST file number on its
            // fileNumber row; that, or an explicit ?source=st, selects the ST wording
            // and makes the ST primary — not the CON mother — the reference.
            $isStRecord = request('source') === 'st' || !empty($record->st_file_no);
        @endphp
        @foreach($copies as $copy)
        <div class="page {{ $copy['slug'] }}">
            <!-- Sidebars -->
            <div class="sidebar sidebar-left"></div>
            <div class="sidebar sidebar-right"></div>

            <!-- Watermark -->
            <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" class="watermark" alt="Watermark"
                onerror="this.src='https://placehold.co/100x100?text=LOGO'">

            <div class="watermark-container">
                <div class="watermark-text">{{ $copy['label'] }}</div>
            </div>

            <!-- 1. Top Header -->
            <div class="top-header">
                <div class="qr-container" id="qrcode-{{ $record->id }}-{{ $copy['slug'] }}"
                    data-tracking-id="{{ $record->tracking_id }}" title="Tracking ID QR Code"></div>
                <div class="logo-center">
                    <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="Logo"
                        onerror="this.src='https://placehold.co/100x100?text=LOGO'">
                </div>
                <div class="code-meta">
                    <div class="code-row">
                        <div class="code-label">Code:</div>
                        <div class="code-value">LAND 13</div>
                    </div>
                    <div class="code-row">
                        <div class="code-label">Serial No.</div>
                        <div class="code-value">{{ str_pad($record->serial_no, 6, '0', STR_PAD_LEFT) }}</div>
                    </div>
                </div>
            </div>

            <!-- 2. Ministry Banner -->
            <div class="ministry-header">
                <div class="title-banner">
                    <h1>Ministry of land and physical planning</h1>
                </div>
                <div class="ministry-address">No. 2 Dr. Bala Mohd. Road, Nassarawa GRA, P.M.B. 3083 KANO-NIGERIA.</div>
            </div>

            <!-- 3. Ref & Date Row -->
            @php
                // A Sectional Titling conversion is referenced by its ST primary, not by
                // the CON mother file the number was raised on.
                $ourRef = $isStRecord
                    ? ($record->st_file_no ?: ($record->mlsfNo ?? ''))
                    : ($record->mlsfNo ?? '');

                // An ST sheet quotes both numbers: the unit's own, then the primary it
                // sits under. Bracketed only when it is genuinely a second number —
                // an ST primary is its own MLS file no and would otherwise print twice.
                $ourRefMls = $isStRecord ? trim((string) ($record->mls_fileno ?? '')) : '';
                if ($ourRefMls !== '' && strcasecmp($ourRefMls, trim((string) $ourRef)) === 0) {
                    $ourRefMls = '';
                }
            @endphp
            <div class="meta-line">
                <div>
                    {{-- An ST sheet brackets the primary file no. Everything else keeps
                         the conversion label it has always shown — and only a conversion
                         label belongs on a conversion document, so a SuA raised by
                         direct allocation must not print "(Direct Allocation)". --}}
                    @php
                        /**
                         * The file this one came out of, bracketed after its own number.
                         *
                         * A file raised by a parcel update — a Subdivision fragment, a
                         * Change of Purpose, a Merger — is only half identified by its own
                         * number: the sheet is read against the mother file, and the LGA has
                         * no way back to it from the sheet alone. related_fileno is a JSON
                         * array on current rows and a plain number on older ones.
                         */
                        $motherRef = '';
                        $rawRelated = trim((string) ($record->related_fileno ?? ''));
                        if ($rawRelated !== '' && $rawRelated !== '[]') {
                            $decoded = json_decode($rawRelated, true);
                            $parts = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                                ? $decoded
                                : explode(',', $rawRelated);
                            foreach ($parts as $part) {
                                $part = trim((string) $part);
                                // Never the file itself — a number quoted against its own
                                // sheet reads as a second file and means nothing.
                                if ($part !== '' && strcasecmp($part, trim((string) $ourRef)) !== 0) {
                                    $motherRef = $part;
                                    break;
                                }
                            }
                        }

                        if ($ourRefMls !== '') {
                            // An ST unit's primary still wins: it is the number the sheet
                            // is filed under, not merely where the parcel came from.
                            $refSuffix = $ourRefMls;
                        } elseif ($motherRef !== '') {
                            $refSuffix = $motherRef;
                        } else {
                            $refSuffix = trim((string) ($record->source_derived ?? ''));
                            $refSuffix = stripos($refSuffix, 'conversion') !== false ? $refSuffix : '';
                        }
                    @endphp
                    Our Ref: <span class="underline-box" style="min-width: 320px;">{{ $ourRef }}@if($refSuffix !== '') ({{ $refSuffix }})@endif</span>
                </div>
                <div>
                    Date: <span class="underline-box" style="min-width: 150px;">{{ date('d/m/Y') }}</span>
                </div>
            </div>

            <!-- 4. Recipient. The Allocation Source confirmed on the print card
                 decides who the sheet goes to: a Local Government source addresses
                 its Chairman, a State Government source the allocating entity. -->
            @php
                $sheetSource = $allocationSource ?? null;
                $sheetEntity = trim((string) ($allocationEntity ?? ''));
                $sheetLga = trim((string) ($record->lga_derived ?? $record->lga ?? ''));
                $isStateAllocation = $sheetSource === 'State Government';

                // A SuA is addressed to a named officer at a named institution, both
                // answered on the print card. Anything else keeps the two blocks the
                // conversion sheet has always used.
                $isSua = ($isSuaSheet ?? false)
                    && trim((string) ($institutionName ?? '')) !== '';
                $sheetAddressee = trim((string) ($addressedTo ?? ''));

                // The parcel and the plot are one number, entered once under Location
                // Details. Only the name for it changes: a council allocation calls it
                // a plot, every other institution calls it a parcel.
                $isLgaSua = $isSua && \App\Services\AllocationSourceResolver::isLocalGovernment(
                    $institutionName ?? null
                );
                $sheetInstitution = \App\Services\AllocationSourceResolver::displayInstitution(
                    $institutionName ?? null,
                    $sheetEntity ?: null
                );

                // A state entity prints as a name over its address — the same shape
                // as the Local Government block, which names the council first.
                $sheetAddress = '';
                if ($isStateAllocation || $isSua) {
                    $parts = array_values(array_filter(array_map(
                        'trim',
                        preg_split('/\r\n|\r|\n|,/', (string) ($allocationAddress ?? ''))
                    ), fn ($part) => $part !== ''));

                    // Sheets written before the labels were dropped still carry them.
                    $parts = array_map(
                        fn ($part) => trim(preg_replace('/^plot\s+|\s+(lga|local government|state)$/i', '', $part)),
                        $parts
                    );
                    $parts = array_values(array_filter($parts, fn ($part) => $part !== ''));

                    if (!$parts || strcasecmp(end($parts), 'Kano') !== 0) {
                        $parts[] = 'Kano';
                    }

                    $sheetAddress = implode(', ', $parts);
                }
            @endphp
            <div class="recipient-block">
                @if($isSua)
                    {{-- The officer first, then the institution, then where it is
                         posted — the shape a SuA letter is written in. --}}
                    The {{ $sheetAddressee ?: '............' }},<br>
                    <span style="text-transform: uppercase;">{{ $sheetInstitution ?: '............' }}</span><br>
                    <span class="underline-box"
                        style="min-width: 420px; padding-left: 0; text-transform: uppercase;">{{ $sheetAddress ?: '............' }}</span>
                @elseif($isStateAllocation)
                    {{-- One heading line — source then the entity it allocated through,
                         e.g. "State Government HOUSING" — with the address ruled beneath
                         it. padding-left is zeroed so both lines start flush with
                         "Our Ref:" above them. --}}
                    <span style="text-transform: uppercase;">{{ trim($sheetSource . ' ' . ($sheetEntity ?: '............')) }}</span><br>
                    <span class="underline-box"
                        style="min-width: 420px; padding-left: 0;">{{ $sheetAddress ?: '............' }}</span>
                @else
                    The Chairman,<br>
                    <span class="underline-box"
                        style="min-width: 280px; border-bottom-width: 2px; text-transform: uppercase;">{{ $sheetEntity ?: ($sheetLga ?: '............') }}</span>
                    Local
                    Government,<br>
                    Kano State.
                @endif
            </div>

            <!-- 5. Subject Banner -->
            <div class="main-subject-banner">
                @if($isStRecord)
                    {{-- Broken after "titling" so the banner stays two lines, like the
                         land variant — the whole phrase on one line wraps to three. --}}
                    <h2 class="two-line">Application for sectional titling<br>statutory right of occupancy</h2>
                @else
                    <h2>Application for conversion of customary to<br>statutory right of occupancy</h2>
                @endif
            </div>

            <!-- 6. Content -->
            <div class="letter-content" style="text-align: left;">
                I am directed to inform you that Alhaji/Malam/Mr. <span class="data-field"
                    style="min-width: 200px;{{ $isSua ? ' text-transform: uppercase;' : '' }}">{{ $record->FileName ?? '................................' }}</span>
                @if($isSua)
                    {{-- A sectional unit is identified by the allocation slip it was
                         raised under, plus the number entered under Location Details —
                         called a plot on a council allocation and a parcel on every
                         other. Nothing about it is a customary claim, so that wording
                         is dropped. --}}
                    has applied to the Ministry for Sectional titling Statutory Right of Occupancy over
                    {{ $isLgaSua ? 'Plot No.' : 'Parcel No.' }}
                    <span class="data-field"
                        style="min-width: 120px;">{{ trim((string) ($record->plot_no ?? '')) ?: '..............' }}</span>
                    at <span class="data-field"
                        style="min-width: 200px;">{{ $sheetLga ?: '................................' }}</span>
                    with allocation slip No <span class="data-field"
                        style="min-width: 120px;">{{ trim((string) ($allocationRefNo ?? '')) ?: '..............' }}</span>
                    You are please accordingly requested to verify the genuineness of this claim and further confirm on how
                    the property was acquired:
                @else
                    has applied to the Ministry for Statutory Right of Occupancy over Plot No.
                    <span class="data-field" style="min-width: 80px;">{{ $record->plot_no ?? '..............' }}</span>
                    at <span class="data-field"
                        style="min-width: 250px;">{{ $sheetLga ?: '................................' }}</span>
                    You are accordingly requested to verify the genuineness of this customary claim and further confirms on how
                    the property was acquired:
                @endif
                <ul class="checklist">
                    <li><span class="check-box">{!! $acquisitionMethod === 'a' ? '&check;' : '&nbsp;' !!}</span> a. By
                        Purchase
                    </li>
                    <li><span class="check-box">{!! $acquisitionMethod === 'b' ? '&check;' : '&nbsp;' !!}</span> b. By
                        Inheritance
                    </li>
                    <li><span class="check-box">{!! $acquisitionMethod === 'c' ? '&check;' : '&nbsp;' !!}</span> c. By Gift
                    </li>
                    <li><span class="check-box">{!! $acquisitionMethod === 'd' ? '&check;' : '&nbsp;' !!}</span> d. Director
                        Local
                        Government Allocation</li>
                    <li><span class="check-box">{!! $acquisitionMethod === 'e' ? '&check;' : '&nbsp;' !!}</span> e. Any
                        other
                        (Specify) @if($acquisitionMethod === 'e' && $specifyOther) <span class="underline-box"
                        style="min-width: 200px;">{{ $specifyOther }}</span> @else
                            .......................................................................... @endif
                    </li>
                </ul>
                @if($isSua)
                    {{-- The allocation slip goes out with the site plan, and what comes
                         back is a confirmation — not the "reply" the land sheet asks for. --}}
                    I attached herewith two copies of site plan of the area and allocation slip for your guidance
                    and further necessary action. One copy is to be returned (Stamped &amp; Signed) along with
                    confirmation / clearance or otherwise
                @else
                    I attached herewith two copies of side plan of the area for your guidance and further necessary action. One
                    copy is to be returned (Stamped & Signed) along with reply, please.
                @endif
            </div>

            <!-- 7. Bottom Signature Box -->
            <div class="signature-container">
                <div class="signature-box">
                    <div class="sig-row-name">
                        Name: <div class="name-underline"></div>
                    </div>
                    <div class="sig-cols">
                        <div class="sig-field">
                            <div class="field-line"></div>
                            <div class="field-label">Rank</div>
                        </div>
                        <div class="sig-field">
                            <div class="field-line"></div>
                            <div class="field-label">Signature</div>
                        </div>
                    </div>
                    <div class="integrated-title">Honourable Commissioner</div>
                </div>

            </div>

            <!-- 8. Footer Logo -->
            <div class="footer-logo" style="text-align: right;">
    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="Logo"
        onerror="this.src='https://placehold.co/120x40?text=LOGO'">
</div>        </div>
        @endforeach
    @endforeach

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.qr-container[data-tracking-id]').forEach(function (el) {
                var trackingId = el.getAttribute('data-tracking-id');
                if (!trackingId) return;
                new QRCode(el, {
                    text: trackingId,
                    width: 75,
                    height: 75,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            });
        });


        //trigger print
        window.onload = function () {
            window.print();
        };
    </script>
</body>


</html>