{{-- Commission New File Number Modal Component --}}
{{-- 
    Usage: @include('components.commission-fileno-modal', ['config' => [...]])
    
    Configuration Options:
    - modalId: (optional) Custom ID for the modal element, default: 'generateModal'
    - formAction: (optional) Custom form submission URL
    - onSuccess: (optional) JavaScript callback function name after successful submission
--}}

@php
    // Get required data for the modal
    $modalId = $config['modalId'] ?? 'generateModal';
    $formAction = $config['formAction'] ?? route('file-numbers.store');
    $successCallback = $config['onSuccess'] ?? null;
    
    // Fetch required data if not provided
    if (!isset($lgas)) {
        $lgas = DB::connection('sqlsrv')->table('lgas')->select('name')->orderBy('name')->get();
    }
    
    if (!isset($landUses')) {
        $landUses = \App\Models\LandUse::all();
    }
    
    if (!isset($allPrefixes)) {
        $allPrefixes = \App\Models\Prefix::select('id', 'prefix', 'land_use_id')->get();
    }
    
    if (!isset($unallocatedEntries)) {
        $unallocatedEntries = \App\Models\AllocationListEntry::where('is_allocated', 0)
            ->orderBy('first_name')
            ->get();
    }
@endphp

<!-- Generate Modal with Alpine.js -->
<div id="{{ $modalId }}" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-5 mx-auto p-6 border w-[800px] max-w-4xl shadow-xl rounded-lg bg-white" 
         x-data="fileNumberGenerator()" x-init="console.log('X-INIT DIRECT LOG'); init()">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h3 id="modalTitle" class="text-xl font-semibold text-gray-900">Commission New File Number</h3>
                    <p class="text-sm text-gray-500 mt-1">Fill in the details to generate a new MLPP file number</p>
                </div>
                <button onclick="closeCommissionFileNoModal('{{ $modalId }}')" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="commissionFileNoForm" onsubmit="submitCommissionFileNoForm(event, '{{ $modalId }}', '{{ $formAction }}', '{{ $successCallback }}')" class="space-y-6">
                @csrf

                <!-- Hidden field for the generated file number that backend expects -->
                <input type="hidden" name="generated_file_number" x-model="preview" id="generatedFileNumber">
                <input type="hidden" name="tracking_id" id="trackingIdInput" value="">

                <!-- Application Type Selection -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-4">
                        <i data-lucide="info" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                        Application Type
                    </label>
                    <div class="flex items-center gap-8">
                        <label class="flex items-center group cursor-pointer commission-conversion-option">
                            <div class="relative flex items-center justify-center">
                                <input type="radio" name="application_type" value="new" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" 
                                       x-model="applicationType" @change="updateApplicationType()" checked required>
                            </div>
                            <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Direct Allocation</span>
                        </label>
                        <label class="flex items-center group cursor-pointer">
                            <div class="relative flex items-center justify-center">
                                <input type="radio" name="application_type" value="conversion" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" 
                                       x-model="applicationType" @change="updateApplicationType()" required>
                            </div>
                            <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Conversion</span>
                        </label>
                    </div>

                    <!-- Allocation Source (Direct Allocation only) -->
                    <div x-show="applicationType === 'new'" class="mt-4 pt-4 border-t border-gray-200" x-transition>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-3">
                            <i data-lucide="filter" class="w-3 h-3 inline mr-1 text-blue-500"></i>
                            Allocation Source
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center justify-between p-3 rounded-md border border-blue-200 bg-blue-50/60 cursor-pointer hover:bg-blue-50 transition-colors">
                                <span class="flex items-center">
                                    <input type="radio" name="allocated_by_filter" value="" x-model="allocatedByFilter" @change="handleAllocationFilterChange()"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked>
                                    <span class="ml-2 text-sm font-semibold text-gray-700">Default</span>
                                </span>
                                <i data-lucide="badge-check" class="w-4 h-4 text-blue-600"></i>
                            </label>
                            <label class="flex items-center justify-between p-3 rounded-md border border-gray-200 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                <span class="flex items-center">
                                    <input type="radio" name="allocated_by_filter" value="Allocation List" x-model="allocatedByFilter" @change="handleAllocationFilterChange()"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <span class="ml-2 text-sm font-semibold text-gray-700">Allocation List</span>
                                </span>
                                <i data-lucide="list-checks" class="w-4 h-4 text-gray-500"></i>
                            </label>
                        </div>

                        <div x-show="allocatedByFilter === ''" class="mt-3 p-3 rounded-md border border-blue-100 bg-blue-50/40" x-cloak>
                            <input type="hidden" name="is_resettlement" :value="defaultAllocationType === 'resettlement' ? 1 : 0">
                            <div class="flex items-center gap-6">
                                <label class="flex items-center group cursor-pointer">
                                    <input type="radio" name="default_allocation_type" value="direct" x-model="defaultAllocationType"
                                           @change="openCommissionOpCaptureModal('direct')"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <i data-lucide="map-pinned" class="w-4 h-4 ml-2 text-blue-600"></i>
                                    <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Direct Allocation</span>
                                </label>
                                <label class="flex items-center group cursor-pointer">
                                    <input type="radio" name="default_allocation_type" value="resettlement" x-model="defaultAllocationType"
                                           @change="openCommissionOpCaptureModal('resettlement')"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                    <i data-lucide="home" class="w-4 h-4 ml-2 text-emerald-600"></i>
                                    <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Resettlement</span>
                                </label>
                            </div>
                        </div>

                        <input x-show="allocatedByFilter === 'Allocation List'" type="hidden" name="is_resettlement" value="0">
                    </div>
                </div>

                <!-- Batch Mode Toggle Section -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border-2 border-blue-200">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="layers" class="w-5 h-5 text-blue-600"></i>
                            <label class="text-sm font-semibold text-gray-700">Batch Mode</label>
                            <span class="text-xs text-gray-500">(Generate multiple files at once)</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="batchMode" @change="toggleBatchMode()" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <!-- Batch Quantity Input (shown when batch mode is active) -->
                    <div x-show="batchMode" x-transition class="mt-3">
                        <label for="batchQuantity" class="block text-xs font-medium text-gray-600 mb-1">
                            Number of Files to Generate
                        </label>
                        <div class="flex items-center space-x-3">
                            <input type="number" id="batchQuantity" x-model="batchQuantity" @input="updateBatchPreview()"
                                   min="2" max="100" 
                                   class="w-32 px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <span class="text-sm text-gray-600">files (Max: 100)</span>
                            <div class="flex-1"></div>
                            <div class="text-xs text-blue-600 font-medium" x-show="batchQuantity > 1">
                                Serial Range: <span x-text="serialRangePreview"></span>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            <i data-lucide="info" class="w-3 h-3 inline"></i>
                           All files will share the same File Name (File Title) and Prefix. You can only add unique location details for each file (if need be)
                        </p>
                    </div>
                </div>

                {{-- Include the rest of the form fields from the original modal --}}
                @include('components.partials.commission-fileno-form-fields', [
                    'lgas' => $lgas,
                    'landUses' => $landUses,
                    'allPrefixes' => $allPrefixes,
                    'unallocatedEntries' => $unallocatedEntries
                ])

                <!-- Form Actions -->
                <div class="flex justify-between border-t border-gray-200 mt-4 pt-4">
                    <button type="button" onclick="showCommissionOverrideModal('{{ $modalId }}')" 
                            id="overrideButton"
                            disabled
                            class="px-4 py-2 bg-orange-600 text-white rounded-md transition-colors flex items-center space-x-2 opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                        <span>Override</span>
                    </button>
                    
                    <button type="button" onclick="closeCommissionFileNoModal('{{ $modalId }}')" 
                            class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>

                    
                    <button type="submit" 
                            id="generateButton"
                            disabled
                            class="px-6 py-2 bg-blue-600 text-white rounded-md transition-colors flex items-center space-x-2 opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Generate</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Include supporting modals --}}
@include('components.partials.commission-fileno-batch-summary-modal')
@include('components.partials.commission-fileno-override-modal')

@push('scripts')
    <script src="{{ asset('js/commission-fileno-modal.js') }}"></script>
    <script>
        // Initialize modal if custom configuration provided
        @if(isset($config))
            window.commissionFileNoModalConfig_{{ $modalId }} = @json($config);
        @endif
        
        // Custom success callback
        @if($successCallback)
            window.{{ $successCallback }} = function(response) {
                console.log('Commission File Number Success:', response);
                // Custom callback logic can be defined by the parent page
            };
        @endif
    </script>
@endpush
