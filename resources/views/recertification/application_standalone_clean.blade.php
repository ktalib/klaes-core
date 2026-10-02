@extends('layouts.app')
@section('page-title')
    {{ __('Recertification Application Form') }}
@endsection

@section('content')
<script>
// Tailwind config
tailwind.config = {
  theme: { 
    extend: {
      colors: {
        primary: '#3b82f6',
        'primary-foreground': '#ffffff',
        muted: '#f3f4f6',
        'muted-foreground': '#6b7280',
        border: '#e5e7eb',
        destructive: '#ef4444',
        'destructive-foreground': '#ffffff',
        secondary: '#f1f5f9',
        'secondary-foreground': '#0f172a',
      }
    }
  }
}
</script>

@include('recertification.css.form_css')

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include('admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="container mx-auto py-6 space-y-6 max-w-7xl px-4 sm:px-6 lg:px-8">
            
            <!-- Header with Back Button -->
            <div class="flex items-center gap-4 mb-6">
                <a href="{{ url('/recertification') }}" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 gap-2">
                    <i data-lucide="arrow-left" class="h-4 w-4"></i>
                    Back to Applications
                </a>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900">New Recertification Application</h1>
                    <p class="text-gray-600">Complete the form below to submit a new recertification application</p>
                </div>
           
            </div>

            
            <!-- Application Form -->
            <div class="bg-white rounded-lg shadow-xl border border-gray-200">
                <!-- Header -->
                <div class="p-6 border-b border-gray-200 text-center">
                    <div class="space-y-1">
                        <!-- Main Header - Centered -->
                        <div class="font-bold text-lg">KANO STATE GEOGRAPHIC INFORMATION SYSTEMS (KANGIS)</div>
                        <div class="text-sm text-gray-600">MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE</div>
                        <div class="text-sm font-semibold">APPLICATION FOR RE-CERTIFICATION OR RE-ISSUANCE OF C-of-O</div>
                        <div id="form-type-display" class="text-xs text-gray-500">INDIVIDUAL FORM</div>
                    </div>
                    
                    <!-- File Numbers Display - Centered Below Header -->
                    <div class="mt-4">
                        <div class="bg-blue-50 px-3 py-2 rounded-lg border border-blue-200 inline-block">
                            <div class="text-xs font-medium text-blue-700 uppercase tracking-wide text-center mb-1">New KANGIS File No</div>
                            <div class="flex items-center gap-2 justify-center">
                                <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                                <span id="new-kangis-file-number-display" class="text-sm font-bold text-blue-900 font-mono">Loading...</span>
                            </div>
                            
                        </div>
                    </div>
                </div>
                
                <!-- Step Indicator -->
                <div class="p-6 pb-0">
                    <div class="step-indicator">
                        <div id="step-1" class="step-circle active">1</div>
                        <div id="line-1" class="step-line inactive"></div>
                        <div id="step-2" class="step-circle inactive">2</div>
                        <div id="line-2" class="step-line inactive"></div>
                        <div id="step-3" class="step-circle inactive">3</div>
                        <div id="line-3" class="step-line inactive"></div>
                        <div id="step-4" class="step-circle inactive">4</div>
                        <div id="line-4" class="step-line inactive"></div>
                        <div id="step-5" class="step-circle inactive">5</div>
                        <div id="line-5" class="step-line inactive"></div>
                        <div id="step-6" class="step-circle inactive">6</div>
                        <div id="line-6" class="step-line inactive"></div>
                        <div id="step-7" class="step-circle inactive">7</div>
                        <div id="line-7" class="step-line inactive"></div>
                        <div id="step-8" class="step-circle inactive">8</div>
                    </div>
                </div>

                <div class="p-6">
                    <form id="recertification-form" method="POST" action="{{ route('recertification.application.store') }}" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- Include Step Partials -->
                        @include('recertification.steps.step1_personal_details')
                        @include('recertification.steps.step2_contact_details')
                        @include('recertification.steps.step3_title_holder')
                        @include('recertification.steps.step4_mortgage_encumbrance')
                        @include('recertification.steps.step5_plot_details')
                        @include('recertification.steps.step6_document_uploads')
                        @include('recertification.steps.step7_payment_terms')
                        @include('recertification.steps.step7_application_summary')
                        
                    </form>

                    <!-- Form Navigation -->
                    <div class="flex justify-between pt-4 border-t">
                        <button
                            type="button"
                            id="prev-btn"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Previous
                        </button>
                        
                        <button
                            type="button"
                            id="next-btn"
                            class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="next-text">Next</span>
                            <div class="loading-spinner hidden"></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    @include('admin.footer')
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
    <!-- Toast messages will be inserted here -->
</div>

@include('recertification.js.standalone_form_js')

@endsection