<!-- Document Upload Enhancement Styles -->
<link rel="stylesheet" href="{{ asset('css/document-upload-enhancement.css') }}">

<div class="form-section" id="step3">
    <div class="p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-center text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
            <button id="closeModal2" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="mb-6">
               <div class="flex items-center mb-2">
                   <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
                   <h3 class="text-lg font-bold">Application for Sectional Titling - Main Application</h3>
                   <div class="ml-auto flex items-center">
                       <span class="text-gray-600 mr-2">Land Use:</span>
                       <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
                           @if (request()->query('landuse') === 'Commercial')
                               Commercial
                           @elseif (request()->query('landuse') === 'Residential')
                               Residential
                           @elseif (request()->query('landuse') === 'Industrial')
                               Industrial
                           @else
                               Mixed Use
                           @endif
                       </span>
                   </div>
               </div>
               <p class="text-gray-600">Complete the form below to submit a new primary application for sectional
                   titling</p>
           </div>

        <div class="flex items-center mb-8">
            <div class="flex items-center mr-4">
                <div class="step-circle inactive cursor-pointer" onclick="goToStep(1)">1</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive cursor-pointer" onclick="goToStep(2)">2</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle active cursor-pointer" onclick="goToStep(3)">3</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive cursor-pointer" onclick="goToStep(4)">4</div>
            </div>
            <div class="flex items-center mr-4">
                <div class="step-circle inactive cursor-pointer" onclick="goToStep(5)">5</div>
            </div>
            <div class="ml-4 step-status-text" data-step-indicator data-step-label="Documents">Step 3 - Documents</div>
        </div>
           <div class="mb-6">
               <!-- Document Upload Section - Only Accompanying Documents -->
               <div class="mb-6">
                   <!-- Hidden tab structure for consistency, but only show accompanying docs -->
               </div>

               <!-- Scan Upload Section - HIDDEN -->
               <div id="scan-upload-section" class="tab-content hidden">
                   <!-- Section hidden as requested -->
               </div>

               <!-- Accompanying Documents Section - Now the main visible section -->
               <div id="accompanying-docs-section" class="tab-content">
                   <div class="bg-green-50 border border-green-200 rounded-lg p-6 mb-6">
                       <div class="flex items-start">
                           <i data-lucide="file-check" class="w-6 h-6 mr-3 text-green-600 mt-1"></i>
                           <div>
                               <h4 class="font-semibold text-green-900 mb-2">Accompanying Submission Documents</h4>
                               <p class="text-sm text-green-700 mb-3">Upload supporting documents for application processing. These documents are optional and can be submitted later.</p>
                               <div class="text-xs text-green-600">
                                   <span class="font-medium">Required formats:</span> PDF, JPG, PNG • 
                                   <span class="font-medium">Max size:</span> 5MB per document
                               </div>
                           </div>
                       </div>
                   </div>

                   <!-- Duplicate File Detection Info -->
                   <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                       <div class="flex items-start">
                           <i data-lucide="shield-check" class="w-5 h-5 mr-3 text-blue-600 mt-0.5"></i>
                           <div>
                               <h5 class="font-medium text-blue-900 mb-1">Duplicate File Protection</h5>
                               <p class="text-xs text-blue-700">The system automatically detects and prevents uploading duplicate files (same name and size). Each document must be unique.</p>
                           </div>
                       </div>
                   </div>

                   <!-- Required Documents Grid -->
                   <div class="grid grid-cols-2 gap-6 mb-6">
                       <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                           <div class="flex items-center mb-3">
                               <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                   <i data-lucide="file-text" class="w-4 h-4 text-red-600"></i>
                               </div>
                               <div>
                                   <h4 class="font-medium">Application Letter</h4>
                                   <p class="text-xs text-gray-500">Optional</p>
                               </div>
                           </div>
                           <p class="text-sm text-gray-600 mb-4">Formal letter requesting sectional titling</p>

                           <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                               <div class="flex justify-center mb-2">
                                   <i data-lucide="upload" class="w-5 h-5 text-gray-400"></i>
                               </div>
                               <div class="flex justify-center">
                                   <input type="file" name="application_letter" id="application_letter"
                                       accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="updateFileName(this, 'application_letter_label')">
                                   <label for="application_letter" id="application_letter_label"
                                       class="flex items-center text-green-600 cursor-pointer hover:text-green-700">
                                       <span>Upload Document</span>
                                   </label>
                               </div>
                               <p class="text-xs text-gray-500 mt-2" id="application_letter_name">PDF, JPG or PNG (max. 5MB)</p>
                           </div>
                       </div>

                       <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                           <div class="flex items-center mb-3">
                               <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                   <i data-lucide="building" class="w-4 h-4 text-red-600"></i>
                               </div>
                               <div>
                                   <h4 class="font-medium">Building Plan</h4>
                                   <p class="text-xs text-gray-500">Optional</p>
                               </div>
                           </div>
                           <p class="text-sm text-gray-600 mb-4">Approved building plan with architectural details</p>

                           <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                               <div class="flex justify-center mb-2">
                                   <i data-lucide="upload" class="w-5 h-5 text-gray-400"></i>
                               </div>
                               <div class="flex justify-center">
                                   <input type="file" name="building_plan" id="building_plan"
                                       accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="updateFileName(this, 'building_plan_label')">
                                   <label for="building_plan" id="building_plan_label"
                                       class="flex items-center text-green-600 cursor-pointer hover:text-green-700">
                                       <span>Upload Document</span>
                                   </label>
                               </div>
                               <p class="text-xs text-gray-500 mt-2" id="building_plan_name">PDF, JPG or PNG (max. 5MB)</p>
                           </div>
                       </div>
                   </div>

                   <div class="grid grid-cols-2 gap-6 mb-6">
                       <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                           <div class="flex items-center mb-3">
                               <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                   <i data-lucide="drafting-compass" class="w-4 h-4 text-blue-600"></i>
                               </div>
                               <div>
                                   <h4 class="font-medium">Architectural Design</h4>
                                   <p class="text-xs text-gray-500">Optional</p>
                               </div>
                           </div>
                           <p class="text-sm text-gray-600 mb-4">Detailed architectural design of the property</p>

                           <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                               <div class="flex justify-center mb-2">
                                   <i data-lucide="upload" class="w-5 h-5 text-gray-400"></i>
                               </div>
                               <div class="flex justify-center">
                                   <input type="file" name="architectural_design" id="architectural_design"
                                       accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="updateFileName(this, 'architectural_design_label')">
                                   <label for="architectural_design" id="architectural_design_label"
                                       class="flex items-center text-green-600 cursor-pointer hover:text-green-700">
                                       <span>Upload Document</span>
                                   </label>
                               </div>
                               <p class="text-xs text-gray-500 mt-2" id="architectural_design_name">PDF, JPG or PNG (max. 5MB)</p>
                           </div>
                       </div>

                       <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                           <div class="flex items-center mb-3">
                               <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                   <i data-lucide="file-check" class="w-4 h-4 text-red-600"></i>
                               </div>
                               <div>
                                   <h4 class="font-medium">Ownership Document</h4>
                                   <p class="text-xs text-gray-500">Optional</p>
                               </div>
                           </div>
                           <p class="text-sm text-gray-600 mb-4">Proof of ownership (CofO, deed, etc.)</p>

                           <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                               <div class="flex justify-center mb-2">
                                   <i data-lucide="upload" class="w-5 h-5 text-gray-400"></i>
                               </div>
                               <div class="flex justify-center">
                                   <input type="file" name="ownership_document" id="ownership_document"
                                       accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="updateFileName(this, 'ownership_document_label')">
                                   <label for="ownership_document" id="ownership_document_label"
                                       class="flex items-center text-green-600 cursor-pointer hover:text-green-700">
                                       <span>Upload Document</span>
                                   </label>
                               </div>
                               <p class="text-xs text-gray-500 mt-2" id="ownership_document_name">PDF, JPG or PNG (max. 5MB)</p>
                           </div>
                       </div>
                   </div>

                   <div class="grid grid-cols-1 gap-6 mb-6">
                       <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                           <div class="flex items-center mb-3">
                               <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                   <i data-lucide="map" class="w-4 h-4 text-red-600"></i>
                               </div>
                               <div>
                                   <h4 class="font-medium">Site Plan (Survey)</h4>
                                   <p class="text-xs text-gray-500">Optional</p>
                               </div>
                           </div>
                           <p class="text-sm text-gray-600 mb-4">Approved survey plan showing property boundaries and measurements</p>

                           <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center hover:border-green-400 transition-colors">
                               <div class="flex justify-center mb-2">
                                   <i data-lucide="upload" class="w-5 h-5 text-gray-400"></i>
                               </div>
                               <div class="flex justify-center">
                                   <input type="file" name="survey_plan" id="survey_plan"
                                       accept=".pdf,.jpg,.jpeg,.png" class="hidden"
                                       onchange="updateFileName(this, 'survey_plan_label')">
                                   <label for="survey_plan" id="survey_plan_label"
                                       class="flex items-center text-green-600 cursor-pointer hover:text-green-700">
                                       <span>Upload Document</span>
                                   </label>
                               </div>
                               <p class="text-xs text-gray-500 mt-2" id="survey_plan_name">PDF, JPG or PNG (max. 5MB)</p>
                           </div>
                       </div>
                   </div>
               </div>

               <div class="flex justify-between mt-8">
                   <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-md"
                       id="backStep3">Back</button>
                   <div class="flex items-center">
                       <span class="text-sm text-gray-500 mr-4 step-status-text" data-step-indicator data-step-total="5">Step 3 of 5</span>
                       <button type="button" class="px-4 py-2 bg-black text-white rounded-md"
                           id="nextStep3">Next</button>
                   </div>
               </div>

           </div>
       </div>
    </div>

   <script>
       // Primary Variables for Scan Upload
    window.scanUploadedFiles = window.scanUploadedFiles || [];
       let scanUploadProgress = 0;

       // Duplicate file tracking
       let uploadedFiles = [];

       function checkDuplicateFile(file) {
           for (let uploadedFile of uploadedFiles) {
               if (uploadedFile.name === file.name && uploadedFile.size === file.size) {
                   return true;
               }
           }
           return false;
       }

       function addToUploadedFiles(file) {
           uploadedFiles.push({
               name: file.name,
               size: file.size,
               lastModified: file.lastModified
           });
       }

       function removeFromUploadedFiles(fileName, fileSize) {
           uploadedFiles = uploadedFiles.filter(file => 
               !(file.name === fileName && file.size === fileSize)
           );
       }

       function showDuplicateAlert(fileName) {
           Swal.fire({
               icon: 'warning',
               title: 'Duplicate File Detected',
               html: `<div style="text-align: left;">
                   <p><strong>File:</strong> ${fileName}</p>
                   <p style="margin-top: 10px;">This file has already been uploaded. Please choose a different file or rename the existing file to upload a new version.</p>
               </div>`,
               confirmButtonText: 'OK',
               confirmButtonColor: '#f39c12'
           });
       }

       function updateFileName(input, labelId) {
           const file = input.files[0];
           if (file) {
               // Check for duplicate file
               if (checkDuplicateFile(file)) {
                   showDuplicateAlert(file.name);
                   // Clear the input
                   input.value = '';
                   return;
               }

               // Remove any previously uploaded file for this input
               const previousFile = input.dataset.previousFile;
               const previousSize = input.dataset.previousSize;
               if (previousFile && previousSize) {
                   removeFromUploadedFiles(previousFile, parseInt(previousSize));
               }

               // Add new file to tracking
               addToUploadedFiles(file);
               
               // Store current file info for future reference
               input.dataset.previousFile = file.name;
               input.dataset.previousSize = file.size;

               const nameElement = document.getElementById(input.id + '_name');
               const labelElement = document.getElementById(labelId);
               
               if (nameElement) {
                   nameElement.textContent = file.name + ` (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
               }
               if (labelElement) {
                   labelElement.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 mr-1 inline-block"></i><span>Change Document</span>';
                   labelElement.classList.add('text-green-600');
               }

               // Show success message for successful upload
               Swal.fire({
                   icon: 'success',
                   title: 'File Uploaded',
                   text: `${file.name} has been uploaded successfully.`,
                   timer: 2000,
                   showConfirmButton: false,
                   toast: true,
                   position: 'top-end'
               });

               // Trigger the summary update whenever a document is uploaded
               if (typeof updateApplicationSummary === 'function') {
                   updateApplicationSummary();
               }
           }
       }

       function switchDocumentTab(tabName) {
           // Always show only the accompanying documents section
           // Scan upload section is hidden
           document.getElementById('scan-upload-section').classList.add('hidden');
           document.getElementById('accompanying-docs-section').classList.remove('hidden');
       }

       // Drag and Drop Functionality for Scan Upload
       function initializeScanUpload() {
           const dropZone = document.getElementById('scan-drop-zone');
           const fileInput = document.getElementById('scan-file-input');

           if (!dropZone || !fileInput) return;

           // Prevent default drag behaviors
           ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
               dropZone.addEventListener(eventName, preventDefaults, false);
               document.body.addEventListener(eventName, preventDefaults, false);
           });

           // Highlight drop zone when item is dragged over it
           ['dragenter', 'dragover'].forEach(eventName => {
               dropZone.addEventListener(eventName, highlight, false);
           });

           ['dragleave', 'drop'].forEach(eventName => {
               dropZone.addEventListener(eventName, unhighlight, false);
           });

           // Handle dropped files
           dropZone.addEventListener('drop', handleScanDrop, false);
           dropZone.addEventListener('click', () => fileInput.click());
           fileInput.addEventListener('change', handleScanFileSelect);

           function preventDefaults(e) {
               e.preventDefault();
               e.stopPropagation();
           }

           function highlight(e) {
               dropZone.classList.add('border-blue-500', 'bg-blue-100');
           }

           function unhighlight(e) {
               dropZone.classList.remove('border-blue-500', 'bg-blue-100');
           }

           function handleScanDrop(e) {
               const dt = e.dataTransfer;
               const files = dt.files;
               handleScanFiles(files);
           }

           function handleScanFileSelect(e) {
               handleScanFiles(e.target.files);
           }
       }

       function handleScanFiles(files) {
           const maxFiles = 50;
           const maxFileSize = 10 * 1024 * 1024; // 10MB
           const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png', 'image/tiff'];

           if (window.scanUploadedFiles.length + files.length > maxFiles) {
               alert(`Maximum ${maxFiles} files allowed. You can upload ${maxFiles - window.scanUploadedFiles.length} more files.`);
               return;
           }

           let validFiles = [];
           let errors = [];

           Array.from(files).forEach(file => {
               if (!allowedTypes.includes(file.type)) {
                   errors.push(`${file.name}: Unsupported file type`);
                   return;
               }
               if (file.size > maxFileSize) {
                   errors.push(`${file.name}: File too large (max 10MB)`);
                   return;
               }
               validFiles.push(file);
           });

           if (errors.length > 0) {
               alert('Some files were not added:\n' + errors.join('\n'));
           }

           if (validFiles.length > 0) {
           processScanFiles(validFiles);
           }
       }

       function processScanFiles(files) {
           const progressSection = document.getElementById('scan-progress-section');
           const previewSection = document.getElementById('scan-files-preview');
           
           progressSection.classList.remove('hidden');
           
           // Simulate file processing with progress
           let processed = 0;
           const totalFiles = files.length;

           files.forEach((file, index) => {
               setTimeout(() => {
                   window.scanUploadedFiles.push(file);
                   addScanFilePreview(file, window.scanUploadedFiles.length - 1);
                   processed++;
                   
                   const progress = (processed / totalFiles) * 100;
                   updateScanProgress(progress);
                   
                   if (processed === totalFiles) {
                       setTimeout(() => {
                           progressSection.classList.add('hidden');
                           previewSection.classList.remove('hidden');
                           updateScanFileCount();
                       }, 500);
                   }
               }, index * 200); // Staggered processing for visual effect
           });
       }

       function addScanFilePreview(file, index) {
           const grid = document.getElementById('scan-files-grid');
           const fileDiv = document.createElement('div');
           fileDiv.className = 'scan-file-item bg-white border border-gray-200 rounded-lg p-3 hover:shadow-md transition-shadow';
           fileDiv.dataset.index = index;

           const isImage = file.type.startsWith('image/');
           const fileSize = (file.size / 1024 / 1024).toFixed(2);

           fileDiv.innerHTML = `
               <div class="flex items-center justify-between mb-2">
                   <div class="flex items-center">
                       <i data-lucide="${isImage ? 'image' : 'file-text'}" class="w-4 h-4 text-blue-600 mr-2"></i>
                       <span class="text-xs font-medium text-gray-900 truncate" title="${file.name}">${file.name.length > 15 ? file.name.substring(0, 15) + '...' : file.name}</span>
                   </div>
                   <button type="button" onclick="removeScanFile(${index})" class="text-red-500 hover:text-red-700" title="Remove file">
                       <i data-lucide="x" class="w-3 h-3"></i>
                   </button>
               </div>
               <div class="text-xs text-gray-500 mb-2">${fileSize} MB</div>
               ${isImage ? `
               <div class="scan-file-thumbnail bg-gray-100 rounded border h-16 flex items-center justify-center cursor-pointer" onclick="previewScanFile(${index})">
                   <span class="text-xs text-gray-500">Click to preview</span>
               </div>
               ` : `
               <div class="scan-file-icon bg-blue-50 rounded border h-16 flex items-center justify-center cursor-pointer" onclick="previewScanFile(${index})">
                   <i data-lucide="file-text" class="w-8 h-8 text-blue-600"></i>
               </div>
               `}
           `;

           grid.appendChild(fileDiv);

           // Generate thumbnail for images
           if (isImage) {
               const reader = new FileReader();
               reader.onload = function(e) {
                   const thumbnail = fileDiv.querySelector('.scan-file-thumbnail');
                   thumbnail.style.backgroundImage = `url(${e.target.result})`;
                   thumbnail.style.backgroundSize = 'cover';
                   thumbnail.style.backgroundPosition = 'center';
                   thumbnail.innerHTML = '';
               };
               reader.readAsDataURL(file);
           }

           // Re-initialize lucide icons
           if (typeof lucide !== 'undefined') {
               lucide.createIcons();
           }
       }

       function removeScanFile(index) {
           window.scanUploadedFiles.splice(index, 1);
           refreshScanFileGrid();
           updateScanFileCount();
       }

       function refreshScanFileGrid() {
           const grid = document.getElementById('scan-files-grid');
           grid.innerHTML = '';
           window.scanUploadedFiles.forEach((file, index) => {
               addScanFilePreview(file, index);
           });
       }

       function updateScanProgress(percentage) {
           const progressBar = document.getElementById('scan-progress-bar');
           const progressText = document.getElementById('scan-progress-text');
           progressBar.style.width = percentage + '%';
           progressText.textContent = Math.round(percentage) + '% Complete';
       }

       function updateScanFileCount() {
           const countElement = document.getElementById('scan-file-count');
           if (countElement) {
               countElement.textContent = window.scanUploadedFiles.length;
           }
           
           const previewSection = document.getElementById('scan-files-preview');
           if (window.scanUploadedFiles.length === 0) {
               previewSection.classList.add('hidden');
           }
       }

       function previewScanFile(index) {
           const file = window.scanUploadedFiles[index];
           if (!file) return;

           // Create modal for file preview
           const modal = document.createElement('div');
           modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4';
           modal.onclick = function(e) {
               if (e.target === modal) {
                   document.body.removeChild(modal);
               }
           };

           const isImage = file.type.startsWith('image/');
           const content = isImage ? `
               <img src="${URL.createObjectURL(file)}" alt="${file.name}" class="max-w-full max-h-full rounded-lg">
           ` : `
               <div class="bg-white rounded-lg p-8 text-center">
                   <i data-lucide="file-text" class="w-16 h-16 text-blue-600 mx-auto mb-4"></i>
                   <h3 class="text-lg font-medium text-gray-900 mb-2">${file.name}</h3>
                   <p class="text-gray-600">File preview not available for this type</p>
               </div>
           `;

           modal.innerHTML = `
               <div class="relative max-w-4xl max-h-full">
                   ${content}
                   <button type="button" class="absolute top-4 right-4 text-white bg-black bg-opacity-50 rounded-full p-2" onclick="document.body.removeChild(this.closest('.fixed'))">
                       <i data-lucide="x" class="w-6 h-6"></i>
                   </button>
               </div>
           `;

           document.body.appendChild(modal);
           
           // Re-initialize lucide icons
           if (typeof lucide !== 'undefined') {
               lucide.createIcons();
           }
       }

       // Validate no duplicate files before proceeding
       function validateNoDuplicateFiles() {
           const fileInputs = [
               'application_letter',
               'building_plan', 
               'architectural_design',
               'ownership_document',
               'survey_plan'
           ];

           const currentFiles = [];
           const duplicates = [];

           // Check each input for files
           for (let inputId of fileInputs) {
               const input = document.getElementById(inputId);
               if (input && input.files && input.files[0]) {
                   const file = input.files[0];
                   
                   // Check if this file matches any already processed
                   const existing = currentFiles.find(f => 
                       f.name === file.name && f.size === file.size
                   );
                   
                   if (existing) {
                       duplicates.push({
                           name: file.name,
                           inputs: [existing.inputId, inputId]
                       });
                   } else {
                       currentFiles.push({
                           name: file.name,
                           size: file.size,
                           inputId: inputId
                       });
                   }
               }
           }

           if (duplicates.length > 0) {
               let duplicateMessage = '<div style="text-align: left;"><strong>Duplicate files detected:</strong><br><br>';
               duplicates.forEach(dup => {
                   duplicateMessage += `• <strong>${dup.name}</strong> appears in multiple upload fields<br>`;
               });
               duplicateMessage += '<br>Please ensure each document is unique before proceeding.</div>';

               Swal.fire({
                   icon: 'error',
                   title: 'Duplicate Files Found',
                   html: duplicateMessage,
                   confirmButtonText: 'OK',
                   confirmButtonColor: '#dc3545'
               });
               return false;
           }

           return true;
       }

       // Add duplicate validation to next button click
       document.addEventListener('DOMContentLoaded', function() {
           // Show only accompanying documents section (scan upload section is hidden)
           switchDocumentTab('accompanying-docs');

           // Add event listener to next button for duplicate validation
           const nextButton = document.getElementById('nextStep3');
           if (nextButton) {
               nextButton.addEventListener('click', function(e) {
                   if (!validateNoDuplicateFiles()) {
                       e.preventDefault();
                       e.stopPropagation();
                       return false;
                   }
               });
           }
       });
   </script>