{{-- One land on Farm Assessment's list. $rowSn is its S/N; $rowOf the TP No. group it folds under on the Imported tab. --}}
    {{-- Imported: a block, which opens to its farms (user, 2026-11-09). --}}
    <tr @if ($rowOf ?? null) data-tp-of="{{ $rowOf }}" class="imp-land" data-land="{{ $c->id }}" data-sn="{{ $rowSn }}" aria-expanded="false" tabindex="0" hidden @endif>
        <td class="muted nowrap">@if ($rowOf ?? null)<i class="fas fa-chevron-right" data-chevron></i> @endif{{ $rowSn }}</td>
        {{-- No case number column (user, 2026-10-30): the scheme has its own column instead. --}}
        {{-- Project code dropped; the TP No. has its own column (user, 2026-10-30). --}}
        <td class="wide strong">{{ $c->project?->display_name ?? '—' }}</td>
        <td class="nowrap">{!! $c->project?->plan_no ? '<span class="tp">' . e($c->project->plan_no) . '</span>' : '<span class="muted">—</span>' !!}</td>
        <td>
            @if ($c->isMonetary())
                <span class="status-badge active"><span class="dot"></span>Monetary Compensation</span>
            @else
                <span class="status-badge completed"><span class="dot"></span>Land to Land</span>
            @endif
        </td>
        <td class="mid">@if ($c->allocation)
    {{ $c->allocation->surveyor_name }}
    <div style="font-size:11px;color:var(--gray-500);">Portion {{ $c->portion_no }} of {{ $c->allocation->portions }} · {{ rtrim(rtrim(number_format((float) $c->portion_size_ha, 4), '0'), '.') }} Ha</div>
@else
    <span style="color:var(--gray-500);">—</span>
@endif</td>
        <td class="mid">{{ $c->property_location ?: '—' }}</td>
        @if ($rowOf ?? null)
    {{-- The sheet's columns, Imported tab only (user, 2026-11-06): a land has none of them. --}}
    @for ($i = 0; $i < 6; $i++)<td class="muted">—</td>@endfor
@endif
<td class="num">@if ($c->area_ha !== null){{ number_format((float) $c->area_ha, 4) }}@else<span class="muted">—</span>@endif</td>
        @if ($rowOf ?? null)
    {{-- The block's list totals (user, 2026-11-09: no blanks). --}}
    @php $cdSum = ($lineSums ?? collect())[$c->id] ?? null; @endphp
    <td class="num">{!! $cdSum && $cdSum->creatable !== null ? number_format((float) $cdSum->creatable, 2) : '<span class="muted">—</span>' !!}</td>
    <td class="num">{!! $cdSum && $cdSum->govt !== null ? number_format((float) $cdSum->govt, 2) : '<span class="muted">—</span>' !!}</td>
    <td class="num">{!! $cdSum && $cdSum->farm !== null ? number_format((float) $cdSum->farm, 2) : '<span class="muted">—</span>' !!}</td>
    <td class="nowrap">{!! $cdSum && $cdSum->plots ? number_format($cdSum->plots) . ' plots' : '<span class="muted">—</span>' !!}</td>
    <td class="muted">—</td>
@endif
<td class="ctr">{{ number_format($c->beneficiaries_count) }}</td>
        <td>
            <span class="status-badge {{ $badgeClass($c->status) }}">
                <span class="dot"></span>{{ $statusLabel($c->status) }}
            </span>
        </td>
        @include('survey_module.partials._created_cells', ['row' => $c])
        <td class="col-actions">
            @php $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50'; @endphp
            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                     :style="'top:' + top + 'px; left:' + left + 'px'"
                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                    <a href="{{ route('survey-module.compensation.cases.show', [$c] + (request('view') === 'showing' ? ['view' => 'showing'] : [])) }}" class="{{ $svItem }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View</a>
                    <a href="{{ route('survey-module.compensation.cases.edit', $c) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                    @unless ($c->isExistingRecord())
                    <a href="{{ route('survey-module.compensation.op', ['case' => $c->id]) }}" class="{{ $svItem }}"><i data-lucide="file-signature" class="w-4 h-4 text-gray-500"></i> Generate OP</a>
                    @endunless
                    <form method="POST" action="{{ route('survey-module.compensation.cases.destroy', $c) }}"
                          onsubmit="return confirm('Delete {{ $c->case_ref }}? This cannot be undone.');">
                        @csrf @method('DELETE')
                        <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                    </form>
                </div>
            </div>
        </td>
    </tr>
