{{-- Manual Upload, sections 1 and 2 (user, Recording 5, 2026-11-16): a scanned / hard copy plan needs only the coordinate
     system (a dropdown) and its reference beacon. No map, no other beacons, no export: those are Digital Plotting's.
     Shared by the KLAES pop-up and the KN-MLPP-SG portal. jQuery + select2 must be on the page (both layouts load them).
     $mrSearchUrl: the beacon search route. Submits coord_system and beacons[0][beacon_no|x|y|source]. --}}
@php
  use App\Models\Survey\SurveyPlanUpload as MrPlan;
  $mrOld = (array) (old('beacons')[0] ?? []);
  $mrSys = old('coord_system', '26332');
  $mrNo = (string) ($mrOld['beacon_no'] ?? '');
  $mrErrors = isset($errors) ? collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'beacons') || $k === 'coord_system')->flatten() : collect();
@endphp
<div class="spc mr" id="mr" data-search="{{ $mrSearchUrl }}">
  <style>
    .mr { display: grid; gap: 12px; }
    .mr-req { color: #dc2626; }
    .mr-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
    @media (max-width: 760px) { .mr-grid { grid-template-columns: minmax(0, 1fr); } }
    .mr label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px; }
    .mr select, .mr input[type=text] { width: 100%; height: 42px; padding: 8px 12px; border: 1.5px solid #d1d5db; border-radius: 8px; font-size: 14px; background: #fff; }
    .mr input.mono { font-family: ui-monospace, Consolas, monospace; }
    .mr input.auto { background: #f5f3ff; border-color: #c4b5fd; }
    .mr .select2-container .select2-selection--single { height: 42px; border: 1.5px solid #d1d5db; border-radius: 8px; }
    .mr .select2-container .select2-selection__rendered { line-height: 40px; padding-left: 12px; }
    .mr .select2-container .select2-selection__arrow { height: 40px; }
    .mr-note { margin: 0; font-size: 12.5px; color: #64748b; }
    .mr-err { padding: 8px 12px; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: 8px; font-size: 13px; }
    .mr-hint { font-size: 12px; color: #6d28d9; min-height: 16px; }
  </style>
  @if ($mrErrors->isNotEmpty())
    <div class="mr-err">@foreach ($mrErrors as $m)<div>{{ $m }}</div>@endforeach</div>
  @endif

  <div class="spc-group"><span class="n">1</span><span>Coordinate system <span class="mr-req">*</span></span><span class="aside">The X / Y on the plan are in this system.</span></div>
  <div style="max-width:420px;">
    <select name="coord_system" id="mrSys" required aria-label="Coordinate system">
      @foreach (MrPlan::COORD_SYSTEMS as $code => $label)
        <option value="{{ $code }}" @selected((string) $mrSys === (string) $code)>{{ $label }} (EPSG:{{ $code }})</option>
      @endforeach
    </select>
  </div>

  <div class="spc-group"><span class="n">2</span><span>Reference beacon <span class="mr-req">*</span></span><span class="aside">One beacon, as written on the plan.</span></div>
  <div class="mr-grid">
    <div>
      <label for="mrBeacon">Beacon No. <span class="mr-req">*</span></label>
      <select id="mrBeacon" aria-label="Beacon No.">
        @if ($mrNo !== '')<option value="{{ $mrNo }}" selected>{{ $mrNo }}</option>@endif
      </select>
      <input type="text" id="mrOther" maxlength="60" placeholder="Specify the beacon number *" style="margin-top:6px;" hidden disabled>
      <input type="hidden" name="beacons[0][beacon_no]" id="mrNo" value="{{ $mrNo }}">
      <input type="hidden" name="beacons[0][source]" id="mrSrc" value="{{ $mrOld['source'] ?? '' }}">
    </div>
    <div>
      <label for="mrX">X (Easting) <span class="mr-req">*</span></label>
      <input type="text" class="mono" id="mrX" name="beacons[0][x]" inputmode="decimal" maxlength="40" value="{{ $mrOld['x'] ?? '' }}" required>
    </div>
    <div>
      <label for="mrY">Y (Northing) <span class="mr-req">*</span></label>
      <input type="text" class="mono" id="mrY" name="beacons[0][y]" inputmode="decimal" maxlength="40" value="{{ $mrOld['y'] ?? '' }}" required>
    </div>
  </div>
  <div class="mr-hint" id="mrHint" aria-live="polite"></div>
  <p class="mr-note">Search the Beacon No. list: a beacon in the Beacon Register fills X and Y in the chosen system. If it is not listed, pick <b>Other (specify)</b> and type the number, X and Y as on the plan.</p>
</div>
<link rel="stylesheet" href="{{ asset('css/survey-plan-check.css') }}?v={{ filemtime(public_path('css/survey-plan-check.css')) }}">
<script src="{{ asset('maps/vendor/proj4.js') }}"></script>
<script>
// Manual Upload reference beacon (Recording 5, 2026-11-16). The Beacon Register holds X / Y in Minna / UTM 32N
// (EPSG:26332); a picked beacon's X / Y are converted to the chosen system, the same definitions as the plan card.
window.addEventListener('load', function () {
  var root = document.getElementById('mr');
  if (!root || root.dataset.ready || !window.jQuery || !jQuery.fn.select2) return;
  root.dataset.ready = '1';
  var MINNA = '+a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs';
  if (window.proj4) {
    proj4.defs('EPSG:26391', '+proj=tmerc +lat_0=4 +lon_0=4.5 +k=0.99975 +x_0=230738.26 +y_0=0 ' + MINNA);
    proj4.defs('EPSG:26392', '+proj=tmerc +lat_0=4 +lon_0=8.5 +k=0.99975 +x_0=670553.98 +y_0=0 ' + MINNA);
    proj4.defs('EPSG:26393', '+proj=tmerc +lat_0=4 +lon_0=12.5 +k=0.99975 +x_0=1110369.7 +y_0=0 ' + MINNA);
    proj4.defs('EPSG:26332', '+proj=utm +zone=32 ' + MINNA);
    proj4.defs('EPSG:32632', '+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs');
  }
  var OTHER = '__other', $ = function (id) { return document.getElementById(id); };
  var sel = $('mrBeacon'), other = $('mrOther'), no = $('mrNo'), src = $('mrSrc'), x = $('mrX'), y = $('mrY'), sys = $('mrSys'), hint = $('mrHint');
  var pick = null;   // the picked register beacon: {x, y} in EPSG:26332
  var setAuto = function (on) { x.classList.toggle('auto', on); y.classList.toggle('auto', on); x.dataset.auto = y.dataset.auto = on ? '1' : ''; };
  function fill() {
    if (!pick || !window.proj4) { if (x.dataset.auto) { x.value = ''; y.value = ''; setAuto(false); } hint.textContent = ''; return; }
    if ((x.value.trim() || y.value.trim()) && !x.dataset.auto) { hint.textContent = 'X / Y kept as typed. Clear them to take the Beacon Register values.'; return; }
    var p = proj4('EPSG:26332', 'EPSG:' + sys.value, [+pick.x, +pick.y]);
    x.value = p[0].toFixed(3); y.value = p[1].toFixed(3); setAuto(true);
    hint.textContent = 'X / Y from the Beacon Register, in ' + sys.options[sys.selectedIndex].text + '.';
  }
  [x, y].forEach(function (i) { i.addEventListener('input', function () { setAuto(false); hint.textContent = ''; }); });
  sys.addEventListener('change', function () { if (x.dataset.auto) { x.value = ''; y.value = ''; setAuto(false); } fill(); });
  jQuery(sel).select2({
    width: '100%', placeholder: 'Search beacon, e.g. A0876A', allowClear: true, minimumInputLength: 0,
    dropdownParent: jQuery(root.closest('.sv-modal, .sv-modal-dialog') || document.body),
    templateResult: function (d) { return d.loading ? d.text : d.id === OTHER ? jQuery('<b style="color:#1d4ed8">').text('Other (specify)') : jQuery('<span>').append(jQuery('<b>').text(d.text), d.note ? jQuery('<span style="color:#6b7280">').text(' · ' + d.note) : ''); },
    ajax: { url: root.dataset.search, dataType: 'json', delay: 200, data: function (p) { return { q: p.term || '' }; },
      processResults: function (res) { return { results: (res.data || []).map(function (b) { return { id: b.beacon_no, text: b.beacon_no, x: b.x, y: b.y, source: b.source, note: b.source === 'extant' ? 'Extant Beacons list' : (b.x ? 'Beacon Register' : 'captured') }; }).concat([{ id: OTHER, text: 'Other (specify)' }]) }; } },
  }).on('select2:open', function () { setTimeout(function () { var f = document.querySelector('.select2-container--open .select2-search__field'); if (f) f.focus(); }, 0); })
    .on('select2:select', function (e) {
      var d = e.params.data || {}, isO = d.id === OTHER;
      other.hidden = !isO; other.disabled = !isO; other.required = isO;
      if (isO) { other.value = ''; no.value = ''; src.value = 'typed'; pick = null; setTimeout(function () { other.focus(); }, 0); }
      else { no.value = d.id; src.value = d.source === 'extant' ? 'extant' : 'captured'; pick = d.x && d.y ? { x: d.x, y: d.y } : null; }
      if (x.dataset.auto) { x.value = ''; y.value = ''; setAuto(false); }
      fill();
    })
    .on('select2:clear', function () { other.hidden = true; other.disabled = true; other.required = false; no.value = ''; src.value = ''; pick = null; fill(); });
  other.addEventListener('input', function () { no.value = other.value.trim().toUpperCase(); });
  other.addEventListener('change', function () { other.value = other.value.trim().toUpperCase(); no.value = other.value; });
  // The reference beacon is required: sending without it opens the list.
  var form = root.closest('form');
  if (form) form.addEventListener('submit', function (e) {
    if (no.value.trim()) return;
    e.preventDefault();
    if (!other.hidden) { other.focus(); return; }
    root.scrollIntoView({ block: 'center' });
    jQuery(sel).select2('open');
  }, true);
});
</script>
