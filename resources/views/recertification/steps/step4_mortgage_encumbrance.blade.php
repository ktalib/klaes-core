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