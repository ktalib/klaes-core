{{--
    Per-beneficiary schedule for the selected project (SurveyCompensationSchedule).
    $for: 'trees' | 'plots' (which tab this sits in); $project, $schedule from CalculatorController.
--}}
@php
    $money  = fn ($v) => '₦' . number_format((float) $v, 2);
    $wanted = $for === 'trees' ? 'Cash for Trees' : 'Plot for Plot';
@endphp

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
            <div class="right">
                <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>S/N</th>
                        <th>Beneficiary</th>
                        <th>Phone / NIN</th>
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
                        <tr @if ($row['unassigned']) style="background:#fff7ed;" @endif>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if ($row['unassigned'])
                                    <em style="color:var(--warning-dark, #b45309);">No beneficiary recorded</em>
                                @else
                                    <strong>{{ $row['name'] }}</strong>
                                    @if ($row['joint'])
                                        <div style="font-size:11px;color:var(--gray-500);">Joint holding</div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                {{ $row['phone'] ?: '—' }}
                                @if ($row['nin'])<div style="font-size:11px;color:var(--gray-500);">NIN {{ $row['nin'] }}</div>@endif
                            </td>
                            <td>
                                @foreach ($row['cases'] as $c)
                                    <a href="{{ route('survey-module.compensation.cases.show', $c['id']) }}" style="white-space:nowrap;">{{ $c['ref'] }}</a>@if ($c['portion'])<span style="color:var(--gray-500);font-size:11px;"> (P{{ $c['portion'] }})</span>@endif{{ $loop->last ? '' : ', ' }}
                                @endforeach
                            </td>
                            @if ($for === 'plots')
                                <td style="text-align:right;">{{ number_format($row['plots']) }}</td>
                                <td style="text-align:right;"><strong>{{ number_format($row['owner']) }}</strong></td>
                                <td style="text-align:right;">{{ number_format($row['govt']) }}</td>
                                <td style="text-align:center;">50 : 50</td>
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
                                <td style="text-align:right;">{{ number_format($row['trees']) }}</td>
                                <td style="text-align:right;"><strong>{{ $money($row['amount']) }}</strong></td>
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
                    A beneficiary appears once; their plots across all cases are totalled and split 50:50, the odd plot to Government.
                @else
                    Each beneficiary's trees across all cases, at the rate recorded on each case line.
                @endif
                People are matched across cases by NIN, then phone, then name. Rejected cases are left out.
            </span>
        </div>
    </div>
@endif

<h4 style="margin:4px 0 10px;font-size:15px;color:var(--gray-700);">
    <i class="fas fa-calculator"></i> Quick estimate <span style="font-weight:400;color:var(--gray-500);">— not tied to a project, nothing is saved</span>
</h4>
