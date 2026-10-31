{{-- Government / Private tabs under Survey Management (CR 30102026 item 15).
     $route: the page's route name; $tab: 'government' | 'private'; $keep: extra query to carry. --}}
@php $keep = $keep ?? []; @endphp
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" role="tablist">
    @foreach (['government' => ['Government', 'fa-landmark'], 'private' => ['Private', 'fa-user-tie']] as $k => [$label, $icon])
        <a href="{{ route($route, array_filter($keep + ['tab' => $k === 'government' ? null : $k])) }}" role="tab"
           aria-selected="{{ $tab === $k ? 'true' : 'false' }}" class="btn {{ $tab === $k ? 'btn-primary' : 'btn-outline' }} btn-sm">
            <i class="fas {{ $icon }}"></i> {{ $label }}
        </a>
    @endforeach
</div>
