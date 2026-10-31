@php
    use App\Support\SurveyGeometry as G;

    $key = $anchor['key'];
    $locked = $anchor['locked'];
    $rp = $anchor['routes'] ?? 'survey-module.gkn';
    $num = fn ($v, $dp = 3) => $v === null ? '—' : number_format((float) $v, $dp);
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    // Field tolerances for flagging a reading against the coordinates.
    $tolSeconds = 60; $tolMetres = 0.05;
@endphp

@include('survey_module.partials._flash')

<div class="page-header">
    @if (! empty($anchor['back']))
        <a class="btn btn-secondary btn-sm" href="{{ $anchor['back']['url'] }}"><i class="fas fa-arrow-left"></i> {{ $anchor['back']['label'] }}</a>
    @else
        <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.gkn.observations.index') }}"><i class="fas fa-arrow-left"></i> All instructions</a>
    @endif
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a class="btn btn-outline btn-sm" href="{{ route($rp . '.coordinates.show', $key) }}"><i class="fas fa-map-pin"></i> List of Coordinates</a>
        <a class="btn btn-outline btn-sm" href="{{ route($rp . '.beacons.show', $key) }}"><i class="fas fa-map-marker-alt"></i> Beacon Nos</a>
        @unless ($locked)
            <button type="button" class="btn btn-primary btn-sm" onclick="svModal.open('obsForm')"><i class="fas fa-plus"></i> Add Reading</button>
        @endunless
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Readings</div><div class="kpi-value">{{ $obs->count() }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Total distance</div><div class="kpi-value">{{ $misclosure ? number_format($misclosure['length'], 2) . ' m' : '—' }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Linear misclosure</div>
        <div class="kpi-value">{{ $closed && $misclosure ? number_format($misclosure['linear'], 3) . ' m' : '—' }}</div>
        <div style="font-size:12px;color:var(--gray-500);">{{ $closed ? 'ΔN ' . number_format($misclosure['dn'], 3) . ' · ΔE ' . number_format($misclosure['de'], 3) : 'Traverse not closed yet' }}</div>
    </div>
    <div class="kpi-card"><div class="kpi-label">Precision</div>
        <div class="kpi-value">
            @if (! $closed || ! $misclosure) —
            @elseif ($misclosure['precision'] === null) Closes
            @else 1 : {{ number_format($misclosure['precision']) }}
            @endif
        </div>
    </div>
</div>

@include('survey_module.partials.sections._survey-sketch-plan', ['anchor' => $anchor, 'sketches' => $sketches ?? collect()])

@if ($locked)
    <div class="comp-type-note" style="margin-bottom:16px;"><i class="fas fa-lock"></i> {{ $anchor['prefill']['its_number'] }} is closed; its readings can no longer be changed.</div>
@else
    <form method="POST" action="{{ route($rp . '.observations.store', $key) }}" id="obsForm"
          class="farmer-entry-form {{ $errors->any() ? 'open' : '' }}" data-sv-modal="Add Reading · {{ $anchor['prefill']['its_number'] }}">
        @csrf
        <datalist id="obsStations">@foreach ($stations as $s)<option value="{{ $s }}">@endforeach</datalist>
        {{-- Bearing and distance per line, "Add line" for more (CR 30102026 item 16); all lines save together. --}}
        @php
            $obsOldLines = old('lines');
            $obsLines = is_array($obsOldLines) && $obsOldLines
                ? array_values(array_map(fn ($l) => array_merge(['from_station' => '', 'to_station' => '', 'bearing_d' => '', 'bearing_m' => '', 'bearing_s' => '', 'observed_distance' => '', 'remarks' => ''], (array) $l), $obsOldLines))
                : [['from_station' => (string) ($obs->last()?->to_station ?? ''), 'to_station' => '', 'bearing_d' => '', 'bearing_m' => '', 'bearing_s' => '', 'observed_distance' => '', 'remarks' => '']];
        @endphp
        <div x-data="{ lines: @js($obsLines),
                       add() { const p = this.lines[this.lines.length - 1]; this.lines.push({ from_station: p ? p.to_station : '', to_station: '', bearing_d: '', bearing_m: '', bearing_s: '', observed_distance: '', remarks: '' }); },
                       remove(i) { if (this.lines.length > 1) this.lines.splice(i, 1); } }">
            <div class="sv-group"><i class="fas fa-route"></i><span>Lines</span></div>
            <div class="table-scroll" style="margin-bottom:8px;">
                <table class="obs-lines" style="width:100%;font-size:14px;">
                    <thead>
                        <tr>
                            <th style="width:44px;">S/N</th>
                            <th>From station <span class="required">*</span></th>
                            <th>To station <span class="required">*</span></th>
                            <th>Bearing (deg, min, sec) <span class="required">*</span></th>
                            <th>Distance (m) <span class="required">*</span></th>
                            <th>Remarks</th>
                            <th style="width:44px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(line, i) in lines" :key="i">
                            <tr>
                                <td x-text="i + 1"></td>
                                <td><input type="text" :name="`lines[${i}][from_station]`" x-model="line.from_station" list="obsStations" maxlength="50" required style="min-width:90px;" /></td>
                                <td><input type="text" :name="`lines[${i}][to_station]`" x-model="line.to_station" list="obsStations" maxlength="50" required style="min-width:90px;" /></td>
                                <td>
                                    <div style="display:grid;grid-template-columns:repeat(3,minmax(56px,1fr));gap:6px;">
                                        <input type="number" :name="`lines[${i}][bearing_d]`" x-model="line.bearing_d" min="0" max="359" step="1" placeholder="deg" required />
                                        <input type="number" :name="`lines[${i}][bearing_m]`" x-model="line.bearing_m" min="0" max="59" step="1" placeholder="min" />
                                        <input type="number" :name="`lines[${i}][bearing_s]`" x-model="line.bearing_s" min="0" max="59.9" step="0.1" placeholder="sec" />
                                    </div>
                                </td>
                                <td><input type="number" :name="`lines[${i}][observed_distance]`" x-model="line.observed_distance" step="0.001" min="0.001" required style="min-width:90px;" /></td>
                                <td><input type="text" :name="`lines[${i}][remarks]`" x-model="line.remarks" maxlength="500" style="min-width:120px;" /></td>
                                <td>
                                    <button type="button" class="btn btn-outline btn-sm" @click="remove(i)" :disabled="lines.length === 1" aria-label="Remove line" title="Remove line"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline btn-sm" @click="add()" style="margin-bottom:16px;"><i class="fas fa-plus"></i> Add line</button>
        </div>
        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-book"></i><span>Field book</span></div>
            <div class="form-group"><label>Date observed</label>
                <input type="date" name="observed_on" max="{{ now()->toDateString() }}" value="{{ old('observed_on', $obs->last()?->observed_on?->format('Y-m-d')) }}" /></div>
            <div class="form-group"><label>Observer</label>
                <input type="text" name="observer" maxlength="255" value="{{ old('observer', $obs->last()?->observer ?? $anchor['prefill']['surveyor_name']) }}" /></div>
            <p class="helper-text" style="grid-column:1/-1;margin:0;">
                Enter readings in the order walked, from the field book. A run that returns to its first station is closed as a traverse.
                Readings saved in the SCF field app arrive here too.
            </p>
        </div>
        <div class="form-actions">
            <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('obsForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
            <div class="right">
                <button class="btn btn-outline" type="submit" data-sv-again><i class="fas fa-redo"></i> Save &amp; add next</button>
                <button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Save Readings</button>
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
                    <th>From</th>
                    <th>To</th>
                    <th>Observed Bearing</th>
                    <th style="text-align:right;">Observed Dist. (m)</th>
                    <th>From Coordinates</th>
                    <th style="text-align:right;">Δ Bearing</th>
                    <th style="text-align:right;">Δ Dist. (m)</th>
                    <th>Observed</th>
                    <th>Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($obs as $o)
                    @php
                        $ck = $checks[$o->id] ?? null;
                        $bad = $ck && (abs($ck['bearing_diff'] ?? 0) > $tolSeconds || abs($ck['distance_diff'] ?? 0) > $tolMetres);
                    @endphp
                    <tr @if ($bad) style="background:#fef2f2;" @endif>
                        <td>{{ $loop->iteration }}</td>
                        <td><strong>{{ $o->from_station }}</strong></td>
                        <td><strong>{{ $o->to_station }}</strong></td>
                        <td style="font-family:monospace;">{{ G::dms($o->observed_bearing) }}</td>
                        <td style="text-align:right;font-family:monospace;">{{ $num($o->observed_distance) }}</td>
                        <td style="font-family:monospace;font-size:12px;">
                            @if ($ck) {{ G::dms($ck['bearing']) }} · {{ $num($ck['distance']) }} m
                            @else <span style="color:var(--gray-500);font-family:inherit;">Stations not on the coordinate list</span>
                            @endif
                        </td>
                        <td style="text-align:right;font-family:monospace;{{ $ck && abs($ck['bearing_diff']) > $tolSeconds ? 'color:#b91c1c;font-weight:700;' : '' }}">
                            {{ $ck ? sprintf('%+.1f"', $ck['bearing_diff']) : '—' }}</td>
                        <td style="text-align:right;font-family:monospace;{{ $ck && abs($ck['distance_diff']) > $tolMetres ? 'color:#b91c1c;font-weight:700;' : '' }}">
                            {{ $ck ? sprintf('%+.3f', $ck['distance_diff']) : '—' }}</td>
                        <td>{{ optional($o->observed_on)->format('d M Y') ?: '—' }}@if ($o->observer)<div style="font-size:11px;color:var(--gray-500);">{{ $o->observer }}</div>@endif</td>
                        <td>{{ $o->source === 'mobile' ? 'Mobile app' : 'Desktop' }}</td>
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
                                        <form method="POST" action="{{ route($rp . '.observations.destroy', [$key, $o->id]) }}" onsubmit="return confirm('Remove reading {{ $o->from_station }} → {{ $o->to_station }}?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Remove reading</button>
                                        </form>
                                    </div>
                                </div>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" style="text-align:center;padding:24px;color:var(--gray-500);">No readings yet{{ $locked ? '.' : '. Add the first one above.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Rows in red differ from the coordinate list by more than {{ $tolSeconds }}" in bearing or {{ $tolMetres * 100 }} cm in distance.</span>
    </div>
</div>
