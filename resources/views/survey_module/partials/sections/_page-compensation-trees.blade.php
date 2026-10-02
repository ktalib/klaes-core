@php
    use App\Http\Controllers\Survey\TreeController;

    $categories = TreeController::CATEGORIES;
    $statuses   = TreeController::STATUSES;
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="toggleTreeCatalogueForm()">
        <i class="fas fa-tree"></i> Add Tree Type
    </button>
</div>

{{-- New catalogue entry. Kept open when validation sent us back with errors. --}}
<form method="POST" action="{{ route('survey-module.compensation.trees.store') }}"
      class="farmer-entry-form {{ $errors->any() && old('_form') === 'catalogue' ? 'open' : '' }}" id="ttForm">
    @csrf
    <input type="hidden" name="_form" value="catalogue" />
    <div class="form-row" style="grid-template-columns:1.5fr 1fr 1fr 1fr auto;">
        <div>
            <label>Tree Type <span class="required">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Palm Oil" required />
        </div>
        <div>
            <label>Default Unit Price (₦) <span class="required">*</span></label>
            <input type="number" step="0.01" min="0" name="default_unit_price"
                   value="{{ old('default_unit_price', '0') }}" required />
        </div>
        <div>
            <label>Category <span class="required">*</span></label>
            <select name="category">
                @foreach ($categories as $c)
                    <option value="{{ $c }}" @selected(old('category') === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Status <span class="required">*</span></label>
            <select name="status">
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(old('status', 'Active') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div style="display:flex;align-items:end;gap:6px;">
            <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i></button>
            <button class="btn btn-secondary btn-sm" type="button" onclick="toggleTreeCatalogueForm()"><i class="fas fa-times"></i></button>
        </div>
    </div>
</form>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-label">Tree Types</div>
        <div class="kpi-value">{{ number_format($stats['types']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Active</div>
        <div class="kpi-value">{{ number_format($stats['active']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Case Tree Lines</div>
        <div class="kpi-value">{{ number_format($stats['lines']) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Total Valued</div>
        <div class="kpi-value">₦{{ number_format($stats['valued'], 2) }}</div>
    </div>
</div>

<form method="GET" class="table-toolbar">
    @if (request('case'))
        <input type="hidden" name="case" value="{{ request('case') }}" />
    @endif
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search tree type…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:220px;" />
        <select name="category" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Categories</option>
            @foreach ($categories as $c)
                <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','category','status']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.trees', request('case') ? ['case' => request('case')] : []) }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>Tree Type</th>
                    <th>Category</th>
                    <th>Unit Price (₦)</th>
                    <th>Cases Using</th>
                    <th>Lines</th>
                    <th>Total Valued (₦)</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($treeTypes as $t)
                    <tr>
                        <td><strong>{{ $t->name }}</strong></td>
                        <td>{{ $t->category ?: '—' }}</td>
                        <td>{{ number_format((float) $t->default_unit_price, 2) }}</td>
                        <td>{{ number_format((int) $t->cases_using) }}</td>
                        <td>{{ number_format((int) $t->lines_count) }}</td>
                        <td>{{ number_format((float) $t->total_valued, 2) }}</td>
                        <td>
                            <span class="status-badge {{ $t->status === 'Active' ? 'active' : 'rejected' }}">
                                <span class="dot"></span>{{ $t->status }}
                            </span>
                        </td>
                        <td>
                            <div class="action-icons">
                                <button type="button" title="Edit" onclick="toggleTreeTypeEdit({{ $t->id }})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('survey-module.compensation.trees.destroy', $t) }}"
                                      style="display:inline"
                                      onsubmit="return confirm('Delete {{ $t->name }}? This cannot be undone.');">
                                    @csrf @method('DELETE')
                                    <button type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr id="ttEdit{{ $t->id }}" style="display:none;">
                        <td colspan="8" style="background:var(--gray-50);">
                            <form method="POST" action="{{ route('survey-module.compensation.trees.update', $t) }}">
                                @csrf @method('PUT')
                                <div class="form-row" style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr auto;gap:12px;align-items:end;">
                                    <div>
                                        <label>Tree Type</label>
                                        <input type="text" name="name" value="{{ $t->name }}" class="form-control" required />
                                    </div>
                                    <div>
                                        <label>Default Unit Price (₦)</label>
                                        <input type="number" step="0.01" min="0" name="default_unit_price"
                                               value="{{ (float) $t->default_unit_price }}" class="form-control" required />
                                    </div>
                                    <div>
                                        <label>Category</label>
                                        <select name="category" class="form-control">
                                            @foreach ($categories as $c)
                                                <option value="{{ $c }}" @selected($t->category === $c)>{{ $c }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            @foreach ($statuses as $s)
                                                <option value="{{ $s }}" @selected($t->status === $s)>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> Save</button>
                                        <button class="btn btn-secondary btn-sm" type="button"
                                                onclick="toggleTreeTypeEdit({{ $t->id }})"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <p class="helper-text" style="margin-top:8px;">
                                    Changing the price affects new case lines only — the
                                    {{ number_format((int) $t->lines_count) }} existing line(s) keep the price they were valued at.
                                </p>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-tree" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No tree types in the catalogue yet.
                            <a href="#" onclick="toggleTreeCatalogueForm();return false;">Add the first one</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $treeTypes->firstItem() ?? 0 }}–{{ $treeTypes->lastItem() ?? 0 }}
            of {{ number_format($treeTypes->total()) }} tree types · Cases Using, Lines and Total Valued
            are read live from case tree lines · Monetary schemes only
        </span>
        <div class="pagination">{{ $treeTypes->links() }}</div>
    </div>
</div>

{{-- ------------------------------ Per-case lines ------------------------------ --}}
<div class="dash-card" style="margin-top:24px;">
    <div class="card-header">
        <h3 style="margin:0;font-size:16px;">Case Tree Lines</h3>
    </div>

    <form method="GET" class="table-toolbar" style="border:0;">
        @foreach (['q','category','status'] as $keep)
            @if (request($keep))
                <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}" />
            @endif
        @endforeach
        <div class="left">
            <select name="case" onchange="this.form.submit()"
                    style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;min-width:240px;">
                <option value="">Select a monetary case…</option>
                @foreach ($monetaryCases as $c)
                    <option value="{{ $c->id }}" @selected($selectedCase && $selectedCase->id === $c->id)>{{ $c->case_ref }}</option>
                @endforeach
            </select>
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i> Load</button>
            @if ($selectedCase)
                <a class="btn btn-outline btn-sm"
                   href="{{ route('survey-module.compensation.trees', request()->except('case')) }}">Clear</a>
            @endif
        </div>
    </form>

    @if (! $selectedCase)
        <div class="comp-type-note">
            <i class="fas fa-info-circle"></i>
            Choose a case to record its economic trees. Only <strong>Monetary</strong> cases are listed —
            Land-for-Land cases are compensated with plots, not cash, and the server refuses tree lines on them.
        </div>
    @elseif (! $selectedCase->isMonetary())
        <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;">
            <i class="fas fa-exclamation-triangle"></i>
            {{ $selectedCase->case_ref }} is a <strong>Land-for-Land</strong> case. Economic trees are monetary-only;
            allocate plots in <a href="{{ route('survey-module.tools.plot-allocation', ['case' => $selectedCase->id]) }}">Plot Allocation</a> instead.
        </div>
    @else
        <form method="POST" action="{{ route('survey-module.compensation.trees.case.store', $selectedCase) }}"
              style="margin-bottom:16px;">
            @csrf
            <div class="form-grid">
                <div class="form-group">
                    <label>Tree Type <span class="required">*</span></label>
                    <select name="survey_tree_type_id" id="ctlType" onchange="prefillCaseTreePrice()">
                        <option value="">Select from catalogue…</option>
                        @foreach ($activeTypes as $a)
                            <option value="{{ $a->id }}" data-price="{{ (float) $a->default_unit_price }}">
                                {{ $a->name }} ({{ $a->category }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity <span class="required">*</span></label>
                    <input type="number" min="1" name="quantity" id="ctlQty" value="1" oninput="previewCaseTreeLine()" />
                </div>
                <div class="form-group">
                    <label>Unit Price (₦)</label>
                    <input type="number" step="0.01" min="0" name="unit_price" id="ctlPrice" oninput="previewCaseTreeLine()" />
                    <p class="helper-text">Pre-filled from the catalogue; override only if this case was valued differently.</p>
                </div>
                <div class="form-group">
                    <label>Line Total (preview)</label>
                    <input type="text" id="ctlPreview" value="₦0.00" readonly
                           style="font-weight:700;color:var(--primary);background:var(--gray-100);" />
                    <p class="helper-text">The server recomputes this on save — the preview is never trusted.</p>
                </div>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-plus"></i> Add Tree Line</button>
        </form>

        <div class="table-wrapper">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Tree Type</th>
                            <th>Quantity</th>
                            <th>Unit Price (₦)</th>
                            <th>Line Total (₦)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedCase->trees as $line)
                            <tr>
                                <td><strong>{{ $line->tree_type }}</strong></td>
                                <td>{{ number_format((int) $line->quantity) }}</td>
                                <td>{{ number_format((float) $line->unit_price, 2) }}</td>
                                <td>{{ number_format((float) $line->line_total, 2) }}</td>
                                <td>
                                    <div class="action-icons">
                                        <form method="POST"
                                              action="{{ route('survey-module.compensation.trees.case.destroy', $line) }}"
                                              style="display:inline"
                                              onsubmit="return confirm('Remove this tree line?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="Remove"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center;padding:24px;color:var(--gray-500);">
                                    No tree lines on {{ $selectedCase->case_ref }} yet. Add the first one above.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>
                    {{ $selectedCase->case_ref }} · {{ number_format($selectedCase->trees->count()) }} line(s) ·
                    <strong>Case tree total ₦{{ number_format($selectedCase->tree_total, 2) }}</strong>
                </span>
            </div>
        </div>
    @endif
</div>

<script>
    // Own names throughout: _scripts.blade.php already defines toggleTreeForm()
    // and calculateTreeTotal() against the prototype's demo ids.
    function toggleTreeCatalogueForm() {
        var f = document.getElementById('ttForm');
        if (f) f.classList.toggle('open');
    }

    function toggleTreeTypeEdit(id) {
        var row = document.getElementById('ttEdit' + id);
        if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
    }

    function prefillCaseTreePrice() {
        var sel = document.getElementById('ctlType');
        var price = document.getElementById('ctlPrice');
        if (!sel || !price) return;
        var opt = sel.options[sel.selectedIndex];
        price.value = opt && opt.dataset.price ? opt.dataset.price : '';
        previewCaseTreeLine();
    }

    function previewCaseTreeLine() {
        var qty = parseInt(document.getElementById('ctlQty')?.value, 10) || 0;
        var price = parseFloat(document.getElementById('ctlPrice')?.value) || 0;
        var out = document.getElementById('ctlPreview');
        if (out) out.value = '₦' + (qty * price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>
