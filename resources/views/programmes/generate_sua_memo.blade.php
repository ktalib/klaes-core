@extends('layouts.app')

@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
    @php
        $applicantType = strtolower($suaApplication->applicant_type ?? '');
        $applicantLabel = match ($applicantType) {
            'corporate' => 'Corporate Name',
            'multiple' => 'Lead Applicant / Primary Contact',
            default => 'Applicant Name',
        };
        $applicantHelperText = match ($applicantType) {
            'corporate' => 'Auto-filled from the corporate applicant information captured on the SUA form.',
            'multiple' => 'Auto-filled from the multiple owners list captured for this SUA. Edit only if you need to override the generated lead name.',
            default => 'Auto-filled from the individual applicant details captured on the SUA form.',
        };

        $defaultPropertyLocation = trim((string) ($propertyLocation ?? ''));
        if ($defaultPropertyLocation === '' && !empty($suaApplication->unit_lga ?? $suaApplication->mother_lga)) {
            $defaultPropertyLocation = trim((string) ($suaApplication->unit_lga ?? $suaApplication->mother_lga));
        }
        $propertyLocationForDisplay = $defaultPropertyLocation !== '' ? $defaultPropertyLocation : 'N/A';

        $ownerNamesArray = isset($ownerNames) && is_array($ownerNames)
            ? array_values(array_filter(array_map('trim', $ownerNames)))
            : [];

        if ((empty($ownerName) || !is_string($ownerName)) && count($ownerNamesArray) > 0) {
            $ownerName = $ownerNamesArray[0];
        }

        $ownerName = is_string($ownerName) ? trim($ownerName) : '';
        $ownerNameDisplay = $ownerName !== '' ? $ownerName : 'Applicant Name Not Provided';
    @endphp

    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="p-6">
            <div class="bg-white rounded-md shadow-sm border border-gray-200">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800">{{ $PageTitle }}</h1>
                            <p class="text-gray-600">{{ $PageDescription }}</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="{{ route('programmes.unit_nonscheme_memo') }}" 
                               class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md transition-colors">
                                <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i>
                                Back to List
                            </a>
                        </div>
                    </div>

                    <!-- Application Info Card -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                        <div class="flex items-start">
                            <i data-lucide="info" class="w-6 h-6 text-blue-500 mt-1 mr-4"></i>
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-blue-900 mb-3">SUA Application Details</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">File Number:</label>
                                        <p class="text-blue-900 font-medium">{{ $suaApplication->fileno ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Applicant Type:</label>
                                        <p class="text-blue-900 font-medium">{{ $suaApplication->applicant_type ?? 'N/A' }}</p>
                                    </div>
                                    {{-- <div>
                                        <label class="text-sm font-medium text-blue-700">Primary File:</label>
                                        <p class="text-blue-900">{{ $suaApplication->primary_fileno ?? 'N/A' }}</p>
                                    </div> --}}
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Applicant Name:</label>
                                        <p class="text-blue-900 font-medium">{{ $ownerNameDisplay }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Unit Type:</label>
                                        <p class="text-blue-900">Standalone Unit Application (SUA)</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Unit Details:</label>
                                        <p class="text-blue-900">
                                           
                                            @if($suaApplication->block_number)Block: {{ $suaApplication->block_number }}, @endif
                                            @if($suaApplication->floor_number)Floor: {{ $suaApplication->floor_number }}, @endif
                                            @if($suaApplication->unit_number)Unit: {{ $suaApplication->unit_number }}@endif
                                        </p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Unit Size:</label>
                                        <p class="text-blue-900">{{ $suaApplication->unit_size ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">LGA:</label>
                                        <p class="text-blue-900">{{ $suaApplication->unit_lga ?? $suaApplication->mother_lga ?? 'N/A' }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Property Location:</label>
                                        <p class="text-blue-900">{{ $propertyLocationForDisplay }}</p>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-blue-700">Land Use:</label>
                                        <p class="text-blue-900">{{ $suaApplication->land_use ?? $suaApplication->mother_land_use ?? 'N/A' }}</p>
                                    </div>
                                    @if($applicantType === 'multiple' && count($ownerNamesArray) > 0)
                                        <div class="md:col-span-2">
                                            <label class="text-sm font-medium text-blue-700">Captured Owners:</label>
                                            <ul class="mt-1 text-blue-900 list-disc list-inside text-sm space-y-0.5">
                                                @foreach($ownerNamesArray as $owner)
                                                    <li>{{ $owner }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Auto-Generation Notice -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 hidden">
                        <div class="flex items-start">
                            <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mt-0.5 mr-3"></i>
                            <div>
                                <h4 class="text-sm font-medium text-green-800">Auto-Generation</h4>
                                <p class="text-sm text-green-700 mt-1">
                                    The system will automatically generate the following numbers and content for this memo:
                                </p>
                                <ul class="list-disc list-inside text-sm text-green-700 mt-2 space-y-1">
                                    <li><strong>Memo Number:</strong> {{ $memoNumber }}</li>
                                    <li><strong>Certificate Number:</strong> {{ $certificateNumber }}</li>
                                    <li><strong>Memo Content:</strong> Based on SUA application details</li>
                                    <li><strong>Planner Recommendation:</strong> Standard recommendation text</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Generate Form -->
                    <form method="POST" action="{{ route('programmes.generate_sua_memo') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="unit_id" value="{{ $suaApplication->id }}">
                        
                        <!-- Memo Details -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Certificate Details -->
                            <div class="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
                                <h3 class="font-semibold text-lg text-gray-800 border-b border-gray-200 pb-3 mb-4 flex items-center">
                                    <i data-lucide="file-text" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Certificate Details
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Certificate Number <span class="text-red-500">*</span></label>
                                        <input type="text" name="certificate_number" value="{{ old('certificate_number', $suaApplication->fileno ?? $certificateNumber) }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Application PageNo <span class="text-red-500">*</span></label>
                                        <input type="text" name="page_no" value="1" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                    </div>
                                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Site Plan PageNo <span class="text-red-500">*</span></label>
                                          <input type="text" name="site_plan_no" value="" required
                                              class="w-full p-2 border border-gray-300 rounded-md text-sm">
                            </div>

                                              <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Arc Design PageNo <span class="text-red-500">*</span></label>
                                <div class="flex space-x-2">
                                    <input type="text" name="arc_design_page_no" id="arc_design_page_no" 
                                           value="the Back Cover" required
                                           class="flex-1 p-2 border border-gray-300 rounded-md text-sm">
                                    <button type="button" onclick="resetArcDesignPageNo()" 
                                            class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md transition-colors duration-150 ease-in-out border border-gray-300">
                                        Reset
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Default: "the Back Cover" or specify page number (e.g., "page 45")</p>
                            </div>

                  

                             <script>
                                function resetArcDesignPageNo() {
                                    const input = document.getElementById('arc_design_page_no');
                                    if (input.value === 'the Back Cover') {
                                        input.value = 'page ';
                                        input.focus();
                                        input.setSelectionRange(5, 5); // Position cursor after "page "
                                    } else {
                                        input.value = 'the Back Cover';
                                    }
                                }
                            </script>
                            
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $applicantLabel }} <span class="text-red-500">*</span></label>
                                        <input type="text" name="applicant_name" value="{{ old('applicant_name', $ownerName) }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                        <p class="mt-1 text-xs text-gray-500">{{ $applicantHelperText }}</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Property Location <span class="text-red-500">*</span></label>
                                        <input type="text" name="property_location" 
                                               value="{{ old('property_location', $defaultPropertyLocation) }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                        <p class="mt-1 text-xs text-gray-500">Auto-filled from the property location captured on the SUA unit application.</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Allocation Slip No <span class="text-red-500">*</span></label>
                         <input type="text" name="allocation_ref_no" value="{{ old('allocation_ref_no', $suaApplication->allocation_ref_no ?? '') }}"
                             class="w-full p-2 border border-gray-300 rounded-md text-sm uppercase"
                             oninput="this.value = this.value.toUpperCase()" required>
                                        <p class="mt-1 text-xs text-gray-500">Enter the slip number from the originating allocation authority.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tenure Details -->
                            <div class="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
                                <h3 class="font-semibold text-lg text-gray-800 border-b border-gray-200 pb-3 mb-4 flex items-center">
                                    <i data-lucide="calendar" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Tenure Details
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Commencement Date <span class="text-red-500">*</span></label>
                                        <input type="date" name="commencement_date" 
                                               value="{{ $suaApplication->approval_date ? date('Y-m-d', strtotime($suaApplication->approval_date)) : date('Y-m-d') }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Term (Years) <span class="text-red-500">*</span></label>
                                        <input type="number" name="term_years" 
                                               value="{{ (($suaApplication->land_use ?? $suaApplication->mother_land_use) === 'Residential') ? 25 : 40 }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Residual Years <span class="text-red-500">*</span></label>
                                        <input type="number" name="residual_years" 
                                               value="{{ (($suaApplication->land_use ?? $suaApplication->mother_land_use) === 'Residential') ? 25 : 40 }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date <span class="text-red-500">*</span></label>
                                        <input type="date" name="expiry_date" 
                                               value="{{ $suaApplication->approval_date ? date('Y-m-d', strtotime($suaApplication->approval_date . ' + ' . ((($suaApplication->land_use ?? $suaApplication->mother_land_use) === 'Residential') ? 25 : 40) . ' years')) : date('Y-m-d', strtotime('+40 years')) }}" 
                                               class="w-full p-2 border border-gray-300 rounded-md text-sm" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Planning Recommendation -->
                        <div class="bg-white p-5 rounded-lg border border-gray-100 shadow-sm">
                            <h3 class="font-semibold text-lg text-gray-800 border-b border-gray-200 pb-3 mb-4 flex items-center">
                                <i data-lucide="clipboard-check" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Planning Recommendation
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Planning Recommendation <span class="text-red-500">*</span></label>
                                    <textarea name="planner_recommendation" rows="4" required
                                        class="w-full p-2 border border-gray-300 rounded-md text-sm">The application was referred to One stop shop for planning, engineering as well as architectural views. The planners recommended the application because it is feasible, and the unit meets the minimum requirements for {{ strtolower($suaApplication->land_use ?? $suaApplication->mother_land_use ?? 'commercial') }} titles.</textarea>
                                </div>
                                
                                <div class="flex items-center">
                                    <input type="checkbox" id="is_planning_recommended" name="is_planning_recommended" value="1" checked
                                           class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="is_planning_recommended" class="text-sm text-gray-700">
                                        Planning Department Recommends this Application
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hidden Fields -->
                        <input type="hidden" name="application_id" value="{{ $suaApplication->id }}">
                        <input type="hidden" name="memo_type" value="SUA">
                        <input type="hidden" name="memo_no" value="{{ $memoNumber }}">

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('programmes.unit_nonscheme_memo') }}" 
                               class="px-6 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center"
                                    id="generateBtn">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                                Generate SUA Memo
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action*="generate-sua-memo"]');
    const generateBtn = document.getElementById('generateBtn');
    
    if (form && generateBtn) {
        form.addEventListener('submit', function(e) {
            const originalText = generateBtn.innerHTML;
            
            generateBtn.disabled = true;
            generateBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i> Generating...';
            
            // Re-enable button after 10 seconds to prevent permanent disable on validation errors
            setTimeout(function() {
                generateBtn.disabled = false;
                generateBtn.innerHTML = originalText;
            }, 10000);
        });
    }
});
</script>
@endsection