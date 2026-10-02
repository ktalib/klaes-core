<div class="betterment-bill-component">
    <style>
        /* Scoped styles that won't conflict with other elements */
        .betterment-bill-component .bb-tab-content {
            display: none;
        }
        .betterment-bill-component .bb-tab-content.active2 {
            display: block;
        }
        .betterment-bill-component .bb-tab-btn.active2 {
            border-bottom-color: rgb(22 163 74);
            color: rgb(22 163 74);
            font-weight: 500;
        }
    </style>

    <div class="border rounded-lg shadow-sm bg-white">
        <!-- Tab Navigation -->
        <div class="p-4">
            <div class="border-b border-gray-200 mb-4">
                <div class="flex">
                    <button type="button" class="bb-tab-btn active2 px-4 py-2 border-b-2 mr-2" 
                            data-tab="generate">Generate Betterment Bill</button>
                    <button type="button" class="bb-tab-btn px-4 py-2 border-b-2 mr-2" 
                            data-tab="receipt">Betterment Bill Receipt</button>
                </div>
            </div>

            <!-- Generate Tab -->
            <div id="bb-generate-tab" class="bb-tab-content active2">
                <div class="p-4 border rounded-lg">
                    <h3 class="text-sm font-medium mb-2">Generate Betterment Bill</h3>
                    <p class="text-xs text-gray-500 mb-4">Calculate betterment charges based on property value</p>
                  
                    
                    <form id="bb-form" class="space-y-4">
                        @csrf
                        
                        <input type="hidden" name="application_id" value="{{ $application->id }}">
                        <input type="hidden" name="Sectional_Title_File_No" value="{{ $application->fileno }}">
                        


                        <div>
                            <label class="text-xs font-medium">Reference ID</label>
                            <input type="text" name="ref_id" id="bb-ref-id-input" 
                                   value="BB-{{ $application->id }}-{{ date('Ymd') }}"
                                   class="w-full p-2 border rounded mt-1 bg-gray-50" readonly>
                        </div>


                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium">Property Value (₦)</label>
                                <input type="text" name="property_value" id="bb-property-value" 
                                       class="w-full p-2 border rounded mt-1" required>
                            </div>
                            <div>
                                <label class="text-xs font-medium">Betterment Rate (%)</label>
                                <input type="text" name="betterment_rate" id="bb-rate" value="2.5" 
                                       class="w-full p-2 border rounded mt-1" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium">Land Size (sqm)</label>
                                <input type="text" name="land_size" id="bb-land-size" 
                                       value="{{ $application->property_size ?? '1,200' }}"
                                       class="w-full p-2 border rounded mt-1 bg-gray-50">
                            </div>
                            <div>
                                <label class="text-xs font-medium">Number of Units</label>
                                <input type="text" name="units_count" id="bb-units-count" 
                                       value="{{ $application->NoOfUnits ?? '12' }}"
                                       class="w-full p-2 border rounded mt-1 bg-gray-50" readonly>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-3 rounded mb-4">
                            <h4 class="text-xs font-medium">Calculation Formula</h4>
                            <p class="text-xs text-gray-500">Betterment Fee = Property Value × Betterment Rate × Land Size Factor</p>
                            <p class="text-xs text-gray-400 mt-1">Land Size Factor is automatically calculated based on the property size.</p>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-xs text-gray-500">Estimated Amount</p>
                                <p class="text-lg font-bold" id="bb-amount">₦0.00</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" id="bb-calc-btn" class="px-3 py-1.5 text-xs bg-gray-100 rounded">
                                    Calculate
                                </button>
                                <button type="button" id="bb-save-btn" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded">
                                    Generate Bill
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Receipt Tab -->
            <div id="bb-receipt-tab" class="bb-tab-content">
                <div class="p-4 border rounded-lg">
                    <h3 class="text-sm font-medium mb-2">Betterment Bill Receipt</h3>
                    <p class="text-xs text-gray-500 mb-4">View and print betterment charges</p>
                    
                    <div id="bb-receipt-container">
                        <div class="text-center p-8">
                            <p class="text-sm text-gray-500">Loading betterment bill details...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Using an IIFE to avoid global scope pollution
(function() {
    // Variables
    let appId = "{{ $application->id }}";
    let formData = {};
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Get elements
        const calcBtn = document.getElementById('bb-calc-btn');
        const saveBtn = document.getElementById('bb-save-btn');
        const tabBtns = document.querySelectorAll('.bb-tab-btn');
        
        // Add event listeners
        calcBtn.addEventListener('click', calculateBetterment);
        saveBtn.addEventListener('click', saveBetterment);
        
        // Tab switching
        tabBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                // Get tab name
                const tab = this.getAttribute('data-tab');
                
                // Update active2 tab button
                tabBtns.forEach(b => b.classList.remove('active2'));
                this.classList.add('active2');
                
                // Hide all tab contents
                document.querySelectorAll('.bb-tab-content').forEach(content => {
                    content.classList.remove('active2');
                });
                
                // Show selected tab
                document.getElementById(`bb-${tab}-tab`).classList.add('active2');
                
                // Load data if receipt tab
                if (tab === 'receipt') {
                    loadBettermentBill();
                }
            });
        });
        
        // Load data on init
        checkExistingBill();
    });
    
    // Check for existing betterment bill
    function checkExistingBill() {
        fetch(`{{ route('betterment-bill.show', '') }}/${appId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Initial bill check:', data);
            if (data.success && data.bill) {
                // Populate form with existing data
                document.getElementById('bb-property-value').value = formatNumber(data.bill.property_value || 0);
                document.getElementById('bb-rate').value = data.bill.betterment_rate || 2.5;
                
                // Format and display betterment charges
                const bettermentValue = parseFloat(data.bill.Betterment_Charges || 0);
                document.getElementById('bb-amount').textContent = '₦' + formatNumber(bettermentValue.toFixed(2));
                
                // Update reference ID
                if (data.bill.ref_id) {
                    document.getElementById('bb-ref-id').textContent = data.bill.ref_id;
                    document.getElementById('bb-ref-id-input').value = data.bill.ref_id;
                }
            }
        })
        .catch(error => {
            console.error('Error checking for existing bill:', error);
        });
    }
    
    // Calculate betterment charges
    function calculateBetterment() {
        const propertyValue = document.getElementById('bb-property-value').value.replace(/,/g, '');
        const bettermentRate = document.getElementById('bb-rate').value;
        
        if (!propertyValue || !bettermentRate) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please enter property value and betterment rate',
                confirmButtonColor: '#16a34a'
            });
            return;
        }
        
        // Make AJAX request to calculate
        const requestData = {
            property_value: propertyValue,
            betterment_rate: bettermentRate
        };
        
        fetch('{{ route("betterment-bill.calculate") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: JSON.stringify(requestData)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Calculation response:', data);
            if (data.success) {
                document.getElementById('bb-amount').textContent = '₦' + data.betterment_charges;
                formData = requestData; // Store for later use
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Calculation Error',
                    text: data.message,
                    confirmButtonColor: '#16a34a'
                });
            }
        })
        .catch(error => {
            console.error('Calculation error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error calculating betterment charges',
                confirmButtonColor: '#16a34a'
            });
        });
    }
    
    // Save betterment bill
    function saveBetterment() {
        const formEl = document.getElementById('bb-form');
        const formData = new FormData(formEl);
        
        // Remove commas from numeric values
        formData.set('property_value', document.getElementById('bb-property-value').value.replace(/,/g, ''));
        
        fetch('{{ route("betterment-bill.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Save response:', data);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: data.message,
                    confirmButtonColor: '#16a34a'
                }).then(() => {
                    // Switch to receipt tab
                    document.querySelector('.bb-tab-btn[data-tab="receipt"]').click();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message,
                    confirmButtonColor: '#16a34a'
                });
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error saving betterment bill',
                confirmButtonColor: '#16a34a'
            });
        });
    }
    
    // Load betterment bill
    function loadBettermentBill() {
        const container = document.getElementById('bb-receipt-container');
        container.innerHTML = '<div class="text-center p-8"><p class="text-sm text-gray-500">Loading betterment bill details...</p></div>';
        
        fetch(`{{ route('betterment-bill.show', '') }}/${appId}`, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Receipt data:', data);
            if (data.success && data.bill) {
                renderReceipt(data.bill, data.application);
            } else {
                container.innerHTML = `
                    <div class="text-center p-8">
                        <p class="text-sm text-gray-500">No betterment bill has been generated yet.</p>
                        <p class="text-xs text-gray-400 mt-2">Please generate a bill first.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Load error:', error);
            container.innerHTML = `
                <div class="text-center p-8">
                    <p class="text-sm text-red-500">Error loading betterment bill</p>
                </div>
            `;
        });
    }
    
    // Render receipt
    function renderReceipt(bill, application) {
        const container = document.getElementById('bb-receipt-container');
        
        // Format values
        const bettermentCharges = formatNumber(parseFloat(bill.Betterment_Charges || 0).toFixed(2));
        const propertyValue = formatNumber(bill.property_value || 0);
        const date = new Date(bill.created_at || new Date()).toLocaleDateString();
        
        // Generate receipt HTML
        const receiptHTML = `
            <div class="bg-white border rounded-lg p-4">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-medium">Betterment Bill Receipt</h3>
                    <button type="button" onclick="window.printBettermentReceipt()" class="px-3 py-1 text-xs bg-gray-100 rounded flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                        Print
                    </button>
                </div>
                
                <div class="border-t border-b py-3 mb-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500">Bill Reference:</p>
                            <p class="text-sm font-medium">${bill.ref_id || ''}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Date:</p>
                            <p class="text-sm font-medium">${date}</p>
                        </div>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <p class="text-xs text-gray-500">Property Value:</p>
                        <p class="text-sm font-medium">₦${propertyValue}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Betterment Rate:</p>
                        <p class="text-sm font-medium">${bill.betterment_rate || 0}%</p>
                    </div>
                </div>
                
                <div class="bg-gray-50 p-3 rounded mb-3">
                    <p class="text-xs text-gray-500">Betterment Charges:</p>
                    <p class="text-lg font-bold">₦${bettermentCharges}</p>
                </div>
                
                <div class="bg-blue-50 p-3 rounded mb-3">
                  
                </div>
            </div>
        `;
        
        container.innerHTML = receiptHTML;
    }
    
    // Format number with commas
    function formatNumber(number) {
        if (number === null || number === undefined) return '0';
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    // Make the print function globally available
    window.printBettermentReceipt = function(bill, application) {
        // Open the print template in a new window
        const printUrl = "{{ route('betterment-bill.print', ['id' => $application->id]) }}";
        const printWindow = window.open(printUrl, '_blank');
        
        if (!printWindow) {
            Swal.fire({
                icon: 'error',
                title: 'Print Error',
                text: 'Pop-up blocked! Please allow pop-ups for this site to print receipts.',
                confirmButtonColor: '#16a34a'
            });
        }
    };
})();
</script>
