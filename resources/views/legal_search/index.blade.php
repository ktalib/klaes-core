@extends('layouts.app')
@section('page-title')
    {{ $moduleConfig['pageTitle'] ?? __('Legal Search - Official (for filing purpose)') }}
@endsection

 
@section('content') 
@php
    /*
     |--------------------------------------------------------------------------
     | Legal Search - forced loading screen
     |--------------------------------------------------------------------------
     | The module is hidden behind a permanent loading overlay for everyone
     | except user ID 1, who gets the real page. Kill it for everyone by setting
     | LEGAL_SEARCH_LOADING=false in .env (then `php artisan config:clear`) or by
     | flipping the fallback below to false.
     */
    $forceLoading = filter_var(env('LEGAL_SEARCH_LOADING', false), FILTER_VALIDATE_BOOLEAN)
        && (int) (auth()->id() ?? 0) !== 1;
@endphp
@if ($forceLoading)
{{-- Same preloader the main layout uses, but this one never hides. --}}
<div id="lsForcedLoading" class="ls-forced-loading">
    <img src="http://app.klaes.ng/assets/logo/klas_logo.gif" alt="Loading..." style="width: 300px; height: auto;">
</div>
<style>
    .ls-forced-loading {
        position: fixed;
        inset: 0;
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
    }
    body.ls-forced-loading-active { overflow: hidden; }
</style>
<script>
    document.body.classList.add('ls-forced-loading-active');
</script>
@else
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
@include('filearchive.partials.document_viewer_modal')

  @include('legal_search.partials.search-modal')

    @include('propertycard.partials.add_property_record')

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
    searchRouteName: "{{ $moduleConfig['searchRouteName'] ?? 'legalsearch.search' }}",
    requiresPayment: false,
    dashboardStatsUrl: "{{ route('legal_search.dashboard_stats') }}"
};
</script>
@include('legal_search.js')
@include('filearchive.assets.js')
{{-- Searchable dropdowns for the Edit File Information modal's long TP No /
     District lists (2,900+ and 1,800+ entries). --}}
@include('components.searchable-select2')
@endif
@endsection
