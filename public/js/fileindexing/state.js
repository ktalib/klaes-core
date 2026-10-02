/**
 * File Indexing - Global State Management
 * Centralised store for UI modules (pending files, indexed files, tracking, AI processing)
 */

export const state = {
  // Pending files
  selectedFiles: [],
  pendingFiles: [],
  currentPendingPage: 1,

  // Indexed files
  selectedIndexedFiles: [],
  indexedFiles: [],
  currentIndexedPage: 1,
  indexedDataTable: null,
  indexedDataTableInitialized: false,
  indexedTableElement: null,
  indexedTableEventsBound: false,
  indexedFilesMap: new Map(),
  indexedFilesLastParams: { search: '', page: 1 },
  pendingIndexedSearchTerm: '',

  // AI processing
  indexingProgress: 0,
  currentStage: 'extract',

  // Navigation
  currentTab: 'pending',
  currentTrackingSubTab: 'not-generated',

  // Batch history
  batchHistory: [],
  currentBatchPage: 1,

  // Tracking sheet
  notGeneratedFiles: [],
  selectedNotGeneratedFiles: [],
  currentNotGeneratedPage: 1,
  notGeneratedItemsPerPage: 50,

  // Performance tuning
  lastApiCall: 0,
  pendingApiRequest: null,
  indexedApiRequest: null,
  API_THROTTLE_DELAY: 150,
  virtualScrollEnabled: true,
  MAX_VISIBLE_ROWS: 100,

  // Caching
  apiCache: {},
  CACHE_DURATION: 30000,
  MAX_CACHE_SIZE: 20,
  indexedFilesCache: null,
  INDEXED_FILES_CACHE_TTL: 60000,
  indexedFilesAbortController: null,
  indexedFilesLastLoadedAt: 0,

  // Diagnostics
  apiCallCount: 0,
  totalResponseTime: 0,

  // Selection helpers
  isPerformingSelection: false,
  itemsPerPage: 20,
};

export function resetApiCache() {
  state.apiCache = {};
  state.indexedFilesCache = null;
}

export function updateIndexedFilesCache(payload) {
  state.indexedFilesCache = payload;
  state.indexedFilesLastLoadedAt = Date.now();
}
