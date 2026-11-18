{{-- Application Plan: the Ministry's A4 template (user, 2026-11-15). Opened from the Survey Plan list's row menu
     (SurveyPlanUploadController::applicationPlan, user 2026-11-17).
     $ap: the Data Sheet entries (all optional; a blank prints as a line to fill by hand).
     $beacons: [['no' => 'SC/KN 1234', 'e' => 441344.78, 'n' => 1338968.26], ...] in order round the parcel, metres in
     $coordSystem (EPSG code; Minna / UTM Zone 32N when not given). Plotted in the frame to scale; the frame's centre
     ticks carry the coordinates. Behind the drawing the frame shows the map (satellite, our Kano imagery where we have
     it) at the same scale, centred on the parcel (user, 2026-11-17: "that big box ... will be the map").
     Header: the two Ministry logos; foot: the KLAES logo (user, 2026-11-17).
     $planImage: the uploaded plan (image or PDF, first page), shown in the frame by default (user, 2026-11-17);
     the bar switches the frame between it, the map drawing and plain. --}}
@php
  $ap = $ap ?? [];
  // Saved entries print in the form's style: the date as 17 November 2026, the amount in naira.
  if (! empty($ap['date'])) { try { $ap['date'] = \Illuminate\Support\Carbon::parse($ap['date'])->format('j F Y'); } catch (\Throwable $e) {} }
  if (isset($ap['amount_paid']) && is_numeric($ap['amount_paid'])) $ap['amount_paid'] = '₦' . number_format((float) $ap['amount_paid'], 2);
  $beacons = array_values(array_filter($beacons ?? [], fn ($b) => is_numeric($b['e'] ?? null) && is_numeric($b['n'] ?? null)));
  $v = fn ($k) => trim((string) ($ap[$k] ?? ''));

  // Frame in mm (as on the printed form); the drawing area keeps a margin inside it.
  $fw = 160; $fh = 120; $pad = 14;
  $scale = (int) ($ap['scale'] ?? 0);
  $plot = null;
  if (count($beacons) >= 1) {
    $es = array_column($beacons, 'e'); $ns = array_column($beacons, 'n');
    $ce = (min($es) + max($es)) / 2; $cn = (min($ns) + max($ns)) / 2;
    $spanE = max($es) - min($es); $spanN = max($ns) - min($ns);
    if ($scale <= 0 && count($beacons) === 1) $scale = 1000;
    if ($scale <= 0) {
      // The largest usual survey scale at which the parcel fits the frame.
      $scale = 100000;
      foreach ([250, 500, 1000, 1250, 2000, 2500, 5000, 10000, 25000, 50000, 100000] as $s) {
        if ($spanE * 1000 / $s <= $fw - 2 * $pad && $spanN * 1000 / $s <= $fh - 2 * $pad) { $scale = $s; break; }
      }
    }
    $mm = fn ($b) => [round($fw / 2 + ($b['e'] - $ce) * 1000 / $scale, 2), round($fh / 2 - ($b['n'] - $cn) * 1000 / $scale, 2)];
    $pts = array_map($mm, $beacons);
    // Area (m²) and side lengths, for the labels.
    $area = 0; $sides = [];
    $nb = count($beacons);
    for ($i = 0; $i < $nb; $i++) {
      $a = $beacons[$i]; $b = $beacons[($i + 1) % $nb];
      $area += $a['e'] * $b['n'] - $b['e'] * $a['n'];
      if ($nb > 2 || ($nb === 2 && $i === 0)) {
        $sides[] = ['d' => hypot($b['e'] - $a['e'], $b['n'] - $a['n']), 'p' => [($pts[$i][0] + $pts[($i + 1) % $nb][0]) / 2, ($pts[$i][1] + $pts[($i + 1) % $nb][1]) / 2]];
      }
    }
    // Side lengths sit just outside the parcel: pushed 2.5 mm from its centre, square to the side.
    $gx = array_sum(array_column($pts, 0)) / count($pts); $gy = array_sum(array_column($pts, 1)) / count($pts);
    foreach ($sides as $j => $sd) {
      $p0 = $pts[$j]; $p1 = $pts[($j + 1) % count($pts)];
      $nx = $p1[1] - $p0[1]; $ny = $p0[0] - $p1[0]; $len = hypot($nx, $ny) ?: 1;
      if (($sd['p'][0] - $gx) * $nx + ($sd['p'][1] - $gy) * $ny < 0) { $nx = -$nx; $ny = -$ny; }
      $sides[$j]['p'] = [$sd['p'][0] + 1.5 * $nx / $len, $sd['p'][1] + 2.5 * $ny / $len + 0.9];
      $sides[$j]['a'] = abs($nx) > abs($ny) * 1.5 ? ($nx > 0 ? 'start' : 'end') : 'middle';
    }
    $plot = ['pts' => $pts, 'ce' => $ce, 'cn' => $cn, 'area' => abs($area) / 2, 'sides' => $sides];
  }
  $scaleText = $scale > 0 ? number_format($scale) : '';
  $fmt = fn ($x) => number_format($x, 2, '.', ',');
  $line = fn ($k, $min = 0) => $v($k) !== '' ? '<span class="fill">' . e($v($k)) . '</span>' : '<span class="blank" style="min-width:' . $min . 'mm"></span>';
  $open = strtolower($v('ground_open'));
  $coordSystem = (string) ($coordSystem ?? '26332');
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Application Plan{{ $v('plan_no') !== '' ? ' ' . $v('plan_no') : '' }}</title>
<style>
  @page { size: A4 portrait; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; background: #e5e7eb; }
  body { font-family: "Times New Roman", Times, serif; color: #000; }
  .sheet { width: 210mm; height: 297mm; overflow: hidden; margin: 10mm auto; padding: 7mm 12mm 5mm; background: #fff; box-shadow: 0 2px 14px rgba(0,0,0,.18); }
  @media print { html, body { background: #fff; } .sheet { margin: 0; box-shadow: none; } .noprint { display: none !important; } }
  .head { display: grid; grid-template-columns: 17mm 1fr 17mm; align-items: center; gap: 3mm; }
  .head img { width: 17mm; height: 17mm; object-fit: contain; }
  h1 { margin: 0; text-align: center; font-size: 17pt; line-height: 1.2; font-weight: 700; }
  .top { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8mm; align-items: center; margin-top: 3mm; }
  .row .fill[title] { cursor: help; }
  .box { border: 1px solid #000; padding: 1.5mm 2mm; font-size: 10.5pt; line-height: 1.45; }
  .title { font-size: 15pt; font-weight: 700; text-align: center; }
  .rows { position: relative; margin-top: 3mm; font-size: 11.5pt; line-height: 1.6; }
  .ap-qr { position: absolute; right: 0; top: 1mm; width: 28mm; height: 28mm; border: 1px solid #000; padding: 1mm; box-sizing: border-box; }
  .ap-qr img { display: block; width: 100%; height: 100%; }
  .row { display: flex; align-items: baseline; gap: 1mm; width: 145mm; }
  .row > span:not(.fill):not(.blank) { white-space: nowrap; }
  .blank { flex: 1; border-bottom: 1px solid #000; height: 1em; display: inline-block; }
  .fill { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-bottom: 1px solid #000; padding: 0 1mm; font-family: Arial, sans-serif; font-size: 10.5pt; }
  .for { padding-left: 15mm; }
  .grow2 { flex: 2.4; display: flex; min-width: 0; }
  .for .blank, .for .fill { border-bottom-style: dotted; text-align: center; }
  .frame-wrap { position: relative; margin: 1mm auto 0; width: 186mm; }
  .frame-wrap svg { position: relative; display: block; width: 186mm; height: 140mm; overflow: visible; pointer-events: none; }
  /* The map sits exactly under the frame (viewBox origin -13 -12, 1 unit = 1 mm). */
  .ap-map { position: absolute; left: 13mm; top: 12mm; width: 160mm; height: 120mm; overflow: hidden; background: #fff; }
  #apMap { position: absolute !important; inset: 0; width: 100%; height: 100%; }
  .ap-map .maplibregl-ctrl-attrib { display: none; }
  .ap-upload { position: absolute; left: 13mm; top: 12mm; width: 160mm; height: 120mm; overflow: hidden; background: #fff; display: flex; align-items: center; justify-content: center; }
  .ap-upload img, .ap-upload canvas { max-width: 100%; max-height: 100%; width: auto; height: auto; display: block; }
  .ap-upload .msg { font: 9pt Arial, sans-serif; color: #555; }
  .halo { paint-order: stroke; stroke: #fff; stroke-width: .7; stroke-linejoin: round; }
  .map-credit { position: absolute; right: 14mm; top: 132.6mm; font: 6pt Arial, sans-serif; color: #555; }
  .scale { text-align: center; font-family: Arial, sans-serif; font-size: 9.5pt; margin-top: 0; }
  .scale .u { display: inline-block; min-width: 12mm; border-bottom: 1px solid #000; }
  .bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; margin-top: 3mm; font-size: 11.5pt; line-height: 1.45; }
  .bottom .row { width: auto; }
  .yn b { text-decoration: underline; }
  .bar { display: flex; justify-content: center; gap: 8px; padding: 8px; background: #0f172a; }
  .bar button { font: 600 13px system-ui, sans-serif; padding: 7px 14px; border-radius: 8px; border: 0; cursor: pointer; background: #2563eb; color: #fff; }
  .bar button.off { background: #334155; }
  .bar span { color: #cbd5e1; font: 13px system-ui, sans-serif; align-self: center; }
</style>
</head>
<body>
@php $frameMode = ! empty($planImage) ? 'upload' : ($plot ? 'map' : 'plain'); @endphp
<div class="bar noprint">
  @if ($plot || ! empty($planImage))
    <span>Frame:</span>
    @if (! empty($planImage))<button type="button" data-ap-bg="upload">Uploaded plan</button>@endif
    @if ($plot)<button type="button" data-ap-bg="map" class="{{ $frameMode === 'map' ? '' : 'off' }}">Map</button>@endif
    <button type="button" data-ap-bg="plain" class="off">Plain</button>
  @endif
  <button type="button" id="apPrint" onclick="window.apPrint ? window.apPrint() : window.print()">Print</button>
</div>
<div class="sheet">
  <div class="head">
    <img src="{{ asset('assets/logo/ministry1.jpg') }}" alt="Kano State coat of arms">
    <h1>MINISTRY OF LAND AND PHYSICAL PLANNING<br>KANO STATE</h1>
    <img src="{{ asset('assets/logo/ministry2.png') }}" alt="Kano State Ministry of Land and Physical Planning seal">
  </div>
  <div class="top">
    <div class="box">Code sur C of B<br>Serial No: {!! $v('serial_no') !== '' ? '<b>' . e($v('serial_no')) . '</b>' : '..........' !!}</div>
    <div class="title">Application Plan</div>
    <div class="box">No 2. Dr. Bala Mohd Road<br>P.M.B. 3083, Kano State</div>
  </div>

  <div class="rows">
    {{-- QR (user, 2026-11-17): the plan's ref, Plan No., plot and location, and its KLAES link. --}}
    @if (! empty($qrPayload) && ($qrImg = qr_data_uri($qrPayload, 220)) !== '')
      <div class="ap-qr"><img src="{{ $qrImg }}" alt="QR code"></div>
    @endif
    <div class="row for"><span>For</span>{!! $line('purpose') !!}<span>(PURPOSE)</span></div>
    <div class="row"><span>Name of Applicant:</span>{!! $line('applicant') !!}</div>
    <div class="row"><span>Address:</span>{!! $line('address') !!}</div>
    <div class="row"><span>Location:</span><span class="grow2">{!! $line('location', 30) !!}</span><span>Map Sheet:</span>{!! $line('map_sheet', 25) !!}<span>Scale:</span>{!! $scaleText !== '' ? '<span class="fill">1:' . $scaleText . '</span>' : '<span class="blank"></span>' !!}</div>
    <div class="row"><span>Plan No:</span>{!! $line('plan_no', 22) !!}<span>MS NO.</span>{!! $line('ms_no', 22) !!}<span>Reference</span>{!! $line('reference', 30) !!}</div>
  </div>

  {{-- The frame: 160 x 120 mm, centre ticks on each side, north arrow to the right. --}}
  <div class="frame-wrap">
    @if ($plot)
      <div class="ap-map" aria-hidden="true" @if ($frameMode !== 'map') style="visibility:hidden" @endif><div id="apMap"></div></div>
    @endif
    @if (! empty($planImage))
      <div class="ap-upload" id="apUpload" title="{{ $planImage['name'] }}">
        @if ($planImage['type'] === 'image')
          <img id="apUploadImg" src="{{ $planImage['url'] }}" alt="Uploaded survey plan">
        @else
          <canvas id="apUploadPdf"></canvas>
        @endif
      </div>
    @endif
    <svg viewBox="-13 -12 186 140" xmlns="http://www.w3.org/2000/svg" font-family="Arial, sans-serif">
      <rect x="0" y="0" width="{{ $fw }}" height="{{ $fh }}" fill="none" stroke="#000" stroke-width=".45"/>
      <line x1="{{ $fw / 2 }}" y1="-1.5" x2="{{ $fw / 2 }}" y2="1.5" stroke="#000" stroke-width=".3"/>
      <line x1="{{ $fw / 2 }}" y1="{{ $fh - 1.5 }}" x2="{{ $fw / 2 }}" y2="{{ $fh + 1.5 }}" stroke="#000" stroke-width=".3"/>
      <line x1="-1.5" y1="{{ $fh / 2 }}" x2="1.5" y2="{{ $fh / 2 }}" stroke="#000" stroke-width=".3"/>
      <line x1="{{ $fw - 1.5 }}" y1="{{ $fh / 2 }}" x2="{{ $fw + 1.5 }}" y2="{{ $fh / 2 }}" stroke="#000" stroke-width=".3"/>
      @if ($plot)
        <g id="apDraw" @if ($frameMode === 'upload') style="display:none" @endif>
        <text x="{{ $fw / 2 }}" y="-3.5" font-size="3" text-anchor="middle">{{ $fmt($plot['ce']) }} mE</text>
        <text x="{{ $fw / 2 }}" y="{{ $fh + 5.5 }}" font-size="3" text-anchor="middle">{{ $fmt($plot['ce']) }} mE</text>
        <text x="-3" y="{{ $fh / 2 - 1.5 }}" font-size="3" text-anchor="end">{{ $fmt($plot['cn']) }}</text>
        <text x="-3" y="{{ $fh / 2 + 2.5 }}" font-size="3" text-anchor="end">mN</text>
        <text x="{{ $fw + 3 }}" y="{{ $fh / 2 - 1.5 }}" font-size="3">{{ $fmt($plot['cn']) }}</text>
        <text x="{{ $fw + 3 }}" y="{{ $fh / 2 + 2.5 }}" font-size="3">mN</text>
        @if (count($plot['pts']) >= 3)
          <polygon points="{{ collect($plot['pts'])->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ') }}" fill="#fff" fill-opacity=".18" stroke="#fff" stroke-width="1.1" stroke-linejoin="round"/>
          <polygon points="{{ collect($plot['pts'])->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ') }}" fill="none" stroke="#d00" stroke-width=".55" stroke-linejoin="round"/>
        @elseif (count($plot['pts']) === 2)
          <polyline points="{{ collect($plot['pts'])->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ') }}" fill="none" stroke="#d00" stroke-width=".55"/>
        @endif
        @foreach ($plot['sides'] as $s)
          <text class="halo" x="{{ $s['p'][0] }}" y="{{ $s['p'][1] }}" font-size="2.4" text-anchor="{{ $s['a'] ?? 'middle' }}" fill="#111">{{ number_format($s['d'], 2) }} m</text>
        @endforeach
        @if ($v('plot_no') !== '' && count($plot['pts']) >= 3)
          <text class="halo" x="{{ $gx }}" y="{{ $gy + 1.2 }}" font-size="3.4" font-weight="700" text-anchor="middle" fill="#111">Plot {{ $v('plot_no') }}</text>
        @endif
        @foreach ($plot['pts'] as $i => $p)
          <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r=".9" fill="#fff" stroke="#000" stroke-width=".35"/>
          @php $dx = $p[0] - $gx; $dy = $p[1] - $gy; @endphp
          <text class="halo" x="{{ $p[0] + ($dx >= 0 ? 1.6 : -1.6) }}" y="{{ $p[1] + ($dy >= 0 ? 3.6 : -1.6) }}" text-anchor="{{ $dx >= 0 ? 'start' : 'end' }}" font-size="2.6" font-weight="700">{{ $beacons[$i]['no'] ?? ($i + 1) }}</text>
        @endforeach
        @if (count($beacons) >= 3)
          <text class="halo" x="{{ $fw / 2 }}" y="{{ $fh - 4 }}" font-size="2.8" text-anchor="middle">Area: {{ number_format($plot['area'], 2) }} m² ({{ number_format($plot['area'] / 10000, 4) }} ha)</text>
        @endif
        </g>
      @else
        <line x1="{{ $fw / 2 - 8 }}" y1="-3" x2="{{ $fw / 2 + 8 }}" y2="-3" stroke="#000" stroke-width=".3"/>
        <line x1="{{ $fw / 2 - 8 }}" y1="{{ $fh + 5 }}" x2="{{ $fw / 2 + 8 }}" y2="{{ $fh + 5 }}" stroke="#000" stroke-width=".3"/>
        <line x1="-13" y1="{{ $fh / 2 + 1 }}" x2="-3" y2="{{ $fh / 2 + 1 }}" stroke="#000" stroke-width=".3"/>
        <line x1="{{ $fw + 3 }}" y1="{{ $fh / 2 + 1 }}" x2="{{ $fw + 13 }}" y2="{{ $fh / 2 + 1 }}" stroke="#000" stroke-width=".3"/>
      @endif
      {{-- North arrow --}}
      <g transform="translate({{ $fw + 7 }} 0)">
        <text x="0" y="0" font-size="4.5" font-weight="700" text-anchor="middle">N</text>
        <polygon points="0,1.5 -2,6 2,6" fill="#000"/>
        <line x1="0" y1="5" x2="0" y2="14" stroke="#000" stroke-width=".9"/>
      </g>
    </svg>
    @if ($plot)
      <div class="map-credit" id="apCredit" @if ($frameMode !== 'map') style="visibility:hidden" @endif></div>
    @endif
    <div class="scale">Scale 1:<span class="u">{{ $scaleText }}</span>@if ($plot && isset($coordSystemLabel)) &nbsp;·&nbsp; Coordinates: {{ $coordSystemLabel }}@endif</div>
  </div>

  <div class="bottom">
    <div>
      <div class="yn">The ground is open {!! $open === 'yes' ? '<b>Yes</b>/No' : ($open === 'no' ? 'Yes/<b>No</b>' : 'Yes/No') !!}</div>
      <div>if No it lies on:</div>
      <div class="row"><span>(i) Layout Plan No.</span>{!! $line('layout_plan_no') !!}</div>
      <div class="row"><span>(ii) Government Item No:</span>{!! $line('government_item_no') !!}</div>
      <div class="row"><span>(iii) Certificate of Occupancy No:</span>{!! $line('cofo_no') !!}</div>
      <div class="row"><span>(iv) Application Plan No:</span>{!! $line('application_plan_no') !!}</div>
    </div>
    <div>
      <div class="row"><span>Surveyed by:</span>{!! $line('surveyed_by') !!}</div>
      <div class="row"><span>Drawn by:</span>{!! $line('drawn_by') !!}</div>
      <div class="row"><span>Checked by:</span>{!! $line('checked_by') !!}</div>
      <div class="row"><span>Amount Paid:</span>{!! $line('amount_paid') !!}</div>
      <div class="row"><span>Receipt No:</span>{!! $line('receipt_no') !!}</div>
      <div class="row"><span>Passed by:</span>{!! $line('passed_by') !!}</div>
      <div class="row"><span>Rank:</span>{!! $line('rank') !!}</div>
      <div class="row"><span>Date:</span>{!! $line('date') !!}</div>
    </div>
  </div>
  @include('partials._klaes_print_logo', ['klaesLogoStyle' => 'padding-top:2mm;', 'klaesLogoHeight' => '10mm'])
</div>
<script>
(function () {
  // Print waits for everything drawn in the frame (map tiles, the uploaded plan); each adds a promise here.
  var waits = [], btn = document.getElementById('apPrint');
  window.apWaitFor = function (p) { waits.push(p); };
  window.apPrint = function () {
    if (btn) btn.textContent = 'Preparing…';
    Promise.all(waits).then(function () { if (btn) btn.textContent = 'Print'; window.print(); });
  };
  // Frame: the uploaded plan, the map with the parcel drawn, or plain.
  document.querySelectorAll('[data-ap-bg]').forEach(function (b) {
    b.addEventListener('click', function () {
      var m = b.dataset.apBg, el;
      if ((el = document.querySelector('.ap-map'))) el.style.visibility = m === 'map' ? '' : 'hidden';
      if ((el = document.getElementById('apCredit'))) el.style.visibility = m === 'map' ? '' : 'hidden';
      if ((el = document.getElementById('apUpload'))) el.style.display = m === 'upload' ? '' : 'none';
      if ((el = document.getElementById('apDraw'))) el.style.display = m === 'upload' ? 'none' : '';
      document.querySelectorAll('[data-ap-bg]').forEach(function (x) { x.classList.toggle('off', x !== b); });
    });
  });
})();
</script>
@if (! empty($planImage))
  @if ($planImage['type'] === 'image')
<script>
(function () {
  var img = document.getElementById('apUploadImg');
  window.apWaitFor(new Promise(function (res) {
    if (img.complete) return res();
    img.addEventListener('load', res);
    img.addEventListener('error', function () { img.replaceWith(Object.assign(document.createElement('span'), { className: 'msg', textContent: 'The uploaded plan could not be loaded.' })); res(); });
  }));
})();
</script>
  @else
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
  // A PDF plan: its first page, drawn sharp enough for print (about 3x the frame size).
  var cv = document.getElementById('apUploadPdf');
  var fail = function () { cv.replaceWith(Object.assign(document.createElement('span'), { className: 'msg', textContent: 'The uploaded plan could not be shown.' })); };
  if (!window.pdfjsLib) { fail(); return; }
  pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
  window.apWaitFor(pdfjsLib.getDocument({ url: @json($planImage['url']), withCredentials: true }).promise
    .then(function (doc) { return doc.getPage(1); })
    .then(function (page) {
      var v1 = page.getViewport({ scale: 1 }), box = cv.parentNode;
      var k = Math.min(box.clientWidth / v1.width, box.clientHeight / v1.height) * 3;
      var vp = page.getViewport({ scale: k });
      cv.width = vp.width; cv.height = vp.height;
      return page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise;
    })
    .catch(fail));
})();
</script>
  @endif
@endif
@if ($plot)
<link rel="stylesheet" href="{{ asset('maps/vendor/maplibre-gl.css') }}">
<script src="{{ asset('maps/vendor/maplibre-gl.js') }}"></script>
<script src="{{ asset('maps/vendor/proj4.js') }}"></script>
<script>
(function () {
  // The map under the frame: centred on the parcel, at the plan's scale (1 mm on paper = scale / 1000 m).
  var sys = 'EPSG:' + @json($coordSystem), scale = {{ (int) $scale }}, ce = {{ $plot['ce'] }}, cn = {{ $plot['cn'] }};
  var MINNA = '+a=6378249.145 +rf=293.465 +towgs84=-92,-93,122,0,0,0,0 +units=m +no_defs';
  proj4.defs('EPSG:26391', '+proj=tmerc +lat_0=4 +lon_0=4.5 +k=0.99975 +x_0=230738.26 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26392', '+proj=tmerc +lat_0=4 +lon_0=8.5 +k=0.99975 +x_0=670553.98 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26393', '+proj=tmerc +lat_0=4 +lon_0=12.5 +k=0.99975 +x_0=1110369.7 +y_0=0 ' + MINNA);
  proj4.defs('EPSG:26332', '+proj=utm +zone=32 ' + MINNA);
  proj4.defs('EPSG:32632', '+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs');
  var box = document.getElementById('apMap'), credit = document.getElementById('apCredit');
  // Print waits until the map is drawn (tiles still loading would print half a map), 20 s at most.
  var done;
  window.apWaitFor(new Promise(function (res) { done = res; setTimeout(res, 20000); }));
  if (!box || !window.maplibregl || !window.proj4 || !proj4.defs(sys)) { if (box) box.remove(); done(); return; }
  var ll = proj4(sys, 'EPSG:4326', [ce, cn]);
  // Metres per CSS pixel on paper (96 px = 25.4 mm), then the web-mercator zoom that gives it at this latitude.
  var mpp = scale / 1000 * 25.4 / 96;
  var zoom = Math.log2(2 * Math.PI * 6378137 * Math.cos(ll[1] * Math.PI / 180) / (512 * mpp));
  var inside = function (b) { return b && ll[0] >= b[0] && ll[0] <= b[2] && ll[1] >= b[1] && ll[1] <= b[3]; };
  // Our own Kano imagery where it covers the parcel, over the online satellite basemap.
  var base = @json(url('/'));
  var sets = [{ dir: '/tiles/kano-imagery', name: 'Kano imagery 0.5 m' }, { dir: '/tiles/kano-metro-ortho', name: 'Kano Metro orthophoto 7 cm' }];
  Promise.all(sets.map(function (s) {
    return fetch(base + s.dir + '/tiles.json').then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; })
      .then(function (j) { s.info = j; return s; });
  })).then(function () {
    var style = { version: 8, sources: {
      esri: { type: 'raster', tileSize: 256, maxzoom: 19, tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'] } },
      layers: [{ id: 'bg', type: 'background', paint: { 'background-color': '#fff' } }, { id: 'esri', type: 'raster', source: 'esri' }] };
    var used = ['Esri World Imagery'];
    sets.forEach(function (s, i) {
      if (!s.info || !inside(s.info.bounds)) return;
      style.sources['k' + i] = { type: 'raster', tileSize: 256, bounds: s.info.bounds, minzoom: s.info.minzoom, maxzoom: s.info.maxzoom,
        tiles: [base + s.dir + '/{z}/{x}/{y}.' + (s.info.format || 'webp')] };
      style.layers.push({ id: 'k' + i, type: 'raster', source: 'k' + i });
      used.unshift(s.name);
    });
    var map = new maplibregl.Map({ container: box, style: style, center: ll, zoom: zoom, interactive: false, attributionControl: false,
      pixelRatio: Math.max(3, window.devicePixelRatio || 1), canvasContextAttributes: { preserveDrawingBuffer: true }, fadeDuration: 0 });
    if (credit) credit.textContent = 'Map: ' + used.join(' / ');
    // Print once every tile is drawn (the canvas keeps its picture for printing: preserveDrawingBuffer).
    map.once('idle', done);
    map.on('error', function () { /* a missing tile leaves that square white; the drawing still prints */ });
  });
})();
</script>
@endif

</body>
</html>
