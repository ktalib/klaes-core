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
                        <input type="hidden" name="type" value="{{ !empty($isOssView) ? 'OSS' : 'ROFO' }}">
                        <input type="hidden" name="tab" value="{{ $tab ?? 'not_printed' }}">
                        
                        <select name="user_id" onchange="this.form.submit()" 
                                class="pl-3 pr-8 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm cursor-pointer font-medium text-slate-700">
                            <option value="all" {{ request('user_id') === 'all' ? 'selected' : '' }}>All Users</option>
                            @foreach($filterUsers as $fUser)
                                <option value="{{ $fUser->id }}" {{ (request('user_id', Auth::id()) == $fUser->id && request('user_id') !== 'all') ? 'selected' : '' }}>
                                    {{ $fUser->name }}
                                </option>
                            @endforeach
                        </select>

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
            @php $tab = $tab ?? 'not_printed'; @endphp
            <div class="flex items-center gap-2 mb-4 bg-white p-1.5 rounded-2xl border border-slate-200 shadow-sm w-full sm:w-max">
                <a href="{{ route('land-recommendations.index', array_filter(['type' => !empty($isOssView) ? 'OSS' : 'ROFO', 'tab' => 'not_printed', 'search' => request('search'), 'user_id' => request('user_id')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'not_printed' ? 'bg-amber-500 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="file-clock" class="h-4 w-4"></i>
                    Not Printed
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'not_printed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['not_printed']) }}</span>
                </a>
                <a href="{{ route('land-recommendations.index', array_filter(['type' => !empty($isOssView) ? 'OSS' : 'ROFO', 'tab' => 'printed', 'search' => request('search'), 'user_id' => request('user_id')])) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold transition {{ $tab === 'printed' ? 'bg-green-600 text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100' }}">
                    <i data-lucide="printer-check" class="h-4 w-4"></i>
                    Printed
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black {{ $tab === 'printed' ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ number_format($stats['printed']) }}</span>
                </a>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between flex-wrap gap-3">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-blue-600"></i>
                        Application Records
                        <span class="text-slate-400 normal-case tracking-normal font-medium">· {{ $tab === 'printed' ? 'Printed' : 'Not Printed' }}</span>
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
                                <th class="px-6 py-4 whitespace-nowrap">Purpose Clause</th>
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
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @php
                                $renderedBatch = null;
                                $batchColspan  = empty($isOssView) ? 20 : 19;
                            @endphp
                            @forelse($recommendations as $rec)
                            @php
                                // Subdivision batch children are adjacent (shared created_at,
                                // ordered by batch_seq), so the grouped header is emitted once,
                                // when the batch id first changes.
                                $batchId    = $rec->rofo_batch_id ?: null;
                                $startBatch = $batchId && $batchId !== $renderedBatch;
                                $renderedBatch = $batchId ?: null;
                            @endphp

                            @if($startBatch)
                            @php $batchTotal = $batchSizes[$batchId] ?? 0; @endphp
                            <tr class="batch-head bg-violet-50 border-y border-violet-200 cursor-pointer hover:bg-violet-100/70 transition"
                                data-batch="{{ $batchId }}" aria-expanded="false">
                                <td colspan="{{ $batchColspan }}" class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="inline-flex items-center gap-1.5 text-violet-800 font-bold text-xs uppercase tracking-wider">
                                            <i data-lucide="chevron-right" class="batch-chevron h-4 w-4 transition-transform"></i>
                                            Batch
                                        </span>
                                        <span class="font-mono font-black text-slate-900">{{ $rec->batch_mother_file_no ?: $rec->old_file_number }}</span>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-800">
                                            <i data-lucide="git-fork" class="h-3 w-3"></i> {{ $rec->application_type ?: 'Plot Subdivision' }}
                                        </span>
                                        <span class="text-xs text-slate-600">{{ $batchTotal }} {{ \Illuminate\Support\Str::plural('child', $batchTotal) }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">{{ $batchId }}</span>

                                        @php
                                            $bAct        = $batchActions[$batchId] ?? ['pending_ids' => [], 'all_approved' => false];
                                            $pendingIds  = $bAct['pending_ids'];
                                            $allApproved = $bAct['all_approved'];
                                        @endphp

                                        {{-- Status at a glance; the actions themselves live in the menu. --}}
                                        @if($allApproved)
                                        <span class="px-2.5 py-0.5 text-[10px] font-bold bg-emerald-100 text-emerald-700 rounded-full inline-flex items-center gap-1">
                                            <i data-lucide="check" class="h-3 w-3"></i> All approved
                                        </span>
                                        @else
                                        <span class="px-2.5 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-700 rounded-full inline-flex items-center gap-1">
                                            <i data-lucide="clock" class="h-3 w-3"></i> {{ count($pendingIds) }} pending
                                        </span>
                                        @endif

                                        {{-- Batch actions menu. Mirrors the per-row kebab, but every item
                                             acts on the whole batch — including children that paginated onto
                                             another page. Kept to the left of the row because this table is
                                             far wider than the viewport, and stopPropagation keeps a click
                                             on the menu from toggling the row open. --}}
                                        <div class="batch-actions" onclick="event.stopPropagation()">
                                            <div x-data="{
                                                open: false,
                                                menuStyle: {},
                                                toggleMenu($event) {
                                                    if (!this.open) {
                                                        const rect = $event.currentTarget.getBoundingClientRect();
                                                        // Anchored to the left edge — this button sits at the
                                                        // start of the row, so right-anchoring would push the
                                                        // menu off-screen.
                                                        this.menuStyle = {
                                                            position: 'fixed',
                                                            top: (rect.bottom + 4) + 'px',
                                                            left: rect.left + 'px',
                                                            zIndex: 9999
                                                        };
                                                        if (window.innerHeight - rect.bottom < 160) {
                                                            this.menuStyle.top = 'auto';
                                                            this.menuStyle.bottom = (window.innerHeight - rect.top + 4) + 'px';
                                                        }
                                                    }
                                                    this.open = !this.open;
                                                }
                                            }" class="relative inline-block text-left">
                                                <button type="button" @click="toggleMenu($event)" @click.away="open = false"
                                                        class="inline-flex items-center gap-1.5 pl-2.5 pr-2 py-1.5 rounded-lg border border-violet-300 bg-white text-violet-700 hover:bg-violet-50 transition">
                                                    <span class="text-[10px] font-bold uppercase tracking-wider">Batch actions</span>
                                                    <i data-lucide="more-vertical" class="h-4 w-4"></i>
                                                </button>

                                                <div x-show="open"
                                                     x-transition:enter="transition ease-out duration-100"
                                                     x-transition:enter-start="opacity-0 scale-95"
                                                     x-transition:enter-end="opacity-100 scale-100"
                                                     x-transition:leave="transition ease-in duration-75"
                                                     x-transition:leave-start="opacity-100 scale-100"
                                                     x-transition:leave-end="opacity-0 scale-95"
                                                     :style="menuStyle"
                                                     class="w-60 rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                                     style="display: none;">
                                                    <div class="px-4 py-2 bg-violet-50 border-b border-violet-100">
                                                        <p class="text-[10px] font-black text-violet-800 uppercase tracking-wider">Whole batch</p>
                                                        <p class="text-[10px] text-slate-500 font-mono truncate">{{ $rec->batch_mother_file_no ?: $rec->old_file_number }}</p>
                                                    </div>
                                                    <div class="py-1">
                                                        @if(count($pendingIds))
                                                        <button type="button" @click="open = false"
                                                                onclick='approveBatch(@json($batchId), @json($pendingIds))'
                                                                class="w-full flex items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-emerald-50 transition gap-2">
                                                            <i data-lucide="check-check" class="h-4 w-4 text-emerald-600"></i>
                                                            Approve all ({{ count($pendingIds) }})
                                                        </button>
                                                        @else
                                                        <div class="flex items-center px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed">
                                                            <i data-lucide="check" class="h-4 w-4 text-emerald-500"></i>
                                                            All approved
                                                        </div>
                                                        @endif

                                                        <div class="border-t border-slate-100 my-1"></div>

                                                        <button type="button" @click="open = false"
                                                                onclick='printBatch(@json($batchId), {{ $allApproved ? 'true' : 'false' }})'
                                                                class="w-full flex items-center px-4 py-2.5 text-sm {{ $allApproved ? 'text-slate-700 hover:bg-violet-50' : 'text-slate-400' }} transition gap-2">
                                                            <i data-lucide="printer" class="h-4 w-4 {{ $allApproved ? 'text-violet-600' : 'text-slate-300' }}"></i>
                                                            Print all ({{ $batchTotal }})
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <span class="ml-auto text-[10px] font-semibold text-violet-700 uppercase tracking-wider batch-hint">Click to expand</span>
                                    </div>
                                </td>
                            </tr>
                            @endif

                            <tr class="hover:bg-slate-50/50 transition row-item {{ $batchId ? 'batch-child bg-violet-50/20 hidden' : '' }}"
                                data-id="{{ $rec->id }}" data-status="{{ $rec->status }}" @if($batchId) data-batch="{{ $batchId }}" @endif>
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
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->purpose_of_clause }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap uppercase">{{ $rec->display_location }}</td>
                                <td class="px-4 py-2 text-blue-600 whitespace-nowrap font-medium italic">{{ $rec->resolved_applicant_address ?? $rec->applicant_address ?? 'N/A' }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->layout_plan_no }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->term }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->development_period }}</td>
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
                            </tr>
                            @empty
                            <tr>
                                <td colspan="17" class="px-8 py-12 text-center">
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
        </div>
    </div>
    @include('admin.footer')
</div>

@push('scripts')
<script>
    // ── Subdivision batches ────────────────────────────────────────────────
    // Children of one mother file are collapsed under a batch row and revealed
    // by clicking it, so a 20-plot subdivision does not bury the rest of the list.
    document.addEventListener('click', function (e) {
        var head = e.target.closest('tr.batch-head');
        if (!head) return;

        var batch    = head.getAttribute('data-batch');
        var expanded = head.getAttribute('aria-expanded') === 'true';
        head.setAttribute('aria-expanded', expanded ? 'false' : 'true');

        var chevron = head.querySelector('.batch-chevron');
        if (chevron) chevron.style.transform = expanded ? '' : 'rotate(90deg)';

        var hint = head.querySelector('.batch-hint');
        if (hint) hint.textContent = expanded ? 'Click to expand' : 'Click to collapse';

        document.querySelectorAll('tr.batch-child[data-batch="' + batch + '"]').forEach(function (tr) {
            tr.classList.toggle('hidden', expanded);
        });
    });

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
