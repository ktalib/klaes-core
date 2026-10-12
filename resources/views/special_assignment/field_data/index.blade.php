@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Field Data' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Field Data</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <button id="btn-add-spa"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 transition-opacity whitespace-nowrap"
                style="background:rgb(186,191,12)">
                <i data-lucide="plus" class="h-4 w-4"></i> Add to SPAS
            </button>
        </div>

        {{-- Where this page sits in the SPAS workflow. --}}
        @include('special_assignment.partials.workflow', ['current' => ['field']])

        {{-- Tabs --}}
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
            <button data-tab="field-map" class="tab-btn active px-5 py-2 text-sm font-medium rounded-md transition-all duration-200">
                <span class="flex items-center gap-2"><i data-lucide="map" class="h-4 w-4"></i> Field Map</span>
            </button>
            <button data-tab="records" class="tab-btn px-5 py-2 text-sm font-medium rounded-md text-gray-500 transition-all duration-200">
                <span class="flex items-center gap-2"><i data-lucide="file-text" class="h-4 w-4"></i> Records</span>
            </button>
        </div>

        {{-- Field Map Tab --}}
        <div id="tab-field-map" class="tab-content">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4" style="min-height:560px;">

                {{-- Sidebar --}}
                <div class="md:col-span-1">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm h-full">
                        <div class="p-4 border-b">
                            <h2 class="text-base font-semibold flex items-center gap-2">
                                <i data-lucide="layers" class="h-5 w-5 text-[rgb(186,191,12)]"></i> GIS Tools
                            </h2>
                        </div>
                        <div class="p-4 space-y-5 text-sm">

                            {{-- Base Map --}}
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Base Map</h3>
                                <select id="baseMapSelect" class="w-full p-2 border border-gray-200 rounded-md text-sm">
                                    <option value="streets">Streets</option>
                                    <option value="satellite" selected>Satellite</option>
                                    <option value="terrain">Terrain</option>
                                </select>
                            </div>

                            <hr class="border-gray-100">

                            {{-- Land Use Filter --}}
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Land Use Filter</h3>
                                <select id="landUseFilter" class="w-full p-2 border border-gray-200 rounded-md text-sm">
                                    <option value="all">All Land Uses</option>
                                    <option value="RESIDENTIAL">Residential</option>
                                    <option value="COMMERCIAL">Commercial</option>
                                    <option value="AGRICULTURAL">Agricultural</option>
                                    <option value="INDUSTRIAL">Industrial</option>
                                </select>
                            </div>

                            <hr class="border-gray-100">

                            {{-- Legend --}}
                            <div>
                                <h3 class="font-medium text-gray-700 mb-2">Legend</h3>
                                <div class="space-y-1.5">
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#0f766e"></div><span class="text-xs text-gray-600">Residential</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#0e7490"></div><span class="text-xs text-gray-600">Commercial</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#b45309"></div><span class="text-xs text-gray-600">Agricultural</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#7e22ce"></div><span class="text-xs text-gray-600">Industrial</span></div>
                                    <div class="flex items-center gap-2"><div class="w-3 h-3 rounded-sm flex-shrink-0" style="background:#4b5563"></div><span class="text-xs text-gray-600">Other</span></div>
                                </div>
                            </div>

                            <hr class="border-gray-100">

                            <div class="text-xs text-gray-400">
                                <span id="map-point-count">{{ count($mapPoints) }}</span> inspection point(s) plotted
                            </div>

                            {{--
                                Records captured without a pin. A surveyor may save
                                offline with no GPS fix — losing the record entirely
                                would be worse — so these arrive unplaced and would
                                otherwise be missing from the map with nothing to say
                                so. Listed here so the office can place them.
                            --}}
                            @if(count($awaitingLocation ?? []) > 0)
                                <hr class="border-gray-100">

                                <div>
                                    <button type="button"
                                            class="w-full flex items-center justify-between text-xs font-semibold text-amber-700 hover:text-amber-800"
                                            onclick="document.getElementById('awaiting-location-list').classList.toggle('hidden')">
                                        <span class="flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                                            </svg>
                                            {{ count($awaitingLocation) }} awaiting location
                                        </span>
                                        <span class="text-gray-400">show</span>
                                    </button>

                                    <div id="awaiting-location-list" class="hidden mt-2 max-h-48 overflow-y-auto space-y-1">
                                        @foreach($awaitingLocation as $row)
                                            <div class="text-xs bg-amber-50 border border-amber-100 rounded-lg px-2 py-1.5">
                                                <div class="font-semibold text-gray-700">{{ $row['file_number'] }}</div>
                                                <div class="text-gray-500 truncate">{{ $row['owner'] }} &middot; {{ $row['lga'] }}</div>
                                                <div class="text-gray-400">{{ $row['date'] }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Map Area --}}
                <div class="md:col-span-3 flex flex-col">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm flex-1 flex flex-col overflow-hidden">
                        {{-- Map Header --}}
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <h2 class="text-sm font-semibold flex items-center gap-2">
                                <i data-lucide="map" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
                                Special Assignment – Field Inspection Map
                            </h2>
                            <div class="flex items-center gap-1">
                                <button id="map-zoom-in" title="Zoom In" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="plus" class="h-4 w-4"></i></button>
                                <button id="map-zoom-out" title="Zoom Out" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="minus" class="h-4 w-4"></i></button>
                                <button id="map-fullscreen" title="Fullscreen" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="maximize" class="h-4 w-4"></i></button>
                                <button id="map-reset" title="Reset View" class="p-1.5 border border-gray-200 rounded hover:bg-gray-50"><i data-lucide="rotate-cw" class="h-4 w-4"></i></button>
                            </div>
                        </div>
                        {{-- Map --}}
                        <div id="field-map" class="flex-1 relative" style="min-height:480px;">
                            <div id="map-loading" class="absolute inset-0 flex items-center justify-center bg-gray-50 z-10">
                                <div class="text-center">
                                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 mx-auto mb-3" style="border-color:rgb(186,191,12)"></div>
                                    <p class="text-sm text-gray-500">Loading map…</p>
                                </div>
                            </div>
                        </div>
                        {{-- Zoom indicator --}}
                        <div class="px-3 py-1.5 border-t border-gray-100 text-xs text-gray-400 flex justify-between">
                            <span>Zoom: <span id="map-zoom-level">11</span></span>
                            <span>Kano State, Nigeria</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Records Tab --}}
        <div id="tab-records" class="tab-content hidden">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-700">Field Records</h2>
                </div>
                <div class="p-4">
                    @include('special_assignment.partials.table_case')
                    <table id="field-records-table" class="spas-table w-full text-sm" style="width:100%">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">SPAS ID</th>
                                <th class="px-3 py-2">File No</th>
                                <th class="px-3 py-2">Sticker No</th>
                                <th class="px-3 py-2">Source</th>
                                <th class="px-3 py-2">Owner</th>
                                <th class="px-3 py-2">Location</th>
                                <th class="px-3 py-2">Approved Use</th>
                                <th class="px-3 py-2">Prevailing Use</th>
                                <th class="px-3 py-2">Contravention</th>
                                <th class="px-3 py-2">Inspection</th>
                                <th class="px-3 py-2">Inspection Date</th>
                                <th class="px-3 py-2">Findings</th>
                                <th class="px-3 py-2">Inspected By</th>
                                <th class="px-3 py-2">Created By</th>
                                <th class="px-3 py-2">Date Created</th>
                                <th class="px-3 py-2">Photos</th>
                                <th class="px-3 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- View Field Inspection Modal --}}
<div id="modal-view-field" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Field Inspection Details</h3>
            <button id="btn-close-view" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="p-6 space-y-4">
            {{-- Where this record stands: Record → … → Certificate. --}}
            <div id="vf-pipeline" class="hidden rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3"></div>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 mb-0.5">SPAS ID · File Number</p>
                    <p id="vf-file" class="font-medium text-gray-800">—</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-0.5">Inspection Date</p>
                    <p id="vf-date" class="font-medium text-gray-800">—</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500 mb-0.5">Inspector</p>
                    <p id="vf-inspector" class="font-medium text-gray-800">—</p>
                </div>
                <div class="col-span-2">
                    <p class="text-xs text-gray-500 mb-0.5">Findings</p>
                    <p id="vf-findings" class="text-gray-700 leading-relaxed whitespace-pre-wrap">—</p>
                </div>
            </div>
            {{-- Every visit to the site, newest first, when there has been more than one. --}}
            <div id="vf-visits-wrap" class="hidden">
                <p class="text-xs text-gray-500 mb-2">Inspection History</p>
                <ol id="vf-visits" class="space-y-2 text-sm"></ol>
            </div>
            {{-- Taken with the land record (Add Land Record card, web or app). --}}
            <div id="vf-property-photos-wrap" class="hidden">
                <p class="text-xs text-gray-500 mb-2">Property Photos</p>
                <div id="vf-property-photos" class="flex flex-wrap gap-2"></div>
            </div>
            <div id="vf-photos-wrap" class="hidden">
                <p class="text-xs text-gray-500 mb-2">Inspection Photos</p>
                <div id="vf-photos" class="flex flex-wrap gap-2"></div>
            </div>
            <p id="vf-no-photos" class="hidden text-xs text-gray-400">No photos were uploaded for this record.</p>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button id="btn-close-view2" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
        </div>
    </div>
</div>

{{-- Log Inspection Modal — for a record saved without its inspection (the Add card's
     inspection fields are optional). Posts to the same field-data.store route. --}}
<div id="modal-log-inspection" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 id="li-title" class="text-base font-semibold text-gray-800">Log Field Inspection</h3>
                <p id="li-file-label" class="text-xs text-gray-500 mt-0.5">—</p>
            </div>
            <button type="button" class="li-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-log-inspection" class="p-6 space-y-4">
            <input type="hidden" name="spa_application_id" id="li-app-id">
            <input type="hidden" name="file_number" id="li-file">
            {{-- Set when editing an existing visit; empty when logging a new one. --}}
            <input type="hidden" id="li-edit-id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Inspection Date <span class="text-red-500">*</span></label>
                    <input type="date" name="inspection_date" id="li-date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Coordinates (lat, lng)</label>
                    <input type="text" id="li-coords" placeholder="e.g. 12.0022, 8.5919"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Findings <span class="text-red-500">*</span></label>
                <textarea name="findings" id="li-findings" rows="3" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-[rgb(186,191,12)]"
                    placeholder="Describe on-site observations…"></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Photos</label>
                <input type="file" name="photos[]" multiple accept="image/*"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="li-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" id="btn-save-inspection" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Inspection</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Record (Add to SPAS) Modal — land record details + optional field inspection in one pass --}}
<div id="modal-add-record" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl mx-auto overflow-hidden max-h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100 flex-shrink-0">
            <div>
                <h3 id="ar-card-title" class="text-base font-semibold text-gray-800">Add Land Record</h3>
                <p id="ar-edit-sub" class="hidden text-xs text-gray-500 mt-0.5"></p>
            </div>
            <div class="flex items-center gap-3">
                {{-- Start the card again: empties every field, the photos, the pin and the traced boundary. --}}
                <button type="button" id="btn-clear-record" title="Clear everything entered and start again"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">
                    <i data-lucide="rotate-ccw" class="h-3.5 w-3.5"></i> Clear form
                </button>
                <button id="btn-close-modal" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
            </div>
        </div>
        {{-- Add mode: the steps this card covers. Edit mode: where this record stands. --}}
        <div id="ar-wf-static" class="px-6 pt-3 flex-shrink-0">
            @include('special_assignment.partials.workflow', ['current' => ['field'], 'compact' => true, 'counts' => false])
        </div>
        <div id="ar-pipeline" class="hidden px-6 py-3 border-b border-gray-100 bg-gray-50/60 flex-shrink-0">
        </div>
        <form id="form-add-record" enctype="multipart/form-data" class="p-6 flex-1 overflow-y-auto min-h-0">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                {{-- Left: land record details --}}
                <div class="lg:col-span-2 space-y-4">

                    {{-- Edit mode: says what this officer may change. --}}
                    <div id="ar-edit-note" class="hidden px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800"></div>

                    {{-- Land Title Type --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Land Title Type <span class="text-red-500">*</span></label>
                        <div class="flex gap-5">
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="land_title_type" id="ltt-statutory" value="statutory" checked
                                    class="text-[rgb(186,191,12)] focus:ring-[rgb(186,191,12)]">
                                Statutory Title
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                                <input type="radio" name="land_title_type" id="ltt-customary" value="customary"
                                    class="text-[rgb(186,191,12)] focus:ring-[rgb(186,191,12)]">
                                Customary Title
                            </label>
                        </div>
                    </div>

                    {{-- SPAS ID: every record gets one, from the same sequence that numbers
                         customary titles. Most owners do not know their file number, so
                         this — not the file number — is what identifies the record. --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">SPAS ID</label>
                        <input type="text" id="display-spa-id" readonly placeholder="Assigned when the record is saved"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 font-semibold cursor-default outline-none">
                        <p id="spa-id-hint" class="text-[11px] text-gray-400 mt-1">Assigned when the record is saved.</p>
                    </div>

                    {{-- File Number picker — optional --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">File Number</label>
                        <div class="flex gap-2">
                            <input type="text" id="display-file-number" readonly placeholder="Not known — select it if the owner has one"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 cursor-default outline-none">
                            <button type="button" id="btn-pick-fileno"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg transition-opacity disabled:opacity-50 disabled:cursor-not-allowed">
                                <i data-lucide="search" class="h-4 w-4"></i> Select
                            </button>
                        </div>
                        <p id="lookup-msg" class="text-xs mt-1 hidden"></p>
                    </div>

                    {{-- Sticker number: the QR sticker on the property. Typed, or scanned. --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sticker Number</label>
                        <div class="flex gap-2">
                            <input type="text" name="sticker_number" id="f-sticker" maxlength="100" placeholder="Type it, or scan the sticker's QR code"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm uppercase outline-none focus:border-[rgb(186,191,12)]">
                            <button type="button" id="btn-scan-sticker"
                                class="inline-flex items-center gap-1.5 px-4 py-2 border border-[rgb(186,191,12)] text-[rgb(140,144,8)] text-sm font-medium rounded-lg hover:bg-[rgba(186,191,12,0.08)]">
                                <i data-lucide="scan-line" class="h-4 w-4"></i> Scan QR
                            </button>
                        </div>
                    </div>

                    {{-- Owner / File Title – readonly, filled from file_title (editable for customary) --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Owner / File Title <span class="text-red-500">*</span></label>
                        <input type="text" name="owner_name" id="f-owner_name" readonly required
                            placeholder="Auto-filled from file number"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 outline-none cursor-default">
                    </div>

                    {{-- Customary Title only: there is no indexed file to read an
                         address from, so the surveyor picks District + LGA and the
                         inspection map geocodes from that. Ordered the way the
                         location itself reads: "District, LGA". --}}
                    <div id="customary-geo-wrap" class="hidden grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">District</label>
                            {{-- Populated on demand from /api/reference/districts: there are
                                 1,818 of them, far too many to render inline. One dropdown with
                                 its search inside (public/js/searchable-select.js); "Other" stays
                                 at the top and opens a box to type a district not on the list. --}}
                            <select name="district" id="f-district" disabled
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                                <option value="">Loading districts…</option>
                            </select>
                            <input type="text" id="f-district-other" maxlength="255" placeholder="Specify the district"
                                class="hidden w-full border border-gray-200 rounded-lg px-3 py-2 text-sm mt-1.5 outline-none focus:border-[rgb(186,191,12)]">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">LGA <span class="text-red-500">*</span></label>
                            <select name="lga" id="f-lga" disabled
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                                <option value="">Select LGA…</option>
                                @foreach($lgaOptions as $lga)
                                    <option value="{{ $lga }}">{{ $lga }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Location badge (shown after file lookup) --}}
                    <div id="location-badge-wrap" class="hidden">
                        <div class="flex items-start gap-2 px-3 py-2 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-800">
                            <i data-lucide="map-pin" class="h-3.5 w-3.5 mt-0.5 shrink-0 text-blue-500"></i>
                            <span id="location-badge-text" class="leading-relaxed"></span>
                        </div>
                    </div>

                    {{-- DCIV Investigation Banner (shown when file has dciv_status = 1) --}}
                    <div id="dciv-banner-wrap" class="hidden">
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700 border border-rose-300">
                                    <i data-lucide="alert-triangle" class="h-3 w-3"></i> Under Investigation
                                </span>
                                <span id="dciv-banner-fileno" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-white text-rose-700 border border-rose-200"></span>
                            </div>
                            <div class="flex items-start gap-2 text-xs text-rose-800">
                                <i data-lucide="file-text" class="h-3.5 w-3.5 mt-0.5 shrink-0 text-rose-400"></i>
                                <div>
                                    <span class="font-semibold uppercase tracking-wide text-rose-500 text-[10px]">Reason&nbsp;</span>
                                    <span id="dciv-banner-reason" class="leading-relaxed"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Hidden fields --}}
                    <input type="hidden" name="file_number"      id="h-file_number">
                    <input type="hidden" name="file_indexing_id" id="h-file_indexing_id">
                    <input type="hidden" name="tracking_id"      id="h-tracking_id">
                    <input type="hidden" name="is_indexed"       id="h-is_indexed" value="0">
                    <input type="hidden" name="location"         id="h-location">
                    {{-- Statutory path only — disabled (and so unsubmitted) for a
                         customary title, where the #f-lga / #f-district selects
                         carry these same names. --}}
                    <input type="hidden" name="lga"              id="h-lga">
                    <input type="hidden" name="district"         id="h-district">
                    {{-- proposed_use is required server-side; mirror the applied land use --}}
                    <input type="hidden" name="proposed_use"     id="h-proposed_use">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- General Landuse – auto-filled from the file for a statutory
                             title; picked from a dropdown for a customary title, which
                             has no indexed file to read it from. Only one of the two is
                             ever enabled, so only one posts `land_use_type`. --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">General Landuse (Observed around) - Special</label>
                            <input type="text" name="land_use_type" id="f-land_use_type" readonly
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 outline-none"
                                placeholder="Auto-filled from file">
                            <select name="land_use_type" id="f-land_use_type_select" disabled
                                class="hidden w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                                <option value="">Select…</option>
                                @foreach($customaryLandUses as $lut)
                                    <option value="{{ $lut }}">{{ $lut }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Phone --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
                            <input type="text" name="phone" id="f-phone"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Prevailing Land Use <span class="text-red-500">*</span></label>
                            <select name="existing_use" id="f-existing_use" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                                <option value="">Select…</option>
                                @foreach($landUseTypes as $lut)
                                    <option value="{{ $lut }}">{{ $lut }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Contravention badge – same row as Prevailing Land Use --}}
                        <div class="flex items-end justify-center">
                            <div id="contravention-badge" class="hidden items-center gap-2 px-6 py-2 rounded-lg bg-red-100 text-red-700 border-2 border-red-400" style="font-size:15px;font-weight:900;letter-spacing:.07em;animation:contraventionPulse 1.2s ease-in-out infinite;">
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                 CONTRAVENTION
                            </div>
                        </div>
                    </div>

                    {{-- Property Photos --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Property Photos</label>
                        <label for="f-photos" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:border-[rgb(186,191,12)] hover:bg-[rgba(186,191,12,0.03)] transition-colors">
                            <div class="flex flex-col items-center gap-1 pointer-events-none">
                                <i data-lucide="image-plus" class="h-6 w-6 text-gray-400"></i>
                                <span class="text-xs text-gray-400">Click to upload photos <span class="text-gray-300">(JPG, PNG — multiple allowed)</span></span>
                            </div>
                            <input type="file" id="f-photos" name="photos[]" multiple accept="image/*" class="hidden">
                        </label>
                        <div id="photo-preview" class="flex flex-wrap gap-2 mt-2"></div>
                        {{-- Edit mode: photos already on the record (new ones are added to them). --}}
                        <div id="photo-existing-wrap" class="hidden mt-2">
                            <p class="text-[11px] text-gray-400 mb-1">Already uploaded</p>
                            <div id="photo-existing" class="flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                </div>

                {{-- Right: optional field inspection, logged in the same pass via the map --}}
                <div class="lg:col-span-3 space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                            <i data-lucide="clipboard-check" class="h-4 w-4 text-[rgb(186,191,12)]"></i>
                            Field Inspection <span class="text-xs font-normal text-gray-400">(optional)</span>
                        </h4>
                        <p class="text-xs text-gray-400 mt-0.5">On-site now? Capture the inspection below and it will be logged together with the record. Otherwise leave this blank and log it later from Field Data.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Inspection Date</label>
                            <input type="date" id="ar-inspection-date"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Coordinates (lat, lng)
                                <span class="font-normal text-gray-400">— auto-set from location</span>
                            </label>
                            <input type="text" id="ar-coords" placeholder="Set automatically once a file is selected"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Findings</label>
                        <textarea id="ar-findings" rows="3"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-[rgb(186,191,12)]"
                            placeholder="Describe on-site observations…"></textarea>
                    </div>
                    <input type="hidden" id="ar-geometry">

                    {{-- Edit mode: photos already on the visit, and a picker to add more to it
                         (used when this officer may edit the visit but not the record). --}}
                    <div id="ar-insp-photos-wrap" class="hidden">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Inspection Photos</label>
                        <div id="ar-insp-photos" class="flex flex-wrap gap-2 mb-2"></div>
                        <input type="file" id="ar-insp-photo-input" multiple accept="image/*"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-medium text-gray-600">Pin Location &amp; Trace Boundary</label>
                            <button type="button" id="ar-locate" class="inline-flex items-center gap-1 text-xs text-[rgb(140,144,8)] hover:underline">
                                <i data-lucide="locate-fixed" class="h-3.5 w-3.5"></i> Use my location
                            </button>
                        </div>
                        <div id="ar-map" class="rounded-lg overflow-hidden border border-gray-200" style="height:280px;width:100%;"></div>
                        <p id="ar-map-status" class="text-[11px] text-gray-400 mt-1">Click the map or drag the pin to set coordinates. Use the polygon tool (top-right) to trace the plot boundary.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-5">
                <button type="button" id="btn-cancel-modal" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" id="btn-save-record" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Record</button>
            </div>
        </form>
    </div>
</div>

{{-- Global File Number Modal --}}
@include('components.global-fileno-modal')

{{-- Sticker QR scanner --}}
@include('special_assignment.partials.qr_scan')

<style>
    /* The page's modals sit at z-index 1100, above SweetAlert's default 1060, so
       a warning raised from inside the Add Land Record card opened BEHIND it. */
    .swal2-container { z-index: 20000 !important; }

    @keyframes contraventionPulse {
        0%, 100% { background-color:#fee2e2; border-color:#fca5a5; transform:scale(1);   box-shadow:none; }
        50%       { background-color:#fecaca; border-color:#ef4444; transform:scale(1.04); box-shadow:0 0 10px rgba(239,68,68,.35); }
    }
</style>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="{{ asset('js/spas-pipeline.js') }}?v={{ @filemtime(public_path('js/spas-pipeline.js')) }}"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
{{-- Leaflet.draw — lets the surveyor trace the plot boundary on the inspection map --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
{{-- Geocoding for the inspection map pin. Leaflet is already loaded just above,
     so pull in the google.maps shim only (same stack as File Indexing). --}}
@include('partials.maps_scripts', ['skipLeaflet' => true])
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/searchable-select.js') }}?v=20261008"></script>

<style>
    .tab-btn.active { background:white; color:#1f2937; box-shadow:0 1px 3px rgba(0,0,0,.1); }
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script>
const MAP_POINTS    = @json($mapPoints);
const CSRF          = '{{ csrf_token() }}';
const STORE         = '{{ route("special-assignment.field-data.store") }}';
const APP_URL       = '{{ route("special-assignment.land-records") }}';
const DELETE_FIELD  = '{{ url("special-assignment/field-data") }}';
const FIELD_BASE    = DELETE_FIELD;                                        // /{id}/update edits a visit
const RECORD_BASE   = '{{ url("special-assignment/land-records") }}';      // /{id}/update edits a record

// Extracts a {lat, lng} pair from free-typed text ("12.0022, 8.5919", pasted
// "12.0022° N, 8.5919° E", etc.) rather than requiring an exact "a, b" split —
// a strict split silently produced garbage that the server then rejected (or,
// worse, quietly dropped the pin) whenever the input didn't match exactly.
function parseLatLngInput(str) {
    if (!str) return null;
    const nums = (String(str).match(/-?\d+(?:\.\d+)?/g) || []).map(Number);
    if (nums.length < 2 || nums.some(Number.isNaN)) return null;
    const [lat, lng] = nums;
    if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
    return { lat, lng };
}

// ── Reusable pin + boundary-trace map controller ────────────────────────────
// Shared by the "Log Field Inspection" modal and the Field Inspection section
// of "Add Land Record", so both get the same pin/drag/geocode/polygon-trace UX.
function createInspectionMapController({ mapId, coordsId, geometryId, statusId, locateBtnId, center = [12.0, 8.52], zoom = 12 }) {
    let map = null, marker = null, drawnItems = null, drawing = false;

    function pinIcon() {
        return L.divIcon({
            className: '',
            html: `<svg width="30" height="40" viewBox="0 0 30 40" xmlns="http://www.w3.org/2000/svg"><path d="M15 0C7 0 1 6 1 14c0 10 14 26 14 26s14-16 14-26C29 6 23 0 15 0z" fill="rgb(186,191,12)" stroke="#fff" stroke-width="2"/><circle cx="15" cy="14" r="5.5" fill="#fff"/></svg>`,
            iconSize:   [30, 40],
            iconAnchor: [15, 40],
        });
    }

    function syncCoordsField(lat, lng) {
        const el = document.getElementById(coordsId);
        if (!el) return;
        const la = Math.round(lat * 1e6) / 1e6, ln = Math.round(lng * 1e6) / 1e6;
        el.value = `${la}, ${ln}`;
    }

    function syncGeometryField() {
        const el = document.getElementById(geometryId);
        if (!el) return;
        const layer = drawnItems && drawnItems.getLayers()[0];
        el.value = layer ? JSON.stringify(layer.toGeoJSON().geometry) : '';
    }

    function setStatus(type, msg) {
        const el = document.getElementById(statusId);
        if (!el) return;
        const colors = { loading: 'text-gray-400', ok: 'text-green-600', warn: 'text-amber-600' };
        el.className = 'text-[11px] mt-1 ' + (colors[type] || 'text-gray-400');
        el.textContent = msg || 'Click the map or drag the pin to set coordinates. You can also type them above.';
    }

    function placeMarker(lat, lng, recenter = true) {
        const pos = [lat, lng];
        if (!marker) {
            marker = L.marker(pos, { icon: pinIcon(), draggable: true }).addTo(map);
            marker.on('dragend', e => { const p = e.target.getLatLng(); syncCoordsField(p.lat, p.lng); });
        } else {
            marker.setLatLng(pos);
        }
        if (recenter) map.setView(pos, Math.max(map.getZoom(), 16));
        syncCoordsField(lat, lng);
    }

    // Auto-pin from the file's stored location, then advise the surveyor to adjust.
    // Goes through google.maps.Geocoder, which on this deployment is the Leaflet
    // shim backed by Nominatim (see partials/maps_scripts.blade.php).
    //
    // `location` may be a single string or an ordered list of candidates, most
    // specific first (full address → district + LGA → LGA). Nominatim rarely
    // resolves a Kano street address, so without the broader fallbacks the pin
    // simply never appeared and the coordinates stayed empty; landing on the
    // right district/LGA and letting the surveyor drag the pin beats nothing.
    // `opts.force` re-pins even when coordinates are already set — used when the
    // surveyor changes the address itself (customary LGA / District), where the
    // old pin is stale rather than something worth protecting.
    function geocode(location, opts = {}) {
        const coordsEl = document.getElementById(coordsId);
        if (opts.force && coordsEl) coordsEl.value = '';
        const candidates = (Array.isArray(location) ? location : [location])
            .map(v => (v == null ? '' : String(v)).trim())
            .filter(v => v && v !== '—')
            // de-duplicate while preserving order
            .filter((v, i, arr) => arr.indexOf(v) === i);

        if (!candidates.length) { setStatus('', ''); return; }
        // Never overwrite coordinates the surveyor has already picked / typed.
        if (coordsEl && coordsEl.value.trim()) { setStatus('', ''); return; }

        if (typeof google === 'undefined' || !google.maps) {
            setStatus('warn', 'Map service still loading — click the map to drop the pin manually.');
            return;
        }

        setStatus('loading', 'Locating the file address on the map…');
        const geocoder = new google.maps.Geocoder();

        (function tryNext(i) {
            if (i >= candidates.length) {
                setStatus('warn', 'Couldn’t auto-locate this address — click the map to drop the pin manually.');
                return;
            }
            // Bias toward Kano State; stored locations rarely name it.
            const raw     = candidates[i];
            const address = /kano/i.test(raw) ? raw + ', Nigeria' : raw + ', Kano, Nigeria';
            geocoder.geocode({ address, region: 'NG' }, (results, status) => {
                // A pin set by hand while the lookup was in flight wins.
                if (coordsEl && coordsEl.value.trim()) { setStatus('', ''); return; }
                if (status === 'OK' && results[0]) {
                    const pos = results[0].geometry.location;
                    placeMarker(pos.lat(), pos.lng(), true);
                    setStatus('ok', i === 0
                        ? '📍 Coordinates auto-set from the file location — drag the pin or click the map to adjust if it’s off.'
                        : '📍 Exact address not found; pin placed on the surrounding area — drag it onto the plot to correct it.');
                } else {
                    tryNext(i + 1);
                }
            });
        })(0);
    }

    function ensureMap() {
        if (map) return;
        map = L.map(mapId, { zoomControl: true }).setView(center, zoom);
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            { attribution: '© Esri', maxZoom: 19 }).addTo(map);
        // Click anywhere to drop / move the pin — suppressed while the polygon draw tool is active.
        map.on('click', e => { if (!drawing) placeMarker(e.latlng.lat, e.latlng.lng, false); });

        // Polygon boundary tracing (Leaflet.draw) — one plot boundary at a time.
        drawnItems = new L.FeatureGroup().addTo(map);
        map.addControl(new L.Control.Draw({
            position: 'topright',
            edit: { featureGroup: drawnItems, remove: true },
            draw: {
                polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#dc2626', weight: 3 } },
                marker: false, circlemarker: false, circle: false, polyline: false, rectangle: false,
            },
        }));
        map.on(L.Draw.Event.DRAWSTART, () => { drawing = true; });
        map.on(L.Draw.Event.DRAWSTOP,  () => { drawing = false; });
        map.on(L.Draw.Event.CREATED, e => {
            drawnItems.clearLayers(); // only one boundary per inspection
            drawnItems.addLayer(e.layer);
            syncGeometryField();
        });
        map.on(L.Draw.Event.EDITED,  syncGeometryField);
        map.on(L.Draw.Event.DELETED, syncGeometryField);

        // Two-way sync: typing coordinates moves the pin.
        const coordsEl = document.getElementById(coordsId);
        if (coordsEl) {
            coordsEl.addEventListener('change', function () {
                const parts = this.value.split(',');
                if (parts.length !== 2) return;
                const lat = parseFloat(parts[0]), lng = parseFloat(parts[1]);
                if (!isNaN(lat) && !isNaN(lng)) placeMarker(lat, lng, true);
            });
        }

        // "Use my location" — capture the surveyor's current GPS position.
        const locateBtn = document.getElementById(locateBtnId);
        if (locateBtn) {
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    Swal.fire({ icon: 'warning', title: 'Not supported', text: 'Geolocation is not available in this browser.' });
                    return;
                }
                this.disabled = true;
                navigator.geolocation.getCurrentPosition(
                    pos => { placeMarker(pos.coords.latitude, pos.coords.longitude, true); this.disabled = false; },
                    err => { Swal.fire({ icon: 'error', title: 'Location unavailable', text: err.message }); this.disabled = false; },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        }
    }

    // Container may have been display:none while its modal was hidden — recalc size,
    // reset any prior pin/boundary, then attempt to auto-pin from the given location.
    function init(location) {
        ensureMap();
        setTimeout(() => {
            map.invalidateSize();
            if (marker) { map.removeLayer(marker); marker = null; }
            drawnItems.clearLayers();
            const geomEl = document.getElementById(geometryId);
            if (geomEl) geomEl.value = '';
            map.setView(center, zoom);
            geocode(location);
        }, 150);
    }

    // Edit mode: show a visit's saved pin and traced boundary rather than
    // geocoding afresh. Falls back to the address when there is no pin.
    function load({ coords = '', geometry = null, location = '' } = {}) {
        ensureMap();
        setTimeout(() => {
            map.invalidateSize();
            if (marker) { map.removeLayer(marker); marker = null; }
            drawnItems.clearLayers();
            const geomEl = document.getElementById(geometryId);
            if (geomEl) geomEl.value = '';

            let bounds = null;
            if (geometry) {
                try {
                    L.geoJSON(geometry, { style: { color: '#dc2626', weight: 3 } })
                        .eachLayer(l => drawnItems.addLayer(l));
                    if (drawnItems.getLayers().length) {
                        bounds = drawnItems.getBounds();
                        if (geomEl) geomEl.value = JSON.stringify(geometry);
                    }
                } catch (e) { /* an unreadable boundary is left off the map, not fatal */ }
            }

            const pin = parseLatLngInput(coords);
            if (pin) {
                placeMarker(pin.lat, pin.lng, !bounds);
                setStatus('ok', '📍 The saved pin for this visit — drag it or click the map to move it.');
            } else {
                map.setView(center, zoom);
                geocode(location);
            }
            if (bounds) map.fitBounds(bounds, { maxZoom: 18, padding: [20, 20] });
        }, 150);
    }

    return { init, geocode, load, reset: () => init('') };
}

$(document).ready(function () {
    // ── Tab switching ──────────────────────────────────────────────────────
    const HASH_TAB = { '#field-map': 'field-map', '#records': 'records' };

    function activateTab(tabKey) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(p => p.classList.add('hidden'));
        const btn = document.querySelector(`.tab-btn[data-tab="${tabKey}"]`);
        if (btn) btn.classList.add('active');
        const content = document.getElementById('tab-' + tabKey);
        if (content) content.classList.remove('hidden');
        if (tabKey === 'records') setTimeout(() => $.fn.DataTable.tables({ visible:true, api:true }).columns.adjust(), 50);
        if (tabKey === 'field-map') setTimeout(() => { if (typeof map !== 'undefined') map.invalidateSize(); }, 100);
    }

    // Activate from hash on load
    activateTab(HASH_TAB[window.location.hash] || 'field-map');

    // Respond to sidebar link clicks (hash change)
    window.addEventListener('hashchange', () => {
        const tab = HASH_TAB[window.location.hash];
        if (tab) activateTab(tab);
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            history.replaceState(null, '', '#' + tab);
            activateTab(tab);
        });
    });

    // ── Leaflet map ────────────────────────────────────────────────────────
    const LAND_USE_COLORS = {
        RESIDENTIAL:  '#0f766e',
        COMMERCIAL:   '#0e7490',
        AGRICULTURAL: '#b45309',
        INDUSTRIAL:   '#7e22ce',
    };
    const DEFAULT_COLOR = '#4b5563';
    const KANO_CENTER   = [12.0, 8.52];
    const KANO_ZOOM     = 11;

    function landUseColor(landUse) {
        if (!landUse) return DEFAULT_COLOR;
        const key = landUse.toString().trim().toUpperCase();
        for (const [k, v] of Object.entries(LAND_USE_COLORS)) {
            if (key.includes(k)) return v;
        }
        return DEFAULT_COLOR;
    }

    function makeIcon(color) {
        return L.divIcon({
            className: '',
            html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>`,
            iconSize:   [14, 14],
            iconAnchor: [7, 7],
            popupAnchor:[0, -10],
        });
    }

    // Base tile layers
    const tileLayers = {
        streets:   L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',         { attribution:'© OpenStreetMap', maxZoom:19 }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution:'© Esri', maxZoom:19 }),
        terrain:   L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',           { attribution:'© OpenTopoMap', maxZoom:17 }),
    };

    const map = L.map('field-map', { zoomControl: false }).setView(KANO_CENTER, KANO_ZOOM);
    tileLayers.satellite.addTo(map);

    // Build markers
    const markersAll = [];
    MAP_POINTS.forEach(p => {
        if (!p.coords || !p.coords.lat || !p.coords.lng) return;
        const color  = landUseColor(p.applied_use || p.approved_use);
        const marker = L.marker([p.coords.lat, p.coords.lng], { icon: makeIcon(color) });
        marker._landUse = (p.applied_use || '').toString().trim().toUpperCase();
        const contraveneBadge = p.contravening
            ? `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;font-size:10px;font-weight:900;letter-spacing:.05em;">⚠ CONTRAVENTION</span>`
            : `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;background:#dcfce7;border:1.5px solid #86efac;color:#16a34a;font-size:10px;font-weight:900;letter-spacing:.05em;">✓ COMPLIANT</span>`;
        const photoHtml = p.photo
            ? `<img src="${p.photo}" style="width:100%;height:110px;object-fit:cover;border-radius:6px;margin-bottom:8px;display:block;">`
            : '';
        function luChip(val) {
            const cols = { RESIDENTIAL:'#0e7490', COMMERCIAL:'#ea580c', INDUSTRIAL:'#7c3aed', AGRICULTURAL:'#92400e' };
            const bg = cols[(val||'').toUpperCase().split(' ')[0]] || '#4b5563';
            return val ? `<span style="display:inline-block;padding:1px 7px;border-radius:99px;background:${bg};color:#fff;font-size:10px;">${val}</span>` : '<span style="color:#9ca3af;font-size:10px;">—</span>';
        }
        const popup = `
            <div style="min-width:240px;font-family:inherit;font-size:12px;">
                ${photoHtml}
                <div style="margin-bottom:7px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">File Number</div>
                    <div style="font-weight:700;font-size:13px;color:#1f2937;">${p.file_number || '—'}</div>
                </div>
                <div style="margin-bottom:7px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:1px;">File Title / Owner</div>
                    <div style="color:#374151;font-size:12px;">${p.owner || '—'}</div>
                </div>
                <div style="margin-bottom:8px;">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;margin-bottom:2px;">Location</div>
                    <div style="color:#374151;font-size:11px;padding:5px 7px;background:#f9fafb;border-radius:5px;border-left:3px solid rgb(186,191,12);">
                        ${p.location || '—'}
                    </div>
                </div>
                <div style="border-top:1px solid #f3f4f6;padding-top:7px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Applied Land Use</span>
                        ${luChip(p.applied_use)}
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Approved Land Use</span>
                        ${luChip(p.approved_use)}
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;padding-bottom:7px;border-bottom:1px solid #f3f4f6;">
                        <span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Prevailing Land Use</span>
                        ${luChip(p.prevailing)}
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:10px;color:#9ca3af;">Date Inspected</span>
                        <span style="font-size:11px;color:#374151;font-weight:500;">${p.date || '—'}</span>
                    </div>
                    <div style="margin-top:7px;">${contraveneBadge}</div>
                </div>
            </div>`;
        marker.bindPopup(popup);
        marker.addTo(map);
        markersAll.push(marker);
    });

    // Plot a freshly-logged inspection point on the live map — used by both the
    // "Log Field Inspection" modal and the field-inspection section of "Add Land
    // Record" (exposed on window so the latter's script block, further down the
    // page, can reach this map/markersAll closure without re-creating them).
    window.plotFieldMapPoint = function (p) {
        if (!p || !p.coords || !p.coords.lat || !p.coords.lng) return;
        const color  = landUseColor(p.applied_use || p.approved_use);
        const marker = L.marker([p.coords.lat, p.coords.lng], { icon: makeIcon(color) });
        marker._landUse = (p.applied_use || '').toString().trim().toUpperCase();
        function lc2(val) { const cols={RESIDENTIAL:'#0e7490',COMMERCIAL:'#ea580c',INDUSTRIAL:'#7c3aed',AGRICULTURAL:'#92400e'}; const bg=cols[(val||'').toUpperCase().split(' ')[0]]||'#4b5563'; return val?`<span style="display:inline-block;padding:1px 7px;border-radius:99px;background:${bg};color:#fff;font-size:10px;">${val}</span>`:'<span style="color:#9ca3af;font-size:10px;">—</span>'; }
        const cb = p.contravening
            ? `<span style="padding:3px 10px;border-radius:99px;background:#fee2e2;border:1.5px solid #fca5a5;color:#dc2626;font-size:10px;font-weight:900;letter-spacing:.05em;">⚠ CONTRAVENTION</span>`
            : `<span style="padding:3px 10px;border-radius:99px;background:#dcfce7;border:1.5px solid #86efac;color:#16a34a;font-size:10px;font-weight:900;letter-spacing:.05em;">✓ COMPLIANT</span>`;
        const ph = p.photo ? `<img src="${p.photo}" style="width:100%;height:110px;object-fit:cover;border-radius:6px;margin-bottom:8px;display:block;">` : '';
        marker.bindPopup(`<div style="min-width:240px;font-family:inherit;font-size:12px;">${ph}<div style="font-weight:700;font-size:13px;margin-bottom:2px;color:#1f2937;">${p.file_number||'—'}</div><div style="color:#6b7280;font-size:11px;margin-bottom:2px;">${p.owner||'—'}</div><div style="color:#374151;font-size:11px;margin-bottom:8px;padding:5px 7px;background:#f9fafb;border-radius:5px;border-left:3px solid rgb(186,191,12);">${p.location||'—'}</div><div style="border-top:1px solid #f3f4f6;padding-top:7px;"><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;"><span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Applied Land Use</span>${lc2(p.applied_use)}</div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;"><span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Approved Land Use</span>${lc2(p.approved_use)}</div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:7px;padding-bottom:7px;border-bottom:1px solid #f3f4f6;"><span style="font-size:10px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Prevailing Land Use</span>${lc2(p.prevailing)}</div><div style="margin-top:5px;">${cb}</div></div></div>`);
        marker.addTo(map);
        markersAll.push(marker);
        const countEl = document.getElementById('map-point-count');
        if (countEl) countEl.textContent = markersAll.length;
        map.panTo([p.coords.lat, p.coords.lng]);
    };

    // Hide loading overlay once map is ready
    map.whenReady(() => {
        const overlay = document.getElementById('map-loading');
        if (overlay) overlay.style.display = 'none';
    });

    // Zoom level indicator
    function updateZoomLabel() {
        const el = document.getElementById('map-zoom-level');
        if (el) el.textContent = map.getZoom();
    }
    map.on('zoomend', updateZoomLabel);
    updateZoomLabel();

    // Custom zoom / fullscreen / reset controls
    document.getElementById('map-zoom-in').addEventListener('click', () => map.zoomIn());
    document.getElementById('map-zoom-out').addEventListener('click', () => map.zoomOut());
    document.getElementById('map-reset').addEventListener('click', () => map.setView(KANO_CENTER, KANO_ZOOM));
    document.getElementById('map-fullscreen').addEventListener('click', () => {
        const el = document.getElementById('field-map');
        if (document.fullscreenElement) { document.exitFullscreen(); }
        else { el.requestFullscreen && el.requestFullscreen(); }
    });

    // Base map switcher
    document.getElementById('baseMapSelect').addEventListener('change', function () {
        Object.values(tileLayers).forEach(l => map.removeLayer(l));
        (tileLayers[this.value] || tileLayers.streets).addTo(map);
    });

    // Land use filter
    document.getElementById('landUseFilter').addEventListener('change', function () {
        const val = this.value.toUpperCase();
        markersAll.forEach(m => {
            if (val === 'ALL' || m._landUse.includes(val)) { m.addTo(map); }
            else { map.removeLayer(m); }
        });
        const visible = val === 'ALL' ? markersAll.length : markersAll.filter(m => m._landUse.includes(val)).length;
        const el = document.getElementById('map-point-count');
        if (el) el.textContent = visible;
    });

    // ── Records DataTable ──────────────────────────────────────────────────
    const LU_CLS = { Residential:'bg-blue-100 text-blue-700', Commercial:'bg-orange-100 text-orange-700', Industrial:'bg-purple-100 text-purple-700', Agricultural:'bg-green-100 text-green-700' };
    function luBadge(d) {
        const cls = LU_CLS[d] || 'bg-gray-100 text-gray-600';
        return d ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${d}</span>` : '—';
    }

    const fieldTable = $('#field-records-table').DataTable({
        processing : true, serverSide: true, scrollX: true,
        ajax: { url: window.location.href, headers:{'X-Requested-With':'XMLHttpRequest'}, data: d => ({ ...d, ajax: 1 }) },
        columns: [
            { data: 'DT_RowIndex', orderable:false, searchable:false },
            { data: 'spa_id', render: d => `<span style="white-space:nowrap;font-weight:600">${d||'—'}</span>` },
            { data: 'file_number', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
            { data: 'sticker_number', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
            // Mobile = pushed by the SPAS app; Web = the desktop form or the mobile web page.
            { data: 'source', render: d => d === 'mobile'
                ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-700" style="white-space:nowrap">📱 Mobile App</span>`
                : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700" style="white-space:nowrap">🖥 Web</span>`
            },
            { data: 'owner_name' },
            { data: 'location', render: d => d ? `<span title="${d}">${d.length>35?d.slice(0,35)+'…':d}</span>` : '—' },
            { data: 'land_use_type', render: d => luBadge(d) },
            { data: 'existing_use',  render: d => luBadge(d) },
            { data: 'contravening', render: d => d
                ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200">⚠ Yes</span>`
                : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">✓ No</span>`
            },
            { data: 'inspection_status', render: d => d === 'inspected'
                ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Inspected</span>`
                : `<span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Pending</span>`
            },
            { data: 'inspection_date', render: (d, t, row) => `<span style="white-space:nowrap">${d || '—'}</span>`
                + (row.visits > 1 ? `<span class="block text-[11px] text-indigo-600" style="white-space:nowrap">${row.visits} visits</span>` : '') },
            { data: 'findings', render: d => d||'—' },
            { data: 'inspected_by', render: d => `<span style="white-space:nowrap">${String(d || '—').replace(/[&<>"']/g, c => '&#' + c.charCodeAt(0) + ';')}</span>` },
            { data: 'created_by', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
            { data: 'date_created', render: d => `<span style="white-space:nowrap">${d || '—'}</span>` },
            // Thumbnails of every picture of the site; each opens full size.
            { data: 'photos', orderable: false, searchable: false, render: d => {
                const urls = d || [];
                if (!urls.length) return '<span class="text-gray-400 text-xs">None</span>';
                const shown = urls.slice(0, 3).map(u => `<a href="${u}" target="_blank" rel="noopener"><img src="${u}" loading="lazy" style="width:34px;height:34px;object-fit:cover;border-radius:5px;border:1px solid #e5e7eb" onerror="this.style.display='none'"></a>`).join('');
                return `<div style="display:flex;gap:3px;align-items:center">${shown}${urls.length > 3 ? `<span class="text-xs text-gray-500">+${urls.length - 3}</span>` : ''}</div>`;
            } },
            { data: 'action', orderable:false, searchable:false },
        ],
    });

    // ── View field inspection ─────────────────────────────────────────────
    const viewModal = document.getElementById('modal-view-field');
    function closeViewModal() { viewModal.classList.add('hidden'); viewModal.classList.remove('flex'); }
    document.getElementById('btn-close-view').addEventListener('click', closeViewModal);
    document.getElementById('btn-close-view2').addEventListener('click', closeViewModal);

    $(document).on('click', '.btn-view-field', function () {
        closeActionMenus();
        const info = JSON.parse($(this).attr('data-info') || '{}');
        window.SpasPipeline && SpasPipeline.mount(document.getElementById('vf-pipeline'), info.id, { current: 'field', force: true });
        // SPAS ID first; the file number only when the owner gave one.
        document.getElementById('vf-file').textContent      = [info.spa_id, info.file && info.file !== info.spa_id ? 'File ' + info.file : null].filter(Boolean).join(' · ') || '—';
        document.getElementById('vf-date').textContent      = info.date      || '—';
        document.getElementById('vf-inspector').textContent = info.inspector || '—';
        document.getElementById('vf-findings').textContent  = info.findings  || '—';

        // Two groups: photos taken with the land record, and with the inspection.
        // Each opens full size in a new tab.
        const showPhotos = (urls, gridId, wrapId) => {
            const grid = document.getElementById(gridId);
            grid.innerHTML = (urls || []).map(url =>
                `<a href="${url}" target="_blank" rel="noopener"><img src="${url}" alt="Photo" loading="lazy"
                    class="h-20 w-20 object-cover rounded border border-gray-200"
                    onerror="this.closest('a').outerHTML='<span class=\\'text-[11px] text-gray-400\\'>Photo missing on disk</span>'"></a>`).join('');
            document.getElementById(wrapId).classList.toggle('hidden', !(urls || []).length);
            return (urls || []).length;
        };
        const visits = info.visits || [];
        const escV = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        document.getElementById('vf-visits').innerHTML = visits.map((v, i) =>
            `<li class="border border-gray-100 rounded-lg px-3 py-2 ${i === 0 ? 'bg-[rgba(186,191,12,0.06)]' : ''}">
                <div class="flex justify-between text-xs text-gray-500"><span>${escV(v.date || '—')}${i === 0 ? ' · latest' : ''}</span><span>${escV(v.by || '—')}</span></div>
                <div class="text-gray-700 whitespace-pre-wrap mt-0.5">${escV(v.findings || '—')}</div>
            </li>`).join('');
        document.getElementById('vf-visits-wrap').classList.toggle('hidden', visits.length < 2);

        const nProperty   = showPhotos(info.property_photos, 'vf-property-photos', 'vf-property-photos-wrap');
        const nInspection = showPhotos(info.photos, 'vf-photos', 'vf-photos-wrap');
        document.getElementById('vf-no-photos').classList.toggle('hidden', nProperty + nInspection > 0);

        viewModal.classList.remove('hidden');
        viewModal.classList.add('flex');
        lucide.createIcons();
    });

    // ── Dropdown action menu ───────────────────────────────────────────────
    // Opened FIXED and lifted to <body>: inside the table it is clipped by the
    // table's scroll box (worst on the last rows). Item handlers are bound to
    // the document, so they still fire once the menu has moved.
    function closeActionMenus() {
        document.querySelectorAll('.action-dropdown').forEach(m => {
            m.classList.add('hidden');
            if (m._home) { m._home.appendChild(m); m._home = null; }   // back into its row
        });
    }
    $(document).on('click', '.btn-action-toggle', function(e) {
        e.stopPropagation();
        const menu = this.parentElement.querySelector('.action-dropdown') || this._menu;
        const wasOpen = menu && !menu.classList.contains('hidden');
        closeActionMenus();
        if (!menu || wasOpen) return;

        this._menu = menu;
        menu._home = this.parentElement;
        document.body.appendChild(menu);
        menu.classList.remove('hidden');
        Object.assign(menu.style, { position: 'fixed', zIndex: 2000, marginTop: 0, right: 'auto' });

        const r = this.getBoundingClientRect(), h = menu.offsetHeight, w = menu.offsetWidth;
        const top = (window.innerHeight - r.bottom < h + 8 && r.top > h + 8) ? r.top - h - 4 : r.bottom + 4;
        menu.style.top  = Math.max(8, top) + 'px';
        menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    });
    // Other script blocks on this page (the Edit card) close the menu too.
    window.closeActionMenus = closeActionMenus;
    $(document).on('click', function() { closeActionMenus(); });
    window.addEventListener('scroll', e => { if (!e.target.closest || !e.target.closest('.action-dropdown')) closeActionMenus(); }, true);
    window.addEventListener('resize', closeActionMenus);
    // A redraw replaces the rows a lifted menu belongs to.
    $('#field-records-table').on('preDraw.dt', closeActionMenus);

    // ── Log inspection (records saved without one) ─────────────────────────
    const logModal = document.getElementById('modal-log-inspection');
    const logForm  = document.getElementById('form-log-inspection');
    function closeLogModal() { logModal.classList.add('hidden'); logModal.classList.remove('flex'); }
    document.querySelectorAll('.li-close').forEach(b => b.addEventListener('click', closeLogModal));

    // Global so the Add Land Record flow can reopen an inspection that failed to
    // save, pre-filled with what the surveyor typed.
    // mode: 'log' (first inspection), 'revisit' (another visit to an inspected
    // record — it continues the same record) or 'edit' (change one's own visit).
    window.openLogInspection = function ({ id, file, spa = '', date, findings, coords, mode = 'log', editId = '' } = {}) {
        logForm.reset();
        document.getElementById('li-edit-id').value = editId || '';
        document.getElementById('li-title').textContent = mode === 'edit' ? 'Edit Field Inspection'
            : (mode === 'revisit' ? 'Revisit Inspection — continues this record' : 'Log Field Inspection');
        document.getElementById('btn-save-inspection').dataset.label = mode === 'edit' ? 'Save Changes' : 'Save Inspection';
        document.getElementById('btn-save-inspection').textContent = document.getElementById('btn-save-inspection').dataset.label;
        document.getElementById('li-app-id').value = id || '';
        document.getElementById('li-file').value   = file || '';
        document.getElementById('li-file-label').textContent = [spa, file && file !== spa ? 'File ' + file : null].filter(Boolean).join(' · ') || '—';
        document.getElementById('li-date').value     = date || new Date().toISOString().slice(0, 10);
        document.getElementById('li-findings').value = findings || '';
        document.getElementById('li-coords').value   = coords || '';
        logModal.classList.remove('hidden');
        logModal.classList.add('flex');
        lucide.createIcons();
    };

    $(document).on('click', '.btn-log-inspection', function () {
        closeActionMenus();
        window.openLogInspection({ id: $(this).data('id'), file: $(this).attr('data-file'), spa: $(this).attr('data-spa') || '', mode: $(this).data('revisit') ? 'revisit' : 'log' });
    });

    logForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const fd = new FormData(this);

        const coords = document.getElementById('li-coords').value.trim();
        if (coords) {
            const parsed = parseLatLngInput(coords);
            if (!parsed) {
                Swal.fire({ icon:'warning', title:'Check the coordinates', text:'Enter them as latitude, longitude, e.g. 12.0022, 8.5919 — or leave the field empty.' });
                return;
            }
            fd.append('coordinates', JSON.stringify(parsed));
        }

        const btn = document.getElementById('btn-save-inspection');
        const editId = document.getElementById('li-edit-id').value;
        btn.disabled = true; btn.textContent = 'Saving…';
        try {
            const url  = editId ? `${FIELD_BASE}/${editId}/update` : STORE;
            const res  = await fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd });
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                closeLogModal();
                if (data.mapPoint) window.plotFieldMapPoint(data.mapPoint);
                fieldTable.ajax.reload(null, false);
                Swal.fire({ icon:'success', title: editId ? 'Inspection updated' : 'Inspection logged', text: data.message || 'Field inspection saved.', timer:2000, showConfirmButton:false });
            } else {
                const first = Object.values(data.errors || {})[0];
                Swal.fire({ icon:'error', title:'Could not save', text: (first && first[0]) || data.message || 'Save failed.' });
            }
        } catch (err) {
            Swal.fire({ icon:'error', title:'Error', text:'A network error stopped the save. Try again.' });
        }
        btn.disabled = false; btn.textContent = btn.dataset.label || 'Save Inspection';
    });

    // ── Delete field record ────────────────────────────────────────────────
    $(document).on('click', '.btn-delete-field', function() {
        closeActionMenus();
        const id = $(this).data('id');
        Swal.fire({
            title: 'Delete Record?',
            text: 'This will permanently remove the field inspection record.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete',
        }).then(result => {
            if (!result.isConfirmed) return;
            fetch(`${DELETE_FIELD}/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    $.fn.DataTable.tables({ visible:true, api:true }).ajax.reload();
                    Swal.fire({ icon:'success', title:'Deleted', text: d.message, timer:2000, showConfirmButton:false });
                } else {
                    Swal.fire({ icon:'error', title:'Error', text: d.message });
                }
            });
        });
    });

    // ── Master delete: the record and everything linked to it ──────────────
    // Super Admin only. Shows what will go, then asks for the record's
    // reference to be typed. The server exports every row and photo first.
    const MASTER_DELETE = '{{ url("special-assignment/land-records") }}';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    $(document).on('click', '.btn-master-delete', async function () {
        closeActionMenus();
        const id = $(this).data('id');
        let info;
        try {
            const r = await fetch(`${MASTER_DELETE}/${id}/master-delete`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            info = await r.json();
            if (!r.ok || !info.success) throw new Error(info.message || 'Could not load the record.');
        } catch (e) {
            return Swal.fire({ icon: 'error', title: 'Error', text: e.message });
        }

        const list = info.counts.map(c => `<li><b>${c.count}</b> ${esc(c.label)}</li>`).join('')
            + (info.photos ? `<li><b>${info.photos}</b> photo file(s)</li>` : '');
        const res = await Swal.fire({
            title: 'Master delete ' + esc(info.reference) + '?',
            icon: 'warning',
            html: `<div class="text-left text-sm">
                     <p class="mb-2">This permanently removes the land record${info.owner ? ' of <b>' + esc(info.owner) + '</b>' : ''} and everything linked to it in SPAS:</p>
                     <ul class="list-disc pl-6 mb-3">${list || '<li>No inspections, notices, bills or other linked items</li>'}</ul>
                     ${(info.kept || []).map(k => `<p class="mb-2 text-amber-700">${esc(k)}</p>`).join('')}
                     <p class="mb-2 text-gray-600">A copy is saved to the server's audit folder first. Phones that already synced this record will still show their own copy.</p>
                     <p>Type <b>${esc(info.reference)}</b> to confirm:</p>
                   </div>`,
            input: 'text',
            inputPlaceholder: info.reference,
            showCancelButton: true,
            confirmButtonColor: '#b91c1c',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Delete everything',
            preConfirm: v => {
                if ((v || '').trim().toUpperCase() !== String(info.reference).trim().toUpperCase()) {
                    Swal.showValidationMessage('Type ' + info.reference + ' exactly');
                    return false;
                }
                return v.trim();
            },
        });
        if (!res.isConfirmed) return;

        Swal.fire({ title: 'Deleting…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const r = await fetch(`${MASTER_DELETE}/${id}/master-delete`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ confirm: res.value }),
            });
            const d = await r.json();
            if (!r.ok || !d.success) throw new Error(d.message || 'Delete failed.');
            // Reload: the map's points come with the page, and the record must leave it too.
            await Swal.fire({ icon: 'success', title: 'Deleted', text: d.message });
            location.reload();
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Not deleted', text: e.message });
        }
    });

});
</script>

{{-- ── Add to SPAS flow (file selector card → Add Land Record modal) ──────────── --}}
<script>
const LR_STORE = '{{ route("special-assignment.land-records.store") }}';
const LR_LOOKUP = '{{ route("special-assignment.check-file") }}';
const NEXT_CUSTOMARY_URL = '{{ route("special-assignment.next-customary-fileno") }}';

$(document).ready(function () {
    const spaModal = document.getElementById('modal-add-record');

    // Reuses the same pin/drag/geocode/polygon-trace controller as "Log Field
    // Inspection", so a surveyor can capture the inspection in the same pass as
    // adding the land record instead of coming back to it later.
    const arMapCtrl = createInspectionMapController({
        mapId: 'ar-map', coordsId: 'ar-coords', geometryId: 'ar-geometry',
        statusId: 'ar-map-status', locateBtnId: 'ar-locate',
    });

    function openSpaModal(mapState = null) {
        spaModal.classList.remove('hidden');
        spaModal.classList.add('flex');
        if (mapState) arMapCtrl.load(mapState);
        else arMapCtrl.init(document.getElementById('h-location').value || '');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    function closeSpaModal() {
        spaModal.classList.add('hidden');
        spaModal.classList.remove('flex');
        setEditMode(null);
        const badge = document.getElementById('contravention-badge');
        badge.classList.add('hidden');
        badge.style.display = 'none';
    }
    ['btn-close-modal', 'btn-cancel-modal'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('click', closeSpaModal);
    });

    // ── Customary address picker (LGA + District) ───────────────────────────
    // Districts are fetched once, on the first switch to Customary Title, rather
    // than rendered inline: there are 1,818 of them and inlining that list is
    // what made the OSS applications page unusable.
    let districtsPromise = null;
    let allDistricts = [];
    const isOtherDistrict = v => String(v || '').trim().toLowerCase() === 'other';
    const escHtml = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // The full list, "Other (specify)" first. Searching happens inside the
    // dropdown itself (SearchableSelect), which keeps "Other" pinned on top.
    function renderDistricts() {
        const sel   = document.getElementById('f-district');
        const keep  = sel.value;
        const other = allDistricts.find(isOtherDistrict) || 'Other';
        sel.innerHTML = '<option value="">Select District…</option>'
            + `<option value="${escHtml(other)}">Other (specify)</option>`
            + allDistricts.filter(n => !isOtherDistrict(n)).map(n => `<option value="${escHtml(n)}">${escHtml(n)}</option>`).join('');
        sel.value = keep;
    }
    SearchableSelect(document.getElementById('f-district'), {
        placeholder: 'Select District…', searchPlaceholder: 'Type to search districts…', pinned: ['Other'],
    });

    function loadDistricts() {
        if (districtsPromise) return districtsPromise;
        const sel = document.getElementById('f-district');
        districtsPromise = fetch('{{ route("api.reference.districts") }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                allDistricts = ((d && d.data) || []).map(x => (x && typeof x === 'object') ? x.name : x).filter(Boolean);
                renderDistricts();
            })
            .catch(() => {
                sel.innerHTML = '<option value="">Districts unavailable — pick the pin on the map</option>';
                districtsPromise = null; // allow a retry on the next switch
            });
        return districtsPromise;
    }


    // "Other": the district typed in the box beside it is the one recorded.
    function districtValue() {
        const sel = document.getElementById('f-district');
        return isOtherDistrict(sel.value)
            ? document.getElementById('f-district-other').value.trim()
            : sel.value.trim();
    }
    document.getElementById('f-district').addEventListener('change', function () {
        const other = document.getElementById('f-district-other');
        const show  = isOtherDistrict(this.value);
        other.classList.toggle('hidden', !show);
        if (show) other.focus(); else other.value = '';
    });
    // On change (leaving the box), not per keystroke: each update re-geocodes the pin.
    document.getElementById('f-district-other').addEventListener('change', () => applyCustomaryLocation());

    // LGA / District drive both the stored location and the map pin.
    function applyCustomaryLocation() {
        const lga      = document.getElementById('f-lga').value.trim();
        const district = districtValue();
        const loc      = [district, lga].filter(Boolean).join(', ');

        document.getElementById('h-location').value = loc;
        const badgeWrap = document.getElementById('location-badge-wrap');
        if (loc) {
            document.getElementById('location-badge-text').textContent = loc + ', Kano';
            badgeWrap.classList.remove('hidden');
        } else {
            badgeWrap.classList.add('hidden');
        }

        if (!lga && !district) return;
        // force: the address just changed, so any earlier pin is stale.
        arMapCtrl.geocode([loc, lga].filter(Boolean), { force: true });
    }
    ['f-lga', 'f-district'].forEach(id => {
        document.getElementById(id).addEventListener('change', applyCustomaryLocation);
    });

    // ── Land Title Type toggle ──────────────────────────────────────────────
    // Statutory: file number is picked from an existing indexed file.
    // Customary: no existing file — a temporary "SPAS-YYYY-####" number is
    // generated server-side and the owner/applicant name is typed manually.
    // The next SPAS ID, shown as a preview; the server assigns the real one on
    // save (the same sequence numbers customary titles).
    function previewSpasId() {
        const box  = document.getElementById('display-spa-id');
        const hint = document.getElementById('spa-id-hint');
        box.value = '';
        box.placeholder = 'Generating…';
        return fetch(NEXT_CUSTOMARY_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                const next = d.spa_id || d.file_number || '';
                box.value = next;
                hint.textContent = 'Next free number — confirmed when the record is saved.';
                return next;
            })
            .catch(() => { box.placeholder = 'Assigned when the record is saved'; return ''; });
    }

    // Sticker number: scanned from its QR code (partials/qr_scan), or typed.
    document.getElementById('btn-scan-sticker').addEventListener('click', function () {
        if (!window.SpaQrScan) { Swal.fire({ icon:'error', title:'Scanner unavailable', text:'Type the sticker number instead.' }); return; }
        window.SpaQrScan.open(sticker => {
            if (!sticker) return;
            const box = document.getElementById('f-sticker');
            box.value = sticker.toUpperCase();
            box.focus();
        });
    });

    // Statutory: owner and land use come from the file when one is picked, and
    // are locked then. With no file — most owners do not know theirs — the
    // officer types the owner and picks the land use, like a customary title.
    function statutoryFromFile(hasFile) {
        const owner    = document.getElementById('f-owner_name');
        const luInput  = document.getElementById('f-land_use_type');
        const luSelect = document.getElementById('f-land_use_type_select');
        owner.readOnly = hasFile;
        ['bg-gray-50', 'text-gray-700', 'cursor-default'].forEach(c => owner.classList.toggle(c, hasFile));
        owner.placeholder = hasFile ? 'Auto-filled from file number' : "Enter the owner's name";
        luInput.classList.toggle('hidden', !hasFile);
        luInput.disabled = !hasFile;
        luSelect.classList.toggle('hidden', hasFile);
        luSelect.disabled = hasFile;
    }

    function setLandTitleType(type, { keepNumber = false } = {}) {
        const selectBtn  = document.getElementById('btn-pick-fileno');
        const fileInput  = document.getElementById('display-file-number');
        const ownerInput = document.getElementById('f-owner_name');
        const msg        = document.getElementById('lookup-msg');
        const luInput    = document.getElementById('f-land_use_type');
        const luSelect   = document.getElementById('f-land_use_type_select');
        const geoWrap    = document.getElementById('customary-geo-wrap');
        const lgaSelect  = document.getElementById('f-lga');
        const distSelect = document.getElementById('f-district');

        document.getElementById('h-file_number').value      = '';
        document.getElementById('h-file_indexing_id').value = '';
        document.getElementById('h-tracking_id').value      = '';
        document.getElementById('h-location').value          = '';
        document.getElementById('h-lga').value               = '';
        document.getElementById('h-district').value          = '';
        document.getElementById('f-land_use_type').value    = '';
        document.getElementById('location-badge-wrap').classList.add('hidden');
        document.getElementById('dciv-banner-wrap').classList.add('hidden');
        msg.classList.add('hidden');
        ownerInput.value = '';
        fileInput.value  = '';

        // Only the active control carries each name (land_use_type / lga /
        // district); its counterpart is disabled so the form never posts two.
        luSelect.value = '';
        const customary = type === 'customary';
        luInput.classList.toggle('hidden', customary);
        luInput.disabled = customary;
        luSelect.classList.toggle('hidden', !customary);
        luSelect.disabled = !customary;

        lgaSelect.value  = '';
        distSelect.value = '';
        lgaSelect.disabled  = !customary;
        distSelect.disabled = !customary;
        document.getElementById('h-lga').disabled      = customary;
        document.getElementById('h-district').disabled = customary;
        geoWrap.classList.toggle('hidden', !customary);
        geoWrap.classList.toggle('grid', customary);
        if (customary) loadDistricts();

        if (customary) {
            selectBtn.disabled = true;
            fileInput.placeholder = 'Generating…';
            ownerInput.readOnly = false;
            ownerInput.classList.remove('bg-gray-50', 'text-gray-700', 'cursor-default');
            ownerInput.placeholder = "Enter applicant's name";
            document.getElementById('h-is_indexed').value = '0';

            // Editing: the record already has its number — never draw a new one.
            if (keepNumber) return;

            // A customary title's file number IS its SPAS ID.
            previewSpasId().then(next => {
                if (next) { fileInput.value = next; document.getElementById('h-file_number').value = next; }
                else fileInput.placeholder = 'Assigned on save';
            });
        } else {
            selectBtn.disabled = false;
            fileInput.placeholder = 'Not known — select it if the owner has one';
            if (!keepNumber) previewSpasId();
            statutoryFromFile(false);
        }
    }
    document.querySelectorAll('input[name="land_title_type"]').forEach(r => {
        r.addEventListener('change', () => setLandTitleType(r.value));
    });

    // Reset the form to a clean state before showing the picker
    function resetSpaForm() {
        document.getElementById('form-add-record').reset();
        document.getElementById('display-file-number').value = '';
        document.getElementById('h-file_number').value       = '';
        document.getElementById('h-file_indexing_id').value  = '';
        document.getElementById('h-tracking_id').value       = '';
        document.getElementById('h-is_indexed').value        = '0';
        document.getElementById('h-location').value          = '';
        document.getElementById('h-lga').value               = '';
        document.getElementById('h-district').value          = '';
        document.getElementById('h-proposed_use').value      = '';
        document.getElementById('f-owner_name').value        = '';
        document.getElementById('f-land_use_type').value     = '';
        document.getElementById('f-phone').value             = '';
        document.getElementById('photo-preview').innerHTML   = '';
        document.getElementById('lookup-msg').classList.add('hidden');
        document.getElementById('location-badge-wrap').classList.add('hidden');
        document.getElementById('dciv-banner-wrap').classList.add('hidden');
        document.getElementById('ltt-statutory').checked = true;
        // District search and "Other" box back to blank, the full list back.
        document.getElementById('f-district-other').classList.add('hidden');
        if (allDistricts.length) renderDistricts();
        setLandTitleType('statutory');
        // Note: the Field Inspection inputs (ar-*) live inside this <form>, so the
        // native reset() call above already clears them; the map pin/boundary are
        // reset separately by arMapCtrl.init() when the modal (re)opens.
    }

    // "Clear form": the whole card back to blank without closing it — fields,
    // photos, the selected file, the map pin and the traced boundary.
    document.getElementById('btn-clear-record').addEventListener('click', async function () {
        const ask = await Swal.fire({
            icon: 'question',
            title: 'Clear the form?',
            text: 'Everything entered on this card will be removed, including photos and the map pin.',
            showCancelButton: true,
            confirmButtonText: 'Clear',
            cancelButtonText: 'Keep it',
            confirmButtonColor: 'rgb(186,191,12)',
        });
        if (!ask.isConfirmed) return;

        resetSpaForm();
        document.getElementById('f-photos').value = '';
        arMapCtrl.reset();
        const badge = document.getElementById('contravention-badge');
        if (badge) { badge.classList.add('hidden'); badge.style.display = 'none'; }
        const btn = document.getElementById('btn-save-record');
        btn.disabled = false; btn.textContent = 'Save Record';
        document.getElementById('form-add-record').scrollTop = 0;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    // Pull details from file indexing and pre-fill the modal
    async function prefillFromFile(fileNo) {
        const msg = document.getElementById('lookup-msg');
        msg.className   = 'text-xs mt-1 text-gray-400';
        msg.textContent = 'Loading file details…';
        msg.classList.remove('hidden');

        document.getElementById('display-file-number').value = fileNo;
        document.getElementById('h-file_number').value       = fileNo;

        try {
            const ctrl  = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 8000);
            const res   = await fetch(`${LR_LOOKUP}?file_number=${encodeURIComponent(fileNo)}`, { signal: ctrl.signal });
            clearTimeout(timer);
            const d = await res.json();

            if (d.found) {
                statutoryFromFile(true);
                const owner = (d.file_title || d.owner_name || '').trim();
                document.getElementById('f-owner_name').value       = owner;
                document.getElementById('f-phone').value            = d.phone || '';
                document.getElementById('f-land_use_type').value    = d.land_use_type || '';
                document.getElementById('h-proposed_use').value     = d.land_use_type || '';
                document.getElementById('h-file_indexing_id').value = d.file_indexing_id || '';
                document.getElementById('h-tracking_id').value      = d.tracking_id || '';
                document.getElementById('h-location').value         = d.location || '';
                document.getElementById('h-lga').value              = d.lga || '';
                document.getElementById('h-district').value         = d.district || '';
                document.getElementById('h-is_indexed').value       = '1';

                const loc = [d.location, d.district, d.lga].filter(Boolean).join(', ');
                if (loc) {
                    document.getElementById('location-badge-text').textContent = loc;
                    document.getElementById('location-badge-wrap').classList.remove('hidden');
                } else {
                    document.getElementById('location-badge-wrap').classList.add('hidden');
                }
                // Auto-set the inspection coordinates from the file's address.
                // Most specific first, then progressively broader, so a street
                // address the geocoder doesn't know still lands on its district/LGA.
                arMapCtrl.geocode([
                    loc,
                    [d.district, d.lga].filter(Boolean).join(', '),
                    d.lga || '',
                ]);
                // DCIV investigation banner
                const dcivWrap   = document.getElementById('dciv-banner-wrap');
                const dcivFileNo = document.getElementById('dciv-banner-fileno');
                const dcivReason = document.getElementById('dciv-banner-reason');
                if (d.dciv_status === 1 && d.dciv_fileno) {
                    dcivFileNo.textContent = d.dciv_fileno;
                    dcivReason.textContent = d.dciv_reason || 'Not specified';
                    dcivWrap.classList.remove('hidden');
                    if (window.lucide) lucide.createIcons();
                } else {
                    dcivWrap.classList.add('hidden');
                }

                msg.className   = 'text-xs mt-1 text-green-600';
                msg.textContent = '✓ File found — details pre-filled.';
            } else {
                statutoryFromFile(false);
                document.getElementById('h-is_indexed').value = '0';
                document.getElementById('location-badge-wrap').classList.add('hidden');
                document.getElementById('dciv-banner-wrap').classList.add('hidden');
                msg.className   = 'text-xs mt-1 text-amber-600';
                msg.textContent = 'File not in index — please fill in details manually.';
            }
        } catch (err) {
            msg.className   = 'text-xs mt-1 text-red-500';
            msg.textContent = err.name === 'AbortError'
                ? 'Lookup timed out — please fill in details manually.'
                : 'Could not load file details. Please fill in manually.';
        }
    }

    // Open the Add Land Record card; the user picks the file via the in-modal "Select" button
    function startAddToSpa() {
        setEditMode(null);
        resetSpaForm();
        openSpaModal();
    }

    // Page button: open the Add Land Record modal
    document.getElementById('btn-add-spa').addEventListener('click', startAddToSpa);

    // In-modal "Select" button: re-open the file-selector card
    document.getElementById('btn-pick-fileno').addEventListener('click', function () {
        if (!window.GlobalFileNoModal) {
            Swal.fire({ icon:'error', title:'Unavailable', text:'File number selector not loaded. Please refresh the page.' });
            return;
        }
        window.GlobalFileNoModal.open({
            callback: function (data) {
                if (!data || !data.fileNumber) return;
                prefillFromFile(data.fileNumber);
            }
        });
    });

    // Whichever General Landuse control is active: the file-filled input for a
    // statutory title, the dropdown for a customary one.
    function currentLandUse() {
        const sel = document.getElementById('f-land_use_type_select');
        const el  = sel.disabled ? document.getElementById('f-land_use_type') : sel;
        return (el.value || '').trim();
    }

    // Contravention check (General Landuse vs Prevailing)
    function checkContravention() {
        const approved   = currentLandUse().toUpperCase();
        const prevailing = (document.getElementById('f-existing_use').value   || '').trim().toUpperCase();
        const badge      = document.getElementById('contravention-badge');
        if (approved && prevailing && approved !== prevailing) {
            badge.classList.remove('hidden');
            badge.style.display = 'flex';
        } else {
            badge.classList.add('hidden');
            badge.style.display = 'none';
        }
    }
    document.getElementById('f-existing_use').addEventListener('change', checkContravention);
    document.getElementById('f-land_use_type_select').addEventListener('change', function () {
        // proposed_use mirrors the general landuse, same as the statutory path
        document.getElementById('h-proposed_use').value = this.value;
        checkContravention();
    });

    // Photo preview
    document.getElementById('f-photos').addEventListener('change', function () {
        const preview = document.getElementById('photo-preview');
        preview.innerHTML = '';
        Array.from(this.files).forEach(file => {
            const url = URL.createObjectURL(file);
            preview.innerHTML += `
                <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 flex-shrink-0">
                    <img src="${url}" class="w-full h-full object-cover">
                </div>`;
        });
    });

    // If the surveyor logged an inspection alongside the record, save it too —
    // as a second call, since it needs the spa_application_id the first call returns.
    //
    // Returns { status, note, entered }: status is 'none' (no inspection typed),
    // 'ok', or 'failed'. On 'failed' the caller keeps the record and offers to
    // log the inspection straight away with `entered`, rather than flashing a
    // message in a popup that closes itself before anyone reads it.
    async function saveInspectionFor(app) {
        const inspDate     = document.getElementById('ar-inspection-date').value;
        const inspFindings = document.getElementById('ar-findings').value.trim();
        const inspCoords   = document.getElementById('ar-coords').value.trim();
        const inspGeometry = document.getElementById('ar-geometry').value;
        const entered      = { id: app.id, file: app.file_number, date: inspDate, findings: inspFindings, coords: inspCoords };
        const failed       = reason => ({ status: 'failed', note: reason, entered });

        // Coordinates are auto-filled from the file's location, so they alone do
        // not mean an inspection was entered. Date + findings do; the submit
        // handler has already refused a half-filled pair.
        if (!inspDate && !inspFindings) {
            return { status: 'none', note: ' No field inspection was logged — use "Log Inspection" in the row menu when it is done.', entered };
        }
        if (!inspDate || !inspFindings) {
            return failed('An inspection date and findings are both required.');
        }

        const fd = new FormData();
        fd.append('spa_application_id', app.id);
        fd.append('file_number', app.file_number);
        fd.append('inspection_date', inspDate);
        fd.append('findings', inspFindings);
        let coordsWarning = '';
        if (inspCoords) {
            const parsed = parseLatLngInput(inspCoords);
            if (parsed) fd.append('coordinates', JSON.stringify(parsed));
            else coordsWarning = ' The pin location could not be understood, so it was not saved — you can add it later from Field Data.';
        }
        if (inspGeometry) fd.append('parcel_geometry', inspGeometry);

        try {
            // Accept: JSON, or a validation failure answers with a redirect to
            // this page's HTML and the real reason is lost as a "network error".
            const res  = await fetch(STORE, { method:'POST', headers:{ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd });
            const data = await res.json().catch(() => ({}));
            if (data.success) {
                if (data.mapPoint) window.plotFieldMapPoint(data.mapPoint);
                return { status: 'ok', note: ' Field inspection logged.' + coordsWarning, entered };
            }
            const first = Object.values(data.errors || {})[0];
            return failed((first && first[0]) || data.message || `The server refused it (HTTP ${res.status}).`);
        } catch (err) {
            return failed('A network error stopped the save.');
        }
    }

    // ── Edit mode ───────────────────────────────────────────────────────────
    // "Edit" in a row's menu opens THIS card with the record and its latest
    // visit filled in — one form with every field, instead of separate Edit
    // Land Record / Edit Field Inspection modals. The record's part is open to
    // the officer who captured it or a Super Admin; the visit's part to the
    // officer who made it or a Super Admin (the server checks both again).
    let editCtx = null;

    // Pick an option case-insensitively, adding it when the list lacks it, so a
    // stored value never silently becomes blank on save.
    const setSelect = (sel, value) => {
        if (!value) { sel.value = ''; return; }
        const hit = [...sel.options].find(o => o.value.toUpperCase() === String(value).toUpperCase());
        if (!hit) sel.add(new Option(value, value));
        sel.value = hit ? hit.value : value;
        if (sel._ss) sel._ss.refresh();
    };

    const RECORD_FIELDS = ['f-sticker', 'btn-scan-sticker', 'f-owner_name', 'f-phone', 'f-existing_use', 'f-land_use_type_select', 'f-lga', 'f-district', 'f-district-other', 'f-photos'];
    const VISIT_FIELDS  = ['ar-inspection-date', 'ar-coords', 'ar-findings', 'ar-insp-photo-input'];

    function lockFields(ids, locked) {
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (locked) el.disabled = true;
            el.classList.toggle('opacity-60', locked);
            if (el._ss) el._ss.refresh();
        });
    }

    function thumbs(urls) {
        return (urls || []).map(u => `<a href="${escHtml(u)}" target="_blank" rel="noopener"><img src="${escHtml(u)}" loading="lazy"
            class="w-20 h-20 object-cover rounded-lg border border-gray-200"
            onerror="this.closest('a').outerHTML='<span class=\\'text-[11px] text-gray-400\\'>Photo missing on disk</span>'"></a>`).join('');
    }

    function setEditMode(ctx) {
        editCtx = ctx;
        const editing = !!ctx;
        const strip = document.getElementById('ar-pipeline');
        document.getElementById('ar-wf-static').classList.toggle('hidden', editing);
        if (window.SpasPipeline && editing && ctx.id) SpasPipeline.mount(strip, ctx.id, { current: 'field', force: true });
        else { strip.classList.add('hidden'); strip.innerHTML = ''; }
        document.getElementById('ar-card-title').textContent = editing ? 'Edit Land Record' : 'Add Land Record';
        const sub = document.getElementById('ar-edit-sub');
        sub.textContent = editing ? (ctx.file || '') : '';
        sub.classList.toggle('hidden', !editing);
        document.getElementById('btn-clear-record').classList.toggle('hidden', editing);
        document.getElementById('btn-save-record').textContent = editing ? 'Save Changes' : 'Save Record';
        document.querySelectorAll('input[name="land_title_type"]').forEach(r => { r.disabled = editing; });

        if (!editing) {
            ['f-sticker', 'btn-scan-sticker', 'f-phone', 'f-existing_use', 'f-photos', ...VISIT_FIELDS].forEach(id => {
                const el = document.getElementById(id);
                if (el) { el.disabled = false; el.classList.remove('opacity-60'); }
            });
            ['f-owner_name', 'f-land_use_type_select', 'f-lga', 'f-district', 'f-district-other']
                .forEach(id => document.getElementById(id)?.classList.remove('opacity-60'));
            document.getElementById('ar-edit-note').classList.add('hidden');
            document.getElementById('ar-edit-note').textContent = '';
            document.getElementById('photo-existing-wrap').classList.add('hidden');
            document.getElementById('photo-existing').innerHTML = '';
            document.getElementById('ar-insp-photos-wrap').classList.add('hidden');
            document.getElementById('ar-insp-photos').innerHTML = '';
            document.getElementById('ar-insp-photo-input').value = '';
        }
    }

    async function openEditCard(d) {
        setEditMode(null);   // undo any locks left by a previous edit
        resetSpaForm();
        const customary = d.land_title_type === 'customary';
        document.getElementById(customary ? 'ltt-customary' : 'ltt-statutory').checked = true;
        setLandTitleType(customary ? 'customary' : 'statutory', { keepNumber: true });
        setEditMode(d);

        // The SPAS ID and file number never change on an edit.
        document.getElementById('display-spa-id').value      = d.spa_id || '';
        document.getElementById('display-spa-id').placeholder = d.spa_id ? '' : 'None';
        document.getElementById('spa-id-hint').textContent   = 'This record\'s SPAS ID.';
        document.getElementById('display-file-number').value = d.file || '';
        document.getElementById('h-file_number').value       = d.file || '';
        document.getElementById('btn-pick-fileno').disabled  = true;
        document.getElementById('h-file_indexing_id').value  = d.file_indexing_id || '';
        document.getElementById('h-tracking_id').value       = d.tracking_id || '';
        document.getElementById('h-is_indexed').value        = d.is_indexed ? '1' : '0';

        // The owner is editable on an edit (it was on the old Edit Record card),
        // for whoever may edit the record.
        // A statutory record saved without a file number edits like one with no file.
        if (!customary) statutoryFromFile(!!d.file);
        const owner = document.getElementById('f-owner_name');
        owner.value = d.owner_name || '';
        owner.readOnly = !d.can_edit_record;
        owner.classList.toggle('bg-gray-50', !d.can_edit_record);
        owner.classList.toggle('cursor-default', !d.can_edit_record);

        document.getElementById('f-phone').value = d.phone || '';
        document.getElementById('f-sticker').value = d.sticker_number || '';
        setSelect(document.getElementById('f-existing_use'), d.existing_use);
        if (customary || !d.file) setSelect(document.getElementById('f-land_use_type_select'), d.land_use_type);
        else document.getElementById('f-land_use_type').value = d.land_use_type || '';
        document.getElementById('h-proposed_use').value = d.proposed_use || d.land_use_type || '';

        document.getElementById('h-location').value = d.location || '';
        document.getElementById('h-lga').value      = d.lga || '';
        document.getElementById('h-district').value = d.district || '';

        if (customary) {
            setSelect(document.getElementById('f-lga'), d.lga);
            await loadDistricts();
            const sel   = document.getElementById('f-district');
            const other = document.getElementById('f-district-other');
            const hit   = d.district && allDistricts.find(n => n.toUpperCase() === String(d.district).toUpperCase());
            if (hit) {
                sel.value = hit;
            } else if (d.district) {
                // Not on the list: it was typed under "Other" when captured.
                sel.value = [...sel.options].find(o => isOtherDistrict(o.value))?.value || '';
                other.value = d.district;
                other.classList.remove('hidden');
            }
            if (sel._ss) sel._ss.refresh();
        }

        const parts = customary ? [d.location] : [d.location, d.district, d.lga];
        const loc = [...new Set(parts.filter(Boolean))].join(', ');
        document.getElementById('location-badge-text').textContent = loc ? loc + (customary ? ', Kano' : '') : '';
        document.getElementById('location-badge-wrap').classList.toggle('hidden', !loc);

        checkContravention();

        // Photos already on the record; new ones picked here are added to them.
        document.getElementById('photo-existing').innerHTML = thumbs(d.photos);
        document.getElementById('photo-existing-wrap').classList.toggle('hidden', !(d.photos || []).length);

        // The latest visit. With none, the inspection section logs a first one.
        const insp = d.inspection;
        if (insp) {
            document.getElementById('ar-inspection-date').value = insp.date || '';
            document.getElementById('ar-findings').value        = insp.findings || '';
            document.getElementById('ar-coords').value          = insp.coords || '';
            document.getElementById('ar-insp-photos').innerHTML = thumbs(insp.photos) || '<span class="text-[11px] text-gray-400">None yet.</span>';
            document.getElementById('ar-insp-photos-wrap').classList.remove('hidden');
        }

        const notes = [];
        if (!d.can_edit_record) {
            lockFields(RECORD_FIELDS, true);
            notes.push('The record details can be changed only by the officer who captured it or a Super Admin.');
        }
        if (insp && !d.can_edit_visit) {
            lockFields(VISIT_FIELDS, true);
            notes.push(`The inspection (by ${insp.by || 'another officer'}) can be changed only by that officer or a Super Admin — use "Add Revisit Inspection" to add your own.`);
        }
        if (insp && d.can_edit_visit) notes.push('Editing the latest inspection' + (insp.by ? ` (by ${insp.by})` : '') + '.');
        const note = document.getElementById('ar-edit-note');
        note.textContent = notes.join(' ');
        note.classList.toggle('hidden', !notes.length);

        openSpaModal({ coords: insp?.coords || '', geometry: insp?.geometry || null, location: [loc, [d.district, d.lga].filter(Boolean).join(', '), d.lga || ''] });
    }

    $(document).on('click', '.btn-edit-full', function () {
        if (window.closeActionMenus) window.closeActionMenus();
        let d = {};
        try { d = JSON.parse($(this).attr('data-edit') || '{}'); } catch (e) { /* fall through to the error */ }
        if (!d.id) { Swal.fire({ icon:'error', title:'Cannot open', text:'This row is missing its record details. Reload the page and try again.' }); return; }
        openEditCard(d).catch(() => Swal.fire({ icon:'error', title:'Cannot open', text:'The record could not be loaded into the form.' }));
    });

    async function postForm(url, fd) {
        const res  = await fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd });
        const data = await res.json().catch(() => ({}));
        if (!data.success) {
            const first = Object.values(data.errors || {})[0];
            throw new Error((first && first[0]) || data.message || `The server refused it (HTTP ${res.status}).`);
        }
        return data;
    }

    // Save an edit: the record (if this officer may), then the visit (if they
    // may) — or log a first visit when the record had none.
    async function saveEditFromCard() {
        const d = editCtx, insp = d.inspection;
        const customary = d.land_title_type === 'customary';

        const date     = document.getElementById('ar-inspection-date').value;
        const findings = document.getElementById('ar-findings').value.trim();
        const coords   = document.getElementById('ar-coords').value.trim();
        const geometry = document.getElementById('ar-geometry').value;
        const visitEditable = insp ? d.can_edit_visit : true;

        if (visitEditable && (!!date !== !!findings)) {
            Swal.fire({ icon:'warning', title:'Field inspection incomplete', text:'An inspection needs both a date and findings.' });
            return;
        }
        if (d.can_edit_record) {
            if (!document.getElementById('f-owner_name').value.trim() || !document.getElementById('f-existing_use').value) {
                Swal.fire({ icon:'warning', title:'Required', text:'Owner and Prevailing Land Use are required.' });
                return;
            }
            if (customary && !document.getElementById('f-lga').value.trim()) {
                Swal.fire({ icon:'warning', title:'Required', text:'Please select the LGA.' });
                return;
            }
            if (customary && isOtherDistrict(document.getElementById('f-district').value) && !document.getElementById('f-district-other').value.trim()) {
                Swal.fire({ icon:'warning', title:'Required', text:'You chose Other — type the district in the box below it.' });
                return;
            }
        }
        let pin = null;
        if (visitEditable && coords) {
            pin = parseLatLngInput(coords);
            if (!pin) { Swal.fire({ icon:'warning', title:'Check the coordinates', text:'Enter them as latitude, longitude, e.g. 12.0022, 8.5919.' }); return; }
        }

        const btn = document.getElementById('btn-save-record');
        btn.disabled = true; btn.textContent = 'Saving…';
        const done = [];

        try {
            if (d.can_edit_record) {
                const landUse = currentLandUse();
                const fd = new FormData();
                fd.append('owner_name', document.getElementById('f-owner_name').value.trim());
                fd.append('phone', document.getElementById('f-phone').value.trim());
                fd.append('sticker_number', document.getElementById('f-sticker').value.trim());
                fd.append('existing_use', document.getElementById('f-existing_use').value);
                fd.append('land_use_type', landUse);
                fd.append('proposed_use', (document.getElementById('h-proposed_use').value || landUse || document.getElementById('f-existing_use').value).trim());
                if (customary) {
                    fd.append('lga', document.getElementById('f-lga').value.trim());
                    fd.append('district', districtValue());
                    fd.append('location', document.getElementById('h-location').value.trim());
                } else {
                    fd.append('lga', d.lga || '');
                    fd.append('district', d.district || '');
                    fd.append('location', d.location || '');
                }
                Array.from(document.getElementById('f-photos').files || []).forEach(f => fd.append('photos[]', f));
                await postForm(`${RECORD_BASE}/${d.id}/update`, fd);
                done.push('Record updated');
            }

            if (insp && d.can_edit_visit) {
                const fd = new FormData();
                fd.append('inspection_date', date);
                fd.append('findings', findings);
                if (pin) fd.append('coordinates', JSON.stringify(pin));
                if (geometry) fd.append('parcel_geometry', geometry);
                Array.from(document.getElementById('ar-insp-photo-input').files || []).forEach(f => fd.append('photos[]', f));
                await postForm(`${FIELD_BASE}/${insp.id}/update`, fd);
                done.push('Inspection updated');
            } else if (!insp && date && findings) {
                const r = await saveInspectionFor({ id: d.id, file_number: d.file });
                if (r.status === 'failed') throw new Error('The inspection was not saved: ' + r.note);
                done.push('Inspection logged');
            }

            closeSpaModal();
            resetSpaForm();
            $('#field-records-table').DataTable().ajax.reload(null, false);
            Swal.fire({ icon:'success', title: done.length ? 'Saved' : 'Nothing to save', text: done.join(' · ') || 'Nothing on this card could be changed by you.', timer:2200, showConfirmButton:false });
        } catch (err) {
            if (done.length) $('#field-records-table').DataTable().ajax.reload(null, false);
            Swal.fire({ icon:'error', title:'Not saved', text: (done.length ? done.join(' · ') + '. But: ' : '') + err.message });
        }
        btn.disabled = false; btn.textContent = editCtx ? 'Save Changes' : 'Save Record';
    }

    // Form submit → create SPAS application (and, if filled in, its field inspection)
    document.getElementById('form-add-record').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (editCtx) { await saveEditFromCard(); return; }

        const isCustomary = document.getElementById('ltt-customary').checked;
        // No file-number check: it is optional (most owners do not know it), and
        // the record is identified by the SPAS ID the server assigns.

        if (isCustomary && !document.getElementById('f-lga').value.trim()) {
            Swal.fire({ icon:'warning', title:'Required', text:'Please select the LGA — a customary title has no file to take its location from.' });
            return;
        }

        // A half-filled inspection is refused here, before anything is saved,
        // rather than saving the record and quietly dropping the inspection.
        // (A date typed only partly reads as empty to the browser.)
        const hasInspDate     = !!document.getElementById('ar-inspection-date').value;
        const hasInspFindings = !!document.getElementById('ar-findings').value.trim();
        if (hasInspDate !== hasInspFindings) {
            const missing = hasInspDate ? 'ar-findings' : 'ar-inspection-date';
            Swal.fire({
                icon: 'warning',
                title: 'Field inspection incomplete',
                text: hasInspDate
                    ? 'Enter the findings, or clear the inspection date to save the record without an inspection.'
                    : 'Enter a complete inspection date, or clear the findings to save the record without an inspection.',
            }).then(() => document.getElementById(missing).focus());
            return;
        }

        const btn = document.getElementById('btn-save-record');
        btn.disabled = true; btn.textContent = 'Saving…';

        // proposed_use (Approved use) defaults to the applied use, then prevailing — server requires it
        const proposedEl = document.getElementById('h-proposed_use');
        if (!proposedEl.value.trim()) {
            proposedEl.value = (currentLandUse()
                || document.getElementById('f-existing_use').value || '').trim();
        }

        try {
            const fd  = new FormData(this);
            // "Other": record the district the officer typed, not the word "Other".
            if (isCustomary && isOtherDistrict(fd.get('district'))) {
                const typed = document.getElementById('f-district-other').value.trim();
                if (!typed) {
                    Swal.fire({ icon:'warning', title:'Required', text:'You chose Other — type the district in the box below it.' })
                        .then(() => document.getElementById('f-district-other').focus());
                    btn.disabled = false; btn.textContent = 'Save Record';
                    return;
                }
                fd.set('district', typed);
            }
            const res = await fetch(LR_STORE, { method:'POST', headers:{ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: fd });
            const data = await res.json();

            if (res.status === 422) {
                // Surface the actual field error rather than a generic "Save failed"
                const first = Object.values(data.errors || {})[0];
                Swal.fire({ icon:'warning', title:'Check the form', text: (first && first[0]) || data.message || 'Please review the highlighted fields.' });
                btn.disabled = false; btn.textContent = 'Save Record';
                return;
            }

            if (data.success) {
                const insp = await saveInspectionFor({ id: data.id, file_number: data.file_number });
                closeSpaModal();
                resetSpaForm();
                $('#field-records-table').DataTable().ajax.reload(null, false);
                if (insp.status === 'failed') {
                    // Stays on screen until answered: the record is saved, but
                    // the inspection is not, and the surveyor must know that.
                    Swal.fire({
                        icon: 'warning',
                        title: 'Record saved — inspection NOT saved',
                        text: insp.note + ' Log it now with what you entered?',
                        showCancelButton: true,
                        confirmButtonText: 'Log it now',
                        cancelButtonText: 'Later',
                        confirmButtonColor: 'rgb(186,191,12)',
                    }).then(r => { if (r.isConfirmed) window.openLogInspection(insp.entered); });
                } else {
                    Swal.fire({ icon:'success', title:'Added to SPAS', text: (data.spa_id ? `SPAS ID ${data.spa_id}. ` : '') + data.message + insp.note, timer: insp.status === 'none' ? 4500 : 2000, showConfirmButton:false });
                }
            } else {
                Swal.fire({ icon:'error', title:'Error', text:data.message || 'Save failed.' });
            }
        } catch (err) {
            Swal.fire({ icon:'error', title:'Error', text:'An unexpected error occurred.' });
        }

        btn.disabled = false; btn.textContent = 'Save Record';
    });
});
</script>
@endsection
