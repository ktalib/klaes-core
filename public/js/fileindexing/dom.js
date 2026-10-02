/**
 * File Indexing - DOM Elements Cache
 * Stores references to frequently accessed DOM elements
 */

// ============================================
// MAIN CONTAINER ELEMENTS
// ============================================
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

// ============================================
// PENDING FILES TAB
// ============================================
const pendingFilesList = document.getElementById('pending-files-list');
const selectedFilesCount = document.getElementById('selected-files-count');
const beginIndexingBtn = document.getElementById('begin-indexing-btn');
const newFileIndexBtn = document.getElementById('new-file-index-btn');
const newFileDialogOverlay = document.getElementById('new-file-dialog-overlay');
const confirmSaveResultsBtn = document.getElementById('confirm-save-results-btn');

// ============================================
// FILE CREATION DIALOG
// ============================================
const closeDialogBtn = document.getElementById('close-dialog-btn');
const cancelBtn = document.getElementById('cancel-btn');
const createFileBtn = document.getElementById('create-file-btn');
const fileNumberTypeRadios = document.querySelectorAll('input[name="file-number-type"]');
const createFileIndexUrl = document.querySelector('meta[data-route-create]')?.getAttribute('data-route-create') || '';

// ============================================
// AI PROCESSING VIEW
// ============================================
const startAiIndexingBtn = document.getElementById('start-ai-indexing-btn');
const aiProcessingView = document.getElementById('ai-processing-view');
const progressBar = document.getElementById('progress-bar');
const progressPercentage = document.getElementById('progress-percentage');
const pipelineProgressBar = document.getElementById('pipeline-progress-bar');
const pipelineProgressLine = document.getElementById('pipeline-progress-line');
const pipelinePercentage = document.getElementById('pipeline-percentage');
const currentStageInfo = document.getElementById('current-stage-info');
const aiInsightsContainer = document.getElementById('ai-insights-container');

// ============================================
// INDEXED FILES TAB
// ============================================
const indexedFilesTable = document.getElementById('indexed-files-table');
const indexedFilesTableBody = document.getElementById('indexed-files-table-body');
const indexedEmptyState = document.getElementById('indexed-empty-state');
const indexedTableContainer = document.getElementById('indexed-table-container');
const selectAllIndexedCheckbox = document.getElementById('select-all-indexed-checkbox');
const selectedIndexedFilesCount = document.getElementById('selected-indexed-files-count');
const generateTrackingSheetBtn = document.getElementById('generate-tracking-sheets-btn');
const select100Btn = document.getElementById('select-100-btn');
const select200Btn = document.getElementById('select-200-btn');

// ============================================
// SEARCH & FILTER ELEMENTS
// ============================================
const searchPendingInput = document.getElementById('search-pending-files');
const searchIndexedInput = document.getElementById('search-indexed-files');
const searchPendingSpinner = document.getElementById('search-pending-spinner');
const searchIndexedSpinner = document.getElementById('search-indexed-spinner');

// ============================================
// PAGINATION ELEMENTS
// ============================================
const pendingPagination = document.getElementById('pending-pagination');
const indexedPagination = document.getElementById('indexed-pagination');
const pendingPrev = document.getElementById('pending-prev');
const pendingNext = document.getElementById('pending-next');
const indexedPrev = document.getElementById('indexed-prev');
const indexedNext = document.getElementById('indexed-next');

// ============================================
// BATCH HISTORY ELEMENTS
// ============================================
const batchHistoryTable = document.getElementById('batch-history-table');
const batchHistoryTableBody = document.getElementById('batch-history-table-body');
const batchHistoryEmptyState = document.getElementById('batch-history-empty-state');

// ============================================
// STATISTICS ELEMENTS
// ============================================
const pendingFilesCount = document.getElementById('pending-files-count');
const indexedFilesCount = document.getElementById('indexed-files-count');
const totalIndexedCount = document.getElementById('total-indexed-count');

// ============================================
// UTILITY FUNCTION: GET ELEMENT SAFELY
// ============================================
export function getElement(id) {
  const el = document.getElementById(id);
  if (!el) {
    console.warn(`Element with ID "${id}" not found`);
  }
  return el;
}

// ============================================
// UTILITY FUNCTION: CHECK ELEMENT EXISTS
// ============================================
export function elementExists(id) {
  return document.getElementById(id) !== null;
}
