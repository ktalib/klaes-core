@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Land RofO Management</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage Right of Occupancy Offers, generation and printing.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('land-rofos.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text"
                               name="search"
                               value="{{ request('search') }}"
                               placeholder="Search file, applicant, or location..."
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </form>
                    <button type="button" onclick="openBatchPrintModal()"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 whitespace-nowrap text-sm">
                        <i data-lucide="printer" class="h-4 w-4"></i> Batch Print RofO
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-10">
                <!-- Eligible -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="shield-check" class="h-32 w-32 text-emerald-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 shadow-sm">
                            <i data-lucide="shield-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Eligible for RoFO</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['total_eligible']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Approved records</span>
                        <span class="text-emerald-500">Ready</span>
                    </div>
                </div>


                <!-- Generated -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="zap" class="h-32 w-32 text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                            <i data-lucide="zap" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Generated RoFOs</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['generated']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Completed offers</span>
                        <span class="text-blue-500">Processed</span>
                    </div>
                </div>

                <!-- OSS Applications -->
                <div class="bg-white p-6 rounded-3xl border border-purple-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="layers" class="h-32 w-32 text-purple-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-purple-50 text-purple-600 rounded-2xl border border-purple-100 shadow-sm">
                            <i data-lucide="layers" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">OSS Applications</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['oss_total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Change of Name</span>
                        <span class="text-purple-500">Print Ready</span>
                    </div>
                </div>

                <!-- Total Dev Charges -->
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-indigo-600 to-indigo-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="coins" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="coins" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-indigo-100 uppercase tracking-widest">Total Dev. Charges</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">₦{{ number_format($stats['total_dev_charge']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-indigo-100 uppercase tracking-widest">
                        <span>Revenue Stream</span>
                        <!-- <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">SQLSRV</span> -->
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                    <h3 class="font-bold text-slate-800 uppercase tracking-wider text-xs flex items-center gap-2">
                        <i data-lucide="shield" class="h-4 w-4 text-blue-600"></i>
                        RoFO Management Records
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left min-w-[2000px] border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-4 py-4 text-center whitespace-nowrap">S/N</th>
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Source</th>
                                <th class="px-6 py-4 whitespace-nowrap">Applicant Name</th>
                                <th class="px-6 py-4 whitespace-nowrap">Land Use / Purpose</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Layout Plan</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Term</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Ground Rent</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Dev. Period</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Survey Fees</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Value</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Dev. Charge</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 text-center text-green-600 whitespace-nowrap">Approved On</th>
                                <!-- <th class="px-6 py-4 text-center text-blue-600 whitespace-nowrap">RofO Status</th> -->
                                <th class="px-6 py-4 whitespace-nowrap">Created By</th>
                                <th class="px-6 py-4 whitespace-nowrap">Security Paper Code</th>
                                <th class="px-6 py-4 whitespace-nowrap">Date Generated</th>
                                <th class="px-6 py-4 text-right sticky right-0 bg-slate-50 border-l border-slate-200 z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @forelse($recommendations as $rec)
                            @php $isOssRec = strtoupper($rec->type ?? '') === 'OSS'; @endphp
                            <tr class="hover:bg-slate-50/50 transition {{ $isOssRec ? 'bg-purple-50/30' : '' }}">
                                <td class="px-4 py-2 text-center text-slate-500 whitespace-nowrap">{{ ($recommendations->currentPage() - 1) * $recommendations->perPage() + $loop->iteration }}</td>
                                <td class="px-4 py-2 font-mono font-bold text-slate-900 whitespace-nowrap">{{ $rec->file_number }}</td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    @if($isOssRec)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                            <i data-lucide="layers" class="h-3 w-3"></i> OSS Applications
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">
                                            <i data-lucide="file" class="h-3 w-3"></i> Land ROFO
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-700 whitespace-nowrap">{{ $rec->applicant_name }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->purpose_of_clause }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->location }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->plot_number }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->layout_plan_no }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->term }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->ground_rent, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->development_period }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->survey_fees, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->development_value, 2) }}</td>
                                <td class="px-4 py-2 text-slate-600 text-right whitespace-nowrap">₦{{ number_format($rec->development_charge, 2) }}</td>
                                <td class="px-4 py-2 text-center whitespace-nowrap">
                                    @if($isOssRec)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800">
                                            PRINT READY
                                        </span>
                                    @elseif($rec->rofo_status === \App\Models\LandRecommendation::ROFO_GENERATED)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800">
                                           APPROVED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                            PENDING
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center text-slate-500 text-xs whitespace-nowrap">
                                    {{ $isOssRec ? '—' : ($rec->approved_at ? $rec->approved_at->format('Y-m-d h:i A') : 'N/A') }}
                                </td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">{{ $rec->creator->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-slate-600 whitespace-nowrap">
                                    @if($isOssRec)
                                        <span class="text-slate-400 italic text-xs">N/A</span>
                                    @elseif($rec->land_rofo_serial_no)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                            {{ $rec->land_rofo_serial_no }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic text-xs">Unassigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-500 text-xs whitespace-nowrap">
                                    {{ $rec->created_at ? $rec->created_at->format('Y-m-d h:i A') : 'N/A' }}
                                </td>
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
                                             class="min-w-[13rem] w-max rounded-xl shadow-2xl bg-white ring-1 ring-black ring-opacity-5 overflow-hidden"
                                             style="display: none;">
                                            <div class="py-1">
                                                <button type="button" onclick="editRofORecord('{{ $rec->id }}', '{{ route('land-recommendations.edit', $rec->id) }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition gap-2">
                                                    <i data-lucide="edit-3" class="h-4 w-4"></i> Edit Record
                                                </button>

                                                <div class="border-t border-slate-100 my-1"></div>

                                                <button type="button" onclick="assignSecurityPaperCode('{{ $rec->id }}', '{{ $rec->file_number }}', '{{ $rec->land_rofo_serial_no }}')" class="flex w-full items-center px-4 py-2.5 text-sm text-emerald-600 hover:bg-emerald-50 transition gap-2 font-bold">
                                                    <i data-lucide="hash" class="h-4 w-4"></i> Enter Security Paper Code
                                                </button>

                                                <div class="border-t border-slate-100 my-1"></div>

                                                <button type="button"
                                                        onclick="SmartPrintManager.open('{{ $rec->file_number }}', 'Land RofO', '{{ route('land-rofos.print', $rec->id) }}')"
                                                        class="flex w-full items-center px-4 py-2.5 text-sm text-blue-700 hover:bg-blue-50 transition gap-2 font-bold">
                                                    <i data-lucide="printer" class="h-4 w-4"></i> Print Manager
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="18" class="px-8 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 mb-4">
                                            <i data-lucide="file-text" class="h-6 w-6"></i>
                                        </div>
                                        <p class="text-slate-500 font-medium">No approved recommendations ready for RofO.</p>
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

<!-- ═══════════════════════ Batch Print Modal ═══════════════════════ -->
<div id="batchPrintModal" class="fixed inset-0 z-[1000090] hidden bg-slate-900/60 p-4 overflow-y-auto">
    <div class="mx-auto mt-10 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4 bg-gradient-to-r from-indigo-900 to-indigo-700 px-6 py-4 rounded-t-2xl">
            <div>
                <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                    <i data-lucide="printer" class="h-5 w-5 text-indigo-300"></i> Batch Print RofO
                </h3>
                <p class="text-indigo-200 text-xs mt-0.5">Select generated RofOs to print — each has 3 copies × 2 pages</p>
            </div>
            <button type="button" onclick="closeBatchPrintModal()" class="text-indigo-300 hover:text-white p-1 rounded-lg hover:bg-white/10 transition">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-5">
            <!-- Loading state -->
            <div id="bpmLoading" class="flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                <p class="text-slate-500 text-sm">Loading unprinted RofOs…</p>
            </div>

            <!-- Empty state -->
            <div id="bpmEmpty" class="hidden flex flex-col items-center justify-center py-12 gap-3">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                    <i data-lucide="check-circle" class="h-7 w-7 text-green-600"></i>
                </div>
                <p class="text-slate-700 font-bold">All RofOs have been printed</p>
                <p class="text-slate-400 text-sm">Nothing pending in the batch queue.</p>
            </div>

            <!-- Records list -->
            <div id="bpmList" class="hidden">
                <!-- Select-all bar -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <label class="flex items-center gap-2 text-sm font-bold text-slate-700 cursor-pointer select-none">
                        <input type="checkbox" id="bpmSelectAll" onchange="bpmToggleAll(this.checked)"
                               class="w-4 h-4 rounded border-slate-300 text-indigo-600 cursor-pointer">
                        Select All
                    </label>
                    <span id="bpmCount" class="text-xs font-bold text-indigo-600"></span>
                </div>

                <!-- Table -->
                <div class="border border-slate-200 rounded-xl overflow-hidden max-h-72 overflow-y-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] font-black text-slate-400 uppercase tracking-widest">
                            <tr>
                                <th class="px-4 py-3 w-8"></th>
                                <th class="px-4 py-3">File Number</th>
                                <th class="px-4 py-3">Applicant</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3 text-center">Serial</th>
                            </tr>
                        </thead>
                        <tbody id="bpmTableBody" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>

                <!-- Print info -->
                <div class="mt-3 p-3 bg-indigo-50 rounded-xl border border-indigo-100 text-xs text-indigo-700 font-semibold flex items-start gap-2">
                    <i data-lucide="info" class="h-4 w-4 shrink-0 mt-0.5"></i>
                    <span>Each RofO prints <strong>3 copies</strong> (Original · Duplicate · Triplicate) × <strong>2 pages</strong> = <strong>6 pages per application</strong>. Once printed they leave this queue.</span>
                </div>
            </div>

            <!-- Post-print confirm state -->
            <div id="bpmConfirm" class="hidden flex flex-col items-center justify-center py-8 gap-4">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center">
                    <i data-lucide="printer" class="h-7 w-7 text-amber-600"></i>
                </div>
                <p class="text-slate-700 font-bold text-center">Did the documents print successfully?</p>
                <p class="text-slate-400 text-sm text-center">Confirming will mark the selected RofOs as printed and remove them from this queue.</p>
                <div class="flex gap-3 mt-2">
                    <button type="button" onclick="bpmConfirmLog()"
                        class="px-6 py-2.5 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition text-sm">
                        Yes, Mark as Printed
                    </button>
                    <button type="button" onclick="bpmCancelConfirm()"
                        class="px-6 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition text-sm">
                        No, Go Back
                    </button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 border-t border-slate-100 flex justify-between items-center rounded-b-2xl bg-slate-50">
            <button type="button" onclick="closeBatchPrintModal()"
                class="px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200 rounded-xl transition">Cancel</button>
            <button type="button" id="bpmPrintBtn" onclick="bpmDoPrint()" disabled
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition text-sm disabled:opacity-40 disabled:cursor-not-allowed">
                <i data-lucide="printer" class="h-4 w-4"></i>
                <span id="bpmPrintBtnLabel">Print Selected</span>
            </button>
        </div>
    </div>
</div>

<script>
var _bpmRecords    = [];   // all unprinted records
var _bpmSelected   = [];   // ids selected for this batch
var _bpmPrintWin   = null;

function openBatchPrintModal() {
    document.getElementById('batchPrintModal').classList.remove('hidden');
    bpmShowState('loading');
    _bpmRecords = []; _bpmSelected = [];
    bpmUpdatePrintBtn();

    fetch('{{ route('land-rofos.unprinted-json') }}', { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(function(res) {
            if (!res.success) throw new Error('Server error');
            _bpmRecords = res.data || [];
            if (_bpmRecords.length === 0) {
                bpmShowState('empty');
            } else {
                bpmRenderTable();
                bpmShowState('list');
            }
            if (window.lucide) window.lucide.createIcons();
        })
        .catch(function() {
            bpmShowState('empty');
        });

    // Listen for afterprint message from batch window
    window.addEventListener('message', bpmHandleMessage);
}

function closeBatchPrintModal() {
    document.getElementById('batchPrintModal').classList.add('hidden');
    window.removeEventListener('message', bpmHandleMessage);
    if (_bpmPrintWin && !_bpmPrintWin.closed) _bpmPrintWin.close();
    _bpmPrintWin = null;
}

function bpmShowState(state) {
    ['bpmLoading','bpmEmpty','bpmList','bpmConfirm'].forEach(function(id) {
        document.getElementById(id).classList.add('hidden');
    });
    var el = document.getElementById('bpm' + state.charAt(0).toUpperCase() + state.slice(1));
    if (el) el.classList.remove('hidden');
    document.getElementById('bpmPrintBtn').style.display = (state === 'list') ? '' : 'none';
}

function bpmRenderTable() {
    var tbody = document.getElementById('bpmTableBody');
    tbody.innerHTML = '';
    _bpmRecords.forEach(function(rec) {
        var tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition';
        tr.innerHTML =
            '<td class="px-4 py-2.5"><input type="checkbox" class="bpm-cb w-4 h-4 rounded border-slate-300 text-indigo-600 cursor-pointer" value="' + rec.id + '" onchange="bpmOnCheck()"></td>' +
            '<td class="px-4 py-2.5 font-mono font-bold text-slate-900 whitespace-nowrap">' + (rec.file_number||'') + '</td>' +
            '<td class="px-4 py-2.5 text-slate-700 whitespace-nowrap uppercase text-xs">' + (rec.applicant_name||'') + '</td>' +
            '<td class="px-4 py-2.5 text-slate-500 text-xs whitespace-nowrap">' + (rec.location||'') + '</td>' +
            '<td class="px-4 py-2.5 text-center"><span class="text-xs font-bold ' + (rec.land_rofo_serial_no ? 'text-emerald-700' : 'text-slate-300 italic') + '">' + (rec.land_rofo_serial_no || 'None') + '</span></td>';
        tbody.appendChild(tr);
    });
    // Select all by default
    document.getElementById('bpmSelectAll').checked = true;
    bpmToggleAll(true);
}

function bpmToggleAll(checked) {
    document.querySelectorAll('.bpm-cb').forEach(function(cb) { cb.checked = checked; });
    bpmOnCheck();
}

function bpmOnCheck() {
    _bpmSelected = [];
    document.querySelectorAll('.bpm-cb:checked').forEach(function(cb) { _bpmSelected.push(parseInt(cb.value)); });
    var total = document.querySelectorAll('.bpm-cb').length;
    document.getElementById('bpmSelectAll').checked = (_bpmSelected.length === total && total > 0);
    document.getElementById('bpmSelectAll').indeterminate = (_bpmSelected.length > 0 && _bpmSelected.length < total);
    document.getElementById('bpmCount').textContent = _bpmSelected.length + ' of ' + total + ' selected · ' + (_bpmSelected.length * 6) + ' pages';
    bpmUpdatePrintBtn();
}

function bpmUpdatePrintBtn() {
    var btn = document.getElementById('bpmPrintBtn');
    var lbl = document.getElementById('bpmPrintBtnLabel');
    btn.disabled = (_bpmSelected.length === 0);
    lbl.textContent = _bpmSelected.length > 0
        ? 'Print ' + _bpmSelected.length + ' RofO' + (_bpmSelected.length > 1 ? 's' : '')
        : 'Print Selected';
}

function bpmDoPrint() {
    if (_bpmSelected.length === 0) return;

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route('land-rofos.batch-print') }}';
    form.target = '_blank';

    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    form.appendChild(csrf);

    _bpmSelected.forEach(function(id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        form.appendChild(inp);
    });

    document.body.appendChild(form);
    _bpmPrintWin = window.open('', '_blank');
    form.target = '_blank';
    form.submit();
    document.body.removeChild(form);

    // Show confirm step immediately (afterprint message also triggers this)
    setTimeout(function() { bpmShowState('confirm'); }, 1200);
}

function bpmHandleMessage(e) {
    if (e.data && e.data.type === 'rofo_batch_printed') {
        bpmShowState('confirm');
    }
}

function bpmConfirmLog() {
    if (_bpmSelected.length === 0) { closeBatchPrintModal(); return; }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch('{{ route('land-rofos.batch-print-log') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ ids: _bpmSelected })
    })
    .then(r => r.json())
    .then(function(res) {
        if (res.success) {
            Swal.fire({ icon:'success', title:'Logged', text: res.count + ' RofO(s) marked as printed.', toast:true, position:'top-end', showConfirmButton:false, timer:3000 });
            closeBatchPrintModal();
        } else {
            Swal.fire({ icon:'error', title:'Error', text: res.message || 'Failed to log prints.' });
        }
    })
    .catch(function() {
        Swal.fire({ icon:'error', title:'Error', text:'Network error logging batch print.' });
    });
}

function bpmCancelConfirm() {
    bpmShowState('list');
}
</script>

<!-- RofO Data Entry Modal -->
<div x-data="rofoModal" 
     @open-rofo-modal.window="openModal($event.detail)"
     x-show="isOpen" 
     class="fixed inset-0 z-50 overflow-y-auto" 
     style="display: none;">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="isOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" 
             @click="closeModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div x-show="isOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block w-full max-w-2xl overflow-hidden text-left align-middle transition-all transform bg-white shadow-2xl rounded-2xl sm:my-8 sm:align-middle">
            
            <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">Generate RofO Offer</h3>
                    <p class="text-sm text-slate-500 mt-1">Please enter the required data for <span class="font-bold text-blue-600" x-text="fileNumber"></span></p>
                </div>
                <button @click="closeModal()" class="text-slate-400 hover:text-slate-600 transition">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <form @submit.prevent="submitForm">
                <div class="px-8 pt-6 pb-2 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Land Use Category</label>
                        <select x-model="selectedLandUseId" disabled
                            class="w-full border border-slate-200 rounded-lg px-4 py-2 bg-slate-100 text-slate-500 cursor-not-allowed shadow-none outline-none transition text-sm font-bold">
                            <option value="">Select Category</option>
                            @foreach($landUses as $lu)
                                <option value="{{ $lu->id }}">{{ $lu->landuse }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Purpose Clause</label>
                        <select x-model="selectedPurposeId" disabled
                            class="w-full border border-slate-200 rounded-lg px-4 py-2 bg-slate-100 text-slate-500 cursor-not-allowed shadow-none outline-none transition text-sm font-bold">
                            <option value="">Select Purpose</option>
                            <template x-for="p in purposes" :key="p.id">
                                <option :value="p.id" x-text="p.name" :selected="p.id == selectedPurposeId"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="px-8 py-8 space-y-6">
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Survey Fees (₦)</label>
                            <input type="number" step="0.01" x-model="formData.rofo_survey_fees" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed transition outline-none" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Dev. Charge (₦)</label>
                            <input type="number" step="0.01" x-model="formData.rofo_dev_charge" readonly class="w-full px-4 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 cursor-not-allowed transition outline-none" placeholder="0.00">
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <div class="p-4 bg-slate-50 rounded-xl">
                            <span class="block text-sm font-bold text-slate-700 mb-3">Survey Method (Select One)</span>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-white transition bg-white">
                                    <input type="radio" x-model="surveyMethod" value="DIRECTOR" class="w-5 h-5 text-blue-600 border-slate-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-slate-700">Require <strong>Director Survey</strong> to carry out survey</span>
                                </label>
                                <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-white transition bg-white">
                                    <input type="radio" x-model="surveyMethod" value="LICENSED" class="w-5 h-5 text-blue-600 border-slate-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-slate-700">Require <strong>Licensed Surveyor</strong> to carry out survey</span>
                                </label>
                            </div>
                        </div>


                        <div class="grid grid-cols-2 gap-6">
                               <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Time Generated</label>
                            <input type="time" x-model="formData.rofo_time_generated" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Date Generated</label>
                            <input type="date" x-model="formData.rofo_date_generated" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 transition outline-none">
                        </div>
                     
                    </div>
                    </div>
                </div>

                <div class="px-8 py-6 bg-slate-50 border-t border-slate-200 flex justify-end gap-3 rounded-b-2xl">
                    <button type="button" @click="closeModal()" class="px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition">
                        Cancel
                    </button>
                    <button type="submit" :disabled="isSubmitting" class="px-8 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-lg shadow-blue-200 transition flex items-center gap-2 disabled:opacity-50">
                        <template x-if="!isSubmitting">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                        </template>
                        <template x-if="isSubmitting">
                            <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </template>
                        Generate RofO
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

 
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('rofoModal', () => ({
            isOpen: false,
            isSubmitting: false,
            fileNumber: '',
            landUse: '',
            purposeClause: '',
            recommendationId: null,
            selectedLandUseId: '',
            selectedPurposeId: '',
            purposes: [],
            isLoadingPurposes: false,
            landUsesList: @json($landUses),
            surveyMethod: '', // DIRECTOR or LICENSED
            formData: {
                rofo_land_use_category: '',
                rofo_survey_fees: '',
                rofo_dev_charge: '',
                // rofo_director_survey and rofo_licensed_surveyor will be set based on surveyMethod
                rofo_date_generated: new Date().toISOString().split('T')[0],
                rofo_time_generated: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
            }, 

            openModal(data) {
                this.recommendationId = data.id;
                this.fileNumber = data.fileNumber;
                this.landUse = data.landUse;
                this.purposeClause = data.purposeClause;
                this.selectedLandUseId = data.landUseId || '';
                this.selectedPurposeId = data.purposeId || '';
                this.formData.rofo_dev_charge = data.developmentCharge ? parseFloat(data.developmentCharge) : '';
                this.formData.rofo_survey_fees = data.surveyFees ? parseFloat(data.surveyFees) : '';
                
                // Set rofo_land_use_category from the passed landUse string
                this.formData.rofo_land_use_category = data.landUse;

                // Reset survey method
                this.surveyMethod = '';

                this.isOpen = true;

                if (this.selectedLandUseId) {
                    this.fetchPurposes(this.selectedPurposeId);
                }

                this.$nextTick(() => lucide.createIcons());
            },

            async fetchPurposes(preselectedId = null) {
                if (!this.selectedLandUseId) {
                    this.purposes = [];
                    this.selectedPurposeId = '';
                    return;
                }

                this.isLoadingPurposes = true;
                try {
                    const response = await fetch(`{{ url('api/reference/purposes') }}?landuseid=${this.selectedLandUseId}`);
                    const result = await response.json();
                    if (result.success) {
                        this.purposes = result.data;
                        if (preselectedId) {
                            this.selectedPurposeId = preselectedId;
                        }
                    }
                } catch (error) {
                    console.error('Error fetching purposes:', error);
                } finally {
                    this.isLoadingPurposes = false;
                }
            },

            closeModal() {
                this.isOpen = false;
                this.resetForm();
            },

            resetForm() {
                this.surveyMethod = '';
                this.formData = {
                    rofo_land_use_category: '',
                    rofo_survey_fees: '',
                    rofo_dev_charge: '',
                    rofo_director_survey: 'NO',
                    rofo_licensed_surveyor: 'NO',
                    rofo_date_generated: new Date().toISOString().split('T')[0],
                    rofo_time_generated: new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
                };
            },

            async submitForm() {
                if (!this.selectedLandUseId || !this.selectedPurposeId || !this.surveyMethod) {
                    Swal.fire('Error', 'Please fill all required fields and select a survey method.', 'error');
                    return;
                }

                this.isSubmitting = true;
                
                // Map surveyMethod to backend fields
                const payload = {
                    ...this.formData,
                    land_use_id: this.selectedLandUseId,
                    purpose_id: this.selectedPurposeId,
                    rofo_director_survey: this.surveyMethod === 'DIRECTOR' ? 'YES' : 'NO',
                    rofo_licensed_surveyor: this.surveyMethod === 'LICENSED' ? 'YES' : 'NO'
                };

                try {
                    const response = await fetch(`{{ url('land-rofos') }}/${this.recommendationId}/generate`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();

                    if (data.success) {
                        this.closeModal();
                        Swal.fire('Generated!', 'RofO has been generated successfully.', 'success')
                        .then(() => window.location.reload());
                    } else {
                        throw new Error(data.message || 'Submission failed');
                    }
                } catch (error) {
                    Swal.fire('Error', error.message, 'error');
                } finally {
                    this.isSubmitting = false;
                }
            }
        }));
    });

    function generateRofo(id, fileNumber, developmentCharge, landUse, purposeClause, landUseId, purposeId, surveyFees) {
        window.dispatchEvent(new CustomEvent('open-rofo-modal', {
            detail: { id, fileNumber, developmentCharge, landUse, purposeClause, landUseId, purposeId, surveyFees }
        }));
    }

    function quickGenerateRofo(id, fileNumber) {
        Swal.fire({
            title: 'Generate RofO?',
            html: `Generate RofO for file <strong class="text-blue-600">${fileNumber}</strong>?<br><small class="text-slate-500">Survey method and dates are pre-filled from the recommendation form.</small>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Generate',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch(`{{ url('land-rofos') }}/${id}/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(r => { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                .catch(err => Swal.showValidationMessage(`Request failed: ${err}`));
            }
        }).then(result => {
            if (result.isConfirmed && result.value?.success) {
                Swal.fire('Generated!', 'RofO has been generated successfully.', 'success')
                .then(() => window.location.reload());
            } else if (result.isConfirmed) {
                Swal.fire('Error', result.value?.message || 'Generation failed.', 'error');
            }
        });
    }

    // Edit RofO Record with Reason
    function editRofORecord(id, editUrl) {
        Swal.fire({
            title: 'Edit RofO Record',
            text: 'Please provide a reason for editing this record:',
            input: 'textarea',
            inputPlaceholder: 'Enter reason for edit here...',
            inputAttributes: {
                'aria-label': 'Reason for edit'
            },
            showCancelButton: true,
            confirmButtonText: 'Proceed to Edit',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#2563eb',
            inputValidator: (value) => {
                if (!value) {
                    return 'You must provide a reason for editing!'
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect to edit page with the reason as a query parameter
                // The controller's update method will pick it up or we can pass it to the form
                const url = new URL(editUrl);
                url.searchParams.set('edit_reason', result.value);
                window.location.href = url.toString();
            }
        });
    }

    // Assign Security Paper Code with Select2
    function assignSecurityPaperCode(id, fileNumber, currentSerial) {
        const serials = @json($availableSerials);
        const serialData = serials.map(s => ({ id: s.paper_code, text: s.paper_code }));

        Swal.fire({
            title: 'Enter Security Paper Code',
            html: `
                <div class="text-left mb-4">
                    <p class="text-sm text-slate-500 mb-2">Enter security paper code for file <span class="font-bold text-blue-600">${fileNumber}</span></p>
                </div>
                <select id="swal-serial-select" class="w-full border border-slate-200 rounded-lg px-4 py-2.5 outline-none focus:ring-2 focus:ring-blue-500/20 shadow-sm">
                </select>
            `,
            showCancelButton: true,
            confirmButtonText: 'Assign Code',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#10b981',
            didOpen: () => {
                $('#swal-serial-select').select2({
                    dropdownParent: $('.swal2-container'),
                    width: '100%',
                    placeholder: 'Type to search security paper code...',
                    minimumInputLength: 1,
                    ajax: {
                        transport: function (params, success) {
                            const term = (params.data.term || '').toLowerCase();
                            const results = serialData.filter(s => s.text.toLowerCase().includes(term));
                            success({ results });
                        },
                        delay: 150
                    },
                    language: {
                        inputTooShort: () => 'Type at least 1 character to search...',
                        noResults: () => 'No matching code found'
                    }
                });
                // Focus the Select2 search input immediately
                setTimeout(() => {
                    $('.swal2-container .select2-search__field').first().trigger('focus');
                }, 100);
            },
            preConfirm: () => {
                const paperCode = $('#swal-serial-select').val();
                if (!paperCode) {
                    Swal.showValidationMessage('Please select a security paper code');
                }
                return paperCode;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Assigning...',
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false
                });

                fetch(`{{ url('land-rofos') }}/${id}/assign-security-paper`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ paper_code: result.value })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success', 'Security paper code assigned successfully.', 'success')
                        .then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Assignment failed', 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'An unexpected error occurred.', 'error');
                });
            }
        });
    }
</script>
 
@endsection
