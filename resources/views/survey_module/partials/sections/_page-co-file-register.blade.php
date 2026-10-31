@php
    use App\Models\Survey\SurveyCoFile;

    $editing = $coFile->exists;
    $capture = $coFile->source === SurveyCoFile::SOURCE_CAPTURED;
    $readonlyStyle = 'background:var(--gray-100);color:var(--gray-700);';

    // CO File No = Prefix CO + Serial No + Year (user, 2026-10-30), shown as three boxes.
    $shownNo = $editing || $capture ? (string) $coFile->co_file_no : (string) $nextNo;
    preg_match('/^CO-(\d+)-(\d{4})$/', $shownNo, $noParts);
    $noSerial = old('co_serial', $noParts[1] ?? '');
    $noYear   = old('co_year', $noParts[2] ?? '');
@endphp

@include('survey_module.partials._flash')

{{-- Two modes (user, 2026-10-30): Commission a new CO file, or Capture an existing paper one. --}}
@unless ($editing)
    <style>
        .survey-proto .co-modes { display:inline-flex; gap:4px; padding:4px; background:var(--gray-100,#f3f4f6); border-radius:10px; margin-bottom:14px; }
        .survey-proto .co-modes a { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; font-weight:600; font-size:14px; color:var(--gray-600,#4b5563); text-decoration:none; }
        .survey-proto .co-modes a.on { background:#fff; color:var(--primary); box-shadow:0 1px 3px rgba(0,0,0,.12); }
    </style>
    {{-- Each form shows only its own mode (user, 2026-10-30): the two lists in the sidebar each open their own form. --}}
    <nav class="co-modes" aria-label="CO file mode">
        @if ($capture)
            <a href="{{ route('survey-module.compensation.co-files.create', ['mode' => SurveyCoFile::SOURCE_CAPTURED]) }}" class="on" aria-current="page">
                <i class="fas fa-keyboard"></i> Capture CO File
            </a>
        @else
            <a href="{{ route('survey-module.compensation.co-files.create') }}" class="on" aria-current="page">
                <i class="fas fa-plus"></i> Commission CO File
            </a>
        @endif
    </nav>
@endunless

<form method="POST"
      action="{{ $editing
                  ? route('survey-module.compensation.co-files.update', $coFile)
                  : route('survey-module.compensation.co-files.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    @unless ($editing)
        <input type="hidden" name="source" value="{{ $coFile->source }}" />
    @endunless

    <div class="form-container">
        <div class="form-body">
            <style>
                .survey-proto .co-no { display: grid; grid-template-columns: 72px 1fr 110px; gap: 6px; }
                .survey-proto .co-no input { text-align: center; font-weight: 600; }
                .survey-proto .co-no .lbl { font-size: 11px; color: var(--gray-500); text-align: center; margin-top: 2px; }
                .survey-proto .co-idx { display: inline-flex; align-items: center; gap: 4px; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
                .survey-proto .co-idx.yes { background: #dcfce7; color: #166534; }
                .survey-proto .co-idx.no { background: #fef3c7; color: #92400e; }
                .survey-proto .co-top-row { display:grid; grid-template-columns:minmax(300px,1.1fr) minmax(200px,.9fr) minmax(310px,1.2fr); gap:16px; }
                .survey-proto .co-sit-toggle { display:flex; align-items:center; gap:8px; min-height:38px; }
                .survey-proto .co-sit-toggle input { width:auto; margin:0; }
                .survey-proto .co-sit-toggle span { margin:0; }
                .survey-proto .co-location-map { height:320px; border:1px solid var(--gray-300); border-radius:8px; overflow:hidden; background:var(--gray-100); }
                .survey-proto .co-location-map img { max-width:none !important; max-height:none !important; }
                @media (max-width: 900px) { .survey-proto .co-top-row { grid-template-columns:1fr 1fr; } .survey-proto .co-related-cell { grid-column:1 / -1; } }
                @media (max-width: 680px) { .survey-proto .co-top-row { grid-template-columns:1fr; } .survey-proto .co-related-cell { grid-column:auto; } }
            </style>

            <div class="co-top-row">
                <div class="form-group">
                    <label>CO File No. @if ($capture)<span class="required">*</span>@endif</label>
                    <div class="co-no">
                        <div>
                            <input type="text" value="CO" readonly style="{{ $readonlyStyle }}" aria-label="Prefix" />
                            <div class="lbl">Prefix</div>
                        </div>
                        <div>
                            @if ($capture)
                                <input type="text" name="co_serial" value="{{ $noSerial }}" inputmode="numeric" maxlength="7"
                                       placeholder="e.g. 9" aria-label="Serial No" required />
                            @else
                                <input type="text" value="{{ $noSerial }}" readonly style="{{ $readonlyStyle }}" aria-label="Serial No" />
                            @endif
                            <div class="lbl">Serial No.</div>
                        </div>
                        <div>
                            @if ($capture)
                                <input type="number" name="co_year" value="{{ $noYear }}" min="{{ SurveyCoFile::FIRST_YEAR }}" max="{{ date('Y') }}"
                                       placeholder="e.g. 2018" aria-label="Year" required />
                            @else
                                <input type="text" value="{{ $noYear }}" readonly style="{{ $readonlyStyle }}" aria-label="Year" />
                            @endif
                            <div class="lbl">Year</div>
                        </div>
                    </div>
                    @foreach (['co_file_no', 'co_serial', 'co_year'] as $k)
                        @error($k)<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
                    @endforeach
                    <p class="helper-text">
                        @if ($capture)
                            The paper file's serial and year; saved as <strong>CO-serial-year</strong>, e.g. CO-9-2018 (no leading zeros).
                        @else
                            {{ $editing ? 'Generated when commissioned; cannot be changed.' : 'Next number for this year (' . $shownNo . '); confirmed when you save.' }}
                        @endif
                    </p>
                </div>

            @php($hasSitFile = (string) old('has_sit_file', $coFile->related_file_no ? '1' : '0') === '1')
            <div class="form-group co-related-cell">
                <label for="coHasSitFile">Has Related File <span class="required">*</span></label>
                <div class="co-sit-toggle">
                    <input type="checkbox" name="has_sit_file" id="coHasSitFile" value="1" @checked($hasSitFile) />
                    <span id="coHasSitFileAnswer">{{ $hasSitFile ? 'Yes' : 'No' }}</span>
                </div>
            {{-- Related SIT, GKN or Misc KN file, indexed or not, from the global file number selector (CR 30102026 item 1). --}}
                <div id="coRelatedFields" @class(['hidden' => ! $hasSitFile])>
                <div id="coRelated" data-lookup="{{ route('survey-module.compensation.co-files.related-lookup') }}">
                    <label>Related File No. (SIT, GKN or Misc KN) <span class="required">*</span></label>
                    <div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">
                        <input type="text" name="related_file_no" id="coRelatedNo" readonly
                               value="{{ old('related_file_no', $coFile->related_file_no) }}"
                               placeholder="No related file selected"
                               style="flex:1;min-width:200px;font-weight:700;background:var(--gray-100);cursor:not-allowed;" />
                        <button type="button" class="btn btn-primary" id="coRelatedPick" style="white-space:nowrap;">
                            <i class="fas fa-folder-open"></i> Select Related File
                        </button>
                        <button type="button" class="btn btn-secondary" id="coRelatedClear" style="white-space:nowrap;">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                    @error('related_file_no')<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
                    <p class="helper-text" id="coRelatedStatus">Pick a SIT, GKN or Misc KN file, indexed or not. When it is indexed, its plot, district and LGA fill in below.</p>
                </div>
                </div>
            </div>

                <div class="form-group">
                    <label>Commission Date @unless ($capture)<span class="required">*</span>@endunless</label>
                    <input type="date" name="commission_date"
                           value="{{ old('commission_date', optional($coFile->commission_date)->format('Y-m-d')) }}"
                           @unless ($capture) required @endunless />
                </div>
            </div>

            {{-- File / Job Title and Status share a row (user, 2026-10-26) --}}
            <div class="form-grid cols-3">
                <div class="form-group span-2">
                    <label>File / Job Title <span class="required">*</span></label>
                    <input type="text" name="title" id="coTitle" value="{{ old('title', $coFile->title) }}" maxlength="255"
                           placeholder="e.g. Tundun Wada Government Acquisition" required />
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status">
                        @foreach (SurveyCoFile::statusesFor($coFile->source) as $s)
                            <option value="{{ $s }}" @selected(old('status', $coFile->status) === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Location: Plot No., District & LGA, State; the full location reads District, LGA, State (user, 2026-10-30). --}}
            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $coFile,
                'legend' => 'Location',
                'fields' => ['plot', 'district', 'lga', 'state'],
            ])

            <div class="form-grid">
                <div class="form-group full">
                    <label>Location Map</label>
                    <div id="co-location-map" class="co-location-map" role="img" aria-label="Map preview based on selected district and LGA"></div>
                    <p class="helper-text" id="co-location-map-status">Select a district, LGA, or both to preview the area on the map.</p>
                </div>
            </div>

            {{-- Remarks sit after Property Location (auto) (user, 2026-10-28) --}}
            <div class="form-grid">
                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" maxlength="4000" placeholder="Optional notes…">{{ old('remarks', $coFile->remarks) }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.compensation.co-files') }}">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check"></i>
                        {{ $editing ? 'Save Changes' : ($capture ? 'Capture CO File' : 'Commission CO File') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

@once
@push('scripts')
    {{-- The shared selector, outside the .survey-proto wrapper so the prototype CSS cannot reach it. --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush
@endonce
@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush
@push('scripts')
@once
    @include('partials.maps_scripts')
@endonce
<script>
(function () {
    'use strict';
    var el = document.getElementById('co-location-map');
    var form = el && el.closest('form');
    var status = document.getElementById('co-location-map-status');
    if (!el || !form || !window.L || !window.google || !google.maps || !google.maps.Geocoder) return;

    var map = L.map(el, { scrollWheelZoom: false }).setView([12.0022, 8.5920], 9);
    L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics'
    }).addTo(map);
    var marker = null;
    var geocoder = new google.maps.Geocoder();
    var timer = null;
    var requestNo = 0;

    function selectedValues(selector) {
        var select = form.querySelector(selector);
        if (!select) return { values: [], hasOther: false };
        var hasOther = false;
        var values = Array.prototype.map.call(select.selectedOptions, function (option) {
            var value = (option.value || '').trim();
            if (value.toLowerCase() === 'other') { hasOther = true; return ''; }
            return value;
        }).filter(Boolean);
        return { values: values, hasOther: hasOther };
    }

    function locationParts() {
        var districtSelection = selectedValues('[name="prop_district[]"]');
        var lgaSelection = selectedValues('[name="prop_lga[]"]');
        var districts = districtSelection.values;
        var lgas = lgaSelection.values;
        if (districtSelection.hasOther) {
            var otherDistrict = form.querySelector('[name="prop_district_other"]');
            if (otherDistrict && otherDistrict.value.trim()) districts.push(otherDistrict.value.trim());
        }
        return { districts: districts, lgas: lgas };
    }

    function geocodeNext(queries, index, zoom, token) {
        if (token !== requestNo) return;
        if (index >= queries.length) {
            status.textContent = 'No map location was found for the selected district or LGA.';
            return;
        }
        geocoder.geocode({ address: queries[index] }, function (results, resultStatus) {
            if (token !== requestNo) return;
            if (resultStatus === 'OK' && results && results[0]) {
                var point = results[0].geometry.location;
                var latlng = [point.lat(), point.lng()];
                if (!marker) marker = L.marker(latlng).addTo(map);
                else marker.setLatLng(latlng);
                map.setView(latlng, zoom);
                status.textContent = 'Map preview based on ' + queries[index].replace(/, Kano State, Nigeria$/, '') + '.';
                return;
            }
            geocodeNext(queries, index + 1, zoom, token);
        });
    }

    function updateMap() {
        var area = locationParts();
        var district = area.districts.join(', ');
        var lga = area.lgas.join(', ');
        var queries = [];
        if (district && lga) queries.push(district + ', ' + lga + ', Kano State, Nigeria');
        if (district) queries.push(district + ', Kano State, Nigeria');
        if (lga) queries.push(lga + ', Kano State, Nigeria');
        queries = queries.filter(function (query, index) { return queries.indexOf(query) === index; });
        var token = ++requestNo;
        if (!queries.length) {
            status.textContent = 'Select a district, LGA, or both to preview the area on the map.';
            if (marker) { map.removeLayer(marker); marker = null; }
            map.setView([12.0022, 8.5920], 9);
            return;
        }
        status.textContent = 'Finding the selected area on the map…';
        geocodeNext(queries, 0, district && lga ? 12 : (district ? 13 : 10), token);
    }

    form.addEventListener('change', function (event) {
        if (event.target.matches('[name="prop_district[]"], [name="prop_lga[]"], [name="prop_district_other"]')) {
            window.clearTimeout(timer);
            timer = window.setTimeout(updateMap, 400);
        }
    });
    window.setTimeout(function () { map.invalidateSize(); updateMap(); }, 250);
})();
</script>
<script>
(function () {
    'use strict';
    var root = document.getElementById('coRelated');
    if (!root) return;
    var form = root.closest('form');
    var number = document.getElementById('coRelatedNo');
    var status = document.getElementById('coRelatedStatus');
    var hasSitFile = document.getElementById('coHasSitFile');
    var relatedFields = document.getElementById('coRelatedFields');
    var hasSitFileAnswer = document.getElementById('coHasSitFileAnswer');
    var filled = [];   // fields this pick filled, cleared when the pick changes

    function $j() { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; }
    function field(name) { return form.querySelector('[name="' + name + '"]'); }
    function say(html, isError) { status.innerHTML = html; status.style.color = isError ? '#b91c1c' : ''; }
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function isBlank(el) {
        if (!el) return false;
        if (el.tagName === 'SELECT') return !Array.prototype.some.call(el.options, function (o) { return o.selected && o.value; });
        return !el.value.trim();
    }
    function setSelect(el, value) {
        if (!el || !value) return false;
        var match = Array.prototype.find.call(el.options, function (o) { return o.value.toLowerCase() === String(value).toLowerCase(); });
        if (!match) { match = new Option(value, value); el.add(match); }
        Array.prototype.forEach.call(el.options, function (o) { o.selected = (o === match); });
        var $ = $j();
        if ($ && $(el).data('select2')) $(el).trigger('change'); else el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }
    function setText(el, value) {
        if (!el || !value) return false;
        el.value = value;
        el.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
    }
    // Only what this pick filled is undone; anything typed by hand stays.
    function clearFilled() {
        filled.forEach(function (f) {
            if (f.tagName === 'SELECT') {
                Array.prototype.forEach.call(f.options, function (o) { o.selected = false; });
                var $ = $j();
                if ($ && $(f).data('select2')) $(f).trigger('change'); else f.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                f.value = ''; f.dispatchEvent(new Event('input', { bubbles: true }));
            }
        });
        filled = [];
    }
    function updateSitFileVisibility(clearWhenOff) {
        var enabled = hasSitFile.checked;
        relatedFields.classList.toggle('hidden', !enabled);
        number.required = enabled;
        hasSitFileAnswer.textContent = enabled ? 'Yes' : 'No';
        if (!enabled && clearWhenOff) {
            clearFilled();
            number.value = '';
            say('Pick a SIT, GKN or Misc KN file, indexed or not. When it is indexed, its plot, district and LGA fill in below.');
        }
    }
    hasSitFile.addEventListener('change', function () { updateSitFileVisibility(true); });
    updateSitFileVisibility(false);
    function badge(d) {
        return d.indexed
            ? '<span class="co-idx yes">Indexed</span>'
            : '<span class="co-idx no">Not indexed</span>';
    }

    function lookup(fileNumber, fill) {
        say(esc(fileNumber) + ': checking the records…');
        return fetch(root.dataset.lookup + '?file_number=' + encodeURIComponent(fileNumber), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (fill && d.known) {
                    // Fill gaps only: what is already on the form wins.
                    var t = document.getElementById('coTitle');
                    if (isBlank(t) && setText(t, d.title)) filled.push(t);
                    var p = field('prop_plot');
                    if (isBlank(p) && setText(p, d.plot)) filled.push(p);
                    [['prop_district[]', d.district], ['prop_lga[]', d.lga]].forEach(function (x) {
                        var el = field(x[0]);
                        if (isBlank(el) && setSelect(el, x[1])) filled.push(el);
                    });
                }
                var what = d.indexed
                    ? (d.title ? ' · ' + esc(d.title) : '')
                    : (d.known ? ' · on the file number register, not yet indexed' : ' · not in the records yet');
                say(badge(d) + ' <strong>' + esc(fileNumber) + '</strong>' + what
                    + (fill && filled.length ? ' — details filled below; check them.' : ''));
            })
            .catch(function () { say(esc(fileNumber) + ' selected. Its index status could not be checked.', true); });
    }

    document.getElementById('coRelatedPick').addEventListener('click', function () {
        if (!window.GlobalFileNoModal) { say('The file number selector did not load. Refresh the page.', true); return; }
        window.GlobalFileNoModal.open({
            initialTab: 'sit',
            allowedTabs: ['sit', 'gkn'],
            callback: function (data) {
                var no = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                if (!no) return;
                clearFilled();
                number.value = no;
                lookup(no, true);
            }
        });
    });
    document.getElementById('coRelatedClear').addEventListener('click', function () {
        clearFilled();
        number.value = '';
        say('Pick a SIT, GKN or Misc KN file, indexed or not. When it is indexed, its plot, district and LGA fill in below.');
    });

    if (hasSitFile.checked && number.value.trim()) lookup(number.value.trim(), false);
})();
</script>
@endpush
