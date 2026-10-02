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
                    CofO No or RofO No
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
    <label for="serial_no" class="block text-sm font-medium text-gray-700 mb-1">Serial No.  <span class="text-red-500">*</span></label>
    <input
        type="text"
        id="serial_no"
        name="serial_no"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
        placeholder="Serial No"
    />
</div>

<div class="form-field">
    <label for="registrationPage" class="block text-sm font-medium text-gray-700 mb-1">Page No </label>
    <input
        type="text"
        id="registrationPage"
        name="registrationPage"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100"
        placeholder="PAGE NO"
        disabled
    />
</div>

<div class="form-field">
    <label for="registrationVolume" class="block text-sm font-medium text-gray-700 mb-1">Volume No</label>
    <input
        type="text"
        id="registrationVolume"
        name="registrationVolume"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
        placeholder="VOLUME NO"
    />
</div>

<div class="form-field">
    <label for="registrationNo" class="block text-sm font-medium text-gray-700 mb-1">Registration Particulars</label>
    <input
        type="text"
        id="registrationNo"
        name="registrationNo"
        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-gray-100"
        placeholder="Registration Particulars"
        disabled
    />
</div>
                 
<div class="grid grid-cols-2 gap-4">
                    <div class="form-field">
                        <label for="registrationDate" class="block text-sm font-medium text-gray-700 mb-1">Registration Date</label>
                        <input
                            type="date"
                            id="registrationDate"
                            name="registrationDate"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        />
                    </div>
                    
                    <div class="form-field">
                        <label for="registrationTime" class="block text-sm font-medium text-gray-700 mb-1">Registration Time</label>
                        <input
                            type="time"
                            id="registrationTime"
                            name="registrationTime"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                        />
                    </div>
                </div>

<script>
    const serialNoInput = document.getElementById("serial_no");
    const pageNoInput = document.getElementById("registrationPage");
    const volumeNoInput = document.getElementById("registrationVolume");
    const regNoInput = document.getElementById("registrationNo");
    const registrationDateInput = document.getElementById("registrationDate");
    const registrationTimeInput = document.getElementById("registrationTime");

    function updateFields() {
        // Page No always matches Serial No
        pageNoInput.value = serialNoInput.value;

        // Registration No = Serial/Page/Volume
        regNoInput.value = `${serialNoInput.value}/${pageNoInput.value}/${volumeNoInput.value}`;
    }

    // Set current date and time
    function setCurrentDateTime() {
        const now = new Date();
        
        // Set current date (YYYY-MM-DD format)
        const currentDate = now.toISOString().split('T')[0];
        registrationDateInput.value = currentDate;
        
        // Set current time (HH:MM format)
        const currentTime = now.toTimeString().slice(0, 5);
        registrationTimeInput.value = currentTime;
    }

    // Initialize current date and time when page loads
    document.addEventListener('DOMContentLoaded', setCurrentDateTime);

    serialNoInput.addEventListener("input", updateFields);
    volumeNoInput.addEventListener("input", updateFields);
</script>

                  
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