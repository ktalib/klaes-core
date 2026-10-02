<script>
  // Lookup data from DB
  const dbLandUseOptions = @json($landUseOptions ?? []);
  const dbDistrictOptions = @json($districtOptions ?? []);

  // Mock data for the application
  const monthlyData = [
    { month: "Jan", searches: 18, revenue: 270000 },
    { month: "Feb", searches: 22, revenue: 330000 },
    { month: "Mar", searches: 25, revenue: 375000 },
    { month: "Apr", searches: 20, revenue: 300000 },
    { month: "May", searches: 28, revenue: 420000 },
    { month: "Jun", searches: 32, revenue: 480000 },
    { month: "Jul", searches: 35, revenue: 525000 },
    { month: "Aug", searches: 30, revenue: 450000 },
    { month: "Sep", searches: 26, revenue: 390000 },
    { month: "Oct", searches: 22, revenue: 330000 },
    { month: "Nov", searches: 20, revenue: 300000 },
    { month: "Dec", searches: 24, revenue: 360000 }
  ];

  // Helper to generate registration numbers in XX/XX/YYY format
  function generateRegNumber() {
    const prefix = Math.floor(Math.random() * 90) + 10; // 10-99
    const suffix = Math.floor(Math.random() * 300) + 1; // 1-300
    return `${prefix}/${prefix}/${suffix}`;
  }

  // DOM Elements
  const searchModal = document.getElementById('search-modal');
  const searchRecordsBtn = document.getElementById('search-records-btn');
  const filterSelector = document.getElementById('filter-selector');
  const filtersContainer = document.getElementById('filters-container');
  const toggleFiltersBtn = document.getElementById('toggle-filters-btn');
  const filtersChevron = document.getElementById('filters-chevron');
  const filtersPanel = document.getElementById('filters-panel');
  const resetSearchBtn = document.getElementById('reset-search-btn');
  const searchLoading = document.getElementById('search-loading');
  const noResultsMessage = document.getElementById('no-results-message');
  const fileInfoFallback = document.getElementById('file-info-fallback');
  const fileInfoFallbackBody = document.getElementById('file-info-fallback-body');
  const tableResults = document.getElementById('table-results');
  const tableResultsBody = document.getElementById('table-results-body');
  const cardResults = document.getElementById('card-results');
  const fileDetailsView = document.getElementById('file-details-view');
  const resultsCount = document.getElementById('results-count');
  const viewTabs = document.querySelectorAll('.tab');
  const dashboardView = document.getElementById('dashboard-view');
  const fileHistoryView = document.getElementById('file-history-view');
  const reportsView = document.getElementById('reports-view');

  const deleteConfirmDialog = document.getElementById('delete-confirm-dialog');
  const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
  const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
  const newSearchFromDetailsBtn = document.getElementById('new-search-from-details-btn');
  const legalSearchReportView = document.getElementById('legal-search-report-view');
  const backToFileDetailsBtn = document.getElementById('back-to-file-details-btn');
  const printReportBtn = document.getElementById('print-report-btn');
  const fileNumberInput = document.getElementById('fileNumber');
  const openFilenoSelectorBtn = document.getElementById('open-fileno-selector-btn');
  const clearFilenoBtn = document.getElementById('clear-fileno-btn');
  const searchNowBtn = document.getElementById('search-now-btn');
  const filenoInfoBadge = document.getElementById('fileno-info-badge');
  const filenoTypeLabel = document.getElementById('fileno-type-label');
  const filenoOwnerLabel = document.getElementById('fileno-owner-label');
  const activeFilterCount = document.getElementById('active-filter-count');
  // Filter label map for display
  const filterLabelMap = {
    guarantorName: 'Party 1',
    guaranteeName: 'Party 2',
    lga: 'LGA',
    district: 'District',
    location: 'Location',
    plotNumber: 'Plot Number',
    planNumber: 'Plan Number',
    size: 'Size',
    caveat: 'Caveat'
  };

  // Debug mode elements
  const toggleDebugBtn = document.getElementById('toggle-debug-btn');
  const sourceBadges = document.getElementById('source-badges');
  let lastApiResponse = null;

  // Debug statements
  console.log("Search modal element:", searchModal);
  console.log("Search records button:", searchRecordsBtn);
  console.log("Search modal class list:", searchModal ? searchModal.classList : "Modal not found");

  // Add document ready event to ensure DOM is fully loaded
  document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM fully loaded, initializing search elements");

    // Initialize GlobalFileNoModal
    if (typeof GlobalFileNoModal !== 'undefined' && typeof GlobalFileNoModal.init === 'function') {
      GlobalFileNoModal.init();
      console.log('GlobalFileNoModal initialized for Legal Search');
    }
    
    // Re-query elements to ensure they're available
    const searchModalRecheck = document.getElementById('search-modal');
    const searchRecordsBtnRecheck = document.getElementById('search-records-btn');
    
    console.log("Search modal element (recheck):", searchModalRecheck);
    console.log("Search records button (recheck):", searchRecordsBtnRecheck);
    
    // Add click handler directly to the button element
    if (searchRecordsBtnRecheck) {
      searchRecordsBtnRecheck.onclick = function() {
        console.log("Search records button clicked via direct onclick");
        if (searchModalRecheck) {
          searchModalRecheck.classList.remove('hidden');
          console.log("Modal hidden class removed, current classes:", searchModalRecheck.classList);
        } else {
          console.error("Search modal element not found when trying to show it");
        }
      };
    }

    // Auto-search from URL params
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('query')) {
        const queryVal = urlParams.get('query');
        if (document.getElementById('fileNumber')) {
            document.getElementById('fileNumber').value = queryVal;
        }

        // Also map other filters if present
        const otherFilters = ['guarantorName', 'guaranteeName', 'lga', 'district', 'location', 'plotNumber', 'planNumber', 'size', 'caveat'];
        let hasOther = false;
        otherFilters.forEach(f => {
            if (urlParams.has(f) && urlParams.get(f)) {
                if (filterSelector) {
                    filterSelector.value = f;
                    // Trigger addFilterRow
                    if (typeof addFilterRow === 'function') addFilterRow(f);

                    setTimeout(() => {
                        if (document.getElementById(f)) {
                            document.getElementById(f).value = urlParams.get(f);
                        }
                    }, 100);
                }
                hasOther = true;
            }
        });

        // Open the search modal so results are visible when auto-searching
        const searchModalEl = document.getElementById('search-modal');
        if (searchModalEl) searchModalEl.classList.remove('hidden');

        // Auto trigger search after a small delay
        setTimeout(() => {
            if (typeof performSearch === 'function') {
                performSearch();
            }
        }, 500);
    }
  });

  // State variables
  let currentView = 'table';
  let selectedFile = null;
  let transactionToDelete = null;
  let searchResults = [];
  
  // Manual Deduplication Overrides
  window._manualDroppedIds = new Set();
  window._manualIncludedIds = new Set();
  
  const getRecordUid = (item) => {
    const table = String(item.source_table || '').trim() === 'File History' ? 'file_history_staging' : 
                  String(item.source_table || '').trim() === 'PRA' ? 'pra' :
                  String(item.source_table || '').trim() === 'CofO' ? 'CofO_staging' :
                  String(item.source_table || '').trim() === 'Deed Registration' ? 'deed_registrations' :
                  String(item.source_table || '').trim();
    return `${table}::${item.id}`;
  };

  // The file number the user explicitly searched for (via the File Number
  // Selector modal or by typing). This is preserved across related-file
  // card clicks so the search header always reflects what the user picked.
  let userSelectedFileNumber = '';

  // Initialize the search trends chart
  const initializeChart = () => {
    const ctx = document.getElementById('searchTrendsChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: monthlyData.map(d => d.month),
        datasets: [{
          label: 'Searches',
          data: monthlyData.map(d => d.searches),
          borderColor: '#3B82F6',
          backgroundColor: 'rgba(59, 130, 246, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
          },
          title: {
            display: true,
            text: 'Monthly Search Volume'
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            title: {
              display: true,
              text: 'Number of Searches'
            }
          },
          x: {
            title: {
              display: true,
              text: 'Month'
            }
          }
        }
      }
    });
  };

  // Initialize the chart when the page loads
  document.addEventListener('DOMContentLoaded', initializeChart);

  // Event Listeners
  if (searchRecordsBtn) {
    searchRecordsBtn.addEventListener('click', () => {
      console.log("Search records button clicked");
      searchModal.classList.remove('hidden');
    });
  } else {
    console.error("Search records button not found");
  }

  if (newSearchFromDetailsBtn) {
    newSearchFromDetailsBtn.addEventListener('click', () => {
      console.log("New search from details button clicked");
      searchModal.classList.remove('hidden');
    });
  }

  // Close modal when clicking outside
  if (searchModal) {
    searchModal.addEventListener('click', (e) => {
      if (e.target === searchModal) {
        searchModal.classList.add('hidden');
      }
    });
  }

  // ── Dropdown-based Additional Filters ─────────────────────────
  const activeFilters = new Set();

  const updateFilterCount = () => {
    const count = activeFilters.size;
    if (activeFilterCount) {
      if (count > 0) {
        activeFilterCount.textContent = count;
        activeFilterCount.classList.remove('hidden');
      } else {
        activeFilterCount.classList.add('hidden');
      }
    }
  };

  const addFilterRow = (filterId) => {
    if (activeFilters.has(filterId)) return;
    const tpl = document.getElementById('tpl-' + filterId);
    if (!tpl) return;

    activeFilters.add(filterId);

    // Hide option in selector
    const opt = filterSelector.querySelector('option[value="' + filterId + '"]');
    if (opt) opt.disabled = true;
    filterSelector.value = '';

    // Build grid cell
    const row = document.createElement('div');
    row.className = 'flex flex-col gap-1';
    row.setAttribute('data-filter-row', filterId);

    // Header: label + remove button
    const header = document.createElement('div');
    header.className = 'flex items-center justify-between';

    const label = document.createElement('span');
    label.className = 'text-xs font-medium text-gray-500';
    label.textContent = filterLabelMap[filterId] || filterId;
    header.appendChild(label);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'p-0.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition';
    removeBtn.title = 'Remove filter';
    removeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
    removeBtn.addEventListener('click', () => removeFilterRow(filterId));
    header.appendChild(removeBtn);

    row.appendChild(header);

    const clone = tpl.content.cloneNode(true);
    // Make input/select full width inside cell
    const el = clone.querySelector('input, select');
    if (el) { el.classList.remove('flex-1'); el.classList.add('w-full'); }
    row.appendChild(clone);

    filtersContainer.appendChild(row);
    updateFilterCount();

    // Focus the new input
    const input = row.querySelector('input, select');
    if (input) input.focus();
  };

  const removeFilterRow = (filterId) => {
    const row = filtersContainer.querySelector('[data-filter-row="' + filterId + '"]');
    if (row) row.remove();
    activeFilters.delete(filterId);

    // Re-enable option in selector
    const opt = filterSelector.querySelector('option[value="' + filterId + '"]');
    if (opt) opt.disabled = false;

    updateFilterCount();
  };

  const removeAllFilterRows = () => {
    filtersContainer.innerHTML = '';
    activeFilters.clear();
    // Re-enable all options
    filterSelector.querySelectorAll('option').forEach(opt => opt.disabled = false);
    filterSelector.value = '';
    updateFilterCount();
    // Collapse panel
    if (filtersPanel) filtersPanel.classList.add('hidden');
    if (filtersChevron) filtersChevron.classList.remove('rotate-90');
  };

  // Toggle Additional Filters panel
  if (toggleFiltersBtn && filtersPanel && filtersChevron) {
    toggleFiltersBtn.addEventListener('click', () => {
      const open = !filtersPanel.classList.contains('hidden');
      filtersPanel.classList.toggle('hidden', open);
      filtersChevron.classList.toggle('rotate-90', !open);
    });
  }

  if (filterSelector) {
    filterSelector.addEventListener('change', () => {
      const val = filterSelector.value;
      if (val) addFilterRow(val);
    });
  }

  // ── Global File Number Selector integration ──────────────────────
  if (openFilenoSelectorBtn) {
    openFilenoSelectorBtn.addEventListener('click', () => {
      if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
        console.warn('GlobalFileNoModal not available, selector button disabled');
        return;
      }
      window.GlobalFileNoModal.open({
        autoPopulateGenericFields: false,
        callback: function(fileData) {
          if (fileData && fileData.fileNumber) {
            fileNumberInput.value = fileData.fileNumber;
            userSelectedFileNumber = String(fileData.fileNumber).trim();

            // Keep the picked record so the File Information card can fall back to
            // its title/location when the file is not in file_indexings.
            window.__lsSelectedRecord = fileData.record || null;
            window.__lsSelectedFileTitle = (fileData.file_title || fileData.file_name || '').toString().trim();

            // Enable search button
            if (searchNowBtn) {
              searchNowBtn.disabled = false;
              searchNowBtn.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
              searchNowBtn.classList.add('bg-gray-900', 'text-white', 'hover:bg-gray-800');
            }

            // Show clear button
            if (clearFilenoBtn) clearFilenoBtn.classList.remove('hidden');

            // Show info badge
            if (filenoInfoBadge) {
              filenoInfoBadge.classList.remove('hidden');
              filenoInfoBadge.classList.add('flex');
              if (filenoTypeLabel) filenoTypeLabel.textContent = (fileData.tab || fileData.system || '').toUpperCase();
              if (filenoOwnerLabel && fileData.record) {
                const ownerName = fileData.record.file_name || fileData.record.FileName || fileData.record.applicant_name || fileData.record.owner_name || '';
                filenoOwnerLabel.textContent = ownerName ? '— ' + ownerName : '';
              }
            }

            // Auto-trigger search
            performSearch();
          }
        }
      });
    });
  }

  // Clear file number
  if (clearFilenoBtn) {
    clearFilenoBtn.addEventListener('click', () => {
      fileNumberInput.value = '';
      userSelectedFileNumber = '';
      window.__lsSelectedRecord = null;
      window.__lsSelectedFileTitle = '';
      clearFilenoBtn.classList.add('hidden');
      if (filenoInfoBadge) {
        filenoInfoBadge.classList.add('hidden');
        filenoInfoBadge.classList.remove('flex');
      }
      // Disable search button
      if (searchNowBtn) {
        searchNowBtn.disabled = true;
        searchNowBtn.classList.remove('bg-gray-900', 'text-white', 'hover:bg-gray-800');
        searchNowBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
      }
    });
  }

  // Show/hide clear button on manual typing
  if (fileNumberInput) {
    fileNumberInput.addEventListener('input', () => {
      if (fileNumberInput.value.trim()) {
        if (clearFilenoBtn) clearFilenoBtn.classList.remove('hidden');
      } else {
        if (clearFilenoBtn) clearFilenoBtn.classList.add('hidden');
        if (filenoInfoBadge) {
          filenoInfoBadge.classList.add('hidden');
          filenoInfoBadge.classList.remove('flex');
        }
      }
    });
    // Search on Enter key
    fileNumberInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        performSearch();
      }
    });
  }

  // Search Now button
  if (searchNowBtn) {
    searchNowBtn.addEventListener('click', () => {
      performSearch();
    });
  }

  // Reset search
  const resetSearch = () => {
    // Clear file number
    document.getElementById('fileNumber').value = '';
    if (clearFilenoBtn) clearFilenoBtn.classList.add('hidden');
    if (filenoInfoBadge) {
      filenoInfoBadge.classList.add('hidden');
      filenoInfoBadge.classList.remove('flex');
    }
    // Disable search button
    if (searchNowBtn) {
      searchNowBtn.disabled = true;
      searchNowBtn.classList.remove('bg-gray-900', 'text-white', 'hover:bg-gray-800');
      searchNowBtn.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
    }

    // Clear additional filters
    removeAllFilterRows();

    // Reset results
    searchResults = [];
    resultsCount.textContent = '0';
    tableResultsBody.innerHTML = '';
    cardResults.innerHTML = '';
    
    // Hide results views
    tableResults.classList.add('hidden');
    cardResults.classList.add('hidden');
    fileDetailsView.classList.add('hidden');
    noResultsMessage.classList.add('hidden');
    if (fileInfoFallback) fileInfoFallback.classList.add('hidden');

    // Clear debug state
    if (sourceBadges) sourceBadges.classList.add('hidden');
    lastApiResponse = null;

    // Reset selected file
    selectedFile = null;
    userSelectedFileNumber = '';
  };

  if (resetSearchBtn) {
    resetSearchBtn.addEventListener('click', resetSearch);
  }

  // Perform search based on filter values
  const performSearch = () => {
    // Capture the file number the user is searching for so it persists
    // even when they later click on a related-file card in the results.
    const _fnInputVal = (document.getElementById('fileNumber')?.value || '').trim();
    if (_fnInputVal) {
      userSelectedFileNumber = _fnInputVal;
    }
    // Get all filter values
    const filters = {
      fileNumber: document.getElementById('fileNumber').value,
      guarantorName: document.getElementById('guarantorName') ? document.getElementById('guarantorName').value : '',
      guaranteeName: document.getElementById('guaranteeName') ? document.getElementById('guaranteeName').value : '',
      lga: document.getElementById('lga') ? document.getElementById('lga').value : '',
      district: document.getElementById('district') ? document.getElementById('district').value : '',
      location: document.getElementById('location') ? document.getElementById('location').value : '',
      plotNumber: document.getElementById('plotNumber') ? document.getElementById('plotNumber').value : '',
      planNumber: document.getElementById('planNumber') ? document.getElementById('planNumber').value : '',
      size: document.getElementById('size') ? document.getElementById('size').value : '',
      caveat: document.getElementById('caveat') ? document.getElementById('caveat').value : ''
    };

    // Preserve the exact file number used in the latest search for LS modal prefill.
    window.__lsLastSearchedFileNumber = (filters.fileNumber || '').trim();

    // Check if at least one search parameter has a value
    const hasSearchCriteria = Object.values(filters).some(value => value && value.trim() !== '');
    
    if (!hasSearchCriteria) {
      searchResults = [];
      resultsCount.textContent = '0';
      tableResults.classList.add('hidden');
      cardResults.classList.add('hidden');
      noResultsMessage.classList.add('hidden');
      if (fileInfoFallback) fileInfoFallback.classList.add('hidden');
      return;
    }

    // Prepare data for AJAX call
    const searchData = {
      _token: '{{ csrf_token() }}',
      query: filters.fileNumber || '',
      guarantorName: filters.guarantorName || '',
      guaranteeName: filters.guaranteeName || '',
      lga: filters.lga || '',
      district: filters.district || '',
      location: filters.location || '',
      plotNumber: filters.plotNumber || '',
      planNumber: filters.planNumber || '',
      size: filters.size || '',
      caveat: filters.caveat || ''
    };

    // Preserve last payload so the silent refresh button can reuse it
    window.__lsLastSearchData = searchData;

    // On-Premise Token Check Logic
    if (window.LEGAL_SEARCH_CONTEXT.searchRouteName === 'onpremise.search') {
        const fileNumber = (document.getElementById('fileNumber')?.value || '').trim();
        if (!fileNumber) {
            Swal.fire('Error', 'Please select a file number first.', 'error');
            return;
        }

        // Check for available token
        $.ajax({
            url: "{{ route('legal-search-tokens.check') }}",
            method: 'POST',
            data: { 
                _token: '{{ csrf_token() }}',
                file_number: fileNumber 
            },
            success: function(response) {
                if (response.success) {
                    // Admin bypass
                    if (response.bypass) {
                        executeSearchAjax(filters, searchData);
                        return;
                    }
                    
                    // Token exists, ask for it
                    Swal.fire({
                        title: 'Search Token Found',
                        html: `
                            <div class="text-left bg-slate-50 p-4 rounded-2xl border border-slate-200 mb-4">
                                <div class="mb-3">
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Applicant Name</label>
                                    <div class="text-sm font-bold text-slate-800">${response.applicant_name}</div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Property Location</label>
                                    <div class="text-[11px] font-medium text-slate-500 leading-relaxed">${response.property_location || 'N/A'}</div>
                                </div>
                            </div>
                            <p class="text-sm text-slate-600 mb-2">Valid token found for File No: <b>${fileNumber}</b>. Click below to verify and proceed.</p>
                        `,
                        input: 'password',
                        inputValue: response.token,
                        inputAttributes: {
                            autocapitalize: 'off',
                            placeholder: 'Enter 12-character token',
                            readonly: 'readonly'
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Verify & Search',
                        confirmButtonColor: '#4f46e5',
                        showLoaderOnConfirm: true,
                        preConfirm: (token) => {
                            if (!token || token.length !== 12) {
                                Swal.showValidationMessage('Token must be exactly 12 characters.');
                                return false;
                            }
                            return $.ajax({
                                url: "{{ route('legal-search-tokens.use') }}",
                                method: 'POST',
                                data: {
                                    _token: '{{ csrf_token() }}',
                                    file_number: fileNumber,
                                    token: token
                                }
                            }).catch(error => {
                                Swal.showValidationMessage(
                                    `Error: ${error.responseJSON ? error.responseJSON.message : 'Invalid Token'}`
                                );
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Stash the token's client details and prefill the editable
                            // Client Details fields so they print on the report.
                            window.__lsTokenClient = {
                                name: response.client_name || '',
                                address: response.client_address || ''
                            };
                            const _cnField = document.getElementById('comment-client_name-text');
                            const _caField = document.getElementById('comment-client_address-text');
                            if (_cnField) _cnField.value = response.client_name || '';
                            if (_caField) _caField.value = response.client_address || '';
                            // Token verified and used, proceed with search
                            executeSearchAjax(filters, searchData);
                        }
                    });
                } else {
                    // No token found
                    Swal.fire({
                        title: 'Access Denied',
                        text: response.message,
                        icon: 'warning',
                        confirmButtonText: 'Understood',
                        confirmButtonColor: '#ef4444'
                    });
                }
            },
            error: function() {
                Swal.fire('Error', 'Could not verify search token status.', 'error');
            }
        });
        return; 
    }

    // Default search execution for other modules
    executeSearchAjax(filters, searchData);
};

// Prefill the Client Details fields (Name / Address) from the file's most recent
// legal_search_token. Runs on every flow — including Super Admin bypass and
// already-used tokens — so it does not depend on the token-verify popup.
// Only fills empty fields so it never clobbers operator edits or popup prefill.
function prefillClientDetails(fileNumber) {
  fileNumber = (fileNumber || '').trim();
  // The Client Details section only applies to Pay-Per-Search files (those with a
  // search token). Keep it hidden until a token is confirmed for this file.
  const section = document.getElementById('client-details-section');
  const showSection = (show) => { if (section) section.classList.toggle('hidden', !show); };
  showSection(false);
  if (!fileNumber) return;
  const nameField = document.getElementById('comment-client_name-text');
  const addrField = document.getElementById('comment-client_address-text');
  if (!nameField && !addrField) return;

  fetch("{{ route('legal-search-tokens.client-details') }}?file_number=" + encodeURIComponent(fileNumber), {
    headers: { 'Accept': 'application/json' },
    credentials: 'same-origin'
  })
    .then(r => r.json())
    .then(d => {
      if (!d || !d.success) return;
      // A token exists for this file — reveal the Client Details editor.
      showSection(true);
      window.__lsTokenClient = { name: d.client_name || '', address: d.client_address || '' };
      if (nameField && !nameField.value.trim() && d.client_name) nameField.value = d.client_name;
      if (addrField && !addrField.value.trim() && d.client_address) addrField.value = d.client_address;
    })
    .catch(() => { });
}

// Land-use prefix mapping (see .agent/skills/klaes/SKILL.md §5). Used to derive a
// file's land use from its file-number prefix when the indexed land_use is missing.
const LAND_USE_PREFIX_MAP = {
  'RES': 'Residential', 'COM': 'Commercial', 'IND': 'Industrial', 'AG': 'Agriculture',
  'CON-RES': 'Residential', 'CON-COM': 'Commercial', 'CON-IND': 'Industrial', 'CON-AG': 'Agriculture',
  'RES-RC': 'Residential', 'COM-RC': 'Commercial', 'IND-RC': 'Industrial', 'AG-RC': 'Agriculture',
  'CON-RES-RC': 'Residential', 'CON-COM-RC': 'Commercial', 'CON-IND-RC': 'Industrial', 'CON-AG-RC': 'Agriculture',
};

const deriveLandUseFromFileNumber = (fileNumber) => {
  if (!fileNumber) return '';
  const normalized = String(fileNumber).toUpperCase().replace(/[\/=_]+/g, '-').trim();
  // Collect the leading non-numeric tokens (the prefix), stopping at the year/serial.
  const prefixTokens = [];
  for (const token of normalized.split('-').filter(Boolean)) {
    if (/^\d+$/.test(token)) break;
    prefixTokens.push(token);
  }
  // ST files lead with an ST token that is not part of the land-use mapping.
  if (prefixTokens[0] === 'ST') prefixTokens.shift();

  const prefix = prefixTokens.join('-');
  if (LAND_USE_PREFIX_MAP[prefix]) return LAND_USE_PREFIX_MAP[prefix];
  // Fallback: match the first recognised base land-use token within the prefix.
  for (const token of prefixTokens) {
    if (LAND_USE_PREFIX_MAP[token]) return LAND_USE_PREFIX_MAP[token];
  }
  return '';
};

// Build the "File Information" card shown when a file is indexed but has no
// transactions, so the user still sees the file's details instead of a bare
// "No results found" message.
const renderFileInfoFallback = (data) => {
  if (!fileInfoFallbackBody) return false;

  const fileNumber = (window.__lsLastSearchedFileNumber || userSelectedFileNumber
    || (document.getElementById('fileNumber')?.value || '')).trim();
  const typeLabel = (filenoTypeLabel?.textContent || '').trim();
  const ownerLabel = (filenoOwnerLabel?.textContent || '').replace(/^[—-]\s*/, '').trim();

  // Land use: prefer the indexed value, else derive it from the file-number prefix.
  const indexedLandUse = (data.file_land_use !== null && data.file_land_use !== undefined && String(data.file_land_use).trim() !== '' && String(data.file_land_use).trim() !== '-')
    ? String(data.file_land_use).trim()
    : '';
  // Fall back to the picked file-number record when the file isn't in file_indexings.
  const rec = window.__lsSelectedRecord || {};
  const landUse = indexedLandUse
    || (rec.land_use && String(rec.land_use).trim() && String(rec.land_use).trim() !== '-' ? String(rec.land_use).trim() : '')
    || deriveLandUseFromFileNumber(fileNumber);
  const pick = (...vals) => {
    for (const v of vals) {
      if (v !== null && v !== undefined && String(v).trim() !== '' && String(v).trim() !== '-') return String(v).trim();
    }
    return '';
  };
  const fileTitle = pick(data.file_title, window.__lsSelectedFileTitle, rec.file_title, rec.file_name, rec.FileName, ownerLabel);
  const location  = pick(data.file_location, rec.Location, rec.location, rec.ma_location, rec.property_location, rec.address);
  const plotNo    = pick(data.file_plot_number, rec.plot_no, rec.plot_number);
  const sizeVal   = pick(data.file_size, rec.size, rec.plot_size);
  const tpNo      = pick(data.file_tp_no, rec.tp_no);
  const district  = pick(data.file_district, rec.district);
  const lga       = pick(data.file_lga, rec.lga);
  const relatedNo = pick(data.file_related_fileno, rec.related_fileno);

  // File lineage (previous / successor). Lets a superseded file show where it came from and
  // what replaced it, instead of appearing as an unrelated record.
  const lineage = data.lineage || {};
  const previousFiles = Array.isArray(lineage.previous_file_nos) ? lineage.previous_file_nos.join(', ') : '';
  let supersededBy = pick(lineage.successor_file_no);
  // Fallback for legacy rows: the successor is often embedded at the end of the reason text
  // (e.g. "Plot Merger to CON-COM-2023-197" or "... → CON-COM-2023-197").
  if (!supersededBy && lineage.is_superseded && lineage.decommission_reason) {
    const m = String(lineage.decommission_reason).match(/(?:to|→|->)\s*([A-Z0-9\/\-]+)\s*$/i);
    if (m) supersededBy = m[1];
  }

  // Heroicon (outline) path data per field for the leading icon.
  const ICONS = {
    'File Number':     'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    'File Type':       'M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 014 8V5a2 2 0 012-2z',
    'Owner':           'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    'File Title':      'M4 6h16M4 12h16M4 18h7',
    'Land Use':        'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
    'Plot No':         'M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z',
    'Size':            'M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4',
    'TP No':           'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
    'Location':        'M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
    'District':        'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0H5m0 0H3m2 0h4m6 0h4M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5',
    'LGA':             'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0H5m0 0H3m2 0h4m6 0h4M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5',
    'Related File No': 'M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m6-6l1.5-1.5a4 4 0 015.656 5.656l-3 3a4 4 0 01-5.656 0',
    'Previous File(s)': 'M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5m6-6l1.5-1.5a4 4 0 015.656 5.656l-3 3a4 4 0 01-5.656 0',
    'Superseded By':    'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
  };
  const DEFAULT_ICON = 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z';

  const rows = [
    ['File Number', fileNumber],
    ['File Type', typeLabel],
    ['File Title', fileTitle],
    ['Land Use', landUse],
    ['Location', location],
    ['Plot No', plotNo],
    ['Size', sizeVal],
    ['TP No', tpNo],
    ['District', district],
    ['LGA', lga],
    ['Related File No', relatedNo],
    ['Previous File(s)', previousFiles],
    ['Superseded By', supersededBy],
  ];

  const escape = (v) => String(v).replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  }[c]));

  const visibleRows = rows.filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '' && String(value).trim() !== '-');
  if (!visibleRows.length) return false;

  const cells = visibleRows.map(([label, value]) => `
    <div class="flex items-start gap-2.5">
      <svg class="h-4 w-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <path stroke-linecap="round" stroke-linejoin="round" d="${ICONS[label] || DEFAULT_ICON}" />
      </svg>
      <div class="min-w-0">
        <div class="text-[11px] uppercase tracking-wide text-gray-400 leading-tight">${escape(label)}</div>
        <div class="text-sm font-medium text-gray-800 break-words leading-snug">${escape(value)}</div>
      </div>
    </div>
  `).join('');

  fileInfoFallbackBody.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3.5">${cells}</div>`;

  return true;
};

const executeSearchAjax = (filters, searchData) => {
    // Show loading
    searchLoading.classList.remove('hidden');
    tableResults.classList.add('hidden');
    cardResults.classList.add('hidden');
    noResultsMessage.classList.add('hidden');
    if (fileInfoFallback) fileInfoFallback.classList.add('hidden');
    fileDetailsView.classList.add('hidden');

    // AJAX call to the server
    $.ajax({
      url: '{{ route($moduleConfig["searchRouteName"] ?? "legalsearch.search") }}',
      type: 'POST',
      data: searchData,
      success: function(data) {
        // Hide loading
        searchLoading.classList.add('hidden');

        // Store raw response for debug view
        lastApiResponse = data;

        // DCIV investigation flag (file linked to a DCIV in master_dciv_links).
        // The "Under Investigation" note is already written into each matching
        // row's Comments cell server-side; this flag is kept for any consumers.
        window._underInvestigation = !!data.under_investigation;

        // W/R/C flag — file tagged [WRC] in duplicate_fileno. Drives whether the
        // W/R/C remark editor is revealed in the comments panel.
        window._isWrcFile = !!data.is_wrc;

        // The API returns a unified chronological array
        searchResults = data.transactions || [];

        // Attach file_indexings data to all results
        const _apiFileTitle = data.file_title || null;
        const _apiFileDistrict = data.file_district || null;
        const _apiFileLga = data.file_lga || null;
        const _apiFileLandUse = data.file_land_use || null;
        const _apiFilePlotNo = data.file_plot_number || null;
        const _apiFileTpNo = data.file_tp_no || null;
        const _apiRelatedFileno = data.file_related_fileno || null;
        const _apiIndexFileNumber = data.file_index_number || null;
        const _apiFileSize = data.file_size || null;
        const _apiCommissioningDate = data.file_commissioning_date || null;
        const _apiCommissionedNumber = data.file_commissioned_number || null;
        const _apiTempFileNumber = data.file_temp_number || null;
        // Stash so the File Information card can show it even when the selected
        // record is a prop_id-expanded row from a different file number.
        window._lsFileTempNumber = _apiTempFileNumber;
        searchResults.forEach(r => {
          if (_apiFileTitle) r._file_title = _apiFileTitle;
          if (_apiCommissioningDate) r._file_commissioning_date = _apiCommissioningDate;
          if (_apiCommissionedNumber) r._file_commissioned_number = _apiCommissionedNumber;
          if (_apiFileDistrict) r._file_district = _apiFileDistrict;
          if (_apiFileLga) r._file_lga = _apiFileLga;
          if (_apiFileLandUse) r._file_land_use = _apiFileLandUse;
          if (_apiFilePlotNo) r._file_plot_number = _apiFilePlotNo;
          if (_apiFileTpNo) r._file_tp_no = _apiFileTpNo;
          if (_apiRelatedFileno) r._file_related_fileno = _apiRelatedFileno;
          if (_apiIndexFileNumber) r._file_index_number = _apiIndexFileNumber;
          if (_apiFileSize) r._file_size = _apiFileSize;
          if (_apiTempFileNumber) r._file_temp_number = _apiTempFileNumber;
        });

        console.log('=== STAGING TABLE SEARCH RESULTS ===');
        console.log('File History:', data.file_history_count);
        console.log('CofO:', data.cofo_count);
        console.log('PRA:', data.pra_count);
        console.log('Deed:', data.deed_count);
        console.log('Total:', data.total_count);
        console.log('Sample results:', searchResults.slice(0, 3));

        // Update results count
        resultsCount.textContent = searchResults.length;

        // Update source breakdown badges
        updateSourceBadges(data);

        // Show appropriate view
        if (searchResults.length === 0) {
          // If the file itself is indexed (even with no transactions), open its full
          // File Details view so the synthetic "File Commissioning" (and, for a "(T)"
          // number, "Temporary File") rows still display in the timeline — a commissioned
          // file always has at least its commissioning event. Otherwise fall back to the
          // File Information card / "No results found" message.
          const hasFileInfo = renderFileInfoFallback(data);
          const searchedNo = String(window.__lsLastSearchedFileNumber || (filters.fileNumber || '')).trim();
          if (hasFileInfo && searchedNo) {
            // No real transactions — reset the buffers so renderTimeline() shows only the
            // synthetic commissioning rows, then build a file-info-only selected record.
            window._preferredRelatedTransactions = [];
            window._allRelatedTransactions = [];
            const syntheticSelected = {
              _is_fileinfo_only: true,
              mlsFNo: searchedNo, fileno: searchedNo, file_number: searchedNo,
              _file_title: data.file_title || null,
              _file_district: data.file_district || null,
              _file_lga: data.file_lga || null,
              _file_land_use: data.file_land_use || null,
              _file_plot_number: data.file_plot_number || null,
              _file_tp_no: data.file_tp_no || null,
              _file_related_fileno: data.file_related_fileno || null,
              _file_index_number: data.file_index_number || null,
              _file_size: data.file_size || null,
              _file_commissioning_date: data.file_commissioning_date || null,
              _file_commissioned_number: data.file_commissioned_number || null,
              _file_temp_number: data.file_temp_number || null,
              prop_id: '',
            };
            // Set only the selected file — leave searchResults empty so getRelatedTransactions()
            // returns [] (no real transactions). Adding the synthetic record to searchResults would
            // make it render as a bogus "undefined" timeline row.
            selectedFile = syntheticSelected;

            const openRecords = () => {
              searchModal.classList.add('hidden');
              dashboardView.classList.add('hidden');
              fileHistoryView.classList.remove('hidden');
              renderFileHistory();
            };
            if (window.LEGAL_SEARCH_CONTEXT?.requiresPayment && !window._lsOnlinePaid
                && typeof window.lsOnlineRequestPayment === 'function') {
              window.lsOnlineRequestPayment(openRecords);
            } else {
              openRecords();
            }
          } else if (hasFileInfo && fileInfoFallback) {
            fileInfoFallback.classList.remove('hidden');
          } else {
            noResultsMessage.classList.remove('hidden');
          }
        } else {
          // Update active filters summary
          const activeFilters = Object.entries(filters)
            .filter(([_, value]) => value && value.trim() !== '')
            .map(([key, value]) => {
              const filterLabels = {
                fileNumber: 'File Number',
                guarantorName: 'Party 1',
                guaranteeName: 'Party 2',
                lga: 'LGA',
                district: 'District',
                location: 'Location',
                plotNumber: 'Plot Number',
                planNumber: 'Plan Number',
                size: 'Size',
                caveat: 'Caveat'
              };
              return `${filterLabels[key]}: ${value}`;
            })
            .join(', ');
          
          document.getElementById('active-filters-summary').textContent = activeFilters;
          
          renderSearchResults();
        }

        // After a cleanup action (Drop/Match/Remove), re-render file history
        if (_pendingCleanupRefresh && fileHistoryView && !fileHistoryView.classList.contains('hidden')) {
          _pendingCleanupRefresh = false;
          if (searchResults.length > 0 && selectedFile) {
            selectedFile = searchResults[0];
            renderFileHistory();
          }
        }
      },
      error: function(error) {
        // Hide loading
        searchLoading.classList.add('hidden');
        noResultsMessage.classList.remove('hidden');
        console.error('Error performing search:', error);
      }
    });
  };

  // Helper function to remove .0 from values
  const cleanNumericValue = (value) => {
    if (!value || value === '-') return value;
    
    // Convert to string if it's a number
    let stringValue = value.toString();
    
    // Remove .0 from the end if present
    if (stringValue.endsWith('.0')) {
      stringValue = stringValue.replace('.0', '');
    }
    
    return stringValue;
  };

  // ─── Debug mode toggle ──────
  if (toggleDebugBtn) {
    toggleDebugBtn.addEventListener('click', () => {
      if (!lastApiResponse || !lastApiResponse.transactions || lastApiResponse.transactions.length === 0) {
        alert('No search results to debug. Run a search first.');
        return;
      }
      openDebugTab(lastApiResponse);
    });
  }

  // ─── Source badges updater ──────────────────────────────────────
  const updateSourceBadges = (data) => {
    if (!sourceBadges) return;
    sourceBadges.classList.remove('hidden');

    const badges = [
      { id: 'badge-file-history', count: data.file_history_count || 0, label: 'FH' },
      { id: 'badge-cofo', count: data.cofo_count || 0, label: 'CofO' },
      { id: 'badge-pra', count: data.pra_count || 0, label: 'PRA' },
      { id: 'badge-deed', count: data.deed_count || 0, label: 'Deed' },
    ];

    badges.forEach(b => {
      const el = document.getElementById(b.id);
      if (el) {
        el.textContent = `${b.label}: ${b.count}`;
        el.classList.toggle('hidden', b.count === 0);
      }
    });
  };

  // ─── Open debug results in a new tab ────────────────────────────
  const openDebugTab = (data) => {
    const rows = data.transactions || [];
    const sourceColors = {
      'File History': '#eef2ff',
      'CofO': '#f0fdf4',
      'PRA': '#fffbeb',
      'Deed Registration': '#faf5ff',
    };

    let tableRows = '';
    rows.forEach((r, i) => {
      const bg = sourceColors[r.source_table] || '#fff';
      tableRows += `<tr style="background:${bg};border-bottom:1px solid #e5e7eb;">
        <td>${i + 1}</td>
        <td><b>${e(r.source_table)}</b></td>
        <td><b>${e(r.file_number)}</b></td>
        <td>${e(r.mlsFNo)}</td>
        <td>${e(r.fileno)}</td>
        <td>${e(r.kangisFileNo)}</td>
        <td>${e(r.NewKANGISFileno)}</td>
        <td>${e(r.transaction_type)}</td>
        <td style="white-space:nowrap">${e(r.transaction_date)}</td>
        <td>${e(r.sort_date)}</td>
        <td>${e(r.party_1)}</td>
        <td>${e(r.party_2)}</td>
        <td>${e(r.party_3)}</td>
        <td>${e(r.party_4)}</td>
        <td>${e(r.land_use)}</td>
        <td>${e(r.location)}</td>
        <td>${e(r.registration)}</td>
        <td>${e(r.regNo)}</td>
        <td>${e(r.serial_no)}</td>
        <td>${e(r.page_no)}</td>
        <td>${e(r.volume_no)}</td>
        <td>${e(r.plot_no)}</td>
        <td>${e(r.size)}</td>
        <td>${e(r.caveat)}</td>
        <td>${e(r.prop_id)}</td>
        <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis" title="${e(r.comments)}">${e(r.comments)}</td>
      </tr>`;
    });

    const html = `<!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>DEV MODE — Raw API Response</title>
<style>
  body { font-family: 'Segoe UI', system-ui, sans-serif; margin: 0; padding: 20px; background: #f9fafb; color: #111; }
  .header { background: #1f2937; color: #fff; padding: 16px 24px; border-radius: 8px; margin-bottom: 16px; }
  .header h1 { margin: 0 0 8px; font-size: 18px; }
  .stats { display: flex; gap: 12px; flex-wrap: wrap; }
  .stat { padding: 4px 10px; border-radius: 4px; font-size: 13px; font-weight: 600; }
  .stat-fh { background: #eef2ff; color: #4338ca; }
  .stat-cofo { background: #f0fdf4; color: #15803d; }
  .stat-pra { background: #fffbeb; color: #b45309; }
  .stat-deed { background: #faf5ff; color: #7e22ce; }
  .stat-total { background: #f3f4f6; color: #111; }
  table { width: 100%; border-collapse: collapse; font-size: 12px; font-family: 'Cascadia Code', 'Fira Code', monospace, sans-serif; }
  th { background: #1f2937; color: #fff; padding: 6px 8px; text-align: left; position: sticky; top: 0; z-index: 1; white-space: nowrap; }
  td { padding: 4px 8px; vertical-align: top; }
  tr:hover td { background: #e5e7eb !important; }
  .na { color: #d1d5db; }
  .wrap { border-radius: 8px; overflow: auto; border: 1px solid #e5e7eb; background: #fff; max-height: calc(100vh - 140px); }
  .ts { font-size: 11px; color: #9ca3af; margin-top: 8px; }
</style>
</head><body>
<div class="header">
  <h1>DEV MODE — Legal Search Raw API Response (Chronological)</h1>
  <div class="stats">
    <span class="stat stat-total">Total: ${rows.length}</span>
    <span class="stat stat-fh">File History: ${data.file_history_count || 0}</span>
    <span class="stat stat-cofo">CofO: ${data.cofo_count || 0}</span>
    <span class="stat stat-pra">PRA: ${data.pra_count || 0}</span>
    <span class="stat stat-deed">Deed: ${data.deed_count || 0}</span>
  </div>
</div>
<div class="wrap">
<table>
<thead><tr>
  <th>#</th><th>Source</th><th>File Number</th><th>MLS FNo</th><th>FileNo</th>
  <th>KANGIS</th><th>NewKANGIS</th><th>Txn Type</th><th>Txn Date</th><th>Sort Date</th>
  <th>Party 1</th><th>Party 2</th><th>Party 3</th><th>Party 4</th><th>Land Use</th><th>Location</th>
  <th>Registration</th><th>RegNo</th><th>Serial</th><th>Page</th><th>Volume</th>
  <th>Plot No</th><th>Size</th><th>Caveat</th><th>Prop ID</th><th>Comments</th>
</tr></thead>
<tbody>${tableRows}</tbody>
</table>
</div>
<div class="ts">Generated: ${new Date().toLocaleString()}</div>
</body></html>`;

    const w = window.open('', '_blank');
    if (w) {
      w.document.write(html);
      w.document.close();
    } else {
      alert('Pop-up blocked. Please allow pop-ups for this site.');
    }
  };

  // HTML-escape helper for debug output
  const e = (val) => {
    if (val === null || val === undefined) return '<span class="na">null</span>';
    const str = String(val);
    if (str === '-') return '<span class="na">-</span>';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  };

  // Helper function to identify file number type by pattern
  const identifyFileNumberType = (value) => {
    if (!value || value === '-' || value === null || value === undefined) {
      return 'unknown';
    }
    
    const cleanValue = cleanNumericValue(value.toString().trim());
    const normalized = cleanValue.replace(/\//g, '-');
    
    // ST File Number patterns: ST-RES-2024-01-001, ST-COM-2024-02-002, ST-IND-2024-03-009
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i.test(normalized)) {
      return 'st';
    }
    
    // Parent File Number (NP) patterns: ST-RES-2024-01, ST-COM-2024-02, ST-IND-2024-03
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+$/i.test(normalized)) {
      return 'parent';
    }
    
    // MLS File Number patterns: allow dash or slash separators
    if (/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d{4}-\d+$/i.test(normalized) ||
        /^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d+$/i.test(normalized)) {
      return 'mls';
    }
    
    // KANGIS File Number patterns: allow 3-6 digits after 4-letter prefix
    if (/^[A-Z]{4}\s?\d{3,6}$/i.test(cleanValue)) {
      return 'kangis';
    }
    
    // New KANGIS File Number patterns: KN followed by 2-6 digits
    if (/^KN\d{2,6}$/i.test(cleanValue)) {
      return 'new_kangis';
    }
    
    return 'unknown';
  };

  // Helper function to extract correct file numbers from a file record - prefer backend-computed fields and broaden patterns
  const extractFileNumbers = (file) => {
    console.log('Extracting file numbers from:', file);
    
    const result = {
      st: '-',
      parent: '-',
      mls: '-',
      kangis: '-',
      new_kangis: '-'
    };
    
    // 1) Prefer backend-computed aliases when present
    if (file.STFileNo && file.STFileNo !== '-') {
      result.st = cleanNumericValue(file.STFileNo);
    }
    if (file.ParentFileNo && file.ParentFileNo !== '-') {
      result.parent = cleanNumericValue(file.ParentFileNo);
    }
    if (file.MLSFileNo && file.MLSFileNo !== '-') {
      result.mls = cleanNumericValue(file.MLSFileNo);
    }
    if (file.KANGISFileNo && file.KANGISFileNo !== '-') {
      result.kangis = cleanNumericValue(file.KANGISFileNo);
    }
    if (file.NewKANGISFileNo && file.NewKANGISFileNo !== '-') {
      result.new_kangis = cleanNumericValue(file.NewKANGISFileNo);
    }
    
    // 2) Fallbacks if aliases are missing depending on table
    if (result.st === '-') {
      const stValues = [file.StFileNo, file.st_file_no, file.sub_fileno].filter(v => v && v !== '-');
      if (stValues.length) result.st = cleanNumericValue(stValues[0]);
    }
    
    if (result.parent === '-') {
      const parentValues = [file.parent_fileNo, file.np_fileno, file.mother_np_fileno].filter(v => v && v !== '-');
      if (parentValues.length) result.parent = cleanNumericValue(parentValues[0]);
    }
    
    if (result.mls === '-') {
      const mlsValues = [file.mlsFNo, file.fileNo, file.fileno, file.mother_fileno].filter(v => v && v !== '-');
      if (mlsValues.length) result.mls = cleanNumericValue(mlsValues[0]);
    }
    
    if (result.kangis === '-') {
      const kangisValues = [file.kangisFileNo, file.KAGISFileNO].filter(v => v && v !== '-');
      if (kangisValues.length) result.kangis = cleanNumericValue(kangisValues[0]);
    }
    
    if (result.new_kangis === '-') {
      const newKangisValues = [file.NewKANGISFileno, file.new_kangis_file_no].filter(v => v && v !== '-');
      if (newKangisValues.length) result.new_kangis = cleanNumericValue(newKangisValues[0]);
    }
    
    // 3) As a last resort, scan all possible values and categorize by pattern
    if (result.st === '-' || result.parent === '-' || result.mls === '-' || result.kangis === '-' || result.new_kangis === '-') {
      const allPossibleValues = [
        file.STFileNo, file.StFileNo, file.st_file_no, file.sub_fileno,
        file.ParentFileNo, file.parent_fileNo, file.np_fileno, file.mother_np_fileno,
        file.MLSFileNo, file.mlsFNo, file.fileNo, file.fileno, file.mother_fileno,
        file.KANGISFileNo, file.kangisFileNo, file.KAGISFileNO,
        file.NewKANGISFileNo, file.NewKANGISFileno, file.new_kangis_file_no
      ].filter(val => val && val !== '-' && val !== null && val !== undefined);
      
      allPossibleValues.forEach(value => {
        const cleanValue = cleanNumericValue(value);
        const type = identifyFileNumberType(cleanValue);
        if (type !== 'unknown' && result[type] === '-') {
          result[type] = cleanValue;
        }
      });
    }
    
    console.log('Final extracted file numbers:', result);
    return result;
  };

  // Parse related_fileno text that may be stored like "['CON-COM-2014-82']"
  const parseRelatedFilenoValue = (raw) => {
    if (!raw) return '-';
    let text = cleanNumericValue(String(raw));
    if (!text || text === '-') return '-';

    text = text.replace(/^\[/, '').replace(/\]$/, '');
    const parts = text
      .split(/[,;|]/)
      .map(p => cleanNumericValue(p).replace(/^['\"]+|['\"]+$/g, '').trim())
      .filter(Boolean);

    if (!parts.length) return '-';
    const mlsCandidate = parts.find(p => identifyFileNumberType(p) === 'mls');
    return cleanNumericValue(mlsCandidate || parts[0]);
  };

  // Render search results based on current view
  const renderSearchResults = () => {
    // Nothing to render when there are no results — the File Information fallback
    // or the no-results message already covers the empty state.
    if (!searchResults.length) return;
    if (currentView === 'table') {
      renderTableResults();
      tableResults.classList.remove('hidden');
      cardResults.classList.add('hidden');
    } else {
      renderCardResults();
      cardResults.classList.remove('hidden');
      tableResults.classList.add('hidden');
    }
  };

  // Render table results - UPDATED FOR NEW FILE NUMBER STRUCTURE AND COLUMN ORDER
  const renderTableResults = () => {
    tableResultsBody.innerHTML = '';
    
    searchResults.forEach((file, index) => {
      const fileNumbers = extractFileNumbers(file);
      const row = document.createElement('tr');
      row.className = 'hover:bg-gray-50 transition-colors';
      row.innerHTML = `
        <td class="p-2 text-sm">${fileNumbers.st}</td>
        <td class="p-2 text-sm">${fileNumbers.parent}</td>
        <td class="p-2 text-sm">${fileNumbers.mls}</td>
        <td class="p-2 text-sm">${fileNumbers.kangis}</td>
        <td class="p-2 text-sm">${fileNumbers.new_kangis}</td>
        <td class="p-2 text-sm font-mono text-xs text-blue-700">${getMappedValue(file, 'fileNumber')}</td>
        <td class="p-2 text-sm">${toProperCase(file.party_1 || '-')}</td>
        <td class="p-2 text-sm">${toProperCase(file.party_2 || '-')}</td>
        <td class="p-2 text-sm">${toProperCase(file.party_3 || '-')}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'lga'))}</td>
        <td class="p-2 text-sm">${file.property_house_no && file.property_plot_no && file.property_street_name && file.property_district && file.property_lga ? toProperCase(`${file.property_house_no},${file.property_plot_no},${file.property_street_name},${file.property_district},${file.property_lga}`) : toProperCase(getMappedValue(file, 'location'))}</td>
        <td class="p-2 text-sm">${getMappedValue(file, 'plotNo')}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'transactionType'))}</td>
        <td class="p-2 text-sm">${getMappedValue(file, 'size')}</td>
        <td class="p-2 text-sm font-medium ${file.caveat === 'Yes' ? 'text-red-600' : ''}">${file.caveat || '-'}</td>
        <td class="p-2 text-sm">
          <button class="view-file-btn inline-flex items-center px-2 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50" data-index="${index}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0  5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
            </svg>
            View Records
          </button>
        </td>
      `;
      
      tableResultsBody.appendChild(row);
    });
    
    // Party 3 is always visible in search results

    // Add event listeners to view buttons
    document.querySelectorAll('.view-file-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const index = parseInt(btn.getAttribute('data-index'));
        console.log('View button clicked for index:', index);

        selectedFile = searchResults[index];
        console.log('Selected file:', selectedFile);

        const openRecords = () => {
          searchModal.classList.add('hidden');
          dashboardView.classList.add('hidden');
          fileHistoryView.classList.remove('hidden');
          renderFileHistory();
        };

        // Payment gate: online portal requires ₦1,000 before viewing
        if (window.LEGAL_SEARCH_CONTEXT?.requiresPayment && !window._lsOnlinePaid) {
          if (typeof window.lsOnlineRequestPayment === 'function') {
            window.lsOnlineRequestPayment(openRecords);
          }
          return;
        }

        openRecords();
      });
    });
  };

  // Helper function to get mapped field value
  const getMappedValue = (item, fieldType) => {
    const fieldMappings = {
      // Date fields - enhanced for applications
      date: [
        'cofo_date', 'transaction_date', 'deeds_date', 'certificateDate', 
        'instrumentDate', 'approval_date', 'planning_approval_date',
        'receipt_date', 'payment_date', 'accountant_signature_date',
        'created_at', 'updated_at'
      ],
      
      // Transaction type fields - enhanced for applications
      transactionType: [
        'transaction_type', 'instrument_type', 'title_type', 'typeForm', 
        'landUseType', 'application_status', 'deeds_status',
        'planning_recommendation_status', 'land_use', 'landUse'
      ],

      // File Number fields
      fileNumber: [
        'file_number', 'fileNumber', 'fileno', 'mlsFNo', 'kangis_file_no', 'new_kangis_file_no'
      ],
      
      // Grantor/From party fields - enhanced for applications
      grantor: [
        'owner_fullname', 'mother_owner_fullname', 
        'first_name', 'corporate_name', 'multiple_owners_names',
        'Assignor', 'assignor', 'assignorName',
        'Grantor', 'Mortgagor', 'mortgagor', 
        'Lessor', 'lessor', 'Surrenderor', 'surrenderor',
        'originalAllottee', 'surrenderingPartyName',
        'applicant_title'
      ],
      
      // Grantee/To party fields - enhanced for applications
      grantee: [
        'sub_owner_fullname', 'multiple_owners_names', 'owner_fullname',
        'Assignee', 'assignee', 'Grantee', 
        'Mortgagee', 'mortgagee', 'Lessee', 'lessee',
        'Surrenderee', 'surrenderee', 'currentAllottee',
        'receivingPartyName', 'releaseeName',
        'first_name', 'corporate_name'
      ],
      
      // Registration number fields - enhanced
      serialNo: [
        'serialNo', 'serial_no', 'oldTitleSerialNo', 
        'rootRegistrationNumber', 'particularsRegistrationNumber',
        'volume_no', 'page_no'
      ],
      pageNo: ['pageNo', 'page_no', 'oldTitlePageNo'],
      volumeNo: ['volumeNo', 'volume_no', 'oldTitleVolumeNo'],
      
      // Size fields - enhanced
      size: ['size', 'plot_size', 'NoOfUnits', 'NoOfSections', 'NoOfBlocks'],
      
      // Comments fields - enhanced for applications
      comments: [
        'comments', 'additional_comments', 'recomm_comments', 
        'director_comments', 'application_comment', 'planning_recomm_comments'
      ],
      
      // Time fields
      time: ['deeds_time', 'transaction_time'],

      // Reg Date fields (for registration date/time display)
      regDate: ['deeds_date', 'reg_date', 'transaction_date'],
      regTime: ['deeds_time', 'reg_time', 'transaction_time'],
      
      // Plot number fields - enhanced for applications
      plotNo: [
        'plot_no', 'plotNo', 'plotNumber', 'property_plot_no', 
        'address_plot_no', 'scheme_no'
      ],
      
      // LGA fields - enhanced for applications
      lga: [
        'property_lga', 'address_lga', 'lga', 'lgaName', 
        'lgsaOrCity'
      ],
      
      // District fields - enhanced for applications
      district: [
        'property_district', 'address_district', 'district', 
        'districtName'
      ],
      
      // Location/Address fields - enhanced for applications
      location: [
        'location', 'propertyAddress', 'propertyDescription', 
        'plotDescription', 'property_location', 'address',
        'property_street_name', 'address_street_name',
        'property_house_no', 'address_house_no'
      ],
      
      // Land use fields - enhanced for applications
      landUse: [
        'land_use', 'landUse', 'landUseType', 'residential_type',
        'commercial_type', 'industrial_type', 'mixed_type'
      ]
    };
    
    const fields = fieldMappings[fieldType] || [];
    for (const field of fields) {
      if (item[field] && item[field] !== null && item[field] !== '') {
        let value = item[field];
        // Remove .0 from numeric values that are actually integers
        if (typeof value === 'number' && value % 1 === 0) {
          value = Math.floor(value).toString();
        } else if (typeof value === 'string' && value.endsWith('.0')) {
          value = value.replace('.0', '');
        }
        return value;
      }
    }
    return '-';
  };

  // Helper: render file number as a red badge if it is different from the searched file number
  const renderFileNumberSpan = (item, fieldType = 'fileNumber') => {
    const rawVal = getMappedValue(item, fieldType);
    const itemFileNo = String(rawVal || '').trim();
    const searchedFileNo = (window.__lsLastSearchedFileNumber || '').trim();

    // Related-Fileno (recertification) rows always render in orange, irrespective of search match
    if (item && item.source_table === 'Related Fileno') {
      const parentFn = item.parent_file_number ? ` (under ${item.parent_file_number})` : '';
      return `<span class="px-2 py-0.5 rounded text-xs font-semibold bg-orange-50 text-orange-700 border border-orange-200" title="Recertification linkage${parentFn}">${itemFileNo}</span>`;
    }

    const normalizeFN = (str) => {
      return str.toUpperCase().replace(/[\s\-_=\/]+/g, '');
    };

    if (searchedFileNo && itemFileNo && itemFileNo !== '-') {
      const normSearch = normalizeFN(searchedFileNo);
      const normItem = normalizeFN(itemFileNo);
      if (normSearch && normItem !== normSearch) {
        return `<span class="px-2 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-600 border border-red-200" title="Associated Related File Number: ${itemFileNo}">${itemFileNo}</span>`;
      }
    }
    return itemFileNo;
  };

  // Helper: format reg particulars with 0/0/0 fallback
  const formatRegParticulars = (serialNo, pageNo, volumeNo) => {
    const s = cleanNumericValue(serialNo);
    const p = cleanNumericValue(pageNo);
    const v = cleanNumericValue(volumeNo);
    const hasAny = (s && s !== '-') || (p && p !== '-') || (v && v !== '-');
    if (!hasAny) return '0/0/0';
    return `${(s && s !== '-') ? s : '0'}/${(p && p !== '-') ? p : '0'}/${(v && v !== '-') ? v : '0'}`;
  };

  // Helper: format reg date for display
  const formatRegDate = (item) => {
    const raw = item.deeds_date || item.reg_date || null;
    if (!raw || raw === '-') return '-';
    try {
      const d = new Date(raw);
      if (isNaN(d.getTime())) return raw;
      return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    } catch(e) { return raw; }
  };

  // Helper: format reg time for display
  const formatRegTime = (item) => {
    const raw = item.deeds_time || item.reg_time || null;
    if (!raw || raw === '-') return '-';
    try {
      // Handle time-only strings like "10:30 AM" or "14:30:00"
      const str = String(raw).trim();
      if (/^\d{1,2}:\d{2}/.test(str)) {
        const d = new Date('2000-01-01 ' + str);
        if (!isNaN(d.getTime())) return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
      }
      return str;
    } catch(e) { return raw; }
  };

  // Helper: strip stray quote characters from display strings
  const stripQuotes = (value) => {
    if (value === null || value === undefined) return '';
    const text = String(value).trim();
    if (text === '-') return text;
    return text.replace(/^["'“”‘’«»]+|["'“”‘’«»]+$/g, '').trim();
  };

  // Helper function to convert text to proper case
  const toProperCase = (text) => {
    const cleaned = stripQuotes(text);
    if (!cleaned || cleaned === '-') return cleaned;
    return cleaned.toString().toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
  };

  const CURRENT_FILE_YEAR = new Date().getFullYear();
  const isCurrentYearFileNumber = (item) => {
    const fileNo = String(getMappedValue(item, 'fileNumber') || '');
    const match = fileNo.match(/\b(?:19|20)\d{2}\b/);
    return match ? Number(match[0]) === CURRENT_FILE_YEAR : false;
  };

  const isCurrentYearTransaction = (item) => {
    const dates = [
        item.reg_date, item.transaction_date, item.deeds_date, 
        item.cofo_date, item.certificateDate, item.approval_date, item.date
    ];
    for (const txDate of dates) {
      if (txDate && txDate !== '-') {
        const d = new Date(txDate);
        if (!isNaN(d.getTime()) && d.getFullYear() === CURRENT_FILE_YEAR) return true;
        
        const parts = String(txDate).split(/[\/\-]/);
        if (parts.length === 3 && parts[2].length === 4 && Number(parts[2]) === CURRENT_FILE_YEAR) return true;
      }
    }
    return false;
  };

  // Rule B: transaction-type priority for Timeline sort order.
  // Priority group (always displayed first, in weight order): OP=10, TOT=9, RoFO=8.
  // CofO + all other instruments = 1 → sorted chronologically by reg date within tie.
  // Current-year file numbers are forced to weight 0 (sorted last).
  const recordPriorityWeight = (item) => {
    if (item && item._is_commissioning) return 12;
    if (item && item._is_temporary_file) return 11;
    if (isCurrentYearFileNumber(item) || isCurrentYearTransaction(item)) return 0;
    const txType = canonicalWeightingInstrumentType(getMappedValue(item, 'transactionType'));
    if (txType === 'occupancy permit') return 10;
    if (txType === 'transfer of title') return 9;
    if (txType === 'right of occupancy') return 8;
    return 1;
  };

  // Rule A (Source/Table Weighting)
  const getTableSourceWeight = (item) => {
    const source = String(item?.source_table || '').trim();
    if (source === 'PRA') return 5;
    if (source === 'Deed Registration') return 5;
    if (source === 'CofO') return 5;
    if (source === 'File History') return 2.5;
    return 1;
  };

  // Rule A (Record-to-Record Weighting) for MSDAM table Weight columns.
  // Five categories × 2 points each = max 10:
  //   Parties (any of P1/P2/P3 present), Reg Particulars (serial/page/volume),
  //   Transaction Date, Reg Time, Reg Date.
  const recordRichnessScore = (item) => {
    if (!item) return 0;
    const hasText = (v) => v !== null && v !== undefined && String(v).trim() !== '' && String(v).trim() !== '-';
    const hasReg  = (v) => {
      if (v === null || v === undefined) return false;
      const s = String(v).trim();
      if (s === '' || s === '-' || s === '0') return false;
      return true;
    };
    let score = 0;
    if (hasText(item.party_1) || hasText(item.party_2) || hasText(item.party_3)) score += 2;
    const serial = item.serial_no ?? item.serialNo ?? '';
    const page   = item.page_no   ?? item.pageNo   ?? '';
    const volume = item.volume_no ?? item.volumeNo ?? '';
    if (hasReg(serial) || hasReg(page) || hasReg(volume)) score += 2;
    const txDate = item.transaction_date ?? item.deeds_date ?? item.reg_date ?? '';
    if (hasText(txDate)) score += 2;
    const regTime = item.reg_time ?? item.deeds_time ?? item.transaction_time ?? '';
    if (hasText(regTime)) score += 2;
    const regDate = item.reg_date ?? item.deeds_date ?? '';
    if (hasText(regDate)) score += 2;
    return score;
  };

  // Keep Aggregate/source tabs complete, but dedupe merged outputs (Timeline/Report)
  // by preferring File History over PRA when the same logical record appears in both.
  // Enhanced: reg particulars bonus (+2) makes records WITH reg data preferred.
  const dedupeTransactionsForTimelineAndReport = (transactions) => {
    if (!Array.isArray(transactions) || transactions.length === 0) {
      window._weightingData = [];
      return [];
    }

    const normalize = (value) => String(value || '')
      .trim()
      .toLowerCase()
      .replace(/\s+/g, ' ')
      .replace(/[.,]/g, '');

    // Canonical instrument type: collapse known aliases so dedup keys match
    // regardless of how different source tables name the same instrument.
    const canonicalInstrumentType = (value) => {
      const raw = normalize(value);
      if (!raw || raw === '-') return raw;

      // ROFO variants
      if (raw.includes('right of occupancy') || raw.includes('right of occupanc')) return 'right of occupancy';
      if (/^r\s*of\s*o$/.test(raw)) return 'right of occupancy';
      const compact = raw.replace(/[^a-z0-9]/g, '');
      if (/^r[o0]f[o0]$/.test(compact) || /^r[o0]f[o0]occupanc/.test(compact)) return 'right of occupancy';
      if (raw === 'customary right of occupancy' || raw === 'statutory right of occupancy') return 'right of occupancy';

      // CofO variants
      if (raw.includes('certificate of occupancy') || raw.includes('cert of occupancy')) return 'certificate of occupancy';
      if (/^c\s*of\s*o$/.test(raw) || /^c[o0]f[o0]$/.test(compact)) return 'certificate of occupancy';

      // OP (Occupancy Permit) variants
      if (raw.includes('occupancy permit')) return 'occupancy permit';
      if (/^o\s*p$/.test(raw) || compact === 'op') return 'occupancy permit';

      // Transfer of Title variants (incl. "TRANSFER OF TITLE (OP)") → transfer of title
      if (raw.includes('transfer of title')) return 'transfer of title';

      // Mortgage variants → mortgage (canonical bare form)
      if (raw === 'tripartite mortgage' || raw === 'legal mortgage' || raw === 'equitable mortgage') return 'mortgage';

      // Surrender/release variants → surrender and release
      if (raw === 'deed of surrender' || raw === 'deed of release' ||
          raw === 'deed of surrender & release' || raw === 'deed of surrender and release' ||
          raw === 'surrender and release' || raw === 'surrender & release') return 'surrender and release';

      // Power of Attorney variants → power of attorney
      // Collapses "Power Of Attorney", "Irrevocable Power Of Attorney",
      // "Deed Of Power Of Attorney", "POA", etc. to one canonical key so
      // copies of the same instrument across PRA/FH dedupe correctly.
      if (raw.includes('power of attorney')) return 'power of attorney';
      if (compact === 'poa' || compact === 'ipoa') return 'power of attorney';

      // Strip "deed of " prefix so that cross-source pairs like
      // "Deed Of Assignment" (PRA) and "Assignment" (File History) share the same key.
      if (raw.startsWith('deed of ')) return raw.slice(8);

      return raw;
    };

    // Rule A: source-based dedup weights (which row wins when two sources have the same record)
    const sourceBaseScore = (row) => {
      const source = String(row?.source_table || '').trim();
      if (source === 'PRA') return 5;
      if (source === 'Deed Registration') return 5;
      if (source === 'CofO') return 5;
      if (source === 'File History') return 2.5;
      return 1;
    };

    const sourceLabel = (row) => {
      const source = String(row?.source_table || '').trim();
      if (source === 'File History') return 'FH';
      if (source === 'PRA') return 'PRA';
      if (source === 'CofO') return 'CofO';
      if (source === 'Deed Registration') return 'Deed';
      return source || '?';
    };

    const totalScore = (row) => sourceBaseScore(row);

    // Fingerprint: use reg particulars as primary dedup key when available.
    // PRA stores deed execution date while File History stores registration date
    // for the same instrument — using date caused PRA and FH copies to never
    // match. Reg particulars (serial/page/volume) are source-neutral and
    // authoritative: same instrument = same numbers in both tables.
    // CofO and Deed Registration sources also participate so their internal
    // duplicates (same reg particulars + parties) collapse to one row.
    const recordKey = (row) => {
      const source = String(row?.source_table || '').trim();
      const dedupableSources = ['File History', 'PRA', 'CofO', 'Deed Registration'];
      if (!dedupableSources.includes(source)) return null;

      const transType = canonicalInstrumentType(getMappedValue(row, 'transactionType'));
      if (!transType) return null;

      // Primary: reg particulars-based key
      const serialNo = cleanNumericValue(getMappedValue(row, 'serialNo')) || '0';
      const pageNo   = cleanNumericValue(getMappedValue(row, 'pageNo'))   || '0';
      const volumeNo = cleanNumericValue(getMappedValue(row, 'volumeNo')) || '0';
      const hasRealReg = (serialNo !== '0' && serialNo !== '-') ||
                         (pageNo   !== '0' && pageNo   !== '-') ||
                         (volumeNo !== '0' && volumeNo !== '-');
      if (hasRealReg) {
        return 'reg|' + transType + '|' + serialNo + '/' + pageNo + '/' + volumeNo;
      }

      // Fallback: party + date (for records without reg particulars, e.g. ROFO)
      const party1 = normalize(row.party_1 || '');
      const party2 = normalize(row.party_2 || '');
      const party3 = normalize(row.party_3 || '');
      const party4 = normalize(row.party_4 || '');
      // For ROFO the date is irrelevant (allocation date varies by source), drop it
      const date = transType === 'right of occupancy' ? '' : normalize(getMappedValue(row, 'date'));
      // Without reg particulars we need a discriminator that keeps genuinely different
      // properties apart (e.g. the source plots of a merger can share the same govt
      // grantor + grantee on a Right of Occupancy and must NOT collapse). prop_id is
      // that discriminator: same property = same prop_id, so copies across PRA/FH/CofO
      // dedupe even when their file-number labels differ (MLS "RES-2000-1767" in File
      // History vs KANGIS "KN2313" in PRA). Fall back to the file number only when the
      // row has no prop_id.
      const propId = String(row.prop_id || '').trim();
      const discriminator = propId !== ''
        ? 'p:' + propId
        : normalize(String(getMappedValue(row, 'fileNumber') || ''));

      const hasSignal = [transType, party1, party2, date, discriminator].some(Boolean);
      if (!hasSignal) return null;

      return [transType, party1, party2, party3, party4, date, discriminator].join('|');
    };

    // Pass 1: build deduped set with scoring
    const preferred = [];
    const excluded = [];
    const keyToIndex = new Map();
    const keyToAllRows = new Map(); // fingerprint -> [rows] for weighting data

    // Handle Manual Overrides First
    const filteredTransactions = transactions.filter(row => {
      const uid = getRecordUid(row);
      if (window._manualDroppedIds.has(uid)) {
        excluded.push(row);
        row._dedup_status = 'manual-excluded';
        return false;
      }
      return true;
    });

    filteredTransactions.forEach((row) => {
      const uid = getRecordUid(row);
      if (window._manualIncludedIds.has(uid)) {
        preferred.push(row);
        row._dedup_status = 'manual-preferred';
        return;
      }

      const key = recordKey(row);
      if (!key) {
        preferred.push(row);
        return;
      }
      row._dedup_fingerprint = key;

      if (!keyToAllRows.has(key)) keyToAllRows.set(key, []);
      keyToAllRows.get(key).push(row);
 
      const existingIndex = keyToIndex.get(key);
      if (existingIndex === undefined) {
        keyToIndex.set(key, preferred.length);
        preferred.push(row);
        return;
      }

      const existing = preferred[existingIndex];
      const rowRichness = recordRichnessScore(row);
      const existingRichness = recordRichnessScore(existing);

      if (rowRichness > existingRichness) {
        excluded.push(existing);
        preferred[existingIndex] = row;
      } else if (rowRichness === existingRichness && totalScore(row) > totalScore(existing)) {
        excluded.push(existing);
        preferred[existingIndex] = row;
      } else {
        excluded.push(row);
      }
    });

    // Tag records
    const preferredUids = new Set(preferred.map(r => getRecordUid(r)));
    transactions.forEach(row => {
      const uid = getRecordUid(row);
      if (preferredUids.has(uid)) {
        if (!row._dedup_status) row._dedup_status = 'preferred';
      } else {
        if (!row._dedup_status) row._dedup_status = 'excluded';
      }
    });

    return { preferred, excluded };
  };

  // Render card results - UPDATED FOR NEW FILE NUMBER STRUCTURE
  const renderCardResults = () => {
    cardResults.innerHTML = '';
    
    searchResults.forEach((file, index) => {
      const fileNumbers = extractFileNumbers(file);
      const card = document.createElement('div');
      card.className = 'bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow cursor-pointer';
      card.setAttribute('data-index', index);
      card.innerHTML = `
        <div class="p-4">
          <div class="flex justify-between items-start mb-3">
            <div>
              <div class="font-medium flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z" />
                </svg>
                ${fileNumbers.mls}
              </div>
              <div class="text-sm text-gray-500 mt-1">
                ST Unit: ${fileNumbers.st} | NP: ${fileNumbers.parent} | KANGIS: ${fileNumbers.kangis} | New KANGIS: ${fileNumbers.new_kangis}
              </div>
            </div>
          </div>
        </div>
      `;
      
      card.addEventListener('click', () => {
        const cardIndex = parseInt(card.getAttribute('data-index'));
        selectedFile = searchResults[cardIndex];
        searchModal.classList.add('hidden');
        dashboardView.classList.add('hidden');
        fileHistoryView.classList.remove('hidden');
        renderFileHistory();
      });
      
      cardResults.appendChild(card);
    });
  };

  // Render file history (the side-by-side layout shown in the screenshot)
  const renderFileHistory = () => {
    if (!selectedFile) {
      console.log('No selected file in renderFileHistory');
      return;
    }
    
    console.log('Rendering file history for:', selectedFile);
    
    const fileNumbers = extractFileNumbers(selectedFile);
    const relatedMls = parseRelatedFilenoValue(selectedFile._file_related_fileno || selectedFile.related_fileno || null);
    const isMlsActuallyKangis = identifyFileNumberType(fileNumbers.mls) === 'kangis';
    let mlsDisplay = fileNumbers.mls;
    if (relatedMls !== '-' && (isMlsActuallyKangis || fileNumbers.mls === '-')) {
      mlsDisplay = relatedMls;
    } else if (fileNumbers.mls === '-' && relatedMls !== '-') {
      mlsDisplay = relatedMls;
    }

    // If the user explicitly picked a file number (via the File Number
    // Selector modal or by typing it into the search box) keep that as the
    // primary displayed file number, regardless of which related-file card
    // they later click in the search results.
    if (userSelectedFileNumber && String(userSelectedFileNumber).trim() !== '') {
      mlsDisplay = String(userSelectedFileNumber).trim();
    }

    // Update file reference in subtitle (with .0 fix)
    let fileRef = mlsDisplay !== '-' ? mlsDisplay : (selectedFile.mlsFNo || selectedFile.MLSFileNo || selectedFile.fileNo || selectedFile.fileno || '-');
    document.getElementById('file-reference').textContent = fileRef;

    // Prefill Client Details from the file's most recent token (any flow, incl. bypass).
    prefillClientDetails((window.__lsLastSearchedFileNumber || '').trim() || (fileRef !== '-' ? fileRef : ''));
    
    // Update file information fields (with .0 fix and better field mapping)
    document.getElementById('file-number-value').textContent = mlsDisplay;

    // Label the primary file number by what is actually displayed — legacy
    // files are searched by their KANGIS number, which lands in this slot.
    const fileNumberLabel = document.getElementById('file-number-label');
    if (fileNumberLabel) {
      const displayedType = identifyFileNumberType(mlsDisplay);
      fileNumberLabel.textContent = displayedType === 'kangis'
        ? 'File Number (KANGIS FileNo):'
        : (displayedType === 'new_kangis'
          ? 'File Number (New KANGIS FileNo):'
          : 'File Number (MLPPFNo):');
    }

    // Temporary "(T)" file number — shown as a second line when the searched
    // file has a temporary sibling registered against it.
    const tempFileNumber = String(selectedFile._file_temp_number || selectedFile.temp_file_no || window._lsFileTempNumber || '').trim();
    const tempRow = document.getElementById('temp-file-number-row');
    const tempVal = document.getElementById('temp-file-number-value');
    if (tempRow && tempVal) {
      if (tempFileNumber && tempFileNumber !== '-' && tempFileNumber.toUpperCase() !== String(mlsDisplay).toUpperCase()) {
        tempVal.textContent = tempFileNumber;
        tempRow.classList.remove('hidden');
      } else {
        tempVal.textContent = '';
        tempRow.classList.add('hidden');
      }
    }

    document.getElementById('kangis-file-number-value').textContent = fileNumbers.kangis;
    document.getElementById('new-kangis-file-number-value').textContent = fileNumbers.new_kangis;
    
    // File Title - look up from file_indexings via search response
    const fileTitleValue = selectedFile._file_title || '-';
    document.getElementById('file-title-value').textContent = fileTitleValue;

    // Plot No - from file_indexings
    const plotNoValue = selectedFile._file_plot_number || selectedFile.plot_no || selectedFile.plotNo || '-';
    document.getElementById('plot-no-value').textContent = plotNoValue;

    // Size - prefer aggregated file_size, fallback to transaction data
    const sizeValue = selectedFile._file_size || selectedFile.size || selectedFile.plot_size || '-';
    document.getElementById('size-value').textContent = sizeValue;

    // TP No - from file_indexings
    const tpnoValue = selectedFile._file_tp_no || selectedFile.tp_no || '-';
    document.getElementById('tpno-value').textContent = tpnoValue;
    
    // LGA - prefer file_indexings, fallback to transaction data
    const lgaValue = selectedFile._file_lga || selectedFile.property_lga || selectedFile.address_lga || 
                    selectedFile.lgsaOrCity || selectedFile.lga || selectedFile.lgaName || '-';
    document.getElementById('lga-value').textContent = lgaValue;
    
    // District - prefer file_indexings, fallback to transaction data
    const districtValue = selectedFile._file_district || selectedFile.property_district || selectedFile.address_district || 
                         selectedFile.district || selectedFile.districtName || '-';
    document.getElementById('district-value').textContent = districtValue;
    
    // Land Use - prefer file_indexings, fallback to transaction data
    const landUseValue = selectedFile._file_land_use || selectedFile.land_use || selectedFile.landUse || 
                             selectedFile.landUseType || selectedFile.title_type || 
                             selectedFile.instrument_type || selectedFile.Type || '-';
    document.getElementById('property-type-value').textContent = landUseValue;
    
    // Last transaction — pick the most recent record (by transaction_date,
    // falling back to reg_date) from all related transactions and use its
    // transaction_type. Falls back to the selected file's own type if no
    // related rows have a usable date.
    let lastTransactionValue = '-';
    try {
      const relatedForLast = getRelatedTransactions(selectedFile) || [];
      const pickDate = (item) => {
        const candidates = [item?.transaction_date, item?.reg_date];
        for (const c of candidates) {
          const ts = parseTimelineDateValue(c);
          if (ts !== null) return ts;
        }
        return null;
      };
      let latestItem = null;
      let latestTs = -Infinity;
      for (const item of relatedForLast) {
        const ts = pickDate(item);
        if (ts !== null && ts > latestTs) {
          latestTs = ts;
          latestItem = item;
        }
      }
      if (latestItem) {
        lastTransactionValue = latestItem.transaction_type || latestItem.instrument_type ||
                               latestItem.transactionType || lastTransactionValue;
      } else {
        lastTransactionValue = selectedFile.transaction_type || selectedFile.instrument_type ||
                               selectedFile.application_status || selectedFile.deeds_status ||
                               selectedFile.planning_recommendation_status || '-';
      }
    } catch (e) {
      console.warn('Last transaction calculation failed:', e);
      lastTransactionValue = selectedFile.transaction_type || selectedFile.instrument_type ||
                             selectedFile.application_status || selectedFile.deeds_status ||
                             selectedFile.planning_recommendation_status || '-';
    }
    document.getElementById('last-transaction-value').textContent = lastTransactionValue;
    
    // Render the transactions tables
    renderTransactionTables();
    
    // Update Timeline button with current file's prop_id / file number
    const tlBtn = document.getElementById('view-property-timeline-btn');
    if (tlBtn) {
      const tlPropId = String(selectedFile.prop_id || '');
      const tlFileNo = fileRef;
      if (tlPropId || tlFileNo) {
        tlBtn.setAttribute('data-prop-id', tlPropId);
        tlBtn.setAttribute('data-file-number', tlFileNo);
        tlBtn.classList.remove('hidden');
      } else {
        tlBtn.classList.add('hidden');
      }
    }
    
    // Default to file history tab
    switchTab('transaction-history');
    loadLegalSearchArchive();
  };

  // Expose renderFileHistory globally so IIFEs and external scripts can call it
  window.renderFileHistory = renderFileHistory;

  // ── Silent Refresh ──────────────────────────────────────────────────────────
  // Re-fetches server data using the last search payload without hiding the
  // current file detail view. Only the button shows a loading state.
  const _silentRefreshUrl = '{{ route($moduleConfig["searchRouteName"] ?? "legalsearch.search") }}';

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('#refresh-history-btn');
    if (!btn) return;
    if (!window.__lsLastSearchData) return; // no search performed yet

    // Button spinner
    const icon = btn.querySelector('[data-refresh-icon]');
    if (icon) icon.classList.add('animate-spin');
    btn.disabled = true;

    $.ajax({
      url: _silentRefreshUrl,
      type: 'POST',
      data: window.__lsLastSearchData,
      success: function (data) {
        if (icon) icon.classList.remove('animate-spin');

        // Keep the DCIV investigation flag in sync on silent refresh
        window._underInvestigation = !!data.under_investigation;
        window._isWrcFile = !!data.is_wrc;

        const prevCount = searchResults.length;
        searchResults = data.transactions || [];

        // Re-attach file_indexings metadata
        const _t = data.file_title || null;
        const _d = data.file_district || null;
        const _l = data.file_lga || null;
        const _lu = data.file_land_use || null;
        const _p = data.file_plot_number || null;
        const _tp = data.file_tp_no || null;
        const _rf = data.file_related_fileno || null;
        const _ix = data.file_index_number || null;
        const _sz = data.file_size || null;
        const _tf = data.file_temp_number || null;
        const _cd = data.file_commissioning_date || null;
        const _cn = data.file_commissioned_number || null;
        window._lsFileTempNumber = _tf;
        searchResults.forEach(function (r) {
          if (_t)  r._file_title          = _t;
          if (_d)  r._file_district       = _d;
          if (_l)  r._file_lga            = _l;
          if (_lu) r._file_land_use       = _lu;
          if (_p)  r._file_plot_number    = _p;
          if (_tp) r._file_tp_no          = _tp;
          if (_rf) r._file_related_fileno = _rf;
          if (_ix) r._file_index_number   = _ix;
          if (_sz) r._file_size           = _sz;
          if (_tf) r._file_temp_number    = _tf;
          if (_cd) r._file_commissioning_date = _cd;
          if (_cn) r._file_commissioned_number = _cn;
        });

        // Update counts
        resultsCount.textContent = searchResults.length;
        updateSourceBadges(data);

        // If a file is open, keep it open — just re-render the tables
        if (selectedFile && fileHistoryView && !fileHistoryView.classList.contains('hidden')) {
          // Try to keep the same record selected by prop_id or first result
          const propId = selectedFile.prop_id;
          const refreshed = propId
            ? searchResults.find(function (r) { return r.prop_id === propId; })
            : null;
          selectedFile = refreshed || (searchResults.length > 0 ? searchResults[0] : selectedFile);
          renderFileHistory();
        }

        const newCount = searchResults.length;
        const diff = newCount - prevCount;
        const msg = diff > 0
          ? diff + ' new record' + (diff > 1 ? 's' : '') + ' loaded'
          : 'No new records found';

        // Brief toast on the button
        const origHtml = btn.innerHTML; // Now it won't have the spin class in its HTML because we removed it above
        btn.innerHTML = '<span class="text-xs">' + msg + '</span>';
        setTimeout(function () { btn.innerHTML = origHtml; btn.disabled = false; }, 2000);
      },
      error: function () {
        btn.disabled = false;
        if (icon) icon.classList.remove('animate-spin');
      }
    });
  });
  // ────────────────────────────────────────────────────────────────────────────

  // Get related transactions for a selected file - UPDATED FOR HIERARCHICAL SEARCH
  const getRelatedTransactions = (file) => {
    console.log('=== getRelatedTransactions called (HIERARCHICAL) ===');
    console.log('Selected file:', file);
    console.log('Search results available:', searchResults);
    console.log('Total search results count:', searchResults ? searchResults.length : 0);
    
    if (!searchResults || searchResults.length === 0 || !file) {
      console.log('No search results or file available, returning empty array');
      return [];
    }
    
    // Since the backend now returns hierarchical results that are already filtered
    // by the hierarchical logic, we should return ALL search results
    console.log('Returning all search results due to hierarchical backend filtering');
    return searchResults;
  };

  // Toggle Party 3/4 column visibility based on whether any record has data
  const togglePartyColumns = (records, containerSelector) => {
    const hasParty3 = records.some(r => r.party_3 && r.party_3 !== '-' && r.party_3.trim() !== '');
    const hasParty4 = records.some(r => r.party_4 && r.party_4 !== '-' && r.party_4.trim() !== '');
    const container = document.querySelector(containerSelector) || document;
    container.querySelectorAll('.party-3-col').forEach(el => el.classList.toggle('hidden', !hasParty3));
    container.querySelectorAll('.party-4-col').forEach(el => el.classList.toggle('hidden', !hasParty4));
  };

  // Dedup status dot indicator for source tab rows
  const dedupDot = (item) => {
    const status = item._dedup_status || '';
    if (status === 'preferred') {
      return '<span title="Preferred — this record is used in Timeline/Report/Print" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-right:4px;vertical-align:middle;"></span>';
    }
    if (status === 'duplicate') {
      return '<span title="Duplicate — a better version exists from another source" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:4px;vertical-align:middle;"></span>';
    }
    // Unique — no duplicate group at all
    return '<span title="Unique — no duplicate detected across sources" style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:4px;vertical-align:middle;"></span>';
  };

  // Render weighting transparency table
  const renderWeightingTable = () => {
    const tbody = document.getElementById('weighting-table');
    if (!tbody) return;
    const data = window._weightingData || [];
    if (data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center text-gray-400 py-4">No duplicate groups detected</td></tr>';
      return;
    }
    tbody.innerHTML = data.map((row, i) => {
      const statusBg = row.status === 'preferred' ? 'background:#fef3c7;color:#92400e;' :
                        row.status === 'duplicate' ? 'background:#fee2e2;color:#991b1b;' : 'background:#dcfce7;color:#166534;';
      const statusLabel = row.status === 'preferred' ? '&#x25CF; Preferred' : row.status === 'duplicate' ? '&#x25CF; Duplicate' : '&#x25CF; Unique';
      return `<tr>
        <td>${i + 1}</td>
        <td style="font-family:monospace;font-size:11px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${row.fingerprint}">${row.fingerprint}</td>
        <td>${row.source}</td>
        <td class="text-center font-semibold text-gray-700">${row.recordToRecord}</td>
        <td class="text-center text-gray-500">${row.tableToTable}</td>
        <td class="text-center text-indigo-600">${row.timeline}</td>
        <td style="${statusBg}padding:2px 8px;border-radius:4px;font-size:12px;font-weight:500;text-align:center;">${statusLabel}</td>
        <td style="font-size:12px;">${row.summary}</td>
      </tr>`;
    }).join('');
  };

  // Wire up weighting section show/collapse toggle
  document.addEventListener('DOMContentLoaded', () => {
    const showBtn = document.getElementById('show-weighting-btn');
    const section = document.getElementById('weighting-section');
    const collapseBtn = document.getElementById('toggle-weighting-btn');
    if (showBtn && section) {
      showBtn.addEventListener('click', () => {
        section.classList.toggle('hidden');
        if (!section.classList.contains('hidden')) {
          renderWeightingTable();
          section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    }
    if (collapseBtn && section) {
      collapseBtn.addEventListener('click', () => {
        section.classList.add('hidden');
      });
    }
  });

  // Render all transaction tables - UPDATED FOR HIERARCHICAL SEARCH
  const renderTransactionTables = () => {
    // Get related transactions for the selected file
    const relatedTransactions = getRelatedTransactions(selectedFile);
    
    // Store both raw aggregate data and deduped merged data.
    window._allRelatedTransactions = relatedTransactions;
    const dedupResult = dedupeTransactionsForTimelineAndReport(relatedTransactions);
    window._preferredRelatedTransactions = dedupResult.preferred;
    window._excludedRelatedTransactions = dedupResult.excluded;
    
    window._currentPropId = selectedFile.prop_id || selectedFile.propId || '';
    window._currentFileNumber = selectedFile.mlsFNo || selectedFile.MLSFileNo || selectedFile.fileNo || selectedFile.fileno || '';
    // Preserve the user's explicit file-number selection across related-file
    // card clicks so generated reports, comments and downstream lookups
    // always carry the file number the user actually searched for.
    if (userSelectedFileNumber && String(userSelectedFileNumber).trim() !== '') {
      window._currentFileNumber = String(userSelectedFileNumber).trim();
    }
    
    console.log('Rendering transaction tables with:', relatedTransactions);
    
    // Separate records by their source_table label (set by normalizeRow in backend).
    // Each per-source tab should collapse identical rows *within its own source*
    // so 4 identical File History "Deed Of Assignment" rows show as 1 — even
    // though the cross-source winner (e.g. a PRA copy) lives on a different tab.
    const dedupeWithinSource = (items) => {
      const seen = new Set();
      return items.filter((item) => {
        const fp = item._dedup_fingerprint;
        if (!fp) return true; // rows that don't participate in dedup are always kept
        if (seen.has(fp)) return false;
        seen.add(fp);
        return true;
      });
    };

    const propertyRecords = sortTimelineChronologically(dedupeWithinSource(
      relatedTransactions.filter(item => item.source_table === 'PRA')
    ));

    const fileHistoryRecords = sortTimelineChronologically(dedupeWithinSource(
      relatedTransactions.filter(item => item.source_table === 'File History')
    ));

    const instrumentRecords = sortTimelineChronologically(dedupeWithinSource(
      relatedTransactions.filter(item => item.source_table === 'Deed Registration')
    ));

    const cofoRecords = sortTimelineChronologically(dedupeWithinSource(
      relatedTransactions.filter(item => item.source_table === 'CofO')
    ));
    
    console.log('Property Record (PRA):', propertyRecords.length);
    console.log('File History (FH):', fileHistoryRecords.length);
    console.log('Instrument Registration (Deed):', instrumentRecords.length);
    console.log('CofO records:', cofoRecords.length);

    // Update colored tab counts in detailed records view
    const tabCountTransactionHistory = document.getElementById('tab-count-transaction-history');
    const tabCountPropertyRecord = document.getElementById('tab-count-property-record');
    const tabCountInstrument = document.getElementById('tab-count-instrument');
    const tabCountCofo = document.getElementById('tab-count-cofo');
    if (tabCountTransactionHistory) tabCountTransactionHistory.textContent = fileHistoryRecords.length;
    if (tabCountPropertyRecord) tabCountPropertyRecord.textContent = propertyRecords.length;
    if (tabCountInstrument) tabCountInstrument.textContent = instrumentRecords.length;
    if (tabCountCofo) tabCountCofo.textContent = cofoRecords.length;

    // Helper: source_table label -> actual DB table name
    const sourceToDbTable = (label) => {
      const map = { 'PRA': 'pra', 'File History': 'file_history_staging', 'CofO': 'CofO_staging', 'Deed Registration': 'deed_registrations' };
      return map[label] || label;
    };

    // Helper: build checkbox cell HTML (hidden by default, shown in cleanup mode)
    const checkboxCell = (item) => `<td class="cleanup-col hidden text-center"><input type="checkbox" class="row-checkbox" data-id="${item.id}" data-table="${sourceToDbTable(item.source_table)}" data-prop-id="${item.prop_id || ''}"></td>`;
    
    // Property Record tab (pra)
    const propertyRecordTable = document.getElementById('property-record-table');
    propertyRecordTable.innerHTML = '';
    
    if (propertyRecords.length > 0) {
      propertyRecords.forEach((item, index) => {
        console.log('Processing property record:', item);
        
        const date = getMappedValue(item, 'date');
        const transactionType = toProperCase(getMappedValue(item, 'transactionType'));
        const party1 = toProperCase(item.party_1 || '-');
        const party2 = toProperCase(item.party_2 || '-');
        const party3 = toProperCase(item.party_3 || '-');
        const party4 = toProperCase(item.party_4 || '-');
        const serialNo = getMappedValue(item, 'serialNo');
        const pageNo = getMappedValue(item, 'pageNo');
        const volumeNo = getMappedValue(item, 'volumeNo');
        const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
        const regDate = formatRegDate(item);
        const regTime = formatRegTime(item);
        const size = getMappedValue(item, 'size');
        const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));
        const commentsShort = comments && comments.length > 50 ? comments.slice(0, 50) + '…' : (comments || '');
        const _wd_pr = recordRichnessScore(item);

        const row = document.createElement('tr');
        row.dataset.id = item.id;
        row.dataset.table = sourceToDbTable(item.source_table);
        row.dataset.propId = item.prop_id || '';
        row.innerHTML = `
          <td>${dedupDot(item)}${index + 1}</td>
          <td class="text-xs text-gray-600 whitespace-nowrap">${renderFileNumberSpan(item, 'fileNumber')}</td>
          <td class="text-gray-500">${_wd_pr}</td>
          <td class="text-gray-500" style="padding-right:4px;">${getTableSourceWeight(item)}</td>
          <td style="white-space:nowrap;padding-left:6px;">${transactionType}</td>
          <td style="white-space:nowrap;">${party1}</td>
          <td>${party2}</td>
          <td>${party3}</td>
          <td>${regParticulars}</td>
          <td>${date}</td>
          <td>${regTime}</td>
          <td>${regDate}</td>
          <td>${size}</td>
          <td class="${item.caveat === 'Yes' ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
          <td title="${comments}" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:default;">${commentsShort}</td>
          <td></td>
        `;
        propertyRecordTable.appendChild(row);
      });
    } else {
      propertyRecordTable.innerHTML = `
        <tr>
          <td colspan="16" class="text-center py-4 text-gray-500">No property records found.</td>
        </tr>
      `;
    }

    // File History tab (file_history_staging table)
    const transactionHistoryTable = document.getElementById('transaction-history-table');
    transactionHistoryTable.innerHTML = '';

    if (fileHistoryRecords.length > 0) {
      fileHistoryRecords.forEach((item, index) => {
        const date = getMappedValue(item, 'date');
        const transactionType = toProperCase(getMappedValue(item, 'transactionType'));
        const party1 = toProperCase(item.party_1 || '-');
        const party2 = toProperCase(item.party_2 || '-');
        const party3 = toProperCase(item.party_3 || '-');
        const party4 = toProperCase(item.party_4 || '-');
        const serialNo = getMappedValue(item, 'serialNo');
        const pageNo = getMappedValue(item, 'pageNo');
        const volumeNo = getMappedValue(item, 'volumeNo');
        const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
        const regDate = formatRegDate(item);
        const regTime = formatRegTime(item);
        const size = getMappedValue(item, 'size');
        const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));
        const commentsShort = comments && comments.length > 50 ? comments.slice(0, 50) + '…' : (comments || '');
        const _wd_fh = recordRichnessScore(item);

        const row = document.createElement('tr');
        row.dataset.id = item.id;
        row.dataset.table = sourceToDbTable(item.source_table);
        row.dataset.propId = item.prop_id || '';
        row.innerHTML = `
          <td>${dedupDot(item)}${index + 1}</td>
          <td class="text-xs text-gray-600 whitespace-nowrap">${renderFileNumberSpan(item, 'fileNumber')}</td>
          <td class="text-gray-500">${_wd_fh}</td>
          <td class="text-gray-500" style="padding-right:4px;">${getTableSourceWeight(item)}</td>
          <td style="white-space:nowrap;padding-left:6px;">${transactionType}</td>
          <td style="white-space:nowrap;">${party1}</td>
          <td>${party2}</td>
          <td>${party3}</td>
          <td>${regParticulars}</td>
          <td>${date}</td>
          <td>${regTime}</td>
          <td>${regDate}</td>
          <td>${size}</td>
          <td class="${item.caveat === 'Yes' ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
          <td title="${comments}" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:default;">${commentsShort}</td>
          <td></td>
        `;
        transactionHistoryTable.appendChild(row);
      });
    } else {
      transactionHistoryTable.innerHTML = `
        <tr>
          <td colspan="16" class="text-center py-4 text-gray-500">No file history records found.</td>
        </tr>
      `;
    }
    
    // Instrument Registration tab (deed_registrations table)
    const instrumentRegistrationTable = document.getElementById('instrument-registration-table');
    instrumentRegistrationTable.innerHTML = '';
    
    if (instrumentRecords.length > 0) {
      console.log('=== RENDERING INSTRUMENT RECORDS ===');
      console.log('Total instrument records:', instrumentRecords.length);
      
      instrumentRecords.forEach((registration, index) => {
        console.log(`Processing instrument record ${index + 1}:`, registration);
        
        const date = getMappedValue(registration, 'date');
        const time = getMappedValue(registration, 'time');
        const transactionType = toProperCase(getMappedValue(registration, 'transactionType'));
        const party1 = toProperCase(registration.party_1 || '-');
        const party2 = toProperCase(registration.party_2 || '-');
        const party3 = toProperCase(registration.party_3 || '-');
        const party4 = toProperCase(registration.party_4 || '-');
        
        // Fix Registration Particulars to show full format (Serial/Page/Volume)
        const serialNo = getMappedValue(registration, 'serialNo');
        const pageNo = getMappedValue(registration, 'pageNo');
        const volumeNo = getMappedValue(registration, 'volumeNo');
        const regNumber = formatRegParticulars(serialNo, pageNo, volumeNo);
        
        // Enhanced logging for ST Fragmentation records
        if (transactionType.toLowerCase().includes('fragmentation') || 
            transactionType.toLowerCase().includes('st fragmentation')) {
          console.log('*** ST FRAGMENTATION RECORD FOUND ***');
          console.log('Transaction Type:', transactionType);
          console.log('Date:', date);
          console.log('Party 1:', party1);
          console.log('Party 2:', party2);
          console.log('Registration Number:', regNumber);
        }
        
        const row = document.createElement('tr');
        row.dataset.id = registration.id;
        row.dataset.table = sourceToDbTable(registration.source_table);
        row.dataset.propId = registration.prop_id || '';
        // Add special styling for ST Fragmentation records
        const isSTFragmentation = transactionType.toLowerCase().includes('fragmentation') || 
                                 transactionType.toLowerCase().includes('st fragmentation');
        
        if (isSTFragmentation) {
          row.className = 'bg-yellow-50 border-l-4 border-l-yellow-400';
        }
        
        const _wd_dr = recordRichnessScore(registration);
        row.innerHTML = `
          <td>${index + 1}</td>
          <td class="text-xs text-gray-600 whitespace-nowrap">${renderFileNumberSpan(registration, 'fileNumber')}</td>
          <td class="text-gray-500">${_wd_dr}</td>
          <td class="text-gray-500" style="padding-right:4px;">${getTableSourceWeight(registration)}</td>
          <td style="white-space:nowrap;padding-left:6px;" class="${isSTFragmentation ? 'text-yellow-800' : ''}">${transactionType}</td>
          <td style="white-space:nowrap;">${party1}</td>
          <td>${party2}</td>
          <td>${party3}</td>
          <td>${regNumber}</td>
          <td>
            <div>${date}</div>
            <div class="text-xs text-gray-600">${time}</div>
          </td>
          <td>${toProperCase(registration.registered_by_name || '-')}</td>
          <td></td>
        `;
        instrumentRegistrationTable.appendChild(row);
      });
      
      // Log summary of ST Fragmentation records found
      const stFragmentationCount = instrumentRecords.filter(record => {
        const transactionType = getMappedValue(record, 'transactionType').toLowerCase();
        return transactionType.includes('fragmentation') || transactionType.includes('st fragmentation');
      }).length;
      
      console.log(`=== ST FRAGMENTATION SUMMARY ===`);
      console.log(`Total ST Fragmentation records displayed: ${stFragmentationCount}`);
      
    } else {
      instrumentRegistrationTable.innerHTML = `
        <tr>
          <td colspan="12" class="text-center py-4 text-gray-500">No instrument registration records found.</td>
        </tr>
      `;
    }
    
    // Certificate of Occupancy (only CofO table)
    const cofoTable = document.getElementById('cofo-table');
    cofoTable.innerHTML = '';
    
    if (cofoRecords.length > 0) {
      cofoRecords.forEach((cofo, index) => {
        const serialNo = getMappedValue(cofo, 'serialNo');
        const pageNo = getMappedValue(cofo, 'pageNo');
        const volumeNo = getMappedValue(cofo, 'volumeNo');
        const cofoRegParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);

        const date = getMappedValue(cofo, 'date');
        const grantee = toProperCase(cofo.party_2 || getMappedValue(cofo, 'grantee'));
        const landUse = toProperCase(getMappedValue(cofo, 'landUse'));
        
        const row = document.createElement('tr');
        row.dataset.id = cofo.id;
        row.dataset.table = sourceToDbTable(cofo.source_table);
        row.dataset.propId = cofo.prop_id || '';
        const _wd_cofo = recordRichnessScore(cofo);
        row.innerHTML = `
          <td>${index + 1}</td>
          <td class="text-xs text-gray-600 whitespace-nowrap">${renderFileNumberSpan(cofo, 'fileNumber')}</td>
          <td class="text-gray-500">${_wd_cofo}</td>
          <td class="text-gray-500" style="padding-right:4px;">${getTableSourceWeight(cofo)}</td>
          <td>${cofoRegParticulars}</td>
          <td>
            <div>${date}</div>
          </td>
          <td>${grantee}</td>
          <td>${landUse}</td>
          <td>${cofo.Period || cofo.term || cofo.occupancy || '-'}</td>
          <td></td>
        `;
        cofoTable.appendChild(row);
      });
    } else {
      cofoTable.innerHTML = `
        <tr>
          <td colspan="10" class="text-center py-4 text-gray-500">No Certificate of Occupancy records found.</td>
        </tr>
      `;
    }

    // Render timeline
    renderTimeline();
    
    // Render excluded/duplicate records
    renderExcludedRows();

    // Load editable comments and show/hide sections based on data
    loadComments(window._currentFileNumber);
    showCommentSections(window._allRelatedTransactions || []);

    // Party 3 is always visible, Party 4 removed
  };
  
  // Switch between tabs in the file details view
  const updateAddRecordButtonVisibility = (tabName) => {
    const btn = document.getElementById('add-record-smart-btn');
    if (!btn) return;
    // Always visible — just point to the right table for the active tab
    btn.setAttribute('data-target', tabName === 'cofo' ? 'cofo_staging' : 'pra');
  };

  const switchTab = (tabName) => {
    // Update active tab
    document.querySelectorAll('.tab').forEach(t => {
      if (t.getAttribute('data-tab') === tabName) {
        t.classList.add('active');
      } else {
        t.classList.remove('active');
      }
    });
    
    // Update visible content
    document.querySelectorAll('.tab-content').forEach(content => {
      content.classList.remove('active');
    });
    document.getElementById(`${tabName}-tab`).classList.add('active');

    // Show Add Record buttons only on their respective tabs.
    updateAddRecordButtonVisibility(tabName);
  };

  // Back to dashboard from file history view
  const backToDashboardBtn = document.getElementById('back-to-dashboard-btn');
  if (backToDashboardBtn) {
    backToDashboardBtn.addEventListener('click', () => {
      fileHistoryView.classList.add('hidden');
      dashboardView.classList.remove('hidden');
    });
  }

  // Timeline button in file history view
  const viewPropertyTimelineBtn = document.getElementById('view-property-timeline-btn');
  if (viewPropertyTimelineBtn) {
    viewPropertyTimelineBtn.addEventListener('click', () => {
      const propId = viewPropertyTimelineBtn.getAttribute('data-prop-id') || '';
      const fileNo = viewPropertyTimelineBtn.getAttribute('data-file-number') || '';
      if (typeof openPropertyTimeline === 'function') {
        openPropertyTimeline(propId, fileNo);
      } else if (typeof window.openTailwindModal === 'function') {
        var params = new URLSearchParams();
        if (propId) params.set('prop_id', propId);
        if (fileNo) params.set('file_number', fileNo);
        params.set('mode', 'partial');
        window.openTailwindModal('/property-search/timeline?' + params.toString(), 'xl');
      }
    });
  }

  // Generate random time strings
  const generateRandomTime = () => {
    const hours = Math.floor(Math.random() * 12) + 1; // 1-12
    const minutes = Math.floor(Math.random() * 60); // 0-59
    const ampm = Math.random() > 0.5 ? 'AM' : 'PM';
    return `${hours}:${minutes.toString().padStart(2, '0')} ${ampm}`;
  };

  // Render legal search report
  const renderLegalSearchReport = () => {
    if (!selectedFile) return;

    // Get related transactions for the selected file
    const { preferred: relatedTransactions } = dedupeTransactionsForTimelineAndReport(getRelatedTransactions(selectedFile));

    // Helper to get Registration Particulars for each transaction
    function getRegistrationParticulars(transaction) {
      // property_records table
      if (
        transaction.hasOwnProperty('serialNo') &&
        transaction.hasOwnProperty('pageNo') &&
        transaction.hasOwnProperty('volumeNo')
      ) {
        return formatRegParticulars(transaction.serialNo, transaction.pageNo, transaction.volumeNo);
      }
      // registered_instruments table
      if (
        transaction.hasOwnProperty('instrument_type') ||
        transaction.hasOwnProperty('rootRegistrationNumber')
      ) {
        return formatRegParticulars(transaction.serial_no, transaction.page_no, transaction.volume_no);
      }
      return '0/0/0';
    }

    // Create combined array of all transactions
    const allTransactions = [];

    relatedTransactions.forEach(transaction => {
      let regNo = getRegistrationParticulars(transaction);
      
      allTransactions.push({
        date: transaction.transaction_date || transaction.cofo_date || transaction.deeds_date || transaction.certificateDate || transaction.approval_date || '-',
        time: transaction.deeds_time || generateRandomTime(),
        transactionType: transaction.transaction_type || transaction.instrument_type || transaction.title_type || 'Record',
        party1: transaction.party_1 || '-',
        party2: transaction.party_2 || '-',
        party3: transaction.party_3 || '-',
        party4: transaction.party_4 || '-',
        regNo: regNo,
        size: transaction.size || transaction.plot_size || '-',
        caveat: transaction.caveat === 'Yes' ? 'Yes' : 'NO',
        comments: transaction.comments || transaction.additional_comments || '-',
        originalRecord: transaction
      });
    });

    // Sort by date (oldest first)
    allTransactions.sort((a, b) => new Date(a.date) - new Date(b.date));

    const fileNumbers = extractFileNumbers(selectedFile);

    // Determine if search was made with primary file numbers
    const searchQuery = document.getElementById('fileNumber').value.trim();
    const isPrimaryFileSearch = searchQuery && (
      // Check if search query matches primary file number patterns
      identifyFileNumberType(searchQuery) === 'parent' ||  // NP FileNO pattern
      identifyFileNumberType(searchQuery) === 'mls' ||     // MLS File No pattern
      identifyFileNumberType(searchQuery) === 'kangis' ||  // KANGIS File No pattern
      identifyFileNumberType(searchQuery) === 'new_kangis' // New KANGIS pattern
    );

    // Update the report content
    document.getElementById('report-file-reference').textContent = fileNumbers.mls;
    
    // Build file numbers display - hide Unit Filno for primary file searches
    let fileNumbersDisplay = `NP FileNo: ${fileNumbers.parent}`;
    
    // Only show Unit Filno if:
    // 1. It's a valid ST file number (subapplication), AND
    // 2. The search was NOT made with primary file numbers
    if (fileNumbers.st !== '-' && 
        fileNumbers.st.match(/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i) && 
        !isPrimaryFileSearch) {
      fileNumbersDisplay += `  |  Unit Filno: ${fileNumbers.st}`;
    }
    
    fileNumbersDisplay += `  |  MLS File No: ${fileNumbers.mls}  |  KANGIS File No: ${fileNumbers.kangis}  |  New KANGIS: ${fileNumbers.new_kangis}`;
    
    document.getElementById('report-file-numbers').textContent = fileNumbersDisplay;
    document.getElementById('report-plot-number').textContent = selectedFile.plot_no || selectedFile.plotNo || "GP No. 1067/1 & 1067/2";
    document.getElementById('report-plan-number').textContent = selectedFile.planNumber || "LKN/RES/2021/3006";
    const _ov = window._fileInfoOverrides || {};
    const _reportDistrict = _ov.district || selectedFile.district || selectedFile.districtName || selectedFile._file_district || 'Niger Street Nassarawa District';
    const _reportLga      = _ov.lga      || selectedFile.lgsaOrCity || selectedFile.lga || selectedFile.lgaName || selectedFile._file_lga || 'Nassarawa';
    document.getElementById('report-plot-description').textContent = `${_reportDistrict}, ${_reportLga} LGA`;
    
    // Update timestamp
    const now = new Date();
    document.getElementById('report-timestamp').textContent = `These details are as at ${now.toLocaleDateString()} ${now.toLocaleTimeString()}`;
    document.getElementById('report-date').textContent = `Date: ${now.toLocaleDateString()}`;
    document.getElementById('report-time').textContent = `Time: ${now.toLocaleTimeString()}`;
    
    // Populate transactions table
    const transactionsTable = document.getElementById('report-transactions-table');
    transactionsTable.innerHTML = '';
    
    allTransactions.forEach((transaction, index) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="border border-gray-300 px-3 py-2">${index + 1}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.party1}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.party2}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.transactionType}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.date}<br><small>${transaction.time}</small></td>
        <td class="border border-gray-300 px-3 py-2">${transaction.regNo}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.size}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.caveat}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.comments}</td>
      `;
      transactionsTable.appendChild(row);
    });
    
    // Update QR code
    const qrCodeImg = document.getElementById('report-qr-code');
    if (qrCodeImg) {
      const fileInfo = `File Number: MLSF: ${fileNumbers.mls} | KANGIS: ${fileNumbers.kangis} | New KANGIS: ${fileNumbers.new_kangis}`;
      qrCodeImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(fileInfo)}`;
    }
  };

  // Switch between table and card view

  // ================================================================
  // SECTION: Timeline Rendering
  // ================================================================
  const timelineSourceToDbTable = (label) => {
    const map = {
      'PRA': 'pra',
      'File History': 'file_history_staging',
      'CofO': 'CofO_staging',
      'Deed Registration': 'deed_registrations',
    };
    return map[label] || label;
  };

  const getTimelinePropId = () => String(selectedFile?.prop_id || '').trim();

  const loadTimelineArrangement = async (propId) => {
    if (!propId) return {};

    const res = await cleanupAjax('/legal_search/get-arrangement', { prop_id: propId });
    if (!res || !res.success) return {};

    return (res.data && res.data.arrangement) ? res.data.arrangement : {};
  };

  const sortTimelineByArrangement = (transactions, arrangementMap) => {
    if (!arrangementMap || Object.keys(arrangementMap).length === 0) {
      return transactions;
    }

    const indexed = transactions.map((item, idx) => ({ item, idx }));

    indexed.sort((a, b) => {
      const keyA = `${timelineSourceToDbTable(a.item.source_table)}:${a.item.id}`;
      const keyB = `${timelineSourceToDbTable(b.item.source_table)}:${b.item.id}`;
      const orderA = arrangementMap[keyA];
      const orderB = arrangementMap[keyB];
      const hasA = Number.isInteger(orderA);
      const hasB = Number.isInteger(orderB);

      if (hasA && hasB) return orderA - orderB;
      if (hasA && !hasB) return -1;
      if (!hasA && hasB) return 1;
      return a.idx - b.idx;
    });

    return indexed.map(x => x.item);
  };

  const parseTimelineDateValue = (value) => {
    if (!value || value === '-') return null;

    const text = String(value).trim();
    if (!text) return null;

    // dd/mm/yyyy or dd-mm-yyyy
    const dmy = text.match(/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/);
    if (dmy) {
      const day = Number(dmy[1]);
      const month = Number(dmy[2]);
      const year = Number(dmy[3]);
      const d = new Date(year, month - 1, day);
      return Number.isNaN(d.getTime()) ? null : d.getTime();
    }

    const parsed = new Date(text);
    return Number.isNaN(parsed.getTime()) ? null : parsed.getTime();
  };

  const getTransactionTimestamp = (item) => {
    const weight = recordPriorityWeight(item);
    
    let candidates;
    if (weight === 1) {
      // For default weight (1) records, prioritize Reg Date
      candidates = [
        item.reg_date,
        item.transaction_date,
        item.deeds_date,
        item.cofo_date,
        item.certificateDate,
        item.approval_date,
        item.date,
      ];
    } else {
      // For high priority (OP, TOT, ROFO), prioritize Transaction Date
      candidates = [
        item.transaction_date,
        item.deeds_date,
        item.reg_date,
        item.cofo_date,
        item.certificateDate,
        item.approval_date,
        item.date,
      ];
    }

    for (const candidate of candidates) {
      const ts = parseTimelineDateValue(candidate);
      if (ts !== null) return ts;
    }

    return null;
  };

  const normalizeWeightingText = (value) => String(value || '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[.,]/g, '');

  const canonicalWeightingInstrumentType = (value) => {
    const raw = normalizeWeightingText(value);
    if (!raw || raw === '-') return raw;

    if (raw.includes('right of occupancy') || raw.includes('right of occupanc')) return 'right of occupancy';
    if (/^r\s*of\s*o$/.test(raw)) return 'right of occupancy';
    const compact = raw.replace(/[^a-z0-9]/g, '');
    if (/^r[o0]f[o0]$/.test(compact) || /^r[o0]f[o0]occupanc/.test(compact)) return 'right of occupancy';
    if (raw === 'customary right of occupancy' || raw === 'statutory right of occupancy') return 'right of occupancy';

    if (raw.includes('certificate of occupancy') || raw.includes('cert of occupancy')) return 'certificate of occupancy';
    if (/^c\s*of\s*o$/.test(raw) || /^c[o0]f[o0]$/.test(compact)) return 'certificate of occupancy';

    if (raw.includes('occupancy permit')) return 'occupancy permit';
    if (/^o\s*p$/.test(raw) || compact === 'op') return 'occupancy permit';

    if (raw.includes('transfer of title')) return 'transfer of title';

    return raw;
  };


  const sortTimelineChronologically = (transactions) => {
    return [...transactions].sort((a, b) => {
      const wa = recordPriorityWeight(a);
      const wb = recordPriorityWeight(b);
      if (wa !== wb) return wb - wa;

      const ta = getTransactionTimestamp(a);
      const tb = getTransactionTimestamp(b);

      if (ta === null && tb === null) {
        return (Number(a.id) || 0) - (Number(b.id) || 0);
      }
      if (ta === null) return 1;
      if (tb === null) return -1;
      if (ta !== tb) return ta - tb;

      return (Number(a.id) || 0) - (Number(b.id) || 0);
    });
  };

  const sourceBadgeClass = (label) => {
    const map = { 'PRA': 'source-badge-pra', 'File History': 'source-badge-fh', 'Deed Registration': 'source-badge-deed', 'CofO': 'source-badge-cofo', 'Related Fileno': 'source-badge-related' };
    return map[label] || '';
  };

  const sourceRowTintClass = (label) => {
    const map = { 'PRA': 'row-tint-pra', 'File History': 'row-tint-fh', 'Deed Registration': 'row-tint-deed', 'CofO': 'row-tint-cofo', 'Related Fileno': 'row-tint-related' };
    return map[label] || '';
  };

  // Build the synthetic "File Commissioning" timeline record. It is always the
  // first row (weight 12). Its Transaction Date is the file's commissioning date
  // (resolved server-side; '-' when the file was not commissioned within KLAES),
  // and its Reg Particulars are 0/0/0.
  const buildCommissioningTimelineRow = () => {
    const commDate = (selectedFile && selectedFile._file_commissioning_date)
      ? selectedFile._file_commissioning_date : '-';
    const rawFileNo = (userSelectedFileNumber && String(userSelectedFileNumber).trim())
      || window._currentFileNumber
      || (selectedFile && (selectedFile.mlsFNo || selectedFile.fileno || selectedFile.fileNo))
      || '-';
    // The File Commissioning row represents the permanent/main file, so it always
    // carries the main file number. When the searched file is itself a temporary
    // "(T)" number, strip the "(T)" here — the temporary number is shown on its own
    // "Temporary File" row directly below.
    const fileNo = String(rawFileNo).replace(/\s*\(\s*T\s*\)\s*$/i, '').trim() || rawFileNo;
    // The commissioning date belongs to whichever file number was actually
    // commissioned (fileNumber.mlsfNo, resolved server-side). It carries a "(T)"
    // suffix when the temporary file was the commissioned one. Show the date on
    // this permanent row ONLY when the commissioned number is NOT a "(T)" number
    // (or when the server reported no commissioned number — legacy responses).
    const commissionedNo = String((selectedFile && selectedFile._file_commissioned_number) || '').trim();
    const commissionedIsTemp = /\(\s*T\s*\)\s*$/i.test(commissionedNo);
    const mainRowDate = (commDate && commDate !== '-' && !commissionedIsTemp)
      ? commDate : '-';
    // Party 1 is the commissioning authority; Party 2 is the file owner/title
    // (the Ministry commissioned the file for them).
    const ownerName = (selectedFile && (selectedFile._file_title || selectedFile.file_title)) || '-';
    return {
      _is_commissioning: true,
      id: 'commissioning',
      source_table: 'File Commissioning',
      fileno: fileNo,
      file_number: fileNo,
      mlsFNo: fileNo,
      transaction_type: 'File Commissioning',
      instrument_type: 'File Commissioning',
      party_1: 'Kano State Ministry of Land and Physical Planning', party_2: ownerName, party_3: '-', party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: mainRowDate,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: (selectedFile && (selectedFile.prop_id || selectedFile.propId)) || '',
    };
  };

  // Build the synthetic "Temporary File" timeline record. It appears directly
  // below File Commissioning (weight 11) only when the searched file has a
  // temporary "(T)" sibling. Its File No is the "(T)" number.
  const buildTemporaryFileTimelineRow = () => {
    let tempFileNo = String(
      (selectedFile && (selectedFile._file_temp_number || selectedFile.temp_file_no)) ||
      window._lsFileTempNumber || ''
    ).trim();
    // When the searched file is ITSELF the temporary "(T)" number there is no child
    // temp for the resolver to return, so fall back to the searched number itself. This
    // keeps the temporary file's own commissioning row visible (directly below the
    // permanent File Commissioning row) when a "(T)" number is searched.
    if (!tempFileNo || tempFileNo === '-') {
      const searched = String(
        (userSelectedFileNumber && String(userSelectedFileNumber).trim())
        || window._currentFileNumber
        || window.__lsLastSearchedFileNumber
        || (selectedFile && (selectedFile.mlsFNo || selectedFile.fileno || selectedFile.fileNo))
        || ''
      ).trim();
      if (/\(\s*T\s*\)\s*$/i.test(searched)) {
        tempFileNo = searched;
      }
    }
    if (!tempFileNo || tempFileNo === '-') return null;

    // The commissioning date sits on THIS temporary row when the commissioned file
    // number (fileNumber.mlsfNo, resolved server-side) is a "(T)" number.
    const commDate = (selectedFile && selectedFile._file_commissioning_date)
      ? selectedFile._file_commissioning_date : '-';
    const commissionedNo = String((selectedFile && selectedFile._file_commissioned_number) || '').trim();
    const commissionedIsTemp = /\(\s*T\s*\)\s*$/i.test(commissionedNo);
    const tempRowDate = (commDate && commDate !== '-' && commissionedIsTemp)
      ? commDate : '-';

    const ownerName = (selectedFile && (selectedFile._file_title || selectedFile.file_title)) || '-';
    return {
      _is_temporary_file: true,
      id: 'temporary-file',
      source_table: 'Temporary File',
      fileno: tempFileNo,
      file_number: tempFileNo,
      mlsFNo: tempFileNo,
      transaction_type: 'Temporary File',
      instrument_type: 'Temporary File',
      party_1: 'Kano State Ministry of Land and Physical Planning', party_2: ownerName, party_3: '-', party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: tempRowDate,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: (selectedFile && (selectedFile.prop_id || selectedFile.propId)) || '',
    };
  };

  const renderTimeline = async () => {
    let transactions = window._preferredRelatedTransactions || window._allRelatedTransactions || [];
    const timelineTable = document.getElementById('timeline-table');
    if (!timelineTable) return;
    timelineTable.innerHTML = '';

    // Timeline view must be chronological by Transaction Date.
    transactions = sortTimelineChronologically(transactions);

    // Default "File Commissioning" record — always the first row in the timeline
    // (weight 12). Commissioning Date = the file's commissioning date when it was
    // commissioned within KLAES (resolved server-side), otherwise '-'. Reg
    // particulars are 0/0/0.
    const commissioningRow = buildCommissioningTimelineRow();
    // "Temporary File" row sits directly below File Commissioning when present.
    const temporaryFileRow = buildTemporaryFileTimelineRow();
    transactions = temporaryFileRow
      ? [commissioningRow, temporaryFileRow, ...transactions]
      : [commissioningRow, ...transactions];

    const timelineTotalCount = document.getElementById('timeline-total-count');
    if (timelineTotalCount) {
      timelineTotalCount.textContent = transactions.length;
    }

    const hasCaveatOnRow = (item) => {
      const caveatText = String(item?.caveat ?? '').trim().toLowerCase();
      const isCaveatTextYes = ['yes', 'y', 'true', '1', 'caveated', 'under caveat'].includes(caveatText);
      const isCaveatedFlag = [1, '1', true, 'true', 'yes', 'y'].includes(item?.is_caveated);

      // Caveat history fields (caveat_number, caveated_comment) may remain after
      // a lift operation, so they must not be treated as "currently caveated".
      return isCaveatTextYes || isCaveatedFlag;
    };

    const timelineHasAnyCaveat = transactions.some(hasCaveatOnRow);

    // Surrender / Release instruments end the encumbrance — placing a new
    // caveat on them makes no legal sense, so hide the "Add Caveat" action.
    // Tolerant to common misspellings (e.g. "Surender" with single 'r').
    const isSurrenderOrRelease = (item) => {
      const raw = String(getMappedValue(item, 'transactionType') || '')
        .trim().toLowerCase().replace(/\s+/g, ' ').replace(/&/g, 'and');
      if (!raw) return false;
      // Normalize "surender" -> "surrender", "sur render" -> "surrender"
      const fuzzy = raw
        .replace(/sur+ender/g, 'surrender')   // surender, surrrender, etc.
        .replace(/surrender+/g, 'surrender'); // surrenderr, etc.
      // Strip "deed of " prefix so "deed of surrender ..." matches "surrender ..."
      const stripped = fuzzy.startsWith('deed of ') ? fuzzy.slice(8) : fuzzy;
      return stripped === 'surrender'
          || stripped === 'release'
          || stripped === 'surrender and release'
          || stripped.includes('surrender and release')
          || stripped.includes('surrender')
          || stripped.includes('release');
    };

    // Expose for the one-time bound click handlers below.
    window._isSurrenderOrRelease = isSurrenderOrRelease;

    transactions.forEach((item, idx) => {
      const date = getMappedValue(item, 'date');
      const transType = toProperCase(getMappedValue(item, 'transactionType'));
      const party1 = toProperCase(item.party_1 || '-');
      const party2 = toProperCase(item.party_2 || '-');
      const party3 = toProperCase(item.party_3 || '-');
      const party4 = toProperCase(item.party_4 || '-');
      const serialNo = getMappedValue(item, 'serialNo');
      const pageNo = getMappedValue(item, 'pageNo');
      const volumeNo = getMappedValue(item, 'volumeNo');
      const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
      const regDate = formatRegDate(item);
      const regTime = formatRegTime(item);
      const size = getMappedValue(item, 'size');
      const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));
      const commentsShort = comments && comments.length > 50 ? comments.slice(0, 50) + '…' : (comments || '');
      const alreadyCaveated = hasCaveatOnRow(item);

      const row = document.createElement('tr');
      row.dataset.id = item.id;
      row.dataset.table = timelineSourceToDbTable(item.source_table);
      row.dataset.originalIndex = idx;
      const tintClass = sourceRowTintClass(item.source_table);
      if (tintClass) row.classList.add(tintClass);
      const dedupScore = item._dedup_score != null ? item._dedup_score : recordPriorityWeight(item);
      // Rule B: Timeline Weight column shows priority-group weights.
      // OP=10, TOT=9, RoFO=8. Other instruments are weight 1.
      const timelineWeight = recordPriorityWeight(item);
      const weightDisplay = timelineWeight;
      const weightColorClass = 'text-gray-500';
      
      row.innerHTML = `
        <td class="cleanup-col hidden text-center"><input type="checkbox" class="row-checkbox" data-id="${item.id}" data-table="${timelineSourceToDbTable(item.source_table)}" data-prop-id="${item.prop_id || ''}"></td>
        <td class="arrange-col hidden text-center font-mono text-xs text-gray-400">${idx + 1}</td>
        <td class="text-center text-xs text-gray-500">${idx + 1}</td>
        <td class="text-xs text-gray-600 whitespace-nowrap">${renderFileNumberSpan(item, 'fileNumber')}</td>
        <td><span class="source-badge ${sourceBadgeClass(item.source_table)}">${item.source_table}</span></td>
        <td class="text-center text-xs ${weightColorClass}">${weightDisplay}</td>
        <td>${transType}</td>
        <td style="white-space:nowrap;">${party1}</td>
        <td>${party2}</td>
        <td>${party3}</td>
        <td>${regParticulars}</td>
        <td>${date}</td>
        <td>${regTime}</td>
        <td>${regDate}</td>
        <td>${size}</td>
        <td class="${alreadyCaveated ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
        <td title="${comments}" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:default;">${commentsShort}</td>
        <td class="text-center relative">
          <div class="timeline-action-dropdown relative inline-block">
            <button class="timeline-action-trigger p-1 rounded text-gray-400 hover:text-gray-700 hover:bg-gray-100" title="Actions">
              <i data-lucide="more-vertical" class="w-4 h-4"></i>
            </button>
            <div class="timeline-action-menu hidden absolute right-0 z-50 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg py-1" style="min-width:176px;">
              <button class="apply-file-info-action flex items-center w-full px-3 py-2 text-xs text-gray-700 hover:bg-gray-50" data-idx="${idx}">
                <i data-lucide="check" class="w-3.5 h-3.5 mr-2 text-green-600"></i>
                Apply to File Info
              </button>
              <button class="add-comment-btn flex items-center w-full px-3 py-2 text-xs text-blue-700 hover:bg-blue-50" data-idx="${idx}">
                <i data-lucide="message-square" class="w-3.5 h-3.5 mr-2"></i>
                Add Comment
              </button>
              ${alreadyCaveated && (timelineSourceToDbTable(item.source_table) === 'pra' || timelineSourceToDbTable(item.source_table) === 'CofO_staging') ? `
              <button class="transfer-caveat-btn flex items-center w-full px-3 py-2 text-xs text-red-700 hover:bg-red-50"
                data-id="${item.id}" data-table="${timelineSourceToDbTable(item.source_table)}"
                data-label="${transType} &mdash; ${party1} / ${party2}">
                <i data-lucide="arrow-left-right" class="w-3.5 h-3.5 mr-2"></i>
                Transfer Caveat
              </button>` : ''}
              ${(!alreadyCaveated && !timelineHasAnyCaveat && !isSurrenderOrRelease(item)) ? `
              <button class="add-caveat-btn flex items-center w-full px-3 py-2 text-xs text-red-700 hover:bg-red-50" data-idx="${idx}">
                <i data-lucide="alert-triangle" class="w-3.5 h-3.5 mr-2"></i>
                Add Caveat
              </button>` : ''}
              ${alreadyCaveated ? `
              <button class="remove-caveat-btn flex items-center w-full px-3 py-2 text-xs text-green-700 hover:bg-green-50" data-idx="${idx}">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 mr-2 text-green-600"></i>
                Remove Caveat
              </button>` : ''}
              <div class="border-t border-gray-100 my-1"></div>
              <button class="drop-record-btn flex items-center w-full px-3 py-2 text-xs text-amber-700 hover:bg-amber-50" data-uid="${getRecordUid(item)}">
                <i data-lucide="arrow-down-left" class="w-3.5 h-3.5 mr-2"></i>
                Drop (Exclude)
              </button>
            </div>
          </div>
        </td>
      `;
      timelineTable.appendChild(row);
    });

    if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: timelineTable.querySelectorAll('[data-lucide]') });

    window._timelineTransactions = transactions;
    // Cache the file-level caveat flag for use by handlers bound once below.
    window._timelineHasAnyCaveat = timelineHasAnyCaveat;

    // Bind the click handlers ONLY ONCE per timelineTable element.
    // Previously these were added on every renderTransactionTables() call, so
    // after re-renders (e.g. reopening the caveat modal) stacked listeners
    // toggled the menu open→close→open→close on a single click, making the
    // action menu appear unclickable.
    if (!timelineTable.dataset.timelineHandlersBound) {
      timelineTable.dataset.timelineHandlersBound = '1';

      // Dropdown toggle for timeline action menus
      timelineTable.addEventListener('click', (e) => {
        const trigger = e.target.closest('.timeline-action-trigger');
        if (!trigger) return;
        e.stopPropagation();
        const menu = trigger.nextElementSibling;
        // Close all others first
        document.querySelectorAll('.timeline-action-menu').forEach(m => {
          if (m !== menu) {
            m.classList.add('hidden');
            // Reset dynamic positioning when closed
            m.style.top = '';
            m.style.bottom = '';
            m.style.marginTop = '';
            m.style.marginBottom = '';
          }
        });

        // Defensive check: if this row is already caveated, never show Add Caveat.
        // Also hide it for Surrender / Release instruments (no new caveat applicable).
        const txns = window._timelineTransactions || [];
        const hasAnyCaveat = window._timelineHasAnyCaveat === true;
        const isSurrenderOrRelease = window._isSurrenderOrRelease || (() => false);
        const idx = parseInt(menu?.querySelector('.apply-file-info-action')?.dataset?.idx || '-1', 10);
        const rowData = txns[idx] || null;
        const addBtn = menu?.querySelector('.add-caveat-btn');
        if (addBtn && (hasAnyCaveat || (rowData && (hasCaveatOnRow(rowData) || isSurrenderOrRelease(rowData))))) {
          addBtn.classList.add('hidden');
        }

        const isOpening = menu.classList.contains('hidden');
        if (!isOpening) {
          menu.classList.add('hidden');
          menu.style.top = '';
          menu.style.bottom = '';
          menu.style.marginTop = '';
          menu.style.marginBottom = '';
          return;
        }

        // Default: open downward
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.marginTop = '';
        menu.style.marginBottom = '';
        menu.classList.remove('hidden');

        // If clipped at the bottom of the scroll container, flip upward
        const scrollWrap = timelineTable.closest('.overflow-x-auto');
        const menuRect = menu.getBoundingClientRect();
        const wrapRect = scrollWrap ? scrollWrap.getBoundingClientRect() : null;
        if (wrapRect && menuRect.bottom > (wrapRect.bottom - 4)) {
          menu.style.top = 'auto';
          menu.style.bottom = 'calc(100% + 4px)';
          menu.style.marginTop = '0';
          menu.style.marginBottom = '0.25rem';
        }
      });

      // "Add Comment" button — opens the Record Comment modal for the clicked row.
      timelineTable.addEventListener('click', (e) => {
        const btn = e.target.closest('.add-comment-btn');
        if (!btn) return;
        e.stopPropagation();
        btn.closest('.timeline-action-menu')?.classList.add('hidden');

        const txns = window._timelineTransactions || [];
        const idx = parseInt(btn.dataset.idx, 10);
        const rowData = txns[idx] || null;

        const modal = document.getElementById('ls-add-comment-modal');
        if (!modal || !rowData) return;

        // Store record identity on the modal for the save handler
        modal.dataset.recordTable = timelineSourceToDbTable(rowData.source_table || '');
        modal.dataset.recordId = rowData.id ?? '';

        // Populate header subtitle
        const rowLabel = document.getElementById('ls-add-comment-row-label');
        if (rowLabel) {
          const transType = toProperCase(getMappedValue(rowData, 'transactionType') || '');
          const party1 = toProperCase(rowData.party_1 || rowData.grantor || '');
          rowLabel.textContent = [transType, party1].filter(Boolean).join(' — ') || 'Selected record';
        }

        // Pre-fill with existing comment if any
        const commentText = document.getElementById('ls-add-comment-text');
        if (commentText) {
          const existing = getMappedValue(rowData, 'comments') || '';
          commentText.value = existing;
        }

        const status = document.getElementById('ls-add-comment-status');
        if (status) { status.textContent = ''; status.classList.add('hidden'); }

        modal.classList.remove('hidden');
        setTimeout(() => commentText?.focus(), 50);
      });

      // Add Comment modal — close / save (bound once globally, not per render)
      if (!window.__addCommentModalBound) {
        window.__addCommentModalBound = true;

        const closeAddCommentModal = () => {
          const modal = document.getElementById('ls-add-comment-modal');
          if (modal) modal.classList.add('hidden');
          const status = document.getElementById('ls-add-comment-status');
          if (status) { status.textContent = ''; status.classList.add('hidden'); }
        };

        document.getElementById('ls-add-comment-close')?.addEventListener('click', closeAddCommentModal);
        document.getElementById('ls-add-comment-cancel')?.addEventListener('click', closeAddCommentModal);
        document.getElementById('ls-add-comment-backdrop')?.addEventListener('click', closeAddCommentModal);

        document.getElementById('ls-add-comment-save')?.addEventListener('click', () => {
          const modal = document.getElementById('ls-add-comment-modal');
          const recordTable = modal?.dataset.recordTable || '';
          const recordId = parseInt(modal?.dataset.recordId || '0', 10);
          const comment = document.getElementById('ls-add-comment-text')?.value?.trim() || '';
          const saveBtn = document.getElementById('ls-add-comment-save');
          const status = document.getElementById('ls-add-comment-status');

          if (!recordTable || !recordId) { alert('Record information missing.'); return; }
          if (!comment) { alert('Please enter a comment.'); return; }

          if (saveBtn) saveBtn.disabled = true;

          fetch('/legal_search/update', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({ table: recordTable, id: recordId, fields: { comments: comment } })
          })
          .then(r => r.json())
          .then(res => {
            if (status) {
              status.textContent = res.success ? 'Comment saved successfully.' : (res.message || 'Failed to save.');
              status.className = `text-xs ${res.success ? 'text-green-600' : 'text-red-600'}`;
              status.classList.remove('hidden');
            }
            if (res.success) {
              // Update the in-memory record so the timeline reflects the new comment
              const txns = window._timelineTransactions || [];
              const tbl = recordTable;
              const rid = recordId;
              txns.forEach(t => {
                if (timelineSourceToDbTable(t.source_table || '') === tbl && Number(t.id) === rid) {
                  t.comments = comment;
                }
              });
              renderTimeline();
              setTimeout(closeAddCommentModal, 1000);
            } else {
              if (saveBtn) saveBtn.disabled = false;
            }
          })
          .catch(() => {
            if (status) {
              status.textContent = 'Network error. Please try again.';
              status.className = 'text-xs text-red-600';
              status.classList.remove('hidden');
            }
            if (saveBtn) saveBtn.disabled = false;
          });
        });
      }

      // "Add Caveat" button in timeline action menu
      timelineTable.addEventListener('click', (e) => {
        const btn = e.target.closest('.add-caveat-btn');
        if (!btn) return;
        e.stopPropagation();
        btn.closest('.timeline-action-menu')?.classList.add('hidden');
        const txns = window._timelineTransactions || [];
        const hasAnyCaveat = window._timelineHasAnyCaveat === true;
        const isSurrenderOrRelease = window._isSurrenderOrRelease || (() => false);
        const idx = parseInt(btn.dataset.idx, 10);
        const rowData = txns[idx] || null;
        if (hasAnyCaveat || (rowData && (hasCaveatOnRow(rowData) || isSurrenderOrRelease(rowData)))) {
          return;
        }
        if (typeof window.__openCaveatModal === 'function') {
          window.__openCaveatModal(rowData);
        }
      });

      // "Remove Caveat" button — lifts the active caveat for the current file.
      timelineTable.addEventListener('click', async (e) => {
        const btn = e.target.closest('.remove-caveat-btn');
        if (!btn) return;
        e.stopPropagation();
        btn.closest('.timeline-action-menu')?.classList.add('hidden');

        const txns = window._timelineTransactions || [];
        const idx = parseInt(btn.dataset.idx || '-1', 10);
        const rowData = txns[idx] || null;
        const rowSourceTable = rowData ? timelineSourceToDbTable(rowData.source_table || '') : '';
        const rowSourceId = rowData && rowData.id != null ? Number(rowData.id) : null;
        const rowFileNo = String(
          rowData?.mlsFNo || rowData?.fileno || rowData?.kangisFileNo || rowData?.NewKANGISFileno || rowData?.temp_fileno || ''
        ).trim();
        const fileNo = String(window._currentFileNumber || rowFileNo || '').trim();

        if (!fileNo) {
          Swal.fire({ icon: 'error', title: 'Missing file number', text: 'Could not determine the current file number.' });
          return;
        }

        const confirmRes = await Swal.fire({
          title: 'Remove caveat?',
          html: 'This will mark the caveat as <strong>lifted</strong> and clear the caveat flag on the file.',
          icon: 'warning',
          input: 'textarea',
          inputLabel: 'Remarks (optional)',
          inputPlaceholder: 'Reason for removing the caveat…',
          showCancelButton: true,
          confirmButtonColor: '#16a34a',
          confirmButtonText: 'Yes, remove',
          cancelButtonText: 'Cancel',
        });
        if (!confirmRes.isConfirmed) return;

        try {
          const res = await fetch('{{ route("caveat.api.remove-by-file") }}', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
              file_number: fileNo,
              remarks: confirmRes.value || '',
              source_table: rowSourceTable || null,
              source_id: Number.isInteger(rowSourceId) ? rowSourceId : null,
            }),
          });
          let data = {};
          try {
            data = await res.json();
          } catch (_) {
            data = {};
          }

          if (!res.ok || !data.success) {
            const validationError = data?.errors ? Object.values(data.errors).flat()[0] : null;
            throw new Error(data.error || data.message || validationError || 'Unable to remove caveat.');
          }
          await Swal.fire({
            icon: 'success',
            title: 'Caveat removed',
            text: 'The caveat has been lifted for file ' + fileNo + '.',
            timer: 2000,
            showConfirmButton: false,
          });
          // Refresh in-place via AJAX so search results & file history stay loaded.
          _pendingCleanupRefresh = true;
          if (typeof performSearch === 'function') {
            performSearch();
          } else if (typeof renderFileHistory === 'function') {
            renderFileHistory();
          }
        } catch (err) {
          Swal.fire({ icon: 'error', title: 'Remove failed', text: err.message || 'Unexpected error.' });
        }
      });
    }

    // Party 3 is always visible in timeline
  };

  // Close timeline dropdowns when clicking outside
  document.addEventListener('click', () => {
    document.querySelectorAll('.timeline-action-menu').forEach(m => m.classList.add('hidden'));
  });

  // ================================================================
  // SECTION: Excluded Records
  // ================================================================
  const renderExcludedRows = () => {
    const excludedTable = document.getElementById('excluded-table');
    if (!excludedTable) return;
    excludedTable.innerHTML = '';
    
    const transactions = window._excludedRelatedTransactions || [];
    
    // Update badge count
    const badge = document.getElementById('excluded-count-badge');
    if (badge) badge.textContent = transactions.length;
    
    const totalCount = document.getElementById('excluded-total-count');
    if (totalCount) totalCount.textContent = transactions.length;

    // Show/hide toggle button
    const toggleBtn = document.getElementById('toggle-excluded-records-btn');
    if (toggleBtn) {
      if (transactions.length > 0) {
        toggleBtn.classList.remove('hidden');
      } else {
        toggleBtn.classList.add('hidden');
        document.getElementById('excluded-records-section')?.classList.add('hidden');
      }
    }

    if (transactions.length === 0) {
      excludedTable.innerHTML = '<tr><td colspan="15" class="text-center py-4 text-gray-400 italic">No duplicate or excluded records.</td></tr>';
      return;
    }

    transactions.forEach((item, idx) => {
      const date = getMappedValue(item, 'date');
      const transType = toProperCase(getMappedValue(item, 'transactionType'));
      const party1 = toProperCase(item.party_1 || '-');
      const party2 = toProperCase(item.party_2 || '-');
      const serialNo = getMappedValue(item, 'serialNo');
      const pageNo = getMappedValue(item, 'pageNo');
      const volumeNo = getMappedValue(item, 'volumeNo');
      const regParticulars = formatRegParticulars(serialNo, pageNo, volumeNo);
      const regDate = formatRegDate(item);
      const regTime = formatRegTime(item);
      const size = getMappedValue(item, 'size');
      const uid = getRecordUid(item);
      const comments = toProperCase((item.is_caveated == 1 && item.caveated_comment) ? item.caveated_comment : getMappedValue(item, 'comments'));
      const commentsShort = comments && comments.length > 50 ? comments.slice(0, 50) + '…' : (comments || '-');
      const _wd_ex = recordRichnessScore(item);
      
      const row = document.createElement('tr');
      row.className = 'border-b border-gray-50 hover:bg-gray-50/50 transition-colors';
      row.innerHTML = `
        <td class="cleanup-col hidden px-3 py-2 text-center"><input type="checkbox" class="row-checkbox" data-id="${item.id}" data-table="${timelineSourceToDbTable(item.source_table)}"></td>
        <td class="px-3 py-2 text-center">${idx + 1}</td>
        <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">${getMappedValue(item, 'fileNumber')}</td>
        <td class="px-3 py-2"><span class="source-badge ${sourceBadgeClass(item.source_table)}">${item.source_table}</span></td>
        <td class="px-3 py-2 text-gray-500">${_wd_ex}</td>
        <td class="px-3 py-2">${party1}</td>
        <td class="px-3 py-2">${party2}</td>
        <td class="px-3 py-2">${transType}</td>
        <td class="px-3 py-2">${regParticulars}</td>
        <td class="px-3 py-2">${date}</td>
        <td class="px-3 py-2">${regTime}</td>
        <td class="px-3 py-2">${regDate}</td>
        <td class="px-3 py-2">${size}</td>
        <td title="${comments}" class="px-3 py-2" style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:default;">${commentsShort}</td>
        <td class="px-3 py-2 text-center">
          <button class="restore-record-btn px-3 py-1 bg-indigo-50 text-indigo-700 rounded border border-indigo-100 hover:bg-indigo-100 font-medium transition-all" data-uid="${uid}">
            Restore
          </button>
        </td>
      `;
      excludedTable.appendChild(row);
    });
  };

  // Toggle Excluded Records Section
  const toggleExcludedBtn = document.getElementById('toggle-excluded-records-btn');
  if (toggleExcludedBtn) {
    toggleExcludedBtn.addEventListener('click', () => {
      const section = document.getElementById('excluded-records-section');
      const isHidden = section.classList.contains('hidden');
      section.classList.toggle('hidden');
      toggleExcludedBtn.innerHTML = isHidden ? 
        '<i data-lucide="eye-off" class="w-4 h-4 mr-2"></i> Hide Excluded Records' : 
        '<i data-lucide="eye" class="w-4 h-4 mr-2"></i> Show Excluded Records';
      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: [toggleExcludedBtn] });
    });
  }

  // Handle Drop Action in Timeline
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.drop-record-btn'); // Assuming you'll add this button
    if (!btn) return;
    const uid = btn.dataset.uid;
    window._manualDroppedIds.add(uid);
    window._manualIncludedIds.delete(uid);
    renderTransactionTables();
  });

  // Handle Restore Action in Excluded Table
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.restore-record-btn');
    if (!btn) return;
    const uid = btn.dataset.uid;
    window._manualIncludedIds.add(uid);
    window._manualDroppedIds.delete(uid);
    renderTransactionTables();
  });

  // Add Drop button to timeline action menu logic or as a direct button
  // For simplicity and per request, let's update renderTimeline row HTML to include a Drop button.

  // ================================================================
  // SECTION: Place Caveat Modal (Legal Search)
  // ================================================================
  (() => {
    const modal       = document.getElementById('ls-caveat-modal');
    const closeBtn    = document.getElementById('ls-caveat-close');
    const cancelBtn   = document.getElementById('ls-caveat-cancel');
    const backdrop    = document.getElementById('ls-caveat-backdrop');
    const form        = document.getElementById('ls-caveat-form');
    const submitBtn   = document.getElementById('ls-caveat-submit');
    const submitLabel = document.getElementById('ls-caveat-submit-label');
    const errorBox    = document.getElementById('ls-caveat-error');
    const isExpiredCb = document.getElementById('ls-caveat-is-expired');
    const isExpiredIn = document.getElementById('ls-caveat-is-expired-input');
    const expiryWrap  = document.getElementById('ls-caveat-expiry-wrapper');
    const fileNumIn   = document.getElementById('ls-caveat-file-number');
    const caveatNumberIn = document.getElementById('ls-caveat-number');
    const caveatNumberSchemeIn = document.getElementById('ls-caveat-number-scheme');
    const sourceTableIn = document.getElementById('ls-caveat-source-table');
    const sourceIdIn = document.getElementById('ls-caveat-source-id');
    const fileDisplay = document.getElementById('ls-caveat-file-display');
    const instrSel    = document.getElementById('ls-caveat-instrument-type');
    const serialIn    = document.getElementById('ls-caveat-serial');
    const pageIn      = document.getElementById('ls-caveat-page');
    const volumeIn    = document.getElementById('ls-caveat-volume');
    const regNumEl    = document.getElementById('ls-caveat-reg-number');
    const startDateIn = document.getElementById('ls-caveat-start-date');
    const releaseDateIn = document.getElementById('ls-caveat-release-date');
    const legalCommentEl = document.getElementById('ls-caveat-legal-comment');

    if (!modal) return;

    const draftIdEl = document.getElementById('ls-caveat-draft-id');

    let instrumentTypesLoaded = false;

    // ── helpers ──────────────────────────────────────────────
    const updateRegNumber = () => {
      if (!regNumEl) return;
      const s = (serialIn?.value || '').trim();
      const p = (pageIn?.value || '').trim();
      const v = (volumeIn?.value || '').trim();
      if (s || v) {
        regNumEl.textContent = [s || '-', p || s || '-', v || '-'].join('/');
        regNumEl.classList.remove('text-gray-400');
        regNumEl.classList.add('text-gray-900');
      } else {
        regNumEl.textContent = 'Enter Serial No. and Volume No. to generate';
        regNumEl.classList.add('text-gray-400');
        regNumEl.classList.remove('text-gray-900');
      }
    };

    const updateReleaseDate = () => {
      if (!startDateIn || !releaseDateIn) return;
      const val = startDateIn.value;
      if (!val) { releaseDateIn.value = ''; return; }
      const d = new Date(val);
      if (isNaN(d.getTime())) { releaseDateIn.value = ''; return; }
      const day = d.getDate();
      d.setMonth(d.getMonth() + 6);
      if (d.getDate() < day) d.setDate(0);
      // Release date field is type="date" — always write YYYY-MM-DD
      releaseDateIn.value = d.getFullYear() + '-'
        + String(d.getMonth() + 1).padStart(2, '0') + '-'
        + String(d.getDate()).padStart(2, '0');
    };

    const updateLegalComment = () => {
      if (!legalCommentEl) return;
      const petitioner = (form?.querySelector('[name="petitioner"]')?.value || '').trim();
      const encumbrance = (form?.querySelector('[name="encumbrance_type"]')?.value || '').trim();
      const startDate  = (startDateIn?.value || '').trim();
      if (!petitioner && !encumbrance && !startDate) {
        legalCommentEl.innerHTML = '<span class="text-gray-400 italic">Auto-generated when form fields are filled</span>';
        return;
      }
      let formattedDate = '-';
      if (startDate) {
        const d = new Date(startDate);
        if (!isNaN(d)) {
          formattedDate = d.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' })
            + (startDate.includes('T') ? ', ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true }) : '');
        }
      }
      legalCommentEl.innerHTML =
        '<strong>Property Under Caveat</strong><br>' +
        'Date: ' + formattedDate + '<br>' +
        'By: ' + (petitioner || '-') + '<br>' +
        'Encumbrance Type: ' + (encumbrance || '-');
    };
    // ─────────────────────────────────────────────────────────

    /**
     * Open the Place Caveat modal.
     * @param {Object|null} rowData — optional timeline row data to prefill from
     */
    const openModal = (rowData) => {
      // Pre-fill file number from currently selected file
      const fileNo = window._currentFileNumber
        || (selectedFile && (selectedFile.mlsFNo || selectedFile.kangisFileNo || selectedFile.NewKANGISFileno || selectedFile.fileno))
        || '';
      fileNumIn.value = fileNo;
      fileDisplay.textContent = fileNo || '—';

      // Pre-fill title holder into grantee (party 2) from the selected file
      const fileTitle = (selectedFile && (selectedFile._file_title || selectedFile.file_title || '')) || '';

      // Reset form state
      form.reset();
      fileNumIn.value = fileNo;
      if (caveatNumberIn) caveatNumberIn.value = '';
      if (caveatNumberSchemeIn) caveatNumberSchemeIn.value = 'ADD-CAV';
      if (sourceTableIn) sourceTableIn.value = '';
      if (sourceIdIn) sourceIdIn.value = '';
      isExpiredIn.value = '0';
      isExpiredCb.checked = false;
      expiryWrap.classList.add('hidden');
      submitLabel.textContent = 'Save Caveat';
      submitBtn.style.background = '';
      submitBtn.disabled = false;
      submitBtn.style.opacity = '';
      submitBtn.style.cursor  = '';
      errorBox.classList.add('hidden');
      errorBox.textContent = '';
      if (regNumEl) { regNumEl.textContent = 'Enter Serial No. and Volume No. to generate'; regNumEl.className = regNumEl.className.replace('text-gray-900', 'text-gray-400'); }
      if (releaseDateIn) releaseDateIn.value = '';

      // Fetch the next ADD-CAV reference (also submitted as caveat_number).
      if (draftIdEl) {
        draftIdEl.textContent = 'Loading…';
        fetch('{{ route("caveat.api.next-draft-id") }}', { headers: { 'Accept': 'application/json' } })
          .then(r => r.json())
          .then(res => {
            const draftId = (res && res.success && res.draft_id) ? String(res.draft_id).trim() : '';
            draftIdEl.textContent = draftId || '—';
            if (caveatNumberIn) caveatNumberIn.value = draftId;
          })
          .catch(() => {
            draftIdEl.textContent = '—';
            if (caveatNumberIn) caveatNumberIn.value = '';
          });
      }

      // Backfill file title into the Grantee / Party 2 field
      const granteeInput = document.getElementById('ls-caveat-grantee');
      const grantorInput = document.getElementById('ls-caveat-grantor');
      const locationInput = document.getElementById('ls-caveat-location');
      if (granteeInput && fileTitle) granteeInput.value = fileTitle;

      // ── Row-level prefill from timeline action menu ──
      if (rowData) {
        const dbTable = timelineSourceToDbTable(rowData.source_table || '');
        if (sourceTableIn) sourceTableIn.value = dbTable || '';
        if (sourceIdIn) sourceIdIn.value = rowData.id != null ? String(rowData.id) : '';

        // Party 1/2 backfill: leave Applicant/Solicitor for manual user entry
        const p1 = rowData.party_1 && rowData.party_1 !== '-' ? rowData.party_1 : '';
        const p2 = rowData.party_2 && rowData.party_2 !== '-' ? rowData.party_2 : '';
        if (grantorInput && p1) grantorInput.value = p1;
        if (granteeInput && p2) granteeInput.value = p2;

        // Serial / Page / Volume
        const sn = rowData.serial_no && rowData.serial_no !== '-' ? rowData.serial_no : '';
        const pn = rowData.page_no && rowData.page_no !== '-' ? rowData.page_no : '';
        const vn = rowData.volume_no && rowData.volume_no !== '-' ? rowData.volume_no : '';
        if (serialIn && sn) serialIn.value = sn;
        if (pageIn && (pn || sn)) pageIn.value = pn || sn;
        if (volumeIn && vn) volumeIn.value = vn;

        // Location
        const loc = (typeof getMappedValue === 'function' ? getMappedValue(rowData, 'location') : (rowData.location || ''));
        if (locationInput && loc && loc !== '-') locationInput.value = loc;

        // Transaction type → encumbrance
        const encEl = form.querySelector('[name="encumbrance_type"]');
        if (encEl && rowData.transaction_type && rowData.transaction_type !== '-') {
          // Try to match an option value (case-insensitive)
          const tt = rowData.transaction_type.toUpperCase();
          const match = Array.from(encEl.options).find(o => o.value.toUpperCase() === tt);
          if (match) encEl.value = match.value;
        }

        // Transaction date → start_date
        const txDate = rowData.transaction_date || rowData.cofo_date || rowData.deeds_date || '';
        if (txDate && txDate !== '-') {
          // Normalize to YYYY-MM-DDThh:mm for datetime-local input
          const d = txDate.includes('T') ? txDate.slice(0, 16) : txDate.slice(0, 10) + 'T00:00';
          startDateIn.value = d;
        }
      }

      // Auto-update derived fields from prefilled values
      if (typeof updateRegNumber === 'function') updateRegNumber();
      if (typeof updateReleaseDate === 'function') updateReleaseDate();
      if (typeof updateLegalComment === 'function') updateLegalComment();
      if (legalCommentEl && !rowData) legalCommentEl.innerHTML = '<span class="text-gray-400 italic">Auto-generated when form fields are filled</span>';

      // Load instrument types (and prefill from row data if available)
      const prefillInstrumentType = (rowData) => {
        if (!rowData) return;
        const txType = (rowData.transaction_type || rowData.instrument_type || '').trim();
        if (!txType || txType === '-') return;
        const ttUpper = txType.toUpperCase();
        const match = Array.from(instrSel.options).find(o => o.textContent.trim().toUpperCase() === ttUpper);
        if (match) instrSel.value = match.value;
      };

      if (!instrumentTypesLoaded) {
        fetch('{{ route("caveat.api.instrument-types") }}', {
          headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(res => {
          if (res.success && res.data) {
            instrSel.innerHTML = '<option value="">Select instrument type</option>';
            res.data.forEach(t => {
              const opt = document.createElement('option');
              opt.value = t.InstrumentTypeID;
              opt.textContent = t.InstrumentName;
              instrSel.appendChild(opt);
            });
            instrumentTypesLoaded = true;
            prefillInstrumentType(rowData);
          }
        })
        .catch(() => {
          instrSel.innerHTML = '<option value="">Could not load types</option>';
        });
      } else {
        prefillInstrumentType(rowData);
      }

      modal.classList.remove('hidden');
      document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
      modal.classList.add('hidden');
      document.body.style.overflow = '';
    };

    // Expose openModal globally so the timeline action menu can call it
    window.__openCaveatModal = openModal;

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    // Serial → Page auto-mirror + reg number + legal comment
    if (serialIn) {
      serialIn.addEventListener('input', () => {
        if (pageIn) pageIn.value = serialIn.value;
        updateRegNumber();
        updateLegalComment();
      });
    }
    if (volumeIn) volumeIn.addEventListener('input', updateRegNumber);

    const syncExpiredSubmitState = () => {
      // Always allow saving — expired caveats can be saved without a release date
    };

    if (startDateIn) {
      const onStartDateChange = () => { updateReleaseDate(); syncExpiredSubmitState(); updateLegalComment(); };
      startDateIn.addEventListener('change', onStartDateChange);
      startDateIn.addEventListener('input',  onStartDateChange);
    }
    const petitionerEl = form?.querySelector('[name="petitioner"]');
    if (petitionerEl) petitionerEl.addEventListener('input', updateLegalComment);
    const encumbranceEl = form?.querySelector('[name="encumbrance_type"]');
    if (encumbranceEl) encumbranceEl.addEventListener('change', updateLegalComment);

    // Expired toggle — show/hide expiry date, update button label/colour
    isExpiredCb.addEventListener('change', () => {
      const expired = isExpiredCb.checked;
      isExpiredIn.value = expired ? '1' : '0';
      expiryWrap.classList.toggle('hidden', !expired);
      submitLabel.textContent = expired ? 'Save Expired Caveat' : 'Save Caveat';
      submitBtn.style.background = expired ? '#6b7280' : '';
      syncExpiredSubmitState();
    });

    // Re-evaluate when release date changes (manual edit or auto-fill from start date)
    if (releaseDateIn) {
      releaseDateIn.addEventListener('change', syncExpiredSubmitState);
      releaseDateIn.addEventListener('input',  syncExpiredSubmitState);
    }

    // Submit
    submitBtn.addEventListener('click', async () => {
      errorBox.classList.add('hidden');
      errorBox.textContent = '';

      // Basic validation
      const fileNo = fileNumIn.value.trim();
      const petitioner = form.querySelector('[name="petitioner"]').value.trim();
      const encumbrance = form.querySelector('[name="encumbrance_type"]').value.trim();
      const startDate = form.querySelector('[name="start_date"]').value.trim();
      if (!fileNo || !petitioner || !encumbrance || !startDate) {
        errorBox.textContent = 'Please fill in all required fields (Encumbrance Type, Petitioner, Date Placed).';
        errorBox.classList.remove('hidden');
        return;
      }

      submitBtn.disabled = true;
      submitLabel.textContent = 'Saving…';

      // Keep caveat_number in sync with the fetched draft ref.
      if (caveatNumberIn && !caveatNumberIn.value) {
        const draftText = String(draftIdEl?.textContent || '').trim();
        if (draftText && draftText !== '—' && draftText !== 'Loading…') {
          caveatNumberIn.value = draftText;
        }
      }
      if (caveatNumberSchemeIn) caveatNumberSchemeIn.value = 'ADD-CAV';

      const formData = new FormData(form);
      // Ensure CSRF token is included
      formData.set('_token', document.querySelector('meta[name="csrf-token"]')?.content || '');

      try {
        const res = await fetch('{{ route("caveat.api.store") }}', {
          method: 'POST',
          headers: { 'Accept': 'application/json' },
          body: formData,
        });
        const json = await res.json();

        if (json.success) {
          closeModal();
          // Silent refresh so caveat column updates immediately
          _pendingCleanupRefresh = true;
          if (typeof performSearch === 'function') performSearch();
          else if (typeof window.renderFileHistory === 'function') window.renderFileHistory();

          Swal.fire({
            icon: 'success',
            title: 'Caveat Recorded',
            text: 'Caveat No: ' + (json.data?.caveat_number || '—'),
            timer: 3000,
            showConfirmButton: false,
          });
        } else {
          errorBox.textContent = json.error || 'Failed to place caveat.';
          errorBox.classList.remove('hidden');
          submitBtn.disabled = false;
          submitLabel.textContent = isExpiredCb.checked ? 'Save Expired Caveat' : 'Save Caveat';
        }
      } catch (e) {
        errorBox.textContent = 'Network error. Please try again.';
        errorBox.classList.remove('hidden');
        submitBtn.disabled = false;
        submitLabel.textContent = isExpiredCb.checked ? 'Save Expired Caveat' : 'Save Caveat';
      }
    });
  })();
  // ================================================================

  // ================================================================
  // SECTION: Editable Comments / Remarks
  // ================================================================
  const loadComments = (fileNumber) => {
    if (!fileNumber) return;
    fetch(`/legal_search/comments?file_number=${encodeURIComponent(fileNumber)}`, {
      headers: { 'Accept': 'application/json' }
    })
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data) {
        const gr = res.data.ground_rent;
        document.getElementById('comment-ground_rent-amount').value = gr?.amount ?? '';
        document.getElementById('comment-ground_rent-text').value = gr?.comment ?? '';

        if (res.data.no_cofo?.comment) {
          document.getElementById('comment-no_cofo-text').value = res.data.no_cofo.comment;
        }
        if (res.data.encumbrance?.comment) {
          document.getElementById('comment-encumbrance-text').value = res.data.encumbrance.comment;
        }
        const litigationInput = document.getElementById('comment-litigation-text');
        if (litigationInput) {
          litigationInput.value = res.data.litigation?.comment ?? '';
        }
        // W/R/C and CoFO overrides — only replace the prefilled default text
        // when this file has a saved override, otherwise keep the editable default.
        if (res.data.wrc?.comment) {
          const wrcInput = document.getElementById('comment-wrc-text');
          if (wrcInput) wrcInput.value = res.data.wrc.comment;
        }
        if (res.data.cofo?.comment) {
          const cofoInput = document.getElementById('comment-cofo-text');
          if (cofoInput) cofoInput.value = res.data.cofo.comment;
        }
      } else {
        document.getElementById('comment-ground_rent-amount').value = '';
        document.getElementById('comment-ground_rent-text').value = '';
        const litigationInput = document.getElementById('comment-litigation-text');
        if (litigationInput) litigationInput.value = '';
      }
    })
    .catch(() => {});
  };

  const showCommentSections = (transactions) => {
    const types = transactions.map(t => (t.transaction_type || '').toLowerCase());
    const hasCofo = types.some(t =>
      t.includes('certificate of occupancy') || t.includes('c of o') ||
      t.includes('cofo') || t.includes('c.of.o') || t.includes('c/o/o')
    );
    const hasCaveat = transactions.some(t => t.is_caveated == 1);
    const hasMortgage = types.some(t => t.includes('deed of mortgage'));
    const hasRelease = types.some(t => t.includes('deed of surrender and release'));
    const mortgageCaveat = hasMortgage && !hasRelease;
    const isClear = hasCofo && !hasCaveat && !mortgageCaveat;

    let baseText = 'Based on our available records, the title is free from encumbrances.';
    if (hasCaveat && mortgageCaveat) {
        baseText = 'This Property is Under an Active Mortgage and Caveat!!!';
    } else if (hasCaveat) {
        baseText = 'N.B. This Property is Under an Active Caveat!!!';
    } else if (mortgageCaveat) {
        baseText = 'This Property is Under an Active Mortgage !!!';
    }

    let noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage, hence Certificate of Occupancy is yet to be issued. However the title is free from encumbrances.';
    if (hasCaveat && mortgageCaveat) {
        noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage. This Property is Under an Active Mortgage and Caveat!!!';
    } else if (hasCaveat) {
        noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage. N.B. This Property is Under an Active Caveat!!!';
    } else if (mortgageCaveat) {
        noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage. This Property is Under an Active Mortgage !!!';
    }

    if (window._underInvestigation) {
        const invText = 'This Property is under Investigation.';
        // Show the dedicated investigation notice panel — keep remark fields clean.
        const noticeSection = document.getElementById('investigation-notice-section');
        const noticeText    = document.getElementById('investigation-notice-text');
        if (noticeSection) noticeSection.classList.remove('hidden');
        if (noticeText)    noticeText.textContent = invText;
    } else {
        const noticeSection = document.getElementById('investigation-notice-section');
        if (noticeSection) noticeSection.classList.add('hidden');
    }

    document.getElementById('comment-encumbrance-text').value = baseText;
    document.getElementById('comment-no_cofo-text').value = noCofoBase;

    // W/R/C remark editor is only revealed for files tagged [WRC] in
    // duplicate_fileno (i.e. Withdrawn / Revoked / Cancelled).
    const wrcSection = document.getElementById('wrc-comment-section');
    if (wrcSection) wrcSection.classList.toggle('hidden', !window._isWrcFile);

    // Both sections are always visible regardless of CofO/caveat state.
    // (Previously conditional; now enabled for all records per user requirement.)
    // DCIV "Under Investigation" is surfaced directly in each row's Comments cell
    // (set server-side), so no separate banner is rendered here.
  };

  document.querySelectorAll('.save-comment-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const commentType = btn.dataset.type;
      const fileNumber = window._currentFileNumber || document.getElementById('file-number-value')?.textContent?.trim();
      if (!fileNumber) return alert('No file selected.');

      const amount = document.getElementById(`comment-${commentType}-amount`)?.value || null;
      const comment = document.getElementById(`comment-${commentType}-text`)?.value || '';
      const statusEl = document.querySelector(`.comment-status[data-type="${commentType}"]`);

      fetch('/legal_search/comments', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        },
        body: JSON.stringify({
          file_number: fileNumber,
          prop_id: window._currentPropId || '',
          comment_type: commentType,
          amount: amount,
          comment: comment
        })
      })
      .then(r => r.json())
      .then(res => {
        if (statusEl) {
          statusEl.textContent = res.success ? 'Saved successfully.' : (res.message || 'Failed to save.');
          statusEl.classList.remove('hidden', 'text-red-600', 'text-green-600');
          statusEl.classList.add(res.success ? 'text-green-600' : 'text-red-600');
          setTimeout(() => statusEl.classList.add('hidden'), 3000);
        }
      })
      .catch(() => {
        if (statusEl) {
          statusEl.textContent = 'Network error.';
          statusEl.classList.remove('hidden', 'text-green-600');
          statusEl.classList.add('text-red-600');
        }
      });
    });
  });

  // Persist edited Client Details (name/address) onto the file's search token so
  // they survive a reload and print on the Pay-Per-Search report.
  const updateClientBtn = document.getElementById('update-client-details-btn');
  if (updateClientBtn) {
    updateClientBtn.addEventListener('click', () => {
      const fileNumber = window._currentFileNumber || document.getElementById('file-number-value')?.textContent?.trim();
      const statusEl = document.getElementById('update-client-details-status');
      const setStatus = (msg, ok) => {
        if (!statusEl) return;
        statusEl.textContent = msg;
        statusEl.classList.remove('hidden', 'text-red-600', 'text-green-600');
        statusEl.classList.add(ok ? 'text-green-600' : 'text-red-600');
        if (ok) setTimeout(() => statusEl.classList.add('hidden'), 3000);
      };
      if (!fileNumber) return setStatus('No file selected.', false);

      const clientName = document.getElementById('comment-client_name-text')?.value || '';
      const clientAddress = document.getElementById('comment-client_address-text')?.value || '';

      fetch('{{ route('legal-search-tokens.client-details.update') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        },
        body: JSON.stringify({
          file_number: fileNumber,
          client_name: clientName,
          client_address: clientAddress
        })
      })
      .then(r => r.json())
      .then(res => {
        setStatus(res.success ? 'Client details updated.' : (res.message || 'Failed to update.'), !!res.success);
        if (res.success) {
          window.__lsTokenClient = { name: res.client_name || '', address: res.client_address || '' };
        }
      })
      .catch(() => setStatus('Network error.', false));
    });
  }

  // ================================================================
  // SECTION: Cleanup Mode Logic
  // ================================================================
  let cleanupModeActive = false;

  const getSelectedRecords = () => {
    const checked = document.querySelectorAll('.row-checkbox:checked');
    const byTable = {};
    checked.forEach(cb => {
      const table = cb.dataset.table;
      const id = parseInt(cb.dataset.id, 10);
      if (!byTable[table]) byTable[table] = [];
      byTable[table].push(id);
    });
    return byTable;
  };

  const getSelectedCount = () => document.querySelectorAll('.row-checkbox:checked').length;

  const updateSelectionCount = () => {
    const selected = document.querySelectorAll('.row-checkbox:checked');
    const count = selected.length;
    const countEl = document.getElementById('cleanup-selection-count');
    if (countEl) {
      countEl.textContent = `${count} selected`;
      countEl.classList.toggle('hidden', count === 0);
    }
    
    // Track selection sources
    let hasExcludedSelected = false;
    let hasTimelineSelected = false;
    selected.forEach(cb => {
      if (cb.closest('#excluded-table')) hasExcludedSelected = true;
      else hasTimelineSelected = true;
    });

    const buttons = ['cleanup-match-btn', 'cleanup-drop-btn', 'cleanup-remove-btn', 'cleanup-edit-btn'];
    buttons.forEach(id => {
      const btn = document.getElementById(id);
      if (btn) {
        btn.disabled = count === 0;
      }
    });

    // Contextual buttons
    const demoteBtn = document.getElementById('smart-demote-btn');
    if (demoteBtn) {
      demoteBtn.disabled = (count === 0 || hasExcludedSelected);
    }

    const promoteBtn = document.getElementById('smart-replace-promote-btn');
    if (promoteBtn) {
      promoteBtn.disabled = (count === 0 || !hasExcludedSelected || hasTimelineSelected);
    }
  };

  const toggleCleanupMode = (active) => {
    cleanupModeActive = active;
    const toggle = document.getElementById('cleanup-mode-toggle');
    const dot = document.getElementById('cleanup-toggle-dot');
    const buttons = ['cleanup-match-btn', 'cleanup-drop-btn', 'cleanup-remove-btn', 'cleanup-edit-btn', 'smart-demote-btn'];

    if (active) {
      toggle.classList.remove('bg-gray-200');
      toggle.classList.add('bg-black');
      dot.classList.remove('translate-x-0');
      dot.classList.add('translate-x-5');
      toggle.setAttribute('aria-checked', 'true');
    } else {
      toggle.classList.add('bg-gray-200');
      toggle.classList.remove('bg-black');
      dot.classList.add('translate-x-0');
      dot.classList.remove('translate-x-5');
      toggle.setAttribute('aria-checked', 'false');
      // Uncheck all
      document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('.select-all-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('tr.row-selected').forEach(tr => tr.classList.remove('row-selected'));
    }

    // Show/hide checkbox columns
    document.querySelectorAll('.cleanup-col').forEach(el => {
      el.classList.toggle('hidden', !active);
    });

    // Enable/disable action buttons
    buttons.forEach(id => {
      const btn = document.getElementById(id);
      if (btn) btn.disabled = !active;
    });

    updateSelectionCount();
  };

  // Toggle event
  const cleanupToggle = document.getElementById('cleanup-mode-toggle');
  if (cleanupToggle) {
    cleanupToggle.addEventListener('click', () => {
      toggleCleanupMode(!cleanupModeActive);
    });
  }

  // Checkbox events (delegated)
  document.addEventListener('change', (e) => {
    if (e.target.classList.contains('row-checkbox')) {
      const tr = e.target.closest('tr');
      if (tr) tr.classList.toggle('row-selected', e.target.checked);
      updateSelectionCount();
    }
    if (e.target.classList.contains('select-all-checkbox')) {
      const tableId = e.target.dataset.table;
      const tbody = document.getElementById(tableId);
      if (tbody) {
        tbody.querySelectorAll('.row-checkbox').forEach(cb => {
          cb.checked = e.target.checked;
          const tr = cb.closest('tr');
          if (tr) tr.classList.toggle('row-selected', cb.checked);
        });
      }
      updateSelectionCount();
    }
  });

  // CSRF token helper
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const cleanupAjax = async (url, body) => {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify(body),
    });
    return res.json();
  };

  const swalAlert = async (message, icon = 'info', title = '') => {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      await window.Swal.fire({
        title,
        text: message,
        icon,
        confirmButtonText: 'OK',
      });
      return;
    }
    alert(message);
  };

  const swalConfirm = async (message, title = 'Are you sure?', icon = 'warning') => {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      const result = await window.Swal.fire({
        title,
        text: message,
        icon,
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel',
        reverseButtons: true,
      });
      return !!result.isConfirmed;
    }
    return confirm(message);
  };

  // Re-run the current search to refresh results after cleanup
  let _pendingCleanupRefresh = false;
  const refreshAfterCleanup = (droppedRecords) => {
    // Remove dropped/removed records from in-memory data and re-render
    if (droppedRecords && fileHistoryView && !fileHistoryView.classList.contains('hidden')) {
      // Build a set of "table:id" keys to remove
      const removeKeys = new Set();
      for (const [table, ids] of Object.entries(droppedRecords)) {
        ids.forEach(id => removeKeys.add(`${table}:${id}`));
      }

      // Filter out dropped records from searchResults
      searchResults = searchResults.filter(r => {
        const key = `${timelineSourceToDbTable(r.source_table)}:${r.id}`;
        return !removeKeys.has(key);
      });

      // Update selectedFile to first remaining result
      if (searchResults.length > 0) {
        selectedFile = searchResults[0];
      }

      // Re-render file history (source tabs + timeline)
      renderFileHistory();

      // Uncheck all and update selection count
      document.querySelectorAll('.row-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('.select-all-checkbox').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('tr.row-selected').forEach(tr => tr.classList.remove('row-selected'));
      updateSelectionCount();
      return;
    }

    // Fallback: re-run search
    _pendingCleanupRefresh = true;
    if (typeof performSearch === 'function') {
      performSearch();
    }
  };

  // MATCH button
  const matchBtn = document.getElementById('cleanup-match-btn');
  if (matchBtn) {
    matchBtn.addEventListener('click', async () => {
      const byTable = getSelectedRecords();
      const tables = Object.keys(byTable);
      if (tables.length === 0) {
        await swalAlert('Select at least one record.', 'warning', 'No Selection');
        return;
      }

      // Find records with prop_id (targets) and without (orphans)
      const allChecked = document.querySelectorAll('.row-checkbox:checked');
      let targetPropId = null;
      const orphanSelections = {};

      allChecked.forEach(cb => {
        const pid = cb.dataset.propId;
        if (pid && pid.trim() !== '') {
          targetPropId = pid.trim();
        } else {
          const table = cb.dataset.table;
          const id = parseInt(cb.dataset.id, 10);
          if (!orphanSelections[table]) orphanSelections[table] = [];
          orphanSelections[table].push(id);
        }
      });

      if (!targetPropId) {
        await swalAlert('Select at least one record WITH a prop_id as the target.', 'warning', 'Target Required');
        return;
      }
      if (Object.keys(orphanSelections).length === 0) {
        await swalAlert('Select at least one orphan record (without prop_id) to match.', 'warning', 'Orphan Required');
        return;
      }

      const proceed = await swalConfirm(`Match selected orphan records to prop_id: ${targetPropId}?`, 'Confirm Match', 'question');
      if (!proceed) return;

      for (const [table, ids] of Object.entries(orphanSelections)) {
        await cleanupAjax('/legal_search/match', { table, ids, prop_id: targetPropId });
      }
      await swalAlert('Records matched successfully.', 'success', 'Completed');
      refreshAfterCleanup();
    });
  }

  // DROP button
  const dropBtn = document.getElementById('cleanup-drop-btn');
  if (dropBtn) {
    dropBtn.addEventListener('click', async () => {
      const byTable = getSelectedRecords();
      if (Object.keys(byTable).length === 0) {
        await swalAlert('Select at least one record.', 'warning', 'No Selection');
        return;
      }
      const proceed = await swalConfirm('Drop selected records from their prop_id group? (They will become orphan records.)', 'Confirm Drop', 'warning');
      if (!proceed) return;

      for (const [table, ids] of Object.entries(byTable)) {
        await cleanupAjax('/legal_search/drop', { table, ids });
      }
      await swalAlert('Records dropped successfully.', 'success', 'Completed');
      refreshAfterCleanup(byTable);
    });
  }

  // REMOVE button
  const removeBtn = document.getElementById('cleanup-remove-btn');
  if (removeBtn) {
    removeBtn.addEventListener('click', async () => {
      const byTable = getSelectedRecords();
      if (Object.keys(byTable).length === 0) {
        await swalAlert('Select at least one record.', 'warning', 'No Selection');
        return;
      }
      const proceed = await swalConfirm('Remove (soft-delete) selected records? They will no longer appear in results.', 'Confirm Remove', 'warning');
      if (!proceed) return;

      for (const [table, ids] of Object.entries(byTable)) {
        await cleanupAjax('/legal_search/remove', { table, ids });
      }
      await swalAlert('Records removed successfully.', 'success', 'Completed');
      refreshAfterCleanup(byTable);
    });
  }

  // DEMOTE button
  const demoteBtn = document.getElementById('smart-demote-btn');
  if (demoteBtn) {
    demoteBtn.addEventListener('click', async () => {
      const selected = document.querySelectorAll('.row-checkbox:checked');
      if (selected.length === 0) {
        await swalAlert('Select at least one record to demote.', 'warning', 'No Selection');
        return;
      }
      
      const proceed = await swalConfirm(`Demote ${selected.length} selected records? They will be moved to the excluded list.`, 'Confirm Demote', 'warning');
      if (!proceed) return;

      selected.forEach(cb => {
        const table = cb.dataset.table;
        const id = cb.dataset.id;
        const uid = `${table}-${id}`;
        window._manualDroppedIds.add(uid);
        window._manualIncludedIds.delete(uid);
      });

      await swalAlert('Records demoted successfully.', 'success', 'Completed');
      renderTransactionTables();
      updateSelectionCount();
    });
  }

  // PROMOTE button
  const promoteBtnInJs = document.getElementById('smart-replace-promote-btn');
  if (promoteBtnInJs) {
    promoteBtnInJs.addEventListener('click', async () => {
      const selected = document.querySelectorAll('.row-checkbox:checked');
      const excludedSelected = Array.from(selected).filter(cb => cb.closest('#excluded-table'));
      
      if (excludedSelected.length === 0) {
        await swalAlert('Select at least one record from the Excluded list to promote.', 'warning', 'No Selection');
        return;
      }

      const proceed = await swalConfirm(`Promote ${excludedSelected.length} selected records back to the main list?`, 'Confirm Promote', 'question');
      if (!proceed) return;

      excludedSelected.forEach(cb => {
        const table = cb.dataset.table;
        const id = cb.dataset.id;
        const uid = `${table}-${id}`;
        window._manualIncludedIds.add(uid);
        window._manualDroppedIds.delete(uid);
      });

      await swalAlert('Records promoted successfully.', 'success', 'Completed');
      renderTransactionTables();
      updateSelectionCount();
    });
  }

  // ================================================================
  // SECTION: Edit Modal
  // ================================================================
  // Field definitions per DB table for the edit form
  const editFieldDefs = {
    'file_history_staging': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time' },
      { key: 'transaction_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
      { key: 'party_1', label: 'Party 1' },
      { key: 'party_2', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' },
      { key: 'party_4', label: 'Party 4' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'plot_size', label: 'Plot Size' },
      { key: 'districtName', label: 'District', type: 'select', optionSource: 'district' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'CofO_staging': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'np_fileno', label: 'NP File No', type: 'fileno' },
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time' },
      { key: 'transaction_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
      { key: 'Grantor', label: 'Party 1' },
      { key: 'Grantee', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' },
      { key: 'party_4', label: 'Party 4' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'period', label: 'Period' }, { key: 'period_unit', label: 'Period Unit' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'pra': [
      // { key: 'kangisFileNo', label: 'KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'fileno', label: 'File No', readonly: true },
      // { key: 'mlsFNo', label: 'MLS File No', type: 'fileno' },  // hidden — using file number selector
      // { key: 'NewKANGISFileno', label: 'New KANGIS File No', type: 'fileno' },  // hidden for now
      { key: 'serialNo', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'pageNo', label: 'Page No', type: 'particular' },
      { key: 'volumeNo', label: 'Volume No', type: 'particular' },
      { key: 'regNo', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'reg_date', label: 'Reg Date', type: 'date' },
      { key: 'reg_time', label: 'Reg Time' },
      { key: 'transaction_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'transaction_date', label: 'Transaction Date', type: 'date' },
      { key: 'party_1', label: 'Party 1' }, { key: 'party_2', label: 'Party 2' },
      { key: 'party_3', label: 'Party 3' }, { key: 'party_4', label: 'Party 4' },
      { key: 'land_use', label: 'Land Use', type: 'select', optionSource: 'land_use' },
      { key: 'plot_no', label: 'Plot No' },
      { key: 'plot_size', label: 'Plot Size' },
      { key: 'districtName', label: 'District', type: 'select', optionSource: 'district' },
      { key: 'lgsaOrCity', label: 'LGA/City', type: 'select', optionSource: 'lga' },
      { key: 'location', label: 'Property Location' },
      { key: 'comments', label: 'Comments', type: 'textarea' }, { key: 'remarks', label: 'Remarks', type: 'textarea' },
    ],
    'deed_registrations': [
      { key: 'fileno', label: 'File No', readonly: true },
      { key: 'parent_fileno', label: 'MLS/NP File No', type: 'fileno' },
      { key: 'serial_no', label: 'Serial No', type: 'particular', sectionStart: 'Registration Number' },
      { key: 'page_no', label: 'Page No', type: 'particular' },
      { key: 'volume_no', label: 'Volume No', type: 'particular' },
      { key: 'registration_number', label: 'Reg No', readonly: true, sectionEnd: true },
      { key: 'instrument_type', label: 'Instrument/Transaction Type', type: 'select', optionSource: 'transaction_type' },
      { key: 'deeds_date', label: 'Deeds Date', type: 'date' }, { key: 'deeds_time', label: 'Deeds Time' },
      { key: 'instrument_date', label: 'Instrument Date', type: 'date' },
      { key: 'grantor', label: 'Party 1' }, { key: 'grantee', label: 'Party 2' },
      { key: 'lga', label: 'LGA' }, { key: 'district', label: 'District' },
      { key: 'plot_number', label: 'Plot Number' }, { key: 'size', label: 'Size' },
      { key: 'property_description', label: 'Property Description', type: 'textarea' },
    ],
  };

  const firstFilled = (...values) => values.find(v => v !== null && v !== undefined && String(v).trim() !== '');

  const updateRegNoFromParticulars = () => {
    const serialInput = document.querySelector('#edit-modal-body [name="serialNo"], #edit-modal-body [name="serial_no"]');
    const pageInput = document.querySelector('#edit-modal-body [name="pageNo"], #edit-modal-body [name="page_no"]');
    const volumeInput = document.querySelector('#edit-modal-body [name="volumeNo"], #edit-modal-body [name="volume_no"]');
    const regNoInput = document.querySelector('#edit-modal-body [name="regNo"], #edit-modal-body [name="registration_number"]');

    if (!serialInput || !pageInput || !volumeInput || !regNoInput) return;

    const serial = String(serialInput.value || '').trim() || '0';
    const page = String(pageInput.value || '').trim() || '0';
    const volume = String(volumeInput.value || '').trim() || '0';
    regNoInput.value = `${serial}/${page}/${volume}`;
  };

  const bindParticularsToRegNo = () => {
    const particulars = document.querySelectorAll('#edit-modal-body [name="serialNo"], #edit-modal-body [name="pageNo"], #edit-modal-body [name="volumeNo"], #edit-modal-body [name="serial_no"], #edit-modal-body [name="page_no"], #edit-modal-body [name="volume_no"]');
    particulars.forEach(el => {
      el.addEventListener('input', updateRegNoFromParticulars);
    });
    updateRegNoFromParticulars();
  };

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

  const getModalSelectOptions = (source, record, fieldKey) => {
    const unique = new Set();

    const add = (v) => {
      const val = String(v ?? '').trim();
      if (val !== '') unique.add(val);
    };

    if (source === 'land_use') {
      dbLandUseOptions.forEach(add);
      add(record?.land_use);
    }

    if (source === 'lga') {
      const lgaSelect = document.getElementById('lga');
      if (lgaSelect) {
        Array.from(lgaSelect.options).forEach(opt => add(opt.value));
      }
      add(record?.lgsaOrCity);
      add(record?.lga);
      (searchResults || []).forEach(r => add(r?.lgsaOrCity || r?.lga));
    }

    if (source === 'district') {
      dbDistrictOptions.forEach(add);
      add(record?.districtName);
      add(record?.district);
    }

    if (source === 'transaction_type') {
      [
        'Certificate of Occupancy',
        'Customary Right of Occupancy',
        'Occupancy Permit (OP)',
        'Deed of Assignment',
        'ST Assignment',
        'Deed of Mortgage',
        'Tripartite Mortgage',
        'Deed of Lease',
        'Deed of Transfer',
        'Deed of Surrender',
        'Power of Attorney',
        'Release of Mortgage',
        'Fragmentation',
        'ST Fragmentation',
        'Other'
      ].forEach(add);

      add(record?.transaction_type);
      add(record?.instrument_type);
      add(record?.title_type);
      (searchResults || []).forEach(r => {
        add(r?.transaction_type);
        add(r?.instrument_type);
        add(r?.title_type);
      });
    }

    // If no source options are available, still keep current value selectable
    if (unique.size === 0) {
      add(record?.[fieldKey]);
    }

    return Array.from(unique).sort((a, b) => a.localeCompare(b));
  };

  let editingRecord = { table: null, id: null };

  const getEditFieldSpanClass = (field) => {
    const fullWidthKeys = ['location', 'property_description', 'comments', 'remarks'];
    if (field.type === 'textarea' || fullWidthKeys.includes(field.key)) {
      return 'lg:col-span-12';
    }
    if (field.type === 'particular') {
      return 'lg:col-span-4';
    }
    return 'lg:col-span-6';
  };

  const openEditModal = async (table, id) => {
    editingRecord = { table, id };
    const modal = document.getElementById('edit-record-modal');
    const body = document.getElementById('edit-modal-body');
    body.innerHTML = '<div class="text-center py-8 text-gray-500">Loading...</div>';
    modal.classList.remove('hidden');

    const res = await cleanupAjax('/legal_search/get-record', { table, id });
    if (!res.success) {
      body.innerHTML = `<div class="text-center py-8 text-red-500">${res.message || 'Failed to load record.'}</div>`;
      return;
    }

    const record = res.data;
    const fields = editFieldDefs[table] || [];
    
    let html = '<div class="grid grid-cols-1 lg:grid-cols-12 gap-4">';
    let inSection = false;
    fields.forEach(f => {
      let val = record[f.key] ?? '';
      // For the readonly fileno field, fall back to any available file number
      if (f.key === 'fileno' && !val) {
        val = record.mlsFNo || record.file_number || record.kangisFileNo || record.NewKANGISFileno || '';
      }
      // Section grouping (Registration Number)
      if (f.sectionStart) {
        html += `<div class="lg:col-span-12 border border-gray-200 rounded-lg p-4 mt-1">
          <label class="block text-xs font-semibold uppercase tracking-wide text-blue-700 mb-3">${f.sectionStart}</label>
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">`;
        inSection = true;
      }
      if (f.type === 'textarea') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <textarea name="${f.key}" rows="2" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">${val}</textarea>
          </div>`;
      } else if (f.type === 'date') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="date" name="${f.key}" value="${val ? val.substring(0, 10) : ''}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
          </div>`;
      } else if (f.type === 'fileno') {
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <div class="flex gap-2">
              <input type="text" name="${f.key}" value="${String(val).replace(/"/g, '&quot;')}" class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
              <button type="button" class="edit-fileno-picker inline-flex items-center px-2.5 py-2 text-xs font-medium border border-gray-300 rounded-md bg-gray-50 hover:bg-gray-100" data-target="${f.key}">Pick</button>
            </div>
          </div>`;
      } else if (f.type === 'select') {
        const options = getModalSelectOptions(f.optionSource, record, f.key);
        const optionsHtml = options.map(opt => {
          const selected = String(val) === String(opt) ? 'selected' : '';
          return `<option value="${escapeHtml(opt)}" ${selected}>${escapeHtml(opt)}</option>`;
        }).join('');

        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <select name="${f.key}" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm bg-white focus:ring-1 focus:ring-black focus:border-black">
              <option value="">Select ${escapeHtml(f.label)}</option>
              ${optionsHtml}
            </select>
          </div>`;
      } else {
        const readOnlyAttr = f.readonly ? 'readonly' : '';
        const readOnlyClass = f.readonly ? 'bg-gray-50' : '';
        html += `
          <div class="${getEditFieldSpanClass(f)}">
            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">${f.label}</label>
            <input type="text" name="${f.key}" value="${String(val).replace(/"/g, '&quot;')}" ${readOnlyAttr} class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-1 focus:ring-black focus:border-black ${readOnlyClass}">
          </div>`;
      }
      // Close section grouping
      if (f.sectionEnd && inSection) {
        html += '</div></div>';
        inSection = false;
      }
    });
    html += '</div>';
    body.innerHTML = html;
    bindParticularsToRegNo();
  };

  const closeEditModal = () => {
    document.getElementById('edit-record-modal').classList.add('hidden');
    editingRecord = { table: null, id: null };
  };

  const saveEditModal = async () => {
    const body = document.getElementById('edit-modal-body');
    const fields = {};
    body.querySelectorAll('input, textarea, select').forEach(el => {
      if (el.name) fields[el.name] = el.value;
    });

    const saveBtn = document.getElementById('edit-modal-save');
    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';

    const res = await cleanupAjax('/legal_search/update', {
      table: editingRecord.table,
      id: editingRecord.id,
      fields,
    });

    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';

    if (res.success) {
      closeEditModal();
      refreshAfterCleanup();
    } else {
      alert(res.message || 'Failed to save.');
    }
  };

  // Edit modal events
  document.getElementById('edit-modal-close')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-cancel')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-backdrop')?.addEventListener('click', closeEditModal);
  document.getElementById('edit-modal-save')?.addEventListener('click', saveEditModal);

  // EDIT button (cleanup toolbar) - opens edit for first selected record
  const editBtn = document.getElementById('cleanup-edit-btn');
  if (editBtn) {
    editBtn.addEventListener('click', () => {
      const firstChecked = document.querySelector('.row-checkbox:checked');
      if (!firstChecked) return alert('Select a record to edit.');
      openEditModal(firstChecked.dataset.table, parseInt(firstChecked.dataset.id, 10));
    });
  }

  // Inline edit-action buttons on each row (delegated)
  document.addEventListener('click', (e) => {
    const editBtn = e.target.closest('.edit-action');
    if (editBtn) {
      const table = editBtn.dataset.table;
      const id = parseInt(editBtn.dataset.id, 10);
      if (table && id) openEditModal(table, id);
    }
  });

  // Shared helper (also used by Transfer Caveat IIFE outside renderTransactionTables scope)
  const sourceToDbTable = (label) => {
    const map = { 'PRA': 'pra', 'File History': 'file_history_staging', 'CofO': 'CofO_staging', 'Deed Registration': 'deed_registrations' };
    return map[label] || label;
  };

  // Transfer Caveat button (delegated)
  (() => {
    let _tcSource = null;
    let _tcTarget = null;

    const modal       = document.getElementById('transfer-caveat-modal');
    const backdrop    = document.getElementById('transfer-caveat-backdrop');
    const closeBtn    = document.getElementById('transfer-caveat-close');
    const cancelBtn   = document.getElementById('transfer-caveat-cancel');
    const confirmBtn  = document.getElementById('transfer-caveat-confirm');
    const sourceLabel = document.getElementById('transfer-caveat-source-label');
    const selectEl    = document.getElementById('transfer-caveat-select');
    const errorEl     = document.getElementById('transfer-caveat-error');

    const closeModal = () => {
      modal?.classList.add('hidden');
      _tcSource = null;
      _tcTarget = null;
      if (selectEl)   { selectEl.value = ''; }
      if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Transfer'; }
      if (errorEl)    { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    };

    backdrop?.addEventListener('click', closeModal);
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.transfer-caveat-btn');
      if (!btn) return;

      // Close the action dropdown immediately
      btn.closest('.timeline-action-menu')?.classList.add('hidden');

      const confirmResult = await Swal.fire({
        title: 'Transfer Caveat?',
        text: 'Are you sure you want to transfer this caveat to another record?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'No, cancel',
      });
      if (!confirmResult.isConfirmed) return;

      _tcSource = { id: parseInt(btn.dataset.id, 10), table: btn.dataset.table, label: btn.dataset.label };
      _tcTarget = null;
      if (confirmBtn) confirmBtn.disabled = true;
      if (errorEl)    { errorEl.classList.add('hidden'); errorEl.textContent = ''; }

      const all = window._allRelatedTransactions || [];
      const candidates = all.filter(r => {
        const t = sourceToDbTable(r.source_table);
        return (t === 'pra' || t === 'CofO_staging') && !(t === _tcSource.table && r.id === _tcSource.id);
      });

      if (sourceLabel) sourceLabel.textContent = 'From: ' + (_tcSource.label || ('ID ' + _tcSource.id));

      if (selectEl) {
        selectEl.innerHTML = candidates.length === 0
          ? '<option value="">No other PRA or CofO records available.</option>'
          : '<option value="">-- Choose a record --</option>' + candidates.map(r => {
              const t   = sourceToDbTable(r.source_table);
              const lbl = toProperCase(getMappedValue(r, 'transactionType') || r.source_table) +
                          ' — ' + toProperCase(r.party_1 || '') + (r.party_2 ? ' / ' + toProperCase(r.party_2) : '') +
                          ' (' + r.source_table + ')';
              return `<option value="${r.id}|${t}">${lbl}</option>`;
            }).join('');

        selectEl.onchange = () => {
          const val = selectEl.value;
          if (!val) { _tcTarget = null; if (confirmBtn) confirmBtn.disabled = true; return; }
          const [id, table] = val.split('|');
          _tcTarget = { id: parseInt(id, 10), table };
          if (confirmBtn) confirmBtn.disabled = false;
        };
      }

      if (typeof lucide !== 'undefined') lucide.createIcons({ nodes: modal?.querySelectorAll('[data-lucide]') });
      modal?.classList.remove('hidden');
    });

    confirmBtn?.addEventListener('click', async () => {
      if (!_tcSource || !_tcTarget) return;
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Transferring...';
      if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }

      try {
        const res = await fetch('{{ route("legalsearch.transferCaveat") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
          },
          body: JSON.stringify({
            source_table: _tcSource.table,
            source_id:    _tcSource.id,
            target_table: _tcTarget.table,
            target_id:    _tcTarget.id,
          }),
        });
        const data = await res.json();
        if (data.success) {
          closeModal();
          // Re-fetch from server so the caveat change is reflected immediately
          _pendingCleanupRefresh = true;
          if (typeof performSearch === 'function') performSearch();
          else if (typeof window.renderFileHistory === 'function') window.renderFileHistory();
        } else {
          if (errorEl) { errorEl.textContent = data.message || 'Transfer failed.'; errorEl.classList.remove('hidden'); }
          confirmBtn.disabled = false;
          confirmBtn.textContent = 'Transfer';
        }
      } catch (_err) {
        if (errorEl) { errorEl.textContent = 'Network error. Please try again.'; errorEl.classList.remove('hidden'); }
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Transfer';
      }
    });
  })();
  let archiveLookupController = null;
  let archiveViewerBound = false;

  const archiveLookupEndpoint = '/legal_search/archive-summary';
  const archivePagesEndpoint = (fileId) => `/filearchive/document-pages/${fileId}`;

  function normalizeArchiveLookupValue(value) {
    return String(value || '')
      .trim()
      .toUpperCase()
      .replace(/[\/_=]+/g, '-')
      .replace(/\s+/g, '');
  }

  function escapeArchiveHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function getCurrentFileReferenceForArchive() {
    const override = String(window.__lsLastSearchedFileNumber || '').trim();
    const panelValue = String(document.getElementById('file-number-value')?.textContent || '').trim();
    const ref = override || panelValue;
    return ref && ref !== '-' ? ref : '';
  }

  function getCurrentArchiveLookupValue() {
    const selectedRelated = String(selectedFile?._file_related_fileno || selectedFile?.related_fileno || '').trim();
    const searchedFile = getCurrentFileReferenceForArchive();
    if (selectedRelated && selectedRelated !== '-' && selectedRelated !== '[]') {
      const normalized = selectedRelated.replace(/^[\[\]\s'\"]+|[\[\]\s'\"]+$/g, '');
      const parts = normalized.split(/[,;|]+/).map(p => p.trim().replace(/^['\"]+|['\"]+$/g, '')).filter(Boolean);
      if (parts.length) {
        return parts[0];
      }
    }
    return searchedFile;
  }

  async function loadLegalSearchArchive() {
    console.log('[Archive Loader] Starting archive load');
    const section = document.getElementById('digital-archive-section');
    if (!section) {
      console.warn('[Archive Loader] Archive section not found in DOM');
      return;
    }

    const foldersEl = document.getElementById('digital-archive-folders');
    const emptyEl = document.getElementById('digital-archive-empty');
    const loadingEl = document.getElementById('digital-archive-loading');
    const fileNumber = getCurrentArchiveLookupValue();
    console.log('[Archive Loader] Current file number:', fileNumber);
    if (!fileNumber) {
      console.warn('[Archive Loader] No file number found');
      if (foldersEl) foldersEl.innerHTML = '';
      if (loadingEl) loadingEl.classList.add('hidden');
      if (emptyEl) {
        emptyEl.textContent = 'No files found in the digital archive for this file.';
        emptyEl.classList.remove('hidden');
      }
      return;
    }

    if (loadingEl) loadingEl.classList.remove('hidden');
    if (emptyEl) emptyEl.classList.add('hidden');
    if (foldersEl) foldersEl.innerHTML = '';

    if (archiveLookupController) {
      archiveLookupController.abort();
    }
    archiveLookupController = new AbortController();

    try {
      const normalizedFileNumber = normalizeArchiveLookupValue(fileNumber);
      const url = `${archiveLookupEndpoint}?file_number=${encodeURIComponent(normalizedFileNumber)}`;
      console.log('[Archive Loader] Fetching from URL:', url);
      const response = await fetch(url, { signal: archiveLookupController.signal, headers: { 'Accept': 'application/json' } });
      const data = await response.json();
      console.log('[Archive Loader] Response received. Status:', response.status, 'Data:', data);

      if (!response.ok || !data.success) {
        throw new Error(data.message || 'Unable to load archive data.');
      }

      const folders = Array.isArray(data.folders) ? data.folders : [];
      console.log('[Archive Loader] Found', folders.length, 'folders');
      if (!folders.length) {
        console.warn('[Archive Loader] No folders found');
        if (emptyEl) {
          emptyEl.textContent = data.message || 'No files found in the digital archive for this file.';
          emptyEl.classList.remove('hidden');
        }
        return;
      }

      console.log('[Archive Loader] Rendering', folders.length, 'archive button(s)');
      if (foldersEl) {
        foldersEl.innerHTML = folders.map((folder) => {
          const folderName = escapeArchiveHtml(folder.folder_name || folder.file_number || 'Archive');
          const fileNumber = escapeArchiveHtml(folder.file_number || folder.folder_name || 'Archive');
          // Red = file is currently logged out via the file tracker.
          // Green = file is in the digital archive (has documents) and not logged out.
          const loggedOut = folder.is_logged_out === true;
          const colorClasses = loggedOut
            ? 'bg-red-600 hover:bg-red-700'
            : 'bg-green-600 hover:bg-green-700';
          const docCount = Number(folder.document_count) || 0;
          const docLabel = docCount ? `${docCount} ${docCount === 1 ? 'doc' : 'docs'}` : '';
          console.log('[Archive Loader] Creating button for folder:', { folderName, fileNumber, folderID: folder.id, loggedOut, docCount });
          return `
            <button type="button" class="archive-folder-card inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium text-white ${colorClasses} transition-colors text-left" data-folder-id="${Number(folder.id)}" data-folder-name="${folderName}" data-file-number="${fileNumber}" title="View digital archive for ${fileNumber}">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z" /></svg>
              <span class="flex-1 min-w-0 truncate">View Digital Archive — ${fileNumber}</span>
              ${docLabel ? `<span class="flex-shrink-0 text-xs opacity-80">${docLabel}</span>` : ''}
            </button>
          `;
        }).join('');
        console.log('[Archive Loader] Archive buttons rendered in DOM');
      }
    } catch (error) {
      if (error.name === 'AbortError') {
        console.log('[Archive Loader] Archive lookup aborted');
        return;
      }
      console.error('[Archive Loader] Error loading archive:', error.message || error);
      if (emptyEl) {
        emptyEl.textContent = 'No files found in the digital archive for this file.';
        emptyEl.classList.remove('hidden');
      }
    } finally {
      if (loadingEl) loadingEl.classList.add('hidden');
    }
  }

  function bindLegalSearchArchiveViewer() {
    if (archiveViewerBound) return;
    archiveViewerBound = true;
    console.log('[Archive Viewer] Binding event listeners for archive folder cards');

    document.addEventListener('click', function (event) {
      const card = event.target.closest('.archive-folder-card');
      if (!card) return;

      console.log('[Archive Viewer] Archive folder card clicked');

      const folderId = card.getAttribute('data-folder-id');
      const folderName = card.getAttribute('data-folder-name');
      console.log('[Archive Viewer] Folder details:', { folderId, folderName });

      if (!folderId) {
        console.warn('[Archive Viewer] No folder ID found on clicked card');
        return;
      }

      if (typeof window.openDocumentViewer !== 'function') {
        console.error('[Archive Viewer] window.openDocumentViewer is not a function', typeof window.openDocumentViewer);
        Swal.fire('Viewer unavailable', 'The digital archive viewer is not available on this page.', 'info');
        return;
      }

      const pagesUrl = archivePagesEndpoint(folderId);
      const metaObj = {
        number: String(card.getAttribute('data-file-number') || card.getAttribute('data-folder-name') || '').trim() || getCurrentFileReferenceForArchive(),
        title: String(card.getAttribute('data-folder-name') || '').trim() || 'Digital Archive'
      };
      console.log('[Archive Viewer] Calling openDocumentViewer with:', { pagesUrl, metaObj });

      window.openDocumentViewer(pagesUrl, false, metaObj);
    });
  }

  // Defer binding until filearchive viewer is available (it's loaded after this script)
  function attemptBindArchiveViewer() {
    if (archiveViewerBound) return;
    const viewerAvailable = typeof window.openDocumentViewer === 'function';
    console.log('[Archive Viewer] Checking viewer availability. Available:', viewerAvailable);
    
    if (!viewerAvailable) {
      // Viewer not ready yet, try again in 200ms
      console.log('[Archive Viewer] Viewer not ready, retrying in 200ms');
      setTimeout(attemptBindArchiveViewer, 200);
      return;
    }
    console.log('[Archive Viewer] Viewer is ready, binding...');
    bindLegalSearchArchiveViewer();
  }
  
  console.log('[Archive Viewer] Initializing. Document readyState:', document.readyState);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attemptBindArchiveViewer);
  } else {
    attemptBindArchiveViewer();
  }

  // Apply-to-File-Information button on timeline rows (delegated)
  document.addEventListener('click', async (e) => {
    const applyBtn = e.target.closest('.apply-file-info-action');
    if (!applyBtn) return;

    // Close the action dropdown immediately
    applyBtn.closest('.timeline-action-menu')?.classList.add('hidden');

    const idx = parseInt(applyBtn.dataset.idx, 10);
    const item = window._timelineTransactions?.[idx];
    if (!item) return;

    const transType   = toProperCase(getMappedValue(item, 'transactionType'));
    const sizeVal     = getMappedValue(item, 'size');
    const party1Val   = toProperCase(item.party_1 || '');
    const party2Val   = toProperCase(item.party_2 || '');
    const districtVal = toProperCase(item.district || item.property_district || item.address_district || '');
    const lgaVal      = toProperCase(item.lga || item.property_lga || item.address_lga || item.lgsaOrCity || '');
    const landUseVal  = toProperCase(item.land_use || item.landUse || item.landUseType || item.title_type || '');

    const confirmResult = await Swal.fire({
      title: 'Apply to File Info?',
      html: `This will update the file information panel with:<br><b>${transType || '-'}</b> &mdash; ${party2Val || party1Val || '-'}`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#2563eb',
      confirmButtonText: 'Yes, apply',
      cancelButtonText: 'No, cancel',
    });
    if (!confirmResult.isConfirmed) return;

    // Store overrides so the report template also uses them
    window._fileInfoOverrides = {
      lastTransaction : transType   && transType   !== '-' ? transType   : null,
      size            : sizeVal     && sizeVal     !== '-' ? sizeVal     : null,
      fileTitle       : (party2Val && party2Val !== '-') ? party2Val : (party1Val && party1Val !== '-' ? party1Val : null),
      district        : districtVal || null,
      lga             : lgaVal      || null,
      landUse         : landUseVal  || null,
    };

    if (window._fileInfoOverrides.lastTransaction) document.getElementById('last-transaction-value').textContent = window._fileInfoOverrides.lastTransaction;
    if (window._fileInfoOverrides.size)            document.getElementById('size-value').textContent            = window._fileInfoOverrides.size;
    if (window._fileInfoOverrides.fileTitle)       document.getElementById('file-title-value').textContent      = window._fileInfoOverrides.fileTitle;
    if (window._fileInfoOverrides.district)        document.getElementById('district-value').textContent        = window._fileInfoOverrides.district;
    if (window._fileInfoOverrides.lga)             document.getElementById('lga-value').textContent             = window._fileInfoOverrides.lga;
    if (window._fileInfoOverrides.landUse)         document.getElementById('property-type-value').textContent  = window._fileInfoOverrides.landUse;

    document.getElementById('reset-file-info-btn')?.classList.remove('hidden');
    loadLegalSearchArchive();
  });

  // Reset File Information back to original file indexing data
  document.getElementById('reset-file-info-btn')?.addEventListener('click', () => {
    window._fileInfoOverrides = null;
    renderFileHistory();
    document.getElementById('reset-file-info-btn')?.classList.add('hidden');
    loadLegalSearchArchive();
  });

  // File number picker buttons inside edit modal
  document.addEventListener('click', (e) => {
    const pickerBtn = e.target.closest('.edit-fileno-picker');
    if (!pickerBtn) return;

    if (typeof window.GlobalFileNoModal === 'undefined' || typeof window.GlobalFileNoModal.open !== 'function') {
      alert('Global File Number selector is not available.');
      return;
    }

    const targetName = pickerBtn.dataset.target;

    window.GlobalFileNoModal.open({
      autoPopulateGenericFields: false,
      callback: function(fileData) {
        const fileNo = fileData?.fileNumber || '';
        const rec = fileData?.record || {};

        const setIfExists = (name, value) => {
          const input = document.querySelector(`#edit-modal-body [name="${name}"]`);
          if (input && value !== null && value !== undefined && String(value).trim() !== '') {
            input.value = String(value);
          }
        };

        setIfExists(targetName, fileNo);

        const mlsValue = firstFilled(rec.mlsFNo, rec.mls_fileno, rec.mls_file_no, rec.MLSFileNo, rec.mls, fileNo);
        const kangisValue = firstFilled(rec.kangisFileNo, rec.kangis_file_no, rec.kangis, rec.KANGISFileNo, fileNo);
        const newKangisValue = firstFilled(rec.NewKANGISFileno, rec.new_kangis_fileno, rec.new_kangis, rec.newKangisFileNo, fileNo);
        const genericFileNo = firstFilled(rec.fileno, rec.file_no, rec.fileNumber, fileNo);
        const parentFileNo = firstFilled(rec.parent_fileno, rec.parentFileNo, rec.np_fileno, rec.npFileNo, fileNo);

        setIfExists('mlsFNo', mlsValue);
        setIfExists('kangisFileNo', kangisValue);
        setIfExists('NewKANGISFileno', newKangisValue);
        setIfExists('fileno', genericFileNo);
        setIfExists('np_fileno', parentFileNo);
        setIfExists('parent_fileno', parentFileNo);
      }
    });
  });

  // Conflict modal events
  document.getElementById('conflict-cancel')?.addEventListener('click', () => {
    document.getElementById('conflict-modal').classList.add('hidden');
  });
  document.getElementById('conflict-modal-backdrop')?.addEventListener('click', () => {
    document.getElementById('conflict-modal').classList.add('hidden');
  });

  // ================================================================
  // SECTION: Arrange Mode (drag-and-drop in timeline)
  // ================================================================
  let arrangeModeActive = false;

  const toggleArrangeMode = (active) => {
    arrangeModeActive = active;
    const btn = document.getElementById('arrange-mode-btn');
    const wrapper = document.getElementById('timeline-table-wrapper');

    if (active) {
      btn.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
      btn.classList.remove('bg-purple-50', 'text-purple-700', 'border-purple-200');
      btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg> Done`;
      wrapper?.classList.add('arrange-active');
      document.querySelectorAll('.arrange-col').forEach(el => el.classList.remove('hidden'));
      enableDragAndDrop();
    } else {
      btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
      btn.classList.add('bg-purple-50', 'text-purple-700', 'border-purple-200');
      btn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg> Arrange`;
      wrapper?.classList.remove('arrange-active');
      document.querySelectorAll('.arrange-col').forEach(el => el.classList.add('hidden'));
      disableDragAndDrop();
    }
  };

  let draggedRow = null;

  const saveTimelineArrangement = async () => {
    const propId = getTimelinePropId();
    if (!propId) return;

    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;

    const items = Array.from(tbody.querySelectorAll('tr'))
      .map((row, index) => ({
        table: row.dataset.table,
        id: parseInt(row.dataset.id, 10),
        order: index + 1,
      }))
      .filter(item => item.table && Number.isInteger(item.id) && item.id > 0);

    if (items.length === 0) return;

    const res = await cleanupAjax('/legal_search/save-arrangement', { prop_id: propId, items });
    if (!res || !res.success) {
      throw new Error(res?.message || 'Failed to save arrangement.');
    }
  };

  const enableDragAndDrop = () => {
    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;

    tbody.querySelectorAll('tr').forEach(row => {
      row.draggable = true;
      row.addEventListener('dragstart', handleDragStart);
      row.addEventListener('dragover', handleDragOver);
      row.addEventListener('drop', handleDrop);
      row.addEventListener('dragend', handleDragEnd);
    });
  };

  const disableDragAndDrop = () => {
    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;

    tbody.querySelectorAll('tr').forEach(row => {
      row.draggable = false;
      row.removeEventListener('dragstart', handleDragStart);
      row.removeEventListener('dragover', handleDragOver);
      row.removeEventListener('drop', handleDrop);
      row.removeEventListener('dragend', handleDragEnd);
    });
  };

  function handleDragStart(e) {
    draggedRow = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  }

  function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
  }

  async function handleDrop(e) {
    e.preventDefault();
    if (draggedRow !== this) {
      const tbody = this.parentNode;
      const rows = Array.from(tbody.children);
      const dragIdx = rows.indexOf(draggedRow);
      const dropIdx = rows.indexOf(this);
      if (dragIdx < dropIdx) {
        tbody.insertBefore(draggedRow, this.nextSibling);
      } else {
        tbody.insertBefore(draggedRow, this);
      }
      renumberTimeline();

      try {
        await saveTimelineArrangement();
      } catch (err) {
        console.error('Timeline arrangement save failed:', err);
        alert('Could not save arrangement. Please try again.');
      }
    }
  }

  function handleDragEnd() {
    this.classList.remove('dragging');
    draggedRow = null;
  }

  const renumberTimeline = () => {
    const tbody = document.getElementById('timeline-table');
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach((row, idx) => {
      const numCell = row.querySelector('.arrange-col');
      if (numCell) numCell.textContent = idx + 1;
    });
  };

  // Arrange button event
  const arrangeBtn = document.getElementById('arrange-mode-btn');
  if (arrangeBtn) {
    arrangeBtn.addEventListener('click', () => {
      toggleArrangeMode(!arrangeModeActive);
    });
  }

  // Print report button - uses the timeline order
  if (printReportBtn) {
    printReportBtn.addEventListener('click', (ev) => {
      ev.preventDefault();

      // Payment gate: online portal requires ₦1,000 before printing
      if (window.LEGAL_SEARCH_CONTEXT?.requiresPayment && !window._lsOnlinePaid) {
        if (typeof window.lsOnlineRequestPayment === 'function') {
          window.lsOnlineRequestPayment(() => printReportBtn.click());
        }
        return;
      }

      const tplUrl = window.LEGAL_SEARCH_CONTEXT?.printTemplateUrl;
      if (tplUrl) {
        const q = new URLSearchParams();
        // Prefer the user's explicitly selected file number so the printed
        // report reflects the file the user searched for, not whichever
        // related-file card was last clicked.
        const _userFn = (typeof userSelectedFileNumber !== 'undefined' && userSelectedFileNumber)
          ? String(userSelectedFileNumber).trim()
          : (window._currentFileNumber || '').toString().trim();
        const fileNo = _userFn || selectedFile?.fileno || selectedFile?.file_number || selectedFile?.mlsFNo || '';
        const propId = selectedFile?.prop_id || '';
        if (fileNo) q.set('file_number', fileNo);
        if (propId) q.set('prop_id', propId);
        // Pass the current Timeline ordering (after Arrange / dedupe) so the
        // print template renders rows in exactly the same order shown in the
        // Timeline table — including any user-applied re-arrangement.
        try {
          const tlSrc = (Array.isArray(window._timelineTransactions) && window._timelineTransactions.length)
            ? window._timelineTransactions
            : (Array.isArray(window._preferredRelatedTransactions) ? window._preferredRelatedTransactions : []);
          if (tlSrc && tlSrc.length) {
            const orderSpec = tlSrc.map(t => {
              const tbl = (typeof timelineSourceToDbTable === 'function')
                ? timelineSourceToDbTable(t.source_table || '')
                : (t.source_table || '');
              const id = (t.id != null ? t.id : '');
              return `${tbl}:${id}`;
            }).join(',');
            if (orderSpec) q.set('timeline_order', orderSpec);
          }
        } catch (_e) { /* non-fatal: server falls back to default order */ }
        // Include any active File Information overrides
        const _ov = window._fileInfoOverrides || {};
        if (_ov.fileTitle)            q.set('display_file_title',    _ov.fileTitle);
        if (_ov.district && _ov.lga)  q.set('display_district_lga', _ov.district + ', ' + _ov.lga);
        else if (_ov.district)        q.set('display_district_lga', _ov.district);
        else if (_ov.lga)             q.set('display_district_lga', _ov.lga);
        if (_ov.landUse)              q.set('display_land_use',      _ov.landUse);
        if (_ov.size)                 q.set('display_size',          _ov.size);
        // Client details: editable fields take precedence, else the verified token values.
        const _cn = (document.getElementById('comment-client_name-text')?.value || window.__lsTokenClient?.name || '').trim();
        const _ca = (document.getElementById('comment-client_address-text')?.value || window.__lsTokenClient?.address || '').trim();
        if (_cn) q.set('client_name', _cn);
        if (_ca) q.set('client_address', _ca);
        const url = q.toString() ? `${tplUrl}?${q.toString()}` : tplUrl;

        // If print-manager doc type is set, open print-manager modal instead
        const pmDocType = window.LEGAL_SEARCH_CONTEXT?.printManagerDocType;
        if (pmDocType) {
          window.dispatchEvent(new CustomEvent('open-print-manager', {
            detail: { ref: fileNo, type: pmDocType, url: url }
          }));
          return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
        return;
      }

      if (typeof renderLegalSearchReport === 'function') {
        renderLegalSearchReport();
      }
    });
  }
  document.querySelectorAll('[data-view]').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-view]').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentView = tab.getAttribute('data-view');
      renderSearchResults();
    });
  });

  // Add event delegation for tab switching and View Detailed Records button
  document.addEventListener('click', (e) => {
    // Tab switching
    if (e.target.closest('.tab')) {
      const tabName = e.target.closest('.tab').getAttribute('data-tab');
      if (tabName) {
        switchTab(tabName);
      }
    }

    // View Detailed Records button
    if (e.target.closest('#view-detailed-records-btn')) {
      const tplUrl = window.LEGAL_SEARCH_CONTEXT?.printTemplateUrl;
      if (tplUrl) {
        const q = new URLSearchParams();
        // Prefer the user's explicitly selected file number so the detail
        // template reflects the file the user searched for, not whichever
        // related-file card was last clicked.
        const _userFn = (typeof userSelectedFileNumber !== 'undefined' && userSelectedFileNumber)
          ? String(userSelectedFileNumber).trim()
          : (window._currentFileNumber || '').toString().trim();
        const fileNo = _userFn || selectedFile?.fileno || selectedFile?.file_number || selectedFile?.mlsFNo || '';
        const propId = selectedFile?.prop_id || '';
        if (fileNo) q.set('file_number', fileNo);
        if (propId) q.set('prop_id', propId);
        // Pass the current Timeline ordering so the detailed records template
        // renders rows in the same order as the Timeline table (incl. Arrange).
        try {
          const tlSrc2 = (Array.isArray(window._timelineTransactions) && window._timelineTransactions.length)
            ? window._timelineTransactions
            : (Array.isArray(window._preferredRelatedTransactions) ? window._preferredRelatedTransactions : []);
          if (tlSrc2 && tlSrc2.length) {
            const orderSpec2 = tlSrc2.map(t => {
              const tbl = (typeof timelineSourceToDbTable === 'function')
                ? timelineSourceToDbTable(t.source_table || '')
                : (t.source_table || '');
              const id = (t.id != null ? t.id : '');
              return `${tbl}:${id}`;
            }).join(',');
            if (orderSpec2) q.set('timeline_order', orderSpec2);
          }
        } catch (_e) { /* non-fatal */ }
        // Include any active File Information overrides
        const _ov2 = window._fileInfoOverrides || {};
        if (_ov2.fileTitle)       q.set('display_file_title',    _ov2.fileTitle);
        if (_ov2.district && _ov2.lga) q.set('display_district_lga', _ov2.district + ', ' + _ov2.lga);
        else if (_ov2.district)   q.set('display_district_lga', _ov2.district);
        else if (_ov2.lga)        q.set('display_district_lga', _ov2.lga);
        if (_ov2.landUse)         q.set('display_land_use',      _ov2.landUse);
        if (_ov2.size)            q.set('display_size',          _ov2.size);
        // Client details: editable fields take precedence, else the verified token values.
        const _cn2 = (document.getElementById('comment-client_name-text')?.value || window.__lsTokenClient?.name || '').trim();
        const _ca2 = (document.getElementById('comment-client_address-text')?.value || window.__lsTokenClient?.address || '').trim();
        if (_cn2) q.set('client_name', _cn2);
        if (_ca2) q.set('client_address', _ca2);
        const url = q.toString() ? `${tplUrl}?${q.toString()}` : tplUrl;

        // If print-manager doc type is set, open print-manager modal instead
        const pmDocType = window.LEGAL_SEARCH_CONTEXT?.printManagerDocType;
        if (pmDocType) {
          window.dispatchEvent(new CustomEvent('open-print-manager', {
            detail: { ref: fileNo, type: pmDocType, url: url }
          }));
          return;
        }

        window.open(url, '_blank', 'noopener,noreferrer');
        return;
      }

      // Fallback only if template route is unavailable
      fileHistoryView.classList.add('hidden');
      legalSearchReportView.classList.remove('hidden');
      renderLegalSearchReport();
    }

    // Back to file details from legal search report view
    if (e.target.closest('#back-to-file-details-btn')) {
      legalSearchReportView.classList.add('hidden');
      fileHistoryView.classList.remove('hidden');
    }

    // Delete action button (placeholder)
    if (e.target.closest('.delete-action')) {
      alert('Delete functionality would be implemented here.');
    }
  });

  // Close modal when pressing Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (searchModal) {
        searchModal.classList.add('hidden');
      }
      const editModal = document.getElementById('edit-record-modal');
      if (editModal && !editModal.classList.contains('hidden')) {
        closeEditModal();
      }
      const conflictModal = document.getElementById('conflict-modal');
      if (conflictModal && !conflictModal.classList.contains('hidden')) {
        conflictModal.classList.add('hidden');
      }
    }
  });
</script>






