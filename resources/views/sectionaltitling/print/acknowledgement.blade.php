<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fragmentation Acknowledgment Sheet</title>
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
                <h3 class="text-base font-bold text-black uppercase">FRAGMENTATION ACKNOWLEDGMENT SHEET</h3>
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

        <!-- Updated content layout to two-column format -->
        <!-- Main Content Grid -->
        <div class="grid grid-cols-2 gap-6 mb-4 relative z-10">
            <!-- Left Column -->
            <div>
                <!-- Applicant Information -->
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                        <i data-lucide="user" class="h-4 w-4"></i>
                        Applicant Information
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-20">Type:</span> <span class="text-black">{{ $applicantType }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-20">Name:</span> <span class="text-black">{{ ucwords(strtolower($applicantName)) }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-20">Phone:</span> <span class="text-black">{{ $applicantPhone }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-20">Address:</span> <span class="text-black">{{ ucwords(strtolower($applicantAddress)) }}</span></div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Property Details -->
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                        <i data-lucide="building" class="h-4 w-4"></i>
                        Property Details
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-32">Units:</span> <span class="text-black">{{ $units }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">Blocks:</span> <span class="text-black">{{ $blocks }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">Sections:</span> <span class="text-black">{{ $sections }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-32">File Number(s):</span> <span class="text-black"><strong>{{ $npFileNumber }}</strong> | <strong>{{ $fileNumber }}</strong></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Address (Full Width) -->
        <div class="mb-4 relative z-10">
            <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                <i data-lucide="map-pin" class="h-4 w-4"></i>
                Property Address
            </h4>
            <div class="text-sm">
                <span class="text-black">{{ ucwords(strtolower($propertyFullAddress)) }}</span>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="mb-4 relative z-10">
            <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                <i data-lucide="credit-card" class="h-4 w-4"></i>
                Payment Information
            </h4>
            <div class="grid grid-cols-2 gap-x-8 gap-y-1.5 text-sm">
                <div class="flex">
                    <span class="font-semibold text-black w-32">Application Fee:</span> 
                    @if(isset($paymentStatus['application_fee_paid']) && $paymentStatus['application_fee_paid'])
                        <span class="text-green-600 font-semibold">Paid</span>
                    @else
                        <span class="text-red-600 font-semibold">Unpaid</span>
                    @endif
                </div>
                <div class="flex"><span class="font-semibold text-black w-32">ST Invoice No:</span> <span class="text-black">{{ $receiptNumber ?? '-' }}</span></div>
                <div class="flex">
                    <span class="font-semibold text-black w-32">Processing Fee:</span> 
                    @if(isset($paymentStatus['processing_fee_paid']) && $paymentStatus['processing_fee_paid'])
                        <span class="text-green-600 font-semibold">Paid</span>
                    @else
                        <span class="text-red-600 font-semibold">Unpaid</span>
                    @endif
                </div>
                <div class="flex"><span class="font-semibold text-black w-32">Payment Date:</span> <span class="text-black">{{ $paymentDate ?? '-' }}</span></div>
                <div class="flex">
                    <span class="font-semibold text-black w-32">Site Plan Fee:</span> 
                    @if(isset($paymentStatus['site_plan_fee_paid']) && $paymentStatus['site_plan_fee_paid'])
                        <span class="text-green-600 font-semibold">Paid</span>
                    @else
                        <span class="text-red-600 font-semibold">Unpaid</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Required Documents -->
        @if(isset($documentsWithStatus) && !empty($documentsWithStatus))
        <div class="mb-6 relative z-10">
            <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                <i data-lucide="file-text" class="h-4 w-4"></i>
                Required Documents
            </h4>
            <div class="grid grid-cols-2 gap-x-8 gap-y-1.5 text-sm">
                @foreach($documentsWithStatus as $documentName => $isSubmitted)
                <div class="flex">
                    @if(str_contains($documentName, '*'))
                        <span class="font-semibold text-black w-40">{{ str_replace('*', '', $documentName) }}<span style="color: #dc3545;">*</span>:</span>
                    @else
                        <span class="font-semibold text-black w-40">{{ $documentName }}:</span>
                    @endif
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

        <!-- Updated signature section and footer with proper layout and Klaes logo -->
        <!-- Signature Section -->
        <div class="mt-8 mb-4 relative z-10">
            <div class="grid grid-cols-3 gap-8 items-end">
                <div>
                    <div class="border-b border-black mb-4 w-48"></div>
                    <div class="text-xs text-black">Applicant's Signature & Date</div>
                </div>
                <div class="text-center text-xs text-gray-600">
                    Generated by {{ auth()->user()->first_name ?? '' }} {{ auth()->user()->last_name ?? '' }} on {{ date('d/m/Y') }}
                </div>
                <div>
                    <div class="border-b border-black mb-4 w-48 ml-auto"></div>
                    <div class="text-xs text-black text-right">Director, Sectional Titling</div>
                </div>
            </div>
        </div>

        <!-- Updated footer to include bottom right seal/logo -->
        <div class="mt-2 relative z-10 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <img src="{{ asset('assets/logo/klaes.png') }}" alt="KLAES Logo" class="w-12 h-12 object-contain">
                <span class="text-sm font-bold text-black">KLAES</span>
            </div>
            <div class="w-12 h-12">
                <img src="{{ asset('assets/logo/las.jpeg') }}" alt="LAS Logo" class="w-full h-full object-contain">
            </div>
        </div>
    </div> 
    <script>
        function markPrinted() {
            // Detect if this is a sub or primary by checking presence of a hint in the URL
            const isSub = /\/sectionaltitling\/sub\/acknowledgement\//.test(window.location.pathname);
            const url = isSub
                ? "{{ url('/sectionaltitling/sub/acknowledgement') }}/{{ $applicationId }}/mark-printed"
                : "{{ url('/sectionaltitling/primary/acknowledgement') }}/{{ $applicationId }}/mark-printed";
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({})
            }).catch(() => {});
        }

        // Track print count to hide watermark on subsequent prints
        let printCount = 0;
        
        window.addEventListener('load', function() { setTimeout(function(){ window.print(); }, 400); });
        window.addEventListener('afterprint', function() {
            printCount++;
            // Hide court of arms watermark after first print
            if (printCount >= 1) {
                const watermarkImage = document.querySelector('.watermark-image');
                if (watermarkImage) {
                    watermarkImage.style.display = 'none';
                }
            }
            
            try { markPrinted(); } catch(e) {}
            setTimeout(function(){ window.close(); }, 800);
        });
        
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>
</html>
