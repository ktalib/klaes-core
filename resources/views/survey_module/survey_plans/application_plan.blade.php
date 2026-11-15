{{-- Application Plan: the Ministry's A4 template (user, 2026-11-15). Template only: not linked to any page yet.
     $ap: the Data Sheet entries (all optional; a blank prints as a line to fill by hand).
     $beacons: [['no' => 'SC/KN 1234', 'e' => 441344.78, 'n' => 1338968.26], ...] in order round the parcel,
     Minna / UTM Zone 32N metres. Plotted in the frame to scale; the frame's centre ticks carry the coordinates. --}}
@php
  $ap = $ap ?? [];
  $beacons = array_values(array_filter($beacons ?? [], fn ($b) => is_numeric($b['e'] ?? null) && is_numeric($b['n'] ?? null)));
  $v = fn ($k) => trim((string) ($ap[$k] ?? ''));

  // Frame in mm (as on the printed form); the drawing area keeps a margin inside it.
  $fw = 160; $fh = 120; $pad = 14;
  $scale = (int) ($ap['scale'] ?? 0);
  $plot = null;
  if (count($beacons) >= 2) {
    $es = array_column($beacons, 'e'); $ns = array_column($beacons, 'n');
    $ce = (min($es) + max($es)) / 2; $cn = (min($ns) + max($ns)) / 2;
    $spanE = max($es) - min($es); $spanN = max($ns) - min($ns);
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
      if ($nb > 2 || $i === 0) {
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
  .sheet { width: 210mm; height: 297mm; overflow: hidden; margin: 10mm auto; padding: 9mm 12mm 8mm; background: #fff; box-shadow: 0 2px 14px rgba(0,0,0,.18); }
  @media print { html, body { background: #fff; } .sheet { margin: 0; box-shadow: none; } .noprint { display: none !important; } }
  h1 { margin: 0; text-align: center; font-size: 17pt; line-height: 1.2; font-weight: 700; }
  .top { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8mm; align-items: center; margin-top: 4mm; }
  .row .fill[title] { cursor: help; }
  .box { border: 1px solid #000; padding: 1.5mm 2mm; font-size: 10.5pt; line-height: 1.45; }
  .title { font-size: 15pt; font-weight: 700; text-align: center; }
  .rows { margin-top: 5mm; font-size: 11.5pt; line-height: 1.75; }
  .row { display: flex; align-items: baseline; gap: 1mm; width: 145mm; }
  .row > span:not(.fill):not(.blank) { white-space: nowrap; }
  .blank { flex: 1; border-bottom: 1px solid #000; height: 1em; display: inline-block; }
  .fill { flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-bottom: 1px solid #000; padding: 0 1mm; font-family: Arial, sans-serif; font-size: 10.5pt; }
  .for { padding-left: 15mm; }
  .for .blank, .for .fill { border-bottom-style: dotted; text-align: center; }
  .frame-wrap { position: relative; margin: 4mm auto 0; width: 186mm; }
  .frame-wrap svg { display: block; width: 186mm; height: 140mm; overflow: visible; }
  .scale { text-align: center; font-family: Arial, sans-serif; font-size: 9.5pt; margin-top: 2mm; }
  .scale .u { display: inline-block; min-width: 12mm; border-bottom: 1px solid #000; }
  .bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 10mm; margin-top: 6mm; font-size: 11.5pt; line-height: 1.55; }
  .bottom .row { width: auto; }
  .yn b { text-decoration: underline; }
  .bar { display: flex; justify-content: center; gap: 8px; padding: 8px; background: #0f172a; }
  .bar button { font: 600 13px system-ui, sans-serif; padding: 7px 14px; border-radius: 8px; border: 0; cursor: pointer; background: #2563eb; color: #fff; }
</style>
</head>
<body>
<div class="bar noprint"><button type="button" onclick="window.print()">Print</button></div>
<div class="sheet">
  <h1>MINISTRY OF LAND AND PHYSICAL PLANNING<br>KANO STATE</h1>
  <div class="top">
    <div class="box">Code sur C of B<br>Serial No: {!! $v('serial_no') !== '' ? '<b>' . e($v('serial_no')) . '</b>' : '..........' !!}</div>
    <div class="title">Application Plan</div>
    <div class="box">No 2. Dr. Bala Mohd Road<br>P.M.B. 3083, Kano State</div>
  </div>

  <div class="rows">
    <div class="row for"><span>For</span>{!! $line('purpose') !!}<span>(PURPOSE)</span></div>
    <div class="row"><span>Name of Applicant:</span>{!! $line('applicant') !!}</div>
    <div class="row"><span>Address:</span>{!! $line('address') !!}</div>
    <div class="row"><span>Location:</span>{!! $line('location', 30) !!}<span>Map Sheet:</span>{!! $line('map_sheet', 25) !!}<span>Scale:</span>{!! $scaleText !== '' ? '<span class="fill">1:' . $scaleText . '</span>' : '<span class="blank"></span>' !!}</div>
    <div class="row"><span>Plan No:</span>{!! $line('plan_no', 22) !!}<span>MS NO.</span>{!! $line('ms_no', 22) !!}<span>Reference</span>{!! $line('reference', 30) !!}</div>
  </div>

  {{-- The frame: 160 x 120 mm, centre ticks on each side, north arrow to the right. --}}
  <div class="frame-wrap">
    <svg viewBox="-13 -12 186 140" xmlns="http://www.w3.org/2000/svg" font-family="Arial, sans-serif">
      <rect x="0" y="0" width="{{ $fw }}" height="{{ $fh }}" fill="none" stroke="#000" stroke-width=".45"/>
      <line x1="{{ $fw / 2 }}" y1="-1.5" x2="{{ $fw / 2 }}" y2="1.5" stroke="#000" stroke-width=".3"/>
      <line x1="{{ $fw / 2 }}" y1="{{ $fh - 1.5 }}" x2="{{ $fw / 2 }}" y2="{{ $fh + 1.5 }}" stroke="#000" stroke-width=".3"/>
      <line x1="-1.5" y1="{{ $fh / 2 }}" x2="1.5" y2="{{ $fh / 2 }}" stroke="#000" stroke-width=".3"/>
      <line x1="{{ $fw - 1.5 }}" y1="{{ $fh / 2 }}" x2="{{ $fw + 1.5 }}" y2="{{ $fh / 2 }}" stroke="#000" stroke-width=".3"/>
      @if ($plot)
        <text x="{{ $fw / 2 }}" y="-3.5" font-size="3" text-anchor="middle">{{ $fmt($plot['ce']) }} mE</text>
        <text x="{{ $fw / 2 }}" y="{{ $fh + 5.5 }}" font-size="3" text-anchor="middle">{{ $fmt($plot['ce']) }} mE</text>
        <text x="-3" y="{{ $fh / 2 - 1.5 }}" font-size="3" text-anchor="end">{{ $fmt($plot['cn']) }}</text>
        <text x="-3" y="{{ $fh / 2 + 2.5 }}" font-size="3" text-anchor="end">mN</text>
        <text x="{{ $fw + 3 }}" y="{{ $fh / 2 - 1.5 }}" font-size="3">{{ $fmt($plot['cn']) }}</text>
        <text x="{{ $fw + 3 }}" y="{{ $fh / 2 + 2.5 }}" font-size="3">mN</text>
        <polygon points="{{ collect($plot['pts'])->map(fn ($p) => $p[0] . ',' . $p[1])->implode(' ') }}" fill="#dbeafe" fill-opacity=".55" stroke="#c00" stroke-width=".5"/>
        @foreach ($plot['sides'] as $s)
          <text x="{{ $s['p'][0] }}" y="{{ $s['p'][1] }}" font-size="2.4" text-anchor="{{ $s['a'] ?? 'middle' }}" fill="#333">{{ number_format($s['d'], 2) }} m</text>
        @endforeach
        @foreach ($plot['pts'] as $i => $p)
          <circle cx="{{ $p[0] }}" cy="{{ $p[1] }}" r=".9" fill="#fff" stroke="#000" stroke-width=".35"/>
          @php $dx = $p[0] - $gx; $dy = $p[1] - $gy; @endphp
          <text x="{{ $p[0] + ($dx >= 0 ? 1.6 : -1.6) }}" y="{{ $p[1] + ($dy >= 0 ? 3.6 : -1.6) }}" text-anchor="{{ $dx >= 0 ? 'start' : 'end' }}" font-size="2.6" font-weight="700">{{ $beacons[$i]['no'] ?? ($i + 1) }}</text>
        @endforeach
        @if (count($beacons) >= 3)
          <text x="{{ $fw / 2 }}" y="{{ $fh - 4 }}" font-size="2.8" text-anchor="middle">Area: {{ number_format($plot['area'], 2) }} m² ({{ number_format($plot['area'] / 10000, 4) }} ha)</text>
        @endif
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
    <div class="scale">Scale 1:<span class="u">{{ $scaleText }}</span></div>
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
</div>
</body>
</html>
