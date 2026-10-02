// Fixed JavaScript for Legal Search - Pattern Recognition Fix: 2025-01-08 23:30:00
<script>
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
  const toggleFiltersBtn = document.getElementById('toggle-filters-btn');
  const filtersContainer = document.getElementById('filters-container');
  const collapsedFilters = document.getElementById('collapsed-filters');
  const resetSearchBtn = document.getElementById('reset-search-btn');
  const resetSearchCollapsedBtn = document.getElementById('reset-search-collapsed-btn');
  const addFilterBtn = document.getElementById('add-filter-btn');
  const filterDropdown = document.getElementById('filter-dropdown');
  const searchLoading = document.getElementById('search-loading');
  const noResultsMessage = document.getElementById('no-results-message');
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

  // Debug statements
  console.log("Search modal element:", searchModal);
  console.log("Search records button:", searchRecordsBtn);
  console.log("Search modal class list:", searchModal ? searchModal.classList : "Modal not found");

  // Add document ready event to ensure DOM is fully loaded
  document.addEventListener('DOMContentLoaded', () => {
    console.log("DOM fully loaded, initializing search elements");
    
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
  });

  // State variables
  let currentView = 'table';
  let selectedFile = null;
  let transactionToDelete = null;
  let searchResults = [];
  let filtersCollapsed = false;

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

  // Toggle filters
  if (toggleFiltersBtn) {
    toggleFiltersBtn.addEventListener('click', () => {
      filtersCollapsed = !filtersCollapsed;
      if (filtersCollapsed) {
        filtersContainer.classList.add('hidden');
        collapsedFilters.classList.remove('hidden');
        toggleFiltersBtn.textContent = 'Expand Filters';
      } else {
        filtersContainer.classList.remove('hidden');
        collapsedFilters.classList.add('hidden');
        toggleFiltersBtn.textContent = 'Collapse Filters';
      }
    });
  }

  // Reset search
  const resetSearch = () => {
    document.getElementById('fileNumber').value = '';
    document.getElementById('guarantorName').value = '';
    document.getElementById('guaranteeName').value = '';
    
    // Reset any other filters that might be added
    const additionalFilters = document.querySelectorAll('.additional-filter');
    additionalFilters.forEach(filter => {
      filter.remove();
    });
    
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
    
    // Reset selected file
    selectedFile = null;
  };

  if (resetSearchBtn) {
    resetSearchBtn.addEventListener('click', resetSearch);
  }
  if (resetSearchCollapsedBtn) {
    resetSearchCollapsedBtn.addEventListener('click', resetSearch);
  }

  // Toggle filter dropdown
  if (addFilterBtn) {
    addFilterBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      filterDropdown.classList.toggle('hidden');
    });
  }

  // Close filter dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (addFilterBtn && filterDropdown && !addFilterBtn.contains(e.target) && !filterDropdown.contains(e.target)) {
      filterDropdown.classList.add('hidden');
    }
  });

  // Add filter when clicking on dropdown item
  if (filterDropdown) {
    filterDropdown.addEventListener('click', (e) => {
      if (e.target.hasAttribute('data-filter')) {
        const filterId = e.target.getAttribute('data-filter');
        addFilter(filterId);
        filterDropdown.classList.add('hidden');
      }
    });
  }

  // Add a new filter to the filters container
  const addFilter = (filterId) => {
    // Check if filter already exists
    if (document.getElementById(filterId)) {
      return;
    }

    const filterLabels = {
      newKangisFileNo: 'New KANGIS File No.',
      guarantorName: 'Guarantor Name',
      guaranteeName: 'Guarantee Name',
      lga: 'LGA',
      district: 'District',
      location: 'Location',
      plotNumber: 'Plot Number',
      planNumber: 'Plan Number',
      size: 'Size',
      caveat: 'Caveat'
    };

    const filterDiv = document.createElement('div');
    filterDiv.className = 'flex items-center gap-2 mb-2 additional-filter';
    filterDiv.id = filterId + '-filter';

    if (filterId === 'lga' || filterId === 'caveat') {
      // Create select for LGA or Caveat
      const options = filterId === 'lga' 
        ? ['Dala', 'Fagge', 'Gwale', 'Kano Municipal', 'Nassarawa', 'Tarauni', 'Ungogo']
        : ['Yes', 'No'];
      
      filterDiv.innerHTML = `
        <span class="badge badge-outline">${filterLabels[filterId]}</span>
        <div class="select-wrapper flex-grow">
          <select id="${filterId}" class="select">
            <option value="">Select ${filterLabels[filterId]}</option>
            ${options.map(opt => `<option value="${opt}">${opt}</option>`).join('')}
          </select>
          <div class="select-icon">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </div>
        </div>
      `;
    } else {
      // Create input for other filters
      filterDiv.innerHTML = `
        <span class="badge badge-outline">${filterLabels[filterId]}</span>
        <input type="text" id="${filterId}" placeholder="Enter ${filterLabels[filterId].toLowerCase()}" class="flex-grow px-3 py-2 border border-gray-300 rounded-md">
      `;
    }

    // Add remove button
    const removeBtn = document.createElement('button');
    removeBtn.className = 'h-8 w-8 rounded-full flex items-center justify-center text-gray-500 hover:bg-gray-100';
    removeBtn.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    `;
    removeBtn.addEventListener('click', () => {
      filterDiv.remove();
      performSearch();
    });

    filterDiv.appendChild(removeBtn);
    filtersContainer.insertBefore(filterDiv, addFilterBtn.parentNode);

    // Add event listener to the new input/select
    const input = document.getElementById(filterId);
    if (input.tagName === 'SELECT') {
      input.addEventListener('change', performSearch);
    } else {
      input.addEventListener('input', performSearch);
    }
  };

  // Perform search based on filter values
  const performSearch = () => {
    // Get all filter values
    const filters = {
      fileNumber: document.getElementById('fileNumber').value,
      guarantorName: document.getElementById('guarantorName').value,
      guaranteeName: document.getElementById('guaranteeName').value
    };

    // Add any additional filters
    const additionalFilters = document.querySelectorAll('.additional-filter');
    additionalFilters.forEach(filter => {
      const input = filter.querySelector('input, select');
      if (input && input.value) {
        filters[input.id] = input.value;
      }
    });

    // Check if at least one search parameter has a value
    const hasSearchCriteria = Object.values(filters).some(value => value && value.trim() !== '');
    
    if (!hasSearchCriteria) {
      searchResults = [];
      resultsCount.textContent = '0';
      tableResults.classList.add('hidden');
      cardResults.classList.add('hidden');
      noResultsMessage.classList.add('hidden');
      return;
    }

    // Show loading
    searchLoading.classList.remove('hidden');
    tableResults.classList.add('hidden');
    cardResults.classList.add('hidden');
    noResultsMessage.classList.add('hidden');
    fileDetailsView.classList.add('hidden');

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

    // AJAX call to the server
    $.ajax({
      url: '{{ route("onpremise.search") }}',
      type: 'POST',
      data: searchData,
      success: function(data) {
        // Hide loading
        searchLoading.classList.add('hidden');

        // Combine results from the 3 specified tables only
        searchResults = [
          ...data.property_records,
          ...data.registered_instruments,
          ...data.cofo
        ];

        console.log('=== HIERARCHICAL SEARCH RESULTS ===');
        console.log('Property records:', data.property_records.length);
        console.log('Registered instruments:', data.registered_instruments.length);
        console.log('CofO records:', data.cofo.length);
        console.log('Total combined results:', searchResults.length);
        console.log('Sample results:', searchResults.slice(0, 3));

        // Update results count
        resultsCount.textContent = searchResults.length;

        // Show appropriate view
        if (searchResults.length === 0) {
          noResultsMessage.classList.remove('hidden');
        } else {
          // Update active filters summary
          const activeFilters = Object.entries(filters)
            .filter(([_, value]) => value && value.trim() !== '')
            .map(([key, value]) => {
              const filterLabels = {
                fileNumber: 'File Number',
                guarantorName: 'Guarantor Name',
                guaranteeName: 'Guarantee Name',
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
    if (!value || value === 'N/A') return value;
    
    // Convert to string if it's a number
    let stringValue = value.toString();
    
    // Remove .0 from the end if present
    if (stringValue.endsWith('.0')) {
      stringValue = stringValue.replace('.0', '');
    }
    
    return stringValue;
  };

  // Helper function to identify file number type by pattern
  const identifyFileNumberType = (value) => {
    if (!value || value === 'N/A' || value === null || value === undefined) {
      return 'unknown';
    }
    
    const cleanValue = cleanNumericValue(value.toString().trim());
    
    // ST File Number patterns: ST-RES-2024-01-001, ST-COM-2024-02-002, ST-IND-2024-03-009
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i.test(cleanValue)) {
      return 'st';
    }
    
    // Parent File Number (NP) patterns: ST-RES-2024-01, ST-COM-2024-02, ST-IND-2024-03
    if (/^ST-(RES|COM|IND|AG)-\d{4}-\d+$/i.test(cleanValue)) {
      return 'parent';
    }
    
    // MLS File Number patterns: COM-2022-572, RES-2023-145, CON-COM-2024-089, CON-IND-42154, etc.
    if (/^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d{4}-\d+$/i.test(cleanValue) ||
        /^(COM|RES|IND|AG|CON-COM|CON-RES|CON-AG|CON-IND)-\d+$/i.test(cleanValue)) {
      return 'mls';
    }
    
    // KANGIS File Number patterns: KNML 00001, MNKL 02500, MLKN 00567, KNGP 01234
    if (/^[A-Z]{4}\s?\d{5}$/i.test(cleanValue)) {
      return 'kangis';
    }
    
    // New KANGIS File Number patterns: KN1586, KN0001, KN2345
    if (/^KN\d{4}$/i.test(cleanValue)) {
      return 'new_kangis';
    }
    
    return 'unknown';
  };

  // Helper function to extract correct file numbers from a file record - UPDATED WITH PATTERN RECOGNITION
  const extractFileNumbers = (file) => {
    console.log('Extracting file numbers from:', file);
    
    const result = {
      st: 'N/A',
      parent: 'N/A', 
      mls: 'N/A',
      kangis: 'N/A',
      new_kangis: 'N/A'
    };
    
    // Collect all possible file number values from the record
    const allPossibleValues = [
      file.STFileNo, file.StFileNo, file.st_file_no, file.sub_fileno,
      file.ParentFileNo, file.parent_fileNo, file.np_fileno, file.mother_np_fileno,
      file.MLSFileNo, file.mlsFNo, file.fileNo, file.fileno, file.mother_fileno,
      file.KANGISFileNo, file.kangisFileNo, file.KAGISFileNO,
      file.NewKANGISFileNo, file.NewKANGISFileno, file.new_kangis_file_no
    ].filter(val => val && val !== 'N/A' && val !== null && val !== undefined);
    
    console.log('All possible file number values:', allPossibleValues);
    
    // Categorize each value by its pattern
    allPossibleValues.forEach(value => {
      const cleanValue = cleanNumericValue(value);
      const type = identifyFileNumberType(cleanValue);
      
      console.log(`Value: ${cleanValue}, Type: ${type}`);
      
      // Only assign if we haven't found a value for this type yet
      if (type !== 'unknown' && result[type] === 'N/A') {
        result[type] = cleanValue;
      }
    });
    
    console.log('Final extracted file numbers:', result);
    return result;
  };

  // Render search results based on current view
  const renderSearchResults = () => {
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
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'grantor'))}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'grantee'))}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'lga'))}</td>
        <td class="p-2 text-sm">${file.property_house_no && file.property_plot_no && file.property_street_name && file.property_district && file.property_lga ? toProperCase(`${file.property_house_no},${file.property_plot_no},${file.property_street_name},${file.property_district},${file.property_lga}`) : toProperCase(getMappedValue(file, 'location'))}</td>
        <td class="p-2 text-sm">${getMappedValue(file, 'plotNo')}</td>
        <td class="p-2 text-sm">${toProperCase(getMappedValue(file, 'transactionType'))}</td>
        <td class="p-2 text-sm">${getMappedValue(file, 'size')}</td>
        <td class="p-2 text-sm font-medium ${file.caveat === 'Yes' ? 'text-red-600' : ''}">${file.caveat || 'N/A'}</td>
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
    
    // Add event listeners to view buttons
    document.querySelectorAll('.view-file-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const index = parseInt(btn.getAttribute('data-index'));
        console.log('View button clicked for index:', index);
        
        selectedFile = searchResults[index];
        console.log('Selected file:', selectedFile);
        
        // Close search modal
        searchModal.classList.add('hidden');
        
        // Show file history view directly instead of file details
        dashboardView.classList.add('hidden');
        fileHistoryView.classList.remove('hidden');
        
        // Populate file details
        renderFileHistory();
      });
    });
  };

  // Helper function to get mapped field value
  const getMappedValue = (item, fieldType) => {
    const fieldMappings = {
      // Date fields - enhanced for applications
      date: [
        'transaction_date', 'deeds_date', 'certificateDate', 
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
    return 'N/A';
  };

  // Helper function to convert text to proper case
  const toProperCase = (text) => {
    if (!text || text === 'N/A') return text;
    return text.toString().toLowerCase().replace(/\b\w/g, l => l.toUpperCase());
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
    
    // Update file reference in subtitle (with .0 fix)
    let fileRef = fileNumbers.mls !== 'N/A' ? fileNumbers.mls : (selectedFile.mlsFNo || selectedFile.MLSFileNo || selectedFile.fileNo || selectedFile.fileno || 'N/A');
    document.getElementById('file-reference').textContent = fileRef;
    
    // Update file information fields (with .0 fix and better field mapping)
    document.getElementById('file-number-value').textContent = fileNumbers.mls;
    document.getElementById('kangis-file-number-value').textContent = fileNumbers.kangis;
    document.getElementById('new-kangis-file-number-value').textContent = fileNumbers.new_kangis;
    
    // Enhanced guarantor/guarantee mapping for mother and sub applications
    const guarantorValue = selectedFile.owner_fullname || selectedFile.mother_owner_fullname || 
                         selectedFile.first_name || selectedFile.corporate_name || 
                         selectedFile.multiple_owners_names || selectedFile.Assignor || 
                         selectedFile.Grantor || selectedFile.Mortgagor || selectedFile.Lessor || 
                         selectedFile.Surrenderor || selectedFile.originalAllottee || 'N/A';
    
    const guaranteeValue = selectedFile.sub_owner_fullname || selectedFile.multiple_owners_names || 
                         selectedFile.owner_fullname || selectedFile.Assignee || 
                         selectedFile.Grantee || selectedFile.Mortgagee || selectedFile.Lessee || 
                         selectedFile.Surrenderee || selectedFile.currentAllottee || 'N/A';
    
    document.getElementById('current-guarantor-value').textContent = guarantorValue;
    document.getElementById('current-guarantee-value').textContent = guaranteeValue;
    
    // Enhanced LGA mapping
    const lgaValue = selectedFile.property_lga || selectedFile.address_lga || 
                    selectedFile.lgsaOrCity || selectedFile.lga || selectedFile.lgaName || 'N/A';
    document.getElementById('lga-value').textContent = lgaValue;
    
    // Enhanced district mapping
    const districtValue = selectedFile.property_district || selectedFile.address_district || 
                         selectedFile.district || selectedFile.districtName || 'N/A';
    document.getElementById('district-value').textContent = districtValue;
    
    // Enhanced property type mapping - prioritize land_use
    const propertyTypeValue = selectedFile.land_use || selectedFile.landUse || 
                             selectedFile.landUseType || selectedFile.title_type || 
                             selectedFile.instrument_type || selectedFile.Type || 
                             selectedFile.residential_type || selectedFile.commercial_type || 
                             selectedFile.industrial_type || selectedFile.mixed_type || 'N/A';
    document.getElementById('property-type-value').textContent = propertyTypeValue;
    
    // Enhanced last transaction mapping
    const lastTransactionValue = selectedFile.transaction_type || selectedFile.instrument_type || 
                                selectedFile.application_status || selectedFile.deeds_status || 
                                selectedFile.planning_recommendation_status || 'N/A';
    document.getElementById('last-transaction-value').textContent = lastTransactionValue;
    
    // Render the transactions tables
    renderTransactionTables();
    
    // Default to property history tab
    switchTab('property-history');
  };

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

  // Render all transaction tables - UPDATED FOR HIERARCHICAL SEARCH
  const renderTransactionTables = () => {
    // Get related transactions for the selected file
    const relatedTransactions = getRelatedTransactions(selectedFile);
    
    console.log('Rendering transaction tables with:', relatedTransactions);
    
    // Separate records by their record_type field (added by backend)
    const propertyRecords = relatedTransactions.filter(item => 
      item.record_type === 'property_records'
    );
    
    const instrumentRecords = relatedTransactions.filter(item => 
      item.record_type === 'registered_instruments'
    );
    
    const cofoRecords = relatedTransactions.filter(item => 
      item.record_type === 'CofO'
    );
    
    console.log('Property records:', propertyRecords.length);
    console.log('Instrument records:', instrumentRecords.length);
    console.log('CofO records:', cofoRecords.length);
    
    // Property History (only property_records table)
    const propertyHistoryTable = document.getElementById('property-history-table');
    propertyHistoryTable.innerHTML = '';
    
    if (propertyRecords.length > 0) {
      propertyRecords.forEach(item => {
        console.log('Processing property record:', item);
        
        const date = getMappedValue(item, 'date');
        const transactionType = toProperCase(getMappedValue(item, 'transactionType'));
        const grantor = toProperCase(getMappedValue(item, 'grantor'));
        const grantee = toProperCase(getMappedValue(item, 'grantee'));
        const serialNo = getMappedValue(item, 'serialNo');
        const pageNo = getMappedValue(item, 'pageNo');
        const volumeNo = getMappedValue(item, 'volumeNo');
        const size = getMappedValue(item, 'size');
        const comments = toProperCase(getMappedValue(item, 'comments'));
        
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>
            <div>${date}</div>
          </td>
          <td>${transactionType}</td>
          <td>${grantor}</td>
          <td>${grantee}</td>
          <td>${cleanNumericValue(serialNo)}/${cleanNumericValue(pageNo)}/${cleanNumericValue(volumeNo)}</td>
          <td>${size}</td>
          <td class="${item.caveat === 'Yes' ? 'text-red-600 font-medium' : ''}">${item.caveat || 'N/A'}</td>
          <td>${comments}</td>
          <td>
            <div class="flex space-x-2">
              <button class="edit-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
        `;
        propertyHistoryTable.appendChild(row);
      });
    } else {
      propertyHistoryTable.innerHTML = `
        <tr>
          <td colspan="9" class="text-center py-4 text-gray-500">No property history records found.</td>
        </tr>
      `;
    }
    
    // Instrument Registration (only registered_instruments table) - ENHANCED FOR ST FRAGMENTATION
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
        const grantor = toProperCase(getMappedValue(registration, 'grantor'));
        const grantee = toProperCase(getMappedValue(registration, 'grantee'));
        
        // Fix Registration Particulars to show full format (Serial/Page/Volume)
        const serialNo = getMappedValue(registration, 'serialNo');
        const pageNo = getMappedValue(registration, 'pageNo');
        const volumeNo = getMappedValue(registration, 'volumeNo');
        const regNumber = `${cleanNumericValue(serialNo)}/${cleanNumericValue(pageNo)}/${cleanNumericValue(volumeNo)}`;
        
        // Enhanced logging for ST Fragmentation records
        if (transactionType.toLowerCase().includes('fragmentation') || 
            transactionType.toLowerCase().includes('st fragmentation')) {
          console.log('*** ST FRAGMENTATION RECORD FOUND ***');
          console.log('Transaction Type:', transactionType);
          console.log('Date:', date);
          console.log('Grantor:', grantor);
          console.log('Grantee:', grantee);
          console.log('Registration Number:', regNumber);
        }
        
        const row = document.createElement('tr');
        // Add special styling for ST Fragmentation records
        const isSTFragmentation = transactionType.toLowerCase().includes('fragmentation') || 
                                 transactionType.toLowerCase().includes('st fragmentation');
        
        if (isSTFragmentation) {
          row.className = 'bg-yellow-50 border-l-4 border-l-yellow-400';
        }
        
        row.innerHTML = `
          <td>
            <div>${date}</div>
            <div class="text-xs text-gray-600">${time}</div>
          </td>
          <td class="${isSTFragmentation ? 'font-semibold text-yellow-800' : ''}">${transactionType}</td>
          <td>${regNumber}</td>
          <td>${grantor} to ${grantee}</td>
          <td>${toProperCase(registration.registered_by_name || 'N/A')}</td>
          <td>
            <div class="flex space-x-2">
              <button class="edit-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
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
          <td colspan="6" class="text-center py-4 text-gray-500">No instrument registration records found.</td>
        </tr>
      `;
    }
    
    // Certificate of Occupancy (only CofO table)
    const cofoTable = document.getElementById('cofo-table');
    cofoTable.innerHTML = '';
    
    if (cofoRecords.length > 0) {
      cofoRecords.forEach(cofo => {
        const serialNo = getMappedValue(cofo, 'serialNo');
        const pageNo = getMappedValue(cofo, 'pageNo');
        const volumeNo = getMappedValue(cofo, 'volumeNo');
        const date = getMappedValue(cofo, 'date');
        const grantee = toProperCase(getMappedValue(cofo, 'grantee'));
        const landUse = toProperCase(getMappedValue(cofo, 'landUse'));
        
        const row = document.createElement('tr');
        row.innerHTML = `
          <td>${cleanNumericValue(serialNo)}/${cleanNumericValue(pageNo)}/${cleanNumericValue(volumeNo)}</td>
          <td>
            <div>${date}</div>
          </td>
          <td>${grantee}</td>
          <td>${landUse}</td>
          <td>${cofo.Period || cofo.term || cofo.occupancy || 'N/A'}</td>
          <td>
            <div class="flex space-x-2">
              <button class="edit-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
              </button>
              <button class="delete-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 6h18"></path>
                  <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"></path>
                  <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
              </button>
            </div>
          </td>
        `;
        cofoTable.appendChild(row);
      });
    } else {
      cofoTable.innerHTML = `
        <tr>
          <td colspan="6" class="text-center py-4 text-gray-500">No Certificate of Occupancy records found.</td>
        </tr>
      `;
    }
  };
  
  // Switch between tabs in the file details view
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
  };

  // Back to dashboard from file history view
  const backToDashboardBtn = document.getElementById('back-to-dashboard-btn');
  if (backToDashboardBtn) {
    backToDashboardBtn.addEventListener('click', () => {
      fileHistoryView.classList.add('hidden');
      dashboardView.classList.remove('hidden');
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
    const relatedTransactions = getRelatedTransactions(selectedFile);

    // Helper to get Registration Particulars for each transaction
    function getRegistrationParticulars(transaction) {
      // property_records table
      if (
        transaction.hasOwnProperty('serialNo') &&
        transaction.hasOwnProperty('pageNo') &&
        transaction.hasOwnProperty('volumeNo')
      ) {
        return `${cleanNumericValue(transaction.serialNo)}/${cleanNumericValue(transaction.pageNo)}/${cleanNumericValue(transaction.volumeNo)}`;
      }
      // registered_instruments table
      if (
        transaction.hasOwnProperty('instrument_type') ||
        transaction.hasOwnProperty('rootRegistrationNumber')
      ) {
        return `${cleanNumericValue(transaction.volume_no)}/${cleanNumericValue(transaction.page_no)}/${cleanNumericValue(transaction.serial_no)}`;
      }
      return 'N/A/N/A/N/A';
    }

    // Create combined array of all transactions
    const allTransactions = [];

    relatedTransactions.forEach(transaction => {
      let regNo = getRegistrationParticulars(transaction);
      
      allTransactions.push({
        date: transaction.transaction_date || transaction.deeds_date || transaction.certificateDate || transaction.approval_date || 'N/A',
        time: transaction.deeds_time || generateRandomTime(),
        transactionType: transaction.transaction_type || transaction.instrument_type || transaction.title_type || 'Record',
        grantor: transaction.owner_fullname || transaction.mother_owner_fullname || transaction.Assignor || transaction.Grantor || transaction.originalAllottee || transaction.first_name || transaction.corporate_name || 'N/A',
        grantee: transaction.sub_owner_fullname || transaction.multiple_owners_names || transaction.Assignee || transaction.Grantee || transaction.currentAllottee || 'N/A',
        regNo: regNo,
        size: transaction.size || transaction.plot_size || 'N/A',
        caveat: transaction.caveat === 'Yes' ? 'Yes' : 'NO',
        comments: transaction.comments || transaction.additional_comments || 'N/A',
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
    if (fileNumbers.st !== 'N/A' && 
        fileNumbers.st.match(/^ST-(RES|COM|IND|AG)-\d{4}-\d+-\d+$/i) && 
        !isPrimaryFileSearch) {
      fileNumbersDisplay += `  |  Unit Filno: ${fileNumbers.st}`;
    }
    
    fileNumbersDisplay += `  |  MLPP File No: ${fileNumbers.mls}  |  KANGIS File No: ${fileNumbers.kangis}  |  New KANGIS: ${fileNumbers.new_kangis}`;
    
    document.getElementById('report-file-numbers').textContent = fileNumbersDisplay;
    document.getElementById('report-plot-number').textContent = selectedFile.plot_no || selectedFile.plotNo || "GP No. 1067/1 & 1067/2";
    document.getElementById('report-plan-number').textContent = selectedFile.planNumber || "LKN/RES/2021/3006";
    document.getElementById('report-plot-description').textContent = `${selectedFile.district || selectedFile.districtName || "Niger Street Nassarawa District"}, ${selectedFile.lgsaOrCity || selectedFile.lga || selectedFile.lgaName || "Nassarawa"} LGA`;
    
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
        <td class="border border-gray-300 px-3 py-2">${transaction.grantor}</td>
        <td class="border border-gray-300 px-3 py-2">${transaction.grantee}</td>
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
  document.querySelectorAll('[data-view]').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('[data-view]').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentView = tab.getAttribute('data-view');
      renderSearchResults();
    });
  });

  // Add input event listeners for search fields
  const fileNumberInput = document.getElementById('fileNumber');
  const guarantorNameInput = document.getElementById('guarantorName');
  const guaranteeNameInput = document.getElementById('guaranteeName');
  
  if (fileNumberInput) {
    fileNumberInput.addEventListener('input', performSearch);
  }
  if (guarantorNameInput) {
    guarantorNameInput.addEventListener('input', performSearch);
  }
  if (guaranteeNameInput) {
    guaranteeNameInput.addEventListener('input', performSearch);
  }

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
      // Show legal search report view
      fileHistoryView.classList.add('hidden');
      legalSearchReportView.classList.remove('hidden');
      
      // Render the legal search report
      renderLegalSearchReport();
    }

    // Back to file details from legal search report view
    if (e.target.closest('#back-to-file-details-btn')) {
      legalSearchReportView.classList.add('hidden');
      fileHistoryView.classList.remove('hidden');
    }

    // Print report button - ENHANCED WITH WATERMARK FIX
    if (e.target.closest('#print-report-btn')) {
      // Ensure watermark is visible before printing
      const watermark = document.querySelector('.watermark');
      if (watermark) {
        watermark.style.display = 'block';
        watermark.style.visibility = 'visible';
        watermark.style.opacity = '1';
        watermark.style.position = 'fixed';
        watermark.style.zIndex = '1000';
        watermark.style.color = 'rgba(200, 200, 200, 0.3)';
        watermark.style.fontSize = '60px';
        watermark.style.fontWeight = 'bold';
        watermark.style.fontFamily = 'Arial Black, Arial, sans-serif';
        watermark.style.textTransform = 'uppercase';
        watermark.style.letterSpacing = '3px';
        watermark.style.top = '50%';
        watermark.style.left = '50%';
        watermark.style.transform = 'translate(-50%, -50%) rotate(-45deg)';
        watermark.style.whiteSpace = 'nowrap';
        watermark.style.pointerEvents = 'none';
      }
      
      // Add a small delay to ensure styles are applied
      setTimeout(() => {
        window.print();
      }, 100);
    }

    // Delete and edit action buttons (placeholder functionality)
    if (e.target.closest('.delete-action')) {
      alert('Delete functionality would be implemented here.');
    }
    
    if (e.target.closest('.edit-action')) {
      alert('Edit functionality would be implemented here.');
    }
  });

  // Close modal when pressing Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (searchModal) {
        searchModal.classList.add('hidden');
      }
    }
  });
</script>
