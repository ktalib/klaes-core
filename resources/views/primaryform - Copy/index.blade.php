@extends('layouts.app')
@section('page-title')
    {{ __('Primary Application Form') }}
@endsection

{{-- Include CSS Assets --}}
@include('sectionaltitling.partials.assets.css')
@include('primaryform.assets.css.styles')

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

@section('content')
<div class="flex-1 overflow-auto">
    {{-- Header --}}
    @include('admin.header')
    
    {{-- Dashboard Content --}}
    <div class="p-6 space-y-6">
        @php
            $draftMeta = $draftBootstrap ?? [];
            $draftList = $draftMeta['drafts'] ?? [];
            $initialMode = $draftMeta['mode'] ?? 'fresh';
        @endphp
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
            <h2 class="text-2xl font-bold leading-tight text-gray-800">
                Primary Application Form
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                Create a new sectional title application or continue from a saved draft
            </p>
            </div>
            <div class="flex items-center gap-3">
            {{-- <button type="button" 
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                onclick="window.print()">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print
            </button> --}}
            <a href="{{ route('sectionaltitling.primary') }}?url=infopro" 
                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
                </svg>
                Back to Applications
            </a>
            </div>
        </div>
                {{-- Instructions Panel --}}
        @include('primaryform.partials.steps.instructions')
        
      
        @include('primaryform.partials.draft-locator')
        
        <div class="grid grid-cols-1 lg:grid-cols-1 gap-6">
            {{-- Loading Overlay --}}
            <div class="loading-overlay">
                <div class="loader"></div>
            </div>
            
            {{-- Main Form Card --}}
            <div class="bg-white rounded-lg shadow ">
                <form id="primaryApplicationForm" method="POST" action="javascript:void(0)" enctype="multipart/form-data" onsubmit="return false;" data-draft-id="{{ $draftMeta['draft_id'] ?? '' }}" data-draft-version="{{ $draftMeta['version'] ?? 1 }}" data-auto-save-frequency="{{ $draftMeta['auto_save_frequency'] ?? 30 }}" data-default-land-use="{{ $landUse ?? '' }}" data-default-np-fileno="{{ $npFileNo ?? '' }}">
                    @csrf
                     
        {{-- Draft Status Panel (Collapsible) --}}
        @include('primaryform.partials.draft-status')

        {{-- Draft Locator Panel (Collapsible) --}}
        @include('primaryform.partials.draft-locator')
        <hr>
        <br> 
                    {{-- ========================================= --}}
                    {{-- ALL HIDDEN FIELDS - GROUPED FOR DEBUGGING --}}
                    {{-- ========================================= --}}
                    
                    {{-- ST API Data (populated by JavaScript) --}}
                    <input type="hidden" name="np_fileno" id="np_fileno" value="{{ old('np_fileno', '') }}" data-source="st-api">
                    <input type="hidden" name="fileno" id="fileno" value="{{ old('fileno', '') }}" data-source="st-api">
                    <input type="hidden" name="land_use" id="land_use" value="{{ old('land_use', $landUse ?? '') }}" data-source="st-api">
                    <input type="hidden" name="applicant_type" id="applicant_type" value="{{ old('applicant_type', '') }}" data-source="st-api">
                    <input type="hidden" name="selected_file_data" id="selected_file_data" value="{{ old('selected_file_data', '') }}" data-source="st-api">
                    <input type="hidden" name="selected_file_id" id="selected_file_id" value="{{ old('selected_file_id', '') }}" data-source="st-api">
                    <input type="hidden" name="selected_file_type" id="selected_file_type" value="{{ old('selected_file_type', '') }}" data-source="st-api">
                    <input type="hidden" name="applied_file_number" id="applied_file_number" value="{{ old('applied_file_number', '') }}" data-source="st-api">
                    <input type="hidden" name="tracking_id" id="tracking_id" value="{{ old('tracking_id', '') }}" data-source="st-api">
                    <input type="hidden" name="primary_file_id" id="primary_file_id" value="{{ old('primary_file_id', '') }}" data-source="st-api">
                    
                    {{-- System Fields --}}
                    <input type="hidden" name="serial_no" value="{{ $serialNo ?? '' }}" data-source="system">
                    <input type="hidden" name="current_year" value="{{ $currentYear ?? date('Y') }}" data-source="system">
                    
                    {{-- Draft Management --}}
                    <input type="hidden" name="draft_id" id="draftIdInput" value="{{ $draftMeta['draft_id'] ?? '' }}" data-source="draft">
                    <input type="hidden" name="draft_version" id="draftVersionInput" value="{{ $draftMeta['version'] ?? 1 }}" data-source="draft">
                    <input type="hidden" name="draft_last_completed_step" id="draftStepInput" value="{{ $draftMeta['last_completed_step'] ?? 1 }}" data-source="draft">
                    
                    
                    {{-- Step 1: Basic Information --}}
                    @include('primaryform.partials.steps.step1-basic')
                    
                    {{-- Step 2: Shared Areas --}}
                    @include('primaryform.partials.steps.step2-sharedareas')
                    
                    {{-- Step 3: Documents --}}
                    @include('primaryform.partials.steps.step3-documents')
                    
                    {{-- Step 4: Buyers List --}}
                    @include('primaryform.partials.steps.step4-buyers')
                    
                    {{-- Step 5: Summary --}}
                    @include('primaryform.partials.steps.step5-summary')
                </form>
            </div>
        </div>
    
    {{-- Footer --}}
    @include('admin.footer')
</div>

{{-- Print Template (Hidden) --}}
@include('primaryform.partials.print')

{{-- JavaScript Assets --}}
{{-- Include Global File Number Modal Component --}}
@include('components.global-fileno-modal')

@include('primaryform.assets.js.scripts')

{{-- === STEP NAVIGATION DIAGNOSTIC SCRIPT === --}}
<script>
console.log('🔍 ========== STEP NAVIGATION DIAGNOSTIC ==========');

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('📋 DOM Ready - Running diagnostics...');
    
    // 1. Check all form sections
    const allSteps = document.querySelectorAll('.form-section');
    console.log(`\n📊 Found ${allSteps.length} form sections`);
    
    allSteps.forEach((step, index) => {
        const computed = window.getComputedStyle(step);
        const isVisible = computed.display !== 'none' && computed.visibility !== 'hidden' && computed.opacity !== '0';
        
        console.log(`
Step ${index + 1} (ID: ${step.id}):
  - Classes: ${step.className}
  - Display (computed): ${computed.display}
  - Display (inline): ${step.style.display || 'not set'}
  - Visibility: ${computed.visibility}
  - Opacity: ${computed.opacity}
  - Has 'active': ${step.classList.contains('active')}
  - IS VISIBLE: ${isVisible ? '✅ YES' : '❌ NO'}
        `);
    });
    
    // 2. Check goToStep function
    console.log(`\n🎯 Navigation function check:`);
    console.log(`   typeof window.goToStep: ${typeof window.goToStep}`);
    console.log(`   typeof goToStep: ${typeof goToStep}`);
    
    console.log('\n✅ Diagnostic complete. Step navigation ready.');
    console.log('� TIP: Click the Next button to test navigation between steps.');
});

console.log('🔍 ========== END DIAGNOSTIC ==========\n');
</script>

{{-- Scan Upload Global Variables --}}
<script src="{{ asset('js/primaryform/scan-upload-globals.js') }}"></script>

{{-- Global File Numbers Auto-Fill Integration --}}
<script src="{{ asset('js/primaryform/global-file-numbers-autofill.js') }}"></script>

{{-- AJAX Form Submission Handler --}}
<script>
    // Make form submit URL available globally
    window.FORM_SUBMIT_URL = "{{ route('primaryform.store') }}";
    console.log('🔗 Form submit URL:', window.FORM_SUBMIT_URL);
</script>
<script src="{{ asset('js/primaryform/form-submission.js') }}"></script>
{{-- <script src="{{ asset('js/primaryform/validation-debug.js') }}"></script> --}}
<script>
    window.PRIMARY_DRAFT_BOOTSTRAP = @json($draftMeta ?? []);
    @php
        $loadTemplate = route('primaryform.draft.load', ['draftId' => '__DRAFT_ID__']);
        $deleteTemplate = route('primaryform.draft.delete', ['draftId' => '__DRAFT_ID__']);
        $exportTemplate = route('primaryform.draft.export', ['draftId' => '__DRAFT_ID__']);
        $analyticsTemplate = route('primaryform.draft.analytics', ['draftId' => '__DRAFT_ID__']);
    @endphp
    window.PRIMARY_DRAFT_ENDPOINTS = {
        save: "{{ route('primaryform.draft.save') }}",
        submit: "{{ route('primaryform.store') }}",
        load: "{{ $loadTemplate }}",
        delete: "{{ $deleteTemplate }}",
        export: "{{ $exportTemplate }}",
        analytics: "{{ $analyticsTemplate }}",
        check: "{{ route('primaryform.draft.check', ['applicationId' => '__APPLICATION_ID__']) }}",
        share: "{{ route('primaryform.draft.share') }}",
        start: "{{ route('primaryform.draft.start') }}",
        mine: "{{ route('primaryform.draft.mine') }}"
    };
</script>
<script src="{{ asset('js/draft-autosave.js') }}"></script>

{{-- Emergency Address Fix - Direct Inline Functions --}}
<script>
console.log('🚀 Loading emergency address fix...');

// Emergency address display functions
function emergencyUpdateAddressDisplay() {
    console.log('🏠 Emergency updateAddressDisplay called');
    
    const houseNo = document.getElementById('ownerHouseNo')?.value || '';
    const streetName = document.getElementById('ownerStreetName')?.value || '';
    const district = document.getElementById('ownerDistrict')?.value || '';
    const lga = document.getElementById('ownerLga')?.value || '';
    const state = document.getElementById('ownerState')?.value || '';
    
    const fullAddress = [houseNo, streetName, district, lga, state]
        .filter(part => part && part.trim() !== '')
        .join(', ');
    
    console.log('📍 Contact Address:', fullAddress);
    
    const displayElement = document.getElementById('fullContactAddress');
    const hiddenElement = document.getElementById('contactAddressDisplay');
    
    if (displayElement) {
        displayElement.textContent = fullAddress || 'Enter address details above';
    }
    
    if (hiddenElement) {
        hiddenElement.value = fullAddress;
    }
}

function emergencyUpdatePropertyAddressDisplay() {
    console.log('🏢 Emergency updatePropertyAddressDisplay called');
    
    const houseNo = document.getElementById('propertyHouseNo')?.value || '';
    const plotNo = document.getElementById('propertyPlotNo')?.value || '';
    const streetName = document.getElementById('propertyStreetName')?.value || '';
    const district = document.getElementById('propertyDistrict')?.value || '';
    const lga = document.getElementById('propertyLga')?.value || '';
    const state = document.getElementById('propertyState')?.value || '';
    
    const fullAddress = [houseNo, plotNo, streetName, district, lga, state]
        .filter(part => part && part.trim() !== '')
        .join(', ');
    
    console.log('🏗️ Property Address:', fullAddress);
    
    const displayElement = document.getElementById('fullPropertyAddress');
    const hiddenElement = document.getElementById('propertyAddressDisplay');
    
    if (displayElement) {
        displayElement.textContent = fullAddress || 'Enter property details above';
    }
    
    if (hiddenElement) {
        hiddenElement.value = fullAddress;
    }
}

// Always use the emergency functions to avoid conflicts
console.log('🔧 Setting emergency functions as primary');
window.updateAddressDisplay = emergencyUpdateAddressDisplay;
window.updatePropertyAddressDisplay = emergencyUpdatePropertyAddressDisplay;

// Setup event listeners on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 Setting up emergency address listeners...');
    
    // Contact address fields
    const contactFields = ['ownerHouseNo', 'ownerStreetName', 'ownerDistrict', 'ownerLga', 'ownerState'];
    contactFields.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.addEventListener('input', emergencyUpdateAddressDisplay);
            element.addEventListener('change', emergencyUpdateAddressDisplay);
            console.log('✅ Added listeners to ' + fieldId);
        }
    });
    
    // Property address fields  
    const propertyFields = ['propertyHouseNo', 'propertyPlotNo', 'propertyStreetName', 'propertyDistrict', 'propertyLga', 'propertyState'];
    propertyFields.forEach(fieldId => {
        const element = document.getElementById(fieldId);
        if (element) {
            element.addEventListener('input', emergencyUpdatePropertyAddressDisplay);
            element.addEventListener('change', emergencyUpdatePropertyAddressDisplay);
            console.log('✅ Added listeners to ' + fieldId);
        }
    });
    
    // Initialize displays
    emergencyUpdateAddressDisplay();
    emergencyUpdatePropertyAddressDisplay();
    
    console.log('✅ Emergency address fix initialized');
});

console.log('🔧 Emergency address fix loaded');

// Global form submit URL for AJAX handler
window.FORM_SUBMIT_URL = '{{ route("primaryform.store") }}';
console.log('🎯 Form submit URL set to:', window.FORM_SUBMIT_URL);

// ID Document upload preview functionality
function previewIdDocument(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        const placeholder = document.getElementById('idDocumentPlaceholder');
        const preview = document.getElementById('idDocumentPreview');
        const info = document.getElementById('idDocumentInfo');
        const removeBtn = document.getElementById('removeIdDocumentBtn');

        // Validate file size (5MB limit)
        if (file.size > 5 * 1024 * 1024) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Please select a file smaller than 5MB.',
                confirmButtonColor: '#dc3545'
            });
            event.target.value = '';
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: 'Please select a JPG, PNG, or PDF file.',
                confirmButtonColor: '#dc3545'
            });
            event.target.value = '';
            return;
        }

        if (file.type === 'application/pdf') {
            // For PDF files, show file info instead of preview
            placeholder.classList.add('hidden');
            preview.classList.add('hidden');
            info.classList.remove('hidden');
            info.innerHTML = `
                <div class="flex items-center justify-center">
                    <svg class="w-8 h-8 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="font-medium">${file.name}</p>
                        <p class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    </div>
                </div>
            `;
            removeBtn.classList.remove('hidden');
        } else {
            // For image files, show preview
            reader.onload = function(e) {
                placeholder.classList.add('hidden');
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                info.classList.add('hidden');
                removeBtn.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }

        // Show success toast
        Swal.fire({
            icon: 'success',
            title: 'File Uploaded',
            text: `${file.name} has been selected successfully.`,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }
}

// Remove ID document functionality
function removeIdDocument() {
    const upload = document.getElementById('idDocumentUpload');
    const placeholder = document.getElementById('idDocumentPlaceholder');
    const preview = document.getElementById('idDocumentPreview');
    const info = document.getElementById('idDocumentInfo');
    const removeBtn = document.getElementById('removeIdDocumentBtn');

    upload.value = '';
    preview.src = '#';
    preview.classList.add('hidden');
    info.classList.add('hidden');
    placeholder.classList.remove('hidden');
    removeBtn.classList.add('hidden');

    Swal.fire({
        icon: 'info',
        title: 'File Removed',
        text: 'ID document has been removed.',
        timer: 1500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// NEW: Simple ID Document Upload Handler
function handleIdDocumentUpload(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    console.log('📄 ID Document file selected:', file.name, file.size, 'bytes');
    
    // Validate file size (5MB limit)
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'File Too Large',
            text: 'Please select a file smaller than 5MB.',
            confirmButtonColor: '#dc3545'
        });
        event.target.value = '';
        return;
    }

    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    if (!allowedTypes.includes(file.type)) {
        Swal.fire({
            icon: 'error',
            title: 'Invalid File Type',
            text: 'Please select a JPG, PNG, or PDF file.',
            confirmButtonColor: '#dc3545'
        });
        event.target.value = '';
        return;
    }

    // Show preview
    const previewArea = document.getElementById('passportPreviewArea');
    const uploadPrompt = document.getElementById('passportUploadPrompt');
    const fileName = document.getElementById('passportFileName');
    const fileSize = document.getElementById('passportFileSize');
    
    if (previewArea && uploadPrompt && fileName && fileSize) {
        fileName.textContent = file.name;
        fileSize.textContent = `${(file.size / 1024).toFixed(2)} KB`;
        
        uploadPrompt.classList.add('hidden');
        previewArea.classList.remove('hidden');
        
        console.log('✅ ID Document preview displayed');
    }

    // Show success toast
    Swal.fire({
        icon: 'success',
        title: 'ID Document Selected',
        text: `${file.name} ready to upload`,
        timer: 2000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

// NEW: Remove Passport File
function removePassportFile() {
    const input = document.getElementById('passportInput');
    const previewArea = document.getElementById('passportPreviewArea');
    const uploadPrompt = document.getElementById('passportUploadPrompt');
    
    if (input) {
        input.value = '';
        console.log('🗑️ Passport file removed');
    }
    
    if (previewArea && uploadPrompt) {
        previewArea.classList.add('hidden');
        uploadPrompt.classList.remove('hidden');
    }

    Swal.fire({
        icon: 'info',
        title: 'File Removed',
        text: 'Passport document has been removed.',
        timer: 1500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}
</script>

@endsection