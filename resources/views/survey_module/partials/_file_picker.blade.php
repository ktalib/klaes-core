{{--
    File number from the global file number selector (never typed), with the
    record's details filled into the form. Used by Examination registers.

    @include('survey_module.partials._file_picker', [
        'id'     => 'bd',                 // unique prefix on the page
        'name'   => 'file_number',        // input name
        'value'  => $model->file_number,
        'label'  => 'File Number',
        'fill'   => ['title' => 'bdTitle', 'plot' => 'bdPlot', 'district' => 'bdDistrict', 'lga' => 'bdLga'],  // lookup key => input id
        'locked' => false,                // true: show the number, no picker
        'required' => true,
    ])
--}}
@php
    $required = $required ?? false;
    $locked = $locked ?? false;
    $fill = $fill ?? [];
@endphp
<div class="form-group" id="{{ $id }}Picker" data-lookup="{{ route('survey-module.lookup.file') }}" data-fill='@json($fill)'>
    <label>{{ $label ?? 'File Number' }} @if ($required)<span class="required">*</span>@endif</label>
    <div style="display:flex;gap:8px;align-items:stretch;">
        <input type="text" name="{{ $name }}" id="{{ $id }}FileNumber" readonly value="{{ old($name, $value) }}"
               placeholder="{{ $locked ? '' : 'Use Select File Number' }}"
               style="flex:1;font-weight:700;background:var(--gray-100);cursor:not-allowed;" />
        @unless ($locked)
            <button type="button" class="btn btn-primary" data-pick="{{ $id }}" style="white-space:nowrap;"><i class="fas fa-folder-open"></i> Select File Number</button>
            <button type="button" class="btn btn-outline btn-sm" data-clear="{{ $id }}" title="Clear the file" aria-label="Clear the file"><i class="fas fa-times"></i></button>
        @endunless
    </div>
    <p class="helper-text" id="{{ $id }}PickStatus">{{ $locked ? '' : 'Picked from the file number selector; its details are filled from the records.' }}</p>
</div>

@unless ($locked)
@once
@push('scripts')
    {{-- The shared selector, outside the .survey-proto wrapper so the prototype CSS cannot reach it. --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script>
    (function () {
        'use strict';
        var state = {};   // picker id -> inputs this pick filled
        function say(id, text, bad) { var s = document.getElementById(id + 'PickStatus'); if (s) { s.textContent = text; s.style.color = bad ? '#b91c1c' : ''; } }
        function clearFilled(id) {
            (state[id] || []).forEach(function (el) { el.value = ''; el.readOnly = false; el.style.background = ''; });
            state[id] = [];
        }
        function apply(id, no) {
            var root = document.getElementById(id + 'Picker');
            var fill = JSON.parse(root.dataset.fill || '{}');
            clearFilled(id);
            document.getElementById(id + 'FileNumber').value = no;
            say(id, 'Looking up ' + no + '…');
            fetch(root.dataset.lookup + '?file_number=' + encodeURIComponent(no), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    Object.keys(fill).forEach(function (k) {
                        var el = document.getElementById(fill[k]);
                        if (el && d[k]) { el.value = d[k]; el.readOnly = true; el.style.background = 'var(--gray-100)'; state[id].push(el); }
                    });
                    say(id, d.found ? no + ' selected. Details filled from the records.' : no + ' selected. No indexed record holds its details; enter them.');
                })
                .catch(function () { say(id, no + ' selected. Its details could not be loaded; enter them.', true); });
        }
        document.addEventListener('click', function (e) {
            var pick = e.target.closest('[data-pick]');
            if (pick) {
                var id = pick.getAttribute('data-pick');
                if (!window.GlobalFileNoModal) { say(id, 'The file number selector did not load. Refresh the page.', true); return; }
                window.GlobalFileNoModal.open({ callback: function (data) {
                    var no = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                    if (no) apply(id, no);
                } });
            }
            var clr = e.target.closest('[data-clear]');
            if (clr) {
                var cid = clr.getAttribute('data-clear');
                clearFilled(cid);
                document.getElementById(cid + 'FileNumber').value = '';
                say(cid, 'No file selected.');
            }
        });
    })();
    </script>
@endpush
@endonce
@endunless
