{{--
    Per-beneficiary schedule for the selected project (SurveyCompensationSchedule).
    $for: 'trees' | 'plots' (which tab this sits in); $project, $schedule from CalculatorController.
--}}
@php
    $money  = fn ($v) => '₦' . number_format((float) $v, 2);
    $wanted = $for === 'trees' ? 'Monetary Compensation' : 'Land to Land';
    // A joint holding lists its first few people; the rest fold under "Show all" (user, 2026-11-02: a case with
    // hundreds of names filled the whole table).
    $showNames = 3;
    $tableId = 'svSchedule' . ucfirst($for);
@endphp
<style>
    .survey-proto .sv-schedule td, .sv-schedule td { vertical-align: top; }
    .sv-schedule .sv-ben { min-width: 240px; max-width: 420px; }
    .sv-schedule .sv-ben summary { cursor: pointer; color: var(--primary, #2563eb); font-size: 12px; margin-top: 4px; list-style: none; }
    .sv-schedule .sv-ben summary::-webkit-details-marker { display: none; }
    .sv-schedule .sv-ben ol { margin: 6px 0 0; padding: 8px 8px 8px 30px; max-height: 220px; overflow: auto; background: var(--gray-50, #f9fafb);
        border: 1px solid var(--gray-200, #e5e7eb); border-radius: 8px; font-size: 12.5px; line-height: 1.6; list-style: decimal; }
    .survey-proto .sv-schedule .sv-ben ol li { list-style: decimal; }
    .sv-schedule .sv-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sv-schedule .sv-pill { display: inline-block; margin-top: 3px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #eef2ff; color: #4338ca; }
</style>

@if (! $project)
    <div class="comp-type-note" style="margin-bottom:20px;">
        <i class="fas fa-hand-pointer"></i>
        Choose a project above to see its {{ $wanted }} schedule for every beneficiary.
    </div>
@elseif ($schedule['scheme'] !== $for)
    <div class="comp-type-note" style="margin-bottom:20px;">
        <i class="fas fa-info-circle"></i>
        <strong>{{ $project->project_code }}</strong> is a <strong>{{ $project->scheme_label }}</strong> project,
        so {{ $wanted }} does not apply to it. A project is compensated one way only.
    </div>
@else
    <div class="table-wrapper" style="margin-bottom:24px;">
        <div class="table-toolbar">
            <div class="left">
                <strong>{{ $project->project_code }} · {{ $project->name }}</strong>
                <span style="color:var(--gray-500);margin-left:8px;">
                    {{ number_format(count($schedule['rows'])) }} beneficiary row(s) from {{ number_format($schedule['cases']) }} case(s)
                </span>
            </div>
            <div class="right" style="gap:8px;display:flex;align-items:center;">
                @if (count($schedule['rows']) > 1)
                    <input type="search" placeholder="Find a beneficiary, phone or case…" oninput="svScheduleFilter(this, '{{ $tableId }}')"
                           style="padding:7px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-size:13px;width:240px;" />
                @endif
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="table-scroll">
            <table class="sv-schedule" id="{{ $tableId }}">
                <thead>
                    <tr>
                        <th style="width:50px;">S/N</th>
                        <th>Beneficiary</th>
                        <th style="white-space:nowrap;">Phone / NIN</th>
                        <th>Cases</th>
                        @if ($for === 'plots')
                            <th style="text-align:right;">Total Plots</th>
                            <th style="text-align:right;">Owner Plots</th>
                            <th style="text-align:right;">Government Plots</th>
                            <th style="text-align:center;">Ratio</th>
                        @else
                            <th>Bank / Account</th>
                            <th>Trees (count × rate)</th>
                            <th style="text-align:right;">Total Trees</th>
                            <th style="text-align:right;">Amount</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedule['rows'] as $row)
                        @php
                            $names = $row['names'] ?? array_filter([$row['name']]);
                            $search = mb_strtolower(implode(' ', $names) . ' ' . $row['phone'] . ' ' . $row['nin'] . ' ' . collect($row['cases'])->pluck('ref')->implode(' '));
                        @endphp
                        <tr data-sv-search="{{ $search }}" @if ($row['unassigned']) style="background:#fff7ed;" @endif>
                            <td data-sv-sn>{{ $loop->iteration }}</td>
                            <td class="sv-ben">
                                @if ($row['unassigned'])
                                    <em style="color:var(--warning-dark, #b45309);">No beneficiary recorded</em>
                                @elseif (count($names) > $showNames)
                                    <strong>{{ implode(', ', array_slice($names, 0, $showNames)) }}</strong>
                                    <span style="color:var(--gray-500);"> and {{ number_format(count($names) - $showNames) }} more</span>
                                    <div><span class="sv-pill">Joint holding · {{ number_format(count($names)) }} people</span></div>
                                    <details>
                                        <summary><i class="fas fa-chevron-down" style="font-size:10px;"></i> Show all {{ number_format(count($names)) }} names</summary>
                                        <ol>@foreach ($names as $n)<li>{{ $n }}</li>@endforeach</ol>
                                    </details>
                                @else
                                    <strong>{{ implode(' & ', $names) }}</strong>
                                    @if ($row['joint'])
                                        <div><span class="sv-pill">Joint holding · {{ count($names) }} people</span></div>
                                    @endif
                                @endif
                            </td>
                            <td style="white-space:nowrap;">
                                {{ $row['phone'] ?: '—' }}
                                @if ($row['nin'])<div style="font-size:11px;color:var(--gray-500);">NIN {{ $row['nin'] }}</div>@endif
                            </td>
                            <td>
                                @foreach ($row['cases'] as $c)
                                    <a href="{{ route('survey-module.compensation.cases.show', $c['id']) }}" style="white-space:nowrap;">{{ $c['ref'] }}</a>@if ($c['portion'])<span style="color:var(--gray-500);font-size:11px;"> (P{{ $c['portion'] }})</span>@endif{{ $loop->last ? '' : ', ' }}
                                @endforeach
                            </td>
                            @if ($for === 'plots')
                                <td class="sv-num">{{ number_format($row['plots']) }}</td>
                                <td class="sv-num"><strong>{{ number_format($row['owner']) }}</strong></td>
                                <td class="sv-num">{{ number_format($row['govt']) }}</td>
                                {{-- The project's sharing ratio (CR 30102026 item 11), not a fixed 50 : 50. --}}
                                <td style="text-align:center;white-space:nowrap;">{{ str_replace(':', ' : ', $project->share_label) }}</td>
                            @else
                                <td>
                                    {{ $row['bank'] ?: '—' }}
                                    @if ($row['account'])<div style="font-size:11px;color:var(--gray-500);">{{ $row['account'] }}</div>@endif
                                </td>
                                <td>
                                    @forelse ($row['lines'] as $l)
                                        <div style="white-space:nowrap;">{{ $l['tree'] }}: {{ number_format($l['count']) }} × {{ $money($l['rate']) }}</div>
                                    @empty
                                        <span style="color:var(--gray-500);">No trees recorded</span>
                                    @endforelse
                                </td>
                                <td class="sv-num">{{ number_format($row['trees']) }}</td>
                                <td class="sv-num"><strong>{{ $money($row['amount']) }}</strong></td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align:center;padding:24px;color:var(--gray-500);">
                                No cases registered under {{ $project->project_code }} yet.
                                <a href="{{ route('survey-module.compensation.cases.register', ['project' => $project->id]) }}">Register a case</a>.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (count($schedule['rows']))
                    <tfoot>
                        <tr style="font-weight:700;background:var(--gray-50);">
                            <td colspan="4" style="text-align:right;">Total</td>
                            @if ($for === 'plots')
                                <td style="text-align:right;">{{ number_format($schedule['totals']['plots']) }}</td>
                                <td style="text-align:right;">{{ number_format($schedule['totals']['owner']) }}</td>
                                <td style="text-align:right;">{{ number_format($schedule['totals']['govt']) }}</td>
                                <td></td>
                            @else
                                <td></td><td></td>
                                <td style="text-align:right;">{{ number_format($schedule['totals']['trees']) }}</td>
                                <td style="text-align:right;">{{ $money($schedule['totals']['amount']) }}</td>
                            @endif
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <div class="table-footer">
            <span>
                @if ($for === 'plots')
                    A beneficiary appears once; their plots across all cases are totalled and split {{ $project->share_label }} (farmer : Government), any part plot to Government.
                @else
                    Each beneficiary's trees across all cases, at the rate recorded on each case line.
                @endif
                People are matched across cases by NIN, then phone, then name. Rejected cases are left out.
            </span>
        </div>
    </div>
@endif

@once
<script>
    // Schedule filter: hides rows that do not match and renumbers the S/N of the ones shown.
    function svScheduleFilter(input, tableId) {
        var q = input.value.trim().toLowerCase(), n = 0;
        document.querySelectorAll('#' + tableId + ' tbody tr[data-sv-search]').forEach(function (tr) {
            var show = !q || tr.dataset.svSearch.indexOf(q) !== -1;
            tr.style.display = show ? '' : 'none';
            if (show) { n++; var sn = tr.querySelector('[data-sv-sn]'); if (sn) sn.textContent = n; }
        });
    }
</script>
@endonce

<h4 style="margin:4px 0 10px;font-size:15px;color:var(--gray-700);">
    <i class="fas fa-calculator"></i> Quick estimate <span style="font-weight:400;color:var(--gray-500);">— not tied to a project, nothing is saved</span>
</h4>
