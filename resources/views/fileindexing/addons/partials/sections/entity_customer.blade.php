<div class="form-section hidden" id="entity-customer-section">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4 min-w-0">
            <div class="flex items-center gap-2">
                <i data-lucide="users" class="h-5 w-5 text-indigo-600 flex-shrink-0"></i>
                <h3 class="form-section-title whitespace-nowrap" style="margin-bottom: 0;">Entity &amp; Customer</h3>
            </div>
            
            <!-- Apply to All Files in Batch Toggle -->
            <div x-show="indexing_type === 'Block' && fileParams.length > 1" 
                 class="flex items-center gap-2 px-3 py-1.5 bg-indigo-50 border border-indigo-100 rounded-lg hover:bg-indigo-100 transition-colors shadow-sm cursor-pointer"
                 @click="$refs.entityCheck.click()">
                <input type="checkbox" id="applyEntityToAllBatch" x-model="applyEntityToAll" x-ref="entityCheck"
                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer"
                       @click.stop>
                <label for="applyEntityToAllBatch" class="text-[11px] font-bold text-indigo-700 cursor-pointer select-none whitespace-nowrap">
                    Apply to All
                </label>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="entity-customer-refresh-btn" class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors">
                <i data-lucide="refresh-cw" class="h-4 w-4" id="entity-customer-refresh-icon"></i>
                <span id="entity-customer-refresh-label">Lookup</span>
            </button>
        </div>
    </div>

    <template x-for="(param, index) in fileParams" :key="param.id">
        <div x-show="index === activeTab">
            <div class="flex items-center justify-between mb-6 bg-gray-50/50 p-2 rounded-lg border border-gray-100" x-show="fileParams.length > 1">
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Entry</span>
                    <div class="px-2 py-0.5 bg-white border border-gray-200 rounded text-xs font-bold text-indigo-600 shadow-sm">
                        <span x-text="activeTab + 1"></span> / <span x-text="fileParams.length"></span>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <button type="button" @click="activeTab = Math.max(0, activeTab - 1)" 
                        :disabled="activeTab === 0"
                        class="p-1.5 rounded-md text-indigo-600 bg-white border border-gray-200 hover:bg-indigo-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" @click="activeTab = Math.min(fileParams.length - 1, activeTab + 1)"
                        :disabled="activeTab === fileParams.length - 1"
                        class="p-1.5 rounded-md text-indigo-600 bg-white border border-gray-200 hover:bg-indigo-50 disabled:opacity-30 disabled:cursor-not-allowed transition-all shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                </div>
            </div>

            <!-- Apply to Batch Button (shown when toggle is enabled) -->
            <div x-show="indexing_type === 'Block' && fileParams.length > 1 && applyEntityToAll" class="mb-6">
                <button type="button" 
                        @click="applyEntityDetailsToAllFiles()" 
                        class="w-full px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-md shadow-sm transition-all flex items-center justify-center space-x-2">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                    <span>Apply Current Entity & Customer to All <span x-text="fileParams.length"></span> Files</span>
                </button>
            </div>

            <input type="hidden" :name="'entity_id[' + index + ']'" value="">
            <input type="hidden" :name="'customer_id[' + index + ']'" value="">
            <input type="hidden" :name="'customer_status[' + index + ']'" value="Active">
            
            <div class="form-grid-2 entity-customer-cards hidden">
                <!-- Entity Details Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                        <i data-lucide="building-2" class="h-4 w-4 text-indigo-600"></i>
                        Entity Details
                    </h4>

                    <div class="space-y-4">
                        <!-- Entity Type -->
                        <div class="form-group">
                            <span :id="'entity-type-group-label-' + index" class="block text-sm font-medium text-gray-700 mb-3">Entity Type</span>
                            <div class="flex flex-wrap gap-4" role="radiogroup" :aria-labelledby="'entity-type-group-label-' + index">
                                <label :for="'entity-type-individual-' + index" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :id="'entity-type-individual-' + index" :name="'entity_type[' + index + ']'" value="Individual" x-model="param.entity_type" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Individual</span>
                                </label>
                                <label :for="'entity-type-corporate-' + index" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :id="'entity-type-corporate-' + index" :name="'entity_type[' + index + ']'" value="Corporate" x-model="param.entity_type" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Corporate</span>
                                </label>
                                <label :for="'entity-type-multiple-' + index" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :id="'entity-type-multiple-' + index" :name="'entity_type[' + index + ']'" value="Multiple Owners" x-model="param.entity_type" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Multiple Owners</span>
                                </label>
                            </div>
                        </div>

                        <!-- Entity Name -->
                        <div class="form-group">
                            <label :for="'entity-name-' + index" class="block text-sm font-medium text-gray-700 mb-2">Entity Name</label>
                            <input type="text" :id="'entity-name-' + index" :name="'entity_name[' + index + ']'" x-model="param.entity_name" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Detected entity name" autocomplete="off">
                        </div>

                        <!-- Entity Addresses -->
                        <div class="border-t border-gray-200 pt-4">
                            <div class="form-group">
                                <label :for="'entity-physical-address-' + index" class="block text-sm font-medium text-gray-700 mb-2">Residential Address</label>
                                <textarea :id="'entity-physical-address-' + index" :name="'entity_physical_address[' + index + ']'" x-model="param.entity_physical_address" rows="2" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="Physical or mailing address for the entity"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Details Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h4 class="mb-6 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-gray-700">
                        <i data-lucide="user-check" class="h-4 w-4 text-indigo-600"></i>
                        Customer Details
                    </h4>

                    <div class="space-y-6">
                        <!-- Customer Type -->
                        <div class="form-group">
                            <span :id="'customer-type-group-label-' + index" class="block text-sm font-medium text-gray-700 mb-3">Customer Type</span>
                            <div class="flex flex-wrap gap-4" role="radiogroup" :aria-labelledby="'customer-type-group-label-' + index">
                                <label :for="'customer-type-individual-' + index" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :id="'customer-type-individual-' + index" :name="'customer_type[' + index + ']'" value="Individual" x-model="param.customer_type" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Individual</span>
                                </label>
                                <label :for="'customer-type-corporate-' + index" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :id="'customer-type-corporate-' + index" :name="'customer_type[' + index + ']'" value="Corporate" x-model="param.customer_type" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Corporate</span>
                                </label>
                                <label :for="'customer-type-multiple-' + index" class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" :id="'customer-type-multiple-' + index" :name="'customer_type[' + index + ']'" value="Multiple Owners" x-model="param.customer_type" class="h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                    <span class="text-sm text-gray-700">Multiple Owners</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group md:col-span-2">
                                <label :for="'customer-name-' + index" class="block text-sm font-medium text-gray-700 mb-2">Customer Name</label>
                                <input type="text" :id="'customer-name-' + index" :name="'customer_name[' + index + ']'" x-model="param.customer_name" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="Detected customer name" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label :for="'customer-account-no-' + index" class="block text-sm font-medium text-gray-700 mb-2">Account Number</label>
                                <input type="text" :id="'customer-account-no-' + index" :name="'customer_account_no[' + index + ']'" x-model="param.customer_account_no" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. ACC-00123" autocomplete="off">
                            </div>
                            <div class="form-group">
                                <label :for="'customer-code-' + index" class="block text-sm font-medium text-gray-700 mb-2">Customer Code</label>
                                <input type="text" :id="'customer-code-' + index" :name="'customer_code[' + index + ']'" x-model="param.customer_code" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="System generated code" autocomplete="off">
                            </div>
                        </div>

                        <div class="space-y-4 border-t border-gray-200 pt-4">
                            <div class="form-group">
                                <label :for="'customer-property-address-' + index" class="block text-sm font-medium text-gray-700 mb-2">Property Address</label>
                                <textarea :id="'customer-property-address-' + index" :name="'property_address[' + index + ']'" x-model="param.property_address" rows="2" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="Property address associated with this customer"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
