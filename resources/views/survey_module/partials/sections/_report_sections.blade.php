{{-- The three sections of the Surveyors Report table (recording, 2026-11-06), one table on GKN and on Examination.
     $part: bands (the section headings), heads (their columns), cells (a row's values), with $rec and $a, the row's
     ReportRecords::summary and anchor; no $rec shows dashes. $links (optional): page => url, for counts read from
     the registers rather than from the anchor's own pages. --}}
@php
    $fmt = fn ($v, $d = 2) => $v === null ? '—' : number_format((float) $v, $d);
    $open = fn ($page) => ($links[$page] ?? null) ?: ($a ? route($a['routes'] . '.' . $page . '.show', $a['key']) : null);
    $cnt = fn ($n, $url, $title) => $url
        ? '<a href="' . e($url) . '" title="' . e($title) . '"><strong>' . number_format((int) $n) . '</strong></a>'
        : '—';
@endphp
@if ($part === 'bands')
    <th colspan="3" class="rs-band rs-beacons rs-split">Beacon Nos</th>
    <th colspan="3" class="rs-band rs-coords rs-split">List of Coordinates</th>
    <th colspan="4" class="rs-band rs-obs rs-split">Computation &amp; Field Observations</th>
@elseif ($part === 'heads')
    <th class="rs-beacons rs-split" style="text-align:right;">Beacons</th>
    <th class="rs-beacons" style="text-align:right;">In Register</th>
    <th class="rs-beacons" style="text-align:right;" title="Not in the Beacon Register, or issued to another surveyor">Flagged</th>
    <th class="rs-coords rs-split" style="text-align:right;">Points</th>
    <th class="rs-coords" style="text-align:right;">Area (m²)</th>
    <th class="rs-coords" style="text-align:right;">Perimeter (m)</th>
    <th class="rs-obs rs-split" style="text-align:right;">Readings</th>
    <th class="rs-obs">Closure</th>
    <th class="rs-obs" style="text-align:right;">Misclosure (m)</th>
    <th class="rs-obs">Sketch Plan</th>
@else
    @php $rec = $rec ?? null; $a = $a ?? null; @endphp
    <td class="rs-split" style="text-align:right;">{!! $rec ? $cnt($rec['beacons'], $open('beacons'), 'Open Beacon Nos') : '—' !!}</td>
    <td style="text-align:right;">{{ $rec ? number_format($rec['in_register']) : '—' }}</td>
    <td style="text-align:right;@if ($rec && $rec['flagged']) color:#b91c1c;font-weight:600;@endif">{{ $rec ? number_format($rec['flagged']) : '—' }}</td>
    <td class="rs-split" style="text-align:right;">{!! $rec ? $cnt($rec['points'], $open('coordinates'), 'Open List of Coordinates') : '—' !!}</td>
    <td style="text-align:right;">{{ $rec ? $fmt($rec['area']) : '—' }}</td>
    <td style="text-align:right;">{{ $rec ? $fmt($rec['perimeter']) : '—' }}</td>
    <td class="rs-split" style="text-align:right;">{!! $rec ? $cnt($rec['readings'], $open('observations'), 'Open Field Observations') : '—' !!}</td>
    <td style="white-space:nowrap;">{{ ! $rec || ! $rec['readings'] ? '—' : ($rec['closed'] ? 'Closed' : 'Open') }}</td>
    <td style="text-align:right;">{{ $rec ? $fmt($rec['misclosure'], 3) : '—' }}</td>
    <td style="white-space:nowrap;">{{ $rec && $rec['sketch'] !== null ? ($rec['sketch'] ? 'Uploaded' : 'Not uploaded') : '—' }}</td>
@endif
