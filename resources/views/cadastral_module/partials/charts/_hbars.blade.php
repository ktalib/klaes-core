{{--
    Magnitude across named categories — horizontal bars, one series.

    Horizontal because the category names are words, not dates: a vertical bar
    chart would need them rotated, and rotated labels are unreadable.

    ONE COLOUR for every bar. The categories here are nominal (registries,
    desks, statuses), so shading each bar by its own value would double-encode
    length as hue and burn the only free channel on information the bar already
    carries. Where the categories are genuinely ordered, pass $ordered = true —
    the rendering is the same, the axis order is what does the work.

    $emphasis (a key) puts one bar in the accent and the rest in gray, for the
    "one of these is the point" case.

    Expects:
      $rows      collection/array of ['key','label','value']
      $title, $subtitle
      $max       optional cap for the scale
      $limit     fold the tail past this many into "Other"
--}}
@php
    $rows  = collect($rows ?? []);
    $limit = $limit ?? 8;

    // Never grow more hues to fit more categories: fold the tail into Other.
    if ($rows->count() > $limit) {
        $head = $rows->take($limit - 1);
        $tail = $rows->slice($limit - 1);
        $rows = $head->push([
            'key'   => '__other',
            'label' => 'Other (' . $tail->count() . ')',
            'value' => (int) $tail->sum('value'),
        ])->values();
    }

    $total    = (int) $rows->sum('value');
    $max      = max(1, $max ?? (int) $rows->max('value'));
    $emphasis = $emphasis ?? null;
    $rowH     = 26;
    $labelW   = $labelW ?? 150;
@endphp

<div class="cad-card">
    <div class="cad-card-head">
        <h3>{{ $title ?? '' }}</h3>
        <span style="font-size:11.5px;color:var(--viz-muted);">{{ number_format($total) }} total</span>
    </div>
    @isset($subtitle)<p class="cad-card-sub">{{ $subtitle }}</p>@endisset

    @if ($rows->isEmpty() || $total === 0)
        <div class="cad-empty">
            <i class="fas fa-chart-bar"></i>
            <span>{{ $empty ?? 'Nothing to show yet.' }}</span>
        </div>
    @else
        @php $h = $rows->count() * $rowH + 6; @endphp

        <svg class="cad-plot" viewBox="0 0 640 {{ $h }}" role="img"
             aria-label="{{ $title ?? 'Breakdown' }}" style="height:{{ $h }}px;">
            @foreach ($rows as $i => $r)
                @php
                    $y  = $i * $rowH;
                    $bw = $r['value'] > 0 ? max(3, ($r['value'] / $max) * (620 - $labelW - 46)) : 0;
                    $fill = $emphasis === null
                        ? 'var(--viz-seq)'
                        : ($emphasis === $r['key'] ? 'var(--viz-seq)' : 'var(--viz-dim)');
                @endphp

                <text class="clabel" x="0" y="{{ $y + 16 }}">
                    {{ Str::limit($r['label'], 24) }}
                    <title>{{ $r['label'] }}</title>
                </text>

                {{-- Track, so a short bar still reads against a known length. --}}
                <rect x="{{ $labelW }}" y="{{ $y + 6 }}" width="{{ 620 - $labelW - 46 }}" height="12"
                      rx="4" fill="var(--viz-plane)" />

                @if ($bw > 0)
                    <rect class="mark" x="{{ $labelW }}" y="{{ $y + 6 }}"
                          width="{{ round($bw, 2) }}" height="12" rx="4" fill="{{ $fill }}">
                        <title>{{ $r['label'] }} — {{ number_format($r['value']) }}</title>
                    </rect>
                    {{-- Square the left end so the bar grows out of the axis
                         instead of floating away from it. --}}
                    <rect x="{{ $labelW }}" y="{{ $y + 6 }}"
                          width="{{ round(min(4, $bw), 2) }}" height="12" fill="{{ $fill }}" pointer-events="none" />
                @endif

                {{-- Value outside the bar end: it can never be clipped by a
                     bar too short to hold it. --}}
                <text class="vlabel" x="{{ 620 - 40 }}" y="{{ $y + 16 }}">{{ number_format($r['value']) }}</text>
            @endforeach
        </svg>

        <details class="cad-tableview">
            <summary>Table view</summary>
            <table>
                <thead><tr><th>{{ $dimension ?? 'Category' }}</th><th class="num">Count</th><th class="num">Share</th></tr></thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>{{ $r['label'] }}</td>
                            <td class="num">{{ number_format($r['value']) }}</td>
                            <td class="num">{{ $total > 0 ? round($r['value'] / $total * 100, 1) : 0 }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
