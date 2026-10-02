<div id="property-view-dialog" class="dialog-overlay hidden">
    <div class="dialog-content property-form-content">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Property Record Details</h2>
            <button id="close-property-view" class="text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        
        <div id="property-view-content" class="space-y-6 max-h-[75vh] overflow-y-auto">
            <!-- Main info section -->
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <h3 class="text-lg font-semibold text-blue-800 mb-2">File Information</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div class="col-span-3">
                        <p class="text-sm text-gray-500">Title Type</p>
                        <p id="view-title-type" class="font-medium"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">MLS File Number</p>
                        <p id="view-mls-file" class="font-medium"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">KANGIS File Number</p>
                        <p id="view-kangis-file" class="font-medium"></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">New KANGIS File Number</p>
                        <p id="view-newkangis-file" class="font-medium"></p>
                    </div>
                </div>
            </div>
            
            <!-- Property information section -->
            <div class="bg-white p-4 rounded-lg border">
                <h3 class="text-lg font-semibold mb-3">Property Details</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p id="view-property-description" class="mt-1"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">LGA/City</p>
                            <p id="view-lgsa-city" class="font-medium"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Plot Number</p>
                            <p id="view-plot-no" class="font-medium"></p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Layout</p>
                            <p id="view-layout" class="font-medium"></p>
                        </div>
                        <div style="display: none">
                            <p class="text-sm text-gray-500">Schedule</p>
                            <p id="view-schedule" class="font-medium"></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Transaction details section -->
            <div class="bg-white p-4 rounded-lg border">
                <h3 class="text-lg font-semibold mb-3">Transaction Details</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Transaction Type</p>
                            <p id="view-transaction-type" class="font-medium"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Transaction Date</p>
                            <p id="view-transaction-date" class="font-medium"></p>
                        </div>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Registration Number</p>
                        <p id="view-reg-no" class="font-medium"></p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Instrument Type</p>
                            <p id="view-instrument-type" class="font-medium"></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Period</p>
                            <p id="view-period" class="font-medium"></p>
                        </div>
                    </div>

                         <div class="grid grid-cols-2 gap-4">
                    <div id="view-party-from-container">
                        <p class="text-sm text-gray-500" id="view-party-from-label">From</p>
                        <p id="view-party-from" class="font-medium"></p>
                    </div>
                    <div id="view-party-to-container">
                        <p class="text-sm text-gray-500" id="view-party-to-label">To</p>
                        <p id="view-party-to" class="font-medium"></p>
                    </div>
                </div>
                </div>
            </div>
            
            
            
            <!-- Record metadata -->
             <div class="bg-gray-50 p-4 rounded-lg border text-sm text-gray-500" style="display: none">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p>Created: <span id="view-created-at"></span></p>
                        <p>By: <span id="view-created-by"></span></p>
                    </div>
                    <div>
                        <p>Updated: <span id="view-updated-at"></span></p>
                        <p>By: <span id="view-updated-by"></span></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-end space-x-3 pt-4 border-t mt-4">
            
            <button id="close-view" class="btn btn-primary">Close</button>
        </div>
    </div>
</div>

<script>
    // Add event listeners when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Add close functionality to both close buttons
        document.getElementById('close-property-view').addEventListener('click', closePropertyView);
        document.getElementById('close-view').addEventListener('click', closePropertyView);
    });
    
    // Function to close the property view dialog
    function closePropertyView() {
        document.getElementById('property-view-dialog').classList.add('hidden');
    }
    
    function displayPropertyDetails(property) {
        // Set title type
        document.getElementById('view-title-type').textContent = property.title_type || 'Not specified';
        
        // Set all file numbers
        document.getElementById('view-mls-file').textContent = property.mlsFNo || 'Not specified';
        document.getElementById('view-kangis-file').textContent = property.kangisFileNo || 'Not specified';
        document.getElementById('view-newkangis-file').textContent = property.NewKANGISFileno || 'Not specified';
        
        // Set property details
        document.getElementById('view-property-description').textContent = property.property_description || 'No description available';
        document.getElementById('view-lgsa-city').textContent = property.lgsaOrCity || 'Not specified';
        document.getElementById('view-plot-no').textContent = property.plot_no || 'Not specified';
        document.getElementById('view-layout').textContent = property.layout || 'Not specified';
        document.getElementById('view-schedule').textContent = property.schedule || 'Not specified';
        
        // Set transaction details
        document.getElementById('view-transaction-type').textContent = property.transaction_type || 'Not specified';
        document.getElementById('view-transaction-date').textContent = property.transaction_date ? new Date(property.transaction_date).toLocaleDateString() : 'Not specified';
        document.getElementById('view-reg-no').textContent = property.regNo || 'Not specified';
        document.getElementById('view-instrument-type').textContent = property.instrument_type || 'Not specified';
        
        // Set period
        const periodText = property.period ? 
            `${property.period} ${property.period_unit || 'Years'}` : 
            'Not specified';
        document.getElementById('view-period').textContent = periodText;
        
        // Set party information based on transaction type
        let fromParty = '';
        let toParty = '';
        let fromLabel = 'From';
        let toLabel = 'To';
        
        switch (property.transaction_type.toLowerCase()) {
            case 'assignment':
                fromParty = property.Assignor || '';
                toParty = property.Assignee || '';
                fromLabel = 'Assignor';
                toLabel = 'Assignee';
                break;
            case 'mortgage':
                fromParty = property.Mortgagor || '';
                toParty = property.Mortgagee || '';
                fromLabel = 'Mortgagor';
                toLabel = 'Mortgagee';
                break;
            case 'surrender':
                fromParty = property.Surrenderor || '';
                toParty = property.Surrenderee || '';
                fromLabel = 'Surrenderor';
                toLabel = 'Surrenderee';
                break;
            case 'sub-lease':
            case 'lease':
                fromParty = property.Lessor || '';
                toParty = property.Lessee || '';
                fromLabel = 'Lessor';
                toLabel = 'Lessee';
                break;
            default:
                fromParty = property.Grantor || '';
                toParty = property.Grantee || '';
                fromLabel = 'Grantor';
                toLabel = 'Grantee';
        }
        
        document.getElementById('view-party-from-label').textContent = fromLabel;
        document.getElementById('view-party-to-label').textContent = toLabel;
        document.getElementById('view-party-from').textContent = fromParty || 'Not specified';
        document.getElementById('view-party-to').textContent = toParty || 'Not specified';
        
        // Set record metadata
        document.getElementById('view-created-at').textContent = property.created_at ? new Date(property.created_at).toLocaleString() : 'Unknown';
        document.getElementById('view-updated-at').textContent = property.updated_at ? new Date(property.updated_at).toLocaleString() : 'Unknown';
        document.getElementById('view-created-by').textContent = property.created_by || 'Unknown';
        document.getElementById('view-updated-by').textContent = property.updated_by || 'Unknown';
        
        // Store property ID for edit button
        //document.getElementById('edit-from-view').dataset.propertyId = property.id;
    }
</script>
