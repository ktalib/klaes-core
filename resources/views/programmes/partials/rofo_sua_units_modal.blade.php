<div class="p-4 space-y-4">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">SUA Application Overview</h3>
            <div class="text-sm text-gray-600 leading-relaxed">
                <div><span class="font-medium text-gray-700">Application Type:</span> Standalone Unit Application (SUA)</div>
                <div class="mt-2 flex flex-wrap gap-3 text-xs">
                    <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full">SUA Units: {{ $stats['total_units'] ?? count($units) }}</span>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full">Memos: {{ $stats['memo_generated'] ?? 0 }} / {{ $stats['total_units'] ?? count($units) }}</span>
                    <span class="px-3 py-1 bg-violet-100 text-violet-700 rounded-full">RoFO: {{ $stats['rofo_generated'] ?? 0 }} / {{ $stats['total_units'] ?? count($units) }}</span>
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
                <tr class="text-xs font-medium text-gray-500 uppercase tracking-wide">
                    <th class="py-2 px-4 text-left">ST FileNo</th>
                    <th class="py-2 px-4 text-left">Unit Details</th>
                    <th class="py-2 px-4 text-left">Owner</th>
                    <th class="py-2 px-4 text-left">Land Use</th>
                    <th class="py-2 px-4 text-left">Location</th>
                    <th class="py-2 px-4 text-left">Memo Status</th>
                    <th class="py-2 px-4 text-left">RoFO Status</th>
                    <th class="py-2 px-4 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="text-sm">
                @forelse($units as $unit)
                    <tr class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="font-medium text-indigo-600">{{ $unit->fileno ?? 'N/A' }}</div>
                            @if(!empty($unit->scheme_no))
                                <div class="text-xs text-gray-500">Scheme: {{ $unit->scheme_no }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-gray-900">
                                @if(!empty($unit->unit_number))
                                    Unit: {{ $unit->unit_number }}
                                @endif
                                @if(!empty($unit->floor_number))
                                    @if(!empty($unit->unit_number)), @endif
                                    Floor: {{ $unit->floor_number }}
                                @endif
                                @if(!empty($unit->block_number))
                                    @if(!empty($unit->unit_number) || !empty($unit->floor_number)), @endif
                                    Block: {{ $unit->block_number }}
                                @endif
                            </div>
                            @if(!empty($unit->unit_type))
                                <div class="text-xs text-gray-500">Type: {{ $unit->unit_type }}</div>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-gray-900">{{ $unit->owner_name ?? 'N/A' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-gray-900">{{ $unit->land_use ?? 'N/A' }}</div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="text-gray-900 text-xs">
                                {{ $unit->property_lga ?? 'N/A' }}
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if($unit->has_st_memo ?? false)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                    Generated
                                </span>
                                @if($unit->memo_uploaded_at)
                                    <div class="text-xs text-gray-500 mt-1">{{ \Carbon\Carbon::parse($unit->memo_uploaded_at)->format('d M Y') }}</div>
                                @endif
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    Pending
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            @if($unit->rofo_generated ?? false)
                                <div class="font-medium text-indigo-600">{{ $unit->rofo_no ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500">Prints: {{ (int) ($unit->print_counter ?? 0) }}</div>
                            @elseif($unit->details_captured ?? false)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    Details Captured
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    Not Generated
                                </span>
                            @endif
                        </td>
                        <td class="table-cell">
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
                                        <a href="{{ route('programmes.generate_rofo', $unit->id) }}?url=edit_pua_rofo" class="dd-item">
                                            <i data-lucide="file-edit" class="w-4 h-4 text-indigo-400"></i>
                                            <span>View/Edit RoFO</span>
                                        </a>
                                    @else
                                        <span class="dd-item disabled" title="Generate RoFO first">
                                            <i data-lucide="file-edit" class="w-4 h-4 opacity-40"></i>
                                            <span>View/Edit RoFO</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 px-4 text-center text-gray-500">
                            No SUA units found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>