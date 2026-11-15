{{-- Survey Plan Check card, sections 1-4 (CR 11112026-2 item 8; user, 2026-11-12, from the approved prototype
     /maps/survey-plan-check.html). Shared by KLAES (Upload Survey Plan pop-up, Check Survey Plan page) and the MLPP-SG
     portal upload page. Order from the recordings: 1 coordinate system, 2 ONE reference coordinate (drives the map),
     3 the other beacons in order of the traverse, 4 map (swapped 2026-11-13). The host page puts Plan details (5, upload only) and then includes
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
@php
    // Kano GIS layers (2026-11-15): the portal gets the parcels without file numbers / deeds details.
    $spcGisUrl = request()->routeIs('surveyor-portal.*')
        ? route('surveyor-portal.survey-plans.gis-tiles', ['layer' => '__LAYER__'])
        : route('survey-module.survey-plans.gis-tiles', ['layer' => '__LAYER__']);
@endphp
<div class="spc" id="spc" data-column-picker="off" data-mode="{{ $spcMode }}" data-search="{{ $spcSearchUrl }}" data-gis="{{ $spcGisUrl }}" data-gis-full="{{ request()->routeIs('surveyor-portal.*') ? '0' : '1' }}" data-prefill='@json($spcPrefill)'>
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
      <input type="radio" name="spc_sys" value="minna" id="spc-sysMinna" data-sys checked>
      <label class="t" for="spc-sysMinna">Minna / UTM Zone 32N</label>
      <span class="d">Minna datum, Clarke 1880 (RGS) ellipsoid, central meridian 9° E, false easting 500,000 m (EPSG:26332). Kano's survey plans and the Beacon Register use this.</span>
    </div>
    <div class="spc-opt">
      <input type="radio" name="spc_sys" value="ntm" id="spc-sysNtm" data-sys>
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
  {{-- Reference charts in one tabbed card (Kunle, 2026-11-15): NTM vs UTM, the UTM Sheet Index, the Application Plan Data Sheet.
       Check Survey Plan and Survey Plan Upload (KLAES and portal). --}}
  @php
    $spcGuides = [
      'ntm' => ['tab' => 'NTM vs UTM', 'img' => 'assets/survey/ntm-vs-utm.webp', 'title' => "NTM vs UTM: Nigeria's two Transverse Mercator grids",
                'text' => 'How the NTM belts (Minna) line up with the UTM zones (WGS 84). Kano is in the NTM Mid Belt and UTM zone 32N. Never convert one to the other by adding constants.'],
      'sheets' => ['tab' => 'Sheet Index', 'img' => 'assets/survey/utm-sheet-index.webp', 'title' => 'Greater Kano UTM Sheet Index',
                'text' => 'The map sheets of Greater Kano (Minna / UTM 32N): the nine A0 sheets, the metric index grid and the 1:50,000 topo sheets. Use it to find the Map Sheet for the plan.'],
      'datasheet' => ['tab' => 'Data Sheet', 'img' => 'assets/survey/application-plan-data-sheet.webp', 'title' => 'Application Plan: Data Sheet',
                'text' => 'The entries of an Application Plan, in the order they appear on the plan: particulars, beacon coordinates, ground status, survey and office use.'],
    ];
    $spcGuideJson = json_encode(array_map(fn ($g) => ['title' => $g['title'], 'src' => asset($g['img'])], $spcGuides), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
  @endphp
    <div class="spc-guide spc-guides">
      <div class="spc-gtabs" role="tablist" aria-label="Reference charts">
        @foreach ($spcGuides as $k => $g)
          <button type="button" role="tab" class="spc-gtab @if ($loop->first) active @endif" data-spc-gtab="{{ $k }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $g['tab'] }}</button>
        @endforeach
      </div>
      @foreach ($spcGuides as $k => $g)
        <div class="spc-gpane" data-spc-gpane="{{ $k }}" role="tabpanel" @if (! $loop->first) hidden @endif>
          <button type="button" class="spc-guide-thumb" data-spc-guide="{{ $k }}" aria-label="View {{ $g['title'] }}"><img src="{{ asset($g['img']) }}" alt="" loading="lazy"></button>
          <div class="spc-guide-text">
            <b>{{ $g['title'] }}</b>
            <span>{{ $g['text'] }}</span>
          </div>
          <button type="button" class="spc-btn spc-plot" data-spc-guide="{{ $k }}"><i class="fas fa-book-atlas"></i> View chart</button>
        </div>
      @endforeach
    </div>
  </div>
    <div class="spc-lightbox" id="spc-guide" hidden role="dialog" aria-modal="true" aria-label="Reference chart">
      <div class="spc-lightbox-bar">
        <b id="spc-guideTitle"></b>
        <span style="flex:1"></span>
        <a class="spc-btn spc-plot" id="spc-guideOpen" href="#" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> Open full size</a>
        <button type="button" class="spc-btn spc-clear" data-spc-guide-close><i class="fas fa-xmark"></i> Close</button>
      </div>
      <div class="spc-lightbox-body" data-spc-guide-close><img id="spc-guideImg" src="" alt=""></div>
    </div>
    <script type="application/json" id="spc-guideData">{!! $spcGuideJson !!}</script>

  <div class="spc-group"><span class="n">2</span><span>Reference coordinate <span class="spc-req">*</span></span><span class="aside" id="spc-data-note">Loading the Beacon Register…</span></div>
  <p class="spc-note">One reference beacon. Search the Beacon No. list: a beacon in the Beacon Register fills X and Y for you. If it is not listed, type the number, pick <b>Not listed</b>, and enter X (Easting) and Y (Northing) as on the plan. <b>Go to point</b> takes the map there.</p>
  <div class="spc-bt">
    <table>
      <thead><tr><th style="text-align:center">#</th><th>Beacon No.</th><th>X (Easting)</th><th>Y (Northing)</th><th></th></tr></thead>
      <tbody id="spc-refRows"></tbody>
    </table>
  </div>
  <div class="spc-verdict" id="spc-refVerdict" hidden></div>

  {{-- Other Beacons (3) before the Map (4) (user, 2026-11-13): the plot shows right under the lines being typed. --}}
  <div class="spc-group"><span class="n">3</span><span>Other Beacons (in order of the traverse)</span><span class="aside">Point 1, 2, 3… as surveyed. Each is joined to the one before.</span></div>
  {{-- Two ways in (user, 2026-11-13, from the prototype): X / Y of each point, or a traverse from a start point by
       bearing and distance, line by line (X1 to X2 ... back to X1). The traverse fields have no names: nothing is saved. --}}
  <div class="spc-mode" role="radiogroup" aria-label="How the other beacons are entered">
    <label><input type="radio" name="spc_svmode" value="xy" checked><span><i class="fas fa-table-cells"></i> X / Y coordinates</span></label>
    <label><input type="radio" name="spc_svmode" value="brg"><span><i class="fas fa-compass"></i> Bearing and distance (traverse)</span></label>
  </div>
  <div id="spc-svBrg" class="spc-trav" hidden>
    {{-- Each line runs From beacon -> To beacon on one row (user's sketch, 2026-11-13): line 1's From beacon is
         picked (X1); every next line starts from the line before's To beacon. --}}
    <div class="spc-row">
      <span class="spc-note">Line 1: pick its From beacon (X1) or use the reference coordinate. Each next line starts at the last line's To beacon.</span>
      <button type="button" class="spc-btn spc-travref" id="spc-trUseRef" style="margin-left:auto"><i class="fas fa-location-crosshairs"></i> Use the reference coordinate</button>
    </div>
    <div id="spc-trHold" hidden>
      <div class="spc-trav-pick" id="spc-trStartWrap"><select id="spc-trStartB" aria-label="From beacon (X1)" data-no-search></select>
        <input type="text" id="spc-trStartOther" class="spc-tother" maxlength="60" placeholder="Specify the beacon number" aria-label="Specify the From beacon" hidden></div>
      <input type="text" id="spc-trStartX" inputmode="decimal" placeholder="X (Easting)" aria-label="From X (Easting)">
      <input type="text" id="spc-trStartY" inputmode="decimal" placeholder="Y (Northing)" aria-label="From Y (Northing)">
    </div>
    <div class="spc-bt">
      <table class="spc-trav-table">
        <thead><tr><th>Line</th><th>From Beacon</th><th>X</th><th>Y</th><th>Bearing</th><th>Distance (m)</th><th>To Beacon</th><th>X</th><th>Y</th><th></th></tr></thead>
        <tbody id="spc-travRows"></tbody>
      </table>
    </div>
    <div class="spc-row">
      <button type="button" class="spc-btn spc-add" id="spc-trAdd"><i class="fas fa-plus"></i> Add line</button>
      <label class="spc-check" for="spc-trClose" style="margin-left:6px"><input type="checkbox" id="spc-trClose" checked><span>Last line closes on the first From beacon (X1)</span></label>
      <span class="spc-note" style="margin-left:auto">Bearing as on the plan: N/S, degrees, minutes, seconds, E/W (e.g. N 84° 17′ 01″ W). A whole-circle bearing over 90° (e.g. 275° 42′ 59″) needs no N/S/E/W. Scroll the table sideways if it does not fit.</span>
    </div>
    <div class="spc-verdict" id="spc-trVerdict" hidden></div>
  </div>
  <div class="spc-bt" id="spc-svXY">
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
    <details class="spc-opts" id="spc-pasteBox">
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

  <div class="spc-group"><span class="n">4</span><span>Map</span>
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
        <label class="snap" title="Untick to plot on a plain georeferenced backdrop"><input type="checkbox" id="spc-baseOn" checked> Basemap</label>
        <label class="snap" title="Coordinate grid in the chosen system"><input type="checkbox" id="spc-gridOn"> Grid</label>
      </div>
      <div class="grp" id="spc-tools">
        <button type="button" class="mb" data-tool="point" title="Add a point"><i class="fas fa-location-dot"></i> Point</button>
        <button type="button" class="mb" data-tool="line" title="Draw a line"><i class="fas fa-bezier-curve"></i> Line</button>
        <button type="button" class="mb" data-tool="polygon" title="Mark out a parcel"><i class="fas fa-draw-polygon"></i> Parcel</button>
        <label class="snap" title="Snap to beacons and lines"><input type="checkbox" id="spc-snap" checked> Snap</label>
      </div>
      <div class="grp"><button type="button" class="mb" id="spc-mapFit" title="Zoom to the beacons"><i class="fas fa-expand"></i> Fit</button></div>
      {{-- A scanned plan or GeoTIFF under the plotting (public/js/survey-plan-scan.js, 2026-11-15). --}}
      <div class="grp"><button type="button" class="mb spc-scan-btn" id="spc-scanBtn" title="Put a scanned plan or GeoTIFF on the map"><i class="fas fa-file-image"></i> Scanned plan</button></div>
      <div class="grp spc-scan-grp" id="spc-scanGrp" hidden>
        <label class="snap" title="Show or hide the scanned plan"><input type="checkbox" id="spc-scanVis" checked> <span id="spc-scanTag" class="spc-scan-tag">Scan</span></label>
        <label class="snap" title="See-through" for="spc-scanOp"><i class="fas fa-circle-half-stroke"></i><input type="range" id="spc-scanOp" min="10" max="100" value="75" aria-label="Scan opacity"></label>
        <select id="spc-scanPaper" class="mb" title="Remove the paper so only the plan&#39;s lines lie on the map" aria-label="Scan paper"><option value="keep">Paper: keep</option><option value="dark">Lines only: dark</option><option value="yellow">Lines only: yellow</option></select>
        <button type="button" class="mb" id="spc-scanAdj" title="Drag the scan on the map to line it up; Shift + drag turns it"><i class="fas fa-up-down-left-right"></i> Move</button>
        <span id="spc-scanAdjBox" class="spc-scan-adj" hidden><label class="snap" title="Turn clockwise, in degrees">Turn<input type="number" id="spc-scanRot" step="0.05" value="0" aria-label="Turn in degrees"></label><label class="snap" title="Size, % of the placed size">Size %<input type="number" id="spc-scanSize" step="0.1" min="10" max="1000" value="100" aria-label="Size in percent"></label><button type="button" class="mb" id="spc-scanReset" title="Back to where it was placed">Reset</button></span>
        <button type="button" class="mb" id="spc-scanDel" title="Remove the scan from the map" aria-label="Remove the scan"><i class="fas fa-xmark"></i></button>
      </div>
      {{-- Table of Contents (CR 141102026 item 4): every layer on / off. Existing layers (2026-11-15) are added from
           its Add layer: parcels, beacons, lines from a file, read in the browser and never uploaded. --}}
      <div class="grp"><button type="button" class="mb active" id="spc-tocBtn" title="Table of contents: turn each layer on or off"><i class="fas fa-list-check"></i> Layers</button></div>
      <input type="file" id="spc-lyrFile" accept=".zip,.shp,.dbf,.prj,.cpg,.geojson,.json,.kml" multiple hidden>
      <span class="grow"></span>
      <span class="gridtag" id="spc-gridtag"></span>
      <button type="button" class="full" id="spc-mapFull" title="Full screen"><i class="fas fa-up-right-and-down-left-from-center"></i> <span>Full screen</span></button>
    </div>
    <div class="spc-mapbody" id="spc-mapbody">
    <aside class="spc-toc" id="spc-toc" aria-label="Layers"></aside>
    <div class="spc-map" id="spc-map">
      <div class="spc-drawinfo" id="spc-drawinfo" hidden></div>
      <div class="spc-fullstats" id="spc-fullStats"></div>
    </div>
    </div>
  </div>
  {{-- Scanned plan panel (survey-plan-scan.js). The file is read in the browser; nothing is uploaded. --}}
  <div class="spc-geo-ovl" id="spc-geoPanel" hidden role="dialog" aria-modal="true" aria-labelledby="spc-geoTitle">
    <div class="spc-geo-box">
      <div class="spc-geo-h">
        <i class="fas fa-file-image" style="color:var(--spc-blue)"></i>
        <div><h3 id="spc-geoTitle">Put a scanned plan on the map</h3><p class="spc-note">A GeoTIFF places itself. A plain scan (TIFF, JPG, PNG) is placed from a beacon on it. The file stays in your browser: nothing is uploaded.</p></div>
        <span style="flex:1"></span>
        <label class="spc-geo-btn spc-geo-btn-primary" for="spc-geoFile"><i class="fas fa-folder-open"></i> Open scan<input type="file" id="spc-geoFile" accept=".tif,.tiff,.jpg,.jpeg,.png,image/tiff,image/jpeg,image/png" hidden></label>
        <button type="button" class="spc-geo-btn" id="spc-geoSample" title="A GeoTIFF layout plan from Abia, until the Kano plans are added"><i class="fas fa-flask"></i> Load the sample (GeoTIFF)</button>
        <button type="button" class="spc-geo-ib" id="spc-geoClose" aria-label="Close" title="Close (Esc)"><i class="fas fa-xmark"></i></button>
      </div>
      <div class="spc-geo-body">
        <div class="spc-geo-view">
          <div class="spc-geo-viewbar"><span id="spc-gvName" class="spc-mono">No scan open</span><span style="flex:1"></span>
            <button type="button" class="spc-geo-ib" id="spc-gvOut" aria-label="Zoom out" title="Zoom out"><i class="fas fa-minus"></i></button>
            <button type="button" class="spc-geo-ib" id="spc-gvIn" aria-label="Zoom in" title="Zoom in"><i class="fas fa-plus"></i></button>
            <button type="button" class="spc-geo-ib" id="spc-gvFit" aria-label="Fit" title="Fit the sheet"><i class="fas fa-expand"></i></button></div>
          <div class="spc-geo-canvas"><canvas id="spc-gvCanvas"></canvas></div>
          <div class="spc-geo-hint" id="spc-gvHint"></div>
        </div>
        <div class="spc-geo-side">
          <p class="spc-note" id="spc-geoMsg">Open a scan, or load the sample.</p>
          <div class="spc-geo-method" role="radiogroup" aria-label="How to place the scan">
            <label><input type="radio" name="spc_geomethod" value="quick" checked><span><i class="fas fa-location-dot"></i> One beacon + scale</span></label>
            <label><input type="radio" name="spc_geomethod" value="cps"><span><i class="fas fa-crosshairs"></i> Control points</span></label>
          </div>
          <p class="spc-note">Coordinates in <b id="spc-geoSys"></b> (section 1).</p>
          <div id="spc-geoQuick" class="spc-geo-form">
            <label class="lbl">Beacon clicked on the scan <span class="spc-note" id="spc-gqPx">not clicked yet</span></label>
            <input type="text" id="spc-gqName" placeholder="Beacon No." aria-label="Beacon No.">
            <div class="spc-geo-two"><label class="lbl">X (Easting)<input type="text" id="spc-gqX" inputmode="decimal"></label><label class="lbl">Y (Northing)<input type="text" id="spc-gqY" inputmode="decimal"></label></div>
            <div class="spc-geo-three"><label class="lbl">Scale 1 :<input type="text" id="spc-gqScale" inputmode="decimal" value="2500"></label><label class="lbl">Scan dpi<input type="text" id="spc-gqDpi" inputmode="decimal" value="200"></label><label class="lbl">Turn (&deg;)<input type="text" id="spc-gqRot" inputmode="decimal" value="0" title="How far the sheet&#39;s north is turned clockwise from grid north"></label></div>
            <p class="spc-note">The scale is printed on the plan. Turn stays 0 when the sheet&#39;s north arrow points straight up.</p>
          </div>
          <div id="spc-geoCps" class="spc-geo-form" hidden>
            <div class="spc-geo-tablewrap"><table class="spc-geo-table"><thead><tr><th>#</th><th>On scan</th><th>Beacon No.</th><th>X (Easting)</th><th>Y (Northing)</th><th>Miss</th><th></th></tr></thead><tbody id="spc-gcRows"></tbody></table></div>
            <div><button type="button" class="spc-geo-btn" id="spc-gcClear"><i class="fas fa-eraser"></i> Clear points</button>
              <span class="spc-note">A Beacon Register number fills its X / Y. 3 or more points also take paper stretch.</span></div>
          </div>
          <div class="spc-geo-fit" id="spc-geoFit"></div>
          <button type="button" class="spc-geo-btn spc-geo-btn-success" id="spc-geoPlace" disabled><i class="fas fa-map-location-dot"></i> Place on map</button>
        </div>
      </div>
    </div>
  </div>

</div>
