@extends('surveyor_portal.layouts.portal')

@section('title', "New Surveyor's Report — MLPP-SG Portal")
@section('heading', "New Surveyor's Report")

@include('surveyor_portal.reports._assets')

@push('styles')
<style>
    .ks-beacon-chips { display:flex; flex-wrap:wrap; gap:6px; }
    .ks-beacon-chips span { padding:5px 10px; border:1px solid var(--ks-line); border-radius:999px; background:#F3F6FB; font-size:12px; font-weight:600; }
</style>
@endpush

@section('main')
    @php
        $canIts = ! empty($its);
        // Beacons picked under Beacons to update (Complete update) ride along; only unused ones of this surveyor.
        $unusedKeys = $beacons->pluck('beacon_no')->all();
        $picked = array_values(array_filter($picked, fn ($b) => in_array($b, $unusedKeys, true)));
    @endphp

    @if (! $canIts)
        {{-- Survey Update 5d (user, 2026-11-10): no report without an I to S; "My issued beacons" is gone. --}}
        <section class="ks-card">
            <h2 style="margin:0;font-size:17px;font-weight:800;">No I to S issued to you yet</h2>
            <p class="ks-help" style="margin:6px 0 0;">A Surveyor's Report starts from an Instruction to Survey issued to you. Once one is issued it appears here to choose.</p>
            <div class="flex flex-wrap justify-end gap-2" style="margin-top:14px;">
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.reports.index') }}">Back</a>
            </div>
        </section>
    @else
    <form method="POST" action="{{ route('surveyor-portal.reports.store') }}" id="repForm">
        @csrf
        <fieldset class="ks-rep-section">
            <legend><i data-lucide="file-badge" class="w-4 h-4"></i> I to S issued</legend>
            <label class="ks-label" for="repIts">Instruction to Survey <span class="ks-req">*</span></label>
            <select class="ks-input" id="repIts" name="its_key" required>
                <option value="">Select I to S…</option>
                @foreach ($its as $key => $i)
                    <option value="{{ $key }}" @selected(old('its_key', $itsKey) === $key)>{{ $i->its_number }}</option>
                @endforeach
            </select>
            <p class="ks-help" style="margin-top:6px;">The I to S's property details fill in.</p>
            @error('its_key')<p class="ks-error">{{ $message }}</p>@enderror

            @if ($picked)
                <span class="ks-label" style="margin-top:12px;">Beacons to update</span>
                <div class="ks-beacon-chips">
                    @foreach ($picked as $b)
                        <span>{{ \App\Models\Survey\SurveyBeaconRegister::display($b) }}</span>
                        <input type="hidden" name="beacons[]" value="{{ $b }}">
                    @endforeach
                </div>
                <p class="ks-help" style="margin-top:6px;">Their coordinates go in the List of Coordinates after you save.</p>
            @endif
            @error('beacons')<p class="ks-error">{{ $message }}</p>@enderror
        </fieldset>

        @include('surveyor_portal.reports._fields', ['lock' => false, 'fileLocked' => (bool) $itsKey])

        <div class="flex flex-wrap justify-end gap-2">
            <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.reports.index') }}">Cancel</a>
            <button class="ks-btn ks-btn-primary"><i data-lucide="save" class="w-4 h-4"></i> Save and continue</button>
        </div>
    </form>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    // Picking an I to S reloads with its details filled in (grantee, plot, district, LGA); picked beacons stay.
    var its = document.getElementById('repIts');
    if (its) its.addEventListener('change', function () {
        if (!its.value) return;
        var q = new URLSearchParams();
        q.set('its', its.value);
        document.querySelectorAll('#repForm input[name="beacons[]"]').forEach(function (b) { q.append('beacons[]', b.value); });
        window.location = @json(route('surveyor-portal.reports.create')) + '?' + q.toString();
    });
})();
</script>
@endpush
