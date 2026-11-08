@extends('surveyor_portal.layouts.portal')

@section('title', 'Requested Beacons — KNSSGOF Portal')
@section('heading', 'Requested Beacons')

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
        .ks-request-type-trigger { display:flex; width:100%; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; border:1px solid #B8BEC5; border-radius:8px; background:#fff; color:var(--ks-ink); text-align:left; cursor:pointer; }
        .ks-request-type-trigger:hover, .ks-request-type-trigger:focus-visible { border-color:var(--ks-blue); outline:2px solid rgba(11,58,126,.12); }
        .ks-request-type-dialog { width:min(540px,calc(100% - 28px)); max-width:540px; padding:0; border:0; border-radius:16px; background:transparent; color:var(--ks-ink); box-shadow:0 24px 70px rgba(13,25,40,.28); }
        .ks-request-type-dialog::backdrop { background:rgba(16,25,38,.56); backdrop-filter:blur(3px); }
        .ks-request-type-panel { padding:22px; border:1px solid var(--ks-line); border-radius:16px; background:#fff; }
        .ks-request-type-options { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; margin-top:17px; }
        .ks-request-type-option { display:flex; min-height:145px; flex-direction:column; align-items:flex-start; padding:16px; border:1px solid var(--ks-line); border-radius:12px; background:#fff; color:var(--ks-ink); text-align:left; cursor:pointer; transition:border-color .15s ease,background .15s ease,transform .15s ease; }
        .ks-request-type-option:hover, .ks-request-type-option:focus-visible { transform:translateY(-2px); border-color:var(--ks-blue); outline:0; }
        .ks-request-type-option.selected { border-color:var(--ks-blue); background:#F0F5FB; box-shadow:0 0 0 2px rgba(11,58,126,.1); }
        .ks-request-type-option svg { margin-bottom:13px; color:var(--ks-blue); }
        .ks-request-type-option strong { font-size:15px; }
        .ks-request-type-option span { margin-top:5px; color:var(--ks-soft); font-size:12px; line-height:1.45; }
        [data-beacon-group-toggle] svg { transition:transform .15s ease; }
        [data-beacon-group-toggle][aria-expanded="true"] svg { transform:rotate(180deg); }
        @media (max-width:480px) { .ks-request-type-options { grid-template-columns:1fr; } .ks-request-type-option { min-height:112px; } }
    </style>
@endpush

@section('main')
    @php
        $me = auth('surveyor')->user();
        $tab = $tab ?? 'beacons';
        $beaconTabUrl = route('surveyor-portal.requests.create', ['tab' => 'beacons']);
    @endphp
    <nav class="flex gap-2 mb-4" role="tablist" aria-label="Requested Beacons">
        <a class="ks-btn {{ $tab === 'beacons' ? 'ks-btn-primary' : 'ks-btn-light' }}"
           role="tab" aria-selected="{{ $tab === 'beacons' ? 'true' : 'false' }}"
           href="{{ $beaconTabUrl }}">Beacons</a>
        <a class="ks-btn {{ $tab === 'request' ? 'ks-btn-primary' : 'ks-btn-light' }}"
           role="tab" aria-selected="{{ $tab === 'request' ? 'true' : 'false' }}"
           href="{{ route('surveyor-portal.requests.create', ['tab' => 'request', 'request_type_dialog' => 1]) }}">Beacon Request</a>
    </nav>
    @if ($tab === 'beacons')
        @if ($hasOutstandingBeacons)
            <div class="ks-flash bad" role="alert">Complete the usage details for every beacon from your previous issued request before submitting another request. Mark unused beacons as No.</div>
        @endif
        <div class="ks-card overflow-x-auto">
            <table class="ks-table" style="min-width:1050px;">
                <thead><tr><th>S/N</th><th>Request No.</th><th>Issue Date</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Beacons</th><th>Usage</th><th></th></tr></thead>
                <tbody>
                    @forelse ($beaconRequests as $beaconRequest)
                        @php
                            $groupId = 'beacon-request-' . $beaconRequest->id;
                            $requestNumbers = $beaconRequest->lines->flatMap(fn ($line) => $line->numbers);
                            $requestNumberCount = $requestNumbers->count();
                            $usedNumberCount = $requestNumbers->filter(fn ($number) => $number->used !== null)->count();
                            $firstIssueDate = $beaconRequest->lines->first()?->issue_date;
                        @endphp
                        <tr>
                            <td>{{ $beaconRequests->firstItem() + $loop->index }}</td>
                            <td style="white-space:nowrap;"><a href="{{ route('surveyor-portal.requests.show', $beaconRequest) }}" style="color:var(--ks-blue);font-weight:600;">{{ $beaconRequest->request_no }}</a></td>
                            <td>{{ optional($firstIssueDate)->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $beaconRequest->plot_no ?: '—' }}</td>
                            <td>{{ $beaconRequest->district ?: '—' }}</td>
                            <td>{{ $beaconRequest->lga ? mb_strtoupper($beaconRequest->lga) : '—' }}</td>
                            <td>{{ $beaconRequest->location ?: '—' }}</td>
                            <td>{{ number_format($requestNumberCount) }}</td>
                            <td>{{ $usedNumberCount }} / {{ $requestNumberCount }}</td>
                            <td>
                                <button type="button" class="ks-btn ks-btn-light" data-beacon-group-toggle aria-expanded="false" aria-controls="{{ $groupId }}" style="padding:7px 10px;">
                                    <i data-lucide="chevron-down" class="w-4 h-4" aria-hidden="true"></i>
                                    <span>Show beacons</span>
                                </button>
                            </td>
                        </tr>
                        <tr id="{{ $groupId }}" hidden>
                            <td colspan="10" style="padding:0 12px 14px;background:#F7F9FC;">
                                <div class="overflow-x-auto">
                                    <table class="ks-table" style="min-width:760px;margin-top:8px;background:#fff;">
                                        <thead><tr><th>S/N</th><th>Beacon No.</th><th>Location</th><th>Used</th><th>X</th><th>Y</th><th></th></tr></thead>
                                        <tbody>
                                            @php
                                                $beaconSn = 0;
                                            @endphp
                                            @foreach ($beaconRequest->lines as $register)
                                                @foreach ($register->numbers as $beacon)
                                                    @php
                                                        $beaconFormId = 'beacon-details-' . $beacon->id;
                                                        $restoreBeaconInput = (string) old('beacon_row') === (string) $beacon->id;
                                                        $usedValue = $beacon->used === null ? '' : ($beacon->used ? '1' : '0');
                                                        if ($restoreBeaconInput) {
                                                            $usedValue = (string) old('used', $usedValue);
                                                        }
                                                    @endphp
                                                    <tr>
                                                        <td>{{ ++$beaconSn }}</td>
                                                        <td class="font-semibold">{{ \App\Models\Survey\SurveyBeaconRegister::display($beacon->beacon_no) }}</td>
                                                        <td>{{ $beaconRequest->location ?: '—' }}</td>
                                                        <td style="min-width:120px;">
                                                            <label class="sr-only" for="used-{{ $beacon->id }}">Used status for {{ $beacon->beacon_no }}</label>
                                                            <select class="ks-input" id="used-{{ $beacon->id }}" name="used" form="{{ $beaconFormId }}" style="min-width:110px;">
                                                                <option value="">Select…</option>
                                                                <option value="1" @selected($usedValue === '1')>Yes</option>
                                                                <option value="0" @selected($usedValue === '0')>No</option>
                                                            </select>
                                                        </td>
                                                        @foreach (['coord_x' => 'X', 'coord_y' => 'Y'] as $field => $label)
                                                            <td style="min-width:110px;">
                                                                <label class="sr-only" for="{{ $field }}-{{ $beacon->id }}">{{ $label }} coordinate for {{ $beacon->beacon_no }}</label>
                                                                <input class="ks-input" id="{{ $field }}-{{ $beacon->id }}" type="number" step="any" name="{{ $field }}" form="{{ $beaconFormId }}" value="{{ $restoreBeaconInput ? old($field, $beacon->{$field}) : $beacon->{$field} }}" style="min-width:100px;" @if ($beacon->used) required @endif>
                                                            </td>
                                                        @endforeach
                                                        <td>
                                                            <form id="{{ $beaconFormId }}" method="POST" action="{{ route('surveyor-portal.requests.beacons.details', ['beacon' => $beacon, 'page' => $beaconRequests->currentPage()]) }}">
                                                                @csrf
                                                                <input type="hidden" name="beacon_row" value="{{ $beacon->id }}">
                                                                <button class="ks-btn ks-btn-primary" style="padding:7px 10px;">Save</button>
                                                            </form>
                                                            @error('coord_x', 'beacon-' . $beacon->id)<p class="ks-error">{{ $message }}</p>@enderror
                                                            @error('coord_y', 'beacon-' . $beacon->id)<p class="ks-error">{{ $message }}</p>@enderror
                                                            @error('used', 'beacon-' . $beacon->id)<p class="ks-error">{{ $message }}</p>@enderror
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="ks-help" style="text-align:center;padding:24px;">No issued beacons yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $beaconRequests->links() }}</div>
    @else
    @if ($hasOutstandingBeacons)
        <div class="ks-flash bad" role="alert">You have beacons from a previous issued request that still need usage details. Update each beacon on the Beacons tab before submitting another request.</div>
    @endif
    <form method="POST" action="{{ route('surveyor-portal.requests.store') }}" class="ks-card p-6 space-y-4" style="max-width:720px;" enctype="multipart/form-data">
        @csrf
        @error('request')<div class="ks-flash bad" role="alert">{{ $message }}</div>@enderror
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
        <div class="grid sm:grid-cols-2 gap-4 items-start">
        <div>
            <label class="ks-label" for="requestTypeOpen">Request Type <span class="ks-req">*</span></label>
            @php($requestType = old('request_type'))
            <input type="hidden" id="request_type" name="request_type" value="{{ $requestType }}">
            <button type="button" class="ks-request-type-trigger" id="requestTypeOpen" aria-haspopup="dialog" aria-controls="requestTypeDialog" aria-describedby="requestTypeHelp" aria-required="true">
                <span id="requestTypeValue">{{ $requestType === 'single' ? 'Single request' : ($requestType === 'bulk' ? 'Bulk request' : 'Choose Single or Bulk…') }}</span>
                <i data-lucide="chevron-down" class="w-4 h-4" aria-hidden="true"></i>
            </button>
            <dialog class="ks-request-type-dialog" id="requestTypeDialog" aria-labelledby="requestTypeDialogTitle" aria-describedby="requestTypeDialogDescription">
                <div class="ks-request-type-panel">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 id="requestTypeDialogTitle" style="margin:0;font-size:19px;font-weight:800;">Choose request type</h2>
                            <p id="requestTypeDialogDescription" class="ks-help" style="margin:5px 0 0;">Select the option that best describes this beacon request.</p>
                        </div>
                        <button type="button" class="ks-btn ks-btn-light" id="requestTypeClose" aria-label="Close request type selection" style="padding:7px;"><i data-lucide="x" class="w-4 h-4"></i></button>
                    </div>
                    <div class="ks-request-type-options">
                        <button type="button" class="ks-request-type-option {{ $requestType === 'single' ? 'selected' : '' }}" data-request-type="single" aria-pressed="{{ $requestType === 'single' ? 'true' : 'false' }}">
                            <i data-lucide="map-pin" class="w-5 h-5" aria-hidden="true"></i>
                            <strong>Single</strong>
                            <span>For one site. District and LGA are required.</span>
                        </button>
                        <button type="button" class="ks-request-type-option {{ $requestType === 'bulk' ? 'selected' : '' }}" data-request-type="bulk" aria-pressed="{{ $requestType === 'bulk' ? 'true' : 'false' }}">
                            <i data-lucide="layers-3" class="w-5 h-5" aria-hidden="true"></i>
                            <strong>Bulk</strong>
                            <span>For multiple sites. Location details are optional.</span>
                        </button>
                    </div>
                </div>
            </dialog>
            @error('request_type')<p class="ks-error" role="alert">{{ $message }}</p>@enderror
            <p class="ks-help" id="requestTypeHelp">For a Single request, provide the district and LGA. Location is optional for Bulk requests because the beacons may be used at different sites.</p>
        </div>
        <div>
            <label class="ks-label" for="quantity">Number of Beacons <span class="ks-req">*</span></label>
            <input class="ks-input" id="quantity" type="number" name="quantity" min="1" max="{{ \App\Services\Survey\BeaconRequests::MAX_QUANTITY }}" value="{{ old('quantity') }}" required>
        </div>
        </div>
        <div>
            <label class="ks-label" for="payment_evidence">SURCON Payment Evidence <span class="ks-req">*</span></label>
            <p class="ks-help" style="margin-bottom:6px;">Payment for requested beacons is made to the State’s surveyors body (SURCON). Enter the payment amount and date, then upload your payment receipt or transfer confirmation. PDF, JPG or PNG; maximum 5 MB.</p>
            <div class="grid sm:grid-cols-2 gap-4" style="margin-bottom:12px;">
                <div>
                    <label class="ks-label" for="amount_paid">Amount Paid (₦) <span class="ks-req">*</span></label>
                    <input class="ks-input" id="amount_paid" type="number" name="amount_paid" min="0.01" max="9999999999999.99" step="0.01" value="{{ old('amount_paid') }}" required>
                    @error('amount_paid')<p class="ks-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ks-label" for="payment_date">Date Paid <span class="ks-req">*</span></label>
                    <input class="ks-input" id="payment_date" type="date" name="payment_date" max="{{ now()->toDateString() }}" value="{{ old('payment_date') }}" required>
                    @error('payment_date')<p class="ks-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <input class="ks-input" id="payment_evidence" type="file" name="payment_evidence" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
            @error('payment_evidence')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="ks-label" for="plot_no">Plot No.</label>
                <input class="ks-input" id="plot_no" name="plot_no" value="{{ old('plot_no') }}" maxlength="100">
            </div>
            <div>
                <label class="ks-label" for="district">District <span class="ks-req" data-single-required>*</span></label>
                <select class="ks-input ks-district-select" id="district" name="district" data-url="{{ route('surveyor-portal.lookup.districts') }}" @if (old('request_type') === 'single') required @endif>
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
                <label class="ks-label" for="lga">LGA <span class="ks-req" data-single-required>*</span></label>
                <select class="ks-input" id="lga" name="lga" @if (old('request_type') === 'single') required @endif>
                    <option value="">Select LGA…</option>
                    @foreach ($lgas as $l)<option value="{{ $l }}" @selected(old('lga') === $l)>{{ $l }}</option>@endforeach
                </select>
            </div>
        </div>
        {{-- Where the survey is (user, 2026-11-06). Optional, as the location fields above are. --}}
        <div>
            <span class="ks-label">Location on the Map</span>
            <p class="ks-help" style="margin:0 0 6px;">Optional map pin. Selecting an LGA places the pin near its approximate center; drag it to the survey location.</p>
            <div id="brqMap"></div>
            <p class="ks-help" style="margin:6px 0 0;">Approximate center data: geoBoundaries (GRID3), via <a href="https://github.com/andresgmg/World-GeoJSON" target="_blank" rel="noopener">World GeoJSON</a>, CC BY 4.0.</p>
            <div class="flex flex-wrap items-center justify-between gap-3" style="margin-top:6px;">
                <span class="ks-help" id="brqPinText">No pin yet.</span>
                <span class="flex gap-2">
                    <button type="button" class="ks-btn ks-btn-light" id="brqPinLga" style="padding:6px 12px;">Pin on Map</button>
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
        <div class="flex justify-end">
            <button class="ks-btn ks-btn-primary" @disabled($hasOutstandingBeacons)>
                {{ $hasOutstandingBeacons ? 'Update previous beacons first' : 'Send to the Surveyor General' }}
            </button>
        </div>
    </form>
    @endif
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
(function () {
    var district = document.getElementById('district'), districtOther = document.getElementById('district_other'),
        requestType = document.getElementById('request_type'), lgaField = document.getElementById('lga'),
        requestTypeOpen = document.getElementById('requestTypeOpen'), requestTypeDialog = document.getElementById('requestTypeDialog');
    function setBeaconGroupExpanded(button, expanded) {
        var row = document.getElementById(button.getAttribute('aria-controls'));
        if (!row) return;
        row.hidden = !expanded;
        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        button.querySelector('span').textContent = expanded ? 'Hide beacons' : 'Show beacons';
    }
    document.querySelectorAll('[data-beacon-group-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            setBeaconGroupExpanded(button, button.getAttribute('aria-expanded') !== 'true');
        });
    });
    @if (old('beacon_row'))
        var beaconWithErrors = document.getElementById('used-{{ old('beacon_row') }}');
        if (beaconWithErrors) {
            var groupRow = beaconWithErrors.closest('tr[hidden]');
            if (groupRow) {
                var groupToggle = document.querySelector('[aria-controls="' + groupRow.id + '"]');
                if (groupToggle) setBeaconGroupExpanded(groupToggle, true);
            }
        }
    @endif
    function syncRequestLocation() {
        var single = requestType && requestType.value === 'single';
        if (district) district.required = single;
        if (lgaField) lgaField.required = single;
        document.querySelectorAll('[data-single-required]').forEach(function (el) {
            el.style.display = single ? '' : 'none';
        });
    }
    if (requestType) {
        syncRequestLocation();
    }
    if (requestTypeOpen && requestTypeDialog && typeof requestTypeDialog.showModal === 'function') {
        var requestTypeValue = document.getElementById('requestTypeValue');
        var requestTypeClose = document.getElementById('requestTypeClose');
        requestTypeOpen.addEventListener('click', function () {
            requestTypeDialog.showModal();
            var selected = requestTypeDialog.querySelector('[data-request-type="' + (requestType.value || 'single') + '"]');
            if (selected) selected.focus();
        });
        requestTypeClose.addEventListener('click', function () { requestTypeDialog.close(); requestTypeOpen.focus(); });
        requestTypeDialog.addEventListener('click', function (event) {
            if (event.target === requestTypeDialog) requestTypeDialog.close();
        });
        requestTypeDialog.querySelectorAll('[data-request-type]').forEach(function (option) {
            option.addEventListener('click', function () {
                var value = option.dataset.requestType;
                requestType.value = value;
                requestTypeValue.textContent = value === 'single' ? 'Single request' : 'Bulk request';
                requestTypeDialog.querySelectorAll('[data-request-type]').forEach(function (choice) {
                    var selected = choice === option;
                    choice.classList.toggle('selected', selected);
                    choice.setAttribute('aria-pressed', selected ? 'true' : 'false');
                });
                syncRequestLocation();
                requestTypeDialog.close();
                requestTypeOpen.focus();
            });
        });
        @if ($errors->has('request_type'))
            requestTypeDialog.showModal();
        @else
            if (new URLSearchParams(window.location.search).get('request_type_dialog') === '1') {
            requestTypeDialog.showModal();
            }
        @endif
    }
    if (district && window.jQuery && jQuery.fn.select2) {
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
    } else if (district) {
        district.disabled = true;
        districtOther.disabled = true;
        document.getElementById('districtSearchError').style.display = '';
    }

    var box = document.getElementById('brqMap');
    if (!box) return;
    var lat = document.getElementById('brqLat'), lng = document.getElementById('brqLng'), text = document.getElementById('brqPinText'),
        clear = document.getElementById('brqClear'), locate = document.getElementById('brqLocate'),
        pinLga = document.getElementById('brqPinLga');
    // The map is optional: without it (no connection to the map server) the form still sends.
    if (!window.L) { box.style.display = 'none'; locate.hidden = true; pinLga.hidden = true; text.textContent = 'The map could not load. You can still send the request.'; return; }

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
        if (!name) {
            text.textContent = 'Select an LGA to pin its approximate center.';
            return false;
        }
        if (!center) {
            text.textContent = 'Could not find the approximate center for ' + name + '. Click the map to place a pin.';
            return false;
        }
        var ll = L.latLng(center[0], center[1]);
        place(ll);
        map.invalidateSize();
        map.setView(ll, 12);
        text.textContent = 'Pin placed near the center of ' + name + '. Drag it to the exact survey location.';
        return true;
    }
    map.on('click', function (e) { place(e.latlng); });
    lga.addEventListener('change', function () { placeAtLga(lga.value); });
    pinLga.addEventListener('click', function () { placeAtLga(lga.value); });
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
