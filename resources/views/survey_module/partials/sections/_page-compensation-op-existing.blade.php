{{-- OP > Existing (user, 2026-11-03): the OPs already issued, from Deeds (as the Plot Showing search reads them), plus
     any captured here as written. Deeds rows are read-only here; captured ones keep Edit / Remove. --}}
@php
    $e = $editing;
    $open = $e || ($errors->any() && old('_form') === 'existing');
    $v = fn ($k) => old($k, $e ? ($k === 'issued_at' ? optional($e->issued_at)->format('Y-m-d') : $e->{$k}) : null);
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $field = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
@endphp

@include('survey_module.partials._flash')

<form method="POST" id="opExistingForm" class="farmer-entry-form {{ $open ? 'open' : '' }}"
      action="{{ $e ? route('survey-module.compensation.op.existing.update', $e) : route('survey-module.compensation.op.existing.store') }}"
      data-sv-modal="{{ $e ? 'Edit Existing OP ' . $e->op_number : 'Capture Existing OP' }}">
    @csrf
    @if ($e) @method('PUT') @endif
    <input type="hidden" name="_form" value="existing">
    @if ($errors->any() && old('_form') === 'existing')
        <div class="comp-type-note" style="margin-bottom:14px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">{{ $errors->first() }}</div>
    @endif
    <div class="form-grid">
        <div class="sv-group"><i class="fas fa-file-signature"></i><span>OP</span></div>
        <div class="form-group"><label>OP No. <span class="required">*</span></label><input type="text" name="op_number" value="{{ $v('op_number') }}" maxlength="50" required><p class="helper-text">Exactly as on the OP.</p></div>
        <div class="form-group"><label>Date Issued</label><input type="date" name="issued_at" value="{{ $v('issued_at') }}" max="{{ now()->format('Y-m-d') }}"></div>
        <div class="form-group full"><label>Allottee <span class="required">*</span></label><input type="text" name="allottee_name" value="{{ $v('allottee_name') }}" maxlength="255" required></div>
        <div class="form-group full">
            <label>Project</label>
            <select name="survey_project_id" class="sv-searchable">
                <option value="">Not known</option>
                @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((string) $v('survey_project_id') === (string) $p->id)>{{ $p->project_code }} · {{ $p->name }}</option>@endforeach
            </select>
        </div>
        {{-- Property order: Plot No., District, LGA. --}}
        <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Plot</span></div>
        <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" value="{{ $v('plot_no') }}" maxlength="100"></div>
        <div class="form-group"><label>District</label><input type="text" name="district" value="{{ $v('district') }}" maxlength="150"></div>
        <div class="form-group"><label>LGA</label><input type="text" name="lga" value="{{ $v('lga') }}" maxlength="150"></div>
        <div class="form-group full"><label>Remarks</label><textarea name="remarks" maxlength="2000" rows="2">{{ $v('remarks') }}</textarea></div>
    </div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.compensation.op', ['tab' => 'existing']) }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Save' }}</button></div>
    </div>
</form>

<div class="kpi-grid">
    {{-- Resettlement + Direct Allocation = the total (user, 2026-11-03): an OP with no type in Deeds counts as Direct Allocation. --}}
    <div class="kpi-card"><div class="kpi-label">Existing OPs</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Resettlement</div><div class="kpi-value">{{ number_format($stats['resettlement']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Direct Allocation</div><div class="kpi-value">{{ number_format($stats['direct']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Captured here</div><div class="kpi-value">{{ number_format($stats['captured']) }}</div></div>
</div>

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('opExistingForm').classList.add('open')"><i class="fas fa-plus"></i> Capture Existing OP</button>
</div>

{{-- List or By TP No. (CR 03112026 item 2). --}}
@php $opViewUrl = fn (array $p) => route('survey-module.compensation.op', ['tab' => 'existing'] + $p); @endphp
<div style="display:flex;gap:6px;margin-bottom:12px;">
    <a class="btn btn-sm {{ $byTp ? 'btn-outline' : 'btn-primary' }}" href="{{ $opViewUrl([]) }}"><i class="fas fa-list"></i> List</a>
    <a class="btn btn-sm {{ $byTp ? 'btn-primary' : 'btn-outline' }}" href="{{ $opViewUrl(['view' => 'tp']) }}"><i class="fas fa-layer-group"></i> By TP No.</a>
</div>

@if ($byTp)
<form method="GET" class="table-toolbar">
    <input type="hidden" name="tab" value="existing">
    <input type="hidden" name="view" value="tp">
    <div class="left" style="flex-wrap:wrap;gap:8px;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="TP number…" style="{{ $field }}width:240px;" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->filled('q'))<a class="btn btn-outline btn-sm" href="{{ $opViewUrl(['view' => 'tp']) }}">Clear</a>@endif
    </div>
</form>
<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>TP No.</th><th>Agency</th><th>Serial No.</th><th>OPs</th><th>Resettlement</th><th>Direct Allocation</th><th>In Public Layout Plans</th></tr></thead>
            <tbody>
                @forelse ($byTp as $g)
                    <tr>
                        <td>{{ $byTp->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;"><a href="{{ $opViewUrl(['tp' => $g->id]) }}"><strong>{{ $g->label }}</strong></a>@if ($g->needs_review)<div class="helper-text" style="margin:0;">Needs checking</div>@endif</td>
                        <td>{{ $g->agency ?: '—' }}</td>
                        <td>{{ $g->serial ?: '—' }}</td>
                        <td>{{ number_format($g->op_count) }}</td>
                        <td>{{ number_format($g->resettlement_count) }}</td>
                        <td>{{ number_format($g->direct_count) }}</td>
                        <td>{{ $g->register_rows ? 'Yes' . ($g->register_rows > 1 ? ' (' . $g->register_rows . ' rows)' : '') : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:22px;color:var(--gray-500);">No layout matches the filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($byTp->total()) }} layouts</span><div class="pagination">{{ $byTp->links() }}</div></div>
</div>
@else
<form method="GET" class="table-toolbar">
    <input type="hidden" name="tab" value="existing">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="OP serial, allottee, plot, file no., district…" style="{{ $field }}width:280px;" />
        <select name="kind" style="{{ $field }}">
            <option value="">All types</option>
            @foreach ($kinds as $k)<option value="{{ $k }}" @selected(request('kind') === $k)>{{ $k }}</option>@endforeach
        </select>
        <select name="tp" class="sv-searchable" style="{{ $field }}max-width:260px;">
            <option value="">All TP Nos.</option>
            @foreach ($tpGroups as $g)<option value="{{ $g->id }}" @selected(request('tp') === (string) $g->id)>{{ $g->label }} ({{ number_format($g->op_count) }})</option>@endforeach
            <option value="none" @selected(request('tp') === 'none')>No TP No.</option>
        </select>
        <select name="project" class="sv-searchable" style="{{ $field }}max-width:320px;">
            <option value="">All projects</option>
            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected((string) request('project') === (string) $p->id)>{{ $p->project_code }} · {{ $p->name }}</option>@endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'project', 'kind', 'tp']))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.op', ['tab' => 'existing']) }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                {{-- Property order: Plot No., District, LGA, Location. --}}
                <tr><th>S/N</th><th>File No.</th><th>OP Serial</th><th>Type</th><th>Allottee</th><th>TP No.</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Date</th><th>Source</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($ops as $op)
                    <tr>
                        <td>{{ $ops->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;">{{ $op->file_no ?: '—' }}</td>
                        <td style="white-space:nowrap;"><strong>{{ $op->serial ?: '—' }}</strong></td>
                        <td style="white-space:nowrap;">{{ $op->op_kind }}</td>
                        <td>{{ $op->allottee ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $op->tp_no ?: '—' }}</td>
                        <td>{{ $op->plot_no ?: '—' }}</td>
                        <td>{{ $op->district ?: '—' }}</td>
                        <td>{{ $op->lga ?: '—' }}</td>
                        <td>{{ $op->location ? \Illuminate\Support\Str::limit($op->location, 60) : '—' }}</td>
                        <td style="white-space:nowrap;">{{ $op->issued ? \Illuminate\Support\Carbon::parse($op->issued)->format('d M Y') : '—' }}</td>
                        <td style="white-space:nowrap;">
                            @if ($op->src === 'klaes')<span class="status-badge active"><span class="dot"></span>Captured here</span>
                            @else<span class="status-badge completed"><span class="dot"></span>Deeds</span>@endif
                        </td>
                        <td>
                            @if ($op->src === 'klaes')
                                <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                    <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                            @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 200); open = !open"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                    </button>
                                    <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                         :style="'top:' + top + 'px; left:' + left + 'px'" class="fixed z-50 w-48 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                        <a href="{{ route('survey-module.compensation.op', ['tab' => 'existing', 'edit' => $op->rid] + request()->only(['q', 'project', 'kind', 'tp', 'page'])) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                        <form method="POST" action="{{ route('survey-module.compensation.op.existing.destroy', $op->rid) }}" onsubmit="return confirm('Remove existing OP {{ addslashes((string) $op->serial) }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                                        </form>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" style="text-align:center;padding:22px;color:var(--gray-500);">
                        {{ request()->hasAny(['q', 'project', 'kind', 'tp']) ? 'No existing OP matches the filter.' : 'No existing OPs yet.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($ops->total()) }} existing OPs · from Deeds, and any captured here</span><div class="pagination">{{ $ops->links() }}</div></div>
</div>
@endif
