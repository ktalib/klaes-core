@extends('layouts.app')
@section('page-title')
    {{ $moduleConfig['pageTitle'] ?? __('Legal Search - Official (for filing purpose)') }}
@endsection

 
@section('content') 
@include('legal_search.style') 
@include('propertycard.css.style')
@include('filearchive.assets.style')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <div class="p-6">

  <!-- Main Content -->

  <main class="flex-1 p-6">
    @include('legal_search.partials.dashboard')
    @include('legal_search.partials.file-history')
    @include('legal_search.partials.report')
  </main>

  @include('legal_search.partials.search-modal')

    @include('propertycard.partials.add_property_record')
@include('filearchive.partials.document_viewer_modal')

        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/property-timeline-modal.js') }}"></script>
<script src="{{ asset('js/pra/helpers.js') }}"></script>
<script src="{{ asset('js/pra/state.js') }}"></script>
<script src="{{ asset('js/pra/modal.js') }}"></script>
<script src="{{ asset('js/pra/form-controller.js') }}?v={{ @filemtime(public_path('js/pra/form-controller.js')) }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.LEGAL_SEARCH_CONTEXT = {
    printTemplateUrl: "{{ route($moduleConfig['printTemplateRouteName'] ?? 'legal_search.print.official') }}",
    printManagerDocType: "{{ $moduleConfig['printManagerDocType'] ?? '' }}",
    dashboardStatsUrl: "{{ route('legal_search.dashboard_stats') }}"
};
</script>
@include('legal_search.js')
@include('filearchive.assets.js')
@endsection
