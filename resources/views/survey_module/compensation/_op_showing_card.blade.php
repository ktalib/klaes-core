{{-- One showing on the Showing History page. $s: SurveyOpShowing; $latest: the newest one is highlighted. --}}
<div class="shw-card {{ $latest ? 'latest' : '' }}">
    <div class="shw-head">
        <div class="shw-no"><i class="fas fa-street-view" style="color:var(--primary);"></i> {{ $s->label }}
            @if ($latest)<span class="status-badge active" style="margin-left:6px;"><span class="dot"></span>Latest</span>@endif
        </div>
        @if ($s->reason)<span class="status-badge review"><span class="dot"></span>{{ $s->reason }}</span>@endif
    </div>
    <div class="shw-meta">
        <div><span>Surveyor</span>{{ $s->surveyor_name }}</div>
        <div><span>Date</span>{{ $s->shown_on->format('d M Y') }}</div>
        <div><span>Time</span>{{ $s->shown_at }}</div>
        <div><span>Photographs</span>{{ $s->photos->count() }}</div>
    </div>
    @if ($s->notes)<div style="font-size:13px;margin-top:8px;white-space:pre-line;">{{ $s->notes }}</div>@endif
    @if ($s->photos->isNotEmpty())
        <div class="shw-photos">
            @foreach ($s->photos as $p)
                <a href="{{ route('survey-module.compensation.op-showings.photo', $p) }}" target="_blank" title="{{ $p->original_name }}">
                    <img src="{{ route('survey-module.compensation.op-showings.photo', $p) }}" alt="{{ $s->label }} photograph {{ $loop->iteration }}" loading="lazy" />
                </a>
            @endforeach
        </div>
    @endif
</div>
