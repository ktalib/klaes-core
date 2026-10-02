<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change of Name - Acknowledgement</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        @media print {
            @page { size: portrait; margin: 1cm; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .print-button, .no-print { display: none !important; }
            .watermark-bg {
                position: fixed !important; z-index: -10 !important;
                top: 50% !important; left: 50% !important;
                transform: translate(-50%, -50%) !important;
            }
        }
    </style>
</head>
<body class="bg-white p-4 font-sans">
    <button onclick="window.print()" class="print-button no-print fixed top-4 right-4 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow-lg flex items-center gap-2 z-50 transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        <span class="font-semibold">Print</span>
    </button>

    <div class="max-w-[800px] mx-auto relative">
        {{-- Header --}}
        <div class="flex items-start justify-between mb-3 pb-3 border-b-2 border-black">
            <div class="w-16 h-16 flex-shrink-0">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Ministry Logo" class="w-full h-full object-contain">
            </div>
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-bold text-black mb-1">MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h3 class="text-base font-bold text-black uppercase">CHANGE OF NAME - ACKNOWLEDGEMENT</h3>
            </div>
            <div class="flex items-start gap-2 flex-shrink-0">
                <div class="w-16 h-16">
                    <img src="{{ qr_data_uri('CON-' . $record->id, 110) }}" alt="QR Code" class="w-full h-full object-contain">
                </div>
                <div class="w-16 h-16">
                    <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Seal" class="w-full h-full object-contain">
                </div>
            </div>
        </div>

        {{-- Watermark --}}
        <div class="watermark-bg opacity-[0.08] pointer-events-none">
            <div class="relative w-[300px] h-[300px]">
                <img src="{{ asset('assets/logo/court_of arms.jpeg') }}" alt="Watermark" class="w-full h-full object-contain">
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-6xl font-bold text-black tracking-[0.2em] transform -rotate-45" style="text-shadow: 0 0 20px rgba(231,227,227,0.3);">ORIGINAL</span>
                </div>
            </div>
        </div>

        {{-- Content --}}
        <div class="grid grid-cols-2 gap-6 mb-4 relative z-10">
            {{-- Left Column --}}
            <div>
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                        <i data-lucide="user" class="h-4 w-4"></i>
                        Name Change Details
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-28">Old Name:</span> <span class="text-black">{{ ucwords(strtolower($record->current_name ?? '-')) }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Title:</span> <span class="text-black">{{ $record->title ?? '-' }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">First Name:</span> <span class="text-black">{{ ucwords(strtolower($record->first_name ?? '-')) }}</span></div>
                        @if($record->middle_name)
                        <div class="flex"><span class="font-semibold text-black w-28">Middle Name:</span> <span class="text-black">{{ ucwords(strtolower($record->middle_name)) }}</span></div>
                        @endif
                        <div class="flex"><span class="font-semibold text-black w-28">Last Name:</span> <span class="text-black">{{ ucwords(strtolower($record->last_name ?? '-')) }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">New Full Name:</span> <span class="text-black font-bold text-emerald-700">{{ strtoupper($record->new_full_name ?? '-') }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Phone:</span> <span class="text-black">{{ $record->phone ?? '-' }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Address:</span> <span class="text-black">{{ ucwords(strtolower($record->residential_address ?? '-')) }}</span></div>
                    </div>
                </div>
            </div>

            {{-- Right Column --}}
            <div>
                <div class="mb-4">
                    <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                        <i data-lucide="file-text" class="h-4 w-4"></i>
                        File Details
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-28">File No:</span> <span class="text-black font-mono font-bold">{{ $record->file_no ?? '-' }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Land Use:</span> <span class="text-black">{{ $record->land_use ?? '-' }}</span></div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                        <i data-lucide="map-pin" class="h-4 w-4"></i>
                        Property Details
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-28">Plot No:</span> <span class="text-black">{{ $record->plot_no ?? '-' }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Plan No:</span> <span class="text-black">{{ $record->plan_no ?? '-' }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Location:</span> <span class="text-black">{{ ucwords(strtolower($record->location ?? '-')) }}</span></div>
                    </div>
                </div>

                <div class="mb-4">
                    <h4 class="text-sm font-bold text-black mb-3 flex items-center gap-2">
                        <i data-lucide="calendar" class="h-4 w-4"></i>
                        Application Details
                    </h4>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex"><span class="font-semibold text-black w-28">Date:</span> <span class="text-black">{{ $record->created_at ? $record->created_at->format('d F Y') : '-' }}</span></div>
                        <div class="flex"><span class="font-semibold text-black w-28">Status:</span> <span class="text-black font-semibold text-emerald-600">{{ ucfirst($record->status) }}</span></div>
                        @if($record->comment)
                        <div class="flex"><span class="font-semibold text-black w-28">Comment:</span> <span class="text-black">{{ $record->comment }}</span></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Signature Section --}}
        <div class="mt-12 mb-4 relative z-10">
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
                    <div class="text-xs text-black text-right">Director of Lands</div>
                </div>
            </div>
        </div>

        {{-- Footer --}}
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
        lucide.createIcons();
    </script>
</body>
</html>
