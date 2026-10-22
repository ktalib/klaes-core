{{-- Misc KN: the Misc KN files on the file indexing table (user, 2026-10-21). Read-only;
     files are indexed and corrected in File Indexing. --}}
@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Misc KN Files</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
        <div class="kpi-sub">Indexed</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">This Month</div>
        <div class="kpi-value">{{ number_format($stats['this_month']) }}</div>
        <div class="kpi-sub">{{ now()->format('F Y') }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">LGAs</div>
        <div class="kpi-value">{{ number_format($stats['lgas']) }}</div>
        <div class="kpi-sub">With Misc KN files</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Decommissioned</div>
        <div class="kpi-value">{{ number_format($stats['decommissioned']) }}</div>
        <div class="kpi-sub">Retired, still listed</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search file no., title, plot, district…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:280px;" />
        <select name="lga" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All LGAs</option>
            @foreach ($lgas as $l)
                <option value="{{ $l }}" @selected(request('lga') === $l)>{{ $l }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'lga']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.records.misc') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>File No.</th>
                    <th>File Title</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>Land Use</th>
                    <th>Registry</th>
                    <th>Indexed</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($files as $f)
                    @php
                        // Location is district + LGA (+ state), never the plot number.
                        $location = collect([$f->district, $f->lga])->map(fn ($v) => trim((string) $v))->filter()->implode(', ');
                        $items = $canViewLibrary
                            ? [['View documents', 'library', route('filearchive.index', ['url' => 'survey', 'search' => $f->file_number])]]
                            : [];
                    @endphp
                    <tr>
                        <td>{{ $files->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $f->file_number }}</strong></td>
                        <td>{{ $f->file_title ?: '—' }}</td>
                        <td>{{ $f->plot_number ?: '—' }}</td>
                        <td>{{ $f->district ?: '—' }}</td>
                        <td>{{ $f->lga ?: '—' }}</td>
                        <td>{{ $location ? $location . ', Kano State' : '—' }}</td>
                        <td>{{ $f->land_use_type ?: '—' }}</td>
                        <td>{{ $f->registry ?: '—' }}</td>
                        <td>
                            {{ $f->created_at ? \Illuminate\Support\Carbon::parse($f->created_at)->format('d M Y') : '—' }}
                            @if ($f->created_by)
                                <div style="font-size:12px;color:var(--gray-600);">{{ $f->created_by }}</div>
                            @endif
                        </td>
                        <td>
                            @if ((string) $f->is_decommissioned === '1')
                                <span class="status-badge rejected"><span class="dot"></span>Decommissioned</span>
                            @else
                                <span class="status-badge active"><span class="dot"></span>Active</span>
                            @endif
                        </td>
                        <td>
                            @if ($items)
                                @include('cadastral_dept.partials._row_actions', ['items' => $items])
                            @else
                                <span style="color:var(--gray-400);">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'lga']))
                                No Misc KN files match that filter.
                                <a href="{{ route('survey-module.records.misc') }}">Clear the filter</a>.
                            @else
                                No Misc KN files have been indexed yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $files->firstItem() ?? 0 }}–{{ $files->lastItem() ?? 0 }}
            of {{ number_format($files->total()) }} file(s) · from File Indexing
        </span>
        <div class="pagination">{{ $files->links() }}</div>
    </div>
</div>
