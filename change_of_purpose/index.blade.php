@extends('layouts.app')

@section('styles')
<style>
    /* -- DataTable overrides -- */
    #cop-pending-table_wrapper .dataTables_filter,
    #cop-pending-table_wrapper .dataTables_length,
    #cop-approved-table_wrapper .dataTables_filter,
    #cop-approved-table_wrapper .dataTables_length { display: none; }

    #cop-pending-table_wrapper .dataTables_info,
    #cop-approved-table_wrapper .dataTables_info {
        font-size: 0.8125rem; color: rgb(100 116 139); padding: 0.75rem 1rem;
    }
    #cop-pending-table_wrapper .dataTables_paginate,
    #cop-approved-table_wrapper .dataTables_paginate {
        padding: 0.5rem 1rem 0.75rem;
    }
    #cop-pending-table thead th,
    #cop-approved-table thead th {
        white-space: nowrap; vertical-align: middle; border-bottom: 2px solid rgb(226 232 240);
    }
    #cop-pending-table tbody td,
    #cop-approved-table tbody td {
        white-space: nowrap; vertical-align: middle; border-bottom: 1px solid rgb(241 245 249);
    }
    #cop-pending-table  tbody td:nth-child(2),
    #cop-pending-table  tbody td:nth-child(7),
    #cop-approved-table tbody td:nth-child(2),
    #cop-approved-table tbody td:nth-child(7) { white-space: normal; min-width: 160px; max-width: 240px; }

    /* -- Tab styles -- */
    .cop-tab-btn.active { background: white; color: rgb(37 99 235); box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .cop-tab-panel { display: none; }
    .cop-tab-panel.active { display: block; }

    /* -- Modals -- */
    .swal2-container { z-index: 20000 !important; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Change of Purpose',
        'PageDescription' => 'Manage land use change applications and commission new file numbers.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- -- Page Heading -- --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                     <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Change of Purpose</h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="cop-search" placeholder="Search records..."
                            class="w-64 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="cop-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $opt)
                            <option value="{{ $opt }}" @selected($limit == $opt)>{{ $opt }} rows</option>
                        @endforeach
                    </select>
                    @if(request('mode') !== 'land')
                    <button type="button" onclick="copOpenCreate()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i> New Application
                    </button>
                    @endif
                </div>
            </div>

            {{-- -- Dashboard Stats -- --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                {{-- Total --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                        <p class="text-3xl font-extrabold text-slate-800 mt-1">{{ $stats['total'] }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">All applications</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                        <i data-lucide="layers" class="w-5 h-5 text-slate-500"></i>
                    </div>
                </div>
                {{-- Daily --}}
                <div class="bg-white rounded-2xl border border-indigo-200 shadow-sm p-5 flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-500">Created Today</p>
                        <p class="text-3xl font-extrabold text-indigo-600 mt-1">{{ $stats['daily'] }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">New entries</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <i data-lucide="calendar" class="w-5 h-5 text-indigo-500"></i>
                    </div>
                </div>
                {{-- Pending --}}
                <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-500">Pending</p>
                        <p class="text-3xl font-extrabold text-amber-600 mt-1">{{ $stats['pending'] }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Awaiting review</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                        <i data-lucide="clock" class="w-5 h-5 text-amber-500"></i>
                    </div>
                </div>
                {{-- Approved --}}
                <div class="bg-white rounded-2xl border border-blue-200 shadow-sm p-5 flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-blue-500">Approved</p>
                        <p class="text-3xl font-extrabold text-blue-600 mt-1">{{ $stats['approved'] }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Ready to commission</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-blue-500"></i>
                    </div>
                </div>


                {{-- Rejected --}}
                <div class="bg-white rounded-2xl border border-red-200 shadow-sm p-5 flex items-start justify-between">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-red-500">Rejected</p>
                        <p class="text-3xl font-extrabold text-red-600 mt-1">{{ $stats['rejected'] }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Not approved</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-500"></i>
                    </div>
                </div>
            </div>

            {{-- -- Tabs -- --}}
            <div class="bg-slate-100 rounded-2xl p-1 inline-flex gap-1">
                <button class="cop-tab-btn active px-5 py-2 rounded-xl text-sm font-semibold text-slate-600 transition" onclick="copSwitchTab('applications', this)">
                    <i data-lucide="file-text" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                    Applications <span class="ml-1 bg-amber-100 text-amber-700 text-[10px] px-1.5 py-0.5 rounded-full font-bold">{{ $pendingRecords->count() }}</span>
                </button>
                <button class="cop-tab-btn px-5 py-2 rounded-xl text-sm font-semibold text-slate-600 transition" onclick="copSwitchTab('initiate', this)">
                    <i data-lucide="check-circle" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                    Initiate Change <span class="ml-1 bg-emerald-100 text-emerald-700 text-[10px] px-1.5 py-0.5 rounded-full font-bold">{{ $approvedRecords->count() }}</span>
                </button>
            </div>

            {{-- ============================================
                 TAB 1 - APPLICATIONS
                 ============================================ --}}
            <div id="cop-tab-applications" class="cop-tab-panel active">
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="cop-pending-table" class="w-full text-sm" style="min-width:1000px">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">Applicant</th>
                                    <th class="px-4 py-3 text-left">File No</th>
                                    <th class="px-4 py-3 text-left">Current Use</th>
                                    <th class="px-4 py-3 text-left">New Purpose</th>
                                    <th class="px-4 py-3 text-left">Location</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    @if(request('mode') !== 'land')
                                    <th class="px-4 py-3 text-center">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingRecords as $record)
                                    <tr data-record='@json($record)'>
                                        <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $record->id }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->applicant_name }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 font-mono text-xs">{{ $record->file_no ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->land_use ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $landUseOptions[strtoupper($record->purpose ?? '')] ?? ($record->purpose ?: '-') }}</td>
                                        <td class="px-4 py-2.5 text-[10px] text-slate-500 leading-snug">
                                            {{ implode(', ', array_filter([
                                                $record->plot_no ? 'Plot '.$record->plot_no : null,
                                                $record->location,
                                                $record->district,
                                                $record->lga,
                                                $record->state
                                            ])) }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            @php $sc = match(strtolower((string) $record->status)) {
                                                'approved'     => 'bg-emerald-50 text-emerald-700',
                                                'pending'      => 'bg-amber-50 text-amber-700',
                                                'rejected'     => 'bg-red-50 text-red-700',
                                                'processing'   => 'bg-blue-50 text-blue-700',
                                                'commissioned' => 'bg-indigo-50 text-indigo-700',
                                                default        => 'bg-slate-50 text-slate-600',
                                            }; @endphp
                                            <span class="px-2 py-1 text-[11px] font-bold rounded {{ $sc }}">{{ ucfirst((string) $record->status) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                                            {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '-' }}
                                        </td>
                                        @if(request('mode') !== 'land')
                                        <td class="px-4 py-2.5 text-center">
                                                <div class="relative inline-block text-left" id="cop-dropdown-{{ $record->id }}">
                                                <button type="button" onclick="copToggleActionMenu({{ $record->id }})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-full transition">
                                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                                </button>
                                                
                                                <div id="cop-menu-{{ $record->id }}" class="hidden fixed z-[999] mt-2 w-max origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none divide-y divide-slate-100 whitespace-nowrap">
                                                    <div class="py-1">
                                                        <button onclick="copViewApplication(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                            <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i> View Details
                                                        </button>
                                                        @if($record->knupda_status === 'Approved' || $record->knupda_status === 'Declined')
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="KNUPDA evaluation completed">
                                                                <i data-lucide="handshake" class="w-4 h-4 text-slate-300"></i> KNUPDA Handshake
                                                            </button>
                                                        @else
                                                            <button onclick="copOpenKnupdaModal({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                <i data-lucide="handshake" class="w-4 h-4 text-purple-500"></i> KNUPDA Handshake
                                                            </button>
                                                        @endif
                                                        <div class="py-1">
                                                            @if($record->knupda_status === 'Approved')
                                                                @if(!$record->application_generated_at)
                                                                    <button onclick="copGenerateApplication({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                        <i data-lucide="file-plus" class="w-4 h-4 text-orange-500"></i> Generate Application
                                                                    </button>
                                                                @else
                                                                    <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Application already generated">
                                                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> Generate Application
                                                                    </button>
                                                                @endif

                                                                @if($record->application_generated_at)
                                                                    <a href="{{ route('change-of-purpose.print-application', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                        <i data-lucide="printer" class="w-4 h-4 text-slate-500"></i> Print Application
                                                                    </a>
                                                                @else
                                                                    <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Generate application first">
                                                                        <i data-lucide="printer" class="w-4 h-4 text-slate-300"></i> Print Application
                                                                    </button>
                                                                @endif
                                                            @else
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires KNUPDA Approval">
                                                                    <i data-lucide="file-plus" class="w-4 h-4 text-slate-300"></i> Generate Application
                                                                </button>
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires KNUPDA Approval">
                                                                    <i data-lucide="printer" class="w-4 h-4 text-slate-300"></i> Print Application
                                                                </button>
                                                            @endif
                                                        </div>
                                                        <div class="py-1">
                                                            @if($record->knupda_status === 'Approved')
                                                                @if(!$record->recommendation_generated_at)
                                                                    <button onclick="copGenerateRecommendation({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                        <i data-lucide="file-check" class="w-4 h-4 text-emerald-500"></i> Generate Recommendation
                                                                    </button>
                                                                @else
                                                                    <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Recommendation already generated">
                                                                        <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> Generate Recommendation
                                                                    </button>
                                                                @endif

                                                                @if($record->recommendation_generated_at)
                                                                    <a href="{{ route('change-of-purpose.print-recommendation', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                        <i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i> Print Recommendation
                                                                    </a>
                                                                @else
                                                                    <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Generate recommendation first">
                                                                        <i data-lucide="file-text" class="w-4 h-4 text-slate-300"></i> Print Recommendation
                                                                    </button>
                                                                @endif
                                                            @else
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires KNUPDA Approval">
                                                                    <i data-lucide="file-check" class="w-4 h-4 text-slate-300"></i> Generate Recommendation
                                                                </button>
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires KNUPDA Approval">
                                                                    <i data-lucide="file-text" class="w-4 h-4 text-slate-300"></i> Print Recommendation
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </div>
                                                        <div class="py-1">
                                                        </div>
                                                         @if($record->status !== 'approved' && $record->status !== 'commissioned')
                                                         <div class="py-1 border-t border-slate-100">
                                                             <button onclick="copApproveRecord(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-emerald-600 hover:bg-emerald-50 gap-2 font-medium">
                                                                 <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i> Approve Application
                                                             </button>
                                                             <button onclick="copRejectRecord(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 gap-2 font-medium">
                                                                 <i data-lucide="x-circle" class="w-4 h-4 text-rose-500"></i> Reject Application
                                                             </button>
                                                         </div>
                                                         @endif
                                                         <div class="py-1 border-t border-slate-100">
                                                        @if($record->status === 'approved' || $record->status === 'commissioned')
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Approved or commissioned applications cannot be deleted">
                                                                <i data-lucide="trash-2" class="w-4 h-4 text-slate-300"></i> Delete
                                                            </button>
                                                        @else
                                                            <button onclick="copDelete(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 gap-2">
                                                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                                            </button>
                                                        @endif
                                                        </div>
                                                    </div>
                                                </div>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ============================================
                 TAB 2 - INITIATE CHANGE OF PURPOSE
                 ============================================ --}}
            <div id="cop-tab-initiate" class="cop-tab-panel">
                <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800">
                    <i data-lucide="info" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                    These applications have been <strong>approved</strong>. Use the <strong>Commission</strong> action to update the file number across all related records.
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="cop-approved-table" class="w-full text-sm" style="min-width:1100px">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">Applicant</th>
                                    <th class="px-4 py-3 text-left">Current File No</th>
                                    <th class="px-4 py-3 text-left">Current Land Use</th>
                                    <th class="px-4 py-3 text-left">New Purpose</th>
                                    <th class="px-4 py-3 text-left">Location</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    @if(request('mode') !== 'land')
                                    <th class="px-4 py-3 text-center">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($approvedRecords as $record)
                                    <tr data-record='@json($record)'>
                                        <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $record->id }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->applicant_name }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 font-mono text-xs">{{ $record->file_no ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->land_use ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-xs">
                                            <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded font-semibold">{{ $landUseOptions[strtoupper($record->purpose ?? '')] ?? ($record->purpose ?: '-') }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-slate-500 leading-snug">{{ $record->location ?: '-' }}</td>
                                        <td class="px-4 py-2.5">
                                            @php $sc = match(strtolower((string) $record->status)) {
                                                'approved'     => 'bg-emerald-50 text-emerald-700',
                                                'commissioned' => 'bg-indigo-50 text-indigo-700',
                                                default        => 'bg-slate-50 text-slate-600',
                                            }; @endphp
                                            <span class="px-2 py-1 text-[11px] font-bold rounded {{ $sc }}">{{ ucfirst((string) $record->status) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                                            {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '-' }}
                                        </td>
                                        @if(request('mode') !== 'land')
                                        <td class="px-4 py-2.5 text-center">
                                                <div class="relative dropdown-container">
                                                    <button type="button" class="p-2 hover:bg-slate-100 text-slate-600 rounded-full transition" onclick="copToggleDropdown(this, event)">
                                                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                                    </button>
                                                    <ul class="fixed cop-action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-48">
                                                        <li>
                                                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="copViewApplication(this)">
                                                                <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
                                                                <span>View</span>
                                                            </button>
                                                        </li>
                                                        <li>
                                                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="copViewAcknowledgement(this)">
                                                                <i data-lucide="file-text" class="w-4 h-4 text-slate-500"></i>
                                                                <span>Acknowledgement</span>
                                                            </button>
                                                        </li>
                                                        {{-- <li>
                                                            <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm text-emerald-600 font-semibold" onclick="copOpenCommission(this)">
                                                                <i data-lucide="check-square" class="w-4 h-4 text-emerald-500"></i>
                                                                <span>Commission</span>
                                                            </button>
                                                        </li> --}}
                                                    </ul>
                                                </div>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================================
         NEW APPLICATION MODAL - 3-STEP WIZARD
         ==================================================================== --}}
    <div id="cop-create-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="copCloseCreateModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100">
            {{-- Header --}}
            <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">New Change of Purpose</h3>
                    <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-widest font-semibold">Land Use Change Application</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition" onclick="copCloseCreateModal()">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            {{-- Wizard Progress --}}
            <div class="px-8 pt-6 pb-2 shrink-0">
                <div class="flex items-center justify-between">
                    @foreach([
                        ['step' => 1, 'label' => 'File & Purpose', 'icon' => 'file-text'],
                        ['step' => 2, 'label' => 'Applicant Details', 'icon' => 'user'],
                        ['step' => 3, 'label' => 'Review', 'icon' => 'check-circle'],
                    ] as $idx => $s)
                        @if($idx > 0)
                            <div class="flex-1 h-0.5 mx-2 cop-wizard-connector" data-before-step="{{ $s['step'] }}">
                                <div class="h-full bg-slate-200 rounded transition-colors duration-300 connector-bar"></div>
                            </div>
                        @endif
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-all duration-300
                                {{ $s['step'] === 1 ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 bg-white text-slate-400' }}"
                                 id="cop-wizard-circle-{{ $s['step'] }}">
                                <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[11px] font-semibold mt-1.5 transition-colors duration-300
                                {{ $s['step'] === 1 ? 'text-blue-600' : 'text-slate-400' }}"
                                  id="cop-wizard-label-{{ $s['step'] }}">{{ $s['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Form Body --}}
            <form id="cop-form" class="flex-1 overflow-y-auto">
                @csrf

                {{-- -- Step 1: File & Purpose -- --}}
                <div class="cop-step px-8 py-6 space-y-6" data-step="1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {{-- File Number selector --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Current File Number <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="text" id="cop-file-no" name="file_no" readonly
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                                        placeholder="Select file...">
                                    <div id="cop-file-indicator" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                        <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
                                    </div>
                                </div>
                                <button type="button" id="cop-select-file-btn"
                                    class="p-3 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition shrink-0" title="Look up file number">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                        {{-- Current Land Use (auto-filled) --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Current Land Use</label>
                            <input id="cop-land-use" name="land_use" type="text" readonly
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-500"
                                placeholder="Auto-detected">
                        </div>
                    </div>

                    {{-- Hidden field keeps the prefix code (RES/COM/IND) used for file-number generation --}}
                    <input type="hidden" name="purpose" id="cop-purpose">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">New Land Use <span class="text-red-500">*</span></label>
                            <select id="cop-land-use-id"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium">
                                <option value="">- Select New Land Use -</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">New Purpose <span class="text-red-500">*</span></label>
                            <select id="cop-new-purpose" name="new_purpose"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium"
                                disabled>
                                <option value="">- Select Land Use First -</option>
                            </select>
                        </div>
                    </div>

                    {{-- File Details (populated after file selection) --}}
                    <div id="cop-file-details" class="hidden bg-blue-50/60 border border-blue-200 rounded-xl p-4 text-sm">
                        <p class="font-semibold text-blue-700 mb-2 text-xs uppercase tracking-wider">File Details</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs text-blue-800">
                            <div><strong>File Name:</strong><br><span id="cop-det-filename">-</span></div>
                            <div><strong>Location:</strong><br><span id="cop-det-location">-</span></div>
                            <div><strong>LGA:</strong><br><span id="cop-det-lga">-</span></div>
                            <div><strong>Source:</strong><br><span id="cop-det-source">-</span></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot No</label>
                            <input id="cop-plot-no" name="plot_no" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm cop-prefill"
                                placeholder="e.g. 123">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan No</label>
                            <input id="cop-plan-no" name="plan_no" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. KN/123">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Size (Sqm) <span class="text-red-500">*</span></label>
                            <input id="cop-land-size" name="land_size" type="number" step="0.01"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. 500.00" oninput="calculateCopFee()">
                        </div>
                        <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100 flex flex-col justify-center">
                            <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (₦50/sqm)</label>
                            <input type="text" id="cop-knupda-fee-display" class="w-full bg-transparent border-none text-sm font-bold text-blue-700 p-0" value="₦0.00" readonly>
                            <input type="hidden" name="knupda_fee" id="cop-knupda-fee-hidden" value="0">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Street</label>
                            <select id="cop-street" class="searchable-select w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm">
                                <option value="">- Select Street -</option>
                                @foreach($streetNames as $street)
                                    <option value="{{ $street->name }}">{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
                                @endforeach
                                <option value="Other">OTHER</option>
                            </select>
                            <input id="cop-street-other" type="text"
                                class="w-full px-4 py-3 mt-2 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm hidden"
                                placeholder="Type street name">
                            {{-- Hidden: full location details (street, district, LGA, state) --}}
                            <input id="cop-location" name="location" type="hidden">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District</label>
                            <select id="cop-district" name="district" class="searchable-select w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm">
                                <option value="">- Select District -</option>
                                @foreach($districts as $district)
                                    <option value="{{ $district->name }}">{{ strtoupper($district->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA</label>
                            <select id="cop-lga" name="lga" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm">
                                <option value="">- Select LGA -</option>
                                @foreach($lgas as $lga)
                                    <option value="{{ $lga->name }}">{{ strtoupper($lga->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State</label>
                            <select id="cop-state" name="state" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm">
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ strtoupper($state->StateName) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="md:col-span-2 flex items-center">
                            <div class="w-full bg-slate-100/50 p-4 rounded-xl border border-dashed border-slate-300">
                                <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Full Property Location Preview</p>
                                <p id="cop-location-preview" class="text-xs font-bold text-slate-700 italic">No location details entered yet.</p>
                            </div>
                        </div>
                    </div>


                </div>

                {{-- -- Step 2: Applicant Details -- --}}
                <div class="cop-step hidden px-8 py-6 space-y-6" data-step="2">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
                            <i data-lucide="user" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Applicant Information</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Full Name <span class="text-red-500">*</span></label>
                            <input id="cop-applicant-name" name="applicant_name" type="text" required readonly
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-gray-100 text-sm text-slate-500 cursor-not-allowed"
                                placeholder="Auto-filled from file record">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone</label>
                            <input id="cop-phone" name="phone" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. 08012345678">
                        </div>
                    </div>

                    {{-- Residential Address Builder --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Residential Address</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-green-50/60 border border-green-200">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">House / Plot No</label>
                                <input type="text" id="cop_res_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g. 12A">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                <select id="cop_res_addr_street" class="searchable-select w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT STREET</option>
                                    @foreach($streetNames as $street)
                                        <option value="{{ $street->name }}">{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
                                    @endforeach
                                    <option value="Other">OTHER</option>
                                </select>
                            </div>
                            <div id="cop-res-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                <input type="text" id="cop_res_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type street name">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                <select id="cop_res_addr_district" class="searchable-select w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT DISTRICT</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ \Illuminate\Support\Str::upper((string) $district->name) }}</option>
                                    @endforeach
                                    <option value="Other">OTHER</option>
                                </select>
                            </div>
                            <div id="cop-res-addr-district-other-wrapper" class="hidden">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                <input type="text" id="cop_res_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type district name">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                <select id="cop_res_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                <select id="cop_res_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT STATE</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <textarea id="cop-residential-address" name="residential_address" rows="2" readonly
                            placeholder="Address will be built automatically..."
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                    </div>
                </div>

                {{-- -- Step 3: Review & Submit -- --}}
                <div class="cop-step hidden px-8 py-6 space-y-5" data-step="3">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Review &amp; Submit</h4>
                    </div>

                    {{-- Info Banner --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
                        <i data-lucide="info" class="h-4 w-4 inline-block mr-1 -mt-0.5"></i>
                        This application will enter an <strong>approval queue</strong>. Commission happens after approval.
                    </div>

                    {{-- Summary Details --}}
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                        <p class="font-semibold text-slate-700 mb-2 text-xs uppercase tracking-wider">Application Summary</p>
                        <div id="cop-review-summary" class="space-y-1 text-xs text-slate-600"></div>
                    </div>

                    {{-- Comment --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Comment</label>
                        <textarea id="cop-comment" name="comment" rows="2"
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm resize-none"
                            placeholder="Optional comment..."></textarea>
                    </div>

                    {{-- Site Plan Upload --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Upload Site Plan</label>
                        <input type="file" id="cop-site-plan" name="site_plan" accept=".pdf,.png,.jpg,.jpeg" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white">
                        <p class="text-[10px] text-slate-400 mt-1 italic">Accepted formats: PDF, PNG, JPG</p>
                        <input type="hidden" id="cop-knupda-fee-hidden" name="knupda_fee" value="0">
                    </div>
                </div>
            </form>

            {{-- Footer --}}
            <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between bg-white shrink-0">
                <button type="button" id="cop-prev-btn" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm disabled:opacity-40" onclick="copPrevStep()" disabled>Previous</button>
                <div class="flex items-center gap-2">
                    <button type="button" id="cop-next-btn" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm" onclick="copNextStep()">Next</button>
                    <button type="button" id="cop-save-btn" class="hidden px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold" onclick="copSubmitForm()">Submit Application</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================================
         VIEW APPLICATION MODAL
         ==================================================================== --}}
    <div id="cop-view-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="this.closest('#cop-view-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
            <div class="px-7 py-5 border-b border-slate-100 bg-gradient-to-r from-indigo-600 to-blue-500 shrink-0">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                            <i data-lucide="repeat" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Application Details</h3>
                            <p id="cop-view-subtitle" class="text-xs text-indigo-100 mt-0.5"></p>
                        </div>
                    </div>
                    <button type="button" class="text-white/70 hover:text-white p-1.5 hover:bg-white/10 rounded-lg" onclick="document.getElementById('cop-view-modal').classList.add('hidden')">
                        <i data-lucide="x" class="h-5 w-5"></i>
                    </button>
                </div>
            </div>
            <div id="cop-view-body" class="flex-1 overflow-y-auto px-7 py-5 space-y-4 text-sm"></div>
            <div class="px-7 py-3 border-t border-slate-100 bg-slate-50 shrink-0 flex justify-end">
                <button type="button" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 bg-white border border-slate-200 rounded-lg hover:bg-slate-50" onclick="document.getElementById('cop-view-modal').classList.add('hidden')">Close</button>
            </div>
        </div>
    </div>

    {{-- Global File Number Modal --}}
    @include('components.global-fileno-modal')

    {{-- Shared Commission File Number Modal --}}
    @include('components.commission-fileno-modal-include')

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
@include('components.searchable-select2')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/change-of-purpose.js') }}"></script>
<script>
    function calculateCopFee() {
        const size = parseFloat(document.getElementById('cop-land-size').value) || 0;
        const fee = size * 50;
        const display = document.getElementById('cop-knupda-fee-display');
        if (display) {
            display.value = '₦' + fee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        const hidden = document.getElementById('cop-knupda-fee-hidden');
        if (hidden) {
            hidden.value = fee;
        }
    }
</script>
@endsection

