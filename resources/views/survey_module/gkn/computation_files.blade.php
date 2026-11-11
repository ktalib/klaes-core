@extends('survey_module.layouts.klaes')

@section('page-title', 'Computation Files — KLAES')

@php
  $PageTitle = 'Computation Files (Field Jackets)';
  $PageDescription = 'Public: commissioned by the Survey Department, LPKN-serial. Private: commissioned by Cadastral, LPPL-year-serial.';
  $field = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $e = $editing ?? null;
  $open = $e || $errors->any();
  $v = fn ($k) => old($k, $e?->$k);
  $propertyLocation = ($v('district') || $v('lga'))
      ? \App\Support\AddressBuilder::propertyLocation(['district' => $v('district'), 'lga' => $v('lga'), 'state' => 'Kano'])
      : $v('location');
@endphp

@section('survey-content')
{{-- CR 30102026 items 12-14 (user, 2026-10-31): the source is settled. Government = Survey's own (LPKN), Private = Cadastral's (LPPL). --}}
@include('survey_module.partials._flash')
@include('survey_module.partials._gov_private_tabs', ['route' => 'survey-module.gkn.computation-files', 'tab' => $tab])

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Public (LPKN)</div><div class="kpi-value">{{ number_format($stats['government']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Private (LPPL)</div><div class="kpi-value">{{ number_format($stats['private']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Commissioned this month</div><div class="kpi-value">{{ number_format($stats['month']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">With an I to S</div><div class="kpi-value">{{ number_format($stats['with_its']) }}</div></div>
</div>

@if ($tab === 'private')
<div class="page-header"><div></div>@include('survey_module.partials._records_export', ['endpoint' => route('survey-module.gkn.computation-files.export'), 'title' => 'Computation Files - Private', 'dateLabel' => 'Date Commissioned'])</div>
@endif

@if ($tab === 'government')
<div class="page-header">
    <div style="font-size:13px;color:var(--gray-500);">Numbered LPKN-serial (e.g. LPKN-123), one series for all years. The serial can be set to continue from the last one used, under Configurable Entities &gt; Serial Initialization.</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">@include('survey_module.partials._records_export', ['endpoint' => route('survey-module.gkn.computation-files.export'), 'title' => 'Computation Files - ' . ($tab === 'private' ? 'Private' : 'Public'), 'dateLabel' => 'Date Commissioned'])
    <button type="button" class="btn btn-primary" onclick="document.getElementById('cfForm').classList.add('open')"><i class="fas fa-plus"></i> Commission Computation File</button>
    </div>
</div>

{{-- Commission / edit, as a modal (Survey entry forms are modals). The number is given on save. --}}
<form method="POST" id="cfForm"
      action="{{ $e ? route('survey-module.gkn.computation-files.update', $e) : route('survey-module.gkn.computation-files.store') }}"
      class="farmer-entry-form {{ $open ? 'open' : '' }}" data-sv-modal="{{ $e ? 'Edit ' . $e->computation_no : 'Commission Computation File' }}">
    @csrf
    @if ($e) @method('PUT') @endif
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-folder-open"></i><span>File</span></div>
        {{-- Order (user, 2026-11-11): File No. first, then Computation File No., then Job No. --}}
        <div class="form-group">
            <label>File No.</label>
            <div style="display:flex;gap:6px;">
                <input type="text" name="file_number" id="cfFileNo" value="{{ $v('file_number') }}" readonly placeholder="Pick from the file selector" style="background:var(--gray-100);" />
                <button type="button" class="btn btn-secondary btn-sm" id="cfPick"><i class="fas fa-search"></i></button>
                <button type="button" class="btn btn-outline btn-sm" id="cfClear" title="Clear"><i class="fas fa-times"></i></button>
            </div>
            <p class="helper-text" id="cfFileNote">Optional. Picking a file fills its details below.</p>
        </div>
        {{-- The Computation File No.: this file's, or the next one on a new file (given on save; user 2026-11-02). --}}
        <div class="form-group">
            <label>Computation File No.</label>
            <input type="text" value="{{ $e ? $e->computation_no : $nextNo }}" readonly tabindex="-1"
                   style="background:var(--gray-100);font-weight:700;letter-spacing:.3px;" />
            @unless ($e)<p class="helper-text">Next number. It is given when you press Commission.</p>@endunless
        </div>
        {{-- Job No (Survey Update 5a, 2026-11-10): auto generated on Commission, never typed; surveyors quote it to request beacons. --}}
        <div class="form-group">
            <label>Job No.</label>
            <input type="text" value="{{ $e ? ($e->job_no ?: 'None (commissioned before Job Nos)') : $nextJobNo }}" readonly tabindex="-1"
                   style="background:var(--gray-100);font-weight:700;letter-spacing:.3px;" />
            @unless ($e)<p class="helper-text">Given with the Computation File No. when you press Commission.</p>@endunless
        </div>
        <div class="form-group">
            <label>File Title</label>
            <input type="text" name="file_title" id="cfTitle" value="{{ $v('file_title') }}" maxlength="500" />
        </div>
    </div>
    {{-- Property order: Plot No., District, LGA, Location. --}}
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Property</span></div>
        <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" id="cfPlot" value="{{ $v('plot_no') }}" maxlength="100" /></div>
        <div class="form-group">
            <label for="cfDistrict">District</label>
            <select name="district" id="cfDistrict" class="cf-location-select" data-placeholder="Search district…">
                <option value=""></option>
                @if ($v('district'))<option value="{{ $v('district') }}" selected>{{ $v('district') }}</option>@endif
            </select>
        </div>
        <div class="form-group">
            <label for="cfLga">LGA</label>
            <select name="lga" id="cfLga" class="cf-location-select" data-placeholder="Search LGA…">
                <option value=""></option>
                @if ($v('lga'))<option value="{{ $v('lga') }}" selected>{{ $v('lga') }}</option>@endif
            </select>
        </div>
        <div class="form-group span-3">
            <label for="cfLocation">Location</label>
            <input type="text" name="location" id="cfLocation" value="{{ $propertyLocation }}" maxlength="500" readonly
                   style="background:var(--gray-100);" placeholder="Filled from District and LGA" />
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-user-tie"></i><span>Survey</span></div>
        <div class="form-group">
            <label>Surveyor <span class="required">*</span></label>
            <select name="surveyors_list_id" id="cfSurveyor" required class="sv-searchable">
                <option value="">Select surveyor…</option>
                @foreach ($surveyors as $s)
                    <option value="{{ $s->id }}" @selected((string) $v('surveyors_list_id') === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>
                @endforeach
            </select>
        </div>
        {{-- Instruction to Survey: removed 2026-11-02, back as optional (user, 2026-11-03). The I to S form can also pick the file. --}}
        @php $itsSel = (string) old('survey_its_id', $editingIts ?? ''); @endphp
        <div class="form-group span-2">
            <label>Instruction to Survey</label>
            <select name="survey_its_id" id="cfIts" class="sv-searchable">
                <option value="">None</option>
                @foreach ($itsOptions as $i)
                    <option value="{{ $i->id }}" data-type="{{ $i->survey_type }}" data-beacons="{{ $itsBeacons[$i->id] ?? '' }}" @selected($itsSel === (string) $i->id)>{{ $i->its_number ?: $i->job_ref }} · {{ $i->file_number }}{{ $i->file_title ? ' · ' . \Illuminate\Support\Str::limit($i->file_title, 60) : '' }}</option>
                @endforeach
            </select>
            <p class="helper-text">Optional.</p>
        </div>
    </div>
    {{-- Survey Type and Beacon Numbers, as on the I to S (user, 2026-11-06). Beacons search the register; Other takes typed numbers and ranges, in the register or not. --}}
    @php
        $stNames = collect($surveyTypes)->pluck('name');
        $stSaved = $e?->survey_type;
        $stPick = old('survey_type', $stSaved === null ? '' : ($stNames->contains($stSaved) ? $stSaved : 'Other'));
        $stOther = old('survey_type_other', $stPick === 'Other' ? $stSaved : '');
        $bPicks = collect(session()->hasOldInput() ? old('beacon_picks', []) : $savedBeacons);
        $bOtherOn = $bPicks->contains('Other');
    @endphp
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-map-pin"></i><span>Survey Type &amp; Beacons</span></div>
        <div class="form-group">
            <label>Survey Type</label>
            <select name="survey_type" id="cfSurveyType" class="sv-searchable"
                    onchange="var o=document.getElementById('cfSurveyTypeOther'),on=this.value==='Other';o.style.display=on?'':'none';o.disabled=!on;o.required=on;">
                <option value="">Select survey type…</option>
                @foreach ($surveyTypes as $t)
                    <option value="{{ $t->name }}" @selected($stPick === $t->name)>{{ $t->name }}</option>
                @endforeach
                <option value="Other" @selected($stPick === 'Other')>Other (specify)</option>
            </select>
            <input type="text" name="survey_type_other" id="cfSurveyTypeOther" maxlength="100" value="{{ $stOther }}" placeholder="Specify the survey type *" aria-label="Specify the survey type"
                   style="margin-top:6px;{{ $stPick === 'Other' ? '' : 'display:none;' }}" @if ($stPick === 'Other') required @else disabled @endif />
            @error('survey_type_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
        </div>
        <div class="form-group span-2">
            <label>Beacon Numbers</label>
            <style>#cfForm .select2-selection--multiple .select2-selection__choice { padding-left: 24px !important; }</style>
            <select name="beacon_picks[]" id="cfBeacons" multiple data-no-search aria-label="Beacon Numbers" style="width:100%;">
                @foreach ($bPicks as $b)
                    <option value="{{ $b }}" selected>{{ $b === 'Other' ? 'Other (specify)' : $b }}</option>
                @endforeach
            </select>
            <textarea name="beacon_other" id="cfBeaconOther" rows="2" maxlength="8000" placeholder="Type the numbers *, e.g. KDJ 0001 to KDJ 0004, A1404B" aria-label="Other beacon numbers"
                      style="margin-top:6px;{{ $bOtherOn ? '' : 'display:none;' }}" @if ($bOtherOn) required @else disabled @endif>{{ old('beacon_other') }}</textarea>
            @error('beacon_picks')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
            @error('beacon_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
            <p class="helper-text" id="cfItsBeacons" style="display:none;"></p>
            <p class="helper-text">Search the numbers free for this surveyor, or choose Other to type numbers and ranges. A number on another job, or issued to another surveyor, is refused.</p>
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="form-group span-3"><label>Remarks</label><textarea name="remarks" maxlength="1000">{{ $v('remarks') }}</textarea></div>
    </div>
    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('cfForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> {{ $e ? 'Save Changes' : 'Commission' }}</button></div>
    </div>
</form>
@endif

<form method="GET" class="table-toolbar">
    @if ($tab === 'private')<input type="hidden" name="tab" value="private" />@endif
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ $term }}" placeholder="Computation file no., file no., title{{ $tab === 'government' ? ', surveyor' : '' }}…" style="{{ $field }}width:300px;" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
        @if ($term !== '')<a class="btn btn-outline btn-sm" href="{{ route('survey-module.gkn.computation-files', $tab === 'private' ? ['tab' => 'private'] : []) }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Computation File No.</th>
                    @if ($tab !== 'private')<th>Job No.</th>@endif
                    <th>File No.</th>
                    <th>Title</th>
                    <th>Plot No.</th>
                    <th>District</th>
                    <th>LGA</th>
                    <th>Location</th>
                    <th>Surveyor</th>
                    @if ($tab !== 'private')<th>Survey Type</th><th>Beacon Numbers</th>@endif
                    <th>{{ $tab === 'private' ? 'Job No.' : 'I to S' }}</th>
                    <th>Commissioned</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        @if ($tab === 'private')
                            <td><strong style="font-family:monospace;">{{ $row->lppl_no ?: $row->field_jacket_no }}</strong>@if ($row->lppl_no)<div style="font-size:11px;color:var(--gray-500);font-family:monospace;">{{ $row->field_jacket_no }}</div>@endif</td>
                        @else
                            <td><strong style="font-family:monospace;">{{ $row->computation_no }}</strong></td>
                            <td style="font-family:monospace;white-space:nowrap;">{{ $row->job_no ?: '—' }}</td>
                        @endif
                        <td style="font-family:monospace;white-space:nowrap;">{{ $row->file_number ?: '—' }}</td>
                        <td>{{ $row->file_title ?: '—' }}</td>
                        <td>{{ $row->plot_no ?: '—' }}</td>
                        <td>{{ $row->district ?: '—' }}</td>
                        <td>{{ $row->lga ?: '—' }}</td>
                        <td>{{ $row->location ?: '—' }}</td>
                        @if ($tab === 'private')
                            <td>{{ $row->surveyor?->display_name ?: '—' }}</td>
                            <td>{{ $row->job?->job_number ?: '—' }}</td>
                        @else
                            <td>{{ $row->surveyor_name ?: '—' }}</td>
                            {{-- The I to S that names this Computation File (or the one picked here before 2026-11-02). --}}
                            <td>{{ $row->survey_type ?: '—' }}</td>
                            <td style="min-width:140px;">{{ implode(', ', $beaconMap[$row->id] ?? []) ?: '—' }}</td>
                            <td>{{ ($its[$row->id] ?? null) ?: '—' }}</td>
                        @endif
                        <td style="white-space:nowrap;">{{ optional($row->commissioned_at)->format('d M Y') ?: '—' }}</td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    @if ($tab === 'private')
                                        <a href="{{ route('cadastral-dept.field-jackets.show', $row->id) }}" class="{{ $svItem }}">
                                            <i data-lucide="briefcase" class="w-4 h-4 text-gray-500"></i> Open in Cadastral
                                        </a>
                                    @else
                                        <a href="{{ route('survey-module.gkn.computation-files', ['edit' => $row->id]) }}" class="{{ $svItem }}">
                                            <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit
                                        </a>
                                    @endif
                                    @if ($row->file_number)
                                        <a href="{{ route('survey-module.file-status', ['by' => 'file', 'q' => $row->file_number]) }}" class="{{ $svItem }}">
                                            <i data-lucide="file-search" class="w-4 h-4 text-gray-500"></i> File Status
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $tab === 'private' ? 12 : 15 }}" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if ($term !== '')
                                No computation file matches the search.
                            @elseif ($tab === 'private')
                                No Computation Files from Cadastral yet. They show here once Cadastral commissions them.
                            @else
                                No Computation Files yet. Use <strong>Commission Computation File</strong>.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection

@if ($tab === 'government')
@push('scripts')
    {{-- The shared selector, outside the .survey-proto wrapper so the prototype CSS cannot reach it (as on the CO form). --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
(function () {
    // The file comes from the global file number selector and fills the details the records hold (rule 5).
    var no = document.getElementById('cfFileNo'), note = document.getElementById('cfFileNote');
    var district = document.getElementById('cfDistrict'), lga = document.getElementById('cfLga');
    var location = document.getElementById('cfLocation');
    function syncLocation() {
        var parts = [district.value, lga.value].map(function (value) { return value.trim(); }).filter(Boolean);
        location.value = parts.length ? parts.concat('Kano').join(', ') : '';
    }
    window.addEventListener('load', function () {
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            [
                { element: district, url: @json(route('survey-module.lookup.districts')) },
                { element: lga, url: @json(route('survey-module.lookup.lgas')) }
            ].forEach(function (lookup) {
                var $select = jQuery(lookup.element);
                $select.select2({
                    width: '100%',
                    placeholder: $select.data('placeholder'),
                    allowClear: true,
                    ajax: {
                        url: lookup.url,
                        dataType: 'json',
                        delay: 250,
                        data: function (params) { return { q: params.term || '', page: params.page || 1 }; },
                        processResults: function (data) { return data; },
                        cache: true
                    },
                    minimumInputLength: 0
                }).on('change', syncLocation);
            });
        } else {
            [district, lga].forEach(function (select) { select.addEventListener('change', syncLocation); });
        }
    });
    var fill = { title: 'cfTitle', plot: 'cfPlot', district: 'cfDistrict', lga: 'cfLga' }, filled = {};
    function setValue(el, value) {
        if (el.tagName === 'SELECT') {
            var option = Array.prototype.find.call(el.options, function (item) { return item.value === value; });
            if (!option) { el.add(new Option(value, value, true, true)); }
            el.value = value;
            if (window.jQuery) jQuery(el).trigger('change');
        } else {
            el.value = value;
        }
    }
    function clearFilled() {
        Object.keys(filled).forEach(function (k) {
            var el = document.getElementById(fill[k]);
            if (el && el.value === filled[k]) setValue(el, '');
        });
        filled = {};
    }
    function lookup(fileNo) {
        fetch(@json(route('survey-module.compensation.co-files.related-lookup')) + '?file_number=' + encodeURIComponent(fileNo), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                Object.keys(fill).forEach(function (k) {
                    var el = document.getElementById(fill[k]);
                    if (el && d[k] && !el.value) { setValue(el, d[k]); filled[k] = d[k]; }
                });
                note.textContent = d.indexed ? 'Indexed file: its details were filled in; check them.' : (d.known ? 'On the file number register, not yet indexed.' : 'Not in the records yet; enter the details.');
            })
            .catch(function () { note.textContent = 'The file details could not be fetched; enter them.'; });
    }
    document.getElementById('cfPick').addEventListener('click', function () {
        if (!window.GlobalFileNoModal) { note.textContent = 'The file number selector did not load. Refresh the page.'; return; }
        window.GlobalFileNoModal.open({
            callback: function (data) {
                var picked = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                if (!picked) return;
                clearFilled();
                no.value = picked;
                lookup(picked);
            }
        });
    });
    document.getElementById('cfClear').addEventListener('click', function () { clearFilled(); no.value = ''; note.textContent = 'Optional. Picking a file fills its details below.'; });

    // Beacon Numbers: search the register, or Other for typed numbers and ranges. select2 loads after this script.
    window.addEventListener('load', function () {
        var $b = window.jQuery && jQuery.fn.select2 ? jQuery('#cfBeacons') : null;
        if (!$b || !$b.length) return;
        var other = document.getElementById('cfBeaconOther'), surveyor = document.getElementById('cfSurveyor');
        $b.select2({
            width: '100%', placeholder: 'Search beacon numbers…', closeOnSelect: false,
            ajax: {
                url: @json(route('survey-module.gkn.computation-files.beacon-search')), delay: 250,
                data: function (p) { return { q: p.term || '', surveyor: surveyor.value, file: @json($e?->id) }; },
                processResults: function (d) {
                    return { results: (d.numbers || []).map(function (n) { return { id: n, text: n }; }).concat([{ id: 'Other', text: 'Other (specify)' }]) };
                }
            }
        }).on('change', function () {
            var on = ($b.val() || []).indexOf('Other') !== -1;
            other.style.display = on ? '' : 'none';
            other.disabled = !on;
            other.required = on;
        });
    });

    // Picking an I to S fills its Survey Type and shows its beacons, which stay on the I to S. A new pick clears what the last one filled.
    (function () {
        var its = document.getElementById('cfIts'), type = document.getElementById('cfSurveyType'),
            typeOther = document.getElementById('cfSurveyTypeOther'), note = document.getElementById('cfItsBeacons');
        if (!its) return;
        var filled = '';
        function show() {
            var o = its.options[its.selectedIndex], beacons = o ? (o.getAttribute('data-beacons') || '') : '';
            note.textContent = beacons ? 'Already on this I to S: ' + beacons + '.' : '';
            note.style.display = beacons ? '' : 'none';
        }
        function setType(value) {
            var listed = Array.prototype.some.call(type.options, function (x) { return x.value === value; });
            type.value = !value ? '' : (listed ? value : 'Other');
            typeOther.value = value && !listed ? value : '';
            type.dispatchEvent(new Event('change', { bubbles: true }));
        }
        its.addEventListener('change', function () {
            var o = its.options[its.selectedIndex], want = o ? (o.getAttribute('data-type') || '') : '';
            var current = type.value === 'Other' ? typeOther.value : type.value;
            if (current === '' || current === filled) { setType(want); filled = want; }
            show();
        });
        show();
    })();
})();
</script>
@endpush
@endif
