<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kano State Land Registry - File Tracking Sheet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'kano-green': '#22c55e',
                    'kano-dark-green': '#16a34a',
                }
            }
        }
    } 
    </script>
    <!-- Added print styles for landscape orientation and single page fit -->
    <style>
    @media print {
        @page {
            size: A4 landscape;
            margin: 0.3in;
        }

        body {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
            font-size: 12px !important;
            line-height: 1.2 !important;
        }

        .print-container {
            max-width: none !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            height: auto !important;
        }

        .no-print {
            display: none !important;
        }

        /* Compact spacing for print */
        .print-compact {
            padding: 8px !important;
        }

        .print-compact-small {
            padding: 4px !important;
        }

        .print-text-sm {
            font-size: 11px !important;
        }

        .print-text-xs {
            font-size: 10px !important;
        }
    }

    /* Enhanced gradient backgrounds for headers */
    .enhanced-header {
        background: linear-gradient(135deg, #bdbdbdff 0%, #b6b8b7ff 50%, #9a9b9aff 100%);
        position: relative;
        overflow: hidden;
    }

    .enhanced-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        animation: shimmer 3s infinite;
    }

    @keyframes shimmer {
        0% {
            left: -100%;
        }

        100% {
            left: 100%;
        }
    }
    </style>
</head>

<body class="bg-gray-50 p-2 font-sans">
    <!-- Print button -->
    <div class="no-print mb-6 text-center">
        <button onclick="window.print()"
            class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition-colors duration-200 flex items-center gap-2 mx-auto">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z">
                </path>
            </svg>
            Print Document
        </button>

 
        </div>
    </div>

    <script class="no-print">
    function openBatchTrackingSheets(additionalIds) {
        try {
            const baseUrl = "{{ route('fileindexing.batch-tracking-sheet') }}";
            const currentId = "{{ $fileIndexing->id }}";
            let ids = [currentId];
            if (additionalIds && typeof additionalIds === 'string') {
                additionalIds.split(',').map(s => s.trim()).filter(Boolean).forEach(id => ids.push(id));
            }
            const url = `${baseUrl}?files=${encodeURIComponent(ids.join(','))}`;
            window.open(url, '_blank');
        } catch (e) {
            console.error('Failed to open batch tracking sheets:', e);
            alert('Unable to open batch tracking sheets.');
        }
    }
    </script>

    <div class="print-container max-w-7xl mx-auto bg-white shadow-lg rounded-lg overflow-hidden">
        <!-- Header Section -->
        <div class="border-b-2 border-gray-200 print-compact-small">
            <div class="flex items-center justify-between mb-2">
                <!-- Left Logo - Kano State Logo -->
                <div
                    class="w-12 h-12 border-2 border-gray-300 rounded-lg flex items-center justify-center bg-gray-50 overflow-hidden">
                    <img src="http://klaes.com.ng/assets/logo/logo1.jpg" alt="Kano State Logo"
                        class="w-10 h-10 object-contain" />
                </div>

                <!-- Center Title -->
                <div class="flex-1 mx-4">
                    <div class="text-center border-2 border-gray-300 py-1 px-3 rounded-lg bg-blue-50">
                        <h1 class="text-sm font-bold text-gray-800 print-text-sm">
                            KANO STATE <span class="text-blue-600">MINISTRY OF LAND AND PHYSICAL PLANNING</span>
                        </h1>
                    </div>
                </div>

                <!-- Right Logo - KLAS Logo -->
                <div
                    class="w-12 h-12 border-2 border-gray-300 rounded-lg flex items-center justify-center bg-gray-50 overflow-hidden">
                    <img src="http://klaes.com.ng/assets/logo/logo3.jpeg" alt="KLAS Logo"
                        class="w-10 h-10 object-contain" />
                </div>
            </div>

            <!-- Tracking Info -->
              <div class="flex items-center justify-end w-full">
                <div class="text-right text-xs text-gray-600 print-text-xs">
                    <p><span class="font-semibold">Tracking ID:</span> {{ $tracker->tracking_id }}</p>
                    <p><span class="font-semibold">Generated By:</span>
                        {{ $tracker->sheet_generated_at->format('n/j/Y, g:i:s A') }}</p>
                    @if($tracker->total_prints > 0)
                    <p><span class="font-semibold">Prints:</span> {{ $tracker->total_prints }}</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- File Details Section -->
        <div class="print-compact-small">
            <div class="flex gap-3">
                <!-- Main Details Table -->
                <div class="flex-1">
                    <h3 class="text-sm font-bold text-kano-dark-green mb-2 print-text-sm">File Details</h3>

                    <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                        <!-- Enhanced table header with gradient, better typography and icons -->
                        <div class="enhanced-header text-white relative">
                            <div class="grid grid-cols-2 gap-0 relative z-10">
                                <div
                                    class="px-4 py-2 font-bold text-center border-r border-green-300/50 flex items-center justify-center space-x-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm0 2h12v8H4V6z" />
                                        <path d="M6 8h8v2H6V8zm0 4h8v2H6v-2z" />
                                    </svg>
                                    <span class="text-sm tracking-wide print-text-sm">FILE INFORMATION</span>
                                </div>
                                <div class="px-4 py-2 font-bold text-center flex items-center justify-center space-x-2">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" />
                                        <path fill-rule="evenodd"
                                            d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm tracking-wide print-text-sm">DETAILS</span>
                                </div>
                            </div>
                        </div>

                        <!-- Table Rows -->
                        <div class="divide-y divide-gray-200">
                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">File Number</div>
                                <div class="px-3 py-2 text-sm print-text-xs">{{ $fileIndexing->file_number }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">File Title</div>
                                <div class="px-3 py-2 text-sm print-text-xs">{{ $fileIndexing->file_title }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">Plot Number</div>
                                <div class="px-3 py-2 text-sm print-text-xs {{ empty($fileIndexing->plot_number) ? 'text-gray-500' : '' }}">
                                    {{ $fileIndexing->plot_number ?? 'Unknown' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">Land Use</div>
                                <div class="px-3 py-2 text-sm print-text-xs {{ empty($fileIndexing->land_use_type) ? 'text-gray-500' : '' }}">
                                    {{ $fileIndexing->land_use_type ?? 'Unknown' }}</div>
                            </div>
                         
                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">District/LGA</div>
                                <div class="px-3 py-2 text-sm print-text-xs {{ empty($fileIndexing->lga) ? 'text-gray-500' : '' }}">
                                    {{ $fileIndexing->district ?? 'Unknown' }}/{{ $fileIndexing->lga ?? 'Unknown' }}</div>
                            </div>

                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">Batch/Registry</div>
                                <div class="px-3 py-2 text-sm print-text-xs {{ empty($fileIndexing->batch_no) ? 'text-gray-500' : '' }}">
                                    {{ $fileIndexing->batch_no ?? 'Unknown' }}/{{ $fileIndexing->registry ?? 'Unknown' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-0">
                                <div class="px-3 py-2 bg-gray-50 font-medium border-r border-gray-200 text-sm print-text-xs">Date Created
                                </div>
                                <div class="px-3 py-2 text-sm print-text-xs">{{ $fileIndexing->created_at->format('Y-m-d') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- QR Code Section -->
                <div class="w-32">
                    <div class="border-2 border-gray-300 rounded-lg p-2 text-center">
                        <!-- QR Code -->
                        <div class="w-24 h-24 mx-auto mb-1 bg-gray-200 rounded-lg flex items-center justify-center">
                            @php
                            $qrData = json_encode([
                            'tracking_id' => $tracker->tracking_id,
                            'file_number' => $fileIndexing->file_number,
                            'file_title' => $fileIndexing->file_title,
                            'plot_number' => $fileIndexing->plot_number,
                            'district' => $fileIndexing->district,
                            'lga' => $fileIndexing->lga,
                            'batch_no' => $fileIndexing->batch_no,
                            'registry' => $fileIndexing->registry,
                            'status' => 'Active',
                            'url' => route('fileindexing.show', $fileIndexing->id)
                            ]);
                            $qrCodeUrl = qr_data_uri($qrData, 100);
                            @endphp
                            <img src="{{ $qrCodeUrl }}" alt="QR Code" class="w-20 h-20 rounded" />
                        </div>
                        <!-- <div class="text-xs">
                            <p class="font-bold text-blue-600">SCAN TO VERIFY</p>
                            <p class="font-bold text-blue-600">FILE STATUS</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $fileIndexing->file_number }}</p>
                        </div> -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Signing Section -->
        <div class="print-compact-small pt-0">
            <!-- Enhanced signing section header with better styling -->
            <div class="text-center mb-2">
                <div
                    class="inline-flex items-center space-x-2 bg-gradient-to-r from-kano-dark-green to-kano-green text-white px-4 py-1 rounded-full shadow-lg">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-bold tracking-wider print-text-sm">SIGNING SECTION</span>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                <!-- Enhanced signing header with gradient and better layout -->
                <div class="enhanced-header text-white relative">
                    <div class="grid grid-cols-4 gap-0 relative z-10">
                        <div
                            class="px-3 py-2 font-bold text-center border-r border-green-300/50 flex flex-col items-center space-y-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs tracking-wide print-text-xs">INDEXED BY</span>
                        </div>
                        <div
                            class="px-3 py-2 font-bold text-center border-r border-green-300/50 flex flex-col items-center space-y-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M4 5a2 2 0 012-2v1a1 1 0 001 1h6a1 1 0 001-1V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs tracking-wide print-text-xs">UPLOADED BY <br> (SCAN UPLOAD)</span>
                        </div>
                        <div
                            class="px-3 py-2 font-bold text-center border-r border-green-300/50 flex flex-col items-center space-y-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs tracking-wide print-text-xs">PAGE TYPED BY</span>
                        </div>
                        <div class="px-3 py-2 font-bold text-center flex flex-col items-center space-y-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                    clip-rule="evenodd" />
                            </svg>
                            <span class="text-xs tracking-wide print-text-xs">SUPERVISED BY</span>
                        </div>
                    </div>
                </div>

                <!-- Signing Fields -->
                <div class="grid grid-cols-4 gap-0 h-16">
                    <div class="border-r border-gray-200 p-2"></div>
                    <div class="border-r border-gray-200 p-2"></div>
                    <div class="border-r border-gray-200 p-2"></div>
                    <div class="p-2"></div>
                </div>
            </div>
            <div
                class="flex items-center justify-between border-t border-gray-300 mt-3 pt-2 bg-gray-50 px-3 py-1 rounded-lg">

                <div class="flex items-center flex-1 mr-4">
                    <span class="text-xs font-medium text-gray-700 mr-3 whitespace-nowrap print-text-xs">Authorized Signature:</span>
                    <div class="border-b-2 border-gray-500 flex-1"></div>
                </div>

                <div class="flex items-center w-32">
                    <span class="text-xs font-medium text-gray-700 mr-3 whitespace-nowrap print-text-xs">Date:</span>
                    <div class="border-b-2 border-gray-500 flex-1"></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="bg-gray-100 px-3 py-1 text-center">
            <p class="text-xs text-gray-500 print-text-xs">Kano State Ministry of Land and Physical Planning - File Tracking System
            </p>
        </div>
    </div>
</body>

</html>