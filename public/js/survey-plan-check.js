/*
 | Survey Plan Check card (CR 11112026-2 item 8; user, 2026-11-12). From the approved prototype
 | /maps/survey-plan-check.html. Markup: resources/views/survey_module/survey_plans/_plan_check(.blade.php|_export).
 | 1 coordinate system · 2 one reference coordinate (drives the map) · 3 map · 4 other beacons in order of the traverse,
 | by X / Y or by bearing and distance from X1 (2026-11-13)
 | (joined, polygon) · export. Upload mode submits coord_system and beacons[0][...]; check mode saves nothing.
 | Starts on window load: jQuery and select2 load at the foot of both layouts.
 */
window.addEventListener('load', function () {
  'use strict';
  const root = document.getElementById('spc');
  if (!root || root.dataset.ready) return;
  root.dataset.ready = '1';
  const $ = id => document.getElementById('spc-' + id);
  const jq = window.jQuery;
  const MODE = root.dataset.mode || 'upload', SEARCH = root.dataset.search || '';
  let PREFILL = null;
  try { PREFILL = JSON.parse(root.dataset.prefill || 'null'); } catch (e) { PREFILL = null; }

  /* ---------- coordinate systems ---------- */
  // NTM belts (EPSG 26391-26393) and UTM 32N on WGS 84. Minna / UTM 32N (26332) is how the Beacon Register is
  // stored; it is converted, never offered.
  const MINNA = '+a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs';   // Clarke 1880 (RGS), as ArcMap
  proj4.defs('EPSG:26391', '+proj=tmerc +lat_0=4 +lon_0=4.5 +k=0.99975 +x_0=230738.26 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26392', '+proj=tmerc +lat_0=4 +lon_0=8.5 +k=0.99975 +x_0=670553.98 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26393', '+proj=tmerc +lat_0=4 +lon_0=12.5 +k=0.99975 +x_0=1110369.7 +y_0=0 ' + MINNA);
  // Minna / UTM Zone 32N, as the surveyor's ArcMap gives it: Clarke 1880 (RGS), a 6378249.145, 1/f 293.465.
  proj4.defs('EPSG:26332', '+proj=utm +zone=32 +a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs');
  proj4.defs('EPSG:32632', '+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs');
  const GRID_NAME = { 26332: 'Minna / UTM Zone 32N (EPSG:26332)', 26391: 'NTM West Belt, Minna (EPSG:26391)', 26392: 'NTM Mid Belt, Minna (EPSG:26392)', 26393: 'NTM East Belt, Minna (EPSG:26393)', 32632: 'UTM 32N, WGS 84 (EPSG:32632)' };
  // Minna / UTM 32N is option 1 and the default (user's surveyor, 2026-11-13).
  const gridCode = () => $('sysMinna').checked ? '26332' : $('sysUtm').checked ? '32632' : (root.querySelector('input[name=spc_belt]:checked') || {}).value || '26392';
  const KANO = { s: 10.3, n: 12.9, w: 7.6, e: 9.5 };
  const inKano = ([lon, lat]) => lat >= KANO.s && lat <= KANO.n && lon >= KANO.w && lon <= KANO.e;
  const toLL = (code, x, y) => proj4('EPSG:' + code, 'EPSG:4326', [x, y]);
  const toXY = (code, lon, lat) => proj4('EPSG:4326', 'EPSG:' + code, [lon, lat]);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const key = s => String(s ?? '').toUpperCase().replace(/\s+/g, '');
  const fmt = (n, d = 3) => Number(n).toLocaleString('en-GB', { minimumFractionDigits: d, maximumFractionDigits: d });
  function metres(a, b) {
    const r = Math.PI / 180, lat = (a[1] + b[1]) / 2 * r;
    return Math.hypot((b[0] - a[0]) * r * Math.cos(lat) * 6378137, (b[1] - a[1]) * r * 6378137);
  }
  function areaOf(ring, code = gridCode()) {
    const p = ring.map(c => toXY(code, c[0], c[1]));
    return Math.abs(p.reduce((s, a, i) => { const b = p[(i + 1) % p.length]; return s + a[0] * b[1] - b[0] * a[1]; }, 0) / 2);
  }
  const lengthOf = coords => coords.slice(1).reduce((s, c, i) => s + metres(coords[i], c), 0);
  function bearing(a, b) {   // grid bearing, D° M' S"
    let d = Math.atan2(b.x - a.x, b.y - a.y) * 180 / Math.PI; if (d < 0) d += 360;
    let deg = Math.floor(d), m = Math.floor((d - deg) * 60), s = Math.round(((d - deg) * 60 - m) * 60);
    if (s === 60) { s = 0; m++; } if (m === 60) { m = 0; deg++; }
    return `${deg}° ${String(m).padStart(2, '0')}' ${String(s).padStart(2, '0')}"`;
  }
  const median = a => { const s = [...a].sort((p, q) => p - q), m = s.length >> 1; return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2; };

  /* ---------- map ---------- */
  const protocol = new pmtiles.Protocol();
  maplibregl.addProtocol('pmtiles', protocol.tile);
  const empty = { type: 'FeatureCollection', features: [] };
  const OUR = ['register', 'register-label', 'links', 'poly-fill', 'sv-line', 'sides', 'sv', 'sv-label', 'ref', 'ref-label',
    'drawn-fill', 'drawn-line', 'drawn-point', 'drawn-label', 'draft-line', 'draft-point', 'snap', 'plain', 'grid-halo', 'grid-line'];
  let map = null, mapReady = false;

  async function initMap() {
    const style = await fetch('/maps/style-light.json').then(r => r.json());
    if (style.sprite?.startsWith('/')) style.sprite = location.origin + style.sprite;
    if (style.glyphs?.startsWith('/')) style.glyphs = location.origin + style.glyphs;
    Object.values(style.sources).forEach(s => { if (s.attribution) s.attribution = s.attribution.replace(/Â©/g, '©'); });
    map = new maplibregl.Map({ container: 'spc-map', style, center: [8.52, 12.0], zoom: 10, maxBounds: [[6.9, 9.6], [10.2, 13.6]], attributionControl: false });
    map.addControl(new maplibregl.NavigationControl(), 'top-right');
    map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
    map.once('idle', () => root.querySelectorAll('.maplibregl-ctrl-attrib.maplibregl-compact-show').forEach(el => { el.classList.remove('maplibregl-compact-show'); el.removeAttribute('open'); }));
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }));
    // The card can open hidden (a pop-up); keep the map sized to its box.
    if (window.ResizeObserver) new ResizeObserver(() => map && map.resize()).observe($('map'));
    map.on('load', () => {
      map.addSource('satellite', { type: 'raster', tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'], tileSize: 256, maxzoom: 19, attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics' });
      const firstLabel = map.getStyle().layers.find(l => l.type === 'symbol')?.id;
      map.addLayer({ id: 'satellite', type: 'raster', source: 'satellite' }, firstLabel);
      ['register', 'ref', 'sv', 'svline', 'poly', 'sides', 'links', 'drawn', 'draft', 'snap', 'grid'].forEach(id => map.addSource(id, { type: 'geojson', data: empty }));
      // Plain backdrop + coordinate grid for plotting without a basemap (Kunle, 2026-11-14).
      map.addLayer({ id: 'plain', type: 'background', layout: { visibility: 'none' }, paint: { 'background-color': '#f8fafc' } }, map.getStyle().layers[0]?.id);
      map.addLayer({ id: 'grid-halo', type: 'line', source: 'grid', paint: { 'line-color': '#fff', 'line-width': 3.4, 'line-opacity': 0.85 } });
      map.addLayer({ id: 'grid-line', type: 'line', source: 'grid', paint: { 'line-color': '#1e293b', 'line-width': 1.2 } });
      map.addLayer({ id: 'register', type: 'circle', source: 'register', paint: {
        'circle-color': ['match', ['get', 'kind'], 'C', '#7c3aed', '#2563eb'],
        'circle-radius': ['interpolate', ['linear'], ['zoom'], 10, 2.5, 16, 6], 'circle-stroke-color': '#fff', 'circle-stroke-width': 1 } });
      map.addLayer({ id: 'register-label', type: 'symbol', source: 'register', minzoom: 16, layout: {
        'text-field': ['get', 'beacon'], 'text-font': ['Noto Sans Regular'], 'text-size': 11, 'text-offset': [0, 1.1], 'text-anchor': 'top' },
        paint: { 'text-color': '#fff', 'text-halo-color': '#1e3a8a', 'text-halo-width': 1.5 } });
      map.addLayer({ id: 'links', type: 'line', source: 'links', paint: { 'line-color': '#dc2626', 'line-width': 2, 'line-dasharray': [2, 1] } });
      map.addLayer({ id: 'poly-fill', type: 'fill', source: 'poly', paint: { 'fill-color': '#06b6d4', 'fill-opacity': 0.18 } });
      map.addLayer({ id: 'sv-line', type: 'line', source: 'svline', paint: { 'line-color': '#22d3ee', 'line-width': 3 } });
      map.addLayer({ id: 'sides', type: 'symbol', source: 'sides', layout: { 'text-field': ['get', 'label'], 'text-font': ['Noto Sans Medium'], 'text-size': 11 },
        paint: { 'text-color': '#fff', 'text-halo-color': '#0e7490', 'text-halo-width': 1.6 } });
      map.addLayer({ id: 'sv', type: 'circle', source: 'sv', paint: { 'circle-color': '#0891b2', 'circle-radius': 6, 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 } });
      map.addLayer({ id: 'sv-label', type: 'symbol', source: 'sv', layout: { 'text-field': ['get', 'label'], 'text-font': ['Noto Sans Medium'], 'text-size': 12, 'text-offset': [0, -1.2], 'text-anchor': 'bottom', 'text-allow-overlap': true },
        paint: { 'text-color': '#fff', 'text-halo-color': '#155e75', 'text-halo-width': 1.6 } });
      map.addLayer({ id: 'ref', type: 'circle', source: 'ref', paint: { 'circle-color': '#ea580c', 'circle-radius': 8, 'circle-stroke-color': '#fff', 'circle-stroke-width': 2.5 } });
      map.addLayer({ id: 'ref-label', type: 'symbol', source: 'ref', layout: { 'text-field': ['get', 'beacon'], 'text-font': ['Noto Sans Medium'], 'text-size': 12, 'text-offset': [0, 1.3], 'text-anchor': 'top', 'text-allow-overlap': true },
        paint: { 'text-color': '#fff', 'text-halo-color': '#9a3412', 'text-halo-width': 1.6 } });
      map.addLayer({ id: 'drawn-fill', type: 'fill', source: 'drawn', filter: ['==', ['geometry-type'], 'Polygon'], paint: { 'fill-color': '#16a34a', 'fill-opacity': 0.18 } });
      map.addLayer({ id: 'drawn-line', type: 'line', source: 'drawn', filter: ['!=', ['geometry-type'], 'Point'], paint: { 'line-color': '#4ade80', 'line-width': 2.5 } });
      map.addLayer({ id: 'drawn-point', type: 'circle', source: 'drawn', filter: ['==', ['geometry-type'], 'Point'], paint: { 'circle-color': '#16a34a', 'circle-radius': 6, 'circle-stroke-color': '#fff', 'circle-stroke-width': 2 } });
      map.addLayer({ id: 'drawn-label', type: 'symbol', source: 'drawn', layout: { 'text-field': ['get', 'label'], 'text-font': ['Noto Sans Medium'], 'text-size': 12, 'text-offset': [0, -1.2], 'text-anchor': 'bottom', 'text-allow-overlap': true },
        paint: { 'text-color': '#fff', 'text-halo-color': '#166534', 'text-halo-width': 1.5 } });
      map.addLayer({ id: 'draft-line', type: 'line', source: 'draft', filter: ['!=', ['geometry-type'], 'Point'], paint: { 'line-color': '#facc15', 'line-width': 2, 'line-dasharray': [2, 1.5] } });
      map.addLayer({ id: 'draft-point', type: 'circle', source: 'draft', filter: ['==', ['geometry-type'], 'Point'], paint: { 'circle-color': '#facc15', 'circle-radius': 4, 'circle-stroke-color': '#111', 'circle-stroke-width': 1 } });
      map.addLayer({ id: 'snap', type: 'circle', source: 'snap', paint: { 'circle-radius': 9, 'circle-color': 'rgba(0,0,0,0)', 'circle-stroke-width': 2.5,
        'circle-stroke-color': ['match', ['get', 'kind'], 'edge', '#facc15', '#22c55e'] } });
      setMode('satellite');
      ['register', 'ref', 'sv', 'drawn-point'].forEach(layer => {
        map.on('click', layer, e => { if (!tool) popup(e.features[0], e.lngLat); });
        map.on('mouseenter', layer, () => { if (!tool) map.getCanvas().style.cursor = 'pointer'; });
        map.on('mouseleave', layer, () => { if (!tool) map.getCanvas().style.cursor = ''; });
      });
      mapReady = true;
      map.on('moveend', drawGrid); map.on('resize', drawGrid);
      wireDrawing();
      loadRegister();
      refresh();
      const r0 = rowsOf(refBody)[0];
      if (r0) goTo(r0);
    });
  }
  let BASE = 'satellite';
  function setMode(m) {
    BASE = m;
    const sat = m === 'satellite', base = $('baseOn').checked;
    map.getStyle().layers.forEach(l => {
      if (OUR.includes(l.id) || l.id.startsWith('lyr')) return;
      const vis = base && (l.id === 'satellite' ? sat : !(sat && l.type !== 'symbol'));
      map.setLayoutProperty(l.id, 'visibility', vis ? 'visible' : 'none');
    });
    map.setLayoutProperty('plain', 'visibility', base ? 'none' : 'visible');
    $('btn-street').classList.toggle('active', base && !sat);
    $('btn-satellite').classList.toggle('active', base && sat);
  }
  const pickBase = m => { if (!map) return; $('baseOn').checked = true; setMode(m); };
  $('btn-street').onclick = () => pickBase('street');
  $('btn-satellite').onclick = () => pickBase('satellite');
  // Basemap off = plain backdrop; the grid comes on with it so the plot stays georeferenced.
  $('baseOn').onchange = () => { if (!map || !mapReady) return; if (!$('baseOn').checked) $('gridOn').checked = true; setMode(BASE); drawGrid(); };
  $('gridOn').onchange = () => drawGrid();
  // Plan-sheet grid (user, 2026-11-14): crosses (+) at every 1/2/5 x 10^n metre intersection in the chosen system,
  // the mE / mN values in a border round the map, a north arrow and the on-screen scale as 1 : N.
  const gridEdge = document.createElement('div'), north = document.createElement('div');
  gridEdge.className = 'spc-gridedge'; north.className = 'spc-north';
  north.innerHTML = '<svg viewBox="0 0 24 34" width="20" height="28" aria-hidden="true"><path d="M12 1 20 27 12 22 4 27Z" fill="#0f172a"/><path d="M12 1 12 22 4 27Z" fill="#fff" stroke="#0f172a" stroke-width="1.2"/></svg><b>N</b><span></span>';
  document.getElementById('spc-map').append(gridEdge, north);
  function drawGrid() {
    if (!map || !mapReady) return;
    const src = map.getSource('grid'), on = $('gridOn').checked;
    gridEdge.hidden = north.hidden = !on; map.getContainer().classList.toggle('spc-sheet', on);
    if (!on) return src.setData(empty);
    const code = gridCode(), b = map.getBounds(), W = map.getContainer().clientWidth, H = map.getContainer().clientHeight;
    const cs = [[b.getWest(), b.getSouth()], [b.getEast(), b.getSouth()], [b.getEast(), b.getNorth()], [b.getWest(), b.getNorth()]].map(c => toXY(code, c[0], c[1]));
    const x0 = Math.min(...cs.map(c => c[0])), x1 = Math.max(...cs.map(c => c[0])), y0 = Math.min(...cs.map(c => c[1])), y1 = Math.max(...cs.map(c => c[1]));
    const raw = Math.max(x1 - x0, y1 - y0) / 8, p = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = Math.max(1, [1, 2, 5, 10].map(k => k * p).find(k => k >= raw)), arm = step * 0.07;
    const xs = [], ys = [], f = [], seg = (a, c) => f.push({ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [toLL(code, a[0], a[1]), toLL(code, c[0], c[1])] } });
    for (let i = Math.ceil(x0 / step); i * step <= x1; i++) xs.push(i * step);
    for (let i = Math.ceil(y0 / step); i * step <= y1; i++) ys.push(i * step);
    xs.forEach(x => ys.forEach(y => { seg([x - arm, y], [x + arm, y]); seg([x, y - arm], [x, y + arm]); }));
    src.setData({ type: 'FeatureCollection', features: f });
    // Border labels where each grid value meets the map edge.
    const ym = (y0 + y1) / 2, xm = (x0 + x1) / 2, lab = [];
    xs.forEach(x => { const q = map.project(toLL(code, x, ym)); if (q.x > 40 && q.x < W - 40) lab.push(`<span class="t" style="left:${q.x}px">${x} mE</span><span class="b" style="left:${q.x}px">${x} mE</span>`); });
    ys.forEach(y => { const q = map.project(toLL(code, xm, y)); if (q.y > 40 && q.y < H - 40) lab.push(`<span class="l" style="top:${q.y}px">${y} mN</span><span class="r" style="top:${q.y}px">${y} mN</span>`); });
    gridEdge.innerHTML = lab.join('');
    // Scale on this screen (96 dpi), rounded to 2 figures.
    const c = map.getCenter(), mpp = 40075016.686 * Math.cos(c.lat * Math.PI / 180) / (512 * Math.pow(2, map.getZoom()));
    const n = mpp / 0.00026458, r = Math.pow(10, Math.floor(Math.log10(n)) - 1);
    north.querySelector('span').textContent = '1 : ' + (Math.round(n / r) * r).toLocaleString('en');
  }
  function popup(f, at) {
    const p = f.properties;
    const what = { R: ' <span style="color:#ea580c">(reference)</span>', S: ' <span style="color:#0891b2">(other beacon)</span>', C: ' (control point)', D: ' <span style="color:#16a34a">(drawn)</span>' }[p.kind] || '';
    const html = `<b>${esc(p.beacon || p.name)}</b>${what}<br>`
      + (p.x !== undefined ? `X ${fmt(p.x)} · Y ${fmt(p.y)}${p.grid ? `<br><span style="color:#6b7280">${esc(p.grid)}</span>` : ''}<br>` : '')
      + `${fmt(p.lat, 7)}, ${fmt(p.lon, 7)}` + (p.plan_no ? `<br>Plan ${esc(p.plan_no)}` : '') + (p.district ? `<br>${esc(p.district)}` : '');
    new maplibregl.Popup({ maxWidth: '260px' }).setLngLat(at || f.geometry.coordinates).setHTML(html).addTo(map);
  }

  /* ---------- Beacon Register (map dots and X / Y) ---------- */
  let REG = [];
  const REG_BY = new Map();
  async function loadRegister() {
    const note = $('data-note');
    try {
      const data = await fetch('/maps/data/beacons.json', { cache: 'no-cache' }).then(r => r.json());
      REG = data.rows.map(([beacon, x, y, kind, plan_no, district]) => { const [lon, lat] = toLL('26332', x, y); return { beacon, kind, plan_no, district, lon, lat }; });
      REG.forEach(b => { if (!REG_BY.has(key(b.beacon))) REG_BY.set(key(b.beacon), b); });
      map.getSource('register').setData({ type: 'FeatureCollection', features: REG.map(b => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [b.lon, b.lat] }, properties: b })) });
      note.textContent = `${REG.length.toLocaleString()} beacons in the Beacon Register on the map.`;
      allRows().forEach(tr => lookup(tr));
      refresh();
      const r0 = rowsOf(refBody)[0];
      if (r0) goTo(r0);
    } catch (e) { note.textContent = 'The Beacon Register could not be loaded on the map. Typed X / Y still work.'; }
  }
  const regXY = b => toXY(gridCode(), b.lon, b.lat);

  /* ---------- rows: one reference coordinate, then the other beacons ---------- */
  const refBody = $('refRows'), svBody = $('svRows');
  const rowsOf = body => [...body.children].filter(tr => tr._hint);
  const allRows = () => [...rowsOf(refBody), ...rowsOf(svBody)];
  function addRow(body, b = '', x = '', y = '', source = '') {
    const isRef = body === refBody, named = isRef && MODE === 'upload';
    const tr = document.createElement('tr');
    tr.innerHTML = `<td class="n"></td><td class="b"><select class="spc-b" aria-label="Beacon No." data-no-search></select>`
      + `<input type="text" class="spc-other" maxlength="60" placeholder="Specify the beacon number" aria-label="Specify the beacon number" hidden>`
      + (named ? '<input type="hidden" class="spc-bno" name="beacons[0][beacon_no]"><input type="hidden" class="spc-src" name="beacons[0][source]">' : '') + `</td>`
      + `<td><input type="text" class="spc-x" inputmode="decimal" maxlength="40" aria-label="X (Easting)"${named ? ' name="beacons[0][x]"' : ''}></td>`
      + `<td><input type="text" class="spc-y" inputmode="decimal" maxlength="40" aria-label="Y (Northing)"${named ? ' name="beacons[0][y]"' : ''}></td>`
      + `<td class="act"><button type="button" class="spc-go" title="Go to point"><i class="fas fa-location-crosshairs"></i> Go to point</button>`
      + (isRef ? `<button type="button" class="spc-ib spc-del" aria-label="Clear" title="Clear"><i class="fas fa-xmark"></i></button></td>`
        : `<button type="button" class="spc-ib spc-up" aria-label="Move up" title="Move up"><i class="fas fa-arrow-up"></i></button><button type="button" class="spc-ib spc-down" aria-label="Move down" title="Move down"><i class="fas fa-arrow-down"></i></button>`
        + `<button type="button" class="spc-ib spc-del" aria-label="Remove" title="Remove"><i class="fas fa-xmark"></i></button></td>`);
    const hint = document.createElement('tr'); hint.className = 'hint';
    hint.innerHTML = `<td></td><td colspan="4"></td>`;
    tr._hint = hint;
    const bIn = tr.querySelector('.spc-b'), xIn = tr.querySelector('.spc-x'), yIn = tr.querySelector('.spc-y');
    if (b) bIn.appendChild(new Option(b, b, true, true));
    tr._lastB = b; tr._src = source || '';
    // Other (specify): the typed beacon number (rule 6: one "Other" with a required specify box).
    const oIn = tr.querySelector('.spc-other');
    oIn.addEventListener('change', () => { oIn.value = oIn.value.trim().toUpperCase(); lookup(tr); syncSource(tr); refresh(); goTo(tr); });
    oIn.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); oIn.dispatchEvent(new Event('change')); } });
    xIn.value = x; yIn.value = y;
    [xIn, yIn].forEach(i => {
      i.addEventListener('input', () => { tr.dataset.auto = ''; });
      i.addEventListener('change', refresh);
      i.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); refresh(); goTo(tr); } });
    });
    tr.querySelector('.spc-del').onclick = () => { if (isRef) { setRef(); return; } jq(bIn).select2('destroy'); tr.remove(); hint.remove(); number(); refresh(); };
    tr.querySelector('.spc-go').onclick = () => { lookup(tr); refresh(); goTo(tr); };
    if (!isRef) {
      tr.querySelector('.spc-up').onclick = () => { const p = tr.previousElementSibling?.previousElementSibling; if (p) { body.insertBefore(tr, p); body.insertBefore(hint, p); number(); refresh(); } };
      tr.querySelector('.spc-down').onclick = () => { const n = hint.nextElementSibling; if (n) { const after = n._hint; body.insertBefore(n, tr); body.insertBefore(after, tr); number(); refresh(); } };
    }
    body.appendChild(tr); body.appendChild(hint); number();
    beaconPicker(tr, bIn);
    if (b) lookup(tr);
    syncSource(tr);
    return tr;
  }
  function number() {
    rowsOf(refBody).forEach(tr => tr.querySelector('.n').textContent = 'R');
    rowsOf(svBody).forEach((tr, i) => tr.querySelector('.n').textContent = i + 1);
  }
  function syncSource(tr) {
    const s = tr.querySelector('.spc-src'), n = tr.querySelector('.spc-bno');
    if (n) n.value = beaconOf(tr);
    if (s) s.value = isOther(tr) ? 'typed' : (tr._reg || tr._src === 'captured' ? 'captured' : (tr._src === 'extant' ? 'extant' : 'typed'));
  }
  const OTHER = '__other';
  const isOther = tr => tr.querySelector('.spc-b').value === OTHER;
  // The row's beacon number: the listed one, or what was typed under Other (specify).
  const beaconOf = tr => isOther(tr) ? tr.querySelector('.spc-other').value.trim().toUpperCase() : tr.querySelector('.spc-b').value.trim();
  function showOther(tr, on) {
    const o = tr.querySelector('.spc-other');
    o.hidden = !on; o.required = on && MODE === 'upload' && tr.parentElement === refBody;
    if (!on) o.value = ''; else setTimeout(() => o.focus(), 0);
  }
  // Beacon No.: KLAES's beacon search (Beacon Register, then the Extant Beacons list), and one "Other (specify)"
  // at the foot of every list (user, 2026-11-12) that opens a box for a number that is not listed.
  function beaconPicker(tr, sel) {
    jq(sel).select2({
      width: '100%', placeholder: 'Search beacon, e.g. A0876A', allowClear: true,
      dropdownParent: jq(root.closest('.sv-modal, .sv-modal-dialog') || document.body),
      templateResult: d => d.loading ? d.text : d.id === OTHER ? jq('<span class="spc-new">').text('Other (specify)')
        : jq('<span>').append(jq('<b>').text(d.text), d.note ? jq('<span class="spc-dist">').text(' \u00b7 ' + d.note) : ''),
      templateSelection: d => d.id === OTHER ? 'Other (specify)' : d.text,
      minimumInputLength: 0,
      ajax: { url: SEARCH, dataType: 'json', delay: 200, data: p => ({ q: p.term || '' }),
        processResults: res => ({ results: (res.data || []).map(b => ({ id: b.beacon_no, text: b.beacon_no, x: b.x, y: b.y, source: b.source,
          note: b.source === 'extant' ? 'Extant Beacons list' : (b.x ? 'Beacon Register' : 'captured') })).concat([{ id: OTHER, text: 'Other (specify)' }]) }) },
    // select2 4.1 does not always put the cursor in its search box (inside a pop-up): put it there.
    }).on('select2:open', () => setTimeout(() => { const f = document.querySelector('.select2-container--open .select2-search__field'); if (f) f.focus(); }, 0))
      .on('select2:select', e => { const d = e.params.data || {}; tr._pick = d; tr._src = d.id === OTHER ? 'typed' : (d.source || ''); })
      .on('change select2:select select2:clear', () => setTimeout(() => {
        const v = sel.value; if (v === tr._lastB) return; tr._lastB = v;
        if (!v) { tr._pick = null; tr._src = ''; }
        showOther(tr, v === OTHER);
        lookup(tr); syncSource(tr); refresh(); goTo(tr);
      }, 0));
  }
  // A register beacon fills its X / Y in the chosen system (only when blank, or filled by us before).
  function lookup(tr) {
    const no = beaconOf(tr);
    const b = no ? REG_BY.get(key(no)) : null;
    const xIn = tr.querySelector('.spc-x'), yIn = tr.querySelector('.spc-y');
    tr._reg = b || null;
    if (!no && tr.dataset.auto === '1') { xIn.value = ''; yIn.value = ''; tr.dataset.auto = ''; }
    const blank = tr.dataset.auto === '1' || (!xIn.value.trim() && !yIn.value.trim());
    if (!blank) return;
    let ll = null;
    if (b) ll = [b.lon, b.lat];
    else if (tr._pick && tr._pick.x && tr._pick.y && key(tr._pick.id) === key(no)) ll = toLL('26332', +tr._pick.x, +tr._pick.y);   // register X / Y from the search
    if (ll) { const [x, y] = toXY(gridCode(), ll[0], ll[1]); xIn.value = x.toFixed(3); yIn.value = y.toFixed(3); tr.dataset.auto = '1'; tr._fromReg = ll; }
    else if (tr.dataset.auto === '1') { xIn.value = ''; yIn.value = ''; tr.dataset.auto = ''; }
  }

  /* ---------- evaluate rows ---------- */
  function evalRow(tr, code, tol, autofix) {
    const o = { tr, beacon: beaconOf(tr), chips: [], bad: false, reg: tr._reg };
    const rawX = tr.querySelector('.spc-x').value.replace(/[,\s]/g, ''), rawY = tr.querySelector('.spc-y').value.replace(/[,\s]/g, '');
    o.empty = !o.beacon && !rawX && !rawY && !isOther(tr);
    if (o.empty) return o;
    if (isOther(tr) && !o.beacon) { o.chips.push(['warn', 'Other: type the beacon number, then its X and Y']); o.missing = true; return o; }
    const x = Number(rawX), y = Number(rawY);
    if (rawX === '' || rawY === '' || !isFinite(x) || !isFinite(y)) {
      o.chips.push(o.beacon && !o.reg ? ['warn', 'Not in the Beacon Register: type its X and Y'] : ['bad', 'X and Y must be numbers']);
      o.bad = !(o.beacon && !o.reg); o.missing = true; return o;
    }
    let ll = toLL(code, x, y), ux = x, uy = y;
    if (!inKano(ll)) {
      const sw = toLL(code, y, x);
      if (inKano(sw) && autofix) { ll = sw; ux = y; uy = x; o.chips.push(['warn', 'X and Y switched back']); }
      else if (inKano(sw)) { o.chips.push(['bad', 'X and Y look reversed']); o.bad = true; }
      else { o.chips.push(['bad', 'Outside Kano: check the coordinate system or the digits']); o.bad = true; }
    }
    Object.assign(o, { x: ux, y: uy, lon: ll[0], lat: ll[1] });
    if (o.reg && tr.dataset.auto !== '1') {
      o.dist = metres(ll, [o.reg.lon, o.reg.lat]);
      if (o.dist <= tol) o.chips.push(['ok', `Matches the Beacon Register (${fmt(o.dist, 2)} m)`]);
      else { o.chips.push(['bad', `${fmt(o.dist, 2)} m from its Beacon Register position`]); o.bad = true; }
    } else if (tr.dataset.auto === '1') o.chips.push(['ok', 'X / Y from the Beacon Register']);
    else if (o.beacon) o.chips.push(['info', tr._src === 'extant' ? 'From the Extant Beacons list' : 'Not in the Beacon Register']);
    return o;
  }

  let REFS = [], SV = [], POLY = false;
  function refresh() {
    const code = gridCode(), tol = Math.max(0.01, parseFloat($('tol').value) || 1), autofix = $('autofix').checked;
    $('coord').value = code;
    REFS = rowsOf(refBody).map(tr => evalRow(tr, code, tol, autofix));
    SV = rowsOf(svBody).map(tr => evalRow(tr, code, tol, autofix));
    const placed = list => list.filter(o => o.lon !== undefined && !o.bad);
    const ref = placed(REFS)[0] || null;
    const sv = placed(SV), all = ref ? [ref, ...sv] : sv;
    if (all.length >= 3) {   // a beacon far from the rest is a typing error, wherever it lands
      const mid = [median(all.map(o => o.lon)), median(all.map(o => o.lat))];
      sv.forEach(o => { if (metres([o.lon, o.lat], mid) > 2000) { o.chips.push(['bad', 'More than 2 km from the other beacons']); o.bad = true; } });
    }
    const svOk = placed(SV);
    if (POLY && svOk.length < 3) POLY = false;

    const side = (a, o, name) => `<span class="chip info">From ${esc(name)}: ${fmt(Math.hypot(o.x - a.x, o.y - a.y), 2)} m · ${bearing(a, o)}</span>`;
    [...REFS, ...SV].forEach(o => {
      const cell = o.tr._hint.lastElementChild;
      if (o.empty) { cell.innerHTML = ''; return; }
      const i = svOk.indexOf(o);
      const s = i > 0 ? side(svOk[i - 1], o, svOk[i - 1].beacon || 'point ' + i) : (i === 0 && ref ? side(ref, o, 'the reference ' + (ref.beacon || '')) : '');
      cell.innerHTML = o.chips.map(c => `<span class="chip ${c[0]}">${esc(c[1])}</span>`).join('') + s;
    });

    const r0 = REFS.find(o => !o.empty), v = $('refVerdict');
    if (!r0) v.hidden = true;
    else if (r0.bad) { v.hidden = false; v.className = 'spc-verdict bad'; v.innerHTML = `<b>Check the reference coordinate</b><span>${esc(r0.beacon || 'Reference')}: ${esc(r0.chips.filter(c => c[0] === 'bad').map(c => c[1]).join('; '))}</span>`; }
    else if (r0.dist !== undefined) { v.hidden = false; v.className = 'spc-verdict ok'; v.innerHTML = `<b>OK</b><span>The reference coordinate matches the Beacon Register within ${fmt(tol, 2)} m (${fmt(r0.dist, 2)} m).</span>`; }
    else if (r0.lon !== undefined) { v.hidden = false; v.className = 'spc-verdict warn'; v.innerHTML = `<b>Plotted</b><span>The reference coordinate is on the map. ${r0.tr.dataset.auto === '1' ? 'Its X / Y came from the Beacon Register.' : 'It is not in the Beacon Register on the map, so there is nothing to compare.'}</span>`; }
    else v.hidden = true;

    const st = $('svStats');
    if (svOk.length >= 2) {
      const sides = svOk.slice(1).map((o, i) => Math.hypot(o.x - svOk[i].x, o.y - svOk[i].y));
      if (POLY) sides.push(Math.hypot(svOk[0].x - svOk[svOk.length - 1].x, svOk[0].y - svOk[svOk.length - 1].y));
      const area = POLY ? Math.abs(svOk.reduce((s, o, i) => { const n = svOk[(i + 1) % svOk.length]; return s + o.x * n.y - n.x * o.y; }, 0) / 2) : 0;
      st.hidden = false;
      st.innerHTML = `<div class="spc-stat"><span>Points</span><b>${svOk.length}</b></div>`
        + `<div class="spc-stat"><span>${POLY ? 'Perimeter' : 'Length'}</span><b>${fmt(sides.reduce((a, b) => a + b, 0), 2)} m</b></div>`
        + (POLY ? `<div class="spc-stat"><span>Area</span><b>${fmt(area, 2)} m²</b></div><div class="spc-stat"><span>Hectares</span><b>${fmt(area / 10000, 4)} ha</b></div>`
                + `<div class="spc-stat"><span>Closing side</span><b>${fmt(sides[sides.length - 1], 2)} m</b></div>`
                : `<div class="spc-stat"><span>Polygon</span><b style="font-size:13px">Not closed yet</b></div>`);
      if (brgMode() && TRAV) st.innerHTML += `<div class="spc-stat"><span>Closing error</span><b>${fmt(TRAV.err, 3)} m</b></div>`
        + (TRAV.err >= 0.0005 ? `<div class="spc-stat"><span>Precision</span><b>1 : ${Math.round(TRAV.ratio).toLocaleString()}</b></div>` : '');
    } else st.hidden = true;
    $('svPoly').innerHTML = `<i class="fas fa-draw-polygon"></i> ${POLY ? 'Open polygon' : 'Create polygon'}`;
    $('svPoly').disabled = svOk.length < 3;
    $('gridtag').textContent = GRID_NAME[code];
    drawGrid();
    const ex = $('ex-grid'); if (ex) ex.textContent = 'X / Y in ' + GRID_NAME[code];
    const fs = $('fullStats');
    fs.innerHTML = !svOk.length && !ref ? '' : `<b>${ref ? 'Reference ' + esc(ref.beacon || '') : 'No reference'} · ${svOk.length} other beacon(s)</b>`
      + (st.hidden ? '' : [...st.querySelectorAll('.spc-stat')].map(s => `<span>${s.querySelector('span').textContent}: ${s.querySelector('b').textContent}</span>`).join(''));

    if (!mapReady) return;
    const pt = (o, kind, label) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [o.lon, o.lat] },
      properties: { beacon: o.beacon || '?', label, x: o.x, y: o.y, lon: o.lon, lat: o.lat, kind, grid: GRID_NAME[code] } });
    map.getSource('ref').setData({ type: 'FeatureCollection', features: ref ? [pt(ref, 'R', ref.beacon)] : [] });
    map.getSource('sv').setData({ type: 'FeatureCollection', features: svOk.map((o, i) => pt(o, 'S', `${i + 1}${o.beacon ? ' · ' + o.beacon : ''}`)) });
    const ring = svOk.map(o => [o.lon, o.lat]);
    map.getSource('svline').setData(ring.length >= 2 ? { type: 'Feature', geometry: { type: 'LineString', coordinates: POLY ? [...ring, ring[0]] : ring }, properties: {} } : empty);
    map.getSource('poly').setData(POLY ? { type: 'Feature', geometry: { type: 'Polygon', coordinates: [[...ring, ring[0]]] }, properties: {} } : empty);
    const pairs = svOk.slice(1).map((o, i) => [svOk[i], o]); if (POLY) pairs.push([svOk[svOk.length - 1], svOk[0]]);
    map.getSource('sides').setData({ type: 'FeatureCollection', features: pairs.map(([a, b]) => ({ type: 'Feature',
      geometry: { type: 'Point', coordinates: [(a.lon + b.lon) / 2, (a.lat + b.lat) / 2] }, properties: { label: (brgMode() ? wcb((Math.atan2(b.x - a.x, b.y - a.y) * 180 / Math.PI + 360) % 360) + ' · ' : '') + `${fmt(Math.hypot(b.x - a.x, b.y - a.y), 2)} m` } })) });
    map.getSource('links').setData({ type: 'FeatureCollection', features: [...REFS, ...SV].filter(o => o.reg && o.dist !== undefined && o.lon !== undefined && o.chips.some(c => c[0] === 'bad'))
      .map(o => ({ type: 'Feature', geometry: { type: 'LineString', coordinates: [[o.lon, o.lat], [o.reg.lon, o.reg.lat]] }, properties: {} })) });
  }
  function goTo(tr) {
    const o = [...REFS, ...SV].find(r => r.tr === tr);
    if (!mapReady || !o) return;
    if (o.lon === undefined) { if (o.reg) map.flyTo({ center: [o.reg.lon, o.reg.lat], zoom: 17 }); return; }
    map.flyTo({ center: [o.lon, o.lat], zoom: 17 });
  }
  function fitAll() {
    const pts = [...REFS, ...SV].filter(o => o.lon !== undefined && !o.bad);
    if (!pts.length || !mapReady) return;
    const b = new maplibregl.LngLatBounds(); pts.forEach(o => b.extend([o.lon, o.lat]));
    map.fitBounds(b, { padding: 90, maxZoom: 19.75, duration: 600 });
  }

  // Changing the system: X / Y filled from the register are refilled in the new system; typed ones stay as typed.
  function systemChanged() {
    allRows().forEach(tr => { if (tr.dataset.auto === '1') { tr.querySelector('.spc-x').value = ''; tr.querySelector('.spc-y').value = ''; lookup(tr); } });
    refresh(); renderDrawn();
  }
  root.querySelectorAll('input[data-sys], input[name=spc_belt]').forEach(r => r.addEventListener('change', () => {
    if (r.name === 'spc_belt') $('sysNtm').checked = true;
    systemChanged();
  }));
  root.querySelectorAll('input[name=spc_belt]').forEach(r => r.addEventListener('click', () => { if (!$('sysNtm').checked) { $('sysNtm').checked = true; systemChanged(); } }));
  root.querySelectorAll('.spc-opt').forEach(c => c.addEventListener('click', e => { const r = c.querySelector('input[data-sys]'); if (e.target.closest('.spc-belt') || r.checked) return; r.checked = true; systemChanged(); }));
  ['tol', 'autofix'].forEach(id => $(id).addEventListener('change', refresh));
  function clearBody(body) { jq(body).find('select.spc-b').each((i, s) => { if (jq(s).data('select2')) jq(s).select2('destroy'); }); body.innerHTML = ''; }
  function setRef(b = '', x = '', y = '', source = '') { clearBody(refBody); addRow(refBody, b, x, y, source); refresh(); }
  function setSystem(code) {
    if (code === '26332') $('sysMinna').checked = true;
    else if (code === '32632') $('sysUtm').checked = true;
    else { $('sysNtm').checked = true; const r = root.querySelector(`input[name=spc_belt][value="${code || '26392'}"]`); if (r) r.checked = true; }
  }
  $('svAdd').onclick = () => jq(addRow(svBody).querySelector('.spc-b')).select2('open');
  $('svPlot').onclick = () => { refresh(); fitAll(); };
  $('svPoly').onclick = () => { POLY = !POLY; refresh(); if (POLY) fitAll(); };
  $('svClear').onclick = () => { if (brgMode()) { resetLines(); return; } clearBody(svBody); POLY = false; addRow(svBody); refresh(); };
  $('usepaste').onclick = () => {
    const lines = $('paste').value.split(/\r?\n/).map(l => l.trim()).filter(Boolean);
    if (!lines.length) return;
    clearBody(svBody); POLY = false;
    lines.forEach(l => { const p = l.split(/[\t,;]+|\s{2,}|\s(?=[\d.-]+(\s|$))/).map(s => s.trim()).filter(Boolean); addRow(svBody, String(p[0] || '').toUpperCase(), (p[1] || '').replace(/,/g, ''), (p[2] || '').replace(/,/g, '')); });
    refresh(); fitAll();
  };

  /* ---------- traverse: bearing and distance from a start point (user, 2026-11-13, from the prototype) ---------- */
  // Lines X1→X2, X2→X3 … each a whole-circle bearing (deg min sec, 0-360) and a distance; the points are worked out on
  // the grid of the chosen system and become the other beacons. With "closes", the last line should land on X1:
  // the gap is the closing error, and the points make the polygon.
  let TRAV = null, swappedNote = false;
  const travBody = $('travRows');
  function brgMode() { const r = root.querySelector('input[name=spc_svmode]:checked'); return !!r && r.value === 'brg'; }
  // Whole-circle bearing (user, 2026-11-13): degrees 0-359 clockwise from grid north, as on the plans.
  const azimuth = (d, m, s) => d + m / 60 + s / 3600;
  // Azimuth to D° MM′ SS″.
  function wcb(az) {
    az = ((az % 360) + 360) % 360;
    let d = Math.floor(az), m = Math.floor((az - d) * 60), s = Math.round(((az - d) * 60 - m) * 60);
    if (s === 60) { s = 0; m++; } if (m === 60) { m = 0; d++; } if (d === 360) d = 0;
    return `${d}° ${String(m).padStart(2, '0')}′ ${String(s).padStart(2, '0')}″`;
  }
  // The same bearing the N/S … E/W way (user, 2026-11-13): entry stays whole circle, this label is for checking
  // against ArcMap or the paper plan. 0 / 90 / 180 / 270 read Due North / East / South / West.
  function quadrantOf(az) {
    az = ((az % 360) + 360) % 360;
    const due = { 0: 'Due North', 90: 'Due East', 180: 'Due South', 270: 'Due West' }[Math.round(az * 3600) / 3600];
    if (due) return due;
    const [ns, ew, t] = az < 90 ? ['N', 'E', az] : az < 180 ? ['S', 'E', 180 - az] : az < 270 ? ['S', 'W', az - 180] : ['N', 'W', 360 - az];
    let d = Math.floor(t + 1e-9), m = Math.floor((t - d) * 60 + 1e-9), s = Math.round(((t - d) * 60 - m) * 60);
    if (s === 60) { s = 0; m++; } if (m === 60) { m = 0; d++; }
    return `${ns} ${d}° ${String(m).padStart(2, '0')}′ ${String(s).padStart(2, '0')}″ ${ew}`;
  }
  // A line's bearing as an azimuth (0-360 from grid north), or null. Up to 90°: a quadrant bearing read with the
  // N/S and E/W dropdowns, as on the plan and in ArcMap (N … E is the same as whole circle). Over 90° (to 359°): a
  // whole-circle bearing; the dropdowns are not used then (user, 2026-11-13).
  function lineAzimuth(tr) {
    const v = c => tr.querySelector(c).value.replace(/[,\s]/g, '');
    const d = v('.d'), m = v('.m') || '0', s = v('.s') || '0', dn = Number(d), mn = Number(m), sn = Number(s);
    if (d === '') return { empty: true };
    if (![dn, mn, sn].every(isFinite) || dn < 0 || mn < 0 || mn >= 60 || sn < 0 || sn >= 60) return { bad: true };
    const t = dn + mn / 60 + sn / 3600;
    if (t >= 360) return { bad: true };
    if (t > 90) return { az: t, whole: true };
    const ns = tr.querySelector('.ns').value, ew = tr.querySelector('.ew').value;
    return { az: ns === 'N' ? (ew === 'E' ? t : 360 - t) % 360 : (ew === 'E' ? 180 - t : 180 + t), whole: false };
  }
  function showQuadrant(tr) {
    const q = tr.querySelector('.qd'), b = lineAzimuth(tr);
    q.className = 'qd';
    tr.querySelectorAll('.ns, .ew').forEach(x => { x.disabled = !!b.whole; x.title = b.whole ? 'Over 90°: read as a whole-circle bearing' : ''; });
    if (b.empty) { q.textContent = 'N/S … E/W, or 0–360°'; q.classList.add('empty'); return; }
    if (b.bad) { q.textContent = 'Not a bearing: degrees 0–359, min / sec under 60'; q.classList.add('bad'); return; }
    q.textContent = b.whole ? `${wcb(b.az)} = ${quadrantOf(b.az)}` : `${quadrantOf(b.az)} = ${wcb(b.az)}`;
  }
  // A picked beacon's Beacon Register X / Y in the chosen system (the map's register, or the search result).
  function regGridOf(sel, name) {
    if (!name) return null;
    const b = REG_BY.get(key(name)), p = sel && sel._pick;
    const ll = b ? [b.lon, b.lat] : (p && p.x && p.y && key(p.id) === key(name) ? toLL('26332', +p.x, +p.y) : null);
    return ll ? toXY(gridCode(), ll[0], ll[1]) : null;
  }
  // The beacon number picked in a traverse dropdown, or typed under Other (specify).
  const pickedName = (sel, other) => sel.value === OTHER ? other.value.trim().toUpperCase() : sel.value.trim();
  function setPicked(sel, other, v) {
    jq(sel).empty();
    if (v) sel.appendChild(new Option(v, v, true, true));
    other.hidden = true; other.value = ''; sel._pick = null;
    jq(sel).trigger('change.select2');
  }
  // The same beacon search as the reference coordinate, with one Other (specify) at the foot.
  // exclude(): the beacon this picker may not take (a line's From beacon, for its To picker; user, 2026-11-13).
  function namePicker(sel, other, placeholder, onChange, exclude) {
    jq(sel).select2({
      width: '100%', placeholder, allowClear: true,
      dropdownParent: jq(root.closest('.sv-modal, .sv-modal-dialog') || document.body),
      templateResult: d => d.loading ? d.text : d.id === OTHER ? jq('<span class="spc-new">').text('Other (specify)')
        : jq('<span>').append(jq('<b>').text(d.text), d.note ? jq('<span class="spc-dist">').text(' · ' + d.note) : ''),
      templateSelection: d => d.id === OTHER ? 'Other (specify)' : d.text,
      minimumInputLength: 0,
      ajax: { url: SEARCH, dataType: 'json', delay: 200, data: p => ({ q: p.term || '' }),
        processResults: res => ({ results: (res.data || []).filter(b => { const ex = exclude && exclude(); return !ex || key(b.beacon_no) !== key(ex); }).map(b => ({ id: b.beacon_no, text: b.beacon_no, x: b.x, y: b.y,
          note: b.source === 'extant' ? 'Extant Beacons list' : (b.x ? 'Beacon Register' : 'captured') })).concat([{ id: OTHER, text: 'Other (specify)' }]) }) },
    }).on('select2:open', () => setTimeout(() => { const f = document.querySelector('.select2-container--open .select2-search__field'); if (f) f.focus(); }, 0))
      .on('select2:select', e => { sel._pick = e.params.data || null; })
      .on('select2:select select2:clear', () => setTimeout(() => {
        const isO = sel.value === OTHER;
        if (!sel.value) sel._pick = null;
        other.hidden = !isO; if (!isO) other.value = ''; else setTimeout(() => other.focus(), 0);
        onChange();
      }, 0));
    other.addEventListener('change', () => { other.value = other.value.trim().toUpperCase(); onChange(); });
    other.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); other.dispatchEvent(new Event('change')); } });
  }
  // Enter in a traverse field works the lines out; it never submits the upload form.
  const enterComputes = el => el.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); computeTraverse(); } });
  function addLine(l = {}) {
    const tr = document.createElement('tr');
    const n = (cls, ph, label, w) => `<input type="text" inputmode="decimal" class="${cls}" maxlength="${w}" placeholder="${ph}" aria-label="${label}">`;
    // One row per line, From beacon then To beacon (user's sketch, 2026-11-13). Line 1's From cells hold the X1
    // picker and X / Y (moved in by computeTraverse); later lines show the line before's To beacon.
    tr.innerHTML = `<td class="ln" data-label="Line"></td>`
      + `<td class="fb mono" data-label="From beacon"><span class="ptag fptag"></span><span class="fbn"></span></td><td class="fx mono" data-label="From X"></td><td class="fy mono" data-label="From Y"></td>`
      + `<td data-label="Bearing"><div class="brg"><select class="ns" aria-label="N or S"><option>N</option><option>S</option></select>`
      + n('d', 'deg', 'Degrees', 3) + '<span>°</span>' + n('m', 'min', 'Minutes', 2) + '<span>′</span>' + n('s', 'sec', 'Seconds', 6) + '<span>″</span>'
      + `<select class="ew" aria-label="E or W"><option>E</option><option>W</option></select></div>`
      + '<div class="qd" aria-live="polite"></div></td>'
      + `<td data-label="Distance (m)">${n('dist', 'm', 'Distance in metres', 12)}</td>`
      + `<td class="tbcell" data-label="To beacon"><span class="ptag tptag"></span><div class="spc-trav-pick tbwrap"><select class="tb" aria-label="To beacon" data-no-search></select>`
      + `<input type="text" class="tbo spc-tother" maxlength="60" placeholder="Specify the beacon number" aria-label="Specify the to beacon" hidden></div><span class="tbfix mono" hidden></span></td>`
      + `<td class="cx mono" data-label="To X">—</td><td class="cy mono" data-label="To Y">—</td>`
      + `<td class="act"><button type="button" class="spc-ib up" aria-label="Move up" title="Move up"><i class="fas fa-arrow-up"></i></button><button type="button" class="spc-ib down" aria-label="Move down" title="Move down"><i class="fas fa-arrow-down"></i></button><button type="button" class="spc-ib del" aria-label="Remove" title="Remove"><i class="fas fa-xmark"></i></button></td>`;
    const set = (c, v) => { if (v !== undefined && v !== '') tr.querySelector(c).value = v; };
    if (l.to) tr.querySelector('.tb').appendChild(new Option(l.to, l.to, true, true));
    set('.ns', l.ns); set('.d', l.d); set('.m', l.m); set('.s', l.s); set('.ew', l.ew); set('.dist', l.dist);
    tr.querySelectorAll('input:not(.tbo), select:not(.tb)').forEach(i => { i.addEventListener('change', computeTraverse); if (i.tagName === 'INPUT') enterComputes(i); });
    namePicker(tr.querySelector('.tb'), tr.querySelector('.tbo'), 'Beacon (optional)', computeTraverse, () => fromNameOf(tr));
    ['.d', '.m', '.s', '.ns', '.ew'].forEach(c => tr.querySelector(c).addEventListener(c[1] === 'n' || c[1] === 'e' ? 'change' : 'input', () => showQuadrant(tr)));
    showQuadrant(tr);
    tr.querySelector('.del').onclick = () => { parkStart(); jq(tr.querySelector('.tb')).select2('destroy'); tr.remove(); computeTraverse(); };
    tr.querySelector('.up').onclick = () => { const p = tr.previousElementSibling; if (p) { travBody.insertBefore(tr, p); computeTraverse(); } };
    tr.querySelector('.down').onclick = () => { const nx = tr.nextElementSibling; if (nx) { travBody.insertBefore(nx, tr); computeTraverse(); } };
    travBody.appendChild(tr);
    return tr;
  }
  // A line's From beacon: line 1's is the X1 picker, every later line's is the line before's To beacon.
  function fromNameOf(tr) {
    const prev = tr.previousElementSibling;
    return prev ? pickedName(prev.querySelector('.tb'), prev.querySelector('.tbo')) : pickedName($('trStartB'), $('trStartOther'));
  }
  // Four lines by default: most plots are four-sided (user, 2026-11-13).
  // The X1 picker and X / Y live in line 1; park them before a row is removed or the lines are reset.
  function parkStart() { ['trStartWrap', 'trStartX', 'trStartY'].forEach(id => $('trHold').appendChild($(id))); }
  function resetLines() {
    parkStart();
    jq(travBody).find('select.tb').each((i, s) => { if (jq(s).data('select2')) jq(s).select2('destroy'); });
    travBody.innerHTML = '';
    for (let i = 0; i < 4; i++) addLine();
    computeTraverse();
  }
  function startFromRef(force) {
    const r = REFS.find(o => o.lon !== undefined && !o.bad);
    if (!r) return false;
    if (force || (!$('trStartX').value.trim() && !$('trStartY').value.trim())) {
      setPicked($('trStartB'), $('trStartOther'), r.beacon || ''); $('trStartX').value = r.x.toFixed(3); $('trStartY').value = r.y.toFixed(3);
    }
    return true;
  }
  function computeTraverse() {
    const num = v => { const t = String(v).replace(/[,\s]/g, ''), x = Number(t); return t === '' || !isFinite(x) ? null : x; };
    {
      const ax = num($('trStartX').value), ay = num($('trStartY').value);
      if (ax !== null && ay !== null && Math.abs(ax) >= 1000000 && Math.abs(ay) < 1000000) {
        $('trStartX').value = $('trStartY').value; $('trStartY').value = String(ax);
        swappedNote = true;
      }
    }
    const sx = num($('trStartX').value), sy = num($('trStartY').value), close = $('trClose').checked;
    const rows = [...travBody.children], lines = rows.length;
    const pts = [], bad = [];
    let cur = sx !== null && sy !== null ? { x: sx, y: sy } : null, closeAt = null;
    const startName = pickedName($('trStartB'), $('trStartOther')) || 'X1';
    if (cur) pts.push({ b: startName, x: cur.x, y: cur.y });
    // Line 1 holds the X1 picker and X / Y; with no lines they wait in the hidden holder.
    const home = rows[0], hold = $('trHold');
    const put = (el, cell) => { if (el.parentElement !== cell) cell.appendChild(el); };
    if (home) { put($('trStartWrap'), home.querySelector('.fb')); put($('trStartX'), home.querySelector('.fx')); put($('trStartY'), home.querySelector('.fy')); }
    else { put($('trStartWrap'), hold); put($('trStartX'), hold); put($('trStartY'), hold); }
    // From / To show the point (X1, X2 …) and its beacon. A To beacon in the Beacon Register gives its X / Y
    // (backfill): the line's own bearing and distance are checked against it, and a line not entered yet carries
    // on from it (user, 2026-11-13).
    let prevName = startName, prevPicked = pickedName($('trStartB'), $('trStartOther'));
    const same = [];
    rows.forEach((tr, i) => {
      const last = close && i === lines - 1 && lines >= 3;
      const fromTag = 'X' + (i + 1), toTag = last ? 'X1' : 'X' + (i + 2);
      tr.querySelector('.ln').innerHTML = `<b>${i + 1}</b>${last ? '<div class="spc-note">closes</div>' : ''}`;
      tr.querySelector('.fptag').textContent = fromTag; tr.querySelector('.tptag').textContent = toTag;
      // From: line 1 is the picker (above); later lines start at the line before's To beacon.
      if (i > 0) {
        tr.querySelector('.fbn').textContent = prevPicked;
        tr.querySelector('.fx').textContent = cur ? fmt(cur.x) : '—'; tr.querySelector('.fy').textContent = cur ? fmt(cur.y) : '—';
      } else tr.querySelector('.fbn').textContent = '';
      // To: the closing line ends on X1, so its To beacon is X1's, not picked.
      const toSel = tr.querySelector('.tb'), toPicked = last ? '' : pickedName(toSel, tr.querySelector('.tbo'));
      tr.querySelector('.tbwrap').hidden = last; const fix = tr.querySelector('.tbfix'); fix.hidden = !last; fix.textContent = last ? pickedName($('trStartB'), $('trStartOther')) : '';
      const sameBeacon = !!toPicked && !!prevPicked && key(toPicked) === key(prevPicked);
      if (sameBeacon) same.push(i + 1);
      const reg = last || sameBeacon ? null : regGridOf(toSel, toPicked);
      prevName = last ? startName : (toPicked || toTag); prevPicked = last ? pickedName($('trStartB'), $('trStartOther')) : toPicked;
      const g = c => tr.querySelector(c).value;
      const d = num(g('.d')), m = num(g('.m')) ?? 0, s = num(g('.s')) ?? 0, dist = num(g('.dist'));
      const typed = g('.d').trim() !== '' || g('.dist').trim() !== '';
      const brg = lineAzimuth(tr);
      const ok = !sameBeacon && cur && d !== null && dist !== null && brg.az !== undefined && dist > 0;
      tr.classList.toggle('bad', sameBeacon || (!ok && typed && !!cur));
      const cx = tr.querySelector('.cx'), cy = tr.querySelector('.cy');
      cx.classList.remove('reg'); cy.classList.remove('reg'); cx.title = ''; cy.title = '';
      if (!ok) {
        if (typed && cur && !sameBeacon) bad.push(i + 1);
        if (reg) {   // not worked out yet: the register's X / Y, and the next line carries on from it
          cur = { x: reg[0], y: reg[1] };
          cx.textContent = fmt(cur.x); cy.textContent = fmt(cur.y); cx.classList.add('reg'); cy.classList.add('reg');
          cx.title = cy.title = 'From the Beacon Register';
          pts.push({ b: toPicked, x: cur.x, y: cur.y });
        } else { cx.textContent = '—'; cy.textContent = '—'; cur = null; }
        return;
      }
      showQuadrant(tr);
      const az = brg.az * Math.PI / 180;
      cur = { x: cur.x + dist * Math.sin(az), y: cur.y + dist * Math.cos(az) };
      cx.textContent = fmt(cur.x); cy.textContent = fmt(cur.y);
      if (reg) {   // worked out and in the register: say how close they are
        const off = Math.hypot(cur.x - reg[0], cur.y - reg[1]);
        cx.title = cy.title = `Beacon Register: X ${fmt(reg[0])}, Y ${fmt(reg[1])} (${fmt(off, 2)} m off)`;
        if (off > 1) { cx.classList.add('reg'); cy.classList.add('reg'); }
      }
      if (!last) pts.push({ b: toPicked || toTag, x: cur.x, y: cur.y });
      else closeAt = cur;
    });
    // Closing error: where the last line lands against X1.
    TRAV = null;
    const v = $('trVerdict');
    if (closeAt && pts.length >= 3) {
      const dx = closeAt.x - pts[0].x, dy = closeAt.y - pts[0].y, err = Math.hypot(dx, dy);
      const per = rows.reduce((t, tr) => t + (num(tr.querySelector('.dist').value) || 0), 0);
      TRAV = { err, dx, dy, per, ratio: err > 0 ? per / err : Infinity };
      v.hidden = false;
      v.className = 'spc-verdict ' + (err <= 0.05 || TRAV.ratio >= 5000 ? 'ok' : err <= 0.5 ? 'warn' : 'bad');
      v.innerHTML = `<b>${err < 0.0005 ? 'Closes exactly on X1' : 'Closing error ' + fmt(err, 3) + ' m'}</b><span>Misclosure ΔX ${fmt(dx, 3)} m, ΔY ${fmt(dy, 3)} m · perimeter ${fmt(per, 2)} m${err >= 0.0005 ? ` · precision 1 : ${Math.round(TRAV.ratio).toLocaleString()}` : ''}.</span>`;
      const turn = pts.reduce((t, a, k) => { const b = pts[(k + 1) % pts.length]; return t + a.x * b.y - b.x * a.y; }, 0);
      if (turn > 0) {
        if (v.className.endsWith('ok')) v.className = 'spc-verdict warn';
        v.innerHTML += '<span><b>Anticlockwise:</b> a traverse goes round clockwise. Check the order of the lines (X1 → X2 → … should turn right).</span>';
      }
    } else if (same.length) { v.hidden = false; v.className = 'spc-verdict bad'; v.innerHTML = `<b>Check line${same.length > 1 ? 's' : ''} ${same.join(', ')}</b><span>The To beacon is the same as the From beacon. A line joins two different beacons.</span>`; }
    else if (bad.length) { v.hidden = false; v.className = 'spc-verdict bad'; v.innerHTML = `<b>Check line${bad.length > 1 ? 's' : ''} ${bad.join(', ')}</b><span>Bearing: degrees 0–359, minutes and seconds under 60 (whole circle, from grid north); and a distance in metres.</span>`; }
    else if (rows.length && (sx === null || sy === null)) { v.hidden = false; v.className = 'spc-verdict warn'; v.innerHTML = '<b>Start point needed</b><span>Pick the start beacon, enter X1, or use the reference coordinate.</span>'; }
    else v.hidden = true;
    if (swappedNote) { if (v.hidden) v.innerHTML = ''; v.hidden = false; if (!/bad/.test(v.className)) v.className = 'spc-verdict warn'; v.innerHTML += '<span><b>X and Y switched back:</b> line 1\'s X had the Northing (7 digits). X is the Easting, Y the Northing.</span>'; swappedNote = false; }
    // The worked-out points become the other beacons (hidden X / Y table), so the map, polygon and export follow.
    clearBody(svBody);
    pts.forEach(p => addRow(svBody, p.b, p.x.toFixed(3), p.y.toFixed(3)));
    if (!pts.length) addRow(svBody);
    POLY = !!closeAt && pts.length >= 3;
    refresh();
  }
  function setSvMode() {
    const brg = brgMode();
    $('svBrg').hidden = !brg; $('svXY').hidden = brg; $('svAdd').hidden = brg; $('pasteBox').hidden = brg;
    if (brg) { startFromRef(false); if (!travBody.children.length) resetLines(); else computeTraverse(); }
    else refresh();
  }
  root.querySelectorAll('input[name=spc_svmode]').forEach(r => r.addEventListener('change', setSvMode));
  ['trStartX', 'trStartY', 'trClose'].forEach(id => $(id).addEventListener('change', computeTraverse));
  ['trStartX', 'trStartY'].forEach(id => enterComputes($(id)));
  // Start beacon: picking a register beacon fills X1's X / Y in the chosen system.
  namePicker($('trStartB'), $('trStartOther'), 'Beacon', () => {
    const no = pickedName($('trStartB'), $('trStartOther')), b = no ? REG_BY.get(key(no)) : null, p = $('trStartB')._pick;
    let ll = b ? [b.lon, b.lat] : (p && p.x && p.y && key(p.id) === key(no) ? toLL('26332', +p.x, +p.y) : null);
    if (ll) { const [x, y] = toXY(gridCode(), ll[0], ll[1]); $('trStartX').value = x.toFixed(3); $('trStartY').value = y.toFixed(3); }
    computeTraverse();
  }, () => { const r = travBody.firstElementChild; return r ? pickedName(r.querySelector('.tb'), r.querySelector('.tbo')) : ''; });
  $('trUseRef').onclick = () => {
    if (!startFromRef(true)) { const v = $('trVerdict'); v.hidden = false; v.className = 'spc-verdict warn'; v.innerHTML = '<b>No reference coordinate yet</b><span>Enter the reference coordinate first (section 2).</span>'; return; }
    computeTraverse(); goTo(rowsOf(refBody)[0]);
  };
  $('trAdd').onclick = () => { const tr = addLine(); computeTraverse(); tr.querySelector('.d').focus(); };
  // Traverse lines for the export, with their bearing and distance as attributes.
  function traverseFeatures() {
    if (!brgMode()) return [];
    const pts = SV.filter(o => o.lon !== undefined && !o.bad), out = [], n = pts.length;
    for (let i = 0; i < n - (POLY ? 0 : 1); i++) {
      const a = pts[i], b = pts[(i + 1) % n], az = (Math.atan2(b.x - a.x, b.y - a.y) * 180 / Math.PI + 360) % 360;
      out.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: [[a.lon, a.lat], [b.lon, b.lat]] },
        properties: { name: `${a.beacon || 'X' + (i + 1)} → ${b.beacon || 'X' + ((i + 1) % n + 1)}`, kind: 'traverse line', from: a.beacon, to: b.beacon,
          bearing: wcb(az), azimuth_deg: +az.toFixed(6), distance_m: +Math.hypot(b.x - a.x, b.y - a.y).toFixed(3) } });
    }
    return out;
  }

  /* ---------- map drawing tools: points, lines and parcels, with snapping ---------- */
  let tool = null, draft = [], hover = null;
  const DRAWN = [];
  let seq = { point: 0, line: 0, polygon: 0 }, nextId = 1;
  const NAMES = { point: 'Point', line: 'Line', polygon: 'Parcel' };
  const SNAP_PX = 12;
  function setTool(t) {
    if (!map) return;
    if (draft.length) cancelDraft();
    tool = tool === t ? null : t;
    root.querySelectorAll('#spc-tools [data-tool]').forEach(b => b.classList.toggle('active', b.dataset.tool === tool));
    map.getCanvas().style.cursor = tool ? 'crosshair' : '';
    if (tool) map.doubleClickZoom.disable(); else map.doubleClickZoom.enable();
    info();
  }
  root.querySelectorAll('#spc-tools [data-tool]').forEach(b => b.onclick = () => setTool(b.dataset.tool));
  function snapCandidates() {
    const pts = [], segs = [], bb = map.getBounds().toArray(), inView = c => c[0] >= bb[0][0] && c[0] <= bb[1][0] && c[1] >= bb[0][1] && c[1] <= bb[1][1];
    REG.forEach(r => { const c = [r.lon, r.lat]; if (inView(c)) pts.push(c); });
    [...REFS, ...SV].filter(o => o.lon !== undefined && !o.bad).forEach(o => pts.push([o.lon, o.lat]));
    const lines = [], ring = SV.filter(o => o.lon !== undefined && !o.bad).map(o => [o.lon, o.lat]);
    if (ring.length >= 2) lines.push(POLY ? [...ring, ring[0]] : ring);
    DRAWN.forEach(f => { if (f.type === 'point') pts.push(f.coords[0]); else { f.coords.forEach(c => pts.push(c)); lines.push(f.type === 'polygon' ? [...f.coords, f.coords[0]] : f.coords); } });
    LYR.filter(l => l.on).forEach(l => l.fc.features.forEach(f => lyrParts(f.geometry, c => { if (inView(c)) pts.push(c); }, l2 => lines.push(l2))));
    draft.forEach(c => pts.push(c));
    if (draft.length >= 2) lines.push(draft);
    lines.forEach(l => { for (let i = 1; i < l.length; i++) segs.push([l[i - 1], l[i]]); });
    return { pts, segs };
  }
  function snapAt(point) {
    if (!$('snap').checked) return null;
    const { pts, segs } = snapCandidates();
    let best = null, bestD = SNAP_PX;
    pts.forEach(c => { const p = map.project(c), d = Math.hypot(p.x - point.x, p.y - point.y); if (d < bestD) { bestD = d; best = { c, kind: 'vertex' }; } });
    if (best) return best;
    bestD = SNAP_PX - 2;
    segs.forEach(([a, b]) => {
      const pa = map.project(a), pb = map.project(b), dx = pb.x - pa.x, dy = pb.y - pa.y, len = dx * dx + dy * dy;
      if (!len) return;
      const t = Math.max(0, Math.min(1, ((point.x - pa.x) * dx + (point.y - pa.y) * dy) / len));
      const qx = pa.x + t * dx, qy = pa.y + t * dy, d = Math.hypot(qx - point.x, qy - point.y);
      if (d < bestD) { bestD = d; const ll = map.unproject([qx, qy]); best = { c: [ll.lng, ll.lat], kind: 'edge' }; }
    });
    return best;
  }
  function renderDraft() {
    const feats = [], path = hover ? [...draft, hover.c] : draft;
    if (path.length >= 2) feats.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: tool === 'polygon' && path.length >= 3 ? [...path, path[0]] : path }, properties: {} });
    draft.forEach(c => feats.push({ type: 'Feature', geometry: { type: 'Point', coordinates: c }, properties: {} }));
    map.getSource('draft').setData({ type: 'FeatureCollection', features: feats });
    map.getSource('snap').setData(hover && hover.kind ? { type: 'Feature', geometry: { type: 'Point', coordinates: hover.c }, properties: { kind: hover.kind } } : empty);
  }
  function info() {
    const el = $('drawinfo');
    if (!tool) { el.hidden = true; return; }
    const path = hover ? [...draft, hover.c] : draft;
    let t = { point: 'Click to add a point.', line: 'Click to add points; double-click or Enter to finish.', polygon: 'Click the corners; double-click, Enter or click the first corner to close.' }[tool];
    if (tool !== 'point' && path.length >= 2) {
      t = `Length ${fmt(lengthOf(path), 2)} m · last side ${fmt(metres(path[path.length - 2], path[path.length - 1]), 2)} m`;
      if (tool === 'polygon' && path.length >= 3) t += ` · area ${fmt(areaOf(path), 2)} m² (${fmt(areaOf(path) / 10000, 4)} ha)`;
    }
    if (hover?.kind) t += hover.kind === 'vertex' ? ' · snapped to a point' : ' · snapped to a line';
    el.textContent = t; el.hidden = false;
  }
  function finishDraft() {
    if (tool === 'line' && draft.length >= 2) addDrawn('line', draft);
    else if (tool === 'polygon' && draft.length >= 3) addDrawn('polygon', draft);
    draft = []; hover = null; renderDraft(); info();
  }
  function cancelDraft() { draft = []; hover = null; renderDraft(); info(); }
  function addDrawn(type, coords) { seq[type]++; DRAWN.push({ id: nextId++, type, name: `${NAMES[type]} ${seq[type]}`, coords: coords.map(c => [...c]) }); renderDrawn(); }
  function renderDrawn() {
    if (!mapReady) return;
    const code = gridCode();
    map.getSource('drawn').setData({ type: 'FeatureCollection', features: DRAWN.map(f => {
      const c0 = f.type === 'point' ? f.coords[0] : null, props = { name: f.name, label: f.name, kind: 'D' };
      if (c0) { const [x, y] = toXY(code, c0[0], c0[1]); Object.assign(props, { lon: c0[0], lat: c0[1], x, y, grid: GRID_NAME[code] }); }
      return { type: 'Feature', geometry: f.type === 'point' ? { type: 'Point', coordinates: c0 } : f.type === 'line' ? { type: 'LineString', coordinates: f.coords }
        : { type: 'Polygon', coordinates: [[...f.coords, f.coords[0]]] }, properties: props };
    }) });
    const box = $('feats');
    if (!box) return;
    if (!DRAWN.length) { box.className = 'spc-note'; box.style.display = ''; box.textContent = 'Shapes drawn with the map tools appear here.'; return; }
    box.className = ''; box.style.display = 'grid'; box.style.gap = '6px';
    box.innerHTML = DRAWN.map(f => {
      const meta = f.type === 'point' ? (() => { const [x, y] = toXY(code, f.coords[0][0], f.coords[0][1]); return `X ${fmt(x)} · Y ${fmt(y)}`; })()
        : f.type === 'line' ? `${f.coords.length} points · ${fmt(lengthOf(f.coords), 2)} m`
        : `${f.coords.length} corners · ${fmt(areaOf(f.coords), 2)} m² (${fmt(areaOf(f.coords) / 10000, 4)} ha) · perimeter ${fmt(lengthOf([...f.coords, f.coords[0]]), 2)} m`;
      return `<div class="spc-feat"><input type="text" value="${esc(f.name)}" data-name="${f.id}" aria-label="Name">`
        + `<span><button type="button" class="spc-ib" data-zoom="${f.id}" title="Zoom to it"><i class="fas fa-location-crosshairs"></i></button> <button type="button" class="spc-ib" data-del="${f.id}" title="Delete" aria-label="Delete"><i class="fas fa-xmark"></i></button></span>`
        + `<span class="meta">${esc(NAMES[f.type])}: ${meta}</span></div>`;
    }).join('');
    box.querySelectorAll('[data-name]').forEach(i => i.onchange = () => { const f = DRAWN.find(d => d.id == i.dataset.name); f.name = i.value.trim() || f.name; renderDrawn(); });
    box.querySelectorAll('[data-del]').forEach(b => b.onclick = () => { DRAWN.splice(DRAWN.findIndex(d => d.id == b.dataset.del), 1); renderDrawn(); });
    box.querySelectorAll('[data-zoom]').forEach(b => b.onclick = () => { const f = DRAWN.find(d => d.id == b.dataset.zoom), bb = new maplibregl.LngLatBounds(); f.coords.forEach(c => bb.extend(c)); map.fitBounds(bb, { padding: 80, maxZoom: 19.75, duration: 500 }); });
  }
  function wireDrawing() {
    map.on('mousemove', e => { if (!tool) return; hover = snapAt(e.point) || { c: [e.lngLat.lng, e.lngLat.lat], kind: null }; renderDraft(); info(); });
    map.on('click', e => {
      if (!tool) return;
      const s = snapAt(e.point), c = s ? s.c : [e.lngLat.lng, e.lngLat.lat];
      if (tool === 'point') { addDrawn('point', [c]); return; }
      if (tool === 'polygon' && draft.length >= 3) { const p0 = map.project(draft[0]); if (Math.hypot(p0.x - e.point.x, p0.y - e.point.y) <= SNAP_PX) { finishDraft(); return; } }
      const last = draft[draft.length - 1];
      if (!last || last[0] !== c[0] || last[1] !== c[1]) draft.push(c);
      renderDraft(); info();
    });
    map.on('dblclick', e => { if (tool && tool !== 'point') { e.preventDefault(); finishDraft(); } });
  }

  // Full screen, as on the correction board: the same map over the whole window; Esc closes it.
  function setFull(on) {
    $('mapbox').classList.toggle('full', on);
    document.body.classList.toggle('spc-noscroll', on);
    $('mapFull').querySelector('span').textContent = on ? 'Exit full screen' : 'Full screen';
    $('mapFull').querySelector('i').className = on ? 'fas fa-down-left-and-up-right-to-center' : 'fas fa-up-right-and-down-left-from-center';
    setTimeout(() => map && map.resize(), 30);
  }
  $('mapFull').onclick = () => setFull(!$('mapbox').classList.contains('full'));
  $('mapFit').onclick = () => { refresh(); fitAll(); };
  document.addEventListener('keydown', e => {
    const inField = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);
    if (tool && !inField) {
      if (e.key === 'Enter') { e.preventDefault(); finishDraft(); return; }
      if (e.key === 'Escape') { if (draft.length) cancelDraft(); else setTool(tool); return; }
      if (e.key === 'Backspace') { e.preventDefault(); draft.pop(); renderDraft(); info(); return; }
    }
    if (e.key === 'Escape' && $('mapbox').classList.contains('full')) { e.stopPropagation(); setFull(false); }
  }, true);

  /* ---------- existing layers (user, 2026-11-15) ---------- */
  // Parcels, beacons and lines kept elsewhere (a shapefile, GeoJSON or KML, e.g. one exported here) shown over the
  // plot and snapped to. The file is read in this browser; nothing is uploaded or saved. Projected files are
  // converted with this card's own Minna definitions (an ESRI .prj leaves out the Minna datum shift, ~100 m), and
  // a file with no .prj / CRS whose numbers are X / Y is taken to be in the chosen system.
  const LYR = [], LYR_COLOURS = ['#e11d48', '#f59e0b', '#a855f7', '#0ea5e9', '#84cc16', '#f97316'];
  let lyrSeq = 0;
  function lyrParts(g, pt, line) {   // every vertex (pt) and every line / ring (line) of a geometry
    if (!g) return;
    const t = g.type, c = g.coordinates;
    if (t === 'Point') pt(c);
    else if (t === 'MultiPoint') c.forEach(pt);
    else if (t === 'LineString') { line(c); c.forEach(pt); }
    else if (t === 'MultiLineString' || t === 'Polygon') c.forEach(l => { line(l); l.forEach(pt); });
    else if (t === 'MultiPolygon') c.forEach(p => p.forEach(l => { line(l); l.forEach(pt); }));
    else if (t === 'GeometryCollection') g.geometries.forEach(x => lyrParts(x, pt, line));
  }
  function mapCoords(g, fn) {
    const deep = c => typeof c[0] === 'number' ? fn(c) : c.map(deep);
    if (!g) return g;
    if (g.type === 'GeometryCollection') return { type: g.type, geometries: g.geometries.map(x => mapCoords(x, fn)) };
    return { type: g.type, coordinates: deep(g.coordinates) };
  }
  // The coordinate system of a .prj (ESRI WKT) or a GeoJSON crs name: one of the card's systems, 'll' for
  // latitude / longitude, or null when it says nothing we know.
  function systemOf(text) {
    const t = String(text || '').toUpperCase().replace(/[\s_]+/g, ' ');
    if (!t.trim()) return null;
    const m = t.match(/EPSG\D*(\d{4,5})/);
    if (m && (GRID_NAME[m[1]] || m[1] === '4326')) return m[1] === '4326' ? 'll' : m[1];
    if (!/PROJCS|PROJCRS/.test(t)) return /GEOGCS|GEOGCRS|CRS84|WGS 84/.test(t) ? 'll' : null;
    if (/MID BELT/.test(t)) return '26392';
    if (/WEST BELT/.test(t)) return '26391';
    if (/EAST BELT/.test(t)) return '26393';
    if (/UTM ZONE 32N|UTM 32N/.test(t)) return /MINNA/.test(t) ? '26332' : '32632';
    return null;
  }
  function toLatLon(fc, sys, prj) {   // sys: a card system, 'll', or null (look at the numbers)
    let first = null;
    fc.features.forEach(f => lyrParts(f.geometry, c => { first = first || c; }, () => {}));
    if (!first) return fc;
    const looksXY = Math.abs(first[0]) > 180 || Math.abs(first[1]) > 90;
    if (sys === 'll' || (!sys && !looksXY)) return fc;
    let conv = null, used = sys;
    if (sys) conv = c => toLL(sys, c[0], c[1]);
    else if (prj) { try { const p = proj4(prj, 'EPSG:4326'); conv = c => p.forward([c[0], c[1]]); used = 'its .prj'; } catch (e) { conv = null; } }
    if (!conv) { used = gridCode(); conv = c => toLL(used, c[0], c[1]); }
    fc.features = fc.features.map(f => ({ ...f, geometry: mapCoords(f.geometry, conv) }));
    fc._from = GRID_NAME[used] || used;
    return fc;
  }
  // A .zip read with the browser's own inflate (stored or deflated entries).
  async function unzip(buf) {
    const v = new DataView(buf), out = {};
    let e = buf.byteLength - 22;
    while (e >= 0 && v.getUint32(e, true) !== 0x06054b50) e--;
    if (e < 0) throw new Error('not a zip file');
    let p = v.getUint32(e + 16, true);
    const n = v.getUint16(e + 10, true), dec = new TextDecoder();
    for (let i = 0; i < n; i++) {
      const method = v.getUint16(p + 10, true), size = v.getUint32(p + 20, true), nl = v.getUint16(p + 28, true), xl = v.getUint16(p + 30, true), cl = v.getUint16(p + 32, true), off = v.getUint32(p + 42, true);
      const name = dec.decode(new Uint8Array(buf, p + 46, nl));
      p += 46 + nl + xl + cl;
      if (name.endsWith('/')) continue;
      const start = off + 30 + v.getUint16(off + 26, true) + v.getUint16(off + 28, true), raw = new Uint8Array(buf, start, size);
      out[name] = method === 0 ? raw.slice().buffer
        : await new Response(new Blob([raw]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).arrayBuffer();
    }
    return out;
  }
  // Shapefile parts by name (each .shp with its .dbf / .prj / .cpg) -> FeatureCollections.
  function fromShapefiles(files) {
    const base = n => n.replace(/\.[^./\\]+$/, '').toLowerCase(), ext = n => ((n.match(/\.([^.]+)$/) || [])[1] || '').toLowerCase();
    const by = {}, dec = new TextDecoder();
    Object.entries(files).forEach(([n, b]) => { if (/(^|\/)__MACOSX\//.test(n)) return; (by[base(n)] = by[base(n)] || {})[ext(n)] = b; });
    return Object.entries(by).filter(([, p]) => p.shp).map(([n, p]) => {
      const prj = p.prj ? dec.decode(p.prj) : '', geoms = shp.parseShp(p.shp);
      const fc = p.dbf ? shp.combine([geoms, shp.parseDbf(p.dbf, p.cpg ? dec.decode(p.cpg) : undefined)])
        : { type: 'FeatureCollection', features: geoms.map(g => ({ type: 'Feature', geometry: g, properties: {} })) };
      fc.fileName = n.split('/').pop() + '.shp';
      return toLatLon(fc, systemOf(prj), prj);
    });
  }
  function fromKml(text) {
    const doc = new DOMParser().parseFromString(text, 'application/xml');
    const pts = s => s.trim().split(/\s+/).map(t => t.split(',').slice(0, 2).map(Number)).filter(c => c.length === 2 && !c.some(isNaN));
    const feats = [];
    doc.querySelectorAll('Placemark').forEach(pm => {
      const props = { name: (pm.querySelector('name')?.textContent || '').trim() };
      pm.querySelectorAll('ExtendedData Data').forEach(d => { props[d.getAttribute('name')] = d.querySelector('value')?.textContent ?? ''; });
      pm.querySelectorAll('ExtendedData SimpleData').forEach(d => { props[d.getAttribute('name')] = d.textContent; });
      pm.querySelectorAll('Point').forEach(g => { const c = pts(g.querySelector('coordinates')?.textContent || ''); if (c.length) feats.push({ type: 'Feature', geometry: { type: 'Point', coordinates: c[0] }, properties: props }); });
      pm.querySelectorAll('LineString').forEach(g => { const c = pts(g.querySelector('coordinates')?.textContent || ''); if (c.length > 1) feats.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: c }, properties: props }); });
      pm.querySelectorAll('Polygon').forEach(g => { const rings = [...g.querySelectorAll('LinearRing coordinates')].map(r => pts(r.textContent)).filter(r => r.length > 2); if (rings.length) feats.push({ type: 'Feature', geometry: { type: 'Polygon', coordinates: rings }, properties: props }); });
    });
    return { type: 'FeatureCollection', features: feats };
  }
  function fromGeoJson(text) {
    const j = JSON.parse(text);
    const fc = j.type === 'FeatureCollection' ? j : j.type === 'Feature' ? { type: 'FeatureCollection', features: [j] } : { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: j, properties: {} }] };
    return toLatLon(fc, systemOf(j.crs?.properties?.name), '');
  }
  async function readLayerFiles(list) {
    const parts = {}, out = [], dec = new TextDecoder();
    for (const f of list) {
      const n = f.name, lo = n.toLowerCase();
      if (lo.endsWith('.zip')) {
        const z = await unzip(await f.arrayBuffer()), inner = Object.keys(z);
        if (inner.some(k => /\.gdb\//i.test(k))) throw new Error(`${n} is a Geodatabase. Open it in ArcGIS or QGIS and export the layer as a shapefile or GeoJSON first.`);
        fromShapefiles(z).forEach(fc => { fc.fileName = `${n} › ${fc.fileName}`; out.push(fc); });
        inner.filter(k => /\.(geo)?json$/i.test(k)).forEach(k => { const fc = fromGeoJson(dec.decode(z[k])); fc.fileName = `${n} › ${k.split('/').pop()}`; out.push(fc); });
        inner.filter(k => /\.kml$/i.test(k)).forEach(k => { const fc = fromKml(dec.decode(z[k])); fc.fileName = `${n} › ${k.split('/').pop()}`; out.push(fc); });
      } else if (/\.(shp|dbf|prj|cpg)$/.test(lo)) parts[n] = await f.arrayBuffer();
      else if (/\.(geo)?json$/.test(lo)) { const fc = fromGeoJson(await f.text()); fc.fileName = n; out.push(fc); }
      else if (lo.endsWith('.kml')) { const fc = fromKml(await f.text()); fc.fileName = n; out.push(fc); }
      else throw new Error(`${n}: use a shapefile (.zip, or .shp with its .dbf and .prj), GeoJSON or KML.`);
    }
    if (Object.keys(parts).length) {
      if (!Object.keys(parts).some(n => /\.shp$/i.test(n))) throw new Error('Pick the .shp too (with its .dbf and .prj).');
      fromShapefiles(parts).forEach(fc => out.push(fc));
    }
    return out;
  }
  const lyrLabel = p => { const k = Object.keys(p || {}).find(k => /^(name|beacon|beacon_no|plot|plot_no|parcel|label|id)$/i.test(k)); return k ? String(p[k] ?? '') : ''; };
  function addLayer(fc) {
    const id = 'lyr' + (++lyrSeq), colour = LYR_COLOURS[(lyrSeq - 1) % LYR_COLOURS.length];
    fc.features = fc.features.filter(f => f && f.geometry).map(f => ({ ...f, properties: { ...(f.properties || {}), _label: lyrLabel(f.properties) } }));
    const l = { id, name: fc.fileName || 'Layer', fc, colour, on: true };
    LYR.push(l);
    map.addSource(id, { type: 'geojson', data: fc });
    const before = map.getLayer('links') ? 'links' : undefined, poly = ['in', ['geometry-type'], ['literal', ['Polygon', 'MultiPolygon']]];
    map.addLayer({ id: id + '-fill', type: 'fill', source: id, filter: poly, paint: { 'fill-color': colour, 'fill-opacity': 0.12 } }, before);
    map.addLayer({ id: id + '-line', type: 'line', source: id, filter: ['!=', ['geometry-type'], 'Point'], paint: { 'line-color': colour, 'line-width': 2 } }, before);
    map.addLayer({ id: id + '-point', type: 'circle', source: id, filter: ['==', ['geometry-type'], 'Point'], paint: { 'circle-color': colour, 'circle-radius': 4.5, 'circle-stroke-color': '#fff', 'circle-stroke-width': 1.5 } }, before);
    map.addLayer({ id: id + '-label', type: 'symbol', source: id, minzoom: 15, layout: { 'text-field': ['get', '_label'], 'text-font': ['Noto Sans Medium'], 'text-size': 11, 'text-offset': [0, 1.1], 'text-anchor': 'top' },
      paint: { 'text-color': colour, 'text-halo-color': '#fff', 'text-halo-width': 1.6 } }, before);
    renderLayers(); zoomLayer(l);
  }
  function zoomLayer(l) {
    const bb = new maplibregl.LngLatBounds();
    let any = false;
    l.fc.features.forEach(f => lyrParts(f.geometry, c => { bb.extend(c); any = true; }, () => {}));
    if (any) map.fitBounds(bb, { padding: 60, maxZoom: 18, duration: 500 });
  }
  function renderLayers() {
    const box = $('layers');
    box.hidden = !LYR.length;
    box.innerHTML = LYR.map(l => {
      const kinds = {};
      l.fc.features.forEach(f => { const t = f.geometry.type.replace('Multi', ''); kinds[t] = (kinds[t] || 0) + 1; });
      const what = Object.entries(kinds).map(([t, c]) => `${c} ${({ Point: 'point', LineString: 'line', Polygon: 'parcel' }[t] || t.toLowerCase())}${c === 1 ? '' : 's'}`).join(', ');
      const out = l.fc.features.some(f => { let o = false; lyrParts(f.geometry, c => { o = o || !inKano(c); }, () => {}); return o; });
      return `<div class="lyr"><span class="sw" style="border-color:${l.colour};background:${l.colour}22"></span><span class="nm">${esc(l.name)}</span>
        <span class="meta">${what ? esc(what) : 'no shapes'}${l.fc._from ? ' · converted from ' + esc(l.fc._from) : ''}${out ? ' · <b style="color:#b91c1c">some shapes are outside Kano: check the file\'s coordinate system</b>' : ''}</span>
        <label><input type="checkbox" data-lyr-on="${l.id}" ${l.on ? 'checked' : ''}> Show</label>
        <button type="button" data-lyr-zoom="${l.id}"><i class="fas fa-magnifying-glass"></i> Zoom</button>
        <button type="button" data-lyr-del="${l.id}"><i class="fas fa-xmark"></i> Remove</button></div>`;
    }).join('');
    box.querySelectorAll('[data-lyr-on]').forEach(i => i.onchange = () => {
      const l = LYR.find(x => x.id === i.dataset.lyrOn);
      l.on = i.checked;
      ['fill', 'line', 'point', 'label'].forEach(s => map.setLayoutProperty(`${l.id}-${s}`, 'visibility', l.on ? 'visible' : 'none'));
    });
    box.querySelectorAll('[data-lyr-zoom]').forEach(b => b.onclick = () => zoomLayer(LYR.find(x => x.id === b.dataset.lyrZoom)));
    box.querySelectorAll('[data-lyr-del]').forEach(b => b.onclick = () => {
      const i = LYR.findIndex(x => x.id === b.dataset.lyrDel), l = LYR[i];
      ['fill', 'line', 'point', 'label'].forEach(s => map.removeLayer(`${l.id}-${s}`));
      map.removeSource(l.id); LYR.splice(i, 1); renderLayers();
    });
  }
  $('lyrAdd').onclick = () => { if (!mapReady) return; $('lyrFile').value = ''; $('lyrFile').click(); };
  $('lyrFile').onchange = async () => {
    const list = [...$('lyrFile').files];
    if (!list.length) return;
    try {
      const fcs = await readLayerFiles(list);
      if (!fcs.some(fc => fc.features.length)) throw new Error('no shapes were found in it.');
      fcs.filter(fc => fc.features.length).forEach(addLayer);
    } catch (e) { alert('That layer could not be loaded: ' + e.message); }
  };

  /* ---------- export ---------- */
  function exportFeatures() {
    const code = gridCode(), feats = [];
    const xyProps = c => { const [x, y] = toXY(code, c[0], c[1]); return { x: +x.toFixed(3), y: +y.toFixed(3), lat: +c[1].toFixed(8), lon: +c[0].toFixed(8) }; };
    if ($('incplan').checked) {
      const ok = l => l.filter(o => o.lon !== undefined && !o.bad);
      ok(REFS).slice(0, 1).forEach(o => feats.push({ type: 'Feature', geometry: { type: 'Point', coordinates: [o.lon, o.lat] }, properties: { name: o.beacon || 'Reference', kind: 'reference coordinate', ...xyProps([o.lon, o.lat]) } }));
      const sv = ok(SV);
      sv.forEach((o, i) => feats.push({ type: 'Feature', geometry: { type: 'Point', coordinates: [o.lon, o.lat] }, properties: { name: o.beacon || 'Point ' + (i + 1), kind: 'plan beacon', order: i + 1, ...xyProps([o.lon, o.lat]) } }));
      const ring = sv.map(o => [o.lon, o.lat]);
      if (POLY && ring.length >= 3) feats.push({ type: 'Feature', geometry: { type: 'Polygon', coordinates: [[...ring, ring[0]]] }, properties: { name: 'Plan polygon', kind: 'plan polygon', area_m2: +areaOf(ring).toFixed(2), length_m: +lengthOf([...ring, ring[0]]).toFixed(2) } });
      else if (ring.length >= 2) feats.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: ring }, properties: { name: 'Plan traverse', kind: 'plan traverse', length_m: +lengthOf(ring).toFixed(2) } });
      traverseFeatures().forEach(f => feats.push(f));
    }
    DRAWN.forEach(f => {
      if (f.type === 'point') feats.push({ type: 'Feature', geometry: { type: 'Point', coordinates: f.coords[0] }, properties: { name: f.name, kind: 'point', ...xyProps(f.coords[0]) } });
      else if (f.type === 'line') feats.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: f.coords }, properties: { name: f.name, kind: 'line', length_m: +lengthOf(f.coords).toFixed(2) } });
      else feats.push({ type: 'Feature', geometry: { type: 'Polygon', coordinates: [[...f.coords, f.coords[0]]] }, properties: { name: f.name, kind: 'parcel', area_m2: +areaOf(f.coords).toFixed(2), length_m: +lengthOf([...f.coords, f.coords[0]]).toFixed(2) } });
    });
    return feats;
  }
  const coordsOf = g => g.type === 'Point' ? [g.coordinates] : g.type === 'LineString' ? g.coordinates : g.coordinates[0];
  const stamp = () => new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '');
  function save(blob, name) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000); }
  const EXPORTS = {
    geojson(f) { save(new Blob([JSON.stringify({ type: 'FeatureCollection', features: f }, null, 1)], { type: 'application/geo+json' }), `survey-plan-${stamp()}.geojson`); },
    kml(f) {
      const pm = x => {
        const g = x.geometry, c = cs => cs.map(p => `${p[0]},${p[1]},0`).join(' ');
        const geom = g.type === 'Point' ? `<Point><coordinates>${c([g.coordinates])}</coordinates></Point>` : g.type === 'LineString' ? `<LineString><coordinates>${c(g.coordinates)}</coordinates></LineString>`
          : `<Polygon><outerBoundaryIs><LinearRing><coordinates>${c(g.coordinates[0])}</coordinates></LinearRing></outerBoundaryIs></Polygon>`;
        return `<Placemark><name>${esc(x.properties.name)}</name><ExtendedData>${Object.entries(x.properties).map(([k, v]) => `<Data name="${esc(k)}"><value>${esc(v)}</value></Data>`).join('')}</ExtendedData>${geom}</Placemark>`;
      };
      save(new Blob([`<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2"><Document><name>Survey Plan Check</name>${f.map(pm).join('')}</Document></kml>`], { type: 'application/vnd.google-earth.kml+xml' }), `survey-plan-${stamp()}.kml`);
    },
    async shp(f) { save(await shpwrite.zip({ type: 'FeatureCollection', features: f }, { outputType: 'blob', types: { point: 'points', polygon: 'polygons', polyline: 'lines' } }), `survey-plan-${stamp()}-shapefile.zip`); },
    dxf(f) {
      const code = gridCode(), L = [], add = (...p) => L.push(...p);
      add('0', 'SECTION', '2', 'ENTITIES');
      f.forEach(x => {
        const pts = coordsOf(x.geometry).map(c => toXY(code, c[0], c[1])), layer = (x.properties.kind || 'DRAWN').toUpperCase().replace(/[^A-Z0-9]+/g, '_');
        if (x.geometry.type === 'Point') { add('0', 'POINT', '8', layer, '10', pts[0][0].toFixed(3), '20', pts[0][1].toFixed(3), '30', '0'); add('0', 'TEXT', '8', layer, '10', (pts[0][0] + 1).toFixed(3), '20', (pts[0][1] + 1).toFixed(3), '30', '0', '40', '1.5', '1', String(x.properties.name)); }
        else { const closed = x.geometry.type === 'Polygon'; add('0', 'POLYLINE', '8', layer, '66', '1', '70', closed ? '1' : '0'); (closed ? pts.slice(0, -1) : pts).forEach(p => add('0', 'VERTEX', '8', layer, '10', p[0].toFixed(3), '20', p[1].toFixed(3), '30', '0')); add('0', 'SEQEND'); }
      });
      add('0', 'ENDSEC', '0', 'EOF');
      save(new Blob([L.join('\r\n') + '\r\n'], { type: 'application/dxf' }), `survey-plan-${stamp()}.dxf`);
    },
    csv(f) {
      const code = gridCode(), rows = [['feature', 'kind', 'point_no', 'x_easting', 'y_northing', 'latitude', 'longitude', 'coordinate_system']];
      f.forEach(x => { const cs = coordsOf(x.geometry), pts = x.geometry.type === 'Polygon' ? cs.slice(0, -1) : cs;
        pts.forEach((c, i) => { const [ex, ny] = toXY(code, c[0], c[1]); rows.push([x.properties.name, x.properties.kind, x.properties.order || i + 1, ex.toFixed(3), ny.toFixed(3), c[1].toFixed(8), c[0].toFixed(8), GRID_NAME[code]]); }); });
      save(new Blob([rows.map(r => r.map(v => /[",\n]/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : v).join(',')).join('\r\n')], { type: 'text/csv' }), `survey-plan-${stamp()}.csv`);
    },
    sql(f) {
      const code = gridCode(), q = s => `N'${String(s).replace(/'/g, "''")}'`;
      const wkt = x => { const pts = coordsOf(x.geometry).map(c => toXY(code, c[0], c[1]).map(n => n.toFixed(3)).join(' '));
        return x.geometry.type === 'Point' ? `POINT (${pts[0]})` : x.geometry.type === 'LineString' ? `LINESTRING (${pts.join(', ')})` : `POLYGON ((${pts.join(', ')}))`; };
      const out = [`-- Survey Plan Check export, ${new Date().toISOString()}. Coordinate system: ${GRID_NAME[code]}.`,
        `-- CREATE TABLE plan_features (id INT IDENTITY PRIMARY KEY, name NVARCHAR(200), kind NVARCHAR(50), geom GEOMETRY);`,
        ...f.map(x => `INSERT INTO plan_features (name, kind, geom) VALUES (${q(x.properties.name)}, ${q(x.properties.kind)}, geometry::STGeomFromText('${wkt(x)}', ${code}));`)];
      save(new Blob([out.join('\r\n') + '\r\n'], { type: 'application/sql' }), `survey-plan-${stamp()}.sql`);
    },
    // Esri File Geodatabase (user, 2026-11-15): written on the server by GDAL, one layer per shape type, X / Y in
    // the chosen system. The browser sends only these features; nothing is kept on the server.
    async gdb(f) {
      const code = gridCode(), ex = $('export'), xy = c => toXY(code, c[0], c[1]).map(n => +n.toFixed(3));
      const layers = { beacons: [], lines: [], parcels: [] };
      f.forEach(x => {
        const g = x.geometry, props = {};
        Object.entries(x.properties).forEach(([k, v]) => { props[k === 'order' ? 'seq' : k] = v; });
        if (g.type === 'Point') layers.beacons.push({ type: 'Feature', geometry: { type: 'Point', coordinates: xy(g.coordinates) }, properties: props });
        else if (g.type === 'LineString') layers.lines.push({ type: 'Feature', geometry: { type: 'LineString', coordinates: g.coordinates.map(xy) }, properties: props });
        else if (g.type === 'Polygon') layers.parcels.push({ type: 'Feature', geometry: { type: 'Polygon', coordinates: g.coordinates.map(r => r.map(xy)) }, properties: props });
      });
      const r = await fetch(ex.dataset.gdb, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': ex.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ epsg: code, name: 'survey_plan_' + stamp(), layers }) });
      if (!r.ok) { let m = 'the server said ' + r.status; try { m = (await r.json()).message || m; } catch (e) { /* not JSON */ } throw new Error(m); }
      save(await r.blob(), `survey-plan-${stamp()}-geodatabase.zip`);
    },
  };
  document.querySelectorAll('#spc-export [data-export]').forEach(b => b.onclick = async () => {
    const f = exportFeatures(), note = $('export-note');
    if (!f.length) { note.textContent = 'Nothing to export yet: enter the reference coordinate or draw something first.'; return; }
    if (b.dataset.export === 'gdb') note.textContent = 'Writing the Geodatabase…';
    try { await EXPORTS[b.dataset.export](f); note.textContent = `${f.length} feature(s) exported as ${b.textContent.trim()}.`; }
    catch (e) { note.textContent = 'The export failed: ' + e.message; }
  });

  // NTM vs UTM chart (check page): opens full size; Esc, Close or a click beside the chart closes it.
  const guide = $('guide');
  if (guide) {
    const show = on => { guide.hidden = !on; document.body.classList.toggle('spc-noscroll', on); };
    root.querySelectorAll('[data-spc-guide]').forEach(b => b.addEventListener('click', () => show(true)));
    guide.addEventListener('click', e => { if (e.target.closest('[data-spc-guide-close]') && !e.target.closest('img')) show(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !guide.hidden) { e.stopPropagation(); show(false); } }, true);
  }

  /* ---------- start: the system and reference from the upload (Check), or blank ---------- */
  setSystem(PREFILL?.sys || '26332');
  const pr = PREFILL?.ref || {};
  addRow(refBody, pr.beacon_no || '', pr.x || '', pr.y || '', pr.source || '');
  (PREFILL?.others || []).forEach(o => addRow(svBody, o.beacon_no || '', o.x || '', o.y || '', o.source || ''));
  if (!(PREFILL?.others || []).length) addRow(svBody);
  refresh();
  initMap();
});
