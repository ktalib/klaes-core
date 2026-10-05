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
    @include('exports.consolidated_report_action', ['reportKey' => 'sltr-rofo'])
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">SLTR RofO Management</h1>
                    <p class="text-slate-500 text-sm mt-1">Generate and print SLTR Right of Occupancy offers.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('sltr-rofos.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search SLTR No, applicant, location..."
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all shadow-sm">
                    </form>
                    <a href="{{ route('sltr-recommendations.index') }}"
                       class="flex items-center gap-2 px-4 py-2.5 text-sm font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition whitespace-nowrap">
                        <i data-lucide="list" class="h-4 w-4"></i> All Recommendations
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-10">
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100">
                            <i data-lucide="shield-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Eligible for RofO</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['total_eligible']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Approved records</span><span class="text-emerald-500">Ready</span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl border border-amber-100">
                            <i data-lucide="zap-off" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pending Generation</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['pending_generation']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Waiting to process</span><span class="text-amber-500">Action Required</span>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100">
                            <i data-lucide="zap" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Generated RofOs</p>
                            <h3 class="text-2xl font-black text-slate-800">{{ number_format($stats['generated']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Completed offers</span><span class="text-blue-500">Processed</span>
                    </div>
                </div>
                {{-- ground_rent is the unit RATE (e.g. 8.00 per m2); the total is the sum of
                     rate x plot size over generated RofOs that have a plot size. --}}
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-teal-600 to-teal-800 border-none">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30">
                            <i data-lucide="coins" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-teal-100 uppercase tracking-widest">Total Ground Rent</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">
                                @if ($stats['total_ground_rent'] === null)
                                    ₦ &mdash;
                                @else
                                    ₦{{ number_format($stats['total_ground_rent'], 2) }}
                                @endif
                            </h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-teal-100 uppercase tracking-widest">
                        <span>Rate &times; Plot Size</span>
                    </div>
                </div>
            </div>

            <!-- Records Table -->
            {{-- overflow-visible, not hidden: the actions menu is positioned out of
                 the row and a clipping ancestor cuts it in half. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-visible">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="printer" class="h-4 w-4 text-teal-600"></i>
                        SLTR RofO Records
                    </h3>
                </div>
                <div class="overflow-x-auto overflow-y-visible">
                    <table class="w-full text-left min-w-[1400px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4 whitespace-nowrap">SLTR Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Land Use</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Processing Fee</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">RofO Status</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Generated On</th>
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Created</th>
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($recommendations as $rec)
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $rec->sltr_number ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->land_use }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->location }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-center text-slate-600 whitespace-nowrap">{{ $rec->term }} yrs</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-right text-slate-600 whitespace-nowrap">₦{{ number_format($rec->processing_fee, 2) }}</td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($rec->rofo_status === \App\Models\SltrRecommendation::ROFO_GENERATED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">GENERATED</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">PENDING</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center text-slate-500 text-xs whitespace-nowrap">
                                    {{ $rec->rofo_generated_at ? $rec->rofo_generated_at->format('Y-m-d h:i A') : 'N/A' }}
                                </td>
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
                                {{-- When the record was captured, which is not "Generated On" two
                                     columns back: a recommendation can sit for weeks before its
                                     RofO is issued. --}}
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->created_at ? $rec->created_at->format('d-m-Y') : '—' }}</td>
                                <td class="px-4 py-2 text-right sticky right-0 bg-white shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] border-l border-slate-100 z-10 whitespace-nowrap">
                                    <div x-data="{
                                        open: false,
                                        menuStyle: {},
                                        toggleMenu($event) {
                                            if (!this.open) {
                                                const btn = $event.currentTarget;
                                                const rect = btn.getBoundingClientRect();
                                                const menuWidth = 208;
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
                                             class="w-52 bg-white rounded-xl shadow-xl border border-slate-200 py-1 text-sm">
                                            <button type="button"
                                                    onclick="viewRecord({{ json_encode($rec) }}, '{{ $rec->rofo_status === \App\Models\SltrRecommendation::ROFO_GENERATED ? route('sltr-rofos.print', $rec->id) : '' }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-slate-700 hover:bg-slate-50 transition gap-2">
                                                <i data-lucide="eye" class="h-4 w-4"></i> View Record
                                            </button>
                                            <div class="border-t border-slate-100 my-1"></div>
                                            @php
                                                // The proofing stage, and the date of issue with it. SLTR has
                                                // its own date_issued column now, and its letter prints DATE OF
                                                // ISSUE like every other — so the White Copy takes that date
                                                // (ownsDate) and the Print Manager shows it read-only with an
                                                // Edit, exactly as the Land RofO does.
                                                //
                                                // issueDateUrl is not optional here: the shared card defaults to
                                                // the Land endpoint, and a date entered on an SLTR proof and
                                                // posted there would be written to a land record.
                                                $sltrWcUrl      = route('sltr-rofos.white-copy', $rec->id);
                                                $sltrDateUrl    = route('sltr-rofos.issue-date');
                                                $sltrIssueDate  = optional($rec->date_issued)->format('Y-m-d') ?? '';
                                                $sltrWcDone     = isset($whiteCopyDone[strtoupper(trim((string) $rec->sltr_number))]);
                                                $sltrPmOpts     = [
                                                    'recordId'          => (int) $rec->id,
                                                    'whiteCopyUrl'      => $sltrWcUrl,
                                                    'whiteCopyOwnsDate' => true,
                                                    'issueDate'         => $sltrIssueDate,
                                                    'issueDateUrl'      => $sltrDateUrl,
                                                ];
                                            @endphp
                                            @if($rec->rofo_status !== \App\Models\SltrRecommendation::ROFO_GENERATED)
                                            <button type="button"
                                                    onclick="openGenerateModal({{ $rec->id }}, '{{ $rec->sltr_number }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-teal-700 hover:bg-teal-50 transition gap-2 font-bold">
                                                <i data-lucide="zap" class="h-4 w-4"></i> Generate RofO
                                            </button>
                                            @elseif(($rec->rofo_print_count ?? 0) > 0)
                                            <button type="button"
                                                    onclick="window.open('{{ route('sltr-rofos.print', $rec->id) }}', '_blank')"
                                                    class="flex w-full items-center px-4 py-2.5 text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                <i data-lucide="eye" class="h-4 w-4"></i> View RofO
                                            </button>
                                            {{-- Printing is not the end of this menu. The split passes, a
                                                 reprint of a spoiled sheet and the Certified True Copies all
                                                 live in the Print Manager, and they are only ever needed
                                                 AFTER a letter has been run — so it stays available here
                                                 rather than disappearing at the moment it becomes useful. --}}
                                            <button type="button"
                                                    onclick="WhiteCopy.openPrintManager(@js($rec->sltr_number), 'SLTR RofO', @js(route('sltr-rofos.print', $rec->id)), @js($sltrPmOpts))"
                                                    class="flex w-full items-center px-4 py-2.5 text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                <i data-lucide="printer" class="h-4 w-4"></i> Print Manager
                                            </button>
                                            @else

                                            {{-- Proof first, then the run — the order the work happens in. --}}
                                            @if($sltrWcDone)
                                            <span class="flex w-full items-center px-4 py-2.5 text-slate-300 cursor-not-allowed gap-2 font-bold"
                                                  title="White copy already run off — print the letter next.">
                                                <i data-lucide="file-search" class="h-4 w-4 text-slate-200"></i> Print White Copy
                                            </span>
                                            @else
                                            <button type="button"
                                                    onclick="openWhiteCopyModal({{ (int) $rec->id }}, @js($rec->sltr_number), @js($sltrIssueDate), @js($sltrWcUrl), @js(['ownsDate' => true, 'issueDateUrl' => $sltrDateUrl]))"
                                                    class="flex w-full items-center px-4 py-2.5 text-slate-700 hover:bg-slate-100 transition gap-2 font-bold">
                                                <i data-lucide="file-search" class="h-4 w-4"></i> Print White Copy
                                            </button>
                                            @endif

                                            @if($sltrWcDone)
                                            <button type="button"
                                                    {{-- SLTR keeps its own recommendations table, which has no
                                                         application_date column, so the manager opens without the
                                                         Date Issued panel — but with the same three passes. --}}
                                                    onclick="WhiteCopy.openPrintManager(@js($rec->sltr_number), 'SLTR RofO', @js(route('sltr-rofos.print', $rec->id)), @js($sltrPmOpts))"
                                                    class="flex w-full items-center px-4 py-2.5 text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                <i data-lucide="printer" class="h-4 w-4"></i> Print Manager
                                            </button>
                                            @else
                                            <span class="flex w-full items-center px-4 py-2.5 text-slate-300 cursor-not-allowed gap-2 font-bold"
                                                  title="Print and read the white copy first.">
                                                <i data-lucide="printer" class="h-4 w-4 text-slate-200"></i> Print Manager
                                            </span>
                                            @endif
                                            @endif
                                            <button type="button"
                                                    onclick="openAssignSecurityPaperModal({{ $rec->id }}, '{{ $rec->sltr_number }}', '{{ $rec->sltr_rofo_serial_no }}', '{{ route('sltr-rofos.assign-security-paper', $rec->id) }}')"
                                                    class="flex w-full items-center px-4 py-2.5 text-sm {{ $rec->sltr_rofo_serial_no ? 'text-emerald-600 font-bold' : 'text-slate-600' }} hover:bg-emerald-50 transition gap-2">
                                                <i data-lucide="hash" class="h-4 w-4"></i>
                                                {{ $rec->sltr_rofo_serial_no ? 'Code: '.$rec->sltr_rofo_serial_no : 'Enter Security Paper Code' }}
                                            </button>
                                            {{-- Supper Admin only. A security paper code identifies a
                                                 numbered sheet already issued against this RofO, so
                                                 releasing it is a controlled act, not an ordinary edit. --}}
                                            @if($rec->sltr_rofo_serial_no && $isSupperAdmin)
                                            <button type="button"
                                                    onclick="openResetSecurityPaperModal({{ $rec->id }}, @js($rec->sltr_number), @js($rec->sltr_rofo_serial_no), '{{ route('sltr-rofos.reset-security-paper', $rec->id) }}', { assignEndpoint: '{{ route('sltr-rofos.assign-security-paper', $rec->id) }}' })"
                                                    class="flex w-full items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition gap-2 font-bold">
                                                <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset Security Paper Code
                                            </button>
                                            @endif
                                            {{-- Master Delete un-issues the RofO and leaves the
                                                 recommendation standing, approved and ready to generate
                                                 again. To erase the record itself, use the Master Delete
                                                 on the Recommendation screen. Supper Admin only. --}}
                                            @if($isSupperAdmin)
                                            <div class="border-t border-slate-100 my-1"></div>
                                            <button type="button"
                                                    onclick="masterDeleteSltrRofo({{ $rec->id }}, @js($rec->sltr_number))"
                                                    class="flex w-full items-center px-4 py-2.5 text-sm text-red-700 hover:bg-red-50 transition gap-2 font-bold">
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
                                <td colspan="13" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">No approved SLTR recommendations ready for RofO.</p>
                                        <a href="{{ route('sltr-recommendations.index') }}" class="mt-3 text-sm text-teal-600 hover:underline font-bold">Go to Recommendations &rarr;</a>
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

<!-- Generate RofO Modal -->
<div id="generate-modal" class="fixed inset-0 z-50 overflow-y-auto hidden">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
        <div id="gen-modal-backdrop" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm"></div>
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10">
            <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex justify-between items-center rounded-t-2xl">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Generate SLTR RofO</h3>
                    <p class="text-sm text-slate-500 mt-1">Enter generation details for <span id="gen-sltr-no" class="font-bold text-teal-600"></span></p>
                </div>
                <button onclick="closeGenerateModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>
            <form id="gen-form">
                <input type="hidden" id="gen-id">
                <div class="px-8 py-6 space-y-5">
                    <div class="p-4 bg-slate-50 rounded-xl">
                        <span class="block text-sm font-bold text-slate-700 mb-3">Survey Method (Select One)</span>
                        <div class="space-y-3">
                            <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-white transition bg-white">
                                <input type="radio" name="survey_method" value="DIRECTOR" class="w-5 h-5 text-teal-600" id="sm-director">
                                <span class="text-sm font-medium text-slate-700">Require <strong>Director Survey</strong> to carry out survey</span>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-white transition bg-white">
                                <input type="radio" name="survey_method" value="LICENSED" class="w-5 h-5 text-teal-600" id="sm-licensed">
                                <span class="text-sm font-medium text-slate-700">Require <strong>Licensed Surveyor</strong> to carry out survey</span>
                            </label>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Date Generated</label>
                            <input type="date" id="gen-date" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none transition text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Time Generated</label>
                            <input type="time" id="gen-time" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-teal-500 outline-none transition text-sm">
                        </div>
                    </div>
                </div>
                <div class="px-8 py-5 bg-slate-50 border-t border-slate-200 flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" onclick="closeGenerateModal()" class="px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">Cancel</button>
                    <button type="submit" id="gen-submit-btn"
                            class="px-8 py-2.5 text-sm font-bold text-white bg-teal-600 hover:bg-teal-700 rounded-xl shadow-lg shadow-teal-200 transition flex items-center gap-2 disabled:opacity-50">
                        <i data-lucide="zap" class="h-4 w-4"></i> Generate RofO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/master-delete.js') }}"></script>
<script>
    /**
     * Master Delete for an SLTR RofO — the ISSUANCE only. The recommendation stays
     * approved and can be generated again; erasing the record is the Master Delete
     * on the Recommendation screen.
     */
    function masterDeleteSltrRofo(id, sltrNumber) {
        MasterDelete.confirm({
            url: '/sltr-rofos/' + id + '/master-destroy',
            reference: sltrNumber,
            title: 'Master Delete RofO',
            lead: 'This permanently un-issues the RofO for <b>' + sltrNumber + '</b>. It cannot be undone.',
            targets: [
                'RofO status, generation date and date of issue',
                'Its PRA transaction',
                'Its security paper code (released, or retired if already printed)',
                'Its print history, white copies included'
            ],
            keeps: 'The recommendation itself is kept, approved, and can be generated again.'
        });
    }
</script>
<script>
// Only Supper Admin / Director SLTR may reprint from the view card.
window.sltrCanApprove = {{ $canApprove ? 'true' : 'false' }};

// Read-only view of an SLTR record's stored fields.
function viewRecord(data, printUrl) {
    const esc = (v) => (v === null || v === undefined || v === '') ? '—'
        : String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const money = (v) => (v === null || v === undefined || v === '') ? '—'
        : '₦' + Number(v).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const appDate = data.application_date ? String(data.application_date).substring(0, 10) : '';

    const rows = [
        ['File Number', esc(data.sltr_number)],
        ['Applicant Name', esc(data.applicant_name)],
        ['Applicant Address', esc(data.applicant_address)],
        ['Location', esc(data.location)],
        ['LGA', esc(data.lga)],
        ['Land Use', esc(data.land_use)],
        ['Purpose Clause', esc(data.purpose_of_clause)],
        ['Plot Number', esc(data.plot_number)],
        ['Term', data.term ? esc(data.term) + ' yrs' : '—'],
        ['Ground Rent', money(data.ground_rent)],
        ['Processing Fee', money(data.processing_fee)],
        ['Application Date', esc(appDate)],
        ['Status', esc(data.status)],
        ['RofO Status', esc(data.rofo_status)],
        ['Security Paper Code', esc(data.sltr_rofo_serial_no)],
        ['Generated On', esc(data.rofo_generated_at)],
        ['Notes', esc(data.notes)],
    ];

    const html = '<div style="text-align:left;font-size:13px;max-height:60vh;overflow-y:auto">'
        + '<table style="width:100%;border-collapse:collapse">'
        + rows.map(([k, v]) =>
            `<tr><td style="padding:6px 10px;font-weight:700;color:#475569;white-space:nowrap;vertical-align:top">${k}</td>`
            + `<td style="padding:6px 10px;color:#0f172a">${v}</td></tr>`).join('')
        + '</table></div>';

    Swal.fire({
        title: 'SLTR RofO Record',
        html: html,
        width: 640,
        confirmButtonText: 'Close',
        confirmButtonColor: '#0d9488'
    });
}

function openGenerateModal(id, sltrNo) {
    document.getElementById('gen-id').value = id;
    document.getElementById('gen-sltr-no').textContent = sltrNo || '#' + id;
    document.getElementById('gen-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('gen-time').value = new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    document.querySelector('input[name="survey_method"]').checked = false;
    document.getElementById('generate-modal').classList.remove('hidden');
}

function closeGenerateModal() {
    document.getElementById('generate-modal').classList.add('hidden');
}

document.getElementById('gen-modal-backdrop').addEventListener('click', closeGenerateModal);

document.getElementById('gen-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('gen-id').value;
    const surveyMethod = document.querySelector('input[name="survey_method"]:checked')?.value || '';
    const body = {
        rofo_director_survey:   surveyMethod === 'DIRECTOR' ? 'YES' : 'NO',
        rofo_licensed_surveyor: surveyMethod === 'LICENSED' ? 'YES' : 'NO',
        rofo_date_generated:    document.getElementById('gen-date').value,
        rofo_time_generated:    document.getElementById('gen-time').value,
    };
    const btn = document.getElementById('gen-submit-btn');
    btn.disabled = true;
    try {
        const res = await fetch(`/sltr-rofos/${id}/generate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { closeGenerateModal(); window.location.reload(); }
        else { alert(data.message || 'Error generating RofO.'); }
    } catch (err) { alert('Network error. Please try again.'); }
    finally { btn.disabled = false; }
});
</script>
@endpush
@endsection
