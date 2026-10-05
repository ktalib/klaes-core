@extends('layouts.app')

@section('page-title')
    {{ __('Valuation Reports') }}
@endsection

@section('content')
    <div x-data="{}" class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header', [
            'PageTitle' => 'Valuation Reports',
            'PageDescription' => 'Generate and manage Valuation Reports for landed properties and buildings.'
        ])
    @include('exports.consolidated_report_action', ['reportKey' => 'valuation'])

        <div class="p-6 space-y-8 max-w-7xl mx-auto">
            <!-- Hero -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm p-6 lg:p-8 space-y-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div>
                        <p class="text-xs font-semibold text-indigo-500 uppercase tracking-widest">EDMS Registry</p>
                        <h1 class="text-3xl md:text-4xl font-bold text-slate-900 mt-1">Valuation Reports</h1>
                        <p class="text-base text-slate-500 mt-2 max-w-2xl">
                            Generate comprehensive valuation reports with construction details, services, and market observations.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="$dispatch('open-valuation-modal', null)"
                            class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow hover:bg-blue-700 transition">
                            <i data-lucide="plus-circle" class="h-5 w-5"></i>
                            Generate New Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                 <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Captured Today</p>
                            <p class="text-3xl font-bold text-purple-700 mt-2">{{ $todayCount }}</p>
                        </div>
                        <div class="w-12 h-12 bg-purple-50 rounded-2xl flex items-center justify-center">
                            <i data-lucide="calendar" class="h-6 w-6 text-purple-600"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Reports</p>
                    <p class="text-3xl font-bold text-slate-900 mt-2">{{ $totalCount }}</p>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Printed</p>
                    <p class="text-3xl font-bold text-blue-700 mt-2">{{ $printedCount }}</p>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-xs font-semibold text-amber-600 uppercase tracking-wider">Unprinted</p>
                    <p class="text-3xl font-bold text-amber-700 mt-2">{{ $unprintedCount }}</p>
                </div>
               
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                {{-- Searches the full register (owner/client name and file number only) on
                     the server, so a file is found even when it isn't on the current page. --}}
                <div class="px-6 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center gap-3">
                    <form action="{{ route('valuation-reports.index') }}" method="GET" class="relative flex-1 max-w-md">
                        <i data-lucide="search" class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input type="search" name="search" value="{{ $search }}" autocomplete="off"
                            placeholder="Search by owner / client name or file number…"
                            class="w-full pl-10 pr-10 py-2.5 rounded-xl border border-slate-200 bg-slate-50/70 text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition">
                        @if($search !== '')
                            <a href="{{ route('valuation-reports.index') }}"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
                                aria-label="Clear search">
                                <i data-lucide="x" class="h-4 w-4"></i>
                            </a>
                        @endif
                    </form>
                    <p class="text-xs font-semibold text-slate-500 whitespace-nowrap">
                        Showing {{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }} of {{ $reports->total() }} reports
                    </p>
                </div>
                <div class="overflow-x-auto text-sm">
                    <table class="w-full text-left border-collapse" id="valuation-table">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">S/N</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">File Number</th>
                                {{-- Where the file stands in Valuation -> Consent -> Registration.
                                     Painted by public/js/deeds-pipeline.js from one batched lookup. --}}
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap" title="Valuation → Consent → Registration">Workflow</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Owner / Client</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Property Type</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">House No</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">Street Name</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">District</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">LGA</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Generated By</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Inspection Date</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Time Generated</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Date Generated</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Prints</th>
                                <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reports as $report)
                                <tr class="hover:bg-slate-50/50 transition duration-200">
                                    <td class="px-6 py-4 text-slate-500 font-semibold">{{ $reports->firstItem() + $loop->index }}</td>
                                    <td class="px-6 py-4 text-slate-900 font-bold whitespace-nowrap">
                                        {{ $report->file_number }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span data-pipeline-file="{{ $report->file_number }}"></span>
                                    </td>
                                    <td class="px-6 py-4 text-slate-900 font-semibold">{{ $report->full_name }}</td>
                                    <td class="px-6 py-4 text-slate-600 font-medium">{{ $report->property_type }}</td>
                                    {{-- Address in parts. property_no is the number on the building;
                                         plot_no is the survey parcel. town_city is the district. --}}
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap">{{ $report->property_no ?: '—' }}</td>
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap">{{ $report->plot_no ?: '—' }}</td>
                                    <td class="px-6 py-4 text-slate-600 font-medium max-w-[180px] truncate" title="{{ $report->street_name }}">{{ $report->street_name ?: '—' }}</td>
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap">{{ $report->town_city ?: '—' }}</td>
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap">{{ $report->lga ?: '—' }}</td>
                                    <td class="px-6 py-4 text-slate-600 font-medium max-w-[240px] truncate" title="{{ $report->address }}">{{ $report->address ?: '—' }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
                                                <i data-lucide="user-check" class="h-3.5 w-3.5"></i>
                                            </div>
                                            @php $generatedBy = $report->user ? trim($report->user->first_name . ' ' . $report->user->last_name) : ''; @endphp
                                            @if($generatedBy !== '')
                                                <span class="upc-trigger text-slate-600 text-sm font-medium" data-user-card
                                                      data-user-id="{{ $report->user->id }}"
                                                      title="{{ __('View profile') }}">{{ $generatedBy }}</span>
                                            @else
                                                <span class="text-slate-600 text-sm font-medium">System</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs">
                                        {{ $report->inspection_date ? $report->inspection_date->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs">
                                        {{ $report->created_at->format('h:i A') }}
                                    </td>
                                    <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs">
                                        {{ $report->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full {{ $report->print_count > 0 ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                            <span class="font-bold text-slate-700">{{ $report->print_count }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-center">
                                            <div x-data="{ open: false }" class="relative inline-block text-left">
                                                <button type="button" @click="open = !open" @click.away="open = false"
                                                    class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition duration-200">
                                                    <i data-lucide="more-horizontal" class="h-5 w-5"></i>
                                                </button>

                                                <div x-show="open" x-cloak
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="transform opacity-0 scale-95"
                                                    x-transition:enter-end="transform opacity-100 scale-100"
                                                    class="absolute right-0 z-50 {{ $loop->remaining < 2 ? 'bottom-full mb-2 origin-bottom-right' : 'mt-2 origin-top-right' }} w-52 rounded-2xl bg-white shadow-2xl ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden border border-slate-100">
                                                    <div class="py-2">
                                                        <div class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-50 mb-1">Actions</div>
                                                        
                                                        @if($report->print_count == 0)
                                                            <button type="button" @click="$dispatch('open-valuation-modal', {{ json_encode($report) }}); open = false"
                                                                class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-amber-600 hover:bg-amber-50 transition font-bold">
                                                                <i data-lucide="edit-3" class="h-4 w-4"></i>
                                                                <span>Edit Report</span>
                                                            </button>
                                                        @else
                                                            <button type="button" @click="open = false"
                                                                data-reeval='{{ json_encode([
                                                                    'id' => $report->id,
                                                                    'file_number' => $report->file_number,
                                                                    'full_name' => $report->full_name,
                                                                    'value_figures' => $report->value_figures,
                                                                    'value_words' => $report->value_words,
                                                                    're_value_figures' => $report->re_value_figures,
                                                                    're_value_words' => $report->re_value_words,
                                                                ]) }}'
                                                                onclick="openReevaluateModal(JSON.parse(this.dataset.reeval))"
                                                                class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-purple-600 hover:bg-purple-50 transition font-bold">
                                                                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                                                <span>Need to Re-Evaluate</span>
                                                            </button>
                                                        @endif


                                                        @if($report->print_count > 0)
                                                            <button type="button"
                                                                @click="open = false"
                                                                onclick="resetValuationPrints({{ $report->id }}, '{{ addslashes($report->file_number) }}', {{ $report->print_count }})"
                                                                class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-rose-600 hover:bg-rose-50 transition font-bold">
                                                                <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                                                                <span>Master Reset (Prints)</span>
                                                            </button>
                                                        @endif

                                                          @if($report->print_count < 2)
                                                        <a href="{{ route('valuation-reports.show', $report->id) }}" target="_blank"
                                                            class="flex items-center gap-3 px-4 py-2.5 text-xs text-blue-600 hover:bg-blue-50 transition font-bold"
                                                            @click="open = false">
                                                            <i data-lucide="printer" class="h-4 w-4"></i>
                                                            <span>Print</span>
                                                        </a>
                                                         @else
                                                        <div class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-slate-300 cursor-not-allowed font-bold" title="Cannot edit after printing">
                                                                <i data-lucide="printer" class="h-4 w-4"></i>
                                                                <span>Print</span>
                                                            </div>
                                                            @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="17" class="px-6 py-12 text-center text-slate-500 italic">
                                        @if($search !== '')
                                            No report matches "{{ $search }}".
                                        @else
                                            No valuation reports found. Click "Generate" to create your first one.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $reports->links() }}
                </div>
            </div>
        </div>
    </div>
     @include('valuation_reports.partials.reevaluate_modal')
    @include('valuation_reports.partials.modal')
   
    @include('components.global-fileno-modal')
 
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/valuation_reports.js') }}"></script>
    {{-- Paints the Workflow column. Batches every row on the page into one lookup. --}}
    <script src="{{ asset('js/deeds-pipeline.js') }}?v={{ @filemtime(public_path('js/deeds-pipeline.js')) }}"></script>
    <script>
        // Workflow strip inside the Generate/Edit Valuation Report modal.
        // Watched rather than bound to the picker: the field is driven by
        // Alpine's x-model, which writes .value without firing an input event.
        // Valuation opens the pipeline, so this strip never blocks.
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.DeedsPipeline) return;
            window.DeedsPipeline.watch('#valuation-pipeline-strip', {
                fileSel: '#valuation_file_number',
                stage: 'valuation'
            });
        });
    </script>
    <script>
        // Master reset: clears the print count so the report becomes editable
        // and printable again.
        window.resetValuationPrints = function (id, fileNumber, printCount) {
            Swal.fire({
                icon: 'warning',
                title: 'Master Reset?',
                html: `This will reset the print count for <b>${fileNumber}</b> from <b>${printCount}</b> back to <b>0</b>.<br><br>The report becomes editable and printable again.`,
                showCancelButton: true,
                confirmButtonText: 'Yes, reset it',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e11d48'
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                try {
                    const response = await fetch(`/valuation-reports/reset-prints/${id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const data = await response.json();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Print Count Reset',
                            text: data.message,
                            timer: 1600,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    } else {
                        Swal.fire('Error', data.message || 'Could not reset the print count.', 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Could not reset the print count. ' + e.message, 'error');
                }
            });
        };

        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide) {
                window.lucide.createIcons();
            }
            if (window.GlobalFileNoModal) {
                window.GlobalFileNoModal.init();
            }
        });
    </script>
@endsection


