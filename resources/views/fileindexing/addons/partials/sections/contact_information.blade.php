<template x-for="(param, index) in fileParams" :key="param.id">
    <div x-show="index === activeTab" class="rounded-lg border border-gray-200 bg-white p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-2 min-w-0">
                <i data-lucide="user-circle" class="h-4 w-4 text-blue-600 flex-shrink-0"></i>
                <h4 class="text-sm font-bold uppercase tracking-wide text-gray-700 whitespace-nowrap">
                    Personal Details
                </h4>
                <span x-show="fileParams.length > 1" 
                      x-text="'(File ' + (index+1) + '/' + fileParams.length + ')'" 
                      class="text-xs font-medium text-gray-400 whitespace-nowrap ml-1"></span>
            </div>

            <div class="flex items-center gap-3">
                <!-- Apply to All Files in Batch Toggle -->
                <div x-show="indexing_type === 'Block' && fileParams.length > 1" 
                     class="flex items-center gap-2 px-3 py-1.5 bg-blue-50 border border-blue-100 rounded-lg hover:bg-blue-100 transition-colors shadow-sm cursor-pointer"
                     @click="$refs.personalCheck.click()">
                    <input type="checkbox" id="applyPersonalToAllBatch" x-model="applyPersonalToAll" x-ref="personalCheck"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
                           @click.stop>
                    <label for="applyPersonalToAllBatch" class="text-[11px] font-bold text-blue-700 cursor-pointer select-none whitespace-nowrap">
                        Apply to All
                    </label>
                </div>

                <div class="flex items-center gap-1.5" x-show="fileParams.length > 1">
                    <button type="button" @click="activeTab = Math.max(0, activeTab - 1)" 
                        :disabled="activeTab === 0"
                        class="p-1.5 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all border border-indigo-100"
                        title="Previous File">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    
                    <button type="button" @click="activeTab = Math.min(fileParams.length - 1, activeTab + 1)"
                        :disabled="activeTab === fileParams.length - 1"
                        class="p-1.5 rounded-md text-indigo-600 bg-indigo-50 hover:bg-indigo-100 disabled:opacity-30 disabled:cursor-not-allowed transition-all border border-indigo-100"
                        title="Next File">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Apply to Batch Button (shown when toggle is enabled) -->
        <div x-show="indexing_type === 'Block' && fileParams.length > 1 && applyPersonalToAll" class="mb-6">
            <button type="button" 
                    @click="applyPersonalDetailsToAllFiles()" 
                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition-all flex items-center justify-center space-x-2">
                <i data-lucide="copy" class="w-4 h-4"></i>
                <span>Apply Current Personal Details to All <span x-text="fileParams.length"></span> Files</span>
            </button>
        </div>

        <!-- Gender -->
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Gender <span class="text-red-500">*</span></label>
            {{-- id/name are built per file by Alpine, so the component's static ones
                 are switched off with :name="null" / :id="null" and the `::` bindings
                 below are passed straight through. The gender-select class is what
                 create-indexing-dialog.js validates on. --}}
            <x-gender-select :name="null" :id="null"
                    ::id="'gender-' + index"
                    ::name="'gender[' + index + ']'"
                    x-model="param.gender"
                    class="gender-select block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    required />
        </div>

        <!-- Date of Birth -->
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Date of Birth</label>
            <input type="date"
                   :id="'dob-' + index"
                   :name="'dob[' + index + ']'"
                   x-model="param.dob"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
        </div>

        <!-- NIN and TIN in Grid -->
        <div class="form-grid-2">
            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">NIN (National ID)</label>
                <input type="text" 
                       :id="'nin-' + index" 
                       :name="'nin[' + index + ']'" 
                       x-model="param.nin"
                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                       placeholder="13 digits"
                       maxlength="13"
                       pattern="[0-9]*"
                       inputmode="numeric">
                <p class="mt-1 text-xs text-gray-500">National Identification Number (13 digits)</p>
            </div>

            <div class="form-group">
                <label class="block text-sm font-medium text-gray-700 mb-2">TIN (Tax ID)</label>
                <input type="text" 
                       :id="'tin-' + index" 
                       :name="'tin[' + index + ']'" 
                       x-model="param.tin"
                       class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                       placeholder="Tax Identification Number">
            </div>
        </div>

        <!-- RC NO (Registration Certificate - Corporate Only) -->
        <div class="form-group" :id="'rc-no-group-' + index" style="display: none;" x-show="param.customer_type === 'Corporate'">
            <label class="block text-sm font-medium text-gray-700 mb-2">RC NO (Registration Certificate)</label>
            <input type="text" 
                   :id="'rc-no-' + index" 
                   :name="'rc_no[' + index + ']'" 
                   x-model="param.rc_no"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                   placeholder="For Corporate entities only">
            <p class="mt-1 text-xs text-gray-500">Required for Corporate file type</p>
        </div>

        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
            <input type="text" 
                   :id="'phone-' + index" 
                   :name="'phone[' + index + ']'" 
                   x-model="param.phone"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                   placeholder="e.g., 08012345678"
                   maxlength="15"
                   pattern="[0-9]*"
                   inputmode="numeric">
            <p class="mt-1 text-xs text-gray-500">Enter digits starting with 0 (e.g., 08012345678)</p>
        </div>

        <!-- Residential Address -->
        <div class="form-group">
            <label class="block text-sm font-medium text-gray-700 mb-2">Residential Address <span class="text-red-500">*</span></label>
            <textarea 
                   :id="'residence_address-' + index" 
                   :name="'residence_address[' + index + ']'" 
                   x-model="param.residence_address"
                   rows="3"
                   class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" 
                   placeholder="Enter residential address"
                   required></textarea>
        </div>
    </div>
</template>
