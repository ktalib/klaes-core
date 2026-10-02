@extends('layouts.app')

@push('styles')
<style>
/* Ensure SweetAlert2 popups always sit above all fixed overlays on this page
   (print-manager z-999999, batchPrintModal z-1000090, dropdown z-9999). */
.swal2-container { z-index: 9999999 !important; }
</style>
@endpush

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $ossViewOnly ? 'OSS ROFO' : 'Land RofO Management' }}</h1>
                    <p class="text-slate-500 text-sm mt-1">{{ $ossViewOnly ? 'OSS Right of Occupancy Offers — view only.' : 'Manage Right of Occupancy Offers, generation and printing.' }}</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('land-rofos.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        @if($ossViewOnly)
                            <input type="hidden" name="view" value="only">
                        @endif
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search file, applicant, or location..."
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </form>
                    <button type="button" onclick="openRecordsExportModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition shadow-lg shadow-emerald-200 whitespace-nowrap text-sm">
                        <i data-lucide="download" class="h-4 w-4"></i> Export Records
                    </button>
                    @if(!$ossViewOnly)
                    <button type="button" onclick="openBatchPrintModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 whitespace-nowrap text-sm">
                        <i data-lucide="printer" class="h-4 w-4"></i> Batch Print RofO
                    </button>
                    <button type="button" onclick="openReissuanceModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 text-white font-bold rounded-xl hover:bg-amber-700 transition shadow-lg shadow-amber-200 whitespace-nowrap text-sm">
                        <i data-lucide="refresh-ccw" class="h-4 w-4"></i> Re-issuance
                    </button>
                    @endif
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 {{ $ossViewOnly ? 'lg:grid-cols-3' : 'lg:grid-cols-4' }} gap-6 mb-10">
                <!-- Daily OSS (first card, OSS view only) -->
                @if($ossViewOnly)
                <div class="bg-white p-6 rounded-3xl border border-emerald-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="calendar-check" class="h-32 w-32 text-emerald-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 shadow-sm">
                            <i data-lucide="calendar-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Daily OSS</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['oss_daily']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Today's applications</span>
                        <span class="text-emerald-500">{{ now()->format('d M Y') }}</span>
                    </div>
                </div>
                @endif

                <!-- Total ROFO -->
                @if(!$ossViewOnly)
                <div class="bg-white p-6 rounded-3xl border border-green-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="file-check" class="h-32 w-32 text-green-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-green-50 text-green-600 rounded-2xl border border-green-100 shadow-sm">
                            <i data-lucide="file-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total RofO Captured</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['total_land'] + $stats['oss_total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Land + OSS</span>
                        <span class="text-green-500">All Records</span>
                    </div>
                </div>
                @endif
                
                <!-- Total Land RoFO -->
                @if(!$ossViewOnly)
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="layers-3" class="h-32 w-32 text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                            <i data-lucide="layers-3" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Land RoFO</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['total_land']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>All land records</span>
                        <span class="text-blue-500">Total</span>
                    </div>
                </div>
                @endif


                <!-- OSS Applications -->
                <div class="bg-white p-6 rounded-3xl border border-purple-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="layers" class="h-32 w-32 text-purple-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl border border-purple-100 shadow-sm">
                            <i data-lucide="layers" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">OSS Applications</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['oss_total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Change of Name</span>
                        <span class="text-purple-500">Print Ready</span>
                    </div>
                </div>

                <!-- Total Dev Charges -->
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-indigo-600 to-indigo-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="coins" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="coins" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-indigo-100 uppercase tracking-widest">Total Dev. Charges</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">₦{{ number_format($stats['total_dev_charge']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-indigo-100 uppercase tracking-widest">
                        <span>Revenue Stream</span>
                        <!-- <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">SQLSRV</span> -->
                    </div>
                </div>
            </div>

            @php $tabBaseParams = array_filter(['search' => request('search'), 'view' => $ossViewOnly ? 'only' : null]); @endphp
            <div class="flex items-center gap-2 mb-4 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm w-full sm:w-max">
                <a href="{{ route('land-rofos.index', $tabBaseParams + ['tab' => 'not_printed']) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'not_printed' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="file-clock" class="h-4 w-4"></i>
                    Not Printed
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'not_printed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['not_printed']) }}</span>
                </a>
                <a href="{{ route('land-rofos.index', $tabBaseParams + ['tab' => 'printed']) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'printed' ? 'bg-green-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="printer-check" class="h-4 w-4"></i>
                    Printed
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'printed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['printed']) }}</span>
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="shield" class="h-4 w-4 text-blue-600"></i>
                        RoFO Management Records
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ $tab === 'printed' ? 'Printed' : 'Not Printed' }}</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[2000px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-4 py-4 text-center whitespace-nowrap">S/N</th>
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Source</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Land Use / Purpose</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Layout Plan</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Dev. Period</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Survey Fees</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Value</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Charge</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 text-center text-green-600 whitespace-nowrap">Approved On</th>
                                <!-- <th class="px-6 py-4 text-center text-blue-600 whitespace-nowrap">RofO Status</th> -->
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Security Paper Code</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Generated</th>
                                <th class="px-6 py-4 whitespace-nowrap text-green-600">Print Date</th>
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @php $renderedBatch = null; @endphp
                            @forelse($recommendations as $rec)
                            @php
                                $isOssRec = strtoupper($rec->type ?? '') === 'OSS';
                                // Subdivision batch children are adjacent (shared created_at,
                                // ordered by batch_seq), so the header row is emitted once,
                                // when the batch id first changes.
                                $batchId    = $rec->rofo_batch_id ?: null;
                                $startBatch = $batchId && $batchId !== $renderedBatch;
                                if ($batchId) { $renderedBatch = $batchId; } else { $renderedBatch = null; }
                            @endphp

                            @if($startBatch)
                            @php
                                $batchTotal    = $batchSizes[$batchId] ?? 0;
                                $batchInfo     = $batchMemberIds[$batchId] ?? ['printable_ids' => [], 'not_generated' => 0];
                                $batchMembers  = $batchInfo['printable_ids'];
                                $notGenerated  = $batchInfo['not_generated'];
                            @endphp
                            <tr class="batch-head bg-violet-50 border-y border-violet-200" data-batch="{{ $batchId }}">
                                <td colspan="21" class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <button type="button" class="batch-toggle inline-flex items-center gap-1.5 text-violet-800 hover:text-violet-950 font-bold text-xs uppercase tracking-wider"
                                                data-batch="{{ $batchId }}" aria-expanded="false">
                                            <i data-lucide="chevron-right" class="batch-chevron h-4 w-4 transition-transform"></i>
                                            Batch
                                        </button>
                                        <span class="font-mono font-black text-slate-900">{{ $rec->batch_mother_file_no ?: $rec->old_file_number }}</span>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-800">
                                            <i data-lucide="git-fork" class="h-3 w-3"></i> {{ $rec->application_type ?: 'Plot Subdivision' }}
                                        </span>
                                        <span class="text-xs text-slate-600">{{ $batchTotal }} {{ \Illuminate\Support\Str::plural('child', $batchTotal) }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">{{ $batchId }}</span>
                                        {{-- Kept left: this table is far wider than the viewport, so a
                                             right-aligned action would sit off-screen until scrolled to. --}}
                                        @if(count($batchMembers))
                                        <button type="button"
                                                onclick='printRofoBatch(@json($batchMembers))'
                                                class="px-3 py-1.5 text-[11px] font-bold bg-violet-600 text-white rounded-lg hover:bg-violet-700 transition inline-flex items-center gap-1.5">
                                            <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                            Print Batch ({{ count($batchMembers) }})
                                        </button>
                                        @endif

                                        {{-- Only a generated RofO has anything to print. Saying so beats a
                                             Print button that yields a blank sheet. --}}
                                        @if($notGenerated)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800"
                                              title="Generate the RofO for these children before printing the batch">
                                            <i data-lucide="clock" class="h-3 w-3"></i>
                                            {{ $notGenerated }} RofO not generated
                                        </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endif

                            <tr class="hover:bg-slate-50/50 transition {{ $isOssRec ? 'bg-purple-50/30' : '' }} {{ $batchId ? 'batch-child bg-violet-50/20 hidden' : '' }}" @if($batchId) data-batch="{{ $batchId }}" @endif>
                                <td class="px-4 py-2 text-center text-slate-500 whitespace-nowrap">{{ ($recommendations->currentPage() - 1) * $recommendations->perPage() + $loop->iteration }}</td>
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">
                                    <div>{{ $rec->file_number }}</div>
                                    @if(isset($rofoSerials[$rec->id]))
                                        @php $rofoSc = $rofoSerials[$rec->id]; @endphp
                                        <div style="display:flex; align-items:center; gap:5px; margin-top:3px; letter-spacing:normal;" title="Security Serial No.">
                                            <span style="line-height:1; color:#059669; display:inline-flex; flex-direction:column; align-items:center; font-weight:900; font-family:Arial, sans-serif;">
                                                <span style="border-bottom:1.5px solid #059669; padding-bottom:1px; font-size:11px;">{{ $rofoSc['alphabet'] }}</span>
                                                <span style="padding-top:1px; font-size:11px;">{{ $rofoSc['digits_start'] }}</span>
                                            </span>
                                            <span style="font-size:18px; font-weight:900; letter-spacing:0.1em; color:#059669; font-family:'Courier New', monospace;">{{ $rofoSc['digits_end'] }}</span>
                                        </div>
                                    @endif
                                    @if($rec->land_rofo_serial_no)
                                        <div style="margin-top:3px; font-size:18px; font-weight:900; letter-spacing:0.1em; color:#dc2626; font-family:'Courier New', monospace;" title="Security Paper Code">
                                            {{ $rec->land_rofo_serial_no }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if($isOssRec)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                            <i data-lucide="layers" class="h-3 w-3"></i> OSS
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                            <i data-lucide="file" class="h-3 w-3"></i> Land
                                        </span>
                                    @endif
                                    @if($rec->is_reissuance)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 mt-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800"
                                              title="{{ $rec->reissuance_source === 'legacy' ? 'Pre-KLAES (Legacy) RofO' : 'KLAES-Generated RofO' }}">
                                            <i data-lucide="refresh-ccw" class="h-3 w-3"></i> Re-issuance
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->purpose_of_clause }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->location }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->layout_plan_no }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->term }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->development_period }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->survey_fees, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->development_value, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">{{ is_numeric($rec->development_charge) ? '₦'.number_format($rec->development_charge, 2) : ($rec->development_charge ?: '₦0.00') }}</td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($tab === 'printed')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                            <i data-lucide="printer-check" class="h-3 w-3"></i> PRINTED
                                        </span>
                                    @elseif($isOssRec)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                            PRINT READY
                                        </span>
                                    @elseif($rec->rofo_status === \App\Models\LandRecommendation::ROFO_GENERATED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                           APPROVED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            PENDING
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center text-slate-500 text-xs whitespace-nowrap">
                                    {{ $isOssRec ? '—' : ($rec->approved_at ? $rec->approved_at->format('Y-m-d h:i A') : 'N/A') }}
                                </td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->creator->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">
                                    @if($rec->land_rofo_serial_no)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                            {{ $rec->land_rofo_serial_no }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic text-xs">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-500 text-xs whitespace-nowrap">
                                    {{ $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A' }}
                                </td>
                                @php $printedAt = $printDates[$rec->id] ?? null; @endphp
                                <td class="px-4 py-2 text-xs whitespace-nowrap {{ $printedAt ? 'text-green-700 font-semibold' : 'text-slate-400 italic' }}">
                                    {{ $printedAt ? \Carbon\Carbon::parse($printedAt)->format('Y-m-d h:i A') : 'Not printed' }}
                                </td>
                                <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap">
                                    <div x-data="{
                                        open: false,
                                        menuStyle: {},
                                        toggleMenu($event) {
                                            if (!this.open) {
                                                const btn = $event.currentTarget;
                                                const rect = btn.getBoundingClientRect();
                                                this.menuStyle = {
                                                    position: 'fixed',
                                                    top: (rect.bottom + 4) + 'px',
                                                    left: (rect.right - 224) + 'px',
                                                    zIndex: 9999
                                                };
                                                const spaceBelow = window.innerHeight - rect.bottom;
                                                if (spaceBelow < 280) {
                                                    this.menuStyle.top = 'auto';
                                                    this.menuStyle.bottom = (window.innerHeight - rect.top + 4) + 'px';
                                                }
                                            }
                                            this.open = !this.open;
                                        }
                                    }" class="relative inline-block text-left">
                                        <button @click="toggleMenu($event)" @click.away="open = false" type="button" class="inline-flex items-center p-2 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition">
                                            <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                        </button>

                                        <div x-show="open"
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="opacity-0 scale-95"
                                             x-transition:enter-end="opacity-100 scale-100"
                                             x-transition:leave="transition ease-in duration-75"
                                             x-transition:leave-start="opacity-100 scale-100"
                                             x-transition:leave-end="opacity-0 scale-95"
                                             :style="menuStyle"
                                             class="min-w-[13rem] w-max rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                             style="display: none;">
                                            <div class="py-1">
                                                {{-- The OSS view is read-only for the record itself; only serial + print actions apply there. --}}
                                                @if(!$ossViewOnly)
                                                <button type="button" onclick="editRofORecord('{{ $rec->id }}', '{{ route('land-recommendations.edit', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                    <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                </button>

                                                <div class="border-t border-slate-100 my-1"></div>
                                                @endif

                                                @if($rec->land_rofo_serial_no)
                                                <button type="button" disabled class="flex w-full items-center px-4 py-2.5 text-sm text-slate-300 gap-2 font-bold cursor-not-allowed" title="Security paper code already assigned">
                                                    <i data-lucide="hash" class="h-4 w-4"></i> Enter Security Paper Code
                                                </button>
                                                <button type="button" onclick="resetSecurityPaperCode('{{ $rec->id }}', '{{ route('land-rofos.reset-security-paper', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition gap-2 font-bold">
                                                    <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset Security Paper Code
                                                </button>
                                                @else
                                                <button type="button" onclick="openAssignSecurityPaperModal('{{ $rec->id }}', '{{ $rec->file_number }}', '{{ $rec->land_rofo_serial_no }}', '{{ route('land-rofos.assign-security-paper', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-emerald-600 hover:bg-emerald-50 transition gap-2 font-bold">
                                                    <i data-lucide="hash" class="h-4 w-4"></i> Enter Security Paper Code
                                                </button>
                                                @endif

                                                <div class="border-t border-slate-100 my-1"></div>

                                                @if($rec->is_reissuance)
                                                    {{-- Re-issued RofOs print only the watermarked re-issuance
                                                         letter, so this replaces the plain Print Manager entry. --}}
                                                    <button type="button"
                                                            onclick="printReissuance('{{ $rec->id }}', @js($rec->file_number), @js($rec->reissuance_source))"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-amber-700 hover:bg-amber-50 transition gap-2 font-bold">
                                                        <i data-lucide="printer" class="h-4 w-4"></i> Print Re-issuance
                                                    </button>
                                                @else
                                                    <button type="button"
                                                            onclick="SmartPrintManager.open('{{ $rec->file_number }}', 'Land RofO', '{{ route('land-rofos.print', $rec->id) }}')"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                        <i data-lucide="printer" class="h-4 w-4"></i> Print Manager
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="21" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">
                                            @if($tab === 'printed')
                                                No {{ $ossViewOnly ? 'OSS ' : '' }}RofOs have been printed yet.
                                            @else
                                                No {{ $ossViewOnly ? 'OSS ' : '' }}RofOs awaiting print.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($recommendations->hasPages())
                <div class="px-8 py-6 border-t border-slate-100">
                    {{ $recommendations->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
    @include('admin.footer')
</div>

<!-- ═══════════════════════ Batch Print Modal ═══════════════════════ -->
<div id="batchPrintModal" class="fixed inset-0 z-[1000090] hidden bg-slate-900/60 p-4 overflow-y-auto">
    <div class="mx-auto mt-10 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 bg-gradient-to-r from-indigo-900 to-indigo-700 px-6 py-4 rounded-t-2xl">
            <div>
                <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="printer" class="h-5 w-5 text-indigo-300"></i> Batch Print RofO
                </h3>
                <p class="text-indigo-200 text-xs mt-0.5">Select generated RofOs to print — each has 3 copies × 2 pages</p>
            </div>
            <button type="button" onclick="closeBatchPrintModal()" class="text-indigo-300 hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-5">
            <!-- Loading state -->
            <div id="bpmLoading" class="flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                <p class="text-slate-500 text-sm">Loading unprinted RofOs…</p>
            </div>

            <!-- Empty state -->
            <div id="bpmEmpty" class="hidden flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                    <i data-lucide="check-circle" class="h-7 w-7 text-green-600"></i>
                </div>
                <p class="text-slate-700 font-bold">All RofOs have been printed</p>
                <p class="text-slate-400 text-sm">Nothing pending in the batch queue.</p>
            </div>

            <!-- Records list -->
            <div id="bpmList" class="hidden">
                <!-- Select-all bar -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700 cursor-pointer select-none">
                        <input type="checkbox" id="bpmSelectAll" onchange="bpmToggleAll(this.checked)"
                               class="w-4 h-4 rounded border-slate-300 text-indigo-600 cursor-pointer">
                        Select All
                    </label>
                    <span id="bpmCount" class="text-xs font-bold text-indigo-600"></span>
                </div>

                <!-- Table -->
                <div class="border border-slate-200 rounded-xl overflow-hidden max-h-72 overflow-y-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-4 py-3 w-8"></th>
                                <th class="px-4 py-3">File Number</th>
                                <th class="px-4 py-3">Applicant</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3 text-center">Serial</th>
                            </tr>
                        </thead>
                        <tbody id="bpmTableBody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>

                <!-- Print info -->
                <div class="mt-3 p-3 bg-indigo-50 rounded-xl border border-indigo-100 text-xs text-indigo-700 font-semibold flex items-start gap-2">
                    <i data-lucide="info" class="h-4 w-4 shrink-0 mt-0.5"></i>
                    <span>Each RofO prints <strong>3 copies</strong> (Original · Duplicate · Triplicate) × <strong>2 pages</strong> = <strong>6 pages per application</strong>. Once printed they leave this queue.</span>
                </div>
            </div>

            <!-- Post-print confirm state -->
            <div id="bpmConfirm" class="hidden flex flex-col items-center justify-center py-8 gap-4">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center">
                    <i data-lucide="printer" class="h-7 w-7 text-amber-600"></i>
                </div>
                <p class="text-slate-700 font-bold text-center">Did the documents print successfully?</p>
                <p class="text-slate-400 text-sm text-center">Confirming will mark the selected RofOs as printed and remove them from this queue.</p>
                <div class="flex gap-3 mt-2">
                    <button type="button" onclick="bpmConfirmLog()"
                        class="px-6 py-2.5 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition text-sm">
                        Yes, Mark as Printed
                    </button>
                    <button type="button" onclick="bpmCancelConfirm()"
                        class="px-6 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition text-sm">
                        No, Go Back
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 border-t border-slate-100 flex justify-between items-center rounded-b-2xl bg-slate-50">
            <button type="button" onclick="closeBatchPrintModal()"
                class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            <button type="button" id="bpmPrintBtn" onclick="bpmDoPrint()" disabled
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                <i data-lucide="printer" class="h-4 w-4"></i>
                <span id="bpmPrintBtnLabel">Print Selected</span>
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════ Re-issuance Modal ═══════════════════════ -->
<div id="reissuanceModal" class="fixed inset-0 z-[1000090] hidden bg-slate-900/60 p-4 overflow-y-auto">
    <div class="mx-auto mt-16 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 bg-gradient-to-r from-amber-700 to-amber-500 px-6 py-4 rounded-t-2xl">
            <div>
                <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="refresh-ccw" class="h-5 w-5 text-amber-200"></i> RofO Re-issuance
                </h3>
                <p class="text-amber-100 text-xs mt-0.5">Re-issue a Right of Occupancy offer letter</p>
            </div>
            <button type="button" onclick="closeReissuanceModal()" class="text-white/80 hover:text-white transition">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <!-- Step indicator -->
        <div class="flex items-center gap-2 px-6 pt-5 text-[11px] font-bold uppercase tracking-widest">
            <span id="reiStepBadge1" class="px-2.5 py-1 rounded-full bg-amber-100 text-amber-800">1. Origin of RofO</span>
            <i data-lucide="chevron-right" class="h-3 w-3 text-slate-300"></i>
            <span id="reiStepBadge2" class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-400">2. Select File Number</span>
        </div>

        <!-- STEP 1: where was the original RofO produced? -->
        <div id="reiStep1" class="px-6 py-6 space-y-3">
            <p class="text-sm text-slate-600">Which RofO is being re-issued?</p>

            <button type="button" onclick="reissuanceSelectSource('klaes')"
                class="w-full text-left p-4 rounded-xl border-2 border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/40 transition group">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100">
                        <i data-lucide="monitor-check" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-900 group-hover:text-emerald-700">KLAES-Generated RofO</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            The original was generated and printed from KLAES, so its issue date is on record.
                        </span>
                    </div>
                </div>
            </button>

            <button type="button" onclick="reissuanceSelectSource('legacy')"
                class="w-full text-left p-4 rounded-xl border-2 border-slate-200 hover:border-amber-500 hover:bg-amber-50/40 transition group">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-xl bg-amber-50 text-amber-600 border border-amber-100">
                        <i data-lucide="archive" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <span class="block text-sm font-bold text-slate-900 group-hover:text-amber-700">Pre-KLAES (Legacy) RofO</span>
                        <span class="block text-xs text-slate-500 mt-0.5">
                            The original was issued before KLAES — no generation record exists, so the issue date is entered by hand.
                        </span>
                    </div>
                </div>
            </button>
        </div>

        <!-- STEP 2: pick the file number being re-issued -->
        <div id="reiStep2" class="hidden px-6 py-6 space-y-4">
            <div id="reiSourceSummary" class="flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-lg"></div>

            {{-- KLAES-generated: choose from the RofO table --}}
            <div id="reiPanelKlaes" class="hidden">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">RofO File Number</label>
                <select id="reiKlaesSelect" class="w-full"></select>
                <p class="mt-1 text-[11px] text-slate-500">
                    Search the file numbers already on the RofO table. The recommendation and RofO
                    were captured in KLAES, so re-issuing only flags this record — nothing is re-entered.
                </p>
                <div id="reiKlaesDetails" class="hidden mt-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 space-y-1"></div>
            </div>

            {{-- Pre-KLAES: pick through the global file number selector --}}
            <div id="reiPanelLegacy" class="hidden">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">File Number</label>
                <div class="flex gap-2">
                    <input type="text" id="reiLegacyFileNo" readonly placeholder="No file number selected"
                        class="flex-1 border border-slate-200 rounded-xl px-4 py-2.5 bg-slate-50 font-mono text-sm outline-none">
                    <button type="button" onclick="reissuancePickLegacyFileNo()"
                        class="px-4 py-2.5 text-xs font-bold bg-slate-800 text-white rounded-xl hover:bg-slate-900 transition whitespace-nowrap">
                        Select File Number
                    </button>
                </div>
                <p class="mt-1 text-[11px] text-slate-500">
                    The original letter pre-dates KLAES, so its details are captured on the next screen.
                </p>
                <div id="reiLegacyDetails" class="hidden mt-3 p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-3 bg-slate-50 px-6 py-4 rounded-b-2xl border-t border-slate-200">
            <button type="button" id="reiBackBtn" onclick="reissuanceBack()"
                class="hidden px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-xl transition">Back</button>
            <button type="button" onclick="closeReissuanceModal()"
                class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            <button type="button" id="reiNextBtn" onclick="reissuanceNext()" disabled
                class="hidden inline-flex items-center gap-2 px-6 py-2.5 bg-amber-600 text-white font-bold rounded-xl hover:bg-amber-700 transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                <span id="reiNextBtnLabel">Next</span> <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </button>
        </div>
    </div>
</div>

{{-- Used by the Re-issuance dialog: the pre-KLAES path picks its file number here.
     The component also pulls in Select2, which the KLAES path's dropdown uses. --}}
@include('components.global-fileno-modal')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>

<script>
// ── RofO Re-issuance ────────────────────────────────────────────
// Step 1 records where the original letter came from, step 2 picks the file
// number — from the RofO table for a KLAES-generated letter, or through the
// global file number selector for a pre-KLAES one. Next opens the recommendation
// form in re-issuance mode; saving there returns to this table.
var _reiSource       = null;   // 'klaes' | 'legacy'
var _reiKlaesId      = null;   // selected recommendation id (klaes path)
var _reiKlaesLabel   = '';     // its file number, for the confirmation prompt
var _reiLegacyFileNo = '';     // selected file number (legacy path)
var _reiLegacyFileTitle = '';  // its file title, shown on the step 2 card
var _reiLegacyLocation  = '';  // its location, shown alongside the title

function escapeReiHtml(value) {
    var d = document.createElement('div');
    d.textContent = value;
    return d.innerHTML;
}

function openReissuanceModal() {
    _reiSource          = null;
    _reiKlaesId         = null;
    _reiLegacyFileNo    = '';
    _reiLegacyFileTitle = '';
    _reiLegacyLocation  = '';

    document.getElementById('reiLegacyFileNo').value = '';
    document.getElementById('reiKlaesDetails').classList.add('hidden');
    document.getElementById('reiLegacyDetails').classList.add('hidden');
    if (window.jQuery && jQuery('#reiKlaesSelect').data('select2')) {
        jQuery('#reiKlaesSelect').val(null).trigger('change');
    }

    reissuanceShowStep(1);
    document.getElementById('reissuanceModal').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}

function closeReissuanceModal() {
    document.getElementById('reissuanceModal').classList.add('hidden');
}

function reissuanceShowStep(step) {
    var onStep2 = step === 2;
    document.getElementById('reiStep1').classList.toggle('hidden', onStep2);
    document.getElementById('reiStep2').classList.toggle('hidden', !onStep2);
    document.getElementById('reiBackBtn').classList.toggle('hidden', !onStep2);
    document.getElementById('reiNextBtn').classList.toggle('hidden', !onStep2);

    document.getElementById('reiStepBadge1').className = 'px-2.5 py-1 rounded-full ' +
        (onStep2 ? 'bg-slate-100 text-slate-400' : 'bg-amber-100 text-amber-800');
    document.getElementById('reiStepBadge2').className = 'px-2.5 py-1 rounded-full ' +
        (onStep2 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-400');
}

function reissuanceSelectSource(source) {
    _reiSource = source;

    var summary = document.getElementById('reiSourceSummary');
    var isKlaes = source === 'klaes';

    summary.className = 'flex items-center gap-2 text-xs font-bold px-3 py-2 rounded-lg border ' +
        (isKlaes ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                 : 'bg-amber-50 text-amber-800 border-amber-200');
    summary.innerHTML = isKlaes
        ? '<i data-lucide="monitor-check" class="h-4 w-4"></i> KLAES-Generated RofO'
        : '<i data-lucide="archive" class="h-4 w-4"></i> Pre-KLAES (Legacy) RofO';

    document.getElementById('reiPanelKlaes').classList.toggle('hidden', !isKlaes);
    document.getElementById('reiPanelLegacy').classList.toggle('hidden', isKlaes);

    // KLAES records are already captured, so that path flags the existing RofO
    // instead of walking through the recommendation form.
    document.getElementById('reiNextBtnLabel').textContent = isKlaes ? 'Re-issue RofO' : 'Next';

    if (isKlaes) reissuanceInitKlaesSelect();

    reissuanceShowStep(2);
    reissuanceUpdateNextBtn();
    if (window.lucide) window.lucide.createIcons();
}

function reissuanceBack() {
    reissuanceShowStep(1);
}

function reissuanceUpdateNextBtn() {
    var ready = (_reiSource === 'klaes' && _reiKlaesId) ||
                (_reiSource === 'legacy' && _reiLegacyFileNo);
    document.getElementById('reiNextBtn').disabled = !ready;
}

// Select2 over the RofO table's file numbers (server-side search)
function reissuanceInitKlaesSelect() {
    if (!window.jQuery || !jQuery.fn.select2) return;
    var $sel = jQuery('#reiKlaesSelect');
    if ($sel.data('select2')) return;

    $sel.select2({
        dropdownParent: jQuery('#reissuanceModal'),
        placeholder: 'Search file number or applicant...',
        allowClear: true,
        width: '100%',
        ajax: {
            url: '{{ route('land-rofos.reissuance-search') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results || [] }; },
            cache: true
        },
        templateResult: function (item) {
            if (!item.id) return item.text;
            return jQuery(
                '<div><div style="font-family:monospace;font-weight:700">' + item.text + '</div>' +
                '<div style="font-size:11px;color:#64748b">' + (item.applicant || '—') + '</div></div>'
            );
        }
    });

    $sel.on('select2:select', function (e) {
        var d = e.params.data || {};
        _reiKlaesId    = d.id || null;
        _reiKlaesLabel = d.text || '';

        var box = document.getElementById('reiKlaesDetails');
        box.innerHTML =
            '<div><strong>Applicant:</strong> ' + (d.applicant || '—') + '</div>' +
            '<div><strong>Location:</strong> ' + (d.location || '—') + '</div>' +
            // No issue date means the RofO was never generated in KLAES — say so
            // rather than showing a date that only reflects when it was captured.
            '<div><strong>Issued on:</strong> ' +
                (d.issued_on || '<span style="color:#b45309">Not generated in KLAES</span>') + '</div>';
        box.classList.remove('hidden');
        reissuanceUpdateNextBtn();
    });

    $sel.on('select2:clear', function () {
        _reiKlaesId    = null;
        _reiKlaesLabel = '';
        document.getElementById('reiKlaesDetails').classList.add('hidden');
        reissuanceUpdateNextBtn();
    });
}

// Pre-KLAES path uses the shared global file number selector
function reissuancePickLegacyFileNo() {
    if (!window.GlobalFileNoModal) {
        alert('File number selector is not available on this page.');
        return;
    }
    window.GlobalFileNoModal.open({
        autoPopulateGenericFields: false,
        targetFields: [],
        callback: function (data) {
            var rec = (data && data.record) || {};

            _reiLegacyFileNo    = (data && data.fileNumber) ? data.fileNumber : '';
            _reiLegacyFileTitle = (data && (data.file_title || data.file_name)) || '';
            // The lookup returns location on the resolved record; grouping-only
            // fallbacks carry just district/lga, so take whichever is present.
            _reiLegacyLocation  = rec.location || rec.district || rec.lga || '';
            document.getElementById('reiLegacyFileNo').value = _reiLegacyFileNo;

            // Title and location are the details the selector can resolve for a
            // pre-KLAES file — show them so the picked file is identifiable.
            var box = document.getElementById('reiLegacyDetails');
            if (_reiLegacyFileNo) {
                box.innerHTML =
                    '<div><strong>File Title:</strong> ' +
                        (_reiLegacyFileTitle ? escapeReiHtml(_reiLegacyFileTitle) : '—') + '</div>' +
                    '<div><strong>Location:</strong> ' +
                        (_reiLegacyLocation ? escapeReiHtml(_reiLegacyLocation) : '—') + '</div>';
                box.classList.remove('hidden');
            } else {
                box.classList.add('hidden');
            }

            reissuanceUpdateNextBtn();
        }
    });
}

// Print an already-flagged re-issuance through the Print Manager. ?supersede=1
// gives the watermarked letter; the doc type carries "Re-issuance" so the manager
// badges itself, and "(Legacy)" tells it to keep the full 3-copy sequence:
//   klaes  — KLAES already issued the set, so only the Original is re-issued
//   legacy — pre-KLAES original, so all 3 copies are issued
function printReissuance(id, fileNumber, source) {
    var isLegacy = String(source || '').toLowerCase() === 'legacy';

    SmartPrintManager.open(
        fileNumber,
        isLegacy ? 'Land RofO Re-issuance (Legacy)' : 'Land RofO Re-issuance',
        '{{ url('land-rofos') }}/' + id + '/print?supersede=1'
            + (isLegacy ? '&reissue_source=legacy' : '&reissue_source=klaes')
    );
}

function reissuanceNext() {
    if (_reiSource === 'klaes') {
        reissuanceConfirmKlaes();
        return;
    }

    // Pre-KLAES: nothing is on record, so the details are captured on the
    // recommendation form in re-issuance mode.
    if (!_reiLegacyFileNo) return;
    window.location.href = '{{ route('land-recommendations.create') }}'
        + '?reissuance=legacy&file_number=' + encodeURIComponent(_reiLegacyFileNo);
}

// KLAES path: the recommendation and RofO already exist, so this only flags the
// existing record as re-issued — no new record, no form.
function reissuanceConfirmKlaes() {
    if (!_reiKlaesId) return;
    var label = _reiKlaesLabel || 'this RofO';

    if (typeof Swal === 'undefined') {
        if (confirm('Mark ' + label + ' as a re-issued RofO?')) reissuanceSubmitKlaes();
        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Re-issue RofO?',
        html: 'Mark <strong>' + label + '</strong> as a re-issued RofO.<br>' +
              '<span style="font-size:0.85rem;color:#64748b">The recommendation and RofO are already captured — ' +
              'only the re-issuance details are updated.</span>',
        showCancelButton: true,
        confirmButtonText: 'Yes, re-issue',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#64748b',
    }).then(function (result) {
        if (result.isConfirmed) reissuanceSubmitKlaes();
    });
}

function reissuanceSubmitKlaes() {
    var btn = document.getElementById('reiNextBtn');
    btn.disabled = true;

    fetch('{{ url('land-rofos') }}/' + _reiKlaesId + '/reissue', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ reissuance_source: 'klaes' })
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (!res.success) throw new Error(res.message || 'Request failed');
        closeReissuanceModal();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'RofO Re-issued',
                text: res.message,
                confirmButtonColor: '#d97706'
            }).then(function () { window.location.reload(); });
        } else {
            window.location.reload();
        }
    })
    .catch(function (err) {
        btn.disabled = false;
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Could not re-issue', text: err.message });
        } else {
            alert(err.message);
        }
    });
}

var _bpmRecords    = [];   // all unprinted records
var _bpmSelected   = [];   // ids selected for this batch
var _bpmPrintWin   = null;

function openBatchPrintModal() {
    document.getElementById('batchPrintModal').classList.remove('hidden');
    bpmShowState('loading');
    _bpmRecords = []; _bpmSelected = [];
    bpmUpdatePrintBtn();

    fetch('{{ route('land-rofos.unprinted-json') }}', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(function(res) {
            if (!res.success) throw new Error('Server error');
            _bpmRecords = res.data || [];
            if (_bpmRecords.length === 0) {
                bpmShowState('empty');
            } else {
                bpmRenderTable();
                bpmShowState('list');
            }
            if (window.lucide) window.lucide.createIcons();
        })
        .catch(function() {
            bpmShowState('empty');
        });

    // Listen for afterprint message from batch window
    window.addEventListener('message', bpmHandleMessage);
}

function closeBatchPrintModal() {
    document.getElementById('batchPrintModal').classList.add('hidden');
    window.removeEventListener('message', bpmHandleMessage);
    if (_bpmPrintWin && !_bpmPrintWin.closed) _bpmPrintWin.close();
    _bpmPrintWin = null;
}

function bpmShowState(state) {
    ['bpmLoading','bpmEmpty','bpmList','bpmConfirm'].forEach(function(id) {
        document.getElementById(id).classList.add('hidden');
    });
    var el = document.getElementById('bpm' + state.charAt(0).toUpperCase() + state.slice(1));
    if (el) el.classList.remove('hidden');
    document.getElementById('bpmPrintBtn').style.display = (state === 'list') ? '' : 'none';
}

function bpmRenderTable() {
    var tbody = document.getElementById('bpmTableBody');
    tbody.innerHTML = '';
    _bpmRecords.forEach(function(rec) {
        var tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition';
        tr.innerHTML =
            '<td class="px-4 py-2.5"><input type="checkbox" class="bpm-cb w-4 h-4 rounded border-slate-300 text-indigo-600 cursor-pointer" value="' + rec.id + '" onchange="bpmOnCheck()"></td>' +
            '<td class="px-4 py-2.5 font-mono font-bold text-slate-900 whitespace-nowrap">' + (rec.file_number||'') + '</td>' +
            '<td class="px-4 py-2.5 text-slate-700 whitespace-nowrap uppercase text-xs">' + (rec.applicant_name||'') + '</td>' +
            '<td class="px-4 py-2.5 text-slate-500 text-xs whitespace-nowrap">' + (rec.location||'') + '</td>' +
            '<td class="px-4 py-2.5 text-center"><span class="text-xs font-bold ' + (rec.land_rofo_serial_no ? 'text-emerald-700' : 'text-slate-300 italic') + '">' + (rec.land_rofo_serial_no || 'None') + '</span></td>';
        tbody.appendChild(tr);
    });
    // Select all by default
    document.getElementById('bpmSelectAll').checked = true;
    bpmToggleAll(true);
}

function bpmToggleAll(checked) {
    document.querySelectorAll('.bpm-cb').forEach(function(cb) { cb.checked = checked; });
    bpmOnCheck();
}

function bpmOnCheck() {
    _bpmSelected = [];
    document.querySelectorAll('.bpm-cb:checked').forEach(function(cb) { _bpmSelected.push(parseInt(cb.value)); });
    var total = document.querySelectorAll('.bpm-cb').length;
    document.getElementById('bpmSelectAll').checked = (_bpmSelected.length === total && total > 0);
    document.getElementById('bpmSelectAll').indeterminate = (_bpmSelected.length > 0 && _bpmSelected.length < total);
    document.getElementById('bpmCount').textContent = _bpmSelected.length + ' of ' + total + ' selected · ' + (_bpmSelected.length * 6) + ' pages';
    bpmUpdatePrintBtn();
}

function bpmUpdatePrintBtn() {
    var btn = document.getElementById('bpmPrintBtn');
    var lbl = document.getElementById('bpmPrintBtnLabel');
    btn.disabled = (_bpmSelected.length === 0);
    lbl.textContent = _bpmSelected.length > 0
        ? 'Print ' + _bpmSelected.length + ' RofO' + (_bpmSelected.length > 1 ? 's' : '')
        : 'Print Selected';
}

function bpmDoPrint() {
    if (_bpmSelected.length === 0) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('land-rofos.batch-print') }}';
    form.target = '_blank';

    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    form.appendChild(csrf);

    _bpmSelected.forEach(function(id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });

    document.body.appendChild(form);
    _bpmPrintWin = window.open('', '_blank');
    form.target = '_blank';
    form.submit();
    document.body.removeChild(form);

    // Show confirm step immediately (afterprint message also triggers this)
    setTimeout(function() { bpmShowState('confirm'); }, 1200);
}

function bpmHandleMessage(e) {
    if (e.data && e.data.type === 'rofo_batch_printed') {
        bpmShowState('confirm');
    }
}

function bpmConfirmLog() {
    if (_bpmSelected.length === 0) { closeBatchPrintModal(); return; }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch('{{ route('land-rofos.batch-print-log') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ ids: _bpmSelected })
    })
    .then(r => r.json())
    .then(function(res) {
        if (res.success) {
            Swal.fire({ icon:'success', title:'Logged', text: res.count + ' RofO(s) marked as printed.', toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
            closeBatchPrintModal();
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to log prints.' });
        }
    })
    .catch(function() {
        Swal.fire({ icon:'error', title:'Error', text:'Network error logging batch print.' });
    });
}

function bpmCancelConfirm() {
    bpmShowState('list');
}

// ── Subdivision batch rows ─────────────────────────────────────────────────
// Children of one mother file are grouped under a batch header. Printing from
// that header reuses the existing batch-print pipeline (same endpoint, same
// print log), just with the batch's own ids instead of a hand-picked selection.
function printRofoBatch(ids) {
    if (!ids || !ids.length) return;
    _bpmSelected = ids.slice();

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('land-rofos.batch-print') }}';
    form.target = '_blank';

    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    form.appendChild(csrf);

    ids.forEach(function (id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // The print window owns the actual printing; confirm here so the batch is
    // logged against the same print_logs rows the modal would have written.
    setTimeout(function () {
        Swal.fire({
            icon: 'question',
            title: 'Mark batch as printed?',
            text: ids.length + ' RofO(s) were sent to the print window.',
            showCancelButton: true,
            confirmButtonText: 'Yes, log the print',
            cancelButtonText: 'Not yet',
        }).then(function (r) { if (r.isConfirmed) bpmConfirmLog(); });
    }, 1200);
}

document.addEventListener('click', function (e) {
    var btn = e.target.closest('.batch-toggle');
    if (!btn) return;

    var batch    = btn.getAttribute('data-batch');
    var expanded = btn.getAttribute('aria-expanded') === 'true';
    btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');

    var chevron = btn.querySelector('.batch-chevron');
    if (chevron) chevron.style.transform = expanded ? '' : 'rotate(90deg)';

    document.querySelectorAll('tr.batch-child[data-batch="' + batch + '"]').forEach(function (tr) {
        tr.classList.toggle('hidden', expanded);
    });
});
</script>

<!-- RofO Data Entry Modal -->
<div x-data="rofoModal" 
     @open-rofo-modal.window="openModal($event.detail)"
     x-show="isOpen" 
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="isOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
             @click="closeModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div x-show="isOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block w-full max-w-2xl overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl sm:my-8 sm:align-middle">
            
            <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Generate RofO Offer</h3>
                    <p class="text-sm text-slate-500 mt-1">Please enter the required data for <span class="font-bold text-blue-600" x-text="fileNumber"></span></p>
                </div>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <form @submit.prevent="submitForm">
                <div class="px-8 pt-6 pb-2 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Land Use Category</label>
                        <select x-model="selectedLandUseId" disabled
                            class="w-full border border-slate-200 rounded-lg px-4 py-2 bg-slate-100 text-slate-500 cursor-not-allowed shadow-none outline-none transition text-sm font-bold">
                            <option value="">Select Category</option>
                            @foreach($landUses as $lu)
                                <option value="{{ $lu->id }}">{{ $lu->landuse }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Purpose Clause</label>
                        <select x-model="selectedPurposeId" disabled
                            class="w-full border border-slate-200 rounded-lg px-4 py-2 bg-slate-100 text-slate-500 cursor-not-allowed shadow-none outline-none transition text-sm font-bold">
                            <option value="">Select Purpose</option>
                            <template x-for="p in purposes" :key="p.id">
                                <option :value="p.id" x-text="p.name" :selected="p.id == selectedPurposeId"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="px-8 py-8 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Survey Fees (₦)</label>
                            <input type="number" step="0.01" x-model="formData.rofo_survey_fees" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed transition outline-none" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Dev. Charge (₦)</label>
                            <input type="number" step="0.01" x-model="formData.rofo_dev_charge" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed transition outline-none" placeholder="0.00">
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <div class="p-4 bg-slate-50 rounded-xl">
                            <span class="block text-sm font-bold text-slate-700 mb-3">Survey Method (Select One)</span>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-white transition bg-white">
                                    <input type="radio" x-model="surveyMethod" value="DIRECTOR" class="w-5 h-5 text-blue-600 border-slate-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-slate-700">Require <strong>Director Survey</strong> to carry out survey</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-white transition bg-white">
                                    <input type="radio" x-model="surveyMethod" value="LICENSED" class="w-5 h-5 text-blue-600 border-slate-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-slate-700">Require <strong>Licensed Surveyor</strong> to carry out survey</span>
                                </label>
                            </div>
                        </div>


                        <div class="grid grid-cols-2 gap-6">
                               <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Time Generated</label>
                            <input type="time" x-model="formData.rofo_time_generated" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Date Generated</label>
                            <input type="date" x-model="formData.rofo_date_generated" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                     
                    </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-slate-50 border-t border-slate-200 flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" @click="closeModal()" class="px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmitting" class="px-8 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-lg shadow-blue-200 transition flex items-center gap-2 disabled:opacity-50">
                        <template x-if="!isSubmitting">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                        </template>
                        <template x-if="isSubmitting">
                            <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </template>
                        Generate RofO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

 
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('rofoModal', () => ({
            isOpen: false,
            isSubmitting: false,
            fileNumber: '',
            landUse: '',
            purposeClause: '',
            recommendationId: null,
            selectedLandUseId: '',
            selectedPurposeId: '',
            purposes: [],
            isLoadingPurposes: false,
            landUsesList: @json($landUses),
            surveyMethod: '', // DIRECTOR or LICENSED
            formData: {
                rofo_land_use_category: '',
                rofo_survey_fees: '',
                rofo_dev_charge: '',
                // rofo_director_survey and rofo_licensed_surveyor will be set based on surveyMethod
                rofo_date_generated: new Date().toISOString().split('T')[0],
                rofo_time_generated: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
            }, 

            openModal(data) {
                this.recommendationId = data.id;
                this.fileNumber = data.fileNumber;
                this.landUse = data.landUse;
                this.purposeClause = data.purposeClause;
                this.selectedLandUseId = data.landUseId || '';
                this.selectedPurposeId = data.purposeId || '';
                this.formData.rofo_dev_charge = data.developmentCharge ? parseFloat(data.developmentCharge) : '';
                this.formData.rofo_survey_fees = data.surveyFees ? parseFloat(data.surveyFees) : '';
                
                // Set rofo_land_use_category from the passed landUse string
                this.formData.rofo_land_use_category = data.landUse;

                // Reset survey method
                this.surveyMethod = '';

                this.isOpen = true;

                if (this.selectedLandUseId) {
                    this.fetchPurposes(this.selectedPurposeId);
                }

                this.$nextTick(() => lucide.createIcons());
            },

            async fetchPurposes(preselectedId = null) {
                if (!this.selectedLandUseId) {
                    this.purposes = [];
                    this.selectedPurposeId = '';
                    return;
                }

                this.isLoadingPurposes = true;
                try {
                    const response = await fetch(`{{ url('api/reference/purposes') }}?landuseid=${this.selectedLandUseId}`);
                    const result = await response.json();
                    if (result.success) {
                        this.purposes = result.data;
                        if (preselectedId) {
                            this.selectedPurposeId = preselectedId;
                        }
                    }
                } catch (error) {
                    console.error('Error fetching purposes:', error);
                } finally {
                    this.isLoadingPurposes = false;
                }
            },

            closeModal() {
                this.isOpen = false;
                this.resetForm();
            },

            resetForm() {
                this.surveyMethod = '';
                this.formData = {
                    rofo_land_use_category: '',
                    rofo_survey_fees: '',
                    rofo_dev_charge: '',
                    rofo_director_survey: 'NO',
                    rofo_licensed_surveyor: 'NO',
                    rofo_date_generated: new Date().toISOString().split('T')[0],
                    rofo_time_generated: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
                };
            },

            async submitForm() {
                if (!this.selectedLandUseId || !this.selectedPurposeId || !this.surveyMethod) {
                    Swal.fire('Error', 'Please fill all required fields and select a survey method.', 'error');
                    return;
                }

                this.isSubmitting = true;
                
                // Map surveyMethod to backend fields
                const payload = {
                    ...this.formData,
                    land_use_id: this.selectedLandUseId,
                    purpose_id: this.selectedPurposeId,
                    rofo_director_survey: this.surveyMethod === 'DIRECTOR' ? 'YES' : 'NO',
                    rofo_licensed_surveyor: this.surveyMethod === 'LICENSED' ? 'YES' : 'NO'
                };

                try {
                    const response = await fetch(`{{ url('land-rofos') }}/${this.recommendationId}/generate`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.closeModal();
                        Swal.fire('Generated!', 'RofO has been generated successfully.', 'success')
                        .then(() => window.location.reload());
                    } else {
                        throw new Error(data.message || 'Submission failed');
                    }
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                } finally {
                    this.isSubmitting = false;
                }
            }
        }));
    });

    function generateRofo(id, fileNumber, developmentCharge, landUse, purposeClause, landUseId, purposeId, surveyFees) {
        window.dispatchEvent(new CustomEvent('open-rofo-modal', {
            detail: { id, fileNumber, developmentCharge, landUse, purposeClause, landUseId, purposeId, surveyFees }
        }));
    }

    function quickGenerateRofo(id, fileNumber) {
        Swal.fire({
            title: 'Generate RofO?',
            html: `Generate RofO for file <strong class="text-blue-600">${fileNumber}</strong>?<br><small class="text-slate-500">Survey method and dates are pre-filled from the recommendation form.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Generate',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ url('land-rofos') }}/${id}/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(r => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                .catch(err => Swal.showValidationMessage(`Request failed: ${err}`));
            }
        }).then(result => {
            if (result.isConfirmed && result.value?.success) {
                Swal.fire('Generated!', 'RofO has been generated successfully.', 'success')
                .then(() => window.location.reload());
            } else if (result.isConfirmed) {
                Swal.fire('Error', result.value?.message || 'Generation failed.', 'error');
            }
        });
    }

    // Edit RofO Record with Reason
    function editRofORecord(id, editUrl) {
        Swal.fire({
            title: 'Edit RofO Record',
            text: 'Please provide a reason for editing this record:',
            input: 'textarea',
            inputPlaceholder: 'Enter reason for edit here...',
            inputAttributes: {
                'aria-label': 'Reason for edit'
            },
            showCancelButton: true,
            confirmButtonText: 'Proceed to Edit',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            inputValidator: (value) => {
                if (!value) {
                    return 'You must provide a reason for editing!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to edit page with the reason as a query parameter
                // The controller's update method will pick it up or we can pass it to the form
                const url = new URL(editUrl);
                url.searchParams.set('edit_reason', result.value);
                window.location.href = url.toString();
            }
        });
    }

    // Legacy local modal — kept temporarily; buttons now call openAssignSecurityPaperModal (global component).
    function _showSpcModal_unused(id, fileNumber, currentSerial) {
        var codes = _spcCodes;

        // Dropdown is appended to <body> so it is never clipped by the Swal overflow container.
        var dropdown = document.createElement('div');
        dropdown.id  = 'spc-dropdown';
        dropdown.style.cssText = [
            'display:none',
            'position:fixed',
            'background:#fff',
            'border:1.5px solid #cbd5e1',
            'border-radius:10px',
            'max-height:220px',
            'overflow-y:auto',
            'z-index:10000000',
            'box-shadow:0 12px 32px rgba(0,0,0,0.15)',
            'font-family:inherit',
        ].join(';');
        document.body.appendChild(dropdown);

        var html  = '<p style="font-size:13px;color:#64748b;margin-bottom:10px;text-align:left;">';
            html += 'Security paper code for file <strong style="color:#2563eb;">' + fileNumber + '</strong></p>';
            html += '<input id="spc-search" type="text" autocomplete="off" placeholder="Type to search a code..." ';
            html += 'style="width:100%;padding:10px 14px;border:1.5px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;box-sizing:border-box;">';
            html += '<input id="spc-value" type="hidden">';
            html += '<p id="spc-label" style="margin-top:10px;font-size:12px;color:#10b981;font-weight:700;min-height:18px;text-align:left;"></p>';

        Swal.fire({
            title: 'Enter Security Paper Code',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Assign Code',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#10b981',
            didOpen: function() {
                var searchEl = document.getElementById('spc-search');
                var valueEl  = document.getElementById('spc-value');
                var labelEl  = document.getElementById('spc-label');
                var MAX_VISIBLE = 50;

                function positionDropdown() {
                    var rect = searchEl.getBoundingClientRect();
                    dropdown.style.left  = rect.left + 'px';
                    dropdown.style.width = rect.width + 'px';
                    dropdown.style.top   = (rect.bottom + 4) + 'px';
                }

                function renderList(term) {
                    var lower = (term || '').trim().toLowerCase();
                    if (lower === '') {
                        dropdown.style.display = 'none';
                        return;
                    }

                    var matches = codes.filter(function(c) {
                        return c && c.toLowerCase().indexOf(lower) !== -1;
                    });

                    dropdown.innerHTML = '';
                    if (matches.length === 0) {
                        dropdown.innerHTML = '<div style="padding:10px 14px;color:#94a3b8;font-size:13px;">No matching code found</div>';
                    } else {
                        matches.slice(0, MAX_VISIBLE).forEach(function(code) {
                            var item = document.createElement('div');
                            item.textContent = code;
                            item.style.cssText = 'padding:9px 14px;cursor:pointer;font-size:14px;color:#1e293b;border-bottom:1px solid #f1f5f9;';
                            item.onmouseenter = function() { item.style.background = '#f0fdf4'; };
                            item.onmouseleave = function() { item.style.background = ''; };
                            item.onmousedown  = function(e) {
                                e.preventDefault();
                                searchEl.value = code;
                                valueEl.value  = code;
                                labelEl.innerHTML = '<span style="color:#10b981;">&#10003; Selected:</span> ' + code;
                                dropdown.style.display = 'none';
                            };
                            dropdown.appendChild(item);
                        });
                        if (matches.length > MAX_VISIBLE) {
                            var more = document.createElement('div');
                            more.textContent = (matches.length - MAX_VISIBLE) + ' more — type more characters to narrow';
                            more.style.cssText = 'padding:8px 14px;color:#94a3b8;font-size:12px;font-style:italic;border-top:1px solid #f1f5f9;';
                            dropdown.appendChild(more);
                        }
                    }
                    positionDropdown();
                    dropdown.style.display = 'block';
                }

                searchEl.oninput = function() {
                    valueEl.value = '';
                    labelEl.textContent = '';
                    renderList(searchEl.value);
                };
                searchEl.onfocus = function() { renderList(searchEl.value); };
                searchEl.onblur  = function() {
                    setTimeout(function() { dropdown.style.display = 'none'; }, 200);
                };

                searchEl.focus();
            },
            willClose: function() {
                // Always clean up the body-level dropdown element
                if (dropdown.parentNode) dropdown.parentNode.removeChild(dropdown);
            },
            preConfirm: function() {
                var paperCode = (document.getElementById('spc-value').value || '').trim();
                if (!paperCode) {
                    Swal.showValidationMessage('Please select a security paper code from the list');
                    return false;
                }
                return paperCode;
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Assigning...',
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false
                });

                fetch(`{{ url('land-rofos') }}/${id}/assign-security-paper`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ paper_code: result.value })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', 'Security paper code assigned successfully.', 'success')
                        .then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Assignment failed', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                });
            }
        });
    }

    function resetSecurityPaperCode(id, url) {
        Swal.fire({
            title: 'Reset Security Paper Code?',
            text: "This will remove the currently assigned security paper code and make it available again. You can assign a new code afterwards.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, reset it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Resetting...',
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false
                });

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', 'Security paper code reset successfully.', 'success')
                        .then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Reset failed', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                });
            }
        });
    }

</script>

{{-- Export (preview + date range + CSV + PDF) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
@include('exports.records_export_modal', ['exportConfig' => [
    'title'         => $ossViewOnly ? 'Export OSS RofO' : 'Export Land RofO',
    'subtitle'      => 'Consolidated report generation & export filter',
    'endpoint'      => route('land-rofos.export'),
    'params'        => array_filter(['view' => $ossViewOnly ? 'only' : null]),
    'filename'      => $ossViewOnly ? 'OSS_RofO' : 'Land_RofO',
    'reportTitle'   => $ossViewOnly ? 'OSS RofO Register' : 'Land RofO Register',
    'search'        => request('search'),
    'statusOptions' => $ossViewOnly
        ? ['' => 'All Records']
        : ['' => 'All Statuses', 'generated' => 'RofO Generated', 'pending' => 'Awaiting Generation', 'oss' => 'OSS Only'],
]])

@endsection
