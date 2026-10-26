@php
    use App\Support\SurveyGeometry as G;

    $key = $anchor['key'];
    $locked = $anchor['locked'];
    $num = fn ($v, $dp = 3) => $v === null ? '—' : number_format((float) $v, $dp);
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.gkn.coordinates.index') }}"><i class="fas fa-arrow-left"></i> All instructions</a>
    <div style="display:flex;gap:8px;">
        <a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.observations.show', $key) }}"><i class="fas fa-calculator"></i> Field Observations</a>
        @unless ($locked)
            <button type="button" class="btn btn-primary btn-sm" onclick="svModal.open('coordForm')"><i class="fas fa-plus"></i> Add Beacon</button>
        @endunless
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Beacons</div><div class="kpi-value">{{ $coords->count() }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Area</div>
        <div class="kpi-value">{{ $shape['area'] === null ? '—' : number_format($shape['area'], 2) . ' m²' }}</div>
        @if ($shape['area'] !== null)<div style="font-size:12px;color:var(--gray-500);">{{ number_format($shape['area'] / 10000, 4) }} Ha</div>@endif
    </div>
    <div class="kpi-card"><div class="kpi-label">Perimeter</div><div class="kpi-value">{{ $shape['perimeter'] === null ? '—' : number_format($shape['perimeter'], 2) . ' m' }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Plot / Location</div>
        <div style="font-size:14px;font-weight:600;">{{ $anchor['prefill']['plot_no'] ? 'Plot ' . $anchor['prefill']['plot_no'] : '—' }}</div>
        <div style="font-size:12px;color:var(--gray-500);">{{ $anchor['prefill']['location'] ?: '' }}</div>
    </div>
</div>

@if ($locked)
    <div class="comp-type-note" style="margin-bottom:16px;"><i class="fas fa-lock"></i> {{ $anchor['prefill']['its_number'] }} is closed; this list can no longer be changed.</div>
@else
    <form method="POST" action="{{ route('survey-module.gkn.coordinates.store', $key) }}" id="coordForm"
          class="farmer-entry-form {{ $errors->any() ? 'open' : '' }}" data-sv-modal="Add Beacon · {{ $anchor['prefill']['its_number'] }}" data-sv-modal-size="sm">
        @csrf
        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-map-pin"></i><span>Beacon</span></div>
            <div class="form-group"><label>Beacon ID <span class="required">*</span></label>
                <input type="text" name="beacon_id" maxlength="50" value="{{ old('beacon_id') }}" placeholder="e.g. KN 1234" required /></div>
            <div class="form-group"><label>Remarks</label>
                <input type="text" name="remarks" maxlength="500" value="{{ old('remarks') }}" /></div>
        </div>
        <div class="form-grid cols-3">
            <div class="sv-group"><i class="fas fa-crosshairs"></i><span>Coordinates</span></div>
            <div class="form-group"><label>Northing (m) <span class="required">*</span></label>
                <input type="number" step="0.001" name="northing" value="{{ old('northing') }}" required /></div>
            <div class="form-group"><label>Easting (m) <span class="required">*</span></label>
                <input type="number" step="0.001" name="easting" value="{{ old('easting') }}" required /></div>
            <div class="form-group"><label>Elevation (m)</label>
                <input type="number" step="0.001" name="elevation" value="{{ old('elevation') }}" /></div>
            <p class="helper-text" style="grid-column:1/-1;margin:0;">Add the beacons in boundary order; the last one closes back to the first.</p>
        </div>
        <div class="form-actions">
            <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('coordForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
            <div class="right">
                <button class="btn btn-outline" type="submit" data-sv-again><i class="fas fa-redo"></i> Save &amp; add next</button>
                <button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Add Beacon</button>
            </div>
        </div>
    </form>
@endif

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Beacon</th>
                    <th style="text-align:right;">Northing (m)</th>
                    <th style="text-align:right;">Easting (m)</th>
                    <th style="text-align:right;">Elevation (m)</th>
                    <th>To</th>
                    <th>Bearing</th>
                    <th style="text-align:right;">Distance (m)</th>
                    <th>Remarks</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($coords as $c)
                    @php $leg = $legs[$c->id] ?? null; @endphp
                    <tr x-data="{ edit: false }">
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $c->beacon_id }}</strong></td>
                        <td style="text-align:right;font-family:monospace;">{{ $num($c->northing) }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ $num($c->easting) }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ $num($c->elevation) }}</td>
                        <td>{{ $leg['to'] ?? '—' }}</td>
                        <td style="font-family:monospace;">{{ $leg ? (G::dms($leg['bearing']) ?? '—') : '—' }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ $leg ? $num($leg['distance']) : '—' }}</td>
                        <td>{{ $c->remarks ?: '—' }}</td>
                        <td>
                            @unless ($locked)
                                <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                    <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                            @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                    </button>
                                    <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                         :style="'top:' + top + 'px; left:' + left + 'px'"
                                         class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                        <button type="button" class="{{ $svItem }}" @click="open = false; $dispatch('edit-beacon', {{ $c->id }})">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </button>
                                        @foreach (['up' => 'Move up', 'down' => 'Move down'] as $dir => $label)
                                            @if (($dir === 'up' && ! $loop->parent->first) || ($dir === 'down' && ! $loop->parent->last))
                                                <form method="POST" action="{{ route('survey-module.gkn.coordinates.move', [$key, $c->id]) }}">@csrf<input type="hidden" name="dir" value="{{ $dir }}">
                                                    <button class="{{ $svItem }}" type="submit"><i data-lucide="arrow-{{ $dir }}" class="w-4 h-4 text-gray-500"></i> {{ $label }}</button>
                                                </form>
                                            @endif
                                        @endforeach
                                        <form method="POST" action="{{ route('survey-module.gkn.coordinates.destroy', [$key, $c->id]) }}" onsubmit="return confirm('Remove beacon {{ $c->beacon_id }}?');">
                                            @csrf @method('DELETE')
                                            <button class="{{ $svItem }} text-red-600" type="submit"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove</button>
                                        </form>
                                    </div>
                                </div>
                            @endunless
                        </td>
                    </tr>
                    @unless ($locked)
                        <tr x-data="{ show: false }" x-show="show" x-cloak @edit-beacon.window="show = ($event.detail === {{ $c->id }}) ? !show : false">
                            <td colspan="10" style="background:var(--gray-50);">
                                <form method="POST" action="{{ route('survey-module.gkn.coordinates.update', [$key, $c->id]) }}"
                                      style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;align-items:end;">
                                    @csrf @method('PUT')
                                    <div><label>Beacon</label><input type="text" name="beacon_id" value="{{ $c->beacon_id }}" maxlength="50" required class="form-control" /></div>
                                    <div><label>Northing</label><input type="number" step="0.001" name="northing" value="{{ $c->northing }}" required class="form-control" /></div>
                                    <div><label>Easting</label><input type="number" step="0.001" name="easting" value="{{ $c->easting }}" required class="form-control" /></div>
                                    <div><label>Elevation</label><input type="number" step="0.001" name="elevation" value="{{ $c->elevation }}" class="form-control" /></div>
                                    <div><label>Remarks</label><input type="text" name="remarks" value="{{ $c->remarks }}" maxlength="500" class="form-control" /></div>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> Save</button>
                                        <button class="btn btn-secondary btn-sm" type="button" @click="show = false"><i class="fas fa-times"></i></button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    @endunless
                @empty
                    <tr><td colspan="10" style="text-align:center;padding:24px;color:var(--gray-500);">
                        No beacons yet{{ $locked ? '.' : '. Add the first one above.' }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Bearings are grid bearings, clockwise from north, from each beacon to the next; the last leg closes to the first beacon.</span>
    </div>
</div>
