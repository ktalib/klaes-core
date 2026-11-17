{{-- Survey Plan Management, Plan details on KLAES (Recording 5, 2026-11-16): shared by the Manual Upload pop-up
     ($withFile: the plan file and its preview) and the Digital Plotting page (no file). $no: the section number. --}}
@php use App\Models\Survey\SurveyPlanUpload as Plan; @endphp
<div class="spc" style="margin:18px 0 12px;"><div class="spc-group"><span class="n">{{ $no }}</span><span>Plan details</span></div></div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-file-upload"></i><span>Survey Plan</span></div>
        <div class="form-group">
            <label>Type <span class="required">*</span></label>
            <select name="plan_type" required>
                <option value="">— Select —</option>
                @foreach (Plan::TYPES as $t)<option value="{{ $t }}" @selected(old('plan_type') === $t)>{{ $t }}</option>@endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Send To <span class="required">*</span></label>
            <select name="unit" required>
                <option value="">— Select unit —</option>
                @foreach (Plan::UNITS as $u => $label)<option value="{{ $u }}" @selected(old('unit') === $u)>{{ $label }}</option>@endforeach
            </select>
            <p class="helper-text">GKN for public plans, Examination/Verification for private.</p>
        </div>
        <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Location of the Plan</span></div>
        <div class="form-group">
            <label>Plot No.</label>
            <input type="text" name="plot_no" maxlength="100" value="{{ old('plot_no') }}">
        </div>
        <div class="form-group">
            <label>District <span class="required">*</span></label>
            <select name="district" id="spuDistrict" required>
                <option value="">— Select district —</option>
                @foreach ($districts as $d)<option value="{{ $d }}" @selected(old('district') === $d)>{{ $d }}</option>@endforeach
                <option value="other" @selected(old('district') === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="district_other" id="spuDistrictOther" maxlength="150" placeholder="Specify the district *" value="{{ old('district_other') }}"
                   style="margin-top:6px;{{ old('district') === 'other' ? '' : 'display:none;' }}" @disabled(old('district') !== 'other') @required(old('district') === 'other')>
        </div>
        <div class="form-group">
            <label>LGA <span class="required">*</span></label>
            <select name="lga" required>
                <option value="">— Select LGA —</option>
                @foreach ($lgas as $l)<option value="{{ $l }}" @selected(old('lga') === $l)>{{ $l }}</option>@endforeach
            </select>
        </div>
        {{-- Map sheet, Metric and Imperial (CR 141102026 item 3, 2026-11-15). Optional: blank when not known. --}}
        <div class="form-group">
            <label>Map Sheet (Metric) <a class="spc-sheet-link" href="{{ asset('assets/survey/utm-sheet-index.webp') }}" target="_blank" rel="noopener" data-spc-guide="sheets"><i class="fas fa-map"></i> View sheet index</a></label>
            <select name="map_sheet_metric">
                <option value="">— Select sheet —</option>
                @foreach (\App\Services\Survey\MapSheets::METRIC as $c)<option value="{{ $c }}" @selected(old('map_sheet_metric') === $c)>{{ $c }}</option>@endforeach
            </select>
            <p class="helper-text">Kano metropolitan index grid. Leave blank if not known.</p>
        </div>
        <div class="form-group">
            <label>Map Sheet (Imperial) <a class="spc-sheet-link" href="{{ asset('assets/survey/utm-sheet-index.webp') }}" target="_blank" rel="noopener" data-spc-guide="sheets"><i class="fas fa-map"></i> View sheet index</a></label>
            <select name="map_sheet_imperial">
                <option value="">— Select sheet —</option>
                @foreach (\App\Services\Survey\MapSheets::IMPERIAL as $c => $n)<option value="{{ $c }}" @selected(old('map_sheet_imperial') === $c)>{{ $c }} · {{ $n }}</option>@endforeach
            </select>
            <p class="helper-text">1:50,000 topo sheet. Leave blank if not known.</p>
        </div>

        <div class="sv-group"><i class="fas fa-user-edit"></i><span>Drawn By</span></div>
        <div class="form-group">
            <label>Who drew the plan <span class="required">*</span></label>
            <select name="drawn_by" id="spuDrawn" required>
                <option value="">— Select surveyor —</option>
                @foreach ($surveyors as $s)<option value="{{ $s->id }}" @selected(old('drawn_by') === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>@endforeach
                <option value="other" @selected(old('drawn_by') === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="drawn_by_other" id="spuDrawnOther" maxlength="255" placeholder="Type the name *" value="{{ old('drawn_by_other') }}"
                   style="margin-top:6px;{{ old('drawn_by') === 'other' ? '' : 'display:none;' }}" @disabled(old('drawn_by') !== 'other') @required(old('drawn_by') === 'other')>
        </div>
        <div class="form-group" style="grid-column:span 2;">
            <label>Remarks</label>
            <textarea name="remarks" rows="2" maxlength="1000">{{ old('remarks') }}</textarea>
        </div>

        @if ($withFile)
        {{-- The plan file comes last, after Drawn By (user, 2026-11-15). --}}
        <div class="sv-group"><i class="fas fa-file-upload"></i><span>Plan File</span></div>
        <div class="form-group">
            <label>Plan File @if (! ($fileOptional ?? false))<span class="required">*</span>@else<span class="helper-text">(optional)</span>@endif</label>
            <input type="file" name="plan_file" id="spuFile" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" @required(! ($fileOptional ?? false))>
            <p class="helper-text">PDF, JPG, PNG or TIFF, up to 25 MB.@if ($fileOptional ?? false) Leave it empty to send the plotting only.@endif</p>
        </div>
        {{-- Preview of the chosen plan, before it is saved (user, 2026-11-10). Read from the browser only. --}}
        <div id="spuPreview" hidden style="grid-column:1 / -1;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;padding:10px 12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:600;color:#374151;"><i class="fas fa-eye" style="margin-right:6px;color:#6b7280;"></i>Preview: <span id="spuPreviewName"></span></span>
                <button type="button" id="spuPreviewClear" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;"><i class="fas fa-times"></i> Remove file</button>
            </div>
            <div id="spuPreviewBody"></div>
        </div>
        @endif
    </div>
@once
@push('scripts')
<script>
// Runs on load: jQuery / select2 load after this partial on the portal.
// "Other (specify)" reveals a required box; any other choice hides, clears and disables it.
window.addEventListener('load', function () {
    [['spuDistrict', 'spuDistrictOther'], ['spuDrawn', 'spuDrawnOther']].forEach(function (pair) {
        var pick = document.getElementById(pair[0]), box = document.getElementById(pair[1]);
        if (!pick || !box) return;
        var sync = function () {
            var on = pick.value === 'other';
            box.style.display = on ? '' : 'none';
            box.disabled = !on; box.required = on;
            if (!on) box.value = '';
        };
        pick.addEventListener('change', sync);
        if (window.jQuery) window.jQuery(pick).on('change', sync);   // select2 on long lists
    });
});
</script>
@endpush
@endonce
