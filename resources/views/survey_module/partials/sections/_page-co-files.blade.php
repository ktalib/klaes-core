@php
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $statusClass = fn ($s) => match ($s) {
        'Active', 'Commissioned' => 'active',
        'Closed' => 'rejected',
        default => 'pending',
    };
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('survey-module.compensation.co-files.create', ['mode' => 'captured']) }}" class="btn btn-secondary">
            <i class="fas fa-keyboard"></i> Capture Existing CO File
        </a>
        <a href="{{ route('survey-module.compensation.co-files.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Commission CO File
        </a>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total CO Files</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Commissioned / Active</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Captured (existing)</div>
        <div class="kpi-value">{{ number_format($stats['captured']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Closed</div>
        <div class="kpi-value">{{ number_format($stats['closed']) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search CO file, title or project…" style="{{ $inputStyle }}width:260px;" />
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (\App\Models\Survey\SurveyCoFile::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="source" style="{{ $inputStyle }}">
            <option value="">All Sources</option>
            <option value="commissioned" @selected(request('source') === 'commissioned')>Commissioned</option>
            <option value="captured" @selected(request('source') === 'captured')>Captured</option>
        </select>
        <input type="date" name="from" value="{{ request('from') }}" title="Commissioned from" style="{{ $inputStyle }}" />
        <input type="date" name="to" value="{{ request('to') }}" title="Commissioned to" style="{{ $inputStyle }}" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'source', 'from', 'to']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.co-files') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>CO File No</th>
                    <th>Title</th>
                    <th>Location</th>
                    <th>Commission Date</th>
                    <th>Projects</th>
                    <th>Surveyor(s)</th>
                    <th>Total Portions</th>
                    <th>Size of Acquisition (Ha)</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coFiles as $f)
                    @php
                        $allocs    = $f->projects->flatMap->surveyors;
                        $surveyors = $allocs->pluck('surveyor_name')->unique()->values();
                    @endphp
                    <tr>
                        <td>{{ $coFiles->firstItem() + $loop->index }}</td>
                        <td>
                            <strong>{{ $f->co_file_no }}</strong>
                            @if ($f->source === 'captured')
                                <div style="font-size:11px;color:var(--gray-500);">Captured</div>
                            @endif
                        </td>
                        <td>{{ $f->title }}</td>
                        <td>{{ $f->property_location ?: '—' }}</td>
                        <td>{{ optional($f->commission_date)->format('d M Y') ?: '—' }}</td>
                        <td>
                            @forelse ($f->projects as $p)
                                <div style="white-space:nowrap;">{{ $p->project_code }} · {{ \Illuminate\Support\Str::limit($p->name, 28) }}</div>
                            @empty
                                <span style="color:var(--gray-500);">None yet</span>
                            @endforelse
                        </td>
                        <td>
                            @if ($surveyors->isEmpty())
                                <span style="color:var(--gray-500);">—</span>
                            @else
                                {{ $surveyors->take(3)->implode(', ') }}{{ $surveyors->count() > 3 ? ' +' . ($surveyors->count() - 3) . ' more' : '' }}
                            @endif
                        </td>
                        <td>{{ number_format($allocs->sum('portions')) }}</td>
                        <td>{{ $f->projects_sum_acquisition_size_ha !== null ? rtrim(rtrim(number_format((float) $f->projects_sum_acquisition_size_ha, 4), '0'), '.') : '—' }}</td>
                        <td><span class="status-badge {{ $statusClass($f->status) }}"><span class="dot"></span>{{ $f->status }}</span></td>
                        <td>{{ optional($f->updated_at)->format('d M Y H:i') }}</td>
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
                                    <a href="{{ route('survey-module.compensation.projects', ['co_file' => $f->id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="folders" class="w-4 h-4 text-gray-500"></i> View Projects
                                    </a>
                                    @if ($f->status !== 'Closed')
                                        <a href="{{ route('survey-module.compensation.projects.create', ['co_file' => $f->id]) }}" class="{{ $svItem }}">
                                            <i data-lucide="folder-plus" class="w-4 h-4 text-gray-500"></i> Create Project
                                        </a>
                                    @endif
                                    <a href="{{ route('survey-module.compensation.co-files.edit', $f) }}" class="{{ $svItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                    </a>
                                    @if ($f->projects_count === 0)
                                        <form method="POST" action="{{ route('survey-module.compensation.co-files.destroy', $f) }}"
                                              onsubmit="return confirm('Delete CO file {{ $f->co_file_no }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'source', 'from', 'to']))
                                No CO files match this filter.
                            @else
                                No CO files yet.
                                <a href="{{ route('survey-module.compensation.co-files.create') }}">Commission the first one</a>
                                or <a href="{{ route('survey-module.compensation.co-files.create', ['mode' => 'captured']) }}">capture an existing file</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $coFiles->firstItem() ?? 0 }}–{{ $coFiles->lastItem() ?? 0 }}
            of {{ number_format($coFiles->total()) }} CO files
        </span>
        <div class="pagination">{{ $coFiles->links() }}</div>
    </div>
</div>
