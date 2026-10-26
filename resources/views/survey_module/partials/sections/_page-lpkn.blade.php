@php
    use App\Http\Controllers\Survey\LpknController;

    $editing = $editing ?? null;
    // Opened from GKN Applications ("Create Layout Plan" on an LPKN file) too.
    $formOpen = $editing || $errors->any() || old('layout_name') !== null || request()->filled('file');

    $badge = [
        'Draft'        => 'pending',
        'Submitted'    => 'pending',
        'Under Review' => 'review',
        'Approved'     => 'active',
        'Rejected'     => 'rejected',
    ];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary btn-sm"
            onclick="document.getElementById('lpknForm').classList.toggle('open')">
        <i class="fas fa-plus"></i> Register Layout
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Layout Plans</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Approved</div>
        <div class="kpi-value">{{ number_format($stats['approved']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Awaiting Approval</div>
        <div class="kpi-value">{{ number_format($stats['pending']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Plots Laid Out</div>
        <div class="kpi-value">{{ number_format($stats['plots']) }}</div>
        <div class="kpi-sub">{{ number_format($stats['area'], 2) }} Ha total</div>
    </div>
</div>

<form method="POST" id="lpknForm" class="farmer-entry-form {{ $formOpen ? 'open' : '' }}"
      data-sv-modal="{{ $editing ? 'Edit Layout Plan ' . $layout->lpkn_number : 'Register Layout Plan' }}"
      @if ($editing) data-sv-modal-close="{{ route('survey-module.records.lpkn') }}" @endif
      action="{{ $editing
                  ? route('survey-module.records.lpkn.update', $editing)
                  : route('survey-module.records.lpkn.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    {{-- The application file the layout is drawn for, from the global selector (2026-10-25). --}}
    <div class="form-grid">
        <div class="sv-group"><i class="fas fa-folder-open"></i><span>Application file</span></div>
        @include('survey_module.partials._file_picker', [
            'id' => 'lp', 'name' => 'application_file_number', 'value' => $layout->application_file_number ?: request('file'),
            'label' => 'Application File (LPKN)', 'fill' => ['title' => 'lpknLayoutName'],
        ])
    </div>

    <div class="form-grid">
        <div class="sv-group"><i class="fas fa-map"></i><span>Layout</span></div>
        <div class="form-group">
            <label>LPKN Number</label>
            <input type="text" value="{{ $editing ? $layout->lpkn_number : 'Generated on save' }}" readonly
                   style="background:var(--gray-100);color:var(--gray-600);" />
        </div>
        <div class="form-group">
            <label>Layout Name <span class="required">*</span></label>
            <input type="text" name="layout_name" id="lpknLayoutName" value="{{ old('layout_name', $layout->layout_name) }}"
                   placeholder="e.g. Green Valley Estate" required />
        </div>
        <div class="form-group">
            <label>No. of Plots</label>
            <input type="number" name="plot_count" min="0" step="1"
                   value="{{ old('plot_count', $layout->plot_count) }}" placeholder="e.g. 40" />
        </div>
        <div class="form-group">
            <label>Area (Ha)</label>
            <input type="number" name="area_ha" min="0" step="0.01"
                   value="{{ old('area_ha', $layout->area_ha) }}" placeholder="e.g. 12.50" />
        </div>
    </div>

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-clipboard-check"></i><span>Use and status</span></div>
        <div class="form-group">
            <label>Land Use</label>
            <select name="land_use">
                <option value="">— Select land use —</option>
                @foreach (LpknController::LAND_USES as $u)
                    <option value="{{ $u }}" @selected(old('land_use', $layout->land_use) === $u)>{{ $u }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Record Date</label>
            <input type="date" name="record_date"
                   value="{{ old('record_date', optional($layout->record_date)->format('Y-m-d')) }}" />
        </div>
        <div class="form-group">
            <label>Status <span class="required">*</span></label>
            <select name="status">
                @foreach (LpknController::STATUSES as $s)
                    <option value="{{ $s }}" @selected(old('status', $layout->status ?? 'Draft') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>


    {{-- District, LGA, State — the layout's own plot numbers are not part of this. --}}
    @include('survey_module.partials._address_builder', [
        'prefix' => 'prop_',
        'mode'   => 'property',
        'model'  => $layout,
        'legend' => 'Layout Location',
    ])

    <div class="form-grid" style="margin-top:18px;">
        <div class="sv-group"><i class="fas fa-sticky-note"></i><span>Remarks</span></div>
        <div class="form-group full">
            <label class="sr-only">Remarks</label>
            <textarea name="remarks" placeholder="Optional notes…"
                      style="min-height:70px;width:100%;padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;">{{ old('remarks', $layout->remarks) }}</textarea>
        </div>
    </div>

    <div class="form-actions">
        <div class="left">
            @if ($editing)
                <a class="btn btn-secondary" href="{{ route('survey-module.records.lpkn') }}"><i class="fas fa-times"></i> Cancel</a>
            @else
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('lpknForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button>
            @endif
        </div>
        <div class="right">
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Register Layout' }}</button>
        </div>
    </div>
</form>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search LPKN no. or layout name…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />
        <select name="status"
                style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach (LpknController::STATUSES as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="land_use"
                style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Land Use</option>
            @foreach (LpknController::LAND_USES as $u)
                <option value="{{ $u }}" @selected(request('land_use') === $u)>{{ $u }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'status', 'land_use']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.records.lpkn') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>LPKN Ref</th>
                    <th>Application File</th>
                    <th>Layout Name</th>
                    <th>Location</th>
                    <th>Plots</th>
                    <th>Area (Ha)</th>
                    <th>Land Use</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($layouts as $l)
                    <tr>
                        <td>{{ $layouts->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $l->lpkn_number }}</strong></td>
                        <td style="font-family:monospace;">
                            @if ($l->application_file_number)
                                <a href="{{ route('survey-module.gkn.applications', ['type' => 'lpkn', 'q' => $l->application_file_number]) }}">{{ $l->application_file_number }}</a>
                            @else — @endif
                        </td>
                        <td>
                            {{ $l->layout_name }}
                            @if ($l->remarks)
                                <div style="font-size:12px;color:var(--gray-600);">{{ Str::limit($l->remarks, 60) }}</div>
                            @endif
                        </td>
                        <td>{{ $l->property_location ?: '—' }}</td>
                        <td>{{ $l->plot_count !== null ? number_format($l->plot_count) : '—' }}</td>
                        <td>{{ $l->area_ha !== null ? number_format($l->area_ha, 2) : '—' }}</td>
                        <td>{{ $l->land_use ?: '—' }}</td>
                        <td>
                            <span class="status-badge {{ $badge[$l->status] ?? 'pending' }}">
                                <span class="dot"></span>{{ $l->status }}
                            </span>
                        </td>
                        <td>{{ optional($l->record_date)->format('Y-m-d') ?: '—' }}</td>
                        <td>
                            @php $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50'; @endphp
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.records.lpkn', array_merge(request()->query(), ['edit' => $l->id])) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                    <form method="POST" action="{{ route('survey-module.records.lpkn.destroy', $l) }}"
                                          onsubmit="return confirm('Delete {{ $l->lpkn_number }}? This cannot be undone.');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-drafting-compass" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'status', 'land_use']))
                                No layout plans match that filter.
                                <a href="{{ route('survey-module.records.lpkn') }}">Clear the filter</a>.
                            @else
                                No layout plans registered yet.
                                <a href="#" onclick="document.getElementById('lpknForm').classList.add('open');return false;">Register the first one</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $layouts->firstItem() ?? 0 }}–{{ $layouts->lastItem() ?? 0 }}
            of {{ number_format($layouts->total()) }} layout plan(s)
            · {{ number_format($stats['pending']) }} awaiting approval
        </span>
        <div class="pagination">{{ $layouts->links() }}</div>
    </div>
</div>
