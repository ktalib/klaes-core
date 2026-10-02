{{--
    A work queue: the rows that need somebody to do something.

    Deliberately not a paginated table. A dashboard queue's job is to be acted
    on, so it shows the handful at the front and links to the full list; a
    dashboard that makes you page through it has become a second index screen.
--}}
<div class="cad-card">
    <div class="cad-card-head">
        <h3>{{ $title }}</h3>
        @isset($more)
            <a href="{{ $more }}" style="font-size:11.5px;">{{ $moreLabel ?? 'See all' }}</a>
        @endisset
    </div>
    @isset($subtitle)<p class="cad-card-sub">{{ $subtitle }}</p>@endisset

    @if (collect($rows)->isEmpty())
        <div class="cad-empty">
            <i class="fas fa-circle-check"></i>
            <span>{{ $empty ?? 'Nothing waiting.' }}</span>
        </div>
    @else
        <div class="cad-queue">
            @foreach ($rows as $row)
                <div class="cad-queue-row">
                    <div class="who">
                        <strong>{{ $row['primary'] }}</strong>
                        <span>{{ $row['secondary'] }}</span>
                    </div>
                    @isset($row['badge'])
                        <span class="status-badge {{ $row['badgeTone'] ?? 'pending' }}">
                            <span class="dot"></span>{{ $row['badge'] }}
                        </span>
                    @endisset
                    <span class="when">{{ $row['when'] ?? '' }}</span>
                    @isset($row['href'])
                        <a class="btn btn-outline btn-xs" href="{{ $row['href'] }}">Open</a>
                    @endisset
                </div>
            @endforeach
        </div>
    @endif
</div>
