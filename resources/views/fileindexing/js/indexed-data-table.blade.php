  // Indexed files DataTable and selection helpers
  let indexedTableEventsBound = false;

  function updateIndexedEmptyState() {
    const tableContainer = document.getElementById('indexed-table-container');
    const emptyState = document.getElementById('indexed-empty-state');

    const hasData = (() => {
      if (indexedDataTable && typeof indexedDataTable.page === 'function') {
        const info = indexedDataTable.page.info();
        return info && info.recordsDisplay > 0;
      }
      return Array.isArray(indexedFiles) && indexedFiles.length > 0;
    })();

    if (tableContainer) {
      tableContainer.style.display = hasData ? 'block' : 'none';
    }
    if (emptyState) {
      emptyState.style.display = hasData ? 'none' : 'block';
    }
  }

  function syncIndexedSelectionStyles() {
    const table = document.getElementById('indexed-files-table');
    if (!table) {
      return;
    }

    table.querySelectorAll('tbody tr').forEach(row => {
      const fileId = row.getAttribute('data-id');
      if (!fileId) {
        return;
      }

      if (selectedIndexedFiles.includes(fileId)) {
        row.classList.add('bg-blue-50');
      } else {
        row.classList.remove('bg-blue-50');
      }
    });
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) {
      return '';
    }

    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function buildIndexedStatusBadge(row) {
    // A decommissioned file is no longer deleted from file_indexings — it stays listed
    // and must read as decommissioned, never as a healthy green "Indexed". The reason
    // and successor ride along in the tooltip so staff can see what replaced it.
    if (Number(row.is_decommissioned) === 1) {
      const reason = row.decommissioning_reason || 'Decommissioned';
      const successor = row.successor_file_no
        ? ` — replaced by ${row.successor_file_no}`
        : '';
      return `
        <span class="badge badge-red" title="${escapeHtml(reason + successor)}">
          <i data-lucide="archive" class="h-3 w-3 mr-1 inline"></i>
          Decommissioned
        </span>
      `;
    }

    const statusText = row.status || row.source || 'Indexed';
    return `
      <span class="badge badge-green">
        <i data-lucide="check" class="h-3 w-3 mr-1 inline"></i>
        ${escapeHtml(statusText)}
      </span>
    `;
  }

  function buildIndexedActionsMenu(row) {
    const fileIdStr = String(row.id ?? '');
    const allowTracking = !!row.batch_generated;
    const trackingClass = allowTracking ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed opacity-50';
    const trackingDisabled = allowTracking ? '' : 'disabled';

    return `
      <div class="relative inline-block text-left">
        <button type="button" class="actions-dropdown-btn inline-flex items-center justify-center w-8 h-8 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" data-file-id="${fileIdStr}">
          <i data-lucide="more-vertical" class="h-4 w-4"></i>
        </button>
        <div class="actions-dropdown-menu hidden absolute right-0 z-10 mt-2 w-48 origin-top-right bg-white rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" data-file-id="${fileIdStr}">
          <div class="py-1">
            <button class="view-file-btn block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-file-id="${fileIdStr}">
              <i data-lucide="eye" class="h-4 w-4 mr-2 inline"></i>
              View Details
            </button>
            <button class="edit-file-btn block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-file-id="${fileIdStr}">
              <i data-lucide="edit" class="h-4 w-4 mr-2 inline"></i>
              Edit Record
            </button>
            <button class="print-tracking-btn block w-full text-left px-4 py-2 text-sm ${trackingClass}" data-file-id="${fileIdStr}" ${trackingDisabled}>
              <i data-lucide="printer" class="h-4 w-4 mr-2 inline"></i>
              View Tracking Sheet
            </button>
            <div class="border-t border-gray-100 my-1"></div>
            <button class="delete-file-btn block w-full text-left px-4 py-2 text-sm text-gray-400 cursor-not-allowed opacity-50" data-file-id="${fileIdStr}" disabled aria-disabled="true" title="Deletion disabled">
              <i data-lucide="trash-2" class="h-4 w-4 mr-2 inline text-gray-400"></i>
              Delete Record
            </button>
          </div>
        </div>
      </div>
    `;
  }

  function initializeIndexedFilesDataTable() {
    if (indexedDataTableInitialized) {
      return;
    }

    indexedTableElement = document.getElementById('indexed-files-table');
    if (!indexedTableElement) {
      console.warn('Indexed files table element not found');
      return;
    }

    const isVisible = indexedTableElement.offsetParent !== null;
    if (!isVisible) {
      console.warn('Table not visible, delaying initialization');
      return;
    }

    if (typeof window.$ === 'undefined' || typeof window.$.fn === 'undefined' || typeof window.$.fn.DataTable === 'undefined') {
      console.warn('DataTables plugin not available; using manual renderer');
      renderIndexedFiles();
      return;
    }

    if (typeof window.fileIndexingDataTableUrl === 'undefined') {
      console.error('DataTable URL not set');
      return;
    }

    try {
      indexedDataTable = window.$(indexedTableElement).DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        autoWidth: false,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[10, 'desc']],
        searchDelay: 350,
        ajax: {
          url: window.fileIndexingDataTableUrl,
          type: 'GET',
          data: function (data) {
            indexedFilesLastParams = {
              search: data.search?.value || '',
              page: Math.floor((data.start || 0) / (data.length || 25)) + 1
            };
          },
          dataSrc: function (json) {
            const rows = json?.data || [];
            indexedFiles = rows;
            indexedFilesMap = new Map(rows.map(item => [String(item.id), item]));
            updateIndexedEmptyState();
            return rows;
          },
          error: function (xhr) {
            console.error('Indexed files DataTable error:', xhr.status, xhr.statusText);
            hideSearchSpinner('indexed');
          }
        },
        columns: [
          { data: 'tracking_id', defaultContent: '-', render: (_, __, row) => escapeHtml(row.tracking_id ?? '-') || '-' },
          { data: 'shelf_location', defaultContent: '-', render: (_, __, row) => escapeHtml(row.shelf_location ?? '-') || '-' },
          { data: 'registry', defaultContent: '-', render: (_, __, row) => escapeHtml(row.registry ?? '-') || '-' },
          { data: 'registry_batch_no', defaultContent: '-', render: (_, __, row) => escapeHtml(row.registry_batch_no ?? '-') || '-' },
          { data: 'sys_batch_no', defaultContent: '-', render: (_, __, row) => escapeHtml(row.sys_batch_no ?? '-') || '-' },
          { data: 'mdc_batch_no', defaultContent: '-', render: (_, __, row) => escapeHtml(row.mdc_batch_no ?? row.batch_no ?? '-') || '-' },
          { data: 'group_no', defaultContent: '-', render: (_, __, row) => escapeHtml(row.group_no ?? row.group ?? '-') || '-' },
          { data: 'fileNumber', defaultContent: '-', render: (_, __, row) => escapeHtml(row.fileNumber ?? '-') || '-' },
          { data: 'name', defaultContent: '-', render: (_, __, row) => escapeHtml(row.name ?? '-') || '-' },
          { data: 'plotNumber', defaultContent: '-', render: (_, __, row) => escapeHtml(row.plotNumber ?? row.plot_number ?? '-') || '-' },
          { data: 'indexed_at', defaultContent: '-', render: (_, __, row) => escapeHtml(row.indexed_at ?? row.date ?? '-') || '-' },
          { data: 'indexed_by', defaultContent: '-', render: (_, __, row) => escapeHtml(row.indexed_by ?? '-') || '-' },
          { data: 'tpNumber', defaultContent: '-', render: (_, __, row) => escapeHtml(row.tpNumber ?? '-') || '-' },
          { data: 'lpknNumber', defaultContent: '-', render: (_, __, row) => escapeHtml(row.lpknNumber ?? '-') || '-' },
          { data: 'landUseType', defaultContent: '-', render: (_, __, row) => escapeHtml(row.landUseType ?? '-') || '-' },
          { data: 'district', defaultContent: '-', render: (_, __, row) => escapeHtml(row.district ?? '-') || '-' },
          { data: 'lga', defaultContent: '-', render: (_, __, row) => escapeHtml(row.lga ?? '-') || '-' },
          { data: 'source', defaultContent: '-', orderable: false, render: (_, __, row) => buildIndexedStatusBadge(row) },
          { data: null, orderable: false, searchable: false, render: (_, __, row) => buildIndexedActionsMenu(row) }
        ],
        createdRow: function (row, data) {
          row.setAttribute('data-id', String(data.id));
          if (selectedIndexedFiles.includes(String(data.id))) {
            row.classList.add('bg-blue-50');
          }
        },
        drawCallback: function () {
          const api = this.api();
          const pageInfo = api.page.info();
          currentIndexedPage = pageInfo.page + 1;
          updateSearchCount('indexed', pageInfo.recordsDisplay);
          hideSearchSpinner('indexed');
          syncIndexedSelectionStyles();
          bindIndexedTableInteractions();
          if (typeof lucide !== 'undefined' && lucide.createIcons) {
            lucide.createIcons();
          }
        },
        preDrawCallback: function () {
          showSearchSpinner('indexed');
        }
      });

      window.$(indexedTableElement).on('xhr.dt', function () {
        updateCounters();
      });

      indexedDataTableInitialized = true;

      if (pendingIndexedSearchTerm) {
        indexedDataTable.search(pendingIndexedSearchTerm).draw();
        pendingIndexedSearchTerm = '';
      }
    } catch (error) {
      console.error('DataTable initialization failed:', error);
      indexedDataTableInitialized = false;
    }
  }

  function bindIndexedTableInteractions() {
    if (indexedTableEventsBound) {
      return;
    }

    const table = document.getElementById('indexed-files-table');
    if (!table) {
      return;
    }

    const tbody = table.querySelector('tbody');
    if (!tbody) {
      return;
    }

    tbody.addEventListener('click', function (event) {
      const dropdownButton = event.target.closest('.actions-dropdown-btn');
      if (dropdownButton) {
        event.preventDefault();
        event.stopPropagation();
        const fileId = dropdownButton.getAttribute('data-file-id');
        table.querySelectorAll('.actions-dropdown-menu').forEach(menu => {
          if (menu.getAttribute('data-file-id') === fileId) {
            menu.classList.toggle('hidden');
          } else {
            menu.classList.add('hidden');
          }
        });
        return;
      }

      const viewBtn = event.target.closest('.view-file-btn');
      if (viewBtn) {
        event.preventDefault();
        event.stopPropagation();
        const fileId = viewBtn.getAttribute('data-file-id');
        const file = indexedFilesMap.get(String(fileId));
        if (file) {
          alert(`File Details:\n\nFile Number: ${file.fileNumber}\nName: ${file.name}\nRegistry: ${file.registry || '-'}\nSys Batch No: ${file.sys_batch_no || '-'}\nMDC Batch No: ${file.mdc_batch_no || file.batch_no || '-'}\nGroup: ${file.group_no || file.group || '-'}\nTracking ID: ${file.tracking_id || '-'}\nType: ${file.type || '-'}\nPlot Number: ${file.plotNumber || '-'}\nTP Number: ${file.tpNumber || '-'}\nIndexed Date: ${file.indexed_at || file.date || '-'}\nIndexed By: ${file.indexed_by || '-'}\nLocation: ${file.location || '-'}\nDistrict: ${file.district || '-'}\nLand Use: ${file.landUseType || '-'}\nLPKN Number: ${file.lpknNumber || '-'}`);
        }
        return;
      }

      const editBtn = event.target.closest('.edit-file-btn');
      if (editBtn) {
        event.preventDefault();
        event.stopPropagation();
        const fileId = editBtn.getAttribute('data-file-id');
        if (!/^\d+$/.test(fileId)) {
          alert('This is a demo file. Editing is not available for demo files.');
          return;
        }
        window.open(`/fileindexing/${fileId}/edit`, '_blank');
        return;
      }

      const trackingBtn = event.target.closest('.print-tracking-btn');
      if (trackingBtn) {
        event.preventDefault();
        event.stopPropagation();
        if (trackingBtn.hasAttribute('disabled')) {
          const fileId = trackingBtn.getAttribute('data-file-id');
          const file = indexedFilesMap.get(String(fileId));
          if (file && file.batch_generated) {
            const batchInfo = file.last_batch_id ? ` (Batch: ${file.last_batch_id})` : '';
            alert(`This file has already been used in batch generation${batchInfo}. Individual tracking sheets cannot be generated for files already included in batch tracking.`);
          }
          return;
        }

        const fileId = trackingBtn.getAttribute('data-file-id');
        if (!/^\d+$/.test(fileId)) {
          alert('This is a demo file. Tracking sheet preview is not available for demo files.');
          return;
        }
        window.open(`/fileindexing/tracking-sheet/${fileId}`, '_blank');
        return;
      }

      const deleteBtn = event.target.closest('.delete-file-btn');
      if (deleteBtn) {
        event.preventDefault();
        event.stopPropagation();
        const fileId = deleteBtn.getAttribute('data-file-id');
        if (!/^\d+$/.test(fileId)) {
          alert('This is a demo file. Deletion is not available for demo files.');
          return;
        }
        if (confirm('Are you sure you want to delete this file indexing record? This action cannot be undone.')) {
          deleteIndexedFile(fileId);
        }
        return;
      }

      const row = event.target.closest('tr');
      if (row && !event.target.closest('button') && !event.target.closest('input')) {
        const fileId = row.getAttribute('data-id');
        if (fileId) {
          toggleIndexedFileSelection(fileId);
        }
      }
    });

    document.addEventListener('click', function (event) {
      if (!event.target.closest('.actions-dropdown-btn') && !event.target.closest('.actions-dropdown-menu')) {
        table.querySelectorAll('.actions-dropdown-menu').forEach(menu => menu.classList.add('hidden'));
      }
    });

    indexedTableEventsBound = true;
  }

  function toggleIndexedFileSelection(fileId) {
    const idStr = String(fileId);

    if (selectedIndexedFiles.includes(idStr)) {
      selectedIndexedFiles = selectedIndexedFiles.filter(id => id !== idStr);
    } else {
      selectedIndexedFiles.push(idStr);
    }

    syncIndexedSelectionStyles();
    updateSelectedIndexedFilesCount();
    updateTrackingButton();
  }

  window.toggleIndexedFileSelection = toggleIndexedFileSelection;

  async function select100Files() {
    const button = document.getElementById('select-100-btn');
    if (!button) {
      return;
    }

    const originalText = button.textContent;

    try {
      button.disabled = true;
      button.innerHTML = '<i class="animate-spin h-3 w-3 mr-1" style="border: 2px solid #ffffff; border-top: 2px solid transparent; border-radius: 50%; display: inline-block;"></i>Loading...';
      button.classList.add('opacity-75');

      const fileIds = await getFileIdsForSelection(100);
      if (fileIds.length === 0) {
        alert('No indexed files available to select.');
        return;
      }

      selectedIndexedFiles = fileIds.map(id => String(id));
      syncIndexedSelectionStyles();
      updateSelectedIndexedFilesCount();
      updateTrackingButton();

      const message = fileIds.length < 100
        ? `Selected all ${fileIds.length} available files (less than 100 files available)`
        : 'Selected first 100 files';
      showSelectionNotification(message);
    } catch (error) {
      console.error('Error selecting 100 files:', error);
      if (error.message.includes('timeout')) {
        alert('Request timed out while loading files. Please try again or contact support.');
      } else {
        alert('Error loading files for selection. Please try again.');
      }
    } finally {
      button.disabled = false;
      button.textContent = originalText;
      button.classList.remove('opacity-75');
    }
  }

  async function select200Files() {
    const button = document.getElementById('select-200-btn');
    if (!button) {
      return;
    }

    const originalText = button.textContent;

    try {
      button.disabled = true;
      button.innerHTML = '<i class="animate-spin h-3 w-3 mr-1" style="border: 2px solid #ffffff; border-top: 2px solid transparent; border-radius: 50%; display: inline-block;"></i>Loading...';
      button.classList.add('opacity-75');

      const fileIds = await getFileIdsForSelection(200);
      if (fileIds.length === 0) {
        alert('No indexed files available to select.');
        return;
      }

      selectedIndexedFiles = fileIds.map(id => String(id));
      syncIndexedSelectionStyles();
      updateSelectedIndexedFilesCount();
      updateTrackingButton();

      const message = fileIds.length < 200
        ? `Selected all ${fileIds.length} available files (less than 200 files available)`
        : 'Selected first 200 files';
      showSelectionNotification(message);
    } catch (error) {
      console.error('Error selecting 200 files:', error);
      if (error.message.includes('timeout')) {
        alert('Request timed out while loading files. Please try again or contact support.');
      } else {
        alert('Error loading files for selection. Please try again.');
      }
    } finally {
      button.disabled = false;
      button.textContent = originalText;
      button.classList.remove('opacity-75');
    }
  }

  async function getFileIdsForSelection(count) {
    try {
      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Request timeout')), 10000);
      });

      const fetchPromise = fetch(`/fileindexing/api/indexed-files?per_page=${count}&fields=id_only`);
      const response = await Promise.race([fetchPromise, timeoutPromise]);
      const data = await response.json();

      if (data.success && data.indexed_files) {
        return data.indexed_files.map(file => file.id);
      }

      console.error('Error fetching file IDs:', data.message);
      return indexedFiles.slice(0, count).map(file => file.id);
    } catch (error) {
      if (error.message === 'Request timeout') {
        console.error('Request timed out while fetching file IDs.');
        throw new Error('Request timed out. The server may be slow.');
      }

      console.error('Error fetching file IDs:', error);
      return indexedFiles.slice(0, count).map(file => file.id);
    }
  }

  function showSelectionNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg z-50 transition-opacity duration-300';
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
      notification.style.opacity = '0';
      setTimeout(() => {
        if (notification.parentElement) {
          notification.parentElement.removeChild(notification);
        }
      }, 300);
    }, 3000);
  }

  window.select100Files = select100Files;
  window.select200Files = select200Files;

  function getCachedResponse(key) {
    const cached = apiCache.get(key);
    if (!cached) {
      return null;
    }

    const now = Date.now();
    if (now - cached.timestamp > API_CACHE_TTL) {
      apiCache.delete(key);
      return null;
    }

    return cached.data;
  }

  function setCachedResponse(key, data) {
    if (apiCache.size >= MAX_CACHE_SIZE) {
      const firstKey = apiCache.keys().next().value;
      apiCache.delete(firstKey);
    }

    apiCache.set(key, {
      data,
      timestamp: Date.now()
    });
  }

  function clearApiCache() {
    apiCache.clear();
  }

  async function toggleSelectAllIndexed() {
    const selectAllCheckbox = document.getElementById('select-all-indexed-checkbox');
    if (!selectAllCheckbox) {
      return;
    }

    const selectAllLabel = document.querySelector('label[for="select-all-indexed-checkbox"]');
    const originalLabelText = selectAllLabel ? selectAllLabel.textContent : 'Select All';

    try {
      if (selectAllCheckbox.checked) {
        if (selectAllLabel) {
          selectAllLabel.innerHTML = '<i class="animate-spin h-3 w-3 mr-1" style="border: 2px solid #666; border-top: 2px solid transparent; border-radius: 50%; display: inline-block;"></i>Loading...';
        }
        selectAllCheckbox.disabled = true;

        try {
          const fileIds = await getFileIdsForSelection(500);
          const availableFileIds = fileIds.filter(id => {
            const file = indexedFiles.find(f => String(f.id) === String(id));
            return !file || !file.batch_generated;
          });

          selectedIndexedFiles = availableFileIds.map(id => String(id));

          const excludedCount = fileIds.length - availableFileIds.length;
          if (excludedCount > 0) {
            if (availableFileIds.length >= 500) {
              showSelectionNotification(`Selected first 500 available files (${excludedCount} files excluded - already used in batch generation)`);
            } else {
              showSelectionNotification(`Selected ${availableFileIds.length} files (${excludedCount} files excluded - already used in batch generation)`);
            }
          } else {
            if (fileIds.length >= 500) {
              showSelectionNotification('Selected first 500 files (maximum for Select All to ensure performance)');
            } else {
              showSelectionNotification(`Selected all ${availableFileIds.length} files`);
            }
          }
        } catch (error) {
          console.log('Falling back to current page selection due to:', error.message);
          const availableFiles = indexedFiles.filter(file => !file.batch_generated);
          selectedIndexedFiles = availableFiles.map(file => String(file.id));
          const excludedCount = indexedFiles.length - availableFiles.length;
          if (excludedCount > 0) {
            showSelectionNotification(`Selected ${availableFiles.length} files from current page (${excludedCount} files excluded - already used in batch generation)`);
          } else {
            showSelectionNotification(`Selected ${availableFiles.length} files from current page (server timeout - try 100/200 buttons for larger selections)`);
          }
        }
      } else {
        selectedIndexedFiles = [];
      }

      renderIndexedFiles();
      updateSelectedIndexedFilesCount();
      updateTrackingButton();
    } catch (error) {
      console.error('Error in toggleSelectAllIndexed:', error);
      if (selectAllCheckbox.checked) {
        selectedIndexedFiles = indexedFiles.map(file => String(file.id));
      } else {
        selectedIndexedFiles = [];
      }

      renderIndexedFiles();
      updateSelectedIndexedFilesCount();
      updateTrackingButton();
    } finally {
      selectAllCheckbox.disabled = false;
      if (selectAllLabel) {
        selectAllLabel.textContent = originalLabelText;
      }
    }
  }

  window.toggleSelectAllIndexed = toggleSelectAllIndexed;

  function updateSelectedIndexedFilesCount() {
    const selectedCountElement = document.getElementById('selected-indexed-files-count');
    if (selectedCountElement) {
      selectedCountElement.textContent = `${selectedIndexedFiles.length} selected`;
    }
  }

  function updateTrackingButton() {
    const trackingBtn = document.getElementById('generate-tracking-sheets-btn');
    const trackingBtnText = document.getElementById('tracking-btn-text');
    const trackingBtnIcon = trackingBtn ? trackingBtn.querySelector('i') : null;

    if (!trackingBtn || !trackingBtnText) {
      return;
    }

    function resetClick() {
      trackingBtn.onclick = null;
    }

    const selectableFileCount = selectedNotGeneratedFiles.length;
    const totalSelectableFiles = notGeneratedFiles.length;

    if (selectableFileCount >= 2) {
      let buttonText = 'Generate Batch Tracking Sheets';
      if (selectableFileCount === 100) {
        buttonText = 'Generate Batch Tracking Sheets (100)';
      } else if (selectableFileCount === 200) {
        buttonText = 'Generate Batch Tracking Sheets (200)';
      } else if (selectableFileCount > 200) {
        buttonText = `Generate Batch Tracking Sheets (${selectableFileCount})`;
      }

      trackingBtnText.textContent = buttonText;
      trackingBtn.removeAttribute('disabled');
      trackingBtn.classList.remove('opacity-50', 'cursor-not-allowed');
      trackingBtn.classList.add('btn-primary');
      if (trackingBtnIcon) {
        trackingBtnIcon.setAttribute('data-lucide', 'file-check');
      }

      resetClick();
      trackingBtn.onclick = (event) => {
        event.preventDefault();
        event.stopPropagation();
        generateBatchTrackingSheets();
      };
    } else {
      let disabledReason = '';
      if (totalSelectableFiles === 0) {
        disabledReason = ' (No files available for tracking)';
      } else {
        disabledReason = ' (Select at least 2 files)';
      }

      trackingBtnText.textContent = `Batch Tracking Sheets${disabledReason}`;
      trackingBtn.setAttribute('disabled', 'true');
      trackingBtn.classList.add('opacity-50', 'cursor-not-allowed');
      trackingBtn.classList.remove('btn-primary');
      if (trackingBtnIcon) {
        trackingBtnIcon.setAttribute('data-lucide', 'file-text');
      }

      resetClick();
    }

    if (typeof lucide !== 'undefined' && lucide.createIcons) {
      lucide.createIcons();
    }
  }

  function hasTrackingRecord(fileId) {
    return true;
  }

  function generateSingleTrackingSheet() {
    if (selectedIndexedFiles.length === 0) {
      alert('Select a file to generate a tracking sheet.');
      return;
    }

    const selectedFile = indexedFiles.find(file => String(file.id) === selectedIndexedFiles[0]);
    if (!selectedFile) {
      alert('Unable to locate selected file.');
      return;
    }

    let fileId = String(selectedFile.id);
    if (!/^\d+$/.test(fileId)) {
      alert('This is a demo file. Tracking sheet generation is not available for demo files.');
      return;
    }

    const trackingUrl = `/fileindexing/tracking-sheet/${fileId}`;
    window.open(trackingUrl, '_blank');
  }

  window.generateSingleTrackingSheet = generateSingleTrackingSheet;

  function openSmartBatchInterface() {
    if (selectedNotGeneratedFiles.length < 1) {
      alert('Please select at least one file for batch tracking operations.');
      return;
    }

    const fileCount = selectedNotGeneratedFiles.length;
    const message = `Opening batch tracking interface for ${fileCount} selected file${fileCount > 1 ? 's' : ''}.\n\nAfter generating the tracking sheets, return to this tab to see the updated file status.`;

    if (confirm(message)) {
      const fileIds = selectedNotGeneratedFiles.join(',');
      const batchInterfaceUrl = `/fileindexing/batch-tracking-interface?files=${fileIds}`;
      window.open(batchInterfaceUrl, '_blank');
    }
  }

  window.openSmartBatchInterface = openSmartBatchInterface;

  function generateBatchTrackingSheets() {
    openSmartBatchInterface();
  }

  window.generateBatchTrackingSheets = generateBatchTrackingSheets;

  function printTrackingSheet(fileId) {
    const printUrl = `/fileindexing/print-tracking-sheet/${fileId}`;
    window.open(printUrl, '_blank');
  }

  window.printTrackingSheet = printTrackingSheet;

  function addActionMenuListeners() {
    document.querySelectorAll('.action-menu-btn').forEach(btn => {
      btn.addEventListener('click', function (event) {
        event.stopPropagation();
        const dropdown = this.nextElementSibling;

        document.querySelectorAll('.action-dropdown').forEach(other => {
          if (other !== dropdown) {
            other.classList.add('hidden');
          }
        });

        dropdown.classList.toggle('hidden');
      });
    });

    document.querySelectorAll('.view-file-btn').forEach(btn => {
      btn.addEventListener('click', function (event) {
        event.stopPropagation();
        const fileId = this.dataset.fileId;
        const file = indexedFiles.find(f => f.id === fileId);
        if (file) {
          alert(`File Details:\n\nFile Number: ${file.fileNumber}\nName: ${file.name}\nBatch NO: ${file.batch_no || '-'}\nTracking ID: ${file.tracking_id || '-'}\nType: ${file.type}\nDistrict: ${file.district}\nLand Use: ${file.landUseType}\nDate: ${file.date}`);
        }
        this.closest('.action-dropdown').classList.add('hidden');
      });
    });

    document.querySelectorAll('.generate-tracking-btn').forEach(btn => {
      btn.addEventListener('click', function (event) {
        event.stopPropagation();
        const fileId = this.dataset.fileId;

        if (!/^\d+$/.test(fileId)) {
          alert('This is a demo file. Tracking sheet generation is not available for demo files.');
          this.closest('.action-dropdown').classList.add('hidden');
          return;
        }

        const trackingUrl = `/fileindexing/tracking-sheet/${fileId}`;
        window.open(trackingUrl, '_blank');
        this.closest('.action-dropdown').classList.add('hidden');
      });
    });

    document.querySelectorAll('.print-tracking-btn').forEach(btn => {
      btn.addEventListener('click', function (event) {
        event.stopPropagation();
        const fileId = this.dataset.fileId;

        if (!/^\d+$/.test(fileId)) {
          alert('This is a demo file. Print tracking is not available for demo files.');
          this.closest('.action-dropdown').classList.add('hidden');
          return;
        }

        printTrackingSheet(fileId);
        this.closest('.action-dropdown').classList.add('hidden');
      });
    });

    document.addEventListener('click', function () {
      document.querySelectorAll('.action-dropdown').forEach(dropdown => {
        dropdown.classList.add('hidden');
      });
    });
  }

  window.addActionMenuListeners = addActionMenuListeners;
