<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change of Purpose - Acknowledgement</title>
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

    <div class="max-w-[800px] mx-auto relative min-h-[280mm] flex flex-col">
        {{-- Header --}}
        <div class="flex items-start justify-between mb-3 pb-3 border-b-2 border-black">
            <div class="w-16 h-16 flex-shrink-0">
                <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Ministry Logo" class="w-full h-full object-contain">
            </div>
            <div class="text-center flex-1 px-4">
                <h1 class="text-xl font-bold text-black mb-1">MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
                <h3 class="text-base font-bold text-black uppercase">CHANGE OF PURPOSE - ACKNOWLEDGEMENT</h3>
            </div>
            <div class="flex items-start gap-2 flex-shrink-0">
                <div class="w-16 h-16">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode('COP-' . $record->id) }}" alt="QR Code" class="w-full h-full object-contain">
                </div>
                <div class="w-16 h-16">
                    <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Seal" class="w-full h-full object-contain">
                </div>
            </div>
        </div>

         {{-- Watermark --}}
        
  

        {{-- Footer --}}
        <div class="mt-auto relative z-10 flex items-center justify-between border-t-2 border-black pt-4 pb-8">
            <div class="flex items-center gap-2">
                <img src="{{ asset('assets/logo/klaes.png') }}" alt="KLAES Logo" class="w-24 h-24 object-contain">
                <span class="text-lg font-bold text-black">KLAES</span>
            </div>
            <div class="w-24 h-24">
                <img src="{{ asset('assets/logo/las.jpeg') }}" alt="LAS Logo" class="w-full h-full object-contain">
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
