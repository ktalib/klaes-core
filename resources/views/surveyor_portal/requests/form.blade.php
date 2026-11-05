@extends('surveyor_portal.layouts.portal')

@section('title', 'Request Beacons — KSSGOF Portal')
@section('heading', 'Request Beacons')

@section('main')
    @php $me = auth('surveyor')->user(); $type = old('layout_type', $me->surveyor_type); @endphp
    <form method="POST" action="{{ route('surveyor-portal.requests.store') }}" class="ks-card p-6 space-y-4" style="max-width:720px;">
        @csrf
        <div>
            <span class="ks-label">Survey Type <span class="ks-req">*</span></span>
            <div class="flex gap-6 text-sm">
                @foreach (['Government' => 'Public', 'Private' => 'Private'] as $v => $l)
                    <label class="flex items-center gap-2"><input type="radio" name="layout_type" value="{{ $v }}" required @checked($type === $v)> {{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <span class="ks-label">Surveyor</span>
                <div class="ks-input" style="background:#F1F3F5;">{{ $me->name }}</div>
            </div>
            <div>
                <span class="ks-label">Company</span>
                <div class="ks-input" style="background:#F1F3F5;">{{ $me->company_name ?: '—' }}</div>
            </div>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="ks-label" for="job_name">Name of Survey <span class="ks-req">*</span></label>
                <input class="ks-input" id="job_name" name="job_name" value="{{ old('job_name') }}" maxlength="255" required>
            </div>
            <div>
                <label class="ks-label" for="quantity">Number of Beacons <span class="ks-req">*</span></label>
                <input class="ks-input" id="quantity" type="number" name="quantity" min="1" max="{{ \App\Services\Survey\BeaconRequests::MAX_QUANTITY }}" value="{{ old('quantity', 4) }}" required>
            </div>
        </div>
        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="ks-label" for="plot_no">Plot No.</label>
                <input class="ks-input" id="plot_no" name="plot_no" value="{{ old('plot_no') }}" maxlength="100">
            </div>
            <div>
                <label class="ks-label" for="district">District</label>
                <input class="ks-input" id="district" name="district" value="{{ old('district') }}" maxlength="255">
            </div>
            <div>
                <label class="ks-label" for="lga">LGA</label>
                <select class="ks-input" id="lga" name="lga">
                    <option value="">Select LGA…</option>
                    @foreach ($lgas as $l)<option value="{{ $l }}" @selected(old('lga') === $l)>{{ $l }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="ks-label" for="remarks">Remarks</label>
            <textarea class="ks-input" id="remarks" name="remarks" rows="3" maxlength="2000">{{ old('remarks') }}</textarea>
        </div>
        <div class="flex justify-end"><button class="ks-btn ks-btn-primary">Send to the Surveyor General</button></div>
    </form>
@endsection
