@extends('surveyor_portal.layouts.portal')

@section('title', 'Request Beacons — KSSGOF Portal')
@section('heading', 'Request Beacons')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        #brqMap { height: 340px; border-radius: 8px; border: 1px solid #B8BEC5; z-index: 0; font: inherit; }
        /* Tailwind's preflight shrinks Leaflet's tiles and markers. */
        #brqMap img { max-width: none !important; max-height: none !important; }
        #brqMap .leaflet-control-attribution { font-size: 9px; }
        .ks-district-select + .select2-container { width: 100% !important; }
        .ks-district-select + .select2-container .select2-selection--single { height: 42px; border: 1px solid #B8BEC5; border-radius: 8px; }
        .ks-district-select + .select2-container .select2-selection__rendered { line-height: 40px; padding-left: 12px; color: var(--ks-ink); font-size: 14px; }
        .ks-district-select + .select2-container .select2-selection__arrow { height: 40px; }
        .ks-district-select + .select2-container.select2-container--focus .select2-selection--single,
        .ks-district-select + .select2-container.select2-container--open .select2-selection--single { border-color: var(--ks-gold); }
    </style>
@endpush

@section('main')
    @php $me = auth('surveyor')->user(); @endphp
    <form method="POST" action="{{ route('surveyor-portal.requests.store') }}" class="ks-card p-6 space-y-4" style="max-width:720px;">
        @csrf
        <div>
            <span class="ks-label">Beacon Type</span>
            <div class="ks-input" style="background:#F1F3F5;">{{ $me->type_label }}</div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <span class="ks-label">Company</span>
                <div class="ks-input" style="background:#F1F3F5;">{{ $me->company_name ?: '—' }}</div>
            </div>
            <div>
                <span class="ks-label">Surveyor</span>
                <div class="ks-input" style="background:#F1F3F5;">{{ $me->name }}</div>
            </div>
        </div>
        <div>
            <label class="ks-label" for="quantity">Number of Beacons <span class="ks-req">*</span></label>
            <input class="ks-input" id="quantity" type="number" name="quantity" min="1" max="{{ \App\Services\Survey\BeaconRequests::MAX_QUANTITY }}" value="{{ old('quantity') }}" required>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="ks-label" for="plot_no">Plot No.</label>
                <input class="ks-input" id="plot_no" name="plot_no" value="{{ old('plot_no') }}" maxlength="100">
            </div>
            <div>
                <label class="ks-label" for="district">District</label>
                <select class="ks-input ks-district-select" id="district" name="district" data-url="{{ route('surveyor-portal.lookup.districts') }}">
                    <option value="">Search or select district…</option>
                    @if (old('district'))
                        <option value="{{ old('district') }}" selected>{{ old('district') === 'Other' ? 'Other (specify)' : old('district') }}</option>
                    @endif
                </select>
                <p class="ks-error" id="districtSearchError" role="alert" style="display:none;">District search is unavailable. Reload the page or contact support.</p>
                <input class="ks-input" id="district_other" name="district_other" value="{{ old('district_other') }}" maxlength="255" placeholder="Specify district" style="margin-top:6px;{{ old('district') === 'Other' ? '' : 'display:none;' }}" @if (old('district') === 'Other') required @else disabled @endif>
                @error('district')<p class="ks-error">{{ $message }}</p>@enderror
                @error('district_other')<p class="ks-error">{{ $message }}</p>@enderror
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
            <p class="ks-help" style="margin:0 0 6px;">Selecting an LGA places the pin near its approximate center. Drag the pin to the exact survey location.</p>
            <div id="brqMap"></div>
            <p class="ks-help" style="margin:6px 0 0;">Approximate center data: geoBoundaries (GRID3), via <a href="https://github.com/andresgmg/World-GeoJSON" target="_blank" rel="noopener">World GeoJSON</a>, CC BY 4.0.</p>
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    var district = document.getElementById('district'), districtOther = document.getElementById('district_other');
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(district).select2({
            width: '100%',
            placeholder: 'Search or select district…',
            ajax: {
                url: district.dataset.url,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                processResults: function (data) { return data; },
                cache: true
            }
        });
        jQuery(district).on('change', function () {
            var other = district.value === 'Other';
            districtOther.style.display = other ? '' : 'none';
            districtOther.disabled = !other;
            districtOther.required = other;
            if (!other) districtOther.value = '';
        });
    } else {
        district.disabled = true;
        districtOther.disabled = true;
        document.getElementById('districtSearchError').style.display = '';
    }

    var box = document.getElementById('brqMap');
    if (!box) return;
    var lat = document.getElementById('brqLat'), lng = document.getElementById('brqLng'), text = document.getElementById('brqPinText'),
        clear = document.getElementById('brqClear'), locate = document.getElementById('brqLocate');
    // The map is optional: without it (no connection to the map server) the form still sends.
    if (!window.L) { box.style.display = 'none'; locate.hidden = true; text.textContent = 'The map could not load. You can still send the request.'; return; }

    var map = L.map(box, { center: [12.0022, 8.5920], zoom: 10, minZoom: 6, maxZoom: 19 });
    var lgaCenters = @json(\App\Services\Survey\KanoLgaMapCenters::CENTERS), lga = document.getElementById('lga');
    var street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' });
    var satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Imagery &copy; Esri' }).addTo(map);
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
    function placeAtLga(name) {
        var center = lgaCenters[name];
        if (!center) return;
        var ll = L.latLng(center[0], center[1]);
        place(ll);
        map.setView(ll, 12);
        text.textContent = 'Pin placed near the center of ' + name + '. Drag it to the exact survey location.';
    }
    map.on('click', function (e) { place(e.latlng); });
    lga.addEventListener('change', function () { placeAtLga(lga.value); });
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
    } else if (lga.value) {
        placeAtLga(lga.value);
    }
})();
</script>
@endpush
