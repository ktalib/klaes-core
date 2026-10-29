{{-- SDI pages: Leaflet straight, not partials/maps_scripts (its Google-maps shim would load a second Leaflet; see ALAES sdi/partials/map_head). --}}
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    @if (! empty($draw))
        <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    @endif
    <style>
        .survey-proto .sdi-nav { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 18px; border-bottom: 1px solid var(--gray-200, #e5e7eb); }
        .survey-proto .sdi-nav a { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; font-size: 14px; color: var(--gray-600, #4b5563);
            border-bottom: 2px solid transparent; margin-bottom: -1px; text-decoration: none; }
        .survey-proto .sdi-nav a:hover { color: var(--gray-900, #111827); }
        .survey-proto .sdi-nav a.on { color: var(--primary); border-bottom-color: var(--primary); font-weight: 600; }
        .survey-proto .sdi-nav .n { font-size: 12px; background: var(--gray-100, #f3f4f6); border-radius: 999px; padding: 1px 8px; color: var(--gray-600); }
        .survey-proto .sdi-map { border-radius: 10px; overflow: hidden; border: 1px solid var(--gray-300, #d1d5db); }
        /* Tailwind's preflight shrinks Leaflet's 256px tiles and leaves the map blank. */
        .survey-proto .sdi-map img, .survey-proto .sdi-map .leaflet-tile { max-width: none !important; max-height: none !important; }
        .survey-proto .sdi-map.leaflet-container { font: inherit; }
        /* Tile credit kept (provider terms) but made small and quiet. */
        .survey-proto .sdi-map .leaflet-control-attribution { font-size: 9px; line-height: 1.4; padding: 0 5px; background: rgba(255,255,255,.55); color: #6b7280; }
        .survey-proto .sdi-map .leaflet-control-attribution a { color: #6b7280; }
        .survey-proto .sdi-points { columns: 2; font-family: ui-monospace, monospace; font-size: 12px; color: var(--gray-700); margin: 6px 0 0; padding-left: 18px; }
        .survey-proto .sdi-chip { display: inline-block; font-size: 11px; padding: 1px 8px; border-radius: 999px; background: var(--gray-100, #f3f4f6); color: var(--gray-700); }
        .survey-proto .sdi-chip.high { background: #fef3c7; color: #92400e; }
        .survey-proto .sdi-chip.late { background: #fee2e2; color: #991b1b; }
    </style>
@endpush
@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @if (! empty($draw))
        <script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
    @endif
    <script src="{{ asset('js/survey/sdi-basemaps.js') }}?v=2"></script>
    <script src="{{ asset('js/survey/sdi-landuse.js') }}?v=2"></script>
@endpush

@php
    $sdiOpen = \App\Models\Sdi\SdiMapRequest::open()->count();
@endphp
<div class="sdi-nav" role="tablist">
    <a href="{{ route('survey-module.sdi.map-requests') }}" class="{{ request()->routeIs('survey-module.sdi.map-requests*') ? 'on' : '' }}">
        <i class="fas fa-map-marked-alt"></i> Map Request
    </a>
    <a href="{{ route('survey-module.sdi.production') }}" class="{{ request()->routeIs('survey-module.sdi.production*') ? 'on' : '' }}">
        <i class="fas fa-industry"></i> Production Unit <span class="n">{{ number_format($sdiOpen) }} open</span>
    </a>
    <a href="{{ route('survey-module.sdi.registry') }}" class="{{ request()->routeIs('survey-module.sdi.registry*') ? 'on' : '' }}">
        <i class="fas fa-building"></i> MDA &amp; Map Type Registry
    </a>
</div>
