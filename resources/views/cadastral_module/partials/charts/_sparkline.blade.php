{{--
    A sparkline inside a stat tile: shape only, no axes, no labels.

    It answers "which way is this going" and nothing else — the tile's own
    number carries the value, so printing numbers here would just be noise.
    One hue, and the last point marked so the eye lands on "now".
--}}
@php
    $series = collect($series ?? []);
    $vals   = $series->pluck('value');
    $max    = max(1, (int) $vals->max());
    $n      = max(1, $series->count());
    $w = 160; $h = 26;
    $step = $n > 1 ? $w / ($n - 1) : $w;

    $points = $series->values()->map(function ($d, $i) use ($step, $h, $max) {
        return round($i * $step, 2) . ',' . round($h - 2 - (($d['value'] / $max) * ($h - 5)), 2);
    })->implode(' ');

    $lastY = $h - 2 - ((($series->last()['value'] ?? 0) / $max) * ($h - 5));
@endphp

@if ($vals->sum() > 0)
    <svg class="cad-tile-spark" viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none"
         role="img" aria-label="Trend over the last {{ $n }} days">
        <polyline points="{{ $points }}" fill="none" stroke="var(--viz-seq)"
                  stroke-width="2" stroke-linejoin="round" stroke-linecap="round"
                  vector-effect="non-scaling-stroke" />
        {{-- 2px surface ring keeps the marker readable where it overlaps the line. --}}
        <circle cx="{{ $w }}" cy="{{ round($lastY, 2) }}" r="3.5"
                fill="var(--viz-seq)" stroke="var(--viz-surface)" stroke-width="2"
                vector-effect="non-scaling-stroke" />
    </svg>
@else
    <div class="cad-tile-spark"></div>
@endif
