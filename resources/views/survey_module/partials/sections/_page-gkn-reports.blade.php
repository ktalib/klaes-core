@php
    // GKN report (reworked 2026-10-25): application files from GKN Applications, and the survey
    // work on them (Instruction to Survey, Surveyors Report, coordinates, readings) in the period.
    use App\Http\Controllers\Survey\GknApplicationController as Apps;
    use App\Models\Survey\SurveyIts;
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
@endphp

@include('survey_module.partials._flash')

<form method="GET" class="report-filters">
    <div class="form-group">
        <label>File Type</label>
        <select name="type">
            <option value="">All</option>
            @foreach (Apps::TYPES as $k => $t)
                <option value="{{ $k }}" @selected($type === $k)>{{ $t['label'] }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label>From</label>
        <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}" />
    </div>
    <div class="form-group">
        <label>To</label>
        <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}" />
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit"><i class="fas fa-chart-bar"></i> Run Report</button>
    </div>
</form>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Application files{{ $type ? ' (' . Apps::TYPES[$type]['label'] . ')' : '' }}</div><div class="kpi-value">{{ number_format($totals['files']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">I to S issued</div><div class="kpi-value">{{ number_format($totals['its']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Surveyors Reports submitted</div><div class="kpi-value">{{ number_format($totals['reports']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Jobs completed</div><div class="kpi-value">{{ number_format($totals['completed']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Beacons · Readings</div><div class="kpi-value">{{ number_format($totals['beacons']) }} · {{ number_format($totals['readings']) }}</div></div>
</div>
<p class="helper-text" style="margin:-6px 0 16px;">File counts are all time; the survey work is for {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}.</p>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:18px;margin-bottom:20px;">
    <div class="dash-card">
        <div class="card-header"><h3>Application files by type</h3></div>
        <table>
            <thead><tr><th>S/N</th><th>Type</th><th style="text-align:right;">Files</th><th style="text-align:right;">Commissioned</th></tr></thead>
            <tbody>
                @foreach ($byType as $k => $t)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{!! $chip($t['label'], Apps::TYPES[$k]['colour']) !!}</td>
                        <td style="text-align:right;"><a href="{{ route('survey-module.gkn.applications', ['type' => $k]) }}">{{ number_format($t['files']) }}</a></td>
                        <td style="text-align:right;">{{ number_format($t['commissioned']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="dash-card">
        <div class="card-header"><h3>By land use{{ $type ? ' · ' . Apps::TYPES[$type]['label'] : '' }}</h3></div>
        <table>
            <thead><tr><th>S/N</th><th>Land Use</th><th style="text-align:right;">Files</th></tr></thead>
            <tbody>
                @forelse ($byLandUse as $row)
                    <tr><td>{{ $loop->iteration }}</td><td>{{ $row->land_use }}</td><td style="text-align:right;">{{ number_format($row->total) }}</td></tr>
                @empty
                    <tr><td colspan="3" style="text-align:center;color:var(--gray-500);">No files.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr><th>S/N</th><th>I to S No.</th><th>File No.</th><th>Title</th><th>Plot No.</th><th>District</th><th>LGA</th>
                    <th>Surveyor</th><th>Issued</th><th>Surveyors Report</th><th style="text-align:right;">Beacons</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $i)
                    @php $sr = $reports->get($i->id); @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('survey-module.gkn.its.edit', $i) }}"><strong>{{ $i->its_number }}</strong></a></td>
                        <td style="font-family:monospace;">{{ $i->file_number }}</td>
                        <td>{{ $i->file_title ?: '—' }}</td>
                        <td>{{ $i->plot_no ?: '—' }}</td>
                        <td>{{ $i->district ?: '—' }}</td>
                        <td>{{ $i->lga ?: '—' }}</td>
                        <td>{{ $i->surveyor_name ?: '—' }}</td>
                        <td>{{ optional($i->its_issued_at)->format('d M Y') ?: '—' }}</td>
                        <td>{{ $sr ? $sr->report_ref . ' (' . $sr->status . ')' : '—' }}</td>
                        <td style="text-align:right;">{{ number_format((int) ($beacons[$i->id] ?? 0)) }}</td>
                        <td>{!! $chip($i->status, SurveyIts::STATUS_COLOURS[$i->status] ?? 'gray') !!}</td>
                    </tr>
                @empty
                    <tr><td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                        No instructions issued in this period{{ $type ? ' for ' . Apps::TYPES[$type]['label'] . ' files' : '' }}.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>{{ number_format($rows->total()) }} instruction(s) &middot; {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>
