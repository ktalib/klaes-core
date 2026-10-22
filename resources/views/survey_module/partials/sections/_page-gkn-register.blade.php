@php
    use App\Http\Controllers\Survey\GknController;

    $editing   = $gkn->exists;
    $suggested = $suggested ?? '';
@endphp

@include('survey_module.partials._flash')

<form method="POST"
      action="{{ $editing ? route('survey-module.gkn.update', $gkn) : route('survey-module.gkn.register.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="form-container">
        <div class="form-body">
            @if ($editing)
                <div class="comp-type-note" style="margin-bottom:16px;">
                    <i class="fas fa-pen"></i>
                    Editing <strong>{{ $gkn->gkn_number }}</strong>, registered
                    {{ optional($gkn->created_at)->format('Y-m-d') ?: 'previously' }}.
                </div>
            @endif

            <div class="form-grid">
                {{-- GKN Number comes from the global file number selector only (user, 2026-10-21);
                     an edit keeps its number. --}}
                <div class="form-group" id="gknPicker" data-lookup="{{ route('survey-module.gkn.lookup') }}">
                    <label>GKN File Number <span class="required">*</span></label>
                    <div style="display:flex;gap:8px;align-items:stretch;">
                        <input type="text" name="gkn_number" id="gknNumber" readonly
                               value="{{ old('gkn_number', $gkn->gkn_number) }}"
                               placeholder="{{ $editing ? '' : 'No file selected — use Select File Number' }}"
                               style="flex:1;font-weight:700;background:var(--gray-100);cursor:not-allowed;" />
                        @unless ($editing)
                            <button type="button" class="btn btn-primary" id="gknPick" style="white-space:nowrap;">
                                <i class="fas fa-folder-open"></i> Select File Number
                            </button>
                        @endunless
                    </div>
                    <p class="helper-text" id="gknPickStatus">
                        {{ $editing ? 'The GKN number cannot be changed on an existing parcel.' : 'Pick the GKN file; its title, plot and location are filled from the records.' }}
                    </p>
                </div>

                <div class="form-group">
                    <label>Title / Description <span class="required">*</span></label>
                    <input type="text" name="title" id="gknTitle" value="{{ old('title', $gkn->title) }}"
                           placeholder="Parcel name" maxlength="255" required />
                </div>

                <div class="form-group">
                    <label>Area (Ha)</label>
                    <input type="number" step="0.01" min="0" name="area_ha"
                           value="{{ old('area_ha', $gkn->area_ha) }}" placeholder="e.g. 2.40" />
                </div>

                <div class="form-group">
                    <label>Land Use <span class="required">*</span></label>
                    <select name="land_use">
                        @foreach (GknController::LAND_USES as $lu)
                            <option value="{{ $lu }}" @selected(old('land_use', $gkn->land_use ?? 'Residential') === $lu)>{{ $lu }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group">
                    <label>Survey Officer</label>
                    <input type="text" name="survey_officer" value="{{ old('survey_officer', $gkn->survey_officer) }}"
                           placeholder="Officer who surveyed the parcel" maxlength="255" />
                </div>

                <div class="form-group">
                    <label>Coordinates</label>
                    <input type="text" name="coordinates" value="{{ old('coordinates', $gkn->coordinates) }}"
                           placeholder="Lat, Long" maxlength="255" />
                </div>

                <div class="form-group">
                    <label>Date</label>
                    <input type="date" name="record_date"
                           value="{{ old('record_date', optional($gkn->record_date)->format('Y-m-d')) }}" />
                </div>

                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status">
                        @foreach (GknController::STATUSES as $s)
                            <option value="{{ $s }}" @selected(old('status', $gkn->status ?? 'Active') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group full">
                    <label>Remarks</label>
                    <textarea name="remarks" placeholder="Notes, boundaries, references…">{{ old('remarks', $gkn->remarks) }}</textarea>
                </div>
            </div>

            {{-- Parcel location: District, LGA, State — the plot number stays in its own field. --}}
            @include('survey_module.partials._address_builder', [
                'prefix' => 'prop_',
                'mode'   => 'property',
                'model'  => $gkn,
                'legend' => 'Parcel Location',
            ])

            <div class="form-actions">
                <div class="left">
                    <a class="btn btn-secondary" href="{{ route('survey-module.gkn.lands') }}">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                </div>
                <div class="right">
                    <button class="btn btn-success" type="submit">
                        <i class="fas fa-check"></i> {{ $editing ? 'Save Changes' : 'Register' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

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
    var root = document.getElementById('gknPicker');
    if (!root) return;
    var form = root.closest('form');
    var number = document.getElementById('gknNumber');
    var status = document.getElementById('gknPickStatus');
    var filled = [];   // fields this pick filled, cleared when the pick changes

    function $j() { return window.jQuery && window.jQuery.fn && window.jQuery.fn.select2 ? window.jQuery : null; }
    function field(name) { return form.querySelector('[name="' + name + '"]'); }
    function say(text, isError) { status.textContent = text; status.style.color = isError ? '#b91c1c' : ''; }

    function setSelect(el, value) {
        if (!el || !value) return false;
        var match = Array.prototype.find.call(el.options, function (o) {
            return o.value.toLowerCase() === String(value).toLowerCase();
        });
        if (!match) { match = new Option(value, value); el.add(match); }
        Array.prototype.forEach.call(el.options, function (o) { o.selected = (o === match); });
        var $ = $j();
        if ($ && $(el).data('select2')) $(el).trigger('change'); else el.dispatchEvent(new Event('change', { bubbles: true }));
        return true;
    }
    function setText(el, value) {
        if (!el || !value) return false;
        el.value = value;
        el.readOnly = true;
        el.style.background = 'var(--gray-100)';
        return true;
    }
    function clearFilled() {
        filled.forEach(function (f) {
            if (f.tagName === 'SELECT') {
                Array.prototype.forEach.call(f.options, function (o) { o.selected = false; });
                var $ = $j();
                if ($ && $(f).data('select2')) $(f).trigger('change');
            } else {
                f.value = ''; f.readOnly = false; f.style.background = '';
            }
        });
        filled = [];
    }

    function apply(fileNumber) {
        clearFilled();
        number.value = fileNumber;
        say('Looking up ' + fileNumber + '…');
        fetch(root.dataset.lookup + '?file_number=' + encodeURIComponent(fileNumber), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.registered) {
                    number.value = '';
                    status.innerHTML = '';
                    status.style.color = '#b91c1c';
                    status.append(fileNumber + ' is already on the register. ');
                    var a = document.createElement('a'); a.href = d.registered.edit_url; a.textContent = 'Open it';
                    status.append(a, '.');
                    return;
                }
                var t = document.getElementById('gknTitle');
                if (setText(t, d.title)) filled.push(t);
                var p = field('prop_plot');
                if (setText(p, d.plot)) filled.push(p);
                ['prop_district[]', 'prop_lga[]'].forEach(function (n, i) {
                    var el = field(n);
                    if (setSelect(el, i === 0 ? d.district : d.lga)) filled.push(el);
                });
                var lu = field('land_use');
                if (d.land_use) setSelect(lu, d.land_use);
                say(d.found
                    ? fileNumber + ' selected. Details filled from the records; complete anything left blank.'
                    : fileNumber + ' selected. No indexed record holds its details, so enter them below.');
            })
            .catch(function () { say(fileNumber + ' selected. Its details could not be loaded; enter them below.', true); });
    }

    document.getElementById('gknPick').addEventListener('click', function () {
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

    form.addEventListener('submit', function (e) {
        if (!number.value.trim()) {
            e.preventDefault();
            say('Select the GKN file number first.', true);
            number.scrollIntoView({ block: 'center' });
        }
    });
})();
</script>
@endpush
@endunless
