@extends('survey_module.layouts.klaes')

@section('page-title', 'Applications — KLAES')

@php
  $PageTitle = 'Applications';
  $PageDescription = 'GKN, LPKN, LPPL, RDKN and Misc KN files in one register. Commission new numbers with Commission Application Files.';
@endphp

@section('survey-content')
<div x-data='gknGeneratorFixed({
    districts: @json($gen["districts"]),
    lgas: @json($gen["lgas"]),
    landUses: @json($gen["landUses"]),
    purposes: @json($gen["purposes"]),
    csrfToken: "{{ csrf_token() }}",
    availableUrl: "{{ route('gkn-generation.available') }}",
    worldTimeUrl: "{{ route('world-time') }}",
    storeUrl: "{{ route('gkn-generation.store') }}",
    partialUrl: "{{ route('gkn-generation.partial') }}",
    currentDate: "{{ date('Y-m-d') }}",
    currentTimeOnly: "{{ date('H:i:s') }}",
    customerTypes: @json($gen["customerTypes"]),
    serialStatuses: @json($gen["serialStatuses"]),
    pollTimeWhenOpen: true
})'>
  @include('survey_module.partials.sections._page-gkn-applications')
  @include('survey_module.gkn.partials._generate_modal')
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/gkn_generation.js') }}?v={{ filemtime(public_path('js/gkn_generation.js')) }}"></script>
@endpush
