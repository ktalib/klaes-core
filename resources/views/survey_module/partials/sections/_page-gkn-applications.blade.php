@php
    use App\Http\Controllers\Survey\GknApplicationController as Apps;

    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $tabLink = fn (?string $type) => route('survey-module.gkn.applications', array_filter(['type' => $type, 'q' => request('q'), 'source' => request('source')]));
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    {{-- The existing GKN file number generator (GKN-, LPKN-, MISC KN), opened from here (user, 2026-10-25). --}}
    <a href="{{ route('gkn-generation.index') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> Commission Application Files
    </a>
</div>

{{-- Type tabs: one table, coloured by type (transcript). --}}
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
    <a href="{{ $tabLink(null) }}" class="btn {{ $tab === null ? 'btn-primary' : 'btn-outline' }} btn-sm">All <span style="opacity:.75;">({{ number_format($counts['all']) }})</span></a>
    @foreach (Apps::TYPES as $k => $t)
        <a href="{{ $tabLink($k) }}"
           class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-sm font-semibold
                  {{ $tab === $k ? 'bg-' . $t['colour'] . '-600 text-white border-' . $t['colour'] . '-600' : 'bg-' . $t['colour'] . '-50 text-' . $t['colour'] . '-800 border-' . $t['colour'] . '-200' }}">
            {{ $t['label'] }} Files <span style="opacity:.75;">({{ number_format($counts[$k]) }})</span>
        </a>
    @endforeach
</div>

<form method="GET" class="table-toolbar">
    @if ($tab)<input type="hidden" name="type" value="{{ $tab }}" />@endif
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search file no., title, district, LGA…" style="{{ $inputStyle }}width:280px;" />
        <select name="source" style="{{ $inputStyle }}">
            <option value="">All Sources</option>
            <option value="indexed" @selected(request('source') === 'indexed')>Indexed (existing files)</option>
            <option value="commissioned" @selected(request('source') === 'commissioned')>Commissioned</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'source']))
            <a class="btn btn-outline btn-sm" href="{{ $tab ? route('survey-module.gkn.applications', ['type' => $tab]) : route('survey-module.gkn.applications') }}">Clear</a>
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
                    <th>Type</th>
                    <th>Title</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>Land Use</th>
                    <th>Source</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($files as $f)
                    @php $type = Apps::typeOf($f->file_number); $t = Apps::TYPES[$type] ?? null; @endphp
                    <tr>
                        <td>{{ $files->firstItem() + $loop->index }}</td>
                        <td><strong style="font-family:monospace;">{{ $f->file_number }}</strong></td>
                        <td>
                            @if ($t)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-{{ $t['colour'] }}-100 text-{{ $t['colour'] }}-800">{{ $t['label'] }}</span>
                            @endif
                        </td>
                        <td>{{ $f->file_title ?: '—' }}</td>
                        <td>{{ $f->plot_number ?: '—' }}</td>
                        <td>{{ $f->district ?: '—' }}</td>
                        <td>{{ $f->lga ?: '—' }}</td>
                        <td>{{ $f->location ?: '—' }}</td>
                        <td>{{ $f->land_use_type ?: '—' }}</td>
                        <td>{{ $f->source === Apps::COMMISSIONED_SOURCE ? 'Commissioned' : 'Indexed' }}</td>
                        <td>{{ $f->created_at ? \Illuminate\Support\Carbon::parse($f->created_at)->format('d M Y') : '—' }}</td>
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
                                    <a href="{{ route('fileindexing.show', $f->id) }}" class="{{ $svItem }}">
                                        <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> View Indexing Record
                                    </a>
                                    <a href="{{ route('filearchive.index', ['url' => 'survey', 'file_number' => $f->file_number]) }}" class="{{ $svItem }}">
                                        <i data-lucide="library" class="w-4 h-4 text-gray-500"></i> Open in Doc-WARE
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'source']))
                                No files match this search.
                            @elseif ($tab === 'lppl')
                                No LPPL files yet. The file number generator has no LPPL prefix yet.
                            @else
                                No files here yet. Use <strong>Commission Application Files</strong> above.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $files->firstItem() ?? 0 }}–{{ $files->lastItem() ?? 0 }} of {{ number_format($files->total()) }} files</span>
        <div class="pagination">{{ $files->links() }}</div>
    </div>
</div>
