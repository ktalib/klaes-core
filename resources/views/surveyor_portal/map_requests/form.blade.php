@extends(auth('surveyor')->check() ? 'surveyor_portal.layouts.portal' : 'surveyor_portal.layouts.public')

@section('title', 'Request Map — KNSSGOF Portal')
@section('heading', 'Request Map')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css" />
    <style>
        .mrq-layout { display: grid; grid-template-columns: minmax(320px, 420px) 1fr; gap: 16px; align-items: start; }
        @media (max-width: 900px) { .mrq-layout { grid-template-columns: 1fr; } #mrqMap { height: 400px !important; } }
        #mrqMap { height: 560px; border-radius: 8px; border: 1px solid #B8BEC5; z-index: 0; font: inherit; }
        /* Tailwind's preflight shrinks Leaflet's tiles and markers. */
        #mrqMap img { max-width: none !important; max-height: none !important; }
        /* No light grid lines between tiles: as on the staff map (see survey_module/sdi/_head). */
        #mrqMap img.leaflet-tile { mix-blend-mode: normal !important; width: 256.5px !important; height: 256.5px !important; }
        #mrqMap .leaflet-control-attribution { font-size: 9px; }
        .mrq-points { columns: 2; font-family: ui-monospace, monospace; font-size: 12px; margin: 6px 0 0; padding-left: 18px; list-style: decimal; }
    </style>
@endpush

@section('main')
    @php
        $me = $account;
        $formats = (array) old('output_formats', ['pdf']);
        $mdaOther = \App\Http\Controllers\SurveyorPortal\MapRequestController::MDA_OTHER;
        $mdaPick = (string) old('mda_id');
        $isOther = $mdaPick === $mdaOther;
    @endphp
    <form method="POST" id="mrqForm" enctype="multipart/form-data" action="{{ route('surveyor-portal.map-requests.store') }}">
        @csrf
        <div class="mrq-layout">
            <div class="ks-card p-6 space-y-4">
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="ks-label" for="contact_name">Your Name <span class="ks-req">*</span></label>
                        <input class="ks-input" id="contact_name" name="contact_name" value="{{ old('contact_name', $me?->name) }}" maxlength="200" required>
                    </div>
                    <div>
                        <label class="ks-label" for="contact_phone">Phone <span class="ks-req">*</span></label>
                        <input class="ks-input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $me?->phone) }}" maxlength="50" required>
                    </div>
                    <div>
                        <label class="ks-label" for="contact_email">Email (optional)</label>
                        <input class="ks-input" id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $me?->email) }}" maxlength="255">
                    </div>
                </div>
                <div>
                    <label class="ks-label" for="mda_id">MDA <span class="ks-req">*</span></label>
                    <select class="ks-input" id="mda_id" name="mda_id" required>
                        <option value="">Select…</option>
                        @foreach ($mdas->groupBy(fn ($x) => $x->category?->name ?? 'Unsorted') as $cat => $list)
                            <optgroup label="{{ $cat }}">
                                @foreach ($list as $md)
                                    <option value="{{ $md->id }}" @selected($mdaPick === (string) $md->id)>{{ $md->displayName() }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                        <option value="{{ $mdaOther }}" @selected($isOther)>Other (specify)</option>
                    </select>
                    @error('mda_id')<div class="ks-error">{{ $message }}</div>@enderror
                </div>
                <div id="mrqOtherBox" @style(['display:none' => ! $isOther])>
                    <label class="ks-label" for="requester_other">Requesting Body <span class="ks-req">*</span></label>
                    <input class="ks-input" id="requester_other" name="requester_other" value="{{ old('requester_other') }}" maxlength="300" @required($isOther) @disabled(! $isOther)>
                    @error('requester_other')<div class="ks-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="ks-label" for="map_type_id">Map Type <span class="ks-req">*</span></label>
                    <select class="ks-input" id="map_type_id" name="map_type_id" required>
                        <option value="">Select…</option>
                        @foreach ($mapTypes as $t)
                            <option value="{{ $t->id }}" @selected((string) old('map_type_id') === (string) $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="ks-label" for="map_name">Map Name <span class="ks-req">*</span></label>
                    <input class="ks-input" id="map_name" name="map_name" value="{{ old('map_name') }}" maxlength="300" required>
                </div>
                <div>
                    <label class="ks-label" for="map_reference">Map Reference</label>
                    <input class="ks-input" id="map_reference" name="map_reference" value="{{ old('map_reference') }}" maxlength="120" placeholder="Letter, file, plot or survey reference">
                </div>
                <div>
                    <label class="ks-label" for="map_description">Description</label>
                    <textarea class="ks-input" id="map_description" name="map_description" rows="3" maxlength="2000">{{ old('map_description') }}</textarea>
                </div>
                <div>
                    <span class="ks-label">Formats Required <span class="ks-req">*</span></span>
                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        @foreach (\App\Models\Sdi\SdiMapRequest::FORMATS as $k => $l)
                            <label class="flex items-center gap-2"><input type="checkbox" name="output_formats[]" value="{{ $k }}" @checked(in_array($k, $formats, true))> {{ $l }}</label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="ks-label" for="request_file">Scanned Map or Sketch</label>
                    <input class="ks-input" id="request_file" type="file" name="request_file" accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.dwg,.dxf,.zip,.kml,.kmz">
                    <p class="ks-help" style="margin:4px 0 0;">Optional. PDF, image, DWG/DXF, KML/KMZ or ZIP, up to 50 MB.</p>
                </div>
                <div class="flex justify-end"><button class="ks-btn ks-btn-primary">Send Map Request</button></div>
            </div>

            <div class="ks-card p-6">
                <span class="ks-label">Area to be Mapped <span class="ks-req">*</span></span>
                <p class="ks-help" id="mrqHint" style="margin:0 0 6px;">Use the rectangle or polygon tool to mark the area. Drawing a new shape replaces the previous one.</p>
                <div id="mrqMap"></div>
                @error('geometry_geojson')<div class="ks-error">{{ $message }}</div>@enderror
                <div class="text-sm font-semibold" style="margin-top:10px;">Boundary points (X / Y)</div>
                <div class="ks-help">X = longitude, Y = latitude (WGS 84). Saved with the request.</div>
                <ol class="mrq-points" id="mrqPoints"></ol>
                <input type="hidden" name="geometry_geojson" id="mrqGeo" value="{{ old('geometry_geojson') }}">
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script src="{{ asset('js/survey/sdi-basemaps.js') }}?v=2"></script>
<script>
// MDA "Other" reveals a required box; any other choice hides, clears and disables it.
(function () {
    var pick = document.getElementById('mda_id'), wrap = document.getElementById('mrqOtherBox'), input = document.getElementById('requester_other');
    pick.addEventListener('change', function () {
        var on = pick.value === @json($mdaOther);
        wrap.style.display = on ? '' : 'none';
        input.disabled = !on;
        input.required = on;
        if (on) input.focus(); else input.value = '';
    });
})();
</script>
<script>
(function () {
    var box = document.getElementById('mrqMap'), hint = document.getElementById('mrqHint');
    // The area is required, so without the map the request cannot go; say so instead of a blank box.
    if (!window.L || !L.Control.Draw) {
        box.style.display = 'none';
        hint.textContent = 'The map could not load, and the area must be drawn on it. Check your connection and reload this page.';
        hint.style.color = 'var(--ks-danger)';
        return;
    }

    var map = L.map(box, { center: [12.0022, 8.5920], zoom: 10, minZoom: 6, maxZoom: 19 });
    map.setMaxBounds([[9.0, 6.0], [14.5, 11.5]]);
    var basemaps = SdiMapRequestBasemaps(map), current = 'satellite';
    basemaps.show(current);

    var Switch = L.Control.extend({
        options: { position: 'topright' },
        onAdd: function () {
            var bar = L.DomUtil.create('div', 'leaflet-bar');
            bar.style.cssText = 'display:flex;background:#fff;overflow:hidden;';
            [['satellite', 'Satellite'], ['streets', 'Streets'], ['terrain', 'Terrain']].forEach(function (x) {
                var btn = L.DomUtil.create('a', '', bar);
                btn.href = '#'; btn.textContent = x[1]; btn.setAttribute('role', 'button');
                btn.style.cssText = 'width:auto;padding:0 10px;font-size:12px;line-height:30px;height:30px;' + (x[0] === current ? 'background:#0B3A7E;color:#fff;font-weight:600;' : '');
                L.DomEvent.on(btn, 'click', function (e) {
                    L.DomEvent.stop(e);
                    current = x[0];
                    basemaps.show(current);
                    Array.prototype.forEach.call(bar.children, function (c) { var on = c === btn; c.style.background = on ? '#0B3A7E' : ''; c.style.color = on ? '#fff' : ''; c.style.fontWeight = on ? '600' : ''; });
                });
            });
            L.DomEvent.disableClickPropagation(bar);
            return bar;
        }
    });
    map.addControl(new Switch());

    var drawn = new L.FeatureGroup().addTo(map);
    map.addControl(new L.Control.Draw({
        draw: { rectangle: true, polygon: true, polyline: false, circle: false, circlemarker: false, marker: false },
        edit: { featureGroup: drawn }
    }));
    var geo = document.getElementById('mrqGeo'), list = document.getElementById('mrqPoints');

    function points(feature) {
        list.replaceChildren();
        var ring = feature ? feature.geometry.coordinates[0].slice(0, -1) : [];
        if (!ring.length) { var none = document.createElement('li'); none.className = 'ks-help'; none.textContent = 'Draw an area to see its points.'; list.appendChild(none); return; }
        ring.forEach(function (c, i) { var li = document.createElement('li'); li.textContent = 'P' + (i + 1) + '  X ' + Number(c[0]).toFixed(6) + '  Y ' + Number(c[1]).toFixed(6); list.appendChild(li); });
    }
    function capture() {
        var fc = drawn.toGeoJSON();
        geo.value = fc.features.length ? JSON.stringify(fc.features[0]) : '';
        hint.textContent = fc.features.length ? 'Area captured. Draw again to replace it.' : 'Use the rectangle or polygon tool to mark the area. Drawing a new shape replaces the previous one.';
        points(fc.features[0] || null);
    }
    map.on(L.Draw.Event.CREATED, function (e) { drawn.clearLayers(); drawn.addLayer(e.layer); capture(); });
    map.on(L.Draw.Event.EDITED, capture);
    map.on(L.Draw.Event.DELETED, capture);

    // Back from a failed save: the area stays.
    if (geo.value) {
        try { L.geoJSON(JSON.parse(geo.value)).eachLayer(function (l) { drawn.addLayer(l); }); } catch (e) {}
        if (drawn.getLayers().length) { map.fitBounds(drawn.getBounds(), { maxZoom: 17 }); capture(); }
    }
    if (!drawn.getLayers().length) points(null);

    document.getElementById('mrqForm').addEventListener('submit', function (e) {
        if (!geo.value) { e.preventDefault(); alert('Draw the area to be mapped before sending.'); }
    });
})();
</script>
@endpush
