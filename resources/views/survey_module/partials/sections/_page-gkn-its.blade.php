@php
    use App\Models\Survey\SurveyIts;
    use App\Http\Controllers\Survey\GknApplicationController as Apps;

    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $chip = fn ($label, $c) => '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-' . $c . '-100 text-' . $c . '-800">' . e($label) . '</span>';
    $cadPrint = \Illuminate\Support\Facades\Route::has('cadastral-module.survey-jobs.its.print');
    $filters = ['q', 'source', 'status'];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a href="{{ route('survey-module.gkn.its.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Create I to S</a>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Survey jobs</div><div class="kpi-value">{{ number_format($stats['survey']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Draft (not issued)</div><div class="kpi-value">{{ number_format($stats['draft']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Issued</div><div class="kpi-value">{{ number_format($stats['issued']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">From Cadastral</div><div class="kpi-value">{{ number_format($stats['cadastral']) }}</div></div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search file, job, I to S no., surveyor…" style="{{ $inputStyle }}width:270px;" />
        <select name="source" style="{{ $inputStyle }}">
            <option value="">All Sources</option>
            <option value="survey" @selected(request('source') === 'survey')>Survey</option>
            <option value="cadastral" @selected(request('source') === 'cadastral')>Cadastral</option>
        </select>
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (array_unique(array_merge(SurveyIts::STATUSES, ['Submitted', 'Accepted'])) as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.its.index') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper" x-data="{ cx: { open: false, url: '', ref: '' } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Source</th>
                    <th>I to S No.</th>
                    <th>Job No.</th>
                    <th>File No.</th>
                    <th>Title</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Surveyor</th>
                    <th>Issued</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $m = $row['model'];
                        $isSurvey = $row['source'] === 'survey';
                        $type = Apps::TYPES[Apps::typeOf($row['file_number'])] ?? null;
                    @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{!! $isSurvey ? $chip('Survey', 'pink') : $chip('Cadastral', 'indigo') !!}</td>
                        <td><strong style="font-family:monospace;">{{ $row['its_number'] ?: '—' }}</strong></td>
                        <td style="font-family:monospace;">{{ $row['job'] }}</td>
                        <td>
                            <span style="font-family:monospace;">{{ $row['file_number'] ?: '—' }}</span>
                            @if ($type) {!! $chip($type['label'], $type['colour']) !!} @endif
                        </td>
                        <td>{{ $row['file_title'] ?: '—' }}</td>
                        <td>{{ $row['plot'] ?: '—' }}</td>
                        <td>{{ $row['district'] ?: '—' }}</td>
                        <td>{{ $row['lga'] ?: '—' }}</td>
                        <td>{{ $row['surveyor'] ?: '—' }}</td>
                        <td>{{ $row['issued'] ? \Illuminate\Support\Carbon::parse($row['issued'])->format('d M Y') : '—' }}</td>
                        <td>{!! $chip($row['status'], SurveyIts::STATUS_COLOURS[$row['status']] ?? 'gray') !!}</td>
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
                                    @if ($isSurvey)
                                        <a href="{{ route('survey-module.gkn.its.edit', $m) }}" class="{{ $svItem }}">
                                            <i data-lucide="{{ $m->isIssued() ? 'eye' : 'pencil' }}" class="w-4 h-4 text-gray-500"></i>
                                            {{ $m->isIssued() ? 'View' : 'Edit / Issue' }}
                                        </a>
                                        @if ($m->isIssued())
                                            <a href="{{ route('survey-module.gkn.its.print', $m) }}" target="_blank" class="{{ $svItem }}">
                                                <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print I to S
                                            </a>
                                        @endif
                                        @if ($m->isIssued() && $m->status !== SurveyIts::CANCELLED)
                                            <a href="{{ route('survey-module.gkn.reports.create', ['its' => 'survey-' . $m->id]) }}" class="{{ $svItem }}">
                                                <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> Surveyors Report
                                            </a>
                                        @endif
                                        @if (in_array($m->status, [SurveyIts::ISSUED, SurveyIts::REPORTED], true))
                                            <form method="POST" action="{{ route('survey-module.gkn.its.complete', $m) }}" onsubmit="return confirm('Mark {{ $m->its_number }} completed?');">
                                                @csrf
                                                <button type="submit" class="{{ $svItem }}"><i data-lucide="check-circle" class="w-4 h-4 text-gray-500"></i> Mark Completed</button>
                                            </form>
                                        @endif
                                        @unless ($m->isClosed())
                                            <button type="button" class="{{ $svItem }} text-red-600"
                                                    @click="open = false; cx = { open: true, url: @js(route('survey-module.gkn.its.cancel', $m)), ref: @js($m->its_number ?: $m->job_ref) }">
                                                <i data-lucide="x-circle" class="w-4 h-4"></i> Cancel
                                            </button>
                                        @endunless
                                    @else
                                        @if ($cadPrint)
                                            <a href="{{ route('cadastral-module.survey-jobs.its.print', $row['id']) }}" target="_blank" class="{{ $svItem }}">
                                                <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Print I to S
                                            </a>
                                        @endif
                                        <a href="{{ route('survey-module.gkn.reports.create', ['its' => 'cadastral-' . $row['id']]) }}" class="{{ $svItem }}">
                                            <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> Surveyors Report
                                        </a>
                                        <div class="px-3 py-2 text-xs text-gray-500">Issued by Cadastral · read-only here</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-clipboard-list" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny($filters))
                                No instructions match these filters.
                            @else
                                No instructions yet. <a href="{{ route('survey-module.gkn.its.create') }}">Create the first one</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} instructions</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>

    <div x-show="cx.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="cx.open = false">
        <form method="POST" :action="cx.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(440px,92vw);" @click.outside="cx.open = false">
            @csrf
            <h3 style="margin:0 0 8px;font-size:16px;font-weight:700;">Cancel <span x-text="cx.ref"></span></h3>
            <label style="display:block;font-size:13px;margin-bottom:4px;">Reason <span class="required">*</span></label>
            <textarea name="reason" rows="3" maxlength="500" required style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;"></textarea>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="cx.open = false">Back</button>
                <button type="submit" class="btn btn-danger btn-sm">Cancel Job</button>
            </div>
        </form>
    </div>
</div>
