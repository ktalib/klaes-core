@php
    $summaries = ($primaries ?? collect())->values();
    $pendingPrimaries = $summaries
        ->filter(function ($summary) {
            return !($summary->all_rofo_generated ?? false);
        })
        ->values();
    $generatedPrimaries = $summaries
        ->filter(function ($summary) {
            return (bool) ($summary->all_rofo_generated ?? false);
        })
        ->values();
@endphp

<div id="wrap-pua-not" class="table-container">
    <table id="table-pua-not" class="simple">
        <thead>
            <tr>
                <th>Primary FileNo</th>
                <th>Scheme No</th>
                <th>Unit Owner</th>
                <th>Land Use</th>
                <th>LGA</th>
                <th>Total Units</th>
                <th>Memo Status</th>
                <th>RoFO Status</th>
                <th>Created By</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingPrimaries as $summary)
                @php
                    $totalUnits = (int) ($summary->total_units ?? 0);
                    $memoGenerated = (int) ($summary->memo_generated_units ?? 0);
                    $detailsCaptured = (int) ($summary->details_captured_units ?? 0);
                    $rofoGenerated = (int) ($summary->generated_units ?? 0);
                    $memoComplete = $totalUnits > 0 && $memoGenerated >= $totalUnits;
                    $detailsComplete = $totalUnits > 0 && $detailsCaptured >= $totalUnits;
                @endphp
                <tr>
                    <td>
                        @if(!empty($summary->primary_np_file_no))
                            <div class="font-semibold text-gray-800">NP: {{ $summary->primary_np_file_no }}</div>
                        @endif
                        <div class="muted text-xs">Primary: {{ $summary->primary_file_no ?? 'N/A' }}</div>
                    </td>
                    <td>{{ $summary->scheme_no ?? 'N/A' }}</td>
                    <td>{{ $summary->owner_name ?? 'N/A' }}</td>
                    <td>{{ $summary->land_use ?? 'N/A' }}</td>
                    <td>{{ $summary->property_lga ?? 'N/A' }}</td>
                    <td>{{ $summary->total_units ?? 0 }}</td>
                    <td>
                        <span>{{ $memoGenerated }} / {{ $summary->total_units ?? 0 }}</span>
                        @if($memoComplete)
                            <div class="muted text-xs">All generated</div>
                        @else
                            <div class="muted text-xs">Pending memo uploads</div>
                        @endif
                    </td>
                    <td>
                        <span>{{ $rofoGenerated }} / {{ $summary->total_units ?? 0 }}</span>
                        @if($rofoGenerated > 0)
                            <div class="muted text-xs">Partial issuance</div>
                        @else
                            <div class="muted text-xs">Not started</div>
                        @endif
                        <div class="muted text-xs mt-1">Details captured: {{ $detailsCaptured }} / {{ $summary->total_units ?? 0 }}</div>
                    </td>
                    <td>{{ $summary->created_by_name ?? '—' }}</td>
                    <td>{{ !empty($summary->created_at) ? date('d-m-Y', strtotime($summary->created_at)) : 'N/A' }}</td>
                    <td class="actions">
                        <div class="dd">
                            <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                            </button>
                            <div class="dd-menu">
                                <a href="{{ route('sectionaltitling.viewrecorddetail') }}?id={{ $summary->main_application_id }}" class="dd-item">
                                    <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                                    <span>View Primary Application</span>
                                </a>
                                <button type="button" class="dd-item customModal" data-size="xl" data-title="Primary Units" data-url="{{ route('programmes.rofo.pua-units', $summary->main_application_id) }}">
                                    <i data-lucide="layout-grid" class="w-4 h-4 text-blue-500"></i>
                                    <span>View PuA(s)</span>
                                </button>
                                @if($detailsComplete)
                                    <button type="button"
                                            class="dd-item"
                                            data-batch-rofo="1"
                                            data-primary="{{ $summary->main_application_id }}"
                                            data-reference-unit="{{ $summary->reference_unit_id }}"
                                            data-total-units="{{ $summary->total_units }}"
                                            data-url="{{ route('programmes.generate_rofo', $summary->reference_unit_id) }}?batch=1&primary={{ $summary->main_application_id }}">
                                        <i data-lucide="layers" class="w-4 h-4 text-indigo-500"></i>
                                        <span>Generate Batch RoFO</span>
                                    </button>
                                @else
                                    <span class="dd-item disabled">
                                        <i data-lucide="layers" class="w-4 h-4 opacity-40"></i>
                                        <span>
                                            Generate Batch RoFO
                                            <span class="block text-xs text-red-500 mt-1">Capture RoFO details first.</span>
                                        </span>
                                    </span>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="muted">No primaries pending RoFO generation</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="wrap-pua-gen" class="table-container" style="display:none;">
    <table id="table-pua-gen" class="simple">
        <thead>
            <tr>
                <th>Primary FileNo</th>
                <th>Scheme No</th>
                <th>Unit Owner</th>
                <th>Land Use</th>
                <th>LGA</th>
                <th>Total Units</th>
                <th>Memo Status</th>
                <th>RoFO Status</th>
                <th>Created By</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($generatedPrimaries as $summary)
                <tr>
                    <td>
                        @if(!empty($summary->primary_np_file_no))
                            <div class="font-semibold text-gray-800">NP: {{ $summary->primary_np_file_no }}</div>
                        @endif
                        <div class="muted text-xs">Primary: {{ $summary->primary_file_no ?? 'N/A' }}</div>
                    </td>
                    <td>{{ $summary->scheme_no ?? 'N/A' }}</td>
                    <td>{{ $summary->owner_name ?? 'N/A' }}</td>
                    <td>{{ $summary->land_use ?? 'N/A' }}</td>
                    <td>{{ $summary->property_lga ?? 'N/A' }}</td>
                    <td>{{ $summary->total_units ?? 0 }}</td>
                    <td>{{ $summary->memo_generated_units ?? 0 }} / {{ $summary->total_units ?? 0 }}</td>
                    <td>{{ $summary->generated_units ?? 0 }} / {{ $summary->total_units ?? 0 }}</td>
                    <td>{{ $summary->created_by_name ?? '—' }}</td>
                    <td>{{ !empty($summary->created_at) ? date('d-m-Y', strtotime($summary->created_at)) : 'N/A' }}</td>
                    <td class="actions">
                        @php
                            $unitCollection = collect($summary->units ?? []);
                            $unitMeta = $unitCollection
                                ->map(function ($unit) {
                                    $id = (int) ($unit->id ?? 0);

                                    if ($id <= 0) {
                                        return null;
                                    }

                                    return [
                                        'id' => $id,
                                        'print_counter' => (int) ($unit->print_counter ?? 0),
                                        'url' => route('programmes.view_rofo', $id),
                                    ];
                                })
                                ->filter()
                                ->values();

                            // printRofoWhiteCopy() logs against the fileno of the unit
                            // the batch is opened from — the first one — so the gate has
                            // to read the same key or it never opens.
                            $stProofUnit = $unitCollection->first(fn ($u) => (int) ($u->id ?? 0) === (int) ($unitMeta->first()['id'] ?? 0));
                            $stWcDone = isset($whiteCopyDone[strtoupper(trim((string) ($stProofUnit->fileno ?? '')))]);

                            $printCounters = $unitMeta->pluck('print_counter');
                            $hasUniformCounters = $unitMeta->isNotEmpty() && $printCounters->unique()->count() === 1;
                            $currentCounter = $hasUniformCounters ? $printCounters->first() : null;

                            $stageLabels = [
                                1 => 'First Print',
                                2 => 'Second Print',
                                3 => 'Third Print',
                            ];

                            $stageAvailability = [
                                1 => $hasUniformCounters && $currentCounter === 0,
                                2 => $hasUniformCounters && $currentCounter === 1,
                                3 => $hasUniformCounters && $currentCounter === 2,
                            ];
                            $stageValues = [
                                1 => 'ORIGINAL',
                                2 => 'DUPLICATE',
                                3 => 'TRIPLICATE',
                            ];
                        @endphp
                        <div class="dd">
                            <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                            </button>
                            <div class="dd-menu">
                                @foreach($stageLabels as $stageNumber => $stageLabel)
                                    @php 
                                        $isEnabled = $stageAvailability[$stageNumber]; 
                                        $printUrl = '#';
                                        if ($isEnabled && $unitMeta->isNotEmpty()) {
                                            $firstUnitId = $unitMeta->first()['id'];
                                            $watermarkValue = $stageValues[$stageNumber];
                                            $printUrl = route('programmes.print_rofo', $firstUnitId) . '?watermark=' . $watermarkValue;
                                        }
                                    @endphp
                                @endforeach

                                @if($unitMeta->isNotEmpty())
                                    <button type="button" class="dd-item{{ $stWcDone ? ' disabled' : '' }}"
                                        @if($stWcDone) disabled title="White copy already run off — print the letters next" @endif
                                        onclick="openWhiteCopyModal(
                                            {{ (int) $unitMeta->first()['id'] }},
                                            @js($summary->primary_file_no),
                                            '',
                                            '{{ route('programmes.white_copy_rofo', $unitMeta->first()['id']) }}?batch_primary={{ $summary->main_application_id }}'
                                        )">
                                        <i data-lucide="file-search" class="w-4 h-4 {{ $stWcDone ? 'opacity-40' : 'text-slate-600' }}"></i>
                                        <span>Print White Copy</span>
                                    </button>

                                    <button type="button" class="dd-item{{ $stWcDone ? '' : ' disabled' }}"
                                        @unless($stWcDone) disabled title="Print and read the white copy first" @endunless
                                        onclick="WhiteCopy.openPrintManager(
                                            '{{ $summary->primary_file_no }}',
                                            'ST RoFO',
                                            '{{ route('programmes.print_rofo', $unitMeta->first()['id']) }}?batch_primary={{ $summary->main_application_id }}',
                                            @js(['whiteCopyUrl' => route('programmes.white_copy_rofo', $unitMeta->first()['id']) . '?batch_primary={{ $summary->main_application_id }}'])
                                        )">
                                        <i data-lucide="printer" class="w-4 h-4 {{ $stWcDone ? 'text-indigo-600' : 'opacity-40' }}"></i>
                                        <span>Print Manager</span>
                                    </button>
                                @endif
                                <button type="button" class="dd-item customModal" data-size="xl" data-title="Primary Units" data-url="{{ route('programmes.rofo.pua-units', $summary->main_application_id) }}">
                                    <i data-lucide="layout-grid" class="w-4 h-4 text-blue-500"></i>
                                    <span>View PuA(s)</span>
                                </button>

                                {{-- A primary's RofOs are generated for every unit in one pass,
                                     so this row has no single `rofo` row to remove — Master
                                     Delete here takes the RofOs of ALL its units, which is what
                                     the row stands for. The applications survive. Supper Admin
                                     only; the server enforces the same rule. --}}
                                @if(auth()->user()?->assign_role === 'Supper Admin')
                                <button type="button" class="dd-item"
                                    onclick="masterDeleteStRofo('primary', {{ (int) $summary->main_application_id }}, @js($summary->primary_file_no), {{ (int) ($summary->generated_units ?? 0) }})">
                                    <i data-lucide="shield-alert" class="w-4 h-4 text-red-600"></i>
                                    <span class="text-red-700 font-semibold">Master Delete</span>
                                </button>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="muted">No primaries with generated RoFOs</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
