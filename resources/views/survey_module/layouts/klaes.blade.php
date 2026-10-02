{{--
    Survey Module shell — renders the ported prototype pages INSIDE the main
    KLAES app layout, so the standard KLAES sidebar, header and footer are kept.

    Structure follows the app-wide convention used by ~278 other pages:
        .flex-1.overflow-auto > admin.header > body > admin.footer

    admin.header reads $PageTitle / $PageDescription (capital P), which each
    page view sets. The prototype's duplicate in-page heading has been removed
    from the section partials; their action buttons are kept.

    The prototype's own sidebar/header shell is not used. Its CSS for those
    selectors is stripped in _prototype_css_scoped.blade.php, and the remaining
    prototype styles are scoped under .survey-proto so they cannot reach the app
    chrome (the app's sidebar root is class="sidebar", which the unscoped
    prototype CSS would otherwise restyle as a fixed navy panel).
--}}
@extends('layouts.app')

@push('styles')
    @include('survey_module.partials._prototype_css_scoped')
    {{-- Select2 powers the address builder's district (1,818) and street (826) lists.
         jQuery 3.6.0 is already global in layouts.app. --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
<div class="flex-1 overflow-auto flex flex-col">
    @include('admin.header')

    <div class="p-6 flex-1">
        <div class="survey-proto">
            @yield('survey-content')
        </div>
    </div>

    @include('admin.footer')
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('survey_module.partials._address_builder_js')
    @include('survey_module.partials._scripts')
@endpush
