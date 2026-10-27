{{--
    Plan Description print, Cadastral Department workflow (change request 26-10-2026).
    Two forms, laid out like the Ministry's printed sheets the client sent:
      CAD 20  "Kano State, Nigeria - Description of Right of Occupancy No. KN": the boundary as
              "For a distance of ... Meters on a bearing of ... To a [beacon] Thence for ...".
      CAD 13  Ministry letterhead with the crest (SLTR files): the property beacon numbers, the
              drawing it was copied from, and the free-text description.
    The blanks are filled from the saved Plan Description (cadastral_plan_descriptions and its
    cadastral_pillars lines). Standalone page: no layout, so it prints alone.
--}}
@php
    $ha = $pd->area_sqm !== null ? rtrim(rtrim(number_format((float) $pd->area_sqm / 10000, 4), '0'), '.') : '';
    $dist = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $lines = $pillars->values();
    $beaconList = $lines->pluck('pillar_number')->filter()->values();
    $beaconText = $beaconList->count() > 1
        ? $beaconList->slice(0, -1)->implode(', ') . ' and ' . $beaconList->last()
        : (string) $beaconList->first();
    $blank = fn ($v, $w = 160) => $v !== null && $v !== '' ? e($v) : '<span class="dots" style="min-width:' . $w . 'px"></span>';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $form === 'cad13' ? 'CAD 13' : 'CAD 20' }} — Description of Right of Occupancy {{ $case->file_number }}</title>
    <style>
        @page { size: A4; margin: 14mm 16mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: #1d4ed8; font-size: 13px; margin: 0; background: #fff; }
        .sheet { max-width: 180mm; margin: 0 auto; padding: 8px 0; }
        .fill { color: #111; font-weight: bold; font-family: "Courier New", monospace; }
        .dots { display: inline-block; border-bottom: 1px dotted #1d4ed8; height: 1em; vertical-align: bottom; }
        .center { text-align: center; }
        h1 { margin: 0; font-size: 17px; letter-spacing: .5px; }
        .title { text-decoration: underline; font-weight: bold; font-size: 14px; margin: 10px 0 12px; }
        p { line-height: 1.9; margin: 0 0 6px; }
        table.lines { width: 100%; border-collapse: collapse; margin: 8px 0 10px; }
        table.lines th { text-align: left; font-size: 12px; padding: 2px 4px; }
        table.lines td { padding: 3px 4px; vertical-align: bottom; }
        table.lines td.v { color: #111; font-weight: bold; font-family: "Courier New", monospace; border-bottom: 1px dotted #93c5fd; }
        .foot { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 18px; font-weight: bold; }
        .sign { min-width: 220px; border-bottom: 1px dotted #1d4ed8; display: inline-block; }
        .head13 { display: flex; align-items: center; gap: 14px; }
        .head13 img { width: 78px; height: auto; }
        .head13 h1 { font-size: 22px; font-weight: 900; }
        .noprint { margin: 12px auto; max-width: 180mm; font-family: Arial, sans-serif; color: #374151; display: flex; gap: 8px; }
        .noprint a, .noprint button { padding: 6px 12px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #111; text-decoration: none; font-size: 13px; cursor: pointer; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
<div class="noprint">
    <button onclick="window.print()">Print</button>
    <a href="{{ route('cadastral-dept.cases.bill.description.print', ['case' => $case->id, 'form' => 'cad20']) }}">CAD 20</a>
    <a href="{{ route('cadastral-dept.cases.bill.description.print', ['case' => $case->id, 'form' => 'cad13']) }}">CAD 13</a>
    <a href="{{ route('cadastral-dept.cases.bill.show', $case->id) }}">Back to the Plan Description</a>
</div>

<div class="sheet">
@if ($form === 'cad13')
    {{-- ===================== CAD 13 (Ministry letterhead) ===================== --}}
    <div class="head13">
        <img src="{{ asset('assets/logo/SCF.png') }}" alt="Kano State">
        <div>
            <h1>MINISTRY OF LAND AND PHYSICAL PLANNING</h1>
            <div class="center" style="font-weight:bold">No. 2 Dr. Bala Muhammad Road, P.M.B. 3083, Kano State</div>
            <div class="center title" style="margin:6px 0 0">DESCRIPTION OF RIGHT OF OCCUPANCY NO. <span class="fill">{{ $case->file_number }}</span></div>
        </div>
    </div>
    <p style="margin-top:16px">
        All that piece of Land situated at <span class="fill">{!! $blank($header['situated_at']) !!}</span>
        in the <span class="fill">{!! $blank($header['district']) !!}</span>
        District of the <span class="fill">{!! $blank($header['lga']) !!}</span>
        administrative Area of Kano State consisting of <span class="fill">{!! $blank($ha, 90) !!}</span>
        Hectares, the corners of which are marked in the ground by Property Beacons Nos
        <span class="fill">{!! $blank($beaconText, 260) !!}</span>
    </p>
    <p>
        And the boundaries of which are delineated by a red verge line on the attached copy of Drawing
        No. <span class="fill">{!! $blank($header['plan_drawing_no']) !!}</span>
        Copied from plan No. <span class="fill">{!! $blank($header['copied_from_plan_no']) !!}</span>
        Approved by and deposited in the office of the Director Cadastral of Kano State and in more particularly described in the drawing as
    </p>
    <p class="fill" style="font-weight:normal;white-space:pre-line;border-bottom:1px dotted #1d4ed8;min-height:3.6em">{{ $pd->description_body ?: $header['dimensions'] }}</p>
    <div class="foot">
        <div>Date <span class="sign" style="min-width:140px"></span><div style="margin:6px 0 0 30px">Cad 13</div></div>
        <div>Director Cadastral <span class="sign"></span></div>
    </div>
@else
    {{-- ===================== CAD 20 (bearings and distances) ===================== --}}
    <div class="center">
        <h1>KANO STATE</h1>
        <h1>NIGERIA</h1>
    </div>
    <div class="title">DESCRIPTION OF RIGHT OF OCCUPANCY NO. KN <span class="fill">{{ $case->file_number }}</span></div>
    <p>
        All that piece of land situated at <span class="fill">{!! $blank($header['situated_at'], 220) !!}</span> in the
        <span class="fill">{!! $blank($header['district']) !!}</span> District of the
        <span class="fill">{!! $blank($header['lga']) !!}</span> Administrative Area of Kano State Consisting of
        <span class="fill">{!! $blank($ha, 90) !!}</span> Hectares, the boundaries of which are delineated by a red verge line on the attached plan
        drawing No. R. Of O KN <span class="fill">{!! $blank($header['plan_drawing_no'], 120) !!}</span> and are more particularly described as follows:
    </p>
    <table class="lines">
        <thead>
            <tr><th style="width:22%">Starting from</th><th style="width:26%">Property</th><th style="width:26%">Beacon <span class="fill">{{ $lines->first()->pillar_number ?? '' }}</span></th><th>the boundary runs</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $line)
                <tr>
                    @if ($i === 0)
                        <td>For a</td>
                    @else
                        <td>To a <span class="fill">{{ $line->pillar_number }}</span></td>
                    @endif
                    <td>{{ $i === 0 ? 'distance of' : 'Thence for' }} <span class="fill">{{ $dist($line->distance_m ?? null) }}</span></td>
                    <td>Meters on a bearing{{ $i === 0 ? ' of' : '' }}</td>
                    <td class="v">{{ \App\Services\CadastralDept\BoundaryTraverse::format($line->bearing_deg ?? null, $line->bearing_min ?? null, $line->bearing_sec ?? null) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p>
        Back to the starting point this enclosing the area stated above. Accessible corners are marked by concrete beacons and all bearings are
        referred to National North inaccessible corners are co-ordinated but not marked on the ground (see plan).
    </p>
    @if ($header['dimensions'])
        <p><span style="font-weight:bold">Dimensions:</span> <span class="fill" style="font-weight:normal">{{ $header['dimensions'] }}</span></p>
    @endif
    <div class="foot">
        <div>CAD 20</div>
        <div>For Director Cadastral <span class="sign"></span></div>
    </div>
@endif
</div>
</body>
</html>
