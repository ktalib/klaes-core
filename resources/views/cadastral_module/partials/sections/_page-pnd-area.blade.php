@include('cadastral_module.partials._flash')

@php
    $isEdit  = (bool) $record;
    $action  = $isEdit
        ? route('cadastral-module.plan-description.area.update', $record)
        : route('cadastral-module.plan-description.area.store');
    $chartOk = $chart && ($chartArea !== null || $chart->area_sqm !== null);

    // The picked file as CadastralRegistryLookup::resolveFile() describes it
    // (PlanDescriptionController::area). Its supplied values go on the
    // in-memory model so the fields and the builder render them before the
    // picker locks them; nothing saves.
    $picked = $picked ?? null;
    $form   = $record ?? new \App\Models\Cadastral\CadastralPlanDescription(['prop_state' => 'Kano']);
    if ($picked && $picked['status'] === 'ok') {
        foreach (\App\Services\Cadastral\CadastralRegistryLookup::lockedInput($picked['values'], ['file_title', 'prop_house', 'prop_plot', 'prop_street', 'prop_district', 'prop_lga', 'prop_state']) as $col => $value) {
            $form->{$col} = $value;
        }
    }

    // Land use the file gives is locked; otherwise it is the officer's.
    $luLocked = ($fileLandUse ?? null) !== null;
    $luValue  = $luLocked ? $fileLandUse : old('land_use', $record?->land_use);

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
    $rows = old('pillars_posted') ? array_values(array_filter((array) old('pillars', []), 'is_array')) : $rows;
@endphp


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

@include('cadastral_module.partials._wizard')

{{-- ===================== The wizard: file, details, area, pillars ===================== --}}
<form method="POST" action="{{ $action }}" class="form-container" id="pnd-area-form" data-wizard
      data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate
      data-factors='@json($factors)'
      data-preview-url="{{ route('cadastral-module.plan-description.area.preview') }}"
      data-area-url="{{ route('cadastral-module.plan-description.area') }}"
      data-chart-area="{{ $chartArea ?? ($chart?->area_sqm ?? '') }}">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-stepper" data-wizard-header></div>

    <div class="form-body">

        {{-- 1. The file. Only the global selector can set it, and only a file
             registered at intake is taken. --}}
        <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                 data-subtitle="{{ $isEdit ? 'The file is fixed once the record exists. Another file starts its own record.' : 'Pick a file registered at intake with the file-number selector. A file that already has a record opens that record.' }}">
            <div class="form-grid">
                @include('cadastral_module.partials._file_picker', [
                    'scope'   => 'receipt',
                    'hidden'  => $isEdit ? [] : ['cadastral_file_receipt_id'],
                    'initial' => $picked,
                    'fixed'   => $isEdit,
                    'number'  => $record?->file_number ?? '',
                    'help'    => $isEdit ? null : 'Only a file received and registered at intake can be given an area.',
                ])

                <div class="form-group">
                    <label>Type</label>
                    <input type="text" data-fp-value="type" class="cad-locked" disabled
                           value="{{ $picked['file']['type'] ?? '' }}" placeholder="From the file number" />
                </div>
                <div class="form-group">
                    <label>Chart</label>
                    <input type="text" id="pnd-chart" class="cad-locked" disabled
                           value="{{ $chart ? $chart->chart_ref . ' · ' . $chartPoints . ' beacon(s) with coordinates' : ($isEdit || $picked ? 'Not charted' : '') }}"
                           placeholder="Found from the file" />
                </div>
            </div>
        </section>

        {{-- 2. What the file's records say about it. Supplied values are
             locked; blanks stay open, and the server re-reads the file on save. --}}
        <section class="form-step" data-step data-title="File Details" data-icon="file-text"
                 data-subtitle="Filled from the intake receipt and the file index. Greyed fields come from the file; complete any it leaves blank.">
            <div class="form-grid">
                <div class="form-group">
                    <label>Name / Owner</label>
                    <input type="text" name="file_title" maxlength="500"
                           value="{{ old('file_title', $form->file_title) }}" placeholder="From the intake record" />
                </div>
                <div class="form-group {{ $luLocked ? 'cad-fp-filled' : '' }}">
                    <label>Land Use</label>
                    <select name="land_use" data-land-use-lock
                            @if ($luLocked) disabled class="cad-locked" data-from-file="1" @endif>
                        <option value="">—</option>
                        @foreach (\App\Models\Cadastral\CadastralPlanDescription::LAND_USES as $u)
                            <option value="{{ $u }}" @selected($luValue === $u)>{{ $u }}</option>
                        @endforeach
                    </select>
                    <div class="helper-text">Read from the file when it carries one; otherwise choose it.</div>
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

            {{-- District, LGA and State compose "District, LGA, Kano". The plot
                 number is its own box and never appears in the location. --}}
            @include('cadastral_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $form,
                'legend' => 'Property Location',
            ])
        </section>

        {{-- 3. The area, typed or taken from the chart. --}}
        <section class="form-step" data-step data-title="Area" data-icon="ruler"
                 data-subtitle="Type the area in any unit, or take it from the file's current chart.">
            <div class="caveat">
                <i class="fas fa-circle-info"></i>
                <div>
                    <strong>Where the area comes from.</strong>
                    A chart area is computed from its beacon ring by the shoelace formula, the arithmetic of a
                    traverse sheet; nothing here reads an area out of a scanned chart or a CAD file. Whichever
                    you use is recorded, so a typed figure is never mistaken for a computed one.
                </div>
            </div>

            <div class="form-grid">
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
                    <label>Chart Area</label>
                    <label style="display:flex;align-items:center;gap:8px;font-weight:normal;">
                        <input type="checkbox" name="area_from_chart" value="1" id="use-chart"
                               @checked($fromChart && $chartOk) @disabled(! $chartOk) style="width:auto;" />
                        Use the chart area
                    </label>
                    <div class="helper-text" id="use-chart-help">
                        @if ($chartOk)
                            {{ number_format($chartArea ?? $chart->area_sqm, 2) }} m² from {{ $chart->chart_ref }}.
                        @elseif ($isEdit || $picked)
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

            {{-- Not posted: the line the Review step shows for the area. --}}
            <div class="form-group" style="margin-top:12px;">
                <label>Area in Every Unit</label>
                <input type="text" id="area-summary" class="cad-locked" readonly placeholder="No area entered" />
            </div>
        </section>

        {{-- 4. The pillar schedule. Saved with the area, in survey order. --}}
        <section class="form-step" data-step data-title="Pillars" data-icon="map-pin"
                 data-subtitle="Government and private pillars, keyed in survey order round the boundary so the area they enclose can be checked.">
            <input type="hidden" name="pillars_posted" value="1" />

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
                        {{-- The template: disabled, so it neither posts nor validates. --}}
                        <tr data-row-template style="display:none;">
                            <td data-row-no></td>
                            <td><input type="text" name="pillars[__i__][pillar_number]" maxlength="50" disabled /></td>
                            <td>
                                <select name="pillars[__i__][ownership]" disabled>
                                    <option value="government">Government</option>
                                    <option value="private">Private</option>
                                </select>
                            </td>
                            <td><input type="text" name="pillars[__i__][pillar_type]" maxlength="50" disabled /></td>
                            <td><input type="number" step="any" name="pillars[__i__][easting]" disabled /></td>
                            <td><input type="number" step="any" name="pillars[__i__][northing]" disabled /></td>
                            <td><input type="number" step="any" min="-90" max="90" name="pillars[__i__][latitude]" disabled /></td>
                            <td><input type="number" step="any" min="-180" max="180" name="pillars[__i__][longitude]" disabled /></td>
                            <td>
                                <select name="pillars[__i__][condition]" disabled>
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
                                <td data-row-no>{{ $i + 1 }}</td>
                                <td><input type="text" name="pillars[{{ $i }}][pillar_number]" maxlength="50" value="{{ $p['pillar_number'] ?? '' }}" /></td>
                                <td>
                                    <select name="pillars[{{ $i }}][ownership]">
                                        <option value="government" @selected(($p['ownership'] ?? '') === 'government')>Government</option>
                                        <option value="private" @selected(($p['ownership'] ?? '') === 'private')>Private</option>
                                    </select>
                                </td>
                                <td><input type="text" name="pillars[{{ $i }}][pillar_type]" maxlength="50" value="{{ $p['pillar_type'] ?? '' }}" /></td>
                                <td><input type="number" step="any" name="pillars[{{ $i }}][easting]" value="{{ $p['easting'] ?? '' }}" /></td>
                                <td><input type="number" step="any" name="pillars[{{ $i }}][northing]" value="{{ $p['northing'] ?? '' }}" /></td>
                                <td><input type="number" step="any" min="-90" max="90" name="pillars[{{ $i }}][latitude]" value="{{ $p['latitude'] ?? '' }}" /></td>
                                <td><input type="number" step="any" min="-180" max="180" name="pillars[{{ $i }}][longitude]" value="{{ $p['longitude'] ?? '' }}" /></td>
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

            <div style="display:flex;align-items:center;gap:10px;margin-top:10px;flex-wrap:wrap;">
                <button type="button" class="btn btn-outline btn-sm" data-pillar-add>
                    <i class="fas fa-plus"></i> Add a Pillar
                </button>
                <span class="helper-text" style="margin:0;">
                    A row with neither a number nor coordinates is ignored. A changed schedule replaces the
                    old one, which stays on record.
                </span>
            </div>

            {{-- Not posted: the line the Review step shows for the schedule. --}}
            <div class="form-group" style="margin-top:12px;">
                <label>Pillar Schedule</label>
                <input type="text" id="pillar-summary" class="cad-locked" readonly />
            </div>
        </section>

        <section class="form-step" data-step data-review data-title="Review & Save" data-icon="clipboard-check"
                 data-subtitle="Check everything below. Saving re-reads the file from its receipt, so the greyed values are the file's, whatever this page shows.">
            <div data-wizard-summary></div>
        </section>
    </div>

    <div class="form-actions" data-wizard-nav>
        <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="button" class="btn btn-outline" data-wizard-back><i data-lucide="arrow-left"></i> Back</button>
        <button type="button" class="btn btn-primary" data-wizard-next>Next <i data-lucide="arrow-right"></i></button>
        @canDo('Cad - Records', $isEdit ? 'edit' : 'create')
            <button type="submit" class="btn btn-primary" data-wizard-submit>
                <i data-lucide="{{ $isEdit ? 'save' : 'file-plus-2' }}"></i> {{ $isEdit ? 'Save Area & Pillars' : 'Start Record' }}
            </button>
        @endcanDo
    </div>
</form>

@include('cadastral_module.pnd._pick_hooks')

@if ($isEdit)
    {{-- ===================== Results ===================== --}}
    <div class="form-container" style="margin-top:22px;" id="pillars">
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

{{--
    The picked file's chart, the live conversion and the pillar rows.

    A fresh pick of a file that already has a record opens that record; any
    other pick looks up the file's chart area. The conversion reads the same
    factors the server uses, passed in data-factors, and is a preview: the
    server converts again on save.

    Runs after DOMContentLoaded plus a tick, like the picker and the wizard.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var form = document.getElementById('pnd-area-form');
    if (!form) return;

    var factors   = JSON.parse(form.dataset.factors || '{}');
    var value     = document.getElementById('area-value');
    var unit      = document.getElementById('area-unit');
    var plot      = document.getElementById('plot-size');
    var useChart  = document.getElementById('use-chart');
    var chartHelp = document.getElementById('use-chart-help');
    var chartBox  = document.getElementById('pnd-chart');
    var summary   = document.getElementById('area-summary');
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

        if (sqm === null) {
            ['sqm', 'ha', 'acres', 'plots'].forEach(function (k) { out(k, '—'); });
            summary.value = '';
            return;
        }

        out('sqm', fmt(sqm, 2));
        out('ha', fmt(sqm / factors.ha, 4));
        out('acres', fmt(sqm / factors.acres, 2));
        out('plots', size > 0 ? fmt(sqm / size, 2) : '—');
        summary.value = fmt(sqm, 2) + ' m² · ' + fmt(sqm / factors.ha, 4) + ' ha · ' + fmt(sqm / factors.acres, 2) + ' acres'
            + (size > 0 ? ' · ' + fmt(sqm / size, 2) + ' plots' : '')
            + (useChart && useChart.checked ? ' (from the chart)' : '');
    }

    [value, unit, plot].forEach(function (el) { if (el) el.addEventListener('input', recalc); });
    if (unit) unit.addEventListener('change', recalc);
    if (useChart) useChart.addEventListener('change', function () {
        value.disabled = useChart.checked;
        recalc();
    });

    // A picked file's chart area, from the area preview endpoint.
    function loadChart(chart, picked) {
        chartArea = null;
        if (useChart) { useChart.checked = false; useChart.disabled = true; value.disabled = false; }
        if (!chart) {
            chartBox.value = picked ? 'Not charted' : '';
            if (chartHelp) chartHelp.textContent = picked
                ? 'This file has no current chart. Type the area.'
                : 'Offered once the file is picked, when it has a charted beacon ring.';
            recalc();
            return;
        }
        chartBox.value = chart.ref + (chart.status ? ' (' + chart.status + ')' : '');
        fetch(form.dataset.previewUrl + '?chart=' + encodeURIComponent(chart.id), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
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

    form.addEventListener('cadastral:file-picked', function (e) {
        var p = e.detail;
        if (p && p.initial) return;

        var ok = !!(p && p.status === 'ok');
        var existing = ok && p.records ? p.records.plan_description : null;
        if (existing) {
            // One working record per file (areaStore refuses a second too).
            chartBox.value = 'Already on ' + existing.ref + ' — opening it…';
            window.location.href = form.dataset.areaUrl + '?record=' + encodeURIComponent(existing.id);
            return;
        }
        loadChart(ok && p.records ? p.records.chart : null, ok);
    });

    /* ---- Pillar rows: add, remove, renumber, summarise ---- */
    var body     = document.getElementById('pillar-body');
    var template = body.querySelector('tr[data-row-template]');
    var schedule = document.getElementById('pillar-summary');
    // Indices only ever grow, so an added row never reuses a removed row's name.
    var next = body.querySelectorAll('tr:not([data-row-template])').length;

    function rows() { return Array.prototype.slice.call(body.querySelectorAll('tr:not([data-row-template])')); }

    function field(tr, key) {
        var el = tr.querySelector('[name$="[' + key + ']"]');
        return el ? String(el.value || '').trim() : '';
    }

    function summarise() {
        var govt = 0, priv = 0, coords = 0, numbers = [];
        rows().forEach(function (tr, i) {
            var cell = tr.querySelector('[data-row-no]');
            if (cell) cell.textContent = i + 1;
            var number = field(tr, 'pillar_number');
            var hasXY  = field(tr, 'easting') !== '' && field(tr, 'northing') !== '';
            if (number === '' && !hasXY && field(tr, 'latitude') === '' && field(tr, 'longitude') === '') return;
            if (field(tr, 'ownership') === 'private') priv++; else govt++;
            if (hasXY) coords++;
            if (number) numbers.push(number);
        });
        var total = govt + priv;
        schedule.value = total
            ? total + ' pillar(s): ' + govt + ' government, ' + priv + ' private; ' + coords + ' with eastings and northings'
                + (numbers.length ? ' — ' + numbers.join(', ') : '')
            : 'No pillars entered';
    }

    form.querySelector('[data-pillar-add]').addEventListener('click', function () {
        var clone = template.cloneNode(true);
        clone.removeAttribute('data-row-template');
        clone.style.display = '';
        clone.querySelectorAll('input, select').forEach(function (el) {
            el.name = el.name.replace('__i__', String(next));
            el.disabled = false;
        });
        next++;
        body.appendChild(clone);
        summarise();
        var first = clone.querySelector('input');
        if (first) first.focus();
    });

    // The module's shared handler removes the row; summarise once it has.
    body.addEventListener('click', function (e) {
        if (e.target.closest('[data-remove-row]')) setTimeout(summarise, 0);
    });
    body.addEventListener('input', summarise);
    body.addEventListener('change', summarise);

    summarise();
    recalc();
}, 0); });
</script>
