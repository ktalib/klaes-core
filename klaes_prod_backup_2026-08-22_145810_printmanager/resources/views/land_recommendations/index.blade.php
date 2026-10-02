@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Recommendation For Grant of RoFO</h1>
                    @if(!empty($isOssView))
                        <p class="oss-label" style="color:#ea1b1b">LAND ONE STOP SHOP</p>
                    @else
                        <p class="text-slate-500 text-sm mt-1">Manage applications, approvals, and controlled printing.</p>
                    @endif
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('land-recommendations.index') }}" method="GET" class="flex items-center gap-3 flex-1 md:w-auto">
                        {{-- The page you are on, not the list you are looking at: on the
                             OSS tab of the Land page these two differ. --}}
                        <input type="hidden" name="type" value="{{ $pageType ?? (!empty($isOssView) ? 'OSS' : 'ROFO') }}">
                        <input type="hidden" name="tab" value="{{ $tab ?? 'not_printed' }}">
                        
                        {{-- The per-user filter is gone: the register is shown whole, so
                             a recommendation captured by a colleague is on the list like
                             any other. --}}
                        <div class="relative group flex-1 md:w-80">
                            <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                            <input type="text" 
                                   name="search" 
                                   value="{{ request('search') }}"
                                   placeholder="Search file, applicant, or location..." 
                                   class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                        </div>
                    </form>
                    <button type="button" onclick="openRecordsExportModal()"
                        class="inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100 whitespace-nowrap">
                        <i data-lucide="download" class="h-5 w-5"></i>
                        <span>Export Records</span>
                    </button>
                    @if(empty($isOssView))
                        <a href="{{ route('land-recommendations.create') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-100 whitespace-nowrap">
                            <i data-lucide="plus-circle" class="h-5 w-5"></i>
                            <span>New Recommendation</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Statistics Cards -->
            {{-- The cards count the list under them. On the OSS tab that is the OSS
                 register, while the tab badges keep counting the Land one. --}}
            @php $cardStats = $cardStats ?? $stats; @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <!-- Total Records -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="file-text" class="h-32 w-32 text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                            <i data-lucide="file-text" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Records</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($cardStats['total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>All applications</span>
                        <span class="text-blue-500">Active Database</span>
                    </div>
                </div>

                <!-- Pending -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="clock" class="h-32 w-32 text-amber-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100 shadow-sm">
                            <i data-lucide="clock" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending Approval</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($cardStats['pending']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Awaiting review</span>
                        <span class="text-amber-500 flex items-center gap-1">Action Required</span>
                    </div>
                </div>

                <!-- Approved / Total Ground Rent -->
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-blue-600 to-blue-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="check-circle" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="check-circle" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest">Approved Applications</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($cardStats['approved']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-blue-100 uppercase tracking-widest">
                        <span>Total Rent Value</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">₦{{ number_format($cardStats['total_ground_rent']) }}</span>
                    </div>
                </div>
            </div>

            {{-- Printed / Not-Printed tabs --}}
            @php
                $tab = $tab ?? 'not_printed';
                // Links stay on the page you are on. $isOssView says what is being
                // listed, which on the OSS tab is not the same thing.
                $pageType = $pageType ?? (!empty($isOssView) ? 'OSS' : 'ROFO');
            @endphp
            <div class="flex items-center gap-2 mb-4 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm w-full sm:w-max">
                <a href="{{ route('land-recommendations.index', array_filter(['type' => $pageType, 'tab' => 'not_printed', 'search' => request('search')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'not_printed' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="file-clock" class="h-4 w-4"></i>
                    Not Printed
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'not_printed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['not_printed']) }}</span>
                </a>
                <a href="{{ route('land-recommendations.index', array_filter(['type' => $pageType, 'tab' => 'printed', 'search' => request('search')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'printed' ? 'bg-green-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="printer-check" class="h-4 w-4"></i>
                    Printed
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'printed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['printed']) }}</span>
                </a>
                {{-- Batches get their own tab because they do not survive this list's
                     pagination: a 100-child batch is one collapsed row whose children
                     are spread over five pages, so expanding it here shows only the 20
                     that happen to be on the page. On the Batches tab one row is one
                     whole batch and expanding it loads every child. --}}
                @if(($stats['batches'] ?? 0) > 0)
                <a href="{{ route('land-recommendations.index', array_filter(['type' => $pageType, 'tab' => 'batches', 'search' => request('search')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'batches' ? 'bg-violet-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="layers" class="h-4 w-4"></i>
                    Batches
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'batches' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['batches']) }}</span>
                </a>
                @endif
                {{-- OSS recommendations, on the Land page. They are still a page of
                     their own (the menu links to ?type=OSS); this is the same list
                     reached without leaving the register. The OSS register is not
                     split by print state, so this tab is the whole of it. --}}
                @if($pageType !== 'OSS' && ($stats['oss'] ?? 0) > 0)
                <a href="{{ route('land-recommendations.index', array_filter(['type' => $pageType, 'tab' => 'oss', 'search' => request('search')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'oss' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="store" class="h-4 w-4"></i>
                    OSS
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'oss' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['oss']) }}</span>
                </a>
                @endif
            </div>

            @if($tab === 'batches')
            {{-- ── Batches ───────────────────────────────────────────────────────
                 One row per subdivision batch, whole. The child count is the real
                 one, and expanding fetches every child rather than showing whatever
                 slice of them the main list's paging left on this page. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between flex-wrap gap-3">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="layers" class="h-4 w-4 text-violet-600"></i>
                        Subdivision Batches
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ number_format($batches->total()) }} batch(es)</span>
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
                                <th class="px-6 py-4 text-center whitespace-nowrap">Children</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Approved</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">RofO Generated</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Created</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($batches as $i => $b)
                            @php
                                $total     = (int) $b->total;
                                $approved  = (int) $b->approved_count;
                                $generated = (int) $b->generated_count;
                                $pending   = $total - $approved;
                                $creator   = $batchCreators[$b->created_by] ?? null;
                            @endphp
                            <tr class="batch-row hover:bg-violet-50/40 transition cursor-pointer" data-batch="{{ $b->rofo_batch_id }}" aria-expanded="false">
                                <td class="px-4 py-4 text-center">
                                    <i data-lucide="chevron-right" class="batch-chevron h-4 w-4 text-violet-600 transition-transform"></i>
                                </td>
                                <td class="px-4 py-4 text-center text-slate-400 font-bold">{{ $batches->firstItem() + $i }}</td>
                                <td class="px-6 py-4">
                                    {{-- A regular batch has no mother file: it is a set of unrelated
                                         files, so it is labelled by what it is rather than left blank. --}}
                                    @php $batchLabel = trim((string) ($b->mother_file_no ?: $b->old_file_number)); @endphp
                                    <span class="font-mono font-black text-slate-900">{{ $batchLabel !== '' ? $batchLabel : 'Regular files' }}</span>
                                    <span class="block text-[10px] text-slate-400">{{ $b->application_type ?: ($batchLabel === '' ? $total . ' files' : '') }}</span>
                                </td>
                                <td class="px-6 py-4 font-mono text-[11px] text-slate-500">{{ $b->rofo_batch_id }}</td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-violet-600 text-white">{{ $total }}</span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($pending === 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All {{ $total }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ $approved }} of {{ $total }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center">
                                    @if($generated >= $total)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All {{ $total }}</span>
                                    @elseif($generated > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ $generated }} of {{ $total }}</span>
                                    @else
                                        <span class="text-[10px] font-bold text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600 whitespace-nowrap">{{ $creator ? trim($creator->first_name . ' ' . $creator->last_name) : '—' }}</td>
                                <td class="px-6 py-4 text-slate-500 whitespace-nowrap text-xs">{{ $b->created_at ? \Carbon\Carbon::parse($b->created_at)->format('d/m/Y H:i') : '—' }}</td>
                                <td class="px-6 py-4 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                    {{-- Re-opens the whole batch in the capture form, filled
                                         back in. Editing one child at a time is still there on
                                         the main list; this is for a correction that runs
                                         across the batch. --}}
                                    {{-- Everything captured against every child, side by side and
                                         read-only. The expander below answers which files are in
                                         the batch; this answers what is on them. --}}
                                    <div x-data="{
                                        open: false,
                                        menuStyle: {},
                                        toggleMenu($event) {
                                            if (!this.open) {
                                                const rect = $event.currentTarget.getBoundingClientRect();
                                                this.menuStyle = {
                                                    position: 'fixed',
                                                    top: (rect.bottom + 4) + 'px',
                                                    left: Math.max(8, rect.right - 224) + 'px',
                                                    zIndex: 99999
                                                };
                                                if (window.innerHeight - rect.bottom < 200) {
                                                    this.menuStyle.top = 'auto';
                                                    this.menuStyle.bottom = (window.innerHeight - rect.top + 4) + 'px';
                                                }
                                            }
                                            this.open = !this.open;
                                        }
                                    }" class="relative inline-block text-left">
                                        <button @click="toggleMenu($event)" @click.away="open = false" type="button"
                                            class="inline-flex items-center p-2 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition">
                                            <i data-lucide="more-vertical" class="h-5 w-5"></i>
                                        </button>

                                        <template x-teleport="body">
                                            <div x-show="open"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 :style="menuStyle"
                                                 class="w-56 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                                 style="display: none;">
                                                <div class="py-1 text-left">
                                                    <a href="{{ route('land-recommendations.batch-records', $b->rofo_batch_id) }}"
                                                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                        <i data-lucide="table-2" class="h-4 w-4"></i> View records
                                                    </a>
                                                    <a href="{{ route('land-recommendations.batch-edit', $b->rofo_batch_id) }}"
                                                        class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                        <i data-lucide="pencil" class="h-4 w-4"></i> Edit batch
                                                    </a>

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    @if($pending > 0)
                                                        <button type="button" @click="open = false"
                                                            onclick='approveWholeBatch(@json($b->rofo_batch_id))'
                                                            class="flex w-full items-center gap-2 px-4 py-2.5 text-sm font-bold text-green-600 hover:bg-green-50 transition">
                                                            <i data-lucide="check-circle" class="h-4 w-4"></i> Approve all ({{ $pending }})
                                                        </button>
                                                        <span class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed italic">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i> Print all (locked)
                                                        </span>
                                                    @else
                                                        <a href="{{ route('land-recommendations.batch-print', $b->rofo_batch_id) }}" target="_blank"
                                                            class="flex items-center gap-2 px-4 py-2.5 text-sm font-bold text-violet-700 hover:bg-violet-50 transition">
                                                            <i data-lucide="printer" class="h-4 w-4"></i> Print all ({{ $total }})
                                                        </a>
                                                    @endif
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            {{-- Filled on first expand from the batch-children endpoint. --}}
                            <tr class="batch-children-row hidden" data-batch-children="{{ $b->rofo_batch_id }}">
                                <td colspan="10" class="p-0 bg-violet-50/30"></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center text-slate-400">
                                    <i data-lucide="layers" class="h-8 w-8 mx-auto mb-3 text-slate-300"></i>
                                    No subdivision batches have been captured yet.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($batches->hasPages())
                <div class="px-6 py-4 border-t border-slate-200">{{ $batches->links() }}</div>
                @endif
            </div>
            @else
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between flex-wrap gap-3">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-blue-600"></i>
                        {{ $tab === 'oss' ? 'OSS Records' : 'Application Records' }}
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ $tab === 'oss' ? 'Land One Stop Shop' : ($tab === 'printed' ? 'Printed' : 'Not Printed') }}</span>
                    </h3>
                    @if(empty($isOssView))
                    <div id="batch-toolbar" class="hidden items-center gap-3">
                        <span id="batch-count-label" class="text-xs font-bold text-slate-500"></span>
                        <button type="button" onclick="batchApprove()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 transition shadow-sm">
                            <i data-lucide="check-circle" class="h-4 w-4"></i> Batch Approve
                        </button>
                    </div>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[2000px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                @if(empty($isOssView))
                                <th class="px-4 py-4 text-center whitespace-nowrap">
                                    <input type="checkbox" id="select-all-chk" class="rounded border-slate-300 text-green-600 focus:ring-green-500 cursor-pointer" title="Select all pending">
                                </th>
                                @endif
                                <th class="px-4 py-4 text-center whitespace-nowrap">S/N</th>
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Landuse/Purpose Clause</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap text-blue-600">Applicant Address</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Layout Plan</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Dev. Period</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Prep. Fees</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Value</th>
                                {{-- <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Charge</th> --}}
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Generated</th>
                                <th class="px-6 py-4 whitespace-nowrap text-blue-600">Application Date</th>
                                <th class="px-6 py-4 whitespace-nowrap text-green-600">Date Printed</th>
                                {{-- No Actions column on the OSS tab: OSS recommendations are
                                     printed in OSS itself, so this list is a read-only register
                                     of what has already been printed. --}}
                                @if(empty($ossTab))
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            {{-- Batches are not shown here: they live on the Batches tab, whole,
                                 where a 100-child batch is one row that expands to all 100 rather
                                 than to whichever 20 this page happened to hold. Batched records
                                 are filtered out of this list entirely — except under a search,
                                 which must still be able to find any file number. --}}
                            @forelse($recommendations as $rec)

                            <tr class="hover:bg-slate-50/50 transition row-item"
                                data-id="{{ $rec->id }}" data-status="{{ $rec->status }}">
                                @if(empty($isOssView))
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_PENDING)
                                        <input type="checkbox" class="row-checkbox rounded border-slate-300 text-green-600 focus:ring-green-500 cursor-pointer" value="{{ $rec->id }}">
                                    @endif
                                </td>
                                @endif
                                <td class="px-4 py-2 text-center text-slate-500 whitespace-nowrap">{{ ($recommendations->currentPage() - 1) * $recommendations->perPage() + $loop->iteration }}</td>
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">
                                    <div>{{ $rec->file_number }}</div>
                                    {{-- The recommendation's own Serial No., which exists only once it has
                                         been printed. land_rofo_serial_no is deliberately not shown here:
                                         it is the RofO's security paper code, not a recommendation serial. --}}
                                    @php $recSc = $recSerials[strtoupper(trim((string) $rec->file_number))] ?? null; @endphp
                                    @if($recSc)
                                        <div style="display:flex; align-items:center; gap:5px; margin-top:3px; letter-spacing:normal;" title="Serial No.">
                                            <span style="line-height:1; color:#dc2626; display:inline-flex; flex-direction:column; align-items:center; font-weight:900; font-family:Arial, sans-serif;">
                                                <span style="border-bottom:1.5px solid #dc2626; padding-bottom:1px; font-size:11px;">{{ $recSc['alphabet'] }}</span>
                                                <span style="padding-top:1px; font-size:11px;">{{ $recSc['digits_start'] }}</span>
                                            </span>
                                            <span style="font-size:18px; font-weight:900; letter-spacing:0.1em; color:#dc2626; font-family:'Courier New', monospace;">{{ $recSc['digits_end'] }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap uppercase font-bold text-blue-900">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->landuse_purpose }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap uppercase">{{ $rec->display_location }}</td>
                                <td class="px-4 py-2 text-blue-600 whitespace-nowrap font-medium italic">{{ $rec->resolved_applicant_address ?? $rec->applicant_address ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->layout_plan_no }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->term }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->development_period_label }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->preparation_fees, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->development_value, 2) }}</td>
                                {{-- <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">{{$rec->development_charge}}</td> --}}
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if(!empty($isOssView))
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                            GENERATED
                                        </span>
                                    @elseif($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-800">
                                            APPROVED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            PENDING
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->creator->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-slate-500 text-xs whitespace-nowrap">
                                    {{ $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A' }}
                                </td>
                                <td class="px-4 py-2 text-blue-600 whitespace-nowrap font-bold italic">{{ $rec->application_date ? $rec->application_date->format('Y-m-d') : ($rec->created_at ? $rec->created_at->format('Y-m-d') : 'N/A') }}</td>
                                @php $printedAt = $printDates[strtoupper(trim((string) $rec->file_number))] ?? null; @endphp
                                <td class="px-4 py-2 text-xs whitespace-nowrap {{ $printedAt ? 'text-green-700 font-semibold' : 'text-slate-400 italic' }}">
                                    {{ $printedAt ? \Carbon\Carbon::parse($printedAt)->format('Y-m-d h:i A') : 'Not printed' }}
                                </td>
                                @if(empty($ossTab))
                                @if(empty($isOssView))
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
                                                    // If menu would go below viewport, pop it up instead
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
                                                 class="w-56 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden" 
                                                 style="display: none;">
                                                <div class="py-1">
                                                    <!-- Edit Action -->
                                                    <a href="{{ route('land-recommendations.edit', $rec->id) }}" class="flex items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                        <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                    </a>

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    <!-- Approval Action -->
                                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_PENDING)
                                                        <button type="button" onclick="approveRecord('{{ $rec->id }}', '{{ $rec->file_number }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-green-600 hover:bg-green-50 transition gap-2 font-bold">
                                                            <i data-lucide="check-circle" class="h-4 w-4"></i> Approve 
                                                        </button>
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="check-circle" class="h-4 w-4 text-slate-200"></i> Approved
                                                        </span>
                                                    @endif

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    <!-- Batch Print Logic -->
                                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                                        <button type="button" 
                                                                onclick="SmartPrintManager.open('{{ $rec->file_number }}', 'Recommendation For Grant', '{{ route('land-recommendations.print', $rec->id) }}')" 
                                                                class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                            <i data-lucide="printer" class="h-4 w-4"></i>  Print Manager
                                                        </button>
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i> Print (Pending Approval)
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                @else
                                    <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap overflow-visible">
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
                                                        left: Math.max(8, rect.right - 280) + 'px',
                                                        zIndex: 99999
                                                    };
                                                    const spaceBelow = window.innerHeight - rect.bottom;
                                                    if (spaceBelow < 80) {
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

                                            <template x-teleport="body">
                                                <div x-show="open"
                                                     x-transition:enter="transition ease-out duration-100"
                                                     x-transition:enter-start="opacity-0 scale-95"
                                                     x-transition:enter-end="opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-75"
                                                     x-transition:leave-start="opacity-100 scale-100"
                                                     x-transition:leave-end="opacity-0 scale-95"
                                                     :style="menuStyle"
                                                     class="w-auto rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                                     style="display: none;">
                                                    <div class="py-1">
                                                        <button type="button"
                                                                @click="open = false"
                                                                onclick="SmartPrintManager.open('{{ $rec->file_number }}', 'OSS Recommendation For Grant', '{{ route('land-recommendations.print', $rec->id) }}')"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold whitespace-nowrap">
                                                        <i data-lucide="printer" class="h-4 w-4 flex-shrink-0"></i>
                                                        OSS Print Recommendation
                                                    </button>
                                                </div>
                                            </div>
                                            </template>
                                        </div>
                                    </td>
                                @endif
                                @endif
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ empty($isOssView) ? 20 : (empty($ossTab) ? 19 : 18) }}" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">No recommendations found.</p>
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

@push('scripts')
<script>
    // ── Batches tab ────────────────────────────────────────────────────────
    // One row per batch. Expanding fetches EVERY child from the server rather
    // than revealing the rows that happen to share this page — which is the whole
    // reason this tab exists, since on the main list a 100-child batch expands to
    // show only the 20 the pagination left behind.
    var CHILDREN_URL = @json(url('land-recommendations/batch'));

    function statusPill(value, okValue, okLabel, pendingLabel) {
        var ok = String(value || '').toLowerCase() === okValue;
        return '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold '
            + (ok ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700') + '">'
            + (ok ? okLabel : pendingLabel) + '</span>';
    }

    function escHtml(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.addEventListener('click', function (e) {
        var row = e.target.closest('tr.batch-row');
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

        // Fetched once and kept — a batch does not change while the page is open.
        if (holder.dataset.loaded === '1') return;

        var cell = holder.querySelector('td');
        cell.innerHTML = '<div class="px-8 py-6 text-xs text-slate-500">Loading all children…</div>';

        fetch(CHILDREN_URL + '/' + encodeURIComponent(batch) + '/children', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Could not load the batch.');
                holder.dataset.loaded = '1';

                var rows = data.children.map(function (c) {
                    return '<tr class="border-b border-violet-100/70 hover:bg-white/70">'
                        + '<td class="px-4 py-2.5 text-center text-[11px] font-bold text-slate-400">' + c.seq + '</td>'
                        + '<td class="px-4 py-2.5 font-mono text-xs font-bold text-slate-900">' + escHtml(c.file_number) + '</td>'
                        + '<td class="px-4 py-2.5 text-xs text-slate-700">' + escHtml(c.applicant_name) + '</td>'
                        + '<td class="px-4 py-2.5 text-xs text-slate-600">' + escHtml(c.plot_number) + '</td>'
                        + '<td class="px-4 py-2.5 text-xs text-slate-600">' + escHtml(c.location) + '</td>'
                        + '<td class="px-4 py-2.5 text-xs text-slate-600">' + escHtml(c.purpose) + '</td>'
                        + '<td class="px-4 py-2.5 text-center">' + statusPill(c.status, 'approved', 'Approved', 'Pending') + '</td>'
                        + '<td class="px-4 py-2.5 text-center">' + statusPill(c.rofo_status, 'generated', 'Generated', 'Pending') + '</td>'
                        + '<td class="px-4 py-2.5 text-right whitespace-nowrap">'
                        +   '<a href="' + c.edit_url + '" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-50 rounded">Edit</a>'
                        +   '<a href="' + c.print_url + '" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-violet-700 hover:bg-violet-50 rounded">Print</a>'
                        + '</td>'
                        + '</tr>';
                }).join('');

                cell.innerHTML =
                    '<div class="px-6 py-4">'
                    + '<p class="text-[10px] font-black uppercase tracking-widest text-violet-700 mb-2">'
                    +   'All ' + data.count + ' children of this batch</p>'
                    + '<div class="overflow-x-auto rounded-lg border border-violet-200 bg-white">'
                    + '<table class="w-full text-left border-collapse min-w-[880px]">'
                    + '<thead><tr class="bg-violet-50 text-[10px] font-black text-violet-800 uppercase tracking-widest">'
                    +   '<th class="px-4 py-2.5 text-center w-10">#</th>'
                    +   '<th class="px-4 py-2.5">File Number</th>'
                    +   '<th class="px-4 py-2.5">Applicant</th>'
                    +   '<th class="px-4 py-2.5">Plot No</th>'
                    +   '<th class="px-4 py-2.5">Location</th>'
                    +   '<th class="px-4 py-2.5">Purpose</th>'
                    +   '<th class="px-4 py-2.5 text-center">Status</th>'
                    +   '<th class="px-4 py-2.5 text-center">RofO</th>'
                    +   '<th class="px-4 py-2.5 text-right">Actions</th>'
                    + '</tr></thead><tbody>' + rows + '</tbody></table></div></div>';
            })
            .catch(function (err) {
                cell.innerHTML = '<div class="px-8 py-6 text-xs text-rose-700">'
                    + escHtml(err.message || 'Network error loading the batch.') + '</div>';
            });
    });

    // Approve every pending child of a batch straight from the Batches tab. The ids
    // are not on the page here (the tab lists batches, not rows), so they are
    // fetched first — the same endpoint the main list's batch row uses.
    function approveWholeBatch(batchId) {
        fetch(CHILDREN_URL + '/' + encodeURIComponent(batchId) + '/children', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Could not load the batch.');
                var ids = data.children
                    .filter(function (c) { return String(c.status).toLowerCase() !== 'approved'; })
                    .map(function (c) { return c.id; });
                if (!ids.length) {
                    Swal.fire({ icon: 'info', title: 'Nothing to approve', text: 'Every recommendation in this batch is already approved.' });
                    return;
                }
                approveBatch(batchId, ids);
            })
            .catch(function (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Network error.' });
            });
    }

    // Approve every still-pending child of a batch in one call. Reuses the same
    // endpoint the row checkboxes use, so approval behaves identically either way.
    function approveBatch(batchId, ids) {
        if (!ids || !ids.length) return;

        Swal.fire({
            icon: 'question',
            title: 'Approve the whole batch?',
            text: ids.length + ' pending recommendation(s) in ' + batchId + ' will be approved.',
            showCancelButton: true,
            confirmButtonText: 'Approve all',
            confirmButtonColor: '#059669',
            cancelButtonText: 'Cancel',
        }).then(function (r) {
            if (!r.isConfirmed) return;

            fetch('{{ route('land-recommendations.batch-approve') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ ids: ids }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || 'Approval failed.');
                Swal.fire({
                    icon: 'success', title: 'Batch approved',
                    text: data.approved + ' recommendation(s) approved.',
                    timer: 1800, showConfirmButton: false,
                }).then(function () { window.location.reload(); });
            })
            .catch(function (err) {
                Swal.fire({ icon: 'error', title: 'Error', text: err.message || 'Network error approving the batch.' });
            });
        });
    }

    // One document containing every child's recommendation letter. The server
    // refuses a batch with pending rows, so say so here rather than opening a
    // window onto a 403.
    function printBatch(batchId, allApproved) {
        if (!allApproved) {
            Swal.fire({
                icon: 'warning',
                title: 'Approve the batch first',
                text: 'Every recommendation in the batch must be approved before it can be printed.',
            });
            return;
        }
        window.open('{{ url('land-recommendations/batch') }}/' + encodeURIComponent(batchId) + '/print', '_blank');
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if(session('success'))
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    confirmButtonColor: '#059669',
                    timer: 4000,
                    timerProgressBar: true,
                });
            }
        @endif

        @if(session('error'))
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: @json(session('error')),
                    confirmButtonColor: '#dc2626',
                });
            }
        @endif
    });

    @if(empty($isOssView))
    // Checkbox & batch toolbar
    const selectAllChk = document.getElementById('select-all-chk');
    const batchToolbar  = document.getElementById('batch-toolbar');
    const batchCountLabel = document.getElementById('batch-count-label');

    function getChecked() {
        return [...document.querySelectorAll('.row-checkbox:checked')].map(c => c.value);
    }

    function updateBatchToolbar() {
        const ids = getChecked();
        if (ids.length > 0) {
            batchToolbar?.classList.remove('hidden');
            batchToolbar?.classList.add('flex');
            if (batchCountLabel) batchCountLabel.textContent = `${ids.length} selected`;
        } else {
            batchToolbar?.classList.add('hidden');
            batchToolbar?.classList.remove('flex');
        }
    }

    document.querySelectorAll('.row-checkbox').forEach(chk => {
        chk.addEventListener('change', updateBatchToolbar);
    });

    if (selectAllChk) {
        selectAllChk.addEventListener('change', function () {
            document.querySelectorAll('.row-checkbox').forEach(chk => {
                chk.checked = this.checked;
            });
            updateBatchToolbar();
        });
    }

    function batchApprove() {
        const ids = getChecked();
        if (!ids.length) return;
        Swal.fire({
            title: 'Batch Approve?',
            text: `Approve ${ids.length} selected recommendation(s)? They cannot be edited after approval.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: `Yes, Approve ${ids.length} Record(s)`,
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ url('land-recommendations/batch-approve') }}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ids })
                })
                .then(r => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                .catch(err => Swal.showValidationMessage(`Request failed: ${err}`));
            }
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire('Approved!', `${result.value?.approved ?? ids.length} record(s) approved successfully.`, 'success')
                .then(() => window.location.reload());
            }
        });
    }

    function approveRecord(id, fileNumber) {
        Swal.fire({
            title: 'Approve Recommendation?',
            text: `Are you sure you want to approve ${fileNumber}? Once approved, it can be printed but not edited.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Approve it!',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ url('land-recommendations') }}/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error(response.statusText);
                    return response.json();
                })
                .catch(error => {
                    Swal.showValidationMessage(`Request failed: ${error}`);
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire('Approved!', 'Document has been approved successfully.', 'success')
                .then(() => window.location.reload());
            }
        });
    }
    @endif
</script>

{{-- Export (preview + date range + CSV + PDF) --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
@include('exports.records_export_modal', ['exportConfig' => [
    'title'         => !empty($isOssView) ? 'Export OSS Recommendations' : 'Export Land Recommendations',
    'subtitle'      => 'Consolidated report generation & export filter',
    'endpoint'      => route('land-recommendations.export'),
    'params'        => ['type' => !empty($isOssView) ? 'OSS' : 'ROFO'],
    'filename'      => !empty($isOssView) ? 'OSS_Recommendations' : 'Land_Recommendations',
    'reportTitle'   => !empty($isOssView)
        ? 'OSS Recommendation Register'
        : 'Recommendation For Grant Of Statutory Right Of Occupancy',
    'search'        => request('search'),
    'statusOptions' => !empty($isOssView)
        ? ['' => 'All Records']
        : ['' => 'All Statuses', 'approved' => 'Approved', 'pending' => 'Pending Approval'],
]])
@endpush

@push('styles')
<style>
    .oss-label {
        margin-top: 3.5mm;
        font-size: 3.2mm;
        font-weight: 700;
        letter-spacing: 1.2mm;
        color: #94a3b8;
        text-transform: uppercase;
    }
</style>
@endpush
@endsection
