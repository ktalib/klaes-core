@extends('layouts.app')

@push('styles')
<style>
/* Ensure SweetAlert2 popups always sit above all fixed overlays on this page
   (print-manager z-999999, batchPrintModal z-1000090, dropdown z-9999). */
.swal2-container { z-index: 9999999 !important; }
</style>
@endpush

@section('content')
@php
    // Resetting a print is a Super Admin action: it takes a letter that the system
    // says is on paper and declares it unprinted, which is a correction to the
    // record and not part of anyone's daily run. Read once here rather than per
    // row — assignedRoleNames() parses the assign_role list on every call.
    $canResetPrint = auth()->check() && auth()->user()->isSuperAdmin();
@endphp
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
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 text-white font-bold rounded-xl hover:bg-red-700 transition shadow-lg shadow-red-200 whitespace-nowrap text-sm">
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
                {{-- Batches list whole, one row each. On the tabs above a batch is a
                     collapsed row whose children are scattered across the pages behind
                     it, so expanding it there shows only the few that share this page. --}}
                @if(($rofoBatchCount ?? 0) > 0)
                <a href="{{ route('land-rofos.index', $tabBaseParams + ['tab' => 'batches']) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'batches' ? 'bg-violet-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="layers" class="h-4 w-4"></i>
                    Batches
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'batches' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($rofoBatchCount) }}</span>
                </a>
                @endif
                {{-- Re-issued RofOs. A re-issuance keeps the file number of the letter
                     it replaces, so the tabs above cannot separate it from the first
                     issue — only the Source badge on the row says which it is. Not
                     split by print state: it lists them whether or not they have been
                     run off, and they stay on Printed / Not Printed too. --}}
                @if(($stats['reissuance'] ?? 0) > 0)
                <a href="{{ route('land-rofos.index', $tabBaseParams + ['tab' => 'reissuance']) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'reissuance' ? 'bg-red-600 text-white shadow-sm' : 'text-red-600 hover:bg-red-50' }}">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    Re-issuance
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'reissuance' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-600' }}">{{ number_format($stats['reissuance']) }}</span>
                </a>
                @endif
            </div>

            @if($tab === 'batches')
            {{-- ── Batches ───────────────────────────────────────────────────────
                 One row per subdivision batch, with its real size. Expanding pulls
                 every child from the server instead of revealing the ones the main
                 list's paging happened to leave on this page. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="layers" class="h-4 w-4 text-violet-600"></i>
                        RofO Batches
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ number_format($rofoBatches->total()) }} batch(es)</span>
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[980px]">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-4 py-4 text-center w-10"></th>
                                <th class="px-4 py-4 text-center whitespace-nowrap">S/N</th>
                                <th class="px-6 py-4 whitespace-nowrap">Mother File No</th>
                                <th class="px-6 py-4 whitespace-nowrap">Batch Ref</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">RofOs</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Generated</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Printed</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Created</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($rofoBatches as $i => $b)
                            @php
                                // $b->total is the issued RofO subset; the badge is
                                // the complete subdivision, including remaining files.
                                $total     = (int) (($rofoBatchChildTotals ?? collect())[$b->rofo_batch_id] ?? $b->total);
                                $generated = (int) $b->generated_count;
                                $printed   = (int) $b->printed_count;
                                $creator   = $rofoBatchCreators[$b->created_by] ?? null;
                            @endphp
                            <tr class="rofo-batch-row hover:bg-violet-50/40 transition cursor-pointer" data-batch="{{ $b->rofo_batch_id }}" aria-expanded="false">
                                <td class="px-4 py-4 text-center">
                                    <i data-lucide="chevron-right" class="batch-chevron h-4 w-4 text-violet-600 transition-transform"></i>
                                </td>
                                <td class="px-4 py-4 text-center text-slate-400 font-bold">{{ $rofoBatches->firstItem() + $i }}</td>
                                <td class="px-6 py-4">
                                    <span class="font-mono font-black text-slate-900">{{ $b->mother_file_no ?: $b->old_file_number }}</span>
                                    <span class="block text-[10px] text-slate-400">{{ $b->application_type }}</span>
                                </td>
                                <td class="px-6 py-4 font-mono text-[11px] text-slate-500">{{ $b->rofo_batch_id }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-violet-600 text-white">{{ $total }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($generated >= $total)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All {{ $total }}</span>
                                    @elseif($generated > 0)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ $generated }} of {{ $total }}</span>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($printed >= $total)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All {{ $total }}</span>
                                    @elseif($printed > 0)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ $printed }} of {{ $total }}</span>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600 whitespace-nowrap">
                                    @if($creator)
                                        {{-- Opens the shared profile card (js/user-profile-card.js). --}}
                                        <span class="upc-trigger" data-user-card data-user-id="{{ $creator->id }}"
                                            title="{{ __('View profile') }}">{{ trim($creator->first_name . ' ' . $creator->last_name) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap text-xs">{{ $b->created_at ? \Carbon\Carbon::parse($b->created_at)->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    @if($generated > 0)
                                        {{-- Only generated RofOs can be shown here; the counts say how
                                             many either button would actually put on paper.

                                             Proof first, then the run — the order the work happens in, and
                                             the same order the row menus use. --}}

                                        {{-- The proofing stage for a whole batch, beside the run that spends
                                             the paper rather than inside it. A batch is where a mistake is
                                             most expensive — one wrong field repeated across every letter in
                                             it — so reading the set through once before any of it goes onto
                                             security stock is worth a button of its own. --}}
                                        @if($printed >= $generated)
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 text-slate-300 border border-slate-200 text-[11px] font-bold rounded-lg cursor-not-allowed"
                                                  title="Every RofO in this batch has been printed — the white copy is a pre-print proof.">
                                                <i data-lucide="file-search" class="h-3.5 w-3.5"></i> White copy
                                            </span>
                                        @else
                                        <button type="button"
                                            onclick="openBatchWhiteCopy(@js($b->rofo_batch_id))"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white text-slate-700 border border-slate-300 text-[11px] font-bold rounded-lg hover:bg-slate-100 transition"
                                            title="Black &amp; white proofs of every RofO in this batch. Nothing is marked printed and no serial is spent.">
                                            <i data-lucide="file-search" class="h-3.5 w-3.5"></i> White copy
                                        </button>
                                        @endif

                                        {{-- One way in to the official run, the same as every other row on
                                             this page. The three passes this used to list in a dropdown are
                                             the Print Manager's own, so the menu was a second copy of a
                                             choice that lives inside it — and one that could not show how far
                                             the batch had already got.

                                             Left enabled whatever the proof's state: which letters in a batch
                                             still owe one is a per-letter fact this summary row cannot carry,
                                             and the manager it opens asks the proofread question itself. --}}
                                        <button type="button"
                                            {{-- @js, not @json: @json emits its own double quotes, which close this
                                                 double-quoted attribute at the first one and leave the browser with
                                                 `openBatchPrintManager(` — a parse error, and a dead button. @js
                                                 escapes them to &quot; so the handler survives the attribute. --}}
                                            onclick="openBatchPrintManager(@js($b->rofo_batch_id))"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 text-white text-[11px] font-bold rounded-lg hover:bg-violet-700 transition">
                                            <i data-lucide="printer" class="h-3.5 w-3.5"></i> Print batch ({{ $generated }})
                                        </button>
                                    @else
                                        <span class="text-[10px] text-slate-400 font-semibold">No RofO generated yet</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="rofo-batch-children hidden" data-batch-children="{{ $b->rofo_batch_id }}">
                                <td colspan="10" class="p-0 bg-violet-50/30"></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center text-slate-400">
                                    <i data-lucide="layers" class="h-8 w-8 mx-auto mb-3 text-slate-300"></i>
                                    No subdivision batches yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($rofoBatches->hasPages())
                <div class="px-6 py-4 border-t border-slate-200">{{ $rofoBatches->links() }}</div>
                @endif
            </div>
            @else
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="shield" class="h-4 w-4 text-blue-600"></i>
                        {{ $tab === 'reissuance' ? 'Re-issued RofOs' : 'RoFO Management Records' }}
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ $tab === 'reissuance' ? 'Re-issuance' : ($tab === 'printed' ? 'Printed' : 'Not Printed') }}</span>
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
                                @if($tab === 'reissuance')
                                <th class="px-6 py-4 whitespace-nowrap text-violet-700">Re-issued By</th>
                                <th class="px-6 py-4 whitespace-nowrap text-violet-700">Re-issued On</th>
                                @endif
                                <!-- <th class="px-6 py-4 text-center text-blue-600 whitespace-nowrap">RofO Status</th> -->
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                {{-- When the record was captured. Distinct from Date Generated
                                     further along, which is when the RofO was issued off it. --}}
                                <th class="px-6 py-4 whitespace-nowrap">Date Created</th>
                                <th class="px-6 py-4 whitespace-nowrap">Security Paper Code</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Generated</th>
                                <th class="px-6 py-4 whitespace-nowrap text-green-600">Print Date</th>
                                @if(!$ossViewOnly)
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            {{-- Batches are not shown here: they live on the Batches tab, whole,
                                 where one row expands to the entire batch rather than to whichever
                                 slice of it this page happened to hold. Batched records are kept
                                 out of this list entirely — except under a search, which must still
                                 find any file number. --}}
                            @forelse($recommendations as $rec)
                            @php $isOssRec = strtoupper($rec->type ?? '') === 'OSS'; @endphp

                            <tr class="hover:bg-slate-50/50 transition {{ $isOssRec ? 'bg-purple-50/30' : '' }}">
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
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap">
                                    <div>{{ $rec->applicant_name }}</div>
                                    @if(trim((string) $rec->applicant_phone) !== '')
                                        <button type="button" data-applicant-phone-action
                                                data-phone="{{ $rec->applicant_phone }}"
                                                data-applicant-name="{{ $rec->applicant_name }}"
                                                data-file-number="{{ $rec->file_number }}"
                                                class="mt-1 inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 transition hover:border-emerald-600 hover:bg-emerald-600 hover:text-white">
                                            <i data-lucide="phone" class="h-3 w-3"></i><span>{{ $rec->applicant_phone }}</span>
                                        </button>
                                    @endif
                                </td>
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
                                    {{-- On the Printed tab every row is printed by definition. The
                                         Re-issuance tab is not split by print state, so there the
                                         per-row print date decides — $printDates is built from
                                         printedPredicateSql() over the ids on the page, not from
                                         the tab, so it is accurate on any tab. --}}
                                    @if($tab === 'printed' || ($tab === 'reissuance' && isset($printDates[$rec->id])))
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
                                @if($tab === 'reissuance')
                                @php
                                    // Legacy creates a new record; KLAES marks the existing one.
                                    // This keeps the displayed audit user/date accurate for both.
                                    $reissueUser = $rec->reissuance_source === 'legacy'
                                        ? ($rec->creator ?: $rec->updater)
                                        : ($rec->updater ?: $rec->creator);
                                    $reissueAt = $rec->reissuance_source === 'legacy'
                                        ? $rec->created_at
                                        : ($rec->updated_at ?: $rec->created_at);
                                @endphp
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $reissueUser->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-slate-600 text-xs whitespace-nowrap">{{ $reissueAt ? $reissueAt->format('Y-m-d h:i A') : 'N/A' }}</td>
                                @endif
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">
                                    @if($rec->creator)
                                        <span class="upc-trigger" data-user-card data-user-id="{{ $rec->creator->id }}"
                                            title="{{ __('View profile') }}">{{ $rec->creator->name }}</span>
                                    @else
                                        System
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->created_at ? $rec->created_at->format('d-m-Y') : '—' }}</td>
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
                                @if(!$ossViewOnly)
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
                                                                                                @canDo('Letter of Grant (RofO)', 'edit')
<button type="button" onclick="editRofORecord('{{ $rec->id }}', '{{ route('land-recommendations.edit', [$rec->id, 'return_to' => 'rofo', 'return_tab' => $tab]) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                    <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                </button>
                                                @endcanDo

                                                <div class="border-t border-slate-100 my-1"></div>
                                                @endif

                                                {{-- "Enter Security Paper Code" is hidden on the Not Printed tab:
                                                     nothing has been run off yet there, so there is no paper in
                                                     hand whose code could be entered. Reset stays wherever a
                                                     serial already exists — that is a correction, not an entry. --}}
                                                @php $canEnterSecurityPaper = $tab !== 'not_printed'; @endphp

                                                @if($rec->land_rofo_serial_no)
                                                    @if($canEnterSecurityPaper)
                                                    <button type="button" disabled class="flex w-full items-center px-4 py-2.5 text-sm text-slate-300 gap-2 font-bold cursor-not-allowed" title="Security paper code already assigned">
                                                        <i data-lucide="hash" class="h-4 w-4"></i> Enter Security Paper Code
                                                    </button>
                                                    @endif
                                                <button type="button" onclick="resetSecurityPaperCode('{{ $rec->id }}', '{{ route('land-rofos.reset-security-paper', $rec->id) }}', @js($rec->file_number), @js($rec->land_rofo_serial_no), '{{ route('land-rofos.assign-security-paper', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition gap-2 font-bold">
                                                    <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset Security Paper Code
                                                </button>
                                                <div class="border-t border-slate-100 my-1"></div>
                                                @elseif($canEnterSecurityPaper)
                                                <button type="button" onclick="openAssignSecurityPaperModal('{{ $rec->id }}', '{{ $rec->file_number }}', '{{ $rec->land_rofo_serial_no }}', '{{ route('land-rofos.assign-security-paper', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-emerald-600 hover:bg-emerald-50 transition gap-2 font-bold">
                                                    <i data-lucide="hash" class="h-4 w-4"></i> Enter Security Paper Code
                                                </button>
                                                <div class="border-t border-slate-100 my-1"></div>
                                                @endif
                                                {{-- Reset print — Super Admin only, and only where there is
                                                     something to reset. Scoped the same way the passes are, so a
                                                     spoilt run can be reopened without throwing away the half
                                                     that came out correctly: reprinting an Original costs a sheet
                                                     of security paper, so "office copies only" exists precisely
                                                     to avoid spending one.

                                                     The print history is not touched by any of them — see
                                                     resetPrint(). --}}
                                                @if($canResetPrint && ($rec->rofo_print_count > 0 || isset($printDates[$rec->id])))
                                                    {{-- One entry, not three. The scope is picked in the card
                                                         it opens: three near-identical lines in a row menu read
                                                         as three separate actions, and the one that costs a
                                                         sheet of security paper looks exactly like the two that
                                                         do not. The card can show what the letter's state
                                                         actually is and what each choice would do to it. --}}
                                                    <button type="button"
                                                            onclick="openResetPrintModal({{ $rec->id }}, @js($rec->file_number), @js($rec->rofo_print_stage))"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-rose-700 hover:bg-rose-50 transition gap-2 font-bold">
                                                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset print…
                                                        <span class="ml-auto text-[9px] font-black uppercase tracking-widest text-rose-400">Super Admin</span>
                                                    </button>

                                                    <div class="border-t border-slate-100 my-1"></div>
                                                @endif

                                                {{-- One way in. The three passes — all copies, the Originals
                                                     alone, the Duplicate and Triplicate alone — used to sit here
                                                     as separate menu items beside a "Print Manager" that walked
                                                     the copies a step at a time and asked for the date of issue
                                                     in a dialog of its own. That was four entries and two print
                                                     paths for one decision, and the menu could not show what had
                                                     already been printed.

                                                     The Print Manager now holds the whole of it: the date of
                                                     issue, a tick against each copy already run off, the three
                                                     passes, and the CTC. --}}
                                                @php
                                                    // A re-issued letter prints with the RE-ISSUANCE watermark
                                                    // and the superseding notice. A KLAES re-issuance replaces a
                                                    // set that was already issued, so it is the Original alone —
                                                    // the manager opens with no pass choice, because the other
                                                    // two would be paper with no letter behind them. A pre-KLAES
                                                    // (legacy) one was never issued from here, so it prints the
                                                    // full set and keeps the three passes.
                                                    $reissue = $rec->is_reissuance
                                                        ? (strtolower(trim((string) $rec->reissuance_source)) === 'legacy' ? 'legacy' : 'klaes')
                                                        : null;

                                                    $pmType = $reissue
                                                        ? ($reissue === 'legacy' ? 'Land RofO Re-issuance (Legacy)' : 'Land RofO Re-issuance')
                                                        : 'Land RofO';

                                                    $pmUrl = $reissue
                                                        ? route('land-rofos.print', $rec->id) . '?supersede=1&reissue_source=' . $reissue
                                                        : route('land-rofos.print', $rec->id);

                                                    // The proof copy carries the same switches as the letter
                                                    // it is a proof of — a re-issuance is proofread with its
                                                    // superseding notice on, or the officer is reading a
                                                    // different document from the one that will print.
                                                    $wcUrl = $reissue
                                                        ? route('land-rofos.white-copy', $rec->id) . '?supersede=1&reissue_source=' . $reissue
                                                        : route('land-rofos.white-copy', $rec->id);

                                                    $wcIssueDate = optional($rec->date_issued)->format('Y-m-d') ?? '';

                                                    $pmOptions = [
                                                        'recordId'     => (int) $rec->id,
                                                        'issueDate'    => $wcIssueDate,
                                                        // Keep the manager bound to the Land RofO date writer.
                                                        // The shared component serves other document modules too.
                                                        'issueDateUrl' => route('land-rofos.issue-date'),
                                                        // Tells the manager the date of issue is not its to
                                                        // edit, and gives it somewhere to send an operator
                                                        // whose letter has none yet. The RofO is one of the
                                                        // documents that actually prints a DATE OF ISSUE, so
                                                        // the White Copy card owns that field.
                                                        'whiteCopyUrl'      => $wcUrl,
                                                        'whiteCopyOwnsDate' => true,
                                                        'whiteCopyDownload' => true,
                                                    ];

                                                    if ($reissue) {
                                                        $pmOptions['reissuance'] = $reissue;
                                                        $pmOptions['passes']     = $reissue === 'legacy';
                                                    }
                                                @endphp

                                                {{-- The proofing stage, and an action of its own rather than a
                                                     fourth tile inside the Print Manager. The Print Manager is
                                                     where official copies are committed to paper; the White Copy
                                                     is what happens before anyone is ready to commit anything,
                                                     and it can be run as many times as the record needs
                                                     correcting. Putting it inside the manager would have made
                                                     "print a draft" and "spend a sheet of security paper" two
                                                     buttons in one dialog. --}}
                                                @php
                                                    // Once the letter is on paper the proofing stage is over:
                                                    // the copy in the applicant's hand is the document now, and
                                                    // a proof of it would only be read against something already
                                                    // issued. Reset print reopens this along with everything
                                                    // else, which is the right way back to it.
                                                    //
                                                    // Read from the print log, not from rofo_print_count or the
                                                    // tab: a letter printed through a batch has a full print
                                                    // history and a count of zero, so it sits under Not Printed
                                                    // with the paper already issued. See $whiteCopyLocked.
                                                    $wcPrinted = isset($whiteCopyLocked[$rec->id]);

                                                    // The proof has been run off. The two entries hand off to
                                                    // each other from here: the White Copy closes, the Print
                                                    // Manager opens.
                                                    $wcDone = isset($whiteCopyDone[$rec->id]);
                                                @endphp

                                                @if($wcPrinted || $wcDone)
                                                <span class="flex w-full items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 font-bold"
                                                      title="{{ $wcPrinted
                                                            ? 'This RofO has already been printed — the white copy is a pre-print proof.'
                                                            : 'White copy already run off — print the letter next.' }}">
                                                    <i data-lucide="file-search" class="h-4 w-4 text-slate-200"></i>
                                                    Generate White Copy
                                                </span>
                                                @else
                                                <button type="button"
                                                        onclick="openWhiteCopyModal(@js((int) $rec->id), @js($rec->file_number), @js($wcIssueDate), @js($wcUrl), { ownsDate: true, download: true })"
                                                        class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition gap-2 font-bold">
                                                    <i data-lucide="file-search" class="h-4 w-4"></i>
                                                    Generate White Copy
                                                </button>
                                                @endif

                                                {{-- Opens once the proof has been run off, or once the letter
                                                     has been printed before (a reprint has already had its
                                                     proofing done). Nothing else on this row says whether the
                                                     letter was read, and that is the whole reason the proof
                                                     exists. --}}
                                                @if($wcDone || $wcPrinted)
                                                <button type="button"
                                                        onclick="WhiteCopy.openPrintManager(@js($rec->file_number), @js($pmType), @js($pmUrl), @js($pmOptions))"
                                                        class="flex w-full items-center px-4 py-2.5 text-sm {{ $reissue ? 'text-amber-700 hover:bg-amber-50' : 'text-blue-700 hover:bg-blue-50' }} transition gap-2 font-bold">
                                                    <i data-lucide="printer" class="h-4 w-4"></i>
                                                    {{ $reissue ? 'Print Manager — Re-issuance' : 'Print Manager' }}
                                                </button>
                                                @else
                                                <span class="flex w-full items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 font-bold"
                                                      title="Print and read the white copy first.">
                                                    <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i>
                                                    {{ $reissue ? 'Print Manager — Re-issuance' : 'Print Manager' }}
                                                </span>
                                                @endif

                                                {{-- Master Delete un-issues the RofO and leaves the
                                                     recommendation standing, approved and ready to be
                                                     generated again. To erase the record itself, use the
                                                     Master Delete on the Land Recommendation screen.
                                                     Supper Admin only — the server enforces it too. --}}
                                                @if(auth()->user()?->assign_role === 'Supper Admin')
                                                <div class="border-t border-slate-100 my-1"></div>
                                                <button type="button"
                                                        onclick="masterDeleteLandRofo({{ $rec->id }}, @js($rec->file_number))"
                                                        class="flex w-full items-center px-4 py-2.5 text-sm text-red-700 hover:bg-red-50 transition gap-2 font-bold">
                                                    <i data-lucide="shield-alert" class="h-4 w-4"></i> Master Delete
                                                </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $ossViewOnly ? 21 : 22 }}" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">
                                            @if($tab === 'printed')
                                                No {{ $ossViewOnly ? 'OSS ' : '' }}RofOs have been printed yet.
                                            @elseif($tab === 'reissuance')
                                                No {{ $ossViewOnly ? 'OSS ' : '' }}RofOs have been re-issued yet.
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
            @endif
        </div>
    </div>
    @include('admin.footer')
</div>

<!-- ═══════════════════════ Batch Print Modal ═══════════════════════ -->
{{-- ── Reset print ────────────────────────────────────────────────────────
     Super Admin only, and the row menu only renders the entry that opens it — but
     the endpoint checks the role again, because a modal in the page is not a
     permission. --}}
<div id="resetPrintModal" class="fixed inset-0 z-[1000095] hidden bg-slate-900/70 p-4 overflow-y-auto">
    <div class="min-h-full flex items-center justify-center">
        <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-rose-700 to-rose-600 flex items-center gap-3">
                <i data-lucide="rotate-ccw" class="h-5 w-5 text-rose-100"></i>
                <div class="min-w-0">
                    <h3 class="text-white font-bold text-lg leading-tight">Reset Print</h3>
                    <p class="text-rose-100 text-[11px] font-semibold uppercase tracking-widest truncate">
                        <span id="resetPrintFile" class="font-mono"></span>
                        <span class="text-rose-200/80"> · currently <span id="resetPrintStage" class="font-bold"></span></span>
                    </p>
                </div>
                <button type="button" onclick="closeResetPrintModal()"
                        class="ml-auto p-2 text-rose-100 hover:text-white hover:bg-white/10 rounded-full transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-4">
                <p class="text-[12.5px] text-slate-500 font-medium">
                    Pick what to reopen. Nothing is deleted — the print history keeps every run and who made it;
                    a reset only means the letter counts as unprinted from here on.
                </p>

                <div class="grid sm:grid-cols-3 gap-3" id="resetPrintScopes">
                    @foreach([
                        ['all',      'rotate-ccw', 'All copies',             'Back to not printed',      'The full set prints again — a fresh sheet of security paper for the Original.'],
                        ['original', 'award',      'Original only',          'Run 1 outstanding again',  'Reprinting the Original spends a sheet of security paper. The office copies keep their record.'],
                        ['office',   'copy',       'Duplicate & Triplicate', 'Run 2 outstanding again',  'They reprint on plain paper — no security paper is spent. The Original stays printed.'],
                    ] as [$scopeKey, $scopeIcon, $scopeTitle, $scopeHint, $scopeWarning])
                        <button type="button"
                                data-scope="{{ $scopeKey }}"
                                data-warning="{{ $scopeWarning }}"
                                onclick="pickResetScope('{{ $scopeKey }}')"
                                class="reset-scope-tile flex flex-col gap-2 p-3.5 rounded-xl border-2 border-slate-200 bg-white text-left transition hover:border-rose-300 hover:bg-rose-50/40">
                            <span class="w-9 h-9 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center">
                                <i data-lucide="{{ $scopeIcon }}" class="w-4 h-4"></i>
                            </span>
                            <span class="block">
                                <span class="block text-[13px] font-bold text-slate-800">{{ $scopeTitle }}</span>
                                <span class="block text-[10.5px] font-medium text-slate-500 mt-0.5">{{ $scopeHint }}</span>
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- What the chosen scope actually costs, in the same place every time. --}}
                <div id="resetPrintWarning" class="hidden flex gap-2.5 p-3.5 rounded-xl bg-amber-50 border border-amber-200">
                    <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <p class="text-[12px] text-amber-900 font-semibold leading-relaxed" id="resetPrintWarningText"></p>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center gap-3">
                <button type="button" onclick="closeResetPrintModal()"
                        class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-slate-900 transition">Cancel</button>
                <button type="button" id="resetPrintConfirm" disabled onclick="confirmResetPrint()"
                        class="ml-auto px-5 py-2.5 rounded-xl bg-rose-600 text-white text-sm font-bold hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-2">
                    <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                    <span id="resetPrintConfirmLabel">Reset print</span>
                </button>
            </div>
        </div>
    </div>
</div>

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
                    <span>Each RofO prints <strong>3 copies</strong> (Original · Duplicate · Triplicate) × <strong>2 pages</strong> = <strong>6 pages per application</strong>. Once printed they leave this queue.
                        <br>Print next asks whether to run them <strong>all at once</strong> or <strong>Originals first</strong> — and if a split run was left half done, it offers to pick it up there.</span>
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
<script src="{{ asset('js/master-delete.js') }}"></script>

<script>
    /**
     * Master Delete for a land RofO — the ISSUANCE only. The recommendation stays
     * approved and can be generated again; erasing the record itself is the Master
     * Delete on the Land Recommendation screen.
     */
    function masterDeleteLandRofo(id, fileNumber) {
        MasterDelete.confirm({
            url: '/land-rofos/' + id + '/master-destroy',
            reference: fileNumber,
            title: 'Master Delete RofO',
            lead: 'This permanently un-issues the RofO for <b>' + fileNumber + '</b>. It cannot be undone.',
            targets: [
                'RofO status, generation date and date of issue',
                "The RofO's own survey fees, development charge and surveyor flags",
                'Its PRA transaction',
                'Its security paper code (released, or retired if already printed)',
                'Its print history, white copies included'
            ],
            keeps: 'The recommendation itself is kept, approved, and can be generated again.'
        });
    }
</script>

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

// Every print on this page now starts in the Print Manager — a single row, a batch,
// or a hand-picked selection alike. It carries the record ids and the dates already
// on record, and asks for a missing date inside itself rather than behind a dialog.
//
// The manager never prints a batch itself: it names the pass and hands back to
// runRofoPrintPipeline() below, which is still the one path to paper.
//
// Left unreachable by that change, and kept only so the pipeline has a fallback if
// it is ever called without a named pass: printRofoBatch(), askResumeOrRestart()
// and askHowToPrintBatch(). Nothing calls them now.

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
}

function closeBatchPrintModal() {
    document.getElementById('batchPrintModal').classList.add('hidden');
}

function bpmShowState(state) {
    ['bpmLoading','bpmEmpty','bpmList'].forEach(function(id) {
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
            '<td class="px-4 py-2.5 font-mono font-bold text-slate-900 whitespace-nowrap">' + (rec.file_number||'')
                // Held back from a split print: its Original is already out, only
                // the office copies are owed. It stays in this queue because that
                // paper has not been used yet — but it must not read as untouched.
                + (rec.print_stage === 'originals'
                    ? '<span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-[9px] font-bold bg-sky-100 text-sky-700 align-middle" '
                      + 'title="Run 1 printed the Original. Only the Duplicate and Triplicate are outstanding.">Office copies due</span>'
                    : '') + '</td>' +
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

    var ids = _bpmSelected.slice();

    // Hands over to the one batch-print pipeline instead of posting its own form.
    // That is what puts the All at once / Originals first choice on this button
    // too — and with it the resume, since a split run is recorded per half.
    //
    // This modal used to print first and ask "did it print?" afterwards. The batch
    // path settled that question the other way round — record, then print —
    // because a confirmation cancelled after the paper was already out left the
    // two out of step. One pipeline, one answer.
    closeBatchPrintModal();

    // The same manager the rest of the page uses. It used to hand straight to the
    // pipeline, which asked how to print in a dialog of its own — a second surface
    // for a choice that now has one home, and one that could not show how far a
    // half-finished run had already got.
    rofoOpenBatchManagerFor(ids, ids.length + ' selected RofO' + (ids.length === 1 ? '' : 's'));
}

// ── Reset print ─────────────────────────────────────────────────────────────
// Super Admin only: the row menu does not render the entry for anyone else and the
// endpoint refuses anyone else, so neither half stands alone.
//
// One entry opens the card and the scope is chosen there. Three lines in a row
// menu read as three separate actions and give no way to say what each one costs —
// reprinting an Original spends a sheet of security paper, reprinting the office
// copies spends none, and that difference is the whole reason the scopes exist.
var _resetPrintId    = null;
var _resetPrintFile  = '';
var _resetPrintScope = '';

function openResetPrintModal(id, fileNumber, stage) {
    _resetPrintId    = id;
    _resetPrintFile  = fileNumber || '';
    _resetPrintScope = '';

    document.getElementById('resetPrintFile').textContent = _resetPrintFile;
    document.getElementById('resetPrintStage').textContent = {
        complete:  'fully printed',
        originals: 'Originals printed, office copies owed',
        none:      'not printed'
    }[stage] || (stage || 'unknown');

    // Cleared every time: a scope left selected from the last file is a reset
    // nobody chose.
    document.querySelectorAll('#resetPrintScopes .reset-scope-tile').forEach(function (tile) {
        tile.classList.remove('border-rose-500', 'bg-rose-50');
        tile.classList.add('border-slate-200', 'bg-white');
    });
    document.getElementById('resetPrintWarning').classList.add('hidden');
    document.getElementById('resetPrintConfirm').disabled = true;
    document.getElementById('resetPrintConfirmLabel').textContent = 'Reset print';

    document.getElementById('resetPrintModal').classList.remove('hidden');
    if (window.lucide) window.lucide.createIcons();
}

function closeResetPrintModal() {
    document.getElementById('resetPrintModal').classList.add('hidden');
    _resetPrintId    = null;
    _resetPrintScope = '';
}

function pickResetScope(scope) {
    _resetPrintScope = scope;

    var chosen = null;
    document.querySelectorAll('#resetPrintScopes .reset-scope-tile').forEach(function (tile) {
        var on = tile.dataset.scope === scope;
        tile.classList.toggle('border-rose-500', on);
        tile.classList.toggle('bg-rose-50', on);
        tile.classList.toggle('border-slate-200', !on);
        tile.classList.toggle('bg-white', !on);
        if (on) chosen = tile;
    });

    if (chosen) {
        document.getElementById('resetPrintWarningText').textContent = chosen.dataset.warning || '';
        document.getElementById('resetPrintWarning').classList.remove('hidden');
        document.getElementById('resetPrintConfirmLabel').textContent =
            'Reset ' + (chosen.querySelector('span span') ? chosen.querySelector('span span').textContent : scope);
    }

    document.getElementById('resetPrintConfirm').disabled = false;
}

function confirmResetPrint() {
    if (!_resetPrintId || !_resetPrintScope) return;

    var btn = document.getElementById('resetPrintConfirm');
    btn.disabled = true;

    fetch('{{ url('land-rofos') }}/' + _resetPrintId + '/reset-print', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': rofoCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ scope: _resetPrintScope })
    })
    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
    .then(function (res) {
        if (!res.ok || !res.data.success) {
            throw new Error((res.data && res.data.message) || 'The print could not be reset.');
        }

        var file = _resetPrintFile;
        closeResetPrintModal();

        Swal.fire({
            icon: 'success',
            title: 'Print reset',
            html: '<b>' + rofoEscHtml(file) + '</b> is now <b>' + rofoEscHtml(res.data.stage_to) + '</b>.'
                + '<div style="margin-top:8px;font-size:12px;color:#475569">The print history is unchanged — '
                + 'every run that was made is still on record.</div>',
            timer: 2600,
            showConfirmButton: false
        }).then(function () { window.location.reload(); });
    })
    .catch(function (err) {
        btn.disabled = false;
        Swal.fire({ icon: 'error', title: 'Not reset', text: err.message || 'Network error.' });
    });
}

// ── Subdivision batch rows ─────────────────────────────────────────────────
// Children of one mother file are grouped under a batch header. Printing from
// that header reuses the existing batch-print pipeline (same endpoint, same
// print log), just with the batch's own ids instead of a hand-picked selection.
// Confirm first, then log, then open the print page. Asking after the window was
// already sent to the printer left the two out of step — a cancelled dialog meant
// paper had been used but nothing was recorded.
// ── Batches tab ────────────────────────────────────────────────────────────
// One row per batch. Expanding pulls EVERY child from the server rather than
// revealing the rows that happen to share this page — which is the whole reason
// the tab exists, since on the main list a 100-RofO batch expands to whatever
// slice of it the pagination left behind.
var ROFO_BATCH_URL = @json(url('land-rofos/batch'));

function rofoEscHtml(v) {
    return String(v == null ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function rofoPill(ok, okLabel, pendingLabel) {
    return '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold '
        + (ok ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700') + '">'
        + (ok ? okLabel : pendingLabel) + '</span>';
}

// The Printed column of the expanded batch table. A split print leaves a real
// third state between printed and not: the Original is on the applicant's paper
// while the office copies are still owed, and that is the state the resume prompt
// acts on — so it has to be visible here too, not folded into "Printed".
// Opens the Print Manager for one child of a batch. The row's own details are kept
// from the fetch that drew the table, so the manager comes up with its date and its
// re-issuance state without going back to the server for a row already on screen.
//
// This replaced a hand-built dropdown of the same three passes. One definition of
// that choice now, inside the manager, so the wording and the passes cannot drift
// apart between the places it opens from.
var _rofoChildById = {};

function rofoOpenChildPrintManager(id) {
    var c = _rofoChildById[String(id)];
    if (!c) return;

    var reissue = c.reissuance || '';
    var type = reissue
        ? (reissue === 'legacy' ? 'Land RofO Re-issuance (Legacy)' : 'Land RofO Re-issuance')
        : 'Land RofO';

    var url = c.print_url;
    if (reissue) {
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'supersede=1&reissue_source=' + encodeURIComponent(reissue);
    }

    var whiteCopyUrl = c.white_copy_url || '';
    if (reissue && whiteCopyUrl) {
        whiteCopyUrl += (whiteCopyUrl.indexOf('?') === -1 ? '?' : '&')
            + 'supersede=1&reissue_source=' + encodeURIComponent(reissue);
    }

    var options = {
        recordId: c.id,
        issueDate: c.issue_date || '',
        whiteCopyUrl: whiteCopyUrl,
        whiteCopyOwnsDate: true,
        whiteCopyDownload: true
    };
    if (reissue) {
        // A KLAES re-issuance is the Original alone — the set was already issued —
        // so it opens with no pass choice. A pre-KLAES one prints the full set.
        options.reissuance = reissue;
        options.passes = (reissue === 'legacy');
    }

    // Through the proofread gate, exactly as the main list is: a child of a batch
    // printed on its own is an individual print and spends the same security paper.
    WhiteCopy.openPrintManager(c.file_number, type, url, options);
}

// The proofing stage for one child of a batch.
function rofoOpenChildWhiteCopy(id) {
    var c = _rofoChildById[String(id)];
    if (!c || !c.white_copy_url) return;

    var url = c.white_copy_url;
    if (c.reissuance) {
        url += (url.indexOf('?') === -1 ? '?' : '&')
            + 'supersede=1&reissue_source=' + encodeURIComponent(c.reissuance);
    }

    openWhiteCopyModal(c.id, c.file_number, c.issue_date || '', url, { ownsDate: true, download: true });
}

function rofoPrintStagePill(c) {
    var stage = c.print_stage || (c.print_count > 0 ? 'complete' : 'none');

    if (stage === 'originals') {
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 text-sky-700" '
            + 'title="The Original is printed. The Duplicate and Triplicate have not been.">Original only</span>';
    }

    return rofoPill(stage === 'complete', 'Printed', 'Not printed');
}

function loadRofoBatchChildren(batchId) {
    return fetch(ROFO_BATCH_URL + '/' + encodeURIComponent(batchId) + '/children', {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message || 'Could not load the batch.');
            return data;
        });
}

document.addEventListener('click', function (e) {
    var row = e.target.closest('tr.rofo-batch-row');
    if (!row) return;

    var batch    = row.getAttribute('data-batch');
    var expanded = row.getAttribute('aria-expanded') === 'true';
    var holder   = document.querySelector('tr[data-batch-children="' + batch + '"]');
    if (!holder) return;

    row.setAttribute('aria-expanded', expanded ? 'false' : 'true');
    var chevron = row.querySelector('.batch-chevron');
    if (chevron) chevron.style.transform = expanded ? '' : 'rotate(90deg)';

    if (expanded) { holder.classList.add('hidden'); return; }
    holder.classList.remove('hidden');
    if (holder.dataset.loaded === '1') return;

    var cell = holder.querySelector('td');
    cell.innerHTML = '<div class="px-8 py-6 text-xs text-slate-500">Loading all RofOs in this batch…</div>';

    loadRofoBatchChildren(batch)
        .then(function (data) {
            holder.dataset.loaded = '1';

            var rows = data.children.map(function (c) {
                // Kept so the row's Print button can open the manager without a
                // second trip for a record already drawn on screen.
                _rofoChildById[String(c.id)] = c;

                var generated = String(c.rofo_status || '').toLowerCase() === 'generated';
                return '<tr class="border-b border-violet-100/70 hover:bg-white/70">'
                    + '<td class="px-4 py-2.5 text-center text-[11px] font-bold text-slate-400">' + c.seq + '</td>'
                    + '<td class="px-4 py-2.5 font-mono text-xs font-bold text-slate-900">' + rofoEscHtml(c.file_number) + '</td>'
                    + '<td class="px-4 py-2.5 text-xs text-slate-700"><div>' + rofoEscHtml(c.applicant_name) + '</div>'
                    + (window.applicantPhoneActionButtonHtml ? window.applicantPhoneActionButtonHtml(c.applicant_phone, c.applicant_name, c.file_number) : '') + '</td>'
                    + '<td class="px-4 py-2.5 text-xs text-slate-600">' + rofoEscHtml(c.plot_number) + '</td>'
                    + '<td class="px-4 py-2.5 text-xs text-slate-600">' + rofoEscHtml(c.location) + '</td>'
                    + '<td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">' + rofoEscHtml(c.serial_no || '—') + '</td>'
                    + '<td class="px-4 py-2.5 text-center">' + rofoPill(generated, 'Generated', 'Pending') + '</td>'
                    + '<td class="px-4 py-2.5 text-center">' + rofoPrintStagePill(c) + '</td>'
                    + '<td class="px-4 py-2.5 text-right whitespace-nowrap">'
                    +   (generated
                            // One child on its own is an individual print, so it opens the
                            // Print Manager like any other single letter — the same three
                            // passes, the same ticks read from its own print log. It used
                            // to raise a dropdown of its own, which was a third copy of
                            // that choice on one page.
                            //
                            // The id rides in the handler and everything else is looked up
                            // from the row: a file number like RES-1993-2644(T) carries
                            // characters that end an HTML attribute early, and a handler
                            // cut in half never runs at all.
                            // Printed letters lose the proof, exactly as they do on the
                            // main list: the copy in the applicant's hand is the document
                            // now, so there is nothing left to proofread before printing.
                            ? (function () {
                                var printed = (c.print_count > 0 || c.print_stage === 'complete' || c.print_stage === 'originals');
                                var proofed = !!c.white_copy_done;

                                // Proof first, then the print — and each closes as the
                                // other opens, exactly as on the main list.
                                var wc = (printed || proofed)
                                    ? '<span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-slate-300 cursor-not-allowed" title="'
                                      + (printed ? 'Already printed — the white copy is a pre-print proof' : 'White copy already run off') + '">'
                                      + '<i data-lucide="file-search" class="h-3 w-3"></i> White copy</span>'
                                    : '<button type="button" onclick="rofoOpenChildWhiteCopy(' + c.id + ')"'
                                      + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-slate-600 hover:bg-slate-100 rounded" title="Black & white proof for vetting — does not count as a print">'
                                      + '<i data-lucide="file-search" class="h-3 w-3"></i> White copy</button>';

                                var pr = (proofed || printed)
                                    ? '<button type="button" onclick="rofoOpenChildPrintManager(' + c.id + ')"'
                                      + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-violet-700 hover:bg-violet-50 rounded">'
                                      + '<i data-lucide="printer" class="h-3 w-3"></i> Print</button>'
                                    : '<span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-slate-300 cursor-not-allowed" title="Print and read the white copy first">'
                                      + '<i data-lucide="printer" class="h-3 w-3"></i> Print</span>';

                                return wc + pr;
                              })()
                            : '<span class="text-[10px] text-slate-400">—</span>')
                    + '</td>'
                    + '</tr>';
            }).join('');

            cell.innerHTML =
                '<div class="px-6 py-4">'
                + '<p class="text-[10px] font-black uppercase tracking-widest text-violet-700 mb-2">All '
                +   data.count + ' RofOs in this batch</p>'
                + '<div class="overflow-x-auto rounded-lg border border-violet-200 bg-white">'
                + '<table class="w-full text-left border-collapse min-w-[900px]">'
                + '<thead><tr class="bg-violet-50 text-[10px] font-black text-violet-800 uppercase tracking-widest">'
                +   '<th class="px-4 py-2.5 text-center w-10">#</th>'
                +   '<th class="px-4 py-2.5">File Number</th>'
                +   '<th class="px-4 py-2.5">Applicant</th>'
                +   '<th class="px-4 py-2.5">Plot No</th>'
                +   '<th class="px-4 py-2.5">Location</th>'
                +   '<th class="px-4 py-2.5">Serial No</th>'
                +   '<th class="px-4 py-2.5 text-center">RofO</th>'
                +   '<th class="px-4 py-2.5 text-center">Printed</th>'
                +   '<th class="px-4 py-2.5 text-right">Actions</th>'
                + '</tr></thead><tbody>' + rows + '</tbody></table></div></div>';
        })
        .catch(function (err) {
            cell.innerHTML = '<div class="px-8 py-6 text-xs text-rose-700">'
                + rofoEscHtml(err.message || 'Network error loading the batch.') + '</div>';
        });
});

// Print a whole batch from the Batches tab. The ids are not on the page here, and
// only generated RofOs can print — batchPrint() filters on that, so a pending
// child would otherwise contribute a blank sheet.
//
// A batch printed the way the menu item names, with no question in between: the
// operator has already said which pass this is.
// Opens the global Print Manager for a whole batch. Everything the manager needs
// to be honest about the batch is read first — which letters are generated, how far
// the batch has already got, and which of them still have no date — so the passes
// come up already ticked and the date panel only appears when there is something to
// fill in. The printing itself stays on the batch pipeline below; the manager only
// names the pass.
function openBatchPrintManager(batchId) {
    loadRofoBatchChildren(batchId)
        .then(function (data) {
            var ids = data.children
                .filter(function (c) { return String(c.rofo_status).toLowerCase() === 'generated'; })
                .map(function (c) { return c.id; });

            if (!ids.length) {
                Swal.fire({ icon: 'info', title: 'Nothing to print', text: 'No RofO in this batch has been generated yet.' });
                return;
            }

            // The Batches tab: a real batch, so the two-run split is on offer.
            rofoOpenBatchManagerFor(ids, batchId, true);
        })
        .catch(function (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Network error.' });
        });
}

// Reads how the run stands and what dates are missing, then hands both to the
// manager. Used by the Batches tab and by a hand-picked selection alike — the two
// differ only in where the ids came from.
function rofoOpenBatchManagerFor(ids, label, splitPasses) {
    if (!ids || !ids.length) return;

    var csrf = rofoCsrf();
    var post = function (url) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ ids: ids })
        }).then(function (r) { return r.json(); });
    };

    // Neither lookup is a gate: if one fails the manager still opens, just without
    // a tick or without knowing which dates are missing.
    Promise.all([
        post('{{ route('land-rofos.batch-print-status') }}').catch(function () { return null; }),
        post('{{ route('land-rofos.issue-dates') }}').catch(function () { return null; })
    ]).then(function (res) {
        var status  = (res[0] && res[0].success) ? res[0] : null;
        var rows    = (res[1] && res[1].data) ? res[1].data : [];
        var missing = rows.filter(function (r) { return !r.date_issued; }).length;

        // The dates themselves, not just how many are missing. A batch is normally
        // issued on one day, so there is usually a single date to show — and the
        // operator is about to spend security paper on it, so it belongs in front
        // of them rather than behind a count.
        var dates = rows.map(function (r) { return r.date_issued; })
            .filter(Boolean)
            .filter(function (d, i, a) { return a.indexOf(d) === i; })
            .sort();

        openRofoBatchManager(ids, label, status, missing, splitPasses, dates);
    });
}

// Every batch print on this page funnels through here, so this is where the
// proofread question belongs — a batch is the run that puts the most paper through
// the printer at once, and the most security stock. Answering "no" prints white
// copies of the whole batch instead, which is the thing that was actually needed.
function openRofoBatchManager(ids, label, status, missing, splitPasses, dates) {
    WhiteCopy.confirmProofread({
        subject: ids.length + ' RofO' + (ids.length === 1 ? '' : 's') + ' in ' + label,
        onYes: function () { openRofoBatchManagerConfirmed(ids, label, status, missing, splitPasses, dates); },
        // No window is opened here: submitBatchWhiteCopy posts a form with
        // target=_blank, which a pop-up blocker leaves alone — unlike a
        // window.open() this far from the original click.
        onWhiteCopy: function () { submitBatchWhiteCopy(ids, rofoCsrf(), null); }
    });
}

function openRofoBatchManagerConfirmed(ids, label, status, missing, splitPasses, dates) {
    window.SmartPrintManager.open(label, 'Land RofO', null, {
        // The date of issue belongs to the White Copy for a batch exactly as it does
        // for a row: the manager shows how the batch stands and sends the operator
        // back to the proof if any letter is still undated.
        whiteCopyOwnsDate: true,
        // Originals-first is a two-run operation over a whole batch, so it is
        // offered where batches are — the Batches tab. A hand-picked selection or a
        // single row gets "All Copies" only.
        splitPasses: !!splitPasses,
        batch: {
            ids: ids,
            count: ids.length,
            missingDates: missing,
            // The distinct dates these letters already carry, earliest first.
            dates: dates || [],
            status: status,
            // The batch pipeline stays where it is; the manager only names the pass.
            onPass: function (copies, extras) {
                runRofoPrintPipeline(ids, copies, extras);
            },
            // And the way back to the proof, for a batch that still owes a date.
            onWhiteCopy: function () {
                rofoBatchWhiteCopyFor(ids, label);
            }
        }
    });
}

function rofoCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// Entry point for every batch print. It asks the server where these ids stand
// first, because a batch printed in two runs can have been abandoned between them
// — the Originals already on security paper, the office copies still owed. Without
// that answer the only thing the dialog can offer is starting the whole batch
// again, which is a second Original on security stock for every file in it.
// -- DATE OF ISSUE -----------------------------------------------------------
// The letter prints land_recommendations.date_issued as its DATE OF ISSUE, a column
// that holds nothing else. There is no fallback behind it: a record that has never
// been issued prints an empty date, which is why this dialog stands in front of
// every print.
//
//   one record  - always shown. A date already on the record is shown LOCKED, with
//                 an Edit that asks for confirmation first: it is normally keyed in
//                 on the recommendation form, and a letter already issued carries it,
//                 so changing it at the printer is a deliberate act rather than a
//                 field left open to a stray keystroke.
//   a selection - shown only when some of them have no date. One answer fills those
//                 only; the rest keep the date they already carry, and nothing in a
//                 bulk run can overwrite a date already on record.
//
// The date travels WITH the print (a hidden field on the print form, saved up front
// on a manager URL) instead of being saved and then printed: the print tab has to be
// claimed inside the click that opened it or the pop-up blocker takes it, and there
// is no room for an await in between.
function rofoAskIssueDate(ids, onReady) {
    fetch('{{ route('land-rofos.issue-dates') }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': rofoCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ ids: ids })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        var rows    = (data && data.data) || [];
        var missing = rows.filter(function (r) { return !r.date_issued; });
        var single  = ids.length === 1;

        // A bulk run where every letter already carries its date has nothing to ask.
        if (!single && !missing.length) {
            onReady(null);
            return;
        }

        var existing = single && rows.length ? (rows[0].date_issued || '') : '';

        rofoIssueDateDialog(ids, rows, missing, existing, single, onReady);
    })
    // The date is the point of the dialog, not a nicety, so a failed lookup still
    // asks - it just cannot prefill.
    .catch(function () {
        rofoIssueDateDialog(ids, [], [], '', ids.length === 1, onReady);
    });
}

// onReady is handed either null (print with what the record holds) or
// { issue_date, issue_date_apply } - 'all' only when the operator confirmed an edit.
function rofoIssueDateDialog(ids, rows, missing, existing, single, onReady) {
    var subject = single
        ? (rows.length ? rofoEscHtml(rows[0].file_number) : 'this RofO')
        : '<b>' + missing.length + '</b> of <b>' + ids.length + '</b> selected RofO(s)';

    // Locked = a date is already on record. The field shows it, greyed, until Edit
    // is confirmed.
    var locked = single && !!existing;

    var body = single
        ? (locked
            ? 'This letter is dated <b>' + rofoEscHtml(existing) + '</b> and prints with that date.'
            : 'No date of issue is on record for <b>' + subject + '</b>, so the letter has nothing to print there.')
        : subject + ' have no date of issue on record. The date entered here is written to those only &mdash; the rest keep the date they already carry.';

    if (typeof Swal === 'undefined') {
        if (locked) { onReady(null); return; }
        var typed = window.prompt('Date of issue (YYYY-MM-DD) - prints on the letter as DATE OF ISSUE', '');
        if (typed) onReady({ issue_date: typed, issue_date_apply: 'missing' });
        return;
    }

    // The input is built here rather than handed to Swal's own `input:` option: a
    // locked field with an Edit beside it and a confirmation in between is more than
    // that option can express, and a disabled Swal input is not returned at all.
    var html = '<div style="text-align:left;font-size:13px;line-height:1.5">' + body
        + '<div style="margin-top:10px;color:#475569;font-size:12px">'
        +   'This prints on the letter as <b>DATE OF ISSUE</b> and is saved to the '
        +   'record, so a reprint comes out carrying the same date.'
        + '</div>'
        + '<div style="display:flex;gap:8px;align-items:center;margin-top:14px">'
        +   '<input type="date" id="rofoIssueDateInput" required value="' + rofoEscHtml(existing) + '"'
        +     ' max="' + rofoToday() + '"' + (locked ? ' disabled' : '')
        +     ' style="flex:1;padding:8px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:14px'
        +     (locked ? ';background:#f1f5f9;color:#64748b' : '') + '">'
        +   (locked
                ? '<button type="button" id="rofoIssueDateEdit"'
                  + ' style="padding:8px 14px;border:0;border-radius:6px;background:#e2e8f0;color:#334155;'
                  + 'font-weight:700;font-size:13px;cursor:pointer">Edit</button>'
                : '')
        + '</div>'
        + '<div id="rofoIssueDateConfirm" style="display:none;margin-top:10px;padding:10px 12px;'
        +   'background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;font-size:12.5px;color:#78350f">'
        +   '<div>Are you sure you want to edit the date of issue? This letter is already dated '
        +     '<b>' + rofoEscHtml(existing) + '</b>, and any copy already issued carries that date.</div>'
        +   '<div style="margin-top:8px;display:flex;gap:8px">'
        +     '<button type="button" id="rofoIssueDateYes" style="padding:6px 14px;border:0;border-radius:6px;'
        +       'background:#b45309;color:#fff;font-weight:700;font-size:12.5px;cursor:pointer">Yes, edit it</button>'
        +     '<button type="button" id="rofoIssueDateNo" style="padding:6px 14px;border:0;border-radius:6px;'
        +       'background:#e2e8f0;color:#334155;font-weight:700;font-size:12.5px;cursor:pointer">No</button>'
        +   '</div>'
        + '</div>'
        + '<div id="rofoIssueDateError" style="display:none;margin-top:8px;color:#b91c1c;font-size:12.5px"></div>'
        + '</div>';

    var unlocked = false;

    Swal.fire({
        icon: 'question',
        title: 'Date of Issue',
        html: html,
        showCancelButton: true,
        confirmButtonText: 'Continue to print',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#7c3aed',
        didOpen: function () {
            var input   = document.getElementById('rofoIssueDateInput');
            var edit    = document.getElementById('rofoIssueDateEdit');
            var confirm = document.getElementById('rofoIssueDateConfirm');

            if (!edit) { if (input) input.focus(); return; }

            edit.addEventListener('click', function () { confirm.style.display = 'block'; });

            document.getElementById('rofoIssueDateNo').addEventListener('click', function () {
                confirm.style.display = 'none';
            });

            document.getElementById('rofoIssueDateYes').addEventListener('click', function () {
                unlocked = true;
                confirm.style.display = 'none';
                edit.style.display = 'none';
                input.disabled = false;
                input.style.background = '';
                input.style.color = '';
                input.focus();
            });
        },
        preConfirm: function () {
            var input = document.getElementById('rofoIssueDateInput');
            var error = document.getElementById('rofoIssueDateError');
            var value = input ? input.value : '';

            // Still locked: print with what the record holds, send nothing.
            if (locked && !unlocked) return { keep: true };

            if (!value) {
                error.textContent = 'Enter the date of issue before printing.';
                error.style.display = 'block';
                return false;
            }

            return { keep: false, value: value };
        }
    }).then(function (r) {
        if (!r.isConfirmed || !r.value) return;
        if (r.value.keep) { onReady(null); return; }

        // Only a confirmed edit is allowed to replace a date already on record;
        // filling a blank one never needs to.
        onReady({
            issue_date: r.value.value,
            issue_date_apply: unlocked ? 'all' : 'missing'
        });
    });
}

function rofoToday() {
    var d = new Date();
    return d.getFullYear() + '-'
        + String(d.getMonth() + 1).padStart(2, '0') + '-'
        + String(d.getDate()).padStart(2, '0');
}

// Saves the date on its own, for the routes that navigate to the letter instead of
// posting the print form.
function rofoSaveIssueDate(ids, chosen) {
    return fetch('{{ route('land-rofos.issue-date') }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': rofoCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ ids: ids, issue_date: chosen.issue_date, issue_date_apply: chosen.issue_date_apply })
    });
}

// The public entry point every print button calls: ask for the date, then hand the
// answer to the pipeline as one more extra.
function printRofoBatch(ids, copies, extras) {
    if (!ids || !ids.length) return;

    rofoAskIssueDate(ids, function (chosen) {
        var merged = extras || {};
        if (chosen) {
            merged = Object.assign({}, merged, chosen);
        }
        runRofoPrintPipeline(ids, copies, merged);
    });
}

function runRofoPrintPipeline(ids, copies, extras) {
    if (!ids || !ids.length) return;

    var csrf = rofoCsrf();

    // The caller already named the pass — a menu item, not a button that has to be
    // asked what it meant. Nothing is asked here, including the resume prompt:
    // "Print Duplicate & Triplicate" IS the resume, chosen outright.
    if (copies) {
        runNamedBatchPass(ids, csrf, copies, extras);
        return;
    }

    fetch('{{ route('land-rofos.batch-print-status') }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ ids: ids })
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (data && data.success && data.awaiting_office > 0) {
            askResumeOrRestart(ids, data, csrf, extras);
        } else {
            askHowToPrintBatch(ids, csrf, extras);
        }
    })
    // The status is a convenience, not a gate: if it cannot be read the operator
    // still gets the ordinary dialog rather than a dead button.
    .catch(function () { askHowToPrintBatch(ids, csrf, extras); });
}

// One pass, named by the caller. The print tab is claimed here, synchronously
// inside the click that chose the menu item — a tab opened after an await is what
// pop-up blockers stop.
function runNamedBatchPass(ids, csrf, copies, extras) {
    if (copies === 'office') {
        var officeWindow = window.open('', 'rofoPrintOffice');

        // Asked so that a batch only half of which is outstanding prints the half
        // that is. If the answer cannot be had, every selected file is printed —
        // the operator asked for these office copies either way.
        fetch('{{ route('land-rofos.batch-print-status') }}', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ ids: ids })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var due = (data && data.success && data.resume_ids && data.resume_ids.length)
                ? data.resume_ids
                : ids;
            runOfficeCopies(due, csrf, officeWindow, extras);
        })
        .catch(function () { runOfficeCopies(ids, csrf, officeWindow, extras); });
        return;
    }

    var twoRuns = (copies === 'original');
    var printWindow = window.open('', twoRuns ? 'rofoPrintOriginals' : 'rofoBatchPrint');

    fetch('{{ route('land-rofos.batch-print-log') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(rofoWithExtras({ ids: ids, copies: twoRuns ? 'original' : 'all' }, extras))
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (!data.success) throw new Error(data.message || 'Failed to record the print.');

        if (twoRuns) {
            // Run 2 is not started for them: the paper has to be changed first, and
            // only the operator knows when that is done. The batch is recorded as
            // held at this point, so the menu's third item finishes it whenever.
            submitBatchPrint(ids, csrf, printWindow, 'original', 'rofoPrintOriginals', false, extras);
            promptOfficeCopiesRun(ids, csrf, extras);
        } else {
            submitBatchPrint(ids, csrf, printWindow, 'all', 'rofoBatchPrint', true, extras);
        }
    })
    .catch(function (err) {
        if (printWindow) { try { printWindow.close(); } catch (e) {} }
        Swal.fire({ icon: 'error', title: 'Not printed', text: err.message || 'Network error recording the batch print.' });
    });
}

// Shown when a previous split print stopped after the Originals. Resuming prints
// only the office copies still owed, and only for the files that owe them.
function askResumeOrRestart(ids, status, csrf, extras) {
    var resumeIds = status.resume_ids || [];

    Swal.fire({
        icon: 'info',
        title: 'Resume this batch?',
        html: '<b>' + status.awaiting_office + '</b> of <b>' + status.total + '</b> RofO(s) in this selection '
            + 'already had the <b>Original</b> printed'
            + (status.originals_at ? ' on <b>' + rofoEscHtml(status.originals_at) + '</b>' : '')
            + ', but not the Duplicate and Triplicate.'
            + '<div style="margin-top:14px;text-align:left;font-size:13px;line-height:1.5">'
            +   '<div><b>Resume</b> &mdash; prints the <b>' + (resumeIds.length * 2) + '</b> office copies still '
            +     'outstanding, on plain paper. No Original is reprinted, so no security paper is used.</div>'
            +   '<div style="margin-top:6px"><b>Start over</b> &mdash; prints all <b>' + (ids.length * 3) + '</b> '
            +     'letters again from the top, Originals included. Use this only if the first run was spoilt.</div>'
            + '</div>'
            // A selection can hold both half-printed and untouched files. Resume
            // deals only with the half-printed ones, and saying so here is the
            // difference between the rest being printed next and being forgotten.
            + (status.not_started > 0
                ? '<div style="margin-top:12px;text-align:left;font-size:12px;color:#475569">'
                  + 'The other <b>' + status.not_started + '</b> in this selection have not been printed at all. '
                  + '<b>Resume</b> leaves them where they are &mdash; print them on the next run.'
                  + '</div>'
                : ''),
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'Resume &mdash; Duplicate &amp; Triplicate',
        denyButtonText: 'Start over',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0f766e',
        denyButtonColor: '#7c3aed',
    }).then(function (r) {
        if (r.isDenied) {
            askHowToPrintBatch(ids, csrf, extras);
            return;
        }
        if (!r.isConfirmed) return;

        // Claimed inside the click, or the pop-up blocker takes it.
        var officeWindow = window.open('', 'rofoPrintOffice');
        runOfficeCopies(resumeIds, csrf, officeWindow, extras);
    });
}

// The original dialog: one run, or two with the paper changed in between.
function askHowToPrintBatch(ids, csrf, extras) {
    // Two ways to put the same letters on paper. Asked here rather than as a second
    // button in the table, because the difference is only meaningful once explained
    // — and this is the dialog that already stands between a click and a lot of paper.
    Swal.fire({
        icon: 'question',
        title: 'Print this batch?',
        html: '<b>' + ids.length + '</b> RofO(s) &times; 3 copies each = <b>' + (ids.length * 3) + '</b> letters,'
            + '<br>recorded as printed either way.'
            + '<div style="margin-top:14px;text-align:left;font-size:13px;line-height:1.5">'
            +   '<div><b>All at once</b> &mdash; one run: each file\'s Original, Duplicate and Triplicate together.</div>'
            +   '<div style="margin-top:6px"><b>Originals first</b> &mdash; <b>two runs</b>. All the Originals print '
            +     'on their own, so the colour / security paper goes in the tray for those alone. Change the paper, '
            +     'then the second run prints every Duplicate and Triplicate. If you stop after the first run, this '
            +     'button picks the batch up at the second one instead of starting again.</div>'
            + '</div>',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: 'All at once',
        denyButtonText: 'Originals first',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#7c3aed',
        denyButtonColor: '#0f766e',
    }).then(function (r) {
        if (!r.isConfirmed && !r.isDenied) return;
        var twoRuns = r.isDenied;

        // Claim the tab now, while still inside the click that opened the dialog —
        // opening it after the await below is what pop-up blockers stop.
        var printWindow = window.open('', twoRuns ? 'rofoPrintOriginals' : 'rofoBatchPrint');

        // Each run records its own half. The Originals run is what counts the batch
        // as printed; the office run that follows it does not count it a second
        // time. Recording per run is also what leaves the batch resumable — a run
        // that never happened is not stamped, so the dialog can still see it is due.
        fetch('{{ route('land-rofos.batch-print-log') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(rofoWithExtras({ ids: ids, copies: twoRuns ? 'original' : 'all' }, extras))
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message || 'Failed to record the print.');

            if (twoRuns) {
                // No reload yet — this page has to stay alive to ask for run 2.
                submitBatchPrint(ids, csrf, printWindow, 'original', 'rofoPrintOriginals', false, extras);
                promptOfficeCopiesRun(ids, csrf, extras);
            } else {
                submitBatchPrint(ids, csrf, printWindow, 'all', 'rofoBatchPrint', true, extras);
            }
        })
        .catch(function (err) {
            if (printWindow) { try { printWindow.close(); } catch (e) {} }
            Swal.fire({ icon: 'error', title: 'Not printed', text: err.message || 'Network error recording the batch print.' });
        });
    });
}

// Run 2, from either route into it: straight after run 1, or resumed later. The
// office copies are stamped only once they are actually sent to a printer, so a
// run abandoned here leaves the batch exactly as resumable as it was.
function runOfficeCopies(ids, csrf, officeWindow, extras) {
    if (!ids || !ids.length) {
        if (officeWindow) { try { officeWindow.close(); } catch (e) {} }
        Swal.fire({ icon: 'info', title: 'Nothing outstanding', text: 'Every office copy in this batch has already been printed.' });
        return;
    }

    fetch('{{ route('land-rofos.batch-print-log') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(rofoWithExtras({ ids: ids, copies: 'office' }, extras))
    })
    .then(function (res) { return res.json(); })
    .then(function (data) {
        if (!data.success) throw new Error(data.message || 'Failed to record the print.');
        submitBatchPrint(ids, csrf, officeWindow, 'office', 'rofoPrintOffice', true, extras);
    })
    .catch(function (err) {
        if (officeWindow) { try { officeWindow.close(); } catch (e) {} }
        Swal.fire({ icon: 'error', title: 'Not printed', text: err.message || 'Network error recording the office copies.' });
    });
}

// Run 2 of the two-run print. Deliberately a button the operator presses rather
// than something that fires on its own: between the runs the paper in the tray has
// to be changed, and only they know when that is done. The second tab is opened
// from inside this click, which is what keeps the pop-up blocker off it.
function promptOfficeCopiesRun(ids, csrf, extras) {
    Swal.fire({
        icon: 'info',
        title: 'Originals are in the other tab',
        html: 'Print them, then put <b>plain paper</b> in the tray.'
            + '<div style="margin-top:12px;text-align:left;font-size:13px;line-height:1.5">'
            +   'When that is done, run the second half: <b>' + (ids.length * 2) + '</b> office copies '
            +   '(a Duplicate and a Triplicate for each of the ' + ids.length + ' files), black &amp; white.'
            + '</div>'
            + '<div style="margin-top:10px;text-align:left;font-size:12px;color:#475569">'
            +   'Not now is safe: the batch is held at this point, and pressing <b>Print batch</b> again '
            +   'picks it up here rather than reprinting the Originals.'
            + '</div>',
        showCancelButton: true,
        confirmButtonText: 'Print Duplicate &amp; Triplicate',
        cancelButtonText: 'Not now',
        confirmButtonColor: '#0f766e',
        allowOutsideClick: false,
    }).then(function (r) {
        if (!r.isConfirmed) {
            // Declined for now. The Originals are already recorded, so the list has
            // to catch up — and the office copies are still stamped as outstanding,
            // which is what the resume prompt reads when the paper is ready.
            window.location.reload();
            return;
        }
        var officeWindow = window.open('', 'rofoPrintOffice');
        runOfficeCopies(ids, csrf, officeWindow, extras);
    });
}

// Posts the ids to the print route, rendering into the tab already opened above.
//
// copies: 'all' (every file's three copies in one run), 'original' (the Originals
// alone) or 'office' (Duplicate + Triplicate alone). Absent reads as 'all' on the
// server, so an older caller prints exactly what it always did.
//
// windowName must match the name the tab was opened with, or the form posts into a
// new blank tab and the one already open sits there empty.
//
// reload: the list behind this moves rows into Printed, so it is refreshed after
// the post — EXCEPT on run 1 of a two-run print, where the page must survive long
// enough to ask for run 2.
// A re-issued letter carries two more things into the print: the watermark and the
// notice naming the letter it supersedes. The template reads them off the request,
// so they only have to reach the form.
function rofoWithExtras(body, extras) {
    if (extras && extras.reissuance) {
        body.reissuance = extras.reissuance;
    }
    return body;
}

// ── White copies of a whole batch ──────────────────────────────────────────
// The proofing stage for the Batches tab. A batch is where an error is most
// expensive — one wrong field repeated across every letter in it — so reading the
// whole set through on ordinary paper before any of it reaches security stock is
// the point of this button.
//
// Deliberately simple next to the batch print beside it. That one has to ask which
// pass, record the run before the tab opens, and know how far a split print got;
// none of that applies here, because a proof puts nothing on record. It fetches the
// batch's ids and posts them, and that is the whole of it.
//
// The tab is claimed synchronously inside the click — a window opened after an
// await is what pop-up blockers stop.
function openBatchWhiteCopy(batchId) {
    loadRofoBatchChildren(batchId)
        .then(function (data) {
            // Only a generated RofO has a letter behind it; the rest would render as
            // empty frames, which is not a proof of anything.
            var ids = (data.children || [])
                .filter(function (c) { return String(c.rofo_status || '').toLowerCase() === 'generated'; })
                .map(function (c) { return c.id; });

            if (!ids.length) {
                Swal.fire({
                    icon: 'info',
                    title: 'Nothing to proofread yet',
                    text: 'No RofO in this batch has been generated, so there is no letter to print a white copy of.'
                });
                return;
            }

            rofoBatchWhiteCopyFor(ids, batchId);
        })
        .catch(function (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Network error.' });
        });
}

// The White Copy card for a whole batch. It asks the server which of these letters
// are still undated, then opens the card with that count — which is the only place
// a date of issue is entered now, for a batch as much as for a single letter.
//
// The date is not written here: it rides on the print request as issue_date, and
// the server writes it to the letters that have none as it renders them. That is
// the same path the official batch print uses, so a date entered on the proof and
// a date entered on a print cannot end up meaning different things.
function rofoBatchWhiteCopyFor(ids, label) {
    fetch('{{ route('land-rofos.issue-dates') }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': rofoCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ ids: ids })
    })
    .then(function (r) { return r.json(); })
    // The count is a convenience, not a gate: if it cannot be read the card still
    // opens and simply asks for a date.
    .catch(function () { return null; })
    .then(function (res) {
        var rows    = (res && res.data) ? res.data : [];
        var missing = rows.length ? rows.filter(function (r) { return !r.date_issued; }).length : ids.length;

        // The dates the letters already carry, so the card shows what will print
        // rather than only how many are still blank — the date is one of the things
        // the proof exists to have read back.
        var dates = rows.map(function (r) { return r.date_issued; })
            .filter(Boolean)
            .filter(function (d, i, a) { return a.indexOf(d) === i; })
            .sort();

        WhiteCopy.open({
            ref:          label,
            ownsDate:     true,
            count:        ids.length,
            missingDates: missing,
            dates:        dates,
            onGenerate:   function (date) {
                var extras = {};
                if (date && missing > 0) {
                    extras.issue_date = date;
                    extras.issue_date_apply = 'missing';
                }
                submitBatchWhiteCopy(ids, rofoCsrf(), null, extras);
            }
        });
    });
}

// Posted rather than linked, for the same reason the batch print is: the ids are a
// list, and a list of a hundred of them does not belong in a URL.
//
// Nothing is logged before or after, and the page behind is NOT reloaded — no row
// has changed state, and reloading would only throw away the expanded batch the
// operator is working through.
function submitBatchWhiteCopy(ids, csrf, proofWindow, extras) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('land-rofos.batch-white-copy') }}';
    form.target = proofWindow ? 'rofoBatchWhiteCopy' : '_blank';

    var token = document.createElement('input');
    token.type = 'hidden'; token.name = '_token'; token.value = csrf;
    form.appendChild(token);

    // What each letter prints as DATE OF ISSUE, and what the server writes to the
    // records that have none as it renders them. 'missing' always: one answer given
    // for many files must never overwrite a date a letter already carries.
    if (extras && extras.issue_date) {
        [['issue_date', extras.issue_date], ['issue_date_apply', extras.issue_date_apply || 'missing']].forEach(function (pair) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = pair[0]; inp.value = pair[1];
            form.appendChild(inp);
        });
    }

    ids.forEach(function (id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function submitBatchPrint(ids, csrf, printWindow, copies, windowName, reload, extras) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('land-rofos.batch-print') }}';
    form.target = printWindow ? (windowName || 'rofoBatchPrint') : '_blank';

    var token = document.createElement('input');
    token.type = 'hidden'; token.name = '_token'; token.value = csrf;
    form.appendChild(token);

    var which = document.createElement('input');
    which.type = 'hidden'; which.name = 'copies'; which.value = copies || 'all';
    form.appendChild(which);

    // What the letter prints as DATE OF ISSUE, and what the server writes to the
    // record's date_issued as it renders — on the rows that have none.
    if (extras && extras.issue_date) {
        [['issue_date', extras.issue_date], ['issue_date_apply', extras.issue_date_apply || 'missing']].forEach(function (pair) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = pair[0]; inp.value = pair[1];
            form.appendChild(inp);
        });
    }

    if (extras && extras.reissuance) {
        [['supersede', '1'], ['reissue_source', extras.reissuance]].forEach(function (pair) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = pair[0]; inp.value = pair[1];
            form.appendChild(inp);
        });
    }

    ids.forEach(function (id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // The rows have moved to Printed, so refresh the list behind the print tab.
    if (reload !== false) {
        setTimeout(function () { window.location.reload(); }, 800);
    }
}

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

    // Delegates to the shared reset-security-paper-modal component (included in
    // layouts/app) so Land RofO, SLTR and ST offer the same reasons and honour
    // the same void rules.
    // NB: never write the component's angle-bracket tag form in this file.
    // Blade compiles component tags even inside a JS comment, which renders the
    // whole component here — its closing script tag then ends this block early
    // and every function below it silently disappears.
    function resetSecurityPaperCode(id, url, fileNumber, currentSerial, assignUrl) {
        openResetSecurityPaperModal(id, fileNumber || '', currentSerial || '', url, {
            assignEndpoint: assignUrl
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
@include('components.applicant-phone-actions')

@endsection
