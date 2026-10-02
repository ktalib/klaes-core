/**
 * File Indexing - API Utilities & Handlers
 * Centralized API calls for file indexing operations, batch history, statistics
 */

import { state } from './state.js';

let cachedApiBaseUrl = null;

// ============================================
// API CONFIGURATION
// ============================================

/**
 * Get API base URL from meta tag or window variable
 */
function getApiBaseUrl() {
  if (cachedApiBaseUrl) {
    return cachedApiBaseUrl;
  }

  const metaElement = document.querySelector('meta[data-api-url]');
  if (metaElement) {
    const metaUrl = metaElement.getAttribute('data-api-url');
    const normalizedMeta = normalizeBaseUrl(metaUrl);
    if (normalizedMeta) {
      cachedApiBaseUrl = normalizedMeta;
      return cachedApiBaseUrl;
    }
  }

  if (typeof window.apiBaseUrl !== 'undefined') {
    const normalizedWindowBase = normalizeBaseUrl(window.apiBaseUrl);
    if (normalizedWindowBase) {
      cachedApiBaseUrl = normalizedWindowBase;
      return cachedApiBaseUrl;
    }
  }
  
  if (typeof window.fileIndexingApiBaseUrl !== 'undefined') {
    const normalizedLegacyBase = normalizeBaseUrl(window.fileIndexingApiBaseUrl);
    if (normalizedLegacyBase) {
      cachedApiBaseUrl = normalizedLegacyBase;
      return cachedApiBaseUrl;
    }
  }

  cachedApiBaseUrl = '/fileindexing/api';
  return cachedApiBaseUrl;
}

/**
 * Ensure API base URL always uses the current origin to avoid mixed-content issues
 */
function normalizeBaseUrl(value) {
  if (value === undefined || value === null) {
    return null;
  }

  const trimmed = String(value).trim();
  if (!trimmed) {
    return null;
  }

  try {
    const parsed = new URL(trimmed, window.location.origin);
    let path = parsed.pathname || '';
    path = path.replace(/\/+$/, '');
    if (!path) {
      path = '/fileindexing/api';
    }
    return path.startsWith('/') ? path : `/${path}`;
  } catch (error) {
    let path = trimmed.replace(/^[a-z]+:\/\//i, '');
    if (!path.startsWith('/')) {
      path = `/${path}`;
    }
    path = path.replace(/\/+$/, '');
    return path || '/fileindexing/api';
  }
}

/**
 * Get Laravel CSRF token for POST/PUT/DELETE requests
 */
function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

/**
 * Build request headers for API calls
 */
function buildHeaders(includeContentType = true) {
  const headers = {
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': getCsrfToken(),
  };

  if (includeContentType) {
    headers['Content-Type'] = 'application/json';
  }

  return headers;
}

// ============================================
// STATISTICS API CALLS
// ============================================

/*
 * Load file indexing statistics
 * Returns: { pending_files, indexed_today, total_indexed }
 */
export async function loadStatistics() {
  // Check cache first
  const cacheKey = 'statistics';
  const cachedData = state.apiCache[cacheKey];
  if (cachedData && Date.now() - cachedData.timestamp < state.CACHE_DURATION) {
    return cachedData.data;
  }

  try {
    const response = await fetch(`${getApiBaseUrl()}/statistics`, {
      method: 'GET',
      headers: buildHeaders(false),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const payload = await response.json();
    const statistics = payload?.statistics || null;

    // Cache the result
    state.apiCache[cacheKey] = {
      data: statistics,
      timestamp: Date.now(),
    };

    return statistics;
  } catch (err) {
    console.error('Error loading statistics:', err);
    return null;
  }
}

/**
 * Load pending files list
 * Params: page, limit, search
 */
export async function loadPendingFiles(page = 1, limit = 50, search = '') {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(limit),
  });

  if (search) {
    params.set('search', search);
  }

  try {
    const response = await fetch(`${getApiBaseUrl()}/pending-files?${params}`, {
      method: 'GET',
      headers: buildHeaders(false),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const data = await response.json();
    const files = Array.isArray(data?.pending_files) ? data.pending_files : [];
    const pagination = data?.pagination || {};

    state.pendingFiles = files;
    state.currentPendingPage = pagination.current_page || page;

  const perPageValue = Math.max(pagination.per_page || limit, 1);
    const currentPageValue = pagination.current_page || page;
    const totalValue = typeof pagination.total === 'number' ? pagination.total : files.length;
    const fromValue = typeof pagination.from === 'number'
      ? pagination.from
      : totalValue === 0
        ? 0
        : (currentPageValue - 1) * perPageValue + 1;
    const toValue = typeof pagination.to === 'number'
      ? pagination.to
      : totalValue === 0
        ? 0
        : Math.min(fromValue + perPageValue - 1, totalValue);

    return {
      success: Boolean(data?.success),
      pending_files: files,
      pagination: {
        current_page: currentPageValue,
        per_page: perPageValue,
        total: totalValue,
        last_page: pagination.last_page || Math.max(1, Math.ceil(totalValue / perPageValue)),
        from: fromValue,
        to: toValue,
      },
    };
  } catch (err) {
    console.error('Error loading pending files:', err);
    return null;
  }
}

/**
 * Load indexed files list
 * Params: page, limit, search
 */
export async function loadIndexedFiles(page = 1, limit = 50, search = '') {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(limit),
  });

  if (search) {
    params.set('search', search);
  }

  try {
    const response = await fetch(`${getApiBaseUrl()}/indexed-files?${params}`, {
      method: 'GET',
      headers: buildHeaders(false),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const data = await response.json();
    const files = Array.isArray(data?.indexed_files) ? data.indexed_files : [];
    const pagination = data?.pagination || {};

    state.indexedFiles = files;
    state.currentIndexedPage = pagination.current_page || page;

    // Cache indexed files
    state.indexedFilesCache = {
      data: files,
      timestamp: Date.now(),
    };

  const perPageValue = Math.max(pagination.per_page || limit, 1);
    const currentPageValue = pagination.current_page || page;
    const totalValue = typeof pagination.total === 'number' ? pagination.total : files.length;
    const fromValue = typeof pagination.from === 'number'
      ? pagination.from
      : totalValue === 0
        ? 0
        : (currentPageValue - 1) * perPageValue + 1;
    const toValue = typeof pagination.to === 'number'
      ? pagination.to
      : totalValue === 0
        ? 0
        : Math.min(fromValue + perPageValue - 1, totalValue);

    return {
      success: Boolean(data?.success),
      indexed_files: files,
      pagination: {
        current_page: currentPageValue,
        per_page: perPageValue,
        total: totalValue,
        last_page: pagination.last_page || Math.max(1, Math.ceil(totalValue / perPageValue)),
        from: fromValue,
        to: toValue,
      },
    };
  } catch (err) {
    console.error('Error loading indexed files:', err);
    return null;
  }
}

// ============================================
// BATCH HISTORY API CALLS
// ============================================

/**
 * Load batch history
 * Params: page, limit, filter
 */
export async function loadBatchHistory(page = 1, limit = 25, filter = 'all') {
  const params = new URLSearchParams({
    page,
    limit,
    filter,
  });

  try {
    const response = await fetch(`${getApiBaseUrl()}/batch-history?${params}`, {
      method: 'GET',
      headers: buildHeaders(false),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const data = await response.json();
    state.batchHistory = data.batches || [];
    state.currentBatchPage = page;

    return data;
  } catch (err) {
    console.error('Error loading batch history:', err);
    return null;
  }
}

// ============================================
// FILE OPERATIONS API CALLS
// ============================================

/**
 * Delete a single indexed file
 */
export async function deleteIndexedFile(fileId) {
  try {
    const response = await fetch(`${getApiBaseUrl()}/indexed-files/${fileId}`, {
      method: 'DELETE',
      headers: buildHeaders(),
    });

    if (!response.ok) {
      throw new Error(`Delete failed: ${response.status}`);
    }

    const data = await response.json();

    // Clear cache
    state.indexedFilesCache = null;

    return data;
  } catch (err) {
    console.error('Error deleting indexed file:', err);
    throw err;
  }
}

/**
 * Delete multiple indexed files
 */
export async function deleteMultipleIndexedFiles(fileIds) {
  try {
    const response = await fetch(`${getApiBaseUrl()}/indexed-files/batch-delete`, {
      method: 'POST',
      headers: buildHeaders(),
      body: JSON.stringify({ file_ids: fileIds }),
    });

    if (!response.ok) {
      throw new Error(`Batch delete failed: ${response.status}`);
    }

    const data = await response.json();

    // Clear cache
    state.indexedFilesCache = null;

    return data;
  } catch (err) {
    console.error('Error deleting multiple indexed files:', err);
    throw err;
  }
}

/**
 * Begin indexing process for selected files
 */
export async function beginIndexing(fileIds) {
  try {
    const response = await fetch(`${getApiBaseUrl()}/begin-indexing`, {
      method: 'POST',
      headers: buildHeaders(),
      body: JSON.stringify({ file_ids: fileIds }),
    });

    if (!response.ok) {
      throw new Error(`Indexing start failed: ${response.status}`);
    }

    const data = await response.json();

    // Clear cache
    state.apiCache.statistics = null;

    return data;
  } catch (err) {
    console.error('Error beginning indexing:', err);
    throw err;
  }
}

/**
 * Get AI insights for files
 */
export async function getAiInsights(fileIds) {
  try {
    const response = await fetch(`${getApiBaseUrl()}/ai-insights`, {
      method: 'POST',
      headers: buildHeaders(),
      body: JSON.stringify({ file_ids: fileIds }),
    });

    if (!response.ok) {
      throw new Error(`AI insights request failed: ${response.status}`);
    }

    const data = await response.json();
    return data;
  } catch (err) {
    console.error('Error getting AI insights:', err);
    throw err;
  }
}

// ============================================
// TRACKING SHEET API CALLS
// ============================================

/**
 * Generate tracking sheets for selected files
 */
export async function generateTrackingSheets(fileIds) {
  try {
    const response = await fetch(`${getApiBaseUrl()}/generate-tracking-sheets`, {
      method: 'POST',
      headers: buildHeaders(),
      body: JSON.stringify({ file_ids: fileIds }),
    });

    if (!response.ok) {
      throw new Error(`Tracking sheet generation failed: ${response.status}`);
    }

    const data = await response.json();
    return data;
  } catch (err) {
    console.error('Error generating tracking sheets:', err);
    throw err;
  }
}

/**
 * Load not-generated tracking files
 */
export async function loadNotGeneratedFiles(page = 1, limit = 50) {
  const params = new URLSearchParams({
    page,
    limit,
  });

  try {
    const response = await fetch(`${getApiBaseUrl()}/not-generated-files?${params}`, {
      method: 'GET',
      headers: buildHeaders(false),
    });

    if (!response.ok) {
      throw new Error(`API error: ${response.status}`);
    }

    const data = await response.json();
    state.notGeneratedFiles = data.files || [];

    return data;
  } catch (err) {
    console.error('Error loading not-generated files:', err);
    return null;
  }
}

// ============================================
// ERROR HANDLING UTILITIES
// ============================================

/**
 * Format API error for display
 */
export function formatApiError(error) {
  if (error.response) {
    // Server responded with error status
    const status = error.response.status;
    const data = error.response.data || {};
    return data.message || `Server error: ${status}`;
  } else if (error.request) {
    // Request made but no response
    return 'No response from server. Please check your connection.';
  } else {
    // Other errors
    return error.message || 'An unexpected error occurred';
  }
}

/**
 * Show API error notification to user
 */
export function showApiErrorNotification(message, duration = 5000) {
  const notificationId = `api-error-${Date.now()}`;
  const notification = document.createElement('div');
  notification.id = notificationId;
  notification.className = 'fixed bottom-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded';
  notification.textContent = message;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.remove();
  }, duration);
}

/**
 * Show API success notification to user
 */
export function showApiSuccessNotification(message, duration = 3000) {
  const notificationId = `api-success-${Date.now()}`;
  const notification = document.createElement('div');
  notification.id = notificationId;
  notification.className = 'fixed bottom-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded';
  notification.textContent = message;

  document.body.appendChild(notification);

  setTimeout(() => {
    notification.remove();
  }, duration);
}

// ============================================
// CACHE MANAGEMENT
// ============================================

/**
 * Clear all API caches
 */
export function clearAllCaches() {
  state.apiCache = {};
  state.indexedFilesCache = null;
}

/**
 * Clear specific cache by key
 */
export function clearCache(key) {
  delete state.apiCache[key];
}

/**
 * Check if cache is valid
 */  
export function isCacheValid(key) {
  const cached = state.apiCache[key];
  if (!cached) return false;
  return Date.now() - cached.timestamp < state.CACHE_DURATION;
}
