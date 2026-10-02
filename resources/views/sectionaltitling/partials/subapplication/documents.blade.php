<div class="form-section" id="step3">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
            <button class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="mb-6">
            <div class="flex items-center mb-2">
                <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
                @if($isSUA ?? false)
                    <h3 class="text-lg font-bold">Standalone Unit Application (SUA)</h3>
                @else
                    <h3 class="text-lg font-bold">Application for Sectional Titling - Unit Application (Secondary)</h3>
                @endif
            </div>
            <p class="text-gray-600">
                @if($isSUA ?? false)
                    Upload the required documents for the standalone unit application
                @else
                    Upload the required documents for the sectional titling unit application
                @endif
            </p>
        </div>

        <div class="flex items-center mb-6">
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab cursor-pointer" onclick="goToStep(1)">1</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab cursor-pointer" onclick="goToStep(2)">2</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle active-tab cursor-pointer" onclick="goToStep(3)">3</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive-tab cursor-pointer" onclick="goToStep(4)">4</div>
            </div>
            <div class="ml-4">Step 3</div>
        </div>

        <div class="mb-6">
            <div class="flex items-start mb-4">
                <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
                <span class="font-medium">Required Documents</span>
            </div>
            
            <div class="bg-blue-50 border border-blue-100 rounded-md p-4 mb-6">
                <div class="flex items-start">
                    <i data-lucide="info" class="w-5 h-5 mr-2 text-blue-500 mt-0.5"></i>
                    <div>
                        <h4 class="font-medium text-blue-800">Document Requirements</h4>
                        <p class="text-sm text-blue-600">Please upload all required documents. Acceptable formats are PDF, JPG, and PNG. Maximum file size is 5MB per document.</p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div class="border border-gray-200 rounded-md p-4">
                    <h4 class="font-medium mb-2">Application Letter</h4>
                    <p class="text-sm text-gray-600 mb-4">Formal letter requesting sectional titling</p>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <div class="flex justify-center mb-2">
                            <i data-lucide="upload" class="w-6 h-6 text-gray-400"></i>
                        </div>
                        <div class="flex justify-center">
                            <input type="file" name="application_letter" id="application_letter" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                            <label for="application_letter" id="application_letter_label" class="flex items-center text-blue-600 cursor-pointer">
                                <span>Upload Document</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2" id="application_letter_name">PDF, JPG or PNG (max. 5MB)</p>
                        <button type="button" onclick="removeFile('application_letter')" id="application_letter_remove" class="text-red-600 text-xs mt-2 hidden hover:text-red-800">
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Remove File
                        </button>
                    </div>
                </div>
                
                <div class="border border-gray-200 rounded-md p-4">
                    <h4 class="font-medium mb-2">Building Plan</h4>
                    <p class="text-sm text-gray-600 mb-4">Approved building plan with architectural details</p>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <div class="flex justify-center mb-2">
                            <i data-lucide="upload" class="w-6 h-6 text-gray-400"></i>
                        </div>
                        <div class="flex justify-center">
                            <input type="file" name="building_plan" id="building_plan" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                            <label for="building_plan" id="building_plan_label" class="flex items-center text-blue-600 cursor-pointer">
                                <span>Upload Document</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2" id="building_plan_name">PDF, JPG or PNG (max. 5MB)</p>
                        <button type="button" onclick="removeFile('building_plan')" id="building_plan_remove" class="text-red-600 text-xs mt-2 hidden hover:text-red-800">
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Remove File
                        </button>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-md p-4">
                    <h4 class="font-medium mb-2">Architectural Design</h4>
                    <p class="text-sm text-gray-600 mb-4">Detailed architectural design of the property</p>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <div class="flex justify-center mb-2">
                            <i data-lucide="upload" class="w-6 h-6 text-gray-400"></i>
                        </div>
                        <div class="flex justify-center">
                            <input type="file" name="architectural_design" id="architectural_design" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                            <label for="architectural_design" id="architectural_design_label" class="flex items-center text-blue-600 cursor-pointer">
                                <span>Upload Document</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2" id="architectural_design_name">PDF, JPG or PNG (max. 5MB)</p>
                        <button type="button" onclick="removeFile('architectural_design')" id="architectural_design_remove" class="text-red-600 text-xs mt-2 hidden hover:text-red-800">
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Remove File
                        </button>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-md p-4">
                    <h4 class="font-medium mb-2">Survey Plan</h4>
                    <p class="text-sm text-gray-600 mb-4">Official survey plan of the property</p>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <div class="flex justify-center mb-2">
                            <i data-lucide="upload" class="w-6 h-6 text-gray-400"></i>
                        </div>
                        <div class="flex justify-center">
                            <input type="file" name="survey_plan" id="survey_plan" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                            <label for="survey_plan" id="survey_plan_label" class="flex items-center text-blue-600 cursor-pointer">
                                <span>Upload Document</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2" id="survey_plan_name">PDF, JPG or PNG (max. 5MB)</p>
                        <button type="button" onclick="removeFile('survey_plan')" id="survey_plan_remove" class="text-red-600 text-xs mt-2 hidden hover:text-red-800">
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Remove File
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 gap-6 mb-6">
                <div class="border border-gray-200 rounded-md p-4">
                    <h4 class="font-medium mb-2">Ownership Document</h4>
                    <p class="text-sm text-gray-600 mb-4">Proof of ownership (CofO, deed, etc.)</p>
                    
                    <div class="border-2 border-dashed border-gray-300 rounded-md p-6 text-center">
                        <div class="flex justify-center mb-2">
                            <i data-lucide="upload" class="w-6 h-6 text-gray-400"></i>
                        </div>
                        <div class="flex justify-center">
                            <input type="file" name="ownership_document" id="ownership_document" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                            <label for="ownership_document" id="ownership_document_label" class="flex items-center text-blue-600 cursor-pointer">
                                <span>Upload Document</span>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2" id="ownership_document_name">PDF, JPG or PNG (max. 5MB)</p>
                        <button type="button" onclick="removeFile('ownership_document')" id="ownership_document_remove" class="text-red-600 text-xs mt-2 hidden hover:text-red-800">
                            <i data-lucide="x-circle" class="w-4 h-4 inline mr-1"></i>Remove File
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex justify-between mt-8">
            <button class="px-4 py-2 bg-white border border-gray-300 rounded-md" id="backStep3">Back</button>
            <div class="flex items-center">
                <span class="text-sm text-gray-500 mr-4">Step 3 of 4</span>
                <button class="px-4 py-2 bg-black text-white rounded-md" id="nextStep3">Next</button>
            </div>
        </div>
    </div>
</div>
  <script>
    // Store uploaded files metadata to prevent duplicates
    let uploadedFilesMetadata = new Map();

    function updateFileName(input, labelId) {
        const file = input.files[0];
        if (!file) return;

        // Create file metadata signature
        const fileSignature = `${file.name}_${file.size}_${file.lastModified}`;
        
        // Check if this exact file has already been uploaded
        if (uploadedFilesMetadata.has(fileSignature)) {
            const existingField = uploadedFilesMetadata.get(fileSignature);
            
            // Show error message
            Swal.fire({
                icon: 'error',
                title: 'Duplicate File Detected',
                html: `<div style="text-align: left;">
                    <p><strong>File:</strong> ${file.name}</p>
                    <p><strong>Already uploaded as:</strong> ${existingField}</p>
                    <p class="text-sm text-gray-600 mt-2">Please select a different file or remove the duplicate from the other field.</p>
                </div>`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626',
                customClass: {
                    popup: 'swal-validation-popup',
                    title: 'swal-validation-title',
                    htmlContainer: 'swal-validation-content'
                }
            });
            
            // Clear the duplicate file selection
            input.value = '';
            return;
        }

        // Check if this input field already has a file and remove its signature
        const currentFieldName = getFieldDisplayName(input.id);
        const existingSignature = Array.from(uploadedFilesMetadata.entries())
            .find(([signature, fieldName]) => fieldName === currentFieldName)?.[0];
        
        if (existingSignature) {
            uploadedFilesMetadata.delete(existingSignature);
        }

        // Add new file signature
        uploadedFilesMetadata.set(fileSignature, currentFieldName);
        
        // Update UI
        const fileName = file.name;
        document.getElementById(input.id + '_name').textContent = fileName;
        document.getElementById(labelId).innerHTML = '<span>Change Document</span>';
        
        // Show remove button
        const removeButton = document.getElementById(input.id + '_remove');
        if (removeButton) {
            removeButton.classList.remove('hidden');
        }
        
        // Add success indicator
        const uploadArea = input.closest('.border-dashed');
        if (uploadArea) {
            uploadArea.classList.remove('border-gray-300');
            uploadArea.classList.add('border-green-300', 'bg-green-50');
            
            // Add success icon
            const uploadIcon = uploadArea.querySelector('[data-lucide="upload"]');
            if (uploadIcon) {
                uploadIcon.setAttribute('data-lucide', 'check-circle');
                uploadIcon.classList.remove('text-gray-400');
                uploadIcon.classList.add('text-green-500');
                // Re-initialize Lucide icons for the updated icon
                if (window.lucide) {
                    lucide.createIcons();
                }
            }
        }
        
        // Trigger the summary update whenever a document is uploaded
        if (typeof updateApplicationSummary === 'function') {
            updateApplicationSummary();
        }
    }

    function getFieldDisplayName(fieldId) {
        const fieldNames = {
            'application_letter': 'Application Letter',
            'building_plan': 'Building Plan',
            'architectural_design': 'Architectural Design',
            'survey_plan': 'Survey Plan',
            'ownership_document': 'Ownership Document'
        };
        return fieldNames[fieldId] || fieldId;
    }

    // Function to remove file and clean up metadata
    function removeFile(inputId) {
        const input = document.getElementById(inputId);
        if (!input || !input.files[0]) return;

        const file = input.files[0];
        const fileSignature = `${file.name}_${file.size}_${file.lastModified}`;
        
        // Remove from metadata store
        uploadedFilesMetadata.delete(fileSignature);
        
        // Clear input
        input.value = '';
        
        // Reset UI
        const labelId = inputId + '_label';
        const nameId = inputId + '_name';
        const removeButtonId = inputId + '_remove';
        
        document.getElementById(labelId).innerHTML = '<span>Upload Document</span>';
        document.getElementById(nameId).textContent = 'PDF, JPG or PNG (max. 5MB)';
        
        // Hide remove button
        const removeButton = document.getElementById(removeButtonId);
        if (removeButton) {
            removeButton.classList.add('hidden');
        }
        
        // Reset upload area styling
        const uploadArea = input.closest('.border-dashed');
        if (uploadArea) {
            uploadArea.classList.remove('border-green-300', 'bg-green-50');
            uploadArea.classList.add('border-gray-300');
            
            // Reset icon
            const uploadIcon = uploadArea.querySelector('[data-lucide="check-circle"]');
            if (uploadIcon) {
                uploadIcon.setAttribute('data-lucide', 'upload');
                uploadIcon.classList.remove('text-green-500');
                uploadIcon.classList.add('text-gray-400');
                // Re-initialize Lucide icons for the updated icon
                if (window.lucide) {
                    lucide.createIcons();
                }
            }
        }
        
        // Update summary
        if (typeof updateApplicationSummary === 'function') {
            updateApplicationSummary();
        }
    }

    // Enhanced file validation function
    function validateFileUpload(input) {
        const file = input.files[0];
        if (!file) return true;

        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        
        // Check file size
        if (file.size > maxSize) {
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: `File "${file.name}" is too large. Maximum file size is 5MB.`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626'
            });
            input.value = '';
            return false;
        }
        
        // Check file type
        if (!allowedTypes.includes(file.type)) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid File Type',
                text: `File "${file.name}" is not a supported format. Please upload PDF, JPG, or PNG files only.`,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626'
            });
            input.value = '';
            return false;
        }
        
        return true;
    }

    // Add event listeners to all file inputs for validation
    document.addEventListener('DOMContentLoaded', function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (validateFileUpload(this)) {
                    // Only proceed with updateFileName if validation passes
                    const labelId = this.id + '_label';
                    updateFileName(this, labelId);
                }
            });
        });
    });
    </script>