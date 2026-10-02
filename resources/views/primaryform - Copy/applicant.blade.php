<div  class="mb-6">
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <!-- Applicant Type Section -->
          
            <input type="hidden" id="applicantType" name="applicant_type" value="{{ old('applicant_type') }}">

            <!-- Personal Information Section -->
            <div class="mb-10" id="individualFields" style="{{ old('applicantType') == 'individual' ? 'display: block;' : 'display: none;' }}">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Personal Information</h2>
                
                <!-- Single Card with Side-by-Side Layout -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg overflow-hidden border border-blue-200">
                    <div class="p-6">
                        <!-- Header with Icon -->
                        <div class="flex items-start mb-6">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-blue-900 mb-1">Applicant Information</h3>
                                <p class="text-sm text-blue-700">Auto-filled from selected file number • Upload passport photo required</p>
                            </div>
                        </div>
                        
                        <!-- Hidden fields for form submission - Database Column Names -->
                        <input type="hidden" id="applicantName" name="fname" value="{{ old('fname') }}">
                        <input type="hidden" id="applicantMiddleName" name="mname" value="{{ old('mname') }}">
                        <input type="hidden" id="applicantSurname" name="lname" value="{{ old('lname') }}">
                        
                        <!-- Hidden select for title (needed for JavaScript population) -->
                        <select id="applicantTitle" name="title" class="hidden">
                            <option value="" disabled {{ old('applicant_title') ? '' : 'selected' }}>Select title</option>
                            @php
                                $titles = [
                                    'Mr.', 'Mrs.', 'Chief', 'Master', 'Capt', 'Coln', 'HRH', 'Mallam',
                                    'Prof', 'Dr.', 'Alhaji', 'Hajia', 'High Chief', 'Senator', 'Messr',
                                    'Honorable', 'Miss', 'Barr.', 'Arc.', 'Other'
                                ];
                            @endphp
                            @foreach($titles as $title)
                                <option value="{{ $title }}" {{ old('applicant_title') == $title ? 'selected' : '' }}>
                                    {{ $title }}
                                </option>
                            @endforeach
                        </select>
                        
                        <!-- Side-by-Side Layout: Applicant Name + Passport Photo -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Left: Applicant Name (2/3 width) -->
                            <div class="lg:col-span-2">
                                <div class="bg-white rounded-lg p-5 shadow-md border border-blue-100">
                                    <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Name of Applicant
                                    </label>
                                    <input type="text" id="applicantNamePreview" name="applicant_name_preview"
                                        class="w-full py-4 px-5 bg-gradient-to-r from-gray-50 to-blue-50 border-2 border-blue-200 rounded-lg shadow-sm font-bold text-lg text-gray-900 uppercase tracking-wide cursor-not-allowed focus:ring-2 focus:ring-blue-400 transition-all"
                                        readonly disabled
                                        placeholder="Name will appear here">
                                    <p class="mt-2 text-xs text-gray-600 flex items-center">
                                        <svg class="w-3 h-3 mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Automatically populated from API
                                    </p>
                                </div>
                            </div>

                            <!-- Right: Passport Photo Upload (1/3 width) -->
                            <div class="lg:col-span-1">
                                <div class="bg-white rounded-lg p-5 shadow-md border border-blue-100">
                                    <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                                        <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        </svg>
                                        Passport Photo <span class="text-red-500 ml-1">*</span>
                                    </label>
                                    <div id="photoUploadContainer"
                                        class="relative w-full aspect-[3.5/4.5] border-2 border-dashed border-blue-300 rounded-lg flex items-center justify-center bg-gradient-to-br from-gray-50 to-blue-50 hover:from-blue-50 hover:to-indigo-100 transition-all duration-300 cursor-pointer group">
                                        <div id="photoPlaceholder"
                                            class="flex flex-col items-center justify-center text-gray-400 absolute inset-0 z-10 rounded-lg group-hover:text-blue-500 transition-colors pointer-events-none">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-2" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <p class="text-xs font-medium text-center px-2">Click to Upload<br><span class="text-[10px] text-gray-500">(3.5 x 4.5 cm)</span></p>
                                        </div>
                                        <img id="photoPreview" class="w-full h-full object-cover rounded-lg absolute inset-0 z-20 hidden border-2 border-blue-500 shadow-lg pointer-events-none" src="#"
                                            alt="Passport Photo Preview">
                                        <button type="button" id="removePhotoBtn"
                                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1.5 hidden hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400 shadow-lg z-50 transition-all hover:scale-110"
                                            onclick="removePhoto()">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                        <input type="file" id="photoUpload" name="passport" accept="image/*"
                                            class="absolute inset-0 opacity-0 cursor-pointer z-40"
                                            onchange="previewPhoto(event)">
                                    </div>
                                    <p class="text-[10px] text-gray-600 mt-2 text-center leading-tight">Passport size (3.5×4.5 cm)<br>Clear background, max 2MB</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Corporate Body Information -->
            <div class="mb-10" id="corporateFields" style="{{ old('applicantType') == 'corporate' ? 'display: block;' : 'display: none;' }}">
                <h2 class="text-lg font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-200">Corporate Body Information</h2>
                
                <!-- Single Card with Side-by-Side Layout -->
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl shadow-lg overflow-hidden border border-purple-200">
                    <div class="p-6">
                        <!-- Header with Icon -->
                        <div class="flex items-start mb-6">
                            <div class="flex-shrink-0 w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center mr-3">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-purple-900 mb-1">Corporate Information</h3>
                                <p class="text-sm text-purple-700">Auto-filled from selected file number • Upload RC document required</p>
                            </div>
                        </div>
                        
                        <!-- Hidden fields for form submission -->
                        <input type="hidden" id="corporateName" name="corporate_name" value="{{ old('corporate_name') }}">
                        <input type="hidden" id="rcNumber" name="rc_number" value="{{ old('rc_number') }}">
                        
                        <!-- Side-by-Side Layout: Corporate Details + RC Document -->
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            <!-- Left: Corporate Details (2/3 width) -->
                            <div class="lg:col-span-2">
                                <div class="bg-white rounded-lg p-5 shadow-md border border-purple-100">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                                                <svg class="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                </svg>
                                                Corporate Name
                                            </label>
                                            <div id="corporateNameDisplay" class="w-full py-4 px-5 bg-gradient-to-r from-gray-50 to-purple-50 border-2 border-purple-200 rounded-lg shadow-sm font-bold text-base text-gray-900 uppercase tracking-wide">-</div>
                                            <p class="mt-2 text-xs text-gray-600 flex items-center">
                                                <svg class="w-3 h-3 mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                Auto-populated
                                            </p>
                                        </div>
                                        <div>
                                            <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                                                <svg class="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                RC Number
                                            </label>
                                            <div id="rcNumberDisplay" class="w-full py-4 px-5 bg-gradient-to-r from-gray-50 to-purple-50 border-2 border-purple-200 rounded-lg shadow-sm font-bold text-base text-gray-900 tracking-wider">-</div>
                                            <p class="mt-2 text-xs text-gray-600 flex items-center">
                                                <svg class="w-3 h-3 mr-1 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                                </svg>
                                                Auto-populated
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: RC Document Upload (1/3 width) -->
                            <div class="lg:col-span-1">
                                <div class="bg-white rounded-lg p-5 shadow-md border border-purple-100">
                                    <label class="flex items-center text-sm font-semibold text-gray-700 mb-3">
                                        <svg class="w-4 h-4 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        RC Document <span class="text-red-500 ml-1">*</span>
                                    </label>
                                    <div class="border-2 border-dashed border-purple-300 rounded-lg bg-gradient-to-br from-gray-50 to-purple-50 hover:from-purple-50 hover:to-pink-100 transition-all duration-300 cursor-pointer group relative overflow-hidden">
                                        <div id="corporateDocumentPlaceholder" class="flex flex-col items-center justify-center p-6 group-hover:text-purple-600 transition-colors">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mb-2 text-gray-400 group-hover:text-purple-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                            </svg>
                                            <p class="text-xs font-medium text-gray-600 mb-1 text-center">Click to upload</p>
                                            <p class="text-[10px] text-gray-500 text-center">JPG, PNG, PDF<br>(max. 5MB)</p>
                                        </div>
                                        <img id="corporateDocumentPreview" class="hidden w-full h-40 object-cover rounded-md" src="#" alt="RC Document Preview">
                                        <div id="corporateDocumentInfo" class="hidden p-3 text-xs text-gray-700 bg-white border-t border-purple-200"></div>
                                        <input type="file" id="corporateDocumentUpload" name="rc_document" accept="image/*,.pdf" class="absolute inset-0 opacity-0 cursor-pointer z-10" onchange="previewCorporateDocument(event)">
                                    </div>
                                    <button type="button" id="removeCorporateDocumentBtn" class="hidden mt-3 w-full px-3 py-2 bg-red-500 text-white text-xs font-medium rounded-lg hover:bg-red-600 transition-colors shadow-md hover:shadow-lg" onclick="removeCorporateDocument()">
                                        <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Remove Document
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>      
            
            <!-- Multiple Owners Information -->
            <div class="mb-10" id="multipleOwnersFields" style="{{ old('applicantType') == 'multiple' ? 'display: block;' : 'display: none;' }}">
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
        const fileInputId = `owner-passport-${ownerRowCount}`;
        const previewImgId = `owner-preview-img-${ownerRowCount}`;
        const idFileInputId = `owner-idimg-${ownerRowCount}`;
        const idPreviewImgId = `owner-idimg-preview-${ownerRowCount}`;

        const row = document.createElement('div');
        row.id = rowId;
        row.className = 'border rounded-lg p-4 bg-white flex flex-col md:flex-row md:items-start gap-4 relative';

        row.innerHTML = `
            <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="multiple_owners_names[]"
                        class="w-full py-2 px-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                        placeholder="Enter full name">
                    <div class="mt-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address <span class="text-red-500">*</span></label>
                        <textarea name="multiple_owners_address[]" rows="2"
                            class="w-full py-2 px-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                            placeholder="Enter complete address"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Email</label>
                            <input type="email" name="multiple_owners_email[]"
                                class="w-full py-2 px-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                placeholder="Enter email">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-700 mb-1">Phone</label>
                            <input type="text" name="multiple_owners_phone[]"
                                class="w-full py-2 px-3 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm"
                                placeholder="Enter phone">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Passport Photo</label>
                    <input type="file" id="${fileInputId}" name="multiple_owners_passport[]" accept="image/*"
                        class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md file:mr-2 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        onchange="previewOwnerPhoto(event, '${rowId}', '${previewImgId}')">
                    <img id="${previewImgId}" class="owner-preview hidden w-16 h-20 object-contain mt-2 rounded-md shadow-sm border border-gray-200 bg-white" src="#" alt="Preview">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Means of Identification <span class="text-red-500">*</span></label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <label class="flex items-center text-xs"><input type="radio" name="multiple_owners_identification_type[${ownerRowCount}]" value="national_id" class="mr-1">National ID</label>
                        <label class="flex items-center text-xs"><input type="radio" name="multiple_owners_identification_type[${ownerRowCount}]" value="drivers_license" class="mr-1">Driver's License</label>
                        <label class="flex items-center text-xs"><input type="radio" name="multiple_owners_identification_type[${ownerRowCount}]" value="voters_card" class="mr-1">Voter's Card</label>
                        <label class="flex items-center text-xs"><input type="radio" name="multiple_owners_identification_type[${ownerRowCount}]" value="international_passport" class="mr-1">Int'l Passport</label>
                    </div>
                    <input type="file" id="${idFileInputId}" name="multiple_owners_identification_image[]" accept="image/*,application/pdf"
                        class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md file:mr-2 file:py-2 file:px-3 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        onchange="previewOwnerIdImage(event, '${rowId}', '${idPreviewImgId}')">
                    <div class="mt-2">
                        <img id="${idPreviewImgId}" class="hidden w-16 h-16 object-contain rounded-md shadow-sm border border-gray-200 bg-white" src="#" alt="ID Preview">
                        <div class="text-xs text-gray-500" id="${idPreviewImgId}-info">No file selected</div>
                    </div>
                </div>
            </div>
            <button type="button" onclick="removeOwnerRow('${rowId}')"
                class="absolute top-2 right-2 p-2 bg-red-500 text-white rounded-full hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        `;

        container.appendChild(row);
        ownerRowCount++;
    }

    function previewOwnerPhoto(event, rowId, previewImgId) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            const preview = document.getElementById(previewImgId);
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            }
            reader.readAsDataURL(file);
        }
    }

    function previewOwnerIdImage(event, rowId, previewImgId) {
        const file = event.target.files[0];
        const preview = document.getElementById(previewImgId);
        const info = document.getElementById(previewImgId + '-info');
        if (file) {
            if (file.type === "application/pdf") {
                preview.src = "https://img.icons8.com/ios-filled/50/000000/pdf.png";
                preview.classList.remove('hidden');
                info.textContent = file.name;
            } else {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('hidden');
                    info.textContent = file.name;
                }
                reader.readAsDataURL(file);
            }
        } else {
            preview.classList.add('hidden');
            info.textContent = "No file selected";
        }
    }

    function showMultipleOwnersFields() {
        document.getElementById('individualFields').style.display = 'none';
        document.getElementById('corporateFields').style.display = 'none';
        document.getElementById('multipleOwnersFields').style.display = 'block';
        
        // Clear existing rows
        document.getElementById('ownersContainer').innerHTML = '';
        // Add first row
        addOwnerRow();
    }

    // Photo upload preview functionality
    function previewPhoto(event) {
        console.log('📸 previewPhoto called');
        const file = event.target.files[0];
        if (file) {
            console.log('📸 File selected:', file.name, 'Size:', (file.size / 1024).toFixed(2) + ' KB');
            
            // Validate file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                event.target.value = '';
                return;
            }
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                alert('Please select an image file');
                event.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                console.log('📸 Image loaded successfully');
                const photoPreview = document.getElementById('photoPreview');
                const photoPlaceholder = document.getElementById('photoPlaceholder');
                const removePhotoBtn = document.getElementById('removePhotoBtn');

                photoPreview.src = e.target.result;
                photoPreview.classList.remove('hidden');
                photoPlaceholder.classList.add('hidden');
                removePhotoBtn.classList.remove('hidden');
            }
            reader.onerror = function(error) {
                console.error('📸 Error reading file:', error);
                alert('Error reading file. Please try again.');
            }
            reader.readAsDataURL(file);
        } else {
            console.log('📸 No file selected');
        }
    }

    // Remove photo functionality
    function removePhoto() {
        console.log('🗑️ removePhoto called');
        const photoUpload = document.getElementById('photoUpload');
        const photoPreview = document.getElementById('photoPreview');
        const photoPlaceholder = document.getElementById('photoPlaceholder');
        const removePhotoBtn = document.getElementById('removePhotoBtn');

        photoUpload.value = '';
        photoPreview.src = '#';
        photoPreview.classList.add('hidden');
        photoPlaceholder.classList.remove('hidden');
        removePhotoBtn.classList.add('hidden');
        console.log('🗑️ Photo removed successfully');
    }

    // Initialize the handlers when the document loads
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎯 Initializing passport photo upload handlers...');
        
        // Add click handler to photo upload container as backup
        const photoUploadContainer = document.getElementById('photoUploadContainer');
        const photoUploadInput = document.getElementById('photoUpload');
        
        if (photoUploadContainer && photoUploadInput) {
            photoUploadContainer.addEventListener('click', function(e) {
                console.log('📸 Container clicked, triggering file input');
                // Don't trigger if clicking the remove button
                if (!e.target.closest('#removePhotoBtn')) {
                    photoUploadInput.click();
                }
            });
            console.log('✅ Photo upload container click handler added');
        } else {
            console.warn('⚠️ Photo upload elements not found:', {
                container: !!photoUploadContainer,
                input: !!photoUploadInput
            });
        }
        
        // Show the correct fields based on old input
        const applicantType = "{{ old('applicantType') }}";
        if (applicantType === 'individual') {
            showIndividualFields();
        } else if (applicantType === 'corporate') {
            showCorporateFields();
        } else if (applicantType === 'multiple') {
            showMultipleOwnersFields();
        }
        
        // Initialize applicant name preview if returning with validation errors
        if ("{{ old('first_name') }}" || "{{ old('middle_name') }}" || "{{ old('surname') }}") {
            updateApplicantNamePreview();
        }
        
        // Restore multiple owners if any
        @if(old('multiple_owners_names'))
            @foreach(old('multiple_owners_names') as $index => $name)
                // Add owner row for each old value
                if ({{ $index }} > 0) { // Skip first row as it's added by default
                    addOwnerRow();
                }
            @endforeach
        @endif
    });

    function showIndividualFields() {
        clearOtherFields('individualFields');
        document.getElementById('individualFields').style.display = 'block';
        document.getElementById('corporateFields').style.display = 'none';
        document.getElementById('multipleOwnersFields').style.display = 'none';
    }

    function showCorporateFields() {
        clearOtherFields('corporateFields');
        document.getElementById('individualFields').style.display = 'none';
        document.getElementById('corporateFields').style.display = 'block';
        document.getElementById('multipleOwnersFields').style.display = 'none';
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
        // Update the visible applicant type field
        document.getElementById('applicantType').value = type;
        
        // Also update the hidden field used for form submission
        const hiddenApplicantType = document.getElementById('hidden-applicant-type');
        if (hiddenApplicantType) {
            hiddenApplicantType.value = type;
            console.log('✅ Set applicant type to:', type);
        } else {
            console.warn('⚠️ Hidden applicant type field not found');
        }
        
        // Debug field removed
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

    // Corporate document upload preview functionality
    function previewCorporateDocument(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            const placeholder = document.getElementById('corporateDocumentPlaceholder');
            const preview = document.getElementById('corporateDocumentPreview');
            const info = document.getElementById('corporateDocumentInfo');
            const removeBtn = document.getElementById('removeCorporateDocumentBtn');

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

    // Remove corporate document functionality
    function removeCorporateDocument() {
        const upload = document.getElementById('corporateDocumentUpload');
        const placeholder = document.getElementById('corporateDocumentPlaceholder');
        const preview = document.getElementById('corporateDocumentPreview');
        const info = document.getElementById('corporateDocumentInfo');
        const removeBtn = document.getElementById('removeCorporateDocumentBtn');

        upload.value = '';
        preview.src = '#';
        preview.classList.add('hidden');
        info.classList.add('hidden');
        placeholder.classList.remove('hidden');
        removeBtn.classList.add('hidden');
    }

    function ensureOwnerRowCount(targetCount) {
        const container = document.getElementById('ownersContainer');
        if (!container) {
            return;
        }

        const desired = Math.max(0, Number.isFinite(Number(targetCount)) ? Math.floor(Number(targetCount)) : 0);
        let current = container.children.length;

        while (current < desired) {
            addOwnerRow();
            current = container.children.length;
        }

        while (current > desired) {
            const lastRow = container.lastElementChild;
            if (!lastRow) {
                break;
            }

            container.removeChild(lastRow);
            ownerRowCount = Math.max(0, ownerRowCount - 1);
            current = container.children.length;
        }

        ownerRowCount = container.children.length;
    }

    function populateMultipleOwnersFromState(state) {
        if (!state || typeof state !== 'object') {
            return;
        }

        const names = Array.isArray(state.multiple_owners_names) ? state.multiple_owners_names : [];
        const addresses = Array.isArray(state.multiple_owners_address) ? state.multiple_owners_address : [];
        const emails = Array.isArray(state.multiple_owners_email) ? state.multiple_owners_email : [];
        const phones = Array.isArray(state.multiple_owners_phone) ? state.multiple_owners_phone : [];

        const idTypes = [];
        Object.keys(state).forEach((key) => {
            const match = key.match(/^multiple_owners_identification_type\[(\d+)\]$/);
            if (match) {
                idTypes[Number(match[1])] = state[key];
            }
        });

        const longest = Math.max(names.length, addresses.length, emails.length, phones.length, idTypes.length);

        ensureOwnerRowCount(longest);

        const container = document.getElementById('ownersContainer');
        if (!container) {
            return;
        }

        const nameInputs = container.querySelectorAll('input[name="multiple_owners_names[]"]');
        const addressInputs = container.querySelectorAll('textarea[name="multiple_owners_address[]"]');
        const emailInputs = container.querySelectorAll('input[name="multiple_owners_email[]"]');
        const phoneInputs = container.querySelectorAll('input[name="multiple_owners_phone[]"]');

        const eventOptions = { bubbles: true };

        nameInputs.forEach((input, index) => {
            input.value = names[index] ?? '';
            input.dispatchEvent(new Event('input', eventOptions));
            input.dispatchEvent(new Event('change', eventOptions));
        });

        addressInputs.forEach((input, index) => {
            input.value = addresses[index] ?? '';
            input.dispatchEvent(new Event('input', eventOptions));
            input.dispatchEvent(new Event('change', eventOptions));
        });

        emailInputs.forEach((input, index) => {
            input.value = emails[index] ?? '';
            input.dispatchEvent(new Event('input', eventOptions));
            input.dispatchEvent(new Event('change', eventOptions));
        });

        phoneInputs.forEach((input, index) => {
            input.value = phones[index] ?? '';
            input.dispatchEvent(new Event('input', eventOptions));
            input.dispatchEvent(new Event('change', eventOptions));
        });

        const identificationGroups = container.querySelectorAll('div[id^="owner-row-"]');
        identificationGroups.forEach((row, index) => {
            const targetValue = idTypes[index];
            if (!targetValue) {
                return;
            }

            const radios = row.querySelectorAll(`input[name="multiple_owners_identification_type[${index}]"]`);
            radios.forEach((radio) => {
                radio.checked = radio.value === targetValue;
                if (radio.checked) {
                    radio.dispatchEvent(new Event('change', eventOptions));
                }
            });
        });
    }
    
    // Make functions globally accessible
    window.showIndividualFields = showIndividualFields;
    window.showCorporateFields = showCorporateFields;
    window.showMultipleOwnersFields = showMultipleOwnersFields;
    window.setApplicantType = setApplicantType;
    window.clearOtherFields = clearOtherFields;
    window.ensureOwnerRowCount = ensureOwnerRowCount;
    window.populateMultipleOwnersFromState = populateMultipleOwnersFromState;
    window.previewPhoto = previewPhoto;
    window.removePhoto = removePhoto;
</script>


