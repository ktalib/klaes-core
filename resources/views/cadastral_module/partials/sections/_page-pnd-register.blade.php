@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($record->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.plan-description.update', $record)
        : route('cadastral-module.plan-description.store');
    $fees = app(\App\Services\Cadastral\CadastralSettings::class)->feeRates();

    // The picked file as CadastralRegistryLookup::resolveFile() describes it
    // (PlanDescriptionController::create/edit). Its supplied values go on the
    // in-memory model so the fields and the builder render them before the
    // picker locks them; nothing saves.
    $picked = $picked ?? null;
    if ($picked && $picked['status'] === 'ok') {
        foreach (\App\Services\Cadastral\CadastralRegistryLookup::lockedInput($picked['values'], ['file_title', 'prop_house', 'prop_plot', 'prop_street', 'prop_district', 'prop_lga', 'prop_state']) as $col => $value) {
            $record->{$col} = $value;
        }
    }

    // Land use the file gives is locked; otherwise it is the officer's.
    $luLocked = ($fileLandUse ?? null) !== null;
    $luValue  = $luLocked ? $fileLandUse : old('land_use', $record->land_use);

    // The chart and report are found from the file on save, never typed.
    $chart     = $chart ?? null;
    $reportRef = $isEdit ? optional($record->report)->report_ref : ($picked['records']['reports'][0]['ref'] ?? null);
@endphp


<div class="page-header">
    <div>@if ($isEdit) <strong>{{ $record->pd_ref }}</strong> · {{ $record->file_number }} @endif</div>
    <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="caveat">
    <i class="fas fa-circle-info"></i>
    <div>
        <strong>Where the area comes from.</strong>
        It is computed from the chart's beacon ring by the shoelace formula — the same arithmetic as
        a traverse sheet. Nothing here reads an area out of a scanned chart or a CAD file; KLAES has
        no geometry type and no DXF import. A figure typed by hand is kept and recorded as
        hand-entered, so nobody later mistakes it for a computed one.
    </div>
</div>


{{-- Area in every unit --}}
<div class="calc-grid" style="margin-bottom:18px;">
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
        <div class="kpi-value">{{ $areas['acres'] !== null ? number_format($areas['acres'], 4) : '—' }}</div>
    </div>
    <div class="calc-card">
        <div class="kpi-label">Plots</div>
        <div class="kpi-value">{{ $areas['plots'] !== null ? number_format($areas['plots'], 2) : '—' }}</div>
        <div class="helper-text">
            at {{ number_format($record->plot_size_sqm ?: app(\App\Services\Cadastral\CadastralSettings::class)->plotSizeSqm(), 0) }} sqm per plot
        </div>
    </div>
</div>

@include('cadastral_module.partials._wizard')

<form method="POST" action="{{ $action }}" class="form-container" id="pnd-register-form" data-wizard
      data-edit-url="{{ route('cadastral-module.plan-description.edit', '__id__') }}"
      data-wizard-errors="{{ json_encode($errors->keys()) }}" novalidate>
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-stepper" data-wizard-header></div>

    <div class="form-body">

        {{-- 1. The file. Only the global selector can set it, and only a file
             registered at intake is taken. --}}
        <section class="form-step" data-step data-title="Select File" data-icon="folder-search"
                 data-subtitle="{{ $isEdit ? 'The file is fixed once the record exists.' : 'Pick a file registered at intake with the file-number selector. A file that already has a record opens that record.' }}">
            <div class="form-grid">
                @include('cadastral_module.partials._file_picker', [
                    'scope'   => 'receipt',
                    'hidden'  => $isEdit ? [] : ['cadastral_file_receipt_id'],
                    'initial' => $picked,
                    'fixed'   => $isEdit,
                    'number'  => $record->file_number ?? '',
                    'help'    => $isEdit ? null : 'Only a file received and registered at intake can have a plan-description record.',
                ])

                <div class="form-group">
                    <label>Type</label>
                    <input type="text" data-fp-value="type" class="cad-locked" disabled
                           value="{{ $picked['file']['type'] ?? '' }}" placeholder="From the file number" />
                </div>
                <div class="form-group">
                    <label>Chart</label>
                    <input type="text" id="pnd-reg-chart" class="cad-locked" disabled
                           value="{{ $chart ? $chart->chart_ref . ' (v' . $chart->version . ', ' . $chart->status . ')' : ($isEdit || $picked ? 'Not charted' : '') }}"
                           placeholder="Found from the file" />
                    <div class="helper-text">The file's current chart, linked on save. Its beacon ring gives the area when none is typed.</div>
                </div>
                <div class="form-group">
                    <label>Report</label>
                    <input type="text" id="pnd-reg-report" class="cad-locked" disabled
                           value="{{ $reportRef ?: ($isEdit || $picked ? 'None' : '') }}" placeholder="Found from the file" />
                </div>
            </div>
        </section>

        {{-- 2. What the file's records say about it. Supplied values are
             locked; blanks stay open, and the server re-reads the file on save. --}}
        <section class="form-step" data-step data-title="File Details" data-icon="file-text"
                 data-subtitle="Filled from the intake receipt and the file index. Greyed fields come from the file; complete any it leaves blank.">
            <div class="form-grid">
                <div class="form-group">
                    <label>File Title</label>
                    <input type="text" name="file_title" maxlength="500" value="{{ old('file_title', $record->file_title) }}" />
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
                </div>
                <div class="form-group">
                    <label>Location Zone</label>
                    <select name="location_zone">
                        <option value="">—</option>
                        @foreach (\App\Models\Cadastral\CadastralPlanDescription::ZONES as $k => $label)
                            <option value="{{ $k }}" @selected(old('location_zone', $record->location_zone)===$k)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- District, LGA and State compose "District, LGA, Kano". The plot
                 number is its own box and never appears in the location. --}}
            @include('cadastral_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $record,
                'legend' => 'Property Location',
            ])
        </section>

        {{-- 3. Area and the plot standard. --}}
        <section class="form-step" data-step data-title="Area & Plot" data-icon="ruler"
                 data-subtitle="A typed area is kept as hand-entered; a blank one is computed from the chart's beacon ring.">
            <div class="form-grid">
                <div class="form-group">
                    <label>Area (sqm)</label>
                    <input type="number" step="0.001" min="0" name="area_sqm" value="{{ old('area_sqm', $record->area_sqm) }}" />
                    <div class="helper-text">Leave blank to compute it from the linked chart's beacon ring.</div>
                </div>
                <div class="form-group">
                    <label>Plot Size (sqm)</label>
                    <input type="number" step="0.01" min="1" name="plot_size_sqm"
                           value="{{ old('plot_size_sqm', $record->plot_size_sqm ?: app(\App\Services\Cadastral\CadastralSettings::class)->plotSizeSqm()) }}" />
                    <div class="helper-text">Snapshotted, so an old bill reprints the same plot count.</div>
                </div>
                <div class="form-group">
                    <label>Decimal Places</label>
                    <input type="number" min="0" max="6" name="area_precision"
                           value="{{ old('area_precision', $record->area_precision ?: 2) }}" />
                </div>
                <div class="form-group">
                    <label>Description Complexity <span class="required">*</span></label>
                    <select name="description_complexity" required>
                        @foreach (\App\Models\Cadastral\CadastralPlanDescription::COMPLEXITIES as $k => $label)
                            <option value="{{ $k }}" @selected(old('description_complexity', $record->description_complexity ?: 'standard')===$k)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        {{-- 4. What bounds the plot, for the description. --}}
        <section class="form-step" data-step data-title="Boundaries" data-icon="compass"
                 data-subtitle="What lies on each side of the plot. Used when the description is generated.">
            <div class="form-grid">
                <div class="form-group"><label>Bounded North</label>
                    <input type="text" name="boundary_north" maxlength="255" value="{{ old('boundary_north', $record->boundary_north) }}" /></div>
                <div class="form-group"><label>Bounded South</label>
                    <input type="text" name="boundary_south" maxlength="255" value="{{ old('boundary_south', $record->boundary_south) }}" /></div>
                <div class="form-group"><label>Bounded East</label>
                    <input type="text" name="boundary_east" maxlength="255" value="{{ old('boundary_east', $record->boundary_east) }}" /></div>
                <div class="form-group"><label>Bounded West</label>
                    <input type="text" name="boundary_west" maxlength="255" value="{{ old('boundary_west', $record->boundary_west) }}" /></div>
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
                <i data-lucide="{{ $isEdit ? 'save' : 'file-plus-2' }}"></i> {{ $isEdit ? 'Save Record' : 'Create Record' }}
            </button>
        @endcanDo
    </div>
</form>

@include('cadastral_module.pnd._pick_hooks')

{{-- A fresh pick: a file that already has a record opens it; otherwise the
     chart and report boxes follow the file. The server finds both again on save. --}}
<script>
document.addEventListener('DOMContentLoaded', function () { setTimeout(function () {
    'use strict';

    var form = document.getElementById('pnd-register-form');
    if (!form) return;

    form.addEventListener('cadastral:file-picked', function (e) {
        var p = e.detail;
        if (p && p.initial) return;

        var ok  = !!(p && p.status === 'ok' && p.records);
        var rec = ok ? p.records.plan_description : null;
        var chart  = document.getElementById('pnd-reg-chart');
        var report = document.getElementById('pnd-reg-report');

        if (rec) {
            chart.value = 'Already on ' + rec.ref + ' — opening it…';
            window.location.href = form.dataset.editUrl.replace('__id__', encodeURIComponent(rec.id));
            return;
        }

        var c = ok ? p.records.chart : null;
        var r = ok && p.records.reports && p.records.reports.length ? p.records.reports[0] : null;
        chart.value  = c ? c.ref + ' (' + c.status + ')' : (ok ? 'Not charted' : '');
        report.value = r ? r.ref : (ok ? 'None' : '');
    });
}, 0); });
</script>


@if ($isEdit)
    {{-- Pillars --}}
    <div class="form-container" style="margin-top:22px;">
        <div class="card-header">
            <strong>Pillars and Beacons</strong>
            <span class="helper-text" style="margin:0;">
                Government and private pillars are priced the same —
                &#8358;{{ number_format($fees['beacon'], 2) }} each. The split is for the
                bill's breakdown, not a different rate.
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
                            <th>Northing</th>
                            <th>Easting</th>
                            <th>Latitude</th>
                            <th>Longitude</th>
                            <th>Condition</th>
                            <th style="width:34px;"></th>
                        </tr>
                    </thead>
                    <tbody id="pillar-body">
                        <tr data-row-template style="display:none;">
                            <td></td>
                            <td><input type="text" name="pillars[][pillar_number]" /></td>
                            <td>
                                <select name="pillars[][ownership]">
                                    <option value="government">Government</option>
                                    <option value="private">Private</option>
                                </select>
                            </td>
                            <td><input type="text" name="pillars[][pillar_type]" /></td>
                            <td><input type="number" step="0.001" name="pillars[][northing]" /></td>
                            <td><input type="number" step="0.001" name="pillars[][easting]" /></td>
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
                            <td><button type="button" class="btn btn-outline btn-xs" data-remove-row><i class="fas fa-times"></i></button></td>
                        </tr>

                        @forelse ($pillars as $i => $pillar)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><input type="text" name="pillars[{{ $i }}][pillar_number]" value="{{ $pillar->pillar_number }}" /></td>
                                <td>
                                    <select name="pillars[{{ $i }}][ownership]">
                                        <option value="government" @selected($pillar->ownership==='government')>Government</option>
                                        <option value="private" @selected($pillar->ownership==='private')>Private</option>
                                    </select>
                                </td>
                                <td><input type="text" name="pillars[{{ $i }}][pillar_type]" value="{{ $pillar->pillar_type }}" /></td>
                                <td><input type="number" step="0.001" name="pillars[{{ $i }}][northing]" value="{{ $pillar->northing }}" /></td>
                                <td><input type="number" step="0.001" name="pillars[{{ $i }}][easting]" value="{{ $pillar->easting }}" /></td>
                                <td><input type="number" step="0.0000001" name="pillars[{{ $i }}][latitude]" value="{{ $pillar->latitude }}" /></td>
                                <td><input type="number" step="0.0000001" name="pillars[{{ $i }}][longitude]" value="{{ $pillar->longitude }}" /></td>
                                <td>
                                    <select name="pillars[{{ $i }}][condition]">
                                        <option value="">—</option>
                                        @foreach (\App\Models\Cadastral\CadastralPillar::CONDITIONS as $c)
                                            <option value="{{ $c }}" @selected($pillar->condition===$c)>{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><button type="button" class="btn btn-outline btn-xs" data-remove-row><i class="fas fa-times"></i></button></td>
                            </tr>
                        @empty
                            @for ($i = 0; $i < 4; $i++)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><input type="text" name="pillars[{{ $i }}][pillar_number]" /></td>
                                    <td>
                                        <select name="pillars[{{ $i }}][ownership]">
                                            <option value="government">Government</option>
                                            <option value="private">Private</option>
                                        </select>
                                    </td>
                                    <td><input type="text" name="pillars[{{ $i }}][pillar_type]" /></td>
                                    <td><input type="number" step="0.001" name="pillars[{{ $i }}][northing]" /></td>
                                    <td><input type="number" step="0.001" name="pillars[{{ $i }}][easting]" /></td>
                                    <td><input type="number" step="0.0000001" name="pillars[{{ $i }}][latitude]" /></td>
                                    <td><input type="number" step="0.0000001" name="pillars[{{ $i }}][longitude]" /></td>
                                    <td>
                                        <select name="pillars[{{ $i }}][condition]">
                                            <option value="">—</option>
                                            @foreach (\App\Models\Cadastral\CadastralPillar::CONDITIONS as $c)
                                                <option value="{{ $c }}">{{ $c }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn btn-outline btn-xs" data-remove-row><i class="fas fa-times"></i></button></td>
                                </tr>
                            @endfor
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-outline btn-sm" data-add-row="#pillar-body">
                    <i class="fas fa-plus"></i> Add a Pillar
                </button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Pillars</button>
            </div>
        </form>
    </div>

    {{-- The bill lives on the Fee Calculator (Phase 7). The anchor keeps old "#fee-calculator" links landing here. --}}
    <div class="form-container" style="margin-top:22px;" id="fee-calculator">
        <div class="card-header">
            <strong>Cadastral Fees and Area</strong>
            <span class="helper-text" style="margin:0;">
                The bill is worked out line by line from the official fee sheet on the Fee Calculator.
            </span>
        </div>
        <div class="form-actions">
            @if ($bill)
                <span class="status-badge {{ $bill->status_badge }}">
                    <span class="dot"></span>{{ $bill->bill_ref }} · {{ $bill->status }} ·
                    &#8358;{{ number_format($bill->grand_total, 2) }}
                </span>
                @canDo('Cad - Records', 'print')
                    <a href="{{ route('cadastral-module.plan-description.bill.print', [$record, $bill]) }}"
                       target="_blank" class="btn btn-outline btn-sm">
                        <i class="fas fa-print"></i> Print the Bill
                    </a>
                @endcanDo
            @endif
            <a href="{{ route('cadastral-module.plan-description.fees', ['record' => $record->id]) }}" class="btn btn-primary">
                <i class="fas fa-calculator"></i> Open the Fee Calculator
            </a>
        </div>
    </div>

    {{-- The description --}}
    <div class="form-container" style="margin-top:22px;">
        <div class="card-header">
            <strong>Land Description</strong>
            @if ($record->validation_status !== 'unvalidated')
                <span class="status-badge {{ $record->validation_status === 'passed' ? 'active' : 'rejected' }}">
                    <span class="dot"></span>{{ ucfirst($record->validation_status) }}
                </span>
            @endif
        </div>

        @if ($record->validation_notes)
            <div class="caveat"><i class="fas fa-circle-info"></i><div>{{ $record->validation_notes }}</div></div>
        @endif

        <form method="POST" action="{{ route('cadastral-module.plan-description.description.generate', $record) }}"
              style="padding:0 0 10px;">
            @csrf
            <div style="display:flex;gap:8px;align-items:flex-end;padding:0 18px;">
                <div class="form-group" style="margin:0;">
                    <label>Template</label>
                    <select name="template_key">
                        @foreach ($templates as $key => $label)
                            <option value="{{ $key }}" @selected($record->template_key===$key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-wand-magic-sparkles"></i> Generate
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('cadastral-module.plan-description.description.save', $record) }}">
            @csrf @method('PUT')
            <div class="form-body">
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description_body" rows="12"
                              placeholder="Generate from a template above, then edit.">{{ old('description_body', $record->description_body) }}</textarea>
                    <div class="helper-text">
                        Generation is a starting point, not an authority — a description is a legal
                        form of words, so the officer has the last say over it.
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group"><label>Bounded North</label>
                        <input type="text" name="boundary_north" value="{{ old('boundary_north', $record->boundary_north) }}" /></div>
                    <div class="form-group"><label>Bounded South</label>
                        <input type="text" name="boundary_south" value="{{ old('boundary_south', $record->boundary_south) }}" /></div>
                    <div class="form-group"><label>Bounded East</label>
                        <input type="text" name="boundary_east" value="{{ old('boundary_east', $record->boundary_east) }}" /></div>
                    <div class="form-group"><label>Bounded West</label>
                        <input type="text" name="boundary_west" value="{{ old('boundary_west', $record->boundary_west) }}" /></div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save &amp; Check Against the Chart
                </button>
            </div>
        </form>
    </div>
@endif
