@extends('survey_module.layouts.klaes')

@section('page-title', 'Public Layout Plans — KLAES')

@php
  use App\Models\Survey\SurveyTpNumber as TP;
  use App\Http\Controllers\Survey\TpNumberController as TPC;

  $PageTitle = 'Public Layout Plans';
  $PageDescription = 'The Ministry register of layout drawings (KANO LAYOUTS), in drawing-number order, and TP numbers added here.';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $e = $editing;
  $v = fn ($k, $d = null) => old($k, $e ? $e->{$k} : $d);
  $open = $errors->any() || $e;
  $lgas = \App\Http\Controllers\Survey\LookupController::options('lgas');
  $filtered = request()->hasAny(['q', 'prefix', 'lga', 'check']);
  // Prefix (TP) + Sub Prefix (MB) + Serial No. (1) = TP No. TP/MB/1 (user, 2026-11-03).
  [$heads, $subs] = $prefixParts;
  [$curPrefix, $curSub] = $e ? TP::splitPrefix($e->prefix) : ['TP', ''];
  $prefixIsListed = in_array($curPrefix, $heads, true);
  $pickedPrefix = old('prefix', $prefixIsListed ? $curPrefix : TP::OTHER);
  $subLabel = fn ($p) => ([$h, $sb] = TP::splitPrefix($p)) && $sb !== '' ? ($h === 'TP' ? $sb : $sb . ' (' . $h . ')') : $h;
  $pickedLandUse = $v('land_use');
  $curPurpose = $e?->purpose;
  $pickedPurpose = old('purpose', $curPurpose);
@endphp

@section('survey-content')
@include('survey_module.partials._flash')
@include('survey_module.partials._table_ui')

{{-- From the KANO LAYOUTS register (user, 2026-11-03); the general TP lookup table is no longer used. --}}
<div class="page-header">
    <div>@include('survey_module.partials._records_export', ['endpoint' => route('survey-module.records.tp-numbers.export'), 'title' => 'Public Layout Plans'])</div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('tpForm').classList.add('open')"><i class="fas fa-plus"></i> Add TP No.</button>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">TP Nos</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Sub Prefixes</div><div class="kpi-value">{{ number_format($stats['prefixes']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">With a compensation project</div><div class="kpi-value">{{ number_format($stats['linked']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Notes to check</div><div class="kpi-value" style="color:#b45309;">{{ number_format($stats['check']) }}</div></div>
</div>

{{-- Add / edit, as a modal (Survey entry forms are modals). --}}
<form method="POST" id="tpForm"
      action="{{ $e ? route('survey-module.records.tp-numbers.update', $e) : route('survey-module.records.tp-numbers.store') }}"
      class="farmer-entry-form {{ $open ? 'open' : '' }}" data-sv-modal="{{ $e ? 'Edit ' . $e->tp_no : 'Add TP No.' }}">
    @csrf
    @if ($e) @method('PUT') @endif
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-hashtag"></i><span>TP No.</span></div>
        <div class="form-group">
            <label>Prefix <span class="required">*</span></label>
            <select name="prefix" id="tpPrefix">
                @foreach ($heads as $p)<option value="{{ $p }}" @selected($pickedPrefix === $p)>{{ $p }}</option>@endforeach
                <option value="{{ TP::OTHER }}" @selected($pickedPrefix === TP::OTHER)>Other</option>
            </select>
        </div>
        <div class="form-group" id="tpPrefixOtherBox" @unless ($pickedPrefix === TP::OTHER) style="display:none;" @endunless>
            <label>Specify Prefix <span class="required">*</span></label>
            <input type="text" name="prefix_other" id="tpPrefixOther" maxlength="80" placeholder="e.g. BLM"
                   value="{{ old('prefix_other', $prefixIsListed ? '' : $curPrefix) }}" @disabled($pickedPrefix !== TP::OTHER) />
        </div>
        <div class="form-group">
            <label>Sub Prefix</label>
            <input type="text" name="sub_prefix" id="tpSubPrefix" list="tpSubPrefixes" maxlength="80" placeholder="e.g. MB" value="{{ old('sub_prefix', $curSub) }}" />
            <datalist id="tpSubPrefixes">@foreach ($subs as $sb)<option value="{{ $sb }}">@endforeach</datalist>
            <p class="helper-text">Pick one or type it, e.g. MB, UDB, MLPP/KBT.</p>
        </div>
        <div class="form-group">
            <label>Serial No. <span class="required">*</span></label>
            <input type="text" name="serial_no" id="tpSerial" value="{{ $v('serial_no') }}" maxlength="40" placeholder="e.g. 338A" required />
        </div>
        <div class="form-group">
            <label>TP No.</label>
            <input type="text" id="tpPreview" readonly style="background:var(--gray-100);font-weight:700;" value="{{ $e?->tp_no }}" />
            <p class="helper-text">Prefix / Sub Prefix / Serial No., as it will be saved.</p>
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-map"></i><span>Layout</span></div>
        <div class="form-group span-2">
            <label>Layout Name (Title)</label>
            <input type="text" name="layout_name" value="{{ $v('layout_name') }}" maxlength="255" placeholder="e.g. New extension to Gwammaja settlement" />
        </div>
        <div class="form-group">
            <label>Date (as written)</label>
            <input type="text" name="drawing_date" value="{{ $v('drawing_date') }}" maxlength="40" placeholder="e.g. 23/04/70 or JUN-84" />
            <p class="helper-text">The drawing's date as in the register.</p>
        </div>
        <div class="form-group">
            <label>Land Use</label>
            <select name="land_use" id="tpLandUse">
                <option value="">Select land use…</option>
                @foreach (TP::LAND_USES as $u)<option value="{{ $u }}" @selected($pickedLandUse === $u)>{{ $u }}</option>@endforeach
                <option value="{{ TP::OTHER }}" @selected($pickedLandUse === TP::OTHER)>Other</option>
            </select>
        </div>
        <div class="form-group" id="tpLandUseOtherBox" @unless ($pickedLandUse === TP::OTHER) style="display:none;" @endunless>
            <label>Specify Land Use <span class="required">*</span></label>
            <input type="text" name="land_use_other" id="tpLandUseOther" maxlength="100" value="{{ $v('land_use_other') }}" @disabled($pickedLandUse !== TP::OTHER) />
        </div>
        <div class="form-group">
            <label>Purpose</label>
            {{-- The purposes already on the register; "Other" adds a new one, offered from then on. --}}
            <select name="purpose" id="tpPurpose">
                <option value="">Select purpose…</option>
                @foreach ($purposes as $pp)<option value="{{ $pp }}" @selected($pickedPurpose === $pp)>{{ $pp }}</option>@endforeach
                <option value="{{ TP::OTHER }}" @selected($pickedPurpose === TP::OTHER)>Other</option>
            </select>
        </div>
        <div class="form-group" id="tpPurposeOtherBox" @unless ($pickedPurpose === TP::OTHER) style="display:none;" @endunless>
            <label>Specify Purpose <span class="required">*</span></label>
            <input type="text" name="purpose_other" id="tpPurposeOther" maxlength="150" placeholder="e.g. Residential Layout"
                   value="{{ old('purpose_other') }}" @disabled($pickedPurpose !== TP::OTHER) />
        </div>
        <div class="form-group">
            <label>No. of Plots</label>
            <input type="number" name="plot_count" min="1" value="{{ $v('plot_count') }}" />
        </div>
        <div class="form-group">
            <label>Area (ha)</label>
            <input type="number" name="area_ha" min="0" step="0.0001" value="{{ $v('area_ha') !== null ? (float) $v('area_ha') : '' }}" />
        </div>
        <div class="form-group">
            <label>Approval Date</label>
            <input type="date" name="approval_date" max="{{ now()->toDateString() }}" value="{{ old('approval_date', $e?->approval_date?->format('Y-m-d')) }}" />
        </div>
    </div>

    @include('survey_module.partials._address_builder', ['prefix' => 'prop_', 'mode' => 'property', 'model' => $e, 'legend' => 'Layout Location', 'fields' => ['district', 'lga', 'state']])

    <div class="form-grid">
        <div class="form-group">
            <label>Scanned / Remarks</label>
            <textarea name="remarks" rows="2" maxlength="2000">{{ $v('remarks') }}</textarea>
        </div>
        <div class="form-group">
            <label>Notes (check)</label>
            <textarea name="check_note" rows="2" maxlength="500" placeholder="e.g. Number used 2 times; date looks wrong">{{ $v('check_note') }}</textarea>
            <p class="helper-text">Clear it once checked against the original drawing.</p>
        </div>
    </div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.records.tp-numbers') }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Add TP No.' }}</button></div>
    </div>
</form>

<div class="table-container" x-data>
    <div class="table-header">
        <h3>Layout Drawings <span style="font-weight:400;font-size:13px;color:var(--gray-500);">· in drawing-number order</span></h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Drawing no., title, date, remarks…" style="{{ $field }}width:230px;">
            <select name="prefix" style="{{ $field }}"><option value="">All sub prefixes</option>@foreach (collect($prefixes)->sortBy(fn ($p) => mb_strtoupper($subLabel($p)), SORT_NATURAL) as $p)<option value="{{ $p }}" @selected(request('prefix') === $p)>{{ $subLabel($p) }}</option>@endforeach</select>
            <select name="lga" class="sv-searchable" data-width="180px" style="{{ $field }}"><option value="">All LGAs</option>@foreach ($lgas as $l)<option value="{{ $l }}" @selected(request('lga') === $l)>{{ $l }}</option>@endforeach</select>
            <select name="check" style="{{ $field }}"><option value="">All rows</option><option value="1" @selected(request('check') === '1')>With a note to check</option></select>
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if ($filtered)<a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.tp-numbers') }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table class="sv-dt">
            <thead><tr>
                <th>S/N</th><th>Prefix</th><th>Sub Prefix</th><th>Serial No.</th><th>Drawing No. (TP No.)</th><th>Date</th><th>Title</th><th>Scanned / Remarks</th><th>Notes (check)</th>
                <th>District</th><th>LGA</th><th>Land Use</th><th>Purpose</th><th>Projects</th>
                <th>Created By</th><th>Date Created</th><th class="col-actions"></th>
            </tr></thead>
            <tbody>
                @forelse ($tps as $x)
                    @php $linked = $projects->get(TPC::key($x->tp_no), collect()); @endphp
                    <tr>
                        <td class="muted">{{ $tps->firstItem() + $loop->index }}</td>
                        <td class="nowrap">{!! $x->prefix_head !== '' ? e($x->prefix_head) : '<span class="muted">—</span>' !!}</td>
                        <td class="nowrap">{!! $x->sub_prefix !== '' ? e($x->sub_prefix) : '<span class="muted">—</span>' !!}</td>
                        <td class="nowrap">{!! trim((string) $x->serial_no) !== '' ? '<strong>' . e($x->serial_no) . '</strong>' : '<span class="muted">—</span>' !!}</td>
                        <td class="nowrap">{!! $x->tp_no !== '' ? '<span class="tp">' . e($x->tp_no) . '</span>' : '<span class="muted">—</span>' !!}</td>
                        <td class="nowrap">{!! $x->drawing_date ? e($x->drawing_date) : '<span class="muted">—</span>' !!}</td>
                        <td class="wide strong">{!! $x->layout_name ? e($x->layout_name) : '<span class="muted">—</span>' !!}</td>
                        <td>{!! $x->remarks ? e($x->remarks) : '<span class="muted">—</span>' !!}</td>
                        <td style="min-width:180px;">{!! $x->check_note ? '<span style="color:#b45309;font-size:12px;"><i class="fas fa-flag"></i> ' . e($x->check_note) . '</span>' : '<span class="muted">—</span>' !!}</td>
                        <td>{{ \App\Support\AddressBuilder::resolve($x->prop_district, $x->prop_district_other) ?: '—' }}</td>
                        <td>{{ \App\Support\AddressBuilder::resolve($x->prop_lga) ?: '—' }}</td>
                        <td>{!! $x->land_use ? e($x->land_use_label) : '<span class="muted">—</span>' !!}</td>
                        <td>{!! $x->purpose ? e($x->purpose) : '<span class="muted">—</span>' !!}</td>
                        <td>
                            @forelse ($linked as $p)
                                <a class="nowrap" href="{{ route('survey-module.compensation.projects', ['q' => $p->plan_no]) }}" title="{{ $p->display_name }}">{{ $p->project_code }}</a>@if (! $loop->last)<br>@endif
                            @empty
                                <span class="muted">—</span>
                            @endforelse
                        </td>
                        @include('survey_module.partials._created_cells', ['row' => $x])
                        <td class="col-actions">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if (! empty($lfPages[$x->tp_no]))
                                        <button type="button" class="{{ $svItem }}" data-lf-title="{{ $x->tp_no }}" data-lf-pages='@json($lfPages[$x->tp_no])' @click.stop="open = false; tpLfOpen($el)"><i data-lucide="map" class="w-4 h-4 text-gray-500"></i> View LF</button>
                                    @endif
                                    <a class="{{ $svItem }}" href="{{ route('survey-module.records.tp-numbers', array_merge(request()->query(), ['edit' => $x->id])) }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                    <form method="POST" action="{{ route('survey-module.records.tp-numbers.destroy', $x) }}" onsubmit="return confirm('Delete TP No. {{ $x->tp_no }}?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="17" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $filtered ? 'No layout drawing matches the filter.' : 'No layout drawings yet. Use Add TP No.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($tps->total()) }} layout drawings, in drawing-number order</span><div class="pagination">{{ $tps->links() }}</div></div>
</div>

{{-- View LF: the large format scan of the drawing, opened over the page. Some are very large, so it fits first; zoom and drag from there. --}}
<div id="tpLf" role="dialog" aria-modal="true" aria-labelledby="tpLfTitle" style="display:none;position:fixed;top:0;right:0;bottom:0;left:0;z-index:10000;background:#111827;flex-direction:column;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:10px 16px;background:#fff;border-bottom:1px solid var(--gray-200);">
        <strong id="tpLfTitle" class="tp" style="margin-right:auto;"></strong>
        <span id="tpLfPager" style="display:none;align-items:center;gap:6px;">
            <button type="button" class="btn btn-secondary btn-sm" id="tpLfPrev" aria-label="Previous page"><i class="fas fa-chevron-left"></i></button>
            <span id="tpLfPage" style="font-size:13px;color:var(--gray-600);"></span>
            <button type="button" class="btn btn-secondary btn-sm" id="tpLfNext" aria-label="Next page"><i class="fas fa-chevron-right"></i></button>
        </span>
        <button type="button" class="btn btn-secondary btn-sm" id="tpLfOut" aria-label="Zoom out"><i class="fas fa-search-minus"></i></button>
        <button type="button" class="btn btn-secondary btn-sm" id="tpLfFit">Fit</button>
        <button type="button" class="btn btn-secondary btn-sm" id="tpLfIn" aria-label="Zoom in"><i class="fas fa-search-plus"></i></button>
        <a class="btn btn-secondary btn-sm" id="tpLfFull" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Full size</a>
        <button type="button" class="btn btn-primary btn-sm" id="tpLfClose">Close</button>
    </div>
    <div id="tpLfStage" style="flex:1;min-height:0;overflow:auto;cursor:grab;position:relative;">
        <div id="tpLfMsg" style="position:absolute;top:24px;left:0;right:0;text-align:center;color:#e5e7eb;font-size:14px;"></div>
        <img id="tpLfImg" alt="" draggable="false" style="display:block;max-width:none;margin:auto;">
    </div>
</div>

@include('survey_module.partials._searchable_selects')

<script>
// View LF: fit first, then zoom about the middle of the view; drag to move around.
window.tpLfOpen = (function () {
    var $ = function (id) { return document.getElementById(id); };
    var box = $('tpLf'), stage = $('tpLfStage'), img = $('tpLfImg'), msg = $('tpLfMsg');
    var pages = [], at = 0, scale = 1, fit = 1;

    function size(next) {
        var midX = (stage.scrollLeft + stage.clientWidth / 2) / (img.width || 1), midY = (stage.scrollTop + stage.clientHeight / 2) / (img.height || 1);
        scale = Math.min(1, Math.max(fit, next));
        img.style.width = Math.round(img.naturalWidth * scale) + 'px';
        img.style.height = Math.round(img.naturalHeight * scale) + 'px';
        stage.scrollLeft = midX * img.width - stage.clientWidth / 2;
        stage.scrollTop = midY * img.height - stage.clientHeight / 2;
    }
    function fitNow() {
        fit = Math.min(1, stage.clientWidth / img.naturalWidth, stage.clientHeight / img.naturalHeight);
        size(fit);
    }
    function show(i) {
        at = i;
        img.style.display = 'none';
        msg.textContent = 'Loading the scan. Large plans can take a moment.';
        $('tpLfPage').textContent = 'Page ' + (at + 1) + ' of ' + pages.length;
        $('tpLfPrev').disabled = at === 0;
        $('tpLfNext').disabled = at === pages.length - 1;
        $('tpLfFull').href = pages[at].url;
        img.alt = $('tpLfTitle').textContent + ', page ' + (at + 1);
        img.src = pages[at].url;
    }
    function close() {
        box.style.display = 'none';
        img.removeAttribute('src');
    }

    img.onload = function () { msg.textContent = ''; img.style.display = 'block'; fitNow(); };
    img.onerror = function () { if (img.getAttribute('src')) msg.textContent = 'This scan could not be opened here. Try Full size.'; };
    $('tpLfPrev').onclick = function () { show(at - 1); };
    $('tpLfNext').onclick = function () { show(at + 1); };
    $('tpLfIn').onclick = function () { size(scale * 1.5); };
    $('tpLfOut').onclick = function () { size(scale / 1.5); };
    $('tpLfFit').onclick = fitNow;
    $('tpLfClose').onclick = close;
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && box.style.display !== 'none') close(); });

    var drag = null;
    stage.addEventListener('mousedown', function (e) { drag = { x: e.clientX, y: e.clientY, l: stage.scrollLeft, t: stage.scrollTop }; stage.style.cursor = 'grabbing'; e.preventDefault(); });
    window.addEventListener('mousemove', function (e) { if (drag) { stage.scrollLeft = drag.l - (e.clientX - drag.x); stage.scrollTop = drag.t - (e.clientY - drag.y); } });
    window.addEventListener('mouseup', function () { drag = null; stage.style.cursor = 'grab'; });

    return function (btn) {
        pages = JSON.parse(btn.dataset.lfPages);
        $('tpLfTitle').textContent = btn.dataset.lfTitle;
        $('tpLfPager').style.display = pages.length > 1 ? 'inline-flex' : 'none';
        box.style.display = 'flex';
        show(0);
    };
})();
</script>
<script>
(function () {
    var sel = document.getElementById('tpPrefix'), box = document.getElementById('tpPrefixOtherBox'),
        other = document.getElementById('tpPrefixOther'), serial = document.getElementById('tpSerial'), preview = document.getElementById('tpPreview'),
        sub = document.getElementById('tpSubPrefix');
    // style.display, not hidden: the form-group styles override [hidden].
    function sync() {
        var on = sel.value === @json(TP::OTHER);
        box.style.display = on ? '' : 'none';
        other.disabled = !on;
        var p = (on ? other.value : sel.value).replace(/\s+/g, '').replace(/^\/+|\/+$/g, '');
        var b = sub.value.replace(/\s+/g, '').replace(/^\/+|\/+$/g, '');
        var s = serial.value.replace(/\s+/g, '').replace(/^\/+|\/+$/g, '');
        preview.value = p && s ? p + (b ? '/' + b : '') + '/' + s : '';
    }
    [sel, other, sub, serial].forEach(function (el) { el.addEventListener('input', sync); el.addEventListener('change', sync); });
    sync();

    // Land Use / Purpose: "Other" reveals a required box; any other choice hides, clears and disables it.
    [['tpLandUse', 'tpLandUseOtherBox', 'tpLandUseOther'], ['tpPurpose', 'tpPurposeOtherBox', 'tpPurposeOther']].forEach(function (ids) {
        var pick = document.getElementById(ids[0]), wrap = document.getElementById(ids[1]), input = document.getElementById(ids[2]);
        function toggle(initial) {
            var on = pick.value === @json(TP::OTHER);
            wrap.style.display = on ? '' : 'none';
            input.disabled = !on;
            input.required = on;
            if (!on) input.value = ''; else if (initial !== true) input.focus();
        }
        pick.addEventListener('change', toggle);
        toggle(true);
    });
})();
</script>
@endsection
