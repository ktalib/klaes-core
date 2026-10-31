@php
    // Which list (user, 2026-10-30): Commission CO File = commissioned files, CO Files = captured files.
    $commissioned = ($only ?? null) === \App\Models\Survey\SurveyCoFile::SOURCE_COMMISSIONED;
@endphp
@php
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $statusClass = fn ($s) => match ($s) {
        'Active', 'Commissioned', 'Captured' => 'active',
        'Closed' => 'rejected',
        default => 'pending',
    };
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if ($commissioned)
            <a href="{{ route('survey-module.compensation.co-files.create') }}" class="btn btn-primary">
                <i class="fas fa-plus"></i> Commission CO File
            </a>
        @else
            <a href="{{ route('survey-module.compensation.co-files.create', ['mode' => 'captured']) }}" class="btn btn-primary">
                <i class="fas fa-keyboard"></i> Capture CO File
            </a>
        @endif
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">{{ $commissioned ? 'Commissioned CO Files' : 'Captured CO Files' }}</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Projects</div>
        <div class="kpi-value">{{ number_format($stats['projects']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Closed</div>
        <div class="kpi-value">{{ number_format($stats['closed']) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search CO file, title, TP No., related file or project…" style="{{ $inputStyle }}width:260px;" />
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (\App\Models\Survey\SurveyCoFile::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        @include('survey_module.partials._origin_filter')
        <input type="date" name="from" value="{{ request('from') }}" title="Commissioned from" style="{{ $inputStyle }}" />
        <input type="date" name="to" value="{{ request('to') }}" title="Commissioned to" style="{{ $inputStyle }}" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'from', 'to', 'origin']))
            <a class="btn btn-outline btn-sm" href="{{ url()->current() }}">Clear</a>
        @endif
    </div>
</form>

@include('survey_module.partials._table_ui')

<div class="table-wrapper">
    <div class="table-scroll">
        <table class="sv-dt">
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>CO File No</th>
                    <th>Related Files</th>
                    <th>Title</th>
                    <th>TP No(s).</th>
                    <th>Location</th>
                    <th>Commission Date</th>
                    <th>Projects</th>
                    <th>Surveyor(s)</th>
                    <th class="ctr">Total Portions</th>
                    <th class="num" title="Size of Acquisition (hectares)">Acquisition (Ha)</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Created</th>
                    <th>Last Updated</th>
                    <th class="col-actions"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coFiles as $f)
                    @php
                        $allocs    = $f->projects->flatMap->surveyors;
                        $surveyors = $allocs->pluck('surveyor_name')->unique()->values();
                    @endphp
                    <tr>
                        <td class="muted">{{ $coFiles->firstItem() + $loop->index }}</td>
                        <td class="code">
                            {{ $f->co_file_no }}
                            @if ($f->source === 'captured')
                                <span class="sub">Captured</span>
                            @endif
                        </td>
                        {{-- Related file and its title under it (CR 30102026 items 30, 31). --}}
                        <td class="code">
                            @if ($f->related_file_no)
                                {{ $f->related_file_no }}
                                @php $relTitle = $relatedTitles[mb_strtoupper(trim($f->related_file_no))] ?? null; @endphp
                                @if ($relTitle)<span class="sub" style="font-family:var(--font);white-space:normal;" title="{{ $relTitle }}">{{ \Illuminate\Support\Str::limit($relTitle, 40) }}</span>@endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="wide"><span class="strong" title="{{ $f->title }}">{{ $f->display_title }}</span></td>
                        <td>
                            @php $tps = $f->tpNumbers(); @endphp
                            @if ($tps)
                                <div class="chips">@foreach ($tps as $tp)<span class="tp">{{ $tp }}</span>@endforeach</div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td class="mid">{{ $f->property_location ?: '—' }}</td>
                        <td class="nowrap">@if ($f->commission_date){{ $f->commission_date->format('d M Y') }}@else<span class="muted">—</span>@endif</td>
                        <td>
                            @forelse ($f->projects as $p)
                                <div class="nowrap" title="{{ $p->display_name }}{{ $p->plan_no ? ' · ' . $p->plan_no : '' }}"><span class="code">{{ $p->project_code }}</span> <span style="color:var(--gray-500);">{{ \Illuminate\Support\Str::limit($p->display_name, 26) }}</span></div>
                            @empty
                                <span class="muted">None yet</span>
                            @endforelse
                        </td>
                        <td>
                            @if ($surveyors->isEmpty())
                                <span class="muted">—</span>
                            @else
                                <div class="chips">
                                    @foreach ($surveyors->take(3) as $sv)
                                        <span class="chip" style="padding-right:9px;">{{ $sv }}</span>
                                    @endforeach
                                    @if ($surveyors->count() > 3)
                                        <span class="sub" title="{{ $surveyors->slice(3)->implode(', ') }}">+{{ $surveyors->count() - 3 }} more</span>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="ctr">@if ($allocs->sum('portions')){{ number_format($allocs->sum('portions')) }}@else<span class="muted">—</span>@endif</td>
                        <td class="num">@if ($f->projects_sum_acquisition_size_ha !== null){{ rtrim(rtrim(number_format((float) $f->projects_sum_acquisition_size_ha, 4), '0'), '.') }}@else<span class="muted">—</span>@endif</td>
                        <td><span class="status-badge {{ $statusClass($f->status) }}"><span class="dot"></span>{{ $f->status }}</span></td>
                        @include('survey_module.partials._created_cells', ['row' => $f])
                        <td class="nowrap" style="color:var(--gray-500);">{{ optional($f->updated_at)->format('d M Y H:i') }}</td>
                        <td class="col-actions">
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
                                    <a href="{{ route('survey-module.compensation.op-verification', ['co_file' => $f->id]) }}" class="{{ $svItem }}">
                                        <i data-lucide="check-check" class="w-4 h-4 text-gray-500"></i> Send to OP Verification
                                    </a>
                                    <a href="{{ route('survey-module.compensation.co-files.commissioning-sheet', $f) }}" class="{{ $svItem }}" target="_blank" rel="noopener">
                                        <i data-lucide="printer" class="w-4 h-4 text-gray-500"></i> Commissioning Sheet
                                    </a>
                                    {{-- The CO file's scans in Doc-WARE (CR 30102026 item 17). --}}
                                    <a href="{{ route('filearchive.index', ['search' => $f->co_file_no, 'field' => 'fileNumber']) }}" class="{{ $svItem }}" target="_blank" rel="noopener">
                                        <i data-lucide="file-search" class="w-4 h-4 text-gray-500"></i> View Uploaded Files
                                    </a>
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
                        <td colspan="16" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'from', 'to', 'origin']))
                                No CO files match this filter.
                            @elseif ($commissioned)
                                No CO files commissioned yet. <a href="{{ route('survey-module.compensation.co-files.create') }}">Commission the first one</a>.
                            @else
                                No CO files captured yet. <a href="{{ route('survey-module.compensation.co-files.create', ['mode' => 'captured']) }}">Capture an existing file</a>.
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
