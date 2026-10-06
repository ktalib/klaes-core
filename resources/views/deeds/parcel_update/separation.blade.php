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
    $jsiCleared = \App\Support\MasterJsiGate::clearedMap('separation', $records);
@endphp

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }
    .section-box {
        background: #fdfdfd;
        padding: 20px 15px 15px 15px;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        position: relative;
        margin-top: 10px;
    }
    .section-label {
        position: absolute;
        top: -12px;
        left: 15px;
        background: #fff;
        padding: 0 10px;
        font-size: 0.75rem;
        font-weight: bold;
        color: #3498db;
        text-transform: uppercase;
    }
    .total-box {
        background-color: #f1f8ff;
        border: 1px dashed #3498db;
        padding: 10px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .readonly { background: #f9f9f9; color: #7f8c8d; font-weight: bold; cursor: not-allowed; }
    .location-card { display: none; }
    .location-card.active {
        display: block;
        animation: fadeIn 0.3s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to   { opacity: 1; transform: translateY(0); }
    }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Plot Separation',
        'PageDescription' => 'Manage Plot Separation applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }}</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Plot Separation</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->total()) }} record(s) found</p>
                </div>
                <div class="flex items-center gap-3">
                    <form action="{{ route('plot-separation.index') }}" method="GET" class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </form>
                    <button type="button" onclick="openCreateModal()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Separation
                    </button>
                </div>
            </div>

            {{-- Status Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex items-start justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total</p>
                        <p class="text-3xl font-extrabold text-slate-800 mt-1">{{ number_format($stats['total']) }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">All applications</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center">
                        <i data-lucide="layers" class="w-5 h-5 text-slate-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-indigo-200 shadow-sm p-5 flex items-start justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-500">Created Today</p>
                        <p class="text-3xl font-extrabold text-indigo-600 mt-1">{{ number_format($stats['daily']) }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">New entries</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <i data-lucide="calendar" class="w-5 h-5 text-indigo-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-5 flex items-start justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-amber-500">Pending</p>
                        <p class="text-3xl font-extrabold text-amber-600 mt-1">{{ number_format($stats['pending']) }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Awaiting review</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                        <i data-lucide="clock" class="w-5 h-5 text-amber-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-blue-200 shadow-sm p-5 flex items-start justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-blue-500">Approved</p>
                        <p class="text-3xl font-extrabold text-blue-600 mt-1">{{ number_format($stats['approved']) }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Ready to process</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-blue-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-red-200 shadow-sm p-5 flex items-start justify-between transition hover:shadow-md">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-red-500">Rejected</p>
                        <p class="text-3xl font-extrabold text-red-600 mt-1">{{ number_format($stats['rejected']) }}</p>
                        <p class="text-[10px] text-slate-400 mt-1">Not approved</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                        <i data-lucide="x-circle" class="w-5 h-5 text-red-500"></i>
                    </div>
                </div>
            </div>

            {{-- Table --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                <th class="px-4 py-3 text-left">S/N</th>
                                <th class="px-4 py-3 text-left">Applicant</th>
                                <th class="px-4 py-3 text-left whitespace-nowrap">File No</th>
                                <th class="px-4 py-3 text-left">File Title</th>
                                <th class="px-4 py-3 text-left">Plots</th>
                                <th class="px-4 py-3 text-left">Location</th>
                                <th class="px-4 py-3 text-left">Source</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($records as $record)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $records->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3 text-slate-700 font-bold">{{ $record->applicant_name ?: '—' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap">{{ $record->file_no }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $record->file_title }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $record->num_plots }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-xs">
                                        {{ implode(', ', array_filter([
                                            $record->house_no   ? 'House No '.$record->house_no : null,
                                            $record->plot_no    ? 'Plot '.$record->plot_no : null,
                                            $record->street_name,
                                            $record->district,
                                            $record->lga,
                                            $record->state
                                        ])) }}
                                    </td>
                                    {{-- Which sidebar raised it: both open this same register. --}}
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded-lg border text-[11px] font-bold uppercase {{ \App\Support\ParcelUpdateSource::chipClass($record->source_module) }}">
                                            {{ \App\Support\ParcelUpdateSource::label($record->source_module) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-500 text-xs">{{ $record->created_at->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="relative inline-block text-left" id="dropdown-{{ $record->id }}">
                                            <button type="button" onclick="toggleDropdown({{ $record->id }})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-full transition">
                                                <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                            </button>
                                            <div id="menu-{{ $record->id }}" class="hidden fixed z-[999] mt-2 w-max origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none divide-y divide-slate-100 whitespace-nowrap">
                                                <div class="py-1">
                                                    <button onclick="viewRecord({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                        <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i> View Details
                                                    </button>
                                                </div>
                                                <div class="py-1 border-t border-slate-100">
                                                    @if(strcasecmp(trim((string) ($record->knupda_status ?? '')), 'Approved') === 0)
                                                    <button disabled title="Already approved by KAMMA / Physical Planning" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50"><i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> KAMMA / Physical Planning</button>
                                                    @else
                                                    <button onclick="openKnupdaModal({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50 gap-2">
                                                        <i data-lucide="ruler" class="w-4 h-4 text-blue-500"></i> KAMMA / Physical Planning
                                                    </button>
                                                    @endif
                                                </div>
                                                <div class="py-1">
                                                    @if(($jsiCleared[$record->id] ?? false))
                                                        @if(!$record->recommendation_generated_at)
                                                            <button onclick="generateRecommendation({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                <i data-lucide="file-check" class="w-4 h-4 text-emerald-500"></i> Generate Recommendation
                                                            </button>
                                                        @else
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Recommendation already generated">
                                                                <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> Generate Recommendation
                                                            </button>
                                                        @endif
                                                        @if($record->recommendation_generated_at)
                                                            <a href="{{ route('plot-separation.print-recommendation', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
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
                                                    {{-- Approve sits BELOW the recommendation and above the
                                                         application: the approval is given on the strength of the
                                                         recommendation memo, so it cannot be offered above it. Reject
                                                         is the same question's other answer and sits beside it. Same
                                                         order of business the duplex register follows.

                                                         This page had no Approve item at all - the route and the
                                                         controller action existed, but the only way to approve was the
                                                         KAMMA Handshake, which is a different decision. Reject is in
                                                         the same position: the route exists, the menu never offered it,
                                                         and this page still needs its own rejectRecord() below. --}}
                                                    @if(in_array($record->status, ['approved', 'rejected', 'commissioned'], true))
                                                        <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Already approved{{ ($jsiCleared[$record->id] ?? false) ? ' via the Master JSI' : '' }}">
                                                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300"></i> Approve / Reject
                                                        </button>
                                                    @else
                                                        <button onclick="openApprovalDecision({{ $record->id }}, {{ ($jsiCleared[$record->id] ?? false) ? 'true' : 'false' }})" class="flex items-center w-full px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 gap-2 font-bold">
                                                            <i data-lucide="scale" class="w-4 h-4 text-emerald-500"></i> Approve / Reject
                                                        </button>
                                                    @endif
                                                </div>
                                                <div class="py-1">
                                                    @if(($jsiCleared[$record->id] ?? false))
                                                        @if(!$record->application_generated_at)
                                                            <button onclick="generateApplication({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                                <i data-lucide="file-plus" class="w-4 h-4 text-orange-500"></i> Generate Application
                                                            </button>
                                                        @else
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Application already generated">
                                                                <i data-lucide="check-circle-2" class="w-4 h-4 text-slate-300"></i> Generate Application
                                                            </button>
                                                        @endif
                                                        @if($record->application_generated_at)
                                                            <a href="{{ route('plot-separation.print-application', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
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
                                                    @if($record->status === 'approved')
                                                        <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Approved applications cannot be deleted">
                                                            <i data-lucide="trash-2" class="w-4 h-4 text-slate-300"></i> Delete
                                                        </button>
                                                    @else
                                                        <button onclick="deleteRecord({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 gap-2">
                                                            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-10 text-center text-slate-400">No records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($records->hasPages())
                    <div class="px-4 py-3 bg-slate-50/50 border-t border-slate-100">
                        {{ $records->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Create Modal --}}
    <div id="create-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">New Separation</h3>
                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">Deeds / Parcel Update</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 transition" onclick="closeModal()">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <form id="create-form" class="flex-1 overflow-y-auto p-8">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">File No <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" name="file_no" id="file_no" readonly class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono">
                            <button type="button" onclick="openFileSelector()" class="p-2.5 bg-blue-50 text-blue-600 rounded-xl border border-blue-100 hover:bg-blue-100 transition">
                                <i data-lucide="search" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">File Title <span class="text-red-500">*</span></label>
                        <input type="text" name="file_title" id="file_title" readonly class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Applicant Name</label>
                        <input type="text" name="applicant_name" id="applicant_name" oninput="document.getElementById('file_title').value = this.value" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">No. of Plots <span class="text-red-500">*</span></label>
                        <input type="number" name="num_plots" id="num_plots" min="1" max="50" value="2" oninput="generateFragments()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                    </div>

                    <div class="md:col-span-3 section-box">
                        <span class="section-label">Separated Plots Details</span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Land Value (NGN) <span class="text-red-500">*</span></label>
                                <input type="number" name="land_value" id="land_value_input" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm" placeholder="Enter land value" oninput="calculateSeparationFee()">
                            </div>
                            <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100">
                                <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (0.25%)</label>
                                <input type="text" id="knupda_fee_display" class="w-full bg-transparent border-none text-sm font-bold text-blue-700 p-0" value="0.00" readonly>
                                <input type="hidden" name="knupda_fee" id="knupda_fee_hidden" value="0">
                            </div>
                        </div>
                        <div id="fragmentsContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <p class="col-span-full text-slate-400 text-xs italic">Enter "No. of Plots" above to define separated plot sizes.</p>
                        </div>
                        <div class="total-box">
                            <label class="text-sm font-semibold text-slate-700">Total Separation Area:</label>
                            <input type="text" id="totalSize" class="readonly w-32 text-right border-none bg-transparent text-orange-700 font-bold" value="0.00 Ha" readonly>
                        </div>
                    </div>

                    <div class="md:col-span-3 section-box mt-6">
                        <div class="flex items-center justify-between mb-6">
                            <span class="section-label">Mother Plot Location Details</span>
                        </div>
                        <div id="location_cards_container">
                            <div id="location_card_1" class="location-card active">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Plot No</label>
                                        <input type="text" name="location_details[1][plot_no]" id="loc_plot_no" oninput="updateLocationPreview(1)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">House No</label>
                                        <input type="text" name="location_details[1][house_no]" id="loc_house_no" oninput="updateLocationPreview(1)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Street Name</label>
                                        <select name="location_details[1][street_name]" id="loc_street_name" onchange="toggleOtherInput(this); updateLocationPreview(1)" class="searchable-select w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                                            <option value="">Select Street</option>
                                            @foreach($streetNames as $street)
                                                <option value="{{ $street->name }}">{{ strtoupper($street->name) }}</option>
                                            @endforeach
                                            <option value="OTHER">OTHER</option>
                                        </select>
                                        <input type="text" name="location_details[1][street_name_other]" id="loc_street_name_other" class="hidden mt-2 w-full px-4 py-2.5 rounded-xl border border-blue-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm" placeholder="Specify Street Name" oninput="updateLocationPreview(1)">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">District</label>
                                        <select name="location_details[1][district]" id="loc_district" onchange="toggleOtherInput(this); updateLocationPreview(1)" class="searchable-select w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                                            <option value="">Select District</option>
                                            @foreach($districts as $district)
                                                <option value="{{ $district->name }}">{{ strtoupper($district->name) }}</option>
                                            @endforeach
                                            <option value="OTHER">OTHER</option>
                                        </select>
                                        <input type="text" name="location_details[1][district_other]" id="loc_district_other" class="hidden mt-2 w-full px-4 py-2.5 rounded-xl border border-blue-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm" placeholder="Specify District" oninput="updateLocationPreview(1)">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">LGA</label>
                                        <select name="location_details[1][lga]" id="loc_lga" onchange="updateLocationPreview(1)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                                            <option value="">Select LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ strtoupper($lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">State</label>
                                        <select name="location_details[1][state]" id="loc_state" onchange="updateLocationPreview(1)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" @selected($state->StateName == 'Kano')>{{ strtoupper($state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="md:col-span-3">
                                        <div class="bg-slate-100/50 p-4 rounded-xl border border-dashed border-slate-300">
                                            <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Full Property Location Preview</p>
                                            <p id="location_preview_1" class="text-sm font-bold text-slate-700 italic">No location details entered yet.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Application Plan (Site Plan)</label>
                        <div class="flex flex-col gap-4">
                            <input type="file" name="site_plan" id="site_plan_input" accept=".pdf,.png,.jpg,.jpeg"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                onchange="previewSitePlan(this)">
                            <div id="site_plan_preview_container" class="hidden relative w-full aspect-video rounded-2xl overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center group">
                                <img id="site_plan_preview_img" src="#" alt="Site Plan Preview" class="max-h-full max-w-full object-contain">
                                <div id="pdf_preview_placeholder" class="hidden flex-col items-center gap-2 text-slate-400">
                                    <i data-lucide="file-text" class="w-12 h-12"></i>
                                    <span class="text-xs font-bold uppercase tracking-wider">PDF Selected</span>
                                </div>
                                <button type="button" onclick="clearSitePlan()" class="absolute top-4 right-4 p-2 bg-red-100 text-red-600 rounded-full opacity-0 group-hover:opacity-100 transition shadow-lg">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1 italic uppercase tracking-wider font-semibold">Accepted formats: PDF, PNG, JPG</p>
                        </div>
                    </div>

                    {{-- Supporting Documents --}}
                    <div class="md:col-span-3 section-box mt-2">
                        <span class="section-label">Supporting Documents</span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Proof of Ownership <span class="text-slate-400 normal-case font-normal">(C of O / R of O)</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="ownership_document" id="sep_ownership_doc" accept=".pdf,.png,.jpg,.jpeg" class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white" onchange="docFileChanged(this,'sep_btn_ownership')">
                                    <button type="button" id="sep_btn_ownership" onclick="openDocPreview('sep_ownership_doc','Proof of Ownership')" class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Application Letter</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="application_letter" id="sep_app_letter" accept=".pdf,.png,.jpg,.jpeg" class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white" onchange="docFileChanged(this,'sep_btn_app_letter')">
                                    <button type="button" id="sep_btn_app_letter" onclick="openDocPreview('sep_app_letter','Application Letter')" class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Means of Identification <span class="text-slate-400 normal-case font-normal">(NIN / Passport / Driver's Licence)</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="means_of_id" id="sep_means_id" accept=".pdf,.png,.jpg,.jpeg" class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white" onchange="docFileChanged(this,'sep_btn_means_id')">
                                    <button type="button" id="sep_btn_means_id" onclick="openDocPreview('sep_means_id','Means of Identification')" class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tax Clearance Certificate</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="tax_clearance" id="sep_tax_clearance" accept=".pdf,.png,.jpg,.jpeg" class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white" onchange="docFileChanged(this,'sep_btn_tax_clearance')">
                                    <button type="button" id="sep_btn_tax_clearance" onclick="openDocPreview('sep_tax_clearance','Tax Clearance Certificate')" class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            <div class="px-8 py-4 border-t border-slate-100 flex justify-end gap-3 bg-slate-50">
                <button type="button" onclick="closeModal()" class="px-6 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-100 transition">Cancel</button>
                <button type="button" onclick="submitForm()" class="px-6 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition">Submit</button>
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

    @include('components.global-fileno-modal')
    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
@include('components.searchable-select2')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
    function toggleOtherInput(select) {
        const otherInput = document.getElementById(select.id + '_other');
        if (otherInput) {
            if (select.value.toUpperCase() === 'OTHER') {
                otherInput.classList.remove('hidden');
                otherInput.focus();
            } else {
                otherInput.classList.add('hidden');
                otherInput.value = '';
            }
        }
    }

    function openCreateModal() {
        document.getElementById('create-form').reset();
        document.getElementById('num_plots').value = 2;
        generateFragments();
        document.getElementById('totalSize').value = '0.00 Ha';
        clearSitePlan();
        document.getElementById('create-modal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('create-modal').classList.add('hidden');
    }

    function calculateSeparationFee() {
        const val = parseFloat(document.getElementById('land_value_input').value) || 0;
        const fee = val * 0.0025;
        const display = document.getElementById('knupda_fee_display');
        if (display) display.value = '₦' + fee.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const hidden = document.getElementById('knupda_fee_hidden');
        if (hidden) hidden.value = fee;
    }

    window.calculateViewSeparationFee = function(inputElem) {
        const val = parseFloat(inputElem.value) || 0;
        const fee = val * 0.0025;
        const feeInput = document.getElementById('view_knupda_fee_input');
        if (feeInput) feeInput.value = fee.toFixed(2);
    };

    function generateFragments() {
        const sizeContainer = document.getElementById('fragmentsContainer');
        const num = parseInt(document.getElementById('num_plots').value) || 0;
        sizeContainer.innerHTML = '';
        if (num > 0) {
            for (let i = 1; i <= num; i++) {
                const div = document.createElement('div');
                div.innerHTML = `
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Plot ${i} Size</label>
                    <input type="number" name="plot_sizes[]" class="fragment-input w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" step="0.01" placeholder="0.00" oninput="calculateTotal()">
                `;
                sizeContainer.appendChild(div);
            }
        } else {
            sizeContainer.innerHTML = '<p class="col-span-full text-slate-400 text-xs italic">Enter "No. of Plots" above to define fragment sizes.</p>';
            document.getElementById('totalSize').value = '0.00 Ha';
        }
    }

    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.fragment-input').forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) total += val;
        });
        document.getElementById('totalSize').value = parseFloat(total.toFixed(10)) + ' Ha';
    }

    function openFileSelector() {
        if (window.GlobalFileNoModal) {
            GlobalFileNoModal.open({
                callback: function(data) {
                    if (data.fileNumber) {
                        document.getElementById('file_no').value = data.fileNumber;
                        if (data.record) {
                            const name = data.record.file_name || data.record.applicant_name || '';
                            document.getElementById('file_title').value = name;
                            document.getElementById('applicant_name').value = name;
                            if (data.record.plot_no)      document.getElementById('loc_plot_no').value = data.record.plot_no;
                            if (data.record.house_no)     document.getElementById('loc_house_no').value = data.record.house_no;
                            if (data.record.street_name)  document.getElementById('loc_street_name').value = data.record.street_name;
                            if (data.record.district)     document.getElementById('loc_district').value = data.record.district;
                            if (data.record.lga)          document.getElementById('loc_lga').value = data.record.lga;
                            if (data.record.state)        document.getElementById('loc_state').value = data.record.state;
                            if (window.syncSearchableSelects) syncSearchableSelects();
                            updateLocationPreview(1);
                        }
                    }
                }
            });
        }
    }

    async function submitForm() {
        const form     = document.getElementById('create-form');
        const formData = new FormData(form);

        formData.append('plot_no',  document.getElementById('loc_plot_no')?.value  || '');
        formData.append('house_no', document.getElementById('loc_house_no')?.value || '');

        const streetVal = document.getElementById('loc_street_name')?.value;
        formData.append('street_name', streetVal?.toUpperCase() === 'OTHER' ? (document.getElementById('loc_street_name_other')?.value || 'OTHER') : (streetVal || ''));

        const districtVal = document.getElementById('loc_district')?.value;
        formData.append('district', districtVal?.toUpperCase() === 'OTHER' ? (document.getElementById('loc_district_other')?.value || 'OTHER') : (districtVal || ''));

        formData.append('lga',   document.getElementById('loc_lga')?.value   || '');
        formData.append('state', document.getElementById('loc_state')?.value || '');

        try {
            const response = await fetch('{{ route("plot-separation.store") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                await Swal.fire({ icon: 'success', title: 'Success', text: result.message, timer: 1500, showConfirmButton: false });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'Something went wrong' });
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred' });
        }
    }

    function updateLocationPreview(index) {
        const card = document.getElementById(`location_card_${index}`);
        if (!card) return;
        const plot  = card.querySelector(`[name="location_details[${index}][plot_no]"]`)?.value  || '';
        const house = card.querySelector(`[name="location_details[${index}][house_no]"]`)?.value || '';
        let street  = card.querySelector(`[name="location_details[${index}][street_name]"]`)?.value || '';
        if (street.toUpperCase() === 'OTHER') street = card.querySelector(`[name="location_details[${index}][street_name_other]"]`)?.value || 'OTHER';
        let district = card.querySelector(`[name="location_details[${index}][district]"]`)?.value || '';
        if (district.toUpperCase() === 'OTHER') district = card.querySelector(`[name="location_details[${index}][district_other]"]`)?.value || 'OTHER';
        const lga   = card.querySelector(`[name="location_details[${index}][lga]"]`)?.value   || '';
        const state = card.querySelector(`[name="location_details[${index}][state]"]`)?.value || '';
        const parts = [];
        if (plot)     parts.push(plot);
        if (house)    parts.push(`House No. ${house}`);
        if (street)   parts.push(street);
        if (district) parts.push(district);
        if (lga)      parts.push(lga);
        if (state)    parts.push(state);
        const preview = document.getElementById(`location_preview_${index}`);
        if (preview) preview.innerText = parts.length > 0 ? parts.join(', ') : 'No location details entered yet.';
    }

    function toggleDropdown(id) {
        const button  = document.querySelector(`#dropdown-${id} button`);
        const menu    = document.getElementById(`menu-${id}`);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        allMenus.forEach(m => { if (m.id !== `menu-${id}`) m.classList.add('hidden'); });
        const isHidden = menu.classList.contains('hidden');
        if (isHidden) {
            menu.classList.remove('hidden');
            const rect = button.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.top  = (rect.bottom + 5) + 'px';
            menu.style.left = (rect.right - menu.offsetWidth) + 'px';
            if (rect.bottom + menu.offsetHeight > window.innerHeight) {
                menu.style.top = (rect.top - menu.offsetHeight - 5) + 'px';
            }
        } else {
            menu.classList.add('hidden');
        }
        const closeDropdown = (e) => {
            if (!document.getElementById(`dropdown-${id}`).contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
                document.removeEventListener('click', closeDropdown);
            }
        };
        if (!menu.classList.contains('hidden')) {
            setTimeout(() => document.addEventListener('click', closeDropdown), 0);
        }
    }

    async function viewRecord(id) {
        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}`);
            const result   = await response.json();
            if (result.success) {
                const data = result.data;
                const plotSizesHtml = data.plot_sizes.map(p => `<li>${p.plot_number}: <strong>${p.plot_size} Sqm</strong></li>`).join('');
                Swal.fire({
                    title: 'Separation Details',
                    html: `
                        <div class="text-left text-sm space-y-3 p-2">
                            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <div><p class="text-[10px] font-black text-slate-400 uppercase">Applicant</p><p class="font-bold text-slate-800">${data.applicant_name || '—'}</p></div>
                                <div><p class="text-[10px] font-black text-slate-400 uppercase">File No</p><p class="font-bold text-slate-800 whitespace-nowrap">${data.file_no}</p></div>
                                <div><p class="text-[10px] font-black text-slate-400 uppercase">Status</p><span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase ${data.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700'}">${data.status}</span></div>
                                <div><p class="text-[10px] font-black text-slate-400 uppercase">No. of Plots</p><p class="font-bold text-slate-800">${data.num_plots}</p></div>
                                <div class="bg-blue-50 p-2 rounded-lg border border-blue-100 col-span-2"><p class="text-[10px] font-black text-blue-600 uppercase">Application Fee (NGN)</p><p class="font-bold text-blue-700">₦${new Intl.NumberFormat('en-NG').format(data.knupda_fee || 0)}</p></div>
                            </div>
                            <div class="space-y-1"><p class="text-[10px] font-black text-slate-400 uppercase">File Title</p><p class="text-slate-600">${data.file_title}</p></div>
                            <div class="space-y-1"><p class="text-[10px] font-black text-slate-400 uppercase">Fragment Sizes</p><ul class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600 list-disc ml-4">${plotSizesHtml}</ul></div>
                            <div class="mt-4 pt-4 border-t border-slate-100"><p class="text-[10px] font-black text-slate-400 uppercase mb-1">Property Location</p><p class="text-sm font-bold text-slate-700">${data.house_no ? 'House No ' + data.house_no + ', ' : ''}${data.street_name || ''}, ${data.district || ''}, ${data.lga || ''}, ${data.state || ''}</p></div>
                        </div>`,
                    width: 600,
                    showConfirmButton: false,
                    showCloseButton: true
                });
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to fetch details.', 'error');
        }
    }

    async function openKnupdaModal(id) {
        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}`);
            const result   = await response.json();
            if (result.success) {
                const data = result.data;
                Swal.fire({
                    title: 'KAMMA Handshake',
                    html: `
                        <div class="text-left text-sm space-y-4 p-2">
                            <div class="bg-blue-50/50 rounded-2xl p-4 border border-blue-100 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div><label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Land Value (NGN)</label><input type="number" id="view_land_value_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" value="${data.land_value || '0'}" oninput="calculateViewSeparationFee(this)"></div>
                                    <div><label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (NGN)</label><input type="text" id="view_knupda_fee_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm font-bold bg-white" value="${data.knupda_fee || '0.00'}" readonly></div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">KAMMA Approval</label>
                                    <div class="flex items-center gap-4 mt-2">
                                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="knupda_status" value="Approved" ${data.knupda_status === 'Approved' ? 'checked' : ''} class="w-4 h-4 text-emerald-600"><span class="text-xs font-medium text-slate-700">Approve</span></label>
                                        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="knupda_status" value="Declined" ${data.knupda_status === 'Declined' ? 'checked' : ''} class="w-4 h-4 text-red-600"><span class="text-xs font-medium text-slate-700">Decline</span></label>
                                    </div>
                                </div>
                                <div><label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Remarks</label><textarea id="knupda_remarks_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" rows="2" placeholder="KAMMA feedback...">${data.knupda_remarks || ''}</textarea></div>
                                <button onclick="saveKnupda(${data.id})" class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">Update KAMMA Status</button>
                            </div>
                        </div>`,
                    width: 500,
                    showConfirmButton: false,
                    showCloseButton: true
                });
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to fetch details.', 'error');
        }
    }

    async function saveKnupda(id) {
        const landValue = document.getElementById('view_land_value_input').value;
        const fee       = document.getElementById('view_knupda_fee_input').value;
        const status    = document.querySelector('input[name="knupda_status"]:checked')?.value || 'Pending';
        const remarks   = document.getElementById('knupda_remarks_input').value;
        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}/knupda`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
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

    async function generateApplication(id) {
        const confirm = await Swal.fire({ title: 'Generate Application?', text: 'Are you sure you want to generate the application document?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Generate', confirmButtonColor: '#f97316' });
        if (!confirm.isConfirmed) return;
        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}/generate-application`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            });
            const res = await response.json();
            if (res.success) { await Swal.fire('Success', 'Application generated. You can now print it.', 'success'); location.reload(); }
        } catch (error) {
            Swal.fire('Error', 'Failed to generate application', 'error');
        }
    }

    async function generateRecommendation(id) {
        const confirm = await Swal.fire({ title: 'Generate Recommendation?', text: 'Are you sure you want to generate the recommendation document?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Generate', confirmButtonColor: '#10b981' });
        if (!confirm.isConfirmed) return;
        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}/generate-recommendation`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            });
            const res = await response.json();
            if (res.success) { await Swal.fire('Success', 'Recommendation generated. You can now print it.', 'success'); location.reload(); }
            else { Swal.fire('Error', 'Recommendation can only be generated for Approved applications.', 'error'); }
        } catch (error) {
            Swal.fire('Error', 'Failed to generate recommendation', 'error');
        }
    }

    /**
     * Approve a separation application.
     *
     * The page carried a Delete and a KAMMA Handshake but no way to approve, even
     * though plot-separation.approve has always existed - so a separation could only
     * be approved as a side effect of the handshake. That side effect is gone: the
     * handshake is now the Master JSI, which clears the application but does not
     * decide it, and this is the only way an approval is given.
     */
    async function openApprovalDecision(id, canApprove = true) {
        const result = await Swal.fire({
            title: 'Approval decision',
            text: canApprove ? 'Choose whether to approve or reject this application.' : 'The Master JSI is not approved, so only rejection is available.',
            input: 'radio',
            inputOptions: canApprove ? { approve: 'Approve', reject: 'Reject' } : { reject: 'Reject' },
            inputValidator: value => !value && 'Select an action to continue.',
            showCancelButton: true,
            confirmButtonText: 'Continue'
        });
        if (!result.isConfirmed) return;
        return result.value === 'approve' ? approveRecord(id) : rejectRecord(id);
    }

    async function approveRecord(id) {
        const result = await Swal.fire({
            title: 'Approve Application?',
            text: 'Are you sure you want to approve this separation application?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, approve'
        });

        if (!result.isConfirmed) return;

        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}/approve`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            });
            const res = await response.json();

            if (res.success) {
                Swal.fire('Approved!', res.message, 'success');
                location.reload();
            } else {
                Swal.fire('Error!', res.message || 'Failed to approve record.', 'error');
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to approve record.', 'error');
        }
    }

    /**
     * Reject a separation application - the other answer to the question Approve asks,
     * so it sits beside it on the menu. The reason is carried to the controller, which
     * files it in remarks; the other parcel-update pages ask for it the same way.
     */
    async function rejectRecord(id) {
        const { value: reason } = await Swal.fire({
            title: 'Reject Application',
            input: 'textarea',
            inputLabel: 'Reason for rejection',
            inputPlaceholder: 'Enter reason here...',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            confirmButtonText: 'Reject'
        });

        if (reason === undefined) return;

        try {
            const response = await fetch(`{{ url('plot-separation') }}/${id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason })
            });
            const res = await response.json();

            if (res.success) {
                Swal.fire('Rejected!', res.message, 'success');
                location.reload();
            } else {
                Swal.fire('Error!', res.message || 'Failed to reject record.', 'error');
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to reject record.', 'error');
        }
    }

    async function deleteRecord(id) {
        const result = await Swal.fire({ title: 'Delete Application?', text: 'Are you sure? This cannot be undone.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', confirmButtonText: 'Yes, delete it' });
        if (result.isConfirmed) {
            try {
                const response = await fetch(`{{ url('plot-separation') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                const res = await response.json();
                if (res.success) { Swal.fire('Deleted!', res.message, 'success'); location.reload(); }
                else { Swal.fire('Error!', res.message || 'Failed to delete record.', 'error'); }
            } catch (error) {
                Swal.fire('Error!', 'An error occurred.', 'error');
            }
        }
    }

    function previewSitePlan(input) {
        const container      = document.getElementById('site_plan_preview_container');
        const img            = document.getElementById('site_plan_preview_img');
        const pdfPlaceholder = document.getElementById('pdf_preview_placeholder');
        if (input.files && input.files[0]) {
            const file   = input.files[0];
            const reader = new FileReader();
            container.classList.remove('hidden');
            if (file.type === 'application/pdf') {
                img.classList.add('hidden');
                pdfPlaceholder.classList.remove('hidden');
            } else if (file.type.startsWith('image/')) {
                pdfPlaceholder.classList.add('hidden');
                img.classList.remove('hidden');
                reader.onload = (e) => { img.src = e.target.result; };
                reader.readAsDataURL(file);
            } else {
                img.classList.add('hidden');
                pdfPlaceholder.classList.remove('hidden');
            }
            if (window.lucide) window.lucide.createIcons();
        } else {
            clearSitePlan();
        }
    }

    function clearSitePlan() {
        const input          = document.getElementById('site_plan_input');
        const container      = document.getElementById('site_plan_preview_container');
        const img            = document.getElementById('site_plan_preview_img');
        const pdfPlaceholder = document.getElementById('pdf_preview_placeholder');
        if (input) input.value = '';
        if (container) container.classList.add('hidden');
        if (img) { img.src = '#'; img.classList.add('hidden'); }
        if (pdfPlaceholder) pdfPlaceholder.classList.add('hidden');
    }

    function docFileChanged(input, btnId) {
        const btn = document.getElementById(btnId);
        if (btn) btn.classList.toggle('hidden', !(input.files && input.files.length > 0));
        if (window.lucide) window.lucide.createIcons();
    }

    function openDocPreview(inputId, label) {
        const input = document.getElementById(inputId);
        if (!input || !input.files || !input.files[0]) return;
        const file = input.files[0];
        const url  = URL.createObjectURL(file);
        document.getElementById('doc-preview-title').textContent = label;
        const img    = document.getElementById('doc-preview-img');
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

    document.addEventListener('DOMContentLoaded', function () {
        if (window.GlobalFileNoModal) GlobalFileNoModal.init();
    });
</script>
@endsection
