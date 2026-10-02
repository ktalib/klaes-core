/**
 * File Indexing - Indexed Files Module
 * Handles rendering, selection, and management of indexed files DataTable
 */

import { state } from './state.js';
import * as domUtils from './dom-utils.js';
import * as api from './api-utils.js';

const DEFAULT_PAGE_LENGTH = 20;
const SEARCH_DEBOUNCE_MS = 300;

let searchDebounceHandle = null;
let usingFallbackRenderer = false;

// ============================================
// RENDERING FUNCTIONS
// ============================================

/**
 * Render indexed files using DataTable
 */
export async function renderIndexedFiles() {
  const tableElement = document.getElementById('indexed-files-table');

  if (!tableElement) {
    console.warn('Indexed files table element not found');
    return;
  }

  if (state.indexedDataTableInitialized && state.indexedDataTable) {
    state.indexedDataTable.ajax.reload(null, false);
    return;
  }

  if (tableElement.offsetParent === null) {
    requestAnimationFrame(renderIndexedFiles);
    return;
  }

  if (!window.$ || !window.$.fn || !window.$.fn.DataTable) {
    console.warn('DataTables plugin is unavailable, using legacy indexed files renderer.');
    usingFallbackRenderer = true;
    await renderIndexedFilesLegacy(state.pendingIndexedSearchTerm || '');
    return;
  }

  initializeIndexedFilesDataTable(tableElement);
}

function initializeIndexedFilesDataTable(tableElement) {
  state.indexedTableElement = tableElement;

  const pageLength = state.itemsPerPage || DEFAULT_PAGE_LENGTH;

  const dataTable = window.$(tableElement).DataTable({
    processing: true,
    serverSide: true,
    deferRender: true,
    autoWidth: false,
    pageLength,
    lengthMenu: [
      [20, 50, 100],
      [20, 50, 100],
    ],
    order: [[9, 'desc']],
    searchDelay: SEARCH_DEBOUNCE_MS,
    ajax: {
      url: window.fileIndexingDataTableUrl,
      type: 'GET',
      data: function (params) {
        const start = params.start || 0;
        const length = params.length || pageLength;

        state.indexedFilesLastParams = {
          search: params.search?.value || '',
          page: Math.floor(start / length) + 1,
          length,
        };

        state.itemsPerPage = length;
      },
      dataSrc: function (json) {
        if (json && json.error) {
          api.showApiErrorNotification(json.error);
        }

        const rows = Array.isArray(json?.data) ? json.data : [];

        state.indexedFiles = rows;
        state.indexedFilesMap = new Map(rows.map((item) => [String(item.id), item]));

        state.selectedIndexedFiles = [];
        updateSelectedIndexedFilesCount();

        updateEmptyState(rows.length > 0);
        usingFallbackRenderer = false;
        setSearchSpinnerVisible(false);

        return rows;
      },
      error: function (xhr) {
        setSearchSpinnerVisible(false);
        updateEmptyState(false);
        api.showApiErrorNotification('Unable to load indexed files right now.');
        console.error('Indexed files DataTable error:', xhr.status, xhr.statusText);
      },
    },
    columns: [
      { data: 'shelf_location', defaultContent: '-', render: renderPlainCell },
      { data: 'registry', defaultContent: '-', render: renderPlainCell },
      { data: 'registry_batch_no', defaultContent: '-', render: renderPlainCell },
      { data: 'sys_batch_no', defaultContent: '-', render: renderPlainCell },
      { data: 'mdc_batch_no', defaultContent: '-', render: renderPlainCell },
      { data: 'group_no', defaultContent: '-', render: renderPlainCell },
      { data: 'fileNumber', defaultContent: '-', render: renderPlainCell },
      { data: 'name', defaultContent: '-', render: renderPlainCell },
      { data: 'plotNumber', defaultContent: '-', render: renderPlainCell },
      {
        data: 'indexed_at',
        defaultContent: '-',
        render: function (_, __, row) {
          return escapeHtml(row.indexed_at || row.date || '-');
        },
      },
      { data: 'indexed_by', defaultContent: '-', render: renderPlainCell },
      { data: 'tpNumber', defaultContent: '-', render: renderPlainCell },
      { data: 'lpknNumber', defaultContent: '-', render: renderPlainCell },
      { data: 'landUseType', defaultContent: '-', render: renderPlainCell },
      { data: 'district', defaultContent: '-', render: renderPlainCell },
      { data: 'lga', defaultContent: '-', render: renderPlainCell },
      {
        data: 'source',
        orderable: false,
        render: function (_, __, row) {
          return buildStatusBadge(row);
        },
      },
      {
        data: null,
        orderable: false,
        searchable: false,
        render: function (_, __, row) {
          return buildActionsMenu(row);
        },
      },
    ],
    createdRow: function (row, data) {
      row.setAttribute('data-id', String(data.id));
    },
    preDrawCallback: function () {
      setSearchSpinnerVisible(true);
    },
    drawCallback: function () {
      const apiInstance = this.api();
      const pageInfo = apiInstance.page.info();

      state.indexedTableEventsBound = false;
      updateEmptyState(pageInfo.recordsDisplay > 0);
      updateSearchCount(pageInfo.recordsDisplay);

      bindIndexedTableInteractions();
      domUtils.reinitializeIcons();
      setSearchSpinnerVisible(false);
    },
  });

  window.$(tableElement).on('xhr.dt', function (_event, _settings, json) {
    const total = Number(json?.recordsTotal ?? json?.recordsFiltered ?? 0);
    updateSummaryCounts(total);
    updateSearchCount(Number(json?.recordsFiltered ?? total));
  });

  window.$(tableElement).on('processing.dt', function (_event, _settings, processing) {
    setSearchSpinnerVisible(Boolean(processing));
  });

  state.indexedDataTable = dataTable;
  state.indexedDataTableInitialized = true;

  if (state.pendingIndexedSearchTerm) {
    dataTable.search(state.pendingIndexedSearchTerm);
    state.pendingIndexedSearchTerm = '';
    dataTable.draw();
  }
}

function renderPlainCell(value) {
  if (value === null || value === undefined || value === '') {
    return '-';
  }
  return escapeHtml(String(value));
}

function setSearchSpinnerVisible(shouldShow) {
  const spinner = document.getElementById('search-indexed-spinner');
  if (spinner) {
    spinner.classList.toggle('hidden', !shouldShow);
  }
}

function updateEmptyState(hasData) {
  const container = document.getElementById('indexed-table-container');
  const emptyState = document.getElementById('indexed-empty-state');

  if (container) {
    container.classList.toggle('hidden', !hasData);
    container.style.display = hasData ? '' : 'none';
  }

  if (emptyState) {
    emptyState.classList.toggle('hidden', hasData);
    emptyState.style.display = hasData ? 'none' : '';
  }
}

function updateSearchCount(count) {
  const countElement = document.getElementById('search-indexed-count');
  const searchInput = document.getElementById('search-indexed-files');

  if (!countElement) {
    return;
  }

  const hasQuery = Boolean(searchInput && searchInput.value.trim().length > 0);

  if (Number.isFinite(count) && hasQuery) {
    const formatted = Number(count).toLocaleString();
    countElement.textContent = `${formatted} results`;
    countElement.classList.remove('hidden');
  } else {
    countElement.textContent = '';
    countElement.classList.add('hidden');
  }
}

function updateSummaryCounts(total) {
  if (!Number.isFinite(total)) {
    return;
  }

  const formatted = Number(total).toLocaleString();
  domUtils.updateElementText('indexed-files-count', formatted);
  domUtils.updateElementText('total-indexed-count', formatted);
}

async function renderIndexedFilesLegacy(searchTerm = '') {
  try {
    usingFallbackRenderer = true;
    state.indexedDataTable = null;
    state.indexedDataTableInitialized = false;
    setSearchSpinnerVisible(true);
    const targetPage = searchTerm ? 1 : state.currentIndexedPage;
    const data = await api.loadIndexedFiles(targetPage, state.itemsPerPage, searchTerm);
    const files = data?.indexed_files || [];

    state.indexedFiles = files;
    state.indexedFilesMap = new Map(files.map((item) => [String(item.id), item]));
    state.selectedIndexedFiles = [];
    updateSelectedIndexedFilesCount();
    if (data?.pagination?.per_page) {
      state.itemsPerPage = Number(data.pagination.per_page) || state.itemsPerPage;
    }

    if (!files.length) {
      updateEmptyState(false);
      updateIndexedPagination(data?.pagination);
      updateIndexedFilesCount();
      updateSearchCount(0);
      if (!searchTerm) {
        updateSummaryCounts(0);
      }
      return;
    }

    updateEmptyState(true);
    renderLegacyIndexedFilesTable(files);
    updateIndexedPagination(data?.pagination);
    updateIndexedFilesCount();

    const total = Number(data?.pagination?.total ?? files.length);
    if (!searchTerm) {
      updateSummaryCounts(total);
    }
    updateSearchCount(total);
  } catch (err) {
    console.error('Error rendering indexed files:', err);
    api.showApiErrorNotification('Failed to load indexed files');
    updateEmptyState(false);
  } finally {
    setSearchSpinnerVisible(false);
  }
}

/**
 * Legacy renderer used when DataTables is unavailable
 */
function renderLegacyIndexedFilesTable(files) {
  const table = document.getElementById('indexed-files-table');
  if (!table) {
    console.error('Indexed files table not found');
    return;
  }

  const tbody = table.querySelector('tbody');
  if (!tbody) {
    console.error('Table tbody not found');
    return;
  }

  // Clear existing rows
  tbody.innerHTML = '';

  // Add file rows
  files.forEach((file) => {
    const row = createIndexedFileRow(file);
    tbody.appendChild(row);
  });

  // Bind row interactions
  bindIndexedTableInteractions();

  // Reinitialize icons
  domUtils.reinitializeIcons();
}

/**
 * Create a table row for an indexed file
 */
function createIndexedFileRow(file) {
  const tr = document.createElement('tr');
  tr.className = 'hover:bg-gray-50 transition-colors';

  const rawFileId = typeof file.id !== 'undefined' && file.id !== null ? String(file.id) : '';
  tr.dataset.fileId = rawFileId;

  const shelfLocation = file.shelf_location ?? '-';
  const registry = file.registry ?? '-';
  const registryBatchNo = file.registry_batch_no
    ?? buildRegistryBatchFallback(registry, file.batch_no ?? file.sys_batch_no ?? null);
  const sysBatchNo = file.sys_batch_no ?? '-';
  const mdcBatchNo = file.mdc_batch_no ?? file.batch_no ?? '-';
  const groupNo = file.group_no ?? file.group ?? '-';
  const fileNumber = file.fileNumber || file.file_number || '-';
  const fileName = file.name || file.file_title || '-';
  const plotNumber = file.plotNumber || file.plot_number || '-';
  const indexedDate = file.indexed_at || file.date || file.created_at;
  const indexedBy = file.indexed_by || '-';
  const tpNumber = file.tpNumber || file.tp_no || '-';
  const lpknNumber = file.lpknNumber || file.lpkn_no || '-';
  const landUse = file.landUseType || '-';
  const district = file.district || '-';
  const lga = file.lga || '-';

  tr.innerHTML = `
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(shelfLocation)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(registry)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(registryBatchNo)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(sysBatchNo)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(mdcBatchNo)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(groupNo)}</td>
    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">${escapeHtml(fileNumber)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(fileName)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(plotNumber)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${formatDate(indexedDate)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(indexedBy)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(tpNumber)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(lpknNumber)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(landUse)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(district)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-gray-700">${escapeHtml(lga)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-center">${buildStatusBadge(file)}</td>
    <td class="px-4 py-3 whitespace-nowrap text-center relative" style="overflow: visible;">
      ${buildActionsMenu(file)}
    </td>
  `;

  return tr;
}

function buildRegistryBatchFallback(registry, batchNo) {
  const registryPart = registry && registry !== '-' ? registry : null;
  const batchPart = batchNo && batchNo !== '-' ? batchNo : null;

  if (!registryPart && !batchPart) {
    return '-';
  }

  return [registryPart, batchPart].filter(Boolean).join('/');
}

/**
 * Build status badge HTML
 */
function buildStatusBadge(file) {
  let statusLabel = file.status || file.type || file.source || 'Indexed';
  const normalizedStatus = String(statusLabel).trim().toLowerCase();

  if (normalizedStatus === 'property document') {
    statusLabel = 'Indexed';
  }

  const normalized = statusLabel.toLowerCase();
  const statusMap = {
    indexed: 'bg-green-100 text-green-800',
    'indexed & typed': 'bg-indigo-100 text-indigo-800',
    'indexed & scanned': 'bg-blue-100 text-blue-800',
    processing: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-gray-100 text-gray-800',
    application: 'bg-gray-100 text-gray-800',
    error: 'bg-red-100 text-red-800',
  };

  const className = statusMap[normalized] || statusMap.indexed;
  return `
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${className}">
      ${escapeHtml(statusLabel)}
    </span>
  `;
}

function buildActionsMenu(file) {
  const rawFileId = typeof file.id !== 'undefined' && file.id !== null ? String(file.id) : '';
  const safeFileId = escapeHtml(rawFileId);
  const editUrl = rawFileId ? `/fileindexing/${encodeURIComponent(rawFileId)}/edit` : '#';

  return `
    <div class="indexed-actions-wrapper inline-flex items-center justify-center">
      <button
        type="button"
        class="indexed-actions-trigger inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-2 text-gray-600 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
        data-file-id="${safeFileId}"
        aria-haspopup="true"
        aria-expanded="false"
      >
        <i data-lucide="more-horizontal" class="h-4 w-4"></i>
      </button>
      <div class="indexed-actions-menu hidden absolute right-0 top-full z-40 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
        <button type="button" class="indexed-action-view flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-file-id="${safeFileId}">
          <i data-lucide="eye" class="h-4 w-4"></i>
          View Details
        </button>
        <a href="${editUrl}" class="indexed-action-edit flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
          <i data-lucide="edit-3" class="h-4 w-4"></i>
          Edit
        </a>
        <a href="/fileindexing/batch-tracking-sheet?files=${encodeURIComponent(rawFileId)}" target="_blank" class="indexed-action-tracking flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
          <i data-lucide="file-text" class="h-4 w-4"></i>
          Tracking Sheet
        </a>
        <div class="border-t border-gray-100 my-1"></div>
        <button type="button" class="indexed-action-delete flex w-full items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50" data-file-id="${safeFileId}">
          <i data-lucide="trash-2" class="h-4 w-4"></i>
          Delete
        </button>
      </div>
    </div>
  `;
}

/**
 * Update indexed files count
 */
export async function updateIndexedFilesCount() {
  try {
    const stats = await api.loadStatistics();
    if (stats) {
      domUtils.updateElementText('indexed-files-count', stats.total_indexed || 0);
    }
  } catch (err) {
    console.warn('Could not update indexed files count:', err);
  }
}

// ============================================
// TABLE INTERACTIONS
// ============================================

/**
 * Bind event listeners to indexed table elements
 */
export function bindIndexedTableInteractions() {
  const table = document.getElementById('indexed-files-table');
  if (!table) {
    return;
  }

  closeAllActionMenus();

  table.querySelectorAll('.indexed-actions-trigger').forEach((trigger) => {
    trigger.setAttribute('aria-expanded', 'false');
  });

  table.removeEventListener('click', handleIndexedTableClick);
  table.addEventListener('click', handleIndexedTableClick);

  document.removeEventListener('click', handleIndexedTableDocumentClick);
  document.addEventListener('click', handleIndexedTableDocumentClick);
}

async function handleIndexedTableClick(event) {
  const trigger = event.target.closest('.indexed-actions-trigger');
  if (trigger) {
    event.preventDefault();
    const wrapper = trigger.closest('.indexed-actions-wrapper');
    if (!wrapper) return;
    const menu = wrapper.querySelector('.indexed-actions-menu');
    if (!menu) return;

    const isMenuVisible = !menu.classList.contains('hidden');
    closeAllActionMenus(wrapper);

    if (isMenuVisible) {
      menu.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
    } else {
      menu.classList.remove('hidden');
      trigger.setAttribute('aria-expanded', 'true');
    }

    return;
  }

  const viewBtn = event.target.closest('.indexed-action-view');
  if (viewBtn) {
    event.preventDefault();
    const fileId = parseInt(viewBtn.dataset.fileId, 10);
    closeAllActionMenus();
    if (!Number.isNaN(fileId)) {
      viewIndexedFile(fileId);
    }
    return;
  }

  const deleteBtn = event.target.closest('.indexed-action-delete');
  if (deleteBtn) {
    event.preventDefault();
    const fileId = parseInt(deleteBtn.dataset.fileId, 10);
    closeAllActionMenus();
    if (!Number.isNaN(fileId) && confirm('Are you sure you want to delete this file?')) {
      await deleteIndexedFile(fileId);
    }
    return;
  }

  const editLink = event.target.closest('.indexed-action-edit');
  if (editLink) {
    closeAllActionMenus();
  }

  const trackingLink = event.target.closest('.indexed-action-tracking');
  if (trackingLink) {
    closeAllActionMenus();
  }
}

function handleIndexedTableDocumentClick(event) {
  if (!event.target.closest('.indexed-actions-wrapper')) {
    closeAllActionMenus();
  }
}

function closeAllActionMenus(exceptWrapper = null) {
  document.querySelectorAll('.indexed-actions-wrapper').forEach((wrapper) => {
    if (exceptWrapper && wrapper === exceptWrapper) {
      return;
    }

    const menu = wrapper.querySelector('.indexed-actions-menu');
    const trigger = wrapper.querySelector('.indexed-actions-trigger');

    if (menu && !menu.classList.contains('hidden')) {
      menu.classList.add('hidden');
    }

    if (trigger) {
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
}

// ============================================
// SELECTION MANAGEMENT
// ============================================

/**
 * Toggle indexed file selection
 */
export function toggleIndexedFileSelection(fileId, isSelected) {
  if (isSelected) {
    if (!state.selectedIndexedFiles.includes(fileId)) {
      state.selectedIndexedFiles.push(fileId);
    }
  } else {
    state.selectedIndexedFiles = state.selectedIndexedFiles.filter((id) => id !== fileId);
  }

  updateSelectedIndexedFilesCount();
  updateSelectAllIndexedCheckbox();
}

/**
 * Toggle select all indexed files
 */
export function toggleSelectAllIndexed(isSelected) {
  const checkboxes = document.querySelectorAll('.indexed-file-checkbox');

  checkboxes.forEach((checkbox) => {
    checkbox.checked = isSelected;
    const fileId = parseInt(checkbox.dataset.fileId);
    toggleIndexedFileSelection(fileId, isSelected);
  });

  updateSelectedIndexedFilesCount();
  updateSelectAllIndexedCheckbox();
}

/**
 * Update select all checkbox state
 */
function updateSelectAllIndexedCheckbox() {
  const selectAllCheckbox = document.getElementById('select-all-indexed-checkbox');
  if (!selectAllCheckbox) return;

  const checkboxes = document.querySelectorAll('.indexed-file-checkbox');
  const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every((cb) => cb.checked);
  const someChecked = Array.from(checkboxes).some((cb) => cb.checked);

  selectAllCheckbox.checked = allChecked;
  selectAllCheckbox.indeterminate = someChecked && !allChecked;
}

/**
 * Update selected indexed files count display
 */
function updateSelectedIndexedFilesCount() {
  domUtils.updateElementText('selected-indexed-files-count', state.selectedIndexedFiles.length);
}

/**
 * Clear all indexed file selections
 */
export function clearAllIndexedSelections() {
  state.selectedIndexedFiles = [];
  document.querySelectorAll('.indexed-file-checkbox').forEach((checkbox) => {
    checkbox.checked = false;
  });
  updateSelectedIndexedFilesCount();
  updateSelectAllIndexedCheckbox();
}

// ============================================
// QUICK SELECT FUNCTIONS
// ============================================

/**
 * Select first 100 indexed files
 */
export async function selectFirst100Files() {
  try {
    const data = await api.loadIndexedFiles(1, 100);
    if (data && data.indexed_files) {
      state.selectedIndexedFiles = data.indexed_files.map((f) => f.id);

      // Update UI
      document.querySelectorAll('.indexed-file-checkbox').forEach((checkbox) => {
        checkbox.checked = state.selectedIndexedFiles.includes(parseInt(checkbox.dataset.fileId));
      });

      updateSelectedIndexedFilesCount();
      updateSelectAllIndexedCheckbox();
      api.showApiSuccessNotification('Selected 100 files');
    }
  } catch (err) {
    console.error('Error selecting files:', err);
    api.showApiErrorNotification('Failed to select files');
  }
}

/**
 * Select first 200 indexed files
 */
export async function selectFirst200Files() {
  try {
    const data = await api.loadIndexedFiles(1, 200);
    if (data && data.indexed_files) {
      state.selectedIndexedFiles = data.indexed_files.map((f) => f.id);

      // Update UI
      document.querySelectorAll('.indexed-file-checkbox').forEach((checkbox) => {
        checkbox.checked = state.selectedIndexedFiles.includes(parseInt(checkbox.dataset.fileId));
      });

      updateSelectedIndexedFilesCount();
      updateSelectAllIndexedCheckbox();
      api.showApiSuccessNotification('Selected 200 files');
    }
  } catch (err) {
    console.error('Error selecting files:', err);
    api.showApiErrorNotification('Failed to select files');
  }
}

// ============================================
// FILE OPERATIONS
// ============================================

/**
 * View indexed file details
 */
function viewIndexedFile(fileId) {
  console.log('Viewing file:', fileId);
  // Could open a modal or navigate to file details page
}

/**
 * Delete indexed file
 */
async function deleteIndexedFile(fileId) {
  try {
    await api.deleteIndexedFile(fileId);
    api.showApiSuccessNotification('File deleted successfully');

    if (state.indexedDataTableInitialized && state.indexedDataTable) {
      state.indexedDataTable.ajax.reload(null, false);
    } else {
      const row = document.querySelector(`tr[data-file-id="${fileId}"]`);
      if (row) {
        row.remove();
      }

      state.selectedIndexedFiles = state.selectedIndexedFiles.filter((id) => id !== fileId);
      updateSelectedIndexedFilesCount();
    }
  } catch (err) {
    console.error('Error deleting file:', err);
    api.showApiErrorNotification('Failed to delete file');
  }
}

// ============================================
// SEARCH & FILTERING
// ============================================

/**
 * Search indexed files with debouncing
 */
export function searchIndexedFiles(query) {
  const searchTerm = query || '';

  if (searchDebounceHandle) {
    clearTimeout(searchDebounceHandle);
  }

  state.pendingIndexedSearchTerm = searchTerm;
  setSearchSpinnerVisible(true);

  if (state.indexedDataTableInitialized && state.indexedDataTable) {
    searchDebounceHandle = setTimeout(() => {
      state.indexedDataTable.search(searchTerm).draw();
    }, SEARCH_DEBOUNCE_MS);
    return;
  }

  if (usingFallbackRenderer) {
    searchDebounceHandle = setTimeout(() => {
      renderIndexedFilesLegacy(searchTerm);
    }, SEARCH_DEBOUNCE_MS);
  } else {
    setSearchSpinnerVisible(false);
  }
}

export function refreshIndexedFiles() {
  if (state.indexedDataTableInitialized && state.indexedDataTable) {
    state.indexedDataTable.ajax.reload(null, false);
    return state.indexedDataTable;
  }

  return renderIndexedFilesLegacy(state.pendingIndexedSearchTerm || '');
}

// ============================================
// PAGINATION
// ============================================

/**
 * Update indexed pagination controls
 */
function updateIndexedPagination(pagination = {}) {
  const currentPage = pagination.current_page || state.currentIndexedPage;
  const perPage = pagination.per_page || state.itemsPerPage || 10;
  const total = pagination.total || state.indexedFiles.length;
  const totalPages = total > 0 ? Math.ceil(total / perPage) : 1;

  const prevBtn = document.getElementById('indexed-prev');
  const nextBtn = document.getElementById('indexed-next');

  if (prevBtn) {
    prevBtn.disabled = currentPage === 1;
  }

  if (nextBtn) {
    nextBtn.disabled = currentPage >= totalPages;
  }

  // Update page indicator if it exists
  const pageIndicator = document.getElementById('indexed-page-indicator');
  if (pageIndicator) {
    pageIndicator.textContent = total > 0 ? `Page ${currentPage} of ${totalPages}` : 'No pages';
  }
}

/**
 * Go to next page
 */
export async function goToIndexedNextPage() {
  if (state.indexedDataTableInitialized && state.indexedDataTable) {
    state.indexedDataTable.page('next').draw('page');
    return;
  }

  state.currentIndexedPage++;
  await renderIndexedFilesLegacy(state.pendingIndexedSearchTerm || '');
  scrollToTop();
}

/**
 * Go to previous page
 */
export async function goToIndexedPreviousPage() {
  if (state.indexedDataTableInitialized && state.indexedDataTable) {
    state.indexedDataTable.page('previous').draw('page');
    return;
  }

  if (state.currentIndexedPage > 1) {
    state.currentIndexedPage--;
    await renderIndexedFilesLegacy(state.pendingIndexedSearchTerm || '');
    scrollToTop();
  }
}

// ============================================
// TRACKING SHEET GENERATION
// ============================================

/**
 * Generate tracking sheets for selected indexed files
 */
export async function generateTrackingSheets() {
  if (state.selectedIndexedFiles.length === 0) {
    api.showApiErrorNotification('Please select files for tracking sheet generation');
    return;
  }

  try {
    const result = await api.generateTrackingSheets(state.selectedIndexedFiles);

    if (result && result.success) {
      api.showApiSuccessNotification('Tracking sheets generated successfully');
      clearAllIndexedSelections();
      // Optionally refresh the list
      await renderIndexedFiles();
    } else {
      api.showApiErrorNotification('Failed to generate tracking sheets');
    }
  } catch (err) {
    console.error('Error generating tracking sheets:', err);
    api.showApiErrorNotification('Failed to generate tracking sheets');
  }
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(value) {
  const text = value === undefined || value === null ? '' : String(value);
  if (text.length === 0) {
    return '';
  }
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  };
  return text.replace(/[&<>"']/g, (m) => map[m]);
}

/**
 * Format date for display
 */
function formatDate(dateString) {
  if (!dateString) return 'Unknown';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Scroll to top of indexed files table
 */
function scrollToTop() {
  const table = document.getElementById('indexed-files-table');
  if (table) {
    table.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
