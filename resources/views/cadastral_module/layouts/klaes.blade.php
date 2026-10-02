{{--
    Cadastral Module shell — renders the module's pages INSIDE the main KLAES app
    layout, so the standard KLAES sidebar, header and footer are kept.

    Structure follows the app-wide convention used by ~278 other pages:
        .flex-1.overflow-auto > admin.header > body > admin.footer

    admin.header reads $PageTitle / $PageDescription (capital P), which each page
    view sets. Section partials must therefore NOT add their own <h2> heading.

    The prototype CSS is the Survey module's, reused deliberately: the Survey
    conventions doc names _prototype_css_scoped.blade.php as shared, and reusing
    it gives the whole documented class vocabulary (kpi-grid, table-wrapper,
    status-badge, form-grid, wf-chain …) without a second copy to keep in step.
    Everything is scoped under .survey-proto, so the wrapper keeps that class;
    .cadastral-proto sits beside it to carry the rose accents this department
    uses in the sidebar.

    Do not edit the survey_module partials — they are shared.
--}}
@extends('layouts.app')

@push('styles')
    @include('survey_module.partials._prototype_css_scoped')
    @include('cadastral_module.partials._palette')
    {{-- Select2 powers the address builder's district (1,818) and street (826)
         lists. jQuery 3.6.0 is already global in layouts.app. --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
<div class="flex-1 overflow-auto flex flex-col">
    @include('admin.header')

    <div class="p-6 flex-1">
        <div class="survey-proto cadastral-proto">
            @yield('cadastral-content')
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('survey_module.partials._address_builder_js')
    @include('survey_module.partials._scripts')
    @include('cadastral_module.partials._scripts')
@endpush
