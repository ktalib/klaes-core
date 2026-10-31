@php
    use App\Http\Controllers\Survey\TreeController;

    $categories = TreeController::CATEGORIES;
    // Farm Assessment tabs (CR 30102026 item 6): the Type filter offers only the open tab's Types.
    $tabCategories = $tab === 'cash' ? [TreeController::CASH_CROP] : array_values(array_diff($categories, [TreeController::CASH_CROP]));
    // Category = Mature / Early, one catalogue row each with its own rate (user, 2026-10-30). Type = Cash Crop / Timber / Fruit.
    $maturities = \App\Models\Survey\SurveyTreeType::MATURITIES;
    $statuses   = TreeController::STATUSES;
    $svItem     = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $rate       = fn ($v) => $v === null ? null : number_format((float) $v, 2);
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary btn-sm" onclick="toggleTreeCatalogueForm()">
        <i class="fas fa-tree"></i> Add Tree Type
    </button>
</div>

{{-- New catalogue entry, as a modal. Reopens when validation sent us back with errors. --}}
<form method="POST" action="{{ route('survey-module.compensation.trees.store') }}"
      class="farmer-entry-form {{ $errors->any() && old('_form') === 'catalogue' ? 'open' : '' }}" id="ttForm"
      data-sv-modal="Add Tree Type" data-sv-modal-size="sm">
    @csrf
    <input type="hidden" name="_form" value="catalogue" />
    <div class="form-grid">
        <div class="sv-group"><i class="fas fa-tree"></i><span>Names</span></div>
        <div class="form-group full">
            <label>Tree Type <span class="required">*</span></label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Locust bean" required />
        </div>
        <div class="form-group">
            <label>Hausa Name</label>
            <input type="text" name="local_name" value="{{ old('local_name') }}" placeholder="e.g. Dorawa" />
        </div>
        <div class="form-group">
            <label>Botanical Name</label>
            <input type="text" name="botanical_name" value="{{ old('botanical_name') }}" placeholder="e.g. Parkia biglobosa" />
        </div>
    </div>

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-tags"></i><span>Rate and classification</span></div>
        {{-- Both Categories at once, each with its own rate (user, 2026-10-30): saving adds a Mature and an Early row. --}}
        <div class="form-group">
            <label>Rate per Tree – Mature (₦)</label>
            <input type="number" step="0.01" min="0" name="rate_mature"
                   value="{{ old('rate_mature') }}" placeholder="Not set" />
            <p class="helper-text">Leave blank until the rate is approved.</p>
        </div>
        <div class="form-group">
            <label>Rate per Tree – Early (₦)</label>
            <input type="number" step="0.01" min="0" name="rate_early"
                   value="{{ old('rate_early') }}" placeholder="Not set" />
            <p class="helper-text">The tree is added as both Mature and Early.</p>
        </div>
        <div class="form-group">
            <label>Type <span class="required">*</span></label>
            <select name="category">
                @foreach ($categories as $c)
                    <option value="{{ $c }}" @selected(old('category', $tab === 'cash' ? TreeController::CASH_CROP : null) === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Status <span class="required">*</span></label>
            <select name="status">
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(old('status', 'Active') === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="form-actions">
        <div class="left"><button class="btn btn-secondary" type="button" onclick="toggleTreeCatalogueForm()"><i class="fas fa-times"></i> Close</button></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Add Tree Type</button></div>
    </div>
</form>

<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" role="tablist">
    @foreach (TreeController::TABS as $k => $label)
        <a href="{{ route('survey-module.compensation.trees', array_filter(['tab' => $k, 'case' => request('case')])) }}" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}"
           class="btn {{ $tab === $k ? 'btn-primary' : 'btn-outline' }} btn-sm">
            <i class="fas {{ $k === 'cash' ? 'fa-seedling' : 'fa-tree' }}"></i> {{ $label }}
        </a>
    @endforeach
</div>

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
        <div class="kpi-label">No Rate Yet</div>
        <div class="kpi-value">
            <a href="{{ route('survey-module.compensation.trees', ['tab' => $tab, 'rate' => 'unset']) }}" style="color:inherit;">{{ number_format($stats['unrated']) }}</a>
        </div>
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
    <input type="hidden" name="tab" value="{{ $tab }}" />
    @if (request('case'))
        <input type="hidden" name="case" value="{{ request('case') }}" />
    @endif
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, Hausa or botanical…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:220px;" />
        <select name="category" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Types</option>
            @foreach ($tabCategories as $c)
                <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <select name="maturity" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Categories</option>
            @foreach ($maturities as $m)
                <option value="{{ $m }}" @selected(request('maturity') === $m)>{{ $m }}</option>
            @endforeach
        </select>
        <select name="status" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">All Status</option>
            @foreach ($statuses as $s)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>
            @endforeach
        </select>
        <select name="rate" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;">
            <option value="">Any Rate</option>
            <option value="unset" @selected(request('rate') === 'unset')>No rate yet</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['q','category','maturity','status','rate']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.trees', array_filter(['tab' => $tab, 'case' => request('case')])) }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Tree Type</th>
                    <th>Hausa Name</th>
                    <th>Botanical Name</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Rate (₦)</th>
                    <th>Cases Using</th>
                    <th>Lines</th>
                    <th>Total Valued (₦)</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            {{-- One row per tree, folding its Mature and Early rows (user, 2026-10-30). Each tree is its own tbody so
                 the chevron opens just that tree. --}}
            @forelse ($treeNames as $tn)
                @php
                    $rows = $treeTypes->where('name', $tn->name)->values();
                    $head = $rows->first();
                    $statuses = $rows->pluck('status')->unique();
                @endphp
                <tbody x-data="{ open: false }">
                    <tr @click="open = !open" style="cursor:pointer;">
                        <td>{{ $treeNames->firstItem() + $loop->index }}</td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                <button type="button" @click.stop="open = !open" :aria-expanded="open.toString()" aria-label="Show Mature and Early"
                                        class="inline-flex items-center justify-center w-6 h-6 rounded hover:bg-gray-100 text-gray-500">
                                    <i class="fas fa-chevron-right" style="font-size:11px;transition:transform .15s;" :style="open ? 'transform:rotate(90deg)' : ''"></i>
                                </button>
                                <strong>{{ $tn->name }}</strong>
                            </span>
                        </td>
                        <td>{{ $head?->local_name ?: '—' }}</td>
                        <td>@if ($head?->botanical_name)<em>{{ $head->botanical_name }}</em>@else — @endif</td>
                        <td>{{ $head?->category ?: '—' }}</td>
                        <td>{{ $rows->map(fn ($x) => $x->maturity ?: 'Mature')->implode(' · ') }}</td>
                        <td style="white-space:nowrap;font-size:13px;">
                            @foreach ($rows as $x)
                                <div>{{ $x->maturity ?: 'Mature' }}:
                                    @if ($x->default_unit_price === null)<span class="status-badge pending"><span class="dot"></span>Not set</span>@else {{ $rate($x->default_unit_price) }}@endif
                                </div>
                            @endforeach
                        </td>
                        <td>{{ number_format((int) $rows->sum('cases_using')) }}</td>
                        <td>{{ number_format((int) $rows->sum('lines_count')) }}</td>
                        <td>{{ number_format((float) $rows->sum('total_valued'), 2) }}</td>
                        <td>
                            @if ($statuses->count() === 1)
                                <span class="status-badge {{ $statuses->first() === 'Active' ? 'active' : 'rejected' }}"><span class="dot"></span>{{ $statuses->first() }}</span>
                            @else
                                <span class="status-badge pending"><span class="dot"></span>Mixed</span>
                            @endif
                        </td>
                        <td @click.stop>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @foreach ($rows as $x)
                                        <button type="button" class="{{ $svItem }}" @click="open = false; toggleTreeTypeEdit({{ $x->id }})">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> {{ $x->default_unit_price === null ? 'Set Rate / Edit' : 'Edit' }} – {{ $x->maturity ?: 'Mature' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </td>
                    </tr>
                    @foreach ($rows as $t)
                        <tr x-show="open" x-cloak style="background:var(--gray-50,#f9fafb);">
                            <td></td>
                            <td style="padding-left:40px;color:var(--gray-600);">↳ {{ $t->maturity ?: 'Mature' }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>{{ $t->maturity ?: 'Mature' }}</td>
                            <td>
                                @if ($t->default_unit_price === null)
                                    <span class="status-badge pending"><span class="dot"></span>Not set</span>
                                @else
                                    {{ $rate($t->default_unit_price) }}
                                @endif
                            </td>
                            <td>{{ number_format((int) $t->cases_using) }}</td>
                            <td>{{ number_format((int) $t->lines_count) }}</td>
                            <td>{{ number_format((float) $t->total_valued, 2) }}</td>
                            <td>
                                <span class="status-badge {{ $t->status === 'Active' ? 'active' : 'rejected' }}">
                                    <span class="dot"></span>{{ $t->status }}
                                </span>
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
                                        <button type="button" class="{{ $svItem }}" @click="open = false; toggleTreeTypeEdit({{ $t->id }})">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> {{ $t->default_unit_price === null ? 'Set Rate / Edit' : 'Edit' }}
                                        </button>
                                        @if ((int) $t->lines_count === 0)
                                            <form method="POST" action="{{ route('survey-module.compensation.trees.destroy', $t) }}"
                                                  onsubmit="return confirm('Delete {{ addslashes($t->name) }} ({{ $t->maturity ?: 'Mature' }})? This cannot be undone.');">
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
                    @endforeach
                </tbody>
            @empty
                <tbody>
                    <tr>
                        <td colspan="12" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-tree" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            No tree types in the catalogue yet.
                            <a href="#" onclick="toggleTreeCatalogueForm();return false;">Add the first one</a>.
                        </td>
                    </tr>
                </tbody>
            @endforelse
        </table>
    </div>
    <div class="table-footer">
        <span>
            Showing {{ $treeNames->firstItem() ?? 0 }}–{{ $treeNames->lastItem() ?? 0 }}
            of {{ number_format($treeNames->total()) }} trees (each Mature and Early) · Cases Using, Lines and Total Valued
            are read live from case tree lines · Monetary Compensation schemes only
        </span>
        <div class="pagination">{{ $treeNames->links() }}</div>
    </div>
</div>

{{-- Edit / Set Rate, one modal per tree type (2026-10-27: the inline row had no styling). Reopens with the
     typed values when a save comes back with errors. --}}
@foreach ($treeTypes as $t)
    @php $mine = $errors->any() && old('_form') === 'edit-' . $t->id; $v = fn ($f) => $mine ? old($f) : $t->{$f}; @endphp
    <form method="POST" action="{{ route('survey-module.compensation.trees.update', $t) }}"
          class="farmer-entry-form {{ $mine ? 'open' : '' }}" id="ttEdit{{ $t->id }}"
          data-sv-modal="{{ $t->default_unit_price === null ? 'Set Rate' : 'Edit Tree Type' }} — {{ $t->name }} ({{ $t->maturity ?: 'Mature' }})" data-sv-modal-size="sm">
        @csrf @method('PUT')
        <input type="hidden" name="_form" value="edit-{{ $t->id }}" />
        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-tree"></i><span>Names</span></div>
            <div class="form-group full">
                <label>Tree Type <span class="required">*</span></label>
                <input type="text" name="name" value="{{ $v('name') }}" required />
            </div>
            <div class="form-group">
                <label>Hausa Name</label>
                <input type="text" name="local_name" value="{{ $v('local_name') }}" />
            </div>
            <div class="form-group">
                <label>Botanical Name</label>
                <input type="text" name="botanical_name" value="{{ $v('botanical_name') }}" />
            </div>
        </div>
        <div class="form-grid cols-3">
            <div class="sv-group"><i class="fas fa-tags"></i><span>Rate and classification</span></div>
            <div class="form-group">
                <label>Category <span class="required">*</span></label>
                <select name="maturity" required>
                    @foreach ($maturities as $m)
                        <option value="{{ $m }}" @selected(($v('maturity') ?: 'Mature') === $m)>{{ $m }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Rate per Tree (₦)</label>
                <input type="number" step="0.01" min="0" name="default_unit_price" placeholder="Not set"
                       value="{{ $mine ? old('default_unit_price') : ($t->default_unit_price === null ? '' : (float) $t->default_unit_price) }}" />
            </div>
            <div class="form-group">
                <label>Type <span class="required">*</span></label>
                <select name="category">
                    @foreach ($categories as $c)
                        <option value="{{ $c }}" @selected($v('category') === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Status <span class="required">*</span></label>
                <select name="status">
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" @selected($v('status') === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>
            <p class="helper-text full" style="margin:0;">
                Changing the rate affects new case lines only. The {{ number_format((int) $t->lines_count) }} existing
                line(s) keep the price they were valued at.
            </p>
        </div>
        <div class="form-actions">
            <div class="left"><button class="btn btn-secondary" type="button" onclick="toggleTreeTypeEdit({{ $t->id }})"><i class="fas fa-times"></i> Close</button></div>
            <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Save</button></div>
        </div>
    </form>
@endforeach

{{-- ------------------------------ Per-case lines ------------------------------ --}}
<div class="dash-card" style="margin-top:24px;">
    <div class="card-header">
        <h3 style="margin:0;font-size:16px;">Case Tree Lines</h3>
    </div>

    <form method="GET" class="table-toolbar" style="border:0;">
        @foreach (['tab','q','category','maturity','status'] as $keep)
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
            Choose a case to record its economic trees. Only <strong>Monetary Compensation</strong> cases are listed —
            Land to Land cases are compensated with plots, not cash, and the server refuses tree lines on them.
        </div>
    @elseif (! $selectedCase->isMonetary())
        <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;">
            <i class="fas fa-exclamation-triangle"></i>
            {{ $selectedCase->case_ref }} is a <strong>Land to Land</strong> case. Economic trees are monetary-only;
            allocate plots in <a href="{{ route('survey-module.tools.plot-allocation', ['case' => $selectedCase->id]) }}">Plot Allocation</a> instead.
        </div>
    @else
        <form method="POST" action="{{ route('survey-module.compensation.trees.case.store', $selectedCase) }}"
              style="margin-bottom:16px;">
            @csrf
            <div class="form-grid">
                {{-- Category first (user, 2026-10-30): it narrows Tree Type to that Category's catalogue rows. --}}
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="maturity" id="ctlMaturity" required onchange="filterCaseTreeTypes()">
                        @foreach ($maturities as $m)
                            <option value="{{ $m }}" @selected(old('maturity', 'Mature') === $m)>{{ $m }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Tree Type <span class="required">*</span></label>
                    <select name="survey_tree_type_id" id="ctlType" required onchange="prefillCaseTreePrice()">
                        <option value="">Select from catalogue…</option>
                        @foreach ($activeTypes as $a)
                            <option value="{{ $a->id }}" data-maturity="{{ $a->maturity ?: 'Mature' }}" data-price="{{ $a->default_unit_price === null ? '' : (float) $a->default_unit_price }}"
                                    @selected((string) old('survey_tree_type_id') === (string) $a->id)>
                                {{ $a->name }}{{ $a->local_name ? ' / ' . $a->local_name : '' }} ({{ $a->category }}){{ $a->default_unit_price === null ? ' — rate not set' : '' }}
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
                            <th>S/N</th>
                            <th>Tree Type</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit Price (₦)</th>
                            <th>Line Total (₦)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedCase->trees as $line)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $line->tree_type }}</strong></td>
                                <td>{{ $line->maturity ?: 'Mature' }}</td>
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
                                <td colspan="7" style="text-align:center;padding:24px;color:var(--gray-500);">
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
        var f = document.getElementById('ttEdit' + id);
        if (f) f.classList.toggle('open');
    }

    // Offer only the catalogue rows of the chosen Category; a pick from the other one is cleared.
    // Options are removed rather than hidden: the searchable (select2) list ignores "hidden".
    var caseTreeOptions = null;
    function filterCaseTreeTypes() {
        var cat = document.getElementById('ctlMaturity'), sel = document.getElementById('ctlType');
        if (!cat || !sel) return;
        if (!caseTreeOptions) caseTreeOptions = Array.prototype.filter.call(sel.options, function (o) { return o.value; });
        var picked = sel.value;
        caseTreeOptions.forEach(function (o) { if (o.parentNode) sel.removeChild(o); });
        caseTreeOptions.forEach(function (o) { if (o.dataset.maturity === cat.value) sel.appendChild(o); });
        sel.value = caseTreeOptions.some(function (o) { return o.value === picked && o.dataset.maturity === cat.value; }) ? picked : '';
        if (sel.value !== picked) prefillCaseTreePrice();
        if (window.jQuery && jQuery(sel).data('select2')) jQuery(sel).trigger('change.select2');
    }
    document.addEventListener('DOMContentLoaded', filterCaseTreeTypes);

    function prefillCaseTreePrice() {
        var sel = document.getElementById('ctlType');
        var price = document.getElementById('ctlPrice');
        if (!sel || !price) return;
        var opt = sel.options[sel.selectedIndex];
        var v = opt ? opt.dataset.price : '';
        price.value = v || '';
        previewCaseTreeLine();
    }

    function previewCaseTreeLine() {
        var qty = parseInt(document.getElementById('ctlQty')?.value, 10) || 0;
        var price = parseFloat(document.getElementById('ctlPrice')?.value) || 0;
        var out = document.getElementById('ctlPreview');
        if (out) out.value = '₦' + (qty * price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
</script>
