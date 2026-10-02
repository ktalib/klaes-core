@php
    $caseId    = $case?->id;
    $qs        = array_filter(['case' => request('case'), 'q' => request('q')], fn ($v) => $v !== null && $v !== '');
    // The two 50% columns are the surveyor's field figures; the register's own
    // split comes from the row count. Flag it when the two disagree.
    $mismatch  = $totals['total'] > 0
                 && ((float) $entered['govt'] !== (float) $totals['govt'] || (float) $entered['farmer'] !== (float) $totals['farmer']);
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <button type="button" class="btn btn-primary btn-sm" onclick="togglePlotFarmerForm()">
            <i class="fas fa-user-plus"></i> Add Farmer
        </button>
        <button type="submit" form="paAddRowForm" class="btn btn-secondary btn-sm">
            <i class="fas fa-plus"></i> Add Plot
        </button>
        <a class="btn btn-outline btn-sm" href="{{ route('survey-module.tools.plot-allocation.export', $qs) }}">
            <i class="fas fa-file-export"></i> Export CSV
        </a>
    </div>
</div>

<div class="comp-type-note" style="margin-bottom:16px;">
    <i class="fas fa-map-marked-alt"></i>
    Plot allocation applies to <strong>Land-for-Land</strong> cases only — only those cases are listed below,
    and the server refuses rows filed against a Monetary (cash for trees) case. One row is one plot; the
    <strong>50:50 split is taken from the row count</strong>, the same source
    <code>SurveyCompCase::plotSplit()</code> uses.
</div>

<form method="GET" class="table-toolbar">
    <div class="left">
        <select name="case" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;min-width:260px;">
            <option value="">All Land-for-Land cases</option>
            @foreach ($landCases as $c)
                <option value="{{ $c->id }}" @selected($caseId === $c->id)>
                    {{ $c->case_ref }} — {{ $c->plots_count }} plot row(s)
                </option>
            @endforeach
        </select>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search farmer, F. No., plot or OP…"
               style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;width:250px;" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny(['case','q']))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.tools.plot-allocation') }}">Clear</a>
        @endif
    </div>
</form>

@if (request('case') && ! $case)
    <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;">
        <i class="fas fa-exclamation-triangle"></i> That case could not be found; showing the whole register.
    </div>
@endif

{{-- "Add Plot": a continuation row under whoever is already on the register. --}}
<form method="POST" action="{{ route('survey-module.tools.plot-allocation.add-row', $qs) }}" id="paAddRowForm">
    @csrf
    <input type="hidden" name="survey_comp_case_id" value="{{ $caseId }}" />
</form>

{{-- Farmer entry: identity plus the surveyor's own 50% field figures. --}}
<form method="POST" action="{{ route('survey-module.tools.plot-allocation.store', $qs) }}"
      class="farmer-entry-form {{ $errors->any() ? 'open' : '' }}" id="paFarmerForm">
    @csrf
    <input type="hidden" name="survey_comp_case_id" value="{{ $caseId }}" />
    <div class="form-row">
        <div>
            <label>F. No. <span class="required">*</span></label>
            <input type="text" name="f_no" value="{{ old('f_no') }}" placeholder="e.g. F-001" />
        </div>
        <div>
            <label>Farmers Name <span class="required">*</span></label>
            <input type="text" name="farmer_name" value="{{ old('farmer_name') }}" placeholder="e.g. Mr. X" />
        </div>
        <div>
            <label>Acreage</label>
            <input type="number" step="0.01" min="0" name="acreage" value="{{ old('acreage') }}" placeholder="e.g. 2.50" />
        </div>
        <div>
            <label>Creetable</label>
            <select name="creetable">
                <option value="Yes" @selected(old('creetable', 'Yes') === 'Yes')>Yes</option>
                <option value="No" @selected(old('creetable') === 'No')>No</option>
            </select>
        </div>
        <div>
            <label>Govt. 50%</label>
            <input type="number" step="0.01" min="0" name="govt_50" value="{{ old('govt_50') }}" placeholder="e.g. 3" />
        </div>
        <div>
            <label>Farmer 50%</label>
            <input type="number" step="0.01" min="0" name="farmer_50" value="{{ old('farmer_50') }}" placeholder="e.g. 3" />
        </div>
    </div>
    <div class="form-row">
        <div>
            <label>Plot No. <span class="required">*</span></label>
            <input type="text" name="plot_no" value="{{ old('plot_no') }}" placeholder="e.g. 1" />
        </div>
        <div>
            <label>OP No.</label>
            <input type="text" name="op_no" value="{{ old('op_no') }}" placeholder="e.g. OP-001" />
        </div>
        <div>
            <label>Sign</label>
            <input type="text" name="sign" value="{{ old('sign') }}" placeholder="e.g. Signature" />
        </div>
        <div>
            <label>Remarks</label>
            <input type="text" name="remarks" value="{{ old('remarks') }}" placeholder="Additional remarks" />
        </div>
        <div style="display:flex;align-items:end;gap:6px;">
            <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> Save</button>
            <button class="btn btn-secondary btn-sm" type="button" onclick="togglePlotFarmerForm()"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <p class="helper-text" style="margin-top:10px;">
        Govt. 50% and Farmer 50% are recorded as entered. The register's totals below are still
        derived from the number of plot rows, which is the authoritative split.
    </p>
</form>

{{-- Wide register: horizontal scroll with a sticky Action column. --}}
<div class="farm-table-wrapper">
    <div class="scroll-indicator">
        <i class="fas fa-arrows-alt-h"></i> Scroll horizontally to reach every column — the Action column stays pinned.
    </div>

    <div class="farm-table-scroll">
        <table class="farm-data-table">
            <thead>
                <tr>
                    <th class="col-sr">SR</th>
                    <th class="col-fno">F. No.</th>
                    <th class="col-name">Farmers Name</th>
                    <th class="col-acreage">Acreage</th>
                    <th class="col-creetable">Creetable</th>
                    <th class="col-govt">Govt. 50%</th>
                    <th class="col-farmer">Farmer 50%</th>
                    <th class="col-plotno">Plot No.</th>
                    <th class="col-opno">OP No.</th>
                    <th class="col-sign">Sign</th>
                    <th class="col-remarks">Remarks</th>
                    <th class="col-action">Action</th>
                </tr>
            </thead>
            <tbody id="plotRegisterBody">
                @php $prevFarmer = null; @endphp
                @forelse ($rows as $row)
                    @php
                        // Continuation rows repeat the farmer above them, exactly as the
                        // paper register does; the prototype drew them with an arrow.
                        $isHead     = $row->farmer_name && $row->farmer_name !== $prevFarmer;
                        $prevFarmer = $row->farmer_name ?: $prevFarmer;
                        $muted      = 'color:var(--gray-400);font-weight:300;';
                    @endphp
                    <tr>
                        <td class="col-sr">{{ $row->sr }}</td>
                        <td class="col-fno" @if (! $isHead) style="{{ $muted }}" @endif>{{ $isHead ? $row->f_no : '↳' }}</td>
                        <td class="col-name text-left" @if (! $isHead) style="{{ $muted }}" @endif>{{ $isHead ? $row->farmer_name : '↳' }}</td>
                        <td class="col-acreage" @if (! $isHead) style="{{ $muted }}" @endif>{{ $isHead ? $row->acreage : '↳' }}</td>
                        <td class="col-creetable" @if (! $isHead) style="{{ $muted }}" @endif>{{ $isHead ? $row->creetable : '↳' }}</td>
                        <td class="col-govt" @if (! $isHead) style="{{ $muted }}" @endif>{{ $isHead ? $row->govt_50 : '↳' }}</td>
                        <td class="col-farmer" @if (! $isHead) style="{{ $muted }}" @endif>{{ $isHead ? $row->farmer_50 : '↳' }}</td>
                        <td class="col-plotno">
                            <input type="text" class="form-control form-control-sm" name="plot_no"
                                   form="paRow{{ $row->id }}" value="{{ $row->plot_no }}" />
                        </td>
                        <td class="col-opno">
                            <input type="text" class="form-control form-control-sm" name="op_no"
                                   form="paRow{{ $row->id }}" value="{{ $row->op_no }}" />
                        </td>
                        <td class="col-sign">
                            <input type="text" class="form-control form-control-sm" name="sign"
                                   form="paRow{{ $row->id }}" value="{{ $row->sign }}" style="max-width:60px;" />
                        </td>
                        <td class="col-remarks">
                            <input type="text" class="form-control form-control-sm" name="remarks"
                                   form="paRow{{ $row->id }}" value="{{ $row->remarks }}" />
                        </td>
                        <td class="col-action">
                            <div style="display:flex;gap:4px;justify-content:center;">
                                {{-- The four editable cells above post through this form via their form= attribute. --}}
                                <form method="POST" action="{{ route('survey-module.tools.plot-allocation.update', [$row, ...$qs]) }}"
                                      id="paRow{{ $row->id }}" style="display:inline;">
                                    @csrf @method('PUT')
                                    <button class="btn btn-success btn-xs" type="submit" title="Save this row">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('survey-module.tools.plot-allocation.destroy', [$row, ...$qs]) }}"
                                      style="display:inline;"
                                      onsubmit="return confirm('Delete SR {{ $row->sr }}? The register will be renumbered.');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-xs" type="submit" title="Delete row">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" style="text-align:center;padding:24px;color:var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            No plot rows {{ $case ? 'on ' . $case->case_ref : 'in the register' }} yet.
                            <a href="#" onclick="togglePlotFarmerForm();return false;">Add a farmer</a> to start.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-actions">
        <div class="action-buttons">
            <button type="button" class="btn btn-secondary btn-sm" onclick="togglePlotFarmerForm()">
                <i class="fas fa-user-plus"></i> Add Farmer
            </button>
            <button type="submit" form="paAddRowForm" class="btn btn-secondary btn-sm">
                <i class="fas fa-plus"></i> Add Plot
            </button>
        </div>
        <div class="totals-display">
            <span><strong>Total Plots:</strong> {{ number_format($totals['total']) }}</span>
            <span><strong>Govt. 50%:</strong> {{ number_format($totals['govt']) }}</span>
            <span><strong>Farmer 50%:</strong> {{ number_format($totals['farmer']) }}</span>
        </div>
    </div>

    <div class="table-footer">
        <span>
            Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }}
            of {{ number_format($rows->total()) }} plot row(s){{ $case ? ' on ' . $case->case_ref : '' }} ·
            totals are derived from the row count, not from the two 50% columns
            @if ($mismatch)
                · <strong style="color:var(--danger-dark);">entered 50% columns sum to
                {{ rtrim(rtrim(number_format($entered['govt'], 2), '0'), '.') }} govt /
                {{ rtrim(rtrim(number_format($entered['farmer'], 2), '0'), '.') }} farmer</strong>
            @endif
        </span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>

<script>
    // Own names: _scripts.blade.php defines toggleFarmerEntryForm()/renderFarmTable()
    // against the prototype's sessionStorage demo data and its #farmTableBody id.
    function togglePlotFarmerForm() {
        var f = document.getElementById('paFarmerForm');
        if (!f) return;
        f.classList.toggle('open');
        if (f.classList.contains('open')) {
            var first = f.querySelector('input[name="f_no"]');
            if (first) first.focus();
        }
    }
</script>
