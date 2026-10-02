<!-- Step 6: Document Uploads -->
<div id="step-content-6" class="step-content hidden">
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="upload" class="h-5 w-5"></i>
                SECTION D: DOCUMENT UPLOADS
            </h3>
        </div>
        <div class="p-4 space-y-6">
            <!-- Title Document Status Section -->
            <div class="bg-blue-50 p-4 rounded-lg">
                <h4 class="font-semibold text-blue-900 mb-3">Title Document Status (Submitted)</h4>
                <p class="text-sm text-blue-800 mb-4">Please check all title documents that have been submitted:</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="right_of_occupancy" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(a) Right of Occupancy</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="certificate_of_occupancy" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(b) Certificate of Occupancy</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="deed_of_assignment" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(c) Deed of Assignment</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="deed_of_sublease" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(d) Deed of Sublease</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="deed_of_mortgage" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(e) Deed of Mortgage</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="deed_of_gift" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(f) Deed of Gift</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="power_of_attorney" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(g) Power of Attorney</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="devolution_order" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(h) Devolution Order</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="letter_of_administration" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(i) Letter of Administration</span>
                    </label>
                    
                    <label class="flex items-center space-x-3 p-2 rounded hover:bg-blue-100 cursor-pointer">
                        <input type="checkbox" name="title_documents[]" value="others" class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                        <span class="text-sm font-medium text-gray-700">(j) Others......</span>
                    </label>
                </div>
                
                <!-- Description field for other documents -->
                <div class="mt-4">
                    <label for="otherDocumentsDescription" class="block text-sm font-medium text-gray-700 mb-1">
                        Description (if Others selected)
                    </label>
                    <input
                        type="text"
                        id="otherDocumentsDescription"
                        name="otherDocumentsDescription"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="Describe the document type"
                    />
                </div>
            </div>

            <!-- Individual Document Uploads -->
            <div id="individual-documents" class="space-y-4">
                <div class="bg-green-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-green-900 mb-2">Document Uploads for Individual</h4>
                    <p class="text-sm text-green-800">Please upload the following documents. All documents should be clear, legible, and in PDF, JPG, or PNG format (Max: 5MB each).</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Original Certificate -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                            <input type="file" id="originalCertificate" name="originalCertificate" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Original Certificate (if available)</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700" data-target="originalCertificate">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Police Report -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                            <input type="file" id="policeReport" name="policeReport" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Police Report (for lost certificates)</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700" data-target="policeReport">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Sworn Affidavit -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                            <input type="file" id="swornAffidavit" name="swornAffidavit" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Sworn Affidavit</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700" data-target="swornAffidavit">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Valid Identification -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                            <input type="file" id="validIdentification" name="validIdentification" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Valid Identification (NIN, Driver's License, etc.)</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700" data-target="validIdentification">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Recent Passport Photographs -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                            <input type="file" id="recentPassportPhotos" name="recentPassportPhotos" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Recent Passport Photographs</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-green-600 text-white hover:bg-green-700" data-target="recentPassportPhotos">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Corporate/Government Document Uploads -->
            <div id="corporate-documents" class="space-y-4 hidden">
                <div class="bg-purple-50 p-4 rounded-lg">
                    <h4 class="font-semibold text-purple-900 mb-2">🧾 Supporting Documents Required</h4>
                    <p class="text-sm text-purple-800 mb-3">To validate the information in this section, applicants must attach:</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Certificate of Incorporation -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-purple-400 transition-colors">
                            <input type="file" id="certificateOfIncorporation" name="certificateOfIncorporation" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Certificate of Incorporation</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-purple-600 text-white hover:bg-purple-700" data-target="certificateOfIncorporation">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- CAC Registration Documents -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-purple-400 transition-colors">
                            <input type="file" id="cacRegistrationDocs" name="cacRegistrationDocs" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">CAC Registration Documents</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-purple-600 text-white hover:bg-purple-700" data-target="cacRegistrationDocs">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Memorandum and Articles of Association -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-purple-400 transition-colors">
                            <input type="file" id="memorandumArticles" name="memorandumArticles" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Memorandum and Articles of Association</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-purple-600 text-white hover:bg-purple-700" data-target="memorandumArticles">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Particulars of Directors -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-purple-400 transition-colors">
                            <input type="file" id="particularsOfDirectors" name="particularsOfDirectors" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Particulars of Directors</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-purple-600 text-white hover:bg-purple-700" data-target="particularsOfDirectors">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>

                    <!-- Registered Deed of Mortgage -->
                    <div class="document-upload-section">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-purple-400 transition-colors">
                            <input type="file" id="registeredDeedOfMortgage" name="registeredDeedOfMortgage" accept=".pdf,.jpg,.jpeg,.png" class="hidden" />
                            <i data-lucide="file-text" class="h-8 w-8 mx-auto mb-2 text-gray-400"></i>
                            <div class="text-sm font-semibold mb-2">Registered Deed of Mortgage (if applicable)</div>
                            <button type="button" class="upload-btn inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-purple-600 text-white hover:bg-purple-700" data-target="registeredDeedOfMortgage">
                                <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                                Choose File
                            </button>
                            <div class="file-info mt-2 text-sm text-gray-600 hidden">
                                <div class="file-name font-medium text-xs"></div>
                                <div class="file-size text-xs text-gray-500"></div>
                            </div>
                            <div class="text-xs text-gray-500 mt-2">PDF, JPG, PNG (Max: 5MB)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-yellow-50 p-4 rounded-lg">
                <h4 class="font-semibold text-yellow-900 mb-2 flex items-center gap-2">
                    <i data-lucide="alert-triangle" class="h-4 w-4"></i>
                    Important Notes
                </h4>
                <ul class="text-sm text-yellow-800 space-y-1">
                    <li>• All documents should be clear, legible, and properly scanned</li>
                    <li>• Accepted formats: PDF, JPG, PNG (Maximum file size: 5MB each)</li>
                    <li>• Original documents may be requested during verification</li>
                    <li>• Ensure all uploaded documents are relevant to your application</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Setup document upload handlers
    setupDocumentUploads();
    
    // Setup applicant type change handler for document sections
    setupDocumentSectionToggle();
});

function setupDocumentSectionToggle() {
    const applicantTypeSelect = document.getElementById('applicantType');
    const individualDocuments = document.getElementById('individual-documents');
    const corporateDocuments = document.getElementById('corporate-documents');
    
    function updateDocumentSections(applicantType) {
        console.log('Updating document sections for:', applicantType);
        
        // Hide all sections first
        if (individualDocuments) {
            individualDocuments.classList.add('hidden');
        }
        if (corporateDocuments) {
            corporateDocuments.classList.add('hidden');
        }
        
        // Show appropriate section based on applicant type
        switch(applicantType) {
            case 'Individual':
            case 'Multiple Owners':
                if (individualDocuments) {
                    individualDocuments.classList.remove('hidden');
                    console.log('Showed individual documents section');
                }
                break;
                
            case 'Corporate':
            case 'Government Body':
                if (corporateDocuments) {
                    corporateDocuments.classList.remove('hidden');
                    console.log('Showed corporate documents section');
                }
                break;
        }
    }
    
    if (applicantTypeSelect) {
        // Set initial state
        updateDocumentSections(applicantTypeSelect.value);
        
        // Add change listener
        applicantTypeSelect.addEventListener('change', function() {
            console.log('Applicant type changed to:', this.value);
            updateDocumentSections(this.value);
        });
        
        console.log('Document section toggle setup complete');
    } else {
        console.error('Applicant type select not found for document sections!');
    }
}

function setupDocumentUploads() {
    console.log('Setting up document upload handlers...');
    
    // Get all upload buttons
    const uploadButtons = document.querySelectorAll('.upload-btn');
    
    uploadButtons.forEach(button => {
        const targetId = button.getAttribute('data-target');
        const fileInput = document.getElementById(targetId);
        const section = button.closest('.document-upload-section');
        
        if (fileInput && section) {
            const fileInfo = section.querySelector('.file-info');
            const fileName = section.querySelector('.file-name');
            const fileSize = section.querySelector('.file-size');
            
            // Button click handler
            button.addEventListener('click', () => {
                fileInput.click();
            });
            
            // File input change handler
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    // Validate file type
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                    if (!allowedTypes.includes(file.type)) {
                        showToast('Please select a PDF, JPG, or PNG file', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Validate file size (5MB max)
                    if (file.size > 5 * 1024 * 1024) {
                        showToast('File size must be less than 5MB', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Show file info
                    if (fileName) {
                        fileName.textContent = file.name;
                    }
                    if (fileSize) {
                        const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                        fileSize.textContent = `${sizeInMB} MB`;
                    }
                    if (fileInfo) {
                        fileInfo.classList.remove('hidden');
                    }
                    
                    // Update button text and style
                    const isIndividualDoc = section.closest('#individual-documents');
                    const isCorporateDoc = section.closest('#corporate-documents');
                    
                    button.innerHTML = `
                        <i data-lucide="check-circle" class="h-4 w-4 mr-1"></i>
                        File Selected
                    `;
                    
                    if (isIndividualDoc) {
                        button.classList.remove('bg-green-600', 'hover:bg-green-700');
                        button.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    } else if (isCorporateDoc) {
                        button.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                        button.classList.add('bg-blue-600', 'hover:bg-blue-700');
                    }
                    
                    // Update border color
                    const uploadArea = section.querySelector('.border-dashed');
                    if (uploadArea) {
                        uploadArea.classList.remove('border-gray-300');
                        if (isIndividualDoc) {
                            uploadArea.classList.add('border-green-400', 'bg-green-50');
                        } else if (isCorporateDoc) {
                            uploadArea.classList.add('border-purple-400', 'bg-purple-50');
                        }
                    }
                    
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                    
                    showToast(`${targetId} uploaded successfully`, 'success');
                } else {
                    // Reset if no file selected
                    if (fileInfo) {
                        fileInfo.classList.add('hidden');
                    }
                    
                    // Reset button text and style
                    const isIndividualDoc = section.closest('#individual-documents');
                    const isCorporateDoc = section.closest('#corporate-documents');
                    
                    button.innerHTML = `
                        <i data-lucide="upload" class="h-4 w-4 mr-1"></i>
                        Choose File
                    `;
                    
                    if (isIndividualDoc) {
                        button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                        button.classList.add('bg-green-600', 'hover:bg-green-700');
                    } else if (isCorporateDoc) {
                        button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                        button.classList.add('bg-purple-600', 'hover:bg-purple-700');
                    }
                    
                    // Reset border color
                    const uploadArea = section.querySelector('.border-dashed');
                    if (uploadArea) {
                        uploadArea.classList.remove('border-green-400', 'bg-green-50', 'border-purple-400', 'bg-purple-50');
                        uploadArea.classList.add('border-gray-300');
                    }
                    
                    // Re-initialize Lucide icons
                    if (typeof lucide !== 'undefined') {
                        lucide.createIcons();
                    }
                }
            });
        }
    });
    
    console.log('Document upload handlers setup complete');
}

// Make showToast available if not already defined
if (typeof showToast === 'undefined') {
    function showToast(message, type = 'info') {
        console.log(`Toast: ${message} (${type})`);
    }
}
</script>