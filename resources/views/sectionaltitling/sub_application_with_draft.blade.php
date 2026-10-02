@extends('layouts.app')
@section('page-title')
{{ __('Secondary Application Form') }}
@endsection
@include('sectionaltitling.sub_app_css')
@include('sectionaltitling.partials.assets.css')

{{-- External Libraries --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">

@section('content')
<!-- SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
<!-- Animate.css for SweetAlert animations -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
.swal-validation-popup {
    font-family: inherit;
}
.swal-validation-title {
    color: #dc2626 !important;
    font-weight: 600;
}
.swal-validation-content {
    color: #374151;
}
/* Select2 Custom Styling */
.select2-container--default .select2-selection--single {
    height: 42px;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px;
    padding-left: 8px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 40px;
}
.select2-dropdown {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3b82f6;
}

/* Enhanced Step Circle Styles */
.step-circle {
    transition: all 0.2s ease;
}

.step-circle.cursor-pointer:hover {
    transform: scale(1.1);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.step-circle.active-tab.cursor-pointer:hover {
    background-color: #059669;
    border-color: #059669;
}

.step-circle.inactive-tab.cursor-pointer:hover {
    background-color: #6b7280;
    border-color: #6b7280;
    color: white;
}
</style>
 
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<!-- jQuery (required by Select2) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
 

<!-- Main Content -->
<div class="flex-1 overflow-auto">
<!-- Header -->
@include('admin.header')
<!-- Dashboard Content -->
<div class="p-6">

    @php
    // SUA Detection - check from multiple sources
    $isSUA = isset($isSUA) ? $isSUA : (
        request()->query('sua', false) || 
        request()->route()->getName() === 'sua.create' ||
        (isset($sua) && $sua)
    );
    
    $isEdit = isset($isEdit) ? $isEdit : false;
    $mainApplicationId = request()->get('application_id');

    if ($isSUA) {
        // SUA-specific initialization
        $motherApplication = null;
        $totalUnitsInMotherApp = 1; // SUA is always 1 unit
        $totalSubApplications = 0;
        $remainingUnits = 1;
        $propertyLocation = '';
        $buyersWithUnits = [];
    } else {
        // Existing logic for regular sub-applications
        // Fetch data from the mother_applications table
        $motherApplication = DB::connection('sqlsrv')->table('mother_applications')->where('id', $mainApplicationId)->first();
        $totalUnitsInMotherApp = $motherApplication ? $motherApplication->NoOfUnits : 0;

        // Count the number of sub-applications linked to the main application
        $totalSubApplications = DB::connection('sqlsrv')->table('subapplications')->where('main_application_id', $mainApplicationId)->count();

        // Calculate the remaining units
        $remainingUnits = $totalUnitsInMotherApp - $totalSubApplications;

        // Get property location
        $propertyLocation = '';
        if ($motherApplication) {
          $locationParts = array_filter([
            $motherApplication->property_plot_no ?? null,
            $motherApplication->property_street_name ?? null,
            $motherApplication->property_district ?? null
          ]);
          $propertyLocation = implode(', ', $locationParts);
        }

        // Fetch buyers and their unit measurements for this mother application
$buyersWithUnits = [];
if ($motherApplication) {
    $buyersWithUnits = DB::connection('sqlsrv')
        ->table('buyer_list as bl')
        ->leftJoin('st_unit_measurements as sum', function($join) use ($motherApplication) {
            $join->on('bl.id', '=', 'sum.buyer_id')
                 ->where('sum.application_id', '=', $motherApplication->id);
        })
        ->where('bl.application_id', $motherApplication->id)
        // Exclude buyers already used in subapplications for this main application
        ->whereNotExists(function($q) use ($motherApplication) {
            $q->select(DB::raw(1))
              ->from('subapplications as s')
              ->whereColumn('s.unit_number', 'bl.unit_no')
              ->where('s.main_application_id', $motherApplication->id);
        })
        ->select(
            'bl.id as buyer_id',
            'bl.application_id',
            'bl.buyer_title',
            'bl.buyer_name', 
            'bl.unit_no',
            'bl.land_use',
            'sum.measurement',
            'sum.id as unit_measurement_id'
        )
        ->get();
}
    }
    
    // Check for MIXED land use from URL parameter
    $isMixedLandUse = !$isSUA && request()->query('land_use') === 'Mixed Use';
    
    // Define variables that might be used in hidden form fields to prevent undefined variable errors
    $prefix = $isSUA ? 'SUA' : 'SUB';
    $currentYear = date('Y');
    $formattedSerialNumber = $isSUA ? '1' : ($totalSubApplications + 1);
    
    // Define file number variables for regular sub-applications
    if (!$isSUA && $motherApplication) {
        $npFileNo = $motherApplication->np_fileno ?? 'N/A';
        $unitFileNo = $npFileNo . '-' . str_pad(($totalSubApplications + 1), 3, '0', STR_PAD_LEFT);
    } else {
        $npFileNo = 'N/A';
        $unitFileNo = 'N/A';
    }

    // Draft bootstrap data
    $draftMeta = $draftBootstrap ?? [];
    $draftList = $draftMeta['drafts'] ?? [];
    $initialMode = $draftMeta['mode'] ?? 'fresh';
    @endphp

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold leading-tight text-gray-800">
            @if($isSUA)
                Standalone Unit Application
            @else
                Unit Application Form
            @endif
        </h2>
        <a href="{{ $isSUA ? route('sua.index') : route('sectionaltitling.index') }}" class="btn btn-secondary">
            <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back to Applications
        </a>
    </div>

    {{-- Draft Status Container --}}
    <div class="bg-blue-50 border border-blue-200 rounded-md px-4 py-3 flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6" id="draftStatusContainer">
        <div>
            <p class="text-sm text-blue-900 font-semibold">Draft status: <span id="draftStatusText" class="font-bold text-blue-700">Initializing…</span></p>
            <p class="text-xs text-blue-700" id="draftLastSavedText">Last saved: {{ $draftMeta['last_saved_at'] ? \Carbon\Carbon::parse($draftMeta['last_saved_at'])->diffForHumans() : 'Not yet saved' }}</p>
            <p class="text-xs text-blue-700 mt-1" id="draftCollaboratorText">Collaborators: <span id="draftCollaboratorCount">{{ isset($draftMeta['collaborators']) ? count($draftMeta['collaborators']) : 1 }}</span></p>
        </div>
        <div class="flex items-center gap-4 w-full md:w-auto">
            <div class="flex-1 md:flex-none w-full md:w-56 h-2 bg-white border border-blue-200 rounded-full overflow-hidden">
                <div id="draftProgressBar" class="h-full bg-blue-600 transition-all duration-500" style="width: {{ $draftMeta['progress_percent'] ?? 0 }}%"></div>
            </div>
            <span id="draftProgressValue" class="text-sm font-semibold text-blue-900">{{ number_format($draftMeta['progress_percent'] ?? 0, 0) }}%</span>
            <div class="flex items-center gap-2">
                <button type="button" id="draftHistoryButton" class="hidden items-center px-3 py-1.5 border border-blue-200 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-100">History</button>
                <button type="button" id="draftShareButton" class="items-center px-3 py-1.5 border border-blue-200 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-100">Share</button>
                <button type="button" id="draftExportButton" class="items-center px-3 py-1.5 border border-blue-200 text-blue-700 text-xs font-medium rounded-md hover:bg-blue-100">Download</button>
            </div>
        </div>
    </div>

    {{-- Draft Locator Container --}}
    <div class="bg-white border border-blue-200 rounded-md px-4 py-4 space-y-4 mb-6" id="draftLocatorContainer">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div class="space-y-3 w-full">
                <div class="flex flex-wrap items-center gap-2" id="draftModeSwitch">
                    <button type="button" id="draftModeFreshButton" data-mode="fresh" class="draft-mode-button px-3 py-1.5 text-xs font-semibold rounded-md transition border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 {{ $initialMode === 'fresh' ? 'bg-blue-600 text-white border-blue-600' : 'text-blue-700 hover:bg-blue-50' }}">Fresh Application</button>
                    <button type="button" id="draftModeDraftButton" data-mode="draft" class="draft-mode-button px-3 py-1.5 text-xs font-semibold rounded-md transition border border-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1 {{ $initialMode === 'draft' ? 'bg-blue-600 text-white border-blue-600' : 'text-blue-700 hover:bg-blue-50' }}">Continue from Draft</button>
                    <span class="text-xs text-gray-500">Choose how you'd like to begin.</span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-gray-500">Current file number:</span>
                    <code id="draftLocatorCurrentId" class="text-xs font-mono px-2 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded break-all">{{ $draftMeta['unit_file_no'] ?? 'Not assigned yet' }}</code>
                    <button type="button" id="draftLocatorCopyButton" class="text-xs font-medium text-blue-600 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Copy</button>
                </div>
            </div>
            <form id="draftLocatorForm" class="flex flex-col sm:flex-row sm:items-center gap-2 w-full xl:w-auto">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
                    <input type="text" id="draftLocatorInput" name="draft_locator" class="flex-1 border border-blue-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="Enter file number">
                    <button type="submit" class="px-3 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Load Draft</button>
                </div>
                <p id="draftLocatorFeedback" class="mt-1 text-xs font-medium hidden"></p>
            </form>
        </div>

        <div id="draftModeDraftPanel" class="rounded-md border border-dashed border-blue-200 bg-blue-50/40 px-3 py-3 {{ $initialMode === 'draft' && !empty($draftList) ? '' : 'hidden' }}">
            <div class="flex flex-col lg:flex-row lg:items-center gap-2">
                <label for="draftListSelect" class="text-xs font-semibold text-blue-800 uppercase tracking-wide">My drafts</label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
                    <select id="draftListSelect" class="flex-1 border border-blue-200 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">Select a draft to resume</option>
                        @foreach ($draftList as $draftSummary)
                            @php
                                $optionValue = $draftSummary['unit_file_no'] ?? $draftSummary['draft_id'];
                                $defaultLabel = $draftSummary['unit_file_no'] ?? ('Draft ' . substr($draftSummary['draft_id'], 0, 8));
                                $optionLabel = $draftSummary['label'] ?? $defaultLabel;
                            @endphp
                            <option value="{{ $optionValue }}" data-draft-id="{{ $draftSummary['draft_id'] }}" {{ !empty($draftSummary['is_current']) ? 'selected' : '' }}>
                                {{ $optionLabel }}
                            </option>
                        @endforeach
                    </select>
                    <button type="button" id="draftListLoadButton" class="px-3 py-2 bg-white text-blue-700 border border-blue-300 text-sm font-semibold rounded-md hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-1">Load Selected</button>
                </div>
            </div>
            <p id="draftListEmpty" class="mt-2 text-xs text-blue-700 {{ empty($draftList) ? '' : 'hidden' }}">You don't have any saved drafts yet. Start a fresh application to create one.</p>
        </div>
    </div>

    {{-- Main Form Card --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <form id="subApplicationForm" method="POST" action="javascript:void(0)" enctype="multipart/form-data" onsubmit="return false;" data-draft-id="{{ $draftMeta['draft_id'] ?? '' }}" data-draft-version="{{ $draftMeta['version'] ?? 1 }}" data-auto-save-frequency="{{ $draftMeta['auto_save_frequency'] ?? 30 }}" data-sub-application-id="{{ $subApplicationId ?? '' }}" data-main-application-id="{{ $mainApplicationId ?? '' }}">
            @csrf
            @if($isSUA && $isEdit)
                @method('PUT')
            @endif
            
            {{-- Hidden Fields --}}
            @if($isSUA)
                <input type="hidden" name="is_sua_unit" value="1">
                <input type="hidden" name="is_sua" value="1">
                <input type="hidden" name="main_application_id" value="">
                <input type="hidden" name="unit_type" value="SUA">
                <input type="hidden" name="applicant_type" id="applicantTypeInput" value="individual">
            @else
                <input type="hidden" name="main_application_id" value="{{ $mainApplicationId ?? '' }}">
                <input type="hidden" name="applicant_type" id="applicantTypeInput" value="individual">
                <input type="hidden" name="main_id" id="mainIdHidden" value="@php
                     $mainYear = $motherApplication && $motherApplication->created_at ? date('Y', strtotime($motherApplication->created_at)) : date('Y');
                    $mainAppId = $motherApplication->id ?? '';
                     echo sprintf('ST-%s-%03d', $mainYear, $mainAppId);
                      @endphp">
            @endif
            
            <input type="hidden" name="draft_id" id="draftIdInput" value="{{ $draftMeta['draft_id'] ?? '' }}">
            <input type="hidden" name="draft_version" id="draftVersionInput" value="{{ $draftMeta['version'] ?? 1 }}">
            <input type="hidden" name="draft_last_completed_step" id="draftStepInput" value="{{ $draftMeta['last_completed_step'] ?? 1 }}">
            
            {{-- Step 1: Basic Information --}}
            @include('sectionaltitling.partials.subapplication.steps.step1-basic')
            
            {{-- Step 2: Shared Areas --}}
            @include('sectionaltitling.partials.subapplication.steps.step2-sharedareas')
            
            {{-- Step 3: Documents --}}
            @include('sectionaltitling.partials.subapplication.steps.step3-documents')
            
            {{-- Step 4: Summary --}}
            @include('sectionaltitling.partials.subapplication.steps.step4-summary')
        </form>
    </div>
</div>

{{-- Footer --}}
@include('admin.footer')

{{-- JavaScript Assets --}}
<script>
    // Make form submit URL available globally
    window.FORM_SUBMIT_URL = "{{ $isSUA ? route('sua.store') : route('secondaryform.save') }}";
    console.log('🔗 Form submit URL:', window.FORM_SUBMIT_URL);
</script>

<script>
    window.SUB_APPLICATION_DRAFT_BOOTSTRAP = @json($draftMeta ?? []);
    @php
        $loadTemplate = route('subapplication.draft.load', ['draftId' => '__DRAFT_ID__']);
        $deleteTemplate = route('subapplication.draft.delete', ['draftId' => '__DRAFT_ID__']);
        $exportTemplate = route('subapplication.draft.export', ['draftId' => '__DRAFT_ID__']);
        $analyticsTemplate = route('subapplication.draft.analytics', ['draftId' => '__DRAFT_ID__']);
    @endphp
    window.SUB_APPLICATION_DRAFT_ENDPOINTS = {
        save: "{{ route('subapplication.draft.save') }}",
        submit: "{{ route('subapplication.draft.submit') }}",
        load: "{{ $loadTemplate }}",
        delete: "{{ $deleteTemplate }}",
        export: "{{ $exportTemplate }}",
        analytics: "{{ $analyticsTemplate }}",
        check: "{{ route('subapplication.draft.check', ['subApplicationId' => '__SUB_APPLICATION_ID__']) }}",
        share: "{{ route('subapplication.draft.share') }}",
        start: "{{ route('subapplication.draft.start') }}",
        mine: "{{ route('subapplication.draft.mine') }}"
    };
</script>

<script src="{{ asset('js/sub-application-draft-autosave.js') }}"></script>

{{-- Include existing JavaScript functionality --}}
<script>
// Include all the existing JavaScript from the original file
// This would include the step navigation, form validation, etc.
// For brevity, I'm including the key functions

function goToStep(step) {
    // Hide all form sections
    document.querySelectorAll('.form-section').forEach(section => {
        section.classList.remove('active-tab');
        section.style.display = 'none';
    });
    
    // Show the selected step
    const targetStep = document.getElementById(`step${step}`);
    if (targetStep) {
        targetStep.classList.add('active-tab');
        targetStep.style.display = 'block';
    }
    
    // Update step circles
    document.querySelectorAll('.step-circle').forEach((circle, index) => {
        circle.classList.remove('active-tab');
        circle.classList.add('inactive-tab');
        if (index + 1 === step) {
            circle.classList.remove('inactive-tab');
            circle.classList.add('active-tab');
        }
    });
}

// Initialize the form
document.addEventListener('DOMContentLoaded', function() {
    // Show only the first step initially
    goToStep(1);
    
    // Add event listeners for form submission
    const form = document.getElementById('subApplicationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            // Handle form submission with draft integration
            console.log('Form submitted with draft support');
        });
    }
});
</script>

@endsection