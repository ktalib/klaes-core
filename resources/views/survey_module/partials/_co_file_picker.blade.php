{{--
    CO file picker (user, 2026-10-30: "use global file number selector for the co file").
    Opens the global file number selector on its CO tab; the number picked is looked up,
    and the form posts survey_co_file_id (the file found) plus co_file_no (as picked).

    Params:
        coCurrent   SurveyCoFile|null   the file already linked (edit, or ?co_file=)
        coRequired  bool                a CO file must be picked
        coAllowNew  bool                a number not yet in KLAES is accepted (captured on save);
                                        otherwise it must already be registered and not Closed
        coLabel     string              label text
--}}
@php
    $coCurrent  = $coCurrent ?? null;
    $coRequired = $coRequired ?? false;
    $coAllowNew = $coAllowNew ?? false;
    $coNo = old('co_file_no', $coCurrent?->co_file_no);
    $coId = old('survey_co_file_id', $coCurrent?->id);
@endphp

<div class="form-group co-picker"
     data-lookup="{{ route('survey-module.compensation.co-files.number-lookup') }}"
     data-allow-new="{{ $coAllowNew ? 1 : 0 }}"
     data-current="{{ $coCurrent?->id }}"
     data-capture-url="{{ route('survey-module.compensation.co-files.create', ['mode' => 'captured']) }}">
    <label>{{ $coLabel ?? 'CO File' }} @if ($coRequired)<span class="required">*</span>@endif</label>
    <div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap;">
        <input type="text" name="co_file_no" class="co-picker-no" readonly value="{{ $coNo }}"
               placeholder="No CO file selected"
               style="flex:0 1 190px;min-width:140px;font-weight:700;background:var(--gray-100);cursor:not-allowed;" />
        <input type="hidden" name="survey_co_file_id" class="co-picker-id" value="{{ $coId }}" />
        <button type="button" class="btn btn-primary co-picker-pick" style="white-space:nowrap;">
            <i class="fas fa-folder-open"></i> Select CO File
        </button>
        @unless ($coRequired)
            <button type="button" class="btn btn-secondary co-picker-clear" style="white-space:nowrap;">
                <i class="fas fa-times"></i> Clear
            </button>
        @endunless
    </div>
    @error('survey_co_file_id')<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
    @error('co_file_no')<p class="helper-text" style="color:#b91c1c;">{{ $message }}</p>@enderror
    <p class="helper-text co-picker-status">
        {{ $coAllowNew ? 'Pick the CO number (CO-serial-year). One not yet in KLAES is captured when you save.' : 'Pick the CO number (CO-serial-year). It must already be commissioned or captured.' }}
    </p>
</div>

@once
@push('scripts')
    {{-- The shared selector, outside the .survey-proto wrapper so the prototype CSS cannot reach it. --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}?v={{ @filemtime(public_path('js/global-fileno-modal.js')) }}"></script>
    <script>
    (function () {
        'use strict';
        function esc(t) { var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }

        document.querySelectorAll('.co-picker').forEach(function (root) {
            var no = root.querySelector('.co-picker-no');
            var id = root.querySelector('.co-picker-id');
            var status = root.querySelector('.co-picker-status');
            var allowNew = root.dataset.allowNew === '1';
            var defaultText = status.textContent;

            function say(html, bad) { status.innerHTML = html; status.style.color = bad ? '#b91c1c' : ''; }

            function check(number) {
                say(esc(number) + ': checking…');
                return fetch(root.dataset.lookup + '?co_file_no=' + encodeURIComponent(number), { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (!d.number || !d.valid) {
                            id.value = ''; no.value = '';
                            say(esc(d.message || (number + ' is not a valid CO number.')), true);
                            return;
                        }
                        no.value = d.number;
                        if (d.exists) {
                            var closed = d.status === 'Closed' && String(d.id) !== root.dataset.current;
                            if (closed && !allowNew) {
                                id.value = '';
                                say('<strong>' + esc(d.number) + '</strong> is Closed; pick an open CO file.', true);
                                return;
                            }
                            id.value = d.id;
                            say('<strong>' + esc(d.number) + '</strong> · ' + esc(d.title) + ' · ' + esc(d.status)
                                + ' · ' + d.projects + ' project' + (d.projects === 1 ? '' : 's'));
                        } else {
                            id.value = '';
                            if (allowNew) {
                                say('<strong>' + esc(d.number) + '</strong> is not in KLAES yet; it will be captured when you save.');
                            } else {
                                no.value = '';
                                say('<strong>' + esc(d.number) + '</strong> is not registered. <a href="' + root.dataset.captureUrl
                                    + '" target="_blank">Capture it</a> or commission it first.', true);
                            }
                        }
                    })
                    .catch(function () { say('Could not check ' + esc(number) + '. Try again.', true); });
            }

            root.querySelector('.co-picker-pick').addEventListener('click', function () {
                if (!window.GlobalFileNoModal) { say('The file number selector did not load. Refresh the page.', true); return; }
                window.GlobalFileNoModal.open({
                    initialTab: 'co',
                    allowedTabs: ['co'],
                    callback: function (data) {
                        var n = data && data.fileNumber ? String(data.fileNumber).trim() : '';
                        if (n) check(n);
                    }
                });
            });
            var clear = root.querySelector('.co-picker-clear');
            if (clear) clear.addEventListener('click', function () { no.value = ''; id.value = ''; say(esc(defaultText)); });

            if (no.value.trim()) check(no.value.trim());
        });
    })();
    </script>
@endpush
@endonce
