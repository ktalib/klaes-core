{{-- Survey Plan Management, Plan details on the MLPP-SG portal (Recording 5, 2026-11-16): shared by Manual Upload
     ($withFile: the plan file and its preview) and Digital Plotting (no file). $no: the section number. $me: the surveyor. --}}
@php
    use App\Models\Survey\SurveyPlanUpload as Plan;
    $district = old('district');
@endphp
        <style>
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
        <div class="spc"><div class="spc-group"><span class="n">{{ $no }}</span><span>Plan details</span></div></div>
        <div class="space-y-4">
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
            {{-- TP No. (user, 2026-11-18): the TP list, or Other + specify. --}}
            @php $spTpNos = \App\Models\Survey\SurveyApplicationPlan::planNoChoices(); $spTpOther = old('tp_no') === 'other'; @endphp
            <div>
                <label class="ks-label" for="spTpNo">TP No.</label>
                <select class="ks-input" id="spTpNo" name="tp_no" onchange="var o = document.getElementById('spTpOther'), on = this.value === 'other'; o.style.display = on ? '' : 'none'; o.disabled = !on; o.required = on; if (on) o.focus();">
                    <option value="">Select TP No.…</option>
                    @foreach ($spTpNos as $tp)<option value="{{ $tp }}" @selected(old('tp_no') === $tp)>{{ $tp }}</option>@endforeach
                    <option value="other" @selected($spTpOther)>Other (specify)</option>
                </select>
                <input class="ks-input" type="text" name="tp_no_other" id="spTpOther" maxlength="120" placeholder="Specify the TP No. *" value="{{ old('tp_no_other') }}"
                       style="margin-top:6px;{{ $spTpOther ? '' : 'display:none;' }}" @disabled(! $spTpOther) @required($spTpOther)>
                @error('tp_no')<p class="ks-error">{{ $message }}</p>@enderror
                @error('tp_no_other')<p class="ks-error">{{ $message }}</p>@enderror
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
        {{-- Map sheet, Metric and Imperial (CR 141102026 item 3, 2026-11-15). Optional: blank when not known. --}}
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="map_sheet_metric">Map Sheet (Metric)</label> <a class="spc-sheet-link" href="{{ asset('assets/survey/utm-sheet-index.webp') }}" target="_blank" rel="noopener" data-spc-guide="sheets"><i class="fas fa-map"></i> View sheet index</a>
                <select class="ks-input ks-searchable" id="map_sheet_metric" name="map_sheet_metric" data-placeholder="Search sheet…">
                    <option value="">Select sheet…</option>
                    @foreach (\App\Services\Survey\MapSheets::METRIC as $c)<option value="{{ $c }}" @selected(old('map_sheet_metric') === $c)>{{ $c }}</option>@endforeach
                </select>
                <p class="ks-help" style="margin-top:4px;">Kano metropolitan index grid. Leave blank if not known.</p>
                @error('map_sheet_metric')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="map_sheet_imperial">Map Sheet (Imperial)</label> <a class="spc-sheet-link" href="{{ asset('assets/survey/utm-sheet-index.webp') }}" target="_blank" rel="noopener" data-spc-guide="sheets"><i class="fas fa-map"></i> View sheet index</a>
                <select class="ks-input ks-searchable" id="map_sheet_imperial" name="map_sheet_imperial" data-placeholder="Search sheet…">
                    <option value="">Select sheet…</option>
                    @foreach (\App\Services\Survey\MapSheets::IMPERIAL as $c => $n)<option value="{{ $c }}" @selected(old('map_sheet_imperial') === $c)>{{ $c }} · {{ $n }}</option>@endforeach
                </select>
                <p class="ks-help" style="margin-top:4px;">1:50,000 topo sheet. Leave blank if not known.</p>
                @error('map_sheet_imperial')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="drawn_by">Drawn By <span class="ks-req">*</span></label>
                <input class="ks-input" id="drawn_by" name="drawn_by" value="{{ old('drawn_by', $me->name) }}" maxlength="255" required>
                <p class="ks-help" style="margin-top:4px;">Who drew the plan. Change it if it was not you.</p>
                @error('drawn_by')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            @if ($withFile)
            <div>
                <label class="ks-label" for="plan_file">Plan File @if (! ($fileOptional ?? false))<span class="ks-req">*</span>@else<span class="ks-help">(optional)</span>@endif</label>
                <input class="ks-input" id="plan_file" type="file" name="plan_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" @required(! ($fileOptional ?? false))>
                <p class="ks-help" style="margin-top:4px;">PDF, JPG, PNG or TIFF; at most 25 MB.@if ($fileOptional ?? false) Leave it empty to send the plotting only.@endif</p>
                @error('plan_file')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            @endif
        </div>
        @if ($withFile)
        {{-- Preview of the chosen plan before it is sent (user, 2026-11-12). PDF and images show in place; a TIFF cannot be shown by the browser. --}}
        <div id="spPreview" class="sp-preview" hidden>
            <div class="sp-preview-head">
                <span><i data-lucide="eye" class="w-4 h-4"></i> Preview: <strong id="spPreviewName"></strong> <span id="spPreviewSize" class="ks-help" style="margin:0;"></span></span>
                <button type="button" class="ks-btn" id="spPreviewClear"><i data-lucide="x" class="w-4 h-4"></i> Remove</button>
            </div>
            <div id="spPreviewBody" class="sp-preview-body"></div>
        </div>
        @endif
        <div>
            <label class="ks-label" for="remarks">Remarks</label>
            <textarea class="ks-input" id="remarks" name="remarks" rows="2" maxlength="1000">{{ old('remarks') }}</textarea>
        </div>
        </div>
@once
@push('scripts')
<script>
// Runs on load: jQuery / select2 load after this partial on the portal.
// District: a searchable list (user, 2026-11-12). "Other (specify)" reveals a required box; any other choice hides, clears and disables it.
window.addEventListener('load', function () {
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
});
</script>
@endpush
@endonce
