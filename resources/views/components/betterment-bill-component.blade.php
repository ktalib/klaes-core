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

                        <input type="hidden" name="{{ Str::contains(request()->url(), 'sub-actions') ? 'sub_application_id' : 'application_id' }}" value="{{ $application->id }}" disabled>
                        <input type="hidden" name="Sectional_Title_File_No" value="{{ $application->fileno }}" disabled>

                        <div>
                            <label class="text-xs font-medium">Reference ID</label>
                            <input type="text" name="ref_id" id="bb-ref-id-input" 
                                   value="BB-{{ $application->id }}-{{ date('Ymd') }}"
                                   class="w-full p-2 border rounded mt-1 bg-gray-50" readonly disabled>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium">Property Value (₦)</label>
                                <input type="text" name="property_value" id="bb-property-value" 
                                       class="w-full p-2 border rounded mt-1" required disabled>
                            </div>
                            <div>
                                <label class="text-xs font-medium">Betterment Rate (%)</label>
                                <input type="text" name="betterment_rate" id="bb-rate" value="2.5" 
                                       class="w-full p-2 border rounded mt-1" required disabled>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-xs font-medium">Land Size (sqm)</label>
                                <input type="text" name="land_size" id="bb-land-size" 
                                       value="{{ $application->property_size ?? '1,200' }}"
                                       class="w-full p-2 border rounded mt-1 bg-gray-50" disabled>
                            </div>
                            <div>
                                <label class="text-xs font-medium">Number of Units</label>
                                <input type="text" name="units_count" id="bb-units-count" 
                                       value="{{ $application->NoOfUnits ?? '12' }}"
                                       class="w-full p-2 border rounded mt-1 bg-gray-50" readonly disabled>
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
                            {{-- <div class="flex gap-2">
                                <button type="button" id="bb-calc-btn" class="px-3 py-1.5 text-xs bg-gray-100 rounded">
                                    Calculate
                                </button>
                                <button type="button" id="bb-save-btn" class="px-3 py-1.5 text-xs bg-green-600 text-white rounded">
                                    Generate Bill
                                </button>
                            </div> --}}
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
        // const calcBtn = document.getElementById('bb-calc-btn');
        // const saveBtn = document.getElementById('bb-save-btn');
        const tabBtns = document.querySelectorAll('.bb-tab-btn');
        
        // Add event listeners
        // calcBtn.addEventListener('click', calculateBetterment);
        // saveBtn.addEventListener('click', saveBetterment);
        
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
                const propertyValue = parseFloat(data.bill.property_value || 0);
                document.getElementById('bb-property-value').value = formatNumber(propertyValue);
                document.getElementById('bb-rate').value = data.bill.betterment_rate || 2.5;
                
                // Format and display betterment charges
                const bettermentValue = parseFloat(data.bill.Betterment_Charges || 0);
                document.getElementById('bb-amount').textContent = '₦' + formatNumber(bettermentValue.toFixed(2));
                
                // Update reference ID
                if (data.bill.ref_id) {
                    document.getElementById('bb-ref-id-input').value = data.bill.ref_id;
                }
                
                console.log('Bill data loaded:', {
                    property_value: propertyValue,
                    betterment_charges: bettermentValue,
                    ref_id: data.bill.ref_id
                });
            } else {
                console.log('No existing bill found');
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
        const landSize = document.getElementById('bb-land-size').value.replace(/,/g, '');
        
        if (!propertyValue || !bettermentRate) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please enter property value and betterment rate',
                confirmButtonColor: '#16a34a'
            });
            return;
        }
        
        // Calculate land size factor (matching backend logic)
        const size = parseFloat(landSize) || 1200;
        let landSizeFactor;
        if (size <= 500) landSizeFactor = 0.8;
        else if (size <= 1000) landSizeFactor = 1.0;
        else if (size <= 2000) landSizeFactor = 1.2;
        else landSizeFactor = 1.5;
        
        // Make AJAX request to calculate
        const requestData = {
            property_value: propertyValue,
            betterment_rate: bettermentRate,
            land_size: landSize,
            land_size_factor: landSizeFactor
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
        const propertyValue = document.getElementById('bb-property-value').value.replace(/,/g, '');
        const landSize = document.getElementById('bb-land-size').value.replace(/,/g, '');
        const bettermentRate = document.getElementById('bb-rate').value;
        
        // Validate required fields
        if (!propertyValue || propertyValue <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please enter a valid property value',
                confirmButtonColor: '#16a34a'
            });
            return;
        }
        
        if (!bettermentRate || bettermentRate <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please enter a valid betterment rate',
                confirmButtonColor: '#16a34a'
            });
            return;
        }
        
        // Calculate first to ensure we have the amount
        const size = parseFloat(landSize) || 1200;
        let landSizeFactor;
        if (size <= 500) landSizeFactor = 0.8;
        else if (size <= 1000) landSizeFactor = 1.0;
        else if (size <= 2000) landSizeFactor = 1.2;
        else landSizeFactor = 1.5;
        
        const bettermentCharges = propertyValue * (bettermentRate / 100) * landSizeFactor;
        
        // Update the display
        document.getElementById('bb-amount').textContent = '₦' + formatNumber(bettermentCharges.toFixed(2));
        
        formData.set('property_value', propertyValue);
        formData.set('land_size', landSize);
        formData.set('betterment_rate', bettermentRate);
        
        // Show loading
        Swal.fire({
            title: 'Saving Bill...',
            text: 'Please wait while we save your betterment bill.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Debug: Log form data
        console.log('Form data being sent:');
        for (let [key, value] of formData.entries()) {
            console.log(key, value);
        }
        
        fetch('{{ route("betterment-bill.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
            },
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Save response:', data);
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: data.message + ' Amount: ₦' + data.betterment_charges,
                    confirmButtonColor: '#16a34a'
                }).then(() => {
                    // Refresh the data and switch to receipt tab
                    checkExistingBill();
                    document.querySelector('.bb-tab-btn[data-tab="receipt"]').click();
                });
            } else {
                console.error('Save failed:', data);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Error saving betterment bill',
                    confirmButtonColor: '#16a34a'
                });
            }
        })
        .catch(error => {
            console.error('Save error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Network error saving betterment bill',
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
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            // Always show the receipt if bill exists, even if amount is zero
            if (data.success && data.bill) {
                renderReceipt(data.bill, data.application);
            } else {
                container.innerHTML = `
                    <div class="text-center p-8">
                        <p class="text-sm text-gray-500">No betterment bill has been generated yet.</p>
                        <p class="text-xs text-gray-400 mt-2">Please generate a bill first using the "Generate Betterment Bill" tab.</p>
                        <button type="button" onclick="document.querySelector('.bb-tab-btn[data-tab=\\"generate\\"]').click()" 
                                class="mt-3 px-3 py-1 text-xs bg-green-600 text-white rounded">
                            Go to Generate Bill
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Load error:', error);
            container.innerHTML = `
                <div class="text-center p-8">
                    <p class="text-sm text-red-500">Error loading betterment bill: ${error.message}</p>
                    <button type="button" onclick="loadBettermentBill()" 
                            class="mt-3 px-3 py-1 text-xs bg-gray-600 text-white rounded">
                        Retry
                    </button>
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
                    <!-- Print button removed -->
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
        // Print function retained for compatibility, but no button calls it now
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
  