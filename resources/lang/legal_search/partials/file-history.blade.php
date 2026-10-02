    <!-- File History View (initially hidden) -->
    <div id="file-history-view" class="hidden">
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-2xl font-bold">File Details</h2>
            <p class="text-gray-500" id="file-details-subtitle">Viewing legal search results for file <span id="file-reference"></span></p>
          </div>
          <div class="flex items-center gap-2">
            <button id="back-to-dashboard-btn" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
              </svg>
              Back to Dashboard
            </button>
            <button id="new-search-from-details-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              New Search
            </button>
            <button id="view-property-timeline-btn" class="hidden inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700" data-prop-id="" data-file-number="">
              <i class="fas fa-history mr-2"></i>
              Timeline
            </button>

          </div>
        </div>
      </div>

      {{-- Section 1: File Information --}}
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - File Information -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">File Information</h3>
            <button id="reset-file-info-btn" class="hidden inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-100 text-amber-800 hover:bg-amber-200 border border-amber-300" title="Reset to default file indexing data">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
              Reset
            </button>
          </div>
          <p class="text-sm text-gray-500 mb-4" id="file-info-subtitle">Details about the selected file</p>
          
          <div class="space-y-4">
            <div class="form-row">
              <span class="form-label" id="file-number-label">File Number (MLPPFNo):</span>
              <span class="form-value" id="file-number-value"></span>
            </div>
            <div class="form-row hidden" id="temp-file-number-row">
              <span class="form-label">Temporary File Number:</span>
              <span class="form-value" id="temp-file-number-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">KANGIS File Number:</span>
              <span class="form-value" id="kangis-file-number-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">New KANGIS File Number:</span>
              <span class="form-value" id="new-kangis-file-number-value"></span>
            </div>
            
            <div class="form-row">
              <span class="form-label">File Title:</span>
              <span class="form-value font-semibold" id="file-title-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Plot No:</span>
              <span class="form-value" id="plot-no-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Size:</span>
              <span class="form-value" id="size-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">TP No:</span>
              <span class="form-value" id="tpno-value"></span>
            </div>
            
            <div class="form-row">
              <span class="form-label">District:</span>
              <span class="form-value" id="district-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">LGA:</span>
              <span class="form-value" id="lga-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Land Use:</span>
              <span class="form-value" id="property-type-value"></span>
            </div>
            
            <div class="form-row">
              <span class="form-label">Last Transaction:</span>
              <span class="form-value" id="last-transaction-value"></span>
            </div>
            <div class="form-row">
              <span class="form-label">Status:</span>
              <span class="form-value flex justify-end items-center" id="status-value">
                <span class="status-indicator bg-green-500"></span>
                Active
              </span>
            </div>
            <div id="digital-archive-section" class="mt-4 pt-3 border-t border-gray-200">
              <h4 class="text-sm font-semibold text-gray-700 mb-2">Digital Archive</h4>
              <div id="digital-archive-loading" class="hidden text-xs text-slate-400">Checking archive...</div>
              <div id="digital-archive-empty" class="hidden text-xs text-slate-400">No digital archive available.</div>
              <div id="digital-archive-folders" class="flex flex-col gap-2"></div>
            </div>
          </div>
        </div>

        {{-- Section 2: File History with Cleanup Mode --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden lg:col-span-2">
          <div class="p-6">
            <div class="flex items-center justify-between mb-2">
              <div>
                <h3 class="text-lg font-semibold">Multi-Source Data Aggregation Model</h3>
                <p class="text-sm text-gray-500">Blockchain Concept</p>
              </div>
              <div class="flex items-center gap-2">
                <button id="refresh-history-btn" type="button" class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-medium bg-white text-gray-600 hover:bg-gray-50 border border-gray-300 cursor-pointer whitespace-nowrap" title="Refresh records">
                  <svg data-refresh-icon xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                  Refresh
                </button>
                <button id="add-record-smart-btn" type="button" class="inline-flex items-center px-3 py-2 rounded-lg text-xs font-medium bg-blue-600 text-white hover:bg-blue-700 border border-transparent cursor-pointer whitespace-nowrap" data-role="legal-search-add-record" data-target="pra">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                  Add Record
                </button>
              </div>
            </div>

            <div class="flex items-center gap-4 mb-4 mt-2 text-[11px] text-gray-600">
              <!-- Legend A group -->
              <div class="flex items-center gap-3 bg-emerald-50/80 px-3 py-1.5 rounded-lg border border-emerald-100">
                <span class="text-[9px] font-black text-emerald-500 uppercase tracking-widest mr-1">Legend A</span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Preferred</span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span> Dropped</span>
              </div>
              <!-- Legend B group -->
              <div class="flex items-center gap-3 bg-blue-50/80 px-3 py-1.5 rounded-lg border border-blue-100">
                <span class="text-[9px] font-black text-blue-500 uppercase tracking-widest mr-1">Legend B</span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Duplicate</span>
                <span class="flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span> Unique</span>
              </div>
            </div>


            <div class="tabs border-b mb-6">
              <button class="tab active" data-tab="transaction-history">
                <span class="tab-label">File History <span id="tab-count-transaction-history" class="tab-count tab-count-fh">0</span></span>
              </button>
              <button class="tab" data-tab="property-record">
                <span class="tab-label">Property Record <span id="tab-count-property-record" class="tab-count tab-count-pra">0</span></span>
              </button>
              <button class="tab" data-tab="instrument-registration">
                <span class="tab-label">Deeds Registration <span id="tab-count-instrument" class="tab-count tab-count-deed">0</span></span>
              </button>
              <button class="tab" data-tab="cofo">
                <span class="tab-label">CofO <span id="tab-count-cofo" class="tab-count tab-count-cofo">0</span></span>
              </button>
            </div>
              <script>
                (function () {
                  if (window.__legalSearchRecordFallbackBound) return;
                  window.__legalSearchRecordFallbackBound = true;

                  function closeRecordDialog() {
                    var modal = document.getElementById('property-form-dialog');
                    if (!modal) return;
                    modal.classList.remove('show');
                    modal.classList.add('hidden');
                    modal.style.display = 'none';
                  }

                  function bindCloseHandlers() {
                    if (window.__legalSearchRecordCloseBound) return;
                    window.__legalSearchRecordCloseBound = true;

                    document.addEventListener('click', function (event) {
                      var closeBtn = event.target.closest('#close-property-form');
                      if (closeBtn) {
                        event.preventDefault();
                        closeRecordDialog();
                        return;
                      }

                      var modal = document.getElementById('property-form-dialog');
                      if (modal && event.target === modal) {
                        closeRecordDialog();
                      }
                    }, true);

                    document.addEventListener('keydown', function (event) {
                      if (event.key === 'Escape') {
                        closeRecordDialog();
                      }
                    });
                  }

                  bindCloseHandlers();

                  function forceOpenRecordDialog(target) {
                    var modal = document.getElementById('property-form-dialog');
                    var form = document.getElementById('property-record-form');
                    if (!modal || !form) {
                      console.error('LegalSearch fallback: dialog/form missing', {
                        hasModal: !!modal,
                        hasForm: !!form
                      });
                      return;
                    }

                    var normalizedTarget = (target || '').toLowerCase() === 'cofo_staging' ? 'CofO_staging' : 'pra';
                    var targetInput = form.querySelector('input[name="target_table"]');
                    if (!targetInput) {
                      targetInput = document.createElement('input');
                      targetInput.type = 'hidden';
                      targetInput.name = 'target_table';
                      form.appendChild(targetInput);
                    }
                    targetInput.value = normalizedTarget;

                    var modeInput = form.querySelector('[data-model="formMode"]') || form.querySelector('[name="record_mode"]');
                    if (modeInput) modeInput.value = 'property';

                    // Prefill file number from the actual search input first,
                    // then fallback to panel value only when search value is unavailable.
                    var fileNumberFromLastSearch = (window.__lsLastSearchedFileNumber || '').trim();
                    var fileNumberFromSearch = (document.getElementById('fileNumber')?.value || '').trim();
                    var fileNumberFromPanel = (document.getElementById('file-number-value')?.textContent || '').trim();
                    var searchedDisplay = fileNumberFromLastSearch || fileNumberFromSearch || ((fileNumberFromPanel && fileNumberFromPanel !== '-') ? fileNumberFromPanel : '');

                    var filenoInput = form.querySelector('#fileno');
                    var mlsInput = form.querySelector('#mlsFNo');
                    var kangisInput = form.querySelector('#kangisFileNo');
                    var newKangisInput = form.querySelector('#NewKANGISFileno');
                    var activeTabInput = form.querySelector('#activeFileTab');
                    var displayInput = form.querySelector('#fileno-display');
                    var clearSelectionBtn = form.querySelector('#clear-selection');

                    if (filenoInput) filenoInput.value = searchedDisplay;
                    if (mlsInput) mlsInput.value = '';
                    if (kangisInput) kangisInput.value = '';
                    if (newKangisInput) newKangisInput.value = '';
                    if (activeTabInput) activeTabInput.value = '';
                    if (displayInput) {
                      displayInput.value = searchedDisplay;
                      if (searchedDisplay) {
                        displayInput.classList.remove('text-gray-400', 'bg-gray-100');
                        displayInput.classList.add('text-gray-900', 'bg-white');
                      } else {
                        displayInput.classList.add('text-gray-400', 'bg-gray-100');
                        displayInput.classList.remove('text-gray-900', 'bg-white');
                      }
                    }
                    if (clearSelectionBtn) {
                      clearSelectionBtn.classList.toggle('hidden', !searchedDisplay);
                    }

                    // Keep LS modal fields minimal
                    var relatedSection = form.querySelector('[data-role="related-file-number-section"]') || form.querySelector('#related_file_number')?.closest('.mt-4');
                    if (relatedSection) relatedSection.classList.add('hidden');

                    var propDescSection = form.querySelector('[data-role="property-description-section"]') || form.querySelector('#property-description')?.closest('.space-y-1');
                    if (propDescSection) {
                      propDescSection.classList.add('hidden');
                      propDescSection.style.display = 'none';
                    }

                    var propDescField = form.querySelector('#property-description');
                    if (propDescField) {
                      propDescField.style.display = 'none';
                    }

                    // Add New Property modal right panel (House No/Plot No/etc.)
                    var propertyDescriptionPanel = form.querySelector('[data-section="property-description"]');
                    if (propertyDescriptionPanel) {
                      propertyDescriptionPanel.classList.add('hidden');
                      propertyDescriptionPanel.style.display = 'none';
                    }

                    if (modal.parentElement !== document.body) {
                      document.body.appendChild(modal);
                    }

                    modal.style.position = 'fixed';
                    modal.style.inset = '0';
                    modal.style.width = '100vw';
                    modal.style.height = '100vh';
                    modal.style.backgroundColor = 'rgba(17, 24, 39, 0.55)';
                    modal.style.zIndex = '2147483000';
                    modal.style.display = 'flex';
                    modal.style.alignItems = 'center';
                    modal.style.justifyContent = 'center';
                    modal.style.padding = '24px';

                    var content = modal.querySelector('.dialog-content');
                    if (content) {
                      content.style.maxWidth = '1100px';
                      content.style.width = '100%';
                      content.style.maxHeight = '92vh';
                      content.style.overflowY = 'auto';
                      content.style.background = '#fff';
                      content.style.borderRadius = '12px';
                      content.style.boxShadow = '0 25px 60px rgba(0,0,0,0.35)';
                    }

                    modal.classList.remove('hidden');
                    modal.classList.add('show');
                    modal.style.display = 'flex';

                    console.log('LegalSearch fallback opened dialog for', normalizedTarget);
                  }

                  window.__openLegalSearchRecordDialog = function (target) {
                    forceOpenRecordDialog(target);
                  };

                  document.addEventListener('click', function (event) {
                    var trigger = event.target.closest('[data-role="legal-search-add-record"]');
                    if (!trigger) return;
                    event.preventDefault();
                    var target = (trigger.getAttribute('data-target') || '').trim();
                    forceOpenRecordDialog(target);
                  }, true);

                  // AJAX form submission — keeps search results intact
                  document.addEventListener('submit', function (event) {
                    var form = event.target;
                    if (!form || form.id !== 'property-record-form') return;
                    // Only intercept when we opened it from legal-search context
                    var modal = document.getElementById('property-form-dialog');
                    if (!modal || !modal.classList.contains('show')) return;

                    event.preventDefault();

                    var submitBtn = document.getElementById('property-submit-btn');
                    var originalLabel = submitBtn ? submitBtn.textContent : 'Submit';
                    if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving…'; }

                    var formData = new FormData(form);

                    fetch(form.action, {
                      method: 'POST',
                      headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                      },
                      body: formData,
                      credentials: 'same-origin',
                    })
                    .then(function (res) { return res.json().then(function (d) { return { ok: res.ok, data: d }; }); })
                    .then(function (result) {
                      if (result.ok && (result.data.status === 'success' || result.data.success === true)) {
                        // Close modal
                        var closeModal = document.getElementById('close-property-form');
                        if (closeModal) closeModal.click();
                        else {
                          modal.classList.remove('show');
                          modal.classList.add('hidden');
                          modal.style.display = 'none';
                        }
                        // Refresh search results
                        if (typeof renderFileHistory === 'function') {
                          renderFileHistory();
                        }
                        // Show success toast if SweetAlert2 available
                        if (typeof Swal !== 'undefined') {
                          Swal.fire({ icon: 'success', title: 'Saved', text: result.data.message || 'Record saved successfully.', timer: 2500, showConfirmButton: false });
                        }
                      } else {
                        var msg = result.data.message || (result.data.errors ? Object.values(result.data.errors).flat().join('\n') : 'Submission failed.');
                        if (typeof Swal !== 'undefined') {
                          Swal.fire({ icon: 'error', title: 'Error', text: msg });
                        } else {
                          alert(msg);
                        }
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
                      }
                    })
                    .catch(function (err) {
                      console.error('Add record AJAX error:', err);
                      if (typeof Swal !== 'undefined') {
                        Swal.fire({ icon: 'error', title: 'Network Error', text: 'Could not save the record. Please try again.' });
                      } else {
                        alert('Network error. Please try again.');
                      }
                      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
                    });
                  }, true);

                })();
              </script>

            <div id="property-record-tab" class="tab-content">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th style="width:36px;">S/N</th>
                      <th style="min-width:140px;white-space:nowrap;">File No</th>
                      <th style="width:36px;">RW</th>
                      <th style="width:36px;">TW</th>
                      <th style="text-align:left;">Instrument/Transaction Type</th>
                      <th style="text-align:left;">Party 1</th>
                      <th style="min-width:110px;">Party 2</th>
                      <th style="min-width:100px;">Party 3</th>
                      <th style="min-width:80px;">Reg Particulars</th>
                      <th style="min-width:100px;">Transaction Date</th>
                      <th style="min-width:70px;">Reg Time</th>
                      <th style="min-width:80px;">Reg Date</th>
                      <th style="min-width:50px;">Size</th>
                      <th style="min-width:55px;">Caveat</th>
                      <th style="min-width:120px;">Comments</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="property-record-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>

            <div id="transaction-history-tab" class="tab-content active">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th style="width:36px;">S/N</th>
                      <th style="min-width:140px;white-space:nowrap;">File No</th>
                      <th style="width:36px;">RW</th>
                      <th style="width:36px;">TW</th>
                      <th style="text-align:left;">Instrument/Transaction Type</th>
                      <th style="text-align:left;">Party 1</th>
                      <th style="min-width:110px;">Party 2</th>
                      <th style="min-width:100px;">Party 3</th>
                      <th style="min-width:80px;">Reg Particulars</th>
                      <th style="min-width:100px;">Transaction Date</th>
                      <th style="min-width:70px;">Reg Time</th>
                      <th style="min-width:80px;">Reg Date</th>
                      <th style="min-width:50px;">Size</th>
                      <th style="min-width:55px;">Caveat</th>
                      <th style="min-width:120px;">Comments</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="transaction-history-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>

            <div id="instrument-registration-tab" class="tab-content">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th style="width:36px;">S/N</th>
                      <th style="min-width:140px;white-space:nowrap;">File No</th>
                      <th style="width:36px;">RW</th>
                      <th style="width:36px;">TW</th>
                      <th style="text-align:left;">Instrument/Transaction Type</th>
                      <th style="text-align:left;">Party 1</th>
                      <th style="min-width:110px;">Party 2</th>
                      <th style="min-width:100px;">Party 3</th>
                      <th style="min-width:80px;">Reg Particulars</th>
                      <th style="min-width:100px;">Registration Date</th>
                      <th style="min-width:100px;">Registered By</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="instrument-registration-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>

            <div id="cofo-tab" class="tab-content">
              <div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr>
                      <th>S/N</th>
                      <th style="min-width:110px;">File No</th>
                      <th style="min-width:60px;">RW</th>
                      <th style="min-width:60px;">TW</th>
                      <th>Reg Particulars</th>
                      <th>Issue Date</th>
                      <th>Holder Name</th>
                      <th>Land Use</th>
                      <th>Term</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="cofo-table">
                    <!-- Will be populated dynamically -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- Section 3: Timeline View --}}
      <div id="timeline-section" class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6">
          <div class="flex flex-col gap-3 mb-4">
            <div>
              <h3 class="text-lg font-semibold flex items-center">Timeline <span id="timeline-total-count" class="ml-2 inline-flex items-center justify-center min-w-[1.5rem] h-[1.5rem] rounded-full bg-blue-100 text-blue-800 text-xs font-bold px-2">0</span></h3>
              <p class="text-sm text-gray-500">Combined chronological view of all transactions</p>
            </div>
            <div class="flex items-center gap-2 overflow-x-auto min-w-0 pb-1 whitespace-nowrap">
              {{-- Cleanup Mode Toggle --}}
              <div class="flex items-center gap-2 mr-2">
                <label for="cleanup-mode-toggle" class="text-xs font-medium text-gray-700 cursor-pointer select-none">Cleanup</label>
                <button type="button" id="cleanup-mode-toggle" role="switch" aria-checked="false"
                  class="relative inline-flex h-6 w-12 flex-shrink-0 cursor-pointer rounded-full border-2 border-black transition-colors duration-200 ease-in-out bg-gray-200"
                >
                  <span class="sr-only">Toggle Cleanup Mode</span>
                  <span id="cleanup-toggle-dot" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-0"></span>
                </button>
              </div>
              {{-- Cleanup Mode Action Buttons --}}
              <div id="cleanup-actions" class="flex items-center gap-2">
                <button id="cleanup-match-btn" disabled
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                         disabled:opacity-40 disabled:cursor-not-allowed
                         bg-white border-orange-200 text-orange-600 hover:bg-orange-50 disabled:hover:bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.102 1.101" /></svg>
                  Match
                </button>
                <button id="cleanup-drop-btn" disabled
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                         disabled:opacity-40 disabled:cursor-not-allowed
                         bg-white border-orange-200 text-orange-600 hover:bg-orange-50 disabled:hover:bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                  Drop
                </button>
                <button id="smart-demote-btn" disabled
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                         disabled:opacity-40 disabled:cursor-not-allowed
                         bg-white border-amber-200 text-amber-600 hover:bg-amber-50 disabled:hover:bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13l-3 3m0 0l-3-3m3 3V8m0 13a9 9 0 110-18 9 9 0 010 18z" /></svg>
                  Demote
                </button>
                <button id="cleanup-remove-btn" disabled
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                         disabled:opacity-40 disabled:cursor-not-allowed
                         bg-white border-orange-200 text-orange-600 hover:bg-orange-50 disabled:hover:bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                  Remove
                </button>
                <button id="cleanup-edit-btn" disabled
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                         disabled:opacity-40 disabled:cursor-not-allowed
                         bg-white border-orange-200 text-orange-600 hover:bg-orange-50 disabled:hover:bg-white">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                  Edit
                </button>
                <span id="cleanup-selection-count" class="text-xs text-gray-500 ml-2 hidden">0 selected</span>
              </div>

              <button id="arrange-mode-btn" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border bg-white border-purple-200 text-purple-600 hover:bg-purple-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                Arrange
              </button>
              <button id="show-weighting-btn" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border bg-white border-blue-200 text-blue-600 hover:bg-blue-50">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3-3m3 3l-3-9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" /></svg>
                Weighing
              </button>
              <button id="toggle-excluded-records-btn" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100 hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                Excluded Records (<span id="excluded-count-badge">0</span>)
              </button>
              <button id="view-detailed-records-btn" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-black hover:bg-black/90">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
                View Detailed Records
              </button>
            </div>
          </div>
          <div class="overflow-x-auto pb-6 min-h-[250px]">
            <table class="w-full" id="timeline-table-wrapper">
              <thead>
                <tr> 
                  <th class="cleanup-col hidden w-8"><input type="checkbox" class="select-all-checkbox" data-table="timeline-table"></th>
                  <th class="arrange-col hidden w-8">#</th>
                  <th style="min-width:40px;">S/N</th>
                  <th style="min-width:140px;white-space:nowrap;">File No</th>
                  <th style="min-width:60px;">Source</th>
                  <th style="min-width:70px;">Weight</th>
                  <th style="min-width:120px;">Instrument/Transaction Type</th>
                  <th style="min-width:110px;white-space:nowrap;">Party 1</th>
                  <th style="min-width:110px;">Party 2</th>
                  <th style="min-width:100px;">Party 3</th>
                  <th style="min-width:80px;">Reg Particulars</th>
                  <th style="min-width:100px;">Transaction Date</th>
                  <th style="min-width:70px;">Reg Time</th>
                  <th style="min-width:80px;">Reg Date</th>
                  <th style="min-width:50px;">Size</th>
                  <th style="min-width:55px;">Caveat</th>
                  <th style="min-width:120px;max-width:200px;">Comments</th>
                  <th style="min-width:60px;">Actions</th>
                </tr>
              </thead>
              <tbody id="timeline-table">
                <!-- Will be populated dynamically -->
              </tbody>
            </table>
          </div>

          {{-- Excluded Records Table --}}
          <div id="excluded-records-section" class="mt-8 pt-8 border-t border-gray-100 hidden">
              <div class="flex items-center justify-between mb-4">
                <div>
                  <h3 class="text-md font-semibold text-gray-700 flex items-center">
                    Excluded / Duplicate Records 
                    <span id="excluded-total-count" class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-[1.25rem] rounded-full bg-gray-100 text-gray-600 text-[10px] font-bold px-1.5">0</span>
                  </h3>
                  <p class="text-xs text-gray-500">Records bypassed by weighting or manually dropped</p>
                </div>
                <button id="smart-replace-promote-btn" disabled
                  class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors
                         disabled:opacity-40 disabled:cursor-not-allowed
                         bg-green-50 border-green-200 text-green-700 hover:bg-green-100 disabled:hover:bg-green-50">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 11l3-3m0 0l3 3m-3-3v8m0 5a9 9 0 110-18 9 9 0 010 18z" /></svg>
                  Promote
                </button>
              </div>
            <div class="overflow-x-auto pb-32">
              <table class="w-full opacity-75 grayscale-[0.5]" id="excluded-table-wrapper">
                <thead>
                  <tr class="bg-gray-50/50"> 
                    <th class="cleanup-col hidden w-8 text-center"><input type="checkbox" class="select-all-checkbox" data-table="excluded-table"></th>
                    <th style="min-width:40px;">S/N</th>
                    <th style="min-width:140px;white-space:nowrap;">File No</th>
                    <th style="min-width:60px;">Source</th>
                    <th style="min-width:70px;">Weight</th>
                    <th style="min-width:110px;">Party 1</th>
                    <th style="min-width:110px;">Party 2</th>
                    <th style="min-width:120px;">Instrument/Transaction Type</th>
                    <th style="min-width:80px;">Reg Particulars</th>
                    <th style="min-width:100px;">Transaction Date</th>
                    <th style="min-width:70px;">Reg Time</th>
                    <th style="min-width:80px;">Reg Date</th>
                    <th style="min-width:50px;">Size</th>
                    <th style="min-width:120px;max-width:200px;">Comments</th>
                    <th style="min-width:70px;">Actions</th>
                  </tr>
                </thead>
                <tbody id="excluded-table" class="text-gray-500 text-xs">
                  <!-- Will be populated dynamically -->
                </tbody>
              </table>
            </div>
          </div>

          {{-- Editable Comments & Remarks Section --}}
          <div id="ls-comments-section" class="mt-4 space-y-3">

            {{-- DCIV Investigation Notice (shown only when property is under investigation) --}}
            <div id="investigation-notice-section" class="p-3 bg-red-50 border border-red-400 rounded-lg hidden">
              <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <p id="investigation-notice-text" class="text-sm font-semibold text-red-700">This Property is under Investigation.</p>
              </div>
            </div>

            {{-- Client Details (Pay-Per-Search) --}}
            {{-- Prefilled from the file's legal_search_token when a token is verified,
                 but editable here. These values print in the Property Details block of
                 the Pay-Per-Search report. --}}
            <div id="client-details-section" class="p-3 bg-slate-50 border border-slate-200 rounded-lg hidden">
              <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-slate-800">Client Details <span class="font-normal text-slate-500">(prints on the search report)</span></h4>
                <button type="button" id="update-client-details-btn" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-slate-700 text-white hover:bg-slate-800">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  Update
                </button>
              </div>
              <div class="flex items-center gap-3">
                <div class="flex-1 flex items-center gap-1">
                  <label class="text-xs font-medium text-gray-700 whitespace-nowrap">Client Name</label>
                  <input type="text" id="comment-client_name-text" class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded" placeholder="Client name">
                </div>
                <div class="flex-1 flex items-center gap-1">
                  <label class="text-xs font-medium text-gray-700 whitespace-nowrap">Client Address</label>
                  <input type="text" id="comment-client_address-text" class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded" placeholder="Client address">
                </div>
              </div>
              <p id="update-client-details-status" class="text-xs mt-1 hidden"></p>
            </div>

            {{-- Ground Rent --}}
            <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-amber-800">Ground Rent Including Land Use Charge Not Paid</h4>
                <button class="save-comment-btn inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-amber-600 text-white hover:bg-amber-700" data-type="ground_rent">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  Save
                </button>
              </div>
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1">
                  <label class="text-xs font-medium text-gray-700 whitespace-nowrap">Amounting to ₦</label>
                  <input type="number" id="comment-ground_rent-amount" step="0.01" min="0" class="w-36 px-2 py-1 text-xs border border-gray-300 rounded" placeholder="0.00">
                </div>
                <div class="flex-1 flex items-center gap-1">
                  <label class="text-xs font-medium text-gray-700">Comment</label>
                  <input type="text" id="comment-ground_rent-text" class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded" placeholder="Optional comment">
                </div>
              </div>
              <p class="comment-status text-xs mt-1 hidden" data-type="ground_rent"></p>
            </div>

            {{-- Litigation Comment --}}
            <div class="p-3 bg-rose-50 border border-rose-200 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-rose-800">Litigation Comment</h4>
                <button class="save-comment-btn inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-rose-600 text-white hover:bg-rose-700" data-type="litigation">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  Save
                </button>
              </div>
              <div class="flex-1 flex items-center gap-1">
                <label class="text-xs font-medium text-gray-700 whitespace-nowrap">Comment</label>
                <input type="text" id="comment-litigation-text" class="flex-1 px-2 py-1 text-xs border border-gray-300 rounded" placeholder="E.g. Subject title is currently under litigation in Suit No. ...">
              </div>
              <p class="comment-status text-xs mt-1 hidden" data-type="litigation"></p>
            </div>

            {{-- No CoFO Remark --}}

            <div id="no-cofo-comment-section" class="p-3 bg-blue-50 border border-blue-200 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-blue-800">No Certificate of Occupancy Remark</h4>
                <button class="save-comment-btn inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-600 text-white hover:bg-blue-700" data-type="no_cofo">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  Save
                </button>
              </div>
              <input type="text" id="comment-no_cofo-text" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="Based on our available records, the subject title is currently at the Letter of Grant stage, hence Certificate of Occupancy is yet to be issued. However the title is free from encumbrances.">
              <p class="comment-status text-xs mt-1 hidden" data-type="no_cofo"></p>
            </div>

            {{-- Encumbrance / Free Title Remark --}}
            <div id="encumbrance-comment-section" class="p-3 bg-green-50 border border-green-200 rounded-lg">
              <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-green-800">Title Encumbrance Remark</h4>
                <button class="save-comment-btn inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-600 text-white hover:bg-green-700" data-type="encumbrance">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  Save
                </button>
              </div>
              <input type="text" id="comment-encumbrance-text" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="Based on our available records, the title is free from encumbrances.">
              <p class="comment-status text-xs mt-1 hidden" data-type="encumbrance"></p>
            </div>

            {{-- W/R/C (Withdrawn / Revoked / Cancelled) Remark --}}
            {{-- Only shown when the searched file is tagged [WRC] in duplicate_fileno.
                 Leave blank to use the default wording; type here to override it. --}}
            <div id="wrc-comment-section" class="p-3 bg-red-50 border border-red-200 rounded-lg hidden">
              <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold text-red-800">W/R/C Remark <span class="font-normal text-red-500">(shown only for Withdrawn / Revoked / Cancelled files)</span></h4>
                <button class="save-comment-btn inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-600 text-white hover:bg-red-700" data-type="wrc">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                  Save
                </button>
              </div>
              <input type="text" id="comment-wrc-text" class="w-full px-2 py-1 text-xs border border-gray-300 rounded" value="N.B. This Application has been Cancelled !!!">
              <p class="comment-status text-xs mt-1 hidden" data-type="wrc"></p>
            </div>

          </div>

        </div>
      </div>
    </div>

    {{-- Section 4: Weighting Transparency --}}
    <div id="weighting-section" class="mt-6 bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hidden">
      <div class="p-6">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold">Weighing</h3>
            <p class="text-sm text-gray-500">Source scoring &amp; deduplication transparency</p>
          </div>
          <button id="toggle-weighting-btn" class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium border bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100">
            Collapse
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr>
                <th class="text-left">S/N</th>
                <th class="text-left">Fingerprint</th>
                <th class="text-left">Source</th>
                <th class="text-center">Record-to-Record</th>
                <th class="text-center">Table-to-Table</th>
                <th class="text-center">Timeline</th>
                <th class="text-center">Status</th>
                <th class="text-left">Instrument / Date</th>
              </tr>
            </thead>
            <tbody id="weighting-table">
              <!-- Populated by renderWeightingTable() -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Edit Record Modal --}}
    <div id="edit-record-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" id="edit-modal-backdrop"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-5xl w-full max-h-[90vh] overflow-y-auto z-10">
          <div class="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold">Edit Record</h3>
            <button id="edit-modal-close" class="text-gray-400 hover:text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
          </div>
          <div class="px-6 py-5 bg-gray-50/40" id="edit-modal-body">
            <!-- Fields rendered dynamically -->
          </div>
          <div class="sticky bottom-0 bg-gray-50 border-t px-6 py-3 flex justify-end gap-2">
            <button id="edit-modal-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">Cancel</button>
            <button id="edit-modal-save" class="px-4 py-2 text-sm font-medium text-white bg-black rounded-md hover:bg-black/90">Save Changes</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Record Comment Modal --}}
    <div id="ls-add-comment-modal" class="fixed inset-0 z-[9999] hidden" aria-modal="true" role="dialog">
      <div class="absolute inset-0 bg-black/50" id="ls-add-comment-backdrop"></div>
      <div class="relative z-10 flex items-center justify-center min-h-screen px-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col">
          {{-- Header --}}
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Record Comment</h3>
              <p class="text-xs text-gray-500 mt-0.5" id="ls-add-comment-row-label"></p>
            </div>
            <button id="ls-add-comment-close" type="button" class="text-gray-400 hover:text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          {{-- Body --}}
          <div class="px-6 py-4 space-y-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Comment</label>
              <textarea id="ls-add-comment-text" rows="5" class="form-input text-sm w-full resize-none" placeholder="Type your comment here…"></textarea>
            </div>
            <p id="ls-add-comment-status" class="text-xs hidden"></p>
          </div>
          {{-- Footer --}}
          <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 rounded-b-xl">
            <button id="ls-add-comment-cancel" type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button id="ls-add-comment-save" type="button" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50">Save Comment</button>
          </div>
        </div>
      </div>
    </div>

    {{-- Conflict Warning Modal --}}
    {{-- Transfer Caveat Modal --}}
    <div id="transfer-caveat-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50" id="transfer-caveat-backdrop"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-lg w-full z-10 p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Transfer Caveat</h3>
            <button id="transfer-caveat-close" class="text-gray-400 hover:text-gray-600">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>
          <p class="text-sm text-gray-600 mb-1">Transfer the caveat <strong>from</strong> the selected record <strong>to</strong>:</p>
          <p class="text-xs text-gray-400 mb-3" id="transfer-caveat-source-label"></p>
          <label class="block text-xs font-medium text-gray-700 mb-1">Select target record</label>
          <select id="transfer-caveat-select" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400 mb-4">
            <option value="">-- Choose a record --</option>
          </select>
          <p class="text-xs text-red-600 hidden mb-2" id="transfer-caveat-error"></p>
          <div class="flex justify-end gap-2">
            <button id="transfer-caveat-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button id="transfer-caveat-confirm" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700" disabled>Transfer</button>
          </div>
        </div>
      </div>
    </div>

    <div id="conflict-modal" class="fixed inset-0 z-50 hidden overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black/50" id="conflict-modal-backdrop"></div>
        <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full z-10 p-6">
          <div class="flex items-center mb-4">
            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" /></svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900">Prop ID Conflict</h3>
          </div>
          <p class="text-sm text-gray-600 mb-2">The selected records belong to different prop_id groups:</p>
          <div id="conflict-prop-ids" class="mb-4 text-sm font-mono bg-gray-50 rounded p-2"></div>
          <p class="text-sm text-gray-600 mb-4">Are you sure you want to proceed?</p>
          <div class="flex justify-end gap-2">
            <button id="conflict-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <button id="conflict-proceed" class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700">Proceed Anyway</button>
          </div>
        </div>
      </div>
    </div>

    {{-- ======= Place Caveat Modal (Legal Search) ======= --}}
    <div id="ls-caveat-modal" class="fixed inset-0 z-[9999] hidden" aria-modal="true" role="dialog">
      {{-- Backdrop --}}
      <div class="absolute inset-0 bg-black/50" id="ls-caveat-backdrop"></div>
      {{-- Dialog --}}
      <div class="relative z-10 flex items-center justify-center min-h-screen px-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
          {{-- Header --}}
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
              <h3 class="text-lg font-semibold text-gray-900">Save Caveat</h3>
              <p class="text-xs text-gray-500 mt-0.5">Recording an existing caveat not yet in the system</p>
            </div>
            <button id="ls-caveat-close" type="button" class="text-gray-400 hover:text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          {{-- Expired toggle banner --}}
          <div class="px-6 pt-4">
            <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-gray-50 cursor-pointer select-none">
              <input type="checkbox" id="ls-caveat-is-expired" class="rounded border-gray-300 text-red-600 focus:ring-red-500 h-4 w-4">
              <div>
                <span class="text-sm font-medium text-gray-800">This caveat has already expired / been lifted</span>
                <p class="text-xs text-gray-500">Record will be saved as <strong>Expired</strong> — <code>is_caveated</code> will remain <strong>0</strong>, but a historical comment will be written and the row saved to the caveat table.</p>
              </div>
            </label>
          </div>

          {{-- Body (scrollable) --}}
          <form id="ls-caveat-form" class="overflow-y-auto px-6 py-4 space-y-4 flex-1">
            @csrf
            <input type="hidden" id="ls-caveat-file-number" name="file_number">
            <input type="hidden" id="ls-caveat-number" name="caveat_number">
            <input type="hidden" id="ls-caveat-number-scheme" name="caveat_number_scheme" value="ADD-CAV">
            <input type="hidden" id="ls-caveat-source-table" name="source_table">
            <input type="hidden" id="ls-caveat-source-id" name="source_id">
            <input type="hidden" name="bypass_record_check" value="1">
            <input type="hidden" id="ls-caveat-is-expired-input" name="is_expired" value="0">

            {{-- Encumbrance & Instrument --}}
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Encumbrance Type <span class="text-red-500">*</span></label>
                <select name="encumbrance_type" id="ls-caveat-encumbrance" class="form-select text-sm w-full" required>
                  <option value="">Select encumbrance type</option>
                  <option value="Mortgage">Mortgage</option>
                  <option value="Lien">Lien</option>
                  <option value="Charge">Charge</option>
                  <option value="Leasehold Interest">Leasehold Interest</option>
                  <option value="Sub-Lease/Sub-Under Lease">Sub-Lease/Sub-Under Lease</option>
                  <option value="Easement/Right of Way">Easement/Right of Way</option>
                  <option value="Court Order/Restraining Order">Court Order/Restraining Order</option>
                  <option value="Pending Litigation (Lis Pendens)">Pending Litigation (Lis Pendens)</option>
                  <option value="Power of Attorney">Power of Attorney</option>
                  <option value="Caution (General or Specific)">Caution (General or Specific)</option>
                  <option value="Dispute/Investigation Tag">Dispute/Investigation Tag</option>
                  <option value="Deed of Assignment/Transfer Not Completed">Deed of Assignment/Transfer Not Completed</option>
                  <option value="Probate/Letters of Administration">Probate/Letters of Administration</option>
                  <option value="Government Acquisition/Reservation">Government Acquisition/Reservation</option>
                  <option value="Unpaid Land Charges/Fees">Unpaid Land Charges/Fees</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Instrument/Transaction Type</label>
                <select name="instrument_type_id" id="ls-caveat-instrument-type" class="form-select text-sm w-full">
                  <option value="">Loading…</option>
                </select>
              </div>
            </div>

            {{-- Parties --}}
            <div class="grid grid-cols-3 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Applicant/Solicitor <span class="text-red-500">*</span></label>
                <input type="text" name="petitioner" id="ls-caveat-petitioner" class="form-input text-sm w-full" placeholder="Name of petitioner" required>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Party 1</label>
                <input type="text" name="grantor" id="ls-caveat-grantor" class="form-input text-sm w-full" placeholder="Name of party 1">
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Party 2</label>
                <input type="text" name="grantee" id="ls-caveat-grantee" class="form-input text-sm w-full" placeholder="Name of party 2">
              </div>
            </div>

            {{-- Location --}}
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Location</label>
              <input type="text" name="location" id="ls-caveat-location" class="form-input text-sm w-full" placeholder="Property location">
            </div>

            {{-- Registration particulars --}}
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-2">Registration Particulars</label>
              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="text-xs text-gray-500">Serial No.</label>
                  <input type="text" name="serial_no" id="ls-caveat-serial" class="form-input text-sm w-full" placeholder="e.g. 35">
                </div>
                <div>
                  <label class="text-xs text-gray-500">Page No. <span class="text-gray-400">(Auto-filled)</span></label>
                  <input type="text" name="page_no" id="ls-caveat-page" class="form-input text-sm w-full bg-gray-100 text-gray-500" readonly placeholder="Matches Serial No.">
                </div>
                <div>
                  <label class="text-xs text-gray-500">Volume No.</label>
                  <input type="text" name="volume_no" id="ls-caveat-volume" class="form-input text-sm w-full" placeholder="e.g. 100">
                </div>
              </div>
              {{-- Registration Number auto-display --}}
              <div class="mt-2">
                <label class="text-xs text-gray-500">Registration Number (Auto-generated)</label>
                <div id="ls-caveat-reg-number" class="text-sm font-mono bg-gray-50 p-2 rounded border border-gray-200 min-h-[36px] flex items-center text-gray-400">
                  Enter Serial No. and Volume No. to generate
                </div>
                <p class="text-xs text-gray-400 mt-1">Format: [Serial No]/[Page No]/[Volume No]</p>
              </div>
              {{-- Reg Date + Reg Time --}}
              <div class="grid grid-cols-2 gap-3 mt-3">
                <div>
                  <label class="text-xs text-gray-500">Reg Date</label>
                  <input type="date" name="reg_date" id="ls-caveat-reg-date" class="form-input text-sm w-full">
                </div>
                <div>
                  <label class="text-xs text-gray-500">Reg Time</label>
                  <input type="time" name="reg_time" id="ls-caveat-reg-time" class="form-input text-sm w-full">
                </div>
              </div>
            </div>

            {{-- Dates --}}
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Date Placed <span class="text-red-500">*</span></label>
                <input type="datetime-local" name="start_date" id="ls-caveat-start-date" class="form-input text-sm w-full" required>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Release Date</label>
                <input type="date" name="release_date" id="ls-caveat-release-date" class="form-input text-sm w-full bg-gray-100 text-gray-500" readonly>
                <p class="text-xs text-gray-400 mt-1">Automatically set to 6 months after Date Placed</p>
              </div>
            </div>
            {{-- Hidden expiry wrapper kept for JS compatibility --}}
            <div id="ls-caveat-expiry-wrapper" class="hidden"></div>

            {{-- Legal Search Comment (Auto-Generated) --}}
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Legal Search Comment</label>
              <div id="ls-caveat-legal-comment" class="w-full p-3 border rounded-md bg-amber-50 border-amber-200 text-sm text-gray-700 min-h-[60px]">
                <span class="text-gray-400 italic">Auto-generated when form fields are filled</span>
              </div>
            </div>

            {{-- Remarks --}}
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Remarks / Instructions</label>
              <textarea name="remarks" id="ls-caveat-remarks" rows="2" class="form-input text-sm w-full resize-none" placeholder="Any additional notes…"></textarea>
            </div>

            {{-- Error area --}}
            <div id="ls-caveat-error" class="hidden rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
          </form>

          {{-- Footer --}}
          <div class="flex items-center justify-between px-6 py-4 border-t bg-gray-50 rounded-b-xl">
            <p class="text-xs text-gray-400">File: <span id="ls-caveat-file-display" class="font-medium text-gray-600"></span></p>
            <div class="flex gap-3">
              <button type="button" id="ls-caveat-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
              <button type="button" id="ls-caveat-submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
                <span id="ls-caveat-submit-label">Save Caveat</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    {{-- ======= /Place Caveat Modal ======= --}}
