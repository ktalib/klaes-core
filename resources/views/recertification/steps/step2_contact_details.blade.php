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
                            <select
                                id="state"
                                name="state"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
                            >
                                <option value="">Select State</option>
                                <option value="Abia">Abia</option>
                                <option value="Adamawa">Adamawa</option>
                                <option value="Akwa Ibom">Akwa Ibom</option>
                                <option value="Anambra">Anambra</option>
                                <option value="Bauchi">Bauchi</option>
                                <option value="Bayelsa">Bayelsa</option>
                                <option value="Benue">Benue</option>
                                <option value="Borno">Borno</option>
                                <option value="Cross River">Cross River</option>
                                <option value="Delta">Delta</option>
                                <option value="Ebonyi">Ebonyi</option>
                                <option value="Edo">Edo</option>
                                <option value="Ekiti">Ekiti</option>
                                <option value="Enugu">Enugu</option>
                                <option value="FCT">Federal Capital Territory</option>
                                <option value="Gombe">Gombe</option>
                                <option value="Imo">Imo</option>
                                <option value="Jigawa">Jigawa</option>
                                <option value="Kaduna">Kaduna</option>
                                <option value="Kano" selected>Kano</option>
                                <option value="Katsina">Katsina</option>
                                <option value="Kebbi">Kebbi</option>
                                <option value="Kogi">Kogi</option>
                                <option value="Kwara">Kwara</option>
                                <option value="Lagos">Lagos</option>
                                <option value="Nasarawa">Nasarawa</option>
                                <option value="Niger">Niger</option>
                                <option value="Ogun">Ogun</option>
                                <option value="Ondo">Ondo</option>
                                <option value="Osun">Osun</option>
                                <option value="Oyo">Oyo</option>
                                <option value="Plateau">Plateau</option>
                                <option value="Rivers">Rivers</option>
                                <option value="Sokoto">Sokoto</option>
                                <option value="Taraba">Taraba</option>
                                <option value="Yobe">Yobe</option>
                                <option value="Zamfara">Zamfara</option>
                            </select>
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