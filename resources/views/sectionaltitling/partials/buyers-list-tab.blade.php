{{-- Buyers List Tab Partial --}}
{{-- This partial handles the buyer list display and CSV upload functionality --}}
{{-- Required variables: $application, $titles, optional $isReadOnly (defaults to $isApproved) --}}

<!-- View Buyer List Tab -->
<div id="buyers-tab" class="tab-content" x-data="buyersData()">
    @php
        $buyersReadOnly = $isReadOnly ?? $isApproved ?? false;
    @endphp
    <input type="hidden" id="application_id" value="{{ $application->id }}">
    {{-- The draft is keyed on the file number, so the autosave needs it on the page. --}}
    <input type="hidden" id="buyer-draft-file-no" value="{{ $application->fileno ?? '' }}">
    <div class="bg-white p-6">
        {{-- Resume banner for a capture that was left unsaved. Filled in by
             buyer-list-diagnostics.js only when there is something to restore. --}}
        <div id="buyer-draft-banner" class="mb-4" style="display: none;"></div>

        @if($buyersReadOnly)
            <div class="mb-4 flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5"></i>
                <span>Planning Recommendation is approved. Buyer edits are disabled for this application.</span>
            </div>
        @endif
       
        <!-- CSV Upload Section -->
        @if(!request()->get('url') || request()->get('url') !== 'view')
        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-sm font-medium text-gray-700 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    Bulk Import Buyers (CSV)
                </h4>
                <a href="{{ route('buyer.template.download') }}"
                   class="text-xs bg-blue-50 text-blue-600 px-3 py-1 rounded-md hover:bg-blue-100 flex items-center"
                   download>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download Template
                </a>
            </div>
            
            <!-- Important Notice -->
            <div class="bg-amber-50 border border-amber-200 rounded-md p-3 mb-4">
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-600 mr-2 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div class="text-sm text-amber-800">
                        <strong>Important — the file replaces the list:</strong> importing a CSV
                        <strong>deletes every buyer currently saved on this application</strong> and stores the
                        rows in the file instead. It does not add to what is already there. Upload the complete
                        list, not just the new buyers. You will be asked to confirm before anything is deleted.
                    </div>
                </div>
            </div>
            
            <!-- File Upload Section -->
            <div class="border-2 border-dashed border-gray-300 rounded-md p-4 text-center hover:border-blue-400 transition-colors {{ $buyersReadOnly ? 'opacity-50 cursor-not-allowed' : '' }}">
                <div class="flex justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                
                <input type="file" 
                       id="csvFileInput"
                       name="csv_file" 
                       accept=".csv" 
                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       onchange="handleCsvImport()"
                       {{ $buyersReadOnly ? 'disabled' : '' }}>
                
                <p class="text-xs text-gray-500 mt-2">CSV file with buyer information (max 5MB)</p>
            </div>
            
            <!-- Result Area -->
            <div id="csv-result" class="mt-3"></div>
        </div>
        @endif
        
        <!-- Existing Buyers List -->
        <div>
            <h4 class="text-md font-semibold text-gray-800 mb-3">Existing Buyers</h4>
            <div id="buyers-list-container">
                <div class="text-center text-gray-500 py-4">Loading buyers list...</div>
            </div>
        </div>
 
 
        @if(!request()->get('url') || request()->get('url') !== 'view')
        <!-- Add Buyer Button (initially visible) -->
        <div class="flex justify-center mb-6" id="initial-add-buyer-section">
            <button type="button" 
                onclick="showBuyersForm()" 
                class="flex items-center px-4 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 {{ $buyersReadOnly ? 'opacity-50 cursor-not-allowed' : '' }}"
                {{ $buyersReadOnly ? 'disabled' : '' }}>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add Buyer
            </button>
        </div>
        
        <div class="bg-gray-50 rounded-lg p-4 mb-6 {{ $buyersReadOnly ? 'opacity-50' : '' }}" id="buyers-form-container" style="display: none;">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-2 border-b">
                <h3 class="text-lg font-semibold text-gray-800">Add Buyers Manually</h3>
                {{-- Autosave receipt. Hidden until the first draft is accepted, so
                     it only ever appears when there is genuinely something kept. --}}
                <span id="buyer-draft-status"
                      class="items-center rounded-md bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700"
                      style="display: none;"></span>
            </div>
            @if($buyersReadOnly)
                <div class="mb-4 p-3 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
                    <div class="flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span class="font-medium">Cannot add buyers - Planning recommendation approved</span>
                    </div>
                    <p class="mt-1 text-sm">The buyers list is locked because the planning recommendation has been approved.</p>
                </div>
            @endif
            
            <form id="add-buyers-form" method="POST" action="{{ route('buyer.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="application_id" value="{{ $application->id }}" required>
                
                <!-- Add Buyer Button (moved up) -->
                <div class="flex justify-start mb-4">
                    <button type="button" 
                        @click.prevent="addBuyer()" 
                        class="flex items-center px-3 py-2 text-sm bg-blue-500 text-white rounded-md hover:bg-blue-600 {{ $buyersReadOnly ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add Another Buyer
                    </button>
                </div>
                
                <div>
                    <template x-for="(buyer, index) in buyers" :key="index">
                        <div class="flex items-start mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 w-full">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Title <span class="text-gray-500">(Optional)</span>
                                    </label>
                                    <select :name="'records['+index+'][buyerTitle]'"
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase"
                                        {{ $buyersReadOnly ? 'disabled' : '' }} 
                                        @change="handleTitleChange($event, index)">
                                        <option value="" selected>No Title</option>
                                        @foreach($titles as $title)
                                            <option value="{{ $title->title }}">{{ $title->display_name }}</option>
                                        @endforeach
                                        <option value="Other">Other</option>
                                    </select>
                                    <div x-show="buyers[index] && buyers[index].showCustomTitle" class="mt-2">
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Enter the exact title to use:</label>
                                        <p class="text-xs text-gray-500 mb-2">Type the honorific exactly as it should appear on the certificate (for example: <span class="font-semibold text-gray-700">Engr.</span>)</p>
                                        <input type="text" 
                                            :name="'records['+index+'][customTitle]'"
                                            class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase" 
                                            placeholder="Enter custom title" 
                                            oninput="this.value = this.value.toUpperCase()"
                                            x-model="buyers[index].customTitle">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" :name="'records['+index+'][firstName]'" 
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm uppercase" 
                                        placeholder="Enter First Name" 
                                        required 
                                        oninput="this.value = this.value.toUpperCase()"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Surname <span class="text-red-500">*</span></label>
                                    <input type="text" :name="'records['+index+'][surname]'" 
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm uppercase" 
                                        placeholder="Enter Surname" 
                                        required 
                                        oninput="this.value = this.value.toUpperCase()"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Land Use</label>
                                    <select :name="'records['+index+'][landUse]'"
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                        <option value="">Select Land Use</option>
                                        <option value="RESIDENTIAL">RESIDENTIAL</option>
                                        <option value="COMMERCIAL">COMMERCIAL</option>
                                        <option value="INDUSTRIAL">INDUSTRIAL</option>
                                        <option value="MIXED">MIXED</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit No <span class="text-red-500">*</span></label>
                                    <input type="text" :name="'records['+index+'][unit_no]'" 
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm uppercase" 
                                        placeholder="Enter Unit No" 
                                        required 
                                        oninput="this.value = this.value.toUpperCase()"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Section Number <span class="text-red-500">*</span></label>
                                    <input type="text" :name="'records['+index+'][sectionNumber]'" 
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm uppercase" 
                                        placeholder="Enter Section Number" 
                                        required 
                                        oninput="this.value = this.value.toUpperCase()"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Block No</label>
                                    <input type="text" :name="'records['+index+'][blockNo]'"
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm uppercase"
                                        placeholder="Enter Block No"
                                        oninput="this.value = this.value.toUpperCase()"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Measurement (sqm)</label>
                                    <input type="number" step="0.01" :name="'records['+index+'][unitMeasurement]'" 
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm" 
                                        placeholder="e.g. 50"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Unit Cubic Measurement (cbm)</label>
                                    <input type="number" step="0.01" :name="'records['+index+'][cubicMeasurement]'" 
                                        class="w-full py-2 px-3 border border-gray-300 rounded-md text-sm" 
                                        placeholder="e.g. 50"
                                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                                </div>
                            </div>
                            <button type="button" 
                                @click="removeBuyer(index)" 
                                x-show="buyers.length > 1" 
                                class="bg-red-500 text-white p-1.5 rounded-md hover:bg-red-600 flex items-center justify-center mt-8 {{ $buyersReadOnly ? 'opacity-50 cursor-not-allowed' : '' }}"
                                {{ $buyersReadOnly ? 'disabled' : '' }}>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                
                <div class="flex justify-end mt-4">
                    <button type="submit" 
                        class="flex items-center px-4 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700 {{ $buyersReadOnly ? 'opacity-50 cursor-not-allowed' : '' }}"
                        {{ $buyersReadOnly ? 'disabled' : '' }}>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                        </svg>
                        Save Buyers
                    </button>
                </div>
            </form>
        </div>
        @endif

      
    </div>
</div>

 
<script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
{{-- Session trace + draft autosave for this form. Included here rather than on the
     pages so both screens that embed this partial are covered. See the file header
     for what it records and why. --}}
<script src="{{ asset('js/buyer-list-diagnostics.js') }}"></script>
<script>
    // Define Alpine.js data function for buyers
    function buyersData() {
        return {
            // Initialize with a single empty buyer to show one form initially
            buyers: [{ showCustomTitle: false, customTitle: '' }],
            init() {
                // A second init on one page visit means Alpine rebuilt this
                // component, which resets `buyers` to one empty row and takes
                // every typed row with it. That is indistinguishable, from the
                // officer's chair, from "the form just closed" — so it is
                // recorded rather than guessed at.
                if (window.__buyerListDiagnostics) {
                    window.__buyerListDiagnostics.noteComponentInit(this.buyers.length);
                }
            },
            addBuyer() {
                // Add exactly one buyer per click
                this.buyers.push({ showCustomTitle: false, customTitle: '' });
                this.trace('row_added', this.buyers.length);
            },
            removeBuyer(index) {
                this.buyers.splice(index, 1);
                if (this.buyers.length === 0) {
                    // Always keep at least one form visible
                    this.buyers.push({ showCustomTitle: false, customTitle: '' });
                }
                this.trace('row_removed', this.buyers.length, { index: index });
            },
            resetBuyers() {
                // Reset to a single empty form after successful save
                this.buyers = [{ showCustomTitle: false, customTitle: '' }];
                this.trace('rows_reset', this.buyers.length);
            },
            trace(event, rowCount, extra) {
                if (!window.__buyerListDiagnostics) return;
                window.__buyerListDiagnostics.log(event, Object.assign({ rows: rowCount }, extra || {}));
            },
            handleTitleChange(event, index) {
                const selectedValue = event.target.value;
                if (selectedValue === 'Other') {
                    this.buyers[index].showCustomTitle = true;
                } else {
                    this.buyers[index].showCustomTitle = false;
                    this.buyers[index].customTitle = '';
                }
            }
        }
    }

    // CSV Import Handler
    //
    // A CSV upload REPLACES the buyer list for the application - it is the officer's
    // authoritative list for the file, not an addition to what is stored. Merging is
    // what left COM-1991-46 holding two cohorts of the same 249 buyers under
    // different section formats ("U1 A/B" vs "U1"), which the server's
    // name+unit+section duplicate check could never match up.
    function handleCsvImport() {
        const fileInput = document.getElementById('csvFileInput');
        const file = fileInput.files[0];
        const applicationId = document.getElementById('application_id').value;

        if (!file) {
            showCsvResult('error', 'Please select a CSV file');
            return;
        }

        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            showCsvResult('error', 'File size exceeds 5MB limit');
            return;
        }

        // Validate file type
        if (!file.name.endsWith('.csv')) {
            showCsvResult('error', 'Please select a valid CSV file');
            return;
        }

        showCsvResult('loading', 'Processing CSV file...');

        // Parse CSV using PapaParse
        Papa.parse(file, {
            header: true,
            skipEmptyLines: true,
            complete: function(results) {
                if (results.errors.length > 0) {
                    showCsvResult('error', 'Error parsing CSV: ' + results.errors[0].message);
                    return;
                }

                const buyers = results.data;

                if (buyers.length === 0) {
                    showCsvResult('error', 'No valid data found in CSV file');
                    return;
                }

                // Validate required fields
                const hasMissingCoreFields = buyers.some(buyer => {
                    const firstName = (buyer.firstName || '').trim();
                    const surname = (buyer.surname || '').trim();
                    const buyerName = (buyer.buyerName || '').trim();
                    const unitNo = (buyer.unit_no || '').trim();
                    const sectionNumber = (buyer.sectionNumber || '').trim();

                    const hasSplitName = firstName !== '' && surname !== '';
                    const hasFullName = buyerName !== '';

                    return ((!hasSplitName && !hasFullName) || unitNo === '' || sectionNumber === '');
                });

                if (hasMissingCoreFields) {
                    showCsvResult('error', 'Some rows have missing required fields. Each row needs either First Name + Surname or Buyer Name, plus Unit No and Section Number.');
                    return;
                }

                // Nothing is deleted until the officer confirms, and the prompt states
                // the counts rather than describing the effect in the abstract.
                confirmCsvReplace(applicationId, buyers.length).then(function (confirmed) {
                    if (!confirmed) {
                        // Clear the picker so the same file can be selected again -
                        // onchange does not refire for an unchanged value.
                        fileInput.value = '';
                        showCsvResult('warning', 'Import cancelled. The saved buyer list was not changed.');
                        return;
                    }

                    submitCsvImport(buyers, applicationId, fileInput);
                });
            },
            error: function(error) {
                showCsvResult('error', 'Error reading file: ' + error.message);
            }
        });
    }

    // Ask before replacing, naming how many buyers go and how many arrive.
    //
    // Uses SweetAlert where the host page loads it. Two of the pages that include
    // this partial (actions/recommendation, actions/parts/recomm_css) do not, so
    // there is a window.confirm fallback - a missing Swal must not leave the
    // officer with a file picker that silently does nothing.
    function confirmCsvReplace(applicationId, incomingRows) {
        showCsvResult('loading', 'Checking the buyers already saved...');

        return fetch(`/buyer/list/${applicationId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => (data && typeof data.count === 'number') ? data.count : null)
            .catch(() => null)
            .then(function (existingCount) {
                let losing;
                if (existingCount === null) {
                    losing = 'Every buyer currently saved on this application will be <strong>deleted</strong>.';
                } else if (existingCount > 0) {
                    losing = `<strong>${existingCount} buyer(s)</strong> currently saved will be <strong>deleted</strong>.`;
                } else {
                    losing = 'There are no buyers saved yet, so nothing will be deleted.';
                }

                if (typeof Swal === 'undefined') {
                    const plain = [
                        'This will REPLACE the buyer list for this application.',
                        '',
                        losing.replace(/<[^>]+>/g, ''),
                        `${incomingRows} row(s) from this file will be stored in their place.`,
                        '',
                        'Upload the COMPLETE list - rows missing from the file will be lost.',
                        'This cannot be undone. Continue?'
                    ].join('\n');

                    return window.confirm(plain);
                }

                return Swal.fire({
                    icon: 'warning',
                    title: 'Replace the buyer list?',
                    html: `
                        <div style="text-align:left;font-size:0.95rem;line-height:1.6">
                            <p style="margin-bottom:0.75rem">
                                This upload <strong>replaces</strong> the buyer list for this application.
                                It does not add to it.
                            </p>
                            <ul style="margin:0 0 0.75rem 1.1rem;padding:0;list-style:disc">
                                <li>${losing}</li>
                                <li><strong>${incomingRows} row(s)</strong> from this file will be stored in their place.</li>
                            </ul>
                            <p style="margin:0;padding:0.6rem 0.75rem;background:#FEF3C7;border-left:3px solid #F59E0B;border-radius:0.25rem">
                                Upload the <strong>complete</strong> list &mdash; any buyer missing from the
                                file will be lost. This cannot be undone.
                            </p>
                        </div>
                    `,
                    showCancelButton: true,
                    focusCancel: true,
                    reverseButtons: true,
                    confirmButtonText: 'Yes, replace the list',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#DC2626',
                    cancelButtonColor: '#6B7280',
                    width: '32rem'
                }).then(result => result.isConfirmed === true);
            });
    }

    function submitCsvImport(buyers, applicationId, fileInput) {
        showCsvResult('loading', 'Importing buyers...');

        const formData = new FormData();
        formData.append('application_id', applicationId);
        formData.append('_token', '{{ csrf_token() }}');
        formData.append('client_source', 'csv');
        // Sent only after the officer confirmed. Without it the server keeps the old
        // add-and-skip-duplicates behaviour, so a stale cached copy of this page can
        // never delete a buyer list without asking.
        formData.append('replace_existing', '1');
        if (window.__buyerListDiagnostics) {
            formData.append('client_trace_id', window.__buyerListDiagnostics.traceId());
            window.__buyerListDiagnostics.log('csv_import_started', { rows: buyers.length, mode: 'replace' });
        }

        buyers.forEach((buyer, index) => {
            // Clean unitMeasurement - remove 'sqm' and other non-numeric characters except decimal point
            let cleanMeasurement = '';
            if (buyer.unitMeasurement) {
                cleanMeasurement = buyer.unitMeasurement.toString()
                    .replace(/sqm/gi, '')  // Remove 'sqm' (case insensitive)
                    .replace(/[^\d.]/g, '') // Remove all non-numeric except decimal point
                    .trim();
            }

            formData.append(`records[${index}][buyerTitle]`, buyer.buyerTitle || '');
            formData.append(`records[${index}][firstName]`, buyer.firstName || '');
            formData.append(`records[${index}][middleName]`, buyer.middleName || '');
            formData.append(`records[${index}][surname]`, buyer.surname || '');
            formData.append(`records[${index}][unit_no]`, buyer.unit_no || '');
            formData.append(`records[${index}][sectionNumber]`, buyer.sectionNumber || '');
            formData.append(`records[${index}][blockNo]`, buyer.blockNo || buyer.block_no || '');
            formData.append(`records[${index}][landUse]`, buyer.landUse || '');
            formData.append(`records[${index}][unitMeasurement]`, cleanMeasurement);
        });

        // Submit data via AJAX
        fetch('{{ route("buyer.import.csv") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json().then(data => ({ status: response.status, data: data })))
        .then(({ status, data }) => {
            if (data.success) {
                // Report the deletion as loudly as the insertion - the officer needs to
                // see that the previous list is gone, not just that rows arrived.
                let message = `Imported ${data.inserted} buyer(s).`;
                if (data.deleted > 0) {
                    message += ` ${data.deleted} previously saved buyer(s) were deleted and replaced.`;
                }
                if (data.skipped > 0) {
                    message += ` ${data.skipped} repeated row(s) in the file were imported once.`;
                }
                message += ` The list now holds ${data.count} buyer(s).`;

                showCsvResult(data.deleted > 0 ? 'warning' : 'success', message);

                if (window.__buyerListDiagnostics) {
                    window.__buyerListDiagnostics.log('csv_import_done', {
                        mode: 'replace',
                        deleted: data.deleted,
                        inserted: data.inserted,
                        skipped: data.skipped,
                        total: data.count
                    });
                }
                // Refresh buyers list
                loadBuyersList();
                // Clear file input
                fileInput.value = '';
                return;
            }

            fileInput.value = '';

            // The server refuses to replace buyers that already hold an allocated ST
            // unit file number, because deleting them orphans the allocation.
            if (status === 409 && data.blocked_reason === 'st_file_numbers_allocated') {
                showCsvResult('error', data.message);
                if (window.__buyerListDiagnostics) {
                    window.__buyerListDiagnostics.log('csv_import_blocked', { reason: data.blocked_reason });
                }
                return;
            }

            showCsvResult('error', data.message || 'Failed to import buyers');
        })
        .catch(error => {
            console.error('Error:', error);
            fileInput.value = '';
            showCsvResult('error', 'An error occurred while importing buyers. The saved list was not changed.');
        });
    }

    function showCsvResult(type, message) {
        const resultDiv = document.getElementById('csv-result');
        let bgColor, borderColor, textColor, icon;
        
        if (type === 'success') {
            bgColor = 'bg-green-50';
            borderColor = 'border-green-200';
            textColor = 'text-green-800';
            icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />';
        } else if (type === 'warning') {
            bgColor = 'bg-amber-50';
            borderColor = 'border-amber-200';
            textColor = 'text-amber-800';
            icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />';
        } else if (type === 'error') {
            bgColor = 'bg-red-50';
            borderColor = 'border-red-200';
            textColor = 'text-red-800';
            icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />';
        } else {
            bgColor = 'bg-blue-50';
            borderColor = 'border-blue-200';
            textColor = 'text-blue-800';
            icon = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />';
        }
        
        resultDiv.innerHTML = `
            <div class="${bgColor} border ${borderColor} rounded-md p-3">
                <div class="flex items-start">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 ${textColor} mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        ${icon}
                    </svg>
                    <span class="text-sm ${textColor}">${message}</span>
                </div>
            </div>
        `;
    }

    // Show buyers form and change initial button
    function showBuyersForm() {
        // Hide initial add buyer section
        const initialSection = document.getElementById('initial-add-buyer-section');
        if (initialSection) {
            initialSection.style.display = 'none';
        }
        
        // Show buyers form container
        const formContainer = document.getElementById('buyers-form-container');
        if (formContainer) {
            formContainer.style.display = 'block';
        }
    }

    // Note: titles variable is declared in the parent page (viewrecorddetail.blade.php)
</script>
 
