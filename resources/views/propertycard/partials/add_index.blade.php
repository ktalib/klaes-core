<!-- Select2 CSS and JS for file selector -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Alpine.js CDN -->
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

<div id="index-card-dialog" class="dialog-overlay hidden" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.5); display: flex; align-items: center; justify-content: center; z-index: 50;">
    <div class="dialog-content property-form-content" style="background-color: white; border-radius: 0.5rem; padding: 1.5rem; max-width: 1000px; width: 90%; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;">
        <div class="flex justify-between items-center mb-4">
            <h2 id="index-card-title" class="text-xl font-bold">Index Card</h2>
            <button id="close-index-card-form" class="text-gray-500 hover:text-gray-700" style="cursor: pointer; z-index: 100; position: relative; display: flex; align-items: center; justify-content: center;">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

<div x-data="{
    // Form fields
  
    fileNumber: '',
    houseNo: '',
    plotNo: '',
    streetName: '',
    district: '',
    lga: '',
    transactionType: '',
    transactionDate: '',
    serialNo: '',
    pageNo: '',
    volumeNo: '',
    regDate: new Date().toISOString().split('T')[0],
    regTime: '09:00',
    landUse: '',
    period: '',
    periodUnit: 'Years',
    firstParty: '',
    secondParty: '',
    propertyDescription: '',
    
    // New fields (optional)
    tpNo: '',
    lpknNo: '',
    approvedPlanNo: '',
    plotSize: '',
    dateRecommended: '',
    dateApproved: '',
    leaseBegins: '',
    leaseExpires: '',
    metricSheet: '',
    
    // Track current values from components
    currentStreetName: '',
    currentDistrict: '',
    
    // Additional instrument handling
    instrumentEntries: [],
    instrumentTypeOptions: ['Assignment', 'Surrender', 'Subdivision', 'Merger', 'Withdrawn', 'Revoke'],
    instrumentPartyMap: {
        'Assignment': { from: 'Assignor', to: 'Assignee' },
        'Surrender': { from: 'Surrenderor', to: 'Surrenderee (Kano State Govt.)' },
        'Subdivision': { from: 'Parent Title Holder', to: 'Subdivided Owner(s)' },
        'Merger': { from: 'Merging Owner', to: 'Resultant Owner' },
        'Withdrawn': { from: 'Kano State Govt.', to: 'Original Owner' },
        'Revoke': { from: 'Kano State Govt.', to: 'Original Owner' },
    },
    instrumentColorMap: {
        'Assignment': { border: 'border-blue-200', bg: 'bg-blue-50', tag: 'bg-blue-100 text-blue-900' },
        'Surrender': { border: 'border-amber-200', bg: 'bg-amber-50', tag: 'bg-amber-100 text-amber-900' },
        'Subdivision': { border: 'border-emerald-200', bg: 'bg-emerald-50', tag: 'bg-emerald-100 text-emerald-900' },
        'Merger': { border: 'border-purple-200', bg: 'bg-purple-50', tag: 'bg-purple-100 text-purple-900' },
        'Withdrawn': { border: 'border-rose-200', bg: 'bg-rose-50', tag: 'bg-rose-100 text-rose-900' },
        'Revoke': { border: 'border-red-200', bg: 'bg-red-50', tag: 'bg-red-100 text-red-900' },
        'default': { border: 'border-gray-200', bg: 'bg-gray-50', tag: 'bg-gray-100 text-gray-800' }
    },
    
    // Party labels for different transaction types
    partyLabels: {
        'Power of Attorney': { first: 'Grantor', second: 'Grantee' },
        'Deed of Assignment': { first: 'Assignor', second: 'Assignee' },
        'ST Assignment': { first: 'Assignor', second: 'Assignee' },
        'Deed of Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
        'Tripartite Mortgage': { first: 'Mortgagor', second: 'Mortgagee' },
        'Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'ST Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'SLTR Certificate of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Customary Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Right of Occupancy': { first: 'Grantor', second: 'Grantee' },
        'Deed of Transfer': { first: 'Transferor', second: 'Transferee' },
        'Deed of Gift': { first: 'Donor', second: 'Donee' },
        'Deed of Lease': { first: 'Lessor', second: 'Lessee' },
        'Deed of Sub Lease': { first: 'Lessor', second: 'Lessee' },
        'Deed of Sub Under Lease': { first: 'Lessor', second: 'Lessee' },
        'Indenture of Lease': { first: 'Lessor', second: 'Lessee' },
        'Quarry Lease': { first: 'Lessor', second: 'Lessee' },
        'Private Lease': { first: 'Lessor', second: 'Lessee' },
        'Building Lease': { first: 'Lessor', second: 'Lessee' },
        'Tenancy Agreement': { first: 'Landlord', second: 'Tenant' },
        'Deed of Surrender and Release': { first: 'Surrenderor', second: 'Surrenderee' },
        'Letter of Administration': { first: 'Administrator', second: 'Beneficiary' },
        'Certificate of Purchase': { first: 'Vendor', second: 'Purchaser' }
    },
    
    // Computed properties
    get showTransactionDetails() {
        return this.transactionType !== '';
    },
    
    get currentPartyLabels() {
        return this.partyLabels[this.transactionType] || { first: 'Grantor', second: 'Grantee' };
    },
    
    get isGovernmentTransaction() {
        const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy', 'Right of Occupancy', 'Occupation Permit'];
        return govTypes.includes(this.transactionType);
    },
    
    get regNumberPreview() {
        const parts = [this.serialNo, this.pageNo, this.volumeNo].filter(Boolean);
        return parts.length > 0 ? parts.join('/') : '';
    },
    
    // Method to update property description
    updatePropertyDescription() {
        const parts = [];
        
        // Add house number if available
        if (this.houseNo && this.houseNo.trim()) {
            parts.push(this.houseNo.trim());
                    }
        
        // Add street name if available
        if (this.currentStreetName && this.currentStreetName.trim()) {
            parts.push(this.currentStreetName.trim());
                    }
        
        // Add district if available
        if (this.currentDistrict && this.currentDistrict.trim()) {
            parts.push(this.currentDistrict.trim());
                    }
        
        // Add LGA if available
        if (this.lga && this.lga.trim()) {
            parts.push(this.lga.trim());
                    }
        
        // Add state (prefer field value, default is Kano State)
        const stateElement = document.querySelector('#state');
        const stateValue = stateElement ? stateElement.value : '';
        if (stateValue && stateValue.trim()) {
            parts.push(stateValue.trim());
        } else {
            parts.push('Kano State');
        }
        
        // Update the property description
        this.propertyDescription = parts.length > 0 ? parts.join(', ') : '';
            },
    
    // Initialize
    init() {
                
        // Watch for transaction type changes
        this.$watch('transactionType', (value, oldValue) => {
                        
            // Auto-fill for government transactions
            const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy', 'Right of Occupancy', 'Occupation Permit'];
            if (govTypes.includes(value)) {
                this.firstParty = 'KANO STATE GOVERNMENT';
                            } else {
                this.firstParty = '';
                            }
        });
        
        // Auto-sync page number with serial number
        this.$watch('serialNo', (value) => {
            this.pageNo = value;
        });
        
        // Watch for property details changes and update description
        this.$watch('houseNo', () => {
            this.updatePropertyDescription();
        });
        
        this.$watch('lga', () => {
            this.updatePropertyDescription();
        });
        
        // Set up comprehensive monitoring for component fields
        const setupFieldMonitoring = () => {
                        
            // Monitor street name dropdown
            const streetNameSelect = document.querySelector('#streetName');
            if (streetNameSelect) {
                                streetNameSelect.addEventListener('change', () => {
                                        setTimeout(() => this.updatePropertyDescription(), 50);
                });
            }
            
            // Monitor custom street name input
            const streetNameCustom = document.querySelector('#otherStreetName');
            if (streetNameCustom) {
                                streetNameCustom.addEventListener('input', () => {
                                        setTimeout(() => this.updatePropertyDescription(), 50);
                });
            }
            
            // Monitor district dropdown
            const districtSelect = document.querySelector('#district');
            if (districtSelect) {
                                districtSelect.addEventListener('change', () => {
                                        setTimeout(() => this.updatePropertyDescription(), 50);
                });
            }
            
            // Monitor custom district input
            const districtCustom = document.querySelector('#otherDistrict');
            if (districtCustom) {
                                districtCustom.addEventListener('input', () => {
                                        setTimeout(() => this.updatePropertyDescription(), 50);
                });
            }
            
            // Monitor state input
            const stateInput = document.querySelector('#state');
            if (stateInput) {
                                stateInput.addEventListener('input', () => {
                                        setTimeout(() => this.updatePropertyDescription(), 50);
                });
            }

            // Set up MutationObserver to watch for dynamic elements
            // Minimal listeners only
        };
        
        // Initial setup and periodic re-setup
        setTimeout(() => {
            setupFieldMonitoring();
            this.updatePropertyDescription();
        }, 200);
    },
    
    // Methods
    getFirstPartyFieldName() {
        const typeMap = {
            'Deed of Assignment': 'Assignor',
            'ST Assignment': 'Assignor',
            'Deed of Mortgage': 'Mortgagor',
            'Tripartite Mortgage': 'Mortgagor',
            'Deed of Surrender and Release': 'Surrenderor',
            'Deed of Sub Lease': 'Lessor',
            'Deed of Sub Under Lease': 'Lessor',
            'Indenture of Lease': 'Lessor',
            'Quarry Lease': 'Lessor',
            'Private Lease': 'Lessor',
            'Building Lease': 'Lessor',
            'Tenancy Agreement': 'Landlord',
            'Deed of Transfer': 'Transferor',
            'Deed of Gift': 'Donor',
            'Letter of Administration': 'Administrator',
            'Certificate of Purchase': 'Vendor'
        };
        return typeMap[this.transactionType] || 'Grantor';
    },
    
    getSecondPartyFieldName() {
        const typeMap = {
            'Deed of Assignment': 'Assignee',
            'ST Assignment': 'Assignee',
            'Deed of Mortgage': 'Mortgagee',
            'Tripartite Mortgage': 'Mortgagee',
            'Deed of Surrender and Release': 'Surrenderee',
            'Deed of Sub Lease': 'Lessee',
            'Deed of Sub Under Lease': 'Lessee',
            'Indenture of Lease': 'Lessee',
            'Quarry Lease': 'Lessee',
            'Private Lease': 'Lessee',
            'Building Lease': 'Lessee',
            'Tenancy Agreement': 'Tenant',
            'Deed of Transfer': 'Transferee',
            'Deed of Gift': 'Donee',
            'Letter of Administration': 'Beneficiary',
            'Certificate of Purchase': 'Purchaser'
        };
        return typeMap[this.transactionType] || 'Grantee';
    },
    
    addInstrumentEntry() {
        this.instrumentEntries.push({
            id: Date.now() + Math.random(),
            type: '',
            date: '',
            party_from: '',
            party_to: ''
        });
        this.$nextTick(() => {
            const container = document.getElementById('index-additional-instruments');
            if (container) {
                container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    },
    
    removeInstrumentEntry(index) {
        if (index >= 0 && index < this.instrumentEntries.length) {
            this.instrumentEntries.splice(index, 1);
        }
    },
    
    getInstrumentLabels(type) {
        return this.instrumentPartyMap[type] || { from: 'Party A', to: 'Party B' };
    },

    getInstrumentColors(type) {
        return this.instrumentColorMap[type] || this.instrumentColorMap['default'];
    },

    applyInstrumentDefaults(instrument) {
        if (!instrument || !instrument.type) return;
        const type = instrument.type;
        if ((type === 'Withdrawn' || type === 'Revoke') && (!instrument.party_from || !instrument.party_from.trim())) {
            instrument.party_from = 'KANO STATE GOVERNMENT';
        }
    },

    submitForm() {
        // Debug: Log all form data before validation
        console.log('=== FORM SUBMISSION DEBUG ===');
        
        // Get file number fields from smart selector
        const smartMlsFNo = document.getElementById('mlsFNo')?.value || '';
        const smartKangisFileNo = document.getElementById('kangisFileNo')?.value || '';
        const smartNewKangisFileNo = document.getElementById('NewKANGISFileno')?.value || '';
        const smartFileno = document.getElementById('fileno')?.value || '';
        
        // Get file number fields from manual entry
        const manualMlsFNo = document.getElementById('manual-mlsFNo')?.value || '';
        const manualKangisFileNo = document.getElementById('manual-kangisFileNo')?.value || '';
        const manualNewKangisFileNo = document.getElementById('manual-NewKANGISFileno')?.value || '';
        
        console.log('Smart Selector Values:');
        console.log('smartMlsFNo:', smartMlsFNo);
        console.log('smartKangisFileNo:', smartKangisFileNo);
        console.log('smartNewKangisFileNo:', smartNewKangisFileNo);
        console.log('smartFileno:', smartFileno);
        
        console.log('Manual Entry Values:');
        console.log('manualMlsFNo:', manualMlsFNo);
        console.log('manualKangisFileNo:', manualKangisFileNo);
        console.log('manualNewKangisFileNo:', manualNewKangisFileNo);
        
        // Check manual entry Alpine.js data (if available)
        let alpineMls = '', alpineKangis = '', alpineNewKangis = '';
        try {
            const manualContainer = document.querySelector('#manual-fileno-container [x-data]');
            if (manualContainer && manualContainer._x_dataStack) {
                const alpineData = manualContainer._x_dataStack[0];
                if (alpineData) {
                    alpineMls = alpineData.mlsPreview ? alpineData.mlsPreview() : '';
                    alpineKangis = alpineData.kangisPreview ? alpineData.kangisPreview() : '';
                    alpineNewKangis = alpineData.newkangisPreview ? alpineData.newkangisPreview() : '';
                    
                    console.log('Alpine.js Preview Functions:');
                    console.log('alpineMls:', alpineMls);
                    console.log('alpineKangis:', alpineKangis);
                    console.log('alpineNewKangis:', alpineNewKangis);
                }
            }
        } catch (e) {
            console.log('Could not access Alpine.js data:', e.message);
        }
        
        // Combine all possible values (smart selector takes precedence, then manual hidden fields, then Alpine.js)
        const finalMls = smartMlsFNo || manualMlsFNo || alpineMls;
        const finalKangis = smartKangisFileNo || manualKangisFileNo || alpineKangis;
        const finalNewKangis = smartNewKangisFileNo || manualNewKangisFileNo || alpineNewKangis;
        const finalFileno = smartFileno;
        
        console.log('Final Combined Values:');
        console.log('finalMls:', finalMls);
        console.log('finalKangis:', finalKangis);
        console.log('finalNewKangis:', finalNewKangis);
        console.log('finalFileno:', finalFileno);
        
        // Validate that at least one file number is provided
        if (!finalMls && !finalKangis && !finalNewKangis && !finalFileno) {
            console.log('VALIDATION FAILED: No file numbers found');
            
            // Show error message
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'File Number Required',
                    text: 'Please select or enter at least one file number (MLPP, KANGIS, or New KANGIS) before submitting.',
                    confirmButtonText: 'OK'
                });
            } else {
                alert('Please select or enter at least one file number (MLPP, KANGIS, or New KANGIS) before submitting.');
            }
            return false;
        }
        
        console.log('VALIDATION PASSED: At least one file number found');
        console.log('=== END FORM SUBMISSION DEBUG ===');
        
        // Call the SweetAlert submission handler directly
        if (typeof submitPropertyForm === 'function') {
            submitPropertyForm();
        } else {
            // Fallback to normal form submission
            const form = document.getElementById('property-record-form');
            if (form) {
                form.submit();
            }
        }
    }
}" x-init="init()">
    <form id="property-record-form" action="{{ route('property-records.store') }}" method="POST" @submit.prevent="submitForm">
        @csrf
        <input type="hidden" name="property_id" id="property_id" value="">
        <input type="hidden" name="form_action" id="form_action" value="add">
        
        <div class="space-y-4 py-2 flex-1 max-h-[75vh] overflow-y-auto pr-1">
            <!-- Top section with two columns -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Left column - Title Type Section -->
                <div class="form-section">
                   
                  <!-- File Number -->
                  <div class="space-y-1" x-data="{ showManualEntry: false }" x-effect="(() => { const manual=$el.querySelector('#manual-fileno-container'); const smart=$el.querySelector('#smart-fileno-container'); if(manual){ manual.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(el=> el.disabled = !showManualEntry); } if(smart){ smart.querySelectorAll('input:not([type=hidden]), select, textarea').forEach(el=> el.disabled = showManualEntry); } })()">
                    <div class="flex items-center justify-between mb-3">
                        <label for="fileno-select" class="block text-sm font-medium text-gray-700">
                            Select File Number <span class="text-red-500">*</span>
                        </label>
                        <button type="button" @click="showManualEntry = !showManualEntry" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-600 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span x-text="showManualEntry ? 'Use Smart Selector' : 'Enter Fileno manually'"></span>
                        </button>
                    </div>
                    
                    <!-- Smart File Number Selector (Default) -->
                    <div x-show="!showManualEntry" x-transition id="smart-fileno-container">
                        @include('propertycard.partials.smart_fileno_selector')
                    </div>
                    
                    <!-- Manual File Number Entry -->
                    <div x-show="showManualEntry" x-transition id="manual-fileno-container">
                        @include('propertycard.partials.manual_fileno')
                    </div>
                    </div>
                </div>

                      
                
                
                
                <div class="mt-4">
                    <label for="index_related_file_number" class="block text-sm font-medium text-gray-700 mb-2">
                        Related File Number (Optional)
                    </label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input 
                                type="text" 
                                id="index_related_file_number" 
                                name="related_file_number"
                                class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Enter related file number if applicable"
                            >
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                </svg>
                            </div>
                        </div>
                        <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" onclick="openRelatedFileNumberModal('index_related_file_number')">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M12.9 14.32a5 5 0 01-7.07 0l-2.83-2.83a5 5 0 117.07-7.07l.7.7a1 1 0 01-1.42 1.42l-.7-.7a3 3 0 00-4.24 4.24l2.83 2.83a3 3 0 004.24 0 1 1 0 011.42 1.42z"/>
                                <path d="M17.66 6.34l-1.41 1.41-1.41-1.41 1.41-1.41a1 1 0 011.41 1.41z"/>
                            </svg>
                            Select
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Link this index card to another file number if needed</p>
                </div>

                <!-- Right column - Property Description -->
                <div class="form-section">
                    <h4 class="form-section-title">Property Description</h4>
                    <div class="space-y-3">
                        <!-- House No and Plot No -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="houseNo" class="text-xs text-gray-600">House No</label>
                                <input id="houseNo" name="house_no" x-model="houseNo" type="text" class="form-input text-sm property-input">
                            </div>
                            <div>
                                <label for="plotNo" class="text-xs text-gray-600">Plot No.</label>
                                <input id="plotNo" name="plot_no" x-model="plotNo" type="text" class="form-input text-sm property-input" placeholder="Enter plot number">
                            </div>
                        </div>
                        
                        <!-- Street Name and District -->
                        <div class="grid grid-cols-2 gap-3" 
                             @street-changed="currentStreetName = $event.detail.isOther ? $event.detail.value : ($event.detail.value !== 'other' ? $event.detail.value : ''); updatePropertyDescription();"
                             @district-changed="currentDistrict = $event.detail.isOther ? $event.detail.value : ($event.detail.value !== 'other' ? $event.detail.value : ''); updatePropertyDescription();">
                            @include('components.StreetName2')
                            @include('components.District')
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="lga" class="text-xs text-gray-600">LGA</label>
                               @include('propertycard.partials.lga')
                            </div>
                            
                            <div>
                                <label for="state" class="text-xs text-gray-600">State</label>
                                <input id="state" name="state" type="text" class="form-input text-sm property-input" placeholder="Enter state" value="Kano">
                            </div>
                        </div>

                        <!-- Additional Property Fields (Optional) -->
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="tp_no_prop" class="text-xs text-gray-600">TP No</label>
                                <input id="tp_no_prop" name="tp_no" x-model="tpNo" type="text" class="form-input text-sm property-input" placeholder="Enter TP number (optional)">
                            </div>
                            <div>
                                <label for="lpkn_no_prop" class="text-xs text-gray-600">LPKN No</label>
                                <input id="lpkn_no_prop" name="lpkn_no" x-model="lpknNo" type="text" class="form-input text-sm property-input" placeholder="Enter LPKN number (optional)">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="approved_plan_no_prop" class="text-xs text-gray-600">Approved Plan No</label>
                                <input id="approved_plan_no_prop" name="approved_plan_no" x-model="approvedPlanNo" type="text" class="form-input text-sm property-input" placeholder="Enter approved plan number (optional)">
                            </div>
                            <div>
                                <label for="plot_size_prop" class="text-xs text-gray-600">Plot Size</label>
                                <input id="plot_size_prop" name="plot_size" x-model="plotSize" type="text" class="form-input text-sm property-input" placeholder="Enter plot size (optional)">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="date_recommended_prop" class="text-xs text-gray-600">Date Recommended</label>
                                <input id="date_recommended_prop" name="date_recommended" x-model="dateRecommended" type="date" class="form-input text-sm property-input">
                            </div>
                            <div>
                                <label for="date_approved_prop" class="text-xs text-gray-600">Date Approved</label>
                                <input id="date_approved_prop" name="date_approved" x-model="dateApproved" type="date" class="form-input text-sm property-input">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="lease_begins_prop" class="text-xs text-gray-600">Lease Begins</label>
                                <input id="lease_begins_prop" name="lease_begins" x-model="leaseBegins" type="date" class="form-input text-sm property-input">
                            </div>
                            <div>
                                <label for="lease_expires_prop" class="text-xs text-gray-600">Lease Expires</label>
                                <input id="lease_expires_prop" name="lease_expires" x-model="leaseExpires" type="date" class="form-input text-sm property-input">
                            </div>
                        </div>

                        <div>
                            <label for="metric_sheet_prop" class="text-xs text-gray-600">Metric Sheet</label>
                            <input id="metric_sheet_prop" name="metric_sheet" x-model="metricSheet" type="text" class="form-input text-sm property-input" placeholder="Enter metric sheet details (optional)">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Instrument Type Section -->
            <div class="form-section">
                <h4 class="form-section-title">Instrument Type</h4>
                <div class="space-y-3">
                    <!-- Transaction Type and Date -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="transactionType-record" class="text-sm">Transaction Type</label>
                            <select id="transactionType-record" name="transactionType" x-model="transactionType" class="form-select text-sm transaction-type-select">
                                <option value="">Select type</option>
                                <option value="Deed of Transfer">Deed of Transfer</option>
                                <option value="Certificate of Occupancy">Certificate of Occupancy</option>
                                <option value="ST Certificate of Occupancy">ST Certificate of Occupancy</option>
                                <option value="SLTR Certificate of Occupancy">SLTR Certificate of Occupancy</option>
                                <option value="Irrevocable Power of Attorney">Irrevocable Power of Attorney</option>
                                <option value="Deed of Assignment">Deed of Assignment</option>
                                <option value="ST Assignment">ST Assignment</option>
                                <option value="Deed of Mortgage">Deed of Mortgage</option>
                                <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                                <option value="Deed of Sub Lease">Deed of Sub Lease</option>
                                <option value="Deed of Sub Under Lease">Deed of Sub Under Lease</option>
                                <option value="Power of Attorney">Power of Attorney</option>
                                <option value="Deed of Surrender and Release">Deed of Surrender and Release</option>
                                <option value="Indenture of Lease">Indenture of Lease</option>
                                <option value="Deed of Variation">Deed of Variation</option>
                                <option value="Customary Right of Occupancy">Customary Right of Occupancy</option>
                                <option value="Vesting Assent">Vesting Assent</option>
                                <option value="Court Judgement">Court Judgement</option>
                                <option value="Exchange of Letters">Exchange of Letters</option>
                                <option value="Tenancy Agreement">Tenancy Agreement</option>
                                <option value="Revocation of Power of Attorney">Revocation of Power of Attorney</option>
                                <option value="Deed of Convenyence">Deed of Convenyence</option>
                                <option value="Memorandom of Agreement">Memorandom of Agreement</option>
                                <option value="Quarry Lease">Quarry Lease</option>
                                <option value="Private Lease">Private Lease</option>
                                <option value="Deed of Gift">Deed of Gift</option>
                                <option value="Deed of Partition">Deed of Partition</option>
                                <option value="Non-European Occupational Lease">Non-European Occupational Lease</option>
                                <option value="Deed of Revocation">Deed of Revocation</option>
                                <option value="Deed of lease">Deed of lease</option>
                                <option value="Deed of Reconveyance">Deed of Reconveyance</option>
                                <option value="Letter of Administration">Letter of Administration</option>
                                <option value="Customary Inhertitance">Customary Inhertitance</option>
                                <option value="Certificate of Purchase">Certificate of Purchase</option>
                                <option value="Deed of Rectification">Deed of Rectification</option>
                                <option value="Building Lease">Building Lease</option>
                                <option value="Memorandum of Loss">Memorandum of Loss</option>
                                <option value="Vesting Deed">Vesting Deed</option>
                                <option value="ST Fragmentation">ST Fragmentation</option>
                                <option value="Occupation Permit">Occupancy Permit</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label for="transactionDate" class="text-sm">Transaction/Certificate Date</label>
                            <input type="date" id="transactionDate" name="transactionDate" x-model="transactionDate" class="form-input text-sm">
                        </div>
                    </div>

                    <!-- Registration Number -->
                    <div class="space-y-1">
                        <label class="text-sm">Registration Number</label>
                        <div class="grid grid-cols-5 gap-2">
                            <div>
                                <label for="serialNo" class="text-xs">Serial No.</label>
                                <input id="serialNo" name="serialNo" x-model="serialNo" @input="pageNo = serialNo" class="form-input text-xs py-1" placeholder="e.g. 1">
                            </div>
                            <div>
                                <label for="pageNo" class="text-xs text-gray-500">Page No. (Auto-filled)</label>
                                <input id="pageNo" name="pageNo" x-model="pageNo" readonly class="form-input text-xs py-1 bg-gray-100 text-gray-500 cursor-not-allowed" placeholder="Same as Serial No.">
                            </div>
                            <div>
                                <label for="volumeNo" class="text-xs">Volume No.</label>
                                <input id="volumeNo" name="volumeNo" x-model="volumeNo" class="form-input text-xs py-1" placeholder="e.g. 2">
                            </div>
                            <div>
                                <label for="regDate" class="text-xs">Reg Date</label>
                                <input id="regDate" name="regDate" type="date" x-model="regDate" class="form-input text-xs py-1">
                            </div>
                            <div>
                                <label for="regTime" class="text-xs">Reg Time</label>
                                <input id="regTime" name="regTime" type="time" x-model="regTime" class="form-input text-xs py-1">
                            </div>
                        </div>
                        
                        <!-- Registration Number Preview -->
                        <div x-show="regNumberPreview" x-transition class="mt-2 p-3 bg-blue-50 border-2 border-blue-200 rounded-lg shadow-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm font-semibold text-blue-700">Registration Number:</span>
                                </div>
                                <span class="text-lg font-bold text-blue-800 tracking-wider" x-text="regNumberPreview"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Land Use and Period -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label for="landUse" class="text-sm">Land Use</label>
                            <select id="landUse" name="land_use" x-model="landUse" class="form-select text-sm">
                                <option value="">Select land use</option>
                                <option value="RESIDENTIAL">RESIDENTIAL</option>
                                <option value="AGRICULTURAL">AGRICULTURAL</option>
                                <option value="COMMERCIAL">COMMERCIAL</option>
                                <option value="COMMERCIAL ( WARE HOUSE)">COMMERCIAL ( WARE HOUSE)</option>
                                <option value="COMMERCIAL (OFFICES)">COMMERCIAL (OFFICES)</option>
                                <option value="COMMERCIAL (PETROL FILLING STATION)">COMMERCIAL (PETROL FILLING STATION)</option>
                                <option value="COMMERCIAL (RICE PROCESSING)">COMMERCIAL (RICE PROCESSING)</option>
                                <option value="COMMERCIAL (SCHOOL)">COMMERCIAL (SCHOOL)</option>
                                <option value="COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)">COMMERCIAL (SHOPS & PUBLIC CONVINIENCE)</option>
                                <option value="COMMERCIAL (SHOPS AND OFFICES)">COMMERCIAL (SHOPS AND OFFICES)</option>
                                <option value="COMMERCIAL (SHOPS)">COMMERCIAL (SHOPS)</option>
                                <option value="COMMERCIAL (WAREHOUSE)">COMMERCIAL (WAREHOUSE)</option>
                                <option value="COMMERCIAL (WORKSHOP AND OFFICES)">COMMERCIAL (WORKSHOP AND OFFICES)</option>
                                <option value="COMMERCIAL AND RESIDENTIAL">COMMERCIAL AND RESIDENTIAL</option>
                                <option value="INDUSTRIAL">INDUSTRIAL</option>
                                <option value="INDUSTRIAL (SMALL SCALE)">INDUSTRIAL (SMALL SCALE)</option>
                                <option value="RESIDENTIAL AND COMMERCIAL">RESIDENTIAL AND COMMERCIAL</option>
                                <option value="RESIDENTIAL/COMMERCIAL">RESIDENTIAL/COMMERCIAL</option>
                                <option value="RESIDENTIAL/COMMERCIAL LAYOUT">RESIDENTIAL/COMMERCIAL LAYOUT</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label for="period" class="text-sm">Period/Tenancy</label>
                            <div class="flex space-x-1">
                                <input id="period" name="period" type="number" x-model="period" class="form-input text-sm" placeholder="Period">
                                <select id="periodUnit" name="periodUnit" x-model="periodUnit" class="form-select text-sm w-[90px]">
                                    <option value="Days">Days</option>
                                    <option value="Months">Months</option>
                                    <option value="Years" selected>Years</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Details Section -->
            <div x-show="showTransactionDetails" 
                 x-transition
                 class="form-section" 
                 >
                <h4 class="form-section-title">Transaction Details</h4>
                
                <!-- Party Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-sm font-medium" x-text="currentPartyLabels.first"></label>
                        <input type="text" 
                               :name="getFirstPartyFieldName()" 
                               x-model="firstParty"
                               class="form-input text-sm"
                               :class="isGovernmentTransaction ? 'bg-gray-100 text-gray-800' : ''"
                               :readonly="isGovernmentTransaction"
                               :placeholder="isGovernmentTransaction ? 'KANO STATE GOVERNMENT' : 'Enter ' + currentPartyLabels.first.toLowerCase() + ' name'">
                    </div>
                    <div class="space-y-1">
                        <label class="text-sm font-medium" x-text="currentPartyLabels.second"></label>
                        <input type="text" 
                               :name="getSecondPartyFieldName()" 
                               x-model="secondParty" 
                               class="form-input text-sm"
                               :placeholder="'Enter ' + currentPartyLabels.second.toLowerCase() + ' name'">
                    </div>
                </div>

                <div class="mt-6" id="index-additional-instruments">
                    <div class="flex items-center justify-between">
                        <h4 class="text-base font-semibold text-gray-800">Additional Instruments</h4>
                        <button type="button" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-md text-white bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 shadow hover:from-indigo-600 hover:via-purple-600 hover:to-pink-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition" @click="addInstrumentEntry()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add More Instruments
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mt-1">
                        Capture every additional instrument (Assignment, Surrender, Subdivision, Merger, Withdrawn, Revoke). Each entry will save as its own index-card row.
                    </p>
                    <template x-if="instrumentEntries.length === 0">
                        <p class="text-xs text-gray-400 mt-3">No supplemental instruments yet.</p>
                    </template>
                    <template x-for="(instrument, index) in instrumentEntries" :key="instrument.id">
                        <div class="mt-4 rounded-lg p-4 space-y-4 border" :class="[getInstrumentColors(instrument.type).border, getInstrumentColors(instrument.type).bg]">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h5 class="font-medium text-gray-700">Instrument <span x-text="index + 1"></span></h5>
                                    <span x-show="instrument.type" class="px-2 py-0.5 text-xs font-semibold rounded-full" :class="getInstrumentColors(instrument.type).tag" x-text="instrument.type"></span>
                                </div>
                                <button type="button" class="text-sm text-red-600 hover:text-red-700" @click="removeInstrumentEntry(index)">Remove</button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Instrument Type</label>
                                    <select class="form-select text-sm" :name="`instrument_entries[${index}][type]`" x-model="instrument.type" @change="applyInstrumentDefaults(instrument)">
                                        <option value="">Select type</option>
                                        <template x-for="option in instrumentTypeOptions" :key="option">
                                            <option :value="option" x-text="option"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700">Instrument Date</label>
                                    <input type="date" class="form-input text-sm" :name="`instrument_entries[${index}][date]`" x-model="instrument.date">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="text-sm font-medium text-gray-700" x-text="getInstrumentLabels(instrument.type).from"></label>
                                    <input type="text" class="form-input text-sm" :placeholder="getInstrumentLabels(instrument.type).from" :name="`instrument_entries[${index}][party_from]`" x-model="instrument.party_from">
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-gray-700 flex items-center gap-1">
                                        <span x-text="getInstrumentLabels(instrument.type).to"></span>
                                        <span class="text-xs text-gray-400" x-show="instrument.type === 'Subdivision'">(1 ... n)</span>
                                    </label>
                                    <input type="text" class="form-input text-sm" :placeholder="getInstrumentLabels(instrument.type).to" :name="`instrument_entries[${index}][party_to]`" x-model="instrument.party_to">
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Property Description -->
            <div class="space-y-1">
                <div class="flex justify-between items-center">
                    <label class="text-sm">Property Description</label>
                    <button type="button" @click="updatePropertyDescription()" class="text-xs text-blue-600 hover:text-blue-800">
                        🔄 Refresh Description
                    </button>
                </div>
                <textarea id="property-description" 
                          name="property_description" 
                          rows="4" 
                          x-model="propertyDescription"
                          class="form-input text-sm bg-gray-50" 
                          readonly
                          placeholder="This field will be auto-populated based on property details above"></textarea>
                <div class="text-xs text-gray-500 italic">This field auto-populates from House No, Street Name (or specified Other Street Name), District (or specified Other District Name), LGA, and State.</div>
            </div>


            <div class="flex justify-end space-x-3 pt-2 border-t mt-4 sticky bottom-0 bg-white z-10">
                <button id="property-submit-btn" type="submit" class="btn btn-primary">Submit</button>
            </div>
        </div>
    </form>
    </div>
    </div>
</div>
