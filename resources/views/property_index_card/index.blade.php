@extends('layouts.app')
@section('page-title')
    {{ __('Property Index Card Assistant') }}
@endsection
@section('content')
@include('propertycard.css.style') 
    <!-- Main Content --> 
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6"> 
            @php
                $currentUser = auth()->user();
                $assignRoles = $currentUser && is_array($currentUser->assign_role)
                    ? $currentUser->assign_role
                    : array_filter(array_map('trim', explode(',', (string) ($currentUser->assign_role ?? ''))));
                $isSupperAdminRole = in_array('Supper Admin', $assignRoles, true);
            @endphp
            <div class="container mx-auto py-6 space-y-6">
                <!-- Page Header -->
                {{-- <div class="flex flex-col space-y-2">
                    <h1 class="text-3xl font-bold tracking-tight">Property Index Card Assistant</h1>
                    <p class="text-gray-500">Capture and manage property index cards</p>
                </div> --}}
        
                <div class="flex items-center justify-end mb-4">
                    @if($isSupperAdminRole)
                        <a href="{{ route('pra-pic-audit.index') }}" class="inline-flex items-center rounded border border-blue-200 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                            <i class="fas fa-chart-bar mr-2"></i>
                            PRA/PIC Audit Dashboard
                        </a>
                    @endif
                </div>

                <!-- Manual Property Details Content -->
                <div id="manual-assistant">
                    @include('propertycard.partials.property_details', [
                        'featuredRecord' => $featuredRecord ?? null,
                        'dataRoute' => route('property_index_card.getData'),
                        'fallbackRoute' => route('propertycard.data.fallback'),
                        'cofoRoute' => route('propertycard.cofo'),
                        'showPropertyRecordButton' => false,
                        'showIndexCardButton' => true
                    ])
                </div>

                <!-- AI Property Details Content (placeholder kept for future use) -->
                <div id="ai-assistant" style="display: none;"></div>
           
            </div>
        
            <!-- Property Modal Dialogs -->
            @include('propertycard.partials.add_property_record')
            @include('propertycard.partials.edit_property_record')
            @include('propertycard.partials.view_property_record')
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <!-- Include JavaScript after all DOM elements -->
    @include('propertycard.js.javascript')
    @include('propertycard.partials.property_form_sweetalert')
@endsection
