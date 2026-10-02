<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deed of Purchase - Registration Details Sheet</title>
    <script src="https://cdn.tailwindcss.com"></script>

    {{--
        Registration Details Sheet, Land Registry edition.

        Built from the Deeds RDS, with three differences that matter:
          - the authority block reads LAND REGISTRY, and the sheet is signed by
            the DIRECTOR LAND (config('land_registration.authority'))
          - no Occupancy Permit branches: an OP cannot reach this registry
          - no consent lookup: the Deeds sheet reads consent_applications for
            multi-property mortgages, and a purchase has no consent behind it

        The data it renders is prepared by RDSController::getPrintViewData(),
        shared with Deeds - only the presentation differs.
    --}}

    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
                margin: 0;
                padding: 0 !important;
                width: 210mm;
                height: 297mm;
                overflow: hidden;
            }

            .document-content {
                padding: 15mm !important;
                height: 100%;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
            }

            @page {
                size: A4 portrait;
                margin: 0;
            }
        }

        @if(request()->boolean('embedded'))
        /* Batch-embed mode: the host page owns the @page rules, so we must NOT
           force a full A4 page here or the parent prints blank sheets. Keep the
           12mm internal padding so fields are not flush to the edge. */
        html, body {
            width: auto !important;
            min-height: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 12mm !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }
        /* Tailwind's max-w-4xl (~237mm) is wider than A4 and clips the right
           edge when embedded. Force the document to fit the page. */
        .document-content {
            max-width: 100% !important;
            width: 100% !important;
            margin: 0 auto !important;
        }
        @media print {
            html, body {
                width: auto !important;
                min-height: 0 !important;
                height: auto !important;
                padding: 12mm !important;
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow-x: hidden !important;
            }
            .document-content {
                max-width: 100% !important;
                width: 100% !important;
            }
        }
        @endif

        .underline-field {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 150px;
            padding: 0 4px;
        }

        .form-line {
            border-bottom: 1px solid #000;
            min-height: 24px;
        }

        .document-title {
            text-decoration: underline;
            text-underline-offset: 4px;
        }

        .signature-rule {
            border-bottom: 1px solid #000;
            min-height: 28px;
            width: 260px;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(200, 200, 200, 0.3);
            font-weight: bold;
            z-index: 0;
            pointer-events: none;
        }

        .document-content {
            position: relative;
            z-index: 1;
        }
    </style>
</head>

<body class="bg-white p-4">
    @if(($watermark ?? '') === 'COPY')
        <div class="watermark">COPY</div>
    @endif

    <button onclick="window.print()"
        class="no-print fixed top-4 right-4 bg-orange-600 text-white px-4 py-2 rounded-lg shadow-lg hover:bg-orange-700 transition-colors flex items-center gap-2 z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z"
                clip-rule="evenodd" />
        </svg>
        Print Document
    </button>

    @php
        $authority = config('land_registration.authority');

        $instrumentType = $instrument->instrument_type ?? config('land_registration.instrument_type');
        $normalizedType = trim((string) $instrumentType);

        $documentTitle = strtoupper($normalizedType);
        $documentSubtitle = 'THIS IS A ' . strtoupper($normalizedType);
        $assignmentMortgageText = 'is Sold and Transferred';

        // The registration date, from rds_tracking, falling back to the
        // instrument's own date.
        $registrationDate = $rds->registration_date ?? $instrument->instrumentDate ?? $instrument->deeds_date ?? null;
        $dateFormatted = ['day' => '', 'month' => '', 'year' => ''];

        if ($registrationDate) {
            try {
                $dateObj = \Carbon\Carbon::parse($registrationDate);
                $dateFormatted['day'] = $dateObj->format('d');
                $dateFormatted['month'] = $dateObj->format('F');
                $dateFormatted['year'] = $dateObj->format('Y');
            } catch (\Exception $e) {
                \Log::warning('Land RDS: could not parse registration date: ' . $registrationDate);
            }
        }

        $vendor = $instrument->Grantor ?? $rds->grantor ?? '';
        $purchaser = $instrument->Grantee ?? $rds->grantee ?? '';
        $vendorAddress = normalizeRDSText($instrument->party_1_address ?? $instrument->GrantorAddress ?? '');
        $purchaserAddress = normalizeRDSText($instrument->party_2_address ?? $instrument->GranteeAddress ?? '');

        // Party names are sometimes stored as a JSON array (multiple holders on
        // one side). Show the first, which is the name the deed is drawn in.
        $firstOf = function ($value) {
            if (is_string($value) && str_starts_with(trim($value), '[')) {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && count($decoded) > 0) {
                    return normalizeRDSText($decoded[0]);
                }
            }
            return normalizeRDSText($value);
        };

        $vendorDisplay = $firstOf($vendor);
        $purchaserDisplay = $firstOf($purchaser);

        $fileNumber = $instrument->fileno ?? $rds->file_number ?? '';
        $fileNumberLabel = 'With FileNo.';

        // A TEMP number is an internal placeholder, never printed on a document
        // that leaves the registry.
        if (str_starts_with(strtoupper((string) $fileNumber), 'TEMP')) {
            $fileNumber = '';
        }
    @endphp

    <div class="max-w-4xl mx-auto relative document-content">

        <!-- Header: logos, registry, document title -->
        <div class="text-center mb-6 flex items-center justify-center gap-8">
            <div class="flex-shrink-0">
                <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Logo Left" class="h-16 w-auto">
            </div>

            <div class="flex-grow text-center">
                <p class="text-[11px] font-bold tracking-wide leading-tight">{{ $authority['ministry'] }}</p>
                <p class="text-sm font-bold tracking-wider leading-tight">{{ $authority['registry'] }}</p>
                <p class="text-sm font-bold tracking-wider mb-2">{{ $authority['department'] }}</p>
                <h1 class="text-xl font-bold tracking-wider mb-1">REGISTRATION DETAILS SHEET</h1>
                <h2 id="document-title" class="text-lg font-bold tracking-wider document-title">
                    {{ $documentTitle }}
                </h2>
            </div>

            <div class="flex-shrink-0">
                <img src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="Ministry Logo Right" class="h-16 w-auto">
            </div>
        </div>

        <div class="text-center mb-8">
            <p id="document-subtitle" class="text-sm">{{ $documentSubtitle }}</p>
        </div>

        <div class="space-y-6 text-sm leading-relaxed flex-grow">
            <div class="flex items-baseline gap-2">
                <span class="font-semibold">Dated:</span>
                <span class="underline-field flex-1 font-bold">{{ $dateFormatted['day'] }}</span>
                <span>day of</span>
                <span class="underline-field flex-1 font-bold">{{ $dateFormatted['month'] }}</span>
                <span>{{ $dateFormatted['year'] }}</span>
            </div>

            <div class="flex items-baseline gap-2">
                <span class="font-semibold">Executed by:</span>
                <span class="form-line flex-1 font-bold">{{ $vendorDisplay }}</span>
            </div>

            <div class="flex items-baseline gap-2">
                <span class="font-semibold">At:</span>
                <span class="form-line flex-1 font-bold">{{ $vendorAddress }}</span>
            </div>

            <div class="mt-6">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span>{{ $fileNumberLabel }}</span>
                    <span class="underline-field flex-1 min-w-[200px] font-bold">{{ $fileNumber }}</span>
                    <span id="assignment-mortgage-text">{{ $assignmentMortgageText }}</span>
                </div>
            </div>

            <div class="flex items-baseline gap-2">
                <span class="font-semibold">to:</span>
                <span class="form-line flex-1 font-bold">{{ $purchaserDisplay }}</span>
            </div>

            <div class="flex items-baseline gap-2">
                <span class="font-semibold">of:</span>
                <span class="form-line flex-1 font-bold">{{ $purchaserAddress }}</span>
            </div>

            <div class="flex items-baseline gap-2 mt-6">
                <span class="font-semibold">From:</span>
                <span class="underline-field flex-1 font-bold">{{ $dateFormatted['day'] }}</span>
                <span>day of</span>
                <span class="underline-field flex-1 font-bold">{{ $dateFormatted['month'] }}</span>
                <span>{{ $dateFormatted['year'] }}</span>
            </div>

            {{-- Consideration and the receipt number are captured on the form, so
                 they print filled. The registration fee and stamp duty are still
                 entered by hand at the counter and print as empty rules. --}}
            @php
                $amount = $instrument->consideration_amount ?? null;
                // Formatted only when it really is a number: legacy rows can hold
                // free text like "N1.5M", which must print as written.
                $amountDisplay = is_numeric($amount)
                    ? '&#8358;' . number_format((float) $amount, 2)
                    : e((string) $amount);
            @endphp
            <div class="mt-8 space-y-2 max-w-md ml-auto">
                <div class="flex items-baseline gap-2">
                    <span class="font-semibold uppercase tracking-wide">CONSIDERATION:</span>
                    <span class="form-line flex-1 font-bold">{!! $amountDisplay !!}</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="font-semibold uppercase tracking-wide">RECEIPT NO:</span>
                    <span class="form-line flex-1 font-bold">{{ $instrument->receipt_no ?? '' }}</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="font-semibold uppercase tracking-wide">REGISTRATION FEE OF &#8358;</span>
                    <span class="form-line flex-1"></span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span class="font-semibold uppercase tracking-wide">STAMP DUTY:</span>
                    <span class="form-line flex-1"></span>
                </div>
            </div>

            <!-- Authority: signed by the Director Land, not a Deeds registrar -->
            <div class="mt-10">
                <div class="signature-rule"></div>
                <p class="font-semibold uppercase tracking-wide mt-1">{{ $authority['signatory'] }}</p>
                <p class="text-xs text-gray-600">{{ $authority['registry'] }}</p>
            </div>

            <div class="text-right text-xs mt-4 text-gray-500 no-print">
                <span class="italic">...to be REGISTERED</span>
            </div>
        </div>

        <div class="mt-auto flex justify-end pt-4">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo" class="h-10 w-auto object-contain">
        </div>
    </div>
</body>

</html>
