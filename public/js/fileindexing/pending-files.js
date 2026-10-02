/**
 * File Indexing - Pending Files Module
 * Handles rendering, selection, and filtering of pending files
 */

import { state } from './state.js';
import * as domUtils from './dom-utils.js';
import * as api from './api-utils.js';

// ============================================
// RENDERING FUNCTIONS
// ============================================

/**
 * Render pending files list
 * Called when switching to pending tab or when files are updated
 */
export async function renderPendingFiles() {
  try {
    const data = await api.loadPendingFiles(state.currentPendingPage);
    const files = data?.pending_files || [];
    state.pendingFiles = files;

    if (!files.length) {
      domUtils.showEmptyState('pending-files-list', 'pending-empty-state');
      state.pendingFiles = [];
      updateSelectedFilesCount();
      updatePendingPagination(data?.pagination);
      return;
    }

    domUtils.hideEmptyState('pending-files-list', 'pending-empty-state');

    const container = document.getElementById('pending-files-list');
    if (!container) {
      console.error('Pending files container not found');
      return;
    }

    container.innerHTML = '';

    files.forEach((file) => {
      const fileElement = createPendingFileElement(file);
      container.appendChild(fileElement);
    });

    // Update pagination
    updatePendingPagination(data?.pagination);

    // Reinitialize icons for new elements
    domUtils.reinitializeIcons();

    // Update counter
    updateSelectedFilesCount();
    updatePendingFilesCount();
  } catch (err) {
    console.error('Error rendering pending files:', err);
    api.showApiErrorNotification('Failed to load pending files');
  }
}

/**
 * Create a single pending file element
 */
function createPendingFileElement(file) {
  const div = document.createElement('div');
  div.className = 'pending-file-item p-4 border border-gray-200 rounded-lg hover:shadow-md transition-shadow';
  const fileId = String(file.id);
  div.dataset.fileId = fileId;

  const isSelected = state.selectedFiles.some((selectedId) => String(selectedId) === fileId);
  const fileNumber = formatValue(file.fileNumber || file.file_number);
  const applicantName = formatValue(file.name || file.description);
  const secondaryParts = [];
  if (file.landUseType) {
    secondaryParts.push(formatValue(file.landUseType));
  }
  if (file.district) {
    secondaryParts.push(formatValue(file.district));
  }
  const secondaryLine = secondaryParts.join(' • ');

  let statusLabel = file.type || file.status || '';
  if (!statusLabel || statusLabel.toLowerCase() === 'application') {
    statusLabel = 'Pending Digital Index';
  }
  const isPendingDigitalStatus = statusLabel.toLowerCase() === 'pending digital index';
  statusLabel = formatValue(statusLabel);

  let badgeLabel = file.source || file.landUseType || '';
  if (!badgeLabel || badgeLabel.toLowerCase() === 'application') {
    badgeLabel = 'Pending Digital Index';
  }
  const isPendingDigitalBadge = badgeLabel.toLowerCase() === 'pending digital index';
  badgeLabel = formatValue(badgeLabel);

  const createdDate = file.date || file.created_at;

  div.innerHTML = `
    <div class="flex items-start gap-3">
      <!-- Checkbox -->
      <input type="checkbox" class="pending-file-checkbox mt-1" data-file-id="${fileId}" ${
    isSelected ? 'checked' : ''
  }>
      
      <!-- File Info -->
      <div class="flex-1 min-w-0">
        <h3 class="font-medium text-gray-900 truncate">
          <i data-lucide="file-text" class="inline-block w-4 h-4 mr-2 text-blue-500"></i>
          <span class="text-blue-600 font-semibold hover:underline cursor-pointer">${escapeHtml(fileNumber)}</span>
        </h3>
        <p class="text-sm text-gray-500">${escapeHtml(applicantName)}</p>
        ${secondaryLine ? `<p class="text-xs text-gray-400">${escapeHtml(secondaryLine)}</p>` : ''}
        <div class="flex items-center gap-2 mt-2 text-xs text-gray-400">
          ${renderStatusLabel(statusLabel, isPendingDigitalStatus)}
          <span>•</span>
          <span>${formatDate(createdDate)}</span>
        </div>
      </div>

      <!-- Badge -->
      <div class="flex-shrink-0">
        ${renderBadgeLabel(badgeLabel, isPendingDigitalBadge)}
      </div>
    </div>
  `;

  // Add checkbox event listener
  const checkbox = div.querySelector('.pending-file-checkbox');
  checkbox.addEventListener('change', () => toggleFileSelection(fileId, checkbox.checked));

  return div;
}

/**
 * Update pending files count display
 */
export async function updatePendingFilesCount() {
  try {
    const stats = await api.loadStatistics();
    if (stats) {
      domUtils.updateElementText('pending-files-count', stats.pending_files || 0);
      domUtils.updateElementText('total-indexed-count', stats.total_indexed || 0);
    }
  } catch (err) {
    console.warn('Could not update pending files count:', err);
  }
}

// ============================================
// SELECTION MANAGEMENT
// ============================================

/**
 * Toggle file selection
 */
export function toggleFileSelection(fileId, isSelected) {
  const normalizedId = String(fileId);

  if (isSelected) {
    const alreadySelected = state.selectedFiles.some((id) => String(id) === normalizedId);
    if (!alreadySelected) {
      state.selectedFiles.push(normalizedId);
    }
  } else {
    state.selectedFiles = state.selectedFiles.filter((id) => String(id) !== normalizedId);
  }

  updateSelectedFilesCount();
  updateSelectAllCheckbox();
}

/**
 * Toggle select all checkbox
 */
export function toggleSelectAll(isSelected) {
  const checkboxes = document.querySelectorAll('.pending-file-checkbox');

  checkboxes.forEach((checkbox) => {
    checkbox.checked = isSelected;
    const fileId = checkbox.dataset.fileId;
    toggleFileSelection(fileId, isSelected);
  });

  updateSelectedFilesCount();
  updateSelectAllCheckbox();
}

/**
 * Update select all checkbox state based on individual selections
 */
function updateSelectAllCheckbox() {
  const selectAllCheckbox = document.getElementById('select-all-checkbox');
  if (!selectAllCheckbox) return;

  const checkboxes = document.querySelectorAll('.pending-file-checkbox');
  const allChecked = checkboxes.length > 0 && Array.from(checkboxes).every((cb) => cb.checked);
  const someChecked = Array.from(checkboxes).some((cb) => cb.checked);

  selectAllCheckbox.checked = allChecked;
  selectAllCheckbox.indeterminate = someChecked && !allChecked;
}

/**
 * Update display of selected files count
 */
function updateSelectedFilesCount() {
  const totalOnPage = Array.isArray(state.pendingFiles) ? state.pendingFiles.length : 0;
  const selected = state.selectedFiles.length;
  domUtils.updateElementText('selected-files-count', `${selected} of ${totalOnPage} selected`);
  domUtils.updateElementText('ai-indexing-files-count', selected);
  domUtils.updateElementText('ai-selected-files-count', selected);
  domUtils.updateElementText('ai-processing-files-count', selected);

  const beginIndexingBtn = document.getElementById('begin-indexing-btn');
  if (beginIndexingBtn) {
    beginIndexingBtn.disabled = selected === 0;
  }

  const startAiIndexingBtn = document.getElementById('start-ai-indexing-btn');
  if (startAiIndexingBtn) {
    startAiIndexingBtn.disabled = selected === 0;
  }
}

/**
 * Clear all file selections
 */
export function clearAllSelections() {
  state.selectedFiles = [];
  document.querySelectorAll('.pending-file-checkbox').forEach((checkbox) => {
    checkbox.checked = false;
  });
  updateSelectedFilesCount();
  updateSelectAllCheckbox();
}

// ============================================
// SEARCH & FILTERING
// ============================================

/**
 * Search pending files with debouncing
 */
let pendingSearchTimeout;
export function searchPendingFiles(query) {
  clearTimeout(pendingSearchTimeout);

  // Show spinner
  domUtils.toggleVisibility('search-pending-spinner', true);

  pendingSearchTimeout = setTimeout(async () => {
    try {
      const data = await api.loadPendingFiles(1, 50, query);
      if (data) {
        renderPendingFilesFromData(data);
        state.selectedFiles = [];
        updateSelectedFilesCount();
      }
    } catch (err) {
      console.error('Search error:', err);
      api.showApiErrorNotification('Search failed');
    } finally {
      domUtils.toggleVisibility('search-pending-spinner', false);
    }
  }, 300);
}

/**
 * Render files from loaded data (used in search results)
 */
function renderPendingFilesFromData(data) {
  const container = document.getElementById('pending-files-list');
  if (!container) return;

  const files = data?.pending_files || [];
  state.pendingFiles = files;

  if (!files.length) {
    domUtils.showEmptyState('pending-files-list', 'pending-empty-state');
    return;
  }

  domUtils.hideEmptyState('pending-files-list', 'pending-empty-state');
  container.innerHTML = '';

  files.forEach((file) => {
    const fileElement = createPendingFileElement(file);
    container.appendChild(fileElement);
  });

  updatePendingPagination(data?.pagination);
  domUtils.reinitializeIcons();
  updateSelectedFilesCount();
}

// ============================================
// PAGINATION
// ============================================

/**
 * Update pagination controls
 */
function updatePendingPagination(pagination = {}) {
  const currentPage = pagination.current_page || state.currentPendingPage;
  const perPage = pagination.per_page || state.itemsPerPage || 10;
  const total = pagination.total || state.pendingFiles.length;
  const totalPages = total > 0 ? Math.ceil(total / perPage) : 1;

  const prevBtn = document.getElementById('pending-prev');
  const nextBtn = document.getElementById('pending-next');

  if (prevBtn) {
    prevBtn.disabled = currentPage === 1;
  }

  if (nextBtn) {
    nextBtn.disabled = currentPage >= totalPages;
  }

  // Update page indicator if it exists
  const pageIndicator = document.getElementById('pending-page-indicator');
  if (pageIndicator) {
    pageIndicator.textContent = total > 0 ? `Page ${currentPage} of ${totalPages}` : 'No pages';
  }
}

/**
 * Go to next page
 */
export async function goToPendingNextPage() {
  state.currentPendingPage++;
  await renderPendingFiles();
  scrollToTop();
}

/**
 * Go to previous page
 */
export async function goToPendingPreviousPage() {
  if (state.currentPendingPage > 1) {
    state.currentPendingPage--;
    await renderPendingFiles();
    scrollToTop();
  }
}

/**
 * Go to specific page
 */
export async function goToPendingPage(pageNumber) {
  state.currentPendingPage = pageNumber;
  await renderPendingFiles();
  scrollToTop();
}

// ============================================
// QUICK SELECT FUNCTIONS
// ============================================

/**
 * Select first N files (by API order)
 */
export async function selectFirstNFiles(count) {
  try {
    // Load first page with enough items
    const data = await api.loadPendingFiles(1, Math.max(count, 50));

    if (!data || !data.pending_files) {
      api.showApiErrorNotification('Could not load files');
      return;
    }

    state.selectedFiles = [];
    const filesToSelect = data.pending_files.slice(0, count);

    filesToSelect.forEach((file) => {
      const fileId = String(file.id);
      const alreadySelected = state.selectedFiles.some((id) => String(id) === fileId);
      if (!alreadySelected) {
        state.selectedFiles.push(fileId);
      }

      // Update checkbox UI
      const checkbox = document.querySelector(
        `.pending-file-checkbox[data-file-id="${fileId}"]`
      );
      if (checkbox) {
        checkbox.checked = true;
      }
    });

    updateSelectedFilesCount();
    updateSelectAllCheckbox();
    api.showApiSuccessNotification(`Selected ${count} files`);
  } catch (err) {
    console.error('Error selecting files:', err);
    api.showApiErrorNotification('Failed to select files');
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
  if (!dateString) return '-';
  const parsed = new Date(dateString);
  if (Number.isNaN(parsed.getTime())) {
    return '-';
  }
  return parsed.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Scroll to top of pending files list
 */
function scrollToTop() {
  const container = document.getElementById('pending-files-list');
  if (container) {
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

/**
 * Normalize value for display
 */
function formatValue(value) {
  if (value === undefined || value === null) {
    return '-';
  }

  const stringValue = String(value).trim();
  return stringValue === '' ? '-' : stringValue;
}

/**
 * Render status label with optional highlight
 */
function renderStatusLabel(label, highlight) {
  const classes = highlight ? 'text-amber-600 font-medium' : 'text-gray-500';
  return `<span class="${classes}">${escapeHtml(label)}</span>`;
}

/**
 * Render badge label with optional highlight
 */
function renderBadgeLabel(label, highlight) {
  const baseClasses = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium';
  const classes = highlight
    ? `${baseClasses} bg-amber-100 text-amber-700`
    : `${baseClasses} bg-gray-100 text-gray-800`;

  return `<span class="${classes}">${escapeHtml(label)}</span>`;
}
