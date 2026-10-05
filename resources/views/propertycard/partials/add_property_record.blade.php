<div id="property-form-dialog" class="dialog-overlay hidden">
    <div class="dialog-content property-form-content">
        <div class="flex justify-between items-center mb-4">
            <h2 id="form-title" class="text-xl font-bold" data-form-title="property">Add New Property Record</h2>
            <div class="flex items-center gap-2">
                <button type="button" id="refresh-property-form"
                    class="inline-flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-100">
                    <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                    <span>Refresh</span>
                </button>
                <button id="close-property-form" class="text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
        <div class="pra-property-form" data-pra-form
            data-temp-endpoint="{{ route('property-records.temp-file-number') }}"
            data-instrument-types-url="{{ url('api/instrument-types') }}"
            data-existing-records-url="{{ route('legalsearch.existingRecords') }}">
            <form id="property-record-form" action="{{ route('property-records.store') }}" method="POST"
                data-role="pra-form" data-update-base="{{ url('property-records') }}">
                @csrf
                <input type="hidden" name="property_id" id="property_id" value="">
                <input type="hidden" name="form_action" id="form_action" value="add">
                <input type="hidden" name="record_mode" value="" data-model="formMode">
                <input type="hidden" name="has_official_file_number" value="1" data-model="hasOfficialFileNumber">
                <input type="hidden" name="temp_fileno" value="" data-model="tempFileNumber">

                <div class="hidden mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                    data-role="update-banner">
                    <span class="font-semibold">Update Mode:</span>
                    Updating existing PRA record <span data-role="update-label" class="font-mono"></span>.
                </div>

                <div class="space-y-4 py-2 flex-1 max-h-[75vh] overflow-y-auto pr-1">
                    <!-- Top section with two columns -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Left column - File Number then Transaction Type -->
                        <div class="form-section" data-section="transaction-type">
                            <!-- File Number -->
                            <div class="space-y-1">
                                @include('propertycard.partials.add_pra.file_number_section')
                            </div>

                            <div class="space-y-1 mt-3">
                                <label for="transactionType-record" class="text-sm">Instrument Type</label>
                                <select id="transactionType-record" name="transactionType"
                                    class="form-select text-sm transaction-type-select" data-model="transactionType"
                                    data-role="transaction-type" data-placeholder="Select type">
                                    <option value="">Select type</option>
                                    {{-- The catalogue is fetched into this select at load; 'Other'
                                         is written here as well so the way out of the catalogue
                                         survives a failed instrument-types lookup. --}}
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            {{-- 'Other' is a prompt, not an instrument. What is typed here is
                                 submitted as the instrument type in its place -- a record whose
                                 type reads 'Other' names nothing on a timeline or in a search.
                                 Shown, required and cleared by PraFormController. --}}
                            <div class="space-y-1 mt-3 hidden" data-role="other-transaction-type-group">
                                <label for="other_transaction_type" class="text-sm">
                                    Specify Instrument Type <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="other_transaction_type" name="other_transaction_type"
                                    maxlength="255" class="form-input text-sm w-full"
                                    placeholder="Name the instrument as it reads on the document">
                                <p class="text-xs text-gray-500">Saved as the instrument type in place of "Other".</p>
                            </div>

                            <div class="mt-4" data-role="related-file-number-section">
                                <label for="related_file_number" class="block text-sm font-medium text-gray-700 mb-2">
                                    Related File Number (Optional)
                                </label>
                                <div class="flex gap-2">
                                    <div class="relative flex-1">
                                        <input type="text" id="related_file_number" name="related_file_number"
                                            class="w-full p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                            placeholder="Enter related file number if applicable" readonly>
                                        <div
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                                                </path>
                                            </svg>
                                        </div>
                                    </div>
                                    <button type="button"
                                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-blue-600 border border-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        onclick="openRelatedFileNumberModal('related_file_number')">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M12.9 14.32a5 5 0 01-7.07 0l-2.83-2.83a5 5 0 117.07-7.07l.7.7a1 1 0 01-1.42 1.42l-.7-.7a3 3 0 00-4.24 4.24l2.83 2.83a3 3 0 004.24 0 1 1 0 011.42 1.42z" />
                                            <path d="M17.66 6.34l-1.41 1.41-1.41-1.41 1.41-1.41a1 1 0 011.41 1.41z" />
                                        </svg>
                                        Select
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Link this property to another file number if
                                    needed</p>
                            </div>

                            <!-- Matching Records Results -->
                            <div id="matching-records-list" class="mt-4 hidden" data-role="matching-records-container">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-[10px] font-bold text-blue-600 uppercase tracking-tight flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        Existing Records for this File <span data-role="matching-records-count"
                                            class="ml-1"></span>
                                    </div>
                                    <button type="button"
                                        data-role="matching-records-history-btn"
                                        data-allow-when-locked="1"
                                        class="inline-flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold text-blue-700 bg-white border border-blue-300 rounded hover:bg-blue-50 shadow-sm">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        View File History
                                    </button>
                                </div>
                                <div class="space-y-2 max-h-64 overflow-y-auto pr-1 custom-scrollbar"
                                    data-role="matching-records-results">
                                    <!-- Results will be injected here via JS -->
                                </div>
                            </div>
                        </div>





                        @include('propertycard.partials.add_pra.property_description_section')
                    </div>

                    @include('propertycard.partials.add_pra.instrument_section')

                    <input type="hidden" name="is_mapped" value="0" data-model="isMapped" />
                    <div class="mt-2 rounded-md p-2 bg-yellow-50 border border-yellow-200 text-sm text-yellow-800 hidden"
                        data-role="mortgage-warning">
                        This file number has a mortgage.
                    </div>

                    {{-- ===== Existing Records for this File =====
                         Everything the file already holds across PRA, File History,
                         Deeds Registration and CofO — the same shape the Legal Search
                         Timeline shows. Rendered here so a duplicate is visible at the
                         point of entry; rows matching what is being typed are
                         highlighted and raise the banner below.
                         Populated by PraFormController.loadExistingRecordsTable(). --}}
                    <div class="mt-4 hidden" data-role="existing-records-section">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Existing Records for this File
                                <span data-role="existing-records-count"
                                      class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600"></span>
                            </h3>
                            <span class="text-[11px] text-gray-400" data-role="existing-records-hint">
                                Check here before saving to avoid a duplicate entry.
                            </span>
                        </div>

                        {{-- Deed of Purchase only: the officer picks which transaction the
                             purchase follows on from, and its Party 1 is carried into the
                             form. Never assumed — nothing is copied until a row is picked. --}}
                        <div class="hidden mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-[11px] font-medium text-red-700"
                             data-role="existing-records-picker-note">
                            Select the transaction this purchase follows on from and click
                            <span class="font-semibold">Use as Party 1</span> to carry its Party 1
                            into the form.
                        </div>

                        {{-- Duplicate flag — raised live as the instrument type, registration
                             particulars or transaction date are filled in. --}}
                        <div class="hidden mb-2 rounded-md border px-3 py-2 text-xs"
                             data-role="duplicate-warning"></div>

                        {{-- The rows come from the Legal Search report engine, the same one
                             behind the Property Timeline — a few seconds per file, so say
                             what is being waited on rather than showing a bare spinner. --}}
                        <div data-role="existing-records-loading" class="hidden py-4 text-center text-xs text-gray-400">
                            Building this file's timeline…
                        </div>

                        <div class="border border-gray-200 rounded-md overflow-hidden">
                            <div class="overflow-x-auto max-h-72 overflow-y-auto">
                                <table class="w-full text-[11px]">
                                    <thead class="bg-gray-50 sticky top-0 z-10">
                                        <tr class="text-left text-gray-500">
                                            <th class="px-2 py-1.5 font-medium" style="min-width:34px;">S/N</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:80px;">Source</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:130px;">File No</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:150px;">Instrument Type</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:110px;">Party 1</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:110px;">Party 2</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:90px;">Reg Particulars</th>
                                            <th class="px-2 py-1.5 font-medium" style="min-width:95px;">Date</th>
                                            {{-- Shown only during Deed of Purchase capture. --}}
                                            <th class="px-2 py-1.5 font-medium text-right hidden" data-col="use-party1"
                                                style="min-width:96px;">Use</th>
                                        </tr>
                                    </thead>
                                    <tbody data-role="existing-records-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    {{-- ===== /Existing Records for this File ===== --}}

                    <!-- Transaction Details Section -->
                    <div class="form-section hidden" data-section="transaction-details">
                        <h4 class="form-section-title">Transaction Details</h4>

                        <input type="hidden" name="party_1" data-model="party1">
                        <input type="hidden" name="party_2" data-model="party2">
                        <input type="hidden" name="party_3" data-model="party3">
                        <input type="hidden" name="party_4" data-model="party4">

                        <div class="flex flex-wrap gap-4 mb-3">
                            <label class="flex items-center gap-2 text-sm text-gray-600 hidden"
                                data-role="tripartite-toggle">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-indigo-600" value="1"
                                    name="tripartite_has_third" data-model="tripartiteHasThird">
                                <span data-role="tripartite-toggle-label">Include Co-Mortgagor (three-party agreement)</span>
                            </label>

                            <label class="flex items-center gap-2 text-sm text-gray-600 hidden"
                                data-role="fourth-party-toggle">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-purple-600" value="1"
                                    name="has_fourth_party" data-model="hasFourthParty">
                                <span data-role="fourth-party-toggle-label">Include Fourth Party</span>
                            </label>
                        </div>

                        <!-- Party Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-sm font-medium" data-role="party-label-first">
                                    <span data-role="label-text">First Party</span>
                                    {{-- Hidden for a Deed of Purchase, whose Party 1 may be left
                                         blank until the physical file is indexed. --}}
                                    <span class="text-red-500" data-role="party-required-first">*</span>
                                </label>
                                <input type="text" class="form-input text-sm" data-model="firstParty"
                                    data-name-key="firstParty" data-government-placeholder="KANO STATE GOVERNMENT"
                                    data-default-placeholder="Enter first party name" required>
                                {{-- Shown in place of the text input when OP Type is "LGA": the grantor
                                     is then one of the 44 Local Governments, not the State. Kept as a
                                     sibling rather than swapping the input's type so every existing
                                     data-model="firstParty" binding keeps working; the controller
                                     mirrors this select's value into the same state key. --}}
                                <select class="form-select text-sm hidden" data-role="op-lga-grantor"
                                    aria-label="Local Government that issued the Occupancy Permit">
                                    @include('partials.kano_lga_options', ['selected' => null])
                                </select>
                                {{-- Deed of Purchase only. Shown by applyPurchasePartyRules(). --}}
                                <p class="text-xs text-gray-500 hidden" data-role="purchase-party-hint">
                                    Optional. If the file is already indexed, pick the transaction it
                                    comes from in <span class="font-medium">Existing Records for this
                                    File</span> above. If the file is not indexed yet, leave this blank
                                    and fill it in from the physical file later.
                                </p>
                            </div>
                            <div class="space-y-1 hidden" data-role="party-third-wrapper">
                                <label class="text-sm font-medium" data-role="party-label-third">
                                    <span data-role="label-text">Co-mortgagor</span> <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="form-input text-sm" data-model="thirdParty"
                                    data-name-key="thirdParty" data-default-placeholder="Enter co-mortgagor name"
                                    autocomplete="off">
                            </div>
                            <div class="space-y-1 hidden" data-role="party-fourth-wrapper">
                                <label class="text-sm font-medium" data-role="party-label-fourth">
                                    <span data-role="label-text">Fourth Party</span> <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="form-input text-sm" data-model="fourthParty"
                                    data-name-key="fourthParty" data-default-placeholder="Enter fourth party name"
                                    autocomplete="off">
                            </div>
                            <div class="space-y-1">
                                <label class="text-sm font-medium" data-role="party-label-second">
                                    <span data-role="label-text">Second Party</span> <span class="text-red-500">*</span>
                                </label>
                                <input type="text" class="form-input text-sm" data-model="secondParty"
                                    data-name-key="secondParty" data-default-placeholder="Enter second party name" required>
                            </div>
                        </div>

                        <div class="mt-6 hidden" id="property-additional-instruments">
                            <div class="flex items-center justify-between">
                                <h4 class="text-base font-semibold text-gray-800">Additional Instruments</h4>
                                <button type="button"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-md text-white bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 shadow hover:from-indigo-600 hover:via-purple-600 hover:to-pink-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition"
                                    data-action="add-instrument">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add More Instruments
                                </button>
                            </div>
                            <p class="text-sm text-gray-500 mt-1">
                                Each instrument entry will be stored as a separate record while keeping the property
                                details intact.
                            </p>
                            <p class="text-xs text-gray-400 mt-3" data-role="instrument-empty">No additional instruments
                                yet. Click “Add More Instruments” to capture another Assignment, Surrender, Subdivision,
                                Merger, Withdrawn, or Revoke.</p>
                            <div class="space-y-4" data-role="instrument-list"></div>
                            <template id="pra-instrument-entry-template">
                                <div class="mt-4 rounded-lg p-4 space-y-4 border border-gray-200 bg-white"
                                    data-role="instrument-entry">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <h5 class="font-medium text-gray-700">Instrument <span
                                                    data-role="instrument-index"></span></h5>
                                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full hidden"
                                                data-role="instrument-tag"></span>
                                        </div>
                                        <button type="button" class="text-sm text-red-600 hover:text-red-700"
                                            data-action="remove-instrument">Remove</button>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Instrument Type </label>
                                            <select class="form-select text-sm" data-field="type" required>
                                                <option value="">Select type</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-700">Instrument Date</label>
                                            <input type="date" class="form-input text-sm" data-field="date">
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="text-sm font-medium text-gray-700"
                                                data-role="instrument-label-from">From</label>
                                            <input type="text" class="form-input text-sm" data-field="party_from"
                                                data-placeholder="from">
                                        </div>
                                        <div>
                                            <label class="text-sm font-medium text-gray-700 flex items-center gap-1">
                                                <span data-role="instrument-label-to">To</span>
                                                <span class="text-xs text-gray-400 hidden"
                                                    data-role="instrument-hint">(1 ... n)</span>
                                            </label>
                                            <input type="text" class="form-input text-sm" data-field="party_to"
                                                data-placeholder="to">
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Property Description -->
                    <div class="space-y-1" data-role="property-description-section">
                        <div class="flex justify-between items-center">
                            <label class="text-sm">Property Description</label>
                            <button type="button" class="text-xs text-blue-600 hover:text-blue-800"
                                data-action="refresh-description">
                                🔄 Refresh Description
                            </button>
                        </div>
                        <textarea id="property-description" name="property_description" rows="4"
                            data-model="propertyDescription" class="form-input text-sm bg-gray-50" readonly
                            placeholder="This field will be auto-populated based on property details above"></textarea>
                        <div class="text-xs text-gray-500 italic">This field auto-populates from House No, Street Name
                            (or specified Other Street Name), District (or specified Other District Name), LGA, and
                            State.</div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2 border-t mt-4 sticky bottom-0 bg-white z-10">
                        <button id="property-submit-btn" type="submit" class="btn btn-primary"
                            data-default-label="Submit">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
