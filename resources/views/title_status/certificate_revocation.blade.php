<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Revocation &mdash; {{ $record->file_no }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Cinzel:wght@600;700&display=swap');

        .document-body {
            font-family: 'Courier Prime', monospace;
            color: #1a1a1a;
            line-height: 1.6;
        }
        .doc-title {
            font-family: 'Cinzel', serif;
            letter-spacing: 0.05em;
        }
        .fill-text {
            color: #a52a2a;
            font-weight: 700;
            text-transform: uppercase;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            body {
                background-color: #fff;
            }
            .print-container {
                width: 100%;
                max-width: 100%;
                box-shadow: none;
                margin: 0 !important;
                height: 100vh;
                max-height: 297mm;
                overflow: hidden;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-100 py-10 print:py-0">

    {{-- Toolbar (hidden on print) --}}
    <div class="no-print max-w-4xl mx-auto mb-4 flex items-center justify-between px-2">
        <span class="text-sm text-slate-500 font-semibold">
            Certificate of Revocation &mdash; {{ $record->file_no }}
        </span>
        <div class="flex gap-3">
            <button onclick="window.print()"
                class="inline-flex items-center gap-2 px-4 py-2 bg-red-700 text-white rounded-lg text-sm font-semibold hover:bg-red-800 transition">
                &#128438; Print Certificate
            </button>
            <button onclick="window.close()"
                class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold hover:bg-slate-300 transition">
                Close
            </button>
        </div>
    </div>

    {{--
      print:pt-[180px]  — space for pre-printed top header
      print:pb-[100px]  — space for pre-printed bottom frame border
      print:px-[60px]   — content safely within side borders
    --}}
    <div class="print-container max-w-4xl mx-auto bg-white p-12 pt-[100px] pb-[120px]
                print:pt-[180px] print:pb-[100px] print:px-[60px]
                min-h-[1050px] document-body flex flex-col justify-between">

        <div>
            {{-- Serial Number --}}
            {{-- <div class="text-right text-lg font-bold tracking-widest mb-6 pr-4">
                {{ str_pad($record->id, 6, '0', STR_PAD_LEFT) }}
            </div> --}}

            {{-- Document Titles --}}
            <div class="text-center space-y-1 mb-6">
                <h1 class="doc-title text-xl font-bold text-red-700 italic decoration-1 underline underline-offset-4">
                    CERTIFICATE OF REVOCATION
                </h1>
                <h2 class="text-sm font-bold tracking-wide decoration-1 underline underline-offset-4">
                    THE LAND USE ACT 1978 (NO. 6 OF 1978)
                </h2>
            </div>

            {{-- Main Body --}}
            <div class="space-y-4 text-justify text-[14px]">

                {{-- Revocation Reference --}}
                <p class="font-bold">
                    REVOCATION OF CERTIFICATE OF OCCUPANCY NO. :-
                    @php
                        $cofoRef = $record->cofo_number
                            ?: ($record->plot_no
                                ? strtoupper($record->plot_no) . ($record->file_no ? ' -- ' . strtoupper($record->file_no) : '')
                                : strtoupper($record->file_no));
                    @endphp
                    <span class="fill-text">{{ $cofoRef ?: '______________________________' }}</span>
                </p>

                {{-- Clause 1 --}}
                <p>
                    <span class="font-bold">WHEREAS</span> by a Certificate of Occupancy Under the hand of Commissioner of Land and physical
                    planning, Kano State of Nigeria, dated
                    <span class="fill-text">:
                        {{ $record->date_of_issue
                            ? strtoupper($record->date_of_issue->format('jS \D\A\Y \O\F F, Y'))
                            : '______________________________' }}
                    </span>
                    and number which said Certificate of Occupancy was registered as No
                    <span class="fill-text">{{ strtoupper($record->title_no ?? '__________') }}</span>
                    at page
                    <span class="fill-text">{{ strtoupper($record->reg_page ?? '__________') }}</span>
                    in Volume
                    <span class="fill-text">{{ strtoupper($record->reg_volume ?? '__________') }}</span>
                    (Certificates of Occupancy) of the land Registry in the office at kano,
                </p>

                {{-- Clause 2 --}}
                <p>
                    it was certified that:
                    <span class="fill-text">{{ strtoupper($record->applicant_name ?? '______________________________') }}</span>
                </p>

                {{-- Clause 3 --}}
                @php
                    $address = $record->location;
                    if (!$address && ($record->house_no || $record->street_name)) {
                        $parts = array_filter([
                            $record->house_no,
                            $record->street_name,
                            $record->district,
                            $record->lga,
                            'KANO STATE',
                        ]);
                        $address = implode(', ', $parts) . '.';
                    }
                @endphp
                <p>
                    of <span class="fill-text">{{ strtoupper($address ?? '______________________________') }}</span>
                </p>

                {{-- Clause 4 --}}
                <p>
                    Was entitled to a Right of Occupancy over the land with Plot number
                    <span class="fill-text">{{ strtoupper($record->plot_no ?? '______________________________') }}</span>
                    on Plan No.
                    <span class="fill-text">{{ strtoupper($record->plot_no ?? $record->file_no ?? '______________________________') }}</span>
                    at: <span class="fill-text">{{ strtoupper($record->location ?? '______________________________') }}</span>
                    and more particularly described in the scheduled to the said Certificate of Occupancy.
                </p>

                {{-- Clause 5 --}}
                <p>
                    <span class="font-bold">AND WHEREAS</span> the said that all piece of land situated at:
                    <span class="fill-text">{{ strtoupper($record->location ?? '______________________________') }}</span>
                    in
                    <span class="fill-text">{{ strtoupper($record->district ?? $record->lga ?? '______________________________') }}</span>
                    District, covered by the above Certificate of Occupancy consisting of
                    <span class="fill-text">{{ strtoupper($record->land_use ?? '______________________________') }}</span>
                </p>

                {{-- Clause 6 --}}
                <p>
                    <span class="font-bold">NOW THEREFORE</span>, in excercise of the power conferred upon me by section 28 of the Land
                    Use Act 1978, I hereby revoked the Certificate of Occupancy Due to:
                    <span class="fill-text">{{ strtoupper($record->reason ?? '______________________________') }}</span>
                    with effect from the the said right of occupancy of the
                    said: <span class="fill-text">{{ strtoupper($record->applicant_name ?? '______________________________') }}</span>
                    over that piece of land at:
                    <span class="fill-text">{{ strtoupper($record->location ?? '______________________________') }}</span>
                    on the plan Number:
                    <span class="fill-text">{{ strtoupper($record->plot_no ?? $record->file_no ?? '______________________________') }}</span>
                    and more particularly described in the schedule to the said Certificate of Occupancy Number:
                    <span class="fill-text">{{ $cofoRef ?: '______________________________' }}</span>
                </p>

            </div>
        </div>

        {{-- Date and Signature --}}
        <div class="mt-8 break-inside-avoid">
            @php
                $certDate = $record->approved_at
                    ?? ($record->date_of_issue ? \Carbon\Carbon::parse($record->date_of_issue) : null)
                    ?? now();
                if (!($certDate instanceof \Carbon\Carbon)) {
                    $certDate = \Carbon\Carbon::parse($certDate);
                }
            @endphp

            <div class="text-[14px] flex w-full items-baseline gap-2">
                <span class="whitespace-nowrap">DATED This</span>
                <div class="border-b border-black text-center flex-1 min-w-[60px] fill-text italic">{{ $certDate->format('jS') }}</div>
                <span class="whitespace-nowrap">day of</span>
                <div class="border-b border-black text-center flex-[2] min-w-[150px] fill-text italic">{{ $certDate->format('F') }}</div>
                <span class="whitespace-nowrap">, 20</span>
                <div class="border-b border-black text-center flex-1 min-w-[40px] fill-text italic">{{ $certDate->format('y') }}</div>
            </div>

            <p class="text-[10px] text-center italic max-w-md mt-1 mx-auto leading-tight">
                Given under my hand the day and year<br>above written
            </p>

            <div class="mt-10 text-center break-inside-avoid">
                <div class="w-64 border-b border-black mx-auto mb-1"></div>
                <p class="text-xs font-bold uppercase tracking-wider">Honourable Commissioner</p>
                <p class="text-[11px] text-gray-700">Kano State, Nigeria</p>
            </div>
        </div>

    </div>

</body>
</html>
