@extends('layouts.app')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* ── Hide native DT search / length controls ── */
    #lss-applications-table_wrapper .dataTables_filter,
    #lss-applications-table_wrapper .dataTables_length { display: none; }

    /* ── Info & pagination bar ── */
    #lss-applications-table_wrapper .dataTables_info {
        font-size: 0.8125rem;
        color: rgb(100 116 139);
        padding: 0.75rem 1rem;
    }
    #lss-applications-table_wrapper .dataTables_paginate {
        padding: 0.5rem 1rem 0.75rem;
    }
    #lss-applications-table_wrapper .paginate_button {
        border-radius: 0.5rem !important;
        border: 1px solid rgb(226 232 240) !important;
        background: #fff !important;
        color: rgb(51 65 85) !important;
        margin: 0 2px !important;
        padding: 4px 12px !important;
        font-size: 0.8125rem !important;
    }
    #lss-applications-table_wrapper .paginate_button.current {
        background: rgb(37 99 235) !important;
        border-color: rgb(37 99 235) !important;
        color: #fff !important;
    }
    #lss-applications-table_wrapper .paginate_button:hover:not(.current) {
        background: rgb(241 245 249) !important;
    }

    /* ── Laravel Pagination Overrides ── */
    .oss-pagination nav svg {
        width: 1.25rem;
        height: 1.25rem;
    }
    .oss-pagination nav div:first-child { display: none; }
    .oss-pagination nav span[aria-current="page"] span {
        background: rgb(37 99 235) !important;
        color: #fff !important;
        border-color: rgb(37 99 235) !important;
    }
    .oss-pagination nav a, .oss-pagination nav span {
        border-radius: 0.5rem;
        border: 1px solid rgb(226 232 240);
        padding: 4px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.15s;
    }
    .oss-pagination nav a:hover {
        background: rgb(241 245 249);
    }

    /* ── Table normalisation ── */
    #lss-applications-table {
        border-collapse: collapse;
        width: 100%;
    }
    #lss-applications-table thead th {
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 2px solid rgb(226 232 240);
    }
    #lss-applications-table tbody td {
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 1px solid rgb(241 245 249);
    }
    /* Allow location to wrap */
    #lss-applications-table tbody td.lss-location-cell {
        white-space: normal;
        min-width: 150px;
        max-width: 240px;
    }
    /* Allow applicant address to wrap */
    #lss-applications-table tbody td.lss-address-cell {
        white-space: normal;
        min-width: 140px;
        max-width: 220px;
    }
    /* Sorting icon alignment */
    #lss-applications-table thead th.sorting,
    #lss-applications-table thead th.sorting_asc,
    #lss-applications-table thead th.sorting_desc {
        background-position: right 0.5rem center;
        padding-right: 1.5rem;
    }
    /* Zebra striping */
    #lss-applications-table tbody tr:nth-child(even) { background: rgb(248 250 252); }
    #lss-applications-table tbody tr:hover            { background: rgb(239 246 255); }

    /* Actions dropdown – fixed positioning to escape containers */
    .lss-action-dropdown {
        position: fixed;
        z-index: 9999;
        width: 10rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(226 232 240);
        background: #fff;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
        display: flex;
        flex-direction: column;
        white-space: nowrap;
        padding: 0.25rem 0;
    }
    .lss-action-dropdown button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        text-align: left;
        padding: 0.4rem 0.9rem;
        font-size: 0.6875rem;
        color: rgb(100 116 139);
        transition: all 0.15s;
    }
    .lss-action-dropdown button:hover {
        background: rgb(239 246 255);
        color: rgb(37 99 235);
    }
    .lss-action-dropdown button.wf-disabled {
        opacity: 0.35;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* Let dropdowns escape the scroll area while keeping horizontal scroll */
    .lss-table-card {
        overflow: visible !important;
    }
    .lss-table-scroll {
        overflow-x: auto;
        overflow-y: visible;
    }

    /* ── Keep info + pagination outside the scroll area ── */
    #lss-applications-table_wrapper .dataTables_info,
    #lss-applications-table_wrapper .dataTables_paginate {
        position: relative;
        z-index: 1;
    }

    /* Type badge colours */
    .oss-badge-residential  { background: rgb(220 252 231); color: rgb(21 128 61); }
    .oss-badge-commercial   { background: rgb(219 234 254); color: rgb(29 78 216); }
    .oss-badge-industrial   { background: rgb(254 226 226); color: rgb(185 28 28); }
    .oss-badge-agricultural { background: rgb(209 250 229); color: rgb(4 120 87); }

    /* Status badge colours */
    .oss-status-pending    { background: rgb(254 249 195); color: rgb(161 98 7); }
    .oss-status-processing { background: rgb(219 234 254); color: rgb(29 78 216); }
    .oss-status-approved   { background: rgb(220 252 231); color: rgb(21 128 61); }
    .oss-status-rejected   { background: rgb(254 226 226); color: rgb(185 28 28); }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @php
        $isChangeOfName = ($isChangeOfNamePage ?? false) || request()->query('type') === 'change-of-name';
        $isNoChangeOfName = ($isNoChangeOfNamePage ?? false) || request()->query('type') === 'no-change-of-name';
        $dynamicPageTitle = $isChangeOfName
            ? 'Applications (Change of Ownership)'
            : ($isNoChangeOfName ? 'Applications (No Change of Ownership)' : 'Applications');
        $dynamicPageDescription = $isChangeOfName
            ? 'Land One Stop Shop - Change of Ownership applications.'
            : ($isNoChangeOfName
                ? 'Land One Stop Shop - No Change of Ownership applications.'
                : 'Land One Stop Shop - all captured applications.');
    @endphp
    @include('admin.header', [
        'PageTitle'       => $dynamicPageTitle,
        'PageDescription' => $dynamicPageDescription
    ])

    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- ── Title row ── --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">OSS Management</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">{{ $dynamicPageTitle }}</h1>
                    @php
                        $headerTotal = $cardTotal ?? (is_array($cardCounts ?? null) ? array_sum($cardCounts) : 0);
                        $headerLoaded = $records->count();
                    @endphp
                    <p class="text-slate-500 mt-1 text-sm">
                        {{ number_format($headerLoaded) }} of {{ number_format($headerTotal) }} record(s) loaded
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="lss-search" value="{{ request('search') }}"
                            placeholder="Search applications..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="lss-type-filter" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="">All Types</option>
                        @foreach($typeOptions as $val => $label)
                            <option value="{{ $val }}" @selected(request('app_type') == $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select id="lss-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $option)
                            <option value="{{ $option }}" @selected($limit == $option)>{{ $option }} rows</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="openRecordsExportModal()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-emerald-700 transition whitespace-nowrap">
                        <i data-lucide="download" class="w-4 h-4"></i>
                        Export Records
                    </button>
                    {{-- Temporarily hidden at the user's request.
                    <button type="button" id="btn-new-application"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition whitespace-nowrap"
                        onclick="ossOpenCreateModal()">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Application
                    </button>
                    --}}
                </div>
            </div>

            {{-- ── Summary Cards ── --}}
            @php
                $totalRecords = $cardTotal ?? (is_array($cardCounts ?? null) ? array_sum($cardCounts) : 0);
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    {{-- Total Records --}}
                <div class="bg-white border border-violet-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-violet-50 flex items-center justify-center">
                        <i data-lucide="layers" class="w-6 h-6 text-violet-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-violet-400">Total Records</p>
                        <p class="text-2xl font-extrabold text-violet-700 leading-tight">{{ number_format($totalRecords) }}</p>
                        <p class="text-[9px] text-slate-400">All categories</p>
                    </div>
                </div>
                    {{-- Daily Records --}}
                <div class="bg-white border border-indigo-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <i data-lucide="calendar-check" class="w-6 h-6 text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-400">Today's Records</p>
                        <p class="text-2xl font-extrabold text-indigo-700 leading-tight">{{ number_format($dailyCount ?? 0) }}</p>
                        <p class="text-[9px] text-slate-400">{{ now()->format('d M Y') }}</p>
                    </div>
                </div>
                {{-- Residential --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i data-lucide="home" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Residential</p>
                        <p class="text-2xl font-extrabold text-slate-900 leading-tight">{{ number_format($cardCounts['residential'] ?? 0) }}</p>
                    </div>
                </div>
                {{-- Commercial --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i data-lucide="building-2" class="w-6 h-6 text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Commercial</p>
                        <p class="text-2xl font-extrabold text-slate-900 leading-tight">{{ number_format($cardCounts['commercial'] ?? 0) }}</p>
                    </div>
                </div>
                {{-- Industrial --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-red-50 flex items-center justify-center">
                        <i data-lucide="factory" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Industrial</p>
                        <p class="text-2xl font-extrabold text-slate-900 leading-tight">{{ number_format($cardCounts['industrial'] ?? 0) }}</p>
                    </div>
                </div>
                {{-- Agricultural --}}
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 flex items-center gap-4">
                    <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i data-lucide="wheat" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Agricultural</p>
                        <p class="text-2xl font-extrabold text-slate-900 leading-tight">{{ number_format($cardCounts['agricultural'] ?? 0) }}</p>
                    </div>
                </div>
            
            </div>

            {{-- ── Data Table ── --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm lss-table-card">
                <div class="lss-table-scroll">
                    <table id="lss-applications-table" class="w-full text-sm" style="min-width:900px">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                <th class="px-4 py-3 text-left" style="min-width:50px">S/N</th>
                                @unless($isNoChangeOfName)
                                <th class="px-4 py-3 text-left" style="min-width:80px">OP Serial No</th>
                                @endunless
                                <th class="px-4 py-3 text-left" style="min-width:100px">File No</th>
                                @unless($isNoChangeOfName)
                                <th class="px-4 py-3 text-left" style="min-width:140px">Original Holder</th>
                                @endunless
                                <th class="px-4 py-3 text-center" style="min-width:50px">Photo</th>
                                <th class="px-4 py-3 text-left" style="min-width:150px">{{ $isNoChangeOfName ? 'Holder' : 'Applicant Name' }}</th>
                                <th class="px-4 py-3 text-left" style="min-width:110px">Application Type</th>
                                <th class="px-4 py-3 text-left" style="min-width:65px">Plot No</th>
                                <th class="px-4 py-3 text-left" style="min-width:65px">Plan No</th>
                                <th class="px-4 py-3 text-left" style="min-width:120px">District</th>
                                <th class="px-4 py-3 text-left" style="min-width:140px">Location</th>
                                <th class="px-4 py-3 text-left" style="min-width:140px">Applicant Address</th>
                                <th class="px-4 py-3 text-left" style="min-width:100px">Phone</th>
                                <th class="px-4 py-3 text-left" style="min-width:100px">Application Date</th>
                                <th class="px-4 py-3 text-left" style="min-width:110px">Created By</th>
                                <th class="px-4 py-3 text-left" style="min-width:100px">Date Created</th>
                                <th class="px-4 py-3 text-center" style="min-width:50px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                <tr data-record='@json($record)'>
                                    <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $loop->iteration }}</td>
                                    @unless($isNoChangeOfName)
                                    <td class="px-4 py-2.5 text-slate-600 text-xs font-mono">
                                        @php
                                            $opSerial = $record->op_serial_number ?? $record->ic_op_serial_number ?? $record->pra_op_serial_number ?? null;
                                        @endphp
                                        @if($opSerial)
                                            <span class="text-slate-900 font-semibold">{{ $opSerial }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    @endunless
                                    <td class="px-4 py-2.5 font-mono text-xs font-semibold text-blue-600">{{ $record->file_no ?? $record->temp_fileno ?? '—' }}</td>
                                    @unless($isNoChangeOfName)
                                    @php
                                        // ic_original_holder (source_capture.party_1_name) is always the OP
                                        // issuer "Kano State Government" and must NOT be shown here. Prefer
                                        // pra_original_holder — the actual holder name entered on the
                                        // Transfer of Title (Grantor). Fall back to ic only if pra is absent.
                                        $displayOriginalHolder = ($record->pra_original_holder && $record->pra_original_holder !== '—')
                                            ? $record->pra_original_holder
                                            : ($record->ic_original_holder ?: '—');
                                    @endphp
                                    <td class="px-4 py-2.5 text-red-600 text-xs font-semibold">{{ $displayOriginalHolder }}</td>
                                    @endunless
                                    <td class="px-4 py-2.5 text-center">
                                        @if($record->passport_photo_url)
                                            <img src="{{ $record->passport_photo_url }}" alt="Photo"
                                                 class="w-8 h-8 rounded-full object-cover border border-slate-200 inline-block"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='inline-flex';">
                                            <span class="w-8 h-8 rounded-full bg-slate-100 items-center justify-center hidden">
                                                <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                                            </span>
                                        @else
                                            <span class="w-8 h-8 rounded-full bg-slate-100 inline-flex items-center justify-center">
                                                <i data-lucide="user" class="w-4 h-4 text-slate-400"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-semibold text-blue-600">{{ $record->applicant_name }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold uppercase oss-badge-{{ $record->application_type }}">
                                            {{ ucfirst($record->application_type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->plot_no ?: ($record->ic_plot_number ?: ($record->pra_plot_no ?: ($record->fi_plot_no ?: ($record->fn_plot_no ?: '—')))) }}</td>
                                    <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->plan_no ?: ($record->ic_plan_no ?: ($record->pra_plan_no ?: ($record->fi_plan_no ?: ($record->fn_plan_no ?: '—')))) }}</td>
                                    <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->district ?: ($record->ic_district ?: ($record->pra_district ?: ($record->fi_district ?: ($record->fn_district ?: '—')))) }}</td>
                                    <td class="lss-location-cell px-4 py-2.5 text-slate-600 text-xs leading-snug">{{ $record->location ?: ($record->ic_location ?: ($record->pra_location ?: ($record->fi_location ?: ($record->fn_location ?: '—')))) }}</td>
                                    <td class="lss-address-cell px-4 py-2.5 text-slate-600 text-xs leading-snug">{{ $record->address ?: ($record->residential_address ?: ($record->correspondence_address ?: '—')) }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">{{ $record->phone ?? '—' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600" data-order="{{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('Y-m-d') : '' }}">
                                        {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-xs text-slate-600">
                                        @php $capturedByName = trim((string) ($record->captured_by_name ?? '')); @endphp
                                        @if($capturedByName !== '' && $capturedByName !== '—')
                                            {{-- Opens the shared profile card (js/user-profile-card.js). --}}
                                            <span class="upc-trigger" data-user-card
                                                @if(!empty($record->captured_by)) data-user-id="{{ $record->captured_by }}" @endif
                                                data-user-name="{{ $capturedByName }}"
                                                title="{{ __('View profile') }}">{{ $capturedByName }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600" data-order="{{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('Y-m-d H:i:s') : '' }}">
                                        {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <div class="inline-block" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                                            <button type="button" x-ref="trigger"
                                                @click="if(open){ open=false; } else { $nextTick(()=>{ let r=$refs.trigger.getBoundingClientRect(); let dd=$refs.dropdown; dd.style.visibility='hidden'; dd.style.display='flex'; let dh=dd.offsetHeight; dd.style.display=''; dd.style.visibility=''; let spaceBelow=window.innerHeight-r.bottom; if(spaceBelow < dh+8){ dd.style.top=(r.top - dh - 4)+'px'; } else { dd.style.top=(r.bottom+4)+'px'; } dd.style.left=Math.max(8, r.right - dd.offsetWidth)+'px'; open=true; if(window.lucide) window.lucide.createIcons(); if(typeof ossUpdateWorkflowButtons==='function') ossUpdateWorkflowButtons($refs.trigger); }); }"
                                                @click.outside="open = false"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition"
                                                data-wf-trigger>
                                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                            </button>
                                            <div x-ref="dropdown" x-cloak x-show="open" x-transition.opacity x-transition.scale.95
                                                class="lss-action-dropdown">
                                                @if($isChangeOfName)
                                                <button type="button" onclick="ossViewRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5 text-blue-500"></i> View Record
                                                </button>
                                                @if(!empty($record->oss_application_id))
                                                <button type="button" onclick="ossEditRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="pencil" class="w-3.5 h-3.5 text-amber-500"></i> Edit Record
                                                </button>
                                                @endif
                                                <button type="button" onclick="ossChangeOfOwnershipRecord(this)" class="inline-flex items-center gap-2">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4 text-amber-500 flex-shrink-0" style="flex-shrink:0;">
                                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                                    </svg> Change of Ownership
                                                </button>
                                                <button type="button" onclick="ossVerificationRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="clipboard-check" class="w-3.5 h-3.5 text-teal-500"></i> Verification
                                                </button>
                                                <button type="button" onclick="ossAcknowledgementRecord(this)" data-wf="acknowledgement" class="inline-flex items-center gap-2 wf-disabled">
                                                    <i data-lucide="badge-check" class="w-3.5 h-3.5 text-emerald-500"></i> Acknowledgement
                                                </button>
                                                <button type="button" onclick="ossLand12Record(this)" data-wf="land12" class="inline-flex items-center gap-2 wf-disabled">
                                                    <i data-lucide="file-text" class="w-3.5 h-3.5 text-orange-500"></i> Land 12
                                                </button>
                                                <button type="button" onclick="ossRecommendationRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="thumbs-up" class="w-3.5 h-3.5 text-blue-500"></i> Recommendation
                                                </button>
                                                <button type="button" onclick="ossPrinterManagerRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="printer" class="w-3.5 h-3.5 text-indigo-600"></i> Printer Manager
                                                </button>
                                                @if(!empty($record->oss_application_id))
                                                @php
                                                    $isApproved = ($record->status ?? '') === \App\Models\LandsOneStopShopApplication::STATUS_APPROVED;
                                                @endphp
                                                @if($isApproved)
                                                <button type="button" disabled title="Approved applications cannot be deleted" class="inline-flex items-center gap-2 opacity-40 cursor-not-allowed">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-slate-400"></i> Delete Entry
                                                </button>
                                                @else
                                                <button type="button" onclick="ossDeleteRecord(this)" class="inline-flex items-center gap-2 !text-red-500 hover:!text-red-700 hover:!bg-red-50">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i> Delete Entry
                                                </button>
                                                @endif
                                                @endif
                                                @else
                                                <button type="button" onclick="ossViewRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5 text-blue-500"></i> View
                                                </button>
                                                <button type="button" onclick="ossEditRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="pencil" class="w-3.5 h-3.5 text-amber-500"></i> Edit
                                                </button>
                                                <button type="button" onclick="ossBillRecord(this)" class="inline-flex items-center gap-2">
                                                    <i data-lucide="receipt" class="w-3.5 h-3.5 text-emerald-500"></i> Bill
                                                </button>
                                                @php
                                                    $isApproved = ($record->status ?? '') === \App\Models\LandsOneStopShopApplication::STATUS_APPROVED;
                                                @endphp
                                                @if($isApproved)
                                                <button type="button" disabled title="Approved applications cannot be deleted" class="inline-flex items-center gap-2 opacity-40 cursor-not-allowed">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-slate-400"></i> Delete
                                                </button>
                                                @else
                                                <button type="button" onclick="ossDeleteRecord(this)" class="inline-flex items-center gap-2 !text-red-500 hover:!text-red-700 hover:!bg-red-50">
                                                    <i data-lucide="trash-2" class="w-3.5 h-3.5 text-red-500"></i> Delete
                                                </button>
                                                @endif
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isNoChangeOfName ? 15 : 17 }}" class="px-4 py-10 text-center text-slate-400 italic">No applications found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ── Custom Pagination Info & Links ── --}}
                <div class="px-4 py-3 flex items-center justify-between border-t border-slate-100 bg-slate-50/30">
                    <div class="text-xs text-slate-500 font-medium">
                        @if($records->total() > 0)
                            Showing <span class="text-slate-900">{{ $records->firstItem() }}</span> to <span class="text-slate-900">{{ $records->lastItem() }}</span> of <span class="text-slate-900">{{ number_format($records->total()) }}</span> applications
                        @else
                            No applications available
                        @endif
                    </div>
                    <div class="oss-pagination">
                        {{ $records->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ──────────────── Application Form Modal (Residential / Commercial / Industrial) ──────────────── --}}
    @include('lands_one_stop_shop.partials.application-form-modal')

    {{-- ──────────────── Bill Modal ──────────────── --}}
    @include('lands_one_stop_shop.partials.bill-modal')

    {{-- ──────────────── In-page Action Modals (Change of Name) ──────────────── --}}
    @include('lands_one_stop_shop.partials.change-of-ownership-modal')
    @include('lands_one_stop_shop.partials.acknowledgement-modal')
    @include('lands_one_stop_shop.partials.recommendation-modal')
    @include('lands_one_stop_shop.partials.verification-modal')

    {{-- ──────────────── Export Records Modal ──────────────── --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
    @include('exports.records_export_modal', ['exportConfig' => [
        'title'         => $isChangeOfName ? 'Export Change of Name Applications' : 'Export Applications',
        'subtitle'      => 'Consolidated report generation & export filter',
        'endpoint'      => route('lands-one-stop-shop.all-applications.index'),
        'params'        => ['type' => request()->query('type')],
        'filename'      => $isChangeOfName ? 'Change_of_Name_Applications' : 'OSS_Applications',
        'reportTitle'   => $isChangeOfName ? 'Change of Name Applications Register' : 'OSS Applications Register',
        'search'        => request('search'),
        'statusOptions' => ['' => 'All Statuses'] + \App\Models\LandsOneStopShopApplication::statusOptions(),
    ]])

    {{-- ──────────────── Global File Number Selector Modal ──────────────── --}}
    @include('components.global-fileno-modal')

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/survey-report.js') }}?v={{ time() }}"></script>
<script src="{{ asset('js/lands-one-stop-shop/applications.js') }}?v={{ time() }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof GlobalFileNoModal !== 'undefined') {
            GlobalFileNoModal.init();
        }
    });
</script>
@endsection
