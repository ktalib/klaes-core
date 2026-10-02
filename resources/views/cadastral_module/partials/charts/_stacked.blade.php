{{--
    Part-to-whole — one horizontal stacked bar.

    Horizontal, not a pie: the segment names are words, and a bar compares
    close values a pie cannot. Capped at six segments; past that it is a table.

    Categorical colour is right here because the segments ARE the subject
    (the three report streams, the four fee lines). Slots are assigned by the
    segment's FIXED position in $parts['segments'], never by its current size,
    so a quiet month never repaints the legend.

    Segments are separated by a 2px surface gap, not by a border drawn around
    each one. A label only renders inside a segment when it fits with padding —
    otherwise it lives in the legend and the table view, which is also the
    relief for aqua's sub-3:1 contrast on this surface.

    Expects:
      $parts   ['segments' => [['key','label','value','percent']], 'total' => n]
      $title, $subtitle
      $money   true to format values as naira
--}}
@php
    $segments = collect($parts['segments'] ?? [])->values();
    $total    = (int) ($parts['total'] ?? 0);
    $money    = $money ?? false;

    // Fixed slot order — identity, not rank.
    $slots = ['var(--viz-1)', 'var(--viz-2)', 'var(--viz-3)', 'var(--viz-4)'];

    $fmt = fn ($v) => $money ? '₦' . number_format($v) : number_format($v);

    $barW = 620; $barH = 30; $gap = 2;
@endphp

<div class="cad-card">
    <div class="cad-card-head">
        <h3>{{ $title ?? '' }}</h3>
        <span style="font-size:11.5px;color:var(--viz-muted);">{{ $fmt($total) }} total</span>
    </div>
    @isset($subtitle)<p class="cad-card-sub">{{ $subtitle }}</p>@endisset

    @if ($total === 0)
        <div class="cad-empty">
            <i class="fas fa-chart-pie"></i>
            <span>{{ $empty ?? 'Nothing recorded yet.' }}</span>
        </div>
    @else
        @php
            $x = 0;
            $drawn = [];
            $live  = $segments->filter(fn ($s) => $s['value'] > 0)->count();
            foreach ($segments as $i => $s) {
                $raw = $total > 0 ? ($s['value'] / $total) * $barW : 0;
                // Reserve the gap out of each segment except the last drawn one.
                $wSeg = max(0, $raw - ($s['value'] > 0 ? $gap : 0));
                $drawn[] = ['seg' => $s, 'x' => $x, 'w' => $wSeg, 'slot' => $slots[$i % count($slots)]];
                $x += $raw;
            }
        @endphp

        <svg class="cad-plot" viewBox="0 0 {{ $barW }} {{ $barH }}" role="img"
             aria-label="{{ $title ?? 'Breakdown' }}" style="height:{{ $barH }}px;">
            @foreach ($drawn as $d)
                @if ($d['w'] > 0)
                    <rect class="mark" x="{{ round($d['x'], 2) }}" y="0"
                          width="{{ round($d['w'], 2) }}" height="{{ $barH }}"
                          rx="4" fill="{{ $d['slot'] }}">
                        <title>{{ $d['seg']['label'] }} — {{ $fmt($d['seg']['value']) }} ({{ $d['seg']['percent'] }}%)</title>
                    </rect>

                    {{-- Only label inside when it genuinely fits. --}}
                    @if ($d['w'] > 56)
                        <text x="{{ round($d['x'] + $d['w'] / 2, 2) }}" y="{{ $barH / 2 + 4 }}"
                              text-anchor="middle" fill="#ffffff"
                              style="font-size:11.5px;font-weight:700;" pointer-events="none">
                            {{ $d['seg']['percent'] }}%
                        </text>
                    @endif
                @endif
            @endforeach
        </svg>

        {{-- The legend is always present: identity is never colour alone. --}}
        <div class="cad-legend">
            @foreach ($drawn as $d)
                <span>
                    <i style="background:{{ $d['slot'] }};"></i>
                    {{ $d['seg']['label'] }}
                    <strong style="color:var(--viz-ink);">{{ $fmt($d['seg']['value']) }}</strong>
                </span>
            @endforeach
        </div>

        <details class="cad-tableview">
            <summary>Table view</summary>
            <table>
                <thead><tr><th>{{ $dimension ?? 'Segment' }}</th><th class="num">{{ $money ? 'Amount' : 'Count' }}</th><th class="num">Share</th></tr></thead>
                <tbody>
                    @foreach ($segments as $s)
                        <tr>
                            <td>{{ $s['label'] }}</td>
                            <td class="num">{{ $fmt($s['value']) }}</td>
                            <td class="num">{{ $s['percent'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
