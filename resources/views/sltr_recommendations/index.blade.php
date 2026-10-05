@php
    // assign_role is a COMMA-SEPARATED list of modules, so it must be split before
    // it is tested. The guards here used to read assign_role === 'Supper Admin',
    // which only matched a user whose entire list was that one word — a Supper
    // Admin who also held any other module silently lost these actions.
    $isSupperAdmin = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
        ->map(fn ($r) => trim($r))
        ->filter()
        ->contains(fn ($r) => strcasecmp($r, 'Supper Admin') === 0);
@endphp
@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">SLTR Recommendations</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage SLTR Right of Occupancy recommendation entries.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    {{-- Open Approval: off, only the Director SLTR and his Deputy can
                         approve; on, anyone who can open this screen can too.
                         Supper Admin only -- setApprovalMode() enforces it server-side.

                         PARKED: the button carries `hidden` so nobody sees the switch
                         for now. The setting it reads is untouched and still in force --
                         hiding the control does not close the gate. Drop `hidden` from
                         the class below to bring it back. --}}
                    @if($canManageApproval)
                    <button type="button" id="approvalModeBtn"
                            data-open="{{ $approvalOpen ? 1 : 0 }}"
                            onclick="toggleApprovalMode()"
                            title="{{ $approvalOpen
                                  ? 'Open Approval is ON — anyone who can open this screen can approve. Click to restrict.'
                                  : 'Open Approval is OFF — only the Director SLTR and his Deputy can approve. Click to open it up.' }}"
                            class="hidden flex items-center gap-2 px-4 py-2.5 text-sm font-bold rounded-xl border transition whitespace-nowrap
                                   {{ $approvalOpen
                                      ? 'bg-amber-50 border-amber-300 text-amber-800 hover:bg-amber-100'
                                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' }}">
                        <i data-lucide="{{ $approvalOpen ? 'unlock' : 'lock' }}" class="h-4 w-4"></i>
                        <span>Approval: {{ $approvalOpen ? 'Everyone' : 'Directors' }}</span>
                    </button>
                    @endif
                    <form action="{{ route('sltr-recommendations.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search SLTR No, applicant, location..."
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-sm">
                    </form>
                    @if(request('role') !== 'pp_director')
                    <button onclick="openCreateModal()"
                            class="flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold rounded-xl shadow-lg shadow-teal-200 transition whitespace-nowrap">
                        <i data-lucide="plus" class="h-4 w-4"></i> New Recommendation
                    </button>
                    @endif
                </div>
            </div>

            <!-- SLTR Workflow -->
            {{-- The whole SLTR journey, Recommendation -> Original, as a plain rail: no counts,
                 no links. SLTR's own pipeline partial; this screen is step 1. The New
                 Recommendation form below shows the same rail in compact form. --}}
            @include('sltr_cofo.partials.styles')
            @include('sltr_cofo.partials.theme')
            <div class="sltr-theme bg-white rounded-2xl shadow-sm border border-slate-200 mb-8" style="overflow:hidden">
                <div class="bg-slate-50 px-6 py-3 border-b border-slate-200">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="git-branch" class="h-4 w-4 text-teal-600"></i>
                        SLTR Workflow
                    </h3>
                </div>
                @include('sltr_cofo.partials.pipeline', [
                    'stages' => $workflowStages,
                    'showCounts' => false,
                    'current' => 'recommendation',
                ])
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-teal-50 text-teal-600 rounded-2xl border border-teal-100">
                            <i data-lucide="file-text" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100">
                            <i data-lucide="clock" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['pending']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100">
                            <i data-lucide="shield-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Approved</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['approved']) }}</h3>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100">
                            <i data-lucide="zap" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">RofO Generated</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['rofo_generated']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            {{-- overflow-visible, not hidden: the actions menu is positioned out of
                 the row and a clipping ancestor cuts it in half. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-visible">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-teal-600"></i>
                        SLTR Recommendation Records
                    </h3>
                    {{-- Selection toolbar. Hidden until something is ticked, and only
                         rendered for a user who may approve -- the checkbox column is
                         gated on the same flag, so there is nothing to tick otherwise. --}}
                    @if($canApprove)
                    <div id="sltr-batch-toolbar" class="hidden items-center gap-3">
                        <span id="sltr-batch-count" class="text-xs font-bold text-slate-500"></span>
                        <button type="button" onclick="sltrBatchApprove()"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition shadow-sm">
                            <i data-lucide="check-circle" class="h-4 w-4"></i> Batch Approve
                        </button>
                    </div>
                    @endif
                    {{-- RofO Management button hidden for now --}}
                </div>
                <div class="overflow-x-auto overflow-y-visible">
                    <table class="w-full text-left min-w-[1600px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                @if($canApprove)
                                <th class="px-4 py-4 text-center whitespace-nowrap">
                                    <input type="checkbox" id="sltr-select-all"
                                           class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                                           title="Select every pending record on this page">
                                </th>
                                @endif
                                <th class="px-6 py-4 whitespace-nowrap">SLTR Number</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">RofO Status</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                                <th class="px-6 py-4 whitespace-nowrap">Land Use</th>
                                <th class="px-6 py-4 whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Revision Period</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Processing Fee</th>
                                <th class="px-6 py-4 whitespace-nowrap">App. Date</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Created</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($recommendations as $rec)
                            <tr class="hover:bg-slate-50/50 transition">
                                @if($canApprove)
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->status === \App\Models\SltrRecommendation::STATUS_APPROVED)
                                        <i data-lucide="check" class="h-4 w-4 text-emerald-500 inline" title="Already approved"></i>
                                    @else
                                        <input type="checkbox" value="{{ $rec->id }}"
                                               class="sltr-row-check rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                                    @endif
                                </td>
                                @endif
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $rec->sltr_number ?? '—' }}</td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->status === \App\Models\SltrRecommendation::STATUS_APPROVED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">APPROVED</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">PENDING</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->rofo_status === \App\Models\SltrRecommendation::ROFO_GENERATED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">GENERATED</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">PENDING</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->location }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->lga }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->land_use }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->term }} yrs</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->revision_period ? $rec->revision_period.' yrs' : '—' }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->processing_fee, 2) }}</td>
                                <td class="px-4 py-2 text-slate-500 text-xs whitespace-nowrap">{{ $rec->application_date?->format('Y-m-d') }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->created_at ? $rec->created_at->format('d-m-Y') : '—' }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">
                                    @php $sltrCreatedBy = trim((string) ($rec->creator->name ?? '')); @endphp
                                    @if($sltrCreatedBy !== '')
                                        <span class="upc-trigger" data-user-card
                                              data-user-id="{{ $rec->creator->id }}"
                                              data-user-name="{{ $sltrCreatedBy }}"
                                              title="{{ __('View profile') }}">{{ $sltrCreatedBy }}</span>
                                    @else
                                        System
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap">
                                    <div x-data="{
                                        open: false,
                                        menuStyle: {},
                                        toggleMenu($event) {
                                            if (!this.open) {
                                                const btn = $event.currentTarget;
                                                const rect = btn.getBoundingClientRect();
                                                const menuWidth = 224;
                                                // Clamped so a row near either edge still shows the whole menu.
                                                const left = Math.min(Math.max(8, rect.right - menuWidth), window.innerWidth - menuWidth - 8);
                                                this.menuStyle = { position: 'fixed', top: (rect.bottom + 4) + 'px', left: left + 'px', zIndex: 9999 };
                                            }
                                            this.open = !this.open;
                                        }
                                    }" @click.outside="open = false">
                                        <button @click="toggleMenu($event)" class="p-1.5 hover:bg-slate-100 rounded-lg transition">
                                            <i data-lucide="more-horizontal" class="h-4 w-4 text-slate-500"></i>
                                        </button>
                                        {{-- Teleported to <body>. position:fixed still gets clipped when any
                                                 ancestor establishes a containing block, and this menu sits
                                                 inside a sticky cell within two scroll wrappers. Moving it out
                                                 of the table removes every ancestor that could cut it. --}}
                                        <template x-teleport="body">
                                        <div x-show="open" x-transition :style="menuStyle"
                                             class="w-56 bg-white rounded-xl shadow-xl border border-slate-200 py-1 text-sm whitespace-nowrap">
                                            <button type="button"
                                                    onclick="viewRecord({{ json_encode($rec) }}, '{{ route('sltr-recommendations.print', $rec->id) }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-slate-700 hover:bg-slate-50 transition gap-2">
                                                <i data-lucide="eye" class="h-4 w-4"></i> View Record
                                            </button>
                                            <button type="button"
                                                    onclick="openEditModal({{ $rec->id }}, {{ json_encode($rec) }})"
                                                    class="flex w-full items-center px-4 py-2.5 text-slate-700 hover:bg-slate-50 transition gap-2">
                                                <i data-lucide="pencil" class="h-4 w-4"></i> Edit
                                            </button>
                                            @php
                                                // The proofing stage. This document prints no DATE OF ISSUE,
                                                // so the card has no date to take — it only opens the proof.
                                                //
                                                // Once printed_at is stamped the record is issued and the
                                                // entry below reads "View Recommendation"; a proof of an
                                                // issued document has nothing left to check, so it closes too.
                                                $sltrRecDone = $rec->printed_at
                                                    || isset($whiteCopyDone[strtoupper(trim((string) $rec->sltr_number))]);
                                            @endphp

                                            {{-- Proof first, then the official copy. --}}
                                            @if($sltrRecDone)
                                            <span class="flex w-full items-center px-4 py-2.5 text-slate-300 cursor-not-allowed gap-2 font-medium"
                                                  title="{{ $rec->printed_at
                                                        ? 'Already printed — the white copy is a pre-print proof.'
                                                        : 'White copy already run off — print the recommendation next.' }}">
                                                <i data-lucide="file-search" class="h-4 w-4 text-slate-200"></i> Print White Copy
                                            </span>
                                            @else
                                            <button type="button"
                                                    onclick="openWhiteCopyModal({{ (int) $rec->id }}, @js($rec->sltr_number ?? $rec->applicant_name), '', @js(route('sltr-recommendations.white-copy', $rec->id)))"
                                                    class="flex w-full items-center px-4 py-2.5 text-slate-700 hover:bg-slate-100 transition gap-2 font-medium">
                                                <i data-lucide="file-search" class="h-4 w-4"></i> Print White Copy
                                            </button>
                                            @endif

                                            {{-- Opens once the proof has been run, or once the record is
                                                 already issued (where this entry is a viewer, not a print). --}}
                                            @if($sltrRecDone)
                                            <a href="{{ route('sltr-recommendations.print', $rec->id) }}" target="_blank"
                                               class="flex w-full items-center px-4 py-2.5 text-blue-700 hover:bg-blue-50 transition gap-2 font-medium">
                                                @if($rec->printed_at)
                                                    <i data-lucide="file-text" class="h-4 w-4"></i> View Recommendation
                                                @else
                                                    <i data-lucide="printer" class="h-4 w-4"></i> Print
                                                @endif
                                            </a>
                                            @else
                                            <span class="flex w-full items-center px-4 py-2.5 text-slate-300 cursor-not-allowed gap-2 font-medium"
                                                  title="Print and read the white copy first.">
                                                <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i> Print
                                            </span>
                                            @endif
                                            @if($canApprove)
                                                @if($rec->status === \App\Models\SltrRecommendation::STATUS_APPROVED)
                                                <button type="button" disabled
                                                        class="flex w-full items-center px-4 py-2.5 text-slate-300 cursor-not-allowed gap-2 font-bold">
                                                    <i data-lucide="check-circle" class="h-4 w-4"></i> Approved
                                                </button>
                                                @else
                                                <button type="button"
                                                        onclick="approveRecord({{ $rec->id }}, '{{ $rec->sltr_number ?? $rec->applicant_name }}')"
                                                        class="flex w-full items-center px-4 py-2.5 text-emerald-700 hover:bg-emerald-50 transition gap-2 font-bold">
                                                    <i data-lucide="check-circle" class="h-4 w-4"></i> Approve
                                                </button>
                                                @endif
                                            @endif
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <button type="button"
                                                    onclick="deleteRecord({{ $rec->id }}, '{{ $rec->sltr_number ?? $rec->applicant_name }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-red-600 hover:bg-red-50 transition gap-2">
                                                <i data-lucide="trash-2" class="h-4 w-4"></i> Delete
                                            </button>
                                            {{-- Master Delete goes further than Delete above, which only
                                                 removes this row: it also takes the RofO the record became,
                                                 its PRA transaction, its security paper and its print log.
                                                 Supper Admin only — the server enforces the same rule. --}}
                                            @if($isSupperAdmin)
                                            <button type="button"
                                                    onclick="masterDeleteSltrRecommendation({{ $rec->id }}, @js($rec->sltr_number))"
                                                    class="flex w-full items-center px-4 py-2.5 text-red-700 hover:bg-red-50 transition gap-2 font-bold">
                                                <i data-lucide="shield-alert" class="h-4 w-4"></i> Master Delete
                                            </button>
                                            @endif
                                        </div>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $canApprove ? 17 : 16 }}" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">No recommendations found.</p>
                                        <button onclick="openCreateModal()" class="mt-3 text-sm text-teal-600 hover:underline font-bold">+ Add the first one</button>
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

<!-- Create / Edit Modal -->
<div id="rec-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <div id="rec-modal-backdrop" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 z-10">

        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title">New Recommendation</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">SLTR Recommendation Details</p>
            </div>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Workflow: where a recommendation sits in the SLTR journey. Outside the
             scrolling body so it stays in view while the form scrolls. -->
        <div class="sltr-theme border-b border-slate-100 bg-white shrink-0">
            @include('sltr_cofo.partials.pipeline', [
                'stages' => $workflowStages,
                'showCounts' => false,
                'compact' => true,
                'current' => 'recommendation',
            ])
        </div>

        <!-- Scrollable Body -->
        <form id="rec-form" class="flex-1 overflow-y-auto">
            <input type="hidden" id="rec-id" value="">
            <div class="px-8 py-8 space-y-8">

                <!-- Section 1: Reference & Applicant -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-teal-600 flex items-center justify-center text-white shadow-lg shadow-teal-200">
                            <i data-lucide="file-text" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Reference & Applicant</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">File number and applicant identity</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">File Number <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <input type="text" id="f-sltr_number-display" readonly
                                    placeholder="Click 'Select File Number' to choose"
                                    class="flex-1 px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium cursor-pointer focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition"
                                    onclick="sltrOpenFileSelector()">
                                <input type="hidden" id="f-sltr_number">
                                <button type="button" onclick="sltrOpenFileSelector()"
                                    class="inline-flex items-center gap-1.5 px-4 py-3 bg-teal-600 hover:bg-teal-700 text-white text-sm font-bold rounded-xl shadow-sm whitespace-nowrap transition">
                                    <i data-lucide="file-search" class="h-4 w-4"></i> Select File Number
                                </button>
                                <button type="button" id="f-sltr-clear-btn" onclick="sltrClearFileNumber()"
                                    class="hidden p-2 text-red-400 hover:text-red-600 transition" title="Clear">
                                    <i data-lucide="x-circle" class="h-5 w-5"></i>
                                </button>
                            </div>
                            <div id="file-number-duplicate-warning" class="hidden mt-2 flex items-start gap-2 bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 text-sm font-medium">
                                <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5 flex-shrink-0"></i>
                                <span id="file-number-duplicate-msg"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Name <span class="text-red-500">*</span></label>
                            <input type="text" id="f-applicant_name"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. Musa Yakubu">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application Date</label>
                            <input type="date" id="f-application_date"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone Number <span class="text-red-500">*</span></label>
                            <input type="text" id="f-applicant_phone"
                                data-phone-ng data-phone-ng-required data-phone-error="f-applicant_phone-error"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="08012345678">
                            <p id="f-applicant_phone-error" class="mt-1 text-xs text-red-600 hidden"></p>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Applicant Address Builder -->
                <div class="pt-6 border-t border-slate-100 space-y-4">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-200">
                                <i data-lucide="home" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Applicant Address</h4>
                                <p class="text-[10px] text-slate-500 font-bold uppercase">Build the full correspondence address</p>
                            </div>
                        </div>
                        <button type="button" id="addr-edit-btn" onclick="expandAddrBuilder()"
                            class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-bold text-blue-600 hover:bg-blue-50 rounded-xl transition">
                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i> Edit Address
                        </button>
                    </div>
                    <div id="addr-builder" class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                                <input type="text" id="addr-house-no"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition"
                                    placeholder="e.g. 12">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                                <select id="addr-street"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                    <option value="">Select Street</option>
                                    @foreach($streetOptions as $street)
                                        <option value="{{ $street->name }}">{{ $street->name }}</option>
                                    @endforeach
                                </select>
                                <input type="text" id="addr-street-other"
                                    class="hidden addr-component w-full mt-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition"
                                    placeholder="Specify street...">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div id="addr-district-wrap" class="relative">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                                <select id="addr-district"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                    <option value="">Select District</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" id="addr-district-other"
                                    class="hidden addr-component w-full mt-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition"
                                    placeholder="Specify district...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA</label>
                                <select id="addr-lga"
                                    class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State</label>
                            <select id="addr-state"
                                class="addr-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-teal-500 transition">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="f-applicant_address">
                    <div class="p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                        <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Address Preview:</span>
                        <span id="addr-preview" class="italic text-black">No address built yet...</span>
                    </div>
                </div>

                <!-- Section 3: Property / Location Builder -->
                <div class="pt-6 border-t border-slate-100 space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                            <i data-lucide="map-pin" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Property / Location</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Location of the subject plot</p>
                        </div>
                    </div>
                    <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                        {{-- Plot Number lives here, with the rest of the property, rather
                             than under Grant Terms where it used to sit. There were two
                             inputs for it: this position held a decorative one with no id,
                             no name and a hardcoded value="Piece of land" — it showed that
                             text on every record, never loaded what was stored and never
                             saved what was typed — while the working field was buried two
                             sections down. Now there is one field, wired through
                             SCALAR_FIELDS, in the place an officer looks for it. --}}
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plot Number <span class="font-normal normal-case text-slate-400">(blank = piece of land)</span></label>
                                <input type="text" id="f-plot_number"
                                    class="w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition"
                                    placeholder="e.g. 222">
                            </div>

                            <div id="prop-district-wrap" class="relative">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District / Area</label>
                                <select id="prop-district"
                                    class="prop-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition">
                                    <option value="">Select District</option>
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                                <input type="text" id="prop-district-other"
                                    class="hidden prop-component w-full mt-2 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition"
                                    placeholder="Specify district...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                                <select id="prop-lga"
                                    class="prop-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State</label>
                            <select id="prop-state"
                                class="prop-component w-full px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-indigo-500 transition">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="f-location">
                    <input type="hidden" id="f-lga">
                    <div class="p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                        <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Location Preview:</span>
                        <span id="prop-preview" class="italic">No location built yet...</span>
                    </div>
                </div>

                <!-- Section 4: Grant Terms -->
                <div class="pt-6 border-t border-slate-100 space-y-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-2xl bg-amber-500 flex items-center justify-center text-white shadow-lg shadow-amber-200">
                            <i data-lucide="scroll-text" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Grant Terms</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Land use, tenure, fees</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Use</label>
                            <select id="f-land_use_id"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                onchange="syncLandUseText()">
                                <option value="">-- Select Land Use --</option>
                                @foreach($landUseOptions as $opt)
                                    <option value="{{ $opt->id }}" data-text="{{ $opt->landuse }}">{{ $opt->landuse }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" id="f-land_use">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Purpose Clause</label>
                            <select id="f-purpose_of_clause"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium">
                                <option value="">-- Select Purpose --</option>
                                @foreach($purposeOptions as $opt)
                                    <option value="{{ $opt->name }}">{{ $opt->name }}</option>
                                @endforeach
                                {{-- Last, after the list: a purpose the schedule does not
                                     name is still a purpose, and the letter has to print
                                     the words rather than "Other". --}}
                                <option value="Other">Other (specify)</option>
                            </select>
                            {{-- Shown only for "Other". The server folds whatever is typed
                                 here into purpose_of_clause and stores that, so nothing
                                 downstream has to know the choice came from this box. --}}
                            <input type="text" id="f-purpose_of_clause_other" maxlength="300"
                                class="hidden w-full mt-2 px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Specify the purpose">
                        </div> 
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application Page No.</label>
                            <input type="number" id="f-page_application"  min="1"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. 1">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Survey Report Page No.</label>
                            <input type="number" id="f-page_survey"  min="1"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. 9">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Planning Page No.</label>
                            <input type="number" id="f-page_planning"  min="1"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. 17">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Term (Years)</label>
                            <input type="text" id="f-term" value="99" disabled
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed text-sm font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Revision Period (Years)</label>
                            <input type="number" id="f-revision_period" min="1"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. 5">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Ground Rent (₦)</label>
                            {{-- Charged by: printed straight after the figure on the
                                 Recommendation and the RofO. The template used to append
                                 " per sq meter" to every record; now the record says. --}}
                            <div class="flex gap-2">
                                <input type="number" step="0.01" id="f-ground_rent" oninput="updateGroundRentAmount()"
                                    class="flex-1 min-w-0 px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                    placeholder="0.00">
                                <select id="f-ground_rent_unit" onchange="applyGroundRentUnit()"
                                    class="w-40 shrink-0 px-2 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium">
                                    <option value="">-- Unit --</option>
                                    @foreach(\App\Support\GroundRentUnit::OPTIONS as $unitOption)
                                        <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                    @endforeach
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <input type="text" id="f-ground_rent_unit_other" placeholder="State the unit, e.g. Per Acre"
                                class="mt-2 w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium hidden">
                        </div>
                        {{-- The rate above is per unit; what is owed is rate x plot size.
                             Prefilled and locked when the file's indexing has a size. --}}
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot Size (m²)</label>
                            <input type="number" step="0.01" min="0" id="f-plot_size" oninput="updateGroundRentAmount()"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. 450">
                            <p id="plot-size-source" class="mt-1 text-[11px] text-slate-500 hidden">From file indexing</p>
                            <p id="ground-rent-amount" class="mt-1 text-xs font-semibold text-teal-700"></p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Use Sub-Type <span class="font-normal normal-case text-slate-400">(sets processing fee)</span></label>
                            <select id="f-land_use_subtype"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                onchange="applySubTypeFee()" disabled>
                                <option value="">-- Select Land Use first --</option>
                            </select>
                        </div>
                        {{-- Shown only for "Other". The schedule has no line for it, so the
                             officer states BOTH halves of what the schedule would otherwise
                             have supplied: what the parcel is, and what it costs. Recording
                             only the fee left an off-schedule amount with nothing on the row
                             explaining it. --}}
                        <div id="other-subtype-wrap" class="hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                                Specify Land Use Sub-Type <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="f-land_use_subtype_other" maxlength="255"
                                class="w-full px-4 py-3 rounded-xl border border-teal-400 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g., Filling Station">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Processing Fee (₦)</label>
                            <input type="number" step="0.01" id="f-processing_fee" disabled
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed text-sm font-medium"
                                placeholder="0.00">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Notes</label>
                            <textarea id="f-notes" rows="2"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-teal-500 focus:ring-2 focus:ring-teal-100 transition focus:bg-white text-sm font-medium resize-none"
                                placeholder="Optional notes..."></textarea>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Footer -->
            <div class="px-8 py-5 bg-slate-50 border-t border-slate-200 flex justify-end gap-3 rounded-b-3xl shrink-0">
                <button type="button" onclick="closeModal()" class="px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                <button type="submit" id="submit-btn" class="px-8 py-2.5 text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 rounded-xl shadow-lg shadow-teal-200 transition flex items-center gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Recommendation
                </button>
            </div>
        </form>
    </div>
</div>

@include('components.global-fileno-modal')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Make Select2 match the rounded modal inputs on this page */
    #addr-district-wrap .select2-container--default .select2-selection--single,
    #prop-district-wrap .select2-container--default .select2-selection--single {
        height: 42px;
        display: flex;
        align-items: center;
        border-radius: 0.75rem;              /* rounded-xl */
        border: 1px solid rgb(226 232 240);  /* slate-200 */
        padding: 0 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    #addr-district-wrap .select2-selection__arrow,
    #prop-district-wrap .select2-selection__arrow {
        height: 40px !important;
    }
    .select2-dropdown { z-index: 60; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/master-delete.js') }}"></script>
<script>
    /**
     * Master Delete for an SLTR recommendation — the record AND the RofO it became.
     * MasterDelete.confirm() owns the two-step confirmation; the server re-checks
     * both the typed number and the Supper Admin role.
     */
    function masterDeleteSltrRecommendation(id, sltrNumber) {
        MasterDelete.confirm({
            url: '/sltr-recommendations/' + id + '/master-destroy',
            reference: sltrNumber,
            title: 'Master Delete Recommendation',
            lead: 'This permanently deletes <b>' + sltrNumber + '</b> and everything it produced. It cannot be undone.',
            targets: [
                'The SLTR recommendation record',
                'The RofO it became — status, dates and date of issue',
                'Its PRA transaction',
                'Its security paper code (released or retired)',
                'Its print history, white copies included'
            ]
        });
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
// ── SLTR File Number Selector ────────────────────────────────────────
function sltrOpenFileSelector() {
    if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
        alert('File Number Selector is not available on this page.');
        return;
    }
    window.GlobalFileNoModal.open({
        callback: function(fileData) {
            var fileNo = (fileData && fileData.fileNumber ? fileData.fileNumber : '').toString().trim().replace(/-+$/, '');
            if (!fileNo) return;
            document.getElementById('f-sltr_number').value = fileNo;
            document.getElementById('f-sltr_number-display').value = fileNo;
            document.getElementById('f-sltr-clear-btn').classList.remove('hidden');

            // Check for duplicate file number
            sltrCheckDuplicateFileNumber(fileNo);

            // Plot size from the file's indexing, locked when present
            loadIndexedPlotSize(fileNo);

            // Backfill from fileNumber table record (file_name only)
            var r = fileData.record;
            if (r && r.file_name) {
                var nameField = document.getElementById('f-applicant_name');
                if (nameField && !nameField.value) nameField.value = r.file_name;
            }

            // Fetch richer details from file_indexings
            $.ajax({
                url: '/api/file-indexings/lookup-by-number',
                method: 'GET',
                data: { file_number: fileNo },
                dataType: 'json',
                timeout: 7000
            }).done(function(response) {
                if (!response || !response.success || !response.data) return;
                var d = response.data;

                var nameField = document.getElementById('f-applicant_name');
                if (nameField && !nameField.value && d.file_name) nameField.value = d.file_name;

                // Backfill applicant address from the file's residence_address.
                // When present, the address builder collapses to a read-only
                // preview with an "Edit Address" option; when null it stays open.
                if (d.residence_address) {
                    setApplicantAddress(d.residence_address);
                }

                // Backfill visible prop dropdowns and rebuild preview
                var propLga = document.getElementById('prop-lga');
                if (propLga && !propLga.value && d.lga) {
                    setSelect(propLga, d.lga);
                }
                var propDistrict = document.getElementById('prop-district');
                if (propDistrict && !propDistrict.value && d.district) {
                    setSelect(propDistrict, d.district);
                    var propDistrictOther = document.getElementById('prop-district-other');
                    if (propDistrictOther) propDistrictOther.classList.toggle('hidden', propDistrict.value !== 'Other');
                    if (typeof syncDistrictWidgets === 'function') syncDistrictWidgets();
                }
                if ((d.lga || d.district) && typeof assembleProp === 'function') assembleProp();

                var landUseField = document.getElementById('f-land_use_id');
                if (landUseField && !landUseField.value && d.land_use_type) {
                    for (var i = 0; i < landUseField.options.length; i++) {
                        var optText = landUseField.options[i].getAttribute('data-text') || landUseField.options[i].text;
                        if (optText.toUpperCase() === d.land_use_type.toUpperCase()) {
                            landUseField.selectedIndex = i;
                            syncLandUseText();
                            break;
                        }
                    }
                }
            });

            window.GlobalFileNoModal.close();
        }
    });
}
function sltrClearFileNumber() {
    document.getElementById('f-sltr_number').value = '';
    document.getElementById('f-sltr_number-display').value = '';
    document.getElementById('f-sltr-clear-btn').classList.add('hidden');
    sltrHideDuplicateWarning();
    // A size that came from the cleared file's indexing goes with it.
    const ps = document.getElementById('f-plot_size');
    if (ps && ps.disabled) ps.value = '';
    setPlotSizeLock(null);
}

// Tracks whether the currently selected file number is a duplicate (blocks save).
window.sltrFileNumberIsDuplicate = false;

function sltrHideDuplicateWarning() {
    var w = document.getElementById('file-number-duplicate-warning');
    if (w) w.classList.add('hidden');
    window.sltrFileNumberIsDuplicate = false;
}

function sltrCheckDuplicateFileNumber(fileNo) {
    var excludeId = document.getElementById('rec-id').value || '';
    sltrHideDuplicateWarning();

    $.ajax({
        url: '{{ route("sltr-recommendations.check-file-number") }}',
        method: 'GET',
        data: { file_number: fileNo, exclude_id: excludeId },
        dataType: 'json',
        timeout: 6000
    }).done(function(res) {
        if (res.exists && res.record) {
            window.sltrFileNumberIsDuplicate = true;
            var msg = 'This file number is already used by: <strong>' + (res.record.applicant_name || 'Unknown') + '</strong>'
                    + ' (Status: ' + (res.record.status || '—') + '). Saving will be blocked.';
            document.getElementById('file-number-duplicate-msg').innerHTML = msg;
            document.getElementById('file-number-duplicate-warning').classList.remove('hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }
    });
}
</script>
<script>
// ── Address Builder: Applicant ──────────────────────────────────────
function assembleAddr() {
    const houseNo  = document.getElementById('addr-house-no').value.trim();
    const streetSel = document.getElementById('addr-street').value;
    const streetOth = document.getElementById('addr-street-other').value.trim();
    const street   = streetSel === 'Other' ? streetOth : streetSel;
    const distSel  = document.getElementById('addr-district').value;
    const distOth  = document.getElementById('addr-district-other').value.trim();
    const district = distSel === 'Other' ? distOth : distSel;
    const lga      = document.getElementById('addr-lga').value;
    const state    = document.getElementById('addr-state').value;

    const parts = [houseNo, street, district, lga, state].filter(Boolean);
    const assembled = parts.join(', ');
    document.getElementById('f-applicant_address').value = assembled;
    document.getElementById('addr-preview').textContent = assembled || 'No address built yet...';
}

// Collapse the address builder (hide the component fields) and reveal the "Edit
// Address" button. Used when a residence_address is backfilled from the file.
function collapseAddrBuilder() {
    const builder = document.getElementById('addr-builder');
    const editBtn = document.getElementById('addr-edit-btn');
    if (builder) builder.classList.add('hidden');
    if (editBtn) editBtn.classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
}

// Expand the address builder (show the component fields) and hide the "Edit
// Address" button. Used by the Edit button and as the default state.
function expandAddrBuilder() {
    const builder = document.getElementById('addr-builder');
    const editBtn = document.getElementById('addr-edit-btn');
    if (builder) builder.classList.remove('hidden');
    if (editBtn) editBtn.classList.add('hidden');
}

// Set the applicant address directly (from a backfilled residence_address).
// Collapses the builder when a non-empty address is supplied, otherwise leaves
// the builder visible for manual entry.
function setApplicantAddress(addr) {
    const value = (addr || '').trim();
    document.getElementById('f-applicant_address').value = value;
    document.getElementById('addr-preview').textContent = value || 'No address built yet...';
    if (value) {
        collapseAddrBuilder();
    } else {
        expandAddrBuilder();
    }
}

document.querySelectorAll('.addr-component').forEach(el => el.addEventListener('change', assembleAddr));
document.querySelectorAll('.addr-component').forEach(el => el.addEventListener('input', assembleAddr));

document.getElementById('addr-street').addEventListener('change', function () {
    const other = document.getElementById('addr-street-other');
    other.classList.toggle('hidden', this.value !== 'Other');
    assembleAddr();
});
document.getElementById('addr-district').addEventListener('change', function () {
    const other = document.getElementById('addr-district-other');
    other.classList.toggle('hidden', this.value !== 'Other');
    assembleAddr();
});

// ── Property / Location Builder ─────────────────────────────────────
function assembleProp() {
    const distSel  = document.getElementById('prop-district').value;
    const distOth  = document.getElementById('prop-district-other').value.trim();
    const district = distSel === 'Other' ? distOth : distSel;
    const lga      = document.getElementById('prop-lga').value;
    const state    = document.getElementById('prop-state').value;

    const locationParts = [district, lga, state].filter(Boolean);
    const assembled = locationParts.join(', ');
    document.getElementById('f-location').value = assembled;
    document.getElementById('f-lga').value = lga;
    document.getElementById('prop-preview').textContent = assembled || 'No location built yet...';
}

document.querySelectorAll('.prop-component').forEach(el => el.addEventListener('change', assembleProp));
document.querySelectorAll('.prop-component').forEach(el => el.addEventListener('input', assembleProp));

document.getElementById('prop-district').addEventListener('change', function () {
    const other = document.getElementById('prop-district-other');
    other.classList.toggle('hidden', this.value !== 'Other');
    assembleProp();
});

// ── Helpers ─────────────────────────────────────────────────────────
function setSelect(el, val) {
    if (!el) return;
    // Try direct match first
    for (const opt of el.options) {
        if (opt.value === val) { el.value = val; return; }
    }
    // Fall back to "Other" with manual input
    for (const opt of el.options) {
        if (opt.value === 'Other') {
            el.value = 'Other';
            // find sibling other-input (next sibling or by convention)
            const otherId = el.id + '-other';
            const other = document.getElementById(otherId);
            if (other) { other.value = val; other.classList.remove('hidden'); }
            return;
        }
    }
}

function resetAddrBuilder() {
    ['addr-house-no','addr-street-other','addr-district-other'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    ['addr-street','addr-district','addr-lga','addr-state'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = 0;
    });
    // Re-select Kano as default state
    const addrState = document.getElementById('addr-state');
    if (addrState) for (const opt of addrState.options) { if (opt.value === 'Kano') { addrState.value = 'Kano'; break; } }
    ['addr-street-other','addr-district-other'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    document.getElementById('f-applicant_address').value = '';
    document.getElementById('addr-preview').textContent = 'No address built yet...';
    expandAddrBuilder();
    if (typeof syncDistrictWidgets === 'function') syncDistrictWidgets();
}

function resetPropBuilder() {
    ['prop-district-other'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.value = ''; el.classList.add('hidden'); }
    });
    ['prop-district','prop-lga','prop-state'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = 0;
    });
    const propState = document.getElementById('prop-state');
    if (propState) for (const opt of propState.options) { if (opt.value === 'Kano') { propState.value = 'Kano'; break; } }
    document.getElementById('f-location').value = '';
    document.getElementById('f-lga').value = '';
    document.getElementById('prop-preview').textContent = 'No location built yet...';
    if (typeof syncDistrictWidgets === 'function') syncDistrictWidgets();
}

// ── Scalar fields (not address-builder) ────────────────────────────
// The unit list and the legacy default, from the one place they are defined.
const GROUND_RENT_UNITS   = @json(\App\Support\GroundRentUnit::OPTIONS);
const GROUND_RENT_DEFAULT = @json(\App\Support\GroundRentUnit::LEGACY_DEFAULT);

const SCALAR_FIELDS = ['applicant_name','applicant_phone','application_date','plot_number','page_application','page_survey','page_planning','purpose_of_clause','purpose_of_clause_other','term','revision_period','ground_rent','ground_rent_unit','ground_rent_unit_other','plot_size','processing_fee','notes'];

/**
 * The ground rent unit's free-text box belongs to the "Other" choice only.
 *
 * Both controls are posted; the server resolves Other down to the words typed,
 * so the column always holds what prints.
 */
/**
 * The "specify" box belongs to the Other choice only.
 *
 * Mirrors applyGroundRentUnit below. A purpose left over from Other must not
 * travel to a listed choice, so the box is cleared whenever one is picked.
 */
document.addEventListener('change', function (e) {
    if (e.target && e.target.id === 'f-purpose_of_clause') applyPurposeClause();
});

function applyPurposeClause() {
    const sel = document.getElementById('f-purpose_of_clause');
    const other = document.getElementById('f-purpose_of_clause_other');
    if (!sel || !other) return;
    const isOther = sel.value === 'Other';
    other.classList.toggle('hidden', !isOther);
    if (!isOther) other.value = '';
}

function applyGroundRentUnit() {
    const sel = document.getElementById('f-ground_rent_unit');
    const other = document.getElementById('f-ground_rent_unit_other');
    if (!sel || !other) return;
    const isOther = sel.value === 'Other';
    other.classList.toggle('hidden', !isOther);
    // A unit left over from "Other" must not travel to a fixed choice.
    if (!isOther) other.value = '';
    updateGroundRentAmount();
}

// ── Plot size & ground rent amount ───────────────────────────────────
// The ground rent field is a RATE; the amount is rate x plot size. Mirrors
// SltrPlotSize::amount() on the server.
function updateGroundRentAmount() {
    const out = document.getElementById('ground-rent-amount');
    if (!out) return;
    const rate = parseFloat(document.getElementById('f-ground_rent')?.value);
    const size = parseFloat(document.getElementById('f-plot_size')?.value);
    const unit = (document.getElementById('f-ground_rent_unit')?.value || '').trim().toLowerCase();
    let qty = null;
    if (unit === '' || unit === 'per square meters') qty = size;
    else if (unit === 'ha') qty = size / 10000;
    if (isNaN(rate) || isNaN(size) || qty === null) { out.textContent = ''; return; }
    const ngn = n => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    out.textContent = `Ground rent amount: ₦${ngn(rate)} × ${ngn(size)} m²${unit === 'ha' ? ' (÷ 10,000)' : ''} = ₦${ngn(rate * qty)}`;
}

// Locked (and greyed) when the size came from the file's indexing; the server
// re-reads the indexing on save, so the lock is a hint, not the guard.
function setPlotSizeLock(sqm) {
    const el = document.getElementById('f-plot_size');
    const tag = document.getElementById('plot-size-source');
    if (!el) return;
    const locked = sqm !== null && sqm !== undefined;
    if (locked) el.value = sqm;
    el.disabled = locked;
    el.style.backgroundColor = locked ? '#e2e8f0' : '';
    el.style.color = locked ? '#64748b' : '';
    el.style.cursor = locked ? 'not-allowed' : '';
    if (tag) tag.classList.toggle('hidden', !locked);
    updateGroundRentAmount();
}

function loadIndexedPlotSize(fileNo) {
    if (!fileNo) { setPlotSizeLock(null); return; }
    $.getJSON('{{ route("sltr-recommendations.plot-size") }}', { file_number: fileNo })
        .done(res => setPlotSizeLock(res && res.plot_size !== null ? res.plot_size : null))
        .fail(() => setPlotSizeLock(null));
}
function syncLandUseText() {
    const sel = document.getElementById('f-land_use_id');
    const hid = document.getElementById('f-land_use');
    if (!sel || !hid) return;
    const opt = sel.options[sel.selectedIndex];
    const text = (opt && opt.value) ? (opt.getAttribute('data-text') || opt.text) : '';
    hid.value = text;
    mapTermFromLandUse(text);
    populateSubTypes(text);
}

// Auto-map the lease term (years) based on the selected land use.
//   Residential  = 99 years
//   Agricultural = 99 years
//   Industrial   = 40 years
//   Commercial   = 40 years
function mapTermFromLandUse(landUse) {
    const termEl = document.getElementById('f-term');
    if (!termEl) return;
    const lu = (landUse || '').toUpperCase();
    let term = null;
    if (lu.includes('RESIDENTIAL') || lu.includes('AGRICULTUR')) {
        term = 99;
    } else if (lu.includes('INDUSTRIAL') || lu.includes('COMMERCIAL')) {
        term = 40;
    }
    if (term !== null) termEl.value = term;
}

// Processing-fee schedule (back of the RofO), keyed by land use, with the
// sub-type driving the exact amount (₦).
const LAND_USE_SUBTYPE_FEES = {
    RESIDENTIAL: [
        { label: 'Systematic', fee: 5000 },
        { label: 'On Demand', fee: 20000 },
    ],
    COMMERCIAL: [
        { label: 'Systematic', fee: 10000 },
        { label: 'On Demand (Within Metro)', fee: 50000 },
        { label: 'On Demand (Outside Metro)', fee: 40000 },
        { label: 'Warehouse', fee: 100000 },
    ],
    INDUSTRIAL: [
        { label: 'Warehouse', fee: 100000 },
        { label: 'Above 1 Hectare', fee: 100000 },
    ],
    AGRICULTURAL: [
        { label: 'Farmland', fee: 50000 },
        { label: 'Above 1 Hectare', fee: 100000 },
    ],
};

function subtypeKeyFor(landUse) {
    const lu = (landUse || '').toUpperCase();
    if (lu.includes('RESIDENTIAL')) return 'RESIDENTIAL';
    if (lu.includes('COMMERCIAL'))  return 'COMMERCIAL';
    if (lu.includes('INDUSTRIAL'))  return 'INDUSTRIAL';
    if (lu.includes('AGRICULTUR'))  return 'AGRICULTURAL';
    return null;
}

// Rebuild the Sub-Type dropdown for the selected land use.
// When `matchFee` is provided (record edit), the sub-type whose fee equals the
// stored fee is pre-selected and the stored fee is left untouched. Otherwise
// the first sub-type is selected and its fee is applied.
function populateSubTypes(landUse, matchFee, storedSubType) {
    const sub = document.getElementById('f-land_use_subtype');
    if (!sub) return;
    const key = subtypeKeyFor(landUse);
    const options = key ? LAND_USE_SUBTYPE_FEES[key] : [];

    if (!options.length) {
        sub.innerHTML = '<option value="">-- Select Land Use first --</option>';
        sub.disabled = true;
        setFeeEditable(false);
        return;
    }

    // Every schedule ends with Other: the fee table is the published schedule,
    // not the whole world, and an officer meeting a case outside it needs a way
    // through that is not "pick the nearest wrong one". data-fee is left empty,
    // which is what applySubTypeFee() reads to hand the field over.
    sub.innerHTML = options.map(o =>
        `<option value="${o.label}" data-fee="${o.fee}">${o.label} — ₦${o.fee.toLocaleString('en-NG')}</option>`
    ).join('') + '<option value="Other" data-fee="">Other — specify the fee</option>';
    sub.disabled = false;

    if (matchFee !== undefined && matchFee !== null && matchFee !== '') {
        let matched = false;
        for (let i = 0; i < sub.options.length; i++) {
            const optFee = sub.options[i].getAttribute('data-fee');
            if (optFee !== '' && Number(optFee) === Number(matchFee)) {
                sub.selectedIndex = i;
                matched = true;
                break;
            }
        }
        // A stored fee that is on no line of the schedule IS an Other -- landing
        // on Systematic instead would misdescribe the record and re-lock a fee
        // somebody set deliberately.
        if (!matched) {
            sub.value = 'Other';
        }
        const isOther = sub.value === 'Other';
        setFeeEditable(isOther);
        // Reopening an Other must show the sub-type that was recorded with it,
        // not an empty box the officer has to remember and retype.
        setOtherSubTypeVisible(isOther);
        if (isOther) {
            const otherEl = document.getElementById('f-land_use_subtype_other');
            if (otherEl) otherEl.value = storedSubType || '';
        }
        return; // keep the stored fee as-is on edit
    }

    applySubTypeFee();
}

// Hand the Processing Fee field to the officer, or take it back.
// Read-only rather than disabled either way: the save reads .value by id, so
// both submit, but read-only can still be focused and copied from.
// "Other" needs the sub-type NAMED as well as priced, so the two fields appear
// and disappear together -- they are the two halves of the same answer.
function setOtherSubTypeVisible(visible) {
    const wrap = document.getElementById('other-subtype-wrap');
    const input = document.getElementById('f-land_use_subtype_other');
    if (!wrap) return;

    wrap.classList.toggle('hidden', !visible);
    if (!visible && input) input.value = '';
}

function setFeeEditable(editable) {
    const feeEl = document.getElementById('f-processing_fee');
    if (!feeEl) return;

    feeEl.disabled = !editable;
    feeEl.classList.toggle('bg-slate-100', !editable);
    feeEl.classList.toggle('text-slate-500', !editable);
    feeEl.classList.toggle('cursor-not-allowed', !editable);
    feeEl.classList.toggle('bg-slate-50', editable);
    feeEl.classList.toggle('border-teal-400', editable);
    feeEl.placeholder = editable ? 'Enter the agreed fee' : '0.00';
}

// Set the processing fee from the currently selected sub-type.
function applySubTypeFee() {
    const sub = document.getElementById('f-land_use_subtype');
    const feeEl = document.getElementById('f-processing_fee');
    if (!sub || !feeEl || sub.disabled) return;

    const opt = sub.options[sub.selectedIndex];
    const fee = opt ? opt.getAttribute('data-fee') : null;

    // Other carries no fee of its own, so the field opens and is cleared: an
    // amount left over from the previously selected sub-type would otherwise
    // read as the schedule's answer for a case the schedule does not cover.
    if (fee === '' || fee === null || fee === undefined) {
        setFeeEditable(true);
        setOtherSubTypeVisible(true);
        feeEl.value = '';
        // The sub-type is asked for first, because it is what decides the fee.
        document.getElementById('f-land_use_subtype_other')?.focus();
        return;
    }

    setFeeEditable(false);
    setOtherSubTypeVisible(false);
    feeEl.value = fee;
}

function openCreateModal() {
    document.getElementById('rec-id').value = '';
    document.getElementById('modal-title').textContent = 'New Recommendation';
    SCALAR_FIELDS.forEach(f => {
        const el = document.getElementById('f-' + f);
        if (!el) return;
        if (f === 'term') { el.value = 40; return; }
        // Every SLTR letter issued so far was charged per square metre - the template
        // printed it outright - so that is what a new one opens on.
        if (f === 'ground_rent_unit') { el.value = GROUND_RENT_DEFAULT; return; }
        el.value = '';
    });
    applyGroundRentUnit();
    applyPurposeClause();
    // Reset file number selector
    document.getElementById('f-sltr_number').value = '';
    document.getElementById('f-sltr_number-display').value = '';
    document.getElementById('f-sltr-clear-btn').classList.add('hidden');
    sltrHideDuplicateWarning();
    setPlotSizeLock(null);
    // Reset page number defaults
    // var pageDefaults = { 'page_application': 1, 'page_survey': 9, 'page_planning': 17 };
    // Object.keys(pageDefaults).forEach(function(k) {
    //     var el = document.getElementById('f-' + k);
    //     if (el) el.value = pageDefaults[k];
    // });
    // Reset land use
    const luSel = document.getElementById('f-land_use_id');
    if (luSel) luSel.selectedIndex = 0;
    const luHid = document.getElementById('f-land_use');
    if (luHid) luHid.value = '';
    populateSubTypes(''); // reset sub-type dropdown to disabled default
    resetAddrBuilder();
    resetPropBuilder();
    document.getElementById('rec-modal').classList.remove('hidden');
}

function openEditModal(id, data) {
    document.getElementById('rec-id').value = id;
    document.getElementById('modal-title').textContent = 'Edit Recommendation';

    SCALAR_FIELDS.forEach(f => {
        const el = document.getElementById('f-' + f);
        if (!el) return;
        let v = data[f] ?? '';
        if (f === 'application_date' && v) v = v.substring(0, 10);
        // A unit the officer typed has no option of its own: show Other and put the
        // words in the box, which is where the server expects them back.
        // A purpose the officer typed has no option of its own: show Other and put
        // the words in the box. Tested against the select's real options rather
        // than a copied list, so it stays right as the schedule changes.
        if (f === 'purpose_of_clause' && v) {
            const listed = Array.from(el.options).some(o => o.value === v);
            if (!listed) {
                document.getElementById('f-purpose_of_clause_other').value = v;
                v = 'Other';
            }
        }
        if (f === 'ground_rent_unit' && v && !GROUND_RENT_UNITS.includes(v)) {
            document.getElementById('f-ground_rent_unit_other').value = v;
            v = 'Other';
        }
        if (f === 'ground_rent_unit_other') return;
        el.value = v;
    });
    applyGroundRentUnit();
    applyPurposeClause();

    // Restore file number selector
    const storedFileNo = data.sltr_number ?? '';
    document.getElementById('f-sltr_number').value = storedFileNo;
    document.getElementById('f-sltr_number-display').value = storedFileNo;
    if (storedFileNo) document.getElementById('f-sltr-clear-btn').classList.remove('hidden');
    else document.getElementById('f-sltr-clear-btn').classList.add('hidden');
    // Unlock first (the stored size stays in the box), then lock if indexed.
    setPlotSizeLock(null);
    loadIndexedPlotSize(storedFileNo);

    // For edit, we set the hidden assembled fields directly and show them in previews.
    // The builder sub-fields will show "Other" fallback with the stored value.
    resetAddrBuilder();
    resetPropBuilder();

    // Restore applicant address as "Other" district/street pattern if not found in dropdowns
    const storedAddr = data.applicant_address ?? '';
    if (storedAddr) {
        // Show stored value in "other" street field as a best-effort restore
        const addrStreetOther = document.getElementById('addr-street-other');
        const addrStreetSel   = document.getElementById('addr-street');
        addrStreetSel.value = 'Other';
        addrStreetOther.value = storedAddr;
        addrStreetOther.classList.remove('hidden');
        setApplicantAddress(storedAddr);
    }

    const storedLoc = data.location ?? '';
    const storedLga = data.lga ?? '';
    if (storedLoc || storedLga) {
        const propDistOther = document.getElementById('prop-district-other');
        document.getElementById('prop-district').value = 'Other';
        propDistOther.value = storedLoc;
        propDistOther.classList.remove('hidden');
        setSelect(document.getElementById('prop-lga'), storedLga);
        document.getElementById('f-location').value = storedLoc;
        document.getElementById('f-lga').value = storedLga;
        document.getElementById('prop-preview').textContent = [storedLoc, storedLga].filter(Boolean).join(', ');
        if (typeof syncDistrictWidgets === 'function') syncDistrictWidgets();
    }

    // Restore land use: find option by data-text match
    const storedLandUse = data.land_use ?? '';
    const luSel = document.getElementById('f-land_use_id');
    const luHid = document.getElementById('f-land_use');
    if (luSel && luHid) {
        luSel.selectedIndex = 0;
        luHid.value = '';
        if (storedLandUse) {
            for (let i = 0; i < luSel.options.length; i++) {
                const optText = luSel.options[i].getAttribute('data-text') || luSel.options[i].text;
                if (optText.toUpperCase() === storedLandUse.toUpperCase()) {
                    luSel.selectedIndex = i;
                    luHid.value = storedLandUse;
                    break;
                }
            }
        }
    }
    // Rebuild sub-type options for the restored land use, pre-selecting the
    // sub-type that matches the stored processing fee (fee itself is preserved).
    populateSubTypes(storedLandUse, data.processing_fee, data.land_use_subtype);

    document.getElementById('rec-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('rec-modal').classList.add('hidden');
}

// Only Supper Admin / Director SLTR may reprint from the view card.
window.sltrCanApprove = {{ $canApprove ? 'true' : 'false' }};

// Read-only view of a recommendation record's stored fields.
function viewRecord(data, printUrl) {
    const esc = (v) => (v === null || v === undefined || v === '') ? '—'
        : String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const money = (v) => (v === null || v === undefined || v === '') ? '—'
        : '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dateOnly = (v) => v ? String(v).substring(0, 10) : '';
    const creator = data.creator && data.creator.name ? data.creator.name : null;

    const rows = [
        ['File Number', esc(data.sltr_number)],
        ['Applicant Name', esc(data.applicant_name)],
        ['Phone Number', esc(data.applicant_phone)],
        ['Applicant Address', esc(data.applicant_address)],
        ['Location', esc(data.location)],
        ['LGA', esc(data.lga)],
        ['Land Use', esc(data.land_use)],
        ['Purpose Clause', esc(data.purpose_of_clause)],
        ['Plot Number', esc(data.plot_number)],
        ['Term', data.term ? esc(data.term) + ' yrs' : '—'],
        ['Revision Period', data.revision_period ? esc(data.revision_period) + ' yrs' : '—'],
        ['Ground Rent', money(data.ground_rent) + (data.ground_rent_unit ? ' ' + esc(data.ground_rent_unit) : '')],
        ['Processing Fee', money(data.processing_fee)],
        ['Application Page No', esc(data.page_application)],
        ['Survey Report Page No', esc(data.page_survey)],
        ['Planning Page No', esc(data.page_planning)],
        ['Application Date', esc(dateOnly(data.application_date))],
        ['Status', esc(data.status)],
        ['RofO Status', esc(data.rofo_status)],
        ['Notes', esc(data.notes)],
        ['Created By', esc(creator)],
        ['Created On', esc(dateOnly(data.created_at))],
    ];

    const html = '<div style="text-align:left;font-size:13px;max-height:60vh;overflow-y:auto">'
        + '<table style="width:100%;border-collapse:collapse">'
        + rows.map(([k, v]) =>
            `<tr><td style="padding:6px 10px;font-weight:700;color:#475569;white-space:nowrap;vertical-align:top">${k}</td>`
            + `<td style="padding:6px 10px;color:#0f172a">${v}</td></tr>`).join('')
        + '</table></div>';

    Swal.fire({
        title: 'Recommendation Record',
        html: html,
        width: 640,
        showDenyButton: window.sltrCanApprove && !!printUrl,
        denyButtonText: 'Reprint',
        denyButtonColor: '#2563eb',
        confirmButtonText: 'Close',
        confirmButtonColor: '#0d9488'
    }).then((result) => {
        if (result.isDenied && printUrl) {
            window.open(printUrl, '_blank');
        }
    });
}

// Modal stays open until the user explicitly closes it (no close on backdrop click).

document.getElementById('rec-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    // ── File number validation ──────────────────────────────────────
    const fileNo = (document.getElementById('f-sltr_number').value || '').trim();
    if (!fileNo) {
        Swal.fire({ icon: 'warning', title: 'File Number Required', text: 'Please select a File Number before saving.' });
        document.getElementById('f-sltr_number-display').focus();
        return;
    }
    if (window.sltrFileNumberIsDuplicate) {
        Swal.fire({ icon: 'error', title: 'Duplicate File Number', text: 'This file number is already used by another recommendation. Please select a different file number.' });
        return;
    }

    // ── Phone number ────────────────────────────────────────────────
    // This modal saves by fetch, so the shared helper's submit interception
    // never fires here — ask it explicitly.
    const phoneEl = document.getElementById('f-applicant_phone');
    if (phoneEl && window.NigerianPhone && !window.NigerianPhone.validateField(phoneEl, false)) {
        Swal.fire({
            icon: 'warning',
            title: 'Phone Number Required',
            text: 'Enter an 11-digit phone number starting with 0, such as 08012345678.'
        });
        phoneEl.focus();
        return;
    }

    const id = document.getElementById('rec-id').value;
    const url = id ? `/sltr-recommendations/${id}` : '/sltr-recommendations';
    const method = id ? 'PUT' : 'POST';
    const body = {};

    SCALAR_FIELDS.forEach(f => {
        const el = document.getElementById('f-' + f);
        if (!el) return;
        const v = el.value;
        if (['term','revision_period','ground_rent','plot_size','processing_fee'].includes(f)) {
            body[f] = v === '' ? null : (['term','revision_period'].includes(f) ? parseInt(v, 10) : parseFloat(v));
        } else {
            body[f] = v;
        }
    });

    // Add file number and assembled address builder values
    body.sltr_number       = document.getElementById('f-sltr_number').value;
    body.applicant_address = document.getElementById('f-applicant_address').value;
    body.location          = document.getElementById('f-location').value;
    body.lga               = document.getElementById('f-lga').value;
    body.land_use          = document.getElementById('f-land_use').value;

    // The sub-type the fee came from: the schedule line chosen, or the one the
    // officer named when the schedule had no line for it. Stored either way, so a
    // fee on the row always has the reason for it beside it.
    const subSel = document.getElementById('f-land_use_subtype');
    const subOther = document.getElementById('f-land_use_subtype_other');
    const isOther = subSel && subSel.value === 'Other';

    // Checked before the submit button is touched, so nothing needs re-enabling.
    if (isOther && !(subOther?.value || '').trim()) {
        alert('Enter the land use sub-type for "Other" before saving.');
        subOther?.focus();
        return;
    }

    body.land_use_subtype = isOther
        ? (subOther?.value || '').trim()
        : (subSel?.value || '');

    const btn = document.getElementById('submit-btn');
    btn.disabled = true;

    try {
        const res = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (res.status === 422 && data.errors) {
            // Laravel returns field errors, not `message` — show the first.
            const firstError = Object.values(data.errors)[0];
            Swal.fire({
                icon: 'error',
                title: data.errors.sltr_number ? 'Duplicate File Number' : 'Check the form',
                text: Array.isArray(firstError) ? firstError[0] : String(firstError)
            });
            if (data.errors.sltr_number) {
                document.getElementById('f-sltr_number-display').focus();
            }
            return;
        }
        if (data.success) {
            closeModal();
            Swal.fire({
                icon: 'success',
                title: 'Saved',
                text: data.message || 'Recommendation saved successfully.',
                timer: 1500,
                showConfirmButton: false
            }).then(() => window.location.reload());
        } else {
            Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Error saving recommendation.' });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Network error. Please try again.' });
    } finally {
        btn.disabled = false;
    }
});

// Open Approval on/off. Widens approval from the two ranked posts to anyone
// holding the "SLTR - Approvals" role, and back. Supper Admin only -- the
// server re-checks, so a hidden button is not the only thing standing in the way.
async function toggleApprovalMode() {
    const btn  = document.getElementById('approvalModeBtn');
    const open = btn.dataset.open === '1';
    const next = !open;

    const confirmed = await Swal.fire({
        icon: next ? 'warning' : 'question',
        title: next ? 'Open approval to everyone?' : 'Restrict approval to the Directors?',
        html: next
            ? '<b>Anyone</b> who can open this screen will be able to approve recommendations, not just the Director SLTR and his Deputy.'
            : 'Only the <b>Director SLTR</b> and the <b>Deputy Director SLTR</b> will be able to approve recommendations.',
        showCancelButton: true,
        confirmButtonText: next ? 'Yes, open it' : 'Yes, restrict it',
        confirmButtonColor: next ? '#d97706' : '#0f766e',
    });
    if (!confirmed.isConfirmed) return;

    const res = await fetch('/sltr-recommendations/approval-mode', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ open: next ? 1 : 0 }),
    });
    const data = await res.json();

    if (!data.success) {
        Swal.fire({ icon: 'error', title: 'Not changed', text: data.message || 'Could not change the approval mode.' });
        return;
    }

    Swal.fire({ icon: 'success', title: data.message, timer: 2200, showConfirmButton: false })
        .then(() => window.location.reload());
}

// ---------------------------------------------------------------- Batch approve
// The cap comes from the controller so the two cannot drift: the server rejects
// anything over it, and this stops the operator before the round trip.
const SLTR_BATCH_MAX = {{ \App\Http\Controllers\SltrRecommendationController::MAX_BATCH_APPROVE }};

function sltrCheckedIds() {
    return [...document.querySelectorAll('.sltr-row-check:checked')].map(c => c.value);
}

function sltrUpdateBatchToolbar() {
    const bar   = document.getElementById('sltr-batch-toolbar');
    const label = document.getElementById('sltr-batch-count');
    if (!bar) return;

    const n = sltrCheckedIds().length;
    bar.classList.toggle('hidden', n === 0);
    bar.classList.toggle('flex', n > 0);

    if (label) {
        label.textContent = n > SLTR_BATCH_MAX
            ? `${n} selected — only ${SLTR_BATCH_MAX} allowed`
            : `${n} selected`;
        label.classList.toggle('text-red-600', n > SLTR_BATCH_MAX);
        label.classList.toggle('text-slate-500', n <= SLTR_BATCH_MAX);
    }
}

document.querySelectorAll('.sltr-row-check').forEach(chk => {
    chk.addEventListener('change', sltrUpdateBatchToolbar);
});

// Select-all stops at the cap rather than ticking a selection the server will
// refuse. Pages hold 20 rows, so this only bites once the page size is raised.
const sltrSelectAll = document.getElementById('sltr-select-all');
if (sltrSelectAll) {
    sltrSelectAll.addEventListener('change', function () {
        const boxes = [...document.querySelectorAll('.sltr-row-check')];
        boxes.forEach((chk, i) => { chk.checked = this.checked && i < SLTR_BATCH_MAX; });

        if (this.checked && boxes.length > SLTR_BATCH_MAX) {
            Swal.fire({
                icon: 'info',
                title: `Selected the first ${SLTR_BATCH_MAX}`,
                text: `A batch approval carries at most ${SLTR_BATCH_MAX} records. Approve these, then select the rest.`,
                timer: 3200,
                showConfirmButton: false,
            });
        }
        sltrUpdateBatchToolbar();
    });
}

async function sltrBatchApprove() {
    const ids = sltrCheckedIds();
    if (!ids.length) return;

    if (ids.length > SLTR_BATCH_MAX) {
        Swal.fire({
            icon: 'warning',
            title: 'Too many selected',
            text: `You have ${ids.length} selected. A batch approval carries at most ${SLTR_BATCH_MAX} records.`,
        });
        return;
    }

    const confirmed = await Swal.fire({
        icon: 'warning',
        title: 'Batch Approve?',
        html: `Approve <b>${ids.length}</b> recommendation(s)? Each one generates its RofO and a PRA entry, and that cannot be undone from this screen.`,
        showCancelButton: true,
        confirmButtonText: `Yes, approve ${ids.length}`,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
    });
    if (!confirmed.isConfirmed) return;

    Swal.fire({
        title: 'Approving…',
        html: `Working through ${ids.length} record(s). This may take a moment.`,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const res = await fetch(`{{ route('sltr-recommendations.batch-approve') }}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ ids }),
        });
        const data = await res.json();

        if (!data.success) {
            Swal.fire({ icon: 'error', title: 'Not approved', text: data.message || 'Batch approval failed.' });
            return;
        }

        Swal.fire({
            icon: data.failed && data.failed.length ? 'warning' : 'success',
            title: 'Done',
            text: data.message,
            timer: data.failed && data.failed.length ? undefined : 2200,
            showConfirmButton: !!(data.failed && data.failed.length),
        }).then(() => window.location.reload());
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Not approved', text: 'Could not reach the server.' });
    }
}

async function approveRecord(id, label) {
    const confirmed = await Swal.fire({
        icon: 'question',
        title: 'Approve Recommendation?',
        text: label,
        showCancelButton: true,
        confirmButtonText: 'Yes, approve',
        confirmButtonColor: '#059669'
    });
    if (!confirmed.isConfirmed) return;
    const res = await fetch(`/sltr-recommendations/${id}/approve`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    });
    const data = await res.json();
    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Approved', timer: 1500, showConfirmButton: false })
            .then(() => window.location.reload());
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Error approving.' });
    }
}

async function deleteRecord(id, label) {
    const confirmed = await Swal.fire({
        icon: 'warning',
        title: 'Delete Recommendation?',
        html: `<strong>${label}</strong><br>This cannot be undone.`,
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        confirmButtonColor: '#dc2626'
    });
    if (!confirmed.isConfirmed) return;
    const res = await fetch(`/sltr-recommendations/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    });
    const data = await res.json();
    if (data.success) {
        Swal.fire({ icon: 'success', title: 'Deleted', timer: 1500, showConfirmButton: false })
            .then(() => window.location.reload());
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Error deleting.' });
    }
}
</script>
<script>
// ── Make the District dropdowns searchable (Select2) ─────────────────
$(function () {
    function initDistrictSelect2(selectId, wrapId) {
        var $sel = $('#' + selectId);
        if (!$sel.length || typeof $.fn.select2 === 'undefined') return;
        if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
        $sel.select2({
            placeholder: 'Search or select district',
            allowClear: true,
            width: '100%',
            // Anchor the dropdown to the field's own relative wrapper so it opens
            // directly under the select inside the fixed modal.
            dropdownParent: $('#' + wrapId)
        });
        $sel.on('select2:open', function () {
            setTimeout(function () {
                var f = document.querySelector('.select2-search__field');
                if (f) f.focus();
            }, 50);
        });
    }
    initDistrictSelect2('addr-district', 'addr-district-wrap');
    initDistrictSelect2('prop-district', 'prop-district-wrap');
});

// Sync the Select2 widgets after a district <select> is changed programmatically
// (reset / prefill / edit). The .select2 namespaced event refreshes the widget
// display WITHOUT re-firing the native 'change' handlers (assembleAddr/assembleProp),
// which the callers already invoke themselves.
function syncDistrictWidgets() {
    if (typeof $ === 'undefined' || typeof $.fn.select2 === 'undefined') return;
    $('#addr-district, #prop-district').each(function () {
        if ($(this).hasClass('select2-hidden-accessible')) $(this).trigger('change.select2');
    });
}
</script>
@endpush
@endsection
