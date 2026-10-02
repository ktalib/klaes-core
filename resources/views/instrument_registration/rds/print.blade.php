<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $documentTitle ?? 'RDS Document' }} - Registered Document Sheet</title>
    <script src="https://cdn.tailwindcss.com"></script>


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

            /* Remove browser headers and footers and set margins */
            @page {
                size: A4 portrait;
                margin: 0;
            }
        }

        @if(request()->boolean('embedded'))
        /* Batch-embed mode: the host page owns the @page rules; we must NOT
           force a full A4 page here or the parent will print blank sheets.
           Keep the same 12mm internal padding so fields aren't flush to the edge. */
        html, body {
            width: auto !important;
            min-height: 0 !important;
            height: auto !important;
            margin: 0 !important;
            padding: 12mm !important;
            box-sizing: border-box !important;
            overflow-x: hidden !important;
        }
        /* Tailwind max-w-4xl (~237mm) is wider than A4 (210mm) and clips the
           right edge when embedded. Force the document to fit the page. */
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

        /* Watermark styling */
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
    <!-- Watermark for COPY prints -->
    @if(($watermark ?? '') === '')
        <div class="watermark"></div>
    @endif

    <!-- Print Button -->
    <button onclick="window.print()"
        class="no-print fixed top-4 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg hover:bg-blue-700 transition-colors flex items-center gap-2 z-50">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd"
                d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z"
                clip-rule="evenodd" />
        </svg>
        Print Document
    </button>

    @php
        /**
         * RDS Print Template - Data-Driven Document Generation
         * All values sourced from rds_tracking and registered_instruments tables
         */

        // Get document title from instrument_type (data-driven)
        $instrumentType = $instrument->instrument_type ?? $documentTitle ?? 'ASSIGNMENT';
        $normalizedType = trim($instrumentType);
        $upperType = strtoupper($normalizedType);

        // Format document title and subtitle .
        if (!empty($instrument->op_serial_number)) {
            $documentTitle = 'OCCUPANCY PERMIT';
            $opType = $instrument->op_type ?? '';
            $documentSubtitle = 'OCCUPANCY PERMIT' . ($opType ? " ({$opType})" : '');
        } else {
            $documentTitle = $normalizedType;
            $documentSubtitle = (in_array(strtoupper($normalizedType[0] ?? ''), ['A', 'E', 'I', 'O', 'U']) ? 'THIS IS AN ' : 'THIS IS A ') . $normalizedType;
        }

        // Determine assignment/mortgage text based on instrument type
        if (stripos($upperType, 'MORTGAGE') !== false) {
            $assignmentMortgageText = 'is Mortgaged';
        } elseif (stripos($upperType, 'ASSIGNMENT') !== false) {
            $assignmentMortgageText = 'is Assigned';
        } elseif (stripos($upperType, 'LEASE') !== false) {
            $assignmentMortgageText = 'is Leased';
        } elseif (stripos($upperType, 'RELEASE') !== false) {
            $assignmentMortgageText = 'is Released';
        } elseif (stripos($upperType, 'SURRENDER') !== false) {
            $assignmentMortgageText = 'is Surrendered';
        } else {
            $assignmentMortgageText = 'is ' . $normalizedType;
        }

        // Format registration date from rds_tracking table
        $registrationDate = $rds->registration_date ?? $instrument->instrumentDate ?? null;
        $dateFormatted = [
            'day' => '',
            'month' => '',
            'year' => ''
        ];

        if ($registrationDate) {
            try {
                $dateObj = \Carbon\Carbon::parse($registrationDate);
                $dateFormatted['day'] = $dateObj->format('d');
                $dateFormatted['month'] = $dateObj->format('F');
                $dateFormatted['year'] = $dateObj->format('Y');
                $dateFormatted['formatted'] = $dateObj->format('jS F Y'); // e.g., "12th November 2025"
            } catch (\Exception $e) {
                \Log::warning('RDS: Could not parse registration date: ' . $registrationDate);
            }
        }

        // Extract and format parties based on instrument type
        // Map party names according to their role in the transaction
        $grantor = $instrument->Grantor ?? $rds->grantor ?? '';
        $grantee = $instrument->Grantee ?? $rds->grantee ?? '';
        $grantorAddress = normalizeRDSText($instrument->party_1_address ?? $instrument->GrantorAddress ?? '');
        $granteeAddress = normalizeRDSText($instrument->party_2_address ?? $instrument->GranteeAddress ?? '');

        // Handle JSON-encoded arrays for party names - inline extraction
        $grantorDisplay = $grantor;
        if (is_string($grantor) && str_starts_with(trim($grantor), '[')) {
            try {
                $grantorArray = json_decode($grantor, true);
                if (is_array($grantorArray) && count($grantorArray) > 0) {
                    $grantorDisplay = normalizeRDSText($grantorArray[0]);
                }
            } catch (\Exception $e) {
                $grantorDisplay = normalizeRDSText($grantor);
            }
        } else {
            $grantorDisplay = normalizeRDSText($grantor);
        }

        $granteeDisplay = $grantee;
        if (is_string($grantee) && str_starts_with(trim($grantee), '[')) {
            try {
                $granteeArray = json_decode($grantee, true);
                if (is_array($granteeArray) && count($granteeArray) > 0) {
                    $granteeDisplay = normalizeRDSText($granteeArray[0]);
                }
            } catch (\Exception $e) {
                $granteeDisplay = normalizeRDSText($grantee);
            }
        } else {
            $granteeDisplay = normalizeRDSText($grantee);
        }

        // Determine party labels based on instrument type
        if (stripos($upperType, 'MORTGAGE') !== false) {
            $party1Label = 'Mortgagor';
            $party2Label = 'Mortgagee';
        } elseif (stripos($upperType, 'LEASE') !== false) {
            $party1Label = 'Lessor';
            $party2Label = 'Lessee';
        } elseif (stripos($upperType, 'ASSIGNMENT') !== false) {
            $party1Label = 'Assignor';
            $party2Label = 'Assignee';
        } else {
            $party1Label = 'Grantor';
            $party2Label = 'Grantee';
        }

        // Get file number
        $fileNumber = $instrument->StFileNo ?? $instrument->fileno ?? $rds->file_number ?? '';
        $fileNumberLabel = 'With FileNo.';

        // Override for Occupancy Permit (OP)
        if ($upperType === 'OCCUPANCY PERMIT (OP)') {
            $fileNumberLabel = 'With OP SerialNo';
            // Use op_serial_number if available, otherwise fall back to what we had
            $fileNumber = $instrument->op_serial_number ?? $fileNumber;
        }

        // Hide TEMP file numbers
        if (str_starts_with(strtoupper($fileNumber), 'TEMP')) {
            $fileNumber = '';
        }

        // --- Multi-property Mortgage logic ---
        $copiesToPrint = 1;
        if (stripos($upperType, 'MORTGAGE') !== false && !empty($fileNumber)) {
            $consentApp = DB::connection('sqlsrv')->table('consent_applications')
                ->where('file_number', $fileNumber)
                ->orderBy('id', 'desc')
                ->first();
                
            if ($consentApp && !empty($consentApp->additional_properties)) {
                $additionalProps = json_decode($consentApp->additional_properties, true);
                if (is_array($additionalProps)) {
                    $copiesToPrint = 1 + count($additionalProps);
                    
                    // Merge all applicant names
                    $titles = [];
                    if (!empty($consentApp->applicant_name)) {
                        $titles[] = strtoupper(trim($consentApp->applicant_name));
                    }
                    foreach ($additionalProps as $prop) {
                        if (!empty($prop['applicant_name'])) {
                            $titles[] = strtoupper(trim($prop['applicant_name']));
                        } elseif (!empty($prop['applicant'])) {
                            $titles[] = strtoupper(trim($prop['applicant']));
                        }
                    }
                    $titles = array_unique(array_filter($titles));
                    if (count($titles) == 1) {
                        $grantorDisplay = array_values($titles)[0];
                    } elseif (count($titles) == 2) {
                        $titles = array_values($titles);
                        $grantorDisplay = $titles[0] . ' & ' . $titles[1];
                    } elseif (count($titles) > 2) {
                        $titles = array_values($titles);
                        $last = array_pop($titles);
                        $grantorDisplay = implode(', ', $titles) . ', & ' . $last;
                    }
                }
            }
        }
        // --- End Multi-property Mortgage logic ---

        // Note: CONSIDERATION, STAMP DUTY, and REGISTRATION FEE are manual entry fields (not data-driven)
    @endphp

    @for ($copy = 0; $copy < $copiesToPrint; $copy++)
    @php
        $displayFileNumber = $fileNumber;
        if ($copy > 0 && !empty($additionalProps[$copy - 1]['file_number'])) {
            $displayFileNumber = $additionalProps[$copy - 1]['file_number'];
        }
    @endphp
    <!-- Document Container -->
    <div class="max-w-4xl mx-auto relative document-content">

        <!-- Header with Logos and Title -->
        <div class="text-center mb-8 flex items-center justify-center gap-8">
            <!-- Left Logo -->
            <div class="flex-shrink-0">
                <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Logo Left"
                    class="logo-left h-16 w-auto">
            </div>

            <!-- Center Title -->
            <div class="flex-grow text-center">
                <h1 class="text-xl font-bold tracking-wider mb-1">REGISTRATION DETAILS SHEET</h1>
                <h2 id="document-title" class="text-lg font-bold tracking-wider document-title">
                    {{ $documentTitle }}
                </h2>
            </div>

            <!-- Right Logo -->
            <div class="flex-shrink-0">
                <img src="{{ asset('assets/logo/ministry2.jpeg') }}" alt="Ministry Logo Right"
                    class="logo-right h-16 w-auto">
            </div>
        </div>

        <!-- Subtitle -->
        <div class="text-center mb-8">
            <p id="document-subtitle" class="text-sm">
                {{ strtoupper($documentSubtitle) }}
            </p>
        </div>

        <!-- Form Content -->
        <div class="space-y-6 text-sm leading-relaxed flex-grow">
            <!-- Date Line - Data-Driven from registration_date -->
            <div class="flex items-baseline gap-2">
                <span class="font-semibold">Dated:</span>
                <span class="underline-field flex-1 font-bold">
                    {{ $dateFormatted['day'] }}
                </span>
                <span>day of</span>
                <span class="underline-field flex-1 font-bold">
                    {{ $dateFormatted['month'] }}
                </span>
                <span>{{ $dateFormatted['year'] }}</span>
            </div>

            <!-- Executed By Section - Data-Driven from Grantor/Assignor/Lessor/Mortgagor -->
            <div class="flex items-baseline gap-2">
                <span class="font-semibold">Executed by:</span>
                <span class="form-line flex-1 font-bold">{{ $grantorDisplay }}</span>
            </div>

            <!-- Of (Address) Section - Data-Driven from GrantorAddress/AssignorAddress/LessorAddress/MortgagorAddress -->
            <div class="flex items-baseline gap-2">
                <span class="font-semibold">At:</span>
                <span class="form-line flex-1 font-bold">{{ $grantorAddress }}</span>
            </div>

            <!-- Right of Occupancy Section - Data-Driven from file_number -->
            <div class="mt-6">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span>{{ $fileNumberLabel }}</span>
                    <span class="underline-field flex-1 min-w-[200px] font-bold">{{ $displayFileNumber }}</span>
                    <span id="assignment-mortgage-text">{{ $assignmentMortgageText }}</span>
                </div>
            </div>

            <!-- To: Recipient Section - Data-Driven from Grantee/Mortgagee/Assignee/Lessee -->
            <div class="flex items-baseline gap-2">
                <span class="font-semibold">to:</span>
                <span class="form-line flex-1 font-bold">{{ $granteeDisplay }}</span>
            </div>

            <!-- Of (Recipient Address) Section - Data-Driven from GranteeAddress/MortgageeAddress/AssigneeAddress/LesseeAddress -->
            <div class="flex items-baseline gap-2">
                <span class="font-semibold">of:</span>
                <span class="form-line flex-1 font-bold">{{ $granteeAddress }}</span>
            </div>

            <!-- From Date -->
            <div class="flex items-baseline gap-2 mt-6">
                <span class="font-semibold">From:</span>
                <span class="underline-field flex-1 font-bold">{{ $dateFormatted['day'] }}</span>
                <span>day of</span>
                <span class="underline-field flex-1 font-bold">{{ $dateFormatted['month'] }}</span>
                <span>{{ $dateFormatted['year'] }}</span>
            </div>

            <!--  -->

            <!-- Right-aligned fee section - Manual entry fields -->
            <div class="mt-8 space-y-2 max-w-md ml-auto">
                <!-- Consideration -->



                <!-- Registration Fee -->
                <div class="flex items-baseline gap-2">
                    <span class="font-semibold uppercase tracking-wide">REGISTRATION FEE OF ₦</span>
                    <span class="form-line flex-1"></span>
                </div>

                <!-- RCR NO - Only for OP -->
                @if($upperType === 'OCCUPANCY PERMIT (OP)')
                    <div class="flex items-baseline gap-2">
                        <span class="font-semibold uppercase tracking-wide">RCR NO:</span>
                        <span class="form-line flex-1"></span>
                    </div>
                @endif


                @if($upperType !== 'OCCUPANCY PERMIT (OP)')
                    <div class="flex items-baseline gap-2">
                        <span class="font-semibold uppercase tracking-wide">CONSIDERATION:</span>
                        <span class="form-line flex-1"></span>
                    </div>

                    <!-- Stamp Duty -->
                    <div class="flex items-baseline gap-2">
                        <span class="font-semibold uppercase tracking-wide">STAMP DUTY:</span>
                        <span class="form-line flex-1"></span>
                    </div>
                @endif


                <!-- DEEDS REGISTRAR - Only for OP -->
                @if($upperType === 'OCCUPANCY PERMIT (OP)')
                    <div class="flex items-baseline gap-2">
                        <span class="font-semibold uppercase tracking-wide">DEEDS REGISTRAR:</span>
                        <span class="form-line flex-1"></span>
                    </div>
                @endif
            </div>

            <!-- Footer Note -->
            <div class="text-right text-xs mt-4 text-gray-500 no-print">
                <span class="italic">...to be REGISTERED</span>
            </div>

            <!-- RDS Reference and Metadata (no-print) -->
            <div class="mt-8 pt-4 border-t border-gray-300 text-xs text-gray-500 no-print space-y-1 hidden">
                @if($rds)
                    <div><strong>RDS Reference:</strong> {{ $rds->rds_reference ?? 'N/A' }}</div>
                    <div><strong>Generated:</strong>
                        {{ $rds->generated_at ? \Carbon\Carbon::parse($rds->generated_at)->format('d/m/Y H:i:s') : 'N/A' }}
                    </div>
                    <div><strong>Print Count:</strong> {{ $rds->print_count ?? 0 }} ({{ $watermark ?? 'ORIGINAL' }})</div>
                    @if($rds->stm_ref)
                        <div><strong>STM Reference:</strong> {{ $rds->stm_ref }}</div>
                    @endif
                @endif
                @if($instrument)
                    <div><strong>Instrument ID:</strong> {{ $instrument->id }}</div>
                    <div><strong>Status:</strong> {{ ucfirst($instrument->status ?? 'unknown') }}</div>
                @endif
            </div>

        </div>

        <!-- Footer Logo -->
        <div class="mt-auto flex justify-end pt-4">
            <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="KLAES Logo"
                class="h-10 w-auto object-contain">
        </div>
    </div>
    
    @if ($copy < $copiesToPrint - 1)
        <div style="page-break-after: always;"></div>
    @endif
    @endfor

    <script>
        /**
         * Watermark and Print Management
         * Tracks print count and shows appropriate watermark
         */
        let printCount = @json($printCount ?? 0);
        let watermarkType = '@json($watermark ?? "ORIGINAL")';
        const rdsAutoPrint = @json((bool) request()->query('autoprint'));
        const batchSessionId = @json(request()->query('batch_session_id'));
        const batchScope = @json(request()->query('batch_scope'));
        const batchDocId = @json(request()->query('batch_doc_id'));
        const batchToken = @json(request()->query('batch_token'));

        function notifyBatchHost(payload) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage(payload, window.location.origin);
            }

            if (window.opener && !window.opener.closed) {
                window.opener.postMessage(payload, window.location.origin);
            }
        }

        // Log print for audit trail (could be extended to AJAX call)
        window.addEventListener('beforeprint', function () {
            // Print tracking already handled in controller
            console.log('Document printed at: ' + new Date().toLocaleString());
        });

        /**
         * Export to PDF capability (optional)
         */
        function exportToPDF() {
            // This would require additional PDF library integration
            window.print();
        }

        if (rdsAutoPrint) {
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();

                    notifyBatchHost({
                        type: 'klaes-batch-print-finished',
                        url: window.location.href,
                        batch_session_id: batchSessionId,
                        batch_scope: batchScope,
                        batch_doc_id: batchDocId,
                        batch_token: batchToken,
                    });
                }, 500);
            }, { once: true });
        }
    </script>
</body>

</html>