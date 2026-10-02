@extends('layouts.app')

@php
    /**
     * The planning clearance, resolved for the whole page in two queries.
     *
     * It used to be the KAMMA/Physical Planning Handshake on this menu. It is now
     * an approved Master JSI, captured by Physical Planning on their own register.
     * MasterJsiGate answers the question, so a record already cleared through the
     * old handshake still passes and does not deadlock behind a modal that is gone.
     *
     * Asked per PAGE, not per row: each row asks two or three times over, which as
     * a per-row query would be the slowest thing on the listing.
     */
    $jsiCleared = \App\Support\MasterJsiGate::clearedMap('extension', $records);
@endphp


@section('styles')
<style>
    #plot-extension-table_wrapper .dataTables_filter,
    #plot-extension-table_wrapper .dataTables_length { display: none; }

    #plot-extension-table_wrapper .dataTables_info {
        font-size: 0.8125rem;
        color: rgb(100 116 139);
        padding: 0.75rem 1rem;
    }

    #plot-extension-table_wrapper .dataTables_paginate {
        padding: 0.5rem 1rem 0.75rem;
    }

    #plot-extension-table thead th {
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 2px solid rgb(226 232 240);
    }

    #plot-extension-table tbody td {
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 1px solid rgb(241 245 249);
    }

    #plot-extension-table tbody td:nth-child(2),
    #plot-extension-table tbody td:nth-child(6) {
        white-space: normal;
        min-width: 180px;
        max-width: 260px;
    }

    /* SweetAlert z-index fix — keep above modal */
    .swal2-container { z-index: 20000 !important; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Plot Extension Applications',
        'PageDescription' => 'Capture and manage Plot Extension applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Page Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }}</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Plot Extension</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->count()) }} record(s) loaded</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="pe-search" placeholder="Search applications..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="pe-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $option)
                            <option value="{{ $option }}" @selected($limit == $option)>{{ $option }} rows</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="peOpenCreate()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Plot Extension
                    </button>
                </div>
            </div>

            {{-- Data Table --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="plot-extension-table" class="w-full text-sm" style="min-width:1200px">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Applicant</th>
                                <th class="px-4 py-3 text-left">R of Occupancy</th>
                                <th class="px-4 py-3 text-left">Existing Land Use</th>
                                <th class="px-4 py-3 text-left">Plot No</th>
                                <th class="px-4 py-3 text-left">Location</th>
                                <th class="px-4 py-3 text-left">Occupation</th>
                                <th class="px-4 py-3 text-left">Source</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                <tr data-record='@json($record)'>
                                    <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $record->id }}</td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->applicant_name }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->file_no ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->land_use ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->plot_no ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs leading-snug">
                                        {{ implode(', ', array_filter([
                                            $record->plot_no ? 'Plot '.$record->plot_no : null,
                                            $record->location,
                                            $record->district,
                                            $record->lga,
                                            $record->state
                                        ])) }}
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->occupation ?: '—' }}</td>
                                    {{-- Which sidebar raised it: both open this same register. --}}
                                    <td class="px-4 py-2.5">
                                        <span class="px-2 py-0.5 rounded-lg border text-[11px] font-bold uppercase {{ \App\Support\ParcelUpdateSource::chipClass($record->source_module) }}">
                                            {{ \App\Support\ParcelUpdateSource::label($record->source_module) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600">
                                        {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                            <div class="relative inline-block text-left" id="pe-dropdown-{{ $record->id }}">
                                                <button type="button" onclick="togglePeDropdown({{ $record->id }})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-full transition">
                                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                                </button>
                                                
                                                <div id="pe-menu-{{ $record->id }}" class="hidden fixed z-[999] mt-2 w-max origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none divide-y divide-slate-100 whitespace-nowrap">
                                                    <div class="py-1">
                                                        <button onclick="peView(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                            <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i> View Details
                                                        </button>
                                                    </div>
                                                    <div class="py-1 border-t border-slate-100">
                                                        @if(strcasecmp(trim((string) ($record->knupda_status ?? '')), 'Approved') === 0)
                                                        <button disabled title="Already approved by KAMMA / Physical Planning" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50"><i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> KAMMA / Physical Planning</button>
                                                        @else
                                                        <button onclick="peOpenKnupdaModal({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50 gap-2">
                                                            <i data-lucide="ruler" class="w-4 h-4 text-blue-500"></i> KAMMA / Physical Planning
                                                        </button>
                                                        @endif
                                                    </div>
                                                    <div class="py-1">
                                                        @if(($jsiCleared[$record->id] ?? false))
                                                            @if(!$record->recommendation_generated_at)
                                                                <button onclick="peGenerateRecommendation({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                    <i data-lucide="file-check" class="w-4 h-4 text-emerald-500"></i> Generate Recommendation
                                                                </button>
                                                            @else
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Recommendation already generated">
                                                                    <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> Generate Recommendation
                                                                </button>
                                                            @endif

                                                            @if($record->recommendation_generated_at)
                                                                <a href="{{ route('plot-extension.print-recommendation', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                    <i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i> Print Recommendation
                                                                </a>
                                                            @else
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Generate recommendation first">
                                                                    <i data-lucide="file-text" class="w-4 h-4 text-slate-300"></i> Print Recommendation
                                                                </button>
                                                            @endif
                                                        @else
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires an approved Master JSI">
                                                                <i data-lucide="file-check" class="w-4 h-4 text-slate-300"></i> Generate Recommendation
                                                            </button>
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires an approved Master JSI">
                                                                <i data-lucide="file-text" class="w-4 h-4 text-slate-300"></i> Print Recommendation
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <div class="py-1">
                                                        <button onclick="peEdit(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-amber-700 hover:bg-amber-50 gap-2">
                                                            <i data-lucide="edit" class="w-4 h-4"></i> Edit
                                                        </button>
                                                    </div>
                                                    <div class="py-1">
                                                        {{-- Approve sits BELOW the recommendation and above the
                                                             application, for the same reason as the other parcel-update
                                                             pages: it is given on the strength of the recommendation
                                                             memo. peApprove() and peReject() both already existed - they
                                                             had simply never been put on the menu. --}}
                                                        @if(in_array($record->status, ['approved', 'rejected', 'commissioned'], true))
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Already approved{{ ($jsiCleared[$record->id] ?? false) ? ' via the Master JSI' : '' }}">
                                                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300"></i> Approve / Reject
                                                            </button>
                                                        @else
                                                            <button onclick="peOpenApprovalDecision({{ $record->id }}, {{ ($jsiCleared[$record->id] ?? false) ? 'true' : 'false' }})" class="flex items-center w-full px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 gap-2 font-bold">
                                                                <i data-lucide="scale" class="w-4 h-4 text-emerald-500"></i> Approve / Reject
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <div class="py-1">
                                                        @if(($jsiCleared[$record->id] ?? false))
                                                            @if(!$record->application_generated_at)
                                                                <button onclick="peGenerateApplication({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                    <i data-lucide="file-plus" class="w-4 h-4 text-orange-500"></i> Generate Application
                                                                </button>
                                                            @else
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Application already generated">
                                                                    <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> Generate Application
                                                                </button>
                                                            @endif

                                                            @if($record->application_generated_at)
                                                                <a href="{{ route('plot-extension.print-application', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                    <i data-lucide="printer" class="w-4 h-4 text-slate-500"></i> Print Application
                                                                </a>
                                                            @else
                                                                <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Generate application first">
                                                                    <i data-lucide="printer" class="w-4 h-4 text-slate-300"></i> Print Application
                                                                </button>
                                                            @endif
                                                        @else
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires an approved Master JSI">
                                                                <i data-lucide="file-plus" class="w-4 h-4 text-slate-300"></i> Generate Application
                                                            </button>
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed" title="Requires an approved Master JSI">
                                                                <i data-lucide="printer" class="w-4 h-4 text-slate-300"></i> Print Application
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <div class="py-1">
                                                        <button onclick="peDelete(this)" class="flex items-center w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 gap-2">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                    <td class="px-4 py-2.5"></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         WIZARD MODAL
         ═══════════════════════════════════════════════════════ --}}
    <div id="pe-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
            {{-- Header --}}
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div>
                    <h3 id="pe-modal-title" class="text-xl font-bold text-slate-900">New Plot Extension Application</h3>
                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">File Selection &amp; Applicant Details</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition" onclick="peCloseModal()">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            {{-- Form Body --}}
            <form id="pe-form" class="flex-1 overflow-y-auto">
                @csrf
                <input type="hidden" id="pe-id">

                <div class="px-8 py-8 space-y-8">


                    {{-- ═══════ SINGLE STEP FORM ═══════ --}}
                    <div class="pe-step" data-step="1">
                        <div class="space-y-8">
                            {{-- File & Applicant Info --}}
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                                        <i data-lucide="file-text" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">File &amp; Applicant Info</h4>
                                </div>
    
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Right of Occupancy (File No) <span class="text-red-500">*</span></label>
                                        <div class="flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <input type="text" id="pe-file-no" name="file_no" readonly
                                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                                                    placeholder="Select file...">
                                                <div id="pe-file-indicator" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                                    <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
                                                </div>
                                            </div>
                                            <button type="button" id="pe-select-file-btn"
                                                class="p-3 rounded-xl bg-blue-50 text-blue-600 font-bold border border-blue-100 hover:bg-blue-100 transition flex items-center justify-center shrink-0"
                                                title="Select File Number">
                                                <i data-lucide="search" class="h-5 w-5"></i>
                                            </button>
                                        </div>
                                    </div>
    
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Full Name <span class="text-red-500">*</span></label>
                                        <input id="pe-applicant-name" name="applicant_name" type="text" required
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                            placeholder="e.g. Musa Ali">
                                    </div>

                                    <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100 flex items-center justify-between">
                                        <div>
                                            <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (Fixed)</label>
                                            <p class="text-lg font-black text-blue-700">₦10,000.00</p>
                                        </div>
                                        <i data-lucide="calculator" class="w-6 h-6 text-blue-200"></i>
                                        <input type="hidden" name="knupda_fee" value="10000">
                                    </div>
                                </div>
    
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Occupation</label>
                                        <input id="pe-occupation" name="occupation" type="text"
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                            placeholder="e.g. Civil Servant">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone</label>
                                        <input id="pe-phone" name="phone" type="text"
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                            placeholder="e.g. 08012345678">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Email</label>
                                        <input id="pe-email" name="email" type="email"
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                            placeholder="e.g. musa@example.com">
                                    </div>
                                </div>
                            </div>

                            {{-- Property Details --}}
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                        <i data-lucide="map" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Property Details</h4>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Existing Land Use</label>
                                        <input id="pe-land-use" name="land_use" type="text"
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                            placeholder="e.g. Residential">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan No</label>
                                        <input id="pe-plan-no" name="plan_no" type="text"
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                            placeholder="e.g. KN/123">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Plot No</label>
                                        <input type="text" id="pe-plot-no" name="plot_no" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20" placeholder="e.g. 123">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">House No</label>
                                        <input type="text" id="pe-house-no" name="house_no" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20" placeholder="e.g. 322">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Street Name</label>
                                        <select id="pe-street" name="street_name" class="searchable-select w-full px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20">
                                            <option value="">SELECT STREET</option>
                                            @foreach($streetNames as $street)
                                                <option value="{{ $street->name }}">{{ strtoupper($street->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">District</label>
                                        <select id="pe-district" name="district" class="searchable-select w-full px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20">
                                            <option value="">SELECT DISTRICT</option>
                                            @foreach($districts as $district)
                                                <option value="{{ $district->name }}">{{ strtoupper($district->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">LGA</label>
                                        <select id="pe-lga" name="lga" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20">
                                            <option value="">SELECT LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ strtoupper($lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">State</label>
                                        <select id="pe-state" name="state" class="w-full px-3 py-2 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500/20">
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ strtoupper($state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-full">
                                        <div class="bg-white p-3 rounded-lg border border-dashed border-slate-200">
                                            <p class="text-[9px] font-bold text-slate-400 uppercase mb-1">Location Preview</p>
                                            <p id="pe-location-preview" class="text-xs font-bold text-slate-600 italic">No location details entered yet.</p>
                                            <input type="hidden" id="pe-location" name="location">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Notes & Site Plan --}}
                            <div class="space-y-4">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                        <i data-lucide="check-circle" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Final Details</h4>
                                </div>
    

    
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application Plan (Site Plan)</label>
                                    <div class="flex flex-col gap-4">
                                        <input type="file" name="site_plan" id="pe-site-plan-input" accept=".pdf,.png,.jpg,.jpeg"
                                            class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                            onchange="pePreviewSitePlan(this)">

                                        <div id="pe-site-plan-preview-container" class="hidden relative w-full aspect-video rounded-2xl overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center group">
                                            <img id="pe-site-plan-preview-img" src="#" alt="Site Plan Preview" class="max-h-full max-w-full object-contain">
                                            <div id="pe-pdf-preview-placeholder" class="hidden flex-col items-center gap-2 text-slate-400">
                                                <i data-lucide="file-text" class="w-12 h-12"></i>
                                                <span class="text-xs font-bold uppercase tracking-wider">PDF Selected</span>
                                            </div>
                                            <button type="button" onclick="peClearSitePlan()" class="absolute top-4 right-4 p-2 bg-red-100 text-red-600 rounded-full opacity-0 group-hover:opacity-100 transition shadow-lg">
                                                <i data-lucide="x" class="w-4 h-4"></i>
                                            </button>
                                        </div>

                                        <p class="text-[10px] text-slate-400 mt-1 italic font-semibold">Accepted formats: PDF, PNG, JPG</p>
                                    </div>
                                </div>

                                {{-- Supporting Documents --}}
                                <div class="mt-6 pt-5 border-t border-slate-100">
                                    <p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-4">Supporting Documents</p>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Proof of Ownership <span class="text-slate-400 normal-case font-normal">(C of O / R of O)</span></label>
                                            <div class="flex items-center gap-2">
                                                <input type="file" name="ownership_document" id="ext_ownership_doc" accept=".pdf,.png,.jpg,.jpeg"
                                                    class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                                    onchange="docFileChanged(this,'ext_btn_ownership','Proof of Ownership')">
                                                <button type="button" id="ext_btn_ownership" onclick="openDocPreview('ext_ownership_doc','Proof of Ownership')"
                                                    class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application Letter</label>
                                            <div class="flex items-center gap-2">
                                                <input type="file" name="application_letter" id="ext_app_letter" accept=".pdf,.png,.jpg,.jpeg"
                                                    class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                                    onchange="docFileChanged(this,'ext_btn_app_letter','Application Letter')">
                                                <button type="button" id="ext_btn_app_letter" onclick="openDocPreview('ext_app_letter','Application Letter')"
                                                    class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Means of Identification <span class="text-slate-400 normal-case font-normal">(NIN / Passport / Driver's Licence)</span></label>
                                            <div class="flex items-center gap-2">
                                                <input type="file" name="means_of_id" id="ext_means_id" accept=".pdf,.png,.jpg,.jpeg"
                                                    class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                                    onchange="docFileChanged(this,'ext_btn_means_id','Means of Identification')">
                                                <button type="button" id="ext_btn_means_id" onclick="openDocPreview('ext_means_id','Means of Identification')"
                                                    class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Tax Clearance Certificate</label>
                                            <div class="flex items-center gap-2">
                                                <input type="file" name="tax_clearance" id="ext_tax_clearance" accept=".pdf,.png,.jpg,.jpeg"
                                                    class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                                    onchange="docFileChanged(this,'ext_btn_tax_clearance','Tax Clearance Certificate')">
                                                <button type="button" id="ext_btn_tax_clearance" onclick="openDocPreview('ext_tax_clearance','Tax Clearance Certificate')"
                                                    class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                                    <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                                </button>
                                            </div>
                                            <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
            </form>

            {{-- Footer: Submit Button --}}
            <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-end bg-white shrink-0">
                <button type="button" class="px-6 py-2 rounded-xl bg-blue-600 text-white text-sm font-bold shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition" onclick="peSubmitForm()">
                    Save Plot Extension Application
                </button>
            </div>
        </div>
    </div>

    {{-- Document Preview Modal --}}
    <div id="doc-preview-modal" class="fixed inset-0 z-[10001] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm" onclick="closeDocPreview()"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden" style="max-height:90vh">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
                <h4 class="text-sm font-bold text-slate-800" id="doc-preview-title">Document Preview</h4>
                <button type="button" onclick="closeDocPreview()" class="text-slate-400 hover:text-slate-600 transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-4 flex items-center justify-center bg-slate-50" style="min-height:60vh">
                <img id="doc-preview-img" src="#" alt="Preview" class="hidden max-w-full max-h-full object-contain rounded-xl shadow">
                <iframe id="doc-preview-iframe" src="" class="hidden w-full border-0 rounded-xl" style="height:70vh"></iframe>
            </div>
        </div>
    </div>

    {{-- Global File Number Modal Component --}}
    @include('components.global-fileno-modal')

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
@include('components.searchable-select2')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
    const peCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const PE_TOTAL_STEPS = 1;
    let peStep = 1;

    /* ─── Address Builder ─── */
    const peAddrDefs = [
        { prefix: 'pe_res_addr',  output: 'pe_residential_address' },
        { prefix: 'pe_corr_addr', output: 'pe_correspondence_address' }
    ];

    function peIsOther(val) { return (val || '').trim().toLowerCase() === 'other'; }

    function peToggleOther(selectEl, wrapperId, otherEl) {
        var wrapper = document.getElementById(wrapperId);
        if (!wrapper || !selectEl) return;
        var show = peIsOther(selectEl.value);
        wrapper.classList.toggle('hidden', !show);
        if (!show && otherEl) otherEl.value = '';
    }

    function peSelectedOrOther(selectEl, otherEl) {
        if (!selectEl) return '';
        if (peIsOther(selectEl.value)) return (otherEl?.value || '').trim();
        return (selectEl.value || '').trim();
    }

    function peBuildAddress(prefix) {
        var plot    = (document.getElementById(prefix + '_plot')?.value || '').trim();
        var street  = document.getElementById(prefix + '_street');
        var streetO = document.getElementById(prefix + '_street_other');
        var dist    = document.getElementById(prefix + '_district');
        var distO   = document.getElementById(prefix + '_district_other');
        var lga     = (document.getElementById(prefix + '_lga')?.value || '').trim();
        var state   = (document.getElementById(prefix + '_state')?.value || '').trim();
        var streetVal = peSelectedOrOther(street, streetO);
        var distVal   = peSelectedOrOther(dist, distO);
        return [plot ? plot : streetVal, distVal, lga, state].filter(Boolean).join(', ').toUpperCase();
    }

    function peUpdateAddr(prefix, outputId) {
        var el = document.getElementById(outputId);
        if (el) el.value = peBuildAddress(prefix);
    }

    function peInitAddressBuilders() {
        peAddrDefs.forEach(function (def) {
            var p = def.prefix, o = def.output;
            var plotEl    = document.getElementById(p + '_plot');
            var streetEl  = document.getElementById(p + '_street');
            var streetOEl = document.getElementById(p + '_street_other');
            var distEl    = document.getElementById(p + '_district');
            var distOEl   = document.getElementById(p + '_district_other');
            var lgaEl     = document.getElementById(p + '_lga');
            var stateEl   = document.getElementById(p + '_state');
            var dashP     = p.replace(/_/g, '-');

            [plotEl, streetEl, streetOEl, distEl, distOEl, lgaEl, stateEl].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () { peUpdateAddr(p, o); });
                el.addEventListener('change', function () { peUpdateAddr(p, o); });
            });

            if (streetEl) streetEl.addEventListener('change', function () {
                peToggleOther(streetEl, dashP + '-street-other-wrapper', streetOEl);
                peUpdateAddr(p, o);
            });
            if (distEl) distEl.addEventListener('change', function () {
                peToggleOther(distEl, dashP + '-district-other-wrapper', distOEl);
                peUpdateAddr(p, o);
            });
        });
    }

    /* ─── Wizard ─── */
    function peSetStep(step) {
        // Single step logic: just ensure everything is visible if needed
        peStep = 1;
    }

    function peNextStep() {}
    function pePrevStep() {}
    function pePopulateReview() {}

    /* ─── Site Plan Preview ─── */
    function pePreviewSitePlan(input) {
        const container = document.getElementById('pe-site-plan-preview-container');
        const img = document.getElementById('pe-site-plan-preview-img');
        const pdfPlaceholder = document.getElementById('pe-pdf-preview-placeholder');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();

            container.classList.remove('hidden');

            if (file.type.startsWith('image/')) {
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    pdfPlaceholder.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            } else if (file.type === 'application/pdf') {
                img.classList.add('hidden');
                pdfPlaceholder.classList.remove('hidden');
            }
            
            if (window.lucide) window.lucide.createIcons();
        }
    }

    function peClearSitePlan() {
        const input = document.getElementById('pe-site-plan-input');
        const container = document.getElementById('pe-site-plan-preview-container');
        const img = document.getElementById('pe-site-plan-preview-img');
        
        if (input) input.value = '';
        if (container) container.classList.add('hidden');
        if (img) img.src = '#';
    }

    /* ─── CRUD ─── */
    function peOpenCreate() {
        peResetForm();
        document.getElementById('pe-modal-title').textContent = 'New Plot Extension Application';
        peShowModal();
    }

    function peUpdateLocationPreview() {
        // No plot number: it has its own field on this card, and a full property
        // location does not carry it. The house number stays — it is part of the
        // address rather than a repeat of a field beside it.
        const house = document.getElementById('pe-house-no')?.value || '';
        const street = document.getElementById('pe-street')?.value || '';
        const district = document.getElementById('pe-district')?.value || '';
        const lga = document.getElementById('pe-lga')?.value || '';
        const state = document.getElementById('pe-state')?.value || '';

        const parts = [];
        if (house) parts.push('NO. ' + house);
        if (street) parts.push(street);
        if (district) parts.push(district);
        if (lga) parts.push(lga);
        if (state) parts.push(state);
        
        const full = parts.join(', ').toUpperCase();
        const previewEl = document.getElementById('pe-location-preview');
        const hiddenEl = document.getElementById('pe-location');
        if (previewEl) previewEl.innerText = full || 'No location details entered yet.';
        if (hiddenEl) hiddenEl.value = full;
    }

    window.togglePeDropdown = function (id) {
        const button = document.querySelector(`#pe-dropdown-${id} button`);
        const menu = document.getElementById(`pe-menu-${id}`);
        const allMenus = document.querySelectorAll('[id^="pe-menu-"]');
        
        allMenus.forEach(m => { if(m.id !== `pe-menu-${id}`) m.classList.add('hidden'); });
        
        const isHidden = menu.classList.contains('hidden');
        if (isHidden) {
            menu.classList.remove('hidden');
            const rect = button.getBoundingClientRect();
            
            menu.style.position = 'fixed';
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = (rect.right - menu.offsetWidth) + 'px';
            
            if (rect.bottom + menu.offsetHeight > window.innerHeight) {
                menu.style.top = (rect.top - menu.offsetHeight - 5) + 'px';
            }
        } else {
            menu.classList.add('hidden');
        }
        
        const closeDropdown = (e) => {
            if (!document.getElementById(`pe-dropdown-${id}`).contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
                document.removeEventListener('click', closeDropdown);
            }
        };
        if (!menu.classList.contains('hidden')) {
            setTimeout(() => document.addEventListener('click', closeDropdown), 0);
        }
    }

    async function peView(btn) {
        const data = peRowData(btn);
        if (!data) return;

        Swal.fire({
            title: 'Plot Extension Details',
            html: `
                <div class="text-left text-sm space-y-3 p-2">
                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase">Applicant</p>
                            <p class="font-bold text-slate-800">${data.applicant_name || '—'}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase">File No</p>
                            <p class="font-bold text-slate-800">${data.file_no || '—'}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase">Status</p>
                            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase ${data.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700'}">${data.status}</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase">Occupation</p>
                            <p class="font-bold text-slate-800">${data.occupation || '—'}</p>
                        </div>
                        <div class="bg-blue-50 p-2 rounded-lg border border-blue-100">
                            <p class="text-[10px] font-black text-blue-600 uppercase">Application Fee (NGN)</p>
                            <p class="font-bold text-blue-700">₦${new Intl.NumberFormat('en-NG').format(data.knupda_fee || 10000)}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase">Land Use</p>
                            <p class="text-slate-600">${data.land_use || '—'}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase">Plot No</p>
                            <p class="text-slate-600">${data.plot_no || '—'}</p>
                        </div>
                    </div>



                    <div class="space-y-1">
                        <p class="text-[10px] font-black text-slate-400 uppercase">Residential Address</p>
                        <p class="text-slate-600 italic">${data.residential_address || '—'}</p>
                    </div>



                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Property Location</p>
                        <p class="text-sm font-bold text-slate-700">${data.location || '—'}</p>
                    </div>
                </div>
            `,
            width: 600,
            showConfirmButton: false,
            showCloseButton: true
        });
    }
    async function peOpenKnupdaModal(id) {
        try {
            const response = await fetch(`{{ url('plot-extension') }}/${id}`);
            const result = await response.json();
            if (result.success) {
                const data = result.data;
                Swal.fire({
                    title: 'KAMMA Handshake',
                    html: `
                        <div class="text-left text-sm space-y-4 p-2">
                            <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-100 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Land Value (NGN)</label>
                                        <input type="number" step="0.01" id="knupda_land_value_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" value="${data.land_value || ''}">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (NGN)</label>
                                        <input type="text" id="knupda_fee_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm font-bold bg-white" value="${data.knupda_fee || '10000'}" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">KAMMA Approval</label>
                                        <div class="flex items-center gap-4 mt-2">
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="knupda_status" value="Approved" ${data.knupda_status === 'Approved' ? 'checked' : ''} class="w-4 h-4 text-emerald-600">
                                                <span class="text-xs font-medium text-slate-700">Approve</span>
                                            </label>
                                            <label class="flex items-center gap-2 cursor-pointer">
                                                <input type="radio" name="knupda_status" value="Declined" ${data.knupda_status === 'Declined' ? 'checked' : ''} class="w-4 h-4 text-red-600">
                                                <span class="text-xs font-medium text-slate-700">Decline</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Remarks</label>
                                    <textarea id="knupda_remarks_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" rows="2" placeholder="KAMMA feedback...">${data.knupda_remarks || ''}</textarea>
                                </div>
                                <button onclick="peSaveKnupda(${data.id})" class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">
                                    Update KAMMA Status
                                </button>
                            </div>
                        </div>
                    `,
                    width: 500,
                    showConfirmButton: false,
                    showCloseButton: true
                });
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to fetch details.', 'error');
        }
    }

    async function peSaveKnupda(id) {
        const landValue = document.getElementById('knupda_land_value_input').value;
        const fee = document.getElementById('knupda_fee_input').value;
        const status = document.querySelector('input[name="knupda_status"]:checked')?.value || 'Pending';
        const remarks = document.getElementById('knupda_remarks_input').value;

        try {
            const response = await fetch(`{{ url('plot-extension') }}/${id}/knupda`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': peCsrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ land_value: landValue, knupda_fee: fee, knupda_status: status, knupda_remarks: remarks })
            });

            const result = await response.json();
            if (result.success) {
                await Swal.fire({ icon: 'success', title: 'Updated', text: 'KAMMA status updated successfully.', timer: 1500, showConfirmButton: false });
                location.reload();
            } else {
                Swal.fire('Error', 'Failed to update KAMMA status', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'An error occurred', 'error');
        }
    }

    async function peGenerateApplication(id) {
        const confirm = await Swal.fire({
            title: 'Generate Application?',
            text: "Are you sure you want to generate the application document?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Generate',
            confirmButtonColor: '#f97316'
        });

        if (!confirm.isConfirmed) return;

        try {
            const response = await fetch(`{{ url('plot-extension') }}/${id}/generate-application`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': peCsrf, 'Accept': 'application/json' }
            });
            const res = await response.json();
            if (res.success) {
                await Swal.fire('Success', 'Application generated. You can now print it.', 'success');
                location.reload();
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to generate application', 'error');
        }
    }

    async function peGenerateRecommendation(id) {
        const confirm = await Swal.fire({
            title: 'Generate Recommendation?',
            text: "Are you sure you want to generate the recommendation document?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Generate',
            confirmButtonColor: '#10b981'
        });

        if (!confirm.isConfirmed) return;

        try {
            const response = await fetch(`{{ url('plot-extension') }}/${id}/generate-recommendation`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': peCsrf, 'Accept': 'application/json' }
            });
            const res = await response.json();
            if (res.success) {
                await Swal.fire('Success', 'Recommendation generated. You can now print it.', 'success');
                location.reload();
            } else {
                Swal.fire('Error', 'Recommendation can only be generated for Approved applications.', 'error');
            }
        } catch (error) {
            Swal.fire('Error', 'Failed to generate recommendation', 'error');
        }
    }

    function peEdit(btn) {
        var data = peRowData(btn);
        if (!data) return;
        peResetForm();
        document.getElementById('pe-modal-title').textContent = 'Edit Plot Extension Application';
        document.getElementById('pe-id').value = data.id || '';
        document.getElementById('pe-applicant-name').value = data.applicant_name || '';

        document.getElementById('pe-occupation').value = data.occupation || '';
        document.getElementById('pe-phone').value = data.phone || '';
        document.getElementById('pe-email').value = data.email || '';
        document.getElementById('pe-file-no').value = data.file_no || '';
        document.getElementById('pe-land-use').value = data.land_use || '';
        document.getElementById('pe-plot-no').value = data.plot_no || '';
        document.getElementById('pe-plan-no').value = data.plan_no || '';
        document.getElementById('pe-location').value = data.location || '';
        document.getElementById('pe-remarks').value = data.remarks || '';
        document.getElementById('pe_residential_address').value = data.residential_address || '';
        document.getElementById('pe_correspondence_address').value = data.correspondence_address || '';

        if (data.file_no) document.getElementById('pe-file-indicator')?.classList.remove('hidden');

        peShowModal();
    }

    async function peDelete(btn) {
        var data = peRowData(btn);
        if (!data || !data.id) return;

        var res = await Swal.fire({
            title: 'Delete record?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        });
        if (!res.isConfirmed) return;

        try {
            var response = await fetch('{{ route("plot-extension.destroy", ["id" => 0]) }}'.replace('/0', '/' + data.id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': peCsrf, 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Delete failed.');
            await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Delete failed.' });
        }
    }

    function peShowModal() {
        document.getElementById('pe-modal').classList.remove('hidden');
        peSetStep(1);
    }

    function peCloseModal() {
        document.getElementById('pe-modal').classList.add('hidden');
    }

    function peResetForm() {
        document.getElementById('pe-form').reset();
        document.getElementById('pe-id').value = '';
        document.getElementById('pe-file-indicator')?.classList.add('hidden');
        document.querySelectorAll('[id$="-other-wrapper"]').forEach(function (w) { w.classList.add('hidden'); });
        peSetStep(1);
    }

    async function peSubmitForm() {
        // Validation moved to final step
        if (!document.getElementById('pe-applicant-name').value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Incomplete Details', text: 'Applicant name is required before saving.' });
            return;
        }
        if (!document.getElementById('pe-file-no').value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Incomplete Details', text: 'Please select a Right of Occupancy (File No).' });
            return;
        }

        var id = document.getElementById('pe-id').value;
        var form = document.getElementById('pe-form');
        var formData = new FormData(form);

        var endpoint = id
            ? '{{ route("plot-extension.update", ["id" => 0]) }}'.replace('/0', '/' + id)
            : '{{ route("plot-extension.store") }}';
        
        // Since we are using FormData, we should use POST and spoof PUT if needed, 
        // but Laravel handles FormData with files better via POST.
        // For simplicity, let's just use POST for both if it's a new or edit with files.
        // Actually, let's keep the logic simple.
        var method = 'POST';
        if (id) {
            formData.append('_method', 'PUT');
        }

        try {
            var response = await fetch(endpoint, {
                method: method,
                headers: { 'X-CSRF-TOKEN': peCsrf, 'Accept': 'application/json' },
                body: formData
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) {
                var msg = payload.message || (payload.errors ? Object.values(payload.errors)[0][0] : 'Save failed.');
                throw new Error(msg);
            }
            await Swal.fire({ icon: 'success', title: 'Saved successfully', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Save failed.' });
        }
    }

    async function peOpenApprovalDecision(id, canApprove = true) {
        var result = await Swal.fire({
            title: 'Approval decision',
            text: canApprove ? 'Choose whether to approve or reject this application.' : 'The Master JSI is not approved, so only rejection is available.',
            input: 'radio',
            inputOptions: canApprove ? { approve: 'Approve', reject: 'Reject' } : { reject: 'Reject' },
            inputValidator: function (value) { return !value && 'Select an action to continue.'; },
            showCancelButton: true,
            confirmButtonText: 'Continue'
        });
        if (!result.isConfirmed) return;
        return result.value === 'approve' ? peApprove(id) : peReject(id);
    }

    async function peApprove(id) {
        var res = await Swal.fire({
            title: 'Approve Application?',
            text: 'Are you sure you want to approve this plot extension application?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, approve'
        });
        if (!res.isConfirmed) return;
        try {
            var response = await fetch('{{ url("plot-extension") }}/' + id + '/approve', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': peCsrf, 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Approval failed.');
            await Swal.fire({ icon: 'success', title: 'Approved', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Approval failed.' });
        }
    }

    async function peReject(id) {
        var res = await Swal.fire({
            title: 'Reject Application',
            input: 'textarea',
            inputLabel: 'Reason for rejection',
            inputPlaceholder: 'Enter reason here...',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            confirmButtonText: 'Reject'
        });
        if (res.value === undefined) return;
        try {
            var response = await fetch('{{ url("plot-extension") }}/' + id + '/reject', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': peCsrf, 'Accept': 'application/json' },
                body: JSON.stringify({ reason: res.value })
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Rejection failed.');
            await Swal.fire({ icon: 'success', title: 'Rejected', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Rejection failed.' });
        }
    }

    function peRowData(btn) {
        var tr = btn.closest('tr');
        if (!tr) return null;
        try { return JSON.parse(tr.getAttribute('data-record')); } catch (e) { return null; }
    }

    function peSafe(value) {
        return (value || '—').toString().replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
        });
    }

    /* ─── Init ─── */
    document.addEventListener('DOMContentLoaded', function () {
        /* DataTable */
        var table = $('#plot-extension-table').DataTable({
            dom: '<"overflow-x-auto"t><"px-4 py-3 flex items-center justify-between border-t border-slate-100"ip>',
            pageLength: parseInt(document.getElementById('pe-length')?.value || '50', 10),
            lengthChange: false,
            order: [[7, 'desc']],
            columnDefs: [{ orderable: false, targets: [8] }],
            language: {
                info: 'Showing _START_ to _END_ of _TOTAL_ plot extension records',
                emptyTable: 'No plot extension applications found.',
                paginate: { previous: 'Prev', next: 'Next' }
            }
        });

        document.getElementById('pe-search')?.addEventListener('input', function () {
            table.search(this.value).draw();
        });
        document.getElementById('pe-length')?.addEventListener('change', function () {
            table.page.len(parseInt(this.value, 10)).draw();
        });

        /* Address builders */
        peInitAddressBuilders();

        /* File Number Selector */
        if (window.GlobalFileNoModal) {
            GlobalFileNoModal.init();
        }

        document.getElementById('pe-select-file-btn')?.addEventListener('click', function () {
            if (!window.GlobalFileNoModal) return;
            GlobalFileNoModal.open({
                callback: function (data) {
                    if (data.fileNumber) {
                        document.getElementById('pe-file-no').value = data.fileNumber.toUpperCase();
                        document.getElementById('pe-file-indicator')?.classList.remove('hidden');
                        if (data.record) {
                            if (data.record.applicant_name) {
                                document.getElementById('pe-applicant-name').value = data.record.applicant_name.toUpperCase();
                            } else if (data.record.file_name) {
                                document.getElementById('pe-applicant-name').value = data.record.file_name.toUpperCase();
                            }
                            // Backfill property details
                            if (data.record.land_use) document.getElementById('pe-land-use').value = data.record.land_use;
                            if (data.record.plot_no) document.getElementById('pe-plot-no').value = data.record.plot_no;
                            if (data.record.plan_no) document.getElementById('pe-plan-no').value = data.record.plan_no;
                            if (data.record.house_no) document.getElementById('pe-house-no').value = data.record.house_no;
                            if (data.record.street_name) document.getElementById('pe-street').value = data.record.street_name;
                            if (data.record.district) document.getElementById('pe-district').value = data.record.district;
                            if (data.record.lga) document.getElementById('pe-lga').value = data.record.lga;
                            if (data.record.state) document.getElementById('pe-state').value = data.record.state;

                            if (window.syncSearchableSelects) syncSearchableSelects();
                            if (typeof peUpdateLocationPreview === 'function') {
                                peUpdateLocationPreview();
                            }
                        }
                    }
                }
            });
            // Raise file-no modal above the form modal (use setTimeout to ensure it applies after open() internals)
            setTimeout(function() {
                $('#global-fileno-modal').css({ 'z-index': '99999', 'display': 'flex' });
            }, 50);
        });

        /* Location Preview listeners */
        document.querySelectorAll('#pe-plot-no, #pe-house-no, #pe-street, #pe-district, #pe-lga, #pe-state')
            .forEach(el => {
                el.addEventListener('change', peUpdateLocationPreview);
                if (el.tagName === 'INPUT') el.addEventListener('input', peUpdateLocationPreview);
            });

        if (window.lucide) window.lucide.createIcons();
    });

    function docFileChanged(input, btnId) {
        const btn = document.getElementById(btnId);
        if (btn) btn.classList.toggle('hidden', !(input.files && input.files.length > 0));
        if (window.lucide) window.lucide.createIcons();
    }

    function openDocPreview(inputId, label) {
        const input = document.getElementById(inputId);
        if (!input || !input.files || !input.files[0]) return;
        const file = input.files[0];
        const url = URL.createObjectURL(file);
        document.getElementById('doc-preview-title').textContent = label;
        const img = document.getElementById('doc-preview-img');
        const iframe = document.getElementById('doc-preview-iframe');
        if (file.type === 'application/pdf') {
            img.classList.add('hidden');
            iframe.src = url;
            iframe.classList.remove('hidden');
        } else {
            iframe.classList.add('hidden');
            img.src = url;
            img.classList.remove('hidden');
        }
        document.getElementById('doc-preview-modal').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    function closeDocPreview() {
        const iframe = document.getElementById('doc-preview-iframe');
        if (iframe) iframe.src = '';
        document.getElementById('doc-preview-modal').classList.add('hidden');
    }
</script>
@endsection
