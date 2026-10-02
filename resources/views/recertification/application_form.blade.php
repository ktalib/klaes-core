@include('recertification.css.form_css')
<!-- New Recertification Application Modal -->
<div id="new-recertification-modal" class="fixed inset-0 z-50 flex items-center justify-center modal-backdrop" style="display: none;">
  <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto fade-in">
    <!-- Header -->
    <div class="p-6 border-b border-gray-200">
      <div class="flex items-center justify-between">
        <div class="text-center flex-1">
          <div class="space-y-1">
            <div class="font-bold text-lg">KANO STATE GEOGRAPHIC INFORMATION SYSTEMS (KANGIS)</div>
            <div class="text-sm text-gray-600">MINISTRY OF LAND AND PHYSICAL PLANNING KANO STATE</div>
            <div class="text-sm font-semibold">APPLICATION FOR RE-CERTIFICATION OR RE-ISSUANCE OF C-of-O</div>
            <div class="text-xs text-gray-500">INDIVIDUAL FORM AR01-01</div>
          </div>
        </div>
        <button id="close-modal" class="text-gray-400 hover:text-gray-600 transition-colors ml-4">
          <i data-lucide="x" class="h-5 w-5"></i>
        </button>
      </div>
    </div>
    
    <!-- Step Indicator -->
    <div class="p-6 pb-0">
      <div class="step-indicator">
        <div id="step-1" class="step-circle active">1</div>
        <div id="line-1" class="step-line inactive"></div>
        <div id="step-2" class="step-circle inactive">2</div>
        <div id="line-2" class="step-line inactive"></div>
        <div id="step-3" class="step-circle inactive">3</div>
        <div id="line-3" class="step-line inactive"></div>
        <div id="step-4" class="step-circle inactive">4</div>
        <div id="line-4" class="step-line inactive"></div>
        <div id="step-5" class="step-circle inactive">5</div>
        <div id="line-5" class="step-line inactive"></div>
        <div id="step-6" class="step-circle inactive">6</div>
      </div>
    </div>
    
    <div class="p-6">
      <form id="recertification-form">
        
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
              <div class="grid grid-cols-12 gap-4">
                <div class="col-span-9 space-y-4">
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
                  
                  <div class="grid grid-cols-3 gap-4">
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
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div class="form-field">
                      <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                      <select
                        id="title"
                        name="title"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      >
                        <option value="">Select Title</option>
                        <option value="MR">MR</option>
                        <option value="MRS">MRS</option>
                        <option value="MISS">MISS</option>
                        <option value="DR">DR</option>
                        <option value="PROF">PROF</option>
                        <option value="ENG">ENG</option>
                        <option value="ARC">ARC</option>
                        <option value="ALHAJI">ALHAJI</option>
                        <option value="HAJIYA">HAJIYA</option>
                      </select>
                    </div>
                    
                    <div class="form-field">
                      <label for="occupation" class="block text-sm font-medium text-gray-700 mb-1">
                        Occupation <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        id="occupation"
                        name="occupation"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="OCCUPATION"
                      />
                      <div class="error-message">Occupation is required</div>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-3 gap-4">
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
                      <label for="stateOfOrigin" class="block text-sm font-medium text-gray-700 mb-1">
                        State of Origin <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        id="stateOfOrigin"
                        name="stateOfOrigin"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="STATE OF ORIGIN"
                      />
                      <div class="error-message">State of origin is required</div>
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div class="form-field">
                      <label for="lgaOfOrigin" class="block text-sm font-medium text-gray-700 mb-1">LGA of Origin</label>
                      <input
                        type="text"
                        id="lgaOfOrigin"
                        name="lgaOfOrigin"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="LGA OF ORIGIN"
                      />
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
                  
                  <div class="grid grid-cols-2 gap-4">
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
                
                <div class="col-span-3">
                  <div class="photo-upload-area">
                    <i data-lucide="camera" class="h-8 w-8 mb-2 text-gray-400"></i>
                    <div class="text-xs font-semibold mb-2">PASSPORT PHOTOGRAPH</div>
                    <div class="text-xs text-gray-500 mb-2">(2" X 2")</div>
                    <button type="button" class="inline-flex items-center justify-center rounded-md font-medium text-sm px-3 py-1.5 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50">
                      Upload Photo
                    </button>
                    <div class="text-xs text-red-600 mt-2">
                      NOTE: DO NOT put a staple pin over the face region of the photo
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Contact Details -->
        <div id="step-content-2" class="step-content hidden">
          <div class="bg-white border border-gray-200 rounded-lg">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold">SECTION A1 & A2: CONTACT DETAILS</h3>
            </div>
            <div class="p-4 space-y-6">
              <div>
                <h4 class="font-semibold mb-4">A1. CONTACT DETAILS OF APPLICANT:</h4>
                <div class="space-y-4">
                  <div class="grid grid-cols-3 gap-4">
                    <div class="form-field">
                      <label for="phoneNo" class="block text-sm font-medium text-gray-700 mb-1">
                        Phone No <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="tel"
                        id="phoneNo"
                        name="phoneNo"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="Phone Number"
                      />
                      <div class="error-message">Phone number is required</div>
                    </div>
                    
                    <div class="form-field">
                      <label for="whatsappPhoneNo" class="block text-sm font-medium text-gray-700 mb-1">Whatsapp Phone No</label>
                      <input
                        type="tel"
                        id="whatsappPhoneNo"
                        name="whatsappPhoneNo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="WhatsApp Number"
                      />
                    </div>
                    
                    <div class="form-field">
                      <label for="alternatePhoneNo" class="block text-sm font-medium text-gray-700 mb-1">Alternate Phone No</label>
                      <input
                        type="tel"
                        id="alternatePhoneNo"
                        name="alternatePhoneNo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="Alternate Number"
                      />
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div class="form-field">
                      <label for="addressLine1" class="block text-sm font-medium text-gray-700 mb-1">
                        Address Line 1 <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        id="addressLine1"
                        name="addressLine1"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="ADDRESS LINE 1"
                      />
                      <div class="error-message">Address line 1 is required</div>
                    </div>
                    
                    <div class="form-field">
                      <label for="addressLine2" class="block text-sm font-medium text-gray-700 mb-1">Address Line 2</label>
                      <input
                        type="text"
                        id="addressLine2"
                        name="addressLine2"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="ADDRESS LINE 2"
                      />
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-3 gap-4">
                    <div class="form-field">
                      <label for="cityTown" class="block text-sm font-medium text-gray-700 mb-1">
                        City/Town <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        id="cityTown"
                        name="cityTown"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="CITY/TOWN"
                      />
                      <div class="error-message">City/Town is required</div>
                    </div>
                    
                    <div class="form-field">
                      <label for="state" class="block text-sm font-medium text-gray-700 mb-1">
                        State <span class="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        id="state"
                        name="state"
                        required
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="STATE"
                      />
                      <div class="error-message">State is required</div>
                    </div>
                    
                    <div class="form-field">
                      <label for="emailAddress" class="block text-sm font-medium text-gray-700 mb-1">E-Mail Address</label>
                      <input
                        type="email"
                        id="emailAddress"
                        name="emailAddress"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="email@example.com"
                      />
                    </div>
                  </div>
                </div>
              </div>
              
              <div>
                <h4 class="font-semibold mb-4">A2: CONTACT DETAILS OF AUTHORISED REPRESENTATIVE:</h4>
                <div class="space-y-4">
                  <div class="grid grid-cols-4 gap-4">
                    <div class="form-field">
                      <label for="repSurname" class="block text-sm font-medium text-gray-700 mb-1">Surname</label>
                      <input
                        type="text"
                        id="repSurname"
                        name="repSurname"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="SURNAME"
                      />
                    </div>
                    
                    <div class="form-field">
                      <label for="repFirstName" class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                      <input
                        type="text"
                        id="repFirstName"
                        name="repFirstName"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="FIRST NAME"
                      />
                    </div>
                    
                    <div class="form-field">
                      <label for="repMiddleName" class="block text-sm font-medium text-gray-700 mb-1">Other Name</label>
                      <input
                        type="text"
                        id="repMiddleName"
                        name="repMiddleName"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="MIDDLE NAME"
                      />
                    </div>
                    
                    <div class="form-field">
                      <label for="repTitle" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                      <input
                        type="text"
                        id="repTitle"
                        name="repTitle"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="TITLE"
                      />
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div class="form-field">
                      <label for="repRelationship" class="block text-sm font-medium text-gray-700 mb-1">Relationship to Applicant/Designation</label>
                      <input
                        type="text"
                        id="repRelationship"
                        name="repRelationship"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="RELATIONSHIP/DESIGNATION"
                      />
                    </div>
                    
                    <div class="form-field">
                      <label for="repPhoneNo" class="block text-sm font-medium text-gray-700 mb-1">Phone No</label>
                      <input
                        type="tel"
                        id="repPhoneNo"
                        name="repPhoneNo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="Phone Number"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 3: Title Holder Details -->
        <div id="step-content-3" class="step-content hidden">
          <div class="bg-white border border-gray-200 rounded-lg">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold">SECTION B: TITLE HOLDER DETAILS</h3>
            </div>
            <div class="p-4 space-y-4">
              <div class="grid grid-cols-4 gap-4">
                <div class="form-field">
                  <label for="titleHolderSurname" class="block text-sm font-medium text-gray-700 mb-1">
                    Surname <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="titleHolderSurname"
                    name="titleHolderSurname"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="SURNAME"
                  />
                  <div class="error-message">Title holder surname is required</div>
                </div>
                
                <div class="form-field">
                  <label for="titleHolderFirstName" class="block text-sm font-medium text-gray-700 mb-1">
                    First Name <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="titleHolderFirstName"
                    name="titleHolderFirstName"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="FIRST NAME"
                  />
                  <div class="error-message">Title holder first name is required</div>
                </div>
                
                <div class="form-field">
                  <label for="titleHolderMiddleName" class="block text-sm font-medium text-gray-700 mb-1">Other Names</label>
                  <input
                    type="text"
                    id="titleHolderMiddleName"
                    name="titleHolderMiddleName"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="MIDDLE NAME"
                  />
                </div>
                
                <div class="form-field">
                  <label for="titleHolderTitle" class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                  <input
                    type="text"
                    id="titleHolderTitle"
                    name="titleHolderTitle"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="TITLE"
                  />
                </div>
              </div>
              
              <div class="form-field">
                <label for="cofoNumber" class="block text-sm font-medium text-gray-700 mb-1">
                  CofO No or RofO No <span class="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  id="cofoNumber"
                  name="cofoNumber"
                  required
                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                  placeholder="CERTIFICATE NUMBER"
                />
                <div class="error-message">Certificate number is required</div>
              </div>
              
              <div class="border-t pt-4">
                <h4 class="font-semibold mb-4">SECTION B2. TITLE REGISTRATION DETAILS:</h4>
                <div class="grid grid-cols-4 gap-4 mb-4">
                  <div class="form-field">
                    <label for="registrationNo" class="block text-sm font-medium text-gray-700 mb-1">Registration No</label>
                    <input
                      type="text"
                      id="registrationNo"
                      name="registrationNo"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="REG NO"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="registrationVolume" class="block text-sm font-medium text-gray-700 mb-1">Volume</label>
                    <input
                      type="text"
                      id="registrationVolume"
                      name="registrationVolume"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="VOLUME"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="registrationPage" class="block text-sm font-medium text-gray-700 mb-1">Page</label>
                    <input
                      type="text"
                      id="registrationPage"
                      name="registrationPage"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="PAGE"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="registrationNumber" class="block text-sm font-medium text-gray-700 mb-1">No.</label>
                    <input
                      type="text"
                      id="registrationNumber"
                      name="registrationNumber"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="NO"
                    />
                  </div>
                </div>
                
                <div class="space-y-4">
                  <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Plot Ownership: Is the Applicant the original owner of the Plot? <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-4">
                      <label class="radio-item">
                        <input type="radio" name="isOriginalOwner" value="yes" required />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Yes</span>
                      </label>
                      <label class="radio-item">
                        <input type="radio" name="isOriginalOwner" value="no" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">No</span>
                      </label>
                    </div>
                    <div class="error-message">Please select plot ownership status</div>
                  </div>
                  
                  <div id="ownership-details" class="space-y-4 border-l-4 border-blue-200 pl-4 hidden">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">
                        If No, provide details of legal transaction through which the Plot was acquired:
                      </label>
                      <div class="mt-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">i. Registered Instrument(s)</label>
                        <div class="grid grid-cols-2 gap-2">
                          <label class="radio-item">
                            <input type="radio" name="instrumentType" value="deed-sub-lease" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Deed of Sub - Lease</span>
                          </label>
                          <label class="radio-item">
                            <input type="radio" name="instrumentType" value="deed-assignment" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Deed of Assignment</span>
                          </label>
                          <label class="radio-item">
                            <input type="radio" name="instrumentType" value="deed-gift" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Deed of Gift</span>
                          </label>
                          <label class="radio-item">
                            <input type="radio" name="instrumentType" value="power-attorney" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Power of Attorney</span>
                          </label>
                          <label class="radio-item">
                            <input type="radio" name="instrumentType" value="devolution-order" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Devolution Order</span>
                          </label>
                          <label class="radio-item">
                            <input type="radio" name="instrumentType" value="others" />
                            <div class="radio-circle"></div>
                            <span class="text-sm">Others, please specify:</span>
                          </label>
                        </div>
                      </div>
                    </div>
                    
                    <div class="form-field">
                      <label for="titleHolderName" class="block text-sm font-medium text-gray-700 mb-1">ii. Name of Title Holder</label>
                      <input
                        type="text"
                        id="titleHolderName"
                        name="titleHolderName"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                        placeholder="TITLE HOLDER NAME"
                      />
                    </div>
                  </div>
                  
                  <div class="grid grid-cols-2 gap-4">
                    <div class="form-field">
                      <label for="commencementDate" class="block text-sm font-medium text-gray-700 mb-1">Commencement Date</label>
                      <input
                        type="date"
                        id="commencementDate"
                        name="commencementDate"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      />
                    </div>
                    
                    <div class="form-field">
                      <label for="grantTerm" class="block text-sm font-medium text-gray-700 mb-1">Grant Term</label>
                      <input
                        type="text"
                        id="grantTerm"
                        name="grantTerm"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        placeholder="GRANT TERM"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 4: Mortgage & Encumbrance Details -->
        <div id="step-content-4" class="step-content hidden">
          <div class="bg-white border border-gray-200 rounded-lg">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold">MORTGAGE & ENCUMBRANCE DETAILS</h3>
            </div>
            <div class="p-4 space-y-4">
              <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Is the plot Encumbered? <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-4">
                  <label class="radio-item">
                    <input type="radio" name="isEncumbered" value="yes" required />
                    <div class="radio-circle"></div>
                    <span class="text-sm">Yes</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="isEncumbered" value="no" />
                    <div class="radio-circle"></div>
                    <span class="text-sm">No</span>
                  </label>
                </div>
                <div class="error-message">Please select encumbrance status</div>
              </div>
              
              <div id="encumbrance-reason" class="form-field hidden">
                <label for="encumbranceReason" class="block text-sm font-medium text-gray-700 mb-1">If yes, state the reason(s):</label>
                <textarea
                  id="encumbranceReason"
                  name="encumbranceReason"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                  placeholder="STATE REASON FOR ENCUMBRANCE"
                ></textarea>
              </div>
              
              <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Is there a subsisting Mortgage on the Property Rights over the Plot? <span class="text-red-500">*</span>
                </label>
                <div class="flex gap-4">
                  <label class="radio-item">
                    <input type="radio" name="hasMortgage" value="yes" required />
                    <div class="radio-circle"></div>
                    <span class="text-sm">Yes</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="hasMortgage" value="no" />
                    <div class="radio-circle"></div>
                    <span class="text-sm">No</span>
                  </label>
                </div>
                <div class="error-message">Please select mortgage status</div>
              </div>
              
              <div id="mortgage-details" class="space-y-4 border-l-4 border-orange-200 pl-4 hidden">
                <div class="form-field">
                  <label for="mortgageeName" class="block text-sm font-medium text-gray-700 mb-1">Name of Mortgagee Institution</label>
                  <input
                    type="text"
                    id="mortgageeName"
                    name="mortgageeName"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="MORTGAGEE INSTITUTION NAME"
                  />
                </div>
                
                <div class="grid grid-cols-4 gap-4">
                  <div class="form-field">
                    <label for="mortgageRegistrationNo" class="block text-sm font-medium text-gray-700 mb-1">Mortgage Registration No</label>
                    <input
                      type="text"
                      id="mortgageRegistrationNo"
                      name="mortgageRegistrationNo"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="REG NO"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="mortgageVolume" class="block text-sm font-medium text-gray-700 mb-1">Volume</label>
                    <input
                      type="text"
                      id="mortgageVolume"
                      name="mortgageVolume"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="VOLUME"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="mortgagePage" class="block text-sm font-medium text-gray-700 mb-1">Page</label>
                    <input
                      type="text"
                      id="mortgagePage"
                      name="mortgagePage"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="PAGE"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="mortgageNumber" class="block text-sm font-medium text-gray-700 mb-1">No.</label>
                    <input
                      type="text"
                      id="mortgageNumber"
                      name="mortgageNumber"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="NO"
                    />
                  </div>
                </div>
                
                <div class="form-field">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Mortgage Released?</label>
                  <div class="flex gap-4">
                    <label class="radio-item">
                      <input type="radio" name="mortgageReleased" value="yes" />
                      <div class="radio-circle"></div>
                      <span class="text-sm">Yes</span>
                    </label>
                    <label class="radio-item">
                      <input type="radio" name="mortgageReleased" value="no" />
                      <div class="radio-circle"></div>
                      <span class="text-sm">No</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 5: Plot Details -->
        <div id="step-content-5" class="step-content hidden">
          <div class="bg-white border border-gray-200 rounded-lg">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="building" class="h-5 w-5"></i>
                SECTION C: PLOT DETAILS
              </h3>
            </div>
            <div class="p-4 space-y-4">
              <div class="grid grid-cols-3 gap-4">
                <div class="form-field">
                  <label for="plotNumber" class="block text-sm font-medium text-gray-700 mb-1">
                    Plot Number or Piece of Land <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="plotNumber"
                    name="plotNumber"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="PLOT NUMBER"
                  />
                  <div class="error-message">Plot number is required</div>
                </div>
                
                <div class="form-field">
                  <label for="fileNumber" class="block text-sm font-medium text-gray-700 mb-1">
                    File Number <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="fileNumber"
                    name="fileNumber"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="FILE NUMBER"
                  />
                  <div class="error-message">File number is required</div>
                </div>
                
                <div class="form-field">
                  <label for="plotSize" class="block text-sm font-medium text-gray-700 mb-1">
                    Plot Size (Ha) <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="plotSize"
                    name="plotSize"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                    placeholder="PLOT SIZE"
                  />
                  <div class="error-message">Plot size is required</div>
                </div>
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div class="form-field">
                  <label for="layoutDistrict" class="block text-sm font-medium text-gray-700 mb-1">
                    Layout/District <span class="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id="layoutDistrict"
                    name="layoutDistrict"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                    placeholder="LAYOUT/DISTRICT"
                  />
                  <div class="error-message">Layout/District is required</div>
                </div>
                
                <div class="form-field">
                  <label for="lga" class="block text-sm font-medium text-gray-700 mb-1">
                    LGA <span class="text-red-500">*</span>
                  </label>
                  <select
                    id="lga"
                    name="lga"
                    required
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                  >
                    <option value="">Select LGA</option>
                    <option value="Kano Municipal">Kano Municipal</option>
                    <option value="Fagge">Fagge</option>
                    <option value="Gwale">Gwale</option>
                    <option value="Dala">Dala</option>
                    <option value="Tarauni">Tarauni</option>
                    <option value="Nassarawa">Nassarawa</option>
                  </select>
                  <div class="error-message">LGA is required</div>
                </div>
              </div>
              
              <div class="form-field">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Current Land Use <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-4 gap-2">
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="residential" required />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Residential</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="commercial" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Commercial</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="industrial" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Industrial</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="agricultural" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Agricultural</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="educational" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Educational</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="religious" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Religious</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="public" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Public</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="ngo" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">NGO</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="social" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Social (Hospital, etc)</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="petrol-station" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">Petrol Filling Station</span>
                  </label>
                  <label class="radio-item">
                    <input type="radio" name="currentLandUse" value="gkn" />
                    <div class="radio-circle"></div>
                    <span class="text-xs">GKN</span>
                  </label>
                </div>
                <div class="error-message">Current land use is required</div>
              </div>
              
              <div class="grid grid-cols-2 gap-4">
                <div class="form-field">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Plot Status <span class="text-red-500">*</span>
                  </label>
                  <div class="flex gap-4">
                    <label class="radio-item">
                      <input type="radio" name="plotStatus" value="developed" required />
                      <div class="radio-circle"></div>
                      <span class="text-sm">Developed</span>
                    </label>
                    <label class="radio-item">
                      <input type="radio" name="plotStatus" value="undeveloped" />
                      <div class="radio-circle"></div>
                      <span class="text-sm">Undeveloped</span>
                    </label>
                  </div>
                  <div class="error-message">Plot status is required</div>
                </div>
                
                <div class="form-field">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Mode of Allocation <span class="text-red-500">*</span>
                  </label>
                  <div class="flex gap-4">
                    <label class="radio-item">
                      <input type="radio" name="modeOfAllocation" value="direct-allocation" required />
                      <div class="radio-circle"></div>
                      <span class="text-sm">Direct Allocation</span>
                    </label>
                    <label class="radio-item">
                      <input type="radio" name="modeOfAllocation" value="resettlement" />
                      <div class="radio-circle"></div>
                      <span class="text-sm">Resettlement</span>
                    </label>
                  </div>
                  <div class="error-message">Mode of allocation is required</div>
                </div>
              </div>
              
              <div class="form-field">
                <label for="startDate" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input
                  type="date"
                  id="startDate"
                  name="startDate"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Step 6: Payment & Terms -->
        <div id="step-content-6" class="step-content hidden">
          <div class="bg-white border border-gray-200 rounded-lg">
            <div class="p-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold flex items-center gap-2">
                <i data-lucide="credit-card" class="h-5 w-5"></i>
                SECTION D: PAYMENT & TERMS
              </h3>
            </div>
            <div class="p-4 space-y-6">
              <div>
                <h4 class="font-semibold mb-4">D2: PAYMENT INFORMATION SECTION</h4>
                <div class="grid grid-cols-3 gap-4">
                  <div class="form-field">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Method of Payment <span class="text-red-500">*</span>
                    </label>
                    <div class="flex gap-4">
                      <label class="radio-item">
                        <input type="radio" name="paymentMethod" value="online" required />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Online</span>
                      </label>
                      <label class="radio-item">
                        <input type="radio" name="paymentMethod" value="pos" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">PoS</span>
                      </label>
                      <label class="radio-item">
                        <input type="radio" name="paymentMethod" value="bank" />
                        <div class="radio-circle"></div>
                        <span class="text-sm">Bank</span>
                      </label>
                    </div>
                    <div class="error-message">Payment method is required</div>
                  </div>
                  
                  <div class="form-field">
                    <label for="receiptNo" class="block text-sm font-medium text-gray-700 mb-1">Receipt No/Teller No</label>
                    <input
                      type="text"
                      id="receiptNo"
                      name="receiptNo"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                      placeholder="RECEIPT/TELLER NUMBER"
                    />
                  </div>
                  
                  <div class="form-field">
                    <label for="bankName" class="block text-sm font-medium text-gray-700 mb-1">Bank Name</label>
                    <input
                      type="text"
                      id="bankName"
                      name="bankName"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 uppercase"
                      placeholder="BANK NAME"
                    />
                  </div>
                </div>
              </div>
              
              <div class="border-t pt-4">
                <h4 class="font-semibold mb-4">Declaration & Terms & Conditions of Service</h4>
                <div class="space-y-4 text-sm">
                  <div class="bg-yellow-50 p-4 rounded-lg">
                    <p class="font-semibold mb-2">Terms & Conditions:</p>
                    <ul class="space-y-2 text-xs">
                      <li>
                        a. It is a criminal offence to provide false information or make misleading inputs when
                        completing this form.
                      </li>
                      <li>
                        b. You may be prosecuted if we find out that your Certificate of Occupancy or Land of Grant
                        (RofO) is Fake or Falsified.
                      </li>
                      <li>
                        c. Payment of Re-certification processing fee is non-refundable and does not guarantee issuance
                        of new Digital Certificate.
                      </li>
                    </ul>
                  </div>
                  
                  <div class="form-field">
                    <label class="checkbox-item">
                      <input type="checkbox" id="agreeTerms" name="agreeTerms" required />
                      <div class="checkbox-box"></div>
                      <span class="text-sm">
                        I agree with the above terms and conditions of service <span class="text-red-500">*</span>
                      </span>
                    </label>
                    <div class="error-message">You must agree to the terms and conditions</div>
                  </div>
                </div>
              </div>
              
              <div class="border-2 border-dashed border-gray-300 rounded-lg p-6">
                <div class="text-center mb-4">
                  <div class="font-semibold">Signature/Thumb Print</div>
                  <div class="text-xs text-gray-500">
                    (please sign/Thumb Print clearly within the box provided)
                  </div>
                </div>
                <div class="signature-area">
                  <span class="text-gray-500">Signature Area</span>
                </div>
              </div>
              
              <div class="bg-blue-50 p-4 rounded-lg">
                <h5 class="font-semibold mb-2">Contact Information:</h5>
                <div class="text-xs space-y-1">
                  <p>KANGIS Complex 2 Dr Bala Muhammad Way, Nassarawa G.R.A. Kano.</p>
                  <p>Tel: +234 (0)900 0000 00, +234 (0) 900 000 000 +234 (0) 810 0000 000</p>
                  <p>Email: recertification@kangis.gov.ng, info@kangis.gov.ng, support@kangis.gov.ng</p>
                  <p>Website: https://kangis.gov.ng</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>

      <!-- Form Navigation -->
      <div class="flex justify-between pt-4 border-t">
        <button
          type="button"
          id="prev-btn"
          class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer bg-transparent border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Previous
        </button>
        
        <button
          type="button"
          id="next-btn"
          class="inline-flex items-center justify-center rounded-md font-medium text-sm px-4 py-2 transition-all cursor-pointer border-0 bg-blue-600 text-white hover:bg-blue-700 gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <span class="next-text">Next</span>
          <div class="loading-spinner hidden"></div>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Toast Notifications -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
  <!-- Toast messages will be inserted here -->
</div>
