/*
 | Field Observations app > Plot (user, 2026-11-15). A phone screen of its own: a map, then
 |   Bearing & distance  a traverse from a start beacon (X1), one card per line, closing error live;
 |   Walk & mark (GPS)   the phone's GPS, a Mark button (optionally averaged), auto-mark every N metres / steps.
 | Same projections, bearing rules (N/S … E/W up to 90°, whole circle above) and maths as the web Survey Plan Check
 | card (public/js/survey-plan-check.js), so both give the same X / Y. Saved to the job by
 | MobileObservationController::storeTraverse. The draft is kept on the phone per job (localStorage).
 */
(function () {
  'use strict';
  const CFG = JSON.parse(document.getElementById('foCfg').textContent);
  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const key = s => String(s || '').trim().toUpperCase().replace(/\s+/g, ' ');
  const fmt = (v, d = 3) => Number(v).toLocaleString('en-GB', { minimumFractionDigits: d, maximumFractionDigits: d });
  const num = v => { const t = String(v ?? '').replace(/[,\s]/g, ''), x = Number(t); return t === '' || !isFinite(x) ? null : x; };

  /* ---------------- coordinate systems (as the web card) ---------------- */
  const MINNA = '+a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs';
  proj4.defs('EPSG:26391', '+proj=tmerc +lat_0=4 +lon_0=4.5 +k=0.99975 +x_0=230738.26 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26392', '+proj=tmerc +lat_0=4 +lon_0=8.5 +k=0.99975 +x_0=670553.98 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26393', '+proj=tmerc +lat_0=4 +lon_0=12.5 +k=0.99975 +x_0=1110369.7 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26332', '+proj=utm +zone=32 ' + MINNA);
  proj4.defs('EPSG:32632', '+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs');
  const SYSTEMS = [
    { code: '26332', name: 'Minna / UTM 32N', sub: 'Kano survey plans and the Beacon Register (default)' },
    { code: '26392', name: 'NTM Mid Belt', sub: 'Minna datum, CM 8.5° E (Kano is in this belt)' },
    { code: '26391', name: 'NTM West Belt', sub: 'Minna datum, CM 4.5° E' },
    { code: '26393', name: 'NTM East Belt', sub: 'Minna datum, CM 12.5° E' },
    { code: '32632', name: 'UTM 32N (WGS 84)', sub: 'GPS and online maps' },
  ];
  const toLL = (code, x, y) => proj4('EPSG:' + code, 'EPSG:4326', [x, y]);
  const toXY = (code, lon, lat) => proj4('EPSG:4326', 'EPSG:' + code, [lon, lat]);

  /* ---------------- state + draft ---------------- */
  const DKEY = 'fo-draft-' + CFG.its;
  const blankLine = () => ({ ns: 'N', d: '', m: '', s: '', ew: 'E', dist: '', to: '' });
  let S = { sys: '26332', mode: 'trav', close: true, start: { name: '', x: '', y: '', src: '' }, lines: [blankLine(), blankLine(), blankLine(), blankLine()], walk: [] , from: '' };
  try { const d = JSON.parse(localStorage.getItem(DKEY) || 'null'); if (d && d.lines) S = Object.assign(S, d); } catch (e) {}
  const save = () => { try { localStorage.setItem(DKEY, JSON.stringify(S)); } catch (e) {} };

  /* ---------------- register (public beacons.json, Minna UTM 32N) ---------------- */
  let REG = [];
  const regReady = fetch('/maps/data/beacons.json', { cache: 'no-cache' }).then(r => r.json()).then(j => {
    REG = j.rows.map(([b, x, y, kind, plan, district]) => ({ b, x, y, kind, plan, district }));
  }).catch(() => {});
  const regFind = name => REG.find(r => key(r.b) === key(name));
  const jobFind = name => CFG.coords.find(c => key(c.b) === key(name));
  // A beacon's listed X / Y in the chosen system: this job's list as entered, else the register (Minna UTM 32N, converted).
  function knownXY(name) {
    const j = jobFind(name); if (j) return [j.e, j.n];
    const r = regFind(name); if (!r) return null;
    return S.sys === '26332' ? [r.x, r.y] : toXY(S.sys, ...toLL('26332', r.x, r.y));
  }

  /* ---------------- bearings ---------------- */
  function azOf(l) {
    const d = num(l.d), m = num(l.m) ?? 0, s = num(l.s) ?? 0;
    if (String(l.d).trim() === '') return { empty: true };
    if (d === null || d < 0 || m < 0 || m >= 60 || s < 0 || s >= 60) return { bad: true };
    const t = d + m / 60 + s / 3600;
    if (t >= 360) return { bad: true };
    if (t > 90) return { az: t, whole: true };
    return { az: l.ns === 'N' ? (l.ew === 'E' ? t : (360 - t) % 360) : (l.ew === 'E' ? 180 - t : 180 + t), whole: false };
  }
  function dms(a) { let d = Math.floor(a + 1e-9), m = Math.floor((a - d) * 60 + 1e-9), s = Math.round(((a - d) * 60 - m) * 60); if (s === 60) { s = 0; m++; } if (m === 60) { m = 0; d++; } return [d, m, s]; }
  const p2 = n => String(n).padStart(2, '0');
  function wcb(az) { const [d, m, s] = dms(((az % 360) + 360) % 360); return `${d % 360}° ${p2(m)}′ ${p2(s)}″`; }
  function quad(az) {
    az = ((az % 360) + 360) % 360;
    const [ns, ew, t] = az < 90 ? ['N', 'E', az] : az < 180 ? ['S', 'E', 180 - az] : az < 270 ? ['S', 'W', az - 180] : ['N', 'W', 360 - az];
    const [d, m, s] = dms(t); return `${ns} ${d}° ${p2(m)}′ ${p2(s)}″ ${ew}`;
  }
  // An azimuth back to the entry fields: up to 90° as N … E, over 90° as whole circle (the dropdowns unused).
  function fieldsOf(az) { const [d, m, s] = dms(((az % 360) + 360) % 360); return az <= 90 ? { ns: 'N', ew: 'E', d: String(d), m: String(m), s: String(s) } : { ns: 'N', ew: 'E', d: String(d % 360), m: String(m), s: String(s) }; }

  /* ---------------- traverse maths ---------------- */
  function compute() {
    const sx = num(S.start.x), sy = num(S.start.y), n = S.lines.length;
    let cur = sx !== null && sy !== null ? { x: sx, y: sy } : null;
    const pts = cur ? [{ b: S.start.name || 'X1', x: cur.x, y: cur.y }] : [];
    const out = []; let closeAt = null, from = S.start.name || 'X1';
    S.lines.forEach((l, i) => {
      const last = S.close && i === n - 1 && n >= 3;
      const to = last ? (S.start.name || 'X1') : (key(l.to) || 'X' + (i + 2));
      const b = azOf(l), dist = num(l.dist);
      const ok = cur && b.az !== undefined && dist !== null && dist > 0 && key(to) !== key(from);
      const o = { i, from, to, last, b, dist, ok: !!ok, typed: String(l.d).trim() !== '' || String(l.dist).trim() !== '' };
      if (ok) {
        o.fx = cur.x; o.fy = cur.y;
        const r = b.az * Math.PI / 180; cur = { x: cur.x + dist * Math.sin(r), y: cur.y + dist * Math.cos(r) };
        o.x = cur.x; o.y = cur.y;
        const ref = !last && l.to ? knownXY(l.to) : null;
        if (ref) o.off = Math.hypot(cur.x - ref[0], cur.y - ref[1]);
        if (last) closeAt = cur; else pts.push({ b: to, x: cur.x, y: cur.y });
      } else cur = null;
      out.push(o); from = to;
    });
    let close = null;
    if (closeAt && pts.length >= 3) {
      const dx = closeAt.x - pts[0].x, dy = closeAt.y - pts[0].y, err = Math.hypot(dx, dy);
      const per = out.reduce((t, o) => t + (o.dist || 0), 0);
      const turn = pts.reduce((t, a, k) => { const c = pts[(k + 1) % pts.length]; return t + a.x * c.y - c.x * a.y; }, 0);
      close = { err, dx, dy, per, ratio: err > 0 ? per / err : Infinity, area: Math.abs(turn) / 2, anti: turn > 0 };
    }
    return { pts, out, close, complete: out.length > 0 && out.every(o => o.ok) && !!pts.length };
  }

  /* ---------------- map ---------------- */
  let map = null, mapOk = false, sat = true;
  const empty = { type: 'FeatureCollection', features: [] };
  async function initMap() {
    const protocol = new pmtiles.Protocol(); maplibregl.addProtocol('pmtiles', protocol.tile);
    let style;
    try {
      style = await fetch('/maps/style-light.json').then(r => r.json());
      if (style.sprite?.startsWith('/')) style.sprite = location.origin + style.sprite;
      if (style.glyphs?.startsWith('/')) style.glyphs = location.origin + style.glyphs;
      Object.values(style.sources).forEach(s => {
        if (s.url?.startsWith('pmtiles:///')) s.url = 'pmtiles://' + location.origin + s.url.slice(10);
        if (s.attribution) s.attribution = s.attribution.replace(/Â©/g, '©');
      });
    } catch (e) { style = { version: 8, sources: {}, layers: [{ id: 'bg', type: 'background', paint: { 'background-color': '#e5e7eb' } }] }; }
    map = new maplibregl.Map({ container: 'foMap', style, center: [8.52, 12.0], zoom: 10, attributionControl: false, maxBounds: [[2.5, 4], [15, 14.5]] });
    map.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right');
    map.once('idle', () => document.querySelectorAll('#foMap .maplibregl-compact-show').forEach(el => el.classList.remove('maplibregl-compact-show')));
    map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');
    if (window.ResizeObserver) new ResizeObserver(() => map.resize()).observe($('foMap'));
    map.on('load', () => {
      const firstLabel = map.getStyle().layers.find(l => l.type === 'symbol')?.id;
      map.addSource('sat', { type: 'raster', tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'], tileSize: 256, maxzoom: 19, attribution: 'Imagery © Esri, Maxar' });
      map.addLayer({ id: 'sat', type: 'raster', source: 'sat' }, firstLabel);
      ['reg', 'plan', 'pts', 'track', 'walk', 'me', 'acc'].forEach(id => map.addSource(id, { type: 'geojson', data: empty }));
      map.addLayer({ id: 'reg', type: 'circle', source: 'reg', minzoom: 12, paint: { 'circle-color': '#60a5fa', 'circle-radius': 4, 'circle-stroke-color': '#fff', 'circle-stroke-width': 1 } });
      map.addLayer({ id: 'reg-l', type: 'symbol', source: 'reg', minzoom: 16.5, layout: { 'text-field': ['get', 'b'], 'text-font': ['Noto Sans Regular'], 'text-size': 11, 'text-offset': [0, 1], 'text-anchor': 'top' }, paint: { 'text-color': '#1e3a8a', 'text-halo-color': '#fff', 'text-halo-width': 1.5 } });
      map.addLayer({ id: 'plan-fill', type: 'fill', source: 'plan', filter: ['==', '$type', 'Polygon'], paint: { 'fill-color': '#22d3ee', 'fill-opacity': 0.22 } });
      map.addLayer({ id: 'plan-line', type: 'line', source: 'plan', paint: { 'line-color': '#22d3ee', 'line-width': 3 } });
      map.addLayer({ id: 'plan-lbl', type: 'symbol', source: 'plan', filter: ['==', '$type', 'LineString'], layout: { 'symbol-placement': 'line-center', 'text-field': ['get', 'label'], 'text-font': ['Noto Sans Regular'], 'text-size': 11.5, 'text-offset': [0, -0.9] }, paint: { 'text-color': '#0e7490', 'text-halo-color': '#fff', 'text-halo-width': 2 } });
      map.addLayer({ id: 'track', type: 'line', source: 'track', paint: { 'line-color': '#f59e0b', 'line-width': 2.5, 'line-dasharray': [1.5, 1.2] } });
      map.addLayer({ id: 'walk-fill', type: 'fill', source: 'walk', filter: ['==', '$type', 'Polygon'], paint: { 'fill-color': '#ec4899', 'fill-opacity': 0.2 } });
      map.addLayer({ id: 'walk-line', type: 'line', source: 'walk', paint: { 'line-color': '#ec4899', 'line-width': 3 } });
      map.addLayer({ id: 'pts', type: 'circle', source: 'pts', paint: { 'circle-color': ['match', ['get', 'k'], 'start', '#ea580c', 'walk', '#db2777', '#0891b2'], 'circle-radius': 7, 'circle-stroke-color': '#fff', 'circle-stroke-width': 2.5 } });
      map.addLayer({ id: 'pts-l', type: 'symbol', source: 'pts', layout: { 'text-field': ['get', 'b'], 'text-font': ['Noto Sans Regular'], 'text-size': 12.5, 'text-offset': [0, -1.3], 'text-allow-overlap': true }, paint: { 'text-color': '#111827', 'text-halo-color': '#fff', 'text-halo-width': 2 } });
      map.addLayer({ id: 'acc', type: 'fill', source: 'acc', paint: { 'fill-color': '#3b82f6', 'fill-opacity': 0.15 } });
      map.addLayer({ id: 'me', type: 'circle', source: 'me', paint: { 'circle-color': '#2563eb', 'circle-radius': 8, 'circle-stroke-color': '#fff', 'circle-stroke-width': 3 } });
      mapOk = true;
      regReady.then(() => map.getSource('reg').setData({ type: 'FeatureCollection', features: REG.map(r => ({ type: 'Feature', properties: { b: r.b }, geometry: { type: 'Point', coordinates: toLL('26332', r.x, r.y) } })) }));
      draw(true);
    });
  }
  const ll = (x, y) => toLL(S.sys, x, y);
  function draw(fit) {
    if (!mapOk) return;
    const c = compute(), feats = [], pf = [];
    const coords = c.pts.map(p => ll(p.x, p.y));
    c.out.forEach(o => {
      if (!o.ok) return;
      feats.push({ type: 'Feature', properties: { label: `${o.b.whole ? wcb(o.b.az) : quad(o.b.az)} · ${fmt(o.dist, 2)} m` }, geometry: { type: 'LineString', coordinates: [ll(o.fx, o.fy), ll(o.x, o.y)] } });
    });
    if (c.close) feats.push({ type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [coords.concat([coords[0]])] } });
    c.pts.forEach((p, i) => pf.push({ type: 'Feature', properties: { b: p.b, k: i === 0 ? 'start' : 'pt' }, geometry: { type: 'Point', coordinates: ll(p.x, p.y) } }));
    if (S.mode === 'walk') {
      S.walk.forEach(w => pf.push({ type: 'Feature', properties: { b: w.b, k: 'walk' }, geometry: { type: 'Point', coordinates: [w.lon, w.lat] } }));
      const wc = S.walk.map(w => [w.lon, w.lat]);
      map.getSource('walk').setData({ type: 'FeatureCollection', features: wc.length >= 2 ? [{ type: 'Feature', properties: {}, geometry: wc.length >= 3 ? { type: 'Polygon', coordinates: [wc.concat([wc[0]])] } : { type: 'LineString', coordinates: wc } }] : [] });
    } else map.getSource('walk').setData(empty);
    map.getSource('plan').setData({ type: 'FeatureCollection', features: S.mode === 'trav' ? feats : [] });
    map.getSource('pts').setData({ type: 'FeatureCollection', features: S.mode === 'trav' ? pf : pf.filter(f => f.properties.k === 'walk') });
    stats(c);
    if (fit) fitAll();
  }
  function fitAll() {
    if (!mapOk) return;
    const c = compute();
    const pts = (S.mode === 'walk' ? S.walk.map(w => [w.lon, w.lat]) : c.pts.map(p => ll(p.x, p.y)));
    if (S.mode === 'walk' && ME) pts.push([ME.lon, ME.lat]);
    if (!pts.length) return;
    if (pts.length === 1) { map.easeTo({ center: pts[0], zoom: 18 }); return; }
    const b = pts.reduce((bb, p) => bb.extend(p), new maplibregl.LngLatBounds(pts[0], pts[0]));
    map.fitBounds(b, { padding: 60, maxZoom: 19.5, duration: 500 });
  }
  function stats(c) {
    const el = $('foMapStats'); const bits = [];
    if (S.mode === 'trav') {
      if (c.close) bits.push(`Area ${fmt(c.close.area, 2)} m²`, `Close ${fmt(c.close.err, 3)} m`);
      else if (c.pts.length) bits.push(`${c.pts.length} point${c.pts.length > 1 ? 's' : ''}`);
    } else {
      if (ME) bits.push(`GPS ±${Math.round(ME.acc)} m`);
      if (S.walk.length) bits.push(`${S.walk.length} marked`);
    }
    el.innerHTML = bits.map(b => `<span>${esc(b)}</span>`).join('');
  }

  /* ---------------- bottom sheet ---------------- */
  function sheet(html, onOpen) {
    $('foSheetBody').innerHTML = '<button type="button" class="fo-x" aria-label="Close"><i class="fas fa-xmark"></i></button>' + html;
    $('foSheetBody').querySelector('.fo-x').onclick = closeSheet;
    $('foSheet').classList.add('open'); $('foSheetBg').classList.add('open');
    if (onOpen) onOpen($('foSheetBody'));
  }
  function closeSheet() { $('foSheet').classList.remove('open'); $('foSheetBg').classList.remove('open'); }
  $('foSheetBg').onclick = closeSheet;

  /* ---------------- coordinate system chip ---------------- */
  function sysLabel() { $('foSysChip').querySelector('span').textContent = (SYSTEMS.find(s => s.code === S.sys) || SYSTEMS[0]).name; }
  $('foSysChip').onclick = () => sheet(`<h3>Coordinate system</h3><div class="sub">The X / Y on the plan are read in this system.</div>`
    + SYSTEMS.map(s => `<button type="button" class="fo-opt ${s.code === S.sys ? 'on' : ''}" data-sys="${s.code}"><i class="fas fa-globe"></i><span><b>${esc(s.name)}</b><small>${esc(s.sub)} · EPSG:${s.code}</small></span></button>`).join(''),
    b => b.querySelectorAll('[data-sys]').forEach(x => x.onclick = () => {
      const code = x.dataset.sys;
      if (code !== S.sys && num(S.start.x) !== null && num(S.start.y) !== null && S.start.src !== 'typed') {
        const [lon, lat] = toLL(S.sys, num(S.start.x), num(S.start.y)); const [nx, ny] = toXY(code, lon, lat);
        S.start.x = nx.toFixed(3); S.start.y = ny.toFixed(3);
      }
      S.sys = code; save(); sysLabel(); renderStart(); refresh(true); closeSheet();
    }));

  /* ---------------- start beacon (X1) ---------------- */
  function renderStart() {
    const s = S.start, has = num(s.x) !== null && num(s.y) !== null;
    $('foStartName').textContent = s.name || (has ? 'X1 (unnamed)' : 'Choose the start beacon');
    $('foStartXY').textContent = has ? `E ${fmt(s.x)} · N ${fmt(s.y)}` : 'From this job, the Beacon Register, GPS or typed';
  }
  function setStart(name, x, y, src) { S.start = { name: key(name), x: x === '' ? '' : Number(x).toFixed(3), y: y === '' ? '' : Number(y).toFixed(3), src }; save(); renderStart(); refresh(true); }
  $('foStart').onclick = () => {
    const tabs = [['job', 'This job'], ['reg', 'Beacon Register'], ['gps', 'My position'], ['type', 'Type it']];
    sheet(`<h3>Start beacon (X1)</h3><div class="sub">Where the traverse begins.</div><div class="fo-sheet-tabs">${tabs.map(([k, t], i) => `<button type="button" data-t="${k}" class="${i === 0 ? 'on' : ''}">${t}</button>`).join('')}</div><div id="foSB"></div>`, b => {
      const body = b.querySelector('#foSB');
      const show = t => {
        b.querySelectorAll('[data-t]').forEach(x => x.classList.toggle('on', x.dataset.t === t));
        if (t === 'job') {
          body.innerHTML = CFG.coords.length ? CFG.coords.map((c, i) => `<button type="button" class="fo-opt" data-i="${i}"><i class="fas fa-location-dot"></i><span><b>${esc(c.b)}</b><small>E ${fmt(c.e)} · N ${fmt(c.n)}</small></span></button>`).join('')
            : '<div class="fo-note">No beacons on this job\'s List of Coordinates yet.</div>';
          body.querySelectorAll('[data-i]').forEach(x => x.onclick = () => { const c = CFG.coords[x.dataset.i]; setStart(c.b, c.e, c.n, 'job'); closeSheet(); });
        } else if (t === 'reg') {
          body.innerHTML = '<input class="fo-txt" id="foRegQ" placeholder="Beacon number, e.g. A0876A" autocomplete="off"><div id="foRegR" style="margin-top:10px;"></div>';
          const q = body.querySelector('#foRegQ'), res = body.querySelector('#foRegR');
          const run = () => {
            const t = key(q.value);
            const hits = t ? REG.filter(r => key(r.b).includes(t)).slice(0, 30) : [];
            res.innerHTML = !t ? '<div class="fo-note">Type part of the number.</div>' : hits.length ? hits.map((r, i) => {
              const [x, y] = S.sys === '26332' ? [r.x, r.y] : toXY(S.sys, ...toLL('26332', r.x, r.y));
              return `<button type="button" class="fo-opt" data-b="${esc(r.b)}" data-x="${x}" data-y="${y}"><i class="fas fa-map-pin"></i><span><b>${esc(r.b)}</b><small>${esc([r.plan, r.district].filter(Boolean).join(' · ') || 'Beacon Register')} · E ${fmt(x)} N ${fmt(y)}</small></span></button>`;
            }).join('') : '<div class="fo-note">Not in the Beacon Register. Use Type it.</div>';
            res.querySelectorAll('[data-b]').forEach(x => x.onclick = () => { setStart(x.dataset.b, x.dataset.x, x.dataset.y, 'register'); closeSheet(); });
          };
          q.oninput = run; regReady.then(run); setTimeout(() => q.focus(), 250);
        } else if (t === 'gps') {
          body.innerHTML = `<div class="fo-note">Stand on the start beacon. The phone's position is good to a few metres: fine to find the plot, not for the official X / Y.</div><input class="fo-txt" id="foGName" placeholder="Beacon name (optional)" value="${esc(S.start.name)}"><button type="button" class="fo-btn primary" id="foGGo" style="width:100%;margin-top:10px;"><i class="fas fa-location-crosshairs"></i> Use my position</button><div class="fo-note" id="foGMsg"></div>`;
          body.querySelector('#foGGo').onclick = () => {
            const msg = body.querySelector('#foGMsg'); msg.textContent = 'Getting a fix…';
            averageFix(5000).then(f => { const [x, y] = toXY(S.sys, f.lon, f.lat); setStart(body.querySelector('#foGName').value || 'GPS1', x, y, 'gps'); closeSheet(); toast(`Start set from GPS (±${Math.round(f.acc)} m)`); })
              .catch(e => { msg.textContent = e.message || 'No GPS fix. Check location is on and allowed for this app.'; });
          };
        } else {
          body.innerHTML = `<input class="fo-txt" id="foTN" placeholder="Beacon name" value="${esc(S.start.name)}"><div class="fo-row2" style="grid-template-columns:1fr 1fr;margin-top:8px;"><input class="fo-num" id="foTX" inputmode="decimal" placeholder="X Easting" value="${esc(S.start.x)}"><input class="fo-num" id="foTY" inputmode="decimal" placeholder="Y Northing" value="${esc(S.start.y)}"></div><button type="button" class="fo-btn primary" id="foTGo" style="width:100%;margin-top:10px;">Use these</button><div class="fo-note" id="foTMsg"></div>`;
          body.querySelector('#foTGo').onclick = () => {
            let x = num(body.querySelector('#foTX').value), y = num(body.querySelector('#foTY').value);
            if (x === null || y === null) { body.querySelector('#foTMsg').textContent = 'Enter both X and Y.'; return; }
            if (Math.abs(x) >= 1000000 && Math.abs(y) < 1000000) { [x, y] = [y, x]; toast('X and Y switched: X is the Easting (6 digits).'); }
            setStart(body.querySelector('#foTN').value, x, y, 'typed'); closeSheet();
          };
        }
      };
      b.querySelectorAll('[data-t]').forEach(x => x.onclick = () => show(x.dataset.t));
      show(CFG.coords.length ? 'job' : 'reg');
    });
  };

  /* ---------------- line cards ---------------- */
  const linesEl = $('foLines');
  function lineCard(l, i) {
    const div = document.createElement('div'); div.className = 'fo-line'; div.dataset.i = i;
    div.innerHTML = `<div class="fo-line-head"><span class="fo-tag" data-r="ft">X${i + 1}</span><span class="arrow"><i class="fas fa-arrow-right"></i></span><span class="fo-tag to" data-r="tt">X${i + 2}</span><span data-r="title" style="font-size:13px;color:var(--muted);font-weight:600;"></span><button type="button" class="del" aria-label="Remove line"><i class="fas fa-trash"></i></button></div>`
      + `<div class="fo-brg"><button type="button" class="fo-tog" data-f="ns">${l.ns}</button>`
      + ['d', 'm', 's'].map((f, k) => `<div><input class="fo-num" data-f="${f}" inputmode="${f === 's' ? 'decimal' : 'numeric'}" maxlength="${f === 'd' ? 3 : f === 'm' ? 2 : 5}" value="${esc(l[f])}" placeholder="${['deg', 'min', 'sec'][k]}"><div class="fo-unit">${['°', '′', '″'][k]}</div></div>`).join('')
      + `<button type="button" class="fo-tog" data-f="ew">${l.ew}</button></div>`
      + `<div class="fo-qd" data-r="qd"></div>`
      + `<div class="fo-row2"><div><input class="fo-num" data-f="dist" inputmode="decimal" value="${esc(l.dist)}" placeholder="metres"><div class="fo-unit">distance (m)</div></div>`
      + `<div><input class="fo-txt" data-f="to" list="foStations" value="${esc(l.to)}" placeholder="To beacon" autocomplete="off"><div class="fo-unit" data-r="tohint">to beacon</div></div></div>`
      + `<div class="fo-out" data-r="out"></div>`;
    div.querySelectorAll('input[data-f]').forEach(inp => inp.addEventListener('input', () => {
      S.lines[i][inp.dataset.f] = inp.dataset.f === 'to' ? inp.value.toUpperCase() : inp.value;
      if (inp.dataset.f === 'to') inp.value = inp.value.toUpperCase();
      save(); refresh(false);
      // Next box after a full field, like a calculator.
      const f = inp.dataset.f, nxt = { d: 'm', m: 's' }[f];
      if (nxt && inp.value.length >= (f === 'd' ? 3 : 2)) div.querySelector(`[data-f="${nxt}"]`).focus();
    }));
    div.querySelectorAll('.fo-tog').forEach(t => t.onclick = () => {
      const f = t.dataset.f; S.lines[i][f] = f === 'ns' ? (S.lines[i].ns === 'N' ? 'S' : 'N') : (S.lines[i].ew === 'E' ? 'W' : 'E');
      t.textContent = S.lines[i][f]; save(); refresh(false); navigator.vibrate && navigator.vibrate(10);
    });
    div.querySelector('.del').onclick = () => { if (S.lines.length <= 1) { S.lines = [blankLine()]; } else S.lines.splice(i, 1); save(); renderLines(); };
    return div;
  }
  function renderLines() { linesEl.innerHTML = ''; S.lines.forEach((l, i) => linesEl.appendChild(lineCard(l, i))); refresh(false); }
  $('foAddLine').onclick = () => { S.lines.push(blankLine()); save(); renderLines(); const c = linesEl.lastElementChild; c.scrollIntoView({ behavior: 'smooth', block: 'center' }); setTimeout(() => c.querySelector('[data-f=d]').focus(), 300); };
  $('foClose').onchange = e => { S.close = e.target.checked; save(); refresh(true); };

  function refresh(fit) {
    const c = compute();
    c.out.forEach(o => {
      const card = linesEl.children[o.i]; if (!card) return;
      card.classList.toggle('ok', o.ok); card.classList.toggle('bad', !o.ok && o.typed);
      card.querySelector('[data-r=ft]').textContent = 'X' + (o.i + 1);
      card.querySelector('[data-r=tt]').textContent = o.last ? 'X1' : 'X' + (o.i + 2);
      card.querySelector('[data-r=title]').textContent = `${o.from} → ${o.last ? o.to + ' (closes)' : o.to}`;
      const toIn = card.querySelector('[data-f=to]'); toIn.parentElement.hidden = false; toIn.disabled = o.last; if (o.last) toIn.value = S.start.name || 'X1';
      card.querySelector('[data-r=tohint]').textContent = o.last ? 'closes on X1' : 'to beacon';
      const whole = o.b.whole; card.querySelectorAll('.fo-tog').forEach(t => t.disabled = !!whole);
      const qd = card.querySelector('[data-r=qd]');
      qd.className = 'fo-qd' + (o.b.empty ? ' empty' : o.b.bad ? ' bad' : '');
      qd.textContent = o.b.empty ? 'N/S … E/W up to 90°, or 0–359° whole circle' : o.b.bad ? 'Not a bearing: degrees 0–359, minutes and seconds under 60' : (whole ? `${wcb(o.b.az)} = ${quad(o.b.az)}` : `${quad(o.b.az)} = ${wcb(o.b.az)}`);
      const out = card.querySelector('[data-r=out]');
      out.className = 'fo-out' + (o.off > 1 ? ' reg' : '');
      out.textContent = o.ok ? (o.last ? `Lands at E ${fmt(o.x)} · N ${fmt(o.y)}` : `${o.to}: E ${fmt(o.x)} · N ${fmt(o.y)}`) + (o.off !== undefined ? ` · ${fmt(o.off, 2)} m from its listed X / Y` : '') : '';
    });
    verdict(c); draw(fit); saveState(c);
  }
  function verdict(c) {
    const v = $('foVerdict'), bad = c.out.filter(o => o.typed && !o.ok).map(o => o.i + 1);
    if (num(S.start.x) === null) { v.className = 'fo-verdict warn'; v.innerHTML = '<b>Start beacon needed</b>Tap X1 above and pick or type the start.'; return; }
    if (bad.length) { v.className = 'fo-verdict bad'; v.innerHTML = `<b>Check line${bad.length > 1 ? 's' : ''} ${bad.join(', ')}</b>Each line needs a bearing (degrees 0–359, minutes / seconds under 60), a distance, and a To beacon different from its From.`; return; }
    if (c.close) {
      const e = c.close, cls = e.err <= 0.05 || e.ratio >= 5000 ? 'ok' : e.err <= 0.5 ? 'warn' : 'bad';
      v.className = 'fo-verdict ' + (e.anti && cls === 'ok' ? 'warn' : cls);
      v.innerHTML = `<b>${e.err < 0.0005 ? 'Closes exactly on X1' : 'Closing error ' + fmt(e.err, 3) + ' m'}</b>ΔE ${fmt(e.dx, 3)} · ΔN ${fmt(e.dy, 3)} · perimeter ${fmt(e.per, 2)} m${e.err >= 0.0005 ? ' · 1 : ' + Math.round(e.ratio).toLocaleString() : ''}<br>Area ${fmt(e.area, 2)} m² (${fmt(e.area / 10000, 4)} ha)`
        + (e.anti ? '<br><b style="font-size:13.5px;display:inline">Anticlockwise:</b> a traverse goes round clockwise. Check the order of the lines.' : '');
      return;
    }
    v.className = 'fo-verdict idle';
    v.innerHTML = c.complete ? `<b>${c.out.length} line${c.out.length > 1 ? 's' : ''} worked out</b>Open traverse (not closing on X1).` : '<b>Enter the lines</b>Bearing and distance per line, as read on the ground.';
  }

  /* ---------------- GPS: walk & mark ---------------- */
  let WATCH = null, ME = null, TRACK = [], LOCK = null, lastAuto = null, FIXES = [];
  const qual = a => a <= 5 ? 'good' : a <= 15 ? 'fair' : 'poor';
  function gpsStart() {
    if (!navigator.geolocation) { toast('This phone has no GPS access here.'); return; }
    WATCH = navigator.geolocation.watchPosition(onFix, e => {
      $('foGpsTitle').textContent = e.code === 1 ? 'Location not allowed' : 'No GPS fix yet';
      $('foGpsSub').textContent = e.code === 1 ? 'Allow location for this app in the phone settings.' : 'Go outside, away from buildings, and wait.';
    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 });
    $('foGpsBtn').innerHTML = '<i class="fas fa-stop"></i> Stop'; $('foGpsBtn').classList.add('stop');
    $('foGpsTitle').textContent = 'Searching for satellites…'; $('foGpsSub').textContent = 'Keep the phone facing the sky.';
    $('foGpsDot').className = 'dot live';
    if (navigator.wakeLock) navigator.wakeLock.request('screen').then(l => LOCK = l).catch(() => {});
  }
  function gpsStop() {
    if (WATCH !== null) navigator.geolocation.clearWatch(WATCH); WATCH = null;
    $('foGpsBtn').innerHTML = '<i class="fas fa-satellite-dish"></i> Start'; $('foGpsBtn').classList.remove('stop');
    $('foGpsDot').className = 'dot'; $('foGpsTitle').textContent = 'GPS is off'; $('foGpsSub').textContent = 'Start it, then walk to each corner and tap Mark.';
    $('foMark').disabled = true; if (LOCK) { LOCK.release().catch(() => {}); LOCK = null; }
  }
  $('foGpsBtn').onclick = () => (WATCH === null ? gpsStart() : gpsStop());
  function onFix(p) {
    ME = { lon: p.coords.longitude, lat: p.coords.latitude, acc: p.coords.accuracy, t: Date.now() };
    FIXES.push(ME); if (FIXES.length > 60) FIXES.shift();
    const q = qual(ME.acc);
    $('foGpsDot').className = 'dot live ' + q;
    $('foGpsTitle').textContent = `±${ME.acc < 10 ? ME.acc.toFixed(1) : Math.round(ME.acc)} m · ${{ good: 'good', fair: 'fair', poor: 'weak' }[q]}`;
    const [x, y] = toXY(S.sys, ME.lon, ME.lat);
    $('foGpsSub').textContent = `E ${fmt(x, 1)} · N ${fmt(y, 1)}`;
    $('foMark').disabled = false;
    if (WATCH !== null) { TRACK.push([ME.lon, ME.lat]); if (TRACK.length > 2000) TRACK.shift(); }
    if (mapOk) {
      map.getSource('me').setData({ type: 'Feature', geometry: { type: 'Point', coordinates: [ME.lon, ME.lat] } });
      map.getSource('acc').setData(circle(ME.lon, ME.lat, ME.acc));
      map.getSource('track').setData(TRACK.length > 1 ? { type: 'Feature', geometry: { type: 'LineString', coordinates: TRACK } } : empty);
      if (S.mode === 'walk' && FOLLOW) map.easeTo({ center: [ME.lon, ME.lat], zoom: Math.max(map.getZoom(), 18), duration: 400 });
    }
    // Auto-mark: a new point each time the walker is N metres (or N steps × 0.75 m) from the last one.
    if ($('foAuto').checked && S.mode === 'walk' && ME.acc <= 15) {
      const n = Math.max(1, num($('foStepN').value) || 10), metres = $('foStepU').value === 'steps' ? n * 0.75 : n;
      const ref = lastAuto || S.walk[S.walk.length - 1];
      if (!ref || distM(ref.lon, ref.lat, ME.lon, ME.lat) >= metres) { addWalk(ME, true); lastAuto = ME; }
    }
    stats(compute());
  }
  function distM(lon1, lat1, lon2, lat2) { const R = 6371008.8, r = Math.PI / 180, dl = (lat2 - lat1) * r, dn = (lon2 - lon1) * r, a = Math.sin(dl / 2) ** 2 + Math.cos(lat1 * r) * Math.cos(lat2 * r) * Math.sin(dn / 2) ** 2; return 2 * R * Math.asin(Math.sqrt(a)); }
  function circle(lon, lat, m) { const pts = []; for (let k = 0; k <= 40; k++) { const a = k / 40 * 2 * Math.PI; pts.push([lon + (m * Math.sin(a)) / (111320 * Math.cos(lat * Math.PI / 180)), lat + (m * Math.cos(a)) / 110540]); } return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [pts] } }; }
  // Fixes over a few seconds, weighted by 1/accuracy², the worst third dropped.
  function averageFix(ms) {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error('This phone has no GPS access here.'));
      const got = [], own = WATCH === null ? navigator.geolocation.watchPosition(p => got.push({ lon: p.coords.longitude, lat: p.coords.latitude, acc: p.coords.accuracy }), e => { navigator.geolocation.clearWatch(own); reject(new Error(e.code === 1 ? 'Location is not allowed for this app.' : 'No GPS fix.')); }, { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 }) : null;
      const t0 = Date.now();
      setTimeout(() => {
        if (own !== null) navigator.geolocation.clearWatch(own);
        const src = own !== null ? got : FIXES.filter(f => f.t >= t0);
        if (!src.length && ME && own === null) src.push(ME);
        if (!src.length) return reject(new Error('No GPS fix yet. Wait for the GPS to settle and try again.'));
        const keep = src.slice().sort((a, b) => a.acc - b.acc).slice(0, Math.max(1, Math.ceil(src.length * 2 / 3)));
        let w = 0, lo = 0, la = 0; keep.forEach(f => { const k = 1 / Math.max(f.acc, 0.5) ** 2; w += k; lo += f.lon * k; la += f.lat * k; });
        resolve({ lon: lo / w, lat: la / w, acc: Math.max(Math.min(...keep.map(f => f.acc)) / Math.sqrt(keep.length), keep.reduce((t, f) => t + f.acc, 0) / keep.length / Math.sqrt(keep.length)), n: keep.length });
      }, ms);
    });
  }
  function addWalk(f, auto) {
    const n = S.walk.length + 1;
    S.walk.push({ b: 'P' + n, lon: f.lon, lat: f.lat, acc: f.acc, auto: !!auto });
    save(); renderWalk(); draw(false);
    navigator.vibrate && navigator.vibrate(auto ? 20 : [30, 40, 30]);
    if (!auto) toast(`P${n} marked (±${f.acc < 10 ? f.acc.toFixed(1) : Math.round(f.acc)} m)`);
  }
  $('foMark').onclick = () => {
    if (!ME) return;
    const btn = $('foMark');
    if (!$('foAvg').checked) { addWalk(ME, false); return; }
    btn.classList.add('busy'); btn.disabled = true; $('foMarkHint').textContent = 'Hold still… averaging 5 seconds';
    averageFix(5000).then(f => addWalk(f, false)).catch(e => toast(e.message))
      .finally(() => { btn.classList.remove('busy'); btn.disabled = WATCH === null; $('foMarkHint').textContent = 'Stand on the beacon and hold still.'; });
  };
  function walkXY() { return S.walk.map(w => { const [x, y] = toXY(S.sys, w.lon, w.lat); return { b: key(w.b) || 'P', x, y, w }; }); }
  function renderWalk() {
    $('foMarkLbl').textContent = 'Mark P' + (S.walk.length + 1);
    const el = $('foPts'), pts = walkXY();
    el.innerHTML = pts.length ? pts.map((p, i) => `<div class="fo-pt"><span class="fo-tag" style="background:#db2777;min-width:30px;height:30px;font-size:12px;">${i + 1}</span><div style="flex:1;min-width:0;"><input value="${esc(p.w.b)}" data-i="${i}" aria-label="Point name"><small>E ${fmt(p.x, 2)} · N ${fmt(p.y, 2)}${p.w.auto ? ' · auto' : ''}</small></div><span class="acc ${qual(p.w.acc)}">±${p.w.acc < 10 ? p.w.acc.toFixed(1) : Math.round(p.w.acc)} m</span><button type="button" data-del="${i}" aria-label="Remove point"><i class="fas fa-xmark"></i></button></div>`).join('')
      : '<div class="fo-note" style="margin:12px 0;">No points yet. Start the GPS, walk to a corner and tap Mark.</div>';
    el.querySelectorAll('input[data-i]').forEach(inp => inp.oninput = () => { inp.value = inp.value.toUpperCase(); S.walk[inp.dataset.i].b = inp.value; save(); draw(false); });
    el.querySelectorAll('[data-del]').forEach(b => b.onclick = () => { S.walk.splice(+b.dataset.del, 1); save(); renderWalk(); draw(false); });
    let per = 0; for (let i = 1; i < pts.length; i++) per += Math.hypot(pts[i].x - pts[i - 1].x, pts[i].y - pts[i - 1].y);
    if (pts.length >= 3) per += Math.hypot(pts[0].x - pts[pts.length - 1].x, pts[0].y - pts[pts.length - 1].y);
    const area = pts.length >= 3 ? Math.abs(pts.reduce((t, a, k) => { const c = pts[(k + 1) % pts.length]; return t + a.x * c.y - c.x * a.y; }, 0)) / 2 : null;
    $('foWPts').textContent = pts.length; $('foWPer').textContent = fmt(per, 1) + ' m';
    $('foWArea').textContent = area === null ? '—' : fmt(area, 1) + ' m²';
    $('foWAcc').textContent = pts.length ? '±' + (pts.reduce((t, p) => t + p.w.acc, 0) / pts.length).toFixed(1) + ' m' : '—';
    $('foWalkToTrav').disabled = pts.length < 2; $('foWalkUndo').disabled = !pts.length;
    saveState(compute());
  }
  $('foWalkUndo').onclick = () => { S.walk.pop(); save(); renderWalk(); draw(false); };
  // The walked points become a traverse: P1 is X1, each next point a line (bearing and distance on the grid), closing on P1.
  $('foWalkToTrav').onclick = () => {
    const pts = walkXY(); if (pts.length < 2) return;
    ask({ title: 'Use the walk as a traverse?', text: `The ${pts.length} walked points become the start beacon and the lines. The bearing and distance lines you have now will be replaced.`, confirm: 'Yes, use the walk', icon: 'question' }).then(ok => { if (ok) walkToTrav(pts); });
  };
  function walkToTrav(pts) {
    S.start = { name: pts[0].b, x: pts[0].x.toFixed(3), y: pts[0].y.toFixed(3), src: 'gps' };
    const seq = pts.slice(1).concat(pts.length >= 3 ? [pts[0]] : []);
    let prev = pts[0];
    S.lines = seq.map((p, i) => {
      const dx = p.x - prev.x, dy = p.y - prev.y, az = (Math.atan2(dx, dy) * 180 / Math.PI + 360) % 360, d = Math.hypot(dx, dy);
      const l = Object.assign(blankLine(), fieldsOf(az), { dist: d.toFixed(2), to: i < pts.length - 1 ? p.b : '' });
      prev = p; return l;
    });
    S.close = pts.length >= 3; $('foClose').checked = S.close;
    save(); setMode('trav'); renderStart(); renderLines(); refresh(true);
    toast('Traverse filled from the walk. Replace the bearings and distances with the measured ones.');
  }

  /* ---------------- modes, map buttons ---------------- */
  let FOLLOW = true;
  function setMode(m) {
    S.mode = m; save();
    document.querySelectorAll('.fo-seg [data-mode]').forEach(b => b.classList.toggle('on', b.dataset.mode === m));
    $('foTrav').hidden = m !== 'trav'; $('foWalk').hidden = m !== 'walk';
    draw(true); saveState(compute());
  }
  document.querySelectorAll('.fo-seg [data-mode]').forEach(b => b.onclick = () => setMode(b.dataset.mode));
  $('foFull').onclick = () => { const w = $('foMapWrap'); w.classList.toggle('full'); $('foFull').innerHTML = w.classList.contains('full') ? '<i class="fas fa-compress"></i>' : '<i class="fas fa-expand"></i>'; setTimeout(() => map && map.resize(), 60); };
  $('foBase').onclick = () => { sat = !sat; if (mapOk) map.setLayoutProperty('sat', 'visibility', sat ? 'visible' : 'none'); $('foBase').classList.toggle('on', sat); $('foBase').innerHTML = sat ? '<i class="fas fa-satellite"></i>' : '<i class="fas fa-map"></i>'; };
  $('foFit').onclick = () => { FOLLOW = false; fitAll(); };
  $('foMe').onclick = () => {
    FOLLOW = true;
    if (ME && mapOk) { map.easeTo({ center: [ME.lon, ME.lat], zoom: 18 }); return; }
    if (WATCH === null) gpsStart();
    toast('Finding your position…');
  };

  /* ---------------- save ---------------- */
  function saveState(c) {
    const b = $('foSave'), lbl = $('foSaveLbl');
    if (CFG.locked) { b.disabled = true; lbl.textContent = 'This job is closed'; return; }
    if (S.mode === 'walk') { b.disabled = !S.walk.length; lbl.textContent = S.walk.length ? `Save ${S.walk.length} GPS point${S.walk.length > 1 ? 's' : ''} to this job` : 'Mark points to save'; return; }
    b.disabled = !c.complete; lbl.textContent = c.complete ? `Save ${c.out.length} line${c.out.length > 1 ? 's' : ''} to this job` : 'Complete the lines to save';
  }
  $('foSave').onclick = () => {
    const walk = S.mode === 'walk', c = compute();
    if (!walk && !c.complete) return;
    const points = walk ? walkXY().map(p => ({ beacon_id: p.b, easting: p.x.toFixed(3), northing: p.y.toFixed(3), gps_latitude: p.w.lat.toFixed(7), gps_longitude: p.w.lon.toFixed(7), gps_accuracy_m: p.w.acc.toFixed(1) }))
      : c.pts.map(p => ({ beacon_id: p.b, easting: p.x.toFixed(3), northing: p.y.toFixed(3) }));
    if (!walk) {
      const unnamed = c.out.filter(o => !o.last && !key(S.lines[o.i].to)).map(o => o.i + 1);
      if (!S.start.name) { toast('Name the start beacon (X1) first: tap X1.'); return; }
      if (unnamed.length) { toast(`Line ${unnamed.join(', ')}: type the To beacon. Every beacon is named to be saved.`); return; }
    }
    const dupes = points.filter(p => CFG.coords.some(k => key(k.b) === key(p.beacon_id))).map(p => p.beacon_id);
    const lines = walk ? [] : c.out.map(o => ({ from_station: o.from, to_station: o.to, bearing: o.b.az, distance: o.dist }));
    sheet(`<h3>${walk ? 'Save GPS points' : 'Save the traverse'}</h3><div class="sub">${esc(CFG.its_no)} · ${esc((SYSTEMS.find(s => s.code === S.sys) || {}).name || '')}</div>`
      + `<div class="fo-sum">${walk ? '' : `<div><b>${lines.length}</b><span>readings</span></div>`}<div><b>${points.length - dupes.length}</b><span>new on the List of Coordinates</span></div>`
      + (c.close && !walk ? `<div><b>${fmt(c.close.err, 3)} m</b><span>closing error</span></div><div><b>${fmt(c.close.area, 1)} m²</b><span>area</span></div>` : '') + `</div>`
      + (dupes.length ? `<div class="fo-note">Already on the list, kept as they are: ${esc(dupes.join(', '))}.</div>` : '')
      + (walk ? '<div class="fo-note">GPS points go to the List of Coordinates with their accuracy. They are not field readings.</div>' : '')
      + `<label class="fo-unit" style="text-align:left;font-size:12.5px;">Date observed</label><input type="date" class="fo-txt" id="foOn" value="${CFG.today}" max="${CFG.today}" style="text-transform:none;margin-bottom:12px;">`
      + `<button type="button" class="fo-btn primary" id="foGo" style="width:100%;padding:16px;"><i class="fas fa-cloud-arrow-up"></i> Save now</button><div class="fo-note" id="foGoMsg"></div>`, b => {
      b.querySelector('#foGo').onclick = () => {
        const msg = b.querySelector('#foGoMsg');
        if (!navigator.onLine) { msg.textContent = 'No signal. Your work stays on this phone: save when you are back online.'; return; }
        const go = b.querySelector('#foGo'); go.disabled = true; go.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        const body = { client_uuid: 'fo' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8), method: walk ? 'gps' : 'traverse', coord_system: S.sys, observed_on: b.querySelector('#foOn').value, lines, points };
        fetch(CFG.saveUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CFG.csrf }, body: JSON.stringify(body) })
          .then(r => r.json().then(j => ({ ok: r.ok, j })))
          .then(({ ok, j }) => {
            if (ok && j.ok) {
              if (walk) S.walk = []; else { S.lines = [blankLine(), blankLine(), blankLine(), blankLine()]; S.start = { name: '', x: '', y: '', src: '' }; }
              save(); msg.style.color = '#047857'; msg.textContent = j.message; go.innerHTML = '<i class="fas fa-check"></i> Saved';
              setTimeout(() => { location.href = CFG.after; }, 1300); return;
            }
            const errs = j.errors || {}, k = Object.keys(errs);
            msg.textContent = k.length ? errs[k[0]][0] : (j.message || 'It could not be saved.'); go.disabled = false; go.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Try again';
          }).catch(() => { msg.textContent = 'It could not be sent. Check the signal and try again.'; go.disabled = false; go.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Try again'; });
      };
    });
  };

  /* ---------------- confirmations: SweetAlert (user, 2026-11-15), never the browser's own pop-up ---------------- */
  function ask(o) {
    // No SweetAlert (no signal when the page loaded): the app's own sheet asks instead.
    if (!window.Swal) return new Promise(res => {
      sheet(`<h3>${esc(o.title)}</h3><div class="sub">${esc(o.text)}</div><div class="fo-actions"><button type="button" class="fo-btn" data-a="0">Cancel</button><button type="button" class="fo-btn primary" data-a="1">${esc(o.confirm || 'Yes')}</button></div>`,
        b => b.querySelectorAll('[data-a]').forEach(x => x.onclick = () => { closeSheet(); res(x.dataset.a === '1'); }));
    });
    return Swal.fire({ title: o.title, text: o.text, icon: o.icon || 'warning', showCancelButton: true, confirmButtonText: o.confirm || 'Yes',
      cancelButtonText: 'Cancel', reverseButtons: true, confirmButtonColor: '#1e1b2e', cancelButtonColor: '#9ca3af', heightAuto: false })
      .then(r => r.isConfirmed);
  }

  /* ---------------- toast ---------------- */
  function toast(t) {
    let el = document.getElementById('foToast');
    if (!el) { el = document.createElement('div'); el.id = 'foToast'; el.style.cssText = 'position:fixed;left:50%;bottom:calc(150px + env(safe-area-inset-bottom));transform:translateX(-50%);background:#1e1b2e;color:#fff;padding:11px 16px;border-radius:14px;font:600 14px Outfit,sans-serif;z-index:120;max-width:88vw;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.3);transition:opacity .3s;'; document.body.appendChild(el); }
    el.textContent = t; el.style.opacity = '1'; clearTimeout(el._t); el._t = setTimeout(() => { el.style.opacity = '0'; }, 2800);
  }

  /* ---------------- start ---------------- */
  const dl = document.createElement('datalist'); dl.id = 'foStations';
  dl.innerHTML = CFG.coords.map(c => `<option value="${esc(c.b)}">`).join(''); document.body.appendChild(dl);
  $('foClose').checked = S.close; sysLabel(); renderStart(); renderLines(); renderWalk(); setMode(S.mode);
  initMap();
  window.FO_FIELD = { compute, state: () => S };
})();
