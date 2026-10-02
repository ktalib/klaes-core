/**
 * File Indexing - DOM Utilities & Initialization
 * Handles DOM element caching, event binding setup, and dynamic icon initialization
 */

import { state } from './state.js';
import { elementExists } from './dom.js';

// ============================================
// ICON INITIALIZATION
// ============================================

/**
 * Initialize Lucide icons on page load
 * Should be called after DOM is loaded and whenever new icons are added
 */
export function initializeIcons() {
  if (typeof lucide !== 'undefined') {
    try {
      lucide.createIcons();
    } catch (err) {
      console.error('Error initializing Lucide icons:', err);
    }
  }
}

/**
 * Reinitialize icons after DOM updates
 * Useful when new elements are dynamically added to the page
 */
export function reinitializeIcons() {
  if (typeof lucide !== 'undefined') {
    try {
      lucide.createIcons({ replaceAll: true });
    } catch (err) {
      console.warn('Error reinitializing icons:', err);
    }
  }
}

// ============================================
// DOM ELEMENT CACHING
// ============================================

/**
 * Cache all DOM element references on page load
 * Improves performance by avoiding repeated querySelector calls
 */
export function cacheDOMElements() {
  const missingElements = [];

  const criticalIds = [
    'main-tabs',
    'pending-tab',
    'batch-history-tab',
    'pending-files-list',
  ];

  criticalIds.forEach((id) => {
    if (!elementExists(id)) {
      missingElements.push(id);
    }
  });

  if (missingElements.length > 0) {
    console.warn(`Missing critical DOM elements: ${missingElements.join(', ')}`);
  }

  return missingElements.length === 0;
}

// ============================================
// TAB SWITCHING UTILITIES
// ============================================

/**
 * Get all tab buttons in the interface
 */
export function getTabButtons() {
  return document.querySelectorAll('#main-tabs .tab');
}

/**
 * Get specific tab by name (pending, indexed, batch-history, etc)
 */
export function getTabButton(tabName) {
  return document.querySelector(`#main-tabs .tab[data-tab="${tabName}"]`);
}

/**
 * Get tab content container by name
 */
export function getTabContent(tabName) {
  return document.getElementById(`${tabName}-tab`);
}

/**
 * Show specific tab and hide others
 */
export function showTab(tabName) {
  const indexedTabName = resolveIndexedTabName(tabName);

  document.querySelectorAll('.tab-content').forEach((panel) => {
    const isTargetPanel = panel.id === `${tabName}-tab`;
    panel.classList.toggle('hidden', !isTargetPanel);
    panel.classList.toggle('active', isTargetPanel);
  });

  getTabButtons().forEach((btn) => {
    const isActive = btn.dataset.tab === tabName;
    btn.classList.toggle('active', isActive);
  });

  const indexedPanels = document.querySelectorAll('[data-indexed-tab-panel]');
  if (indexedPanels.length > 0) {
    indexedPanels.forEach((panel) => {
      const panelName = panel.getAttribute('data-indexed-tab-panel');
      const shouldShow = panelName === indexedTabName;
      panel.classList.toggle('hidden', !shouldShow);
    });
  }

  const indexedButtons = document.querySelectorAll('[data-indexed-tab]');
  if (indexedButtons.length > 0) {
    indexedButtons.forEach((button) => {
      const targetName = button.getAttribute('data-indexed-tab');
      const isActive = targetName === indexedTabName;
      button.classList.toggle('indexed-tab-active', isActive);
      button.classList.toggle('border-blue-500', isActive);
      button.classList.toggle('bg-blue-50', isActive);
      button.classList.toggle('text-blue-700', isActive);
      button.classList.toggle('border-transparent', !isActive);
      button.classList.toggle('bg-slate-50', !isActive);
      button.classList.toggle('text-slate-600', !isActive);
    });
  }

  state.currentTab = tabName;
}

function resolveIndexedTabName(tabName) {
  const name = typeof tabName === 'string' ? tabName.toLowerCase() : '';
  const mapping = {
    pending: 'unindexed',
    unindexed: 'unindexed',
    indexing: 'digital',
    digital: 'digital',
    indexed: 'indexed',
  };

  return mapping[name] || name;
}

// ============================================
// FORM & DIALOG UTILITIES
// ============================================

/**
 * Show modal/dialog by ID
 */
export function showModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');

    // Trigger any animations
    setTimeout(() => {
      modal.classList.add('show');
    }, 10);
  }
}

/**
 * Hide modal/dialog by ID
 */
export function hideModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    modal.classList.remove('show');
  }
}

/**
 * Get all checked checkboxes in a container
 */
export function getCheckedCheckboxes(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return [];
  return Array.from(container.querySelectorAll('input[type="checkbox"]:checked'));
}

/**
 * Set checkbox state in container
 */
export function setCheckboxState(containerId, checked) {
  const container = document.getElementById(containerId);
  if (!container) return;
  const checkboxes = container.querySelectorAll('input[type="checkbox"]');
  checkboxes.forEach((checkbox) => {
    checkbox.checked = checked;
  });
}

// ============================================
// CONTENT RENDERING UTILITIES
// ============================================

/**
 * Clear container and show empty state
 */
export function showEmptyState(containerId, emptyStateId) {
  const container = document.getElementById(containerId);
  const emptyState = document.getElementById(emptyStateId);

  if (container) {
    container.innerHTML = '';
    container.classList.add('hidden');
    container.style.display = 'none';
  }

  if (emptyState) {
    emptyState.classList.remove('hidden');
    emptyState.style.display = '';
  }
}

/**
 * Show container and hide empty state
 */
export function hideEmptyState(containerId, emptyStateId) {
  const container = document.getElementById(containerId);
  const emptyState = document.getElementById(emptyStateId);

  if (container) {
    container.classList.remove('hidden');
    container.style.display = '';
  }

  if (emptyState) {
    emptyState.classList.add('hidden');
    emptyState.style.display = 'none';
  }
}

/**
 * Update text content of element
 */
export function updateElementText(elementId, text) {
  const el = document.getElementById(elementId);
  if (el) {
    el.textContent = text;
  }
}

/**
 * Update HTML content of element (use with caution - sanitize input)
 */
export function updateElementHTML(elementId, html) {
  const el = document.getElementById(elementId);
  if (el) {
    el.innerHTML = html;
  }
}

// ============================================
// VISIBILITY & STATE UTILITIES
// ============================================

/**
 * Toggle visibility of element
 */
export function toggleVisibility(elementId, show = null) {
  const el = document.getElementById(elementId);
  if (!el) return;

  if (show === null) {
    // Toggle
    el.classList.toggle('hidden');
  } else if (show) {
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}

/**
 * Add CSS class to element
 */
export function addClass(elementId, className) {
  const el = document.getElementById(elementId);
  if (el) {
    el.classList.add(className);
  }
}

/**
 * Remove CSS class from element
 */
export function removeClass(elementId, className) {
  const el = document.getElementById(elementId);
  if (el) {
    el.classList.remove(className);
  }
}

/**
 * Toggle CSS class on element
 */
export function toggleClass(elementId, className) {
  const el = document.getElementById(elementId);
  if (el) {
    el.classList.toggle(className);
  }
}

// ============================================
// INITIALIZATION ON PAGE LOAD
// ============================================

/**
 * Complete DOM initialization sequence
 * Call this once when document is ready
 */
export function initializePage() {
  // 1. Cache DOM elements
  const cached = cacheDOMElements();
  if (!cached) {
    console.warn('Some DOM elements could not be cached - page may not function correctly');
  }

  // 2. Initialize icons
  initializeIcons();

  // 3. Log successful initialization
  console.log('DOM utilities initialized successfully');
}
