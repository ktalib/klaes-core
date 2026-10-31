@extends('survey_module.layouts.klaes')

@section('page-title', 'Resettlement Calculator — KLAES')

@php
  use App\Services\Survey\ResettlementCalculator as RC;

  $PageTitle = 'Resettlement Calculator';
  $PageDescription = 'Plots = area (ha) ÷ plot size for the land use and density. Land-to-Land lands split 50:50 (odd plot to Government).';
  $active = $rates->where('is_active', true)->values();
  // Only these four land uses, in full (user, 2026-10-30). One without a plot size yet shows a note under Density,
  // and the calculation waits until the SG size is added below.
  $landUses = RC::LAND_USES;
  $ratesJs = $active->map(fn ($x) => ['id' => $x->id, 'landUse' => $x->land_use, 'purpose' => $x->purpose, 'density' => $x->density, 'code' => $x->code, 'size' => (float) $x->plot_size_ha])->values();
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;';
  $pickLand = (string) old('survey_comp_case_id', request('land', ''));
@endphp

@section('survey-content')
@include('survey_module.partials._flash')
<div style="margin-bottom:12px;"><a href="{{ route('survey-module.compensation.calculator', ['mode' => 'land']) }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-left"></i> Land to Land Calculator</a></div>
@if ($errors->any())<div class="alert alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>@endif

<style>
    .survey-proto .rc-grid { display:grid; grid-template-columns: minmax(320px, 420px) 1fr; gap:18px; align-items:start; }
    .survey-proto .rc-card { background:#fff; border:1px solid var(--gray-200,#e5e7eb); border-radius:12px; padding:18px; }
    .survey-proto .rc-card h3 { font-size:15px; font-weight:700; margin:0 0 12px; }
    .survey-proto .rc-choices { display:flex; flex-wrap:wrap; gap:8px; }
    .survey-proto .rc-choices label { display:inline-flex; align-items:center; gap:6px; border:1.5px solid var(--gray-300,#d1d5db); border-radius:8px; padding:6px 12px; cursor:pointer; font-weight:500; }
    .survey-proto .rc-choices label.on { border-color:var(--primary); background:var(--primary-100,#e0ecff); }
    .survey-proto .form-group .rc-choices input { width:auto; padding:0; }
    .survey-proto .rc-big { font-size:40px; font-weight:800; line-height:1; }
    .survey-proto .rc-sub { font-size:13px; color:var(--gray-500); }
    .survey-proto table.rc-t { width:100%; border-collapse:collapse; font-size:14px; }
    .survey-proto table.rc-t th { text-align:left; font-weight:600; font-size:12px; color:var(--gray-500); padding:8px; border-bottom:1px solid var(--gray-200,#e5e7eb); }
    .survey-proto table.rc-t td { padding:9px 8px; border-bottom:1px solid var(--gray-100,#f3f4f6); }
    .survey-proto table.rc-t tr.on td { background:var(--primary-100,#e0ecff); font-weight:600; }
    .survey-proto table.rc-t .n { text-align:right; font-variant-numeric: tabular-nums; }
    @media (max-width: 1100px) { .survey-proto .rc-grid { grid-template-columns: 1fr; } }
</style>

<div x-data="{ rate: null, purposes: @js($purposes) }" @rc-add-rate.window="rate = { url: @js(route('survey-module.compensation.resettlement-calculator.rates.store')), method: 'POST', title: 'Add Plot Size', land_use: $event.detail.landUse, purpose: $event.detail.purpose, density: '', code: '', plot_size_ha: '', source: '' }">

<form method="POST" action="{{ route('survey-module.compensation.resettlement-calculator.store') }}" id="rcForm">
    @csrf
    <div class="rc-grid">
        <div class="rc-card">
            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="sv-group"><i class="fas fa-seedling"></i><span>Land</span></div>
                <div class="form-group full">
                    <label>Land (farm)</label>
                    <select name="survey_comp_case_id" id="rcLand">
                        <option value="">— None: type the area —</option>
                        @foreach ($lands as $l)
                            <option value="{{ $l->id }}" @selected($pickLand === (string) $l->id)>{{ $l->case_ref }}{{ $l->beneficiaries->first() ? ' · ' . $l->beneficiaries->first()->full_name : '' }}</option>
                        @endforeach
                    </select>
                    <p class="helper-text" id="rcLandInfo"></p>
                </div>
                <div class="form-group full">
                    <label>Area (hectares) <span class="required">*</span></label>
                    <input type="number" name="area_ha" id="rcArea" step="0.0001" min="0.0001" value="{{ old('area_ha') }}" required placeholder="e.g. 2.5" />
                    <p class="helper-text" id="rcAreaNote" style="display:none;">From the land's record; change it if the surveyed area differs.</p>
                </div>

                <div class="sv-group"><i class="fas fa-city"></i><span>Land Use &amp; Density</span></div>
                {{-- Land Use -> Purpose (KLAES purposes lookup) -> Density for that purpose (user, 2026-10-30). --}}
                <div class="form-group">
                    <label>Land Use <span class="required">*</span></label>
                    <select id="rcLandUse" required>
                        <option value="">Select Land Use</option>
                        @foreach ($landUses as $lu)<option value="{{ $lu }}">{{ $lu }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Purpose <span class="required">*</span></label>
                    <select name="purpose" id="rcPurpose" required disabled>
                        <option value="">Select Purpose</option>
                    </select>
                </div>
                <div class="form-group full" id="rcDensityBox" style="display:none;">
                    <label>Density <span class="required">*</span></label>
                    <div class="rc-choices" id="rcDensities"></div>
                    <input type="hidden" name="rate_id" id="rcRate" value="{{ old('rate_id') }}" />
                </div>

                {{-- Settings hidden for now (user, 2026-10-30); the fields still post their defaults:
                     0% roads/open space, round down, 50:50 split on Land-to-Land. --}}
                <div id="rcSettings" hidden>
                <div class="sv-group"><i class="fas fa-sliders-h"></i><span>Settings</span></div>
                <div class="form-group">
                    <label>Roads &amp; open space (%)</label>
                    <input type="number" name="deduction_percent" id="rcDeduct" step="0.5" min="0" max="90" value="{{ old('deduction_percent', RC::DEFAULT_DEDUCTION) }}" />
                </div>
                <div class="form-group">
                    <label>Part plots</label>
                    <select name="rounding" id="rcRound">
                        @foreach ($roundings as $k => $label)<option value="{{ $k }}" @selected(old('rounding', RC::DEFAULT_ROUNDING) === $k)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="form-group full" id="rcSplitBox">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:400;">
                        <input type="hidden" name="split" value="0" />
                        <input type="checkbox" name="split" value="1" id="rcSplit" style="width:auto;" @checked(old('split', '1') === '1') />
                        Apply the 50:50 split (Land to Land)
                    </label>
                </div>
                <p class="helper-text full" id="rcSplitFixed" style="display:none;grid-column:1/-1;"></p>
                </div>
                <div class="form-group full">
                    <label>Notes</label>
                    <textarea name="notes" maxlength="1000" rows="2">{{ old('notes') }}</textarea>
                </div>
            </div>
            <div class="form-actions" style="margin-top:12px;">
                <div class="left"></div>
                <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-save"></i> Save Calculation</button></div>
            </div>
        </div>

        <div>
            <div class="rc-card" style="margin-bottom:18px;">
                <h3><i class="fas fa-calculator" style="color:var(--primary);"></i> Result</h3>
                <div style="display:flex;gap:28px;flex-wrap:wrap;align-items:flex-end;">
                    <div><div class="rc-sub">Plots in the farm</div><div class="rc-big" id="rcPlots">—</div><div class="rc-sub" id="rcExact"></div></div>
                    <div id="rcShares" style="display:none;">
                        <div class="rc-sub">50:50 split</div>
                        <div style="font-size:22px;font-weight:700;">Farmer <span id="rcFarmer">—</span> · Government <span id="rcGovt">—</span></div>
                        <div class="rc-sub">Odd plot to Government, as the current Land-to-Land rule.</div>
                    </div>
                </div>
                <div class="rc-sub" id="rcFormula" style="margin-top:12px;font-family:ui-monospace,monospace;"></div>
            </div>

            <div class="rc-card">
                <h3>All densities for <span id="rcLuName">—</span></h3>
                <table class="rc-t" data-column-picker="off">
                    <thead><tr><th>Density</th><th class="n">Plot size (ha)</th><th class="n">Creatable</th><th class="n">Plots</th><th class="n">Farmer</th><th class="n">Government</th></tr></thead>
                    <tbody id="rcTable"></tbody>
                </table>
            </div>
        </div>
    </div>
</form>

{{-- Saved calculations --}}
<div class="rc-card" style="margin-top:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
        <h3 style="margin:0;">Saved Calculations</h3>
        <form method="GET" style="display:flex;gap:6px;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Ref or land…" style="{{ $field }}width:200px;" />
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>Ref</th><th>Date</th><th>Land</th><th>Land Use / Purpose / Density</th><th style="text-align:right;">Area (ha)</th><th style="text-align:right;">Roads %</th><th style="text-align:right;">Plots</th><th style="text-align:right;">Farmer / Govt</th></tr></thead>
            <tbody>
                @forelse ($calcs as $c)
                    <tr>
                        <td>{{ $calcs->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $c->calc_ref }}</strong></td>
                        <td>{{ $c->created_at?->format('d M Y H:i') }}</td>
                        <td>{{ $c->land?->case_ref ?? '—' }}</td>
                        <td>{{ $c->land_use }}{{ $c->purpose ? ' · ' . $c->purpose : '' }}{{ $c->density ? ' – ' . $c->density : '' }}<div style="font-size:12px;color:var(--gray-500);">{{ number_format($c->plot_size_ha, 4) }} ha per plot</div></td>
                        <td style="text-align:right;">{{ number_format($c->area_ha, 4) }}</td>
                        <td style="text-align:right;">{{ rtrim(rtrim(number_format($c->deduction_percent, 2), '0'), '.') }}</td>
                        <td style="text-align:right;"><strong>{{ number_format($c->plots) }}</strong><div style="font-size:12px;color:var(--gray-500);">{{ number_format($c->exact_plots, 2) }} · {{ $roundings[$c->rounding] ?? $c->rounding }}</div></td>
                        <td style="text-align:right;">{{ $c->split_applied ? $c->farmer_plots . ' / ' . $c->government_plots : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" style="text-align:center;padding:22px;color:var(--gray-500);">No saved calculations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($calcs->total()) }} saved</span><div class="pagination">{{ $calcs->links() }}</div></div>
</div>

{{-- Plot sizes --}}
<div class="rc-card" style="margin-top:18px;">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:10px;">
        <h3 style="margin:0;">Plot Sizes (by land use &amp; density)</h3>
        <button type="button" class="btn btn-primary btn-sm" @click.stop="rate = { url: @js(route('survey-module.compensation.resettlement-calculator.rates.store')), method: 'POST', title: 'Add Plot Size', land_use: '', purpose: '', density: '', code: '', plot_size_ha: '', source: '' }"><i class="fas fa-plus"></i> Add Plot Size</button>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>Land Use</th><th>Purpose</th><th>Density</th><th>Code</th><th style="text-align:right;">Plot Size (ha)</th><th style="text-align:right;">m²</th><th style="text-align:right;">Plots / ha</th><th>Source</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($rates as $x)
                    <tr @style(['opacity:.55' => ! $x->is_active])>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $x->land_use }}</td>
                        <td>{!! $x->purpose ? e($x->purpose) : '<span style="color:var(--gray-500);">All purposes</span>' !!}</td>
                        <td>{{ $x->density ?: '—' }}</td>
                        <td>{{ $x->code ?: '—' }}</td>
                        <td style="text-align:right;">{{ number_format($x->plot_size_ha, 4) }}</td>
                        <td style="text-align:right;">{{ number_format($x->plot_size_ha * 10000) }}</td>
                        <td style="text-align:right;">{{ number_format(1 / $x->plot_size_ha, 2) }}</td>
                        <td style="font-size:12px;">{{ $x->source ?: '—' }}</td>
                        <td><span class="status-badge {{ $x->is_active ? 'active' : 'archived' }}"><span class="dot"></span>{{ $x->is_active ? 'Active' : 'Retired' }}</span></td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <button type="button" class="{{ $svItem }}"
                                            @click.stop="open = false; rate = @js(['url' => route('survey-module.compensation.resettlement-calculator.rates.update', $x), 'method' => 'PUT', 'title' => 'Edit ' . $x->label,
                                                'land_use' => $x->land_use, 'purpose' => (string) $x->purpose, 'density' => (string) $x->density, 'code' => (string) $x->code, 'plot_size_ha' => (string) $x->plot_size_ha, 'source' => (string) $x->source])">
                                        <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                    </button>
                                    <form method="POST" action="{{ route('survey-module.compensation.resettlement-calculator.rates.toggle', $x) }}">
                                        @csrf
                                        <button type="submit" class="{{ $svItem }}"><i data-lucide="{{ $x->is_active ? 'archive' : 'rotate-ccw' }}" class="w-4 h-4 text-gray-500"></i> {{ $x->is_active ? 'Retire' : 'Restore' }}</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<template x-if="rate">
    <div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="rate = null">
        <form method="POST" :action="rate.url" class="bg-white rounded-lg shadow-xl p-5" style="width:min(520px,94vw);" @click.outside="rate = null">
            @csrf
            <input type="hidden" name="_method" :value="rate.method" />
            <h3 style="margin:0 0 12px;font-size:16px;font-weight:700;" x-text="rate.title"></h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <label style="font-size:13px;">Land Use / Purpose <span class="required">*</span><select name="land_use" required x-model="rate.land_use" @change="rate.purpose = ''" style="{{ $field }}"><option value="">— Select —</option>@foreach (RC::LAND_USES as $lu)<option value="{{ $lu }}">{{ $lu }}</option>@endforeach</select></label>
                <label style="font-size:13px;">Purpose<select name="purpose" x-model="rate.purpose" style="{{ $field }}"><option value="">All purposes</option><template x-for="p in (purposes[rate.land_use] || [])" :key="p"><option :value="p" x-text="p" :selected="p === rate.purpose"></option></template></select></label>
                <label style="font-size:13px;">Density<input type="text" name="density" maxlength="50" x-model="rate.density" style="{{ $field }}" placeholder="e.g. High Density" /></label>
                <label style="font-size:13px;">Code<input type="text" name="code" maxlength="10" x-model="rate.code" style="{{ $field }}" placeholder="e.g. HD" /></label>
                <label style="font-size:13px;">Plot Size (ha) <span class="required">*</span><input type="number" name="plot_size_ha" step="0.0001" min="0.0001" required x-model="rate.plot_size_ha" style="{{ $field }}" /></label>
                <label style="grid-column:1/-1;font-size:13px;">Source<input type="text" name="source" maxlength="255" x-model="rate.source" style="{{ $field }}" placeholder="e.g. SG memo of 29 Oct 2026" /></label>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="rate = null">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
            </div>
        </form>
    </div>
</template>
</div>

<script>
(function () {
    var RATES = @json($ratesJs), LANDS = @json($landMeta), PURPOSES = @json($purposes),
        OLD_RATE = @json((string) old('rate_id')), OLD_PURPOSE = @json((string) old('purpose'));
    var $ = function (id) { return document.getElementById(id); };
    var land = $('rcLand'), area = $('rcArea'), lu = $('rcLandUse'), pur = $('rcPurpose'), dens = $('rcDensities'), rateIn = $('rcRate'),
        deduct = $('rcDeduct'), round = $('rcRound'), split = $('rcSplit');
    var filledArea = null;
    var nf = function (n, d) { return Number(n).toLocaleString(undefined, { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); };

    // Mirrors App\Services\Survey\ResettlementCalculator::calculate(); the server recomputes on save.
    function calc(a, size, pct, mode, doSplit) {
        pct = Math.max(0, Math.min(100, pct || 0));
        var net = a * (1 - pct / 100), exact = net / size;
        var plots = mode === 'up' ? Math.ceil(exact - 1e-6) : (mode === 'nearest' ? Math.round(exact) : Math.floor(exact + 1e-6));
        plots = Math.max(0, plots);
        return { net: net, exact: exact, plots: plots, farmer: doSplit ? Math.floor(plots / 2) : null, govt: doSplit ? Math.ceil(plots / 2) : null };
    }

    function splitOn() {
        var m = LANDS[land.value];
        return m ? !!m.plotForPlot : split.checked;
    }

    // The densities offered for the chosen purpose: its own plot sizes, or else the land use's general ones (no purpose).
    function ratesFor() {
        if (!lu.value || !pur.value) return [];
        var same = function (a, b) { return String(a || '').toUpperCase() === String(b || '').toUpperCase(); };
        var own = RATES.filter(function (r) { return r.landUse === lu.value && r.purpose && same(r.purpose, pur.value); });
        return own.length ? own : RATES.filter(function (r) { return r.landUse === lu.value && !r.purpose; });
    }

    // Land Use -> its purposes; a purpose from the previous land use is cleared.
    function purposes() {
        var keep = pur.value;
        pur.innerHTML = '<option value="">Select Purpose</option>' + (PURPOSES[lu.value] || []).map(function (p) {
            return '<option value="' + p.replace(/"/g, '&quot;') + '">' + p.replace(/</g, '&lt;') + '</option>';
        }).join('');
        pur.disabled = !lu.value;
        if (keep && (PURPOSES[lu.value] || []).indexOf(keep) !== -1) pur.value = keep;
    }

    function densities() {
        $('rcDensityBox').style.display = pur.value ? '' : 'none';
        var list = ratesFor();
        if (!list.some(function (r) { return String(r.id) === rateIn.value; })) rateIn.value = list.length ? String(list[0].id) : '';
        dens.innerHTML = list.map(function (r) {
            var on = String(r.id) === rateIn.value;
            return '<label class="' + (on ? 'on' : '') + '"><input type="radio" name="_density" value="' + r.id + '"' + (on ? ' checked' : '') + '> ' +
                (r.code ? r.code + ' – ' : '') + (r.density || r.landUse) + '</label>';
        }).join('') || '<span class="helper-text">No plot size has been set for ' + pur.value + ' yet, so plots cannot be counted. ' +
            '<a href="#" id="rcAddRate">Add the SG plot size</a>.</span>';
        var add = $('rcAddRate');
        if (add) add.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); window.dispatchEvent(new CustomEvent('rc-add-rate', { detail: { landUse: lu.value, purpose: pur.value } })); });
        dens.querySelectorAll('input').forEach(function (i) { i.addEventListener('change', function () { rateIn.value = i.value; densities(); render(); }); });
    }

    function render() {
        var a = parseFloat(area.value), pct = parseFloat(deduct.value) || 0, mode = round.value, doSplit = splitOn();
        var list = ratesFor();
        $('rcLuName').textContent = pur.value || '—';
        $('rcShares').style.display = doSplit ? '' : 'none';
        $('rcTable').innerHTML = list.map(function (r) {
            var res = a > 0 ? calc(a, r.size, pct, mode, doSplit) : null;
            return '<tr class="' + (String(r.id) === rateIn.value ? 'on' : '') + '"><td>' + (r.code ? r.code + ' – ' : '') + (r.density || '') + '</td>' +
                '<td class="n">' + nf(r.size, 4) + '</td><td class="n">' + (res ? nf(res.exact, 2) : '—') + '</td><td class="n">' + (res ? nf(res.plots) : '—') + '</td>' +
                '<td class="n">' + (res && doSplit ? nf(res.farmer) : '—') + '</td><td class="n">' + (res && doSplit ? nf(res.govt) : '—') + '</td></tr>';
        }).join('');
        var r = RATES.find(function (x) { return String(x.id) === rateIn.value; });
        if (!r || !(a > 0)) {
            $('rcPlots').textContent = '—'; $('rcExact').textContent = 'Enter the area and choose a density.'; $('rcFormula').textContent = '';
            return;
        }
        var res = calc(a, r.size, pct, mode, doSplit);
        $('rcPlots').textContent = nf(res.plots);
        $('rcExact').textContent = nf(res.exact, 2) + ' before ' + round.options[round.selectedIndex].text.toLowerCase();
        $('rcFarmer').textContent = nf(res.farmer); $('rcGovt').textContent = nf(res.govt);
        $('rcFormula').textContent = (pct ? nf(a, 4) + ' ha × (1 − ' + pct + '%) = ' + nf(res.net, 4) + ' ha;  ' : '') +
            nf(res.net, 4) + ' ha ÷ ' + nf(r.size, 4) + ' ha = ' + nf(res.exact, 2) + ' → ' + res.plots + ' plots';
    }

    // Land → area and scheme (rule 5): fill from the record, clear what the last pick filled.
    function pickLand() {
        var m = LANDS[land.value];
        if (filledArea !== null && area.value === String(filledArea)) area.value = '';
        filledArea = null;
        if (m && m.area !== null && !area.value) { area.value = m.area; filledArea = String(m.area); }
        $('rcAreaNote').style.display = filledArea !== null ? '' : 'none';
        $('rcLandInfo').textContent = m ? [m.project, m.farmers ? 'Farmer: ' + m.farmers : null, m.place].filter(Boolean).join(' · ') : '';
        $('rcSplitBox').style.display = m ? 'none' : '';
        var fixed = $('rcSplitFixed');
        fixed.style.display = m ? '' : 'none';
        fixed.textContent = m ? (m.plotForPlot ? 'Land-to-Land land: the 50:50 split applies.' : 'Monetary Compensation land: no plot split.') : '';
        render();
    }

    if (OLD_RATE) { var o = RATES.find(function (r) { return String(r.id) === OLD_RATE; }); if (o) { lu.value = o.landUse; rateIn.value = OLD_RATE; } }
    purposes(); if (OLD_PURPOSE) pur.value = OLD_PURPOSE;
    lu.addEventListener('change', function () { rateIn.value = ''; purposes(); densities(); render(); });
    pur.addEventListener('change', function () { rateIn.value = ''; densities(); render(); });
    [area, deduct, round].forEach(function (el) { el.addEventListener('input', render); el.addEventListener('change', render); });
    split.addEventListener('change', render);
    land.addEventListener('change', pickLand);
    densities(); pickLand();
})();
</script>
@endsection
