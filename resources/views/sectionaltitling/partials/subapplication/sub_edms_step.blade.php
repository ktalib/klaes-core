<!-- Step 4: EDMS Workflow -->
<div class="form-section" id="step4">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
            <button class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i data-lucide="database" class="w-5 h-5 mr-2 text-blue-600"></i>
                    <h3 class="text-lg font-bold items-center">EDMS Workflow - Electronic Document Management</h3>
                </div>
                <div class="flex items-center">
                    <span class="text-gray-600 mr-2">Step:</span>
                    <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">4 of 5</span>
                </div>
            </div>
            <p class="text-gray-600 mt-1">Configure electronic document management for your unit application</p>
        </div>

        <div class="flex items-center mb-6">
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center">1</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center">2</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center">3</div>
            </div>    
            <div class="flex items-center mr-4">
                <div class="step-circle active-tab flex items-center justify-center">4</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab flex items-center justify-center">5</div>
            </div>
            <div class="ml-4">Step 4</div>
        </div>

        <div class="mb-6">
            <div class="text-right text-sm text-gray-500">CODE: ST FORM - 4</div>
            <hr class="my-4">
            
            <!-- EDMS Information Card -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 mb-6">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="database" class="w-6 h-6 text-blue-600"></i>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Electronic Document Management System</h3>
                        <p class="text-gray-700 mb-4">
                            The EDMS workflow will be automatically configured for your unit application after submission. 
                            This system will help digitize, organize, and manage all documents related to your unit application.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-white rounded-lg p-4 border border-blue-100">
                                <div class="flex items-center mb-2">
                                    <i data-lucide="folder-plus" class="w-5 h-5 text-blue-600 mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">File Indexing</h4>
                                </div>
                                <p class="text-sm text-gray-600">Digital file organization with metadata</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 border border-blue-100">
                                <div class="flex items-center mb-2">
                                    <i data-lucide="upload" class="w-5 h-5 text-blue-600 mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Scanning</h4>
                                </div>
                                <p class="text-sm text-gray-600">Upload and store scanned documents</p>
                            </div>
                            
                            <div class="bg-white rounded-lg p-4 border border-blue-100">
                                <div class="flex items-center mb-2">
                                    <i data-lucide="tag" class="w-5 h-5 text-blue-600 mr-2"></i>
                                    <h4 class="font-semibold text-gray-800">Pagetyping</h4>
                                </div>
                                <p class="text-sm text-gray-600">Classify and label document pages</p>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-start">
                                <i data-lucide="info" class="w-5 h-5 text-blue-600 mr-2 mt-0.5"></i>
                                <div>
                                    <h4 class="font-semibold text-blue-800 mb-1">What happens next?</h4>
                                    <p class="text-sm text-blue-700">
                                        After your application is submitted, you'll receive access to the EDMS workflow dashboard 
                                        where you can complete the document management process for your unit application.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reference Information -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i data-lucide="link" class="w-5 h-5 text-gray-600 mr-2"></i>
                    Reference Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-800 mb-3">Unit Application Details</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Unit File Number:</span>
                                <span class="font-medium text-gray-800" id="edms-unit-fileno">Will be assigned</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Unit Number:</span>
                                <span class="font-medium text-gray-800" id="edms-unit-number">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Block Number:</span>
                                <span class="font-medium text-gray-800" id="edms-block-number">-</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Floor Number:</span>
                                <span class="font-medium text-gray-800" id="edms-floor-number">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="font-medium text-gray-800 mb-3">Primary Application Reference</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Primary File Number:</span>
                                <span class="font-medium text-gray-800">{{ $motherApplication->fileno ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Plot Number:</span>
                                <span class="font-medium text-gray-800">{{ $motherApplication->property_plot_no ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">District:</span>
                                <span class="font-medium text-gray-800">{{ $motherApplication->property_district ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">LGA:</span>
                                <span class="font-medium text-gray-800">{{ $motherApplication->property_lga ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EDMS Configuration Options -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6" style="display: none;" >
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i data-lucide="settings" class="w-5 h-5 text-gray-600 mr-2"></i>
                    EDMS Configuration
                </h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-800">Auto-create File Index</h4>
                            <p class="text-sm text-gray-600">Automatically create file indexing record upon application submission</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="edms_auto_index" id="edms_auto_index" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" checked>
                            <label for="edms_auto_index" class="ml-2 text-sm font-medium text-gray-700">Enable</label>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-800">Link to Primary Application</h4>
                            <p class="text-sm text-gray-600">Link this unit's EDMS records to the primary application</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="edms_link_primary" id="edms_link_primary" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" checked>
                            <label for="edms_link_primary" class="ml-2 text-sm font-medium text-gray-700">Enable</label>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div>
                            <h4 class="font-medium text-gray-800">Email Notifications</h4>
                            <p class="text-sm text-gray-600">Send email notifications for EDMS workflow updates</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="edms_notifications" id="edms_notifications" class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500" checked>
                            <label for="edms_notifications" class="ml-2 text-sm font-medium text-gray-700">Enable</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6"  style="display: none;">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <i data-lucide="file-text" class="w-5 h-5 text-gray-600 mr-2"></i>
                    Additional Notes for EDMS
                </h3>
                
                <div class="mb-4">
                    <label for="edms_notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Special instructions or notes for document management (Optional)
                    </label>
                    <textarea 
                        id="edms_notes" 
                        name="edms_notes" 
                        rows="4" 
                        class="w-full p-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                        placeholder="Enter any special instructions for document handling, scanning requirements, or other EDMS-related notes..."
                    ></textarea>
                </div>
                
                <div class="text-sm text-gray-600">
                    <p class="mb-2"><strong>Note:</strong> These notes will be visible to EDMS operators and can help ensure proper handling of your documents.</p>
                    <p><strong>Examples:</strong> "Please scan documents in high resolution", "Contains sensitive information", "Documents are in multiple languages", etc.</p>
                </div>
            </div>
        </div>

        <!-- Navigation Buttons -->
        <div class="flex justify-between mt-8">
            <button type="button" class="px-6 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors" id="backStep4">
                <i data-lucide="arrow-left" class="w-4 h-4 inline mr-2"></i>
                Back
            </button>
            <div class="flex items-center">
                <span class="text-sm text-gray-500 mr-4">Step 4 of 5</span>
                <button type="button" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors" id="nextStep4">
                    Next
                    <i data-lucide="arrow-right" class="w-4 h-4 inline ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Update reference information when form fields change
document.addEventListener('DOMContentLoaded', function() {
    // Function to update EDMS reference information
    function updateEdmsReference() {
        // Update unit details
        const unitNumber = document.querySelector('input[name="unit_number"]')?.value || '-';
        const blockNumber = document.querySelector('input[name="block_number"]')?.value || '-';
        const floorNumber = document.querySelector('input[name="floor_number"]')?.value || '-';
        
        document.getElementById('edms-unit-number').textContent = unitNumber;
        document.getElementById('edms-block-number').textContent = blockNumber;
        document.getElementById('edms-floor-number').textContent = floorNumber;
    }
    
    // Listen for changes in unit details
    const unitFields = ['unit_number', 'block_number', 'floor_number'];
    unitFields.forEach(fieldName => {
        const field = document.querySelector(`input[name="${fieldName}"]`);
        if (field) {
            field.addEventListener('input', updateEdmsReference);
            field.addEventListener('change', updateEdmsReference);
        }
    });
    
    // Initial update
    updateEdmsReference();
});
</script>