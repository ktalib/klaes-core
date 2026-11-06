@extends('surveyor_portal.layouts.portal')

@section('title', 'Request Beacons — KSSGOF Portal')
@section('heading', 'Request Beacons')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        #brqMap { height: 340px; border-radius: 8px; border: 1px solid #B8BEC5; z-index: 0; font: inherit; }
        /* Tailwind's preflight shrinks Leaflet's tiles and markers. */
        #brqMap img { max-width: none !important; max-height: none !important; }
        #brqMap .leaflet-control-attribution { font-size: 9px; }
    </style>
@endpush

@section('main')
    @php $me = auth('surveyor')->user(); $type = old('layout_type', $me->surveyor_type); @endphp
    <form method="POST" action="{{ route('surveyor-portal.requests.store') }}" class="ks-card p-6 space-y-4" style="max-width:720px;">
        @csrf
        <div>
            <span class="ks-label">Survey Type <span class="ks-req">*</span></span>
            <div class="flex gap-6 text-sm">
                @foreach (['Government' => 'Public', 'Private' => 'Private'] as $v => $l)
                    <label class="flex items-center gap-2"><input type="radio" name="layout_type" value="{{ $v }}" required @checked($type === $v)> {{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <span class="ks-label">Surveyor</span>
                <div class="ks-input" style="background:#F1F3F5;">{{ $me->name }}</div>
            </div>
            <div>
                <span class="ks-label">Company</span>
                <div class="ks-input" style="background:#F1F3F5;">{{ $me->company_name ?: '—' }}</div>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="job_name">Name of Survey <span class="ks-req">*</span></label>
                <input class="ks-input" id="job_name" name="job_name" value="{{ old('job_name') }}" maxlength="255" required>
            </div>
            <div>
                <label class="ks-label" for="quantity">Number of Beacons <span class="ks-req">*</span></label>
                <input class="ks-input" id="quantity" type="number" name="quantity" min="1" max="{{ \App\Services\Survey\BeaconRequests::MAX_QUANTITY }}" value="{{ old('quantity', 4) }}" required>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="ks-label" for="plot_no">Plot No.</label>
                <input class="ks-input" id="plot_no" name="plot_no" value="{{ old('plot_no') }}" maxlength="100">
            </div>
            <div>
                <label class="ks-label" for="district">District</label>
                <input class="ks-input" id="district" name="district" value="{{ old('district') }}" maxlength="255">
            </div>
            <div>
                <label class="ks-label" for="lga">LGA</label>
                <select class="ks-input" id="lga" name="lga">
                    <option value="">Select LGA…</option>
                    @foreach ($lgas as $l)<option value="{{ $l }}" @selected(old('lga') === $l)>{{ $l }}</option>@endforeach
                </select>
            </div>
        </div>
        {{-- Where the survey is (user, 2026-11-06). Optional, as the location fields above are. --}}
        <div>
            <span class="ks-label">Location on the Map</span>
            <p class="ks-help" style="margin:0 0 6px;">Click the map where the survey is. Drag the pin to adjust it.</p>
            <div id="brqMap"></div>
            <div class="flex flex-wrap items-center justify-between gap-3" style="margin-top:6px;">
                <span class="ks-help" id="brqPinText">No pin yet.</span>
                <span class="flex gap-2">
                    <button type="button" class="ks-btn ks-btn-light" id="brqLocate" style="padding:6px 12px;">Use my location</button>
                    <button type="button" class="ks-btn ks-btn-light" id="brqClear" style="padding:6px 12px;" hidden>Remove pin</button>
                </span>
            </div>
            <input type="hidden" name="latitude" id="brqLat" value="{{ old('latitude') }}">
            <input type="hidden" name="longitude" id="brqLng" value="{{ old('longitude') }}">
            @error('latitude')<div class="ks-error">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="ks-label" for="remarks">Remarks</label>
            <textarea class="ks-input" id="remarks" name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea>
        </div>
        <div class="flex justify-end"><button class="ks-btn ks-btn-primary">Send to the Surveyor General</button></div>
    </form>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
(function () {
    var box = document.getElementById('brqMap');
    if (!box) return;
    var lat = document.getElementById('brqLat'), lng = document.getElementById('brqLng'), text = document.getElementById('brqPinText'),
        clear = document.getElementById('brqClear'), locate = document.getElementById('brqLocate');
    // The map is optional: without it (no connection to the map server) the form still sends.
    if (!window.L) { box.style.display = 'none'; locate.hidden = true; text.textContent = 'The map could not load. You can still send the request.'; return; }

    var map = L.map(box, { center: [12.0022, 8.5920], zoom: 10, minZoom: 6, maxZoom: 19 });
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Imagery &copy; Esri' });
    L.control.layers({ 'Street': street, 'Satellite': satellite }, null, { collapsed: false }).addTo(map);

    var pin = null;
    function place(ll) {
        if (!pin) {
            pin = L.marker(ll, { draggable: true, keyboard: false }).addTo(map);
            pin.on('dragend', function () { place(pin.getLatLng()); });
        }
        pin.setLatLng(ll);
        lat.value = ll.lat.toFixed(7);
        lng.value = ll.lng.toFixed(7);
        text.textContent = 'Pin at ' + ll.lat.toFixed(6) + ', ' + ll.lng.toFixed(6);
        clear.hidden = false;
    }
    map.on('click', function (e) { place(e.latlng); });
    clear.addEventListener('click', function () {
        if (pin) { map.removeLayer(pin); pin = null; }
        lat.value = ''; lng.value = '';
        text.textContent = 'No pin yet.';
        clear.hidden = true;
    });
    locate.addEventListener('click', function () {
        if (!navigator.geolocation) { text.textContent = 'This browser cannot give your location. Click the map instead.'; return; }
        navigator.geolocation.getCurrentPosition(function (p) {
            var ll = L.latLng(p.coords.latitude, p.coords.longitude);
            place(ll);
            map.setView(ll, Math.max(map.getZoom(), 16));
        }, function () { text.textContent = 'Your location is not available. Click the map instead.'; });
    });
    // Back from a failed save: the pin stays.
    if (lat.value && lng.value) {
        var back = L.latLng(parseFloat(lat.value), parseFloat(lng.value));
        place(back);
        map.setView(back, 16);
    }
})();
</script>
@endpush
