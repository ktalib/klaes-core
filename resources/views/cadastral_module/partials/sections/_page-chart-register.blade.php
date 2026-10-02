@include('cadastral_module.partials._flash')

@php
    $isEdit = (bool) ($chart->exists ?? false);
    $action = $isEdit
        ? route('cadastral-module.charting.update', $chart)
        : route('cadastral-module.charting.store');
@endphp

<div class="unit-tag"><i class="fas fa-pen-ruler"></i> 4.3 · Cadastral Information</div>

<div class="page-header">
    <div>
        @if ($isEdit)
            <strong>{{ $chart->chart_ref }}</strong> · version {{ $chart->version }}
            @unless ($chart->is_current)
                <span class="status-badge rejected"><span class="dot"></span>Superseded</span>
            @endunless
        @endif
    </div>
    <a href="{{ route('cadastral-module.charting.index') }}" class="btn btn-outline btn-sm">
        <i class="fas fa-arrow-left"></i> Back to the register
    </a>
</div>

@if ($isEdit && ! $chart->charting_required)
    <div class="caveat">
        <i class="fas fa-circle-info"></i>
        <div>
            <strong>{{ $chart->file_number }} is a conversion file, so it is not charted.</strong>
            Conversion files go straight to index-card commissioning. This record exists so the
            registry can see the decision was made deliberately.
            <a href="{{ route('cadastral-module.index-cards.index') }}">Commission its index card</a>.
        </div>
    </div>
@endif

@if ($isEdit && ! empty($conflicts))
    <div class="caveat" style="border-left-color:var(--danger);background:#fdecea;color:#7f1d1d;">
        <i class="fas fa-triangle-exclamation"></i>
        <div>
            <strong>{{ count($conflicts) }} possible conflict(s).</strong>
            <ul style="margin:6px 0 0 18px;">
                @foreach ($conflicts as $conflict)
                    <li>
                        <em>{{ $conflict['type'] === 'identity' ? 'Identity' : 'Geometric' }}:</em>
                        {{ $conflict['reason'] }}
                        <a href="{{ route('cadastral-module.charting.edit', $conflict['chart']) }}">
                            {{ $conflict['chart']->chart_ref }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="form-container">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    <div class="form-body">
        <fieldset style="border:none;padding:0;margin:0 0 18px;">
            <div class="ab-legend">The Parcel</div>

            <div class="form-grid">
                <div class="form-group">
                    <label>File Number <span class="required">*</span></label>
                    <input type="text" name="file_number" value="{{ old('file_number', $chart->file_number) }}"
                           placeholder="e.g. RES-1981-1" required />
                    <div class="helper-text">
                        A <code>CON-</code> prefix marks the chart as a conversion file and switches
                        charting off — the category is read from the number, not chosen here.
                    </div>
                </div>

                <div class="form-group">
                    <label>File Title</label>
                    <input type="text" name="file_title" value="{{ old('file_title', $chart->file_title) }}" />
                </div>

                <div class="form-group">
                    <label>Plot Number</label>
                    <input type="text" name="plot_no" value="{{ old('plot_no', $chart->plot_no) }}" />
                </div>

                <div class="form-group">
                    <label>Block Number</label>
                    <input type="text" name="block_no" value="{{ old('block_no', $chart->block_no) }}" />
                </div>

                <div class="form-group">
                    <label>Layout Name</label>
                    <input type="text" name="layout_name" value="{{ old('layout_name', $chart->layout_name) }}" />
                </div>

                <div class="form-group">
                    <label>Approved Plan No.</label>
                    <input type="text" name="approved_plan_no" value="{{ old('approved_plan_no', $chart->approved_plan_no) }}" />
                </div>

                <div class="form-group">
                    <label>TP Plan No.</label>
                    <input type="text" name="tp_plan_no" value="{{ old('tp_plan_no', $chart->tp_plan_no) }}" />
                </div>

                <div class="form-group">
                    <label>Scheme Plan No.</label>
                    <input type="text" name="scheme_plan_no" value="{{ old('scheme_plan_no', $chart->scheme_plan_no) }}" />
                </div>
            </div>
        </fieldset>

        <fieldset style="border:none;padding:0;margin:0 0 18px;">
            <div class="ab-legend">Sheets and Charting</div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Metric Sheet Index</label>
                    <input type="text" name="sheet_metric_index" value="{{ old('sheet_metric_index', $chart->sheet_metric_index) }}" />
                </div>
                <div class="form-group">
                    <label>Metric Sheet No.</label>
                    <input type="text" name="sheet_metric_no" value="{{ old('sheet_metric_no', $chart->sheet_metric_no) }}" />
                </div>
                <div class="form-group">
                    <label>Imperial Sheet</label>
                    <input type="text" name="sheet_imperial" value="{{ old('sheet_imperial', $chart->sheet_imperial) }}" />
                </div>
                <div class="form-group">
                    <label>Imperial Sheet No.</label>
                    <input type="text" name="sheet_imperial_no" value="{{ old('sheet_imperial_no', $chart->sheet_imperial_no) }}" />
                </div>
                <div class="form-group">
                    <label>Chart Officer</label>
                    <input type="text" name="chart_officer_name" value="{{ old('chart_officer_name', $chart->chart_officer_name) }}" />
                </div>
                <div class="form-group">
                    <label>Charted On</label>
                    <input type="date" name="charted_on" value="{{ old('charted_on', optional($chart->charted_on)->format('Y-m-d')) }}" />
                </div>
                <div class="form-group">
                    <label>Manual Chart (scan path)</label>
                    <input type="text" name="manual_chart_path" value="{{ old('manual_chart_path', $chart->manual_chart_path) }}"
                           placeholder="Trimsheet / topsheet scan" />
                </div>
                <div class="form-group">
                    <label>Digital Chart (path)</label>
                    <input type="text" name="digital_chart_path" value="{{ old('digital_chart_path', $chart->digital_chart_path) }}" />
                </div>
                <div class="form-group">
                    <label>Area (sqm)</label>
                    <input type="number" step="0.001" name="area_sqm" value="{{ old('area_sqm', $chart->area_sqm) }}" />
                    <div class="helper-text">
                        Leave blank to use the area computed from the beacon ring below.
                        A figure typed here is recorded as hand-entered.
                    </div>
                </div>
                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        @foreach (\App\Models\Cadastral\CadastralChart::STATUSES as $s)
                            <option value="{{ $s }}" @selected(old('status', $chart->status)===$s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Conflict Status</label>
                    <select name="conflict_status">
                        @foreach (['none' => 'None', 'suspected' => 'Suspected', 'confirmed' => 'Confirmed', 'cleared' => 'Cleared'] as $k => $label)
                            <option value="{{ $k }}" @selected(old('conflict_status', $chart->conflict_status)===$k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label>Conflict Note</label>
                    <input type="text" name="conflict_note" value="{{ old('conflict_note', $chart->conflict_note) }}" />
                </div>
            </div>
        </fieldset>

        {{-- GIS is linked to, not drawn here (plan Q6): the chart points at the
             capture record it mirrors, and that record opens in its own screen. --}}
        <fieldset style="border:none;padding:0;margin:0 0 18px;">
            <div class="ab-legend">GIS Record</div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Mirrors</label>
                    <select name="gis_origin">
                        <option value="">— Not linked —</option>
                        <option value="gisCapture" @selected(old('gis_origin', $chart->origin) === 'gisCapture')>GIS capture record</option>
                        <option value="surveyCadastral" @selected(old('gis_origin', $chart->origin) === 'surveyCadastral')>Survey cadastral record</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Record ID</label>
                    <input type="number" min="1" name="gis_origin_id"
                           value="{{ old('gis_origin_id', in_array($chart->origin, ['gisCapture', 'surveyCadastral'], true) ? $chart->origin_id : '') }}" />
                    <div class="helper-text">Checked against the GIS table; nothing is written there.</div>
                </div>
                <div class="form-group">
                    <label>Open in GIS</label>
                    @if ($gis = $chart->gisLink())
                        <a class="btn btn-outline btn-sm" href="{{ $gis[1] }}" target="_blank" rel="noopener">
                            <i class="fas fa-map-location-dot"></i> {{ $gis[0] }}
                        </a>
                    @else
                        <div class="helper-text">
                            Not linked.
                            @if (\Illuminate\Support\Facades\Route::has('gis_record.index'))
                                <a href="{{ route('gis_record.index') }}" target="_blank" rel="noopener">Find it in GIS capture</a>.
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </fieldset>

        @include('cadastral_module.partials._address_builder', [
            'prefix' => 'prop_',
            'mode'   => 'property',
            'model'  => $chart,
            'legend' => 'Property Location',
            'plotField' => 'plot_no',
        ])
    </div>

    <div class="form-actions">
        <a href="{{ route('cadastral-module.charting.index') }}" class="btn btn-secondary">Cancel</a>
        @canDo('Cad - Records', $isEdit ? 'edit' : 'create')
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ $isEdit ? 'Save Chart' : 'Create Chart' }}
            </button>
        @endcanDo
    </div>
</form>

{{-- Version history: every chart kept for this file, superseded ones included. --}}
@if ($isEdit && $versions->count() > 0)
    <div class="table-wrapper" style="margin-top:18px;">
        <div class="table-toolbar">
            <div class="left">
                <strong>Version history</strong>
                <span class="helper-text" style="margin:0 0 0 8px;">Superseded versions are kept, never overwritten.</span>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Version</th><th>Chart</th><th>Supersedes</th><th>Status</th><th>Area (sqm)</th><th>Charted</th><th>Checked</th><th>Created</th></tr>
                </thead>
                <tbody>
                    @foreach ($versions as $v)
                        <tr @if ($v->id === $chart->id) style="background:var(--gray-100);" @endif>
                            <td>v{{ $v->version }}</td>
                            <td>
                                @if ($v->id === $chart->id)
                                    <strong>{{ $v->chart_ref }}</strong> (this one)
                                @else
                                    <a href="{{ route('cadastral-module.charting.edit', $v->id) }}">{{ $v->chart_ref }}</a>
                                @endif
                            </td>
                            <td>{{ optional($versions->firstWhere('id', $v->supersedes_chart_id))->chart_ref ?: '—' }}</td>
                            <td>
                                <span class="status-badge {{ $v->status_badge }}"><span class="dot"></span>{{ $v->status }}</span>
                            </td>
                            <td class="money">{{ $v->area_sqm !== null ? number_format($v->area_sqm, 2) : '—' }}</td>
                            <td>{{ optional($v->charted_on)->format('d M Y') ?: '—' }}</td>
                            <td>{{ $v->checked_by ? $v->checked_by . ' · ' . optional($v->checked_on)->format('d M Y') : '—' }}</td>
                            <td>{{ optional($v->created_at)->format('d M Y') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if ($isEdit)
    {{-- The beacon ring, saved as a whole list in one transaction. --}}
    <div class="form-container" style="margin-top:22px;">
        <div class="card-header">
            <strong>Beacon Ring</strong>
            <span class="helper-text" style="margin:0;">
                Eastings and northings in metres, in survey order. Three or more complete points are
                needed before an area can be computed. This ring is also what any geometric conflict
                test compares — KLAES stores no parcel geometry of its own.
            </span>
        </div>

        @if (($computedArea ?? null) !== null)
            <div class="calc-grid" style="padding:12px 0;">
                <div class="calc-card">
                    <div class="kpi-label">Computed Area</div>
                    <div class="kpi-value" style="font-size:18px;">{{ number_format($computedArea, 3) }} sqm</div>
                    <div class="helper-text">{{ number_format($computedArea / 10000, 4) }} ha</div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Perimeter</div>
                    <div class="kpi-value" style="font-size:18px;">
                        {{ ($perimeter ?? null) !== null ? number_format($perimeter, 3) . ' m' : '—' }}
                    </div>
                </div>
                <div class="calc-card">
                    <div class="kpi-label">Points</div>
                    <div class="kpi-value" style="font-size:18px;">{{ $coordinates->count() }}</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('cadastral-module.charting.coordinates.save', $chart) }}">
            @csrf
            <div class="table-scroll">
                <table class="coord-table">
                    <thead>
                        <tr>
                            <th style="width:36px;">#</th>
                            <th>Beacon ID</th>
                            <th>Northing</th>
                            <th>Easting</th>
                            <th>Elevation</th>
                            <th>Bearing</th>
                            <th>Distance</th>
                            <th>Remarks</th>
                            <th style="width:36px;"></th>
                        </tr>
                    </thead>
                    <tbody id="coord-body">
                        {{-- Hidden template row, cloned by the Add-row button. --}}
                        <tr data-row-template style="display:none;">
                            <td></td>
                            <td><input type="text" name="coordinates[][beacon_id]" /></td>
                            <td><input type="number" step="0.001" name="coordinates[][northing]" /></td>
                            <td><input type="number" step="0.001" name="coordinates[][easting]" /></td>
                            <td><input type="number" step="0.001" name="coordinates[][elevation]" /></td>
                            <td><input type="text" name="coordinates[][bearing]" /></td>
                            <td><input type="number" step="0.001" name="coordinates[][distance]" /></td>
                            <td><input type="text" name="coordinates[][remarks]" /></td>
                            <td><button type="button" class="btn btn-outline btn-xs" data-remove-row><i class="fas fa-times"></i></button></td>
                        </tr>

                        @forelse ($coordinates as $i => $c)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td><input type="text" name="coordinates[{{ $i }}][beacon_id]" value="{{ $c->beacon_id }}" /></td>
                                <td><input type="number" step="0.001" name="coordinates[{{ $i }}][northing]" value="{{ $c->northing }}" /></td>
                                <td><input type="number" step="0.001" name="coordinates[{{ $i }}][easting]" value="{{ $c->easting }}" /></td>
                                <td><input type="number" step="0.001" name="coordinates[{{ $i }}][elevation]" value="{{ $c->elevation }}" /></td>
                                <td><input type="text" name="coordinates[{{ $i }}][bearing]" value="{{ $c->bearing }}" /></td>
                                <td><input type="number" step="0.001" name="coordinates[{{ $i }}][distance]" value="{{ $c->distance }}" /></td>
                                <td><input type="text" name="coordinates[{{ $i }}][remarks]" value="{{ $c->remarks }}" /></td>
                                <td><button type="button" class="btn btn-outline btn-xs" data-remove-row><i class="fas fa-times"></i></button></td>
                            </tr>
                        @empty
                            @for ($i = 0; $i < 4; $i++)
                                <tr>
                                    <td>{{ $i + 1 }}</td>
                                    <td><input type="text" name="coordinates[{{ $i }}][beacon_id]" /></td>
                                    <td><input type="number" step="0.001" name="coordinates[{{ $i }}][northing]" /></td>
                                    <td><input type="number" step="0.001" name="coordinates[{{ $i }}][easting]" /></td>
                                    <td><input type="number" step="0.001" name="coordinates[{{ $i }}][elevation]" /></td>
                                    <td><input type="text" name="coordinates[{{ $i }}][bearing]" /></td>
                                    <td><input type="number" step="0.001" name="coordinates[{{ $i }}][distance]" /></td>
                                    <td><input type="text" name="coordinates[{{ $i }}][remarks]" /></td>
                                    <td><button type="button" class="btn btn-outline btn-xs" data-remove-row><i class="fas fa-times"></i></button></td>
                                </tr>
                            @endfor
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-outline btn-sm" data-add-row="#coord-body">
                    <i class="fas fa-plus"></i> Add a Point
                </button>
                @canDo('Cad - Records', 'edit')
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calculator"></i> Save Ring &amp; Recompute Area
                    </button>
                @endcanDo
            </div>
        </form>
    </div>

    @if ($chart->is_current && $chart->status !== 'Checked')
        <form method="POST" action="{{ route('cadastral-module.charting.mark-checked', $chart) }}"
              class="form-container" style="margin-top:18px;">
            @csrf
            <div class="form-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Checked By</label>
                        <input type="text" name="checked_by" value="{{ auth()->user()->name }}" />
                    </div>
                </div>
            </div>
            <div class="form-actions">
                @canDo('Cad - Records', 'edit')
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Mark Checked</button>
                @endcanDo
            </div>
        </form>
    @endif
@endif
