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
            <div class="form-grid">
                <div class="sv-group"><i class="fas fa-folder-open"></i><span>File</span></div>
                {{-- The file comes from the global file number selector only (GKN, LPKN, Misc KN); a job keeps its file. --}}
                <div class="form-group full" id="itsPicker" data-lookup="{{ route('survey-module.gkn.its.lookup') }}">
                    <label>File Number <span class="required">*</span></label>
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
                        {{ $editing ? 'A survey job keeps its file.' : 'Pick the GKN, LPKN or Misc KN file; its title, plot and location are filled from the records.' }}
                    </p>
                </div>

                @if ($editing)
                    <div class="form-group">
                        <label>Survey Job No.</label>
                        <input type="text" value="{{ $its->job_ref }}" readonly style="{{ $ro }}" />
                    </div>
                @endif

                <div class="form-group full">
                    <label>File Title</label>
                    <input type="text" name="file_title" id="itsTitle" maxlength="500" value="{{ old('file_title', $its->file_title) }}" @disabled($locked) />
                </div>

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

            <div class="form-grid">
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

                <div class="form-group full">
                    <label>Job Scope</label>
                    <textarea name="job_scope" maxlength="4000" placeholder="What the survey must cover…" @disabled($locked)>{{ old('job_scope', $its->job_scope) }}</textarea>
                </div>

                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" maxlength="2000" @disabled($locked)>{{ old('remarks', $its->remarks) }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.gkn.its.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
                </div>
                <div class="right">
                    @unless ($locked)
                        <button class="btn btn-success" type="submit">
                            <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Register Survey Job' }}
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

    document.getElementById('itsForm').addEventListener('submit', function (e) {
        if (!number.value.trim()) {
            e.preventDefault();
            say('Select the file number first.', true);
            number.scrollIntoView({ block: 'center' });
        }
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
