<div>
    <style>
        .step-circle {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            border: 2px solid #e5e7eb;
            background-color: #f9fafb;
            color: #6b7280;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .step-circle.active {
            background-color: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .step-circle.completed {
            background-color: #10b981;
            color: white;
            border-color: #10b981;
        }

        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }

        .step-line {
            width: 2rem;
            height: 2px;
            background-color: #e5e7eb;
            margin: 0 0.5rem;
        }

        .step-line.completed {
            background-color: #10b981;
        }
    </style>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
                    <div class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">
                        🚀 Livewire Powered
                    </div>
                </div>
                
                <div class="mb-6">
                    <div class="flex items-center mb-2">
                        <i class="w-5 h-5 mr-2 text-green-600">📄</i>
                        <h3 class="text-lg font-bold">Application for Sectional Titling - Main Application</h3>
                        <div class="ml-auto flex items-center">
                            <span class="text-gray-600 mr-2">Land Use:</span>
                            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                                {{ ucfirst($landUse) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-gray-600">Complete the form below to submit a new primary application for sectional titling</p>
                </div>

                <!-- Progress Steps -->
                <div class="flex items-center mb-8">
                    @for ($i = 1; $i <= $totalSteps; $i++)
                        <div class="flex items-center">
                            <div class="step-circle {{ $currentStep == $i ? 'active' : ($currentStep > $i ? 'completed' : '') }}" 
                                 wire:click="goToStep({{ $i }})">
                                {{ $i }}
                            </div>
                            @if ($i < $totalSteps)
                                <div class="step-line {{ $currentStep > $i ? 'completed' : '' }}"></div>
                            @endif
                        </div>
                    @endfor
                    <div class="ml-4">
                        Step {{ $currentStep }} - 
                        @switch($currentStep)
                            @case(1) Applicant Information @break
                            @case(2) Property Details @break
                            @case(3) Document Upload @break
                            @case(4) Buyers List @break
                            @case(5) Review & Submit @break
                        @endswitch
                    </div>
                </div>

                <!-- Form Steps -->
                <form wire:submit.prevent="submit">
                    <!-- Step 1: Applicant Information -->
                    <div class="form-section {{ $currentStep == 1 ? 'active' : '' }}">
                        <h4 class="text-lg font-semibold mb-4">Applicant Information</h4>
                        
                        <!-- Applicant Type -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Applicant Type</label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" wire:model="applicantType" value="individual" class="mr-2">
                                    Individual
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" wire:model="applicantType" value="corporate" class="mr-2">
                                    Corporate
                                </label>
                            </div>
                            @error('applicantType') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        @if ($applicantType == 'individual')
                            <!-- Individual Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                    <select wire:model="applicant_title" class="w-full p-2 border border-gray-300 rounded-md">
                                        <option value="">Select Title</option>
                                        <option value="Mr.">Mr.</option>
                                        <option value="Mrs.">Mrs.</option>
                                        <option value="Miss">Miss</option>
                                        <option value="Dr.">Dr.</option>
                                    </select>
                                    @error('applicant_title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                    <input type="text" wire:model="first_name" class="w-full p-2 border border-gray-300 rounded-md">
                                    @error('first_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                    <input type="text" wire:model="middle_name" class="w-full p-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Surname</label>
                                    <input type="text" wire:model="surname" class="w-full p-2 border border-gray-300 rounded-md">
                                    @error('surname') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        @if ($applicantType == 'corporate')
                            <!-- Corporate Fields -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Corporate Name</label>
                                    <input type="text" wire:model="corporate_name" class="w-full p-2 border border-gray-300 rounded-md">
                                    @error('corporate_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">RC Number</label>
                                    <input type="text" wire:model="rc_number" class="w-full p-2 border border-gray-300 rounded-md">
                                    @error('rc_number') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <!-- Contact Information -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                <input type="email" wire:model="owner_email" class="w-full p-2 border border-gray-300 rounded-md">
                                @error('owner_email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                                <input type="text" wire:model="phone_number" class="w-full p-2 border border-gray-300 rounded-md">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Property Details -->
                    <div class="form-section {{ $currentStep == 2 ? 'active' : '' }}">
                        <h4 class="text-lg font-semibold mb-4">Property Details</h4>
                        
                        <!-- Scheme Number (New Field) -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Scheme Number</label>
                            <input type="text" wire:model="scheme_number" class="w-full p-2 border border-gray-300 rounded-md" style="text-transform:uppercase">
                            @error('scheme_number') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Property Address -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Property Street Name</label>
                            <input type="text" wire:model="property_street_name" class="w-full p-2 border border-gray-300 rounded-md" style="text-transform:uppercase">
                            @error('property_street_name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Property LGA</label>
                            <select wire:model="property_lga" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="">Select LGA</option>
                                <option value="Kano Municipal">Kano Municipal</option>
                                <option value="Fagge">Fagge</option>
                                <option value="Dala">Dala</option>
                                <option value="Gwale">Gwale</option>
                                <option value="Tarauni">Tarauni</option>
                                <option value="Nassarawa">Nassarawa</option>
                                <option value="Ungogo">Ungogo</option>
                                <option value="Kumbotso">Kumbotso</option>
                            </select>
                            @error('property_lga') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                        </div>

                        <!-- Property Specifications -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of Units</label>
                                <input type="number" wire:model="units_count" min="1" class="w-full p-2 border border-gray-300 rounded-md">
                                @error('units_count') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of Blocks</label>
                                <input type="number" wire:model="blocks_count" min="1" class="w-full p-2 border border-gray-300 rounded-md">
                                @error('blocks_count') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Number of Floors</label>
                                <input type="number" wire:model="sections_count" min="1" class="w-full p-2 border border-gray-300 rounded-md">
                                @error('sections_count') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Generated File Number Display -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h5 class="font-medium text-blue-800 mb-2">Generated File Number</h5>
                            <p class="text-blue-700 font-mono text-lg">{{ $npFileNo }}</p>
                            <p class="text-blue-600 text-sm mt-1">This file number will be automatically assigned to your application</p>
                        </div>
                    </div>

                    <!-- Step 3: Document Upload -->
                    <div class="form-section {{ $currentStep == 3 ? 'active' : '' }}">
                        <h4 class="text-lg font-semibold mb-4">Document Upload</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Application Letter -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Application Letter</label>
                                <input type="file" wire:model="application_letter" accept=".pdf,.jpg,.jpeg,.png" class="w-full">
                                @error('application_letter') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                <div wire:loading wire:target="application_letter" class="text-blue-500 text-sm">Uploading...</div>
                            </div>

                            <!-- Building Plan -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Building Plan</label>
                                <input type="file" wire:model="building_plan" accept=".pdf,.jpg,.jpeg,.png" class="w-full">
                                @error('building_plan') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                <div wire:loading wire:target="building_plan" class="text-blue-500 text-sm">Uploading...</div>
                            </div>

                            <!-- Ownership Document -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Ownership Document</label>
                                <input type="file" wire:model="ownership_document" accept=".pdf,.jpg,.jpeg,.png" class="w-full">
                                @error('ownership_document') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                <div wire:loading wire:target="ownership_document" class="text-blue-500 text-sm">Uploading...</div>
                            </div>

                            <!-- Site Plan (Survey) - Updated Label -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Site Plan (Survey)</label>
                                <input type="file" wire:model="survey_plan" accept=".pdf,.jpg,.jpeg,.png" class="w-full">
                                @error('survey_plan') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                <div wire:loading wire:target="survey_plan" class="text-blue-500 text-sm">Uploading...</div>
                            </div>

                            <!-- ID Document -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">ID Document</label>
                                <input type="file" wire:model="id_document" accept=".pdf,.jpg,.jpeg,.png" class="w-full">
                                @error('id_document') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                <div wire:loading wire:target="id_document" class="text-blue-500 text-sm">Uploading...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Buyers List -->
                    <div class="form-section {{ $currentStep == 4 ? 'active' : '' }}">
                        <h4 class="text-lg font-semibold mb-4">Buyers List</h4>
                        
                        <!-- CSV Import Section -->
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                            <h5 class="font-medium text-gray-800 mb-3">CSV Import Option</h5>
                            
                            <div class="flex flex-col sm:flex-row gap-4 items-start">
                                <div class="flex-1">
                                    <input type="file" wire:model="csvFile" accept=".csv" class="w-full">
                                    @error('csvFile') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    <div wire:loading wire:target="csvFile" class="text-blue-500 text-sm mt-1">
                                        Processing CSV file...
                                    </div>
                                </div>
                                
                                <button type="button" wire:click="downloadTemplate" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 whitespace-nowrap">
                                    📥 Download Template
                                </button>
                            </div>
                            
                            @if ($csvProcessing)
                                <div class="mt-3 text-blue-600">
                                    <div class="flex items-center">
                                        <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Processing CSV file...
                                    </div>
                                </div>
                            @endif
                            
                            @if ($csvError)
                                <div class="mt-3 text-red-600 text-sm">{{ $csvError }}</div>
                            @endif
                        </div>

                        <!-- Buyers List -->
                        <div class="space-y-4">
                            @error('buyers_duplicate')
                                <div class="p-3 bg-red-100 text-red-700 rounded">{{ $message }}</div>
                            @enderror
                            @foreach ($buyers as $index => $buyer)
                                <div class="border border-gray-300 rounded-lg p-4 bg-white">
                                    <div class="flex justify-between items-center mb-3">
                                        <h6 class="font-medium text-gray-800">Buyer {{ $index + 1 }}</h6>
                                        @if (count($buyers) > 1)
                                            <button type="button" wire:click="removeBuyer({{ $index }})" class="text-red-500 hover:text-red-700">
                                                Remove
                                            </button>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                            <select wire:model="buyers.{{ $index }}.buyerTitle" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                                <option value="">Select</option>
                                                <option value="Mr.">Mr.</option>
                                                <option value="Mrs.">Mrs.</option>
                                                <option value="Miss">Miss</option>
                                                <option value="Dr.">Dr.</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.firstName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.middleName" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Surname</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.surname" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" wire:model="buyers.{{ $index }}.buyerEmail" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                            @error('buyers.' . $index . '.buyerEmail') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.buyerPhone" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                            @error('buyers.' . $index . '.buyerPhone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit Number</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.unitNumber" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                            @error('buyers.' . $index . '.unitNumber') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit Type</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.unitType" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit Measurement</label>
                                            <input type="text" wire:model="buyers.{{ $index }}.unitMeasurement" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                            @error('buyers.' . $index . '.unitMeasurement') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                        <textarea wire:model="buyers.{{ $index }}.buyerAddress" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-sm"></textarea>
                                    </div>
                                </div>
                            @endforeach

                            <button type="button" wire:click="addBuyer" class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                                Add Another Buyer
                            </button>
                        </div>
                    </div>

                    <!-- Step 5: Review & Submit -->
                    <div class="form-section {{ $currentStep == 5 ? 'active' : '' }}">
                        <h4 class="text-lg font-semibold mb-4">Review & Submit</h4>
                        
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-6">
                            <h5 class="font-medium text-gray-800 mb-4">Application Summary</h5>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h6 class="font-medium text-gray-700 mb-2">Applicant Information</h6>
                                    <p class="text-sm text-gray-600">Type: {{ ucfirst($applicantType) }}</p>
                                    @if ($applicantType == 'individual')
                                        <p class="text-sm text-gray-600">Name: {{ $applicant_title }} {{ $first_name }} {{ $middle_name }} {{ $surname }}</p>
                                    @else
                                        <p class="text-sm text-gray-600">Company: {{ $corporate_name }}</p>
                                        <p class="text-sm text-gray-600">RC Number: {{ $rc_number }}</p>
                                    @endif
                                    <p class="text-sm text-gray-600">Email: {{ $owner_email }}</p>
                                </div>
                                
                                <div>
                                    <h6 class="font-medium text-gray-700 mb-2">Property Details</h6>
                                    <p class="text-sm text-gray-600">File Number: {{ $npFileNo }}</p>
                                    <p class="text-sm text-gray-600">Scheme: {{ $scheme_number }}</p>
                                    <p class="text-sm text-gray-600">Units: {{ $units_count }}</p>
                                    <p class="text-sm text-gray-600">Blocks: {{ $blocks_count }}</p>
                                    <p class="text-sm text-gray-600">Floors: {{ $sections_count }}</p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="font-medium text-gray-700 mb-2">Buyers ({{ count($buyers) }})</h6>
                                <div class="max-h-40 overflow-y-auto">
                                    @foreach ($buyers as $index => $buyer)
                                        <p class="text-sm text-gray-600">{{ $index + 1 }}. {{ $buyer['buyerTitle'] }} {{ $buyer['firstName'] }} {{ $buyer['surname'] }} - Unit {{ $buyer['unitNumber'] }}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="flex justify-between mt-8">
                        <button type="button" wire:click="previousStep" 
                                @if($currentStep == 1) disabled @endif
                                class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed">
                            Previous
                        </button>

                        @if ($currentStep < $totalSteps)
                            <button type="button" wire:click="nextStep" 
                                    class="bg-blue-500 text-white px-6 py-2 rounded-md hover:bg-blue-600">
                                Next
                            </button>
                        @else
                            <button type="submit" 
                                    class="bg-green-500 text-white px-6 py-2 rounded-md hover:bg-green-600">
                                Submit Application
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('success'))
        <div class="flash-message fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="flash-message fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
            {{ session('error') }}
        </div>
    @endif
</div>
