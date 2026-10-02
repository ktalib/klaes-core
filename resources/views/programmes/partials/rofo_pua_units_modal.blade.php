<div class="p-4 space-y-4">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">PuA Overview</h3>
            <div class="text-sm text-gray-600 leading-relaxed">
                <div><span class="font-medium text-gray-700">Primary File No:</span> {{ $mother->fileno ?? 'N/A' }}</div>
                @if(!empty($mother->np_fileno))
                    <div><span class="font-medium text-gray-700">NP File No:</span> {{ $mother->np_fileno }}</div>
                @endif
                <div><span class="font-medium text-gray-700">Owner:</span> {{ $mother->owner_name ?? 'N/A' }}</div>
                @if(!empty($mother->property_label))
                    <div><span class="font-medium text-gray-700">Location:</span> {{ $mother->property_label }}</div>
                @endif
                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full">Units: {{ $stats['total_units'] ?? 0 }}</span>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full">Memos: {{ $stats['memo_generated'] ?? 0 }} / {{ $stats['total_units'] ?? 0 }}</span>
                    <span class="px-3 py-1 bg-violet-100 text-violet-700 rounded-full">RoFO: {{ $stats['rofo_generated'] ?? 0 }} / {{ $stats['total_units'] ?? 0 }}</span>
                </div>
            </div>
        </div>
        <button type="button" data-dismiss="modal" class="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded-full p-1" aria-label="Close">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>
    

    <div class="table-container">
        <table class="simple w-full">
            <thead>
                <tr>
                    <th>Unit ST FileNo</th>
                    <th>Unit Details</th>
                    <th>Owner</th>
                    <th>Memo Status</th>
                    <th>RoFO Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($units as $unit)
                    <tr>
                        <td>{{ $unit->fileno ?? 'N/A' }}</td>
                        <td>
                            <div>{{ $unit->unit_label !== '' ? $unit->unit_label : 'N/A' }}</div>
                            @if(!empty($unit->scheme_no))
                                <div class="muted text-xs">Scheme: {{ $unit->scheme_no }}</div>
                            @endif
                        </td>
                        <td>{{ $unit->owner_name ?? 'N/A' }}</td>
                        <td>
                            @if($unit->has_st_memo ?? false)
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">Generated</span>
                                @if(!empty($unit->memo_uploaded_at))
                                    <div class="muted text-xs">{{ date('d-m-Y', strtotime($unit->memo_uploaded_at)) }}</div>
                                @endif
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-semibold">Pending</span>
                            @endif
                        </td>
                        <td>
                            @if($unit->rofo_generated ?? false)
                                <div class="font-semibold text-gray-800">{{ $unit->rofo_no }}</div>
                                <div class="muted text-xs">Prints: {{ (int) ($unit->print_counter ?? 0) }}</div>
                            @elseif($unit->details_captured ?? false)
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold">Details Captured</span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">Not Generated</span>
                            @endif
                        </td>


                        <td class="actions">
                            <div class="dd">
                                <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                </button>
                                <div class="dd-menu">
                                    <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unit->id) }}" class="dd-item">
                                        <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                                        <span>View Application</span>
                                    </a>

                                    @if($unit->rofo_generated ?? false)
                                        @if($unit->security_paper_code)
                                            <span class="dd-item disabled" title="Security code already assigned">
                                                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i>
                                                <span>Code Assigned: {{ $unit->security_paper_code }}</span>
                                            </span>
                                            <button type="button" class="dd-item"
                                                onclick="closeAllDD(); resetSecurityPaperModal('{{ $unit->id }}', @js($unit->fileno), @js($unit->security_paper_code))">
                                                <i data-lucide="rotate-ccw" class="w-4 h-4 text-red-500"></i>
                                                <span class="text-red-600">Reset Security Paper Code</span>
                                            </button>
                                        @elseif(($unit->print_counter ?? 0) < 1)
                                            <span class="dd-item disabled" title="Print the RoFO before entering a security code">
                                                <i data-lucide="shield" class="w-4 h-4 text-slate-400"></i>
                                                <span>Security Code (print first)</span>
                                            </span>
                                        @else
                                            <button type="button" class="dd-item"
                                                onclick="closeAllDD(); openSecurityPaperModal('{{ $unit->id }}', '{{ $unit->fileno }}', '{{ $unit->security_paper_code }}')">
                                                <i data-lucide="shield" class="w-4 h-4 text-emerald-400"></i>
                                                <span>Security Code</span>
                                            </button>
                                        @endif

                                        {{-- 
                                        <a href="{{ route('programmes.generate_cofo', $unit->id) }}" class="dd-item" onclick="closeAllDD()">
                                            <i data-lucide="file-plus" class="w-4 h-4 text-emerald-500"></i>
                                            <span>Generate/Edit CofO</span>
                                        </a>

                                        <a href="{{ route('programmes.print_cofo', $unit->id) }}" target="_blank" class="dd-item" onclick="closeAllDD()">
                                            <i data-lucide="printer" class="w-4 h-4 text-purple-400"></i>
                                            <span>Print CofO</span>
                                        </a>
                                        --}}
                                    @else
                                        {{-- 
                                        <span class="dd-item disabled" title="Generate RoFO first">
                                            <i data-lucide="file-plus" class="w-4 h-4 opacity-40"></i>
                                            <span>Generate CofO</span>
                                        </span>
                                        --}}
                                    @endif
                                </div>
                            </div>
                        </td>


 
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="flex justify-end">
        <button type="button" data-dismiss="modal" class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            Close
        </button>
    </div>
</div>
