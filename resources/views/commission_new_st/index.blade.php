@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? 'Commission New ST' }}
@endsection

{{-- Include CSS Assets --}}
@include('sectionaltitling.partials.assets.css')
@include('primaryform.assets.css.styles')

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/commission_new_st/main.css') }}">
@endpush

@section('content')
    <script src="{{ asset('js/primaryform/states-lga.js') }}"></script>
    {{-- Shared "where did this record go" card, also used after file indexing and
         property transaction capture. Must load before sua_commission.js, which
         wraps it as showStCommissioningCard(). --}}
    <script src="{{ asset('js/shared/record-summary-card.js') }}?v={{ @filemtime(public_path('js/shared/record-summary-card.js')) }}"></script>
    <script src="{{ asset('js/commission_new_st/location-map.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/applicant-autofill.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/primary.js') }}"></script>
    {{-- Shared-number check on the applicant phone field, for all three ST paths. --}}
    <script src="{{ asset('js/sms-applicant-phone.js') }}?v={{ @filemtime(public_path('js/sms-applicant-phone.js')) }}" defer></script>
    <script src="{{ asset('js/commission_new_st/sua_commission.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/pua.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/file-modal-integration.js') }}"></script>
    <script src="{{ asset('js/commission_new_st/page-init.js') }}"></script>
    @if(!empty($editRecord))
        {{-- Edit mode payload — read by edit-mode.js to prefill the form and lock the
             file-number controls. Loaded last so every form script is already defined. --}}
        <script>
            window.ST_EDIT_RECORD = @json($editRecord);
            window.ST_EDIT_UPDATE_URL = '{{ route('commission-new-st.update', ['id' => $editRecord['id']]) }}';
        </script>
        <script src="{{ asset('js/commission_new_st/edit-mode.js') }}?v={{ @filemtime(public_path('js/commission_new_st/edit-mode.js')) }}"></script>
    @endif





   <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        @include('exports.consolidated_report_action', ['reportKey' => 'st-commissioning'])
        <!-- Dashboard Content -->
        <div class="p-6">

        @if(!empty($editRecord))
            {{-- Edit mode badge: this page is normally a commissioning form, so it has
                 to say plainly that it is editing an issued record instead. --}}
            <div class="mb-6 flex flex-wrap items-start gap-3 rounded-xl border-2 border-amber-300 bg-amber-50 p-4 shadow-sm">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white">
                    <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                    Edit Mode
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-900">
                        Editing commissioned record
                        <span class="font-mono">{{ $editRecord['registry_file_number'] }}</span>
                        <span class="ml-1 rounded bg-amber-200 px-2 py-0.5 text-xs font-bold text-amber-900">{{ $editRecord['file_no_type'] }}</span>
                    </p>
                    <p class="mt-1 text-xs text-amber-800">
                        File numbers, land use and allocation type are locked — they are already
                        issued and referenced across the registry. Applicant details, gender and
                        location can be corrected and saved.
                    </p>
                </div>
                <a href="{{ route('st-file-numbers.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-100">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Back to ST Commissioning
                </a>
            </div>
        @endif

        <!-- ST File Number Commissioning Navigation -->
        <div class="mb-6" x-data="{ activeTab: '{{ $editTab ?? 'primary' }}' }">
            <div class="border-b border-gray-200 bg-white rounded-t-lg">
            <nav class="flex space-x-2 px-6 py-5" aria-label="ST Workflow Tabs">
                <button 
                @click="activeTab = 'primary'"
                :class="activeTab === 'primary' 
                    ? 'bg-blue-600 text-white shadow-md border-blue-600' 
                    : 'bg-white text-gray-700 hover:bg-blue-50 hover:text-blue-700 border-gray-300'"
                class="inline-flex items-center px-6 py-3 text-base font-medium rounded-lg border-2 transition-all duration-200 min-w-[120px] justify-center"
                >
                <i class="fas fa-file-contract w-5 h-5 mr-3"></i>
                Primary
                </button>
                   <button 
                @click="activeTab = 'pua'; $nextTick(() => window.STLocationMaps?.pua?.refresh())"
                :class="activeTab === 'pua' 
                    ? 'bg-purple-600 text-white shadow-md border-purple-600' 
                    : 'bg-white text-gray-700 hover:bg-purple-50 hover:text-purple-700 border-gray-300'"
                class="inline-flex items-center px-6 py-3 text-base font-medium rounded-lg border-2 transition-all duration-200 min-w-[120px] justify-center"
                >
                <i class="fas fa-users w-5 h-5 mr-3"></i>
                PuA
                </button>
                <button 
                @click="activeTab = 'sua'; $nextTick(() => window.STLocationMaps?.sua?.refresh())"
                :class="activeTab === 'sua' 
                    ? 'bg-green-600 text-white shadow-md border-green-600' 
                    : 'bg-white text-gray-700 hover:bg-green-50 hover:text-green-700 border-gray-300'"
                class="inline-flex items-center px-6 py-3 text-base font-medium rounded-lg border-2 transition-all duration-200 min-w-[120px] justify-center"
                >
                <i class="fas fa-building w-5 h-5 mr-3"></i>
                SuA
                </button>
                
             
            </nav>
        </div>
        
        <!-- Tabs Container -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Primary Tab -->
                <div x-show="activeTab === 'primary'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                    @include('commission_new_st.partials.primary')
                </div>
      <!-- PuA Tab -->
                <div x-show="activeTab === 'pua'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                    @include('commission_new_st.partials.pua')
                </div>

                
                <!-- SuA Tab-->
                <div x-show="activeTab === 'sua'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                    @include('commission_new_st.partials.sua')
                </div>

          
            </div>
        </div>

     
    </div>
</div>

{{-- Include Global File Number Modal Component (also loads Select2) --}}
@include('components.global-fileno-modal')

{{-- Searchable District / Street Name dropdowns --}}
@include('components.reference-select')

{{-- JavaScript Assets --}}
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/primaryform/init.js') }}"></script>

{{-- Map stack — powers the Property Location Map in Location Details --}}
@include('partials.maps_scripts')

{{-- CSRF Token for JavaScript --}}
<script>
// Set CSRF token for AJAX requests
document.addEventListener('DOMContentLoaded', function() {
    // Add CSRF token meta tag for JavaScript access
    if (!document.querySelector('meta[name="csrf-token"]')) {
        const csrfMeta = document.createElement('meta');
        csrfMeta.name = 'csrf-token';
        csrfMeta.content = '{{ csrf_token() }}';
        document.head.appendChild(csrfMeta);
    }
});
</script>
@endsection
