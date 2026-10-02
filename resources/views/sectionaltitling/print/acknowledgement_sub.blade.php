<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unit/SUA Acknowledgement Slip</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        @media print {
            @page {
                size: landscape;
                margin: 0.5cm;
            }
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            /* Ensure print button is hidden when printing */
            .print-button, .no-print {
                display: none !important;
            }
            /* Ensure watermark stays in background and doesn't affect page breaks */
            .watermark-bg {
                position: fixed !important;
                z-index: -10 !important;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
            }
        }

    </style>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        function markPrintedSub() {
            fetch("{{ url('/sectionaltitling/sub/acknowledgement') }}/{{ $applicationId }}/mark-printed", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            }).catch(() => {});
        }
        window.addEventListener('load', function() { 
            setTimeout(function(){ window.print(); }, 400); 
            // Initialize Lucide icons
            lucide.createIcons();
        });
        window.addEventListener('afterprint', function() { try { markPrintedSub(); } catch(e) {} setTimeout(function(){ window.close(); }, 800); });
    </script>
</head>
<body class="bg-white p-4 font-sans">
    <!-- Added no-print class to ensure button is hidden during printing -->
    <button onclick="window.print()" class="print-button no-print fixed top-4 right-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-lg flex items-center gap-2 z-50 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        <span class="font-semibold">Print</span>
    </button>

    <div class="max-w-[1200px] mx-auto relative">
        <!-- Reduced header margin and padding to save space -->
        <div class="flex items-start justify-between mb-3 pb-3 border-b-2 border-black">
            <!-- Left Logo -->
            <div class="w-16 h-16 flex-shrink-0">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Ministry Logo 1" class="w-full h-full object-contain">
            </div>
            
            <!-- Center Text -->
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-bold text-black mb-1">MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h2 class="text-base font-semibold text-black mb-1">SECTIONAL TITLING DEPARTMENT</h2>
                <h3 class="text-base font-bold text-black uppercase">UNIT/SUA ACKNOWLEDGEMENT SLIP</h3>
            </div>
            
            <!-- Right QR Code and Seal -->
            <div class="flex items-start gap-2 flex-shrink-0">
                <div class="w-16 h-16">
                    <img src="{{ qr_data_uri($applicationId, 110) }}" alt="QR Code" class="w-full h-full object-contain">
                </div>
                <div class="w-16 h-16">
                    <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo 2" class="w-full h-full object-contain">
                </div>
            </div>
        </div>

        <!-- Fixed watermark positioning to stay in background -->
        @if(isset($printedCount) && (int)$printedCount === 0)
        <div class="watermark-bg opacity-[0.08] pointer-events-none">
            <div class="relative w-[300px] h-[300px]">
                <img src="{{ asset('assets/logo/court_of arms.jpeg') }}" alt="Nigerian Coat of Arms Watermark" class="w-full h-full object-contain">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-6xl font-bold text-black tracking-[0.2em] transform -rotate-45" style="text-shadow: 0 0 20px rgba(231, 227, 227, 0.3);">ORIGINAL</span>
                </div>
            </div>
        </div>
        @else
        <div class="watermark-bg opacity-[0.08] pointer-events-none">
            <div class="relative w-[300px] h-[300px]">
                {{-- <img src="{{ asset('assets/logo/court_of arms.jpeg') }}" alt="Nigerian Coat of Arms Watermark" class="w-full h-full object-contain"> --}}
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-6xl font-bold text-black tracking-[0.2em] transform -rotate-45 whitespace-nowrap" style="text-shadow: 0 0 20px rgba(231, 227, 227, 0.3);">COPY OF ORIGINAL</span>
                </div>
            </div>
        </div>
        @endif

        <!-- App Info Section -->
        <div class="flex justify-between mb-4 pb-2 border-b border-gray-300 text-sm relative z-10">
            <span><strong>Application ID:</strong> {{ $applicationId }}</span>
            <span><strong>Date:</strong> {{ date('d/m/Y') }}</span>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-2 gap-4 mb-3 relative z-10">
            <!-- Left Column -->
            <div>
                <!-- Applicant Information -->
                <div class="mb-3">
                    <h4 class="text-sm font-bold text-black mb-2 flex items-center gap-2">
                        <i data-lucide="user" class="h-4 w-4"></i>
                        Applicant Information
                    </h4>
                    <div class="space-y-1 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-20">Type:</span> <span class="text-black">{{ $applicantType }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-20">Name:</span> <span class="text-black">{{ $applicantName }}</span></div>
       
                        <div class="flex"><span class="font-semibold text-black w-20">Phone:</span> <span class="text-black">{{ $applicantPhone }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-20">Address:</span> <span class="text-black">{{ $applicantAddress }}</span></div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Unit Details -->
                <div class="mb-3">
                    <h4 class="text-sm font-bold text-black mb-2 flex items-center gap-2">
                        <i data-lucide="home" class="h-4 w-4"></i>
                        Unit Details
                    </h4>
                    <div class="space-y-1 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-32">Units Type:</span> <span class="text-black">{{ $unitType }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">Units:</span> <span class="text-black">{{ $unitNumber }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">Blocks:</span> <span class="text-black">{{ $blockNumber }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">Unit Size:</span> <span class="text-black">{{ $unitSize }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">File Number(s):</span> <span class="text-black"><strong>NP:</strong> <strong>{{ $npFileNumber }}</strong> | <strong>Unit:</strong> <strong>{{ $unitFileNumber }}</strong></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Address Section -->
        <div class="mb-3 relative z-10">
            <h4 class="text-sm font-bold text-black mb-2 flex items-center gap-2">
                <i data-lucide="map-pin" class="h-4 w-4"></i>
                Property Address
            </h4>
            <div class="text-sm">
                <span class="text-black">{{ $propertyLocation }}</span>
            </div>
            {{-- <div class="grid grid-cols-2 gap-6 text-sm">
                <div class="space-y-1.5">
                    <div class="flex"><span class="font-semibold text-black w-20">LGA:</span> <span class="text-black">{{ $unitLGA }}</span></div>
                    <div class="flex"><span class="font-semibold text-black w-20">District:</span> <span class="text-black">{{ $unitDistrict }}</span></div>
                    <div class="flex"><span class="font-semibold text-black w-20">Address:</span> <span class="text-black">{{ $propertyLocation }}</span></div>
                </div>
                <div class="space-y-1.5">
                    <div class="flex"><span class="font-semibold text-black w-20">House No:</span> <span class="text-black">{{ $blockNumber }}</span></div>
                    <div class="flex"><span class="font-semibold text-black w-20">Plot No:</span> <span class="text-black">-</span></div>
                    <div class="flex"><span class="font-semibold text-black w-20">Street:</span> <span class="text-black">-</span></div>
                </div>
            </div> --}}
        </div>

        <!-- Payment Information -->
        @php
            $normalize = function($v) {
                if (is_null($v) || $v === '') return 0;
                if (is_numeric($v)) return (float)$v;
                $s = preg_replace('/[^0-9.]/', '', (string)$v);
                return $s === '' ? 0 : (float)$s;
            };
            $sum = $normalize($applicationFee) + $normalize($processingFee) + $normalize($sitePlanFee);
            $totalDisplay = (!is_null($totalFee) && $totalFee !== '') ? $totalFee : ($sum > 0 ? number_format($sum, 2) : '-');
            $display = function($v){ return (is_null($v) || $v === '') ? '-' : $v; };
        @endphp
        <div class="mb-3 relative z-10">
            <h4 class="text-sm font-bold text-black mb-2 flex items-center gap-2">
                <i data-lucide="credit-card" class="h-4 w-4"></i>
                Payment Information
            </h4>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                <div class="flex"><span class="font-semibold text-black w-32">Application Fee:</span> <span class="text-black">{{ $display($applicationFee) }}</span></div>
                <div class="flex"><span class="font-semibold text-black w-32">Total:</span> <span class="text-black font-bold">{{ $totalDisplay }}</span></div>
                <div class="flex"><span class="font-semibold text-black w-32">Processing Fee:</span> <span class="text-black">{{ $display($processingFee) }}</span></div>
                <div class="flex"><span class="font-semibold text-black w-32">Receipt No:</span> <span class="text-black">{{ $display($receiptNumber) }}</span></div>
                <div class="flex"><span class="font-semibold text-black w-32">Site Plan Fee:</span> <span class="text-black">{{ $display($sitePlanFee) }}</span></div>
                <div class="flex"><span class="font-semibold text-black w-32">Payment Date:</span> <span class="text-black">{{ $display($paymentDate) }}</span></div>
            </div>
        </div>

        <!-- Required Documents -->
        @if(isset($documentsWithStatus) && !empty($documentsWithStatus))
        <div class="mb-4 relative z-10">
            <h4 class="text-sm font-bold text-black mb-2 flex items-center gap-2">
                <i data-lucide="file-text" class="h-4 w-4"></i>
                Required Documents
            </h4>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                @foreach($documentsWithStatus as $documentName => $isSubmitted)
                <div class="flex">
                    <span class="font-semibold text-black w-36">{{ str_replace('*', '', $documentName) }}:</span>
                    @if($isSubmitted)
                        <span class="text-green-600 font-semibold">Submitted</span>
                    @else
                        <span class="text-red-600 font-semibold">Not Submitted</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Signature Section -->
        <div class="signature-section mt-8 mb-3 relative z-10">
            <div class="grid grid-cols-3 gap-6 items-end">
            <div>
            <div class="border-b border-black mb-4 w-48"></div>
            <div class="text-xs text-black">Director, Sectional Titling</div>
            </div>
            <div class="text-center text-xs text-gray-600">
            Generated on {{ date('d/m/Y') }}
            </div>
            <div>
            <div class="border-b border-black mb-4 w-48 ml-auto"></div>
            <div class="text-xs text-black text-right">Receiving Officer's Signature & Date</div>
            </div>
            </div>
        </div>
         
        <!-- Updated footer to include bottom logos -->
        <div class="footer-section mt-3 relative z-10 flex items-center justify-between">
            <div class="footer-left flex items-center gap-2">
            <img src="{{ asset('assets/logo/klaes.png') }}" alt="KLAES Logo" class="footer-logo w-12 h-12 object-contain">
            <span class="footer-text text-sm font-bold text-black">KLAES</span>
            </div>
            <div class="footer-right w-12 h-12">
            <img src="{{ asset('assets/logo/las.jpeg') }}" alt="LAS Logo" class="footer-logo w-full h-full object-contain">
            </div>
        </div>

        <style>
        /* Custom footer styles */
        .footer-section {
            min-height: 60px;
            padding: 8px 0;
        }
        
        .footer-left {
            align-items: center;
        }
        
        .footer-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        
        .footer-logo {
            max-width: 48px;
            max-height: 48px;
        }
        
        .footer-text {
            font-weight: 700;
            color: #000;
            font-size: 0.875rem;
        }
        
        @media print {
            .footer-section {
            break-inside: avoid;
            page-break-inside: avoid;
            }
            
            .footer-logo {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            }
        }
        </style>
    </div>
</body>
</html>
