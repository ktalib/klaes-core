{{-- Configurable Entries > Farm Features (user, 2026-11-18): the Survey Farm Features
     catalogue (TreeController::index) drawn as a tab of this page, on its own route
     (configurable-entries.farm-features) so the sidebar lights Configurable Entries,
     not Compensation Mgt. Styles and scripts are the ones survey_module.layouts.klaes
     loads; $tab here is the catalogue's Economic Trees / Cash Crops tab, $ceTab the
     Configurable Entries one. --}}
@extends('layouts.app')
@section('page-title')
    {{ __('Configurable Entries') }}
@endsection

@push('styles')
    @include('survey_module.partials._prototype_css_scoped')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
    @include('admin.header', ['PageTitle' => 'Configurable Entries', 'PageDescription' => 'System Admin · revenue items, bill formulas, departments & roles, file numbers, instrument volumes, pipeline, land rates'])
    @include('instrument_workflow.partials.styles')

    <div class="iw-page space-y-5" style="max-width:1400px">
        <div class="flex items-center justify-between gap-3" style="margin-bottom:0">
            <div class="iw-crumbs" style="margin-bottom:0">
                <span>System Admin</span><span>/</span><span>Configurable Entries</span><span>/</span><span class="text-gray-900">Farm Features</span>
            </div>
            <button type="button" onclick="window.location.reload()" class="iw-btn iw-btn-secondary" title="Reload Farm Features">
                <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
            </button>
        </div>

        @include('system_admin.configurable_entries.partials._tab_bar', ['activeTab' => $ceTab])

        <div class="survey-proto">
            @include('survey_module.partials.sections._page-compensation-trees')
            @include('survey_module.partials._entry_modal')
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    @include('survey_module.partials._address_builder_js')
    @include('survey_module.partials._scripts')
@endpush

@include('survey_module.partials._searchable_selects')
