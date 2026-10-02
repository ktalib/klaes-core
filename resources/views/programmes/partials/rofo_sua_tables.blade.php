<div id="wrap-sua-not" class="table-container">
    <table id="table-sua-not" class="simple">
        <thead>
            <tr>
                <th>ST FileNo</th>
                <th>Scheme No</th>
                <th>Unit Owner</th>
                <th>Land Use</th>
                <th>LGA</th>
                <th>Unit Type</th>
                <th>Unit/Section/Block</th>
                <th>ST Memo Status</th>
                <th>RoFO Details</th>
                <th>Created By</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($applications ?? collect())->filter(function($app) {
                return !($app->rofo_generated ?? false);
            }) as $unitApplication)
            <tr>
                <td>{{ $unitApplication->fileno ?? 'N/A' }}</td>
                <td>{{ $unitApplication->scheme_no ?? 'N/A' }}</td>
                <td>{{ $unitApplication->owner_name ?? 'N/A' }}</td>
                <td>{{ $unitApplication->land_use ?? 'N/A' }}</td>
                <td>{{ $unitApplication->property_lga ?? 'N/A' }}</td>
                <td>{{ $unitApplication->unit_type_label ?? 'SUA' }}</td>
                <td>{{ $unitApplication->unit_number ?? '' }}-{{ $unitApplication->floor_number ?? '' }}-{{ $unitApplication->block_number ?? '' }}</td>
                <td>
                    @if($unitApplication->has_st_memo ?? false)
                        <span>Generated</span>
                    @else
                        <span class="muted">Not Generated</span>
                    @endif
                </td>
                <td>
                    @if($unitApplication->details_captured ?? false)
                        <span>Captured</span>
                    @else
                        <span class="muted">Pending</span>
                    @endif
                </td>
                <td>{{ $unitApplication->created_by_name ?? '—' }}</td>
                <td>{{ $unitApplication->created_at ? date('d-m-Y', strtotime($unitApplication->created_at)) : 'N/A' }}</td>
                <td class="actions">
                    <div class="dd">
                            <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                            </button>
                        <div class="dd-menu">
                            <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}" class="dd-item">
                                <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                                <span>View Application</span>
                            </a>
                            @if($unitApplication->has_st_memo ?? false)
                                <a href="{{ route('programmes.generate_rofo', $unitApplication->id) }}" class="dd-item">
                                    <i data-lucide="plus-circle" class="w-4 h-4 text-emerald-500"></i>
                                    <span>Generate RoFO</span>
                                </a>
                            @else
                                <span class="dd-item disabled">
                                    <i data-lucide="plus-circle" class="w-4 h-4 opacity-40"></i>
                                    <span>Generate RoFO</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="12" class="muted">No SUA records pending RoFO generation</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="wrap-sua-gen" class="table-container" style="display:none;">
    <table id="table-sua-gen" class="simple">
        <thead>
            <tr>
                <th>ST FileNo</th>
                <th>RoFO No</th>
                <th>Scheme No</th>
                <th>Unit Owner</th>
                <th>Land Use</th>
                <th>LGA</th>
                <th>Unit Type</th>
                <th>Unit/Section/Block</th>
                <th>RoFO Details</th>
                <th>Created By</th>
                <th>Date Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($applications ?? collect())->filter(function($app) {
                return (bool) ($app->rofo_generated ?? false);
            }) as $unitApplication)
            <tr>
                <td>{{ $unitApplication->fileno ?? 'N/A' }}</td>
                <td><strong>{{ $unitApplication->rofo_no ?? 'N/A' }}</strong></td>
                <td>{{ $unitApplication->scheme_no ?? 'N/A' }}</td>
                <td>{{ $unitApplication->owner_name ?? 'N/A' }}</td>
                <td>{{ $unitApplication->land_use ?? 'N/A' }}</td>
                <td>{{ $unitApplication->property_lga ?? 'N/A' }}</td>
                <td>{{ $unitApplication->unit_type_label ?? 'SUA' }}</td>
                <td>{{ $unitApplication->unit_number ?? '' }}-{{ $unitApplication->floor_number ?? '' }}-{{ $unitApplication->block_number ?? '' }}</td>
                <td>
                    @if($unitApplication->details_captured ?? false)
                        <span>Captured</span>
                    @else
                        <span class="muted">Pending</span>
                    @endif
                </td>
                <td>{{ $unitApplication->created_by_name ?? '—' }}</td>
                <td>{{ $unitApplication->created_at ? date('d-m-Y', strtotime($unitApplication->created_at)) : 'N/A' }}</td>
                <td class="actions">
                    <div class="dd">
                            <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                            </button>
                        <div class="dd-menu">
                            <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $unitApplication->id) }}" class="dd-item">
                                <i data-lucide="eye" class="w-4 h-4 text-slate-400"></i>
                                <span>View Application</span>
                            </a>
                            @php
                                $printCounter = (int) ($unitApplication->print_counter ?? 0);
                                $viewRofoUrl = route('programmes.view_rofo', $unitApplication->id);

                                // The proof has been run off for this unit, so the two
                                // entries hand off to each other: the White Copy closes,
                                // the Print Manager opens.
                                $stWcDone = isset($whiteCopyDone[strtoupper(trim((string) $unitApplication->fileno))]);
                            @endphp

                            {{-- Proof first, then the run — the order the work happens in
                                 and the order every other module lists them in. ST prints
                                 no DATE OF ISSUE, so the card has no date to take. --}}
                            @if($stWcDone)
                                <span class="dd-item disabled" title="White copy already run off — print the letter next">
                                    <i data-lucide="file-search" class="w-4 h-4 opacity-40"></i>
                                    <span>Print White Copy</span>
                                </span>
                            @else
                            <button type="button" class="dd-item"
                                onclick="openWhiteCopyModal(
                                    {{ (int) $unitApplication->id }},
                                    @js($unitApplication->fileno),
                                    '',
                                    '{{ route('programmes.white_copy_rofo', $unitApplication->id) }}'
                                )">
                                <i data-lucide="file-search" class="w-4 h-4 text-slate-600"></i>
                                <span>Print White Copy</span>
                            </button>
                            @endif

                            {{-- Opens only once the proof has been run. Nothing else on
                                 this row says whether the letter was read, and that is
                                 the whole reason the proof exists. --}}
                            @if($stWcDone)
                            <button type="button" class="dd-item"
                                onclick="WhiteCopy.openPrintManager(
                                    '{{ $unitApplication->fileno }}',
                                    'ST RoFO',
                                    '{{ route('programmes.print_rofo', $unitApplication->id) }}',
                                    @js(['whiteCopyUrl' => route('programmes.white_copy_rofo', $unitApplication->id)])
                                )">
                                <i data-lucide="printer" class="w-4 h-4 text-indigo-600"></i>
                                <span>Print Manager</span>
                            </button>
                            @else
                                <span class="dd-item disabled" title="Print and read the white copy first">
                                    <i data-lucide="printer" class="w-4 h-4 opacity-40"></i>
                                    <span>Print Manager</span>
                                </span>
                            @endif
                            @if($unitApplication->security_paper_code)
                                <span class="dd-item disabled" title="Security code already assigned">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Code Assigned: {{ $unitApplication->security_paper_code }}</span>
                                </span>
                                <button type="button" class="dd-item"
                                    onclick="resetSecurityPaperModal('{{ $unitApplication->id }}', @js($unitApplication->fileno), @js($unitApplication->security_paper_code))">
                                    <i data-lucide="rotate-ccw" class="w-4 h-4 text-red-500"></i>
                                    <span class="text-red-600">Reset Security Paper Code</span>
                                </button>
                            @elseif($printCounter < 1)
                                <span class="dd-item disabled" title="Print the RoFO before entering a security code">
                                    <i data-lucide="shield" class="w-4 h-4 text-slate-400"></i>
                                    <span>Enter Security Code (print first)</span>
                                </span>
                            @else
                                <button type="button" class="dd-item"
                                    onclick="openSecurityPaperModal('{{ $unitApplication->id }}', '{{ $unitApplication->fileno }}', '{{ $unitApplication->security_paper_code }}')">
                                    <i data-lucide="shield" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Enter Security Code</span>
                                </button>
                            @endif

                            {{-- Master Delete removes the `rofo` row itself, with its PRA
                                 transaction, security paper and print log. The unit
                                 application, its memo and its file number survive — erasing
                                 an ST FILE is a different screen. Supper Admin only; the
                                 server enforces the same rule. --}}
                            @if(auth()->user()?->assign_role === 'Supper Admin')
                                <button type="button" class="dd-item"
                                    onclick="masterDeleteStRofo('unit', {{ (int) $unitApplication->id }}, @js($unitApplication->fileno))">
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
                <td colspan="12" class="muted">No generated SUA RoFO applications found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
