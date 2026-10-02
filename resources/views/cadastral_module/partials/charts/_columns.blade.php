{{--
    Trend over time — columns, one series.

    One series, so one colour and no legend: the card title names it. The most
    recent day is the point of the chart, so it takes the accent and the rest
    recede to the de-emphasis gray — emphasis, not categorical.

    Expects:
      $series  array of ['label','short','value','today']
      $title, $subtitle
      $id      unique DOM id

    Column ends are rounded 4px at the top only and stay anchored to the
    baseline. Values are NOT printed on every column — the peak is direct-
    labelled, the rest are in the tooltip and the table view.
--}}
@php
    $id       = $id       ?? 'col-' . uniqid();
    $series   = $series   ?? [];
    $max      = max(1, collect($series)->max('value') ?? 0);
    $peakAt   = collect($series)->sortByDesc('value')->keys()->first();
    $total    = collect($series)->sum('value');

    // Geometry. The container height includes the x-axis band, so the axis
    // labels are never clipped into a nested scrollbar.
    $w = 720; $h = 190; $padL = 34; $padR = 10; $padT = 14; $padB = 30;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;
    $n     = max(1, count($series));
    $slot  = $plotW / $n;
    $barW  = min(26, max(6, $slot - 8));
@endphp

<div class="cad-card">
    <div class="cad-card-head">
        <h3>{{ $title ?? 'Trend' }}</h3>
        <span style="font-size:11.5px;color:var(--viz-muted);">{{ number_format($total) }} in {{ $n }} days</span>
    </div>
    @isset($subtitle)<p class="cad-card-sub">{{ $subtitle }}</p>@endisset

    @if ($total === 0)
        <div class="cad-empty">
            <i class="fas fa-chart-column"></i>
            <span>Nothing recorded in the last {{ $n }} days.</span>
        </div>
    @else
        <svg class="cad-plot" viewBox="0 0 {{ $w }} {{ $h }}" role="img"
             aria-label="{{ $title ?? 'Trend' }} over the last {{ $n }} days">

            {{-- Recessive hairline grid: four solid rules, never dashed. --}}
            @for ($g = 0; $g <= 3; $g++)
                @php $gy = $padT + ($plotH / 3) * $g; @endphp
                <line class="grid" x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $w - $padR }}" y2="{{ $gy }}" />
                <text class="tick" x="{{ $padL - 6 }}" y="{{ $gy + 3 }}" text-anchor="end">
                    {{ round($max - ($max / 3) * $g) }}
                </text>
            @endfor

            <line class="axis" x1="{{ $padL }}" y1="{{ $padT + $plotH }}" x2="{{ $w - $padR }}" y2="{{ $padT + $plotH }}" />

            @foreach ($series as $i => $d)
                @php
                    $bh = $d['value'] > 0 ? max(2, ($d['value'] / $max) * $plotH) : 0;
                    $x  = $padL + ($slot * $i) + (($slot - $barW) / 2);
                    $y  = $padT + $plotH - $bh;
                    $isPeak = $i === $peakAt && $d['value'] > 0;
                    $fill = $d['today'] || $isPeak ? 'var(--viz-seq)' : 'var(--viz-dim)';
                @endphp

                {{-- Hit target spans the whole slot, so hovering is easy even
                     when the column is 2px tall. --}}
                <rect class="hit" x="{{ $padL + $slot * $i }}" y="{{ $padT }}"
                      width="{{ $slot }}" height="{{ $plotH }}">
                    <title>{{ $d['label'] }} — {{ number_format($d['value']) }}</title>
                </rect>

                @if ($bh > 0)
                    <rect class="mark" x="{{ round($x, 2) }}" y="{{ round($y, 2) }}"
                          width="{{ round($barW, 2) }}" height="{{ round($bh, 2) }}"
                          rx="4" ry="4" fill="{{ $fill }}" pointer-events="none" />
                    {{-- Square off the bottom so the column stays anchored to
                         the baseline rather than floating on a rounded foot. --}}
                    <rect x="{{ round($x, 2) }}" y="{{ round(min($y + 4, $padT + $plotH - 0.5), 2) }}"
                          width="{{ round($barW, 2) }}" height="{{ round(max(0.5, $bh - 4), 2) }}"
                          fill="{{ $fill }}" pointer-events="none" />
                @endif

                {{-- Selective direct label: the peak only. --}}
                @if ($isPeak)
                    <text class="vlabel" x="{{ round($x + $barW / 2, 2) }}" y="{{ round($y - 6, 2) }}"
                          text-anchor="middle">{{ number_format($d['value']) }}</text>
                @endif

                {{-- Thin the x ticks so they never collide. --}}
                @if ($n <= 10 || $i % 2 === 0 || $d['today'])
                    <text class="tick" x="{{ round($padL + $slot * $i + $slot / 2, 2) }}"
                          y="{{ $padT + $plotH + 15 }}" text-anchor="middle"
                          @if ($d['today']) style="font-weight:700;fill:var(--viz-ink-2);" @endif>
                        {{ $d['short'] }}
                    </text>
                @endif
            @endforeach
        </svg>

        <details class="cad-tableview">
            <summary>Table view</summary>
            <table>
                <thead><tr><th>Day</th><th class="num">Count</th></tr></thead>
                <tbody>
                    @foreach ($series as $d)
                        <tr><td>{{ $d['label'] }}</td><td class="num">{{ number_format($d['value']) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
