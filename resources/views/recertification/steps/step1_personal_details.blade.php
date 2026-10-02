<!-- Step 1: Applicant Personal Details -->
<div id="step-content-1" class="step-content"> 
    <div class="bg-white border border-gray-200 rounded-lg">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="user" class="h-5 w-5"></i>
                SECTION A: APPLICANT PERSONAL DETAILS
            </h3>
        </div>
        <div class="p-4 space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-9 space-y-4">
                    <div class="form-field">
                        <label for="applicationDate" class="block text-sm font-medium text-gray-700 mb-1">
                            Application Date <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="date"
                            id="applicationDate"
                            name="applicationDate"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        />
                        <div class="error-message">Application date is required</div>
                    </div>

                    <!-- Applicant Type -->
                    <div class="form-field">
                        <label for="applicantType" class="block text-sm font-medium text-gray-700 mb-1">
                            Applicant Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="applicantType"
                            name="applicantType"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        >
                            <option value="Individual" selected>Individual</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Government Body">Government Body</option>
                            <option value="Multiple Owners">Multiple Owners</option>
                        </select>
                        <div class="error-message">Applicant type is required</div>
                    </div>
                    
                    <!-- Individual Fields -->
                    <div id="individual-fields" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-field">
                                <label for="surname" class="block text-sm font-medium text-gray-700 mb-1">
                                    Surname <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="surname"
                                    name="surname"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="SURNAME"
                                />
                                <div class="error-message">Surname is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="firstName" class="block text-sm font-medium text-gray-700 mb-1">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="firstName"
                                    name="firstName"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="FIRST NAME"
                                />
                                <div class="error-message">First name is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="middleName" class="block text-sm font-medium text-gray-700 mb-1">
                                    Other Names (Middle Name or Initials)
                                </label>
                                <input
                                    type="text"
                                    id="middleName"
                                    name="middleName"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="MIDDLE NAME"
                                />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-field">
                                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                                <select
                                    id="title"
                                    name="title"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                >
                                    <option value="" disabled selected>Select title</option>
                                    <option value="Mr.">Mr.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Chief">Chief</option>
                                    <option value="Master">Master</option>
                                    <option value="Capt">Capt</option>
                                    <option value="Coln">Coln</option>
                                    <option value="Pastor">Pastor</option>
                                    <option value="King">King</option>
                                    <option value="Prof">Prof</option>
                                    <option value="Dr.">Dr.</option>
                                    <option value="Alhaji">Alhaji</option>
                                    <option value="Alhaja">Alhaja</option>
                                    <option value="High Chief">High Chief</option>
                                    <option value="Lady">Lady</option>
                                    <option value="Bishop">Bishop</option>
                                    <option value="Senator">Senator</option>
                                    <option value="Messr">Messr</option>
                                    <option value="Honorable">Honorable</option>
                                    <option value="Miss">Miss</option>
                                    <option value="Rev.">Rev.</option>
                                    <option value="Barr.">Barr.</option>
                                    <option value="Arc.">Arc.</option>
                                    <option value="Sister">Sister</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <!-- <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">
                                    Occupation <span class="text-red-500">*</span>
                                </label> -->
                                @include('components.Occupation2')
                                <div class="error-message">Occupation is required</div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="form-field">
                                <label for="dateOfBirth" class="block text-sm font-medium text-gray-700 mb-1">
                                    Date of Birth <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="date"
                                    id="dateOfBirth"
                                    name="dateOfBirth"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                />
                                <div class="error-message">Date of birth is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="nationality" class="block text-sm font-medium text-gray-700 mb-1">
                                    Nationality <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="nationality"
                                    name="nationality"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="NIGERIAN"
                                />
                                <div class="error-message">Nationality is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="state" class="block text-sm font-medium text-gray-700 mb-1">
                                    State of Origin <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="state"
                                    name="stateOfOrigin"
                                    required
                                    onchange="selectLGA(this)"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                >
                                    <option>Select State <span class="text-red-500">*</span></option>
                                </select>
                                <div class="error-message">State of origin is required</div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-field">
                                <label for="lga" class="block text-sm font-medium text-gray-700 mb-1">LGA of Origin <span class="text-red-500">*</span></label>
                                <select
                                    id="lga"
                                    name="lgaOfOrigin"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                >
                                    <option>Select LGA</option>
                                </select>
                            </div>
                            
                            <div class="form-field">
                                <label for="nin" class="block text-sm font-medium text-gray-700 mb-1">NIN</label>
                                <input
                                    type="text"
                                    id="nin"
                                    name="nin"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                    placeholder="NATIONAL IDENTIFICATION NUMBER"
                                />
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-field">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-4">
                                    <label class="radio-item">
                                        <input type="radio" name="gender" value="male" required />
                                        <div class="radio-circle"></div>
                                        <span class="text-sm">Male</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="gender" value="female" />
                                        <div class="radio-circle"></div>
                                        <span class="text-sm">Female</span>
                                    </label>
                                </div>
                                <div class="error-message">Gender is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Marital Status <span class="text-red-500">*</span>
                                </label>
                                <div class="flex gap-4 flex-wrap">
                                    <label class="radio-item">
                                        <input type="radio" name="maritalStatus" value="single" required />
                                        <div class="radio-circle"></div>
                                        <span class="text-sm">Single</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="maritalStatus" value="married" />
                                        <div class="radio-circle"></div>
                                        <span class="text-sm">Married</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="maritalStatus" value="divorced" />
                                        <div class="radio-circle"></div>
                                        <span class="text-sm">Divorced</span>
                                    </label>
                                    <label class="radio-item">
                                        <input type="radio" name="maritalStatus" value="widowed" />
                                        <div class="radio-circle"></div>
                                        <span class="text-sm">Widowed</span>
                                    </label>
                                </div>
                                <div class="error-message">Marital status is required</div>
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label for="maidenName" class="block text-sm font-medium text-gray-700 mb-1">
                                Maiden Name (if applicable)
                            </label>
                            <input
                                type="text"
                                id="maidenName"
                                name="maidenName"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                placeholder="MAIDEN NAME"
                            />
                        </div>
                    </div>

                    <!-- Corporate Fields (Used for both Corporate and Government Body) -->
                    <div id="corporate-fields" class="space-y-4 hidden">
                        <div class="bg-purple-50 p-4 rounded-lg mb-4">
                            <h4 class="font-semibold text-purple-900 mb-2" id="corporate-header">Corporate Details</h4>
                            <p class="text-sm text-purple-800" id="corporate-description">Please provide the following corporate information:</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-field col-span-1 md:col-span-2">
                                <label for="organisationName" class="block text-sm font-medium text-gray-700 mb-1">
                                    Organization Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="organisationName"
                                    name="organisationName"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="ORGANIZATION NAME"
                                />
                                <div class="error-message">Organization name is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="cacRegistrationStatus" class="block text-sm font-medium text-gray-700 mb-1">
                                    Registration Status with CAC <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="cacRegistrationStatus"
                                    name="cacRegistrationStatus"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                >
                                    <option value="" disabled selected>Select Registration Status</option>
                                    <option value="Registered">Registered</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Not Registered">Not Registered</option>
                                </select>
                                <div class="error-message">Registration status is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="typeOfOrganisation" class="block text-sm font-medium text-gray-700 mb-1">
                                    Type of Organization <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="typeOfOrganisation"
                                    name="typeOfOrganisation"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                >
                                    <option value="" disabled selected>Select Organization Type</option>
                                    <option value="Limited Liability Company">Limited Liability Company</option>
                                    <option value="Partnership">Partnership</option>
                                    <option value="PLC">PLC</option>
                                    <option value="NGO">NGO</option>
                                    <option value="Diplomatic Mission">Diplomatic Mission</option>
                                    <option value="Government Institution">Government Institution</option>
                                    <option value="Cooperative Society">Cooperative Society</option>
                                    <option value="Registered Enterprise Business">Registered Enterprise Business</option>
                                </select>
                                <div class="error-message">Type of organization is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="typeOfBusiness" class="block text-sm font-medium text-gray-700 mb-1">
                                    Type of Business <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="typeOfBusiness"
                                    name="typeOfBusiness"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                                    placeholder="E.G. CONSTRUCTION, TRADING, MANUFACTURING"
                                />
                                <div class="error-message">Type of business is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="cacRegistrationNumber" class="block text-sm font-medium text-gray-700 mb-1">
                                    CAC Registration Number <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="cacRegistrationNumber"
                                    name="cacRegistrationNumber"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                    placeholder="CAC REGISTRATION NUMBER"
                                />
                                <div class="error-message">CAC registration number is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="businessSize" class="block text-sm font-medium text-gray-700 mb-1">
                                    Size of Business <span class="text-red-500">*</span>
                                </label>
                                <select
                                    id="businessSize"
                                    name="businessSize"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                >
                                    <option value="" disabled selected>Select Business Size</option>
                                    <option value="Small Scale">Small Scale</option>
                                    <option value="Medium Scale">Medium Scale</option>
                                    <option value="Large Scale">Large Scale</option>
                                    <option value="Not in Operation">Not in Operation</option>
                                </select>
                                <div class="error-message">Business size is required</div>
                            </div>
                            
                            <div class="form-field">
                                <label for="taxIdentificationNumber" class="block text-sm font-medium text-gray-700 mb-1">
                                    Tax Identification Number (TIN) <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="taxIdentificationNumber"
                                    name="taxIdentificationNumber"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                                    placeholder="TAX IDENTIFICATION NUMBER"
                                />
                                <div class="error-message">Tax identification number is required</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Multiple Owners Fields -->
                    <div id="multiple-owners-fields" class="space-y-4 hidden">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold">Owners</h4>
                            <button type="button" id="add-owner-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-blue-600 text-white hover:bg-blue-700">
                                Add Owner
                            </button>
                        </div>
                        <div id="owners-list" class="space-y-4"></div>
                    </div>
                </div>
                
                <div class="lg:col-span-3 space-y-4">
                    <!-- Individual Passport Photo (Shows for Individual only) -->
                    <div id="individual-photo-section" class="photo-upload-area text-center" style="display: block;">
                        <input type="file" id="passportPhoto" name="passportPhoto" accept="image/*" class="hidden" />
                        <div id="photo-preview-container" class="mb-3 hidden">
                            <!-- Passport Card Style Preview -->
                            <div class="passport-card bg-white border-2 border-gray-300 rounded-lg p-3 mx-auto" style="width: 140px;">
                                <div class="passport-photo-frame bg-gray-100 border border-gray-300 rounded" style="width: 114px; height: 140px; margin: 0 auto;">
                                    <img id="photo-preview" src="" alt="Passport Photo Preview" class="w-full h-full object-cover rounded" />
                                </div>
                                <div class="text-center mt-2">
                                    <div class="text-xs font-semibold text-gray-700">PASSPORT</div>
                                    <div class="text-xs text-gray-500">2" X 2"</div>
                                </div>
                            </div>
                        </div>
                        <i data-lucide="camera" class="h-8 w-8 mb-2 text-gray-400" id="camera-icon"></i>
                        <div class="text-xs font-semibold mb-2">PASSPORT PHOTOGRAPH</div>
                        <div class="text-xs text-gray-500 mb-2">(2" X 2")</div>
                        <button type="button" id="passport-upload-btn" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                            Upload Photo
                        </button>
                        <div id="passport-file-info" class="text-xs text-gray-600 mt-2 hidden">
                            <div id="passport-file-name" class="font-medium"></div>
                        </div>
                        <div class="text-xs text-red-600 mt-2">
                            NOTE: DO NOT put a staple pin over the face region of the photo
                        </div>
                    </div>

                    
                    <!-- Multiple Owners - Empty sidebar message (Hidden by default) -->
                    <div id="multiple-owners-sidebar" class="owners-sidebar-section" style="display: none;">
                        <div class="text-center text-gray-500 py-8">
                            <i data-lucide="users" class="h-12 w-12 mx-auto mb-3 text-gray-400"></i>
                            <div class="text-sm font-medium mb-1">Multiple Owners</div>
                            <div class="text-xs">Passport photos will appear with each owner's details</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Immediate script to handle applicant type changes
document.addEventListener('DOMContentLoaded', function() {
    console.log('Step 1 - Setting up applicant type handler');
    
    const applicantTypeSelect = document.getElementById('applicantType');
    const individualFields = document.getElementById('individual-fields');
    const corporateFields = document.getElementById('corporate-fields');
    const multipleOwnersFields = document.getElementById('multiple-owners-fields');
    const individualPhotoSection = document.getElementById('individual-photo-section');
    const multipleOwnersSidebar = document.getElementById('multiple-owners-sidebar');
    const corporateHeader = document.getElementById('corporate-header');
    const corporateDescription = document.getElementById('corporate-description');
    
    function updateFormSections(applicantType) {
        console.log('Updating form sections for:', applicantType);
        
        // Hide all form sections first
        if (individualFields) {
            individualFields.classList.add('hidden');
            console.log('Hidden individual fields');
        }
        if (corporateFields) {
            corporateFields.classList.add('hidden');
            console.log('Hidden corporate fields');
        }
        if (multipleOwnersFields) {
            multipleOwnersFields.classList.add('hidden');
            console.log('Hidden multiple owners fields');
        }
        
        // Hide all file upload sections first using inline styles to override any CSS conflicts
        if (individualPhotoSection) {
            individualPhotoSection.style.display = 'none';
            console.log('Hidden individual photo section');
        }
        if (multipleOwnersSidebar) {
            multipleOwnersSidebar.style.display = 'none';
            console.log('Hidden multiple owners sidebar');
        }
        
        // Show appropriate sections based on applicant type
        switch(applicantType) {
            case 'Individual':
                if (individualFields) {
                    individualFields.classList.remove('hidden');
                    console.log('Showed individual fields');
                }
                if (individualPhotoSection) {
                    individualPhotoSection.style.display = 'block';
                    console.log('Showed individual photo section');
                }
                break;
                
            case 'Corporate':
                if (corporateFields) {
                    corporateFields.classList.remove('hidden');
                    console.log('Showed corporate fields');
                }
                // Update header for Corporate
                if (corporateHeader) {
                    corporateHeader.textContent = 'Corporate Details';
                }
                if (corporateDescription) {
                    corporateDescription.textContent = 'Please provide the following corporate information:';
                }
                console.log('Corporate type selected - no document upload section');
                break;
                
            case 'Government Body':
                if (corporateFields) {
                    corporateFields.classList.remove('hidden');
                    console.log('Showed corporate fields for Government Body');
                }
                // Update header for Government Body
                if (corporateHeader) {
                    corporateHeader.textContent = 'Government Body Details';
                }
                if (corporateDescription) {
                    corporateDescription.textContent = 'Please provide the following government body information:';
                }
                console.log('Government Body type selected - no document upload section');
                break;
                
            case 'Multiple Owners':
                if (multipleOwnersFields) {
                    multipleOwnersFields.classList.remove('hidden');
                    console.log('Showed multiple owners fields');
                }
                if (multipleOwnersSidebar) {
                    multipleOwnersSidebar.style.display = 'block';
                    console.log('Showed multiple owners sidebar');
                }
                break;
        }
    }
    
    if (applicantTypeSelect) {
        // Set initial state
        updateFormSections(applicantTypeSelect.value);
        
        // Add change listener
        applicantTypeSelect.addEventListener('change', function() {
            console.log('Applicant type changed to:', this.value);
            updateFormSections(this.value);
        });
        
        console.log('Applicant type handler setup complete');
    } else {
        console.error('Applicant type select not found!');
    }

    // Fetch all States
    fetch('https://nga-states-lga.onrender.com/fetch')
    .then((res) => res.json())
    .then((data) => {
        var x = document.getElementById("state");
        for (let index = 0; index < Object.keys(data).length; index++) {
            var option = document.createElement("option");
            option.text = data[index];
            option.value = data[index];
            x.add(option);
        }
    });
});

// Fetch Local Governments based on selected state
function selectLGA(target) {
    var state = target.value;
    fetch('https://nga-states-lga.onrender.com/?state='+state)
    .then((res) => res.json())
    .then((data) => {
        var x = document.getElementById("lga");
        var select = document.getElementById("lga");
        var length = select.options.length;
        
        for (i = length-1; i >= 0; i--) {
            select.options[i] = null;
        }
        
        for (let index = 0; index < Object.keys(data).length; index++) {
            var option = document.createElement("option");
            option.text = data[index];
            option.value = data[index];
            x.add(option);
        }
    });
}
</script>