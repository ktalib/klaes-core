@php
    $editing = $its->exists;
    $issued  = $its->isIssued();
    $locked  = $issued || $its->isClosed();
    $ro      = 'background:var(--gray-100);color:var(--gray-700);';
@endphp

@include('survey_module.partials._flash')

@if ($issued)
    <div class="comp-type-note" style="margin-bottom:16px;">
        <i class="fas fa-lock"></i>
        Instruction <strong>{{ $its->its_number }}</strong> was issued on {{ optional($its->its_issued_at)->format('d M Y') }}
        by {{ $its->its_issued_by ?: '—' }}; it can no longer be edited.
        <a href="{{ route('survey-module.gkn.its.print', $its) }}" target="_blank">Print it</a>.
    </div>
@endif

<form method="POST" action="{{ $editing ? route('survey-module.gkn.its.update', $its) : route('survey-module.gkn.its.store') }}" id="itsForm">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-container">
        <div class="form-body">
            {{-- Three columns so the fields are not page-wide (user, 2026-11-03: "the form fields are too long"). --}}
            <div class="form-grid cols-3">
                <div class="sv-group"><i class="fas fa-folder-open"></i><span>File</span></div>
                {{-- The file comes from the global selector or the selected Computation File; a job keeps its file. --}}
                <div class="form-group span-2" id="itsPicker" data-lookup="{{ route('survey-module.gkn.its.lookup') }}">
                    <label>File Number</label>
                    <div style="display:flex;gap:8px;align-items:stretch;">
                        <input type="text" name="file_number" id="itsFileNumber" readonly
                               value="{{ old('file_number', $its->file_number) }}"
                               placeholder="{{ $editing ? '' : 'No file selected — use Select File Number' }}"
                               style="flex:1;font-weight:700;{{ $ro }}cursor:not-allowed;" />
                        @unless ($editing)
                            <button type="button" class="btn btn-primary" id="itsPick" style="white-space:nowrap;">
                                <i class="fas fa-folder-open"></i> Select File Number
                            </button>
                        @endunless
                    </div>
                    <p class="helper-text" id="itsPickStatus">
                        {{ $editing ? 'A survey job keeps its file.' : 'Select a Computation File to fill its file number, or use Select File Number.' }}
                    </p>
                </div>

                {{-- The Computation File this instruction is for (user, 2026-11-02); picking one fills any empty details below. --}}
                {{-- Also LPKN files already in File Indexing (user, 2026-11-03), posted as idx:<file number>. --}}
                @php
                    $cfPick = (string) old('survey_computation_file_id', $its->survey_computation_file_id
                        ?: ($its->computation_no ? 'idx:' . $its->computation_no : ''));
                    $cfIdx = collect($indexedLpkn ?? []);
                @endphp
                <div class="form-group">
                    <label>Computation File No.</label>
                    <select name="survey_computation_file_id" id="itsComputation" @disabled($locked)>
                        <option value="">— None —</option>
                        @foreach ($computationFiles ?? [] as $cf)
                            <option value="{{ $cf->id }}" @selected($cfPick === (string) $cf->id)
                                    data-file-number="{{ $cf->file_number }}"
                                    data-title="{{ $cf->file_title }}" data-plot="{{ $cf->plot_no }}" data-district="{{ $cf->district }}"
                                    data-lga="{{ $cf->lga }}" data-surveyor="{{ $cf->surveyors_list_id }}" data-type="{{ $cf->survey_type }}" data-beacons="{{ $cf->beacon_list }}">
                                {{ $cf->computation_no }}{{ $cf->file_number ? ' · ' . $cf->file_number : '' }}{{ $cf->file_title ? ' · ' . \Illuminate\Support\Str::limit($cf->file_title, 40) : '' }}
                            </option>
                        @endforeach
                        @if ($cfIdx->isNotEmpty())
                            <optgroup label="Existing LPKN files (File Indexing)">
                                @foreach ($cfIdx as $fi)
                                    <option value="idx:{{ $fi->file_number }}" @selected($cfPick === 'idx:' . $fi->file_number)
                                            data-file-number="{{ $fi->file_number }}"
                                            data-title="{{ $fi->file_title }}" data-plot="{{ $fi->plot_number }}" data-district="{{ $fi->district }}"
                                            data-lga="{{ $fi->lga }}" data-surveyor="">
                                        {{ $fi->file_number }}{{ $fi->file_title ? ' · ' . \Illuminate\Support\Str::limit($fi->file_title, 40) : '' }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($its->computation_no && $its->survey_computation_file_id && ! collect($computationFiles ?? [])->contains('id', $its->survey_computation_file_id))
                            <option value="{{ $its->survey_computation_file_id }}" selected>{{ $its->computation_no }}</option>
                        @elseif ($its->computation_no && ! $its->survey_computation_file_id && ! $cfIdx->contains('file_number', $its->computation_no))
                            <option value="idx:{{ $its->computation_no }}" selected>{{ $its->computation_no }}</option>
                        @endif
                    </select>
                    @error('survey_computation_file_id')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                    <p class="helper-text">Optional. LPKN Computation Files commissioned by Survey, and existing LPKN files from File Indexing.</p>
                </div>

                <div class="form-group span-2">
                    <label>File Title</label>
                    <input type="text" name="file_title" id="itsTitle" maxlength="500" value="{{ old('file_title', $its->file_title) }}" @disabled($locked) />
                </div>

                @if ($editing)
                    <div class="form-group">
                        <label>Survey Job No.</label>
                        <input type="text" value="{{ $its->job_ref }}" readonly style="{{ $ro }}" />
                    </div>
                @endif

            </div>

            <div class="form-grid cols-3">
                <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Property</span></div>
                {{-- Property order: Plot No., District, LGA, then Location (composed on save). --}}
                <div class="form-group">
                    <label>Plot No.</label>
                    <input type="text" name="plot_no" id="itsPlot" maxlength="100" value="{{ old('plot_no', $its->plot_no) }}" @disabled($locked) />
                </div>
                <div class="form-group">
                    <label>District</label>
                    <input type="text" name="district" id="itsDistrict" maxlength="255" value="{{ old('district', $its->district) }}" @disabled($locked) />
                </div>
                <div class="form-group">
                    <label>LGA</label>
                    <input type="text" name="lga" id="itsLga" maxlength="255" value="{{ old('lga', $its->lga) }}" @disabled($locked) />
                </div>

            </div>

            <div class="form-grid cols-3">
                <div class="sv-group"><i class="fas fa-user-tie"></i><span>Assignment</span></div>
                <div class="form-group">
                    <label>Surveyor <span class="required">*</span></label>
                    @php
                        // A surveyor typed under "Other (specify)" has a name but no List of Surveyors id.
                        $svPick = (string) old('surveyor_id', $its->surveyor_id ?: ($its->surveyor_name ? 'other' : ''));
                        $svOther = old('surveyor_other', $its->surveyor_id ? '' : $its->surveyor_name);
                    @endphp
                    <select name="surveyor_id" id="itsSurveyor" @disabled($locked)>
                        <option value="">— Select from the List of Surveyors —</option>
                        @foreach ($surveyors as $s)
                            <option value="{{ $s->id }}" @selected($svPick === (string) $s->id)>
                                {{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}{{ $s->is_active ? '' : ' (inactive)' }}
                            </option>
                        @endforeach
                        <option value="other" @selected($svPick === 'other')>Other (specify)</option>
                    </select>
                    <input type="text" name="surveyor_other" id="itsSurveyorOther" maxlength="255" value="{{ $svOther }}"
                           placeholder="Specify surveyor's full name *" aria-label="Specify surveyor" style="margin-top:6px;{{ $svPick === 'other' ? '' : 'display:none;' }}"
                           class="@error('surveyor_other') is-invalid @enderror"
                           @if ($svPick === 'other' && ! $locked) required @else disabled @endif />
                    @error('surveyor_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                    <p class="helper-text">Survey Department staff, or Other (specify). Needed before the instruction is issued.</p>
                </div>

                <div class="form-group">
                    <label>Job Scope</label>
                    <textarea name="job_scope" rows="3" maxlength="4000" placeholder="What the survey must cover…" @disabled($locked)>{{ old('job_scope', $its->job_scope) }}</textarea>
                </div>

                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="3" maxlength="2000" @disabled($locked)>{{ old('remarks', $its->remarks) }}</textarea>
                </div>
            </div>

            {{-- Survey type + beacons (CR 03112026 item 11). Searchable with Other, as on the Computation File (user, 2026-11-06). --}}
            @php
                $stNames = collect($surveyTypes ?? [])->pluck('name');
                $stSaved = $its->survey_type;
                $stPick = old('survey_type', $stSaved === null ? '' : ($stNames->contains($stSaved) ? $stSaved : 'Other'));
                $stOther = old('survey_type_other', $stPick === 'Other' ? $stSaved : '');
                $bPicks = collect(session()->hasOldInput() ? old('beacon_picks', []) : ($beaconList ?? []));
                $bOtherOn = $bPicks->contains('Other');
            @endphp
            <div class="form-grid cols-3">
                <div class="sv-group"><i class="fas fa-map-pin"></i><span>Survey Type &amp; Beacons</span></div>
                <div class="form-group">
                    <label>Survey Type</label>
                    <select name="survey_type" id="itsSurveyType" class="sv-searchable" @disabled($locked)
                            onchange="var o=document.getElementById('itsSurveyTypeOther'),on=this.value==='Other';o.style.display=on?'':'none';o.disabled=!on;o.required=on;">
                        <option value="">Select survey type…</option>
                        @foreach ($surveyTypes ?? [] as $st)
                            <option value="{{ $st->name }}" @selected($stPick === $st->name)>{{ $st->name }}</option>
                        @endforeach
                        <option value="Other" @selected($stPick === 'Other')>Other (specify)</option>
                    </select>
                    <input type="text" name="survey_type_other" id="itsSurveyTypeOther" maxlength="100" value="{{ $stOther }}" placeholder="Specify the survey type *" aria-label="Specify the survey type"
                           style="margin-top:6px;{{ $stPick === 'Other' ? '' : 'display:none;' }}" class="@error('survey_type_other') is-invalid @enderror"
                           @if ($stPick === 'Other' && ! $locked) required @else disabled @endif />
                    @error('survey_type_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                </div>
                <div class="form-group span-2">
                    <label>Beacon Numbers</label>
                    <style>#itsForm .select2-selection--multiple .select2-selection__choice { padding-left: 24px !important; }</style>
                    <select name="beacon_picks[]" id="itsBeacons" multiple data-no-search aria-label="Beacon Numbers" style="width:100%;" @disabled($locked)>
                        @foreach ($bPicks as $b)
                            <option value="{{ $b }}" selected>{{ $b === 'Other' ? 'Other (specify)' : $b }}</option>
                        @endforeach
                    </select>
                    <textarea name="beacon_other" id="itsBeaconOther" rows="2" maxlength="8000" placeholder="Type the numbers *, e.g. KDJ 0001 to KDJ 0004, A1404B" aria-label="Other beacon numbers"
                              style="margin-top:6px;{{ $bOtherOn ? '' : 'display:none;' }}" @if ($bOtherOn && ! $locked) required @else disabled @endif>{{ old('beacon_other') }}</textarea>
                    @error('beacon_picks')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                    @error('beacon_other')<p class="helper-text" style="color:var(--danger);">{{ $message }}</p>@enderror
                    <p class="helper-text" id="itsCfBeacons" style="display:none;"></p>
                    <p class="helper-text">Search the numbers free for this surveyor, or choose Other to type numbers and ranges. A number on another job, or issued to another surveyor, is refused.</p>
                </div>
            </div>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.gkn.its.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="right">
                    @unless ($locked)
                        <button class="btn btn-success" type="submit">
                            <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Issue I to S' }}
                        </button>
                    @endunless
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Issue: after the job is registered. The instruction text is offered and can be edited first. --}}
@if ($editing && ! $issued && ! $its->isClosed())
    <form method="POST" action="{{ route('survey-module.gkn.its.issue', $its) }}" style="margin-top:20px;"
          onsubmit="return confirm('Issue the instruction? It can no longer be edited afterwards.');">
        @csrf
        <div class="form-container">
            <div class="form-body">
                <h3 style="margin:0 0 10px;font-size:16px;font-weight:700;"><i class="fas fa-file-signature"></i> Issue the Instruction to Survey</h3>
                <div class="form-group full">
                    <label>Instruction <span class="required">*</span></label>
                    <textarea name="its_instructions" rows="5" maxlength="8000" required>{{ old('its_instructions', $its->its_instructions ?: $its->defaultInstructions()) }}</textarea>
                    <p class="helper-text">Issued to {{ $its->surveyor_name ?: '(no surveyor yet — choose one above and save)' }}. The I to S number and date are given on issue.</p>
                </div>
                <div class="form-actions">
                    <div class="left"></div>
                    <div class="right">
                        <button class="btn btn-primary" type="submit" @disabled(! $its->surveyor_name)><i class="fas fa-paper-plane"></i> Issue Instruction</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endif

@unless ($editing)
@once
@push('scripts')
    {{-- The shared selector, outside the .survey-proto wrapper so the prototype CSS cannot reach it. --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush
@endonce
@push('scripts')
<script>
(function () {
    'use strict';
    var root = document.getElementById('itsPicker');
    if (!root) return;
    var number = document.getElementById('itsFileNumber');
    var status = document.getElementById('itsPickStatus');
    var fields = { title: 'itsTitle', plot: 'itsPlot', district: 'itsDistrict', lga: 'itsLga' };
    var filled = [];   // fields this pick filled, cleared when the pick changes

    function say(text, isError) { status.textContent = text; status.style.color = isError ? '#b91c1c' : ''; }
    function clearFilled() {
        filled.forEach(function (el) { el.value = ''; el.readOnly = false; el.style.background = ''; });
        filled = [];
    }

    function apply(fileNumber) {
        clearFilled();
        number.value = fileNumber;
        say('Looking up ' + fileNumber + '…');
        fetch(root.dataset.lookup + '?file_number=' + encodeURIComponent(fileNumber), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                Object.keys(fields).forEach(function (k) {
                    var el = document.getElementById(fields[k]);
                    if (el && d[k]) { el.value = d[k]; el.readOnly = true; el.style.background = 'var(--gray-100)'; filled.push(el); }
                });
                var msg = d.found
                    ? fileNumber + ' selected. Details filled from the records; complete anything left blank.'
                    : fileNumber + ' selected. No indexed record holds its details, so enter them below.';
                if (d.open_jobs && d.open_jobs.length) msg += ' Note: open survey job(s) already exist for this file: ' + d.open_jobs.join(', ') + '.';
                say(msg, false);
            })
            .catch(function () { say(fileNumber + ' selected. Its details could not be loaded; enter them below.', true); });
    }

    document.getElementById('itsPick').addEventListener('click', function () {
        if (!window.GlobalFileNoModal) { say('The file number selector did not load. Refresh the page.', true); return; }
        window.GlobalFileNoModal.open({
            initialTab: 'gkn',
            allowedTabs: ['gkn'],
            callback: function (data) {
                var no = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                if (no) apply(no);
            }
        });
    });

})();
</script>
@endpush
@endunless

@push('scripts')
<script>
// Surveyor: searchable (Select2, as elsewhere in the module) with "Other (specify)",
// which reveals a required name box; any other choice hides, clears and disables it.
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('itsSurveyor');
    var box = document.getElementById('itsSurveyorOther');
    if (!sel || !box) return;
    function sync() {
        var on = sel.value === 'other';
        box.style.display = on ? '' : 'none';
        box.disabled = !on || sel.disabled;
        box.required = on && !sel.disabled;
        if (!on) box.value = '';
    }
    var $ = window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null;
    if ($) {
        // No dropdownParent: the default (<body>) keeps the panel clear of the scroll containers.
        $(sel).select2({ width: '100%', placeholder: '— Select from the List of Surveyors —' })
              .on('change', function () { sync(); if (sel.value === 'other') box.focus(); });
    } else {
        sel.addEventListener('change', sync);
    }
    sync();
});
</script>
@endpush

@push('scripts')
<script>
// Computation File No. (user, 2026-11-02): picking one fills the file number, title, plot, district, LGA and surveyor
// where they are still empty; nothing typed is overwritten.
document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('itsComputation');
    if (!sel) return;
    function fill() {
        var o = sel.options[sel.selectedIndex];
        if (!o || !o.value) return;
        if (o.dataset.fileNumber) {
            var fileNumber = document.getElementById('itsFileNumber');
            var status = document.getElementById('itsPickStatus');
            if (fileNumber) fileNumber.value = o.dataset.fileNumber;
            if (status) {
                status.textContent = 'File number filled from ' + o.textContent.trim().split(' · ')[0] + '.';
                status.style.color = '';
            }
        }
        [['itsTitle', 'title'], ['itsPlot', 'plot'], ['itsDistrict', 'district'], ['itsLga', 'lga']].forEach(function (p) {
            var el = document.getElementById(p[0]);
            if (el && !el.disabled && !el.value.trim() && o.dataset[p[1]]) el.value = o.dataset[p[1]];
        });
        var sv = document.getElementById('itsSurveyor');
        if (sv && !sv.disabled && !sv.value && o.dataset.surveyor && sv.querySelector('option[value="' + o.dataset.surveyor + '"]')) {
            sv.value = o.dataset.surveyor;
            if (window.jQuery) window.jQuery(sv).trigger('change');
        }
    }
    if (window.jQuery) window.jQuery(sel).on('change', fill); else sel.addEventListener('change', fill);
});
</script>
@endpush

@push('scripts')
<script>
// Beacon Numbers: search the register, or Other for typed numbers and ranges. select2 loads after this script.
window.addEventListener('load', function () {
    var $b = window.jQuery && jQuery.fn.select2 ? jQuery('#itsBeacons') : null;
    if (!$b || !$b.length) return;
    var other = document.getElementById('itsBeaconOther'), surveyor = document.getElementById('itsSurveyor'),
        surveyorOther = document.getElementById('itsSurveyorOther');
    $b.select2({
        width: '100%', placeholder: 'Search beacon numbers…', closeOnSelect: false,
        ajax: {
            url: @json(route('survey-module.gkn.its.beacon-search')), delay: 250,
            data: function (p) { return { q: p.term || '', surveyor: surveyor.value, surveyor_other: surveyorOther.value, its: @json($its->id) }; },
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

// Picking a Computation File fills its Survey Type and shows its beacons, which stay on that file. A new pick clears what the last one filled.
document.addEventListener('DOMContentLoaded', function () {
    var cf = document.getElementById('itsComputation'), type = document.getElementById('itsSurveyType'),
        typeOther = document.getElementById('itsSurveyTypeOther'), note = document.getElementById('itsCfBeacons');
    if (!cf || !type) return;
    var filled = '';
    function show() {
        var o = cf.options[cf.selectedIndex], beacons = o ? (o.getAttribute('data-beacons') || '') : '';
        note.textContent = beacons ? 'Already on this Computation File: ' + beacons + '.' : '';
        note.style.display = beacons ? '' : 'none';
    }
    function setType(value) {
        var listed = Array.prototype.some.call(type.options, function (x) { return x.value === value; });
        type.value = !value ? '' : (listed ? value : 'Other');
        typeOther.value = value && !listed ? value : '';
        type.dispatchEvent(new Event('change', { bubbles: true }));
    }
    cf.addEventListener('change', function () {
        var o = cf.options[cf.selectedIndex], want = o ? (o.getAttribute('data-type') || '') : '';
        var current = type.value === 'Other' ? typeOther.value : type.value;
        if (!type.disabled && (current === '' || current === filled)) { setType(want); filled = want; }
        show();
    });
    show();
});
</script>
@endpush
