@extends('survey_module.layouts.klaes')

@section('page-title', 'Private Layout — KLAES')

@php
  use App\Models\Survey\SurveyPrivateLayout as PL;

  $PageTitle = 'Private Layout';
  $PageDescription = 'Privately owned layouts: the owner or developer, the surveyor who prepared the plan, and its approval.';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $field = 'border:1.5px solid var(--gray-300);border-radius:6px;padding:7px 8px;';
  $e = $editing;
  $v = fn ($k, $d = null) => old($k, $e ? $e->{$k} : $d);
  $open = $errors->any() || $e;
  $lgas = \App\Http\Controllers\Survey\LookupController::options('lgas');
  $statusClass = fn ($s) => ['Approved' => 'completed', 'Rejected' => 'rejected'][$s] ?? 'pending';
  $filtered = request()->hasAny(['q', 'status', 'land_use', 'lga']);
@endphp

@section('survey-content')
@include('survey_module.partials._flash')
@if ($errors->any())<div class="alert alert-danger" style="margin-bottom:12px;">{{ $errors->first() }}</div>@endif

<div class="page-header">
    <div></div>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('plForm').classList.add('open')"><i class="fas fa-plus"></i> Add Private Layout</button>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Private layouts</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Approved</div><div class="kpi-value">{{ number_format($stats['approved']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Awaiting decision</div><div class="kpi-value">{{ number_format($stats['pending']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Plots</div><div class="kpi-value">{{ number_format($stats['plots']) }}</div></div>
</div>

{{-- Register / edit, as a modal (Survey entry forms are modals). --}}
<form method="POST" enctype="multipart/form-data" id="plForm"
      action="{{ $e ? route('survey-module.records.private-layouts.update', $e) : route('survey-module.records.private-layouts.store') }}"
      class="farmer-entry-form {{ $open ? 'open' : '' }}" data-sv-modal="{{ $e ? 'Edit ' . $e->layout_name : 'Add Private Layout' }}">
    @csrf
    @if ($e) @method('PUT') @endif
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-map"></i><span>Layout</span></div>
        {{-- Layout Ref (PVL-….) hidden from the form and table (user, 2026-10-30); still given on save and searchable. --}}
        <div class="form-group">
            <label>Layout / Plan No.</label>
            <input type="text" name="layout_no" value="{{ $v('layout_no') }}" maxlength="100" placeholder="As written on the plan" />
        </div>
        <div class="form-group span-2">
            <label>Layout Name <span class="required">*</span></label>
            <input type="text" name="layout_name" value="{{ $v('layout_name') }}" maxlength="255" required />
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-user-tie"></i><span>Owner and surveyor</span></div>
        <div class="form-group">
            <label>Owner / Developer <span class="required">*</span></label>
            <input type="text" name="owner_name" value="{{ $v('owner_name') }}" maxlength="255" required />
        </div>
        <div class="form-group">
            <label>Owner Phone</label>
            <input type="text" name="owner_phone" value="{{ $v('owner_phone') }}" maxlength="50" />
        </div>
        <div class="form-group">
            <label>Surveyor</label>
            <input type="text" name="surveyor_name" value="{{ $v('surveyor_name') }}" maxlength="255" />
        </div>
        <div class="form-group">
            <label>Surveyor Reg. No.</label>
            <input type="text" name="surveyor_registration" value="{{ $v('surveyor_registration') }}" maxlength="100" placeholder="e.g. SURCON no." />
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-ruler-combined"></i><span>Plots and use</span></div>
        <div class="form-group">
            <label>No. of Plots</label>
            <input type="number" name="plot_count" min="1" value="{{ $v('plot_count') }}" />
        </div>
        <div class="form-group">
            <label>Area (ha)</label>
            <input type="number" name="area_ha" min="0" step="0.0001" value="{{ $v('area_ha') !== null ? (float) $v('area_ha') : '' }}" />
        </div>
        <div class="form-group">
            <label>Land Use</label>
            <select name="land_use" id="plLandUse">
                <option value="">Select…</option>
                @foreach (PL::LAND_USES as $u)<option value="{{ $u }}" @selected($v('land_use') === $u)>{{ $u }}</option>@endforeach
                <option value="{{ PL::OTHER }}" @selected($v('land_use') === PL::OTHER)>Other</option>
            </select>
        </div>
        <div class="form-group" id="plLandUseOtherBox" @unless ($v('land_use') === PL::OTHER) style="display:none;" @endunless>
            <label>Specify Land Use <span class="required">*</span></label>
            <input type="text" name="land_use_other" value="{{ $v('land_use_other') }}" maxlength="255" @disabled($v('land_use') !== PL::OTHER) />
        </div>
    </div>

    @include('survey_module.partials._address_builder', ['prefix' => 'prop_', 'mode' => 'property', 'model' => $e, 'legend' => 'Layout Location'])

    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-stamp"></i><span>Approval</span></div>
        <div class="form-group">
            <label>Status <span class="required">*</span></label>
            <select name="status">
                @foreach (PL::STATUSES as $s)<option value="{{ $s }}" @selected($v('status', 'Submitted') === $s)>{{ $s }}</option>@endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Submitted On</label>
            <input type="date" name="submitted_on" max="{{ now()->toDateString() }}" value="{{ old('submitted_on', $e?->submitted_on?->format('Y-m-d')) }}" />
        </div>
        <div class="form-group">
            <label>Approved On</label>
            <input type="date" name="approved_on" max="{{ now()->toDateString() }}" value="{{ old('approved_on', $e?->approved_on?->format('Y-m-d')) }}" />
            <p class="helper-text">Required when the status is Approved.</p>
        </div>
        <div class="form-group">
            <label>Layout Plan (PDF / JPG / PNG)</label>
            <input type="file" name="plan" accept="application/pdf,image/jpeg,image/png" />
            @if ($e && $e->plan_path)<p class="helper-text">Current: {{ $e->plan_name }}. A new file replaces it.</p>@endif
        </div>
        <div class="form-group span-2">
            <label>Remarks</label>
            <textarea name="remarks" maxlength="2000">{{ $v('remarks') }}</textarea>
        </div>
    </div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.records.private-layouts') }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Register Layout' }}</button></div>
    </div>
</form>

<div class="table-container" x-data>
    <div class="table-header">
        <h3>Private Layouts</h3>
        <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Ref, plan no., name, owner, surveyor…" style="{{ $field }}width:230px;">
            <select name="status" style="{{ $field }}"><option value="">All status</option>@foreach (PL::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ $s }}</option>@endforeach</select>
            <select name="land_use" style="{{ $field }}"><option value="">All land uses</option>@foreach (array_merge(PL::LAND_USES, [PL::OTHER]) as $u)<option value="{{ $u }}" @selected(request('land_use') === $u)>{{ $u }}</option>@endforeach</select>
            <select name="lga" style="{{ $field }}"><option value="">All LGAs</option>@foreach ($lgas as $l)<option value="{{ $l }}" @selected(request('lga') === $l)>{{ $l }}</option>@endforeach</select>
            <button class="btn btn-secondary btn-sm" type="submit"><i class="fas fa-search"></i></button>
            @if ($filtered)<a class="btn btn-secondary btn-sm" href="{{ route('survey-module.records.private-layouts') }}">Clear</a>@endif
        </form>
    </div>
    <div class="table-scroll">
        <table>
            <thead><tr><th>S/N</th><th>Plan No.</th><th>Layout Name</th><th>Owner / Developer</th><th>Surveyor</th><th>District</th><th>LGA</th><th>Plots</th><th>Area (ha)</th><th>Land Use</th><th>Status</th><th>Created By</th><th>Date Created</th><th></th></tr></thead>
            <tbody>
                @forelse ($layouts as $x)
                    <tr>
                        <td>{{ $layouts->firstItem() + $loop->index }}</td>
                        <td>{{ $x->layout_no ?: '—' }}</td>
                        <td><strong>{{ $x->layout_name }}</strong></td>
                        <td>{{ $x->owner_name }}@if ($x->owner_phone)<div style="font-size:12px;color:var(--gray-500);">{{ $x->owner_phone }}</div>@endif</td>
                        <td>{{ $x->surveyor_name ?: '—' }}@if ($x->surveyor_registration)<div style="font-size:12px;color:var(--gray-500);">{{ $x->surveyor_registration }}</div>@endif</td>
                        <td>{{ \App\Support\AddressBuilder::resolve($x->prop_district, $x->prop_district_other) ?: '—' }}</td>
                        <td>{{ \App\Support\AddressBuilder::resolve($x->prop_lga) ?: '—' }}</td>
                        <td>{{ $x->plot_count !== null ? number_format($x->plot_count) : '—' }}</td>
                        <td>{{ $x->area_ha !== null ? number_format((float) $x->area_ha, 4) : '—' }}</td>
                        <td>{{ $x->land_use_label ?: '—' }}</td>
                        <td><span class="status-badge {{ $statusClass($x->status) }}"><span class="dot"></span>{{ $x->status }}</span></td>
                        @include('survey_module.partials._created_cells', ['row' => $x])
                        <td style="width:44px;">
                            <div x-data="{ open: false, top: 0, left: 0 }" class="relative">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false" :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a class="{{ $svItem }}" href="{{ route('survey-module.records.private-layouts', array_merge(request()->query(), ['edit' => $x->id])) }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                    @if ($x->plan_path)
                                        <a class="{{ $svItem }}" href="{{ route('survey-module.records.private-layouts.plan', $x) }}" target="_blank" rel="noopener"><i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> View Plan</a>
                                    @endif
                                    <form method="POST" action="{{ route('survey-module.records.private-layouts.destroy', $x) }}" onsubmit="return confirm({{ json_encode('Delete ' . $x->layout_name . '?') }});">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="14" style="text-align:center;padding:22px;color:var(--gray-500);">{{ $filtered ? 'No private layout matches the filter.' : 'No private layouts registered yet.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer"><span>{{ number_format($layouts->total()) }} private layouts</span><div class="pagination">{{ $layouts->links() }}</div></div>
</div>

<script>
(function () {
    var sel = document.getElementById('plLandUse'), box = document.getElementById('plLandUseOtherBox');
    // style.display, not hidden: the form-group styles override [hidden].
    var sync = function () { var on = sel.value === @json(PL::OTHER); box.style.display = on ? '' : 'none'; box.querySelector('input').disabled = !on; };
    sel.addEventListener('change', sync); sync();
})();
</script>
@endsection
