@include('cadastral_module.partials._flash')

@php
    $isEdit  = (bool) $record;
    $action  = $isEdit
        ? route('cadastral-module.plan-description.area.update', $record)
        : route('cadastral-module.plan-description.area.store');
    $chartOk = $chart && ($chartArea !== null || $chart->area_sqm !== null);

    // The box shows the stored figure in m²; a chart-sourced area keeps its tick.
    // A chart-sourced area whose chart has since lost its ring falls back to
    // the box, so saving without looking does not clear it.
    $fromChart = (bool) old('area_from_chart', $isEdit && in_array($record->area_source, ['coordinates', 'survey_plan'], true))
        && $chartOk;
    $typed     = old('area_value', $isEdit && ! $fromChart ? $record->area_sqm : null);

    // Existing pillars, or six blank rows (four government, two private) to key into.
    $rows = $pillars->isNotEmpty()
        ? $pillars->map(fn ($p) => $p->only(['pillar_number', 'ownership', 'pillar_type', 'northing', 'easting', 'latitude', 'longitude', 'condition']))->all()
        : array_merge(
            array_fill(0, 4, ['ownership' => 'government']),
            array_fill(0, 2, ['ownership' => 'private'])
        );
    $rows = old('pillars') ? array_values(array_filter(old('pillars'), fn ($r) => is_array($r) && count($r) > 1)) : $rows;
@endphp

<div class="unit-tag"><i class="fas fa-ruler-combined"></i> 4.4 · Plan and Description · Area &amp; Pillars</div>

<div class="page-header">
    <div>
        @if ($isEdit)
            <strong>{{ $record->pd_ref }}</strong> · {{ $record->file_number }}
        @endif
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if ($isEdit)
            <a href="{{ route('cadastral-module.plan-description.area') }}" class="btn btn-outline btn-sm">
                <i class="fas fa-folder-open"></i> Another File
            </a>
            <a href="{{ route('cadastral-module.plan-description.descriptions', ['record' => $record->id]) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-lines"></i> Next: Description
            </a>
        @endif
        <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-outline btn-sm">
            <i class="fas fa-list"></i> Register
        </a>
    </div>
</div>

<div class="caveat">
    <i class="fas fa-circle-info"></i>
    <div>
        <strong>Where the area comes from.</strong>
        Type it in square metres, hectares or acres, or take it from the file's current chart. A chart
        area is computed from its beacon ring by the shoelace formula, the arithmetic of a traverse
        sheet; nothing here reads an area out of a scanned chart or a CAD file. Whichever you use is
        recorded, so a typed figure is never mistaken for a computed one.
    </div>
</div>

{{-- ===================== The file and its area ===================== --}}
<form method="POST" action="{{ $action }}" class="form-container" id="pnd-area-form"
      data-picked="{{ $picked ? json_encode($picked) : '' }}"
      data-factors='@json($factors)'
      data-preview-url="{{ route('cadastral-module.plan-description.area.preview') }}"
      data-chart-area="{{ $chartArea ?? ($chart?->area_sqm ?? '') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="card-header">
        <strong>{{ $isEdit ? 'File and Area' : 'Choose a File' }}</strong>
        <span class="helper-text" style="margin:0;">
            @if ($isEdit)
                File number, owner and location came from the intake record.
            @else
                Registered intake files only. Owner and location come from the intake record and cannot be typed here.
            @endif
        </span>
    </div>

    <div class="form-body">
        <div class="form-grid">
            @if ($isEdit)
                <div class="form-group">
                    <label>File No</label>
                    <input type="text" value="{{ $record->file_number }}" readonly class="intake-locked" />
                </div>
            @else
                <div class="form-group" style="grid-column:1/-1;">
                    <label>File No <span class="required">*</span></label>
                    {{-- Only an intake receipt id can be posted: there is no free-text number. --}}
                    <select name="cadastral_file_receipt_id" id="pnd-file" required
                            data-url="{{ route('cadastral-module.plan-description.intake-files') }}"
                            data-placeholder="Type at least 2 characters of the file number or receipt ref…">
                        <option value=""></option>
                        @if ($picked)
                            <option value="{{ $picked['id'] }}" selected>{{ $picked['text'] }}</option>
                        @endif
                    </select>
                    <div class="helper-text">A file that already has a record opens that record instead.</div>
                    <div id="pnd-file-notes" style="margin-top:6px;display:flex;gap:8px;flex-wrap:wrap;"></div>
                </div>
            @endif

            <div class="form-group">
                <label>Name / Owner</label>
                <input type="text" id="pnd-owner" readonly class="intake-locked"
                       value="{{ $record?->file_title }}" placeholder="From the intake record" />
            </div>
            <div class="form-group">
                <label>Location</label>
                {{-- District, LGA, State. Never the plot. --}}
                <input type="text" id="pnd-location" readonly class="intake-locked"
                       value="{{ $record?->property_location }}" placeholder="District, LGA, State" />
            </div>
            <div class="form-group">
                <label>Plot</label>
                <input type="text" id="pnd-plot" readonly class="intake-locked"
                       value="{{ $record ? ($record->chart?->plot_no ?: $record->prop_plot) : '' }}" />
            </div>
            <div class="form-group">
                <label>Chart</label>
                <input type="text" id="pnd-chart" readonly class="intake-locked"
                       value="{{ $chart ? $chart->chart_ref . ' · ' . $chartPoints . ' beacon(s) with coordinates' : ($isEdit ? 'Not charted' : '') }}" />
            </div>

            <div class="form-group">
                <label>Land Use</label>
                <select name="land_use" id="pnd-land-use">
                    <option value="">—</option>
                    @foreach (\App\Models\Cadastral\CadastralPlanDescription::LAND_USES as $u)
                        <option value="{{ $u }}" @selected(old('land_use', $record?->land_use) === $u)>{{ $u }}</option>
                    @endforeach
                </select>
                <div class="helper-text">Read from the file number when the record starts; change it if it is wrong.</div>
            </div>
            <div class="form-group">
                <label>Location Zone</label>
                <select name="location_zone">
                    <option value="">—</option>
                    @foreach (\App\Models\Cadastral\CadastralPlanDescription::ZONES as $k => $label)
                        <option value="{{ $k }}" @selected(old('location_zone', $record?->location_zone) === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-grid" style="margin-top:6px;">
            <div class="form-group">
                <label>Area</label>
                <input type="number" step="any" min="0" name="area_value" id="area-value" value="{{ $typed }}"
                       placeholder="e.g. 4500" @disabled($fromChart) />
                <div class="helper-text">Leave blank to clear the area.</div>
            </div>
            <div class="form-group">
                <label>Unit</label>
                <select name="area_unit" id="area-unit">
                    @foreach ($units as $k => $label)
                        <option value="{{ $k }}" @selected(old('area_unit', 'sqm') === $k)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Plot Size (m²)</label>
                <input type="number" step="0.01" min="1" name="plot_size_sqm" id="plot-size"
                       value="{{ old('plot_size_sqm', $plotSize) }}" />
                <div class="helper-text">The standard plot from Configurable Entries, kept on the record.</div>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
                    <input type="checkbox" name="area_from_chart" value="1" id="use-chart"
                           @checked($fromChart && $chartOk) @disabled(! $chartOk) style="width:auto;" />
                    Use the chart area
                </label>
                <div class="helper-text" id="use-chart-help">
                    @if ($chartOk)
                        {{ number_format($chartArea ?? $chart->area_sqm, 2) }} m² from {{ $chart->chart_ref }}.
                    @elseif ($isEdit)
                        No current chart with a usable beacon ring.
                    @else
                        Offered once the file is picked, when it has a charted beacon ring.
                    @endif
                </div>
            </div>
        </div>

        {{-- Live conversion of whatever is in the box; the server converts again on save. --}}
        <div class="calc-grid" style="margin-top:6px;" id="area-live">
            <div class="calc-card"><div class="kpi-label">Square Metres</div><div class="kpi-value" data-out="sqm">—</div></div>
            <div class="calc-card"><div class="kpi-label">Hectares</div><div class="kpi-value" data-out="ha">—</div></div>
            <div class="calc-card"><div class="kpi-label">Acres</div><div class="kpi-value" data-out="acres">—</div></div>
            <div class="calc-card">
                <div class="kpi-label">Plots</div><div class="kpi-value" data-out="plots">—</div>
                <div class="helper-text">at <span data-out="plot-size">{{ number_format($plotSize, 0) }}</span> m² per plot</div>
            </div>
        </div>
    </div>

    <div class="form-actions">
        @if ($isEdit)
            @canDo('Cad - Records', 'edit')
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Area</button>
            @endcanDo
        @else
            @canDo('Cad - Records', 'create')
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Start Record</button>
            @endcanDo
        @endif
    </div>
</form>

@if ($isEdit)
    {{-- ===================== Results ===================== --}}
    <div class="form-container" style="margin-top:22px;">
        <div class="card-header">
            <strong>Results</strong>
            <span class="helper-text" style="margin:0;">
                Saved area: {{ $record->area_sqm !== null ? ucfirst(str_replace('_', ' ', (string) $record->area_source)) : 'none recorded' }}
            </span>
        </div>
        <div class="form-body">
            <div class="calc-grid">
                <div class="calc-card">
                    <div class="kpi-label">Square Metres</div>
                    <div class="kpi-value">{{ $areas['sqm'] !== null ? number_format($areas['sqm'], 2) : '—' }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Hectares</div>
                    <div class="kpi-value">{{ $areas['hectares'] !== null ? number_format($areas['hectares'], 4) : '—' }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Acres</div>
                    <div class="kpi-value">{{ $areas['acres'] !== null ? number_format($areas['acres'], 2) : '—' }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Plots</div>
                    <div class="kpi-value">{{ $areas['plots'] !== null ? number_format($areas['plots'], 2) : '—' }}</div>
                    <div class="helper-text">at {{ number_format($plotSize, 0) }} m² per plot</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Government Pillars</div>
                    <div class="kpi-value">{{ $check['government'] }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Private Pillars</div>
                    <div class="kpi-value">{{ $check['private'] }}</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Enclosed by Pillars</div>
                    <div class="kpi-value">{{ $check['ring_sqm'] !== null ? number_format($check['ring_sqm'], 2) : '—' }}</div>
                    <div class="helper-text">m², from {{ $check['with_coordinates'] }} pillar(s) with coordinates</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Perimeter</div>
                    <div class="kpi-value">{{ $check['perimeter_m'] !== null ? number_format($check['perimeter_m'], 2) : '—' }}</div>
                    <div class="helper-text">metres, round the pillars</div>
                </div>
            </div>

            @if ($check['notes'] !== [])
                <div class="caveat" style="margin-top:14px;">
                    <i class="fas fa-triangle-exclamation"></i>
                    <div>
                        <strong>Boundary checks.</strong>
                        @foreach ($check['notes'] as $note) {{ $note }} @endforeach
                    </div>
                </div>
            @elseif ($check['ring_sqm'] !== null)
                <div class="helper-text" style="margin-top:10px;">
                    <i class="fas fa-check"></i> The pillars enclose the recorded area to within 1%.
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== Pillars ===================== --}}
    <div class="form-container" style="margin-top:22px;" id="pillars">
        <div class="card-header">
            <strong>Government and Private Pillars</strong>
            <span class="helper-text" style="margin:0;">
                Key them in survey order round the boundary, so the area they enclose can be checked. Saving
                replaces the whole list.
            </span>
        </div>

        <form method="POST" action="{{ route('cadastral-module.plan-description.pillars.save', $record) }}">
            @csrf
            <div class="table-scroll">
                <table class="coord-table">
                    <thead>
                        <tr>
                            <th style="width:34px;">#</th>
                            <th>Pillar No.</th>
                            <th>Ownership</th>
                            <th>Type</th>
                            <th>Easting</th>
                            <th>Northing</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Condition</th>
                            <th style="width:34px;"></th>
                        </tr>
                    </thead>
                    <tbody id="pillar-body">
                        <tr data-row-template style="display:none;">
                            <td></td>
                            <td><input type="text" name="pillars[][pillar_number]" maxlength="50" /></td>
                            <td>
                                <select name="pillars[][ownership]">
                                    <option value="government">Government</option>
                                    <option value="private">Private</option>
                                </select>
                            </td>
                            <td><input type="text" name="pillars[][pillar_type]" maxlength="50" /></td>
                            <td><input type="number" step="0.001" name="pillars[][easting]" /></td>
                            <td><input type="number" step="0.001" name="pillars[][northing]" /></td>
                            <td><input type="number" step="0.0000001" name="pillars[][latitude]" /></td>
                            <td><input type="number" step="0.0000001" name="pillars[][longitude]" /></td>
                            <td>
                                <select name="pillars[][condition]">
                                    <option value="">—</option>
                                    @foreach (\App\Models\Cadastral\CadastralPillar::CONDITIONS as $c)
                                        <option value="{{ $c }}">{{ $c }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><button type="button" class="btn btn-outline btn-xs" data-remove-row title="Remove"><i class="fas fa-times"></i></button></td>
                        </tr>

                        @foreach ($rows as $i => $p)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><input type="text" name="pillars[{{ $i }}][pillar_number]" maxlength="50" value="{{ $p['pillar_number'] ?? '' }}" /></td>
                                <td>
                                    <select name="pillars[{{ $i }}][ownership]">
                                        <option value="government" @selected(($p['ownership'] ?? '') === 'government')>Government</option>
                                        <option value="private" @selected(($p['ownership'] ?? '') === 'private')>Private</option>
                                    </select>
                                </td>
                                <td><input type="text" name="pillars[{{ $i }}][pillar_type]" maxlength="50" value="{{ $p['pillar_type'] ?? '' }}" /></td>
                                <td><input type="number" step="0.001" name="pillars[{{ $i }}][easting]" value="{{ $p['easting'] ?? '' }}" /></td>
                                <td><input type="number" step="0.001" name="pillars[{{ $i }}][northing]" value="{{ $p['northing'] ?? '' }}" /></td>
                                <td><input type="number" step="0.0000001" name="pillars[{{ $i }}][latitude]" value="{{ $p['latitude'] ?? '' }}" /></td>
                                <td><input type="number" step="0.0000001" name="pillars[{{ $i }}][longitude]" value="{{ $p['longitude'] ?? '' }}" /></td>
                                <td>
                                    <select name="pillars[{{ $i }}][condition]">
                                        <option value="">—</option>
                                        @foreach (\App\Models\Cadastral\CadastralPillar::CONDITIONS as $c)
                                            <option value="{{ $c }}" @selected(($p['condition'] ?? '') === $c)>{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-outline btn-xs" data-remove-row title="Remove"><i class="fas fa-times"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <span class="helper-text" style="margin:0 auto 0 0;">
                    A row with neither a number nor coordinates is ignored.
                </span>
                <button type="button" class="btn btn-outline btn-sm" data-add-row="#pillar-body">
                    <i class="fas fa-plus"></i> Add a Pillar
                </button>
                @canDo('Cad - Records', 'edit')
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Pillars</button>
                @endcanDo
            </div>
        </form>
    </div>
@else
    {{-- ===================== Recent records ===================== --}}
    <div class="table-wrapper" style="margin-top:22px;">
        <div class="card-header" style="padding:12px 16px;"><strong>Recently Started</strong></div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Ref</th><th>File Number</th><th>Owner</th><th>Area (m²)</th><th>Hectares</th><th>Pillars</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($recent as $r)
                        <tr>
                            <td><strong>{{ $r->pd_ref }}</strong></td>
                            <td>{{ $r->file_number }}</td>
                            <td>{{ $r->file_title ?: '—' }}</td>
                            <td class="money">{{ $r->area_sqm_display }}</td>
                            <td class="money">{{ $r->area_ha !== null ? number_format($r->area_ha, 4) : '—' }}</td>
                            <td>{{ $r->pillars_count }}</td>
                            <td>
                                <a href="{{ route('cadastral-module.plan-description.area', ['record' => $r->id]) }}" class="btn btn-outline btn-xs">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center;padding:22px;color:var(--gray-500);">No records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<style>
    .survey-proto .intake-locked { background: var(--gray-100); color: var(--gray-700); }
</style>

{{--
    Picker and live conversion.

    Picking a receipt fills the read-only boxes; a file that already has a
    record opens it. The conversion reads the same factors the server uses,
    passed in data-factors, and is a preview: the server converts again.

    Runs after DOMContentLoaded plus a tick, because Select2 is pushed to the
    page footer after this partial.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var form = document.getElementById('pnd-area-form');
    if (!form) return;

    var $       = window.jQuery;
    var hasS2   = !!($ && $.fn && $.fn.select2);
    var factors = JSON.parse(form.dataset.factors || '{}');
    var value   = document.getElementById('area-value');
    var unit    = document.getElementById('area-unit');
    var plot    = document.getElementById('plot-size');
    var useChart = document.getElementById('use-chart');
    var chartHelp = document.getElementById('use-chart-help');
    var chartArea = form.dataset.chartArea === '' ? null : parseFloat(form.dataset.chartArea);

    function out(key, text) {
        document.querySelectorAll('#area-live [data-out="' + key + '"]').forEach(function (el) { el.textContent = text; });
    }

    function fmt(n, dp) {
        return n.toLocaleString(undefined, { minimumFractionDigits: dp, maximumFractionDigits: dp });
    }

    function recalc() {
        var sqm = null;

        if (useChart && useChart.checked) {
            sqm = chartArea;
        } else if (value.value !== '' && !isNaN(parseFloat(value.value))) {
            sqm = parseFloat(value.value) * (factors[unit.value] || 1);
        }

        var size = parseFloat(plot.value) || 0;
        out('plot-size', size ? fmt(size, 0) : '—');

        if (sqm === null) { ['sqm', 'ha', 'acres', 'plots'].forEach(function (k) { out(k, '—'); }); return; }

        out('sqm', fmt(sqm, 2));
        out('ha', fmt(sqm / factors.ha, 4));
        out('acres', fmt(sqm / factors.acres, 2));
        out('plots', size > 0 ? fmt(sqm / size, 2) : '—');
    }

    [value, unit, plot].forEach(function (el) { if (el) el.addEventListener('input', recalc); });
    if (unit) unit.addEventListener('change', recalc);
    if (useChart) useChart.addEventListener('change', function () {
        value.disabled = useChart.checked;
        recalc();
    });

    function set(id, v) { var el = document.getElementById(id); if (el) el.value = v || ''; }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // A picked file's chart area, from the same endpoint the old screen used.
    function loadChart(chart) {
        chartArea = null;
        if (useChart) { useChart.checked = false; useChart.disabled = true; value.disabled = false; }
        if (!chart) {
            set('pnd-chart', 'Not charted');
            if (chartHelp) chartHelp.textContent = 'This file has no current chart. Type the area.';
            recalc();
            return;
        }
        set('pnd-chart', chart.ref + ' · ' + chart.coordinates + ' beacon(s)');
        fetch(form.dataset.previewUrl + '?chart=' + encodeURIComponent(chart.id), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok && data.areas && data.areas.sqm !== null) {
                    chartArea = data.areas.sqm;
                    if (useChart) useChart.disabled = false;
                    if (chartHelp) chartHelp.textContent = fmt(chartArea, 2) + ' m² from ' + chart.ref + '.';
                } else if (chartHelp) {
                    chartHelp.textContent = chart.ref + ' has fewer than three beacons with coordinates. Type the area.';
                }
                recalc();
            })
            .catch(function () { if (chartHelp) chartHelp.textContent = 'The chart area could not be read. Type the area.'; });
    }

    function apply(item) {
        item = item || {};
        set('pnd-owner', item.owner);
        set('pnd-location', item.location);
        set('pnd-plot', item.plot);

        var landUse = document.getElementById('pnd-land-use');
        if (landUse && item.land_use && !landUse.value) landUse.value = item.land_use;

        var notes = document.getElementById('pnd-file-notes');
        if (notes) {
            var bits = [];
            if (item.type) bits.push('<span style="color:var(--gray-600)">' + esc(item.type) + '</span>');
            if (item.record) bits.push('<span class="status-badge review"><span class="dot"></span>Already on ' + esc(item.record.ref) + ' — opening it</span>');
            notes.innerHTML = bits.join(' ');
        }

        if (item.record && item.record.url) { window.location.href = item.record.url; return; }
        if (item.id) loadChart(item.chart); else recalc();
    }

    var file = document.getElementById('pnd-file');
    if (file && hasS2) {
        var $file = $(file);
        $file.select2({
            width: '100%',
            placeholder: file.dataset.placeholder,
            allowClear: true,
            minimumInputLength: 2,
            ajax: {
                url: file.dataset.url,
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) { return data; }
            },
            templateResult: function (item) {
                if (item.loading || !item.file_number) return item.text;
                return $('<div>').append(
                    $('<div>').text(item.text),
                    $('<div style="font-size:11px;opacity:.7">').text([item.type, item.plot ? 'Plot ' + item.plot : '', item.location, item.chart ? 'Chart ' + item.chart.ref : ''].filter(Boolean).join(' · '))
                );
            }
        });
        $file.on('select2:select', function (e) { apply(e.params.data); });
        $file.on('select2:clear', function () { apply(null); });
    }

    // Re-rendered after a failed start: refill the preview.
    if (form.dataset.picked) {
        try { apply(JSON.parse(form.dataset.picked)); } catch (e) { /* leave it blank */ }
    }

    recalc();
}, 0); });
</script>
