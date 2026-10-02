/**
 * File Indexing - Main UI Controller
 * Orchestrates interactions between all module components
 * This is the main entry point for initializing the file indexing interface
 */

import { state } from './state.js';
import * as domUtils from './dom-utils.js';
import * as api from './api-utils.js';
import * as pendingFiles from './pending-files.js';
import * as aiProcessing from './ai-processing.js';

let hasInitialized = false;

// ============================================
// PAGE INITIALIZATION
// ============================================

/**
 * Initialize the entire file indexing interface
 * Call this when the DOM is ready
 */
export async function initializeFileIndexingInterface() {
  if (hasInitialized) {
    console.warn('File Indexing Interface already initialized; skipping duplicate request');
    return;
  }

  try {
    console.log('Initializing File Indexing Interface...');

    // 1. Initialize DOM utilities
    domUtils.initializePage();

    // 2. Kick off stats loading (don't block UI)
    const statsPromise = loadInitialData().catch((err) => {
      console.warn('Statistics failed to load:', err);
    });

    // 3. Setup event listeners
    setupEventListeners();

    // 4. Render initial views
    domUtils.showTab('pending');
    updateActionButtons('pending');
    await pendingFiles.renderPendingFiles();
    await statsPromise;

    hasInitialized = true;
    console.log('✅ File Indexing Interface initialized successfully');
  } catch (err) {
    console.error('❌ Error initializing File Indexing Interface:', err);
    api.showApiErrorNotification('Failed to initialize interface');
  }
}

/**
 * Load initial data on page load
 */
async function loadInitialData() {
  try {
    const stats = await api.loadStatistics();
    if (stats) {
      domUtils.updateElementText('pending-files-count', stats.pending_files || 0);
      domUtils.updateElementText('indexed-files-count', stats.total_indexed || 0);
      domUtils.updateElementText('total-indexed-count', stats.total_indexed || 0);
    }
  } catch (err) {
    console.warn('Could not load initial statistics:', err);
  }
}

// ============================================
// TAB NAVIGATION
// ============================================

/**
 * Setup tab switching listeners
 */
function setupTabNavigation() {
  const tabButtons = domUtils.getTabButtons();

  tabButtons.forEach((button) => {
    button.addEventListener('click', async (e) => {
      const externalUrl = button.dataset.externalUrl;
      if (externalUrl) {
        return;
      }

      e.preventDefault();

      if (button.classList.contains('disabled')) {
        return;
      }
      const tabName = button.dataset.tab;

      if (!tabName) return;

      // Show tab
      domUtils.showTab(tabName);
      updateActionButtons(tabName);

      // Load tab-specific content
      switch (tabName) {
        case 'pending':
          await pendingFiles.renderPendingFiles();
          break;
        case 'batch-history':
          switchTrackingSubTab(state.currentTrackingSubTab || 'not-generated');
          await loadBatchHistory();
          break;
        default:
          break;
      }
    });
  });
}

/**
 * Load and display batch history
 */
async function loadBatchHistory() {
  try {
    const data = await api.loadBatchHistory(1, 25);
    if (data) {
      renderBatchHistoryTable(data.batches || []);
    }
  } catch (err) {
    console.error('Error loading batch history:', err);
    api.showApiErrorNotification('Failed to load batch history');
  }
}

/**
 * Render batch history table
 */
function renderBatchHistoryTable(batches) {
  const tbody = document.getElementById('batch-history-table-body');
  if (!tbody) return;

  if (!batches || batches.length === 0) {
    domUtils.showEmptyState('batch-history-table-container', 'batch-history-empty-state');
    tbody.innerHTML = '';
    return;
  }

  domUtils.hideEmptyState('batch-history-table-container', 'batch-history-empty-state');
  tbody.innerHTML = '';

  batches.forEach((batch) => {
    const tr = document.createElement('tr');
    tr.className = 'hover:bg-gray-50 transition-colors';

    const batchId = batch.batch_id || batch.id || 'Unknown';
    const batchName = batch.batch_name || batch.name || '-';
    const batchNotes = batch.notes || '';
    const fileCount = typeof batch.file_count === 'number' ? batch.file_count : (batch.total_files || 0);
    const batchType = batch.batch_type || batch.type || 'manual';
    const batchStatus = batch.status || 'generated';
    const generatedAt = batch.generated_at || batch.created_at || null;
    const generatedBy = batch.generated_by_name || batch.generated_by || '';
    const printCount = typeof batch.print_count === 'number' ? batch.print_count : 0;
    const lastPrintedAt = batch.last_printed_at || null;

    tr.innerHTML = `
      <td class="px-6 py-4 whitespace-nowrap">
        <span class="font-medium text-gray-900">${escapeHtml(batchId)}</span>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
        <div class="font-medium text-gray-900">${escapeHtml(batchName)}</div>
        ${batchNotes ? `<div class="text-xs text-gray-500">${escapeHtml(batchNotes)}</div>` : ''}
      </td>
      <td class="px-6 py-4 whitespace-nowrap">
        <span class="font-medium text-gray-900">${fileCount}</span>
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        ${buildBatchTypeBadge(batchType)}
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        ${buildBatchStatusBadge(batchStatus)}
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
        ${generatedAt ? formatDate(generatedAt) : '—'}
        ${generatedBy ? `<div class="text-xs text-gray-500">by ${escapeHtml(generatedBy)}</div>` : ''}
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
        <span class="font-medium">${printCount}</span>
        ${lastPrintedAt ? `<div class="text-xs text-gray-500">Last: ${formatDate(lastPrintedAt)}</div>` : ''}
      </td>
      <td class="px-6 py-4 whitespace-nowrap text-sm">
        <div class="flex items-center gap-2">
          <button class="text-blue-600 hover:text-blue-800 font-medium batch-history-view-btn" data-batch-id="${escapeHtml(batchId)}">View</button>
          <button class="text-green-600 hover:text-green-800 font-medium batch-history-reprint-btn" data-batch-id="${escapeHtml(batchId)}">Reprint</button>
        </div>
      </td>
    `;
    tbody.appendChild(tr);
  });

  domUtils.reinitializeIcons();
}

function buildBatchStatusBadge(status) {
  const normalized = String(status || '').toLowerCase();
  const statusMap = {
    generated: { className: 'bg-blue-100 text-blue-800', label: 'Generated' },
    printing: { className: 'bg-yellow-100 text-yellow-800', label: 'Printing' },
    printed: { className: 'bg-green-100 text-green-800', label: 'Printed' },
    archived: { className: 'bg-slate-100 text-slate-800', label: 'Archived' },
    canceled: { className: 'bg-red-100 text-red-800', label: 'Canceled' },
  };

  const { className, label } = statusMap[normalized] || {
    className: 'bg-gray-100 text-gray-800',
    label: status || 'Unknown',
  };

  return `
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${className}">
      ${escapeHtml(label)}
    </span>
  `;
}

function buildBatchTypeBadge(batchType) {
  const normalized = String(batchType || '').toLowerCase();
  const typeMap = {
    manual: { className: 'bg-purple-100 text-purple-800', label: 'Manual' },
    auto_100: { className: 'bg-blue-100 text-blue-800', label: 'Auto 100' },
    auto_200: { className: 'bg-emerald-100 text-emerald-800', label: 'Auto 200' },
    automated: { className: 'bg-indigo-100 text-indigo-800', label: 'Automated' },
  };

  const { className, label } = typeMap[normalized] || {
    className: 'bg-gray-100 text-gray-800',
    label: batchType || 'Unknown',
  };

  return `
    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${className}">
      ${escapeHtml(label)}
    </span>
  `;
}

// ============================================
// PENDING FILES TAB EVENTS
// ============================================

/**
 * Setup pending files event listeners
 */
function setupPendingFilesEvents() {
  // Select all checkbox
  const selectAllPending = document.getElementById('select-all-checkbox');
  if (selectAllPending) {
    selectAllPending.addEventListener('change', (e) => {
      pendingFiles.toggleSelectAll(e.target.checked);
    });
  }

  // Begin indexing button
  const beginIndexingBtn = document.getElementById('begin-indexing-btn');
  if (beginIndexingBtn) {
    beginIndexingBtn.addEventListener('click', async () => {
      if (state.selectedFiles.length === 0) {
        api.showApiErrorNotification('Please select files to index');
        return;
      }

      try {
        // Switch to Digital Index tab before starting the pipeline
        domUtils.showTab('indexing');
        updateActionButtons('indexing');
        state.currentTab = 'indexing';

        beginIndexingBtn.disabled = true;
        beginIndexingBtn.textContent = 'Starting...';

        await aiProcessing.startAiIndexing();
      } catch (err) {
        console.error('Error starting indexing:', err);
        api.showApiErrorNotification('Failed to start indexing');
      } finally {
        beginIndexingBtn.disabled = false;
        beginIndexingBtn.textContent = 'Begin Indexing';
      }
    });
  }

  const startAiIndexingBtn = document.getElementById('start-ai-indexing-btn');
  if (startAiIndexingBtn) {
    const originalMarkup = startAiIndexingBtn.innerHTML;

    startAiIndexingBtn.addEventListener('click', async () => {
      if (state.selectedFiles.length === 0) {
        api.showApiErrorNotification('Please select files to index');
        return;
      }

      try {
        domUtils.showTab('indexing');
        updateActionButtons('indexing');
        state.currentTab = 'indexing';

        startAiIndexingBtn.disabled = true;
        startAiIndexingBtn.innerHTML = `
          <span class="inline-flex items-center gap-2">
            <i data-lucide="loader" class="h-4 w-4 animate-spin"></i>
            Starting...
          </span>
        `;
        domUtils.reinitializeIcons();

        await aiProcessing.startAiIndexing();
      } catch (err) {
        console.error('Error starting AI indexing:', err);
        api.showApiErrorNotification('Failed to start indexing');
      } finally {
        startAiIndexingBtn.innerHTML = originalMarkup;
        domUtils.reinitializeIcons();
        startAiIndexingBtn.disabled = state.selectedFiles.length === 0;
      }
    });
  }

  // Search input
  const searchInput = document.getElementById('search-pending-files');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      pendingFiles.searchPendingFiles(e.target.value);
    });
  }

  // Pagination
  const prevBtn = document.getElementById('pending-prev');
  if (prevBtn) {
    prevBtn.addEventListener('click', () => pendingFiles.goToPendingPreviousPage());
  }

  const nextBtn = document.getElementById('pending-next');
  if (nextBtn) {
    nextBtn.addEventListener('click', () => pendingFiles.goToPendingNextPage());
  }

  // Quick select
  const selectFirstBtn = document.getElementById('select-first-n-btn');
  if (selectFirstBtn) {
    selectFirstBtn.addEventListener('click', () => {
      const count = parseInt(prompt('How many files to select?') || '0');
      if (count > 0) {
        pendingFiles.selectFirstNFiles(count);
      }
    });
  }
}

// ============================================
// DIALOG & MODAL EVENTS
// ============================================

/**
 * Setup dialog and modal listeners
 */
function setupDialogEvents() {
  // New file dialog
  const newFileBtn = document.getElementById('new-file-index-btn');
  if (newFileBtn) {
    newFileBtn.addEventListener('click', () => {
      if (newFileBtn.getAttribute('aria-disabled') === 'true') {
        return;
      }
      domUtils.showModal('new-file-dialog-overlay');
    });
  }

  const closeDialogBtn = document.getElementById('close-dialog-btn');
  if (closeDialogBtn) {
    closeDialogBtn.addEventListener('click', () => {
      domUtils.hideModal('new-file-dialog-overlay');
    });
  }

  const cancelBtn = document.getElementById('cancel-btn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      domUtils.hideModal('new-file-dialog-overlay');
    });
  }

  // Close modal when clicking outside
  const dialogOverlay = document.getElementById('new-file-dialog-overlay');
  if (dialogOverlay) {
    dialogOverlay.addEventListener('click', (e) => {
      if (e.target === dialogOverlay) {
        domUtils.hideModal('new-file-dialog-overlay');
      }
    });
  }
}

// ============================================
// INITIALIZATION
// ============================================

/**
 * Setup all event listeners
 */
function setupEventListeners() {
  setupTabNavigation();
  setupPendingFilesEvents();
  setupTrackingSubTabs();
  setupDialogEvents();

  // Global error handler
  window.addEventListener('error', (e) => {
    console.error('Global error:', e);
  });

  // Refresh stats periodically (every 5 minutes)
  setInterval(() => {
    api.clearCache('statistics');
  }, 5 * 60 * 1000);
}

// ============================================
// AUTO-INITIALIZATION
// ============================================

// ============================================
// EXPORT MAIN FUNCTIONS
// ============================================

export {
  setupEventListeners,
  loadInitialData,
};

// ============================================
// UTILITY FUNCTIONS
// ============================================

/**
 * Enable or disable anchor-like action buttons based on active tab
 */
function updateActionButtons(activeTab) {
  const importCsvBtn = document.getElementById('import-csv-btn');
  const newFileBtn = document.getElementById('new-file-index-btn');

  const indexedTabExists = Boolean(domUtils.getTabButton('indexed'));
  const shouldEnableImport = indexedTabExists ? activeTab === 'indexed' : true;
  const shouldEnableNewFile = activeTab === 'pending';

  setAnchorEnabled(importCsvBtn, shouldEnableImport);
  setAnchorEnabled(newFileBtn, shouldEnableNewFile);
}

/**
 * Toggle anchor enabled state with visual feedback and href preservation
 */
function setAnchorEnabled(anchor, enabled) {
  if (!anchor) return;

  const disabledClasses = ['opacity-50', 'pointer-events-none', 'cursor-not-allowed'];

  if (enabled) {
    if (anchor.dataset.originalHref) {
      anchor.setAttribute('href', anchor.dataset.originalHref);
    }
    disabledClasses.forEach((cls) => anchor.classList.remove(cls));
    anchor.removeAttribute('aria-disabled');
    anchor.removeAttribute('tabindex');
  } else {
    const currentHref = anchor.getAttribute('href');
    if (currentHref && !anchor.dataset.originalHref) {
      anchor.dataset.originalHref = currentHref;
    }
    anchor.removeAttribute('href');
    disabledClasses.forEach((cls) => anchor.classList.add(cls));
    anchor.setAttribute('aria-disabled', 'true');
    anchor.setAttribute('tabindex', '-1');
  }
}

/**
 * Setup Tracking Sheet sub-tab interactions
 */
function setupTrackingSubTabs() {
  const subTabButtons = document.querySelectorAll('.tracking-sub-tab-btn');
  if (!subTabButtons.length) {
    return;
  }

  subTabButtons.forEach((button) => {
    button.addEventListener('click', async (event) => {
      event.preventDefault();

      const subTabName = button.dataset.subtab;
      if (!subTabName || state.currentTrackingSubTab === subTabName) {
        return;
      }

      switchTrackingSubTab(subTabName);

      if (subTabName === 'generated') {
        await loadBatchHistory();
      }
    });
  });

  switchTrackingSubTab(state.currentTrackingSubTab || 'not-generated');
}

/**
 * Toggle tracking sheet sub-tabs
 */
function switchTrackingSubTab(subTabName) {
  const subTabButtons = document.querySelectorAll('.tracking-sub-tab-btn');
  const subTabContents = document.querySelectorAll('.tracking-sub-content');

  subTabButtons.forEach((button) => {
    const isActive = button.dataset.subtab === subTabName;
    button.classList.toggle('active', isActive);
    button.classList.toggle('text-blue-600', isActive);
    button.classList.toggle('border-blue-600', isActive);
  button.classList.toggle('text-gray-500', !isActive);
  button.classList.toggle('border-transparent', !isActive);
  button.setAttribute('aria-selected', String(isActive));
  });

  subTabContents.forEach((content) => {
    const isActive = content.id === `${subTabName}-subtab`;
    content.classList.toggle('hidden', !isActive);
    content.setAttribute('aria-hidden', String(!isActive));
  });

  state.currentTrackingSubTab = subTabName;
}

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
    hour: '2-digit',
    minute: '2-digit',
  });
}
