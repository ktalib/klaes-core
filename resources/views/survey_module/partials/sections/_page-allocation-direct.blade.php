@php
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $filters = ['q', 'lga', 'land_use', 'year'];
    $dash = fn ($v) => trim((string) $v) !== '' ? trim($v) : '—';
@endphp

@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card" title="Indexed files whose Occupancy Permit Type in File Indexing is Direct Allocation">
        <div class="kpi-label">Direct Allocation Files</div>
        <div class="kpi-value">{{ number_format($stats['files']) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    <input type="hidden" name="tab" value="direct" />
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File no., title, plot, district, TP no…" style="{{ $inputStyle }}width:260px;" />
        <select name="lga" style="{{ $inputStyle }}max-width:200px;">
            <option value="">All LGAs</option>
            @foreach ($lgas as $l)
                <option value="{{ $l }}" @selected(request('lga') === $l)>{{ $l }}</option>
            @endforeach
        </select>
        <select name="land_use" style="{{ $inputStyle }}max-width:200px;">
            <option value="">All Land Uses</option>
            @foreach ($landUses as $u)
                <option value="{{ $u }}" @selected(request('land_use') === $u)>{{ $u }}</option>
            @endforeach
        </select>
        <select name="year" style="{{ $inputStyle }}">
            <option value="">All Years</option>
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected((string) request('year') === (string) $y)>{{ $y }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.allocation-records', ['tab' => 'direct']) }}">Clear</a>
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
                    <th>TP No.</th>
                    <th>Land Use</th>
                    <th>OP Serial</th>
                    <th>OP Date</th>
                    <th>Commissioned</th>
                    <th>Year</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($files as $f)
                    <tr>
                        <td>{{ $files->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;">
                            <strong>{{ $f->file_number }}</strong>
                            @if ($f->is_decommissioned)
                                <div style="font-size:11px;color:var(--danger);" title="{{ $f->successor_file_no ? 'Successor: ' . $f->successor_file_no : '' }}">
                                    <i class="fas fa-ban"></i> Decommissioned{{ $f->successor_file_no ? ' → ' . $f->successor_file_no : '' }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $dash($f->file_title) }}</td>
                        <td>{{ $dash($f->plot_number) }}</td>
                        <td>{{ $dash($f->district) }}</td>
                        <td>{{ $dash($f->lga) }}</td>
                        <td>{{ $dash($f->location) }}</td>
                        <td style="white-space:nowrap;">{{ $dash($f->tp_no) }}</td>
                        <td>{{ $dash($f->land_use_type) }}</td>
                        <td style="white-space:nowrap;">{{ $dash($f->op_serial_number) }}</td>
                        @php
                            // pra.transaction_date is free text; a mistyped one (0001-01-01, 22015-01-11) shows as typed.
                            $opDate = '—';
                            if ($f->op_date) {
                                $opDate = $f->op_date;
                                if (preg_match('/^(19|20)\d\d-\d\d-\d\d$/', $f->op_date)) {
                                    try { $opDate = \Illuminate\Support\Carbon::parse($f->op_date)->format('d M Y'); } catch (\Throwable $e) {}
                                }
                            }
                        @endphp
                        <td style="white-space:nowrap;">{{ $opDate }}</td>
                        <td style="white-space:nowrap;">{{ $f->commissioning_date ? \Illuminate\Support\Carbon::parse($f->commissioning_date)->format('d M Y') : '—' }}</td>
                        <td>{{ $dash($f->da_year) }}</td>
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
                                        <i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View Indexing
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="14" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            {{ request()->hasAny($filters) ? 'No Direct Allocation files match these filters.' : 'No Direct Allocation files are indexed yet.' }}
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
