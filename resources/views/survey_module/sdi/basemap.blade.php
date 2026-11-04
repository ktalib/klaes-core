@extends('survey_module.layouts.klaes')

@section('page-title', 'Basemap (Satellite Imagery) — KLAES')

@php
    // Geo-informatics > Basemap (Satellite Imagery) (user, 2026-11-03): the SDI maps on satellite imagery.
    $PageTitle = 'Basemap (Satellite Imagery)';
    $PageDescription = 'Kano on satellite imagery, with the area of every SDI map request. Switch to the street or terrain map at the top right.';
    $statusColours = [
        \App\Models\Sdi\SdiMapRequest::PENDING     => '#d97706',
        \App\Models\Sdi\SdiMapRequest::IN_PROGRESS => '#2563eb',
        \App\Models\Sdi\SdiMapRequest::FULFILLED   => '#16a34a',
        \App\Models\Sdi\SdiMapRequest::REJECTED    => '#dc2626',
    ];
@endphp

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .survey-proto .bm-map { height: calc(100vh - 260px); min-height: 460px; border-radius: 10px; overflow: hidden; border: 1px solid var(--gray-300, #d1d5db); }
        /* Tailwind's preflight shrinks Leaflet's 256px tiles and leaves the map blank. */
        .survey-proto .bm-map img, .survey-proto .bm-map .leaflet-tile { max-width: none !important; max-height: none !important; }
        .survey-proto .bm-map.leaflet-container { font: inherit; }
        /* No light grid lines between tiles (user, 2026-11-03): Leaflet 1.9 adds tile edges together (plus-lighter), which shows
           every seam as a bright line; tiles are drawn normally instead and overlap the next by half a pixel, so no gap opens at a browser zoom. */
        .survey-proto .bm-map img.leaflet-tile { mix-blend-mode: normal !important; width: 256.5px !important; height: 256.5px !important; }
        .survey-proto .bm-map .leaflet-control-attribution { font-size: 9px; line-height: 1.4; padding: 0 5px; background: rgba(255,255,255,.55); color: #6b7280; }
        .survey-proto .bm-map .leaflet-control-attribution a { color: #6b7280; }
        .survey-proto .bm-switch { background: #fff; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.25); display: flex; overflow: hidden; }
        .survey-proto .bm-switch button { border: 0; background: #fff; padding: 6px 12px; font-size: 13px; cursor: pointer; color: #374151; }
        .survey-proto .bm-switch button.on { background: var(--primary, #be185d); color: #fff; font-weight: 600; }
        .survey-proto .bm-legend { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; margin-bottom: 10px; font-size: 13px; color: var(--gray-700, #374151); }
        .survey-proto .bm-legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 5px; vertical-align: -1px; }
    </style>
@endpush

@section('survey-content')
<div class="bm-legend">
    <strong>{{ number_format($areas->count()) }}</strong>&nbsp;map request area{{ $areas->count() === 1 ? '' : 's' }}
    @foreach (\App\Models\Sdi\SdiMapRequest::STATUS_LABELS as $k => $label)
        <span><i style="background:{{ $statusColours[$k] }};"></i>{{ $label }}</span>
    @endforeach
</div>
<div id="bmMap" class="bm-map"></div>
@if ($areas->isEmpty())
    <p class="helper-text" style="margin-top:8px;">No SDI map request has an area drawn yet. Requests made with Map Request appear here.</p>
@endif
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="{{ asset('js/survey/sdi-basemaps.js') }}?v=2"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var map = L.map('bmMap').setView([12.0, 8.52], 11); // Kano
        var basemaps = SdiMapRequestBasemaps(map);
        basemaps.show('satellite');

        // Satellite / Streets / Terrain switch.
        var Switch = L.Control.extend({
            options: { position: 'topright' },
            onAdd: function () {
                var box = L.DomUtil.create('div', 'bm-switch');
                [['satellite', 'Satellite'], ['streets', 'Streets'], ['terrain', 'Terrain']].forEach(function (b) {
                    var btn = L.DomUtil.create('button', b[0] === 'satellite' ? 'on' : '', box);
                    btn.type = 'button'; btn.textContent = b[1];
                    L.DomEvent.on(btn, 'click', function (e) {
                        L.DomEvent.stop(e);
                        basemaps.show(b[0]);
                        Array.prototype.forEach.call(box.children, function (c) { c.classList.toggle('on', c === btn); });
                    });
                });
                L.DomEvent.disableClickPropagation(box);
                return box;
            }
        });
        map.addControl(new Switch());

        var colours = @json($statusColours);
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
        var group = L.featureGroup().addTo(map);
        @json($areas).forEach(function (a) {
            var c = colours[a.status] || '#0277bd';
            L.geoJSON(a.geometry, { style: { color: c, weight: 2, fillOpacity: 0.18 } })
                .bindPopup('<strong>' + esc(a.ref) + '</strong>' + (a.name ? '<br>' + esc(a.name) : '')
                    + (a.type ? '<br>Map type: ' + esc(a.type) : '') + (a.mda ? '<br>Requested by: ' + esc(a.mda) : '')
                    + '<br>Status: ' + esc(a.label) + (a.date ? ' · ' + esc(a.date) : '')
                    + '<br><a href="' + esc(a.url) + '">Open request</a>')
                .addTo(group);
        });
        if (group.getLayers().length) map.fitBounds(group.getBounds(), { maxZoom: 15, padding: [20, 20] });
    });
    </script>
@endpush
