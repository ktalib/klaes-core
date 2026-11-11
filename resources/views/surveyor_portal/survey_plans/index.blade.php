@extends('surveyor_portal.layouts.portal')

@section('title', 'Survey Plan Upload — MLPP-SG Portal')
@section('heading', 'Survey Plan Upload')

@php
    use App\Models\Survey\SurveyPlanUpload as Plan;
    $district = old('district');
@endphp

@section('main')
    {{-- Survey Update 5g/5h (user, 2026-11-10): the very short form. The unit chosen receives the plan in KLAES. --}}
    <form method="POST" action="{{ route('surveyor-portal.survey-plans.store') }}" enctype="multipart/form-data" class="ks-card p-6 space-y-4">
        @csrf
        <p class="ks-help" style="margin:0;">Send a survey plan for checking: GKN for public / government plans, Examination/Verification for private.</p>
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
                <select class="ks-input" id="spDistrict" name="district" required>
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
        <div>
            <label class="ks-label" for="remarks">Remarks</label>
            <textarea class="ks-input" id="remarks" name="remarks" rows="2" maxlength="1000">{{ old('remarks') }}</textarea>
        </div>
        <div class="flex justify-end">
            <button class="ks-btn ks-btn-primary"><i data-lucide="send" class="w-4 h-4"></i> Upload and Send</button>
        </div>
    </form>

    <h2 style="margin:22px 0 10px;font-size:16px;font-weight:800;">My uploads</h2>
    <div class="ks-card overflow-x-auto">
        <table class="ks-table">
            <thead>
                <tr><th>S/N</th><th>Upload Ref</th><th>Type</th><th>Sent To</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Drawn By</th><th>Uploaded</th><th>Plan</th></tr>
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
                        <td><a href="{{ route('surveyor-portal.survey-plans.file', $p->id) }}" target="_blank" rel="noopener" style="color:var(--ks-blue);font-weight:600;">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="ks-help" style="text-align:center;padding:24px;">No survey plans uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection

@push('scripts')
<script>
// District "Other (specify)" reveals a required box; any other choice hides, clears and disables it.
(function () {
    var pick = document.getElementById('spDistrict'), box = document.getElementById('spDistrictOther');
    pick.addEventListener('change', function () {
        var on = pick.value === 'other';
        box.style.display = on ? '' : 'none';
        box.disabled = !on; box.required = on;
        if (on) box.focus(); else box.value = '';
    });
})();
</script>
@endpush
