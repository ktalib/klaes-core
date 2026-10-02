<div  class="mb-6">
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="p-6">
                <!-- Applicant Type Section -->
               
                <input type="hidden" id="applicantType" name="applicantType">

                <!-- Personal Information Section -->
                <div class="mb-10" id="individualFields" style="display: none;">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Personal Information</h2>
                    <div class="bg-gray-50 p-6 rounded-lg shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <!-- Left side - Personal Details -->
                            <div class="md:col-span-3">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <!-- Title -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Title
                                        </label>
                                        <select id="applicantTitleSelect"
                                            class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                            onchange="(function(sel){
                                                const isOther = sel.value==='Other';
                                                const otherWrap = document.getElementById('applicantTitleOtherWrapper');
                                                const otherInput = document.getElementById('applicantTitleOther');
                                                const hidden = document.getElementById('applicantTitle'); // holds submitted value
                                                otherWrap.classList.toggle('hidden', !isOther);
                                                hidden.value = isOther ? otherInput.value : sel.value;
                                                updateApplicantNamePreview();
                                            })(this)">
                                            <option value="" disabled selected>Select title</option>
                                            @php
                                                // Try to get titles from database, fallback to hardcoded array
                                                try {
                                                    $titlesFromDb = \App\Models\Title::getFormOptions();
                                                    $titles = !empty($titlesFromDb) ? $titlesFromDb : [
                                                        'Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Chief' => 'Chief', 'Master' => 'Master', 
                                                        'Capt' => 'Capt.', 'Coln' => 'Col.', 'HRH' => 'HRH', 'Mallam' => 'Mallam',
                                                        'Prof' => 'Prof.', 'Dr.' => 'Dr.', 'Alhaji' => 'Alhaji', 'Hajia' => 'Hajia', 
                                                        'High Chief' => 'High Chief', 'Senator' => 'Senator', 'Messr' => 'Messr.',
                                                        'Honorable' => 'Honorable', 'Miss' => 'Miss', 'Barr.' => 'Barr.', 'Arc.' => 'Arc.', 'Other' => 'Other'
                                                    ];
                                                } catch (\Exception $e) {
                                                    // Fallback to hardcoded titles if database query fails
                                                    $titles = [
                                                        'Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Chief' => 'Chief', 'Master' => 'Master', 
                                                        'Capt' => 'Capt.', 'Coln' => 'Col.', 'HRH' => 'HRH', 'Mallam' => 'Mallam',
                                                        'Prof' => 'Prof.', 'Dr.' => 'Dr.', 'Alhaji' => 'Alhaji', 'Hajia' => 'Hajia', 
                                                        'High Chief' => 'High Chief', 'Senator' => 'Senator', 'Messr' => 'Messr.',
                                                        'Honorable' => 'Honorable', 'Miss' => 'Miss', 'Barr.' => 'Barr.', 'Arc.' => 'Arc.', 'Other' => 'Other'
                                                    ];
                                                }
                                            @endphp
                                            @foreach($titles as $value => $display)
                                                <option value="{{ $value }}">{{ $display }}</option>
                                            @endforeach
                                        </select>

                                        <!-- This hidden input carries the actual value to submit and is read by updateApplicantNamePreview() -->
                                        <input type="hidden" id="applicantTitle" name="applicant_title" value="">

                                        <!-- Shown only when 'Other' is selected -->
                                        <div id="applicantTitleOtherWrapper" class="mt-2 hidden">
                                            <input type="text" id="applicantTitleOther"
                                                class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                                placeholder="Enter other title"
                                                oninput="document.getElementById('applicantTitle').value = this.value; updateApplicantNamePreview();">
                                        </div>
                                    </div>

                                    <!-- First Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            First Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="applicantName" name="first_name"
                                            class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                            placeholder="Enter first name" style="text-transform:uppercase" 
                                            oninput="this.value = this.value.toUpperCase(); updateApplicantNamePreview();">
                                    </div>

                                    <!-- Middle Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Middle Name (Optional)
                                        </label>
                                        <input type="text" id="applicantMiddleName" name="middle_name"
                                            class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                            placeholder="Enter middle name" style="text-transform:uppercase" 
                                            oninput="this.value = this.value.toUpperCase(); updateApplicantNamePreview();">
                                    </div>

                                    <!-- Surname -->
                                    <div class="md:col-span-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Surname <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" id="applicantSurname" name="surname"
                                            class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                            placeholder="Enter surname" style="text-transform:uppercase" 
                                            oninput="this.value = this.value.toUpperCase(); updateApplicantNamePreview();">
                                    </div>
                               
                                    <!-- Name of Applicant -->
                                    <div class="md:col-span-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Name of Applicant
                                        </label>
                                        <input type="text" id="applicantNamePreview" name="applicant_name_preview"
                                            class="w-full py-3 px-4 bg-gray-100 border border-gray-300 rounded-lg shadow-sm font-medium text-gray-800"
                                            disabled>
                                    </div>
                                </div>
                            </div>

                            <!-- Right side - Photo Upload -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Passport Photo <span class="text-red-500">*</span>
                                </label>
                                <div id="photoUploadContainer"
                                    class="relative w-full aspect-[7/9] max-w-xs mx-auto border-2 border-dashed border-gray-300 rounded-lg flex items-center justify-center bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <div id="photoPlaceholder"
                                        class="flex flex-col items-center justify-center text-gray-400 w-full h-full">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-2" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <p class="text-sm">Upload Photo</p>
                                    </div>
                                    <img id="photoPreview" class="w-full h-full object-contain rounded-lg hidden" src="#"
                                        alt="">
                                    <button type="button" id="removePhotoBtn"
                                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 hidden hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400"
                                        onclick="removePhoto()">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                    <input type="file" id="photoUpload" name="passport" accept="image/*"
                                        class="absolute inset-0 opacity-0 cursor-pointer"
                                        onchange="previewPhoto(event)">
                                </div>
                                <p class="text-xs text-gray-500 mt-2 text-center">Passport size photo (3.5 x 4.5 cm)</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Corporate Body Information -->
                <div class="mb-10" id="corporateFields" style="display: none;">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Corporate Body Information</h2>
                <div class="bg-gray-50 p-6 rounded-lg shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                Name of Corporate Body <span class="text-red-500">*</span>
                </label>
                <input type="text" id="corporateName" name="corporate_name"
                class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                placeholder="Enter corporate body name">
                </div>
                <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                RC Number <span class="text-red-500">*</span>
                </label>
                <input type="text" id="rcNumber" name="rc_number"
                class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                placeholder="Enter RC number">
                </div>
                </div>
                
                <!-- RC Document Upload Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Upload RC Document <span class="text-red-500">*</span></label>
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center bg-gray-50 hover:bg-gray-100 transition-colors">
                <div id="subCorporateDocumentPlaceholder" class="flex flex-col items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 mb-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                <p class="text-sm text-gray-600 mb-1">Click to upload RC document</p>
                <p class="text-xs text-gray-500">JPG, PNG, PDF (max. 5MB)</p>
                </div>
                <img id="subCorporateDocumentPreview" class="hidden w-full h-32 object-cover rounded-md mt-2" src="#" alt="RC Document Preview">
                <div id="subCorporateDocumentInfo" class="hidden mt-2 text-sm text-gray-600"></div>
                <input type="file" id="subCorporateDocumentUpload" name="identification_image" accept="image/*,.pdf" class="hidden" required onchange="previewSubCorporateDocument(event)">
                <button type="button" id="removeSubCorporateDocumentBtn" class="hidden mt-2 px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600" onclick="removeSubCorporateDocument()">Remove</button>
                </div>
                <div class="mt-2">
                <button type="button" onclick="document.getElementById('subCorporateDocumentUpload').click()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                Choose File
                </button>
                </div>
                </div>
                </div>
                </div>
                </div>
                
                <!-- Multiple Owners Information -->
                <div class="mb-10" id="multipleOwnersFields" style="display: none;">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Multiple Owners Information</h2>
                    <div class="bg-gray-50 p-6 rounded-lg shadow-sm">
                        <div id="ownersContainer" class="space-y-4">
                            <!-- Dynamic rows will be inserted here -->
                        </div>
                        <div class="mt-6">
                            <button type="button" onclick="addOwnerRow()" 
                                class="flex items-center px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium transition-colors hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Owner
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>    
    </div>

    <script>
        let ownerRowCount = 0;

        function addOwnerRow() {
            const container = document.getElementById('ownersContainer');
            const rowId = `owner-row-${ownerRowCount}`;
            
            const row = document.createElement('div');
            row.id = rowId;
            row.className = 'mb-4 p-4 border border-gray-200 rounded-lg bg-white shadow-sm';

            row.innerHTML = `  
                <div class="grid grid-cols-12 gap-4">
                    <!-- Owner Details Section -->
                    <div class="col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="multiple_owners_names[]" 
                               class="w-full py-2 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm" 
                               placeholder="Enter full name">
                        <div class="mt-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Address <span class="text-red-500">*</span></label>
                            <textarea name="multiple_owners_address[]" rows="2" class="w-full p-2 border border-gray-300 rounded-md text-xs" placeholder="Enter complete address"></textarea>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                                <input type="email" name="multiple_owners_email[]" 
                                       class="w-full py-1 px-2 border border-gray-300 rounded-md text-xs" 
                                       placeholder="Enter email">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
                                <input type="tel" name="multiple_owners_phone[]" 
                                       class="w-full py-1 px-2 border border-gray-300 rounded-md text-xs" 
                                       placeholder="Enter phone">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Passport Photo Section -->
                    <div class="col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Passport Photo</label>
                        <div class="relative">
                            <input type="file" name="multiple_owners_passport[]" 
                                   class="w-full py-2 px-3 border border-gray-300 rounded-lg file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 focus:outline-none" 
                                   accept="image/*" 
                                   onchange="previewOwnerPhoto(event, '${rowId}')">
                            <img class="owner-preview hidden w-16 h-16 object-cover mt-2 rounded-md shadow-sm" src="#" alt="Preview">
                        </div>
                    </div>
                    
                    <!-- Means of Identification Section -->
                    <div class="col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Means of Identification <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-1 mb-2">
                            <label class="flex items-center text-xs">
                                <input type="radio" name="multiple_owners_identification_type_${ownerRowCount}" class="mr-1" value="national id" checked>
                                <span>National ID</span>
                            </label>
                            <label class="flex items-center text-xs">
                                <input type="radio" name="multiple_owners_identification_type_${ownerRowCount}" class="mr-1" value="drivers license">
                                <span>Driver's License</span>
                            </label>
                            <label class="flex items-center text-xs">
                                <input type="radio" name="multiple_owners_identification_type_${ownerRowCount}" class="mr-1" value="voters card">
                                <span>Voter's Card</span>
                            </label>
                            <label class="flex items-center text-xs">
                                <input type="radio" name="multiple_owners_identification_type_${ownerRowCount}" class="mr-1" value="international passport">
                                <span>Int'l Passport</span>
                            </label>
                        </div>
                        <input type="file" name="multiple_owners_identification_image[]" 
                               class="w-full py-1 px-2 border border-gray-300 rounded-md text-xs bg-white" 
                               accept="image/*,.pdf"
                               onchange="previewOwnerIdentification(event, '${rowId}')">
                        <div id="identification_preview_${rowId}" class="mt-1 border border-gray-200 rounded-md bg-white flex items-center justify-center min-h-[60px]">
                            <span class="text-gray-400 text-xs">No file selected</span>
                        </div>
                    </div>
                    
                    <!-- Remove Button -->
                    <div class="col-span-1 flex items-center justify-center">
                        <button type="button" onclick="removeOwnerRow('${rowId}')" 
                                class="p-2 bg-red-500 text-white rounded-full hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(row);
            ownerRowCount++;
        }

        function removeOwnerRow(rowId) {
            const row = document.getElementById(rowId);
            if (row) {
                row.classList.add('opacity-0', 'scale-95');
                row.style.transition = 'all 200ms ease-in-out';
                
                setTimeout(() => {
                    row.remove();
                }, 200);
            }
        }

        function previewOwnerPhoto(event, rowId) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                const row = document.getElementById(rowId);
                const preview = row.querySelector('.owner-preview');
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }
        }

        function previewOwnerIdentification(event, rowId) {
            const file = event.target.files[0];
            const preview = document.getElementById(`identification_preview_${rowId}`);
            
            if (!file) {
                preview.innerHTML = '<span class="text-gray-400 text-xs">No file selected</span>';
                return;
            }
            
            preview.innerHTML = '';
            
            if (file.type.startsWith('image/')) {
                const img = document.createElement('img');
                img.className = "max-h-14 mx-auto";
                img.style.maxWidth = "100%";
                img.alt = "Preview";
                img.src = URL.createObjectURL(file);
                preview.appendChild(img);
            } else if (file.type === 'application/pdf') {
                const icon = document.createElement('div');
                icon.innerHTML = '<svg class="w-6 h-6 text-red-500 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span class="block text-xs mt-1">PDF Selected</span>';
                preview.appendChild(icon);
            } else {
                preview.innerHTML = '<span class="text-red-500 text-xs">Unsupported file type</span>';
            }
        }

        function showMultipleOwnersFields() {
            document.getElementById('individualFields').style.display = 'none';
            document.getElementById('corporateFields').style.display = 'none';
            document.getElementById('multipleOwnersFields').style.display = 'block';
            
            // Hide Unit Owner's Address section when multiple owners is selected
            hideAddressSection();
            
            // Hide main Means of Identification section when multiple owners is selected
            hideMainIdentificationSection();
            
            // Show multiple owners passport fields and hide individual/corporate passport
            const individualPassportFields = document.querySelectorAll('.individual-fields');
            const corporatePassportFields = document.querySelectorAll('.corporate-fields');
            const multiplePassportFields = document.querySelectorAll('.multiple-fields');
            
            individualPassportFields.forEach(field => field.style.display = 'none');
            corporatePassportFields.forEach(field => field.style.display = 'none');
            multiplePassportFields.forEach(field => field.style.display = 'block');
            
            // Clear existing rows
            document.getElementById('ownersContainer').innerHTML = '';
            // Add first row
            addOwnerRow();
        }

        function hideAddressSection() {
            // Find and hide the Unit Owner's Address section
            const allDivs = document.querySelectorAll('div.bg-gray-50.p-4.rounded-md.mb-6');
            allDivs.forEach(div => {
                const addressText = div.querySelector('p');
                if (addressText && addressText.textContent.includes("Unit Owner's Address")) {
                    div.style.display = 'none';
                }
            });
        }

        function hideMainIdentificationSection() {
            // Find and hide the main Means of Identification section
            const allDivs = document.querySelectorAll('div.bg-gray-50.p-4.rounded-md.grid.grid-cols-1.md\\:grid-cols-2.gap-6.mb-6');
            allDivs.forEach(div => {
                const identificationLabel = div.querySelector('label');
                if (identificationLabel && identificationLabel.textContent.includes("Means of Identification")) {
                    div.style.display = 'none';
                }
            });
        }

        function showMainIdentificationSection() {
            // Find and show the main Means of Identification section
            const allDivs = document.querySelectorAll('div.bg-gray-50.p-4.rounded-md.grid.grid-cols-1.md\\:grid-cols-2.gap-6.mb-6');
            allDivs.forEach(div => {
                const identificationLabel = div.querySelector('label');
                if (identificationLabel && identificationLabel.textContent.includes("Means of Identification")) {
                    div.style.display = 'grid';
                }
            });
        }

        // Photo upload preview functionality
        function previewPhoto(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const photoPreview = document.getElementById('photoPreview');
                    const photoPlaceholder = document.getElementById('photoPlaceholder');
                    const removePhotoBtn = document.getElementById('removePhotoBtn');

                    photoPreview.src = e.target.result;
                    photoPreview.classList.remove('hidden');
                    photoPlaceholder.classList.add('hidden');
                    removePhotoBtn.classList.remove('hidden');
                    // Ensure object-contain is always applied
                    photoPreview.classList.remove('object-cover');
                    photoPreview.classList.add('object-contain');
                }
                reader.readAsDataURL(file);
            }
        }

        // Remove photo functionality
        function removePhoto() {
            const photoUpload = document.getElementById('photoUpload');
            const photoPreview = document.getElementById('photoPreview');
            const photoPlaceholder = document.getElementById('photoPlaceholder');
            const removePhotoBtn = document.getElementById('removePhotoBtn');

            photoUpload.value = '';
            photoPreview.src = '#';
            photoPreview.classList.add('hidden');
            photoPlaceholder.classList.remove('hidden');
            removePhotoBtn.classList.add('hidden');
        }

        function showIndividualFields() {
            clearOtherFields('individualFields');
            document.getElementById('individualFields').style.display = 'block';
            document.getElementById('corporateFields').style.display = 'none';
            document.getElementById('multipleOwnersFields').style.display = 'none';
            
            // Show Unit Owner's Address section for individual
            showAddressSection();
            
            // Show main Means of Identification section for individual
            showMainIdentificationSection();
            
            // Show individual passport fields and hide multiple owners passport
            const individualPassportFields = document.querySelectorAll('.individual-fields');
            const corporatePassportFields = document.querySelectorAll('.corporate-fields');
            const multiplePassportFields = document.querySelectorAll('.multiple-fields');
            
            individualPassportFields.forEach(field => field.style.display = 'block');
            corporatePassportFields.forEach(field => field.style.display = 'block');
            multiplePassportFields.forEach(field => field.style.display = 'none');
        }

        function showCorporateFields() {
            clearOtherFields('corporateFields');
            document.getElementById('individualFields').style.display = 'none';
            document.getElementById('corporateFields').style.display = 'block';
            document.getElementById('multipleOwnersFields').style.display = 'none';
            
            // Show Unit Owner's Address section for corporate
            showAddressSection();
            
            // Show main Means of Identification section for corporate
            showMainIdentificationSection();
            
            // Show corporate passport fields and hide multiple owners passport
            const individualPassportFields = document.querySelectorAll('.individual-fields');
            const corporatePassportFields = document.querySelectorAll('.corporate-fields');
            const multiplePassportFields = document.querySelectorAll('.multiple-fields');
            
            individualPassportFields.forEach(field => field.style.display = 'block');
            corporatePassportFields.forEach(field => field.style.display = 'block');
            multiplePassportFields.forEach(field => field.style.display = 'none');
        }

        function showAddressSection() {
            // Show Unit Owner's Address section
            const allDivs = document.querySelectorAll('div.bg-gray-50.p-4.rounded-md.mb-6');
            allDivs.forEach(div => {
                const addressText = div.querySelector('p');
                if (addressText && addressText.textContent.includes("Unit Owner's Address")) {
                    div.style.display = 'block';
                }
            });
        }

        function clearOtherFields(exceptId) {
            const fields = ['individualFields', 'corporateFields', 'multipleOwnersFields'];
            fields.forEach(id => {
                if (id !== exceptId) {
                    document.getElementById(id).querySelectorAll('input, select, textarea').forEach(input => {
                        input.value = '';
                    });
                }
            });
        }

        function setApplicantType(type) {
            // Update the hidden input field
            const applicantTypeInput = document.getElementById('mainApplicantTypeInput') || document.getElementById('applicantTypeInput');
            if (applicantTypeInput) {
                applicantTypeInput.value = type;
            }

            // Also update legacy field if it exists
            const legacyField = document.getElementById('applicantType');
            if (legacyField) {
                legacyField.value = type;
            }
        }

        function updateApplicantNamePreview() {
            const title = document.getElementById('applicantTitle').value;
            const name = document.getElementById('applicantName').value;
            const middleName = document.getElementById('applicantMiddleName').value;
            const surname = document.getElementById('applicantSurname').value;
            let applicantName = '';

            if (title) {
                applicantName += title + ' ';
            }
            if (name) {
                applicantName += name + ' ';
            }
            if (middleName) {
                applicantName += middleName + ' ';
            }
            if (surname) {
                applicantName += surname;
            }

            document.getElementById('applicantNamePreview').value = applicantName.trim();
            
            // Add hidden input for the full name to be used in form submission and summary
            let fullnameInput = document.getElementById('fullname');
            if (!fullnameInput) {
                fullnameInput = document.createElement('input');
                fullnameInput.type = 'hidden';
                fullnameInput.id = 'fullname';
                fullnameInput.name = 'fullname';
                document.querySelector('form').appendChild(fullnameInput);
            }
            fullnameInput.value = applicantName.trim();
        }

        // Sub-application corporate document upload preview functionality
        function previewSubCorporateDocument(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                const placeholder = document.getElementById('subCorporateDocumentPlaceholder');
                const preview = document.getElementById('subCorporateDocumentPreview');
                const info = document.getElementById('subCorporateDocumentInfo');
                const removeBtn = document.getElementById('removeSubCorporateDocumentBtn');

                // Validate file size (5MB limit)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Please select a file smaller than 5MB.');
                    event.target.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a JPG, PNG, or PDF file.');
                    event.target.value = '';
                    return;
                }

                if (file.type === 'application/pdf') {
                    // For PDF files, show file info instead of preview
                    placeholder.classList.add('hidden');
                    preview.classList.add('hidden');
                    info.classList.remove('hidden');
                    info.innerHTML = `
                        <div class="flex items-center justify-center">
                            <svg class="w-8 h-8 text-red-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <p class="font-medium">${file.name}</p>
                                <p class="text-xs text-gray-500">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                            </div>
                        </div>
                    `;
                    removeBtn.classList.remove('hidden');
                } else {
                    // For image files, show preview
                    reader.onload = function(e) {
                        placeholder.classList.add('hidden');
                        preview.src = e.target.result;
                        preview.classList.remove('hidden');
                        info.classList.add('hidden');
                        removeBtn.classList.remove('hidden');
                    }
                    reader.readAsDataURL(file);
                }
            }
        }

        // Remove sub-application corporate document functionality
        function removeSubCorporateDocument() {
            const upload = document.getElementById('subCorporateDocumentUpload');
            const placeholder = document.getElementById('subCorporateDocumentPlaceholder');
            const preview = document.getElementById('subCorporateDocumentPreview');
            const info = document.getElementById('subCorporateDocumentInfo');
            const removeBtn = document.getElementById('removeSubCorporateDocumentBtn');

            upload.value = '';
            preview.src = '#';
            preview.classList.add('hidden');
            info.classList.add('hidden');
            placeholder.classList.remove('hidden');
            removeBtn.classList.add('hidden');
        }
    </script>
        
        