{{--
    The hero band: the one number the unit leads with, plus its supporting row.

    A dashboard that opens with eight equal tiles has no lead. This makes the
    single figure that decides "is this unit healthy" unmissable, and demotes
    the rest to context beside it.

    The delta is stated as a direction and a count, never as a bare arrow — "up"
    is not automatically good, and the hint says what it means for this unit.
--}}
@php
    $hero  = $hero ?? [];
    $delta = $hero['delta'] ?? null;
@endphp

<div class="cad-hero">
    <div class="cad-hero-figure">
        <span class="cad-hero-label">{{ $hero['label'] ?? '' }}</span>

        <span class="cad-hero-value {{ ($hero['tone'] ?? null) === 'critical' && ($hero['value'] ?? 0) > 0 ? 'is-critical' : '' }}">
            {{ number_format($hero['value'] ?? 0) }}
        </span>

        @if ($delta && $delta['last_week'] + $delta['this_week'] > 0)
            <span class="cad-delta">
                @if ($delta['direction'] === 'up')
                    <i class="fas fa-arrow-trend-up"></i>
                    {{ number_format(abs($delta['change'])) }} more than the week before
                @elseif ($delta['direction'] === 'down')
                    <i class="fas fa-arrow-trend-down"></i>
                    {{ number_format(abs($delta['change'])) }} fewer than the week before
                @else
                    <i class="fas fa-minus"></i>
                    Level with the week before
                @endif
            </span>
        @endif

        @isset($hero['hint'])
            <span class="cad-hero-hint" style="margin-top:8px;">{{ $hero['hint'] }}</span>
        @endisset
    </div>

    <div>
        @include('cadastral_module.partials.charts._tiles', ['tiles' => $tiles ?? []])
    </div>
</div>
