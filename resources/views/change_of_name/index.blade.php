@extends('layouts.app')

@section('styles')
<style>
    /* -- DataTable overrides -- */
    #con-pending-table_wrapper .dataTables_filter,
    #con-pending-table_wrapper .dataTables_length,
    #con-approved-table_wrapper .dataTables_filter,
    #con-approved-table_wrapper .dataTables_length { display: none; }

    #con-pending-table_wrapper .dataTables_info,
    #con-approved-table_wrapper .dataTables_info {
        font-size: 0.8125rem; color: rgb(100 116 139); padding: 0.75rem 1rem;
    }
    #con-pending-table_wrapper .dataTables_paginate,
    #con-approved-table_wrapper .dataTables_paginate {
        padding: 0.5rem 1rem 0.75rem;
    }
    #con-pending-table thead th,
    #con-approved-table thead th {
        white-space: nowrap; vertical-align: middle; border-bottom: 2px solid rgb(226 232 240);
    }
    #con-pending-table tbody td,
    #con-approved-table tbody td {
        white-space: nowrap; vertical-align: middle; border-bottom: 1px solid rgb(241 245 249);
    }
    #con-pending-table  tbody td:nth-child(2),
    #con-pending-table  tbody td:nth-child(5),
    #con-approved-table tbody td:nth-child(2),
    #con-approved-table tbody td:nth-child(4) { white-space: normal; min-width: 160px; max-width: 240px; }

    /* -- Tab styles -- */
    .con-tab-btn.active { background: white; color: rgb(37 99 235); box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .con-tab-panel { display: none; }
    .con-tab-panel.active { display: block; }

    /* -- Modals -- */
    .swal2-container { z-index: 20000 !important; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Change of Name',
        'PageDescription' => 'Manage file name/owner change applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- -- Page Heading -- --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                     <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Change of Name</h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="con-search" placeholder="Search records..."
                            class="w-64 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="con-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $opt)
                            <option value="{{ $opt }}" @selected($limit == $opt)>{{ $opt }} rows</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="conOpenCreate()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i> New Application
                    </button>
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
                        <p class="text-[10px] text-slate-400 mt-1">Name updated</p>
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
                <button class="con-tab-btn active px-5 py-2 rounded-xl text-sm font-semibold text-slate-600 transition" onclick="conSwitchTab('applications', this)">
                    <i data-lucide="file-text" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                    Applications <span class="ml-1 bg-amber-100 text-amber-700 text-[10px] px-1.5 py-0.5 rounded-full font-bold">{{ $pendingRecords->count() }}</span>
                </button>
                <button class="con-tab-btn px-5 py-2 rounded-xl text-sm font-semibold text-slate-600 transition" onclick="conSwitchTab('initiate', this)">
                    <i data-lucide="check-circle" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                    Initiate Change <span class="ml-1 bg-emerald-100 text-emerald-700 text-[10px] px-1.5 py-0.5 rounded-full font-bold">{{ $approvedRecords->count() }}</span>
                </button>
            </div>

            {{-- ============================================
                 TAB 1 - APPLICATIONS
                 ============================================ --}}
            <div id="con-tab-applications" class="con-tab-panel active">
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="con-pending-table" class="w-full text-sm" style="min-width:1000px">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">Old Name</th>
                                    <th class="px-4 py-3 text-left">File No</th>
                                    <th class="px-4 py-3 text-left">Title</th>
                                    <th class="px-4 py-3 text-left">New Name</th>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pendingRecords as $record)
                                    <tr data-record='@json($record)'>
                                        <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $record->id }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->current_name ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 font-mono text-xs">{{ $record->file_no ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->title ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 text-xs">{{ $record->new_full_name ?: '-' }}</td>
                                        <td class="px-4 py-2.5">
                                            @php $sc = match($record->status) {
                                                'approved'   => 'bg-emerald-50 text-emerald-700',
                                                'pending'    => 'bg-amber-50 text-amber-700',
                                                'rejected'   => 'bg-red-50 text-red-700',
                                                'processing' => 'bg-blue-50 text-blue-700',
                                                default      => 'bg-slate-50 text-slate-600',
                                            }; @endphp
                                            <span class="px-2 py-1 text-[11px] font-bold rounded {{ $sc }}">{{ ucfirst($record->status) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                                            {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '-' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <div class="relative dropdown-container">
                                                <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full" onclick="conToggleDropdown(this, event)">
                                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                </button>
                                                <ul class="fixed con-action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-48">
                                                    <li>
                                                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="conViewApplication(this)">
                                                            <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
                                                            <span>View</span>
                                                        </button>
                                                    </li>
                                                    @if($record->status === 'pending' || $record->status === 'processing')
                                                    <li>
                                                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="conApproveRecord(this)">
                                                            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i>
                                                            <span>Approve</span>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="conRejectRecord(this)">
                                                            <i data-lucide="x-circle" class="w-4 h-4 text-orange-500"></i>
                                                            <span>Reject</span>
                                                        </button>
                                                    </li>
                                                    @endif
                                                    <li class="border-t border-gray-100">
                                                        @if($record->status === 'approved')
                                                            <button disabled class="block w-full text-left px-4 py-2 opacity-50 cursor-not-allowed flex items-center space-x-2 text-sm text-slate-400" title="Approved applications cannot be deleted">
                                                                <i data-lucide="trash-2" class="w-4 h-4 text-slate-300"></i>
                                                                <span>Delete</span>
                                                            </button>
                                                        @else
                                                            <button class="block w-full text-left px-4 py-2 hover:bg-red-50 flex items-center space-x-2 text-sm text-red-600" onclick="conDelete(this)">
                                                                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i>
                                                                <span>Delete</span>
                                                            </button>
                                                        @endif
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ============================================
                 TAB 2 - INITIATE CHANGE OF NAME
                 ============================================ --}}
            <div id="con-tab-initiate" class="con-tab-panel">
                <div class="mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800">
                    <i data-lucide="info" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                    These applications have been <strong>approved</strong>. The file name has been updated across all related records. Use <strong>Acknowledgement</strong> to print.
                </div>
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table id="con-approved-table" class="w-full text-sm" style="min-width:1100px">
                            <thead>
                                <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left">Old Name</th>
                                    <th class="px-4 py-3 text-left">File No</th>
                                    <th class="px-4 py-3 text-left">New Name</th>
                                    <th class="px-4 py-3 text-left">Location</th>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($approvedRecords as $record)
                                    <tr data-record='@json($record)'>
                                        <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $record->id }}</td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->current_name ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-slate-700 font-mono text-xs">{{ $record->file_no ?: '-' }}</td>
                                        <td class="px-4 py-2.5 text-xs">
                                            <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded font-semibold">{{ $record->new_full_name ?: '-' }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-slate-500 leading-snug">{{ $record->location ?: '-' }}</td>
                                        <td class="px-4 py-2.5 font-mono text-xs text-slate-500">
                                            {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '-' }}
                                        </td>
                                        <td class="px-4 py-2.5 text-center">
                                            <div class="relative dropdown-container">
                                                <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full" onclick="conToggleDropdown(this, event)">
                                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                </button>
                                                <ul class="fixed con-action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-48">
                                                    <li>
                                                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="conViewApplication(this)">
                                                            <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
                                                            <span>View</span>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2 text-sm" onclick="conViewAcknowledgement(this)">
                                                            <i data-lucide="file-text" class="w-4 h-4 text-slate-500"></i>
                                                            <span>Acknowledgement</span>
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
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
    <div id="con-create-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="conCloseCreateModal()"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-100">
            {{-- Header --}}
            <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">New Change of Name</h3>
                    <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-widest font-semibold">File Owner Name Change Application</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition" onclick="conCloseCreateModal()">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            {{-- Wizard Progress --}}
            <div class="px-8 pt-6 pb-2 shrink-0">
                <div class="flex items-center justify-between">
                    @foreach([
                        ['step' => 1, 'label' => 'File Selection', 'icon' => 'file-text'],
                        ['step' => 2, 'label' => 'New Name & Details', 'icon' => 'user'],
                        ['step' => 3, 'label' => 'Review', 'icon' => 'check-circle'],
                    ] as $idx => $s)
                        @if($idx > 0)
                            <div class="flex-1 h-0.5 mx-2 con-wizard-connector" data-before-step="{{ $s['step'] }}">
                                <div class="h-full bg-slate-200 rounded transition-colors duration-300 connector-bar"></div>
                            </div>
                        @endif
                        <div class="flex flex-col items-center shrink-0">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center border-2 transition-all duration-300
                                {{ $s['step'] === 1 ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 bg-white text-slate-400' }}"
                                 id="con-wizard-circle-{{ $s['step'] }}">
                                <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
                            </div>
                            <span class="text-[11px] font-semibold mt-1.5 transition-colors duration-300
                                {{ $s['step'] === 1 ? 'text-blue-600' : 'text-slate-400' }}"
                                  id="con-wizard-label-{{ $s['step'] }}">{{ $s['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Form Body --}}
            <form id="con-form" class="flex-1 overflow-y-auto">
                @csrf

                {{-- -- Step 1: File Selection -- --}}
                <div class="con-step px-8 py-6 space-y-6" data-step="1">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        {{-- File Number selector --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">File Number <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="text" id="con-file-no" name="file_no" readonly
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                                        placeholder="Select file...">
                                    <div id="con-file-indicator" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                        <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
                                    </div>
                                </div>
                                <button type="button" id="con-select-file-btn"
                                    class="p-3 rounded-xl bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-100 transition shrink-0" title="Look up file number">
                                    <i data-lucide="search" class="h-4 w-4"></i>
                                </button>
                            </div>
                        </div>
                        {{-- Current Name (auto-filled) --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Current Name on File</label>
                            <input id="con-current-name" name="current_name" type="text" readonly
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-gray-100 text-sm text-slate-500 cursor-not-allowed"
                                placeholder="Auto-filled from file record">
                        </div>
                    </div>

                    {{-- File Details (populated after file selection) --}}
                    <div id="con-file-details" class="hidden bg-blue-50/60 border border-blue-200 rounded-xl p-4 text-sm">
                        <p class="font-semibold text-blue-700 mb-2 text-xs uppercase tracking-wider">File Details</p>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs text-blue-800">
                            <div><strong>File Name:</strong><br><span id="con-det-filename">-</span></div>
                            <div><strong>Location:</strong><br><span id="con-det-location">-</span></div>
                            <div><strong>LGA:</strong><br><span id="con-det-lga">-</span></div>
                            <div><strong>Source:</strong><br><span id="con-det-source">-</span></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot No</label>
                            <input id="con-plot-no" name="plot_no" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm con-prefill"
                                placeholder="e.g. 123">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan No</label>
                            <input id="con-plan-no" name="plan_no" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. KN/123">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Location</label>
                            <input id="con-location" name="location" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm con-prefill"
                                placeholder="e.g. Nassarawa GRA">
                        </div>
                    </div>
                </div>

                {{-- -- Step 2: New Name & Applicant Details -- --}}
                <div class="con-step hidden px-8 py-6 space-y-6" data-step="2">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600 shrink-0">
                            <i data-lucide="user" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">New Name Details</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Title</label>
                            <select id="con-title" name="title"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium">
                                <option value="">- Select Title -</option>
                                @foreach($titleOptions as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">First Name <span class="text-red-500">*</span></label>
                            <input id="con-first-name" name="first_name" type="text" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. Musa">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Middle Name</label>
                            <input id="con-middle-name" name="middle_name" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. Abdullahi">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Last Name <span class="text-red-500">*</span></label>
                            <input id="con-last-name" name="last_name" type="text" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. Sani">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">New Full Name (Auto-composed)</label>
                        <input id="con-new-full-name" name="new_full_name" type="text" readonly
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-gray-100 text-sm text-slate-500 cursor-not-allowed font-bold"
                            placeholder="Will be composed from Title + First + Middle + Last">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone</label>
                            <input id="con-phone" name="phone" type="text"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm"
                                placeholder="e.g. 08012345678">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Use</label>
                            <input id="con-land-use" name="land_use" type="text" readonly
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-gray-100 text-sm text-slate-400 cursor-not-allowed"
                                placeholder="Auto-detected">
                        </div>
                    </div>

                    {{-- Residential Address Builder --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Residential Address</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-green-50/60 border border-green-200">
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                <input type="text" id="con_res_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g. 12A">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                <input type="text" id="con_res_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g. 45">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                <select id="con_res_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT STREET</option>
                                    @foreach($streetNames as $street)
                                        <option value="{{ $street->name }}">{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
                                    @endforeach
                                    <option value="Other">OTHER</option>
                                </select>
                            </div>
                            <div id="con-res-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                <input type="text" id="con_res_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type street name">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                <select id="con_res_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT DISTRICT</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ \Illuminate\Support\Str::upper((string) $district->name) }}</option>
                                    @endforeach
                                    <option value="Other">OTHER</option>
                                </select>
                            </div>
                            <div id="con-res-addr-district-other-wrapper" class="hidden">
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                <input type="text" id="con_res_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type district name">
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                <select id="con_res_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                <select id="con_res_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                    <option value="">SELECT STATE</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <textarea id="con-residential-address" name="residential_address" rows="2" readonly
                            placeholder="Address will be built automatically..."
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                    </div>
                </div>

                {{-- -- Step 3: Review & Submit -- --}}
                <div class="con-step hidden px-8 py-6 space-y-5" data-step="3">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600 shrink-0">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Review &amp; Submit</h4>
                    </div>

                    {{-- Info Banner --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800">
                        <i data-lucide="info" class="h-4 w-4 inline-block mr-1 -mt-0.5"></i>
                        This application will enter an <strong>approval queue</strong>. Once approved, the file name will be updated across all related records.
                    </div>

                    {{-- Summary Details --}}
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm">
                        <p class="font-semibold text-slate-700 mb-2 text-xs uppercase tracking-wider">Application Summary</p>
                        <div id="con-review-summary" class="space-y-1 text-xs text-slate-600"></div>
                    </div>

                    {{-- Comment --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Comment <span class="text-red-500">*</span></label>
                        <textarea id="con-comment" name="comment" rows="2" required
                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm resize-none"
                            placeholder="Reason for name change..."></textarea>
                    </div>
                </div>
            </form>

            {{-- Footer --}}
            <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between bg-white shrink-0">
                <button type="button" id="con-prev-btn" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm disabled:opacity-40" onclick="conPrevStep()" disabled>Previous</button>
                <div class="flex items-center gap-2">
                    <button type="button" id="con-next-btn" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm" onclick="conNextStep()">Next</button>
                    <button type="button" id="con-save-btn" class="hidden px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold" onclick="conSubmitForm()">Submit Application</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ====================================================================
         VIEW APPLICATION MODAL
         ==================================================================== --}}
    <div id="con-view-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="this.closest('#con-view-modal').classList.add('hidden')"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
            <div class="px-7 py-5 border-b border-slate-100 flex items-center justify-between bg-gradient-to-r from-slate-50 to-white shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i data-lucide="user" class="w-5 h-5 text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Application Details</h3>
                        <p id="con-view-subtitle" class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold mt-0.5"></p>
                    </div>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1.5 hover:bg-slate-100 rounded-xl transition" onclick="document.getElementById('con-view-modal').classList.add('hidden')">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
            <div id="con-view-body" class="flex-1 overflow-y-auto px-7 py-5 space-y-5 text-sm"></div>
            <div class="px-7 py-4 border-t border-slate-100 bg-slate-50/60 flex justify-end shrink-0">
                <button type="button" class="px-5 py-2 rounded-xl bg-slate-800 text-white text-sm font-semibold hover:bg-slate-700 transition" onclick="document.getElementById('con-view-modal').classList.add('hidden')">Close</button>
            </div>
        </div>
    </div>

    {{-- Global File Number Modal --}}
    @include('components.global-fileno-modal')

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/change-of-name.js') }}"></script>
@endsection
