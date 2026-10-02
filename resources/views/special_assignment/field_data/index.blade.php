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
                    <table id="field-records-table" class="w-full text-sm" style="width:100%">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">File No</th>
                                <th class="px-3 py-2">Owner</th>
                                <th class="px-3 py-2">Location</th>
                                <th class="px-3 py-2">Approved Use</th>
                                <th class="px-3 py-2">Prevailing Use</th>
                                <th class="px-3 py-2">Contravention</th>
                                <th class="px-3 py-2">Inspection</th>
                                <th class="px-3 py-2">Findings</th>
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
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Field Inspection Details</h3>
            <button id="btn-close-view" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-gray-500 mb-0.5">File Number</p>
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
            <div id="vf-photos-wrap" class="hidden">
                <p class="text-xs text-gray-500 mb-2">Photos</p>
                <div id="vf-photos" class="flex flex-wrap gap-2"></div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button id="btn-close-view2" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
        </div>
    </div>
</div>

{{-- Add Record (Add to SPAS) Modal — land record details + optional field inspection in one pass --}}
<div id="modal-add-record" class="fixed inset-0 z-[1100] hidden items-center justify-center bg-black/40 p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl mx-auto overflow-hidden max-h-[92vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-3 border-b border-gray-100 flex-shrink-0">
            <h3 class="text-base font-semibold text-gray-800">Add Land Record</h3>
            <button id="btn-close-modal" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-add-record" enctype="multipart/form-data" class="p-6 flex-1 overflow-y-auto min-h-0">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                {{-- Left: land record details --}}
                <div class="lg:col-span-2 space-y-4">

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

                    {{-- File Number picker --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">File Number <span class="text-red-500">*</span></label>
                        <div class="flex gap-2">
                            <input type="text" id="display-file-number" readonly placeholder="No file number selected"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 cursor-default outline-none">
                            <button type="button" id="btn-pick-fileno"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg transition-opacity disabled:opacity-50 disabled:cursor-not-allowed">
                                <i data-lucide="search" class="h-4 w-4"></i> Select
                            </button>
                        </div>
                        <p id="lookup-msg" class="text-xs mt-1 hidden"></p>
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
                                 1,818 of them, far too many to render inline. --}}
                            <select name="district" id="f-district" disabled
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                                <option value="">Loading districts…</option>
                            </select>
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

<style>
    @keyframes contraventionPulse {
        0%, 100% { background-color:#fee2e2; border-color:#fca5a5; transform:scale(1);   box-shadow:none; }
        50%       { background-color:#fecaca; border-color:#ef4444; transform:scale(1.04); box-shadow:0 0 10px rgba(239,68,68,.35); }
    }
</style>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
{{-- Leaflet.draw — lets the surveyor trace the plot boundary on the inspection map --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
{{-- Geocoding for the inspection map pin. Leaflet is already loaded just above,
     so pull in the google.maps shim only (same stack as File Indexing). --}}
@include('partials.maps_scripts', ['skipLeaflet' => true])
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>

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

    return { init, geocode, reset: () => init('') };
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
            { data: 'file_number', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
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
            { data: 'findings', render: d => d||'—' },
            { data: 'action', orderable:false, searchable:false },
        ],
    });

    // ── View field inspection ─────────────────────────────────────────────
    const viewModal = document.getElementById('modal-view-field');
    function closeViewModal() { viewModal.classList.add('hidden'); viewModal.classList.remove('flex'); }
    document.getElementById('btn-close-view').addEventListener('click', closeViewModal);
    document.getElementById('btn-close-view2').addEventListener('click', closeViewModal);

    $(document).on('click', '.btn-view-field', function () {
        $('.action-dropdown').addClass('hidden');
        const info = JSON.parse($(this).attr('data-info') || '{}');
        document.getElementById('vf-file').textContent      = info.file      || '—';
        document.getElementById('vf-date').textContent      = info.date      || '—';
        document.getElementById('vf-inspector').textContent = info.inspector || '—';
        document.getElementById('vf-findings').textContent  = info.findings  || '—';

        const photos = info.photos || [];
        const photosDiv  = document.getElementById('vf-photos');
        const photosWrap = document.getElementById('vf-photos-wrap');
        photosDiv.innerHTML = '';
        if (photos.length) {
            photos.forEach(url => {
                photosDiv.innerHTML += `<a href="${url}" target="_blank"><img src="${url}" class="h-20 w-20 object-cover rounded border border-gray-200"></a>`;
            });
            photosWrap.classList.remove('hidden');
        } else {
            photosWrap.classList.add('hidden');
        }

        viewModal.classList.remove('hidden');
        viewModal.classList.add('flex');
        lucide.createIcons();
    });

    // ── Dropdown action menu ───────────────────────────────────────────────
    $(document).on('click', '.btn-action-toggle', function(e) {
        e.stopPropagation();
        const $dd = $(this).siblings('.action-dropdown');
        $('.action-dropdown').not($dd).addClass('hidden');
        $dd.toggleClass('hidden');
    });
    $(document).on('click', function() { $('.action-dropdown').addClass('hidden'); });

    // ── Delete field record ────────────────────────────────────────────────
    $(document).on('click', '.btn-delete-field', function() {
        $('.action-dropdown').addClass('hidden');
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

    function openSpaModal() {
        spaModal.classList.remove('hidden');
        spaModal.classList.add('flex');
        arMapCtrl.init(document.getElementById('h-location').value || '');
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }
    function closeSpaModal() {
        spaModal.classList.add('hidden');
        spaModal.classList.remove('flex');
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
    function loadDistricts() {
        if (districtsPromise) return districtsPromise;
        const sel = document.getElementById('f-district');
        districtsPromise = fetch('{{ route("api.reference.districts") }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                const list = (d && d.data) || [];
                sel.innerHTML = '<option value="">Select District…</option>'
                    + list.map(x => `<option value="${x.name}">${x.name}</option>`).join('');
            })
            .catch(() => {
                sel.innerHTML = '<option value="">Districts unavailable — pick the pin on the map</option>';
                districtsPromise = null; // allow a retry on the next switch
            });
        return districtsPromise;
    }

    // LGA / District drive both the stored location and the map pin.
    function applyCustomaryLocation() {
        const lga      = document.getElementById('f-lga').value.trim();
        const district = document.getElementById('f-district').value.trim();
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
    function setLandTitleType(type) {
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

            fetch(NEXT_CUSTOMARY_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(d => {
                    fileInput.value = d.file_number;
                    document.getElementById('h-file_number').value = d.file_number;
                })
                .catch(() => {
                    fileInput.placeholder = 'Could not generate — try again';
                });
        } else {
            selectBtn.disabled = false;
            fileInput.placeholder = 'No file number selected';
            ownerInput.readOnly = true;
            ownerInput.classList.add('bg-gray-50', 'text-gray-700', 'cursor-default');
            ownerInput.placeholder = 'Auto-filled from file number';
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
        setLandTitleType('statutory');
        // Note: the Field Inspection inputs (ar-*) live inside this <form>, so the
        // native reset() call above already clears them; the map pin/boundary are
        // reset separately by arMapCtrl.init() when the modal (re)opens.
    }

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
    async function saveInspectionFor(app) {
        const inspDate     = document.getElementById('ar-inspection-date').value;
        const inspFindings = document.getElementById('ar-findings').value.trim();
        const inspCoords   = document.getElementById('ar-coords').value.trim();
        const inspGeometry = document.getElementById('ar-geometry').value;

        if (!inspDate && !inspFindings && !inspCoords) return ''; // nothing entered — skip silently
        if (!inspDate || !inspFindings) {
            return ' Field inspection was not logged — an inspection date and findings are both required; you can add them later from Field Data.';
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
            const res  = await fetch(STORE, { method:'POST', headers:{ 'X-CSRF-TOKEN': CSRF }, body: fd });
            const data = await res.json();
            if (data.success) {
                if (data.mapPoint) window.plotFieldMapPoint(data.mapPoint);
                return ' Field inspection logged.' + coordsWarning;
            }
            return ' Field inspection could not be saved (' + (data.message || 'save failed') + ') — you can log it later from Field Data.';
        } catch (err) {
            return ' Field inspection could not be saved due to a network error — you can log it later from Field Data.';
        }
    }

    // Form submit → create SPAS application (and, if filled in, its field inspection)
    document.getElementById('form-add-record').addEventListener('submit', async function (e) {
        e.preventDefault();

        const isCustomary = document.getElementById('ltt-customary').checked;
        if (!document.getElementById('h-file_number').value.trim()) {
            Swal.fire({
                icon: 'warning',
                title: 'Required',
                text: isCustomary
                    ? 'The temporary file number is still generating — please wait a moment and try again.'
                    : 'Please select a file number first.',
            });
            return;
        }

        if (isCustomary && !document.getElementById('f-lga').value.trim()) {
            Swal.fire({ icon:'warning', title:'Required', text:'Please select the LGA — a customary title has no file to take its location from.' });
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
                const inspectionNote = await saveInspectionFor({ id: data.id, file_number: data.file_number });
                closeSpaModal();
                resetSpaForm();
                $('#field-records-table').DataTable().ajax.reload(null, false);
                Swal.fire({ icon:'success', title:'Added to SPAS', text: data.message + inspectionNote, timer: inspectionNote ? 3500 : 2000, showConfirmButton:false });
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
