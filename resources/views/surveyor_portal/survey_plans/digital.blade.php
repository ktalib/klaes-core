@extends('surveyor_portal.layouts.portal')

@section('title', 'Digital Plotting — KN-MLPP-SG Portal')
@section('heading', 'Digital Plotting')

@section('main')
    {{-- Digital Plotting (Recording 5, 2026-11-16): the plan plotted on the map; the plan file is optional. 1 coordinate system, 2 beacons in
         traverse order (X1 is the reference), 3 map, 4 plan details, 5 export. --}}
    <div style="margin-bottom:12px;"><a class="ks-btn" href="{{ route('surveyor-portal.survey-plans.index', ['method' => 'digital']) }}"><i data-lucide="arrow-left" class="w-4 h-4"></i> My digital plottings</a></div>
    @php $spdErrors = collect($errors->getMessages())->reject(fn ($m, $k) => str_starts_with($k, 'beacons') || $k === 'coord_system')->flatten(); @endphp
    @if ($spdErrors->isNotEmpty())
        <div class="ks-card p-4" style="margin-bottom:12px;border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            @foreach ($spdErrors as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif
    <form method="POST" id="spPortalDigital" action="{{ route('surveyor-portal.survey-plans.store') }}" enctype="multipart/form-data" class="ks-card p-6 space-y-4">
        @csrf
        <input type="hidden" name="plan_method" value="digital">
        @include('survey_module.survey_plans._plan_check', ['spcMode' => 'digital', 'spcSearchUrl' => route('surveyor-portal.survey-plans.beacons')])
        @include('surveyor_portal.survey_plans._plan_details_portal', ['no' => 4, 'withFile' => true, 'fileOptional' => true])
        @include('survey_module.survey_plans._plan_check_export', ['spcExportNo' => 5, 'spcExportGate' => true])
        <div class="flex justify-end">
            <button class="ks-btn ks-btn-primary"><i data-lucide="send" class="w-4 h-4"></i> Plot and Send</button>
        </div>
    </form>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endpush
