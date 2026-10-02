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
                                $total     = (int) $b->total;
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
                                <td class="px-6 py-4 text-slate-600 whitespace-nowrap">{{ $creator ? trim($creator->first_name . ' ' . $creator->last_name) : '—' }}</td>
                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap text-xs">{{ $b->created_at ? \Carbon\Carbon::parse($b->created_at)->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    @if($generated > 0)
                                        {{-- Only generated RofOs can be printed; the count says how many
                                             this would actually put on paper.

                                             The three ways to run a batch are here as menu items rather
                                             than as questions asked after the click. They are not a
                                             sequence of preferences — they are the two passes a split
                                             print is made of, plus the single pass that does both at
                                             once. Naming them means the second pass can be started on
                                             its own, which is what picking up an abandoned run is. --}}
                                        <div x-data="{
                                            open: false,
                                            menuStyle: {},
                                            toggleMenu($event) {
                                                if (!this.open) {
                                                    const rect = $event.currentTarget.getBoundingClientRect();
                                                    this.menuStyle = {
                                                        position: 'fixed',
                                                        top: (rect.bottom + 4) + 'px',
                                                        left: Math.max(8, rect.right - 288) + 'px',
                                                        zIndex: 9999
                                                    };
                                                    const spaceBelow = window.innerHeight - rect.bottom;
                                                    if (spaceBelow < 240) {
                                                        this.menuStyle.top = 'auto';
                                                        this.menuStyle.bottom = (window.innerHeight - rect.top + 4) + 'px';
                                                    }
                                                }
                                                this.open = !this.open;
                                            }
                                        }" class="relative inline-block text-left">
                                            <button type="button" @click="toggleMenu($event)" @click.away="open = false"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-600 text-white text-[11px] font-bold rounded-lg hover:bg-violet-700 transition">
                                                <i data-lucide="printer" class="h-3.5 w-3.5"></i> Print batch ({{ $generated }})
                                                <i data-lucide="chevron-down" class="h-3.5 w-3.5"></i>
                                            </button>

                                            <div x-show="open" x-cloak
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 :style="menuStyle"
                                                 class="w-72 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden text-left"
                                                 style="display: none;">
                                                <div class="py-1">
                                                    <button type="button" @click="open = false; printBatchGroupCopies(@json($b->rofo_batch_id), 'all')"
                                                        class="flex w-full items-start px-4 py-2.5 text-sm text-slate-700 hover:bg-violet-50 transition gap-2 text-left">
                                                        <i data-lucide="printer" class="h-4 w-4 mt-0.5 shrink-0 text-violet-600"></i>
                                                        <span>
                                                            <span class="font-bold block">Print all</span>
                                                            <span class="text-[11px] text-slate-500">One run — Original, Duplicate and Triplicate of each.</span>
                                                        </span>
                                                    </button>
                                                    <button type="button" @click="open = false; printBatchGroupCopies(@json($b->rofo_batch_id), 'original')"
                                                        class="flex w-full items-start px-4 py-2.5 text-sm text-slate-700 hover:bg-violet-50 transition gap-2 text-left">
                                                        <i data-lucide="award" class="h-4 w-4 mt-0.5 shrink-0 text-emerald-600"></i>
                                                        <span>
                                                            <span class="font-bold block">Print Originals only</span>
                                                            <span class="text-[11px] text-slate-500">Run 1 — colour / security paper in the tray.</span>
                                                        </span>
                                                    </button>
                                                    <button type="button" @click="open = false; printBatchGroupCopies(@json($b->rofo_batch_id), 'office')"
                                                        class="flex w-full items-start px-4 py-2.5 text-sm text-slate-700 hover:bg-teal-50 transition gap-2 text-left">
                                                        <i data-lucide="copy" class="h-4 w-4 mt-0.5 shrink-0 text-teal-600"></i>
                                                        <span>
                                                            <span class="font-bold block">Print Duplicate &amp; Triplicate</span>
                                                            <span class="text-[11px] text-slate-500">Run 2 — plain paper. Picks up a batch stopped after the Originals.</span>
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
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
                                <!-- <th class="px-6 py-4 text-center text-blue-600 whitespace-nowrap">RofO Status</th> -->
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
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
                                                <button type="button" onclick="editRofORecord('{{ $rec->id }}', '{{ route('land-recommendations.edit', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                    <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                </button>

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

                                                {{-- The three passes, listed flat in this menu. They are the
                                                     same three the Batches tab offers, because the paper does
                                                     not care whether a letter was captured in a batch: the
                                                     Original goes on security stock, the office copies on plain
                                                     paper, and the second pass can be run on its own whenever
                                                     the tray is ready.

                                                     Deliberately not a submenu. This is already the row's action
                                                     menu; a menu inside it puts two clicks and a second surface
                                                     in front of a choice that is three lines long. --}}
                                                @if($rec->rofo_status === 'generated')
                                                    @php
                                                        // A re-issued letter prints with the RE-ISSUANCE watermark
                                                        // and the superseding notice, and does not consume the
                                                        // print allowance. A KLAES re-issuance replaces a set that
                                                        // was already issued, so it is the Original alone — the
                                                        // other two passes would be paper with no letter behind it.
                                                        $reissue = $rec->is_reissuance
                                                            ? (strtolower(trim((string) $rec->reissuance_source)) === 'legacy' ? 'legacy' : 'klaes')
                                                            : null;
                                                        $reissueArg = $reissue ? ", { reissuance: '" . $reissue . "' }" : '';

                                                        $passes = $reissue === 'klaes'
                                                            ? [['original', 'award', 'Print the re-issued Original', 'text-amber-700 hover:bg-amber-50']]
                                                            : [
                                                                ['all',      'printer', 'Print all 3 copies',          'text-violet-700 hover:bg-violet-50'],
                                                                ['original', 'award',   'Print Original only',         'text-emerald-700 hover:bg-emerald-50'],
                                                                ['office',   'copy',    'Print Duplicate & Triplicate', 'text-teal-700 hover:bg-teal-50'],
                                                            ];
                                                    @endphp

                                                    @if($reissue)
                                                        <div class="px-4 pt-2 pb-1 text-[10px] font-black uppercase tracking-widest text-amber-600">Re-issuance</div>
                                                    @endif

                                                    @foreach($passes as [$copiesArg, $icon, $label, $tone])
                                                        <button type="button"
                                                                onclick="printRofoBatch([{{ $rec->id }}], '{{ $copiesArg }}'{!! $reissueArg !!})"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm {{ $tone }} transition gap-2 font-bold">
                                                            <i data-lucide="{{ $icon }}" class="h-4 w-4"></i> {{ $label }}
                                                        </button>
                                                    @endforeach

                                                    <div class="border-t border-slate-100 my-1"></div>
                                                @endif

                                                @if($rec->is_reissuance)
                                                    {{-- Kept as well: the Print Manager is the step-by-step route
                                                         for a re-issued letter, and the only way to a CTC. --}}
                                                    <button type="button"
                                                            onclick="printReissuance('{{ $rec->id }}', @js($rec->file_number), @js($rec->reissuance_source))"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50 transition gap-2 font-bold">
                                                        <i data-lucide="sliders" class="h-4 w-4"></i> Print Manager
                                                    </button>
                                                @else
                                                    {{-- One file goes through the Print Manager, which walks
                                                         Original → Duplicate → Triplicate a step at a time and
                                                         works out where it is from the print_logs on the server —
                                                         so it already picks up where it stopped, on any machine.
                                                         The batch dialog's Originals-first choice is for runs of
                                                         many files, which the Print Manager cannot drive: it is
                                                         keyed to one reference number and one letter. --}}
                                                    <button type="button"
                                                            onclick="printManagerWithIssueDate({{ $rec->id }}, '{{ $rec->file_number }}', '{{ route('land-rofos.print', $rec->id) }}')"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                        <i data-lucide="printer" class="h-4 w-4"></i> Print Manager
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
                                <td colspan="{{ $ossViewOnly ? 20 : 21 }}" class="px-8 py-12 text-center">
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
    rofoAskIssueDate([id], function (chosen) {
        if (!chosen) { openReissuancePrintManager(id, fileNumber, source); return; }
        // The manager navigates to the letter itself, so unlike the batch form there
        // is nothing to hang the date on - it is stored before the manager opens.
        rofoSaveIssueDate([id], chosen)
            .then(function () { openReissuancePrintManager(id, fileNumber, source); })
            .catch(function () { openReissuancePrintManager(id, fileNumber, source); });
    });
}

// The ordinary Print Manager, behind the same date. It walks the copies one at a
// time from a URL, so like the re-issuance manager the date has to be on the record
// before it opens rather than travelling with a form post.
function printManagerWithIssueDate(id, fileNumber, url) {
    rofoAskIssueDate([id], function (chosen) {
        if (!chosen) { SmartPrintManager.open(fileNumber, 'Land RofO', url); return; }

        rofoSaveIssueDate([id], chosen)
            .then(function () { SmartPrintManager.open(fileNumber, 'Land RofO', url); })
            .catch(function () { SmartPrintManager.open(fileNumber, 'Land RofO', url); });
    });
}

function openReissuancePrintManager(id, fileNumber, source) {
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
    printRofoBatch(ids);
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
// The three ways to run one file's copies, as a menu anchored to whatever button
// asked for it. One definition for every individual print on this page, so the
// wording and the passes cannot drift apart between the places it opens from.
//
// Positioned fixed and parented to <body>: both tables it opens over scroll
// horizontally, and a menu inside one is clipped by it.
//
// opts.reissuance — 'klaes' or 'legacy' when this is a re-issued letter. A KLAES
// re-issuance replaces an already-issued set, so it is the Original alone and the
// menu is one item; a pre-KLAES one was never issued from here and prints the full
// set like any other letter.
var _rofoPrintMenuEl = null;

function rofoClosePrintMenu() {
    if (!_rofoPrintMenuEl) return;
    _rofoPrintMenuEl.remove();
    _rofoPrintMenuEl = null;
    document.removeEventListener('click', rofoClosePrintMenuOnOutside, true);
    window.removeEventListener('resize', rofoClosePrintMenu);
    window.removeEventListener('scroll', rofoClosePrintMenu, true);
}

function rofoClosePrintMenuOnOutside(e) {
    if (_rofoPrintMenuEl && !_rofoPrintMenuEl.contains(e.target)) rofoClosePrintMenu();
}

function rofoOpenPrintMenu(btn, id, opts) {
    opts = opts || {};

    // A second click on the same button closes it.
    var wasOpen = _rofoPrintMenuEl && _rofoPrintMenuEl.dataset.forId === String(id);
    rofoClosePrintMenu();
    if (wasOpen) return;

    var reissue = opts.reissuance || '';
    var suffix  = reissue ? ' The RE-ISSUANCE watermark and the superseding notice print on it.' : '';

    var items = [
        ['all',      'printer', 'text-violet-600',  'Print all',
         'One run — Original, Duplicate and Triplicate.' + suffix],
        ['original', 'award',   'text-emerald-600', 'Print Original only',
         'Colour / security paper in the tray.' + suffix],
        ['office',   'copy',    'text-teal-600',    'Print Duplicate &amp; Triplicate',
         'Plain paper. Finishes a letter stopped after the Original.']
    ];

    // The Original is the whole of a KLAES re-issuance, so the other two would be
    // offering paper that has no letter behind it.
    if (reissue === 'klaes') {
        items = [['original', 'award', 'text-amber-600', 'Print the re-issued Original',
                  'The re-issuance is the Original copy alone — the set was already issued.']];
    }

    var menu = document.createElement('div');
    menu.dataset.forId = String(id);
    menu.className = 'w-72 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden text-left';
    menu.style.position = 'fixed';
    menu.style.zIndex = '9999';
    menu.innerHTML = '<div class="py-1">' + items.map(function (it) {
        return '<button type="button" data-copies="' + it[0] + '" '
            + 'class="flex w-full items-start px-4 py-2.5 text-sm text-slate-700 hover:bg-violet-50 transition gap-2 text-left">'
            + '<i data-lucide="' + it[1] + '" class="h-4 w-4 mt-0.5 shrink-0 ' + it[2] + '"></i>'
            + '<span><span class="font-bold block">' + it[3] + '</span>'
            + '<span class="text-[11px] text-slate-500">' + it[4] + '</span></span>'
            + '</button>';
    }).join('') + '</div>';

    document.body.appendChild(menu);

    var rect = btn.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(rect.right - 288, window.innerWidth - 296)) + 'px';
    if (window.innerHeight - rect.bottom < 240) {
        menu.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
    } else {
        menu.style.top = (rect.bottom + 4) + 'px';
    }

    menu.querySelectorAll('button[data-copies]').forEach(function (b) {
        b.addEventListener('click', function () {
            var copies = b.dataset.copies;
            rofoClosePrintMenu();
            printRofoBatch([id], copies, reissue ? { reissuance: reissue } : null);
        });
    });

    _rofoPrintMenuEl = menu;
    if (window.lucide) window.lucide.createIcons();

    // Bound on the capture phase, and only once this click has finished — bound
    // now, the click that opened the menu would close it on its way back up.
    setTimeout(function () {
        document.addEventListener('click', rofoClosePrintMenuOnOutside, true);
        window.addEventListener('resize', rofoClosePrintMenu);
        window.addEventListener('scroll', rofoClosePrintMenu, true);
    }, 0);
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
                var generated = String(c.rofo_status || '').toLowerCase() === 'generated';
                return '<tr class="border-b border-violet-100/70 hover:bg-white/70">'
                    + '<td class="px-4 py-2.5 text-center text-[11px] font-bold text-slate-400">' + c.seq + '</td>'
                    + '<td class="px-4 py-2.5 font-mono text-xs font-bold text-slate-900">' + rofoEscHtml(c.file_number) + '</td>'
                    + '<td class="px-4 py-2.5 text-xs text-slate-700">' + rofoEscHtml(c.applicant_name) + '</td>'
                    + '<td class="px-4 py-2.5 text-xs text-slate-600">' + rofoEscHtml(c.plot_number) + '</td>'
                    + '<td class="px-4 py-2.5 text-xs text-slate-600">' + rofoEscHtml(c.location) + '</td>'
                    + '<td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">' + rofoEscHtml(c.serial_no || '—') + '</td>'
                    + '<td class="px-4 py-2.5 text-center">' + rofoPill(generated, 'Generated', 'Pending') + '</td>'
                    + '<td class="px-4 py-2.5 text-center">' + rofoPrintStagePill(c) + '</td>'
                    + '<td class="px-4 py-2.5 text-right whitespace-nowrap">'
                    +   (generated
                            // One child on its own is an individual print, so it goes to
                            // the Print Manager like any other single letter — it steps
                            // through Original / Duplicate / Triplicate and reads its own
                            // position from the print log, so it resumes by itself. The
                            // batch dialog stays on Print batch, above.
                            // The arguments ride on data- attributes rather than inside
                            // the onclick: a file number like RES-1993-2644(T) and a URL
                            // both carry characters that end an HTML attribute early, and
                            // a handler cut in half never runs at all.
                            ? '<button type="button" onclick="rofoOpenPrintMenu(this, ' + c.id + ')"'
                              + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-violet-700 hover:bg-violet-50 rounded">Print &#9662;</button>'
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
function printBatchGroupCopies(batchId, copies) {
    loadRofoBatchChildren(batchId)
        .then(function (data) {
            var ids = data.children
                .filter(function (c) { return String(c.rofo_status).toLowerCase() === 'generated'; })
                .map(function (c) { return c.id; });

            if (!ids.length) {
                Swal.fire({ icon: 'info', title: 'Nothing to print', text: 'No RofO in this batch has been generated yet.' });
                return;
            }
            printRofoBatch(ids, copies);
        })
        .catch(function (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Network error.' });
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
// The letter prints the APPLICATION DATE as its DATE OF ISSUE, so no letter goes to
// paper without the operator seeing the date it will carry.
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
        var missing = rows.filter(function (r) { return !r.application_date; });
        var single  = ids.length === 1;

        // A bulk run where every letter already carries its date has nothing to ask.
        if (!single && !missing.length) {
            onReady(null);
            return;
        }

        var existing = single && rows.length ? (rows[0].application_date || '') : '';

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
            : 'No application date is on record for <b>' + subject + '</b>, so the letter has nothing to print as its date of issue.')
        : subject + ' have no application date on record. The date entered here is written to those only &mdash; the rest keep the date they already carry.';

    if (typeof Swal === 'undefined') {
        if (locked) { onReady(null); return; }
        var typed = window.prompt('Application date (YYYY-MM-DD) - prints on the letter as DATE OF ISSUE', rofoToday());
        if (typed) onReady({ issue_date: typed, issue_date_apply: 'missing' });
        return;
    }

    // The input is built here rather than handed to Swal's own `input:` option: a
    // locked field with an Edit beside it and a confirmation in between is more than
    // that option can express, and a disabled Swal input is not returned at all.
    var html = '<div style="text-align:left;font-size:13px;line-height:1.5">' + body
        + '<div style="margin-top:10px;color:#475569;font-size:12px">'
        +   'This is the <b>application date</b>. It prints on the letter as <b>DATE OF ISSUE</b> '
        +   'and is saved to the record, so a reprint comes out carrying the same date.'
        + '</div>'
        + '<div style="display:flex;gap:8px;align-items:center;margin-top:14px">'
        +   '<input type="date" id="rofoIssueDateInput" value="' + rofoEscHtml(existing || rofoToday()) + '"'
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
        title: 'Application Date',
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
                error.textContent = 'Enter the application date before printing.';
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
    // record's application_date as it renders — on the rows that have none.
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

@endsection
