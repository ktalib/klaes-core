<script>
  // Lookup data from DB
  const dbLandUseOptions = @json($landUseOptions ?? []);
  const dbDistrictOptions = @json($districtOptions ?? []);
  const dbInstrumentTypeOptions = @json($instrumentTypeOptions ?? []);


  // Helper to generate registration numbers in XX/XX/YYY format
  function generateRegNumber() {
    const prefix = Math.floor(Math.random() * 90) + 10; // 10-99
    const suffix = Math.floor(Math.random() * 300) + 1; // 1-300
    return `${prefix}/${prefix}/${suffix}`;
  }

  // DOM elements 
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

  // Initialize the search trends chart with real weekly search volume
  const initializeChart = (weeklyData) => {
    const canvas = document.getElementById('searchTrendsChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: weeklyData.map(d => d.week),
        datasets: [{
          label: 'Searches',
          data: weeklyData.map(d => d.searches),
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
            text: 'Weekly Search Volume'
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
              text: 'Week'
            }
          }
        }
      }
    });
  };

  // Populate the Search Statistics card with live figures
  const renderDashboardStats = (stats) => {
    const totalEl = document.getElementById('stat-total-searches');
    const printedEl = document.getElementById('stat-printed-reports');
    const successEl = document.getElementById('stat-success-rate');
    const commonTypeEl = document.getElementById('stat-common-type');
    if (totalEl) totalEl.textContent = stats.total_this_month ?? 0;
    if (printedEl) printedEl.textContent = stats.printed_this_month ?? 0;
    if (successEl) successEl.textContent = (stats.success_rate ?? 0) + '%';
    if (commonTypeEl) commonTypeEl.textContent = stats.most_common_type || '-';
  };

  // Populate the Recent Activity feed with the latest search logs
  const renderRecentActivity = (activities) => {
    const list = document.getElementById('recent-activity-list');
    if (!list) return;
    if (!activities || activities.length === 0) {
      list.innerHTML = '<p class="text-sm text-gray-500">No recent searches.</p>';
      return;
    }
    list.innerHTML = activities.map((activity, index) => {
      const borderClass = index < activities.length - 1 ? 'border-b pb-2' : '';
      const badgeClass = activity.status === 'Found' ? 'bg-green-100 text-green-700' : 'bg-gray-100';
      return `
        <div class="flex justify-between items-center ${borderClass}">
          <div>
            <p class="font-medium">${activity.title}</p>
            <p class="text-sm text-gray-500">${activity.time}</p>
          </div>
          <span class="px-2 py-1 text-xs rounded-full ${badgeClass}">${activity.status}</span>
        </div>`;
    }).join('');
  };

  // Fetch live dashboard data (trends, statistics, recent activity) and render it
  const loadDashboardData = () => {
    const statsUrl = window.LEGAL_SEARCH_CONTEXT && window.LEGAL_SEARCH_CONTEXT.dashboardStatsUrl;
    if (!statsUrl) return;
    fetch(statsUrl, { headers: { 'Accept': 'application/json' } })
      .then(response => response.json())
      .then(data => {
        initializeChart(data.weekly_trend || []);
        renderDashboardStats(data.stats || {});
        renderRecentActivity(data.recent_activity || []);
      })
      .catch(error => console.error('Failed to load legal search dashboard stats', error));
  };

  // Load live dashboard data when the page loads
  document.addEventListener('DOMContentLoaded', loadDashboardData);

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
              // The picker's tab key is an identifier ('mls'); MLS reads as MLPP.
              if (filenoTypeLabel) {
                const t = (fileData.tab || fileData.system || '').toUpperCase();
                filenoTypeLabel.textContent = t === 'MLS' ? 'MLPP' : t;
              }
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
        const _apiFileLonLat = data.file_lon_lat || null;
        const _apiRelatedFileno = data.file_related_fileno || null;
        const _apiIndexFileNumber = data.file_index_number || null;
        const _apiFileSize = data.file_size || null;
        const _apiFileGroundRentAmount = data.file_ground_rent_amount || null;
        const _apiFileGroundRentDate = data.file_ground_rent_date || null;
        // Term saved on the file indexing record (Edit File Information).
        const _apiFileTerm = data.file_term || null;
        const _apiCommissioningDate = data.file_commissioning_date || null;
        const _apiCommissionedNumber = data.file_commissioned_number || null;
        const _apiCommissioningHolder = data.file_commissioning_holder || null;
        // mls_file_no.source — how the file was commissioned ('Direct Allocation',
        // 'OP Direct Allocation', …). Feeds the Root of Title annotation.
        const _apiCommissioningSource = data.file_commissioning_source || null;
        const _apiTempFileNumber = data.file_temp_number || null;
        // "SEARCHED (LINKED)" combined file number — e.g. "CON-AG-2014-35 (MLKN 2455)" —
        // resolved server-side so it matches the printable report / Pay-Per-Search template.
        const _apiFileNumberDisplay = data.file_number_display || null;
        // Stash so the File Information card can show it even when the selected
        // record is a prop_id-expanded row from a different file number.
        window._lsFileTempNumber = _apiTempFileNumber;
        // Lineage (previous/successor files + their commissioning info) — used by the
        // timeline to render the full commissioning chain (mother → split/CoP → child).
        window._lsLineage = data.lineage || null;
        // Per-file lifecycle metadata (commissioning/decommissioning/temp flags) for
        // every lifecycle owner surfaced in the result set.
        window._lsLifecycleMeta = data.lifecycle_meta || {};
        // Authoritative block order (Rule 11: transitive ancestors first) from the backend.
        window._lsLifecycleOrder = Array.isArray(data.lifecycle_order) ? data.lifecycle_order : [];
        // Whether the searched file is actually indexed (has its own file_indexings row).
        // When false, the synthetic "File Commissioning" timeline row is suppressed.
        const _apiIsIndexed = (data.is_indexed === true);

        // Expose the searched file's land use + title globally so the standalone
        // "Add New Property Record" opener (partials/file-history.blade.php) can
        // prefill Land Use and backfill Grantee without access to selectedFile.
        window.__lsSelectedFileLandUse = (_apiFileLandUse || '').toString().trim();
        if (_apiFileTitle) window.__lsSelectedFileTitle = _apiFileTitle.toString().trim();

        searchResults.forEach(r => {
          r._file_is_indexed = _apiIsIndexed;
          if (_apiFileTitle) r._file_title = _apiFileTitle;
          if (_apiCommissioningDate) r._file_commissioning_date = _apiCommissioningDate;
          if (_apiCommissionedNumber) r._file_commissioned_number = _apiCommissionedNumber;
          if (_apiCommissioningHolder) r._file_commissioning_holder = _apiCommissioningHolder;
          if (_apiCommissioningSource) r._file_commissioning_source = _apiCommissioningSource;
          if (_apiFileDistrict) r._file_district = _apiFileDistrict;
          if (_apiFileLga) r._file_lga = _apiFileLga;
          if (_apiFileLandUse) r._file_land_use = _apiFileLandUse;
          if (_apiFilePlotNo) r._file_plot_number = _apiFilePlotNo;
          if (_apiFileTpNo) r._file_tp_no = _apiFileTpNo;
          if (_apiFileLonLat) r._file_lon_lat = _apiFileLonLat;
          if (_apiRelatedFileno) r._file_related_fileno = _apiRelatedFileno;
          if (_apiIndexFileNumber) r._file_index_number = _apiIndexFileNumber;
          if (_apiFileSize) r._file_size = _apiFileSize;
          if (_apiFileGroundRentAmount) r._file_ground_rent_amount = _apiFileGroundRentAmount;
          if (_apiFileGroundRentDate) r._file_ground_rent_date = _apiFileGroundRentDate;
          if (_apiFileTerm) r._file_term = _apiFileTerm;
          if (_apiTempFileNumber) r._file_temp_number = _apiTempFileNumber;
          if (_apiFileNumberDisplay) r._file_number_display = _apiFileNumberDisplay;
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
              _file_lon_lat: data.file_lon_lat || null,
              _file_related_fileno: data.file_related_fileno || null,
              _file_index_number: data.file_index_number || null,
              _file_size: data.file_size || null,
              _file_commissioning_date: data.file_commissioning_date || null,
              _file_commissioned_number: data.file_commissioned_number || null,
              _file_commissioning_holder: data.file_commissioning_holder || null,
              _file_commissioning_source: data.file_commissioning_source || null,
              _file_temp_number: data.file_temp_number || null,
              _file_number_display: data.file_number_display || null,
              _file_is_indexed: (data.is_indexed === true),
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
  <th>#</th><th>Source</th><th>File Number</th><th>MLPP FNo</th><th>FileNo</th>
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
    
    // KANGIS File Number patterns: allow 1-6 digits after 4-letter prefix and optional unit suffix
    if (/^(MLKN|KNML|KNGP)\s?\d{1,6}([-_]\d{1,3})?$/i.test(cleanValue)) {
      return 'kangis';
    }
    
    // New KANGIS File Number patterns: KN followed by 2-6 digits, tolerating space/dash
    if (/^KN[\s-]?\d{2,6}$/i.test(cleanValue)) {
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

  // ── Shared result formatting ────────────────────────────────────────────
  // The table row and the card show the same facts, so they read them the same way.

  const SOURCE_STYLES = {
    'PRA':               { label: 'PRA',   cls: 'bg-amber-100 text-amber-800' },
    'File History':      { label: 'FH',    cls: 'bg-indigo-100 text-indigo-800' },
    'CofO':              { label: 'CofO',  cls: 'bg-green-100 text-green-800' },
    'Deed Registration': { label: 'Deed',  cls: 'bg-purple-100 text-purple-800' },
    'Related Fileno':    { label: 'Rel',   cls: 'bg-slate-100 text-slate-700' }
  };

  const sourceBadge = (file) => {
    const meta = SOURCE_STYLES[String(file.source_table || '').trim()];
    if (!meta) return '';
    return `<span class="inline-flex items-center rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide ${meta.cls}">${meta.label}</span>`;
  };

  const isBlank = (v) => v === null || v === undefined || String(v).trim() === '' || String(v).trim() === '-';

  // The alternates, shown only when a row actually carries one. Five always-empty
  // columns is what made the old table unreadable.
  const altFileChips = (fileNumbers, primary) => {
    const alts = [
      ['ST', fileNumbers.st], ['NP', fileNumbers.parent], ['MLS', fileNumbers.mls],
      ['KANGIS', fileNumbers.kangis], ['New KANGIS', fileNumbers.new_kangis]
    ].filter(([, v]) => !isBlank(v) && String(v).trim() !== String(primary).trim());

    if (!alts.length) return '';

    return '<div class="mt-1 flex flex-wrap gap-1">' + alts.map(([k, v]) =>
      `<span class="inline-flex items-center gap-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-600">
         <span class="font-semibold text-gray-400">${k}</span>${v}</span>`).join('') + '</div>';
  };

  const resultLocation = (file) => {
    const parts = [file.property_house_no, file.property_plot_no, file.property_street_name,
                   file.property_district, file.property_lga].filter(v => !isBlank(v));
    return parts.length >= 3 ? toProperCase(parts.join(', ')) : toProperCase(getMappedValue(file, 'location'));
  };

  const partyFlow = (file) => {
    const p1 = toProperCase(file.party_1 || '-');
    const p2 = toProperCase(file.party_2 || '-');
    const p3 = file.party_3 && String(file.party_3).trim() !== '-' ? toProperCase(file.party_3) : '';
    return { p1, p2, p3 };
  };

  const caveatCell = (file) => (String(file.caveat || '').toLowerCase() === 'yes')
    ? '<span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">CAVEAT</span>'
    : '<span class="text-gray-300">&mdash;</span>';

  // Render table results
  const renderTableResults = () => {
    tableResultsBody.innerHTML = '';

    searchResults.forEach((file, index) => {
      const fileNumbers = extractFileNumbers(file);
      const primary = getMappedValue(file, 'fileNumber');
      const { p1, p2, p3 } = partyFlow(file);
      const date = getMappedValue(file, 'date');

      const row = document.createElement('tr');
      row.className = 'hover:bg-blue-50/40 transition-colors align-top';
      row.innerHTML = `
        <td class="px-3 py-2.5 text-xs text-gray-400">${index + 1}</td>
        <td class="px-3 py-2.5">
          <div class="flex items-center gap-1.5">
            <span class="font-mono text-xs font-bold text-blue-700">${primary}</span>
            ${sourceBadge(file)}
          </div>
          ${altFileChips(fileNumbers, primary)}
        </td>
        <td class="px-3 py-2.5">
          <div class="text-xs font-semibold text-gray-800">${toProperCase(getMappedValue(file, 'transactionType'))}</div>
          ${isBlank(date) ? '' : `<div class="text-[10px] text-gray-400 mt-0.5">${date}</div>`}
        </td>
        <td class="px-3 py-2.5">
          <div class="text-xs text-gray-700">${p1}</div>
          <div class="text-xs text-gray-900 font-medium flex items-start gap-1">
            <span class="text-gray-300">&rarr;</span><span>${p2}</span>
          </div>
          ${p3 ? `<div class="text-[10px] text-gray-400 mt-0.5">+ ${p3}</div>` : ''}
        </td>
        <td class="px-3 py-2.5 text-xs text-gray-600 max-w-[220px]">${resultLocation(file)}</td>
        <td class="px-3 py-2.5 text-xs text-gray-600">${getMappedValue(file, 'plotNo')}</td>
        <td class="px-3 py-2.5 text-xs text-gray-600">${getMappedValue(file, 'size')}</td>
        <td class="px-3 py-2.5">${caveatCell(file)}</td>
        <td class="px-3 py-2.5 text-right">
          <button class="view-file-btn inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-blue-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition" data-index="${index}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            View
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
    // A SYSTEM temporary number ("TEMP-91950") is an internal placeholder — never shown in the
    // File No field (mirrors PHP: the file_no is blanked server-side for the print report).
    if (isSystemTempFileNo(itemFileNo)) return '-';
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

  // Rule B: event weight for the Timeline sort. The numbers come from
  // App\Support\LegalSearchTimelineWeights so this table and the printed slip cannot drift
  // apart. A null weight marks a "floating" event (the C of O, parcel updates, decommissionings): it
  // carries no rank and is injected chronologically by sortTimelineChronologically().
  const TIMELINE_WEIGHTS = @json(\App\Support\LegalSearchTimelineWeights::MAP);
  const PARCEL_UPDATE_PATTERN = /subdivision|merger|change of purpose|plot extension|separation|parcel update/;

  // An OLD Ministry KN number carries a SEPARATOR — "KN 3686", "KN-3686". A new-KANGIS number
  // is written solid, "KN3686", and is a different registry entirely.
  // Mirrors LegalSearchTimelineWeights::isOldKnFileNo().
  const isOldKnFileNo = (v) => /^KN[\s\-]\d+/i.test(String(v || '').trim());

  // The old Ministry file's OPENING line (its commissioning or recertification row), not an
  // ordinary dealing that merely sits on that file — a mortgage registered against "KN 3686"
  // keeps its normal rank. Mirrors LegalSearchTimelineWeights::isOldKnCommissioningRow().
  const isOldKnCommissioningRow = (item, txType) => {
    if (!txType.includes('recertification') && !txType.includes('file commissioning')) return false;
    const own = ['file_no', 'fileno', 'file_number', 'mlsFNo']
      .map(c => String(item?.[c] || '').trim())
      .find(v => v && v !== '-') || '';
    return isOldKnFileNo(own);
  };

  const classifyTimelineEvent = (item) => {
    // Tested first: the old Ministry file's opening line outranks every other event,
    // including the commissioning row it sits above.
    if (isOldKnCommissioningRow(item, canonicalWeightingInstrumentType(getMappedValue(item, 'transactionType')))) {
      return 'OLD_KN_COMMISSIONING';
    }
    if (item?._is_commissioning) return 'FILE_COMMISSIONING';
    if (item?._is_temporary_file) return 'TEMP_FILE_COMMISSIONING';
    if (item?._is_decommissioning) return 'FILE_DECOMMISSIONING';

    const source = String(item?.source_table || '').trim();
    if (source === 'DCIV File Commissioning') return 'DCIV_COMMISSIONING';
    // ST commissioning rows (primary + "– Fragmentation" units) rank at the
    // File Commissioning tier so they open their lifecycle block.
    if (source === 'ST File Commissioning'
        || String(getMappedValue(item, 'transactionType') || '').startsWith('ST File Commissioning')) {
      return 'FILE_COMMISSIONING';
    }

    // 'Related Fileno' is a source, not an event: a Merger or Subdivision links files just
    // as a recertification does, so the type must win. Testing the source first would rank
    // a Subdivision as a recertification (8) and lift it out of the floating events.
    const txType = canonicalWeightingInstrumentType(getMappedValue(item, 'transactionType'));
    if (txType === 'occupancy permit') return 'OCCUPANCY_PERMIT';
    if (txType === 'transfer of title') return 'TRANSFER_OF_TITLE_OP';
    if (txType === 'right of occupancy') return 'RIGHT_OF_OCCUPANCY';
    if (txType.includes('recertification')) return 'KANGIS_RECERTIFICATION';
    if (txType.includes('certificate of occupanc')) return 'CERTIFICATE_OF_OCCUPANCY';
    if (PARCEL_UPDATE_PATTERN.test(txType)) return 'PARCEL_UPDATE';

    // Lineage rows — a parent/child file's own commissioning or decommissioning, which reach
    // the timeline through the 'Related Fileno' source rather than their own source_table.
    // Classified on TYPE so they land in the commissioning tier and the floating band; without
    // this they fell through to the untyped fallback below and were ranked as recerts (8).
    if (txType.includes('decommissioning')) return 'FILE_DECOMMISSIONING';
    if (txType.includes('file commissioning')) return 'FILE_COMMISSIONING';

    // An untyped 'Related Fileno' row is the synthetic KANGIS recertification marker.
    if (source === 'Related Fileno') return 'KANGIS_RECERTIFICATION';
    return 'OTHER_INSTRUMENTS';
  };

  // Mirrors LegalSearchTimelineWeights::DATE_FIELDS / eventYear(). Only the 4-digit year
  // matters, and the same row reaches here as "2026-05-11", "May 11, 2026", "11/05/2026" or
  // a bare "2026", so it is read by regex rather than parsed.
  const TIMELINE_DATE_FIELDS = [
    'reg_date', 'deeds_date', 'transaction_date',
    'cofo_date', 'certificateDate', 'approval_date', 'date',
  ];

  const timelineEventYear = (item) => {
    for (const field of TIMELINE_DATE_FIELDS) {
      const value = String(item?.[field] ?? '').trim();
      if (!value || value === '-') continue;
      const match = value.match(/(?:19|20)\d{2}/);
      if (match) return Number(match[0]);
    }
    return null;
  };

  // Lineage rows (a child file's commissioning/decommissioning) are positioned by splice
  // against the parcel-update that created them — see renderTimeline. They must never be
  // weight-sorted, so they float: a later re-sort (e.g. the arrange feature) can no longer
  // hoist them to the top of the table alongside the searched file's own commissioning.
  const recordPriorityWeight = (item) => {
    if (item?._pinned) return null;
    const eventKey = classifyTimelineEvent(item);

    // Current-year exception (mirrors LegalSearchTimelineWeights::isCurrentYearInstrument):
    // a dealing dated in the year we are in now closes the timeline, below the C of O.
    // Scoped to OTHER_INSTRUMENTS — grants and commissioning rows keep their rank.
    if (eventKey === 'OTHER_INSTRUMENTS' && timelineEventYear(item) === new Date().getFullYear()) {
      return TIMELINE_WEIGHTS.CURRENT_YEAR_INSTRUMENT;
    }

    const weight = TIMELINE_WEIGHTS[eventKey];
    return weight === undefined ? TIMELINE_WEIGHTS.OTHER_INSTRUMENTS : weight;
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

  // Render card results. Each card carries what a table row carries — the old one
  // showed a file number over a line of dashes, which told a searcher nothing and
  // made Card View the view nobody used.
  const renderCardResults = () => {
    cardResults.innerHTML = '';

    searchResults.forEach((file, index) => {
      const fileNumbers = extractFileNumbers(file);
      const primary = getMappedValue(file, 'fileNumber');
      const { p1, p2, p3 } = partyFlow(file);
      const date = getMappedValue(file, 'date');
      const instrument = toProperCase(getMappedValue(file, 'transactionType'));
      const location = resultLocation(file);
      const plot = getMappedValue(file, 'plotNo');
      const size = getMappedValue(file, 'size');
      const caveated = String(file.caveat || '').toLowerCase() === 'yes';

      const card = document.createElement('div');
      card.className = 'group bg-white rounded-xl border border-gray-200 overflow-hidden hover:border-blue-300 hover:shadow-md transition cursor-pointer'
        + (caveated ? ' ring-1 ring-red-200' : '');
      card.setAttribute('data-index', index);

      const meta = [
        ['Plot', plot], ['Size', size], ['Date', date]
      ].filter(([, v]) => !isBlank(v));

      card.innerHTML = `
        <div class="flex items-center justify-between gap-2 border-b border-gray-100 bg-gray-50/70 px-4 py-2.5">
          <div class="flex items-center gap-2 min-w-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="font-mono text-sm font-bold text-gray-900 truncate">${primary}</span>
            ${sourceBadge(file)}
          </div>
          ${caveated ? '<span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">CAVEAT</span>' : ''}
        </div>

        <div class="px-4 py-3 space-y-2.5">
          <div class="text-xs font-bold uppercase tracking-wide text-blue-700">${isBlank(instrument) ? 'Transaction' : instrument}</div>

          <div class="rounded-lg bg-gray-50 px-3 py-2">
            <div class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Party 1</div>
            <div class="text-xs text-gray-800">${p1}</div>
            <div class="mt-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400">Party 2</div>
            <div class="text-xs font-semibold text-gray-900">${p2}</div>
            ${p3 ? `<div class="mt-1 text-[10px] text-gray-500">Party 3: ${p3}</div>` : ''}
          </div>

          ${isBlank(location) ? '' : `
            <div class="flex items-start gap-1.5 text-[11px] text-gray-600">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 mt-px text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
              <span>${location}</span>
            </div>`}

          ${meta.length ? `<div class="flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500">`
            + meta.map(([k, v]) => `<span><span class="font-semibold text-gray-400">${k}:</span> ${v}</span>`).join('')
            + `</div>` : ''}

          ${altFileChips(fileNumbers, primary)}
        </div>

        <div class="flex items-center justify-end border-t border-gray-100 px-4 py-2">
          <span class="text-[11px] font-bold text-blue-600 group-hover:text-blue-700">View records &rarr;</span>
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

  // Term of the Right of Occupancy in years, derived from land use:
  // Residential / Agricultural = 99 years; Commercial / Industrial = 40 years.
  // (Same rule as the print templates' termFromLandUse.)
  const lsTermYearsFromLandUse = (landUse) => {
    const lu = String(landUse || '').toUpperCase();
    if (lu.includes('RESIDENT') || lu.startsWith('RES') || lu.includes('AGRIC') || lu.startsWith('AG')) return 99;
    if (lu.includes('COMMERC') || lu.startsWith('COM') || lu.includes('INDUSTR') || lu.startsWith('IND')) return 40;
    return null;
  };

  // Term saved on the searched file's indexing record (file_indexings.term, set
  // via the Edit File Information modal), as a year count — e.g. "99 Years" → 99.
  // Overrides the land-use derived term wherever it is available.
  const lsSavedTermYears = () => {
    const m = String(window.__lsSavedTerm || '').match(/\d+/);
    return m ? parseInt(m[0], 10) : null;
  };

  // Commencement date of the R of O term. Two possible sources, checked in
  // priority order:
  //   1. Certificate of Occupancy — earliest dated row's transaction_date,
  //      from CofO_staging.
  //   2. Right of Occupancy — earliest dated row's Transaction Date (falling
  //      back to Reg Date), from the pra table — used only when no dated
  //      CofO exists.
  // Returns { date: "YYYY-MM-DD", source: 'RofO'|'CofO' }, or null when
  // neither source has a usable date.
  const lsFindCommencementSource = (transactions) => {
    const earliestTsFor = (canonType) => {
      let ts = null;
      for (const t of (transactions || [])) {
        const canon = canonicalWeightingInstrumentType(t.transaction_type || t.instrument_type || t.transactionType || '');
        if (canon !== canonType) continue;
        for (const cand of [t.transaction_date, t.reg_date, t.deeds_date]) {
          const cts = parseTimelineDateValue(cand);
          if (cts !== null) {
            if (ts === null || cts < ts) ts = cts;
            break; // Transaction Date wins; only fall through when it is absent.
          }
        }
      }
      return (ts !== null && ts <= Date.now()) ? ts : null;
    };
    const toIsoDate = (ts) => {
      const d = new Date(ts);
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };

    const cofoTs = earliestTsFor('certificate of occupancy');
    if (cofoTs !== null) return { date: toIsoDate(cofoTs), source: 'CofO' };

    const rofoTs = earliestTsFor('right of occupancy');
    if (rofoTs !== null) return { date: toIsoDate(rofoTs), source: 'RofO' };

    return null;
  };

  // "YYYY-MM-DD" → "2nd January, 2002" (matches the printed report).
  const lsFormatCommencementDate = (iso) => {
    if (!iso) return '';
    const d = new Date(iso + 'T00:00:00');
    if (isNaN(d.getTime())) return iso;
    const day = d.getDate();
    const suffix = (day % 10 === 1 && day !== 11) ? 'st'
      : (day % 10 === 2 && day !== 12) ? 'nd'
      : (day % 10 === 3 && day !== 13) ? 'rd' : 'th';
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    return day + suffix + ' ' + months[d.getMonth()] + ', ' + d.getFullYear();
  };

  // Residual Term = term minus years elapsed since the commencement year.
  // Returns e.g. "28 Years", or '' when either part is unknown. When the term
  // has fully elapsed the result is negative (e.g. "-3 Years") — no longer
  // floored at 0 — so an expired title reads as such on screen and on the report.
  const lsComputeResidualTerm = (termYears, commencementYear) => {
    const nowYear = new Date().getFullYear();
    if (!termYears || !commencementYear || commencementYear > nowYear) return '';
    return (termYears - (nowYear - commencementYear)) + ' Years';
  };

  // Set the Residual Term display and colour it red when the term is negative
  // (expired, e.g. "-34 Years") so an elapsed title stands out at a glance.
  const lsSetResidualDisplay = (el, text) => {
    const value = String(text ?? '');
    el.textContent = value;
    const isNegative = /^\s*-\s*\d/.test(value);
    el.style.color = isNegative ? '#dc2626' : '';
    el.style.fontWeight = isNegative ? '600' : '';
  };

  // Expiry date of the R of O term = commencement date + term years, as an
  // ISO yyyy-mm-dd string. Returns '' when either part is unknown/invalid.
  const lsComputeExpiryIso = (isoDate, termYears) => {
    if (!isoDate || !termYears) return '';
    const d = new Date(isoDate);
    if (isNaN(d.getTime())) return '';
    d.setFullYear(d.getFullYear() + termYears);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  };

  // Recompute the Residual Term input + File Information display from the
  // current land use and commencement date. A manual residual entry is kept
  // while it differs from the last auto value.
  //
  // A Term saved on the file indexing record (Edit File Information,
  // window.__lsSavedTerm — set per file in displayFileDetails) takes precedence
  // over the term derived from land use, so an operator-corrected term drives
  // the Residual Term on screen and on the report.
  //
  // autoHide=false is used right after the operator edits the Commencement
  // Date themselves: recomputing immediately hides the card (see below) before
  // they get a chance to click Save, so the card must stay open until the
  // value is actually persisted (or a different file is loaded).
  const lsRecomputeResidualTerm = (autoHide = true) => {
    const termYears = lsSavedTermYears()
      || lsTermYearsFromLandUse(document.getElementById('property-type-value')?.textContent || '');
    const dateInput = document.getElementById('comment-commencement_date-text');
    const isoDate = (dateInput?.value || '').trim();
    const commencementYear = isoDate ? parseInt(isoDate.slice(0, 4), 10) || null : null;
    const computed = lsComputeResidualTerm(termYears, commencementYear);

    const input = document.getElementById('residual-term-input');
    if (input) {
      if (!input.value || input.value === input.dataset.autoValue) {
        input.value = computed;
      }
      input.dataset.autoValue = computed;
    }
    const display = document.getElementById('residual-term-value');
    if (display) lsSetResidualDisplay(display, (input?.value || computed || '-'));
    const dateDisplay = document.getElementById('commencement-date-value');
    if (dateDisplay) dateDisplay.textContent = lsFormatCommencementDate(isoDate) || '-';

    // When the title's term has fully elapsed, prefill the General Comment with
    // the standard "term expired" remark carrying the actual expiry date. Kept
    // editable and only overwrites the previous auto value, so a saved/manual
    // General Comment is never clobbered.
    const generalInput = document.getElementById('comment-general-text');
    if (generalInput) {
      const expiryIso = lsComputeExpiryIso(isoDate, termYears);
      const isExpired = !!expiryIso && new Date(expiryIso) < new Date();
      const expiryText = lsFormatCommencementDate(expiryIso);
      const autoRemark = (isExpired && expiryText)
        ? `The Term expired on ${expiryText}. However, there is no any request or application for Re-Grant.`
        : '';
      if (!generalInput.value || generalInput.value === generalInput.dataset.autoValue) {
        generalInput.value = autoRemark;
      }
      generalInput.dataset.autoValue = autoRemark;
    }

    // Source badge: shows which record supplied the Commencement Date — RofO
    // (pra) or CofO (CofO_staging) when it's the untouched auto value, or
    // nothing once the operator has overridden it with their own date.
    const sourceBadge = document.getElementById('commencement-date-source-badge');
    if (sourceBadge) {
      const isAutoValue = !!isoDate && isoDate === (dateInput?.dataset.autoValue || '');
      const sourceLabel = isAutoValue ? (dateInput?.dataset.autoSource || '') : '';
      if (sourceLabel) {
        sourceBadge.textContent = sourceLabel;
        sourceBadge.className = 'source-badge ' + (sourceLabel === 'CofO' ? 'source-badge-cofo' : 'source-badge-pra');
        sourceBadge.style.display = 'inline-block';
      } else {
        sourceBadge.textContent = '';
        sourceBadge.style.display = 'none';
      }
    }

    // The editable Residual Term card is only needed when the term is unknown
    // (no auto-computed value) so the operator can fill it in manually — once
    // a value exists (auto or saved), the card is no longer actionable.
    if (autoHide) {
      const section = document.getElementById('residual-term-section');
      if (section) section.classList.toggle('hidden', !!(input?.value || '').trim());
    }
  };

  // Ground Rent Including Land Use Charge — helpers for the single card that
  // toggles between the receipted "Last Paid" record and the manual
  // "Not Paid" entry (see #ground-rent-section in file-history.blade.php).
  // The Amount/Date fields are real <input> elements toggled via the
  // `readonly` attribute (not swapped with separate display spans/inputs) so
  // Edit can never leave one field out of sync with the other.
  // Plain text, not a native <input type="date"> — that control silently
  // discards any value it doesn't consider strict "YYYY-MM-DD" and just
  // renders blank, which is exactly what was happening here. Text always
  // shows something: a nicely formatted date when the raw value parses, the
  // raw value itself (unchanged) when it doesn't, matching how this field
  // displayed before it became editable.
  const lsGroundRentDateDisplayValue = (raw) => {
    if (!raw) return '';
    const s = String(raw).trim();
    try {
      const d = new Date(s);
      if (!isNaN(d.getTime())) {
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
      }
    } catch (e) { /* fall through */ }
    return s;
  };
  const lsSetGroundRentPaidReadOnly = (readOnly) => {
    document.querySelectorAll('.ground-rent-paid-input').forEach((el) => {
      el.readOnly = readOnly;
      el.classList.toggle('border-transparent', readOnly);
      el.classList.toggle('bg-transparent', readOnly);
      el.classList.toggle('border-gray-300', !readOnly);
      el.classList.toggle('bg-white', !readOnly);
    });
    document.getElementById('edit-ground-rent-btn')?.classList.toggle('hidden', !readOnly);
    document.getElementById('save-ground-rent-paid-btn')?.classList.toggle('hidden', readOnly);
  };
  const lsSetGroundRentMode = (hasLastPaid) => {
    const section = document.getElementById('ground-rent-section');
    const title = document.getElementById('ground-rent-section-title');
    const paidFields = document.getElementById('ground-rent-paid-fields');
    const notPaidFields = document.getElementById('ground-rent-not-paid-fields');
    if (!section) return;
    section.classList.remove('bg-teal-50', 'border-teal-200', 'bg-amber-50', 'border-amber-200');
    if (hasLastPaid) {
      section.classList.add('bg-teal-50', 'border-teal-200');
      if (title) { title.textContent = 'Ground Rent Including Land Use Charge — Last Paid'; title.className = 'text-sm font-semibold text-teal-800'; }
      paidFields?.classList.remove('hidden');
      paidFields?.classList.add('flex');
      notPaidFields?.classList.add('hidden');
      document.getElementById('save-ground-rent-not-paid-btn')?.classList.add('hidden');
      lsSetGroundRentPaidReadOnly(true);
    } else {
      section.classList.add('bg-amber-50', 'border-amber-200');
      if (title) { title.textContent = 'Ground Rent Including Land Use Charge Not Paid'; title.className = 'text-sm font-semibold text-amber-800'; }
      paidFields?.classList.add('hidden');
      paidFields?.classList.remove('flex');
      notPaidFields?.classList.remove('hidden');
      document.getElementById('edit-ground-rent-btn')?.classList.add('hidden');
      document.getElementById('save-ground-rent-paid-btn')?.classList.add('hidden');
      document.getElementById('save-ground-rent-not-paid-btn')?.classList.remove('hidden');
    }
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
    
    // Update file information fields (with .0 fix and better field mapping).
    // Prefer the server-resolved "SEARCHED (LINKED)" combined display (e.g.
    // "CON-AG-2014-35 (MLKN 2455)") — same format used by the printable report and
    // Pay-Per-Search template — falling back to the plain number when none was resolved.
    const displayedValue = selectedFile._file_number_display || mlsDisplay;
    document.getElementById('file-number-value').textContent = displayedValue;

    // Label the primary file number to match what is actually displayed. The value is
    // either a lone number or "<lead> (<counterpart>)" (searched number leads). Each
    // slot is named by its type, so:
    //   - KANGIS searched:            "KANGIS FileNo (File Number):"  MLKN 3725 (CON-IND-2021-18)
    //   - Land searched w/ KANGIS:    "File Number (KANGIS FileNo):"  CON-IND-2021-18 (MLKN 3725)
    //   - No counterpart:             "File Number:"                  CON-IND-2021-18
    const fileNumberLabel = document.getElementById('file-number-label');
    if (fileNumberLabel) {
      const m = String(displayedValue).match(/^\s*(.+?)\s*(?:\(\s*([^)]*?)\s*\))?\s*$/);
      const leadPart = m ? m[1].trim() : String(displayedValue).trim();
      const parenPart = (m && m[2]) ? m[2].trim() : '';
      const slotName = (v) => {
        const t = identifyFileNumberType(v);
        if (t === 'kangis') return 'KANGIS FileNo';
        if (t === 'new_kangis') return 'New KANGIS FileNo';
        return 'File Number';
      };
      fileNumberLabel.textContent = parenPart
        ? `${slotName(leadPart)} (${slotName(parenPart)}):`
        : 'File Number:';
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
    
    // Land Use - prefer file_indexings, fallback to transaction data, then auto-detect
    // from the file-number prefix (e.g. legacy files with no indexed land use).
    const landUseValue = selectedFile._file_land_use || selectedFile.land_use || selectedFile.landUse ||
                             selectedFile.landUseType || selectedFile.title_type ||
                             selectedFile.instrument_type || selectedFile.Type ||
                             deriveLandUseFromFileNumber(selectedFile.mlsFNo || selectedFile.file_number || selectedFile.fileno || window.__lsLastSearchedFileNumber || '') || '-';
    document.getElementById('property-type-value').textContent = landUseValue;

    // Lon/Lat — from the file indexing record (matches the printed report).
    const lonLatEl = document.getElementById('lon-lat-value');
    if (lonLatEl) lonLatEl.textContent = selectedFile._file_lon_lat || '-';

    // Ground Rent Including Land Use Charge — single card, toggled between
    // "Last Paid" (from file_indexings.ground_rent_amount /
    // ground_rent_receipt_date, editable in place) and the manual "Not Paid"
    // entry, based on whether a receipted payment is on file. A saved edit
    // overrides the auto value via the 'ground_rent_paid' comment type
    // (applied in loadComments()).
    const groundRentSection = document.getElementById('ground-rent-section');
    const groundRentAmount = selectedFile._file_ground_rent_amount || '';
    const groundRentDate = selectedFile._file_ground_rent_date || '';
    if (groundRentSection) {
      groundRentSection.dataset.autoAmount = groundRentAmount;
      groundRentSection.dataset.autoDate = groundRentDate;
      delete groundRentSection.dataset.overrideAmount;
      delete groundRentSection.dataset.overrideDate;
      const hasLastPaid = !!(groundRentAmount && groundRentDate);
      lsSetGroundRentMode(hasLastPaid);
      if (hasLastPaid) {
        const amtEl = document.getElementById('file-ground-rent-amount');
        const dateEl = document.getElementById('file-ground-rent-date');
        if (amtEl) amtEl.value = Number(groundRentAmount).toFixed(2);
        if (dateEl) dateEl.value = lsGroundRentDateDisplayValue(groundRentDate);
      }
    }

    // Term of the R of O — the value saved on the indexing record (Edit File
    // Information) when there is one, otherwise derived from land use (99
    // res/agric, 40 comm/ind). Set before lsRecomputeResidualTerm below, which
    // reads it back through lsSavedTermYears() for the Residual Term.
    // Reset per file so a previous file's saved term never leaks onto the next one.
    window.__lsSavedTerm = (selectedFile._file_term || '').toString().trim();
    const termYears = lsSavedTermYears() || lsTermYearsFromLandUse(landUseValue);
    const termEl = document.getElementById('term-value');
    if (termEl) termEl.textContent = window.__lsSavedTerm || (termYears ? termYears + ' Years' : '-');

    // Commencement Date — auto-filled from the R of O grant's Transaction Date,
    // falling back to the CofO's transaction_date when no dated R of O exists
    // (see lsFindCommencementSource). A user-picked or saved date is kept
    // while it differs from the last auto value. The Residual Term (editable
    // below the Timeline) derives from it and prints on the report's Residual
    // Term field. The source badge next to the File Information value is
    // driven by dataset.autoSource in lsRecomputeResidualTerm.
    const commencementInput = document.getElementById('comment-commencement_date-text');
    const commencementSource = lsFindCommencementSource(getRelatedTransactions(selectedFile) || []);
    const grantDate = commencementSource?.date || '';
    if (commencementInput) {
      if (!commencementInput.value || commencementInput.value === commencementInput.dataset.autoValue) {
        commencementInput.value = grantDate;
      }
      commencementInput.dataset.autoValue = grantDate;
      commencementInput.dataset.autoSource = commencementSource?.source || '';
    }
    lsRecomputeResidualTerm();

    // Last transaction — the most recent real transaction on the MAIN (searched)
    // file itself. It must NOT reflect events that belong to parcel-update-derived
    // files (subdivision/merger children), nor the parcel-update events themselves
    // (Subdivision, Merger, Change of Purpose…), nor the synthetic lifecycle rows
    // (File Commissioning/Decommissioning, Temporary File).
    let lastTransactionValue = '-';
    try {
      // Use the SAME deduped/preferred set the timeline table displays, so the
      // status reflects what the user actually sees. Duplicate instruments attributed
      // to a related file (e.g. a shared Deed of Assignment) are therefore excluded
      // from the main file's dealings, exactly as in the table.
      const rawForLast = getRelatedTransactions(selectedFile) || [];
      let relatedForLast = rawForLast;
      try {
        const dd = dedupeTransactionsForTimelineAndReport(rawForLast);
        if (dd && Array.isArray(dd.preferred)) relatedForLast = dd.preferred;
      } catch (_) { /* fall back to raw set */ }

      const typeOf = (item) => item?.transaction_type || item?.instrument_type || item?.transactionType || '';
      const isParcelUpdateType = (t) => /subdivision|merger|consolidat|change of purpose|reconstitut|resettlement|extension|regrant/i.test(String(t || ''));
      // Certificate / administrative events are not "dealings": a Certificate of
      // Occupancy is the title document and a KANGIS Recertification is an
      // administrative re-issuance — neither is the file's last transaction.
      const isCertOrAdminType = (t) => /certificate of occupanc|recertification/i.test(String(t || ''));
      const isLifecycleSyntheticType = (t) => {
        const s = String(t || '').toLowerCase();
        return s.includes('commissioning') || s.includes('decommissioning') || s === 'temporary file';
      };

      // Resolve the main file's lifecycle key (map a searched KANGIS alias to its land file).
      const rawMain = userSelectedFileNumber || window._currentFileNumber || (mlsDisplay !== '-' ? mlsDisplay : '');
      let mainKey = normalizeLifecycleFileNo(baseFileNo(rawMain));
      const aliasMap = buildKangisAliasMap(relatedForLast);
      if (mainKey && aliasMap[mainKey]) mainKey = aliasMap[mainKey];
      const ownerKey = (item) => {
        let k = normalizeLifecycleFileNo(item?.lifecycle_file_no || extractLifecycleFileNo(item) || '');
        if (k && aliasMap[k]) k = aliasMap[k];
        return k;
      };

      // Only real dealings on the main file are eligible.
      const eligible = relatedForLast.filter((item) => {
        if (mainKey && ownerKey(item) !== mainKey) return false;
        const t = typeOf(item);
        return t && !isParcelUpdateType(t) && !isCertOrAdminType(t) && !isLifecycleSyntheticType(t);
      });

      // Order the eligible dealings with the SAME chronological sort the timeline table
      // uses, then take the LAST one — so the status matches what the user sees. A plain
      // date-max fails here: an instrument with no transaction date (e.g. a Deed of
      // Mortgage recorded only by registration particulars) would be skipped, yet the
      // timeline parks such undated rows at the very end as floaters, making them the
      // most recent dealing. Sorting the same way keeps the two in agreement.
      let orderedEligible = eligible;
      try { orderedEligible = sortTimelineChronologically(eligible.slice()); }
      catch (_) { /* keep filter order on failure */ }
      const latestItem = orderedEligible.length ? orderedEligible[orderedEligible.length - 1] : null;

      if (latestItem) {
        lastTransactionValue = typeOf(latestItem) || lastTransactionValue;
      } else {
        const own = selectedFile.transaction_type || selectedFile.instrument_type ||
                    selectedFile.application_status || selectedFile.deeds_status ||
                    selectedFile.planning_recommendation_status || '-';
        lastTransactionValue = (isParcelUpdateType(own) || isCertOrAdminType(own) || isLifecycleSyntheticType(own)) ? '-' : own;
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

  // Keep the File Information panel's Residual Term in sync while the user
  // types in the Residual Term editor below the Timeline.
  document.addEventListener('input', (e) => {
    if (e.target && e.target.id === 'residual-term-input') {
      const disp = document.getElementById('residual-term-value');
      if (disp) lsSetResidualDisplay(disp, e.target.value.trim() || '-');
    }
  });

  // Recompute the Residual Term whenever the user picks a different
  // Commencement Date.
  document.getElementById('comment-commencement_date-text')?.addEventListener('change', () => {
    // The date changed, so the previous auto residual is stale — recompute
    // from scratch unless the user typed a custom residual. Keep the card
    // open (autoHide=false) so the operator can still see and click Save —
    // otherwise the card disappears the instant a residual auto-computes.
    const input = document.getElementById('residual-term-input');
    if (input && input.value === input.dataset.autoValue) input.value = '';
    lsRecomputeResidualTerm(false);
  });

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
        const _ll = data.file_lon_lat || null;
        const _rf = data.file_related_fileno || null;
        const _ix = data.file_index_number || null;
        const _sz = data.file_size || null;
        const _gra = data.file_ground_rent_amount || null;
        const _grd = data.file_ground_rent_date || null;
        const _trm = data.file_term || null;
        const _tf = data.file_temp_number || null;
        const _cd = data.file_commissioning_date || null;
        const _cn = data.file_commissioned_number || null;
        const _ch = data.file_commissioning_holder || null;
        const _cs = data.file_commissioning_source || null;
        const _fnd = data.file_number_display || null;
        window._lsFileTempNumber = _tf;
        window._lsLineage = data.lineage || null;
        window._lsLifecycleMeta = data.lifecycle_meta || {};
        window._lsLifecycleOrder = Array.isArray(data.lifecycle_order) ? data.lifecycle_order : [];
        const _isIndexed = (data.is_indexed === true);
        searchResults.forEach(function (r) {
          r._file_is_indexed = _isIndexed;
          if (_t)  r._file_title          = _t;
          if (_d)  r._file_district       = _d;
          if (_l)  r._file_lga            = _l;
          if (_lu) r._file_land_use       = _lu;
          if (_p)  r._file_plot_number    = _p;
          if (_tp) r._file_tp_no          = _tp;
          if (_ll) r._file_lon_lat        = _ll;
          if (_rf) r._file_related_fileno = _rf;
          if (_ix) r._file_index_number   = _ix;
          if (_sz) r._file_size           = _sz;
          if (_gra) r._file_ground_rent_amount = _gra;
          if (_grd) r._file_ground_rent_date   = _grd;
          if (_trm) r._file_term               = _trm;
          if (_tf) r._file_temp_number    = _tf;
          if (_cd) r._file_commissioning_date = _cd;
          if (_cn) r._file_commissioned_number = _cn;
          if (_ch) r._file_commissioning_holder = _ch;
          if (_cs) r._file_commissioning_source = _cs;
          if (_fnd) r._file_number_display = _fnd;
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

    // Load editable comments and show/hide sections based on data.
    // Use the deduped/preferred set (not _all…) so the encumbrance/mortgage
    // remark ignores Excluded / Duplicate records — matching what the timeline
    // and printed report already operate on.
    loadComments(window._currentFileNumber);
    showCommentSections(window._preferredRelatedTransactions || window._allRelatedTransactions || []);

    // Party 3 is always visible, Party 4 removed
  };
  
  // Switch between tabs in the file details view
  const updateAddRecordButtonVisibility = (tabName) => {
    // Both menu options (New / Existing) open the capture card, so both carry
    // data-target; the trigger button itself only opens the menu.
    const target = tabName === 'cofo' ? 'cofo_staging' : 'pra';
    const options = document.querySelectorAll('.add-record-option');

    if (options.length > 0) {
      options.forEach((option) => option.setAttribute('data-target', target));
      return;
    }

    // Fallback for any view still rendering the pre-menu single button.
    const btn = document.getElementById('add-record-smart-btn');
    if (btn) btn.setAttribute('data-target', target);
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
      identifyFileNumberType(searchQuery) === 'mls' ||     // MLPP File No pattern
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
    
    fileNumbersDisplay += `  |  MLPP File No: ${fileNumbers.mls}  |  KANGIS File No: ${fileNumbers.kangis}  |  New KANGIS: ${fileNumbers.new_kangis}`;
    
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
      qrCodeImg.src = `/qr/svg?size=150&data=${encodeURIComponent(fileInfo)}`;
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

  // Compact encoder for the `timeline_order` / `excluded_keys` print params.
  //
  // Written out plainly ("file_history_staging:123,pra:456,…") a subdivision mother with
  // 100+ children produced a few hundred keys and pushed the print URL past Apache's
  // LimitRequestLine (8190 bytes) — the browser answered "Request-URI Too Long" and the
  // report never opened. This names each table once and refers to it by index:
  //
  //     v2:<table>~<table>|<tableIdx>-<id>,<tableIdx>-<id>,…
  //
  // Rows with no database id keep an empty id ("0-"), so the server decodes exactly the
  // same "<table>:<id>" keys as before — only the wire format is shorter.
  // Decoder: LegalSearchService::decodeRowKeySpec().
  const encodeRowKeySpec = (list, { requireId = false } = {}) => {
    const tables = [];
    const tableIdx = new Map();
    const seq = [];
    (list || []).forEach(t => {
      const tbl = (typeof timelineSourceToDbTable === 'function')
        ? timelineSourceToDbTable(t.source_table || '')
        : (t.source_table || '');
      const id = (t.id != null && t.id !== '') ? String(t.id) : '';
      if (requireId && id === '') return;
      if (!tableIdx.has(tbl)) {
        tableIdx.set(tbl, tables.length);
        tables.push(tbl);
      }
      seq.push(tableIdx.get(tbl) + '-' + id);
    });
    return seq.length ? `v2:${tables.join('~')}|${seq.join(',')}` : '';
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

  // OP / TOT / RofO carry their operative date in transaction_date; every other event —
  // C of O, recertifications, other instruments — is keyed off its registration date.
  // Keyed off the event class rather than the weight: the weight scale has no stable
  // "default" number to test against, and a null (floating) weight is not comparable.
  const getTransactionTimestamp = (item) => {
    // Registration date FIRST, for every event without exception, falling back to the
    // transaction date only when no registration date is recorded.
    //
    // Occupancy Permits, Transfers of Title (OP) and Rights of Occupancy used to be
    // exempted and sorted on transaction_date instead, which put them out of sequence
    // against neighbours sorted on their reg date. The register is what dates a dealing,
    // so one rule now covers them all.
    //
    // deeds_date sits second because pra and CofO_staging have no literal reg_date column
    // and carry their registration date there — without it those two sources would fall
    // straight through to transaction_date and never sort on a registration date at all.
    const candidates = [
      item.reg_date,
      item.deeds_date,
      item.transaction_date,
      item.cofo_date,
      item.certificateDate,
      item.approval_date,
      item.date,
    ];

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
    if (raw.includes('occupancy permit') || raw === 'op') return 'occupancy permit';
    if (raw.includes('transfer of title') || raw.includes('tot')) return 'transfer of title';
    if (raw.includes('file commissioning')) return 'file commissioning';
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


  // The Timeline Weighting Method (spec §3). Two phases, because weighted and floating
  // events are ranked on different keys and must not be compared to each other:
  //
  //   1. Weighted events (weight !== null) sort by weight DESC, then timestamp ASC, then id.
  //   2. Floating events (weight === null — the C of O, parcel updates, decommissionings, DCIV rows)
  //      have no rank of their own. Each is injected after the last weighted event that is
  //      no later than it, so it lands chronologically without disturbing the hierarchy.
  //
  // A single comparator cannot express this: floating events are not ordered relative to
  // weighted ones by weight at all, only by date, so mixing them in one sort makes the
  // comparator intransitive and the result depends on input order.
  const compareWeightedEvents = (a, b) => {
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
  };

  // Only a weighted event that actually carries a date can anchor a floater in time.
  const isDatedWeighted = (item) =>
    recordPriorityWeight(item) !== null && getTransactionTimestamp(item) !== null;

  const sortTimelineChronologically = (transactions) => {
    const weighted = [];
    const floating = [];
    for (const t of transactions) {
      (recordPriorityWeight(t) === null ? floating : weighted).push(t);
    }

    weighted.sort(compareWeightedEvents);
    if (!floating.length) return weighted;

    // Undated floaters keep their arrival order at the very end; dated ones sort among
    // themselves so several sharing a timestamp stay stable.
    floating.sort((a, b) => {
      const ta = getTransactionTimestamp(a);
      const tb = getTransactionTimestamp(b);
      if (ta === null && tb === null) return (Number(a.id) || 0) - (Number(b.id) || 0);
      if (ta === null) return 1;
      if (tb === null) return -1;
      if (ta !== tb) return ta - tb;
      return (Number(a.id) || 0) - (Number(b.id) || 0);
    });

    const result = [...weighted];
    for (const floater of floating) {
      const ts = getTransactionTimestamp(floater);
      if (ts === null) { result.push(floater); continue; }

      // Anchor = the LAST dated weighted event on or before this floater. Scanned forward
      // over the originally-weighted rows only, so floaters inserted on an earlier pass
      // never act as anchors themselves. The weighted list is ordered by weight, not date,
      // so "last" is positional: a floater settles below the deepest weighted event it
      // post-dates, which keeps the hierarchy above it intact.
      let insertAt = 0;
      for (let i = 0; i < result.length; i++) {
        if (!isDatedWeighted(result[i])) continue;
        if (getTransactionTimestamp(result[i]) <= ts) insertAt = i + 1;
      }
      // Advance to just before the next dated weighted event. This steps over two things:
      // floaters already parked on this anchor (`floating` is sorted ascending, so this one
      // belongs after them — otherwise a Decommissioning would precede the Subdivision that
      // caused it), and UNDATED weighted rows, which have no position in time and must stay
      // attached to their weight group rather than be split off by a dated floater.
      while (insertAt < result.length && !isDatedWeighted(result[insertAt])) {
        insertAt++;
      }
      result.splice(insertAt, 0, floater);
    }
    return result;
  };

  // Lifecycle-transaction rule mirrored from LegalSearchService::placeKangisRecertBeforeCofo().
  // Within one lifecycle's transaction phase, each KANGIS Recertification sits immediately
  // above its matching KANGIS C of O, and duplicate recerts for the same KANGIS key are
  // suppressed.
  const placeKangisRecertBeforeCofo = (rows) => {
    const typeOf = (r) => String(r?.transaction_type || r?.instrument_type || '').toLowerCase();
    const isCofo = (r) => typeOf(r).includes('certificate of occupanc');
    const isRecert = (r) => typeOf(r).includes('recertification');

    const cofoKeys = new Set();
    rows.forEach((r) => {
      if (!isCofo(r)) return;
      const k = extractKangisLifecycleKey(r);
      if (k) cofoKeys.add(k);
    });

    const seenRecert = new Set();
    const pendingRecertByKey = new Map();
    const pendingOrder = [];
    const out = [];

    rows.forEach((r) => {
      if (isRecert(r)) {
        const key = extractKangisLifecycleKey(r);
        const own = normalizeLifecycleFileNo(extractLifecycleFileNo(r) || '');
        const dedupeKey = key || `ROW:${own}`;
        if (seenRecert.has(dedupeKey)) return;
        seenRecert.add(dedupeKey);

        if (key && cofoKeys.has(key)) {
          pendingRecertByKey.set(key, r);
          pendingOrder.push(key);
          return;
        }

        out.push(r);
        return;
      }

      if (isCofo(r)) {
        const key = extractKangisLifecycleKey(r);
        if (key && pendingRecertByKey.has(key)) {
          out.push(pendingRecertByKey.get(key));
          pendingRecertByKey.delete(key);
        }
      }

      out.push(r);
    });

    pendingOrder.forEach((key) => {
      if (pendingRecertByKey.has(key)) {
        out.push(pendingRecertByKey.get(key));
        pendingRecertByKey.delete(key);
      }
    });

    return placeUnpairedRecertBelowCofo(orderRecertGenerations(out));
  };

  // A KANGIS Recertification with no KANGIS C of O to sit above belongs BELOW the file's
  // ordinary C of O, not above it.
  //
  // placeKangisRecertBeforeCofo() pulls a recert above the C of O it recertifies — right
  // when that C of O is itself a KANGIS one, because the pair is one KANGIS exercise. When
  // the file's C of O is an ordinary Ministry C of O the recert did NOT produce it: the
  // recertification came afterwards, against a title that already existed, so printing it
  // above reverses the real sequence.
  //
  // Mirrors LegalSearchService::placeUnpairedRecertBelowCofo(). Keep the two in step.
  const placeUnpairedRecertBelowCofo = (rows) => {
    const typeOf = (r) => String(r?.transaction_type || r?.instrument_type || '').toLowerCase();
    const isCofo = (r) => typeOf(r).includes('certificate of occupanc');
    const isRecert = (r) => typeOf(r).includes('recertification');

    const anchorIndex = rows.findIndex((r) => isCofo(r) && !extractKangisLifecycleKey(r));
    if (anchorIndex === -1) return rows;

    const cofoKeys = new Set();
    rows.forEach((r) => {
      if (!isCofo(r)) return;
      const k = extractKangisLifecycleKey(r);
      if (k) cofoKeys.add(k);
    });

    const moving = [];
    const kept = [];
    rows.forEach((r, i) => {
      const key = isRecert(r) ? extractKangisLifecycleKey(r) : '';
      const unpaired = isRecert(r) && (!key || !cofoKeys.has(key));
      if (unpaired && i !== anchorIndex) { moving.push(r); return; }
      kept.push(r);
    });

    if (!moving.length) return rows;

    const out = [];
    let placed = false;
    kept.forEach((r) => {
      out.push(r);
      if (!placed && isCofo(r) && !extractKangisLifecycleKey(r)) {
        moving.forEach((m) => out.push(m));
        placed = true;
      }
    });
    if (!placed) moving.forEach((m) => out.push(m));

    return out;
  };

  // Lifecycle-transaction rule mirrored from LegalSearchService::orderRecertGenerations().
  // Recertification exercises print in generation order — First KANGIS Recertification (old
  // KNML/MLKN/KNGP, 2014–2024) before Second KANGIS Recertification (new KN, 2025–present) —
  // and each generation's C of O stays with its own recertification.
  //
  // The weight/date sort alone gets this wrong: both recerts share the same weight, and a Second
  // Recertification usually has no C of O of its own, so placeKangisRecertBeforeCofo() leaves
  // it wherever it landed — which can be ABOVE the First Recertification / C of O pair.
  const orderRecertGenerations = (rows) => {
    const typeOf = (r) => String(r?.transaction_type || r?.instrument_type || '').toLowerCase();
    const isCofo = (r) => typeOf(r).includes('certificate of occupanc');
    const isRecert = (r) => typeOf(r).includes('recertification');

    // 0 = not a generational KANGIS recert (e.g. the Ministry recertification, which is
    // hoisted under File Commissioning by Rule 4 and must not be reordered here).
    const genOf = (r) => {
      if (!isRecert(r)) return 0;
      const t = typeOf(r);
      if (t.includes('second')) return 2;
      return t.includes('first') ? 1 : 0;
    };

    // A KANGIS C of O inherits the generation of the recertification sharing its file key,
    // so it travels with that generation instead of being stranded.
    const genByKey = new Map();
    rows.forEach((r) => {
      const g = genOf(r);
      if (!g) return;
      const key = extractKangisLifecycleKey(r);
      if (key) genByKey.set(key, g);
    });

    const gens = rows.map((r) => {
      const g = genOf(r);
      if (g) return g;
      if (isCofo(r)) {
        const key = extractKangisLifecycleKey(r);
        if (key && genByKey.has(key)) return genByKey.get(key);
      }
      return 0;
    });

    // Each recertification plus the C of O rows already parked directly beneath it forms one
    // indivisible block. Blocks are reordered among themselves; every other row keeps its
    // position, so nothing escapes the lifecycle group.
    const blocks = [];
    const slots = [];
    for (let i = 0; i < rows.length;) {
      if (!gens[i]) { i++; continue; }
      const gen = gens[i];
      const block = [rows[i]];
      slots.push(i);
      let j = i + 1;
      while (j < rows.length && gens[j] === gen && isCofo(rows[j])) {
        block.push(rows[j]);
        slots.push(j);
        j++;
      }
      blocks.push({ gen, rows: block });
      i = j;
    }

    if (blocks.length < 2) return rows;

    const order = blocks.map((_, i) => i);
    order.sort((a, b) => (blocks[a].gen - blocks[b].gen) || (a - b));

    const flat = [];
    order.forEach((b) => blocks[b].rows.forEach((r) => flat.push(r)));

    const out2 = rows.slice();
    slots.forEach((idx, k) => { out2[idx] = flat[k]; });
    return out2;
  };

  // Business rule: a Mortgage (Deed of Mortgage / Tripartite Mortgage) is later discharged by a
  // Surrender & Release involving the SAME lender bank. The mortgage must render directly ABOVE
  // its matching Surrender & Release row regardless of transaction date — the discharge often
  // carries a later or missing date that would otherwise strand the mortgage at the end of the
  // chronologically-sorted list (e.g. mortgage below its own surrender).
  const placeMortgageAboveSurrender = (rows) => {
    const typeOf = (r) => String(r?.transaction_type || r?.instrument_type || '').toLowerCase();
    const isMortgage = (r) => typeOf(r).includes('mortgage');
    const isSurrender = (r) => typeOf(r).includes('surrender');

    if (!rows.some(isMortgage) || !rows.some(isSurrender)) return rows;

    // Normalize a party name for loose bank matching: lowercase, strip punctuation and common
    // company suffixes so "Bank Of The North Ltd" ≈ "Bank Of The North" and "Ja'Iz Bank Plc" ≈
    // "Jaiz Bank Plc".
    const normParty = (v) => String(v || '')
      .toLowerCase()
      .replace(/[^a-z0-9 ]/g, '')
      .replace(/\b(ltd|limited|plc|nig|nigeria|company|co)\b/g, '')
      .replace(/\s+/g, ' ')
      .trim();
    const partiesOf = (r) => [r?.party_1, r?.party_2, r?.party_3, r?.party_4]
      .map(normParty).filter(p => p.length > 3);
    // The discharging lender is a bank-like party — used to pair a mortgage with its surrender.
    const isBankLike = (p) => /bank|mortgage|micro ?finance|building society/.test(p);
    const sharesLender = (a, b) => {
      const pa = partiesOf(a), pb = partiesOf(b);
      for (const x of pa) for (const y of pb) {
        if (!isBankLike(x) && !isBankLike(y)) continue;
        if (x === y || (x.length > 4 && y.length > 4 && (x.includes(y) || y.includes(x)))) return true;
      }
      return false;
    };

    // Move each mortgage row to sit directly above the surrender it matches. Prefer a surrender
    // that shares the lender bank; otherwise fall back to the first unclaimed surrender.
    let result = rows.slice();
    const usedSurrender = new Set();
    result.filter(isMortgage).forEach((m) => {
      let target = result.find(r => isSurrender(r) && !usedSurrender.has(r) && sharesLender(m, r))
        || result.find(r => isSurrender(r) && !usedSurrender.has(r));
      if (!target || target === m) return;
      usedSurrender.add(target);
      result = result.filter(r => r !== m);
      result.splice(result.indexOf(target), 0, m);
    });
    return result;
  };

  const sourceBadgeClass = (label) => {
    const map = { 'PRA': 'source-badge-pra', 'File History': 'source-badge-fh', 'Deed Registration': 'source-badge-deed', 'CofO': 'source-badge-cofo', 'Related Fileno': 'source-badge-related' };
    return map[label] || '';
  };

  const sourceRowTintClass = (label) => {
    const map = { 'PRA': 'row-tint-pra', 'File History': 'row-tint-fh', 'Deed Registration': 'row-tint-deed', 'CofO': 'row-tint-cofo', 'Related Fileno': 'row-tint-related' };
    return map[label] || '';
  };

  // Legacy files digitized into KLAES (no genuine commissioning date on record)
  // still encode the year they were originally commissioned in the file number
  // itself, e.g. "RES-2001-3874" → "2001", "CON-RES-1993-387" → "1993". Matches a
  // dash-delimited 4-digit segment starting with 19 or 20 so serial numbers of the
  // same length (e.g. "3874") aren't mistaken for a year.
  const extractYearFromFileNumber = (fileNo) => {
    const parts = String(fileNo || '').split('-');
    for (const part of parts) {
      const trimmed = part.trim();
      if (/^(?:19|20)\d{2}$/.test(trimmed)) return trimmed;
    }
    return null;
  };

  // Party 2 of a commissioning row is the allottee the file was opened for, read off the
  // instrument that opened it. WHICH party carries that name differs per instrument — the
  // State grants TO the allottee (Party 2), but the allottee assigns the land AWAY (Party 1)
  // — so the list pairs each type with its party number. The file title names the CURRENT
  // owner, the wrong name on a commissioning row once the land has changed hands, so it
  // stays only as the last resort. Returns null when the file has none of these instruments
  // — the caller then keeps the name it already had.
  // Read straight from LegalSearchService::COMMISSIONING_HOLDER_SOURCES so the two cannot drift.
  const COMMISSIONING_HOLDER_SOURCES = @json(\App\Services\LegalSearchService::COMMISSIONING_HOLDER_SOURCES);

  // File numbers from this year on are exempt: the rule repairs LEGACY files whose title
  // drifted to a later owner over decades. A KLAES-era file already carries the right holder,
  // and there the rule misfires — a file created by a Transfer of Title would take Party 2
  // from the OP that preceded it, i.e. the PREVIOUS owner. Files with no year in their number
  // are legacy by definition, so the rule applies to them.
  // Mirrors LegalSearchService::GRANT_HOLDER_EXEMPT_FROM_YEAR.
  const GRANT_HOLDER_EXEMPT_FROM_YEAR = 2026;
  const grantHolderRuleApplies = (fileNo) => {
    const year = extractYearFromFileNumber(fileNo);
    return year === null || Number(year) < GRANT_HOLDER_EXEMPT_FROM_YEAR;
  };

  const resolveHolderFromGrantEvent = (fileNo) => {
    if (!grantHolderRuleApplies(fileNo)) return null;
    const rows = window._preferredRelatedTransactions || window._allRelatedTransactions || [];
    const norm = (v) => String(v || '').toUpperCase().replace(/[\s\-_=\/]+/g, '');
    const target = norm(fileNo);
    for (const source of COMMISSIONING_HOLDER_SOURCES) {
      const partyKey = 'party_' + source.party;

      // Unlike the PHP twin, this row set is the DEDUPED list, not the date-sorted timeline,
      // so the earliest match has to be picked explicitly: the Deed of Assignment that names
      // the original allottee is the FIRST one, not whichever the dedup happened to emit.
      const matches = [];
      for (const r of rows) {
        if (canonicalWeightingInstrumentType(getMappedValue(r, 'transactionType')) !== source.type) continue;
        const holder = String((r && r[partyKey]) || '').trim();
        if (!holder || holder === '-') continue;
        // Rows on this very file win over rows on linked files, so a KANGIS alias or a
        // subdivision sibling cannot lend its party to this file's commissioning row.
        const own = target && norm(r.lifecycle_file_no || r.mlsFNo || r.fileno || r.file_number) === target;
        matches.push({ holder, own, ts: getTransactionTimestamp(r) });
      }
      if (!matches.length) continue;

      matches.sort((a, b) => {
        if (a.own !== b.own) return a.own ? -1 : 1;
        if (a.ts === null && b.ts === null) return 0;
        if (a.ts === null) return 1;
        if (b.ts === null) return -1;
        return a.ts - b.ts;
      });
      return matches[0].holder;
    }
    return null;
  };

  // Build the synthetic "File Commissioning" timeline record. It is always the
  // first row (weight 12). Its Transaction Date is the file's commissioning date
  // (resolved server-side; '-' when the file was not commissioned within KLAES),
  // and its Reg Particulars are 0/0/0.
  const buildCommissioningTimelineRow = () => {
    // A file that has not yet been indexed (no file_indexings row) gets no synthetic
    // "File Commissioning" row — the commissioning event is only surfaced once the
    // file has actually been indexed into KLAES.
    if (selectedFile && selectedFile._file_is_indexed === false) return null;
    const commDate = (selectedFile && selectedFile._file_commissioning_date)
      ? selectedFile._file_commissioning_date : '-';
    // The File Commissioning row represents the permanent/main LAND file, so it must
    // carry the land/MLS file number — never the KANGIS alias (e.g. "MLKN 3725") the
    // user may have searched by. Prefer the land number that leads the "SEARCHED
    // (LINKED)" display (e.g. "CON-IND-2021-18 (MLKN 3725)" → "CON-IND-2021-18"), then
    // the indexed land number, then whatever was searched.
    const _notKangisNo = (v) => {
      const t = identifyFileNumberType(v);
      return v && v !== '-' && t !== 'kangis' && t !== 'new_kangis';
    };
    const _displayLead = String((selectedFile && selectedFile._file_number_display) || '').split('(')[0].trim();
    const _indexedNo = String((selectedFile && selectedFile._file_index_number) || '').trim();
    const _searchedNo = (userSelectedFileNumber && String(userSelectedFileNumber).trim())
      || window._currentFileNumber
      || (selectedFile && (selectedFile.mlsFNo || selectedFile.fileno || selectedFile.fileNo))
      || '-';
    const rawFileNo = _notKangisNo(_displayLead) ? _displayLead
      : (_notKangisNo(_indexedNo) ? _indexedNo : _searchedNo);
    // The File Commissioning row represents the permanent/main file, so it always
    // carries the main file number. When the searched file is itself a temporary
    // "(T)" number, strip the "(T)" here — the temporary number is shown on its own
    // "Temporary File" row directly below.
    const fileNo = String(rawFileNo).replace(/\s*\(\s*T\s*\)\s*$/i, '').trim() || rawFileNo;
    // The File Commissioning row represents the permanent/main LAND file. When no
    // land/MLS number could be resolved and the row would fall back to a KANGIS alias
    // (e.g. "MLKN 1934"), suppress the row entirely — a KANGIS file is never shown as
    // a File Commissioning event.
    if (!_notKangisNo(fileNo)) return null;
    // A SYSTEM temporary number ("TEMP-xxx") is an internal placeholder, never a commissioning event.
    if (isSystemTempFileNo(fileNo)) return null;
    // Sectional Titling: a mother ST file has TWO commissioning events that must BOTH
    // show — the Land File Commissioning (this generic row, on the mls_fileno/land
    // number) and the ST File Commissioning (a backend "ST File Commissioning" row on
    // the np_fileno, tagged into this same land group). When the backend emitted an ST
    // primary commissioning row for this land file, relabel this generic row to
    // "Land File Commissioning" so the two events read distinctly (no longer suppressed).
    const _stCommRows = window._preferredRelatedTransactions || window._allRelatedTransactions || [];
    const _normStFn = (s) => String(s || '').toUpperCase().replace(/[\s\-_=\/]+/g, '');
    const _fileNoNormST = _normStFn(fileNo);
    const _hasStCommissioning = _stCommRows.some(r =>
      String((r && r.source_table) || '') === 'ST File Commissioning' &&
      String((r && r.transaction_type) || '').indexOf('Fragmentation') === -1 &&
      _normStFn((r && (r.lifecycle_file_no || r.parent_file_number)) || '') === _fileNoNormST);
    const _commissioningLabel = _hasStCommissioning ? 'Land File Commissioning' : commissioningLabelFor(fileNo);
    // The commissioning date belongs to whichever file number was actually
    // commissioned (fileNumber.mlsfNo, resolved server-side). It carries a "(T)"
    // suffix when the temporary file was the commissioned one. Show the date on
    // this permanent row ONLY when the commissioned number is NOT a "(T)" number
    // (or when the server reported no commissioned number — legacy responses).
    const commissionedNo = String((selectedFile && selectedFile._file_commissioned_number) || '').trim();
    const commissionedIsTemp = /\(\s*T\s*\)\s*$/i.test(commissionedNo);
    let mainRowDate = (commDate && commDate !== '-' && !commissionedIsTemp)
      ? commDate : '-';
    // Legacy file with no genuine KLAES commissioning date — fall back to the
    // year embedded in the file number itself rather than leaving it blank.
    if (mainRowDate === '-') {
      const yearFromFileNo = extractYearFromFileNumber(fileNo);
      if (yearFromFileNo) mainRowDate = yearFromFileNo;
    }
    // Party 1 is the commissioning authority; Party 2 is the FILE TITLE, and nothing
    // else — see the same decision in LegalSearchService::buildPrintReport(). It was
    // read off the grant that opened the file (RofO/OP grantee, ToT/Deed assignor),
    // which showed LAMASH on RES-2024-2184 while the file is titled SAYYIDI UBA WARU.
    const ownerName = (selectedFile && (selectedFile._file_title || selectedFile.file_title)) || '-';
    return {
      _is_commissioning: true,
      id: 'commissioning',
      source_table: 'File Commissioning',
      fileno: fileNo,
      file_number: fileNo,
      mlsFNo: fileNo,
      lifecycle_file_no: fileNo,
      transaction_type: _commissioningLabel,
      instrument_type: _commissioningLabel,
      party_1: 'Kano State Ministry of Land and Physical Planning', party_2: ownerName, party_3: '-', party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: mainRowDate,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: (selectedFile && (selectedFile.prop_id || selectedFile.propId)) || '',
    };
  };

  // A transaction row that creates/retires files (subdivision, merger, change of
  // purpose, extension, separation, generic parcel update). Used to anchor where
  // lineage commissioning rows sit in the timeline.
  const isParcelUpdateRow = (item) => {
    const t = String(item?.transaction_type || item?.instrument_type || '').toLowerCase();
    if (item?._is_commissioning || item?._is_temporary_file) return false;
    return /subdivision|merger|change of purpose|plot extension|separation|parcel update/.test(t);
  };

  // Build a "File Commissioning" row for a successor "child" lineage file.
  // Mirrors buildCommissioningTimelineRow but for a file other than the searched
  // one: date comes from the server-resolved lineage commissioning info, falling
  // back to the year embedded in the number.
  const buildLineageCommissioningRow = (fileNo, commissioningDate, fileTitle, idSuffix) => {
    const no = String(fileNo || '').replace(/\s*\(\s*T\s*\)\s*$/i, '').trim();
    if (!no) return null;
    // A KANGIS-format file (KNML/MLKN/KNGP/KN…) is an alias of a land file, never its own
    // lifecycle — it shows no File Commissioning row (only its Recertification appears).
    const _kt = identifyFileNumberType(no);
    if (_kt === 'kangis' || _kt === 'new_kangis') return null;
    if (isSystemTempFileNo(no)) return null;
    let date = (commissioningDate && commissioningDate !== '-') ? commissioningDate : '-';
    if (date === '-') date = extractYearFromFileNumber(no) || '-';
    return {
      _is_commissioning: true,
      _is_lineage_commissioning: true,
      // Positioned by splice against the parcel-update that created this file, never by weight.
      _pinned: true,
      id: 'commissioning-' + idSuffix + '-' + no,
      source_table: 'File Commissioning',
      fileno: no,
      file_number: no,
      mlsFNo: no,
      lifecycle_file_no: no,
      transaction_type: commissioningLabelFor(no),
      instrument_type: commissioningLabelFor(no),
      party_1: 'Kano State Ministry of Land and Physical Planning',
      party_2: fileTitle || '-', party_3: '-', party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: date,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: '',
    };
  };

  // Rule 2: a File Decommissioning row shows the real Date Decommissioned only for a genuine
  // KLAES decommission ('parcel_update_new' / 'title_status_update'); a backfilled / "back
  // linkage" decommission ('backfill' or an unset event_type) shows a blank Transaction Date.
  // Mirrors PHP LegalSearchService::decommissionDisplayDate().
  const decommissionDisplayDate = (eventType, rawDate) => {
    const d = String(rawDate == null ? '' : rawDate).trim();
    if (d === '' || d === '-') return '-';
    return (eventType === 'parcel_update_new' || eventType === 'title_status_update') ? d : '-';
  };

  // Build a "File Decommissioning" row for a successor lineage file that was ITSELF
  // later retired — e.g. the Subdivision child CON-AG-2026-108 retired by a Change of
  // Purpose into CON-COM-2026-430. Mirrors buildDecommissioningTimelineRow but for a
  // file other than the searched one.
  const buildLineageDecommissioningRow = (succ) => {
    const no = String(succ?.decommission_file_no || succ?.file_no || '')
      .replace(/\s*\(\s*T\s*\)\s*$/i, '').trim();
    if (!no) return null;
    // A KANGIS-format file is an alias of a land file — it shows no File Decommissioning row.
    const _kt = identifyFileNumberType(no);
    if (_kt === 'kangis' || _kt === 'new_kangis') return null;
    if (isSystemTempFileNo(no)) return null;
    return {
      _is_decommissioning: true,
      _is_lineage_decommissioning: true,
      _pinned: true,
      id: 'decommissioning-next-' + no,
      source_table: 'File Decommissioning',
      fileno: no,
      file_number: no,
      mlsFNo: no,
      lifecycle_file_no: no,
      transaction_type: 'File Decommissioning',
      instrument_type: 'File Decommissioning',
      party_1: 'Kano State Ministry of Land and Physical Planning',
      party_2: succ?.decommission_holder || succ?.file_title || '-', party_3: '-', party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: decommissionDisplayDate(succ?.decommission_event_type, succ?.decommission_date),
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: '',
      comments: succ?.decommission_reason || '-',
    };
  };

  // Commissioning rows for the searched file's successor(s) (when the searched
  // file was itself superseded by a subdivision/merger/CoP). A batch subdivision
  // retires the mother into SEVERAL children at once (successor_file_no is a CSV
  // list), so one row is built per successor. The server resolves the chain
  // recursively, so a child that was itself retired (Change of Purpose) also
  // contributes its own File Decommissioning row, followed by the grandchild's
  // commissioning. They sit after the parcel-update transaction that retired the
  // searched file.
  const buildSuccessorCommissioningRows = () => {
    const lineage = window._lsLineage || {};
    const succFiles = Array.isArray(lineage.successor_files) && lineage.successor_files.length
      ? lineage.successor_files
      // Fallback for a response without the resolved list: split the raw CSV.
      : String(lineage.successor_file_no || '').split(',')
          .map(s => ({ file_no: s.trim(), commissioning_date: '-', file_title: '' }))
          .filter(s => s.file_no);
    // Each successor contributes its commissioning row and, when it was itself later
    // retired, its own decommissioning row directly below — so a Change-of-Purpose child
    // reads: commissioned → decommissioned, with the grandchild's commissioning next.
    return succFiles.flatMap(s => [
      buildLineageCommissioningRow(s.file_no, s.commissioning_date, s.file_title, 'next'),
      s.is_superseded ? buildLineageDecommissioningRow(s) : null,
    ]).filter(Boolean);
  };

  // Build the synthetic "File Decommissioning" row for the SEARCHED file when it
  // has itself been decommissioned (superseded by a subdivision / merger / change of
  // purpose / etc.). The original file's own transaction history ends at this event,
  // so the row is placed immediately after the file's last own transaction and before
  // the parcel-update row that retired it (see renderTimeline). Returns null when the
  // searched file is still active.
  const buildDecommissioningTimelineRow = () => {
    const lineage = window._lsLineage || {};
    if (!lineage.is_superseded) return null;
    const rawFileNo = (lineage.decommission_file_no && String(lineage.decommission_file_no).trim())
      || (userSelectedFileNumber && String(userSelectedFileNumber).trim())
      || window._currentFileNumber
      || (selectedFile && (selectedFile.mlsFNo || selectedFile.fileno || selectedFile.fileNo))
      || '-';
    const fileNo = String(rawFileNo).replace(/\s*\(\s*T\s*\)\s*$/i, '').trim() || rawFileNo;
    // A KANGIS-format file is an alias of a land file — it shows no File Decommissioning row.
    const _kt = identifyFileNumberType(fileNo);
    if (_kt === 'kangis' || _kt === 'new_kangis') return null;
    if (isSystemTempFileNo(fileNo)) return null;
    const date = decommissionDisplayDate(lineage.decommission_event_type, lineage.decommission_date);
    // Holder resolved server-side from the decommission archive; fall back to the
    // searched file's title.
    const ownerName = (lineage.decommission_holder && String(lineage.decommission_holder).trim())
      || (selectedFile && (selectedFile._file_title || selectedFile.file_title)) || '-';
    return {
      _is_decommissioning: true,
      // Spliced immediately before the parcel-update row that retired this file.
      _pinned: true,
      id: 'decommissioning',
      source_table: 'File Decommissioning',
      fileno: fileNo,
      file_number: fileNo,
      mlsFNo: fileNo,
      lifecycle_file_no: fileNo,
      transaction_type: 'File Decommissioning',
      instrument_type: 'File Decommissioning',
      party_1: 'Kano State Ministry of Land and Physical Planning',
      party_2: ownerName, party_3: '-', party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: date,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: (selectedFile && (selectedFile.prop_id || selectedFile.propId)) || '',
      comments: lineage.decommission_reason || '-',
    };
  };

  // Build the synthetic "Temporary File" timeline record. It appears directly
  // below File Commissioning (both weight 12; the temp row is ordered right after
  // the main commissioning by the group merge) only when the searched file has a
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

    // Same Party 2 as the permanent File Commissioning row above it: the file title.
    const ownerName = (selectedFile && (selectedFile._file_title || selectedFile.file_title)) || '-';
    return {
      _is_temporary_file: true,
      id: 'temporary-file',
      source_table: 'Temporary File',
      fileno: tempFileNo,
      file_number: tempFileNo,
      mlsFNo: tempFileNo,
      lifecycle_file_no: tempFileNo,
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

  // ---------------------------------------------------------------------------
  // Lifecycle-file grouping: each file's complete lifecycle (commissioning →
  // transactions → decommissioning) is rendered as a block, then the next file.
  // ---------------------------------------------------------------------------

  const normalizeLifecycleFileNo = (v) => {
    let s = String(v || '').trim().toUpperCase();
    s = s.replace(/\s+/g, ' ');
    s = s.replace(/\s*\(\s*T\s*\)\s*$/i, '(T)');
    return s;
  };

  // The file the user actually searched, normalized — or '' when nothing is selected.
  const searchedLifecycleKey = () => {
    try {
      const raw = userSelectedFileNumber || window._currentFileNumber || '';
      return raw ? normalizeLifecycleFileNo(baseFileNo(raw)) : '';
    } catch (_) {
      // Called before the module's own state is initialised — no searched file yet,
      // so grouping falls through to the ordinary candidate order.
      return '';
    }
  };

  const extractLifecycleFileNo = (item) => {
    const candidates = [
      item?.file_no,
      item?.fileno,
      item?.file_number,
      item?.mlsFNo,
      item?.kangisFileNo,
      item?.NewKANGISFileno,
    ];

    // A row that NAMES the searched file belongs to the searched file, whichever
    // column comes first in the list below. pra #126911 carries mlsFNo COM-2016-219
    // and fileno COM-2026-219; taking `fileno` filed that file's own Occupancy Permit
    // into a SECOND lifecycle group, where weight 14 could not lift it above the
    // searched file's rows — so the OP read third, under a File Commissioning row of
    // 12, and a second commissioning row appeared. Mirrors the same guard in
    // LegalSearchService::buildPrintReport().
    const searched = searchedLifecycleKey();
    if (searched) {
      for (const c of [item?.lifecycle_file_no, ...candidates]) {
        const s = String(c || '').trim();
        if (s && s !== '-' && normalizeLifecycleFileNo(s) === searched) return searched;
      }
    }

    if (item?.lifecycle_file_no) return normalizeLifecycleFileNo(item.lifecycle_file_no);

    for (const c of candidates) {
      const s = String(c || '').trim();
      if (s && s !== '-') return normalizeLifecycleFileNo(s);
    }
    return null;
  };

  const isKangisFileNo = (v) => {
    const t = identifyFileNumberType(v);
    return t === 'kangis' || t === 'new_kangis';
  };

  // A SYSTEM temporary file number ("TEMP-91950") is an internal deed-registration placeholder,
  // not a real file: no File Commissioning/Decommissioning row and never shown in the File No
  // field. Mirrors PHP LegalSearchService::isSystemTempFileNo(). Distinct from a genuine "(T)"
  // temporary file (isTempFileNo), which keeps its own "Temporary File" row.
  const isSystemTempFileNo = (v) => /^TEMP[-_ ]?\d+/i.test(String(v || '').trim());

  const extractKangisLifecycleKey = (r) => {
    const candidates = [
      r?.kangisFileNo,
      r?.NewKANGISFileno,
      r?.file_no,
      r?.fileno,
      r?.file_number,
      r?.mlsFNo,
      r?.parent_file_number,
    ];
    for (const c of candidates) {
      const s = String(c || '').trim();
      if (s && s !== '-' && isKangisFileNo(s)) return normalizeLifecycleFileNo(s);
    }
    return '';
  };

  // A KANGIS number (e.g. "KNML 6992") is only an alias of a permanent land file;
  // it has no lifecycle of its own. Build a KANGIS -> main-land map so KANGIS rows
  // (Recertification, KANGIS C of O, …) roll into their owning file's lifecycle
  // group instead of forming a phantom group of their own.
  const buildKangisAliasMap = (rows) => {
    const map = {};
    const addPair = (kangis, main) => {
      if (!kangis || !main) return;
      const k = normalizeLifecycleFileNo(kangis);
      const m = normalizeLifecycleFileNo(main);
      if (k && m && k !== m && isKangisFileNo(k) && !isKangisFileNo(m)) map[k] = m;
    };
    // Pair the two numbers inside a "MAIN (ALIAS)" display string, e.g.
    // "CON-COM-2023-197 (KNML 6992)" — order-independent: the KANGIS side becomes
    // the alias, the land side its owning lifecycle file.
    const addFromDisplay = (display) => {
      const s = String(display || '').trim();
      if (!s) return;
      const m = s.match(/^(.+?)\s*\(\s*([^()]+?)\s*\)\s*$/);
      const parts = m ? [m[1].trim(), m[2].trim()] : [s];
      const kangisSide = parts.find(p => isKangisFileNo(p));
      const mainSide = parts.find(p => p && p !== '-' && !isKangisFileNo(p));
      if (kangisSide && mainSide) addPair(kangisSide, mainSide);
    };

    // Seed from the searched/selected file — the authoritative main <-> KANGIS pairing.
    try {
      if (selectedFile) {
        addFromDisplay(selectedFile._file_number_display);
        const fn = extractFileNumbers(selectedFile);
        const mainNo = [fn.mls, fn.parent, fn.st].find(v => v && v !== '-');
        [fn.kangis, fn.new_kangis].forEach(k => { if (k && k !== '-') addPair(k, mainNo); });
      }
    } catch (e) { /* best-effort */ }
    // Seed from the raw searched/display strings too (covers "MAIN (KANGIS)" and the
    // reverse "KANGIS (MAIN)" when the user searched by the KANGIS number).
    addFromDisplay(window.__lsLastSearchedFileNumber);
    addFromDisplay(userSelectedFileNumber);
    // Seed from any row that carries both a land number and a KANGIS number, or a
    // "MAIN (ALIAS)" display string of its own.
    for (const r of (rows || [])) {
      addFromDisplay(r?._file_number_display);
      const nums = [r?.file_no, r?.fileno, r?.file_number, r?.mlsFNo, r?.kangisFileNo, r?.NewKANGISFileno, r?.parent_file_number]
        .map(v => String(v || '').trim())
        .filter(v => v && v !== '-');
      const mainNo = nums.find(v => !isKangisFileNo(v));
      if (!mainNo) continue;
      nums.filter(v => isKangisFileNo(v)).forEach(k => addPair(k, mainNo));
    }
    return map;
  };

  const extractSerialFromFileNumber = (v) => {
    const s = String(v || '').trim().replace(/\s*\(\s*T\s*\)\s*$/i, '');
    const m = s.match(/(\d+)$/);
    return m ? parseInt(m[1], 10) : null;
  };

  const isTempFileNo = (v) => /\(\s*T\s*\)\s*$/i.test(String(v || ''));

  const baseFileNo = (v) => String(v || '').replace(/\s*\(\s*T\s*\)\s*$/i, '').trim();

  const lifecycleMetaFor = (fileNo) => {
    const meta = window._lsLifecycleMeta || {};
    return meta[normalizeLifecycleFileNo(fileNo)] || meta[baseFileNo(fileNo)] || null;
  };

  const getHolderForFile = (fileNo, rowsForFile) => {
    const meta = lifecycleMetaFor(fileNo);
    if (meta?.commissioning_holder && String(meta.commissioning_holder).trim() !== '') {
      return String(meta.commissioning_holder).trim();
    }
    const ordered = [...rowsForFile].sort((a, b) => (getTransactionTimestamp(a) ?? Infinity) - (getTransactionTimestamp(b) ?? Infinity));
    for (const r of ordered) {
      const p2 = String(r?.party_2 || '').trim();
      if (p2 && p2 !== '-') return p2;
      const p1 = String(r?.party_1 || '').trim();
      if (p1 && p1 !== '-') return p1;
    }
    return selectedFile?._file_commissioning_holder || selectedFile?._file_title || selectedFile?.file_title || '-';
  };

  const buildLifecycleCommissioningRow = (fileNo, rowsForFile) => {
    const no = baseFileNo(fileNo);
    if (!no || no === '-') return null;
    // A KANGIS-format file (KNML/MLKN/KNGP/KN…) is an alias of a land file, never its own
    // lifecycle — it shows no File Commissioning row (only its Recertification appears).
    const _kt = identifyFileNumberType(no);
    if (_kt === 'kangis' || _kt === 'new_kangis') return null;
    if (isSystemTempFileNo(no)) return null;

    const meta = lifecycleMetaFor(fileNo);
    // A genuine KLAES commissioning date (resolved server-side from mls_file_no) is
    // the only real date shown here. A file NOT commissioned through KLAES (absent
    // from mls_file_no, so meta.commissioning_date is '-') must show only the bare
    // year embedded in its file number — never the date of its earliest transaction.
    let date = '-';
    if (meta?.commissioning_date && String(meta.commissioning_date).trim() !== '' && String(meta.commissioning_date).trim() !== '-') {
      date = String(meta.commissioning_date).trim();
    } else {
      const year = extractYearFromFileNumber(no);
      if (year) date = year;
    }

    const holder = getHolderForFile(fileNo, rowsForFile);

    return {
      _is_commissioning: true,
      id: 'commissioning-' + no,
      source_table: 'File Commissioning',
      fileno: no,
      file_number: no,
      mlsFNo: no,
      lifecycle_file_no: normalizeLifecycleFileNo(no),
      transaction_type: commissioningLabelFor(no),
      instrument_type: commissioningLabelFor(no),
      party_1: 'Kano State Ministry of Land and Physical Planning',
      party_2: holder,
      party_3: '-',
      party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: date,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: '',
    };
  };

  const buildLifecycleDecommissioningRow = (fileNo, rowsForFile) => {
    const no = baseFileNo(fileNo);
    if (!no || no === '-') return null;
    // A KANGIS-format file is an alias of a land file — it shows no File Decommissioning row.
    const _kt = identifyFileNumberType(no);
    if (_kt === 'kangis' || _kt === 'new_kangis') return null;
    if (isSystemTempFileNo(no)) return null;
    const meta = lifecycleMetaFor(fileNo);
    const date = decommissionDisplayDate(meta?.decommission_event_type, meta?.decommission_date);
    const holder = getHolderForFile(fileNo, rowsForFile);
    return {
      _is_decommissioning: true,
      id: 'decommissioning-' + no,
      source_table: 'File Decommissioning',
      fileno: no,
      file_number: no,
      mlsFNo: no,
      lifecycle_file_no: normalizeLifecycleFileNo(no),
      transaction_type: 'File Decommissioning',
      instrument_type: 'File Decommissioning',
      party_1: 'Kano State Ministry of Land and Physical Planning',
      party_2: holder,
      party_3: '-',
      party_4: '-',
      serial_no: '', page_no: '', volume_no: '',
      transaction_date: date,
      reg_date: '',
      caveat: 'No',
      is_caveated: 0,
      prop_id: '',
      comments: '-',
    };
  };

  const classifyLifecycleEventType = (row) => {
    const source = String(row?.source_table || '');
    const instrument = String(row?.instrument_type || '');
    const type = String(row?.transaction_type || '');
    const synth = row?._synthesized === true;

    if (source === 'File Commissioning' || source === 'DCIV File Commissioning' || source === 'ST File Commissioning'
        || instrument === 'File Commissioning' || instrument === 'DCIV File Commissioning'
        || type === 'File Commissioning' || type === 'DCIV File Commissioning'
        || type.startsWith('ST File Commissioning')
        || (synth && !row?._is_recertification && (instrument.includes('Commissioning') || type.includes('Commissioning')))) {
      return 'File Commissioning';
    }
    if (source === 'Temporary File' || instrument === 'Temporary File' || type === 'Temporary File' || (synth && instrument.includes('Temporary File'))) {
      return 'Temporary File';
    }
    if (source === 'File Decommissioning' || instrument === 'File Decommissioning' || type === 'File Decommissioning' || (synth && instrument.includes('Decommissioning'))) {
      return 'File Decommissioning';
    }
    if (/kangis/i.test(type) && /recertification/i.test(type)) {
      return 'Kangis Recertification';
    }
    return null;
  };

  const scoreLifecycleRow = (row, eventType) => {
    const source = String(row?.source_table || '');
    const instrument = String(row?.instrument_type || '');
    const type = String(row?.transaction_type || '');
    const date = String(row?.transaction_date || '').trim();

    let score = 0;
    // Prefer rows whose source_table matches the lifecycle event type.
    if (source === eventType) score += 100;
    // Prefer non-synthesized rows.
    if (!row?._is_commissioning && !row?._is_decommissioning && !row?._is_temporary_file) score += 50;
    // Prefer rows with a real transaction date.
    if (date && date !== '-') score += 25;
    // Prefer rows with a matching instrument type.
    if (instrument === eventType) score += 10;
    // Prefer rows with a matching transaction type.
    if (type === eventType) score += 10;
    return score;
  };

  const dedupeLifecycleRows = (groupedRows) => {
    const result = {};
    const lifecycleEventDedupeKey = (r) => {
      const eventType = classifyLifecycleEventType(r);
      if (!eventType) return null;
      // The mother ST "ST File Commissioning" event is DISTINCT from the mother's
      // "Land File Commissioning" — both classify as 'File Commissioning' but must
      // coexist in the same land block, so give the ST primary its own dedupe key.
      if (r && r._st_primary_commissioning) return 'ST File Commissioning';
      // Likewise the old Ministry "KN 6071" file's commissioning: a DIFFERENT file's event from
      // the land file's own, though both classify as 'File Commissioning' and share the group.
      // Key it by its own number so the two coexist instead of one deduping the other away.
      const ownNo = ['file_no', 'fileno', 'file_number', 'mlsFNo']
        .map(c => String(r?.[c] || '').trim())
        .find(v => v && v !== '-') || '';
      if (eventType === 'File Commissioning' && isOldKnFileNo(ownNo)) {
        return `${eventType}|${normalizeLifecycleFileNo(ownNo)}`;
      }
      if (eventType === 'Kangis Recertification') {
        const k = extractKangisLifecycleKey(r) || normalizeLifecycleFileNo(extractLifecycleFileNo(r) || '');
        return `${eventType}|${k}`;
      }
      return eventType;
    };

    for (const fno of Object.keys(groupedRows)) {
      const rows = groupedRows[fno];

      // Pick the best-scored row per lifecycle-event key for the whole group.
      // KANGIS recertification rows are keyed by KANGIS number so distinct KANGIS
      // recerts can coexist while duplicates collapse.
      const bestByKey = {};
      for (const r of rows) {
        const dedupeKey = lifecycleEventDedupeKey(r);
        const eventType = classifyLifecycleEventType(r);
        if (!dedupeKey || !eventType) continue;
        if (!bestByKey[dedupeKey]
            || scoreLifecycleRow(r, eventType) > scoreLifecycleRow(bestByKey[dedupeKey], eventType)) {
          bestByKey[dedupeKey] = r;
        }
      }

      // Rebuild preserving original order: emit each event key's winner at the
      // position of its FIRST occurrence (so a Kangis Recertification stays directly
      // above its C of O), and drop any subsequent duplicates.
      const emitted = {};
      const deduped = [];
      for (const r of rows) {
        const dedupeKey = lifecycleEventDedupeKey(r);
        if (!dedupeKey) { deduped.push(r); continue; }
        if (emitted[dedupeKey]) continue;
        emitted[dedupeKey] = true;
        deduped.push(bestByKey[dedupeKey]);
      }

      result[fno] = deduped;
    }
    return result;
  };

  // Rule 4: a recertified LAND file carries an "-RC-" token (RES-RC-1982-200,
  // CON-RES-RC-2005-1). Mirrors PHP LegalSearchService::isRecertLandFile(). KANGIS-format
  // numbers are excluded (they carry a KANGIS Recertification instead).
  const isRecertLandFileNo = (v) => {
    const s = String(v || '').toUpperCase().trim();
    if (!s || isKangisFileNo(s)) return false;
    return /(?:^|[-_/ ])RC(?:[-_/ ]|$)/.test(s);
  };

  // An "-RC-" land file was commissioned AND recertified by the Ministry; the client reads
  // those as one event, so its commissioning row carries the combined label and no separate
  // "Land Recertification (File Commissioning)" line is emitted for it (see
  // dropMergedRecertRows). Mirrors LegalSearchService::commissioningLabelFor().
  const commissioningLabelFor = (fileNo) =>
    isRecertLandFileNo(fileNo) ? 'File Commissioning & Recertification' : 'File Commissioning';

  // Drop the recert rows that commissioningLabelFor() has folded into an RC file's own
  // commissioning line. Scoped to rows sitting on the RC file ITSELF — the identically-typed
  // row belonging to its old Ministry "KN 3686" number is a different file's line and
  // survives, ranking above the commissioning row (OLD_KN_COMMISSIONING).
  // Mirrors LegalSearchService::dropMergedRecertRows().
  const dropMergedRecertRows = (rows) => rows.filter(r => {
    const type = String(r?.instrument_type || r?.transaction_type || '').trim();
    if (type.toLowerCase() !== 'land recertification (file commissioning)') return true;
    const own = ['file_no', 'fileno', 'file_number', 'mlsFNo']
      .map(c => String(r?.[c] || '').trim())
      .find(v => v && v !== '-') || '';
    return !isRecertLandFileNo(own);
  });

  const ensureLifecycleSyntheticRows = (groupedRows) => {
    const result = {};
    // The searched file's own lineage flag (window._lsLineage) describes ONLY that
    // file — it must never decommission related/child files, whose decommission
    // state comes from their own per-file metadata.
    const searchedNo = normalizeLifecycleFileNo(userSelectedFileNumber || window._currentFileNumber || '');
    const primaryMainNo = normalizeLifecycleFileNo(baseFileNo(searchedNo));
    for (const fno of Object.keys(groupedRows)) {
      const rows = groupedRows[fno];
      const meta = lifecycleMetaFor(fno);
      // The old Ministry "KN 6071" row is typed "File Commissioning" but belongs to the KN
      // file, not to the group it is folded into — it must not satisfy this group's own
      // commissioning row, which would then never be synthesized.
      const isForeignOldKnRow = (r) => isOldKnFileNo(
        ['file_no', 'fileno', 'file_number', 'mlsFNo']
          .map(c => String(r?.[c] || '').trim())
          .find(v => v && v !== '-') || ''
      );
      const hasCommissioning = rows.some(r => !isForeignOldKnRow(r) && (
        String(r?.source_table || '') === 'File Commissioning'
        || String(r?.source_table || '') === 'DCIV File Commissioning'
        // A unit ST "ST File Commissioning – Fragmentation" row IS that unit's own
        // commissioning event — don't synthesize a generic "File Commissioning" that
        // would replace its ST label + real date. (The mother's ST primary row is tagged
        // to the LAND group, which already carries its Land File Commissioning row.)
        || String(r?.source_table || '') === 'ST File Commissioning'
        || String(r?.instrument_type || '') === 'File Commissioning'
        || String(r?.instrument_type || '') === 'DCIV File Commissioning'
        || String(r?.transaction_type || '') === 'File Commissioning'
        || String(r?.transaction_type || '') === 'DCIV File Commissioning'
      ));
      const hasTemp = rows.some(r =>
        String(r?.source_table || '') === 'Temporary File'
        || String(r?.instrument_type || '') === 'Temporary File'
        || String(r?.transaction_type || '') === 'Temporary File'
      );
      const hasDecommissioning = rows.some(r =>
        String(r?.source_table || '') === 'File Decommissioning'
        || String(r?.instrument_type || '') === 'File Decommissioning'
        || String(r?.transaction_type || '') === 'File Decommissioning'
      );

      if (!hasCommissioning) {
        const row = buildLifecycleCommissioningRow(fno, rows);
        if (row) rows.push(row);
      }
      // Rule 4 (superseded): an "-RC-" land file used to get its own Ministry of Land and
      // Physical Planning Recertification line here. That event now reads off the file's
      // commissioning row instead — see commissioningLabelFor() — so nothing is synthesized,
      // and any real row of that type on the file itself is folded away by
      // dropMergedRecertRows().
      // Rules 6/7: a land file that carries a KANGIS number WAS recertified — the KANGIS
      // number is the product of the exercise. The line must show even when no
      // related_file_number link records it: the alias is frequently known only from the
      // KANGIS file_indexings row's related_fileno back-link, which yields KANGIS
      // transaction rows (a C of O) but no recert link. Old KNML/MLKN/KNGP -> First,
      // new KN -> Second. Mirrors LegalSearchService::ensureLifecycleSyntheticRows().
      const isKangisRecertRow = (r) => {
        const t = String(r?.transaction_type || r?.instrument_type || '');
        if (!/recertification/i.test(t)) return false;
        return !(/physical planning|ministry/i.test(t) || /land recertification/i.test(t));
      };
      // KANGIS endpoints already covered by a recert row on this lifecycle; '' means a
      // recert row whose KANGIS endpoint could not be identified (suppresses all).
      const recertedKangis = new Set();
      let hasUnkeyedRecert = false;
      for (const r of rows) {
        if (!isKangisRecertRow(r)) continue;
        const k = extractKangisLifecycleKey(r);
        if (k) recertedKangis.add(k); else hasUnkeyedRecert = true;
      }
      if (!isKangisFileNo(fno) && !isSystemTempFileNo(fno) && !hasUnkeyedRecert) {
        const aliases = new Set();
        for (const r of rows) {
          const k = extractKangisLifecycleKey(r);
          // A SPACED "KN 6071" is the old Ministry file number, not evidence of a KANGIS
          // recertification — identifyFileNumberType() reads it as new-KANGIS only because its
          // regex tolerates a separator. Synthesizing a "Second KANGIS Recertification" for it
          // would invent a second line for the file whose commissioning row is already here.
          if (k && !recertedKangis.has(k) && !isOldKnFileNo(k)) aliases.add(k);
        }
        for (const kangisNo of aliases) {
          rows.push({
            _is_recertification: true,
            id: 'kangis-recert-' + kangisNo,
            source_table: 'Related Fileno',
            fileno: kangisNo, file_number: kangisNo, mlsFNo: kangisNo,
            lifecycle_file_no: normalizeLifecycleFileNo(fno),
            parent_file_number: fno,
            transaction_type: identifyFileNumberType(kangisNo) === 'new_kangis'
              ? 'Second KANGIS Recertification'
              : 'First KANGIS Recertification',
            instrument_type: identifyFileNumberType(kangisNo) === 'new_kangis'
              ? 'Second KANGIS Recertification'
              : 'First KANGIS Recertification',
            party_1: 'Kano Geographic Information Service',
            party_2: getHolderForFile(fno, rows), party_3: '-', party_4: '-',
            serial_no: '', page_no: '', volume_no: '',
            // A KANGIS recertification's true date is recorded nowhere — print a dash
            // rather than borrow the KANGIS C of O's date.
            transaction_date: '-', reg_date: '',
            caveat: 'No', is_caveated: 0, prop_id: '',
            comments: '-',
            _synthesized: true
          });
        }
      }

      const shouldHaveTemp = (meta?.is_temp === true) || isTempFileNo(fno);
      if (shouldHaveTemp && !hasTemp) {
        const holder = getHolderForFile(fno, rows);
        rows.push({
          _is_temporary_file: true,
          id: 'temporary-file-' + fno,
          source_table: 'Temporary File',
          fileno: fno,
          file_number: fno,
          mlsFNo: fno,
          lifecycle_file_no: normalizeLifecycleFileNo(fno),
          transaction_type: 'Temporary File',
          instrument_type: 'Temporary File',
          party_1: 'Kano State Ministry of Land and Physical Planning',
          party_2: holder,
          party_3: '-',
          party_4: '-',
          serial_no: '', page_no: '', volume_no: '',
          transaction_date: '-',
          reg_date: '',
          caveat: 'No',
          is_caveated: 0,
          prop_id: '',
          _synthesized: true
        });
      }
      const metaDecommissioned = (meta?.is_decommissioned === true) || (meta?.is_decommissioned === 1);
      const isSearchedFileGroup = (fno === searchedNo) || (fno === primaryMainNo);
      const isDecommissioned = metaDecommissioned
        || (isSearchedFileGroup && !!window._lsLineage?.is_superseded);
      if (!hasDecommissioning && isDecommissioned) {
        const dec = buildLifecycleDecommissioningRow(fno, rows);
        if (dec) rows.push(dec);
      }
      // The server already folds these away for the searched file; repeat it here so a row
      // reaching the timeline by another route (an expanded child, a re-sort) can't reinstate
      // a second line for an event the commissioning row now names.
      result[fno] = dropMergedRecertRows(rows);
    }
    return result;
  };

  const arrangeLifecycleFileRows = (rows) => {
    const decommissioning = [];
    const transactions = [];

    for (const r of rows) {
      const evt = classifyLifecycleEventType(r);
      if (evt === 'File Decommissioning') {
        decommissioning.push(r);
      } else {
        transactions.push(r);
      }
    }

    // Two phases, matching arrangeLifecycleFileRows() in LegalSearchService. This sorter used
    // to read a null weight as 0 and rank floating events in one pass with the weighted ones —
    // which pinned every floater to the C of O's old rank and sank it beneath the file's
    // dealings, silently undoing the chronological placement sortTimelineChronologically()
    // had already agreed on.
    const compareByDateThenId = (a, b) => {
      const ta = getTransactionTimestamp(a);
      const tb = getTransactionTimestamp(b);
      if (ta === null && tb === null) return (Number(a.id) || 0) - (Number(b.id) || 0);
      if (ta === null) return 1;
      if (tb === null) return -1;
      if (ta !== tb) return ta - tb;
      return (Number(a.id) || 0) - (Number(b.id) || 0);
    };

    const weightedRows = [];
    const floatingRows = [];
    for (const t of transactions) {
      (recordPriorityWeight(t) === null ? floatingRows : weightedRows).push(t);
    }

    // Phase 1 — weight DESC, then date ASC, then id ASC.
    weightedRows.sort((a, b) => {
      const wa = recordPriorityWeight(a);
      const wb = recordPriorityWeight(b);
      if (wa !== wb) return wb - wa;
      return compareByDateThenId(a, b);
    });

    // Phase 2 — inject each floater after the last DATED weighted event on or before it.
    floatingRows.sort(compareByDateThenId);

    const isDatedWeighted = (item) =>
      recordPriorityWeight(item) !== null && getTransactionTimestamp(item) !== null;

    const ordered = [...weightedRows];
    for (const floater of floatingRows) {
      const ts = getTransactionTimestamp(floater);
      if (ts === null) { ordered.push(floater); continue; }

      let insertAt = 0;
      ordered.forEach((existing, i) => {
        if (!isDatedWeighted(existing)) return;
        if (getTransactionTimestamp(existing) <= ts) insertAt = i + 1;
      });
      // Past floaters already parked on this anchor, and past UNDATED weighted rows, which
      // have no position in time and must stay with their weight group.
      while (insertAt < ordered.length && !isDatedWeighted(ordered[insertAt])) {
        insertAt++;
      }
      ordered.splice(insertAt, 0, floater);
    }

    let arrangedTransactions = placeKangisRecertBeforeCofo(ordered);

    // A File Commissioning row is NO LONGER hoisted to the head of its block — it takes the
    // position its weight earns, so an Occupancy Permit (14) and its Transfer of Title (13)
    // read ABOVE the commissioning line (12). One exception survives:
    //
    //  - A FLOATING commissioning row (DCIV, weight null) has no rank to take a position
    //    from and would sink to the foot of the block, so it keeps the old hoist.
    //
    // The Rule 4 hoist that used to splice a "Land Recertification" row directly under the
    // commissioning line is GONE: an RC file's recertification is now named by the
    // commissioning row itself, and the only rows still carrying that label belong to the
    // file's old Ministry "KN 3686" number, which must rank ABOVE the commissioning row
    // (OLD_KN_COMMISSIONING = 15) — the splice was dragging them back underneath it.
    //
    // Mirrors LegalSearchService::arrangeLifecycleFileRows().
    const isCommissioningEvent = (r) => {
      const evt = classifyLifecycleEventType(r);
      return evt === 'File Commissioning' || evt === 'Temporary File';
    };
    const floatingCommissioning = [];
    const otherTransactions = [];
    for (const r of arrangedTransactions) {
      if (isCommissioningEvent(r) && recordPriorityWeight(r) === null) {
        floatingCommissioning.push(r);
      } else {
        otherTransactions.push(r);
      }
    }

    arrangedTransactions = [...floatingCommissioning, ...otherTransactions];

    // A WEIGHTED commissioning row must still lead everything it outranks. Phase 1
    // should already do this, but a commissioning row that loses its weight upstream is
    // injected by date instead and sinks into the middle of the block (production:
    // CON-RES-RC-1982-709 read RofO(11), CofO(1), Commissioning(12)). Placing it
    // explicitly makes the outcome independent of that.
    //
    // Rows that genuinely outrank commissioning keep their place above it: an Old KN
    // commissioning (15), an Occupancy Permit (14) and its Transfer of Title (13).
    // Mirrors LegalSearchService::hoistCommissioningAboveLowerRanks().
    for (let i = 0; i < arrangedTransactions.length; i++) {
      const row = arrangedTransactions[i];
      if (!isCommissioningEvent(row)) continue;

      const ownWeight = recordPriorityWeight(row);
      if (ownWeight === null) continue;   // already hoisted by the floater pass above

      let target = -1;
      for (let k = 0; k < i; k++) {
        const w = recordPriorityWeight(arrangedTransactions[k]);
        if (w === null || w < ownWeight) { target = k; break; }
      }
      if (target === -1 || target >= i) continue;

      arrangedTransactions.splice(i, 1);
      arrangedTransactions.splice(target, 0, row);
    }

    // Sectional Titling: within an ST unit's block the transactions read strictly
    // chronologically (Right of Occupancy before its later Assignment/Transfer of Title),
    // NOT by the global legal-hierarchy weight (which ranks Transfer of Title above Right
    // of Occupancy for OP/TOT parcels). Only re-sort when EVERY transaction in the band is
    // an ST row, so non-ST lifecycles keep their weighted order untouched. Mirrors
    // LegalSearchService::arrangeLifecycleFileRows().
    const isStRow = (r) => {
      const source = String(r?.source_table || '');
      const type = String(r?.transaction_type || r?.instrument_type || '').toUpperCase();
      return source === 'ST File Commissioning' || type.startsWith('ST ') || type.includes('(ST)');
    };
    if (arrangedTransactions.length > 1 && arrangedTransactions.every(isStRow)) {
      arrangedTransactions = arrangedTransactions.slice().sort((a, b) => {
        const ta = getTransactionTimestamp(a);
        const tb = getTransactionTimestamp(b);
        if (ta === null && tb === null) return (Number(a.id) || 0) - (Number(b.id) || 0);
        if (ta === null) return 1;   // undated rows sink to the end of the band
        if (tb === null) return -1;
        return ta - tb;
      });
    }

    // A KANGIS Recertification (First or Second) closes the file's KANGIS chapter, so it reads
    // LAST in the transaction band — directly above the File Decommissioning that retires the
    // file, since decommissioning rows are appended after this. Its weight (8) would otherwise
    // rank it above the file's own dealings.
    //
    // Any C of O pinned directly beneath a recert by placeKangisRecertBeforeCofo() travels with
    // it: the pair moves as one block so the recert still precedes the C of O it produced.
    // Classification is by lifecycle event, not by weight, so the Ministry of Land
    // recertification (Rule 4, kept under File Commissioning) is untouched.
    // Mirrors LegalSearchService::arrangeLifecycleFileRows().
    const recertBlock = [];
    const beforeRecert = [];
    for (let i = 0; i < arrangedTransactions.length; i++) {
      if (classifyLifecycleEventType(arrangedTransactions[i]) !== 'Kangis Recertification') {
        beforeRecert.push(arrangedTransactions[i]);
        continue;
      }
      recertBlock.push(arrangedTransactions[i]);
      while (i + 1 < arrangedTransactions.length
        && classifyTimelineEvent(arrangedTransactions[i + 1]) === 'CERTIFICATE_OF_OCCUPANCY') {
        recertBlock.push(arrangedTransactions[++i]);
      }
    }
    // ...unless the KANGIS chapter closed BEFORE this file was commissioned. A land file
    // commissioned in 2026 that carries a 2021 KANGIS recertification + C of O inherited that
    // chapter from its predecessor: parking the pair at the foot of the band prints 2021 events
    // under a 2026 commissioning line. When every dated row of the recert block predates the
    // file's own commissioning row, the block is spliced in directly ABOVE that commissioning
    // row instead (rows outranking commissioning — an OP, a Transfer of Title, the old KN line —
    // keep their place above it). Mirrors LegalSearchService::arrangeLifecycleFileRows().
    let recertTs = null;
    for (const r of recertBlock) {
      const t = getTransactionTimestamp(r);
      if (t !== null && (recertTs === null || t < recertTs)) recertTs = t;
    }
    let commIndex = null;
    let commTs = null;
    for (let i = 0; i < beforeRecert.length; i++) {
      if (!isCommissioningEvent(beforeRecert[i])) continue;
      commIndex = i;
      commTs = getTransactionTimestamp(beforeRecert[i]);
      break;
    }

    if (recertBlock.length && recertTs !== null && commTs !== null && recertTs < commTs) {
      arrangedTransactions = [...beforeRecert];
      arrangedTransactions.splice(commIndex, 0, ...recertBlock);
    } else {
      arrangedTransactions = [...beforeRecert, ...recertBlock];
    }

    return [...arrangedTransactions, ...decommissioning];
  };

  const groupAndSortTimeline = (transactions) => {
    // KANGIS aliases belong to their owning main land file's lifecycle.
    const kangisToMain = buildKangisAliasMap(transactions);
    // Anchor each KANGIS key to the lifecycle owner of its CofO row so matching
    // recertifications can never drift into a different lifecycle group.
    const cofoOwnerByKangis = {};
    transactions.forEach((r) => {
      const type = String(r?.transaction_type || r?.instrument_type || '').toLowerCase();
      if (!type.includes('certificate of occupanc')) return;

      const kangisKey = extractKangisLifecycleKey(r);
      if (!kangisKey) return;

      let owner = extractLifecycleFileNo(r);
      if (owner && kangisToMain[owner]) owner = kangisToMain[owner];
      if (!owner) return;

      if (!cofoOwnerByKangis[kangisKey]) {
        cofoOwnerByKangis[kangisKey] = owner;
      }
    });

    // The primary file is the one the user actually searched. When a temp file
    // number is searched, its main/base file is treated as the primary group.
    // Resolved before the rows are tagged: resolveLifecycleOwner() needs the primary
    // number to fold system-temp rows into it.
    const searchedNo = normalizeLifecycleFileNo(
      userSelectedFileNumber || window._currentFileNumber || ''
    );
    const searchedIsTemp = isTempFileNo(searchedNo);
    const primaryFileNo = searchedIsTemp ? normalizeLifecycleFileNo(baseFileNo(searchedNo)) : searchedNo;
    const mainFileNo = normalizeLifecycleFileNo(baseFileNo(searchedNo));

    const resolveLifecycleOwner = (r) => {
      const type = String(r?.transaction_type || r?.instrument_type || '').toLowerCase();
      const isRecert = type.includes('recertification');

      if (isRecert) {
        const kangisKey = extractKangisLifecycleKey(r);
        if (kangisKey && cofoOwnerByKangis[kangisKey]) {
          return cofoOwnerByKangis[kangisKey];
        }
      }

      let fno = extractLifecycleFileNo(r);
      if (fno && kangisToMain[fno]) fno = kangisToMain[fno];

      // A system temporary file ("TEMP-91950") has no lifecycle of its own — it is the
      // placeholder an Occupancy Permit / deed registration is captured under, and its
      // number is hidden in the UI. Left as its own group it became a separate block
      // ranked after the searched file, so an OP (weight 14) rendered BELOW the searched
      // file's lower-weighted rows. Roll it into the primary group.
      // Mirrors LegalSearchService::tagRowsWithLifecycleFileNo().
      if (fno && isSystemTempFileNo(fno) && primaryFileNo) {
        return primaryFileNo;
      }

      // A "(T)" number is not its own lifecycle either — it is the SAME physical file as
      // its base number, so its rows join the base file's group (they keep displaying
      // their own "(T)" number; only the grouping key collapses). Same rule as PHP.
      if (fno && isTempFileNo(fno)) {
        const base = normalizeLifecycleFileNo(baseFileNo(fno));
        if (base) return base;
      }

      return fno;
    };

    // Ensure every row carries a normalized lifecycle owner (KANGIS rows remapped).
    let tagged = transactions.map(r => ({
      ...r,
      lifecycle_file_no: resolveLifecycleOwner(r),
    }));

    // Group rows by lifecycle owner. For temp-file searches, merge the temp file's
    // rows into the main file's group so the lifecycle reads:
    //   main commissioning → temp commissioning → temp transactions → ...
    const grouped = {};
    for (const r of tagged) {
      let fno = r.lifecycle_file_no;
      if (!fno) continue;
      if (searchedIsTemp && fno === searchedNo) {
        fno = mainFileNo;
      }
      if (!grouped[fno]) grouped[fno] = [];
      grouped[fno].push(r);
    }

    // Ensure the primary/main file exists as a group even if it has no transaction rows.
    if (primaryFileNo && !grouped[primaryFileNo]) grouped[primaryFileNo] = [];

    // Add missing commissioning/temp/decommissioning rows, then dedupe so each
    // lifecycle event type appears at most once per file.
    const enriched = ensureLifecycleSyntheticRows(grouped);
    const deduped = dedupeLifecycleRows(enriched);

    // Effective lifecycle start for each file: prefer server-side metadata,
    // then fall back to the earliest dated row in the group.
    const fileEffectiveStart = {};
    for (const fno of Object.keys(deduped)) {
      const meta = lifecycleMetaFor(fno);
      let metaTs = null;
      if (meta?.effective_start_timestamp) {
        metaTs = parseInt(meta.effective_start_timestamp, 10) * 1000;
      } else if (meta?.commissioning_timestamp) {
        metaTs = parseInt(meta.commissioning_timestamp, 10) * 1000;
      }
      if (metaTs && !isNaN(metaTs)) {
        fileEffectiveStart[fno] = metaTs;
        continue;
      }
      let minTs = Infinity;
      for (const r of deduped[fno]) {
        const ts = getTransactionTimestamp(r);
        if (ts !== null && ts < minTs) minTs = ts;
      }
      fileEffectiveStart[fno] = minTs === Infinity ? Infinity : minTs;
    }

    // Rule 11 (mirror of PHP orderLifecycleFiles): when a CHILD is searched, its PARENT (a
    // predecessor, from window._lsLineage) block renders FIRST, then the searched child, then
    // siblings/successors. Rank: predecessor = 0, searched = 1, other = 2. A parent/mother search
    // has no predecessors, so the searched file stays first. Block order only — the "Last
    // Transaction" field stays scoped to the searched file's own group.
    const predecessorSet = new Set(
      [].concat(window._lsLineage?.previous_file_nos || [],
                (window._lsLineage?.previous_files || []).map(p => p?.file_no))
        .map(n => normalizeLifecycleFileNo(n || ''))
        .filter(Boolean)
    );
    const lifecycleRankOf = (f) => {
      if (f === primaryFileNo) return 1;
      return predecessorSet.has(f) ? 0 : 2;
    };
    // Authoritative block order from the backend (Rule 11: transitive ancestors first). When
    // present it wins; the predecessorSet rank below is only the fallback for files it omits.
    const serverOrder = (window._lsLifecycleOrder || []).map(n => normalizeLifecycleFileNo(n || '')).filter(Boolean);
    const serverIndexOf = (f) => { const i = serverOrder.indexOf(f); return i === -1 ? Number.MAX_SAFE_INTEGER : i; };

    const files = Object.keys(deduped);
    files.sort((a, b) => {
      const ia = serverIndexOf(a);
      const ib = serverIndexOf(b);
      if (ia !== ib) return ia - ib;

      const ra = lifecycleRankOf(a);
      const rb = lifecycleRankOf(b);
      if (ra !== rb) return ra - rb;

      // Main file always precedes the temp variant when both exist.
      if (searchedIsTemp) {
        if (a === mainFileNo) return -1;
        if (b === mainFileNo) return 1;
      }

      const ta = fileEffectiveStart[a] ?? Infinity;
      const tb = fileEffectiveStart[b] ?? Infinity;
      if (ta !== tb) return ta - tb;

      const sa = extractSerialFromFileNumber(a);
      const sb = extractSerialFromFileNumber(b);
      if (sa !== null && sb !== null && sa !== sb) return sa - sb;

      return a.localeCompare(b);
    });

    let result = [];
    for (const fno of files) {
      result = result.concat(arrangeLifecycleFileRows(deduped[fno]));
    }
    return result;
  };

  // ---------------------------------------------------------------------------
  // Root of Title
  // ---------------------------------------------------------------------------
  // The foundational grant the applicant's title traces back to. Two roots are
  // recognised:
  //   • an Occupancy Permit (OP) on the file          → "Occupancy Permit (OP)"
  //   • File Commissioning by Direct Allocation       → "Allocation List"
  // mls_file_no.source (surfaced as _file_commissioning_source) is the authority
  // for the commissioning route: a bare "Direct Allocation" means the file came
  // off an Allocation List, while "OP Direct Allocation" / "OP Resettlement" mean
  // the file was opened on an Occupancy Permit. mls_file_no only covers files
  // commissioned through KLAES, so a legacy file has no route on record at all —
  // there the root is inferred from the earliest State grant instead.
  const ROOT_OF_TITLE_OP = 'Occupancy Permit (OP)';
  const ROOT_OF_TITLE_ALLOCATION = 'Allocation List';

  const commissioningSourceRoot = (src) => {
    const t = String(src || '').trim().toLowerCase().replace(/\s+/g, ' ');
    if (!t) return null;
    if (t === 'direct allocation' || t === 'allocation list') return ROOT_OF_TITLE_ALLOCATION;
    if (t.startsWith('op ') || t === 'op' || t.includes('occupancy permit')) return ROOT_OF_TITLE_OP;
    return null;
  };

  // Rule 3 — "Other File Commissioning": the earliest real instrument executed before
  // the file's final commissioning, as { index, label }, or null when it carries none.
  //
  // "Real" excludes the synthetic rows the timeline builds for itself — commissioning,
  // decommissioning, temporary-file and recertification markers — and the parcel-update
  // rows (subdivision, merger, change of purpose…), which record an administrative
  // reshaping of the parcel rather than a dealing in the title.
  //
  // Timeline order is weight-based, not purely chronological, so "earliest" is decided on
  // the parsed dates rather than on row position.
  const REAL_INSTRUMENT_TABLES = ['pra', 'file_history_staging', 'CofO_staging', 'deed_registrations'];

  // candidatesMustBeOwnFile: false for a Conversion/SLTR, whose earlier dealings sit on
  // the predecessor being converted rather than on the new file. The commissioning
  // cut-off is always taken from THIS file's own commissioning, so relaxing it widens
  // which instruments qualify, never when. Mirrors the PHP of the same name.
  const earliestInstrumentBeforeCommissioning = (rows, belongsToOwnFile, candidatesMustBeOwnFile = true) => {
    // The file's own final commissioning is the cut-off.
    let commissioningTs = null;
    for (const r of rows) {
      if (!r || !r._is_commissioning || !belongsToOwnFile(r)) continue;
      const ts = getTransactionTimestamp(r);
      if (ts !== null && (commissioningTs === null || ts > commissioningTs)) commissioningTs = ts;
    }
    if (commissioningTs === null) return null;

    let bestIndex = -1;
    let bestTs = null;
    for (let i = 0; i < rows.length; i++) {
      const r = rows[i];
      if (!r) continue;
      if (candidatesMustBeOwnFile && !belongsToOwnFile(r)) continue;
      if (!REAL_INSTRUMENT_TABLES.includes(timelineSourceToDbTable(r.source_table))) continue;
      if (isParcelUpdateRow(r)) continue;
      const type = String(getMappedValue(r, 'transactionType') || '').trim();
      if (!type || type === '-') continue;
      const ts = getTransactionTimestamp(r);
      if (ts === null || ts >= commissioningTs) continue;
      if (bestTs === null || ts < bestTs) { bestTs = ts; bestIndex = i; }
    }

    if (bestIndex === -1) return null;

    return {
      index: bestIndex,
      label: toProperCase(getMappedValue(rows[bestIndex], 'transactionType')),
    };
  };

  // A Conversion or SLTR file, judged from its number.
  //
  // These never came off an allocation list: the land was already held under an earlier
  // title and the file was opened to convert or regularise it. So File Commissioning is
  // NOT their root, and labelling it "Allocation List" asserts an allocation that never
  // happened.
  //
  // Read from the number rather than mls_file_no.source, which answers for only a
  // fraction of them (3,127 rows carry source='Conversion' against 44,510 CON- and
  // 6,107 SLTR- files) — the rest reach the legacy branch below.
  //
  // Mirrors LegalSearchService::isConversionOrSltrFileNo(). Keep the two in step.
  const isConversionOrSltrFileNo = (fileNo) => /^(CON|SLTR)[-_/ ]/i.test(String(fileNo || '').trim());

  // The first valid title document on the file: a Right of Occupancy, else a Certificate
  // of Occupancy. Rows arrive in timeline order, so the first match is the earliest.
  const firstTitleDocument = (rows, belongsToOwnFile, mustBeOwnFile = true) => {
    let cofo = -1;
    for (let i = 0; i < rows.length; i++) {
      const r = rows[i];
      if (!r) continue;
      if (mustBeOwnFile && !belongsToOwnFile(r)) continue;
      const type = canonicalWeightingInstrumentType(getMappedValue(r, 'transactionType'));
      if (type === 'right of occupancy') {
        return { index: i, label: toProperCase(getMappedValue(r, 'transactionType')) };
      }
      if (type === 'certificate of occupancy' && cofo === -1) cofo = i;
    }
    return cofo === -1
      ? null
      : { index: cofo, label: toProperCase(getMappedValue(rows[cofo], 'transactionType')) };
  };

  // Root of Title for a Conversion / SLTR file. The order is deliberate and must not be
  // relaxed into a commissioning default:
  //   1. the earliest real instrument executed BEFORE File Commissioning
  //   2. failing that, the first valid title document (RofO, else CofO)
  //   3. failing both, NO root — better silent than claiming an allocation that never was
  //
  // Candidates are not restricted to this file's own lifecycle: a conversion's earlier
  // dealings sit on the file being converted, which is the whole point of a conversion.
  const conversionRootOfTitle = (rows, belongsToOwnFile) =>
    earliestInstrumentBeforeCommissioning(rows, belongsToOwnFile, false)
      || firstTitleDocument(rows, belongsToOwnFile, false);

  // Returns { index, label } for the row the Root of Title comment belongs under,
  // or null when neither root can be established for the searched file.
  const resolveRootOfTitle = (rows) => {
    if (!Array.isArray(rows) || !rows.length) return null;

    // The comment describes the APPLICANT's title, so it is anchored to the searched
    // file's own lifecycle block — an OP sitting on a linked/successor file is that
    // file's root, not this one's.
    // Rows reach here through groupAndSortTimeline(), which has already folded every
    // alias into its owning file's lifecycle — a KANGIS number onto its land file, a
    // "(T)" or SYSTEM temp number onto its base. Read that resolved owner rather than
    // re-deriving one from the row's own columns: extractLifecycleFileNo() answers with
    // the SEARCHED number whenever a row names it, so on a KANGIS search (KN81 →
    // RES-2016-2818) the file's own OP came back as "KN81" while the File Commissioning
    // row came back as "RES-2016-2818", and the OP was judged to be another file's —
    // dropping the Root of Title onto commissioning, where the printed report (which
    // groups first, in LegalSearchService) put it on the OP.
    const lifecycleOf = (r) => normalizeLifecycleFileNo(r?.lifecycle_file_no || '') || extractLifecycleFileNo(r);

    const commIndex = rows.findIndex((r) => r && r._is_commissioning);
    let ownFileNo = commIndex !== -1
      ? lifecycleOf(rows[commIndex])
      : normalizeLifecycleFileNo(
          (userSelectedFileNumber && String(userSelectedFileNumber).trim())
          || window._currentFileNumber
          || window.__lsLastSearchedFileNumber
          || ''
        );
    // A KANGIS alias (e.g. "MLKN 337") is not a lifecycle of its own — the whole timeline
    // renders under the land file it resolves to, so no row carries the searched number
    // and every row would be judged "not this file". Adopt the first row's lifecycle then.
    if (ownFileNo && !rows.some((r) => lifecycleOf(r) === ownFileNo)) {
      ownFileNo = lifecycleOf(rows[0]) || ownFileNo;
    }
    const belongsToOwnFile = (r) => {
      if (!ownFileNo) return true;
      const fn = lifecycleOf(r);
      if (!fn) return false;
      // A SYSTEM temp number ("TEMP-91442") is the placeholder an unlinked OP capture
      // sits on, not a file of its own — such a row only reaches this timeline because
      // it belongs to the searched file's parcel, so it counts as this file's.
      if (isSystemTempFileNo(fn)) return true;
      // A "(T)" temporary number is the same physical file as its permanent twin.
      const strip = (v) => String(v || '').replace(/\(\s*T\s*\)$/i, '').trim();
      return fn === ownFileNo || strip(fn) === strip(ownFileNo);
    };

    // 1. An Occupancy Permit captured on the file roots the title in that OP.
    //    A file can carry more than one OP — a merger registers a permit per source
    //    parcel — and they are jointly the root, so EVERY OP on the file is tagged
    //    rather than only the earliest. With a single OP this is the old behaviour.
    const opIndices = [];
    for (let i = 0; i < rows.length; i++) {
      const r = rows[i];
      if (!r || !belongsToOwnFile(r)) continue;
      if (canonicalWeightingInstrumentType(getMappedValue(r, 'transactionType')) === 'occupancy permit') {
        opIndices.push(i);
      }
    }
    if (opIndices.length) {
      return { index: opIndices[0], indices: opIndices, label: ROOT_OF_TITLE_OP };
    }

    // With no OP on the file the root is not an instrument at all — it is how the FILE
    // itself was opened, so the remark belongs on the File Commissioning row. Only the
    // LABEL is derived below; the anchor is the commissioning row either way.
    let label = null;
    // Where to put the remark if this file has no File Commissioning row of its own
    // (a KANGIS alias, or a file suppressed from commissioning) — better on the grant
    // that established the root than nowhere.
    let fallbackIndex = -1;

    // 2. mls_file_no.source records how the file was commissioned.
    const src = String((selectedFile && selectedFile._file_commissioning_source) || '').trim();
    if (src !== '') {
      label = commissioningSourceRoot(src);
      if (!label) {
        // Rule 3 — "Other File Commissioning". The route is neither allocation nor OP
        // (a Conversion, Re-grant, Subdivision…), so the title did not start at
        // commissioning: the root is the earliest real instrument executed BEFORE the
        // final commissioning, marked on that entry itself.
        return conversionRootOfTitle(rows, belongsToOwnFile);
      }
    } else if (isConversionOrSltrFileNo(ownFileNo)) {
      // A Conversion / SLTR file with no recorded route. The legacy default below would
      // label its File Commissioning "Allocation List" — a root it does not have — so it
      // takes the conversion rule instead, and shows nothing when that finds nothing.
      return conversionRootOfTitle(rows, belongsToOwnFile);
    } else {
      // 3. Nothing recorded at all — the file predates mls_file_no, which holds only files
      // commissioned through KLAES (~4.5% of file_indexings). For those legacy files the
      // root is read off the earliest State grant on the file: a Right of Occupancy (or,
      // failing that, the Certificate of Occupancy) means the land came off an allocation
      // list. Rows arrive in timeline order, so the first match IS the earliest.
      let rofoIndex = -1;
      let cofoIndex = -1;
      for (let i = 0; i < rows.length; i++) {
        const r = rows[i];
        if (!r || !belongsToOwnFile(r)) continue;
        const type = canonicalWeightingInstrumentType(getMappedValue(r, 'transactionType'));
        if (type === 'right of occupancy') { rofoIndex = i; break; }
        if (type === 'certificate of occupancy' && cofoIndex === -1) cofoIndex = i;
      }
      fallbackIndex = rofoIndex !== -1 ? rofoIndex : cofoIndex;
      // A file whose timeline holds nothing but its own File Commissioning row still has
      // a root — the commissioning IS the root. Only give up when there is no grant AND
      // no commissioning row to anchor on, otherwise the remark silently disappears for
      // every legacy file with no dealings captured yet.
      if (fallbackIndex === -1 && commIndex === -1) return null;
      label = ROOT_OF_TITLE_ALLOCATION;
    }

    const index = commIndex !== -1 ? commIndex : fallbackIndex;
    return index !== -1 ? { index, label } : null;
  };

  const rootOfTitleText = (label) => 'Root of Title: ' + label;

  // The File Information card echoes the same comment directly above the File Number,
  // so the root is visible without reading down the timeline.
  const renderRootOfTitleBanner = (label) => {
    const banner = document.getElementById('ls-root-of-title-banner');
    if (!banner) return;
    const text = document.getElementById('ls-root-of-title-banner-text');
    if (!label) {
      banner.classList.add('hidden');
      if (text) text.textContent = '';
      return;
    }
    if (text) text.textContent = rootOfTitleText(label);
    banner.classList.remove('hidden');
  };

  const renderTimeline = async () => {
    let transactions = window._preferredRelatedTransactions || window._allRelatedTransactions || [];
    const timelineTable = document.getElementById('timeline-table');
    if (!timelineTable) return;
    timelineTable.innerHTML = '';

    // First sort chronologically by weight/date across the whole result set, then
    // group by lifecycle owner so each file's commissioning → transactions →
    // decommissioning is displayed as a continuous block.
    transactions = sortTimelineChronologically(transactions);
    // KANGIS Recertification/CoFO pairing is applied per lifecycle transaction
    // phase inside arrangeLifecycleFileRows().
    // A Mortgage always sits directly above the Surrender & Release that discharges it,
    // regardless of transaction date.
    transactions = placeMortgageAboveSurrender(transactions);

    // Synthetic lifecycle rows for the searched file and its successors.
    const commissioningRow = buildCommissioningTimelineRow(); // null when not indexed
    const temporaryFileRow = buildTemporaryFileTimelineRow();
    const decommissioningRow = buildDecommissioningTimelineRow();
    const successorRows = buildSuccessorCommissioningRows();

    transactions = [
      ...transactions,
      ...(commissioningRow ? [commissioningRow] : []),
      ...(temporaryFileRow ? [temporaryFileRow] : []),
      ...(decommissioningRow ? [decommissioningRow] : []),
      ...successorRows,
    ];

    transactions = groupAndSortTimeline(transactions);

    // Root of Title annotation — resolved once against the final, ordered row set so
    // the comment lands under the row the reader actually sees.
    const rootOfTitle = resolveRootOfTitle(transactions);
    // The annotation can sit on several rows (a file with more than one OP); every other
    // rule anchors on exactly one, so a bare {index} is read as a single-row set.
    const rootOfTitleIndices = new Set(
      rootOfTitle ? (Array.isArray(rootOfTitle.indices) ? rootOfTitle.indices : [rootOfTitle.index]) : []
    );
    renderRootOfTitleBanner(rootOfTitle ? rootOfTitle.label : null);

    const timelineTotalCount = document.getElementById('timeline-total-count');
    if (timelineTotalCount) {
      timelineTotalCount.textContent = transactions.length;
    }

    // When every timeline row belongs to the searched file itself — i.e. there are
    // no related/associated file numbers — repeating the same File No on each row is
    // noise, so the whole File No column is hidden (header + cells). The moment any
    // row carries a different file number (merger/subdivision/alias/recert), the
    // column returns so the associations stay visible.
    const _normFN = (str) => String(str || '').toUpperCase().replace(/[\s\-_=\/]+/g, '');
    const _searchedNormFN = _normFN(window.__lsLastSearchedFileNumber || '');
    const _distinctFileNos = new Set();
    let _hasOtherFileNo = false;
    transactions.forEach((item) => {
      const raw = String(getMappedValue(item, 'fileNumber') || '').trim();
      if (!raw || raw === '-') return;
      const n = _normFN(raw);
      if (!n) return;
      _distinctFileNos.add(n);
      if (_searchedNormFN && n !== _searchedNormFN) _hasOtherFileNo = true;
    });
    const hideFileNoCol = _searchedNormFN ? !_hasOtherFileNo : (_distinctFileNos.size <= 1);
    const _fileNoTh = document.querySelector('#timeline-table-wrapper thead th.file-no-col');
    if (_fileNoTh) _fileNoTh.classList.toggle('hidden', hideFileNoCol);

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

    let previousLifecycleFileNo = null;
    transactions.forEach((item, idx) => {
      const currentLifecycleFileNo = item.lifecycle_file_no || extractLifecycleFileNo(item);
      const isFirstRowOfGroup = currentLifecycleFileNo && currentLifecycleFileNo !== previousLifecycleFileNo;
      previousLifecycleFileNo = currentLifecycleFileNo;
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
      // Root of Title rides in this row's Comments cell — it IS a comment on the record,
      // not a record of its own — sitting under any existing comment on the same row.
      const isRootOfTitleRow = !!(rootOfTitle && rootOfTitleIndices.has(idx));
      const rootOfTitleHtml = isRootOfTitleRow
        ? `<div class="ls-root-of-title">${rootOfTitleText(rootOfTitle.label)}</div>` : '';
      // The Instrument/Transaction Type cell already names the record, so the marker
      // there rides inline after the instrument name — "File Commissioning -RoT" —
      // with the full remark as its tooltip.
      const rootOfTitleShortHtml = isRootOfTitleRow
        ? ` <span class="ls-root-of-title ls-root-of-title-inline" title="${rootOfTitleText(rootOfTitle.label)}">-RoT</span>` : '';
      const commentsTitle = isRootOfTitleRow
        ? [comments, rootOfTitleText(rootOfTitle.label)].filter(Boolean).join(' — ') : comments;
      const alreadyCaveated = hasCaveatOnRow(item);

      const row = document.createElement('tr');
      row.dataset.id = item.id;
      row.dataset.table = timelineSourceToDbTable(item.source_table);
      row.dataset.originalIndex = idx;
      const tintClass = sourceRowTintClass(item.source_table);
      if (tintClass) row.classList.add(tintClass);
      if (isFirstRowOfGroup && idx > 0) {
        row.classList.add('ls-group-divider');
      }
      // Rule B: the Weight column reports the key this row was actually sorted on.
      // Floating events (parcel updates, decommissionings) and lineage rows placed by
      // splice have no weight, and must not claim one — a number here next to a row that
      // was never weight-sorted is what made the timeline look mis-sorted.
      const timelineWeight = recordPriorityWeight(item);
      const isFloating = timelineWeight === null;
      const weightDisplay = isFloating ? '—' : timelineWeight;
      const weightTitle = isFloating
        ? (item._pinned ? 'Positioned by file lineage' : 'Floating event — placed chronologically')
        : '';
      const weightColorClass = isFloating ? 'text-gray-400' : 'text-gray-500';

      row.innerHTML = `
        <td class="cleanup-col text-center${cleanupModeActive ? '' : ' hidden'}"><input type="checkbox" class="row-checkbox" data-id="${item.id}" data-table="${timelineSourceToDbTable(item.source_table)}" data-prop-id="${item.prop_id || ''}"></td>
        <td class="arrange-col hidden text-center font-mono text-xs text-gray-400">${idx + 1}</td>
        <td class="text-center text-xs text-gray-500">${idx + 1}</td>
        <td class="file-no-col text-xs text-gray-600 whitespace-nowrap${hideFileNoCol ? ' hidden' : ''}">${renderFileNumberSpan(item, 'fileNumber')}</td>
        <td><span class="source-badge ${sourceBadgeClass(item.source_table)}">${item.source_table}</span></td>
        <td class="text-center text-xs ${weightColorClass}" title="${weightTitle}">${weightDisplay}</td>
        <td>${transType}${rootOfTitleShortHtml}</td>
        <td style="white-space:nowrap;">${party1}</td>
        <td>${party2}</td>
        <td>${party3}</td>
        <td>${regParticulars}</td>
        <td>${date}</td>
        <td>${regTime}</td>
        <td>${regDate}</td>
        <td>${size}</td>
        <td class="${alreadyCaveated ? 'text-red-600 font-medium' : ''}">${item.caveat || '-'}</td>
        <td title="${commentsTitle}" class="${isRootOfTitleRow ? 'ls-comments-cell-root' : ''}" style="max-width:160px;${isRootOfTitleRow ? '' : 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis;'}cursor:default;">${commentsShort}${rootOfTitleHtml}</td>
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

        // Last Paid override — a manual correction to the file_indexings
        // Amount/Date shown in the "Last Paid" card. Only applied while that
        // card is actually the active mode (i.e. the file has a receipted
        // payment on record); the toggle itself always follows file_indexings.
        const grPaid = res.data.ground_rent_paid;
        const grSection = document.getElementById('ground-rent-section');
        if (grSection) {
          if (grPaid && grPaid.amount && grPaid.comment) {
            grSection.dataset.overrideAmount = grPaid.amount;
            grSection.dataset.overrideDate = grPaid.comment;
            if (grSection.dataset.autoAmount && grSection.dataset.autoDate) {
              const amtEl = document.getElementById('file-ground-rent-amount');
              const dateEl = document.getElementById('file-ground-rent-date');
              if (amtEl) amtEl.value = Number(grPaid.amount).toFixed(2);
              if (dateEl) dateEl.value = lsGroundRentDateDisplayValue(grPaid.comment);
            }
          } else {
            delete grSection.dataset.overrideAmount;
            delete grSection.dataset.overrideDate;
          }
        }

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
        const generalInput = document.getElementById('comment-general-text');
        if (generalInput) {
          generalInput.value = res.data.general?.comment ?? '';
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
        // Saved Commencement Date overrides the auto value from the R of O
        // grant date; fall back to auto when this file has none saved.
        const commencementInput = document.getElementById('comment-commencement_date-text');
        if (commencementInput) {
          const savedDate = String(res.data.commencement_date?.comment || '').trim();
          commencementInput.value = savedDate || commencementInput.dataset.autoValue || '';
          lsRecomputeResidualTerm();
        }
      } else {
        document.getElementById('comment-ground_rent-amount').value = '';
        document.getElementById('comment-ground_rent-text').value = '';
        const grSection = document.getElementById('ground-rent-section');
        if (grSection) {
          delete grSection.dataset.overrideAmount;
          delete grSection.dataset.overrideDate;
        }
        const litigationInput = document.getElementById('comment-litigation-text');
        if (litigationInput) litigationInput.value = '';
        const generalInput = document.getElementById('comment-general-text');
        if (generalInput) generalInput.value = '';
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
    // Match the instrument family, not one exact label: "MORTGAGE" / "Deed of
    // Mortgage" / "Tripartite Mortgage" and "SURRENDER AND RELEASE" / "Deed of
    // Surrender and Release" all count. Keying on a fixed "deed of …" prefix
    // silently missed the release rows, so a discharged mortgage still showed the
    // "Under an Active Mortgage" remark. Mirrors LegalSearchService::buildPrintReport().
    const isMortgageType = (t) => (t || '').includes('mortgage');
    const isReleaseType  = (t) => (t || '').includes('surrender') && (t || '').includes('release');

    // Discharge test by COUNT, not mere presence: each Deed of Surrender & Release
    // discharges one mortgage, so the title stays "Under an Active Mortgage" while
    // there are more mortgages than releases on the file. The old presence-only
    // rule (hasMortgage && !hasRelease) wrongly cleared the whole file the moment a
    // single release existed — even when a second, unrelated mortgage (e.g. from a
    // different lender) was never surrendered. Excluded/duplicate rows are already
    // filtered out upstream. Mirrors LegalSearchService::buildPrintReport().
    const mortgageCount = types.filter(isMortgageType).length;
    const releaseCount  = types.filter(isReleaseType).length;
    const mortgageCaveat = mortgageCount > releaseCount;
    const isClear = hasCofo && !hasCaveat && !mortgageCaveat;

    // A record flagged via Title Status Update (title_status = 1, e.g. an
    // initiated Withdrawal/Cancellation/Revocation) means the title is NOT
    // free from encumbrances, regardless of caveat/mortgage state — its
    // title_status_remark (surfaced by the backend in normalizeRow()) takes
    // precedence over the default "free from encumbrances" wording.
    const flaggedTxn = transactions.find(t => Number(t.title_status) === 1);
    const flaggedRemark = flaggedTxn ? String(flaggedTxn.title_status_remark || '').trim() : '';

    let baseText = 'Based on our available records, the title is free from encumbrances.';
    if (hasCaveat && mortgageCaveat) {
        baseText = 'This Property is Under an Active Mortgage and Caveat!!!';
    } else if (hasCaveat) {
        baseText = 'N.B. This Property is Under an Active Caveat!!!';
    } else if (mortgageCaveat) {
        baseText = 'This Property is Under an Active Mortgage !!!';
    }
    if (flaggedTxn) {
        baseText = flaggedRemark || 'N.B. This title is not free from encumbrances.';
    }

    let noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage, hence Certificate of Occupancy is yet to be issued. However the title is free from encumbrances.';
    if (hasCaveat && mortgageCaveat) {
        noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage. This Property is Under an Active Mortgage and Caveat!!!';
    } else if (hasCaveat) {
        noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage. N.B. This Property is Under an Active Caveat!!!';
    } else if (mortgageCaveat) {
        noCofoBase = 'Based on our available records, the subject title is currently at the Letter of Grant stage. This Property is Under an Active Mortgage !!!';
    }
    if (flaggedTxn) {
        noCofoBase = flaggedRemark || 'Based on our available records, the subject title is currently at the Letter of Grant stage. N.B. This title is not free from encumbrances.';
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

    // No CoFO Remark only applies while the title is still at the Letter of
    // Grant stage — once a Certificate of Occupancy transaction exists on the
    // file, this card no longer applies.
    const noCofoSection = document.getElementById('no-cofo-comment-section');
    if (noCofoSection) noCofoSection.classList.toggle('hidden', hasCofo);

    // W/R/C remark editor is only revealed for files tagged [WRC] in
    // duplicate_fileno (i.e. Withdrawn / Revoked / Cancelled).
    const wrcSection = document.getElementById('wrc-comment-section');
    if (wrcSection) wrcSection.classList.toggle('hidden', !window._isWrcFile);

    // Encumbrance Remark section: hidden when the title is clear (no caveat,
    // no unresolved mortgage, no flagged title status) — nothing to remark on.
    const hasEncumbrance = hasCaveat || mortgageCaveat || !!flaggedTxn;
    const encSection = document.getElementById('encumbrance-comment-section');
    if (encSection) encSection.classList.toggle('hidden', !hasEncumbrance);

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

  // Ground Rent "Last Paid" — Edit removes `readonly` from the existing
  // Amount/Date inputs in place; Save persists the correction as a
  // 'ground_rent_paid' comment override and restores read-only display.
  document.getElementById('edit-ground-rent-btn')?.addEventListener('click', () => {
    lsSetGroundRentPaidReadOnly(false);
    document.getElementById('file-ground-rent-amount')?.focus();
  });

  document.getElementById('save-ground-rent-paid-btn')?.addEventListener('click', () => {
    const fileNumber = window._currentFileNumber || document.getElementById('file-number-value')?.textContent?.trim();
    const statusEl = document.getElementById('ground-rent-status');
    const setStatus = (msg, ok) => {
      if (!statusEl) return;
      statusEl.textContent = msg;
      statusEl.classList.remove('hidden', 'text-red-600', 'text-green-600');
      statusEl.classList.add(ok ? 'text-green-600' : 'text-red-600');
      if (ok) setTimeout(() => statusEl.classList.add('hidden'), 3000);
    };
    if (!fileNumber) return setStatus('No file selected.', false);

    const amount = document.getElementById('file-ground-rent-amount')?.value || '';
    const dateVal = document.getElementById('file-ground-rent-date')?.value || '';
    if (!amount || !dateVal) return setStatus('Amount and date are required.', false);

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
        comment_type: 'ground_rent_paid',
        amount: amount,
        comment: dateVal
      })
    })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const section = document.getElementById('ground-rent-section');
        if (section) {
          section.dataset.overrideAmount = amount;
          section.dataset.overrideDate = dateVal;
        }
        lsSetGroundRentPaidReadOnly(true);
      }
      setStatus(res.success ? 'Saved successfully.' : (res.message || 'Failed to save.'), !!res.success);
    })
    .catch(() => setStatus('Network error.', false));
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
      const proceed = await swalConfirm('Drop selected records from their prop_id group?\n\nEach dropped record will be detached and allocated a NEW prop_id — it will no longer share history with this file.', 'Confirm Drop', 'warning');
      if (!proceed) return;

      let totalAffected = 0;
      for (const [table, ids] of Object.entries(byTable)) {
        const resp = await cleanupAjax('/propid-master/drop-reallocate', { table, ids });
        if (resp && resp.data && typeof resp.data.affected === 'number') totalAffected += resp.data.affected;
      }
      await swalAlert(`${totalAffected} record(s) dropped. A new prop_id was allocated to each.`, 'success', 'Completed');
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
  @include('legal_search.partials.record_edit_modal_js')

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

  function setDigitalArchiveStatus(hasFiles) {
    const cardEl = document.getElementById('digital-archive-status-card');
    const headingEl = document.getElementById('digital-archive-status-heading');
    if (cardEl) {
      cardEl.classList.remove('bg-green-50', 'border-green-200', 'bg-red-50', 'border-red-200');
      cardEl.classList.add(...(hasFiles ? ['bg-green-50', 'border-green-200'] : ['bg-red-50', 'border-red-200']));
    }
    if (headingEl) {
      headingEl.classList.remove('text-green-700', 'text-red-700');
      headingEl.classList.add(hasFiles ? 'text-green-700' : 'text-red-700');
    }
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
      setDigitalArchiveStatus(false);
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
        setDigitalArchiveStatus(false);
        return;
      }

      setDigitalArchiveStatus(true);
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
      setDigitalArchiveStatus(false);
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
            const orderSpec = encodeRowKeySpec(tlSrc);
            if (orderSpec) q.set('timeline_order', orderSpec);
          }
        } catch (_e) { /* non-fatal: server falls back to default order */ }
        // Pass the dedupe-excluded rows (the "Excluded / Duplicate Records" panel)
        // so the printed report hides exactly what the on-screen timeline hides.
        try {
          const exSet = Array.isArray(window._excludedRelatedTransactions) ? window._excludedRelatedTransactions : [];
          const exSpec = encodeRowKeySpec(exSet, { requireId: true });
          if (exSpec) q.set('excluded_keys', exSpec);
        } catch (_e) { /* non-fatal */ }
        // Include any active File Information overrides
        const _ov = window._fileInfoOverrides || {};
        if (_ov.fileTitle)            q.set('display_file_title',    _ov.fileTitle);
        if (_ov.district && _ov.lga)  q.set('display_district_lga', _ov.district + ', ' + _ov.lga);
        else if (_ov.district)        q.set('display_district_lga', _ov.district);
        else if (_ov.lga)             q.set('display_district_lga', _ov.lga);
        if (_ov.landUse)              q.set('display_land_use',      _ov.landUse);
        if (_ov.size)                 q.set('display_size',          _ov.size);
        // Residual Term + Commencement Date — whatever is in the Residual Term
        // editor (auto-calculated or user-entered) prints on the report.
        const _rt = (document.getElementById('residual-term-input')?.value || '').trim();
        if (_rt) q.set('display_residual_term', _rt);
        const _cdInput = document.getElementById('comment-commencement_date-text');
        const _cd = (_cdInput?.value || '').trim();
        if (_cd) q.set('display_commencement_date', _cd);
        // Only label the source when this is the untouched auto-filled value —
        // once the operator overrides it manually, the source no longer applies.
        if (_cd && _cd === (_cdInput?.dataset.autoValue || '') && _cdInput?.dataset.autoSource) {
          q.set('display_commencement_source', _cdInput.dataset.autoSource);
        }
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
            const orderSpec2 = encodeRowKeySpec(tlSrc2);
            if (orderSpec2) q.set('timeline_order', orderSpec2);
          }
        } catch (_e) { /* non-fatal */ }
        // Mirror the print-report handler: hide the dedupe-excluded duplicates.
        try {
          const exSet2 = Array.isArray(window._excludedRelatedTransactions) ? window._excludedRelatedTransactions : [];
          const exSpec2 = encodeRowKeySpec(exSet2, { requireId: true });
          if (exSpec2) q.set('excluded_keys', exSpec2);
        } catch (_e) { /* non-fatal */ }
        // Include any active File Information overrides
        const _ov2 = window._fileInfoOverrides || {};
        if (_ov2.fileTitle)       q.set('display_file_title',    _ov2.fileTitle);
        if (_ov2.district && _ov2.lga) q.set('display_district_lga', _ov2.district + ', ' + _ov2.lga);
        else if (_ov2.district)   q.set('display_district_lga', _ov2.district);
        else if (_ov2.lga)        q.set('display_district_lga', _ov2.lga);
        if (_ov2.landUse)         q.set('display_land_use',      _ov2.landUse);
        if (_ov2.size)            q.set('display_size',          _ov2.size);
        // Residual Term + Commencement Date — mirrors the print-report handler above.
        const _rt2 = (document.getElementById('residual-term-input')?.value || '').trim();
        if (_rt2) q.set('display_residual_term', _rt2);
        const _cdInput2 = document.getElementById('comment-commencement_date-text');
        const _cd2 = (_cdInput2?.value || '').trim();
        if (_cd2) q.set('display_commencement_date', _cd2);
        if (_cd2 && _cd2 === (_cdInput2?.dataset.autoValue || '') && _cdInput2?.dataset.autoSource) {
          q.set('display_commencement_source', _cdInput2.dataset.autoSource);
        }
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

  // ── Add Record: New / Existing ───────────────────────────────────────────────
  // The trigger opens the chooser card. BOTH options then open the same Add
  // Property Record card via the existing [data-role="legal-search-add-record"]
  // capture handler in file-history.blade.php — they differ only in what the
  // operator is capturing. That card carries the "Existing Records for this File"
  // table (PRA / File History / Deeds Registration / CofO) and the duplicate flag,
  // so neither path can add a record blind to what the file already holds.

  const closeAddRecordMenu = () => {
    const modal = document.getElementById('add-record-choice-modal');
    if (modal) modal.classList.add('hidden');
  };

  document.addEventListener('click', (e) => {
    const modal = document.getElementById('add-record-choice-modal');
    if (!modal) return;

    if (e.target.closest('#add-record-smart-btn')) {
      e.preventDefault();
      modal.classList.remove('hidden');
      return;
    }

    if (e.target.closest('#add-record-choice-close')
      || e.target.closest('#add-record-choice-cancel')
      || e.target.closest('#add-record-choice-backdrop')) {
      closeAddRecordMenu();
      return;
    }

    // Either option dismisses the chooser; the capture-phase handler opens the card.
    if (e.target.closest('.add-record-option')) {
      closeAddRecordMenu();
    }
  });

  // Close modal when pressing Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      closeAddRecordMenu();
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




