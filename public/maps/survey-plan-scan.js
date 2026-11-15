/*
 | Survey Plan Check prototype: a scanned plan (or GeoTIFF) on the map (user, 2026-11-13).
 | Their paper sheets are scanned; we place the scan on the map so the plotted traverse can be checked against it
 | instead of with biro and ruler. Two ways to place it:
 |   1. One beacon + scale: click a beacon on the scan, type its X / Y, the plan's scale and the scan's dpi.
 |   2. Control points: click 2 or more beacons on the scan and type their X / Y (2 = scale + turn, 3+ = best fit).
 | A real GeoTIFF places itself (its own coordinates). Runs after the page's main script and shares its globals
 | (map, $, toLL, toXY, gridCode, GRID_NAME, REG_BY, key, fmt, esc, setRef, refresh, toast, OUR).
 | Prototype only: the scan stays in the browser, nothing is uploaded.
 */
(function () {
  'use strict';
  OUR.push('scan');
  // Projections a GeoTIFF may carry, beyond the page's own.
  const MINNA = '+a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs';
  proj4.defs('EPSG:26331', '+proj=utm +zone=31 ' + MINNA);
  proj4.defs('EPSG:32631', '+proj=utm +zone=31 +datum=WGS84 +units=m +no_defs');
  const KNOWN = ['26391', '26392', '26393', '26331', '26332', '32631', '32632', '4326'];
  const NAME = c => GRID_NAME[c] || { 26331: 'Minna / UTM 31N (EPSG:26331)', 26332: 'Minna / UTM 32N (EPSG:26332)', 32631: 'UTM 31N, WGS 84 (EPSG:32631)', 4326: 'Latitude / longitude, WGS 84 (EPSG:4326)' }[c] || 'EPSG:' + c;
  const MAXTEX = 4096;          // the map texture limit we keep to; bigger scans are shrunk for the map only

  let SRC = null;               // { canvas, w, h, name, dpi }
  let PLACED = null;            // { code, T, corners }
  let pts = [];                 // control points: { c, r, b, x, y }
  let quickPt = null;           // the one beacon clicked (quick method)
  let method = 'quick';

  /* ---------- the panel ---------- */
  const panel = $('geoPanel'), cv = $('gvCanvas'), ctx = cv.getContext('2d');
  const open = () => { panel.hidden = false; document.body.classList.add('noscroll'); sizeCanvas(); fitView(); draw(); };
  const close = () => { panel.hidden = true; document.body.classList.remove('noscroll'); };
  $('scanBtn').onclick = open;
  $('geoClose').onclick = close;
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { e.stopPropagation(); close(); } }, true);
  panel.querySelectorAll('input[name=geomethod]').forEach(r => r.addEventListener('change', () => { method = r.value; showMethod(); draw(); }));
  function showMethod() {
    $('geoQuick').hidden = method !== 'quick'; $('geoCps').hidden = method !== 'cps';
    $('gvHint').textContent = !SRC ? 'Open a scan first.' : method === 'quick' ? 'Click the beacon whose coordinates you know (zoom in with the mouse wheel).' : 'Click a beacon on the scan, then type its X / Y. Two or more points.';
    solve();
  }
  $('geoSys').textContent = NAME(gridCode());
  document.querySelectorAll('input[name=sys], input[name=belt]').forEach(r => r.addEventListener('change', () => { $('geoSys').textContent = NAME(gridCode()); solve(); }));

  /* ---------- opening a file ---------- */
  $('geoFile').addEventListener('change', async e => {
    const f = e.target.files[0]; if (!f) return;
    $('geoMsg').className = 'note'; $('geoMsg').textContent = 'Reading ' + f.name + '…';
    try {
      if (/\.tiff?$/i.test(f.name) || f.type === 'image/tiff') await readTiff(await f.arrayBuffer(), f.name);
      else await readImage(URL.createObjectURL(f), f.name, null);
    } catch (err) { $('geoMsg').className = 'note bad'; $('geoMsg').textContent = 'Could not read the file: ' + err.message; }
  });
  async function readImage(url, name, dpi) {
    const img = await new Promise((ok, no) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => no(new Error('not an image')); i.src = url; });
    const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; c.getContext('2d').drawImage(img, 0, 0);
    loaded({ canvas: c, w: c.width, h: c.height, name, dpi }, null);
  }
  async function readTiff(buf, name) {
    try { return await readGeoTiff(buf, name); }
    catch (err) { return readUtif(buf, name); }   // scanners often write a bad tag geotiff.js refuses (the sample's is one)
  }
  // Fallback: UTIF.js skips bad tags. It reads the GeoTIFF tags too, when there are any.
  function readUtif(buf, name) {
    const ifds = UTIF.decode(buf), ifd = ifds.find(f => f.t256 && f.t257) || ifds[0];
    UTIF.decodeImage(buf, ifd, ifds);
    const w = ifd.width, h = ifd.height, rgba = UTIF.toRGBA8(ifd);
    const c = document.createElement('canvas'); c.width = w; c.height = h;
    c.getContext('2d').putImageData(new ImageData(new Uint8ClampedArray(rgba.buffer, rgba.byteOffset, w * h * 4), w, h), 0, 0);
    const res = ifd.t282, unit = (ifd.t296 || [2])[0];
    let dpi = res ? (Array.isArray(res) ? +res[0] : +res) : null;
    if (dpi && unit === 3) dpi *= 2.54;
    let geo = null;
    if (ifd.t33550 && ifd.t33922) {   // ModelPixelScale + ModelTiepoint; GeoKeyDirectory (34735) for the system
      const [sx, sy] = ifd.t33550, tp = ifd.t33922, keys = ifd.t34735 || [], k = {};
      for (let i = 4; i + 3 < keys.length; i += 4) k[keys[i]] = keys[i + 3];
      const code = String(k[3072] || k[2048] || '');
      geo = { code, T: { a: sx, b: 0, tx: tp[3] - tp[0] * sx, d: 0, e: -sy, ty: tp[4] + tp[1] * sy } };
    }
    loaded({ canvas: c, w, h, name, dpi }, geo);
  }
  async function readGeoTiff(buf, name) {
    const tiff = await GeoTIFF.fromArrayBuffer(buf), im = await tiff.getImage();
    const w = im.getWidth(), h = im.getHeight();
    const rgb = await im.readRGB({ interleave: true });
    const c = document.createElement('canvas'); c.width = w; c.height = h;
    const g = c.getContext('2d'), id = g.createImageData(w, h);
    for (let i = 0, j = 0; i < w * h; i++, j += 3) { id.data[i * 4] = rgb[j]; id.data[i * 4 + 1] = rgb[j + 1]; id.data[i * 4 + 2] = rgb[j + 2]; id.data[i * 4 + 3] = 255; }
    g.putImageData(id, 0, 0);
    const fd = im.fileDirectory, res = fd.XResolution, unit = fd.ResolutionUnit || 2;
    let dpi = Array.isArray(res) ? res[0] / (res[1] || 1) : (+res || null);
    if (dpi && unit === 3) dpi *= 2.54;
    // A GeoTIFF: its own coordinates place it.
    let geo = null;
    if (fd.ModelPixelScale && fd.ModelTiepoint) {
      const k = im.getGeoKeys() || {}, code = String(k.ProjectedCSTypeGeoKey || k.GeographicTypeGeoKey || '');
      const [ox, oy] = im.getOrigin(), [rx, ry] = im.getResolution();
      geo = { code, T: { a: rx, b: 0, tx: ox, d: 0, e: ry, ty: oy } };
    }
    loaded({ canvas: c, w, h, name, dpi }, geo);
  }
  function loaded(src, geo) {
    $('geoReadBox').hidden = !/^Sample/.test(src.name);
    SRC = src; pts = []; quickPt = null; renderPts();
    $('gvName').textContent = `${src.name} · ${src.w.toLocaleString()} × ${src.h.toLocaleString()} px${src.dpi ? ' · ' + Math.round(src.dpi) + ' dpi' : ''}`;
    if (src.dpi) $('gqDpi').value = Math.round(src.dpi);
    $('geoMsg').className = 'note';
    if (geo && KNOWN.includes(geo.code)) {
      $('geoMsg').className = 'note ok';
      $('geoMsg').textContent = `GeoTIFF: it carries its own coordinates (${NAME(geo.code)}). Placed on the map as it is.`;
      place(geo.code, geo.T); close(); return;
    }
    $('geoMsg').textContent = geo ? `GeoTIFF in ${geo.code ? 'EPSG:' + geo.code : 'an unknown system'}, which this check does not read yet: place it by hand below.`
      : 'A plain scan: it has no coordinates of its own. Place it with one beacon + scale, or with control points.';
    fitView(); showMethod(); draw();
  }

  /* ---------- scan viewer: wheel zoom, drag to pan, click to pick ---------- */
  let view = { s: 1, ox: 0, oy: 0 };
  function sizeCanvas() { const r = cv.parentElement.getBoundingClientRect(), k = devicePixelRatio || 1; cv.width = Math.max(1, r.width * k); cv.height = Math.max(1, r.height * k); cv.style.width = r.width + 'px'; cv.style.height = r.height + 'px'; }
  if (window.ResizeObserver) new ResizeObserver(() => { if (!panel.hidden) { sizeCanvas(); draw(); } }).observe(cv.parentElement);
  function fitView() { if (!SRC) return; const s = Math.min(cv.width / SRC.w, cv.height / SRC.h) * 0.96; view = { s, ox: (cv.width - SRC.w * s) / 2, oy: (cv.height - SRC.h * s) / 2 }; }
  const toImg = (px, py) => [(px - view.ox) / view.s, (py - view.oy) / view.s];
  function draw() {
    ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.fillStyle = '#1e293b'; ctx.fillRect(0, 0, cv.width, cv.height);
    if (!SRC) { ctx.fillStyle = '#94a3b8'; ctx.font = `${14 * devicePixelRatio}px system-ui`; ctx.textAlign = 'center'; ctx.fillText('Open a scanned plan (TIFF, JPG or PNG) or try the sample', cv.width / 2, cv.height / 2); return; }
    ctx.imageSmoothingEnabled = view.s < 1; ctx.setTransform(view.s, 0, 0, view.s, view.ox, view.oy); ctx.drawImage(SRC.canvas, 0, 0);
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    const k = devicePixelRatio || 1;
    const mark = (p, label, col) => {
      const x = p.c * view.s + view.ox, y = p.r * view.s + view.oy;
      ctx.strokeStyle = col; ctx.lineWidth = 2.5 * k; ctx.beginPath(); ctx.arc(x, y, 9 * k, 0, 7); ctx.moveTo(x - 15 * k, y); ctx.lineTo(x + 15 * k, y); ctx.moveTo(x, y - 15 * k); ctx.lineTo(x, y + 15 * k); ctx.stroke();
      ctx.font = `bold ${12 * k}px system-ui`; ctx.textAlign = 'left'; ctx.lineWidth = 4 * k; ctx.strokeStyle = '#fff'; ctx.strokeText(label, x + 12 * k, y - 10 * k); ctx.fillStyle = col; ctx.fillText(label, x + 12 * k, y - 10 * k);
    };
    if (method === 'quick' && quickPt) mark(quickPt, $('gqName').value.trim() || 'Beacon', '#ea580c');
    if (method === 'cps') pts.forEach((p, i) => mark(p, String(i + 1) + (p.b ? ' · ' + p.b : ''), '#dc2626'));
  }
  let drag = null;
  cv.addEventListener('pointerdown', e => { drag = { x: e.clientX, y: e.clientY, ox: view.ox, oy: view.oy, moved: false }; cv.setPointerCapture(e.pointerId); });
  cv.addEventListener('pointermove', e => {
    if (!drag) return; const k = devicePixelRatio || 1, dx = (e.clientX - drag.x) * k, dy = (e.clientY - drag.y) * k;
    if (Math.hypot(dx, dy) > 4 * k) drag.moved = true;
    if (drag.moved) { view.ox = drag.ox + dx; view.oy = drag.oy + dy; draw(); }
  });
  cv.addEventListener('pointerup', e => {
    const d = drag; drag = null; if (!d || d.moved || !SRC) return;
    const r = cv.getBoundingClientRect(), k = devicePixelRatio || 1, [c, rr] = toImg((e.clientX - r.left) * k, (e.clientY - r.top) * k);
    if (c < 0 || rr < 0 || c > SRC.w || rr > SRC.h) return;
    if (method === 'quick') { quickPt = { c, r: rr }; $('gqPx').textContent = `pixel ${Math.round(c)}, ${Math.round(rr)}`; }
    else { pts.push({ c, r: rr, b: '', x: '', y: '' }); renderPts(); const ins = $('gcRows').querySelectorAll('input.b'); if (ins.length) ins[ins.length - 1].focus(); }
    solve(); draw();
  });
  cv.addEventListener('wheel', e => {
    if (!SRC) return; e.preventDefault();
    const r = cv.getBoundingClientRect(), k = devicePixelRatio || 1, px = (e.clientX - r.left) * k, py = (e.clientY - r.top) * k;
    const f = Math.exp(-e.deltaY * 0.0015), s = Math.min(8, Math.max(0.02, view.s * f)), [ix, iy] = toImg(px, py);
    view = { s, ox: px - ix * s, oy: py - iy * s }; draw();
  }, { passive: false });
  $('gvFit').onclick = () => { fitView(); draw(); };
  $('gvIn').onclick = () => zoomBy(1.5); $('gvOut').onclick = () => zoomBy(1 / 1.5);
  function zoomBy(f) { const cx = cv.width / 2, cy = cv.height / 2, [ix, iy] = toImg(cx, cy), s = Math.min(8, Math.max(0.02, view.s * f)); view = { s, ox: cx - ix * s, oy: cy - iy * s }; draw(); }

  /* ---------- control point rows ---------- */
  function renderPts() {
    const body = $('gcRows');
    body.innerHTML = pts.map((p, i) => `<tr><td class="n">${i + 1}</td><td class="mono">${Math.round(p.c)}, ${Math.round(p.r)}</td>`
      + `<td><input type="text" class="b" data-i="${i}" value="${esc(p.b)}" placeholder="Beacon No." aria-label="Beacon No."></td>`
      + `<td><input type="text" class="x" data-i="${i}" value="${esc(p.x)}" inputmode="decimal" placeholder="X (Easting)" aria-label="X (Easting)"></td>`
      + `<td><input type="text" class="y" data-i="${i}" value="${esc(p.y)}" inputmode="decimal" placeholder="Y (Northing)" aria-label="Y (Northing)"></td>`
      + `<td class="mono res" data-i="${i}">—</td><td><button type="button" class="ib" data-del="${i}" aria-label="Remove" title="Remove"><i class="fas fa-xmark"></i></button></td></tr>`).join('')
      || '<tr><td colspan="7" class="note" style="padding:10px">Click a beacon on the scan to add a point.</td></tr>';
    body.querySelectorAll('input').forEach(inp => inp.addEventListener('change', () => {
      const p = pts[+inp.dataset.i], f = inp.classList.contains('b') ? 'b' : inp.classList.contains('x') ? 'x' : 'y';
      p[f] = inp.value.trim();
      // A Beacon Register number fills its X / Y in the chosen system.
      if (f === 'b') { p.b = p.b.toUpperCase(); inp.value = p.b; const xy = knownXY(p.b); if (xy && !p.x && !p.y) { p.x = xy[0].toFixed(3); p.y = xy[1].toFixed(3); renderPts(); } }
      solve(); draw();
    }));
    body.querySelectorAll('[data-del]').forEach(b => b.onclick = () => { pts.splice(+b.dataset.del, 1); renderPts(); solve(); draw(); });
  }
  // A beacon's X / Y: the beacons plotted on this card first (other beacons, then reference), then the Beacon Register.
  function knownXY(b) {
    const o = [...SV, ...REFS].find(o => o.beacon && key(o.beacon) === key(b) && o.x !== undefined && !o.bad);
    if (o) return [o.x, o.y];
    const reg = REG_BY.get(key(b)); return reg ? toXY(gridCode(), reg.lon, reg.lat) : null;
  }
  $('gcClear').onclick = () => { pts = []; renderPts(); solve(); draw(); };
  ['gqName', 'gqX', 'gqY', 'gqScale', 'gqDpi', 'gqRot'].forEach(id => $(id).addEventListener('input', () => { solve(); draw(); }));

  /* ---------- the fit: image pixel (c, r) -> grid X / Y ---------- */
  // T: X = a·c + b·r + tx ; Y = d·c + e·r + ty  (r counts down the scan).
  const num = v => { const t = String(v ?? '').replace(/[,\s]/g, ''), n = Number(t); return t === '' || !isFinite(n) ? null : n; };
  function lsq(A, L) {   // least squares: (AᵀA) x = AᵀL, Gauss-Jordan
    const k = A[0].length, N = Array.from({ length: k }, () => Array(k + 1).fill(0));
    A.forEach((row, i) => { for (let p = 0; p < k; p++) { for (let q = 0; q < k; q++) N[p][q] += row[p] * row[q]; N[p][k] += row[p] * L[i]; } });
    for (let p = 0; p < k; p++) {
      let m = p; for (let q = p + 1; q < k; q++) if (Math.abs(N[q][p]) > Math.abs(N[m][p])) m = q;
      [N[p], N[m]] = [N[m], N[p]]; if (Math.abs(N[p][p]) < 1e-12) return null;
      for (let q = 0; q < k; q++) if (q !== p) { const f = N[q][p] / N[p][p]; for (let j = p; j <= k; j++) N[q][j] -= f * N[p][j]; }
    }
    return N.map((row, p) => row[k] / row[p]);
  }
  let FIT = null;
  function solve() {
    FIT = null; const out = $('geoFit'); out.className = 'geo-fit';
    if (!SRC) { out.textContent = ''; $('geoPlace').disabled = true; return; }
    if (method === 'quick') {
      const X0 = num($('gqX').value), Y0 = num($('gqY').value), sc = num($('gqScale').value), dpi = num($('gqDpi').value), rot = num($('gqRot').value) ?? 0;
      const miss = [!quickPt && 'click the beacon on the scan', (X0 === null || Y0 === null) && 'its X and Y', !sc && 'the scale', !dpi && 'the dpi'].filter(Boolean);
      if (miss.length) { out.textContent = 'Still needed: ' + miss.join(', ') + '.'; $('geoPlace').disabled = true; return; }
      const p = sc * 0.0254 / dpi, t = rot * Math.PI / 180, a = p * Math.cos(t), b = -p * Math.sin(t), d = -p * Math.sin(t), e = -p * Math.cos(t);
      FIT = { a, b, d, e, tx: X0 - a * quickPt.c - b * quickPt.r, ty: Y0 - d * quickPt.c - e * quickPt.r };
      out.innerHTML = `<b>Ready.</b> One pixel = ${fmt(p, 4)} m on the ground (1:${sc.toLocaleString()} at ${dpi} dpi), turned ${fmt(rot, 2)}°. The sheet covers about ${fmt(SRC.w * p, 0)} × ${fmt(SRC.h * p, 0)} m.`;
    } else {
      const ok = pts.filter(p => num(p.x) !== null && num(p.y) !== null);
      $('gcRows').querySelectorAll('.res').forEach(td => td.textContent = '—');
      if (ok.length < 2) { out.textContent = `Still needed: ${2 - ok.length} more point${ok.length ? '' : 's'} with X and Y (3 or more checks each other).`; $('geoPlace').disabled = true; return; }
      if (ok.length === 2) {   // scale + turn + shift, exact
        const A = [], L = []; ok.forEach(p => { A.push([p.c, p.r, 1, 0]); L.push(num(p.x)); A.push([-p.r, p.c, 0, 1]); L.push(num(p.y)); });
        const s = lsq(A, L); if (s) FIT = { a: s[0], b: s[1], tx: s[2], d: s[1], e: -s[0], ty: s[3] };
      } else {                 // best fit (affine): takes paper stretch, and shows each point's miss
        const A = ok.map(p => [p.c, p.r, 1]), sx = lsq(A, ok.map(p => num(p.x))), sy = lsq(A, ok.map(p => num(p.y)));
        if (sx && sy) FIT = { a: sx[0], b: sx[1], tx: sx[2], d: sy[0], e: sy[1], ty: sy[2] };
      }
      if (!FIT) { out.className = 'geo-fit bad'; out.textContent = 'These points do not fit: two of them are in the same place.'; $('geoPlace').disabled = true; return; }
      let ss = 0;
      ok.forEach(p => { const [x, y] = apply(FIT, p.c, p.r), m = Math.hypot(x - num(p.x), y - num(p.y)); ss += m * m; const td = $('gcRows').querySelector(`.res[data-i="${pts.indexOf(p)}"]`); if (td) td.textContent = fmt(m, 2) + ' m'; });
      const pix = Math.sqrt(Math.abs(FIT.a * FIT.e - FIT.b * FIT.d)), turn = Math.atan2(-FIT.b, FIT.a) * 180 / Math.PI;
      const rms = ok.length > 2 ? Math.sqrt(ss / ok.length) : null;
      out.className = 'geo-fit' + (rms !== null && rms > 2 ? ' bad' : '');
      out.innerHTML = `<b>${ok.length} points.</b> One pixel = ${fmt(pix, 4)} m, turned ${fmt(turn, 2)}°.` + (rms !== null ? ` Average miss ${fmt(rms, 2)} m${rms > 2 ? ': check the points with the biggest miss' : ''}.` : ' Add a third point to check the fit.');
    }
    $('geoPlace').disabled = !FIT;
  }
  const apply = (T, c, r) => [T.a * c + T.b * r + T.tx, T.d * c + T.e * r + T.ty];

  /* ---------- on the map ---------- */
  $('geoPlace').onclick = () => {
    if (!FIT) return;
    place(gridCode(), FIT);
    // One beacon + scale: that beacon becomes the reference coordinate when section 2 is empty.
    if (method === 'quick' && !REFS.some(o => !o.empty)) setRef($('gqName').value.trim().toUpperCase(), $('gqX').value.trim(), $('gqY').value.trim());
    close();
  };
  function place(code, T) {
    const ll = code === '4326' ? (x, y) => [x, y] : (x, y) => toLL(code, x, y);
    const corners = [[0, 0], [SRC.w, 0], [SRC.w, SRC.h], [0, SRC.h]].map(([c, r]) => ll(...apply(T, c, r)));
    PLACED = { code, T, corners, w: SRC.w, h: SRC.h, adj: { tx: 0, ty: 0, rot: 0, s: 1 } };
    syncAdj();
    // Outside Kano (like the Umuahia sample): open the map and the checks to all of Nigeria.
    const mid = corners.reduce((s, c) => [s[0] + c[0] / 4, s[1] + c[1] / 4], [0, 0]);
    if (!inKano(mid) && $('kanoOnly').checked) { $('kanoOnly').checked = false; $('kanoOnly').dispatchEvent(new Event('change')); toast('This plan is outside Kano: the map and checks now cover all of Nigeria (Check options).'); }
    let c = SRC.canvas;
    if (Math.max(c.width, c.height) > MAXTEX) { const k = MAXTEX / Math.max(c.width, c.height), s = document.createElement('canvas'); s.width = Math.round(c.width * k); s.height = Math.round(c.height * k); s.getContext('2d').drawImage(c, 0, 0, s.width, s.height); c = s; }
    PLACED.canvas = c; INK = null;
    // Lines only by default: yellow over the satellite, dark over the street map or the plain backdrop.
    $('scanPaper').value = $('baseOn').checked && BASE === 'satellite' ? 'yellow' : 'dark';
    paperOut(c, $('scanPaper').value).toBlob(blob => {
      const url = URL.createObjectURL(blob);
      const show = () => {
        const s = map.getSource('scan');
        if (s) s.updateImage({ url, coordinates: corners });
        else { map.addSource('scan', { type: 'image', url, coordinates: corners }); map.addLayer({ id: 'scan', type: 'raster', source: 'scan', paint: { 'raster-opacity': +$('scanOp').value / 100, 'raster-fade-duration': 0 } }, 'links'); }
        map.setLayoutProperty('scan', 'visibility', 'visible');
        bindMove();
        const b = new maplibregl.LngLatBounds(); corners.forEach(x => b.extend(x)); map.fitBounds(b, { padding: 40, duration: 700 });
        $('scanGrp').hidden = false; $('scanVis').checked = true;
        $('scanTag').textContent = SRC.name;
      };
      if (mapReady) show(); else map.once('load', show);
    }, 'image/png');
  }

  /* ---------- lines only: take the paper away (user, 2026-11-14) ---------- */
  // A scan lands as a whole sheet (paper colour, blueprint tint, scanner edge) and hides the map under it.
  // Each pixel is compared with the paper around it (brightest value per 24 px block, smoothed), so a tint
  // or a lighter band across the sheet still counts as paper. What is darker than its paper is ink: kept,
  // in one colour, as solid as it is dark; the paper goes see-through.
  let INK = null;   // ink strength per pixel (0-255), worked out once per placed scan
  function inkOf(c) {
    const w = c.width, h = c.height, px = c.getContext('2d').getImageData(0, 0, w, h).data, L = new Uint8Array(w * h);
    for (let i = 0, j = 0; i < w * h; i++, j += 4) L[i] = (px[j] * 77 + px[j + 1] * 150 + px[j + 2] * 29) >> 8;
    const B = 24, bw = Math.ceil(w / B), bh = Math.ceil(h / B), M = new Float32Array(bw * bh);
    for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { const v = L[y * w + x], k = ((y / B) | 0) * bw + ((x / B) | 0); if (v > M[k]) M[k] = v; }
    const S = new Float32Array(bw * bh);   // 3 x 3 average: one line through a block does not darken its paper
    for (let by = 0; by < bh; by++) for (let bx = 0; bx < bw; bx++) {
      let t = 0, n = 0;
      for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) { const yy = by + dy, xx = bx + dx; if (yy >= 0 && xx >= 0 && yy < bh && xx < bw) { t += M[yy * bw + xx]; n++; } }
      S[by * bw + bx] = t / n;
    }
    const A = new Uint8Array(w * h);
    for (let y = 0; y < h; y++) {
      const fy = Math.min(bh - 1, Math.max(0, y / B - 0.5)), y0 = fy | 0, y1 = Math.min(bh - 1, y0 + 1), ty = fy - y0;
      for (let x = 0; x < w; x++) {
        const fx = Math.min(bw - 1, Math.max(0, x / B - 0.5)), x0 = fx | 0, x1 = Math.min(bw - 1, x0 + 1), tx = fx - x0;
        const bg = (S[y0 * bw + x0] * (1 - tx) + S[y0 * bw + x1] * tx) * (1 - ty) + (S[y1 * bw + x0] * (1 - tx) + S[y1 * bw + x1] * tx) * ty;
        const t = (bg - L[y * w + x] - 24) / Math.max(30, bg * 0.36);
        A[y * w + x] = t <= 0 ? 0 : t >= 1 ? 255 : Math.round(255 * Math.sqrt(t));
      }
    }
    return A;
  }
  const INKS = { dark: [15, 23, 42], yellow: [253, 224, 71] };
  function paperOut(c, how) {
    if (how === 'keep' || !INKS[how]) return c;
    if (!INK) INK = inkOf(c);
    const o = document.createElement('canvas'); o.width = c.width; o.height = c.height;
    const g = o.getContext('2d'), id = g.createImageData(c.width, c.height), d = id.data, [r, gg, b] = INKS[how];
    for (let i = 0, j = 0; i < INK.length; i++, j += 4) { d[j] = r; d[j + 1] = gg; d[j + 2] = b; d[j + 3] = INK[i]; }
    g.putImageData(id, 0, 0); return o;
  }
  $('scanPaper').onchange = () => {
    if (!PLACED || !PLACED.canvas || !map.getSource('scan')) return;
    paperOut(PLACED.canvas, $('scanPaper').value).toBlob(blob => map.getSource('scan').updateImage({ url: URL.createObjectURL(blob), coordinates: PLACED.corners }), 'image/png');
  };
  $('scanOp').addEventListener('input', () => { if (map.getLayer('scan')) map.setPaintProperty('scan', 'raster-opacity', +$('scanOp').value / 100); });
  $('scanVis').onchange = () => { if (map.getLayer('scan')) map.setLayoutProperty('scan', 'visibility', $('scanVis').checked ? 'visible' : 'none'); };
  $('scanDel').onclick = () => { if (map.getLayer('scan')) { map.removeLayer('scan'); map.removeSource('scan'); } PLACED = null; $('scanGrp').hidden = true; setMove(false); };

  /* ---------- line it up by hand: drag on the map (user, 2026-11-14) ---------- */
  // The placed fit stays as it is; on top of it a shift, a turn about the sheet's middle and a size.
  // Drag the scan to shift it, Shift + drag to turn it; Turn and Size take typed values for fine steps.
  const llOf = (code, x, y) => code === '4326' ? [x, y] : toLL(code, x, y);
  const xyOf = (code, lng, lat) => code === '4326' ? [lng, lat] : toXY(code, lng, lat);
  const middle = () => apply(PLACED.T, PLACED.w / 2, PLACED.h / 2);
  function adjCorners() {
    const P = PLACED, [cx, cy] = middle(), t = P.adj.rot * Math.PI / 180, cs = Math.cos(t), sn = Math.sin(t);
    return [[0, 0], [P.w, 0], [P.w, P.h], [0, P.h]].map(([c, r]) => {
      const [x, y] = apply(P.T, c, r), dx = (x - cx) * P.adj.s, dy = (y - cy) * P.adj.s;   // clockwise turn on the map
      return llOf(P.code, cx + dx * cs + dy * sn + P.adj.tx, cy - dx * sn + dy * cs + P.adj.ty);
    });
  }
  function moved() { PLACED.corners = adjCorners(); const src = map.getSource('scan'); if (src) src.setCoordinates(PLACED.corners); syncAdj(); }
  function syncAdj() { if (!PLACED) return; $('scanRot').value = +PLACED.adj.rot.toFixed(2); $('scanSize').value = +(PLACED.adj.s * 100).toFixed(1); }
  let MOVE = false, mv = null;
  function setMove(on) {
    MOVE = on && !!PLACED; $('scanAdj').classList.toggle('active', MOVE); $('scanAdjBox').hidden = !MOVE;
    map.getCanvas().style.cursor = MOVE ? 'move' : '';
    if (MOVE) toast('Drag the scan to line it up with the map or the plotted beacons. Shift + drag turns it.');
  }
  $('scanAdj').onclick = () => setMove(!MOVE);
  $('scanRot').addEventListener('change', () => { const v = num($('scanRot').value); if (PLACED && v !== null) { PLACED.adj.rot = v; moved(); } });
  $('scanSize').addEventListener('change', () => { const v = num($('scanSize').value); if (PLACED && v && v >= 10 && v <= 1000) { PLACED.adj.s = v / 100; moved(); } });
  $('scanReset').onclick = () => { if (PLACED) { PLACED.adj = { tx: 0, ty: 0, rot: 0, s: 1 }; moved(); } };
  function onScan(pt) {   // is the screen point inside the placed sheet?
    const q = PLACED.corners.map(c => map.project(c)); let inside = false;
    for (let i = 0, j = 3; i < 4; j = i++) if ((q[i].y > pt.y) !== (q[j].y > pt.y) && pt.x < (q[j].x - q[i].x) * (pt.y - q[i].y) / (q[j].y - q[i].y) + q[i].x) inside = !inside;
    return inside;
  }
  function grabScan(e) {
    if (!MOVE || !PLACED || !$('scanVis').checked || !onScan(e.point)) return;
    e.preventDefault(); map.dragPan.disable();
    const [x, y] = xyOf(PLACED.code, e.lngLat.lng, e.lngLat.lat);
    mv = { x, y, turn: !!(e.originalEvent && e.originalEvent.shiftKey), a: { ...PLACED.adj } };
  }
  function dragScan(e) {
    if (!mv) return;
    const [x, y] = xyOf(PLACED.code, e.lngLat.lng, e.lngLat.lat), P = PLACED;
    if (mv.turn) {
      const [mx, my] = middle(), cx = mx + mv.a.tx, cy = my + mv.a.ty;
      const da = Math.atan2(x - cx, y - cy) - Math.atan2(mv.x - cx, mv.y - cy);
      P.adj.rot = mv.a.rot + da * 180 / Math.PI;
    } else { P.adj.tx = mv.a.tx + x - mv.x; P.adj.ty = mv.a.ty + y - mv.y; }
    moved();
  }
  function dropScan() { if (!mv) return; mv = null; map.dragPan.enable(); }
  // The map is made when the card first opens, so the handlers go on when a scan is first placed.
  let bound = false;
  function bindMove() {
    if (bound) return; bound = true;
    map.on('mousedown', grabScan); map.on('mousemove', dragScan); map.on('mouseup', dropScan);
    map.on('touchstart', e => { if (e.points && e.points.length === 1) grabScan(e); }); map.on('touchmove', dragScan); map.on('touchend', dropScan);
    window.addEventListener('mouseup', dropScan);
  }

  /* ---------- the sample: an Abia plan the user sent (2026-11-13) ---------- */
  // Amended survey of Mechanic Village at Ute Ezi, Umuahia South, 1:2500, scanned at 200 dpi. The plan gives
  // PBC 7954 as 167457.210 m N, 554404.433 m E: NTM Mid Belt. PBC 7954 sits at pixel (1698, 879) on the scan.
  $('geoSample').onclick = async () => {
    $('geoMsg').className = 'note'; $('geoMsg').textContent = 'Loading the sample…';
    document.querySelector('input[name=sys][value=ntm]').checked = true; document.querySelector('input[name=belt][value="26392"]').checked = true;
    document.querySelector('input[name=belt][value="26392"]').dispatchEvent(new Event('change'));
    panel.querySelector('input[name=geomethod][value=quick]').checked = true; method = 'quick';
    await readImage('/maps/samples/scanned-plan-sample.jpg', 'Sample: Mechanic Village, Ute Ezi (Abia)', 200);
    quickPt = { c: 1698, r: 879 }; $('gqPx').textContent = 'pixel 1698, 879';
    $('gqName').value = 'PBC7954'; $('gqX').value = '554404.433'; $('gqY').value = '167457.210'; $('gqScale').value = '2500'; $('gqDpi').value = '200'; $('gqRot').value = '0';
    $('geoMsg').className = 'note'; $('geoMsg').textContent = 'Sample filled in from the plan: PBC 7954 (167457.210 m N, 554404.433 m E, NTM Mid Belt), scale 1:2500, 200 dpi. Press Place on map.';
    showMethod(); draw();
  };

  /* ---------- the plans from the user's drive (2026-11-15) ---------- */
  // Six Abia scans (Downloads	iff). 004 and 005 have NTM Mid Belt grids printed on them: their grid crossings
  // were found on the full scans and fitted (004: 10 crossings, 0.70 m rms; 005: 17 crosses, 0.44 m rms), and saved
  // as GeoTIFFs, so they place themselves. The others are shrunk copies to place by hand: 007 and 008 have only
  // bearings from beacons with no coordinates on the sheet, 010 is on a local Aba grid (0 = an origin in the
  // town), 006 is a faded blueprint.
  const DRIVE_NOTE = {
    '006': 'Outline development plan, a faded blueprint: its grid labels cannot be read. Place it with control points.',
    '007': 'No grid: bearings and distances from PB 2363 (origin), whose coordinates are not on the sheet. Place it with control points once the beacons’ X / Y are known.',
    '008': 'No grid: bearings and distances between C4932…C2180 (origin Nigeria National), coordinates not on the sheet. Place it with control points once the beacons’ X / Y are known.',
    '010': 'A local Aba grid (metres E / W and N / S of an origin "0" in the town), not NTM: place it with control points.',
  };
  $('geoDrive').onchange = async () => {
    const f = $('geoDrive').value; if (!f) return;
    $('geoMsg').className = 'note'; $('geoMsg').textContent = 'Loading ' + f + '…';
    const ko = document.getElementById('kanoOnly');
    if (ko && ko.checked) { ko.checked = false; ko.dispatchEvent(new Event('change')); }   // Abia, not Kano
    document.querySelector('input[name=sys][value=ntm]').checked = true; document.querySelector('input[name=belt][value="26392"]').checked = true;
    document.querySelector('input[name=belt][value="26392"]').dispatchEvent(new Event('change'));
    const label = $('geoDrive').selectedOptions[0].textContent;
    try {
      if (/\.tif$/.test(f)) await readTiff(await (await fetch('/maps/samples/tiff/' + f)).arrayBuffer(), label);
      else {
        await readImage('/maps/samples/tiff/' + f, label, null);
        $('geoMsg').className = 'note'; $('geoMsg').textContent = DRIVE_NOTE[f.slice(0, 3)] || '';
      }
    } catch (err) { $('geoMsg').className = 'note bad'; $('geoMsg').textContent = 'Could not read ' + f + ': ' + err.message; }
    $('geoDrive').value = '';
  };

  /* ---------- the figures instead of the picture (user, 2026-11-14) ---------- */
  // Prototype of "read the plan, then plot it": the figures below are read off the sample sheet (what an AI
  // reader would hand the officer to confirm). Whole-circle bearings as printed, PBC7954 → … → PBC7954.
  // One figure was half covered on the scan: PBC7953 → PBC7954 is 214.19 m (read first as 244.19, which
  // left a 30 m miss along that very line). With 214.19 it closes to 0.014 m, area 214,226.75 m² (plan: 214,254.475).
  const ABIA = { start: 'PBC7954', x: '554404.433', y: '167457.210', lines: [
    ['PBC7955', 140, 36, 246.72], ['PBC7956', 143, 29, 42.44], ['PBC7957', 147, 56, 9.03], ['C2197', 162, 5, 178.07],
    ['C7958', 76, 12, 36.44], ['PBC7959', 155, 6, 105.50], ['PBC7960', 230, 14, 12.77], ['PBC7961', 230, 14, 52.67],
    ['PBC7962', 230, 19, 23.18], ['PBC7963', 230, 29, 28.43], ['PBC7949', 230, 26, 117.03], ['PBC7950', 306, 3, 235.28],
    ['PBC7951', 306, 4, 204.58], ['PBC7952', 291, 27, 207.29], ['PBC7953', 53, 20, 293.95], ['', 53, 20, 214.19]],
    // Where 11 of those beacons sit on the scan (pixel column, row), read off the sheet like the figures.
    px: { PBC7954: [1698, 879], PBC7955: [2160, 1503], PBC7957: [2242, 1646], C2197: [2395, 2179], PBC7959: [2633, 2466], PBC7963: [2335, 2698],
      PBC7949: [2034, 2928], PBC7950: [1461, 2448], PBC7951: [967, 2037], PBC7952: [371, 1759], PBC7953: [1137, 1254] } };
  // Whole-circle bearing → the traverse table's N/S d° m′ E/W.
  function quadrant(d, m) {
    const t = d * 60 + m, q = t <= 5400 ? ['N', t, 'E'] : t <= 10800 ? ['S', 10800 - t, 'E'] : t <= 16200 ? ['S', t - 10800, 'W'] : ['N', 21600 - t, 'W'];
    return { ns: q[0], d: Math.floor(q[1] / 60), m: q[1] % 60, s: 0, ew: q[2] };
  }
  $('geoRead').onclick = () => {
    if (!PLACED && FIT) $('geoPlace').click(); else close();
    setSystem('ntm', '26392');
    loadTraverse({ start: ABIA.start, x: ABIA.x, y: ABIA.y, lines: ABIA.lines.map(([to, d, m, dist]) => ({ ...quadrant(d, m), dist, to: to || undefined })) });
    // Automation: the beacons found on the scan + the same beacons just plotted = control points, so the scan
    // lines itself up on the figures (best fit: turn, scale, paper stretch). Dragging fine-tunes it after.
    const n = alignToBeacons(ABIA.px);
    toast(`16 lines read off the plan and plotted; the scan lined up on ${n} of its beacons. Check each figure against the sheet: PBC7953 → PBC7954 was half covered (214.19 m).`);
  };
  function alignToBeacons(px) {
    pts = Object.entries(px).map(([b, [c, r]]) => { const xy = knownXY(b); return { c, r, b, x: xy ? xy[0].toFixed(3) : '', y: xy ? xy[1].toFixed(3) : '' }; });
    method = 'cps'; panel.querySelector('input[name=geomethod][value=cps]').checked = true; showMethod(); renderPts(); solve();
    if (!FIT) return 0;
    place(gridCode(), FIT);
    return pts.filter(p => p.x !== '').length;
  }
  renderPts(); showMethod();
})();
