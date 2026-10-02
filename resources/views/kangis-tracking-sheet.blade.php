<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KANGIS New File Tracking Sheet</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { size: landscape; margin: 0.25in; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 12px; }
            .no-print { display: none !important; }
            .page-break { page-break-after: always; }
        }
        .sig-table { width: 100%; border-collapse: collapse; }
        .sig-table th, .sig-table td {
            border: 1px solid #1a1a1a;
            padding: 3px 4px;
            text-align: left;
            font-size: 10px;
            vertical-align: middle;
        }
        .sig-table th { background-color: #e8f0fe; font-weight: 700; text-align: left; }
        .sig-cell-tall td { height: 42px; }
        .tracking-band {
            background: #f0f4ff;
            border: 1px solid #bfcbf8;
            border-top: none;
            padding: 4px 12px;
            font-size: 11px;
        }
    </style>
</head>
<body class="bg-white font-sans text-sm">

    <!-- Print Button (screen only) -->
    <div class="no-print text-center my-4">
        <button onclick="window.print()"
            class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-8 rounded-lg shadow transition">
            Print Tracking Sheet(s)
        </button>
        <a href="{{ route('fileindexing.index') }}"
            class="ml-4 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg shadow transition">
            Back
        </a>
    </div>

    @if(request()->boolean('autoprint'))
    <script>
        window.addEventListener('load', function () {
            // Small delay so QR codes and images can render
            setTimeout(function () { window.print(); }, 600);
        });
    </script>
    @endif
    </div>

    @forelse($fileIndexings as $index => $fileIndexing)
        @php
            $tracker   = $trackers[$fileIndexing->new_kangis_file_no] ?? null;
            $step      = $tracker ? (int)($tracker->workflow_step ?? 1) : 1;
            $meta      = $tracker ? json_decode($tracker->module_meta ?? '{}', true) : [];
            $stages    = [
                1 => ['label' => 'Origin (Customer Service)'],
                2 => ['label' => 'One Stop Shop (KANGIS OSS)'],
                3 => ['label' => 'Verification'],
                4 => ['label' => 'Geometry (GIS)'],
                5 => ['label' => 'Vetting (Committee)'],
                6 => ['label' => 'Pagination (Deeds)'],
                7 => ['label' => 'Production (GIS)'],
                8 => ['label' => 'Collection (DG)'],
                9 => ['label' => 'Registry (KANGIS)'],
            ];

           
        @endphp

        <div class="max-w-full mx-auto p-2 {{ $index > 0 ? 'page-break' : '' }}">

            <!-- ===== HEADER ===== -->
            <div class="bg-blue-50 border border-gray-300 p-2 mb-0">
                <div class="flex items-center justify-between">
                    <!-- Left logo -->
                    <div style="width:56px;height:56px;">
                        <img src="http://app.klaes.ng/assets/logo/ministry1.jpg" alt="KANGIS Logo"
                             class="w-14 h-14 object-contain">
                    </div>
                    <!-- Center title -->
                    <div class="text-center flex-1 px-2">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Kano State</p>
                        <h1 class="text-base font-extrabold text-gray-800 uppercase leading-tight">
                            Kano Geographic Information Service
                        </h1>
                        <p class="text-xs text-gray-600">Kano State LAnd ADmin Enterprise System (KLAES)</p>
                        <p class="text-xs font-bold text-green-800 mt-0.5">New File — Tracking Sheet</p>
                    </div>
                    {{-- add qr code here for the file number --}}
                    <div class="flex items-center justify-center mr-8" style="width:56px;height:56px;">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode($fileIndexing->tracking_id ?? 'N/A') }}"
                             alt="QR Code" class="w-14 h-14 object-contain">
                    </div>

                    <!-- Right logo -->
                    <div style="width:56px;height:56px;">
                        <img src="http://app.klaes.ng/assets/logo/kangis.jpg" alt="KANGIS Logo"
                             class="w-14 h-14 object-contain">
                    </div>


                </div>
            </div>

            <!-- ===== FILE DETAILS BAND ===== -->
            <table class="tracking-band mb-1" style="width:100%; border:none; border-collapse:collapse; font-size:10px;">
                <tr style="vertical-align:top;">
                    <td style="width:33%; padding:1px 4px; border:none;">
                          <div><span class="font-semibold">New KANGIS File No:</span>
                            <span class="font-mono font-bold text-green-800">{{ $fileIndexing->fn_new_kangis_file_no ?? $fileIndexing->new_kangis_file_no ?? '—' }}</span>
                        </div>

                        <div><span class="font-semibold">KANGIS File No:</span>
                            {{ $fileIndexing->fn_kangis_file_no ?? $fileIndexing->kangis_file_no ?? $fileIndexing->kangis_fileno_placeholder ?? $fileIndexing->file_number ?? '—' }}
                        </div>
                      
                        <div><span class="font-semibold">MLPP File No:</span>
                            {{ $fileIndexing->fn_mls_file_no ?? $fileIndexing->mls_file_no ?? $fileIndexing->related_file_no ?? '—' }}
                        </div>
                    </td>
                    <td style="width:34%; padding:1px 4px; border:none; text-align:center;">
                        <div style="display: inline-block; text-align: left;">
                            <div><span class="font-semibold">File Title:</span>
                                {{ $fileIndexing->file_title ?? '—' }}
                            </div>
                            <div><span class="font-semibold">Indexed By:</span>
                                {{ $tracker->created_by_name ?? auth()->user()->name ?? '—' }}
                            </div>
                            <div><span class="font-semibold">Date Indexed:</span>
                                {{ $fileIndexing->created_at ? \Carbon\Carbon::parse($fileIndexing->created_at)->format('d M Y') : '—' }}
                            </div>
                        </div>
                    </td>
                    <td style="width:33%; padding:1px 4px; border:none; text-align:right;">
                        <div><span class="font-semibold">Applicant Phone:</span>
                            {{ $fileIndexing->applicant_phone ?? $meta['applicant_phone'] ?? '080-XXXXXXX' }}
                        </div>
                    </td>
                </tr>
            </table>
            <div style="display:none;"><span class="font-semibold">Workflow Step:</span>
                <span class="font-bold text-indigo-700">{{ $step }} / 7</span>
            </div>
 
            {{-- {SIGTABLE} --}}
            <!-- ===== 6-STAGE SIGN-OFF TABLE ===== -->
            <table class="sig-table mt-2">
                <thead>
                    <tr>
                        <th style="width:5%">Stage</th>
                        <th style="width:22%">Movement</th>
                        <th style="width:13%">Log Out Date/Time</th>
                        <th style="width:13%">Log In Date/Time</th>
                        <th style="width:13%">Sent by (Name & Sign)</th>
                        <th style="width:13%">Received by (Name & Sign)</th>
                        <th style="width:10%">Remarks</th>
                        <th style="width:11%">Status</th>
                    </tr>
                </thead>
                <tbody class="sig-cell-tall">
                    @foreach($stages as $stageNum => $stage)
                        @php
                            $isDone    = $tracker && $stageNum < $step;
                            $isCurrent = $tracker && $stageNum === $step;
                            $movements = $tracker ? collect(is_array($tracker->movement_log) ? $tracker->movement_log : json_decode($tracker->movement_log ?? '[]', true)) : collect([]);
                            // Stage 1 = "Origin" (no movement entry); stage 2+ maps to movement index 0, 1, 2...
                            $mv        = $stageNum > 1 ? $movements->get($stageNum - 2) : null;
                            $rowBg     = $isDone ? 'background:#f0fff4' : ($isCurrent ? 'background:#fffbeb' : '');
                        @endphp
                        <tr style="{{ $rowBg }}">
                            <td class="font-bold" style="text-align:center;">{{ $stageNum }}</td>
                            <td class="font-bold" style="font-size:13px">{{ $stage['label'] }}</td>
                            <td class="font-mono text-xs">
                                
                            </td>
                            <td class="font-mono text-xs">
                                
                            </td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>&nbsp;</td>
                            <td>
                                 
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>


            <br><br><br><br>

            <!-- Signature Lines -->
            <div class="mt-4 flex justify-between items-end" style="gap: 24px;">
                <div class="text-center" style="flex:1; border-top: 1.5px solid #1a1a1a; padding-top: 4px;">
                    <p class="font-bold" style="font-size:11px; margin:0;">Customer Service</p>
                    
                </div>
                <div class="text-center" style="flex:1; border-top: 1.5px solid #1a1a1a; padding-top: 4px;">
                    <p class="font-bold" style="font-size:11px; margin:0;">Registry</p>
                     
                </div>
                <div class="text-center" style="flex:1; border-top: 1.5px solid #1a1a1a; padding-top: 4px;">
                    <p class="font-bold" style="font-size:11px; margin:0;">DG KANGIS </p>
                   
                </div>
            </div>

            <br><br>
            <!-- ===== FOOTER ===== -->
            <div class="mt-2 flex items-center text-xs text-gray-500 border-t border-gray-300 pt-1" style="justify-content:space-between;">
                <div style="flex:1;">
                    <img src="http://app.klaes.ng/storage/upload/logo/logo.png" alt="" style="height:28px;display:inline;vertical-align:middle;">
                </div>
                <div style="flex:2; text-align:center;">
                    KLAES — Kano State LAnd ADmin Enterprise System &nbsp;·&nbsp; Printed: {{ now()->format('d M Y, g:i A') }}
                </div>
                <div style="flex:1; text-align:right;">
                    <img src="http://app.klaes.ng/assets/logo/las.jpg" alt="" style="height:28px;display:inline;vertical-align:middle;">
                </div>
            </div>
        </div><!-- /card -->

    @empty
        <div class="text-center py-16 text-gray-500">No files found for KANGIS tracking sheet.</div>
    @endforelse

</body>
</html>
