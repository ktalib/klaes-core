{{-- Survey Plan Check card, sections 1-4 (CR 11112026-2 item 8; user, 2026-11-12, from the approved prototype
     /maps/survey-plan-check.html). Shared by KLAES (Upload Survey Plan pop-up, Check Survey Plan page) and the MLPP-SG
     portal upload page. Order from the recordings: 1 coordinate system, 2 ONE reference coordinate (drives the map),
     3 map, 4 the other beacons in survey order. The host page puts Plan details (5, upload only) and then includes
     _plan_check_export for Export, the styles and the script.
     $spcMode: 'upload' (the reference coordinate and the system are submitted) or 'check' (nothing is saved).
     $spcSearchUrl: the beacon search route. $spcPrefill: ['sys' => '26392', 'ref' => [...], 'others' => [[...]], 'note' => html] or null. --}}
@php
  $spcMode = $spcMode ?? 'upload';
  $spcPrefill = $spcPrefill ?? null;
  // After a failed upload, the entered system and reference come back.
  if ($spcMode === 'upload' && ! $spcPrefill && (old('coord_system') || old('beacons'))) {
      $ob = (array) (old('beacons')[0] ?? []);
      $spcPrefill = ['sys' => old('coord_system'), 'ref' => ['beacon_no' => $ob['beacon_no'] ?? '', 'x' => $ob['x'] ?? '', 'y' => $ob['y'] ?? '', 'source' => $ob['source'] ?? '']];
  }
  $spcErrors = isset($errors) ? collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'beacons') || $k === 'coord_system')->flatten() : collect();
@endphp
<div class="spc" id="spc" data-mode="{{ $spcMode }}" data-search="{{ $spcSearchUrl }}" data-prefill='@json($spcPrefill)'>
  @if (! empty($spcPrefill['note']))
    <div class="spc-prefill"><i class="fas fa-circle-info"></i> {!! $spcPrefill['note'] !!}</div>
  @endif
  @if ($spcErrors->isNotEmpty())
    <div class="spc-err">@foreach ($spcErrors as $m)<div>{{ $m }}</div>@endforeach</div>
  @endif

  <div class="spc-group"><span class="n">1</span><span>Reference coordinate system <span class="spc-req">*</span></span><span class="aside">The X / Y on the plan are read in this system.</span></div>
  <input type="hidden" id="spc-coord" @if ($spcMode === 'upload') name="coord_system" @endif value="">
  {{-- Two halves (user, 2026-11-12): the systems on the left, the NTM vs UTM chart on the right. --}}
  <div class="spc-sysrow">
  <div class="spc-sys" role="radiogroup" aria-label="Coordinate system">
    <div class="spc-opt">
      <input type="radio" name="spc_sys" value="ntm" id="spc-sysNtm" data-sys checked>
      <label class="t" for="spc-sysNtm">NTM · Nigerian Transverse Mercator (Minna datum)</label>
      <span class="d">Clarke 1880 ellipsoid, three 4° belts. Kano is in the Mid Belt.</span>
      <span class="spc-belt" role="radiogroup" aria-label="NTM belt">
        <label><input type="radio" name="spc_belt" value="26391">West Belt · CM 4.5° E</label>
        <label><input type="radio" name="spc_belt" value="26392" checked>Mid Belt · CM 8.5° E</label>
        <label><input type="radio" name="spc_belt" value="26393">East Belt · CM 12.5° E</label>
      </span>
    </div>
    <div class="spc-opt">
      <input type="radio" name="spc_sys" value="utm" id="spc-sysUtm" data-sys>
      <label class="t" for="spc-sysUtm">UTM · Universal Transverse Mercator (WGS 84)</label>
      <span class="d">WGS 84 ellipsoid, zone 32N, central meridian 9° E (EPSG:32632). GPS / GNSS coordinates.</span>
    </div>
  </div>
  {{-- NTM vs UTM chart: Check Survey Plan and Survey Plan Upload (KLAES and portal; user, 2026-11-12). --}}
    <div class="spc-guide">
      <button type="button" class="spc-guide-thumb" data-spc-guide aria-label="View the NTM vs UTM chart"><img src="{{ asset('assets/survey/ntm-vs-utm.webp') }}" alt="" loading="lazy"></button>
      <div class="spc-guide-text">
        <b>NTM vs UTM: Nigeria's two Transverse Mercator grids</b>
        <span>How the NTM belts (Minna) line up with the UTM zones (WGS 84). Kano is in the NTM Mid Belt and UTM zone 32N. Never convert one to the other by adding constants.</span>
      </div>
      <button type="button" class="spc-btn spc-plot" data-spc-guide><i class="fas fa-book-atlas"></i> View chart</button>
    </div>
  </div>
    <div class="spc-lightbox" id="spc-guide" hidden role="dialog" aria-modal="true" aria-label="NTM vs UTM chart">
      <div class="spc-lightbox-bar">
        <b>NTM vs UTM: Nigeria's two Transverse Mercator grids</b>
        <span style="flex:1"></span>
        <a class="spc-btn spc-plot" href="{{ asset('assets/survey/ntm-vs-utm.webp') }}" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Open full size</a>
        <button type="button" class="spc-btn spc-clear" data-spc-guide-close><i class="fas fa-xmark"></i> Close</button>
      </div>
      <div class="spc-lightbox-body" data-spc-guide-close><img src="{{ asset('assets/survey/ntm-vs-utm.webp') }}" alt="NTM vs UTM: comparison of Nigeria's two Transverse Mercator grids"></div>
    </div>

  <div class="spc-group"><span class="n">2</span><span>Reference coordinate <span class="spc-req">*</span></span><span class="aside" id="spc-data-note">Loading the Beacon Register…</span></div>
  <p class="spc-note">One reference beacon. Search the Beacon No. list: a beacon in the Beacon Register fills X and Y for you. If it is not listed, type the number, pick <b>Not listed</b>, and enter X (Easting) and Y (Northing) as on the plan. <b>Go to point</b> takes the map there.</p>
  <div class="spc-bt">
    <table>
      <thead><tr><th style="text-align:center">#</th><th>Beacon No.</th><th>X (Easting)</th><th>Y (Northing)</th><th></th></tr></thead>
      <tbody id="spc-refRows"></tbody>
    </table>
  </div>
  <div class="spc-verdict" id="spc-refVerdict" hidden></div>

  <div class="spc-group"><span class="n">3</span><span>Map</span>
    <span class="aside spc-legend">
      <span><span class="spc-dot" style="background:var(--spc-ref)"></span>Reference coordinate</span>
      <span><span class="spc-dot" style="background:var(--spc-sv)"></span>Other beacon</span>
      <span><span class="spc-dot" style="background:var(--spc-reg)"></span>Beacon Register</span>
      <span><span class="spc-dot" style="background:var(--spc-ctl)"></span>Control point</span>
      <span><span class="spc-dot" style="background:var(--spc-draw)"></span>Drawn</span>
    </span></div>
  <div class="spc-mapbox" id="spc-mapbox">
    <div class="spc-mapbar" role="toolbar" aria-label="Map tools">
      <div class="grp">
        <button type="button" class="mb" id="spc-btn-street"><i class="fas fa-road"></i> Street</button>
        <button type="button" class="mb active" id="spc-btn-satellite"><i class="fas fa-satellite"></i> Satellite</button>
      </div>
      <div class="grp" id="spc-tools">
        <button type="button" class="mb" data-tool="point" title="Add a point"><i class="fas fa-location-dot"></i> Point</button>
        <button type="button" class="mb" data-tool="line" title="Draw a line"><i class="fas fa-bezier-curve"></i> Line</button>
        <button type="button" class="mb" data-tool="polygon" title="Mark out a parcel"><i class="fas fa-draw-polygon"></i> Parcel</button>
        <label class="snap" title="Snap to beacons and lines"><input type="checkbox" id="spc-snap" checked> Snap</label>
      </div>
      <div class="grp"><button type="button" class="mb" id="spc-mapFit" title="Zoom to the beacons"><i class="fas fa-expand"></i> Fit</button></div>
      <span class="grow"></span>
      <span class="gridtag" id="spc-gridtag"></span>
      <button type="button" class="full" id="spc-mapFull" title="Full screen"><i class="fas fa-up-right-and-down-left-from-center"></i> <span>Full screen</span></button>
    </div>
    <div class="spc-map" id="spc-map">
      <div class="spc-drawinfo" id="spc-drawinfo" hidden></div>
      <div class="spc-fullstats" id="spc-fullStats"></div>
    </div>
  </div>

  <div class="spc-group"><span class="n">4</span><span>Other Beacons (in order of the traverse)</span><span class="aside">Point 1, 2, 3… as surveyed. Each is joined to the one before.</span></div>
  <div class="spc-bt">
    <table>
      <thead><tr><th style="text-align:center">#</th><th>Beacon No.</th><th>X (Easting)</th><th>Y (Northing)</th><th></th></tr></thead>
      <tbody id="spc-svRows"></tbody>
    </table>
  </div>
  <div class="spc-row">
    <button type="button" class="spc-btn spc-add" id="spc-svAdd"><i class="fas fa-plus"></i> Add beacon</button>
    <button type="button" class="spc-btn spc-plot" id="spc-svPlot"><i class="fas fa-location-crosshairs"></i> Plot and zoom</button>
    <button type="button" class="spc-btn spc-poly" id="spc-svPoly"><i class="fas fa-draw-polygon"></i> Create polygon</button>
    <button type="button" class="spc-btn spc-clear" id="spc-svClear"><i class="fas fa-eraser"></i> Clear</button>
  </div>
  <div class="spc-row" style="align-items:flex-start">
    <details class="spc-opts">
      <summary>Paste from Excel</summary>
      <label for="spc-paste" style="display:grid;gap:6px;margin-top:8px;min-width:min(520px,80vw);font-size:13px;font-weight:500">One beacon per line, in survey order: number, X, Y
        <textarea id="spc-paste" rows="4" placeholder="A0876A	669007.00	912420.35"></textarea></label>
      <div class="spc-row" style="margin-top:8px"><button type="button" class="spc-btn spc-plot" id="spc-usepaste">Use these rows</button></div>
    </details>
    <details class="spc-opts" style="margin-left:auto">
      <summary>Check options</summary>
      <div style="display:grid;gap:8px;margin-top:8px;max-width:360px">
        <label for="spc-tol" style="font-size:13px;font-weight:500">Register tolerance (m)<input type="number" id="spc-tol" value="1" min="0.01" step="0.1" style="margin-top:4px"></label>
        <label class="spc-check" for="spc-autofix"><input type="checkbox" id="spc-autofix" checked><span>Switch X and Y back when they were typed the wrong way round</span></label>
      </div>
    </details>
  </div>
  <div class="spc-stats" id="spc-svStats" hidden></div>
</div>
