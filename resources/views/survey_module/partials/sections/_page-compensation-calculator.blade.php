@php
    $activeMode = $result['mode'] ?? $mode;
    // Monetary (Kano schedule, user 2026-11-01): one line per cash crop / economic tree in a Category, with the
    // land under it. A catalogue row is one item in one Category (Mature / Immature / Seedling).
    $blankLine  = ['survey_tree_type_id' => '', 'quantity' => '', 'quantity_unit' => 'sqm', 'unit_price' => '', 'land_size' => '', 'land_unit' => 'hectare', 'land_rate' => ''];
    $oldLines   = old('lines', [$blankLine]);
    $maturities = \App\Models\Survey\SurveyTreeType::MATURITIES;
    $cashCrop   = \App\Models\Survey\SurveyTreeType::CASH_CROP;
    // "Type|Name" => {type, name, cost, rows: {Mature: {id, rate, rate_ha}, ...}}; a name can be in both lists.
    $ccItems = $treeTypes->groupBy(fn ($t) => $t->category . '|' . $t->name)->map(function ($g) {
        $h = $g->first();
        return ['type' => $h->category, 'name' => $h->name, 'local' => $h->local_name,
            'cost' => $h->cost_per_ha === null ? null : (float) $h->cost_per_ha,
            'rows' => $g->mapWithKeys(fn ($t) => [($t->maturity ?: 'Mature') => ['id' => $t->id,
                'rate' => $t->default_unit_price === null ? null : (float) $t->default_unit_price,
                'rate_ha' => $t->price_per_ha === null ? null : (float) $t->price_per_ha]])];
    });
    $ccIdKey = $treeTypes->mapWithKeys(fn ($t) => [$t->id => $t->category . '|' . $t->name]);
    $ccIdMat = $treeTypes->mapWithKeys(fn ($t) => [$t->id => $t->maturity ?: 'Mature']);
    $ccGroupLabels = \App\Http\Controllers\Survey\CalculatorController::GROUPS;
    $ccQty = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
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
        <div class="type-desc">Plot split by sharing ratio · Farmer : Government</div>
    </label>
</div>


{{-- ------------------------------- Monetary Compensation ------------------------------- --}}
<div id="ccMon" style="{{ $activeMode === 'monetary' ? '' : 'display:none;' }}">
    @include('survey_module.partials.sections._calculator-schedule', ['for' => 'trees'])
    @if ($treeTypes->isEmpty())
        <div class="comp-type-note" style="border-left:4px solid var(--danger);background:#fdecea;">
            <i class="fas fa-exclamation-triangle"></i>
            The Farm Assessment catalogue is empty, so there is nothing to price.
            <a href="{{ route('survey-module.compensation.trees') }}">Add cash crops and economic trees</a> first.
        </div>
    @else
        <form method="POST" action="{{ route('survey-module.compensation.calculator.run') }}">
            @csrf
            <input type="hidden" name="mode" value="monetary" />

            {{-- One line per cash crop / economic tree; the land under it follows it (recording 302). --}}
            <div class="dash-card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3 style="margin:0;font-size:16px;">Cash Crops and Economic Trees</h3>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="ccAddLine()">
                        <i class="fas fa-plus"></i> Add Line
                    </button>
                </div>
                <p class="helper-text" style="margin:0 0 10px;">
                    Cash crop: area × rate per m² (or per hectare when the area is in hectares). Economic tree: stands × rate per stand.
                    The land under it: hectares × its cost of production per hectare (m² ÷ 10,000 = ha).
                </p>

                <div id="ccLines">
                    @foreach ($oldLines as $i => $line)
                        @php
                            $pickedId = (string) ($line['survey_tree_type_id'] ?? '');
                            $pickedKey = $ccIdKey[$pickedId] ?? '';
                            $isCrop = $pickedKey !== '' && str_starts_with($pickedKey, $cashCrop . '|');
                        @endphp
                        <div class="cc-line" data-cc-line style="border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:12px;">
                            <input type="hidden" name="lines[{{ $i }}][survey_tree_type_id]" value="{{ $pickedId }}" data-cc-type data-n="survey_tree_type_id" />
                            <div class="form-grid" style="margin-bottom:0;">
                                <div class="form-group">
                                    <label>Cash Crop / Economic Tree <span class="required">*</span></label>
                                    <select data-cc-item onchange="ccPick(this)">
                                        <option value="">Select from catalogue…</option>
                                        @foreach (['Cash Crops' => $cashCrop, 'Economic Trees' => \App\Models\Survey\SurveyTreeType::ECONOMIC_TREE] as $glabel => $gtype)
                                            <optgroup label="{{ $glabel }}">
                                                @foreach ($ccItems->where('type', $gtype)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE) as $key => $it)
                                                    <option value="{{ $key }}" @selected($pickedKey === $key)>{{ $it['name'] }}{{ $it['local'] ? ' / ' . $it['local'] : '' }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Category <span class="required">*</span></label>
                                    <select data-cc-maturity onchange="ccPick(this)">
                                        @foreach ($maturities as $m)
                                            <option value="{{ $m }}" @selected(($ccIdMat[$pickedId] ?? 'Mature') === $m)>{{ $m }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label data-cc-qlabel>{{ $isCrop ? 'Area' : 'No. of Stands' }} <span class="required">*</span></label>
                                    <div style="display:flex;gap:6px;">
                                        <input type="number" step="any" min="0" name="lines[{{ $i }}][quantity]" value="{{ $line['quantity'] ?? '' }}" data-cc-qty data-n="quantity" oninput="ccQtyChanged(this)" style="flex:1;" />
                                        <select name="lines[{{ $i }}][quantity_unit]" data-cc-qunit data-n="quantity_unit" onchange="ccQtyChanged(this)" style="width:auto;{{ $isCrop ? '' : 'display:none;' }}">
                                            <option value="sqm" @selected(($line['quantity_unit'] ?? 'sqm') === 'sqm')>m²</option>
                                            <option value="hectare" @selected(($line['quantity_unit'] ?? '') === 'hectare')>hectares</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Rate (₦)</label>
                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]" value="{{ $line['unit_price'] ?? '' }}" data-cc-price data-n="unit_price" oninput="ccPreview()" />
                                    <p class="helper-text" data-cc-rate>From the catalogue; change it only if this was valued differently.</p>
                                </div>
                                <div class="form-group">
                                    <label>Amount</label>
                                    <div style="display:flex;gap:8px;align-items:center;">
                                        <input type="text" data-cc-amount value="₦0.00" readonly style="font-weight:700;color:var(--primary);background:var(--gray-100);" />
                                        <button type="button" class="btn btn-danger btn-sm" onclick="ccRemoveLine(this)" title="Remove line"><i class="fas fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                            {{-- The land under this crop / tree. --}}
                            <div class="form-grid" style="margin:8px 0 0;padding-top:8px;border-top:1px dashed var(--gray-200);">
                                <div class="form-group">
                                    <label><i class="fas fa-vector-square" style="opacity:.6;"></i> Land under it</label>
                                    <div style="display:flex;gap:6px;">
                                        <input type="number" step="any" min="0" name="lines[{{ $i }}][land_size]" value="{{ $line['land_size'] ?? '' }}" data-cc-land-size data-n="land_size" oninput="ccLandTouched(this)" placeholder="Land size" style="flex:1;" />
                                        <select name="lines[{{ $i }}][land_unit]" data-cc-land-unit data-n="land_unit" onchange="ccLandTouched(this)" style="width:auto;">
                                            <option value="hectare" @selected(($line['land_unit'] ?? 'hectare') === 'hectare')>hectares</option>
                                            <option value="sqm" @selected(($line['land_unit'] ?? '') === 'sqm')>m²</option>
                                        </select>
                                    </div>
                                    <p class="helper-text" data-cc-land-note>For a cash crop it follows the area until you change it.</p>
                                </div>
                                <div class="form-group">
                                    <label>Cost of Production per ha (₦)</label>
                                    <input type="number" step="0.01" min="0" name="lines[{{ $i }}][land_rate]" value="{{ $line['land_rate'] ?? '' }}" data-cc-land-rate data-n="land_rate" oninput="ccPreview()" />
                                </div>
                                <div class="form-group">
                                    <label>Land Amount</label>
                                    <input type="text" data-cc-land-amount value="₦0.00" readonly style="font-weight:700;color:var(--primary);background:var(--gray-100);" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- One table grouped into the four sections, each with a subtotal, and the grand total (recording 302). --}}
            <div class="dash-card" style="margin-bottom:20px;">
                <div class="card-header"><h3 style="margin:0;font-size:16px;">Assessment (live preview)</h3></div>
                <div class="table-wrapper">
                    <div class="table-scroll">
                        <table>
                            <thead><tr><th>S/N</th><th>Item</th><th>Category</th><th>Quantity</th><th>Rate (₦)</th><th>Amount (₦)</th></tr></thead>
                            <tbody id="ccGrouped"></tbody>
                        </table>
                    </div>
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
                    <div class="label">Grand Total</div>
                    <div class="amount" id="ccTotalPreview">₦0.00</div>
                    <div class="detail">The four subtotals · live preview, press Calculate for the verified figure</div>
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
                        <thead><tr><th>S/N</th><th>Item</th><th>Category</th><th>Quantity</th><th>Rate (₦)</th><th>Amount (₦)</th></tr></thead>
                        <tbody>
                            @php $sn = 0; @endphp
                            @foreach ($result['groups'] as $g)
                                <tr style="background:var(--gray-100);"><td colspan="6"><strong>{{ $g['label'] }}</strong></td></tr>
                                @forelse ($g['rows'] as $l)
                                    @php $sn++; @endphp
                                    <tr>
                                        <td>{{ $sn }}</td>
                                        <td><strong>{{ $l['item'] }}</strong></td>
                                        <td>{{ $l['category'] ?: '—' }}</td>
                                        <td>
                                            {{ $ccQty($l['quantity']) }} {{ ['sqm' => 'm²', 'hectare' => 'ha', 'stand' => $l['quantity'] == 1 ? 'stand' : 'stands'][$l['unit']] ?? '' }}
                                            @if (isset($l['hectares']) && $l['unit'] === 'sqm')<span class="helper-text">({{ $ccQty($l['hectares']) }} ha)</span>@endif
                                        </td>
                                        <td>
                                            {{ number_format($l['rate'], 2) }} {{ isset($l['hectares']) ? 'per ha' : ['sqm' => 'per m²', 'hectare' => 'per ha', 'stand' => 'per stand'][$l['unit']] }}
                                            @if ($l['overridden'])<span class="status-badge pending" style="margin-left:6px;"><span class="dot"></span>Override</span>@endif
                                        </td>
                                        <td>{{ number_format($l['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td></td><td colspan="5" style="color:var(--gray-500);">None</td></tr>
                                @endforelse
                                <tr><td></td><td colspan="4" style="text-align:right;"><strong>Subtotal: {{ $g['label'] }}</strong></td><td><strong>{{ number_format($g['subtotal'], 2) }}</strong></td></tr>
                            @endforeach
                            <tr style="background:var(--gray-100);"><td></td><td colspan="4" style="text-align:right;"><strong>Grand Total</strong></td><td><strong>{{ number_format($result['total'], 2) }}</strong></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer"><span>Computed on the server from the catalogue · beneficiary count {{ $result['beneficiary_source'] }}</span></div>
            </div>

            <div class="calc-grid" style="margin-top:16px;">
                <div class="calc-card">
                    <div class="label">Grand Total</div>
                    <div class="amount">₦{{ number_format($result['total'], 2) }}</div>
                    <div class="detail">{{ collect($result['groups'])->map(fn ($g) => $g['label'] . ' ₦' . number_format($g['subtotal'], 2))->implode(' + ') }}</div>
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
    {{-- The Resettlement Calculator, on the Land to Land tab only, above the schedule (user, 2026-11-02; CR 30102026 item 10). --}}
    <div class="dash-card" style="margin-bottom:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div>
            <h3 style="margin:0 0 4px;font-size:16px;"><i class="fas fa-calculator" style="color:var(--primary);"></i> Resettlement Calculator</h3>
            <div style="font-size:13px;color:var(--gray-500);">Plots = area (ha) ÷ plot size for the land use and density, split by the sharing ratio on Land to Land lands, with the Classmark reading of part plots. Plot sizes are managed there.</div>
        </div>
        <a href="{{ route('survey-module.compensation.resettlement-calculator') }}" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Open Resettlement Calculator</a>
    </div>
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
                            <option value="{{ $c->id }}" data-plots="{{ (int) $c->num_plots }}" data-farmer-pct="{{ $c->project?->farmer_share_pct ?? 50 }}"
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
                {{-- Sharing ratio (CR 30102026 item 11): a case uses its project ratio; an ad-hoc estimate picks one. --}}
                <div class="form-group">
                    <label>Sharing ratio (farmer : Government)</label>
                    <select name="farmer_pct" id="ccRatio" onchange="ccLandPreview()">
                        @foreach ($ratios as $ratio)
                            <option value="{{ $ratio->farmer_pct }}" @selected((string) old('farmer_pct', $ratios->firstWhere('is_default', 1)?->farmer_pct ?? 50) === (string) $ratio->farmer_pct)>{{ $ratio->label }}{{ $ratio->is_default ? ' (general)' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="helper-text" id="ccRatioNote">Set in Configurable Entities &gt; Survey. A selected case uses its project's ratio.</p>
                </div>
                <div class="form-group">
                    <label>Odd-plot rule</label>
                    <select name="odd_rule" id="ccOddRule" onchange="ccLandPreview()">
                        <option value="govt" @selected(old('odd_rule', 'govt') === 'govt')>Extra plot → Government (system rule)</option>
                        <option value="farmer" @selected(old('odd_rule') === 'farmer')>Extra plot → Farmer</option>
                    </select>
                    <p class="helper-text">Only matters when the share leaves a part plot. Government is the standing rule.</p>
                </div>
                <div class="form-group">
                    <label>Farmers sharing the allocation</label>
                    <input type="number" min="1" name="beneficiaries" id="ccLandBen"
                           value="{{ old('beneficiaries') }}" placeholder="e.g. 4" oninput="ccLandPreview()" />
                    <p class="helper-text">A selected case supplies its own registered count instead.</p>
                </div>
                {{-- Classmark (CR 30102026 item 8): plots worked out to decimals read as full / half / quarter plots. --}}
                <div class="form-group">
                    <label>Classmark: plots with decimals</label>
                    <input type="number" step="0.01" min="0" id="ccExact" placeholder="e.g. 1.49" oninput="ccClassmark()" />
                    <p class="helper-text" id="ccClassmarkOut">The Classmark ranges are set in Configurable Entities &gt; Survey.</p>
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
                            No part plot ({{ $result['ratio'] ?? '50:50' }})
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
                Sharing ratio <strong>{{ $result['ratio'] ?? '50:50' }}</strong> · rule applied: extra plot → <strong>{{ $result['odd_rule'] === 'govt' ? 'Government' : 'Farmer' }}</strong>
                @unless ($result['is_default_rule'])
                    — this overrides the system-wide default (Government).
                @endunless
            </div>
        </div>
    @endif

</div>

<script>
    // Distinct names: _scripts.blade.php already defines runCashCalc()/runLandCalc()
    // against the prototype's demo ids, and is loaded after this partial.
    // Kano schedule (user, 2026-11-01): "Type|Name" => {type, name, cost, rows: {Category: {id, rate, rate_ha}}}.
    var ccItems = @json($ccItems);
    var CC_CASH = @json($cashCrop);
    var CC_GROUPS = @json($ccGroupLabels);

    function ccSelectMode(mode) {
        document.getElementById('ccMon').style.display = mode === 'monetary' ? 'block' : 'none';
        document.getElementById('ccLand').style.display = mode === 'land' ? 'block' : 'none';
        document.getElementById('ccOptMon').classList.toggle('selected', mode === 'monetary');
        document.getElementById('ccOptLand').classList.toggle('selected', mode === 'land');
    }

    function ccLines() { return document.querySelectorAll('#ccLines [data-cc-line]'); }

    function ccRenumber() {
        ccLines().forEach(function (row, i) {
            row.querySelectorAll('[data-n]').forEach(function (el) { el.name = 'lines[' + i + '][' + el.dataset.n + ']'; });
        });
    }

    function ccAddLine() {
        var rows = ccLines();
        if (!rows.length) return;
        var clone = rows[0].cloneNode(true);
        if (window.svSearchable) svSearchable.cleanClone(clone);   // the item list is a search box
        clone.querySelector('[data-cc-item]').selectedIndex = 0;
        clone.querySelector('[data-cc-maturity]').value = 'Mature';
        clone.querySelectorAll('[data-cc-type], [data-cc-qty], [data-cc-price], [data-cc-land-size], [data-cc-land-rate]').forEach(function (el) { el.value = ''; });
        clone.querySelector('[data-cc-qunit]').value = 'sqm';
        clone.querySelector('[data-cc-land-unit]').value = 'hectare';
        delete clone.querySelector('[data-cc-land-size]').dataset.touched;
        document.getElementById('ccLines').appendChild(clone);
        if (window.svSearchable) svSearchable(clone);
        ccRenumber();
        ccApplyKind(clone);
        ccPreview();
    }

    function ccRemoveLine(btn) {
        if (ccLines().length <= 1) return;               // an estimate needs at least one line
        btn.closest('[data-cc-line]').remove();
        ccRenumber();
        ccPreview();
    }

    function ccItem(row) { var k = row.querySelector('[data-cc-item]').value; return k ? ccItems[k] : null; }
    function ccIsCrop(row) { var it = ccItem(row); return !!(it && it.type === CC_CASH); }

    // Area + m² / ha for a cash crop; number of stands for a tree.
    function ccApplyKind(row) {
        var crop = ccIsCrop(row);
        row.querySelector('[data-cc-qlabel]').innerHTML = (crop ? 'Area' : 'No. of Stands') + ' <span class="required">*</span>';
        row.querySelector('[data-cc-qunit]').style.display = crop ? '' : 'none';
        row.querySelector('[data-cc-qty]').step = crop ? 'any' : '1';
        row.querySelector('[data-cc-land-note]').textContent = crop ? 'Follows the area until you change it.' : 'The land the trees stand on.';
    }

    // The catalogue rate for the line's unit: per m², per hectare (cash crop in ha), or per stand.
    function ccCatalogueRate(row) {
        var it = ccItem(row), cat = row.querySelector('[data-cc-maturity]').value;
        if (!it || !it.rows[cat]) return null;
        var r = it.rows[cat];
        return ccIsCrop(row) && row.querySelector('[data-cc-qunit]').value === 'hectare' ? r.rate_ha : r.rate;
    }

    // Item + Category -> that catalogue row (hidden id), its rate and its cost of production.
    function ccPick(select) {
        var row = select.closest('[data-cc-line]'), it = ccItem(row), cat = row.querySelector('[data-cc-maturity]').value;
        row.querySelector('[data-cc-type]').value = it && it.rows[cat] ? it.rows[cat].id : '';
        ccApplyKind(row);
        var r = ccCatalogueRate(row);
        row.querySelector('[data-cc-price]').value = r == null ? '' : r;
        row.querySelector('[data-cc-land-rate]').value = it && it.cost != null ? it.cost : '';
        ccFollowArea(row);
        ccPreview();
    }

    // A cash crop's land follows its area until the land size is changed by hand.
    function ccFollowArea(row) {
        var land = row.querySelector('[data-cc-land-size]');
        if (!ccIsCrop(row) || land.dataset.touched) return;
        land.value = row.querySelector('[data-cc-qty]').value;
        row.querySelector('[data-cc-land-unit]').value = row.querySelector('[data-cc-qunit]').value;
    }
    function ccQtyChanged(el) {
        var row = el.closest('[data-cc-line]');
        if (el.matches('[data-cc-qunit]')) {   // m² <-> ha changes which catalogue rate applies
            var r = ccCatalogueRate(row);
            row.querySelector('[data-cc-price]').value = r == null ? '' : r;
        }
        ccFollowArea(row);
        ccPreview();
    }
    function ccLandTouched(el) {
        el.closest('[data-cc-line]').querySelector('[data-cc-land-size]').dataset.touched = '1';
        ccPreview();
    }

    function ccMoney(n) {
        return '₦' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function ccNum(n) { return (Math.round(n * 10000) / 10000).toLocaleString(); }
    function ccEsc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : String(t); return d.innerHTML; }

    function ccPreview() {
        var groups = {};
        Object.keys(CC_GROUPS).forEach(function (k) { groups[k] = { rows: [], subtotal: 0 }; });

        ccLines().forEach(function (row) {
            var it = ccItem(row), cat = row.querySelector('[data-cc-maturity]').value, crop = ccIsCrop(row);
            var qty = parseFloat(row.querySelector('[data-cc-qty]').value) || 0;
            var unit = crop ? row.querySelector('[data-cc-qunit]').value : 'stand';
            var priceEl = row.querySelector('[data-cc-price]'), cat_rate = ccCatalogueRate(row);
            var rate = priceEl.value !== '' ? parseFloat(priceEl.value) : (cat_rate || 0);
            var note = row.querySelector('[data-cc-rate]');
            if (note) note.textContent = !it ? 'From the catalogue; change it only if this was valued differently.'
                : (!it.rows[cat] ? it.name + ' has no ' + cat + ' entry in the catalogue'
                : (cat_rate == null ? cat + ' rate not set' : cat + ' rate ' + ccMoney(cat_rate) + ' per ' + (unit === 'hectare' ? 'ha' : (crop ? 'm²' : 'stand'))));
            var amount = Math.round(qty * (rate || 0) * 100) / 100;
            row.querySelector('[data-cc-amount]').value = ccMoney(amount);

            var lsize = parseFloat(row.querySelector('[data-cc-land-size]').value) || 0;
            var lunit = row.querySelector('[data-cc-land-unit]').value;
            var ha = lunit === 'sqm' ? lsize / 10000 : lsize;
            var cost = parseFloat(row.querySelector('[data-cc-land-rate]').value) || 0;
            var lamount = Math.round(ha * cost * 100) / 100;
            row.querySelector('[data-cc-land-amount]').value = ccMoney(lamount);

            if (!it) return;
            var key = crop ? 'cash' : 'trees';
            groups[key].rows.push({ item: it.name, category: cat, qty: ccNum(qty) + ' ' + (unit === 'sqm' ? 'm²' : (unit === 'hectare' ? 'ha' : (qty === 1 ? 'stand' : 'stands'))),
                rate: ccMoney(rate || 0) + (unit === 'hectare' ? ' per ha' : (crop ? ' per m²' : ' per stand')), amount: amount });
            groups[key].subtotal += amount;
            if (lsize > 0) {
                var lkey = crop ? 'cash_land' : 'tree_land';
                groups[lkey].rows.push({ item: 'Land: ' + it.name, category: '—', qty: ccNum(lsize) + (lunit === 'sqm' ? ' m² (' + ccNum(ha) + ' ha)' : ' ha'),
                    rate: ccMoney(cost) + ' per ha', amount: lamount });
                groups[lkey].subtotal += lamount;
            }
        });

        var html = '', sn = 0, total = 0;
        Object.keys(CC_GROUPS).forEach(function (k) {
            var g = groups[k];
            html += '<tr style="background:var(--gray-100);"><td colspan="6"><strong>' + ccEsc(CC_GROUPS[k]) + '</strong></td></tr>';
            if (!g.rows.length) html += '<tr><td></td><td colspan="5" style="color:var(--gray-500);">None</td></tr>';
            g.rows.forEach(function (r) {
                sn++;
                html += '<tr><td>' + sn + '</td><td><strong>' + ccEsc(r.item) + '</strong></td><td>' + ccEsc(r.category) + '</td><td>' + ccEsc(r.qty) + '</td><td>' + ccEsc(r.rate) + '</td><td>' + ccMoney(r.amount).slice(1) + '</td></tr>';
            });
            html += '<tr><td></td><td colspan="4" style="text-align:right;"><strong>Subtotal: ' + ccEsc(CC_GROUPS[k]) + '</strong></td><td><strong>' + ccMoney(g.subtotal).slice(1) + '</strong></td></tr>';
            total += g.subtotal;
        });
        html += '<tr style="background:var(--gray-100);"><td></td><td colspan="4" style="text-align:right;"><strong>Grand Total</strong></td><td><strong>' + ccMoney(total).slice(1) + '</strong></td></tr>';
        var tb = document.getElementById('ccGrouped');
        if (tb) tb.innerHTML = html;

        var el = document.getElementById('ccTotalPreview');
        if (el) el.textContent = ccMoney(total);
        var ben = parseInt(document.getElementById('ccBenManual')?.value, 10) || 0;
        var benEl = document.getElementById('ccBenPreview');
        var avgEl = document.getElementById('ccAvgPreview');
        if (benEl) benEl.textContent = ben > 0 ? ben : '—';
        if (avgEl) avgEl.textContent = ben > 0 ? ccMoney(total / ben) : '—';
    }

    // Lines sent back after a validation error: show each one's kind and a land size typed by hand stays.
    document.addEventListener('DOMContentLoaded', function () {
        ccLines().forEach(function (row) {
            ccApplyKind(row);
            var land = row.querySelector('[data-cc-land-size]');
            if (land.value !== '' && land.value !== row.querySelector('[data-cc-qty]').value) land.dataset.touched = '1';
        });
        ccPreview();
    });

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

    // Mirrors SurveySettings::applyClassmark(): whole plots kept, the decimal part read to two places.
    var CC_CLASSMARKS = @json($classmarks);
    function ccClassmark() {
        var x = parseFloat(document.getElementById('ccExact').value), out = document.getElementById('ccClassmarkOut');
        if (!(x >= 0)) { out.textContent = 'The Classmark ranges are set in Configurable Entities > Survey.'; return; }
        var whole = Math.floor(x + 1e-9), frac = Math.round((x - whole) * 100) / 100;
        var r = CC_CLASSMARKS.find(function (c) { return frac >= c.from - 1e-9 && frac <= c.to + 1e-9; });
        var plots = whole + (r ? r.plots : 0), w = Math.floor(plots), h = Math.round((plots - w) * 100);
        var sign = { 25: '\u00bc', 50: '\u00bd', 75: '\u00be' }[h];
        var label = h === 0 ? String(w) : (sign ? (w ? w : '') + sign : plots.toFixed(2));
        out.innerHTML = '<strong>' + label + (plots === 1 ? ' plot' : ' plots') + '</strong> by the Classmark (part plot .' + String(Math.round(frac * 100)).padStart(2, '0') + ')';
    }

    function ccLandPreview() {
        var plots = parseInt(document.getElementById('ccPlots')?.value, 10) || 0;
        var rule = document.getElementById('ccOddRule')?.value || 'govt';
        // A picked case uses its project's ratio (the select locks to it); else the ratio chosen.
        var caseSel = document.querySelector('select[name="survey_comp_case_id"]'), opt = caseSel ? caseSel.options[caseSel.selectedIndex] : null;
        var ratioSel = document.getElementById('ccRatio');
        if (opt && opt.value) { ratioSel.value = opt.dataset.farmerPct || '50'; ratioSel.disabled = true; } else if (ratioSel) { ratioSel.disabled = false; }
        var pct = parseInt(ratioSel ? ratioSel.value : 50, 10) || 50;
        // SurveySettings::splitPlots(): the farmer takes the whole plots of their share, Government the rest.
        var farmer = Math.floor(plots * pct / 100);
        if (rule === 'farmer' && (plots * pct) % 100 !== 0) farmer++;
        var govt = plots - farmer;
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
