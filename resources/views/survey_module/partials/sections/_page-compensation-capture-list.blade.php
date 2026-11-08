@php
    use App\Models\Survey\SurveyProject;
    use App\Services\Survey\CompensationListCapture;

    // Grid columns: field => [heading, width, input type].
    $cols = [
        'fn'        => ['F/No.', 80, 'text'],
        'name'      => ['Farmer Name', 220, 'text'],
        'area'      => ['Area (Ha)', 90, 'number'],
        'creatable' => ['Creatable', 85, 'text'],
        'govt50'    => ['Govt. 50%', 85, 'number'],
        'farmer50'  => ['Farmer 50%', 85, 'number'],
        'plot'      => ['Plot No(s).', 150, 'text'],
        'op'        => ['OP No.', 110, 'text'],
        'sign'      => ['Sign', 80, 'text'],
        'remarks'   => ['Status / Remarks', 180, 'text'],
    ];
    $oldRows = array_values(old('rows', []));
    $rowCount = max(15, count($oldRows));

    $calcLandUses = \App\Services\Survey\ResettlementCalculator::LAND_USES;
@endphp

@include('survey_module.partials._flash')
@include('survey_module.partials._searchable_selects')

<style>
    .survey-proto .cap-h { display: flex; align-items: center; gap: 10px; margin: 4px 0 14px; font-size: 13px; font-weight: 700;
                           letter-spacing: .4px; text-transform: uppercase; color: var(--primary); }
    .survey-proto .cap-h .n { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 50%;
                              background: var(--primary); color: #fff; font-size: 12px; }
    .survey-proto .cap-h::after { content: ''; flex: 1; height: 1px; background: var(--gray-200); }
    .survey-proto .cap-grid-wrap { overflow: auto; max-height: 62vh; border: 1px solid var(--gray-200); border-radius: 8px; }
    .survey-proto table.cap-grid { border-collapse: separate; border-spacing: 0; font-size: 13px; width: max-content; min-width: 100%; }
    .survey-proto table.cap-grid thead th { position: sticky; top: 0; z-index: 2; background: var(--gray-50); padding: 8px 6px; font-size: 11px;
                                            text-transform: uppercase; letter-spacing: .3px; color: var(--gray-600); border-bottom: 1px solid var(--gray-200); white-space: nowrap; text-align: left; }
    .survey-proto table.cap-grid tbody td { padding: 0; border-bottom: 1px solid var(--gray-100); border-right: 1px solid var(--gray-100); }
    .survey-proto table.cap-grid tbody td.sn { padding: 0 8px; color: var(--gray-400); text-align: right; font-variant-numeric: tabular-nums; background: var(--gray-50); }
    .survey-proto table.cap-grid input { width: 100%; border: 0; border-radius: 0; padding: 7px 8px; font-size: 13px; background: transparent; box-shadow: none; }
    .survey-proto table.cap-grid input:focus { outline: 2px solid var(--primary); outline-offset: -2px; background: #fff; }
    .survey-proto table.cap-grid input[type=number] { text-align: right; }
    .survey-proto table.cap-grid tbody tr:hover td { background: #fafafa; }
    .survey-proto table.cap-grid .del { border: 0; background: none; color: var(--gray-400); padding: 6px 8px; cursor: pointer; }
    .survey-proto table.cap-grid .del:hover { color: #b91c1c; }
    .survey-proto .cap-totals { display: flex; flex-wrap: wrap; gap: 8px 20px; padding: 10px 2px; font-size: 13px; color: var(--gray-600); }
    .survey-proto .cap-totals b { color: var(--gray-900); font-variant-numeric: tabular-nums; }
</style>

<form method="POST" action="{{ route('survey-module.compensation.capture-list.store') }}" id="capForm" autocomplete="off">
    @csrf
    <div class="form-container">
        <div class="form-body">

            {{-- 1. Project --}}
            <div class="cap-h"><span class="n">1</span> Project</div>
            <input type="hidden" name="project_mode" value="new" />
            <div class="form-grid" id="capNew">
                <div class="form-group">
                    <label>Project Name <span class="required">*</span></label>
                    <input type="text" name="project_name" value="{{ old('project_name') }}" maxlength="255" placeholder="e.g. Tsamiyar Kara Phase II" />
                    <p class="helper-text">The layout name only; the TP number goes in its own box.</p>
                </div>
                <div class="form-group">
                    <label>TP No.</label>
                    <select name="plan_no" class="sv-searchable" data-placeholder="Search TP number or layout…">
                        <option value=""></option>
                        @foreach ($tpNumbers as $tp)
                            <option value="{{ $tp['no'] }}" @selected(old('plan_no') === $tp['no'])>{{ $tp['no'] }}{{ $tp['name'] ? ' — ' . \Illuminate\Support\Str::limit($tp['name'], 60) : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Scheme <span class="required">*</span></label>
                    <select name="scheme_type">
                        <option value="{{ SurveyProject::SCHEME_LAND }}" @selected(old('scheme_type', SurveyProject::SCHEME_LAND) === SurveyProject::SCHEME_LAND)>Land to Land</option>
                        <option value="{{ SurveyProject::SCHEME_MONETARY }}" @selected(old('scheme_type') === SurveyProject::SCHEME_MONETARY)>Monetary Compensation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Purpose</label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" maxlength="100" placeholder="e.g. Residential Layout" />
                </div>
                @include('survey_module.partials._co_file_picker', ['coCurrent' => null, 'coRequired' => false, 'coAllowNew' => true])
            </div>

            {{-- 2. The list --}}
            <div class="cap-h" style="margin-top:10px;"><span class="n">2</span> The list</div>
            <div class="form-grid cols-3">
                <div class="form-group">
                    <label>List Type <span class="required">*</span></label>
                    <select name="list_type">
                        @foreach (CompensationListCapture::LIST_TYPES as $k => $label)
                            <option value="{{ $k }}" @selected(old('list_type', 'farmer') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Surveyed By</label>
                    <input type="text" name="surveyor_name" value="{{ old('surveyor_name') }}" maxlength="255" list="capSurveyors" placeholder="Start typing a surveyor…" />
                    <datalist id="capSurveyors">
                        @foreach ($surveyors as $s)<option value="{{ $s }}">@endforeach
                    </datalist>
                    <p class="helper-text">This list becomes the surveyor's next portion on the project.</p>
                </div>
                <div class="form-group">
                    <label>Section / Block</label>
                    <input type="text" name="section" value="{{ old('section') }}" maxlength="50" placeholder="e.g. A" />
                </div>
                <div class="form-group">
                    <label>List Title</label>
                    <input type="text" name="list_title" value="{{ old('list_title') }}" maxlength="255" placeholder="As written at the top of the list" />
                </div>
                <div class="form-group">
                    <label>Date on the List</label>
                    <input type="date" name="list_date" value="{{ old('list_date') }}" />
                </div>
                <div class="form-group">
                    <label>Paper Reference</label>
                    <input type="text" name="source_ref" value="{{ old('source_ref') }}" maxlength="255" placeholder="e.g. CO-9-2018, pages 4–7" />
                </div>
            </div>

            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => null,
                'legend' => 'Location of the land (optional)',
                'fields' => ['district', 'lga', 'state'],
            ])

            {{-- 3. Rows --}}
            <div class="cap-h" style="margin-top:18px;"><span class="n">3</span> Rows</div>
            <p class="helper-text" style="margin:-6px 0 10px;">
                Type the rows as they appear on the paper. <strong>Enter</strong> moves down a row; you can also paste rows copied from Excel or Word
                (columns in this order). Empty rows are ignored. A row needs a farmer name or a plot number; write ABSENT etc. as on the list.
            </p>

            {{-- The Land to Land calculator inside the form (user, 2026-11-03), as the Compensation Calculator works it: the
                 plot size from Land Use > Purpose > Density (the purpose fixes the density where it names one), or typed
                 as on the paper; Creatable = Area / plot size and the shares = Creatable x the project's sharing ratio,
                 4 decimals like the lists. It only fills blank cells (or ones it filled); typed values are kept. --}}
            <div id="capCalc" style="display:none;border:1px dashed var(--gray-300);border-radius:10px;padding:12px 14px;background:var(--gray-50,#f9fafb);margin-bottom:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                    <div style="font-weight:600;"><i class="fas fa-calculator"></i> Plot Calculator (Land to Land)</div>
                    <div style="font-size:13px;color:var(--gray-600);">Sharing (Farmer : Government): <b id="capRatio">50:50</b></div>
                </div>
                <div class="form-grid" style="grid-template-columns:repeat(4,minmax(0,1fr));margin:0;">
                    <div class="form-group">
                        <label>Land Use</label>
                        <select id="capLandUse" data-no-search>
                            <option value="">Select…</option>
                            @foreach ($calcLandUses as $lu)<option value="{{ $lu }}">{{ $lu }}</option>@endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Purpose</label>
                        <select id="capPurpose" data-no-search disabled><option value="">Select land use first</option></select>
                    </div>
                    <div class="form-group">
                        <label>Density</label>
                        <select id="capDensity" data-no-search disabled><option value="">Select purpose first</option></select>
                        <p class="helper-text" id="capDensityNote" style="margin:4px 0 0;">SG plot sizes.</p>
                    </div>
                    <div class="form-group">
                        <label>Plot Size (Ha)</label>
                        <input type="number" id="capPlotSize" step="any" min="0" placeholder="e.g. 0.0454" />
                        <p class="helper-text" style="margin:4px 0 0;">From the density, or type the list's own.</p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-top:6px;">
                    <label style="display:inline-flex;align-items:center;gap:6px;margin:0;font-weight:500;font-size:13px;">
                        <input type="checkbox" id="capAutoFill" checked /> Fill Creatable and the shares from the Area as I type
                    </label>
                    <button type="button" class="btn btn-secondary btn-sm" id="capRecalc"><i class="fas fa-sync-alt"></i> Recalculate all rows</button>
                    <span class="helper-text" id="capCalcNote" style="margin:0;">Creatable = Area ÷ plot size; Govt. and Farmer = Creatable × the sharing ratio.</span>
                </div>
            </div>

            <div class="cap-grid-wrap">
                <table class="cap-grid" data-column-picker="off">
                    <thead>
                        <tr>
                            <th style="width:46px;text-align:right;">S/N</th>
                            @foreach ($cols as $f => [$label, $w])
                                <th style="min-width:{{ $w }}px;" data-h="{{ $f }}">{{ $label }}</th>
                            @endforeach
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody id="capRows">
                        @for ($i = 0; $i < $rowCount; $i++)
                            <tr>
                                <td class="sn">{{ $i + 1 }}</td>
                                @foreach ($cols as $f => [$label, $w, $type])
                                    <td><input type="{{ $type }}" @if ($type === 'number') step="any" min="0" @endif
                                               name="rows[{{ $i }}][{{ $f }}]" data-f="{{ $f }}" value="{{ $oldRows[$i][$f] ?? '' }}" aria-label="{{ $label }}" /></td>
                                @endforeach
                                <td><button type="button" class="del" title="Remove row" aria-label="Remove row"><i class="fas fa-times"></i></button></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div class="cap-totals" id="capTotals">
                <span>Rows: <b data-t="rows">0</b></span>
                <span>Farmers: <b data-t="farmers">0</b></span>
                <span>Area: <b data-t="area">0</b> Ha</span>
                <span><span data-tl="govt">Govt. 50%</span>: <b data-t="govt">0</b></span>
                <span><span data-tl="farmer">Farmer 50%</span>: <b data-t="farmer">0</b></span>
                <span>Plots: <b data-t="plots">0</b></span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary btn-sm" data-add="1"><i class="fas fa-plus"></i> Add row</button>
                <button type="button" class="btn btn-secondary btn-sm" data-add="10"><i class="fas fa-plus"></i> Add 10 rows</button>
                <button type="button" class="btn btn-outline btn-sm" id="capTrim"><i class="fas fa-broom"></i> Remove empty rows</button>
            </div>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.cases') }}"><i class="fas fa-arrow-left"></i> Cancel</a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Save List</button>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    'use strict';
    var form = document.getElementById('capForm');
    if (!form) return;
    var body = document.getElementById('capRows');
    var FIELDS = @json(array_keys($cols));

    function rows() { return Array.prototype.slice.call(body.rows); }
    function isEmpty(tr) { return Array.prototype.every.call(tr.querySelectorAll('input'), function (i) { return !i.value.trim(); }); }

    // S/N and input names follow the visible order.
    function renumber() {
        rows().forEach(function (tr, i) {
            tr.cells[0].textContent = i + 1;
            tr.querySelectorAll('input').forEach(function (inp) { inp.name = 'rows[' + i + '][' + inp.dataset.f + ']'; });
        });
        totals();
    }
    function addRows(n) {
        var tpl = body.rows[0];
        for (var k = 0; k < n; k++) {
            var tr = tpl.cloneNode(true);
            tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
            body.appendChild(tr);
        }
        renumber();
    }

    function countPlots(s) {
        var n = 0;
        String(s || '').split(/\s*[,;&]\s*/).forEach(function (p) {
            if (!p) return;
            var m = p.match(/^(\d+)\s*-\s*(\d+)$/);
            if (m && +m[2] >= +m[1] && m[2] - m[1] < 500) n += m[2] - m[1] + 1; else n++;
        });
        return n;
    }
    function num(v) { var x = parseFloat(v); return isNaN(x) ? 0 : x; }
    function fmt(x) { return (Math.round(x * 10000) / 10000).toLocaleString(); }
    function totals() {
        var t = { rows: 0, farmers: {}, area: 0, govt: 0, farmer: 0, plots: 0 };
        rows().forEach(function (tr) {
            if (isEmpty(tr)) return;
            var v = function (f) { return tr.querySelector('[data-f="' + f + '"]').value.trim(); };
            t.rows++;
            var k = v('name').toUpperCase().replace(/[^A-Z]/g, '');
            if (k && ['ABSENT', 'NIL', 'NONE'].indexOf(k) === -1) t.farmers[k] = 1;
            t.area += num(v('area')); t.govt += num(v('govt50')); t.farmer += num(v('farmer50'));
            t.plots += countPlots(v('plot'));
        });
        var box = document.getElementById('capTotals');
        box.querySelector('[data-t="rows"]').textContent = t.rows;
        box.querySelector('[data-t="farmers"]').textContent = Object.keys(t.farmers).length;
        box.querySelector('[data-t="area"]').textContent = fmt(t.area);
        box.querySelector('[data-t="govt"]').textContent = fmt(t.govt);
        box.querySelector('[data-t="farmer"]').textContent = fmt(t.farmer);
        box.querySelector('[data-t="plots"]').textContent = t.plots;
    }

    form.querySelectorAll('[data-add]').forEach(function (b) {
        b.addEventListener('click', function () { addRows(parseInt(b.dataset.add, 10)); });
    });
    document.getElementById('capTrim').addEventListener('click', function () {
        rows().forEach(function (tr) { if (isEmpty(tr) && body.rows.length > 1) tr.remove(); });
        renumber();
    });
    body.addEventListener('click', function (e) {
        var b = e.target.closest('.del');
        if (!b) return;
        var tr = b.closest('tr');
        if (body.rows.length > 1) tr.remove(); else tr.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        renumber();
    });
    body.addEventListener('input', totals);

    // Enter moves down the same column, adding a row at the bottom.
    body.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.dataset.f) return;
        e.preventDefault();
        var tr = e.target.closest('tr');
        if (!tr.nextElementSibling) addRows(1);
        var next = tr.nextElementSibling.querySelector('[data-f="' + e.target.dataset.f + '"]');
        if (next) next.focus();
    });

    // Paste a block copied from Excel / Word: tab-separated cells, one row per line.
    body.addEventListener('paste', function (e) {
        var inp = e.target;
        if (!inp.dataset || !inp.dataset.f) return;
        var text = (e.clipboardData || window.clipboardData).getData('text');
        if (!/[\t\n]/.test(text)) return;
        e.preventDefault();
        var lines = text.replace(/\r/g, '').replace(/\n+$/, '').split('\n');
        var tr = inp.closest('tr');
        var start = FIELDS.indexOf(inp.dataset.f);
        lines.forEach(function (line, li) {
            if (!tr) { addRows(1); tr = body.rows[body.rows.length - 1]; }
            line.split('\t').forEach(function (cell, ci) {
                var f = FIELDS[start + ci];
                if (!f) return;
                var target = tr.querySelector('[data-f="' + f + '"]');
                cell = cell.trim();
                if (target.type === 'number') cell = cell.replace(/,/g, '');
                target.value = cell;
            });
            tr = tr.nextElementSibling;
        });
        renumber();
    });

    form.addEventListener('submit', function () {
        // Drop trailing empty rows so the S/N saved matches what was keyed.
        var all = rows();
        for (var i = all.length - 1; i > 0 && isEmpty(all[i]); i--) all[i].remove();
        renumber();
    });

    /* Project details + Plot Calculator (user, 2026-11-03). */
    var RC_PURPOSES = @json($rcPurposes), RC_RATES = @json($rcRates), DEFAULT_FARMER = {{ (int) $defaultFarmerPct }};
    var schemeSel = form.querySelector('select[name="scheme_type"]');
    var luSel = document.getElementById('capLandUse'), pSel = document.getElementById('capPurpose'), dSel = document.getElementById('capDensity');
    var sizeIn = document.getElementById('capPlotSize'), autoBox = document.getElementById('capAutoFill');
    var calc = { land: false, farmer: DEFAULT_FARMER };

    function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t; return o; }
    function purposeDensity(s) {
        var m = String(s || '').toUpperCase().replace(/\s+/g, ' ').trim().match(/^(VERY HIGH|HIGH|MEDIUM|LOW) DENSITY$/);
        return m ? m[1] : null;
    }

    // Land Use > Purpose > Density, as the Compensation Calculator: the purpose fixes the density where it names one.
    function density(changed) {
        var lu = luSel.value;
        if (changed === 'landuse') {
            pSel.innerHTML = ''; pSel.appendChild(opt('', lu ? 'Select purpose…' : 'Select land use first'));
            (RC_PURPOSES[lu] || []).forEach(function (p) { pSel.appendChild(opt(p, p)); });
            pSel.disabled = !lu;
        }
        if (changed === 'landuse' || changed === 'purpose') {
            var purpose = pSel.value.toUpperCase();
            var rows = RC_RATES.filter(function (r) { return r.land_use === lu; });
            var own = rows.filter(function (r) { return r.purpose && r.purpose.toUpperCase() === purpose; });
            rows = own.length ? own : rows.filter(function (r) { return !r.purpose; });
            var fixed = purposeDensity(purpose);
            if (fixed) rows = rows.filter(function (r) { return purposeDensity(r.density) === fixed; });
            dSel.innerHTML = '';
            if (!pSel.value) dSel.appendChild(opt('', 'Select purpose first'));
            else if (!rows.length) dSel.appendChild(opt('', 'No SG plot size for this yet'));
            else if (!fixed || rows.length > 1) dSel.appendChild(opt('', 'Select density…'));
            if (pSel.value) rows.forEach(function (r) { dSel.appendChild(opt(r.size, (r.density || 'General') + ' · ' + r.size + ' ha a plot')); });
            if (fixed && rows.length === 1) dSel.value = String(rows[0].size);
            dSel.disabled = !pSel.value || !rows.length;
            var locked = !!fixed && rows.length === 1;
            dSel.style.pointerEvents = locked ? 'none' : ''; dSel.style.background = locked ? 'var(--gray-100)' : ''; dSel.tabIndex = locked ? -1 : 0;
            document.getElementById('capDensityNote').textContent = locked ? 'Set by the purpose.' : 'SG plot sizes.';
        }
        if (dSel.value) sizeIn.value = dSel.value;
        fillAll(false);
    }

    // The entered purpose (e.g. Residential Layout) picks the land use and matching density.
    function presetPurpose(text) {
        var want = String(text || '').toUpperCase().trim();
        if (!want) return;
        var lu = null, purpose = null;
        Object.keys(RC_PURPOSES).forEach(function (k) {
            (RC_PURPOSES[k] || []).forEach(function (p) { if (!purpose && p.toUpperCase() === want) { lu = k; purpose = p; } });
        });
        if (!lu) Object.keys(RC_PURPOSES).forEach(function (k) { if (!lu && want.indexOf(k.toUpperCase()) !== -1) lu = k; });
        if (!lu) return;
        luSel.value = lu; density('landuse');
        if (purpose) { pSel.value = purpose; density('purpose'); }
    }

    function sharePct() { return { farmer: calc.farmer, govt: 100 - calc.farmer }; }
    function labels() {
        var s = sharePct();
        document.getElementById('capRatio').textContent = s.farmer + ':' + s.govt;
        var g = form.querySelector('th[data-h="govt50"]'), f = form.querySelector('th[data-h="farmer50"]');
        if (g) g.textContent = 'Govt. ' + s.govt + '%';
        if (f) f.textContent = 'Farmer ' + s.farmer + '%';
        form.querySelector('[data-tl="govt"]').textContent = 'Govt. ' + s.govt + '%';
        form.querySelector('[data-tl="farmer"]').textContent = 'Farmer ' + s.farmer + '%';
    }

    // Scheme on this newly captured project drives the plot calculator.
    function syncProject() {
        var scheme = schemeSel ? schemeSel.value : '';
        calc.land = scheme === 'land';
        calc.farmer = DEFAULT_FARMER;
        document.getElementById('capCalc').style.display = calc.land ? '' : 'none';
        labels();
        fillAll(false);
    }

    function round4(x) { return Math.round(x * 10000) / 10000; }
    // One row: blank cells (or ones the calculator filled) take the worked figures; typed ones are kept unless forced.
    // The shares follow the row's Creatable, worked out or typed as on the paper.
    function fillRow(tr, force, typing) {
        if (!calc.land) return;
        var size = parseFloat(sizeIn.value), area = parseFloat(tr.querySelector('[data-f="area"]').value);
        var cells = ['creatable', 'govt50', 'farmer50'].map(function (f) { return tr.querySelector('[data-f="' + f + '"]'); });
        var mine = function (c) { return force || c.value.trim() === '' || c.dataset.auto === '1'; };
        var put = function (c, v) { c.value = v; c.dataset.auto = '1'; c.style.color = 'var(--primary)'; };
        var clear = function (c) { if (c.dataset.auto === '1') { c.value = ''; delete c.dataset.auto; c.style.color = ''; } };
        var creatable = null;
        if (mine(cells[0]) && !typing) {
            if (size > 0 && area > 0) { creatable = round4(area / size); put(cells[0], creatable); } else clear(cells[0]);
        } else {
            creatable = parseFloat(cells[0].value);
        }
        var s = sharePct();
        [[cells[1], s.govt], [cells[2], s.farmer]].forEach(function (x) {
            if (!mine(x[0])) return;
            if (creatable > 0) put(x[0], round4(creatable * x[1] / 100)); else clear(x[0]);
        });
    }
    function fillAll(force) {
        if (!force && !autoBox.checked) return;
        rows().forEach(function (tr) { fillRow(tr, force); });
        totals();
    }

    luSel.addEventListener('change', function () { density('landuse'); });
    pSel.addEventListener('change', function () { density('purpose'); });
    dSel.addEventListener('change', function () { density(); });
    sizeIn.addEventListener('input', function () { fillAll(false); });
    autoBox.addEventListener('change', function () { fillAll(false); });
    document.getElementById('capRecalc').addEventListener('click', function () {
        if (!(parseFloat(sizeIn.value) > 0)) { alert('Pick the density or type the plot size first.'); return; }
        if (!confirm('Work out Creatable and both shares again for every row with an Area? Values typed in those columns will be replaced.')) return;
        fillAll(true);
    });
    body.addEventListener('input', function (e) {
        var f = e.target.dataset && e.target.dataset.f;
        if (!f) return;
        if (f === 'area') { if (autoBox.checked) fillRow(e.target.closest('tr'), false); totals(); }
        else if (['creatable', 'govt50', 'farmer50'].indexOf(f) !== -1) {
            delete e.target.dataset.auto; e.target.style.color = '';
            // Clearing Creatable by hand leaves it blank (it is not worked out again while typing).
            if (f === 'creatable' && autoBox.checked) { fillRow(e.target.closest('tr'), false, true); totals(); }
        }
    });
    body.addEventListener('paste', function () { setTimeout(function () { fillAll(false); }, 0); });
    if (schemeSel) schemeSel.addEventListener('change', syncProject);
    var newPurpose = form.querySelector('input[name="purpose"]');
    if (newPurpose) newPurpose.addEventListener('change', function () { if (!luSel.value) presetPurpose(newPurpose.value); });

    syncProject();
    totals();
})();
</script>
@endpush
