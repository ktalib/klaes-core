/*
 | Survey Plan Check card: a scanned plan (or GeoTIFF) on the map. Ported from the prototype's
 | /maps/survey-plan-scan.js to the live card (user, 2026-11-15: "bring the tiff scanned files feature to
 | live, one file only as a sample until the Kano ones come").
 |
 | A GeoTIFF places itself (its own coordinates). A plain scan (TIFF, JPG, PNG) is placed by hand:
 |   1. One beacon + scale: click a beacon on the scan, type its X / Y, the plan's scale and the scan's dpi.
 |   2. Control points: click 2 or more beacons on the scan and type their X / Y (2 = scale + turn, 3+ = best fit).
 | On the map: see-through, lines only (paper removed), Move (drag, Shift + drag turns), Turn / Size, Remove.
 | The file is read in this browser; nothing is uploaded or saved.
 |
 | Runs inside the card: survey-plan-check.js hands over its map and helpers as window.SPC_SCAN_API and fires
 | spc:ready. Markup: _plan_check.blade.php (#spc-scanBtn, #spc-scanGrp, #spc-geoPanel). The prototype's demos
 | (the Mechanic Village sample, "Read figures from the plan") stay in the prototype.
 */
(function () {
  'use strict';

  function start(S) {
    const { root, $, toLL, toXY, gridCode, GRID_NAME, REG_BY, key, fmt, esc, setRef, inKano, OUR } = S;
    const panel = $('geoPanel');
    if (!panel) return;
    OUR.push('scan');

    // Projections a GeoTIFF may carry, beyond the card's own.
    const MINNA = '+a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs';
    proj4.defs('EPSG:26331', '+proj=utm +zone=31 ' + MINNA);
    proj4.defs('EPSG:32631', '+proj=utm +zone=31 +datum=WGS84 +units=m +no_defs');
    const KNOWN = ['26391', '26392', '26393', '26331', '26332', '32631', '32632', '4326'];
    const NAME = c => GRID_NAME[c] || { 26331: 'Minna / UTM 31N (EPSG:26331)', 32631: 'UTM 31N, WGS 84 (EPSG:32631)', 4326: 'Latitude / longitude, WGS 84 (EPSG:4326)' }[c] || 'EPSG:' + c;
    const MAXTEX = 4096;   // the map texture limit we keep to; bigger scans are shrunk for the map only

    // The sample, until the Kano plans come (user, 2026-11-15: one file only). An Abia layout plan with an NTM
    // Mid Belt grid printed on it; its 17 grid crossings were fitted (0.44 m rms) and saved as a GeoTIFF, so it
    // places itself. It is outside Kano: the map is opened up while it is on, and closed again when removed.
    const SAMPLE = { file: '005-isieke-ibeku-umuahia.tif', label: 'Sample: Isieke Ibeku layout, Umuahia (Abia)' };
    const SAMPLE_URL = (root.dataset.scanSamples || '/maps/samples/tiff') + '/' + SAMPLE.file;

    const map = () => S.map();
    let SRC = null;          // { canvas, w, h, name, dpi }
    let PLACED = null;       // { code, T, corners, w, h, adj, canvas }
    let pts = [];            // control points: { c, r, b, x, y }
    let quickPt = null;      // the one beacon clicked (quick method)
    let method = 'quick';
    let savedBounds = null;  // the card's Kano bounds while a scan outside Kano is on the map

    const cv = $('gvCanvas'), ctx = cv.getContext('2d');
    const num = v => { const t = String(v ?? '').replace(/[,\s]/g, ''), n = Number(t); return t === '' || !isFinite(n) ? null : n; };

    /* ---------- a short message over the map ---------- */
    let toastTimer = null;
    function toast(text) {
      let t = $('scanToast');
      if (!t) { t = document.createElement('div'); t.id = 'spc-scanToast'; t.className = 'spc-scan-toast'; $('mapbox').appendChild(t); }
      t.textContent = text; t.hidden = false;
      clearTimeout(toastTimer); toastTimer = setTimeout(() => { t.hidden = true; }, 6000);
    }

    /* ---------- the panel ---------- */
    const open = () => { panel.hidden = false; document.body.classList.add('spc-noscroll'); sizeCanvas(); fitView(); draw(); };
    const close = () => { panel.hidden = true; if (!document.querySelector('.spc-mapbox.full')) document.body.classList.remove('spc-noscroll'); };
    $('scanBtn').onclick = open;
    $('geoClose').onclick = close;
    panel.addEventListener('click', e => { if (e.target === panel) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { e.stopPropagation(); close(); } }, true);
    panel.querySelectorAll('input[name=spc_geomethod]').forEach(r => r.addEventListener('change', () => { method = r.value; showMethod(); draw(); }));
    function showMethod() {
      $('geoQuick').hidden = method !== 'quick'; $('geoCps').hidden = method !== 'cps';
      $('gvHint').textContent = !SRC ? 'Open a scan first.' : method === 'quick' ? 'Click the beacon whose coordinates you know (zoom in with the mouse wheel).' : 'Click a beacon on the scan, then type its X / Y. Two or more points.';
      solve();
    }
    const showSys = () => { $('geoSys').textContent = NAME(gridCode()); solve(); };
    root.querySelectorAll('input[data-sys], input[name=spc_belt]').forEach(r => { r.addEventListener('change', showSys); r.addEventListener('click', () => setTimeout(showSys)); });
    $('geoSys').textContent = NAME(gridCode());

    /* ---------- opening a file ---------- */
    const msg = (text, cls = '') => { const m = $('geoMsg'); m.className = 'spc-note ' + cls; m.textContent = text; };
    $('geoFile').addEventListener('change', async e => {
      const f = e.target.files[0]; if (!f) return;
      msg('Reading ' + f.name + '…');
      try {
        if (/\.tiff?$/i.test(f.name) || f.type === 'image/tiff') await readTiff(await f.arrayBuffer(), f.name);
        else await readImage(URL.createObjectURL(f), f.name, null);
      } catch (err) { msg('Could not read the file: ' + err.message, 'bad'); }
      e.target.value = '';
    });
    $('geoSample').onclick = async () => {
      msg('Loading the sample…');
      try {
        const res = await fetch(SAMPLE_URL);
        if (!res.ok) throw new Error('the sample is not on the server');
        await readTiff(await res.arrayBuffer(), SAMPLE.label);
      } catch (err) { msg('Could not load the sample: ' + err.message, 'bad'); }
    };
    async function readImage(url, name, dpi) {
      const img = await new Promise((ok, no) => { const i = new Image(); i.onload = () => ok(i); i.onerror = () => no(new Error('not an image')); i.src = url; });
      const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; c.getContext('2d').drawImage(img, 0, 0);
      loaded({ canvas: c, w: c.width, h: c.height, name, dpi }, null);
    }
    async function readTiff(buf, name) {
      try { return await readGeoTiff(buf, name); }
      catch (err) { return readUtif(buf, name); }   // scanners often write a bad tag geotiff.js refuses
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
      let geo = null;
      if (fd.ModelPixelScale && fd.ModelTiepoint) {
        const k = im.getGeoKeys() || {}, code = String(k.ProjectedCSTypeGeoKey || k.GeographicTypeGeoKey || '');
        const [ox, oy] = im.getOrigin(), [rx, ry] = im.getResolution();
        geo = { code, T: { a: rx, b: 0, tx: ox, d: 0, e: ry, ty: oy } };
      }
      loaded({ canvas: c, w, h, name, dpi }, geo);
    }
    function loaded(src, geo) {
      SRC = src; pts = []; quickPt = null; renderPts();
      $('gvName').textContent = `${src.name} · ${src.w.toLocaleString()} × ${src.h.toLocaleString()} px${src.dpi ? ' · ' + Math.round(src.dpi) + ' dpi' : ''}`;
      if (src.dpi) $('gqDpi').value = Math.round(src.dpi);
      if (geo && KNOWN.includes(geo.code)) {
        msg(`GeoTIFF: it carries its own coordinates (${NAME(geo.code)}). Placed on the map as it is.`, 'ok');
        place(geo.code, geo.T); close(); return;
      }
      msg(geo ? `GeoTIFF in ${geo.code ? 'EPSG:' + geo.code : 'an unknown system'}, which this check does not read yet: place it by hand below.`
        : 'A plain scan: it has no coordinates of its own. Place it with one beacon + scale, or with control points.');
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
      if (!SRC) { ctx.fillStyle = '#94a3b8'; ctx.font = `${14 * (devicePixelRatio || 1)}px system-ui`; ctx.textAlign = 'center'; ctx.fillText('Open a scanned plan (TIFF, JPG or PNG) or load the sample', cv.width / 2, cv.height / 2); return; }
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
      body.innerHTML = pts.map((p, i) => `<tr><td>${i + 1}</td><td class="spc-mono">${Math.round(p.c)}, ${Math.round(p.r)}</td>`
        + `<td><input type="text" class="b" data-i="${i}" value="${esc(p.b)}" placeholder="Beacon No." aria-label="Beacon No."></td>`
        + `<td><input type="text" class="x" data-i="${i}" value="${esc(p.x)}" inputmode="decimal" placeholder="X (Easting)" aria-label="X (Easting)"></td>`
        + `<td><input type="text" class="y" data-i="${i}" value="${esc(p.y)}" inputmode="decimal" placeholder="Y (Northing)" aria-label="Y (Northing)"></td>`
        + `<td class="spc-mono res" data-i="${i}">—</td><td><button type="button" class="spc-geo-ib" data-del="${i}" aria-label="Remove" title="Remove"><i class="fas fa-xmark"></i></button></td></tr>`).join('')
        || '<tr><td colspan="7" class="spc-note" style="padding:10px">Click a beacon on the scan to add a point.</td></tr>';
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
      const o = [...S.sv(), ...S.refs()].find(o => o.beacon && key(o.beacon) === key(b) && o.x !== undefined && !o.bad);
      if (o) return [o.x, o.y];
      const reg = REG_BY.get(key(b)); return reg ? toXY(gridCode(), reg.lon, reg.lat) : null;
    }
    $('gcClear').onclick = () => { pts = []; renderPts(); solve(); draw(); };
    ['gqName', 'gqX', 'gqY', 'gqScale', 'gqDpi', 'gqRot'].forEach(id => $(id).addEventListener('input', () => { solve(); draw(); }));

    /* ---------- the fit: image pixel (c, r) -> grid X / Y ---------- */
    // T: X = a·c + b·r + tx ; Y = d·c + e·r + ty  (r counts down the scan).
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
    const apply = (T, c, r) => [T.a * c + T.b * r + T.tx, T.d * c + T.e * r + T.ty];
    function solve() {
      FIT = null; const out = $('geoFit'); out.className = 'spc-geo-fit';
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
        if (!FIT) { out.className = 'spc-geo-fit bad'; out.textContent = 'These points do not fit: two of them are in the same place.'; $('geoPlace').disabled = true; return; }
        let ss = 0;
        ok.forEach(p => { const [x, y] = apply(FIT, p.c, p.r), m = Math.hypot(x - num(p.x), y - num(p.y)); ss += m * m; const td = $('gcRows').querySelector(`.res[data-i="${pts.indexOf(p)}"]`); if (td) td.textContent = fmt(m, 2) + ' m'; });
        const pix = Math.sqrt(Math.abs(FIT.a * FIT.e - FIT.b * FIT.d)), turn = Math.atan2(-FIT.b, FIT.a) * 180 / Math.PI;
        const rms = ok.length > 2 ? Math.sqrt(ss / ok.length) : null;
        out.className = 'spc-geo-fit' + (rms !== null && rms > 2 ? ' bad' : '');
        out.innerHTML = `<b>${ok.length} points.</b> One pixel = ${fmt(pix, 4)} m, turned ${fmt(turn, 2)}°.` + (rms !== null ? ` Average miss ${fmt(rms, 2)} m${rms > 2 ? ': check the points with the biggest miss' : ''}.` : ' Add a third point to check the fit.');
      }
      $('geoPlace').disabled = !FIT;
    }

    /* ---------- on the map ---------- */
    $('geoPlace').onclick = () => {
      if (!FIT) return;
      place(gridCode(), FIT);
      // One beacon + scale: that beacon becomes the reference coordinate when section 2 is empty.
      if (method === 'quick' && !S.refs().some(o => !o.empty)) setRef($('gqName').value.trim().toUpperCase(), $('gqX').value.trim(), $('gqY').value.trim());
      close();
    };
    // The card keeps its map to Kano. A scan outside it (the Abia sample) opens the map up while it is on.
    function openBounds(mid) {
      const m = map(); if (!m) return;
      if (inKano(mid)) { closeBounds(); return; }
      if (!savedBounds) { savedBounds = m.getMaxBounds(); m.setMaxBounds(null); }
      toast('This plan is outside Kano: the map is opened up while it is on, and closes back to Kano when it is removed.');
    }
    function closeBounds() { const m = map(); if (m && savedBounds) { m.setMaxBounds(savedBounds); savedBounds = null; } }
    function place(code, T) {
      const ll = code === '4326' ? (x, y) => [x, y] : (x, y) => toLL(code, x, y);
      const corners = [[0, 0], [SRC.w, 0], [SRC.w, SRC.h], [0, SRC.h]].map(([c, r]) => ll(...apply(T, c, r)));
      PLACED = { code, T, corners, w: SRC.w, h: SRC.h, name: SRC.name, adj: { tx: 0, ty: 0, rot: 0, s: 1 } };
      syncAdj();
      let c = SRC.canvas;
      if (Math.max(c.width, c.height) > MAXTEX) { const k = MAXTEX / Math.max(c.width, c.height), s = document.createElement('canvas'); s.width = Math.round(c.width * k); s.height = Math.round(c.height * k); s.getContext('2d').drawImage(c, 0, 0, s.width, s.height); c = s; }
      PLACED.canvas = c; INK = null;
      // Lines only by default: yellow over the satellite, dark over the street map or the plain backdrop.
      $('scanPaper').value = $('baseOn').checked && S.base() === 'satellite' ? 'yellow' : 'dark';
      paperOut(c, $('scanPaper').value).toBlob(blob => {
        const url = URL.createObjectURL(blob);
        const show = () => {
          const m = map(), s = m.getSource('scan');
          if (s) s.updateImage({ url, coordinates: corners });
          else { m.addSource('scan', { type: 'image', url, coordinates: corners }); m.addLayer({ id: 'scan', type: 'raster', source: 'scan', paint: { 'raster-opacity': +$('scanOp').value / 100, 'raster-fade-duration': 0 } }, m.getLayer('links') ? 'links' : undefined); }
          m.setLayoutProperty('scan', 'visibility', 'visible');
          bindMove();
          const mid = corners.reduce((a, x) => [a[0] + x[0] / 4, a[1] + x[1] / 4], [0, 0]);
          openBounds(mid);
          const b = new maplibregl.LngLatBounds(); corners.forEach(x => b.extend(x)); m.fitBounds(b, { padding: 40, duration: 700 });
          $('scanGrp').hidden = false; $('scanVis').checked = true;
          $('scanTag').textContent = SRC.name;
          tocScan();
        };
        const m = map();
        if (m && S.ready()) show();
        else if (m) m.once('load', show);
        else toast('Open the map first, then place the scan.');
      }, 'image/png');
    }

    /* ---------- lines only: take the paper away ---------- */
    // Each pixel is compared with the paper around it (brightest value per 24 px block, smoothed), so a tint or a
    // lighter band across the sheet still counts as paper. What is darker than its paper is ink: kept, in one
    // colour, as solid as it is dark; the paper goes see-through.
    let INK = null;
    function inkOf(c) {
      const w = c.width, h = c.height, px = c.getContext('2d').getImageData(0, 0, w, h).data, L = new Uint8Array(w * h);
      for (let i = 0, j = 0; i < w * h; i++, j += 4) L[i] = (px[j] * 77 + px[j + 1] * 150 + px[j + 2] * 29) >> 8;
      const B = 24, bw = Math.ceil(w / B), bh = Math.ceil(h / B), M = new Float32Array(bw * bh);
      for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) { const v = L[y * w + x], k = ((y / B) | 0) * bw + ((x / B) | 0); if (v > M[k]) M[k] = v; }
      const Sm = new Float32Array(bw * bh);
      for (let by = 0; by < bh; by++) for (let bx = 0; bx < bw; bx++) {
        let t = 0, n = 0;
        for (let dy = -1; dy <= 1; dy++) for (let dx = -1; dx <= 1; dx++) { const yy = by + dy, xx = bx + dx; if (yy >= 0 && xx >= 0 && yy < bh && xx < bw) { t += M[yy * bw + xx]; n++; } }
        Sm[by * bw + bx] = t / n;
      }
      const A = new Uint8Array(w * h);
      for (let y = 0; y < h; y++) {
        const fy = Math.min(bh - 1, Math.max(0, y / B - 0.5)), y0 = fy | 0, y1 = Math.min(bh - 1, y0 + 1), ty = fy - y0;
        for (let x = 0; x < w; x++) {
          const fx = Math.min(bw - 1, Math.max(0, x / B - 0.5)), x0 = fx | 0, x1 = Math.min(bw - 1, x0 + 1), tx = fx - x0;
          const bg = (Sm[y0 * bw + x0] * (1 - tx) + Sm[y0 * bw + x1] * tx) * (1 - ty) + (Sm[y1 * bw + x0] * (1 - tx) + Sm[y1 * bw + x1] * tx) * ty;
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
      const m = map();
      if (!PLACED || !PLACED.canvas || !m || !m.getSource('scan')) return;
      paperOut(PLACED.canvas, $('scanPaper').value).toBlob(blob => m.getSource('scan').updateImage({ url: URL.createObjectURL(blob), coordinates: PLACED.corners }), 'image/png');
    };
    $('scanOp').addEventListener('input', () => { const m = map(); if (m && m.getLayer('scan')) m.setPaintProperty('scan', 'raster-opacity', +$('scanOp').value / 100); });
    function setVisible(on) {
      const m = map(); if (m && m.getLayer('scan')) m.setLayoutProperty('scan', 'visibility', on ? 'visible' : 'none');
      $('scanVis').checked = on; const t = root.querySelector('#spc-tocScan input[type=checkbox]'); if (t) t.checked = on;
    }
    $('scanVis').onchange = () => setVisible($('scanVis').checked);
    function removeScan() {
      const m = map();
      if (m && m.getLayer('scan')) { m.removeLayer('scan'); m.removeSource('scan'); }
      PLACED = null; $('scanGrp').hidden = true; setMove(false); closeBounds(); tocScan();
    }
    $('scanDel').onclick = removeScan;

    /* ---------- the Layers panel's "Scanned plans" group ---------- */
    function tocScan() {
      const box = root.querySelector('#spc-tocScan'); if (!box) return;
      if (!PLACED) { box.innerHTML = '<p class="toc-none">None on this map yet. <button type="button" class="spc-linkbtn" data-scan-open>Put a scanned plan on the map</button></p>'; return; }
      box.innerHTML = `<div class="toc-row"><label><input type="checkbox" ${$('scanVis').checked ? 'checked' : ''}> <span>${esc(PLACED.name)}</span></label>`
        + ` <button type="button" class="spc-linkbtn" data-scan-zoom>Zoom</button> <button type="button" class="spc-linkbtn" data-scan-del>Remove</button></div>`;
      box.querySelector('input[type=checkbox]').onchange = e => setVisible(e.target.checked);
    }
    root.addEventListener('click', e => {
      if (e.target.closest('[data-scan-open]')) { e.preventDefault(); open(); }
      else if (e.target.closest('[data-scan-del]')) { e.preventDefault(); removeScan(); }
      else if (e.target.closest('[data-scan-zoom]') && PLACED) { e.preventDefault(); const b = new maplibregl.LngLatBounds(); PLACED.corners.forEach(x => b.extend(x)); map().fitBounds(b, { padding: 40, duration: 500 }); }
    });
    // survey-plan-check.js rebuilds the Layers panel; it calls this to refill the group.
    window.SpcScanToc = tocScan;
    tocScan();

    /* ---------- line it up by hand: drag on the map ---------- */
    // The placed fit stays as it is; on top of it a shift, a turn about the sheet's middle and a size.
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
    function moved() { PLACED.corners = adjCorners(); const src = map().getSource('scan'); if (src) src.setCoordinates(PLACED.corners); syncAdj(); }
    function syncAdj() { if (!PLACED) return; $('scanRot').value = +PLACED.adj.rot.toFixed(2); $('scanSize').value = +(PLACED.adj.s * 100).toFixed(1); }
    let MOVE = false, mv = null;
    function setMove(on) {
      MOVE = on && !!PLACED; $('scanAdj').classList.toggle('active', MOVE); $('scanAdjBox').hidden = !MOVE;
      const m = map(); if (m) m.getCanvas().style.cursor = MOVE ? 'move' : '';
      if (MOVE) toast('Drag the scan to line it up with the map or the plotted beacons. Shift + drag turns it.');
    }
    $('scanAdj').onclick = () => setMove(!MOVE);
    $('scanRot').addEventListener('change', () => { const v = num($('scanRot').value); if (PLACED && v !== null) { PLACED.adj.rot = v; moved(); } });
    $('scanSize').addEventListener('change', () => { const v = num($('scanSize').value); if (PLACED && v && v >= 10 && v <= 1000) { PLACED.adj.s = v / 100; moved(); } });
    $('scanReset').onclick = () => { if (PLACED) { PLACED.adj = { tx: 0, ty: 0, rot: 0, s: 1 }; moved(); } };
    function onScan(pt) {   // is the screen point inside the placed sheet?
      const q = PLACED.corners.map(c => map().project(c)); let inside = false;
      for (let i = 0, j = 3; i < 4; j = i++) if ((q[i].y > pt.y) !== (q[j].y > pt.y) && pt.x < (q[j].x - q[i].x) * (pt.y - q[i].y) / (q[j].y - q[i].y) + q[i].x) inside = !inside;
      return inside;
    }
    function grabScan(e) {
      if (!MOVE || !PLACED || !$('scanVis').checked || !onScan(e.point)) return;
      e.preventDefault(); map().dragPan.disable();
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
    function dropScan() { if (!mv) return; mv = null; map().dragPan.enable(); }
    // The map is made when the card first opens, so the handlers go on when a scan is first placed.
    let bound = false;
    function bindMove() {
      if (bound) return; bound = true;
      const m = map();
      m.on('mousedown', grabScan); m.on('mousemove', dragScan); m.on('mouseup', dropScan);
      m.on('touchstart', e => { if (e.points && e.points.length === 1) grabScan(e); }); m.on('touchmove', dragScan); m.on('touchend', dropScan);
      window.addEventListener('mouseup', dropScan);
    }

    renderPts(); showMethod();
  }

  if (window.SPC_SCAN_API) start(window.SPC_SCAN_API);
  else document.addEventListener('spc:ready', () => start(window.SPC_SCAN_API), { once: true });
})();
