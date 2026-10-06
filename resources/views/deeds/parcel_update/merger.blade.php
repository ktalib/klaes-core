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
    $jsiCleared = \App\Support\MasterJsiGate::clearedMap('merger', $records);
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
        color: #e67e22;
        text-transform: uppercase;
    }
    .total-box {
        background-color: #fffaf0;
        border: 1px dashed #e67e22;
        padding: 10px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .readonly { background: #f9f9f9; color: #7f8c8d; font-weight: bold; cursor: not-allowed; }
    .location-card {
        display: none;
    }
    .location-card.active {
        display: block;
        animation: fadeIn 0.3s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Plot Merger',
        'PageDescription' => 'Manage Plot Merger applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }}</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Plot Merger</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->total()) }} record(s) found</p>
                </div>
                <div class="flex items-center gap-3">
                    <form action="{{ route('plot-merger.index') }}" method="GET" class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </form>
                    <button type="button" onclick="openCreateModal()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-orange-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-orange-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Merger
                    </button>
                </div>
            </div>

            {{-- Status Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                {{-- Total --}}
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
                {{-- Daily --}}
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
                {{-- Pending --}}
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
                {{-- Approved --}}
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
                {{-- Rejected --}}
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
                                <th class="px-4 py-3 text-left">Applicant Name</th>
                                <th class="px-4 py-3 text-left">Temp File No</th>
                                <th class="px-4 py-3 text-left">Source Plots</th>
                                <th class="px-4 py-3 text-left">Consolidated Title</th>
                                <th class="px-4 py-3 text-left">Plots</th>
                                <th class="px-4 py-3 text-left">Location</th>
                                <th class="px-4 py-3 text-left">Source</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($records as $record)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $records->firstItem() + $loop->index }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">{{ $record->applicant_name ?: '—' }}</div>
                                       
                                    </td>
                                    <td class="px-4 py-3 font-bold text-orange-700">{{ $record->temp_file_no }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @php
                                            $sourceFilesData = $record->plotSizes->map(fn($p) => [
                                                'number' => $p->source_file_no ?? $p->plot_number, // File Number (fallback to plot_number for old records)
                                                'plot_no' => $p->source_file_no ? $p->plot_number : '—', // Plot No (if new record)
                                                'title'  => $p->source_file_title ?? '—',
                                                'size'   => $p->plot_size ?? 0
                                            ])->filter(fn($p) => !empty($p['number']))->values()->toArray();
                                            $count = count($sourceFilesData);
                                            $firstFile = $sourceFilesData[0]['number'] ?? $record->file_no;
                                        @endphp
                                        
                                        <div class="flex flex-col items-start gap-0.5">
                                            @if($firstFile)
                                                <a href="javascript:void(0)" 
                                                   onclick="showSourcePlotsModal({{ $record->id }}, {{ json_encode($sourceFilesData) }})" 
                                                   class="text-blue-600 hover:text-blue-800 font-bold font-mono text-sm underline decoration-blue-100 underline-offset-4 hover:decoration-blue-400 transition-all">
                                                    {{ $firstFile }}
                                                </a>
                                            @else
                                                <span class="text-xs font-bold text-slate-400 italic">No Source Data</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="text-slate-700 font-semibold leading-tight line-clamp-2" title="{{ $record->file_title }}">{{ $record->file_title }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">{{ $record->num_plots }}</td>
                                    <td class="px-4 py-3 text-slate-600 text-xs">
                                        {{ implode(', ', array_filter([
                                            $record->plot_no ? 'Plot '.$record->plot_no : null,
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
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            /**
                                             * May this merger still be edited?
                                             *
                                             * A rejected one carries a decision; a commissioned one has its merged
                                             * file minted and its sources decommissioned behind it, so a further
                                             * plot is a new instruction rather than a wider old one. Everything
                                             * else may be added to.
                                             */
                                            $canUpdate = !in_array($record->status, ['rejected', 'commissioned'], true);
                                        @endphp

                                        <div class="relative inline-block text-left" id="dropdown-{{ $record->id }}">
                                            <button type="button" onclick="toggleDropdown({{ $record->id }})" class="p-2 hover:bg-slate-100 text-slate-600 rounded-full transition">
                                                <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                            </button>
                                           
                                            <div id="menu-{{ $record->id }}" class="hidden fixed z-[999] mt-2 w-max origin-top-right rounded-xl bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none divide-y divide-slate-100 whitespace-nowrap">
                                                <div class="py-1">
                                                    <button onclick="viewRecord({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                        <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i> View Details
                                                    </button>
                                                    @if($canUpdate)
                                                        <button onclick="openUpdateModal({{ $record->id }})" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
                                                            <i data-lucide="square-pen" class="w-4 h-4 text-amber-500"></i> Update / Add Plots
                                                        </button>
                                                    @else
                                                        <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50"
                                                                title="{{ $record->status === 'rejected' ? 'A rejected application cannot be edited' : 'This merger has been commissioned — capture a new merger for further plots' }}">
                                                            <i data-lucide="square-pen" class="w-4 h-4 text-slate-300"></i> Update / Add Plots
                                                        </button>
                                                    @endif
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
                                                            <a href="{{ route('plot-merger.print-recommendation', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
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
                                                         is the same question's other answer and sits beside it - its
                                                         handler has always existed here, it had simply never been put
                                                         on the menu. Same order of business the duplex register
                                                         follows. --}}
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
                                                            <a href="{{ route('plot-merger.print-application', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
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
                                    <td colspan="8" class="px-4 py-10 text-center text-slate-400">No records found.</td>
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
                    {{-- The same form captures and edits, so its title says which. --}}
                    <h3 id="modalTitle" class="text-xl font-bold text-slate-900">New Plot Merger</h3>
                    <p id="modalSubtitle" class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">Deeds / Parcel Update</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 transition" onclick="closeModal()">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <form id="create-form" class="flex-1 overflow-y-auto p-8">
                @csrf
                {{-- Which sidebar this page was opened from, travelling WITH the capture.

                     ParcelUpdateSource::fromRequest() reads `mode` off the request that
                     creates the record, but the form posts to a bare route URL — no query
                     string, and until now no field either — so the mode never arrived and
                     every merger ever captured was stamped Deeds. The page had the answer
                     all along; it just never sent it. --}}
                <input type="hidden" name="mode" value="{{ \App\Support\ParcelUpdateSource::normalise(request('mode')) }}">
                {{-- Shown only while editing: what an untouched file input does to a
                     document already attached, and whether plots may still be removed. --}}
                <div id="editNotice" class="hidden mb-6 rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-4">
                    <p class="text-xs font-black uppercase tracking-widest text-amber-700 mb-1">Editing an existing merger</p>
                    <p id="editNoticeText" class="text-xs text-amber-800 leading-relaxed"></p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Temp File No <span class="text-red-500">*</span></label>
                        <input type="text" name="temp_file_no" id="temp_file_no" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-sm font-bold font-mono readonly" placeholder="AUTO-GEN" readonly>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Plots to Merge <span class="text-red-500">*</span></label>
                        <input type="number" name="num_plots" id="num_plots" min="2" max="50" oninput="generateSources()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-sm font-bold text-orange-600">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Applicant Name</label>
                        <input type="text" name="applicant_name" id="applicant_name" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                    </div>
                    
                    <input type="hidden" name="file_no" id="file_no">
                    <input type="hidden" name="file_title" id="file_title">

                    <div class="md:col-span-3 section-box">
                        <span class="section-label text-orange-600">Plot Merger Financials</span>
                        <div class="bg-orange-50/50 p-4 rounded-xl border border-orange-100 flex items-center justify-between">
                            <div>
                                <label class="block text-[10px] font-bold text-orange-600 uppercase mb-1">Application Fee (Fixed)</label>
                                <p class="text-xl font-black text-orange-700">₦20,000.00</p>
                            </div>
                            <i data-lucide="calculator" class="w-8 h-8 text-orange-200"></i>
                            <input type="hidden" name="knupda_fee" value="20000">
                        </div>
                    </div>

                    <div class="md:col-span-3 section-box mt-6">
                        <span class="section-label">Individual Plot Sizes (Sqm)</span>
                        <div id="sourcesContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <p class="col-span-full text-slate-400 text-xs italic">Select and "Add" files above to define source sizes.</p>
                        </div>
                        <div class="total-box">
                            <label class="text-sm font-semibold text-slate-700">Total Merged Area:</label>
                            <input type="text" id="totalSize" class="readonly w-32 text-right border-none bg-transparent text-orange-700 font-bold" value="0.00 Ha" readonly>
                        </div>
                    </div>

                    <div class="md:col-span-3 section-box mt-6">
                        <div class="flex items-center justify-between mb-6">
                            <span class="section-label">Location Details (Plot <span id="current_plot_label">1</span> of <span id="total_plots_label">1</span>)</span>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="navLocation(-1)" class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-orange-600 hover:border-orange-200 hover:bg-orange-50 transition shadow-sm">
                                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                </button>
                                <button type="button" onclick="navLocation(1)" class="p-2 rounded-xl border border-slate-200 text-slate-400 hover:text-orange-600 hover:border-orange-200 hover:bg-orange-50 transition shadow-sm">
                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        <div id="location_cards_container">
                            <div class="p-12 text-center text-slate-400 italic text-sm border border-dashed border-slate-200 rounded-3xl bg-slate-50/30">
                                <i data-lucide="layers" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                                No plots added to merger yet. <br>Use the <span class="font-bold text-blue-600">Search</span> and <span class="font-bold text-emerald-600">+ Plus</span> buttons above.
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-3 mt-6">
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Application Plan (Site Plan)</label>
                        <div class="flex flex-col gap-4">
                            <input type="file" name="site_plan" id="site_plan_input" accept=".pdf,.png,.jpg,.jpeg"
                                class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                onchange="previewSitePlan(this)">

                            <div id="site_plan_preview_container" class="hidden relative w-full aspect-video rounded-2xl overflow-hidden border border-slate-200 bg-slate-50 flex items-center justify-center group">
                                <div id="site_plan_icon_preview" class="hidden flex-col items-center gap-2">
                                    <i data-lucide="file-text" class="w-16 h-16 text-slate-300"></i>
                                    <p id="site_plan_filename" class="text-xs font-medium text-slate-500"></p>
                                </div>
                                <img id="site_plan_img_preview" class="hidden w-full h-full object-contain" src="" alt="Site Plan Preview">

                                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                    <button type="button" onclick="clearSitePlan()" class="p-3 bg-red-500 text-white rounded-full hover:bg-red-600 transition shadow-lg">
                                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Supporting Documents --}}
                    <div class="md:col-span-3 section-box mt-2">
                        <span class="section-label">Supporting Documents</span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mt-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Proof of Ownership <span class="text-slate-400 normal-case font-normal">(C of O / R of O)</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="ownership_document" id="mrg_ownership_doc" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'mrg_btn_ownership','Proof of Ownership')">
                                    <button type="button" id="mrg_btn_ownership" onclick="openDocPreview('mrg_ownership_doc','Proof of Ownership')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-orange-50 text-orange-600 border border-orange-200 text-xs font-bold hover:bg-orange-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Application Letter</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="application_letter" id="mrg_app_letter" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'mrg_btn_app_letter','Application Letter')">
                                    <button type="button" id="mrg_btn_app_letter" onclick="openDocPreview('mrg_app_letter','Application Letter')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-orange-50 text-orange-600 border border-orange-200 text-xs font-bold hover:bg-orange-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Means of Identification <span class="text-slate-400 normal-case font-normal">(NIN / Passport / Driver's Licence)</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="means_of_id" id="mrg_means_id" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'mrg_btn_means_id','Means of Identification')">
                                    <button type="button" id="mrg_btn_means_id" onclick="openDocPreview('mrg_means_id','Means of Identification')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-orange-50 text-orange-600 border border-orange-200 text-xs font-bold hover:bg-orange-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tax Clearance Certificate</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="tax_clearance" id="mrg_tax_clearance" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'mrg_btn_tax_clearance','Tax Clearance Certificate')">
                                    <button type="button" id="mrg_btn_tax_clearance" onclick="openDocPreview('mrg_tax_clearance','Tax Clearance Certificate')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-orange-50 text-orange-600 border border-orange-200 text-xs font-bold hover:bg-orange-100 transition">
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
                {{-- One button, two jobs; the label is set by setModalMode(). --}}
                <button type="button" id="saveSubmitBtn" onclick="submitForm()" class="px-6 py-2 rounded-xl bg-orange-600 text-white text-sm font-semibold hover:bg-orange-700 shadow-lg shadow-orange-500/30 transition">Submit</button>
            </div>
        </div>
    </div>

    {{-- Source Plots Modal --}}
    <div id="source-plots-modal" class="fixed inset-0 z-[10000] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeSourcePlotsModal()"></div>
        <div class="relative bg-white rounded-[2rem] shadow-2xl w-full max-w-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in duration-200">
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/30">
                        <i data-lucide="layers" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-slate-900 tracking-tight leading-none">Source Plots</h3>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1">List of merged files</p>
                    </div>
                </div>
                <button type="button" class="w-8 h-8 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-400 hover:text-slate-600 hover:border-slate-300 transition shadow-sm" onclick="closeSourcePlotsModal()">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="p-8">
                <div class="overflow-hidden border border-slate-200 rounded-[1.5rem] shadow-inner-sm bg-slate-50/30">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50/80 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                <th class="px-6 py-4 text-left">S/N</th>
                                <th class="px-6 py-4 text-left">File Number</th>
                                <th class="px-6 py-4 text-left">File Title</th>
                                <th class="px-6 py-4 text-left">Plot No</th>
                                <th class="px-6 py-4 text-right">Size</th>
                            </tr>
                        </thead>
                        <tbody id="source-plots-table-body" class="divide-y divide-slate-100 bg-white">
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 flex justify-center">
                    <button type="button" onclick="closeSourcePlotsModal()" class="px-8 py-2.5 rounded-2xl bg-slate-900 text-white text-xs font-black uppercase tracking-widest hover:bg-slate-800 transition shadow-lg shadow-slate-900/20">
                        Close Details
                    </button>
                </div>
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
    let currentLocationStep = 1;
    let selectedFileData = null;
    let addedFilesCount = 0;

    function toggleOtherInput(select) {
        const container = select.parentElement;
        const otherInput = container.querySelector('input[type="text"]');
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

    // Pre-store options to avoid losing them when clearing container.
    // Data is passed as JSON arrays and options are built in JS with an escaper, so a name
    // containing a backtick / ${ / quote can never break this <script> block (which previously
    // happened in production with names that contained such characters).
    const _esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const _opt = (val, label, selected) =>
        `<option value="${_esc(val)}"${selected ? ' selected' : ''}>${_esc(label)}</option>`;

    const _streetNames = @json($streetNames->pluck('name'));
    const _districtNames = @json($districts->pluck('name'));
    const _lgaNames = @json($lgas->pluck('name'));
    const _stateNames = @json($states->pluck('StateName'));

    const streetOpts = '<option value="">Select Street</option>'
        + _streetNames.map(n => _opt(n, String(n).toUpperCase())).join('')
        + '<option value="OTHER">OTHER</option>';
    const districtOpts = '<option value="">Select District</option>'
        + _districtNames.map(n => _opt(n, String(n).toUpperCase())).join('')
        + '<option value="OTHER">OTHER</option>';
    const lgaOpts = '<option value="">Select LGA</option>'
        + _lgaNames.map(n => _opt(n, String(n).toUpperCase())).join('');
    const stateOpts = _stateNames
        .map(n => _opt(n, String(n).toUpperCase(), n === 'Kano')).join('');

    /* ------------------------------------------------------------------
       Capture or edit: one form, two jobs
       ------------------------------------------------------------------
       `editingId` is null while capturing and holds the application id while
       editing — it is what decides where submitForm() posts.

       `minPlots` is the floor the plot count may not go below. A merger still in
       draft may be corrected downward to the statutory two; once it has left
       'pending' its schedule is what the recommendation memo was written on, so
       it may be added to but not cut down. The server checks this again.
       ------------------------------------------------------------------ */
    let editingId = null;
    let minPlots = 2;

    function setModalMode(editing, record) {
        const title = document.getElementById('modalTitle');
        const subtitle = document.getElementById('modalSubtitle');
        const btn = document.getElementById('saveSubmitBtn');
        const notice = document.getElementById('editNotice');
        const noticeText = document.getElementById('editNoticeText');
        const numInput = document.getElementById('num_plots');

        if (!editing) {
            if (title) title.innerText = 'New Plot Merger';
            if (subtitle) subtitle.innerText = 'Deeds / Parcel Update';
            if (btn) btn.innerText = 'Submit';
            if (notice) notice.classList.add('hidden');
            if (numInput) numInput.min = 2;
            return;
        }

        if (title) title.innerText = 'Update Plot Merger';
        if (subtitle) subtitle.innerText = 'Deeds / Parcel Update · ' + (record.temp_file_no || record.file_no || '');
        if (btn) btn.innerText = 'Save Changes';
        if (numInput) numInput.min = minPlots;

        const lines = ['Raise "Plots to Merge" to add plots; the plots already captured keep their details.'];
        if (minPlots > 2) {
            lines.push('This application is ' + (record.status || 'past draft') + ', so its ' + minPlots.toLocaleString() + ' plots can be added to but not removed.');
        }

        if (notice) notice.classList.remove('hidden');
        if (noticeText) noticeText.innerText = lines.join(' ');
    }

    function openCreateModal() {
        editingId = null;
        minPlots = 2;
        setModalMode(false);

        document.getElementById('create-form').reset();
        document.getElementById('sourcesContainer').innerHTML = '<p class="col-span-full text-slate-400 text-xs italic">Set number of plots to define source sizes.</p>';
        document.getElementById('location_cards_container').innerHTML = `
            <div class="p-12 text-center text-slate-400 italic text-sm border border-dashed border-slate-200 rounded-3xl bg-slate-50/30">
                <i data-lucide="layers" class="w-12 h-12 mx-auto mb-3 opacity-20"></i>
                No plots added to merger yet. <br>Use the <span class="font-bold text-blue-600">Search</span> and <span class="font-bold text-emerald-600">+ Plus</span> buttons above.
            </div>
        `;
        document.getElementById('totalSize').value = "0.00 Ha";
        // Auto-gen temp file no if empty
        document.getElementById('temp_file_no').value = 'TEMP-' + Date.now().toString().slice(-6);
        
        clearSitePlan();
        
        currentLocationStep = 0;
        addedFilesCount = 0;
        selectedFileData = null;
        document.getElementById('num_plots').value = 0;
        document.getElementById('current_plot_label').innerText = '0';
        document.getElementById('total_plots_label').innerText = '0';

        if (window.lucide) window.lucide.createIcons();
        document.getElementById('create-modal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('create-modal').classList.add('hidden');
    }

    function navLocation(dir) {
        const num = parseInt(document.getElementById('num_plots').value) || 1;
        
        if (currentLocationStep === 1 && dir === -1) {
            document.getElementById('create-form').scrollTop = 0;
            return;
        }

        let next = currentLocationStep + dir;
        if (next < 1 || next > num) return;

        document.querySelectorAll('.location-card').forEach(c => c.classList.remove('active'));
        const nextCard = document.getElementById(`location_card_${next}`);
        if (nextCard) {
            nextCard.classList.add('active');
            currentLocationStep = next;
            document.getElementById('current_plot_label').innerText = next;
        }
    }

    function previewSitePlan(input) {
        const container = document.getElementById('site_plan_preview_container');
        const imgPreview = document.getElementById('site_plan_img_preview');
        const iconPreview = document.getElementById('site_plan_icon_preview');
        const filenameLabel = document.getElementById('site_plan_filename');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            container.classList.remove('hidden');
            filenameLabel.innerText = file.name;

            if (file.type.match('image.*')) {
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                    imgPreview.classList.remove('hidden');
                    iconPreview.classList.add('hidden');
                }
                reader.readAsDataURL(file);
            } else {
                imgPreview.classList.add('hidden');
                iconPreview.classList.remove('hidden');
            }
        }
    }

    function clearSitePlan() {
        const input = document.getElementById('site_plan_input');
        const container = document.getElementById('site_plan_preview_container');
        if (input) input.value = '';
        if (container) container.classList.add('hidden');
    }

    {{-- Values reaching this template now come from a saved application as well as
         from the file-number modal, so they are escaped rather than trusted: an
         apostrophe or a quote in a file title used to end the attribute it sat in. --}}
    function getLocationCardHTML(index, fileNo = '', record = {}) {
        const fileTitle = _esc(record.file_name || '');
        fileNo = _esc(fileNo || '');
        record = Object.assign({}, record, {
            plot_no: _esc(record.plot_no || ''),
            house_no: _esc(record.house_no || ''),
        });
        return `
            <div class="mb-6 bg-slate-50/50 p-4 rounded-2xl border border-slate-200">
                <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                    <div class="flex-1 flex items-center gap-6">
                        <div class="flex flex-col gap-1">
                            <span class="px-2 py-0.5 w-fit bg-slate-600 text-white text-[9px] font-black rounded uppercase tracking-wider">Source File</span>
                            <span class="source-file-display text-sm font-bold text-slate-800 font-mono">${fileNo || 'NOT SELECTED'}</span>
                            <input type="hidden" name="location_details[${index}][source_file_no]" value="${fileNo}">
                        </div>
                        <div class="flex-1 min-w-0">
                            <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-tight mb-0.5">File Title</label>
                            <span class="source-title-display text-xs font-bold text-slate-600 block truncate" title="${fileTitle}">${fileTitle || '—'}</span>
                            <input type="hidden" name="location_details[${index}][source_file_title]" value="${fileTitle}">
                        </div>
                    </div>
                    <button type="button" onclick="openFileSelectorForCard(${index})" class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 text-blue-600 rounded-lg border border-blue-100 hover:bg-blue-100 transition text-xs font-bold shadow-sm whitespace-nowrap">
                        <i data-lucide="search" class="w-3.5 h-3.5"></i>
                        Select File
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Plot No <span class="text-red-500">*</span></label>
                        <input type="text" name="location_details[${index}][plot_no]" value="${record.plot_no || ''}" oninput="updateLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">House No</label>
                        <input type="text" name="location_details[${index}][house_no]" value="${record.house_no || ''}" oninput="updateLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Street Name</label>
                        <select name="location_details[${index}][street_name]" onchange="toggleOtherInput(this); updateLocationPreview(${index})" class="searchable-select w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                            ${streetOpts}
                        </select>
                        <input type="text" name="location_details[${index}][street_name_other]" class="hidden mt-2 w-full px-4 py-2.5 rounded-xl border border-blue-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm" placeholder="Specify Street Name" oninput="updateLocationPreview(${index})">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">District</label>
                        <select name="location_details[${index}][district]" onchange="toggleOtherInput(this); updateLocationPreview(${index})" class="searchable-select w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                            ${districtOpts}
                        </select>
                        <input type="text" name="location_details[${index}][district_other]" class="hidden mt-2 w-full px-4 py-2.5 rounded-xl border border-blue-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm" placeholder="Specify District" oninput="updateLocationPreview(${index})">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">LGA</label>
                        <select name="location_details[${index}][lga]" onchange="updateLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                            ${lgaOpts}
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">State</label>
                        <select name="location_details[${index}][state]" onchange="updateLocationPreview(${index})" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                            ${stateOpts}
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <div class="bg-white p-4 rounded-xl border border-dashed border-slate-300 shadow-sm">
                            <p class="text-[10px] font-bold text-slate-400 uppercase mb-1">Full Property Location Preview</p>
                            <p id="location_preview_${index}" class="text-sm font-bold text-slate-700 italic">No location details entered yet.</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function openFileSelectorForCard(index) {
        GlobalFileNoModal.open({
            callback: function(data) {
                backfillCard(index, data);
            }
        });
    }

    function backfillCard(index, data) {
        const card = document.getElementById(`location_card_${index}`);
        if (!card) return;

        // Set file number
        // Set file number
        const fileNoInput = card.querySelector(`input[name="location_details[${index}][source_file_no]"]`);
        if (fileNoInput) fileNoInput.value = data.fileNumber;
        
        const fileNoDisplay = card.querySelector(`.source-file-display`);
        if (fileNoDisplay) fileNoDisplay.innerText = data.fileNumber;

        const record = data.record || {};
        const title = (record.file_name || '').toUpperCase();

        const fileTitleDisplay = card.querySelector(`.source-title-display`);
        if (fileTitleDisplay) {
            fileTitleDisplay.innerText = title || '—';
            fileTitleDisplay.title = title;
        }

        const fileTitleInput = card.querySelector(`input[name="location_details[${index}][source_file_title]"]`);
        if (fileTitleInput) fileTitleInput.value = title;

        // Backfill details
        if (record.plot_no) card.querySelector(`input[name*="[plot_no]"]`).value = record.plot_no;
        if (record.house_no) card.querySelector(`input[name*="[house_no]"]`).value = record.house_no;
        if (record.street_name) card.querySelector(`select[name*="[street_name]"]`).value = record.street_name;
        if (record.district) card.querySelector(`select[name*="[district]"]`).value = record.district;
        if (record.lga) card.querySelector(`select[name*="[lga]"]`).value = record.lga;
        if (record.state) card.querySelector(`select[name*="[state]"]`).value = record.state;

        if (window.syncSearchableSelects) syncSearchableSelects(card);
        updateLocationPreview(index);

        // Sync with master fields if first card
        if (index === 1) {
            document.getElementById('file_no').value = data.fileNumber;
            if (record.file_name) {
                const title = record.file_name.toUpperCase();
                document.getElementById('file_title').value = title;
            }
        }
        
        if (window.lucide) window.lucide.createIcons();
    }

    function updateLocationPreview(index) {
        const card = document.getElementById(`location_card_${index}`);
        if (!card) return;

        const plot = card.querySelector(`input[name="location_details[${index}][plot_no]"]`)?.value || '';
        const house = card.querySelector(`input[name="location_details[${index}][house_no]"]`)?.value || '';
        
        let street = card.querySelector(`select[name="location_details[${index}][street_name]"]`)?.value || '';
        if (street.toUpperCase() === 'OTHER') {
            street = card.querySelector(`input[name="location_details[${index}][street_name_other]"]`)?.value || 'OTHER';
        }
        
        let district = card.querySelector(`select[name="location_details[${index}][district]"]`)?.value || '';
        if (district.toUpperCase() === 'OTHER') {
            district = card.querySelector(`input[name="location_details[${index}][district_other]"]`)?.value || 'OTHER';
        }
        
        const lga = card.querySelector(`select[name="location_details[${index}][lga]"]`)?.value || '';
        const state = card.querySelector(`select[name="location_details[${index}][state]"]`)?.value || '';
        
        let parts = [];
        if (plot) parts.push(plot);
        if (house) parts.push(`House No. ${house}`);
        if (street) parts.push(street);
        if (district) parts.push(district);
        if (lga) parts.push(lga);
        if (state) parts.push(state);
        
        const preview = document.getElementById(`location_preview_${index}`);
        if (preview) {
            preview.innerText = parts.length > 0 ? parts.join(', ') : 'No location details entered yet.';
        }
    }

    /**
     * What is currently on screen, keyed by 1-based plot index.
     *
     * generateSources() rebuilds every card from scratch, so without this, raising
     * "Plots to Merge" from 2 to 3 blanked the two plots already entered — which is
     * exactly the gesture "add a plot" is made of.
     */
    function harvestSources() {
        const kept = {};

        document.querySelectorAll('#location_cards_container .location-card').forEach(card => {
            const match = /location_card_(\d+)$/.exec(card.id || '');
            if (!match) return;

            const i = Number(match[1]);
            const val = sel => card.querySelector(sel)?.value || '';

            kept[i] = {
                source_file_no:    val(`input[name="location_details[${i}][source_file_no]"]`),
                // getLocationCardHTML reads the source title as `file_name`, the same
                // shape the file-number modal hands back.
                file_name:         val(`input[name="location_details[${i}][source_file_title]"]`),
                plot_no:           val(`input[name="location_details[${i}][plot_no]"]`),
                house_no:          val(`input[name="location_details[${i}][house_no]"]`),
                street_name:       val(`select[name="location_details[${i}][street_name]"]`),
                street_name_other: val(`input[name="location_details[${i}][street_name_other]"]`),
                district:          val(`select[name="location_details[${i}][district]"]`),
                district_other:    val(`input[name="location_details[${i}][district_other]"]`),
                lga:               val(`select[name="location_details[${i}][lga]"]`),
                state:             val(`select[name="location_details[${i}][state]"]`),
            };
        });

        // Sizes live in their own container; they are emitted in plot order.
        document.querySelectorAll('#sourcesContainer .source-input').forEach((el, idx) => {
            const i = idx + 1;
            kept[i] = kept[i] || {};
            kept[i].plot_size = el.value;
        });

        return kept;
    }

    /**
     * Put a saved card's dropdowns back.
     *
     * Only the text inputs can be set from the markup; the selects are built from a
     * shared option string with nothing selected, and a street or district saved as
     * OTHER is free text that no option carries at all.
     */
    function restoreCardSelects(index, saved) {
        const card = document.getElementById(`location_card_${index}`);
        if (!card) return;

        const pair = (name, otherName, value, otherValue) => {
            const select = card.querySelector(`select[name="location_details[${index}][${name}]"]`);
            const other = otherName
                ? card.querySelector(`input[name="location_details[${index}][${otherName}]"]`)
                : null;
            if (!select) return;

            const wanted = String(value || '').trim();
            if (!wanted) return;

            const match = Array.from(select.options).find(o =>
                o.value.trim().toLocaleLowerCase() === wanted.toLocaleLowerCase());

            if (match) {
                select.value = match.value;
            } else if (other) {
                // Saved as free text under OTHER — select OTHER and restore the text.
                const otherOption = Array.from(select.options).find(o => o.value.toUpperCase() === 'OTHER');
                if (otherOption) select.value = otherOption.value;
            }

            if (other) {
                const text = String(otherValue || (match ? '' : wanted)).trim();
                if (select.value.toUpperCase() === 'OTHER' && text) {
                    other.value = text;
                    other.classList.remove('hidden');
                }
            }
        };

        pair('street_name', 'street_name_other', saved.street_name, saved.street_name_other);
        pair('district', 'district_other', saved.district, saved.district_other);
        pair('lga', null, saved.lga);
        pair('state', null, saved.state);

        updateLocationPreview(index);
    }

    /**
     * Build the size boxes and the location cards for `num` plots.
     *
     * `prefill` is passed only when opening a saved application; otherwise whatever
     * is on screen is harvested and laid back in, so changing the plot count never
     * throws away work.
     */
    function generateSources(prefill) {
        const container = document.getElementById('sourcesContainer');
        const locContainer = document.getElementById('location_cards_container');
        const num = parseInt(document.getElementById('num_plots').value) || 0;

        const kept = prefill || harvestSources();

        addedFilesCount = num;
        container.innerHTML = '';
        locContainer.innerHTML = '';

        if (num > 0) {
            document.getElementById('total_plots_label').innerText = num;
            currentLocationStep = 1;
            document.getElementById('current_plot_label').innerText = '1';

            for (let i = 1; i <= num; i++) {
                const saved = kept[i] || {};

                const sizeDiv = document.createElement('div');
                sizeDiv.innerHTML = `
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Plot ${i} Size</label>
                    <input type="number" name="plot_sizes[]" value="${_esc(saved.plot_size ?? '')}" class="source-input w-full px-3 py-2 rounded-lg border border-slate-200 text-sm" step="0.01" placeholder="0.00" oninput="calculateTotal()">
                `;
                container.appendChild(sizeDiv);

                const locDiv = document.createElement('div');
                locDiv.id = `location_card_${i}`;
                locDiv.className = `location-card ${i === 1 ? 'active' : ''}`;
                locDiv.innerHTML = getLocationCardHTML(i, saved.source_file_no || '', saved);
                locContainer.appendChild(locDiv);
            }

            // Values first, then the searchable wrappers, so each wrapper is built
            // around the option that is actually selected.
            for (let i = 1; i <= num; i++) restoreCardSelects(i, kept[i] || {});
            if (window.initSearchableSelects) initSearchableSelects(locContainer);
            if (window.syncSearchableSelects) syncSearchableSelects(locContainer);
            if (window.lucide) window.lucide.createIcons();
            calculateTotal();
        } else {
            container.innerHTML = '<p class="col-span-full text-slate-400 text-xs italic">Set number of plots to define source sizes.</p>';
            locContainer.innerHTML = '<div class="p-8 text-center text-slate-400 italic text-xs border border-dashed border-slate-200 rounded-xl">Define number of plots to set location details.</div>';
            document.getElementById('totalSize').value = "0.00 Ha";
            document.getElementById('total_plots_label').innerText = '0';
            document.getElementById('current_plot_label').innerText = '0';
        }
    }

    function calculateTotal() {
        const inputs = document.querySelectorAll('.source-input');
        let total = 0;
        inputs.forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) total += val;
        });
        document.getElementById('totalSize').value = parseFloat(total.toFixed(10)) + " Ha";
    }

    /**
     * Re-open a captured merger in the same form.
     *
     * The source schedule comes back from the record rather than from the listing
     * row, so the first Save cannot write a summary back over the detail.
     *
     * Each source plot carries its own location, because the plots being merged are
     * separate parcels with separate file numbers. A row captured before those columns
     * existed has none, and opens with its location boxes blank — there is nothing to
     * show and nothing to infer, since a merger of separate parcels is exactly the case
     * where the merged parcel's own location need not be each source's.
     */
    async function openUpdateModal(id) {
        try {
            const response = await fetch(`{{ url('plot-merger') }}/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (!result.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'This merger could not be loaded.' });
                return;
            }

            const data = result.data;
            {{-- Row order is plot order: the controller edits the schedule by position,
                 so the form must show it in the order it was written. --}}
            const plots = (data.plot_sizes || []).slice()
                .sort((a, b) => (Number(a.id) || 0) - (Number(b.id) || 0));

            openCreateModal();

            editingId = id;
            minPlots = (data.status === 'pending') ? 2 : Math.max(plots.length, 2);
            setModalMode(true, data);

            document.getElementById('temp_file_no').value = data.temp_file_no || '';
            document.getElementById('applicant_name').value = data.applicant_name || '';
            document.getElementById('file_no').value = data.file_no || '';
            document.getElementById('file_title').value = data.file_title || '';

            const num = Number(data.num_plots) || plots.length;
            document.getElementById('num_plots').value = num;

            {{-- The capture writes placeholders where nothing was picked — 'Plot 3' for
                 a missing file number and an em dash for a missing title or plot no.
                 They go back as blanks so the card reads "NOT SELECTED" and the
                 officer is asked for the real thing rather than shown a stand-in. --}}
            const real = (value, placeholder) => {
                const v = String(value ?? '').trim();
                return (v === '' || v === '—' || v === placeholder) ? '' : v;
            };

            const prefill = {};
            plots.forEach((row, idx) => {
                const i = idx + 1;
                prefill[i] = {
                    source_file_no: real(row.source_file_no, 'Plot ' + i),
                    file_name: real(row.source_file_title),
                    plot_no: real(row.plot_number),
                    plot_size: (row.plot_size === null || row.plot_size === undefined) ? '' : Number(row.plot_size),
                    {{-- This plot's own location. A street or district that was typed
                         as OTHER comes back as free text that no dropdown option
                         carries; restoreCardSelects() is what puts those back under
                         OTHER rather than letting the select fall to blank. --}}
                    house_no:    real(row.house_no),
                    street_name: real(row.street_name),
                    district:    real(row.district),
                    lga:         real(row.lga),
                    state:       real(row.state),
                };
            });

            generateSources(prefill);

            if (window.lucide) window.lucide.createIcons();
            document.getElementById('create-modal').classList.remove('hidden');
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'This merger could not be loaded.' });
        }
    }

    async function submitForm() {
        const form = document.getElementById('create-form');
        const num = parseInt(document.getElementById('num_plots').value) || 0;

        if (num < 2) {
            Swal.fire({ icon: 'warning', title: 'Invalid Plot Count', text: 'Plot merger requires at least 2 plots.' });
            return;
        }

        {{-- The floor, checked here so the officer is told before anything is sent.
             The server checks it again and is the one that decides. --}}
        if (editingId !== null && num < minPlots) {
            Swal.fire({
                icon: 'warning',
                title: 'Plot count too low',
                text: `This application's ${minPlots.toLocaleString()} plots can be added to but not removed.`,
            });
            return;
        }

        // Validate Plot Sizes
        const sizeInputs = document.querySelectorAll('.source-input');
        let sizesValid = true;
        sizeInputs.forEach(input => {
            const val = parseFloat(input.value);
            if (isNaN(val) || val <= 0) {
                sizesValid = false;
                input.classList.add('border-red-500', 'bg-red-50');
            } else {
                input.classList.remove('border-red-500', 'bg-red-50');
            }
        });

        if (!sizesValid) {
            Swal.fire({ icon: 'warning', title: 'Incomplete Sizes', text: 'Please enter a valid size (greater than 0) for all plots being merged.' });
            return;
        }

        const formData = new FormData(form);
        const locations = {};
        for (let i = 1; i <= num; i++) {
            const card = document.getElementById(`location_card_${i}`);
            if (card) {
                locations[i] = {
                    source_file_no: card.querySelector(`input[name="location_details[${i}][source_file_no]"]`)?.value || '',
                    source_file_title: card.querySelector(`input[name="location_details[${i}][source_file_title]"]`)?.value || '',
                    plot_no: card.querySelector(`input[name="location_details[${i}][plot_no]"]`)?.value || '',
                    house_no: card.querySelector(`input[name="location_details[${i}][house_no]"]`)?.value || '',
                    street_name: (function() {
                        const sel = card.querySelector(`select[name="location_details[${i}][street_name]"]`);
                        if (sel?.value.toUpperCase() === 'OTHER') return card.querySelector(`input[name="location_details[${i}][street_name_other]"]`)?.value || 'OTHER';
                        return sel?.value || '';
                    })(),
                    district: (function() {
                        const sel = card.querySelector(`select[name="location_details[${i}][district]"]`);
                        if (sel?.value.toUpperCase() === 'OTHER') return card.querySelector(`input[name="location_details[${i}][district_other]"]`)?.value || 'OTHER';
                        return sel?.value || '';
                    })(),
                    lga: card.querySelector(`select[name="location_details[${i}][lga]"]`)?.value || '',
                    state: card.querySelector(`select[name="location_details[${i}][state]"]`)?.value || ''
                };
            }
        }

        // Validate Source Files
        let filesValid = true;
        for (let i = 1; i <= num; i++) {
            if (!locations[i] || !locations[i].source_file_no) {
                filesValid = false;
                const card = document.getElementById(`location_card_${i}`);
                if (card) card.classList.add('ring-2', 'ring-red-500');
            } else {
                const card = document.getElementById(`location_card_${i}`);
                if (card) card.classList.remove('ring-2', 'ring-red-500');
            }
        }

        if (!filesValid) {
            Swal.fire({ icon: 'warning', title: 'Missing Source Files', text: 'Please select a source file number for each plot being merged.' });
            return;
        }

        formData.append('location_details_json', JSON.stringify(locations));

        const url = editingId !== null
            ? `{{ url('plot-merger') }}/${editingId}/update`
            : '{{ route("plot-merger.store") }}';

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                await Swal.fire({ icon: 'success', title: 'Success', text: result.message, timer: 1500, showConfirmButton: false });
                location.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message || firstValidationError(result) || 'Validation failed' });
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred while saving.' });
        }
    }

    {{-- A 422 body carries `errors` keyed by field and no message. Showing the first
         one matters most for the plot-count checks, whose whole purpose is to say why
         nothing was saved. --}}
    function firstValidationError(result) {
        const errors = result?.errors;
        if (!errors) return null;
        const first = Object.values(errors)[0];
        return Array.isArray(first) ? first[0] : first;
    }



    function toggleDropdown(id) {
        const button = document.querySelector(`#dropdown-${id} button`);
        const menu = document.getElementById(`menu-${id}`);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        
        allMenus.forEach(m => { if(m.id !== `menu-${id}`) m.classList.add('hidden'); });
        
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
            const response = await fetch(`{{ url('plot-merger') }}/${id}`);
            const result = await response.json();
            if (result.success) {
                const data = result.data;
                let plotSizesHtml = data.plot_sizes.map(p => {
                    const plotNo = p.source_file_no ? p.plot_number : '—';
                    const fileNo = p.source_file_no ?? p.plot_number;
                    return `<li>Plot ${plotNo} in ${fileNo} <span class="text-[10px] text-slate-400">(${p.source_file_title || '—'})</span>: <strong>${p.plot_size} Sqm</strong></li>`;
                }).join('');
                
                Swal.fire({
                    title: 'Merger Details',
                    html: `
                        <div class="text-left text-sm space-y-3 p-2">
                            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">Applicant</p>
                                    <p class="font-bold text-slate-800">${data.applicant_name || '—'}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">Temp File No</p>
                                    <p class="font-bold text-orange-600">${data.temp_file_no}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">File No</p>
                                    <p class="font-bold text-slate-800">${data.file_no}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">Status</p>
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase ${data.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700'}">${data.status}</span>
                                </div>
                                <div class="bg-blue-50 p-2 rounded-lg border border-blue-100">
                                    <p class="text-[10px] font-black text-blue-600 uppercase">Application Fee (NGN)</p>
                                    <p class="font-bold text-blue-700">₦${new Intl.NumberFormat('en-NG').format(data.knupda_fee || 20000)}</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">File Title</p>
                                <p class="text-slate-600">${data.file_title}</p>
                            </div>

                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">Merged Plots (${data.num_plots})</p>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600 list-disc ml-4">
                                    ${plotSizesHtml}
                                </ul>
                            </div>





                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Property Location</p>
                                {{-- No plot number: a full property location does not carry it. --}}
                                <p class="text-sm font-bold text-slate-700">${data.street_name || ''}, ${data.district || ''}, ${data.lga || ''}, ${data.state || ''}</p>
                            </div>
                        </div>
                    `,
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
            const response = await fetch(`{{ url('plot-merger') }}/${id}`);
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
                                        <input type="text" id="knupda_fee_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm font-bold bg-white" value="${data.knupda_fee || '20000'}" readonly>
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
                                <button onclick="saveKnupda(${data.id})" class="w-full py-2.5 bg-blue-600 text-white rounded-xl text-[10px] font-black uppercase hover:bg-blue-700 transition shadow-lg shadow-blue-500/20">
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

    async function saveKnupda(id) {
        const landValue = document.getElementById('knupda_land_value_input').value;
        const fee = document.getElementById('knupda_fee_input').value;
        const status = document.querySelector('input[name="knupda_status"]:checked')?.value || 'Pending';
        const remarks = document.getElementById('knupda_remarks_input').value;

        try {
            const response = await fetch(`{{ url('plot-merger') }}/${id}/knupda`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
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

    async function generateApplication(id) {
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
            const response = await fetch(`{{ url('plot-merger') }}/${id}/generate-application`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
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

    async function generateRecommendation(id) {
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
            const response = await fetch(`{{ url('plot-merger') }}/${id}/generate-recommendation`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
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
            text: "Are you sure you want to approve this merger application?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, approve'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch(`{{ url('plot-merger') }}/${id}/approve`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Approved!', res.message, 'success');
                    location.reload();
                }
            } catch (error) {
                Swal.fire('Error!', 'Failed to approve record.', 'error');
            }
        }
    }

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

        if (reason !== undefined) {
            try {
                const response = await fetch(`{{ url('plot-merger') }}/${id}/reject`, {
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
                }
            } catch (error) {
                Swal.fire('Error!', 'Failed to reject record.', 'error');
            }
        }
    }

    async function deleteRecord(id) {
        const result = await Swal.fire({
            title: 'Delete Application?',
            text: "Are you sure you want to delete this application? This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, delete it'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch(`{{ url('plot-merger') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Deleted!', res.message, 'success');
                    location.reload();
                } else {
                    Swal.fire('Error!', res.message || 'Failed to delete record.', 'error');
                }
            } catch (error) {
                Swal.fire('Error!', 'An error occurred.', 'error');
            }
        }
    }

    function showSourcePlotsModal(recordId, plots) {
        const tbody = document.getElementById('source-plots-table-body');
        tbody.innerHTML = '';
        
        if (Array.isArray(plots) && plots.length > 0) {
            plots.forEach((plot, index) => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-50 transition-colors';
                row.innerHTML = `
                    <td class="px-6 py-4 text-slate-400 font-mono text-[11px] font-bold">${String(index + 1).padStart(2, '0')}</td>
                    <td class="px-6 py-4 font-black font-mono text-blue-700 text-sm whitespace-nowrap">${plot.number}</td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-bold text-slate-500 uppercase truncate max-w-[250px] block" title="${plot.title}">${plot.title}</span>
                    </td>
                    <td class="px-6 py-4 font-black text-slate-700 text-sm whitespace-nowrap">${plot.plot_no}</td>
                    <td class="px-6 py-4 text-right">
                        <span class="text-sm font-black text-slate-800 whitespace-nowrap">${new Intl.NumberFormat('en-US', {minimumFractionDigits: 2}).format(plot.size)}</span>
                    </td>
                `;
                tbody.appendChild(row);
            });
        } else {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-slate-400 italic text-sm">
                        No source plots found for this record.
                    </td>
                </tr>
            `;
        }
        
        document.getElementById('source-plots-modal').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
        
        // Disable body scroll
        document.body.style.overflow = 'hidden';
    }

    function closeSourcePlotsModal() {
        document.getElementById('source-plots-modal').classList.add('hidden');
        // Re-enable body scroll
        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (window.GlobalFileNoModal) GlobalFileNoModal.init();
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
