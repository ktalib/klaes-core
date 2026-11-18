{{-- A plotting's traverse (user, 2026-11-18): the reference beacon, then line by line From beacon (X / Y), the bearing and
     distance, To beacon (X / Y). Shown on the plan page's Plotted Beacons tab and as the checker's "Traverse + reference
     beacons" card, in place of the CSV download. Uses the .sp-tbl / .sp-mono styles of _plan_summary.
     $tvPlan: the plan; $tvLines: PlanTraverseSheet::lines(). --}}
@php
  $tvRef = $tvPlan->referenceBeacons->first();
  $tvPd = (array) $tvPlan->plot_data;
@endphp
<div style="display:grid;gap:10px;">
  <div style="font-size:13px;color:#374151;display:flex;gap:16px;flex-wrap:wrap;">
    <span><strong>Reference beacon:</strong> <span class="sp-mono">{{ $tvRef?->beacon_no ?: '—' }}</span>
      @if ($tvRef) · X <span class="sp-mono">{{ $tvRef->coord_x }}</span> · Y <span class="sp-mono">{{ $tvRef->coord_y }}</span>@endif</span>
    <span><strong>Coordinate system:</strong> {{ $tvPlan->coord_system_label ?: '—' }}</span>
    @if (isset($tvPd['closing_error_m']))<span><strong>Closing error:</strong> {{ number_format((float) $tvPd['closing_error_m'], 3) }} m</span>@endif
    @if (isset($tvPd['area_m2']))<span><strong>Area:</strong> {{ number_format((float) $tvPd['area_m2'], 2) }} m²</span>@endif
  </div>
  <div style="overflow-x:auto;">
    <table class="sp-tbl">
      <thead><tr><th>Line</th><th>From Beacon</th><th>X (Easting)</th><th>Y (Northing)</th><th>Bearing</th><th>Distance (m)</th><th>To Beacon</th><th>X (Easting)</th><th>Y (Northing)</th></tr></thead>
      <tbody>
        @forelse ($tvLines as $l)
          <tr>
            <td>{{ $l['line'] }}</td>
            <td class="sp-mono">{{ $l['from'] }}@if ($loop->first && $tvRef && mb_strtoupper($l['from']) === mb_strtoupper((string) $tvRef->beacon_no)) <span style="font-size:10px;font-weight:700;color:#c2410c;">REF</span>@endif</td>
            <td class="sp-mono">{{ $l['from_x'] }}</td>
            <td class="sp-mono">{{ $l['from_y'] }}</td>
            <td class="sp-mono" style="white-space:nowrap;" title="Whole-circle {{ $l['bearing_wcb'] }}">{{ $l['bearing'] }}</td>
            <td class="sp-mono">{{ $l['distance'] }}</td>
            <td class="sp-mono">{{ $l['to'] }}</td>
            <td class="sp-mono">{{ $l['to_x'] }}</td>
            <td class="sp-mono">{{ $l['to_y'] }}</td>
          </tr>
        @empty
          <tr><td colspan="9" style="color:#6b7280;">No traverse: fewer than two beacons were plotted.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
