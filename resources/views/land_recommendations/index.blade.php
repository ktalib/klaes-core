@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Recommendation For Grant of RoFO</h1>
                    {{-- The page you are on, not the list under the tabs: the OSS tab
                         of the Land page shows OSS records without becoming the OSS
                         page, so the heading stays with $pageType. --}}
                    @if(($pageType ?? (!empty($isOssView) ? 'OSS' : 'ROFO')) === 'OSS')
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
                    @if(($pageType ?? (!empty($isOssView) ? 'OSS' : 'ROFO')) !== 'OSS')
                                                @canDo('Letter of Grant (RofO)', 'create')
<a href="{{ route('land-recommendations.create') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-100 whitespace-nowrap">
                            <i data-lucide="plus-circle" class="h-5 w-5"></i>
                            <span>New Recommendation</span>
                        </a>
                        @endcanDo
                    @endif
                </div>
            </div>

            <!-- Statistics Cards -->
            {{-- The cards and the tab badges both count the page you are on. The OSS
                 tab swaps the TABLE for the OSS register and nothing else: switching
                 these to OSS totals made the Land page read as the OSS page. --}}
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
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['total']) }}</h3>
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
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['pending']) }}</h3>
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
                            <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($stats['approved']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-blue-100 uppercase tracking-widest">
                        <span>Total Rent Value</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">₦{{ number_format($stats['total_ground_rent']) }}</span>
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
                {{-- Files whose recommendation is a document we hold rather than one
                     KLAES prints: captured with Use Subdivision Template, and the mother's
                     signed letter now scanned against them. Nothing is ever printed for
                     one, so without a tab of their own they would sit in Not Printed for
                     good — this is their Printed. --}}
                @if($pageType !== 'OSS' && ($stats['extant'] ?? 0) > 0)
                <a href="{{ route('land-recommendations.index', array_filter(['type' => $pageType, 'tab' => 'extant', 'search' => request('search')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'extant' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="file-check-2" class="h-4 w-4"></i>
                    Mother Recommendations
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'extant' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['extant']) }}</span>
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
                    OSS Recommendations
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'oss' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['oss']) }}</span>
                </a>
                @endif
                {{-- Batches get their own tab because they do not survive this list's
                     pagination: a 100-child batch is one collapsed row whose children
                     are spread over five pages, so expanding it here shows only the 20
                     that happen to be on the page. On the Batches tab one row is one
                     whole batch and expanding it loads every child. --}}
                @if(($stats['batches'] ?? 0) > 0)
                <a href="{{ route('land-recommendations.index', array_filter(['type' => $pageType, 'tab' => 'batches', 'search' => request('search')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'batches' ? 'bg-violet-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="layers" class="h-4 w-4"></i>
                    Batch Recommendations
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'batches' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['batches']) }}</span>
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
                                $lineageTotal = (int) (($batchChildTotals ?? collect())[$b->rofo_batch_id] ?? $total);
                                $lineageTotal = max($lineageTotal, $total);

                                // A subdivision batch is keyed to a mother file; a regular batch is a
                                // set of unrelated files with no mother. The children of a subdivision
                                // inherit the mother's recommendation instead of earning letters of
                                // their own, so this is what decides between the print actions and the
                                // mother's scanned letter.
                                $isSubdivision = trim((string) ($b->mother_file_no ?: '')) !== '';
                                $batchDoc      = ($batchDocuments ?? collect())[$b->rofo_batch_id] ?? null;
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
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-black bg-violet-600 text-white">{{ $total }} of {{ $lineageTotal }}</span>
                                </td>
                                {{-- Both counters are repainted in place when a child is approved
                                     on its own from the expander below, so the batch row and the
                                     children under it can never disagree. --}}
                                <td class="px-6 py-4 text-center" data-batch-approved="{{ $b->rofo_batch_id }}">
                                    @if($pending === 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All {{ $total }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ $approved }} of {{ $total }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-center" data-batch-generated="{{ $b->rofo_batch_id }}">
                                    @if($generated >= $total)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">All {{ $total }}</span>
                                    @elseif($generated > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">{{ $generated }} of {{ $total }}</span>
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
                                                 class="w-64 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                                 style="display: none;">
                                                <div class="py-1 text-left">
                                                    {{-- Every item in this menu is laid out the same way: a fixed
                                                         icon, then a left-aligned label that takes the rest of the
                                                         width. `text-left` is on the buttons because a <button>
                                                         centres its own text by default — a two-line label then sits
                                                         centred under an icon that is not, which is what this looked
                                                         like before. --}}
                                                    <a href="{{ route('land-recommendations.batch-records', $b->rofo_batch_id) }}"
                                                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                        <i data-lucide="table-2" class="h-4 w-4 shrink-0"></i>
                                                        <span class="flex-1">View records</span>
                                                    </a>
                                                    <a href="{{ route('land-recommendations.batch-edit', $b->rofo_batch_id) }}"
                                                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                        <i data-lucide="pencil" class="h-4 w-4 shrink-0"></i>
                                                        <span class="flex-1">Edit batch</span>
                                                    </a>

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    @if($isSubdivision)
                                                        {{-- The mother's letter comes first: it is what the children
                                                             inherit, and it is the step an officer opens this menu to
                                                             do. Approving 500 children that show nothing when opened
                                                             is the wrong way round.

                                                             No print actions here at all — the children inherit the
                                                             mother's grant, so the letter is printed once for the
                                                             mother and signed on paper, and that sheet is what gets
                                                             scanned in. --}}
                                                        <button type="button" @click="open = false"
                                                            onclick="uploadMotherRecommendation(@js($b->rofo_batch_id), @js($b->mother_file_no), {{ (int) $total }}, {{ $batchDoc ? 'true' : 'false' }})"
                                                            class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-bold text-violet-700 hover:bg-violet-50 transition">
                                                            <i data-lucide="upload" class="h-4 w-4 shrink-0"></i>
                                                            <span class="flex-1 leading-tight">{{ $batchDoc ? 'Replace mother recommendation' : 'Upload mother recommendation' }}</span>
                                                        </button>

                                                        @if($batchDoc)
                                                            {{-- Opens the preview card rather than a new tab: the sheet
                                                                 means nothing on its own, and everything that follows
                                                                 from reading it — Replace, Reset — is on the card. --}}
                                                            <button type="button" @click="open = false"
                                                                onclick="viewMotherRecommendation(@js($b->rofo_batch_id), @js($b->mother_file_no), @js($batchDoc->summary()), @js($batchDoc->mime_type), {{ (int) $total }})"
                                                                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-bold text-emerald-700 hover:bg-emerald-50 transition">
                                                                <i data-lucide="image" class="h-4 w-4 shrink-0"></i>
                                                                <span class="flex-1 leading-tight">View mother recommendation</span>
                                                                <span class="shrink-0 text-[10px] font-medium text-slate-400">{{ $batchDoc->summary() }}</span>
                                                            </button>

                                                            {{-- Replace corrects a wrong scan; this is for a scan that
                                                                 should not be on this batch at all, which replacing
                                                                 cannot fix. Destructive, so it sits below the pair it
                                                                 undoes and is the only red entry in the menu. --}}
                                                            <button type="button" @click="open = false"
                                                                onclick="resetRecDocUpload(@js(route('land-recommendations.batch-document.destroy', $b->rofo_batch_id)))"
                                                                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-bold text-rose-600 hover:bg-rose-50 transition">
                                                                <i data-lucide="rotate-ccw" class="h-4 w-4 shrink-0"></i>
                                                                <span class="flex-1 leading-tight">Reset upload</span>
                                                            </button>
                                                        @else
                                                            {{-- Kept visible rather than hidden: the pair reads as one
                                                                 step, and an item that appears only after the upload
                                                                 makes the menu change shape under the officer. --}}
                                                            <span class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed">
                                                                <i data-lucide="image" class="h-4 w-4 shrink-0 text-slate-200"></i>
                                                                <span class="flex-1 leading-tight italic">View (nothing uploaded)</span>
                                                            </span>
                                                        @endif

                                                        @if($pending > 0)
                                                            <div class="border-t border-slate-100 my-1"></div>

                                                            @if($batchDoc)
                                                                @canDo('Letter of Grant (RofO)', 'approve')
                                <button type="button" @click="open = false"
                                                                    onclick='approveWholeBatch(@json($b->rofo_batch_id))'
                                                                    class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-bold text-green-600 hover:bg-green-50 transition">
                                                                    <i data-lucide="check-circle" class="h-4 w-4 shrink-0"></i>
                                                                    <span class="flex-1 leading-tight">Approve all (<span data-approve-all-count="{{ $b->rofo_batch_id }}">{{ $pending }}</span>)</span>
                                                                </button>
                                @endcanDo
                                                            @else
                                                                {{-- Approval is what turns these children into records an
                                                                     officer can open, and what they open is the mother's
                                                                     letter — so it cannot come first. Shown disabled with
                                                                     the reason rather than hidden: an absent item reads as
                                                                     a bug, and the officer would go looking for it. The
                                                                     server refuses this too (motherRecommendationGate),
                                                                     because the same approval runs from the main list. --}}
                                                                <span title="Upload the mother recommendation first — these children inherit it"
                                                                    class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed">
                                                                    <i data-lucide="check-circle" class="h-4 w-4 shrink-0 text-slate-200"></i>
                                                                    <span class="flex-1 leading-tight">
                                                                        Approve all (<span data-approve-all-count="{{ $b->rofo_batch_id }}">{{ $pending }}</span>)
                                                                        <span class="block text-[10px] font-medium not-italic text-amber-600">Upload the recommendation first</span>
                                                                    </span>
                                                                </span>
                                                            @endif
                                                        @endif
                                                    @elseif($pending > 0)
                                                        @canDo('Letter of Grant (RofO)', 'approve')
                            <button type="button" @click="open = false"
                                                            onclick='approveWholeBatch(@json($b->rofo_batch_id))'
                                                            class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-bold text-green-600 hover:bg-green-50 transition">
                                                            <i data-lucide="check-circle" class="h-4 w-4 shrink-0"></i>
                                                            <span class="flex-1 leading-tight">Approve all (<span data-approve-all-count="{{ $b->rofo_batch_id }}">{{ $pending }}</span>)</span>
                                                        </button>
                            @endcanDo
                                                        <span class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed">
                                                            <i data-lucide="printer" class="h-4 w-4 shrink-0 text-slate-200"></i>
                                                            <span class="flex-1 leading-tight italic">Print all (locked)</span>
                                                        </span>
                                                    @else
                                                        {{-- Proofs of the whole batch, in front of the official run.
                                                             A plain link, not the White Copy card: these documents
                                                             print no DATE OF ISSUE, so the card would have nothing
                                                             to ask for. --}}
                                                        <a href="{{ route('land-recommendations.batch-white-copy', $b->rofo_batch_id) }}" target="_blank"
                                                            class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100 transition">
                                                            <i data-lucide="file-search" class="h-4 w-4 shrink-0"></i>
                                                            <span class="flex-1 leading-tight">White copy all ({{ $total }})</span>
                                                        </a>
                                                        {{-- A button, not the link it used to be: the official run
                                                             stands behind the same proofread question a single
                                                             record does, and a plain href cannot ask one. --}}
                                                        <button type="button" @click="open = false"
                                                            onclick="recBatchPrintGate(@js(route('land-recommendations.batch-print', $b->rofo_batch_id)), @js(route('land-recommendations.batch-white-copy', $b->rofo_batch_id)), @js(($b->mother_file_no ?: $b->rofo_batch_id)), {{ (int) $total }})"
                                                            class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-bold text-violet-700 hover:bg-violet-50 transition">
                                                            <i data-lucide="printer" class="h-4 w-4 shrink-0"></i>
                                                            <span class="flex-1 leading-tight">Print all ({{ $total }})</span>
                                                        </button>
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
                        @php
                            // Names the tab being listed. Without a case of its own a new
                            // tab falls through to "Not Printed", which is exactly what the
                            // Mother Recommendations tab did.
                            $tabLabel = match ($tab) {
                                'oss'     => 'Land One Stop Shop',
                                'printed' => 'Printed',
                                'extant'  => 'Mother Recommendations',
                                'batches' => 'Batch Recommendations',
                                default   => 'Not Printed',
                            };
                        @endphp
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ $tabLabel }}</span>
                    </h3>
                    @if(empty($isOssView))
                    @canDo('Letter of Grant (RofO)', 'approve')
                    <div id="batch-toolbar" class="hidden items-center gap-3">
                        <span id="batch-count-label" class="text-xs font-bold text-slate-500"></span>
                        <button type="button" onclick="batchApprove()" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 transition shadow-sm">
                            <i data-lucide="check-circle" class="h-4 w-4"></i> Batch Approve
                        </button>
                    </div>
                    @endcanDo
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
                                {{-- The OSS tab inside Land is intentionally read-only.  The
                                     standalone OSS page still has its own three-item menu. --}}
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
                            @php
                                // Use Subdivision Template: this file prints as a child of a subdivision
                                // whose mother was never indexed, so it joins no batch. It inherits the
                                // mother's letter exactly as a real batch child does -- the only difference
                                // is where the document hangs, which for a batch-less record is a key built
                                // from its own id. See LandRecommendationBatchDocument.
                                //
                                // Declared here, at the head of the row, rather than down in the actions
                                // cell: the EXTANT badge in the File Number cell is drawn long before the
                                // menu is, and both have to read the same answer.
                                $subTpl       = (bool) $rec->use_subdivision_template;
                                // Several letters, not one: a merged file inherits a recommendation from
                                // each of the files it was merged from. $subTplDoc stays as the FIRST of
                                // them so every existing test for "is there a letter" still reads true.
                                $subTplDocs   = $subTpl ? collect(($subdivisionTemplateDocs ?? collect())[$rec->id] ?? []) : collect();
                                $subTplDoc    = $subTplDocs->first();
                                $subTplKey    = $subTpl ? \App\Models\LandRecommendationBatchDocument::subdivisionTemplateKey((int) $rec->id) : null;
                                $subTplMother = trim((string) $rec->old_file_number);
                            @endphp

                            <tr class="hover:bg-slate-50/50 transition row-item"
                                data-id="{{ $rec->id }}" data-status="{{ $rec->status }}"
                                data-file-number="{{ $rec->file_number }}"
                                {{-- Marks a record still waiting for its already-approved letter, so a
                                     save that arrives with ?upload_letter=<id> can open the upload on it. --}}
                                @if($rec->is_existing_recommendation) data-approved-letter-pending="{{ $rec->id }}" @endif>
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
                                    {{-- Came through OSS by way of the OP-holder Match: the file keeps the
                                         recommendation it was granted on paper, so nothing is generated or
                                         printed for it. The badge is what says so on the list — the record
                                         is an ordinary Land row in every other respect, deliberately, so
                                         that it stays on the page whose actions can work it. Amber while
                                         the letter is still missing, because approval is held until it. --}}
                                    @if($rec->is_existing_recommendation || $subTpl)
                                        @php
                                            // Two routes to the same fact: this file's recommendation exists as a
                                            // document rather than as something KLAES prints. The OP-holder Match
                                            // keeps the file's OWN approved letter; Use Subdivision Template makes
                                            // it a subdivision child, which inherits the MOTHER's. Either way the
                                            // badge says whether that document is in hand yet.
                                            $hasLetter = $subTpl
                                                ? (bool) $subTplDoc
                                                : isset(($approvedLetters ?? collect())[$rec->id]);
                                        @endphp
                                        <span class="inline-flex items-center gap-1 mt-1.5 px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide {{ $hasLetter ? 'bg-violet-100 text-violet-800' : 'bg-amber-100 text-amber-800' }}"
                                              style="font-family: ui-sans-serif, system-ui, sans-serif;"
                                              title="{{ $subTpl
                                                    ? ($hasLetter
                                                        ? 'This file prints as a subdivision child. It inherits the recommendation granted to ' . ($subTplMother !== '' ? $subTplMother : 'its mother file') . ' — the uploaded scan is its letter, and none is printed from KLAES.'
                                                        : 'This file prints as a subdivision child. Upload the mother recommendation it inherits.')
                                                    : ($hasLetter
                                                        ? 'This file\'s Occupancy Permit was matched. It keeps the recommendation approved on paper — the uploaded scan is its letter, and none is printed from KLAES.'
                                                        : 'This file\'s Occupancy Permit was matched. Upload the already-approved recommendation before it can be approved.') }}">
                                            <i data-lucide="{{ $hasLetter ? 'file-check-2' : 'upload' }}" class="h-3 w-3"></i>
                                            {{ $hasLetter ? 'MOTHER RECOMMENDATION' : 'MOTHER RECOMMENDATION PENDING' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap uppercase font-bold text-blue-900">
                                    <div>{{ $rec->applicant_name }}</div>
                                    @if(trim((string) $rec->applicant_phone) !== '')
                                        <button type="button" data-applicant-phone-action
                                                data-phone="{{ $rec->applicant_phone }}"
                                                data-applicant-name="{{ $rec->applicant_name }}"
                                                data-file-number="{{ $rec->file_number }}"
                                                class="mt-1 inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold normal-case text-emerald-700 transition hover:border-emerald-600 hover:bg-emerald-600 hover:text-white">
                                            <i data-lucide="phone" class="h-3 w-3"></i><span>{{ $rec->applicant_phone }}</span>
                                        </button>
                                    @endif
                                </td>
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
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">
                                    @if($rec->creator)
                                        <span class="upc-trigger" data-user-card data-user-id="{{ $rec->creator->id }}"
                                            title="{{ __('View profile') }}">{{ $rec->creator->name }}</span>
                                    @else
                                        System
                                    @endif
                                </td>
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
                                                    // Anchored by its RIGHT edge to the button, so a
                                                    // label wider than w-56 grows the menu leftwards
                                                    // into the page instead of off its edge.
                                                    this.menuStyle = {
                                                        position: 'fixed',
                                                        top: (rect.bottom + 4) + 'px',
                                                        right: Math.max(8, window.innerWidth - rect.right) + 'px',
                                                        minWidth: '14rem',
                                                        zIndex: 99999
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

                                            {{-- Teleported to <body>, like the batch and OSS menus: left inside
                                                 the scrolling table with its sticky column, the menu is clipped
                                                 at the table's edge and its labels are cut off. --}}
                                            <template x-teleport="body">
                                            <div x-show="open"
                                                 x-transition:enter="transition ease-out duration-100"
                                                 x-transition:enter-start="opacity-0 scale-95"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 x-transition:leave="transition ease-in duration-75"
                                                 x-transition:leave-start="opacity-100 scale-100"
                                                 x-transition:leave-end="opacity-0 scale-95"
                                                 :style="menuStyle"
                                                 class="whitespace-nowrap text-left rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                                 style="display: none;">
                                                <div class="py-1">
                                                                                                        @canDo('Letter of Grant (RofO)', 'edit')
<!-- Edit Action -->
                                                    <a href="{{ route('land-recommendations.edit', $rec->id) }}" class="flex items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                        <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                    </a>
                                                    @endcanDo

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    @if($subTpl)
                                                        {{-- One entry per letter already held. A file merged from several parents
                                                             inherits one from each, so these stack; Replace re-scans that one
                                                             letter, and the Upload entry below adds the next. --}}
                                                        @foreach($subTplDocs as $i => $doc)
                                                        {{-- Opens the preview card, the same one the batch menu and the
                                                             approved-recommendation entry open: Replace and Reset sit on the
                                                             card itself, so the menu needs no separate entry for either. --}}
                                                        <button type="button"
                                                                onclick="viewSubdivisionMother(@js($doc->rofo_batch_id), @js($rec->file_number), @js($subTplMother), @js($doc->summary()), @js($doc->mime_type), {{ $i + 1 }}, {{ $subTplDocs->count() }})"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 transition gap-2 font-bold">
                                                            <i data-lucide="file-check-2" class="h-4 w-4"></i>
                                                            <span class="flex-1 leading-tight">
                                                                View mother recommendation{{ $subTplDocs->count() > 1 ? ' ' . ($i + 1) : '' }}
                                                                <span class="block text-[10px] font-medium not-italic text-slate-500">{{ $doc->original_name }}</span>
                                                            </span>
                                                        </button>
                                                        @endforeach

                                                        <button type="button"
                                                                onclick="uploadMotherRecommendation(@js($subTplKey), @js($subTplMother), 1, false, {{ $subTplDocs->count() ? 'true' : 'false' }})"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm {{ $subTplDocs->count() ? 'text-slate-700 hover:bg-slate-50' : 'text-violet-700 hover:bg-violet-50' }} transition gap-2 font-bold">
                                                            <i data-lucide="upload" class="h-4 w-4"></i>
                                                            {{ $subTplDocs->count() ? 'Upload another mother recommendation' : 'Upload mother recommendation' }}
                                                        </button>

                                                        <div class="border-t border-slate-100 my-1"></div>
                                                    @endif

                                                    @php
                                                        // This record stands for a recommendation approved on
                                                        // paper (the OP-holder Match flow): nothing was generated
                                                        // for it, so the scan of that letter is what it points at.
                                                        $needsLetter  = (bool) $rec->is_existing_recommendation;
                                                        $letter       = ($approvedLetters ?? collect())[$rec->id] ?? null;
                                                        $letterMissing = $needsLetter && !$letter;
                                                    @endphp

                                                    @if($needsLetter)
                                                        <button type="button"
                                                                onclick="uploadApprovedRecommendation({{ (int) $rec->id }}, @js($rec->file_number), {{ $letter ? 'true' : 'false' }})"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm {{ $letterMissing ? 'text-amber-700 hover:bg-amber-50' : 'text-slate-700 hover:bg-slate-50' }} transition gap-2 font-bold">
                                                            <i data-lucide="upload" class="h-4 w-4"></i>
                                                            {{ $letter ? 'Replace approved recommendation' : 'Upload approved recommendation' }}
                                                        </button>

                                                        @if($letter)
                                                            {{-- The same preview card the batch uses. This letter has no
                                                                 Reset: it is what the record stands for, and a record
                                                                 with no letter behind it cannot be approved at all —
                                                                 Replace is the whole of the correction here. --}}
                                                            <button type="button"
                                                                    onclick="viewApprovedRecommendation({{ (int) $rec->id }}, @js($rec->file_number), @js($letter->summary()), @js($letter->mime_type))"
                                                               class="flex w-full items-center px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                                <i data-lucide="file-check-2" class="h-4 w-4"></i> View approved recommendation
                                                            </button>
                                                        @endif

                                                        <div class="border-t border-slate-100 my-1"></div>
                                                    @endif

                                                    <!-- Approval Action -->
                                                    @if($rec->status === \App\Models\LandRecommendation::STATUS_PENDING && $subTpl && !$subTplDoc)
                                                        {{-- Same hold for a Use Subdivision Template record: it inherits the
                                                             mother's letter, so approving before that scan is up approves a
                                                             recommendation with nothing behind it. motherRecommendationGate
                                                             refuses it server-side as well. --}}
                                                        <span title="Upload the mother recommendation this file inherits first."
                                                              class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="check-circle" class="h-4 w-4 text-slate-200"></i>
                                                            <span class="flex-1 leading-tight">
                                                                Approve
                                                                <span class="block text-[10px] font-medium not-italic text-violet-600">Upload the mother recommendation first</span>
                                                            </span>
                                                        </span>
                                                    @elseif($rec->status === \App\Models\LandRecommendation::STATUS_PENDING && $letterMissing)
                                                        {{-- Held shut on purpose: approving now would approve a
                                                             recommendation with nothing behind it. The server
                                                             refuses this too — this only saves the round trip. --}}
                                                        <span title="Upload the already-approved recommendation for this file first."
                                                              class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="check-circle" class="h-4 w-4 text-slate-200"></i>
                                                            <span class="flex-1 leading-tight">
                                                                Approve
                                                                <span class="block text-[10px] font-medium not-italic text-amber-600">Upload the approved letter first</span>
                                                            </span>
                                                        </span>
                                                    @elseif($rec->status === \App\Models\LandRecommendation::STATUS_PENDING)
                                                        {{-- Approving is its own permission, separate from editing the
                                                             record. Without it the item is shown inert rather than
                                                             hidden: the officer still needs to see that the file is
                                                             waiting on an approval, just not be able to give it. --}}
                                                        @canDo('Letter of Grant (RofO)', 'approve')
                                                            <button type="button" onclick="approveRecord('{{ $rec->id }}', '{{ $rec->file_number }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-green-600 hover:bg-green-50 transition gap-2 font-bold">
                                                                <i data-lucide="check-circle" class="h-4 w-4"></i> Approve
                                                            </button>
                                                        @else
                                                            <span title="{{ __('You do not have approval rights for this module.') }}"
                                                                  class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                                <i data-lucide="check-circle" class="h-4 w-4 text-slate-200"></i>
                                                                <span class="flex-1 leading-tight">
                                                                    Approve
                                                                    <span class="block text-[10px] font-medium not-italic text-slate-400">{{ __('Awaiting approval — not yours to give') }}</span>
                                                                </span>
                                                            </span>
                                                        @endcanDo
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="check-circle" class="h-4 w-4 text-slate-200"></i> Approved
                                                        </span>
                                                    @endif

                                                    <div class="border-t border-slate-100 my-1"></div>

                                                    <!-- Batch Print Logic -->
                                                    @if($subTpl)
                                                        {{-- A subdivision's children inherit the mother's grant rather than
                                                             earning letters of their own, so nothing is printed for one: the
                                                             scanned mother recommendation IS its letter. This is the same swap
                                                             renderBatchChildren() makes for a real batch child (childAction) --
                                                             View once the scan is up, and the reason why while it is not.
                                                             The RofO is unaffected: it is generated and printed from the RofO
                                                             table as usual once this record is approved. --}}
                                                        @if($subTplDoc)
                                                        <button type="button"
                                                                onclick="viewSubdivisionMother(@js($subTplDoc->rofo_batch_id), @js($rec->file_number), @js($subTplMother), @js($subTplDoc->summary()), @js($subTplDoc->mime_type), 1, {{ $subTplDocs->count() }})"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 transition gap-2 font-bold">
                                                            <i data-lucide="file-check-2" class="h-4 w-4"></i>
                                                            <span class="flex-1 leading-tight">
                                                                View recommendation
                                                                <span class="block text-[10px] font-medium text-slate-500">Inherited from {{ $subTplMother !== '' ? $subTplMother : 'the mother file' }}</span>
                                                            </span>
                                                        </button>
                                                        @else
                                                        <span title="This file prints as a subdivision child, so it inherits the mother's recommendation instead of printing one of its own. Upload that scanned letter above."
                                                              class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i>
                                                            <span class="flex-1 leading-tight">
                                                                Print
                                                                <span class="block text-[10px] font-medium not-italic text-violet-600">Upload the mother recommendation first</span>
                                                            </span>
                                                        </span>
                                                        @endif
                                                    @elseif($needsLetter)
                                                        {{-- Nothing is generated or printed for a file whose OP was
                                                             matched: it keeps the recommendation it was granted on
                                                             paper, and the uploaded scan IS that letter. Printing
                                                             would mint a serial and put a second, KLAES-issued
                                                             recommendation on a file that already has one, so the
                                                             two print entries are replaced by the one thing there is
                                                             to do with the document — read it. The server refuses
                                                             the print route as well; this only saves the trip.
                                                             The RofO is unaffected — it is generated and printed
                                                             from the RofO table as usual, once this is approved. --}}
                                                        <span title="This file keeps the recommendation it was already granted on paper — the uploaded scan is its letter. Nothing is printed from here; the RofO still prints from the RofO table once this record is approved."
                                                              class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i>
                                                            <span class="flex-1 leading-tight">
                                                                Print
                                                                <span class="block text-[10px] font-medium text-violet-600">
                                                                    {{ $letter ? 'Not printed — read it from "View approved recommendation" above' : 'Not printed — this file keeps its extant letter' }}
                                                                </span>
                                                            </span>
                                                        </span>
                                                    @elseif($rec->status === \App\Models\LandRecommendation::STATUS_APPROVED)
                                                        @php
                                                            // The proofing stage in front of the official print.
                                                            // whiteCopyOwnsDate is deliberately NOT set: this
                                                            // document prints no DATE OF ISSUE — only blank
                                                            // hand-signed date lines — so there is no date for
                                                            // the proof to own, and the Print Manager keeps the
                                                            // date panel it has always had.
                                                            $pmOptions = [
                                                                'recordId'     => (int) $rec->id,
                                                                'issueDate'    => optional($rec->application_date)->format('Y-m-d') ?? '',
                                                                'whiteCopyUrl' => route('land-recommendations.white-copy', $rec->id),
                                                                'whiteCopyDownload' => true,
                                                            ];
                                                        @endphp

                                                        @php
                                                            // Once it is on paper the proofing stage is over — the
                                                            // copy that went out is the document now. Same test the
                                                            // Date Printed column uses, so the menu and the column
                                                            // can never disagree.
                                                            $wcPrinted = isset($printDates[strtoupper(trim((string) $rec->file_number))]);

                                                            // The proof has been run off, so the two entries hand
                                                            // off: the White Copy closes and the Print Manager opens.
                                                            $wcDone = isset($whiteCopyDone[strtoupper(trim((string) $rec->file_number))]);
                                                        @endphp

                                                        @if($wcPrinted || $wcDone)
                                                        <span class="flex w-full items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 font-bold"
                                                              title="{{ $wcPrinted
                                                                    ? 'This recommendation has already been printed — the white copy is a pre-print proof.'
                                                                    : 'White copy already run off — print the recommendation next.' }}">
                                                            <i data-lucide="file-search" class="h-4 w-4 text-slate-200"></i> Generate White Copy
                                                        </span>
                                                        @else
                                                        <button type="button"
                                                                onclick="openWhiteCopyModal(@js((int) $rec->id), @js($rec->file_number), '', @js(route('land-recommendations.white-copy', $rec->id)), { download: true })"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition gap-2 font-bold">
                                                            <i data-lucide="file-search" class="h-4 w-4"></i> Generate White Copy
                                                        </button>
                                                        @endif

                                                        @if($wcDone || $wcPrinted)
                                                        <button type="button"
                                                                onclick="WhiteCopy.openPrintManager(@js($rec->file_number), 'Recommendation For Grant', @js(route('land-recommendations.print', $rec->id)), @js($pmOptions))"
                                                                class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                            <i data-lucide="printer" class="h-4 w-4"></i>  Print Manager
                                                        </button>
                                                        @else
                                                        <span class="flex w-full items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 font-bold"
                                                              title="Print and read the white copy first.">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i>  Print Manager
                                                        </span>
                                                        @endif
                                                    @else
                                                        <span class="flex items-center px-4 py-2.5 text-sm text-slate-300 cursor-not-allowed gap-2 italic">
                                                            <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i> Print (Pending Approval)
                                                        </span>
                                                    @endif

                                                    {{-- Master Delete: the recommendation AND the RofO it became,
                                                         out of every table it reached. Supper Admin only — the
                                                         server enforces the same rule, so this only hides it. --}}
                                                    @if(auth()->user()?->assign_role === 'Supper Admin')
                                                    <div class="border-t border-slate-100 my-1"></div>
                                                    <button type="button"
                                                        onclick="masterDeleteLandRecommendation({{ $rec->id }}, @js($rec->file_number))"
                                                        class="flex w-full items-center px-4 py-2.5 text-sm text-red-700 hover:bg-red-50 transition gap-2 font-bold">
                                                        <i data-lucide="shield-alert" class="h-4 w-4"></i> Master Delete
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                            </template>
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
                                                        @php
                                                            $ossCardRecord = [
                                                                'id' => (int) $rec->id,
                                                                'applicant_name' => (string) $rec->applicant_name,
                                                                'file_number' => (string) $rec->file_number,
                                                                'purpose' => (string) $rec->landuse_purpose,
                                                                'location' => (string) $rec->display_location,
                                                                'plot_no' => (string) $rec->plot_number,
                                                                'plan_no' => (string) $rec->layout_plan_no,
                                                                'application_date' => optional($rec->application_date)->format('Y-m-d'),
                                                                'applicant_address' => (string) ($rec->resolved_applicant_address ?? $rec->applicant_address ?? ''),
                                                                // Pass the actual saved OSS recommendation fields to
                                                                // the card. Do not look them up by file number: a
                                                                // legacy Land recommendation can share that number.
                                                                'term' => (string) $rec->term,
                                                                'dev_value' => (string) $rec->development_value,
                                                                'completion_time' => (string) $rec->development_period,
                                                                'ground_rent' => (string) $rec->ground_rent,
                                                                'dev_charge' => (string) $rec->development_charge,
                                                                'survey_charges' => (string) $rec->survey_fees,
                                                                'director_reasons' => (string) $rec->recommendation,
                                                                'printed' => !empty($printedAt),
                                                            ];
                                                        @endphp
                                                        <button type="button"
                                                           @click="open = false"
                                                           data-oss-recommendation='@json($ossCardRecord)'
                                                           onclick="openOssRecommendationModal(JSON.parse(this.dataset.ossRecommendation))"
                                                           class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2 font-bold whitespace-nowrap">
                                                            <i data-lucide="edit-3" class="h-4 w-4 flex-shrink-0"></i>
                                                            Edit OSS Recommendation
                                                        </button>
                                                        <div class="border-t border-slate-100 my-1"></div>
                                                        @php
                                                            $ossPmOptions = [
                                                                'recordId'     => (int) $rec->id,
                                                                'issueDate'    => optional($rec->application_date)->format('Y-m-d') ?? '',
                                                                'whiteCopyUrl' => route('land-recommendations.white-copy', $rec->id),
                                                                'whiteCopyDownload' => true,
                                                            ];
                                                        @endphp

                                                        <button type="button"
                                                                @click="open = false"
                                                                onclick="openWhiteCopyModal(@js((int) $rec->id), @js($rec->file_number), '', @js(route('land-recommendations.white-copy', $rec->id)), { download: true })"
                                                            class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-100 transition gap-2 font-bold whitespace-nowrap">
                                                        <i data-lucide="file-search" class="h-4 w-4 flex-shrink-0"></i>
                                                        Generate White Copy
                                                    </button>

                                                        <button type="button"
                                                                @click="open = false"
                                                                onclick="WhiteCopy.openPrintManager(@js($rec->file_number), 'OSS Recommendation For Grant', @js(route('land-recommendations.print', $rec->id)), @js($ossPmOptions))"
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

{{-- ── The recommendation preview card ──────────────────────────────────────
     The scanned letter used to open as a bare file in a new tab, which is the
     browser's own viewer: no idea which file it belongs to, no way back, and a
     PDF handed to whatever the machine has registered. Here it is shown in the
     page it was opened from, named, with the actions that belong to it.

     One card serves both scans, because they are the same thing seen from two
     places: the mother's letter, which the whole batch inherits, and the
     already-approved letter a single OP-matched record stands for. --}}
<div id="recDocModal" class="fixed inset-0 z-[100000] hidden items-center justify-center bg-slate-900/70 p-4"
     role="dialog" aria-modal="true" aria-labelledby="recDocModalTitle">
    <div class="w-full max-w-4xl max-h-[92vh] flex flex-col rounded-2xl bg-white shadow-2xl overflow-hidden">

        <div class="px-6 py-4 border-b border-slate-200 flex items-start gap-3">
            <div class="h-10 w-10 shrink-0 rounded-xl bg-violet-100 flex items-center justify-center">
                <i data-lucide="file-check-2" class="h-5 w-5 text-violet-700"></i>
            </div>
            <div class="min-w-0 flex-1">
                <h3 id="recDocModalTitle" class="text-sm font-black text-slate-900 truncate">Recommendation</h3>
                <p id="recDocModalSub" class="text-[11px] font-semibold text-slate-500 truncate"></p>
            </div>
            <span id="recDocModalMeta" class="hidden shrink-0 px-2.5 py-1 rounded-full bg-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-500"></span>
            <button type="button" onclick="closeRecDocModal()" aria-label="Close"
                    class="shrink-0 p-2 rounded-lg text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        {{-- The scan itself. An image is shown directly; a PDF gets an iframe,
             because a multi-page letter off a departmental scanner arrives as one
             and an <img> would show nothing at all. --}}
        <div id="recDocModalBody" class="flex-1 min-h-[240px] overflow-auto bg-slate-100 flex items-center justify-center p-4">
            <p id="recDocModalLoading" class="text-xs font-semibold text-slate-400">Loading the scan…</p>

            {{-- Click toggles between fitted and full size: a phone photograph of an
                 A4 letter is unreadable scaled into a card, and the reason to open
                 this at all is to read what it says. --}}
            <img id="recDocModalImage" alt="" class="hidden max-w-full object-contain cursor-zoom-in rounded-lg shadow-sm"
                 style="max-height: 68vh;">

            <iframe id="recDocModalFrame" title="Scanned recommendation"
                    class="hidden w-full rounded-lg bg-white" style="height: 68vh;"></iframe>

            <div id="recDocModalError" class="hidden text-center px-6">
                <i data-lucide="image-off" class="h-7 w-7 mx-auto mb-2 text-slate-300"></i>
                <p class="text-xs font-bold text-slate-600">This scan could not be displayed.</p>
                <p class="text-[11px] text-slate-500 mt-1">The file may have been moved or removed from the EDMS folder.</p>
                <a id="recDocModalErrorLink" href="#" target="_blank"
                   class="inline-block mt-3 text-[11px] font-black text-violet-700 hover:underline">Try opening it directly</a>
            </div>
        </div>

        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center gap-2 flex-wrap">
            <a id="recDocModalOpen" href="#" target="_blank"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                <i data-lucide="external-link" class="h-3.5 w-3.5"></i> Open in new tab
            </a>
            <button type="button" id="recDocModalReplace"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold text-violet-700 hover:bg-violet-100 transition">
                <i data-lucide="upload" class="h-3.5 w-3.5"></i> Replace
            </button>
            {{-- Destructive, so it is last and it is the only red thing here. --}}
            <button type="button" id="recDocModalReset"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-xs font-bold text-rose-600 hover:bg-rose-50 transition">
                <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Reset upload
            </button>
            <button type="button" onclick="closeRecDocModal()"
                    class="ml-auto px-4 py-2 rounded-lg bg-slate-800 text-white text-xs font-bold hover:bg-slate-900 transition">
                Close
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/master-delete.js') }}"></script>
<script>
    /**
     * Master Delete for a land recommendation — the record AND the RofO it became.
     * MasterDelete.confirm() owns the two-step confirmation; the server re-checks
     * both the typed file number and the Supper Admin role.
     */
    function masterDeleteLandRecommendation(id, fileNumber) {
        MasterDelete.confirm({
            url: '/land-recommendations/' + id + '/master-destroy',
            reference: fileNumber,
            title: 'Master Delete Recommendation',
            lead: 'This permanently deletes the recommendation for <b>' + fileNumber + '</b> and everything it produced. It cannot be undone.',
            targets: [
                'The recommendation record',
                'The RofO it became — status, dates and date of issue',
                'Its PRA transaction',
                'Its security paper code (released, or retired if already printed)',
                'Its print history, white copies included'
            ],
            keeps: 'The file number, its indexing and any deed registered against it are untouched.'
        });
    }
</script>
<script>
    // ── Batches tab ────────────────────────────────────────────────────────
    // One row per batch. Expanding fetches EVERY child from the server rather
    // than revealing the rows that happen to share this page — which is the whole
    // reason this tab exists, since on the main list a 100-child batch expands to
    // show only the 20 the pagination left behind.
    // ── The proofread gate in front of a batch run ─────────────────────────
    // A batch is where a mistake is most expensive — one wrong field repeated
    // across every record in it — so the whole set is read on ordinary paper before
    // any of it is printed officially. Answering "no" opens white copies of the
    // batch, which is the thing that was actually needed.
    //
    // Both tabs open in a new tab rather than through window.open() after the
    // dialog: a plain assignment to a form-less link this far from the click is
    // what a pop-up blocker stops, so each is a real navigation on its own anchor.
    function recBatchPrintGate(printUrl, whiteCopyUrl, label, count) {
        WhiteCopy.confirmProofread({
            subject: count + ' ' + (count === 1 ? 'recommendation' : 'recommendations') + ' in ' + label,
            onYes:       function () { WhiteCopy.openTab(printUrl); },
            onWhiteCopy: function () { WhiteCopy.openTab(whiteCopyUrl); }
        });
    }

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

    // ── Approving the children of a batch ───────────────────────────────────
    // A batch is captured in one pass but it is not always approved in one: the
    // children come back from vetting a few at a time, and "Approve all" alone
    // meant the whole batch waited on the slowest file. The expander below
    // therefore approves one child, a chosen few, or all of them — through the
    // same two endpoints the main list uses, under the same gates, so approval
    // means the same thing however it is reached.

    // Child-level approval is OFF for now: a subdivision batch is approved as a
    // whole, from the mother's menu, and that is the only Approve on this page.
    //
    // Everything below stays behind this one flag rather than being deleted —
    // flipping it back to true returns the per-row Approve AND the tick-some
    // toolbar together, because they are one capability reached two ways and
    // leaving the checkboxes behind would put back exactly what was hidden. The
    // server keeps both endpoints and both gates either way, so nothing here is
    // load-bearing for correctness.
    var CHILD_APPROVAL_ENABLED = false;

    // Approving is a permission of its own, separate from editing the record. These child
    // controls are rendered in JavaScript, so the answer has to travel here rather than
    // through a blade guard. ANDed into CHILD_APPROVAL_ENABLED below so that flipping that
    // flag back on cannot hand the capability to someone who was never granted it.
    var CAN_APPROVE = @json(auth()->check() && auth()->user()->canDo('Letter of Grant (RofO)', 'approve'));

    // Why this child cannot be approved yet, or null when it can. Mirrors the two
    // server-side gates: the mother's letter belongs to the batch and answers for
    // every child at once (motherRecommendationGate), while an already-approved
    // letter belongs to one child and only that child waits on it
    // (approvedLetterGate). The server still refuses either way — this only saves
    // the officer a refused round trip and says which of the two it is.
    function childApprovalBlock(data, c) {
        if (!data.mother_satisfied) return 'Upload the mother recommendation first — this child inherits it';
        if (c.needs_letter && !c.letter_uploaded) return 'Upload this file’s own approved recommendation first';
        return null;
    }

    function childIsApproved(c) {
        return String(c.status || '').toLowerCase() === 'approved';
    }

    function recPostJson(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: body ? JSON.stringify(body) : null
        })
        // The body carries the refusal, so it is read on failure too: a blocked
        // approval answers 422 with a reason an officer can act on, and reporting
        // the HTTP status instead would throw that away.
        .then(function (r) {
            return r.json().catch(function () { return {}; })
                .then(function (d) { return { ok: r.ok, data: d }; });
        });
    }

    // The batch row above the expander carries its own Approved / RofO counters and
    // an "Approve all (N)" label. Approving one child changes all three, so they are
    // repainted from the same data the expander was just drawn from — the row and
    // the children under it can never drift apart.
    function paintBatchRowCounters(data) {
        // Matched by walking the attributes rather than through a selector: a batch
        // ref is not guaranteed to be a valid CSS identifier.
        function cellFor(attr, batchId) {
            var all = document.querySelectorAll('[' + attr + ']');
            for (var i = 0; i < all.length; i++) {
                if (all[i].getAttribute(attr) === batchId) return all[i];
            }
            return null;
        }

        function pill(ok, text) {
            return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold '
                + (ok ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700') + '">' + text + '</span>';
        }

        var total     = data.count;
        var approved  = data.children.filter(childIsApproved).length;
        var generated = data.children.filter(function (c) {
            return String(c.rofo_status || '').toLowerCase() === 'generated';
        }).length;
        var pending   = total - approved;

        var approvedCell = cellFor('data-batch-approved', data.batch_id);
        if (approvedCell) {
            approvedCell.innerHTML = pending === 0
                ? pill(true, 'All ' + total)
                : pill(false, approved + ' of ' + total);
        }

        var generatedCell = cellFor('data-batch-generated', data.batch_id);
        if (generatedCell) {
            generatedCell.innerHTML = generated >= total
                ? pill(true, 'All ' + total)
                : (generated > 0
                    ? pill(false, generated + ' of ' + total)
                    : '<span class="text-[10px] font-bold text-slate-400">&mdash;</span>');
        }

        var countLabel = cellFor('data-approve-all-count', data.batch_id);
        if (countLabel) countLabel.textContent = pending;
    }

    // Re-read the batch after an approval rather than patching the rows from the
    // response: approval also generates the RofO, and one child's generation can
    // fail without failing the approval (generateRofosForBatchMembers logs it and
    // carries on). Only the server knows which rows actually came back generated.
    function refreshBatchChildren(cell, batchId) {
        return fetch(CHILDREN_URL + '/' + encodeURIComponent(batchId) + '/children', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (fresh) {
                if (!fresh.success) throw new Error(fresh.message || 'Could not reload the batch.');
                renderBatchChildren(cell, fresh);
                paintBatchRowCounters(fresh);
                return fresh;
            });
    }

    function afterChildApproval(cell, batchId, title, text) {
        return refreshBatchChildren(cell, batchId)
            .then(function (fresh) {
                return Swal.fire({
                    icon: 'success', title: title, text: text,
                    timer: 1800, showConfirmButton: false
                }).then(function () {
                    // The batch menu swaps its whole set of actions the moment
                    // nothing is pending — Approve all gives way to White copy /
                    // Print all — and that menu is rendered server-side. So this
                    // one transition is a reload; every other approval is patched
                    // in place, which is what lets an officer work down the list.
                    if (fresh.pending_count === 0) window.location.reload();
                });
            })
            .catch(function (err) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Approved, but the list did not refresh',
                    text: err.message || 'Reload the page to see the current state.'
                });
            });
    }

    // One child, on its own. The same endpoint the main list's Approve uses, so a
    // child approved here and a single record approved there are the same act.
    function approveOneChild(cell, data, c) {
        var rest = data.mother_file_no
            ? 'The rest of ' + escHtml(data.mother_file_no)
            : 'The rest of the batch';

        Swal.fire({
            icon: 'question',
            title: 'Approve this file on its own?',
            html: '<p class="text-sm text-slate-600"><b>' + escHtml(c.file_number || 'This record')
                    + '</b> will be approved and its RofO generated. ' + rest + ' is left exactly as it is.</p>'
                + '<p class="text-xs text-slate-500 mt-2">It cannot be edited after approval.</p>',
            showCancelButton: true,
            confirmButtonText: 'Approve this file',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            showLoaderOnConfirm: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function () {
                return recPostJson(c.approve_url, null)
                    .then(function (res) {
                        if (!res.ok || !res.data.success) {
                            throw new Error(res.data.message || 'Approval failed.');
                        }
                        return res.data;
                    })
                    .catch(function (err) {
                        Swal.showValidationMessage(err.message || 'Network error during approval.');
                        return false;
                    });
            }
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) return;
            afterChildApproval(cell, data.batch_id, 'Approved',
                (c.file_number || 'The record') + ' approved'
                + (r.value.rofos_generated ? ' and its RofO generated.' : '.'));
        });
    }

    // A chosen few. Reuses batch-approve, which is the same call "Approve all" and
    // the main list's checkboxes make — the only difference is which ids go in it.
    function approveSelectedChildren(cell, data, ids) {
        Swal.fire({
            icon: 'question',
            title: 'Approve ' + ids.length + ' selected file(s)?',
            html: '<p class="text-sm text-slate-600">Only the files you ticked are approved. The others in <b>'
                    + escHtml(data.mother_file_no || data.batch_id) + '</b> stay pending.</p>'
                + '<p class="text-xs text-slate-500 mt-2">They cannot be edited after approval.</p>',
            showCancelButton: true,
            confirmButtonText: 'Approve ' + ids.length + ' file(s)',
            confirmButtonColor: '#059669',
            cancelButtonColor: '#64748b',
            showLoaderOnConfirm: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function () {
                return recPostJson(data.batch_approve_url, { ids: ids })
                    .then(function (res) {
                        if (!res.ok || !res.data.success) {
                            throw new Error(res.data.message || 'Approval failed.');
                        }
                        return res.data;
                    })
                    .catch(function (err) {
                        Swal.showValidationMessage(err.message || 'Network error during approval.');
                        return false;
                    });
            }
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) return;
            var count = (r.value.approved === undefined || r.value.approved === null) ? ids.length : r.value.approved;
            afterChildApproval(cell, data.batch_id, 'Approved',
                count + ' recommendation(s) approved'
                + (r.value.rofos_generated ? ', ' + r.value.rofos_generated + ' RofO(s) generated.' : '.'));
        });
    }

    // The expanded panel under a batch row. Drawn on first expand and re-drawn
    // after every approval, so the rows, the selection bar and the batch counters
    // above it all come from one read of the batch.
    function renderBatchChildren(cell, data) {
        // A subdivision's children inherit the mother's recommendation, so their
        // row offers her scanned letter instead of a Print action: View once it is
        // uploaded, Upload while it is not. One document covers the whole batch, so
        // every row points at the same URL and one upload answers for all of them.
        var doc = data.document;

        function childAction(c) {
            if (!data.is_subdivision) {
                return '<a href="' + c.print_url + '" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-violet-700 hover:bg-violet-50 rounded">Print</a>';
            }

            if (doc) {
                // Opens the preview card, the same one the batch menu opens: a child
                // and its batch show one document, so they must show it one way.
                return '<button type="button" data-view-mother="1" title="' + escHtml(doc.original_name || '') + '"'
                    + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-emerald-700 hover:bg-emerald-50 rounded">View</button>';
            }

            return '<button type="button" onclick="uploadMotherRecommendation(' + JSON.stringify(data.batch_id) + ', '
                + JSON.stringify(data.mother_file_no || '') + ', ' + data.count + ', false)"'
                + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-violet-700 hover:bg-violet-50 rounded">Upload</button>';
        }

        // Approve sits on the row itself, next to Edit, because that is where the
        // officer already is when they decide one file is ready. A child that
        // cannot be approved yet keeps the entry and says why rather than losing
        // it — a missing action reads as a bug and sends people looking for it.
        function approveAction(c) {
            // Nothing at all, not a disabled entry: the row has no approval to
            // offer while this is off, and a greyed-out control invites the
            // question of how to un-grey it. Status still says Pending/Approved.
            if (!CHILD_APPROVAL_ENABLED || !CAN_APPROVE) {
                return '';
            }

            if (childIsApproved(c)) {
                return '<span class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-slate-300 italic cursor-default">Approved</span>';
            }

            var blocked = childApprovalBlock(data, c);
            if (blocked) {
                // A child waiting on its OWN letter is otherwise stranded here: a
                // batched row is kept off the main list, so the upload it needs is
                // not reachable anywhere else. The mother's letter needs no such
                // entry — childAction already offers Upload for that one.
                var ownLetter = c.needs_letter && !c.letter_uploaded && data.mother_satisfied
                    ? '<button type="button" onclick="uploadApprovedRecommendation(' + c.id + ', '
                        + JSON.stringify(c.file_number || '') + ', false)"'
                        + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-amber-700 hover:bg-amber-50 rounded">Upload letter</button>'
                    : '';

                return '<span title="' + escHtml(blocked) + '"'
                    + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-slate-300 cursor-not-allowed">Approve</span>'
                    + ownLetter;
            }

            return '<button type="button" data-approve-child="' + c.id + '"'
                + ' class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-green-700 hover:bg-green-50 rounded">Approve</button>';
        }

        function canSelect(c) {
            if (!CHILD_APPROVAL_ENABLED || !CAN_APPROVE) {
                return false;
            }

            return !childIsApproved(c) && !childApprovalBlock(data, c);
        }

        var selectable = data.children.filter(canSelect).length;

        var rows = data.children.map(function (c) {
            return '<tr class="border-b border-violet-100/70 hover:bg-white/70' + (childIsApproved(c) ? ' bg-emerald-50/40' : '') + '">'
                + (selectable
                    ? '<td class="px-3 py-2.5 text-center">'
                        + (canSelect(c)
                            ? '<input type="checkbox" class="rec-child-chk rounded border-slate-300 text-green-600 focus:ring-green-500 cursor-pointer" value="' + c.id + '">'
                            : '')
                        + '</td>'
                    : '')
                + '<td class="px-4 py-2.5 text-center text-[11px] font-bold text-slate-400">' + c.seq + '</td>'
                + '<td class="px-4 py-2.5 font-mono text-xs font-bold text-slate-900">' + escHtml(c.file_number) + '</td>'
                + '<td class="px-4 py-2.5 text-xs text-slate-700"><div>' + escHtml(c.applicant_name) + '</div>'
                + (window.applicantPhoneActionButtonHtml ? window.applicantPhoneActionButtonHtml(c.applicant_phone, c.applicant_name, c.file_number) : '') + '</td>'
                + '<td class="px-4 py-2.5 text-xs text-slate-600">' + escHtml(c.plot_number) + '</td>'
                + '<td class="px-4 py-2.5 text-xs text-slate-600">' + escHtml(c.location) + '</td>'
                + '<td class="px-4 py-2.5 text-xs text-slate-600">' + escHtml(c.purpose) + '</td>'
                + '<td class="px-4 py-2.5 text-center">' + statusPill(c.status, 'approved', 'Approved', 'Pending') + '</td>'
                + '<td class="px-4 py-2.5 text-center">' + statusPill(c.rofo_status, 'generated', 'Generated', 'Pending') + '</td>'
                + '<td class="px-4 py-2.5 text-right whitespace-nowrap">'
                +   '<a href="' + c.edit_url + '" class="inline-flex items-center gap-1 px-2 py-1 text-[10px] font-bold text-blue-700 hover:bg-blue-50 rounded">Edit</a>'
                +   approveAction(c)
                +   childAction(c)
                + '</td>'
                + '</tr>';
        }).join('');

        cell.innerHTML =
            '<div class="px-6 py-4">'
            + '<div class="flex items-end justify-between gap-3 flex-wrap mb-2">'
            + '<p class="text-[10px] font-black uppercase tracking-widest text-violet-700">'
            +   'All ' + data.count + ' children of this batch'
            +   (data.is_subdivision
                    ? (doc
                        ? '<span class="ml-2 normal-case tracking-normal font-bold text-emerald-700">· mother recommendation uploaded '
                            + escHtml(doc.uploaded_at || '') + ' (' + escHtml(doc.summary || '') + ')</span>'
                        : '<span class="ml-2 normal-case tracking-normal font-bold text-amber-600">· no mother recommendation uploaded yet — approval is held until it is on file</span>')
                    : '')
            +   (selectable
                    ? '<span class="ml-2 normal-case tracking-normal font-medium text-slate-500">· approve one at a time, or tick the files that are ready</span>'
                    : '')
            +   '</p>'
            {{-- Only where there is something to tick. Emitting it regardless left a
                 hidden bar that nothing could ever open, since syncToolbar() shows
                 it on a ticked box and there are no boxes. --}}
            + (selectable
                    ? '<div data-child-toolbar class="hidden items-center gap-3">'
                        + '<span data-child-count class="text-[11px] font-bold text-slate-500"></span>'
                        + '<button type="button" data-approve-selected'
                        +   ' class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 text-white text-[11px] font-bold rounded-lg hover:bg-green-700 transition shadow-sm">'
                        +   'Approve selected</button>'
                        + '</div>'
                    : '')
            + '</div>'
            + '<div class="overflow-x-auto rounded-lg border border-violet-200 bg-white">'
            + '<table class="w-full text-left border-collapse min-w-[880px]">'
            + '<thead><tr class="bg-violet-50 text-[10px] font-black text-violet-800 uppercase tracking-widest">'
            +   (selectable
                    ? '<th class="px-3 py-2.5 text-center w-10">'
                        + '<input type="checkbox" data-child-select-all title="Select every file that can be approved"'
                        + ' class="rounded border-slate-300 text-green-600 focus:ring-green-500 cursor-pointer"></th>'
                    : '')
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

        wireBatchChildren(cell, data);
    }

    // Listeners are attached after every render rather than delegated, because the
    // panel is replaced wholesale on refresh — fresh nodes each time, so nothing
    // doubles up.
    function wireBatchChildren(cell, data) {
        var toolbar    = cell.querySelector('[data-child-toolbar]');
        var countLabel = cell.querySelector('[data-child-count]');
        var selectAll  = cell.querySelector('[data-child-select-all]');
        var selectBtn  = cell.querySelector('[data-approve-selected]');
        var boxes      = Array.prototype.slice.call(cell.querySelectorAll('.rec-child-chk'));

        function selectedIds() {
            return boxes.filter(function (b) { return b.checked; })
                .map(function (b) { return parseInt(b.value, 10); });
        }

        function syncToolbar() {
            var n = selectedIds().length;
            if (toolbar) {
                toolbar.classList.toggle('hidden', n === 0);
                toolbar.classList.toggle('flex', n > 0);
            }
            if (countLabel) countLabel.textContent = n + ' selected';
            if (selectBtn) selectBtn.textContent = 'Approve selected (' + n + ')';
            if (selectAll) {
                selectAll.checked = boxes.length > 0 && n === boxes.length;
                selectAll.indeterminate = n > 0 && n < boxes.length;
            }
        }

        boxes.forEach(function (b) { b.addEventListener('change', syncToolbar); });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                boxes.forEach(function (b) { b.checked = selectAll.checked; });
                syncToolbar();
            });
        }

        cell.querySelectorAll('[data-approve-child]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = parseInt(btn.getAttribute('data-approve-child'), 10);
                var child = data.children.filter(function (c) { return c.id === id; })[0];
                if (child) approveOneChild(cell, data, child);
            });
        });

        if (selectBtn) {
            selectBtn.addEventListener('click', function () {
                var ids = selectedIds();
                if (ids.length) approveSelectedChildren(cell, data, ids);
            });
        }

        // Every child row shows the one document the batch carries, so they all
        // open the same card with the same arguments.
        cell.querySelectorAll('[data-view-mother]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                viewMotherRecommendation(
                    data.batch_id,
                    data.mother_file_no || '',
                    (data.document && data.document.summary) || '',
                    (data.document && data.document.mime_type) || '',
                    data.count
                );
            });
        });

        syncToolbar();
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
                renderBatchChildren(cell, data);
            })
            .catch(function (err) {
                cell.innerHTML = '<div class="px-8 py-6 text-xs text-rose-700">'
                    + escHtml(err.message || 'Network error loading the batch.') + '</div>';
            });
    });

    // Approve every pending child of a batch straight from the Batches tab. The ids
    // are not on the page here (the tab lists batches, not rows), so they are
    // fetched first — the same endpoint the main list's batch row uses.
    // Opening the batch's letter. Reachable from the batch menu and from any child
    // row, because one document answers for the whole batch.
    function viewMotherRecommendation(batchId, motherFileNo, summary, mime, childCount) {
        var base = CHILDREN_URL + '/' + encodeURIComponent(batchId) + '/document';

        openRecDocModal({
            title:    'Mother recommendation',
            subtitle: (motherFileNo ? motherFileNo + ' · ' : '') + batchId
                        + (childCount ? ' · inherited by all ' + childCount + ' children' : ''),
            meta:     summary,
            mime:     mime,
            url:      base,
            resetUrl: base,
            onReplace: function () {
                uploadMotherRecommendation(batchId, motherFileNo || '', childCount || 0, true);
            }
        });
    }

    // One Use Subdivision Template record's inherited letter. Same card as the
    // batch's and as the approved-recommendation one -- it is the same kind of
    // thing: a scanned sheet that stands in for a printed recommendation. The
    // batch version is not reused directly because its subtitle counts children
    // and names the batch id, neither of which means anything for a lone record.
    function viewSubdivisionMother(key, fileNumber, motherFileNo, summary, mime, position, total) {
        var base = CHILDREN_URL + '/' + encodeURIComponent(key) + '/document';

        openRecDocModal({
            title:    total > 1 ? ('Mother recommendation ' + position + ' of ' + total) : 'Mother recommendation',
            subtitle: (fileNumber ? fileNumber + ' · ' : '')
                        + (motherFileNo ? 'inherited from ' + motherFileNo : 'inherited from the mother file'),
            meta:     summary,
            mime:     mime,
            url:      base,
            resetUrl: base,
            onReplace: function () {
                uploadMotherRecommendation(key, motherFileNo || '', 1, true);
            }
        });
    }

    // The single record's own already-approved letter. No Reset: see the menu entry.
    function viewApprovedRecommendation(recordId, fileNumber, summary, mime) {
        openRecDocModal({
            title:    'Approved recommendation',
            subtitle: fileNumber ? fileNumber + ' · granted on paper' : 'Granted on paper',
            meta:     summary,
            mime:     mime,
            url:      '{{ url('land-recommendations') }}/' + encodeURIComponent(recordId) + '/approved-recommendation',
            onReplace: function () {
                uploadApprovedRecommendation(recordId, fileNumber || '', true);
            }
        });
    }

    // ── The recommendation preview card ────────────────────────────────────
    // The scan opened in a new tab before this: the browser's own viewer, with
    // nothing saying which file the sheet belongs to and no way back to the batch.
    // It is a document that belongs to a record, so it is shown inside the page
    // that record is on, with the two actions that follow from looking at it —
    // Replace, when the proof is wrong, and Reset, when it should not be there.
    var recDoc = { resetUrl: '', replace: null, viewUrl: '' };

    function openRecDocModal(opts) {
        opts = opts || {};
        recDoc.resetUrl = opts.resetUrl || '';
        recDoc.replace  = (typeof opts.onReplace === 'function') ? opts.onReplace : null;
        recDoc.viewUrl  = opts.url || '';

        var modal   = document.getElementById('recDocModal');
        var image   = document.getElementById('recDocModalImage');
        var frame   = document.getElementById('recDocModalFrame');
        var loading = document.getElementById('recDocModalLoading');
        var error   = document.getElementById('recDocModalError');
        var meta    = document.getElementById('recDocModalMeta');

        document.getElementById('recDocModalTitle').textContent = opts.title || 'Recommendation';
        document.getElementById('recDocModalSub').textContent   = opts.subtitle || '';
        document.getElementById('recDocModalOpen').href         = recDoc.viewUrl;
        document.getElementById('recDocModalErrorLink').href    = recDoc.viewUrl;

        meta.textContent = opts.meta || '';
        meta.classList.toggle('hidden', !opts.meta);

        // Reset and Replace are only offered when the caller says how to do them —
        // the same card is opened from places that own neither.
        document.getElementById('recDocModalReset').classList.toggle('hidden', !recDoc.resetUrl);
        document.getElementById('recDocModalReplace').classList.toggle('hidden', !recDoc.replace);

        // Every state off, then exactly one on, so re-opening the card never shows
        // the previous document underneath the new one.
        image.classList.add('hidden');
        frame.classList.add('hidden');
        error.classList.add('hidden');
        loading.classList.remove('hidden');
        image.removeAttribute('src');
        frame.removeAttribute('src');
        image.classList.remove('rec-doc-zoomed');
        image.style.maxHeight = '68vh';

        // A cache-buster, so a Replace is not answered with the copy the browser
        // already has under the same URL — this route serves whatever the batch
        // currently points at, and the URL does not change when the file does.
        var src = recDoc.viewUrl + (recDoc.viewUrl.indexOf('?') === -1 ? '?' : '&') + 'v=' + Date.now();

        if (String(opts.mime || '').toLowerCase() === 'application/pdf') {
            frame.src = src;
            frame.classList.remove('hidden');
            loading.classList.add('hidden');
        } else {
            image.onload = function () {
                loading.classList.add('hidden');
                image.classList.remove('hidden');
            };
            image.onerror = function () {
                loading.classList.add('hidden');
                error.classList.remove('hidden');
            };
            image.src = src;
        }

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
        if (window.lucide) window.lucide.createIcons();
    }

    function closeRecDocModal() {
        var modal = document.getElementById('recDocModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        // Dropped rather than left loaded: a 20 MB scan does not need to stay in
        // memory behind a closed card.
        document.getElementById('recDocModalImage').removeAttribute('src');
        document.getElementById('recDocModalFrame').removeAttribute('src');
    }

    (function () {
        var modal = document.getElementById('recDocModal');
        if (!modal) return;

        // The backdrop only. A click inside the card must not close it — dragging
        // a selection across the scan ends in a mouseup that would otherwise land
        // on the overlay and dismiss the whole thing.
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeRecDocModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeRecDocModal();
        });

        var image = document.getElementById('recDocModalImage');
        image.addEventListener('click', function () {
            var zoomed = image.classList.toggle('rec-doc-zoomed');
            image.style.maxHeight = zoomed ? 'none' : '68vh';
            image.style.cursor = zoomed ? 'zoom-out' : 'zoom-in';
        });

        document.getElementById('recDocModalReplace').addEventListener('click', function () {
            var run = recDoc.replace;
            closeRecDocModal();
            if (run) run();
        });

        document.getElementById('recDocModalReset').addEventListener('click', function () {
            resetRecDocUpload(recDoc.resetUrl);
        });
    })();

    // Put the record back to "nothing uploaded". Not a correction — Replace is the
    // correction — but the way out when the wrong document was attached to the
    // wrong batch, which no amount of replacing fixes.
    function resetRecDocUpload(url) {
        if (!url) return;

        Swal.fire({
            icon: 'warning',
            title: 'Reset this upload?',
            html: '<p class="text-sm text-slate-600">The scan is removed and the record goes back to '
                + '<b>nothing uploaded</b>.</p>'
                + '<p class="text-xs text-amber-700 mt-2 font-semibold">Approval is held until a letter is on file '
                + 'again, so any children still pending cannot be approved until you upload one.</p>',
            showCancelButton: true,
            confirmButtonText: 'Yes, reset it',
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true,
            showLoaderOnConfirm: true,
            allowOutsideClick: function () { return !Swal.isLoading(); },
            preConfirm: function () {
                return fetch(url, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function (r) {
                    return r.json().catch(function () { return {}; })
                        .then(function (d) { return { ok: r.ok, data: d }; });
                })
                .then(function (res) {
                    if (!res.ok || !res.data.success) {
                        throw new Error(res.data.message || 'The upload could not be reset.');
                    }
                    return res.data;
                })
                .catch(function (err) {
                    Swal.showValidationMessage(err.message || 'Network error while resetting the upload.');
                    return false;
                });
            }
        }).then(function (r) {
            if (!r.isConfirmed || !r.value) return;

            closeRecDocModal();

            Swal.fire({
                icon: 'success', title: 'Reset', text: r.value.message,
                timer: 2000, showConfirmButton: false
            }).then(function () {
                // The batch menu, the child rows and the Approve gate all key off
                // whether a document exists, and they are rendered in three places
                // — so this is a reload rather than a patch in three.
                window.location.reload();
            });
        });
    }

    // ── The mother's scanned recommendation ────────────────────────────────
    // A subdivision's children inherit the mother's grant, so no letter is printed
    // for a child: the mother's signed sheet is scanned once and every child in the
    // batch shows it. One upload therefore answers for all of them, which is why
    // this is reachable both from the batch menu and from any child row.
    // addAnother: a merged file inherits a recommendation from each of the files
    // it was merged from, so an upload against a record that already holds one is
    // an addition, not a replacement. The server allocates the next slot.
    function uploadMotherRecommendation(batchId, motherFileNo, childCount, alreadyUploaded, addAnother) {
        var label = motherFileNo ? ('<b>' + escHtml(motherFileNo) + '</b>') : 'this batch';

        Swal.fire({
            title: alreadyUploaded
                ? 'Replace mother recommendation'
                : (addAnother ? 'Add another mother recommendation' : 'Upload mother recommendation'),
            html: '<p class="text-sm text-slate-600">Scan or photograph the signed recommendation for ' + label + '.</p>'
                // A Use Subdivision Template record is a batch of one, and
                // "all 1 children" is not a sentence.
                + (childCount === 1
                    ? '<p class="text-xs text-slate-500 mt-2">It will be shown against this record in place of a printed letter.</p>'
                    : '<p class="text-xs text-slate-500 mt-2">It will be shown against <b>all ' + childCount + '</b> children of this batch.</p>')
                + (alreadyUploaded
                    ? '<p class="text-xs text-amber-600 mt-2">This replaces the copy already uploaded.</p>'
                    : '')
                + '<input type="file" id="mother-rec-file" accept="image/*,application/pdf" '
                + 'class="mt-4 block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg '
                + 'file:border-0 file:text-sm file:font-bold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: alreadyUploaded ? 'Replace' : 'Upload',
            confirmButtonColor: '#7c3aed',
            preConfirm: function () {
                var input = document.getElementById('mother-rec-file');
                var file = input && input.files ? input.files[0] : null;

                if (!file) {
                    Swal.showValidationMessage('Choose the scanned letter first.');
                    return false;
                }
                // The server enforces this too; checking here saves the officer a
                // 20 MB upload that is only going to be refused at the end of it.
                if (file.size > 20 * 1024 * 1024) {
                    Swal.showValidationMessage('That file is larger than 20 MB. Scan it at a lower resolution.');
                    return false;
                }

                var body = new FormData();
                body.append('document', file);
                body.append('_token', '{{ csrf_token() }}');
                if (addAnother) body.append('add_another', '1');

                Swal.showLoading();

                return fetch('{{ url('land-recommendations/batch') }}/' + encodeURIComponent(batchId) + '/document', {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (res) {
                        if (!res.ok || !res.data.success) {
                            // Laravel returns validation failures under `errors`; the
                            // file rules are the ones an officer actually trips.
                            var errors = res.data.errors && res.data.errors.document;
                            throw new Error(errors ? errors[0] : (res.data.message || 'The upload failed.'));
                        }
                        return res.data;
                    })
                    .catch(function (err) {
                        Swal.showValidationMessage(err.message || 'Network error during the upload.');
                        return false;
                    });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            Swal.fire({
                icon: 'success',
                title: alreadyUploaded ? 'Replaced' : 'Uploaded',
                text: result.value.message,
                confirmButtonColor: '#7c3aed'
            }).then(function () {
                // The batch menu and every expanded child row both key off whether a
                // document exists, and they are rendered in two different places —
                // so the page is reloaded rather than patched in two.
                window.location.reload();
            });
        });
    }

    // ── The already-approved recommendation, per record ────────────────────
    // Files whose OP holder differs from the File Indexing name keep the letter
    // they were granted on paper: no new recommendation is generated for them, so
    // this scan is the document the record stands for, and approval waits for it.
    function uploadApprovedRecommendation(recordId, fileNumber, alreadyUploaded) {
        Swal.fire({
            title: alreadyUploaded ? 'Replace approved recommendation' : 'Upload approved recommendation',
            html: '<p class="text-sm text-slate-600">Scan or photograph the signed, already-approved recommendation for <b>'
                    + escHtml(fileNumber || 'this file') + '</b>.</p>'
                + '<p class="text-xs text-slate-500 mt-2">This file has been through recommendation once already, so a new letter is not generated for it. The record cannot be approved until this is on file.</p>'
                + (alreadyUploaded
                    ? '<p class="text-xs text-amber-600 mt-2">This replaces the copy already uploaded.</p>'
                    : '')
                + '<input type="file" id="approved-rec-file" accept="image/*,application/pdf" '
                + 'class="mt-4 block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg '
                + 'file:border-0 file:text-sm file:font-bold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">',
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: alreadyUploaded ? 'Replace' : 'Upload',
            confirmButtonColor: '#7c3aed',
            preConfirm: function () {
                var input = document.getElementById('approved-rec-file');
                var file = input && input.files ? input.files[0] : null;

                if (!file) {
                    Swal.showValidationMessage('Choose the scanned letter first.');
                    return false;
                }
                // The server enforces this too; checking here saves the officer a
                // 20 MB upload that is only going to be refused at the end of it.
                if (file.size > 20 * 1024 * 1024) {
                    Swal.showValidationMessage('That file is larger than 20 MB. Scan it at a lower resolution.');
                    return false;
                }

                var body = new FormData();
                body.append('document', file);
                body.append('_token', '{{ csrf_token() }}');

                Swal.showLoading();

                return fetch('{{ url('land-recommendations') }}/' + encodeURIComponent(recordId) + '/approved-recommendation', {
                    method: 'POST',
                    body: body,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
                    .then(function (res) {
                        if (!res.ok || !res.data.success) {
                            var errors = res.data.errors && res.data.errors.document;
                            throw new Error(errors ? errors[0] : (res.data.message || 'The upload failed.'));
                        }
                        return res.data;
                    })
                    .catch(function (err) {
                        Swal.showValidationMessage(err.message || 'Network error during the upload.');
                        return false;
                    });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;

            Swal.fire({
                icon: 'success',
                title: alreadyUploaded ? 'Replaced' : 'Uploaded',
                text: result.value.message,
                confirmButtonColor: '#7c3aed'
            }).then(function () {
                // The menu entry and the Approve gate both key off whether the
                // document exists, and they are rendered server-side — so the page
                // is reloaded rather than patched in two places.
                window.location.reload();
            });
        });
    }

    // Arriving straight from a save that captured an existing recommendation: open
    // the upload on the row that was just created, so the officer is not left to
    // find it on a register of hundreds.
    (function () {
        var pending = new URLSearchParams(window.location.search).get('upload_letter');
        if (!pending) return;

        var row = document.querySelector('[data-approved-letter-pending="' + pending + '"]');
        if (!row) return;

        uploadApprovedRecommendation(
            parseInt(pending, 10),
            row.getAttribute('data-file-number') || '',
            false
        );
    })();

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
                // Read the body on failure too, as approveRecord() does: a 422 names
                // the files still waiting on their mother recommendation.
                .then(r => r.json().catch(() => ({})).then(data => ({ ok: r.ok, data })))
                .then(res => {
                    if (!res.ok || !res.data.success) {
                        throw new Error(res.data.message || 'The approval failed.');
                    }
                    return res.data;
                })
                .catch(err => { Swal.showValidationMessage(err.message || 'Request failed.'); return false; });
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
                // The body carries the reason, so it is read on failure too. A
                // refusal answers 422 with a message an officer can act on — most
                // often "the mother recommendation has not been uploaded yet" —
                // and reporting the HTTP status instead would throw that away.
                .then(response => response.json()
                    .catch(() => ({}))
                    .then(data => ({ ok: response.ok, data })))
                .then(res => {
                    if (!res.ok || !res.data.success) {
                        throw new Error(res.data.message || 'The approval failed.');
                    }
                    return res.data;
                })
                .catch(error => {
                    Swal.showValidationMessage(error.message || 'Request failed.');
                    // Without this the dialog resolves as confirmed and the caller
                    // announces an approval that never happened.
                    return false;
                });
            }
        }).then((result) => {
            if (!result.isConfirmed || !result.value) return;

            Swal.fire('Approved!', 'Document has been approved successfully.', 'success')
                .then(() => window.location.reload());
        });
    }
    @endif
</script>

{{-- The OSS register edits its recommendation in place.  It uses the same form
     officers already know from OSS Applications, without loading that large
     register merely to open one card. --}}
@if(!empty($isOssView) && empty($ossTab))
    @include('lands_one_stop_shop.partials.recommendation-modal')
    <script>
        function closeRecommendationModal() {
            document.getElementById('recommendationModal')?.classList.add('hidden');
        }

        function setOssRecommendationFieldsLocked(locked) {
            [
                'rec_term', 'rec_dev_value', 'rec_completion_time', 'rec_ground_rent',
                'rec_dev_charge', 'rec_survey_charges', 'rec_director_reasons',
                'rec_director_sign', 'rec_director_date', 'rec_ps_sign', 'rec_ps_date',
                'rec_commissioner_name', 'rec_commissioner_date'
            ].forEach(function (id) {
                const field = document.getElementById(id);
                if (field) field.disabled = !!locked;
            });
        }

        function openOssRecommendationModal(record) {
            const set = function (id, value) {
                const field = document.getElementById(id);
                if (field) field.value = value || '';
            };

            set('rec_applicant_name', record.applicant_name);
            set('rec_file_ref', record.file_number);
            set('rec_purpose', record.purpose);
            set('rec_location', record.location);
            set('rec_plot_no', record.plot_no);
            set('rec_plan_no', record.plan_no);
            set('rec_record_id', record.id);
            set('rec_application_date', record.application_date);
            set('rec_applicant_address', record.applicant_address);
            set('rec_ps_plot', record.plot_no);
            set('rec_ps_location', record.location);
            set('rec_term', record.term);
            set('rec_dev_value', record.dev_value);
            set('rec_completion_time', record.completion_time);
            set('rec_ground_rent', record.ground_rent);
            set('rec_dev_charge', record.dev_charge || 'To Follow');
            set('rec_survey_charges', record.survey_charges);
            set('rec_director_reasons', record.director_reasons);

            const modal = document.getElementById('recommendationModal');
            if (!modal) return;
            modal.classList.remove('hidden');

            const saveButton = document.getElementById('recSaveBtn');
            const printButton = document.getElementById('recPrintBtn');
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.classList.remove('opacity-40', 'cursor-not-allowed');
            }
            if (printButton) printButton.disabled = !!record.printed;
            setOssRecommendationFieldsLocked(false);
            if (window.lucide) window.lucide.createIcons();
        }

        function saveRecommendation() {
            const value = (id) => document.getElementById(id)?.value?.trim() || '';
            const payload = {
                record_id: value('rec_record_id'),
                applicant_name: value('rec_applicant_name'),
                file_ref: value('rec_file_ref'),
                purpose: value('rec_purpose'),
                location: value('rec_location'),
                plot_no: value('rec_plot_no'),
                plan_no: value('rec_plan_no'),
                application_date: value('rec_application_date'),
                applicant_address: value('rec_applicant_address'),
                term: value('rec_term'),
                dev_value: value('rec_dev_value'),
                completion_time: value('rec_completion_time'),
                ground_rent: value('rec_ground_rent'),
                dev_charge: value('rec_dev_charge'),
                survey_charges: value('rec_survey_charges'),
                director_reasons: value('rec_director_reasons'),
            };
            const required = [
                ['term', 'Term (years)'], ['dev_value', 'Value for Proposed Development'],
                ['completion_time', 'Time for Completion'], ['ground_rent', 'Annual Ground Rent'],
                ['dev_charge', 'Development Charge'], ['survey_charges', 'Survey & Processing Charges']
            ].filter(([key]) => !payload[key]);

            if (required.length) {
                Swal.fire('Required fields missing', required.map(([, label]) => label).join(', '), 'warning');
                return;
            }

            const saveButton = document.getElementById('recSaveBtn');
            if (saveButton) {
                saveButton.disabled = true;
                saveButton.classList.add('opacity-40', 'cursor-not-allowed');
            }

            fetch('{{ route('lands-one-stop-shop.all-applications.save-recommendation') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(payload),
            })
            .then(response => response.json().catch(() => ({})).then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) throw new Error(data.message || 'Could not update the OSS recommendation.');
                Swal.fire('Saved', data.message, 'success');
                const printButton = document.getElementById('recPrintBtn');
                if (printButton) printButton.disabled = false;
            })
            .catch(error => Swal.fire('Save failed', error.message, 'error'))
            .finally(() => {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.classList.remove('opacity-40', 'cursor-not-allowed');
                }
            });
        }

        function printRecommendation() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('lands-one-stop-shop.all-applications.print-recommendation') }}';
            // The OSS White Copy route returns a Content-Disposition attachment.
            // Do not open a new tab: submit in place so the browser downloads the
            // PDF and the user remains on this recommendation card.
            const values = {
                _token: '{{ csrf_token() }}',
                applicant_name: document.getElementById('rec_applicant_name').value,
                file_ref: document.getElementById('rec_file_ref').value,
                purpose: document.getElementById('rec_purpose').value,
                location: document.getElementById('rec_location').value,
                plot_no: document.getElementById('rec_plot_no').value,
                plan_no: document.getElementById('rec_plan_no').value,
                term: document.getElementById('rec_term').value,
                dev_value: document.getElementById('rec_dev_value').value,
                completion_time: document.getElementById('rec_completion_time').value,
                ground_rent: document.getElementById('rec_ground_rent').value,
                dev_charge: document.getElementById('rec_dev_charge').value,
                survey_charges: document.getElementById('rec_survey_charges').value,
                director_reasons: document.getElementById('rec_director_reasons').value
            };
            Object.keys(values).forEach(function (key) {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = key; input.value = values[key] || '';
                form.appendChild(input);
            });
            document.body.appendChild(form); form.submit(); form.remove();
        }
    </script>
@endif

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
@include('components.applicant-phone-actions')
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
