@php
    $is_ai = $is_ai_assistant ?? false;
@endphp

<!-- Alpine.js CDN -->
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

@if(!$is_ai)
<div id="property-form-dialog" class="dialog-overlay hidden">
    <div class="dialog-content property-form-content">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Add New Property</h2>
            <button id="close-property-form" class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
@endif

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
    
    // Track current values from components
    currentStreetName: '',
    currentDistrict: '',
    
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
        'Deed of Release': { first: 'Releasor', second: 'Releasee' },
        'Deed of Surrender': { first: 'Surrenderor', second: 'Surrenderee' },
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
        const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy', 'Occupation Permit'];
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
            const govTypes = ['Certificate of Occupancy', 'ST Certificate of Occupancy', 'SLTR Certificate of Occupancy', 'Customary Right of Occupancy', 'Occupation Permit'];
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
            'Deed of Surrender': 'Surrenderor',
            'Deed of Sub Lease': 'Lessor',
            'Deed of Sub Under Lease': 'Lessor',
            'Indenture of Lease': 'Lessor',
            'Quarry Lease': 'Lessor',
            'Private Lease': 'Lessor',
            'Building Lease': 'Lessor',
            'Tenancy Agreement': 'Landlord',
            'Deed of Release': 'Releasor',
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
            'Deed of Surrender': 'Surrenderee',
            'Deed of Sub Lease': 'Lessee',
            'Deed of Sub Under Lease': 'Lessee',
            'Indenture of Lease': 'Lessee',
            'Quarry Lease': 'Lessee',
            'Private Lease': 'Lessee',
            'Building Lease': 'Lessee',
            'Tenancy Agreement': 'Tenant',
            'Deed of Release': 'Releasee',
            'Deed of Transfer': 'Transferee',
            'Deed of Gift': 'Donee',
            'Letter of Administration': 'Beneficiary',
            'Certificate of Purchase': 'Purchaser'
        };
        return typeMap[this.transactionType] || 'Grantee';
    },
    
    submitForm() {
                
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
        
        <div class="space-y-4 py-2 flex-1 @if(!$is_ai) max-h-[75vh] overflow-y-auto pr-1 @endif">
            <!-- Top section with two columns -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Left column - Title Type Section -->
                <div class="form-section">
                   
                  <!-- File Number -->
                  <div class="space-y-1" x-data="{ showManualEntry: false }" x-effect="(() => { const manual=$el.querySelector('#manual-fileno-container'); const smart=$el.querySelector('#smart-fileno-container'); if(manual){ manual.querySelectorAll('input, select, textarea').forEach(el=> el.disabled = !showManualEntry); } if(smart){ smart.querySelectorAll('input, select, textarea').forEach(el=> el.disabled = showManualEntry); } })()">
                    <div class="flex items-center justify-between mb-3">
                        <label for="fileno-select" class="block text-sm font-medium text-gray-700">Select File Number</label>
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
                                <option value="Deed of Release">Deed of Release</option>
                                <option value="Deed of Assignment">Deed of Assignment</option>
                                <option value="ST Assignment">ST Assignment</option>
                                <option value="Deed of Mortgage">Deed of Mortgage</option>
                                <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                                <option value="Deed of Sub Lease">Deed of Sub Lease</option>
                                <option value="Deed of Sub Under Lease">Deed of Sub Under Lease</option>
                                <option value="Power of Attorney">Power of Attorney</option>
                                <option value="Deed of Surrender">Deed of Surrender</option>
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
                            <select id="landUse" name="landUse" x-model="landUse" class="form-select text-sm">
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

@if(!$is_ai)
    </div>
</div>
@else
<script>
// In AI inline mode, ensure the container is visible and not modal
document.addEventListener('DOMContentLoaded', function() {
  const aiRoot = document.getElementById('ai-add-form-root');
  if (aiRoot) {
    aiRoot.style.display = 'block';
  }
});
</script>
@endif