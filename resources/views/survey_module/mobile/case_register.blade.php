@php
    use App\Support\AddressBuilder;

    /**
     * Survey Mobile — Register Compensation Case.
     *
     * The six steps of the desktop register (case_register.blade.php), one card at a
     * time with a sticky action bar. Everything posts once to MobileCaseController::store,
     * which validates and saves through CaseController exactly as the desktop does. No
     * scheme_type field exists: the scheme is inherited from the project server-side.
     */
    $user = auth()->user();

    $projectMeta = $projects->mapWithKeys(fn ($p) => [$p->id => [
        'name'    => $p->name,
        'code'    => $p->project_code,
        'scheme'  => $p->scheme_type,
        'purpose' => $p->purpose,
        'label'   => $p->scheme_label,
    ]])->all();

    $treePrices = $treeTypes->pluck('default_unit_price', 'name')->all();

    $benRows  = old('beneficiaries', []);
    $treeRows = old('trees', []);

    $scheme = $case->scheme_type ?: null;

    $curDistrict = AddressBuilder::split(old('prop_district', $case->prop_district));
    $curStreet   = AddressBuilder::split(old('prop_street', $case->prop_street));
    $curLgas     = AddressBuilder::split(old('prop_lga', $case->prop_lga));
    $curState    = old('prop_state', $case->prop_state ?: 'Kano');

    $statusLabel = fn ($s) => $s === 'Review' ? 'Pending Review' : $s;
    $statusClass = fn ($s) => match ($s) {
        'Review'    => 'st-review',
        'Active'    => 'st-active',
        'Completed' => 'st-done',
        'Rejected'  => 'st-rejected',
        default     => 'st-draft',
    };

    $purposes = ['Infrastructure Development', 'Housing', 'Commercial', 'Agricultural', 'Government Acquisition', 'Urban Renewal'];
    $steps    = ['General', 'Beneficiaries', 'Farm Info', 'Trees', 'Compensation', 'Preview'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#be185d">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register Compensation Case — Survey Mobile</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('survey_module.mobile._styles')
    <style>:root { --page-w: 880px; }</style>
</head>
<body>

{{-- ============================== APP BAR ============================== --}}
<header class="appbar">
    <div class="appbar-row">
        <div class="appbar-logo">
            <img src="{{ asset('storage/upload/logo/Klase.png') }}" alt="KLAES"
                 onerror="this.replaceWith(Object.assign(document.createElement('i'), {className: 'fas fa-compass'}))">
        </div>
        <div class="appbar-title">
            <h1><span class="long">Register Compensation Case</span><span class="short">New Case</span></h1>
            <p>Survey Mobile · {{ $user->name }}</p>
        </div>
        <a href="{{ route('survey-module.mobile.index') }}" class="icon-btn" aria-label="Dashboard"><i class="fas fa-house"></i></a>
        <button type="button" class="icon-btn" onclick="ui.menu()" aria-label="Account menu"><i class="fas fa-user"></i></button>
    </div>

    <div class="progress">
        <div class="progress-meta"><span id="stepLabel">Step 1 of 6 · General</span><span id="stepPct">17%</span></div>
        <div class="progress-track"><div class="progress-fill" id="progressFill" style="width:16.6%"></div></div>
        <div class="chips" id="chips">
            @foreach ($steps as $i => $label)
                <button type="button" class="chip {{ $i === 0 ? 'active' : '' }}" data-step="{{ $i }}" onclick="reg.go({{ $i }})">
                    <span class="n">{{ $i + 1 }}</span>{{ $label }}
                </button>
            @endforeach
        </div>
    </div>
</header>

@include('survey_module.mobile._menu')

<div class="wrap">
<main class="main">

    {{-- ============================== FLASH ============================== --}}
    @if ($errors->any())
        <div class="flash bad" style="margin-bottom:16px;" role="alert">
            <i class="fas fa-triangle-exclamation"></i>
            <div>
                <strong>The case was not saved. Please fix:</strong>
                <ul>@foreach (array_unique($errors->all()) as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('survey-module.mobile.cases.store') }}" id="caseForm" novalidate>
        @csrf
        <input type="hidden" name="submit_now" id="submitNow" value="0">

        {{-- ============================== STEP 1 — GENERAL ============================== --}}
        <section class="step card card-pad active" data-step="0">
            <div class="step-head">
                <div class="step-icon"><i class="fas fa-folder-open"></i></div>
                <div>
                    <h2>General Information</h2>
                    <p>Pick the project first. Its compensation scheme applies to this case and cannot be changed here.</p>
                </div>
            </div>

            <div class="grid two">
                <div class="field span-2" data-key="survey_project_id">
                    <label for="caseProject">Project <span class="req">*</span></label>
                    <select class="input" name="survey_project_id" id="caseProject" onchange="reg.onProject()">
                        <option value="">— Choose a registered project —</option>
                        @foreach ($projects as $p)
                            <option value="{{ $p->id }}" @selected((string) old('survey_project_id', $case->survey_project_id) === (string) $p->id)>
                                {{ $p->project_code }} · {{ $p->name }} ({{ $p->isMonetary() ? 'Monetary' : 'Land-for-Land' }})
                            </option>
                        @endforeach
                    </select>
                    <div class="err">Select the project this case belongs to.</div>
                    @if ($projects->isEmpty())
                        <div class="hint">No active projects yet. A project must be created in the desktop module first.</div>
                    @endif
                </div>

                <div class="span-2" id="schemeBox" style="display:none;">
                    <div class="note pink" style="align-items:center;flex-wrap:wrap;">
                        <span class="badge" id="schemeBadge"></span>
                        <span id="schemeText" style="flex:1;min-width:200px;"></span>
                    </div>
                </div>

                <div class="field" data-key="purpose">
                    <label for="casePurpose">Purpose</label>
                    <select class="input" name="purpose" id="casePurpose">
                        <option value="">Select purpose…</option>
                        @foreach ($purposes as $p)
                            <option value="{{ $p }}" @selected(old('purpose', $case->purpose) === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" data-key="survey_officer">
                    <label for="caseOfficer">Survey Officer <span class="req">*</span></label>
                    <input class="input" type="text" name="survey_officer" id="caseOfficer" autocomplete="name"
                           value="{{ old('survey_officer', $case->survey_officer) }}" placeholder="Officer's full name">
                    <div class="err">Enter the survey officer's name.</div>
                </div>

                <div class="field" data-key="case_date">
                    <label for="caseDate">Date <span class="req">*</span></label>
                    <input class="input" type="date" name="case_date" id="caseDate"
                           value="{{ old('case_date', optional($case->case_date)->format('Y-m-d')) }}">
                    <div class="err">Enter the date.</div>
                </div>

                <div class="field" data-key="area_ha">
                    <label for="caseArea">Area (Ha) <span class="req">*</span></label>
                    <input class="input" type="number" inputmode="decimal" step="0.01" min="0.01" name="area_ha" id="caseArea"
                           value="{{ old('area_ha', $case->area_ha) }}" placeholder="e.g. 2.40">
                    <div class="err">Enter an area of at least 0.01 Ha.</div>
                </div>

                <div class="field span-2" data-key="description">
                    <label for="caseDescription">Description</label>
                    <textarea class="input" name="description" id="caseDescription" maxlength="4000"
                              placeholder="Brief description of the case…">{{ old('description', $case->description) }}</textarea>
                </div>
            </div>

            {{-- Property location: District, LGA, State. The plot number is its own field. --}}
            <div class="section-title"><i class="fas fa-location-dot"></i> Case property location</div>
            <div class="grid two">
                <div class="field span-2" data-key="prop_district">
                    <label>District <span class="req">*</span></label>
                    <div class="picker" data-name="prop_district[]" data-url="{{ route('survey-module.lookup.districts') }}"
                         data-other="districtOther" data-placeholder="Search district…">
                        <div class="picker-box">
                            @foreach ($curDistrict as $v)
                                <span class="tag" data-v="{{ $v }}"><span>{{ $v === 'Other' ? 'Other (specify)' : $v }}</span><button type="button" aria-label="Remove">×</button><input type="hidden" name="prop_district[]" value="{{ $v }}"></span>
                            @endforeach
                            <input class="picker-search" type="search" autocomplete="off" placeholder="Search district…">
                        </div>
                        <div class="picker-list"></div>
                    </div>
                    <div class="err">Pick at least one district.</div>
                    <div class="hint">Pick more than one if the land straddles a boundary.</div>
                </div>

                <div class="field span-2" id="districtOther" data-key="prop_district_other" @style(['display:none' => ! in_array('Other', $curDistrict, true)])>
                    <label for="propDistrictOther">Specify District <span class="req">*</span></label>
                    <input class="input" type="text" name="prop_district_other" id="propDistrictOther"
                           value="{{ old('prop_district_other', $case->prop_district_other) }}" placeholder="Type the district name">
                    <div class="err">Type the district name.</div>
                </div>

                <div class="field" data-key="prop_lga">
                    <label>LGA <span class="req">*</span></label>
                    <div class="picker" data-name="prop_lga[]" data-options="{{ json_encode(array_values($lgas)) }}" data-placeholder="Select LGA…">
                        <div class="picker-box">
                            @foreach ($curLgas as $v)
                                <span class="tag" data-v="{{ $v }}"><span>{{ $v }}</span><button type="button" aria-label="Remove">×</button><input type="hidden" name="prop_lga[]" value="{{ $v }}"></span>
                            @endforeach
                            <input class="picker-search" type="search" autocomplete="off" placeholder="Select LGA…">
                        </div>
                        <div class="picker-list"></div>
                    </div>
                    <div class="err">Pick the LGA.</div>
                </div>

                <div class="field" data-key="prop_state">
                    <label for="propState">State <span class="req">*</span></label>
                    <select class="input" name="prop_state" id="propState">
                        @foreach ($states as $st)
                            <option value="{{ $st }}" @selected($curState === $st)>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="span-2">
                    <details id="moreAddress" @if(old('prop_street') || old('prop_house') || old('prop_plot')) open @endif>
                        <summary style="cursor:pointer;color:var(--primary-dark);font-weight:600;font-size:14px;padding:4px 0;">
                            Street, house and plot number (optional)
                        </summary>
                        <div class="grid two" style="margin-top:12px;">
                            <div class="field span-2" data-key="prop_street">
                                <label>Street Name</label>
                                <div class="picker" data-name="prop_street[]" data-url="{{ route('survey-module.lookup.streets') }}"
                                     data-other="streetOther" data-placeholder="Search street…">
                                    <div class="picker-box">
                                        @foreach ($curStreet as $v)
                                            <span class="tag" data-v="{{ $v }}"><span>{{ $v === 'Other' ? 'Other (specify)' : $v }}</span><button type="button" aria-label="Remove">×</button><input type="hidden" name="prop_street[]" value="{{ $v }}"></span>
                                        @endforeach
                                        <input class="picker-search" type="search" autocomplete="off" placeholder="Search street…">
                                    </div>
                                    <div class="picker-list"></div>
                                </div>
                            </div>
                            <div class="field span-2" id="streetOther" data-key="prop_street_other" @style(['display:none' => ! in_array('Other', $curStreet, true)])>
                                <label for="propStreetOther">Specify Street <span class="req">*</span></label>
                                <input class="input" type="text" name="prop_street_other" id="propStreetOther"
                                       value="{{ old('prop_street_other', $case->prop_street_other) }}" placeholder="Type the street name">
                                <div class="err">Type the street name.</div>
                            </div>
                            <div class="field" data-key="prop_house">
                                <label for="propHouse">House No.</label>
                                <input class="input" type="text" name="prop_house" id="propHouse" value="{{ old('prop_house', $case->prop_house) }}" placeholder="e.g. 12">
                            </div>
                            <div class="field" data-key="prop_plot">
                                <label for="propPlot">Plot No.</label>
                                <input class="input" type="text" name="prop_plot" id="propPlot" value="{{ old('prop_plot', $case->prop_plot) }}" placeholder="e.g. 4">
                            </div>
                        </div>
                    </details>
                </div>

                <div class="span-2">
                    <div class="note"><i class="fas fa-map"></i><span>Property location: <strong id="locPreview">—</strong></span></div>
                </div>
            </div>
        </section>

        {{-- ============================== STEP 2 — BENEFICIARIES ============================== --}}
        <section class="step card card-pad" data-step="1">
            <div class="step-head">
                <div class="step-icon"><i class="fas fa-users"></i></div>
                <div>
                    <h2>Beneficiaries</h2>
                    <p>Farmers and landowners affected. At least one is needed before the case can be submitted.</p>
                </div>
            </div>

            <button type="button" class="btn btn-outline" onclick="reg.addBen()"><i class="fas fa-user-plus"></i> Add farmer</button>

            <div class="rows two" id="benRows">
                @foreach ($benRows as $i => $row)
                    @include('survey_module.mobile._ben_card', ['i' => $i, 'row' => $row])
                @endforeach
            </div>
            <div class="empty-state" id="benEmpty" @style(['display:none' => count($benRows)])>
                <i class="fas fa-user-group"></i>No farmers added yet. You can save a draft without them.
            </div>
            <p class="hint" style="margin-top:12px;">Bank details and full addresses are captured in the Beneficiaries register on the desktop module.</p>
        </section>

        {{-- ============================== STEP 3 — FARM INFO ============================== --}}
        <section class="step card card-pad" data-step="2">
            <div class="step-head">
                <div class="step-icon"><i class="fas fa-seedling"></i></div>
                <div>
                    <h2>Farm Information</h2>
                    <p>Survey detail for the affected land.</p>
                </div>
            </div>

            <div class="grid two">
                <div class="field span-2" data-key="coordinates">
                    <label for="caseCoordinates">Coordinates</label>
                    <div class="gps-row">
                        <input class="input" type="text" name="coordinates" id="caseCoordinates" maxlength="255"
                               value="{{ old('coordinates', $case->coordinates) }}" placeholder="e.g. 12.0022° N, 8.5920° E">
                        <button type="button" class="btn btn-ghost" id="gpsBtn" onclick="reg.gps()" aria-label="Use my location">
                            <i class="fas fa-location-crosshairs"></i><span class="gps-lbl">Use GPS</span>
                        </button>
                    </div>
                    <div class="hint" id="gpsHint">Stand on the land and tap Use GPS to fill this from the phone.</div>
                </div>
                <div class="field" data-key="gps_reading">
                    <label for="caseGps">GPS Reading</label>
                    <input class="input" type="text" name="gps_reading" id="caseGps" maxlength="255"
                           value="{{ old('gps_reading', $case->gps_reading) }}" placeholder="e.g. N 12°00'07.9&quot;, E 08°35'31.2&quot;">
                </div>
                <div class="field" data-key="num_plots" id="plotsField">
                    <label for="caseNumPlots">Number of Plots <span class="req" id="plotsReq" style="display:none;">*</span></label>
                    <input class="input" type="number" inputmode="numeric" min="0" step="1" name="num_plots" id="caseNumPlots"
                           value="{{ old('num_plots', $case->num_plots) }}" placeholder="e.g. 10">
                    <div class="err">A land-for-land case needs the number of plots.</div>
                    <div class="hint" id="plotsHint">Drives the 50:50 split.</div>
                </div>
                <div class="field span-2" data-key="boundary_file">
                    <label for="caseBoundary">Boundary File reference</label>
                    <input class="input" type="text" name="boundary_file" id="caseBoundary" maxlength="500"
                           value="{{ old('boundary_file', $case->boundary_file) }}" placeholder="Shapefile / KML / GeoJSON reference">
                    <div class="hint">Reference only. The GIS unit handles the file itself.</div>
                </div>
            </div>
        </section>

        {{-- ============================== STEP 4 — TREES ============================== --}}
        <section class="step card card-pad" data-step="3">
            <div class="step-head">
                <div class="step-icon"><i class="fas fa-tree"></i></div>
                <div>
                    <h2>Economic Trees</h2>
                    <p id="treesSub">Trees on the affected land: type × quantity × unit price.</p>
                </div>
            </div>

            <div class="note" id="treesNA" style="display:none;">
                <i class="fas fa-ban"></i>
                <span><strong>Not applicable.</strong> Land-for-Land compensation pays no cash for trees, so nothing is recorded on this step.</span>
            </div>

            <div id="treesActive">
                <button type="button" class="btn btn-outline" onclick="reg.addTree()"><i class="fas fa-plus"></i> Add tree</button>
                <datalist id="treeCatalogue">
                    @foreach ($treeTypes as $t) <option value="{{ $t->name }}"></option> @endforeach
                </datalist>
                <div class="rows two" id="treeRows">
                    @foreach ($treeRows as $i => $row)
                        @include('survey_module.mobile._tree_card', ['i' => $i, 'row' => $row])
                    @endforeach
                </div>
                <div class="empty-state" id="treeEmpty" @style(['display:none' => count($treeRows)])>
                    <i class="fas fa-tree"></i>No trees yet. A monetary case needs at least one.
                </div>
                <div class="note pink" style="margin-top:14px;justify-content:space-between;">
                    <span>Total trees value</span><strong id="treeTotal">₦0.00</strong>
                </div>
            </div>
        </section>

        {{-- ============================== STEP 5 — COMPENSATION ============================== --}}
        <section class="step card card-pad" data-step="4">
            <div class="step-head">
                <div class="step-icon"><i class="fas fa-scale-balanced"></i></div>
                <div>
                    <h2>Compensation Summary</h2>
                    <p>The scheme is exclusive: monetary or land-for-land, never both.</p>
                </div>
            </div>

            <div class="note amber" id="compUnknown"><i class="fas fa-circle-info"></i><span>Choose a project on Step 1 to see its compensation.</span></div>

            <div id="compMonetary" style="display:none;">
                <div class="stat">
                    <div class="lbl"><i class="fas fa-money-bill-wave"></i> Cash compensation</div>
                    <div class="big" id="cashTotal">₦0.00</div>
                    <div class="hint">Tree type × quantity × unit price, paid to the beneficiaries</div>
                    <div class="breakdown" id="cashBreakdown"></div>
                </div>
                <div class="note" style="margin-top:12px;"><i class="fas fa-circle-info"></i><span>No plots are allocated on a monetary case. Totals are recalculated when saved.</span></div>
            </div>

            <div id="compLand" style="display:none;">
                <div class="stat">
                    <div class="lbl"><i class="fas fa-map-location-dot"></i> Land-for-Land (50:50)</div>
                    <div class="big"><span id="landPlots">0</span> plots</div>
                    <div class="hint">Physical land allocation · no cash component</div>
                    <div class="split">
                        <div><b>Farmer</b><small>½ share</small></div>
                        <span style="font-size:24px;color:#d1d5db;">:</span>
                        <div><b>Government</b><small>½ share, odd plot</small></div>
                    </div>
                </div>
                <div class="note" style="margin-top:12px;"><i class="fas fa-circle-info"></i><span>The exact Farmer : Government numbers are calculated by the system when the case is saved.</span></div>
            </div>
        </section>

        {{-- ============================== STEP 6 — PREVIEW ============================== --}}
        <section class="step card card-pad" data-step="5">
            <div class="step-head">
                <div class="step-icon"><i class="fas fa-clipboard-check"></i></div>
                <div>
                    <h2>Preview &amp; Submit</h2>
                    <p>Check the details. Save as a draft, or save and submit for review.</p>
                </div>
            </div>

            <div class="review" id="review"></div>

            <div class="note pink" style="margin-top:16px;">
                <i class="fas fa-route"></i>
                <span>Submitting sets the case to <strong>Pending Review</strong> and routes it Survey → GIS → Commissioner → Deeds → Land/OSS.</span>
            </div>
        </section>
    </form>
</main>
</div>

{{-- ============================== ACTION BAR ============================== --}}
<nav class="actionbar">
    <div class="actionbar-inner">
        <button type="button" class="btn btn-ghost" id="btnBack" onclick="reg.step(-1)" aria-label="Back"><i class="fas fa-arrow-left"></i></button>
        <span class="spacer"></span>
        <button type="button" class="btn btn-ghost" id="btnDraft" onclick="reg.save(0)" style="display:none;"><i class="fas fa-floppy-disk"></i> <span class="long">Save draft</span><span class="short">Draft</span></button>
        <button type="button" class="btn btn-primary" id="btnNext" onclick="reg.step(1)">Next <i class="fas fa-arrow-right"></i></button>
        <button type="button" class="btn btn-success" id="btnSubmit" onclick="reg.save(1)" style="display:none;"><i class="fas fa-paper-plane"></i> <span class="long">Save &amp; Submit</span><span class="short">Submit</span></button>
    </div>
</nav>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

{{-- Prototypes cloned for new rows; values are set through the DOM, never as markup. --}}
<template id="benTpl">@include('survey_module.mobile._ben_card', ['i' => '__I__', 'row' => []])</template>
<template id="treeTpl">@include('survey_module.mobile._tree_card', ['i' => '__I__', 'row' => []])</template>

<script>
(function () {
    'use strict';

    var PROJECTS    = @json($projectMeta);
    var TREE_PRICES = @json($treePrices);
    var STEPS       = @json($steps);
    var ERROR_KEYS  = @json($errors->keys());

    var step = 0;
    var scheme = @json($scheme);
    var benIndex = {{ count($benRows) ? max(array_map('intval', array_keys($benRows))) + 1 : 0 }};
    var treeIndex = {{ count($treeRows) ? max(array_map('intval', array_keys($treeRows))) + 1 : 0 }};
    var dirty = false;

    function $(id) { return document.getElementById(id); }
    function all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
    function money(n) { return '₦' + (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function toast(msg) {
        var t = $('toast'); t.textContent = msg; t.classList.add('show');
        clearTimeout(toast.t); toast.t = setTimeout(function () { t.classList.remove('show'); }, 2600);
    }

    /* ------------------------------ picker ------------------------------ */

    function Picker(el) {
        var box = el.querySelector('.picker-box'), search = el.querySelector('.picker-search'), list = el.querySelector('.picker-list');
        var name = el.dataset.name, url = el.dataset.url, options = el.dataset.options ? JSON.parse(el.dataset.options) : null;
        var otherId = el.dataset.other, timer = null, seq = 0;

        function values() { return all('input[type=hidden]', box).map(function (i) { return i.value; }); }

        function syncOther() {
            if (!otherId) return;
            var on = values().indexOf('Other') !== -1, wrap = $(otherId);
            wrap.style.display = on ? '' : 'none';
            if (!on) { var inp = wrap.querySelector('input'); if (inp) inp.value = ''; }
        }

        function add(value, text) {
            if (values().indexOf(value) !== -1) return;
            var tag = document.createElement('span'); tag.className = 'tag'; tag.dataset.v = value;
            var s = document.createElement('span'); s.textContent = text;
            var b = document.createElement('button'); b.type = 'button'; b.textContent = '×'; b.setAttribute('aria-label', 'Remove');
            var h = document.createElement('input'); h.type = 'hidden'; h.name = name; h.value = value;
            tag.appendChild(s); tag.appendChild(b); tag.appendChild(h);
            box.insertBefore(tag, search);
            changed();
        }

        function changed() {
            syncOther(); dirty = true;
            var f = el.closest('.field'); if (f) f.classList.remove('invalid');
            updateLocation();
        }

        function render(items) {
            list.textContent = '';
            var cur = values();
            if (!items.length) {
                var e = document.createElement('div'); e.className = 'empty'; e.textContent = 'No matches.'; list.appendChild(e); return;
            }
            items.forEach(function (it) {
                var btn = document.createElement('button'); btn.type = 'button';
                var on = cur.indexOf(it.id) !== -1;
                if (on) btn.className = 'on';
                var s = document.createElement('span'); s.textContent = it.text; btn.appendChild(s);
                if (on) { var ic = document.createElement('i'); ic.className = 'fas fa-check'; btn.appendChild(ic); }
                btn.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
                btn.addEventListener('click', function () {
                    if (on) removeValue(it.id); else add(it.id, it.text);
                    search.value = ''; load('');
                    search.focus();
                });
                list.appendChild(btn);
            });
        }

        function removeValue(v) {
            all('.tag', box).forEach(function (t) { if (t.dataset.v === v) t.remove(); });
            changed();
        }

        function load(term) {
            if (options) {
                var t = term.toLowerCase();
                render(options.filter(function (o) { return !t || o.toLowerCase().indexOf(t) !== -1; })
                              .map(function (o) { return { id: o, text: o }; }));
                return;
            }
            var mine = ++seq;
            var e = document.createElement('div'); e.className = 'empty'; e.textContent = 'Searching…';
            list.textContent = ''; list.appendChild(e);
            fetch(url + '?q=' + encodeURIComponent(term) + '&page=1', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
                .then(function (d) { if (mine === seq) render(d.results || []); })
                .catch(function () {
                    if (mine !== seq) return;
                    list.textContent = '';
                    var x = document.createElement('div'); x.className = 'empty'; x.textContent = 'Could not load the list. Check your connection.'; list.appendChild(x);
                });
        }

        box.addEventListener('click', function (ev) {
            var rm = ev.target.closest('.tag button');
            if (rm) { removeValue(rm.parentNode.dataset.v); return; }
            search.focus();
        });
        search.addEventListener('focus', function () { el.classList.add('open'); load(search.value.trim()); });
        search.addEventListener('blur', function () { setTimeout(function () { el.classList.remove('open'); }, 120); });
        search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(search.value.trim()); }, options ? 0 : 250); });
        search.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); var first = list.querySelector('button'); if (first) first.click(); }
            if (ev.key === 'Backspace' && !search.value) { var tags = all('.tag', box); if (tags.length) removeValue(tags[tags.length - 1].dataset.v); }
            if (ev.key === 'Escape') search.blur();
        });

        return { values: values };
    }

    var pickers = {};
    all('.picker').forEach(function (el) { pickers[el.dataset.name] = Picker(el); });

    function pickerText(name) {
        var p = pickers[name]; if (!p) return '';
        return p.values().map(function (v) { return v; });
    }

    // "District, LGA, State" — the same composition AddressBuilder::propertyLocation() makes.
    function resolve(vals, other) {
        var out = vals.filter(function (v) { return v !== 'Other'; });
        if (vals.indexOf('Other') !== -1 && other) out.push(other);
        return out.join(' & ');
    }
    function locationText() {
        var parts = [
            resolve(pickerText('prop_district[]'), ($('propDistrictOther').value || '').trim()),
            resolve(pickerText('prop_lga[]'), ''),
            $('propState').value
        ].filter(Boolean);
        return parts.join(', ');
    }
    function updateLocation() { $('locPreview').textContent = locationText() || '—'; }

    /* ------------------------------ scheme ------------------------------ */

    function applyScheme(next) {
        scheme = next || null;
        var mon = scheme === 'monetary', land = scheme === 'land';

        $('schemeBox').style.display = scheme ? '' : 'none';
        var badge = $('schemeBadge');
        badge.className = 'badge ' + (mon ? 'monetary' : 'land');
        badge.textContent = mon ? 'Monetary (Cash for Trees)' : 'Land-for-Land (50:50)';
        $('schemeText').textContent = mon
            ? 'Record economic trees and the cash they are worth. No plots are allocated.'
            : 'Plots are split 50:50 between Farmer and Government. No cash is paid for trees.';

        $('treesNA').style.display = land ? '' : 'none';
        $('treesActive').style.display = land ? 'none' : '';
        $('treesSub').textContent = land
            ? 'Not applicable: this case is compensated with plots, not cash.'
            : 'Required for a monetary case: type × quantity × unit price.';

        var plots = $('caseNumPlots');
        plots.disabled = mon;
        $('plotsReq').style.display = land ? '' : 'none';
        $('plotsHint').textContent = mon ? 'Not used: a monetary case allocates no plots.' : 'Drives the 50:50 split.';

        $('compUnknown').style.display = scheme ? 'none' : '';
        $('compMonetary').style.display = mon ? '' : 'none';
        $('compLand').style.display = land ? '' : 'none';
    }

    function onProject() {
        var meta = PROJECTS[$('caseProject').value];
        var purpose = $('casePurpose');
        if (meta && !purpose.value && meta.purpose) purpose.value = meta.purpose;
        applyScheme(meta ? meta.scheme : null);
        $('caseProject').closest('.field').classList.remove('invalid');
    }

    /* ------------------------------- rows ------------------------------- */

    function cloneRow(tplId, containerId, index) {
        var frag = $(tplId).content.cloneNode(true);
        var card = frag.querySelector('.row-card');
        all('[name]', card).forEach(function (i) { i.name = i.name.replace('__I__', index); });
        $(containerId).appendChild(frag);
        return $(containerId).lastElementChild;
    }

    function renumber() {
        all('#benRows .row-card').forEach(function (c, i) { c.querySelector('.num').textContent = i + 1; });
        all('#treeRows .row-card').forEach(function (c, i) { c.querySelector('.num').textContent = i + 1; });
        $('benEmpty').style.display = all('#benRows .row-card').length ? 'none' : '';
        $('treeEmpty').style.display = all('#treeRows .row-card').length ? 'none' : '';
    }

    function addBen() {
        var card = cloneRow('benTpl', 'benRows', benIndex++);
        renumber(); dirty = true;
        var first = card.querySelector('input[type=text]');
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { first.focus(); }, 250);
    }

    function addTree() {
        var card = cloneRow('treeTpl', 'treeRows', treeIndex++);
        renumber(); dirty = true;
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { card.querySelector('input').focus(); }, 250);
    }

    function lineTotal(card) {
        var q = Number(card.querySelector('[name$="[quantity]"]').value) || 0;
        var p = Number(card.querySelector('[name$="[unit_price]"]').value) || 0;
        return q * p;
    }

    function refreshTotals() {
        var total = 0, bd = $('cashBreakdown');
        bd.textContent = '';
        all('#treeRows .row-card').forEach(function (card) {
            var t = lineTotal(card); total += t;
            card.querySelector('[data-role=line]').textContent = money(t);
            var line = document.createElement('div');
            var l = document.createElement('span');
            l.textContent = (card.querySelector('[name$="[tree_type]"]').value || 'Tree') + ' × ' + (card.querySelector('[name$="[quantity]"]').value || 0);
            var r = document.createElement('span'); r.textContent = money(t);
            line.appendChild(l); line.appendChild(r); bd.appendChild(line);
        });
        $('treeTotal').textContent = money(total);
        $('cashTotal').textContent = money(total);
        $('landPlots').textContent = (Number($('caseNumPlots').value) || 0).toLocaleString();
        return total;
    }

    document.addEventListener('click', function (ev) {
        var del = ev.target.closest('.row-card .del');
        if (!del) return;
        var card = del.closest('.row-card');
        var label = card.closest('#benRows') ? 'this farmer' : 'this tree line';
        if (!confirm('Remove ' + label + ' from the case?')) return;
        card.remove(); renumber(); refreshTotals(); dirty = true;
    });

    document.addEventListener('input', function (ev) {
        var t = ev.target;
        if (!t.closest || !t.closest('#caseForm')) return;
        dirty = true;
        var f = t.closest('.field'); if (f) f.classList.remove('invalid');
        if (t.closest('#treeRows')) refreshTotals();
        if (t.id === 'propDistrictOther') updateLocation();
        if (t.id === 'caseNumPlots') refreshTotals();
    });
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (t.id === 'propState') updateLocation();
        // Fill the catalogue price when a known tree is chosen and no price is typed yet.
        if (t.name && /\[tree_type\]$/.test(t.name)) {
            var price = t.closest('.row-card').querySelector('[name$="[unit_price]"]');
            var known = TREE_PRICES[(t.value || '').trim()];
            if (price && !price.value && known != null) { price.value = known; refreshTotals(); }
        }
    });

    /* ----------------------------- validation ---------------------------- */

    function mark(el, bad) { var f = el.closest('.field') || el; f.classList.toggle('invalid', bad); return !bad; }

    // Light checks so a field officer is told on the spot; the server re-validates everything.
    function validStep(n) {
        var ok = true, check = function (cond) { ok = cond && ok; };
        if (n === 0) {
            check(mark($('caseProject'), !$('caseProject').value));
            check(mark($('caseOfficer'), !$('caseOfficer').value.trim()));
            check(mark($('caseDate'), !$('caseDate').value));
            check(mark($('caseArea'), !(Number($('caseArea').value) >= 0.01)));
            var d = pickerText('prop_district[]');
            check(mark(document.querySelector('[data-key=prop_district]'), !d.length));
            if (d.indexOf('Other') !== -1) check(mark($('propDistrictOther'), !$('propDistrictOther').value.trim()));
            check(mark(document.querySelector('[data-key=prop_lga]'), !pickerText('prop_lga[]').length));
            if (pickerText('prop_street[]').indexOf('Other') !== -1) {
                var so = !$('propStreetOther').value.trim();
                if (so) $('moreAddress').open = true;
                check(mark($('propStreetOther'), so));
            }
        }
        if (n === 1) {
            all('#benRows .row-card').forEach(function (c) {
                check(mark(c.querySelector('[name$="[full_name]"]'), !c.querySelector('[name$="[full_name]"]').value.trim()));
            });
        }
        if (n === 2 && scheme === 'land') {
            check(mark($('caseNumPlots'), !(Number($('caseNumPlots').value) >= 1)));
        }
        if (n === 3 && scheme !== 'land') {
            var cards = all('#treeRows .row-card');
            if (scheme === 'monetary' && !cards.length) { ok = false; toast('A monetary case needs at least one tree.'); }
            cards.forEach(function (c) {
                check(mark(c.querySelector('[name$="[tree_type]"]'), !c.querySelector('[name$="[tree_type]"]').value.trim()));
                check(mark(c.querySelector('[name$="[quantity]"]'), !(Number(c.querySelector('[name$="[quantity]"]').value) >= 1)));
                check(mark(c.querySelector('[name$="[unit_price]"]'), c.querySelector('[name$="[unit_price]"]').value === ''));
            });
        }
        all('.chip')[n].classList.toggle('bad', !ok);
        return ok;
    }

    function focusFirstInvalid() {
        var bad = document.querySelector('.step.active .field.invalid');
        if (!bad) return;
        bad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var inp = bad.querySelector('input:not([type=hidden]), select, textarea');
        if (inp) setTimeout(function () { inp.focus({ preventScroll: true }); }, 300);
    }

    /* ------------------------------ stepper ------------------------------ */

    function render() {
        all('.step').forEach(function (s, i) { s.classList.toggle('active', i === step); });
        all('.chip').forEach(function (c, i) {
            c.classList.toggle('active', i === step);
            c.classList.toggle('done', i < step && !c.classList.contains('bad'));
        });
        var pct = Math.round((step + 1) / STEPS.length * 100);
        $('progressFill').style.width = pct + '%';
        $('stepPct').textContent = pct + '%';
        $('stepLabel').textContent = 'Step ' + (step + 1) + ' of ' + STEPS.length + ' · ' + STEPS[step];
        all('.chip')[step].scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' });

        var last = step === STEPS.length - 1;
        $('btnBack').style.visibility = step === 0 ? 'hidden' : 'visible';
        $('btnNext').style.display = last ? 'none' : '';
        $('btnDraft').style.display = last ? '' : 'none';
        $('btnSubmit').style.display = last ? '' : 'none';

        if (step === 3 || step === 4) refreshTotals();
        if (step === 5) fillReview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function go(n) {
        if (n < 0 || n >= STEPS.length || n === step) return;
        // Moving forward checks the steps being left behind; going back never blocks.
        if (n > step) {
            for (var i = step; i < n; i++) {
                if (!validStep(i)) { step = i; render(); setTimeout(focusFirstInvalid, 350); return; }
            }
        }
        step = n; render();
    }

    /* ------------------------------ review ------------------------------ */

    function fillReview() {
        var meta = PROJECTS[$('caseProject').value];
        var bens = all('#benRows .row-card').length;
        var trees = all('#treeRows .row-card');
        var rows = [
            ['Project', meta ? meta.code + ' · ' + meta.name : '—', 0, true],
            ['Scheme', meta ? meta.label : '—', 0],
            ['Purpose', $('casePurpose').value || '—', 0],
            ['Survey officer', $('caseOfficer').value || '—', 0],
            ['Date', $('caseDate').value || '—', 0],
            ['Area', ($('caseArea').value || '0') + ' Ha', 0],
            ['Location', locationText() || '—', 0, true],
            ['Beneficiaries', bens + ' farmer(s)', 1],
            ['Coordinates', $('caseCoordinates').value || '—', 2]
        ];
        if (scheme === 'land') {
            rows.push(['Land compensation', (Number($('caseNumPlots').value) || 0) + ' plots · 50:50 split on save', 2, true]);
        } else {
            var qty = trees.reduce(function (s, c) { return s + (Number(c.querySelector('[name$="[quantity]"]').value) || 0); }, 0);
            rows.push(['Economic trees', trees.length + ' line(s), ' + qty + ' tree(s)', 3]);
            rows.push(['Cash compensation', money(refreshTotals()), 3]);
        }
        rows.push(['Description', $('caseDescription').value || '—', 0, true]);

        var box = $('review'); box.textContent = '';
        rows.forEach(function (r) {
            var d = document.createElement('div'); if (r[3]) d.className = 'wide';
            var k = document.createElement('span'); k.textContent = r[0] + ' ';
            var e = document.createElement('button'); e.type = 'button'; e.className = 'edit'; e.textContent = 'Edit';
            e.addEventListener('click', function () { step = r[2]; render(); });
            k.appendChild(e);
            var v = document.createElement('span'); v.textContent = r[1];
            d.appendChild(k); d.appendChild(v); box.appendChild(d);
        });

        if (!bens) toast('Add at least one farmer before submitting. A draft can still be saved.');
    }

    /* ------------------------------- save ------------------------------- */

    function save(submitNow) {
        for (var i = 0; i < STEPS.length - 1; i++) {
            if (!validStep(i)) { step = i; render(); setTimeout(focusFirstInvalid, 350); toast('Please complete the highlighted fields.'); return; }
        }
        if (submitNow && !all('#benRows .row-card').length) {
            step = 1; render(); toast('A case needs at least one farmer before it can be submitted.'); return;
        }
        if (submitNow && !confirm('Save and submit this case for review?')) return;

        $('submitNow').value = submitNow ? '1' : '0';
        ['btnDraft', 'btnSubmit', 'btnBack'].forEach(function (id) { $(id).disabled = true; });
        var b = submitNow ? $('btnSubmit') : $('btnDraft');
        b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
        dirty = false;
        $('caseForm').submit();
    }

    /* -------------------------------- GPS -------------------------------- */

    function dms(v, pos, neg) {
        var a = Math.abs(v), d = Math.floor(a), mf = (a - d) * 60, m = Math.floor(mf), s = ((mf - m) * 60).toFixed(1);
        return (v >= 0 ? pos : neg) + ' ' + String(d).padStart(2, '0') + '°' + String(m).padStart(2, '0') + "'" + String(s).padStart(4, '0') + '"';
    }

    function gps() {
        var hint = $('gpsHint'), btn = $('gpsBtn');
        if (!navigator.geolocation) { hint.textContent = 'This device or browser cannot read GPS.'; return; }
        btn.disabled = true; hint.textContent = 'Reading location…';
        navigator.geolocation.getCurrentPosition(function (p) {
            var lat = p.coords.latitude, lng = p.coords.longitude;
            $('caseCoordinates').value = Math.abs(lat).toFixed(5) + '° ' + (lat >= 0 ? 'N' : 'S') + ', ' + Math.abs(lng).toFixed(5) + '° ' + (lng >= 0 ? 'E' : 'W');
            $('caseGps').value = dms(lat, 'N', 'S') + ', ' + dms(lng, 'E', 'W');
            hint.textContent = 'Filled from GPS · accuracy about ' + Math.round(p.coords.accuracy) + ' m.';
            btn.disabled = false; dirty = true;
        }, function (err) {
            hint.textContent = err.code === 1
                ? 'Location permission was refused. Allow it in the browser settings, or type the coordinates.'
                : 'Could not get a GPS fix. Move into the open and try again, or type the coordinates.';
            btn.disabled = false;
        }, { enableHighAccuracy: true, timeout: 20000, maximumAge: 0 });
    }

    /* -------------------------------- boot -------------------------------- */

    window.reg = {
        go: go, step: function (d) { go(step + d); }, onProject: onProject,
        addBen: addBen, addTree: addTree, save: save, gps: gps
    };
    window.addEventListener('beforeunload', function (ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });

    applyScheme(scheme || (PROJECTS[$('caseProject').value] || {}).scheme);
    renumber(); refreshTotals(); updateLocation();

    // A bounced submission: open the first step that holds an error.
    if (ERROR_KEYS.length) {
        var stepOf = function (k) {
            if (/^beneficiaries/.test(k)) return 1;
            if (/^(coordinates|gps_reading|num_plots|boundary_file)$/.test(k)) return 2;
            if (/^trees/.test(k)) return 3;
            return 0;
        };
        var first = Math.min.apply(null, ERROR_KEYS.map(stepOf));
        ERROR_KEYS.forEach(function (k) {
            var f = document.querySelector('[data-key="' + k.split('.')[0] + '"]');
            if (f) f.classList.add('invalid');
            all('.chip')[stepOf(k)].classList.add('bad');
        });
        step = first;
    }
    render();
})();
</script>
</body>
</html>
