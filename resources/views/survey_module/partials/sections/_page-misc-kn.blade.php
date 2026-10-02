@php
    use App\Http\Controllers\Survey\MiscKnController;

    $editing = $editing ?? null;
    // Reopen the slide-out when editing, or when a failed save has to be shown again.
    $formOpen = $editing || $errors->any() || old('title') !== null;

    $badge = [
        'Dispute'     => 'rejected',
        'Boundary'    => 'review',
        'Survey Note' => 'active',
        'Other'       => 'pending',
    ];
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary btn-sm"
            onclick="document.getElementById('miscForm').classList.toggle('open')">
        <i class="fas fa-plus"></i> New Note
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Total Notes</div>
        <div class="kpi-value">{{ number_format($stats['total']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Disputes</div>
        <div class="kpi-value">{{ number_format($stats['disputes']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Boundary Notes</div>
        <div class="kpi-value">{{ number_format($stats['boundary']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">This Month</div>
        <div class="kpi-value">{{ number_format($stats['this_month']) }}</div>
    </div>
</div>

<form method="POST" id="miscForm" class="farmer-entry-form {{ $formOpen ? 'open' : '' }}"
      action="{{ $editing
                  ? route('survey-module.records.misc.update', $editing)
                  : route('survey-module.records.misc.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
        <div>
            <label>Reference</label>
            <input type="text" value="{{ $editing ? $note->ref_no : 'Generated on save' }}" readonly
                   style="background:var(--gray-100);color:var(--gray-600);" />
        </div>
        <div>
            <label>Title <span class="required">*</span></label>
            <input type="text" name="title" value="{{ old('title', $note->title) }}"
                   placeholder="Note title" required />
        </div>
        <div>
            <label>Category <span class="required">*</span></label>
            <select name="category">
                @foreach (MiscKnController::CATEGORIES as $c)
                    <option value="{{ $c }}" @selected(old('category', $note->category) === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
        <div>
            <label>Linked Ref</label>
            <input type="text" name="linked_ref" value="{{ old('linked_ref', $note->linked_ref) }}"
                   placeholder="GKN / Case / LPKN ref" />
        </div>
        <div>
            <label>Record Date</label>
            <input type="date" name="record_date"
                   value="{{ old('record_date', optional($note->record_date)->format('Y-m-d')) }}" />
        </div>
        <div>
            <label>Officer</label>
            <input type="text" name="officer" value="{{ old('officer', $note->officer) }}"
                   placeholder="Recording officer" />
        </div>
    </div>

    <div class="form-row" style="grid-template-columns:1fr;">
        <div>
            <label>Details</label>
            <textarea name="details" placeholder="Note content…"
                      style="min-height:80px;width:100%;padding:8px 12px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;">{{ old('details', $note->details) }}</textarea>
        </div>
    </div>

    {{-- District, LGA, State — the plot number stays in its own field. --}}
    @include('survey_module.partials._address_builder', [
        'prefix' => 'prop_',
        'mode'   => 'property',
        'model'  => $note,
        'legend' => 'Note Location',
    ])

    <div class="form-row" style="grid-template-columns:1fr auto;">
        <div></div>
        <div style="display:flex;align-items:end;gap:6px;">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Save Note' }}
            </button>
            @if ($editing)
                <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.misc') }}">
                    <i class="fas fa-times"></i> Cancel
                </a>
            @else
                <button type="button" class="btn btn-secondary btn-sm"
                        onclick="document.getElementById('miscForm').classList.remove('open')">
                    <i class="fas fa-times"></i>
                </button>
            @endif
        </div>
    </div>
</form>

<form method="GET" class="table-toolbar">
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search ref, title, linked ref…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:260px;" />
        <select name="category"
                style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Categories</option>
            @foreach (MiscKnController::CATEGORIES as $c)
                <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q', 'category']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.records.misc') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Ref</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Linked</th>
                    <th>Location</th>
                    <th>Date</th>
                    <th>Officer</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($notes as $n)
                    <tr>
                        <td><strong>{{ $n->ref_no }}</strong></td>
                        <td>
                            {{ $n->title }}
                            @if ($n->details)
                                <div style="font-size:12px;color:var(--gray-600);">{{ Str::limit($n->details, 70) }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="status-badge {{ $badge[$n->category] ?? 'pending' }}">
                                <span class="dot"></span>{{ $n->category ?: '—' }}
                            </span>
                        </td>
                        <td>{{ $n->linked_ref ?: '—' }}</td>
                        <td>{{ $n->property_location ?: '—' }}</td>
                        <td>{{ optional($n->record_date)->format('Y-m-d') ?: '—' }}</td>
                        <td>{{ $n->officer ?: '—' }}</td>
                        <td>
                            <div class="action-icons">
                                <a href="{{ route('survey-module.records.misc', array_merge(request()->query(), ['edit' => $n->id])) }}"
                                   title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="{{ route('survey-module.records.misc.destroy', $n) }}"
                                      style="display:inline"
                                      onsubmit="return confirm('Delete {{ $n->ref_no }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-sticky-note" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny(['q', 'category']))
                                No notes match that filter.
                                <a href="{{ route('survey-module.records.misc') }}">Clear the filter</a>.
                            @else
                                No miscellaneous notes yet.
                                <a href="#" onclick="document.getElementById('miscForm').classList.add('open');return false;">Record the first one</a>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $notes->firstItem() ?? 0 }}–{{ $notes->lastItem() ?? 0 }}
            of {{ number_format($notes->total()) }} note(s)
        </span>
        <div class="pagination">{{ $notes->links() }}</div>
    </div>
</div>
