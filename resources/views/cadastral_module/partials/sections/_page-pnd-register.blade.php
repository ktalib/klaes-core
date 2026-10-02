@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($record->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.plan-description.update', $record)
        : route('cadastral-module.plan-description.store');
    $fees = config('cadastral_module.fees');
@endphp

<div class="unit-tag"><i class="fas fa-ruler-combined"></i> 4.4 · Plan and Description</div>

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

<form method="POST" action="{{ $action }}" class="form-container">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-body">
        <div class="form-grid">
            <div class="form-group">
                <label>File Number <span class="required">*</span></label>
                <input type="text" name="file_number" value="{{ old('file_number', $record->file_number) }}" required />
            </div>
            <div class="form-group">
                <label>File Title</label>
                <input type="text" name="file_title" value="{{ old('file_title', $record->file_title) }}" />
            </div>
            <div class="form-group">
                <label>Chart ID</label>
                <input type="number" name="cadastral_chart_id" value="{{ old('cadastral_chart_id', $record->cadastral_chart_id) }}" />
                <div class="helper-text">Linking a chart lets the area be computed from its beacon ring.</div>
            </div>
            <div class="form-group">
                <label>Report ID</label>
                <input type="number" name="cadastral_report_id" value="{{ old('cadastral_report_id', $record->cadastral_report_id) }}" />
            </div>
            <div class="form-group">
                <label>Land Use</label>
                <select name="land_use">
                    <option value="">—</option>
                    @foreach (\App\Models\Cadastral\CadastralPlanDescription::LAND_USES as $u)
                        <option value="{{ $u }}" @selected(old('land_use', $record->land_use)===$u)>
                            {{ $u }} (×{{ rtrim(rtrim(number_format($fees['land_use_multiplier'][$u] ?? 1, 2), '0'), '.') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Location Zone</label>
                <select name="location_zone">
                    <option value="">—</option>
                    @foreach (\App\Models\Cadastral\CadastralPlanDescription::ZONES as $k => $label)
                        <option value="{{ $k }}" @selected(old('location_zone', $record->location_zone)===$k)>
                            {{ $label }} (×{{ rtrim(rtrim(number_format($fees['zone_multiplier'][$k] ?? 1, 2), '0'), '.') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Area (sqm)</label>
                <input type="number" step="0.001" name="area_sqm" value="{{ old('area_sqm', $record->area_sqm) }}" />
                <div class="helper-text">Leave blank to compute it from the linked chart's beacon ring.</div>
            </div>
            <div class="form-group">
                <label>Plot Size (sqm)</label>
                <input type="number" step="0.01" name="plot_size_sqm"
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
                            {{ $label }} (&#8358;{{ number_format($fees['description_fee'][$k] ?? 0, 2) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group"><label>Bounded North</label>
                <input type="text" name="boundary_north" value="{{ old('boundary_north', $record->boundary_north) }}" /></div>
            <div class="form-group"><label>Bounded South</label>
                <input type="text" name="boundary_south" value="{{ old('boundary_south', $record->boundary_south) }}" /></div>
            <div class="form-group"><label>Bounded East</label>
                <input type="text" name="boundary_east" value="{{ old('boundary_east', $record->boundary_east) }}" /></div>
            <div class="form-group"><label>Bounded West</label>
                <input type="text" name="boundary_west" value="{{ old('boundary_west', $record->boundary_west) }}" /></div>
        </div>

        @include('cadastral_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $record,
            'legend' => 'Property Location',
        ])
    </div>

    <div class="form-actions">
        <a href="{{ route('cadastral-module.plan-description.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> {{ $isEdit ? 'Save Record' : 'Create Record' }}
        </button>
    </div>
</form>

@if ($isEdit)
    {{-- Pillars --}}
    <div class="form-container" style="margin-top:22px;">
        <div class="card-header">
            <strong>Pillars and Beacons</strong>
            <span class="helper-text" style="margin:0;">
                Government and private pillars are priced the same —
                &#8358;{{ number_format($fees['pillar_unit_price'], 2) }} each. The split is for the
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

    {{-- The bill. The anchor is where "Next: Fee Calculator" lands until Phase 7. --}}
    <div class="form-container" style="margin-top:22px;" id="fee-calculator">
        <div class="card-header">
            <strong>Consolidated Bill</strong>
            <span class="helper-text" style="margin:0;">
                Computed on the server from the stored area and the stored pillar rows. A total posted
                from the browser is discarded.
            </span>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Line</th><th>Basis</th><th class="money">Amount (&#8358;)</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Area charge</td>
                        <td>
                            {{ $areas['sqm'] !== null ? number_format($areas['sqm'], 2) : '—' }} sqm
                            @ {{ number_format($preview['area_rate_per_sqm'], 2) }}
                            × {{ rtrim(rtrim(number_format($preview['land_use_multiplier'], 2), '0'), '.') }} use
                            × {{ rtrim(rtrim(number_format($preview['zone_multiplier'], 2), '0'), '.') }} zone
                        </td>
                        <td class="money">{{ number_format($preview['area_fee'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Pillar verification</td>
                        <td>
                            {{ $preview['pillar_count_government'] }} government +
                            {{ $preview['pillar_count_private'] }} private
                            @ {{ number_format($preview['pillar_unit_price'], 2) }} each
                        </td>
                        <td class="money">{{ number_format($preview['pillar_fee'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Land description</td>
                        <td>{{ ucfirst($record->description_complexity) }}</td>
                        <td class="money">{{ number_format($preview['description_fee_amount'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>Service charge</td>
                        <td>{{ rtrim(rtrim(number_format($preview['service_charge_percent'], 3), '0'), '.') }}% of the above</td>
                        <td class="money">{{ number_format($preview['service_charge'], 2) }}</td>
                    </tr>
                    <tr style="border-top:2px solid var(--gray-400);">
                        <td colspan="2"><strong>Grand Total</strong></td>
                        <td class="money"><strong>{{ number_format($preview['grand_total'], 2) }}</strong></td>
                    </tr>
                </tbody>
            </table>
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

            <form method="POST" action="{{ route('cadastral-module.plan-description.bill.generate', $record) }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-receipt"></i> {{ $bill ? 'Reissue the Bill' : 'Issue the Bill' }}
                </button>
            </form>
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
