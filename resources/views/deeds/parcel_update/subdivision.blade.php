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
    $jsiCleared = \App\Support\MasterJsiGate::clearedMap('subdivision', $records);
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
        'PageTitle' => 'Plot Subdivision',
        'PageDescription' => 'Manage Plot Subdivision applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }}</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Plot Subdivision</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->total()) }} record(s) found</p>
                </div>
                <div class="flex items-center gap-3">
                    <form action="{{ route('plot-subdivision.index') }}" method="GET" class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </form>
                    <button type="button" onclick="openCreateModal()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Subdivision
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
                                <th class="px-4 py-3 text-left">#</th>
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
                                    <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $record->id }}</td>
                                    <td class="px-4 py-3 text-slate-700 font-bold">{{ $record->applicant_name ?: '—' }}</td>
                                    <td class="px-4 py-3 font-bold text-slate-700 whitespace-nowrap">{{ $record->file_no }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $record->file_title }}</td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ $record->num_plots }}
                                        {{-- Commissioning runs in chunks of 200, so show how much of the
                                             subdivision has actually been minted so far. --}}
                                        @if($record->commissionedCount() > 0 && !$record->isCommissioningComplete())
                                            <span class="ml-1 inline-flex items-center rounded-md bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800"
                                                  title="{{ $record->commissionedCount() }} commissioned in {{ count($record->commissionedBatches()) }} batch(es), {{ $record->remainingPlots() }} remaining">
                                                {{ $record->commissionedCount() }}/{{ $record->num_plots }} done
                                            </span>
                                        @elseif($record->commissionedCount() > 0)
                                            <span class="ml-1 inline-flex items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800"
                                                  title="Commissioned in {{ count($record->commissionedBatches()) ?: 1 }} batch(es)">
                                                all commissioned
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 text-xs">
                                        {{ implode(', ', array_filter([
                                            $record->house_no ? 'House No '.$record->house_no : null,
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
                                    <td class="px-4 py-3 text-slate-500 text-xs">{{ $record->created_at->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-center">
                                            @php
                                                /**
                                                 * May this application still be edited?
                                                 *
                                                 * A rejected one carries a decision, and a fully commissioned one has
                                                 * every plot minted behind it — more plots there are a new application.
                                                 * Everything else, including an application part-way through
                                                 * commissioning, may be added to; the plots already minted are locked
                                                 * in the form and read past by the controller.
                                                 */
                                                $canUpdate = !in_array($record->status, ['rejected', 'commissioned'], true)
                                                    && !($record->commissionedCount() > 0 && $record->isCommissioningComplete());
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
                                                                    title="{{ $record->status === 'rejected' ? 'A rejected application cannot be edited' : 'Every plot has been commissioned — capture a new subdivision for further plots' }}">
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
                                                                <a href="{{ route('plot-subdivision.print-recommendation', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
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
                                                        @if($record->commissionedCount() > 0 || $record->status === 'commissioned')
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Approved — {{ $record->commissionedCount() }} of {{ $record->num_plots }} plots already commissioned">
                                                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300"></i> Approved
                                                            </button>
                                                        @elseif(in_array($record->status, ['approved', 'rejected'], true))
                                                            <button disabled class="flex items-center w-full px-4 py-2.5 text-sm text-slate-400 gap-2 cursor-not-allowed bg-slate-50/50" title="Already approved{{ ($jsiCleared[$record->id] ?? false) ? ' via the Master JSI' : '' }}">
                                                                <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-300"></i> Approve / Reject
                                                            </button>
                                                        @else
                                                            <button onclick="openApprovalDecision({{ $record->id }}, {{ ($jsiCleared[$record->id] ?? false) ? 'true' : 'false' }}, {{ ($jsiCleared[$record->id] ?? false) ? 'false' : 'true' }})" class="flex items-center w-full px-4 py-2.5 text-sm text-emerald-700 hover:bg-emerald-50 gap-2 font-bold">
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
                                                                <a href="{{ route('plot-subdivision.print-application', $record->id) }}" target="_blank" class="flex items-center w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 gap-2">
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
                    {{-- The same form captures and edits, so its title says which. --}}
                    <h3 id="modalTitle" class="text-xl font-bold text-slate-900">New Subdivision</h3>
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
                     every capture was stamped Deeds, including the ones made from the Land
                     sidebar. The page had the answer all along; it just never sent it.

                     Hidden rather than derived server-side from the referer: the referer can
                     be stripped or stale, and this column is meant to be a record of which
                     module raised the application, not a guess at it. --}}
                <input type="hidden" name="mode" value="{{ \App\Support\ParcelUpdateSource::normalise(request('mode')) }}">
                {{-- Shown only while editing: the two things about an update that are not
                     obvious from the form, namely which plots are frozen and what an
                     untouched file input does to a document already attached. --}}
                <div id="editNotice" class="hidden mb-6 rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-4">
                    <p class="text-xs font-black uppercase tracking-widest text-amber-700 mb-1">Editing an existing application</p>
                    <p id="editNoticeText" class="text-xs text-amber-800 leading-relaxed"></p>
                </div>
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
                        <input type="number" name="num_plots" id="num_plots" min="1" max="{{ \App\Http\Controllers\Deeds\ParcelUpdate\PlotSubdivisionController::MAX_PLOTS }}" oninput="scheduleGenerateFragments()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm">
                    </div>


                    <div class="md:col-span-3 section-box">
                        <span class="section-label">Subdivided Plots Details</span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Land Value (NGN) <span class="text-red-500">*</span></label>
                                <input type="number" name="land_value" id="land_value_input" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm" placeholder="Enter land value" oninput="calculateSubdivisionFee()">
                            </div>
                            <div class="bg-blue-50/50 p-3 rounded-xl border border-blue-100">
                                <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (0.25%)</label>
                                <input type="text" id="knupda_fee_display" class="w-full bg-transparent border-none text-sm font-bold text-blue-700 p-0" value="0.00" readonly>
                                <input type="hidden" name="knupda_fee" id="knupda_fee_hidden" value="0">
                            </div>
                        </div>

                        <div id="applyAllRow" class="hidden flex flex-wrap items-end gap-3 mb-4 p-3 rounded-xl bg-slate-50 border border-slate-200">
                            <label for="apply_all_toggle" class="flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 bg-white cursor-pointer select-none">
                                <input type="checkbox" id="apply_all_toggle" onchange="toggleApplyAll()" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-xs font-bold uppercase text-slate-600">Apply Same Size To All Plots</span>
                            </label>
                            <div id="applyAllSizeWrapper" class="hidden flex-1 min-w-[220px]">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Dimensions For All Plots (m)</label>
                                {{-- The same widget as a plot cell, so the shortcut asks for what the
                                     plots themselves ask for. Copied down by applySizeToAllPlots(). --}}
                                <div id="apply_all_dims" class="plot-dims flex flex-wrap items-center gap-1.5" data-slot="all"></div>
                                <input type="number" id="apply_all_area" step="0.01" min="0" placeholder="Measured area (m2)"
                                       oninput="applySizeToAllPlots()"
                                       class="hidden mt-2 w-full px-3 py-2 rounded-lg border border-amber-200 bg-amber-50/40 text-sm">
                                <p id="apply_all_area_note" class="hidden text-[10px] text-slate-500 mt-1">
                                    Two sides give the area on their own. With any other number of sides, type the measured area from the plan.
                                </p>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-500 mb-2">
                            One box per side, in metres &mdash; use <b>+</b> for a parcel with more than two sides.
                        </p>
                        {{-- Shown only while a large grid is pending, so a pause after
                             typing "1000" reads as work rather than a dead box. --}}
                        <p id="fragmentsBuilding" class="hidden flex items-center gap-2 text-xs text-slate-500 mb-3">
                            <svg class="animate-spin h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Preparing plot fields&hellip;
                        </p>

                        <div id="fragmentsContainer" class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-4">
                            <p class="col-span-full text-slate-400 text-xs italic">Enter "No. of Plots" above to define subdivided plot sizes.</p>
                        </div>
                        <div class="total-box">
                            <label class="text-sm font-semibold text-slate-700">Total Subdivision Area:</label>
                            <input type="text" id="totalSize" class="readonly w-56 text-right border-none bg-transparent text-orange-700 font-bold" value="0 m2 &middot; 0.0000 Ha" readonly>
                        </div>
                    </div>



                    <div class="md:col-span-3 section-box mt-6">
                        <div class="flex items-center justify-between mb-6">
                            <span class="section-label">Mother Plot Location Details</span>
                        </div>

                        <div id="location_cards_container">
                            <!-- Initial card for Plot 1 -->
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
                                        <select name="location_details[1][street_name]" id="loc_street_name" onchange="toggleOtherInput(this); updateLocationPreview(1)" class="searchable-select w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm loc-street">
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
                                        <select name="location_details[1][district]" id="loc_district" onchange="toggleOtherInput(this); updateLocationPreview(1)" class="searchable-select w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm loc-district">
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
                                        <select name="location_details[1][lga]" id="loc_lga" onchange="updateLocationPreview(1)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm loc-lga">
                                            <option value="">Select LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ strtoupper($lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-600 uppercase mb-2">State</label>
                                        <select name="location_details[1][state]" id="loc_state" onchange="updateLocationPreview(1)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm loc-state">
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
                                    <input type="file" name="ownership_document" id="sub_ownership_doc" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'sub_btn_ownership','Proof of Ownership')">
                                    <button type="button" id="sub_btn_ownership" onclick="openDocPreview('sub_ownership_doc','Proof of Ownership')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Application Letter</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="application_letter" id="sub_app_letter" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'sub_btn_app_letter','Application Letter')">
                                    <button type="button" id="sub_btn_app_letter" onclick="openDocPreview('sub_app_letter','Application Letter')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Means of Identification <span class="text-slate-400 normal-case font-normal">(NIN / Passport / Driver's Licence)</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="means_of_id" id="sub_means_id" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'sub_btn_means_id','Means of Identification')">
                                    <button type="button" id="sub_btn_means_id" onclick="openDocPreview('sub_means_id','Means of Identification')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase mb-2">Tax Clearance Certificate</label>
                                <div class="flex items-center gap-2">
                                    <input type="file" name="tax_clearance" id="sub_tax_clearance" accept=".pdf,.png,.jpg,.jpeg"
                                        class="flex-1 min-w-0 px-4 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 text-sm bg-white"
                                        onchange="docFileChanged(this,'sub_btn_tax_clearance','Tax Clearance Certificate')">
                                    <button type="button" id="sub_btn_tax_clearance" onclick="openDocPreview('sub_tax_clearance','Tax Clearance Certificate')"
                                        class="hidden shrink-0 flex items-center gap-1 px-3 py-2.5 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 text-xs font-bold hover:bg-blue-100 transition">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview
                                    </button>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1 italic">PDF, PNG, JPG · max 5 MB</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Save progress.

                 Submitting used to do nothing visible until the server answered, which on
                 a large layout is a long silence with a live transaction behind it -- and
                 a silent button invites a second click.

                 The bar is REAL: it is the upload of the request body (XHR
                 upload.onprogress), which is the part that genuinely takes time on a slow
                 link. Once the body is up, the server is writing and cannot report its own
                 progress from inside one atomic request, so the card stops pretending to
                 measure and names the phase instead. Nothing here is driven by a timer. --}}
            <div id="saveProgress" class="hidden px-8 py-5 border-t border-slate-100 bg-white">
                <div class="flex items-center gap-3 mb-3">
                    <svg class="animate-spin h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <p id="saveProgressTitle" class="text-sm font-semibold text-slate-800">Saving subdivision</p>
                    <span id="saveProgressPct" class="ml-auto text-xs font-mono text-slate-500"></span>
                </div>

                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden mb-3">
                    <div id="saveProgressBar" class="h-full w-0 rounded-full bg-blue-600 transition-all duration-200"></div>
                </div>

                <p id="saveProgressPhase" class="text-xs text-slate-600">Preparing&hellip;</p>
                <p class="text-[11px] text-slate-400 mt-1">Do not close this window.</p>
            </div>

            <div id="saveActions" class="px-8 py-4 border-t border-slate-100 flex justify-end gap-3 bg-slate-50">
                <button type="button" onclick="closeModal()" class="px-6 py-2 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-100 transition">Cancel</button>
                <button type="button" id="saveSubmitBtn" onclick="submitForm()" class="px-6 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 shadow-lg shadow-blue-500/30 transition">Submit</button>
                {{-- One button, two jobs; the label is set by setModalMode(). --}}
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

    function toggleOtherInput(select) {
        const otherInputId = select.id + '_other';
        const otherInput = document.getElementById(otherInputId);
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

    /* ------------------------------------------------------------------
       Capture or edit: one form, two jobs
       ------------------------------------------------------------------
       `editingId` is null while capturing and holds the application id while
       editing. It is what decides where submitForm() posts, so nothing else
       needs to know which mode the form is in.

       `lockedPlots` is how many of this application's plots have already been
       commissioned. Those plots have file numbers minted against them, so the
       form shows them read-only and the controller reads past them however they
       come back — the lock here is courtesy, the one on the server is the rule.

       `minPlots` is the floor the plot count may not go below: the plots already
       minted, or — once the application has left 'pending' — the whole schedule
       the recommendation memo was written on.
       ------------------------------------------------------------------ */
    let editingId = null;
    let lockedPlots = 0;
    let minPlots = 1;

    /** Is this plot already commissioned, and so not the form's to change? */
    function isLocked(slot) {
        return editingId !== null && String(slot) !== 'all' && Number(slot) < lockedPlots;
    }

    function setModalMode(editing, record) {
        const title = document.getElementById('modalTitle');
        const subtitle = document.getElementById('modalSubtitle');
        const btn = document.getElementById('saveSubmitBtn');
        const notice = document.getElementById('editNotice');
        const noticeText = document.getElementById('editNoticeText');
        const progressTitle = document.getElementById('saveProgressTitle');
        const numInput = document.getElementById('num_plots');

        if (!editing) {
            if (title) title.innerText = 'New Subdivision';
            if (subtitle) subtitle.innerText = 'Deeds / Parcel Update';
            if (btn) btn.innerText = 'Submit';
            if (progressTitle) progressTitle.innerText = 'Saving subdivision';
            if (notice) notice.classList.add('hidden');
            if (numInput) numInput.min = 1;
            return;
        }

        if (title) title.innerText = 'Update Subdivision';
        if (subtitle) subtitle.innerText = 'Deeds / Parcel Update · ' + (record.file_no || '');
        if (btn) btn.innerText = 'Save Changes';
        if (progressTitle) progressTitle.innerText = 'Saving changes';
        if (numInput) numInput.min = minPlots;

        const lines = ['Raise "No. of Plots" to add plots; the plots already captured keep their sizes.'];
        if (lockedPlots > 0) {
            lines.push(lockedPlots.toLocaleString() + ' plot(s) have already been commissioned. They are shown read-only and cannot be changed or removed.');
        } else if (minPlots > 1) {
            lines.push('This application is ' + (record.status || 'past draft') + ', so its ' + minPlots.toLocaleString() + ' plots can be added to but not removed.');
        }

        if (notice) notice.classList.remove('hidden');
        if (noticeText) noticeText.innerText = lines.join(' ');
    }

    function openCreateModal() {
        editingId = null;
        lockedPlots = 0;
        minPlots = 1;
        setModalMode(false);

        document.getElementById('create-form').reset();
        document.getElementById('fragmentsContainer').innerHTML = '<p class="col-span-full text-slate-400 text-xs italic">Enter "No. of Plots" above to define fragment sizes.</p>';

        // form.reset() does not reach markup this file drew itself, so the
        // apply-to-all row is emptied and re-collapsed by hand.
        document.getElementById('apply_all_dims').innerHTML = '';
        document.getElementById('apply_all_area').value = '';
        document.getElementById('apply_all_area').classList.add('hidden');
        document.getElementById('apply_all_area_note').classList.add('hidden');
        document.getElementById('applyAllRow').classList.add('hidden');
        toggleApplyAll();
        calculateTotal();

        // Reset location navigation
        currentLocationStep = 1;
        
        // Clear site plan preview
        clearSitePlan();
        
        document.getElementById('create-modal').classList.remove('hidden');
    }

    function closeModal() {
        {{-- Not while a save is in flight. Closing the modal does not cancel the request,
             so the officer would be left looking at the register with no idea whether a
             1000-plot capture had landed -- and no idempotency key to stop them capturing
             it a second time. --}}
        if (saveInFlight) return;

        document.getElementById('create-modal').classList.add('hidden');
    }

    function navLocation(dir) {
        const num = parseInt(document.getElementById('num_plots').value) || 1;
        let next = currentLocationStep + dir;
        
        if (next < 1) next = 1;
        if (next > num) next = num;
        
        if (next === currentLocationStep) return;
        
        // Hide current
        const currentCard = document.getElementById(`location_card_${currentLocationStep}`);
        if (currentCard) currentCard.classList.remove('active');
        
        // Show next
        currentLocationStep = next;
        const nextCard = document.getElementById(`location_card_${currentLocationStep}`);
        if (nextCard) nextCard.classList.add('active');
        
        // Update labels
        document.getElementById('current_plot_label').innerText = currentLocationStep;
    }

    function calculateSubdivisionFee() {
        const val = parseFloat(document.getElementById('land_value_input').value) || 0;
        const fee = val * 0.0025;
        
        const display = document.getElementById('knupda_fee_display');
        if (display) {
            display.value = '₦' + fee.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
        
        const hidden = document.getElementById('knupda_fee_hidden');
        if (hidden) {
            hidden.value = fee;
        }
    }

    window.calculateViewSubdivisionFee = function(inputElem) {
        const val = parseFloat(inputElem.value) || 0;
        const fee = val * 0.0025;
        const feeInput = document.getElementById('view_knupda_fee_input');
        if (feeInput) {
            feeInput.value = fee.toFixed(2);
        }
    };

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
       Plot sizes, taken as DIMENSIONS
       ------------------------------------------------------------------
       The same gesture as the Duplex parcel update (New APU): the officer reads
       the sides off the survey plan and types them; the area is DERIVED and never
       typed. A parcel is a polygon, not always a rectangle, so this is a list of
       any length - two sides multiply into an area, any other count cannot, and
       there the officer types the measured area from the plan rather than the
       screen inventing one from the perimeter.

       `plot_sizes[]` remains the field the controller stores, and it remains in
       m2 - the unit ParcelSizeSummary prints the memo in. The sides themselves
       travel alongside it as plot_dimensions[slot][].
       ------------------------------------------------------------------ */
    const M2_PER_HECTARE = 10000;
    const DERIVABLE_TERMS = 2;

    /** m2 from the sides, or null where it cannot honestly be derived. */
    function areaFromTerms(terms) {
        const nums = (terms || [])
            .map(v => parseFloat(v))
            .filter(v => isFinite(v) && v > 0);

        return nums.length === DERIVABLE_TERMS ? nums[0] * nums[1] : null;
    }

    /** 2842.3474 -> "0.2842 Ha". Four places: a plot is a fraction of a hectare. */
    function hectares(m2) {
        const ha = (Number(m2) || 0) / M2_PER_HECTARE;
        return ha.toLocaleString('en-NG', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) + ' Ha';
    }

    /** 1410 -> "1,410 m2"; 1410.3 -> "1,410.3 m2". */
    function areaText(n) {
        const v = Number(n) || 0;
        return (v === Math.round(v)
            ? v.toLocaleString('en-NG')
            : v.toLocaleString('en-NG', { minimumFractionDigits: 1, maximumFractionDigits: 2 }))
            + ' m²';
    }

    /** Always at least one box to type in, and two by default. */
    function normaliseTerms(value) {
        const terms = Array.isArray(value) ? value.slice() : [];
        return terms.length ? terms : ['', ''];
    }

    function dimWrap(slot) {
        return String(slot) === 'all'
            ? document.getElementById('apply_all_dims')
            : document.querySelector('.plot-dims[data-slot="' + slot + '"]');
    }

    /** The sides typed into one row of boxes, in order. */
    function termsOf(slot) {
        const wrap = dimWrap(slot);
        return wrap ? [...wrap.querySelectorAll('.plot-dim')].map(el => el.value) : [];
    }

    /**
     * One row of dimension boxes, as markup.
     *
     * `slot` is the plot's 0-based index, and it is what names the field:
     * plot_dimensions[0][], plot_dimensions[1][] ... so the sides reach the
     * controller grouped by the plot they belong to. The apply-to-all row uses
     * slot "all" and is deliberately unnamed - it is a shortcut, not a plot.
     */
    function dimBoxes(slot, terms) {
        const named = String(slot) === 'all' ? '' : ' name="plot_dimensions[' + slot + '][]"';
        const esc = v => (v === undefined || v === null) ? '' : String(v).replace(/"/g, '&quot;');

        {{-- A commissioned plot is shown, not offered: readonly rather than disabled,
             because a disabled field submits nothing and the server reconciles the
             plot count against the schedule that arrives. The sides still travel; the
             controller is what refuses to write them. --}}
        const locked = isLocked(slot);
        const ro = locked ? ' readonly tabindex="-1"' : '';
        const boxClass = locked
            ? 'plot-dim w-14 px-1.5 py-2 rounded-lg border border-slate-200 bg-slate-100 text-sm text-center text-slate-500 cursor-not-allowed'
            : 'plot-dim w-14 px-1.5 py-2 rounded-lg border border-slate-200 bg-white text-sm text-center';

        const box = (t, value) =>
            '<div class="relative">'
            + '<input type="number" step="any" min="0" placeholder="' + (t + 1) + '"'
            + ' aria-label="Side ' + (t + 1) + ' in metres" value="' + esc(value) + '"'
            + ' data-slot="' + slot + '" data-term="' + t + '"' + named + ro
            + ' oninput="onPlotDimInput(this)"'
            + ' class="' + boxClass + '">'
            + (terms.length > 1 && !locked
                ? '<button type="button" onclick="removePlotDimTerm(\'' + slot + '\', ' + t + ')" title="Remove this side"'
                  + ' class="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-white border border-slate-200'
                  + ' text-slate-400 hover:text-rose-600 hover:border-rose-200 text-[9px] font-black'
                  + ' leading-none flex items-center justify-center">&times;</button>'
                : '')
            + '</div>';

        return terms.map((v, t) => box(t, v))
                .join('<span class="text-slate-400 text-xs font-black shrink-0">&times;</span>')
            + (locked ? '' :
                '<button type="button" onclick="addPlotDimTerm(\'' + slot + '\')" title="Add another side"'
                + ' class="w-7 h-9 rounded-lg border border-dashed border-slate-300 text-slate-400'
                + ' hover:border-blue-400 hover:text-blue-600 transition text-sm font-black shrink-0">+</button>');
    }

    /** One plot's cell: its label, its sides, its area, and the m2 the form sends. */
    function plotCell(slot, terms) {
        const locked = isLocked(slot);

        return '<label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">'
            + 'Plot ' + (slot + 1) + ' Dimensions (m)'
            {{-- Says why the row will not take a keystroke, rather than leaving the
                 officer to work it out from the grey. --}}
            + (locked
                ? ' <span class="ml-1 inline-flex items-center rounded bg-emerald-100 px-1 py-0.5 text-[8px] font-black text-emerald-700 normal-case">commissioned</span>'
                : '')
            + '</label>'
            + '<div class="plot-dims flex flex-wrap items-center gap-1.5" data-slot="' + slot + '">'
            + dimBoxes(slot, normaliseTerms(terms))
            + '</div>'
            {{-- The area itself. Filled and hidden while two sides give it; shown, and
                 typed into, where they cannot. Either way this is the plot_sizes[]
                 the controller stores. --}}
            + '<input type="number" name="plot_sizes[]" data-slot="' + slot + '" step="0.01" min="0"'
            + (locked ? ' readonly tabindex="-1"' : '')
            + ' placeholder="Measured area (m&sup2;)" oninput="calculateTotal()"'
            + ' class="fragment-input hidden mt-1.5 w-full px-2 py-1.5 rounded-lg border border-amber-200 bg-amber-50/40 text-xs">'
            + '<p class="plot-area text-[10px] font-bold text-slate-500 text-center mt-1" data-slot="' + slot + '"></p>';
    }

    /**
     * Repaint one plot's area readout from its boxes, and write the m2 into the
     * field that is actually submitted.
     *
     * Where the sides cannot give an area the box is revealed for the officer to
     * fill in, and whatever is already in it is left alone - re-deriving would
     * wipe a figure they had typed.
     */
    {{-- `els` is optional and purely an optimisation: a caller that has already
         resolved this slot's elements passes them in, and a caller that has not gets
         exactly the behaviour this function always had. Each lookup below is a
         whole-document attribute scan, and there are three of them per call -- at 1000
         plots, applySizeToAllPlots was running four thousand of them. --}}
    function syncPlotArea(slot, els) {
        const size = els?.size !== undefined ? els.size : (String(slot) === 'all'
            ? document.getElementById('apply_all_area')
            : document.querySelector('.fragment-input[data-slot="' + slot + '"]'));
        const out = els?.out !== undefined ? els.out : document.querySelector('.plot-area[data-slot="' + slot + '"]');

        const terms = els?.terms !== undefined ? els.terms : termsOf(slot);
        const derived = areaFromTerms(terms);

        // Only once the officer has actually measured something. An untouched plot
        // asks for its sides, not for an area box it has no way to answer yet.
        const asked = terms.some(v => String(v).trim() !== '')
            || !!(size && String(size.value).trim() !== '');
        const needsArea = derived === null && asked;

        if (size) {
            if (derived !== null) size.value = derived;
            size.classList.toggle('hidden', !needsArea);
        }

        if (String(slot) === 'all') {
            const note = document.getElementById('apply_all_area_note');
            if (note) note.classList.toggle('hidden', !needsArea);
        }

        if (out) {
            const m2 = parseFloat(size ? size.value : '');
            out.innerHTML = (isFinite(m2) && m2 > 0)
                ? areaText(m2) + ' &middot; <span class="text-blue-700">' + hectares(m2) + '</span>'
                : '';
        }
    }

    window.onPlotDimInput = function (input) {
        const slot = input.dataset.slot;
        syncPlotArea(slot);

        if (slot === 'all') {
            applySizeToAllPlots();
        } else {
            calculateTotal();
        }
    };

    /** One more side on this parcel, keeping what is already typed. */
    window.addPlotDimTerm = function (slot) {
        redrawDims(slot, termsOf(slot).concat(['']), true);
    };

    window.removePlotDimTerm = function (slot, term) {
        const terms = termsOf(slot);
        if (terms.length <= 1) return;   // never down to nothing

        terms.splice(Number(term), 1);
        redrawDims(slot, terms, false);
    };

    /**
     * Rebuild one row of boxes rather than splicing the DOM, so every remaining
     * box keeps the right data-term and the x buttons stay addressed correctly.
     */
    function redrawDims(slot, terms, focusLast) {
        const wrap = dimWrap(slot);
        if (!wrap) return;

        wrap.innerHTML = dimBoxes(slot, normaliseTerms(terms));

        if (focusLast) {
            const boxes = wrap.querySelectorAll('.plot-dim');
            if (boxes.length) boxes[boxes.length - 1].focus();
        }

        syncPlotArea(slot);

        if (String(slot) === 'all') {
            applySizeToAllPlots();
        } else {
            calculateTotal();
        }
    }

    {{-- Debounced entry point for the "No. of Plots" box.

         generateFragments() rebuilds every plot cell from scratch, and it was wired
         straight to oninput. Typing "1000" therefore built the whole grid four times --
         at 1, 10, 100 and 1000 -- and the last of those is 1000 cells the user never
         asked to see, on the keystroke before they were finished typing. Waiting for
         them to stop is the difference between a usable box and a frozen tab.

         generateFragments() itself stays synchronous so anything calling it directly
         still behaves exactly as before. --}}
    let fragmentsTimer = null;

    const MAX_PLOTS = {{ \App\Http\Controllers\Deeds\ParcelUpdate\PlotSubdivisionController::MAX_PLOTS }};

    function scheduleGenerateFragments() {
        const input = document.getElementById('num_plots');

        {{-- The max attribute on the input is decorative: this form is submitted by
             onclick="submitForm()", never as a native form submit, so the browser's
             constraint validation never runs and typing 5000 would happily build 5000
             cells and then eat a 422. Clamp for real. --}}
        if (input && Number(input.value) > MAX_PLOTS) input.value = MAX_PLOTS;

        const hint = document.getElementById('fragmentsBuilding');
        const num = parseInt(input?.value) || 0;

        if (hint) hint.classList.toggle('hidden', num < 200);

        clearTimeout(fragmentsTimer);
        fragmentsTimer = setTimeout(() => {
            fragmentsTimer = null;
            generateFragments();
            if (hint) hint.classList.add('hidden');
        }, 300);
    }

    /** Run a pending rebuild right now. Called before submit. */
    function flushPendingFragments() {
        if (fragmentsTimer === null) return;
        clearTimeout(fragmentsTimer);
        fragmentsTimer = null;
        generateFragments();
        document.getElementById('fragmentsBuilding')?.classList.add('hidden');
    }

    function generateFragments() {
        const sizeContainer = document.getElementById('fragmentsContainer');
        const num = parseInt(document.getElementById('num_plots').value) || 0;

        const applyAllRow = document.getElementById('applyAllRow');

        {{-- What is already on screen, kept: raising "No. of Plots" from 3 to 4 must
             not blank the three plots that have already been measured.

             Harvested with two sweeps rather than two whole-document scans per plot.
             The cells are emitted in slot order, so position is the slot. --}}
        const oldWraps = [...sizeContainer.querySelectorAll('.plot-dims')];
        const oldAreas = [...sizeContainer.querySelectorAll('.fragment-input')];

        const kept = Array.from({ length: num }).map((_, i) => {
            const wrap = oldWraps[i];
            return wrap ? [...wrap.querySelectorAll('.plot-dim')].map(el => el.value) : [];
        });
        const keptAreas = Array.from({ length: num }).map((_, i) => oldAreas[i] ? oldAreas[i].value : '');

        sizeContainer.innerHTML = '';
        if (applyAllRow) applyAllRow.classList.toggle('hidden', num <= 0);
        if (num <= 0) {
            const toggle = document.getElementById('apply_all_toggle');
            if (toggle) toggle.checked = false;
            toggleApplyAll();
        }

        if (num > 0) {
            {{-- Built off-document and attached once. Appending each cell to the live
                 container made the browser reflow 1000 times for a 1000-plot layout;
                 a DocumentFragment costs one. --}}
            const batch = document.createDocumentFragment();
            for (let i = 0; i < num; i++) {
                const sizeDiv = document.createElement('div');
                sizeDiv.innerHTML = plotCell(i, kept[i]);
                batch.appendChild(sizeDiv);
            }
            sizeContainer.appendChild(batch);

            {{-- Resolved once, then indexed -- the same reason as the harvest above.
                 The per-wrap term read is scoped to that wrap, so it stays cheap. --}}
            const newSizes = [...sizeContainer.querySelectorAll('.fragment-input')];
            const newOuts = [...sizeContainer.querySelectorAll('.plot-area')];
            const newWraps = [...sizeContainer.querySelectorAll('.plot-dims')];

            for (let i = 0; i < num; i++) {
                const el = newSizes[i];
                if (el && keptAreas[i]) el.value = keptAreas[i];

                const wrap = newWraps[i];
                syncPlotArea(i, {
                    size: el,
                    out: newOuts[i],
                    terms: wrap ? [...wrap.querySelectorAll('.plot-dim')].map(x => x.value) : [],
                });
            }
        } else {
            sizeContainer.innerHTML = '<p class="col-span-full text-slate-400 text-xs italic">Enter "No. of Plots" above to define fragment sizes.</p>';
        }

        applySizeToAllPlots();
        calculateTotal();
    }

    function toggleApplyAll() {
        const toggle = document.getElementById('apply_all_toggle');
        const wrapper = document.getElementById('applyAllSizeWrapper');
        const checked = !!(toggle && toggle.checked);

        if (wrapper) wrapper.classList.toggle('hidden', !checked);
        if (!checked) return;

        const wrap = document.getElementById('apply_all_dims');
        if (wrap && !wrap.querySelector('.plot-dim')) {
            wrap.innerHTML = dimBoxes('all', normaliseTerms(null));
        }

        const first = wrap ? wrap.querySelector('.plot-dim') : null;
        if (first) first.focus();

        syncPlotArea('all');
        applySizeToAllPlots();
    }

    /** Copy the shortcut row's sides - and its area - onto every plot. */
    function applySizeToAllPlots() {
        const toggle = document.getElementById('apply_all_toggle');
        if (!toggle || !toggle.checked) return;

        const terms = termsOf('all');
        const master = document.getElementById('apply_all_area');
        const masterArea = master ? master.value : '';

        {{-- Resolved in two sweeps instead of two lookups per plot.

             This is the busiest path on a large capture -- "apply the same size to all
             plots" is how a 1000-plot layout is actually entered -- and it was the most
             expensive: per plot it scanned the whole document for the size box, then
             syncPlotArea scanned it three more times. Four thousand scans of an
             eight-thousand-node tree, on every keystroke in the master size box.

             The boxes are emitted in slot order, so position is the slot. --}}
        const container = document.getElementById('fragmentsContainer');
        const sizeEls = [...container.querySelectorAll('.fragment-input')];
        const outEls = [...container.querySelectorAll('.plot-area')];

        {{-- Every plot is being given the SAME sides, so the markup and the derived
             area are identical for all of them. Compute once, not a thousand times. --}}
        const normalised = normaliseTerms(terms);

        container.querySelectorAll('.plot-dims').forEach((wrap, i) => {
            const slot = wrap.dataset.slot;

            {{-- "Apply to all" means all the plots still being captured. A plot whose
                 file number is already minted is not one of them. --}}
            if (isLocked(slot)) return;

            wrap.innerHTML = dimBoxes(slot, normalised);

            {{-- The area travels with the sides. A parcel measured by its perimeter
                 has an area its sides cannot re-derive, so copying the sides alone
                 would leave every other plot showing a figure of its own. --}}
            const size = sizeEls[i];
            if (size) size.value = masterArea;

            syncPlotArea(slot, { size: size, out: outEls[i], terms: normalised });
        });

        calculateTotal();
    }

    function calculateTotal() {
        const inputs = document.querySelectorAll('#fragmentsContainer .fragment-input');
        let total = 0;
        inputs.forEach(input => {
            const val = parseFloat(input.value);
            if (!isNaN(val)) total += val;
        });

        document.getElementById('totalSize').value =
            areaText(parseFloat(total.toFixed(4))) + ' · ' + hectares(total);
    }

    function openFileSelector() {
        if (window.GlobalFileNoModal) {
            GlobalFileNoModal.open({
                callback: function(data) {
                    if (data.fileNumber) {
                        document.getElementById('file_no').value = data.fileNumber;
                        if (data.record) {
                            let commonName = data.record.file_name || data.record.applicant_name || '';
                            document.getElementById('file_title').value = commonName;
                            document.getElementById('applicant_name').value = commonName;
                            
                            // Backfill location details
                            if (data.record.plot_no) document.getElementById('loc_plot_no').value = data.record.plot_no;
                            if (data.record.house_no) document.getElementById('loc_house_no').value = data.record.house_no;
                            if (data.record.street_name) document.getElementById('loc_street_name').value = data.record.street_name;
                            const district = [
                                data.record.district,
                                data.record.property_district,
                                data.record.address_district,
                                data.record.ma_district
                            ].find(value => String(value || '').trim())
                                || extractDistrictFromLocation(data.record);
                            backfillDistrict(district);
                            if (data.record.lga) document.getElementById('loc_lga').value = data.record.lga;
                            if (data.record.state) document.getElementById('loc_state').value = data.record.state;

                            if (window.syncSearchableSelects) syncSearchableSelects();
                            updateLocationPreview(1);
                        }
                    }
                }
            });
        }
    }

    function extractDistrictFromLocation(record) {
        const location = String(
            record.location || record.Location || record.ma_location || ''
        ).trim();
        if (!location) return '';

        const parts = location.split(',').map(part => part.trim()).filter(Boolean);
        if (!parts.length) return '';

        // Prefer a location component that is an actual configured district.
        const configuredDistrict = [...parts].reverse().find(part =>
            _districtNames.some(name =>
                String(name || '').trim().toLocaleLowerCase() === part.toLocaleLowerCase()
            )
        );
        if (configuredDistrict) return configuredDistrict;

        // Some legacy records only store a combined address. In the established
        // "plot, street, district, LGA, state" shape, District precedes LGA.
        const lga = String(record.lga || record.property_lga || '').trim();
        const lgaIndex = lga
            ? parts.findIndex(part => part.toLocaleLowerCase() === lga.toLocaleLowerCase())
            : -1;

        return lgaIndex >= 3 ? parts[lgaIndex - 1] : '';
    }

    function backfillDistrict(value) {
        const district = String(value || '').trim();
        if (!district) return;

        const select = document.getElementById('loc_district');
        const otherInput = document.getElementById('loc_district_other');
        if (!select) return;

        const matchingOption = Array.from(select.options).find(option =>
            option.value.trim().toLocaleLowerCase() === district.toLocaleLowerCase()
        );

        if (matchingOption) {
            select.value = matchingOption.value;
            if (otherInput) {
                otherInput.value = '';
                otherInput.classList.add('hidden');
            }
            return;
        }

        const otherOption = Array.from(select.options).find(option =>
            option.value.toUpperCase() === 'OTHER'
        );
        if (otherOption) {
            select.value = otherOption.value;
            if (otherInput) {
                otherInput.value = district;
                otherInput.classList.remove('hidden');
            }
        }
    }

    /**
     * Re-open a captured application in the same form.
     *
     * Everything shown comes from the record itself rather than from the row on the
     * listing: the listing carries a summary, and an edit has to start from what was
     * actually saved or the first Save would quietly write the summary back.
     */
    async function openUpdateModal(id) {
        try {
            const response = await fetch(`{{ url('plot-subdivision') }}/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (!result.success) {
                Swal.fire({ icon: 'error', title: 'Error', text: result.message || 'This application could not be loaded.' });
                return;
            }

            const data = result.data;
            {{-- Row order is plot order: the schedule is commissioned in id order and
                 the controller edits it by position, so the form must show it the same
                 way round rather than trusting whatever order the relation came back
                 in. --}}
            const plots = (data.plot_sizes || []).slice()
                .sort((a, b) => (Number(a.id) || 0) - (Number(b.id) || 0));

            // Start from the capture form's own clean slate, then fill it.
            openCreateModal();

            editingId = id;
            lockedPlots = Number(data.commissioned_count) || 0;
            {{-- A draft with nothing minted may still be corrected downward. Anything
                 else is the schedule a memo has been written on, so it is a floor. --}}
            minPlots = (data.status === 'pending' && lockedPlots === 0)
                ? 1
                : Math.max(lockedPlots, plots.length, 1);

            setModalMode(true, data);

            document.getElementById('file_no').value = data.file_no || '';
            document.getElementById('file_title').value = data.file_title || '';
            document.getElementById('applicant_name').value = data.applicant_name || '';

            const landValue = document.getElementById('land_value_input');
            if (landValue) {
                landValue.value = (data.land_value === null || data.land_value === undefined) ? '' : data.land_value;
                calculateSubdivisionFee();
            }

            document.getElementById('loc_plot_no').value = data.plot_no || '';
            document.getElementById('loc_house_no').value = data.house_no || '';
            setSelectOrOther('loc_street_name', 'loc_street_name_other', data.street_name);
            backfillDistrict(data.district);
            document.getElementById('loc_lga').value = data.lga || '';
            if (data.state) document.getElementById('loc_state').value = data.state;

            if (window.syncSearchableSelects) syncSearchableSelects();
            updateLocationPreview(1);

            const num = Number(data.num_plots) || plots.length;
            document.getElementById('num_plots').value = num;
            populateFragments(plots, num);

            if (window.lucide) window.lucide.createIcons();
            document.getElementById('create-modal').classList.remove('hidden');
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'This application could not be loaded.' });
        }
    }

    /**
     * A stored value into a select that may not offer it.
     *
     * A district or street typed as OTHER was saved as free text, so it is not in the
     * dropdown it came from. Putting it back means selecting OTHER and restoring the
     * text — otherwise the select silently falls to its blank option and the next Save
     * writes the location away.
     */
    function setSelectOrOther(selectId, otherId, value) {
        const select = document.getElementById(selectId);
        const other = document.getElementById(otherId);
        const wanted = String(value || '').trim();

        if (!select) return;

        if (!wanted) {
            select.value = '';
            if (other) { other.value = ''; other.classList.add('hidden'); }
            return;
        }

        const match = Array.from(select.options).find(o =>
            o.value.trim().toLocaleLowerCase() === wanted.toLocaleLowerCase());

        if (match) {
            select.value = match.value;
            if (other) { other.value = ''; other.classList.add('hidden'); }
            return;
        }

        const otherOption = Array.from(select.options).find(o => o.value.toUpperCase() === 'OTHER');
        if (otherOption) {
            select.value = otherOption.value;
            if (other) { other.value = wanted; other.classList.remove('hidden'); }
        }
    }

    /** "60 x 21 x 46" back into the boxes it was typed in. */
    function parseDimensions(text) {
        return String(text || '')
            .split(/[x×]/i)
            .map(s => s.trim())
            .filter(s => s !== '');
    }

    /**
     * Draw the plot grid from a saved schedule.
     *
     * generateFragments() harvests whatever is already on screen, which on a fresh
     * open is nothing, so the saved rows are laid in here instead. Beyond the saved
     * rows the cells are blank — those are the plots being added.
     */
    function populateFragments(plots, num) {
        const container = document.getElementById('fragmentsContainer');
        container.innerHTML = '';

        if (num <= 0) {
            container.innerHTML = '<p class="col-span-full text-slate-400 text-xs italic">Enter "No. of Plots" above to define fragment sizes.</p>';
            calculateTotal();
            return;
        }

        const batch = document.createDocumentFragment();
        for (let i = 0; i < num; i++) {
            const div = document.createElement('div');
            div.innerHTML = plotCell(i, parseDimensions((plots[i] || {}).dimensions));
            batch.appendChild(div);
        }
        container.appendChild(batch);

        const sizeEls = [...container.querySelectorAll('.fragment-input')];
        const outEls = [...container.querySelectorAll('.plot-area')];
        const wraps = [...container.querySelectorAll('.plot-dims')];

        for (let i = 0; i < num; i++) {
            const saved = plots[i] || {};
            const el = sizeEls[i];
            if (el && saved.plot_size !== undefined && saved.plot_size !== null && Number(saved.plot_size) > 0) {
                el.value = Number(saved.plot_size);
            }

            const wrap = wraps[i];
            syncPlotArea(i, {
                size: el,
                out: outEls[i],
                terms: wrap ? [...wrap.querySelectorAll('.plot-dim')].map(x => x.value) : [],
            });
        }

        document.getElementById('applyAllRow')?.classList.remove('hidden');
        calculateTotal();
    }

    async function submitForm() {
        {{-- Flush any rebuild still waiting on the debounce.

             Without this, clicking Submit within 300ms of typing the plot count submits
             the PREVIOUS grid: the form would carry, say, 100 plot boxes while num_plots
             says 1000, and the server's reconciliation check would rightly refuse a
             capture the officer had done nothing wrong to produce. The debounce is a
             rendering optimisation and must never decide what gets sent. --}}
        flushPendingFragments();

        const form = document.getElementById('create-form');
        const formData = new FormData(form);
        
        // Map location fields to top-level for controller compatibility
        formData.append('plot_no', document.getElementById('loc_plot_no')?.value || '');
        formData.append('house_no', document.getElementById('loc_house_no')?.value || '');
        
        const streetVal = document.getElementById('loc_street_name')?.value;
        formData.append('street_name', streetVal?.toUpperCase() === 'OTHER' ? (document.getElementById('loc_street_name_other')?.value || 'OTHER') : (streetVal || ''));
        
        const districtVal = document.getElementById('loc_district')?.value;
        formData.append('district', districtVal?.toUpperCase() === 'OTHER' ? (document.getElementById('loc_district_other')?.value || 'OTHER') : (districtVal || ''));
        
        formData.append('lga', document.getElementById('loc_lga')?.value || '');
        formData.append('state', document.getElementById('loc_state')?.value || '');

        const plotCount = parseInt(document.getElementById('num_plots')?.value) || 0;

        {{-- The floor, checked here so the officer is told before a 1000-plot body goes
             up the wire. The server checks it again and is the one that decides. --}}
        if (editingId !== null && plotCount < minPlots) {
            Swal.fire({
                icon: 'warning',
                title: 'Plot count too low',
                text: lockedPlots > 0
                    ? `${lockedPlots.toLocaleString()} of these plots have already been commissioned, so the count cannot go below ${minPlots.toLocaleString()}.`
                    : `This application's ${minPlots.toLocaleString()} plots can be added to but not removed.`,
            });
            return;
        }

        const url = editingId !== null
            ? `{{ url('plot-subdivision') }}/${editingId}/update`
            : '{{ route("plot-subdivision.store") }}';

        try {
            const result = await postWithProgress(formData, plotCount, url);

            if (result.success) {
                saveProgressPhase('Done.');
                await Swal.fire({ icon: 'success', title: 'Success', text: result.message, timer: 1500, showConfirmButton: false });
                location.reload();
                return;
            }

            saveProgressStop();
            Swal.fire({ icon: 'error', title: 'Error', text: result.message || firstValidationError(result) || 'Something went wrong' });
        } catch (error) {
            saveProgressStop();
            Swal.fire({ icon: 'error', title: 'Error', text: error?.message || 'An unexpected error occurred' });
        }
    }

    {{-- The 422 body carries `errors` keyed by field. Showing the first one matters most
         for the plot-count checks, whose whole purpose is to say why nothing was saved. --}}
    function firstValidationError(result) {
        const errors = result?.errors;
        if (!errors) return null;
        const first = Object.values(errors)[0];
        return Array.isArray(first) ? first[0] : first;
    }

    {{-- True from the moment the request is sent until it resolves. Guards the modal's
         close button and the Submit button against a second 1000-plot capture. --}}
    let saveInFlight = false;

    function saveProgressPhase(text) {
        const el = document.getElementById('saveProgressPhase');
        if (el) el.innerText = text;
    }

    function saveProgressStop() {
        saveInFlight = false;
        document.getElementById('saveProgress')?.classList.add('hidden');
        document.getElementById('saveActions')?.classList.remove('hidden');
        const btn = document.getElementById('saveSubmitBtn');
        if (btn) btn.disabled = false;
    }

    {{-- XMLHttpRequest rather than fetch(): fetch cannot report upload progress at all,
         and the upload is the one phase whose real completion we can measure. --}}
    function postWithProgress(formData, plotCount, url) {
        return new Promise((resolve, reject) => {
            const card = document.getElementById('saveProgress');
            const bar = document.getElementById('saveProgressBar');
            const pct = document.getElementById('saveProgressPct');
            const btn = document.getElementById('saveSubmitBtn');

            saveInFlight = true;
            card?.classList.remove('hidden');
            document.getElementById('saveActions')?.classList.add('hidden');
            if (btn) btn.disabled = true;
            saveProgressPhase('Uploading…');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', url || '{{ route("plot-subdivision.store") }}');
            xhr.setRequestHeader('X-CSRF-TOKEN', '{{ csrf_token() }}');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.onprogress = (e) => {
                if (!e.lengthComputable) return;
                const done = Math.round((e.loaded / e.total) * 100);
                if (bar) bar.style.width = done + '%';
                if (pct) pct.innerText = done + '%';
            };

            {{-- Body is up; the server now writes. It cannot report from inside one
                 transaction, so the bar is pinned full and the card names the phase
                 rather than inventing movement. --}}
            xhr.upload.onload = () => {
                if (bar) bar.style.width = '100%';
                if (pct) pct.innerText = '';
                saveProgressPhase(plotCount > 0
                    ? `Saving application and writing ${plotCount.toLocaleString()} plots…`
                    : 'Saving application…');
            };

            xhr.onload = () => {
                try {
                    resolve(JSON.parse(xhr.responseText));
                } catch (e) {
                    reject(new Error('The server returned an unreadable response (HTTP ' + xhr.status + '). The subdivision may not have been saved — check the register before capturing it again.'));
                }
            };

            xhr.onerror = () => reject(new Error('The connection dropped before the server answered. Check the register before capturing this subdivision again.'));
            xhr.ontimeout = () => reject(new Error('The save timed out. Check the register before capturing this subdivision again.'));

            xhr.send(formData);
        });
    }

    function updateLocationPreview(index) {
        const card = document.getElementById(`location_card_${index}`);
        if (!card) return;
        
        const house = card.querySelector(`[name="location_details[${index}][house_no]"]`)?.value || '';
        
        let street = card.querySelector(`[name="location_details[${index}][street_name]"]`)?.value || '';
        if (street.toUpperCase() === 'OTHER') {
            street = card.querySelector(`[name="location_details[${index}][street_name_other]"]`)?.value || 'OTHER';
        }
        
        let district = card.querySelector(`[name="location_details[${index}][district]"]`)?.value || '';
        if (district.toUpperCase() === 'OTHER') {
            district = card.querySelector(`[name="location_details[${index}][district_other]"]`)?.value || 'OTHER';
        }
        
        const lga = card.querySelector(`[name="location_details[${index}][lga]"]`)?.value || '';
        const state = card.querySelector(`[name="location_details[${index}][state]"]`)?.value || '';
        
        let parts = [];
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


    function toggleDropdown(id) {
        const button = document.querySelector(`#dropdown-${id} button`);
        const menu = document.getElementById(`menu-${id}`);
        const allMenus = document.querySelectorAll('[id^="menu-"]');
        
        // Close others
        allMenus.forEach(m => { if(m.id !== `menu-${id}`) m.classList.add('hidden'); });
        
        const isHidden = menu.classList.contains('hidden');
        if (isHidden) {
            menu.classList.remove('hidden');
            const rect = button.getBoundingClientRect();
            
            // Positioning logic for fixed menu
            menu.style.top = (rect.bottom + window.scrollY + 5) + 'px'; // Default below
            menu.style.left = (rect.right - menu.offsetWidth) + 'px';
            
            // Check if it goes off bottom
            if (rect.bottom + menu.offsetHeight > window.innerHeight) {
                menu.style.top = (rect.top + window.scrollY - menu.offsetHeight - 5) + 'px';
            }

            // Sync with scroll to prevent floating
            const syncPos = () => {
                if (menu.classList.contains('hidden')) return;
                const newRect = button.getBoundingClientRect();
                menu.style.top = (newRect.bottom + 5) + 'px';
                menu.style.left = (newRect.right - menu.offsetWidth) + 'px';
            };

            // Use fixed coordinates relative to viewport for the element itself
            menu.style.position = 'fixed';
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = (rect.right - menu.offsetWidth) + 'px';
            
            if (rect.bottom + menu.offsetHeight > window.innerHeight) {
                menu.style.top = (rect.top - menu.offsetHeight - 5) + 'px';
            }
        } else {
            menu.classList.add('hidden');
        }
        
        // Close on click outside
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
            const response = await fetch(`{{ url('plot-subdivision') }}/${id}`);
            const result = await response.json();
            if (result.success) {
                const data = result.data;
                // The sides where they were captured, the area either way. A row saved
                // before the form asked for dimensions carries only the area.
                {{-- Capped, now that an application can hold 1000 plots. Listing every
                     one built a thousand <li> into a fixed-width dialog with no scroll --
                     taller than the screen and unreadable. The full schedule is what the
                     printed recommendation is for. --}}
                const PLOT_LIST_CAP = 100;
                const allPlots = data.plot_sizes || [];

                let plotSizesHtml = allPlots.slice(0, PLOT_LIST_CAP).map(p => `<li>${p.plot_number}: <strong>${areaText(p.plot_size)}</strong>${
                    p.dimensions ? ` <span class="text-slate-400">(${p.dimensions} m)</span>` : ''
                }</li>`).join('');

                if (allPlots.length > PLOT_LIST_CAP) {
                    plotSizesHtml += `<li class="list-none col-span-2 pt-1 text-slate-400 italic">&hellip;and ${(allPlots.length - PLOT_LIST_CAP).toLocaleString()} more. Print the recommendation for the full schedule.</li>`;
                }
                
                Swal.fire({
                    title: 'Subdivision Details',
                    html: `
                        <div class="text-left text-sm space-y-3 p-2">
                            <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">Applicant</p>
                                    <p class="font-bold text-slate-800">${data.applicant_name || '—'}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">File No</p>
                                    <p class="font-bold text-slate-800 whitespace-nowrap">${data.file_no}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">Status</p>
                                    <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-bold uppercase ${data.status === 'approved' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700'}">${data.status}</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase">No. of Plots</p>
                                    <p class="font-bold text-slate-800">${data.num_plots}</p>
                                </div>
                                <div class="bg-blue-50 p-2 rounded-lg border border-blue-100 col-span-2">
                                    <p class="text-[10px] font-black text-blue-600 uppercase">Application Fee (NGN)</p>
                                    <p class="font-bold text-blue-700">₦${new Intl.NumberFormat('en-NG').format(data.knupda_fee || 0)}</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">File Title</p>
                                <p class="text-slate-600">${data.file_title}</p>
                            </div>

                            <div class="space-y-1">
                                <p class="text-[10px] font-black text-slate-400 uppercase">Fragment Sizes</p>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600 list-disc ml-4 max-h-[40vh] overflow-y-auto pr-2">
                                    ${plotSizesHtml}
                                </ul>
                            </div>



                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100">
                                <p class="text-[10px] font-black text-slate-400 uppercase mb-1">Property Location</p>
                                {{-- No plot number: a full property location does not carry it. --}}
                                <p class="text-sm font-bold text-slate-700">${data.house_no ? 'House No ' + data.house_no + ', ' : ''}${data.street_name || ''}, ${data.district || ''}, ${data.lga || ''}, ${data.state || ''}</p>
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
            const response = await fetch(`{{ url('plot-subdivision') }}/${id}`);
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
                                        <input type="number" id="view_land_value_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm bg-white" value="${data.land_value || '0'}" oninput="calculateViewSubdivisionFee(this)">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-blue-600 uppercase mb-1">Application Fee (NGN)</label>
                                        <input type="text" id="view_knupda_fee_input" class="w-full px-3 py-2 rounded-lg border border-blue-200 text-sm font-bold bg-white" value="${data.knupda_fee || '0.00'}" readonly>
                                    </div>
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
        const landValue = document.getElementById('view_land_value_input').value;
        const fee = document.getElementById('view_knupda_fee_input').value;
        const status = document.querySelector('input[name="knupda_status"]:checked')?.value || 'Pending';
        const remarks = document.getElementById('knupda_remarks_input').value;

        try {
            const response = await fetch(`{{ url('plot-subdivision') }}/${id}/knupda`, {
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
            const response = await fetch(`{{ url('plot-subdivision') }}/${id}/generate-application`, {
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
            const response = await fetch(`{{ url('plot-subdivision') }}/${id}/generate-recommendation`, {
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


    // `jsiPending` — the planning clearance is an approved Master JSI now, where it
    // used to be the KAMMA Handshake. The approval is still allowed without it;
    // the officer is told, and decides.
    async function openApprovalDecision(id, canApprove = true, jsiPending = false) {
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
        return result.value === 'approve' ? approveRecord(id, jsiPending) : rejectRecord(id);
    }

    async function approveRecord(id, jsiPending = false) {
        const result = await Swal.fire({
            title: 'Approve Application?',
            html: jsiPending
                ? "Are you sure you want to approve this subdivision application?<br><span class='text-amber-600 font-bold'>Note: the Master JSI has not been approved yet.</span>"
                : "Are you sure you want to approve this subdivision application?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, approve'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch(`{{ url('plot-subdivision') }}/${id}/approve`, {
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
                const response = await fetch(`{{ url('plot-subdivision') }}/${id}/reject`, {
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

    function previewSitePlan(input) {
        const container = document.getElementById('site_plan_preview_container');
        const img = document.getElementById('site_plan_preview_img');
        const pdfPlaceholder = document.getElementById('pdf_preview_placeholder');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            
            container.classList.remove('hidden');
            
            if (file.type === 'application/pdf') {
                img.classList.add('hidden');
                pdfPlaceholder.classList.remove('hidden');
            } else if (file.type.startsWith('image/')) {
                pdfPlaceholder.classList.add('hidden');
                img.classList.remove('hidden');
                
                reader.onload = function(e) {
                    img.src = e.target.result;
                }
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
        const input = document.getElementById('site_plan_input');
        const container = document.getElementById('site_plan_preview_container');
        const img = document.getElementById('site_plan_preview_img');
        const pdfPlaceholder = document.getElementById('pdf_preview_placeholder');
        
        if (input) input.value = '';
        if (container) container.classList.add('hidden');
        if (img) {
            img.src = '#';
            img.classList.add('hidden');
        }
        if (pdfPlaceholder) pdfPlaceholder.classList.add('hidden');
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
                const response = await fetch(`{{ url('plot-subdivision') }}/${id}`, {
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
