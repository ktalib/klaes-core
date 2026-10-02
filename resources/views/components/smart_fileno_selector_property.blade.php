<!-- Smart File Number Selector Component for Property Cards using Alpine.js -->
<div class="smart-fileno-selector-property" x-data="propertyFilenoSelector()">
    <!-- Hidden input for the main fileno field that gets submitted -->
    <input type="hidden" id="fileno" name="fileno" x-model="selectedFileno">
    
    <div class="flex items-center justify-between mb-3">
        <label for="fileno-select-property" class="block text-sm font-medium text-gray-700">Select File Number</label>
        <button type="button" @click="toggleMode()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            <span x-text="isManualMode ? 'Use dropdown' : 'Enter Fileno manually'"></span>
        </button>
    </div>
    
    <!-- Dropdown Selection Mode -->
    <div x-show="!isManualMode" x-transition>
        @php
            $applications = DB::connection('sqlsrv')->table('mother_applications')
                ->select('id', 'fileno', 'applicant_type', 'first_name', 'surname', 'corporate_name', 'multiple_owners_names', 'land_use')
                ->orderBy('id', 'desc')
                ->limit(50)
                ->get();
        @endphp
        <select id="fileno-select-property" class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <option value="">-- Select File Number --</option>
            @foreach($applications as $app)
                @php
                    $displayText = $app->fileno;
                    $applicantName = '';
                    if ($app->applicant_type === 'individual' && $app->first_name && $app->surname) {
                        $applicantName = $app->first_name . ' ' . $app->surname;
                        $displayText .= ' - ' . $applicantName;
                    } elseif ($app->applicant_type === 'corporate' && $app->corporate_name) {
                        $applicantName = $app->corporate_name;
                        $displayText .= ' - ' . $applicantName;
                    } elseif ($app->multiple_owners_names) {
                        $applicantName = $app->multiple_owners_names;
                        $displayText .= ' - ' . $applicantName;
                    }
                @endphp
                <option value="{{ $app->id }}" data-fileno="{{ $app->fileno }}" data-applicant-name="{{ $applicantName }}" data-land-use="{{ $app->land_use }}">{{ $displayText }}</option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Can't find your file number? <button type="button" class="text-blue-600 hover:underline" @click="toggleMode()">Enter it manually</button></p>
        
        <!-- Selected File Number Display (in dropdown mode) -->
        <div x-show="selectedFileno && !isManualMode" x-transition class="mt-3">
            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-2 border-green-200 rounded-lg p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-medium text-green-800 mb-1">Selected File Number</h3>
                            <div class="flex items-center space-x-2">
                                <span class="text-lg font-bold text-green-900 font-mono bg-white px-3 py-1 rounded border border-green-200" x-text="selectedFileno"></span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✓ Ready to use
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <button type="button" @click="clearSelection()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Clear
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Manual Entry Mode -->
    <div x-show="isManualMode" x-transition>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 w-full">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h4 class="text-lg font-semibold text-blue-800">Enter File Number Information</h4>
                </div>
                <button type="button" @click="toggleMode()" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-white border border-blue-300 rounded-md hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to dropdown
                </button>
            </div>
            
            <!-- Manual File Number Entry with Alpine.js -->
            <div class="bg-white rounded-lg p-4 border border-blue-100">
                <div class="bg-green-50 border border-green-100 rounded-md p-4 mb-6">
                    <div class="flex items-center mb-2">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="font-medium">File Number Information</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">Select file number type and enter the details</p>
                    
                    <!-- Tab Navigation -->
                    <div class="bg-white p-2 rounded-md mb-4 flex space-x-2">
                        <button type="button" @click="activeTab = 'mls'" :class="activeTab === 'mls' ? 'px-4 py-2 rounded-md bg-gray-200 text-gray-800' : 'px-4 py-2 rounded-md hover:bg-gray-100 text-gray-600'">MLPP</button>
                        <button type="button" @click="activeTab = 'kangis'" :class="activeTab === 'kangis' ? 'px-4 py-2 rounded-md bg-gray-200 text-gray-800' : 'px-4 py-2 rounded-md hover:bg-gray-100 text-gray-600'">KANGIS</button>
                        <button type="button" @click="activeTab = 'newkangis'" :class="activeTab === 'newkangis' ? 'px-4 py-2 rounded-md bg-gray-200 text-gray-800' : 'px-4 py-2 rounded-md hover:bg-gray-100 text-gray-600'">New KANGIS</button>
                    </div>
                    
                    <!-- MLS Tab Content -->
                    <div x-show="activeTab === 'mls'" x-transition>
                        <p class="text-sm text-gray-600 mb-2">MLPP File Number</p>
                        <div class="grid grid-cols-3 gap-4 mb-3">
                            <div>
                                <label class="block text-sm mb-1">File Prefix</label>
                                <select x-model="mlsPrefix" @change="updateMlsPreview()" class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="">Select prefix</option>
                                    <option value="COM">COM</option>
                                    <option value="RES">RES</option>
                                    <option value="CON-COM">CON-COM</option>
                                    <option value="CON-RES">CON-RES</option>
                                    <option value="CON-AG">CON-AG</option>
                                    <option value="CON-IND">CON-IND</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Serial Number</label>
                                <input type="text" x-model="mlsNumber" @input="updateMlsPreview()" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 2022-572">
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Full FileNo</label>
                                <input type="text" x-model="mlsPreview" class="w-full p-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- KANGIS Tab Content -->
                    <div x-show="activeTab === 'kangis'" x-transition>
                        <p class="text-sm text-gray-600 mb-2">KANGIS File Number</p>
                        <div class="grid grid-cols-3 gap-4 mb-3">
                            <div>
                                <label class="block text-sm mb-1">File Prefix</label>
                                <select x-model="kangisPrefix" @change="updateKangisPreview()" class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="">Select Prefix</option>
                                    <option value="KNML">KNML</option>
                                    <option value="MNKL">MNKL</option>
                                    <option value="MLKN">MLKN</option>
                                    <option value="KNGP">KNGP</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Serial Number</label>
                                <input type="text" x-model="kangisNumber" @input="updateKangisPreview()" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 0001 or 2500">
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Full FileNo</label>
                                <input type="text" x-model="kangisPreview" class="w-full p-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <!-- New KANGIS Tab Content -->
                    <div x-show="activeTab === 'newkangis'" x-transition>
                        <p class="text-sm text-gray-600 mb-2">New KANGIS File Number</p>
                        <div class="grid grid-cols-3 gap-4 mb-3">
                            <div>
                                <label class="block text-sm mb-1">File Prefix</label>
                                <select x-model="newKangisPrefix" @change="updateNewKangisPreview()" class="w-full p-2 border border-gray-300 rounded-md">
                                    <option value="">Select Prefix</option>
                                    <option value="KN">KN</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Serial Number</label>
                                <input type="text" x-model="newKangisNumber" @input="updateNewKangisPreview()" class="w-full p-2 border border-gray-300 rounded-md" placeholder="e.g. 1586">
                            </div>
                            <div>
                                <label class="block text-sm mb-1">Full FileNo</label>
                                <input type="text" x-model="newKangisPreview" class="w-full p-2 border border-gray-300 rounded-md bg-gray-50" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end">
                <button type="button" @click="confirmManualEntry()" class="inline-flex items-center px-6 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Use This File Number
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Alpine.js component for property fileno selector
    function propertyFilenoSelector() {
        return {
            // State management
            isManualMode: false,
            selectedFileno: '',
            activeTab: 'mls',
            
            // MLS fields
            mlsPrefix: '',
            mlsNumber: '',
            mlsPreview: '',
            
            // KANGIS fields
            kangisPrefix: '',
            kangisNumber: '',
            kangisPreview: '',
            
            // New KANGIS fields
            newKangisPrefix: '',
            newKangisNumber: '',
            newKangisPreview: '',
            
            // Methods
            init() {
                console.log('Property fileno selector Alpine.js component initialized');
                this.initializeSelect();
            },
            
            handleSelection(event) {
                const selectedOption = event.target.options[event.target.selectedIndex];
                this.selectedFileno = selectedOption.getAttribute('data-fileno') || '';
                const applicantName = selectedOption.getAttribute('data-applicant-name') || '';
                const landUse = selectedOption.getAttribute('data-land-use') || '';
                
                // Dispatch event with selected data
                this.$dispatch('fileno-selected', {
                    fileno: this.selectedFileno,
                    applicantName: applicantName,
                    landUse: landUse
                });
            },
            
            initializeSelect() {
                const filenoSelect = document.getElementById('fileno-select-property');
                if (filenoSelect) {
                    filenoSelect.addEventListener('change', this.handleSelection.bind(this));
                }
            }
            
            toggleMode() {
                console.log('Toggling mode. Current isManualMode:', this.isManualMode);
                this.isManualMode = !this.isManualMode;
                console.log('New isManualMode:', this.isManualMode);
            },
            
            clearSelection() {
                console.log('Clearing selection');
                this.selectedFileno = '';
                this.resetManualFields();
            },
            
            resetManualFields() {
                this.mlsPrefix = '';
                this.mlsNumber = '';
                this.mlsPreview = '';
                this.kangisPrefix = '';
                this.kangisNumber = '';
                this.kangisPreview = '';
                this.newKangisPrefix = '';
                this.newKangisNumber = '';
                this.newKangisPreview = '';
                this.activeTab = 'mls';
            },
            
            updateMlsPreview() {
                if (this.mlsPrefix && this.mlsNumber) {
                    this.mlsPreview = this.mlsPrefix + '-' + this.mlsNumber;
                } else if (this.mlsPrefix) {
                    this.mlsPreview = this.mlsPrefix;
                } else if (this.mlsNumber) {
                    this.mlsPreview = this.mlsNumber;
                } else {
                    this.mlsPreview = '';
                }
            },
            
            updateKangisPreview() {
                if (this.kangisPrefix && this.kangisNumber) {
                    // Pad to 5 digits
                    const paddedNumber = this.kangisNumber.padStart(5, '0');
                    this.kangisNumber = paddedNumber;
                    this.kangisPreview = this.kangisPrefix + ' ' + paddedNumber;
                } else if (this.kangisPrefix) {
                    this.kangisPreview = this.kangisPrefix;
                } else if (this.kangisNumber) {
                    this.kangisPreview = this.kangisNumber;
                } else {
                    this.kangisPreview = '';
                }
            },
            
            updateNewKangisPreview() {
                if (this.newKangisPrefix && this.newKangisNumber) {
                    this.newKangisPreview = this.newKangisPrefix + this.newKangisNumber;
                } else if (this.newKangisPrefix) {
                    this.newKangisPreview = this.newKangisPrefix;
                } else if (this.newKangisNumber) {
                    this.newKangisPreview = this.newKangisNumber;
                } else {
                    this.newKangisPreview = '';
                }
            },
            
            confirmManualEntry() {
                console.log('Confirming manual entry. Active tab:', this.activeTab);
                
                let fileNumber = '';
                
                if (this.activeTab === 'mls') {
                    fileNumber = this.mlsPreview;
                } else if (this.activeTab === 'kangis') {
                    fileNumber = this.kangisPreview;
                } else if (this.activeTab === 'newkangis') {
                    fileNumber = this.newKangisPreview;
                }
                
                console.log('File number to set:', fileNumber);
                
                if (fileNumber.trim()) {
                    this.selectedFileno = fileNumber;
                    this.isManualMode = false;
                    
                    // Show success message
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'File Number Set',
                            text: `File number "${fileNumber}" has been set for the property record.`,
                            icon: 'success',
                            confirmButtonText: 'Continue'
                        });
                    } else {
                        alert(`File number "${fileNumber}" has been set for the property record.`);
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Invalid File Number',
                            text: 'Please enter a valid file number.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert('Please enter a valid file number.');
                    }
                }
            },
            
            initializeSelect() {
                const filenoSelect = document.getElementById('fileno-select-property');
                if (filenoSelect) {
                    filenoSelect.addEventListener('change', (e) => {
                        const selectedOption = e.target.options[e.target.selectedIndex];
                        this.selectedFileno = selectedOption.getAttribute('data-fileno') || '';
                    });
                }
            }
        }
    }
}
    </script>