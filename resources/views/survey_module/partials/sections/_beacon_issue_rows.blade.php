{{-- By issue rows (user, 2026-11-06). With $group: one letter group's row, which is its first entry with every column
     filled, and a click opens the rest of the group under it. Without: those other entries, fetched by groupRows and
     numbered under the group (2.1, 2.2). --}}
@php
    $group   = $group ?? null;
    $groupNo = $groupNo ?? 0;
    $rowStart = $rowStart ?? ($entries instanceof \Illuminate\Pagination\AbstractPaginator ? $entries->firstItem() : 1);
    $keep   = request()->only(['q', 'layout', 'year', 'quarter', 'month', 'status']);
    $brCan  = collect(['create', 'edit', 'approve', 'delete'])
        ->mapWithKeys(fn ($a) => [$a => (bool) optional(auth()->user())->canDo('Survey - Beacon Register', $a)])->all();
    $brItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $userName = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : null;
@endphp
@foreach ($entries as $row)
                    @php
                        $rowSn = $group ? $groupNo : ($groupNo ? $groupNo . '.' : '') . ($rowStart + $loop->index);
                        $opens = $group && (int) $group->entries > 1;
                    @endphp
                    <tr @if ($opens) class="br-group" data-series="{{ $group->letters }}" data-sn="{{ $groupNo }}" tabindex="0" aria-expanded="false" onclick="brGroupToggle(this, event)" onkeydown="if (event.target === this && (event.key === 'Enter' || event.key === ' ')) { event.preventDefault(); brGroupToggle(this); }" @endif>
                        <td style="white-space:nowrap;">@if ($opens)<i class="fas fa-chevron-right br-group-icon"></i>@endif{{ $rowSn }}</td>
                        <td style="white-space:nowrap;">{{ $row->layout_type_label ?: '—' }}</td>
                        <td style="white-space:nowrap;">@forelse ($row->fileNumberList() as $fno)<div>{{ $fno }}</div>@empty — @endforelse</td>
                        <td>{{ $row->register_no ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            <strong>{{ $row->issued_range }}</strong>
                            @if ($group)
                                <span class="br-group-sub">{{ $group->letters !== '' ? $group->letters : 'No letters' }} · {{ number_format($group->issued) }} {{ (int) $group->issued === 1 ? 'number' : 'numbers' }} · {{ $group->lowest === $group->highest ? $group->lowest : $group->lowest . ' to ' . $group->highest }}</span>
                            @endif
                            @if ($row->numbers->count() > 1)
                                <button type="button" class="br-expand" onclick="brToggleNumbers({{ $row->id }}, this)" aria-expanded="false">
                                    <i class="fas fa-chevron-down"></i> {{ $row->numbers->count() }} numbers
                                </button>
                            @endif
                        </td>
                        <td>{{ number_format($row->total_issued) }}</td>
                        <td>{{ $row->plan_no ?: '—' }}</td>
                        <td>{{ $row->company_name ?: '—' }}</td><td>{{ $row->surveyor_name ?: '—' }}</td>
                        <td>{{ $row->surveyor_registration ?: '—' }}</td>
                        <td>{{ $row->prop_plot ?: '—' }}</td>
                        <td>{{ $row->resolvedDistrict('prop_') ?: '—' }}</td>
                        <td>{{ mb_strtoupper((string) \App\Support\AddressBuilder::resolve($row->prop_lga)) ?: '—' }}</td>
                        <td>{{ $row->resolvedStreet('prop_') ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->coord_x !== null ? rtrim(rtrim(number_format((float) $row->coord_x, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->coord_y !== null ? rtrim(rtrim(number_format((float) $row->coord_y, 4, '.', ''), '0'), '.') : '—' }}</td>
                        <td>
                            {{ $row->teller_no ?: '—' }}
                        </td>
                        <td style="text-align:right;">{{ $row->amount !== null ? number_format((float) $row->amount, 2) : '—' }}</td>
                        <td style="white-space:nowrap;">{{ optional($row->date_received)->format('d/m/Y') ?: '—' }}</td>
                        {{-- Who entered it, in its own column (user, 2026-11-03), no longer under the status. --}}
                        <td style="white-space:nowrap;">{{ $userName($row->creator) ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->issue_date->format('d/m/Y') }}</td>
                        <td style="white-space:nowrap;">
                            @if ($row->audited_at)
                                <span class="status-badge completed" title="Audited by {{ $userName($row->auditor) }} on {{ $row->audited_at->format('d/m/Y H:i') }}"><span class="dot"></span>Audited</span>
                            @elseif ($row->reviewed_at)
                                <span class="status-badge review" title="Reviewed by {{ $userName($row->reviewer) }} on {{ $row->reviewed_at->format('d/m/Y H:i') }}"><span class="dot"></span>Reviewed</span>
                            @else
                                <span class="status-badge pending"><span class="dot"></span>Entered</span>
                            @endif
                        </td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if (! $row->audited_at && $brCan['edit'])
                                        <a href="{{ route('survey-module.workflow.beacon-register.index', $keep + ['edit' => $row->id]) }}" class="{{ $brItem }}">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </a>
                                    @endif
                                    @if (! $row->reviewed_at && $brCan['approve'])
                                        <form method="POST" action="{{ route('survey-module.workflow.beacon-register.review', $row) }}">
                                            @csrf
                                            <button type="submit" class="{{ $brItem }}"><i data-lucide="check" class="w-4 h-4 text-gray-500"></i> Mark reviewed</button>
                                        </form>
                                    @elseif ($row->reviewed_at && ! $row->audited_at && $brCan['approve'])
                                        <form method="POST" action="{{ route('survey-module.workflow.beacon-register.audit', $row) }}">
                                            @csrf
                                            <button type="submit" class="{{ $brItem }}"><i data-lucide="check-check" class="w-4 h-4 text-gray-500"></i> Mark audited</button>
                                        </form>
                                    @endif
                                    @if ($row->audited_at)
                                        <div class="px-3 py-2 text-xs text-gray-500">Audited entries are locked.</div>
                                    @elseif ($brCan['delete'])
                                        <form method="POST" action="{{ route('survey-module.workflow.beacon-register.destroy', $row) }}"
                                              onsubmit="return confirm('Delete the entry {{ $row->issued_range }} for {{ addslashes($row->surveyor_name) }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $brItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Delete</button>
                                        </form>
                                    @endif
                                    @if (! $row->audited_at && ! $brCan['edit'] && ! $brCan['approve'] && ! $brCan['delete'])
                                        <div class="px-3 py-2 text-xs text-gray-500">No actions available to you.</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                    @if ($row->numbers->count() > 1)
                        {{-- One row per beacon record, in the issue row's columns. The amount
                             covers the whole line, so it is not repeated on each beacon. --}}
                        @php
                            $rowDistrict = $row->resolvedDistrict('prop_') ?: '—';
                            $rowLga      = mb_strtoupper((string) \App\Support\AddressBuilder::resolve($row->prop_lga)) ?: '—';
                            $rowStreet   = $row->resolvedStreet('prop_') ?: '—';
                        @endphp
                        @foreach ($row->numbers as $n)
                            <tr class="br-num-row" data-parent="{{ $row->id }}" hidden>
                                <td class="br-num-sn">{{ $rowSn }}.{{ $loop->iteration }}</td>
                                <td style="white-space:nowrap;">{{ $row->layout_type_label ?: '—' }}</td>
                                <td style="white-space:nowrap;">@forelse ($row->fileNumberList() as $fno)<div>{{ $fno }}</div>@empty — @endforelse</td>
                                <td>{{ $row->register_no ?: '—' }}</td>
                                <td style="white-space:nowrap;"><span class="br-num-no">{{ \App\Models\Survey\SurveyBeaconRegister::display($n->beacon_no) }}</span></td>
                                <td>1</td>
                                <td>{{ $row->plan_no ?: '—' }}</td>
                                <td>{{ $row->company_name ?: '—' }}</td><td>{{ $row->surveyor_name ?: '—' }}</td>
                                <td>{{ $row->surveyor_registration ?: '—' }}</td>
                                <td>{{ $row->prop_plot ?: '—' }}</td>
                                <td>{{ $rowDistrict }}</td>
                                <td>{{ $rowLga }}</td>
                                <td>{{ $rowStreet }}</td>
                                <td style="white-space:nowrap;">{{ $row->coord_x !== null ? rtrim(rtrim(number_format((float) $row->coord_x, 4, '.', ''), '0'), '.') : '—' }}</td>
                                <td style="white-space:nowrap;">{{ $row->coord_y !== null ? rtrim(rtrim(number_format((float) $row->coord_y, 4, '.', ''), '0'), '.') : '—' }}</td>
                                <td>{{ $row->teller_no ?: '—' }}</td>
                                <td style="text-align:right;color:var(--gray-400);">—</td>
                                <td style="white-space:nowrap;">{{ optional($row->date_received)->format('d/m/Y') ?: '—' }}</td>
                                <td style="white-space:nowrap;">{{ $userName($row->creator) ?: '—' }}</td>
                                <td style="white-space:nowrap;">{{ $row->issue_date->format('d/m/Y') }}</td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
@endforeach
