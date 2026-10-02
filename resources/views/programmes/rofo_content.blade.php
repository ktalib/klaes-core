@php
    $isPrintRoute = $isPrintRoute ?? false;
    $autoPrint = $autoPrint ?? false;
    $bodyClasses = trim('bg-gray-100 p-4 md:p-8 ' . ($isPrintRoute ? 'print-mode' : ''));

    // The proof sheet. Set by RofoController::printWhiteCopy(); no query string
    // switches it on, so an official print URL cannot become a proof and a proof URL
    // cannot become an official print.
    $isWhiteCopy = !empty($isWhiteCopy);

    $currentWatermark = strtoupper($currentWatermark ?? 'ORIGINAL');
    $watermarkPalette = [
        'ORIGINAL'           => '#ff0000',   // Red
        'DUPLICATE'          => '#0000ff',   // Blue
        'TRIPLICATE'         => '#008000',   // Green
    ];
    $watermarkColor = $watermarkPalette[$currentWatermark] ?? '#ff0000';

    // Normalize input to batchRofos
    if (!isset($batchRofos)) {
        // Fallback for single view mode
        $batchRofos = [
            [
                'subApp' => $rofo ?? null,
                'unitRofoData' => $rofoData ?? null,
                'finalRofoData' => $finalRofoData ?? [],
                'unitLandAdmin' => $landAdmin ?? null,
                'totalYears' => $totalYears ?? 40,
                'residualYears' => $residualYears ?? 0,
                'pageNo' => $pageNo ?? '01'
            ]
        ];
    }
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ROFO </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style id="rofo-inline-styles">
        :root {
            --rofo-page-width: 210mm;
        }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12px;
            line-height: 1.25;
            color: #111827;
        }

        .checkbox {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 1px solid #000;
            margin-right: 8px;
            vertical-align: middle;
        }

        .rofo-terms-heading {
            letter-spacing: 0.02em;
            text-decoration: underline;
        }

        .terms-offer-box {
            margin-top: 8px;
            margin-bottom: 8px;
            padding: 0.5rem;
        }

        .terms-offer-box ol {
            font-size: 11px;
            line-height: 1.2;
        }

        .terms-offer-box ol li {
            margin-bottom: 0.15rem;
        }

        .terms-offer-box .signature-grid {
            margin-top: 1.25rem;
        }

        /* ── White Copy: the proof sheet ────────────────────────────────────
           A draft on ordinary white paper, in black and white, read against the
           record before a sheet of security stock is spent. The arms, the QR, the
           serial, the copy designation and the signature blocks come off in the
           markup; what is left is marked WHITE COPY, centred across the head of the
           page and large enough to be the first thing read off it.

           Geometry untouched — the frame keeps its width, the header keeps its
           height, the signature blocks leave their space — so the proof breaks
           across pages exactly where the official letter will. */
        /* The decorative frame is painted by a border-image on ::before, which a
           border-color rule cannot reach — that was the earlier attempt, and it left
           the green border exactly where it was. Dropping the image is enough: the
           20px border underneath is already transparent, and the padding that
           positions the letter lives on the parent, so the geometry is untouched.

           It comes off because it is the printed border of the security stock. A
           sheet of plain paper carrying it reads as the real thing, which is the one
           impression a proof must never give. */
        .white-copy .st-rofo-green-frame::before {
            border-image-source: none !important;
            border-image-width: 0 !important;
        }

        /* Black on white, the whole sheet. Named selectors were not enough here
           either: the letter carries accent colours of its own and the ministry
           banner is navy with white text, so the proof paints everything black and
           then puts back only what has to stay drawn.

           Not a grayscale filter: that turns the accents into a mid-grey that prints
           faint, and this sheet exists to be read. */
        .white-copy,
        .white-copy * { color: #000 !important; }

        /* The navy ministry banner: white box, black rule, black text. Its inline
           background is overridden here and the scheme switcher is told to leave a
           proof alone (see applyScheme). */
        .white-copy [data-rofo-badge] {
            background: #fff !important;
            border: 1px solid #000;
        }

        /* Nothing on a proof should offer to restyle the official frame. */
        .white-copy #scheme-toolbar { display: none !important; }

        /* The images that remain — the KLAES footer logo, the barcode — go
           monochrome. The text is forced black above, but colour in an image is not
           text and would still come out of a colour printer in colour. A filter is
           right here where it was wrong for text: there is no faint-grey reading
           problem in a logo. */
        .white-copy img {
            filter: grayscale(100%);
            -webkit-filter: grayscale(100%);
        }

        .white-copy-mark-block {
            position: absolute;
            top: 8px;
            left: 0;
            right: 0;
            text-align: center;
            z-index: 5;
        }

        .white-copy-mark {
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 26px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: 0.26em;
            text-transform: uppercase;
        }

        .white-copy-note {
            margin-top: 3px;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 0.14em;
            color: #333;
            text-transform: uppercase;
        }

        /* The room the signature blocks occupied. */
        .white-copy-signature-gap {
            text-align: center;
            font-size: 8px;
            font-style: italic;
            font-weight: 600;
            color: #6b7280;
            padding: 40px 30px 10px 30px;
        }

        .acceptance-letter-container {
            margin-bottom: 18px;
        }

        .acceptance-spacer {
            height: 16mm;
        }

        .st-rofo-qr-footer {
            position: absolute;
            right: 9mm;
            bottom: 8mm;
            width: 40px;
            height: 40px;
            z-index: 2;
            background: #fff;
            padding: 1px;
        }

        .original-placeholder {
            position: absolute !important;
            top: 14mm;
            right: 14mm;
            left: auto;
            transform: none;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.35em;
            text-align: right;
            z-index: 3;
        }

        #first-page {
             /* background-image: url('{{ asset('images/rofo_first_page_bg.jpg') }}'); */
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: center;
            background-color: #ffffff;
            position: relative;
            width: 210mm;
            height: 297mm;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            overflow: hidden;
        }

        .st-rofo-green-frame {
            position: relative;
            flex: 1;
            margin: 6mm 8mm 10mm 8mm;
            padding: 12mm 12mm 3mm 12mm;
        }

        .st-rofo-green-frame::before {
            content: '';
            position: absolute;
            inset: 0;
            border: 20px solid transparent;
            border-image-source: url("http://app.klaes.ng/storage/template_frames/sltr.jpeg");
            border-image-slice: 160;
            border-image-repeat: round;
            border-image-width: 45px;
            pointer-events: none;
            z-index: 0;
            box-sizing: border-box;
        }

        .st-rofo-green-frame > * {
            position: relative;
            z-index: 1;
        }

        .st-rofo-footer {
            height: 12mm;
            padding: 1mm 15mm 3mm 15mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            z-index: 5;
            position: relative;
            flex-shrink: 0;
        }

        .acceptance-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
            align-items: stretch;
            margin-bottom: 2rem;
        }

        .commissioner-box,
        .date-box {
            min-height: 110px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .date-box-lines {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.75rem;
        }

        .date-box-lines .line {
            height: 1px;
            width: 100%;
            background-color: #000000;
        }

        body.print-mode {
            background-color: #f3f4f6;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1rem;
            gap: 2rem;
        }

        body.print-mode .rofo-wrapper {
            width: var(--rofo-page-width);
            max-width: var(--rofo-page-width);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        table {
            font-size: 0.95em;
        }

        /* Print-specific styles */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            html,
            body {
                width: 210mm;
                min-height: 297mm;
                margin: 0;
                padding: 0;
            }

            body {
                background-color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color: #000000 !important;
                padding: 0 !important;
                font-size: 12px !important;
                line-height: 1.3 !important;
            }

            .no-print,
            a[href="javascript:history.back()"] {
                display: none !important;
            }

            body {
                display: block !important;
                background-color: #ffffff !important;
            }

            .rofo-wrapper {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }

            #first-page {
                width: 210mm !important;
                height: 297mm !important;
                overflow: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                page-break-after: always !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                display: flex !important;
                flex-direction: column !important;
                  /* background-image: url('{{ asset('images/rofo_first_page_bg.jpg') }}') !important;   */
                background-size: cover !important;
                background-repeat: no-repeat !important;
                background-position: center !important;
                position: relative !important;
            }

            #second-page {
                width: 210mm !important;
                height: 297mm !important;
                overflow: hidden !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                page-break-inside: avoid !important;
                padding: 10mm !important;
                background-color: #ffffff !important;
                color: #000000 !important;
            }

            .st-rofo-green-frame {
                position: relative !important;
                flex: 1 !important;
                margin: 6mm 8mm 10mm 8mm !important;
                padding: 12mm 12mm 3mm 12mm !important;
            }

            .st-rofo-green-frame::before {
                content: '' !important;
                position: absolute !important;
                inset: 0 !important;
                border: 20px solid transparent !important;
                border-image-source: url("http://app.klaes.ng/storage/template_frames/sltr.jpeg") !important;
                border-image-slice: 160 !important;
                border-image-repeat: round !important;
                border-image-width: 45px !important;
                pointer-events: none !important;
                z-index: 0 !important;
                box-sizing: border-box !important;
            }

            .st-rofo-green-frame > * {
                position: relative !important;
                z-index: 1 !important;
            }

            .st-rofo-footer {
                height: 12mm !important;
                padding: 1mm 15mm 3mm 15mm !important;
                flex-shrink: 0 !important;
            }

            #first-page>*,
            #second-page>* {
                position: relative !important;
                z-index: 1;
            }

            #second-page {
                page-break-after: auto;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                font-size: 0.85em !important;
            }

            th,
            td {
                border-color: #000000 !important;
                color: #000000 !important;
                padding: 0.25rem !important;
            }

            .acceptance-meta {
                display: grid !important;
                grid-template-columns: 280px 1fr !important;
                gap: 1.5rem !important;
                align-items: flex-end !important;
            }

            #second-page>div:last-child {
                display: flex !important;
                flex-direction: row !important;
                gap: 0 !important;
            }

            #second-page>div:last-child>div:first-child {
                width: 70% !important;
                flex: none !important;
            }

            #second-page>div:last-child>div:last-child {
                width: 30% !important;
                flex: none !important;
            }

            ol {
                margin-bottom: 0.5rem !important;
            }

            ol li {
                margin-bottom: 0.15rem !important;
                line-height: 1.3 !important;
            }

            .rofo-terms-heading {
                font-size: 0.95rem !important;
                margin-bottom: 0.4rem !important;
            }

            p {
                margin-bottom: 0.4rem !important;
            }

            .terms-offer-box {
                margin-top: 4px !important;
                margin-bottom: 11px !important;
                padding: 0.9rem !important;
            }

            .terms-offer-box ol {
                font-size: 0.8rem !important;
                line-height: 1.1 !important;
            }

            .terms-offer-box ol li {
                margin-bottom: 0.18rem !important;
            }

            .terms-offer-box .signature-grid {
                margin-top: 1rem !important;
            }

            .acceptance-letter-container {
                margin-bottom: 16px !important;
            }

            .acceptance-spacer {
                height: 12mm !important;
            }

            .st-rofo-qr-footer {
                position: absolute !important;
                right: 9mm !important;
                bottom: 8mm !important;
                width: 40px !important;
                height: 40px !important;
                z-index: 2 !important;
                background: #fff !important;
                padding: 1px !important;
            }

            .original-placeholder {
                position: absolute !important;
                top: 14mm !important;
                right: 14mm !important;
                left: auto !important;
                transform: none !important;
                font-size: 16px !important;
                text-align: right !important;
                z-index: 3 !important;
            }
        }

        /* Additional styles for the document */
        .print-buttons {
            margin: 1rem 0;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Preview modal styles */
        #preview-modal {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>

<body class="{{ $bodyClasses }}">

    @unless($isPrintRoute)
        <a href="javascript:history.back()"
            class="bg-blue-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
        </a>
        <br>
    @endunless
    @foreach($batchRofos as $context)
        @php
            $rofo = (object) $context['subApp'];
            $rofoData = (object) $context['unitRofoData'];
            $finalRofoData = $context['finalRofoData'] ?? [];
            $landAdmin = (object) ($context['unitLandAdmin'] ?? []);
            $totalYears = $context['totalYears'] ?? 40;
            $residualYears = $context['residualYears'] ?? 0;
            $pageNo = $context['pageNo'] ?? '01';

            // Per-entry watermark: use entry-level watermark if available, else global
            $entryWatermark = strtoupper($context['watermark'] ?? $currentWatermark);
            $entryWatermarkColor = $watermarkPalette[$entryWatermark] ?? '#b91c1c';

            $subApplication = $rofo;
            $subApplicationId = $rofo->id;

            $unitNumberValue = trim($subApplication->unit_number ?? '');
            $schemeNumberValue = trim($subApplication->scheme_no ?? '');
            $unitTypeValue = trim($subApplication->unit_type ?? '');
            $unitLineHasData = $unitNumberValue !== '' || $schemeNumberValue !== '' || $unitTypeValue !== '';

            $unitNumberDisplay = $unitNumberValue !== ''
                ? strtoupper($unitNumberValue)
                : ($rofoData->unit_number ?? $rofo->unit_number ?? '-');

            // Shared utilities logic
            $sharedUtilitiesRaw = null;
            $sharedUtilities = [];

            if ($subApplicationId) {
                // We use Query Builder here to avoid potential N+1 if we don't have this relationship eager loaded
                // For batch printing ~50 units, this is acceptable.
                $jsir = \DB::connection('sqlsrv')
                    ->table('joint_site_inspection_reports')
                    ->where('sub_application_id', $subApplicationId)
                    ->select('shared_utilities')
                    ->first();

                $sharedUtilitiesRaw = $jsir->shared_utilities ?? null;

                if ($sharedUtilitiesRaw) {
                    $decoded = json_decode($sharedUtilitiesRaw, true);
                    if (is_array($decoded)) {
                        $cleaned = [];
                        foreach ($decoded as $item) {
                            if (!$item)
                                continue;
                            $parts = preg_split('/\s*,\s*/', $item);
                            foreach ($parts as $part) {
                                $part = trim($part);
                                if ($part !== '') {
                                    $cleaned[] = ucfirst(strtolower($part));
                                }
                            }
                        }
                        $cleaned = array_values(array_unique($cleaned));
                        $sharedUtilities = array_slice($cleaned, 0, 2);
                    }
                }
            }
        @endphp

        <div id="rofo-print-root" class="max-w-4xl mx-auto bg-white shadow-md rofo-wrapper{{ $isWhiteCopy ? ' white-copy' : '' }}">
            <!-- First Page -->
            <div id="first-page">
              <div class="st-rofo-green-frame">
                {{-- Where the official letter names the copy — ORIGINAL, DUPLICATE,
                     TRIPLICATE, over the security serial — the proof says WHITE COPY
                     across the head of the page instead, and carries no serial: a
                     proof has none to carry. --}}
                @if($isWhiteCopy)
                <div class="white-copy-mark-block">
                    <div class="white-copy-mark">White Copy</div>
                    <div class="white-copy-note">Proof for vetting — not an official document</div>
                </div>
                @else
                <div class="original-placeholder" style="color: {{ $entryWatermarkColor }};">
                    {{ $entryWatermark }}
                    @if(isset($context['securityCode']))
                        @php
                            $sc = app(\App\Services\SecurityCodeService::class)->formatForDisplay($context['securityCode']->code);
                        @endphp
                        <div style="display: flex; justify-content: flex-end; margin-top: 4px;">
                            <div style="display: inline-flex; align-items: center; gap: 4px; letter-spacing: normal;">
                                <span style="line-height: 1; color: #334155; display: inline-flex; flex-direction: column; align-items: center; font-weight: 900;">
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
                @endif
                <!-- Nigerian Coat of Arms + QR Code -->
                @php
                    $qrData = ($rofoData->fileno ?? $rofo->fileno ?? 'N/A');
                    $trackingId = \DB::connection('sqlsrv')
                        ->table('file_indexings')
                        ->where('file_number', $qrData)
                        ->value('tracking_id');
                    $qrData = $trackingId ?? $qrData;
                    $qrUrl = qr_data_uri($qrData, 150);
                @endphp
                {{-- The arms are the State's and the QR resolves to a verifiable
                     record, so a proof carries neither. The block keeps its height
                     with them taken out, so the letter below begins on the same line
                     of the page as it will on the official print. --}}
                <div style="position: relative; margin-bottom: 4px; margin-top: 0; min-height: 90px;">
                    @unless($isWhiteCopy)
                    <div style="text-align: center;">
                        <img src="{{ asset('assets/logo/Nigerian-Coat-of-Arms.png') }}" alt="Nigerian Coat of Arms" style="width: 90px; height: auto; display: inline-block;" />
                    </div>
                    <img src="{{ $qrUrl }}" alt="QR Code" style="position: absolute; left: 40px; top: 50%; transform: translateY(-50%); width: 55px; height: 55px;" />
                    @endunless
                </div>

                <div style="text-align: center; margin-bottom: 8px;">
                    <div style="display: inline-block; border: 2px solid #000; border-radius: 8px; padding: 3px; background: #fff;">
                        <div data-rofo-badge style="background: #1a3a5c; padding: 8px 16px; border-radius: 5px; -webkit-print-color-adjust: exact; print-color-adjust: exact;">
                            <p style="font-weight: bold; color: #fff; text-transform: uppercase; font-size: 15px; margin: 0; letter-spacing: 0.5px;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</p>
                            <p style="font-size: 15px; color: #fff; margin: 3px 0 0 0; letter-spacing: 0.3px;">No. 2 Dr Bala Mohammed Road, Kano State, Nigeria</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-2 items-stretch">

                    <!-- Left: Applicant Name & Address -->
                    <div class="flex">
                        <div class="border-2 border-black p-2 rounded flex-1">
                            <div class="mb-2">
                                <label class="font-bold">TO: <span class="font-bold uppercase">
                                        @php
                                            $corporateName = $rofoData->corporate_name ?? $rofo->corporate_name ?? '';
                                            if ($corporateName) {
                                                echo $corporateName;
                                            } else {
                                                echo trim(($rofoData->applicant_title ?? $rofo->applicant_title ?? '') . ' ' .
                                                    ($rofoData->first_name ?? $rofo->first_name ?? '') . ' ' .
                                                    ($rofoData->middle_name ?? $rofo->middle_name ?? '') . ' ' .
                                                    ($rofoData->surname ?? $rofo->surname ?? '')) ?: '-';
                                            }
                                        @endphp
                                    </span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                            <div class="mb-2">
                                <label class="font-bold">ADDRESS: <span class="font-bold uppercase">{{ $subApplication->address ?? '' }}</span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: ROFO No, Land Use, Unit, Section, Location -->
                    <div class="flex">
                        <div class="border-2 border-black p-2 rounded font-bold flex-1">
                            <div class="mb-2">
                                <label class="font-bold">ROFO NO: <span class="font-bold">{{ $rofoData->fileno ?? $rofo->fileno ?? '-' }}</span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                            <div class="mb-2">
                                <label class="font-bold">LAND USE: <span class="font-bold uppercase">
                                        @php
                                            $landUse = $rofoData->land_use ?? $rofo->land_use ?? '-';
                                            $specificLandUse = $rofo->unit_specific_use ?? $rofoData->specific_land_use ?? $rofo->specific_land_use ?? '';
                                            if (\Str::contains(strtolower(trim($landUse)), 'mixed')) {
                                                if (!empty($specificLandUse)) {
                                                    echo strtoupper($specificLandUse);
                                                } else {
                                                    $fee = $rofoData->recertification_fee ?? $rofoData->ground_rent ?? '';
                                                    if (\Str::contains($fee, ['100.00K', '50.00K', '20.00K'])) { echo 'COMMERCIAL'; }
                                                    elseif (\Str::contains($fee, ['12.00K', '10.00K', '8.00K'])) { echo 'RESIDENTIAL'; }
                                                    else { echo strtoupper($landUse); }
                                                }
                                            } else {
                                                echo strtoupper($landUse);
                                            }
                                        @endphp
                                    </span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                            <div class="mb-2">
                                <label class="font-bold">UNIT NO/SCHEME NO: <span class="font-bold">
                                        @if($unitLineHasData)
                                            <span class="uppercase">{{ $unitNumberValue }}</span>
                                            <span class="uppercase">{{ $schemeNumberValue }}</span>
                                            <span class="uppercase" style="color: #b91c1c;">{{ $unitTypeValue }}</span>
                                        @else
                                            -
                                        @endif
                                    </span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                            <div class="mb-2">
                                <label class="font-bold">SECTION NO: <span class="font-bold">{{ $rofoData->floor_no ?? $rofo->floor_number ?? '1' }}</span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                            <div>
                                <label class="font-bold">LOCATION: <span class="font-bold">{{ $rofoData->location ?? '-' }}</span></label>
                                <div class="border-b border-black mt-1"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms of Offer Section -->
                <div class="terms-offer-box">
                    <h2 class="text-center text-base font-bold mb-3 rofo-terms-heading underline">TERMS OF OFFER OF GRANT/CONVEYANCE
                        OF APPROVAL</h2>

                    <p class="mb-2 font-bold" style="font-size: 11px;">Reference to your application dated <span
                            class="font-bold underline">
                            {{ isset($rofoData->application_date) ? \Carbon\Carbon::parse($rofoData->application_date)->format('d/m/Y') : '-' }}</span>
                        I am directed to inform you that, The Governor of Kano State has approved the Grant of a Right of
                        occupancy to you over a Block No <span
                            class="font-bold underline">{{ $rofoData->block_no ?? $rofo->block_number ?? '-' }}</span>
                        Section No <span
                            class="font-bold underline">{{ $rofoData->floor_no ?? $rofo->floor_number ?? '-' }}</span> Unit
                        No <span class="font-bold underline">{{ $unitNumberDisplay }}</span> Situated at <span
                            class="font-bold underline">{{ $rofoData->location ?? '-' }}</span> as scheme No <span
                            class="font-bold underline">{{ $schemeNumberValue }}</span> on the following conditions.</p>

                    <ol class="list-decimal pl-5 mb-2 font-bold">
                        <li>Payment of
                            <ol class="list-[lower-latin] pl-5" style="margin-top: 0.25rem;">
                                <li>Ground Rate <span
                                        class="font-bold underline">{{ $rofoData->recertification_fee  }}</span>
                                </li>
                                <li>Development Charges <span
                                        class="font-bold underline">{{ ($rofoData->development_charges === null) ? 'to follow' : number_format($rofoData->development_charges, 2) }}</span>
                                    (payable only once)</li>
                                <li>Survey and processing fees <span
                                        class="font-bold underline">₦{{ number_format($rofoData->survey_processing_fees ?? 0, 2) }}</span>
                                </li>
                            </ol>
                        </li>

                        <li><ol class="list-[lower-latin] pl-5" style="margin-top: 0;">
                                <li>Term <span class="font-bold underline">{{ $rofoData->term_years ?? 40 }}</span> years
                                </li>
                                <li>Purpose <span
                                        class="font-bold underline">{{ $rofoData->purpose ?? $rofo->land_use ?? '-' }}</span>
                                </li>
                                <li>Value of improvements thereon <span
                                        class="font-bold underline">{{ $rofoData->improvement_value ?? '-' }}</span> Within
                                    <span class="font-bold underline">{{ $rofoData->improvement_time_limit ?? 2 }}</span>
                                    years
                                </li>
                            </ol>
                        </li>

                        <li>Not to alienate the Right of Occupancy in part or whole without the written consent of the
                            Governor</li>

                        <li>To be responsible for the development, maintenance and general beautifications of the frontage
                            of the subject property</li>

                        <li>Not to erect or permit to be erected on the subject land any building or development except in accordance with plans and specifications approved by the State Planning Authority in the case of urban areas or this ministry in the case of rural areas.</li>

                        <li>To Complete Development on Land Within <span
                                class="font-bold underline">{{ $rofoData->improvement_time_limit ?? 2 }}</span> Years</li>

                        <li>To become joint owner of the common property of the sectional Title land and actively
                            participate in all quotas that benefit or burden sections</li>

                        <li>
                            To exclusively use certain parts and share undivided sections of the common property e.g.
                            @if(!empty($sharedUtilities))
                                <span class="font-bold">
                                    {{ implode(', ', $sharedUtilities) }} etc.
                                </span>
                            @else
                                <span class="font-bold">-</span>
                            @endif
                        </li>


                        <li>The duplicate &amp; triplicate copies of the letter of Grant must be returned duly accepted with the required fees to enable production of C OF O, otherwise the offer lapses.</li>
                    </ol>
                    <div style="height: 0.5rem;"></div>
                    <!-- SIGNATURE BLOCK - Inspired by Land ROFO with security microtext lines -->
                    {{-- Off the proof entirely, microtext security lines and all. A
                         signature line is what makes a sheet look executed, and a
                         proof carrying one can be signed and passed off as the letter
                         itself — the one misuse this stage exists to prevent. The
                         space is left behind so the page still breaks where the
                         official letter breaks. --}}
                    @if($isWhiteCopy)
                    <div class="white-copy-signature-gap" style="margin-top: auto;">
                        Signature block omitted — white copy for proofreading only
                    </div>
                    @else
                    <div style="margin-top: auto; display: flex; justify-content: space-between; padding: 0 30px 10px 30px; text-align: center; font-weight: bold; align-items: flex-end;">
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div style="position: relative; width: 280px; height: 40px; margin-bottom: 1px; overflow: hidden; background: transparent;">
                                <span style="position: absolute; bottom: 0; left: 0; width: 100%; font-size: 3.5px; font-weight: 900; letter-spacing: -0.65px; word-spacing: -3.2px; color: #000; white-space: nowrap; overflow: hidden; text-transform: uppercase; pointer-events: none; z-index: 2; font-family: 'Arial Narrow', 'Helvetica Condensed', 'Courier New', monospace; text-align: center; line-height: 1; text-shadow: 0 0.5px 0 #666;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</span>
                            </div>
                            <div style="font-size: 12px;">HONOURABLE COMMISSIONER</div>
                        </div>
                        <div style="display: flex; flex-direction: column; align-items: center;">
                            <div style="position: relative; width: 280px; height: 40px; margin-bottom: 1px; overflow: hidden; background: transparent;">
                                <span style="position: absolute; bottom: 0; left: 0; width: 100%; font-size: 3px; font-weight: 900; letter-spacing: -0.65px; word-spacing: -3.2px; color: #000; white-space: nowrap; overflow: hidden; text-transform: uppercase; pointer-events: none; z-index: 2; font-family: 'Arial Narrow', 'Helvetica Condensed', 'Courier New', monospace; text-align: center; line-height: 1; text-shadow: 0 0.5px 0 #666;">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</span>
                            </div>
                            <div style="margin-top: 5px; font-size: 12px;">DATE</div>
                        </div>
                    </div>
                    @endif

                </div>
              </div>

              <!-- FOOTER with barcode - outside border frame, inside page -->
              <div class="st-rofo-footer">
                  <div style="display: flex; align-items: flex-end; gap: 10px; visibility: visible; opacity: 0.0001;">
                      <div style="font-size: 7px; transform: rotate(-90deg);">©NSPM</div>
                      <div style="display: flex; flex-direction: column; align-items: center;">
                          <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text={{ urlencode($rofoData->fileno ?? $rofo->fileno ?? 'N/A') }}&scale=1" style="height: 32px; margin-bottom: -10px;" />
                          <span style="font-size: 8px;">{{ $rofoData->fileno ?? $rofo->fileno ?? 'N/A' }}</span>
                      </div>
                  </div>
              </div>
            </div>

            <!-- Second Page -->
            <div id="second-page" class="p-4 mb-8">
                <!-- Left and Right Boxes positioned outside main container -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 acceptance-meta" style="align-items: flex-end;">
                    <!-- Left Box -->
                    <div class="border-2 border-black p-3 rounded-lg"
                        style="min-height: 80px; max-width: 280px; margin-top: 40px;">
                        <p class="font-bold text-sm">The Permanent Secretary,</p>
                        <p class="font-bold text-sm">Ministry of Land and Physical Planning</p>
                    </div>

                    <!-- Right Box -->

                    <div class="border-2 border-black p-4 rounded-lg" style="min-height: 160px;">
                        <br>
                        <div class="mb-3">
                            <div class="flex items-center">
                                <span class="font-bold mr-2" style="min-width: 120px;">Applicant Name</span>
                                <div class="border-b border-black flex-grow ml-2">
                                    <span class="font-bold">
                                        @php
                                            $corporateName = $rofoData->corporate_name ?? $rofo->corporate_name ?? '';
                                            if ($corporateName) {
                                                echo $corporateName;
                                            } else {
                                                echo trim(($rofoData->applicant_title ?? $rofo->applicant_title ?? '') . ' ' .
                                                    ($rofoData->first_name ?? $rofo->first_name ?? '') . ' ' .
                                                    ($rofoData->middle_name ?? $rofo->middle_name ?? '') . ' ' .
                                                    ($rofoData->surname ?? $rofo->surname ?? '')) ?: '';
                                            }
                                        @endphp
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="flex items-center">
                                <span class="font-bold mr-2" style="min-width: 120px;">Land Use</span>
                                <div class="border-b border-black flex-grow ml-2">
                                    <span class="font-bold">
                                        @php
                                            $landUse = $rofoData->land_use ?? $rofo->land_use ?? '-';

                                            // Priority 1: Aliased specific use from controller
                                            $specificLandUse = $rofo->unit_specific_use ?? '';

                                            // Priority 2: Direct property (if alias missing)
                                            if (empty($specificLandUse)) {
                                                $specificLandUse = $rofoData->specific_land_use ?? $rofo->specific_land_use ?? '';
                                            }

                                            // Check if main land use indicates Mixed
                                            if (\Str::contains(strtolower(trim($landUse)), 'mixed')) {
                                                if (!empty($specificLandUse)) {
                                                    echo strtoupper($specificLandUse);
                                                } else {
                                                    // Fallback: Infer from Fee
                                                    $fee = $rofoData->recertification_fee ?? $rofoData->ground_rent ?? '';
                                                    if (\Str::contains($fee, ['100.00K', '50.00K', '20.00K'])) {
                                                        echo 'COMMERCIAL';
                                                    } elseif (\Str::contains($fee, ['12.00K', '10.00K', '8.00K'])) {
                                                        echo 'RESIDENTIAL';
                                                    } else {
                                                        echo strtoupper($landUse);
                                                    }
                                                }
                                            } else {
                                                echo strtoupper($landUse);
                                            }
                                        @endphp
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="flex items-center">
                                <span class="font-bold mr-2" style="min-width: 120px;">Contact Address</span>
                                <div class="border-b border-black flex-grow ml-2">
                                    <span
                                        class="font-bold uppercase">{{ $rofoData->address ?? $rofo->address ?? '' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="flex items-center">
                                <span class="font-bold mr-2" style="min-width: 120px;">Date</span>
                                <div class="border-b border-black flex-grow ml-2">
                                    <span class="font-bold">{{ date('d/m/Y') }}</span>
                                </div>
                            </div>
                        </div>


                    </div>
                </div>

                <!-- Main Content Container -->
                <div class="border-4 border-black acceptance-letter-container">
                    <!-- Acceptance Letter Section -->
                    <div class="p-4">


                        <h2 class="text-center text-lg font-bold mb-3">ACCEPTANCE LETTER</h2>

                        <!-- Horizontal line below heading -->
                        <hr class="border-t-4 border-black mb-4 w-full"
                            style="margin-left: -1rem; margin-right: -1rem; width: calc(100% + 2rem);">

                        <p class="text-justify mb-4 text-sm">Reference to the offer of Grant, I hereby accept the terms and
                            condition of the grant of right of occupancy as conveyed
                            to me by your overleaf quoted letter.</p>

                        <!-- Fee Table -->
                        <div class="border border-black mb-4">
                            <table class="w-full border-collapse">
                                <tr>
                                    <th class="border border-black p-2 text-left">Land Use</th>
                                    <th class="border border-black p-2 text-left">Survey / Processing Fees</th>
                                    <th class="border border-black p-2 text-left">Dev. Charges ₦</th>
                                </tr>
                                <tr>
                                    <td class="border border-black p-2">
                                        <p class="font-bold">a.Residential Fees</p>
                                        <p class="pl-4">i.Processing Fee</p>
                                        <p class="font-bold">b.Survey Fees</p>
                                        <p class="pl-4">i.Block of Flats</p>
                                        <p class="pl-4">ii.Appartment</p>
                                        <p class="font-bold">c.Assignment Fees</p>
                                        <p class="font-bold">d.Bill Balance</p>
                                        <p class="font-bold">e.Commercial Fees</p>
                                        <p class="pl-4">i.Processing Fee</p>
                                        <p class="pl-4">ii.Survey Fees</p>
                                        <p class="pl-4">iii.Assignment Fees</p>
                                        <p class="pl-4">iv.Bill Balance</p>
                                    </td>
                                    <td class="border border-black p-2">
                                        <p>&nbsp;</p>
                                        <p>₦ 20,000.00K</p>
                                        <p>&nbsp;</p>
                                        <p>₦ 50,000.00K</p>
                                        <p>₦ 70,000.00K</p>
                                        <p>₦ 50,000.00</p>
                                        <p>₦ 30,525.00K</p>
                                        <p>&nbsp;</p>
                                        <p>₦ 50,000.00K</p>
                                        <p>₦ 100,000.00K</p>
                                        <p>₦ 100,000.00K</p>
                                        <p>₦ 30,525.00K</p>
                                    </td>
                                    <td class="border border-black p-2">
                                        <p>&nbsp;</p>
                                    </td>
                                </tr>


                                <!-- <tr>
                                                                                                                    <td class="border border-black p-2">One year Ground Rent</td>
                                                                                                                    <td class="border border-black p-2">₦ <span
                                                                                                                            class="font-bold">{{  $rofoData->recertification_fee  }}</span>
                                                                                                                    </td>
                                                                                                                    <td class="border border-black p-2">₦ <span
                                                                                                                            class="font-bold">{{ $rofoData->recertification_fee  }}</span>
                                                                                                                    </td>
                                                                                                                </tr> -->
                                <tr>
                                    <td class="border border-black p-2" colspan="3">
                                        <!-- <p>TOTAL ₦ <span class="font-bold">00.0 </span> -->

                                        <!-- {{  number_format(($rofoData->development_charges ?? 0) + ($rofoData->survey_processing_fees ?? 0), 2) }} -->
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- Checkboxes -->
                        <div class="mb-4 space-y-2">
                            <div class="flex items-start">
                                <div class="checkbox border-2 border-green-500"></div>
                                <p>I require the Director Survey to carry out the land survey for me</p>
                            </div>

                            <div class="flex items-start">
                                <div class="checkbox border-2 border-green-500"></div>
                                <p>I require a licensed Surveyor to carry out the land survey for me</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="acceptance-spacer"></div>
                <!-- Note Section - Outside main container -->
                <div class="flex border-2 border-black rounded-lg mt-4">
                    <div class="w-7/10 p-3" style="width: 70%;">
                        <p class="font-bold">*NOTE:</p>
                        <p>APPLICANT TO RETAIN ORIGINAL AND RETURN 2 COPIES AFTER SIGNING HIS/HER ACCEPTANCE OF THE GRANT.
                        </p>
                        <p style="margin-top: 6px;">THIS R OF O IS SUBJECT TO VERIFICATION BEFORE ANY STATUTORY PAYMENTS TO REVENUE DEPARTMENT.</p>
                    </div>
                    {{-- The applicant's own line goes with the Commissioner's: a proof
                         carrying any signature line is a sheet that can be signed, and
                         a signed white copy read at a counter looks like an accepted
                         grant. --}}
                    <div class="w-3/10 p-3 border-l-2 border-black flex flex-col justify-between" style="width: 30%;">
                        @if($isWhiteCopy)
                        <div class="white-copy-signature-gap px-2" style="padding-top: 28px;">
                            Signature block omitted — white copy for proofreading only
                        </div>
                        @else
                        <div class="px-2">
                            <br>
                            <br>
                            <br>
                            <div class="border-b-2 border-black mb-1 w-full"></div>
                            <span class="font-bold text-center block text-xs">APPLICANT'S SIGNATURE</span>
                        </div>
                        <div class="flex items-center mt-4 px-2">
                            <span class="font-bold mr-1 text-xs">DATE:</span>
                            <div class="border-b-2 border-black flex-grow"></div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Footer Logos -->
           <div style="display: flex; align-items: center; justify-content: flex-end; margin-top: 16px; padding-top: 6px; border-top: 1px solid #ccc;">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" style="height: 35px; width: auto; object-fit: contain;">
                </div>
            </div>
        </div>

        @if (!$loop->last)
            <div class="page-break" style="page-break-after: always; height:0; margin:0; overflow:hidden;"></div>
        @endif

    @endforeach

    {{-- Print button removed per request --}}
    {{-- @unless($isPrintRoute)
        <div class="print-buttons no-print flex gap-2 justify-center my-4">
            <button onclick="printROFO()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                Print ROFO
            </button>
        </div>
    @endunless --}}

    <script>
        @php
            $firstContext = $batchRofos[0] ?? null;
            $printRouteId = $firstContext ? ($firstContext['subApp']->id ?? ($firstContext['unitRofoData']->sub_application_id ?? null)) : null;
        @endphp

        @if($printRouteId)
            function printROFO() {
                const printUrl = "{{ route('programmes.print_rofo', $printRouteId) }}";
                window.open(printUrl, '_blank');
            }
        @else
            function printROFO() {
                alert('Unable to determine the ROFO document to print.');
            }
        @endif

            @if($autoPrint)
                    async function waitForAssets() {
                        const imagePromises = Array.from(document.images).map((img) => {
                            if (img.complete) {
                                return Promise.resolve();
                            }

                            return new Promise((resolve) => {
                                img.addEventListener('load', resolve, { once: true });
                                img.addEventListener('error', resolve, { once: true });
                            });
                        });

                        try {
                            await Promise.all(imagePromises);
                        } catch (e) {
                            console.warn('ROFO print: image preload warning', e);
                        }

                        if (document.fonts && document.fonts.ready) {
                            try {
                                await document.fonts.ready;
                            } catch (e) {
                                console.warn('ROFO print: font preload warning', e);
                            }
                        }
                    }

                window.addEventListener('load', function () {
                    waitForAssets().then(function () {
                        setTimeout(function () {
                            window.print();
                        }, 200);
                    });
                });
            @endif
    </script>

    <!-- Color Scheme Switcher (hidden in print) -->
    <div id="scheme-toolbar" style="position: fixed; top: 20px; left: 20px; z-index: 9999; display: flex; gap: 8px; background: #fff; padding: 10px 14px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.25); font-family: Arial, sans-serif; font-size: 13px; align-items: center; display: none;">
        <span style="font-weight: bold; margin-right: 4px;">Scheme:</span>
        <button onclick="applyScheme('A')" class="scheme-btn" data-scheme="A" style="padding: 6px 14px; border: 2px solid #1a3a5c; border-radius: 5px; cursor: pointer; font-weight: bold; background: #1a3a5c; color: #fff;">A</button>
        <button onclick="applyScheme('B')" class="scheme-btn" data-scheme="B" style="padding: 6px 14px; border: 2px solid #1a3a5c; border-radius: 5px; cursor: pointer; font-weight: bold; background: #fff; color: #1a3a5c;">B</button>
        <button onclick="applyScheme('C')" class="scheme-btn" data-scheme="C" style="padding: 6px 14px; border: 2px solid #1a3a5c; border-radius: 5px; cursor: pointer; font-weight: bold; background: #fff; color: #1a3a5c;">C</button>
        <span id="scheme-label" style="margin-left: 8px; color: #666; font-size: 12px;">Current: A (ST border, navy badge)</span>
    </div>
    <style id="scheme-override"></style>
    <style>
        @media print { #scheme-toolbar { display: none !important; } }
    </style>
    <script>
        var stSchemes = {
            A: { frame: 'st.png', badge: '#1a3a5c', label: 'A (ST border, navy badge)' },
            B: { frame: 'sltr.jpeg', badge: '#1a3a5c', label: 'B (green border, navy badge)' },
            C: { frame: 'sltr.jpeg', badge: '#4ebf97', label: 'C (green border, green badge)' }
        };
        function applyScheme(key) {
            // A proof has no frame and no coloured badge to restyle; the toolbar is
            // hidden there, but a stray call must not paint them back on.
            if (document.querySelector('.rofo-wrapper.white-copy')) return;

            var s = stSchemes[key];
            var frameUrl = 'http://app.klaes.ng/storage/template_frames/' + s.frame;
            var css = '.st-rofo-green-frame::before { border-image-source: url("' + frameUrl + '") !important; }';
            document.getElementById('scheme-override').textContent = css;
            // Update badge inline style
            var badges = document.querySelectorAll('[data-rofo-badge]');
            badges.forEach(function(badge) { badge.style.background = s.badge; });
            document.getElementById('scheme-label').textContent = 'Current: ' + s.label;
            document.querySelectorAll('.scheme-btn').forEach(function(btn) {
                var active = '#1a3a5c';
                if (btn.dataset.scheme === key) {
                    btn.style.background = active; btn.style.color = '#fff';
                } else {
                    btn.style.background = '#fff'; btn.style.color = active;
                }
            });
        }
    </script>
</body>

</html>