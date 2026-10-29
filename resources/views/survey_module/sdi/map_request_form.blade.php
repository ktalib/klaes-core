@extends('survey_module.layouts.klaes')

@section('page-title', ($m->exists ? 'Edit ' . $m->request_ref : 'New Map Request') . ' — SDI — KLAES')

@php
    use App\Http\Controllers\Survey\SdiMapRequestController;
    use App\Models\Sdi\SdiMapRequest;

    $PageTitle = $m->exists ? 'Edit Map Request ' . $m->request_ref : 'New Map Request';
    $PageDescription = 'Draw the area to be mapped — no coordinates to type in. The request goes to the Production Unit.';

    $mdaPick = old('mda_id', $m->exists ? ($m->mda_id ? (string) $m->mda_id : SdiMapRequestController::REQUESTER_OTHER) : '');
    $isOther = $mdaPick === SdiMapRequestController::REQUESTER_OTHER;
    $formats = (array) old('output_formats', $m->output_formats ?? ['pdf']);
    $grouped = $mdas->groupBy(fn ($x) => $x->category?->name ?? 'Other');
@endphp

@section('survey-content')
@include('survey_module.sdi._head', ['draw' => true])
@include('survey_module.partials._flash')

<style>
    .survey-proto .mrq-layout { display: grid; grid-template-columns: minmax(360px, 440px) 1fr; gap: 18px; align-items: start; }
    .survey-proto .mrq-map-col { position: sticky; top: 12px; }
    .survey-proto .mrq-card { background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px; padding: 18px; }
    .survey-proto .mrq-card .form-grid { grid-template-columns: 1fr 1fr; }
    .survey-proto .mrq-choices { display: flex; flex-wrap: wrap; gap: 8px 16px; padding: 4px 0; }
    .survey-proto .mrq-choices label { display: inline-flex; align-items: center; gap: 6px; font-weight: 400; cursor: pointer; }
    .survey-proto .form-group .mrq-choices input { width: auto; padding: 0; }
    .survey-proto .err { color: var(--danger, #dc2626); font-size: 12px; }
    #mrqMap { height: 620px; }
    @media (max-width: 1100px) { .survey-proto .mrq-layout { grid-template-columns: 1fr; } .survey-proto .mrq-map-col { position: static; } #mrqMap { height: 420px; } }
</style>

<form method="POST" id="mrqForm" action="{{ $m->exists ? route('survey-module.sdi.map-requests.update', $m) : route('survey-module.sdi.map-requests.store') }}">
    @csrf
    @if ($m->exists) @method('PUT') @endif

    <div class="mrq-layout">
        <div class="mrq-card">
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-building"></i><span>Requesting Body</span></div>
                <div class="form-group full">
                    <label>MDA <span class="required">*</span></label>
                    <select name="mda_id" id="mrqMda" required>
                        <option value="">— Select MDA —</option>
                        @foreach ($grouped as $cat => $list)
                            <optgroup label="{{ $cat }}">
                                @foreach ($list as $md)
                                    <option value="{{ $md->id }}" data-contact="{{ $md->contact_name }}" data-phone="{{ $md->contact_phone }}" data-email="{{ $md->contact_email }}"
                                            @selected($mdaPick === (string) $md->id)>{{ $md->displayName() }}{{ $md->is_active ? '' : ' — retired' }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                        <option value="{{ SdiMapRequestController::REQUESTER_OTHER }}" @selected($isOther)>Other (specify)</option>
                    </select>
                    @error('mda_id')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group full" id="mrqOtherBox" @style(['display:none' => ! $isOther])>
                    <label>Requesting Body <span class="required">*</span></label>
                    <input type="text" name="requester_other" id="mrqOther" maxlength="300" value="{{ old('requester_other', $m->requester_other) }}"
                           placeholder="e.g. Federal Ministry of Works, a private firm" @disabled(! $isOther) />
                    @error('requester_other')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group full">
                    <label>Contact Person <span class="required">*</span></label>
                    <input type="text" name="contact_name" id="mrqContact" maxlength="200" value="{{ old('contact_name', $m->contact_name) }}" required />
                    @error('contact_name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Phone <span class="required">*</span></label>
                    <input type="text" name="contact_phone" id="mrqPhone" maxlength="50" value="{{ old('contact_phone', $m->contact_phone) }}" required />
                    @error('contact_phone')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="contact_email" id="mrqEmail" maxlength="255" value="{{ old('contact_email', $m->contact_email) }}" />
                    @error('contact_email')<div class="err">{{ $message }}</div>@enderror
                </div>

                <div class="sv-group"><i class="fas fa-map"></i><span>Map Required</span></div>
                <div class="form-group full">
                    <label>Map Type <span class="required">*</span></label>
                    <select name="map_type_id" id="mrqType" required>
                        <option value="">— Select —</option>
                        @foreach ($mapTypes as $t)
                            <option value="{{ $t->id }}" data-basemap="{{ $t->previewBasemap() }}" data-landuse="{{ $t->isLandUse() ? 1 : 0 }}" @selected((string) old('map_type_id', $m->map_type_id) === (string) $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                    @error('map_type_id')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group full">
                    <label>Map Name <span class="required">*</span></label>
                    <input type="text" name="map_name" maxlength="300" value="{{ old('map_name', $m->map_name) }}" required placeholder="e.g. Land use map of Kumbotso layout" />
                    @error('map_name')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group full">
                    <label>Map Reference</label>
                    <input type="text" name="map_reference" maxlength="120" value="{{ old('map_reference', $m->map_reference) }}" placeholder="Letter, file, plot or survey reference" />
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea name="map_description" maxlength="2000" rows="3">{{ old('map_description', $m->map_description) }}</textarea>
                </div>
                <div class="form-group full">
                    <label>Formats Required <span class="required">*</span></label>
                    <div class="mrq-choices">
                        @foreach (SdiMapRequest::FORMATS as $k => $l)
                            <label><input type="checkbox" name="output_formats[]" value="{{ $k }}" @checked(in_array($k, $formats, true)) /> {{ $l }}</label>
                        @endforeach
                    </div>
                    @error('output_formats')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group full">
                    <label>Priority <span class="required">*</span></label>
                    <div class="mrq-choices">
                        @foreach (SdiMapRequest::PRIORITY_LABELS as $k => $l)
                            <label><input type="radio" name="priority" value="{{ $k }}" @checked(old('priority', $m->priority) === $k) required /> {{ $l }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="form-group">
                    <label>Date Received <span class="required">*</span></label>
                    <input type="date" name="received_date" max="{{ now()->toDateString() }}" value="{{ old('received_date', optional($m->received_date)->format('Y-m-d')) }}" required />
                    @error('received_date')<div class="err">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="{{ old('due_date', optional($m->due_date)->format('Y-m-d')) }}" />
                    @error('due_date')<div class="err">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="form-actions" style="margin-top:16px;">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ $m->exists ? route('survey-module.sdi.map-requests.show', $m) : route('survey-module.sdi.map-requests') }}"><i class="fas fa-times"></i> Cancel</a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit"><i class="fas fa-paper-plane"></i> {{ $m->exists ? 'Save Changes' : 'Submit Request' }}</button>
                </div>
            </div>
        </div>

        <div class="mrq-map-col">
            <div class="mrq-card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong><i class="fas fa-draw-polygon" style="color:var(--primary);"></i> Area to be mapped <span class="required" style="color:var(--danger);">*</span></strong>
                    <span class="sdi-chip" id="mrqBasemapLabel">Street map preview</span>
                </div>
                <div id="mrqMap" class="sdi-map"></div>
                <p class="helper-text" id="mrqHint" style="margin-top:6px;">Use the rectangle or polygon tool to mark the area. Drawing a new shape replaces the previous one.</p>
                @error('geometry_geojson')<div class="err">{{ $message }}</div>@enderror
                <div style="font-size:13px;font-weight:600;margin-top:8px;">Boundary points (X / Y)</div>
                <div class="helper-text">X = longitude, Y = latitude (WGS 84). Saved with the request.</div>
                <ol class="sdi-points" id="mrqPoints"></ol>
                <div id="mrqLandUse" style="display:none;margin-top:12px;padding-top:10px;border-top:1px solid var(--gray-200,#e5e7eb);"></div>
                <input type="hidden" name="geometry_geojson" id="mrqGeo" value="{{ old('geometry_geojson', $m->geometry_geojson) }}" />
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // MDA → contact backfill (rule 5): fill blanks from the registry, clear what the last pick filled.
    var mda = document.getElementById('mrqMda'), otherBox = document.getElementById('mrqOtherBox'), other = document.getElementById('mrqOther');
    var fields = { contact: document.getElementById('mrqContact'), phone: document.getElementById('mrqPhone'), email: document.getElementById('mrqEmail') };
    var filled = {};
    mda.addEventListener('change', function () {
        var o = mda.options[mda.selectedIndex], isOther = mda.value === @json(SdiMapRequestController::REQUESTER_OTHER);
        otherBox.style.display = isOther ? '' : 'none';
        other.disabled = !isOther; other.required = isOther;
        if (!isOther) other.value = '';
        Object.keys(fields).forEach(function (k) {
            var el = fields[k], v = (o && o.dataset[k]) || '';
            if (filled[k] && el.value === filled[k]) el.value = '';
            if (v && !el.value) el.value = v;
            filled[k] = v;
        });
    });
    if (mda.value === @json(SdiMapRequestController::REQUESTER_OTHER)) other.required = true;

    // Draw map (ALAES map-request create): one area per request, bbox recomputed on the server.
    var map = L.map('mrqMap', { center: [12.0022, 8.5920], zoom: 10, minZoom: 6, maxZoom: 19 });
    map.setMaxBounds([[9.0, 6.0], [14.5, 11.5]]);
    var basemaps = SdiMapRequestBasemaps(map), type = document.getElementById('mrqType'), label = document.getElementById('mrqBasemapLabel');
    // Land Use Map: indexed land use from File Indexing drawn over the map.
    var landuse = SdiLandUse(map, @json(route('survey-module.sdi.map-requests.land-use-points')), { summaryEl: document.getElementById('mrqLandUse') });
    function basemap() {
        var o = type.options[type.selectedIndex];
        label.textContent = basemaps.show(o && o.value ? o.dataset.basemap : 'streets') + ' preview';
        landuse.show(!!(o && o.dataset.landuse === '1'));
    }
    type.addEventListener('change', basemap); basemap();

    var drawn = new L.FeatureGroup().addTo(map);
    map.addControl(new L.Control.Draw({
        draw: { rectangle: true, polygon: true, polyline: false, circle: false, circlemarker: false, marker: false },
        edit: { featureGroup: drawn }
    }));
    var geo = document.getElementById('mrqGeo'), hint = document.getElementById('mrqHint'), list = document.getElementById('mrqPoints');

    function points(feature) {
        list.replaceChildren();
        var g = feature && (feature.geometry || feature), polys = g && g.type === 'Polygon' ? [g.coordinates] : (g && g.type === 'MultiPolygon' ? g.coordinates : []), pts = [];
        (polys || []).forEach(function (p) {
            var ring = (Array.isArray(p) ? p[0] : []) || [];
            if (ring.length > 1 && ring[0][0] === ring[ring.length - 1][0] && ring[0][1] === ring[ring.length - 1][1]) ring = ring.slice(0, -1);
            pts = pts.concat(ring);
        });
        if (!pts.length) { var li = document.createElement('li'); li.className = 'helper-text'; li.textContent = 'Draw an area to see its points.'; list.appendChild(li); return; }
        pts.forEach(function (c, i) { var li = document.createElement('li'); li.textContent = 'P' + (i + 1) + '  X ' + Number(c[0]).toFixed(6) + '  Y ' + Number(c[1]).toFixed(6); list.appendChild(li); });
    }
    function capture() {
        var fc = drawn.toGeoJSON();
        geo.value = fc.features.length ? JSON.stringify(fc.features[0]) : '';
        hint.textContent = fc.features.length ? 'Area captured. Draw again to replace it.' : 'Use the rectangle or polygon tool to mark the area. Drawing a new shape replaces the previous one.';
        points(fc.features[0] || null);
        landuse.summarize(fc.features[0] || null);
    }
    map.on(L.Draw.Event.CREATED, function (e) { drawn.clearLayers(); drawn.addLayer(e.layer); capture(); });
    map.on(L.Draw.Event.EDITED, capture);
    map.on(L.Draw.Event.DELETED, capture);

    if (geo.value) {
        try { L.geoJSON(JSON.parse(geo.value)).eachLayer(function (l) { drawn.addLayer(l); }); } catch (e) {}
        if (drawn.getLayers().length) { map.fitBounds(drawn.getBounds(), { maxZoom: 17 }); capture(); }
    }
    if (!drawn.getLayers().length) points(null);

    document.getElementById('mrqForm').addEventListener('submit', function (e) {
        if (!geo.value) { e.preventDefault(); alert('Draw the area to be mapped before submitting.'); }
    });
});
</script>
@endpush
@endsection
