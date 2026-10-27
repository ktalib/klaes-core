@php
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $ha = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $filters = ['q', 'project', 'co_file', 'progress'];
@endphp

@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Allocations</div>
        <div class="kpi-value">{{ number_format($stats['allocations']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Portions Allocated</div>
        <div class="kpi-value">{{ number_format($stats['portions']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Portions Surveyed</div>
        <div class="kpi-value">{{ number_format($stats['surveyed']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Area Allocated (Ha)</div>
        <div class="kpi-value">{{ $ha($stats['size']) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search surveyor or project…" style="{{ $inputStyle }}width:230px;" />
        <select name="co_file" style="{{ $inputStyle }}max-width:220px;">
            <option value="">All CO Files</option>
            @foreach ($coFiles as $f)
                <option value="{{ $f->id }}" @selected((string) request('co_file') === (string) $f->id)>{{ $f->co_file_no }}</option>
            @endforeach
        </select>
        <select name="project" style="{{ $inputStyle }}max-width:260px;">
            <option value="">All Projects</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->project_code }} · {{ $p->name }}</option>
            @endforeach
        </select>
        <select name="progress" style="{{ $inputStyle }}">
            <option value="">Any Progress</option>
            <option value="not-started" @selected(request('progress') === 'not-started')>Not started</option>
            <option value="in-progress" @selected(request('progress') === 'in-progress')>In progress</option>
            <option value="complete" @selected(request('progress') === 'complete')>Complete</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.allocation-records') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>CO File</th>
                    <th>Project</th>
                    <th>Surveyor</th>
                    <th style="text-align:right;">Portions</th>
                    <th style="text-align:right;">Size per Portion (Ha)</th>
                    <th style="text-align:right;">Total (Ha)</th>
                    <th style="text-align:right;">Surveyed</th>
                    <th>Progress</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($allocations as $a)
                    @php
                        $done = min($a->surveyed, $a->portions);
                        $pct  = $a->portions ? (int) round($done / $a->portions * 100) : 0;
                        $free = array_values(array_diff(range(1, max(1, $a->portions)), $a->usedPortions()));
                    @endphp
                    <tr>
                        <td>{{ $allocations->firstItem() + $loop->index }}</td>
                        <td>{{ $a->project->coFile?->co_file_no ?: '—' }}</td>
                        <td><strong>{{ $a->project->project_code }}</strong><div style="font-size:12px;color:var(--gray-500);">{{ $a->project->name }}</div></td>
                        <td>{{ $a->surveyor_name }}</td>
                        <td style="text-align:right;">{{ number_format($a->portions) }}</td>
                        <td style="text-align:right;">{{ $ha($a->portion_size_ha) }}</td>
                        <td style="text-align:right;">{{ $ha($a->total_size_ha) }}</td>
                        <td style="text-align:right;">{{ number_format($a->surveyed) }} / {{ number_format($a->portions) }}</td>
                        <td style="min-width:140px;">
                            <div style="background:var(--gray-200);border-radius:999px;height:8px;overflow:hidden;">
                                <div style="width:{{ $pct }}%;height:8px;background:{{ $pct >= 100 ? 'var(--secondary)' : 'var(--primary)' }};"></div>
                            </div>
                            <div style="font-size:11px;color:var(--gray-500);margin-top:2px;">
                                {{ $pct >= 100 ? 'Complete' : ($a->surveyed ? $pct . '%' : 'Not started') }}
                            </div>
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
                                    @if ($free)
                                        <a href="{{ route('survey-module.compensation.cases.register', ['project' => $a->survey_project_id, 'allocation' => $a->id, 'portion' => $free[0]]) }}" class="{{ $svItem }}">
                                            <i data-lucide="plus-circle" class="w-4 h-4 text-gray-500"></i> Register Portion {{ $free[0] }}
                                        </a>
                                    @endif
                                    <a href="{{ route('survey-module.compensation.cases', ['project' => $a->survey_project_id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="list" class="w-4 h-4 text-gray-500"></i> View Cases
                                    </a>
                                    <a href="{{ route('survey-module.compensation.projects.edit', $a->survey_project_id) }}" class="{{ $svItem }}">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit Allocation
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-clipboard-list" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny($filters))
                                No allocations match these filters.
                            @else
                                No surveyor allocations yet. Assign surveyors and portions on a project in
                                <a href="{{ route('survey-module.compensation.projects') }}">Projects</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $allocations->firstItem() ?? 0 }}–{{ $allocations->lastItem() ?? 0 }} of {{ number_format($allocations->total()) }} allocations</span>
        <div class="pagination">{{ $allocations->links() }}</div>
    </div>
</div>
