@php
    $tab = function (string $route, string $key, string $icon, string $label, array $params = []) use ($activeTab) {
        $on = $activeTab === $key;
        return '<a href="' . e(route($route, $params)) . '" class="mobile-tab' . ($on ? ' active' : '') . '"' . ($on ? ' aria-current="page"' : '') . '>'
            . '<i class="fas ' . $icon . '" aria-hidden="true"></i><span>' . e($label) . '</span></a>';
    };
@endphp
<footer class="mobile-footer">
    <nav class="mobile-footer-inner" aria-label="Survey mobile navigation">
        {{-- The tabs of the field app this phone runs (App\Support\SurveyMobileApps, 2026-10-30). --}}
        @switch(\App\Support\SurveyMobileApps::current())
            @case('cfo')
                {!! $tab('survey-module.mobile.observations', 'readings', 'fa-compass-drafting', 'Jobs') !!}
                @break
            @case('plot')
                {!! $tab('survey-module.mobile.plots.index', 'plots', 'fa-house', 'Home') !!}
                {!! $tab('survey-module.mobile.plots.ops', 'showings', 'fa-people-arrows', 'Show OP') !!}
                {!! $tab('survey-module.mobile.plots.checks.create', 'checks', 'fa-map-location-dot', 'Ground check') !!}
                @break
            @default
                {!! $tab('survey-module.mobile.register', 'register', 'fa-pen-to-square', 'Register') !!}
                {!! $tab('survey-module.mobile.index', 'cases', 'fa-folder-open', 'Cases') !!}
        @endswitch
        <button type="button" class="mobile-tab" onclick="ui.menu()" aria-label="Account menu">
            <i class="fas fa-circle-user" aria-hidden="true"></i><span>Profile</span>
        </button>
    </nav>
</footer>

@include('survey_module.mobile._offline')
