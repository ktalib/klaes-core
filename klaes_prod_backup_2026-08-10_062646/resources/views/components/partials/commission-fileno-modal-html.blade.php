{{-- Commission New File Number Modal HTML Partial --}}
{{-- Extracted from generate_fileno/mlsfno.blade.php for reuse across modules --}}
{{-- Expected variables: $lgas, $landUses, $allPrefixes, $unallocatedEntries --}}

<!-- Generate Modal with Alpine.js -->
<div id="generateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-5 mx-auto p-4 md:p-6 border w-full max-w-4xl md:w-[800px] shadow-xl rounded-lg bg-white" 
         x-data="fileNumberGenerator()" x-init="console.log('X-INIT DIRECT LOG'); init()">
        <div class="mt-3">
            <!-- Modal Header -->
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h3 id="modalTitle" class="text-xl font-semibold text-gray-900">Commission New File Number</h3>
                    <p class="text-sm text-gray-500 mt-1">Fill in the details to generate a new MLS file number</p>
                </div>
                <button onclick="closeGenerateModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- Modal Form -->
            <form id="generateForm" onsubmit="submitForm(event)" class="space-y-6">
                @csrf 

                

                 
                <!-- Hidden field for the generated file number that backend expects -->
                <input type="hidden" name="generated_file_number" x-model="preview" id="generatedFileNumber">
                <input type="hidden" name="tracking_id" id="trackingIdInput" value="">
                <input type="hidden" name="require_op_source" :value="requireOpSource ? 1 : 0">
                <input type="hidden" name="source_instrument_capture_id" x-model="sourceInstrumentCaptureId">
                <input type="hidden" name="source_prop_id" x-model="sourcePropId">
                <input type="hidden" name="source_pra_id" x-model="sourcePraId">
                <input type="hidden" name="source_op_serial_number" x-model="sourceOpSerialNumber">
                <input type="hidden" name="source_registration_number" x-model="sourceRegistrationNumber">
                <input type="hidden" name="source_serial_no" x-model="sourceSerialNo">
                <input type="hidden" name="source_page_no" x-model="sourcePageNo">
                <input type="hidden" name="source_volume_no" x-model="sourceVolumeNo">
                <input type="hidden" name="source_original_owner" x-model="sourceOriginalOwner">
                <input type="hidden" name="source" x-model="applicationType">
                <input type="hidden" name="sub_source" x-model="subSource">
                <input type="hidden" name="change_of_purpose_app_id" x-model="changeOfPurposeAppId">
                <input type="hidden" name="original_file_no" x-model="originalFileNo">
                <input type="hidden" name="new_purpose" x-model="newPurpose">

                <!-- Application Type Selection -->
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-100">
                    <label class="block text-sm font-semibold text-gray-700 mb-4">
                        <i data-lucide="info" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                        Application Type
                    </label>
                    <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-8">
                        <div class="flex items-center gap-8 shrink-0">
                            <label class="flex items-center group cursor-pointer commission-da-option">
                                <div class="relative flex items-center justify-center">
                                    <input type="radio" name="application_type" value="new" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" 
                                           x-model="applicationType" @change="updateApplicationType()" checked required>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Direct Allocation</span>
                            </label>
                            <label class="flex items-center group cursor-pointer commission-conversion-option">
                                <div class="relative flex items-center justify-center">
                                    <input type="radio" name="application_type" value="conversion" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" 
                                           x-model="applicationType" @change="updateApplicationType()" required>
                                </div>
                                <span class="ml-2 text-sm font-medium text-gray-700 group-hover:text-blue-600 transition-colors">Conversion</span>
                            </label>
                           
                        </div>

                        <div class="flex-1 min-w-0">
                            {{-- <div class="flex items-center gap-2 mb-2">
                                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">File Type</span>
                            </div> --}}
                            <div class="grid grid-cols-2 xl:grid-cols-3 gap-2">
                                <label class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="quick_file_option" value="temporary"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                           x-model="fileOption" @change="updateFileOption()">
                                    <span class="ml-2 text-[11px] font-medium text-gray-700">Temporary</span>
                                </label>

                                <label class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="quick_file_option" value="extension"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                           x-model="fileOption" @change="updateFileOption()">
                                    <span class="ml-2 text-[11px] font-medium text-gray-700">Extension</span>
                                </label>

                                <label class="inline-flex items-center px-2.5 py-1.5 rounded-md border border-gray-300 bg-white cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio" name="quick_file_option" value="miscellaneous"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                           x-model="fileOption" @change="updateFileOption()">
                                    <span class="ml-2 text-[11px] font-medium text-gray-700">Miscellaneous</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Allocation Source (Direct Allocation only) -->
                    <div id="allocation-source-section" x-show="applicationType === 'new'" class="mt-4 pt-4 border-t border-gray-200" x-transition>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-3">
                            <i data-lucide="filter" class="w-3 h-3 inline mr-1 text-blue-500"></i>
                            Select Registry
                        </label>
                        
                        <!-- Custom File Name Checkbox -->
                        <div class="mb-3 p-3 rounded-md border border-gray-200 bg-gray-50">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" id="hasCustomFileName" x-model="hasCustomFileName" 
                                       class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                <span class="text-sm font-medium text-gray-700">Default</span>
                            </label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <label class="flex items-center justify-between p-3 rounded-md border border-blue-200 bg-blue-50/60 cursor-pointer hover:bg-blue-50 transition-colors">
                                <span class="flex items-center">
                                    <input type="radio" name="allocated_by_filter" value="" x-model="allocatedByFilter" @change="handleAllocationFilterChange()"
                                           class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500">
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

                    <!-- Change of Purpose Selection (Change of Purpose only) -->
                    <div id="change-of-purpose-section" x-show="applicationType === 'change_of_purpose'" 
                            class="mt-4 pt-4 border-t border-gray-200 space-y-4" x-transition x-cloak>
 

                        <!-- Search/Select Approved CoP Applications -->
                        <div class="space-y-3">
                            <input type="text" 
                                    id="copSearch" 
                                    placeholder="Search by file number, applicant, or land use..."
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    @input="searchChangeOfPurposeApps($event.target.value)">
                            
                            <div id="copDropdown" class="hidden max-h-64 overflow-y-auto border border-gray-300 rounded-lg bg-white shadow-lg relative z-50">
                                <ul id="copList" class="divide-y divide-gray-100">
                                    <!-- Results populated by JS -->
                                </ul>
                            </div>
                        </div>

                        <!-- Selected Application Details -->
                        <div id="selectedCopDetails" x-show="changeOfPurposeAppId" 
                                class="p-4 rounded-lg border border-blue-200 bg-blue-50/40 space-y-2 relative mt-4">
                            <div class="text-sm font-semibold text-gray-700">Selected Application:</div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-xs text-gray-500">Current File Number</div>
                                    <div class="font-mono font-bold text-gray-900" x-text="originalFileNo || '—'"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Applicant</div>
                                    <div class="font-semibold text-gray-900" x-text="copApplicantName || '—'"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Current Land Use</div>
                                    <div class="text-sm text-gray-700" x-text="copCurrentLandUse || '—'"></div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">New Purpose</div>
                                    <div class="font-semibold text-emerald-700" x-text="copNewPurpose || '—'"></div>
                                </div>
                            </div>
                            
                            <button type="button" @click="clearChangeOfPurposeSelection()"
                                    class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-xs text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                <i data-lucide="x" class="w-3 h-3"></i>Clear Selection
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Batch Mode Toggle — kept OUTSIDE the !isOpFormHidden wrapper so it stays
                     visible even when an Occupancy Permit (OP) source is selected. Turning it on
                     and picking an OP allocation type opens the Batch Capture OP stepper. --}}
                 <div id="batch-mode-section" x-show="!hideBatchMode" x-transition
                     class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border-2 border-blue-200"
                     :class="{ 'opacity-70': isBatchModeLocked }">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <i data-lucide="layers" class="w-5 h-5 text-blue-600"></i>
                            <label class="text-sm font-semibold text-gray-700">Batch Mode</label>
                            <span class="text-xs text-gray-500">(Generate multiple files at once)</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer"
                               :class="{ 'cursor-not-allowed': isBatchModeLocked }">
                            <input type="checkbox" x-model="batchMode" @change="toggleBatchMode()"
                                   :disabled="isBatchModeLocked"
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <p x-show="isBatchModeLocked" class="text-xs text-amber-700 font-medium mt-1" x-cloak>
                        Batch Mode is disabled when Occupancy Permit (OP) is selected.
                    </p>

                    <!-- Uncommissioned OP batch awaiting commissioning: offer a way back to the
                         OP Batch card to correct a mistake before generating. -->
                    <div x-show="pendingOpBatchId" x-transition x-cloak
                         class="mt-3 flex flex-wrap items-center justify-between gap-3 px-3 py-2 bg-violet-50 border border-violet-200 rounded-lg">
                        <div class="text-xs text-violet-800">
                            Commissioning OP batch
                            <span class="font-mono font-semibold" x-text="pendingOpBatchId"></span>
                            <span class="text-violet-500">— not yet commissioned, so it can still be edited.</span>
                        </div>
                        <button type="button" onclick="copBackToBatchCard()"
                                class="px-3 py-1.5 bg-white border border-violet-300 rounded-lg text-xs font-semibold text-violet-700 hover:bg-violet-100 transition">
                            &lt; Back to OP Batch
                        </button>
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
                        <p class="text-xs text-gray-500 mt-2 font-bold">
                                        <i data-lucide="info" class="w-3 h-3 inline"></i>
                                       All files will share the same File Name (File Title) and Prefix. You can only add unique location details for each file (if need be)
                                    </p>
                    </div>
                </div>

                <!-- Sections hidden when OP source is selected but not yet captured -->
                <div x-show="!isOpFormHidden" x-transition.opacity.duration.200ms class="space-y-6">

                <!-- Main Form Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <!-- Middle Prefix Section -->
                        <div id="middlePrefixSection" class="hidden">
                            <label for="middlePrefix" class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="tag" class="w-4 h-4 inline mr-1"></i>
                                Middle Prefix
                            </label>
                            <input type="text" id="middlePrefix" name="middle_prefix" x-model="middlePrefix"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="e.g., KN" value="KN">
                        </div>

                        <!-- File Options Section - 2x2 Grid -->
                        <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <i data-lucide="settings" class="w-4 h-4 inline mr-1"></i>
                                    <label class="block text-sm font-semibold text-gray-700">File Options</label>
                                    <!-- Applicant counter (batch mode) — mirrors the MLS modal + Location Details nav -->
                                    <span x-show="batchMode" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                        Applicant <span x-text="currentEntryIndex + 1"></span> of <span x-text="batchQuantity"></span>
                                    </span>
                                </div>
                                <div x-show="batchMode" class="flex items-center space-x-2">
                                    <button type="button" @click="previousEntry()"
                                            :disabled="currentEntryIndex === 0"
                                            :class="currentEntryIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                            class="p-1 rounded-md text-blue-600 transition-colors">
                                        <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                    </button>
                                    <button type="button" @click="nextEntry()"
                                            :disabled="currentEntryIndex >= batchQuantity - 1"
                                            :class="currentEntryIndex >= batchQuantity - 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                            class="p-1 rounded-md text-blue-600 transition-colors">
                                        <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Party 1 for this applicant: the paired OP's Party 2 (allottee), matched by
                                 batch sequence. Only present after a Batch Capture OP hand-off. --}}
                            <div x-show="batchMode && opBatchAllottees.length > 0" x-cloak
                                 class="mb-3 pb-3 border-b border-gray-200">
                                <div class="text-xs font-medium text-gray-500">
                                    Party 1 — allottee from OP <span x-text="currentEntryIndex + 1"></span>
                                </div>
                                <div class="text-sm font-semibold text-violet-700"
                                     x-text="opBatchAllottees[currentEntryIndex] || '—'"></div>
                            </div>

                            <!-- 2x2 Grid Layout -->
                            <div class="grid grid-cols-2 gap-4">
                                <!-- Row 1, Col 1: File Type -->
                                <div>
                                    <label for="fileOption" class="block text-xs font-medium text-gray-600 mb-1">
                                        File Type
                                    </label>
                                    <select id="fileOption" name="file_option" x-model="fileOption" @change="updateFileOption()"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                                        <option value="normal" selected>Normal File</option>
                                        <option value="temporary" hidden>Temporary File</option>
                                        <option value="extension" hidden>Extension</option>
                                        <option value="miscellaneous" hidden>Miscellaneous</option>
                                        <option value="old_mls">Old MLS</option>
                                        <option value="sltr">SLTR</option>
                                        <option value="sit" x-show="applicationType === 'new'">SIT</option>
                                    </select>
                                </div>

                                <!-- Row 1, Col 2: File NameSelection -->
                                <div>
                                    <label for="fileName" class="block text-xs font-medium text-gray-600 mb-1">
                                        File Name
                                    </label>
                                    
                                    <!-- Standard Text Input (Always shown when hasCustomFileName is checked) -->
                                    <div x-show="hasCustomFileName || applicationType !== 'new' || allocatedByFilter === ''" x-cloak>
                                        <input type="text" id="fileName" name="file_name" x-model="fileName"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                               :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                               :readonly="isInherited"
                                               placeholder="Enter file name">
                                    </div>

                                    <!-- Select2 Dropdown (Shown only when NOT using custom file name AND Direct Allocation + Allocation List) -->
                                    <div x-show="!hasCustomFileName && applicationType === 'new' && allocatedByFilter === 'Allocation List'" wire:ignore x-cloak>
                                        <select id="allocationSelect" 
                                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                                style="width: 100%">
                                            <option value="">Search and select an allottee</option>
                                            @foreach($unallocatedEntries as $entry)
                                                <option value="{{ $entry->id }}" 
                                                        data-full-name="{{ $entry->first_name }} {{ $entry->middle_name ? $entry->middle_name . ' ' : '' }}{{ $entry->last_name }}"
                                                        data-plot="{{ $entry->plot_number }}"
                                                        data-district="{{ $entry->district }}"
                                                        data-lga="{{ $entry->lga }}"
                                                        data-state="{{ $entry->state }}"
                                                        data-allocated-by="{{ $entry->allocated_by }}"
                                                        data-allottee-address="{{ $entry->allottee_address }}">
                                                    {{ $entry->first_name }} {{ $entry->middle_name ? $entry->middle_name . ' ' : '' }}{{ $entry->last_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <!-- Hidden input for the allocation ID -->
                                        <input type="hidden" name="allocation_id" id="allocation_id" x-model="allocationId">
                                        <!-- Hidden input to submit the file_name when using select2 -->
                                        <input type="hidden" name="file_name" :value="fileName" :disabled="applicationType !== 'new'">
                                    </div>
                                </div>

                                <!-- Row 2, Col 1: Prefix -->
                                <div>
                                    <label for="prefix" class="block text-xs font-medium text-gray-600 mb-1">
                                        Select Prefix
                                    </label>
                                    <input type="hidden" name="prefix" :value="prefix" :disabled="!isInherited">
                                    <select id="prefix" :name="isInherited ? '' : 'prefix'" x-model="prefix" @change="handlePrefixChange($event)"
                                            class="w-full px-3 py-2 border border-blue-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                                            :class="{ 'bg-gray-100 opacity-75 cursor-not-allowed': isInherited }"
                                            :disabled="isInherited">
                                        <option value="">Select Prefix</option>
                                        <template x-for="px in filteredPrefixes" :key="px.id">
                                            <option :value="px.prefix" :data-limit="px.land_use_id" x-text="px.prefix"></option>
                                        </template>
                                    </select>
                                </div>

                                <!-- Row 2, Col 2: Land Use (Read-Only/Disabled) -->
                                <div>
                                    <label for="landUse" class="block text-xs font-medium text-gray-600 mb-1">
                                        Select Land Use
                                    </label>
                                    <input type="hidden" name="land_use" x-model="landUse">
                                    <select id="landUse" x-model="landUse" disabled
                                            class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed">
                                        <option value="">(Auto-selected)</option>
                                        @foreach($landUses as $lu)
                                            @php
                                                $name = strtoupper($lu->landuse);
                                                $code = '';
                                                if (str_contains($name, 'RESIDENTIAL')) $code = 'RES';
                                                elseif (str_contains($name, 'COMMERCIAL')) $code = 'COM';
                                                elseif (str_contains($name, 'INDUSTRIAL')) $code = 'IND';
                                                elseif (str_contains($name, 'AGRICULTURAL')) $code = 'AG';
                                                else $code = substr($name, 0, 3);
                                            @endphp
                                            <option value="{{ $code }}" data-id="{{ $lu->id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <!-- Row 3, Col 1: Purpose -->
                                <div>
                                    <label for="purpose" class="block text-xs font-medium text-gray-600 mb-1">
                                        Purpose
                                    </label>
                                    <select id="purpose" name="purpose_id" x-model="purpose" @change="updatePreview()"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                                            :disabled="!landUse" required>
                                        <option value="">Select Purpose</option>
                                        <template x-for="p in purposes" :key="p.id">
                                            <option :value="p.id" x-text="p.name"></option>
                                        </template>
                                    </select>
                                </div>
                             
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                       Customer Type
                                    </label>
                                    <div class="flex items-center h-[42px]">
                                        <select id="customerType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white" name="customer_type" x-model="customerType" required>
                                            <option value="">Select Customer Type</option>
                                            <option value="Individual">Individual</option>
                                            <option value="Corporate">Corporate</option>
                                            <option value="Multiple">Multiple</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Gender -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">
                                       Gender
                                    </label>
                                    <div class="flex items-center h-[42px]">
                                        <select id="gender" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white" name="gender" x-model="gender" required>
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Row 4, Col 1: Phone Number -->
                                <div>
                                    <label for="generatePhoneNo" class="block text-xs font-medium text-gray-600 mb-1">
                                        Phone No of Applicant
                                    </label>
                                    <input type="text" id="generatePhoneNo" name="phone_no" x-model="phone_no"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                           :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                           :readonly="isInherited"
                                           placeholder="Enter Phone No">
                                </div>

                                <!-- Row 4, Col 2: Address -->
                                <div>
                                    <label for="generateAddress" class="block text-xs font-medium text-gray-600 mb-1">
                                        Address of Applicant
                                    </label>
                                    <input type="text" id="generateAddress" name="address" x-model="address"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                                           :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                           :readonly="isInherited"
                                           placeholder="Enter Address">
                                </div>
                            </div>

                            <!-- Local Rep Details (Shown only for Conversion) -->
                            <div x-show="applicationType === 'conversion'" class="mt-4 p-4 border border-blue-200 bg-blue-50 rounded-lg">
                                <h4 class="text-sm font-semibold text-blue-800 mb-3 border-b border-blue-200 pb-2">Details of Local Rep</h4>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label for="generateRepPhoneNo" class="block text-xs font-medium text-gray-700 mb-1">
                                            Phone No
                                        </label>
                                        <input type="text" id="generateRepPhoneNo" name="rep_phone_no" x-model="rep_phone_no"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                               :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                               :readonly="isInherited"
                                               placeholder="Rep Phone No">
                                    </div>

                                    <div>
                                        <label for="generateRepAddress" class="block text-xs font-medium text-gray-700 mb-1">
                                            Address
                                        </label>
                                        <input type="text" id="generateRepAddress" name="rep_address" x-model="rep_address"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm uppercase"
                                               :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                               :readonly="isInherited"
                                               placeholder="Rep Address">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <!-- Extension File Selection -->
                        <div x-show="fileOption === 'extension'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="link" class="w-4 h-4 inline mr-1"></i>
                                Select Existing MLS File Number to Extend
                            </label>

                            <div class="flex space-x-2">
                                <div class="relative flex-grow">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="file-text" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                    <input type="text" 
                                           id="displayExtensionFileNo"
                                           x-model="existingFileNo" 
                                           readonly
                                           placeholder="No file selected"
                                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-gray-50 text-gray-500 placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                </div>
                                <button type="button" 
                                        onclick="openExtensionFileSelector()"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i data-lucide="search" class="w-4 h-4 mr-2"></i>
                                    Select File
                                </button>
                            </div>
                            
                            <input type="hidden" id="extensionFileNo" name="existing_file_no" x-model="existingFileNo">

                            <p class="mt-2 text-xs text-gray-500">
                                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i>
                                Search and select the file you wish to extend.
                            </p>
                        </div>

                        <!-- Temporary File Selection -->
                        <div x-show="fileOption === 'temporary'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="clock" class="w-4 h-4 inline mr-1"></i>
                                Select Existing File for Temporary Version
                            </label>

                            <div class="flex items-center space-x-3">
                                <div class="flex-grow">
                                    <input type="text" id="displayTemporaryFileNo" x-model="existingFileNo" readonly
                                           placeholder="No file selected"
                                           class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md focus:outline-none cursor-not-allowed">
                                    <input type="hidden" id="temporaryFileNo" name="existing_file_no" x-model="existingFileNo">
                                </div>
                                <button type="button" onclick="openTemporaryFileSelector()"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm font-medium whitespace-nowrap">
                                    <i data-lucide="search" class="w-4 h-4"></i>
                                    Select File
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Select the main file number to fetch tracking ID and other details</p>
                        </div>

                        <!-- Full File Number Preview -->
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="eye" class="w-4 h-4 inline mr-1"></i>
                                Generated File Number Preview
                            </label>
                            <div id="mlsfPreview" class="w-full px-4 py-3 bg-white border border-blue-300 rounded-md text-lg font-mono text-center font-bold shadow-sm"
                                 :class="previewClass" x-text="preview">
                            </div>

                            <!-- Year and Serial Number Grid -->
                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <!-- Year -->
                                <div x-show="showYearSection" x-cloak>
                                    <label for="year" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="calendar" class="w-4 h-4 inline mr-1"></i>
                                        Year
                                    </label>
                                    <input type="number" id="year" name="year" x-model="year" @input="updatePreview()"
                                           :class="yearFieldClass"
                                           min="2020" max="2050" :readonly="!isYearEditable">
                                    <p class="text-xs text-gray-500 mt-1" x-text="yearDescription"></p>
                                </div>

                                <!-- Serial Number -->
                                <div>
                                    <label for="serialNo" class="block text-sm font-medium text-gray-700 mb-2">
                                        <i data-lucide="hash" class="w-4 h-4 inline mr-1"></i>
                                        Serial No.
                                    </label>
                                    <input :type="serialFieldType" id="serialNo" name="serial_no" 
                                           x-model="serialNo" 
                                           x-on:input="serialFieldType === 'text' ? updatePreviewOnly() : updatePreview()"
                                           :class="serialFieldClass"
                                           :placeholder="serialPlaceholder" 
                                           :readonly="isSerialReadonly" 
                                           :disabled="isSerialDisabled"
                                           x-bind:min="serialFieldType === 'number' ? '1' : false"
                                           x-bind:max="serialFieldType === 'number' ? '9999' : false"
                                           :inputmode="serialFieldType === 'text' ? 'text' : 'numeric'"
                                           autocomplete="off">
                                    <p class="text-xs mt-1" :class="serialDescriptionClass" x-text="serialDescription"></p>
                                </div>
                            </div>

                            <div class="bg-gray-100 px-4 py-2 rounded-md flex justify-between items-center max-w-xs mt-4">
                                <div class="text-gray-700 font-mono text-sm font-bold whitespace-nowrap">
                                    <i data-lucide="file-search" class="inline h-4 w-4 mr-1"></i> 
                                    Tracking ID: <span id="trackingIdDisplay" class="text-red-600 font-bold">--</span>
                                </div> 
                            </div>

                            <!-- Land Use Category Preview -->
                            <label class="block text-xs font-medium text-gray-600 mb-1">
                                Land Use (Purpose)
                            </label>
                            <div class="flex items-center h-[42px]">
                                <span id="landUsePreview" 
                                      class="px-3 py-2 rounded-lg text-sm font-bold inline-flex items-center shadow-sm border transition-all duration-300 ease-in-out transform uppercase"
                                      :class="{
                                          'bg-blue-50 text-blue-700 border-blue-200 scale-105 shadow-blue-100': landUse && landUse.toUpperCase().includes('RES'),
                                          'bg-green-50 text-green-700 border-green-200 scale-105 shadow-green-100': landUse && landUse.toUpperCase().includes('COM'),
                                          'bg-amber-50 text-amber-700 border-amber-200 scale-105 shadow-amber-100': landUse && landUse.toUpperCase().includes('IND'),
                                          'bg-purple-50 text-purple-700 border-purple-200 scale-105 shadow-purple-100': landUse && landUse.toUpperCase().includes('AG'),
                                          'bg-gray-50 text-gray-400 border-gray-200': !landUse
                                      }"
                                      x-text="landUseFullText || 'Select prefix'">
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (Miscellaneous) -->
                    <div class="space-y-4">
                        <!-- Middle Prefix (for miscellaneous files) -->
                        <div x-show="fileOption === 'miscellaneous'">
                            <label for="middlePrefix" class="block text-sm font-medium text-gray-700 mb-2">
                                <i data-lucide="tag" class="w-4 h-4 inline mr-1"></i>
                                Middle Prefix
                            </label>
                            <input type="text" id="middlePrefix" name="middle_prefix" x-model="middlePrefix" @input="updatePreview()"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., KN" value="KN">
                        </div>
                    </div>
                </div><!-- end main form grid -->

                <!-- Location Details + Commissioning side-by-side -->
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-2">
                                <i data-lucide="map" class="w-4 h-4 inline mr-1"></i>
                                <label class="block text-sm font-semibold text-gray-700">Location Details</label>
                                <span x-show="batchMode" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-medium">
                                    Entry <span x-text="currentEntryIndex + 1"></span> of <span x-text="batchQuantity"></span>
                                </span>
                            </div>
                            <div x-show="batchMode" class="flex items-center space-x-2">
                                <button type="button" @click="previousEntry()" 
                                        :disabled="currentEntryIndex === 0"
                                        :class="currentEntryIndex === 0 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                        class="p-1 rounded-md text-blue-600 transition-colors">
                                    <i data-lucide="chevron-left" class="w-5 h-5"></i>
                                </button>
                                <button type="button" @click="nextEntry()" 
                                        :disabled="currentEntryIndex >= batchQuantity - 1"
                                        :class="currentEntryIndex >= batchQuantity - 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-100'"
                                        class="p-1 rounded-md text-blue-600 transition-colors">
                                    <i data-lucide="chevron-right" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Batch Location Sync Toggle -->
                        <div x-show="batchMode" class="flex items-center space-x-2 mb-3 pb-3 border-b border-gray-200">
                            <input type="checkbox" id="applyToAllBatch" x-model="applyLocationToAll" class="rounded text-blue-600 focus:ring-blue-500">
                            <label for="applyToAllBatch" class="text-xs font-medium text-gray-600 cursor-pointer">
                                Apply Location to All Files in Batch
                            </label>
                        </div>

                        <div x-show="batchMode && applyLocationToAll" class="mb-3">
                            <button type="button" @click="applyLocationToBatch()" 
                                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors flex items-center justify-center space-x-2">
                                <i data-lucide="copy" class="w-4 h-4"></i>
                                <span>Apply Current Location to All <span x-text="batchQuantity"></span> Files</span>
                            </button>
                        </div>

                        <!-- 2x2 Grid Layout -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="plotNo" class="block text-xs font-medium text-gray-600 mb-1">Plot Number</label>
                                <input type="text" id="plotNo" name="plot_no" 
                                       :value="batchMode ? locationEntries[currentEntryIndex]?.plotNo : plotNo"
                                       @input="batchMode ? updateLocationEntry('plotNo', $event.target.value) : plotNo = $event.target.value"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                       :readonly="isInherited"
                                       placeholder="Enter plot number">
                            </div>
                            <!-- TP Number: custom search -->
                            <div class="relative">
                                <label class="block text-xs font-medium text-gray-600 mb-1">TP Number</label>
                                <div class="relative">
                                    <input type="text"
                                           id="tp_search_input"
                                           :value="tpSearchQuery"
                                           @input="tpSearchQuery = $event.target.value; debounceTpSearch()"
                                           @keydown.escape="tpSearchOpen = false"
                                           @keydown.arrow-down.prevent="tpFocusNext()"
                                           @keydown.arrow-up.prevent="tpFocusPrev()"
                                           @keydown.enter.prevent="tpSelectFocused()"
                                           @blur="setTimeout(() => tpSearchOpen = false, 150)"
                                           autocomplete="off"
                                           placeholder="Type to search TP no..."
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent pr-7"
                                           :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                           :disabled="isInherited">
                                    <button type="button" x-show="tpNo"
                                            @click="clearTpNo()"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">&#215;</button>
                                </div>
                                <input type="hidden" name="tp_no" id="generator_tp_no_val" :value="tpNo">
                                <!-- Dropdown results -->
                                <div x-show="tpSearchOpen"
                                     x-cloak
                                     class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-y-auto">
                                    <div x-show="tpSearchLoading" class="px-3 py-2 text-xs text-gray-500">Searching...</div>
                                    <div x-show="!tpSearchLoading && tpSearchResults.length === 0" class="px-3 py-2 text-xs text-gray-400">No results</div>
                                    <template x-for="(result, index) in tpSearchResults" :key="result.id">
                                        <div @mousedown.prevent="selectTpResult(result)"
                                             :class="tpFocusIndex === index ? 'bg-blue-50 text-blue-700' : 'hover:bg-gray-50'"
                                             class="px-3 py-2 text-sm cursor-pointer"
                                             x-text="result.text">
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label for="location" class="block text-xs font-medium text-gray-600 mb-1">Location</label>
                                <input type="text" id="location" name="location" 
                                       :value="batchMode ? locationEntries[currentEntryIndex]?.location : location"
                                       @input="batchMode ? updateLocationEntry('location', $event.target.value) : location = $event.target.value"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       :class="{ 'bg-gray-100 opacity-75': isInherited }"
                                       :readonly="isInherited"
                                       placeholder="Enter location details">
                            </div>
                            <div>
                                <label for="generator_lga" class="block text-xs font-medium text-gray-600 mb-1">LGA</label>
                                <input type="hidden" name="lga" :value="lga" :disabled="!isInherited">
                                <select id="generator_lga" :name="isInherited ? '' : 'lga'" 
                                        :value="batchMode ? locationEntries[currentEntryIndex]?.lga : lga"
                                        @change="batchMode ? updateLocationEntry('lga', $event.target.value) : lga = $event.target.value"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                        :class="{ 'bg-gray-100 opacity-75 cursor-not-allowed': isInherited }"
                                        :disabled="isInherited">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Entry Progress Indicator (batch mode) -->
                        <div x-show="batchMode" class="mt-3 pt-3 border-t border-gray-300 hidden">
                            <div class="flex items-center justify-between text-xs text-gray-600">
                                <span>Progress: <span x-text="filledEntriesCount"></span> of <span x-text="batchQuantity"></span> entries filled</span>
                                <div class="flex items-center space-x-1">
                                    <template x-for="i in parseInt(batchQuantity)" :key="i">
                                        <div class="w-2 h-2 rounded-full" 
                                             :class="isEntryFilled(i-1) ? 'bg-green-500' : 'bg-gray-300'"></div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commissioning Metadata Grid -->
                    <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="commissionTime" class="block text-xs font-semibold text-gray-600 mb-1">
                                        <i data-lucide="clock" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                        Commissioning Time
                                    </label>
                                    <input type="time" id="commissionTime" name="commission_time"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent bg-gray-100 sync-time text-sm"
                                           placeholder="Auto-filled" value="{{ date('H:i') }}">
                                </div>
                                <div>
                                    <label for="commissionDate" class="block text-xs font-semibold text-gray-600 mb-1">
                                        <i data-lucide="calendar-check" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                        Commissioning Date
                                    </label>
                                    <input type="date" id="commissionDate" name="commission_date"                 
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent bg-gray-100 sync-time text-sm"
                                           placeholder="Auto-filled" value="{{ date('Y-m-d') }}">
                                </div>
                            </div>
                            <div>
                                <label for="commissionedBy" class="block text-xs font-semibold text-gray-600 mb-1">
                                    <i data-lucide="user-check" class="w-3.5 h-3.5 inline mr-1 text-blue-500"></i>
                                    Commissioned By
                                </label>
                                <input type="text" id="commissionedBy" name="commissioned_by"  
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-transparent bg-gray-100 text-sm"
                                       placeholder="Auto-filled" value="{{ Auth::user()->name }}">
                            </div>
                        </div>
                    </div>
                </div><!-- end location+commissioning grid -->

                <!-- Form Actions -->
                <div class="flex justify-between border-t border-gray-200 mt-4">
                    <button type="button" onclick="showOverrideModal()" 
                            id="overrideButton"
                            disabled
                            class="px-4 py-2 bg-orange-600 text-white rounded-md transition-colors flex items-center space-x-2 opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                        <span>Override</span>
                    </button>
                    
                    <button type="button" onclick="closeGenerateModal()" 
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

                </div><!-- end x-show !isOpFormHidden wrapper -->

                <!-- OP capture pending message (shown when OP is selected but not yet captured) -->
                <div x-show="isOpFormHidden" x-cloak x-transition.opacity.duration.200ms class="flex flex-col items-center justify-center p-8 text-center bg-gray-50 rounded-xl border-2 border-dashed border-gray-200 min-h-[250px] overflow-hidden">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                        <i data-lucide="file-search" class="w-8 h-8 text-blue-600"></i>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-2">Capture Occupancy Permit First</h4>
                    <p class="text-sm text-gray-500 max-w-md mx-auto">Please select <strong>Direct Allocation</strong> or <strong>Resettlement</strong> above, then capture/select an Occupancy Permit (OP) record to continue with the commissioning process.</p>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Batch Summary Modal -->
<div id="batchSummaryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-[60]">
    <div class="relative top-10 mx-auto p-6 border w-[700px] max-w-3xl shadow-2xl rounded-lg bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-6 pb-4 border-b border-gray-200">
                <div>
                    <h3 class="text-xl font-semibold text-gray-900 flex items-center space-x-2">
                        <i data-lucide="layers" class="w-6 h-6 text-blue-600"></i>
                        <span>Batch Generation Summary</span>
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">Review the details before generating files</p>
                </div>
                <button onclick="closeBatchSummaryModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="space-y-4">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Batch Size</p>
                            <p class="text-lg font-semibold text-gray-900" id="summaryBatchSize">-</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Serial Number Range</p>
                            <p class="text-lg font-semibold text-blue-600" id="summarySerialRange">-</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">Land Use</p>
                            <p class="text-lg font-semibold text-gray-900" id="summaryLandUse">-</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-600 mb-1">File Name</p>
                            <p class="text-lg font-semibold text-gray-900" id="summaryFileName">-</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-xs text-gray-600 mb-2">File Numbers to be Generated</p>
                    <p class="text-sm font-mono text-green-600" id="summaryFileNumbers">-</p>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg">
                    <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 rounded-t-lg">
                        <h4 class="text-sm font-semibold text-gray-700">Location Details</h4>
                    </div>
                    <div class="max-h-64 overflow-y-auto p-4" id="summaryLocationList"></div>
                </div>

                @include('generate_fileno.partials.summary_destination')

                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                This action will generate <strong id="summaryTotalFiles">0</strong> file numbers. 
                                Please review all details carefully before confirming.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 mt-6 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeBatchSummaryModal()" 
                        class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="confirmBatchGeneration()" 
                        id="confirmBatchButton"
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors flex items-center space-x-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Confirm & Generate</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Override Modal -->
<div id="overrideModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Override File Number</h3>
                <button onclick="closeOverrideModal()" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form id="overrideForm" onsubmit="submitOverrideForm(event)">
                @csrf

                <div class="mb-4">
                    <label for="overrideYear" class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                    <input type="number" id="overrideYear" name="override_year" 
                           value="{{ date('Y') }}"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label for="overrideSerialNo" class="block text-sm font-medium text-gray-700 mb-2">Serial Number</label>
                    <input type="number" id="overrideSerialNo" name="override_serial_no" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           min="1" max="9999">
                </div>

                <div class="mb-4" style="display:none;">
                    <label class="flex items-center">
                        <input type="checkbox" id="overrideExtension" name="override_extension" class="mr-2">
                        <span>File Extension</span>
                    </label>
                </div>
          
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeOverrideModal()" 
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                        Apply Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
