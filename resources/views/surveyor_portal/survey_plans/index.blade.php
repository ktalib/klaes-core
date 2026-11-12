@extends('surveyor_portal.layouts.portal')

@section('title', 'Survey Plan Upload — MLPP-SG Portal')
@section('heading', 'Survey Plan Upload')

@php
    use App\Models\Survey\SurveyPlanUpload as Plan;
    $district = old('district');
@endphp

@section('main')
    {{-- Survey Update 5g/5h (user, 2026-11-10): the very short form. The unit chosen receives the plan in KLAES. --}}
    <form method="POST" id="spPortalForm" action="{{ route('surveyor-portal.survey-plans.store') }}" enctype="multipart/form-data" class="ks-card p-6 space-y-4">
        @csrf
        <p class="ks-help" style="margin:0;">Send a survey plan for checking: GKN for public plans, Examination/Verification for private.</p>
        {{-- Two tabs (CR 11112026-2, item 4): the plan's details, and its reference beacons. --}}
        @php $spBeaconErr = collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'beacons')); @endphp
        <div role="tablist" style="display:flex;gap:6px;border-bottom:1px solid #e5e7eb;">
            <button type="button" role="tab" data-sp-tab="details" class="sp-ptab" aria-selected="{{ $spBeaconErr ? 'false' : 'true' }}">Plan Details</button>
            <button type="button" role="tab" data-sp-tab="beacons" class="sp-ptab" aria-selected="{{ $spBeaconErr ? 'true' : 'false' }}">Reference Beacons <span class="ks-req">*</span></button>
        </div>
        <style>
            .sp-ptab { padding: 8px 14px; border: 0; border-bottom: 2px solid transparent; background: none; font-weight: 700; color: #6b7280; cursor: pointer; }
            .sp-ptab[aria-selected="true"] { color: var(--ks-blue, #1d4ed8); border-bottom-color: var(--ks-blue, #1d4ed8); }
            .ks-searchable + .select2-container { width: 100% !important; }
            .ks-searchable + .select2-container .select2-selection--single { height: 44px; border: 1px solid #B8BEC5; border-radius: 8px; }
            .ks-searchable + .select2-container .select2-selection__rendered { line-height: 42px; padding-left: 12px; color: var(--ks-ink); font-size: 14px; }
            .ks-searchable + .select2-container .select2-selection__arrow { height: 42px; }
            .sp-preview { border: 1px solid var(--ks-line); border-radius: 10px; overflow: hidden; }
            .sp-preview-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 12px; background: #F5F7FA; border-bottom: 1px solid var(--ks-line); font-size: 13px; }
            .sp-preview-head > span { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; min-width: 0; word-break: break-all; }
            .sp-preview-body { background: #fff; }
            .sp-preview-body iframe { display: block; width: 100%; height: 520px; border: 0; }
            .sp-preview-body img { display: block; max-width: 100%; max-height: 520px; margin: 0 auto; }
            .sp-preview-note { padding: 18px; color: var(--ks-soft); font-size: 13px; text-align: center; }
            @media (max-width: 640px) { .sp-preview-body iframe { height: 380px; } }
        </style>
        <div data-sp-panel="beacons" @unless ($spBeaconErr) hidden @endunless>
            @include('survey_module.survey_plans._reference_beacons', ['searchUrl' => route('surveyor-portal.survey-plans.beacons')])
        </div>
        <div data-sp-panel="details" class="space-y-4" @if ($spBeaconErr) hidden @endif>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="plan_type">Type <span class="ks-req">*</span></label>
                <select class="ks-input" id="plan_type" name="plan_type" required>
                    <option value="">Select…</option>
                    @foreach (Plan::TYPES as $t)<option value="{{ $t }}" @selected(old('plan_type') === $t)>{{ $t }}</option>@endforeach
                </select>
                @error('plan_type')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="unit">Send To <span class="ks-req">*</span></label>
                <select class="ks-input" id="unit" name="unit" required>
                    <option value="">Select unit…</option>
                    @foreach (Plan::UNITS as $u => $label)<option value="{{ $u }}" @selected(old('unit') === $u)>{{ $label }}</option>@endforeach
                </select>
                @error('unit')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="ks-label" for="plot_no">Plot No.</label>
                <input class="ks-input" id="plot_no" name="plot_no" value="{{ old('plot_no') }}" maxlength="100">
            </div>
            <div>
                <label class="ks-label" for="spDistrict">District <span class="ks-req">*</span></label>
                <select class="ks-input ks-searchable" id="spDistrict" name="district" data-placeholder="Search district…" required>
                    <option value="">Select district…</option>
                    @foreach ($districts as $d)<option value="{{ $d }}" @selected($district === $d)>{{ $d }}</option>@endforeach
                    <option value="other" @selected($district === 'other')>Other (specify)</option>
                </select>
                <input class="ks-input" id="spDistrictOther" name="district_other" value="{{ old('district_other') }}" maxlength="150" placeholder="Specify district"
                       style="margin-top:6px;{{ $district === 'other' ? '' : 'display:none;' }}" @disabled($district !== 'other') @required($district === 'other')>
                @error('district')<p class="ks-error">{{ $message }}</p>@enderror
                @error('district_other')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="lga">LGA <span class="ks-req">*</span></label>
                <select class="ks-input" id="lga" name="lga" required>
                    <option value="">Select LGA…</option>
                    @foreach ($lgas as $l)<option value="{{ $l }}" @selected(old('lga') === $l)>{{ $l }}</option>@endforeach
                </select>
                @error('lga')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="drawn_by">Drawn By <span class="ks-req">*</span></label>
                <input class="ks-input" id="drawn_by" name="drawn_by" value="{{ old('drawn_by', $me->name) }}" maxlength="255" required>
                <p class="ks-help" style="margin-top:4px;">Who drew the plan. Change it if it was not you.</p>
                @error('drawn_by')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="plan_file">Plan File <span class="ks-req">*</span></label>
                <input class="ks-input" id="plan_file" type="file" name="plan_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required>
                <p class="ks-help" style="margin-top:4px;">PDF, JPG, PNG or TIFF; at most 25 MB.</p>
                @error('plan_file')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        {{-- Preview of the chosen plan before it is sent (user, 2026-11-12). PDF and images show in place; a TIFF cannot be shown by the browser. --}}
        <div id="spPreview" class="sp-preview" hidden>
            <div class="sp-preview-head">
                <span><i data-lucide="eye" class="w-4 h-4"></i> Preview: <strong id="spPreviewName"></strong> <span id="spPreviewSize" class="ks-help" style="margin:0;"></span></span>
                <button type="button" class="ks-btn" id="spPreviewClear"><i data-lucide="x" class="w-4 h-4"></i> Remove</button>
            </div>
            <div id="spPreviewBody" class="sp-preview-body"></div>
        </div>
        <div>
            <label class="ks-label" for="remarks">Remarks</label>
            <textarea class="ks-input" id="remarks" name="remarks" rows="2" maxlength="1000">{{ old('remarks') }}</textarea>
        </div>
        </div>
        <div class="flex justify-end">
            <button class="ks-btn ks-btn-primary"><i data-lucide="send" class="w-4 h-4"></i> Upload and Send</button>
        </div>
    </form>

    <h2 style="margin:22px 0 10px;font-size:16px;font-weight:800;">My uploads</h2>
    <div class="ks-card overflow-x-auto">
        <table class="ks-table">
            <thead>
                <tr><th>S/N</th><th>Upload Ref</th><th>Type</th><th>Sent To</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Drawn By</th><th>Uploaded</th><th>Stage</th><th>Plan</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $p)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;font-weight:600;">{{ $p->upload_ref }}</td>
                        <td>{{ $p->plan_type }}</td>
                        <td>{{ $p->unit }}</td>
                        <td>{{ $p->plot_no ?: '—' }}</td>
                        <td>{{ $p->district ?: '—' }}</td>
                        <td>{{ $p->lga ?: '—' }}</td>
                        <td>{{ $p->location ?: '—' }}</td>
                        <td>{{ $p->drawn_by }}</td>
                        <td style="white-space:nowrap;">{{ optional($p->created_at)->format('d/m/Y') }}</td>
                        <td style="white-space:nowrap;">{{ $p->stage_no }} · {{ $p->stage_label }}<div class="ks-help" style="margin:0;">{{ $p->stage_status }}</div></td>
                        <td style="white-space:nowrap;"><a href="{{ route('surveyor-portal.survey-plans.show', $p->id) }}" style="color:var(--ks-blue);font-weight:600;">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="ks-help" style="text-align:center;padding:24px;">No survey plans uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// Plan Details | Reference Beacons tabs; a missing field on the hidden tab opens it.
(function () {
    var form = document.getElementById('spPortalForm'), tabs = document.querySelectorAll('[data-sp-tab]');
    var show = function (key) {
        tabs.forEach(function (t) { t.setAttribute('aria-selected', t.dataset.spTab === key ? 'true' : 'false'); });
        document.querySelectorAll('[data-sp-panel]').forEach(function (p) { p.hidden = p.dataset.spPanel !== key; });
    };
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.spTab); }); });
    form.addEventListener('invalid', function (e) { var p = e.target.closest('[data-sp-panel]'); if (p && p.hidden) show(p.dataset.spPanel); }, true);
    form.addEventListener('submit', function (e) {
        var filled = Array.prototype.some.call(form.querySelectorAll('.rb-no'), function (i) { return i.value.trim() !== ''; });
        if (!filled) { e.preventDefault(); show('beacons'); var rb = form.querySelector('.rb-no'); if (window.jQuery && jQuery(rb).data('select2')) jQuery(rb).select2('open'); else rb.focus(); }
    });
})();
// District: a searchable list (user, 2026-11-12). "Other (specify)" reveals a required box; any other choice hides, clears and disables it.
(function () {
    var pick = document.getElementById('spDistrict'), box = document.getElementById('spDistrictOther');
    var sync = function () {
        var on = pick.value === 'other';
        box.style.display = on ? '' : 'none';
        box.disabled = !on; box.required = on;
        if (on) box.focus(); else box.value = '';
    };
    if (window.jQuery && jQuery.fn.select2) {
        // "Other (specify)" stays in the list whatever is typed, so an unlisted district can always be given.
        var matcher = jQuery.fn.select2.defaults.defaults.matcher;
        jQuery(pick).select2({
            width: '100%', placeholder: pick.dataset.placeholder || '',
            matcher: function (params, data) { return data.id === 'other' ? data : matcher(params, data); },
        }).on('change', sync);
    } else {
        pick.addEventListener('change', sync);
    }
})();
// Preview of the chosen plan file: PDF and images in place; anything else (TIFF) by name and size only.
(function () {
    var input = document.getElementById('plan_file'), wrap = document.getElementById('spPreview'), body = document.getElementById('spPreviewBody');
    var url = null;
    var clear = function () {
        if (url) { URL.revokeObjectURL(url); url = null; }
        body.innerHTML = ''; wrap.hidden = true;
    };
    input.addEventListener('change', function () {
        clear();
        var f = input.files && input.files[0];
        if (!f) return;
        document.getElementById('spPreviewName').textContent = f.name;
        document.getElementById('spPreviewSize').textContent = '(' + (f.size / 1048576).toFixed(2) + ' MB)';
        var type = (f.type || '').toLowerCase(), name = f.name.toLowerCase();
        url = URL.createObjectURL(f);
        if (type === 'application/pdf' || /\.pdf$/.test(name)) {
            var frame = document.createElement('iframe');
            frame.src = url; frame.title = 'Preview of ' + f.name;
            body.appendChild(frame);
        } else if (/^image\/(jpeg|png)$/.test(type) || /\.(jpe?g|png)$/.test(name)) {
            var img = document.createElement('img');
            img.src = url; img.alt = 'Preview of ' + f.name;
            body.appendChild(img);
        } else {
            URL.revokeObjectURL(url); url = null;
            var note = document.createElement('div');
            note.className = 'sp-preview-note';
            note.textContent = 'A TIFF cannot be shown in the browser. Check the file name and size, then upload.';
            body.appendChild(note);
        }
        if (f.size > 25 * 1048576) {
            var warn = document.createElement('div');
            warn.className = 'sp-preview-note'; warn.style.color = '#b91c1c';
            warn.textContent = 'This file is larger than 25 MB and will be refused. Choose a smaller file.';
            body.prepend(warn);
        }
        wrap.hidden = false;
        if (window.lucide) lucide.createIcons();
    });
    document.getElementById('spPreviewClear').addEventListener('click', function () { input.value = ''; clear(); input.focus(); });
})();
</script>
@endpush
