@extends('survey_module.layouts.klaes')

@section('page-title', 'Public Layout Plans — KLAES')

@php
  use App\Models\Survey\SurveyTpNumber as TP;
  use App\Http\Controllers\Survey\TpNumberController as TPC;

  $PageTitle = 'Public Layout Plans';
  $PageDescription = 'Layout plan TP numbers (e.g. TP/KNUPDA/338A), listed by serial number.';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $e = $editing;
  $v = fn ($k, $d = null) => old($k, $e ? $e->{$k} : $d);
  $open = $errors->any() || $e;
  $lgas = \App\Http\Controllers\Survey\LookupController::options('lgas');
  $filtered = request()->hasAny(['q', 'prefix', 'lga']);
  $curPrefix = $v('prefix', 'TP/KNUPDA');
  $prefixIsListed = in_array($curPrefix, $prefixes, true);
  $pickedPrefix = old('prefix', $prefixIsListed ? $curPrefix : TP::OTHER);
  $pickedLandUse = $v('land_use');
  $curPurpose = $e?->purpose;
  $pickedPurpose = old('purpose', $curPurpose);
@endphp

@section('survey-content')
@include('survey_module.partials._flash')
@include('survey_module.partials._table_ui')

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('tpForm').classList.add('open')"><i class="fas fa-plus"></i> Add TP No.</button>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">TP Nos</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Prefixes</div><div class="kpi-value">{{ number_format($stats['prefixes']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">With a compensation project</div><div class="kpi-value">{{ number_format($stats['linked']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Plots</div><div class="kpi-value">{{ number_format($stats['plots']) }}</div></div>
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
                @foreach ($prefixes as $p)<option value="{{ $p }}" @selected($pickedPrefix === $p)>{{ $p }}</option>@endforeach
                <option value="{{ TP::OTHER }}" @selected($pickedPrefix === TP::OTHER)>Other</option>
            </select>
        </div>
        <div class="form-group" id="tpPrefixOtherBox" @unless ($pickedPrefix === TP::OTHER) style="display:none;" @endunless>
            <label>Specify Prefix <span class="required">*</span></label>
            <input type="text" name="prefix_other" id="tpPrefixOther" maxlength="80" placeholder="e.g. TP/KNUPDA/UC/GZ"
                   value="{{ old('prefix_other', $prefixIsListed ? '' : $curPrefix) }}" @disabled($pickedPrefix !== TP::OTHER) />
        </div>
        <div class="form-group">
            <label>Serial No. <span class="required">*</span></label>
            <input type="text" name="serial_no" id="tpSerial" value="{{ $v('serial_no') }}" maxlength="40" placeholder="e.g. 338A" required />
        </div>
        <div class="form-group">
            <label>TP No.</label>
            <input type="text" id="tpPreview" readonly style="background:var(--gray-100);font-weight:700;" value="{{ $e?->tp_no }}" />
            <p class="helper-text">Prefix / Serial No., as it will be saved.</p>
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-map"></i><span>Layout</span></div>
        <div class="form-group span-2">
            <label>Layout Name <span class="required">*</span></label>
            <input type="text" name="layout_name" value="{{ $v('layout_name') }}" maxlength="255" required placeholder="e.g. K/Inna" />
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
        <div class="form-group full">
            <label>Remarks</label>
            <textarea name="remarks" maxlength="2000">{{ $v('remarks') }}</textarea>
        </div>
    </div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.records.tp-numbers') }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Add TP No.' }}</button></div>
    </div>
</form>

<div class="table-container" x-data>
    <div class="table-header">
        <h3>TP Nos <span style="font-weight:400;font-size:13px;color:var(--gray-500);">· by serial number</span></h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="TP No., serial, layout, district…" style="{{ $field }}width:230px;">
            <select name="prefix" style="{{ $field }}"><option value="">All prefixes</option>@foreach ($prefixes as $p)<option value="{{ $p }}" @selected(request('prefix') === $p)>{{ $p }}</option>@endforeach</select>
            <select name="lga" class="sv-searchable" data-width="180px" style="{{ $field }}"><option value="">All LGAs</option>@foreach ($lgas as $l)<option value="{{ $l }}" @selected(request('lga') === $l)>{{ $l }}</option>@endforeach</select>
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if ($filtered)<a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.tp-numbers') }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table class="sv-dt">
            <thead><tr>
                <th>S/N</th><th>Prefix</th><th>Serial No.</th><th>TP No.</th><th>Layout Name</th><th>District</th><th>LGA</th>
                <th>Land Use</th><th>Purpose</th><th class="num">Plots</th><th class="num">Area (ha)</th><th>Approval Date</th><th>Projects</th>
                <th>Created By</th><th>Date Created</th><th class="col-actions"></th>
            </tr></thead>
            <tbody>
                @forelse ($tps as $x)
                    @php $linked = $projects->get(TPC::key($x->tp_no), collect()); @endphp
                    <tr>
                        <td class="muted">{{ $tps->firstItem() + $loop->index }}</td>
                        <td class="nowrap"><span class="tp">{{ $x->prefix }}</span></td>
                        <td class="code">{{ $x->serial_no }}</td>
                        <td><span class="tp">{{ $x->tp_no }}</span></td>
                        <td class="wide strong">{!! $x->layout_name ? e($x->layout_name) : '<span class="muted">—</span>' !!}</td>
                        <td>{{ \App\Support\AddressBuilder::resolve($x->prop_district, $x->prop_district_other) ?: '—' }}</td>
                        <td>{{ \App\Support\AddressBuilder::resolve($x->prop_lga) ?: '—' }}</td>
                        <td>{!! $x->land_use ? e($x->land_use_label) : '<span class="muted">—</span>' !!}</td>
                        <td>{!! $x->purpose ? e($x->purpose) : '<span class="muted">—</span>' !!}</td>
                        <td class="num">{!! $x->plot_count !== null ? number_format($x->plot_count) : '<span class="muted">—</span>' !!}</td>
                        <td class="num">{!! $x->area_ha !== null ? rtrim(rtrim(number_format((float) $x->area_ha, 4), '0'), '.') : '<span class="muted">—</span>' !!}</td>
                        <td class="nowrap">{!! $x->approval_date ? $x->approval_date->format('d M Y') : '<span class="muted">—</span>' !!}</td>
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
                    <tr><td colspan="16" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $filtered ? 'No TP No. matches the filter.' : 'No TP Nos yet. Use Add TP No.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($tps->total()) }} TP Nos, by serial number</span><div class="pagination">{{ $tps->links() }}</div></div>
</div>

@include('survey_module.partials._searchable_selects')

<script>
(function () {
    var sel = document.getElementById('tpPrefix'), box = document.getElementById('tpPrefixOtherBox'),
        other = document.getElementById('tpPrefixOther'), serial = document.getElementById('tpSerial'), preview = document.getElementById('tpPreview');
    // style.display, not hidden: the form-group styles override [hidden].
    function sync() {
        var on = sel.value === @json(TP::OTHER);
        box.style.display = on ? '' : 'none';
        other.disabled = !on;
        var p = (on ? other.value : sel.value).replace(/\s+/g, '').replace(/^\/+|\/+$/g, '');
        var s = serial.value.replace(/\s+/g, '').replace(/^\/+|\/+$/g, '');
        preview.value = p && s ? p + '/' + s : '';
    }
    [sel, other, serial].forEach(function (el) { el.addEventListener('input', sync); el.addEventListener('change', sync); });
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
