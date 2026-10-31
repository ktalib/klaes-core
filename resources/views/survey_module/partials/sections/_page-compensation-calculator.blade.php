@php
    $activeMode  = $result['mode'] ?? $mode;
    $oldLines    = old('lines', [['survey_tree_type_id' => '', 'quantity' => 1, 'unit_price' => '']]);
    // Each catalogue row is one tree in one Category (Mature / Early) with its own rate (2026-10-30).
    $priceMap    = $treeTypes->mapWithKeys(fn ($t) => [$t->id => $t->default_unit_price === null ? null : (float) $t->default_unit_price]);
    $maturityMap = $treeTypes->mapWithKeys(fn ($t) => [$t->id => $t->maturity ?: 'Mature']);
    // Tree Type lists each tree once; the Category picked (Mature / Early) chooses its row and rate (user, 2026-10-30).
    $treeRows  = $treeTypes->groupBy('name')->map(fn ($g) => $g->mapWithKeys(fn ($t) => [$t->maturity ?: 'Mature' => $t->id]));
    $treeNames = $treeTypes->unique('name')->values();
    $rowName   = $treeTypes->mapWithKeys(fn ($t) => [$t->id => $t->name]);
    $maturities = \App\Models\Survey\SurveyTreeType::MATURITIES;
@endphp

@include('survey_module.partials._flash')

{{-- The calculator is driven by a project (user transcript, 2026-10-25): pick one to see its schedule. --}}
<form method="GET" action="{{ route('survey-module.compensation.calculator') }}" class="table-toolbar" style="margin-bottom:16px;">
    <div class="left" style="flex-wrap:wrap;">
        <select name="project" onchange="this.form.submit()" style="padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;min-width:320px;">
            <option value="">— Choose a project —</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected($project?->id === $p->id)>
                    {{ $p->project_code }} · {{ $p->name }} ({{ $p->scheme_label }})
                </option>
            @endforeach
        </select>
        <noscript><button class="btn btn-secondary" type="submit">Show</button></noscript>
        @if ($project)
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.compensation.calculator') }}">Clear</a>
        @endif
    </div>
</form>

<div class="comp-type-grid" style="margin-bottom:24px;">
    <label class="comp-type-option {{ $activeMode === 'monetary' ? 'selected' : '' }}"
           id="ccOptMon" onclick="ccSelectMode('monetary')">
        <div class="type-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="type-title">Monetary Compensation Calculator</div>
        <div class="type-desc">Cash for economic trees · Tree × Qty × Unit Price</div>
    </label>
    <label class="comp-type-option {{ $activeMode === 'land' ? 'selected' : '' }}"
           id="ccOptLand" onclick="ccSelectMode('land')">
        <div class="type-icon"><i class="fas fa-map-marked-alt"></i></div>
        <div class="type-title">Land to Land Calculator</div>
        <div class="type-desc">50:50 plot split · Farmer : Government</div>
    </label>
</div>

<div class="comp-type-note" style="margin-bottom:20px;">
    <i class="fas fa-info-circle"></i>
    The two modes are <strong>mutually exclusive</strong> — a case is compensated in cash
    <em>or</em> in plots, never both. Press <strong>Calculate</strong> to work out the figures from the
    tree rates; the running preview while you type is only a guide.
</div>

{{-- ------------------------------- Monetary Compensation ------------------------------- --}}
<div id="ccMon" style="{{ $activeMode === 'monetary' ? '' : 'display:none;' }}">
    @include('survey_module.partials.sections._calculator-schedule', ['for' => 'trees'])
    @if ($treeTypes->isEmpty())
        <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;">
            <i class="fas fa-exclamation-triangle"></i>
            The tree catalogue is empty, so there is nothing to price.
            <a href="{{ route('survey-module.compensation.trees') }}">Add tree types</a> first.
        </div>
    @else
        <form method="POST" action="{{ route('survey-module.compensation.calculator.run') }}">
            @csrf
            <input type="hidden" name="mode" value="monetary" />

            <div class="dash-card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3 style="margin:0;font-size:16px;">Tree Lines</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="ccAddLine()">
                        <i class="fas fa-plus"></i> Add Line
                    </button>
                </div>

                <div id="ccLines">
                    @foreach ($oldLines as $i => $line)
                        <div class="cc-line" data-cc-line>
                            <div class="form-grid" style="margin-bottom:0;">
                                <div class="form-group">
                                    <label>Tree Type <span class="required">*</span></label>
                                    @php $pickedId = (string) ($line['survey_tree_type_id'] ?? ''); @endphp
                                    <input type="hidden" name="lines[{{ $i }}][survey_tree_type_id]" value="{{ $pickedId }}" data-cc-type />
                                    <select data-cc-name onchange="ccPrefill(this)">
                                        <option value="">Select from catalogue…</option>
                                        @foreach ($treeNames as $t)
                                            <option value="{{ $t->name }}" @selected(($rowName[$pickedId] ?? null) === $t->name)>{{ $t->name }} ({{ $t->category }})</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Category <span class="required">*</span></label>
                                    <select data-cc-maturity onchange="ccPrefill(this)">
                                        @foreach ($maturities as $m)
                                            <option value="{{ $m }}" @selected(($maturityMap[$pickedId] ?? 'Mature') === $m)>{{ $m }}</option>
                                        @endforeach
                                    </select>
                                    <p class="helper-text" data-cc-rate></p>
                                </div>
                                <div class="form-group">
                                    <label>Quantity <span class="required">*</span></label>
                                    <input type="number" min="1" name="lines[{{ $i }}][quantity]"
                                           value="{{ $line['quantity'] ?? 1 }}" data-cc-qty oninput="ccPreview()" />
                                </div>
                                <div class="form-group">
                                    <label>Unit Price (₦)</label>
                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]"
                                           value="{{ $line['unit_price'] ?? '' }}" data-cc-price oninput="ccPreview()" />
                                    <p class="helper-text">Blank uses the catalogue price.</p>
                                </div>
                                <div class="form-group">
                                    <label>Line Total</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="text" data-cc-line-total value="₦0.00" readonly
                                               style="font-weight:700;color:var(--primary);background:var(--gray-100);" />
                                        <button type="button" class="btn btn-danger btn-sm" onclick="ccRemoveLine(this)"
                                                title="Remove line"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="dash-card" style="margin-bottom:20px;">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Beneficiary count from case</label>
                        <select name="survey_comp_case_id" onchange="ccCasePicked(this)">
                            <option value="">None — enter a count below</option>
                            @foreach ($cases as $c)
                                <option value="{{ $c->id }}" data-beneficiaries="{{ $c->beneficiaries_count }}"
                                    @selected((string) old('survey_comp_case_id') === (string) $c->id)>
                                    {{ $c->case_ref }} ({{ $c->beneficiaries_count }} beneficiaries)
                                </option>
                            @endforeach
                        </select>
                        <p class="helper-text">A real, registered count — it always wins over the manual figure.</p>
                    </div>
                    <div class="form-group">
                        <label>…or beneficiaries (manual)</label>
                        <input type="number" min="1" name="beneficiaries" id="ccBenManual"
                               value="{{ old('beneficiaries') }}" placeholder="e.g. 3" oninput="ccPreview()" />
                    </div>
                </div>
            </div>

            <div class="calc-grid">
                <div class="calc-card">
                    <div class="label">Estimated Cash Total</div>
                    <div class="amount" id="ccTotalPreview">₦0.00</div>
                    <div class="detail">Live preview — press Calculate for the verified figure</div>
                </div>
                <div class="calc-card">
                    <div class="label">Beneficiaries</div>
                    <div class="amount" id="ccBenPreview">—</div>
                    <div class="detail">From the selected case, or entered manually</div>
                </div>
                <div class="calc-card">
                    <div class="label">Avg per Beneficiary</div>
                    <div class="amount" id="ccAvgPreview">—</div>
                    <div class="detail">If split equally</div>
                </div>
            </div>

            <div class="form-actions" style="margin-top:16px;">
                <div class="left"></div>
                <div class="right">
                    <button class="btn btn-success" type="submit"><i class="fas fa-calculator"></i> Calculate</button>
                </div>
            </div>
        </form>
    @endif

    @if (($result['mode'] ?? null) === 'monetary')
        <div class="dash-card" style="margin-top:24px;">
            <div class="card-header">
                <h3 style="margin:0;font-size:16px;">
                    Server Estimate{{ $result['case_ref'] ? ' · ' . $result['case_ref'] : '' }}
                </h3>
            </div>
            <div class="table-wrapper">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>S/N</th>
                                <th>Tree Type</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Quantity</th>
                                <th>Unit Price (₦)</th>
                                <th>Line Total (₦)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['lines'] as $l)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $l['tree_type'] }}</strong></td>
                                    <td>{{ $l['category'] ?: '—' }}</td>
                                    <td>{{ $l['maturity'] ?? 'Mature' }}</td>
                                    <td>{{ number_format($l['quantity']) }}</td>
                                    <td>
                                        {{ number_format($l['unit_price'], 2) }}
                                        @if ($l['overridden'])
                                            <span class="status-badge pending" style="margin-left:6px;"><span class="dot"></span>Override</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($l['line_total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span>{{ count($result['lines']) }} line(s) · beneficiary count {{ $result['beneficiary_source'] }}</span>
                </div>
            </div>

            <div class="calc-grid" style="margin-top:16px;">
                <div class="calc-card">
                    <div class="label">Estimated Cash Total</div>
                    <div class="amount">₦{{ number_format($result['total'], 2) }}</div>
                    <div class="detail">Sum of all tree lines, computed server-side</div>
                </div>
                <div class="calc-card">
                    <div class="label">Beneficiaries</div>
                    <div class="amount">{{ $result['beneficiaries'] ?: '—' }}</div>
                    <div class="detail">{{ $result['beneficiary_source'] }}</div>
                </div>
                <div class="calc-card">
                    <div class="label">Avg per Beneficiary</div>
                    <div class="amount">
                        {{ $result['avg_per_beneficiary'] !== null ? '₦' . number_format($result['avg_per_beneficiary'], 2) : '—' }}
                    </div>
                    <div class="detail">
                        {{ $result['avg_per_beneficiary'] !== null ? 'If split equally' : 'Select a case or enter a count' }}
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

{{-- ----------------------------- Land to Land ----------------------------- --}}
<div id="ccLand" style="{{ $activeMode === 'land' ? '' : 'display:none;' }}">
    @include('survey_module.partials.sections._calculator-schedule', ['for' => 'plots'])
    <form method="POST" action="{{ route('survey-module.compensation.calculator.run') }}">
        @csrf
        <input type="hidden" name="mode" value="land" />

        <div class="dash-card" style="margin-bottom:20px;">
            <div class="form-grid">
                <div class="form-group">
                    <label>Land to Land case (optional)</label>
                    <select name="survey_comp_case_id" onchange="ccLandCasePicked(this)">
                        <option value="">None — ad-hoc estimate</option>
                        @foreach ($cases->where('scheme_type', '!=', $schemeMonetary) as $c)
                            <option value="{{ $c->id }}" data-plots="{{ (int) $c->num_plots }}"
                                    data-beneficiaries="{{ $c->beneficiaries_count }}"
                                @selected((string) old('survey_comp_case_id') === (string) $c->id)>
                                {{ $c->case_ref }} ({{ (int) $c->num_plots }} plots declared)
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Creatable Plots <span class="required">*</span></label>
                    <input type="number" min="1" name="total_plots" id="ccPlots"
                           value="{{ old('total_plots', 10) }}" oninput="ccLandPreview()" />
                </div>
                <div class="form-group">
                    <label>Odd-plot rule</label>
                    <select name="odd_rule" id="ccOddRule" onchange="ccLandPreview()">
                        <option value="govt" @selected(old('odd_rule', 'govt') === 'govt')>Extra plot → Government (system rule)</option>
                        <option value="farmer" @selected(old('odd_rule') === 'farmer')>Extra plot → Farmer</option>
                    </select>
                    <p class="helper-text">Only matters when the plot count is odd. Government is the standing rule.</p>
                </div>
                <div class="form-group">
                    <label>Farmers sharing the allocation</label>
                    <input type="number" min="1" name="beneficiaries" id="ccLandBen"
                           value="{{ old('beneficiaries') }}" placeholder="e.g. 4" oninput="ccLandPreview()" />
                    <p class="helper-text">A selected case supplies its own registered count instead.</p>
                </div>
            </div>
        </div>

        <div class="calc-ratio">
            <div class="ratio-item">
                <div class="num" id="ccFarmerPlots">5</div>
                <div class="label">Farmer Plots</div>
            </div>
            <div class="ratio-divider">:</div>
            <div class="ratio-item">
                <div class="num" id="ccGovtPlots">5</div>
                <div class="label">Government Plots</div>
            </div>
        </div>

        <div class="comp-type-note" style="margin-top:16px;">
            <i class="fas fa-info-circle"></i>
            Land to Land schemes have <strong>no cash component</strong>. Assign plot numbers and OP numbers in
            <a href="{{ route('survey-module.tools.plot-allocation') }}">Plot Allocation</a> after case approval.
        </div>

        <div class="form-actions" style="margin-top:16px;">
            <div class="left"></div>
            <div class="right">
                <button class="btn btn-success" type="submit"><i class="fas fa-calculator"></i> Calculate</button>
            </div>
        </div>
    </form>

    @if (($result['mode'] ?? null) === 'land')
        <div class="dash-card" style="margin-top:24px;">
            <div class="card-header">
                <h3 style="margin:0;font-size:16px;">
                    Server Split{{ $result['case_ref'] ? ' · ' . $result['case_ref'] : '' }}
                </h3>
            </div>
            <div class="calc-grid">
                <div class="calc-card">
                    <div class="label">Total Plots</div>
                    <div class="amount">{{ number_format($result['total_plots']) }}</div>
                    <div class="detail">Carved for this scheme</div>
                </div>
                <div class="calc-card">
                    <div class="label">Government Plots</div>
                    <div class="amount">{{ number_format($result['govt_plots']) }}</div>
                    <div class="detail">
                        @if ($result['odd_plot'])
                            Odd plot {{ $result['odd_rule'] === 'govt' ? 'included' : 'passed to the farmer' }}
                        @else
                            Exact half
                        @endif
                    </div>
                </div>
                <div class="calc-card">
                    <div class="label">Farmer Plots</div>
                    <div class="amount">{{ number_format($result['farmer_plots']) }}</div>
                    <div class="detail">
                        {{ $result['plots_per_farmer'] !== null
                            ? number_format($result['plots_per_farmer'], 2) . ' per farmer (' . $result['beneficiary_source'] . ')'
                            : 'Enter a farmer count for a per-farmer figure' }}
                    </div>
                </div>
            </div>
            <div class="comp-type-note" style="margin-top:16px;">
                <i class="fas fa-balance-scale"></i>
                Rule applied: extra plot → <strong>{{ $result['odd_rule'] === 'govt' ? 'Government' : 'Farmer' }}</strong>
                @unless ($result['is_default_rule'])
                    — this overrides the system-wide default (Government).
                @endunless
            </div>
        </div>
    @endif

    {{-- The Resettlement Calculator sits under the Land to Land calculator (CR 30102026 item 10); its own page is unchanged. --}}
    <div class="dash-card" style="margin-top:24px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <h3 style="margin:0 0 4px;font-size:16px;"><i class="fas fa-calculator" style="color:var(--primary);"></i> Resettlement Calculator</h3>
            <div style="font-size:13px;color:var(--gray-500);">Plots = area (ha) ÷ plot size for the land use and density, with the 50:50 split on Land to Land lands. Plot sizes are managed there.</div>
        </div>
        <a href="{{ route('survey-module.compensation.resettlement-calculator') }}" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Open Resettlement Calculator</a>
    </div>
</div>

<script>
    // Distinct names: _scripts.blade.php already defines runCashCalc()/runLandCalc()
    // against the prototype's demo ids, and is loaded after this partial.
    var ccPrices = @json($priceMap);
    var ccMaturity = @json($maturityMap);
    var ccRows = @json($treeRows);   // tree name => { Mature: id, Early: id }

    function ccSelectMode(mode) {
        document.getElementById('ccMon').style.display = mode === 'monetary' ? 'block' : 'none';
        document.getElementById('ccLand').style.display = mode === 'land' ? 'block' : 'none';
        document.getElementById('ccOptMon').classList.toggle('selected', mode === 'monetary');
        document.getElementById('ccOptLand').classList.toggle('selected', mode === 'land');
    }

    function ccRenumber() {
        document.querySelectorAll('#ccLines [data-cc-line]').forEach(function (row, i) {
            row.querySelector('[data-cc-type]').name = 'lines[' + i + '][survey_tree_type_id]';
            row.querySelector('[data-cc-qty]').name = 'lines[' + i + '][quantity]';
            row.querySelector('[data-cc-price]').name = 'lines[' + i + '][unit_price]';
        });
    }

    function ccAddLine() {
        var rows = document.querySelectorAll('#ccLines [data-cc-line]');
        if (!rows.length) return;
        var clone = rows[0].cloneNode(true);
        if (window.svSearchable) svSearchable.cleanClone(clone);   // the tree list is a search box
        clone.querySelector('[data-cc-name]').selectedIndex = 0;
        clone.querySelector('[data-cc-type]').value = '';
        clone.querySelector('[data-cc-maturity]').value = 'Mature';
        clone.querySelector('[data-cc-qty]').value = 1;
        clone.querySelector('[data-cc-price]').value = '';
        document.getElementById('ccLines').appendChild(clone);
        if (window.svSearchable) svSearchable(clone);
        ccRenumber();
        ccPreview();
    }

    function ccRemoveLine(btn) {
        var rows = document.querySelectorAll('#ccLines [data-cc-line]');
        if (rows.length <= 1) return;               // an estimate needs at least one line
        btn.closest('[data-cc-line]').remove();
        ccRenumber();
        ccPreview();
    }

    // The catalogue rate of the line's tree row (a tree in one Category).
    function ccRate(row) {
        var id = row.querySelector('[data-cc-type]').value;
        return id && ccPrices[id] !== undefined ? ccPrices[id] : null;
    }

    // Tree + Category -> that catalogue row (hidden id) and its rate.
    function ccPrefill(select) {
        var row = select.closest('[data-cc-line]');
        var name = row.querySelector('[data-cc-name]').value, cat = row.querySelector('[data-cc-maturity]').value;
        row.querySelector('[data-cc-type]').value = name && ccRows[name] && ccRows[name][cat] ? ccRows[name][cat] : '';
        var r = ccRate(row);
        row.querySelector('[data-cc-price]').value = r == null ? '' : r;
        ccPreview();
    }

    function ccMoney(n) {
        return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function ccPreview() {
        var total = 0;
        document.querySelectorAll('#ccLines [data-cc-line]').forEach(function (row) {
            var sel = row.querySelector('[data-cc-type]');
            var id = sel.value;
            var qty = parseInt(row.querySelector('[data-cc-qty]').value, 10) || 0;
            var priceEl = row.querySelector('[data-cc-price]');
            var rate = ccRate(row);
            var cat = row.querySelector('[data-cc-maturity]').value, name = row.querySelector('[data-cc-name]').value;
            var note = row.querySelector('[data-cc-rate]');
            if (note) note.textContent = id ? (rate == null ? cat + ' rate not set' : cat + ' rate ' + ccMoney(rate))
                : (name ? name + ' has no ' + cat + ' entry in the catalogue' : '');
            var price = priceEl.value !== '' ? parseFloat(priceEl.value) : (rate || 0);
            var line = qty * (price || 0);
            total += line;
            row.querySelector('[data-cc-line-total]').value = ccMoney(line);
        });

        var el = document.getElementById('ccTotalPreview');
        if (el) el.textContent = ccMoney(total);

        var ben = parseInt(document.getElementById('ccBenManual')?.value, 10) || 0;
        var benEl = document.getElementById('ccBenPreview');
        var avgEl = document.getElementById('ccAvgPreview');
        if (benEl) benEl.textContent = ben > 0 ? ben : '—';
        if (avgEl) avgEl.textContent = ben > 0 ? ccMoney(total / ben) : '—';
    }

    function ccCasePicked(select) {
        var opt = select.options[select.selectedIndex];
        var manual = document.getElementById('ccBenManual');
        if (opt && opt.value && manual) manual.value = opt.dataset.beneficiaries || '';
        ccPreview();
    }

    function ccLandCasePicked(select) {
        var opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return;
        var plots = document.getElementById('ccPlots');
        var ben = document.getElementById('ccLandBen');
        if (plots && opt.dataset.plots && opt.dataset.plots !== '0') plots.value = opt.dataset.plots;
        if (ben) ben.value = opt.dataset.beneficiaries || '';
        ccLandPreview();
    }

    function ccLandPreview() {
        var plots = parseInt(document.getElementById('ccPlots')?.value, 10) || 0;
        var rule = document.getElementById('ccOddRule')?.value || 'govt';
        var govt = rule === 'farmer' ? Math.floor(plots / 2) : Math.ceil(plots / 2);
        var farmer = plots - govt;
        var f = document.getElementById('ccFarmerPlots');
        var g = document.getElementById('ccGovtPlots');
        if (f) f.textContent = farmer;
        if (g) g.textContent = govt;
    }

    document.addEventListener('DOMContentLoaded', function () {
        ccPreview();
        ccLandPreview();
    });
</script>
