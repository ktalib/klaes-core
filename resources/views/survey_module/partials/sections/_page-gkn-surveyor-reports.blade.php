@php
    use App\Models\Survey\SurveySurveyorReport as SR;

    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
    $filters = ['q', 'status', 'source'];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a href="{{ route('survey-module.gkn.reports.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Create SR</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Reports</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Draft</div><div class="kpi-value">{{ number_format($stats['draft']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Submitted</div><div class="kpi-value">{{ number_format($stats['submitted']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">I to S awaiting a report</div><div class="kpi-value">{{ number_format($awaiting) }}</div></div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search SR, file, I to S, grantee, surveyor…" style="{{ $inputStyle }}width:280px;" />
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach ([SR::DRAFT, SR::SUBMITTED] as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="source" style="{{ $inputStyle }}">
            <option value="">All I to S</option>
            <option value="survey" @selected(request('source') === 'survey')>Survey I to S</option>
            <option value="cadastral" @selected(request('source') === 'cadastral')>Cadastral I to S</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.reports.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>SR No.</th>
                    <th>I to S</th>
                    <th>File No.</th>
                    <th>Grantee</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Surveyor</th>
                    <th>Survey Dates</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reports as $r)
                    <tr>
                        <td>{{ $reports->firstItem() + $loop->index }}</td>
                        <td><strong style="font-family:monospace;">{{ $r->report_ref }}</strong></td>
                        <td style="font-family:monospace;">{{ $r->its_number }}<div style="font-size:11px;color:var(--gray-500);font-family:inherit;">{{ $r->its_source === 'cadastral' ? 'Cadastral' : 'Survey' }}</div></td>
                        <td style="font-family:monospace;">{{ $r->file_number }}</td>
                        <td>{{ $r->grantee_name ?: '—' }}</td>
                        <td>{{ $r->plot_no ?: '—' }}</td>
                        <td>{{ $r->district ?: '—' }}</td>
                        <td>{{ $r->lga ?: '—' }}</td>
                        <td>{{ $r->surveyor_name ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ optional($r->commenced_on)->format('d M Y') ?: '—' }} – {{ optional($r->completed_on)->format('d M Y') ?: '—' }}</td>
                        <td>{!! $chip($r->status, $r->isSubmitted() ? 'green' : 'gray') !!}</td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const rr = $refs.btn.getBoundingClientRect(); top = rr.bottom + 4; left = Math.max(8, rr.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.gkn.reports.edit', $r) }}" class="{{ $svItem }}">
                                        <i data-lucide="{{ $r->isSubmitted() ? 'eye' : 'pencil' }}" class="w-4 h-4 text-gray-500"></i> {{ $r->isSubmitted() ? 'View' : 'Edit / Submit' }}
                                    </a>
                                    <a href="{{ route('survey-module.gkn.reports.print', $r) }}" target="_blank" class="{{ $svItem }}">
                                        <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-file-alt" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny($filters))
                                No reports match these filters.
                            @else
                                No Surveyors Reports yet. <a href="{{ route('survey-module.gkn.reports.create') }}">Create one</a> against an issued I to S.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $reports->firstItem() ?? 0 }}–{{ $reports->lastItem() ?? 0 }} of {{ number_format($reports->total()) }} reports</span>
        <div class="pagination">{{ $reports->links() }}</div>
    </div>
</div>
