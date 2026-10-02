<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Final ST Conveyance</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap');

        body {
            font-family: 'Source Sans Pro', sans-serif;
            background-color: #f8f9fa;
            font-size: 12px;
            line-height: 1.2;
        }

        .download-mode .no-print {
            display: none !important;
        }

        .download-mode .document-container,
        .download-mode .document-page {
            height: auto !important;
            min-height: unset !important;
        }

        .document-container {
            width: 21cm;
            height: 29.7cm;
            background: white;
            margin: 0 auto;
            padding: 1cm;
            position: relative;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header-line {
            height: 2px;
            background: linear-gradient(90deg, #1a56db, #1e3a8a);
            margin: 8px 0;
        }

        .signature-line {
            border-bottom: 1px solid #cbd5e1;
            display: inline-block;
            width: 180px;
            margin: 0 5px;
        }

        .short-line {
            width: 100px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            font-size: 11px;
            margin: 10px 0;
        }

        th,
        td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            text-align: left;
        }

        th {
            background-color: #f1f5f9;
            font-weight: 600;
            text-align: center;
        }

        .reference {
            background-color: #f1f5f9;
            padding: 10px;
            border-left: 4px solid #1a56db;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .underline {
            text-decoration: underline;
        }

        /* Compact styling for single page */
        .compact-section {
            margin-bottom: 12px;
        }

        /* Logo styling */
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .logo-left,
        .logo-right {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        .header-text {
            flex: 1;
            text-align: center;
        }

        .document-page {
            position: relative;
            min-height: 27cm;
        }

        /* Page break for buyers section */
        .page-break {
            page-break-before: always;
            break-before: page;
        }

        .buyers-section {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Footer styling for all pages */
        .document-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 10px 0;
            background: white;
            border-top: 1px solid #e5e7eb;
            z-index: 1000;
        }

        /* Hide print button when printing */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                width: 21cm;
                height: 29.7cm;
            }

            .document-container {
                width: 100%;
                height: auto;
                min-height: 29.7cm;
                padding: 1cm;
                margin: 0;
                box-shadow: none;
            }

            .no-print {
                display: none !important;
            }

            .page-break {
                page-break-before: always;
                break-before: page;
            }

            .buyers-section {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            @page {
                size: A4 portrait;
                margin: 0.1cm 0 2cm 0;
                margin-top: 1%;

                @bottom-center {
                    content: "Generated on: " attr(data-date) " by " attr(data-user);
                    font-size: 10px;
                    color: #666;
                }
            }



            .document-footer {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                padding: 5px 0;
                font-size: 10px;
                color: #666;
                background: white;
            }

            /* Ensure content doesn't overlap footer */
            .document-container {
                padding-bottom: 3cm;
            }



        }
    </style>
    @php
        use Illuminate\Support\Carbon;

        $authUser = auth()->user();

        $generatedBy = trim(collect([
            optional($authUser)->first_name,
            optional($authUser)->last_name,
        ])->filter()->implode(' '));

        if ($generatedBy === '') {
            $generatedBy = optional($authUser)->name ?? 'System';
        }

        if ($generatedBy === '') {
            $generatedBy = 'System';
        }

        $generatedTimestamp = Carbon::now();
        $generatedAtDisplay = $generatedTimestamp->format('F j, Y \\a\\t g:i A');

        $rawFileNumber = $application->fileno ?? ($application->np_fileno ?? 'application');
        $sanitizedFileNumber = preg_replace('/[^A-Za-z0-9_-]+/', '_', $rawFileNumber);

        $finalConveyanceRecord = $finalConveyanceRecord ?? null;
        $latestFinalConveyanceUpload = $latestFinalConveyanceUpload ?? null;
        $finalConveyanceAvailable = (int) ($application->final_conveyance_generated ?? 0) === 1 || !empty($finalConveyanceRecord);

        $latestUploadTimestamp = null;
        if ($latestFinalConveyanceUpload && !empty($latestFinalConveyanceUpload->uploaded_at)) {
            try {
                $latestUploadTimestamp = Carbon::parse($latestFinalConveyanceUpload->uploaded_at)->format('F j, Y \\a\\t g:i A');
            } catch (\Exception $e) {
                $latestUploadTimestamp = null;
            }
        }
    @endphp
</head>

<body class="py-2" data-file-number="{{ $sanitizedFileNumber }}" data-date="{{ $generatedAtDisplay }}"
    data-user="{{ $generatedBy }}">
    <div class="no-print flex flex-wrap items-center justify-center lg:justify-between gap-3 mb-4 px-4">
        <div class="flex flex-wrap items-center justify-center gap-3">
            <button onclick="window.print()"
                class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-4 rounded text-sm flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m4 4h6a2 2 0 002-2v-4a2 2 0 00-2-2h-6a2 2 0 00-2 2v4a2 2 0 002 2z" />
                </svg>
                Print Document
            </button>
            @if($finalConveyanceAvailable)
                <button type="button" onclick="exportToWord()"
                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-1 px-4 rounded text-sm flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 3h12l4 4v12a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 11v5m0 0l-3-3m3 3l3-3" />
                    </svg>
                    Download Word (.docx)
                </button>
            @else
                <button type="button"
                    class="bg-gray-200 text-gray-500 font-semibold py-1 px-4 rounded text-sm flex items-center cursor-not-allowed"
                    title="Generate the Final Conveyance first to enable download." disabled>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M4 3h9a1 1 0 01.707.293l2 2A1 1 0 0116 6v9a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2z" />
                        <path d="M9 8h2v5h3l-4 4-4-4h3V8z" />
                    </svg>
                    Download Word (.docx)
                </button>
            @endif
        </div>
    </div>

    <!-- Document Container -->
    <div id="document-container" class="document-container" data-date="{{ $generatedAtDisplay }}"
        data-user="{{ $generatedBy }}">
        <div class="document-page page-1">
            <!-- Letter Head with Logos -->
            <div class="logo-container">
                <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Ministry Logo Left" class="logo-left">
                <div class="header-text">
                    <h1 class="text-lg font-bold text-blue-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                    <p class="text-lg font-bold text-blue-800">KANO STATE, NIGERIA</p>
                    <p class="text-base font-semibold text-blue-700 mt-1">SECTIONAL TITLING DEPARTMENT</p>
                    <p class="text-xs text-gray-700 mt-1">No2 Dr Bala Muhammad Road, Nassarawa GRA, Kano State.</p>
                </div>
                <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Ministry Logo Right" class="logo-right">
            </div>



            <!-- Recipient Address -->
            <div class="compact-section">
                @php
                    // Get applicant name based on type
                    $applicantName = '';
                    if ($application->applicant_type == 'individual') {
                        $applicantName = ($application->applicant_title ?? '') . ' ' . ($application->first_name ?? '') . ' ' . ($application->surname ?? '');
                    } elseif ($application->applicant_type == 'corporate') {
                        $applicantName = $application->corporate_name ?? '';
                    } elseif ($application->applicant_type == 'multiple') {
                        $applicantName = $application->multiple_owners_names ?? '';
                    }
                    $applicantName = trim($applicantName);
                @endphp
                <p class="font-bold">{{ strtoupper($applicantName ?: '______') }}</p>
                <p>{{ strtoupper($application->address ?? '-') }}</p>

            </div>

            <!-- Title -->
            <h2 class="text-lg font-bold text-center my-3 text-blue-800">SECTIONAL TITLING CONVEYANCE</h2>

            <!-- Reference -->
            <div class="reference">
                @php
                    // Build property location from individual fields
                    $propertyLocationParts = [];
                    if (!empty($application->property_house_no)) {
                        $propertyLocationParts[] = 'House No: ' . $application->property_house_no;
                    }
                    if (!empty($application->property_plot_no)) {
                        $propertyLocationParts[] = 'Plot No: ' . $application->property_plot_no;
                    }
                    if (!empty($application->property_street_name)) {
                        $propertyLocationParts[] = $application->property_street_name;
                    }
                    if (!empty($application->property_district)) {
                        $propertyLocationParts[] = $application->property_district;
                    }
                    if (!empty($application->property_lga)) {
                        $propertyLocationParts[] = $application->property_lga;
                    }
                    $propertyLocation = !empty($propertyLocationParts) ? implode(', ', $propertyLocationParts) : ($application->layout_name ?? $application->property_location ?? '______');
                @endphp
                @php
                    // Get CofO number from database
                    $cofoNumber = DB::connection('sqlsrv')
                        ->table('CofO')
                        ->where('application_id', $application->id)
                        ->value('cofo_no');
                    $fileno = $application->fileno ?? '______';
                @endphp
                <p class="font-bold underline">
                    RE: APPLICATION FOR FRAGMENTATION IN RESPECT OF PROPERTY
                    @if($cofoNumber)
                        WITH C-OF-O NO:
                        <span class="underline font-bold">
                            {{ strtoupper($cofoNumber) }}
                        </span>
                    @else
                        WITH OLD STATUTORY FILE NO: {{ strtoupper($fileno) }}
                    @endif
                    LOCATED AT
                    <span class="underline font-bold">{{ strtoupper($propertyLocation) }}</span>
                    IN THE NAME OF
                    <span class="underline font-bold">{{ strtoupper($applicantName ?: '______') }}</span>
                </p>
            </div>

            @php
                $sharedUtilities = DB::connection('sqlsrv')
                    ->table('shared_utilities')
                    ->where('application_id', $application->id)
                    ->select('id', 'application_id', 'utility_type', 'dimension')
                    ->get();

                $sharedUtilitiesSummary = '-';

                if ($sharedUtilities->count() > 0) {
                    $summaryString = $sharedUtilities
                        ->map(function ($utility) {
                            $name = strtoupper(trim($utility->utility_type ?? ''));
                            return $name !== '' ? $name : null;
                        })
                        ->filter()
                        ->unique()
                        ->values()
                        ->implode(', ');

                    if ($summaryString !== '') {
                        $sharedUtilitiesSummary = '“' . $summaryString . '”';
                    } else {
                        $sharedUtilitiesSummary = '-';
                    }
                }
            @endphp

            <!-- Main Content -->
            <div class="compact-section">
                <p style="font-size: 13px;">
                    Reference to your application for sectional titling dated <span
                        class="font-semibold">{{ isset($application->application_date) ? \Carbon\Carbon::parse($application->application_date)->format('d/m/Y') : (isset($application->created_at) ? \Carbon\Carbon::parse($application->created_at)->format('d/m/Y') : '-') }}</span>,
                    am directed to convey the approval of Honorable Commissioner regarding the above caption,
                    in line with the Sectional Titling Law under the provisions of the Kano State Sectional and
                    Systematic Land Titling and Registration Law, 2024; Relevant State Development and Planning
                    regulations.
                </p>
                <p class="mt-2" style="font-size: 13px;">
                    Meanwhile you may please be informed that, your title is now sectioned into
                    <span class="font-semibold">{{ $application->NoOfSections ?? '0' }}</span> sections,
                    <span class="font-semibold">{{ $application->NoOfUnits ?? '0' }}</span> units described below and
                    with
                    <span class="font-semibold">{{ $sharedUtilitiesSummary }}</span> as shared Utilities / Common Areas.
                </p>
            </div>

            <!-- Shared Properties Table -->
            @if(count($sharedUtilities) > 0)
                <table class="compact-section">
                    <thead>
                        <tr>
                            <th style="width: 50px;">SN</th>
                            <th>DESCRIPTION</th>
                            <th>DIMENSION (m²)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sharedUtilities as $index => $utility)
                            <tr>
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td>{{ strtoupper($utility->utility_type ?? '-') }}</td>
                                <td>{{ $utility->dimension ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <!-- Buyers List Table -->
            <table>
                <thead>
                    <tr>
                        <th>SN</th>
                        <th>BUYER NAME</th>
                        <th>UNIT NO</th>
                        <th>SECTION</th>
                        <th>MEASUREMENT M²</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // Query buyers from database with measurements and dimensions
                        $buyers = DB::connection('sqlsrv')
                            ->table('buyer_list as bl')
                            ->leftJoin('st_unit_measurements as sum', function ($join) use ($application) {
                                $join->on('bl.id', '=', 'sum.buyer_id')
                                    ->where('sum.application_id', '=', $application->id);
                            })
                            ->where('bl.application_id', $application->id)
                            ->select(
                                // Preserve every buyer record. Without the primary key,
                                // DISTINCT merged different buyers that happened to have
                                // identical visible details.
                                'bl.id',
                                'bl.buyer_title',
                                'bl.buyer_name',
                                'bl.unit_no',
                                'bl.section_number',
                                'sum.measurement'
                            )
                            ->distinct()
                            ->get();
                    @endphp

                    @if(count($buyers) > 0)
                        @foreach($buyers as $index => $buyer)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ strtoupper($buyer->buyer_title ? $buyer->buyer_title . ' ' : '') }}{{ strtoupper($buyer->buyer_name) }}
                                </td>
                                <td>{{ $buyer->unit_no }}</td>
                                <td>
                                    {{ ($buyer->section_number ?? '') !== '' ? strtoupper($buyer->section_number) : '-' }}
                                </td>
                                <td>{{ $buyer->measurement ?? '-' }}</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5">No buyers found.</td>
                        </tr>
                    @endif
                </tbody>
            </table>

            <div class="compact-section mt-4">
                <p>
                    In view of this, you are expected to write an acceptance letter, Submit the Original Title document
                    in your possession for cancellation and Commissioning of new sectional units file(s).
                    You may also refer to the table above for the list of buyers, Units number, section and dimensions /
                    measurement of each unit in square meters (SQM) for further guidance.
                </p>
            </div>

            <!-- Closing -->
            <div class="compact-section mt-4">
                <p>Above is for your information.</p>
                <p class="mt-2">Best Regards.</p>
            </div>

            <!-- Signature Section -->
            <div class="mt-4">

                <p class="text-sm">Assistant Chief Land Officer</p>
                <p class="text-sm italic">For: Hon. Commissioner</p>

                <div class="flex mt-3">
                    <div class="mr-6">
                        <p class="text-sm">Sign: <span class="signature-line"></span></p>
                    </div>
                    <div>
                        <p class="text-sm">Date: <span class="signature-line short-line"></span></p>
                    </div>
                </div>
            </div>

        </div>


    </div> <!-- End of Document Container -->

    <!-- Fixed Footer for all pages -->

    <!-- Word document generation -->
    <script src="https://unpkg.com/docx@8.2.2/build/index.umd.js"></script>
    <script src="https://unpkg.com/file-saver@2.0.5/dist/FileSaver.min.js"></script>

    <script>
        (function syncPrintMeta() {
            const meta = {
                date: @json($generatedAtDisplay),
                user: @json($generatedBy),
            };

            [document.documentElement, document.body, document.getElementById('document-container')]
                .filter(Boolean)
                .forEach((el) => {
                    el.setAttribute('data-date', meta.date);
                    el.setAttribute('data-user', meta.user);
                });
        })();

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // Action menu toggle
        const fcActionToggle = document.getElementById('fc-action-toggle');
        const fcActionMenu = document.getElementById('fc-action-menu');
        if (fcActionToggle && fcActionMenu) {
            fcActionToggle.addEventListener('click', (event) => {
                event.stopPropagation();
                fcActionMenu.classList.toggle('hidden');
            });

            document.addEventListener('click', (event) => {
                if (!event.target.closest('.fc-action-dropdown')) {
                    fcActionMenu.classList.add('hidden');
                }
            });
        }

        const leftLogoUrl = @json(asset('assets/logo/ministry1.jpg'));
        const rightLogoUrl = @json(asset('assets/logo/ministry2.png'));

        async function fetchLogoArrayBuffer(url) {
            if (!url) return null;
            try {
                const response = await fetch(url, { cache: 'force-cache' });
                if (!response.ok) throw new Error(`Failed to fetch logo: ${response.status}`);
                return await response.arrayBuffer();
            } catch (error) {
                console.warn('Logo load failed for', url, error);
                return null;
            }
        }

        async function exportToWord() {
            try {
                if (!window.docx) throw new Error('docx library is unavailable.');

                const {
                    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
                    AlignmentType, WidthType, Footer, ImageRun, BorderStyle, UnderlineType
                } = docx;

                const tableBordersNone = {
                    top: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    bottom: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    left: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    right: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    insideHorizontal: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                    insideVertical: { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' },
                };

                const [leftLogoData, rightLogoData] = await Promise.all([
                    fetchLogoArrayBuffer(leftLogoUrl),
                    fetchLogoArrayBuffer(rightLogoUrl),
                ]);

                @php
                    $jsBuyers = $buyers->map(function ($b, $i) use ($application) {
                        return [
                            'sn' => $i + 1,
                            'name' => trim(($b->buyer_title ? $b->buyer_title . ' ' : '') . ($b->buyer_name ?? '')),
                            'unitNo' => $b->unit_no ?? '-',
                            'section' => $b->section_number ?? '-',
                            'landUse' => $b->land_use ?? $application->land_use ?? '-',
                            'dimension' => $b->measurement ?? '-'
                        ];
                    });

                    $jsUtilities = $sharedUtilities->map(function ($u, $i) {
                        return [
                            'sn' => $i + 1,
                            'description' => $u->utility_type ?? '-',
                            'dimension' => $u->dimension ?? '-'
                        ];
                    });
                @endphp

                // Prepare Data
                const appData = {
                    applicantName: @json(strtoupper($applicantName)),
                    applicantAddress: @json(strtoupper($application->address ?? '-')),
                    propertyLocation: @json(strtoupper($propertyLocation)),
                    fileNo: @json(strtoupper($application->fileno ?? ($application->np_fileno ?? '______'))),
                    cofoNo: @json($cofoNumber ? strtoupper($cofoNumber) : null),
                    noOfSections: @json($application->NoOfSections ?? '0'),
                    noOfUnits: @json($application->NoOfUnits ?? '0'),
                    sharedUtilitiesSummary: @json($sharedUtilitiesSummary),
                    generatedDate: @json($generatedAtDisplay),
                    generatedBy: @json($generatedBy)
                };

                const buyersData = @json($jsBuyers);
                const utilitiesData = @json($jsUtilities);

                const sectionChildren = [];

                // 1. Header Table (Logos and Titles)
                const headerCells = [];

                // Left Logo
                headerCells.push(new TableCell({
                    width: { size: 15, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    children: leftLogoData ? [new Paragraph({
                        children: [new ImageRun({ data: leftLogoData, transformation: { width: 65, height: 65 } })],
                        alignment: AlignmentType.LEFT
                    })] : []
                }));

                // Center Titles
                headerCells.push(new TableCell({
                    width: { size: 70, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    children: [
                        new Paragraph({
                            children: [new TextRun({ text: "MINISTRY OF LAND AND PHYSICAL PLANNING", bold: true, size: 24, color: "1e3a8a" })],
                            alignment: AlignmentType.CENTER
                        }),
                        new Paragraph({
                            children: [new TextRun({ text: "KANO STATE, NIGERIA", bold: true, size: 24, color: "1e3a8a" })],
                            alignment: AlignmentType.CENTER
                        }),
                        new Paragraph({
                            children: [new TextRun({ text: "SECTIONAL TITLING DEPARTMENT", bold: true, size: 22, color: "1a56db" })],
                            alignment: AlignmentType.CENTER
                        }),
                        new Paragraph({
                            children: [new TextRun({ text: "No2 Dr Bala Muhammad Road, Nassarawa GRA, Kano State.", size: 16, italic: true })],
                            alignment: AlignmentType.CENTER
                        })
                    ]
                }));

                // Right Logo
                headerCells.push(new TableCell({
                    width: { size: 15, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    children: rightLogoData ? [new Paragraph({
                        children: [new ImageRun({ data: rightLogoData, transformation: { width: 65, height: 65 } })],
                        alignment: AlignmentType.RIGHT
                    })] : []
                }));

                sectionChildren.push(new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    rows: [new TableRow({ children: headerCells })]
                }));

                sectionChildren.push(new Paragraph({ spacing: { before: 200, after: 200 } }));

                // 2. Applicant Info
                sectionChildren.push(new Paragraph({ children: [new TextRun({ text: appData.applicantName, bold: true, size: 20 })] }));
                sectionChildren.push(new Paragraph({ children: [new TextRun({ text: appData.applicantAddress, size: 20 })], spacing: { after: 300 } }));

                // 3. Document Title
                sectionChildren.push(new Paragraph({
                    children: [new TextRun({ text: "SECTIONAL TITLING CONVEYANCE", bold: true, size: 28, color: "1e3a8a" })],
                    alignment: AlignmentType.CENTER,
                    spacing: { after: 300 }
                }));

                // 4. Reference Box
                const refText = `RE: APPLICATION FOR FRAGMENTATION IN RESPECT OF PROPERTY ${appData.cofoNo ? 'WITH C-OF-O NO: ' + appData.cofoNo : 'WITH OLD STATUTORY FILE NO: ' + appData.fileNo} LOCATED AT ${appData.propertyLocation} IN THE NAME OF ${appData.applicantName}`;

                sectionChildren.push(new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    borders: {
                        top: { style: BorderStyle.SINGLE, size: 6, color: "cbd5e1" },
                        bottom: { style: BorderStyle.SINGLE, size: 6, color: "cbd5e1" },
                        left: { style: BorderStyle.SINGLE, size: 6, color: "cbd5e1" },
                        right: { style: BorderStyle.SINGLE, size: 6, color: "cbd5e1" }
                    },
                    rows: [new TableRow({
                        children: [new TableCell({
                            shading: { fill: "f1f5f9" },
                            margins: { top: 100, bottom: 100, left: 100, right: 100 },
                            children: [new Paragraph({
                                children: [new TextRun({ text: refText, bold: true, underline: { type: UnderlineType.SINGLE } })]
                            })]
                        })]
                    })]
                }));

                sectionChildren.push(new Paragraph({ spacing: { before: 300 } }));

                // 5. Main Paragraphs
                const pStyle = { spacing: { after: 200 }, alignment: AlignmentType.JUSTIFY };
                sectionChildren.push(new Paragraph({
                    children: [new TextRun({ text: "I am directed to inform you that your application for fragmentation of the above mentioned property has been considered and subsequently approved as Sectional Titles.", size: 20 })],
                    ...pStyle
                }));
                sectionChildren.push(new Paragraph({
                    children: [new TextRun({ text: "Approval is in line with the Sectional Titling Law under the provisions of the Kano State Sectional and Systematic Land Titling and Registration Law, 2024; Relevant State Development and Planning regulations.", size: 20 })],
                    ...pStyle
                }));

                const unitPara = new Paragraph({
                    children: [
                        new TextRun({ text: "Meanwhile you may please be informed that, your title is now sectioned into ", size: 20 }),
                        new TextRun({ text: `${appData.noOfSections} sections, `, bold: true, size: 20 }),
                        new TextRun({ text: `${appData.noOfUnits} units described below and with `, bold: true, size: 20 }),
                        new TextRun({ text: `${appData.sharedUtilitiesSummary} as shared Utilities / Common Areas.`, bold: true, size: 20 })
                    ],
                    ...pStyle
                });
                sectionChildren.push(unitPara);

                sectionChildren.push(new Paragraph({ spacing: { before: 200 } }));

                // 6. Shared Utilities Table
                if (utilitiesData.length > 0) {
                    sectionChildren.push(new Paragraph({
                        children: [new TextRun({ text: "Shared Utilities / Common Areas Details:", bold: true, size: 22, color: "1e3a8a" })],
                        spacing: { after: 100 }
                    }));

                    const utilRows = [
                        new TableRow({
                            children: [
                                new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "SN", bold: true })], alignment: AlignmentType.CENTER })] }),
                                new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "DESCRIPTION", bold: true })] })] }),
                                new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "DIMENSION (m²)", bold: true })], alignment: AlignmentType.CENTER })] })
                            ]
                        })
                    ];

                    utilitiesData.forEach(u => {
                        utilRows.push(new TableRow({
                            children: [
                                new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(u.sn) })], alignment: AlignmentType.CENTER })] }),
                                new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(u.description).toUpperCase() })] })] }),
                                new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(u.dimension) })], alignment: AlignmentType.CENTER })] })
                            ]
                        }));
                    });

                    sectionChildren.push(new Table({
                        width: { size: 100, type: WidthType.PERCENTAGE },
                        rows: utilRows
                    }));
                    sectionChildren.push(new Paragraph({ spacing: { before: 300 } }));
                }

                // 7. Buyers Table
                sectionChildren.push(new Paragraph({
                    children: [new TextRun({ text: "Buyers and Units Allocation Details:", bold: true, size: 22, color: "1e3a8a" })],
                    spacing: { after: 100 }
                }));

                const buyerRows = [
                    new TableRow({
                        children: [
                            new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "SN", bold: true })], alignment: AlignmentType.CENTER })] }),
                            new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "BUYER NAME", bold: true })] })] }),
                            new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "UNIT NO", bold: true })], alignment: AlignmentType.CENTER })] }),
                            new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "SECTION", bold: true })], alignment: AlignmentType.CENTER })] }),
                            new TableCell({ shading: { fill: "f1f5f9" }, children: [new Paragraph({ children: [new TextRun({ text: "MEASUREMENT M²", bold: true })] })] })
                        ]
                    })
                ];

                buyersData.forEach(b => {
                    buyerRows.push(new TableRow({
                        children: [
                            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(b.sn) })], alignment: AlignmentType.CENTER })] }),
                            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(b.name).toUpperCase() })] })] }),
                            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(b.unitNo).toUpperCase() })], alignment: AlignmentType.CENTER })] }),
                            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(b.section).toUpperCase() })], alignment: AlignmentType.CENTER })] }),
                            new TableCell({ children: [new Paragraph({ children: [new TextRun({ text: String(b.dimension) })] })] })
                        ]
                    }));
                });

                sectionChildren.push(new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    rows: buyerRows
                }));

                sectionChildren.push(new Paragraph({ spacing: { before: 300 } }));

                // 8. Closing
                sectionChildren.push(new Paragraph({
                    children: [new TextRun({ text: "In view of this, you are expected to write an acceptance letter, Submit the Original Title document in your possession for cancellation and Commissioning of new sectional units file(s).", size: 20 })],
                    ...pStyle
                }));
                sectionChildren.push(new Paragraph({
                    children: [new TextRun({ text: "You may also refer to the table above for the list of buyers, Units number, section and dimensions / measurement of each unit in square meters (SQM) for further guidance.", size: 20 })],
                    ...pStyle
                }));
                sectionChildren.push(new Paragraph({ children: [new TextRun({ text: "Above is for your information.", size: 20 })], spacing: { after: 100 } }));
                sectionChildren.push(new Paragraph({ children: [new TextRun({ text: "Best Regards.", size: 20 })], spacing: { after: 300 } }));

                // 9. Signature Block
                sectionChildren.push(new Paragraph({ children: [new TextRun({ text: "Assistant Chief Land Officer", size: 20 })] }));
                sectionChildren.push(new Paragraph({ children: [new TextRun({ text: "For: Hon. Commissioner", size: 20, italic: true })], spacing: { after: 300 } }));

                sectionChildren.push(new Table({
                    width: { size: 100, type: WidthType.PERCENTAGE },
                    borders: tableBordersNone,
                    rows: [new TableRow({
                        children: [
                            new TableCell({ borders: tableBordersNone, children: [new Paragraph({ children: [new TextRun({ text: "Sign: ____________________", size: 20 })] })] }),
                            new TableCell({ borders: tableBordersNone, children: [new Paragraph({ children: [new TextRun({ text: "Date: ____________________", size: 20 })] })] })
                        ]
                    })]
                }));

                const doc = new Document({
                    creator: "KLAES GIS EDMS",
                    title: "Final Conveyance Agreement",
                    sections: [{
                        properties: { page: { margin: { top: 720, right: 720, bottom: 720, left: 720 } } },
                        footers: {
                            default: new Footer({
                                children: [new Paragraph({
                                    children: [new TextRun({ text: `Generated on: ${appData.generatedDate} by ${appData.generatedBy}`, size: 16, color: "666666" })],
                                    alignment: AlignmentType.CENTER
                                })]
                            })
                        },
                        children: sectionChildren
                    }]
                });

                const buffer = await Packer.toBlob(doc);
                const filename = `${appData.fileNo.replace(/[^a-zA-Z0-9]/g, '_')}_Final_Conveyance.docx`;
                saveAs(buffer, filename);

            } catch (error) {
                console.error('Error generating Word document:', error);
                alert('Error generating Word document. Please contact system administrator.');
            }
        }
    </script>
</body>

</html>
