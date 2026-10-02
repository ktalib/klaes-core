{{--
    Map stack for KLAES.

    Leaflet + free, keyless tile sources (Esri World Imagery for satellite,
    OpenStreetMap for street view) standing in for the Google Maps JS API,
    which stopped working when the Google Cloud billing account was suspended.

    js/maps/leaflet-maps-shim.js re-implements the slice of `google.maps.*`
    that our pages call, so every existing map/marker/geocode call site keeps
    working unchanged. To move back to real Google Maps, replace the includes
    of this partial with the old maps.googleapis.com tag.

    Leaflet must load synchronously before the shim, hence no `defer`.

    Pages that already pull in Leaflet themselves (e.g. the Special Assignment
    field-data map, which also loads Leaflet.draw) must pass
    ['skipLeaflet' => true] so Leaflet is not loaded twice — a second copy
    replaces `L` and detaches any plugin already registered against the first.
--}}
@unless ($skipLeaflet ?? false)
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endunless
<script src="{{ asset('js/maps/leaflet-maps-shim.js') }}?v={{ @filemtime(public_path('js/maps/leaflet-maps-shim.js')) }}"></script>
