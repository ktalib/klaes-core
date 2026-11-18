/*
 | Survey Plan Check card (CR 11112026-2 item 8; user, 2026-11-12). From the approved prototype
 | /maps/survey-plan-check.html. Markup: resources/views/survey_module/survey_plans/_plan_check(.blade.php|_export).
 | 1 coordinate system · 2 one reference coordinate (drives the map) · 3 map · 4 other beacons in order of the traverse,
 | by X / Y or by bearing and distance from X1 (2026-11-13)
 | (joined, polygon) · export. Upload mode submits coord_system and beacons[0][...]; check mode saves nothing.
 | Digital mode (Digital Plotting, Recording 5 2026-11-16): no reference section; every plotted beacon is submitted.
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
  // Kano imagery held on our own server (F:\GIS Data), cut into tiles by resources/gis/make_tiles.py (Kunle, 2026-11-15).
  // Each is its own layer in the Layers panel, above the online basemap and under everything else; off at first.
  const IMAGERY = [
    { key: 'kano05', name: 'Kano imagery 0.5 m', note: 'Central Kano, about 15 x 13 km', dir: '/tiles/kano-imagery' },
    { key: 'ortho7', name: 'Kano Metro orthophoto 7 cm', note: 'Sharada and around, about 6 x 6.5 km', dir: '/tiles/kano-metro-ortho' },
  ];
  const IMG_ON = {}, IMG_INFO = {};
  // Each set's tiles.json gives its area and zooms; a set not made yet is listed as not available.
  const imageryReady = Promise.all(IMAGERY.map(i => fetch(i.dir + '/tiles.json', { cache: 'no-cache' })
    .then(r => (r.ok ? r.json() : null)).catch(() => null).then(j => { IMG_INFO[i.key] = j; })));

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
      imageryReady.then(() => { addImagery(); renderToc(); });
      addGis();
      tocApply();
      map.on('moveend', drawGrid); map.on('resize', drawGrid);
      // The cursor's X / Y in the chosen system, as ArcMap shows them (Recording 5, 2026-11-16).
      const xy = document.createElement('div'); xy.className = 'spc-xy'; xy.setAttribute('aria-live', 'off');
      xy.innerHTML = '<i class="fas fa-crosshairs"></i> <span>Move over the map for X / Y</span>';
      $('map').appendChild(xy);
      map.on('mousemove', e => { const [x, y] = toXY(gridCode(), e.lngLat.lng, e.lngLat.lat);
        xy.querySelector('span').innerHTML = `X <b>${fmt(x, 2)}</b> mE · Y <b>${fmt(y, 2)}</b> mN <em>${esc(GRID_NAME[gridCode()].replace(/ \(EPSG:\d+\)/, ''))}</em> · ${fmt(e.lngLat.lat, 6)}, ${fmt(e.lngLat.lng, 6)}`; });
      map.getCanvas().addEventListener('mouseleave', () => { xy.querySelector('span').textContent = 'Move over the map for X / Y'; });
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
      if (OUR.includes(l.id) || l.id.startsWith('lyr') || l.id.startsWith('img-') || l.id.startsWith('gis-')) return;   // basemap layers only
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
    // Digital Plotting: point 1 (X1) is the reference, labelled so (user, 2026-11-16).
    rowsOf(svBody).forEach((tr, i) => tr.querySelector('.n').innerHTML = (i + 1) + (MODE === 'digital' && i === 0 ? '<div class="spc-reftag">Reference</div>' : ''));
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
    fs.innerHTML = !svOk.length && !ref ? '' : `<b>${MODE === 'digital' ? `${svOk.length} beacon(s)${svOk[0] ? ' from ' + esc(svOk[0].beacon || 'X1') : ''}` : `${ref ? 'Reference ' + esc(ref.beacon || '') : 'No reference'} · ${svOk.length} other beacon(s)`}</b>`
      + (st.hidden ? '' : [...st.querySelectorAll('.spc-stat')].map(s => `<span>${s.querySelector('span').textContent}: ${s.querySelector('b').textContent}</span>`).join(''));

    if (!mapReady) return;
    const pt = (o, kind, label) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [o.lon, o.lat] },
      properties: { beacon: o.beacon || '?', label, x: o.x, y: o.y, lon: o.lon, lat: o.lat, kind, grid: GRID_NAME[code] } });
    // Digital Plotting has no reference section: X1 (the first beacon) is the reference, drawn on its own layer (2026-11-16).
    const svLbl = (o, i) => `${i + 1}${o.beacon ? ' · ' + o.beacon : ''}`;
    const x1 = MODE === 'digital' ? svOk[0] : null;
    map.getSource('ref').setData({ type: 'FeatureCollection', features: x1 ? [pt(x1, 'R', svLbl(x1, 0))] : ref ? [pt(ref, 'R', ref.beacon)] : [] });
    map.getSource('sv').setData({ type: 'FeatureCollection', features: svOk.map((o, i) => pt(o, 'S', svLbl(o, i))).slice(x1 ? 1 : 0) });
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
    // (?:…): a capturing group here put undefined into the split and broke every pasted row (fixed 2026-11-16).
    lines.forEach(l => { const p = l.split(/[\t,;]+|\s{2,}|\s(?=[\d.-]+(?:\s|$))/).map(s => s.trim()).filter(Boolean); addRow(svBody, String(p[0] || '').toUpperCase(), (p[1] || '').replace(/,/g, ''), (p[2] || '').replace(/,/g, '')); });
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
      tr.querySelector('.ln').innerHTML = `<b>${i + 1}</b>${MODE === 'digital' && i === 0 ? '<div class="spc-reftag">Reference</div>' : ''}${last ? '<div class="spc-note">closes</div>' : ''}`;
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
    // Kano GIS beacons and control pillars on the screen (user: show and snap).
    const gisPts = ['gis-beacons', 'gis-ctl'].filter(id => map.getLayer(id) && map.getLayoutProperty(id, 'visibility') === 'visible');
    if (gisPts.length) map.queryRenderedFeatures({ layers: gisPts }).forEach(f => { const c = f.geometry.type === 'Point' ? f.geometry.coordinates : f.geometry.coordinates[0]; if (c) pts.push(c); });
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
  // A .zip of the given files, stored (no compression); used to rewrite the shapefile export's .prj.
  const CRC = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
  const crc32 = u => { let c = 0xFFFFFFFF; for (let i = 0; i < u.length; i++) c = CRC[(c ^ u[i]) & 0xFF] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; };
  function zipStore(files) {
    const enc = new TextEncoder(), parts = [], dir = [];
    let off = 0;
    Object.entries(files).forEach(([name, data]) => {
      const u = data instanceof Uint8Array ? data : new Uint8Array(data), nm = enc.encode(name), c = crc32(u);
      const h = new DataView(new ArrayBuffer(30));
      h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint32(14, c, true); h.setUint32(18, u.length, true); h.setUint32(22, u.length, true); h.setUint16(26, nm.length, true);
      const d = new DataView(new ArrayBuffer(46));
      d.setUint32(0, 0x02014b50, true); d.setUint16(4, 20, true); d.setUint16(6, 20, true); d.setUint32(16, c, true); d.setUint32(20, u.length, true); d.setUint32(24, u.length, true); d.setUint16(28, nm.length, true); d.setUint32(42, off, true);
      parts.push(h, nm, u); dir.push(d, nm); off += 30 + nm.length + u.length;
    });
    const size = dir.reduce((s, x) => s + x.byteLength, 0), e = new DataView(new ArrayBuffer(22)), n = Object.keys(files).length;
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, n, true); e.setUint16(10, n, true); e.setUint32(12, size, true); e.setUint32(16, off, true);
    return new Blob([...parts, ...dir, e]);
  }
  // The .prj ArcGIS writes for each of the card's systems (taken from GDAL, ESRI WKT).
  const PRJ = {
    26332: "PROJCS[\"Minna_UTM_Zone_32N\",GEOGCS[\"GCS_Minna\",DATUM[\"D_Minna\",SPHEROID[\"Clarke_1880_RGS\",6378249.145,293.465]],PRIMEM[\"Greenwich\",0.0],UNIT[\"Degree\",0.0174532925199433]],PROJECTION[\"Transverse_Mercator\"],PARAMETER[\"False_Easting\",500000.0],PARAMETER[\"False_Northing\",0.0],PARAMETER[\"Central_Meridian\",9.0],PARAMETER[\"Scale_Factor\",0.9996],PARAMETER[\"Latitude_Of_Origin\",0.0],UNIT[\"Meter\",1.0]]",
    26391: "PROJCS[\"Nigeria_West_Belt\",GEOGCS[\"GCS_Minna\",DATUM[\"D_Minna\",SPHEROID[\"Clarke_1880_RGS\",6378249.145,293.465]],PRIMEM[\"Greenwich\",0.0],UNIT[\"Degree\",0.0174532925199433]],PROJECTION[\"Transverse_Mercator\"],PARAMETER[\"False_Easting\",230738.26],PARAMETER[\"False_Northing\",0.0],PARAMETER[\"Central_Meridian\",4.5],PARAMETER[\"Scale_Factor\",0.99975],PARAMETER[\"Latitude_Of_Origin\",4.0],UNIT[\"Meter\",1.0]]",
    26392: "PROJCS[\"Nigeria_Mid_Belt\",GEOGCS[\"GCS_Minna\",DATUM[\"D_Minna\",SPHEROID[\"Clarke_1880_RGS\",6378249.145,293.465]],PRIMEM[\"Greenwich\",0.0],UNIT[\"Degree\",0.0174532925199433]],PROJECTION[\"Transverse_Mercator\"],PARAMETER[\"False_Easting\",670553.98],PARAMETER[\"False_Northing\",0.0],PARAMETER[\"Central_Meridian\",8.5],PARAMETER[\"Scale_Factor\",0.99975],PARAMETER[\"Latitude_Of_Origin\",4.0],UNIT[\"Meter\",1.0]]",
    26393: "PROJCS[\"Nigeria_East_Belt\",GEOGCS[\"GCS_Minna\",DATUM[\"D_Minna\",SPHEROID[\"Clarke_1880_RGS\",6378249.145,293.465]],PRIMEM[\"Greenwich\",0.0],UNIT[\"Degree\",0.0174532925199433]],PROJECTION[\"Transverse_Mercator\"],PARAMETER[\"False_Easting\",1110369.7],PARAMETER[\"False_Northing\",0.0],PARAMETER[\"Central_Meridian\",12.5],PARAMETER[\"Scale_Factor\",0.99975],PARAMETER[\"Latitude_Of_Origin\",4.0],UNIT[\"Meter\",1.0]]",
    32632: "PROJCS[\"WGS_1984_UTM_Zone_32N\",GEOGCS[\"GCS_WGS_1984\",DATUM[\"D_WGS_1984\",SPHEROID[\"WGS_1984\",6378137.0,298.257223563]],PRIMEM[\"Greenwich\",0.0],UNIT[\"Degree\",0.0174532925199433]],PROJECTION[\"Transverse_Mercator\"],PARAMETER[\"False_Easting\",500000.0],PARAMETER[\"False_Northing\",0.0],PARAMETER[\"Central_Meridian\",9.0],PARAMETER[\"Scale_Factor\",0.9996],PARAMETER[\"Latitude_Of_Origin\",0.0],UNIT[\"Meter\",1.0]]",
  };

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
        fromShapefiles(z).forEach(fc => { fc.fileName = `${n} › ${fc.fileName}`; out.push(fc); });
        inner.filter(k => /\.(geo)?json$/i.test(k)).forEach(k => { const fc = fromGeoJson(dec.decode(z[k])); fc.fileName = `${n} › ${k.split('/').pop()}`; out.push(fc); });
        inner.filter(k => /\.kml$/i.test(k)).forEach(k => { const fc = fromKml(dec.decode(z[k])); fc.fileName = `${n} › ${k.split('/').pop()}`; out.push(fc); });
      } else if (/\.(shp|dbf|prj|cpg)$/.test(lo)) parts[n] = await f.arrayBuffer();
      else if (/\.(geo)?json$/.test(lo)) { const fc = fromGeoJson(await f.text()); fc.fileName = n; out.push(fc); }
      else if (lo.endsWith('.kml')) { const fc = fromKml(await f.text()); fc.fileName = n; out.push(fc); }
      else throw new Error(`${n}: use a shapefile (.zip, or .shp with its .dbf and .prj), GeoJSON, KML or a zipped Geodatabase.`);
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
  function pickLayerFile() { if (!mapReady) return; $('lyrFile').value = ''; $('lyrFile').click(); }
  $('lyrFile').onchange = async () => {
    let list = [...$('lyrFile').files];
    if (!list.length) return;
    // A zipped geodatabase opens its Catalog (read on the server); the rest are read here.
    const gdbs = [];
    for (const f of list) if (await isGdbZip(f).catch(() => false)) gdbs.push(f);
    list = list.filter(f => !gdbs.includes(f));
    gdbs.forEach(f => openCatalog(f, f.name));
    if (!list.length) return;
    try {
      const fcs = await readLayerFiles(list);
      if (!fcs.some(fc => fc.features.length)) throw new Error('no shapes were found in it.');
      fcs.filter(fc => fc.features.length).forEach(addLayer);
    } catch (e) { topMsg('That layer could not be loaded: ' + e.message); }
  };

  /* ---------- Imagery (our own tiles) ---------- */
  function addImagery() {
    const firstLabel = map.getStyle().layers.find(l => l.type === 'symbol' && !OUR.includes(l.id))?.id;
    IMAGERY.forEach(i => {
      const t = IMG_INFO[i.key];
      if (!t || map.getSource('img-' + i.key)) return;
      map.addSource('img-' + i.key, { type: 'raster', tiles: [location.origin + i.dir + '/{z}/{x}/{y}.webp'], tileSize: 256,
        minzoom: t.minzoom, maxzoom: t.maxzoom, bounds: t.bounds, attribution: 'Imagery: Kano State (KLAES)' });
      // Above the online satellite, under the basemap's labels and every layer of ours.
      map.addLayer({ id: 'img-' + i.key, type: 'raster', source: 'img-' + i.key, layout: { visibility: IMG_ON[i.key] ? 'visible' : 'none' } }, firstLabel);
    });
  }
  function showImagery(key, on) {
    IMG_ON[key] = on;
    if (mapReady && map.getLayer('img-' + key)) map.setLayoutProperty('img-' + key, 'visibility', on ? 'visible' : 'none');
  }
  function zoomImagery(key) {
    const b = IMG_INFO[key]?.bounds;
    if (b && mapReady) map.fitBounds([[b[0], b[1]], [b[2], b[3]]], { padding: 20, duration: 600 });
  }
  const imageryRows = () => IMAGERY.map(i => {
    const t = IMG_INFO[i.key];
    if (!t) return `<div class="toc-row toc-off"><input type="checkbox" disabled><span>${esc(i.name)}<small class="toc-meta">Not available yet</small></span></div>`;
    const op = IMG_FX[i.key]?.op ?? 100;
    return `<div class="toc-row" data-imgrow="${i.key}"><label class="toc-lbl"><input type="checkbox" data-img="${i.key}" ${IMG_ON[i.key] ? 'checked' : ''}><span class="sw sw-img"></span><span>${esc(i.name)}<small class="toc-meta">${esc(i.note)}${op < 100 ? ` · shown ${op}%` : ''}</small></span></label>
      <button type="button" class="tl-menu" data-img-menu="${i.key}" aria-label="Layer actions: ${esc(i.name)}" title="Layer actions"><i class="fas fa-ellipsis-vertical"></i></button></div>`;
  }).join('');
  // Action menu for our imagery (user, 2026-11-16): on/off, zoom, transparency, draw order, properties. For this map
  // only; nothing is saved.
  const IMG_FX = {};
  const fxOf = key => (IMG_FX[key] = IMG_FX[key] || { op: 100, br: 0, ct: 0 });
  function applyImgFx(key) {
    const f = fxOf(key), id = 'img-' + key;
    if (!mapReady || !map.getLayer(id)) return;
    map.setPaintProperty(id, 'raster-opacity', f.op / 100);
    // Brightness -50..50 moves the dark and light ends together; contrast -50..50 maps to -0.5..0.5.
    map.setPaintProperty(id, 'raster-brightness-min', Math.max(0, f.br / 100));
    map.setPaintProperty(id, 'raster-brightness-max', Math.min(1, 1 + f.br / 100));
    map.setPaintProperty(id, 'raster-contrast', f.ct / 100);
  }
  function imgToTop(key) {
    if (!mapReady || !map.getLayer('img-' + key)) return;
    const firstLabel = map.getStyle().layers.find(l => l.type === 'symbol' && !OUR.includes(l.id))?.id;
    // Back under the basemap's labels (or under our grid and layers when there are none), above the other imagery.
    const under = IMAGERY.map(x => 'img-' + x.key);
    const next = firstLabel || map.getStyle().layers.find(l => !under.includes(l.id) && l.type !== 'raster' && l.type !== 'background')?.id;
    map.moveLayer('img-' + key, next);
  }
  function imageryMenu(key, btn, ev) {
    const i = IMAGERY.find(x => x.key === key), t = IMG_INFO[key];
    if (!i || !t) return;
    const f = fxOf(key), other = IMAGERY.filter(x => x.key !== key && IMG_INFO[x.key]);
    showMenu([
      { head: i.name },
      { label: IMG_ON[key] ? 'Turn off' : 'Turn on', icon: IMG_ON[key] ? 'fa-eye-slash' : 'fa-eye', run: () => { showImagery(key, !IMG_ON[key]); if (IMG_ON[key]) zoomIfAway(key); renderToc(); } },
      { label: 'Zoom To Layer', icon: 'fa-magnifying-glass-location', run: () => zoomImagery(key) },
      { label: 'Zoom to full resolution', icon: 'fa-magnifying-glass-plus', hint: 'zoom ' + t.maxzoom, run: () => { const b = t.bounds; if (!IMG_ON[key]) { showImagery(key, true); renderToc(); }
        const c = map.getCenter(), inside = c.lng >= b[0] && c.lng <= b[2] && c.lat >= b[1] && c.lat <= b[3];
        map.easeTo({ center: inside ? c : [(b[0] + b[2]) / 2, (b[1] + b[3]) / 2], zoom: t.maxzoom, duration: 600 }); } },
      '-',
      { label: 'Transparency', icon: 'fa-circle-half-stroke', hint: (100 - f.op) + '%', run: b => showMenu([{ head: 'See-through: ' + i.name },
        ...[0, 25, 50, 75].map(p => ({ label: p ? p + '% see-through' : 'Solid (0%)', checked: 100 - f.op === p, run: () => { f.op = 100 - p; applyImgFx(key); renderToc(); } }))], b) },
      { label: 'Draw above the other imagery', icon: 'fa-layer-group', disabled: !other.length, run: () => imgToTop(key) },
      { label: 'Properties…', icon: 'fa-sliders', run: () => imgProps(key) },
      '-',
      { label: 'Remove', icon: 'fa-trash-can', danger: true, disabled: true, hint: 'part of the card' },
    ], btn, ev);
  }
  function imgProps(key) {
    const i = IMAGERY.find(x => x.key === key), t = IMG_INFO[key] || {}, f = fxOf(key);
    const b = t.bounds || [];
    const deg = v => (typeof v === 'number' ? v.toFixed(5) : '—');
    propsEl.innerHTML = `<div class="ph"><b><i class="fas fa-image"></i> ${esc(i.name)}</b><button type="button" class="x" aria-label="Close"><i class="fas fa-xmark"></i></button></div>
      <div class="spc-imginfo">
        <div><span>Source</span><b>${esc(t.source || '—')}</b></div>
        <div><span>Covers</span><b>${esc(i.note)}</b></div>
        <div><span>Extent</span><b>${deg(b[0])}, ${deg(b[1])} to ${deg(b[2])}, ${deg(b[3])}</b></div>
        <div><span>Zoom levels</span><b>${t.minzoom ?? '—'} to ${t.maxzoom ?? '—'}</b></div>
        <div><span>Tiles</span><b>${(t.tiles ?? 0).toLocaleString('en')} (${esc(t.format || '')}), made ${esc(t.made || '—')}</b></div>
      </div>
      <label>Shown <input type="range" data-p="op" min="10" max="100" step="5" value="${f.op}"><span class="v">${f.op}%</span></label>
      <label>Brightness <input type="range" data-p="br" min="-50" max="50" step="5" value="${f.br}"><span class="v">${f.br}</span></label>
      <label>Contrast <input type="range" data-p="ct" min="-50" max="50" step="5" value="${f.ct}"><span class="v">${f.ct}</span></label>
      <p class="spc-note">For this map only; not saved.</p>`;
    propsEl.hidden = false;
    $('mapbody').appendChild(propsEl);
    propsEl.querySelectorAll('input[type=range]').forEach(r => r.oninput = () => {
      f[r.dataset.p] = +r.value; r.nextElementSibling.textContent = r.value + (r.dataset.p === 'op' ? '%' : ''); applyImgFx(key);
    });
    propsEl.querySelector('.x').onclick = () => { propsEl.hidden = true; renderToc(); };
  }


  /* ---------- Kano GIS layers (F:\GIS Data), 2026-11-15 ---------- */
  // PMTiles cut by resources/gis/make_vector_tiles.py, served to signed-in users only (GisTilesController).
  // The portal's parcels carry the shape, plot, block, TP plan, layout, district and LGA only (user); KLAES
  // staff also get file numbers and the deeds details. All off until ticked.
  const GIS_URL = root.dataset.gis || '', GIS_FULL = root.dataset.gisFull === '1';
  const GIS = [
    { key: 'parcels', name: 'Parcels', note: '41,180 plots · from zoom 12', minzoom: 12, sw: ['#f97316', 'poly'], ids: ['gis-parcels-fill', 'gis-parcels-line', 'gis-parcels-label'] },
    { key: 'beacons', name: 'Beacons', note: '87,064 named beacons · from zoom 13', minzoom: 13, sw: ['#e11d48', 'pt'], ids: ['gis-beacons', 'gis-beacons-label'] },
    { key: 'roads', name: 'Roads and streets', note: '11,867 lines · from zoom 10', minzoom: 10, sw: ['#fbbf24', 'line'], ids: ['gis-roads', 'gis-roads-label'] },
    { key: 'lga', name: 'LGA boundaries', note: 'Kano Metro, 18 LGAs', minzoom: 6, sw: ['#a855f7', 'line'], ids: ['gis-lga', 'gis-lga-label'] },
    { key: 'control-beacons', name: 'Control beacons', note: 'Kano Metro, 100 pillars', minzoom: 8, sw: ['#facc15', 'pt'], ids: ['gis-ctl', 'gis-ctl-label'] },
  ];
  const GIS_ON = {};
  const gisSrc = key => 'pmtiles://' + GIS_URL.replace('__LAYER__', key);
  function addGis() {
    if (!GIS_URL || map.getSource('gis-parcels')) return;
    const before = map.getLayer('grid-halo') ? 'grid-halo' : undefined;   // over the imagery, under everything of ours
    const vis = key => ({ visibility: GIS_ON[key] ? 'visible' : 'none' });
    GIS.forEach(g => map.addSource('gis-' + g.key, { type: 'vector', url: gisSrc(g.key), attribution: 'GIS: Kano State (KLAES)' }));
    const parcelLayer = GIS_FULL ? 'kano_parcels' : 'kano_parcels_public';
    map.addLayer({ id: 'gis-lga', type: 'line', source: 'gis-lga', 'source-layer': 'kano_lga', layout: vis('lga'), paint: { 'line-color': '#a855f7', 'line-width': 2, 'line-dasharray': [3, 2] } }, before);
    map.addLayer({ id: 'gis-roads', type: 'line', source: 'gis-roads', 'source-layer': 'kano_roads', layout: vis('roads'), paint: { 'line-color': '#fbbf24', 'line-opacity': 0.85, 'line-width': ['interpolate', ['linear'], ['zoom'], 10, 0.6, 16, 3] } }, before);
    map.addLayer({ id: 'gis-parcels-fill', type: 'fill', source: 'gis-parcels', 'source-layer': parcelLayer, layout: vis('parcels'), paint: { 'fill-color': '#f97316', 'fill-opacity': 0.07 } }, before);
    map.addLayer({ id: 'gis-parcels-line', type: 'line', source: 'gis-parcels', 'source-layer': parcelLayer, layout: vis('parcels'), paint: { 'line-color': '#f97316', 'line-width': ['interpolate', ['linear'], ['zoom'], 12, 0.4, 17, 1.8] } }, before);
    map.addLayer({ id: 'gis-beacons', type: 'circle', source: 'gis-beacons', 'source-layer': 'kano_beacons', minzoom: 13, layout: vis('beacons'), paint: { 'circle-color': '#e11d48', 'circle-radius': ['interpolate', ['linear'], ['zoom'], 13, 1.6, 18, 5], 'circle-stroke-color': '#fff', 'circle-stroke-width': 0.8 } }, before);
    map.addLayer({ id: 'gis-ctl', type: 'circle', source: 'gis-control-beacons', 'source-layer': 'kano_control_beacons', layout: vis('control-beacons'), paint: { 'circle-color': '#facc15', 'circle-radius': 6, 'circle-stroke-color': '#111827', 'circle-stroke-width': 2 } }, before);
    const lbl = (id, src, layer, field, minzoom, color, extra = {}) => map.addLayer({ id, type: 'symbol', source: src, 'source-layer': layer, minzoom,
      layout: { visibility: 'none', 'text-field': ['get', field], 'text-font': ['Noto Sans Regular'], 'text-size': 11, ...extra },
      paint: { 'text-color': '#fff', 'text-halo-color': color, 'text-halo-width': 1.5 } }, before);
    lbl('gis-parcels-label', 'gis-parcels', parcelLayer, 'plotNo', 16, '#9a3412');
    lbl('gis-beacons-label', 'gis-beacons', 'kano_beacons', 'beaconName', 17, '#9f1239', { 'text-offset': [0, 1], 'text-anchor': 'top' });
    lbl('gis-roads-label', 'gis-roads', 'kano_roads', 'NAME', 15, '#92400e', { 'symbol-placement': 'line' });
    lbl('gis-lga-label', 'gis-lga', 'kano_lga', 'name', 9, '#6b21a8');
    lbl('gis-ctl-label', 'gis-control-beacons', 'kano_control_beacons', 'PILLAR_NO', 13, '#713f12', { 'text-offset': [0, 1.2], 'text-anchor': 'top' });
    GIS.forEach(g => { if (GIS_ON[g.key]) showGis(g.key, true); });

    // Click a parcel, a beacon or a control pillar (no drawing tool on): what the layer holds.
    const ours = ['register', 'ref', 'sv', 'drawn-point'];
    ['gis-parcels-fill', 'gis-beacons', 'gis-ctl'].forEach(id => {
      map.on('click', id, e => {
        if (tool || map.queryRenderedFeatures(e.point, { layers: ours.filter(l => map.getLayer(l)) }).length) return;
        if (id === 'gis-parcels-fill' && map.queryRenderedFeatures(e.point, { layers: ['gis-beacons', 'gis-ctl'].filter(l => map.getLayer(l) && map.getLayoutProperty(l, 'visibility') === 'visible') }).length) return;
        gisPopup(id, e.features[0], e.lngLat);
      });
      map.on('mouseenter', id, () => { if (!tool) map.getCanvas().style.cursor = 'pointer'; });
      map.on('mouseleave', id, () => { if (!tool) map.getCanvas().style.cursor = ''; });
    });
  }
  // The pop-up card for a Kano GIS feature (user, 2026-11-15: icons and colours). Sections in the property
  // order the app uses everywhere: Plot No., District, LGA, then the layout and plans.
  const GIS_CARD = {
    'gis-parcels-fill': {
      title: 'Parcel', icon: 'fa-draw-polygon', tone: 'parcel',
      chips: [['mlsfNo', 'MLS'], ['kangisFileNo', 'KANGIS']],
      sections: [
        ['Property', 'fa-map-location-dot', [['plotNo', 'Plot No.'], ['blockNo', 'Block'], ['districtName', 'District'], ['lgaName', 'LGA'], ['layoutName', 'Layout']]],
        ['Plans', 'fa-file-lines', [['tpPlanNo', 'TP Plan'], ['approvedPlanNo', 'Approved Plan'], ['metricSheetNo', 'Metric sheet'], ['imperialSheetNo', 'Imperial sheet']]],
        ['Survey', 'fa-user-pen', [['plotYearCreated', 'Year'], ['surveyedBy', 'Surveyed by'], ['drawnBy', 'Drawn by'], ['checkedBy', 'Checked by'], ['passedBy', 'Passed by']]],
        ['Old title / Deeds', 'fa-book', [['oldTitleSerialNo', 'Serial'], ['oldTitlePageNo', 'Page'], ['oldTitleVolumeNo', 'Volume'], ['deedsDate', 'Deeds date']]],
      ],
    },
    'gis-beacons': {
      title: 'Beacon', icon: 'fa-location-dot', tone: 'beacon', chips: [['beaconName', 'Beacon']], sections: [],
    },
    'gis-ctl': {
      title: 'Control beacon', icon: 'fa-crosshairs', tone: 'control', chips: [['PILLAR_NO', 'Pillar']],
      sections: [
        ['Location', 'fa-map-location-dot', [['LOCATION', 'Location'], ['LGA', 'LGA']]],
        ['Coordinates', 'fa-ruler-combined', [['E', 'Easting'], ['N', 'Northing'], ['H', 'Height']]],
      ],
    },
  };
  function gisPopup(id, f, at) {
    const p = f.properties || {}, card = GIS_CARD[id];
    const val = k => { const v = p[k]; if (v === undefined || v === null) return ''; const t = String(v).replace(/T00:00:00$/, '').trim(); return t === 'NaT' || t === 'nan' ? '' : t; };
    const chips = card.chips.filter(([k]) => val(k)).map(([k, label]) =>
      `<span class="spc-gp-chip"><small>${esc(label)}</small><b>${esc(val(k))}</b><button type="button" class="spc-gp-copy" data-copy="${esc(val(k))}" title="Copy" aria-label="Copy ${esc(val(k))}"><i class="fas fa-copy"></i></button></span>`).join('');
    const sections = card.sections.map(([name, icon, fields]) => {
      const rows = fields.filter(([k]) => val(k)).map(([k, label]) => `<div class="spc-gp-row"><span>${esc(label)}</span><b>${esc(val(k))}</b></div>`).join('');
      return rows ? `<div class="spc-gp-sec"><div class="spc-gp-sname"><i class="fas ${icon}"></i>${esc(name)}</div>${rows}</div>` : '';
    }).join('');
    const [lon, lat] = [at.lng, at.lat];
    const html = `<div class="spc-gp spc-gp-${card.tone}">
        <div class="spc-gp-head"><span class="spc-gp-ic"><i class="fas ${card.icon}"></i></span><div><b>${card.title}</b><small>Kano GIS</small></div></div>
        ${chips ? `<div class="spc-gp-chips">${chips}</div>` : ''}
        <div class="spc-gp-body">${sections || (chips ? '' : '<p class="spc-gp-none">No details on this feature.</p>')}</div>
        <div class="spc-gp-foot"><i class="fas fa-crosshairs"></i> ${fmt(lat, 6)}, ${fmt(lon, 6)}</div>
      </div>`;
    const pop = new maplibregl.Popup({ maxWidth: '320px', className: 'spc-gp-wrap' }).setLngLat(at).setHTML(html).addTo(map);
    pop.getElement().querySelectorAll('[data-copy]').forEach(b => b.onclick = () => {
      try { navigator.clipboard.writeText(b.dataset.copy); b.innerHTML = '<i class="fas fa-check"></i>'; setTimeout(() => { b.innerHTML = '<i class="fas fa-copy"></i>'; }, 1200); } catch (e) { /* clipboard blocked */ }
    });
  }
  function showGis(key, on) {
    GIS_ON[key] = on;
    const g = GIS.find(x => x.key === key);
    if (!g || !mapReady) return;
    g.ids.forEach(id => { if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', on ? 'visible' : 'none'); });
    if (on && map.getZoom() < g.minzoom) map.easeTo({ zoom: g.minzoom + 0.5, duration: 500 });
  }

  /* ---------- Table of Contents (CR 141102026 item 4; Recording 5, 2026-11-16: ArcMap style) ---------- */
  // Layers in groups that turn on / off and fold. Drag a layer (or a group) to change the order: the map draws the
  // list from the bottom up, so the top of the list is on top. New layers come in on, points above lines above
  // polygons. Each layer has a menu (the ⋮ button or a right-click): Zoom to Layer, Open Attribute Table, Label
  // Features, Visible Scale Range, Properties, Export, Move to group, Remove. Scanned plans, imagery and the basemap
  // stay at the bottom, as in ArcMap.
  const KIND_RANK = { pt: 0, line: 1, poly: 2 };
  const ENTRY = {};
  const ent = o => (ENTRY[o.key] = Object.assign({ on: true, labelsOn: true, minScale: 0, labels: [] }, o));
  const ptFeat = (o, extra = {}) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [o.lon, o.lat] },
    properties: { beacon: o.beacon || '', x: +(+o.x).toFixed(3), y: +(+o.y).toFixed(3), lat: +o.lat.toFixed(7), lon: +o.lon.toFixed(7), ...extra } });
  const placedOf = list => list.filter(o => o.lon !== undefined && !o.bad);
  ent({ key: 'ref', name: MODE === 'digital' ? 'X1 (reference)' : 'Reference coordinate', kind: 'pt', sw: ['#ea580c', 'pt'], ids: ['ref'], labels: ['ref-label'], group: 'plan',
    feats: () => (MODE === 'digital' ? placedOf(SV) : placedOf(REFS)).slice(0, 1).map(o => ptFeat(o)) });
  ent({ key: 'sv', name: MODE === 'digital' ? 'Beacons' : 'Other beacons', kind: 'pt', sw: ['#0891b2', 'pt'], ids: ['sv'], labels: ['sv-label'], group: 'plan',
    feats: () => placedOf(SV).map((o, i) => ptFeat(o, { point: i + 1 })).slice(MODE === 'digital' ? 1 : 0) });
  ent({ key: 'links', name: 'Distance to Register beacons', kind: 'line', sw: ['#dc2626', 'line'], ids: ['links'], group: 'plan',
    feats: () => (srcData('links')?.features || []) });
  ent({ key: 'bnd', name: 'Boundary and polygon', kind: 'poly', sw: ['#22d3ee', 'poly'], ids: ['poly-fill', 'sv-line'], labels: ['sides'], group: 'plan',
    feats: () => { const s = srcData(POLY ? 'poly' : 'svline'); return s && s.geometry ? [{ ...s, properties: { kind: POLY ? 'parcel' : 'line', points: placedOf(SV).length } }] : []; } });
  ent({ key: 'drawn', name: 'Drawn shapes', kind: 'poly', sw: ['#16a34a', 'poly'], ids: ['drawn-fill', 'drawn-line', 'drawn-point'], labels: ['drawn-label'], group: 'plan',
    feats: () => (srcData('drawn')?.features || []) });
  ent({ key: 'reg', name: 'Beacon Register', kind: 'pt', sw: ['#2563eb', 'pt'], ids: ['register'], labels: ['register-label'], group: 'ref',
    feats: () => REG.map(b => { const [x, y] = toXY(gridCode(), b.lon, b.lat); return ptFeat({ ...b, x, y }, { kind: b.kind === 'C' ? 'control point' : 'beacon', plan_no: b.plan_no || '', district: b.district || '' }); }) });
  if (GIS_URL) GIS.forEach(g => ent({ key: 'gis-' + g.key, gis: g.key, name: g.name, note: g.note, kind: g.sw[1], sw: g.sw, group: 'ref', on: false,
    ids: g.ids.filter(id => !id.endsWith('-label')), labels: g.ids.filter(id => id.endsWith('-label')),
    feats: () => {
      const id = g.ids[0], lyr = map.getLayer(id); if (!lyr) return [];
      const seen = new Set();
      return map.querySourceFeatures('gis-' + g.key, { sourceLayer: lyr.sourceLayer }).filter(f => { const k = JSON.stringify(f.properties); if (seen.has(k)) return false; seen.add(k); return true; })
        .slice(0, 5000).map(f => ({ type: 'Feature', geometry: f.geometry, properties: { ...f.properties } }));
    } }));
  // The coordinate grid is not a layer (user, 2026-11-17): only the map bar's Grid toggle switches it.
  const byKind = keys => keys.sort((a, b) => KIND_RANK[ENTRY[a].kind] - KIND_RANK[ENTRY[b].kind]);
  let groupSeq = 0;
  const GROUPS = [
    { id: 'plan', name: 'This plan', on: true, open: true, keys: byKind(['ref', 'sv', 'links', 'bnd', 'drawn']) },
    { id: 'added', name: 'Added layers', on: true, open: true, keys: [], empty: 'None. Use Add layer: shapefile, GeoJSON, KML or a Geodatabase.' },
    { id: 'ref', name: 'Reference layers', on: true, open: true, keys: byKind(Object.keys(ENTRY).filter(k => ENTRY[k].group === 'ref')) },
  ];
  const groupOf = k => GROUPS.find(g => g.keys.includes(k));
  const effOn = e => e.on && (groupOf(e.key)?.on !== false);
  const fold = { scan: true, base: true };   // the fixed groups at the bottom

  // Loaded files are layers of their own in "Added layers" (on top of it, switched on).
  function syncAdded() {
    LYR.forEach(l => {
      if (ENTRY[l.id]) { ENTRY[l.id].on = l.on; return; }
      const kinds = {};
      l.fc.features.forEach(f => { const t = f.geometry.type.replace('Multi', ''); kinds[t] = (kinds[t] || 0) + 1; });
      const top = Object.entries(kinds).sort((a, b) => b[1] - a[1])[0]?.[0] || 'Polygon';
      const kind = { Point: 'pt', LineString: 'line' }[top] || 'poly';
      ent({ key: l.id, name: l.name, kind, sw: [l.colour, kind], ids: [l.id + '-fill', l.id + '-line', l.id + '-point'], labels: [l.id + '-label'], group: 'added', lyr: l, removable: true,
        meta: () => Object.entries(kinds).map(([t, c]) => `${c} ${({ Point: 'point', LineString: 'line', Polygon: 'parcel' }[t] || t.toLowerCase())}${c === 1 ? '' : 's'}`).join(', ')
          + (l.fc._from ? ' · from ' + l.fc._from : '')
          + (l.fc.features.some(f => { let o = false; lyrParts(f.geometry, c => { o = o || !inKano(c); }, () => {}); return o; }) ? ' · some shapes are outside Kano: check the coordinate system' : ''),
        feats: () => l.fc.features });
      const g = GROUPS.find(x => x.id === 'added') || GROUPS[0];
      g.keys.unshift(l.id); g.on = true;
    });
    Object.keys(ENTRY).filter(k => ENTRY[k].lyr && !LYR.includes(ENTRY[k].lyr)).forEach(k => { const g = groupOf(k); if (g) g.keys.splice(g.keys.indexOf(k), 1); delete ENTRY[k]; });
  }

  // Visibility: a layer shows when it and its group are on; its labels when "Label Features" is ticked too.
  function applyVis(e, user) {
    const on = effOn(e);
    if (e.grid) { if ($('gridOn').checked !== on) { $('gridOn').checked = on; drawGrid(); } return; }
    if (e.lyr) e.lyr.on = on;
    if (e.gis) { GIS_ON[e.gis] = on; const g = GIS.find(x => x.key === e.gis); if (user && on && mapReady && g && map.getZoom() < g.minzoom) map.easeTo({ zoom: g.minzoom + 0.5, duration: 500 }); }
    if (!mapReady) return;
    e.ids.forEach(id => { if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', on ? 'visible' : 'none'); });
    e.labels.forEach(id => { if (map.getLayer(id)) map.setLayoutProperty(id, 'visibility', on && e.labelsOn ? 'visible' : 'none'); });
  }
  // Draw order = the list, bottom up; every label above every shape. The drawing aids (draft, snap) stay on top.
  function applyOrder() {
    if (!mapReady) return;
    const anchor = map.getLayer('draft-line') ? 'draft-line' : undefined, geo = [], lab = [];
    [...GROUPS].reverse().forEach(g => [...g.keys].reverse().forEach(k => { const e = ENTRY[k]; if (!e) return; geo.push(...e.ids); lab.push(...e.labels); }));
    [...geo, ...lab].forEach(id => { if (map.getLayer(id)) map.moveLayer(id, anchor); });
  }

  const tocSwatch = ([c, k]) => `<span class="sw sw-${k}" style="--c:${c}"></span>`;
  const tocRow = e => `<div class="tl${effOn(e) ? '' : ' dim'}" draggable="true" data-k="${esc(e.key)}">
      <span class="grip" title="Drag to change the order" aria-hidden="true"><i class="fas fa-grip-vertical"></i></span>
      <label class="tl-lbl"><input type="checkbox" data-on="${esc(e.key)}" ${e.on ? 'checked' : ''}>${tocSwatch(e.sw)}<span class="nm">${esc(e.name)}${e.note || e.meta ? `<small class="toc-meta">${esc(e.meta ? e.meta() : e.note)}</small>` : ''}${e.minScale ? `<small class="toc-meta">Shown closer than 1 : ${e.minScale.toLocaleString('en')}</small>` : ''}${e.group === 'plan' ? '<small class="toc-meta toc-empty" hidden>Nothing on the map yet</small>' : ''}</span></label>
      <button type="button" class="tl-menu" data-menu="${esc(e.key)}" aria-label="Layer actions: ${esc(e.name)}" title="Layer actions"><i class="fas fa-ellipsis-vertical"></i></button></div>`;
  function renderToc() {
    const box = $('tocTree') || $('toc');
    if (!box) return;
    syncAdded();
    const base = $('baseOn').checked ? BASE : 'none';
    const grp = g => `<div class="tg${g.on ? '' : ' off'}" data-g="${g.id}">
        <div class="tg-h" draggable="true" data-gh="${g.id}">
          <button type="button" class="tg-fold" data-fold="${g.id}" aria-expanded="${g.open}" aria-label="${g.open ? 'Fold' : 'Open'} ${esc(g.name)}"><i class="fas fa-caret-${g.open ? 'down' : 'right'}"></i></button>
          <label class="tg-lbl"><input type="checkbox" data-gon="${g.id}" ${g.on ? 'checked' : ''}><span>${esc(g.name)}</span><span class="tg-c">${g.keys.length}</span></label>
          <button type="button" class="tl-menu" data-gmenu="${g.id}" aria-label="Group actions: ${esc(g.name)}" title="Group actions"><i class="fas fa-ellipsis-vertical"></i></button>
        </div>
        <div class="tg-b" data-gb="${g.id}" ${g.open ? '' : 'hidden'}>${g.keys.map(k => tocRow(ENTRY[k])).join('') || `<p class="toc-none">${esc(g.empty || 'Empty. Drag layers here.')}</p>`}</div></div>`;
    const fixed = (id, name, body) => `<div class="tg tg-fixed" data-fixed="${id}"><div class="tg-h"><button type="button" class="tg-fold" data-ffold="${id}" aria-expanded="${!fold[id] ? 'false' : 'true'}"><i class="fas fa-caret-${fold[id] ? 'down' : 'right'}"></i></button><span class="tg-name">${name}</span></div><div class="tg-b" ${fold[id] ? '' : 'hidden'}>${body}</div></div>`;
    box.innerHTML = `
      <div class="toc-head"><b><i class="fas fa-layer-group"></i> Layers</b>
        <span class="toc-hbtns"><button type="button" class="toc-add" id="spc-tocGroup" title="A new group: drag layers into it"><i class="fas fa-folder-plus"></i></button>
        <button type="button" class="toc-add" id="spc-tocAdd" title="Add existing parcels, beacons or lines: a shapefile (.zip, or .shp + .dbf + .prj), GeoJSON, KML or a Geodatabase (.gdb zipped)"><i class="fas fa-plus"></i> Add layer</button></span></div>
      <p class="toc-tip">Drag <i class="fas fa-grip-vertical"></i> to reorder · right-click a layer for its menu</p>
      ${GROUPS.map(grp).join('')}
      ${fixed('scan', 'Scanned plans (GeoTIFF)', '<div id="spc-tocScan"><p class="toc-none">None on this map yet.</p></div>')}
      ${fixed('base', 'Base layers', imageryRows() + [['satellite', 'Online satellite'], ['street', 'Online street map'], ['none', 'None (blank)']].map(([v, t]) => `<label class="toc-row"><input type="radio" name="spc_tocbase" value="${v}" ${base === v ? 'checked' : ''}><span>${t}</span></label>`).join(''))}`;
    box.querySelectorAll('[data-on]').forEach(i => i.onchange = () => { const e = ENTRY[i.dataset.on]; e.on = i.checked; applyVis(e, true); i.closest('.tl').classList.toggle('dim', !effOn(e)); });
    box.querySelectorAll('[data-gon]').forEach(i => i.onchange = () => { const g = GROUPS.find(x => x.id === i.dataset.gon); g.on = i.checked; g.keys.forEach(k => applyVis(ENTRY[k], true)); renderToc(); });
    box.querySelectorAll('[data-fold]').forEach(b => b.onclick = () => { const g = GROUPS.find(x => x.id === b.dataset.fold); g.open = !g.open; renderToc(); });
    box.querySelectorAll('[data-ffold]').forEach(b => b.onclick = () => { fold[b.dataset.ffold] = !fold[b.dataset.ffold]; renderToc(); });
    box.querySelectorAll('[data-img]').forEach(i => i.onchange = () => { showImagery(i.dataset.img, i.checked); if (i.checked) zoomIfAway(i.dataset.img); });
    box.querySelectorAll('[data-img-menu]').forEach(b => b.onclick = ev => { ev.stopPropagation(); imageryMenu(b.dataset.imgMenu, b); });
    box.querySelectorAll('[data-imgrow]').forEach(r => r.addEventListener('contextmenu', ev => { ev.preventDefault(); imageryMenu(r.dataset.imgrow, null, ev); }));
    box.querySelectorAll('[name=spc_tocbase]').forEach(r => r.onchange = () => {
      if (!map || !mapReady) return;
      if (r.value === 'none') { $('baseOn').checked = false; $('baseOn').dispatchEvent(new Event('change')); }
      else pickBase(r.value);
      renderToc();
    });
    box.querySelectorAll('[data-menu]').forEach(b => b.onclick = ev => { ev.stopPropagation(); layerMenu(ENTRY[b.dataset.menu], b); });
    box.querySelectorAll('[data-gmenu]').forEach(b => b.onclick = ev => { ev.stopPropagation(); groupMenu(GROUPS.find(x => x.id === b.dataset.gmenu), b); });
    box.querySelectorAll('.tl').forEach(r => r.addEventListener('contextmenu', ev => { ev.preventDefault(); layerMenu(ENTRY[r.dataset.k], null, ev); }));
    box.querySelectorAll('.tg-h[data-gh]').forEach(h => h.addEventListener('contextmenu', ev => { ev.preventDefault(); groupMenu(GROUPS.find(x => x.id === h.dataset.gh), null, ev); }));
    $('tocAdd').onclick = e => { e.stopPropagation(); addLayerMenu(e.currentTarget); };
    $('tocGroup').onclick = () => newGroup();
    wireDrag(box);
    if (window.SpcScanToc) window.SpcScanToc();   // survey-plan-scan.js fills its group
    markEmpty();
  }
  // A ticked box on an empty layer changes nothing on the map: say so under the name (2026-11-16).
  function markEmpty() {
    const box = $('toc'); if (!box) return;
    box.querySelectorAll('.tl').forEach(r => { const e = ENTRY[r.dataset.k], n = r.querySelector('.toc-empty'); if (!e || !n) return;
      let has = true; try { has = e.feats().length > 0; } catch (x) { /* not drawn yet */ } n.hidden = has; });
  }
  // Loaded files: kept for the old callers (addLayer); the list is the ToC now.
  function renderLayers() { renderToc(); applyOrder(); }

  /* drag and drop: layers within or between groups, groups among themselves */
  let DRAG = null;
  function wireDrag(box) {
    const clear = () => box.querySelectorAll('.drop-above, .drop-below, .drop-in').forEach(x => x.classList.remove('drop-above', 'drop-below', 'drop-in'));
    box.querySelectorAll('.tl').forEach(r => {
      r.addEventListener('dragstart', ev => { DRAG = { k: r.dataset.k }; r.classList.add('dragging'); ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', r.dataset.k); ev.stopPropagation(); });
      r.addEventListener('dragend', () => { r.classList.remove('dragging'); clear(); DRAG = null; });
    });
    box.querySelectorAll('.tg-h[data-gh]').forEach(h => {
      h.addEventListener('dragstart', ev => { DRAG = { g: h.dataset.gh }; ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', h.dataset.gh); });
      h.addEventListener('dragend', () => { clear(); DRAG = null; });
    });
    box.querySelectorAll('.tg[data-g]').forEach(gEl => {
      gEl.addEventListener('dragover', ev => {
        if (!DRAG) return;
        ev.preventDefault(); clear();
        const row = ev.target.closest('.tl');
        if (DRAG.k && row && row.dataset.k !== DRAG.k) { const r = row.getBoundingClientRect(); row.classList.add(ev.clientY < r.top + r.height / 2 ? 'drop-above' : 'drop-below'); }
        else if (DRAG.k) gEl.querySelector('.tg-b').classList.add('drop-in');
        else if (DRAG.g && DRAG.g !== gEl.dataset.g) { const r = gEl.getBoundingClientRect(); gEl.classList.add(ev.clientY < r.top + r.height / 2 ? 'drop-above' : 'drop-below'); }
      });
      gEl.addEventListener('drop', ev => {
        if (!DRAG) return;
        ev.preventDefault();
        const to = GROUPS.find(x => x.id === gEl.dataset.g);
        if (DRAG.k) {
          const from = groupOf(DRAG.k), row = ev.target.closest('.tl');
          from.keys.splice(from.keys.indexOf(DRAG.k), 1);
          let at = to.keys.length;
          if (row && row.dataset.k !== DRAG.k) { const r = row.getBoundingClientRect(); at = to.keys.indexOf(row.dataset.k) + (ev.clientY < r.top + r.height / 2 ? 0 : 1); }
          to.keys.splice(Math.max(0, at), 0, DRAG.k); to.open = true;
          applyVis(ENTRY[DRAG.k]);
        } else if (DRAG.g && DRAG.g !== to.id) {
          const i = GROUPS.findIndex(x => x.id === DRAG.g), g = GROUPS.splice(i, 1)[0], r = gEl.getBoundingClientRect();
          GROUPS.splice(GROUPS.indexOf(to) + (ev.clientY < r.top + r.height / 2 ? 0 : 1), 0, g);
        }
        DRAG = null; renderToc(); applyOrder();
      });
    });
  }

  /* the menu: one floating list, by the ⋮ button or at the right-click */
  const menuEl = document.createElement('div');
  menuEl.className = 'spc-ctx'; menuEl.hidden = true; menuEl.setAttribute('role', 'menu');
  root.appendChild(menuEl);
  const closeMenu = () => { menuEl.hidden = true; menuEl.innerHTML = ''; };
  document.addEventListener('click', ev => { if (!menuEl.hidden && !menuEl.contains(ev.target)) closeMenu(); });
  document.addEventListener('keydown', ev => { if (ev.key === 'Escape' && !menuEl.hidden) closeMenu(); });
  // The page scrolling away closes it (not the panel's own scroll, nor the scroll that brought the row into view).
  let menuAt = 0;
  window.addEventListener('scroll', () => { if (!menuEl.hidden && Date.now() - menuAt > 400) closeMenu(); });
  function showMenu(items, btn, ev) {
    menuEl.innerHTML = items.map((it, i) => it === '-' ? '<div class="sep"></div>' : it.head ? `<div class="hd">${esc(it.head)}</div>`
      : `<button type="button" role="menuitem" data-i="${i}" ${it.disabled ? 'disabled' : ''} class="${it.danger ? 'danger' : ''}${it.checked ? ' on' : ''}"><i class="fas ${it.icon || (it.checked ? 'fa-check' : '')}"></i>${esc(it.label)}${it.hint ? `<small>${esc(it.hint)}</small>` : ''}</button>`).join('');
    menuEl.hidden = false; menuAt = Date.now();
    const r = btn ? btn.getBoundingClientRect() : { left: ev.clientX, right: ev.clientX, bottom: ev.clientY, top: ev.clientY };
    const w = menuEl.offsetWidth, h = menuEl.offsetHeight;
    menuEl.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, btn ? r.right - w : r.left)) + 'px';
    menuEl.style.top = Math.max(8, Math.min(window.innerHeight - h - 8, r.bottom + 4)) + 'px';
    menuEl.querySelectorAll('[data-i]').forEach(b => b.onclick = e2 => { e2.stopPropagation(); const it = items[+b.dataset.i]; if (!it.keep) closeMenu(); it.run(b); });
    menuEl.querySelector('button')?.focus();
  }
  // Scale ↔ zoom at Kano's latitude, on a 96 dpi screen.
  const zoomFor = scale => Math.log2(40075016.686 * Math.cos(12 * Math.PI / 180) / (512 * scale * 0.00026458));
  const SCALES = [0, 50000, 10000, 5000, 2500, 1000];
  function setScaleRange(e, s) {
    e.minScale = s;
    if (!mapReady) return;
    [...e.ids, ...e.labels].forEach(id => {
      const l = map.getLayer(id); if (!l) return;
      if (l._spcMin === undefined) l._spcMin = l.minzoom || 0;
      map.setLayerZoomRange(id, s ? Math.max(l._spcMin, zoomFor(s)) : l._spcMin, 24);
    });
    renderToc();
  }
  function layerMenu(e, btn, ev) {
    if (!e) return;
    const g = groupOf(e.key);
    const items = [
      { head: e.name },
      { label: 'Zoom To Layer', icon: 'fa-magnifying-glass-location', run: () => zoomEntry(e), disabled: e.grid },
      { label: 'Open Attribute Table', icon: 'fa-table', run: () => openAttr(e), disabled: e.grid },
      '-',
      { label: 'Label Features', checked: e.labelsOn && !!e.labels.length, disabled: !e.labels.length, run: () => { e.labelsOn = !e.labelsOn; applyVis(e); } },
      { label: 'Visible Scale Range', icon: 'fa-ruler', hint: e.minScale ? 'closer than 1 : ' + e.minScale.toLocaleString('en') : 'always', disabled: e.grid,
        run: b => showMenu([{ head: 'Show ' + e.name }, ...SCALES.map(s => ({ label: s ? `Only closer than 1 : ${s.toLocaleString('en')}` : 'At every scale', checked: e.minScale === s, run: () => setScaleRange(e, s) }))], b) },
      { label: 'Properties…', icon: 'fa-sliders', run: () => openProps(e), disabled: e.grid },
      { label: 'Export data (GeoJSON)', icon: 'fa-file-export', run: () => exportEntry(e), disabled: e.grid },
      '-',
      { label: 'Move to group', icon: 'fa-folder-tree', run: b => showMenu([{ head: 'Move ' + e.name + ' to' }, ...GROUPS.filter(x => x !== g).map(x => ({ label: x.name, icon: 'fa-folder', run: () => moveTo(e.key, x) })), '-', { label: 'New group…', icon: 'fa-folder-plus', run: () => newGroup([e.key]) }], b) },
      { label: 'Remove', icon: 'fa-trash-can', danger: true, disabled: !e.removable, hint: e.removable ? '' : 'part of the card', run: () => removeEntry(e) },
    ];
    showMenu(items, btn, ev);
  }
  function groupMenu(g, btn, ev) {
    if (!g) return;
    const fixed = ['plan', 'added', 'ref'].includes(g.id);
    showMenu([
      { head: g.name },
      { label: g.on ? 'Turn the group off' : 'Turn the group on', icon: g.on ? 'fa-eye-slash' : 'fa-eye', run: () => { g.on = !g.on; g.keys.forEach(k => applyVis(ENTRY[k], true)); renderToc(); } },
      { label: 'Zoom To Group', icon: 'fa-magnifying-glass-location', run: () => zoomFeats(g.keys.flatMap(k => ENTRY[k].grid ? [] : ENTRY[k].feats())), disabled: !g.keys.length },
      { label: g.open ? 'Fold' : 'Open', icon: g.open ? 'fa-caret-up' : 'fa-caret-down', run: () => { g.open = !g.open; renderToc(); } },
      '-',
      { label: 'Rename…', icon: 'fa-pen', run: () => renameGroup(g) },
      { label: 'Ungroup', icon: 'fa-folder-minus', danger: true, disabled: fixed, hint: fixed ? 'a group of the card' : 'layers go back', run: () => ungroup(g) },
    ], btn, ev);
  }
  function moveTo(k, g) { const f = groupOf(k); f.keys.splice(f.keys.indexOf(k), 1); g.keys.unshift(k); g.open = true; applyVis(ENTRY[k]); renderToc(); applyOrder(); }
  // A group's name typed in place in the panel (no browser prompt).
  function nameInPlace(g, done) {
    renderToc();
    const lbl = $('toc').querySelector(`[data-gh="${g.id}"] .tg-lbl span`); if (!lbl) return;
    const inp = document.createElement('input');
    inp.type = 'text'; inp.className = 'tg-name-in'; inp.value = g.name; inp.maxLength = 60; inp.setAttribute('aria-label', 'Group name');
    lbl.replaceWith(inp); inp.focus(); inp.select();
    const finish = ok => { if (ok && inp.value.trim()) g.name = inp.value.trim(); done && done(); renderToc(); };
    inp.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); finish(true); } if (ev.key === 'Escape') finish(false); });
    inp.addEventListener('blur', () => finish(true));
    inp.addEventListener('click', ev => ev.preventDefault());
  }
  function newGroup(keys = []) {
    const g = { id: 'grp' + (++groupSeq), name: 'New group ' + groupSeq, on: true, open: true, keys: [], user: true };
    const at = keys.length ? GROUPS.indexOf(groupOf(keys[0])) : 0;
    GROUPS.splice(Math.max(0, at), 0, g);
    keys.forEach(k => { const f = groupOf(k); f.keys.splice(f.keys.indexOf(k), 1); g.keys.push(k); });
    applyOrder(); nameInPlace(g);
  }
  const renameGroup = g => nameInPlace(g);
  function ungroup(g) {
    g.keys.forEach(k => { const home = GROUPS.find(x => x.id === ENTRY[k].group) || GROUPS[0]; home.keys.push(k); });
    GROUPS.splice(GROUPS.indexOf(g), 1);
    GROUPS.forEach(x => { if (!x.user) byKind(x.keys); });
    GROUPS.forEach(x => x.keys.forEach(k => applyVis(ENTRY[k])));
    renderToc(); applyOrder();
  }
  function removeEntry(e) {
    if (!e.removable || !e.lyr) return;
    const i = LYR.indexOf(e.lyr);
    [...e.ids, ...e.labels].forEach(id => { if (map.getLayer(id)) map.removeLayer(id); });
    if (map.getSource(e.lyr.id)) map.removeSource(e.lyr.id);
    if (i >= 0) LYR.splice(i, 1);
    if (ATTR && ATTR.e === e) closeAttr();
    renderToc();
  }

  /* zoom, export */
  function zoomFeats(fs) {
    const bb = new maplibregl.LngLatBounds(); let any = false;
    fs.forEach(f => lyrParts(f.geometry, c => { bb.extend(c); any = true; }, () => {}));
    if (any && mapReady) map.fitBounds(bb, { padding: 60, maxZoom: 18, duration: 500 });
    return any;
  }
  function zoomEntry(e) {
    if (e.lyr) return zoomLayer(e.lyr);
    if (zoomFeats(e.feats())) return;
    if (e.gis) { const g = GIS.find(x => x.key === e.gis); map.easeTo({ zoom: Math.max(map.getZoom(), g.minzoom + 0.5), duration: 500 }); topMsg(e.name + ': zoomed in to where the layer shows.'); return; }
    topMsg(e.name + ': nothing on the map yet.');
  }
  function exportEntry(e) {
    const fs = e.feats();
    if (!fs.length) { topMsg(e.name + ': nothing to export.'); return; }
    save(new Blob([JSON.stringify({ type: 'FeatureCollection', name: e.name, features: fs.map(f => ({ type: 'Feature', geometry: f.geometry, properties: Object.fromEntries(Object.entries(f.properties || {}).filter(([k]) => !k.startsWith('_'))) })) })], { type: 'application/geo+json' }),
      e.name.replace(/[^\w.-]+/g, '_') + '.geojson');
  }

  /* Properties: colour, size / width and how see-through, on the map at once */
  const propsEl = document.createElement('div');
  propsEl.className = 'spc-props'; propsEl.hidden = true; propsEl.setAttribute('role', 'dialog');
  function openProps(e) {
    const kinds = e.ids.map(id => map.getLayer(id)?.type).filter(Boolean);
    const paint = (id, p) => { try { return map.getPaintProperty(id, p); } catch (x) { return undefined; } };
    const first = (t, p) => { const id = e.ids.find(i => map.getLayer(i)?.type === t); return id ? paint(id, p) : undefined; };
    const colour = [first('circle', 'circle-color'), first('line', 'line-color'), first('fill', 'fill-color')].find(c => typeof c === 'string' && /^#/.test(c)) || e.sw[0];
    const size = kinds.includes('circle') ? (typeof first('circle', 'circle-radius') === 'number' ? first('circle', 'circle-radius') : 5) : (typeof first('line', 'line-width') === 'number' ? first('line', 'line-width') : 2);
    e._op = e._op ?? 100;
    propsEl.innerHTML = `<div class="ph"><b><i class="fas fa-sliders"></i> ${esc(e.name)}</b><button type="button" class="x" aria-label="Close"><i class="fas fa-xmark"></i></button></div>
      <label>Colour <input type="color" data-p="c" value="${esc(colour)}"></label>
      <label>${kinds.includes('circle') ? 'Point size' : 'Line width'} <input type="range" data-p="s" min="1" max="${kinds.includes('circle') ? 14 : 8}" step="0.5" value="${size}"><span class="v">${size}</span></label>
      <label>Shown <input type="range" data-p="o" min="10" max="100" step="5" value="${e._op}"><span class="v">${e._op}%</span></label>
      <p class="spc-note">For this map only; not saved.</p>`;
    propsEl.hidden = false;
    $('mapbody').appendChild(propsEl);
    const set = (t, p, v) => e.ids.forEach(id => { if (map.getLayer(id)?.type === t) map.setPaintProperty(id, p, v); });
    const base = {}; e.ids.forEach(id => { const t = map.getLayer(id)?.type; if (t) base[id] = paint(id, t + '-opacity') ?? 1; });
    e._base = e._base || base;
    propsEl.querySelector('[data-p=c]').oninput = ev => { const c = ev.target.value; set('circle', 'circle-color', c); set('line', 'line-color', c); set('fill', 'fill-color', c); e.sw = [c, e.sw[1]]; };
    propsEl.querySelector('[data-p=s]').oninput = ev => { const v = +ev.target.value; ev.target.nextElementSibling.textContent = v; set('circle', 'circle-radius', v); set('line', 'line-width', v); };
    propsEl.querySelector('[data-p=o]').oninput = ev => { const v = +ev.target.value; e._op = v; ev.target.nextElementSibling.textContent = v + '%';
      e.ids.forEach(id => { const t = map.getLayer(id)?.type, b = e._base[id]; if (t && typeof b === 'number') map.setPaintProperty(id, t + '-opacity', b * v / 100); }); };
    propsEl.querySelector('.x').onclick = () => { propsEl.hidden = true; renderToc(); };
  }

  /* Attribute table: docked under the map, as in ArcMap. Click a row to go to it. */
  let ATTR = null;
  const attrEl = document.createElement('div');
  attrEl.className = 'spc-attr'; attrEl.hidden = true;
  function closeAttr() { attrEl.hidden = true; ATTR = null; $('mapbody').classList.remove('withattr'); if (map) { map.getSource('attr-hl')?.setData(empty); setTimeout(() => map.resize(), 0); } }
  function openAttr(e) {
    const fs = e.feats(), MAX = 2000;
    const cols = [...new Set(fs.slice(0, 500).flatMap(f => Object.keys(f.properties || {})))].filter(k => !k.startsWith('_')).slice(0, 30);
    ATTR = { e, fs };
    const cell = v => v === null || v === undefined ? '' : esc(typeof v === 'object' ? JSON.stringify(v) : String(v).replace(/T00:00:00$/, ''));
    attrEl.innerHTML = `<div class="ah"><b><i class="fas fa-table"></i> ${esc(e.name)}</b><span class="spc-note">${fs.length.toLocaleString()} feature${fs.length === 1 ? '' : 's'}${e.gis ? ' in the part of the map loaded so far' : ''}${fs.length > MAX ? ` · first ${MAX.toLocaleString()} shown` : ''}</span>
        <input type="search" class="af" placeholder="Filter…" aria-label="Filter the rows"><button type="button" class="x" aria-label="Close the attribute table"><i class="fas fa-xmark"></i></button></div>
      <div class="ab">${fs.length ? `<table><thead><tr><th>S/N</th>${cols.map(c => `<th>${esc(c)}</th>`).join('')}</tr></thead><tbody>${fs.slice(0, MAX).map((f, i) => `<tr data-r="${i}"><td>${i + 1}</td>${cols.map(c => `<td>${cell(f.properties?.[c])}</td>`).join('')}</tr>`).join('')}</tbody></table>` : '<p class="toc-none" style="padding:10px">No features on this layer yet.</p>'}</div>`;
    attrEl.hidden = false;
    $('mapbody').appendChild(attrEl); $('mapbody').classList.add('withattr');
    setTimeout(() => map && map.resize(), 0);
    attrEl.querySelector('.x').onclick = closeAttr;
    attrEl.querySelector('.af').oninput = ev => { const q = ev.target.value.trim().toLowerCase(); let n = 0; attrEl.querySelectorAll('tbody tr').forEach(tr => { const show = !q || tr.textContent.toLowerCase().includes(q); tr.hidden = !show; if (show) tr.firstElementChild.textContent = ++n; }); };
    attrEl.querySelectorAll('tbody tr').forEach(tr => tr.onclick = () => {
      attrEl.querySelectorAll('tr.sel').forEach(x => x.classList.remove('sel')); tr.classList.add('sel');
      const f = ATTR.fs[+tr.dataset.r]; if (!f || !mapReady) return;
      if (!map.getSource('attr-hl')) {
        map.addSource('attr-hl', { type: 'geojson', data: empty });
        map.addLayer({ id: 'attr-hl-line', type: 'line', source: 'attr-hl', filter: ['!=', ['geometry-type'], 'Point'], paint: { 'line-color': '#00e5ff', 'line-width': 4 } });
        map.addLayer({ id: 'attr-hl-pt', type: 'circle', source: 'attr-hl', filter: ['==', ['geometry-type'], 'Point'], paint: { 'circle-radius': 11, 'circle-color': 'rgba(0,0,0,0)', 'circle-stroke-color': '#00e5ff', 'circle-stroke-width': 3 } });
        OUR.push('attr-hl-line', 'attr-hl-pt');
      }
      map.getSource('attr-hl').setData({ type: 'FeatureCollection', features: [{ type: 'Feature', geometry: f.geometry, properties: {} }] });
      if (f.geometry.type === 'Point') map.flyTo({ center: f.geometry.coordinates, zoom: Math.max(map.getZoom(), 18), duration: 500 }); else zoomFeats([f]);
    });
  }

  // Ticking an imagery layer while looking somewhere it does not cover takes the map to it.
  function zoomIfAway(key) {
    const b = IMG_INFO[key]?.bounds;
    if (!b || !mapReady) return;
    const c = map.getBounds();
    if (c.getEast() < b[0] || c.getWest() > b[2] || c.getNorth() < b[1] || c.getSouth() > b[3]) zoomImagery(key);
  }
  function setToc(on) {
    $('mapbody').classList.toggle('notoc', !on);
    $('tocBtn').classList.toggle('active', on);
    if (map) setTimeout(() => map.resize(), 0);
  }
  $('tocBtn').onclick = () => setToc($('mapbody').classList.contains('notoc'));
  if ($('addLayerBtn')) $('addLayerBtn').onclick = e => { e.stopPropagation(); addLayerMenu(e.currentTarget); };
  // Entry beside the map (Recording 5, 2026-11-16): the system and beacon sections move into a card on the map's right,
  // so the map keeps its room while the beacons are typed; "Back under the map" puts them back.
  function setSide(on) {
    const entry = $('entry'); if (!entry) return;
    (on ? $('side') : $('entryHome')).appendChild(entry);
    $('mapbody').classList.toggle('withside', on);
    $('sideBtn').classList.toggle('active', on);
    if (map) setTimeout(() => { map.resize(); drawGrid(); }, 0);
  }
  if ($('sideBtn')) { $('sideBtn').onclick = () => setSide(!$('mapbody').classList.contains('withside')); $('sideClose').onclick = () => setSide(false); }
  // The bar's own controls keep the panel in step.
  ['btn-street', 'btn-satellite'].forEach(id => $(id).addEventListener('click', () => setTimeout(renderToc, 0)));
  $('baseOn').addEventListener('change', () => setTimeout(renderToc, 0));
  if (window.matchMedia('(max-width: 700px)').matches) setToc(false);
  renderToc();
  // Applied once the map is ready: what was turned off before, and the order.
  // Our GeoJSON sources keep a copy of what they were last given (attribute table, export, zoom).
  let markT = 0;
  const srcData = id => map && map.getSource(id) ? map.getSource(id)._spcData : null;
  function tocApply() {
    ['links', 'poly', 'svline', 'drawn', 'ref', 'sv'].forEach(id => { const s = map.getSource(id); if (!s || s._spcWrapped) return; const set = s.setData.bind(s); s.setData = d => { s._spcData = d; clearTimeout(markT); markT = setTimeout(markEmpty, 50); return set(d); }; s._spcWrapped = true; });
    Object.values(ENTRY).forEach(e => applyVis(e)); applyOrder(); renderToc();
  }

  /* ---------- Geodatabase Catalog (Recording 5, 2026-11-16: "a catalog ... select the ones that you want to load") ---------- */
  // A zipped .gdb (or the .gdb folder itself, zipped here) goes to the server, which lists its feature datasets and
  // layers; the ones ticked come back as GeoJSON in their own coordinates and are added, switched on, in a group
  // named after the geodatabase and dataset. "Only what is in the map view" reads a part of a big layer.
  const GDB_URL = root.dataset.gdbCatalog || '', GDB_READ = root.dataset.gdbRead || '';
  // The names in a zip, from its central directory only (a big zip is never read whole for this).
  async function zipNames(file) {
    const tail = new DataView(await file.slice(Math.max(0, file.size - 65558)).arrayBuffer());
    let e = -1;
    for (let i = tail.byteLength - 22; i >= 0; i--) if (tail.getUint32(i, true) === 0x06054b50) { e = i; break; }
    if (e < 0) return [];
    const size = tail.getUint32(e + 12, true), off = tail.getUint32(e + 16, true);
    const cd = new DataView(await file.slice(off, off + size).arrayBuffer()), dec = new TextDecoder(), out = [];
    for (let p = 0; p + 46 <= cd.byteLength && cd.getUint32(p, true) === 0x02014b50;) {
      const n = cd.getUint16(p + 28, true), x = cd.getUint16(p + 30, true), c = cd.getUint16(p + 32, true);
      out.push(dec.decode(new Uint8Array(cd.buffer, cd.byteOffset + p + 46, n)));
      p += 46 + n + x + c;
    }
    return out;
  }
  const isGdbZip = async f => /\.zip$/i.test(f.name) && (await zipNames(f)).some(n => /\.gdb\//i.test(n.replace(/\\/g, '/')));
  // A .gdb folder picked as a folder: its files zipped (stored, not compressed) for the upload.
  async function gdbFolderZip(files) {
    const own = files.filter(f => /\.gdb\/[^/]+$/i.test((f.webkitRelativePath || f.name).replace(/\\/g, '/')));
    if (!own.length) throw new Error('That folder is not a geodatabase: pick the folder whose name ends in .gdb.');
    const parts = {};
    for (const f of own) { const rel = f.webkitRelativePath.replace(/\\/g, '/'), m = rel.match(/([^/]+\.gdb)\/([^/]+)$/i); parts[m[1] + '/' + m[2]] = new Uint8Array(await f.arrayBuffer()); }
    const name = Object.keys(parts)[0].split('/')[0];
    return { blob: await zipDeflate(parts), name };
  }
  // A zip with each file deflated by the browser (CompressionStream): a geodatabase shrinks several times for the upload.
  async function zipDeflate(files) {
    if (!window.CompressionStream) return zipStore(files);
    const enc = new TextEncoder(), parts = [], dir = [];
    let off = 0;
    for (const [name, u] of Object.entries(files)) {
      const z = new Uint8Array(await new Response(new Blob([u]).stream().pipeThrough(new CompressionStream('deflate-raw'))).arrayBuffer());
      const nm = enc.encode(name), c = crc32(u);
      const h = new DataView(new ArrayBuffer(30));
      h.setUint32(0, 0x04034b50, true); h.setUint16(4, 20, true); h.setUint16(8, 8, true); h.setUint32(14, c, true); h.setUint32(18, z.length, true); h.setUint32(22, u.length, true); h.setUint16(26, nm.length, true);
      const d = new DataView(new ArrayBuffer(46));
      d.setUint32(0, 0x02014b50, true); d.setUint16(4, 20, true); d.setUint16(6, 20, true); d.setUint16(10, 8, true); d.setUint32(16, c, true); d.setUint32(20, z.length, true); d.setUint32(24, u.length, true); d.setUint16(28, nm.length, true); d.setUint32(42, off, true);
      parts.push(h, nm, z); dir.push(d, nm); off += 30 + nm.length + z.length;
    }
    const size = dir.reduce((t, x) => t + x.byteLength, 0), e = new DataView(new ArrayBuffer(22)), n = Object.keys(files).length;
    e.setUint32(0, 0x06054b50, true); e.setUint16(8, n, true); e.setUint16(10, n, true); e.setUint32(12, size, true); e.setUint32(16, off, true);
    return new Blob([...parts, ...dir, e]);
  }
  const catEl = document.createElement('div');
  catEl.className = 'spc-cat'; catEl.hidden = true; catEl.setAttribute('role', 'dialog'); catEl.setAttribute('aria-modal', 'true'); catEl.setAttribute('aria-label', 'Geodatabase Catalog');
  root.appendChild(catEl);
  const closeCat = () => { catEl.hidden = true; catEl.innerHTML = ''; };
  catEl.addEventListener('click', e => { if (e.target === catEl) closeCat(); });
  const geomIcon = g => /point/i.test(g) ? ['pt', '#e11d48'] : /line/i.test(g) ? ['line', '#f59e0b'] : ['poly', '#0ea5e9'];
  function catShell(title, body, foot = '') {
    catEl.innerHTML = `<div class="cat-box"><div class="cat-h"><i class="fas fa-database"></i><div><b>Catalog</b><small>${esc(title)}</small></div><button type="button" class="cat-x" aria-label="Close"><i class="fas fa-xmark"></i></button></div>
      <div class="cat-b">${body}</div>${foot ? `<div class="cat-f">${foot}</div>` : ''}</div>`;
    catEl.hidden = false;
    catEl.querySelector('.cat-x').onclick = closeCat;
  }
  async function postJson(url, body) {
    const r = await fetch(url, { method: 'POST', credentials: 'same-origin', body,
      headers: Object.assign({ 'X-CSRF-TOKEN': root.dataset.csrf || '', Accept: 'application/json' }, body instanceof FormData ? {} : { 'Content-Type': 'application/json' }) });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.message || Object.values(j.errors || {}).flat()[0] || 'The server could not read it (' + r.status + ').');
    return j;
  }
  async function openCatalog(blob, label) {
    if (!GDB_URL) { topMsg('Geodatabases cannot be read here.'); return; }
    catShell(label, `<p class="cat-wait"><i class="fas fa-spinner fa-spin"></i> Reading ${esc(label)} (${(blob.size / 1048576).toFixed(1)} MB)…</p>`);
    let cat;
    try {
      const fd = new FormData(); fd.append('gdb', blob, /\.zip$/i.test(label) ? label : label + '.zip');
      cat = await postJson(GDB_URL, fd);
    } catch (e) { catShell(label, `<p class="cat-err"><i class="fas fa-triangle-exclamation"></i> ${esc(e.message)}</p>`); return; }
    const sets = {};
    cat.layers.forEach(l => (sets[l.dataset || ''] = sets[l.dataset || ''] || []).push(l));
    const keys = Object.keys(sets).sort((a, b) => (a === '') - (b === '') || a.localeCompare(b));
    const row = l => { const [k, c] = geomIcon(l.geometry); return `<label class="cat-l"><input type="checkbox" data-l="${esc(l.name)}"><span class="sw sw-${k}" style="--c:${c}"></span><span class="nm">${esc(l.name)}</span><small>${esc(l.geometry.replace('Multi', '').replace(' Z', ''))} · ${l.count === null ? '?' : l.count.toLocaleString()} · ${esc(l.crs || 'no CRS')}</small></label>`; };
    catShell(cat.name, `<p class="spc-note">${cat.layers.length} layer${cat.layers.length === 1 ? '' : 's'} with shapes. Tick the ones to load.</p>
      <div class="cat-tree">${keys.map(k => `<div class="cat-ds" data-ds="${esc(k)}"><label class="cat-dsh"><input type="checkbox" data-ds-all="${esc(k)}"><i class="fas ${k ? 'fa-folder' : 'fa-folder-open'}"></i><b>${k ? esc(k) : 'Outside a feature dataset'}</b><small>${sets[k].length}</small></label>
        <div class="cat-ls">${sets[k].map(row).join('')}</div></div>`).join('')}</div>`,
      `<label class="spc-check"><input type="checkbox" id="spc-catView"><span>Only what is in the map view</span></label>
       <span class="cat-n" id="spc-catN">0 ticked</span><button type="button" class="spc-btn spc-clear" id="spc-catCancel">Cancel</button><button type="button" class="spc-btn spc-plot" id="spc-catAdd" disabled><i class="fas fa-plus"></i> Add to map</button>`);
    const picked = () => [...catEl.querySelectorAll('[data-l]:checked')].map(i => i.dataset.l);
    const sync = () => {
      const n = picked().length, big = picked().some(nm => (cat.layers.find(l => l.name === nm)?.count || 0) > cat.max_features);
      $('catN').textContent = `${n} ticked` + (big && !$('catView').checked ? ` · big layers: the first ${cat.max_features.toLocaleString()} shapes, or tick "Only what is in the map view"` : '');
      $('catAdd').disabled = !n;
      catEl.querySelectorAll('[data-ds-all]').forEach(a => { const all = [...catEl.querySelectorAll(`.cat-ds[data-ds="${CSS.escape(a.dataset.dsAll)}"] [data-l]`)]; a.checked = all.every(i => i.checked); a.indeterminate = !a.checked && all.some(i => i.checked); });
    };
    catEl.querySelectorAll('[data-l]').forEach(i => i.onchange = sync);
    catEl.querySelectorAll('[data-ds-all]').forEach(a => a.onchange = () => { catEl.querySelectorAll(`.cat-ds[data-ds="${CSS.escape(a.dataset.dsAll)}"] [data-l]`).forEach(i => { i.checked = a.checked; }); sync(); });
    $('catView').onchange = sync;
    $('catCancel').onclick = closeCat;
    $('catAdd').onclick = () => catalogAdd(cat, picked(), $('catView').checked);
  }
  async function catalogAdd(cat, names, inView) {
    const b = $('catAdd'); b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading…';
    let bbox = null;
    if (inView && mapReady) {
      const first = cat.layers.find(l => l.name === names[0]), sys = systemOf(first?.crs), bb = map.getBounds();
      const cs = [[bb.getWest(), bb.getSouth()], [bb.getEast(), bb.getSouth()], [bb.getEast(), bb.getNorth()], [bb.getWest(), bb.getNorth()]];
      const pts = sys === 'll' ? cs : sys ? cs.map(c => toXY(sys, c[0], c[1])) : null;
      if (pts) bbox = [Math.min(...pts.map(p => p[0])), Math.min(...pts.map(p => p[1])), Math.max(...pts.map(p => p[0])), Math.max(...pts.map(p => p[1]))];
    }
    let res;
    try { res = await postJson(GDB_READ, JSON.stringify({ token: cat.token, layers: names, bbox })); }
    catch (e) { b.disabled = false; b.innerHTML = '<i class="fas fa-plus"></i> Add to map'; topMsg(e.message); $('catN').textContent = e.message; return; }
    const added = {}, notes = [];
    res.layers.forEach(l => {
      if (!l.fc.features?.length) { notes.push(`${l.name}: nothing${l.bbox ? ' in the map view' : ''}`); return; }
      const fc = toLatLon(l.fc, systemOf(l.crs), l.crs_wkt);
      fc.fileName = l.name;
      addLayer(fc);
      const ds = cat.layers.find(x => x.name === l.name)?.dataset || '';
      (added[ds] = added[ds] || []).push(LYR[LYR.length - 1].id);
      if (l.truncated) notes.push(`${l.name}: first ${l.fc.features.length.toLocaleString()} of ${l.count.toLocaleString()}`);
    });
    // One group per feature dataset, named after the geodatabase, at the top of the panel.
    Object.entries(added).forEach(([ds, ids]) => {
      const g = { id: 'grp' + (++groupSeq), name: cat.name.replace(/\.gdb$/i, '') + (ds ? ' › ' + ds : ''), on: true, open: true, keys: [], user: true };
      ids.forEach(k => { const f = groupOf(k); if (f) f.keys.splice(f.keys.indexOf(k), 1); g.keys.push(k); });
      byKind(g.keys); GROUPS.unshift(g);
    });
    renderToc(); applyOrder();
    const all = Object.values(added).flat();
    if (all.length && !inView) zoomFeats(all.flatMap(k => ENTRY[k].feats()));
    closeCat();
    topMsg(`Added ${all.length} layer${all.length === 1 ? '' : 's'} from ${cat.name}.` + (notes.length ? ' ' + notes.join('; ') + '.' : ''));
  }
  // Add layer: files (shapefile, GeoJSON, KML, or a zipped .gdb) or a .gdb folder.
  const dirIn = document.createElement('input');
  dirIn.type = 'file'; dirIn.hidden = true; dirIn.webkitdirectory = true; dirIn.multiple = true;
  root.appendChild(dirIn);
  dirIn.onchange = async () => {
    const files = [...dirIn.files]; dirIn.value = '';
    if (!files.length) return;
    try { topMsg('Preparing the geodatabase…'); const z = await gdbFolderZip(files); openCatalog(z.blob, z.name); }
    catch (e) { topMsg(e.message); }
  };
  function addLayerMenu(btn) {
    if (!mapReady) return;
    showMenu([{ head: 'Add layer' },
      { label: 'Files…', icon: 'fa-file-import', hint: 'shapefile (.zip or .shp + .dbf + .prj), GeoJSON, KML or a zipped .gdb', run: () => { $('lyrFile').value = ''; $('lyrFile').click(); } },
      { label: 'Geodatabase folder (.gdb)…', icon: 'fa-database', hint: 'the folder itself; its catalog opens', disabled: !GDB_URL, run: () => dirIn.click() },
      // Scanned plan moved here from the map header (user, 2026-11-16); survey-plan-scan.js still listens on #spc-scanBtn.
      { label: 'Scanned plan / GeoTIFF…', icon: 'fa-file-image', hint: 'a GeoTIFF places itself; a plain scan needs 4 points', disabled: !$('scanBtn'), run: () => $('scanBtn').click() }], btn);
  }

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
  let CAPTURE = null;   // set by SPC_EXPORT: the next save is handed back instead of downloaded
  function save(blob, name) { if (CAPTURE) { CAPTURE(blob, name); return; } const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 2000); }
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
    // X / Y in the chosen system with that system's .prj (Kunle, 2026-11-15: it always said WGS 84). shpwrite
    // writes a WGS 84 .prj only, so its zip is opened and each .prj replaced with the one ArcGIS writes.
    async shp(f) {
      const code = gridCode(), xy = c => toXY(code, c[0], c[1]).map(n => +n.toFixed(3));
      const fc = { type: 'FeatureCollection', features: f.map(x => ({ ...x, geometry: mapCoords(x.geometry, xy) })) };
      const files = await unzip(await shpwrite.zip(fc, { outputType: 'arraybuffer', types: { point: 'points', polygon: 'polygons', polyline: 'lines' } }));
      const enc = new TextEncoder();
      Object.keys(files).forEach(n => { if (/\.prj$/i.test(n)) { files[n] = enc.encode(PRJ[code]); files[n.replace(/\.prj$/i, '.cpg')] = enc.encode('UTF-8'); } });
      save(new Blob([zipStore(files)], { type: 'application/zip' }), `survey-plan-${stamp()}-shapefile.zip`);
    },
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
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': ex.dataset.csrf || document.querySelector('meta[name=csrf-token]')?.content || '', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ epsg: code, name: 'survey_plan_' + stamp(), layers }) });
      if (!r.ok) { let m = 'the server said ' + r.status; try { m = (await r.json()).message || m; } catch (e) { /* not JSON */ } throw new Error(m); }
      save(await r.blob(), `survey-plan-${stamp()}-geodatabase.zip`);
    },
  };
  // Digital Plotting sends the plotting to the checker in a chosen format (Kunle, 2026-11-18): the export as a File.
  window.SPC_EXPORT = async fmt => {
    const inc = $('incplan'), was = inc ? inc.checked : true;
    if (inc) inc.checked = true;      // the plan itself always goes to the checker
    let f;
    try { f = exportFeatures(); } finally { if (inc) inc.checked = was; }
    if (!f.length) throw new Error('Nothing is plotted yet.');
    if (!EXPORTS[fmt]) throw new Error('Unknown format ' + fmt);
    let got = null;
    CAPTURE = (blob, name) => { got = new File([blob], name, { type: blob.type || 'application/octet-stream' }); };
    try { await EXPORTS[fmt](f); } finally { CAPTURE = null; }
    if (!got) throw new Error('The ' + fmt + ' file was not made.');
    return got;
  };
  document.querySelectorAll('#spc-export [data-export]').forEach(b => b.onclick = async () => {
    const f = exportFeatures(), note = $('export-note');
    if (!f.length) { note.textContent = 'Nothing to export yet: enter the reference coordinate or draw something first.'; return; }
    if (b.dataset.export === 'gdb') note.textContent = 'Writing the Geodatabase…';
    try { await EXPORTS[b.dataset.export](f); note.textContent = `${f.length} feature(s) exported as ${b.textContent.trim()}.`; }
    catch (e) { note.textContent = 'The export failed: ' + e.message; }
  });

  // Reference charts (NTM vs UTM, Sheet Index, Data Sheet) in tabs; each opens full size. Esc, Close or a click
  // beside the chart closes it. A [data-spc-guide=key] anywhere on the page (e.g. by the Map Sheet dropdowns) opens one.
  const guide = $('guide');
  if (guide) {
    let GUIDES = {};
    try { GUIDES = JSON.parse($('guideData').textContent); } catch (e) { /* the tabs still switch */ }
    const tab = k => {
      root.querySelectorAll('[data-spc-gtab]').forEach(t => { const on = t.dataset.spcGtab === k; t.classList.toggle('active', on); t.setAttribute('aria-selected', on); });
      root.querySelectorAll('[data-spc-gpane]').forEach(p => { p.hidden = p.dataset.spcGpane !== k; });
    };
    root.querySelectorAll('[data-spc-gtab]').forEach(t => t.addEventListener('click', () => tab(t.dataset.spcGtab)));
    const show = (on, k) => {
      const g = on && GUIDES[k];
      if (g) { $('guideTitle').textContent = g.title; $('guideImg').src = g.src; $('guideImg').alt = g.title; $('guideOpen').href = g.src; guide.setAttribute('aria-label', g.title); tab(k); }
      guide.hidden = !on; document.body.classList.toggle('spc-noscroll', on);
    };
    document.addEventListener('click', e => { const b = e.target.closest('[data-spc-guide]'); if (b) { e.preventDefault(); show(true, b.dataset.spcGuide || 'ntm'); } });
    guide.addEventListener('click', e => { if (e.target.closest('[data-spc-guide-close]') && !e.target.closest('img')) show(false); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !guide.hidden) { e.stopPropagation(); show(false); } }, true);
  }

  /* ---------- top bar: Refresh and Reset (user, 2026-11-15, as on the FC card) ---------- */
  let topMsgTimer = null;
  function topMsg(text) {
    const m = $('topMsg'); if (!m) return;
    m.textContent = text; m.hidden = false;
    clearTimeout(topMsgTimer); topMsgTimer = setTimeout(() => { m.hidden = true; }, 4000);
  }
  // Refresh: the Beacon Register again and a fresh draw; nothing typed is touched.
  if ($('refreshBtn')) $('refreshBtn').onclick = async () => {
    const b = $('refreshBtn'); b.disabled = true; b.querySelector('i').classList.add('fa-spin');
    try {
      if (mapReady) { await loadRegister(); map.resize(); map.triggerRepaint(); }
      refresh(); renderToc();
      topMsg('Refreshed: Beacon Register reloaded; your entries are kept.');
    } finally { b.disabled = false; b.querySelector('i').classList.remove('fa-spin'); }
  };
  // Reset: back to a blank card (default system, empty reference, other beacons, traverse and drawn shapes).
  // The map, its layers, imagery and any scanned plan stay as they are.
  if ($('resetBtn')) $('resetBtn').onclick = () => {
    if (tool) setTool(tool);
    setSystem('26332'); systemChanged();
    clearBody(refBody); addRow(refBody);
    clearBody(svBody); addRow(svBody); POLY = false;
    setPicked($('trStartB'), $('trStartOther'), ''); $('trStartX').value = ''; $('trStartY').value = '';
    resetLines();
    DRAWN.length = 0; renderDrawn();
    refresh();
    topMsg('Reset: the card is blank again.');
  };

  /* ---------- Digital Plotting (Recording 5, 2026-11-16): the plotting is what is sent ---------- */
  // Every plotted beacon in traverse order (X1, the reference, first) as beacons[i][...], and the plotting as JSON
  // (X / Y or traverse, closed, each line's bearing and distance, closing error, area). Nothing plotted, nothing sent.
  const form = MODE === 'digital' ? root.closest('form') : null;
  if (form) form.addEventListener('submit', e => {
    if (brgMode()) computeTraverse(); else refresh();
    const pts = SV.filter(o => o.lon !== undefined && !o.bad), box = $('plotInputs');
    if (pts.length < 3 || SV.some(o => o.bad)) {
      e.preventDefault();
      const v = brgMode() ? $('trVerdict') : $('refVerdict').parentElement.hidden ? null : $('refVerdict');
      topMsg(pts.length < 3 ? 'Plot the plan first: at least three beacons.' : 'Check the beacons marked in red before sending.');
      (v && !v.hidden ? v : $(brgMode() ? 'svBrg' : 'svXY')).scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }
    const src = o => o.tr._reg || o.tr._src === 'captured' ? 'captured' : o.tr._src === 'extant' ? 'extant' : 'typed';
    const hid = (name, value) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = name; i.value = value; box.appendChild(i); };
    box.innerHTML = '';
    pts.forEach((o, i) => {
      hid(`beacons[${i}][beacon_no]`, o.beacon || 'X' + (i + 1));
      hid(`beacons[${i}][x]`, o.x.toFixed(3)); hid(`beacons[${i}][y]`, o.y.toFixed(3)); hid(`beacons[${i}][source]`, src(o));
    });
    const lines = brgMode() ? [...travBody.children].map((tr, i, all) => {
      const b = lineAzimuth(tr), last = $('trClose').checked && i === all.length - 1 && all.length >= 3;
      const to = last ? pickedName($('trStartB'), $('trStartOther')) || 'X1' : pickedName(tr.querySelector('.tb'), tr.querySelector('.tbo')) || 'X' + (i + 2);
      return { from: fromNameOf(tr) || 'X' + (i + 1), to, azimuth: b.az, distance: tr.querySelector('.dist').value.replace(/[,\s]/g, '') };
    }).filter(l => l.azimuth !== undefined && l.distance !== '') : [];
    const area = POLY ? Math.abs(pts.reduce((s, o, i) => { const n = pts[(i + 1) % pts.length]; return s + o.x * n.y - n.x * o.y; }, 0) / 2) : null;
    hid('plot', JSON.stringify({ mode: brgMode() ? 'brg' : 'xy', closed: POLY, lines, closing_error: TRAV ? TRAV.err : null, area }));
  });

  /* ---------- start: the system and reference from the upload (Check), or blank ---------- */
  setSystem(PREFILL?.sys || '26332');
  const pr = PREFILL?.ref || {};
  addRow(refBody, pr.beacon_no || '', pr.x || '', pr.y || '', pr.source || '');
  (PREFILL?.others || []).forEach(o => addRow(svBody, o.beacon_no || '', o.x || '', o.y || '', o.source || ''));
  if (!(PREFILL?.others || []).length) addRow(svBody);
  // A digital plotting that closed opens as its polygon.
  if (PREFILL?.closed && (PREFILL?.others || []).length >= 3) POLY = true;
  refresh();
  initMap();

  /* ---------- scanned plans (GeoTIFF): public/js/survey-plan-scan.js, 2026-11-15 ---------- */
  // The scan script runs inside this card: it gets the map and the helpers it needs from here.
  window.SPC_SCAN_API = {
    root, $, toLL, toXY, gridCode, GRID_NAME, REG_BY, key, fmt, esc, setRef, inKano, OUR,
    map: () => map, ready: () => mapReady, refs: () => REFS, sv: () => SV, base: () => BASE,
  };
  document.dispatchEvent(new CustomEvent('spc:ready'));
});
