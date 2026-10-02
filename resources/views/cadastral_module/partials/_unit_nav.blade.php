{{--
    The four units as tabs.

    Each unit has its own dashboard rather than a shared page of stacked cards:
    the Registry clerk, the Chart Officer, the Report desk and the Plans officer
    do different jobs and need different leading numbers. The tab strip is how
    you cross between them, and it carries each unit's live queue count so the
    busiest one is visible without opening it.

    Registry comes first and is the module's landing page — it is where files
    enter the department, so it is where the day starts.
--}}
@php
    $unit = $unit ?? 'registry';

    $units = [
        [
            'key'   => 'registry',
            'label' => 'Registry',
            'icon'  => 'fa-inbox',
            'route' => 'cadastral-module.dashboard',
            'count' => $navCounts['registry'] ?? null,
        ],
        [
            'key'   => 'information',
            'label' => 'Information',
            'icon'  => 'fa-pen-ruler',
            'route' => 'cadastral-module.information.dashboard',
            'count' => $navCounts['information'] ?? null,
        ],
        [
            'key'   => 'reports',
            'label' => 'Reports',
            'icon'  => 'fa-file-lines',
            'route' => 'cadastral-module.reports.dashboard',
            'count' => $navCounts['reports'] ?? null,
        ],
        [
            'key'   => 'pnd',
            'label' => 'Plan & Description',
            'icon'  => 'fa-ruler-combined',
            'route' => 'cadastral-module.plan-description.dashboard',
            'count' => $navCounts['pnd'] ?? null,
        ],
    ];
@endphp

<nav class="cad-units" aria-label="Cadastral units">
    @foreach ($units as $u)
        <a href="{{ route($u['route']) }}"
           class="cad-unit {{ $unit === $u['key'] ? 'is-active' : '' }}"
           @if ($unit === $u['key']) aria-current="page" @endif>
            <i class="fas {{ $u['icon'] }}"></i>
            <span>{{ $u['label'] }}</span>
            @if (($u['count'] ?? null) !== null)
                <span class="n">{{ number_format($u['count']) }}</span>
            @endif
        </a>
    @endforeach
</nav>
