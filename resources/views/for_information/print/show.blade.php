<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <title>For Information - {{ $record->serial_no ?? 'LAND 16' }}</title>
    <style>
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            box-sizing: border-box;
        }

        @media print {
            html, body {
                height: 100%;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden;
            }
            @page {
                size: A4;
                margin: 0;
            }
            .no-print { display: none !important; }
            .a4-page {
                width: 210mm;
                height: 297mm;
                padding: 15mm !important;
                margin: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }
        }

        body {
            font-family: 'Arial', sans-serif;
        }

        .dotted-line {
            border-bottom: 1px dotted #000;
            flex-grow: 1;
            height: 18px;
        }

        .watermark {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            font-weight: 800;
            color: rgba(220, 38, 38, 0.12);
            transform: rotate(-20deg);
            pointer-events: none;
            z-index: 1;
        }
    </style>
</head>
<body class="bg-gray-200 flex flex-col items-center p-4">

    <button onclick="window.print()" class="no-print mb-6 bg-black text-white px-10 py-3 rounded-full font-bold shadow-xl hover:opacity-80 transition-all">
        Print Form (LAND 16)
    </button>

    <div class="a4-page bg-white w-[210mm] h-[297mm] p-20 border border-gray-300 shadow-2xl relative text-black box-border flex flex-col">
        @if(($watermark ?? 'ORIGINAL') !== 'ORIGINAL')
            <div class="watermark">{{ $watermark }}</div>
        @endif

        
        <div class="flex justify-between items-start mb-2 relative z-10">
            <div class="w-24">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/Coat_of_arms_of_Nigeria.svg" class="w-20" alt="Nigeria Coat of Arms">
            </div>

            <div class="pt-2 ml-64">
                <img src="{{ qr_data_uri($record->file_number ?? 'KanoLand16', 100) }}" alt="QR Code" class="w-14 h-14 grayscale">
            </div>

            <div class="flex flex-col items-end text-[11px] space-y-1">
                <div class="w-44">
                    <div class="flex justify-between items-center border border-black p-1 px-2 mb-1">
                        <span class="font-bold">CODE:</span>
                        <span>LAND 16</span>
                    </div>
                    <div class="flex justify-between items-center border border-black p-1 px-2 mb-2">
                        <span class="font-bold">SERIAL NO:</span>
                        {{-- <span>{{ $record->serial_no ?? '' }}</span> --}}
                    </div>
                </div>
            </div>
        </div>
  
        <div class="flex justify-end mb-6 relative z-10">
            <div class="text-left leading-tight text-[11px] w-64">
                <p class="font-bold">REF/No/LKN: {{ urlencode($record->file_number ?? '-') }}</p>
                <p>Ministry of Land and Physical Planning</p>
                <p>No. 2 Dr. Bala Mohammed Road,</p>
                <p>Kano State</p>
                <p>P.M.B. 3083</p>
            </div>
        </div>

        <div class="mb-6 text-[14px] font-bold leading-tight relative z-10">
            <p>The Surveyor General</p>
            <p>Survey Department.</p>
        </div>

        <div class="text-center mb-6 relative z-10">
            <h2 class="inline-block border-b-2 border-black font-bold text-lg uppercase px-4">FOR INFORMATION</h2>
        </div>

        <div class="space-y-4 text-[14px] leading-relaxed relative z-10">
            <p class="font-medium">The grantee has accepted the term and conditions of the grant and date of commencement of the</p>

            <div class="flex items-end gap-2">
                <span class="font-bold italic">Tittle</span>
                <div class="dotted-line flex items-end">
                    <span class="font-medium text-sm">{{ $record->title ?? '' }}</span>
                </div>
                <span class="font-medium">day of</span>
                <div class="dotted-line w-40 flex items-end">
                    <span class="font-medium text-sm">{{ $record->commencement_day ?? '' }}</span>
                </div>
            </div>

            <div class="flex gap-4">
                <span class="font-bold">2.</span>
                <p class="font-medium">He has nominated you to survey his plot</p>
            </div>

            <div class="flex gap-4">
                <span class="font-bold">3.</span>
                <p class="font-medium">It is not intended to prepare the Certificate of Occupancy until initial fee has been paid</p>
            </div>
        </div>

        <div class="mt-16 mb-6 flex flex-col items-end relative z-10">
            <div class="w-72 space-y-3 text-[14px]">
                <div class="flex items-end gap-2">
                    <span class="font-bold">Name:</span>
                    <div class="border-b border-black flex-grow h-5">
                        <span class="font-medium text-sm">{{ $record->signer_name ?? '' }}</span>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <span class="font-bold">Rank:</span>
                    <div class="border-b border-black flex-grow h-5">
                        <span class="font-medium text-sm">{{ $record->signer_rank ?? '' }}</span>
                    </div>
                </div>
                <div class="pt-3 border-t border-black text-center font-bold uppercase tracking-wider">
                    FOR: DIRECTOR LAND
                </div>
            </div>
        </div>

        <div class="flex-grow"></div>

    </div>

</body>
</html>
