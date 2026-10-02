@php
    use App\Http\Controllers\Survey\LpknController;

    $editing = $editing ?? null;
    $formOpen = $editing || $errors->any() || old('layout_name') !== null;

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
      action="{{ $editing
                  ? route('survey-module.records.lpkn.update', $editing)
                  : route('survey-module.records.lpkn.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr;">
        <div>
            <label>LPKN Number</label>
            <input type="text" value="{{ $editing ? $layout->lpkn_number : 'Generated on save' }}" readonly
                   style="background:var(--gray-100);color:var(--gray-600);" />
        </div>
        <div>
            <label>Layout Name <span class="required">*</span></label>
            <input type="text" name="layout_name" value="{{ old('layout_name', $layout->layout_name) }}"
                   placeholder="e.g. Green Valley Estate" required />
        </div>
        <div>
            <label>No. of Plots</label>
            <input type="number" name="plot_count" min="0" step="1"
                   value="{{ old('plot_count', $layout->plot_count) }}" placeholder="e.g. 40" />
        </div>
        <div>
            <label>Area (Ha)</label>
            <input type="number" name="area_ha" min="0" step="0.01"
                   value="{{ old('area_ha', $layout->area_ha) }}" placeholder="e.g. 12.50" />
        </div>
    </div>

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
        <div>
            <label>Land Use</label>
            <select name="land_use">
                <option value="">— Select land use —</option>
                @foreach (LpknController::LAND_USES as $u)
                    <option value="{{ $u }}" @selected(old('land_use', $layout->land_use) === $u)>{{ $u }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Record Date</label>
            <input type="date" name="record_date"
                   value="{{ old('record_date', optional($layout->record_date)->format('Y-m-d')) }}" />
        </div>
        <div>
            <label>Status <span class="required">*</span></label>
            <select name="status">
                @foreach (LpknController::STATUSES as $s)
                    <option value="{{ $s }}" @selected(old('status', $layout->status ?? 'Draft') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-row" style="grid-template-columns:1fr;">
        <div>
            <label>Remarks</label>
            <textarea name="remarks" placeholder="Optional notes…"
                      style="min-height:70px;width:100%;padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;">{{ old('remarks', $layout->remarks) }}</textarea>
        </div>
    </div>

    {{-- District, LGA, State — the layout's own plot numbers are not part of this. --}}
    @include('survey_module.partials._address_builder', [
        'prefix' => 'prop_',
        'mode'   => 'property',
        'model'  => $layout,
        'legend' => 'Layout Location',
    ])

    <div class="form-row" style="grid-template-columns:1fr auto;">
        <div></div>
        <div style="display:flex;align-items:end;gap:6px;">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Register Layout' }}
            </button>
            @if ($editing)
                <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.lpkn') }}">
                    <i class="fas fa-times"></i> Cancel
                </a>
            @else
                <button type="button" class="btn btn-secondary btn-sm"
                        onclick="document.getElementById('lpknForm').classList.remove('open')">
                    <i class="fas fa-times"></i>
                </button>
            @endif
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
                    <th>LPKN Ref</th>
                    <th>Layout Name</th>
                    <th>Location</th>
                    <th>Plots</th>
                    <th>Area (Ha)</th>
                    <th>Land Use</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($layouts as $l)
                    <tr>
                        <td><strong>{{ $l->lpkn_number }}</strong></td>
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
                            <div class="action-icons">
                                <a href="{{ route('survey-module.records.lpkn', array_merge(request()->query(), ['edit' => $l->id])) }}"
                                   title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('survey-module.records.lpkn.destroy', $l) }}"
                                      style="display:inline"
                                      onsubmit="return confirm('Delete {{ $l->lpkn_number }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
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
