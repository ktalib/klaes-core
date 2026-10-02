{{--
    A KPI row of stat tiles.

    A single current value is a stat tile, not a one-bar bar chart. Where a
    tile carries a sparkline the shape is context; the number stays the point.

    $tone uses the reserved status colours and always ships beside a label —
    the colour never carries the meaning on its own.
--}}
<div class="cad-tiles">
    @foreach ($tiles as $t)
        <div class="cad-tile">
            <span class="cad-tile-label">{{ $t['label'] }}</span>

            <span class="cad-tile-value {{ ($t['money'] ?? false) ? 'is-money' : '' }} {{ isset($t['tone']) && $t['value'] > 0 ? 'is-' . $t['tone'] : '' }}">
                @if ($t['money'] ?? false)
                    &#8358;{{ number_format($t['value'], 2) }}
                @else
                    {{ number_format($t['value']) }}
                @endif
            </span>

            @isset($t['spark'])
                @include('cadastral_module.partials.charts._sparkline', ['series' => $t['spark']])
            @endisset
        </div>
    @endforeach
</div>
