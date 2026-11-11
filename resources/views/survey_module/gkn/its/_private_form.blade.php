{{-- Add a Private I to S on the Private tab (user, 2026-11-10): the Survey Department's paper form keyed in, in the
     form's own order (the job, the field surveyor, the instruction, signed by), then printed on the same form.
     The file comes from the global file number selector and fills what the records hold (standard 5). --}}
@php
    $pvOpen = old('pv_form') && $errors->any();
    $pvSurveyors = \App\Models\Cadastral\CadastralSurveyor::query()->where('is_active', true)->orderBy('full_name')
        ->get(['id', 'full_name', 'firm_name', 'surcon_number', 'licence_status']);
    $pvPosts = (array) config('cadastral_module.posts');
    $pv = fn (string $f, $d = '') => old($f, $d);
@endphp
@if (session('its_print_url'))
    <div class="alert alert-success" style="margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:10px;">
        <span>The I to S is saved.</span>
        <a href="{{ session('its_print_url') }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Print I to S</a>
    </div>
@endif

<style>
    /* Full capitals (user, 2026-11-10), as they are saved; the Additional Instruction keeps its own case. */
    #pvItsModal input[type="text"], #pvItsModal .select2-selection__rendered, #pvItsModal .select2-results__option { text-transform: uppercase; }
    #pvItsModal input::placeholder, #pvItsModal .select2-selection__placeholder { text-transform: none; }
    /* A detail filled from the picked file is locked and greyed (user, 2026-11-10); it is still sent. */
    #pvItsModal input.pv-locked { background: var(--gray-100, #f3f4f6) !important; color: var(--gray-600, #4b5563) !important; cursor: not-allowed; }
    #pvItsModal .select2.pv-locked { pointer-events: none; }
    #pvItsModal .select2.pv-locked .select2-selection { background: var(--gray-100, #f3f4f6) !important; cursor: not-allowed; }
    #pvItsModal .select2.pv-locked .select2-selection__clear { display: none; }
</style>
{{-- Shown with style.display: the "flex" class would override a hidden attribute. --}}
<div id="pvItsModal" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black bg-opacity-40 p-6" style="{{ $pvOpen ? '' : 'display:none;' }}">
    <form method="POST" action="{{ route('survey-module.gkn.its.store-private') }}" class="form-container w-full" style="max-width:960px;margin:24px auto;" autocomplete="off">
        @csrf
        <input type="hidden" name="pv_form" value="1">
        <div class="card-header" style="position:relative;padding-right:56px;">
            <button type="button" data-pv-close title="Close" aria-label="Close"
                    style="position:absolute;top:14px;right:18px;font-size:22px;line-height:1;color:var(--gray-500);background:none;border:0;cursor:pointer;">&times;</button>
            <strong>Issue I to S</strong>
        </div>

        <div class="form-body">
            @if ($pvOpen)
                <div class="alert alert-error" style="margin-bottom:12px;">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif

            <div class="form-grid">
                <div class="form-group">
                    <label>File Number <span class="required">*</span></label>
                    <div style="display:flex;gap:6px;">
                        <input type="text" name="file_number" id="pvFileNo" value="{{ $pv('file_number') }}" readonly required placeholder="Pick from the file selector" style="background:var(--gray-100);" />
                        <button type="button" class="btn btn-secondary btn-sm" id="pvPick" title="Pick the file"><i class="fas fa-search"></i></button>
                        <button type="button" class="btn btn-outline btn-sm" id="pvClear" title="Clear"><i class="fas fa-times"></i></button>
                    </div>
                    <p class="helper-text" id="pvFileNote">Picking a file fills its details below.</p>
                </div>
                <div class="form-group">
                    <label>File Title</label>
                    <input type="text" name="file_title" id="pvTitle" value="{{ $pv('file_title') }}" maxlength="500" />
                </div>
                {{-- Property order: Plot No., District, LGA, Location. --}}
                <div class="form-group">
                    <label>Plot No.</label>
                    <input type="text" name="plot_no" id="pvPlot" value="{{ $pv('plot_no') }}" maxlength="100" />
                </div>
                {{-- Dropdowns searched from the lookups (user, 2026-11-10); Other on District reveals its box. --}}
                <div class="form-group">
                    <label>District</label>
                    <select name="district" id="pvDistrict" data-no-search data-placeholder="Search district…" style="width:100%;">
                        <option value=""></option>
                        @if ($pv('district'))<option value="{{ $pv('district') }}" selected>{{ mb_strtoupper($pv('district')) }}</option>@endif
                    </select>
                    <input type="text" name="district_other" id="pvDistrictOther" value="{{ $pv('district_other') }}" maxlength="255" placeholder="Specify the district *"
                           style="margin-top:6px;{{ $pv('district') === 'Other' ? '' : 'display:none;' }}" @disabled($pv('district') !== 'Other') />
                </div>
                <div class="form-group">
                    <label>LGA</label>
                    <select name="lga" id="pvLga" data-no-search data-placeholder="Search LGA…" style="width:100%;">
                        <option value=""></option>
                        @if ($pv('lga'))<option value="{{ $pv('lga') }}" selected>{{ mb_strtoupper($pv('lga')) }}</option>@endif
                    </select>
                    <input type="text" name="lga_other" id="pvLgaOther" value="{{ $pv('lga_other') }}" maxlength="255" placeholder="Specify the LGA *"
                           style="margin-top:6px;{{ $pv('lga') === 'Other' ? '' : 'display:none;' }}" @disabled($pv('lga') !== 'Other') />
                </div>
                <div class="form-group">
                    <label>Location (auto)</label>
                    <input type="text" id="pvLocation" readonly tabindex="-1" style="background:var(--gray-100);" placeholder="District, LGA" />
                </div>
            </div>

            <div class="ab-legend" style="margin:14px 0 8px;">To: The Field Surveyor</div>
            <div class="form-grid">
                <div class="form-group full">
                    <label>Surveyor <span class="required">*</span></label>
                    <select name="cadastral_surveyor_id" class="sv-searchable" data-placeholder="Choose the surveyor…" required>
                        <option value=""></option>
                        @foreach ($pvSurveyors as $s)
                            <option value="{{ $s->id }}" @selected((string) $pv('cadastral_surveyor_id') === (string) $s->id) @disabled($s->licence_status !== 'Active')>
                                {{ $s->full_name }}{{ $s->firm_name ? ' — ' . $s->firm_name : '' }}{{ $s->surcon_number ? ' (' . $s->surcon_number . ')' : '' }}{{ $s->licence_status !== 'Active' ? ' · licence ' . $s->licence_status : '' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="helper-text">The SURCON directory. Only a current licence may receive an I to S.</p>
                </div>
            </div>

            <div class="ab-legend" style="margin:14px 0 8px;">Instruction to Survey</div>
            <p class="helper-text" style="margin:0 0 8px;">Item 1 (Technical Instructions, Cadastral ties) and item 4 (the attached plan) print as on the form.</p>
            <div class="form-grid">
                <div class="form-group">
                    <label>2. Road reservation: parallel to and … from the centre line of the road</label>
                    <input type="text" name="its_road_reservation" value="{{ $pv('its_road_reservation') }}" maxlength="100" placeholder="e.g. 50 feet" />
                </div>
                <div class="form-group">
                    <label>3. Rail reservation: parallel to and … from the centre line of the railway</label>
                    <input type="text" name="its_rail_reservation" value="{{ $pv('its_rail_reservation') }}" maxlength="100" placeholder="e.g. 50 feet" />
                </div>
                <div class="form-group full">
                    <label>Additional Instruction</label>
                    <textarea name="its_instructions" rows="4" maxlength="8000" placeholder="Printed on page 2 under Additional Instruction">{{ $pv('its_instructions') }}</textarea>
                </div>
            </div>

            <div class="ab-legend" style="margin:14px 0 8px;">Signed by</div>
            <div class="form-grid">
                <div class="form-group">
                    <label>Name <span class="required">*</span></label>
                    <input type="text" name="its_issued_by" value="{{ $pv('its_issued_by', auth()->user()->name ?? '') }}" maxlength="255" required />
                </div>
                <div class="form-group">
                    <label>Post</label>
                    <select name="its_officer_post">
                        <option value="">—</option>
                        @foreach ($pvPosts as $k => $label)
                            <option value="{{ $k }}" @selected($pv('its_officer_post') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" name="its_issued_at" value="{{ $pv('its_issued_at', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required />
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-secondary" data-pv-close>Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Issue I to S</button>
        </div>
    </form>
</div>

@push('scripts')
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
(function () {
    var modal = document.getElementById('pvItsModal');
    if (!modal) return;
    var shown = function () { return modal.style.display !== 'none'; };
    var open = function (on) { modal.style.display = on ? 'flex' : 'none'; document.body.style.overflow = on ? 'hidden' : ''; };
    document.querySelectorAll('[data-pv-open]').forEach(function (b) { b.addEventListener('click', function (e) { e.preventDefault(); open(true); }); });
    modal.querySelectorAll('[data-pv-close]').forEach(function (b) { b.addEventListener('click', function () { open(false); }); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && shown() && !document.querySelector('#global-fileno-modal.show, #global-fileno-modal[style*="block"]')) open(false); });
    if (shown()) document.body.style.overflow = 'hidden';

    // The file from the global selector fills what the records hold; a new pick clears what the last one filled.
    var no = document.getElementById('pvFileNo'), note = document.getElementById('pvFileNote');
    var fill = { title: 'pvTitle', plot: 'pvPlot', district: 'pvDistrict', lga: 'pvLga' }, filled = {};
    var loc = document.getElementById('pvLocation'), district = document.getElementById('pvDistrict'),
        districtOther = document.getElementById('pvDistrictOther'), lga = document.getElementById('pvLga'), lgaOther = document.getElementById('pvLgaOther');
    function named(sel, other) { return sel.value === 'Other' ? other.value : sel.value; }
    function syncLocation() {
        var parts = [named(district, districtOther), named(lga, lgaOther)].map(function (v) { return String(v || '').trim().toUpperCase(); }).filter(Boolean);
        loc.value = parts.join(', ');   // never the plot
    }
    function syncOther() {
        [[district, districtOther], [lga, lgaOther]].forEach(function (p) {
            var on = p[0].value === 'Other';
            p[1].style.display = on ? '' : 'none';
            p[1].disabled = !on;
            p[1].required = on;
            if (!on) p[1].value = '';
        });
    }
    [districtOther, lgaOther].forEach(function (el) { el.addEventListener('input', syncLocation); });
    // A value for a dropdown: added as an option when the list has not loaded it.
    function setValue(el, value) {
        if (el.tagName !== 'SELECT') { el.value = value; return; }
        if (value && !Array.prototype.some.call(el.options, function (o) { return o.value === value; })) el.add(new Option(String(value).toUpperCase(), value, true, true));
        el.value = value;
        if (window.jQuery && jQuery(el).data('select2')) jQuery(el).trigger('change');
        else el.dispatchEvent(new Event('change'));
    }
    window.addEventListener('load', function () {
        if (!(window.jQuery && jQuery.fn && jQuery.fn.select2)) {
            [district, lga].forEach(function (s) { s.addEventListener('change', function () { syncOther(); syncLocation(); }); });
            return;
        }
        [[district, @json(route('survey-module.lookup.districts'))], [lga, @json(route('survey-module.lookup.lgas'))]].forEach(function (pair) {
            var $s = jQuery(pair[0]);
            $s.select2({
                width: '100%', allowClear: true, placeholder: $s.data('placeholder'), dropdownParent: jQuery('#pvItsModal'),
                ajax: {
                    url: pair[1], dataType: 'json', delay: 250, cache: true,
                    data: function (p) { return { q: p.term || '', page: p.page || 1 }; },
                    processResults: function (d) { return d; }
                },
                templateResult: function (o) { return o.text ? String(o.text).toUpperCase() : o.text; },
                templateSelection: function (o) { return o.text ? String(o.text).toUpperCase() : o.text; }
            }).on('change', function () { syncOther(); syncLocation(); });
        });
    });
    syncLocation();
    // Locked, not disabled: a disabled field would not be sent with the form.
    function lock(el, on) {
        if (el.tagName === 'SELECT') {
            var box = el.nextElementSibling && el.nextElementSibling.classList.contains('select2') ? el.nextElementSibling : null;
            if (box) box.classList.toggle('pv-locked', on);
            el.setAttribute('aria-readonly', on ? 'true' : 'false');
            el.tabIndex = on ? -1 : 0;
        } else {
            el.readOnly = on;
            el.classList.toggle('pv-locked', on);
            el.tabIndex = on ? -1 : 0;
        }
    }
    function clearFilled() {
        Object.keys(filled).forEach(function (k) {
            var el = document.getElementById(fill[k]);
            if (!el) return;
            lock(el, false);
            if (el.value === filled[k]) setValue(el, '');
        });
        filled = {};
        syncLocation();
    }
    function lookup(fileNo) {
        fetch(@json(route('survey-module.compensation.co-files.related-lookup')) + '?file_number=' + encodeURIComponent(fileNo), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                Object.keys(fill).forEach(function (k) {
                    var el = document.getElementById(fill[k]);
                    if (el && d[k] && !el.value) { setValue(el, d[k]); filled[k] = d[k]; lock(el, true); }
                });
                syncLocation();
                note.textContent = d.indexed ? 'Indexed file: its details are filled in from the records and locked. Clear the file to change them.' : (d.known ? 'On the file number register, not yet indexed.' : 'Not in the records yet; enter the details.');
            })
            .catch(function () { note.textContent = 'The file details could not be fetched; enter them.'; });
    }
    document.getElementById('pvPick').addEventListener('click', function () {
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
    document.getElementById('pvClear').addEventListener('click', function () { clearFilled(); no.value = ''; note.textContent = 'Picking a file fills its details below.'; });
})();
</script>
@endpush
